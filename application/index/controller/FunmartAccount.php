<?php

namespace app\index\controller;

use app\common\cache\Cache;
use app\common\controller\Base;
use app\common\service\Common;
use app\index\service\FunmartAccountService;
use service\funmart\Common\CommonService;
use think\Db;
use think\Exception;
use think\Request;
use app\common\model\fummart\FummartAccount as FunmartAccountModel;
use think\Validate;   //现funmart平台

/**
 * @module 账号管理
 * @title funmart账号
 * @url /funmart-account
 * @package app\index\controller
 * @author linpeng
 */
class FunmartAccount extends Base
{
    /**
     * @title  funamrt平台账号列表
     * @method get
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function index(Request $request)
    {
        $where = [];
        $params = $request->param();

        // if (isset($params['site'])) {
        //     $where[] = ['site', '==', $params['site']];
        // }

        if (isset($params['status']) && $params['status'] > -1) {

            $where[] = ['status', '==', $params['status']];
        }

        if (isset($params['authorization']) && $params['authorization'] > -1) {
            $where[] = ['is_authorization', '==', $params['authorization']];
        }
        if (isset($params['taskName']) && isset($params['taskCondition']) && isset($params['taskTime']) && $params['taskName'] !== '' && $params['taskTime'] !== '') {
            $operator = ['eq' => '==', 'gt' => '>', 'lt' => '<'];
            if (isset($operator[trim($params['taskCondition'])])) {
                $where[] = [$params['taskName'], $operator[trim($params['taskCondition'])], $params['taskTime']];
            }
        }

        /**有效状态筛选， linpeng time 2019/1/22 9:57 */

        if (isset($params['is_invalid']) && is_numeric($params['is_invalid'])) {
            $where[] =  ['is_invalid', '==', intval($params['is_invalid'])];
        }

        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'account_name':
                    $where[] = ['account_name', 'like', $params['snText']];
                    break;
                case 'code':
                    $where[] = ['code', 'like', $params['snText']];
                    break;
                default:
                    break;
            }
        }
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $account_list = Cache::store('FunmartAccount')->getAccount();
        if (isset($where)) {
            $account_list = Cache::filter($account_list, $where);
        }

        //排序
        //排序刷选
        if (param($params, 'sort_type') && in_array($params['sort_type'], ['account_name', 'code'])) {
            $sort = ($params['sort_val'] == 2) ? SORT_DESC : SORT_ASC;
            $sort_type = $params['sort_type'];
            $account_list = $this->my_sort($account_list,$sort_type,$sort);
        }



        //总数
        $count = count($account_list);
        $accountData = Cache::page($account_list, $page, $pageSize);
        $new_array = [];
        foreach ($accountData as $k => $v) {
            // $v['updated_time'] = !empty($v['updated_time']) ? date('Y-m-d',$v['updated_time']) : '';
            // $v['is_invalid'] = $v['is_invalid'] == 1 ? true : false;
            $new_array[$k] = $v;
        }
        $new_array = Cache::filter($new_array, [], 'id,base_account_id,name,account_name,code,secrect,appkey,
        token,phone,email,seller_id,status,is_invalid,download_order,status,is_authorization,download_listing,sync_delivery');
        foreach ($new_array as &$val) {
            $val['status'] = (int)$val['status'];
        }
        $result = [
            'data' => $new_array,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];

        return json($result, 200);
    }


    /**
     * @title 显示指定的funmart账号
     * @method GET
     * @url /funmart-account/:id(\d+)
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function read($id)
    {
        $account = Cache::store('FunmartAccount')->getTableRecord($id);
        $result = [$account];
        $result[0]['download_order'] = (int)$result[0]['download_order'];
        $result[0]['sync_delivery'] = (int)$result[0]['sync_delivery'];
        $result[0]['download_listing'] = (int)$result[0]['download_listing'];
        return json($result, 200);
    }


    /**
     * @title 编辑funmart账号
     * @url /funmart-account/:id(\d+)/edit
     * @method GET
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function edit($id)
    {
        $account = Cache::store('FunmartAccount')->getTableRecord($id);
        $result = [$account];
        $result[0]['download_order'] = (int)$result[0]['download_order'];
        $result[0]['sync_delivery'] = (int)$result[0]['sync_delivery'];
        $result[0]['download_listing'] = (int)$result[0]['download_listing'];
        return json($result, 200);
    }


    /**
     * @title 更新funmart账号
     * @method put
     * @url /funmart-account/:id(\d+)
     * @param  \think\Request $request
     * @param  int $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $params = $request->param();
        $data['code'] = $params['code'] ?? '';
        $data['account_name']=$params['account_name'] ?? '';
        $data['download_order'] = $params['download_order'] ?? '';
        $data['sync_delivery'] = $params['sync_delivery'] ?? '';
        $data['download_listing'] = $params['download_listing'] ?? '';
        $data['id'] = $id;
        $validateAccount = new \app\common\validate\FunmartAccount();
        if (!$validateAccount->scene('edit')->check($data)) {
            return json(['message' => $validateAccount->getError()], 400);
        }

        //判断授权；
//         if (!empty($data['merchant_id']) && !empty($data['access_key_id']) && !empty($data['secret_key'])) {
//             $data['is_authorization'] = 1;
//         } else {
//             $data['is_authorization'] = 0;
//         }

        $model = new FunmartAccountModel();
        //启动事务
        Db::startTrans();
        try {
            if (empty($data)) {
                return json(['message' => '数据参数不能为空'], 200);
            }
            $data['update_time'] = time();
            $model->allowField(true)->save($data, ['id' => $id]);
            Db::commit();

            //更新缓存
            $cache = Cache::store('FunmartAccount');
            foreach ($data as $key => $val) {
                $cache->updateTableRecord($id, $key, $val);
            }
            return json(['message' => "更新成功!"], 200);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['message' => '更新失败'], 500);
        }
    }


    /**
     * @title 系统状态切换
     * @url /funmart-account/change-status
     * @method post
     */
    public function changeStatus(Request $request)
    {
        try {
            $params = $request->param();
            $userInfo = Common::getUserInfo($request);
            if (!empty($userInfo)) {
                $params['updater_id'] = $userInfo['user_id'];
            }
            $ser = new FunmartAccountService();
            $ser->changeStatus($params);
            return json(['message' => '切换系统状态成功'], 200);
        } catch (Exception $ex) {
            return json(['message' => $ex->getMessage()], 400);
        }
    }
    /**
     * @title 更新funmart账号授权信息
     * @method put
     * @url /funmart-account-token/:id(\d+)
     * @param Request $request
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getToken(Request $request, $id)
    {
        $params = $request->param();
        $data['name'] = trim($params['name']);
        $data['id'] = $id;
        $data['phone'] = trim($params['phone']);
        $data['email'] = trim($params['email']);
        $data['secrect'] = trim($params['secrect']);
        $data['appkey'] = trim($params['appkey']);

        $userInfo = Common::getUserInfo($request);
        $data['updater_id'] = param($userInfo, 'user_id', '');

        if(empty($data['name'])) {
            return json(['message' => 'name 不能为空'], 400);
        }
        if (empty($data['phone'])) {
            return json(['message' => 'phone 不能为空'], 400);
        }
        if (empty($data['email'])) {
            return json(['message' => 'email 不能为空'], 400);
        }
        if (empty($data['secrect'])) {
            return json(['message' => 'secrect 不能为空'], 400);
        }
        if (empty($data['appkey'])) {
            return json(['message' => 'appkey 不能为空'], 400);
        }
        if (!$id) {
            return json(['message' => 'id 不能为空'], 400);
        }

        $postData = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'secrect' => $data['secrect'],
        ];
        $model = new FunmartAccountModel();
        $res = $model->field('id')->where('id', $id)->find();
        if (!$res) {
            return json(['message' => '账号不存在'], 400);
        }
        // $postData = array(
        //     'name' => "利朗达",            //必需
        //     'phone' => "15914130311",   //必需
        //     'email' => "bhjgvjk@outlook.com",       //必需
        //     'secrect' => "cz5uax3pqyi3",        //必需
        // );
        try {
            $obj = new CommonService($data['appkey'], '');
            $re = $obj->GetToken($postData);
            if (param($re, 'ask') == 1 && param($re, 'api_token')) {
                Db::startTrans();
                $data['token'] = param($re, 'api_token');
                $data['is_authorization'] = 1;
                $data['is_invalid'] = 1;
                if (empty($data)) {
                    return json(['message' => '数据参数不能为空'], 200);
                }
                $data['updated_time'] = time();
                $model->allowField(true)->save($data, ['id' => $id]);
                Db::commit();
                //更新缓存
                $cache = Cache::store('FunmartAccount');
                foreach ($data as $key => $val) {
                    $cache->updateTableRecord($id, $key, $val);
                }
                return json(['message' => "更新成功!"], 200);
            }else {
                return json(['message' => $re['message']], 400);
            }
        } catch (Exception $e) {
            Db::rollback();
            return json(['message' => '更新失败'], 500);
        }






    }
    /**
     * @title 二维数组排序
     * @url /amazon/my_sort
     * @public
     * @method get
     * @return $arrays
     */
    public function my_sort($arrays, $sort_key, $sort_order = SORT_ASC, $sort_type = SORT_STRING)
    {
        if (is_array($arrays)) {
            foreach ($arrays as $array) {
                if (is_array($array)) {
                    $key_arrays[] = $array[$sort_key];
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
        array_multisort($key_arrays, $sort_order, $sort_type, $arrays);
        return $arrays;
    }
}