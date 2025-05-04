<?php

declare(strict_types=1);

namespace App\Services\Adverts;

use App\Models\Adverts\Advert\Advert;
use App\Models\User\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FavoriteService
{
    public function addToFavorites($userId, $advertId): void
    {
        $user = $this->getUser($userId);
        $advert = $this->getActiveAdvert($advertId);

        $user->addToFavorites($advert->id);
    }

    public function remove($userId, $advertId): void
    {
        $user = $this->getUser($userId);
        // $advert = $this->getAdvert($advertId);
        $favorite = Advert::favoredByUser($user)->where('id', $advertId)->first();

        if ($favorite) {
            $user->removeFromFavorites($favorite->id);
        } else {
            throw new NotFoundHttpException('Resource not found.');
        }
    }

    // HELPER sub-methods ==================================

    private function getUser($userId): User
    {
        return User::findOrFail($userId);
    }

    private function getAdvert($advertId): Advert
    {
        return Advert::findOrFail($advertId);
    }

    private function getActiveAdvert($advertId): Advert
    {
        $advert = Advert::active()->notExpired()->find($advertId);
        if ($advert) {
            return $advert;
        }
        throw new \DomainException('Advert is not active or not found.');
    }
}
