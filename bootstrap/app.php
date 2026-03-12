<?php

use App\Exceptions\AppointmentActionNotAllowedException;
use App\Exceptions\AppointmentNotAvailableException;
use App\Exceptions\ScheduleConflictException;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $renderDomain = static function (Request $request, \Exception $e): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 422);
        };

        $exceptions->render(function (AppointmentNotAvailableException $e, Request $request) use ($renderDomain): JsonResponse {
            return $renderDomain($request, $e);
        });

        $exceptions->render(function (AppointmentActionNotAllowedException $e, Request $request) use ($renderDomain): JsonResponse {
            return $renderDomain($request, $e);
        });

        $exceptions->render(function (ScheduleConflictException $e, Request $request) use ($renderDomain): JsonResponse {
            return $renderDomain($request, $e);
        });
    })->create();
