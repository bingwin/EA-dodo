<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | File  : ProfitStatement.php
// +----------------------------------------------------------------------
// | Author: tanbin 
// +----------------------------------------------------------------------
// | Date  : 2017-09-19
// +----------------------------------------------------------------------
// +----------------------------------------------------------------------
namespace app\report\service;

use erp\AbsServer;
use app\common\model\report\ReportStatisticByDeeps as ReportStatisticByDeepsModel;
use app\index\service\User as UserService;
use app\index\service\ChannelAccount;
use app\index\service\MemberShipService;
use app\index\service\Department;
use app\common\cache\Cache;
use app\common\service\Common;
use app\common\service\CommonQueuer;
use think\Db;
use think\Exception;
use app\report\model\ReportExportFiles;
use app\report\queue\PerformanceExportQueue;
use think\Loader;
use app\report\validate\FileExportValidate;
use app\common\model\User as UserModel;
use app\common\model\Order as OrderModel;
use app\common\model\Department as DepartmentModel;
use app\index\service\DepartmentUserMapService as DepartmentUserMapService;
Loader::import('phpExcel.PHPExcel', VENDOR_PATH);

class PerformanceService extends  AbsServer
{
  
    protected $model;
    protected $where = [];
    protected $colMap = [
        'amazon' => [
            'account' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员',     'width'=>20],
                    'C' => ['title'=>'销售组长',   'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'测评费用',       'width'=>15],
                    'H' => ['title'=>'实际售价  ', 'width'=>15],
                    'I' => ['title'=>'平台费用CNY', 'width'=>15],
                    'J' => ['title'=>'P卡费用',     'width'=>15],
                    'K' => ['title'=>'物流费用', 'width'=>15],
                    'L' => ['title'=>'包装费用', 'width'=>15],
                    'M' => ['title'=>'头程报关费',   'width'=>15],
                    'N' => ['title'=>'商品成本',   'width'=>15],
                    'O' => ['title'=>'毛利', 'width'=>10],
                    'P' => ['title'=>'退款','width'=>10],
                    'Q' => ['title'=>'店铺费用',     'width'=>15],
                    'R' => ['title'=>'广告费用',     'width'=>15],
                    'S' => ['title'=>'实际利润',  'width'=>15],
                    'T' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'sale_director'     => ['col'=>'D', 'type' => 'str'],
                    'order_num'         => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'F', 'type' => 'numeric'],
                    'appraisal_fee'     => ['col'=>'G', 'type' => 'numeric'],
                    'actual_fee'        => ['col'=>'H', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'I', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'J', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'K', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'L', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'M', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'N', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'O', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'P', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'Q', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'R', 'type' => 'numeric'],
                    'profit'            => ['col'=>'S', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'T', 'type' => 'str'],
                ],
                'last_col' =>'T'
            ],
            'seller' =>  [
                'title' => [
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'测评费用',       'width'=>15],
                    'H' => ['title'=>'实际售价  ', 'width'=>15],
                    'I' => ['title'=>'平台费用CNY', 'width'=>15],
                    'J' => ['title'=>'P卡费用',     'width'=>15],
                    'K' => ['title'=>'物流费用', 'width'=>15],
                    'L' => ['title'=>'包装费用', 'width'=>15],
                    'M' => ['title'=>'头程报关费',   'width'=>15],
                    'N' => ['title'=>'商品成本',   'width'=>15],
                    'O' => ['title'=>'毛利', 'width'=>10],
                    'P' => ['title'=>'退款','width'=>10],
                    'Q' => ['title'=>'店铺费用',     'width'=>10],
                    'R' => ['title'=>'广告费用',     'width'=>10],
                    'S' => ['title'=>'实际利润',  'width'=>15],
                    'T' => ['title'=>'利润率','width'=>15]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'A', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'B', 'type' => 'str'],
                    'sale_director'     => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'appraisal_fee'     => ['col'=>'F', 'type' => 'numeric'],
                    'actual_fee'        => ['col'=>'G', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'H', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'I', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'J', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'K', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'L', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'M', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'N', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'O', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'P', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'Q', 'type' => 'numeric'],
                    'profit'            => ['col'=>'R', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'S', 'type' => 'str'],
                ],
                'last_col' =>'S'
            ],
            'overseas' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'平台费用CNY', 'width'=>15],
                    'H' => ['title'=>'P卡费用',     'width'=>15],
                    'I' => ['title'=>'物流费用', 'width'=>15],
                    'J' => ['title'=>'包装费用', 'width'=>15],
                    'K' => ['title'=>'头程报关费',   'width'=>15],
                    'L' => ['title'=>'商品成本',   'width'=>15],
                    'M' => ['title'=>'毛利', 'width'=>10],
                    'N' => ['title'=>'退款','width'=>10],
                    'O' => ['title'=>'实际利润',  'width'=>15],
                    'P' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'sale_director'     => ['col'=>'D', 'type' => 'str'],
                    'order_num'         => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'F', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'G', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'H', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'I', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'J', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'K', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'L', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'M', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'N', 'type' => 'numeric'],
                    'profit'            => ['col'=>'O', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'P', 'type' => 'str'],
                ],
                'last_col'=>'P'
            ],
            'local' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>10],
                    'G' => ['title'=>'测评费用',       'width'=>15],
                    'H' => ['title'=>'实际售价  ', 'width'=>15],
                    'I' => ['title'=>'平台费用CNY', 'width'=>15],
                    'J' => ['title'=>'P卡费用',     'width'=>15],
                    'K' => ['title'=>'物流费用', 'width'=>15],
                    'L' => ['title'=>'包装费用', 'width'=>15],
                    'M' => ['title'=>'头程报关费',   'width'=>15],
                    'N' => ['title'=>'商品成本',   'width'=>15],
                    'O' => ['title'=>'毛利', 'width'=>10],
                    'P' => ['title'=>'退款','width'=>10],
                    'Q' => ['title'=>'店铺费用',     'width'=>15],
                    'R' => ['title'=>'广告费用',     'width'=>15],
                    'S' => ['title'=>'实际利润',  'width'=>15],
                    'T' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'sale_director'     => ['col'=>'D', 'type' => 'str'],
                    'order_num'         => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'F', 'type' => 'numeric'],
                    'appraisal_fee'     => ['col'=>'G', 'type' => 'numeric'],
                    'actual_fee'        => ['col'=>'H', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'I', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'J', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'K', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'L', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'M', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'N', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'O', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'P', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'Q', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'R', 'type' => 'numeric'],
                    'profit'            => ['col'=>'S', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'T', 'type' => 'str'],
                ],
                'last_col'=>'T'
            ],
        ],
        'wish' => [
            'account' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'P卡费用',     'width'=>15],
                    'H' => ['title'=>'物流费用', 'width'=>15],
                    'I' => ['title'=>'包装费用', 'width'=>15],
                    'J' => ['title'=>'头程报关费',   'width'=>15],
                    'K' => ['title'=>'商品成本',   'width'=>15],
                    'L' => ['title'=>'毛利', 'width'=>10],
                    'M' => ['title'=>'退款','width'=>10],
                    'N' => ['title'=>'推广费',     'width'=>15],
                    'O' => ['title'=>'罚款',     'width'=>10],
                    'P' => ['title'=>'活动现金返利',     'width'=>20],
                    'Q' => ['title'=>'实际利润',  'width'=>15],
                    'R' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'G', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'H', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'I', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'J', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'K', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'L', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'M', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'N', 'type' => 'numeric'],
                    'fine'              => ['col'=>'O', 'type' => 'numeric'],
                    'cash_rebate'       => ['col'=>'P', 'type' => 'numeric'],
                    'profit'            => ['col'=>'Q', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'R', 'type' => 'str'],
                ],
                'last_col'=>'R'
            ],
            'seller' =>  [
                'title' => [
                    'A' => ['title'=>'销售员', 'width'=>20],
                    'B' => ['title'=>'订单数', 'width'=>10],
                    'C' => ['title'=>'售价CNY', 'width'=>15],
                    'D' => ['title'=>'平台费用CNY', 'width'=>15],
                    'E' => ['title'=>'P卡费用',     'width'=>15],
                    'F' => ['title'=>'物流费用', 'width'=>15],
                    'G' => ['title'=>'包装费用', 'width'=>15],
                    'H' => ['title'=>'头程报关费',   'width'=>15],
                    'I' => ['title'=>'商品成本',   'width'=>15],
                    'J' => ['title'=>'毛利', 'width'=>10],
                    'K' => ['title'=>'退款','width'=>10],
                    'L' => ['title'=>'推广费',     'width'=>15],
                    'M' => ['title'=>'罚款',     'width'=>10],
                    'N' => ['title'=>'活动现金返利',     'width'=>10],
                    'O' => ['title'=>'实际利润',  'width'=>10],
                    'P' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'sale_user'         => ['col'=>'A', 'type' => 'str'],
                    'order_num'         => ['col'=>'B', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'C', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'D', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'E', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'F', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'G', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'H', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'I', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'J', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'K', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'L', 'type' => 'numeric'],
                    'fine'              => ['col'=>'M', 'type' => 'numeric'],
                    'cash_rebate'       => ['col'=>'N', 'type' => 'numeric'],
                    'profit'            => ['col'=>'O', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'P', 'type' => 'str'],
                ],
                'last_col'=>'P'
            ],
            'overseas' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'P卡费用',     'width'=>15],
                    'H' => ['title'=>'物流费用', 'width'=>15],
                    'I' => ['title'=>'包装费用', 'width'=>15],
                    'J' => ['title'=>'头程报关费',   'width'=>15],
                    'K' => ['title'=>'商品成本',   'width'=>15],
                    'L' => ['title'=>'毛利', 'width'=>10],
                    'M' => ['title'=>'退款','width'=>10],
                    'N' => ['title'=>'推广费',     'width'=>15],
                    'O' => ['title'=>'罚款',     'width'=>10],
                    'P' => ['title'=>'活动现金返利',     'width'=>20],
                    'Q' => ['title'=>'实际利润',  'width'=>15],
                    'R' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'G', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'H', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'I', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'J', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'K', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'L', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'M', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'N', 'type' => 'numeric'],
                    'fine'              => ['col'=>'O', 'type' => 'numeric'],
                    'cash_rebate'       => ['col'=>'P', 'type' => 'numeric'],
                    'profit'            => ['col'=>'Q', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'R', 'type' => 'str'],
                ],
                'last_col'=>'R'
            ],
            'local' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'P卡费用',     'width'=>15],
                    'H' => ['title'=>'物流费用', 'width'=>15],
                    'I' => ['title'=>'包装费用', 'width'=>15],
                    'J' => ['title'=>'头程报关费',   'width'=>15],
                    'K' => ['title'=>'商品成本',   'width'=>15],
                    'L' => ['title'=>'毛利', 'width'=>10],
                    'M' => ['title'=>'退款','width'=>10],
                    'N' => ['title'=>'推广费',     'width'=>15],
                    'O' => ['title'=>'实际利润',  'width'=>15],
                    'P' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'p_fee'             => ['col'=>'G', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'H', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'I', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'J', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'K', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'L', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'M', 'type' => 'numeric'],
                    'ads_fee'           => ['col'=>'N', 'type' => 'numeric'],
                    'profit'            => ['col'=>'O', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'P', 'type' => 'str'],
                ],
                'last_col'=>'R'
            ],
        ],
        'aliExpress' =>[
            'account' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'物流费用', 'width'=>15],
                    'H' => ['title'=>'包装费用', 'width'=>15],
                    'I' => ['title'=>'头程报关费',   'width'=>15],
                    'J' => ['title'=>'商品成本',   'width'=>15],
                    'K' => ['title'=>'毛利', 'width'=>10],
                    'L' => ['title'=>'退款','width'=>10],
                    'M' => ['title'=>'账号年费','width'=>15],
                    'N' => ['title'=>'店铺费用',     'width'=>15],
                    'O' => ['title'=>'实际利润',  'width'=>15],
                    'P' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'G', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'H', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'I', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'J', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'K', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'L', 'type' => 'numeric'],
                    'account_fee'       => ['col'=>'M', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'N', 'type' => 'numeric'],
                    'profit'            => ['col'=>'O', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'P', 'type' => 'str'],
                ],
                'last_col'=>'P'
            ],
            'seller' =>  [
                'title' => [
                    'A' => ['title'=>'销售员', 'width'=>20],
                    'B' => ['title'=>'订单数', 'width'=>10],
                    'C' => ['title'=>'售价CNY', 'width'=>15],
                    'D' => ['title'=>'平台费用CNY', 'width'=>15],
                    'E' => ['title'=>'物流费用', 'width'=>15],
                    'F' => ['title'=>'包装费用', 'width'=>15],
                    'G' => ['title'=>'头程报关费',   'width'=>15],
                    'H' => ['title'=>'商品成本',   'width'=>15],
                    'I' => ['title'=>'毛利', 'width'=>10],
                    'J' => ['title'=>'退款','width'=>10],
                    'K' => ['title'=>'账号年费',     'width'=>15],
                    'L' => ['title'=>'店铺费用',     'width'=>15],
                    'M' => ['title'=>'实际利润',  'width'=>15],
                    'N' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'sale_user'         => ['col'=>'A', 'type' => 'str'],
                    'order_num'         => ['col'=>'B', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'C', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'D', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'E', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'F', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'G', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'H', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'I', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'J', 'type' => 'numeric'],
                    'account_fee'       => ['col'=>'K', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'L', 'type' => 'numeric'],
                    'profit'            => ['col'=>'M', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'N', 'type' => 'str'],
                ],
                'last_col'=>'N'
            ],
            'overseas' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'物流费用', 'width'=>15],
                    'H' => ['title'=>'包装费用', 'width'=>15],
                    'I' => ['title'=>'头程报关费',   'width'=>15],
                    'J' => ['title'=>'商品成本',   'width'=>15],
                    'K' => ['title'=>'毛利', 'width'=>10],
                    'L' => ['title'=>'退款','width'=>10],
                    'M' => ['title'=>'实际利润',  'width'=>15],
                    'N' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'G', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'H', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'I', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'J', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'K', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'L', 'type' => 'numeric'],
                    'profit'            => ['col'=>'M', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'N', 'type' => 'str'],
                ],
                'last_col'=>'N'
            ],
            'local' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'物流费用', 'width'=>15],
                    'H' => ['title'=>'包装费用', 'width'=>15],
                    'I' => ['title'=>'头程报关费',   'width'=>15],
                    'J' => ['title'=>'商品成本',   'width'=>15],
                    'K' => ['title'=>'毛利', 'width'=>10],
                    'L' => ['title'=>'退款','width'=>10],
                    'M' => ['title'=>'账号年费',     'width'=>15],
                    'N' => ['title'=>'店铺费用',     'width'=>15],
                    'O' => ['title'=>'实际利润',  'width'=>15],
                    'P' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'G', 'type' => 'numeric'],
                    'package_fee'       => ['col'=>'H', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'I', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'J', 'type' => 'numeric'],
                    'gross_profit'      => ['col'=>'K', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'L', 'type' => 'numeric'],
                    'account_fee'       => ['col'=>'M', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'N', 'type' => 'numeric'],
                    'profit'            => ['col'=>'O', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'P', 'type' => 'str'],
                ],
                'last_col'=>'P'
            ],
        ],
        'ebay' => [
            'account' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'平台费用CNY', 'width'=>15],
                    'H' => ['title'=>'PayPal费用',     'width'=>15],
                    'I' => ['title'=>'货币转换费',     'width'=>15],
                    'J' => ['title'=>'物流费用', 'width'=>15],
                    'K' => ['title'=>'包装费用', 'width'=>15],
                    'L' => ['title'=>'头程报关费',   'width'=>15],
                    'M' => ['title'=>'商品成本',   'width'=>15],
                    'N' => ['title'=>'毛利', 'width'=>10],
                    'O' => ['title'=>'退款','width'=>10],
                    'P' => ['title'=>'店铺费用',     'width'=>15],
                    'Q' => ['title'=>'实际利润',  'width'=>15],
                    'R' => ['title'=>'利润率','width'=>10],
                    'S' => ['title'=>'呆货成本补贴','width'=>15],
                    'T' => ['title'=>'补贴后利润','width'=>15],
                    'U' => ['title'=>'补贴后利润率','width'=>15]
                ],
                'data' => [
                    'account_code'            => ['col'=>'A', 'type' => 'str'],
                    'sale_user'               => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader'       => ['col'=>'C', 'type' => 'str'],
                    'sale_director'           => ['col'=>'D', 'type' => 'str'],
                    'order_num'               => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'             => ['col'=>'F', 'type' => 'numeric'],
                    'channel_cost'            => ['col'=>'G', 'type' => 'numeric'],
                    'paypal_fee'              => ['col'=>'H', 'type' => 'numeric'],
                    'currency_transform_fee'  => ['col'=>'I', 'type' => 'numeric'],
                    'shipping_fee'            => ['col'=>'J', 'type' => 'numeric'],
                    'package_fee'             => ['col'=>'K', 'type' => 'numeric'],
                    'first_fee'               => ['col'=>'L', 'type' => 'numeric'],
                    'goods_cost'              => ['col'=>'M', 'type' => 'numeric'],
                    'gross_profit'            => ['col'=>'N', 'type' => 'numeric'],
                    'refund_amount'           => ['col'=>'O', 'type' => 'numeric'],
                    'shop_fee'                => ['col'=>'P', 'type' => 'numeric'],
                    'profit'                  => ['col'=>'Q', 'type' => 'numeric'],
                    'profit_rate'             => ['col'=>'R', 'type' => 'str'],
                    'cost_subsidy'            => ['col'=>'S', 'type' => 'numeric'],
                    'after_subsidy_profits'   => ['col'=>'T', 'type' => 'numeric'],
                    'after_subsidy_profits_rate'  => ['col'=>'U', 'type' => 'numeric'],
                ],
                'last_col'=>'U'
            ],
            'seller' =>  [
                'title' => [
                    'A' => ['title'=>'销售员', 'width'=>20],
                    'B' => ['title'=>'订单数', 'width'=>10],
                    'C' => ['title'=>'售价CNY', 'width'=>15],
                    'D' => ['title'=>'平台费用CNY', 'width'=>15],
                    'E' => ['title'=>'PayPal费用',     'width'=>15],
                    'F' => ['title'=>'货币转换费',     'width'=>15],
                    'G' => ['title'=>'物流费用', 'width'=>15],
                    'H' => ['title'=>'包装费用', 'width'=>15],
                    'I' => ['title'=>'头程报关费',   'width'=>15],
                    'J' => ['title'=>'商品成本',   'width'=>15],
                    'K' => ['title'=>'毛利', 'width'=>10],
                    'L' => ['title'=>'退款','width'=>10],
                    'M' => ['title'=>'店铺费用',     'width'=>15],
                    'N' => ['title'=>'实际利润',  'width'=>15],
                    'O' => ['title'=>'利润率','width'=>10],
                    'P' => ['title'=>'呆货成本补贴','width'=>15],
                    'Q' => ['title'=>'补贴后利润','width'=>15],
                    'R' => ['title'=>'补贴后利润率','width'=>15]
                ],
                'data' => [
                    'sale_user'               => ['col'=>'A', 'type' => 'str'],
                    'order_num'               => ['col'=>'B', 'type' => 'numeric'],
                    'sale_amount'             => ['col'=>'C', 'type' => 'numeric'],
                    'channel_cost'            => ['col'=>'D', 'type' => 'numeric'],
                    'paypal_fee'              => ['col'=>'E', 'type' => 'numeric'],
                    'currency_transform_fee'  => ['col'=>'F', 'type' => 'numeric'],
                    'shipping_fee'            => ['col'=>'G', 'type' => 'numeric'],
                    'package_fee'             => ['col'=>'H', 'type' => 'numeric'],
                    'first_fee'               => ['col'=>'I', 'type' => 'numeric'],
                    'goods_cost'              => ['col'=>'J', 'type' => 'numeric'],
                    'gross_profit'            => ['col'=>'K', 'type' => 'numeric'],
                    'refund_amount'           => ['col'=>'L', 'type' => 'numeric'],
                    'shop_fee'                => ['col'=>'M', 'type' => 'numeric'],
                    'profit'                  => ['col'=>'N', 'type' => 'numeric'],
                    'profit_rate'             => ['col'=>'O', 'type' => 'str'],
                    'cost_subsidy'            => ['col'=>'P', 'type' => 'numeric'],
                    'after_subsidy_profits'   => ['col'=>'Q', 'type' => 'numeric'],
                    'after_subsidy_profits_rate'  => ['col'=>'R', 'type' => 'str'],
                ],
                'last_col'=>'R'
            ],
            'overseas' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'平台费用CNY', 'width'=>15],
                    'H' => ['title'=>'PayPal费用',     'width'=>15],
                    'I' => ['title'=>'货币转换费',     'width'=>15],
                    'J' => ['title'=>'物流费用', 'width'=>15],
                    'K' => ['title'=>'包装费用', 'width'=>15],
                    'L' => ['title'=>'头程报关费',   'width'=>15],
                    'M' => ['title'=>'商品成本',   'width'=>15],
                    'N' => ['title'=>'毛利', 'width'=>10],
                    'O' => ['title'=>'退款','width'=>10],
                    'P' => ['title'=>'店铺费用',     'width'=>15],
                    'Q' => ['title'=>'实际利润',  'width'=>15],
                    'R' => ['title'=>'利润率','width'=>10],
                    'S' => ['title'=>'呆货成本补贴','width'=>15],
                    'T' => ['title'=>'补贴后利润','width'=>15],
                    'U' => ['title'=>'补贴后利润率','width'=>15]
                ],
                'data' => [
                    'account_code'            => ['col'=>'A', 'type' => 'str'],
                    'sale_user'               => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader'       => ['col'=>'C', 'type' => 'str'],
                    'sale_director'           => ['col'=>'D', 'type' => 'str'],
                    'order_num'               => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'             => ['col'=>'F', 'type' => 'numeric'],
                    'channel_cost'            => ['col'=>'G', 'type' => 'numeric'],
                    'paypal_fee'              => ['col'=>'H', 'type' => 'numeric'],
                    'currency_transform_fee'  => ['col'=>'I', 'type' => 'numeric'],
                    'shipping_fee'            => ['col'=>'J', 'type' => 'numeric'],
                    'package_fee'             => ['col'=>'K', 'type' => 'numeric'],
                    'first_fee'               => ['col'=>'L', 'type' => 'numeric'],
                    'goods_cost'              => ['col'=>'M', 'type' => 'numeric'],
                    'gross_profit'            => ['col'=>'N', 'type' => 'numeric'],
                    'refund_amount'           => ['col'=>'O', 'type' => 'numeric'],
                    'shop_fee'                => ['col'=>'P', 'type' => 'numeric'],
                    'profit'                  => ['col'=>'Q', 'type' => 'numeric'],
                    'profit_rate'             => ['col'=>'R', 'type' => 'str'],
                    'cost_subsidy'            => ['col'=>'S', 'type' => 'numeric'],
                    'after_subsidy_profits'   => ['col'=>'T', 'type' => 'numeric'],
                    'after_subsidy_profits_rate'  => ['col'=>'U', 'type' => 'numeric'],
                ],
                'last_col'=>'U'
            ],
            'local ' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>15],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'平台费用CNY', 'width'=>15],
                    'H' => ['title'=>'PayPal费用',     'width'=>15],
                    'I' => ['title'=>'货币转换费',     'width'=>15],
                    'J' => ['title'=>'物流费用', 'width'=>15],
                    'K' => ['title'=>'包装费用', 'width'=>15],
                    'L' => ['title'=>'头程报关费',   'width'=>15],
                    'M' => ['title'=>'商品成本',   'width'=>15],
                    'N' => ['title'=>'毛利', 'width'=>10],
                    'O' => ['title'=>'退款','width'=>10],
                    'P' => ['title'=>'店铺费用',     'width'=>15],
                    'Q' => ['title'=>'实际利润',  'width'=>15],
                    'R' => ['title'=>'利润率','width'=>10],
                    'S' => ['title'=>'呆货成本补贴','width'=>15],
                    'T' => ['title'=>'补贴后利润','width'=>15],
                    'U' => ['title'=>'补贴后利润率','width'=>15]
                ],
                'data' => [
                    'account_code'            => ['col'=>'A', 'type' => 'str'],
                    'sale_user'               => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader'       => ['col'=>'C', 'type' => 'str'],
                    'sale_director'           => ['col'=>'D', 'type' => 'str'],
                    'order_num'               => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'             => ['col'=>'F', 'type' => 'numeric'],
                    'channel_cost'            => ['col'=>'G', 'type' => 'numeric'],
                    'paypal_fee'              => ['col'=>'H', 'type' => 'numeric'],
                    'currency_transform_fee'  => ['col'=>'I', 'type' => 'numeric'],
                    'shipping_fee'            => ['col'=>'J', 'type' => 'numeric'],
                    'package_fee'             => ['col'=>'K', 'type' => 'numeric'],
                    'first_fee'               => ['col'=>'L', 'type' => 'numeric'],
                    'goods_cost'              => ['col'=>'M', 'type' => 'numeric'],
                    'gross_profit'            => ['col'=>'N', 'type' => 'numeric'],
                    'refund_amount'           => ['col'=>'O', 'type' => 'numeric'],
                    'shop_fee'                => ['col'=>'P', 'type' => 'numeric'],
                    'profit'                  => ['col'=>'Q', 'type' => 'numeric'],
                    'profit_rate'             => ['col'=>'R', 'type' => 'str'],
                    'cost_subsidy'            => ['col'=>'S', 'type' => 'numeric'],
                    'after_subsidy_profits'   => ['col'=>'T', 'type' => 'numeric'],
                    'after_subsidy_profits_rate'  => ['col'=>'U', 'type' => 'numeric'],
                ],
                'last_col'=>'U'
            ],
        ],
        'fba' => [
            'account' =>  [
                'title' => [
                    'A' => ['title'=>'账号简称',   'width'=>20],
                    'B' => ['title'=>'销售员', 'width'=>20],
                    'C' => ['title'=>'销售组长',     'width'=>20],
                    'D' => ['title'=>'销售主管',   'width'=>20],
                    'E' => ['title'=>'订单数', 'width'=>10],
                    'F' => ['title'=>'售价CNY', 'width'=>15],
                    'G' => ['title'=>'平台费用CNY', 'width'=>15],
                    'H' => ['title'=>'物流费用', 'width'=>15],
                    'I' => ['title'=>'头程报关费',   'width'=>15],
                    'J' => ['title'=>'商品成本',   'width'=>15],
                    'K' => ['title'=>'退款','width'=>10],
                    'L' => ['title'=>'店铺费用',     'width'=>15],
                    'M' => ['title'=>'利润',  'width'=>10],
                    'N' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'account_code'      => ['col'=>'A', 'type' => 'str'],
                    'sale_user'         => ['col'=>'B', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'C', 'type' => 'str'],
                    'sale_director'     => ['col'=>'D', 'type' => 'str'],
                    'order_num'         => ['col'=>'E', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'F', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'G', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'H', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'I', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'J', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'K', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'L', 'type' => 'numeric'],
                    'profit'            => ['col'=>'M', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'N', 'type' => 'str'],
                ],
                'last_col'=>'N'
            ],
            'seller ' =>  [
                'title' => [
                    'A' => ['title'=>'销售员', 'width'=>20],
                    'B' => ['title'=>'销售组长',     'width'=>20],
                    'C' => ['title'=>'销售主管',   'width'=>20],
                    'D' => ['title'=>'订单数', 'width'=>10],
                    'E' => ['title'=>'售价CNY', 'width'=>15],
                    'F' => ['title'=>'平台费用CNY', 'width'=>15],
                    'G' => ['title'=>'物流费用', 'width'=>15],
                    'H' => ['title'=>'头程报关费',   'width'=>15],
                    'I' => ['title'=>'商品成本',   'width'=>15],
                    'J' => ['title'=>'退款','width'=>10],
                    'K' => ['title'=>'店铺费用',     'width'=>15],
                    'L' => ['title'=>'利润',  'width'=>10],
                    'M' => ['title'=>'利润率','width'=>10]
                ],
                'data' => [
                    'sale_user'         => ['col'=>'A', 'type' => 'str'],
                    'sale_group_leader' => ['col'=>'B', 'type' => 'str'],
                    'sale_director'     => ['col'=>'C', 'type' => 'str'],
                    'order_num'         => ['col'=>'D', 'type' => 'numeric'],
                    'sale_amount'       => ['col'=>'E', 'type' => 'numeric'],
                    'channel_cost'      => ['col'=>'F', 'type' => 'numeric'],
                    'shipping_fee'      => ['col'=>'G', 'type' => 'numeric'],
                    'first_fee'         => ['col'=>'H', 'type' => 'numeric'],
                    'goods_cost'        => ['col'=>'I', 'type' => 'numeric'],
                    'refund_amount'     => ['col'=>'J', 'type' => 'numeric'],
                    'shop_fee'          => ['col'=>'K', 'type' => 'numeric'],
                    'profit'            => ['col'=>'L', 'type' => 'numeric'],
                    'profit_rate'       => ['col'=>'M', 'type' => 'str'],
                ],
                'last_col'=>'M'
            ],
        ]
    ];
    
    protected function init()
    {
        if (is_null($this->model)) {
            $this->model = new ReportStatisticByDeepsModel();
        }
    }

    /**
     * 搜索参数
     * @param array $params
     * @return array
     */
    public function getWhere(array $params)
    {
        $where = [];
        switch ($params['report_type']) {
            case 'local':
                $where['r.warehouse_type'] = ['eq', 1];//本地仓
                break;
            case 'overseas':
                $where['r.warehouse_type'] = ['eq', 3];//海外仓
                break;
            default:
                break;
        }
        #平台id搜索
        if(param($params, 'channel_id')){
            $where['r.channel_id'] = ['eq',$params['channel_id']];
        }else{
            //fba
            $where['r.warehouse_type'] = 5;
        }
        
        #账号id搜索
        if(param($params, 'account_id')){
            $where['r.account_id'] = ['eq',$params['account_id']];
        }
        
        #销售员搜索(要修改)       
        if(param($params, 'saler_id')){
            $where['r.user_id'] = ['eq',$params['saler_id']];
        }
        
        #按照发货日期 搜索
        if(param($params, 'search_time') && in_array($params['search_time'], ['shipping_time'])){  
            //switch ($params['search_time']){
            //    case 'shipping_time':
            //       $search_time = 'dateline';
            //        break;
            //    default:
            //        break;
            //}
                       
            $b_time = !empty(param($params, 'date_b'))?strtotime($params['date_b'].' 00:00:00'):'';
            $e_time = !empty(param($params, 'date_e'))?strtotime($params['date_e'].' 23:59:59'):'';
            
            if($b_time && $e_time){
                $where['r.dateline']  =  ['BETWEEN', [$b_time, $e_time]];
                //$where['o.shipping_time']  =  ['BETWEEN', [$b_time, $e_time]];
            }elseif ($b_time) {
                $where['r.dateline']  = ['EGT',$b_time];
                //$where['o.shipping_time']  = ['EGT',$b_time];
            }elseif ($e_time) {
                $where['r.dateline']  = ['ELT',$e_time];
                //$where['o.shipping_time']  = ['ELT',$e_time];
            }
            
        }
        return $where;
    }

    /**
     * @param string
     * @return array
     */
    /*public function getJoin($report_type)
    {
        if($report_type=='seller'){
            //销售员利润汇总
            $join[] = ['order o', 'o.seller_id = r.user_id', 'left'];
        }else{
            //销售账号利润汇总（包括海外、本地）
            $join[] = ['order o', 'o.channel_account_id = r.account_id', 'left'];
        }
        return $join;
    }*/

    /**
     * @param string
     * @return string
     */
    public function getGroupBy($report_type)
    {
        if($report_type=='seller'){
            //销售员利润汇总
            $group_by = "r.user_id";
        }else{
            //销售账号利润汇总（包括海外、本地）
            $group_by = 'r.account_id';
        }
        return $group_by;
    }

    /**
     * 查询销售账号利润报表
     * @param array $params
     * @return array
     */
    public function search($params)
    {
        $page  = param($params,'page',1);
        $pageSize  = param($params,'pageSize',20);

        $lists = $this->assemblyData($this->doSearch($params, $page, $pageSize), $params['report_type']);
        $count = $this->searchCount($params);

        $result = [
            'data' => $lists,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];

        return $result;
    }

    /**
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return false|\PDOStatement|string|\think\Collection
     */
    protected function doSearch($params, $page=1, $pageSize=10)
    {
        $lists = ReportStatisticByDeepsModel::alias('r')
//            ->join($this->getJoin($params['report_type']))
              ->field(
                'r.account_id, '.    //账号
                'r.channel_id,'.     //渠道
                'r.department_id,'.  //部门
                'sum(r.paypal_fee) as paypal_fee,'.   //paypal费用
                'sum(r.channel_cost) as channel_cost,'. //平台费用
                'sum(r.shipping_fee) as shipping_fee,'. //运费（物流费）
                'sum(r.package_fee) as package_fee,'.   //包装费用
                'sum(r.first_fee) as first_fee,'.       //头程费
                'sum(r.refund_amount) as refund_amount,'. //退款金额
                'sum(r.delivery_quantity) as order_num,'.  //订单发货数
                'sum(r.p_fee) as p_fee,'.    //P卡费用 CNY
                'sum(r.profits) as profits,'.    //利润
                'sum(r.sale_amount) as sale_amount,'.   //售价CNY
                'sum(r.channel_cost) as channel_cost,'.  //渠道成交费CNY
                'sum(r.cost) as goods_cost,'.  //成本
                'r.user_id as seller_id'             //销售员id
            )
            ->where($this->getWhere($params))
            ->group($this->getGroupBy($params['report_type']))
            ->page($page, $pageSize)
            ->select();
        return $lists;
    }

    /**
     * @param array $params
     * @return int|string
     */
    public function searchCount($params)
    {
        $count = ReportStatisticByDeepsModel::alias('r')
            ->where($this->getWhere($params))
            ->group($this->getGroupBy($params['report_type']))
            ->count();
        return $count;
    }

    /**
     * 获取销售员组长以及主管
     * @param $userId
     * @return array
     * @throws \think\exception\DbException
     */
    public function getLeaderDirector($userId)
    {
        $data = [];
        $departmentUserMapService = new DepartmentUserMapService();
        $department_ids = $departmentUserMapService->getDepartmentByUserId($userId);
        $director_id = [];
        $leader_id = [];
        foreach ($department_ids as $d => $department) {
            if(!empty($department)){
                    $_leader_id = $departmentUserMapService->getGroupLeaderByChannel($department);
                    if(!empty($_leader_id)){
                        foreach($_leader_id as $id){
                            array_push($leader_id, $id);
                        }
                    }
                    $_director_id = $departmentUserMapService->getDirector($department);
                    if(!empty($_director_id)){
                        foreach($_director_id as $id){
                            array_push($director_id, $id);
                        }
                    }
                }
        }
        $leader = [];
        foreach ($leader_id as $v) {
            $realname = cache::store('user')->getOneUserRealname($v);
            array_push($leader, $realname);
        }
        $director = [];
        foreach ($director_id as $v) {
            $realname = cache::store('user')->getOneUserRealname($v);
            array_push($director, $realname);
        }
        $data['sale_group_leader'] = !empty($leader) ? implode(', ', $leader) : '';
        $data['sale_director'] = !empty($director) ? implode(', ', $director): '';
        return $data;
    }

    /**
     * 组装查询返回数据
     * @param array $records
     * @param string $report_type
     * @return array
     */
    protected function assemblyData($records, $report_type)
    {
        //$UserService = new UserService();
        $ChannelAccountService = new ChannelAccount();
        $MembershipService = new MemberShipService();
        //$DepartmentService = new Department();
        foreach ($records as &$vo) {
            $vo = $vo->toArray();
            if ($report_type == 'seller') {
                //销售员利润汇总
                //$where['seller_id'] = $vo['seller_id'];
                //$where['channel_id'] = $vo['channel_id'];

                //获取销售员
                $sale_user = Cache::store('user')->getOneUser($vo['seller_id']);
                $vo['sale_user'] = $sale_user['realname'] ?? '';
            } else {
                $seller_id = $vo['seller_id'];
                //销售账号利润汇总（包括海外、本地）
                //$where['channel_account_id'] = $vo['account_id'];
                //$where['channel_id'] = $vo['channel_id'];

                //获取卖家账号
                $account = $ChannelAccountService->getAccount($vo['channel_id'] , $vo['account_id']);
                $vo['account_code'] = param($account, 'code');
                //获取销售员
                $member = $MembershipService->member($vo['channel_id'], $vo['account_id'], 'sales');
                $has_seller = 0;
                $sales = [];
                if ($member) {
                    foreach ($member as $mvo){
                        if($mvo['seller_id'] == $seller_id){
                            $has_seller = 1;
                        }
                        $sales[] = param($mvo, 'realname');
                        //获取销售员
                        if (isset($mvo['realname'])) {
                            $user_id = (new UserModel())->field('id')->where(['realname' => $mvo['realname']])->find();
                            if(!empty($user_id)){
                                $vo['seller_id'] = $user_id['id'];
                            }
                        }

                    }
                }
                if($has_seller == 0){
                    $sales[] = Cache::store('user')->getOneUser($seller_id)['realname'] ?? '';
                }
                $vo['sale_user'] = $sales ? implode(',', $sales) : '';
            }
            //$vo['goods_cost'] = $orderModel->where($where)->value('sum(cost)', 0);
            //获取账号分组信息
            //$department = $DepartmentService->getDepartment($vo['department_id']);
            //$vo['sale_group_leader'] = param($department, 'leader_id'); //销售组长(要修改)
            //获取销售主管
            //$vo['sale_director'] = "";
            //if(param($department, 'pid')){
            //    $user = $UserService->getUser($department['pid']);
            //    $vo['sale_director'] = param($user, 'realname');
            //}
            $vo['sale_group_leader'] = '';
            $vo['sale_director'] = '';
            $user = $this->getLeaderDirector($vo['seller_id']);
            if(!empty($user)){
                $vo['sale_group_leader'] = $user['sale_group_leader'];
                $vo['sale_director'] = $user['sale_director'];
            }
            $vo['paypal_fee'] = sprintf('%.2f', $vo['paypal_fee']);//paypal费用
            $vo['channel_cost'] = sprintf('%.2f', $vo['channel_cost']);//平台费用
            $vo['shipping_fee'] = sprintf('%.2f', $vo['shipping_fee']);//运费（物流费）
            $vo['package_fee'] = sprintf('%.2f', $vo['package_fee']);//包装费用
            $vo['first_fee'] = sprintf('%.2f', $vo['first_fee']);//头程费
            $vo['refund_amount'] = sprintf('%.2f', $vo['refund_amount']);//退款金额
            $vo['p_fee'] = sprintf('%.2f', $vo['p_fee']);//P卡费用 CNY
            $vo['profits'] = sprintf('%.2f', $vo['profits']);//利润
            $vo['goods_cost'] = sprintf('%.2f', $vo['goods_cost']);//商品成本
            $vo['sale_amount'] = sprintf('%.2f', $vo['sale_amount']);//售价CNY
            $vo['channel_cost'] = sprintf('%.2f', $vo['channel_cost']);//渠道成交费CNY

            $vo['appraisal_fee'] = 0; //测评费用（没给）
            $vo['ads_fee'] = 0; //广告费用（没给）
            $vo['shop_fee'] = 0; //店铺费用（没给）
            $vo['account_fee'] = 0; //账号年费（没给）
            $vo['fine'] = 0; //罚款（没给）
            $vo['cash_rebate'] = 0; //活动现金返利（没给）
            $vo['cost_subsidy'] = 0; //呆货成本补贴（没给）
            $vo['after_subsidy_profits'] = 0; //补贴后利润（没给）
            $vo['after_subsidy_profits_rate'] = 0; //补贴后利润率（没给）

            //实际售价(售价+测评费用) ？
            $vo['actual_fee'] = $vo['sale_amount']+$vo['appraisal_fee'];

            //毛利
            $vo['gross_profit'] = $vo['profits'];

            //实际利润(毛利-店铺费用-广告费用-退款) ？
            $vo['profit'] = $vo['gross_profit'] - $vo['shop_fee'] -$vo['ads_fee'] - $vo['refund_amount'];

            //利润率（实际利润÷实际售价）？
            $vo['profit_rate'] = $vo['actual_fee']!=0 ? sprintf('%.2f', $vo['profit']/$vo['actual_fee']*100).'%' : '0.00%';

            //货币转换率（总售价-渠道成交费-PayPal费用）×0.025 ？
            $vo['currency_transform_fee'] = sprintf('%.2f',($vo['sale_amount'] - $vo['channel_cost']-$vo['paypal_fee'])*0.025);
        }
        return $records;
    }

    /**
     * 获取参数
     * @param array $params
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getParameter(array $params , $key,$default)
    {
        $v = $default;
        if (isset($params[$key]) && $params[$key]) {
            $v = $params[$key];
        }
        return $v;
    }

    /**
     * 创建导出文件名
     * @param int $channel_id
     * @return string $report_type
     * @return int $user_id
     * @throws Exception
     */
    protected function createExportFileName($params,$channel_id, $report_type, $user_id)
    {
        $ChannelAccountService = new ChannelAccount();    
        $fileName = '';
        $report_str = '';
        switch ($report_type) {
            case 'account';
                $report_str = '销售账号利润汇总';
                break;
            case 'seller':
                $report_str = '销售员利润汇总';
                break;
            case 'overseas':
                $report_str = '海外仓利润汇总';
                break;
            case 'local':
                $report_str = '本地仓利润汇总';
                break;
            default:throw new Exception('不支持的汇总类型');
        }
        switch ($channel_id) {
            case 0:
                $fileName = 'fba'.$report_str.'报表';
                break;
            case 1:
                $fileName = 'ebay'.$report_str.'报表';
                break;
            case 2:
                $fileName = '亚马逊平台'.$report_str.'报表';
                break;
            case 3:
                $fileName = 'WISH平台'.$report_str.'报表';
                break;
            case 4:
                $fileName = '速卖通平台'.$report_str.'报表';
                break;
            default:throw new Exception('不支持的平台');
        }
        //确保文件名称唯一
        $lastID  = (new ReportExportFiles())->order('id desc')->value('id');
        $fileName .= ($lastID+1);
        if(isset($params['channel_id']) && isset($params['account_id']) && $params['account_id'] && $params['channel_id']){
            $account = $ChannelAccountService->getAccount($params['channel_id'] , $params['account_id']);
            $accountCode = param($account, 'code');
            $fileName .= '_'.$accountCode;
        }
        if(isset($params['seller_id']) && $params['seller_id']){
            $userName = Cache::store('user')->getOneUser($params['seller_id'])['realname'];
            $fileName .= '_'.$userName;
        }
        $start_time = $params['date_b'] ?$params['date_b'] : '';
        $end_time = $params['date_e'] ?$params['date_e'] : '';
        if ($start_time && $end_time) {
            $fileName .= '_'.$start_time.'_'.$end_time;
        }
        $fileName .= '.xlsx';
        return $fileName;
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
        try{
            $userId = Common::getUserInfo()->toArray()['user_id'];
            $cacher = Cache::handler();
            $lastApplyTime = $cacher->hget('hash:export_performance_apply',$userId);
            if ($lastApplyTime && time() - $lastApplyTime < 5) {
                throw new Exception('请求过于频繁',400);
            } else {
                $cacher->hset('hash:export_performance_apply',$userId,time());
            }
            //if(!isset($params['channel_id']) || (trim($params['channel_id']) == '' && $params['channel_id'])){
            //    throw new Exception('平台未设置',400);
            //}
            if (!isset($params['report_type']) || trim($params['report_type']) == '') {
                throw new Exception('汇总类型未设置',400);
            }
            
            $model = new ReportExportFiles();
            $model->applicant_id     = $userId;
            $model->apply_time       = time();
            $model->export_file_name = $this->createExportFileName($params,$params['channel_id'], $params['report_type'], $model->applicant_id);
            $model->status =0;
            if (!$model->save()) {
                throw new Exception('导出请求创建失败',500);
            }
            $params['file_name'] = $model->export_file_name;
            $params['apply_id'] = $model->id;
            $queuer = new CommonQueuer(PerformanceExportQueue::class);
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
                    $params['apply_id'].'_'.time(),
                    $ex->getMessage());
                throw new Exception('导出请求创建失败',500);
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
        try{
            ini_set('memory_limit','1024M');
            $validate = new FileExportValidate();
            if (!$validate->scene('export')->check($params)) {
                throw new Exception($validate->getError());
            }

            $downLoadDir = '/download/performance/';
            $saveDir = ROOT_PATH.'public'.$downLoadDir;
            if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true)) {
                throw new Exception('导出目录创建失败');
            }
            $fullName = $saveDir.$params['file_name'];
            //创建excel对象
            $exceler = new \PHPExcel();
            $exceler->setActiveSheetIndex(0);
            $sheet = $exceler->getActiveSheet();
            $titleRowIndex = 1;
            $dataRowStartIndex = 2;
            $titleMap  = [];
            $lastCol   = 'AA';
            $dataMap   = [];
            switch ($params['channel_id']) {
                case 0:
                    $titleMap = $this->colMap['fba'][$params['report_type']]['title'];
                    $dataMap  = $this->colMap['fba'][$params['report_type']]['data'];
                    $lastCol  = $this->colMap['fba'][$params['report_type']]['last_col'];
                    break;
                case 1:
                    $titleMap = $this->colMap['ebay'][$params['report_type']]['title'];
                    $dataMap  = $this->colMap['ebay'][$params['report_type']]['data'];
                    $lastCol  = $this->colMap['ebay'][$params['report_type']]['last_col'];
                    break;
                case 2:
                    $titleMap = $this->colMap['amazon'][$params['report_type']]['title'];
                    $dataMap  = $this->colMap['amazon'][$params['report_type']]['data'];
                    $lastCol  = $this->colMap['amazon'][$params['report_type']]['last_col'];
                    break;
                case 3:
                    $titleMap = $this->colMap['wish'][$params['report_type']]['title'];
                    $dataMap  = $this->colMap['wish'][$params['report_type']]['data'];
                    $lastCol  = $this->colMap['wish'][$params['report_type']]['last_col'];
                    break;
                case 4:
                    $titleMap = $this->colMap['aliExpress'][$params['report_type']]['title'];
                    $dataMap  = $this->colMap['aliExpress'][$params['report_type']]['data'];
                    $lastCol  = $this->colMap['aliExpress'][$params['report_type']]['last_col'];
                    break;
            }
            //设置表头和表头样式
            foreach ($titleMap as $col => $set) {
                $sheet->getColumnDimension($col)->setWidth($set['width']);
                $sheet->getCell($col . $titleRowIndex)->setValue($set['title']);
                $sheet->getStyle($col . $titleRowIndex)
                    ->getFill()
                    ->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E8811C');
                $sheet->getStyle($col . $titleRowIndex)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
            }
            $sheet->setAutoFilter('A1:'.$lastCol.'1');
            //统计需要导出的数据行
            $count    = $this->searchCount($params);
            $pageSize = 10000;
            $loop = ceil($count/$pageSize);
            //分批导出
            for($i = 0; $i<$loop; $i++) {
                $data = $this->assemblyData($this->doSearch($params, $i+1, $pageSize), $params['report_type']);
                foreach ($data as $r) {
                    foreach ($dataMap as $field => $set) {
                        $cell = $sheet->getCell($set['col']. $dataRowStartIndex);
                        switch ($set['type']) {
                            case 'time_stamp':
                                if (empty($r[$field])) {
                                    $cell->setValue('');
                                } else {
                                    $cell->setValue(date('Y-m-d',$r[$field]));
                                }
                                break;
                            case 'numeric':
                                $cell->setDataType(\PHPExcel_Cell_DataType::TYPE_NUMERIC);
                                if (empty($r[$field])) {
                                    $cell->setValue(0);
                                } else {
                                    $cell->setValue($r[$field]);
                                }
                                break;
                            default:
                                if (is_null($r[$field])) {
                                    $r[$field] = '';
                                }
                                $cell->setValue($r[$field]);
                                break;
                        }
                    }
                    $dataRowStartIndex++;
                }
            }
            $writer = \PHPExcel_IOFactory::createWriter($exceler,'Excel2007');
            $writer->save($fullName);
            if (is_file($fullName)) {
                $applyRecord = ReportExportFiles::get($params['apply_id']);
                $applyRecord->exported_time = time();
                $applyRecord->download_url = $downLoadDir.$params['file_name'];
                $applyRecord->status = 1;
                $applyRecord->isUpdate()->save();
            } else {
                throw new Exception('文件写入失败');
            }
        } catch (\Exception $ex) {
            $applyRecord = ReportExportFiles::get($params['apply_id']);
            $applyRecord->status = 2;
            $applyRecord->error_message = $ex->getMessage();
            $applyRecord->isUpdate()->save();
            Cache::handler()->hset(
                'hash:report_export',
                $params['apply_id'].'_'.time(),
                '申请id: '.$params['apply_id'].',导出失败:'.$ex->getMessage());
        }
    }



}