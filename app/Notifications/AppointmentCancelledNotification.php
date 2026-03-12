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

class AppointmentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $cancelledBy,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentCancelled, NotificationChannel::Email)) {
            $channels[] = NotificationChannel::Email->value;
        }

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentCancelled, NotificationChannel::InApp)) {
            $channels[] = NotificationChannel::InApp->value;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cita Cancelada')
            ->line('Tu cita ha sido cancelada por '.$this->cancelledBy.'.')
            ->line('Fecha original: '.$this->appointment->scheduled_at->format('d/m/Y H:i'))
            ->action('Ver Detalles', url('/appointments/'.$this->appointment->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventType::AppointmentCancelled->value,
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'cancelled_by' => $this->cancelledBy,
            'message' => 'Tu cita para '.$this->appointment->scheduled_at->format('d/m/Y H:i').' ha sido cancelada',
        ];
    }
}
