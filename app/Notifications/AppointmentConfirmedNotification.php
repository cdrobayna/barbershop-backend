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

class AppointmentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentConfirmed, NotificationChannel::Email)) {
            $channels[] = NotificationChannel::Email->value;
        }

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentConfirmed, NotificationChannel::InApp)) {
            $channels[] = NotificationChannel::InApp->value;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cita Confirmada')
            ->line('Tu cita ha sido confirmada.')
            ->line('Fecha: '.$this->appointment->scheduled_at->format('d/m/Y H:i'))
            ->line('Duración: '.$this->appointment->duration_minutes.' minutos')
            ->action('Ver Cita', url('/appointments/'.$this->appointment->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventType::AppointmentConfirmed->value,
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => 'Tu cita para '.$this->appointment->scheduled_at->format('d/m/Y H:i').' ha sido confirmada',
        ];
    }
}
