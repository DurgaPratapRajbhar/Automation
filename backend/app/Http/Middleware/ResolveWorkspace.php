<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceStatus;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads X-Workspace-Id, checks access and tells Spatie which workspace (team) to use.
 * Sets request attributes: workspace, workspace_role (null for a super admin who is not a member).
 */
class ResolveWorkspace
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private MemberRepositoryInterface $memberRepository,
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspaceId = $request->header('X-Workspace-Id');

        if (!$workspaceId || !ctype_digit((string) $workspaceId)) {
            return $this->deny('rbac.workspace_required', 400);
        }

        $workspace = $this->workspaceRepository->findById((int) $workspaceId);
        $isMember = $workspace && $this->memberRepository->isMember($workspace->id, $user->id);

        if (!$user->isSuperAdmin()) {
            if (!$isMember) {
                return $this->deny('rbac.workspace_no_access', 403);
            }

            if ($workspace->status !== WorkspaceStatus::Active->value) {
                return $this->deny('rbac.workspace_suspended', 403);
            }
        } elseif (!$workspace) {
            return $this->deny('rbac.workspace_not_found', 404);
        }

        $role = $this->roleRepository->findForUser($workspace->id, $user->id);

        if ($isMember && !$role) {
            return $this->deny('rbac.workspace_invalid_role', 403);
        }

        setPermissionsTeamId($workspace->id);
        $user->unsetRelation('roles');

        $request->attributes->set('workspace', $workspace);
        $request->attributes->set('workspace_role', $role);

        return $next($request);
    }

    private function deny(string $messageKey, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => __($messageKey)], $status);
    }
}
