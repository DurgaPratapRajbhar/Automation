<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function getAllIds(): array
    {
        return Permission::pluck('id')->all();
    }

    public function getAll(): Collection
    {
        return Permission::orderBy('id')->get(['id', 'name', 'slug']);
    }

    public function getAllSlugs(): array
    {
        return Permission::orderBy('id')->pluck('slug')->all();
    }

    public function getIdsBySlugs(array $slugs): array
    {
        return Permission::whereIn('slug', $slugs)->pluck('id')->all();
    }
}
