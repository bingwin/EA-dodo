<?php

namespace app\index\service;

use app\common\exception\JsonErrorException;
use app\common\model\cd\CdAccount;
use app\common\cache\Cache;
use app\common\service\ChannelAccountConst;
use cd\CdBaseApi;
use cd\CdOrderApi;
use think\Request;
use think\Db;
use cd\CdAccountApi;

/**
 * Created by PhpStorm.
 * User: libaimin
 * Date: 2017/5/25
 * Time: 11:17
 */
class CdAccountService
{
    protected $cdAccountModel;
    protected $error = '';

    public function __construct()
    {
        if (is_null($this->cdAccountModel)) {
            $this->cdAccountModel = new CdAccount();
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
        $field = 'id,account_name,code,is_invalid status,create_time,update_time,is_authorization,sync_delivery,download_order,download_listing,expiry_time,enabled';

        $sort = "create_time desc";
        //排序刷选
        if (param($params, 'sort_type') && in_array($params['sort_type'], ['account_name', 'code', 'created_at', 'expiry_time'])) {
            $sort_by = $params['sort_val'] == 2 ? 'DESC' : ' ';
            $sort = $params['sort_type'] . " " . $sort_by . " ,create_time desc";
            unset($sort_by);
        }

        $count = $this->cdAccountModel->field($field)->where($where)->count();
        $accountList = $this->cdAccountModel->field($field)->where($where)->order($sort)->page($page, $pageSize)->select();
        $thisTime = time();
        foreach ($accountList as &$item) {

        }
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

            $save_data['update_time'] = $time;
            $save_data['code'] = $data['code'];
            $save_data['download_order'] = $data['download_order'] ?? 0;
            $save_data['download_listing'] = $data['download_listing'] ?? 0;
            $save_data['sync_delivery'] = $data['sync_delivery'] ?? 0;
            $save_data['base_account_id'] = $data['base_account_id'] ?? 0;



            if ($id == 0) {
                //检查产品是否已存在
                if ($this->cdAccountModel->check(['account_name' => $data['account_name']])) {
                    $this->error = 'Cd账号已经存在无法重复添加';
                    return false;
                }
                if ($this->cdAccountModel->check(['code' => $data['code']])) {
                    $this->error = 'Cd简称已经存在无法重复添加';
                    return false;
                }
                //必须要去账号基础资料里备案
                \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_CD,$data['code']);
                $save_data['account_name'] = $data['account_name'];
                $save_data['create_time'] = $time;
                $save_data['creator_id'] = $uid;

            } else{
                $is_ok = $this->cdAccountModel->field('id')->where(['code' => $data['code']])->where('id','<>',$id)->find();
                if($is_ok){
                    $this->error = 'Cd简称已经存在无法修改';
                    return false;
                }

                $save_data['id'] = $id;
                $save_data['updater_id'] = $uid;
                //更新缓存
                $cache = Cache::store('CdAccount');
                foreach ($save_data as $key => $val) {
                    $cache->updateTableRecord($id, $key, $val);
                }
            }
            $this->cdAccountModel->add($save_data);
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
        $field = 'id,account_name,code,is_invalid status,create_time,update_time,is_authorization,sync_delivery,download_order,download_listing,expiry_time,enabled';
        if ($id == 0) {
            return $this->cdAccountModel->field($field)->order('id desc')->find();
        }
        return $this->cdAccountModel->where('id', $id)->field($field)->find();
    }

    /** 获取订单授权信息
     * @param $id
     * @return array
     */
    public function getTokenOne($id)
    {
        return $this->cdAccountModel->where('id', $id)->field('id,code,account_name,email,client_id,client_secret')->find();
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
        if (isset($params['authorization']) && $params['authorization'] > -1) {
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
        if (isset($params['taskName']) && isset($params['taskCondition']) && isset($params['taskTime']) && $params['taskName'] !== '' && $params['taskTime'] !== '') {
            $where[$params['taskName']] = [trim($params['taskCondition']), $params['taskTime']];
        }
        return $where;
    }

    /** 状态
     * @param $data
     * @return array
     */
    public function changeStatus($data)
    {
        $cache = Cache::store('CdAccount');
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
            $this->cdAccountModel->allowField(true)->save($updata, ['id' => $data['id']]);
            //修改缓存
            $cache = Cache::store('CdAccount');
            foreach ($updata as $key => $val) {
                $cache->updateTableRecord($data['id'], $key, $val);
            }
            return true;
        } catch (\Exception $e) {
            throw new JsonErrorException($e->getMessage() . $e->getFile() . $e->getLine(), 400);
        }
    }

    /** 验证账号
     * @param $data
     * @return array
     */
    public function check($data)
    {
        $this->error = '验证失败：';
        $cache = Cache::store('CdAccount');
        $account = $cache->getAccount($data['id']);
        if (!isset($account)) {
            $this->error .= '账号不存在';
            return false;
        }
        if(!$account['client_id'] || !$account['client_secret']){
            $this->error .= '账号程序ID或者秘钥不存在';
            return false;
        }
        try {

            $api = new CdOrderApi($account);
            $token = $api->getTokens();
            if(!$token) {
                $this->error .= '账号秘钥没有更新，或者没有绑定IP';
                return false;
            }
            $updata = [];
            $updata['is_authorization'] = 1;
            $updata['update_time'] = time();
            $this->cdAccountModel->allowField(true)->save($updata, ['id' => $data['id']]);
            //修改缓存
            $cache = Cache::store('CdAccount');
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
        if (empty($data['client_id']) || empty($data['client_secret']) || empty($data['id'])) {
            $this->error = '帐号授权信息不完整';
            return false;
        }
        $cache = Cache::store('CdAccount');
        $account = $cache->getAccount($data['id']);
        if (!isset($account)) {
            $this->error = '账号不存在';
            return false;
        }
        $account['client_id'] = $updata['client_id'] = $data['client_id'];
        $account['client_secret'] = $updata['client_secret'] = $data['client_secret'];
        $updata['enabled'] = 1;


        $this->cdAccountModel->allowField(true)->save($updata, ['id' => $data['id']]);
        //修改缓存
        $cache = Cache::store('CdAccount');
        foreach ($updata as $key => $val) {
            $cache->updateTableRecord($data['id'], $key, $val);
        }
        return true;
    }


}