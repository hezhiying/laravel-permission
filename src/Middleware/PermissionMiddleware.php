<?php

namespace ZineAdmin\Permission\Middleware;

use Closure;
use ZineAdmin\Permission\Exceptions\UnLoginException;
use ZineAdmin\Permission\Exceptions\UnPermissionException;
use ZineAdmin\Permission\PermissionManage;

class PermissionMiddleware
{

    /**
     * The Guard implementation.
     *
     * @var \Illuminate\Contracts\Auth\Guard
     */
    protected $auth;

    /**
     * @var \ZineAdmin\Permission\Traits\HasRoles
     */
    protected $user;

    /**
     * The route instance.
     *
     * @var \Illuminate\Routing\Route
     */
    public $route;

    public function __construct() {
        $this->auth  = app('auth');
        $this->user  = app('auth')->user();
        $this->route = app('router')->current();
    }

    /**
     * Run the request filter.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param String $permissions
     * @return mixed
     */
    public function handle($request, Closure $next, $permissions = null) {
        //如果超级用户直接跳过检查
        if($this->auth->check() && $this->user->hasSuperAdmin()){
            return $next($request);
        }
        if($permissions){
            throw_if($this->auth->guest(), UnLoginException::class);

            $permissions = collect(explode(" ", $permissions))->filter()->toArray();
            throw_unless(
                $this->user->hasAnyPermissions($permissions),
                new UnPermissionException($permissions)
            );
        }

        //检查控制器的注释中是否有权限
        if ($this->route->getActionName() !== 'Closure') {
            app(PermissionManage::class)->checkPermissionsForController($this->route->getController(), $this->route->getActionMethod());
        }

        return $next($request);
    }

}
