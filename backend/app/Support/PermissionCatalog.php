<?php

namespace App\Support;

/**
 * App ki saari permissions ki master list.
 * Ye code ka hissa hai (routes inhi slugs par depend karte hain), isliye inka CRUD API nahi hai.
 * Nayi permission chahiye -> yahan add karo + ek nayi migration jo PermissionCatalog::sync() chalaye.
 */
class PermissionCatalog
{
    public static function all(): array
    {
        return [
            // Members
            ['name' => 'View Members', 'slug' => 'members.view'],
            ['name' => 'Create Members', 'slug' => 'members.create'],
            ['name' => 'Edit Members', 'slug' => 'members.edit'],
            ['name' => 'Delete Members', 'slug' => 'members.delete'],

            // Channels
            ['name' => 'View Channels', 'slug' => 'channels.view'],
            ['name' => 'Create Channels', 'slug' => 'channels.create'],
            ['name' => 'Edit Channels', 'slug' => 'channels.edit'],
            ['name' => 'Delete Channels', 'slug' => 'channels.delete'],

            // Conversations
            ['name' => 'View Conversations', 'slug' => 'conversations.view'],
            ['name' => 'Reply Conversations', 'slug' => 'conversations.reply'],
            ['name' => 'Assign Conversations', 'slug' => 'conversations.assign'],
            ['name' => 'Delete Conversations', 'slug' => 'conversations.delete'],

            // Knowledge Base
            ['name' => 'View Knowledge', 'slug' => 'knowledge.view'],
            ['name' => 'Create Knowledge', 'slug' => 'knowledge.create'],
            ['name' => 'Edit Knowledge', 'slug' => 'knowledge.edit'],
            ['name' => 'Delete Knowledge', 'slug' => 'knowledge.delete'],

            // AI Settings
            ['name' => 'View AI Settings', 'slug' => 'ai-settings.view'],
            ['name' => 'Edit AI Settings', 'slug' => 'ai-settings.edit'],

            // Leads
            ['name' => 'View Leads', 'slug' => 'leads.view'],
            ['name' => 'Create Leads', 'slug' => 'leads.create'],
            ['name' => 'Edit Leads', 'slug' => 'leads.edit'],
            ['name' => 'Delete Leads', 'slug' => 'leads.delete'],
            ['name' => 'Export Leads', 'slug' => 'leads.export'],

            // Analytics
            ['name' => 'View Analytics', 'slug' => 'analytics.view'],
            ['name' => 'Export Analytics', 'slug' => 'analytics.export'],

            // Workspace Settings
            ['name' => 'View Workspace Settings', 'slug' => 'workspace.view'],
            ['name' => 'Edit Workspace Settings', 'slug' => 'workspace.edit'],

            // Roles
            ['name' => 'View Roles', 'slug' => 'roles.view'],
            ['name' => 'Create Roles', 'slug' => 'roles.create'],
            ['name' => 'Edit Roles', 'slug' => 'roles.edit'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete'],
        ];
    }
}
