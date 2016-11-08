<?php

include __DIR__ . '/sign.php';

//数据库ORM
use Illuminate\Database\Capsule\Manager as DB;
//输入过滤器
use Illuminate\Http\Request;


class UserAddress extends Sign{

    public function listAll(Request $request){
        $user_id = intval($request->input('user_id'));
        $lists = DB::table('user_address')->where('user_id',$user_id)->get();
        if(count($lists)){
            $message = '获取收货人信息成功';
        }else{
            $message = '暂无数据';
            $lists = $this->obj;
        }
        $data = ['errcode' => 200, 'message' => $message, 'data'=>$lists];
        $this->response($data);
    }

    /*
     * 获取默认收货人信息
     */
    public function getDefault(Request $request){
        $user_id = intval($request->input('user_id'));
        $defInfo = DB::table('user_address')->where('user_id',$user_id)->where('is_default','y')->first();
        if($defInfo){
            $message = '获取默认收货人信息成功';
            $data = ['errcode' => 200, 'message' => $message, 'data'=>$defInfo];
        }else{
            $message = '您还没有设置默认收货人';
            $data = ['errcode' => 404, 'message' => $message, 'data'=>$this->obj];
        }
        $this->response($data);
    }


    /*
     * 设置默认地址
     */
    public function setDefault(Request $request){
        $user_id = intval($request->input('user_id'));
        $address_id = intval($request->input('address_id'));
        DB::table('user_address')->where('user_id',$user_id)->update(['is_default'=>'n']);
        $res = DB::table('user_address')->where('user_id',$user_id)->where('address_id',$address_id)->update(['is_default'=>'y']);
        if($res){
            $data = ['errcode' => 200, 'message' => '设置默认地址成功', 'data'=>['new_id'=>$address_id]];
        }else{
            $data = ['errcode' => 500, 'message' => '设置默认地址失败', 'data'=>$this->obj];
        }
        $this->response($data);
    }

    /*
     * 设置默认地址
     */
    public function delAddress(Request $request){
        $user_id = intval($request->input('user_id'));
        $address_id = intval($request->input('address_id'));
        $res = DB::table('user_address')->where('user_id',$user_id)->where('address_id',$address_id)->delete();
        if($res){
            $data = ['errcode' => 200, 'message' => '删除收货人信息成功', 'data'=>['del_id'=>$address_id]];
        }else{
            $data = ['errcode' => 500, 'message' => '删除收货人信息失败', 'data'=>$this->obj];
        }
        $this->response($data);
    }

    /*
     * 添加地址
     */
    public function addAddress(Request $request){
        //获取输入的信息
        $user_id = intval($request->input('user_id'));
        if(!$user_id){
            $this->response(['errcode'=>1001,'message'=>'参数错误','data'=>$this->obj ]);
        }
        $num = DB::table('user_address')->where('user_id',$user_id)->count();
        if($num >= 5){
            $this->response(['errcode'=>300,'message'=>'您最多只能保留5条收货人信息','data'=>$this->obj ]);
        }
        $setDef = trim($request->input('setDef'));
        //验证输入信息
        $data = $this->checkInput($request);
        //组合数据
        $data['is_default'] = 'n';
        $data['user_id'] = $user_id;
        if($setDef == 'y'){
            DB::table('user_address')->where('user_id',$user_id)->update(['is_default'=>'n']);
            $data['is_default'] = 'y';
        }
        //保存地址
        $res = DB::table('user_address')->insert($data);
        if($res){
            $data = ['errcode' => 200, 'message' => '新增收货人信息成功', 'data'=>$this->obj ];
        }else{
            $data = ['errcode' => 500, 'message' => '新增收货人信息失败', 'data'=>$this->obj ];
        }
        $this->response($data);
    }

    /*
     * 编辑地址
     */
    public function editAddress(Request $request){
        //验证输入信息
        $data = $this->checkInput($request);
        $address_id = intval($request->input('address_id'));
        if(!$address_id){
            $this->response(['errcode'=>1001,'message'=>'参数错误','data'=>$this->obj ]);
        }
        $res = DB::table('user_address')->where('address_id',$address_id)->update($data);
        if($res){
            $data = ['errcode' => 200, 'message' => '修改收货人信息成功', 'data'=>$this->obj ];
        }else{
            $data = ['errcode' => 500, 'message' => '收货人信息无修改', 'data'=>$this->obj ];
        }
        $this->response($data);
    }

    private function checkInput(Request $request){
        $mobile = trim($request->input('mobile'));
        $consignee = trim($request->input('name'));
        $address = trim($request->input('address'));
        if(!$mobile){
            $this->response(['errcode'=>302,'message'=>'请输入手机号','data'=>$this->obj ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>302,'message'=>'手机号不正确','data'=>$this->obj ]);
        }
        if(!$consignee){
            $this->response(['errcode'=>302,'message'=>'请输入收货人姓名','data'=>$this->obj ]);
        }
        if(!$address){
            $this->response(['errcode'=>302,'message'=>'请输入收货人地址','data'=>$this->obj ]);
        }
        return compact('consignee','mobile','address');
    }
}

$activities = new UserAddress();

//参数路由
$request = Request::capture();
$act = $request->input('act');
switch($act){
    case 'listAll': $activities->listAll($request);          break;
    case 'setDefault': $activities->setDefault($request);        break;
    case 'getDefault': $activities->getDefault($request);        break;
    case 'addAddress': $activities->addAddress($request);          break;
    case 'editAddress': $activities->editAddress($request);        break;
    case 'delAddress': $activities->delAddress($request);            break;
    default: $activities->response();
}
