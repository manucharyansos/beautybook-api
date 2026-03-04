<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewBookingNotification extends Notification
{
    public function __construct(public $booking) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Նոր ամրագրում')
            ->line('Դուք ունեք նոր ամրագրում.')
            ->line('Հաճախորդ: ' . $this->booking->client_name)
            ->line('Ամսաթիվ: ' . $this->booking->starts_at)
            ->action('Դիտել', url('/app/calendar'));
    }
}
