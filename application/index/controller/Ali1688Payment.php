<?php
/**
 * Created by PhpStorm.
 * User: huangjintao
 * Date: 2019/3/20
 * Time: 17:15
 */

namespace app\index\controller;


use app\common\controller\Base;
use app\index\service\Ali1688PaymentService;
use think\Request;

class Ali1688Payment extends Base
{
    /**
     * @title 付款申请单线上支付
     * @author huangjintao
     * @method post
     * @url /ali1688payment/online-payment
     * @return \think\response\Json
     */
    public function onlinePayment()
    {
        $request = Request::instance();
        $params = $request->param();
        $ali1688PaymentService = new Ali1688PaymentService();
        $result = $ali1688PaymentService->payment($params);
        return json($result,200);
    }
}