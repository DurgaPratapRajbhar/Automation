<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceModule;
use App\Http\Controllers\Controller;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceAccessController extends Controller
{
    public function __construct(
        private WorkspacePermissionService $permissionService
    ) {}

    // What the logged-in user can do here; the frontend shows pages/buttons from this list
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace');
        $role = $request->attributes->get('workspace_role');

        return response()->json([
            'success' => true,
            'data' => [
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'inbox_language' => $workspace->inbox_language,
                    'type' => $workspace->type,
                    'trial_ends_at' => $workspace->trial_ends_at,
                    'trial_days_left' => $workspace->trialDaysLeft(),
                ],
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_owner' => $role->isOwner(),
                ] : null,
                'is_super_admin' => $user->isSuperAdmin(),
                // false for an account made with Google that has not set a password yet
                'has_password' => $user->has_password,
                // Modules switched on for this shop, and every module there is (the app shows the others as "ask ScriptDP")
                'modules' => $user->isSuperAdmin() ? WorkspaceModule::values() : $workspace->enabledModules(),
                'available_modules' => WorkspaceModule::values(),
                // code => name of the languages the language bridge knows (Inbox language choice, labels)
                'languages' => config('translation.languages'),
                'permissions' => $this->permissionService->permissionsInEnabledModules(
                    $this->permissionService->permissionsOf($user, $role),
                    $user->isSuperAdmin() ? null : $workspace
                ),
            ],
        ]);
    }
}
