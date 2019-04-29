<?php
namespace app\api\controller;

use app\common\model\Member as MemberModel;
use app\common\model\Huoyuan as HuoyuanModel;
use app\common\model\Carlines as CarlinesModel;
use app\common\model\Partner as PartnerModel;
use think\Controller;
use think\Db;
// use think\Session;
use app\common\controller\ApiBase;
use app\common\model\CarList as CarListModel;
use app\common\model\Order as OrderModel;
use app\common\model\DriverList as DriverListModel;
use app\common\model\User as UserModel;


/**
 * 用户相关接口
 * Class Upload
 * @package app\api\controller
 */
class Index extends ApiBase
{
    protected $member_model;
    protected $huoyuan_model;
    protected $partner_model;
    protected $carlines_model;
    protected $carlist_model;
    protected $order_model;
    protected $driverList_model;
    protected $user_model;
    protected function _initialize()
    {
        parent::_initialize();
        $this->user_model = new UserModel();
        $this->driverList_model = new DriverListModel();
        $this->order_model = new OrderModel();
        $this->member_model = new MemberModel();
        $this->huoyuan_model = new HuoyuanModel();
        $this->partner_model = new PartnerModel();
        $this->carlines_model = new CarlinesModel();
        $this->carlist_model = new CarListModel();
        $action = $this->request->action();
        $tokens = input('tokens');
//        if (!in_array($action,array('build_yzm1')) && (!$tokens || !$this->member_model->check_tokens($tokens))) {
//            $result = [
//                'error'   => 1,
//                'message' => '请先登录'
//            ];
//            die(json_encode($result,JSON_UNESCAPED_UNICODE));
//        }
    }



    //推荐货源列表
    public function tuijian_hylist(){
        if($this->request->isPost()){
            
            $uid = input('uid');
            if(is_numeric($uid) && !empty($uid)){
                //屏蔽司机黑名单货主
                $black = Db::name('blacklist')->where(['uid'=>$uid])->column('buid');
                $where['a.uid'] = ['not in',$black];
            }
            
            $staradd = input('startads1')?input('startads1'):'';
            $where['flag']   = ['=',1];
            $where['status'] = ['=',0];
            if($staradd){
                $where['startads1'] = array('like','%'.$staradd."%");
            }
            $list = Db::name('huoyuan')
                    ->alias('a')
                    ->join('chinacity b','b.id = a.startads1','left')
                    ->join('chinacity c','c.id = a.endds1','left')
                    ->field('a.*,b.lat,b.lng,c.lat as lat2,c.lng as lng2')
                    ->where($where)->limit(0,5)->select();
            foreach($list as $key=>$row){
                $list[$key]['startads'] =  sliceAddressStr($row['startads']);
                $list[$key]['endds']    =  sliceAddressStr($row['endds']);
                $list[$key]['distance'] = getDistance($row['lng'], $row['lat'], $row['lng2'], $row['lat2']);
            }
            if($list){
                $result['error'] = 0;
                $result['success_msg'] = '推荐货源获取成功';
                $result['data'] = $list;
            }else{
                $result['error'] = 3;
                $result['success_msg'] = '推荐货源为空';
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //推荐车辆列表bak
    public function tuijian_carlist(){
        if($this->request->isPost()){
            
            $uid = input('uid');
            $lng = input('lng');
            $lat = input('lat');
            
            if(is_numeric($uid) && !empty($uid)){
                //屏蔽司机黑名单货主
                $black = Db::name('blacklist')->where(['uid'=>$uid])->column('buid');
                $where['a.uid'] = ['not in',$black];
            }
            $staradd = input('startads1','');
            //$where['a.flag']   = 1;
            $where['b.status'] = 1;
            $where['b.types']  = 1;
            if($staradd){
                $where['startads1'] = array('like','%'.$staradd."%");
            }
            $field = 'a.id,a.uid,a.startads,a.endds,a.remark,b.realname,b.mobile,b.lng,b.lat,c.car_long,c.car_type,c.car_weight,d.lng as lng2,d.lat as lat2';
            $list = $this->carlist_model->getTuijianCarLists($where, $field);
            if($list){
                foreach($list as $k=>$v){
                    $list[$k]['startads']    = sliceAddressStr($v['startads']);
                    $list[$k]['endds']    = sliceAddressStr($v['endds']);
                    $list[$k]['distance'] = getDistance($v['lng'],$v['lat'],$lng,$lat);
                }
                array_multisort(array_column($list, 'distance'), SORT_ASC, $list);
                $list = array_slice($list,0,3);
                $result['error'] = 0;
                $result['success_msg'] = '推荐车辆获取成功';
                $result['data'] = $list;
            }else{
                $result['error'] = 3;
                $result['success_msg'] = '推荐车辆为空';
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    public function tuijian_carlist2(){
        if(!request()->isPost()){return json(['error'=>1,'success_msg'=>'请求方式错误!']);}
            
        $uid = input('uid');
        if(is_numeric($uid) && !empty($uid)){
            //屏蔽司机黑名单货主
            $black = Db::name('blacklist')->where(['uid'=>$uid])->column('buid');
            $where['a.uid'] = ['not in',$black];
        }
        $car_long = input('car_long',0);
        $car_type = input('car_type','');
        
        $where['b.status'] = 1;
        $where['b.types']  = 1;
        if($car_long){
            $where['a.car_long'] = array('<',$car_long);
        }
        
        if($car_type){
            $where['a.car_type'] = $car_type;
        }
        if(!empty($user['location_city'])){
            $where['a.location_city'] = $user['location_city'];
        }
        $user = Db::name('user')->where(['id'=>$uid])->find();
        $field = 'a.*,b.lng,b.lat,b.headpath,b.nickname,b.realname,b.mobile,b.is_auth,b.location_city';
        $lists = Db::name('car_list')->alias('a')
                ->join('user b','a.uid = b.id','left')
                ->field($field)
                ->where($where)
                ->select();
        if($lists){
            foreach($lists as $k=>$v){
                $lists[$k]['distance'] = getDistance($v['lng'],$v['lat'],$user['lng'],$v['lat']);
            }
            
            array_multisort (array_column($lists, 'distance'), SORT_DESC, $lists);
            $lists = array_slice($lists,0,3);
            return json(['error'=>0,'success_msg'=>'推荐车辆获取成功!','data'=>$lists]);
        }else{
            return json(['error'=>1,'success_msg'=>'推荐车辆为空!']);
        }
    }

    public function get_all_carlist()
    {
    	if ($this->request->isPost()){
    		$where = [];
                $lng = input('lng');
                $lat = input('lat');
                
                $uid = input('uid');
                if(is_numeric($uid) && !empty($uid)){
                    //屏蔽司机黑名单货主
                    $black = Db::name('blacklist')->where(['uid'=>$uid])->column('buid');
                    $where['a.uid'] = ['not in',$black];
                }
            
    		$startads1 = input('startads1');
    		$p = input('page',1);
                $where['b.types'] = 1;
    		if (!empty($startads1)){
                        $arr = explode(',',$startads1);
                        $startads1 = end($arr);
    			$where['a.startads1'] = ['like','%'.$startads1.'%'];
    		}
    		$endds1 = input('endds1');
    		if (!empty($endds1)){
                        $arr = explode(',',$endds1);
                        $endds1    = end($arr);
    			$where['a.endds1'] = ['like','%'.$endds1.'%'];
    		}
    		$car_weight = input('car_weight');
    		if (!empty($car_weight)){
    			$where['c.car_weight'] = $car_weight;
    		}
    		$field = 'a.id,a.uid,a.startads,a.endds,a.remark,b.realname,b.mobile,b.lng,b.lat,c.car_long,c.car_type,c.car_weight,d.lng as lng2,d.lat as lat2';
    		$lists = $this->carlist_model->getAllCarLists($where, $field,$p);
                
                foreach($lists as $k=>$v){
                    $lists[$k]['startads'] = sliceAddressStr($v['startads']);
                    $lists[$k]['endds'] = sliceAddressStr($v['endds']);
                    $lists[$k]['distance'] = getDistance($v['lng'],$v['lat'],$lng,$lat);
                }
    		return json(['error'=>0,'success_msg'=>$lists]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    //线路详情
    public function getCarlineDetail()
    {
    	if ($this->request->isPost()){
    		$lineid = $where['a.id'] = input('lineid');
                $lng = input('lng');
                $lat = input('lat');
                
    		if (!$lineid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$p = 1;
    		$field = 'a.startads,a.endds,a.remark,b.id as uid,b.realname,b.mobile,b.headpath,b.is_auth,b.lng,b.lat,c.car_no,c.car_long,c.car_type,c.car_weight,a.like_city,d.lng as lng2,d.lat as lat2';
    		$info = $this->carlist_model->getAllCarLists($where, $field,$p);
                
    		if (is_array($info) && !empty($info)){
    			$info = $info[0];
                        $info['startads'] = sliceAddressStr($info['startads']);
                        $info['endds'] = sliceAddressStr($info['endds']);
    			$info['headpath'] = "http://".$_SERVER['SERVER_NAME'].$info['headpath'];
                        $info['distance'] = getDistance($info['lng'], $info['lat'], $lng, $lat);
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }

    //首页幻灯片
    public function get_slide_list(){
        if($this->request->isPost()){
            $cid = input('cid');
            if(!$cid){
                $result['error'] = 3;
                $result['success_msg'] = '缺失参数';
            }else{
                $list = Db::name('slide')->where(['cid'=>$cid])->select();
                if($list){
                    foreach($list as $k=>$v){
                        $list[$k]['image'] = '/public'.$v['image'];
                    }
                    $result['error'] = 0;
                    $result['success_msg'] = '货主幻灯获取成功';
                    $result['data'] = $list;
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '货主幻灯获取为空';
                }
            }

        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    
    public function searchCars()
    {
    	if ($this->request->isPost()){
    		$keywords = input('keywords');
    		if (!$keywords){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$where = ['c.car_no|b.mobile|b.realname'=>['like','%'.$keywords.'%'],'b.types'=>1,'b.status'=>1];
    		$field = 'a.id,a.uid,a.startads,a.endds,a.remark,b.realname,b.mobile,c.car_long,c.car_type,c.car_weight,d.lng,d.lat,e.lng as lng2,e.lat as lat2';
    		$info = $this->carlist_model->searchCars($where, $field);
     		foreach ($info as $k=>$v){
     			//$info[$k]['headpath'] = 'http://'.$_SERVER['SERVER_NAME'].$v['headpath'];
                    $info[$k]['distance'] = getDistance($v['lng'], $v['lat'], $v['lng2'], $v['lat2']);
     		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function searchHuoyuan()
    {
    	if ($this->request->isPost()){
    		$keywords = input('keywords');
    		if ($keywords == ''){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$where = ['b.realname|b.mobile|a.company|a.startads|a.endds|a.good_type'=>['like','%'.$keywords.'%']];
    		$field = 'c.lng,c.lat,d.lng as lng2,d.lat as lat2,b.mobile,b.realname,a.company,a.id,a.startads,a.endds,a.good_type,b.headpath,a.car_type,a.uid,a.car_long,a.danwei,a.price,a.weight,a.create_time,a.remark';
    		$info = $this->huoyuan_model->getHuoyuan($where,$field);
    		foreach ($info as $k=>$v){
    			$info[$k]['headpath'] = 'http://'.$_SERVER['SERVER_NAME'].$v['headpath'];
    			$info[$k]['startads'] = sliceAddressStr($v['startads']);
    			$info[$k]['endds'] = sliceAddressStr($v['endds']);
                        $info[$k]['distance'] = getDistance($v['lng'], $v['lat'], $v['lng2'], $v['lat2']);
    		}
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function searchFriend()
    {
    	if ($this->request->isPost()){
    		$uid = input('uid');
    		if (!$uid){
    			return json(['error'=>3,'success_msg'=>'参数缺失']);
    		}
    		$p = input('page');
    		$p = $p?$p:1;
    		$driver_id = $this->driverList_model->getDriverIdByUid($uid);
    		if (!$driver_id){
    			return json(['error'=>3,'success_msg'=>'您还没添加车辆']);
    		}
    		$where = ['a.driver_id'=>$driver_id,'a.status'=>['in','1,5']];
//     		$field = 'a.start_place,a.destination,a.goods_type,a.goods_num,a.danwei,a.post_price,a.ctime,b.headpath,b.realname,
// 					c.company,b.mobile,c.remark';
    		$field = 'a.id,a.start_place,a.destination,a.goods_type,a.goods_num,a.danwei,a.post_price,a.ctime,b.realname,b.mobile';
    		$info = $this->order_model->getFriendsByOrder($where,$field,$p);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求类型有误']);
    	}
    }
    
    public function saveLocationInfo()
    {
    	if ($this->request->isPost()){
    		$lng = $data['lng'] = input('lng');
    		$lat = $data['lat'] = input('lat');
    		$uid = $data['uid'] = input('uid');
    		$locationcity = $data['location_city'] = input('location_city');
    		if (!$lat && !$lat && !$uid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$re = $this->user_model->saveLocationInfo($data);
    		if ($re){
    			return json(['error'=>0,'success_msg'=>"SUCCESS"]);
    		}else {
    			return json(['error'=>1,'success_msg'=>'FAIL']);
    		}
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求方式有误']);
    	}
    }
    
    public function getProvinceList()
    {
    	$info = getProvince();
    	return json(['error'=>0,'success_msg'=>$info]);
    }
    
    public function getCityList()
    {
    	if ($this->request->isPost()){
    		$provinceid = input('provinceid');
    		if (!$provinceid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$info = getCity($provinceid);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求方式有误']);
    	}
    }
    
    public function getCountyList()
    {
    	if ($this->request->isPost()){
    		$cityid = input('cityid');
    		if (!$cityid){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$info = getCounty($cityid);
    		return json(['error'=>0,'success_msg'=>$info]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求方式有误']);
    	}
    }
    
    public function chargePostFee()
    {
    	if ($this->request->isPost()){
    		$star_place = input('start_place');
    		$end_place = input('end_place');
    		if (!$star_place || !$end_place){
    			return json(['error'=>1,'success_msg'=>'参数缺失']);
    		}
    		$star_place_info = getAddressInfo($star_place);
    		$end_place_info = getAddressInfo($end_place);
    		$juli = getDistance($star_place_info['lng'], $star_place_info['lat'], $end_place_info['lng'], $end_place_info['lat']);
    		$post_fee = $juli*20;
    		return json(['error'=>0,'success_msg'=>$post_fee]);
    	}else {
    		return json(['error'=>1,'success_msg'=>'请求方式有误']);
    	}
    }
    
    public function getmailprice()
    {
    	$info = getMailPrice();
    	return json(['error'=>0,'success_msg'=>$info]);
    }
    
  //系统消息
    
    public function getSysMsg(){
        if(!request()->isPost()){return json(['error'=>1,'success_msg'=>'请求方式错误']);}
        
        $uid = input('uid');
        $os_type = input('os_type',1);
        $sysmsg = Db::name('message')->where(['type'=>0,'os_type'=>$os_type])->whereOr(['FIND_IN_SET('.$uid.',uid)'=>['>',0]])->order('id desc')->limit(10)->select();
        foreach($sysmsg as $k=>$v){
            $sysmsg[$k]['time'] = date('Y-m-d H:i:s',$v['time']);
        }
        
        if($sysmsg !== false){
            return json(['error'=>0,'success_msg'=>$sysmsg]);
        }else{
            return json(['error'=>1,'success_msg'=>'faild!']);
        }
    }
    
    //二维码地址
    public function getQrCode(){
        if(!request()->isPost()){
            return json(['error'=>1,'success_msg'=>'请求方式错误']);
        }
        $os_type = input('os_type',1);
        $download_url = '';
        if($os_type == 1){
            $download_url = Db::name('setting')->where(['name'=>'android_download_url'])->find();
        }else{
            $download_url = Db::name('setting')->where(['name'=>'ios_download_url'])->find();
        }
        $http_host = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/';
        
        $where = ['os_type'=>$os_type];
        $share = Db::name('user_share')->where($where)->find();
        if(!empty($share['url']) && $share['download_url'] == $download_url['value']){
             return json(['error'=>0,'success_msg'=>$share['url']]);
        }else{
            $qrcode = $http_host.getQrcode($download_url['value']);
            
            $data = ['os_type'=>$os_type,'url'=>$qrcode,'download_url'=>$download_url['value']];
            $count = Db::name('user_share')->where($where)->count();
            if($count){
                Db::name('user_share')->where($where)->update($data);
            }else{
                Db::name('user_share')->insert($data);
            }
            return json(['error'=>0,'success_msg'=>$qrcode]);
        }
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    

}