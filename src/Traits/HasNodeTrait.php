<?php
/**
 * Created by PhpStorm.
 * User: zine
 * Date: 2017/12/26
 * Time: 上午10:11
 */

namespace ZineAdmin\Permission\Traits;

use Kalnoy\Nestedset\NodeTrait;

trait HasNodeTrait
{
    use NodeTrait;

    public function getLftName()
    {
        return 'lft';
    }

    public function getRgtName()
    {
        return 'rgt';
    }

    public function getParentIdName()
    {
        return 'parent_id';
    }
}