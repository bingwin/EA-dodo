<?php
namespace app\common\model;

use think\Model;
/**
 * Created by PhpStorm.
 * User: XPDN
 * Date: 2017/8/22
 * Time: 17:46
 */
class ServerUserAccountInfo extends Model
{

    /**
     * 服务器渠道账号人员关系表
     */
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $where
     * @return array|bool|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isHas($where)
    {
        $result = $this->where($where)->find();
        if (empty($result)) {   //不存在
            return false;
        }
        return $result;
    }

    /**
     *
     * @param $data
     * @return bool|false|int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add($data)
    {
        $where = [
//            'channel_id' => $data['channel_id'],
            'user_id' => $data['user_id'],
            'account_id' => $data['account_id'],
//            'server_id' => $data['server_id'],
        ];

        if(is_array($data['cookie'])){
            $data['cookie'] = json_encode($data['cookie'],JSON_UNESCAPED_UNICODE);
        }
        if(is_array($data['profile'])){
            $data['profile'] = json_encode($data['profile'],JSON_UNESCAPED_UNICODE);
        }
        $time = time();
        $old = $this->isHas($where);
        if($old){
            $saveData['update_time'] = $time;
            $saveData['cookie'] = $data['cookie'];
            $saveData['profile'] = $data['profile'];
            $status = (new ServerUserAccountInfo())->save($saveData,['id'=>$old['id']]);
        }else{
            $data['update_time'] = $time;
            $data['create_time'] = $time;
            $status = (new ServerUserAccountInfo())->allowField(true)->isUpdate(false)->save($data);
        }
        return $status;
    }


}