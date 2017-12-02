<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/11/29
 * Time: 下午4:00
 */

namespace ZineAdmin\Permission\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ZineAdmin\Permission\Contracts\PermissionContract;
use ZineAdmin\Permission\Traits\RefreshCache;

class Permission extends Model implements PermissionContract
{
    use RefreshCache;

    public $guarded = ['id'];

    protected $table = 'role_has_permissions';

    /**
     * 权限对应的角色关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function roles():BelongsTo {
        return $this->belongsTo(config('permission.models.role'), 'role_id', 'id');
    }

    public function save(array $options = [])
    {
        // before save code
        parent::save();
        // after save code
        $this->forgetCachedPermissionsForRole($this->attributes['role_id']);

    }

    public function delete()
    {
        // before save code
        parent::delete();
        // after save code
        $this->forgetCachedPermissionsForRole($this->attributes['role_id']);

    }

}