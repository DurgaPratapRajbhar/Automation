<?php

namespace App\Listeners;

use App\Events\MemberRemoved;
use App\Events\MemberRoleChanged;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

class WriteAuditLog
{
    public function __construct(private AuditLogRepositoryInterface $auditLogs) {}

    public function handle(MemberRoleChanged $event): void
    {
        // logger()->info('Audit: member role changed', [
        //     'workspace_id'   => $event->workspaceId,
        //     'actor_user_id'  => $event->actorUserId,   // who changed it
        //     'member_user_id' => $event->memberUserId,  // whose role was changed
        //     'old_role'       => $event->oldRoleName,
        //     'new_role'       => $event->newRoleName,
        // ]);

        $this->auditLogs->create([
            'workspace_id' => $event->workspaceId,
            'actor_user_id' => $event->actorUserId,
            'action' => 'member.role_changed',
            'subject_type' => 'member',
            'subject_id' => $event->memberUserId,
            'changes' => [
                'old_role' => $event->oldRoleName,
                'new_role' => $event->newRoleName,
            ],
        ]);
    }

    public function handleMemberRemoved(MemberRemoved $event): void
    {
        $this->auditLogs->create([
            'workspace_id' => $event->workspaceId,
            'actor_user_id' => $event->actorUserId,
            'action' => 'member.removed',
            'subject_type' => 'member',
            'subject_id' => $event->memberUserId,
            'changes' => [
                'role' => $event->roleName,
            ],
        ]);
    }
}
