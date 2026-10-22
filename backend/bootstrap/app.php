<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Middleware\CheckPermission;
use App\Exceptions\BusinessRuleException;
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
        ]);

        
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // API par hamesha JSON (HTML error page nahi)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

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
