<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/11/29
 * Time: 下午4:07
 */

namespace ZineAdmin\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ZineAdmin\Permission\Contracts\RoleContract;
use ZineAdmin\Permission\Exceptions\PermissionDoesNotExist;
use ZineAdmin\Permission\PermissionManage;
use ZineAdmin\Permission\Traits\HasNodeTrait;
use ZineAdmin\Permission\Traits\RefreshCache;

class Role extends Model implements RoleContract
{
    use RefreshCache, HasNodeTrait;

    public $guarded = ['id'];

    protected $table = 'roles';

    /**
     * 角色拥有的权限
     * @return HasMany
     */
    public function permissions(): HasMany
    {
        return $this->HasMany(
            config('permission.models.permission'),
            'role_id'
        );
    }

    /**
     * 角色对应的用户
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('permission.table_names.user_has_roles'),
            'role_id',
            'user_id'
        );
    }

    /**
     * 确定角色是否拥有其中之一个权限
     *
     * @param string|array $permission
     *
     * @return boolean
     */
    public function hasAnyPermissions($permission): bool
    {
        $permissionManage = app(PermissionManage::class);
        return $permissionManage->hasAnyPermissions($permission, $this->cachedPermissions());
    }

    /**
     * 缓存角色对应的权限资源列表信息
     * @return array
     */
    public function cachedPermissions(): array
    {
        $cacheKey = 'zine_permissions_for_role_' . $this->attributes[$this->primaryKey];

        return $this->getCachedByDebug($cacheKey, function () {
            $operations = [];
            $permissions = $this->permissions()->get();
            foreach ($permissions as $permission) {
                $operations [$permission ['permission']] = $permission ['allowed'];
            }

            return $operations;
        });
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param array $permissions
     *
     * @return $this
     */
    public function givePermissionToAllowed(...$permissions)
    {
        $permissionManage = app(PermissionManage::class);
        foreach (collect($permissions)->flatten()->filter()->all() as $permission) {
            throw_unless(
                $permissionManage->checkPermissionExists($permission),
                PermissionDoesNotExist::create($permission)
            );

            $this->permissions()->updateOrCreate(['permission' => $permission], ['allowed' => 1]);
        }

        $this->forgetCachedPermissionsForRole($this->attributes[$this->primaryKey]);
        return $this;
    }

    /**
     * 授权指定权限禁止
     * @param array ...$permissions
     * @return $this
     */
    public function givePermissionToDeny(...$permissions)
    {
        $permissionManage = app(PermissionManage::class);
        foreach (collect($permissions)->flatten()->filter()->all() as $permission) {
            throw_unless(
                $permissionManage->checkPermissionExists($permission),
                PermissionDoesNotExist::create($permission)
            );
            $this->permissions()->updateOrCreate(['permission' => $permission], ['allowed' => 0]);
        }

        $this->forgetCachedPermissionsForRole($this->attributes[$this->primaryKey]);

        return $this;
    }

    /**
     * Revoke the given permission.
     *
     * @param array ...$permissions
     *
     * @return $this
     */
    public function removePermission(...$permissions)
    {
        $result = collect($permissions)
        ->flatten()
        ->filter()
        ->map(function ($permission) {
            return $this->permissions()->wherePermission($permission)->delete();
        });
        $this->forgetCachedPermissionsForRole($this->attributes[$this->primaryKey]);

        return $result->sum();
    }
}
