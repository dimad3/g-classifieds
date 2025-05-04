<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Adverts\Elasticsearch\AdvertDocsIndexerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteAdvertFromElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $advertId; // Store just the ID

    public function __construct(int $advertId)
    {
        $this->advertId = $advertId;
    }

    public function handle(AdvertDocsIndexerService $indexer): void
    {
        $indexer->deleteAdvert($this->advertId); // Use the ID directly
    }
}
