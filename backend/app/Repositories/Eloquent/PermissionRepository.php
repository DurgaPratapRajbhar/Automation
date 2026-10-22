<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function getAllNames(): array
    {
        return Permission::orderBy('id')->pluck('name')->all();
    }

    public function createMissing(array $names): int
    {
        $existing = Permission::whereIn('name', $names)->pluck('name')->all();
        $missing = array_diff($names, $existing);

        foreach ($missing as $name) {
            Permission::create(['name' => $name]);
        }

        return count($missing);
    }

    public function deleteExcept(array $names): int
    {
        $stale = Permission::whereNotIn('name', $names)->get();

        // Model delete (not a bulk query) so Spatie also detaches it from roles
        $stale->each->delete();

        return $stale->count();
    }
}
