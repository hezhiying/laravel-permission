<?php

namespace ZineAdmin\Permission;

use Illuminate\Support\Collection;

class Resource extends Collection
{


    protected $items = [
        'id' => 0,
        'uri' => '',
        'node' => '',
        'name' => '',
        'res_id' => '',
        'desc' => '',
        'level' => 0,
        'pos' => 9999,
        'operations' => [],
        'child' => [],
    ];

    /**
     * @var Resource
     */
    public $root;

    /**
     * @var Resource
     */
    public $parent;

    public $nodeNum = 0;

    /**
     * Resource constructor.
     *
     * @param array|mixed $node
     * @param null|self $root
     * @param null|self $parent
     */
    public function __construct($node = '/', $root = null, $parent = null)
    {
        parent::__construct($this->items);
        $this->items['node'] = $node;
        $this->items['uri'] = $node;
        $this->items['name'] = $node;
        $this->items['operations'] = new Collection();
        $this->items['child'] = new Collection();

        $this->root = $root ?: $this;
        $this->parent = $parent ?: $this;

        if ($this->parent->node !== '/') {
            $this->items['uri'] = $this->parent->uri . '/' . $node;
        }

        if ($root) {
            $this->items['id'] = ++$root->nodeNum;
            $this->items['res_id'] = '*:' . $this->uri;
            $this->items['level'] = $this->parent->level + 1;
        }

    }

    /**
     * 自动创建资源，路径以/分隔传递一个路径会依次创建
     * eg:create('a/b/c/d')
     * @param string $node
     * @param null $builder
     *
     * @return self
     */
    public function create($node = '', $builder = null)
    {
        if (empty($node) || $node == '/') {
            return $this;
        } else {
            $nodes = explode("/", $node);
            $resource = $this;
            foreach ($nodes as $node) {
                $resource = $resource->findChildItem($node);
            }
        }
        if ($builder instanceof \Closure) {
            call_user_func($builder, $resource);
        } elseif (is_array($builder)) {
            unset($builder['node'], $builder['child']);
            foreach ($builder as $key => $val) {
                $resource->$key = $val;
            }
        } elseif (!empty(trim($builder))) {
            $resource->name = $builder;
        }

        return $resource;
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
            'id' => ++$this->root->nodeNum,
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
     * @param string $node 菜单ID
     * @param string $sortField 需要排序的字段（默认顺序排列）
     * @param boolean $descending 排序方向
     *
     * @return $this||null
     */
    public function find($node = '', $sortField = '', $descending = false)
    {
        $resource = $this->create($node);
        if ($sortField) {
            $resource->sortBy($sortField, SORT_REGULAR, $descending);
        }

        return $resource;
    }

    /**
     * 查找返回子集合
     *
     * @param string $node 菜单ID
     * @param string $sortField 需要排序的字段（默认顺序排列）
     * @param boolean $descending 排序方向
     *
     * @return \Illuminate\Support\Collection
     */
    public function findGetChild($node = '', $sortField = '', $descending = false)
    {
        return $this->find($node, $sortField, $descending)->getChilds();
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
     * @param string $node
     *
     * @return self
     */
    private function findChildItem($node)
    {
        foreach ($this->child ?: [] as $resource) {
            if ($resource->node == $node) {
                return $resource;
            }
        }

        if (!$this->child) {
            $this->child = new Collection();
        }

        return $this->child->push(new Resource($node, $this->root, $this))->last();
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
            $arr->push(['id'=>$item->id, 'parent' => $item->parent->id, 'is_leaf'=>0, 'res_id' => $item['res_id'], 'name' => $item['name'], 'level' => $item['level']]);

            if ($item['child']) {
                $children = $this->resFlatten($item['child']);
                foreach ($children as $v) {
                    $arr->push($v);
                }
            }
            if ($item['operations']) {
                foreach ($item['operations'] as $v) {
                    $arr->push(['id'=>$v['id'], 'parent' => $item->id, 'is_leaf'=>1, 'res_id' => $v['res_id'], 'name' => $v['name'], 'level' => $item['level'] + 1]);
                }
            }
        }
        return $arr;
    }
}