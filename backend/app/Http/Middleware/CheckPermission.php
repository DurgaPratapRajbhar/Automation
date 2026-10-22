<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {

        $workspace = $request->attributes->get('workspace');

        if (!$workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not resolved.',
            ], 400);
        }

        $roleId = $workspace->pivot->role_id;

        $hasPermission = \App\Models\Role::where('id', $roleId)
            ->where('workspace_id', $workspace->id)
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}