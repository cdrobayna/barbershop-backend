<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Appointment;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RescheduleRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::RescheduleRequested, NotificationChannel::Email)) {
            $channels[] = NotificationChannel::Email->value;
        }

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::RescheduleRequested, NotificationChannel::InApp)) {
            $channels[] = NotificationChannel::InApp->value;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Solicitud de Reprogramación')
            ->line('El proveedor ha solicitado que reprogrames tu cita.')
            ->line('Cita actual: '.$this->appointment->scheduled_at->format('d/m/Y H:i'))
            ->line('Por favor, reprograma tu cita a la brevedad.')
            ->action('Reprogramar', url('/appointments/'.$this->appointment->id.'/reschedule'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventType::RescheduleRequested->value,
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => 'Se ha solicitado reprogramar tu cita del '.$this->appointment->scheduled_at->format('d/m/Y H:i'),
        ];
    }
}
