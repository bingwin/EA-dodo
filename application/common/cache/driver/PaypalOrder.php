<?php
namespace app\common\cache\driver;

use app\common\cache\Cache;
use app\common\model\PaypalEventCode;
use app\common\model\PaypalTransaction;
use think\Db;

/**
 * Created by tanbin.
 * User: PHILL
 * Date: 2017/06/14
 * Time: 11:44
 */
class PaypalOrder extends Cache
{

    /** 获取属性信息
     * @param string $message_id  
     * @param array $data
     * @return array|mixed
     */
    public function paypalOrderByTxnid($account_id, $txn_id, $data = [])
    {      
        //Cache::handler()->del('hash:PaypalOrderByTxnid'); //删除
        $key = 'hash:PaypalOrderByTxnid:'. $account_id;
        if ($data) {
            $this->redis->hset($key, $txn_id, json_encode($data));
            return true;
        }
        $result = json_decode($this->redis->hget($key, $txn_id), true);
        if($result){
            return $result;
        }
        
        return [];
        
    }
    
      
    /**
     * 添加测试日志
     * @param unknown $key
     * @param array $data
     */
    public function addOrderLogs($key, $data = [])
    {
        if(!is_string($data))
        {
            $data = json_encode($data);
        }
        $this->redis->zAdd('cache:PaypalOrderLogs', $key, $data);
    }
    
    /**
     * 获取测试日志
     * @param number $start
     * @param number $end
     */
    public function getOrderLogs($start = 0, $end = 50)
    {
        $result = [];
        if($this->redis->exists('cache:PaypalOrderLogs')) {
            $result = $this->redis->zRange('cache:PaypalOrderLogs', $start, $end);
        }
        return $result;
    }

    public function getLatelyLogs($start=0,$end=50)
    {
        $result = [];
        if($this->redis->exists('cache:PaypalOrderLogs')) {
            $result = $this->redis->zRevRange('cache:PaypalOrderLogs', $start, $end);
        }
        return $result;
    }

    public function setTableField($data)
    {
        if(is_array($data))
        {
            $data = json_encode($data);
        }
        $this->redis->set("cache:PaypalTranField",$data);
    }

    public function getTableField()
    {
        $data = $this->redis->get("cache:PaypalTranField");
        if($data)
        {
           $data = json_decode($data,true);
        }
        if(!empty($data))
        {
            return $data;
        }
        $paypalTrans = new PaypalTransaction();
        $fields = $paypalTrans->getTableFields(["table"=>"paypal_transaction"]);
        $this->setTableField($fields);
        return $fields;

    }

    public function eventCodeId($eventCodes, $exclude=[], $condition='in')
    {
        if(empty($eventCodes))
        {
            return '';
        }

        switch ($condition)
        {
            case 'in':
                break;
            case 'not in':
                !empty($exclude) && $eventCodes = array_merge($eventCodes,$exclude);
                break;
        }

        $cacheKey = "cache:eventCode_".md5(serialize($eventCodes));
        $ids = $this->redis->get($cacheKey);
        if($ids)
        {
           return $ids;
        }
        $PaypalEventIds = Db::table("paypal_event_code")->where("event_code",$condition,$eventCodes)->column("id");
        if(!$PaypalEventIds)
        {
            return '';
        }
        $PaypalEventIds = implode(",",$PaypalEventIds);
        $this->redis->setex($cacheKey,10*60,$PaypalEventIds);
        return $PaypalEventIds;
    }

}
