<?php

namespace App\Http\Middleware;

use App\Services\RoutePermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the permission derived from the route name.
 * Runs after ResolveWorkspace. A route whose permission cannot be resolved is denied (fail closed).
 */
class CheckPermission
{
    public function __construct(
        private RoutePermissionResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->attributes->has('workspace')) {
            return response()->json(['success' => false, 'message' => __('rbac.workspace_not_resolved')], 400);
        }

        $permission = $this->resolver->forRoute($request->route());

        if (! $permission || ! $request->user()->can($permission)) {
            return response()->json(['success' => false, 'message' => __('rbac.forbidden')], 403);
        }

        return $next($request);
    }
}
