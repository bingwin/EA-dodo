<?php

namespace app\report\service;

use app\common\cache\Cache;
use app\common\exception\JsonErrorException;
use app\common\filter\DevelopmentFilter;
use app\common\filter\PurchaserFilter;
use app\common\model\GoodsSku;
use app\common\model\OrderLack;
use app\common\model\OrderOos;
use app\common\model\OrderSourceLack;
use app\common\service\Common;
use app\common\service\CommonQueuer;
use app\common\service\Filter;
use app\goods\service\GoodsHelp;
use app\report\model\ReportExportFiles;
use app\report\queue\OrderLackExportQueue;
use think\Db;
use think\Exception;
use think\Loader;
use app\common\model\OrderOos as LackModel;
use app\common\model\Goods as GoodsModel;
use app\warehouse\service\WarehouseGoods as WarehouseGoodsService;
use app\common\traits\Export;

Loader::import('phpExcel.PHPExcel', VENDOR_PATH);

/**
 * Created by PhpStorm.
 * User: libaimin
 * Date: 2018/6/14
 * Time: 20:12
 */
class OrderLackService
{
    use Export;

    protected $PCardRate = [
        'amazon' => 0.006,
        'wish' => 0.005,
    ];
    protected $colMap = [
        'order' => [
            'title' => [
                'A' => ['title' => 'SKU', 'width' => 30],
                'B' => ['title' => '别名', 'width' => 10],
                'C' => ['title' => '商品名称', 'width' => 10],
                'D' => ['title' => '商品状态', 'width' => 10],
                'E' => ['title' => '在途库存', 'width' => 10],
                'F' => ['title' => '可用库存', 'width' => 10],
                'G' => ['title' => '待发库存', 'width' => 10],
                'H' => ['title' => '缺货数量', 'width' => 10],
                'I' => ['title' => '缺货订单数量', 'width' => 30],
                'J' => ['title' => '最早缺货时间', 'width' => 30],
                'K' => ['title' => '开发员', 'width' => 30],
                'L' => ['title' => '采购员', 'width' => 15],
            ],
            'data' => [
                'sku' => ['col' => 'A', 'type' => 'str'],
                'alias' => ['col' => 'B', 'type' => 'str'],
                'name' => ['col' => 'C', 'type' => 'str'],
                'sales_status' => ['col' => 'D', 'type' => 'int'],
                'instransit_qty' => ['col' => 'E', 'type' => 'str'],
                'available_qty' => ['col' => 'F', 'type' => 'str'],
                'shipping_qty' => ['col' => 'G', 'type' => 'str'],
                'lack_qty' => ['col' => 'H', 'type' => 'str'],
                'counts_order_id' => ['col' => 'I', 'type' => 'str'],
                'create_time' => ['col' => 'J', 'type' => 'time'],
                'developer_id' => ['col' => 'K', 'type' => 'str'],
                'purchaser_id' => ['col' => 'L', 'type' => 'str'],
            ]
        ],
    ];



    public function title()
    {
        $title = [
            'sku' => [
                'title' => 'sku',
                'remark' => 'SKU',
                'is_show' => 1
            ],
            'alias' => [
                'title' => 'alias',
                'remark' => '别名',
                'is_show' => 1
            ],
            'name' => [
                'title' => 'name',
                'remark' => '商品名称',
                'is_show' => 1
            ],
            'sales_status' => [
                'title' => 'sales_status',
                'remark' => '商品状态',
                'is_show' => 1
            ],
            'instransit_qty' => [
                'title' => 'instransit_qty',
                'remark' => '在途库存',
                'is_show' => 1
            ],
            'available_qty' => [
                'title' => 'available_qty',
                'remark' => '可用库存',
                'is_show' => 1
            ],
            'shipping_qty' => [
                'title' => 'shipping_qty',
                'remark' => '待发库存',
                'is_show' => 1
            ],
            'lack_qty' => [
                'title' => 'lack_qty',
                'remark' => '缺货数量',
                'is_show' => 1
            ],
            'counts_order_id' => [
                'title' => 'counts_order_id',
                'remark' => '缺货订单数量',
                'is_show' => 1
            ],
            'create_time' => [
                'title' => 'create_time',
                'remark' => '最早缺货时间',
                'is_show' => 1
            ],
            'developer_id' => [
                'title' => 'developer_id',
                'remark' => '开发员',
                'is_show' => 1
            ],
            'purchaser_id' => [
                'title' => 'purchaser_id',
                'remark' => '采购员',
                'is_show' => 1
            ],
        ];
        return $title;
    }

    /**
     * 查询相关goods_id
     * @param $key
     * @param $val
     * @return array
     */
    public function getGoodIds($key, $val)
    {
        $goods = new GoodsModel();
        $where = [];
        if(is_array($val)){
            $where[$key] = ['in',$val];
        }else{
            $where[$key] = $val;
        }
        $goodsIds = $goods->where($where)->column('id');
        return $goodsIds;
    }

    /**
     * 列表详情
     * @param $page
     * @param $pageSize
     * @param $params
     * @return array
     */
    public function getStockLacks($params)
    {


        $page = param($params, 'page', 1);
        $pageSize = param($params, 'pageSize', 20);
        $where = [];

        $sort = "";
        $sort_type = param($params, 'sort_type');
        $sort_field = param($params, 'sort_field');
        //排序刷选
        if ($sort_type && $sort_field) {
            $sort = $sort_field . " " . $sort_type;
        }

        $join = $this->getJoin();
        $this->getWhere($params, $where,$join);
        $count = $this->getCount($where ,$join);
        $returnArr = $this->assemblyData($where ,$join, $page, $pageSize, $sort);
        return [
            'count' => $count,
            'data' => $returnArr,
            'page' => $page,
            'pageSize' => $pageSize
        ];
    }


    /**
     * 导出申请
     * @param $params
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function exportApply($params)
    {
        $userId = Common::getUserInfo()->toArray()['user_id'];
        $cache = Cache::handler();
        $lastApplyTime = $cache->hget('hash:export_lack_apply', $userId);
        if ($lastApplyTime && time() - $lastApplyTime < 5) {
            throw new JsonErrorException('请求过于频繁', 400);
        } else {
            $cache->hset('hash:export_apply', $userId, time());
        }
        Db::startTrans();
        try {
            $model = new ReportExportFiles();
            $data['applicant_id'] = $userId;
            $data['apply_time'] = time();

            //设置导出文件名
            $fileName = $this->newExportFileName($params);
            if($fileName != ''){
                $data['export_file_name'] = $fileName . '库存管理_缺货列表.xls';
            }else{
                $data['export_file_name'] = $this->createExportFileName($userId);
            }

            $data['status'] = 0;
            $data['applicant_id'] = $userId;
            $model->allowField(true)->isUpdate(false)->save($data);
            $params['file_name'] = $data['export_file_name'];
            $params['apply_id'] = $model->id;
            (new CommonQueuer(OrderLackExportQueue::class))->push($params);
            Db::commit();
            return true;
        } catch (\Exception $ex) {
            Db::rollback();
            throw new JsonErrorException('申请导出失败');
        }
    }

    /**
     * @title 生成导出用户名
     * @param $params
     * @return string
     */
    public function newExportFileName($params)
    {
        $fileName = '';
        // 仓库ID
        if ($warehouse_id = param($params, 'warehouse_id')) {
            $warehouse_name = Cache::store('warehouse')->getWarehouseNameById($warehouse_id);
            $fileName .= '仓库' . $warehouse_name . '|';
        }
        // 开发人员
        if ($purchaser_id = param($params, 'purchaser_id')) {
            $cache = Cache::store('user');
            $user = $cache->getOneUser($purchaser_id ?? '') ?? '';
            $fileName .= '开发员：' . $user['realname'] . '|';
        }
        // 采购人员
        if ($developer_id = param($params, 'developer_id')) {
            $cache = Cache::store('user');
            $user = $cache->getOneUser($developer_id ?? '') ?? '';
            $fileName .= '采购员：' . $user['realname'] . '|';
        }
        // 商品状态
        if ($goodsStatus = param($params, 'goodsStatus')) {
            if ($goodsStatus != 0) {
                $GoodsHelp = new GoodsHelp();
                $goodsStatus = $GoodsHelp->getStatusAttr($goodsStatus);
                $fileName .= '商品状态' . $goodsStatus . '|';
            }
        }
        return $fileName;
    }

    /**
     * 创建导出文件名
     * @param $userId
     * @return string
     */
    protected function createExportFileName($userId)
    {
        $fileName = '库存管理_缺货列表导出队列_' . $userId . '_' . date("Y_m_d_H_i_s") . '.xls';
        return $fileName;
    }

    /**
     * 导出数据至excel文件
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function export(array $params)
    {
        try {
            opcache_reset();
            set_time_limit(0);
            //ini_set('memory_limit', '4096M');
            if (!isset($params['apply_id']) || empty($params['apply_id'])) {
                throw new Exception('导出申请id获取失败');
            }
            if (!isset($params['file_name']) || empty($params['file_name'])) {
                throw new Exception('导出文件名未设置');
            }
            $fileName = $params['file_name'];
            $downLoadDir = '/download/order_lack/';
            $saveDir = ROOT_PATH . 'public' . $downLoadDir;
            if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true)) {
                throw new Exception('导出目录创建失败');
            }
            $fullName = $saveDir . $fileName;
            //创建excel对象
            $writer = new \XLSXWriter();
            $fields = $params['field'] ?? [];
            $titleData = $this->title();
            $title = [];
            if (!empty($fields)) {
                $titleNewData = [];
                foreach ($fields as $k => $v) {
                    if (isset($titleData[$v])) {
                        array_push($title, $v);
                        $titleNewData[$v] = $titleData[$v];
                    }
                }
                $titleData = $titleNewData;
            } else {
                foreach ($titleData as $k => $v) {
                    if ($v['is_show'] == 0) {
                        unset($titleData[$k]);
                    } else {
                        array_push($title, $k);
                    }
                }
            }
            list($titleMap, $dataMap) = $this->getExcelMap($titleData);
            end($titleMap);
            $titleOrderData = [];
            foreach ($titleMap as $t => $tt) {
                $titleOrderData[$tt['title']] = 'string';
            }
            $writer->writeSheetHeader('Sheet1', $titleOrderData);


            //统计需要导出的数据行
            $where = [];
            $join = $this->getJoin();
            $this->getWhere($params, $where,$join);
            $where = is_null($where) ? [] : $where;
            $count = $this->getCount($where,$join);
            $pageSize = 5000;
            $loop = ceil($count / $pageSize);
            if (empty($loop)) {
                $loop = 1;
            }
            $goodsHelp = new GoodsHelp();
            //分批导出
            for ($i = 0; $i < $loop; $i++) {
                $data = $this->assemblyData($where, $join,$i + 1, $pageSize,'',$title);
                foreach ($data as $a => $r) {
                    $r['sales_status'] = $goodsHelp->getStatusAttr($r['sales_status']);
                    $r['create_time'] = date('Y-m-d H:i:s',$r['create_time']);
                    $writer->writeSheetRow('Sheet1', $r);
                }
                unset($data);
            }
            $writer->writeToFile($fullName);
            if (is_file($fullName)) {
                $applyRecord = ReportExportFiles::get($params['apply_id']);
                $applyRecord['exported_time'] = time();
                $applyRecord['download_url'] = $downLoadDir . $fileName;
                $applyRecord['status'] = 1;
                $applyRecord->isUpdate()->save();
            } else {
                throw new Exception('文件写入失败');
            }



        } catch (\Exception $ex) {
            $applyRecord = ReportExportFiles::get($params['apply_id']);
            $applyRecord['status'] = 2;
            $applyRecord['error_message'] = $ex->getMessage();
            $applyRecord->isUpdate()->save();
            Cache::handler()->hset(
                'hash:report_export',
                $params['apply_id'].'_'.time(),
                '申请id: ' . $params['apply_id'] . ',导出失败:' . $ex->getMessage());
        }
    }


    /**
     * 组装查询返回数据
     * @param $records
     * @return array
     */
    protected function assemblyData($where, $join,$page, $pageSize, $sort = '',$title=[])
    {

        $model = new LackModel();
        $field = 'min(o.create_time) as create_time,count(o.order_id) as counts_order_id,o.warehouse_id,o.sku_id,o.sku,o.goods_id,(sum(requisition_qty) - sum(alloted_qty) ) as lack_qty,g.status';

        $list = $model->alias('o')->join($join)->field($field)->where($where)
            ->where('`requisition_qty` > `alloted_qty`')
            ->order($sort)
            ->group('o.sku')
            ->having('lack_qty > 0')
            ->page($page, $pageSize)->select();
        $returnArr = [];
        $warehouseGoodsServer = new WarehouseGoodsService();

        $cache = Cache::store('user');
        foreach ($list as $data) {
            $one = [];
            $goods = Cache::store('goods')->getGoodsInfo($data['goods_id']);
            $sku = Cache::store('Goods')->getSkuInfo($data['sku_id']);
            $one['name'] = $goods['name'] ?? '';
            $one['thumb'] = $sku['thumb'] ?? '';
            $one['alias'] = $goods['alias'] ?? '';
            $one['spu'] = $goods['spu'] ?? '';
            $user = $cache->getOneUser($goods['developer_id'] ?? '') ?? '';
            $one['developer_id'] = $user['realname'] ?? '';
            $one['sales_status'] = $data['status'] ?? ''; //sku状态
            $user = $cache->getOneUser($goods['purchaser_id'] ?? '') ?? '';
            $one['purchaser_id'] = $user['realname'] ?? '';
            $one['sku'] = $data['sku'];
            $one['create_time'] = $data['create_time'];
            $one['counts_order_id'] = $data['counts_order_id'];
            $one['lack_qty'] = $data['lack_qty'];//$data['requisition_qty'] - $data['alloted_qty']; //缺货数量 在途库存  可用库存 待发货库存
            $qty = $warehouseGoodsServer->getWarehouseGoods($data['warehouse_id'], $data['sku_id'], ['instransit_quantity', 'available_quantity', 'waiting_shipping_quantity']);
            $one['shipping_qty'] = $qty['waiting_shipping_quantity']; //待发货库存
            $one['instransit_qty'] = $qty['instransit_quantity']; //在途库存
            $one['available_qty'] = $qty['available_quantity']; //可用库存

            if($title){
                $temp = [];
                foreach ($title as $k => $v) {
                    $temp[$v] = $one[$v] ?? '';
                }
                array_push($returnArr, $temp);
            }else{
                $returnArr[] = $one;
            }
        }
        unset($list);
        return $returnArr;
    }

    /**
     * 查询条件
     * @param $params
     * @param $where
     * @return \think\response\Json
     */
    private function getWhere($params, &$where ,&$join)
    {

        $gGoodsIds = [];



        //采购过滤器
        $contents = false;
        $targetFillter = new Filter(PurchaserFilter::class,true);
        if($targetFillter->filterIsEffective()) {
            $contents = $targetFillter->getFilterContent();
            if(in_array(-1,$contents)){
                $contents = false;
            }
        }
        //采购人
        if ($purchaser_id = param($params, 'purchaser_id') ) {
            $gGoodsIds[] = $this->getGoodIds('purchaser_id', $purchaser_id);
            if($contents && !in_array($purchaser_id,$contents)){
                $where['o.id'] = ['=', -1];
            }
        }else{
            if($contents){
                $goods = new GoodsModel();
                $gGoodsIds[] = $goods->where('purchaser_id','in',$contents)->column('id');
            }
        }

        //开发过滤器
        $targetFillter = new Filter(DevelopmentFilter::class,true);
        $contents = false;
        if($targetFillter->filterIsEffective()) {
            $contents = $targetFillter->getFilterContent();
            if(in_array(-1,$contents)){
                $contents = false;
            }
        }
        // 开发人员
        if ($developer_id = param($params, 'developer_id')) {
            $gGoodsIds[] = $this->getGoodIds('developer_id', $developer_id);
            if ($contents && !in_array($developer_id, $contents)) {
                $where['o.id'] = ['=', -1];
            }
        }else{
            if($contents){
                $goods = new GoodsModel();
                $gGoodsIds[] = $goods->where('developer_id', 'in', $contents)->column('id');
            }
        }


        $where['o.lock'] = ['<>', 2];
        // 仓库ID
        if ($warehouse_id = param($params, 'warehouse_id')) {
            $where['o.warehouse_id'] = $warehouse_id;
        }
        // 商品SKU状态
        if ($goodsStatus = param($params, 'goodsStatus')) {
            if ($goodsStatus != 0) {
                $where['g.status'] = $goodsStatus;
            }
        }

        $snType = param($params, 'snType');
        $snText = param($params, 'snText');
        if ($snType && $snText) {
            $snText = is_json($snText) ? json_decode($snText, true) : (array)$params['snText'];
            switch ($snType) {
                case 'alias':
                    $gGoodsIds[] = $this->getGoodIds('alias', $snText);
                    break;
                case 'spu':
                    $gGoodsIds[] = $this->getGoodIds('spu', $snText);
                    break;
                case 'sku':
                    $where['o.sku'] = ['in', $snText];
                    break;
                default:
                    break;
            }
        }

        if($gGoodsIds){
            $str = $gGoodsIds[0];
            if(count($gGoodsIds) > 1){
                foreach ($gGoodsIds as $k=>$v){
                    if($k == 0){
                        continue;
                    }
                    $str = array_intersect($str,$v);
                }
            }
            $where['o.goods_id'] = ['in', $str];
        }

        //平台过滤器

        if ($channelId = param($params, 'channel_id') ) {
            if($channelId > 0){
                $join['order'] = ['order','o.order_id = order.id','left'];
                $where['order.channel_id'] = $channelId;
            }
        }
    }

    public function getCount($where,$join){
        $model = new OrderOos();
        $count = $model->alias('o')->join($join)->where($where)->where('`requisition_qty` > `alloted_qty`')->group('o.sku')->count();
        return $count;
    }

    public function getJoin(){
        $join['goods_sku'] = ['goods_sku g', 'o.sku_id = g.id', 'left'];
        return $join;
    }

    /**
     * 根据sku_id 获取 最早缺货时间 如果不存在则为0
     * @param $sku_id
     * @param int $warehouse_id
     * @return mixed
     */
    public function getShortageEarliestTime($sku_id, $warehouse_id = 0)
    {
        $where = [
            'sku_id' => $sku_id,
            'lock' => ['<>', 2],
        ];
        if($warehouse_id > 0){
            $where['warehouse_id'] = $warehouse_id;
        }
        $time = (new OrderOos())
            ->where('requisition_qty > alloted_qty ')
            ->where($where)
            ->order('create_time asc')
            ->value('create_time');
        return $time ? $time : 0;
    }

}