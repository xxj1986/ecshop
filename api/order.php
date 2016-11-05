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
        //echo $request->method();
        //参数路由
        $act = $request->input('act');
        switch ($act) {
            case 'set_cart':
                $this->set_cart($request);
                break;
            case 'get_cart_infos':
                $this->get_cart_infos($request);
                break;
            case 'update_cart_infos':
                $this->update_cart($request);
                break;
            case 'delete_cart':
                $this->delete_cart($request);
                break;
            default:
                $this->response();
        }
    }


    /*
     * 添加购物车信息
     */
    private function set_cart(Request $request)
    {
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
    private function get_cart_infos(Request $request)
    {
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
     */
    private function update_cart(Request $request)
    {
        $goods_id = $request->input('id') ? $request->input('id') : 0;
        if ($goods_id > 0) {
            $attrs = DB::table('goods_attr')
                ->where('goods_id', $goods_id)
                ->select('goods_id', 'attr_id', 'attr_value', 'attr_price')
                ->get();
            //print_r($goods);
            if ($attrs) {
                $res['errcode'] = '200';
                $res['message'] = '获取商品属性信息成功！';
                $res['data'] = $attrs;
            } else {
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
     * 删除对应购物车信息
     */
    private function delete_cart(Request $request)
    {
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


    /*
     * 输出函数
     */
    public function response($res = ['errcode' => '2001', 'message' => 'action not found', 'data' => []])
    {
        header('Content-type:text/json');
        die(json_encode($res));
    }
}
$Order = new Order();

?>