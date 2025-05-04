<?php

declare(strict_types=1);

namespace App\Http\Requests\Adverts;

use App\Models\Action\Action;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Rules\IsLeafNode;
use App\Services\Adverts\CategoryAttributeService;
use App\Services\Adverts\PhotoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreRequest extends FormRequest
{
    protected PhotoService $photoService;

    protected CategoryAttributeService $categoryAttributeService;

    public function __construct(CategoryAttributeService $categoryAttributeService, PhotoService $photoService)
    {
        $this->categoryAttributeService = $categoryAttributeService;
        $this->photoService = $photoService;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // dd($this->input());
        // dd($this->query());
        return array_merge(
            $this->getRulesOnPost(),
            $this->getBasicRules(),
            $this->getAttributesRules(),
        );
    }

    /**
     * Prepare the data for validation.
     *
     * This method is automatically invoked by Laravel before validation occurs.
     * It is used to modify or add to the request data, ensuring all required
     * attributes are present and correctly formatted for validation.
     *
     * Here, we merge route parameters (category_id and region_id) into the
     * request data, allowing these attributes to be validated alongside other
     * input fields. This keeps validation rules clean and consistent by
     * ensuring all required data is part of the validated input.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST')) {
            $this->merge([
                'category_id' => $this->route('category')->id ?? null,
                'region_id' => $this->route('region')->id ?? null,
            ]);
        }
    }

    /**
     * Handle a failed validation attempt.
     * Is called automatically by Laravel’s validation system whenever the validation of the request data fails.
     * Overrides the default behavior when validation fails.
     *
     * @param  Validator  $validator  The validator instance with failed rules.
     *
     * @throws ValidationException Exception thrown to redirect with errors.
     */
    protected function failedValidation(Validator $validator): void
    {
        // https://emekambah.medium.com/taking-control-of-laravel-formrequest-response-abdea97d3475

        // Only updates allow saving pending photos;
        // for creates, photos cannot have status 'pending' since the advert does not yet exist.
        if ($this->isMethod('PUT')) {
            // Perform an additional validation on `files` before saving them as pending images.
            $this->validate([
                'files' => ['array', 'max:12'],
                'files.*' => 'max:4096|image|mimes:jpg,jpeg,png',
            ]);

            // If files have been chosen and a validation rule fails, manage pending photos for this advert.
            if ($this->hasFile('files')) {
                // Delete any previously pending photos to prepare for new uploads.
                $this->photoService->deletePendingPhotos($this->advert);

                // Add the current set of uploaded files to storage and the database with status 'pending'
                // so they can be shown as thumbnails.
                $this->photoService->addPendingPhotos($this->advert, $this);
            }
        }

        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }

    /**
     * Get the validation rules specific to POST requests.
     *
     * @return array The array of validation rules specific to POST requests.
     */
    private function getRulesOnPost(): array
    {
        if ($this->isMethod('POST')) {
            // Retrieve the current category from the route parameter
            $category = $this->route('category');

            // Get actions for the category; used to validate action availability
            $adjustedActions = $category->getAdjustedActions($category->ancestorsAndMe());

            return [
                'category_id' => [
                    'required',
                    'integer',
                    new IsLeafNode(Category::class), // category_id must be a leaf node in the category hierarchy
                ],

                'region_id' => [
                    'required',
                    'integer',
                    new IsLeafNode(Region::class), // region_id must be a leaf node in the region hierarchy
                ],

                'action' => [
                    'nullable', // action is optional unless category has actions
                    Rule::requiredIf(fn () => $adjustedActions->isNotEmpty()), // enforce required if category has actions
                    'integer', // ensure action is an integer
                    Rule::in($adjustedActions->pluck('id')), // validate action is within category's actions list
                ],
            ];
        }

        // Return an empty array if the method is not POST
        return [];
    }

    private function getBasicRules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:64'],
            'content' => ['required', 'string', 'max:65535'],
            // 'price' => 'required|numeric|min:0|max:1000000000',
            'files' => [
                // on update -> ignore files required
                Rule::requiredIf(function () {
                    if ($this->isMethod('POST')) {
                        return true;
                    } elseif ($this->method() === 'PUT' && ! $this->advert->hasPhotos()) {
                        return true;
                    }

                    return false;
                }),
                'array',
                'max:12',
            ],
            'files.*' => 'max:4096|image|mimes:jpg,jpeg,png',
        ];
    }

    /**
     * Retrieve validation rules for dynamic attributes associated with the form.
     *
     * @return array An associative array of validation rules for each attribute, indexed by attribute ID.
     */
    private function getAttributesRules(): array
    {
        // todo: attributesIds in attributes array can only be from category's attributes

        $attributesRules = []; // Initialize attributes rules.
        $availableAttributes = $this->getAvailableAttributes(); // Get available attributes for the form.
        $requiredAttributes = $this->getRequiredAttributes($availableAttributes); // Get required attributes for the form.

        foreach ($availableAttributes as $attribute) {
            $rules = [$requiredAttributes->contains($attribute) ? 'required' : 'nullable']; // Check if the attribute is required.
            $rules = array_merge($rules, $this->getAttributeSpecificRules($attribute));

            $attributesRules['attribute_' . $attribute->id] = $rules; // Add the rules to the attributes array.
        }

        return $attributesRules;
    }

    /**
     * Define validation rules based on the attribute type.
     */
    private function getAttributeSpecificRules($attribute): array
    {
        if ($attribute->isInteger()) {
            return ['integer'];
        } elseif ($attribute->isFloat()) {
            return ['numeric', 'min:0.01'];
        } elseif ($attribute->isBoolean()) {
            return ['boolean'];
        } elseif ($attribute->isJson()) {
            return ['array', 'max:175', Rule::in((array) $attribute->options)]; // Ensure the value is within defined options (tested).
        } elseif ($attribute->isSelect()) {
            return [Rule::in($attribute->options), 'max:1600'];
        }

        return ['string', 'max:64'];
    }

    private function getAvailableAttributes(): Collection
    {
        if ($this->isMethod('POST')) {
            $category = $this->route('category');
        } elseif ($this->isMethod('PUT')) {
            // return $this->advert->category->allAttributes();
            ($category = $this->advert->category);
        } else {
            $category = new Category();
        }

        return $this->categoryAttributeService->getAvailableAncestorsAndSelfAttributes($category);
    }

    private function getRequiredAttributes(Collection $availableAttributes): Collection
    {
        if ($this->isMethod('PUT')) {
            return $this->categoryAttributeService->getRequiredAttributes($availableAttributes, $this->advert->action);
        }

        $action = $this['action'] ? Action::findOrFail($this['action']) : null;

        return $this->categoryAttributeService->getRequiredAttributes($availableAttributes, $action);
    }
}
