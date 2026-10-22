<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function getAllNames(): array
    {
        return Permission::orderBy('name')->pluck('name')->all();
    }

    public function getAllByModule(): Collection
    {
        return Permission::orderBy('module')->orderBy('name')->get(['id', 'name', 'module']);
    }

    public function createMissing(array $names): int
    {
        $missing = array_diff($names, Permission::whereIn('name', $names)->pluck('name')->all());

        foreach ($missing as $name) {
            Permission::create(['name' => $name, 'module' => explode('.', $name)[0]]);
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
