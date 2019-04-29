<?php
namespace app\api\validate;

use think\Validate;

class Safe extends Validate
{
	protected $rule = [
			"id"=>"require",
			"goods_type"=>"require",
			"is_attach"=>"require",
			"rate"=>"require",
			"goods_name"=>"require",
			"goods_num"=>"require",
			"danwei"=>"require",
			"safemoney"=>"require",
			"safefee"=>"require",
			"start_place"=>"require",
			"end_place"=>"require",
			"start_time"=>"require",
			"car_no"=>"require",
			"card_type"=>"require",
			"card_no"=>"require",
			"linkman"=>"require",
			"linktel"=>"require|number|length:11",
			"linkaddress"=>"require",
			"bank_name"=>"require",
			"bank_no"=>"require",
			"is_same"=>"require",
			"ctime"=>"require",
	];
	
	protected $message = [
			'linktel.number'            => '手机号格式错误',
			'linktel.length'            => '手机号长度错误',
	];
}