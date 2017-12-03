<?php

namespace ZineAdmin\Permission\Traits;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
        $cacheTagKey = $this->getCacheTagKey();

        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($cacheTagKey)->remember($cacheKey, config('permission.cache_expiration_time', 60), function () use ($fun) {
                return call_user_func($fun);
            });
        } else return call_user_func($fun);
    }

    /**
     * 手动清空所有缓存
     */
    public function forgetCachedPermissions()
    {
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($this->getCacheTagKey())->flush();
        }
    }

    /**
     * 清除用户对应的角色缓存
     * @param int $userID
     *
     */
    public function forgetCachedRolesForUser($userID = null)
    {
        $cacheKey = $this->getCacheKeyForUser($userID);
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($this->getCacheTagKey())->forget($cacheKey);
        }
    }

    /**
     * 清空角色对应的权限列表
     * @param int $roleID
     */
    public function forgetCachedPermissionsForRole($roleID = null)
    {
        $cacheKey = $this->getCacheKeyForRole($roleID);
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($this->getCacheTagKey())->forget($cacheKey);
        }
    }

    /**
     * 获取缓存tag名称
     * @return string
     */
    protected function getCacheTagKey()
    {
        return 'zine.permission.cache';
    }

    /**
     * 指定用户的角色缓存KEY
     * @param $userID
     * @return string
     */
    protected function getCacheKeyForUser($userID)
    {
        return 'zine_roles_for_user_' . $userID;
    }

    /**
     * 指定角色的权限缓存KEY
     * @param $roleID
     * @return string
     */
    protected function getCacheKeyForRole($roleID)
    {
        return 'zine_permissions_for_role_' . $roleID;
    }

    /**
     *
     * @param $pipeContent
     * @return Collection
     */
    protected function convertPipeToArray($pipeContent): Collection
    {
        return collect(array_wrap($pipeContent))
            ->map(function ($content) {
                if (is_string($content)) {
                    return explode('|', trim($content));
                } else {
                    return $content;
                }
            })
            ->flatten();
    }
}