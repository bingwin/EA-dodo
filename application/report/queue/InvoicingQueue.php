<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | File  : ProfitExportQueue.php
// +----------------------------------------------------------------------
// | Author: LiuLianSen <3024046831@qq.com>
// +----------------------------------------------------------------------
// | Date  : 2017-08-07
// +----------------------------------------------------------------------

namespace  app\report\queue;

use app\common\cache\Cache;
use app\common\service\SwooleQueueJob;
use app\report\service\Invoicing;
use app\report\service\ProfitStatement;


class InvoicingQueue extends SwooleQueueJob
{
    public function getName(): string
    {
        return "经销存导出报表";
    }

    public function getDesc(): string
    {
        return "经销存导出报表";
    }

    public function getAuthor(): string
    {
        return "laiyongfeng";
    }



    public function execute()
    {
        try {
            $service = new Invoicing();
            $service->export($this->params);
        }catch (\Exception $ex){
            Cache::handler()->hset(
                'hash:report_export',
                'error_'.time(),
                $ex->getMessage());
        }
    }
}