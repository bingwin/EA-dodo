<?php

namespace app\index\service;

use app\common\exception\JsonErrorException;
use app\common\model\ChannelNode;
use app\common\cache\Cache;
use app\common\service\Common;
use think\Request;
use think\Db;

/**
 * Created by PhpStorm.
 * Created by PhpStorm.
 * User: libaimin
 * Date: 2018/10/22
 * Time: 14:46
 */
class ChannelNodeService
{
    protected $channelNodeServer;

    public function __construct()
    {
        if (is_null($this->channelNodeServer)) {
            $this->channelNodeServer = new ChannelNode();
        }
    }

    /** 账号列表
     * @param Request $request
     * @return array
     * @throws \think\Exception
     */
    public function lists(Request $request)
    {
        $where = [];
        $params = $request->param();
        if (isset($params['channel_id']) && $params['channel_id'] !== '') {
            $where['channel_id'] = $params['channel_id'];
        }

        if (isset($params['channel_site']) && $params['channel_site'] !== '') {
            $where['channel_site'] = $params['channel_site'];
        }
        if(isset($params['type']) && $params['type'] !=-1&& !empty($params['type'])){
            $where['type']=$params['type'];
        }
        $order = 'id';
        $sort = 'desc';
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $field = '*';
        $count = $this->channelNodeServer->field($field)->where($where)->count();
        $accountList = $this->channelNodeServer->field($field)
            ->where($where)
            ->order($order, $sort)
            ->page($page, $pageSize)
            ->select();

        foreach ($accountList as $k => &$v) {
            $v['channel_name'] = Cache::store('Channel')->getChannelName($v['channel_id']);
            $v['node_info'] = json_decode($v['node_info'],true);
            $userInfo = Cache::store('user')->getOneUser($v['creator_id']);
            $userName = '';
            if (!empty($userInfo)) {
                $userName = $userInfo['realname'];
            }
            $roleName = (new Role())->getRoleNameByUserId($v['creator_id']);
            $v['creator_id'] = $roleName .'-'. $userName;
            $roleName = (new Role())->getRoleNameByUserId($v['updater_id']);
            $userInfo = Cache::store('user')->getOneUser($v['updater_id']);
            $userName = $userInfo['realname']??'';
            $v['updater_id'] = $roleName .'-'. $userName;
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
     * @param $data
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function saveOld($data)
    {
        $time = time();
        unset($data['name']);
        $data['create_time'] = $time;
        $data['update_time'] = $time;
        if ($this->channelNodeServer->isHas($data['channel_id'], $data['channel_site'], $data['website_url'])) {
            throw new JsonErrorException('账号已经存在', 500);
        }
        $this->channelNodeServer->allowField(true)->isUpdate(false)->save($data);
        //获取最新的数据返回
        $new_id = $this->channelNodeServer->id;
        return $this->read($new_id);
    }

    /**
     * 保存信息
     * @param $data
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function save($data)
    {
        $time = time();
        $data['create_time'] = $time;
        $data['update_time'] = $time;
        if ($this->channelNodeServer->isHas($data['channel_id'], $data['channel_site'], $data['website_url'])) {
            throw new JsonErrorException('账号已经存在', 500);
        }
        $userInfo = Common::getUserInfo();
        $data['creator_id'] = $data['updater_id'] = $userInfo['user_id'] ?? 0;
        $this->channelNodeServer->allowField(true)->isUpdate(false)->save($data);
        //获取最新的数据返回
        $new_id = $this->channelNodeServer->id;
        return $this->read($new_id);
    }

    /**
     * 账号信息
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function read($id)
    {
        $accountInfo = $this->channelNodeServer->field(true)->where(['id' => $id])->find();
        if (empty($accountInfo)) {
            throw new JsonErrorException('账号不存在', 500);
        }
        $userName = '';
        $userInfo = Cache::store('user')->getOneUser($accountInfo['creator_id']);
        if (!empty($userInfo)) {
            $userName = $userInfo['realname'];
        }
        $roleName = (new Role())->getRoleNameByUserId($accountInfo['creator_id']);
        $accountInfo['creator_id'] = $roleName . $userName;
        $accountInfo['node_info'] = json_decode($accountInfo['node_info'], true);
        $accountInfo['verification_node_info'] = json_decode($accountInfo['verification_node_info'], true);
        $roleName = (new Role())->getRoleNameByUserId($accountInfo['updater_id']);
        $userInfo = Cache::store('user')->getOneUser($accountInfo['updater_id']);
        $userName = $userInfo['realname']??'';
        $accountInfo['updater_id'] = $roleName .'-'. $userName;
        $accountInfo['type']=strval($accountInfo['type']);
        return $accountInfo;
    }

    /**
     * 更新
     * @param $id
     * @param $data
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update($id, $data)
    {

        $oldData = $this->channelNodeServer->isHas($data['channel_id'], $data['channel_site'], $data['website_url']);
        if ($oldData && $oldData['id'] != $id) {
            throw new JsonErrorException('账号已经存在', 500);
        }
        $userInfo = Common::getUserInfo();
        $data['update_time'] = time();
        $data['updater_id'] = $userInfo['user_id'];
        $this->channelNodeServer->allowField(true)->save($data, ['id' => $id]);
        return $this->read($id);
    }

    /**
     * 删除信息
     * @param $id
     * @return int
     */
    public function delete($id)
    {
        return $this->channelNodeServer->where('id', $id)->delete();
    }

    public $nodeType = [
        '输入框',
        '提交按钮',
        '验证码',
    ];



    public function nodeTpye()
    {
        $reData = [];
        foreach ($this->nodeType as $k=>$v){
            $reData[] =  [
                'value' => $k,
                'name' => $v,
            ];
        }
        return $reData;
    }
}