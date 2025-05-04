<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet\Adverts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adverts\StoreRequest;
use App\Http\Requests\Cabinet\AdvertsIndexRequest;
use App\Models\Adverts\Advert\Advert;
use App\Services\Adverts\AdvertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/*
|--------------------------------------------------------------------------
| This controller is used to provide for Authenticated User
| functionality to manage his own adverts
|--------------------------------------------------------------------------
|
*/

class AdvertController extends Controller
{
    private $advertService;

    public function __construct(AdvertService $advertService)
    {
        $this->advertService = $advertService;
    }

    public function index(AdvertsIndexRequest $request)
    {
        $validated = $request->validated();

        // Start query building
        $query = Advert::forUser(Auth::user());

        // Apply other filters
        if (! empty($value = $validated['expires_at'] ?? null)) {
            try {
                $date = Carbon::createFromFormat('d.m.Y', $value); // Convert date format from dd.mm.yyyy to Y-m-d
                // copy() method creates a clone of the original Carbon instance.
                // This prevents mutation of the $date instance
                $query->whereBetween('expires_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ]);
            } catch (\Exception $e) {
                // Invalid date, skip filtering
                // Handle invalid date input (optional: log or ignore)
            }
        }

        if (! empty($value = $validated['title'] ?? null)) {
            $query->where('title', 'like', '%' . $value . '%');
        }

        if (! empty($value = $validated['region'] ?? null)) {
            $query->where('region_id', $value);
        }

        if (! empty($value = $validated['category'] ?? null)) {
            $query->where('category_id', $value);
        }

        if (! empty($value = $validated['status'] ?? null)) {
            $query->where('status', $value);
        }

        ($adverts = $query->select(['id', 'title', 'region_id', 'category_id', 'status', 'reject_reason', 'expires_at'])
            ->orderByDesc('expires_at')
            ->with(['category:id,name', 'region:id,name', 'defaultPhoto'])
            // ->orderBy('expires_at')
            ->paginate(40)
            ->withQueryString()); // Retain query string parameters

        return view('cabinet.adverts.index', [
            'adverts' => $adverts,
            'statuses' => Advert::statusesList(),
            'advertsCount' => $adverts->total(),
        ]);
    }

    /**
     * Show the form for editing an advert.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(Advert $advert)
    {
        $this->checkAccess($advert);

        $advertAttributes = $this->advertService->getAdvertAttributes($advert);

        return view('cabinet.adverts.create_or_edit', compact('advert', 'advertAttributes'));
    }

    /**
     * Update the advert in storage.
     */
    public function update(StoreRequest $request, Advert $advert)
    {
        $this->checkAccess($advert);
        try {
            $this->advertService->updateAdvert($advert, $request);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('adverts.show', $advert);
    }

    /**
     * Remove the advert from storage.
     */
    public function destroy(Advert $advert)
    {
        $this->checkAccess($advert);
        try {
            $this->advertService->destroy($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.adverts.index');
    }

    /**
     * Update advert's status to 'moderation'
     */
    public function sendToModeration(Advert $advert)
    {
        $this->checkAccess($advert);
        try {
            $this->advertService->sendToModeration($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.adverts.index');
    }

    /**
     * Update advert's status to 'closed'
     */
    public function close(Advert $advert)
    {
        $this->checkAccess($advert);
        try {
            $this->advertService->close($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.adverts.index');
    }

    /**
     * Update advert's status to 'draft'
     */
    public function restore(Advert $advert)
    {
        $this->checkAccess($advert);
        try {
            $this->advertService->restore($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('adverts.show', $advert);
    }

    /**
     * Update advert's status to 'draft'
     */
    public function revertToDraft(Advert $advert)
    {
        $this->checkAccess($advert);
        try {
            $this->advertService->revertToDraft($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('adverts.show', $advert);
    }

    public function advertsWithUserDialogs()
    {
        // 1-st approach
        $adverts = Advert::withUserDialogs()
            ->select(['advert_adverts.id', 'advert_adverts.title', 'advert_adverts.user_id'])
            // https://stackoverflow.com/questions/44599550/eloquent-count-nested-relationships-with-nested-eager-loading
            ->with([
                'user:id,name',
                'activePhotos:id,advert_id,file',
                'userDialogs' => function ($query): void {
                    $query->withCount(['messagesReceived', 'messagesSent']);
                },
            ])
            ->withCount(['userDialogs'])
            // https://laraveldaily.com/tip/withaggregate-method
            // https://stackoverflow.com/questions/38261546/order-by-relationship-column/72277299#72277299
            ->withMax('userDialogs', 'updated_at')
            ->orderByDesc('user_dialogs_max_updated_at')
            ->paginate(20);

        // 2-nd approach - Fetch correct data as array so Models utilities are not avilable
        // todo: for using this approach try to find solution
        // $userId = auth()->user()?->id;
        // $adverts = collect(
        //     DB::select(
        //         "SELECT q1.id, q1.title, COUNT(q1.id) AS count_of_dialogs, COUNT(q1.dialog_id) AS count_of_messages, MAX(q1.updated_at) AS dialog_updated_at
        //         FROM (
        //         SELECT advert_adverts.id, advert_adverts.title, advert_dialogs.owner_id, advert_dialogs.client_id, advert_dialogs.updated_at, advert_dialog_messages.dialog_id
        //         FROM (advert_adverts
        //         INNER JOIN advert_dialogs ON advert_adverts.id = advert_dialogs.advert_id)
        //         LEFT JOIN advert_dialog_messages ON advert_dialogs.id = advert_dialog_messages.dialog_id
        //         WHERE (((advert_dialogs.owner_id)=$userId)) OR (((advert_dialogs.client_id)=$userId))) AS q1
        //         GROUP BY q1.id, q1.title
        //         ORDER BY MAX(q1.updated_at) DESC;"
        //     )
        // );

        // dump($adverts);
        // return 2;
        return view('cabinet.adverts.with_dialogs', compact('adverts'));
    }

    // HELPERS sub-methods ====================================

    private function checkAccess(Advert $advert): void
    {
        // can not be injected in constructor because this middleware accepts two parameters
        // so the middleware can be called only from method where $advert argument is accessable
        if (! Gate::allows('manage-own-advert', $advert)) {
            // if just abort - not possible to handle?
            // abort(403);
            // abort(Response::HTTP_FORBIDDEN);

            // for handling HTTP_FORBIDDEN response
            throw new AccessDeniedHttpException();
        }
    }
}
