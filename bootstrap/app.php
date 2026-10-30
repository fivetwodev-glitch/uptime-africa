<?php

use App\Exceptions\UptimeException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetTenantContext::class]);
        $middleware->alias(['tenant' => SetTenantContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Gestion centralisée des erreurs.
        $exceptions->dontReportDuplicates();

        // Erreurs métier : journalisées en « warning » avec leur contexte.
        $exceptions->report(function (UptimeException $e) {
            logger()->warning($e->getMessage(), $e->context + ['exception' => $e::class]);

            return false;
        });

        $exceptions->render(function (UptimeException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->userMessage,
                    'request_id' => Context::get('request_id'),
                ], $e->status);
            }

            return response()->view('errors.generic', [
                'status' => $e->status,
                'message' => $e->userMessage,
            ], $e->status);
        });

        // Réponses JSON homogènes, sans détail technique hors debug.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'message' => $status >= 500 && ! config('app.debug')
                    ? 'Une erreur interne est survenue.'
                    : ($e->getMessage() ?: 'Erreur.'),
                'request_id' => Context::get('request_id'),
            ], $status);
        });
    })->create();
