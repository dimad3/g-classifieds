<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banners\EditRequest;
use App\Http\Requests\Banners\RejectRequest;
use App\Models\Banner\Banner;
use App\Services\Banners\BannerService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    private $service;

    public function __construct(BannerService $service)
    {
        $this->service = $service;
        // $this->middleware('can:manage-banners'); // is called twice see "barryvdh/laravel-debugbar": gates. Is applyed also in: resources\views\admin\_nav.blade.php
    }

    public function index(Request $request)
    {
        $query = Banner::orderByDesc('updated_at');

        if (! empty($value = $request->get('id'))) {
            $query->where('id', $value);
        }

        if (! empty($value = $request->get('user'))) {
            $query->where('user_id', $value);
        }

        if (! empty($value = $request->get('region'))) {
            $query->where('region_id', $value);
        }

        if (! empty($value = $request->get('category'))) {
            $query->where('category_id', $value);
        }

        if (! empty($value = $request->get('status'))) {
            $query->where('status', $value);
        }

        $banners = $query->paginate(20);

        $statuses = Banner::statusesList();

        return view('admin.banners.index', compact('banners', 'statuses'));
    }

    public function show(Banner $banner)
    {
        return view('admin.banners.show', compact('banner'));
    }

    public function editForm(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));   // todo: add view 'admin.banners.edit'
    }

    public function edit(EditRequest $request, Banner $banner)
    {
        try {
            $this->service->editByAdmin($banner->id, $request);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.banners.show', $banner);
    }

    public function moderate(Banner $banner)
    {
        try {
            $this->service->moderate($banner->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.banners.show', $banner);
    }

    public function rejectForm(Banner $banner)
    {
        return view('admin.banners.reject', compact('banner'));
    }

    public function reject(RejectRequest $request, Banner $banner)
    {
        try {
            $this->service->reject($banner->id, $request);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.banners.show', $banner);
    }

    public function pay(Banner $banner)
    {
        try {
            $this->service->pay($banner->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.banners.show', $banner);
    }

    public function destroy(Banner $banner)
    {
        try {
            $this->service->removeByAdmin($banner->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.banners.index');
    }
}
