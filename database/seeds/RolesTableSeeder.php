<?php

use Illuminate\Database\Seeder;
use ZineAdmin\Permission\Models\Role;

class RolesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        //新添admin 和 user 角色
        $role = Role::firstOrNew(['name' => 'admin']);
        if (!$role->exists) {
            $role->fill([
                    'desc' => 'Administrator',
                ])->save();
        }

        $role = Role::firstOrNew(['name' => 'user']);
        if (!$role->exists) {
            $role->fill([
                    'desc' => 'Normal User',
                ])->save();
        }
    }
}
