<?php
/**
 * Created by PhpStorm.
 * User: wlw2533
 * Date: 2019/1/18
 * Time: 11:31
 */

namespace app\publish\task;


use app\common\model\ebay\EbayListing;
use app\index\service\AbsTasker;
use app\common\model\ebay\EbayListingSetting as ELSMysql;
use app\common\model\mongodb\ebay\EbayListingSetting as ELSMongo;
use think\Exception;

class EbayListingSettingMoveMysqlToMongodb extends AbsTasker
{
    public function getName()
    {
        return "ebay setting表从mysql移到mongodb";
    }

    public function getDesc()
    {
        return "ebay setting表从mysql移到mongodb";
    }

    public function getCreator()
    {
        return "wlw2533";
    }

    public function getParamRule()
    {
        return [];
    }


    public function execute()
    {
        try {
            //保险起见，每次执行前获取已转移的最大id
            $id = ELSMongo::order('id', 'desc')->value('id');
            $id = $id ?: 0;
            //先查询主表，避免转移一些垃圾信息
            $wh = [
                'draft' => 0,//范本不移
                'id' => ['>', $id],
            ];
            $ids = EbayListing::where($wh)->order('id')->limit(1000)->column('id');

            //获取设置信息
            $settings = ELSMysql::whereIn('id', $ids)->select();
            if (!$settings) {
                return;
            }
            $settings = collection($settings)->toArray();
            //mysql里面值为null的字段存储到mongodb会变成字符串的 "NULL"，存储前进行处理
            foreach ($settings as &$setting) {
                foreach ($setting as &$set) {
                    if (is_null($set)) {
                        $set = '';
                    }
                }
            }
            ELSMongo::insertAll($settings);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

}