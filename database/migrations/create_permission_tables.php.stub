<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePermissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableNames = config('permission.table_names');

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('desc', 1000)->nullable();
            $table->unsignedInteger('lft')->default(0);
            $table->unsignedInteger('rgt')->default(0);
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamps();
            $table->index([ 'lft', 'rgt', 'parent_id' ]);

        });

        Schema::create($tableNames['user_has_roles'], function (Blueprint $table) use ($tableNames) {
            $user = config('auth.providers.users.model');
            $table->integer('user_id')->unsigned();
            $table->integer('role_id')->unsigned();
		    $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
//		    $table->foreign('user_id')->references('id')->on((new $user())->getTable())->onDelete('cascade');

            $table->primary([
                'user_id',
                'role_id'
            ]);
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->string('permission');
            $table->integer('allowed')->default(0);;
            $table->integer('sort')->default(999);
            $table->timestamps();

		    $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            $table->unique([
                'role_id',
                'permission'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableNames = config('permission.table_names');

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['user_has_roles']);
        Schema::drop($tableNames['roles']);
    }
}
