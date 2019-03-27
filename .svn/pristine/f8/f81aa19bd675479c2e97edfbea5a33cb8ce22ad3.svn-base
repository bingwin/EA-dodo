<?php
namespace app\customerservice\task;

use app\common\model\paypal\PaypalDispute;
use app\common\model\paypal\PaypalDisputeRecord;
use app\common\service\UniqueQueuer;
use app\customerservice\queue\PaypalDisputeByIdQueue;
use app\customerservice\queue\PaypalDisputeOperateQueue;
use think\Exception;
use app\index\service\AbsTasker;


class PaypalDisputeAutoUpdate extends AbsTasker
{
    public function getName()
    {
        return "Paypal纠纷自动更新";
    }

    public function getDesc()
    {
        return "Paypal纠纷自动更新";
    }

    public function getCreator()
    {
        return "冬";
    }

    public function getParamRule()
    {
        return [
            'updateTime|更新时间' => 'require|select:默认10天:0,1天前到现在:1,3天前到现在:3,5天前到现在:5,10天前到现在:10,15天前到现在:15,20天前到现在:20,25天前到现在:25,30天前到现在:30,50天前到现在:50,90天前到现在:90,180天前到现在:180,所有的纠纷:10000'
        ];
    }

    public function execute()
    {
        try {
            $limit = 100;

            $queue = new UniqueQueuer(PaypalDisputeByIdQueue::class);
            $disputeModel = new PaypalDispute();
            $params = [];

            //1.先把详情未下载完成的，下载详情；
            $where = [];
            $where['update_time'] = 0;
            $where['create_time'] = ['<', time() - 60 * 30];
            $start = 1;
            do {
                $pdata = $disputeModel->where($where)->page($start++, $limit)->order('id', 'asc')->field('account_id account,dispute_id')->select();
                if (empty($pdata)) {
                    break;
                }
                foreach ($pdata as $data) {
                    $params[] = $data->toArray();
                }
            } while ($limit == count($pdata));


            //2.更新；
            @$down_time = (int)$this->getParam('updateTime');
            if (empty($down_time)) {
                $down_time = 10;
            }
            $start_time = time() - 86400 * $down_time;
            $where = [];
            $where['update_time'] = ['BETWEEN', [$start_time, time() - 60 * 30]];
            $where['status'] = ['<>', 'RESOLVED'];
            $start = 1;
            do {
                $pdata = $disputeModel->where($where)->page($start++, $limit)->order('id', 'asc')->field('account_id account,dispute_id')->select();
                if (empty($pdata)) {
                    break;
                }
                foreach ($pdata as $data) {
                    $params[] = $data->toArray();
                }
            } while ($limit == count($pdata));

            //放进列新队列；
            foreach ($params as $val) {
                $queue->push($val);
                //(new PaypalDisputeByIdQueue($val))->execute();
            }

            //3.处理队列推送；
            $params = [];
            $queue = new UniqueQueuer(PaypalDisputeOperateQueue::class);
            $where = [];
            $where['update_time'] = ['BETWEEN', [time() - 86400, time() - 60 * 10]];
            $where['status'] = ['IN', [0, 2]];
            $record = new PaypalDisputeRecord();
            $start = 1;
            do {
                $ids = $record->where($where)->page($start++, $limit)->order('id', 'asc')->column('id');
                if (empty($ids)) {
                    break;
                }
                $params = array_merge($params, $ids);
            } while ($limit == count($ids));

            //放进列新队列；
            foreach ($params as $val) {
                $queue->push($val);
                //(new PaypalDisputeOperateQueue($val))->execute();
            }

        } catch (Exception $e) {
            throw new Exception($e->getFile() . '|' . $e->getLine() . '|' . $e->getMessage());
        }
    }

}