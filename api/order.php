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
        $this->serverName = 'http://jiunonghuabao.com/';
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
        $goods_ids  = $request->input('id') ? $request->input('id') : '';
        $goods_nums = $request->input('num') ? $request->input('num') : '';
        $user       = $request->input('user') ? $request->input('user') : 0;
        $addressId  = $request->input('addressId') ? $request->input('addressId') : 0;
        $money      = $request->input('money') ? $request->input('money') : 0;

        $idarrs     = explode(',',$goods_ids);
        $noarrs     = explode(',',$goods_nums);
        //var_dump($idarrs);
        $idcounts   = count($idarrs);
        $nocounts   = count($noarrs);

        //订单编号
        $order_sn   = $this->buildOrderNo('JN');

        if ($goods_ids != '' && $goods_nums != '' && $idcounts == $nocounts && $user > 0 && $addressId>0 && $money>0) {
            $userAddress = DB::table('user_address')
                ->select('address_id','consignee','country','province','city','district','address','zipcode','tel','mobile','email','sign_building')
                ->where('address_id',$addressId)
                ->where('user_id',$user)
                ->first();
            if ($userAddress) {
                $oid = DB::table('order_info')->insertGetId(
                    [   'order_sn'      => $order_sn,
                        'user_id'       => $user,
                        'consignee'     => $userAddress->consignee,
                        'country'       => $userAddress->country,
                        'province'      => $userAddress->province,
                        'city'          => $userAddress->city,
                        'district'      => $userAddress->district,
                        'address'       => $userAddress->address,
                        'zipcode'       => $userAddress->zipcode,
                        'tel'           => $userAddress->tel,
                        'mobile'        => $userAddress->mobile,
                        'email'         => $userAddress->email,
                        'sign_building' => $userAddress->sign_building,

                        'goods_amount'  => $money,
                        'order_amount'  => $money,
                        'add_time'      => time(),
                        'referer'       => 'APP'
                    ]
                );
                if ($oid) {
                    //添加订单商品数据
                    $status = $this->addOrderGoods($oid,$idarrs,$noarrs);
                    if($status){
                        $res['errcode'] = '200';
                        $res['message'] = '订单生成成功！';
                        $res['data'] = [];
                    }else{
                        $res['errcode'] = '2003';
                        $res['message'] = '订单数据缺失！';
                        $res['data'] = [];
                    }
                } else {
                    $res['errcode'] = '2002';
                    $res['message'] = '订单生成失败！';
                    $res['data'] = [];
                }
            } else {
                $res['errcode'] = '2003';
                $res['message'] = '地址信息有误！';
                $res['data'] = [];
            }
        } else {
            $res['errcode'] = '2001';
            $res['message'] = '订单信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }

    /**
     * 添加订单信息
     */
    private function addOrderGoods($oid,$goodsIds,$goodsNums){

        $counts = count($goodsIds);
        if ($oid > 0 && $counts > 0 && count($goodsNums) == $counts) {
            $goodsInfo = DB::table('goods')
                ->select('goods_sn','goods_name','goods_id','market_price','shop_price','is_real')
                ->whereIn('goods_id',$goodsIds)
                ->get();

            for($i = 0; $i < $counts; $i++) {
                $goods2nums[$goodsIds[$i]] = $goodsNums[$i];
            }

            $datas = [];
            foreach($goodsInfo as $info){
                $data['order_id']     = $oid;
                $data['goods_id']     = $info->goods_id;
                $data['goods_sn']     = $info->goods_sn;
                $data['goods_name']   = $info->goods_name;
                $data['goods_number'] = $goods2nums[$info->goods_id];
                $data['market_price'] = $info->market_price;
                $data['goods_price']  = $info->shop_price;
                $data['is_real']      = $info->is_real;

                $datas[]              = $data;
            }
            if(DB::table('order_goods')->insert($datas)){
                return true;
            }else{
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取订单信息
     */
    public function get_order_lists(Request $request){
        $user = $request->input('user') ? $request->input('user') : 0;
        $type = $request->input('type') ? $request->input('type') : -1; //默认为-1 所有类型订单

        //订单状态 0：待支付 1：已支付待发货 2:已发货，待收货 3：已收货,待评价 4:已评价完成'  shipping_status发货  pay_status支付
        if($type>=0){
            $catQuery = '=';
        }else{
            $catQuery = '>';
        }

        if ($user > 0) {
            $orders = DB::table('order_info')
                ->where('order_status',$catQuery,$type) //此处可能会视情况调整
                ->where('user_id', $user)
                ->select('order_id','order_sn','order_amount')
                ->get();
            //print_r($goods);
            if ($orders) {
                $allData = [];
                foreach ($orders as $order) {
                    $orderGoods = DB::table('order_goods')
                        ->where('order_id', $order->order_id)
                        ->select('goods_id', 'goods_number')
                        ->get();
                    if ($orderGoods) {
                        $goods2nums = [];
                        $ids = [];
                        foreach ($orderGoods as $value) {
                            $goods2nums[$value->goods_id] = $value->goods_number;
                            $ids[] = $value->goods_id;
                        }
                        var_dump($goods2nums);
                        var_dump($ids);
                        $goodsLists = DB::table('goods')
                            ->where('goods_id', $order->order_id)
                            ->select('goods_id', 'goods_name', 'shop_price', 'keywords', 'goods_img')
                            ->orderBy('goods_id', 'ASC')
                            ->get();

                        $datas = [];
                        foreach ($goodsLists as $goods) {
                            $data['goods_id'] = $goods->goods_id;
                            $data['goods_img'] = (!empty($goods->goods_img)) ? $this->serverName . $goods->goods_img : '';
                            $data['shop_price'] = $goods->shop_price;
                            $data['goods_name'] = $goods->goods_name;
                            $data['keywords'] = $goods->keywords;
                            $data['goods_number'] = $goods2nums[$goods->goods_id];

                            $datas[] = $data;
                        }
                    }else{
                        $res['errcode'] = '2001';
                        $res['message'] = '获取订单信息失败！';
                        $res ['data']   = [];
                    }

                    $allData[] = $datas;

                    $res['errcode'] = '200';
                    $res['message'] = '获取订单信息成功！';
                    $res['data']    = $allData;
                }
            } else {
                $res['errcode'] = '2002';
                $res['message'] = '订单数据为空！';
                $res['data'] = [];
            }
        } else {
            $res['errcode'] = '1002';
            $res['message'] = '参数信息有误！';
            $res['data'] = [];
        }
        $this->response($res);
    }


    //生成订单编号
    private function buildOrderNo($type){
        return $type.date('Ymd').mt_rand(10,99).substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 3).mt_rand(100,999);
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
    case 'get_order_lists':
        $Order->get_order_lists($request);
        break;
    default:
        $Order->response();
}
?>