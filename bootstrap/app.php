<?php

use App\Exceptions\AppointmentActionNotAllowedException;
use App\Exceptions\AppointmentNotAvailableException;
use App\Exceptions\ScheduleConflictException;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $domainExceptions = [
            AppointmentNotAvailableException::class,
            AppointmentActionNotAllowedException::class,
            ScheduleConflictException::class,
        ];

        foreach ($domainExceptions as $exceptionClass) {
            $exceptions->render(function ($e, $request) use ($exceptionClass): ?JsonResponse {
                if ($e instanceof $exceptionClass && $request->expectsJson()) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }

                return null;
            });
        }
    })->create();
