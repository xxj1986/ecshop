<?php

/**
 * ECSHOP 获取商品信息
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: goods.php 17217 2011-01-19 06:29:08Z liubo $
 */


define('IN_ECS', true);

// 载入初始化文件
include __DIR__ . '/aip_init.php';

//数据库ORM
use Illuminate\Database\Capsule\Manager as DB;
//输入过滤器
use Illuminate\Http\Request;
//图片验证码
use Gregwar\Captcha\CaptchaBuilder;

//定义认证类
class Goods
{
    protected $dev; //设备ID
    protected $redis;

    public function __construct()
    {
        //捕获输入信息
        $request = Request::capture();
        //定义错误消息
        //$this->dev = isset($_REQUEST['device']) ? trim($_REQUEST['device']):function ($res) {$this->response($res);};
        //连接redis
        //$redis = new Redis();
        //$redis->connect(REDIS_HOST, REDIS_PORT);
        //$this->redis = $redis;

        //参数路由
        $act = $request->input('act');
        switch ($act) {
            case 'get_lists':
                $this->get_lists($request);
                break;
            case 'get_goods_lists':
                $this->get_goods_lists($request);
                break;
            case 'get_goods_info':
                $this->get_goods_info($request);
                break;
            case 'get_search':
                $this->get_search($request);
                break;
            case 'get_goods_attribute':
                $this->get_goods_attribute($request);
                break;
            default:
                $this->response();
        }
    }

    /*
     * 获取列表页面数据
     */
    private function get_lists(Request $request){
        $cat_id = $request->input('cat_id')?$request->input('cat_id'):1;
        $record_number = $request->input('record_number')?$request->input('record_number'):20;
        $page_number = $request->input('page_number')?$request->input('page_number'):0;
        $res['errcode'] = '200';
        $res['message'] = '商品列表获取成功';
        //banner图数据
        $bannerData = DB::table("ad_custom")
            ->select('content','url')
            ->orderBy('ad_id','DESC')
            ->get();

        if($bannerData){
            $banners = array();
            foreach ($bannerData as $banner) {
                $data['url'] = (!empty($banner->url))?$banner->url:'';
                $data['path'] = (!empty($banner->content))? 'http://'.$_SERVER['SERVER_NAME'].'/'.$banner->content:'';
                $banners[] = $data;
            }
        }
        $res['data']['banners'] = $banners;

        // 商品数据
        $goods_list = DB::table('goods')
            ->where('is_delete',0)
            ->where('cat_id',$cat_id)
            ->select('goods_id','cat_id','goods_name','goods_number','shop_price','keywords','goods_thumb','goods_img','last_update')
            ->orderBy('goods_id','ASC')
            ->skip($record_number * $page_number)->take($record_number+1)
            ->get();

        //print_r($goods_list);
        if($goods_list){
            $datas = array();
            foreach ($goods_list as $goods)
            {
                //echo $goods->goods_name;
                $data['goods_thumb'] = (!empty($goods->goods_thumb))? 'http://'.$_SERVER['SERVER_NAME'].'/'.$goods->goods_thumb:'';
                $data['goods_img'] = (!empty($goods->goods_img))? 'http://'.$_SERVER['SERVER_NAME'].'/'. $goods->goods_img:'';
                $data['shop_price']  = $goods->shop_price;
                $data['goods_name']  = $goods->goods_name;
                $data['cat_id']  = $goods->cat_id;
                $data['goods_number']  = $goods->goods_number;
                $datas[] = $data;
            }
            //print_r($datas);
            $res['data']['lists'] = $datas;
        }

        if(!$goods_list && !$bannerData){ //如果banner和商品列表数据都为空
            $res['errcode'] = '1001';
            $res['message'] = '数据获取失败！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /*
     * 获取产品列表信息
     */
    private function get_goods_lists(Request $request){
        $cat_id = $request->input('cat_id')?$request->input('cat_id'):1;
        $record_number = $request->input('record_number')?$request->input('record_number'):20;
        $page_number = $request->input('page_number')?$request->input('page_number'):0;
        /*$users = DB::table('users')
            ->join('contacts', 'users.id', '=', 'contacts.user_id')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.*', 'contacts.phone', 'orders.price')
            ->orderBy('name', 'desc')
            ->skip(10)->take(5)  // 要限制查找所返回的结果数量，或略过指定数量的查找结果（偏移），则可使用 skip 和 take 方法：
            ->get();   //first() 只取一条
        若你不想取出完整的一行，则可以使用 value 方法来从单条记录中取出单个值。这个方法会直接返回字段的值：
        $email = DB::table('users')->where('name', 'John')->value('email');
        $user = DB::table('users')->where('name', 'John')->first();
        echo $user->name;*/
        $goods_list = DB::table('goods')
            ->where('is_delete',0)
            ->where('cat_id',$cat_id)
            ->select('goods_id','cat_id','goods_name','goods_number','shop_price','keywords','goods_thumb','goods_img','last_update')
            ->orderBy('goods_id','ASC')
            ->skip($record_number * $page_number)->take($record_number+1)
            ->get();

        //print_r($goods_list);
        if($goods_list){
            $res['errcode'] = '200';
            $res['message'] = '商品列表获取成功';
            $datas = array();
            foreach ($goods_list as $goods)
            {
                //echo $goods->goods_name;
                $data['goods_thumb'] = (!empty($goods->goods_thumb))? 'http://'.$_SERVER['SERVER_NAME'].'/'.$goods->goods_thumb:'';
                $data['goods_img'] = (!empty($goods->goods_img))? 'http://'.$_SERVER['SERVER_NAME'].'/'. $goods->goods_img:'';
                $data['shop_price']  = $goods->shop_price;
                $data['goods_name']  = $goods->goods_name;
                $data['cat_id']  = $goods->cat_id;
                $data['goods_number']  = $goods->goods_number;
                $datas[] = $data;
            }
            //print_r($datas);
            $res['data'] = $datas;
        }else{
            $res['errcode'] = '1001';
            $res['message'] = '商品列表获取失败！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /**
     * 获取商品信息
     */
    private function get_goods_info(Request $request){
        $goods_id = $request->input('id')?$request->input('id'):0;
        if($goods_id>0){
            $goods = DB::table('goods')
                ->where('is_delete',0)
                ->where('goods_id',$goods_id)
                ->select('goods_id','cat_id','goods_name','goods_number','shop_price','keywords','goods_thumb','goods_img','last_update')
                ->first();
            //print_r($goods);
            if($goods){
                $res['errcode'] = '200';
                $res['message'] = '商品信息获取成功';
                $attrInfo = DB::table('attribute')
                    ->select('attr_id', 'attr_values')
                    ->get();
                //print_r($attrInfo);
                $attrs = [];
                foreach($attrInfo as $attr){
                    $attrs[$attr->attr_id] = $attr->attr_values;
                }
                //print_r($attrs);
                $attrInfo2 = DB::table('goods_attr')
                    ->where('goods_id',$goods_id)
                    ->select('attr_id', 'attr_value')
                    ->get();
                //print_r($attrInfo2);
                //$attrs2 = [];
                $attr2value = [];
                foreach($attrInfo2 as $attr2){
                    //$attrs2[$attr2->attr_id] = $attr2->attr_value;
                    $attr2value[$attrs[$attr2->attr_id]] = $attr2->attr_value;
                }
                //print_r($attrs2);
                //print_r($attr2value);

                $info = $attr2value;
                $info['goods_thumb'] = (!empty($goods->goods_thumb))? 'http://' . $_SERVER['SERVER_NAME'] . '/' . $goods->goods_thumb:'';
                $info['goods_img'] = (!empty($goods->goods_img))? 'http://' . $_SERVER['SERVER_NAME'] . '/' . $goods->goods_img:'';
                $info['shop_price']  = $goods->shop_price;
                $info['goods_name']  = $goods->goods_name;
                $info['cat_id']  = $goods->cat_id;
                $info['goods_number']  = $goods->goods_number;
                $res['data'] = $info;
            }else{
                $res['errcode'] = '1001';
                $res['message'] = '获取商品信息失败！';
                $res['data'] = [];
            }
        }else {
            $res['errcode'] = '1002';
            $res['message'] = '商品信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /**
     * 获取商品对应的属性信息
     */
    private function get_goods_attribute(Request $request){
        $goods_id = $request->input('id')?$request->input('id'):0;
        if ($goods_id>0) {
            $attrs = DB::table('goods_attr')
                ->where('goods_id',$goods_id)
                ->select('goods_id','attr_id','attr_value','attr_price')
                ->get();
            //print_r($goods);
            if($attrs){
                $res['errcode'] = '200';
                $res['message'] = '获取商品属性信息成功！';
                $res['data'] = $attrs;
            }else{
                $res['errcode'] = '1001';
                $res['message'] = '获取商品属性信息失败！';
                $res['data'] = [];
            }
        } else {
            $res['errcode'] = '1002';
            $res['message'] = '商品信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /**
     * 获取商品对应的属性信息
     */
    private function get_search(Request $request){
        $goods_name = $request->input('name')?$request->input('name'):'红酒';
        $attrs = DB::table('goods')
            ->where('goods_name','like','%'.$goods_name.'%')  //where('name', 'like', 'T%')
            ->select('goods_id','cat_id','goods_name','goods_number','shop_price','keywords','goods_thumb','goods_img','last_update')
            ->orderBy('goods_id','ASC')
            ->take(30)
            ->get();
        //print_r($attrs);
        if($attrs){
            $datas = [];
            foreach($attrs as $attr){
                $res['errcode'] = '200';
                $res['message'] = '获取商品属性信息成功！';
                $data['goods_thumb'] = (!empty($attr->goods_thumb))? 'http://'.$_SERVER['SERVER_NAME'].'/'.$attr->goods_thumb:'';
                $data['goods_img'] = (!empty($attr->goods_img))? 'http://'.$_SERVER['SERVER_NAME'].'/'. $attr->goods_img:'';
                $data['shop_price']  = $attr->shop_price;
                $data['goods_name']  = $attr->goods_name;
                $data['cat_id']  = $attr->cat_id;
                $data['goods_number']  = $attr->goods_number;
                $datas[] = $data;
            }
            $res['data'] = $datas;
        }else{
            $res['errcode'] = '1001';
            $res['message'] = '获取商品属性信息失败！';
            $res['data'] = [];
        }
        $this->response($res);
    }


    /*
     * 输出函数
     */
    public function response($res=['errcode'=>2001,'message'=>'action not found','data'=>[]]){
        header('Content-type:text/json');
        die(json_encode($res));
    }

    /**
     * 解密函数
     *
     * @param string $txt
     * @param string $key
     * @return string
     */
    public function passport_decrypt($txt, $key)
    {
        $txt = passport_key(base64_decode($txt), $key);
        $tmp = '';
        for ($i = 0;$i < strlen($txt); $i++) {
            $md5 = $txt[$i];
            $tmp .= $txt[++$i] ^ $md5;
        }
        return $tmp;
    }

    /**
     * 加密函数
     *
     * @param string $txt
     * @param string $key
     * @return string
     */
    public function passport_encrypt($txt, $key)
    {
        srand((double)microtime() * 1000000);
        $encrypt_key = md5(rand(0, 32000));
        $ctr = 0;
        $tmp = '';
        for($i = 0; $i < strlen($txt); $i++ )
        {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $encrypt_key[$ctr].($txt[$i] ^ $encrypt_key[$ctr++]);
        }
        return base64_encode(passport_key($tmp, $key));
    }

    /**
     * 编码函数
     *
     * @param string $txt
     * @param string $encrypt_key
     * @return string
     */
    public function passport_key($txt, $encrypt_key)
    {
        $encrypt_key = md5($encrypt_key);
        $ctr = 0;
        $tmp = '';
        for($i = 0; $i < strlen($txt); $i++)
        {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $txt[$i] ^ $encrypt_key[$ctr++];
        }
        return $tmp;
    }
}

$Goods = new Goods();

?>