<?php
namespace app\common\model\amazon;

use app\common\model\PurchaseRuleItem;
use think\Model;
use think\Db;
use app\common\cache\Cache;
use think\Exception;
use erp\ErpModel;
use app\common\traits\ModelFilter;
use think\db\Query;
use app\order\service\AmazonOrderService;

class AmazonOrder extends ErpModel
{
    use ModelFilter;
    
    public function scopeOrder(Query $query, $params)
    {
        if (!empty($params)) {
            $query->where('__TABLE__.account_id', 'in', $params);
        }
    }

    /**
     * 初始化
     * @return [type] [description]
     */
    protected function initialize()
    {
        //需要调用 mdoel 的 initialize 方法
        parent::initialize();
        $this->query('set names utf8mb4');
    }

    /**
     * 关系
     * @return [type] [description]
     */
    public function role()
    {
        //一对一的关系，一个订单对应一个商品
        return $this->belongsTo('WishPlatformOnlineGoods');
    }

    /**
     * 新增订单
     * @param array $data [description]
     */
    public function add(array $data)
    {  
        if (empty($data)) {
            return false;
        }

        $masterTable = "amazon_order";
        $detailTable = "amazon_order_detail";
        $partitionCache = Cache::store('Partition');
        foreach ($data as $order) {
            Db::startTrans();
            try {
                if ($order['order']['id']) {
                    $id = $order['order']['id'];
                    
                    /*
                     * 检查订单是否需要拉入的人工审核
                     * wangwei 2019-1-22 17:08:07
                     */
                    (new AmazonOrderService())->checkStatusChangeOrder($order['order']);

                    $this->where(['id' => $id])->update($order['order']);

                    foreach ($order['orderDetail'] as $detail) {
                        // $row = Db::name($detailTable)->where(['amazon_order_id' => $id, 'record_number' => $detail['record_number']])->update($detail);
                        $row = Db::name($detailTable)->where(['amazon_order_id' => $id, 'record_number' => $detail['record_number']])->field('id')->find();
                        if ($row) {
                            Db::name($detailTable)->where(['id' => $row['id']])->update($detail);
                        } else {
                            $detail['amazon_order_id'] = $id;
                            Db::name($detailTable)->insert($detail);
                        }
                    }
                }else {
                    if (!$partitionCache->getPartition('AmazonOrder', $order['order']['created_time'])) {
                        $partitionCache->setPartition('AmazonOrder', $order['order']['created_time']);
                    }
                    unset($order['order']['id']);

                    $id = Db::name($masterTable)->insert($order['order'], false, true);
                    foreach ($order['orderDetail'] as $detail) {
                        $detail['amazon_order_id'] = $id;
                        Db::name($detailTable)->insert($detail);
                    }
                }

                $order['order']['payment_amount'] = $order['order']['actual_total'];
                (new \app\order\service\AmazonSettlementReport())->updateSettlement($order['order']);

                Db::commit();
                
                /*
                 * 触发最晚预计到达时间事件
                 */
                if(!empty($order['order']['lastest_delivery_time'])){
                    (new AmazonOrderService())->trigger_lastest_delivery_event($order['order']);
                }
                
                $info = [
                    'last_update_time' => $order['order']['last_update_time'],
                    'id'  => $id
                ];
                Cache::store('AmazonOrder')->OrderUpdateTime($order['order']['account_id'], $order['order']['order_number'], $info);
            } catch (Exception $ex) {
                Db::rollback();
                Cache::handler()->hSet('hash:amazon_order:add_error', $order['order']['order_number'] . ' ' . date('Y-m-d H:i:s', time()), 'amazon订单添加异常'. $ex->getMessage());       
            }
            unset($order);
        }
        return true;
    }

    public function newAdd(array $data)
    {
        if (empty($data)) {
            return false;
        }

        $masterTable = "amazon_order";
        $partitionCache = Cache::store('Partition');
        foreach ($data as $order) {
            Db::startTrans();
            try {
                if ($order['order']['id']) {
                    $id = $order['order']['id'];
                    $this->where(['id' => $id])->update($order['order']);
                }else {
                    if (!$partitionCache->getPartition('AmazonOrder', $order['order']['created_time'])) {
                        $partitionCache->setPartition('AmazonOrder', $order['order']['created_time']);
                    }
                    unset($order['order']['id']);
                    $id = Db::name($masterTable)->insert($order['order'], false, true);
                }

                $order['order']['payment_amount'] = $order['order']['actual_total'];
                (new \app\order\service\AmazonSettlementReport())->updateSettlement($order['order']);
                Db::commit();
                $info = [
                    'last_update_time' => $order['order']['last_update_time'],
                    'id'  => $id
                ];
                Cache::store('AmazonOrder')->OrderUpdateTime($order['order']['account_id'], $order['order']['order_number'], $info);
            } catch (Exception $ex) {
                Db::rollback();
                Cache::handler()->hSet('hash:amazon_order:add_error', $order['order']['order_number'] . ' ' . date('Y-m-d H:i:s', time()), 'amazon订单添加异常'. $ex->getMessage());
            }
            unset($order);
        }
        return true;
    }

    public function addDetail(array $data)
    {
        if (empty($data)) {
            return false;
        }
        $masterTable = "amazon_order";
        $detailTable = "amazon_order_detail";
        $partitionCache = Cache::store('Partition');
        foreach ($data as $order) {
            Db::startTrans();
            try {
                if ($order['order']['id']) {
                    $id = $order['order']['id'];

                    $this->where(['id' => $id])->update($order['order']);
                    foreach ($order['orderDetail'] as $detail) {
//                         $row = Db::name($detailTable)->where(['amazon_order_id' => $id, 'record_number' => $detail['record_number']])->update($detail);
                        $row = Db::name($detailTable)->where(['amazon_order_id' => $id, 'record_number' => $detail['record_number']])->field('id')->find();
                        if ($row) {
                            Db::name($detailTable)->where(['id' => $row['id']])->update($detail);
                        } else {
                            $detail['amazon_order_id'] = $id;
                            Db::name($detailTable)->insert($detail);
                        }
                    }
                }else {
                    if (!$partitionCache->getPartition('AmazonOrder', $order['order']['created_time'])) {
                        $partitionCache->setPartition('AmazonOrder', $order['order']['created_time']);
                    }
                    unset($order['order']['id']);
                    $id = Db::name($masterTable)->insert($order['order'], false, true);
                    foreach ($order['orderDetail'] as $detail) {
                        $detail['amazon_order_id'] = $id;
                        Db::name($detailTable)->insert($detail);
                    }
                }
                Db::commit();
            } catch (Exception $ex) {
                Db::rollback();
                Cache::handler()->hSet('hash:amazon_order:add_error', $order['order']['order_number'] . ' ' . date('Y-m-d H:i:s', time()), 'amazon订单添加异常'. $ex->getMessage());
            }
            unset($order);
        }
        return true;
    }

    /**
     * 批量新增
     * @param array $data [description]
     */
    public function addAll(array $data)
    {
        foreach ($data as $key => $value) {
            $this->add($value);
        }
    }
    
    
    /**
     * 修改订单
     * @param  array $data [description]
     * @return [type]       [description]
     */
    public function edit(array $data, array $where)
    {
        return $this->allowField(true)->save($data, $where);
    }

    /**
     * 批量修改
     * @param  array $data [description]
     * @return [type]       [description]
     */
    public function editAll(array $data)
    {
        return $this->save($data);
    }

    /**
     * 检查订单是否存在
     * @return [type] [description]
     */
    protected function checkorder(array $data)
    {
        $result = $this->get($data);
        if (!empty($result)) {
            return $result;
        }
        return false;
    }
    
    public function skuList()
    {
        return $this->hasMany('amazon_order_detail', 'amazon_order_id', 'id')->field(true);
    }

}