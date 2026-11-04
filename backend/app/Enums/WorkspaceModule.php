<?php

namespace App\Enums;

/**
 * Parts of the app a shop can have switched on. The super admin turns them on per shop.
 * A permission such as "customers.view" belongs to a module through the first word of its name.
 * Permissions that belong to no module (workspace profile, api-key, audit-logs...) are always available.
 */
enum WorkspaceModule: string
{
    case Inbox = 'inbox';
    case WhatsApp = 'whatsapp';
    case Knowledge = 'knowledge';
    case Orders = 'orders';
    case Customers = 'customers';
    case Team = 'team';
    case Campaigns = 'campaigns';

    // What a shop that signs up by itself starts with
    public static function starter(): array
    {
        return [self::Inbox, self::WhatsApp, self::Knowledge];
    }

    public static function values(?array $modules = null): array
    {
        return array_map(fn (self $module) => $module->value, $modules ?? self::cases());
    }

    // First words of the permission names that belong to this module
    public function permissionGroups(): array
    {
        return match ($this) {
            self::Inbox => ['conversations', 'messages'],
            self::WhatsApp => ['channels', 'templates'],
            self::Knowledge => ['knowledge-sources'],
            self::Orders => ['orders', 'promises', 'products', 'actions', 'overview'],
            self::Customers => ['customers'],
            self::Team => ['members', 'roles'],
            self::Campaigns => ['contact-imports', 'campaigns', 'campaign-drafts'],
        };
    }

    // The module a permission belongs to; null = always available
    public static function forPermission(string $permission): ?self
    {
        $group = explode('.', $permission)[0];

        foreach (self::cases() as $module) {
            if (in_array($group, $module->permissionGroups(), true)) {
                return $module;
            }
        }

        return null;
    }
}
