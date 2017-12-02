<?php

namespace ZineAdmin\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

Interface RoleContract
{
    /**
     * 角色拥有的权限
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions(): HasMany;

    /**
     * 角色对应的用户
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany;

    /**
     * 确定角色是否拥有对应的权限
     * @param string|array $permissions
     *
     * @return boolean
     */
    public function hasAnyPermissions($permissions);
}
