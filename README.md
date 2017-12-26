# 用户角色权限包

* [Installation](#installation)
* [Usage](#usage)
    * [创建角色](#创建角色)
    * [注册权限](#注册权限)
    * [为角色授予权限或禁止权限](#为角色授予权限或禁止权限)
    * [用户分配角色](#用户分配角色)
* [角色和权限检查](#角色和权限检查)
    * [检查角色](#检查角色)
    * [检查权限](#检查权限)
    * [Blade指令](#blade指令)
    * [Middleware](#middleware)
    * [Controller 注释](#controller)
* [捕获角色和权限检查错误异常](#捕获角色和权限检查错误异常)
* [cache](#cache)
* [角色表生成树](#role-tree)

写在前面


> 验证方式：本包的权限验证思路有别于其它权限包，权限格式以 `操作:资源父级/资源下级` 操作资源格式，下级权限默认是继承上线资源的权限，例如：

```php
$role = Role::whereName('editor')->first();
//授予
$role->givePermissionToAllowed([
    '*:dashboard',
    'view:dashboard/roles'
])

//禁止
$role->givePermissionToDeny([
    'update:dashboard/users',
    '*:dashboard/roles'
])
```

上面的意思表示角色 `editor` 拥有`dashboard`资源下的的所有权限，但是不能更新用户操作`update:dashboard/users` 和 对角色资源的操作`*:dashboard/roles` 但是可以拥有查看角色的权限`view:dashboard/roles`, 如以下代码：

```php
$user = User::first();
//对用户分配角色
$user->assignRole('editor');

$user->hasPermission('*:dashboard'); //true
$user->hasPermission('view:dashboard/users'); //true
$user->hasPermission('update:dashboard/users'); //false
$user->hasPermission('*:dashboard/logs'); //true
$user->hasPermission('*:dashboard/roles'); //false
$user->hasPermission('create:dashboard/roles'); //false
$user->hasPermission('update:dashboard/roles'); //false
$user->hasPermission('view:dashboard/roles'); //true

```

> 管理添加系统中的所有权限资源
本包没有提供`permissions`表以存储所有可分配的权限表，而是以注册的形式，假如我们开发一个会员模块，可以在某个`ServiceProvider`中注册，如：

```php
use Illuminate\Support\ServiceProvider;
use ZineAdmin\Permission\PermissionManage;
class AppServiceProvider extends ServiceProvider
{
    public function boot(PermissionManage $permissionManage)
    {
        $permissionManage->registerPermissions($this->registerPermissionsMain());
    }
    
    protected function registerPermissionsMain(): array
    {
        return [
            'dashboard/users' => '用户管理',
            'create:dashboard/users' => '创建用户',
            'view:dashboard/users' => '显示用户',
            'delete:dashboard/users' => '删除用户',
            'update:dashboard/users' => '更新用户',
        ];
    }
    //code...
```

权限注册后，可以通过以下方式把系统中所有地方注册过的资源显示出来，然后可以授权给某个角色或用户。
通过这种方法，你可以很方便的为用户角色进行授权。
```php
use ZineAdmin\Permission\PermissionManage;

class HomeController
{
    public function index()
    {
        $permissionManage = app(PermissionManage::class);
        $resources = $permissionManage->getFlattenResource();
        return view('perm', compact('resources'));
    }
```

perm view:

```blade
//每个资源有如下属性 res_id  parent name level
<table>
    @foreach($resources as $re)
        <tr parent_id="{{$re['parent']}}" id="{{$re['res_id']}}">
            <td>{{str_repeat('&nbsp;&nbsp;',$re['level'])}} {{$re['res_id']}} ({{$re['name']}})</td>
            <td>
                <input type="radio" name="{{$re['level']}} value="允许"> 允许
                <input type="radio" name="{{$re['level']}} value="继承"> 继承
                <input type="radio" name="{{$re['level']}} value="禁止"> 禁止
            </td>
        </tr>
    @endforeach
</table>
```


一旦安装你就可以这样使用：
```php
// 分配角色给用户
$user->assignRole('writer');

//角色授予权限
$role->givePermissionToAllowed('edit:dashboard/articles');
//角色禁止权限
$role->givePermissionToDeny('edit:dashboard/articles');
//角色撤销权限
$role->removePermission('edit:dashboard/articles');
```

您可以使用Laravel的默认功能测试用户是否具有权限：

```php
$user->can('*:dashboard/users')
```

## Installation

This package can be used in Laravel 5.4 or higher. 

You can install the package via composer:

``` bash
composer require zine-admin/permission
```

您可以通过以下方式发布迁移(migration)：

```bash
php artisan vendor:publish --provider="ZineAdmin\Permission\PermissionServiceProvider" --tag="migrations"
```

迁移发布后，您可以通过运行迁移来创建角色和权限表:

```bash
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="ZineAdmin\Permission\PermissionServiceProvider" --tag="config"
```

## Usage

First, add the `ZineAdmin\Permission\Traits\HasRoles` trait to your `User` model(s):

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use ZineAdmin\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // ...
}
```
### 创建角色
Using model create

```php
use ZineAdmin\Permission\Models\Role;
// Create a superadmin role for the admin user
$role = Role::create(['name'=>'superadmin', 'desc'=>'superadmin']);

```

Using artisan command

```php
php artisan permission:create-role role-name role-desc
```

### 注册权限

本包没有permissions表，系统中的权限可以在boot中注册。

```php
use Illuminate\Support\ServiceProvider;
use ZineAdmin\Permission\PermissionManage;
class AppServiceProvider extends ServiceProvider
{
    public function boot(PermissionManage $permissionManage)
    {
        $permissionManage->registerPermissions($this->registerPermissionsMain());
    }
    
    protected function registerPermissionsMain(): array
    {
        return [
            'dashboard/users' => '用户管理',
            'create:dashboard/users' => '创建用户',
            'view:dashboard/users' => '显示用户',
            'delete:dashboard/users' => '删除用户',
            'update:dashboard/users' => '更新用户',
        ];
    }
```

注册的权限资源会自动生成tree对象，结构如下：
```
"id" => "0",
"name" => "/",
"res_id" => "/",
"desc" => "",
"operations" => [],
"child" => [
    [
        "name" => "dashboard",
         "res_id" => "*:dashboard",
         "operations" => [],
         "child" => [
             "name" => "users",
             "res_id" => "*:dashboard/users",
             "operations" => [
                "create"=>[
                    "name"=>"创建用户",
                    "res_id"=>"create:dashboard/users",
                ],
                "view"=>[
                    "name"=>"显示用户",
                        "res_id"=>"view:dashboard/users",
                ]
             ],
         ]
    ]
]


```

```php
use ZineAdmin\Permission\PermissionManage;
$permissionManage = app(PermissionManage::class);

//获得所有权限资源
$resource = $permissionManage->getResource()

//将tree结构的权限资源转换成一维数组,方便前台以表格显示
$resource = $permissionManage->getFlattenResource()

//检查是否存在指定的资源
$permissionManage->checkPermissionExists('add:dashboard/users')
```
获得权限资源一维数组的结构

```php
//Collection
[[
    "id" => 1,
    "parent" => 0,
    "is_leaf" => 0,
    "res_id" => "*:dashboard",
    "name" => "dashboard",
    "level" => 1
  ],
...
]   
```

### 为角色授予权限或禁止权限

```php
use ZineAdmin\Permission\Models\Role;
$role = Role::first();

///角色允许权限
$role->givePermissionToAllowed(...$permission)

//角色禁止权限
$role->givePermissionToDeny(...$permission)

//删除角色上面的权限
$role->removePermissionTo(...$permission)
```
### 用户分配角色

角色可以分配给任何用户

First, add the `ZineAdmin\Permission\Traits\HasRoles` trait to your `User` model(s):

角色可以分配给任何用户：

```php
$user->assignRoles('writer');

// You can also assign multiple roles at once
$user->assignRoles('writer', 'admin');
// or as an assignRoles
$user->assignRole(['writer', 'admin']);
```

角色也可以同步:

```php
$user->syncRoles(['writer', 'admin']);
$user->syncRoles('writer', 'admin');
```

从用户身上移除角色,返回成功删除的数量

```php
$user->removeRoles('writer');
$user->removeRoles(['writer', 'admin']);
$user->removeRoles('writer', 'admin');
```

## 角色和权限检查

### 检查角色

确定用户是否有指定角色
```php
$user->hasRole('admin')
```
or

```php
$user->hasAnyRoles('admin')
$user->hasAnyRoles(['writer','admin'])
$user->hasAnyRoles(Role::all());
```

您还可以确定用户是否具有所有给定的角色列表：

```php
$user->hasAllRoles(['writer','admin'])
$user->hasAllRoles(Role::all());
```

### 检查权限

确定角色是否具有某种权限：

```php
$user->hasAnyPermissions('view:dashboard/users')
$user->hasAnyPermissions(['view:dashboard/users', 'update:dashboard/users'])
```

确定用户是否具有给定的全部权限

```php
$user->hasAllPermissions(['view:dashboard/users', 'update:dashboard/users'])
```

### Blade指令

#### Blade and Roles

测试一个特定的角色:

```php
@role('writer')
    I am a writer!
@else
    I am not a writer...
@endrole
```
is the same as
```php
@hasanyroles('writer')
    I am a writer!
@else
    I am not a writer...
@endhasanyroles
```
确定是否有列表中任一角色
```php
@hasanyrole('writer|admin')
    I am either a writer or an admin or both!
@else
    I have none of these roles...
@endhasanyrole
```
确定是否有列表中所有角色

```php
@hasallroles('writer|admin')
    I am both a writer and an admin!
@else
    I do not have all of these roles...
@endhasallroles
```
#### Blade and Permissions
使用Laravel的本地@can指令来检查用户是否具有某个权限。

```php
@can('edit:dashboard/articles')
  //
@endcan
```
or
```php
@if(auth()->user()->can('edit:dashboard/articles') && $some_other_condition)
  //
@endif
```

### middleware

Using a middleware

已注册如下中间件:

```php
protected $routeMiddleware = [
    // ...
    'role' => \ZineAdmin\Permission\Middleware\RoleMiddleware::class,
    'permission' => \ZineAdmin\Permission\Middleware\PermissionMiddleware::class,
];
```

你可以使用中间件规则来保护你的路由： 

```php
Route::group(['middleware' => ['role:super-admin']], function () {
    //
});

Route::group(['middleware' => ['permission:*:dashboard/users create:dashboard/roles']], function () {
    //
});

Route::group(['middleware' => ['role:super-admin','permission:*:dashboard/users create:dashboard/roles']], function () {
    //
});
```

或者，您可以用`|`（管道）字符分隔多个角色或权限：
```php
Route::group(['middleware' => ['role:super-admin|writer']], function () {
    //
});

Route::group(['middleware' => ['permission:publish articles|edit:dashboard/articles']], function () {
    //
});
```

您可以通过在构造函数中设置所需的中间件来类似地保护您的控制器

```php
public function __construct()
{
    $this->middleware(['role:super-admin','permission:publish articles|edit:dashboard/articles']);
}
```

### controller
利用控制器方法上的注释来检查权限和角色

首先在控制器添加`HasPermissionsForController` Trait

First, add the `ZineAdmin\Permission\Traits\HasPermissionsForController` trait to your `Controller` Controller:

```php
use ZineAdmin\Permission\Traits\HasPermissionsForController;

class HomeController extends Controller
{
    use HasPermissionsForController;

    /**
     * @role admin
     * @permission view:dashboard/roles create:dashboard/roles
     */
    public function view()
    {
        // Code here ...
    }
}
```

### Catching role and permission failures
If you want to override the default `403` response, you can catch the `UnauthorizedException` using your app's exception handler:

```php
public function render($request, Exception $exception)
{
    if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
        // Code here ...
    }

    return parent::render($request, $exception);
}

```

## cache
缓存角色和权限数据以加速性能。

当您使用提供的方法来处理角色和权限时，缓存会自动为您重置：

```php
$user->assignRoles('writer');
$user->removeRoles('writer');
$user->syncRoles(params);
$role->givePermissionToAllowed('edit:dashboard/articles');
$role->givePermissionToDeny('edit:dashboard/articles');
$role->removePermission('edit:dashboard/articles');
```

### 手动清除所有缓存

```php
use ZineAdmin\Permission\PermissionManage;
app(PermissionManage::class)->forgetCachedPermissions()
```

## role-tree

角色表支持无限树结构，更详细请访问[Documentation](https://github.com/lazychaser/laravel-nestedset)

添加一个子节点：

```php
// #1
$node->prependToNode($parent)->save();

// #2
$parent->prependNode($node);
```

祖先和后代

```php
// 访问祖先
$node->ancestors;

// 访问后代
$node->descendants;
```
根据ID查询祖先和后代

```php
//查找祖先
$result = Role::ancestorsOf($id);
//查找祖先和自己（包含节点深度）
$result = Role::withDepth()->ancestorsAndSelf($id);
$result = Role::descendantsOf($id);
$result = Role::descendantsAndSelf($id);
```

转化为树形结构

```php
$tree = Role::get()->toTree();
```

转化为扁平树

```php
$nodes = Role::withDepth()->get()->toFlatTree();
```
获取指定节点的子树

```php
$root = Role::descendantsAndSelf($rootId)->toTree()->first();
$root = Role::withDepth()->descendantsOf($rootId)->toTree()->first();
```
删除节点：
>重要！该节点的任何后代也将被删除！
```php
$role->delete();
```
不能使用查询删除
>这将破坏树结构
```php
Role::where('id', '=', $id)->delete();
```


你可以检查树是否被破坏（即有一些结构错误）：

```php
$bool = Role::isBroken(); //true or false
//错误统计信息
$data = Role::countErrors();
```

修复树

```php
Role::fixTree();
```
