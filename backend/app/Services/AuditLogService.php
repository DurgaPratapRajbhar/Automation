<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

class AuditLogService
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogs
    ) {}

  public function list(int $workspaceId, int $perPage): CursorPaginator
{
    $perPage = max(1, min($perPage, config('pagination.max_per_page')));

    return $this->auditLogs
        ->cursorForWorkspace($workspaceId, $perPage)
        ->through(fn (AuditLog $log) => [
                'id'      => $log->id,
                'action'  => $log->action,
                'actor'   => [
                    'id'   => $log->actor_user_id,
                    'name' => $log->actor_name ?? 'Deleted user',
                ],
                'subject' => [
                    'type' => $log->subject_type,
                    'id'   => $log->subject_id,
                    'name' => $log->subject_name,
                ],
                'changes'    => $log->changes,
                'created_at' => $log->created_at,
            ]);
    }
}