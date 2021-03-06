<?php

namespace app\test\controller;

use service\amazon\Inbound\InboundService;
use app\common\cache\Cache;
use service\amazon\Feed\InboundCartonFeedService;

/**
 * @module 亚马逊FBA调拨测试
 * @title 亚马逊FBA调拨测试
 * @description 接口说明
 * @url /amazon-inbound
 */
class AmazonInbound
{
    
    /*
     * 获取亚马逊账号
     */
    private function getAccount(){
        
        $id = '438';
        
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
     * step-1--创建入库货件计划
     * @title 创建入库货件计划
     * @url /createInboundShipmentPlan
     * @return \think\Response
     */
    public function createInboundShipmentPlan(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $ShipFromAddress = array(
            'Name'=>'Zaozhuang Telaer Shangmao Youxiangongsi',// Y 50  名称或公司名称
            'AddressLine1'=>'Xuechengqu Yanshanlu 602haomenshi',//Y 180  街道地址信息1
            'AddressLine2'=>'Shandongsheng, Zaozhuangshi',//N 60  街道地址信息2
            'City'=>'Zaozhuangshi',//Y 30  城市
//             'DistrictOrCounty'=>'',//N 25  区或县
//             'StateOrProvinceCode'=>'',//N 2 省/自治区/直辖市代码
            'CountryCode'=>'CN',//Y 2 国家/地区代码
            'PostalCode'=>'277099',//N 30 邮政编码
        );
        
        $InboundShipmentPlanRequestItems = array(
            0=>array(
                'SellerSKU'=>'FC0024201|zaoteFBA',//Y 	商品的卖家 SKU
                // 						'ASIN'=>'',//N 商品的亚马逊标准识别号 (ASIN)
            // 						'Condition'=>'',//N 商品的状况
                'Quantity'=>'30',//Y 商品数量
                // 						'QuantityInCase'=>'',// N 每个包装箱中的商品数量
            ),
            1=>array(
                'SellerSKU'=>'FC0024202|zaoteFBA',//Y 	商品的卖家 SKU
                // 						'ASIN'=>'',//N 商品的亚马逊标准识别号 (ASIN)
            // 						'Condition'=>'',//N 商品的状况
                'Quantity'=>'30',//Y 商品数量
                // 						'QuantityInCase'=>'',// N 每个包装箱中的商品数量
            ),
            2=>array(
                'SellerSKU'=>'FC0046600|zaoteFBA',//Y 	商品的卖家 SKU
                // 						'ASIN'=>'',//N 商品的亚马逊标准识别号 (ASIN)
                // 						'Condition'=>'',//N 商品的状况
                'Quantity'=>'30',//Y 商品数量
                // 						'QuantityInCase'=>'',// N 每个包装箱中的商品数量
            )
        );
        
        $ShipToCountryCode = 'UK';
        
        $LabelPrepPreference = 'SELLER_LABEL';
        
        $re = $obj->createInboundShipmentPlan($ShipFromAddress, $InboundShipmentPlanRequestItems, $ShipToCountryCode);
        
        print_r($re);
        die;
    }
    
    /**
     * step-2--创建入库货件
     * @title 创建入库货件
     * @url /createInboundShipment
     * @return \think\Response
     */
    public function createInboundShipment(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
//         $ShipmentId = 'FBA15C446MTW';
//         $ShipmentId = 'FBA15C46T46N';
//         $ShipmentId = 'FBA15C46XF1F';
//         $ShipmentId = 'FBA15C472RGT';
        $ShipmentId = 'FBA15CBSZG70';
        
        $InboundShipmentHeader = array(
            'ShipmentName'=>'FBA_test_1_2019-03-20',//Y 您为货件选择的名称。请使用命名规则
            'ShipFromAddress'=>array(
                'Name'=>'Zaozhuang Telaer Shangmao Youxiangongsi',// Y 50  名称或公司名称
                'AddressLine1'=>'Xuechengqu Yanshanlu 602haomenshi',//Y 180  街道地址信息1
                'AddressLine2'=>'Shandongsheng, Zaozhuangshi',//N 60  街道地址信息2
                'City'=>'Zaozhuangshi',//Y 30  城市
                //             'DistrictOrCounty'=>'',//N 25  区或县
            //             'StateOrProvinceCode'=>'',//N 2 省/自治区/直辖市代码
                'CountryCode'=>'CN',//Y 2 国家/地区代码
                'PostalCode'=>'277099',//N 30 邮政编码
            ),
            'DestinationFulfillmentCenterId'=>'LBA2',//Y 您的货件将运至的亚马逊配送中心的编号。可以从 CreateInboundShipmentPlan 操作返回的InboundShipmentPlans 响应元素获取此编号
            'LabelPrepPreference'=>'SELLER_LABEL',//Y 	入库货件的标签准备首选项:SELLER_LABEL、AMAZON_LABEL_ONLY、AMAZON_LABEL_PREFERRED、
            // 				'AreCasesRequired'=>'',//N  指明入库货件是否包含原厂包装发货商品
            'ShipmentStatus'=>'SHIPPED',//Y  入库货件的状态,枚举值
        );
        
        $InboundShipmentItems = array(
            0=>array(
//                 'ShipmentId'=>'',//N 货件编号
                'SellerSKU'=>'FC0024201|zaoteFBA',//Y 商品的卖家 SKU
//                 'FulfillmentNetworkSKU'=>'',//N 商品的亚马逊配送网络 SKU
                'QuantityShipped'=>'30',//Y 要配送的商品数量
//                 'QuantityReceived'=>'',//N 亚马逊配送中心已接收的商品数量
//                 'QuantityInCase'=>'',//N 每个包装箱中的商品数量（仅针对原厂包装发货商品）
            ),
            1=>array(
            //                 'ShipmentId'=>'',//N 货件编号
                'SellerSKU'=>'FC0024202|zaoteFBA',//Y 商品的卖家 SKU
                //                 'FulfillmentNetworkSKU'=>'',//N 商品的亚马逊配送网络 SKU
                'QuantityShipped'=>'30',//Y 要配送的商品数量
                //                 'QuantityReceived'=>'',//N 亚马逊配送中心已接收的商品数量
            //                 'QuantityInCase'=>'',//N 每个包装箱中的商品数量（仅针对原厂包装发货商品）
            ),
            2=>array(
                //                 'ShipmentId'=>'',//N 货件编号
                'SellerSKU'=>'FC0046600|zaoteFBA',//Y 商品的卖家 SKU
                //                 'FulfillmentNetworkSKU'=>'',//N 商品的亚马逊配送网络 SKU
                'QuantityShipped'=>'30',//Y 要配送的商品数量
                //                 'QuantityReceived'=>'',//N 亚马逊配送中心已接收的商品数量
                //                 'QuantityInCase'=>'',//N 每个包装箱中的商品数量（仅针对原厂包装发货商品）
            )
        );
        
        $re = $obj->createInboundShipment($ShipmentId,$InboundShipmentHeader,$InboundShipmentItems);
        
        print_r($re);
        die;
    }
    
    /**
     * step-3
     * @title 向亚马逊发送入库货件的运输信息
     * @url /putTransportContent
     * @return \think\Response
     */
    public function putTransportContent(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
//         $ShipmentId = 'FBA15C446MTW';
//         $ShipmentId = 'FBA15C46T46N';
//         $ShipmentId = 'FBA15C46XF1F';
//         $ShipmentId = 'FBA15C472RGT';
        $ShipmentId = 'FBA15CBSZG70';
        $Param_TransportDetails = array(
//             'PartneredSmallParcelData'=>array(
//                 'PackageList'=>array(
//                     0=>array(
//                         'Dimensions'=>array(//货件的尺寸
//                             'Length'=>100,//float
//                             'Width'=>101,//float
//                             'Height'=>102,//float
//                             'Unit'	=>'centimeters',//string
//                         ),
//                         'Weight'=>array(//货件的重量
//                             'Value'=>50,//float
//                             'Unit'=>'kilograms',//string
//                         ),
//                     ),
//                     2=>array(
//                         'Dimensions'=>array(//货件的尺寸
//                             'Length'=>103,//float
//                             'Width'=>104,//float
//                             'Height'=>105,//float
//                             'Unit'	=>'centimeters',//string
//                         ),
//                         'Weight'=>array(//货件的重量
//                             'Value'=>55,//float
//                             'Unit'=>'kilograms',//string
//                         ),
//                     )
//                 ),
//             ),
            'NonPartneredSmallParcelData'=>array(
                'CarrierName'=>'OTHER',
                'PackageList'=>array(
                    1=>array('TrackingId'=>'961493117179'),
                    2=>array('TrackingId'=>'961493117337'),
                    3=>array('TrackingId'=>'961493117338'),
//                     4=>array('TrackingId'=>'961493117339'),
//                     5=>array('TrackingId'=>'961493117340'),
//                     6=>array('TrackingId'=>'961493117341'),
//                     7=>array('TrackingId'=>'961493117342'),
                ),
            ),
            
        );
        $Param_IsPartnered = false;
        $Param_ShipmentType = 'SP';
        $re = $obj->putTransportContent($ShipmentId, $Param_TransportDetails,$Param_IsPartnered,$Param_ShipmentType);
        print_r($re);
        die;
    }
    
    /**
     * step-4
     * @title 提交纸箱内容信息
     * @url /postFbaInboundCartonContents
     * @return \think\Response
     */
    public function postFbaInboundCartonContents(){
        
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
        $obj = new InboundCartonFeedService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $Param_CartonsArray = [
            'FBA15CBSZG70'=>[
                [
                    'items'=>[
                        [
                            'SKU'=>'FC0024201|zaoteFBA',
                            'QuantityShipped'=>'30',
                            'QuantityInCase'=>'30',//每个包装箱中的商品数量
                            //                         'ExpirationDate'=>'',
                        ]
                    ]
                ],
                [
                    'items'=>[
                        [
                            'SKU'=>'FC0024202|zaoteFBA',
                            'QuantityShipped'=>'30',
                            'QuantityInCase'=>'30',
                            //                         'ExpirationDate'=>'',
                        ]
                    ]
                ],
                [
                    'items'=>[
                        [
                            'SKU'=>'FC0046600|zaoteFBA',
                            'QuantityShipped'=>'30',
                            'QuantityInCase'=>'30',
                            //                         'ExpirationDate'=>'',
                        ],
                    ]
                ],
                
            ]
        ];
        
        $re = $obj->submitInboundCartonByArray($Param_CartonsArray);
        
        print_r($re);
        die;
    }
    
    /**
     * step-4-2
     * @title 查询提交的纸箱内容信息处理结果
     * @url /getFeedSubmissionResult
     * @return \think\Response
     */
    public function getFeedSubmissionResult(){
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
        $obj = new InboundCartonFeedService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        $Param_FeedSubmissionId = '117984017976';
        $re = $obj->getFeedSubmissionResult($Param_FeedSubmissionId);
        print_r($re);
        die;
    }
    
    /**
     * step-5
     * @title 获取标签
     * @url /getPackageLabels
     * @return \think\Response
     */
    public function getPackageLabels(){
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        //         $ShipmentId = 'FBA15C446MTW';
        //         $ShipmentId = 'FBA15C46T46N';
//         $ShipmentId = 'FBA15C472RGT';
        $ShipmentId = 'FBA15CBSZG70';
        $PageType = 'PackageLabel_Plain_Paper';
        $NumberOfPackages = 3;
        $re = $obj->getPackageLabels($ShipmentId,$PageType,$NumberOfPackages);
        
        if($re['ask']){
            $TransportDocument = $re['data']['GetPackageLabelsResponse']['GetPackageLabelsResult']['TransportDocument'];
            $PdfDocument = $TransportDocument['PdfDocument'];
            $Checksum =  $TransportDocument['Checksum'];
            
            $file_name = $ShipmentId . '_' . $PageType . '.zip';
            
            $size = file_put_contents($file_name, base64_decode($PdfDocument));
            
            echo '保存文件：'.$size.'<br/>';
        }
        
        print_r($re);
        die;
    }
    
    /**
     * step-6 -- 标记为已发货
     * @title 更新入库货件
     * @url /updateInboundShipment
     * @return \think\Response
     */
    public function updateInboundShipment(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        //         $ShipmentId = 'FBA15C446MTW';
        //         $ShipmentId = 'FBA15C46T46N';
//         $ShipmentId = 'FBA15C46XF1F';
//         $ShipmentId = 'FBA15C472RGT';
        $ShipmentId = 'FBA15CBSZG70';
        
        $InboundShipmentHeader = array(
            'ShipmentName'=>'FBA_test_1_2019-03-20',//Y 您为货件选择的名称。请使用命名规则
            'ShipFromAddress'=>array(
                'Name'=>'Zaozhuang Telaer Shangmao Youxiangongsi',// Y 50  名称或公司名称
                'AddressLine1'=>'Xuechengqu Yanshanlu 602haomenshi',//Y 180  街道地址信息1
                'AddressLine2'=>'Shandongsheng, Zaozhuangshi',//N 60  街道地址信息2
                'City'=>'Zaozhuangshi',//Y 30  城市
                //             'DistrictOrCounty'=>'',//N 25  区或县
                //             'StateOrProvinceCode'=>'',//N 2 省/自治区/直辖市代码
                'CountryCode'=>'CN',//Y 2 国家/地区代码
                'PostalCode'=>'277099',//N 30 邮政编码
            ),
            'DestinationFulfillmentCenterId'=>'LBA2',//Y 您的货件将运至的亚马逊配送中心的编号。可以从 CreateInboundShipmentPlan 操作返回的InboundShipmentPlans 响应元素获取此编号
            'LabelPrepPreference'=>'SELLER_LABEL',//Y 	入库货件的标签准备首选项:SELLER_LABEL、AMAZON_LABEL_ONLY、AMAZON_LABEL_PREFERRED、
            // 				'AreCasesRequired'=>'',//N  指明入库货件是否包含原厂包装发货商品
            'ShipmentStatus'=>'SHIPPED',//Y  入库货件的状态,枚举值
        );
        
        $InboundShipmentItems = array(
            0=>array(
                //                 'ShipmentId'=>'',//N 货件编号
                'SellerSKU'=>'FC0024201|zaoteFBA',//Y 商品的卖家 SKU
                //                 'FulfillmentNetworkSKU'=>'',//N 商品的亚马逊配送网络 SKU
                'QuantityShipped'=>'30',//Y 要配送的商品数量
                //                 'QuantityReceived'=>'',//N 亚马逊配送中心已接收的商品数量
                //                 'QuantityInCase'=>'',//N 每个包装箱中的商品数量（仅针对原厂包装发货商品）
            ),
            1=>array(
                //                 'ShipmentId'=>'',//N 货件编号
                'SellerSKU'=>'FC0024202|zaoteFBA',//Y 商品的卖家 SKU
                //                 'FulfillmentNetworkSKU'=>'',//N 商品的亚马逊配送网络 SKU
                'QuantityShipped'=>'30',//Y 要配送的商品数量
                //                 'QuantityReceived'=>'',//N 亚马逊配送中心已接收的商品数量
                //                 'QuantityInCase'=>'',//N 每个包装箱中的商品数量（仅针对原厂包装发货商品）
            ),
            2=>array(
                //                 'ShipmentId'=>'',//N 货件编号
                'SellerSKU'=>'FC0046600|zaoteFBA',//Y 商品的卖家 SKU
                //                 'FulfillmentNetworkSKU'=>'',//N 商品的亚马逊配送网络 SKU
                'QuantityShipped'=>'30',//Y 要配送的商品数量
                //                 'QuantityReceived'=>'',//N 亚马逊配送中心已接收的商品数量
                //                 'QuantityInCase'=>'',//N 每个包装箱中的商品数量（仅针对原厂包装发货商品）
            )
                        
        );
        
        $re = $obj->updateInboundShipment($ShipmentId,$InboundShipmentHeader,$InboundShipmentItems);
        
        print_r($re);
        die;
    }

    ########################################################
    ########################################################
    ########################################################
    
    /**
     * @title 根据您指定的条件返回入库货件列表
     * @url /listInboundShipments
     * @return \think\Response
     */
    public function listInboundShipments(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $ShipmentStatusList = [];
        $ShipmentIdList = [
            'FBA15C446MTW',
            'FBA15C46T46N',
            'FBA15C4514YD',
            'FBA15C46XF1F',
            'FBA15C472RGT',
        ];
        $re = $obj->listInboundShipments($ShipmentStatusList, $ShipmentIdList);
        print_r($re);
        die;
    }
    
    /**
     * @title 下载入库货件数据（未发货前报错提示“没有与装运相关的承运人信息”）
     * @url /getTransportContent
     * @return \think\Response
     */
    public function getTransportContent(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        //         $ShipmentId = 'FBA15C4514YD';
        
        
//         $ShipmentId = 'FBA15C446MTW';
//         $ShipmentId = 'FBA15C46T46N';
        $ShipmentId = 'FBA15C472RGT';
        $re = $obj->getTransportContent($ShipmentId);
        print_r($re);
        die;
    }
    
    /**
     * @title 取消入库货件(仅限亚马逊合作承运人配送)
     * @url /voidTransportRequest
     * @return \think\Response
     */
//     public function voidTransportRequest(){
        
//         /*
//          * 1、获取账号数据
//          */
//         $config = $this->getAccount();
//         $token_id = $config["token_id"];
//         $token = $config["token"];
//         $saller_id = $config["saller_id"];
//         $site = $config["site"];
//         $mws_auth_token = $config['mws_auth_token'];
        
//         /*
//          * 2、实例化接口服务类
//          */
//         $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
//         /*
//          * 3、组装参数、调用接口
//          */
//         $Param_ShipmentId = 'FBAZZQB3W';
//         $re = $obj->voidTransportRequest($Param_ShipmentId);
//         print_r($re);
//         die;
//     }
    
    /**
     * @title 返回用于打印入库货件提单的 PDF 文档数据
     * @url /getBillOfLading
     * @return \think\Response
     */
    public function getBillOfLading(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        
        $ShipmentId = 'FBA15C446MTW';
        $re = $obj->getBillOfLading($ShipmentId);
        print_r($re);
        die;
    }
    
    /**
     * @title 请求入库货件的预计运费
     * @url /estimateTransportRequest
     * @return \think\Response
     */
    public function estimateTransportRequest(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $Param_ShipmentId = 'FBA15C446MTW';
        $re = $obj->estimateTransportRequest($Param_ShipmentId);
        print_r($re);
        die;
    }
    
    /**
     * @title 返回“配送入库货件 API”部分的运行状态
     * @url /getServiceStatus
     * @return \think\Response
     */
    public function getServiceStatus(){
        
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
        $obj = new InboundService($token_id, $token, $saller_id, $site, $mws_auth_token);
        
        /*
         * 3、组装参数、调用接口
         */
        $re = $obj->getServiceStatus();
        print_r($re);
        die;
    }
    
}