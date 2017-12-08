<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/12/8
 * Time: 上午9:54
 */

namespace ZineAdmin\Permission\Exceptions;


use Illuminate\Auth\Access\AuthorizationException;

class UnPermissionException extends AuthorizationException implements PermissionException
{

    /**
     * @param string|array $permissions
     * UnPermissionException constructor.
     */
    public function __construct($permissions)
    {
        $permissions = is_array($permissions) ? implode(",", $permissions) : $permissions;
        $this->message = 'User does not have the right permissions['.$permissions.'].';
    }
}