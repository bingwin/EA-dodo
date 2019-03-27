<?php

namespace app\common\model;

use app\common\service\Common;
use app\index\service\AccountApplyService;
use think\Cache;
use think\Model;

/**
 * Created by PhpStorm.
 * User: libaimin
 * Date: 2019/2/19
 * Time: 11:47
 */
class ReturnWaitShelvesDetail extends Model
{
    //取消原因 1.销售取消单，未包装  2.销售取消单，已包装  3.物流渠道不支持  4.仓库少货等原因取消
    const cancel_reason_unpacked = 1;//
    const cancel_reason_packaging = 2;//
    const cancel_reason_channel_not_supported = 3;//
    const cancel_reason_warehouse_problem = 4;//

    const allCancelReason = [
        '',
        '销售取消单，未包装',
        '销售取消单，已包装',
        '物流渠道不支持',
        '仓库少货等原因取消',
    ];

    /**
     * 初始化
     */
    protected function initialize()
    {
        parent::initialize();
    }



    public function isHas($packageNumber)
    {
        $where['package_number'] = $packageNumber;
        return $this->where($where)->find();
    }

}