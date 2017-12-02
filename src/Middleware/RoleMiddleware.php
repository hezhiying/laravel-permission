<?php

namespace ZineAdmin\Permission\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use ZineAdmin\Permission\Exceptions\UnauthorizedException;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param String $role
     * @return mixed
     */
    public function handle($request, Closure $next, String $role)
    {
        throw_if(Auth::guest(), UnauthorizedException::notLoggedIn());
        if(method_exists(Auth::user(), 'hasSuperAdmin') && Auth::user()->hasSuperAdmin()){
            return $next($request);
        }
        $role = collect(explode(" ", $role))->filter()->toArray();
        if(method_exists(Auth::user(), 'hasAnyRoles') && Auth::user()->hasAnyRoles($role)){
            return $next($request);
        }
        throw UnauthorizedException::forRoles($role);
    }
}
