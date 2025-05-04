<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Adverts\Advert\Advert;
use App\Services\Adverts\FavoriteService;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    private $favoriteService;

    public function __construct(FavoriteService $favoriteService)
    {
        $this->favoriteService = $favoriteService;
        $this->middleware('auth');
    }

    public function index()
    {
        $adverts = Advert::favoredByUser(Auth::user())
            ->orderByDesc('id')
            ->with(['category:id,name', 'region:id,name', 'user:id,name'])
            ->paginate(20);

        return view('cabinet.favorites.index', compact('adverts'));
    }

    public function addToFavorites(Advert $advert)
    {
        try {
            $this->favoriteService->addToFavorites(Auth::id(), $advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('adverts.show', $advert)->with('success', 'Advert was added to your favorites.');
    }

    public function remove(Advert $advert)
    {
        try {
            $this->favoriteService->remove(Auth::id(), $advert->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Advert was removed from your favorites.');
    }
}
