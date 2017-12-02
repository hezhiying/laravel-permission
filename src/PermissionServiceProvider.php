<?php

namespace ZineAdmin\Permission;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use ZineAdmin\Permission\Commands\CreatePermission;
use ZineAdmin\Permission\Commands\CreateRole;
use ZineAdmin\Permission\Contracts\PermissionContract;
use ZineAdmin\Permission\Contracts\RoleContract;
use ZineAdmin\Permission\Middleware\PermissionMiddleware;
use ZineAdmin\Permission\Middleware\RoleMiddleware;

class PermissionServiceProvider extends ServiceProvider
{

    public $permissionManage;

    /**
     * Bootstrap the application services.
     *
     * @param Router $router
     * @return void
     */
    public function boot(Router $router)
    {
        $this->registerConfig();
        $this->registerMiddleware($router);
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        $this->registerModelBindings();
        $this->registerCommand();
        $this->registerGatePermission();
        $this->registerBladeExtensions();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/permission.php', 'permission'
        );
        $this->app->singleton(PermissionManage::class, function () {
            return new PermissionManage();
        });
    }

    /**
     * Register config.
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/Config/permission.php' => config_path('permission.php'),
        ], 'permission_config');

    }

    /**
     * register Middleware
     * @param Router $router
     */
    protected function registerMiddleware(Router $router)
    {
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);

    }

    /**
     * register Command
     */
    protected function registerCommand()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateRole::class,
                CreatePermission::class
            ]);
        }
    }

    protected function registerModelBindings()
    {
        $this->app->bind(PermissionContract::class, config('permission.models.permission'));
        $this->app->bind(RoleContract::class, config('permission.models.role'));
    }

    /**
     * Gate授权检查截获
     */
    protected function registerGatePermission()
    {
        Gate::before(function (Authenticatable $user, string $ability) {
            if (method_exists($user, 'hasSuperAdmin') && $user->hasSuperAdmin()) {
                return true;
            }
            if (method_exists($user, 'hasAnyPermissions')) {
                return $user->hasAnyPermissions($ability) ?: null;
            }
        });
    }


    protected function registerBladeExtensions()
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            //注册role指令
            $bladeCompiler->directive('role', function ($arguments) {
                list($roles, $guard) = explode(',', $arguments.',');
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasAnyRoles({$roles})): ?>";
            });
            $bladeCompiler->directive('endrole', function () {
                return '<?php endif; ?>';
            });

            //注册hasanyroles指令
            $bladeCompiler->directive('hasanyroles', function ($arguments) {
                list($roles, $guard) = explode(',', $arguments.',');
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasAnyRoles({$roles})): ?>";
            });
            $bladeCompiler->directive('endhasanyroles', function () {
                return '<?php endif; ?>';
            });

            //注册hasallroles指令
            $bladeCompiler->directive('hasallroles', function ($arguments) {
                list($roles, $guard) = explode(',', $arguments.',');

                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasAllRoles({$roles})): ?>";
            });
            $bladeCompiler->directive('endhasallroles', function () {
                return '<?php endif; ?>';
            });
        });
    }
}
