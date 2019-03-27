<?php
/**
 * Created by PhpStorm.
 * User: rondaful_user
 * Date: 2018/10/10
 * Time: 19:49
 */

namespace app\publish\task;


use app\common\model\ebay\EbayListing;
use app\common\model\ebay\EbayListingVariation;
use app\index\service\AbsTasker;
use think\Exception;

class EbayPublishSPUStatistics extends AbsTasker
{
    public function getName()
    {
        return 'eBay刊登SPU上下架统计';
    }

    public function getDesc()
    {
        return 'eBay刊登SPU上下架统计';
    }

    public function getCreator()
    {
        return 'wlw2533';
    }

    public function getParamRule()
    {
        return [];
    }
    
    public function execute()
    {
        try {

            $todayTime = strtotime(date('Y-m-d', time())) - 1;
            $yesterday = $todayTime + 1 - 86400;
            $wh['start_date'] = ['BETWEEN', [$yesterday, $todayTime]];

            //$todayTime = strtotime(date('Y-m-d', time()));
            //$wh['start_date'] = ['gt', $todayTime];


            $wh['application'] = 1;
            $wh['listing_status'] = 3;
            $wh['draft'] = 0;
            $field = 'goods_id,account_id,realname,count(goods_id) as spu_count,start_date';
            $group = 'goods_id,account_id,realname';
            //统计上架的
            $listings = EbayListing::field($field)->where($wh)->group($group)->select();

            foreach ($listings as $listing) {
                if (empty($listing['realname']) || empty($listing['account_id'])
                    || empty($listing['goods_id']) || empty($listing['spu_count'])) {
                    continue;
                }
                \app\report\service\StatisticShelf::addReportShelf(1,
                                                                    $listing['account_id'],
                                                                    $listing['realname'],
                                                                    $listing['goods_id'],
                                                                    $listing['spu_count'],
                                                                        0,
                                                                    $listing['start_date']
                                                                     );
            }

           //统计下架的
            unset($wh['start_date']);
            $wh['listing_status'] = ['in',[9,11]];
            $wh['manual_end_time'] = ['gt', $todayTime];
            $whOr['end_date'] = ['between', [$todayTime,$todayTime+86400]];
            $listings = EbayListing::field($field)->where($wh)->whereOr($whOr)->group($group)->select();
            //调用接口
            foreach ($listings as $listing) {
                if (empty($listing['realname']) || empty($listing['account_id'])
                    || empty($listing['goods_id']) || empty($listing['spu_count'])) {
                    continue;
                }
                \app\report\service\StatisticPicking::addReportPicking(1,
                    $listing['account_id'],
                    $listing['realname'],
                    $listing['goods_id'],
                    $listing['spu_count']);
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

}