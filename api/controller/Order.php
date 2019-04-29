<?php
namespace app\api\controller;

use app\common\controller\ApiBase;
use app\common\model\Member as MemberModel;
use app\common\model\Order as OrderModel;
use app\common\model\Huoyuan as HuoyuanModel;
use app\common\model\DriverList as DriverListModel;
use think\Db;
use think\Exception;
// use app\common\model\Huoyuan as HuoyuanModel;
class Order extends ApiBase
{
	protected $order_model;
	protected $member_model;
	protected $huoyuan_model;
	protected $driverlist_model;
	protected function _initialize()
	{
		parent::_initialize();
		$this->member_model = new MemberModel();
		$this->order_model = new OrderModel();
		$this->huoyuan_model = new HuoyuanModel();
		$this->driverlist_model = new DriverListModel();
		$action = $this->request->action();
		$tokens = input('tokens');
//		if (!$tokens || !$this->member_model->check_tokens($tokens)) {
//			$result = [
//					'error'   => 1,
//					'success_msg' => '请先登录'
//			];
//			die(json_encode($result,JSON_UNESCAPED_UNICODE));
//		}
	}
	//获取运单列表
	public function getHuoyunList()
	{
		if ($this->request->isPost()){
			$uid = input('uid');
			if (!$uid){
				return json(['error'=>3,'success_msg'=>'参数缺失']);
			}
			$where['uid'] = $uid;
			$map['status'] = ['in','2,3,4'];
			$huoyuaninfo = $this->huoyuan_model->getHuoyuanList($where);
			$orderinfo = $this->order_model->getOrderList($map);
			foreach ($huoyuaninfo as $k=>$v){
				foreach ($orderinfo as $kk=>$vv){
					if ($v['id'] == $vv['huoyuan_id']){
						unset($huoyuaninfo[$k]);
					}
				}
			}
			return json(['error'=>0,'success_msg'=>$huoyuaninfo]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	//根据运单模板获取运单详情
	public function getHuoyunInfoById()
	{
		if ($this->request->isPost()){
			$id = input('pid');
			if (!$id){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$where['id'] = $id;
			$info = $this->huoyuan_model->getHuoyunInfoById($where);
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
	//根据搜索内容获取司机列表
	public function driverList()
	{
		if ($this->request->isPost()){
			$keywords = input('keywords');
			if (!$keywords){
				return json(['error'=>3,'success_msg'=>'参数缺失']);
			}
			$where['a.car_no|b.realname|b.mobile'] = ['like','%'.$keywords.'%'];
			$info = $this->driverlist_model->getDriverList($where);
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	//根据id获取司机信息
	public function getDriverInfoById()
	{
		if ($this->request->isPost()){
			$id = input('pid');
			if (!$id){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$where['a.id'] = $id;
			$info = $this->driverlist_model->getDriverInfoById($where);
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
	public function getHuoyuanInfoById()
	{
		if ($this->request->isPost()){
			$huoyuan_id = input('huoyuan_id');
			$info = DB::name('huoyuan')
				->field('endds,startads,mobile,realname,good_type,weight,price,danwei,remark,id')
				->where(['id'=>$huoyuan_id])
				->find();
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型错误']);
		}
	}
	
	
	//指定司机下单
	public function take_order()
	{
		if (!request()->isPost())return json(['error'=>1,'success_msg'=>'请求方式错误']);
                
                $uid = $data['uid'] = input('uid');
                $user = Db::name('user')->where(['id'=>$uid])->find();
                if($user['status'] != 1){
                    return json(['error'=>1,'success_msg'=>'您的账户暂不能下单,请联系管理员!']);
                }
                
                $driver_id = $data['driver_id'] = input('driver_id');
                
                if(Db::name('order')->where(['driver_id'=>$driver_id,'status'=>['<>',5]])->count()){
                    return json(['error'=>1,'success_msg'=>'司机有尚未完成订单']);
                }
                $goods_name = $data['goods_name'] = input('goods_name');
                $start_time = $data['starttime'] = strtotime(input('start_time'));
                $yundanno = $data['yundanno'] = input('yundanno','YD'.date('YmdHis').rand(100,999));
                $payment  = input('payment',0);
                $post_fee = $data['post_price'] = input('post_fee');
                $data['goods_weight'] = input('goods_weight',0);
                $data['destid']       = input('endds1',0);
                $destination = $data['destination'] = input('destination');
                $start_place = $data['start_place'] = input('start_place');
                $goods_num = $data['goods_num'] = input('goods_num');
                $danwei = $data['danwei'] = input('danwei');
                $goods_type = $data['goods_type'] = input('goods_type');
                $remark = $data['remark'] = input('remark');
                $is_fapiao = $data['is_fapiao'] = input('is_fapiao');
                $times = $data['times']   = input('times');
                $volume = $data['volume'] = input('volume');
                $pay_type = $data['pay_type'] = input('pay_type',1);

                $tax_fee = $data['tax_fee'] = round($post_fee/1.01*0.01,2);
                if ($is_fapiao == 1){
                        $total_fee = $data['total_fee'] = $post_fee+$tax_fee;
                }else {
                        $total_fee = $data['total_fee'] = $post_fee;
                }
                $data['pre_fee'] = round($total_fee*0.3,2);

               // var_dump($uid,$driver_id,$yundanno,$post_fee,$destination,$start_place,$goods_num,$danwei,$goods_type,$is_fapiao);
                if (!$uid || !$driver_id || !$yundanno || !$post_fee || !$destination || !$start_place || !$goods_num || !$danwei || !$goods_type || !$is_fapiao){
                        return json(['error'=>1,'success_msg'=>'参数缺失']);
                }
                
                Db::startTrans();
                //$re = $this->order_model->createOrder($data);
                $data['orderid'] = createOrderid();
		$data['ctime'] = time();
		$data['status'] = 3;
		$data['paid'] = 0;
		$data['order_status'] = 1;
                $order_id  =  Db::name('order')->insertGetId($data);
                
                if (is_numeric($order_id) && $order_id>0){
                    try{
                        if($payment){
                            Db::commit();
                            $type = 2;
                            if($payment == 1)$type=4;
                            $data = [
                                'order_id' => $order_id,
                                'uid' =>  $uid,
                                'totalfee' => $total_fee,
                                'type'=> $type,
                                'payment'=> $payment
                            ];
                            $payapi_url  = url('api/pay/alipay','','',true);
                            switch($pay_type){
                                case '1': $payapi_url  = url('api/pay/alipay','','',true);break;
                                case '2': $payapi_url  = url('api/pay/wxpay','','',true);break;
                                case '3': $payapi_url  = url('api/pay/balance','','',true);break;
                            }
                             curlpost($payapi_url,$data);
                             exit;
                        }else{
                            //推送信息
                            //$this->jgPushOne('有货主为你指定了订单', 'apkpro_'.$driver_id);
                            Db::commit();
                            return json(['error'=>0,'success_msg'=>'下单成功']);
                        }
                    }catch(think\Exception $e){
                        Db::rollback();
                        return json(['error'=>2,'success_msg'=>'下单成功,推送信息异常']);
                    }
                        
                }else {
                        Db::rollback();
                        return json(['error'=>1,'success_msg'=>'下单失败']);
                }
		
	}
	
        //司机接单
        public function driver_takeorder(){
           if(!request()->isPost()){ return json(['error'=>1,'success_msg'=>'请求方式错误']); }
                $data['huoyuan_id'] = input('hyid');
                $data['driver_id'] = input('uid');
                
                if(!$data['huoyuan_id'] || !$data['driver_id']){
                    return json(['error'=>1,'success_msg'=>'缺少参数']);
                }
                
                //司机状态
                if(!Db::name('user')->where(['id'=>$data['driver_id'],'types'=>1])->count()){
                    return json(['error'=>1,'success_msg'=>'司机不存在']);
                }
                
                //司机是否有订单
                if(Db::name('order')->where(['driver_id'=>$data['driver_id'],'status'=>['in',[1,2,3,4,6]]])->count()){
                    return json(['error'=>1,'success_msg'=>'同一时间只能接一单']);
                }
                
                $info = $this->huoyuan_model->get_huoyuan_info(['hy.id'=>$data['huoyuan_id']]);
                if($info['status']){
                    return json_encode(['error'=>1,'success_msg'=>'此货源已失效']);
                }
                $data['uid']       = $info['uid'];
                $data['orderid']   = createOrderid();
                $data['destination'] = $info['endds'];
                $data['destid']    = $info['endds1'];
                $data['starttime'] = time();
                $data['start_place'] = $info['startads'];
                $data['goods_type'] = $info['good_type'];
                $data['goods_name'] = $info['goods_name'];
                $data['goods_weight'] = $info['weight'];
                $data['car_type']    = $info['car_type'];
                $data['car_long']    = $info['car_long'];
                $data['post_price'] = $info['price'];
                $data['status']     = 2;
                $data['ctime']      = time();
                $data['pay_type']    = 'offline';
                $data['tax_fee']    = 2;
                $data['total_fee']  = $info['price']+$data['tax_fee'];
                
                $data['paid']         = 2;
                $data['order_status'] = 1;
                $data['accept_time']  = time();
                $data['is_fapiao']    = 1;
                $data['volume']       = $info['volume'];
                $data['times']        = $info['times'];
                
                 Db::startTrans();
                try{
                    $ret = $this->order_model->create($data);
                    if($ret !== false){
                        $re = $this->huoyuan_model->where(['id'=>$data['huoyuan_id']])->update(['status'=>1]);
                        if($re !== false){
                            // 提交事务
                            Db::commit();  
                            return json_encode(['error'=>0,'success_msg'=>'接单成功']);
                        }else{
                            Db::rollback();
                             return json_encode(['error'=>1,'success_msg'=>'接单失败']);
                        }
                    }else{
                        Db::rollback();
                        return json_encode(['error'=>1,'success_msg'=>'接单失败']);
                    }
                 
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }

        }
        
	public function calPrice()
	{
		if ($this->request->isPost()){
                    $get = Db::name('setting')->where(['name'=>'prefee_percent'])->find();
			$star_place = input('start_place');
			$end_place = input('end_place');
			if (!$star_place || !$end_place){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$star_place_info = getAddressInfo($star_place);
			$end_place_info  = getAddressInfo($end_place);
			$juli = getDistance($star_place_info['lng'], $star_place_info['lat'], $end_place_info['lng'], $end_place_info['lat']);
			$starting_price = getStartingPrice();
			$mail_price = getMailPrice();
			$post_fee = input('post_fee');//$juli*$mail_price+$starting_price;
                        //$post_fee += round($post_fee/1.01*0.01);
			$data = [
					'mailprice'=>$mail_price,
					'startingprice'=>$starting_price,
					'postfee'=>$post_fee,
					'juli'=>$juli,
					'tax_fee'=>round($post_fee/1.01*0.01),
                                        'pre_fee'=>round($post_fee*$get['value'],2),
			];
			return json(['error'=>0,'success_msg'=>$data]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型错误']);
		}
	}
	
        //根据状态获得订单列表
	public function getOrderListByStatus()
	{
		if ($this->request->isPost()){
			$status = input('status');
			$uid = input('uid');
                        $user_type = input('user_type');
                        
			if (!$status && !$uid){
				return json(['error'=>3,'success_msg'=>'参数缺失']);
			}
			if ($status == 1){
				$where['a.status'] = ['in',[3,2]];
                        }else if($status == 4){
                            $where['a.status'] = ['in',[4,6,7]];
                        }else{
				$where['a.status'] = $status;
			}
			$p = input('page')?input('page'):1;
                        
			if($user_type == 1){
                            $where['a.driver_id'] = $uid;
                        }else{
                            $where['a.uid'] = $uid;
                        }
                        //var_dump($status,$user_type,$where);
			$lists = $this->order_model->getOrderListByStatus($where,$p);
                        
                        foreach ($lists as $k=>$v){
                    
                        //$city = Db::name('chinacity')->where(['id'=>$v['startads1']])->find();
//
                            //var_dump($v);exit;
                            $temp1 = explode(',',$v['start_place']);
                            $temp1 = array_slice($temp1,0,2);

                            $temp2 = explode(',',$v['destination']);
                            $temp2 = array_slice($temp2,0,2);

                            $lists[$k]['start_place'] = implode('',$temp1);
                            $lists[$k]['destination'] = implode('',$temp2);
//                            //$lists[$k]['distance'] = getDistance($v['lng'],$v['lat'],$city['lng'],$city['lat']);
                            $lists[$k]['weight'] = $v['goods_weight'].$v['danwei'];//$v['goods_num'].$v['danwei'];
                            unset($lists[$k]['goods_num']);
                            unset($lists[$k]['danwei']);
                            $lists[$k]['ctime'] = date('Y-m-d',$v['ctime']);
                            $lists[$k]['post_price'] = $v['post_price'];
                            $lists[$k]['distance'] = getDistance($v['lng'], $v['lat'], $v['lng2'], $v['lat2']);
                            $lists[$k]['car_long'] = empty($v['car_long'])?$v['car_long2']:$v['car_long'];
                            $lists[$k]['car_type'] = empty($v['car_type'])?$v['car_type2']:$v['car_type'];
                         }
                        
                         
			return json(['error'=>0,'success_msg'=>$lists]);
		}else{
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
	public function getOrderDetail()
	{
		if ($this->request->isPost()){
			$order_id = input('order_id');
			if (!$order_id){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$where['a.id'] = $order_id;
			$info = $this->order_model->getOrderDetail($where);
			return json(['error'=>0,'success_msg'=>$info]);
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
        //接单大厅接单
	public function takeOrder()
	{
		if ($this->request->isPost()){
			$uid = $data['uid'] = input('uid');
                        
                        $user = Db::name('user')->where(['id'=>$uid])->find();
                        if($user['status'] != 1){
                            return json(['error'=>1,'success_msg'=>'您的账户暂不能接单,请联系管理员!']);
                        }
                        
			if (!$uid){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$order_id = $data['order_id'] = input('order_id');
			$huoyuan_id = $data['huoyuan_id'] = input('huoyuan_id');
			$re = $this->order_model->takeOrder($data);
			if ($re){
				return json(['error'=>0,'success_msg'=>'接单成功']);
			}else {
				return json(['error'=>1,'success_msg'=>'接单失败']);
			}
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
        //取消确认
	public function refuseOrder()
	{
		if (request()->isPost()){
			$uid = $data['uid'] = input('uid');
			$order_id = $data['order_id'] = input('order_id');
                        $user_type = $data['user_type']=input('user_type');
			if (!$uid && !$order_id){
				return json(['error'=>1,'success_msg'=>'参数缺失']);
			}
			$re = $this->order_model->refuseOrder($data);
			if ($re){
				return json(['error'=>0,'success_msg'=>'拒绝成功']);
			}else {
				return json(['error'=>0,'success_msg'=>'拒绝失败,请稍后再试']);
			}
		}else {
			return json(['error'=>1,'success_msg'=>'请求类型有误']);
		}
	}
	
        
        //更新订单状态
        // action=1:货主确认订单，2货主取消订单3.司机确认订单，4司机取消订单5，司机确认送到 6.货主确认收货并付款 7.司机确认收款，订单完成
        public function updateOrderStatus(){
            if(!request()->isPost()){
                exit(json_encode(['error'=>1,'success_msg'=>'请求方式错误!']));
            }
            
            $user_id   = input('user_id');
            $order_id  = input('order_id');
            $action    = input('action');
            $status    = 0;
            
            $dict[1] = [2,4];
            $dict[2] = [2,0];
            $dict[3] = [3,4];
            $dict[4] = [3,0];
            $dict[5] = [4,6];
            
            $dict[6] = [6,7];
            $dict[7] = [7,5];
            
            $where = ['id'=>$order_id];
            $order = $this->order_model->where($where)->find();
            $driver  = Db::name('user')->where(['id'=>$order['driver_id']])->find();
    
            if(in_array($action,[1,2,6]) && $order['uid'] != $user_id){
                 exit(json_encode(['error'=>1,'success_msg'=>'非订单货主操作！']));
            }
            
            if(in_array($action,[3,4,5,7]) && $order['driver_id'] != $user_id){
                 exit(json_encode(['error'=>1,'success_msg'=>'非订单司机操作！']));
            }
           
           Db::startTrans();
           try{
               
                //货主确认订单，货源恢复
            if($action == 1){
                Db::name('order')->where(['id'=>$order_id])->update(['starttime'=>time(),'accept_time'=>time()]);
            }
            
             //货主取消订单，货源恢复
            if($action == 2 && $order['huoyuan_id']){
                Db::name('huoyuan')->where(['id'=>$order['huoyuan_id']])->update(['status'=>0]);
            }
            
            //司机确认订单,确认收到定金或全款，并接单
            if($action == 3){
                switch($order['payment']){
                    case 1:$paid=1;break;
                    case 2:$paid=3;break;
                    case 0:$paid=0;break;
                }
                Db::name('order')->where(['id'=>$order_id])->update(['paid'=>$paid,'starttime'=>time(),'accept_time'=>time()]);
            }
            
            //司机取消订单，返还定金
            if($action == 4){
                 Db::name('order')->where(['id'=>$order_id])->update(['paid'=>0]);
                 if($order['paid'] == 2){
                     $get = Db::name('setting')->where(['name'=>'prefee_percent'])->find();
                     $pre_fee = round($order['total_fee']*$get['value'],2);
                     Db::name('user')->where(['id'=>$order['driver_id']])->setDec('frozen',$pre_fee);
                     Db::name('user')->where(['id'=>$order['uid']])->setInc('money',$pre_fee);
                 }
            }
            //司机确认送达
            if($action == 5){
                Db::name('order')->where(['id'=>$order_id])->update(['arrive_time'=>time()]);
            }
            
            //货主确认收货
            if($action == 6){
                if($order['payment'] == 0){
                    Db::name('order')->where(['id'=>$order_id])->update(['paid'=>4]);
                }
            }
            //货主确认订单完成，解冻司机冻结资金
            if($action == 7){
                if($order['total_fee'] > $driver['frozen'] && $order['pay_type']){
                    Db::rollback();
                    return json(['error'=>1,'success_msg'=>'冻结资金异常']);
                }
                Db::name('user')->where(['id'=>$order['driver_id']])->setDec('frozen',$order['total_fee']);
                Db::name('user')->where(['id'=>$order['driver_id']])->setInc('money',$order['total_fee']);
                Db::name('order')->where(['id'=>$order_id])->update(['paid'=>1,'finish_time'=>time()]);//更新支付状态为支付完成
            }
            
            foreach($dict as $k=>$v){
                if($k == $action){
                    if($v[0] != $order['status']){
                        Db::rollback();
                        exit(json_encode(['error'=>1,'success_msg'=>'非法操作！']));
                    }else{
                        $status = $v[1];
                    }
                }
                
            }
            
            $ret = Db::name('order')->where($where)->update(['status'=>$status]);
            if($ret !== false){
                // 提交事务
                Db::commit();  
                exit(json_encode(['error'=>0,'success_msg'=>'ok']));
            }else{
                exit(json_encode(['error'=>1,'success_msg'=>'faild']));
            }
           }catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                exit(json_encode(['error'=>1,'success_msg'=>$e->getMessage().';rollback!']));
            }
            
        }
	
        
        //更新订单支付状态,司机确认货主支付尾款或货主全款支付,完成订单
        public function updatePayStatus(){
            if(!request()->isPost()){
                exit(json_encode(['error'=>1,'success_msg'=>'请求方式错误!']));
            }
            
            $user_id   = input('user_id');
            $order_id  = input('order_id');
            
            $where = ['id'=>$order_id];
            $order = $this->order_model->where($where)->find();
            if($order['driver_id'] != $user_id){
                 exit(json_encode(['error'=>1,'success_msg'=>'非订单司机操作！']));
            }
            
            if(in_array($order['status'],[2,3,4])){
                 exit(json_encode(['error'=>1,'success_msg'=>'订单未送达！']));
            }
            
            if(in_array($order['paid'],[0,2,3])){
                 exit(json_encode(['error'=>1,'success_msg'=>'货主尚未完成支付！']));
            }
           
           Db::startTrans();
           try{
                $driver  = Db::name('user')->where(['id'=>$order['driver_id']])->find();
                if($order['total_fee'] > $driver['frozen'] && $order['pay_type']){
                    return json(['error'=>1,'success_msg'=>'冻结资金异常']);
                }
                
                Db::name('order')->where($where)->update(['paid'=>1,'status'=>5,'finish_time'=>time()]);
                Db::name('user')->where(['id'=>$order['driver_id']])->setDec('frozen',$order['total_fee']);
                Db::name('user')->where(['id'=>$order['driver_id']])->setInc('money',$order['total_fee']);
                Db::commit();
                return json(['error'=>0,'success_msg'=>'订单完成']);
           } catch (Exception $ex) {
                Db::rollback();
                return json(['error'=>1,'success_msg'=>$ex->getMessage()]);
           }
            
        }
	
	
	
//        public function push_test(){
//            $msg = 'hello,郑州！';
//            $return = $this->jgPushAll($msg);
//            var_dump($return);exit;
//        }
//        
//        public function push_byid(){
//            $msg = "郑州，你好！";
//            $id = 'apkpro_124';
//            $return = $this->jgPushOne($msg,$id);
//              var_dump($return);exit;
//        }
	
	
	
	
	
	
	
	
	
	
	
	
}