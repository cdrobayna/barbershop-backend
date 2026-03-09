<?php

namespace App\Providers;

use App\Models\ScheduleOverride;
use App\Models\WeeklySchedule;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\ScheduleService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AvailabilityService::class);

        $this->app->singleton(AppointmentService::class, function ($app) {
            return new AppointmentService($app->make(AvailabilityService::class));
        });

        $this->app->singleton(ScheduleService::class, function ($app) {
            return new ScheduleService($app->make(AppointmentService::class));
        });
    }

    public function boot(): void
    {
        // Polymorphic morph map for WorkSession schedule relationship
        Relation::enforceMorphMap([
            'weekly' => WeeklySchedule::class,
            'override' => ScheduleOverride::class,
        ]);
    }
}
