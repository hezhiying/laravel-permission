<?php

namespace ZineAdmin\Permission\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use ZineAdmin\Permission\PermissionServiceProvider;

class InstallCommand extends Command
{
    protected $seedersPath = __DIR__.'/../../database/seeds/';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'permission:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Zine RolePermission package';

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" '.getcwd().'/composer.phar';
        }

        return 'composer';
    }

    public function handle(Filesystem $filesystem)
    {
        $this->info('Step 1: Publishing the Permission database, and config files');
        $this->call('vendor:publish', ['--provider' => PermissionServiceProvider::class]);

        $this->info('Step 2: Migrating the database tables into your application');
        $this->call('migrate');

        $this->info('Step 3: Dumping the autoloaded files and reloading all new files');
        $composer = $this->findComposer();

        $process = new Process($composer.' dump-autoload');
        $process->setTimeout(null); //Setting timeout to null to prevent installation from stopping at a certain point in time
        $process->setWorkingDirectory(base_path())->run();

        $this->info('Step 4: Seeding data into the database');
        $this->seed('RolesTableSeeder');

        $this->info('Step 5: Attempting to add `use HasRoles` Trait and `hasSuperAdmin` method to App\User');
        if (file_exists(app_path('User.php'))) {
            $str = file_get_contents(app_path('User.php'));

            if ($str !== false) {
                if(false === strpos($str, 'use HasRoles;')){
                    $str = str_replace(
                        'use Illuminate\Foundation\Auth\User as Authenticatable;',
                        "use Illuminate\Foundation\Auth\User as Authenticatable; \nuse ZineAdmin\Permission\Traits\HasRoles;",
                        $str);
                    $str = str_replace(
                        'use Notifiable;',
                        "use Notifiable; \n    use HasRoles;",
                        $str);
                }
                if (false === strpos($str, 'public function hasSuperAdmin')) {
                    $str = str_replace_last('}', "\n    /** \n     * 确定用户是否具有超级管理员身份 \n     * \n     * @return bool\n     */ \n    public function hasSuperAdmin()\n    {\n        return \$this->id === 1;\n    }\n}", $str);
                }
                file_put_contents(app_path('User.php'), $str);
            }
        } else {
            $this->warn('Unable to locate "app/User.php".  Did you move this file?');
            $this->warn('You will need to update this manually.  add "use ZineAdmin\Permission\Traits\HasRoles;" and "use HasRoles;" in your User model');
        }
        $this->info('Successfully installed ZineAdmin/Permission! Enjoy');
        $this->info('安装成功，太棒了');

    }

    public function seed($class)
    {

        if (!class_exists($class)) {
            require_once $this->seedersPath.$class.'.php';
        }

        with(new $class())->run();
    }
}
