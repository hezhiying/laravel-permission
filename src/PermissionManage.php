<?php

namespace ZineAdmin\Permission;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use ZineAdmin\Permission\Exceptions\UnLoginException;
use ZineAdmin\Permission\Exceptions\UnPermissionException;
use ZineAdmin\Permission\Exceptions\UnRolesException;
use ZineAdmin\Permission\Traits\RefreshCache;

class RefreshCacheExtend
{
    use RefreshCache;
}

class PermissionManage extends RefreshCacheExtend
{
    /** @var Collection */
    public $container;

    /** @var Resource */
    public $resource;

    /**
     * Permissions constructor.
     */
    public function __construct()
    {
        $this->container = collect();

        $this->resource = new Resource();
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

    /**
     * 检查资源中权限是否允许或禁止
     * @param string $permission
     * @param array $resources
     * @return bool|null
     */
    public function hasPermission(string $permission, array $resources)
    {
        $permission = trim($permission, ':');
        if (!str_contains($permission, ':')) {
            $permission = '*:' . $permission;
        }
        if (isset ($resources [$permission])) {
            return $resources [$permission] == 1;
        } else {
            //将权限转成 action:resources [操作,资源]
            $perm_resources = collect(explode('/', str_after($permission, ':')));
            while ($perm_resources->isNotEmpty()) {
                $acl = '*:' . $perm_resources->implode('/');
                if (isset ($resources [$acl])) {
                    return $resources [$acl] == 1;
                }
                $perm_resources->pop();
            }
        }
        return null;
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
        $permissions = $this->convertPipeToArray($permissions);
        //只需要其中一个具有权限
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $resources)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查控制器注释的权限
     * @param object $controller 当前控制器
     * @param string $methodName 方法名
     * @return null
     * @throws
     */
    public function checkPermissionsForController($controller, $methodName)
    {
        $globalSetting = ['login' => false];

        $reflectionObj = new \ReflectionObject($controller);
        if ($reflectionObj instanceof \ReflectionObject) {
            $ann = new Annotation($reflectionObj);
            $globalSetting['login'] = $globalSetting['login'] || $ann->has('login');

            $globalSetting['role'] = $ann->getArray('role');
            $globalSetting['permission'] = $ann->getArray('permission');
            $globalSetting['aclmsg'] = $ann->getString('aclmsg', 'You have no access to this resource!');

            $reflectionMethod = new \ReflectionMethod($controller, $methodName);
            $annotation = new Annotation($reflectionMethod);
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
                throw new UnLoginException();
            }

            //权限检查
            if ($permission && Auth::user()->hasAnyPermissions($permission) == false) {
                throw new UnPermissionException($permission);
            }
            //角色检查
            if ($role && Auth::user()->hasAnyRoles($role) == false) {
                throw new UnRolesException($role);
            }

        }
    }

    /**
     * 手动清空所有缓存
     */
    public function forgetCachedPermissions()
    {
        parent::forgetCachedPermissions();
    }


}