<?php

return [
    'cannot_grant' => 'You cannot grant permissions you do not have: :permissions',
    'owner_only' => 'Only an owner can manage the owner role.',
    'role_too_powerful' => 'You cannot manage a role that has more permissions than yours.',
    'role_in_use' => 'This role is assigned to :count member(s). Change their role first.',
    'system_role_locked' => 'System roles cannot be modified.',
    'cannot_edit_own_role' => 'You cannot modify your own role.',
    'invalid_role_name' => 'Role name must contain letters or numbers.',
    'role_exists' => 'A role with this name already exists.',
    'role_not_found' => 'Role not found.',
    'role_not_in_workspace' => 'Selected role does not exist in this workspace.',

    'name_and_password_required' => 'Name and password are required to create a new user.',
    'already_member' => 'This user is already a member of this workspace.',
    'cannot_change_own_role' => 'You cannot change your own role.',
    'cannot_remove_self' => 'You cannot remove yourself from the workspace.',
    'owner_assign_only' => 'Only an owner can assign the owner role.',
    'last_owner' => 'A workspace must have at least one owner.',
    'member_not_found' => 'Member not found.',

    'forbidden' => 'You do not have permission to perform this action.',
    'workspace_required' => 'Workspace is required.',
    'workspace_no_access' => 'You do not have access to this workspace.',
    'workspace_suspended' => 'This workspace is suspended.',
    'workspace_invalid_role' => 'Your role in this workspace is invalid.',
    'workspace_not_resolved' => 'Workspace not resolved.',
    'workspace_not_found' => 'Workspace not found.',
    'super_admin_only' => 'Only a super admin can perform this action.',
];
