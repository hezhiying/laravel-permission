<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/12/8
 * Time: 上午9:54
 */

namespace ZineAdmin\Permission\Exceptions;


use Illuminate\Auth\Access\AuthorizationException;

class UnRolesException extends AuthorizationException implements PermissionException
{

    /**
     * @param string|array $roles
     * UnRolesException constructor.
     */
    public function __construct($roles)
    {
        $roles = is_array($roles) ? implode(",", $roles) : $roles;
        $this->message = 'User does not have the right roles['.$roles.'].';
    }
}