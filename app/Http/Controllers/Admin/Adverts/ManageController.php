<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Adverts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdvertsIndexRequest;
use App\Http\Requests\Adverts\RejectRequest;
use App\Http\Requests\Adverts\StoreRequest;
use App\Models\Adverts\Advert\Advert;
use App\Models\User\User;
use App\Services\Adverts\AdvertService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | This controller is used to provde functionality for Admin
    | to manage ALL adverts
    |--------------------------------------------------------------------------
    |
    */

    private $advertService;

    public function __construct(AdvertService $advertService)
    {
        $this->advertService = $advertService;
        // $this->middleware('can:manage-adverts'); // is called twice see "barryvdh/laravel-debugbar": gates. Is applyed also in: resources\views\admin\_nav.blade.php
    }

    /**
     * Display a listing of adverts.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(AdvertsIndexRequest $request)
    {
        // Extract validated and sanitized query parameters
        $validated = $request->validated();

        // Check if there are any query parameters
        if ($request->query()) {
            $type = $validated['type'] ?? null;
            $period = $validated['period'] ?? null;

            // Start query building
            $query = Advert::query();

            // Apply type filter
            if ($type === 'published') {
                $query->published();
            } elseif ($type === 'unpublished') {
                $query->unpublished();
            }

            // Apply period filter
            $query->forPeriod('published_at', $period); // todo: If published_at is null, then these adverts are not selected at all.

            // Apply other filters
            if (! empty($value = $validated['id'] ?? null)) {
                $query->where('id', $value);
            }

            if (! empty($value = $validated['published'] ?? null)) {
                try {
                    $date = Carbon::createFromFormat('d.m.Y', $value); // Convert date format from dd.mm.yyyy to Y-m-d
                    // copy() method creates a clone of the original Carbon instance.
                    // This prevents mutation of the $date instance
                    $query->whereBetween('published_at', [
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

            if (! empty($value = $validated['user'] ?? null)) {
                $query->where('user_id', $value);
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

            // $advertsCount = $query->count(); // Return advert count

            $adverts = $query->select(['id', 'title', 'user_id', 'region_id', 'category_id', 'status', 'reject_reason', 'published_at'])
                ->with(['category:id,name', 'region:id,name', 'user:id,name'])
                // ->orderByDesc('published_at')
                ->orderByDesc('id')
                // ->cursorPaginate(100)
                ->paginate(100)
                ->withQueryString(); // Retain query string parameters
        } else {
            $type = '';
            $period = '';
            $adverts = new LengthAwarePaginator([], 0, 100);
        }

        return view('admin.adverts.manage.index', [
            'adverts' => $adverts,
            'statuses' => Advert::statusesList(),
            'roles' => User::rolesList(),
            'type' => $type,
            'period' => $period,
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
        $advertAttributes = $this->advertService->getAdvertAttributes($advert);

        return view('cabinet.adverts.create_or_edit', compact('advert', 'advertAttributes'));
    }

    /**
     * Update the advert in storage.
     */
    public function update(StoreRequest $request, Advert $advert)
    {
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
        try {
            $this->advertService->destroy($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.adverts.manage.index');
    }

    /**
     * Update advert's status to 'active'
     */
    public function activate(Advert $advert)
    {
        try {
            $this->advertService->activate($advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.adverts.manage.index');
    }

    /**
     * Show the form for creating rejectaton reason for advert activating
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function rejectForm(Advert $advert)
    {
        return view('admin.adverts.manage.reject', compact('advert'));
    }

    /**
     * Update advert's status to 'draft'
     */
    public function reject(RejectRequest $request, Advert $advert)
    {
        try {
            $this->advertService->reject($advert->id, $request);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        // return redirect()->route('admin.adverts.manage.index');
        return redirect()->route('adverts.show', $advert);
    }
}
