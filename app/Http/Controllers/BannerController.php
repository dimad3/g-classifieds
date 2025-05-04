<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Banner\Banner;
use App\Services\Banners\BannerService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    private $bannerService;

    public function __construct(BannerService $bannerService)
    {
        $this->bannerService = $bannerService;
    }

    // todo: try without ES
    public function get(Request $request)
    {
        $format = $request['format'];
        $category = $request['category'];
        $region = $request['region'];

        if (! $banner = $this->bannerService->getRandomForView($category, $region, $format)) {
            return '';
        }

        return view('banner.get', compact('banner'));
    }

    // todo: try without ES
    public function click(Banner $banner)
    {
        $this->bannerService->click($banner);

        return redirect($banner->url);
    }
}
