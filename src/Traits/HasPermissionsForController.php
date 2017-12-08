<?php

namespace ZineAdmin\Permission\Traits;

use ZineAdmin\Permission\PermissionManage;

trait HasPermissionsForController
{
    public function callAction($method, $parameters)
    {
        app(PermissionManage::class)->checkPermissionsForController($this, $method);
        return call_user_func_array([$this, $method], $parameters);
    }

}