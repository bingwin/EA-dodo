<?php
namespace app\index\service;

use app\common\exception\JsonErrorException;
use app\common\model\joom\JoomAccount;
use app\common\model\joom\JoomShop as JoomShopModel;
use app\common\cache\Cache;
use think\Exception;
use think\Request;
use think\Db;

/**
 * Created by PhpStorm.
 * User: PHILL
 * Date: 2017/4/25
 * Time: 11:17
 */
class JoomAccountService
{
    protected $joomAccountModel;

    public function __construct()
    {
        if (is_null($this->joomAccountModel)) {
            $this->joomAccountModel = new JoomAccount();
        }
    }

    /** 账号列表
     * @param Request $request
     * @return array
     * @throws \think\Exception
     */
    public function accountList(Request $request)
    {
        $where = [];
        $params = $request->param();
        if (isset($params['status']) && $params['status'] !== '' && in_array($params['status'], [0, 1])) {
            $where['is_invalid'] = ($params['status'] == 1) ? 1 : 0;
        }
        if (isset($params['platform_status']) && $params['platform_status'] !== '' && in_array($params['platform_status'], [0, 1])) {
            $where['platform_status'] = ($params['platform_status'] == 1) ? 1 : 0;
        }
        if (!empty($params['name'])) {
            $where['account_name'] = ['like', '%'. $params['name']. '%'];
        }

        $order = 'id';
        $sort = 'desc';
        if (!empty($params['order_by']) && in_array($params['order_by'], ['account_name', 'code', 'company'])) {
            $order = $params['order_by'];
        }
        if (!empty($params['sort']) && in_array($params['sort'], ['asc', 'desc'])) {
            $sort = $params['sort'];
        }

        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $field = 'id,account_name name,code,company,is_invalid status,platform_status,create_time,update_time';
        $count = $this->joomAccountModel->field($field)->where($where)->count();
        $accountList = $this->joomAccountModel->field($field)
            ->where($where)
            ->order($order, $sort)
            ->page($page, $pageSize)
            ->select();

        $new_array = [];
        $counts = $this->accountCounts();
        foreach ($accountList as $k => $v) {
            $temp = $v->toArray();
            $temp['total'] = $counts[$v['id']]?? 0;
            $new_array[$k] = $temp;
        }
        $result = [
            'data' => $new_array,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;
    }

    /**
     * 获取各账户的店辅数量
     * @return array
     */
    public function accountCounts()
    {
        $shopM = new JoomShopModel();
        $list = $shopM->field('joom_account_id,count(id) as total')->group('joom_account_id')->select();
        if(empty($list)) {
            return [];
        }

        $counts = [];
        foreach($list as $val) {
            $counts[$val['joom_account_id']] = $val['total'];
        }
        return $counts;
    }

    /** 保存账号信息
     * @param $data
     * @return array
     */
    public function save($data)
    {
        $ret = [
            'msg' => '',
            'code' => ''
        ];
        $time = time();
        $data['account_name'] = $data['name'];
        unset($data['name']);
        $data['create_time'] = $time;
        $data['update_time'] = $time;
        $data['is_invalid'] = $data['platform_status'] = 1;  //设置为启用
        $res = $this->joomAccountModel->where('code', $data['code'])->field('id')->find();
        if (count($res)) {
            $ret['msg'] = '账户名重复';
            $ret['code'] = 400;
            return $ret;
        }
        Db::startTrans();
        try {
            $this->joomAccountModel->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $this->joomAccountModel->id;
            //新增缓存
            Cache::store('JoomAccount')->setTableRecord($new_id);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new JsonErrorException($e->getMessage(), 500);
        }
        $accountInfo = $this->joomAccountModel->field(true)->where(['id' => $new_id])->find();
        $new_data['id'] = $new_id;
        $new_data['name'] = param($data,'account_name', '');
        $new_data['code'] = param($data,'code', '');
        $new_data['company'] = param($data,'company', '');;
        $new_data['status'] = param($data,'is_invalid', '');
        $new_data['platform_status'] = param($data,'platform_status', '');
        $new_data['create_time'] = param($data,'create_time', '');
        $new_data['update_time'] = param($data,'update_time', '');
        return $new_data;
    }

    /** 账号信息
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function read($id)
    {
        $accountInfo = Cache::store('JoomAccount')->getTableRecord($id);
        $accountInfo['name'] = $accountInfo['account_name'];
        if(empty($accountInfo)){
            throw new JsonErrorException('账号不存在',500);
        }
        return $accountInfo;
    }

    /** 更新
     * @param $id
     * @param $data
     * @return \think\response\Json
     */
    public function update($id, $data)
    {
        Db::startTrans();
        try {
            if(isset($data['name'])) {
                $data['account_name'] = $data['name'];
                unset($data['name']);
            }
            $data['update_time'] = time();
            unset($data['id']);
            $this->joomAccountModel->allowField(true)->save($data, ['id' => $id]);
            //修改缓存
            $cache = Cache::store('JoomAccount');
            foreach($data as $key=>$val) {
                $cache->updateTableRecord($id, $key, $val);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new JsonErrorException($e->getMessage() . $e->getFile() . $e->getLine(), 500);
        }
    }

    /** 状态
     * @param $id
     * @param $data
     * @return array
     */
    public function status($id, $data)
    {
        if (!$this->joomAccountModel->check(['id' => $id])) {
            throw new JsonErrorException('账号不存在', 400);
        }
        try {
            if(isset($data['status'])) {
                $data['is_invalid'] = $data['status'];
                unset($data['status']);
            }
            $data['update_time'] = time();
            $this->joomAccountModel->allowField(true)->save($data, ['id' => $id]);
            //修改缓存
            $cache = Cache::store('JoomAccount');
            foreach($data as $key=>$val) {
                $cache->updateTableRecord($id, $key, $val);
            }
            return true;
        } catch (\Exception $e) {
            throw new JsonErrorException($e->getMessage() . $e->getFile() . $e->getLine(), 400);
        }
    }
}