<?php

namespace ZineAdmin\Permission\Commands;

use Illuminate\Console\Command;
use ZineAdmin\Permission\Contracts\PermissionContract;

class CreatePermission extends Command
{
    protected $signature = 'permission:create-permission 
                {name : The name of the permission} 
                {desc? : The name of the guard}';

    protected $description = 'Create a permission';

    public function handle()
    {
        $name = $this->argument('name');
        if(!str_contains($name, ':')){
            $this->error('权限 name 格式为:  操作:资源1/资源2');
            return;
        }


        $permissionClass = app(PermissionContract::class);

        $permission = $permissionClass::create([
            'name' => $this->argument('name')
        ]);

        $this->info("Permission `{$permission->name}` created");
    }
}
