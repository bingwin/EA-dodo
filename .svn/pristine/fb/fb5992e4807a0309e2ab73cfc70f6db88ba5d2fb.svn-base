<?php
namespace app\index\controller;

use app\common\cache\Cache;
use app\common\service\ChannelAccountConst;
use think\Request;
use app\common\controller\Base;
use app\common\service\Common as CommonService;
use app\common\model\joom\JoomAccount as JoomAccountModel;
use app\index\service\JoomAccountService;
use think\Db;

/**
 * @module 账号管理
 * @title joom账号管理
 * @author zhangdongdong
 * @url /joom-account
 * Class Joom
 * @package app\index\controller
 */
class JoomAccount extends Base
{
    protected $joomAccountService;

    public function __construct()
    {
        parent::__construct();
        if(is_null($this->joomAccountService)){
            $this->joomAccountService = new JoomAccountService();
        }
    }

    /**
     * @title joom帐号列表
     * @method GET
     * @url /joom-account
     * @return \think\Response
     */
    public function index()
    {
        $request = Request::instance();

        $result = $this->joomAccountService->accountList($request);
        return json($result, 200);
    }

    /**
     * @title 保存新建的资源
     * @method POST
     * @url /joom-account
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $params = $request->param();
        $data = $params;
        $result = $this->validate($data, [
            'name|joom用户名' => 'require|unique:joom_account,account_name|length:2,50',
            'code|帐户简称' => 'require|unique:joom_account,code|length:2,50',
            'company|公司名称' => 'require|length:3,255',
        ]);
        if ($result !== true) {
            return json(['message' => $result], 400);
        }

        //必须要去账号基础资料里备案
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_Joom,$data['code']);
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['creator_id'] = $user['user_id'];
        }
        $accountInfo = $this->joomAccountService->save($data);
        return json(['message' => '新增成功','data' => $accountInfo]);
    }

    /**
     * @title 显示指定的资源
     * @param  int $id
     * @method GET
     * @url /joom-account/:id
     * @return \think\Response
     */
    public function read($id)
    {
        $result = $this->joomAccountService->read($id);
        return json($result, 200);
    }

    /**
     * @title 显示指定的资源
     * @param  int $id
     * @method GET
     * @url /joom-account/:id/edit
     * @return \think\Response
     */
    public function edit($id)
    {
        $result = $this->joomAccountService->read($id);
        return json($result, 200);
    }

    /**
     * @title 保存更新的资源
     * @param  \think\Request $request
     * @param  int $id
     * @method PUT
     * @url /joom-account/:id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $params = $request->param();
        $data = $params;
        $result = $this->validate($data, [
            'name|joom用户名' => 'require|unique:joom_account,account_name|length:2,50',
            'code|帐户简称' => 'require|unique:joom_account,code|length:2,50',
            'company|公司名称' => 'require|length:3,255',
        ]);
        if ($result !== true) {
            return json(['message' => $result], 400);
        }
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_id'] = $user['user_id'];
        }
        $this->joomAccountService->update($id,$data);
        return json(['message' => '更改成功']);
    }

    /**
     * @title JOOM账号停用，启用
     * @method POST
     * @url /joom-account/status
     * @return \think\Response
     */
    public function changeStatus()
    {
        $request = Request::instance();
        $id = $request->post('id', 0);
        $data = $request->post();

        //判断参数是否存在；
        if(!isset($data['status']) && !isset($data['platform_status'])) {
            return json(['message' => '参数为空，请传参数 status或 platform_status ']);
        }

        if(isset($data['status'])) {
            $data['status'] = $data['status'] == 1 ? 1 : 0;
        }

        if(isset($data['platform_status'])) {
            $data['platform_status'] = $data['platform_status'] == 1 ? 1 : 0;
        }

        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_id'] = $user['user_id'];
        }
        $this->joomAccountService->status($id,$data);
        return json(['message' => '操作成功']);
    }

    /**
     * @title 批量开启
     * @url batch-set
     * @method post
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function batchSet(Request $request)
    {
        $params = $request->post();
        $result = $this->validate($params, [
            'ids|帐号ID' => 'require|min:1',
            'is_invalid|系统状态' => 'require|number',
        ]);

        if ($result != true) {
            throw new Exception($result);
        }

        //实例化模型
        $model = new JoomAccountModel();

        if (isset($params['is_invalid']) && $params['is_invalid'] != '') {
            $data['is_invalid'] = (int)$params['is_invalid'];   //1 启用， 0未启用
        }

        $idArr = array_merge(array_filter(array_unique(explode(',',$params['ids']))));

        //开启事务
        Db::startTrans();
        try {
            if (empty($data)) {
                return json(['message' => '数据参数不能为空'], 200);
            }

            $data['update_time'] = time();
            $model->allowField(true)->update($data,['id' => ['in', $idArr]]);
            Db::commit();

            //更新缓存
            $cache = Cache::store('JoomAccount');
            foreach ($idArr as $id) {
                foreach ($data as $k => $v) {
                    $cache->updateTableRecord($id, $k, $v);
                }
            }
            return json(['message' => '更新成功'], 200);
        } catch (Exception $ex) {
            Db::rollback();
            return json(['message' => '更新失败'], 400);
        }
    }
}
