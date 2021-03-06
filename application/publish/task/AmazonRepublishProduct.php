<?php
namespace app\publish\task;

use app\common\cache\Cache;
use app\common\model\amazon\AmazonPublishProductDetail;
use app\common\model\amazon\AmazonPublishProductSubmission;
use app\common\service\UniqueQueuer;
use app\index\service\AbsTasker;
use app\publish\queue\AmazonPublishImageQueuer;
use app\publish\queue\AmazonPublishPriceQueuer;
use app\publish\queue\AmazonPublishProductQueuer;
use app\publish\queue\AmazonPublishProductResultQueuer;
use app\publish\queue\AmazonPublishQuantityQueuer;
use app\publish\queue\AmazonPublishRelationQueuer;
use app\publish\service\AmazonPublishConfig;
use app\common\model\amazon\AmazonPublishProduct as AmazonPublishProductModel;
use think\Db;
use think\Exception;


/**
 * @node Aamazon刊登断点刊登
 * Class AmazonRsyncListing
 * packing app\listing\task
 */
class AmazonRepublishProduct extends AbsTasker
{
    /**
     * 定义任务名称
     * @return string
     */
    public function getName()
    {
        return "amazon刊登-意外终断继续";
    }
    
    /**
     * 定义任务描述
     * @return string
     */
    public function getDesc()
    {
        return "amazon刊登-刊登意外终断时，重新刊登";
    }
    
    /**
     * 定义任务作者
     * @return string
     */
    public function getCreator()
    {
        return "冬";
    }

    /**
     * 定义任务参数规则
     * @return array
     */
    public function getParamRule()
    {
        return [];
    }

    public $time = 0;
    public $max_time = 86400 * 5;
    public $max_total = 1000;

    public $resultModel = null;
    public $productModel = null;
    public $detailModel = null;
    /** @var int 每分钟刊登10个帐号 */
    public $minutePublishNum = 10;

    /** @var int 参数在队列里达到这个时间秒还没执行，就清除 */
    public $clearParamTime = 14400;

    public function __construct()
    {
        $this->time = time();
        $this->max_time = 86400 * 5;
        $this->max_total = 500;
        $this->resultModel = new AmazonPublishProductSubmission();
        $this->productModel = new AmazonPublishProductModel();
        $this->detailModel = new AmazonPublishProductDetail();
    }


    /**
     * 任务执行内容
     * @return void
     */
    public  function execute()
    {

        //删除时间过长的日志文件；
        //$this->deleteLog();

        //模式2，使用销售员为参数来刊登；
        $this->execute2();
    }


    public function deleteLog()
    {
        //删除时间过长的日志文件；
        $this->resultModel->where(['create_time' => ['<', strtotime('-30 days')]])->delete();
    }


    /**
     * 任务执行内容-以帐号为主刊登
     * @return void
     */
    public  function execute1()
    {
        /**
         * @分析submission 会出现好几种情况；
         * 1. status = 0, 这种情况会自动刊登；
         * 2. status = 1, 刊登中，但是没有获取submission_id;
         * 3. status = 1, 已获取submission_id;
         * 4. status = 2, 这种不用问,已完成了；
         * 5. status = 3, 失败了，这种不用管
         */
        $typeQueueArr = [
            1 => AmazonPublishProductQueuer::class,
            2 => AmazonPublishRelationQueuer::class,
            3 => AmazonPublishQuantityQueuer::class,
            4 => AmazonPublishImageQueuer::class,
            5 => AmazonPublishPriceQueuer::class
        ];

        //设置刊登等级数组；
        $levelArr = $this->setLevelData($typeQueueArr);

        /** @var array $pushTypeArr 最终会循环推送到上面的队列里面去的，为防止重复，先集合到一起，再推  */
        $pushTypeArr = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => []
        ];

        $inZeroTime = $this->checkTime();

        //刊登完成未显示完成的完成；
        $this->updateFinish();
        //每次刊登或取值都会取刊登结果都会更新更新时间，所以，如果一个半小时内一直在刊登中，那说明被漏掉了，取出来检查一下；
        $this->handelAways();

        //1. status = 1,但是没有submission_id，超如10分钟，则主动把status改成0，让他去重新刊登；
        $this->resultModel->update(['status' => 0], [
            'status' => 1,
            'submission_id' => '',
            'create_time' => ['BETWEEN', [$this->time - 86000, $this->time - 600]]
        ]);

        //2. status = 1, 已取得submission_id, 超过20最后请求时间超过当前时间
        $subIds = $this->resultModel->where([
            'status' => 1,
            'submission_id' => ['<>', ''],
            'create_time' => ['BETWEEN', [$this->time - 86000, $this->time - 600]],
            'last_request_time' => ['BETWEEN', [$this->time - 86400, $this->time - 600]]
        ])->limit($this->max_total * 2)->order('id asc')->column('last_request_time', 'id');
        if (!empty($subIds)) {
            $queue = new UniqueQueuer(AmazonPublishProductResultQueuer::class);
            foreach ($subIds as $id=>$last_request_time) {
                if ($last_request_time < $this->time - $this->clearParamTime) {
                    $queue->remove($id);
                    $queue->push($id);
                } else {
                    $queue->push($id);
                }
            }
        }

        //3.找出status = 1, 不管有没有刊登过的,已过了24小时的，去创建新的数据；
        $subDatas = $this->resultModel->where([
            'status' => 1,
            'pids' => ['<>', ''],
            'create_time' => ['BETWEEN', [$this->time - 86400 * 2, $this->time - 86400]]
        ])->limit($this->max_total * 2)->order('id asc')->select();

        if (!empty($subDatas)) {
            $typeStatusArr = [1 => 'product_status', 2 => 'relation_status', 3 => 'quantity_status', 4 => 'image_status', 5 => 'price_status'];
            foreach ($subDatas as $data) {
                $tmp = $data->toArray();

                if (!in_array($tmp['type'], [1, 2, 3, 4, 5])) {
                    continue;
                }

                //找出还没有刊登完成的；
                $pidArr = explode(',', $tmp['pids']);
                $newPidArr = $this->productModel->where([
                    'id' => ['in', $pidArr],
                    $typeStatusArr[$tmp['type']] => ['<>', AmazonPublishConfig::PUBLISH_STATUS_FINISH]
                ])->column('id');
                if (empty($newPidArr)) {
                    $this->resultModel->update(['status' => 3], ['id' => $tmp['id']]);
                    continue;
                }

                $newsub = [
                    'pids' => implode(',', $newPidArr),
                    'total' => count($newPidArr),
                    'status' => 0,
                    'type' => $tmp['type'],    //刊登关系
                    'account_id' => $tmp['account_id'],
                    'submission_id' => '',
                    'create_time' => time(),
                ];

                try {
                    Db::startTrans();
                    //旧的数据作废；
                    $this->resultModel->update(['status' => 3], ['id' => $tmp['id']]);
                    //插入新的数据；
                    $this->resultModel->insertGetId($newsub);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    continue;
                }
                //放进刊登各类别的帐号数组
                $pushTypeArr[$tmp['type']] = $this->mergeTypeAccountArr(
                    $pushTypeArr[$tmp['type']],
                    [['account_id' => $tmp['account_id'], 'update_time' => strtotime('-1 day')]]
                );
            }
        }

        //4. 找出订时刊登了的帐号
        $products = $this->productModel->where([
            'publish_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
            'product_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
            'timer' => ['BETWEEN', [$this->time - $this->max_time, $this->time + 900]]
            ])->field('id,account_id')->limit($this->max_total)->select();
        $account_pids = [];
        foreach ($products as $val) {
            $account_pids[$val['account_id']][] = $val['id'];
        }

        foreach ($account_pids as $account_id=>$pidArr) {
            AmazonPublishProductModel::update(['publish_status' => 1], ['id' => ['in', $pidArr]]);
            $newsub = [
                'pids' => implode(',', $pidArr),
                'total' => count($pidArr),
                'status' => 0,
                'type' => 1,    //刊登关系
                'account_id' => $account_id,
                'submission_id' => '',
                'create_time' => time(),
            ];
            //插入新的数据；
            $this->resultModel->insertGetId($newsub);
            //订时刊登的数据，可以不受刊登等组限制，直接放进队列；
            (new UniqueQueuer(AmazonPublishProductQueuer::class))->push($account_id);
        }

        //5.重刊，找出待刊登的状态；
        //产品
        $typeIdArr[1] = $this->productModel->where([
                'publish_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'timer' => 0,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 600]]
            ])
            ->order('update_time', 'asc')
            ->field('id,account_id,update_time')
            ->limit($this->max_total * 2)->column('account_id,update_time', 'id');
        $pushTypeArr[1] = $this->mergeTypeAccountArr($pushTypeArr[1], $typeIdArr[1]);

        //关系
        $typeIdArr[2] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'relation_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 600]]
            ])
            ->order('update_time', 'asc')
            ->field('id,account_id,update_time')
            ->limit($this->max_total * 2)->column('account_id,update_time', 'id');
        $pushTypeArr[2] = $this->mergeTypeAccountArr($pushTypeArr[2], $typeIdArr[2]);
        //库存
        $typeIdArr[3] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'quantity_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 600]]
            ])
            ->order('update_time', 'asc')
            ->field('id,account_id,update_time')
            ->limit($this->max_total * 2)->column('account_id,update_time', 'id');
        $pushTypeArr[3] = $this->mergeTypeAccountArr($pushTypeArr[3], $typeIdArr[3]);
        //图片
        $typeIdArr[4] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'image_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 600]]
            ])
            ->order('update_time', 'asc')
            ->field('id,account_id,update_time')
            ->limit($this->max_total * 2)->column('account_id,update_time', 'id');
        $pushTypeArr[4] = $this->mergeTypeAccountArr($pushTypeArr[4], $typeIdArr[4]);
        //价格
        $typeIdArr[5] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'price_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 600]]
            ])
            ->order('update_time', 'asc')
            ->field('id,account_id,update_time')
            ->limit($this->max_total * 2)->column('account_id,update_time', 'id');
        $pushTypeArr[5] = $this->mergeTypeAccountArr($pushTypeArr[5], $typeIdArr[5]);
        unset($typeIdArr);

        //6. 找出status = 0, 但是创建时间已经起过10分钟了的，重新放进队列；
        $subDatas = $this->resultModel->where([
            'status' => 0,
            'pids' => ['<>', ''],
            'submission_id' => '',
            'create_time' => ['BETWEEN', [$this->time - 86400, $this->time - 300]]
        ])->field('id,total,type,account_id,last_request_time')->select();

        if (!empty($subDatas)) {
            //把帐号ID重新放进队列；
            foreach ($subDatas as $data) {
                if (!in_array($data['type'], [1, 2, 3, 4, 5])) {
                    continue;
                }
                if (!empty($pushTypeArr[$data['type']][$data['account_id']])) {
                    $old = $pushTypeArr[$data['type']][$data['account_id']];
                    $pushTypeArr[$data['type']][$data['account_id']] = [
                        'count' => $old['count'] + $data['total'],
                        'update_time' => min([$data['last_request_time'], $old['update_time']]),
                    ];
                } else {
                    $pushTypeArr[$data['type']][$data['account_id']] = [
                        'count' => $data['total'],
                        'update_time' => $data['last_request_time'],
                    ];
                }
            }
        }
        //product_status,relation_status,quantity_status,image_status,price_status

        /*
         * 刊登等级$level 与 帐号记录数量之间的刊登关系；
         * level是根据队列里面的数量来的；0-99条是1，随时可以提交，100-199是1；
         * level = 0 时，count 1条以上或 更新时间 10分钟以上就可以提交；
         * level = 1 时，count 2条以上或 更新时间 20分钟以上就可以提交；
         * level = 2 时，count 3条以上或 更新时间 30分钟以上就可以提交；
         * level = 5 时，count 6条以上或 更新时间 60分钟以上就可以提交；
         * 但是，如果只刊登了一条数据不再刊登的话，总不能等刊登等级降下来后再提交，所以可以同时考虑刊时间，可以设置
         * level = 1时，1条记录+10分钟 可以提交；
         * level = 1时，2条记录+0分钟 可以提交；
         * level = 2时，1条记录+20分钟 可以提交；
         * level = 2时，2条记录+10分钟 可以提交；
         * level = 5时，1条记录+50分钟 可以提交；
         * level = 5时，2条记录+40分钟 可以提交；
         * level = 5时，3条记录+30分钟 可以提交；
         * level = 5时，4条记录+20分钟 可以提交；
         *
         * 可以得出 (level + 1) * 10 <= count * 10 + update_time，这种情况可以提交；
         */
        //7.把前面的查询到的帐号ID放进队列；
        foreach ($pushTypeArr as $type=>$data) {
            if (empty($data) || !is_array($data)) {
                continue;
            }
            $queue = new UniqueQueuer($typeQueueArr[$type]);
            $level = $levelArr[$type];
            foreach ($data as $account_id=>$val) {
                //检查出当前帐号的最选一条记录是多少分钟之前更新的；
                $minute = intval(($this->time - $val['update_time']) / 60);
                $num = $this->minutePublishNum;
                if (($level + 1) * $num > $val['count'] * $num + $minute) {
                    continue;
                }
                if ($inZeroTime || $this->time - $val['update_time'] > $this->clearParamTime) {
                    $queue->remove($account_id);
                }
                $queue->push($account_id);
            }
        }

        //完成后再设置一下刊登等级；
        $this->setLevelData($typeQueueArr);
    }


    /**
     * 任务执行内容-以创建者为主刊登；
     * @return void
     */
    public  function execute2()
    {
        /**
         * @分析submission 会出现好几种情况；
         * 1. status = 0, 这种情况会自动刊登；
         * 2. status = 1, 刊登中，但是没有获取submission_id;
         * 3. status = 1, 已获取submission_id;
         * 4. status = 2, 这种不用问,已完成了；
         * 5. status = 3, 失败了，这种不用管
         */
        $typeQueueArr = [
            1 => AmazonPublishProductQueuer::class,
            2 => AmazonPublishRelationQueuer::class,
            3 => AmazonPublishQuantityQueuer::class,
            4 => AmazonPublishImageQueuer::class,
            5 => AmazonPublishPriceQueuer::class
        ];

        /** @var array $pushTypeArr 最终会循环推送到上面的队列里面去的，为防止重复，先集合到一起，再推  */
        $pushTypeArr = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => []
        ];

        $inZeroTime = $this->checkTime();

        //刊登完成未显示完成的完成；
        $this->updateFinish();
        //每次刊登或取值都会取刊登结果都会更新更新时间，所以，如果一个半小时内一直在刊登中，那说明被漏掉了，取出来检查一下；
        $this->handelAways();

        //1. status = 1,但是没有submission_id，超如10分钟，则主动把status改成0，让他去重新刊登；
        $this->resultModel->update(['status' => 0], [
            'status' => 1,
            'submission_id' => '',
            'create_time' => ['BETWEEN', [$this->time - 86000, $this->time - 600]]
        ]);

        //2. status = 1, 已取得submission_id, 超过20最后请求时间超过当前时间
        $subIds = $this->resultModel->where([
            'status' => 1,
            'submission_id' => ['<>', ''],
            'create_time' => ['BETWEEN', [$this->time - 86000, $this->time - 600]],
            'last_request_time' => ['BETWEEN', [$this->time - 86400, $this->time - 600]]
        ])->limit($this->max_total * 2)->order('id asc')->column('last_request_time', 'id');
        if (!empty($subIds)) {
            $queue = new UniqueQueuer(AmazonPublishProductResultQueuer::class);
            foreach ($subIds as $id=>$last_request_time) {
                if ($last_request_time < $this->time - $this->clearParamTime) {
                    $queue->remove($id);
                    $queue->push($id, 0);
                } else {
                    $queue->push($id);
                }
            }
        }

        //3.找出status = 1, 不管有没有刊登过的,已过了24小时的，去创建新的数据；
        $subDatas = $this->resultModel->where([
            'status' => 1,
            'pids' => ['<>', ''],
            'create_time' => ['BETWEEN', [$this->time - 86400 * 2, $this->time - 86400]]
        ])->limit($this->max_total * 2)->order('id asc')->select();

        if (!empty($subDatas)) {
            $typeStatusArr = [1 => 'product_status', 2 => 'relation_status', 3 => 'quantity_status', 4 => 'image_status', 5 => 'price_status'];
            foreach ($subDatas as $data) {
                $tmp = $data->toArray();
                if (!in_array($tmp['type'], [1, 2, 3, 4, 5])) {
                    continue;
                }

                //找出还没有刊登完成的；
                $pidArr = explode(',', $tmp['pids']);
                $newPidArr = $this->productModel->where([
                    'id' => ['in', $pidArr],
                    $typeStatusArr[$tmp['type']] => ['<>', AmazonPublishConfig::PUBLISH_STATUS_FINISH]
                ])->column('creator_id', 'id');
                if (empty($newPidArr)) {
                    $this->resultModel->update(['status' => 3], ['id' => $tmp['id']]);
                    continue;
                }
                $creatorIds = array_values($newPidArr);
                $newPidArr = array_keys($newPidArr);

                $newsub = [
                    'pids' => implode(',', $newPidArr),
                    'total' => count($newPidArr),
                    'status' => 0,
                    'type' => $tmp['type'],    //刊登关系
                    'account_id' => $tmp['account_id'],
                    'submission_id' => '',
                    'create_time' => time(),
                ];

                try {
                    Db::startTrans();
                    //旧的数据作废；
                    $this->resultModel->update(['status' => 3], ['id' => $tmp['id']]);
                    //插入新的数据；
                    $this->resultModel->insert($newsub);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    continue;
                }
                //放进刊登各类别的帐号数组
                foreach ($creatorIds as $creatorId) {
                    $pushTypeArr[$tmp['type']] = $this->mergeTypeSellerArr(
                        $pushTypeArr[$tmp['type']],
                        [['creator_id' => $creatorId, 'update_time' => strtotime('-1 day')]]
                    );
                }
            }
        }

        //4. 找出订时刊登了的销售号；
        $products = $this->productModel->where([
            'publish_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
            'product_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
            'timer' => ['BETWEEN', [$this->time - $this->max_time, $this->time + 900]]
            ])->field('id,account_id,creator_id')->limit($this->max_total)->select();
        $account_pids = [];
        //定时需刊登用户；
        $timeCreatorIds = [];
        foreach ($products as $val) {
            $account_pids[$val['account_id']][] = $val['id'];
            if (!in_array($val['creator_id'], $timeCreatorIds)) {
                $timeCreatorIds[] = $val['creator_id'];
            }
        }
        foreach ($account_pids as $account_id=>$pidArr) {
            AmazonPublishProductModel::update(['publish_status' => 1, 'update_time' => $this->time], ['id' => ['in', $pidArr]]);
            $newsub = [
                'pids' => implode(',', $pidArr),
                'total' => count($pidArr),
                'status' => 0,
                'type' => 1,    //刊登关系
                'account_id' => $account_id,
                'submission_id' => '',
                'create_time' => time(),
            ];
            //插入新的数据；
            $this->resultModel->insert($newsub);
        }
        //在这里先把用户记录下来，在下面忽略掉防止重复
        $productQueue = new UniqueQueuer($typeQueueArr[1]);
        foreach ($timeCreatorIds as $creatorId) {
            $productQueue->push($creatorId, 0);
        }

        //5.重刊，找出待刊登的状态；
        //产品
        $typeIdArr[1] = $this->productModel->where([
                'publish_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'timer' => 0,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 60]]
            ])
            ->order('update_time', 'asc')
            ->field('id,creator_id,update_time')
            ->limit($this->max_total * 2)->column('creator_id,update_time', 'id');
        $pushTypeArr[1] = $this->mergeTypeSellerArr($pushTypeArr[1], $typeIdArr[1]);

        //关系
        $typeIdArr[2] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'relation_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 60]]
            ])
            ->order('update_time', 'asc')
            ->field('id,creator_id,update_time')
            ->limit($this->max_total * 2)->column('creator_id,update_time', 'id');
        $pushTypeArr[2] = $this->mergeTypeSellerArr($pushTypeArr[2], $typeIdArr[2]);
        //库存
        $typeIdArr[3] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'quantity_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 60]]
            ])
            ->order('update_time', 'asc')
            ->field('id,creator_id,update_time')
            ->limit($this->max_total * 2)->column('creator_id,update_time', 'id');
        $pushTypeArr[3] = $this->mergeTypeSellerArr($pushTypeArr[3], $typeIdArr[3]);
        //图片
        $typeIdArr[4] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'image_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 60]]
            ])
            ->order('update_time', 'asc')
            ->field('id,creator_id,update_time')
            ->limit($this->max_total * 2)->column('creator_id,update_time', 'id');
        $pushTypeArr[4] = $this->mergeTypeSellerArr($pushTypeArr[4], $typeIdArr[4]);
        //价格
        $typeIdArr[5] = $this->productModel->where([
                'publish_status' => ['in', [AmazonPublishConfig::PUBLISH_STATUS_NONE, AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY]],
                'product_status' => AmazonPublishConfig::PUBLISH_STATUS_FINISH,
                'price_status' => AmazonPublishConfig::PUBLISH_STATUS_NONE,
                'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 60]]
            ])
            ->order('update_time', 'asc')
            ->field('id,creator_id,update_time')
            ->limit($this->max_total * 2)->column('creator_id,update_time', 'id');
        $pushTypeArr[5] = $this->mergeTypeSellerArr($pushTypeArr[5], $typeIdArr[5]);
        unset($typeIdArr);

        //product_status,relation_status,quantity_status,image_status,price_status

        Cache::handler()->hSet('task:amazon:republish_type_timer', date('Y-m-d-H:i:s', $this->time), json_encode($timeCreatorIds));

        /*
         * 按销售刊登，用不着看等级，因为每个销售绑定的帐号不一样，肯定帐号多的占便宜；
         */
        //7.把前面的查询到的帐号ID放进队列；
        foreach ($pushTypeArr as $type=>$data) {
            if (empty($data) || !is_array($data)) {
                continue;
            }
            $queue = new UniqueQueuer($typeQueueArr[$type]);
            $sorts = [];
            foreach ($data as $creator_id=>$val) {
                if ($inZeroTime || $this->time - $val['update_time'] > $this->clearParamTime) {
                    $queue->remove($creator_id);
                }
                //上面已经执行过定时的元素了；
                if ($type == 1 && in_array($creator_id, $timeCreatorIds)) {
                    continue;
                }
                //检查出当前帐号的最选一条记录是多少分钟之前更新的；
                $num = $this->minutePublishNum;
                $minute = $this->time - $val['update_time'];
                $sortKey = $val['count'] * $num * 60 + $minute;
                $sorts = $this->uniqueKeyArr($sorts, $sortKey, $creator_id);
            }
            krsort($sorts);
            Cache::handler()->hSet('task:amazon:republish_type_'. $type, date('Y-m-d-H:i:s', $this->time), json_encode(array_values($sorts)));
            foreach ($sorts as $creator_id) {
                $queue->push($creator_id);
            }
        }

        //完成后再设置一下刊登等级；
        //第二个参数3，相当于平时每1个销售同时在刊登3个帐号；
        $this->setLevelData($typeQueueArr, 3);
    }

    public function uniqueKeyArr($arr, $key, $val)
    {
        for ($i = 0; $i <= 10000; $i++) {
            if (!isset($arr[$key - $i])) {
                $arr[$key - $i] = $val;
                return $arr;
            }
        }
        return $arr;
    }


    /**
     * 将一直在刊登中的标记为待刊登，或刊登失败；
     */
    public function handelAways()
    {
        $underways = $this->productModel->where([
            'publish_status' => AmazonPublishConfig::PUBLISH_STATUS_UNDERWAY,
            'update_time' => ['BETWEEN', [$this->time - $this->max_time, $this->time - 7200]]
        ])->limit($this->max_total * 2)->column('id,account_id,product_status,update_time');

        $fieldArr = [1 => 'upload_product', 'upload_relation', 'upload_quantity', 'upload_image', 'upload_price'];
        foreach ($underways as $val) {
            $detailList = $this->detailModel->where(['product_id' => $val['id']])
                ->field('id,type,upload_product,upload_relation,upload_quantity,upload_image,upload_price')
                ->select();
            //用来更新SPU的数组；
            $update = [];
            $total = count($detailList);
            $update['publish_status'] = AmazonPublishConfig::PUBLISH_STATUS_NONE;
            if ($total < 2) {
                $update['publish_status'] = AmazonPublishConfig::PUBLISH_STATUS_ERROR;
            }
            $update['product_status'] = 2;
            $update['relation_status'] = 2;
            $update['quantity_status'] = 2;
            $update['image_status'] = 2;
            $update['price_status'] = 2;
            foreach ($detailList as $detail) {
                if ($total == 2 && $detail['type'] == 0) {
                    continue;
                }
                foreach ($fieldArr as $field) {
                    $fileStatus = str_replace('upload_', '', $field). '_status';
                    //失败；
                    if ($detail[$field] == 2) {
                        if ($field != 'image_status') {
                            $update['publish_status'] = AmazonPublishConfig::PUBLISH_STATUS_ERROR;
                        } else {
                            if ($val['create_time'] < $this->time - 86400) {
                                $update['publish_status'] = AmazonPublishConfig::PUBLISH_STATUS_ERROR;
                            } else {
                                $update['publish_status'] = AmazonPublishConfig::PUBLISH_STATUS_NONE;
                            }
                        }
                        $update[$fileStatus] = 0;
                    }  if ($detail[$field] == 0) {
                        $update[$fileStatus] = 0;
                    }
                }
            }
            if (10 == array_sum($update)) {
                $update['publish_status'] = AmazonPublishConfig::PUBLISH_STATUS_FINISH;
            }
            $this->productModel->update($update, ['id' => $val['id']]);
        }
    }


    /**
     * 把5个分状态都是完成了的，改成完成；
     */
    public function updateFinish()
    {
        $page = 1;
        while (true) {
            $productIds = $this->productModel->where([
                'publish_status' => ['<>', 2],
                'product_status' => 2,
                'relation_status' => 2,
                'quantity_status' => 2,
                'image_status' => 2,
                'price_status' => 2,
                //加一个最后更新时间限制，防止正在操作的当前数据受到影响
                'update_time' => ['<', $this->time - 1200]
            ])->field('id')->page($page++, $this->max_total)->column('id');
            if (empty($productIds)) {
                break;
            }
            $this->productModel->update(['publish_status' => 2], ['id' => ['in', $productIds]]);
            if (count($productIds) < $this->max_total) {
                break;
            }
        }
    }


    /**
     * 判断当前是否是0-1点之前
     * @return bool
     */
    public function checkTime()
    {
        $time = time();
        $zero = strtotime(date('Y-m-d'), $time);
        if ($time >= $zero && $time < $zero + 3600) {
            return true;
        }
        return false;
    }


    public function setLevelData($typeQueueArr, $subjoin = 1)
    {
        /** @var $cache \app\common\cache\driver\AmazonPublish */
        $cache = Cache::store('AmazonPublish');
        $levelArr = [];
        foreach ($typeQueueArr as $type=>$queue) {
            $lists = (new UniqueQueuer($queue))->lists();
            $level = intval(count($lists) / 100 * $subjoin);
            $cache->setPublishLevel($type, $level);
            $levelArr[$type] = $level;
        }

        return $levelArr;
    }


    public function mergeTypeAccountArr(array $typeArr1, array $typeArr2)
    {
        foreach ($typeArr2 as $val) {
            $old_count = empty($typeArr1[$val['account_id']]['count']) ? 0 : $typeArr1[$val['account_id']]['count'];
            $old_update_time = empty($typeArr1[$val['account_id']]['update_time']) ? 10000000000 : $typeArr1[$val['account_id']]['update_time'];
            $typeArr1[$val['account_id']]['count'] = $old_count + 1;
            $typeArr1[$val['account_id']]['update_time'] = min([$val['update_time'], $old_update_time]);
        }
        return $typeArr1;
    }


    public function mergeTypeSellerArr(array $typeArr1, array $typeArr2)
    {
        foreach ($typeArr2 as $val) {
            $old_count = empty($typeArr1[$val['creator_id']]['count']) ? 0 : $typeArr1[$val['creator_id']]['count'];
            $old_update_time = empty($typeArr1[$val['creator_id']]['update_time']) ? 10000000000 : $typeArr1[$val['creator_id']]['update_time'];
            $typeArr1[$val['creator_id']]['count'] = $old_count + 1;
            $typeArr1[$val['creator_id']]['update_time'] = min([$val['update_time'], $old_update_time]);
        }
        return $typeArr1;
    }
}
