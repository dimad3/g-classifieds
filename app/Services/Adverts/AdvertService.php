<?php

declare(strict_types=1);

namespace App\Services\Adverts;

use App\Events\Advert\ModerationPassed;
use App\Http\Requests\Adverts\RejectRequest;
use App\Http\Requests\Adverts\StoreRequest;
use App\Jobs\DeleteAdvertFromElasticsearchJob;
use App\Jobs\IndexAdvertInElasticsearchJob;
use App\Models\Action\Action;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\AttributeValue;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Class AdvertService
 *
 * Manages the core operations of advert handling, including creation, updating,
 * status changes, and interactions with Elasticsearch. This service centralizes
 * business logic for adverts, ensuring data integrity through transactions and
 * performing necessary asynchronous jobs to synchronize the data.
 *
 * Dependencies:
 * - PhotoService: Handles photo management for adverts.
 * - Elasticsearch Jobs: Jobs for indexing and deleting adverts in Elasticsearch.
 */
class AdvertService
{
    protected PhotoService $photoService;

    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(PhotoService $photoService, CategoryAttributeService $categoryAttributeService)
    {
        $this->photoService = $photoService;
        $this->categoryAttributeService = $categoryAttributeService;
    }

    /**
     * Store a newly created advert with associated attributes and photos in storage.
     *
     * This method processes user input to create and store an advert along with
     * its associated category, action, region, attributes and photos.
     *
     * @param  int  $userId  User ID of the advert owner
     * @param  int  $categoryId  Category ID assigned to the advert
     * @param  int  $regionId  Region ID assigned to the advert
     * @param  StoreRequest  $request  Incoming request with advert data
     * @return Advert The stored advert model
     *
     * @throws \Throwable
     */
    public function storeAdvert($userId, $categoryId, $regionId, StoreRequest $request): Advert
    {
        // Retrieve associated models
        /** @var User $user */
        $user = User::findOrFail($userId);
        /** @var Category $category */
        $category = Category::findOrFail($categoryId);
        /** @var Region $region */
        $region = Region::findOrFail($regionId);
        /** @var Action|null $action */
        $action = $request['action'] ? Action::findOrFail($request['action']) : null;

        return DB::transaction(function () use ($request, $user, $category, $action, $region) {
            /**
             * make() - create and return an un-saved model instance.
             * vendor\laravel\framework\src\Illuminate\Database\Eloquent\Builder.php
             *
             * @param  array  $attributes
             * @return \Illuminate\Database\Eloquent\Model|static
             */
            /** @var Advert $advert */
            $advert = Advert::make([
                'title' => $request['title'],
                'content' => $request['content'],
                'status' => Advert::STATUS_DRAFT,
            ]);

            // Associate user, category, action, and region with the advert
            $advert->user()->associate($user);
            $advert->category()->associate($category);
            $advert->action()->associate($action);
            $advert->region()->associate($region);

            $advert->saveOrFail();

            // Process and save advert attributes
            foreach ($this->categoryAttributeService->getAncestorsAndSelfAttributes($category) as $attribute) {
                // "??" - is null coalescing operator, it is like isset()
                // The expression (expr1) ?? (expr2) evaluates to expr2 if expr1 is null, and expr1 otherwise.
                // https://stackoverflow.com/questions/53610622/what-does-double-question-mark-operator-mean-in-php
                $attributeValue = $request['attribute_' . $attribute->id] ?? null;
                if (! empty($attributeValue)) {
                    // Convert arrays to JSON if necessary
                    if (is_array($attributeValue)) {
                        $attributeValue = json_encode($attributeValue, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    }
                    // Format price attributes
                    if ($attribute->isPrice()) {
                        $attributeValue = format_price($attributeValue);
                    }
                    // save advert attributes
                    $advert->attributesValues()->create([
                        'attribute_id' => $attribute->id,
                        'value' => $attributeValue,
                    ]);
                }
            }

            // save advert photos
            $this->photoService->addActivePhotos($advert, $request);

            return $advert;
        });
    }

    /**
     * Update an existing advert with new data attributes and photos.
     *
     * This method updates the advert details and associated attributes, handling
     * photos and syncing with Elasticsearch.
     *
     * @param  Advert  $advert  The advert to update
     * @param  StoreRequest  $request  Request containing updated advert data
     *
     * @throws \Throwable
     */
    public function updateAdvert(Advert $advert, StoreRequest $request): void
    {
        DB::transaction(function () use ($request, $advert): void {
            $advert->update($request->only(['title', 'content']));

            // Delete existing attributes and re-add updated ones
            $advert->attributesValues()->delete();
            $category = $advert->category;
            $availableAttributes = $this->categoryAttributeService->getAvailableAncestorsAndSelfAttributes($category);
            foreach ($availableAttributes as $attribute) {
                $attributeValue = $request['attribute_' . $attribute->id] ?? null;
                if (! empty($attributeValue)) {
                    // Convert arrays to JSON if necessary
                    if (is_array($attributeValue)) {
                        $attributeValue = json_encode($attributeValue, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    }
                    // Format price attributes
                    if ($attribute->isPrice()) {
                        $attributeValue = format_price($attributeValue);
                    }
                    // save advert attributes
                    $advert->attributesValues()->create([
                        'attribute_id' => $attribute->id,
                        'value' => $attributeValue,
                    ]);
                }
            }

            // Update and manage photos
            $this->photoService->updatePhotos($advert, $request);

            // Clear views to reflect updated information
            Artisan::call('view:clear');

            // After the transaction commits, dispatch Elasticsearch indexing job if advert is active
            DB::afterCommit(function () use ($advert): void {
                if ($advert->isActive()) {
                    // Dispatches a job to add the advert in Elasticsearch,
                    // helping to keep the Elasticsearch data in sync with the db state.
                    dispatch(new IndexAdvertInElasticsearchJob($advert));
                }
            });
        });
    }

    /**
     * Delete an advert and dispatch removal from Elasticsearch.
     *
     * @param  int  $id  The ID of the advert to delete
     *
     * @throws \Throwable
     */
    public function destroy(int $id): void
    {
        $advert = $this->getAdvert($id);
        $folderPath = "public/adverts/{$id}";

        DB::transaction(function () use ($id, $advert, $folderPath): void {
            $advert->delete();

            // Delete folder and its contents
            Storage::deleteDirectory($folderPath);

            // Dispatch Elasticsearch removal job after commit
            DB::afterCommit(function () use ($id): void {
                dispatch(new DeleteAdvertFromElasticsearchJob($id));
            });
        });
    }

    // Manage Adverts === === === === === === === === === === === === === === === ===

    /**
     * Activate an advert updating its status to active
     * and making it publicly visible and searchable.
     *
     * @param  int  $id  The ID of the advert to activate
     *
     * @throws \Throwable
     */
    public function activate(int $id): void
    {
        $advert = $this->getAdvert($id);
        DB::transaction(function () use ($advert): void {
            $advert->activate(Carbon::now());

            // Dispatch Elasticsearch indexing and moderation-passed event
            DB::afterCommit(function () use ($advert): void {
                // Dispatches a job to add the advert in Elasticsearch,
                // helping to keep the Elasticsearch data in sync with the db state.
                dispatch(new IndexAdvertInElasticsearchJob($advert));
                event(new ModerationPassed($advert));
            });
        });
    }

    /**
     * Close an advert updating its status to closed and making it inactive.
     *
     * @param  int  $id  The ID of the advert to close
     *
     * @throws \Throwable
     */
    public function close(int $id): void
    {
        $advert = $this->getAdvert($id);
        DB::transaction(function () use ($advert): void {
            $advert->close();

            // Dispatch job to remove advert from Elasticsearch
            DB::afterCommit(function () use ($advert): void {
                // Dispatches a job to remove the advert from Elasticsearch,
                // helping to keep the Elasticsearch data in sync with the db state.
                dispatch(new DeleteAdvertFromElasticsearchJob($advert->id));
            });
        });
    }

    /**
     * Mark an advert as expired, updating its status to expired.
     *
     * @param  int  $id  The ID of the advert to mark as expired
     */
    public function expire(int $id): void
    {
        $advert = $this->getAdvert($id);
        $advert->expire();
    }

    /**
     * Reject an advert updating its status to 'draft' and providing a reason.
     *
     * @param  int  $id  The ID of the advert to reject
     * @param  RejectRequest  $request  Contains the rejection reason
     *
     * @throws \Throwable
     */
    public function reject(int $id, RejectRequest $request): void
    {
        $advert = $this->getAdvert($id);
        DB::transaction(function () use ($advert, $request): void {
            $advert->reject($request['reason']);

            // Dispatch job to remove advert from Elasticsearch
            DB::afterCommit(function () use ($advert): void {
                dispatch(new DeleteAdvertFromElasticsearchJob($advert->id));
            });
        });
    }

    // Cabinet methods === === === === === === === === === === === === === === === ===

    /**
     * Set an advert's status to 'moderation'.
     *
     * @param  int  $id  The ID of the advert to send to moderation
     */
    public function sendToModeration(int $id): void
    {
        $advert = $this->getAdvert($id);
        $advert->sendToModeration();
    }

    /**
     * Restore an advert updating its status to draft.
     *
     * @param  int  $id  The ID of the advert to restore
     */
    public function restore($id): void
    {
        $advert = $this->getAdvert($id);
        $advert->restore();
    }

    /**
     * Restore an advert updating its status to draft.
     *
     * @param  int  $id  The ID of the advert to restore
     */
    public function revertToDraft($id): void
    {
        $advert = $this->getAdvert($id);
        $advert->revertToDraft();
    }

    // HELPERS sub-methods === === === === === === === === === === === === === === === ===

    /**
     * Get the available and required attributes for a given advert with assigned values.
     *
     * @param  Advert  $advert  The advert instance for which to retrieve attributes.
     * @return array An associative array containing 'availableAttributes' and 'requiredAttributes'.
     */
    public function getAdvertAttributes(Advert $advert)
    {
        $category = $advert->category; // Retrieve the category associated with the advert
        $action = $advert->action; // Retrieve the action associated with the advert

        // Retrieve available attributes for the category and action
        $availableAttributes = $this->getAvailableAttributesWithValues($advert);
        // Retrieve required attributes for the category and action (if the action provided)
        $requiredAttributes = $this->categoryAttributeService->getRequiredAttributes($availableAttributes, $action);

        // Return both available and required attributes as an associative array
        return compact('availableAttributes', 'requiredAttributes');
    }

    /**
     * Get all attributes which belongs to advert->category and values which are assigned to them
     */
    public function getAvailableAttributesWithValues(Advert $advert): Collection
    {
        $category = $advert->category;
        $action = $advert->action;
        $availableAttributes = $this->categoryAttributeService->getAllAvailableAttributes($category, $action);
        $assignedValues = AttributeValue::forAdvert($advert)->get();

        $availableAttributesWithValues = $availableAttributes->map(function (Attribute $attribute) use ($assignedValues) {
            if ($assignedValues->contains('attribute_id', $attribute->id)) {
                $attribute->value = $assignedValues->where('attribute_id', $attribute->id)->first()->value;

                return $attribute;
            }
            $attribute->value = '';

            return $attribute;
        });

        return $availableAttributesWithValues;
    }

    /**
     * Retrieve an advert by its ID.
     *
     * @param  int  $id  The ID of the advert
     */
    private function getAdvert(int $id): Advert
    {
        return Advert::findOrFail($id);
    }
}
