<?php 

namespace App\Repositories\Eloquent;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\CursorPaginator;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    public function cursorForWorkspace(int $workspaceId, int $perPage = 25): CursorPaginator
{
   
       return AuditLog::query()
            ->where('audit_logs.workspace_id', $workspaceId)
            // who did it
            ->leftJoin('users as actor', 'actor.id', '=', 'audit_logs.actor_user_id')
            // whom it was done to (only when the subject is a member)
            ->leftJoin('users as subject_user', function ($join) {
                $join->on('subject_user.id', '=', 'audit_logs.subject_id')
                     ->where('audit_logs.subject_type', '=', 'member');
            })
            ->select([
                'audit_logs.*',
                'actor.name as actor_name',
                'subject_user.name as subject_name',
            ])
        ->orderByDesc('audit_logs.created_at')
        ->orderByDesc('audit_logs.id')      // tie-breaker: two rows in the same second
        ->cursorPaginate($perPage);
}
}