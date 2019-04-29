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
use app\common\model\CarList as CarListModel;

/**
 * 用户相关接口
 * Class Upload
 * @package app\api\controller
 */
class Cars extends ApiBase
{
    protected $member_model;
    protected $huoyuan_model;
    protected $partner_model;
    protected $carlines_model;
    protected $carlist_model;
    protected function _initialize()
    {
        parent::_initialize();
        $this->carlist_model = new CarListModel();
        $this->member_model = new MemberModel();
        $this->huoyuan_model = new HuoyuanModel();
        $this->partner_model = new PartnerModel();
        $this->carlines_model = new CarlinesModel();
        $action = $this->request->action();
        $tokens = $this->request->post('tokens');
        if (!in_array($action,array('build_yzm1')) && (!$tokens || !$this->member_model->check_tokens($tokens))) {
            $result = [
                'error'   => 1,
                'success_msg' => '请先登录'
            ];
            die(json_encode($result,JSON_UNESCAPED_UNICODE));
        }
    }

    //车辆检索列表
    public function get_carlist(){
        if($this->request->isPost()){
            $startads1 = $this->request->post('startads1');
            $endds1 = $this->request->post('endds1');
            $car_weight = $this->request->post('car_weight');
            $page = $this->request->post('page')?$this->request->post('page'):1;
            $map = [];
            $startads1?$map['car.startads1'] = $startads1:'';
            $endds1?$map['car.endds1'] = $endds1:'';
            $car_weight?$map['car.car_weight'] = $car_weight:'';
            $list = $this->carlines_model->get_car_list($map,'car.id desc',$page);
            if($list){
                $result['error'] = 0;
                $result['success_msg'] = '车辆列表获取成功';
                $result['data'] = $list;
            }else{
                $result['error'] = 0;
                $result['success_msg'] = '车辆列表获取为空';
                $result['data'] = array();
            }
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

    //货源详情页
    public function view(){
        if($this->request->isPost()){
            $id = $this->request->post('id');
            $uid = $this->request->post('uid');
            if(!$id || !$uid){
                $result['error'] = 3;
                $result['success_msg'] = '缺失参数';
            }else{
                $map = ['car.id'=>$id];
                $info = $this->carlines_model->get_car_info($map);
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
    
     /**
     * 车辆当前位置
     */
    public function current_location() {
        if($this->request->isPost()){
            $uid = $this->request->post('uid');
            $city = $this->request->post('city');
            $lng = $this->request->post('lng');
            $lat = $this->request->post('lat');
            if (!$uid) {
                $result['error'] = 3;
                $result['success_msg'] = '参数缺失';
            } else {
                $car = model('DriverList')->where('uid', $uid)->find();
                if (!$car) {
                    $result['error'] = 4;
                    $result['success_msg'] = '车辆不存在';
                } else {
                    if(!$city) {
                        if($car) {
                            $result['error'] = 0;
                            $result['success_msg'] = '位置信息获取成功';
                            $result['data'] = ['location'=>$car['current_location'], 'location_time'=>$car['location_time'], 'lng'=>$car['lng'], 'lat'=>$car['lat']];
                        }
                    } else {
                        $car->current_location = $city;
                        $car->location_time = date("Y-m-d H:i:s");
                        $car->lng = $lng;
                        $car->lat = $lat;
                        $car->save();
                        $result['error']=0;
                        $result['success_msg'] = "位置更新成功";
                    }
                }
                
            }
            
        }else{
            $result['error'] = 2;
            $result['success_msg'] = '请求类型有误';
        }
        return json($result);
    }

}