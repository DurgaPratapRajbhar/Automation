<?php 
namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
interface AuditLogRepositoryInterface
{
     
    public function create(array $data): AuditLog;
    public function cursorForWorkspace(int $workspaceId, int $perPage): CursorPaginator;
    
}
