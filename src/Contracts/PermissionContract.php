<?php

namespace ZineAdmin\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

Interface PermissionContract
{
    /**
     * 权限对应于角色。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function roles(): BelongsTo;
}
