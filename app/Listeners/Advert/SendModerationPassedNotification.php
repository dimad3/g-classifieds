<?php

declare(strict_types=1);

namespace App\Listeners\Advert;

use App\Events\Advert\ModerationPassed;
use App\Notifications\Advert\ModerationPassedNotification;

/**
 * Listener for handling the ModerationPassed event.
 *
 * This listener is responsible for sending a notification to the user
 * when their advert successfully passes moderation.
 */
class SendModerationPassedNotification
{
    /**
     * Handle the ModerationPassed event.
     *
     * This method is triggered when an advert successfully passes moderation.
     * It sends a notification to the user associated with the advert.
     *
     * @param  ModerationPassed  $event  The event instance containing the advert details.
     */
    public function handle(ModerationPassed $event): void
    {
        // Retrieve the advert instance from the event.
        $advert = $event->advert;

        // Notify the user associated with the advert about the moderation success.
        $advert->user->notify(new ModerationPassedNotification($advert));
    }
}
