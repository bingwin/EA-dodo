<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/20
 * Time: 15:30
 */

namespace app\index\service;

use think\Db;
use think\Request;
use app\common\cache\Cache;
use app\common\service\Encryption;
use app\common\model\account\WorldfirstAccount;
use app\common\exception\JsonErrorException;

class WorldfirstService
{

    protected $model;
    /**
     * @var \app\common\cache\driver\User
     */
    protected $cache;

    public function __construct()
    {
        if (is_null($this->model)) {
            $this->model = new WorldfirstAccount();
        }
        if (is_null($this->cache)) {
            $this->cache = Cache::store('user');
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
     * 获取列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getWorldfirstList()
    {
        $request = Request::instance();
        $params = $request->param();

        $order = 'worldfirst_account.id';
        $sort = 'desc';
        $sortArr = [
            'ip_name'     => 'worldfirst_account.ip_name',
            'ip_address'  => 'worldfirst_account.ip_address',
            'wf_account'  => 'worldfirst_account.wf_account',
            'operator_id' => 'worldfirst_account.operator_id',
            'status'      => 'worldfirst_account.status',
            'create_time' => 'worldfirst_account.create_time',
        ];
        if (!empty($params['order_by']) && !empty($sortArr[$params['order_by']])) {
            $order = $sortArr[$params['order_by']];
        }
        if (!empty($params['sort']) && in_array($params['sort'], ['asc', 'desc'])) {
            $sort = $params['sort'];
        }

        $where = $this->getWhere($params);
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $field = 'id,ip_name,ip_address,wf_account,belong,operator_id,status,create_id,create_time';

        $count = $this->model
            ->where($where)
            ->count();
        $list = $this->model
            ->field($field)
            ->where($where)
            ->order($order, $sort)
            ->page($page, $pageSize)
            ->select();

        foreach ($list as $key => $item) {
            $list[$key]['operator'] = $this->cache->getOneUserRealname($item['operator_id']);
            $list[$key]['create'] = $this->cache->getOneUserRealname($item['create_id']);
            $list[$key]['create_time'] = date('Y-m-d H:i:s',$item['create_time']);
        }
        $result = [
            'data' => $list,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;

    }

    /**
     * 根据ID查询记录
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\exception\DbException
     */
    public function read($id)
    {
        $info = $this->model->where(['id' => $id])->find();
        if (!$info) {
            $this->error = '查询无记录';
            return false;
        }

        $info['operator'] =  $this->cache->getOneUserRealname($info['operator_id']);
        $info['create']   =  $this->cache->getOneUserRealname($info['create_id']);
        $info['create_time']   =  date('Y-m-d H:i:s',$info['create_time']);
        $info['update_time']   =  date('Y-m-d H:i:s',$info['update_time']);
        return $info;
    }

    /**
     * 保存记录信息
     * @param $data
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\exception\DbException
     */
    public function save($data)
    {
        Db::startTrans();
        try {
            if (isset($data['wf_password'])) {
                $Encryption = new Encryption();
                $data['wf_password'] = $Encryption->encrypt($data['wf_password']);
            }
            $this->model->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $this->model->id;
            Db::commit();
        } catch (JsonErrorException $e) {
            $this->error = $e->getMessage();
            Db::rollback();
            return false;
        }

        $info = $this->model->field(true)->where(['id' => $new_id])->find();
        $info['create_time'] = date('Y-m-d H:i:s',$info['create_time']);
        return $info;
    }

    /**
     * 更新记录
     * @param $id
     * @param $data
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\exception\DbException
     */
    public function update($id, $data)
    {
        if (!$this->read($id)) {
            return false;
        }

        Db::startTrans();
        try {
            unset($data['id']);

            if ($data['wf_password']) {
                $Encryption = new Encryption();
                $data['wf_password'] = $Encryption->encrypt($data['wf_password']);
            }

            $this->model->allowField(true)->save($data, ['id' => $id]);
            Db::commit();
        } catch (JsonErrorException $e) {
            $this->error = $e->getMessage(). $e->getFile() . $e->getLine();
            Db::rollback();
            return false;
        }

        $info = $this->model->field(true)->where(['id' => $id])->find();
        $info['operator'] =  $this->cache->getOneUserRealname($info['operator_id']);
        $info['create']   =  $this->cache->getOneUserRealname($info['create_id']);
        return $info;
    }

    /**
     * 明文显示密码
     * @param $id
     * @return bool|string
     * @throws \think\exception\DbException
     */
    public function showPassword($id)
    {
        $result = $this->read($id);

        if (!$result) {
            return false;
        }

        $Encryption = new Encryption();
        return $Encryption->decrypt($result['wf_password']);
    }

    /**
     * 编辑
     * @param $id
     * @return bool|string
     * @throws \think\exception\DbException
     */
    public function editStatus($id, $status)
    {
        $result = $this->read($id);

        if (!$result) {
            return false;
        }
        $data['status'] = 0;
        if ($status == 1) {
            $data['status'] = 1;
        }
        return $this->model->edit($data, ['id'=>$id]);
    }

    /**
     * 查询条件获取
     * @param $params
     * @return array
     */
    public function getWhere($params)
    {
        $where = [];
        if (isset($params['status']) && ($params['status'] !== '')) {
            $where['worldfirst_account.status'] = ['eq', $params['status']];
        }

        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'ip_name':
                    $where['worldfirst_account.ip_name'] = ['like', '%'.$params['snText'].'%'];
                    break;
                case 'ip_address':
                    $where['worldfirst_account.ip_address'] = ['like', '%'.$params['snText'].'%'];
                    break;
                case 'wf_account':
                    $where['worldfirst_account.wf_account'] = ['like', '%'.$params['snText'].'%'];
                    break;
                default:
                    break;
            }
        }

        if (isset($params['belong']) && ($params['belong'] !== '')) {
            $where['worldfirst_account.belong'] = ['eq', $params['belong']];
        }

        if (isset($params['operator_id']) && ($params['operator_id'] !== '')) {
            $where['worldfirst_account.operator_id'] = ['eq', $params['operator_id']];
        }

        return $where;
    }




}