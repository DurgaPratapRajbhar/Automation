<?php

use App\Enums\Permission;

/*
| Roles every new workspace gets on registration (besides the Owner role).
| They are normal roles: the owner can rename, edit or delete them later.
*/
return [

    'owner' => [
        'name' => 'Owner',
    ],

    'default_roles' => [

        'manager' => [
            'name' => 'Manager',
            // Everything except role management, workspace settings and removing members
            'permissions' => Permission::except([
                Permission::RolesView,
                Permission::RolesCreate,
                Permission::RolesEdit,
                Permission::RolesDelete,
                Permission::WorkspaceEdit,
                Permission::MembersDelete,
            ]),
        ],

        'staff' => [
            'name' => 'Staff',
            'permissions' => Permission::valuesOf([
                Permission::ConversationsView,
                Permission::ConversationsReply,
                Permission::CustomersView,
                Permission::CustomersCreate,
                Permission::LeadsView,
                Permission::LeadsCreate,
                Permission::KnowledgeView,
            ]),
        ],

    ],

];
