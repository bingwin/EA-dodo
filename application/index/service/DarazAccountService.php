<?php
namespace app\index\service;
use app\common\cache\Cache;
use app\common\exception\JsonErrorException;
use app\common\model\daraz\DarazAccount;
use app\common\service\ChannelAccountConst;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class DarazAccountService
{
    protected $darazAccountModel;

    public function __construct()
    {
        if(is_null($this->darazAccountModel))
        {
            $this->darazAccountModel = new DarazAccount();
        }
    }

    /**
     * 账号列表
     * @param Request $request
     * @return array
     */
    public function accountList(Request $request)
    {
        $params = $request->param();

        $where = $this->getWhere($params);

        $page = param($params,"page",1);
        $pageSize = param($params,"pageSize",50);

        $count = $this->darazAccountModel->where($where)->count();
        $accountList = $this->darazAccountModel->where($where)->fetchSql(false)->page($page, $pageSize)->select();
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
        if (isset($params['authorization']) && $params['authorization'] != '') {
            $where['is_authorization'] = ['eq', $params['authorization']];
        }
        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'account_name':
                    $where['name'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                case 'code':
                    $where['code'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                default:
                    break;
            }
        }

        if(isset($params['site']) && !empty($params['site']))
        {
            $where['site'] = $params['site'];
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
        $data['name'] = $data['account_name'];
        $data['seller_id'] = isset($data['seller_id']) ?? '';
        unset($data['account_name']);

        $validateAccount = validate('DarazAccount');
        if (!$validateAccount->check($data)) {
            $ret['msg'] = $validateAccount->getError();
            $ret['code'] = 400;
            return $ret;
        }
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_Daraz, $data['code'], $data['site']);
        Db::startTrans();
        try {
            $darazModel = new DarazAccount();
            $darazModel->allowField(true)->isUpdate(false)->save($data);
            //获取最新的数据返回
            $new_id = $darazModel->id;
            //删除缓存
            Cache::store('DarazAccount')->delAccount();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new JsonErrorException($e->getMessage(), 500);
        }
        $accountInfo = $this->darazAccountModel->field(true)->where(['id' => $new_id])->find();
        return $accountInfo;
    }

    /**
     * 账号信息
     * @return mixed
     * @throws \think\Exception
     */
    public function read($id)
    {
        if(intval($id) <= 0)
        {
            throw new JsonErrorException('账号不存在',500);
        }
        $accountInfo = Cache::store('DarazAccount')->getTableRecord($id);
        if(empty($accountInfo)){
            throw new JsonErrorException('账号不存在',500);
        }
        return $accountInfo;
    }

    /**
     * 更新资源
     * @param $id
     * @param $data
     */
    public function update($id,$data)
    {
        if ($this->darazAccountModel->isHas($id, $data['code'], '')) {
            throw new JsonErrorException('代码或者用户名已存在', 400);
        }
        $model = $this->darazAccountModel->get($id);

        Db::startTrans();
        try {
            //赋值
            $model->code = isset($data['code'])?$data['code']:'';
            if(isset($data['name']))
            {
                $model->name = $data['name'];
            }
            $model->site = isset($data['site'])?$data['site']:'';
            $model->download_order = isset($data['download_order'])?$data['download_order']:0;
            $model->download_listing = isset($data['download_listing'])?$data['download_listing']:0;
            $model->sync_delivery = isset($data['sync_delivery'])?$data['sync_delivery']:0;
            $model->update_time =time();
            unset($data['id']);

            //更新数据
            $model->allowField(true)->isUpdate(true)->save();

            //删除缓存
            Cache::store('DarazAccount')->delAccount();
            Db::commit();
            return $model;
        } catch (\Exception $e) {
            Db::rollback();
            throw new JsonErrorException($e->getMessage() . $e->getFile() . $e->getLine(), 500);
        }
    }

    /**
     * 保存授权信息
     * @param $data
     */
    public function authorization($data)
    {
       $rule = [
            'id'  => 'require|number|gt:0',
            'api_user'   => 'require',
            'api_key' => 'require',
            'seller_id' => 'require',
            'shop_name' => 'require'
            ];
       $msg = [
            'id.require' => '账号不存在',
            'id.number'  => '账号ID不合法',
            'id.gt'   => '账号ID不合法',
            'api_user.require'  => 'API账号不能为空',
            'api_key.require'   => 'API秘钥不能为空',
            'seller_id.require' => '销售员ID不能空',
            'shop_name.require' => '店铺名称不能空'
            ];
       $validae = new Validate($rule,$msg);
       if(!$validae->check($data))
       {
            throw new Exception($validae->getError());
       }
       $model = $this->darazAccountModel->where("id",$data['id'])->find();
       if(!$model)
       {
           throw new Exception('该账号不存在');
       }
       $model->api_user = $data['api_user'];
       $model->api_key = $data['api_key'];
       $model->seller_id = $data['seller_id'];
       $model->is_authorization = 1;
       $model->shop_name = $data['shop_name'];
       $model->isUpdate(true)->allowField(true)->save();
        //删除缓存
        Cache::store('DarazAccount')->delAccount();
        return true;
    }

    /**
     * 切换账号状态
     * @param $data
     */
    public function changeStatus($data)
    {
        $accountInfo = $this->darazAccountModel->where('id', $data['id'])->find();
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