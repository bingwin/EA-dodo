<?php

namespace app\goods\service;

use app\common\model\Category;
use app\common\model\GoodsGallery;
use app\common\model\Supplier;
use app\common\model\User;
use app\common\model\Attribute;
use app\common\model\AttributeValue;
use app\common\service\Excel;
use think\db\Query;
use think\Exception;
use app\common\cache\Cache;
use think\Db;
use app\common\model\Goods;
use app\common\model\GoodsLang;
use app\common\model\GoodsSourceUrl;
use app\common\model\GoodsAttribute;
use app\common\model\CategoryAttribute;
use app\common\model\AttributeGroup;
use app\common\model\GoodsSku;
use app\common\model\SupplierGoodsOffer;
use app\goods\service\GoodsSku as GoodSkuService;
use app\goods\service\GoodsSkuAlias as ServiceGoodsSkuAlias;
use app\common\model\GoodsSkuAlias;
use think\Loader;
use PDO;
use app\goods\service\GoodsHelp;
use app\goods\service\GoodsSku as ServiceGoodsSku;
use app\common\model\LogExportDownloadFiles;
use app\report\model\ReportExportFiles;
use phpzip\PHPZip;
use app\purchase\service\SupplierOfferService;
use app\common\model\ExportField;
use app\publish\service\GoodsImage as GoodsImagePublish;
use app\common\model\Channel;
use app\common\model\SupplierStatisticReport;

//use app\common\service\CommonQueuer;
//use app\goods\queue\WmsGoodsQueue;


/**
 * class GoodsImport
 * @package app\goods\service
 */
class GoodsImport
{

//    private $queue = null;

    private static $imageUrl = null;
    private static $attributeTitles = [
        '属性名1' => '属性值1',
        '属性名2' => '属性值2',
        '属性名3' => '属性值3'
    ];

    public static $oa_attr_map = [
        'Color' => 1,
        'Size（Standard）' => 2,
        'Size（One Size）' => 3,
        'Size（Kid）' => 4,
        'Size（Bra）' => 12,
        'Plug' => 5,
        'Model' => 9,
        'Storage Capacity' => 10,
        'Color Temperature（Standard）' => 6,
        'Voltage（Standard）' => 7,
        'Current（Standard）' => 8,
        'Type' => 11,
        'Style' => 15,
    ];
    //开发部门对应渠道id
    public static $DEVELOPER_DEPARTMENT_CHANNELID_MAP = [
        0 => 4,//速卖通
        1 => 2,//亚马逊
        2 => 1,//ebay
        3 => 3,//wish
        4 => 2,//服装事业部  亚马逊
        5 => 2,//LED事业部  亚马逊
        6 => 2,//品牌事业部
        7 => 1//义乌分公司
    ];
    /**
     * 开发部门中文对应id[0-6]
     * @var array
     */
    public static $DEVELOPER_DEPARTMENT_ID = [
        'AliExpress部' => 0,
        'Amazon部' => 1,
        'eBay部' => 2,
        'Wish部' => 3,
        '服装事业部' => 4,
        'LED事业部' => 5,
        '女装事业部' => 6,
        '义乌分公司' => 7
    ];


    private static $headers = [
        'SPU', '产品中文名称', '产品英文名称', '分类', '属性名1', '属性值1', '属性名2', '属性值2', '属性名3', '属性值3',
        'SKU', '采购价格', '产品重量(g)', '产品是否均重', '默认仓库名称', '是否多仓库',
        '包装尺寸(长cm)', '包装尺寸(宽cm)', '包装尺寸(高cm)', '默认供应商名称', '品牌', '关键词', '中文配货名称', '英文配货名称', '中文报关名',
        '英文报关名', '开发员', '采购员', '采购链接', '出售状态', '参考链接', '海关编码', 'eBay平台', '亚马逊平台', 'Wish平台',
        '速卖通平台', '产品类型', '产品最低限价', '产品中文描述', '产品英文描述', "开发部门"
    ];
    /**
     * 如果存在一个，则所有都要存在
     * @var array
     */
    private static $mustHeadersSameValate = [
        '属性名1|属性名2|属性名3' => ['属性名1', '属性值1', '属性名2', '属性值2', '属性名3', '属性值3'],
        'eBay平台|亚马逊平台|Wish平台|速卖通平台' => ['eBay平台', '亚马逊平台', 'Wish平台', '速卖通平台']
    ];
    public static $diy_attr = [11, 15];

//    private function queue()
//    {
//        if ($this->queue === null) {
//            $this->queue = new CommonQueuer(WmsGoodsQueue::class);
//        }
//        return $this->queue;
//    }

    /**
     * 数据装填
     * @param array $data
     * @return array
     * @author starzhan <397041849@qq.com>
     */
    public static function convertUpdateData(array $data)
    {
        $spu = '';
        $flag = false;
        $result = [];
        foreach ($data as $k => $row) {
            do {
                $row = array_filter($row);
                if (!$row) {
                    continue(2);
                }
                $list = [];
                if (empty($row['SPU'])) {
                    $row['SPU'] = 'onlySku';
                }
                if ($row['SPU'] != $spu) {
                    $flag = true;
                    $aDescription = [];
                    $spu = $row['SPU'];
                    $list['message'] = '';
                    $list['status'] = 1;
                    isset($row['SPU']) && $row['SPU'] && $list['spu'] = $row['SPU'];
                    if (isset($row['产品中文名称']) && $row['产品中文名称']) {
                        $list['name'] = $row['产品中文名称'];
                        $aDescription[1]['title'] = $list['name'];

                    }
                    if (isset($row['产品英文名称']) && $row['产品英文名称']) {
                        $list['name_en'] = $row['产品英文名称'];
                        $aDescription[2]['title'] = $list['name_en'];
                    }

                    if (isset($row['分类']) && $row['分类']) {
                        $category_id = self::getCategoryId($row['分类']);
                        if (is_string($category_id)) {
                            $list['message'] = "产品 {$spu} " . $category_id;
                            break;
                        }
                        $list['category_id'] = $category_id;
                    }
                    if (isset($row['默认仓库名称']) && $row['默认仓库名称']) {
                        $list['warehouse_id'] = self::getWarehouseId($row['默认仓库名称']);
                        if (!$list['warehouse_id']) {
                            $list['message'] = "系统内找不到该仓库名称[{$row['默认仓库名称']}]";
                            break;
                        }
                    }
                    isset($row['是否多仓库']) && $row['是否多仓库'] && $list['is_multi_warehouse'] = trim($row['是否多仓库']) == '是' ? 1 : 0;
                    isset($row['默认供应商名称']) && $row['默认供应商名称'] && $list['supplier_id'] = self::getsupplierId($row['默认供应商名称']);
                    if (isset($row['默认供应商名称']) && $row['默认供应商名称']) {
                        $list['supplier_id'] = self::getsupplierId($row['默认供应商名称']);
                        if (!$list['supplier_id']) {
                            $list['message'] = "系统内找不到该供应商名称[{$row['默认供应商名称']}]";
                            break;
                        }
                    }
                    isset($row['英文配货名称']) && $row['英文配货名称'] && $list['packaging_en_name'] = $row['英文配货名称'];
                    isset($row['中文配货名称']) && $row['中文配货名称'] && $list['packaging_name'] = $row['中文配货名称'];
                    isset($row['中文报关名']) && $row['中文报关名'] && $list['declare_name'] = $row['中文报关名'];
                    isset($row['英文报关名']) && $row['英文报关名'] && $list['declare_en_name'] = $row['英文报关名'];
                    if (isset($row['关键词']) && $row['关键词']) {
                        $list['tags'] = $row['关键词'];
                        $aDescription[1]['tags'] = $list['tags'];
                    }
                    if (isset($row['品牌']) && $row['品牌']) {
                        $brand_id = self::getBrandByName($row['品牌']);
                        if (!$brand_id) {
                            $list['message'] = "系统内找不到该品牌[{$row['品牌']}]";
                            break;
                        }
                        $list['brand_id'] = $brand_id;
                    }
                    isset($row['采购链接']) && $row['采购链接'] && $list['purchase_link'] = $row['采购链接'];
                    isset($row['开发员']) && $row['开发员'] && $list['developer_id'] = self::getUserIdByExcellJobId($row['开发员']);
                    isset($row['采购员']) && $row['采购员'] && $list['purchaser_id'] = self::getUserIdByExcellJobId($row['采购员']);
                    if (isset($row['出售状态']) && $row['出售状态']) {
                        $list['sales_status'] = self::getSalesStatus($row['出售状态']);
                        $list['sales_status'] == 1 && $list['publish_time'] = time();
                        $list['sales_status'] == 2 && $list['stop_selling_time'] = time();
                    }
                    isset($row['参考链接']) && $row['参考链接'] && $list['source_url'] = $row['参考链接'];
                    isset($row['海关编码']) && $row['海关编码'] && $list['hs_code'] = $row['海关编码'];
                    $platform_sale = [];
                    $self = new self();
                    if (!empty($row['eBay平台']) && !empty($row['亚马逊平台']) && !empty($row['Wish平台']) && !empty($row['速卖通平台'])) {
                        $self->checkPlatForm($row);
                        $platform_sale['ebay'] = self::getPlatformSale($row['eBay平台']);
                        $platform_sale['amazon'] = self::getPlatformSale($row['亚马逊平台']);
                        $platform_sale['wish'] = self::getPlatformSale($row['Wish平台']);
                        $platform_sale['aliExpress'] = self::getPlatformSale($row['速卖通平台']);
                        $list['platform'] = $self->getPlatForm($platform_sale);
                    }
                    isset($row['产品最低限价']) && $row['产品最低限价'] && $list['retail_price'] = $row['产品最低限价'];
                    isset($row['物流属性']) && $row['物流属性'] && $list['transport_property'] = self::getTransportProperty($row['物流属性']);
                    isset($row['产品重量(g)']) && $row['产品重量(g)'] && ($list['weight'] = $row['产品重量(g)']) && ($list['net_weight'] = $row['产品重量(g)']);
                    isset($row['产品是否均重']) && $row['产品是否均重'] && $list['same_weight'] = $row['产品是否均重'] == '是' ? 1 : 0;
                    if (isset($row['产品中文描述']) && $row['产品中文描述']) {
                        $list['description_cn'] = $row['产品中文描述'];
                        $aDescription[1]['description'] = $list['description_cn'];
                    }
                    if (isset($row['产品英文描述']) && $row['产品英文描述']) {
                        $list['description_en'] = $row['产品英文描述'];
                        $aDescription[2]['description'] = $list['description_en'];
                    }
                    isset($row['包装尺寸(长cm)']) && $row['包装尺寸(长cm)'] && $list['depth'] = $row['包装尺寸(长cm)'] * 10;
                    isset($row['包装尺寸(宽cm)']) && $row['包装尺寸(宽cm)'] && $list['width'] = $row['包装尺寸(宽cm)'] * 10;
                    isset($row['包装尺寸(高cm)']) && $row['包装尺寸(高cm)'] && $list['height'] = $row['包装尺寸(高cm)'] * 10;
                    // sku 信息
                    $sku = [];
                    if (isset($row['SKU']) && $row['SKU']) {
                        $sku['sku'] = $row['SKU'];
                        isset($row['产品中文名称']) && $row['产品中文名称'] && isset($row['SKU']) && $row['SKU'] && $sku['spu_name'] = $row['产品中文名称'];
                        isset($row['采购价格']) && $row['采购价格'] && $sku['cost_price'] = $row['采购价格'];
                        isset($row['出售状态']) && $row['出售状态'] && $sku['status'] = self::getSalesStatus($row['出售状态']);
                        isset($row['产品重量(g)']) && $row['产品重量(g)'] && $sku['weight'] = $row['产品重量(g)'];
                        isset($row['包装尺寸(长cm)']) && $row['包装尺寸(长cm)'] && $sku['length'] = $row['包装尺寸(长cm)'] * 10;
                        isset($row['包装尺寸(宽cm)']) && $row['包装尺寸(宽cm)'] && $sku['width'] = $row['包装尺寸(宽cm)'] * 10;
                        isset($row['包装尺寸(高cm)']) && $row['包装尺寸(高cm)'] && $sku['height'] = $row['包装尺寸(高cm)'] * 10;
                        //isset($row['采购链接']) && $row['采购链接'] && $sku['purchase_link'] = $row['采购链接'];
                        if (array_intersect(array_keys(self::$attributeTitles), array_keys($row))) {
                            $attributes = self::getAttributeInfo($row);
                            if (is_string($attributes)) {
                                $list['message'] = $attributes;
                                break;
                            }
                            $sku['attributes'] = $attributes;
                        }
                        if ($sku) {
                            $list['sku'][] = $sku;
                        }
                    }
                    if ($aDescription) {
                        $list['aDescriptions'] = $aDescription;
                    }
                } else {
                    $flag = false;
                    isset($row['产品中文名称']) && $row['产品中文名称'] && $list['spu_name'] = $row['产品中文名称'];
                    isset($row['SKU']) && $row['SKU'] && $list['sku'] = $row['SKU'];
                    isset($row['采购价格']) && $row['采购价格'] && $list['cost_price'] = $row['采购价格'];
                    isset($row['产品重量(g)']) && $row['产品重量(g)'] && $list['weight'] = $row['产品重量(g)'] ? $row['产品重量(g)'] : 0;
                    isset($row['出售状态']) && $row['出售状态'] && $list['status'] = self::getSalesStatus($row['出售状态']);
                    isset($row['包装尺寸(长cm)']) && $row['包装尺寸(长cm)'] && $list['length'] = $row['包装尺寸(长cm)'] * 10;
                    isset($row['包装尺寸(宽cm)']) && $row['包装尺寸(宽cm)'] && $list['width'] = $row['包装尺寸(宽cm)'] * 10;
                    isset($row['包装尺寸(高cm)']) && $row['包装尺寸(高cm)'] && $list['height'] = $row['包装尺寸(高cm)'] * 10;
                    // isset($row['采购链接']) && $row['采购链接'] && $list['purchase_link'] = $row['采购链接'];
                    if (array_intersect(array_keys(self::$attributeTitles), array_keys($row))) {
                        $attributes = self::getAttributeInfo($row);
                        if (is_string($attributes)) {
                            $result[$spu]['message'] = $attributes;
                            break;
                        }
                        $list['attributes'] = $attributes;
                    }
                }
            } while (false);
            if ($flag) {
                $result[$spu] = $list;
            } else {
                $result[$spu]['sku'][] = $list;
            }
        }
        return $result;
    }

    public static function convertData(array $data)
    {
        $spu = '';
        $flag = false;
        $result = [];
        foreach ($data as $k => $row) {
            do {
                $list = [];
                $rowTmp = array_filter($row);
                if (!$rowTmp) {
                    continue(2);
                }
                if (!$row['SPU']) {
                    $list['message'] = '';
                    $list['message'] = "第" . ($k + 1) . "行SPU不能为空";
                    break;
                }
                if ($row['SPU'] != $spu) {
                    $flag = true;
                    $spu = $row['SPU'];
                    $list['status'] = 1;
                    $list['spu'] = $row['SPU'];
                    $aGoods = [];
                    $row['产品中文名称'] && $aGoods['name'] = $row['产品中文名称'];
                    $row['产品英文名称'] && $aGoods['name_en'] = $row['产品英文名称'];
                    $list['message'] = '';
                    if ($row['分类']) {
                        $category_id = self::getCategoryId($row['分类']);
                        if (is_string($category_id)) {
                            $list['message'] = "产品 {$spu} " . $category_id;
                            break;
                        }
                        $aGoods['category_id'] = $category_id;
                    }
                    if ($row['默认仓库名称']) {
                        $warehouse_id = self::getWarehouseId($row['默认仓库名称']);
                        if (!$warehouse_id) {
                            $list['message'] = "系统内找不到该默认仓库名称[{$row['默认仓库名称']}]";
                            break;
                        }
                        $aGoods['warehouse_id'] = $warehouse_id;
                    }
                    $row['是否多仓库'] && $aGoods['is_multi_warehouse'] = trim($row['是否多仓库']) == '是' ? 1 : 0;
                    if ($row['默认供应商名称'] && $row['默认供应商名称']) {
                        $supplier_id = self::getsupplierId($row['默认供应商名称']);
                        if (!$supplier_id) {
                            $list['message'] = "系统内找不到该默认供应商名称[{$row['默认供应商名称']}]";
                            break;
                        }
                        $aGoods['supplier_id'] = $supplier_id;
                    }
                    $row['英文配货名称'] && $aGoods['packaging_en_name'] = $row['英文配货名称'];
                    $row['中文配货名称'] && $aGoods['packaging_name'] = $row['中文配货名称'];
                    $row['中文报关名'] && $aGoods['declare_name'] = $row['中文报关名'];
                    $row['英文报关名'] && $aGoods['declare_en_name'] = $row['英文报关名'];
                    $row['关键词'] && $aGoods['tags'] = $row['关键词'];
                    if ($row['品牌']) {
                        $brand_id = self::getBrandByName($row['品牌']);
                        if (!$brand_id) {
                            $list['message'] = "系统内找不到该品牌[{$row['品牌']}]";
                            break;
                        }
                        $aGoods['brand_id'] = $brand_id;
                    }
                    $row['采购链接'] && $aGoods['purchase_link'] = $row['采购链接'];
                    if ($row['开发员']) {
                        $developer_id = self::getUserIdByExcellJobId($row['开发员']);
                        if (!$developer_id) {
                            $list['message'] = "系统内找不到该开发员[{$row['开发员']}]";
                            break;
                        }
                        $aGoods['developer_id'] = $developer_id;
                    }
                    if ($row['采购员']) {
                        $purchaser_id = self::getUserIdByExcellJobId($row['采购员']);
                        if (!$purchaser_id) {
                            $list['message'] = "系统内找不到该采购员[{$row['采购员']}]";
                            break;
                        }
                        $aGoods['purchaser_id'] = $purchaser_id;
                    }
                    $row['出售状态'] && $aGoods['sales_status'] = self::getSalesStatus($row['出售状态']);
                    if (isset($aGoods['sales_status'])) {
                        $aGoods['publish_time'] = $aGoods['sales_status'] == 1 ? time() : 0;
                        $aGoods['stop_selling_time'] = $aGoods['sales_status'] == 2 ? time() : 0;
                    }
                    $row['参考链接'] && $aGoods['source_url'] = $row['参考链接'];
                    $row['海关编码'] && $aGoods['hs_code'] = $row['海关编码'];
                    $self = new self();
                    if ($row['eBay平台'] && $row['亚马逊平台'] && $row['Wish平台'] && $row['速卖通平台']) {
                        $self->checkPlatForm($row);
                        $platform_sale = [
                            'ebay' => self::getPlatformSale($row['eBay平台']),
                            'amazon' => self::getPlatformSale($row['亚马逊平台']),
                            'wish' => self::getPlatformSale($row['Wish平台']),
                            'aliExpress' => self::getPlatformSale($row['速卖通平台'])
                        ];
                        $aGoods['platform_sale'] = json_encode($platform_sale);
                        $aGoods['platform'] = $self->getPlatForm($platform_sale);
                    } else {
                        $aGoods['platform'] = '0';
                    }
                    $row['产品最低限价'] && $aGoods['retail_price'] = $row['产品最低限价'];
                    $row['物流属性'] && $aGoods['transport_property'] = self::getTransportProperty($row['物流属性']);
                    $row['产品重量(g)'] && $aGoods['weight'] = $row['产品重量(g)'];
                    $row['产品是否均重'] && $aGoods['same_weight'] = $row['产品是否均重'] == '是' ? 1 : 0;
                    $row['产品中文描述'] && $aGoods['description_cn'] = $row['产品中文描述'];
                    $row['产品英文描述'] && $aGoods['description_en'] = $row['产品英文描述'];
                    $row['包装尺寸(长cm)'] && $aGoods['depth'] = $row['包装尺寸(长cm)'] * 10;
                    $row['包装尺寸(宽cm)'] && $aGoods['width'] = $row['包装尺寸(宽cm)'] * 10;
                    $row['包装尺寸(高cm)'] && $aGoods['height'] = $row['包装尺寸(高cm)'] * 10;
                    if ($row['开发部门']) {
                        if (!isset(self::$DEVELOPER_DEPARTMENT_ID[$row['开发部门']])) {
                            throw new Exception('开发部门只能为[“AliExpress部“，“Amazon部”，“eBay部”，“Wish部”，“服装事业部”,“LED事业部”,“女装事业部”]');
                        }
                        $aGoods['dev_platform_id'] = self::$DEVELOPER_DEPARTMENT_ID[$row['开发部门']];
                    }
                    if (isset($aGoods['dev_platform_id'])) {
                        $aGoods['channel_id'] = self::$DEVELOPER_DEPARTMENT_CHANNELID_MAP[$aGoods['dev_platform_id']];
                    }
                    if ($aGoods) {
                        $aGoods['spu'] = $spu;
                        $list['goods'] = $aGoods;
                    }
                    // sku 信息
                    if ($row['SKU']) {
                        $sku['sku'] = $row['SKU'];
                        $row['产品中文名称'] && $sku['spu_name'] = $row['产品中文名称'];
                        $row['采购价格'] && $sku['cost_price'] = $row['采购价格'];
                        $row['出售状态'] && $sku['status'] = self::getSalesStatus($row['出售状态']);
                        if ($row['产品重量(g)']) {
                            $sku['weight'] = $row['产品重量(g)'];
                            $sku['old_weight'] = $sku['weight'];
                        }
                        $row['采购链接'] && $sku['purchase_link'] = $row['采购链接'];
                        isset($row['SKU别名']) && $row['SKU别名'] && $sku['alias'] = $row['SKU别名'];
                        $row['包装尺寸(长cm)'] && $sku['length'] = $row['包装尺寸(长cm)'] * 10;
                        $row['包装尺寸(宽cm)'] && $sku['width'] = $row['包装尺寸(宽cm)'] * 10;
                        $row['包装尺寸(高cm)'] && $sku['height'] = $row['包装尺寸(高cm)'] * 10;
                        $attributes = self::getAttributeInfo($row);
                        if (is_string($attributes)) {
                            $list['message'] = $attributes;
                            break;
                        }
                        $sku['attributes'] = $attributes;
                        $list['sku'][] = $sku;
                    }

                } else {
                    $flag = false;
                    if ($row['SKU']) {
                        $row['产品中文名称'] && $list['spu_name'] = $row['产品中文名称'];
                        $list['sku'] = $row['SKU'];
                        $row['采购价格'] && $list['cost_price'] = $row['采购价格'];
                        if ($row['产品重量(g)']) {
                            $sku['weight'] = $row['产品重量(g)'];
                            $sku['old_weight'] = $sku['weight'];
                        }
                        $row['出售状态'] && $list['status'] = self::getSalesStatus($row['出售状态']);
                        $row['采购链接'] && $list['purchase_link'] = $row['采购链接'];
                        isset($row['SKU别名']) && $row['SKU别名'] && $list['alias'] = $row['SKU别名'];
                        $row['包装尺寸(长cm)'] && $list['length'] = $row['包装尺寸(长cm)'] * 10;
                        $row['包装尺寸(宽cm)'] && $list['width'] = $row['包装尺寸(宽cm)'] * 10;
                        $row['包装尺寸(高cm)'] && $list['height'] = $row['包装尺寸(高cm)'] * 10;
                        $attributes = self::getAttributeInfo($row);
                        if (is_string($attributes)) {
                            $result[$spu]['message'] = $attributes;
                            break;
                        }
                        $list['attributes'] = $attributes;
                    }
                }
            } while (false);
            if ($flag) {
                $result[$spu] = $list;
            } else {
                $result[$spu]['sku'][] = $list;
            }
        }
        return $result;
    }

    public function checkPlatForm($row)
    {
        $keys = ['eBay平台', '亚马逊平台', 'Wish平台', '速卖通平台'];
        $GoodsHelp = new GoodsHelp();
        $allowValues = [];
        $tmpValue = $GoodsHelp->getPlatformSaleStatus();
        foreach ($tmpValue as $v) {
            $allowValues[] = $v['name'];
        }
        foreach ($keys as $key) {
            if (!in_array($row[$key], $allowValues)) {
                throw new Exception($key . "的值[{$row[$key]}]不在取值范围内");
            }
        }
    }

    public function getPlatForm($platform_sale)
    {
        $lists = [];
        foreach ($platform_sale as $key => $value_id) {
            $list = [];
            $list['name'] = $key;
            $list['value_id'] = $value_id;
            $lists[] = $list;
        }
        $goodsHelp = new GoodsHelp();
        return $goodsHelp->getPlatform($lists);
    }

    /**
     * 获取分类id
     * @param string $category
     * @return int
     * @throws Exception
     */
    private static function getCategoryId($category)
    {
        $categories = explode('/', $category);
        if (count($categories) != 2) {
            return '分类格式不对';
        }
        $parentInfo = Category::where(['title' => trim($categories[0]), 'pid' => 0])->field('id')->find();
        if (!$parentInfo) {
            return '分类 ' . $categories[0] . ' 不在系统中';
        }
        $subInfo = Category::where(['title' => trim($categories[1]), 'pid' => $parentInfo['id']])->field('id')->find();
        if (!$subInfo) {
            return '分类 ' . $categories[1] . ' 不在系统中';
        }
        return $subInfo['id'];
    }

    /**
     * 获取仓库id
     * @param string $name
     * @return int
     */
    public static function getWarehouseId($name)
    {
        $warehouses = Cache::store('warehouse')->getWarehouse();
        $warehouse_id = 0;
        foreach ($warehouses as $warehouse) {
            if ($warehouse['name'] == $name) {
                $warehouse_id = $warehouse['id'];
                break;
            }
        }
        return $warehouse_id;
    }

    /**
     * 获取供应商名称
     * @param stirng $name
     * @return int
     */
    public static function getSupplierId($name)
    {
        $name = trim($name);
        //$name = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", $name);
        $supplierInfo = Supplier::where(['company_name' => $name])->field('id')->find();
        return $supplierInfo ? $supplierInfo['id'] : 0;
    }

    /**
     * 获取用户id
     * @param string $name
     * @param string $staff
     * @return int
     */
    public static function getUserId($name, $staff = '')
    {
        $name = trim($name);
        $name = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", $name);
        $userInfo = User::where(['realname' => $name])->field('id')->find();
        return $userInfo ? $userInfo['id'] : 0;
    }

    public static function getUserIdByJobId($job_id)
    {
        $job_id = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", $job_id);
        $job_id = str_pad($job_id, 4, '0', STR_PAD_LEFT);
        $userInfo = User::where(['job_number' => $job_id])->field('id')->find();
        return $userInfo ? $userInfo['id'] : 0;
    }

    public static function getUserIdByExcellJobId($job_id)
    {
        $job_id = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", $job_id);
        $b = preg_match('/\[(\d+)\]/', $job_id, $data);
        $num = isset($data[1]) ? $data[1] : 0;
        return self::getUserIdByJobId($num);
    }

    /**
     * 获取销售状态
     * @param string $status
     * @return id
     */
    public static function getSalesStatus($status)
    {
        $id = 3;
        $goodsHelp = new GoodsHelp();
        foreach ($goodsHelp->getSalesStatus() as $row) {
            if ($row['name'] == $status) {
                $id = $row['id'];
                break;
            }
        }
        return $id;
    }

    /**
     * 获取产品物流属性
     * @param string $name
     * @return int
     */
    public static function getTransportProperty($name)
    {
        $id = 0;
        $list = explode(',', $name);
        $goodsHelp = new GoodsHelp();
        $transport_properties = $goodsHelp->getTransportProperies();
        while ($name = trim(array_shift($list))) {
            foreach ($transport_properties as $property) {
                if ($property['name'] == $name) {
                    $id += $property['value'];
                    break;
                }
            }
        }
        return $id;
    }

    /**
     * 平台销售状态
     * @param array $str
     * @return int
     */
    public static function getPlatformSale($str)
    {
        $id = 1;
        $goodsHelp = new GoodsHelp();
        $sales = $goodsHelp->getPlatformSaleStatus();
        foreach ($sales as $sale) {
            if ($sale['name'] == $str) {
                $id = $sale['id'];
                break;
            }
        }
        return $id;
    }

    public static function getBrandByName($str)
    {
        $lists = Cache::store('brand')->getBrand();
        foreach ($lists as $list) {
            if ($list['name'] == $str) {
                return $list['id'];
            }
        }
        return '';
    }

    /**
     * 获取属性id
     * @param string $name
     * @return int
     * @throws Exception
     */
    public static function getAttributeId($name)
    {
        $attributes = self::$oa_attr_map;
        $name = trim($name);
        if (isset($attributes[$name])) {
            return $attributes[$name];
        }
        $attributeInfo = Attribute::where(['name' => ['like', $name . '%']])->field('id')->find();
        if (!$attributeInfo) {
            throw new Exception("属性名 {$name} 不在系统中");
        }
        return $attributeInfo['id'];
    }

    /**
     * 获取属性值id
     * @param int $attribute_id
     * @param string $value
     * @throws Exception
     */
    public static function getAttributeValueId($attribute_id, $value)
    {
        $valueInfo = AttributeValue::where(['attribute_id' => $attribute_id, 'value' => ['like', $value . '%']])->field('id')->find();
        if (!$valueInfo) {
            throw new Exception("属性值 {$value} 不在系统中");
        }
        return $valueInfo['id'];
    }

    /**
     * sku属性值
     * @param array $row
     * @return array
     */
    public static function getAttributeInfo($row)
    {
        $attributes = [];
        $message = '';
        foreach (self::$attributeTitles as $attributeTitle => $valueTitle) {
            if (!isset($row[$attributeTitle]) || !isset($row[$valueTitle]) || !$row[$attributeTitle] || !$row[$valueTitle]) {
                continue;
            }
            try {
                $attribute_id = self::getAttributeId($row[$attributeTitle]);
                if (in_array($attribute_id, self::$diy_attr)) {
                    $attributes[] = [
                        'attribute_id' => $attribute_id,
                        'value' => $row[$valueTitle],
                        'value_id' => 0
                    ];
                } else {
                    $value_id = self::getAttributeValueId($attribute_id, $row[$valueTitle]);
                    $attributes[] = [
                        'attribute_id' => $attribute_id,
                        'value_id' => $value_id
                    ];
                }
            } catch (Exception $ex) {
                $message .= $ex->getMessage();
            }
        }
        return $message ? $message : $attributes;
    }

    /**
     * 检查头
     * @param $result
     * @author starzhan <397041849@qq.com>
     */
    protected function checkHeader($result)
    {
        if (!$result) {
            throw new Exception("未收到该文件的数据");
        }
        $row = reset($result);
        $aRowFiles = array_keys($row);
        $aDiffRowField = array_diff(self::$headers, $aRowFiles);
        if (!empty($aDiffRowField)) {
            throw new Exception("缺少列名[" . implode(';', $aDiffRowField) . "]");
        }
    }

    /**
     * @param $result
     * @throws Exception
     * @author starzhan <397041849@qq.com>
     */
    protected function checkUpdateHeader($result)
    {
        if (!$result) {
            throw new Exception("未收到该文件的数据");
        }
        $row = reset($result);
        if (!isset($row['SPU']) && !isset($row['SKU'])) {
            throw new Exception("[SPU]和[SKU]至少要有一个");
        }
        foreach (self::$mustHeadersSameValate as $fields => $aMustFields) {
            $aFields = explode('|', $fields);
            foreach ($aFields as $sField) {
                if (isset($row[$sField])) {
                    foreach ($aMustFields as $sMustField) {
                        if (!isset($row[$sMustField])) {
                            $aDiff = array_diff($aMustFields, array_keys($row));
                            throw new Exception("缺少列名[" . implode('、', $aDiff) . "]");
                        }
                    }
                    break;
                }
            }
        }

    }

    /**
     * 导入产品
     * @param array $params
     * @param array $user_id
     * @return array
     */
    public function import(array $params, $user_id)
    {
        $filename = 'upload/' . uniqid() . '.' . $params['extension'];
        self::saveFile($filename, $params);
        try {
            $aResults = [
                'spu' => ['s' => 0, 'f' => 0, 'r' => []],
                'sku' => ['s' => 0, 'f' => 0, 'r' => []]
            ];
            $result = Excel::readExcel($filename);
            @unlink($filename);
            $this->checkHeader($result);
            $lists = self::convertData($result);
            foreach ($lists as $list) {
                if ($list['message']) {
                    $aResults['spu']['f']++;
                    $aResults['spu']['r'][] = $list['message'];
                    continue;
                }
                $aResult = $this->add($list, $user_id);
                $aResults['spu']['s'] += $aResult['spu']['s'];
                $aResults['spu']['f'] += $aResult['spu']['f'];
                $aResults['spu']['r'] = array_merge($aResults['spu']['r'], $aResult['spu']['r']);
                $aResults['sku']['s'] += $aResult['sku']['s'];
                $aResults['sku']['f'] += $aResult['sku']['f'];
                $aResults['sku']['r'] = array_merge($aResults['sku']['r'], $aResult['sku']['r']);
            }
            return $aResults;
        } catch (Exception $ex) {
            @unlink($filename);
            throw new Exception($ex->getMessage());
        }
    }

    /**
     * @param array $params
     * @author starzhan <397041849@qq.com>
     */
    public function importUpdate(array $params, $user_id)
    {
        set_time_limit(0);
        $filename = 'upload/' . uniqid() . '.' . $params['extension'];
        self::saveFile($filename, $params);
        try {
            $aResults = [
                'spu' => ['s' => 0, 'f' => 0, 'r' => []],
                'sku' => ['s' => 0, 'f' => 0, 'r' => []]
            ];
            $result = Excel::readExcel($filename);
            @unlink($filename);
            $this->checkUpdateHeader($result);
            $lists = self::convertUpdateData($result);
            foreach ($lists as $k => $list) {
                if ($list['message']) {
                    $aResults['spu']['f']++;
                    $aResults['spu']['r'][] = $list['message'];
                    continue;
                }
                $onlySku = $k == 'onlySku' ? 1 : 0;
                $aResult = $this->updateProduct($list, $onlySku, $user_id);
                $aResults['spu']['s'] += $aResult['spu']['s'];
                $aResults['spu']['f'] += $aResult['spu']['f'];
                $aResults['spu']['r'] = array_merge($aResults['spu']['r'], $aResult['spu']['r']);
                $aResults['sku']['s'] += $aResult['sku']['s'];
                $aResults['sku']['f'] += $aResult['sku']['f'];
                $aResults['sku']['r'] = array_merge($aResults['sku']['r'], $aResult['sku']['r']);
            }
            return $aResults;
        } catch (Exception $ex) {
            @unlink($filename);
            throw new Exception($ex->getMessage());
        }
    }

    /**
     * 添加产品
     * @param array $row
     * @return boolean
     * @throws Exception
     */
    public function add(array $row, $user_id)
    {
        $aResult = [
            'spu' => ['s' => 0, 'f' => 0, 'r' => []],
            'sku' => ['s' => 0, 'f' => 0, 'r' => []]
        ];
        $goodsHelp = new GoodsHelp();
        $aGoods = Goods::where(['spu' => $row['spu']])->find();
        Db::startTrans();
        try {
            $aInsertGoods = [];
            $GoodsLog = new GoodsLog();
            if (!$aGoods) {
                if (isset($row['goods'])) {
                    $aInsertGoods = $row['goods'];
                    $aInsertGoods['create_time'] = time();
                    $aInsertGoods['status'] = 1;
                    $goods = new Goods();
                    !isset($aInsertGoods['description']) && $aInsertGoods['description'] = '';
                    $goodsValidate = new \app\common\validate\Goods();
                    $flag = $goodsValidate->scene('import')->check($aInsertGoods);
                    if (false === $flag) {
                        throw new Exception($goodsValidate->getError());
                    }
                    $goods->allowField(true)->isUpdate(false)->save($aInsertGoods);
                    $GoodsLog->addSpu($aInsertGoods['spu']);
                    if(!empty($aInsertGoods['supplier_id'])){
                        SupplierStatisticReport::statisticSpuQty($aInsertGoods['supplier_id'],1);
                    }
                    $goods_id = $goods->id;
                    $aDescriptions = [];
                    $aDescription = [];
                    isset($aInsertGoods['tags']) && $aDescription['tags'] = $aInsertGoods['tags'];
                    if (isset($aInsertGoods['description_cn']) && isset($aInsertGoods['name'])) {
                        $aDescription['description'] = $aInsertGoods['description_cn'];
                        $aDescription['title'] = $aInsertGoods['name'];
                        $aDescription['goods_id'] = $goods_id;
                        $aDescription['lang_id'] = 1;
                        $aDescription['selling_point'] = '{}';
                        $aDescriptions[] = $aDescription;
                    }
                    $aDescription1 = [];
                    if (isset($aInsertGoods['name_en']) && isset($aInsertGoods['name_en'])) {
                        $aDescription1['description'] = $aInsertGoods['description_en'];
                        $aDescription1['title'] = $aInsertGoods['name_en'];
                        $aDescription1['goods_id'] = $goods_id;
                        $aDescription1['lang_id'] = 2;
                        $aDescription1['selling_point'] = '{}';
                        $aDescriptions[] = $aDescription1;
                    }
                    if ($aDescriptions) {
                        $goodsLang = new GoodsLang();
                        $goodsLang->allowField(true)->saveAll($aDescriptions);
                    }
                    // 添加sourceurl
                    if (isset($aInsertGoods['source_url'])) {
                        $goodsSourceUrl = new GoodsSourceUrl();
                        $goodsSourceUrl->allowField(true)->save(['goods_id' => $goods_id, 'source_url' => $aInsertGoods['source_url'], 'create_time' => time(), 'create_id' => $user_id]);
                    }
                    $aResult['spu']['s']++;
                } else {
                    throw new Exception('该产品不存在！无法进行此操作');
                }

            } else {
                $goods_id = $aGoods->id;
                $aInsertGoods['sales_status'] = $aGoods->getData('sales_status');
                $aInsertGoods['category_id'] = $aGoods->getData('category_id');
                $aInsertGoods['name'] = $aGoods->getData('name');
                $aInsertGoods['supplier_id'] = $aGoods->getData('supplier_id');
                $aInsertGoods['developer_id'] = $aGoods->getData('developer_id');
            }
            $GoodSkuService = new GoodSkuService();
            foreach ($row['sku'] as $skuInfo) {
                if ($goodsHelp->isSameSku($goods_id, $skuInfo['sku'])) {
                    $aResult['sku']['f']++;
                    $aResult['sku']['r'][] = $skuInfo['sku'] . '在系统中已存在';
                    continue;
                }
                $skuInfo['spu_name'] = $aInsertGoods['name'];
                isset($skuInfo['status']) && $skuInfo['status'] == 0 && $skuInfo['status'] = $aInsertGoods['sales_status'];
                $attributes = [];
                if (!$aInsertGoods['category_id']) {
                    $aResult['sku']['f']++;
                    $aResult['sku']['r'][] = '分类不能为空';
                    continue;
                }
                if ($skuInfo['attributes']) {
                    foreach ($skuInfo['attributes'] as &$attribute) {
                        $attribute['category_id'] = $aInsertGoods['category_id'];
                        $attribute['goods_id'] = $goods_id;
                        if ($attribute['value_id'] == 0) {
                            $value_id = self::addSelfAttribute($attribute);
                            $attributes['attr_' . $attribute['attribute_id']] = $value_id;
                        } else {
                            self::addAttribute($attribute);
                            $attributes['attr_' . $attribute['attribute_id']] = $attribute['value_id'];
                        }
                    }
                    $skuInfo['sku_attributes'] = json_encode($attributes);
                    if ($GoodSkuService->isSameSkuAttributes($goods_id, $skuInfo['sku_attributes'])) {
                        $aResult['sku']['f']++;
                        $aResult['sku']['r'][] = $skuInfo['sku'] . '已存在这个属性，不需要重复添加';
                        continue;
                    }
                } else {
                    $skuInfo['sku_attributes'] = json_encode($attributes);
                }
                $skuInfo['goods_id'] = $goods_id;
                $goodsSku = new GoodsSku();
                $validateGoodsSku = new \app\common\validate\GoodsSku();
                $flag = $validateGoodsSku->scene('import')->check($skuInfo);
                if (false === $flag) {
                    $aResult['sku']['f']++;
                    $aResult['sku']['r'][] = $skuInfo['sku'] . $validateGoodsSku->getError();
                    continue;
                }
                $goodsSku->allowField(true)->isUpdate(false)->save($skuInfo);
                $GoodsLog->addSku($skuInfo['sku']);
                $ServiceGoodsSkuAlias = new ServiceGoodsSkuAlias();
                $ServiceGoodsSkuAlias->insert($goodsSku->id, $skuInfo['sku'], $skuInfo['sku'], 1);
                $aResult['sku']['s']++;
                if (isset($aInsertGoods['supplier_id']) && $skuInfo['cost_price']) {
                    $offerData = [];
                    if (isset($skuInfo['purchase_link'])) {
                        $offerData['link'] = $skuInfo['purchase_link'];
                    } else {
                        isset($aInsertGoods['purchase_link']) && $offerData['link'] = $aInsertGoods['purchase_link'];
                    }
                    isset($aInsertGoods['developer_id']) && $offerData['creator_id'] = $aInsertGoods['developer_id'];
                }
                if (isset($skuInfo['alias'])) {
                    $GoodsSkuAlias = new GoodsSkuAlias();
                    $aGoodsSkuAliasData = [
                        'sku_id' => $goodsSku->id,
                        'sku_code' => $goodsSku->sku,
                        'alias' => $skuInfo['alias'],
                        'create_time' => time()
                    ];
                    $GoodsSkuAlias->allowField(true)->isUpdate(false)->save($aGoodsSkuAliasData);
                }

            }
            $GoodsLog->save($user_id, $goods_id);
//            $this->queue()->push(['goods_id'=>$goods_id]);
            Db::commit();
            Cache::handler()->del('cache:categoryAttribute');
            return $aResult;
        } catch (Exception $ex) {
            Db::rollback();
            $aResult['spu']['f']++;
            $aResult['spu']['r'][] = $row['spu'] . ' ' . $ex->getMessage();;
            return $aResult;
        }
    }

    /**
     * 更改产品
     * @param array $row
     * @return boolean
     * @throws Exception
     */
    public function updateProduct(array $row, $onlySku = 0, $user_id)
    {
        if ($onlySku) {
            return $this->onlyUpdateSku($row, $user_id);
        }
        Db::startTrans();
        try {
            $aResult = [
                'spu' => ['s' => 0, 'f' => 0, 'r' => []],
                'sku' => ['s' => 0, 'f' => 0, 'r' => []]
            ];
            $aGoods = Goods::where(['spu' => $row['spu']])->find();
            if (!$aGoods) {
                $aResult['spu']['f']++;
                $aResult['spu']['r'][] = '产品 ' . $row['spu'] . ' 不存在';
                Db::rollback();
                return $aResult;
            }
            $GoodsLog = new GoodsLog();
            $GoodSkuService = new GoodSkuService();
            $goods = new Goods();
            $goods_id = $aGoods->id;
            $aResult['goods_id'] = $goods_id;
            if (isset($row['platform_sale'])) {
                $platform_sale = json_decode($aGoods->platform_sale, true);
                $row['platform_sale'] = json_decode($row['platform_sale'], true);
                if ($platform_sale) {
                    $row['platform_sale'] = array_merge($platform_sale, $row['platform_sale']);
                }
                $row['platform_sale'] = json_encode($row['platform_sale']);
            }
            if (isset($row['supplier_id'])) {
                if ($row['supplier_id'] != $aGoods['supplier_id']) {
                    SupplierStatisticReport::statisticSpuQty($aGoods['supplier_id'],-1);
                    SupplierStatisticReport::statisticSpuQty($row['supplier_id'],1);
                    $GoodSkuService->afterUpdateDefSupplier($row['supplier_id'], $goods_id, $GoodsLog);
                }
            }
            $goods->allowField(true)->isUpdate(true)->save($row, ['id' => $goods_id]);
            $GoodsLog->mdfSpu($aGoods['spu'], $aGoods->toArray(), $row);
            Cache::store('goods')->delGoodsInfo($goods_id);
            if (isset($row['sales_status']) && $row['sales_status'] && empty($row['sku'])) {
                $this->afterUpdateGoodsStatus($aGoods, $row['sales_status']);
            }
            // 添加描述
            if (isset($row['aDescriptions']) && $row['aDescriptions']) {
                foreach ($row['aDescriptions'] as $lang_id => $val) {
                    $goodsLang = new GoodsLang();
                    $oGoodsLang = $goodsLang->where(['goods_id' => $goods_id, 'lang_id' => $lang_id])->find();
                    if ($oGoodsLang) {
                        isset($val['description']) && $val['description'] && $oGoodsLang->description = $val['description'];
                        isset($val['title']) && $val['title'] && $oGoodsLang->title = $val['title'];
                        isset($val['tags']) && $val['tags'] && $goodsLang->tags = $val['tags'];
                        $oGoodsLang->save();
                    } else {
                        if (isset($val['description']) && $val['description'] && isset($val['title']) && $val['title']) {
                            $insertDetail = [
                                'goods_id' => $goods_id,
                                'lang_id' => $lang_id,
                                'description' => $val['description'],
                                'title' => $val['title']
                            ];
                            isset($val['tags']) && $val['tags'] && $insertDetail['tags'] = $val['tags'];
                            $goodsLang = new GoodsLang();
                            $goodsLang->allowField(true)->isUpdate(false)->save($insertDetail);
                        }

                    }
                }
            }
            $aResult['spu']['s']++;
            $row['sales_status'] = isset($row['sales_status']) && $row['sales_status'] ? $row['sales_status'] : $aGoods->sales_status;
            $row['category_id'] = isset($row['category_id']) && $row['category_id'] ? $row['category_id'] : $aGoods->category_id;
            if (isset($row['sku']) && $row['sku']) {
                $GoodSkuService = new GoodSkuService();
                foreach ($row['sku'] as $skuInfo) {
                    $goodsSku = new GoodsSku();
                    $aGoodsSku = $goodsSku->where(['goods_id' => $goods_id, 'sku' => $skuInfo['sku']])->find();
                    if (!$aGoodsSku) {
                        $aAlias = GoodsSkuAlias::where('alias', $skuInfo['sku'])->find();
                        if (!$aAlias) {
                            $aResult['sku']['f']++;
                            $aResult['sku']['r'][] = 'sku: ' . $skuInfo['sku'] . ' 不存在,修改失败';
                            continue;
                        }
                        $nSkuId = $aAlias->sku_id;

                    } else {
                        $nSkuId = $aGoodsSku->id;
                    }

                    if (isset($skuInfo['status'])) {
                        $skuInfo['status'] = $skuInfo['status'] == 0 ? $row['sales_status'] : $skuInfo['status'];
                        $this->afterUpdateSkuStatus($aGoodsSku, $aGoods, $skuInfo['status']);
                    }
                    if (isset($skuInfo['attributes'])) {
                        $attributes = [];
                        foreach ($skuInfo['attributes'] as &$attribute) {
                            $attribute['category_id'] = $row['category_id'];
                            $attribute['goods_id'] = $goods_id;
                            if ($attribute['value_id'] == 0) {
                                $value_id = self::addSelfAttribute($attribute);
                                $attributes['attr_' . $attribute['attribute_id']] = $value_id;
                            } else {
                                self::addAttribute($attribute);
                                $attributes['attr_' . $attribute['attribute_id']] = $attribute['value_id'];
                            }
                        }
                        if ($attributes) {
                            $skuInfo['sku_attributes'] = json_encode($attributes);
                            if ($GoodSkuService->isSameSkuAttributes($goods_id, $skuInfo['sku_attributes'])) {
                                $aResult['sku']['f']++;
                                $aResult['sku']['r'][] = $skuInfo['sku'] . '已存在这个属性,请修改为其他属性';
                                continue;
                            }
                        }
                    }
                    if (isset($skuInfo['weight'])) {
                        if ($skuInfo['weight'] != $aGoodsSku['weight']) {
                            $skuInfo['old_weight'] = $aGoodsSku['weight'];
                        }
                    }
                    $skuInfo['goods_id'] = $goods_id;
                    $sku = $skuInfo['sku'];
                    unset($skuInfo['sku']);
                    $goodsSku->allowField(true)->save($skuInfo, ['id' => $nSkuId]);
                    $GoodSkuService->afterUpdate($aGoodsSku->toArray(), $skuInfo);
                    if (isset($skuInfo['status'])) {
                        $this->afterUpdateSkuStatus($aGoodsSku, $aGoods, $skuInfo['status']);
                    }
                    $GoodsLog->mdfSku($sku, $aGoodsSku->toArray(), $skuInfo);
                    Cache::store('goods')->delSkuInfo($nSkuId);
                    $aResult['sku']['s']++;
                }
            }
            if (isset($row['name'])) {
                $allsku = GoodsSku::where('goods_id', $goods_id)->select();
                foreach ($allsku as $skuInfo) {
                    $skuInfo->spu_name = $row['name'];
                    $skuInfo->save();
                    Cache::store('goods')->delSkuInfo($skuInfo->id);
                }
            }
            $GoodsLog->save($user_id, $goods_id);
            Db::commit();
            Cache::handler()->del('cache:categoryAttribute');
            return $aResult;
        } catch (Exception $ex) {
            Db::rollback();
            $aResult['spu']['f']++;
            $aResult['spu']['r'][] = $row['spu'] . ' ' . $ex->getMessage();;
            return $aResult;
        }
    }

    private function afterUpdateGoodsStatus($goodsInfo, $newStatus)
    {
        if ($goodsInfo->sales_status != $newStatus) {
            $goodsHelp = new GoodsHelp();
            switch ($newStatus) {
                case 1:  // 出售
                    $goodsHelp->pushSpuStatusQueue($goodsInfo['id'], $newStatus);
                    //sku表
                    $aSku = $goodsInfo->sku()->select();
                    foreach ($aSku as $skuInfo) {
                        if ($skuInfo->status != 1) {
                            // $GoodsLog->mdfSku($skuInfo->sku,['status'=>$skuInfo->status],['status'=>1]);
                            $skuInfo->status = 1;
                            $skuInfo->save();
                            $goodsHelp->pushSkuStatusQueue($skuInfo->id, 1);
                        }
                    }
                    break;
                case 2:  // 停售
                    $goodsHelp->pushSpuStatusQueue($goodsInfo['id'], $newStatus);
                    //sku表
                    $aSku = $goodsInfo->sku()->select();
                    foreach ($aSku as $skuInfo) {
                        if ($skuInfo->status != 2) {
                            //  $GoodsLog->mdfSku($skuInfo->sku,['status'=>$skuInfo->status],['status'=>2]);
                            $skuInfo->status = 2;
                            $skuInfo->save();
                            $goodsHelp->pushSkuStatusQueue($skuInfo->id, 2);
                        }
                    }
                    break;
                case 4: // 卖完下架
                    $goodsHelp->pushSpuStatusQueue($goodsInfo['id'], $newStatus);
                    $aSku = $goodsInfo->sku()->select();
                    //sku表
                    foreach ($aSku as $skuInfo) {
                        if ($skuInfo->status != 4) {
                            //   $GoodsLog->mdfSku($skuInfo->sku,['status'=>$skuInfo->status],['status'=>4]);
                            $skuInfo->status = 4;
                            $skuInfo->save();
                            $goodsHelp->pushSkuStatusQueue($skuInfo->id, 4);
                        }
                    }
                    break;
                case 5: // 缺货
                    $goodsHelp->pushSpuStatusQueue($goodsInfo['id'], $newStatus);
                    //sku表
                    $aSku = $goodsInfo->sku()->select();
                    foreach ($aSku as $skuInfo) {
                        if ($skuInfo->status != 5) {
                            //   $GoodsLog->mdfSku($skuInfo->sku,['status'=>$skuInfo->status],['status'=>5]);
                            $skuInfo->status = 5;
                            $skuInfo->save();
                            $goodsHelp->pushSkuStatusQueue($skuInfo->id, 5);
                        }
                    }
                    break;
            }
        }

    }

    /**
     * 导入只处理Sku
     * @author starzhan <397041849@qq.com>
     */
    private function onlyUpdateSku($row, $user_id)
    {
        $GoodSkuService = new GoodSkuService();
        $GoodsLog = new GoodsLog();
        $aResult = [
            'spu' => ['s' => 0, 'f' => 0, 'r' => []],
            'sku' => ['s' => 0, 'f' => 0, 'r' => []]
        ];
        $aSkus = [];
        foreach ($row['sku'] as $skuInfo) {
            $aSkus[] = $skuInfo['sku'];
        }
        if (!$aSkus) {
            $aResult['sku']['f']++;
            $aResult['sku']['r'][] = 'sku不能为空';
            return $aResult;
        }
        $aAlias = GoodsSkuAlias::whereIn('alias', $aSkus)->select();
        $aAliasSkuIds = [];
        $aAliasMap = [];
        foreach ($aAlias as $v) {
            $aAliasSkuIds[] = $v->sku_id;
            $aAliasMap[$v->sku_id] = $v->alias;
        }
        $GoodsSku = new GoodsSku();
        $o = $GoodsSku->whereIn('sku', $aSkus);
        if ($aAliasSkuIds) {
            $o = $o->whereOr('id', 'in', $aAliasSkuIds);
        }
        $aGoodsSkus = $o->select();
        $aGoodsid = [];
        $aSkuGoodIdMap = [];
        $aSkuIdMap = [];
        $oldSkuInfo = [];
        foreach ($aGoodsSkus as $v) {
            $aGoodsid[] = $v->goods_id;
            if (isset($aAliasMap[$v->id])) {
                $aSkuGoodIdMap[$aAliasMap[$v->id]] = $v->goods_id;
                $aSkuIdMap[$aAliasMap[$v->id]] = $v->id;
            } else {
                $aSkuGoodIdMap[$v['sku']] = $v->goods_id;
                $aSkuIdMap[$v['sku']] = $v->id;
            }
            $oldSkuInfo[$v->id] = $v;
        }
        $aGoodsMapId = [];
        if ($aGoodsid) {
            $aGoods = Goods::whereIn('id', $aGoodsid)->select();
            foreach ($aGoods as $v) {
                //  $ret = $v->toArray();
                $aGoodsMapId[$v->id] = $v;
            }
        }
        try {
            Db::startTrans();
            foreach ($row['sku'] as $skuInfo) {
                if (!isset($aSkuIdMap[$skuInfo['sku']])) {
                    $aResult['sku']['f']++;
                    $aResult['sku']['r'][] = "该sku[{$skuInfo['sku']}]不存在";
                    continue;
                }
                $skuId = $aSkuIdMap[$skuInfo['sku']];
                if (!isset($aSkuGoodIdMap[$skuInfo['sku']])) {
                    $aResult['sku']['f']++;
                    $aResult['sku']['r'][] = "该sku[{$skuInfo['sku']}]不存在";
                    continue;
                }
                $goods_id = $aSkuGoodIdMap[$skuInfo['sku']];
                if (!isset($aGoodsMapId[$goods_id])) {
                    $aResult['sku']['f']++;
                    $aResult['sku']['r'][] = "该sku[{$skuInfo['sku']}]对应的商品不存在";
                    continue;
                }
                $aGoodsInfo = $aGoodsMapId[$goods_id];
                if (isset($skuInfo['status']) && isset($row['sales_status'])) {
                    $skuInfo['status'] = $skuInfo['status'] == 0 ? $row['sales_status'] : $skuInfo['status'];
                }
                if (isset($skuInfo['weight'])) {
                    if($skuInfo['weight']!= $oldSkuInfo[$skuId]['weight']){
                        $skuInfo['old_weight'] = $oldSkuInfo[$skuId]['weight'];
                    }
                }
                if (isset($skuInfo['attributes'])) {
                    $attributes = [];
                    foreach ($skuInfo['attributes'] as &$attribute) {
                        $attribute['category_id'] = $aGoodsInfo['category_id'];
                        $attribute['goods_id'] = $goods_id;
                        if ($attribute['value_id'] == 0) {
                            $value_id = self::addSelfAttribute($attribute);
                            $attributes['attr_' . $attribute['attribute_id']] = $value_id;
                        } else {
                            self::addAttribute($attribute);
                            $attributes['attr_' . $attribute['attribute_id']] = $attribute['value_id'];
                        }
                    }
                    if ($attributes) {
                        $skuInfo['sku_attributes'] = json_encode($attributes);
                        if ($GoodSkuService->isSameSkuAttributes($goods_id, $skuInfo['sku_attributes'])) {
                            $aResult['sku']['f']++;
                            $aResult['sku']['r'][] = $skuInfo['sku'] . '已存在这个属性,请修改为其他属性';
                            continue;
                        }
                    }
                }
                $skuInfo['goods_id'] = $goods_id;
                $sku = $skuInfo['sku'];
                unset($skuInfo['sku']);
                $GoodsSku = new GoodsSku();
                $GoodsSku->allowField(true)->save($skuInfo, ['id' => $skuId]);
                $GoodSkuService->afterUpdate($oldSkuInfo[$skuId], $skuInfo);
                if (isset($skuInfo['status']) && isset($row['sales_status'])) {
                    $this->afterUpdateSkuStatus($oldSkuInfo[$skuId], $aGoodsInfo, $skuInfo['status']);
                }
                Cache::store('goods')->delSkuInfo($skuId);
                $GoodsLog->mdfSku($sku, $oldSkuInfo[$skuId]->toArray(), $skuInfo);
                Cache::store('goods')->delGoodsInfo($goods_id);
                $aResult['sku']['s']++;
                $GoodsLog->save($user_id, $goods_id);
            }
            Db::commit();
            Cache::handler()->del('cache:categoryAttribute');
        } catch (Exception $ex) {
            Db::rollback();
            $aResult['spu']['f']++;
            $aResult['spu']['r'][] = $ex->getMessage();;
            return $aResult;
        }

        return $aResult;
    }

    private function afterUpdateSkuStatus($oldSkuInfo, $oldGoodsInfo, $newStatus)
    {

        $GoodsHelp = new GoodsHelp();
        if ($oldSkuInfo['status'] != $newStatus) {
            switch ($newStatus) {
                case 1://在售
                    $sales_status = 6;
                    $count = $oldGoodsInfo->sku()->where('status', '<>', 1)->count();
                    if (!$count) {
                        $sales_status = 1;
                    }
                    $GoodsHelp->pushSkuStatusQueue($oldSkuInfo['id'], $newStatus);
                    if ($oldGoodsInfo->sales_status != $sales_status) {
                        $oldGoodsInfo->sales_status = $sales_status;
                        $oldGoodsInfo->save();
                        $GoodsHelp->pushSpuStatusQueue($oldGoodsInfo->id, $sales_status);
                        $GoodsHelp->putPushList($oldGoodsInfo->id);
                    }
                    break;
                case 2://停售
                    $GoodsHelp->pushSkuStatusQueue($oldSkuInfo['id'], $newStatus);
                    $count = $oldGoodsInfo->sku()->where('status', '<>', $newStatus)->count();
                    if (!$count) {
                        if ($oldGoodsInfo->sales_status != 2) {
                            $oldGoodsInfo->sales_status = 2;
                            $oldGoodsInfo->platform = 0;
                            $oldGoodsInfo->save();
                            $GoodsHelp->pushSpuStatusQueue($oldGoodsInfo->id, 2);
                            $GoodsHelp->putPushList($oldGoodsInfo->id);
                        }
                    } else {
                        if ($oldGoodsInfo->sales_status != 6) {
                            $oldGoodsInfo->sales_status = 6;
                            $oldGoodsInfo->save();
                            $GoodsHelp->pushSpuStatusQueue($oldGoodsInfo->id, 2);
                            $GoodsHelp->putPushList($oldGoodsInfo->id);
                        }
                    }
                    break;
                case 4:// 卖完下架
                    $GoodsHelp->pushSkuStatusQueue($oldSkuInfo['id'], $newStatus);
                    break;
                case 5:
                    $GoodsHelp->pushSkuStatusQueue($oldSkuInfo['id'], $newStatus);
                    break;
            }
        }
    }

    /**
     * 添加产品属性
     * @param array $info
     * @param array $act 是插入还是更新
     * @return boolean
     */
    public static function addAttribute(array $info)
    {
        //添加分类属性
        $categoryAttribute = new CategoryAttribute();
        $categoryAttr = $categoryAttribute->where('category_id', $info['category_id'])->where('attribute_id', $info['attribute_id'])->find();
        if (!$categoryAttr) {
            $categoryAttrArr = [
                'category_id' => $info['category_id'],
                'attribute_id' => $info['attribute_id'],
                'group_id' => 1,
                'sku' => 1,
                'gallery' => 0,
                'value_range' => '[' . $info['value_id'] . ']'
            ];
            $attributeGroup = new AttributeGroup();
            $groupInfo = $attributeGroup->where(['category_id' => $info['category_id']])->find();
            if (!$groupInfo) {
                $attributeGroup->allowField(true)->save(['category_id' => $info['category_id'], 'name' => '分组1']);
                $group_id = $attributeGroup->id;
            } else {
                $group_id = $groupInfo->id;
            }
            $categoryAttrArr['group_id'] = $group_id;
            $categoryAttribute->allowField(true)->save($categoryAttrArr);
        } else {
            $value_range = json_decode($categoryAttr->value_range);
            if (!in_array($info['value_id'], $value_range)) {
                $value_range[] = $info['value_id'];
                $categoryAttribute->where('category_id', $info['category_id'])->where('attribute_id', $info['attribute_id'])->update(['value_range' => json_encode($value_range), 'sku' => 1]);
            }
        }
        //添加商品属性
        $goodsAttrAttr = [
            'attribute_id' => $info['attribute_id'],
            'goods_id' => $info['goods_id'],
            'value_id' => $info['value_id'],
        ];
        $goodsAttribute = new GoodsAttribute();
        if (!$goodsAttribute->where($goodsAttrAttr)->count()) {
            $goodsAttribute->allowField(true)->save($goodsAttrAttr);
        }
    }


    /**
     * 产品自定义属性值
     * @param array $info
     * @return int
     */
    public static function addSelfAttribute(array $info)
    {
        //添加分类属性
        $valueStartIds = [
            11 => 171,
            15 => 298
        ];
        $categoryAttribute = new CategoryAttribute();
        $categoryAttr = $categoryAttribute->where('category_id', $info['category_id'])->where('attribute_id', $info['attribute_id'])->find();
        if (!$categoryAttr) {
            $categoryAttrArr = [
                'category_id' => $info['category_id'],
                'attribute_id' => $info['attribute_id'],
                'group_id' => 1,
                'sku' => 1,
                'value_range' => '[]'
            ];
            $attributeGroup = new AttributeGroup();
            $groupInfo = $attributeGroup->where(['category_id' => $info['category_id']])->find();
            if (!$groupInfo) {
                $attributeGroup->allowField(true)->save(['category_id' => $info['category_id'], 'name' => '分组1']);
                $group_id = $attributeGroup->id;
            } else {
                $group_id = $groupInfo->id;
            }
            $categoryAttrArr['group_id'] = $group_id;
            $categoryAttribute->allowField(true)->save($categoryAttrArr);
        }
        //添加商品属性
        $goodsAttrAttr = [
            'attribute_id' => $info['attribute_id'],
            'goods_id' => $info['goods_id'],
            'alias' => $info['value']
        ];
        $goodsAttribute = new GoodsAttribute();
        $goodsAttributeInfo = $goodsAttribute->where($goodsAttrAttr)->find();
        if ($goodsAttributeInfo) {
            $value_id = $goodsAttributeInfo->value_id;
        } else {
            $lastAttributeInfo = $goodsAttribute->where(['attribute_id' => $info['attribute_id'], 'goods_id' => $info['goods_id']])->order('value_id desc')->find();
            if ($lastAttributeInfo) {
                $value_id = $lastAttributeInfo->value_id + 1;
            } else {
                $value_id = $valueStartIds[$info['attribute_id']];
            }
            $goodsAttrAttr['value_id'] = $value_id;
            $goodsAttribute->allowField(true)->save($goodsAttrAttr);
        }
        return $value_id;
    }

    /**
     * 保存文件
     * @param string $filename
     * @param array $params
     */
    public static function saveFile($filename, &$params)
    {
        if (empty($params['content'])) {
            throw new Exception('添加的内容不能为空');
        }
        $start = strpos($params['content'], ',');
        $content = substr($params['content'], $start + 1);
        file_put_contents($filename, base64_decode(str_replace(" ", "+", $content)));
        return $filename;
    }


    public function getDaoWhere($params)
    {

        $o = new Goods();
        $o = $o->where('status', 1);
        if (isset($params['status']) && !empty($params['status'])) {
            $o = $o->where('sales_status', $params['status']);
        }

        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'name':
                    $o = $o->where('name', 'like', "%" . $params['snText'] . "%");
                    break;
                case 'declareName':
                    $o = $o->where('declare_name', 'like', "%" . $params['snText'] . "%");
                    break;
                case 'declareEnName':
                    $o = $o->where('declare_en_name', 'like', "%" . $params['snText'] . "%");
                    break;
                case 'packingName':
                    $o = $o->where('packing_name', 'like', "%" . $params['snText'] . "%");
                    break;
                case 'packingEnName':
                    $o = $o->where('packing_en_name', 'like', "%" . $params['snText'] . "%");
                    break;
                case 'sku':
                    $GoodSkuService = new GoodSkuService();
                    $sku_id = $GoodSkuService->getSkuIdBySku($params['snText']);
                    if ($sku_id) {
                        $aGoodsSku = GoodSkuService::getBySkuID($sku_id);
                        $o = $o->where('id', $aGoodsSku['goods_id']);
                    } else {
                        $o = $o->where('id', '-1');
                    }
                    break;
                case 'spu':
                    $o = $o->where('spu', 'like', $params['snText'] . "%");
                    break;
                case 'alias':
                    $o = $o->where('alias', 'like', "%" . $params['snText'] . "%");
                    break;
                default:
                    break;
            }
        }

        if (isset($params['dateType']) && $params['dateType'] == 'sellTime') {
            if (isset($params['date_start']) && !empty($params['date_start'])) {
                $start = strtotime($params['date_start']);
                $start && $o = $o->where('publish_time', '>', $start);
            }
            if (isset($params['date_end']) && !empty($params['date_end'])) {
                $end = strtotime($params['date_end'] . ' 23:59:59');
                $end && $o = $o->where('publish_time', '<', $end);
            }
        }

        if (isset($params['dateType']) && $params['dateType'] == 'stopTime') {
            if (isset($params['date_start']) && !empty($params['date_start'])) {
                $start = strtotime($params['date_start']);
                $start && $o = $o->where('stop_selling_time', '>', $start);
            }

            if (isset($params['date_end']) && !empty($params['date_end'])) {
                $end = strtotime($params['date_end'] . ' 23:59:59');
                $end && $o = $o->where('stop_selling_time', '<', $end);
            }
        }
        if (isset($params['category_id']) && $params['category_id']) {
            $params['category_id'] = intval($params['category_id']);
            $aCategorys = Cache::store('category')->getCategoryTree();
            $aCategory = isset($aCategorys[$params['category_id']]) ? $aCategorys[$params['category_id']] : [];
            if ($aCategory) {
                $searchIds = [$params['category_id']];
                if ($aCategory['child_ids']) {
                    $searchIds = array_merge($searchIds, $aCategory['child_ids']);
                }
                $o = $o->where('category_id', 'in', $searchIds);
            }
        }
        if (isset($params['without_img']) && $params['without_img']) {
            $o = $o->where('thumb', '');
        }
        return $o;
    }

    /**
     * @title 注释..
     * @param GoodsLang $GoodsLang
     * @param $goods_id
     * @return array
     * @author starzhan <397041849@qq.com>
     */
    private $goodsId = 0;
    private $goodsInfo = [];

    public function getLang($goods_id)
    {
        $result = [];
        if ($goods_id == $this->goodsId) {
            return $this->goodsInfo;
        } else {
            $tmp = GoodsLang::where('goods_id', $goods_id)->field('lang_id,goods_id,description,title,packing_name,declare_name,tags')->select();
            foreach ($tmp as $v) {
                $result[$v['lang_id']] = $v;
            }
            if ($result) {
                $this->goodsId = $goods_id;
                $this->goodsInfo = $result;

            }
            return $this->goodsInfo;
        }
    }

    private function getAlias($sku_id)
    {
        $result = [];
        $alias = GoodsSkuAlias::where('type', 'in', [2])->where('sku_id', $sku_id)->field('alias')->select();
        foreach ($alias as $v) {
            $result[] = $v['alias'];
        }
        return implode(',', $result);
    }

    /**
     * @title 注释..
     * @author starzhan <397041849@qq.com>
     */
    private function ExportCategoryName($name)
    {
        return str_replace('>', '/', $name);
    }

    private function getPlatformSaleName(GoodsHelp $GoodsHelp, $platform_sale)
    {
        $result = [
            'ebay' => '',
            'wish' => '',
            'amazon' => '',
            'aliExpress' => ''
        ];
        $arr = json_decode($platform_sale, true);
        foreach ($arr as $k => $v) {
            $result[$k] = $GoodsHelp->getPlatformSaleAttr($v);
        }
        return $result;
    }

    public function getSupplierName($supplier_id)
    {
        $tmp = Cache::store('supplier')->getSupplier($supplier_id);
        return $tmp['company_name'] ?? '';
    }

    private function getTransportPropertyName(GoodsHelp $GoodsHelp, $transport_property)
    {
        return $GoodsHelp->getProTransPropertiesTxt($transport_property);
    }

    private function getAttr($attrData, $goods_id)
    {
        $attr = [
            'attr_name_1' => '',
            'attr_value_1' => '',
            'attr_name_2' => '',
            'attr_value_2' => '',
            'attr_name_3' => '',
            'attr_value_3' => '',
        ];
        $attrData = json_decode($attrData, true);
        $data = GoodsHelp::getAttrbuteInfoBySkuAttributes($attrData, $goods_id);
        foreach ($data as $k => $value) {
            $i = $k + 1;
            $attr['attr_name_' . $i] = $value['name'];
            $attr['attr_value_' . $i] = $value['value'];
        }
        return $attr;
    }

    public function getWhere($params)
    {
        $where = ' g.status =1 ';
        $join = [];
        if (isset($params['status']) && !empty($params['status'])) {
            $where .= ' and g.sales_status = ' . $params['status'];

        } else {
            // $where.= ' and g.sales_status in (1,2)';
        }

        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'name':
                    $where .= " and g.name like '%" . $params['snText'] . "%'";
                    break;
                case 'declareName':
                    $where .= " and g.declare_name like '%" . $params['snText'] . "%'";
                    break;
                case 'declareEnName':
                    $where .= " and g.declare_en_name like '%" . $params['snText'] . "%'";
                    break;
                case 'packingName':
                    $where .= " and g.packing_name like '%" . $params['snText'] . "%'";
                    break;
                case 'packingEnName':
                    $where .= " and g.packing_en_name like '%" . $params['snText'] . "%'";
                    break;
                case 'sku':
                    $ServiceGoodsSku = new ServiceGoodsSku();
                    $skuId = $ServiceGoodsSku->getSkuIdBySku($params['snText']);
                    if ($skuId) {
                        $aGoodsSku = ServiceGoodsSku::getBySkuID($skuId);
                        if ($aGoodsSku) {
                            $where .= " and g.id={$aGoodsSku['goods_id']} ";
                        }
                    } else {
                        $where .= " and false ";
                    }
                    break;
                case 'spu':
                    $where .= " and g.spu like '" . $params['snText'] . "%'";
                    break;
                case 'alias':
                    $where .= " and g.alias like '%" . $params['snText'] . "%'";
                    break;
                default:
                    break;
            }
        }

        if (isset($params['dateType']) && $params['dateType'] == 'sellTime') {
            if (isset($params['date_start']) && !empty($params['date_start'])) {
                $start = strtotime($params['date_start']);
                $start ? $where .= ' and g.publish_time > ' . $start : '';
            }

            if (isset($params['date_end']) && !empty($params['date_end'])) {
                $end = strtotime($params['date_end'] . ' 23:59:59');
                $end ? $where .= ' and g.publish_time < ' . $end : '';
            }
        }

        if (isset($params['dateType']) && $params['dateType'] == 'stopTime') {
            if (isset($params['date_start']) && !empty($params['date_start'])) {
                $start = strtotime($params['date_start']);
                $start ? $where .= ' and g.stop_selling_time > ' . $start : '';
            }

            if (isset($params['date_end']) && !empty($params['date_end'])) {
                $end = strtotime($params['date_end'] . ' 23:59:59');
                $end ? $where .= ' and g.stop_selling_time < ' . $end : '';
            }
        }
        if (isset($params['category_id']) && $params['category_id']) {
            $params['category_id'] = intval($params['category_id']);
            $aCategorys = Cache::store('category')->getCategoryTree();
            $aCategory = isset($aCategorys[$params['category_id']]) ? $aCategorys[$params['category_id']] : [];
            if ($aCategory) {
                $searchIds = [$params['category_id']];
                if ($aCategory['child_ids']) {
                    $searchIds = array_merge($searchIds, $aCategory['child_ids']);
                }
                $where .= ' and g.category_id in (' . implode(',', $searchIds) . ') ';
            }
        }
        if (isset($params['without_img']) && $params['without_img']) {
            $where .= " and g.thumb = '' ";
        }
        $wheres['where'] = $where;
        $wheres['join'] = $join;
        return $wheres;
    }

    private function getSkuThumb($sku_id)
    {

        $result = [
            'sku_thumb1' => '',
            'sku_thumb2' => '',
            'sku_thumb3' => '',
            'sku_thumb4' => '',
            'sku_thumb5' => '',
            'sku_thumb6' => '',
            'sku_thumb7' => '',
            'sku_thumb8' => '',
            'sku_thumb9' => ''
        ];
        $list = GoodsGallery::where('sku_id', $sku_id)
            ->order('is_default asc')
            ->order('sort asc')
            ->limit(9)
            ->select();
        $i = 1;
        foreach ($list as $v) {
            $result['sku_thumb' . $i] = GoodsImage::getThumbPath($v['path'], 0, 0);
            $i++;
        }
        return $result;
    }

    private function getSpuThumb($goods_id)
    {

        $result = [
            'spu_thumb1' => '',
            'spu_thumb2' => '',
            'spu_thumb3' => '',
            'spu_thumb4' => '',
            'spu_thumb5' => '',
            'spu_thumb6' => '',
            'spu_thumb7' => '',
            'spu_thumb8' => '',
            'spu_thumb9' => ''
        ];
        $where['goods_id'] = $goods_id;
        $where['sku_id'] = 0;
        $list = GoodsGallery::where($where)
            ->order('is_default asc')
            ->order('sort asc')
            ->limit(10)
            ->select();
        $i = 1;
        $index = 0;
        foreach ($list as $v) {
            if ($index == 0) {
                $index++;
                continue;
            }
            $result['spu_thumb' . $i] = GoodsImage::getThumbPath($v['path'], 0, 0);
            $i++;
        }
        return $result;
    }

    public function getBaseField($filed = [])
    {
        $model = new ExportField();
        if ($filed) {
            $tmp1 = $model->where('type', ExportField::TYPE_GOODS)
                ->order('sort asc')
                ->select();
            $tmp = [];
            foreach ($tmp1 as $k => $v) {
                if (in_array($v['field_key'], $filed)) {
                    $tmp[] = $v;
                }
            }
        } else {
            $tmp = $model->where('type', ExportField::TYPE_GOODS)
                ->order('sort asc')
                ->select();
        }
        $result = [];
        foreach ($tmp as $v) {
            $row = [];
            $row['title'] = $v['field_label'];
            $row['key'] = $v['field_key'];
            $result[] = $row;
        }
        return $result;
    }

    public function getExportSkuField($filed)
    {
        $result = [];
        $result['data'] = $this->getBaseField($filed);
        $result['key'] = $filed;
        return $result;
    }

    private function getExportWhere($params)
    {
        $GoodsHelp = new GoodsHelp();
        if (isset($params['ids']) && $params['ids']) {
            $ids = json_decode($params['ids'], true);
            if (is_array($ids) && $ids) {
                $where['where'] = ' g.status =1 and g.id in (' . implode(',', $ids) . ')';
                $where['join'] = [];
                return $where;
            }
        }
        return $GoodsHelp->getWhere($params);
    }

    public function getExportSkuData($params = [], $header = [])
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $topLine = 0;
        $result = ['status' => 0, 'message' => 'error'];
        $GoodsHelp = new GoodsHelp();
        $wheres = $this->getExportWhere($params);
        $GoodsLang = new GoodsLang();
        $Goods = new Goods();
        $GoodsSku = new GoodsSku();
        $sql = "SELECT g.id,
                   g.alias,
                   g.packing_name,
                   g.packing_en_name,
                   g.spu,g.name,
                   g.thumb as main_image,
                   g.category_id,
                   g.same_weight,
                   g.warehouse_id,
                   g.is_multi_warehouse,
                   g.supplier_id,
                   g.brand_id,
                   g.tags,
                   g.declare_name,
                   g.declare_en_name,
                   g.developer_id,
                   g.purchaser_id,
                   g.source_url,
                   g.hs_code,
                   g.dev_platform_id,
                   g.transport_property,
                   g.sales_status,
                   g.platform_sale,
                   gs.id as sku_id,
                   gs.sku,
                   gs.status,
                   gs.thumb as sku_thumb,
                   gs.width,
                   gs.height,
                   gs.weight,
                   gs.length,
                   gs.cost_price,
                   gs.retail_price,
                   gs.sku_attributes
             FROM  {$GoodsSku->getTable()} as gs left join {$Goods->getTable()}  g on g.id = gs.goods_id";
        $sql .= " where  " . $wheres['where'];
        $sqlCount = "select count(*) as num from {$GoodsSku->getTable()} gs left join {$Goods->getTable()} as g on g.id = gs.goods_id where " . $wheres['where'];
        $countData = Db::query($sqlCount);
        $countData = reset($countData);
        $count = $countData['num'] ?? 0;
        $index = 1;
        $skus = [];
        $imageUrl = Cache::store('configParams')->getConfig('innerPicUrl')['value'] . DS;
        self::$imageUrl = $imageUrl;
        $page = 1;
        $page_size = 10000;
        $page_total = ceil($count / $page_size);
        if (!$count) {
            throw new Exception('导出的数据为空');
        }
        $sql .= ' order by g.id asc ';

        if (!$header) {
            throw new Exception('请选择导出的字段');
        }
        $filed = $header['key'];
        if (!$filed) {
            throw new Exception('请选择导出的字段');
        }
        $header = $header['data'];
        $result = ['status' => 0, 'message' => 'error'];
        try {
            $aHeader = [];
            foreach ($header as $v) {
                $v['title'] = $this->charset($v['title'], 1);
                $aHeader[] = $v['title'];
            }
            $downFileName = $params['file_name'];
            $fileName = str_replace('.csv', '', $downFileName);
            $file = ROOT_PATH . 'public' . DS . 'download' . DS . 'goods';
            $filePath = $file . DS . $downFileName;
            //无文件夹，创建文件夹
            if (!is_dir($file) && !mkdir($file, 0777, true)) {
                $result['message'] = '创建文件夹失败。';
                @unlink($filePath);
                return $result;
            }
            $filePath = str_replace(">", "_", $filePath);
            $fp = fopen($filePath, 'w+');
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $aHeader);
            fclose($fp);
            try {
                do {
                    $offset = ($page - 1) * $page_size;
                    $dosql = $sql . " limit  {$offset},{$page_size}";
                    $Q = new Query();
                    $a = $Q->query($dosql, [], true, true);
                    $fp = fopen($filePath, 'a');
                    while ($row = $a->fetch(PDO::FETCH_ASSOC)) {
                        $list = $this->fetchList($row, $header);
                        if (in_array('ps_variation_1_ps_variation_sku', $filed)) {
                            $skus[$index]['sku'] = $row['sku'];
                            $skus[$index]['cost_price'] = $row['cost_price'];
                            $skus[$index]['sku_attributes'] = $row['sku_attributes'];
                            if ($count - $index >= 1) {
                                $index++;
                                continue;
                            }
                            $len = 1;
                            foreach ($skus as $key => $sku) {
                                $list['ps_variation' . '_' . $len . '_' . 'ps_variation_sku'] = $sku['sku'];
                                $attr = json_decode($sku['sku_attributes'], true);
                                $attrs = GoodsHelp::getAttrbuteInfoBySkuAttributes($attr, $row['id']);
                                $tmp = [];
                                foreach ($attrs as $val) {
                                    $tmp[] = $val['value'];
                                }
                                $list['ps_variation' . '_' . $len . '_' . 'ps_variation_name'] = implode('_', $tmp);
                                $list['ps_variation' . '_' . $len . '_' . 'ps_variation_price'] = $sku['cost_price'];
                                $list['ps_variation' . '_' . $len . '_' . 'ps_variation_stock'] = '';
                                $len = $len + 1;
                            }
                            if ($len < 20) {
                                for ($len; $len < 21; ++$len) {
                                    $list['ps_variation' . '_' . $len . '_' . 'ps_variation_sku'] = '';
                                    $list['ps_variation' . '_' . $len . '_' . 'ps_variation_name'] = '';
                                    $list['ps_variation' . '_' . $len . '_' . 'ps_variation_price'] = '';
                                    $list['ps_variation' . '_' . $len . '_' . 'ps_variation_stock'] = '';
                                }
                            }
                            $rowContent = [];
                            foreach ($header as $h) {
                                $field = $h['key'];
                                $value = isset($list[$field]) ? $list[$field] : '';
                                $rowContent[] = $value;
                            }
                            fputcsv($fp, $rowContent);
                            $index =0;
                            $skus =[];
                        } else {
                            $rowContent = [];
                            foreach ($header as $h) {
                                $field = $h['key'];
                                $value = isset($list[$field]) ? $list[$field] : '';
                                $rowContent[] = $value;
                            }
                            fputcsv($fp, $rowContent);
                        }
                    }
                    unset($a);
                    unset($Q);
                    fclose($fp);
                    $page++;
                } while ($page <= $page_total);

            } catch (Exception $ex) {
                // halt($ex->getMessage());
            }
            try {
                if (!isset($params['download'])) {
                    $logExportDownloadFiles = new LogExportDownloadFiles();
                    $data = [];
                    $data['file_extionsion'] = 'csv';
                    $data['saved_path'] = $filePath;
                    $data['download_file_name'] = $downFileName;
                    $data['type'] = 'supplier_export';
                    $data['created_time'] = time();
                    $data['updated_time'] = time();
                    $logExportDownloadFiles->allowField(true)->isUpdate(false)->save($data);
                    $udata = [];
                    $udata['id'] = $logExportDownloadFiles->id;
                    $udata['file_code'] = date('YmdHis') . $logExportDownloadFiles->id;
                    $logExportDownloadFiles->allowField(true)->isUpdate(true)->save($udata);
                    $result['status'] = 1;
                    $result['message'] = 'OK';
                    $result['file_code'] = $udata['file_code'];
                    $result['file_name'] = $fileName;
                    $result['type'] = '.csv';
                    return $result;
                } else {
                    $zipPath = $file . DS . $fileName . ".zip";
                    $PHPZip = new PHPZip();
                    $zipData = [
                        [
                            'name' => $fileName,
                            'path' => $filePath
                        ]
                    ];
                    $PHPZip->saveZip($zipData, $zipPath);
                    @unlink($filePath);
                    $applyRecord = ReportExportFiles::get($params['apply_id']);
                    $applyRecord['exported_time'] = time();
                    $applyRecord['download_url'] = '/download/goods/' . $fileName . ".zip";
                    $applyRecord['status'] = 1;
                    $applyRecord->isUpdate()->save();
                }
            } catch (\Exception $e) {
                $result['message'] = '创建导出文件日志失败。' . $e->getMessage();
                @unlink($filePath);
                return $result;
            }

        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * @title 处理导出的每行的信息
     * @param $list
     * @param $field
     * @author starzhan <397041849@qq.com>
     */
    private function fetchList($row, $header)
    {
        $GoodsHelp = new GoodsHelp();
        $Goods = new Goods();
        $list = [];
        foreach ($header as $fieldData) {
            $field = $fieldData['key'];
            if (isset($list[$field])) {
                continue;
            }
            if (isset($fieldData['value'])) {
                $list[$field] = $fieldData['value'];
                continue;
            }
            if ('spu' == $field) {
                $list['spu'] = $row['spu'];
                $list['spu'] = $this->charset($row['spu']);
                continue;
            }
            if ('name' == $field) {
                $list['name'] = $this->charset($row['name']);
                continue;
            }
            if ('main_image' == $field) {
                if ($row['main_image']) {
                    $list['main_image'] = GoodsImage::getThumbPath($row['main_image'], 800, 800);
                    $list['main_image'] = $this->charset($list['main_image']);
                } else {
                    $list['main_image'] = '';
                }
                continue;
            }
            if ('sku_thumb' == $field) {
                if ($row['sku_thumb']) {
                    $list['sku_thumb'] = GoodsImage::getThumbPath($row['sku_thumb'], 800, 800);
                    $list['sku_thumb'] = $this->charset($list['sku_thumb']);
                } else {
                    $list['sku_thumb'] = '';
                }
                continue;
            }
            if (in_array($field, [
                'sku_thumb1',
                'sku_thumb2',
                'sku_thumb3',
                'sku_thumb4',
                'sku_thumb5',
                'sku_thumb6',
                'sku_thumb7',
                'sku_thumb8',
                'sku_thumb9'], $field)) {
                $aThumb = $this->getSkuThumb($row['sku_id']);
                $list['sku_thumb1'] = $aThumb['sku_thumb1'];
                $list['sku_thumb2'] = $aThumb['sku_thumb2'];
                $list['sku_thumb3'] = $aThumb['sku_thumb3'];
                $list['sku_thumb4'] = $aThumb['sku_thumb4'];
                $list['sku_thumb5'] = $aThumb['sku_thumb5'];
                $list['sku_thumb6'] = $aThumb['sku_thumb6'];
                $list['sku_thumb7'] = $aThumb['sku_thumb7'];
                $list['sku_thumb8'] = $aThumb['sku_thumb8'];
                $list['sku_thumb9'] = $aThumb['sku_thumb9'];
                continue;
            }
            if (in_array($field, [
                'spu_thumb1',
                'spu_thumb2',
                'spu_thumb3',
                'spu_thumb4',
                'spu_thumb5',
                'spu_thumb6',
                'spu_thumb7',
                'spu_thumb8',
                'spu_thumb9'])) {
                $aThumb = $this->getSpuThumb($row['id']);
                $list['spu_thumb1'] = $aThumb['spu_thumb1'];
                $list['spu_thumb2'] = $aThumb['spu_thumb2'];
                $list['spu_thumb3'] = $aThumb['spu_thumb3'];
                $list['spu_thumb4'] = $aThumb['spu_thumb4'];
                $list['spu_thumb5'] = $aThumb['spu_thumb5'];
                $list['spu_thumb6'] = $aThumb['spu_thumb6'];
                $list['spu_thumb7'] = $aThumb['spu_thumb7'];
                $list['spu_thumb8'] = $aThumb['spu_thumb8'];
                $list['spu_thumb9'] = $aThumb['spu_thumb9'];
                continue;
            }

            if (in_array($field, ['name_en', 'description', 'description_cn', 'description_ga'])) {
                $aLang = $this->getLang($row['id']);
                $list['name_en'] = '';
                $list['description'] = '';
                $list['description_cn'] = '';
                if (isset($aLang[1])) {
                    $list['description'] = $aLang[1]['description'];
                    $list['description'] = preg_replace("/[\f\n\r]+/", PHP_EOL, $list['description']);
                    $list['description'] = $this->charset($list['description']);
                }
                if (isset($aLang[2])) {
                    $list['name_en'] = $aLang[2]['title'];
                    $list['name_en'] = $this->charset($list['name_en']);
                    $list['description_en'] = preg_replace("/(\xc2\xa0)/", " ", $aLang[2]['description']);
                    $list['description_en'] = preg_replace("/[\f\n\r]+/", PHP_EOL, $list['description_en']);
                    $list['description_en'] = $this->charset($list['description_en']);
                    $list['description_ga'] = str_replace(PHP_EOL, "<br/>" . PHP_EOL, $list['description_en']);
                }
                continue;
            }
            if ($field == 'declare_name') {
                $list['declare_name'] = $this->charset($row['declare_name']);
                continue;
            }
            if ($field == 'declare_en_name') {
                $list['declare_en_name'] = $row['declare_en_name'];
                $list['declare_en_name'] = $this->charset($row['declare_en_name']);
                continue;
            }
            if ($field == 'packing_name') {
                $list['packing_name'] = $this->charset($row['packing_name']);
                continue;
            }
            if ($field == 'packing_en_name') {
                $list['packing_en_name'] = $row['packing_en_name'];
                $list['packing_en_name'] = $this->charset($row['packing_en_name']);
                continue;
            }
            if ($field == 'alias') {
                $list['alias'] = $row['alias'];
                $list['alias'] = $this->charset($row['alias']);
                continue;
            }
            if ('category_name' == $field) {
                $category_name = $Goods->getCategoryAttr('', ['category_id' => $row['category_id']]);
                $tmpCate = explode('>', $category_name);
                $category_name = implode('/', $tmpCate);
                $list['category_name'] = $this->charset($category_name);
                continue;
            }
            if (in_array($field, ['ebay', 'wish', 'amazon', 'aliExpress'])) {
                $platform_sale = $row['platform_sale'];
                $aPlatformSale = $this->getPlatformSaleName($GoodsHelp, $platform_sale);
                $list['ebay'] = $aPlatformSale['ebay'];
                $list['wish'] = $aPlatformSale['wish'];
                $list['amazon'] = $aPlatformSale['amazon'];
                $list['aliExpress'] = $aPlatformSale['aliExpress'];
                $list['ebay'] = $this->charset($list['ebay']);
                $list['wish'] = $this->charset($list['wish']);
                $list['amazon'] = $this->charset($list['amazon']);
                $list['aliExpress'] = $this->charset($list['aliExpress']);
                continue;
            }
            if ('warehouse_id' == $field) {
                $list['warehouse_id'] = $GoodsHelp->getWarehouseById($row['warehouse_id']);
                $list['warehouse_id'] = $this->charset($list['warehouse_id']);
                continue;
            }

            if ('same_weight' == $field) {
                $list['same_weight'] = $row['same_weight'] == 1 ? '是' : '否';
                $list['same_weight'] = $this->charset($list['same_weight']);
                continue;
            }

            if ('is_multi_warehouse' == $field) {
                $list['is_multi_warehouse'] = $row['is_multi_warehouse'] == 1 ? '是' : '否';
                $list['is_multi_warehouse'] = $this->charset($list['is_multi_warehouse']);
                continue;
            }

            if ('supplier_id' == $field) {
                $list['supplier_id'] = $this->getSupplierName($row['supplier_id']);
                $list['supplier_id'] = $this->charset($list['supplier_id']);
                continue;
            }

            if ('brand_id' == $field) {
                $list['brand_id'] = $Goods->getBrandAttr(null, ['brand_id' => $row['brand_id']]);
                $list['brand_id'] = $this->charset($list['brand_id']);
                continue;
            }

            if ('tags' == $field) {
                $list['tags'] = $this->charset($row['tags']);
                continue;
            }

            if ('sales_status' == $field) {
                $list['sales_status'] = $Goods->getSalesStatusTxtAttr(null, ['sales_status' => $row['sales_status']]);
                $list['sales_status'] = $this->charset($list['sales_status']);
                continue;
            }

            if ('status' == $field) {
                $list['status'] = $Goods->getSalesStatusTxtAttr(null, ['sales_status' => $row['status']]);
                $list['status'] = $this->charset($list['status']);
                continue;
            }

            if ('developer_id' == $field) {
                $list['developer_id'] = $Goods->getDeveloperWithJobNumberAttr(null, ['developer_id' => $row['developer_id']]);
                $list['developer_id'] = $this->charset($list['developer_id']);
                continue;
            }

            if ('hs_code' == $field) {
                $list['hs_code'] = $row['hs_code'];
                continue;
            }

            if ('dev_platform_id' == $field) {
                $list['dev_platform_id'] = $Goods->getDevPlatform(null, ['dev_platform_id' => $row['dev_platform_id']]);
                $list['dev_platform_id'] = $this->charset($list['dev_platform_id']);
                continue;
            }

            if ('purchaser_id' == $field) {
                $list['purchaser_id'] = $Goods->getPurchaserWithJobNumberAttr(null, ['purchaser_id' => $row['purchaser_id']]);
                $list['purchaser_id'] = $this->charset($list['purchaser_id']);
                continue;
            }
            if ('transport_property' == $field) {
                $list['transport_property'] = $this->getTransportPropertyName($GoodsHelp, $row['transport_property']);
                $list['transport_property'] = $this->charset($list['transport_property']);
                continue;
            }

            if ('source_url' == $field) {
                $list['source_url'] = $this->charset($row['source_url']);
                continue;
            }

            if ('type' == $field) {
                $list['type'] = '';
                continue;
            }

            if ('purchase_link' == $field) {
                $SupplierOfferService = new SupplierOfferService();
                $aLink = $SupplierOfferService->getGoodsLink($row['sku_id'], $row['supplier_id']);
                if ($aLink) {
                    $list['purchase_link'] = reset($aLink);
                } else {
                    $list['purchase_link'] = '';
                }
                continue;
            }

            if (in_array($field, ['attr_name_1', 'attr_name_2', 'attr_name_3', 'attr_value_1', 'attr_value_2', 'attr_value_3'])) {
                $attr = $this->getAttr($row['sku_attributes'], $row['id']);
                $list['attr_name_1'] = $attr['attr_name_1'];
                $list['attr_name_2'] = $attr['attr_name_2'];
                $list['attr_name_3'] = $attr['attr_name_3'];
                $list['attr_value_1'] = $attr['attr_value_1'];
                $list['attr_value_2'] = $attr['attr_value_2'];
                $list['attr_value_3'] = $attr['attr_value_3'];
                $list['attr_name_1'] = $this->charset($list['attr_name_1']);
                $list['attr_name_2'] = $this->charset($list['attr_name_2']);
                $list['attr_name_3'] = $this->charset($list['attr_name_3']);
                $list['attr_value_1'] = $this->charset($list['attr_value_1']);
                $list['attr_value_2'] = $this->charset($list['attr_value_2']);
                $list['attr_value_3'] = $this->charset($list['attr_value_3']);
                continue;
            }

            if ('weight' == $field) {
                $list['weight'] = $row['weight'];
                continue;
            }

            if ('ps_product_weight' == $field) {
                $list['ps_product_weight'] = $row['weight'] ? number_format($row['weight'] / 1000, 2) : 0;
                continue;
            }
            if ('ps_stock' == $field) {
                $list['ps_stock'] = 0;
                continue;
            }
            if ('ps_days_to_ship' == $field) {
                $list['ps_days_to_ship'] = 3;
                continue;
            }
            if ('ps_mass_upload_variation_help' == $field) {
                $list['ps_mass_upload_variation_help'] = '';
                continue;
            }

            if ('productIdType' == $field) {
                $list['productIdType'] = 'UPC';
                continue;
            }
            if ('productId' == $field) {
                $list['productId'] = '';
                continue;
            }
            if ('pound' == $field) {
                $list['重量'] = $row['weight'] * 0.0022046;
                continue;
            }
            if ('ProductTaxCode' == $field) {
                $list['ProductTaxCode'] = 2038711;
                continue;
            }
            if (in_array($field, ['color', 'clothingSize'])) {
                $color = $clothingSize = '';
                $sku_attributes = json_decode($row['sku_attributes'], true);
                $temps = GoodsHelp::getAttrbuteInfoBySkuAttributes($sku_attributes, $row['id']);
                foreach ($temps as $temp) {
                    if ($temp['id'] == 1) {
                        $color = $temp['value'];
                    } elseif (strpos($temp['name'], '尺码') !== false) {
                        $clothingSize = $temp['value'];
                    }
                }
                $list['color'] = $color;
                $list['clothingSize'] = $clothingSize;
                continue;
            }
            if ('length' == $field) {
                $list['length'] = $row['length'] / 10;
                continue;
            }

            if ('width' == $field) {
                $list['width'] = $row['width'] / 10;
                continue;
            }

            if ('height' == $field) {
                $list['height'] = $row['height'] / 10;
                continue;
            }

            if ('cost_price' == $field) {
                $list['cost_price'] = $row['cost_price'];
                continue;
            }

            if ('retail_price' == $field) {
                $list['retail_price'] = $row['retail_price'];
                continue;
            }

            if ('sku' == $field) {
                $list['sku'] = $row['sku'];
                $list['sku'] = $this->charset($list['sku']);
                continue;
            }

            if ('sku_alias' == $field) {
                $list['sku_alias'] = $this->getAlias($row['sku_id']);
                $list['sku_alias'] = $this->charset($list['sku_alias']);
                continue;
            }

            if (isset($row[$field])) {
                $list[$field] = $row[$field];
            }

        }

        return $list;
    }

    public const ZIP_LEN = 1000;//几行数据以上使用队列下载

    /**
     * @title 注释..
     * @param $params
     * @param $categoryName
     * @return string
     * @author starzhan <397041849@qq.com>
     */
    public function paramsConditionFileName($params, $categoryName = null)
    {

        $goods = new Goods();
        if (!$categoryName) {
            $categoryName = $goods->getCategoryAttr('', ['category_id' => $params['category_id']]);
        }
        $name = [];
        $GoodsHelp = new GoodsHelp();
        if (isset($params['status']) && $params['status']) {
            $statusTxt = $GoodsHelp->sales_status[$params['status']] ?? '';
            if ($statusTxt) {
                $name[] = $statusTxt;
            }
        }
        if (isset($params['category_id']) && $params['category_id']) {
            $name[] = $categoryName;
        }

        if (isset($params['without_img']) && $params['without_img']) {
            $name[] = "无图";
        }

        if (isset($params['supplier_id']) && $params['supplier_id']) {
            $supplier = $this->getSupplierName($params['supplier_id']);
            $name[] = $supplier . "供应商";
        }

        if (isset($params['developer_id']) && $params['developer_id']) {
            $developer = $goods->getDeveloperAttr(null, ['developer_id' => $params['developer_id']]);
            $name[] = $developer . "开发员";
        }

        if (isset($params['purchaser_id']) && $params['purchaser_id']) {
            $purchaser = $goods->getPurchaserAttr(null, ['purchaser_id' => $params['purchaser_id']]);
            $name[] = $purchaser . "采购员";
        }

        return implode('_', $name);
    }

    public function getExportFileName($param, $categoryName, $user_name)
    {
        $name = $this->paramsConditionFileName($param, $categoryName);
        $fileName = $name . date('Y-m-d_H-i') . "($user_name)";
        $downFileName = $fileName . '.csv';
        $file = ROOT_PATH . 'public' . DS . 'download' . DS . 'goods';
        $filePath = $file . DS . $downFileName;
        //无文件夹，创建文件夹
        if (!is_dir($file) && !mkdir($file, 0777, true)) {
            @unlink($filePath);
            throw new Exception('创建文件夹失败');
        }
        return $downFileName;
    }

    public function getExportCount($params)
    {
        $GoodsHelp = new GoodsHelp();
        $wheres = $this->getExportWhere($params);
        $Goods = new Goods();
        $GoodsSku = new GoodsSku();
        $sqlCount = "select count(*) as num from {$GoodsSku->getTable()} gs left join {$Goods->getTable()} as g on g.id = gs.goods_id where " . $wheres['where'];
        $countData = Db::query($sqlCount);
        $countData = reset($countData);
        $count = $countData['num'] ?? 0;
        return $count;
    }

    private function charset($value, $no = false)
    {
        $value = str_replace('\"', '"', $value);
        return $value;
        //return mb_convert_encoding($value, "GBK", "UTF-8");

        // return iconv($value,'UTF-8','GBK');
    }

    public function runThumb()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $Goods = new Goods();
        $count = $Goods->where('thumb', '')->count();
        $pageSize = 100;
        $totalPage = ceil($count / $pageSize);
        $page = 1;
        do {
            $Goods = new Goods();
            $aGoods = $Goods->where('thumb', '')->field('id')->page($page, $pageSize)->select();
            foreach ($aGoods as $goodsInfo) {
                $goodsId = $goodsInfo['id'];
                $GoodsGallery = new GoodsGallery();
                $aThumb = $GoodsGallery->field('path')
                    ->where('goods_id', $goodsId)
                    ->where('is_default', 1)
                    ->order('sku_id', 'asc')
                    ->select();
                if ($aThumb) {
                    $goodsInfo->thumb = $aThumb[0]['path'];
                    $goodsInfo->save();
                }
            }
            $page++;
        } while ($page <= $totalPage);

    }

    public function updatePlatform()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $Goods = new Goods();
        $count = $Goods->where('platform', 0)->count();
        $pageSize = 100;
        $totalPage = ceil($count / $pageSize);
        $page = 1;
        $map = [
            0 => 1,
            1 => 1,
            2 => 0,
            3 => 0
        ];
        $i = 0;
        $j = 0;
        do {
            $Goods = new Goods();
            $aGoods = $Goods->field('*')->where('platform', 0)->page($page, $pageSize)->select();
            foreach ($aGoods as $goodsInfo) {
                $num = '';
                $platform_sale = json_decode($goodsInfo['platform_sale'], true);
                if (isset($platform_sale['ebay'])) {
                    $tmp = $map[$platform_sale['ebay']] ?? 0;
                    $num .= $tmp;
                } else {
                    $num .= '0';
                }
                if (isset($platform_sale['amazon'])) {
                    $tmp = $map[$platform_sale['amazon']] ?? 0;
                    $num .= $tmp;
                } else {
                    $num .= '0';
                }
                if (isset($platform_sale['wish'])) {
                    $tmp = $map[$platform_sale['wish']] ?? 0;
                    $num .= $tmp;
                } else {
                    $num .= '0';
                }
                if (isset($platform_sale['aliExpress'])) {
                    $tmp = $map[$platform_sale['aliExpress']] ?? 0;
                    $num .= $tmp;
                } else {
                    $num .= '0';
                }
                if (isset($platform_sale['joom'])) {
                    $tmp = $map[$platform_sale['joom']] ?? 0;
                    $num .= $tmp;
                } else {
                    $num .= '0';
                }
                $result = bindec($num);
                if ($result) {
                    $goodsInfo->platform = bindec($num);
                    $goodsInfo->save();
                    $i++;
                } else {
                    echo $goodsInfo['id'] . "=>" . $goodsInfo['platform_sale'] . "<br>";
                    $j++;
                }
            }
            $page++;
        } while ($page <= $totalPage);
        halt('执行完毕!执行了' . $i . "条,没改{$j}条");
    }

    public function updateAlias($pid = 1)
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $GoodsSku = new GoodsSku();
        $count = $GoodsSku->count();
        $pageSize = 100;
        $totalPage = ceil($count / $pageSize);
        $suc = 0;
        $page = 1;
        do {
            $GoodsSku = new GoodsSku();
            $aGoodsSku = $GoodsSku->field('id,sku')->page($page, $pageSize)->select();
            foreach ($aGoodsSku as $skuInfo) {
                if ($skuInfo['id'] % 4 != $pid) {
                    continue;
                }
                $ser = new ServiceGoodsSkuAlias();
                $flag = $ser->insert($skuInfo['id'], $skuInfo['sku'], $skuInfo['sku'], 1);
                if ($flag) {
                    $suc++;
                }
            }
            $page++;
        } while ($page <= $totalPage);
        echo "成功执行了" . $suc . "条记录";
        exit;
    }


}

