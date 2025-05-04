<?php

declare(strict_types=1);

namespace App\Console\Commands\Advert;

use App\Models\Adverts\Advert\Advert;
use App\Services\Adverts\AdvertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireCommand extends Command
{
    protected $signature = 'advert:expire';

    private $advertService;

    public function __construct(AdvertService $advertService)
    {
        parent::__construct();
        $this->advertService = $advertService;
    }

    public function handle(): bool
    {
        $success = true;

        foreach (Advert::active()->where('expires_at', '<', Carbon::now())->cursor() as $advert) {
            try {
                $this->advertService->expire($advert->id);
            } catch (\DomainException $e) {
                $this->error($e->getMessage());
                $success = false;
            }
        }

        return $success;
    }
}
