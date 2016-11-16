<?php

include __DIR__ . '/sign.php';

//数据库ORM
use Illuminate\Database\Capsule\Manager as DB;
//输入过滤器
use Illuminate\Http\Request;


class Activities extends Sign{

    public function listAll(Request $request){
        $lists = DB::table('activities')->orderBy('id','DESC')->paginate();
        if(count($lists)){
            $message = '获取详情信息成功';
        }else{
            $message = '暂无数据';
        }
        $data = ['errcode' => 200, 'message' => $message, 'data'=>$lists];
        $this->response($data);
    }

    public function listLive(Request $request){
        $lists = DB::table('activities')->where('active_status',0)->orderBy('id','DESC')->paginate();
        if(count($lists)){
            $message = '获取详情信息成功';
        }else{
            $message = '暂无数据';
        }
        $data = ['errcode' => 200, 'message' => $message, 'data'=>$lists];
        $this->response($data);
    }

    public function details(Request $request){
        $active_id = intval($request->input('active_id'));
        $oneInfo = DB::table('activities')->where('id',$active_id)->first();
        dd($oneInfo);
    }

    /*
     * 报名
     */
    public function join(Request $request){
        $active_id = intval($request->input('active_id'));
        $num = intval($request->input('num'));
        if(!$num) $num = 1;
        $user_id = intval($request->input('user_id'));

        $live = DB::table('activities')->where('id',$active_id)->first();
        if(!$live){
            $data = [
                'errcode' => 404,
                'message' => '没有找到对应活动信息',
                'data'=>$this->obj
            ];
            $this->response($data);
        }
        if($live->active_status != 0){
            $data = [
                'errcode' => 3000,
                'message' => '该活动报名已结束',
                'data'=>$this->obj
            ];
            $this->response($data);
        }
        $has_join = DB::table('activity_users')->where(['active_id'=>$active_id,'user_id'=>$active_id])->first();
        if($has_join){
            $res = DB::table('activity_users')->where(['id'=>$has_join->id])->update(['num'=>$num]);
        }else{
            $res = DB::table('activity_users')->insert(compact('active_id','num','user_id'));
        }
        if($res){
            $data = ['errcode' => 200, 'message' => '报名成功', 'data'=>$this->obj ];
        }else{
            $data = ['errcode' => 500, 'message' => '报名失败', 'data'=>$this->obj ];
        }
        $this->response($data);
    }

    /*
     * 取消报名
     */
    public function cancel(Request $request){
        $active_id = intval($request->input('active_id'));
        $user_id = intval($request->input('user_id'));
        $res = DB::table('activity_users')->where(['active_id'=>$active_id,'user_id'=>$user_id])->delete();
        if($res){
            $data = ['errcode' => 200, 'message' => '取消报名成功', 'data'=>$this->obj ];
        }else{
            $data = ['errcode' => 500, 'message' => '取消报名失败', 'data'=>$this->obj ];
        }
        $this->response($data);
    }
}

$activities = new Activities();

//参数路由
$request = Request::capture();
$act = $request->input('act');
switch($act){
    case 'listAll': $activities->listAll($request);          break;
    case 'listLive': $activities->listLive($request);        break;
    case 'details': $activities->details($request);          break;
    case 'join': $activities->join($request);                break;
    case 'cancel': $activities->cancel($request);            break;
    default: $activities->response();
}
