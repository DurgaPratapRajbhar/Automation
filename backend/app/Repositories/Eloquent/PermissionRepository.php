<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function getAllIds(): array
    {
        return Permission::pluck('id')->all();
    }
}