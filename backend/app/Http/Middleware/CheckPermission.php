<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware(Permission::CustomersView->middleware())
 * Runs after ResolveWorkspace, so Spatie already knows the workspace.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->attributes->has('workspace')) {
            return response()->json(['success' => false, 'message' => __('rbac.workspace_not_resolved')], 400);
        }

        if (!$request->user()->can($permission)) {
            return response()->json(['success' => false, 'message' => __('rbac.forbidden')], 403);
        }

        return $next($request);
    }
}
