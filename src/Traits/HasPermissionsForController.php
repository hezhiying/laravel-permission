<?php

namespace ZineAdmin\Permission\Traits;

use Illuminate\Support\Facades\Auth;
use ZineAdmin\Permission\Annotation;
use ZineAdmin\Permission\Exceptions\UnauthorizedException;
use ZineAdmin\Permission\PermissionManage;

trait HasPermissionsForController
{
    public function callAction($method, $parameters)
    {
        app(PermissionManage::class)->checkPermissionsForController($this, $method);
        return call_user_func_array([$this, $method], $parameters);
    }

}