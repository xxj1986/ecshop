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
        //定义错误消息
        $res = ['errcode'=>1001,'message'=>'参数错误！'];
        if(isset($_REQUEST['device'])){
            $this->dev = trim($_REQUEST['device']);
        }else{
            $this->response($res);
        }
        //连接redis
        $redis = new Redis();
        $redis->connect(REDIS_HOST,REDIS_PORT);
        $this->redis = $redis;
    }

    /*
     * 登录
     */
    public function login(Request $request){
        //获取用户名和密码
        $mobile = $request->input('mobile');
        $password = $request->input('password');
        //获取用户信息
        $user = DB::table('users')->where('mobile_phone',$mobile)->first();
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
            $this->response(['errcode'=>200,'message'=>'登录成功','data'=>['user_id'=>$user->user_id,'token'=>$token] ]);
        }else{
            $this->response(['errcode'=>2002,'message'=>'账号或密码错误！','data'=>[] ]);
        }
    }

    /*
     * 退出
     */
    public function logout(Request $request){
        //获取签名
        $sign = $request->input('sign');
        if(!$sign){
            $this->response(['errcode'=>1001,'message'=>'参数错误！','data'=>[] ]);
        }
        //检查签名
        $this->checkSign($request);
        //删除token
        $this->redis->del('token'.$this->dev);
        $this->response(['errcode'=>200,'message'=>'退出成功','data'=>[] ]);
    }

    public function checkSign(Request $request){

        //判断是否已经登陆或超时退出
        $token = $this->redis->get('token'.$this->dev);
        if(!$token || strlen($token) != 32){
            $this->response(['errcode'=>2001,'message'=>'请重新登录','data'=>[] ]);
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
            $this->response(['errcode'=>1002,'message'=>'数字签名错误！','data'=>[] ]);
        }
    }

    /*
     * 注册
     */
    public function register(Request $request){

        $mobile = $request->input('mobile');
        $password = $request->input('password');
        $sex = intval($request->input('sex'));
        $age = intval($request->input('age'));
        $profession = $request->input('profession'); //职业
        /*
        $captcha = $request->input('captcha');
        //检查验证码
        if(!$captcha){
            $this->response(['errcode'=>2008,'message'=>'请输入验证码','data'=>[] ]);
        }
        $cap = $this->redis->get('captcha'.$this->dev);
        if($captcha != $cap){
            $this->response(['errcode'=>2009,'message'=>'验证码错误','data'=>[] ]);
        }*/
        $code = $request->input('code');
        //检查验证码
        if(!$code){
            $this->response(['errcode'=>2008,'message'=>'请输入验证码','data'=>[] ]);
        }
        $regCode = $this->redis->get('regCode'.$mobile);
        if($code != $regCode){
            //$this->response(['errcode'=>302,'message'=>'验证码不正确','data'=>[] ]);
        }
        //检查手机号
        if(!$mobile){
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>[] ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>[] ]);
        }
        $userInfo = DB::table('users')->where('mobile_phone',$mobile)->first();
        if($userInfo){
            $this->response(['errcode'=>2006,'message'=>'该手机号已注册','data'=>[] ]);
        }
        //检查密码
        if(!$password){
            $this->response(['errcode'=>2007,'message'=>'请输入密码','data'=>[] ]);
        }
        /*
        // 存redis并设置过期时间
        $this->redis->set('regMobile'.$this->dev, $mobile);
        $this->redis->set('regPass'.$this->dev, md5($password));
        $this->redis->expire('regMobile'.$this->dev, 10*60);
        $this->redis->expire('regPass'.$this->dev, 10*60);

        //发送短信验证码
        $this->response(['errcode'=>200,'message'=>'提交信息成功','data'=>[] ]);
        */

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
            'sex' => $sex,
            'age' => $age ? $age : '',
            'profession' => $profession ? $profession : '', //职业
        ];
        $data['mobile_phone'] = $mobile;
        $data['password'] = md5($password);
        //将信息保存到数据库
        $res = DB::table('users')->insert($data);
        if(!$res){
            $this->response(['errcode'=>2,'message'=>'注册失败','data'=>[] ]);
        }
        //$this->response(['errcode'=>200,'message'=>'注册成功','data'=>[] ]);
        $this->login($request); //注册后直接登录
    }

    /*
     * 短信验证码确认
     */
    public function confirm(Request $request){
        $code = $request->input('code');
        if(!$code){
            $this->response(['errcode'=>2008,'message'=>'请输入短信验证码','data'=>[] ]);
        }
        $cache = [
            'mobile_phone' => $this->redis->get('regMobile'.$this->dev),
            'password' => $this->redis->get('regPass'.$this->dev),
        ];
        //验证是否超时
        if(!$cache['mobile_phone']){
            $this->response(['errcode'=>2,'message'=>'验证超时,请重新注册','data'=>[] ]);
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
            $this->response(['errcode'=>2,'message'=>'注册失败','data'=>[] ]);
        }
        $this->response(['errcode'=>200,'message'=>'注册成功','data'=>[] ]);
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
     * 创建短信验证码
     */
    public function createSmsCode(Request $request){
        $mobile = trim($request->input('mobile'));
        $type = trim($request->input('type'));
        //检查手机号
        if(!$mobile){
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>[] ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>[] ]);
        }
        $timer = $this->redis->get('codeTimer'.$mobile);
        if($timer){
            $sec = time() - $timer + 1;
            $this->response(['errcode'=>300,'message'=>'发送频率过快，请于'.$sec.'后再试','data'=>[] ]);
        }
        $code = rand(100000,999999);
        switch($type){
            case 'register' :
                $message = "您的注册验证码是$code,10分钟内输入有效";
                $this->redis->set('regCode'.$mobile,$code);
                $this->redis->expire('regCode'.$mobile, 10*60);
                break;
            case 'reset' :
                $message = "您重置密码的证码是$code,10分钟内输入有效";
                $this->redis->set('rstCode'.$mobile,$code);
                $this->redis->expire('rstCode'.$mobile, 10*60);
                break;
            default:
                $message = "您的证码是$code,10分钟内输入有效";
                $this->redis->set('defCode'.$mobile,$code);
                $this->redis->expire('defCode'.$mobile, 10*60);
        }
        //发送短信验证码
        $res = true;
        if($res){
            //设置计数器
            $this->redis->set('codeTimer'.$mobile,time());
            $this->redis->expire('codeTimer'.$mobile, 60); //限制每个手机号发送频率为60秒一次
            $this->response(['errcode'=>200,'message'=>'该功能暂时没有实现，请随意填写个手机验证码','data'=>[] ]);
        }
        else $this->response(['errcode'=>500,'message'=>'发送短信验证码失败','data'=>[] ]);
    }

    /*
     * 检测手机号
     */
    public function checkMobile(Request $request){
        $mobile = $request->input('mobile');
        //检查手机号
        if(!$mobile){
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>[] ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>[] ]);
        }
        $userInfo = DB::table('users')->where('mobile_phone',$mobile)->first();
        if($userInfo){
            $this->response(['errcode'=>2006,'message'=>'该手机号已注册','data'=>[] ]);
        }
        $this->response(['errcode'=>200,'message'=>'该手机号未注册','data'=>[] ]);
    }

    /*
     * 重置密码
     */
    public function resetPassword(Request $request){
        $mobile = trim($request->input('mobile'));
        $code = trim($request->input('code'));
        $password = trim(strval($request->input('password')));

        //检查手机号
        if(!$mobile){
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>[] ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>[] ]);
        }
        if(!$password){
            $this->response(['errcode'=>302,'message'=>'请输入密码','data'=>[] ]);
        }
        if(strlen($password) < 6){
            $this->response(['errcode'=>302,'message'=>'密码长度至少为6位','data'=>[] ]);
        }
        //检查验证码
        if(!$code){
            $this->response(['errcode'=>2008,'message'=>'请输入验证码','data'=>[] ]);
        }
        $regCode = $this->redis->get('rstCode'.$mobile); //获取重置验证码
        if($code != $regCode){
            //$this->response(['errcode'=>302,'message'=>'验证码不正确','data'=>[] ]);
        }
        $userInfo = DB::table('users')->where('mobile_phone',$mobile)->first();
        if(!$userInfo){
            $this->response(['errcode'=>300,'message'=>'该手机号未注册','data'=>[] ]);
        }
        $newPwd = md5($password);
        if($newPwd == $userInfo->password){
            $this->response(['errcode'=>200,'message'=>'修改密码成功','data'=>[] ]);
        }
        $res = DB::table('users')->where('mobile_phone',$mobile)->update(['password'=>$newPwd]);
        if($res){
            $this->redis->del('rstCode'.$mobile);
            $this->response(['errcode'=>200,'message'=>'修改密码成功','data'=>[] ]);
        }
        $this->response(['errcode'=>300,'message'=>'修改密码失败','data'=>[] ]);
    }

    /*
     * 输出函数
     */
    public function response($data=['errcode'=>1002,'message'=>'action not found','data'=>[] ]){
        header('Content-type:text/json');
        die(json_encode($data));
    }
}

$auth = new Auth();

//参数路由
$request = Request::capture();
$act = $request->input('act');
switch($act){
    case 'login': $auth->login($request);          break;
    case 'logout': $auth->logout($request);        break;
    case 'register': $auth->register($request);    break;
    case 'createSmsCode': $auth->createSmsCode($request);      break;
    case 'resetPassword': $auth->resetPassword($request);      break;
    case 'createCaptcha': $auth->createCaptcha();  break;
    default: $auth->response();
}


