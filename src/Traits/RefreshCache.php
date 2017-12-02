<?php

namespace ZineAdmin\Permission\Traits;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use ZineAdmin\Permission\PermissionManage;

trait RefreshCache
{
    /**
     * 获取缓存内容
     * @param string $cacheKey
     * @param \Closure $fun
     *
     * @return mixed
     */
    protected function getCachedByDebug($cacheKey, \Closure $fun)
    {
        $cacheTagKey = app(PermissionManage::class)->globalCacheTagKey;

        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($cacheTagKey)->remember($cacheKey, config('cache.ttl', 60), function () use ($fun) {
                return call_user_func($fun);
            });
        } else return call_user_func($fun);
    }

    /**
     * 清除用户对应的角色缓存
     * @param int $userID
     *
     */
    public function forgetCachedRolesForUser($userID = null)
    {
        $cacheKey = 'zine_roles_for_user_' . $userID;
        return app(PermissionManage::class)->forgetCachedPermissions($cacheKey);
    }

    /**
     * 清空角色对应的权限列表
     * @param int $roleID
     */
    public function forgetCachedPermissionsForRole($roleID = null)
    {
        $cacheKey = 'zine_permissions_for_role_' . $roleID;
        return app(PermissionManage::class)->forgetCachedPermissions($cacheKey);
    }

}