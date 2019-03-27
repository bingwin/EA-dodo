<?php
namespace app\common\cache\driver;
use app\common\cache\Cache;
use think\Db;

class Area extends Cache
{
    /**
     * 获取省市区
     * @return $trees: 省市区列表
     */
    public function getArea()
    {
        if ($this->redis->exists('cache:Area')) {
            $result = json_decode($this->redis->get('cache:Area'),true);
            return $result ? : [];
        }
        $trees = [];
        $result = Db::name('area')->select();
        if ($result) {
            $trees = list_to_tree($result);
            $this->redis->set('cache:Area', json_encode($trees));
        }
        return $trees;
    }
    
}