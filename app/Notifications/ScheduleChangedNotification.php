<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Carbon $changeDate,
        public readonly int $affectedAppointmentsCount,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::ScheduleChanged, NotificationChannel::Email)) {
            $channels[] = NotificationChannel::Email->value;
        }

        if (NotificationPreference::isEnabled($notifiable, NotificationEventType::ScheduleChanged, NotificationChannel::InApp)) {
            $channels[] = NotificationChannel::InApp->value;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cambio de Horario')
            ->line('El proveedor ha modificado su horario de trabajo.')
            ->line('Fecha afectada: '.$this->changeDate->format('d/m/Y'))
            ->line('Tienes '.$this->affectedAppointmentsCount.' cita(s) afectada(s) que requieren ser reprogramadas.')
            ->action('Ver Citas', url('/appointments'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventType::ScheduleChanged->value,
            'change_date' => $this->changeDate->toDateString(),
            'affected_count' => $this->affectedAppointmentsCount,
            'message' => 'Cambio de horario afecta '.$this->affectedAppointmentsCount.' cita(s). Por favor reprograma.',
        ];
    }
}
