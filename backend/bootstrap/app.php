<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'jwt.auth' => JwtAuthMiddleware::class,
            'workspace' => ResolveWorkspace::class,
            'permission' => CheckPermission::class,
            'super.admin' => EnsureSuperAdmin::class,
            'api.key' => AuthenticateApiKey::class,
        ]);

        // Requests come through our own frontend container (private Docker network), which passes on
        // the visitor's IP. Trust that header only from private addresses, never from the internet,
        // so rate limits count each visitor and nobody can fake an IP from outside.
        $middleware->trustProxies(
            at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR,
        );

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // API par hamesha JSON (HTML error page nahi)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        // Expected business errors are answered to the client, not written to the error log
        $exceptions->dontReport(BusinessRuleException::class);

        $exceptions->render(function (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        });

        // Model / route na mile to internal class name bahar na jaye
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

    })->create();
