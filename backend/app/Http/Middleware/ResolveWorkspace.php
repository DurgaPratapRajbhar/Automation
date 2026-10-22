<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $workspaceId = $request->header('X-Workspace-Id');

        if (!$workspaceId) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace is required.',
            ], 400);
        }

        $workspace = $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->first();

        if (!$workspace) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this workspace.',
            ], 403);
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}