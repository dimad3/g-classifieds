<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\Dialog\Dialog;
use App\Models\Banner\Banner;
use App\Models\Ticket\Ticket;
use App\Models\User\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPermissions();
    }

    private function registerPermissions(): void
    {
        // ADNIN + MODERATOR

        Gate::define('admin-panel', function (User $user) {
            return $user->isAdmin() || $user->isModerator();
        });

        Gate::define('manage-adverts', function (User $user) {
            return $user->isAdmin() || $user->isModerator();
        });

        Gate::define('manage-banners', function (User $user) {
            return $user->isAdmin() || $user->isModerator();
        });

        Gate::define('manage-tickets', function (User $user) {
            return $user->isAdmin() || $user->isModerator();
        });

        // ADNIN ONLY

        Gate::define('manage-adverts-categories', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-regions', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-actions', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-users', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-pages', function (User $user) {
            return $user->isAdmin();
        });

        // ADNIN + MODERATOR + USER

        // when advert is not active only admin, moderator and author (user) can see it
        Gate::define('show-advert', function (User $user, Advert $advert) {
            return $user->isAdmin() || $user->isModerator() || $advert->user_id === $user->id;
        });

        // USER ONLY

        Gate::define('manage-own-advert', function (User $user, Advert $advert) {
            return $advert->user_id === $user->id;
        });

        Gate::define('manage-own-banner', function (User $user, Banner $banner) {
            return $banner->user_id === $user->id;
        });

        Gate::define('manage-own-ticket', function (User $user, Ticket $ticket) {
            return $ticket->user_id === $user->id;
        });

        Gate::define('manage-own-dialog', function (User $user, Dialog $dialog) {
            return $dialog->owner_id === $user->id || $dialog->client_id === $user->id;
        });

        Gate::define('access-to-tickets', function (User $user) {
            return $user->isUser();
        });
    }
}
