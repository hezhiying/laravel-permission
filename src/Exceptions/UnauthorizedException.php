<?php

namespace ZineAdmin\Permission\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UnauthorizedException extends HttpException
{
    /**
     * @param string|array $roles
     * @return UnauthorizedException
     */
    public static function forRoles($roles): self
    {
        $roles = is_array($roles) ? implode(",", $roles) : $roles;
        return new static(403, 'User does not have the right roles['.$roles.'].', null, []);
    }

    /**
     * @param string|array $permissions
     * @return UnauthorizedException
     */
    public static function forPermissions($permissions): self
    {
        $permissions = is_array($permissions) ? implode(",", $permissions) : $permissions;
        return new static(403, 'User does not have the right permissions['.$permissions.'].', null, []);
    }

    public static function notLoggedIn(): self
    {
        return new static(403, 'User is not logged in.', null, []);
    }
}
