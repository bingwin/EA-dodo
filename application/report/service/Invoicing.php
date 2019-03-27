<?php

namespace app\report\service;

use app\common\model\User;
use app\purchase\service\PurchaseOrder;
use app\purchase\service\SupplierOfferService;
//use app\report\queue\ProfitExportQueue1;
use app\warehouse\service\WarehouseGoods;
use think\Db;
use think\Exception;
use think\Loader;
use app\common\model\WarehouseLog;
use app\common\cache\Cache;
use app\warehouse\service\StockIn as StockInService;
use app\warehouse\service\StockOut as StockOutService;
use app\common\model\StockInDetail as StockInDetail;
use app\common\model\StockOutDetail as StockOutDetail;
use app\goods\service\GoodsSkuAlias as GoodsSkuAliasService;
use \app\goods\service\GoodsHelp as GoodsHelp;
use app\common\model\GoodsSku;
use app\common\model\Goods;
use app\common\service\Common;
use app\report\model\ReportExportFiles;
use app\common\service\CommonQueuer;
use app\report\queue\ProfitExportQueue;
use app\report\queue\ProfitExportQueue1;
use app\report\queue\GoodsAnalysisExportQueue;
use app\common\traits\Export;

Loader::import('phpExcel.PHPExcel', VENDOR_PATH);

/**
 * Created by PhpStorm.
 * User: laiyongfeng
 * Date: 2019/03/20
 * Time: 19:17
 */
class Invoicing
{
    use Export;

    protected $stockInDetailModel = null;
    protected $stockOutDetailModel = null;
    protected $warehouseLogModel = null;
    protected $start_time = 0;//开始时间
    protected $end_time = 0;//时间时间
    protected $inout_where = [];
    protected $where = [];
    protected $warehouse_id = 0;
    protected $in_out_type = [];
    protected $colMap = [
        'SKU' => 'string',
        '产品名称' => 'string',
        '商品分类' =>'string',
        '仓库' => 'string',
        '期初库存-数量' => 'price',
        '期初库存-单价' => 'price',
        '期初库存-金额' => 'price',
        '本期入库-数量' => 'string',
        '本期入库-单价' =>'price',
        '本期入库-金额' => 'price',
        '本期出库-数量' => 'string',
        '本期出库-单价' => 'price',
        '本期出库-金额' => 'price',
        '期末库存-数量' => 'string',
        '期末库存-单价' => 'price',
        '期末库存-金额' => 'price',
        '<30天' => 'string',
        '30天<>60天' => 'string',
        '60天<>90天' => 'string',
        '90天以上' => 'string',
        '盘点数量' => 'string',
        '最近采购单价' =>'price',
        '最新采购报价' => 'price'
    ];

    public function __construct()
    {
        if (is_null($this->stockInDetailModel)) {
            $this->stockInDetailModel = new StockInDetail();
        }
        if (is_null($this->stockOutDetailModel)) {
            $this->stockOutDetailModel = new StockOutDetail();
        }
        if (is_null($this->warehouseLogModel)) {
            $this->warehouseLogModel = new WarehouseLog();
        }
        $in_type_arr = (new StockInService())->getTypes();
        $out_type_arr = (new StockOutService())->getOutType();
        $this->in_type = array_diff(array_column($in_type_arr, 'value'), array(0));
        $this->out_type = array_keys($out_type_arr);
        $this->in_out_type = array_merge($this->in_type, $this->out_type);
    }


    /*
     * @desc 分类查询
     * @param int $category_id
     */
    public function category($category_id)
    {
        $category_list = Cache::store('category')->getCategoryTree();
        if (isset($category_list[$category_id])) {
            $child = $category_list[$category_id]['child_ids'];
            if ($child) {
                $child = implode(',', $child);
                $category_ids = $child;
            } else {
                $category_ids = [$category_id];
            }
        } else {
            $category_ids = [$category_id];
        }
        $goods = (new Goods())->where('category_id', 'in', $category_ids)->field('id')->select();
        $goods_ids = array_map(function ($good) {
            return $good->id;
        }, $goods);
        $this->where .= ' and goods_id in(' . implode(',', $goods_ids) . ')';

    }

    /**
     * @desc 汇总列表
     * @param array $params
     * @param string $type
     */
    public function where($params, $type = 'summary')
    {
        $this->inout_where = [
            's.warehouse_id' => $this->warehouse_id,
            's.create_time' => [['>=', $this->start_time], ['<=', $this->end_time]],
        ];
        $this->where = 'l1.warehouse_id = ' . $this->warehouse_id;
        $this->where .= ' and l1.type in ('.implode(',', $this->in_out_type).')';
        $this->where .= " and l1.create_time >= {$this->start_time} and l1.create_time <= {$this->end_time}";
        if (($snType = param($params, 'snType')) && ($snValue = param($params, 'snText'))) {
            switch ($snType) {
                case 'sku':
                    $sku_arr = json_decode($snValue);
                    if (!$sku_arr) {
                        break;
                    }
                    $sku_id_arr = [];
                    foreach ($sku_arr as $value) {
                        $sku_id = GoodsSkuAliasService::getSkuIdByAlias($value);//别名
                        if (!$sku_id) {
                            $sku_id = GoodsHelp::sku2id($value);
                        }
                        array_push($sku_id_arr, $sku_id);
                    }
                    $this->where .= ' and l1.sku_id in (' . implode(',', $sku_id_arr) . ')';
                    break;
                case 'name':
                    $sku_id_arr = (new GoodsSku())->where('spu_name', 'like', "%$snValue%")->column('id');
                    $this->where .= ' and l1.sku_id in (' . implode(',', $sku_id_arr) . ')';
                    break;
                default:
                    break;
            }
        }
        if (param($params, 'category_id')) {
            $this->category($params['category_id']);
        }
    }

    /**
     * @desc 设置开始结束时间
     * @param array $params
     * @throws Exception
     */
    public function setStartEndTime($params)
    {
        if (!param($params, 'date_from')) {
            throw new Exception('开始时间不能为空');
        }
        if (!param($params, 'date_to')) {
            throw new Exception('结束时间不能为空');
        }
        $this->start_time = strtotime($params['date_from']);
        $this->end_time = strtotime($params['date_to']) + (3600 * 24 - 1);
    }

    /**
     * @desc 设置仓库
     * @param array $params
     * @throws Exception
     */
    public function setWarehouseId($params)
    {
        if (!param($params, 'warehouse_id')) {
            throw new Exception('仓库不能为空');
        }
        $this->warehouse_id = $params['warehouse_id'];
    }


    /**
     * @desc 出库数据
     * @param  array $param
     * @return array
     */
    private function getOutData()
    {
        $field = 'd.sku_id, s.warehouse_id, sum(d.quantity) as quantity, sum(d.price+d.shipping_cost) as price';
        return $this->stockOutDetailModel->alias('d')
            ->join('stock_out s', 's.id = d.stock_out_id')
            ->where($this->inout_where)
            ->field($field)
            ->column($field, 'd.sku_id');
    }

    /**
     * @desc 入库数据
     * @param  array $param
     * @return array
     */
    private function getInData()
    {
        $field = 's.code, d.sku_id, s.warehouse_id, sum(d.quantity) as quantity, sum(d.price+d.shipping_cost) as price';
        return $this->stockInDetailModel->alias('d')
            ->join('stock_in s', 's.id = d.stock_in_id')
            ->where($this->inout_where)
            ->field($field)
            ->group('d.sku_id')
            ->column($field, 'sku_id');
    }


    /**
     * @desc 期末数据
     * @param  array $params
     * @return array
     */
    private function geEndLogData($page, $pageSize)
    {
        $start = ($page - 1)*$pageSize;
        $sql = "SELECT *
        FROM (
            SELECT l1.sku_id, l1.stock_quantity, l1.type, l1.quantity, l1.price, l1.per_cost, l1.average_price, l1.shipping_cost, l1.shipping_fee
            FROM warehouse_log l1
            where {$this->where}
            ORDER BY l1.id desc LIMIT 9999
            ) l2
        GROUP BY l2.sku_id  limit {$start}, {$pageSize}";
        return  DB::query($sql);
    }

    /**
     * @desc 汇总列表
     * @return array
     */
    public function summaryCount()
    {
        $sql = "SELECT count(distinct l1.sku_id) as count
            FROM warehouse_log l1
            where {$this->where}";
        $data = DB::query($sql);
        return $data[0]['count'];
    }

    /**
     * @desc 期初数据
     * @param  array $sku_id_arr
     * @return array
     */
    private function getStartLogData($sku_id_arr)
    {
        $sql = "SELECT *
        FROM (
            SELECT l1.sku_id, l1.stock_quantity, l1.type, l1.quantity, l1.price, l1.per_cost, l1.average_price, l1.shipping_cost, l1.shipping_fee
            FROM warehouse_log l1
            where l1.create_time < {$this->start_time}  and l1.sku_id in (" . implode(',', $sku_id_arr) . ") and l1.warehouse_id = {$this->warehouse_id}
            ORDER BY l1.id desc  LIMIT 9999
            ) l2
        GROUP BY l2.sku_id";
        $data = DB::query($sql);
        return $data;
    }

    /**
     * @desc 汇总列表
     * @param array $data
     * @return array
     */
    private function getData($data)
    {
        $result = [];
        $type = in_array($data['type'], $this->in_type) ? 1 : 2; //1-入库 2-出库
        $result['qty'] = $type == 1 ? $data['stock_quantity'] + $data['quantity'] : $data['stock_quantity'] - $data['quantity'];
        if ($data['average_price']>0) {
            $result['price'] = $data['average_price'] + $data['shipping_cost'];
        } else {
            //原来没有存平均单价
            if ($type == 1) {
                $result['price'] = ($data['per_cost'] * $data['stock_quantity'] + $data['quantity'] * $data['price']) / $result['qty'];
            } else {
                $result['price'] = $data['per_cost'];
            }
        }
        $result['amount'] = $result['qty'] * $result['price'];
        return $result;
    }

    /**
     * @desc 汇总列表
     * @param  array $params
     * @return array
     */
    public function summary($page=1, $pageSize=20)
    {

        $stockInService = new StockInService;
        $purchaseOrder = new PurchaseOrder();
        $supplierOfferService = new SupplierOfferService();
        $warehouse = cache::store('warehouse')->getWarehouse($this->warehouse_id);
        //期末数据
        $end_data = $this->geEndLogData($page, $pageSize);
        $sku_id_arr = array_column($end_data, 'sku_id');
        //期初数据
        $start_data = $this->getStartLogData($sku_id_arr);
        $start_sku_data = [];
        foreach ($start_data as $item) {
            $start_sku_data[$item['sku_id']] = $item;
        }
        //出入库
        $this->inout_where['d.sku_id'] = ['in', $sku_id_arr];
        $in_data = $this->getInData();  //入库数据
        $out_data = $this->getOutData(); //出库数据

        $data = [];
        $end_qty_arr = [];
        foreach ($end_data as $end) {
            $sku_info = Cache::store('goods')->getSkuInfo($end['sku_id']);
            //期末数据
            $this_end = $this->getData($end);
            $end_qty = $this_end['qty'];//期末库存
            $end_price = $this_end['price'];//期末库存
            $end_amount = $this_end['amount']; //期末库存
            $end_qty_arr[$end['sku_id']] = $end_qty;


            //期初数据
            $init_qty = 0;//期初库存
            $init_price = 0;//期初单价
            $init_amount = 0; //期初数量
            $start = $start_sku_data[$end['sku_id']] ?? [];
            if ($start) {
                $this_start = $this->getData($start);
                $init_qty = $this_start['qty'];
                $init_price = $this_start['price'];
                $init_amount = $this_start['amount'];
            }


            //入库数据
            $in = $in_data[$end['sku_id']] ?? [];
            $in_qty = $in['quantity'] ?? 0;
            $in_price = $in ? $in['price'] : 0;
            $in_amount = sprintf('%.4f', $in_qty * $in_price);

            //出库数据
            $out = $out_data[$end['sku_id']] ?? [];
            $out_qty = $out['quantity'] ?? 0;
            $out_price = $out ? $out['price'] : 0;
            $out_amount = sprintf('%.4f', $out_price * $out_qty);


            //组装数据
            $data[$end['sku_id']] = [
                'sku' => $sku_info['sku'],
                'spu_name' => $sku_info['spu_name'],
                'warehouse_name' => $warehouse['name'],
                'init_qty' => $init_qty,
                'inti_price' => sprintf('%.4f', $init_price),
                'init_amount' => sprintf('%.4f', $init_amount),
                'end_qty' => $end_qty,
                'end_price' =>sprintf('%.4f', $end_price),
                'end_amount' => sprintf('%.4f', $end_amount),
                'in_qty' =>$in_qty,
                'in_price' => sprintf('%.4f', $in_price),
                'in_amount' => sprintf('%.4f', $in_amount),
                'out_qty' => $out_qty,
                'out_price' => sprintf('%.4f', $out_price),
                'out_amount' =>sprintf('%.4f', $out_amount),
                'check_qty' => 0, //盘点数据
                'latest purchase_price' => $purchaseOrder->getLastPurchasePrice($end['sku_id']), //最近采购单价
                'latest_supply_prcie' => $supplierOfferService->getGoodsOffer($end['sku_id']),
                'less_third' => 0,
                'third_sixty' => 0,
                'sixty_ninety' => 0,
                'more_ninety' => 0,
            ];
        }
        $stockInService->batchGetAgeDetail($this->warehouse_id, $end_qty_arr, $this->end_time, $data);
        return $data;
    }

    /**
     * @desc 入库运费
     * @param int $stock_in_id
     * @param int $sku_id
     * @return int
     */
    private function getInShippingFee($stock_in_id, $sku_id)
    {
        $where = [
            'stock_in_id' => $stock_in_id,
            'sku_id' => $sku_id,
        ];
        return $this->stockInDetailModel->where($where)->value('shipping_cost', 0);
    }


    /**
     * @desc 出库运费
     * @param int $stock_out_id
     * @param int $sku_id
     * @return int
     */
    private function getOutShippingFee($stock_out_id, $sku_id)
    {
        $where = [
            'stock_out_id' => $stock_out_id,
            'sku_id' => $sku_id,
        ];
        return $this->stockOutDetailModel->where($where)->value('shipping_cost', 0);
    }

    /**
     * @desc 汇总列表
     * @return array
     */
    public function detailCount()
    {
        return (new WarehouseLog())->alias('l1')->where($this->where)->count();
    }

    /**
     * @desc 明细列表
     * @param array $params
     * @return array
     */
    public function detail($params, $page = 1, $pageSize=20)
    {
        $stockInService = new StockInService;
        $data = (new WarehouseLog())->alias('l1')
            ->where($this->where)
            ->page($page, $pageSize)
            ->select();
        $in_types = $stockInService->getTypes();
        $warehouse = cache::store('warehouse')->getWarehouse($this->warehouse_id);
        foreach ($data as &$item) {
            $sku_info = Cache::store('goods')->getSkuInfo($item['id']);
            $item['spu_name'] = param($sku_info, 'name');
            $item['warehouse_name'] = $warehouse['name'];
            if (!$item['shipping_fee']) {
                if (in_array($data['type'], $in_types)) {
                    $item['shipping_fee'] = $this->getInShippingFee($item['stock_inout_id'], $item['sku_id']);
                } else {
                    $item['shipping_fee'] = $this->getOutShippingFee($item['stock_inout_id'], $item['sku_id']);
                }
            }
            $item['warehouse'] = cache::store('user')->getOneUserRealname($item['create_id']);
            $item['creator'] = cache::store('user')->getOneUserRealname($item['create_id']);
            $item['amount'] = ($item['price'] + $item['shipping_fee']) * $item['quantity'];
        }
        return $data;
    }

    /**
     * @desc 创建导出文件名
     * @param string $type
     * @return string
     */
    protected function createExportFileName($type = 'summary')
    {
        $fileName = $type == 'summary' ? '进销存汇总报表' : '进销存明细报表';
        $lastID = (new ReportExportFiles())->order('id desc')->value('id');
        $fileName .= ($lastID + 1);
        $fileName .= '_' . $this->start_time . '_' . $this->end_time.'.xlsx';
        return $fileName;
    }

    /**
     * 获取参数
     * @param array $params
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getParameter(array $params, $key, $default)
    {
        $v = $default;
        if (isset($params[$key]) && $params[$key]) {
            $v = $params[$key];
        }
        return $v;
    }


    /**
     * 申请导出
     * @param $params
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function applyExport($params)
    {
        Db::startTrans();
        try {
            $userId = Common::getUserInfo()->toArray()['user_id'];
            $userId = 1508;
            $cache = Cache::handler();
            $lastApplyTime = $cache->hget('hash:export_apply', $userId);
            if ($lastApplyTime && time() - $lastApplyTime < 5) {
                throw new Exception('请求过于频繁', 400);
            } else {
                $cache->hset('hash:export_apply', $userId, time());
            }
            $model = new ReportExportFiles();
            $model->applicant_id = $userId;
            $model->apply_time = time();
            $model->export_file_name = $this->createExportFileName();
            $model->status = 0;
            if (!$model->save()) {
                throw new Exception('导出请求创建失败', 500);
            }
            $params['file_name'] = $model->export_file_name;
            $params['apply_id'] = $model->id;

            $this->export($params);
//            new ProfitExporT
//            $queuer = new CommonQueuer(ProfitExportQueue::class);
            $queuer = new CommonQueuer(GoodsAnalysisExportQueue::class);
            $queuer->push($params);
            Db::commit();
            return true;
        } catch (\Exception $ex) {
            Db::rollback();
            if ($ex->getCode()) {
                throw $ex;
            } else {
                Cache::handler()->hset(
                    'hash:report_export_apply',
                    $params['apply_id'] . '_' . time(),
                    $ex->getMessage());
                throw new Exception($ex->getFile().$ex->getLine().$ex->getMessage(), 500);
            }
        }
    }


    /**
     * 导出数据至excel文件
     * @param $params
     * @return bool
     * @throws Exception
     */
    public function export($params)
    {
        set_time_limit(0);
        try {
//            ini_set('memory_limit','4096M');
            $applyId = $this->getParameter($params, 'apply_id', '');
            if (!$applyId) {
                throw new Exception('导出申请id获取失败');
            }
            $fileName = $this->getParameter($params, 'file_name', '');
            if (!$fileName) {
                throw new Exception('导出文件名未设置');
            }

            $downLoadDir = '/download/invoicing/';
            $saveDir = ROOT_PATH . 'public' . $downLoadDir;
            if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true)) {
                throw new Exception('导出目录创建失败');
            }
            $fullName = $saveDir . $fileName;
            //创建excel对象
            $writer = new \XLSXWriter();
            $titleMap = $this->colMap;
            $this->setStartEndTime($params);
            $this->setWarehouseId($params);
            $this->where($params);
            if ($params['type'] == 'summary') {
                //统计需要导出的数据行
                $count = $this->summaryCount();
                $func = 'summary';
            } else {
                $count = $this->detailCount();
                $func = 'detail';
            }
            $pageSize = 100;
            $loop = ceil($count / $pageSize);
            $writer->writeSheetHeader('Sheet1', $titleMap);
            //分批导出
            for ($i = 0; $i < $loop; $i++) {
                $params['page'] =  $i+1;
                $params['pageSize'] =  $pageSize;

                $data = $this->$func($params);
                var_dump($params);exit;
                foreach ($data as $r) {
                    $writer->writeSheetRow('Sheet1', $r);
                }
                unset($data);
            }

            $writer->writeToFile($fullName);
            var_dump( $downLoadDir . $fileName);exit;
            if (is_file($fullName)) {
                $applyRecord = ReportExportFiles::get($applyId);
                $applyRecord->exported_time = time();
                $applyRecord->download_url = $downLoadDir . $fileName;
                $applyRecord->status = 1;
                $applyRecord->isUpdate()->save();
            } else {
                throw new Exception('文件写入失败');
            }
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage());
            $applyRecord = ReportExportFiles::get($applyId);
            $applyRecord->status = 2;
            $applyRecord->error_message = $ex->getMessage();
            $applyRecord->isUpdate()->save();
            Cache::handler()->hset(
                'hash:report_export',
                $applyId . '_' . time(),
                '申请id: ' . $applyId . ',导出失败:' . $ex->getMessage());

        }
        return true;
    }
}