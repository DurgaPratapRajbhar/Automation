<?php

namespace App\Enums;

/**
 * Single source of truth for every permission in the app.
 * Add a case here, then run `php artisan permissions:sync`.
 */
enum Permission: string
{
    case MembersView = 'members.view';
    case MembersCreate = 'members.create';
    case MembersEdit = 'members.edit';
    case MembersDelete = 'members.delete';

    case ChannelsView = 'channels.view';
    case ChannelsCreate = 'channels.create';
    case ChannelsEdit = 'channels.edit';
    case ChannelsDelete = 'channels.delete';

    case ConversationsView = 'conversations.view';
    case ConversationsReply = 'conversations.reply';
    case ConversationsAssign = 'conversations.assign';
    case ConversationsDelete = 'conversations.delete';

    case KnowledgeView = 'knowledge.view';
    case KnowledgeCreate = 'knowledge.create';
    case KnowledgeEdit = 'knowledge.edit';
    case KnowledgeDelete = 'knowledge.delete';

    case AiSettingsView = 'ai-settings.view';
    case AiSettingsEdit = 'ai-settings.edit';

    case LeadsView = 'leads.view';
    case LeadsCreate = 'leads.create';
    case LeadsEdit = 'leads.edit';
    case LeadsDelete = 'leads.delete';
    case LeadsExport = 'leads.export';

    case AnalyticsView = 'analytics.view';
    case AnalyticsExport = 'analytics.export';

    case WorkspaceView = 'workspace.view';
    case WorkspaceEdit = 'workspace.edit';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesEdit = 'roles.edit';
    case RolesDelete = 'roles.delete';

    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersEdit = 'customers.edit';
    case CustomersDelete = 'customers.delete';

    // Group name shown in the role form, e.g. "customers"
    public function module(): string
    {
        return explode('.', $this->value)[0];
    }

    // Human label, e.g. "customers.view" -> "View Customers"
    public function label(): string
    {
        [$module, $action] = explode('.', $this->value);

        return ucfirst($action) . ' ' . ucwords(str_replace('-', ' ', $module));
    }

    // Route middleware string, e.g. "permission:customers.view"
    public function middleware(): string
    {
        return 'permission:' . $this->value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @param list<self> $permissions @return list<string> */
    public static function valuesOf(array $permissions): array
    {
        return array_map(fn (self $permission) => $permission->value, $permissions);
    }

    /** @param list<self> $excluded @return list<string> */
    public static function except(array $excluded): array
    {
        return array_values(array_diff(self::values(), self::valuesOf($excluded)));
    }
}
