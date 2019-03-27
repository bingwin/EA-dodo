<?php


namespace app\common\validate;


use think\Validate;

class GoodsTortDescription extends Validate
{

    protected $rule = [
        'id'=>'require',
        'goods_id'=>'require',
        'channel_id'=>'require',
        'account_id'=>'require',
        'site_code'=>'require',
        'remark'=>'require',
        'tort_time'=>'require',
        'create_id'=>'require',
        'create_time'=>'require'
    ];
    protected $message = [
        'id.require'=>'id不能为空',
        'id.number'=>'id须为整形',
        'goods_id.require'=>'goods_id不能为空',
        'goods_id.number'=>'goods_id须为整形',
        'channel_id.require'=>'平台不能为空',
        'channel_id.number'=>'平台须为整形',
        'account_id.require'=>'帐号不能为空',
        'account_id.number'=>'帐号须为整形',
        'site_code.require'=>'站点不能为空',
        'remark.require'=>'侵权描述不能为空',
        'tort_time.require'=>'侵权时间不能为空',
        'tort_time.number'=>'侵权时间须为整形',
        'create_id.require'=>'创建人不能为空',
        'create_id.number'=>'创建人须为整形',
        'create_time.require'=>'创建时间不能为空',
        'create_time.number'=>'创建时间须为整形',
    ];

    protected $scene = [
        'insert'=>[
            'channel_id'=>'require|number',
            'account_id'=>'require|number',
//            'site_code'=>'require',
            'remark'=>'require',
            'tort_time'=>'require|number',
            'create_time'=>'require|number',
        ],
        'edit'=>[
            'id'=>'require|number',
            'channel_id'=>'number',
            'account_id'=>'number',
            'tort_time'=>'number'
        ]
    ];
}