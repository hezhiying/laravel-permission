<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/12/8
 * Time: 上午9:47
 */

namespace ZineAdmin\Permission\Exceptions;


use Illuminate\Auth\AuthenticationException;

class UnLoginException extends AuthenticationException implements PermissionException
{

}