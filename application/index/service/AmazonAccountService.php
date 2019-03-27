<?php

namespace app\index\service;

use app\common\cache\Cache;
use app\common\exception\JsonErrorException;
use app\common\model\amazon\AmazonAccount as AmazonAccountModel;
use app\common\service\ChannelAccountConst;
use app\common\service\Common as CommonService;
use think\Db;
use think\Exception;

/**
 * @desc 亚马逊账号管理
 * @author wangwei
 * @date 2018-9-29 15:46:15
 */
class AmazonAccountService
{
    /**
     * @var 站点市场划分
     */
    public static $siteMap = [
        //北美
        'CA' => 'NA',
        'US' => 'NA',
        'MX' => 'NA',
        //欧洲
        'DE' => 'CO',
        'ES' => 'CO',
        'FR' => 'CO',
        'IN' => 'CO',
        'IT' => 'CO',
        'UK' => 'CO',
        //远东
        'JP' => 'JP',
        //中国
        'CN' => 'CN',
        //澳洲
        'AU' => 'AU',
    ];

    /**
     * @desc 获取指定站点的开发者授权信息
     * @author wangwei
     * @date 2018-9-29 16:19:24
     * @param string $site
     * @return array|array|string[]
     */
    public function getDeveloperAccount($site)
    {
        $return = [];
        $site = strtoupper($site);
        $developer = [
            //北美
            'NA' => [
                'name' => 'Sirmoon',
                'id' => '7193-4483-7966',
                'access_key_id' => 'AKIAJZMSTTITQPLAYEIQ',
                'secret_key' => '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',
            ],
            //欧洲
            'CO' => [
                'name' => 'Lanlary',
                'id' => '2608-9180-2986',
                'access_key_id' => 'AKIAJ7QZVI3XHTGJ4V6A',
                'secret_key' => '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',
            ],
            //日本
            'JP' => [
                'name' => 'anhuiwuzhixunxinxikeji',
                'id' => '7240-7784-6314',
                'access_key_id' => 'AKIAIRDIRP5BIKIZFJEA',
                'secret_key' => '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',
            ],
        ];
        if (!isset(self::$siteMap[$site])) {
            return $return;
        }
        $market = self::$siteMap[$site];
        return isset($developer[$market]) ? $developer[$market] : [];
    }

    public function sava($data, $user_id)
    {
        $ret = [
            'msg' => '',
            'code' => ''
        ];
        $amazonAccount = new AmazonAccountModel();
        $re = $amazonAccount->where(['code' => trim($data['code'])])->find();
        if ($re) {
            $ret['msg'] = '账户名重复';
            $ret['code'] = 400;
            return $ret;
        }
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_amazon, $data['code'], $data['site']);

        //启动事务
        Db::startTrans();
        try {
            $data['create_time'] = time();
            //获取操作人信息

            /** warning: 重构时记得传created_user_id linpeng 2019-2-19*/
            if (!param($data, 'created_user_id')) {
                $data['created_user_id'] = $user_id;
            }
            $amazonAccount->allowField(true)->isUpdate(false)->save($data);


            //开通wish服务时，新增一条list数据，如果存在，则不加
            if (isset($data['download_health'])) {
                (new AmazonAccountHealthService())->openAmazonHealth($amazonAccount->id, $data['download_health']);
            }

            Db::commit();
            //新增缓存
            Cache::store('AmazonAccount')->setTableRecord($amazonAccount->id);
            $ret = [
                'msg' => '新增成功',
                'code' => 200,
                'id' => $amazonAccount->id
            ];
            return $ret;
        } catch (\Exception $e) {
            Db::rollback();
            $ret = [
                'msg' => '新增失败',
                'code' => 500
            ];
            return $ret;
        }
    }

    public function reAge($id)
    {
        $amazonAccount = new AmazonAccountModel();
        $where['id'] = $id;

        $temp['assessment_of_usage'] = 0;
        $temp['updated_time'] = time();
        $amazonAccount->where($where)->update($temp);//修改账号表把assessment_of_usage 设置为0开启
        ///修改缓存
        $data['assessment_of_usage'] = 0;
        $data['updated_time'] = time();
        $data['id'] = $id;
        $cache = Cache::store('AmazonAccount');
        foreach ($data as $key => $val) {
            $cache->updateTableRecord($id, $key, $val);
        }
        return true;
    }

}
