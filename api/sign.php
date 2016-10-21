<?php

// 载入初始化文件
include __DIR__ . '/aip_init.php';

use Illuminate\Http\Request;

//定义认证类
class Sign{
    protected $dev; //设备ID
    protected $redis;

    public function __construct()
    {
        //连接redis
        $redis = new Redis();
        $redis->connect(REDIS_HOST,REDIS_PORT);
        $this->redis = $redis;
    }
    /*
     * 验证
     */
    public function checkSign(){
        //捕获输入信息
        $request = Request::capture();
        $dev = $request->input('device');
        $sign = $request->input('sign');
        /*
         * App登录互斥
         * 说明：如果传输了user_id,一个账户只能在一个设备登录
         * 如果不传user_id，那么可以多个设备同时登录账户
         */
        $user_id = intval($request->input('user_id'));
        if($user_id){
            $onlineDev = $this->redis->hget('id2dev',$user_id);
            if($dev !== $onlineDev){
                $this->response(['errcode'=>1,'message'=>'您已在其他设备登录']);
            }
        }
        if(!$dev || !$sign){
            $this->response(['errcode'=>1001,'message'=>'参数错误！']);
        }
        //判断是否已经登陆或超时退出
        $token = $this->redis->get('token'.$dev);
        if(!$token || strlen($token) != 32){
            $this->response(['errcode'=>2001,'message'=>'请重新登录']);
        }
        //计算签名
        $data = $request->except('sign'); // 获取数据，排除签名
        $data['token'] = $token; // 加入token
        foreach($data as $k=>$v) $data[$k] = $k.'='.$v; //内容变成key=value
        ksort($data); // 按照key升序排序
        $str = implode('&',$data); // 序列化
        $correctSign = md5($str); // md5哈希
        //判断签名
        if($sign != $correctSign){
            $this->response(['errcode'=>1,'message'=>'数字签名错误！']);
        }
        //更新过期时间
        $this->redis->expire('token'.$dev, 20*60);
    }

    /*
     * 输出函数
     */
    public function response($data=['errcode'=>1002,'message'=>'action not found']){
        header('Content-type:text/json');
        die(json_encode($data));
    }
}


