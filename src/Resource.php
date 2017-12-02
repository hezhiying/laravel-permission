<?php

namespace ZineAdmin\Permission;

use Illuminate\Support\Collection;

class Resource extends Collection
{


    protected $items = [
        'id' => '',
        'uri' => '',
        'name' => '',
        'res_id' => '',
        'desc' => '',
        'operations' => [],
        'child' => [],
        'level' => 0,
        'pos' => 9999
    ];

    /**
     * @var Resource
     */
    public $root;

    /**
     * @var Resource
     */
    public $parent;

    /**
     * Resource constructor.
     *
     * @param array|mixed $id
     * @param null|self $root
     * @param null|self $parent
     */
    public function __construct($id = '/', $root = null, $parent = null)
    {
        parent::__construct($this->items);
        $this->items['id'] = $id;
        $this->items['uri'] = $id;
        $this->items['name'] = $id;
        $this->items['operations'] = new Collection();
        $this->items['child'] = new Collection();

        $this->root = $root ?: $this;
        $this->parent = $parent ?: $this;

        if ($this->parent->id !== '/') {
            $this->items['uri'] = $this->parent->uri . '/' . $id;
        }

        if ($root) {
            $this->items['res_id'] = '*:' . $this->uri;
            $this->items['level'] = $this->parent->level + 1;
        }
    }

    /**
     * 自动创建资源，路径以/分隔传递一个路径会依次创建
     * eg:create('a/b/c/d')
     * @param string $id
     * @param null $builder
     *
     * @return self
     */
    public function create($id = '', $builder = null)
    {
        if (empty($id) || $id == '/') {
            return $this;
        } else {
            $ids = explode("/", $id);
            $node = $this;
            foreach ($ids as $id) {
                $node = $node->findChildItem($id);
            }
        }
        if ($builder instanceof \Closure) {
            call_user_func($builder, $node);
        } elseif (is_array($builder)) {
            unset($builder['id'], $builder['child']);
            foreach ($builder as $key => $val) {
                $node->$key = $val;
            }
        } elseif (!empty(trim($builder))) {
            $node->name = $builder;
        }

        return $node;
    }

    /**
     * 添加操作
     *
     * @param     $op
     * @param     $name
     * @param     $desc
     * @param int $pos
     */
    public function addOperation($op, $name, $desc = '', $pos = 9999)
    {
        $this->operations [$op] = new Collection(array(
            'uri' => $this->uri,
            'name' => $name,
            'desc' => $desc,
            'res_id' => $op . ':' . $this->uri,
            'pos' => $pos
        ));

    }

    /**
     * 查找并返回，不存在返回null
     *
     * @param string $id 菜单ID
     * @param string $sortField 需要排序的字段（默认顺序排列）
     * @param boolean $descending 排序方向
     *
     * @return $this||null
     */
    public function find($id = '', $sortField = '', $descending = false)
    {
        $resource = $this->create($id);
        if ($sortField) {
            $resource->sortBy($sortField, SORT_REGULAR, $descending);
        }

        return $resource;
    }

    /**
     * 查找返回子集合
     *
     * @param string $id 菜单ID
     * @param string $sortField 需要排序的字段（默认顺序排列）
     * @param boolean $descending 排序方向
     *
     * @return \Illuminate\Support\Collection
     */
    public function findGetChild($id = '', $sortField = '', $descending = false)
    {
        return $this->find($id, $sortField, $descending)->getChilds();
    }

    /**
     * 返回当前下级资源
     * @return \Illuminate\Support\Collection
     */
    public function getChilds()
    {
        return $this->child ?: new Collection();
    }

    /**
     * 获取单个资源，不存在则先创建
     *
     * @param string $id
     *
     * @return self
     */
    private function findChildItem($id)
    {
        foreach ($this->child ?: [] as $resource) {
            if ($resource->id == $id) {
                return $resource;
            }
        }

        if (!$this->child) {
            $this->child = new Collection();
        }

        return $this->child->push(new Resource($id, $this->root, $this))->last();
    }

    function __set($name, $value)
    {
        $this->items[$name] = $value;
    }

    function __get($name)
    {
        return isset($this->items[$name]) ? $this->items[$name] : null;
    }

    /**
     * Sort the collection using the given callback.
     * 使用指定方法排序
     *
     * @param  string $callback
     * @param  int $options
     * @param  bool $descending
     *
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        if ($this->child instanceof Collection) {
            $this->child = $this->child->sortBy($callback, $options, $descending)->values();
        }
        foreach ($this->child ?: [] as $resource) {
            $resource->sortBy($callback, $options, $descending);
        }

        return $this;
    }

    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * 检查res_id是否存在
     * @param $val
     * @param null $res
     * @return bool
     */
    public function checkPermissionExists($val, $res = null)
    {
        if ($res) {
            foreach ($res as $resource) {
                if ($resource->checkPermissionExists($val)) {
                    return true;
                }
            }
            return false;
        } elseif ($this->res_id == $val) {
            return true;
        } elseif ($this->child->isNotEmpty()) {
            return $this->checkPermissionExists($val, $this->child);
        } elseif ($this->operations->contains('res_id', $val)) {
            return true;
        }
        return false;
    }

    /**
     * 将资源弄成一维数组
     * @param array $array
     * @return Collection
     */
    public function resFlatten($array = [])
    {
        $arr = collect();
        if (!$array && $this->getChilds()->isNotEmpty()) {
            return $this->resFlatten($this->getChilds());
        }
        foreach ($array as $item) {
            $arr->push(['res_id' => $item['res_id'], 'parent' => $item->parent->res_id, 'name' => $item['name'], 'level' => $item['level']]);

            if ($item['child']) {
                $children = $this->resFlatten($item['child']);
                foreach ($children as $v) {
                    $arr->push($v);
                }
            }
            if ($item['operations']) {
                foreach ($item['operations'] as $v) {
                    $arr->push(['res_id' => $v['res_id'], 'parent' => $item->res_id, 'name' => $v['name'], 'level' => $item['level'] + 1]);
                }
            }
        }
        return $arr;
    }
}