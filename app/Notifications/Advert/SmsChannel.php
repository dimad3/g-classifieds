<?php

declare(strict_types=1);

namespace App\Notifications\Advert;

use App\Models\User\User;
use App\Services\Sms\SmsSender;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    private $sender;

    public function __construct(SmsSender $sender)
    {
        $this->sender = $sender;
    }

    /**
     * Send SMS
     */
    public function send(User $notifiable, Notification $notification): void
    {
        if (! $notifiable->isPhoneVerified()) {
            return;
        }
        $message = $notification->toSms($notifiable);
        $this->sender->send($notifiable->phone, $message);
    }
}
