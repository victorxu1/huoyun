<?php
namespace app\api\validate;

use think\Validate;

class Partner extends Validate
{
    protected $rule = [
        'mobile'           => 'number|length:11|unique:partner',
    ];

    protected $message = [
        'mobile.number'            => '手机号格式错误',
        'mobile.length'            => '手机号长度错误',
        'mobile.unique'          => '手机号已存在'
    ];
}