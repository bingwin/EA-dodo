<?php

/**
 * Description of AliexpressCategoryService
 * @datetime 2017-6-6  11:14:59
 * @author zhangdongdong
 */

namespace app\publish\service;
use think\Db;
use think\Exception;
use app\common\cache\Cache;
use app\common\model\joom\JoomAccount as JoomAccountModel;
use app\common\model\joom\JoomShop as JoomShopModel;
use app\common\model\joom\JoomShopCategory as JoomShopCategoryModel;
use app\common\model\Goods as GoodsModel;
use app\goods\service\CategoryHelp;
//部门权限
use app\common\service\Common as CommonService;
use app\index\service\Department;
use app\common\service\ChannelAccountConst;

use app\publish\validate\AliCategoryAuthValidate;

class JoomCategoryService {

    protected $cgModel = null;

    private $error = '';

    public function __construct()
    {
        $this->cgModel = new JoomShopCategoryModel();
    }

    /**
     * 获取列表
     * @param $param
     * @return array
     */
    public function lists($param) {
        $page = $param['page']?? 1;
        $pageSize = $param['pageSize']?? 10;

        $where = [];
        $where2 = [];
        if(!empty($param['joom_account_id'])) {
            $where['joom_account_id'] = $param['joom_account_id'];
        }
        if(!empty($param['joom_shop_id'])) {
            $where['joom_shop_id'] = $param['joom_shop_id'];
        }
        if(!empty($param['category_id'])) {
            $category_ids[] = $param['category_id'];
            $catelists = Cache::store('category')->getCategory();
            foreach($catelists as $val) {
                if(isset($val['pid']) && $val['pid'] == $param['category_id']) {
                    $category_ids[] = $val['id'];
                }
            }
            unset($category_list);
            $where['category_id'] = ['in', $category_ids];
        }

        $count = $this->cgModel->where($where)->count();

        $lists = $this->cgModel->where($where)->order('create_time', 'desc')->page($page, $pageSize)->select();
        if(empty($lists)) {
            return [
                'data' => [],
                'page' => $page,
                'pageSize' => $pageSize,
                'count' => $count,
            ];
        }

        $account_ids = [];
        $shop_ids = [];
        $new_lists = [];
        foreach($lists as $val) {
            $account_ids[] = $val['joom_account_id'];
            $shop_ids[] = $val['joom_shop_id'];
            $new_lists[] = $val->toArray();
        }

        //帐号名,店铺名；
        $account_arr = JoomAccountModel::where(['id' => ['in', $account_ids]])->column('account_name,code', 'id');
        $shop_arr = JoomShopModel::where(['id' => ['in', $shop_ids]])->column('shop_name', 'id');

        //获取分类名；
        $help = new CategoryHelp();
        $category_list = $help->getCategoryLists();

        $new_array = [];
        foreach($new_lists as $val) {
            $val['account_name'] = $account_arr[$val['joom_account_id']]['account_name']?? '';
            $val['account_code'] = $account_arr[$val['joom_account_id']]['code']?? '';
            $val['shop_name'] = $shop_arr[$val['joom_shop_id']]?? '';
            $val['category_name'] = $this->getCategoryName($category_list, $val['category_id']);
            $val['create_time'] = date('Y-m-d H:i:s', $val['create_time']);
            $new_array[] = $val;
        }

        $result = [
            'data' => $new_array,
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => $count,
        ];
        return $result;
    }

    /**
     * 通过categoryid来取得category名字；
     * @param $category_list
     * @param $id
     * @return string
     */
    private function getCategoryName($category_list, $id) {
        if($id == 0) {
            return '';
        }
        $bol = true;
        $name = '';
        foreach($category_list as $val) {
            $name = '';
            $title = $val['title'];
            if(isset($val) && $val['id'] == $id) {
                $name = $title;
                break;
            }
            foreach($val['childs'] as $v) {
                if(isset($v['id']) && $v['id'] == $id) {
                    $name = $title. '>'. $v['title'];
                    break;
                }
            }
            if(!empty($name)) {
                break;
            }

        }

        return $name;
    }

    /**
     * joom分钏拿取帐号信息；
     */
    public function accounts($warehouse_type = 0)
    {
        $lists = $this->seller($warehouse_type);
        $data = [];
        foreach($lists as $val) {
            $data[] = [
                'label' => $val['code'],
                'value' => $val['id'],
            ];
        }
        return $data;
    }

    public function seller($warehouse_type = 0) {
        if(!empty($warehouse_type)) {
            $where['b.warehouse_type'] = ['eq', $warehouse_type];
        }
        $where['a.is_invalid'] = ['eq', 1];
        $where['a.platform_status'] = ['eq', 1];
        $where['b.channel_id'] = ['eq', 7];
        $seller = (new JoomAccountModel())->alias('a')
            ->group('a.id')
            ->field('a.id,a.code,a.account_name,u.realname,u.id uid,b.warehouse_type')
            ->join('channel_user_account_map b', 'a.id=b.account_id', 'LEFT')
            ->join('user u', 'b.seller_id=u.id', 'LEFT')
            ->where($where)
            ->order('a.id ASC')
            ->select();
        return $seller;
    }

    /**
     * joom分钏拿取帐号对应的店铺信息；
     */
    public function shops($joom_account_id)
    {
        $where = empty($joom_account_id)? [] : ['joom_account_id' => $joom_account_id];
        $where['is_invalid'] = 1;
        $where['is_authorization'] = 1;
        return JoomShopModel::field('shop_name label,id value')->where($where)->select();
    }

    /**
     * @title 当前可用的分类列表；
     */
    public function categoryLists($lang)
    {
        $help = new CategoryHelp();

        $lang_id = $lang == 'zh' ? 1 : 2;
        $list = $help->getCategoryLists($lang_id);
        return $list;
    }

    /**
     * @title 设置分类
     * @param $data
     * @return array
     */
    public function setCategory($data)
    {
        $where['joom_account_id'] = $data['joom_account_id'];
        $where['joom_shop_id'] = $data['joom_shop_id'];
        //先查看这个账号店铺存不存在
        $count = JoomShopModel::where(['joom_account_id' => $data['joom_account_id'], 'id' => $data['joom_shop_id']])->count();
        if($count < 1) {
            $this->error = '帐号店铺信息不存在';
            return false;
        }

        //先找出所有的数据；
        $oldList = $this->cgModel->where(['joom_account_id' => $data['joom_account_id'], 'joom_shop_id' => $data['joom_shop_id']])->column('category_id', 'id');

        $categoryArr = explode(',', $data['category_id']);
        //排序
        if(count($categoryArr) > 1) {
            $categoryArr = array_unique($categoryArr);
            sort($categoryArr);
        }

        //编辑时再找出要删除的数据和要新增的数据，新增时不进行操作；
        $delArr = [];
        if($data['update'] != 0) {
            foreach($oldList as $key=>$val) {
                if(!in_array($val, $categoryArr)) {
                    $delArr[] = $key;
                }
            }
        }

        //需要新增的数据数组；
        $addArr = [];
        foreach($categoryArr as $val) {
            if(!in_array($val, $oldList)) {
                $addArr[] = [
                    'joom_account_id' => $data['joom_account_id'],
                    'joom_shop_id' => $data['joom_shop_id'],
                    'category_id' => $val,
                    'create_time' => $data['create_time'],
                    'creator_id' => $data['creator_id'],
                ];
            }
        }

        try {
            if(!empty($delArr)) {
                $this->cgModel->where(['id' => ['in', $delArr]])->delete();
            }

            if(!empty($addArr)) {
                $this->cgModel->allowField(true)->isUpdate(false)->saveAll($addArr);
            }

            return true;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function getError() {
        return $this->error;
    }

    public function getcategoryID($data)
    {
        $list = $this->cgModel->where(['joom_account_id' => $data['joom_account_id'], 'joom_shop_id' => $data['joom_shop_id']])->field('category_id')->select();
        if(empty($list)) {
            return [];
        }
        $list = collection($list)->toArray();
        return array_column($list, 'category_id');
    }

    public function checkShops($data) {
        $good = GoodsModel::where(['id' => $data['goods_id']])->find();
        if(empty($good) || $good['category_id'] == 0) {
            return [];
        }
        $where = [];
        $where['category_id'] = $good['category_id'];
        if(!empty($data['account_id'])) {
            $where['joom_account_id'] = $data['account_id'];
        }

        $lists = JoomShopCategoryModel::where($where)->field('joom_account_id,joom_shop_id')->select();
        if(empty($lists)) {
            return [];
        }

        $shop_ids = [];
        $account_ids = [];
        foreach($lists as $val) {
            $account_ids[] = $val['joom_account_id'];
            $shop_ids[] = $val['joom_shop_id'];
        }

        //找出帐号
        $where = [];
        if(isset($data['warehouse_type']) && $data['warehouse_type'] !== '') {
            $where['b.warehouse_type'] = $data['warehouse_type'];
        }
        $where['a.is_invalid'] = ['eq', 1];
        $where['a.platform_status'] = ['eq', 1];
        $where['b.channel_id'] = ['eq', 7];
        $where['a.id'] = ['in', $account_ids];
        $accountList = (new JoomAccountModel())->alias('a')
            ->group('a.id')
            ->field('a.id,a.account_name name,a.code,u.realname,u.id uid,b.warehouse_type')
            ->join('channel_user_account_map b', 'a.id=b.account_id', 'LEFT')
            ->join('user u', 'b.seller_id=u.id', 'LEFT')
            ->where($where)
            ->order('a.id ASC')
            ->select();

        //找出上述帐号对应的店铺
        $account_ids = [];
        foreach($accountList as $val) {
            $account_ids[] = $val['id'];
        }
        $shopList = JoomShopModel::where(['id' => ['in', $shop_ids], 'joom_account_id' => ['in', $account_ids], 'is_invalid' => 1, 'is_authorization' => 1])->field('id,code,shop_name,joom_account_id')->select();

        //需要限制用户帐号，组员只能查看到自已的帐号和店铺
        $channel = ChannelAccountConst::channel_Joom;
        //获取操作人信息
        $user = CommonService::getUserInfo(request());
        $departmentList = Cache::store('department')->tree();
        $dModel = new Department();
        $departments = $dModel->getDepsByChannel($channel);
        $ids = array_column($departments,'id');
        //检查权限，找出领导的ID；
        $leader_id = [];
        foreach($ids as $id) {
            if(isset($departmentList[$id])) {
                $leader_id = array_merge($leader_id, $departmentList[$id]['leader_id']);
            }
        }
        /*
         * 这里的权限限制说明，三种情况
         * 1.用户是组长，那么显示全列表；
         * 2.用户是组员，只显示本人；
         * 3.不是组长，也不是组员，属于别的组，那么显示全部列表，让菜单的权限来控制进入刊登的权限；
         */

        $new_list = [];
        $one_list = [];
        foreach($accountList as $data) {
            $tmp = $data->toArray();
            $tmp['shop'] = [];
            foreach($shopList as $val) {
                if($tmp['id'] == $val['joom_account_id']) {
                    $tmp['shop'][] = $val->toArray();
                }
            }
            $new_list[] = $tmp;
            //是组长，全部显示，刊登人员在名单里，只显示本人，不在名单里，全部显示，另外有权限控制；
            if(!in_array($user['user_id'], $leader_id) && $user['user_id'] == $tmp['id']) {
                foreach($shopList as $val) {
                    if($tmp['id'] == $val['joom_account_id']) {
                        $tmp['shop'][] = $val->toArray();
                    }
                }
                $one_list[] = $tmp;
            }
        }
        return empty($one_list)? $new_list : $one_list;
    }
}
