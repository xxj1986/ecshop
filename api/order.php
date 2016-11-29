<?php

/**
 * 购物车 相关功能
 */


define('IN_ECS', true);

// 载入初始化文件
include __DIR__ . '/aip_init.php';

//数据库ORM
use Illuminate\Database\Capsule\Manager as DB;
//输入过滤器
use Illuminate\Http\Request;

//定义认证类
class Order
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
        //echo $request->method();
    }

    /*
     * 添加购物车信息
     */
    public function set_cart(Request $request){
        $goods_id = $request->input('goods_id') ? $request->input('goods_id') : 0;
        $goods_num = $request->input('goods_num') ? $request->input('goods_num') : 0;
        $user = $request->input('user') ? $request->input('user') : 0;

        if ($goods_id > 0 && $goods_num > 0 && $user > 0) {
            $goodsInfo = DB::table('goods')
                ->select('goods_sn', 'goods_name', 'goods_id', 'market_price', 'shop_price')
                ->where('is_delete', 0)
                ->where('goods_id', $goods_id)
                ->first();
            if ($goodsInfo) {
                $id = DB::table('cart')->insertGetId(
                    ['user_id' => $user,
                        'goods_id' => $goods_id,
                        'goods_sn' => $goodsInfo->goods_sn,
                        'goods_name' => $goodsInfo->goods_name,
                        'market_price' => $goodsInfo->market_price,
                        'goods_price' => $goodsInfo->shop_price,
                        'goods_number' => $goods_num,
                        'is_real' => 1
                    ]
                );
                if ($id) {
                    $res['errcode'] = '200';
                    $res['message'] = '添加购物车成功！';
                    $res['data'] = [];
                } else {
                    $res['errcode'] = '10001';
                    $res['message'] = '添加购物车失败！';
                    $res['data'] = [];
                }
            } else {
                $res['errcode'] = '10002';
                $res['message'] = '所选商品信息有误！';
                $res['data'] = [];
            }
        } else {
            $res['errcode'] = '10003';
            $res['message'] = '提交参数信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /**
     * 获取购物车信息
     */
    public function get_cart_infos(Request $request){
        $user = $request->input('user') ? $request->input('user') : 0;
        if ($user > 0) {
            $carts = DB::table('cart')
                ->where('is_real', 1)
                ->where('user_id', $user)
                ->select('goods_id','goods_sn','goods_name','goods_number','market_price','goods_price')
                ->get();
            //print_r($goods);
            if ($carts) {
                $data = [];
                foreach($carts as $cart){
                    $data[] = $cart;
                }
                if(count($data)>0){
                    $res['errcode'] = '200';
                    $res['message'] = '获取购物车信息成功！';
                }else{
                    $res['errcode'] = '1001';
                    $res['message'] = '购物车为空！';
                }
                $res ['data'] = $data;
            } else {
                $res['errcode'] = '1002';
                $res['message'] = '获取购物车信息失败！';
                $res['data'] = [];
            }
        } else {
            $res['errcode'] = '1002';
            $res['message'] = '参数信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /**
     * 更新购物车信息
     * 参数格式为 /api/order.php?act=update_cart&id=5,6,7&nums=699,676,888
     */
    public function update_cart(Request $request){
        $goods_ids = $request->input('id') ? $request->input('id') :'';
        $goods_nums = $request->input('nums') ? $request->input('nums') :'';
        $idarrs = explode(',',$goods_ids);
        $noarrs = explode(',',$goods_nums);
        //var_dump($idarrs);
        $idcounts = count($idarrs);
        $nocounts = count($noarrs);
        //var_dump($idcounts);
        if ($goods_ids == '' || $goods_nums == '' || $idcounts != $nocounts) {
            $res['errcode'] = '202';
            $res['message'] = '购物车信息有误！';
            $res['data']    = [];
        } else {
            $res['errcode'] = '200';
            $res['message'] = '更新购物车信息成功！';
            $res['data']    = [];
            for($i = 0; $i < $idcounts; $i++){
                $cid = $idarrs[$i];
                $num = $noarrs[$i];
                $state = DB::table('cart')
                    ->where('rec_id',$cid)
                    ->update(['goods_number' => $num]);

                if(!$state){
                    $res['errcode'] = '201';
                    $res['message'] = '更新购物车信息失败！';
                }
            }
        }
        $this->response($res);
    }

    /**
     * 删除对应购物车信息
     */
    public function delete_cart(Request $request){
        $cart_id = $request->input('cid') ? $request->input('cid') :0;

        $state = DB::table('cart')
            ->where('rec_id',$cart_id)
            ->delete();
        if ($state) {
            $res['errcode'] = '200';
            $res['message'] = '删除记录成功！';
        } else {
            $res['errcode'] = '2001';
            $res['message'] = '删除购物车记录失败！';
        }
        $res['data'] = [];
        $this->response($res);
    }

    /**
     * 添加订单信息
     */
    public function addOrder(Request $request){
        $goods_id = $request->input('goods_id') ? $request->input('goods_id') : 0;
        $goods_num = $request->input('goods_num') ? $request->input('goods_num') : 0;
        $user = $request->input('user') ? $request->input('user') : 0;

        if ($goods_id > 0 && $goods_num > 0 && $user > 0) {
            $goodsInfo = DB::table('goods')
                ->select('goods_sn', 'goods_name', 'goods_id', 'market_price', 'shop_price')
                ->where('is_delete', 0)
                ->where('goods_id', $goods_id)
                ->first();
            if ($goodsInfo) {
                $id = DB::table('cart')->insertGetId(
                    ['user_id' => $user,
                        'goods_id' => $goods_id,
                        'goods_sn' => $goodsInfo->goods_sn,
                        'goods_name' => $goodsInfo->goods_name,
                        'market_price' => $goodsInfo->market_price,
                        'goods_price' => $goodsInfo->shop_price,
                        'goods_number' => $goods_num,
                        'is_real' => 1
                    ]
                );
                if ($id) {
                    $res['errcode'] = '200';
                    $res['message'] = '添加购物车成功！';
                    $res['data'] = [];
                } else {
                    $res['errcode'] = '10001';
                    $res['message'] = '添加购物车失败！';
                    $res['data'] = [];
                }
            } else {
                $res['errcode'] = '10002';
                $res['message'] = '所选商品信息有误！';
                $res['data'] = [];
            }
        } else {
            $res['errcode'] = '10003';
            $res['message'] = '提交参数信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /*
     * 输出函数
     */
    public function response($res = ['errcode' => '2001', 'message' => 'action not found', 'data' => []]){
        header('Content-type:text/json');
        die(json_encode($res));
    }
}
$Order = new Order();

//捕获输入信息
$request = Request::capture();
//参数路由
$act = $request->input('act');
switch ($act) {
    case 'set_cart':
        $Order->set_cart($request);
        break;
    case 'get_cart_infos':
        $Order->get_cart_infos($request);
        break;
    case 'update_cart':
        $Order->update_cart($request);
        break;
    case 'delete_cart':
        $Order->delete_cart($request);
        break;
    case 'addOrder':
        $Order->addOrder($request);
        break;
    default:
        $Order->response();
}
?>