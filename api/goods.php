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

    public function __construct(){
        //定义错误消息
        //$this->dev = isset($_REQUEST['device']) ? trim($_REQUEST['device']):function ($res) {$this->response($res);};
        //连接redis
        //$redis = new Redis();
        //$redis->connect(REDIS_HOST, REDIS_PORT);
        //$this->redis = $redis;
        $this->serverName = 'http://jiunonghuabao.com/';
    }

    /*
     * 获取首页数据
     */
    public function get_lists(Request $request){
        $cat_id = $request->input('cat_id')?$request->input('cat_id'):1;
        //banner图数据
        $bannerData = DB::table("ad_custom")
            ->select('content','url','ad_name')
            ->orderBy('ad_id','DESC')
            ->get();

        if($bannerData){
            $banners = array();
            foreach ($bannerData as $banner) {
                $data['url']   = (!empty($banner->url))?$banner->url:'';
                $data['title'] = (!empty($banner->ad_name))?$banner->ad_name:'';
                $data['path']  = (!empty($banner->content))?$this->serverName.$banner->content:'';
                $banners[]     = $data;
            }
        }
        $res['data']['banners'] = $banners;

        $adData = DB::table("ad")
            ->where('enabled',1)
            ->select('ad_name','ad_link','ad_code')
            ->orderBy('ad_id','DESC')
            ->first();

        $ad['title'] = (!empty($adData->ad_name))?$adData->ad_name:'';
        $ad['url']   = (!empty($adData->ad_link))?$adData->ad_link:'';
        $ad['path']  = (!empty($adData->ad_code))?$this->serverName.'data/afficheimg/'.$adData->ad_code:'';
        $res['data']['ad'] = $ad;

        // 商品数据
        $goods_list = DB::table('goods')
            ->where('is_delete',0)
            ->where('cat_id',$cat_id)
            ->select('goods_id','goods_name','shop_price','keywords','goods_img')
            ->orderBy('goods_id','ASC')
            ->take(10)
            ->get();

        //print_r($goods_list);
        if($goods_list){
            $datas = array();
            foreach ($goods_list as $goods)
            {
                //echo $goods->goods_name;
                $data['goods_id']    = $goods->goods_id;
                $data['goods_img']   = (!empty($goods->goods_img))?$this->serverName.$goods->goods_img:'';
                $data['shop_price']  = $goods->shop_price;
                $data['goods_name']  = $goods->goods_name;
                $data['keywords']    = $goods->keywords;
                $datas[] = $data;
            }
            //print_r($datas);
            if(count($datas)>0){
                $res['errcode']       = '200';
                $res['message']       = '商品获取成功！';
                $res['data']['lists'] = $datas;
            }else{
                $res['errcode']       = '2001';
                $res['message']       = '商品数据为空！';
                $res['data']['lists'] = $datas;
            }
        }

        if(!$goods_list && !$bannerData){ //如果banner和商品列表数据都为空
            $res['errcode'] = '1001';
            $res['message'] = '数据获取失败！';
            $res['data']    = [];
        }
        $this->response($res);
    }

    /*
     * 获取产品列表信息
     */
    public function get_goods_lists(Request $request){
        $cat_id = $request->input('cat_id')?$request->input('cat_id'):0;
        $record = $request->input('record')?$request->input('record'):10;
        $page = $request->input('page')?$request->input('page'):1;

        if($cat_id>0){
            $catQuery = '=';
        }else{
            $catQuery = '>';
        }
        $goods_list = DB::table('goods')
            ->where('is_delete',0)
            ->where('cat_id',$catQuery,$cat_id)
            ->select('goods_id','goods_name','shop_price','keywords','goods_img')
            ->orderBy('goods_id','ASC')
            ->skip($record * ($page-1))->take($record)
            ->get();

        //print_r($goods_list);
        if($goods_list){
            $datas = array();
            foreach ($goods_list as $goods)
            {
                //echo $goods->goods_name;
                $data['goods_id']    = $goods->goods_id;
                $data['goods_img']   = (!empty($goods->goods_img))?$this->serverName.$goods->goods_img:'';
                $data['shop_price']  = $goods->shop_price;
                $data['goods_name']  = $goods->goods_name;
                $data['keywords']    = $goods->keywords;
                $datas[] = $data;
            }
            if(count($datas)>0){
                $res['errcode'] = '200';
                $res['message'] = '商品列表获取成功';
                $res['data']    = $datas;
            }else{
                $res['errcode'] = '2001';
                $res['message'] = '商品数据为空！';
                $res['data']    = $datas;
            }
        }else{
            $res['errcode'] = '1001';
            $res['message'] = '商品列表获取失败！';
            $res['data']    = [];
        }
        $this->response($res);
    }

    /**
     * 获取商品信息 包括熟悉，相册和基本信息
     */
    public function get_goods_info(Request $request){
        $goods_id = $request->input('id')?$request->input('id'):0;
        if($goods_id>0){
            $goods = DB::table('goods')
                ->where('is_delete',0)
                ->where('goods_id',$goods_id)
                ->select('goods_id','goods_name','goods_number','market_price','shop_price','keywords','goods_thumb','goods_img','goods_desc')
                ->first();
            //print_r($goods);
            if($goods){
                $res['errcode'] = '200';
                $res['message'] = '商品信息获取成功！';

                $imgs = $this->get_goods_gallery($goods_id);
                //print_r($imgs);
                $attr2value = $this->get_goods_attribute($goods_id);
                //print_r($attr2value);
                $commetns = $this->get_goods_comment($goods_id);
                //print_r($commetns);

                $info = $attr2value;
                //$info['goods_thumb'] = (!empty($goods->goods_thumb))?$this->serverName.$goods->goods_thumb:'';
                $info['goods_img']     = (!empty($goods->goods_img))?$this->serverName.$goods->goods_img:'';
                $info['market_price']  = $goods->market_price;
                $info['shop_price']    = $goods->shop_price;
                $info['goods_name']    = $goods->goods_name;
                $info['keywords']      = $goods->keywords;
                $info['goods_desc']    = $goods->goods_desc;
                $info['goods_number']  = $goods->goods_number;

                $res['data']['imgs']     = $imgs;
                $res['data']['attrs']    = $info;
                $res['data']['commetns'] = $commetns;
            }else{
                $res['errcode'] = '1001';
                $res['message'] = '获取商品信息失败！';
                $res['data']    = [];
            }
        }else {
            $res['errcode'] = '1002';
            $res['message'] = '商品信息有误！';
            $res['data']    = [];
        }
        $this->response($res);
    }

    /**
     * 获取商品对应的属性信息
     */
    private function get_goods_attribute($goods_id){
        if ($goods_id>0) {
            $res['errcode'] = '200';
            $res['message'] = '商品信息获取成功';
            $attrInfo = DB::table('attribute')
                ->pluck('attr_values','attr_id');
            //print_r($attrInfo);

            $attrInfo2 = DB::table('goods_attr')
                ->where('goods_id',$goods_id)
                ->pluck( 'attr_value','attr_id');
            //print_r($attrInfo2);

            $attr2value = [];
            foreach($attrInfo as $attrid => $attrval){
                $attr2value[$attrval]    = isset($attrInfo2[$attrid])?$attrInfo2[$attrid]:'';
            }
        }else{
            $attr2value = [];
        }
        return $attr2value;
    }

    /**
     * 获取商品对应相册数据
     */
    private function get_goods_gallery($goods_id){
        $imgs = [];
        if ($goods_id>0) {
            $gallery = DB::table('goods_gallery')
                ->where('goods_id', $goods_id)
                ->select('img_url')
                ->get();
            //print_r($gallery);

            if($gallery){
                foreach($gallery as $img){
                    $imgs[] = $img->img_url;
                }
            }
        }
        return $imgs;
    }

    /**
     * 获取商品对应相册数据
     */
    public function get_goods_comment($goods_id){
        $comments = [];
        if ($goods_id>0) {
            $contents = DB::table('comment')
                ->where('id_value', $goods_id)
                ->select('content','user_name','comment_id')
                ->get();
            //print_r($contents);

            if($contents){
                foreach ($contents as $content) {
                    $data['comment_id'] = $content->comment_id;
                    $data['user_name']  = $content->user_name;
                    $data['content']    = $content->content;
                    $comments[]         = $data;
                }
            }
        }
        return $comments;
    }

    /**
     * 获取商品对应的属性信息
     */
    public function get_search(Request $request){
        $goods_name = $request->input('name')?$request->input('name'):'红酒';
        $goods = DB::table('goods')
            ->where('goods_name','like','%'.$goods_name.'%')  //where('name', 'like', 'T%')
            ->select('goods_id','goods_name','market_price','shop_price','keywords','goods_img')
            ->orderBy('goods_id','ASC')
            ->take(10)
            ->get();
        //print_r($attrs);
        if($goods){
            $datas = [];
            foreach($goods as $good){
                $res['errcode']      = '200';
                $res['message']      = '搜索商品成功！';

                $data['goods_id']    = $good->goods_id;
                $data['goods_img']   = (!empty($good->goods_img))?$this->serverName.$good->goods_img:'';
                $data['shop_price']  = $good->shop_price;
                $data['market_price']= $good->market_price;
                $data['goods_name']  = $good->goods_name;
                $data['keywords']    = $good->keywords;
                $datas[]             = $data;
            }
            $res['data']             = $datas;
        }else{
            $res['errcode'] = '1001';
            $res['message'] = '搜索商品失败！';
            $res['data']    = [];
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
}

$Goods = new Goods();

//捕获输入信息
$request = Request::capture();
//参数路由
$act = $request->input('act');
switch ($act) {
    case 'get_lists':
        $Goods->get_lists($request);
        break;
    case 'get_goods_lists':
        $Goods->get_goods_lists($request);
        break;
    case 'get_goods_info':
        $Goods->get_goods_info($request);
        break;
    case 'get_search':
        $Goods->get_search($request);
        break;
    default:
        $Goods->response();
}
?>