<?php
namespace app\common\model;

use think\Model;

/**
 * Created by PhpStorm.
 * User: PHILL
 * Date: 2016/10/28
 * Time: 9:13
 */
class OrderDetail extends Model
{
    /**
     * 订单
     */
    protected function initialize()
    {
        //需要调用 mdoel 的 initialize 方法
        parent::initialize();
    }

    /** 新增订单详情
     * @param array $data
     * @return bool
     */
    public function add(array $data)
    {
        if (!isset($data['order_id'])) {
            return false;
        }
        time_partition(__CLASS__, $data['create_time']);
        $this->allowField(true)->isUpdate(false)->save($data);
    }

    public function getDetails($orderIDS)
    {
        $this->where('order_id','in', $orderIDS);
        return $this;
    }

    public function getPackageIdAttr($attr)
    {
        return $attr."";
    }

    /** 详情id转字符串
     * @param $value
     * @return string
     */
    public function getIdAttr($value)
    {
        if(is_numeric($value)){
            $value = $value.'';
        }
        return $value;
    }
}