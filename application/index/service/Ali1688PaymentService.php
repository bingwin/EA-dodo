<?php
/**
 * Created by PhpStorm.
 * User: huangjintao
 * Date: 2019/3/20
 * Time: 17:20
 */

namespace app\index\service;


use app\common\exception\JsonErrorException;
use app\common\model\Ali1688Account;
use app\common\model\PurchaseOrder;

class Ali1688PaymentService
{
    public function payment($params)
    {
        $external_number_arr = $data = [];
        $purchase_order_ids = param($params, 'purchase_order_ids', '');
        $account_1688 = param($params, 'account_1688', '');
//        if (!$external_numbers || !is_array($external_number_arr = json_decode($external_numbers, true)) || empty($external_number_arr)) {
//            throw new JsonErrorException('外部流水号格式错误');
//        }
        if (!$purchase_order_ids || !is_array($purchase_order_id_arr = json_decode($purchase_order_ids, true)) || empty($purchase_order_id_arr)) {
            throw new JsonErrorException('采购单号格式错误');
        }
        $purchaseOrderModel = new PurchaseOrder();
        $ali1688AccountModel = new Ali1688Account();
        foreach ($purchase_order_id_arr as $purchase_order_id){
            $purchaseOrderInfo = $purchaseOrderModel->find($purchase_order_id);
            if (!$purchaseOrderInfo){
                throw new JsonErrorException('找不到采购单号对应数据:'.$purchase_order_id);
            }
            if ($purchaseOrderInfo['external_number']){
                $external_number_arr[] = $purchaseOrderInfo['external_number'];
            }
        }
        if (empty($external_number_arr)){
            throw new JsonErrorException('所有的对应采购单号没有外部流水号,不能进行线上付款');
        }
        $account1688Info = $ali1688AccountModel->where(['account_name' => $account_1688])->find();
        if (!$account1688Info){
            throw new JsonErrorException('找不到1688账号信息');
        }
//        $refresh_token = $account1688Info['refresh_token'];
//        $access_token = $account1688Info['access_token'];
//        $app_key = $account1688Info['client_id'];
//        $app_secret = $account1688Info['client_secret'];
        $paramToSign = '';
        foreach ($external_number_arr as $external_number){
            $paramToSign = $paramToSign ."'". $external_number."',";
        }
        $paramLength = strlen ( $paramToSign );
        if ($paramLength > 0) {
            $paramToSign = substr ( $paramToSign, 0, $paramLength - 1 );
        }
        $order_id = "[" . $paramToSign ."]";
        $refresh_token = '2b213199-918d-4b36-b7bc-9ef30f4ddf90';
        $access_token = '50a239f9-99a2-4411-8f90-da0d26063948';
        $app_key = '6683451';
        $app_secret= 'FHp29Uo9LjY';
        //$order_id = "['388729315544427573','388822307267427573']";
        $getAliPayUrlApi = new \aliy1688\GetAliPayUrl($order_id,$app_key,$app_secret,$refresh_token,$access_token);
        $result = $getAliPayUrlApi->request();
        if ($result['success'] == 'true'){
            $data = [
                'success' => $result['success'],
                'payUrl' => $result['payUrl'],
                'purchase_order_ids' => $purchase_order_id_arr,
            ];
        }else{
            $data = [
                'success' => $result['success'],
                'erroMsg' => $result['erroMsg'],
                'purchase_order_ids' => $purchase_order_id_arr,
            ];
        }
        return $data;
    }

    public function getPayType($external_number,$account_1688)
    {
        //$data = [];
        $ali1688AccountModel = new Ali1688Account();
        $account1688Info = $ali1688AccountModel->where(['account_name' => $account_1688])->find();
        if ($account1688Info){
            $refresh_token = $account1688Info['refresh_token'];
            $access_token = $account1688Info['access_token'];
            $app_key = $account1688Info['client_id'];
            $app_secret = $account1688Info['client_secret'];
//            $refresh_token = '2b213199-918d-4b36-b7bc-9ef30f4ddf90';
//            $access_token = '50a239f9-99a2-4411-8f90-da0d26063948';
//            $app_key = '6683451';
//            $app_secret= 'FHp29Uo9LjY';

            $getAliPayUrlApi = new \aliy1688\GetAliPayWay($external_number,$app_key,$app_secret,$refresh_token,$access_token);
            $result = $getAliPayUrlApi->request();
            if ($result['success'] == "true"){
                $payWays = [];
                foreach ($result['resultList']['channels'] as $payWay){
                    $payWays[] = $payWay['name'];
                }
                $payWayText = implode(',',$payWays);
                $data = [
                    'success' => $result['success'],
                    'payType' => $payWayText,
                    'external_number' => $result['resultList']['orderId'],
                    'payFee' => $result['resultList']['payFee']/100,
                ];
            }else{
                $data = [
                    'errorMsg' => $result['errorMsg'],
                    'payType' => '',
                    'external_number' => '',
                    'payFee' => '',
                ];
            }
        }else{
            $data = [
                'payType' => '--',
                'external_number' => '--',
                'payFee' => '--',
            ];
        }
        return $data;
    }
}