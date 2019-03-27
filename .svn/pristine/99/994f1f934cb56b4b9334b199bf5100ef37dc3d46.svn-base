<?php

namespace app\test\controller;

use service\amazon\Report\ReportService;
use app\common\cache\Cache;

/**
 * @module 亚马逊报告测试
 * @title 亚马逊报告测试
 * @description 接口说明
 * @url /amazon-report
 */
class AmazonReport
{
    
    /*
     * 获取亚马逊账号
     */
    private function getAccount(){
        
//         $id = '438';
//         $id = '478';//chulaius
//         $id = '479';//chulaica
        $id = '480';//chulaiuk
        
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
            'mws_auth_token'=>$accRow['auth_token'],
        ];
        
        return $config;
    }
    
    /**
     * @title 创建报告请求
     * @url /requestReport
     * @return \think\Response
     */
    public function requestReport(){
        /*
         * 1、获取账号数据
         */
        $config = $this->getAccount();
        $token_id = $config["token_id"];
        $token = $config["token"];
        $saller_id = $config["saller_id"];
        $site = $config["site"];
        $mws_auth_token = $config['mws_auth_token'];
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new ReportService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $ReportType = '_GET_MERCHANT_LISTINGS_DATA_BACK_COMPAT_';
        $StartDate = '';
        $EndDate = '';
        $ReportOptions = '';
        $MarketplaceIdList = array();
        $re = $obj->requestReport($ReportType, $StartDate, $EndDate, $ReportOptions, $MarketplaceIdList);
        print_r($re);
        die;
    }
    
    /**
     * @title 查询报告处理状态
     * @url /getReportRequestLists
     * @return \think\Response
     */
    public function getReportRequestList(){
        
        /*
         * 1、获取账号数据
         */
        $config = $this->getAccount();
        $token_id = $config["token_id"];
        $token = $config["token"];
        $saller_id = $config["saller_id"];
        $site = $config["site"];
        $mws_auth_token = $config['mws_auth_token'];
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new ReportService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $RequestedFromDate = '';
        $RequestedToDate = '';
        $ReportRequestIdList = array(
                    '101371017870'
        );
        $ReportTypeList = array(
//             '_GET_MERCHANT_LISTINGS_DATA_BACK_COMPAT_'
        );
        $ReportProcessingStatusList = array();
        $MaxCount = 100;
        
        $re = $obj->getReportRequestList(
            $RequestedFromDate,
            $RequestedToDate,
            $ReportRequestIdList,
            $ReportTypeList,
            $ReportProcessingStatusList,
            $MaxCount);
        
        print_r($re);
        die;
    }
    
    /**
     * @title 下载报告内容
     * @url /getReport
     * @return \think\Response
     */
    public function getReport(){
        
        /*
         * 1、获取账号数据
         */
        $config = $this->getAccount();
        $token_id = $config["token_id"];
        $token = $config["token"];
        $saller_id = $config["saller_id"];
        $site = $config["site"];
        $mws_auth_token = $config['mws_auth_token'];
        
        /*
         * 2、实例化接口服务类
         */
        $obj = new ReportService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $ReportId = '13827040302017870';
        $re = $obj->getReport($ReportId);
        print_r($re);
        die;
    }
    
}