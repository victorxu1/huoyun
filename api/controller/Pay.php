<?php
namespace app\api\controller;

use think\Controller;
use app\common\controller\ApiBase;
use app\common\model\Member as MemberModel;
use app\common\model\Wallet as WalletModel;
use think\Request;
use think\Db;
class Pay extends ApiBase
{
	protected $member_model;
	protected $wallet_model;
	protected function _initialize()
	{
		parent::_initialize();
		$this->wallet_model = new WalletModel();
		$this->member_model = new MemberModel();
		$action = $this->request->action();
		$tokens = $this->request->post('tokens');
//		if (!$tokens || !$this->member_model->check_tokens($tokens)) {
//			$result = [
//					'error'   => 1,
//					'message' => '请先登录'
//			];
//			die(json_encode($result,JSON_UNESCAPED_UNICODE));
//		}
	}

	
	
	
	//提现
	public function applyCash()
	{
                if (!$this->request->isPost()){return json(['error'=>1,'success_msg'=>'请求类型有误']);}
                
                $money = $data['money'] = input('money');
                $uid = $data['uid'] = input('uid');
                
                $user = Db::name('user')->where(['id'=>$uid])->find();
                if(empty($user['ali_user'])){
                    return json(['error'=>1,'success_msg'=>'尚未绑定支付宝，请先去绑定!']);
                }
                
                if($user['money'] < $money){
                    return json(['error'=>1,'success_msg'=>'余额不足!']);
                }
                 Db::startTrans();
                //更新账户余额
                
                 try{
                        Db::name('user')->where(['id'=>$uid])->setDec('money',$money);

                        $pay_type = $data['pay_type'] = input('pay_type',1);
                        if (!$money && !$uid && $pay_type){
                                return json(['error'=>1,'success_msg'=>'参数缺失']);
                        }

                        $add_data = [
                                        'uid'=>$data['uid'],
                                        'money'=>$data['money'],
                                        'pay_type'=>$data['pay_type'],
                                        'status'=>0,
                                        'alipay'=>$user['ali_user'],
                                        'time'=>time()
                        ];
                        $re = Db::name('tixian')->insert($add_data,false,true);
                        if ($re){
                                Db::commit();
                                return json(['error'=>0,'success_msg'=>'申请成功,请等待后台人员审核']);
                        }else {
                                Db::rollback();
                                return json(['error'=>1,'success_msg'=>'申请失败,请稍后再试']);
                        }
                 }catch(think\Exception $e){
                     Db::rollback();
                     return json(['error'=>1,'success_msg'=>'异常错误：'.$e->getMessage()]);
                 }
		
	}
	
	//我的账单
	public function getMyBillTop10()
	{
                if (!request()->isPost()){return json(['error'=>1,'success_msg'=>'请求类型有误']);}
                 
                $uid = input('uid');
                if (!$uid){
                        return json(['error'=>1,'success_msg'=>'参数缺失']);
                }

                $temp = ['1'=>'充值','2'=>'提现','3'=>'余额支付','4'=>'余额入账','5'=>'支付宝支付','6'=>'微信支付'];
                $lists = Db::name('logs')
                        ->where(['uid'=>$uid])
                        ->order('id desc')
                        ->field('')
                        ->limit(10)
                        ->select();
                foreach($lists as $k=>$v){
                    $lists[$k]['title'] = $temp[$v['action']].$v['fee'].'元';
                    $lists[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
                    $lists[$k]['money'] = in_array($v['action'],[1,4])?'+'.$v['fee']:'-'.$v['fee'];
                }
                return json(['error'=>0,'success_msg'=>$lists]);
	}
	
	//查看全部账单
	public function getMyBillAll()
	{
                if (!request()->isPost()){return json(['error'=>1,'success_msg'=>'请求类型有误']);}
                
                $uid = input('uid');
                if (!$uid){
                        return json(['error'=>1,'success_msg'=>'参数缺失']);
                }
                $page = input('page',1);
                $where['uid'] = $uid;
                $from = input('from','');
                $to   = input('to','');
                $unix_from = strtotime(input('from').' 00:00:00');
                $unix_to   = strtotime(input('to').' 23:59:59');
                if (!empty($from) && !empty($to)){
                        $where['ctime'] = ['between',[$unix_from,$unix_to]];
                }
                
                $lists = Db::name('logs')
                        ->where($where)
                        ->order('id desc')
                        ->paginate(config('paginate.list_rows'), false, ['page' => $page]);
                $lists = $lists->toArray();
                $lists = $lists['data'];
                
                $temp = ['1'=>'充值','2'=>'提现','3'=>'余额支付','4'=>'余额入账','5'=>'支付宝支付','6'=>'微信支付'];
                
                foreach($lists as $k=>$v){
                    $lists[$k]['title'] = $temp[$v['action']].$v['fee'].'元';
                    $lists[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
                    $lists[$k]['money'] = in_array($v['action'],[1,4])?'+':'-'.$v['fee'];
                }
                return json(['error'=>0,'success_msg'=>$lists]);
	}
	
	
	
public function alipay(){
        vendor('ali.aop.AopClient');
        vendor('ali.aop.AlipayTradeAppPayRequest');
            
        //file_put_contents('zhifu.txt',json_encode($_REQUEST));
        // 获取参数
         $uid      = input('uid');
         $total    = input('total_fee');
         $paytype  = input('type',1);//1充值，2支付定金，3支付余款，4支付全款
         $payment  = input('payment',1);//1=线上全款，2=线上定金
         $order_id = 0;
         
         $user = model('user')->where(['id'=>$uid])->find();
         if(!$user){
             exit(json_encode(['error'=>1,'success_msg'=>'用户不存在']));
         }
//         if(!is_numeric($total)){
//             exit(json_encode(['error'=>1,'success_msg'=>'金额必须为数字']));
//         }
         if($paytype != 1){
            $order_id = input('order_id');
            $order = model('order')->where(['id'=>$order_id])->find();
            if(!$order){
                exit(json_encode(['error'=>1,'success_msg'=>'订单不存在!']));
            }
            if($uid != $order['uid']){
                exit(json_encode(['error'=>1,'success_msg'=>'非订单货主!']));
            }
            
            switch($paytype){
                case 2:$total = $order['pre_fee'];break;
                case 3:$total = round($order['post_price']-$order['pre_fee'],2);break;
                case 4:$total = $order['post_price'];break;
            }
         }
        $total = round($total,2);
        $out_trade_no = date('YmdHis', time()).rand(1000,9999);
        $aop = new \AopClient;
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2019022263300312";
        $aop->rsaPrivateKey = "MIIEowIBAAKCAQEA1zI3S1le7BWvPj2LGqGcweO/fJIgwgWDpPOHnvEzpGGToDDn7V6H/Tn4gFTJO5ZutcrgpWjhowPsBPIdKq3ne74Eg3iENyRMgEU3N++fCPAgincy3a/Ic4PRJWrYXdvAWiTPf/sESCuwPy5uKsuvjtFcRkHRHrvx3B9dOS1Yz2E0V8gsFJV6f7+hH25GigzktBZDQnQQKUyoE7LKkvJ2dVgOPgKux/TSoBwtG0S44k5B7yCUYNC5QfNoBYwXIM3ApCj/h12AjZoCV3bYykTJ1i5LT6AFAagtrrWtaZzJoSpleqkdMYGecXXTzlpy0fTp7oBFzylvFXCNAvVxBuVV0QIDAQABAoIBAHC0qCkagZB8OvAKI5SrGAKkWWHQ1r9HTA9UTK99/GIXiM3ZT18Op4KEnhX4UfyXaRxlSQrYx3QtVauxcn0r35T7jDmfIQAQTtDPb4AoS87OlPxwOX/J1N5LJ3rFXtSphzCvHs4UlcWGvCQbGS/oxeWy2PUi+3dxT9bNxnFvwpe5hqTwcmUfrwKUph7QOkvgE9yJ1bA5BC7wHDAzkw7jZ3GNj5pEd+dXoocQkT+ThKNDk3axUpYuu1BpFt95nTGpjPAyR+XjFrRbsLZCAeiWeXUNHYslKHG7v/W4tot3Y8lpVoACfmMAHiX34YtSudwhDMSGLlz4N4szsQDPCkbPyBECgYEA/fizUPucicKL/QSohZWHIY2klUywz3Kop761LDDD9F5c21eG5+QNA6LF9G58Y3m0mb0GOVzUDsnv0Lu+KpKhCPM3FZEPhFo6P0Fo/jDea2KZZIGzArWAxsIjOEAQ9MiH8IPRMmgcXmuBXsd/XdAX5tjY2C/6mdWpZJ3lph+Vg8MCgYEA2Oo7IxLe9FyiOmESehICjqONcBH+peDxL8tmqt2GM2l+KJvt7tapth5HkiTiy1GvxeSPaX0Kd7Lm7SyfrWdPqa+yovzBlcHPet/5zf186F/m+84dT+6f+dAEPXZLAlYLrgZ/n1O2zlATXj1y5SnCNwrkWzDt8Q7jBUb1orRICtsCgYEAvGMPeCG7gwBZYKxk6Pn6i6wKimihuq99ZTh0ITxnDkw0qsspGbey46CJIAAYqp0rluJ8zG4MKpAmIHoy3Fd5ti7bDFdW4EEhdaxqWisbwSIZUFFY3MiPpl95So9TRI+/LmxI8BAEAxHRm+F1m7vxDt6JMwub4yJ3DViGcYx6FosCgYAFIi36G4U5RUP1m7yDNQ/obkh3oYVkSZmLpwWr/4ggiVuZ/G66G0xy6e6Jr+PKKgm95jXaqkHoKUy7yjWRNgO1oVnZ759pOuf9IXXZASnGmhZuWSdqb9xLXjfreHQ3/u9R1AeLLsEByvVaYpvofn+BK/nRiqvm65JF2rMPgbK+xwKBgEFhhF3adiMVmbeRvFGKS6iknwJhkYzIn9A0AIvX3fem2pHztc8Dr2faAgMRk+v4TU4QketeYTBt3EZQ1SLUa92CN/ImWtQnaTHcDZl8t6JyBl09a/4qeqG+IqZ3s0h+Z0YcwNuSny2KVHaMIHcBGQQ9z1cNA8p1eUHHIUlpsn5q";
        $aop->alipayrsaPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAizASEyssfgTk5St0ZqcuFG3p3cyKobbQl/BTVzGYV9jcLv1JVEla6El3Ps+K3Yym2hJVPKThuEOhuFbWpQGIZmrDHRukxAq9h5ikRVCyl4L9CGm7g8VIMY5EBN45jvz3+PwBn08NNzSSwaw+DmdWRZ3h2uTXblbfhvgr8IaFdPP1I+CKyVrldrq1MBv4mORMguw1Y513oJT7eJlalXiDn1RkkWX0h4Y39rh/RqdMvC6JP0tFUR7Gl4Jl83jqIMT558PvOb0ehEVo4V51PE4QbFFFZYtBLoHmUToDwEjTKwQW5DHly4ZEodPu/tzAWl8pQQCRCVtANwRcogQlEdgrqwIDAQAB";
        $aop->format   = "json";
        $aop->charset  = "UTF-8";
        $aop->signType = "RSA2";
         
        //$aop->alipayrsaPublicKey = '请填写支付宝公钥，一行字符串';
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new \AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{\"body\":\"支付\"," 
                        . "\"subject\": \"智慧物流\","
                        . "\"out_trade_no\": \"$out_trade_no\","
                        . "\"timeout_express\": \"30m\"," 
                        . "\"total_amount\": \"$total\","
                        . "\"product_code\":\"QUICK_MSECURITY_PAY\""
                        . "}";
        
        $notify_url = 'http://huoyun.vanshung.com/ali_notify';
        $request->setNotifyUrl($notify_url);
        $request->setBizContent($bizcontent);
        
        //支付订单数据
        $pay_data = [
            'order_sn' => $out_trade_no,
            'type'     => $paytype,
            'money'    => $total,
            'status'   => 0,
            'time'     => time(),
            'user_id'  => $uid,
            'pay_from' => 'alipay',
            'ex_id'    => 1,
            'order_id' => $order_id,
            'payment'  => $payment
        ];
        
       Db::name('pay')->insert($pay_data);
        
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
        exit(json_encode(['error'=>0,'success_msg'=>$response]));//就是orderString 可以直接给客户端请求，无需再做处理。
    }
	
	
	
	
    //支付宝通知
    public function alipay_notify(){
        //error_reporting(E_ALL);
        
       // file_put_contents('alipay_log.txt', json_encode($_REQUEST));
        
        
            
            if($_POST['trade_status'] == 'TRADE_SUCCESS'){
                $out_trade_no = $_POST['out_trade_no'];
                $money  = $_POST['total_amount'];
                $trade_no = $_POST['trade_no'];
                $buyer_logon_id = $_POST['buyer_logon_id'];
                $notify_time  = $_POST['notify_time'];

                $money = round($money,2);
                $where = ['order_sn' => $out_trade_no];
                $pay_order = Db::name('pay')->where($where)->find();

                if($pay_order['status'] && $pay_order['trade_no']){
                    exit;
                }
                
                Db::startTrans();
                try{
                if($pay_order){
                  $notify_data = [
                       'status' => 1,
                       'trade_no'=>$trade_no,
                       'buyer_logon_id'=>$buyer_logon_id,
                       'notify_time' => $notify_time
                   ];
                   Db::name('pay')->where($where)->update($notify_data);
                   //file_put_contents('alipay_sql.txt', Db::name('pay')->getLastSql());
                   if($pay_order['type'] == 1){//充值逻辑
                       //file_put_contents('alipay_1.txt', 'aaaa');
                        //用户账户加钱
                        Db::name('user')->where(['id'=>$pay_order['user_id']])->setInc('money',$money);

                         //更新交易记录
                        $log_data = [
                            'uid'    => $pay_order['user_id'],
                            'fee'    => $money,
                            'ctime'  => time(),
                            'remark' => '支付宝充值',
                            'action' => 1,
                            'payid'  => $pay_order['id']
                        ];
                        Db::name('logs')->insert($log_data);
                   }else{
                       
                       //file_put_contents('alipay_2.txt', 'aaaa');
                       $payment = 0;
                       switch($pay_order['type']){
                           case 2:$paid = 2;$payment=2;$remark="支付宝支付订单定金";break;
                           case 3:$paid = 4;$payment=2;$remark='支付宝支付订单尾款';break;
                           case 4:$paid = 4;$payment=1;$remark='支付宝支付订单全款';break;
                       }
                       //更新订单支付状态,以及支付方式
                       Db::name('order')->where(['id'=>$pay_order['order_id']])->update(['paid'=>$paid,'payment'=>$payment]);
                       
                       $order = Db::name('order')->where(['id'=>$pay_order['order_id']])->find();
                       //司机更新账户
                       Db::name('user')->where(['id'=>$order['driver_id']])->setInc('frozen',$money);
                       
                       //更新交易记录
                        $log_data = [
                            'uid'    => $pay_order['user_id'],
                            'fee'    => $money,
                            'ctime'  => time(),
                            'remark' => $remark,
                            'action' => 5,
                            'payid'  => $pay_order['id']
                        ];
                        Db::name('logs')->insert($log_data);
                }}else{
                    //更新交易记录
                        $log_data = [
                            'uid'    => 0,
                            'fee'    => $money,
                            'ctime'  => time(),
                            'remark' => '未查到订单，支付失败;buyer_logon_id:'.$buyer_logon_id.';out_trade_no:'.$out_trade_no.';total_amount:'.$money,
                            'action' => 0,
                            'payid'  => 0
                        ];
                        Db::name('logs')->insert($log_data);
                }
                // 提交事务
                Db::commit();  
             } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
               
            }else{
                //支付失败
                $notify_data = [
                    'status' => 2,
                    'trade_no'=>$trade_no,
                    'buyer_logon_id'=>$buyer_logon_id,
                    'notify_time' => $notify_time,
                    'note'=> '支付失败，通知异常'
                ];
                Db::name('pay')->where($where)->udpate($notify_data);
            }
            
          
    }
	
    
    //余额支付货款
    public function balance(){
        if(!request()->isPost()){
            exit(json_encode(['error'=>1,'success_msg'=>'请求方式错误!']));
        }
         $uid = input('uid');
         $order_id = input('order_id');
         $payment  = input('payment',2);
         
         $user  = Db::name('user')->where(['id'=>$uid])->find();
         $order = Db::name('order')->where(['id'=>$order_id])->find();
         
         if($order['uid'] != $uid){
               exit(json_encode(['error'=>1,'success_msg'=>'非该订单货主!']));
         }
         
         if(in_array($order['status'],[0,5,6])){
              exit(json_encode(['error'=>1,'success_msg'=>'订单状态不可支付!']));
         }
         
         $payfee = 1;//$order['total_fee'];
         $paid   = 1;//订单支付状态
         if($order['paid'] == 0){
             if($payment == 1){
                 $paid   = 4; 
                 $payfee = $order['total_fee'];
             }else if($payment == 2){
                 $paid = 2;
                 $payfee = $order['pre_fee'];
             }else if($payment == 3){
                 exit(json_encode(['error'=>1,'success_msg'=>'线下订单，无需在线支付!']));
             }
         }elseif($order['paid'] == 3){
             $payment = 2;
             $paid = 4;
             $payfee = round($order['total_fee'] - $order['pre_fee'],2);
         }elseif($order['paid'] == 1){
             exit(json_encode(['error'=>1,'success_msg'=>'订单已支付!']));
         }else{
             exit(json_encode(['error'=>1,'success_msg'=>'订单暂时无法支付，等待司机操作!']));
         }
         
         //用户账户余额
         if($user['money'] < $payfee){
             exit(json_encode(['error'=>1,'success_msg'=>'余额不足!']));
         }
         
         //开启事物处理
         Db::startTrans();
         
         try{
             //扣除账户金额
            Db::name('user')->where(['id'=>$uid])->setDec('money',$payfee);
            //司机账户冻结金额
            Db::name('user')->where(['id'=>$order['driver_id']])->setInc('frozen',$payfee);
            
            //更新订单支付状态
            Db::name('order')->where(['id'=>$order_id])->update(['paid'=>$paid,'pay_type'=>3,'payment'=>$payment,'paid_time'=>time()]);
            
            //添加支付记录
            $log = [
                'uid' => $uid,
                'action'=> 3,
                'fee' => $payfee,
                'ctime' => time(),
                'remark'=> '余额支付订单',
                'order_id' => $order_id
            ];
            Db::name('logs')->insert($log);
            
            Db::commit();
            
            exit(json_encode(['error'=>0,'success_msg'=>'支付成功']));
         }catch(\think\Exception $e){
            Db::rollback();    
            return json(['error'=>0,'success_msg'=>$e->getMessage()]);
         }
         
    }
	
}