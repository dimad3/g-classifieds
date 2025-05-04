<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Adverts\Advert\Dialog\Dialog;
use App\Models\Ticket\Message;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer('cabinet._nav', function ($view): void {
            // evaluate dialogsNewMessages (budge - count of dialogs' messages unread by user)
            $dialogs = Dialog::unreadByUser()->get();
            $dialogs = $dialogs->map(function ($dialog, $key) {
                // add new attribute for Dialog models
                $dialog['newMessages'] = $dialog->loggedInUserIsOwner() ? $dialog->owner_new_messages : $dialog->client_new_messages;

                return $dialog;
            });
            $dialogsNewMessages = $dialogs->sum('newMessages');

            // evaluate ticketsNewMessages (budge - count of tickets' messages unread by user)
            $ticketsNewMessages = auth()->user()->messagesReceived->sum('is_new_message');

            $view->with('dialogsNewMessages', $dialogsNewMessages)
                ->with('ticketsNewMessages', $ticketsNewMessages);
        });

        view()->composer(['admin._nav'], function ($view): void {
            // evaluate ticketsNewMessages (budge - count of tickets' messages unread by admin)
            $view->with(
                'ticketsNewMessages',
                Message::unreadByAdmins()->count()
            );
        });
    }
}
