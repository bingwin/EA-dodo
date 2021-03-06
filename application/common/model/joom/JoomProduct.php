<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Date: 18-1-7
 * Time: 上午9:57
 */

namespace app\common\model\joom;

use app\common\traits\ModelFilter;
use erp\ErpModel;
use think\Exception;
use think\Model;
use think\db;
use app\common\cache\Cache;
use think\db\Query;
use app\index\service\Department;
use app\index\service\MemberShipService;

class JoomProduct extends ErpModel
{
    public const KINGS=[
        ['name'=>'普通货','value'=>'notDangerous'],
        ['name'=>'含电池的物品','value'=>'withBattery'],
        ['name'=>'液体和半液体','value'=>'liquid'],
        ['name'=>'磁性物品','value'=>'magnetizedItems'],
        ['name'=>'粉末，膏状，颗粒状商品','value'=>'powder'],
        ['name'=>'尖锐器具与武器','value'=>'weapon'],
        ['name'=>'压力喷雾','value'=>'aerosoleAndGases'],
        ['name'=>'锂电池和蓄电池','value'=>'battery'],
        ['name'=>'易燃液体，固体和菲林','value'=>'flammable'],
        ['name'=>'动物毛真人发','value'=>'hair'],
        ['name'=>'植物','value'=>'plants'],
        ['name'=>'茶叶','value'=>'teaLeafs'],
        ['name'=>'成人用品','value'=>'adult'],
        ['name'=>'高密度','value'=>'highDensity'],
        ['name'=>'仿真武器','value'=>'lookAlikeWeapon'],

    ];
    use ModelFilter;
    protected $autoWriteTimestamp=true;
    private $channel_id=7;
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * listing过滤
     * @param Query $query
     * @param $params
     */
    public function scopeListing(Query $query, $params)
    {

        if(!empty($params))
        {
            $query->where('__TABLE__.shop_id','in',$params);
        }
    }

    /**
     * 部门过滤
     * @param Query $query
     * @param $params
     */
    public function scopeDepartment(Query $query, $params)
    {

        if(!empty($params))
        {
            $accounts=[];
            //用户列表
            foreach ($params as $param)
            {
                $users = (new Department())->getDepartmentUser($param);

                if($users)
                {
                    foreach ($users as $user)
                    {
                        if($user)
                        {
//                            $where['seller_id']=['IN',$user];
                            $memberShipService = new MemberShipService();
                            $account_list = $memberShipService->getAccountIDByUserId($user, $this->channel_id);
//                            $account_list =MemberShipService::getChannelAccountsByUers($this->channel_id,$where);
                            if($account_list)
                            {
                                $accounts = array_merge($accounts,$account_list);
                            }
                        }
                    }
                }
            }

            if(!empty($accounts))
            {
                $query->where('__TABLE__.shop_id','in',$accounts);
            }
        }
    }

    /**
     * 关联变体
     * @return \think\model\relation\HasMany
     */
    public function variants()
    {
        return $this->hasMany(JoomVariant::class,'joom_product_id','id');
    }

    public function shop()
    {
        return $this->hasOne(JoomShop::class,'id','shop_id');
    }

    /**
     * 关联商品信息
     * @return \think\model\relation\HasOne
     */
    public function info()
    {
        return $this->hasOne(JoomProductInfo::class,'id','id');
    }

    public function syncAll($lists) {
        $infoModel = new JoomProductInfo();
        $variantModel = new JoomVariant();
//        $cache = Cache::store('JoomListing');
        $time = time();

        foreach($lists as $key=>$data) {
            Db::startTrans();
            try {
//                $productCache = [];
//                $variantCache = [];
                $id = $data['product']['id'];

                unset($data['product']['id']);

                if($id == 0) {
                    $data['product']['create_time'] = $time;
                    $data['product']['update_time'] = $time;

                    //先保存product数据；
                    $id = $this->insert($data['product']);
                    $id = $this->getLastInsID();
                    //再保存info数据；
                    $data['info']['id'] = $id;
                    $infoModel->insert($data['info']);
                } else {
                    $data['product']['update_time'] = $time;

                    $this->update($data['product'], ['id' => $id]);
                    $infoModel->update($data['info'], ['id' => $id]);
                }
                //缓存数据product;
//                $productCache = ['shop_id' => $data['product']['shop_id'], 'product_id' => $data['product']['product_id'], 'data' => ['id' => $id, 'update_time' => $time]];

                //最后保存variant数据；
                if(!empty($data['variant'])) {
                    foreach($data['variant'] as $variant) {
                        $variant['joom_product_id'] = $id;
                        $vid = $variant['id'];
                        unset($variant['id']);

                        if($vid == 0) {
                            $vid = $variantModel->insert($variant);
                        } else {
                            $variantModel->update($variant, ['id' => $vid]);
                        }
                        //变体缓存；
//                        $variantCache[] = ['variant_id' => $variant['variant_id'], 'data' => ['id' => $vid]];
                    }
                }

                Db::commit();
                //保存缓存
//                $cache->setProductCache($productCache['shop_id'], $productCache['product_id'], $productCache['data']);
//                //保存变体缓存；
//                foreach($variantCache as $vcache) {
//                    $cache->setVariantCache($productCache['shop_id'], $vcache['variant_id'], $vcache['data']);
//                }

            } catch(Exception $e) {
                Db::rollback();
                $error = $e->getMessage(). '( product_id :'. $data['product']['product_id']. ' 数据'. ($id == 0? '保存' : '更新'). '出现问题 )';
                throw new Exception($error);
            }
        }
        return true;
    }

    /**
     * @title 同步已存在的单个产品
     * @param $data
     * @throws Exception
     */
    public function syncProduct($data) {
        $infoModel = new JoomProductInfo();
        $variantModel = new JoomVariant();
        $time = time();
        Db::startTrans();
        try {
            $data['product']['update_time'] = $time;
            $this->update($data['product'], ['product_id' => $data['product']['product_id']]);
            $infoModel->update($data['info'], ['product_id' => $data['product']['product_id']]);

            //最后保存variant数据；
            if(!empty($data['variant'])) {
                foreach($data['variant'] as $variant) {
                    $variantModel->update($variant, ['sku' => $variant['sku']]);
                }
            }

            Db::commit();
        } catch(Exception $e) {
            Db::rollback();
            $error = $e->getMessage(). '( product_id :'. $data['product']['product_id']. ' 数据同步出现问题 )';
            throw new Exception($error);
        }
    }
}