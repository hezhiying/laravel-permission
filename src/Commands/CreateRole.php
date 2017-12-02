<?php

namespace ZineAdmin\Permission\Commands;

use Illuminate\Console\Command;
use ZineAdmin\Permission\Contracts\PermissionContract;
use ZineAdmin\Permission\Contracts\RoleContract;

class CreateRole extends Command
{
    protected $signature = 'permission:create-role
        {name : The name of the role}
        {desc? : The name of the guard}';

    protected $description = 'Create a role';

    public function handle()
    {
        $roleClass = app(RoleContract::class);

        $role = $roleClass::create([
            'name' => $this->argument('name'),
            'desc' => $this->argument('desc')?:'',
        ]);

        $this->info("Role `{$role->name}` created");
    }
}
