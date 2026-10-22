<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    public function getAllNames(): array;

    // All permissions ordered by module, for the permission tree
    public function getAllByModule(): Collection;

    // Returns how many were created
    public function createMissing(array $names): int;

    // Returns how many were deleted
    public function deleteExcept(array $names): int;
}
