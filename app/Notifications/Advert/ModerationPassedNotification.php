<?php

declare(strict_types=1);

namespace App\Notifications\Advert;

use App\Models\Adverts\Advert\Advert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Notification sent to the user when their advert has successfully passed moderation.
 *
 * This notification is queued and can be delivered through multiple channels, such as email or SMS.
 */
class ModerationPassedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @var Advert The advert instance that has passed moderation.
     */
    private $advert;

    /**
     * Create a new notification instance.
     *
     * @param  Advert  $advert  The advert that has passed moderation.
     */
    public function __construct(Advert $advert)
    {
        $this->advert = $advert;
    }

    /**
     * Get the delivery channels for the notification.
     *
     * @param  mixed  $notifiable  The entity being notified.
     * @return array The channels through which the notification will be delivered.
     */
    public function via($notifiable)
    {
        return [
            'mail', // Send via email
            // SmsChannel::class // todo: Uncomment after SMS channel implementation
        ];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable  The entity being notified.
     * @return MailMessage The mail message to be sent.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Moderation Passed') // Set the email subject
            ->greeting('Hello!') // Set the greeting text
            ->line('Your advert has successfully passed moderation.') // Main email message line
            ->action('View Advert', route('adverts.show', $this->advert)) // Link to the advert
            ->line('Thank you for using our application!'); // Closing line
    }

    /**
     * Build the SMS representation of the notification.
     *
     * @return string The SMS message content.
     */
    public function toSms(): string
    {
        // SMS message content, notifying the user of moderation success
        return 'Your advert has successfully passed moderation.';
    }
}
