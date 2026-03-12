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

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentReminder, NotificationChannel::Email)) {
            $channels[] = NotificationChannel::Email->value;
        }

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentReminder, NotificationChannel::InApp)) {
            $channels[] = NotificationChannel::InApp->value;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Recordatorio de Cita')
            ->line('Este es un recordatorio de tu próxima cita.')
            ->line('Fecha: '.$this->appointment->scheduled_at->format('d/m/Y H:i'))
            ->line('Duración: '.$this->appointment->duration_minutes.' minutos')
            ->line('Personas: '.$this->appointment->party_size)
            ->action('Ver Cita', url('/appointments/'.$this->appointment->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventType::AppointmentReminder->value,
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => 'Recordatorio: Tienes una cita el '.$this->appointment->scheduled_at->format('d/m/Y H:i'),
        ];
    }
}
