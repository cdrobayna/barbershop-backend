<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Appointment;
use App\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $oldAppointmentId,
        public readonly Carbon $oldScheduledAt,
        public readonly Appointment $newAppointment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentRescheduled, NotificationChannel::Email)) {
            $channels[] = NotificationChannel::Email->value;
        }

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::AppointmentRescheduled, NotificationChannel::InApp)) {
            $channels[] = NotificationChannel::InApp->value;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cita Reprogramada')
            ->line('Tu cita ha sido reprogramada.')
            ->line('Fecha anterior: '.$this->oldScheduledAt->format('d/m/Y H:i'))
            ->line('Nueva fecha: '.$this->newAppointment->scheduled_at->format('d/m/Y H:i'))
            ->action('Ver Cita', url('/appointments/'.$this->newAppointment->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventType::AppointmentRescheduled->value,
            'old_appointment_id' => $this->oldAppointmentId,
            'new_appointment_id' => $this->newAppointment->id,
            'old_scheduled_at' => $this->oldScheduledAt->toIso8601String(),
            'new_scheduled_at' => $this->newAppointment->scheduled_at->toIso8601String(),
            'message' => 'Tu cita ha sido reprogramada de '.$this->oldScheduledAt->format('d/m/Y H:i').' a '.$this->newAppointment->scheduled_at->format('d/m/Y H:i'),
        ];
    }
}
