<?php

/**
 * Description of CommonService
 * @datetime 2017-6-14  17:26:15
 * @author joy
 */

namespace app\publish\service;

use app\common\exception\JsonErrorException;
use app\common\cache\Cache;
use app\common\model\aliexpress\AliexpressProduct;
use app\common\model\aliexpress\AliexpressProductSku;
use app\common\model\pandao\PandaoProduct;
use app\common\model\pandao\PandaoVariant;
use app\common\model\wish\WishWaitUploadProduct;
use app\common\model\wish\WishWaitUploadProductVariant;
use think\Db;
use think\Exception;
use think\exception\PDOException;

class CommonService {
    /**
     * 获取本身和所有子分类
     * @param $category_id
     */
    public static function getSelfAndChilds($category_id)
    {
        $categorys=[];
        $category_list = Cache::store('category')->getCategoryTree();
        array_push($categorys,$category_id);
        $childs = $category_list[$category_id]['child_ids'];

        if($childs)
        {
            foreach ($childs as $child)
            {
                array_push($categorys,$child);
            }
        }
        return $categorys;
    }
    /**
     * 生成sku编码
     * @param type $sku
     * @param type $separator
     * @param type $len
     * @param type $charlist
     * @return string
     */
    public  function create_sku_code($sku,$separator='|',$len=20,$charlist='0-9')
    {      
        $left = $len - strlen($sku.$separator);      
        $sku_code = $sku.$separator.\Nette\Utils\Random::generate($left, $charlist);     
        return $sku_code;      
    }
    /**
     * 生成随机sku编码
     * @return type
     */
    public  function create_random_sku_code()
    {
        $charlist='0-9a-zA-Z|_';
        $sku_cod = \Nette\Utils\Random::generate(20, $charlist);
        return $sku_cod;
    }
    /**
     * 生成捆绑商品sku
     * @param type $data
     * @param type $length
     * @param type $charlist
     * @return string
     */
    public  function create_sku_code_with_quantity($data,$length=20,$charlist='0-9')
    {
        $arr = explode('|', $data);
        $sku_code='';
        foreach ($arr as $k => $v) 
        {
            list($sku,$quantity)= explode('*', $v);
            if(strlen($sku_code)<$length)
                $sku_code = '_'.$sku.$sku_code;
        }
        $sku_code = substr($sku_code, 1);
        if(strlen($sku_code)<$length)
        {
            $sku_code = $sku_code.'|';
        }
        
        $left = $length  - strlen($sku_code); //剩余长度
        
        if($left>0)
        {
           $sku_code = $sku_code.\Nette\Utils\Random::generate($left, $charlist);
        }
        return $sku_code;
    }
    public static function replaceDesriptionHtmlTags($description)
    {
        if(empty($description))
        {
            return $description;
        }
        $description = str_replace('<br>', "\n", $description);
        $description = str_replace('<br />', "\n", $description);
        $description = str_replace('&nbsp;', " ", $description);

        return $description;
    }

    /**
     * @param $images 图片数据源
     * @param  $source图片来源
     * @param $source
     */
    public function saveImages(&$images,$source)
    {
        try {
            // 保存图片
            $return=[];
            foreach($images as $k=>$image)
            {
                $return[$k]= $this->handleNetImage($image);
            }
            return $return;
        } catch (JsonErrorException $exp) {
            throw new JsonErrorException($exp->getMessage());
        }
    }

    public function uploadImageAndSave($image,$source)
    {
        switch ($source)
        {
            case 1:
                if (isset($image['name'])) {
                    //list($name, $ext) = explode('.', $image['name']);
                    $name = pathinfo($image['name'],PATHINFO_FILENAME);
                    $tmp = explode('.', $image['name']);
                    $ext = strtolower(end($tmp));
                } else {
                    $name = uniqid();
                    $ext = 'jpg';
                }
                $filename = $this->savePic($image['image'], $name, $ext);
                break;
            case 2:
                $name = uniqid();
                $ext = 'jpg';
                $filename = $this->saveNetPic($image, $name, $ext);
                break;
            default:
                break;
        }
        return $filename;
    }

    public function saveNetImages($images)
    {
        try {
            // 保存图片
            $return=[];
            foreach($images as $k=>$image)
            {
                $return[$k]= $this->handleNetImage($image);
            }
            return $return;
        } catch (JsonErrorException $exp) {
            throw new JsonErrorException($exp->getMessage());
        }
    }
    /**
     * 处理图片
     * @param string $spu
     * @param int $goods_id
     * @param array $image
     */
    private function handleNetImage(&$image)
    {

        $name = uniqid();
        $ext = 'jpg';
        $filename = $this->saveNetPic($image, $name, $ext);
        return $filename;
    }
    /**
     * 保存图片
     * @param string $spu
     * @param stirng $image
     * @param stirng $name
     * @param string $ext
     * @return string
     * @throws Exception
     */
    private function saveNetPic($image, $name, $ext)
    {
        $base_path = ROOT_PATH.'/public/upload';

        $dir = date('Y-m-d',time());

        if (!is_dir($base_path . '/' . $dir) && !mkdir($base_path . '/' . $dir, 0777, true)) {
            throw new Exception('目录创建不成功');
        }

        if (!in_array($ext, ['jpg', 'gif', 'png', 'jpeg'])) {
            throw new Exception('图片格式不对');
        }

        $data = file_get_contents($image);

        if (strpos('<?php', $data)) {
            throw new Exception('上传内容有敏感信息');
        }
        $fileName = $name . '.' . $ext;
        file_put_contents($base_path . '/' . $dir . '/' . $fileName, $data);
        //$this->thumb($base_path . '/' . $dir . '/' . $fileName, 100, 100);
        return $dir . '/' .$fileName;
    }
    public function saveLocalImages(&$images)
    {
        try {
            // 保存图片
            $return=[];
            foreach($images as $k=>$image)
            {
                $return[$k]= $this->handle($image);
            }
            return $return;
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }
    /**
     * 处理图片
     * @param string $spu
     * @param int $goods_id
     * @param array $image
     */
    private function handle(&$image)
    {

        $name = uniqid();
        $ext = 'jpg';
        $filename = $this->savePic($image, $name, $ext);
        return $filename;
    }

    private function savePic($image, $name, $ext)
    {
        $base_path = ROOT_PATH.'/public/upload';

        $dir = date('Ymd',time());

        if (!is_dir($base_path . '/' . $dir) && !mkdir($base_path . '/' . $dir, 0777, true)) {
            throw new JsonErrorException('目录创建不成功,请联系服务器管理员');
        }

        if (!in_array($ext, ['jpg', 'gif', 'png', 'jpeg'])) {
            throw new JsonErrorException('图片格式不对');
        }
        $start=strpos($image,',');
        $img= substr($image,$start+1);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        if (strpos('<?php', $data)) {
            throw new JsonErrorException('上传内容有敏感信息');
        }
        $fileName = $name . '.' . $ext;
        file_put_contents($base_path . '/' . $dir . '/' . $fileName, $data);
        //$this->thumb($base_path . '/' . $dir . '/' . $fileName, 100, 100);
        return $dir . '/' .$fileName;
    }

    /**
     * 获取图片上传的相对路径
     * @return string
     */
    public static function getUploadPath()
    {
        return Cache::store('configParams')->getConfig('api_ip')['value'].DS.'upload'.DS;
    }

    public static function updateListingSellStatus($channel_id,$params)
    {
        if(is_array($params)){
            $params = $params;
        }elseif (is_json($params) || is_string($params)){
            $params = json_decode($params,true);
        }

        if(isset($params['type']) && isset($params['id']) && isset($params['status']))
        {
            switch ($channel_id)
            {
                case 3:
                    self::updateWishListingSellStatus($params);
                    break;
                case 4:
                    self::updateAliexpressListingSellStatus($params);
                    break;
                case 8:
                    self::updatePandaoListingSellStatus($params);
                    break;
                default:
                    break;
            }
        }else{
            throw new Exception("数据格式错误");
        }
    }
    public static function updatePandaoListingSellStatus($params){
        $id = $params['id'];$type=$params['type'];$status=$params['status'];
        Db::startTrans();
        try{
            if($type==1)
            {
                PandaoProduct::where('goods_id',$id)->setField('spu_status',$status);
            }elseif($type==2){
                PandaoVariant::where('sku_id',$id)->setField('sell_status',$status);
            }
            Db::commit();
        }catch (PDOException $exp){
            Db::rollback();
            throw new Exception($exp->getMessage());
        }
    }

    public static function updateWishListingSellStatus($params)
    {
        $id = $params['id'];$type=$params['type'];$status=$params['status'];
        Db::startTrans();
        try{
            if($type==1)
            {
                WishWaitUploadProduct::where('goods_id',$id)->setField('spu_status',$status);
            }elseif($type==2){
                WishWaitUploadProductVariant::where('sku_id',$id)->setField('sell_status',$status);
            }
            Db::commit();
        }catch (PDOException $exp){
            Db::rollback();
            throw new Exception($exp->getMessage());
        }
    }
    public static function updateAliexpressListingSellStatus($params)
    {
        $id = $params['id'];$type=$params['type'];$status=$params['status'];
        Db::startTrans();
        try{
            if($type==1)
            {
                AliexpressProduct::where('goods_id',$id)->setField('spu_status',$status);
            }elseif($type==2){
                AliexpressProductSku::where('goods_sku_id',$id)->setField('sell_status',$status);
            }
            Db::commit();
        }catch (PDOException $exp){
            Db::rollback();
            throw new Exception($exp->getMessage());
        }
    }
}
