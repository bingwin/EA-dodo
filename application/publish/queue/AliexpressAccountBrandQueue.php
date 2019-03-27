<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Date: 18-5-4
 * Time: 上午11:38
 */

namespace app\publish\queue;


use app\common\cache\Cache;
use app\common\exception\QueueException;
use app\common\model\aliexpress\AliexpressCategory;
use app\common\service\SwooleQueueJob;
use app\publish\service\AliexpressTaskHelper;
use think\Exception;

class AliexpressAccountBrandQueue extends SwooleQueueJob
{
    protected static $priority=self::PRIORITY_HEIGHT;
    /**
     * @doc 失败时下次处理秒数
     * @var int
     */
    protected $failExpire = 600;
    /**
     * @doc 失败最大重新处理次数
     * @var int
     */
    protected $maxFailPushCount = 3;

    public static function swooleTaskMaxNumber():int
    {
        return 3;
    }
    public function getName(): string
    {
        return '速卖通账号授权品牌队列';
    }

    public function getDesc(): string
    {
        return '速卖通账号授权品牌队列';
    }

    public function getAuthor(): string
    {
        return 'joy';
    }

    public function execute()
    {
         set_time_limit(0);
         try{
            $params = $this->params;
            if($params){
                list($acount_id,$category_id)=explode('|',$params);
                $account = Cache::store('AliexpressAccount')->getAccountById($acount_id);
                if($account && $category_id){
                    $categorys = self::getChildsByPid($category_id);
                    if($categorys){
                        $this->getBrandsByCategory($account,$categorys);
                    }
                }
            }
         }catch (Exception $exp){
             throw new QueueException($exp->getMessage());
         }
    }
    private function getBrandsByCategory($config,$categorys){
        try{
            $service = new AliexpressTaskHelper();
            foreach ($categorys as $category){
                $service->getAeBrandAttribute($config,$category);
            }
        }catch (\Throwable $exp){
            throw new QueueException($exp->getMessage());
        }
    }
    /**
     * 获取所有子分类
     * @param $cate
     * @param $pid
     * @return array
     */
    public static function getChildsByPid($pid,&$return=[],&$leafNodes=[])
    {
        $model = new AliexpressCategory();
        $categorys = $model->field('category_id,category_pid,category_isleaf,category_name_zh')->where('category_pid',$pid)->select();
        if($categorys){
            foreach ($categorys as $category){
                $category = $category->toArray();
                if($category['category_isleaf']){
                    $leafNodes[]=$category;
                }
                $return[] = $category;
                self::getChildsByPid($category['category_id'],$return,$leafNodes);
            }
        }else{
            $category['category_id']=$pid;
            $leafNodes[]=$category;
        }
        return $leafNodes;
    }
}