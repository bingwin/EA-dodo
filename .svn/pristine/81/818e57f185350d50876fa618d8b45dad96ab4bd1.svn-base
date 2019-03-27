<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Date: 17-12-15
 * Time: 下午2:30
 */

namespace app\publish\task;

use app\common\service\UniqueQueuer;
use app\common\exception\TaskException;
use app\common\model\amazon\AmazonUpOpenLog as AmazonUpOpenLogModel;
use app\index\service\AbsTasker;
use app\publish\queue\AliexpressSkuMapQueue;
use think\Db;
use app\common\cache\Cache;
use think\Exception;
use app\common\model\amazon\AmazonListing;
use app\publish\service\AmazonCategoryXsdConfig;
use app\publish\queue\AmazonTimerUpLowerQueuer;


class AmazonUpLowerPush extends AbsTasker
{
    public function getName()
    {
        return "Amazon定时上下架推送";
    }

    public function getDesc()
    {
        return "Amazon定时上下架推送";
    }

    public function getCreator()
    {
        return "hao";
    }

    public function getParamRule()
    {
        return [];
    }


    public function execute()
    {
        set_time_limit(0);
        try {
            $page=1;
            $pageSize=100;

            $start_time = strtotime(date('Y-m-d H:00'));

            $where=[
                'is_up_open' => ['=',0],
                'up_open_time' => ['=',$start_time],
            ];

            do{
                $upOpenLogModel = new AmazonUpOpenLogModel();

                $amazonListingModel = new AmazonListing;

                $upOpenLog = $upOpenLogModel->where($where)->page($page,$pageSize)->select();

                if(empty($upOpenLog))
                {
                    break;
                }else{

                    $where = [];
                    foreach ($upOpenLog as $key => $val){
                        $val = is_object($val)?$val->toArray():$val;

                        //更新加入队列字段
                        $upOpenLogModel->isUpdate(true)->save(['is_up_open' => 1], ['id' => $val['id']]);


                        if($amazonListingModel->field('id')->where(['id' => $val['listing_id'], 'seller_sku' => $val['seller_sku'], 'seller_type' => 2])->find()){


                            $data = [
                                'id' => $val['listing_id'],
                                'seller_sku' => $val['seller_sku'],
                                'seller_status' => $val['seller_status'],
                                'up_open_time' => $start_time
                            ];

                            //加入定时下架消息队列
                            (new UniqueQueuer(AmazonTimerUpLowerQueuer::class))->push($data);
                        }
                    }

                    $page = $page + 1;
                }

            }while(count($upOpenLog)==$pageSize);

        }catch (Exception $exp){
            throw new TaskException($exp->getMessage());
        }
    }
}