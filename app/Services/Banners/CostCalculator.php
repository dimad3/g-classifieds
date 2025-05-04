<?php

declare(strict_types=1);

namespace App\Services\Banners;

class CostCalculator
{
    private $price;

    public function __construct(float $price)
    {
        $this->price = $price;
    }

    public function calc(int $views): float
    {
        return round($this->price * ($views / 1000), 2);
    }
}
