<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/11/29
 * Time: 下午9:21
 */

namespace ZineAdmin\Permission\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use ZineAdmin\Permission\Contracts\RoleContract;

trait HasRoles
{
    use RefreshCache;

    /**
     * 用户拥有多个角色
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.user_has_roles'),
            'user_id',
            'role_id'
        );

    }

    /**
     * 缓存用户角色列表信息
     * @return RoleContract|Collection
     */
    public function cachedRoles()
    {
        $cacheKey = 'zine_roles_for_user_' . $this->attributes[$this->primaryKey];
        return $this->getCachedByDebug($cacheKey, function () {
            return $this->roles()->get();
        });
    }

    /**
     * 将用户查询限定为某些角色
     * Scope the model query to certain roles only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|RoleContract|\Illuminate\Support\Collection $roles
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRole(Builder $query, $roles): Builder
    {

        if ($roles instanceof Collection === false) {
            $roles = collect(array_wrap($roles));
        }

        $roles = $roles->filter()->map(function ($role) {
            if ($role instanceof RoleContract) {
                return $role;
            }
            return app(RoleContract::class)->whereName($role)->first();
        })->filter();

        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                }
            });
        });
    }

    /**
     *
     * 分配角色给用户
     * Assign the given role to the model.
     *
     * @param array|string|\ZineAdmin\Permission\Contracts\RoleContract ...$roles
     *
     * @return $this
     */
    public function assignRoles(...$roles)
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                return $this->getStoredRole($role);
            })
            ->filter()
            ->all();

        $this->roles()->saveMany($roles);

        $this->forgetCachedRolesForUser($this->attributes[$this->primaryKey]);

        return $this;
    }

    /**
     * 移除用户身上的角色关联
     * Revoke the given role from the model.
     *
     * @param array|string|\ZineAdmin\Permission\Contracts\RoleContract ...$roles
     * @return integer;
     */
    public function removeRoles(...$roles)
    {
        $result = collect($roles)
            ->flatten()
            ->map(function ($role) {
                return $this->roles()->detach($this->getStoredRole($role));
            });
        $this->forgetCachedRolesForUser($this->attributes[$this->primaryKey]);

        return $result->sum();
    }

    /**
     * 重新同步用户角色对应关系
     * Remove all current roles and set the given ones.
     *
     * @param array|\ZineAdmin\Permission\Contracts\RoleContract|string ...$roles
     *
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $this->roles()->detach();

        return $this->assignRoles($roles);
    }

    /**
     * @param string|mixed $role
     * @return RoleContract|null
     */
    protected function getStoredRole($role)
    {
        if (is_numeric($role)) {
            return app(RoleContract::class)->whereId($role)->first();
        }
        if (is_string($role)) {
            return app(RoleContract::class)->whereName($role)->first();
        }
        return $role;
    }

    /**
     * 检查用户是否拥有任一角色
     *
     * @param string|array $permissions 要检查的权限
     *
     * @return bool
     */
    public function hasAnyPermissions($permissions)
    {
        foreach ($this->cachedRoles() as $role) {
            /**
             * @var RoleContract $role
             */
            if ($role->hasAnyPermissions($permissions) == true) {
                return true;
            }
        }

        return false;
    }

    /**
     * 确定是否具有所有指定的权限
     * @param $permissions
     * @return bool
     */
    public function hasAllPermissions($permissions)
    {
        $permissions = collect(array_wrap($permissions));
        return $permissions->every(function ($permission) {
            return $this->hasAnyPermissions($permission);
        });

    }

    /**
     * 确定用户是否有指定角色
     * @param string|array|RoleContract|Collection $roles
     * @return bool
     */
    public function hasRole($roles)
    {
        return $this->hasAnyRoles($roles);
    }

    /**
     * 确定是否含有任一角色。
     *
     * @param string|array|RoleContract|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRoles($roles): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->cachedRoles()->contains('name', $roles);
        }

        if ($roles instanceof RoleContract) {
            return $this->cachedRoles()->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasAnyRoles($role)) {
                    return true;
                }
            }

            return false;
        }

        //collection 集合
        return (bool)$roles->intersect($this->cachedRoles())->isNotEmpty();
    }

    /**
     * 确认是否包含所有角色
     * @param string|RoleContract|array $roles
     * @return bool
     */
    public function hasAllRoles($roles): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->cachedRoles()->contains('name', $roles);
        }

        if ($roles instanceof RoleContract) {
            return $this->cachedRoles()->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof RoleContract ? $role->name : $role;
        });
        return $roles->intersect($this->cachedRoles()->pluck('name')) == $roles;

    }

    /**
     * 判断用户是否超级用户
     * @return bool
     */
    public function hasSuperAdmin(): bool
    {
        return $this->primaryKey === 1;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (!in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}