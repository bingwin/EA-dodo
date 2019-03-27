<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/12
 * Time: 10:36
 */

namespace app\index\service;

use app\common\cache\Cache;
use app\common\exception\JsonErrorException;
use app\common\model\oberlo\OberloAccount;
use app\common\service\ChannelAccountConst;
use think\Db;
use think\Validate;

class OberloAccountService
{
    private $accountModel;
    public function __construct()
    {
        $this->accountModel = new OberloAccount();
    }

    /**
     * 账号列表
     * @param Request $request
     * @return array
     */
    public function accountList($params)
    {
        $where = $this->getWhere($params);

        $page = param($params,"page",1);
        $pageSize = param($params,"pageSize",50);

        $count = $this->accountModel->where($where)->count();
        $accountList = $this->accountModel->where($where)->fetchSql(false)->page($page, $pageSize)->select();
       // echo $accountList;die;
        $result = [
            'data' => $accountList,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;
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
            $params['status'] = $params['status'] == 1 ? 1 : 0;
            $where['status'] = ['eq', $params['status']];
        }
        if (isset($params['is_authorization']) && $params['is_authorization'] != '') {
            $where['is_authorization'] = ['eq', $params['is_authorization']];
        }
        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'name':
                    $where['name'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                case 'code':
                    $where['code'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                default:
                    break;
            }
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

        if (isset($params['taskName']) && isset($params['taskCondition']) && isset($params['taskTime']) && $params['taskName'] !== '' && $params['taskTime'] !== '') {
            $where[$params['taskName']] = [trim($params['taskCondition']), $params['taskTime']];
        }
        return $where;
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
        $data['create_time'] = time();
        $data['update_time'] = time();

        $validateAccount = validate('OberloAccount');
        if (!$validateAccount->check($data)) {
            $ret['msg'] = $validateAccount->getError();
            $ret['code'] = 400;
            return $ret;
        }
        BasicAccountService::isHasCode(ChannelAccountConst::channel_Oberlo, $data['code']);
        Db::startTrans();
        try {
            $this->accountModel->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $this->accountModel->id;
            //删除缓存
            Cache::store('OberloAccount')->clearCache();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new JsonErrorException($e->getMessage(), 500);
        }
        $accountInfo = $this->accountModel->field(true)->where(['id' => $new_id])->find();
        return $accountInfo;
    }

    /**
     * 更新资源
     * @param $data
     * @return OberloAccount
     * @throws \think\exception\DbException
     */
    public function update($data)
    {
        $id = intval($data['id']);
        if ($this->accountModel->isHas($id, $data['code'], '')) {
            throw new JsonErrorException('代码或者用户名已存在', 400);
        }
        $model = $this->accountModel->get($id);

        Db::startTrans();
        try {
            //赋值
            $model->code = isset($data['code'])?$data['code']:'';
            if(isset($data['name']))
            {
                $model->name = $data['name'];
            }
            $model->download_order = isset($data['download_order'])?$data['download_order']:0;
            $model->download_listing = isset($data['download_listing'])?$data['download_listing']:0;
            $model->sync_delivery = isset($data['sync_delivery'])?$data['sync_delivery']:0;
            $model->update_time =time();
            $model->update_id = $data['update_id'];
            unset($data['id']);

            //更新数据
            $model->allowField(true)->isUpdate(true)->save();

            //删除缓存
            Cache::store('OberloAccount')->clearCache();
            Db::commit();
            return $model;
        } catch (\Exception $e) {
            Db::rollback();
            throw new JsonErrorException($e->getMessage() . $e->getFile() . $e->getLine(), 500);
        }
    }

    /**
     * 读取指定资源
     */
    public function read($id)
    {
        if(intval($id) <= 0)
        {
            throw new JsonErrorException('账号不存在',500);
        }
        $accountInfo = Cache::store('OberloAccount')->getTableRecord($id);
        if(empty($accountInfo)){
            throw new JsonErrorException('账号不存在',500);
        }
        return $accountInfo;
    }

    /**
     * 账号授权
     * @param $data
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function authorize($data)
    {
        $id = intval($data['id']);
        $account = $this->accountModel->where("id",$id)->find();
        if(!$account)
        {
            throw new JsonErrorException("账号不存在",500);
        }
        $rule = [
           ['token_key','require',"秘钥必填"]
        ];
        $validate = new Validate($rule);
        if(!$validate->check($data))
        {
            return ['message'=>$validate->getError(),'code'=>400];
        }
        $account->token_key = $data['token_key'];
        $account->update_id = $data['update_id'];
        $account->is_authorization = 1;
        $account->platform_status = 1;
        $account->save();
        Cache::store('OberloAccount')->clearCache();
        return ['message'=>"授权成功",'code'=>200];
    }

    /**
     * 切换账号状态
     * @param $data
     */
    public function changeStatus($data)
    {
        $accountInfo = $this->accountModel->where('id', $data['id'])->find();
        if (!$accountInfo) {
            throw new Exception('该记录不存在');
        }
        $accountInfo->status = $data['status'] == 1 ? 1 : 0;
        $accountInfo->update_id = $data['update_id'];
        $accountInfo->save();
        //删除缓存
        Cache::store('DarazAccount')->delAccount();
        return ['message' => '修改成功'];
    }

}