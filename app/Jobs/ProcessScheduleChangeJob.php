<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ScheduleChangedNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduleChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $providerId,
        public readonly Carbon $changeDate,
        public readonly array $affectedClientIds,
    ) {}

    public function handle(): void
    {
        if (empty($this->affectedClientIds)) {
            Log::info("No affected clients for schedule change on {$this->changeDate->toDateString()}");

            return;
        }

        // Group affected appointments by client
        $clientCounts = array_count_values($this->affectedClientIds);

        foreach ($clientCounts as $clientId => $count) {
            $client = User::find($clientId);

            if ($client) {
                Log::info("Notifying client {$clientId} of {$count} affected appointment(s)");
                $client->notify(new ScheduleChangedNotification($this->changeDate, $count));
            }
        }
    }
}
