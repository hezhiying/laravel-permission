<?php

namespace ZineAdmin\Permission;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use ZineAdmin\Permission\Exceptions\UnauthorizedException;

class PermissionManage
{
    /** @var Collection */
    public $container;

    /** @var Resource */
    public $resource;

    /** @var string  */
    public $globalCacheTagKey = 'zine.permission.cache';

    /** @var \Illuminate\Contracts\Cache\Repository */
    public $cache;
    /**
     * Permissions constructor.
     */
    public function __construct()
    {
        $this->container = collect();

        $this->resource = new Resource();

        $this->cache = app('cache');
    }

    /**
     *
     * 注册权限资源
     * registerPermissions(['create:dashboard/users'])
     * @param array $permission
     *
     * @return Collection
     */
    public function registerPermissions(array $permission)
    {
        foreach ($permission as $perm => $desc) {
            if (str_contains($perm, ":")) {
                $perms = explode(":", $perm);
                $this->resource->create($perms[1])->addOperation($perms[0], $desc);
            } else {
                $this->resource->create($perm, $desc);
            }
        }
        $this->container->push($this->container->merge($permission));

        //$old = $this->container->get(key($permission), []);

        //$this->container->put(key($permission), array_merge_recursive($old, $permission));
        //return $this->container;
    }

    /**
     * 获取权限资源
     * @return Resource
     */
    public function getResource(): Resource
    {
        return $this->resource->root;
    }

    /**
     * 弄平资源（一维）
     * @return Collection
     */
    public function getFlattenResource()
    {
        return $this->resource->resFlatten();
    }

    /**
     * 检查res_id是否存在
     * @param $permission
     * @return bool
     */
    public function checkPermissionExists($permission): bool
    {
        return $this->resource->checkPermissionExists($permission);
    }

    /***
     * 确认需要的权限是否在权限列表里（检查多个权限，只要有一个权限存在则返回True)
     *
     * @param array|string $permissions
     * @param array $resources
     *
     * @return bool
     */
    public function hasAnyPermissions($permissions, $resources): bool
    {
        if (empty ($resources) || !$permissions) {
            return false;
        }
        //如果是数组则遍历调用
        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                if ($this->hasAnyPermissions($permission, $resources) == true) {
                    return true;
                }
            }
        } elseif (is_string($permissions)) {
            //检查权限是否存在
            //判断是否有|分隔
            if(str_contains($permissions, "|")){
               return  $this->hasAnyPermissions(explode('|', $permissions), $resources);
            }
            $permission = $permissions;
            if (isset ($resources [$permission])) {
                return $resources [$permission] == 1;
            } else {
                //将权限转成 action:resources [操作,资源]
                $permission_array = collect(explode(':', $permission));
                if ($permission_array->count() < 2) {
                    //格式不正确
                    return false;
                }
                //遍历每一个资源，检查权限列表中有没有对应的
                $resources_array = collect(explode('/', $permission_array->last())); //资源
                while ($resources_array->count()) {
                    $acl = '*:' . $resources_array->implode('/');
                    if (isset ($resources [$acl])) {
                        return $resources [$acl] == 1;
                    }
                    $resources_array->pop();
                }
            }
        }

        return false;
    }

    /**
     * 检查控制器注释的权限
     * @param object $controller 当前控制器
     * @param string $methodName 方法名
     * @return null
     */
    public function checkPermissionsForController($controller, $methodName)
    {
        $globalSetting = ['login' => false];

        $reflectionObj = new \ReflectionObject($controller);
        if ($reflectionObj instanceof \ReflectionObject) {
            $ann                               = new Annotation($reflectionObj);
            $globalSetting['login']  = $globalSetting['login'] || $ann->has('login');

            $globalSetting['role']  = $ann->getArray('role');
            $globalSetting['permission']    = $ann->getArray('permission');
            $globalSetting['aclmsg'] = $ann->getString('aclmsg', 'You have no access to this resource!');

            $reflectionMethod = new \ReflectionMethod($controller, $methodName);
            $annotation       = new Annotation($reflectionMethod);
            //不需要登录
            $nologin = $annotation->has('nologin');
            if ($nologin) {
                return null;
            }

            //登录检测
            $login = $annotation->has('login') || $globalSetting['login'];

            if ($annotation->has('permission')) {
                $permission = $annotation->getArray('permission');
            } else {
                $permission = $globalSetting['permission'];
            }

            if ($annotation->has('role')) {
                $role = $annotation->getArray('role');
            } else {
                $role = $globalSetting['role'];
            }

            $login = $login || $permission || $role;
            //登入检查
            if ($login && Auth::guest()) {
                throw UnauthorizedException::notLoggedIn();
            }

            //权限检查
            if ($permission && Auth::user()->hasAnyPermissions($permission) == false) {
                throw UnauthorizedException::forPermissions($permission);
            }
            //角色检查
            if ($role && Auth::user()->hasAnyRoles($role) == false) {
                throw UnauthorizedException::forRoles($role);
            }

        }
    }

    /**
     * 手动清空所有缓存
     * @param string|null $cacheKey
     */
    public function forgetCachedPermissions($cacheKey = null)
    {
        if ($this->cache->getStore() instanceof TaggableStore) {
            if($cacheKey){
                return $this->cache->tags($this->globalCacheTagKey)->forget($cacheKey);
            }else{
                return $this->cache->tags($this->globalCacheTagKey)->flush();
            }
        }
    }


}