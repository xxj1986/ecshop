<?php

// 载入初始化文件
include __DIR__ . '/aip_init.php';

//数据库ORM
use Illuminate\Database\Capsule\Manager as DB;
//输入过滤器
use Illuminate\Http\Request;
//图片验证码
use Gregwar\Captcha\CaptchaBuilder;

//定义认证类
class Auth{
    protected $dev; //设备ID
    protected $redis;

    public function __construct()
    {
        //捕获输入信息
        $request = Request::capture();
        //定义错误消息
        $res = ['errcode'=>1001,'message'=>'参数错误！'];
        $this->dev = isset($_REQUEST['device'])?trim($_REQUEST['device']):
            function($res){$this->response($res);};
        //连接redis
        $redis = new Redis();
        $redis->connect(REDIS_HOST,REDIS_PORT);
        $this->redis = $redis;

        //参数路由
        $act = $request->input('act');
        switch($act){
            case 'login': $this->login($request);          break;
            case 'logout': $this->logout($request);        break;
            case 'register': $this->register($request);    break;
            case 'confirm': $this->confirm($request);      break;
            case 'createCaptcha': $this->createCaptcha();  break;
            default: $this->response();
        }

    }

    /*
     * 登录
     */
    private function login(Request $request){
        //获取用户名和密码
        $user_mobile = $request->input('mobile_phone');
        $password = $request->input('password');
        //获取
        $user = DB::table('users')->where('mobile_phone',$user_mobile)->first();
        //var_dump($user);die();
        if( $user && md5($password) == $user->password ){
            $token = str_random(32);
            //绑定user_id和device
            $this->redis->hset('id2dev', $user->user_id, $this->dev);
            $this->redis->hset('dev2id', $this->dev, $user->user_id);
            //保存token,设置超时时间20秒
            $this->redis->set('token'.$this->dev, $token);
            $this->redis->expire('token'.$this->dev, 20*60);
            //更新最后登陆时间
            //$this->db->table('users')->where('user_id',$user->user_id)->update(['last_login'=>time()]);
            DB::table('users')->where('user_id',$user->user_id)->update(['last_login'=>time()]);
            //返回user_id,token
            $this->response(['errcode'=>0,'message'=>'登录成功','user_id'=>$user->user_id,'token'=>$token]);
        }else{
            $this->response(['errcode'=>2002,'message'=>'账号或密码错误！']);
        }
    }

    /*
     * 退出
     */
    private function logout(Request $request){
        //获取签名
        $sign = $request->input('sign');
        if(!$sign){
            $this->response(['errcode'=>1001,'message'=>'参数错误！']);
        }
        //检查签名
        $this->checkSign($request);
        //删除token
        $this->redis->del('token'.$this->dev);
        $this->response(['errcode'=>0,'message'=>'退出成功']);
    }

    private function checkSign(Request $request){

        //判断是否已经登陆或超时退出
        $token = $this->redis->get('token'.$this->dev);
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
        $sign = $request->input('sign');
        if($sign != $correctSign){
            $this->response(['errcode'=>1002,'message'=>'数字签名错误！']);
        }
    }

    /*
     * 注册
     */
    private function register(Request $request){

        $mobile_phone = $request->input('mobile_phone');
        $password = $request->input('password');
        $captcha = $request->input('captcha');
        //检查验证码
        if(!$captcha){
            $this->response(['errcode'=>2008,'message'=>'请输入验证码']);
        }
        $cap = $this->redis->get('captcha'.$this->dev);
        if($captcha != $cap){
            $this->response(['errcode'=>2009,'message'=>'验证码错误']);
        }
        //检查手机号
        if(!$mobile_phone){
            $this->response(['errcode'=>2004,'message'=>'请输入手机号']);
        }
        /*
        $match = '/^((13[0-9])|(15[^4,\d])|(18[0,5-9]))[0-9]{8}$/';
        if(!preg_match($match, $mobile_phone)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确']);
        }*/
        $userInfo = DB::table('users')->where('mobile_phone',$mobile_phone)->first();
        if($userInfo){
            $this->response(['errcode'=>2006,'message'=>'该手机号已注册']);
        }
        //检查密码
        if(!$password){
            $this->response(['errcode'=>2007,'message'=>'请输入密码']);
        }
        // 存redis并设置过期时间
        $this->redis->set('regMobile'.$this->dev, $mobile_phone);
        $this->redis->set('regPass'.$this->dev, md5($password));
        $this->redis->expire('regMobile'.$this->dev, 10*60);
        $this->redis->expire('regPass'.$this->dev, 10*60);

        //发送短信验证码
        $this->response(['errcode'=>0,'message'=>'提交信息成功']);
    }

    /*
     * 短信验证码确认
     */
    public function confirm(Request $request){
        $code = $request->input('code');
        if(!$code){
            $this->response(['errcode'=>2008,'message'=>'请输入短信验证码']);
        }
        $cache = [
            'mobile_phone' => $this->redis->get('regMobile'.$this->dev),
            'password' => $this->redis->get('regPass'.$this->dev),
        ];
        //验证是否超时
        if(!$cache['mobile_phone']){
            $this->response(['errcode'=>2,'message'=>'验证超时,请重新注册']);
        }
        $data = [
            'email' => '',
            'user_name' => str_random(16), //用户名
            'password' => '', //密码
            'question' => '',
            'answer' => '',
            'last_ip' => '',
            'alias' => '', //昵称
            'mobile_phone' => '', //手机号
            'credit_line' => 800000, // 最大消费
        ];
        $data['mobile_phone'] = $cache['mobile_phone'];
        $data['password'] = $cache['password'];
        //将信息保存到数据库
        $res = DB::table('users')->insert($data);
        if(!$res){
            $this->response(['errcode'=>2,'message'=>'注册失败']);
        }
        $this->response(['errcode'=>0,'message'=>'注册成功']);
    }

    /*
     * 创建验证码
     */
    public function createCaptcha(){
        //创建验证码
        $captcha = new CaptchaBuilder();
        $captcha->build();
        //将验证码保存到缓存
        $this->redis->set('captcha'.$this->dev, $captcha->getPhrase());
        $this->redis->expire('captcha'.$this->dev, 10*60);
        //输出验证码图片
        die ('<img src="'.$captcha->inline().'" />');
    }

    /*
     * 检测手机号
     */
    public function checkMobile($mobile){
        //考虑到安全，该接口暂时不开放。
        // 非法用户可能根据这个接口来探测注册的手机号。
    }

    /*
     * 输出函数
     */
    public function response($data=['errcode'=>1002,'message'=>'action not found']){
        header('Content-type:text/json');
        die(json_encode($data));
    }
}

$auth = new Auth();


