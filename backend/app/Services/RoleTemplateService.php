<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\RoleTemplate;
use App\Repositories\Contracts\RoleTemplateRepositoryInterface;
use Illuminate\Support\Str;

// Super admin CRUD for the default roles of new workspaces
class RoleTemplateService
{
    public function __construct(
        private RoleTemplateRepositoryInterface $templateRepository
    ) {}

    public function list(): array
    {
        return $this->templateRepository->all()
            ->map(fn (RoleTemplate $template) => $this->present($template))
            ->all();
    }

    public function create(array $data): array
    {
        return $this->present($this->templateRepository->create([
            'name' => $data['name'],
            'slug' => $this->makeUniqueSlug($data['name']),
            'permissions' => $data['permissions'],
        ]));
    }

    public function update(int $templateId, array $data): array
    {
        $template = $this->findOrFail($templateId);

        if (isset($data['name'])) {
            $data['slug'] = $this->makeUniqueSlug($data['name'], $template->id);
        }

        return $this->present($this->templateRepository->update($template, $data));
    }

    public function delete(int $templateId): void
    {
        // Existing workspaces keep their copied roles
        $this->templateRepository->delete($this->findOrFail($templateId));
    }

    private function makeUniqueSlug(string $name, ?int $ignoreTemplateId = null): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw new BusinessRuleException(__('rbac.invalid_role_name'));
        }

        if ($slug === Role::OWNER_SLUG || $this->templateRepository->slugExists($slug, $ignoreTemplateId)) {
            throw new BusinessRuleException(__('rbac.role_exists'));
        }

        return $slug;
    }

    private function findOrFail(int $templateId): RoleTemplate
    {
        $template = $this->templateRepository->find($templateId);

        if (!$template) {
            throw new BusinessRuleException(__('rbac.role_not_found'), 404);
        }

        return $template;
    }

    private function present(RoleTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'permissions' => $template->permissions,
        ];
    }
}
