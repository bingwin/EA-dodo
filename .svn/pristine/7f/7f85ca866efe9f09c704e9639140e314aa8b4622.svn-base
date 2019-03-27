<?php
namespace app\index\service;


use app\common\cache\Cache;
use app\common\model\ebay\EbayAccount;
use app\common\model\ebay\EbayAccount as EbayAccountModel;
use app\common\service\ChannelAccountConst;
use app\common\service\Common as CommonService;
use app\common\service\ebay\EbayRestful;
use erp\AbsServer;
use think\Db;
use app\common\model\paypal\PaypalAccount as PaypalAccountModel;
use app\common\model\ebay\EbaySite as EbaySiteModel;
use think\Exception;

/**
 * Created by PhpStorm.
 * User: tanbin
 * Date: 2017/7/19
 * Time: 15:48
 */
class EbayAccountService extends AbsServer
{
    /**
     * 获取ebay账号绑定的大小收款paypal账号
     * @param number $account_id ebay账号id
     * @return unknown[][]
     */
    function getEbayMapPaypalAccout($account_id = 0){
        $result = [];
        $ebay_account = EbayAccountModel::field('min_paypal_id,max_paypal_id')->where(['id'=>$account_id])->find();
        if(isset($ebay_account['min_paypal_id']) && $ebay_account['min_paypal_id']>0){
            $paypal_account_min = PaypalAccountModel::field('id,account_name')->where(['id'=>$ebay_account['min_paypal_id']])->find();          
            $result['min'] = [
                'id' => $paypal_account_min['id'],
                'account_name' => $paypal_account_min['account_name']  
            ];
        }
        
        if(isset($ebay_account['max_paypal_id']) && $ebay_account['max_paypal_id']>0){
            $paypal_account_max = PaypalAccountModel::field('id,account_name')->where(['id'=>$ebay_account['max_paypal_id']])->find();
            $result['max'] = [
                'id' => $paypal_account_max['id'],
                'account_name' => $paypal_account_max['account_name']
            ];
        }
        return $result;
    }
    
    /**
     * 通过站点获取币种
     * @param number $site_id 站点id
     * @return string
     */
    function getSiteCurrency($site_code = 0){
        $result = EbaySiteModel::field('currency')->where(['country'=>$site_code])->find();
        if($result){
            $result = explode(',', $result['currency']);
            $result = $result[0];
        }
        return $result;
    }
    
    /**
     * 获取ebay id
     * @param string $name
     * @return int
     */
    public function getIdByName($name)
    {
        $info = EbayAccountModel::where('account_name', $name)->field('id')->find();
        return $info ? $info['id'] : 0;
    }

    /**
     * 刷新token
     * @param $accountIds
     * @return array
     */
    public function refreshToken($accountIds)
    {
        try {
            if (!isset($accountIds[0])) {
                $accountIds = [$accountIds];
            }
            $field = 'id,app_id,cert_id,ru_name,oauth_refresh_token,ort_invalid_time';
            $accounts = EbayAccount::field($field)->whereIn('id',$accountIds)->select();

            $header['Content-Type'] = 'application/x-www-form-urlencoded';
            $data['grant_type'] = 'refresh_token';
            $data['scope'] = 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.marketing https://api.ebay.com/oauth/api_scope/sell.inventory.readonly https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.account.readonly https://api.ebay.com/oauth/api_scope/sell.account https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment https://api.ebay.com/oauth/api_scope/sell.analytics.readonly';
            $url = 'https://api.ebay.com/identity/v1/oauth2/token';

            $errMsg = [];
            $successCnt = 0;

            foreach ($accounts as $account) {
                try {
                    $header['Authorization'] = 'Basic ' . base64_encode($account['app_id'] . ':' . $account['cert_id']);
                    $data['refresh_token'] = $account['oauth_refresh_token'];
                    $response = (new EbayRestful('POST', $header))->sendRequest($url, $data);
                    $res = json_decode($response, true);
                    if (isset($res['error'])) {
                        $errMsg[$account['id']] = $res['error_description'];
                        continue;
                    }
                    $account->oauth_token = $res['access_token'];
                    $account->ot_invalid_time = time() + $res['expires_in'];
                    $account->save();
                    $successCnt++;
                } catch (\Exception $e) {
                    $errMsg[$account['id']] = $e->getMessage();
                }
            }
            $extraMsg = empty($errMsg) ? '' : json_encode($errMsg);
            return ['result'=>true,'message'=> '成功执行'.$successCnt.'条。'.$extraMsg];
        } catch (\Exception $e) {
            return ['result'=>false, 'message'=>$e->getFile().'|'.$e->getLine().'|'.$e->getMessage()];
        }
    }


    public function save($data = [])
    {
        $ret = [
            'msg' => '',
            'code' => ''
        ];

        /** #warning 少了type验证 linpeng time 2019/2/20 14:41 */
        /** || !is_numeric($data['type']) */
        if (empty($data['code']) || empty($data['account_name'])) {
            $ret['msg'] = '参数不能为空';
            $ret['code'] = 400;
            return $ret;
        }
        $site_check = [];
        //*******************过滤正确的site_id 站点，保存到数据库*********
        if (param($data, 'site_id')) {
            $service = new AccountService();
            $site_check = $service->checkEbaySite(json_decode($data['site_id'], true));
        }
        //*******************过滤正确的site_id 站点，保存到数据库*********
        $data['site_id'] = empty($site_check) ? [] : $site_check;
        $data['site_id'] = json_encode($data['site_id']);

        /** warning: 重构时记得传created_user_id linpeng 2019-2-19*/

        // if (!param($data, 'created_user_id')) {
        //     $user = CommonService::getUserInfo($request);
        //     $data['created_user_id'] = $user['user_id'];
        // }
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_ebay, $data['code']);
        //启动事务
        $ebayAccount = new EbayAccount();
        $isHas = $ebayAccount->where('code', $data['code'])->find();
        if ($isHas) {
            $ret['msg'] = '该账号已存在';
            $ret['code'] = 300;
            return $ret;
        }
        Db::startTrans();
        try {
            $data['create_time'] = time();
            $data['update_time'] = time();
            $ebayAccount->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $ebayAccount->id;
            Db::commit();
            //删除缓存
            Cache::store('EbayAccount')->setTableRecord($new_id);
            $ret['msg'] = '新增成功';
            $ret['code'] = 200;
            $ret['id'] = $new_id;
            return $ret;
        } catch (\Exception $e) {
            Db::rollback();
            $ret['msg'] = '新增失败';
            $ret['code'] = 500;
            return $ret;
        }
    }




}