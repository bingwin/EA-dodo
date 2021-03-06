<?php

namespace app\common\model;

use think\Model;
use app\common\cache\Cache;

/**
 * Created by PhpStorm.
 * User: XPDN
 * Date: 2016/10/28
 * Time: 9:13
 */
class Supplier extends Model
{

    /**
     * 初始化
     */
    protected function initialize()
    {
        parent::initialize();
    }

    /** 获取来源信息
     * @param $value
     * @return mixed
     */
    public function getSourceAttr($value)
    {
        $source = [0 => '采购', 1 => '开发'];
        return $source[$value];
    }

    /** 检查是否存在
     * @param array $data
     * @return bool
     */
    public function check(array $data)
    {
        $result = $this->get($data);
        if (!empty($result)) {
            return true;
        }
        return false;
    }

    /** 创建时间获取器
     * @param $value
     * @return int
     */
    public function getCreateTimeAttr($value)
    {
        if (is_numeric($value)) {
            return $value;
        } else {
            return strtotime($value);
        }
    }

    /** 更新时间获取器
     * @param $value
     * @return int
     */
    public function getUpdateTimeAttr($value)
    {
        if (is_numeric($value)) {
            return $value;
        } else {
            return strtotime($value);
        }
    }

    /** 获取供应商等级信息
     * @return array
     */
    public function getLevel()
    {
        $level = [
            0 => [
                'label' => 1,
                'name' => '一等供应商'
            ],
            1 => [
                'label' => 2,
                'name' => '二等供应商'
            ],
            2 => [
                'label' => 3,
                'name' => '三等供应商'
            ],
        ];
        return $level;
    }

    /** 获取供应商支付方式
     * @return array
     */
    public function getPayment()
    {
        $payment = [
            0 => [
                'label' => 2,
                'name' => '银行转账'
            ],
            1 => [
                'label' => 4,
                'name' => '支付宝支付'
            ],
        ];
        return $payment;
    }

    /** 结算方式
     * @return array
     */
    public function getBalance()
    {
        $balance = [
            0 => ['label' => 3, 'name' => '款到发货'],
            1 => ['label' => 4, 'name' => '货到付款'],
            2 => ['label' => 5, 'name' => '定期结算-周结周付'],
            3 => ['label' => 6, 'name' => '定期结算-半月结半月付'],
            4 => ['label' => 7, 'name' => '定期结算-月结月付'],
            5 => ['label' => 8, 'name' => '定期结算-月结2月付'],
            6 => ['label' => 9, 'name' => '定期结算-月结3月付'],
            //7 => ['label' => 10, 'name' => '定期结算-当月结'],
            7 => ['label' => 11, 'name' => '阿里30天'],
            8 => ['label' => 12, 'name' => '阿里现结'],
            9 => ['label' => 13, 'name' => '跨境宝-阿里现结'],
            10 => ['label' => 14, 'name' => '跨境宝-阿里30天'],
            11 => ['label' => 15, 'name' => '跨境宝-阿里60天'],
            12 => ['label' => 16, 'name' => '跨境宝-阿里20天'],
            //13 => ['label' => 17, 'name' => '跨境宝-阿里60天'],
            13 => ['label' => 18, 'name' => '跨境宝-阿里7天'],
            14 => ['label' => 19, 'name' => '阿里20天'],
            15 => ['label' => 20, 'name' => '阿里7天'],
            16 => ['label' => 21, 'name' => '阿里60天'],
            17 => ['label' => 22, 'name' => '阿里45天'],
            18 => ['label' => 23, 'name' => '阿里90天'],
            19 => ['label' => 24, 'name' => '跨境宝-阿里45天'],
            20 => ['label' => 25, 'name' => '跨境宝-阿里90天'],

        ];
        return $balance;
    }

    /** 发票类型
     * @return array
     */
    public function getInvoice()
    {
        $invoice = [
                [
                'label' => 1,
                'name' => '17%增值税专用发票'
            ],
                [
                'label' => 5,
                'name' => '17%的增值税普通发票'
            ],
                [
                'label' => 2,
                'name' => '3%增值税普通发票'
            ],
                [
                'label' => 3,
                'name' => '3%普通发票'
            ],
                [
                'label' => 6,
                'name' => '13%的增值税普通发票'
            ],
                [
                'label' => 9,
                'name' => '13%的增值税专用发票'
            ],
                [
                'label' => 8,
                'name' => '不能开票'
            ],
                [
                'label' => 4,
                'name' => '无税'
            ],
                [
                'label' => 7,
                'name' => '其他'
            ],
        ];
        return $invoice;
    }

    /**
     * 获取供应商类型
     * 0-企业（有限责任公司） 1-个人（个人工商户）2-股份有限公司 3-一人有限责任公司 4-个人独资企业 5-自然人独资
     * @return array
     */
    public function getType()
    {
        $types = [
            [
                'label' => 0,
                'name' => '有限责任公司'
            ],
            [
                'label' => 1,
                'name' => '个人工商户'
            ],
            [
                'label' => 2,
                'name' => '股份有限公司'
            ],
            [
                'label' => 3,
                'name' => '一人有限责任公司'
            ],
            [
                'label' => 4,
                'name' => '个人独资企业'
            ],
            [
                'label' => 5,
                'name' => '自然人独资'
            ],
            [
                'label' => 6,
                'name' => '普通合伙企业'
            ],
        ];
        return $types;
    }

    /**
     * 1-线上交易 2-线下-款到发货
     * @return array
     */
    public function getTransactionType()
    {
        $types = [
                [
                'label' => 1,
                'name' => '线上交易'
            ],
                [
                'label' => 2,
                'name' => '线下交易'
            ],
        ];
        return $types;
    }

    /**
     * 退货天数(1-30天,2-45天,3-60天,4-90天)
     * @return array
     */
    public function getReturnGoodsData()
    {
        $return_goods_data = [
            0 => ['label' => 1, 'name' => '30天'],
            1 => ['label' => 2, 'name' => '45天'],
            2 => ['label' => 3, 'name' => '60天'],
            3 => ['label' => 4, 'name' => '90天'],
        ];
        return $return_goods_data;
    }

    /**
     * 是否贴标、套牌(1-仅贴标，2-仅套牌，3-贴标和套袋，4-都不支持)
     * @return array
     */
    public function getLabelDeck()
    {
        $label_deck = [
            0 => ['label' => 1, 'name' => '仅贴标'],
            1 => ['label' => 2, 'name' => '仅套袋'],
            2 => ['label' => 3, 'name' => '贴标和套袋'],
            3 => ['label' => 4, 'name' => '都不支持'],
        ];
        return $label_deck;
    }

    /** 检查代码或者用户名是否有存在了
     * @param $id
     * @param $company_name
     * @param $code
     * @return bool
     */
    public function isHas($id, $company_name = '',$code = '')
    {
        if (!empty($company_name)) {
            $result = $this->where(['company_name' => $company_name])->where('id', 'NEQ', $id)->select();
            if (!empty($result)) {
                return true;
            } else {
                return false;
            }
        }
        if (!empty($code)) {
            $result = $this->where(['code' => $code])->where('id', 'NEQ', $id)->select();
            if (!empty($result)) {
                return true;
            } else {
                return false;
            }
        }
        if (!empty($id)) {
            $result = $this->where(['id' => $id])->find();
            if (!empty($result)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取器——获取采购员名称
     *
     * @param int $value 字段值
     *
     * @return string
     * @throws \think\Exception
     */
    /*public function getPurchaserIdAttr($value)
    {
        if (empty($value))  return '';
        $cacheUser = Cache::store('user')->getOneUser($value);
        return $cacheUser ? $cacheUser['realname'] : '';
    }*/

    /**
     * @desc 获取贴标、套牌文字信息
     * @param $labelDeck
     * @return string
     * @author Reece
     * @date 2019-01-22 18:14:24
     */
    public function getLabelDeckText($labelDeck)
    {
        $arr = (new self())->getLabelDeck();
        $arr = array_combine(array_column($arr, 'label'), array_column($arr, 'name'));
        return $arr[$labelDeck] ?? '';
    }
}
