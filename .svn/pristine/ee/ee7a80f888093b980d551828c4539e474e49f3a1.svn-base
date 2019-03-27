<?php
namespace app\common\validate;

use \think\Validate;
/**
 * Created by PhpStorm.
 * User: XPDN
 * Date: 2016/10/28
 * Time: 9:57
 */
class PaypalAccount extends  Validate
{
    protected $rule = [        
        ['account_name','require','账号名称不能为空！'],
        ['api_user_name','require','API用户名不能为空'],
        ['api_secret','require','API密码不能为空'],
        ['api_signature','require','API签名不能为空'],
    ];
    

}