<?php

namespace app\publish\queue;

/**
 * 曾绍辉
 * 17-8-5
 * ebay图片上传队列
*/
use app\common\model\ebay\EbayListingSetting;
use app\common\service\CommonQueueJob;
use app\common\exception\TaskException;
use app\publish\service\EbayListingCommonHelper;
use think\Db;
use app\common\service\SwooleQueueJob;
use app\common\cache\Cache;
use service\ebay\EbayApi;
use think\cache\driver;
use app\index\service\AbsTasker;
use app\common\service\UniqueQueuer;
use app\common\model\ebay\EbayListing;
use app\common\model\ebay\EbayListingImage;
use think\Exception;

class EbayImgQueuer extends SwooleQueueJob
{
    public static function swooleTaskMaxNumber():int
    {
        return 4;
    }

    public function getName():string
    {
        return 'ebay图片上传至EPS';
    }

    public function getDesc():string
    {
        return 'ebay图片上传至EPS';
    }

    public function getAuthor():string
    {
        return 'wlw2533';
    }

    public  function execute()
    {
        $ebayListingMod = new EbayListing();
//        $ebayImageMod = new EbayListingImage();
        set_time_limit(0);
        $listingId = $this->params;
        try{
//            if(!$listingId) {
//                throw new \Exception('参数listingId获取失败');
//            }
            #$list = Db::name("ebay_listing")->where(['id'=>$listingId])->find();
            $list = $ebayListingMod->where(['id'=>$listingId])->find();
            if(!$list) {
                throw new \Exception('list信息获取失败');
            }
            $accountInfo = Cache::store('EbayAccount')->getAccountById($list['account_id']);
            (new EbayListingCommonHelper())->uploadImgToEPS($listingId, $accountInfo, $list['site']);
            $ebayListingMod->where(['id'=>$list['id']])->update(['listing_status'=>1]);
            $pubQueuer = new UniqueQueuer(EbayPublishItemQueuer::class);
            //加入刊登队列
            if($list['timing']==0){//立即刊登
                $res = $pubQueuer->push($listingId);
            }else{//定时刊登
                $res = $pubQueuer->push($listingId,$list['timing']);
            }



            #获取账号token
            #$t = Cache::store('account')->ebayAccount($list['account_id']);#获取账号信息
//            $t = Cache::store('EbayAccount')->getTableRecord($list['account_id']);
//            $t = Cache::store('EbayAccount')->getAccountById($list['account_id']);
//            $tokenArr = json_decode($t['token'],true);
//            $token = trim($tokenArr[0])?$tokenArr[0]:$t['token'];
//
//            $config['devID']=$t['dev_id'];//开发者id
//            $config['appID']=$t['app_id'];//开发者client_id
//            $config['certID']=$t['cert_id'];//开发者
//            $config['userToken']=$token;//用户安全证书
//            $config['compatLevel']=957;//兼容级别
//            $config['siteID']=0;//站点id
//            $config['verb']="UploadSiteHostedPictures";//callname
//            $config['appMode']=0;//正式环境
//            $config['account_id']=$t['id'];//账号id
//            $ebayApi = new EbayApi($config);
//
//            #$imgs = Db::name("ebay_listing_image")->where(['listing_id'=>$list['id'],'status'=>0])->select();
//            $imgs = $ebayImageMod->where(['listing_id'=>$list['id'],'status'=>0])->select();
//            if(!$imgs){#图片为空 说明所需的图片已上传过了，更新listing的创建时间
//                $ebayListingMod->where(['id'=>$list['id']])->update(['create_date'=>time()]);
//            }
//            foreach($imgs as $k => $v){
//                $xml = $this->createXml($v,$token);
//                #echo $xml;
//                $resText = $ebayApi->createHeaders()->__set("requesBody",$xml)->sendHttpRequest2();
//                // echo "<pre>";
//                // print_r($resText);
//                $res = $resText["UploadSiteHostedPicturesResponse"];
//                if($res["Ack"]=="Failure"){#图片上传失败
//                    $errStr = $res['Errors'];
//                    if(isset($errStr[0])){
//                        $error=$errStr;
//                    }else{
//                        $error=array($errStr);
//                    }
//
//                    $msg=array();
//                    foreach($error as $val){
//                        if($val["SeverityCode"]=="Error"){
//                            $msg[]=$val["ErrorCode"]."：".$val["LongMessage"];
//                        }
//                    }
//                    $errorMsg='';
//                    if(empty($msg)){
//                        $errorMsg=json_encode($errStr);
//                    }else{
//                        $errorMsg=implode("\n",$msg);
//                    }
//                    $ebayImageMod->where(['id'=>$v['id']])->update(['status'=>0,'update_time'=>time(),'message'=>$errorMsg]);
//                }else{
//                    if(isset($res["SiteHostedPictureDetails"]["FullURL"])){
//                        $ebayImageMod->where(['id'=>$v['id']])
//                        ->update(['status'=>3,'update_time'=>time(),'eps_path'=>$res["SiteHostedPictureDetails"]["FullURL"]]);
//                    }
//                }
//            }

//            $tolCount = $ebayImageMod->where(['listing_id'=>$list['id']])->count();#所有图片数量
//            $count = $ebayImageMod->where(['listing_id'=>$list['id'],'status'=>3])->count();
//            #上传成功的图片数量
//            if($tolCount == $count){#所有图片上传至eps
//                $ebayListingMod->where(['id'=>$list['id']])->update(['listing_status'=>1]);
//                $pubQueuer = new UniqueQueuer(EbayPublishItemQueuer::class);
//                #加入刊登队列
//                if($list['timing']==0){#立即刊登
//                    $pubQueuer->push($listingId);
//                }else{#定时刊登
//                    $pubQueuer->push($listingId,$list['timing']);
//                }
//            }else{
//                $ebayListingMod->where(['id'=>$list['id']])->update(['listing_status'=>0]);
//                #重新压入图片队列
//                $queuer = new UniqueQueuer(EbayImgQueuer::class);
//                $queuer->push($list['id']);
//            }
        }catch(Exception $exp){
            if ($listingId) {
                EbayListing::update(['listing_status'=>4], ['id'=>$listingId]);
                EbayListingSetting::update(['message'=>$exp->getMessage()], ['id'=>$listingId]);
            }
            throw new Exception($exp->getMessage());
        }
    }

    #创建xml
//    public function createXml($img,$token)
//    {
/*       $xml ="<?xml version='1.0' encoding='utf-8'?>\n";*/
//       $xml.="<UploadSiteHostedPicturesRequest xmlns='urn:ebay:apis:eBLBaseComponents'>\n";
//       $xml.="<RequesterCredentials>\n";
//        $xml.="<eBayAuthToken>".$token."</eBayAuthToken>\n";#账号token
//        $xml.="</RequesterCredentials>\n";
//        $xml.="<ExternalPictureURL>".$img['ser_path']."</ExternalPictureURL>\n";#图片地址
//        $xml.="<WarningLevel>High</WarningLevel>\n";
//        $xml.="</UploadSiteHostedPicturesRequest>\n";
//        return $xml;
//    }

}