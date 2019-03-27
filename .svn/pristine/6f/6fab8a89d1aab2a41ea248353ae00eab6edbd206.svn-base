<?php

namespace app\test\controller;

use service\amazon\Order\OrderService;
use app\common\cache\Cache;

/**
 * @module 亚马逊报告测试
 * @title 亚马逊报告测试
 * @description 接口说明
 * @url /amazon-order
 */
class AmazonOrder
{
    
    /*
     * 获取亚马逊账号
     */
    private function getAccount(){
//         $id = '438';
//         $id = '478';//chulaius
//         $id = '479';//chulaica
//         $id = '502';//chulaiuk
        $id = '427';//lurdajp
        
        $accRow = Cache::store('AmazonAccount')->getTableRecord($id);
        if(!$accRow){
            echo 'AmazonAccount not exist!!!';
            exit;
        }
        $config = [
            'token_id'=>$accRow['access_key_id'],
            'token'=>$accRow['secret_key'],
            'saller_id'=>$accRow['merchant_id'],
            'site'=>$accRow['site'],
            'mws_auth_token'=>'',
        ];
        //如果有第三方授权，使用第三方授权
        $developer_token_id = paramNotEmpty($accRow, 'developer_access_key_id','');
        $developer_token = paramNotEmpty($accRow, 'developer_secret_key','');
        $auth_token = paramNotEmpty($accRow, 'auth_token','');
        if($developer_token_id && $developer_token && $auth_token){
            $config['token_id'] = $developer_token_id;
            $config['token'] = $developer_token;
            $config['mws_auth_token'] = $auth_token;
        }
        return array_values($config);
    }
    
    /**
     * @title 返回“订单 API”部分的运行状态
     * @url /getServiceStatus
     * @return \think\Response
     */
    public function getServiceStatus(){
        /*
         * 1、获取账号数据
         */
        list($token_id,$token,$saller_id,$site,$mws_auth_token) = $this->getAccount();
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new OrderService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $re = $obj->getServiceStatus();
        print_r($re);
        die;
    }
    
    /**
     * @title 返回您在指定时间段内所创建或更新的订单
     * @url /listOrders
     * @return \think\Response
     */
    public function listOrders(){
        /*
         * 1、获取账号数据
         */
        list($token_id,$token,$saller_id,$site,$mws_auth_token) = $this->getAccount();
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new OrderService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $Params = [
            'LastUpdatedAfter'=>'2019-01-01 17:11:36',
            'LastUpdatedBefore'=>'2019-01-22 17:13:36',
        ];
        $re = $obj->listOrders($Params);
        print_r($re);
        die;
    }
    
    /**
     * @title 使用 NextToken 参数返回下一页订单
     * @url /listOrdersByNextToken
     * @return \think\Response
     */
    public function listOrdersByNextToken(){
        /*
         * 1、获取账号数据
         */
        list($token_id,$token,$saller_id,$site,$mws_auth_token) = $this->getAccount();
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new OrderService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $NextToken = '1u55Nki/EvR/YYuZQbKv1QafEPREmauvizt1MIhPYZZYegLd/TCzqps6sZdY2rn983HaHR9/MViHNkPWD9qzA98ytOVgN7d/KyNtf5fepe3iG+vCyuJg6hanQHYOdTc+S+0zfa1p/rlwwUVaIJ5xpvyiYL2UhJuAao9WevXbE9+nMvTcb5wXR17TMh5o5U17y+9VsJ0xnRi1asCu7VgoNhwl6dqw20m1MMvThwZlrFkn+AwiN87vnp+lUkZL6+7mkUcxfLpcvmVlkZZjJNnRdtlx/IhUNqB+HGGtljBBkTiLdbAGTjFVaOrSyOslVIrxCJYwrpjsuvYbqlXCnHcknHyPbJw5zbKYLkBlYC71jRXH2ey5bttU0QoUrMQNwCe+mPUwd45Xb/n0W99K0Nf5cg1IG6e3fQh1ZZqwiE6q877pqlaZ7PGAEEt2Lw7UY/n6wuifyJCgGbVFhEiH1Ef/lk+gdUoHs1tXrU7ISRBeznxL2YlKtQV6flhxEPWhgxiN';
        $re = $obj->listOrdersByNextToken($NextToken);
        print_r($re);
        die;
    }
    
    /**
     * @title 根据您指定的 AmazonOrderId 值返回订单
     * @url /getOrder
     * @return \think\Response
     */
    public function getOrder(){
        /*
         * 1、获取账号数据
         */
        list($token_id,$token,$saller_id,$site,$mws_auth_token) = $this->getAccount();
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new OrderService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $AmazonOrderId = '249-9684225-7785444';
        $re = $obj->getOrder($AmazonOrderId);
        print_r($re);
        die;
    }
    
    /**
     * @title 根据您指定的 AmazonOrderId 返回订单商品
     * @url /listOrderItems
     * @return \think\Response
     */
    public function listOrderItems(){
        /*
         * 1、获取账号数据
         */
        list($token_id,$token,$saller_id,$site,$mws_auth_token) = $this->getAccount();
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new OrderService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $AmazonOrderId = '249-9684225-7785444';
        $re = $obj->listOrderItems($AmazonOrderId);
        print_r($re);
        die;
    }
    
}