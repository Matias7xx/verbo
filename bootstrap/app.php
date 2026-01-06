<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Intercepta erros HTTP para renderizar via Inertia
        $exceptions->render(function (HttpException $e, $request) {
            if ($request->inertia() || $request->wantsJson()) {
                return null; // Deixa o Laravel lidar normalmente se for JSON/Inertia request
            }
            
            // Se for acesso direto via navegador (GET) e der 403
            if ($e->getStatusCode() === 403) {
                return Inertia::render('Errors/403', [
                    'message' => $e->getMessage() ?: 'Acesso Proibido'
                ])->toResponse($request)->setStatusCode(403);
            }
            
            return null;
        });
    })->create();
