<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => ApiResponse::error($e->getMessage(), $e->status, $e->errors),
                $e instanceof ValidationException => ApiResponse::error('Validation failed.', 422, $e->errors()),
                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
                $e instanceof AuthorizationException => ApiResponse::error('You are not authorized to perform this action.', 403),
                $e instanceof ModelNotFoundException => ApiResponse::error('Resource not found.', 404),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    match ($e->getStatusCode()) {
                        403 => 'You are not authorized to perform this action.',
                        404 => 'Resource not found.',
                        405 => 'Method not allowed.',
                        429 => 'Too many requests. Please try again later.',
                        default => $e->getMessage() ?: 'Request failed.',
                    },
                    $e->getStatusCode(),
                    [],
                    $e->getHeaders(),
                ),
                default => ApiResponse::error(
                    config('app.debug') ? $e->getMessage() : 'Something went wrong.',
                    500,
                ),
            };
        });
    })->create();
