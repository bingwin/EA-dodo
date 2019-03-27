<?php

namespace app\index\service;

use app\common\exception\JsonErrorException;
use app\common\model\vova\VovaAccount;
use app\common\cache\Cache;
use app\common\service\ChannelAccountConst;
use cd\CdBaseApi;
use cd\CdOrderApi;
use think\Request;
use think\Db;
use vova\VovaAccountApi;

/**
 * Created by PhpStorm.
 * User: zhaixueli
 * Date: 2017/5/25
 * Time: 11:17
 */
class VovaAccountService
{
    protected $vovaAccountModel;
    protected $error = '';

    public function __construct()
    {
        if (is_null($this->vovaAccountModel)) {
            $this->vovaAccountModel = new VovaAccount();
        }
    }

    public function getError()
    {
        return $this->error;
    }


    /** 账号列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function accountList($params = [], $page = 1, $pageSize = 10)
    {
        $where = $this->getWhere($params);
        $where1 = $this->handleTaskTime($params);
        if(!empty($where1))
        {
            $where = array_merge($where,$where1);
        }
        $field = 'id,account_name,code,is_invalid status,create_time,is_authorization,sync_delivery,download_order,download_listing';
        $sort = "create_time desc";
        //排序刷选
        if (param($params, 'sort_type') && in_array($params['sort_type'], ['account_name', 'code', 'created_at'])) {
            $sort_by = $params['sort_val'] == 2 ? 'DESC' : ' ';
            $sort = $params['sort_type'] . " " . $sort_by . " ,create_time desc";
            unset($sort_by);
        }
        $count = $this->vovaAccountModel->field($field)->where($where)->count();
        $accountList = $this->vovaAccountModel->field($field)->where($where)->order($sort)->page($page, $pageSize)->fetchSql(false)->select();
        $result = [
            'data' => $accountList,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;
    }

    /**
     * 新增
     * @param $data
     * @return bool
     */
    public function add($data, $uid = 0)
    {
        try {
            $id = $data['id'] ?? 0;
            $time = time();
            $save_data['code'] = $data['code'];
            $save_data['updated_time'] = $time;
            $save_data['download_order'] = $data['download_order'] ?? 0;
            $save_data['download_listing'] = $data['download_listing'] ?? 0;
            $save_data['sync_delivery'] = $data['sync_delivery'] ?? 0;
            $save_data['base_account_id'] = $data['base_account_id'] ?? 0;
            if ($id == 0) {
                //必须要去账号基础资料里备案
                \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_Vova,$data['code']);
                //检查产品是否已存在
                if ($this->vovaAccountModel->check(['account_name' => $data['account_name']])) {
                    $this->error = 'Vova账号已经存在无法重复添加';
                    return false;
                }
                if ($this->vovaAccountModel->check(['code' => $data['code']])) {
                    $this->error = 'Vova简称已经存在无法重复添加';
                    return false;
                }
                $save_data['account_name'] = $data['account_name'];
                $save_data['create_time'] = $time;
               $save_data['created_user_id'] = $uid;
               $save_data['merchant_id'] = '';
               $save_data['nanjing_id'] = '';
               $save_data['jinhua_id'] = '';


            } else{
                $is_ok = $this->vovaAccountModel->field('id')->where(['code' => $data['code']])->where('id','<>',$id)->find();
                if($is_ok){
                    $this->error = $data['code'].'简称已经存在无法修改';
                    return false;
                }
                $save_data['id'] = $id;
                //更新缓存
                $cache = Cache::store('VovaAccount');
                foreach ($save_data as $key => $val) {
                    $cache->updateTableRecord($id, $key, $val);
                }
            }
            $this->vovaAccountModel->add($save_data);
            return $this->getOne($id);
        } catch (Exception $e) {
            throw new JsonErrorException($e->getMessage());
        }
    }


    /** 获取账号信息
     * @param $id
     * @return array
     */
    public function getOne($id)
    {
        $field = 'id,account_name,code,is_invalid status,create_time,is_authorization,sync_delivery,download_order,download_listing';
        if ($id == 0) {
            return $this->vovaAccountModel->field($field)->order('id desc')->find();
        }
        return $this->vovaAccountModel->where('id', $id)->field($field)->find();
    }

    /** 获取订单授权信息
     * @param $id
     * @return array
     */
    public function getTokenOne($id)
    {
        return $this->vovaAccountModel->where('id', $id)->field('id,code,account_name,access_key_id,secret_key,merchant_id,nanjing_id,jinhua_id')->find();
    }

    /**
     * 封装where条件
     * @param array $params
     * @return array
     */
    function getWhere($params = [])
    {

        $where = [];
        if (isset($params['status']) && $params['status'] != '' ) {
            $params['status'] = $params['status'] == 'true' ? 1 : 0;
            $where['is_invalid'] = ['eq', $params['status']];
        }

        if (isset($params['authorization']) && $params['authorization'] > -1&& $params['authorization']!='') {
            $where['is_authorization'] = ['eq', $params['authorization']];
        }
        if (isset($params['download_order']) && $params['download_order'] > -1) {
            if (empty($params['download_order'])) {
                $where['download_order'] = ['eq', 0];
            } else {
                $where['download_order'] = ['>', 0];
            }
        }

        if (isset($params['download_listing']) && $params['download_listing'] > -1) {
            if (empty($params['download_listing'])) {
                $where['download_listing'] = ['eq', 0];
            } else {
                $where['download_listing'] = ['>', 0];
            }
        }
        if (isset($params['sync_delivery']) && $params['sync_delivery'] > -1) {
            if (empty($params['sync_delivery'])) {
                $where['sync_delivery'] = ['eq', 0];
            } else {
                $where['sync_delivery'] = ['>', 0];
            }
        }
        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'account_name':
                    $where['account_name'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                case 'code':
                    $where['code'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                default:
                    break;
            }
        }


        if ( isset($params['taskCondition'])  && isset($params['taskNum'])  &&  !empty($params['taskNum'])) {
            $where['ip'] = [trim($params['taskCondition']), $params['taskNum']];
        }

        return $where;
    }

    private function handleTaskTime($param)
    {
        $where = [];
        $task_time = param($param,'taskTime');
        if(is_numeric($task_time))
        {
           $taskName = param($param,'taskName');
           $taskCondition = trim(param($param,'taskCondition'));
           $allow_task_name = ['download_listing','download_order','sync_delivery'];
           $allow_condition = ['lt','gt','eq'];
           if(in_array($taskName,$allow_task_name) && in_array($taskCondition,$allow_condition))
           {
               $where[$taskName] = [$taskCondition,$task_time];
           }
        }else{
            return $where;
        }
        return $where;
    }

    /** 状态
     * @param $data
     * @return array
     */
    public function changeStatus($data)
    {

        $cache = Cache::store('VovaAccount');
        $account = $cache->getAccount($data['id']);
        if (!isset($account)) {
            $this->error = '账号不存在';
            return false;
        }
        try {
            $updata = [];
            if (isset($data['status'])) {
                $updata['is_invalid'] = $data['status'];
            }
            $updata['update_time'] = time();
            $this->vovaAccountModel->allowField(true)->save($updata, ['id' => $data['id']]);
            //修改缓存
            $cache = Cache::store('VovaAccount');
            foreach ($updata as $key => $val) {
                $cache->updateTableRecord($data['id'], $key, $val);
            }
            return true;
        } catch (\Exception $e) {
            throw new JsonErrorException($e->getMessage() . $e->getFile() . $e->getLine(), 400);
        }
    }



    /** 刷新refresh_token
     * @param $data
     * @param $uid
     * @return array
     */
    public function refresh_token($data, $uid = 0)
    {
        if (empty($data['access_key_id']) || empty($data['secret_key']) || empty($data['id']) || empty($data['merchant_id']) ) {
            $this->error = '帐号授权信息不完整';
            return false;
        }
        $cache = Cache::store('VovaAccount');
        $account = $cache->getAccount($data['id']);
        if (!isset($account)) {
            $this->error = '账号不存在';
            return false;
        }
        $account['access_key_id'] = $updata['access_key_id'] = $data['access_key_id'];
        $account['secret_key'] = $updata['secret_key'] = $data['secret_key'];
        $account['merchant_id'] = $updata['merchant_id'] = $data['merchant_id']; //中山仓物流ID绑定
        $account['nanjing_id'] = $updata['nanjing_id'] = $data['nanjing_id'];//南京仓物流id绑定
        $account['jinhua_id'] = $updata['jinhua_id'] = $data['jinhua_id']; //金华仓物流ID绑定
        $updata['is_authorization'] = 1;
        $this->vovaAccountModel->allowField(true)->save($updata, ['id' => $data['id']]);
        //修改缓存
        $cache = Cache::store('VovaAccount');
        foreach ($updata as $key => $val) {
            $cache->updateTableRecord($data['id'], $key, $val);
        }
        return true;
    }


}