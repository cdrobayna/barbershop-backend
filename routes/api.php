<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ScheduleController;
use Illuminate\Support\Facades\Route;

// ── Public routes ─────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->name('api.v1.auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });
});

// ── Protected routes ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::prefix('auth')->name('api.v1.auth.')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });

    // Availability — accessible to both clients and providers
    Route::get('/availability', [AvailabilityController::class, 'show'])->name('api.v1.availability.show');

    // Notifications — accessible to both clients and providers
    Route::prefix('notifications')->name('api.v1.notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('markAsRead');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('markAllAsRead');
    });

    // Appointments
    Route::prefix('appointments')->name('api.v1.appointments.')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('index');
        Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('show');
        Route::patch('/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('cancel');
        Route::patch('/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('reschedule');

        Route::middleware('role:client')->group(function () {
            Route::post('/', [AppointmentController::class, 'store'])->name('store');
        });

        Route::middleware('role:provider')->group(function () {
            Route::patch('/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('confirm');
            Route::patch('/{appointment}/complete', [AppointmentController::class, 'complete'])->name('complete');
            Route::patch('/{appointment}/request-reschedule', [AppointmentController::class, 'requestReschedule'])->name('requestReschedule');
        });
    });

    // Client profile and preferences
    Route::middleware('role:client')->prefix('profile')->name('api.v1.profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'showClientProfile'])->name('show');
        Route::put('/', [ProfileController::class, 'updateClientProfile'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('updatePassword');
        Route::get('/notification-preferences', [ProfileController::class, 'getNotificationPreferences'])->name('notificationPreferences.show');
        Route::put('/notification-preferences', [ProfileController::class, 'updateNotificationPreferences'])->name('notificationPreferences.update');
    });

    // ── Provider-only routes ─────────────────────────────────────────────────
    Route::middleware('role:provider')->group(function () {
        Route::prefix('provider/profile')->name('api.v1.provider.profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'showProviderProfile'])->name('show');
            Route::put('/', [ProfileController::class, 'updateProviderProfile'])->name('update');
        });

        // Weekly schedule
        Route::prefix('schedule')->name('api.v1.schedule.')->group(function () {
            Route::get('/', [ScheduleController::class, 'index'])->name('index');
            Route::put('/{dayOfWeek}', [ScheduleController::class, 'updateDay'])
                ->name('updateDay')
                ->where('dayOfWeek', '[0-6]');

            // Overrides — static segment must come before {dayOfWeek} wildcard
            Route::prefix('overrides')->name('overrides.')->group(function () {
                Route::get('/', [ScheduleController::class, 'indexOverrides'])->name('index');
                Route::post('/', [ScheduleController::class, 'storeOverride'])->name('store');
                Route::put('/{override}', [ScheduleController::class, 'updateOverride'])->name('update');
                Route::delete('/{override}', [ScheduleController::class, 'destroyOverride'])->name('destroy');
            });
        });
    });
});
