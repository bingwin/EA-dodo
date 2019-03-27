<?php

namespace app\common\cache\driver;

use app\common\cache\Cache;
use app\common\model\Currency as currencyModel;
use think\Exception;

/**
 * Created by PhpStorm.
 * User: PHILL
 * Date: 2016/12/7
 * Time: 17:45
 */
class Currency extends Cache
{
    /** 获取货币种类
     * @return array
     */
    public function getCurrency($code = 0)
    {
        if ($this->redis->exists('cache:Currency')) {
            if (!empty($code)) {
                $result = json_decode($this->redis->get('cache:Currency'), true);
                return isset($result[$code]) ? $result[$code] : [];
            }
            return json_decode($this->redis->get('cache:Currency'), true);
        }
        $currencyMdoel = new currencyModel();
        $result = $currencyMdoel->order('sort asc')->select();
        $new_array = [];
        foreach ($result as $k => $v) {
            $new_array[$v['code']] = $v;
        }
        $this->redis->set('cache:Currency', json_encode($new_array));
        if (!empty($code)) {
            return $new_array[$code];
        }
        return $new_array;
    }
    /**
     * @desc 不同币种之间的汇率转换，以人民币CNY为基准.
     * @param string $in 原来的币种
     * @param string $out 需要转换成的币种
     * @return double 汇率差
     * @author Jimmy
     * @date 2017-10-19 14:10:11
     */
    public function exchangeCurrency($in, $out)
    {
        try {
            $res = $this->getCurrency();
            return $res[$in]['system_rate']/$res[$out]['system_rate'];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
        
    }

}