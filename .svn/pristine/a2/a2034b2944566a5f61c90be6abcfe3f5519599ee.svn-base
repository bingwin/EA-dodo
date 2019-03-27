<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/26
 * Time: 9:36
 */

namespace app\goods\service;

use think\Request;
use app\index\service\User as userService;
use app\common\cache\driver\Goods;
use app\common\cache\driver\Warehouse;
use app\common\model\GoodsDiscount as Model;
use app\common\exception\JsonErrorException;


class GoodsDiscount
{

    /**
     * @var creditcard
     */
    protected $model;

    public function __construct()
    {
        if (is_null($this->model)) {
            $this->model = new Model();
        }
    }

    /**
     * 接收错误并返回,当你调用此类时，如果遇到需要获取错误信息时，请使用此方法。
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }


    /**
     * 获取列表
     * @param array $params
     * @return array
     * @throws \think\exception\DbException
     */
    public function getGoodsDiscountList(array $params)
    {
        $order = 'goods_discount.id';
        $sort = 'desc';
        $sortArr = [
            'proposer_time' => 'goods_discount.proposer_time',
            'audit_time' => 'goods_discount.audit_time',
        ];
        if (!empty($params['order_by']) && !empty($sortArr[$params['order_by']])) {
            $order = $sortArr[$params['order_by']];
        }
        if (!empty($params['sort']) && in_array($params['sort'], ['asc', 'desc'])) {
            $sort = $params['sort'];
        }
        $where = $this->getWhere($params);
        $request = Request::instance();
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $field = 'id,sku_id,warehouse_id,inventory_price,last_purchase_price,new_price,discount_type,discount_value,
        discount_num,sell_num,valid_time,over_time,proposer_id,proposer_time,audit_id,audit_time,status,remark';
        $count = $this->model->where($where)->count();
        $info  = $this->model->where($where)->field($field)->order($order, $sort)->page($page, $pageSize)->select();
        $userService = new UserService();
        $warehouseService = new Warehouse();
        $goodsCahce = new Goods();

        foreach ($info as $key => $item) {
            $proposerInfo = $userService->getUser($item['proposer_id']);//申请人
            $auditInfo = $userService->getUser($item['audit_id']);      //审核人
            $goodsInfo = $goodsCahce->getSkuInfo($item['sku_id']);
            $warehouseInfo = $warehouseService->getWarehouse($item['warehouse_id']);
            $info[$key]['sku'] = $goodsInfo['sku'];
            $info[$key]['warehouse'] = $warehouseInfo['name'];
            $info[$key]['proposer'] = $proposerInfo['realname'] ?? '';
            $info[$key]['audit'] = $auditInfo['realname'] ?? '';
            $info[$key]['proposer_time'] = date('Y-m-d H:i:s', $item['proposer_time']);
            $info[$key]['audit_time'] = $item['audit_time']?date('Y-m-d H:i:s', $item['audit_time']):'';
            $info[$key]['valid_time'] = date('Y-m-d H:i:s', $item['valid_time']);
            $info[$key]['over_time'] = date('Y-m-d H:i:s', $item['over_time']);
        }

        $result = [
            'data' => $info,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;

    }


    /**
     * 根据条件查询记录
     * @param array $data
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\exception\DbException
     */
    public function read(array $data)
    {
        $where = $this->getWhere($data);
        $field = 'id,sku_id,warehouse_id,inventory_price,last_purchase_price,new_price,discount_type,discount_value,
        discount_num,sell_num,valid_time,over_time,proposer_id,proposer_time,audit_id,audit_time,status,remark';

        $info = $this->model->field($field)->where($where)->find();
        if (!$info) {
            $this->error = '无法查询到记录';
            return $info;
        }
        $userService = new UserService();
        $warehouseService = new Warehouse();
        $goodsCahce = new Goods();
        $proposerInfo = $userService->getUser($info['proposer_id']);//申请人
        $auditInfo = $userService->getUser($info['audit_id']);      //审核人
        $goodsInfo = $goodsCahce->getSkuInfo($info['sku_id']);
        $warehouseInfo = $warehouseService->getWarehouse($info['warehouse_id']);
        $info['sku'] = $goodsInfo['sku'];
        $info['warehouse'] = $warehouseInfo['name'];
        $info['proposer'] = $proposerInfo['realname'] ?? '';
        $info['audit'] = $auditInfo['realname'] ?? '';
        $info['proposer_time'] = date('Y-m-d H:i:s', $info['proposer_time']);
        $info['audit_time'] = $info['audit_time']?date('Y-m-d H:i:s', $info['audit_time']):'';
        $info['valid_time'] = date('Y-m-d H:i:s', $info['valid_time']);
        $info['over_time'] = date('Y-m-d H:i:s', $info['over_time']);
        return $info;
    }

    /**
     * 获取查询条件
     * @param $params
     * @return array
     * @throws \Exception
     */
    public function getWhere($params)
    {
        $where = [];
        if (isset($params['warehouse_id']) && ($params['warehouse_id'] !== '')) {
            $where['goods_discount.warehouse_id'] = $params['warehouse_id'];
        }

        if (isset($params['sku_id']) && ($params['sku_id'] !== '')) {
            $where['goods_discount.sku_id'] = $params['sku_id'];
        }

        if (isset($params['proposer_id']) && ($params['proposer_id'] !== '')) {
            $where['goods_discount.proposer_id'] = $params['proposer_id'];
        }

        if (isset($params['sku']) && ($params['sku'] !== '')) {
            $sku_id = (new GoodsSku())->getSkuIdBySku($params['sku']);
            if ($sku_id) $where['goods_discount.sku_id'] = $sku_id;
        }

        if (isset($params['valid_time']) && ($params['valid_time'] !== '')) {
            $where['goods_discount.valid_time'] = ['egt',strtotime($params['valid_time'])];
        }

        if (isset($params['over_time']) && ($params['over_time'] !== '')) {
            $where['goods_discount.over_time'] = ['elt',strtotime($params['over_time'])];
        }

        if (isset($params['time']) && ($params['time'] !== '')) {
            $where['goods_discount.over_time'] = ['elt',strtotime($params['over_time'])];
            $where['goods_discount.valid_time'] = ['egt',strtotime($params['valid_time'])];
        }

        return $where;

    }


}