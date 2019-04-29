<?php
namespace app\api\controller;

use app\common\model\Member as MemberModel;
use app\common\model\Huoyuan as HuoyuanModel;
use app\common\model\Partner as PartnerModel;
use think\Controller;
use think\Db;
use app\common\controller\ApiBase;
use app\common\model\UserBank as UserBankModel;
use app\common\model\Feedback as FeedbackModel;
use app\common\model\BankList as BankListModel;
use app\common\model\Carlines as CarlinesModel;
use app\common\model\User as UserModel;
use app\common\model\SafeInfo as SafeInfoModel;
use app\common\model\Safe as SafeModel;
use app\common\model\CarList as CarListModel;
use app\common\model\CarGroup as CarGroupModel;

/**
 * 用户相关接口
 * Class Upload
 * @package app\api\controller
 */
class User extends ApiBase
{
    protected $member_model;
    protected $huoyuan_model;
    protected $partner_model;
    protected $userBank_model;
    protected $feedback_model;
    protected $banklist_model;
    protected $carlines_model;
    protected $user_model;
    protected $driverimg;
    protected $safeinfo_model;
    protected $safe_model;
    protected $car_list_model;
    protected $car_group_model;
    protected function _initialize()
    {
        parent::_initialize();
        $this->car_group_model = new CarGroupModel();
        $this->safe_model = new SafeModel();
        $this->car_list_model = new CarListModel();
        $this->safeinfo_model = new SafeInfoModel();
        $this->member_model = new MemberModel();
        $this->huoyuan_model = new HuoyuanModel();
        $this->partner_model = new PartnerModel();
        $this->userBank_model = new UserBankModel();
        $this->feedback_model = new FeedbackModel();
        $this->banklist_model = new BankListModel();
        $this->carlines_model = new CarlinesModel();
        $this->user_model = new UserModel();
        
        $action = $this->request->action();
        $tokens = input('tokens');
//        if (!in_array($action,array('login','registor','build_yzm1','build_yzm','find_password')) && (!$tokens || !$this->member_model->check_tokens($tokens))) {
//            $result = [
//                'error'   => 1,
//                'success_msg' => '请先登录'
//            ];
//            die(json_encode($result,JSON_UNESCAPED_UNICODE));
//        }
    }



    //用户登录
    public function login(){
        if($this->request->isPost()){
            $mobile   = input('mobile');
            $password = input('password');
            if(!$mobile || !$password){
                $result['error'] = 1;
                $result['error_msg'] = '用户名和密码不能为空';
            }else{
                $uinfo = $this->member_model->ulogin($mobile,$password);
                if($uinfo == -1){
                    $result['error'] = 2;
                    $result['success_msg'] = '用户名或密码有误';
                }elseif($uinfo == -2){
                    $result['error'] = 3;
                    $result['success_msg'] = '用户登录状态更新失败';
                }else{
                    if(in_array($uinfo['status'],[0,1])){
                        $result['error'] = 0;
                        if($uinfo['types']==1 || $uinfo['types']==3){
                            $result['success_msg'] = '司机登录成功';
                        }elseif($uinfo['types']==2){
                            $result['success_msg'] = '货主登录成功';
                        }else{
                            $result['success_msg'] = '用户登录成功';
                        }
                        $result['data'] = $uinfo;
                    }else{
                        $result['error'] = 3;
                        $result['success_msg'] = '您已被禁用';
                    }
                }
            }
        }else{
            $result['error'] = 4;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    
    //判断用户是否在接单大厅
    public function isOnline(){
        if(!request()->isPost()){
            exit(json_encode(['error'=>1,'success_msg'=>'请求方式错误!']));
        }
        $uid = input('uid');
        $is_online = input('is_online');
        
        $re = $this->member_model->setOnlineStatus($uid,$is_online);
        
        if($re === false){
            $result['error'] = 5;
            $result['success_msg'] = 'faild';
        }else{
            $result['error'] = 0;
            $result['success_msg'] = 'ok';
        }
        return json($result);
    }
    
    //注册接口
    public function registor(){
        if($this->request->isPost()){
            $mobile = input('mobile');
            $password = input('password');
            $codes = input('codes');
            if(!trim($mobile) || !trim($password) || !trim($codes)){
                $result['error'] = 2;
                $result['success_msg'] = '请信息输入完整';
            }else{
                if($this->check_code($mobile,$codes,1) == 1){
                    $mobile_already = $this->member_model->is_phone_already($mobile);
                    if(!$mobile_already){
                        $reg_result = $this->member_model->uregisotr($mobile,$password);
                        if(!$reg_result){
                            $result['error'] = 5;
                            $result['success_msg'] = '用户注册失败';
                        }else{
                            $result['error'] = 0;
                            $result['success_msg'] = '用户注册成功';
                            $result['data'] = $reg_result;
                        }
                    }else{
                        $result['error'] = 7;
                        $result['success_msg'] = '手机号已被占用';
                    }
                }elseif($this->check_code($mobile,$codes,1) == 2){
                    $result['error'] = 3;
                    $result['success_msg'] = '输入验证码有误';
                }elseif($this->check_code($mobile,$codes,1) == 3){
                    $result['error'] = 4;
                    $result['success_msg'] = '验证码超时';
                }
            }
        }else{
            $result['error'] = 6;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //获取货主数据
    public function getHuozhuInfo(){
         if(!request()->isPost())exit(json(['error'=>0,'success_msg'=>'请求方式错误']));
        
        $uid = input('uid');
        
        $info = Db::name('user')->alias('a')
                    ->join('company b','a.id = b.uid','left')
                    ->field('a.realname,a.idcard,b.id as company_id,b.business,b.faren_wtshu,hetong_img')
                    ->where(['a.uid'=>$uid])
                    ->find();
        if($lists !== false){
            return json(['error'=>0,'success_msg'=>$info]);
        }else{
            return json(['error'=>1,'success_msg'=>'获取数据错误']);
        }
    }
    
    //货主注册下一步信息编辑
    public function save_info(){
        if(!request()->isPost()){return json(['error'=>1,'success_msg'=>'请求类型错误']);}
        
            if(!input('uid') || !input('realname') || !input('idcard')){
                $result['error'] = 2;
                $result['success_msg'] = '参数缺失';
            }else{
                $data = input('');
                
                //halt($data);
                $udata['realname'] = $data['realname'];
                $udata['idcard'] = $data['idcard'];
                $udata['types'] = 2;
                
                $bussiness = isset($data['bussiness_num'])?$this->upload_arr_mobile('bussiness',$data['bussiness_num']):'';
                $faren_wtshu = isset($data['faren_wtshu_num'])?$this->upload_arr_mobile('faren_wtshu',$data['faren_wtshu_num']):'';
                if(!empty($data['company'])){
                    $comdata['company'] = $data['company'];
                }
                if(!empty($bussiness)){
                    $comdata['bussiness'] = serialize($bussiness);
                }
                if(!empty($faren_wtshu)){ 
                    $comdata['faren_wtshu'] = serialize($faren_wtshu);
                }
                
                if(!Db::name('user')->where(['id'=>$data['uid']])->count()){
                     $result['error'] = 1;
                     $result['success_msg'] = '用户不存在';
                     return json($result);
                }
                Db::startTrans();
                try{
                    Db::name('user')->where(['id'=>$data['uid']])->update($udata);

                    $info = Db::name('company')->where(['uid'=>$data['uid']])->find();
                    if(!$info){
                        $comdata['uid'] = $data['uid'];
                        $comdata['create_time'] = date('Y-m-d H:i:s');
                        Db::name('company')->insert($comdata);
                    }else{
                        Db::name('company')->where(array('uid'=>$data['uid']))->update($comdata);
                    }
                    Db::commit();
                    $result['error'] = 0;
                    $result['success_msg'] = '用户信息完善成功';
                    $result['data'] = array('uid'=>$data['uid'],'realname'=> $udata['realname']);
                }catch(Exception $e){
                    Db::rollback();
                    $result['error'] = 3;
                    $result['success_msg'] = '用户信息完善失败';
                }
            }
      
        return json($result);
    }
    
    //获取司机数据
    public function getDriverInfo(){
        if(!request()->isPost())exit(json(['error'=>0,'success_msg'=>'请求方式错误']));
        
        $uid = input('uid');
        
        $driver = Db::name('user')->alias('a')
                    ->join('car_list b','a.id = b.uid','left')
                    ->field('a.realname,a.idcard,b.id as car_id,b.car_no,b.car_long,b.car_weight,b.drive_card,b.run_card,b.car_bussi_card,b.zige_card,b.hetong_car,b.car_type,b.tuogua,b.car_special')
                    ->where(['a.id'=>$uid])
                    ->find();
        
        $driver['drive_card'] = unserialize($driver['drive_card']);
        $driver['run_card'] = unserialize($driver['run_card']);
        $driver['car_bussi_card'] = unserialize($driver['car_bussi_card']);
        $driver['zige_card'] = unserialize($driver['zige_card']);
        $driver['hetong_car'] = unserialize($driver['hetong_car']);
        
        if($driver !== false){
            return json(['error'=>0,'success_msg'=>$driver]);
        }else{
            return json(['error'=>1,'success_msg'=>'获取数据错误']);
        }
    }
    //司机注册下一步\
  	public function save_driverinfo()
  	{
  		if($this->request->isPost()){
                        
  			$realname = $data['realname'] = input('realname');
  			$idcard = $data['idcard'] = input('idcard');
  			$car_no = $arr['car_no'] = input('car_no');
  			$car_type = $arr['car_type'] = input('car_type');
  			$car_long = $arr['car_long'] = input('car_long');
  			$load_weight = $arr['car_weight'] = input('load_weight');
  			$uid = $arr['uid'] = input('uid');
                        
                        $driver_license = input('driver_license');
                        $driving_license = input('driving_license');
                        $operate_img = input('operate_img');
                        $yyzgz_img = input('yyzgz_img'); 
                        $hccd_hetong = input('hccd_hetong');

                        
                        if(!empty($driver_license)){
                            $driver_license_path  = $this->upload_img($driver_license);
                            $arr['drive_card'] = serialize($driver_license_path);
                        }
                        
                        if(!empty($driving_license)){
                            $driving_license_path = $this->upload_img($driving_license);
                            $arr['run_card'] = serialize($driving_license_path);
                        }
                        if(!empty($operate_img)){
                            $operate_img_path     = $this->upload_img($operate_img,'');
                            $arr['car_bussi_card'] = serialize($operate_img_path);
                        }
                        
                        if(!empty($yyzgz_img)){
                            $yyzgz_img_path       = $this->upload_img($yyzgz_img,'');
                            $arr['zige_card'] = serialize($yyzgz_img_path);
                        }
                        
                        if(!empty($hccd_hetong)){
                            $hccd_hetong_path     = $this->upload_img($hccd_hetong,'');
                            $arr['hetong_car'] = serialize($hccd_hetong_path);
                        }
  			if (!$realname && !$idcard && !$car_no && !$uid && !$driver_license && !$driving_license && !$operate_img && !$yyzgz_img){
  				return json(['error'=>1,'success_msg'=>'参数缺失']);
  			}
                        $arr['tuogua'] = input('tuogua');
          	if(!empty(input('car_special'))){
                        $arr['car_special'] = input('car_special');
          	}
                        $arr['create_time'] = time();
  			Db::startTrans();
                        
                        $re = $this->user_model->updateDriverInfo(['id'=>$uid],$data);
                        $count = Db::name('car_list')->where(['uid'=>$uid])->count();
                        if(!$count){
                            $res = $this->car_list_model->save($arr);
                        }else{
                            $car_id  = input('car_id');
                            unset($arr['create_time']);
                            unset($arr['uid']);
                            $res = $this->car_list_model->where(['uid'=>$uid])->update($arr);
                        }
                        
                        //var_dump($data,$re,$res);exit;
  			if ($re && $res !== false){
  				Db::commit();
  				return json(['error'=>0,'success_msg'=>'成功']);
  			}else {
  				Db::rollback();
  				return json(['error'=>1,'success_msg'=>'失败']);
  			}
  			
  		}else{
	  		return json(['error'=>1,'success_msg'=>'请求类型有误']);
  		}
  	}

    //批量上传图片
    protected function upload_arr($img){
        $imgdata = array();
        $is_true = 1;
        $files = request()->file($img);
        if(!$files){
            return array();
        }else{
            foreach($files as $file){
                $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
                if($info){
                    $filepath = '/public/uploads/'.str_replace('\\','/',$info->getSaveName());
                    $imgdata[] = $filepath;
                }else{
                    $is_true = 0;
                    break;
                }
            }
            if($is_true == 0){
                return false;
            }else{
                return $imgdata;
            }
        }

    }

    //上传图片app
    protected function upload_arr_mobile($name,$lens){
        //trace($_FILES,'file_arr');
        $date_name = date('Ymd');
        $dir = ROOT_PATH . 'public' . DS . 'uploads'. DS .$date_name;
        if(!is_dir($dir)){
            mkdir($dir);
        }
        $arr = array();
        for($i=1;$i<$lens+1;$i++){
            $filename1 = $name.$i;
            $filename2 = $_FILES[$filename1]['name'];
            $arr = explode('.',$filename2);
            $ext = end($arr);
            $ext = ($ext == 'jepg')?'jpg':$ext;
            //trace($_FILES[$filename1]['tmp_name'],'file_tmp');
            $savefile = creat_uniq().'.'.$ext;
            $res = move_uploaded_file($_FILES[$filename1]['tmp_name'],ROOT_PATH . 'public/uploads/'.$date_name.'/'.$savefile);
            $arr[] = "/public/uploads/".$date_name."/".$savefile;
        }
        return $arr;
    }

    //注册生成验证码
    
    public function build_yzm()
    {
    	if ($this->request->isPost()){
    		$mobile = input('mobile');
    		if (!$mobile){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$code = rand(1000,9999);
    		$templatecode = 'SMS_151490217';
    		$re = sendMsgCode($mobile, $code, $templatecode,1);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>$re]);
    		}else {
    			return json(['error'=>1,'success_msg'=>'验证码更新失败']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    //忘记密码和修改密码生成验证码
    
    public function build_yzm1()
    {
    	if ($this->request->isPost()){
    		$mobile = input('mobile');
    		if (!$mobile){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$code = rand(1000,9999);
    		$templatecode = 'SMS_151490217';
    		$re = sendMsgCode($mobile, $code, $templatecode,2);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>$re]);
    		}else {
    			return json(['error'=>0,'success_msg'=>'验证码更新失败']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }

    //检验验证码
    protected function check_code($mobile,$codes,$types){
        $info = Db::name('phcodes')->where(array('mobile'=>$mobile,'code'=>$codes,'types'=>$types))->order("id desc")->find();
        //echo Db::name('phcodes')->getLastSql();exit;
       $yzm_over_time = config('mobile_over_times');
        if($info){
            if((time()-$info['times'])>$yzm_over_time){
                return 3;//超时
            }else{
                return 1;
            }
        }else{
            return 2;//验证码错误
        }
    }

    //找回密码
    public function find_password(){
        if($this->request->isPost()){
            $mobile = input('mobile');
            $password = input('password');
            $codes = input('codes');
            if(!trim($mobile) || !trim($password) || !trim($codes)){
                $result['error'] = 3;
                $result['success_msg'] = '请信息输入完整';
            }else{
                if($this->check_code($mobile,$codes,2) == 1){
                    $reg_result = $this->member_model->where(array('mobile'=>$mobile))->update(array('password'=>md5($password . config('salt'))));
                    if($reg_result === false){
                        $result['error'] = 5;
                        $result['success_msg'] = '密码修改失败';
                    }else{
                        $result['error'] = 0;
                        $result['success_msg'] = '密码修改成功';
                    }
                }elseif($this->check_code($mobile,$codes,2) == 2){
                    $result['error'] = 3;
                    $result['success_msg'] = '输入验证码有误';
                }elseif($this->check_code($mobile,$codes,2) == 3){
                    $result['error'] = 4;
                    $result['success_msg'] = '验证码超时';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //修改密码
    public function edit_password(){
        if($this->request->isPost()){
            $mobile = input('mobile');
            $password = input('password');
            $uid = input('uid');
            $codes = input('codes');
            if(!trim($mobile) || !trim($password) || !trim($codes) || !$uid){
                $result['error'] = 3;
                $result['success_msg'] = '请信息输入完整';
            }else{
                if($this->check_code($mobile,$codes,2) == 1){
                    $uinfo = $this->member_model->where(array('mobile'=>$mobile,'id'=>$uid))->find();
                    if($uinfo){
                        $reg_result = $this->member_model->where(array('mobile'=>$mobile,'id'=>$uid))->update(array('password'=>md5($password . config('salt'))));
                        if($reg_result === false){
                            $result['error'] = 5;
                            $result['success_msg'] = '密码修改失败';
                        }elseif($reg_result === 0){
                            $result['error'] = 7;
                            $result['success_msg'] = '和旧密码一样无需修改';
                        }else{
                            $result['error'] = 0;
                            $result['success_msg'] = '密码修改成功';
                        }
                    }else{
                        $result['error'] = 6;
                        $result['success_msg'] = '手机号不是当前用户的手机号';
                    }

                }elseif($this->check_code($mobile,$codes,2) == 2){
                    $result['error'] = 8;
                    $result['success_msg'] = '输入验证码有误';
                }elseif($this->check_code($mobile,$codes,2) == 3){
                    $result['error'] = 4;
                    $result['success_msg'] = '验证码超时';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    
    private function getLocationinfo($placeId)
    {
    	$info = DB::name('chinacity')->where('id','=',$placeId)->field('lng,lat')->find();
    	return $info;
    }

    //货主发布货源
    public function add_huoyuan()
    {
    	if(!request()->isPost()){
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
        $company = $data['company'] = input('company');
        $realname = $data['realname'] = input('realname');

        $uid = $data['uid'] = input('uid');
        
        $user = Db::name('user')->where(['id'=>$uid])->find();
        if($user['status'] != 1){
            return json(['error'=>1,'success_msg'=>'您的账户暂不能发布货源,请联系管理员!']);
        }
        $ydsn     = $data['ydsn']    = input('ydsn'); 
        
        if(Db::name('huoyuan')->where(['uid'=>$uid,'ydsn'=>$ydsn])->count()){
            return json(['error'=>1,'success_msg'=>'不能重复提交']);
        }
        $times    = $data['times']   = input('times');
        $display  = $data['display'] = input('display');
        $is_notify  = $data['is_notify'] = input('is_notify');
        $volume  = $data['volume'] = input('volume');
        $weight  = $data['weight'] = input('weight');

        $mobile = $data['mobile'] = input('mobile');
        $goods_name = $data['goods_name'] = input('goods_name');
        $good_type = $data['good_type'] = input('good_type');
        $startads = $data['startads'] = input('startads');
        $endds = $data['endds'] = input('endds');
        $startads1 = $data['startads1'] = input('startads1');
        $endds1 = $data['endds1'] = input('endds1');
        $data['weight'] = input('weight',0);
        $goods_num = $data['goods_num'] = input('goods_num');
        $danwei = $data['danwei'] = input('danwei');
        $car_long = $data['car_long'] = input('car_long');
        $car_type = $data['car_type'] = input('car_type');
        $remark = $data['remark'] =input('remark');
        $unitprice = $data['unitprice'] = input('unitprice');
        $startadsinfo = $this->getLocationinfo($startads1);
        $enddsinfo = $this->getLocationinfo($endds1);
        $data['price'] = input('post_price',1);//;getStartingPrice()+getMailPrice()*getDistance($startadsinfo['lng'], $startadsinfo['lat'], $enddsinfo['lng'], $enddsinfo['lat']);
        try{
            $re = HuoyuanModel::addHuoyuan($data);
            if ($re !== false){
                $display = 1;
                $showds = ['1'=>20,'2'=>50,'3'=>100];
                $receive_ds = $showds[$display];//接受推送距离

                $receivers = $this->getTuiReceivers($re,$receive_ds);
                if(is_array($receivers)){
                    foreach($receivers as $rc){
                        $alias[] = 'apkpro_'.$rc;
                    }
                    //推送信息
                    @$this->jgPushOne('有新的货源发布了！',$alias);
                }
                return json(['error'=>0,'success_msg'=>'发布成功']);

            }else{
                return json(['error'=>1,'success_msg'=>'数据写入失败']);
            }
        }catch(think\Exception $e){
                return json(['error'=>1,'success_msg'=>'发布异常;'.$e->getMessage()]);
        }
    }
    
    
    //货主编辑货源
    public function edit_huoyuan()
    {
    	if(!$this->request->isPost()){
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
        
        $huoyuan_id = input('huoyuan_id');
        $uid    = input('uid');
        
        $huoyuan = $this->huoyuan_model->where(['id'=>$huoyuan_id])->find();
        
        if(!$huoyuan){
             return json(['error'=>1,'success_msg'=>'货源不存在']);
        }
        if($huoyuan['uid'] != $uid){
            return json(['error'=>1,'success_msg'=>'非发布人不能进行编辑操作']);
        }
        
        if($huoyuan['status']){
            return json(['error'=>1,'success_msg'=>'货源已被接单，不能进行编辑操作']);
        }
        
        //$company = $data['company'] = input('company');
        //$realname = $data['realname'] = input('realname');
        //$ydsn     = $data['ydsn']    = input('ydsn'); 
        //$mobile = $data['mobile'] = input('mobile');
        
        $times    = $data['times']   = input('times');
        $display  = $data['display'] = input('display');
        $is_notify  = $data['is_notify'] = input('is_notify');
        $volume  = $data['volume'] = input('volume');
        $weight  = $data['weight'] = input('weight');

        $goods_name = $data['goods_name'] = input('goods_name');
        $good_type = $data['good_type'] = input('good_type');
        $goods_num = $data['goods_num'] = input('goods_num');
        $startads = $data['startads'] = input('startads');
        $endds = $data['endds'] = input('endds');
        $startads1 = $data['startads1'] = input('startads1');
        $endds1 = $data['endds1'] = input('endds1');
        $load_weight = $data['load_weight'] = input('load_weight');
        $danwei = $data['danwei'] = input('danwei');
        $car_long = $data['car_long'] = input('car_long');
        $car_type = $data['car_type'] = input('car_type');
        $remark = $data['remark'] = input('remark');
        $unitprice = $data['unitprice'] = input('unitprice');//单价
        
        $startadsinfo = $this->getLocationinfo($startads1);
        $enddsinfo = $this->getLocationinfo($endds1);
        $data['price'] = input('price');
        
        //$data['price'] = getStartingPrice()+getMailPrice()*getDistance($startadsinfo['lng'], $startadsinfo['lat'], $enddsinfo['lng'], $enddsinfo['lat']);
        
        $re = HuoyuanModel::editHuoyuan($data,$huoyuan_id);
        if ($re !== false){
            return json(['error'=>0,'success_msg'=>'编辑成功']);
            
        }else{
            return json(['error'=>1,'success_msg'=>'发布失败']);
        }
    	
    }
    
//     public function add_huoyuan(){
//         if($this->request->isPost()){
//             $data = input();
//             if(!$data['company'] || !$data['realname'] || !$data['mobile'] || !$data['startads'] || !$data['startads1'] || !$data['endds'] || !$data['endds1']){
//                 $result['error'] = 3;
//                 $result['success_msg'] = '参数缺失';
//             }else{
//                 $data = input();
//                 $data['create_time'] = date('Y-m-d H:i:s');
//                 if ($this->huoyuan_model->allowField(true)->save($data)) {
//                     $result['error'] = 0;
//                     $result['success_msg'] = '货源添加成功';
//                 } else {
//                     $result['error'] = 4;
//                     $result['success_msg'] = '货源添加失败';
//                 }
//             }
//         }else{
//             $result['error'] = 2;
//             $result['success_msg'] = '请求类型有误';
//         }
//         return json($result);
//     }

    //实名认证
    public function user_renzhen_info(){
        if($this->request->isPost()){
            $data = input();
            if(!$data['realname'] || !$data['mobile'] || !$data['idcard'] || !$data['bank_card']){
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            }else{
                $idcard_img1 = $data['idcard_img1_num']?$this->upload_arr_mobile('idcard_img1',$data['idcard_img1_num']):'';
                $idcard_img2 = $data['idcard_img2_num']?$this->upload_arr_mobile('idcard_img2',$data['idcard_img2_num']):'';
                $idcard_img3 = $data['idcard_img3_num']?$this->upload_arr_mobile('idcard_img3',$data['idcard_img3_num']):'';
                $data['idcard_img1'] = $idcard_img1?serialize($idcard_img1):array();
                $data['idcard_img2'] = $idcard_img2?serialize($idcard_img2):array();
                $data['idcard_img3'] = $idcard_img3?serialize($idcard_img3):array();
                $data['is_auth'] = 2;
                $res = $this->member_model->allowField(true)->save($data,array('id'=>$data['uid']));
                if($res !== false){
                    $result['error'] = 0;
                    $result['success_msg'] = '认证资料提交成功';
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '认证资料提交失败';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    
    //司机距离范围 
    //货源id,接受距离
    public function getTuiReceivers($huoyuan_id,$ds){
        $huoyuan = Db::name('huoyuan')->alias('a')
                ->join('chinacity b','a.startads1 = b.id','left')
                ->field('b.lng,b.lat')
                ->where(['a.id'=>$huoyuan_id])
                ->find();
        
        if(!$huoyuan){
            return 0;
        }
        //司机列表
        $lists = Db::name('user')->field('id,lng,lat')->where(['types'=>1,'status'=>1,'is_online'=>1])->select();
        foreach($lists as $k=>$v){
            
            $distance = getDistance($huoyuan['lng'], $huoyuan['lat'], $v['lng'], $v['lat']);
            if($distance <= 20){
                 $temp[100][] = $temp[50][] = $temp[20][] = $v['id'];
            }elseif($distance > 20 && $distance <= 50){
                 $temp[100][] = $temp[50][] = $v['id'];
            }else{
                $temp[100][] = $v['id'];
            }
        }
        return isset($temp[$ds])?$temp[$ds]:0;
    }
    
    //发布招募合伙人信息
    public function add_hehuoren(){
        if($this->request->isPost()){
            $data = input();
            if(!$data['realname'] || !$data['mobile']){
                return json(['error'=>1,'success_msg'=>'参数缺失']);
            }else{
                
                if(Db::name('partner')->where(['realname'=>$data['realname'],'mobile'=>$data['mobile']])->count()){
                    return json(['error'=>1,'success_msg'=>'请勿重复提交']);
                }
                $data['create_time'] = date('Y-m-d H:i:s');
                $validate_result = $this->validate($data, 'Partner');
                if($validate_result === true){
                    $res = $this->partner_model->allowField(true)->save($data);
                    if($res !== false){
                        return json(['error'=>0,'success_msg'=>'合伙人添加成功']);
                    }else{
                        return json(['error'=>1,'success_msg'=>'合伙人添加失败']);
                    }
                }else{
                    return json(['error'=>1,'success_msg'=>$validate_result]);
                }

            }
        }else{ 
            return json(['error'=>1,'success_msg'=>'请求类型有误']);
        }
    }

    //手机号修改接口
    public function update_phone(){
        if($this->request->isPost()){
            $mobile1 = input('mobile1');
            $mobile = input('mobile');
            $codes = input('codes');
            $uid = input('uid');
            if(!$mobile1 || !$mobile || !$codes){
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            }else{
                $is_phone = $this->member_model->field('id')->where(['id'=>$uid,'mobile'=>$mobile1])->count();
                if($is_phone){
                    $validate_result = $this->validate(['mobile'=>$mobile], 'Phone');
                    if($validate_result === true){
                        if($this->check_code($mobile1,$codes,2) == 1){
                            $res = $this->member_model->allowField(true)->save(['mobile'=>$mobile],['id'=>$uid]);
                            if($res !== false){
                                $result['error'] = 0;
                                $result['success_msg'] = '手机修改成功';
                            }else{
                                $result['error'] = 8;
                                $result['success_msg'] = '手机修改失败';
                            }
                        }elseif($this->check_code($mobile1,$codes,2) == 2){
                            $result['error'] = 6;
                            $result['success_msg'] = '输入验证码有误';
                        }elseif($this->check_code($mobile1,$codes,2) == 3){
                            $result['error'] = 7;
                            $result['success_msg'] = '验证码超时';
                        }
                    }else{
                        $result['error'] = 5;
                        $result['success_msg'] = $validate_result;
                    }
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '原始手机输入有误';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //获取关于我们
    public function get_about_us(){
        if($this->request->isPost()){
            $info = Db::name('category')->field('name,content')->where(['name'=>'关于我们'])->find();
            $result['error'] = 0;
            $result['success_msg'] = '获取数据成功';
            $result['data'] =  $info['content'];
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //支付宝账号和密码绑定
    public function bd_alipay(){
        if($this->request->isPost()){
            $ali_user = input('ali_user');
            $ali_realname = input('ali_realname');
            $uid = input('uid');
            if(!$ali_user || !$ali_realname || !$uid){
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            }else{
                $res = $this->member_model->where(array('id'=>$uid))->update(['ali_realname'=>$ali_realname,'ali_user'=>$ali_user]);
                if($res !== false){
                    $result['error'] = 0;
                    $result['success_msg'] = '支付宝绑定成功';
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '支付宝绑定失败';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //获取个人资料
    public function get_user_profile(){
        if($this->request->isPost()){
            $uid = input('uid');
            if(!$uid){
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            }else{
                $info = Db::name('user')->alias('a')
                                        ->join('company b','a.id = b.uid','left')->where(array('a.id'=>$uid))
                                        ->field("a.id,a.headpath,a.realname,a.idcard,a.nickname,a.address,a.money,a.is_pay_pass,a.is_auth,a.ali_user,b.bussiness,b.faren_wtshu")
                                        ->find();
                if($info){
                	//$info = $info->toArray();
                	//$info['money'] = $info['money']/100;
                    if(!empty($info['bussiness'])){
                        $info['bussiness']   = unserialize($info['bussiness']);
                        $info['bussiness']   = array_pop($info['bussiness'] );
                    }
                    if(!empty($info['faren_wtshu'])){
                        $info['faren_wtshu'] = unserialize($info['faren_wtshu']);
                        $info['faren_wtshu']   = array_pop($info['faren_wtshu'] );
                    }
                    $result['error'] = 0;
                    $result['success_msg'] = '用户信息获取成功';
                    $result['data'] = $info;
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '用户不存在';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    

    //修改个人资料
    public function edit_profile(){
        if($this->request->isPost()){
            $uid = input('uid');
            $headpath_num = input('headpath_num');
            $headpath = $headpath_num?$this->upload_arr_mobile('headpath',$headpath_num):array();
            $nickname = input('nickname');
            $address = input('address');
            if(!$uid){
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            }else{
                if ($nickname){
                	$udata['nickname'] = $nickname;
                }
                if ($address){
                	$udata['address'] = $address;
                }
                !empty($headpath)?$udata['headpath'] = $headpath[2]:'';
                $res = $this->member_model->where(array('id'=>$uid))->update($udata);
                if($res !== false){
                    $result['error'] = 0;
                    $result['success_msg'] = '个人资料修改成功';
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '个人资料修改失败';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //获取常见问题列表
    public function get_questions(){
        if($this->request->isPost()){
            $uid = $this->request->isPost('uid');
            if(!$uid){
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            }else{
                $list = Db::table(config('database.prefix').'article')->where(['cid'=>4])->select();
                if($list){
                    $result['error'] = 0;
                    $result['success_msg'] = '获取数据成功';
                    $result['data'] = $list;
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '获取数据为空';
                }
            }

        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    
    //添加银行卡生成验证码
    
    public function build_yzm3()
    {
    	if ($this->request->isPost()){
    		$mobile = input('mobile');
    		if (!$mobile){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$code = rand(1000,9999);
    		$templatecode = 'SMS_151490217';
    		$re = sendMsgCode($mobile, $code, $templatecode,3);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>$re]);
    		}else {
    			return json(['error'=>0,'success_msg'=>'验证码更新失败']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function get_bank_list()
    {
    	if ($this->request->isPost()){
    		$info = $this->banklist_model->getBankList();
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    //添加银行卡
    public function addBankCard()
    {
    	if ($this->request->isPost()){
    		$data['uid'] = $uid = input('uid');
    		$data['name'] = $name = input('name');
    		$data['card_no'] = $card_no = input('card_no');
    		$data['bank_list_id'] = $bank_list_id = input('bank_list_id');
    		$data['address'] = $address = input('address');
    		$data['mobile'] = $mobile = input('mobile');
    		$codes = input('code');
    		if (!$uid || !$name || !$card_no || !$bank_list_id || !$address || !$mobile || !$codes){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$res = $this->check_code($mobile,$codes,3);
    		if ($res == 3){
    			return json(['error'=>5,'success_msg'=>'验证码超时']);
    		}
    		if ($res == 2){
    			return json(['error'=>4,'success_msg'=>'验证码错误']);
    		}
    		if ($res == 1){
    			$re = $this->userBank_model->addBankcard($data);
    			if ($re){
    				return json(['error'=>0,'success_msg'=>'添加成功']);
    			}else {
    				return json(['error'=>1,'success_msg'=>'添加失败']);
    			}
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function getBankcardList()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$info = $this->userBank_model->getBankcardList($uid);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function add_feedback()
    {
    	if ($this->request->isPost()){
    		$data['uid'] = $uid = input('uid');
    		$data['content'] = $content = input('content');
    		if (!$uid || !$content){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$re = $this->feedback_model->add_feedback($data);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'反馈成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'反馈失败']);
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function feedback_list()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$info = $this->feedback_model->getfeedback_list($uid);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    //添加委托人
    public function add_principal()
    {
    	if ($this->request->isPost()){
    		$data['company'] = $company = input('company');
    		$data['realname'] = $realname = input('realname');
    		$data['mobile'] = $mobile = input('mobile');
    		$data['pid'] = $uid = input('uid');
    		$bussiness = input('bussiness');
    		$faren_wtshu = input('faren_wtshu');
    		$hetong_img = input('hetong_img');
    		if (!$company || !$realname && !$mobile && !$uid && !$bussiness && !$faren_wtshu && !$hetong_img ){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$data['bussiness'] = $this->upload_img($bussiness,'');
    		$data['faren_wtshu'] = $this->upload_img($faren_wtshu,'');
    		$data['hetong_img'] = $this->upload_img($hetong_img,'');
    		$data['create_time'] = date('Y-m-d H:i:s');
    		$re = model('wetuos')->save($data);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'添加成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'添加失败']);
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型有误']);
    	}
    }
    
    
    protected function upload_img($imgbase,$savepath='')
    {
    	if (empty($savepath)){
	    	$savepath = '/public/uploads/'.date('Ymd').'/';
    	}
    	$imgbase_arr = explode("|",$imgbase);
    	foreach ($imgbase_arr as $k=>$v){
    		if (substr($v,0,10) == 'data:image'){
    			$img_urls = saveBase64Img($v,$savepath).'|';
    		}else {
    			$imgbase64 = '';
    			$imgbase64 .= 'data:image/jpg;base64,'.$v.'|';
    		}
    	}
    	$imgbase64_arr= explode('|',rtrim($imgbase64,'|'));
    	foreach ($imgbase64_arr as $k=>$v){
    		$img_urls = saveBase64Img($v,$savepath).'|';
    	}
    	return rtrim($img_urls,"|");
    }
    
    public function getPrincipalList()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$info = model('wetuos')->where(['pid'=>$uid])->field('id,company,realname,mobile')->select();
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function getPrincipalInfo()
    {
    	if ($this->request->isPost()){
    		$pid = input('pid');
    		if (!$pid){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$info = model('wetuos')->where(['id'=>$pid])->field('id,company,realname,mobile,bussiness,faren_wtshu,hetong_img')->select();
    		foreach ($info as $k=>$v){
    			if (empty(explode('|',$v['bussiness'])[1])){
    				$info[$k]['bussiness'] = 'http://'.$_SERVER['SERVER_NAME'].$v['bussiness'];
    			}else {
    				foreach ($v['bussiness'] as $kk=>$vv){
    					$info[$k]['bussiness'][] = 'http://'.$_SERVER['SERVER_NAME'].$vv;
    				}
    			}
    			if (empty(explode('|',$v['faren_wtshu'])[1])){
    				$info[$k]['faren_wtshu'] = 'http://'.$_SERVER['SERVER_NAME'].$v['faren_wtshu'];
    			}else {
    				foreach ($v['faren_wtshu'] as $kk=>$vv){
    					$info[$k]['faren_wtshu'][] = 'http://'.$_SERVER['SERVER_NAME'].$vv;
    				}
    			}
    			if (empty(explode('|',$v['hetong_img'])[1])){
    				$info[$k]['hetong_img'] = 'http://'.$_SERVER['SERVER_NAME'].$v['hetong_img'];
    			}else {
    				foreach ($v['hetong_img'] as $kk=>$vv){
    					$info[$k]['hetong_img'][] = 'http://'.$_SERVER['SERVER_NAME'].$vv;
    				}
    			}
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function save_principal()
    {
    	if ($this->request->isPost()){
    		$data['company'] = $company = input('company');
    		$data['realname'] = $realname = input('realname');
    		$data['mobile'] = $mobile = input('mobile');
    		$id = input('pid');
    		$bussiness = input('bussiness');
    		$faren_wtshu = input('faren_wtshu');
    		$hetong_img = input('hetong_img');
    		if (!$company || !$realname && !$mobile && !$id && !$bussiness && !$faren_wtshu && !$hetong_img ){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		if (substr($bussiness,0,4) != 'http'){
	    		$data['bussiness'] = $this->upload_img($bussiness,'');
    		}else {
    			$data['bussiness'] = str_replace('http://'.$_SERVER['SERVER_NAME'],'',$bussiness);
    		}
    		if (substr($faren_wtshu,0,4) != 'http'){
	    		$data['faren_wtshu'] = $this->upload_img($faren_wtshu,'');
    		}else {
    			$data['faren_wtshu'] = str_replace('http://'.$_SERVER['SERVER_NAME'],'',$faren_wtshu);
    		}
    		if (substr($hetong_img,0,4) != 'http'){
	    		$data['hetong_img'] = $this->upload_img($hetong_img,'');
    		}else {
    			$data['hetong_img'] = str_replace('http://'.$_SERVER['SERVER_NAME'],'',$hetong_img);
    		}
    		$data['create_time'] = date('Y-m-d H:i:s');
    		$re = model('wetuos')->save($data,['id'=>$id]);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'修改成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'修改失败']);
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function del_principal()
    {
    	if ($this->request->isPost()){
    		$id = input('pid');
    		if (!$id){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$re = model('wetuos')->where(['id'=>$id])->delete();
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'删除成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'删除失败']);
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型有误']);
    	}
    }
    
    //司机添加线路，货主添加模板
    public function addMyLines()
    {
    	if ($this->request->isPost()){
    		$uid = $data['uid'] = input('uid');
    		$startads = $data['startads']   = input('startads');
    		$startads1 = $data['startads1'] = input('startads1');
    		$endds = $data['endds']   = input('endds');
    		$endds1 = $data['endds1'] = input('endds1');
    		$like_city = $data['like_city'] = input('like_city');
    		$remark = $data['remark'] = input('remark');
                $data['car_type']    = input('car_type');
                $data['load_weight'] = input('load_weight');
                $data['car_long']    = input('car_long');
                
                $line_count = Db::name('carlines')->where(['startads1'=>$startads1,'endds1'=>$endds1,'uid'=>$uid])->count();
                IF($line_count){
                    RETURN json(['error'=>1,'已存在相同线路']);
                }
    		if (!$uid && !$startads && !$startads1 && !$endds && !$endds1 && !$like_city){
    			return json(['error'=>3,'参数缺失']);
    		}
    		$re = $this->carlines_model->addCarLines($data);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'添加成功']);
    		}else{
    			return json(['error'=>1,'success_msg'=>'添加失败']);
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getMyLinesList()
    {
    	if ($this->request->isPost()){
    		$uid = $data['uid'] = input('uid');
    		if (!$uid){
    			return json(['error'=>3,'参数缺失']);
    		}
    		$where = ['a.uid'=>$uid];
    		$field = 'a.id,a.startads,a.endds,a.like_city,a.remark,a.create_time';
    		$info = $this->carlines_model->getCarLinesList($where,$field);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    //线路详情
    public function getMyLinesDetail()
    {
    	if ($this->request->isPost()){
    		$linesid = $data['uid'] = input('linesid');
    		if (!$linesid){
    			return json(['error'=>3,'参数缺失']);
    		}
    		$where = ['a.id'=>$linesid];
    		$field = 'a.id,a.startads,a.endds,a.car_long,a.load_weight,a.car_type,a.like_city,a.remark,a.create_time';
    		$info = $this->carlines_model->getCarLinesList($where,$field);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function delMyLines()
    {
    	if ($this->request->isPost()){
    		$linesid = input('linesid');
    		if (!$linesid){
    			return json(['error'=>3,'参数缺失']);
    		}
    		$re = $this->carlines_model->delLines($linesid);
    		if ($re){
	    		return json(['error'=>0,'success_msg'=>'删除成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'删除失败']);
    		}
    	}else {
    		return json(['error'=>2,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getGoodsType()
    {
    	if ($this->request->isPost()){
    		$where = ['type'=>1];
    		$field = 'id,name,desc';
    		$info = $this->safeinfo_model->getSafeInfoList($where,$field);
    		return json(['error'=>0,'success_msg'=>$info]);
	    }else {
	    	return json(['error'=>1,'success_msg'=>'请求类型错误']);
	    }
    }
    
    public function getDanwei()
    {
    	if ($this->request->isPost()){
    		$where = ['type'=>2];
    		$field = 'id,name';
    		$info = $this->safeinfo_model->getSafeInfoList($where,$field);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getCardType()
    {
    	if ($this->request->isPost()){
    		$where = ['type'=>3];
    		$field = 'id,name';
    		$info = $this->safeinfo_model->getSafeInfoList($where,$field);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function addSafe()
    {
    	if ($this->request->isPost()){
    		$data = input();
    		$re = $this->safe_model->addSafe($data);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'添加成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'添加失败']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    //保单列表
    public function getSafeList(){
        if(!request()->isPost()){
            return json(['error'=>1,'success_msg'=>'请求类型错误']);
        }
        $uid = input('uid');
        $lists = Db::name('safe')->where(['uid'=>$uid])->order('id desc')->select();
        
        foreach($lists as $k=>$v){
            $lists[$k]['ctime'] = date('Y-m-d',$v['ctime']);
            $lists[$k]['start_time'] = date('Y-m-d',$v['start_time']);
        }
        
        if($lists !== false){
            return json(['error'=>0,'success_msg'=>$lists]);
        }else{
            return json(['error'=>1,'success_msg'=>'faild']);
        }
    }
    
    public function getHisSafeList()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		$page = input('page');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$page = $page?$page:1;
    		$where = [];
    		$where['a.uid'] = $uid;
    		$field = 'a.bxcompany,b.name as goods_type,a.start_place,a.end_place,a.ctime,a.safemoney,a.id';
    		$info = $this->safe_model->getSafeList($where, $field, $page);
    		if (is_array($info)){
    			foreach ($info as $k=>$v){
    				$info[$k]['safemoney'] = $v['safemoney']/100;
    				$info[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
    			}
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getSafeDetail()
    {
    	if ($this->request->isPost()){
    		$safeid = input('safeid');
    		if (!$safeid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$where = [];
    		$where['a.id'] = $safeid;
    		$field = 'a.*,b.name as goods_type,c.name as card_type,d.name as danwei';
    		$info = $this->safe_model->getSafeDetail($where, $field);
    		if (is_array($info)){
    			foreach ($info as $k=>$v){
    				$info[$k]['safemoney'] = $v['safemoney'];
    				$info[$k]['safefee'] = $v['safefee'];
    				$info[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
    				$info[$k]['start_time'] = date('Y-m-d H:i:s',$v['start_time']);
    			}
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getLocalNum()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		$localion_city = input('location_city');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$info = $this->user_model->getUserLocationCity($uid);
    		$location_city = $info['location_city'];
    		$where = ['a.uid'=>$uid,'d.location_city'=>$location_city,'a.status'=>1];
    		$group = 'b.location_city';
    		$info = $this->car_group_model->getLocalCarNum($where,$group);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getCarGruopNum()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$where = ['uid'=>$uid,'status'=>1];
    		$info = $this->car_group_model->getGroupCarNum($where);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function getLocalCityCarList()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$info = $this->user_model->getUserLocationCity($uid);
    		$location_city = $info['location_city'];
    		$where = ['a.uid'=>$uid,'d.location_city'=>$location_city,'a.status'=>1];
    		$group = 'a.id';
    		$info = $this->car_group_model->getLocalCarInfo($where, $group);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    //车队车辆列表
    public function getGroupCarList()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$where = ['a.uid'=>$uid,'a.status'=>1];
    		$group = 'a.id';
    		$info = $this->car_group_model->getLocalCarInfo($where, $group);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    //删除车队车辆
    public function delGroupCar(){
        if(!request()->isPost()){return json(['error'=>1,'success_msg'=>'请求类型错误']);}
        
        //$where['uid']    = input('uid');
        $where['id']  = input('car_id');
        
        if(empty($where['id'])){
            return json(['error'=>1,'success_msg'=>'参数缺失']);
        }
        
        $affect_row = Db::name('car_group')->where($where)->delete(true);
        if($affect_row){
            return json(['error'=>0,'success_msg'=>'删除成功']);
        }else{
            return json(['error'=>1,'success_msg'=>'删除失败']);
        }
    }
    //找车
    public function searchCars()
    {
    	if ($this->request->isPost()){
    		$keywords = input('keywords');
    		if (!$keywords){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$where = ['a.car_no|b.realname|b.mobile'=>['like',"%".$keywords."%"]];
    		$field = 'a.car_no,a.car_long,b.mobile,b.realname,b.is_auth,b.location_city,a.uid,a.id';
    		$info = $this->car_list_model->getCarList($where, $field);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    //精确查找
    public function searchCarsAccurate()
    {
    	if ($this->request->isPost()){
    		$city = input('city');
    		$endcity = input('endcity');
    		$car_type = input('car_type');
    		$car_long = input('car_long');
    		$car_no = input('car_no');
    		if (empty($city) && empty($endcity) && empty($car_long) && empty($car_no) && empty($car_type)){
    			return json(['error'=>1,'success_msg'=>'您至少需要选一个条件']);
    		}
    		$where = [];
    		if (!empty($city)){
    			$where['b.location_city'] = ['like','%'.$city.'%'];
    		}
    		if (!empty($endcity)){
    			$where['a.like_city'] = ['like','%'.$endcity.'%'];
    		}
    		if (!empty($car_type)){
    			$where['c.car_type'] = ['like','%'.$car_type.'%'];
    		}
    		if (!empty($car_long)){
    			$where['c.car_long'] = $car_long;
    		}
    		if (!empty($car_no)){
    			$where['c.car_no'] = ['like','%'.$car_no.'%'];
    		}
    		$field = 'c.car_no,c.car_long,b.mobile,b.realname,b.is_auth,b.location_city,c.uid,c.id';
    		$info = $this->car_list_model->searchCars($where, $field);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    //车队邀请
    public function invitation()
    {
    	if ($this->request->isPost()){
    		$carid = $data['car_id'] = input('carid');
    		$uid = $data['uid'] = input('uid');
    		if (!$carid && !$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$re = $this->car_group_model->checkIsMyCarGroup($carid, $uid);
    		if ($re){
    			return json(['error'=>'1','success_msg'=>'该车辆已经在您的车队中']);
    		}
    		$info = $this->car_list_model->getUidByCarid($carid);
    		if (is_array($info)){
    			if ($info['uid'] == $uid){
	    			return json(['error'=>1,'success_msg'=>'您不能自己邀请自己']);
    			}
    		}
    		$re = $this->car_group_model->Invitation($data);
    		if ($re){
	    		return json(['error'=>0,'success_msg'=>'邀请成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'邀请失败']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    //车队邀请
    public function invitationManager()
    {
    	if ($this->request->isPost()){
    		$type = input('type');
    		$uid = input('uid');
    		$page = input('page');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$page = $page?$page:1;
    		if ($type == 1){
	    		$where = ['a.uid'=>$uid];
	    		$field = 'a.id,b.car_no,b.car_long,c.realname,c.mobile,a.status,c.is_auth,c.location_city';
	    		$info = $this->car_group_model->invitationManager($where, $field, $page);
	    		return json(['error'=>0,'success_msg'=>$info]);
    		}elseif ($type == 2) {
    			$carid = $this->car_list_model->getCaridByuid($uid);
    			if (is_array($carid)){
    				$carid = $carid['id'];
	    			$where = ['car_id'=>$carid];
	    			$field = 'b.id as uid,a.car_no,a.car_long,b.realname,b.mobile,b.is_auth,b.location_city';
	    			$info = $this->car_group_model->invitationMe($where, $field, $page);
	    			return json(['error'=>0,'success_msg'=>$info]);
    			}
    		}else {
    			return json(['error'=>1,'success_type'=>'非法参数type']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function carGroupOperation()
    {
    	if ($this->request->isPost()){
    		$id = input('inid');
    		$type = input('type');
    		if (!$id && !$type){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		if ($type == 1){
    			$data = ['status'=>1];
    		}elseif ($type == 2) {
    			$data = ['status'=>3];
    		}else {
    			return json(['error'=>1,'success_msg'=>'非法参数type']);
    		}
    		$re = $this->car_group_model->carGroupOperation($data, $id);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>'操作成功']);
    		}else {
    			return json(['error'=>1,'success_msg'=>'操作失败']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    public function carManager()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		$page = input('page');
    		if (!$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
                $where['a.status'] = 1;
    		$where['a.uid'] = $uid;
    		$field = 'a.id,a.car_id,c.idcard_img1,c.idcard_img2,c.realname,b.car_no';
    		$info = $this->car_group_model->invitationManager($where, $field, $page);
    		if (is_array($info)){
    			foreach ($info as $k=>$v){
    				$info[$k]['idcard_img1'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($v['idcard_img1'])[2];
    				$info[$k]['idcard_img2'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($v['idcard_img2'])[2];
    			}
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
	    }else {
	    	return json(['error'=>1,'success_msg'=>'请求类型错误']);
	    }
    }
    
    public function getCarManagerDetail()
    {
    	if ($this->request->isPost()){
    		$cargroupid = input('cargroupid');
    		if (!$cargroupid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$where = ['a.id'=>$cargroupid];
    		$field = 'c.realname,c.mobile,c.is_auth,b.car_no,b.car_long,b.car_weight,c.idcard,c.idcard_img1,b.drive_card,b.run_card,b.zige_card,b.hetong_car,b.car_bussi_card';
    		$info = $this->car_group_model->getCarManagerDetail($where, $field);
    		if (is_array($info)){
   				$info['idcard_img1'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['idcard_img1'])[2];
   				$info['drive_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['drive_card'])[1];
   				$info['run_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['run_card'])[1];
   				$info['hetong_car'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['hetong_car'])[1];
   				$info['zige_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['zige_card'])[0];
   				$info['car_bussi_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['car_bussi_card'])[0];
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
    //车辆详情
    public function getCarDetail()
    {
    	if ($this->request->isPost()){
    		$carid = input('carid');
    		if (!$carid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$where = ['a.id'=>$carid];
    		$field = 'b.realname,b.mobile,b.is_auth,a.car_no,a.car_long,a.car_weight,b.idcard,b.idcard_img1,a.drive_card,a.run_card,a.zige_card,a.hetong_car,a.car_bussi_card';
    		$info = $this->car_list_model->getCarDeatil($where, $field);
    		if (is_array($info)){
    			$info['idcard_img1'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['idcard_img1'])[2];
    			$info['drive_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['drive_card'])[1];
    			$info['run_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['run_card'])[1];
    			$info['hetong_car'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['hetong_car'])[1];
    			$info['zige_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['zige_card'])[0];
    			$info['car_bussi_card'] = 'http://'.$_SERVER['SERVER_NAME'].unserialize($info['car_bussi_card'])[0];
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
    	}
    }
    
	public function checkPaypass()
	{
		if ($this->request->isPost()){
			$uid = input('uid');
			$pay_pass = input('pay_pass');
			if (!$uid){
				return json(['error'=>3,'success_msg'=>'参数缺失']);
			}
			$info = $this->user_model->checkPaypass($uid);
			if (is_array($info)){
				if ($info['pay_pass'] != md5($pay_pass)){
					return json(['error'=>1,'success_msg'=>'支付密码错误']);
				}else {
					return json(['error'=>0,'success_msg'=>'SUCCESS']);
				}
			}
		}else {
    		return json(['error'=>1,'success_msg'=>'请求类型错误']);
		}
	}
	
	public function getUserLocationInfo()
	{
		if($this->request->isPost()){
			$car_id = input('car_id');
			$uid = $this->car_list_model->getUidByCarid($car_id);
			if (is_array($uid)){
				$uid = $uid['uid'];
				$info = $this->user_model->getUserInfo($uid);
			}
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型错误']);
		}
	}
	
	public function getNeedBill()
	{
		if ($this->request->isPost()){
			$uid = input('uid');
			if (!$uid){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$where['a.uid'] = $uid;
			$where['a.is_fapiao'] = 1;
			$orderinfo = DB::name('order')->alias('a')
				->join('bill_order b','a.id = b.order_id','left')
				->field('a.destination,a.start_place,a.car_long,a.goods_weight,a.goods_name,a.goods_num,a.danwei,a.id,a.post_price,a.orderid,a.yundanno,a.status')
				->where(['a.uid'=>$uid,'a.is_fapiao'=>1])
				->select();
			foreach ($orderinfo as $k=>$v){
                            $orderinfo[$k]['start_place'] = sliceAddressStr($v['start_place']);
                            $orderinfo[$k]['destination'] = sliceAddressStr($v['destination']);
				if ($v['status'] != 5){
					unset($orderinfo[$k]);
				}
			}
			return json(['error'=>0,'success_msg'=>$orderinfo]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
	public function getPostPriceByid()
	{
		if ($this->request->isPost()){
			$order_id = input('order_id');
			if (!$order_id){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$postprice = DB::name('order')
				->field('post_price')
				->where(['id'=>$order_id])
				->find();
			return json(['error'=>0,'success_msg'=>(string)round($postprice['post_price']/100,2)]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
        //开发票
	public function drawBill()
	{
		if ($this->request->isPost()){
			$order_id = input('order_id');
                        
                        if(Db::name('bill_order')->where(['order_id'=>$order_id])->count()){
                            return json(['error'=>1,'success_msg'=>'不能重复申请']);
                        }
			$name = input('name');
			$mobile = input('mobile');
			$address = input('address');
			$uid = input('uid');
                        $bill_no  = input('bill_no');
			if (!$order_id || !$name || !$mobile || !$address || !$uid){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$data = [
					'order_id'=>$order_id,
					'name'=>$name,
					'mobile'=>$mobile,
					'address'=>$address,
					'uid'=>$uid,
					'status'=>0,
					'ctime'=>time(),
                                        'bill_no'=>$bill_no
			];
			$re = DB::name('bill_order')->insert($data);
			if ($re){
				return json(['error'=>0,'success_msg'=>'申请成功']);
			}else {
				return json(['error'=>1,'success_msg'=>'申请失败']);
			}
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
	public function getBillList()
	{
		if ($this->request->isPost()){
			$uid = input('uid');
			if (!$uid){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$info = DB::name('bill_order')->alias('a')
				->field('a.bill_no,a.name,b.yundanno,b.orderid,b.post_price,a.status,a.id')
				->join('order b','a.order_id=b.id','left')
				->where(['a.uid'=>$uid])
				->select();
			foreach ($info as $k=>$v){
				$info[$k]['post_price'] = (string)round($v['post_price']/100,2);
			}
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
	public function getBillDetail()
	{
		if ($this->request->isPost()){
			$bill_id = input('bill_id');
			if (!$bill_id){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$info = DB::name('bill_order')->alias('a')
				->field('a.bill_no,a.name,a.address,a.mobile,c.company,c.company_no,c.company_address,c.company_tel,c.bank_name,c.bank_no,c.remark,
						b.id,b.yundanno,d.car_no,b.destination,b.start_place,b.goods_name,b.goods_num,b.danwei,b.post_price,b.ctime,b.orderid,
						b.pre_fee,b.total_fee')
				->join('order b','a.order_id=b.id','left')
				->join('company_bill c','a.company_id=c.id','left')
				->join('car_list d','b.driver_id=d.uid','left')
				->where(['a.id'=>$bill_id])
				->find();
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
    
    
    //加入黑名单
        public function addBlackList(){
            if(!request()->isPost())exit(json(['error'=>1,'success_msg'=>'非法请求']));
            
            $uid  = input('uid');
            $buid = input('buid');
            $remark = input('remark');
            
            //避免重复添加
            $count = Db::name('blacklist')->where(['uid'=>$uid,'buid'=>$buid])->count();
            if($count){
                 return json(['error'=>1,'success_msg'=>'重复添加']);
            }
            $user = $this->member_model->where(['id'=>$uid,'status'=>1])->find();
            $userb = $this->member_model->where(['id'=>$buid,'status'=>1])->find();
            
            if(!$user || !$userb){
                return json(['error'=>1,'success_msg'=>'用户不存在']);
            }
            
            if($user['types'] == $userb['types']){
                return json(['error'=>1,'success_msg'=>'同类型用户不能添加黑名单']);
            }
            $ret = Db::name('blacklist')->insert(
                    ['uid'=>$uid,'buid'=>$buid,'ctime'=>time(),'remark'=>$remark]
                    );
            if($ret){
                return json(['error'=>0,'success_msg'=>'ok']);
            }else{
                return json(['error'=>1,'success_msg'=>'faild']);
            }
        }
        
        //黑名单列表
        public function getBlackList(){
            if(!request()->isPost())exit(json(['error'=>1,'success_msg'=>'请求方式错误']));
            
            $uid  = input('uid');
            
            $list = Db::name('blacklist')
                    ->alias('a')
                    ->join('user b','a.buid = b.id','left')
                    ->join('company c','a.buid = c.uid','left')
                    ->join('car_list d','a.buid = d.uid','left')
                    ->where(['a.uid'=>$uid])
                    ->field('a.*,b.nickname,b.headpath,b.mobile,c.company,d.car_no')
                    ->order('id desc')
                    ->select();
            
            
            
            return json(['error'=>0,'success_msg'=>$list]);
        }
        
        //解除黑名单
        public function delBlackList(){
            if(!request()->isPost())exit(json(['error'=>1,'success_msg'=>'请求方式错误']));
            
            $uid  = input('uid');
            $buid = input('buid');
            
            Db::name('blacklist')->where(['uid'=>$uid,'buid'=>$buid])->delete(true);
             return json(['error'=>0,'success_msg'=>'ok']);
        }
    
        //线路列表
        
        public function getCarlines(){
            if(!request()->isPost()){
                return json(['error'=>1,'success_msg'=>'请求方式错误']);
            }
            
            $uid = input('uid');
            
            if(!Db::name('user')->where(['id'=>$uid])->count()){
                return json(['error'=>1,'success_msg'=>'用户不存在']);
            }
            $lists = Db::name('carlines')->where(['uid'=>$uid])->select();
            
            if($lists){
                return json(['error'=>0,'success_msg'=>$lists]);
            }else{
                return json(['error'=>1,'success_msg'=>'数据为空']);
            }
        }
        
        //编辑线路
        public function editCarline(){
            if(!request()->isPost()){
                return json(['error'=>1,'success_msg'=>'请求方式错误']);
            }
            
             $line_id = input('line_id');
             
             
             $data['startads'] = input('startads');
             $data['endds']    = input('endds');
             $data['startads1'] = input('startads1');
             $data['endds1']    = input('endds1');
             
             $data['car_long']  = input('car_long');
             $data['load_weight'] = input('load_weight');
             $data['like_city']   = input('like_city');
             $data['car_type']    = input('car_type');
             
             $data['create_time'] = input('create_time');
             $data['remark']      = input('remark');
             
             $ret = Db::name('carlines')->where(['id'=>$line_id])->update($data);
             if($ret != false){
                 return json(['error'=>0,'success_msg'=>'操作成功']);
             }else{
                 return json(['error'=>1,'success_msg'=>'更新失败']);
             }
             
        }
        
        
        //删除线路
        public function delCarline(){
            if(!request()->isPost()){
                return json(['error'=>1,'success_msg'=>'请求方式错误']);
            }
            
            $line_id = input('line_id');
            
            if(Db::name('carlines')->where(['id'=>$line_id])->delete(true)){
                return json(['error'=>0,'success_msg'=>'删除成功']);
            }else{
                return json(['error'=>1,'success_msg'=>'删除失败']);
            }
        }
        //审核
    public function shenhe(){
        $id = input('id');
        if(!$id){
            return json_encode(['error'=>1,'msg'=>'参数错误']);
        }
        
        $user = Db::name('user')->where(['id'=>$id])->find();
        var_dump(!$user['realname'] || !$user['idcard'] || !$user['idcard_img1'] || !$user['idcard_img2']|| !$user['idcard_img3']);exit;
        if(!$user['realname'] || !$user['idcard'] || !$user['idcard_img1'] || !$user['idcard_img2']|| !$user['idcard_img3']){
           return json(['code'=>0,'msg'=>'实名认证数据不完整']);
        }
        $affect_row = Db::name('user')->where(['id'=>$id])->update(['status'=>1,'is_auth'=>1]);
        if($affect_row !==  false){
            return json(['code'=>1,'msg'=>'审核通过']);
        }else{
            return json(['code'=>0,'msg'=>'审核失败']);
        }
    }
    public function testds(){
        $huoyuan_id =  176;
                   $receivers = $this->getTuiReceivers($huoyuan_id,100);
                   $re = 0;
                   if(is_array($receivers)){
                        foreach($receivers as $rc){
                             $temp[] = 'apkpro_'.$rc;
                         }

                        //推送信息
                        $re = $this->jgPushOne('刚刚有新的货源发布了fsdfsdf！',$temp);
                   }
                   var_dump($receivers,$re);
    }    
    
    
    
    
    
    
    

}