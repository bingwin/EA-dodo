<?php

namespace app\index\service;

use app\common\model\aliexpress\AliexpressAccount;
use app\common\service\ChannelAccountConst;
use Exception;
use app\common\cache\Cache;
use app\common\model\aliexpress\AliexpressAccount as AliexpressAccountModel;
use think\Config;
use service\alinew\AliexpressApi;
use think\Db;

/**
 * @desc 速卖通账号管理
 * @author Jimmy <554511322@qq.com>
 * @date 2018-04-10 11:21:11
 */
class AliexpressAccountService
{
    public static $topicListMap = [
        'message'=>[
            1=>['topic'=>'aliexpress_message_Pushnewmsg','status'=>0,'name'=>'站内信新消息主动推送']
        ],
        'order'=>[
            2=>['topic'=>'aliexpress_order_Finish','status'=>0,'name'=>'交易成功'],
            3=>['topic'=>'aliexpress_order_FundProcessing','status'=>0,'name'=>'资金处理中'],
            4=>['topic'=>'aliexpress_order_InCancel','status'=>0,'name'=>'取消订单中'],
            5=>['topic'=>'aliexpress_order_WaitBuyerAcceptGoods','status'=>0,'name'=>'等待买家收货'],
            6=>['topic'=>'aliexpress_order_SellerPartSendGoods','status'=>0,'name'=>'等待部分发货'],
            7=>['topic'=>'aliexpress_order_WaitSellerSendGoods','status'=>0,'name'=>'等待卖家发货'],
            8=>['topic'=>'aliexpress_order_WaitGroupSuccess','status'=>0,'name'=>'等待成团'],
            9=>['topic'=>'aliexpress_order_WaitSellerExamineMoney ','status'=>0,'name'=>'待卖家验款'],
            10=>['topic'=>'aliexpress_order_RiskControl','status'=>0,'name'=>'风控24小时'],
            11=>['topic'=>'aliexpress_order_PlaceOrderSuccess','status'=>0,'name'=>'下单成功']
        ]
    ];
    private $url = 'https://oauth.aliexpress.com/token'; //速卖通获取access_token请求的url
    
    /**
     * @desc 获取access_token
     * @param arrray $params  前端提交过来的数据信息
     * @author Jimmy <554511322@qq.com>
     * @date 2018-04-10 11:25:11
     */

    public function getToken($params)
    {
        //验证用户提交过来的数据信息
        $this->checkParams($params);
        //组装请求数据
        $data = $this->getData($params);
        //curl 请求数据
        $res = $this->getAccessToken($data);
        //更新数据库表及缓存
        $this->updateAccount($params, $res);
    }

    /**
     * @desc 更新account的数据库及缓存信息
     * @param array $params 用户请求的数据信息
     * @param array $res curl获取到的数据信息
     * @author Jimmy <554511322@qq.com>
     * @date 2018-04-10 11:45:11
     */
    private function updateAccount($params, $res)
    {
        //组装更新数据
        $data = [];
        $data['client_id'] = $params['client_id'];
        $data['client_secret'] = $params['client_secret'];
        $data['access_token'] = $res['access_token'];
        $data['refresh_token'] = $res['refresh_token'];
        $data['expiry_time'] = $res['expire_time']/1000;//微妙转化为秒
        $data['user_nick'] = $res['user_nick'];
        $data['update_time'] = time();
        $data['is_authorization'] = 1;
        $data['aliexpress_enabled'] = 1;
        //获取数据model
        $model = AliexpressAccountModel::get(['id' => $params['id']]);
        $model->allowField(true)->save($data);
        //更新缓存
        $cache = Cache::store('AliexpressAccount');
        $account = $model->toArray();
        foreach ($account as $key => $val) {
            $cache->updateTableRecord($model->id, $key, $val);
        }
    }

    /**
     * @desc curl 请求数据信息
     * @param type $data
     * @author Jimmy <554511322@qq.com>
     * @date 2018-04-10 11:36:11
     */
    private function getAccessToken($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //指定post数据
        curl_setopt($ch, CURLOPT_POST, true);
        //添加变量
        curl_setopt($ch, CURLOPT_POSTFIELDS, substr($data, 0, -1));
        $output = curl_exec($ch);
        $res = json_decode($output, true);
        //如果授权成功
        if (param($res, 'error_msg') && param($res, 'error_code')) {
            throw new Exception($res['error_msg']);
        }
        return $res;
    }

    /**
     * @desc 组装用户请求的数据
     * @param array $params 用户请求的数据信息
     * @author Jimmy <554511322@qq.com>
     * @date 2018-04-10 11:32:11
     */
    private function getData($params)
    {
        //组装请求参数
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $params['client_id'],
            'client_secret' => $params['client_secret'],
            'code' => $params['authorization_code'],
            'sp' => 'se',
            'redirect_uri' => Config::get('redirect_uri'),
        ];
        $res = '';
        foreach ($data as $key => $value) {
            $res .= $key . '=' . urlencode($value) . '&';
        }
        return $res;
    }

    /**
     * @desc 验证授权时提交过来的数据信息
     * @author Jimmy <554511322@qq.com>
     * @date 2018-04-10 11:27:11
     */
    private function checkParams($params)
    {
        if (empty($params['id'])) {
            throw new Exception('参数错误:ID不能为空!');
        }
        if (empty($params['client_id'])) {
            throw new Exception('参数错误:账号ID client_id不能为空!');
        }
        if (empty($params['client_secret'])) {
            throw new Exception('参数错误:账号秘钥 client_secret不能为空!');
        }
        if (empty($params['authorization_code'])) {
            throw new Exception('参数错误:授权码 authorization_code不能为空!');
        }
    }

    /**
     * @desc 处理响应数据
     * @param string $data 执行api请求返回的订单数据json字符串
     * @return array 结果集
     * @author Jimmy <554511322@qq.com>
     * @date 2018-03-19 15:20:11
     */
    public function dealResponse($data)
    {
        //已经报错了,抛出异常信息
        if (isset($data->error_response) && $data->error_response) {
            throw new Exception($data->sub_msg, $data->code);
        }
        //如果没有result
        if (!isset($data->result)) {
            throw new Exception(json_encode($data));
        }
        return json_decode($data->result, true);
    }


    /**
     * @desc 获取账号的配置信息
     * @param int $id 账号对应的数据库表ID
     * @return array $config 账号配置信息
     * @author Jimmy <554511322@qq.com>
     * @date 2018-03-13 15:03:11
     */
    public function getConfig($id)
    {
        $info = Cache::store('AliexpressAccount')->getTableRecord($id);
        if (!$info || !isset($info['id'])) {
            //throw new Exception('账号信息缺失');
            return ['message' => '账号信息缺失'];
        }
        if (!param($info, 'client_id')) {
            //throw new Exception('账号ID缺失,请先授权!');
            return ['message' => '账号ID缺失,请先授权!'];
        }
        if (!param($info, 'client_secret')) {
           // throw new Exception('账号秘钥缺失,请先授权!');
            return ['message' => '账号秘钥缺失,请先授权!'];
        }
        if (!param($info, 'access_token')) {
           // throw new Exception('access token缺失,请先授权!');
            return ['message' => 'access token缺失,请先授权!'];
        }
        $config['id'] = $info['id'];
        $config['client_id'] = $info['client_id'];
        $config['client_secret'] = $info['client_secret'];
        $config['token'] = $info['access_token'];
        return $config;
    }

    public function batchUpdate($ids, $data)
    {
        $updateData = [
            'is_invalid' => param($data, 'is_invalid') ? 1 : 0,
            'download_listing' => param($data, 'download_listing'),
            'download_order' => param($data, 'download_order'),
            'download_message' => param($data, 'download_message'),
            'sync_delivery' => param($data, 'sync_delivery'),
            'download_health' => param($data, 'download_health')
        ];
        $updateWhere = ['id' => ['in', $ids]];
        $model = new AliexpressAccount();
        $trueIds = $model->where($updateWhere)->column('id');
        if(array_diff($ids, $trueIds)){
            throw new Exception('勾选错误');
        }
        $res = $model->allowField(true)->update($updateData,$updateWhere);
        if(! $res){
            throw new Exception('更新失败');
        }
        $result = $model->where($updateWhere)->select();
        //删除缓存
        $cache = Cache::store('AliexpressAccount');
        $aliexpressServ = new AliexpressAccountHealthService();
        foreach($result as $v){
            if (isset($data['download_health'])) {
                $aliexpressServ->openHealth($v['id'], $data['download_health']);
            }
            $cache->delAccount($v['id']);
        }
        return $result;
    }
    
    /**
     * @desc 为单个速卖通卖家账号开启消息通知
     * @author wangwei
     * @date 2018-9-28 19:35:34
     * @param int $account_id
     * @param array $topics_ids
     * @return number[]|string[]
     */
    public function notificationUserPermit($account_id, $topics_ids=[])
    {
        $return = [
            'ask'=>0,
            'message'=>'userPermitBatch error',
        ];
        try {
            //简单校验
            if(!$account_id){
                throw new Exception('account_id not empty');
            }
            //获取接口所需授权信息
            $config = $this->getConfig($account_id);
            if (!isset($config['id'])){
                throw new Exception('getConfig is empty');
            }
            
            //获取接口对象
            $api = AliexpressApi::instance($config)->loader('MessageNotification');
//             $api instanceof \service\alinew\operation\MessageNotification;

            //查询已有授权信息
            $model = new AliexpressAccountModel();
            $val = $model::where(['id'=>$account_id])->field('topics')->find();
            if(empty($val['topics'])){//如果数据库消息主题为空，则取默认的数据
                $data = AliexpressAccountService::$topicListMap;//取得默认数据
            }else{
                $data = json_decode($val['topics'], true);
            }
            
            $topic_arr = [];
            //处理当前操作的授权
            foreach ($data as $group_name =>$topics){
                foreach ($topics as $topic_id => $topic){
                    if(empty($topics_ids)){
                        $data[$group_name][$topic_id]['status'] = 1;
                    }else if(in_array($topic_id, $topics_ids)){
                        $topic_arr[] = $topic['topic'];
                        $data[$group_name][$topic_id]['status'] = 1;
                    }else{
                        $data[$group_name][$topic_id]['status'] = 0;
                    }
                }
            }
            $res = $api->userPermit($topic_arr);//调用接口给相应的消息主题开通服务
            $res = $this->dealResponse($res);
            if(!(isset($res['is_success']) && $res['is_success'])){
                throw new Exception('操作失败:' . ($res['sub_msg'] ? $res['sub_msg'] : ''));
            }
            
            $topics = json_encode($data);
            $model->save(['topics'=>$topics], ['id'=>$account_id]);//更新数据
            
            $return['ask'] = 1;
            $return['message'] = '操作成功';
            
        } catch (Exception $e) {
            $return['message'] = $e->getMessage();
        }
        
        return $return;
    }
    
    /**
     * @desc 为多个速卖通卖家账号开启消息通知
     * @author wangwei
     * @date 2018-9-28 19:35:34
     * @param int $account_id
     * @param array $topics_ids
     * @return number[]|string[]
     */
    public function notificationUserPermitBatch($account_ids, $topics_ids=[])
    {
        $return = [
            'ask'=>0,
            'message'=>'userPermitBatch error',
            'errors'=>[],
            'ids'=>[]
        ];
        if(!($account_ids && is_array($account_ids))){
            $return['message'] = 'account_ids not empty';
            return $return;
        }
        //批量处理
        foreach ($account_ids as $account_id){
            $nupRe = $this->notificationUserPermit($account_id, $topics_ids);
            if(!$nupRe['ask']){
                $accInfo = Cache::store('AliexpressAccount')->getTableRecord($account_id);
                $return['errors'][] = "账号:{$accInfo['code']},开启消息通知错误:{$nupRe['message']}";
            }else{
                $return['ids'][] = $account_id;
            }
        }
        if(empty($return['errors'])){
            $return['ask'] = 1;
            $return['message'] = '批量操作成功';
        }
        return $return;
    }

    public function save($data)
    {
        $ret = [
            'msg' => '',
            'code' => ''
        ];
        $aliexpressAccountModel = new AliexpressAccount();
        $res = $aliexpressAccountModel->where('code', $data['code'])->field('id')->find();
        if (count($res)) {
            $ret['msg'] = '账户名重复';
            $ret['code'] = 400;
            return $ret;
        }
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_aliExpress,$data['code']);
        //启动事务
        Db::startTrans();
        try {
            $data['create_time'] = time();
            $aliexpressAccountModel->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $aliexpressAccountModel->id;
            Db::commit();
            //删除缓存
            //Cache::handler()->del('cache:AliexpressAccount');
            //开通后立即加一条数据；
            if (isset($data['download_health'])) {
                (new AliexpressAccountHealthService())->openHealth($new_id, $data['download_health']);
            }
            $data['id'] = $new_id;
            //$account = \app\common\model\aliexpress\AliexpressAccount::get($new_id);
            Cache::store('AliexpressAccount')->setTableRecord($new_id);
            $ret = [
                'msg' => '新增成功',
                'code' => 200,
                'id' => $new_id
            ];
            return $ret;
        } catch (\Exception $e) {
            Db::rollback();
            $ret = [
                'msg' => $e->getMessage(),
                'code' => 500,
            ];
            return $ret;
        }
    }
    
}
