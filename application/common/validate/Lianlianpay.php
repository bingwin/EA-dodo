<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/21
 * Time: 17:12
 */

namespace app\common\validate;


use think\Validate;

class Lianlianpay extends Validate
{
    protected $rule = [
        'ip_name' => 'require|max:100',
        'ip_address' => 'require|max:100',
        'channel_id' => 'require|number',
        'account_id' => 'require|number',
        'site_code' => 'require',
        'lianlian_account' => 'require|max:50|number',
        'lianlian_name' => 'require|max:100',
        'operator_id' => 'require|number',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'ip_name.require' => '服务器名必须',
        'ip_name.max'     => '服务器名最多不能超过100个字符',
        'ip_address.number'  => '服务器地址必须',
        'ip_address.max'  => '服务器地址最多不能超过100个字符',
        'channel_id.require'  => '平台必须',
        'channel_id.number'   => '平台数据不合法',
        'account_id.require'  => '账号必须',
        'account_id.number'  => '账号数据不合法',
        'site_code'  => '账号数据不合法',
        'lianlian_account.require'  => '收款账号必须',
        'lianlian_account.max'  => '收款账号最多不能超过50个字符',
        'lianlian_account.number'  => '收款账号必须为数值',
        'lianlian_name.require'  => '收款名称必须',
        'lianlian_name.max'  => '收款名称最多不能超过100个字符',
        'operator_id.require'  => '操作人必须',
        'operator_id.number'  => '操作人必须为数值',
        'status'  => '系统状态不合法',
    ];


    protected $scene = [
        'add'   =>  ['ip_name','ip_address','channel_id','account_id','site_code','lianlian_account','lianlian_name','operator_id','status'],
        'edit'  =>  ['ip_name','ip_address','channel_id','account_id','site_code','lianlian_account','lianlian_name','operator_id','status'],
    ];
}