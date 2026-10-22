<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\RoleRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

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

        if (!$workspaceId || !ctype_digit((string) $workspaceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace is required.',
            ], 400);
        }

        $workspace = $user->workspaces()
            ->where('workspaces.id', (int) $workspaceId)
            ->first();

        if (!$workspace) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this workspace.',
            ], 403);
        }

        // Role bhi isi workspace ka hona chahiye
        $role = $this->roleRepository->findInWorkspace(
            $workspace->id,
            (int) $workspace->pivot->role_id
        );

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Your role in this workspace is invalid.',
            ], 403);
        }

        $request->attributes->set('workspace', $workspace);
        $request->attributes->set('workspace_role', $role);

        return $next($request);
    }
}
