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
    protected $obj;

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

        $this->obj = new \stdClass();
    }

    /*
     * 登录
     */
    public function login(Request $request){
        //获取用户名和密码
        $mobile = $request->input('mobile');
        $password = $request->input('password');
        //微信登录
        $openid = $request->input('openid');
        $accessToken = $request->input('accessToken');
        //获取微信用户信息
        if($accessToken && $openid){
            $userInfo = json_decode( $this->getWechatUser($accessToken,$openid),true);
            $valid_openID = isset($userInfo['openid']) ? $userInfo['openid'] : false;
        }
        else $valid_openID = false;
        //获取用户信息
        if($valid_openID){
            $user = DB::table('users')->where('openid',$valid_openID)->first();
            if(!$user){ //如果不存在微信用户，则直接注册
                $this->register($request);
            }
        }else{
            $user = DB::table('users')->where('mobile_phone',$mobile)->where('password',md5($password))->first();
        }
        if($user){
            $token = str_random(32);
            //绑定user_id和device
            $this->redis->hset('id2dev', $user->user_id, $this->dev);
            $this->redis->hset('dev2id', $this->dev, $user->user_id);
            //保存token
            $this->redis->set('token'.$this->dev, $token);
            //更新最后登陆时间
            //$this->db->table('users')->where('user_id',$user->user_id)->update(['last_login'=>time()]);
            DB::table('users')->where('user_id',$user->user_id)->update(['last_login'=>time()]);
            //返回user_id,token
            $this->response(['errcode'=>200,'message'=>'登录成功',
                'data'=>['user_id'=>$user->user_id,'token'=>$token,'pattern_on'=>$user->pattern_on,'openid'=>$user->openid ]
            ]);
        }else{
            if($openid)
                $this->response(['errcode'=>2002,'message'=>'未找到对应注册用户','data'=>$this->obj ]);
            $this->response(['errcode'=>2002,'message'=>'账号或密码错误！','data'=>$this->obj ]);
        }
    }

    /*
     * 退出
     */
    public function logout(Request $request){
        /*
        //获取签名
        $sign = $request->input('sign');
        if(!$sign){
            $this->response(['errcode'=>1001,'message'=>'参数错误！','data'=>$this->obj ]);
        }
        //检查签名
        $this->checkSign($request);
        */
        $token = trim($request->input('token'));
        $histToken = $this->redis->get('token'.$this->dev);
        if(!$histToken){
            $this->response(['errcode'=>1,'message'=>'请重新登录','data'=>$this->obj ]);
        }
        if($histToken != $token){
            $this->response(['errcode'=>300,'message'=>'非法操作！','data'=>$this->obj ]);
        }
        //删除token
        $this->redis->del('token'.$this->dev);
        $this->response(['errcode'=>200,'message'=>'退出成功','data'=>$this->obj ]);
    }

    public function checkSign(Request $request){

        //判断是否已经登陆或超时退出
        $token = $this->redis->get('token'.$this->dev);
        if(!$token || strlen($token) != 32){
            $this->response(['errcode'=>2001,'message'=>'请重新登录','data'=>$this->obj ]);
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
            $this->response(['errcode'=>1002,'message'=>'数字签名错误！','data'=>$this->obj ]);
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
        $openid = $request->input('openid');
        $accessToken = $request->input('accessToken');
        /*
        $captcha = $request->input('captcha');
        //检查验证码
        if(!$captcha){
            $this->response(['errcode'=>2008,'message'=>'请输入验证码','data'=>$this->obj ]);
        }
        $cap = $this->redis->get('captcha'.$this->dev);
        if($captcha != $cap){
            $this->response(['errcode'=>2009,'message'=>'验证码错误','data'=>$this->obj ]);
        }*/
        if($accessToken && $openid){
            $userInfo = json_decode( $this->getWechatUser($accessToken,$openid), true);
            $valid_openID = $userInfo['openid'];
            $userInfo = DB::table('users')->where('openid',$valid_openID)->first();
            if($userInfo){
                $this->login($request);
            }
        } else {
            $code = $request->input('code');
            //检查验证码
            if(!$code){
                $this->response(['errcode'=>2008,'message'=>'请输入验证码','data'=>$this->obj ]);
            }
            $regCode = $this->redis->get('regCode'.$mobile);
            if($code != $regCode){
                //$this->response(['errcode'=>302,'message'=>'验证码不正确','data'=>$this->obj ]);
            }
            //检查手机号
            if(!$mobile){
                $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>$this->obj ]);
            }
            $match = "/1[3458]{1}\d{9}$/";
            if(!preg_match($match, $mobile)){
                $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>$this->obj ]);
            }
            $userInfo = DB::table('users')->where('mobile_phone',$mobile)->first();
            if($userInfo){
                $this->response(['errcode'=>2006,'message'=>'该手机号已注册','data'=>$this->obj ]);
            }
            //检查密码
            if(!$password){
                $this->response(['errcode'=>2007,'message'=>'请输入密码','data'=>$this->obj ]);
            }
        }
        /*
        // 存redis并设置过期时间
        $this->redis->set('regMobile'.$this->dev, $mobile);
        $this->redis->set('regPass'.$this->dev, md5($password));
        $this->redis->expire('regMobile'.$this->dev, 10*60);
        $this->redis->expire('regPass'.$this->dev, 10*60);

        //发送短信验证码
        $this->response(['errcode'=>200,'message'=>'提交信息成功','data'=>$this->obj ]);
        */

        $data = [
            'email' => '',
            'user_name' => str_random(16), //用户名
            'password' => '', //密码
            'question' => '',
            'answer' => '',
            'last_ip' => '',
            'alias' => isset($userInfo['nickname']) ? $userInfo['nickname'] : '', //昵称
            'mobile_phone' => '', //手机号
            'credit_line' => 800000, // 最大消费
            'sex' => isset($userInfo['sex']) ? $userInfo['sex'] : $sex,
            'age' => $age ? $age : '',
            'profession' => $profession ? $profession : '', //职业
            'openid' => isset($userInfo['openid']) ? $userInfo['openid'] : '',
            'head_culpture' => isset($userInfo['headimgurl']) ? $userInfo['headimgurl'] : '',
        ];
        if($mobile) $data['mobile_phone'] = $mobile;
        if($password) $data['password'] = md5($password);
        //将信息保存到数据库
        $res = DB::table('users')->insert($data);
        if(!$res){
            $this->response(['errcode'=>2,'message'=>'注册失败','data'=>$this->obj ]);
        }
        //$this->response(['errcode'=>200,'message'=>'注册成功','data'=>$this->obj ]);
        $this->login($request); //注册后直接登录
    }

    /*
     * 短信验证码确认
     */
    public function confirm(Request $request){
        $code = $request->input('code');
        if(!$code){
            $this->response(['errcode'=>2008,'message'=>'请输入短信验证码','data'=>$this->obj ]);
        }
        $cache = [
            'mobile_phone' => $this->redis->get('regMobile'.$this->dev),
            'password' => $this->redis->get('regPass'.$this->dev),
        ];
        //验证是否超时
        if(!$cache['mobile_phone']){
            $this->response(['errcode'=>2,'message'=>'验证超时,请重新注册','data'=>$this->obj ]);
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
            $this->response(['errcode'=>2,'message'=>'注册失败','data'=>$this->obj ]);
        }
        $this->response(['errcode'=>200,'message'=>'注册成功','data'=>$this->obj ]);
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
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>$this->obj ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>$this->obj ]);
        }
        $timer = $this->redis->get('codeTimer'.$mobile);
        if($timer){
            $sec = time() - $timer + 1;
            $this->response(['errcode'=>300,'message'=>'发送频率过快，请于'.$sec.'后再试','data'=>$this->obj ]);
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
            case 'chMobile':
                $message = "您更改新手机号的证码是$code,10分钟内输入有效";
                $this->redis->set('chmCode'.$mobile,$code);
                $this->redis->expire('chmCode'.$mobile, 10*60);
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
            $this->response(['errcode'=>200,'message'=>'该功能暂时没有实现，请随意填写个手机验证码','data'=>$this->obj ]);
        }
        else $this->response(['errcode'=>500,'message'=>'发送短信验证码失败','data'=>$this->obj ]);
    }

    /*
     * 检测手机号
     */
    public function checkMobile(Request $request){
        $mobile = $request->input('mobile');
        //检查手机号
        if(!$mobile){
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>$this->obj ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>$this->obj ]);
        }
        $userInfo = DB::table('users')->where('mobile_phone',$mobile)->first();
        if($userInfo){
            $this->response(['errcode'=>2006,'message'=>'该手机号已注册','data'=>$this->obj ]);
        }
        $this->response(['errcode'=>200,'message'=>'该手机号未注册','data'=>$this->obj ]);
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
            $this->response(['errcode'=>2004,'message'=>'请输入手机号','data'=>$this->obj ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'手机号不正确','data'=>$this->obj ]);
        }
        if(!$password){
            $this->response(['errcode'=>302,'message'=>'请输入密码','data'=>$this->obj ]);
        }
        if(strlen($password) < 6){
            $this->response(['errcode'=>302,'message'=>'密码长度至少为6位','data'=>$this->obj ]);
        }
        //检查验证码
        if(!$code){
            $this->response(['errcode'=>2008,'message'=>'请输入验证码','data'=>$this->obj ]);
        }
        $regCode = $this->redis->get('rstCode'.$mobile); //获取重置验证码
        if($code != $regCode){
            //$this->response(['errcode'=>302,'message'=>'验证码不正确','data'=>$this->obj ]);
        }
        $userInfo = DB::table('users')->where('mobile_phone',$mobile)->first();
        if(!$userInfo){
            $this->response(['errcode'=>300,'message'=>'该手机号未注册','data'=>$this->obj ]);
        }
        $newPwd = md5($password);
        if($newPwd == $userInfo->password){
            $this->response(['errcode'=>200,'message'=>'修改密码成功','data'=>$this->obj ]);
        }
        $res = DB::table('users')->where('mobile_phone',$mobile)->update(['password'=>$newPwd]);
        if($res){
            $this->redis->del('rstCode'.$mobile);
            $this->response(['errcode'=>200,'message'=>'修改密码成功','data'=>$this->obj ]);
        }
        $this->response(['errcode'=>300,'message'=>'修改密码失败','data'=>$this->obj ]);
    }

    /*
     * 修改密码
     */
    public function chPwd(Request $request){
        $password = trim(strval($request->input('password')));
        $newPwd  = trim(strval($request->input('newPwd')));
        $confPwd = trim(strval($request->input('confPwd')));
        $user_id = intval($request->input('user_id'));
        //检查数据
        if(!$password){
            $this->response(['errcode'=>302,'message'=>'请输入旧密码','data'=>$this->obj ]);
        }
        if(!$newPwd){
            $this->response(['errcode'=>302,'message'=>'新密码不能为空','data'=>$this->obj ]);
        }
        if(strlen($newPwd) < 6){
            $this->response(['errcode'=>302,'message'=>'新密码长度至少为6位','data'=>$this->obj ]);
        }
        if($newPwd != $confPwd){
            $this->response(['errcode'=>302,'message'=>'新密码和确认密码不一致','data'=>$this->obj ]);
        }
        //检查账号
        $userInfo = DB::table('users')->where(['user_id'=>$user_id,'password'=>md5($password)])->first();
        if(!$userInfo){
            $this->response(['errcode'=>300,'message'=>'旧密码不正确','data'=>$this->obj ]);
        }
        //更新密码
        $res = DB::table('users')->where(['user_id'=>$user_id])->update(['password'=>md5($newPwd)]);
        if($res){
            $this->response(['errcode'=>200,'message'=>'修改密码成功','data'=>$this->obj ]);
        }else{
            $this->response(['errcode'=>500,'message'=>'修改密码失败','data'=>$this->obj ]);
        }
    }

    /*
     * 修改手机号
     */
    public function chMobile(Request $request){
        $mobile = trim($request->input('mobile'));
        $password = trim(strval($request->input('password')));
        $user_id = intval($request->input('user_id'));
        $code = trim($request->input('code'));
        //检查数据
        if(!$mobile){
            $this->response(['errcode'=>2004,'message'=>'新手机号不能为空','data'=>$this->obj ]);
        }
        $match = "/1[3458]{1}\d{9}$/";
        if(!preg_match($match, $mobile)){
            $this->response(['errcode'=>2005,'message'=>'新手机号不正确','data'=>$this->obj ]);
        }
        $regCode = $this->redis->get('chmCode'.$mobile); //获取重置验证码
        if($code != $regCode){
            //$this->response(['errcode'=>302,'message'=>'验证码不正确','data'=>$this->obj ]);
        }
        //检查账号
        $userInfo = DB::table('users')->where(['user_id'=>$user_id,'password'=>md5($password)])->first();
        if(!$userInfo){
            $this->response(['errcode'=>300,'message'=>'密码不正确','data'=>$this->obj ]);
        }
        //更新手机号
        $res = DB::table('users')->where(['user_id'=>$user_id])->update(['mobile_phone'=>$mobile]);
        if($res){
            $this->response(['errcode'=>200,'message'=>'修改手机号成功','data'=>$this->obj ]);
        }else{
            $this->response(['errcode'=>500,'message'=>'修改手机号失败','data'=>$this->obj ]);
        }
    }

    /*
     * 上传头像
     */
    public function uploadCulpture(Request $request){
        //验证输入数据
        $token = trim($request->input('token'));
        $user_id = intval($request->input('user_id'));
        $histToken = $this->redis->get('token'.$this->dev);
        if(!$histToken){
            $this->response(['errcode'=>1,'message'=>'请重新登录','data'=>$this->obj ]);
        }
        if($histToken != $token){
            $this->response(['errcode'=>300,'message'=>'非法操作！','data'=>$this->obj ]);
        }
        $culpture = $request->file('culpture');
        if ($culpture){
            $file = $culpture->getRealPath();
            $ext = $culpture->getClientOriginalExtension();
            //文件类型验证代码
            $allowType = ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'];
            if(!in_array($ext,$allowType)){
                $this->response(['errcode'=>300,'message'=>'别糊弄我，请上传图片','data'=>$this->obj ]);
            }
            //保存图片
            $culpturePath = '/images/upload/Culpture/';
            $picPath = __DIR__.'/..'.$culpturePath;
            if(!is_writeable($picPath)){
                $this->response(['errcode'=>500,'message'=>'目录不可写','data'=>$this->obj ]);
            }
            $fileName = date('YmdHis') .'-'. uniqid() .'.'.$ext;
            $res = move_uploaded_file($file, $picPath.$fileName);
            if($res){ //图片保存成功
                $host = $request->getSchemeAndHttpHost();
                $result = DB::table('users')->where('user_id',$user_id)->update(['head_culpture'=>$host.$culpturePath.$fileName]);
                if($result){
                    $this->response(['errcode'=>200,'message'=>'修改头像成功',
                        'data'=>['new_url'=>$culpturePath.$fileName] ]
                    );
                }else{
                    $this->response(['errcode'=>500,'message'=>'修改头像失败','data'=>$this->obj ]);
                }
            }else{
                $this->response(['errcode'=>500,'message'=>'文件保存失败','data'=>$this->obj ]);
            }
        }else{
            $this->response(['errcode'=>300,'message'=>'请上传图片','data'=>$this->obj ]);
        }
    }

    /*
     * 开启/关闭手势密码
     */
    public function switchPattern(Request $request){
        //获取输入信息
        $pattern = $request->input('pattern');
        $user_id = intval($request->input('user_id'));
        $pattern_on = ($pattern == 'on') ? 'on' : 'off';
        $message = ($pattern == 'on') ? '开启手势密码验证' : '关闭手势密码验证';
        $res = DB::table('users')->where('user_id',$user_id)->update(compact('pattern_on'));
        if($res)
            $this->response(['errcode'=>200,'message'=>$message.'成功','data'=>compact('pattern_on') ]);

        $this->response(['errcode'=>300,'message'=>$message.'失败','data'=>$this->obj ]);
    }

    //验证微信用户
    private function getWechatUser($accessToken,$openid){
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$accessToken.'&openid='.$openid;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0); //不显示header
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE); // https请求 不验证hosts
        //curl_setopt($curl, CURLOPT_HTTPHEADER,['Content-Type: application/json']);
        $userInfo = curl_exec($curl);
        curl_close($curl);
        return $userInfo;
    }

    /*
     * 输出函数
     */
    public function response($data=['errcode'=>1002,'message'=>'action not found' ]){
        if(!isset($data['data'])) $data['data'] = $this->obj;
        header('Content-type:text/json');
        die(json_encode($data,true));
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
    case 'chPwd': $auth->chPwd($request);          break;
    case 'chMobile': $auth->chMobile($request);    break;
    case 'uploadCulpture': $auth->uploadCulpture($request);    break;
    case 'switchPattern': $auth->switchPattern($request);      break;
    case 'createCaptcha': $auth->createCaptcha();  break;
    default: $auth->response();
}


