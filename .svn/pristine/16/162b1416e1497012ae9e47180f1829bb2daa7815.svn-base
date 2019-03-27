<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/20
 * Time: 17:48
 */

namespace app\common\validate;

use think\Validate;

class WorldfirstValidate extends Validate
{

    protected $rule = [
        'ip_name' => 'require|max:100',
        'ip_address' => 'require|max:100',
        'wf_account' => 'require|max:50',
        'wf_password' => 'require|max:50',
        'operator_id' => 'require|number',
        'status' => 'in:0,1',
        'encrypted_answers' => 'max:100',
    ];

    protected $message = [
        'id.require' => 'ID必须',
        'id.number' => 'ID必须为数值',
        'ip_name.require' => '服务器名必须',
        'ip_name.max' => '服务器名不能超过100个字符',
        'ip_address.require' => '服务器地址必须',
        'ip_address.max' => '服务器地址不能超过100个字符',
        'wf_account.require' => '登陆邮箱必须',
        'wf_account.max' => '登陆邮箱不能超过50个字符',
        'wf_password.require' => '登陆密码必须',
        'wf_password.max' => '登陆密码不能超过50个字符',
        'operator_id.require' => '操作人必须',
        'operator_id.number' => '操作人必须为数值',
        'status.in' => '系统状态取值错误',
    ];


    protected $scene = [
        'add'   =>  ['ip_name','ip_address','wf_account','wf_password','operator_id','status','encrypted_answers'],
        'edit'  =>  ['ip_name','ip_address','wf_account','operator_id','status','encrypted_answers'],
    ];
}