<?php


namespace app\goods\service;

use app\common\model\Goods;
use app\common\model\GoodsGallery;
use app\common\model\GoodsLang;
use service\brandslink\BrandslinkApi;
use app\common\model\brandslink\BrandslinkCategory;
use app\common\model\brandslink\BrandslinkCategoryMap;
use app\goods\service\GoodsImage;
use think\Exception;
use app\common\model\Category;
use app\common\model\GoodsSku;
use think\Db;

class GoodsBrandsLink
{

    private $api = null;

    public function __construct()
    {
        $this->api = BrandslinkApi::instance([]);
    }

    public function getAllCategory()
    {
        $page = 1;
        $pageSize = 100;
        $result = [];
        do {
            $category = $this->api->loader('category')->lists($page, $pageSize);
            if (!$category) {
                throw new Exception('返回为空');
            }
            if ($category['errorCode'] != 100200) {
                throw  new Exception($category['msg']);
            }
            if (isset($category['data'])) {
                $pageInfo = $category['data']['pageInfo'] ?? [];
                if ($pageInfo) {
                    foreach ($pageInfo['list'] as $row) {
                        $newRow = $row;
                        unset($newRow['children']);
                        $newRow['path'] = $row['id'];
                        $result[] = $newRow;
                        $data2 = $this->pageInfo($row, [], $row['id']);
                        $result = array_merge($result, $data2);
                    }
                }
            } else {
                throw  new Exception('返回格式有误');
            }
            $page++;
        } while ($pageInfo['hasNextPage'] == true);
        return $result;
    }

    public function ref()
    {
        $result = $this->getAllCategory();
        if ($result) {
            $time = time();
            Db::startTrans();
            try {
                $BrandslinkCategory = new BrandslinkCategory();
                $BrandslinkCategory->where('id', '>', 0)->delete();
                foreach ($result as $info) {
                    $info['create_time'] = $time;
                    $BrandslinkCategory = new BrandslinkCategory();
                    $BrandslinkCategory->allowField(true)
                        ->isUpdate(false)
                        ->save($info);
                }
                Db::commit();
            } catch (Exception $ex) {
                Db::rollback();
                throw $ex;
            }

        }
    }

    public function buildMap()
    {
        $Category = new Category();
        $aCategory = $Category->field('id,title')->select();
        foreach ($aCategory as $categoryInfo) {
            $title = $categoryInfo['title'];
            $mapId = $this->mapCategoryTitle($title);
            if ($mapId) {
                $data = [
                    'category_id' => $categoryInfo['id'],
                    'title' => $categoryInfo['title'],
                    'brandslink_category_id' => $mapId,
                ];
                $BrandslinkCategoryMap = new BrandslinkCategoryMap();
                $BrandslinkCategoryMap
                    ->allowField(true)
                    ->isUpdate(false)
                    ->save($data);
            }
        }
    }

    public function mapCategoryTitle($title)
    {
        $BrandslinkCategory = new BrandslinkCategory();
        $ret = $BrandslinkCategory
            ->where('categoryName', $title)
            ->field('id')
            ->find();
        if ($ret) {
            return $ret['id'];
        }
        return 0;
    }

    private function pageInfo($row, $data = [], $path = '')
    {

        if ($row['children']) {
            foreach ($row['children'] as $v) {
                $new = $v;
                unset($new['children']);
                if ($path) {
                    $new['path'] = $path . "-" . $v['id'];
                } else {
                    $new['path'] = $path;
                }
                $data[] = $new;
                $data = $this->pageInfo($v, $data, $new['path']);
            }
        }
        return $data;

    }

    public function attr()
    {
        $category = $this->api->loader('Attribute')->lists(1, 500);
    }

    public function brand()
    {
        $category = $this->api->loader('Brand')->lists(1, 500);
    }

    public function createGoods($id)
    {
        $goodsModel = new Goods();
        $goodsHelp = new GoodsHelp();
        $goodsInfo = $goodsModel->where('id', $id)->find($id);
        if (!$goodsInfo) {
            throw new Exception('商品不存在');
        }
        if (!$goodsInfo['category_id']) {
            throw new Exception('当前商品没有分类推送失败');
        }
        $CategoryModel = new Category();
        $categoryInfo = $CategoryModel->where('id',$goodsInfo['category_id'])->find();
        if(!$categoryInfo){
            throw new Exception('该分类不存在');
        }
        $data = [];
        $categoryId = $goodsInfo['category_id'];
        $categoryData = $this->mapBrandsLinkCategoryId($categoryInfo['title']);
        $data['brandName'] = '';
        if ($goodsInfo['brand_id']) {
            $data['brandName'] = $goodsModel->getBrandAttr(null, ['brand_id' => $goodsInfo['brand_id']]);
        }
        if (!$data['brandName']) {
            $data['brandName'] = 'OEM';
        }
        $data = array_merge($data, $categoryData);
        $data['defaultRepository'] = $goodsModel->getWarehouseNameAttr(null, ['warehouse_id' => $goodsInfo['warehouse_id']]);
        $data['isPrivateModel'] = false;
        $data['masterPicture'] = GoodsImage::getThumbPath($goodsInfo['thumb'], 0, 0);
        $data['producer'] = $goodsModel->getSupplierAttr(null, ['supplier_id' => $goodsInfo['supplier_id']]);
        $data['productLogisticsAttributes'] = $goodsHelp->getProTransPropertiesTxt($goodsInfo['transport_property']);
        $data['productMarketTime'] = $goodsInfo['publish_time'] ? date('Y-m-d', $goodsInfo['publish_time']) : '';
        $data['supplierId'] = 100;
        $data['supplierName'] = '利朗达';
        $data['title'] = $goodsInfo['name'];
        $aPlatform = $goodsHelp->getPlatformSale($goodsInfo['platform']);
        if (!$aPlatform) {
            throw new Exception('当前商品可用平台为空，无法推送');
        }
        $canPush = true;
        $channelId = [1 => 'eBay', 2 => 'Amazon', 3 => 'wish', 4 => 'AliExpress'];
        $can = [];
        foreach ($aPlatform as $v) {
            if (in_array($v['id'], array_keys($channelId))) {
                if ($v['value_id']) {
                    $can[] = $v['id'];
                }
            }
            if ($v['id'] == 31) {
                $canPush = $v['value_id'];
            }
        }
//        if (!$canPush) {
//            throw new Exception('当前商品分销平台禁止出售，无法推送');
//        }
        if (empty($can)) {
            throw new Exception('当前商品无可售平台，无法推送');
        }
        $aVendibilityPlatform = [];
        foreach ($can as $canId) {
            $aVendibilityPlatform[] = $channelId[$canId];
        }
        $aSku = $this->getSku($id);
        if (!$aSku) {
            throw new Exception('当前产品sku为空');
        }
        $skuMap = [];
        foreach ($aSku as $skuInfo) {
            $skuMap[$skuInfo['id']] = $skuInfo['sku'];
        }
        $data['vendibilityPlatform'] = implode(',', $aVendibilityPlatform);
        $commodityDetails = [];
        $image = $this->getAllPicByGoodsId($id, $goodsInfo['spu'], $skuMap);
        $commodityDetails['masterPicture'] = implode("|", $image['main']);
        $commodityDetails['additionalPicture'] = implode("|", $image['detail']);
        $aLang = $this->getLang($id);
        $commodityDetails['commodityDescCn'] = isset($aLang[1]) ? $aLang[1]['description'] : $goodsInfo['description'];
        $commodityDetails['commodityDescEn'] = isset($aLang[2]) ? $aLang[2]['description'] : '';
        $commodityDetails['commodityHeight'] = number_format($goodsInfo['height'] / 10, 2, '.', '');
        $commodityDetails['commodityLength'] = number_format($goodsInfo['depth'] / 10, 2, '.', '');
        $commodityDetails['commodityWidth'] = number_format($goodsInfo['width'] / 10, 2, '.', '');
        $commodityDetails['commodityNameCn'] = $goodsInfo['name'];
        $commodityDetails['commodityNameEn'] = isset($aLang[2]) ? $aLang[2]['title'] : '';
        $commodityDetails['commodityWeight'] = (int)$goodsInfo['weight'];
        $commodityDetails['packingHeight'] = number_format($goodsInfo['height'] / 10, 2, '.', '');
        $commodityDetails['packingLength'] = number_format($goodsInfo['depth'] / 10, 2, '.', '');
        $commodityDetails['packingWidth'] = number_format($goodsInfo['width'] / 10, 2, '.', '');
        $commodityDetails['packingListCn'] = '';
        $commodityDetails['packingListEn'] = '';
        $commodityDetails['packingWeight'] = (int)$goodsInfo['weight'];
        $commodityDetails['productFeaturesCn'] = '';
        $commodityDetails['productFeaturesEn'] = '';
        $commodityDetails['searchKeywordsCn'] = '';
        $commodityDetails['searchKeywordsEn'] = '';
        $data['commodityDetails'] = $commodityDetails;
        if (isset($aLang[1])) {
            $tags = explode('\n', $aLang[1]['tags']);
            $commodityDetails['searchKeywordsCn'] = implode('|', $tags);
        }
        if (isset($aLang[2])) {
            $tags = explode('\n', $aLang[2]['tags']);
            $commodityDetails['searchKeywordsEn'] = implode('|', $tags);
        }
        $CommoditySpec = [];
        foreach ($aSku as $skuInfo) {
            $row = [];
            $row['commodityPrice'] = number_format($skuInfo['cost_price'], 2, '.', '');
            $row['retailPrice'] = number_format($skuInfo['retail_price'], 2, '.', '');
            $row['supplierSku'] = $skuInfo['sku'];
            $attr = json_decode($skuInfo['sku_attributes'], true);
            $json = GoodsHelp::getAttrbuteInfoBySkuAttributes($attr, $id);
            $newJson = [];
            foreach ($json as $k => $attr_value) {
                $_key = explode('|', $attr_value['name']);
                $newKey = '';
                if (count($_key) == 2) {
                    $newKey = $_key[0] . "({$_key[1]})";
                } else {
                    $newKey = $_key[0] . "({$_key[0]})";
                }
                $_value = explode('|', $attr_value['value']);
                $newValue = '';
                if (count($_value) == 2) {
                    $newValue = $_value[0] . "({$_value[1]})";
                } else {
                    $newValue = $_value[0] . "({$_value[0]})";
                }
                $newJson[] = $newKey . ":" . $newValue;
            }
            if(empty($newJson)){
                $row['commoditySpec'] = '无属性(NoAttributes):无属性(NoAttributes)';
            }else{
                $row['commoditySpec'] = implode('|', $newJson);
            }

            $CommoditySpec[] = $row;
        }
        $data['commoditySpecList'] = $CommoditySpec;
        $result = $this->api->loader('goods')->push($data);
        if ($result['errorCode'] == 100200) {
            return ['message' => '请求成功'];
        } else {
            throw new Exception($result['msg']);
        }
    }

    public function getSku($goods_id)
    {
        $GoodsSku = new GoodsSku();
        return $GoodsSku->where('goods_id', $goods_id)->where('status','<>',2)->select();
    }

    public function getLang($goods_id)
    {
        $GoodsLang = new GoodsLang();
        $result = [];
        $aLang = $GoodsLang->where('goods_id', $goods_id)->select();
        foreach ($aLang as $langInfo) {
            $result[$langInfo->lang_id] = $langInfo;
        }
        return $result;
    }

    public function getAllPicByGoodsId($goods_id, $spu, $skuMap)
    {
        $GoodsGallery = new GoodsGallery();
        $ret = $GoodsGallery
            ->field('id,goods_id,path,sku_id,is_default')
            ->where('goods_id', $goods_id)
            ->select();

        $trueMain = [];
        $detail = [];
        foreach ($ret as $v) {
            if ($v['is_default'] == 1) {
                if (!$v['sku_id']) {
                    if (empty($trueMain)) {
                        $trueMain[] = GoodsImage::getThumbPath($v['path'], 0, 0) . "?spu=" . $spu;
                    }
                } else {
                    if (!isset($skuMap[$v['sku_id']])) {
                        continue;
                    }
                    $sku = $skuMap[$v['sku_id']];
                    if (empty($trueMain)) {
                        $trueMain[] = GoodsImage::getThumbPath($v['path'], 0, 0) . "?sku=" . $sku;
                    } else {
                        $detail[] = GoodsImage::getThumbPath($v['path'], 0, 0) . "?sku=" . $sku;
                    }
                }
            } else {
                if (!$v['sku_id']) {
                } else {
                    if (!isset($skuMap[$v['sku_id']])) {
                        continue;
                    }
                    $sku = $skuMap[$v['sku_id']];
                    $detail[] = GoodsImage::getThumbPath($v['path'], 0, 0) . "?sku=" . $sku;
                }
            }
        }
        return ['main' => $trueMain, 'detail' => $detail];
    }

    private function mapCategoryId($categoryId)
    {

        $brandslinkCategoryMap = new BrandslinkCategoryMap();
        $mapInfo = $brandslinkCategoryMap->where('category_id', $categoryId)->find();
        if (!$mapInfo) {
            throw new Exception("当前分类[{$categoryId}]未找到匹配的分销分类id");
        }
        $mapId = $mapInfo['brandslink_category_id'];
        $brandslinkCategory = new BrandslinkCategory();
        $brandslinkInfo = $brandslinkCategory->where('id', $mapId)->find();
        if (!$brandslinkInfo) {
            throw new Exception("当前分类[{$categoryId}]对应分销分类不存在");
        }
        $path = explode('-', $brandslinkInfo['path']);
        if (count($path) != 3) {
            throw new Exception("当前分类[{$categoryId}]对应分销分类不是三级分类，无法推送");
        }
        $data = [];
        foreach ($path as $k => $id) {
            $j = $k + 1;
            $key = 'categoryLevel' . $j;
            $data[$key] = $id;
        }
        return $data;
    }

    /**
     * @title 与詹先生商议后改版匹配分类
     * @author starzhan <397041849@qq.com>
     */
    public function mapBrandsLinkCategoryId($categoryName)
    {
        $aCategory = $this->getAllCategory();
        if (!$aCategory) {
            throw new Exception('获取的品连分类为空');
        }
        foreach ($aCategory as $categoryInfo) {
            if ($categoryInfo['categoryLevel'] != 3) {
                continue;
            }
            if ($categoryName == $categoryInfo['categoryName']) {
                $categoryIds = explode('-', $categoryInfo['path']);
                $data = [];
                foreach ($categoryIds as $k => $id) {
                    $j = $k + 1;
                    $key = 'categoryLevel' . $j;
                    $data[$key] = $id;
                }
                return $data;
            }
        }
        throw new Exception('品类分类匹配失败');
    }


}