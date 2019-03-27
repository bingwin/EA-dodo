<?php

namespace app\index\service;

use app\common\cache\Cache;
use app\common\controller\Base;
use app\common\model\fummart\FummartAccount as FunmartAccountModel;
use app\common\service\ChannelAccountConst;
use think\Db;
use think\Exception;

/**
 * @title  Funmart账号管理
 * @module 账号管理
 * Class FunmartAccountService
 * Created by linpeng
 * updateTime: time 2019/3/14 13:51
 */
class FunmartAccountService extends Base
{
    /**
     * 保存账号信息
     * @param $data
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function save($data)
    {

        $ret = [
            'msg' => '',
            'code' => ''
        ];
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['platform_status'] = 1;  //设置为有效
        $funmartModel = new FunmartAccountModel();
        Db::startTrans();
        $re = $funmartModel->where('code', $data['code'])->field('id')->find();
        if (count($re)) {
            $ret['msg'] = '账户名重复';
            $ret['code'] = 400;
            return $ret;
        }
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_Fummart, $data['code']);
        try {

            $funmartModel->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $funmartModel->id;
            //删除缓存
            Cache::store('FunmartAccount')->delAccount();
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw new Exception($e->getMessage(), 500);
        }
        $accountInfo = $funmartModel->field(true)->where(['id' => $new_id])->find();
        if (count($accountInfo)) {
            $ret['msg'] = 'success';
            $ret['code'] = 200;
            return $ret;
        }
    }

    /**
     * 切换账号状态
     * @param $data
     */
    public function changeStatus($data)
    {
        $model = new FunmartAccountModel();
        $accountInfo = $model->where('id', $data['id'])->find();
        if (!$accountInfo) {
            throw new Exception('该记录不存在');
        }
        $accountInfo->status = $data['status'] == 1 ? 1 : 0;
        $accountInfo->updater_id = $data['updater_id'];
        $accountInfo->save();
        //删除缓存
        Cache::store('FunmartAccount')->delAccount();
        return ['message' => '修改成功'];
    }
}
