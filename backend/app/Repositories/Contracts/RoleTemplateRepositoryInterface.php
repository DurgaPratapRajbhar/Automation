<?php

namespace App\Repositories\Contracts;

use App\Models\RoleTemplate;
use Illuminate\Support\Collection;

interface RoleTemplateRepositoryInterface
{
    public function all(): Collection;

    public function find(int $templateId): ?RoleTemplate;

    public function slugExists(string $slug, ?int $ignoreTemplateId = null): bool;

    public function create(array $data): RoleTemplate;

    public function update(RoleTemplate $template, array $data): RoleTemplate;

    public function delete(RoleTemplate $template): void;
}
