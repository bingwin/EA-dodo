<?php
/**
 * Created by PhpStorm.
 * User: zhuda
 * Date: 2019/2/28
 * Time: 15:25
 */

namespace app\index\service;


use app\common\model\account\CreditCategory;
use think\DB;
use think\Request;
use app\index\service\User as userService;
use app\common\exception\JsonErrorException;
use app\common\model\Bank;
use app\common\model\account\CreditCard;
use \app\common\model\UserLog;

class CreditCardService
{

    /**
     * @var creditcard
     */
    protected $creditCardModel;

    public function __construct()
    {
        if (is_null($this->creditCardModel)) {
            $this->creditCardModel = new CreditCard();
        }
    }

    /**
     * 接收错误并返回,当你调用此类时，如果遇到需要获取错误信息时，请使用此方法。
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 信用卡列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function creditCardList()
    {
        $request = Request::instance();
        $params = $request->param();

        $order = 'credit_card.id';
        $sort = 'desc';
        $sortArr = [
            'card_number' => 'c.card_number',
            'card_master' => 'c.card_master',
            'card_status' => 'c.card_status',
            'card_category' => 'c.card_category',
            'validity_date' => 'c.validity_date',
            'account_count' => 'c.account_count',
            'update_time' => 'c.update_time',
        ];
        if (!empty($params['order_by']) && !empty($sortArr[$params['order_by']])) {
            $order = $sortArr[$params['order_by']];
        }
        if (!empty($params['sort']) && in_array($params['sort'], ['asc', 'desc'])) {
            $sort = $params['sort'];
        }
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $field = 'c.id,c.card_number,c.card_master,c.card_status,c.card_category,c.validity_date,c.bank_id,c.security_code,t.num as account_count,c.synchronize_status,c.creator_id,c.create_time';
        $count = $this->getWhere($params)->count();
        $shopList =  $this->getWhere($params)
            ->field($field)
            ->order($order, $sort)
            ->page($page, $pageSize)
            ->select();
        $userService = new UserService();
        $userLog = new UserLog();
        $bank = (new bank())->select();
        $temp = [];

        foreach ($bank as $k => $v) {
            $temp[$v['id']] = $v['bank_name'];
        }

        foreach ($shopList as $key => $item) {
            $userInfo = $userService->getUser($item['creator_id']);
            $shopList[$key]['department'] = $userLog->getDepartmentNameAttr('', ['operator_id' => $item['creator_id']]);
            $shopList[$key]['creator'] = $userInfo['realname'] ?? '';
            $shopList[$key]['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $shopList[$key]['bank'] = $temp[$item['bank_id']];
            $shopList[$key]['account_count'] = (int)$item['account_count'];
        }
        $result = [
            'data' => $shopList,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;
    }


    /**
     * 保存信用卡记录信息
     * @param $data
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\exception\DbException
     */
    public function save($data)
    {
        $time = time();
        $data['create_time'] = $time;
        $data['update_time'] = $time;
        $data['account_count'] = $data['account_count'] ?? 0;

        $bankModel = new Bank();
        $bankCheck = $bankModel->where(['id' => $data['bank_id']])->count();
        if ($bankCheck == 0) {
            $this->error = '银行卡bank_id记录不存在';
            return false;
        }

        Db::startTrans();
        try {
            $this->creditCardModel->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $this->creditCardModel->id;
            Db::commit();
        } catch (JsonErrorException $e) {
            $this->error = $e->getMessage();
            Db::rollback();
            return false;
        }

        $creditInfo = $this->creditCardModel->field(true)->where(['id' => $new_id])->find();
        $creditInfo['create_time'] = date('Y-m-d H:i:s', $creditInfo['create_time']);
        $creditInfo['update_time'] = date('Y-m-d H:i:s', $creditInfo['update_time']);
        return $creditInfo;
    }


    /**
     * 根据ID查询信用卡记录
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\exception\DbException
     */
    public function read($id)
    {
        $creditInfo = $this->creditCardModel->where(['id' => $id])->find();
        if (!$creditInfo) {
            $this->error = '无法查询到信用卡记录';
            return false;
        }
        $bank = (new bank())->where(['id' => $creditInfo['bank_id']])->find();
        $creditInfo['bank'] = $bank ? $bank['bank_name'] : '';
        return $creditInfo;
    }


    /**
     * 更新记录
     * @param $id
     * @param $data
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update($id, $data)
    {
        $bankModel = new Bank();
        $bankCheck = $bankModel->where(['id' => $data['bank_id']])->count();
        if ($bankCheck == 0) {
            $this->error = '银行卡bank_id记录不存在';
            return false;
        }

        if (!$this->read($id)) {
            return false;
        }

        Db::startTrans();
        try {
            $data['update_time'] = time();
            unset($data['id']);
            $this->creditCardModel->allowField(true)->save($data, ['id' => $id]);
            Db::commit();
        } catch (JsonErrorException $e) {
            $this->error = $e->getMessage() . $e->getFile() . $e->getLine();
            Db::rollback();
            return false;
        }

        $creditInfo = $this->creditCardModel->field(true)->where(['id' => $id])->find();
        $creditInfo['create_time'] = date('Y-m-d H:i:s', $creditInfo['create_time']);
        $creditInfo['update_time'] = date('Y-m-d H:i:s', $creditInfo['update_time']);
        return $creditInfo;
    }

    /**
     * 查询条件获取
     * @param $params
     * @return mixed
     */
    public function getWhere($params)
    {
        $ModelCreditCardModel = new CreditCard();
        $ModelCreditCardModel->alias('c')->join(" (SELECT  credit_card_id,count(*) as num FROM account where credit_card_id > 0 GROUP BY credit_card_id) as t ", "c.id = t.credit_card_id", 'left');
        //信用卡状态
        if (isset($params['card_status']) && ($params['card_status'] !== '')) {
            // $where['credit_card.card_status'] = ['eq', $params['card_status']];
            $ModelCreditCardModel->where('c.card_status', $params['card_status']);
        }

        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'card_number':
                    $ModelCreditCardModel->where('c.card_number', 'like', '%' . $params['snText'] . '%');
                    //$where['credit_card.card_number'] = ['like', '%'.$params['snText'].'%'];
                    break;
                case 'card_master':
                    //$where['credit_card.card_master'] = ['like', '%'.$params['snText'].'%'];
                    $ModelCreditCardModel->where('c.card_master', 'like', '%' . $params['snText'] . '%');
                    break;
                case 'validity_date':
                    //$where['credit_card.validity_date'] = ['like', '%'.$params['snText'].'%'];
                    $ModelCreditCardModel->where('c.validity_date', 'like', '%' . $params['snText'] . '%');
                    break;
                default:
                    break;
            }
        }

        //信用卡类别
        if (isset($params['card_category']) && ($params['card_category'] !== '')) {
            //$where['credit_card.card_category'] = ['eq', $params['card_category']];
            $ModelCreditCardModel->where('c.card_category', $params['card_category']);
        }
        $is_null = false;
        if (isset($params['account_count_start']) && $params['account_count_start'] !== '') {
            $ModelCreditCardModel->where('t.num', '>=', intval($params['account_count_start']));
            if (!$params['account_count_start']) {
                $ModelCreditCardModel->whereOr('t.num', 'exp', 'is null');
                $is_null = true;
            }
        }
        if (isset($params['account_count_end']) && $params['account_count_end'] !== '') {
            $ModelCreditCardModel->where('t.num', '<=', intval($params['account_count_end']));
            if (!$params['account_count_end']) {
                if (!$is_null) {
                    $ModelCreditCardModel->whereOr('t.num', 'exp', 'is null');
                }
            }
        }
        //被绑定账号数
//        if (isset($params['account_count_start']) || isset($params['account_count_end'])) {
//
//            if (!empty($params['account_count_start']) && !empty($params['account_count_end'])) {
//
//                $where['credit_card.account_count'] = ['between', [$params['account_count_start'],$params['account_count_end']]];
//
//            } elseif (!empty($params['account_count_start']) && empty($params['account_count_end'])) {
//
//                $where['credit_card.account_count'] = ['egt', $params['account_count_start']];
//
//            } elseif (empty($params['account_count_start']) && !empty($params['account_count_end'])) {
//
//                $where['credit_card.account_count'] = ['elt', $params['account_count_end']];
//
//            } elseif ($params['account_count_start'] == 0 && $params['account_count_end'] == 0) {
//
//                $where['credit_card.account_count'] = ['eq', 0];
//            }
//        }
        if (isset($params['taskCondition']) && isset($params['taskTime']) && $params['taskTime'] !== '') {

            $ModelCreditCardModel->where('c.synchronize_status', trim($params['taskCondition']), $params['taskTime']);
            //$where['credit_card.synchronize_status'] = [trim($params['taskCondition']), $params['taskTime']];
        }
        return $ModelCreditCardModel;
    }


}