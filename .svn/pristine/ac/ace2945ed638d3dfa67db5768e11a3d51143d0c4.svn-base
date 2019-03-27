<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/20
 * Time: 15:03
 */

namespace app\index\controller;

use think\Request;
use app\common\controller\Base;
use app\index\service\PayoneerService;
use app\common\service\Common as CommonService;
use app\common\validate\Payoneer as PayoneerValidate;


/**
 * @module 收款账号管理
 * @title Payoneer账户管理
 * @url /payoneer
 * @author zhuda
 * @package app\index\controller
 */
class PayoneerAccount extends Base
{
    protected $service;

    public function __construct()
    {
        parent::__construct();
        if (is_null($this->service)) {
            $this->service = new PayoneerService();
        }
    }


    /**
     * @title Payoneer账号列表
     * @method GET
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $result = $this->service->getPayoneerList();
        return json($result, 200);
    }

    /**
     * @title 显示账号详细.
     * @param $id
     * @method GET
     * @url /payoneer/:id/edit
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function edit($id)
    {
        $result = $this->service->read($id);

        if (!$result) {
            return json(['message'=>$this->service->getError()], 400);
        }
        return json($result, 200);
    }


    /**
     * @title 新增lianlian账号记录
     * @method POST
     * @url /payoneer/add
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function save(Request $request)
    {
        $validate = new PayoneerValidate();

        $data = $request->param();
        $time = time();
        $data['create_time'] = $time;
        $data['update_time'] = $time;
        $result = $validate->scene('add')->check($data);

        if ($result !== true) {
            return json(['message' => $validate->getError()], 400);
        }

        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['create_id']  = $user['user_id'];
            $data['updater_id'] = $user['user_id'];
        }

        $result = $this->service->save($data);

        if ($result === false) {
            return json(['message' => $this->service->getError()], 400);
        }

        return json(['message' => '新增成功','data' => $result]);
    }

    /**
     * @title 修改记录
     * @param Request $request
     * @param $id
     * @method PUT
     * @url /payoneer/:id/save
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function update(Request $request, $id)
    {
        $validate = new PayoneerValidate();
        $data = $request->param();
        $data['update_time'] = time();
        $result = $validate->scene('edit')->check($data);

        if ($result !== true) {
            return json(['message' => $validate->getError()], 400);
        }

        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_id'] = $user['user_id'];
        }
        $result = $this->service->update($id,$data);
        if (!$result) {
            return json(['message'=>$this->service->getError()], 400);
        }
        return json(['message' => '更改成功','data' => $result],200);
    }

    /**
     * @title 显示密码.
     * @param $id
     * @method GET
     * @url /payoneer/:id/show-pw
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function showPassword($id){
        $result = $this->service->showPassword($id);

        if (!$result) {
            return json(['message'=>$this->service->getError()], 400);
        }
        return json($result, 200);
    }

}