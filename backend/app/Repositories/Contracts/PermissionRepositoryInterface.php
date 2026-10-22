<?php

namespace App\Repositories\Contracts;

interface PermissionRepositoryInterface
{
    /** @return list<string> */
    public function getAllNames(): array;

    /** @param list<string> $names @return int number created */
    public function createMissing(array $names): int;

    /** @param list<string> $names @return int number deleted */
    public function deleteExcept(array $names): int;
}
