<?php

return [

    'models' => [

        'permission' => ZineAdmin\Permission\Models\Permission::class,

        'role' => ZineAdmin\Permission\Models\Role::class,

    ],

    'table_names' => [

        'roles' => 'roles',

        'user_has_roles' => 'user_has_roles',

        'role_has_permissions' => 'role_has_permissions',
    ],

    'cache_expiration_time' => 60 * 24,
];
