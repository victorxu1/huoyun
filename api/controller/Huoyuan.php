<?php
namespace app\api\controller;

use app\common\model\Member as MemberModel;
use app\common\model\Huoyuan as HuoyuanModel;
use app\common\model\Carlines as CarlinesModel;
use app\common\model\Partner as PartnerModel;
use think\Controller;
use think\Db;
use think\Session;
use app\common\controller\ApiBase;

/**
 * 用户相关接口
 * Class Upload
 * @package app\api\controller
 */
class Huoyuan extends ApiBase
{
    protected $member_model;
    protected $huoyuan_model;
    protected $partner_model;
    protected $carlines_model;
    protected function _initialize()
    {
        parent::_initialize();
        $this->member_model = new MemberModel();
        $this->huoyuan_model = new HuoyuanModel();
        $this->partner_model = new PartnerModel();
        $this->carlines_model = new CarlinesModel();
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

    //货源检索列表
    public function get_hylist(){
        if(!request()->isPost()){return json_encode(['error'=>2,'success_msg'=>'请求类型错误']);}
        
        $uid = input('uid');
        if(is_numeric($uid) && !empty($uid)){
            //屏蔽司机黑名单货主
            $black = Db::name('blacklist')->where(['uid'=>$uid])->column('buid');
            $map['hy.uid'] = ['not in',$black];
        }
        
        $startads1 = input('startads1');
        $endds1    = input('endds1');
        $car_long = input('car_long');
        
        $page = input('page',1);
        $map=['hy.status'=>0];
        if(!empty($startads1)){
            $arr = explode(',',$startads1);
            $startads1 = end($arr);
            $map['hy.startads1'] = $startads1;
        }
        if(!empty($endds1)){
            $arr = explode(',',$endds1);
            $endds1    = end($arr);
            $map['hy.endds1'] = $endds1;
        }
        if(!empty($car_long)){
            $map['hy.car_long'] = $car_long;
        }
        $list = $this->huoyuan_model->get_huoyuan_list($uid,$map,'hy.id desc',$page);
        if($list !== false){
            $result['error'] = 0;
            $result['success_msg'] = $list;
        }else{
            $result['error'] = 1;
            $result['success_msg'] = '获取数据错误';
        }
        
        return json($result);
    }

    //货源详情页
    public function view(){
        if($this->request->isPost()){
            $id = input('id');
            if(!$id){
                $result['error'] = 3;
                $result['success_msg'] = '缺失参数';
            }else{
                $map = ['hy.id'=>$id];
                $info = $this->huoyuan_model->get_huoyuan_info($map);
                if($info){
                    $result['error'] = 0;
                    $result['success_msg'] = '货源信息获取成功';
                    $result['data'] = $info;
                }else{
                    $result['error'] = 4;
                    $result['success_msg'] = '货源信息获取为空';
                }
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }
    

}