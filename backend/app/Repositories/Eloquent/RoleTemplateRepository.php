<?php

namespace App\Repositories\Eloquent;

use App\Models\RoleTemplate;
use App\Repositories\Contracts\RoleTemplateRepositoryInterface;
use Illuminate\Support\Collection;

class RoleTemplateRepository implements RoleTemplateRepositoryInterface
{
    public function all(): Collection
    {
        return RoleTemplate::orderBy('id')->get();
    }

    public function find(int $templateId): ?RoleTemplate
    {
        return RoleTemplate::find($templateId);
    }

    public function slugExists(string $slug, ?int $ignoreTemplateId = null): bool
    {
        return RoleTemplate::where('slug', $slug)
            ->when($ignoreTemplateId, fn ($query) => $query->where('id', '!=', $ignoreTemplateId))
            ->exists();
    }

    public function create(array $data): RoleTemplate
    {
        return RoleTemplate::create($data);
    }

    public function update(RoleTemplate $template, array $data): RoleTemplate
    {
        $template->update($data);

        return $template;
    }

    public function delete(RoleTemplate $template): void
    {
        $template->delete();
    }
}
