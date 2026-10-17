<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Pages d'erreur aux couleurs du site (en local, les erreurs 500 gardent
        // la page détaillée de Laravel, plus utile pour corriger un bug)
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $statut = $response->getStatusCode();
            $statuts = app()->isLocal() ? [403, 404, 409] : [403, 404, 409, 500, 503];

            if (! in_array($statut, $statuts, true) || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('Erreur', [
                'statut' => $statut,
                'message' => $statut === 409 ? $e->getMessage() : null,
            ])->toResponse($request)->setStatusCode($statut);
        });
    })->create();
