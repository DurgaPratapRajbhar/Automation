<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    public function getAllIds(): array;

    public function getAll(): Collection;

    public function getAllSlugs(): array;

    public function getIdsBySlugs(array $slugs): array;
}
