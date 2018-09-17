<?php
/**
 * Created by PhpStorm.
 * User: yangmz
 * Date: 2017-5-10
 * Time: 11:46
 * @brief web收银端
 */
class cash_register extends pluginBase
{
    private $return = array('success'=>0,'msg'=>'','data'=>'');
    //插件注册
    public function reg()
    {
        $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Origin: $origin");
        $function = array(
            'check_balance','delete_shopping_guide','get_shopping_guides','add_shopping_guide','get_prop',
            'seller_logout','select_goods','get_payment','search_user', 'remove_cart',
            'shop_cart','new_order','go_to_pay','clear_car','get_user_address','user_logout','user_address_update',
            'get_area','get_seller_order','hang_order','get_hang_order','cancel_hang_order','hang_to_cart','check_order','del_order');
        //这些接口是需要商户登录才能访问
        if ($this->get_seller_id())
        {
            foreach ($function as $value)
            {
                $func = function() use ($value)
                {
                    $func_son = function($value){$this->$value();};
                    self::controller()->$agrs = $func_son($value);
                };
                plugin::reg("onBeforeCreateAction@cash@$value",$func);
            }
        }
        //这些接口是未登录或者登录可以访问
        plugin::reg("onBeforeCreateAction@cash@seller_login",function(){
            self::controller()->seller_login = function(){$this->seller_login();};
        });
        //判断是否登录
        plugin::reg("onBeforeCreateAction@cash@check_islogin",function(){
            self::controller()->check_islogin = function(){$this->check_islogin();};
        });
        //新增会员
        plugin::reg("onBeforeCreateAction@cash@new_member",function(){
            self::controller()->new_member = function(){$this->new_member();};
        });
        //入口文件
        plugin::reg("onBeforeCreateAction@cash@index",function(){
            self::controller()->index = function(){$this->index();};
        });
    }
    //
    private function index()
    {
         $this->view('index');
    }
    //新增会员
    private function new_member()
    {
        $result = plugin::trigger("userRegAct",$_POST);
        if(is_array($result))
        {
            $this->return['msg'] = '注册成功';
            $this->return['success'] = 1;
        }
        else
        {
            $this->return['msg'] = $result;
        }
        $this->output();
    }
    //删除导购员
    private function delete_shopping_guide()
    {
        $guide_id = IFilter::act(IReq::get('id'),'int');
        $guideDB = new IModel('shopping_guide');
        if($guideDB->getObj('id = '.$guide_id,'id'))
        {
            $guideDB->del('id = '.$guide_id. ' and seller_id = '.$this->get_seller_id());
            $this->return['msg']     = '删除成功';
            $this->return['success'] = 1;
        }
        else
        {
            $this->return['msg']     = '删除失败';
        }
        $this->output();
    }
    //获取导购员列表
    private function get_shopping_guides()
    {
        $guideDB = new IModel('shopping_guide');
        if($seller_id = $this->get_seller_id())
        {
            $guideSet = $guideDB->query('seller_id = '.$seller_id);
            $this->return['data'] = $guideSet;
            $this->return['success'] = 1;
            $this->output();
        }
    }
    //添加导购员
    private function add_shopping_guide()
    {
        $guide_name = IFilter::act(IReq::get('guide_name'),'string');
        if(!$guide_name)
        {
            $this->return['msg'] = '缺少参数';
            $this->output();
        }
        $guideDB = new IModel('shopping_guide');
        $insertData = array(
            'seller_id'  => $this->get_seller_id(),
            'guide_name'=> $guide_name,
        );
        $guideDB->setData($insertData);
        if($insert_id = $guideDB->add())
        {
            $this->return['msg']     = '添加成功';
            $this->return['success'] = 1;
            $this->return['data']    = array('id'=>$insert_id);
        }
        $this->output();
    }
    //商户登出
    private function seller_logout()
    {
        //商户登出
        ISafe::clear('cash_seller_id','cookie');
        //用户登出
        ISafe::clear('cash_user_id','cookie');
        //情况购物车
        $cartObj = new Cart();
        $cartObj->clear();
        $this->return['success'] = 1;
        $this->return['msg']     = '登出成功';
        $this->output();
    }
    //检查用户是否登录
    private function check_islogin()
    {
        if($seller_id = $this->get_seller_id())
        {
            $this->return['success'] = 1;
            $this->return['msg']     = '已经登录';
            $sellerDB                = new IModel('seller');
            $returnData              = $sellerDB->getObj(' id = '.$seller_id,'true_name,seller_name');
            if($returnData)
            {
                $this->return['data'] = $returnData;
            }
        }
        else
        {
            $this->return['msg']     = '未登录';
        }
        $this->output();
    }
    //商户登录
    public function seller_login()
    {
        $seller_id = IFilter::act(IReq::get('id'),'int');
        $seller_name = IFilter::act(IReq::get('seller_name'),'string');
        $password = IFilter::act(IReq::get('password'),'string');
        $sellerDB = new IModel('seller');
        $result  = $sellerDB->getObj('id  = '.$seller_id.' and seller_name = \''.$seller_name.'\' and password =\''.$password.'\' and is_del = 0','id');
        if($result)
        {
            $this->set_seller_id($seller_id);
            $this->return['success'] = 1;
            $this->return['msg'] = '登录成功';
        }
        else
        {
            $this->return['msg'] = '登录失败';
        }
        $this->output();
    }
    //获取会员优惠券
    private function get_prop()
    {
        $ticket_code = IFilter::act(IReq::get('ticket_code'),'string');//通过券码查找
        $user_id = $this->get_user_id();
        $countSumObj = new CountSum($user_id);
        $goodsResult = $countSumObj->cart_count();
        $seller_ids = $this->get_seller_id();
        $prop = array();
        //会员已经登录
        if ($user_id)
        {
            //返回所有的卡券
            $memberObj = new IModel('member');
            $memberRow = $memberObj->getObj('user_id = '.$user_id,'prop');
            if(isset($memberRow['prop']) && ($propId = trim($memberRow['prop'],',')))
            {
                $prop_ids = explode(",", trim($memberRow['prop'],','));
                //dyg_jzw 20170105 计算代金券是否可用
                $card_name_arr = array();
                foreach($prop_ids as $tid)
                {
                    if(!$tid)
                    {
                        continue;
                    }
                    $ticketRow = Api::run('checkTicketUseful', $tid, $goodsResult, $seller_ids);
                    if ($ticketRow)
                    {
                        $prop[] = $ticketRow;
                        $card_name_arr[] = $ticketRow['card_name'];
                    }
                }
            }
            //单张卡券
            if(trim($ticket_code))
            {
                $ticketRow = Api::run('checkTicketUseful', 0, $goodsResult, $seller_ids, $ticket_code);
                //优惠券存在 而且不是他人的优惠券
                if ($ticketRow && $ticketRow['user_id'] != $user_id)
                {
                    //卡券未发放, 绑定给当前客户
                    if (empty($ticketRow['user_id']))
                    {
                        $propObj = new IModel("prop");
                        $propObj->setData(array('user_id' => $user_id));
                        $propObj->update("id = ".$ticketRow['id']);

                        //更新用户prop字段
                        $memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$ticketRow['id']},')");
                        $memberObj->setData($memberArray);
                        $memberObj->update('user_id = '.$user_id,'prop');
                    }

                    $prop[] = $ticketRow;
                }
            }
            $this->return['success'] = 1;
            $this->return['data']    = $prop;
            //获取商品数据信息
        }
        //未登录
        else
        {
            $propObj   = new IModel('prop');
            //确定这张券没有被使用
            if(trim($ticket_code) && $tid_arr = $propObj->getObj('card_name = '.$ticket_code,'id'))
            {
                $ticketRow = Api::run('checkTicketUseful', $tid_arr['id'], $goodsResult, $seller_ids);
                if($ticketRow)
                {
                    $prop[] = $ticketRow;
                }
                $this->return['success'] = 1;
                $this->return['data']    = $prop;
            }
        }
        foreach ($prop as &$val)
        {
            unset($val['card_pwd']);
            unset($val['type']);
            unset($val['condition']);
            unset($val['img']);
        }
        $this->output();
    }
    //获取支付方式
    private function get_payment()
    {
        $where = '(status = 0 or id =0)';
        switch(IClient::getDevice())
        {
            //移动支付
            case IClient::MOBILE:
            {

                //如果是微信客户端,必须用微信专用支付
                if(IClient::isWechat() == true)
                {
                    //dyg_jzw 20160307 非跨境订单微信端
                    $where .= " and is_cbe = 0 and (class_name in ( 'wap_wechat','balance' ) or ( type = 2 and client_type in(2,3) )) ";
                }
                else
                {
                    //dyg_jzw 20160307 非跨境订单普通移动端
                    $where .= " and is_cbe = 0 and client_type in(2,3) and class_name !=  'wap_wechat' ";
                }
            }
                break;

            //pc支付
            case IClient::PC:
            {
                //dyg_jzw 20160307 非跨境订单
                $where .= ' and is_cbe = 0 and client_type in(1,3) ';
            }
                break;
        }
        if(IReq::get('user_id') || IReq::get('user_id') === '0')
        {
            $user_id = IReq::get('user_id');
        }
        else
        {
            $user_id = $this->get_user_id();
        }
        //预存款是登录会员才能显示,没有登录没有预付款
        if(!$user_id)
        {
            $where .= ' and id <> 1';
        }
        $paymentDB = new IModel('payment');
        $paymentList = $paymentDB->query($where,"*","`order` asc");
        if($paymentList)
        {
            //  print_r($paymentList);
            $this->return['success'] = 1;
            $this->return['data']    = $paymentList;
        }
        else
        {
            $this->return['data']    = '暂时不支持支付';
        }
        $this->output();
    }
    //搜索商品
    private function select_goods()
    {
        $keyword = IFilter::act(IReq::get('keyword'),'string');
        if(!$keyword)
        {
            $this->return['msg'] = '输入参数不能为空';
            $this->output();
        }
        $resultSet = array();
        //条码搜索
        if(preg_match('#\d{32}#',$keyword))
        {
            $barCodeDB = new IModel('barcode');
            if($goods_info = $barCodeDB->getObj(" bar_code = ".$keyword,'goods_id,product_id'))
            {
                $this->return['data']    = $goods_info;
                $this->return['success'] = 1;
                $this->output();
            }
        }
        //非条码搜索
        else
        {
            //跨境商品不在搜索范围
            $goodsDB = new IQuery('goods');
            $goodsDB->setWhere("is_del = 0 and is_cbe = 0 and  name like '%{$keyword}%'");
            $goodsDB->limit  = 100;
            $goodsDB->fields = 'id,name,sell_price,store_nums';
            $all_goods  = $goodsDB->find();
        }
        // $goodsDB = new IModel('goods');
        if($all_goods)
        {
            //查找每个商品的所有规格
            foreach ($all_goods as $key=>$value)
            {
                $goods_id = $value['id'];
                if(!$goods_id)
                {
                    continue;
                }
                $spec_arr  = $this->search_spec_by_goods_id($goods_id);
                if($spec_arr)
                {
                    foreach ($spec_arr as $key2=>$v)
                    {
                        $resultSet[$key][$key2]['id']         = $v['id']; //goods-id
                        $resultSet[$key][$key2]['product_id'] = $v['product_id'];//product-id
                        $resultSet[$key][$key2]['goods_name'] = $value['name'];//goods-name
                        $resultSet[$key][$key2]['group_price'] = $v['group_price'];//goods-name
                        $resultSet[$key][$key2]['store_nums'] = $value['store_nums'];//store_nums
                        if($spec_arr = json_decode($v['spec_array'],true))
                        {
                            $spec_str = '';
                            foreach ($spec_arr as $spec)
                            {
                                $spec_str .= ' '.$spec['name'].':'.$spec['value'];
                            }
                            //拼接goods_name
                            $resultSet[$key][$key2]['goods_name'] = $value['name'].$spec_str;
                        }
                    }
                }
            }
            $this->return['data'] = $resultSet;
            $this->return['success'] = 1;
        }
        else
        {
            $this->return['data'] = '';
            $this->return['msg']  = '搜索结果为空';
        }
        $this->output();
    }
    //删除订单
    private function del_order()
    {
        $order_id = IFilter::act(IReq::get('order_id'),'int');
        $user_id = IFilter::act(IReq::get('user_id'),'int');
        if(!$order_id && !$user_id)
        {
            $this->return['msg'] = '缺少请求参数';
            $this->output();
        }
        $orderDB = new IModel('order');
        $orderDB->setData(array('if_del'=>1));
        //更新状态
        if($orderDB->update(' id = '.$order_id.' AND user_id = '.$user_id.' AND seller_id = '.$this->get_seller_id()))
        {
            $this->return['success'] = 1;
            $this->return['msg'] = '删除成功';
        }
        $this->output();
    }
    //核销支付订单
    private function check_order()
    {
        $order_id = IFilter::act(IReq::get('order_id'),'int');
        if(!$order_id)
        {
            $this->return['msg'] = '缺少参数';
            $this->output();
        }
        $orderDB = new IModel('order');
        $order  = $orderDB->getObj(' id ='.$order_id.' AND if_del = 0','pay_status,status');
        if($order)
        {
            //判断是否支付成功
            if($order['pay_status'] == 1 && ($order['status'] == 2 || $order['status'] == 5))
            {
                $this->return['success'] = 1;
                $this->return['msg'] = '支付成功';
            }
            else
            {
                $this->return['msg'] = '未支付或者退款订单';
            }
        }
        else
        {
            $this->return['msg'] = '找不到此订单';
        }
        $this->output();
    }
    //挂单转成购物车
    private function hang_to_cart()
    {
        //清空购物车
        $cartObj = new Cart();
        $cartObj->clear();
        $goods_car_id = IFilter::act(IReq::get('gc_id'),'int');
        if(!$goods_car_id)
        {
            $this->return['msg'] = '缺少参数';
            $this->output();
        }
        $hangOrderDB = new IModel('hang_order');
        $goods_car = $hangOrderDB->getObj('id = '.$goods_car_id.' AND seller_id = '.$this->get_seller_id());
        if(!$goods_car)
        {
            $this->return['msg'] = '找不到该挂单';
            $this->output();
        }
        $goods_content_json = $goods_car['content'];
        if($goods_content_json)
        {
            $goods_content_arr = $this->decode($goods_content_json);
            unset($goods_content_arr['normal_ids']);
            //遍历怎么购物车
            foreach ($goods_content_arr as $type => $car)
            {
                if($car)
                {
                    foreach ($car as $gid => $num)
                    {
                        //挂单的时候不算会员价
                        $this->goodsCartFormat($gid,$type,$num,0);
                    }
                }
            }
            //挂单订单转购物车够，删除该挂单
            $hangOrderDB->del(' id = '.$goods_car_id);
            //返回登录会员的username或者mobile
            $username = '';
            if($user_id = $goods_car['user_id'])
            {
                $userInfo = Api::run('getMemberInfo',$user_id);
                $username = $userInfo['mobile']?$userInfo['mobile']:$userInfo['username'];
            }
            $this->return['success'] = 1;
            $this->return['data']['keywords'] = $username;
            $this->output();
        }
    }
    //取消挂单
    private function cancel_hang_order()
    {
        //获取挂单ID
        $goods_car_id =  IFilter::act(IReq::get('gc_id'),'int');
        if(!$goods_car_id)
        {
            $this->return['msg'] = '缺少参数';
            $this->output();
        }
        $hangOrderDB = new IModel('hang_order');
        //删除挂单
        if($hangOrderDB->del('id = '.$goods_car_id.' AND seller_id = '.$this->get_seller_id()))
        {
            $this->return['success'] = 1;
            $this->return['msg'] = '删除成功';
        }
        else
        {
            $this->return['msg'] = '删除失败';
        }
        $this->output();
    }
    //获取挂单列表
    private function get_hang_order()
    {
        $seller_id = $this->get_seller_id();
        $memberDB = new IModel('member');
        $new_arr = array();
        if($seller_id)
        {
            $hangOrderDB = new IModel('hang_order');
            $cartDB = new cart();
            //查找当前商户的所有挂点记录
            $goodsInfo = $hangOrderDB->query(' seller_id = '.$seller_id,'*','create_time desc ');
            if($goodsInfo)
            {
                foreach($goodsInfo as $k=>$car)
                {
                    if ($car)
                    {
                        $car_content = $this->decode($car['content']);
                        $goods_car  = $cartDB->cartFormat($car_content);
                        $new_arr[$k]['id']           = $car['id'];
                        $new_arr[$k]['create_time']  = $car['create_time'];
                        $new_arr[$k]['count']        = $goods_car['count'];
                        $new_arr[$k]['sum']          = $goods_car['sum'];
                        $new_arr[$k]['username']     = $car['user_id']?$memberDB->getObj('user_id = '.$car['user_id'],'true_name')['true_name']:$car['name'];//可以拓展mobile
                        $new_arr[$k]['data']         = $goods_car['goods']['data']+$goods_car['product']['data'];
                        $new_arr[$k]['seller_guide_id'] =   $car['seller_guide_id'];
                    }
                }
                $this->return['success'] = 1;
            }
            else
            {
                $this->return['msg'] = '暂时没有挂单记录';
            }
        }
        else
        {
            $this->return['msg'] = 'seller_id不能为空';
        }
        $this->return['data'] = $new_arr;
        $this->output();
    }
    //挂单
    private function hang_order()
    {
        $seller_id  = $this->get_seller_id();
        $user_id = $this->get_user_id();
        $cartObj = new Cart();
        $cart_info = $cartObj->getMyCart();
        $username = IFilter::act(IReq::get('username'),'string');
        $seller_guide_id = IFilter::act(IReq::get('seller_guide_id'),'int');
        if(!$cart_info['goods']['data'] && !$cart_info['product']['data'])
        {
            $this->return['msg'] = "购物车没有商品，挂单失败";
            $this->output();
        }
        $new_cart = array('goods'=>array(),'product'=>array(),'normal_ids'=>array());
        //判断购物车里面的goods商品
        if($cart_info['goods']['data'])
        {
            foreach ($cart_info['goods']['data'] as $goods)
            {
                $new_cart['goods'][$goods['id']] = $goods['count'];
            }
        }
        //判断购物车里面的product商品
        if($cart_info['product']['data'])
        {
            foreach ($cart_info['product']['data'] as $product)
            {
                $new_cart['product'][$product['id']] = $product['count'];
            }
        }
        $new_cart['normal_ids'] = $new_cart['product']+$new_cart['goods'];
        $hangOrderDB = new IModel('hang_order');
        $updateData = array(
            'user_id'        =>$user_id,//以为这个user_id外键 与user 表 id 主键有外键约束的？如果未登录的会员呢？
            'content'        =>$this->encode($new_cart),
            'create_time'    =>date('Y-m-d H:i:s',time()),
            'seller_id'      =>$seller_id,
            'name'           =>$username,
            'seller_guide_id'=>$seller_guide_id,
        );
        $hangOrderDB->setData($updateData);
        if($hangOrderDB->add())
        {
            $this->return['success'] = 1;
            $this->return['msg'] = '挂单成功';
            $cartObj->clear();
        }
        else
        {
            $this->return['msg'] = '挂单失败';
        }
        //挂单成功登出会员
        $this->user_logout();
        $this->output();
    }
    //商户查看历史订单
    private function get_seller_order()
    {
        $orderDB = new IModel('order');
        $seller_id = $this->get_seller_id();
        //每页显示多少条
        $pageSize = IFilter::act(IReq::get('pageSize'),'int')?IFilter::act(IReq::get('pageSize'),'int'):50000;
        $pageSize = $pageSize < 0 ?10:$pageSize;
        //总条数
        $pageTotal = $orderDB->getObj(' seller_id ='.$seller_id.' AND if_del =0','count(id) as total')['total'];
        //当前是第几页 从第一页开始
        $currentPage = IFilter::act(IReq::get('currentPage'),'int')?IFilter::act(IReq::get('currentPage'),'int'):1;
        $currentPage = $currentPage < 1 ? 1:$currentPage;
        //最大的页数 $pageTotal%$pageSize == 0 ?$pageTotal/$pageSize:ceil($pageTotal/$pageSize)
        $maxPage = floor(($pageTotal-1)/$pageSize)+1;
        $currentPage = $currentPage > $maxPage?$maxPage:$currentPage;
        $limit = ($currentPage-1)*$pageSize.' , '.$pageSize;
        //获取商家的订单
        $pay_status = ['未支付','已支付'];
        $tb_payment = new IModel('payment');
        $orderList  = $orderDB->query(' seller_id ='.$seller_id.' AND if_del = 0','id,user_id,order_no,pay_status,order_amount,accept_name,create_time,province,city,area,address,pay_type','create_time desc',$limit);
        $userobj = new IModel('user');
        if($orderList)
        {
            // 关联表
            $goodsOrderDB = new IQuery('order_goods as o');
            $goodsOrderDB->join = 'left join goods as g on g.id = o.goods_id';
            $goodsOrderDB->fields = "o.goods_array,o.goods_nums,o.goods_price,o.img";
            foreach ($orderList as &$value)
            {
                $order_id             = $value['id'];
                $goodsOrderDB->where  = 'order_id = '.$order_id;
                $value['goods_list']  = $goodsOrderDB->find();
                $value['pay_status']  = $pay_status[$value['pay_status']];//支付状态
                $temp                 = area::name($value['province'],$value['city'],$value['area']);
                $value['province']    =  $temp[$value['province']];
                $value['city']        =  $temp[$value['city']];
                $value['area']        =  $temp[$value['area']];
                $value['member_name'] =  $value['user_id']?Api::run('getMemberInfo',$value['user_id'])['true_name']:'游客';
                $user_info  = $userobj->getObj(' id  = '.$value['user_id'],'username');
                //获取支付方式
                $value['username']    = isset($user_info['username'])?$user_info['username']:'';
                $payment_info = $tb_payment->getObj('id='.$value['pay_type'],'name');
                if($payment_info)
                {
                    $value['pay_name'] = $payment_info['name'];
                }
            }
            $this->return['success'] = 1;
            $this->return['data'] = $orderList;
            $this->return['totalPage'] =  $maxPage;
            $this->return['currentPage'] =  $currentPage;
            $this->return['pageSize'] =  $pageSize;
        }
        else
        {
            $this->return['msg'] = '暂时没有销售数据';
        }
        $this->output();
    }
    //获取地址接口
    private function get_area()
    {
        $parent_id = IFilter::act(IReq::get("pid"),'int');
        $areaDB    = new IModel('areas');
        $data      = $areaDB->query("parent_id=$parent_id",'*','sort asc');
        if($data)
        {
            $this->return['success'] = 1;
            $this->return['data'] = $data;
        }
        else
        {
            $this->return['msg'] = "请求参数错误或者返回结果为空";
        }
        $this->output();
    }
    //收货地址新增或者修改
    private function user_address_update()
    {
        $address_id          = IFilter::act(IReq::get('address_id'),'int');
        $accept_name = IFilter::act(IReq::get('accept_name'),'string');
        $province    = IFilter::act(IReq::get('province'),'string');
        $city        = IFilter::act(IReq::get('city'),'string');
        $area        = IFilter::act(IReq::get('area'),'string');
        $address     = IFilter::act(IReq::get('address'),'string');
        $mobile     = IFilter::act(IReq::get('mobile'),'string');
        $zip         = IFilter::act(IReq::get('zip'),'string');
        if(!$accept_name || !$province || !$city || !$area || !$address || !$mobile)
        {
            $this->return['msg'] = '缺少参数';
            $this->output();
        }
        //如果会员未登录的话，用商家的user_id
        if(!$user_id = $this->get_seller_user_id())
        {
            $this->return['msg'] = '商家未登录';
            $this->output();
        }
        $sqlData = array(
            'user_id'     => $user_id,
            'accept_name' => $accept_name,
            'accept_id'   => 0, //dyg_jzw 20160309 增加身份证号保存
            'zip'         => $zip,
            'province'    => $province,
            'city'        => $city,
            'area'        => $area,
            'mobile'      => $mobile,
            'address'     => $address,
        );
        $model = new IModel('address');
        $model->setData($sqlData);
        if($address_id)
        {
            $model->update("id = ".$address_id." and user_id = ".$user_id);
        }
        else
        {
            $add_id = $model->add();
            $this->return['data']['address_id'] = $add_id;
        }
        $this->return['success'] = 1;
        $this->output();
    }
    //获取商家对应的user_id，解决会员未登录一系列问题
    private function  get_seller_user_id()
    {
        if(!$this->get_user_id())
        {
            $seller_id = $this->get_seller_id();
            $sellerDB = new IModel('seller');
            if($seller_user = $sellerDB->getObj(' id = '.$seller_id,'user_id'))
            {
                return $seller_user['user_id'] ? $seller_user['user_id'] : 0;
            }
        }
        return $this->get_user_id();
    }
    //获取user_id
    private function get_user_id()
    {
        return ISafe::get('cash_user_id','cookie')?intval(ISafe::get('cash_user_id','cookie')):0;
    }
    //设置会员ID
    private function set_user_id($user_id)
    {
        ISafe::set('cash_user_id',$user_id,'cookie');
    }
    //设置seller_id
    private function set_seller_id($seller_id)
    {
        ISafe::set('cash_seller_id',$seller_id,'cookie');
    }
    //获取seller_id
    private function get_seller_id()
    {
        //商家登录之后会保存
        return ISafe::get('cash_seller_id','cookie');
    }
    //会员登出
    public function user_logout()
    {
        if($this->get_user_id())
        {
            ISafe::clear('cash_user_id','cookie');
            $this->return['msg'] = "删除成功";
            $this->return['success'] = 1;
        }
        $this->output();
    }
    //收货地址
    public function get_user_address()
    {
        $user_id = $this->get_user_id();
        //获取收货地址
        //会员地址
        $seller_id = $this->get_seller_id();//商家登录的时候会有seller_id
        $addressObj  = new IModel('address');
        $addressList = $addressObj->query('user_id = '.$user_id,"*",' is_default desc');
        //商家地址
        $sellerObj  = new IModel('seller');
        $sellerressList = $sellerObj->getObj('id = '.$seller_id,"id,true_name, mobile, phone, country, province, city, area, address");
        //更新$addressList数据
        $address_info['member_info'] = $addressList;
        $address_info['seller_info'] = $sellerressList;
        if($addressList || $sellerressList)
        {
            foreach ($address_info as $model=>$address)
            {
                //城市code
                if($model == 'member_info')
                {
                    foreach ($address as $key=>$value)
                    {
                        $temp = area::name($value['province'],$value['city'],$value['area']);
                        if(isset($temp[$value['province']]) && isset($temp[$value['city']]) && isset($temp[$value['area']]))
                        {
                            $address_info[$model][$key]['province_val'] = $temp[$value['province']];
                            $address_info[$model][$key]['city_val']     = $temp[$value['city']];
                            $address_info[$model][$key]['area_val']     = $temp[$value['area']];
                        }
                    }
                }
                else
                {
                    $temp = area::name($address['province'],$address['city'],$address['area']);
                    if(isset($temp[$address['province']]) && isset($temp[$address['city']]) && isset($temp[$address['area']]))
                    {
                        $address_info[$model]['province_val'] = $temp[$address['province']];
                        $address_info[$model]['city_val']     = $temp[$address['city']];
                        $address_info[$model]['area_val']     = $temp[$address['area']];
                    }
                }
            }
            $this->return['success'] = 1;
            $this->return['data'] = $address_info;
        }
        else
        {
            $this->return['msg'] = "没有任何地址";
        }
        $this->output();
    }
    //清空购物车
    public function clear_car()
    {
        $cartObj = new Cart();
        $cartObj->clear();
        $this->return['success'] = 1;
        $this->return['msg'] = "购物车清空成功";
        $this->output();
    }
    //预存款验证接口
    private function check_balance()
    {
        $pay_psw = IFilter::act(IReq::get('pay_psw'),'string');
        $user_id = $this->get_user_id();
        $user_info = Api::run('getMemberInfo',$user_id);
        $userObj = new IModel('user');
        $username = $userObj->getObj('id = '.$user_id,'username')['username'];
        if(!plugin::trigger("isValidUser",array($user_info['email'],md5($pay_psw))) && !plugin::trigger("isValidUser",array($username,md5($pay_psw))))
        {
            $this->return['msg']    = '支付密码错误';
        }
        else
        {
            $this->return['success'] = 1;
            $this->return['msg']     = '支付密验证成功';
        }
        $this->output();
    }
    //支付
    private function go_to_pay()
    {
        //订单ID
        $order_id      = IFilter::act(IReq::get('order_id'),'int');
        //支付方式
        $payment_id      = IFilter::act(IReq::get('payment_id'),'int');

        //获取订单信息
        $orderDB = new IModel("order");
        $orderRow = $orderDB->getObj("id = ".$order_id.' AND if_del = 0');

        //修改订单支付方式
        $orderDB->setData(array('pay_type'=>$payment_id));
        $orderDB->update(' id = '.$order_id);

        if ($orderRow)
        {
            //如果是预存款，需要输入密码验证
            $pay_psw = IFilter::act(IReq::get('pay_psw'),'string');
            if($payment_id == 1 &&  !$pay_psw)
            {
                $this->return['msg'] = '必须填写预付款支付密码';
                $this->output();
            }
            if($payment_id == 1 && $pay_psw)
            {
                $user_info = Api::run('getMemberInfo',$orderRow['user_id']);
                $userObj = new IModel('user');
                $username = $userObj->getObj('id = '.$orderRow['user_id'],'username')['username'];
                if(!plugin::trigger("isValidUser",array($user_info['email'],md5($pay_psw))) && !plugin::trigger("isValidUser",array($username,md5($pay_psw))))
                {
                    $this->return['msg'] = '支付密码错误';
                    $this->output();
                }
            }
            //是否已支付
            if ($orderRow['pay_status'] > 0)
            {
                $this->return['msg'] = "该订单已经支付了";
                $this->output();
            }
            elseif($orderRow['status'] != 1)
            {
                $this->return['msg'] = "订单已被取消，无法支付";
                $this->output();
            }
            else
            {
                $paymentObj = new IModel('payment');
                $paymentRow = $paymentObj->getObj('id = '.$payment_id.' and (status = 0 or id = 0)','type,name');
                if(!$paymentRow)
                {
                    $this->return['msg'] = "该支付方式不存在或不支持";
                    $this->output();
                }
                // 货到付款 （现金支付）
                if($payment_id == 0)
                {
                    Order_Class::updateOrderStatus($orderRow['order_no']);
                    $this->return['success'] = 1;
                    $this->return['msg'] = '现金支付成功';
                    $this->output();
                }
                //调用支付插件
                $paymentInstance = Payment::createPaymentInstance($payment_id);
                $sendData = $paymentInstance->getSendData(Payment::getPaymentInfo($payment_id,'order',array($order_id)));
                if($sendData)
                {
                    $this->return['data']['order_id']  = $order_id;
                    //是否跳转
                    $this->return['data']['is_skip']    = 0;
                    $this->return['success'] = 1;
                    //返回二维码形式
                    if($payment_id == 13) //微信支付
                    {
                        $this->return['data']['code_img_url'] = "http://s.jiathis.com/qrcode.php?url=".$sendData['code_url'];
                    }
                    //直接跳转形式
                    elseif($payment_id == 8) //支付宝支付
                    {
                        if(IFilter::act(IReq::get('isPay'),'int') == 1)
                        {
                            $paymentInstance->dopay($sendData);
                        }
                        $this->return['data']['is_skip'] = 1;
                        //跳转链接
                        $this->return['data']['pay_url'] = IUrl::getUrl().'?order_id='.$order_id.'&payment_id='.$payment_id.'&isPay=1';
                    }
                    elseif($payment_id == 19) //兴业银行微信支付
                    {
                        $this->return['data']['code_img_url'] = $sendData['code_img_url'];
                    }
                    elseif($payment_id == 20) //兴业银行支付宝
                    {
                        $this->return['data']['code_img_url'] = $sendData['code_img_url'];
                    }
                    elseif ($payment_id == 22) // 直付通
                    {
                        if(IFilter::act(IReq::get('isPay'),'int') == 1)
                        {
                            $paymentInstance->dopay($sendData);
                        }
                        $this->return['data']['is_skip'] = 1;
                        //跳转链接
                        $this->return['data']['pay_url'] = IUrl::getUrl().'?order_id='.$order_id.'&payment_id='.$payment_id.'&isPay=1';
                    }
                    //预存款
                    elseif ($payment_id == 1)
                    {
                        $payResult       = $paymentInstance->callback($sendData,$payment_id,$money,$message,$orderNo);
                        if($payResult == false)
                        {
                            $this->return['success'] = 0;
                            $this->return['msg'] = $message;
                            $this->output();
                        }
                        $user_id = $orderRow['user_id'];
                        $memberObj = new IModel('member');
                        $memberRow = $memberObj->getObj('user_id = '.$user_id);

                        if(empty($memberRow))
                        {
                            $this->return['success'] = 0;
                            $this->return['msg'] = '用户信息不存在';
                            $this->output();
                        }
                        if($memberRow['balance'] < $sendData['total_fee'])
                        {
                            $this->return['success'] = 0;
                            $this->return['msg'] = '账户余额不足';
                            $this->output();
                        }
                        $orderObj = new IModel('order');
                        $orderRow = $orderObj->getObj('order_no  = "'.$sendData['order_no'].'" and pay_status = 0 and status = 1 and user_id = '.$user_id);
                        if(!$orderRow)
                        {
                            $this->return['success'] = 0;
                            $this->return['msg'] = '订单号【'.$sendData['order_no'].'】已经被处理过，请查看订单状态';
                            $this->output();
                        }
                        $logObj = new AccountLog();
                        $config = array(
                            'user_id'  => $user_id,
                            'event'    => 'pay',
                            'num'      => $sendData['total_fee'],
                            'order_no' => str_replace("_",",",$sendData['attach']),
                        );
                        $is_success = $logObj->write($config);
                        if(!$is_success)
                        {
                            $orderObj->rollback();
                            $this->return['success'] = 0;
                            $this->return['msg'] = $logObj->error. '用户余额更新失败';
                            $this->output();
                        }
                        $moreOrder = Order_Class::getBatch($orderNo);
                        if($money >= array_sum($moreOrder))
                        {
                            foreach($moreOrder as $key => $item)
                            {
                                $order_id = Order_Class::updateOrderStatus($key);
                                if(!$order_id)
                                {
                                    $orderObj->rollback();
                                    $this->return['success'] = 0;
                                    $this->return['msg'] = '订单修改失败';
                                    $this->output();
                                }
                            }
                        }
                        else
                        {
                            $orderObj->rollback();
                            $this->return['success'] = 0;
                            $this->return['msg'] = '付款金额与订单金额不符合';
                            $this->output();
                        }
                        $this->return['success'] = 1;
                        $this->return['msg'] = '余额支付成功';
                        $this->output();
                    }
                    else
                    {
                        $this->return['msg'] = '现金支付成功';
                    }
                }
                else
                {
                    $this->return['msg'] = '支付请求失败';
                }
                $this->output();
            }
        }
    }
    //生成订单
    private function new_order()
    {
        //默认是微信二维码支付 $payment = 8 支付宝
        $payment   = IFilter::act(IReq::get('payment_id'),'int');
        $delivery_id   = IFilter::act(IReq::get('delivery_id'),'int')?IFilter::act(IReq::get('delivery_id'),'int'):3;
        $ticket_id     = IFilter::act(IReq::get('ticket_id'),'int')?IFilter::act(IReq::get('ticket_id'),'int'):null;
        $user_id = $this->get_user_id();
        $gid = 0;
        $member_address_id    = IFilter::act(IReq::get('member_address_id'),'int');//会员地址
        $seller_address_id    = IFilter::act(IReq::get('seller_address_id'),'int');//商家地址
        //计算购物车
        $countSumObj = new CountSum($user_id);
        $goodsResult = $countSumObj->cart_count();//直接获取购物车的商品进行结算
        if($countSumObj->error)
        {
            $this->return['msg'] = $countSumObj->error;
            $this->output();
        }
        if($seller_address_id)
        {
            $sellerDB = new IModel('seller');
            $address_info = $sellerDB->getObj('id = '.$seller_address_id,'seller_name,true_name,email,mobile,phone,province,city,area,address');
        }
        elseif($member_address_id)
        {
            $addressDB = new IModel('address');
            $address_info= $addressDB->getObj('id = '.$member_address_id);
        }
        else
        {
            $this->return['msg'] = "参数不完整";
            $this->output();
        }

        if(!$address_info)
        {
            $this->return['msg'] = "收货地址不存在";
            $this->output();
        }
        //判断地址是否是一个有效的地址
        $is_address_correct = Api::run('getAreasName', array($address_info['province'], $address_info['city'], $address_info['area']));
        if (count($is_address_correct) < 3)
        {
            $this->return['msg'] = "您的收货地址由于数据更新已失效，请重新修改更新地址";
            $this->output();
        }
        $accept_name   = isset($address_info['true_name'])?$address_info['true_name']:$address_info['accept_name'];
        $province      = $address_info['province'];
        $city          = $address_info['city'];
        $area          = $address_info['area'];
        $address       = $address_info['address'];
        $mobile        = $address_info['mobile'];
        $telphone      = isset($address_info['phone'])?$address_info['phone']:$address_info['telphone'];
        $zip           = '';
        $accept_id     = '';//收货人身份证号码
        $active_id      = '';//促销活动ID
        if (empty($province) || empty($city) || empty($area) || empty($address) || empty($mobile))
        {
            $this->return['msg'] = "地址信息不完整，请重新填写";
            $this->output();
        }
        //检查订单重复
        $checkData = array(
            "accept_name" => $accept_name,
            "address"     => $address,
            "mobile"      => $mobile,
            "distribution"=> $delivery_id,
        );
        $result = order_class::checkRepeat($checkData,$goodsResult['goodsList']);
        if( is_string($result) )
        {
            $this->return['msg'] =  $result;
            $this->output();
        }
        if(!$gid)
        {
            //清空购物车
            IInterceptor::reg("cart@onFinishAction");
        }
        //判断商品是否存在
        if(is_string($goodsResult) || empty($goodsResult['goodsList']))
        {
            $this->return['msg'] =  '商品数据错误';
            $this->output();
        }
        //最终订单金额计算
        $taxes = 0;
        $orderData = $countSumObj->countOrderFee($goodsResult,$province,$delivery_id,$payment,$taxes,0);
        if(is_string($orderData))
        {
            $this->return['msg'] =  $orderData;
            $this->output();
        }
        foreach($orderData as $seller_id => $goodsResult)
        {
            //生成的订单数据
            $dataArray = array(
                'order_no'            => Order_Class::createOrderNum(),
                'user_id'             => $user_id,
                'accept_name'         => $accept_name,
                'accept_id'           => $accept_id, //dyg_jzw 20160309 增加跨境电商必须的身份证信息
                'pay_type'            => $payment,
                'distribution'        => $delivery_id,
                'postcode'            => $zip,
                'telphone'            => $telphone,
                'province'            => $province,
                'city'                => $city,
                'area'                => $area,
                'address'             => $address,
                'mobile'              => $mobile,
                'create_time'         => ITime::getDateTime(),
                'postscript'          => '收银端支付',
                'accept_time'         => '任意',
                'exp'                 => $goodsResult['exp'],
                'point'               => $goodsResult['point'],
                'type'                => 0,

                //商品价格
                'payable_amount'      => $goodsResult['sum'],
                'real_amount'         => $goodsResult['final_sum'],

                //运费价格
                'payable_freight'     => $goodsResult['deliveryOrigPrice'],
                'real_freight'        => $goodsResult['deliveryPrice'],

                //手续费
                'pay_fee'             => $goodsResult['paymentPrice'],

                //税金
                'invoice'             => 0,
                'invoice_title'       => '',
                'taxes'               => $goodsResult['taxPrice'],

                //优惠价格
                'promotions'          => $goodsResult['proReduce'] + $goodsResult['reduce'],

                //订单应付总额
                'order_amount'        => $goodsResult['orderAmountPrice'],

                //订单保价
                'insured'             => $goodsResult['insuredPrice'],

                //自提点ID
                'takeself'            => 0,

                //促销活动ID
                'active_id'           => $active_id,

                //商家ID
                'seller_id'           => $this->get_seller_id(),

                //备注信息
                'note'                => '',

                //dyg_jzw 20150819 佣金合计
                'commission'          => $goodsResult['order_commission'],

                //dyg_jzw 20161215 高返自身返利
                'my_commission'          => $goodsResult['order_my_commission'],

                //dyg_jzw 20160307 是否跨境订单
                'is_cbe'              => $goodsResult['is_cbe'],
            );

            //消耗优惠券
            if($ticket_id)
            {
                $memberObj = new IModel('member');
                $memberRow = $memberObj->getObj('user_id = '.$this->get_user_id(),'prop,custom');
                if(!$memberRow)
                {
                    $this->return['msg'] = "没有优惠券";
                    $this->output();
                }
                if(stripos(','.trim($memberRow['prop'],',').',',','.$ticket_id.',')!==false)
                {
                    $ticketRow = Api::run('checkTicketUseful', $ticket_id, $goodsResult, $seller_id);
                    if(isset($ticketRow['can_use']) && $ticketRow['can_use'])
                    {
                        $propObj   = new IModel('prop');
                        //抵扣处理
                        $ticketRow['value']         = $ticketRow['value'] >= $goodsResult['final_sum'] ? $goodsResult['final_sum'] : $ticketRow['value'];
                        $dataArray['prop']          = $ticket_id;
                        $dataArray['promotions']   += $ticketRow['value'];
                        $dataArray['order_amount'] -= $ticketRow['value'];
                        $goodsResult['promotion'][] = array("plan" => "代金券","info" => "使用了￥".$ticketRow['value']."代金券");
                        //锁定红包状态
                        $propObj->setData(array('is_close' => 2));
                        $propObj->update('id = '.$ticket_id);
                        //dyg_jzw 20150819 使用代金卷 取消返佣
                        $dataArray['commission'] = 0;
                    }
                    else
                    {
                        $this->return['msg'] = '代金券不可用,不符合条件';
                        $this->output();
                    }
                }
            }
            $dataArray['order_amount'] = $dataArray['order_amount'] <= 0 ? 0 : $dataArray['order_amount'];
            //生成订单插入order表中
            //!$goodsResult['is_cbe']  海外购 商品生产订单情况如何？
            if ($dataArray)
            {
                $orderObj  = new IModel('order');
                $orderObj->setData($dataArray);
                $order_id = $orderObj->add();
            }
            if(!isset($order_id) || $order_id == false)
            {
                $this->return['msg'] =  '订单生成错误';
                $this->output();
            }
            /*将订单中的商品插入到order_goods表*/
            $orderInstance = new Order_Class();
            $orderInstance->insertOrderGoods($order_id,$goodsResult['goodsResult']);
        }
        //更新导购员与订单关系表
        $seller_guide_id = IFilter::act(IReq::get('seller_guide_id'),'int');
        $guideOrderRelationDB = new IModel('guide_order_relation');
        $guideOrderRelationDB->setData(array('seller_guide_id'=>$seller_guide_id,'order_id'=>$order_id));
        $guideOrderRelationDB->add();

        $this->return['success'] = 1;
        $this->return['data'] = array('order_id'=>$order_id,'payment_id'=>$payment);
        $this->output();
    }
    /**
     * input  Goods_no
     * 通过goods_id查找规格
     */
    private function search_spec_by_goods_id($goods_id)
    {
        $goods_info = array();
        $user_id = $this->get_user_id();
        $goodsDB = new  IModel('goods');
        $tb_products = new IModel('products');
        // 根据商品goods_id 搜索商品
        $products_info_all = $tb_products->query("goods_id = ".$goods_id, 'id, products_no, spec_array, store_nums, market_price, sell_price, weight, point'); //dyg_jzw 20170220 增加积分字段
        $countsumInstance = new countsum($user_id);
        if (count($products_info_all) > 0)
        {
            //存在一个或多个规格的商品
            foreach ($products_info_all as $_key => $_pro_spec)
            {
                //spec_array 规格列表
                $goods_info[$_key] = array(
                    'id' => $goods_id,
                    'product_id' => $_pro_spec['id'],
                    'spec_array' => $_pro_spec['spec_array'],
                    'products_no' => $_pro_spec['products_no'],
                    'market_price' => $_pro_spec['market_price'],
                    'sell_price' => $_pro_spec['sell_price'],
                );
                //根据每个规格获取对应的会员价
                $group_price = $countsumInstance->getGroupPrice($_pro_spec['id'], 'product');//根据product_id 获取会员价格
                if ($group_price)
                {
                    //如果有分组价格，就使用分组价格，没有的话，就用销售价
                    $goods_info[$_key]['group_price'] = $group_price;
                }
                else
                {
                    $goods_info[$_key]['group_price'] = $_pro_spec['sell_price'];
                }
            }
        }
        else
        {
            //没有sku的商品
            $all_goods = $goodsDB->getObj('id = '.$goods_id.' and is_del =0','id,goods_no,spec_array, store_nums, market_price, sell_price, weight, point');
            if($all_goods)
            {
                //商品规格中插入价格
                unset($all_goods['weight']);//去掉商品重量属性
                unset($all_goods['point']);//去掉商品积分属性
                unset($all_goods['store_nums']);//去掉商品库存属性
                $countsumInstance = new countsum($user_id);
                $all_goods['product_id'] = 0;
                $all_goods['group_price'] = $countsumInstance->getGroupPrice($goods_id, 'goods');//根据goods_id 获取会员价格
                if(!$all_goods['group_price'])
                {
                    $all_goods['group_price']  = $all_goods['sell_price'];
                }
                $goods_info[0] = $all_goods;
            }
        }
        return $goods_info;
    }
    /**
     * 查找用户会员并模拟登记
     */
    public function search_user()
    {
        $keywords =  IFilter::act(IReq::get('keywords'),'string');
        if(!$keywords)
        {
            $this->return['msg'] =  "商品条码或者会员手机号不能为空";
            $this->output();
        }
        //采用这种比较慢的联合查询，没有逐条写效率那么高，但是看起来逼格高一点
        //username（搜索user）或mobile（搜索member）
        $userDB = new IModel('user');
        $user_arr= $userDB->getObj('username = '.$keywords,'id');
        if($user_arr)
        {
            $user_id = $user_arr['id'];
        }
        else
        {
            $memberDB = new IModel('member');
            //----------有必要加入缓存？ ---------//
            $member_arr = $memberDB->getObj('mobile = '.$keywords,'user_id');
            if($member_arr)
            {
                $user_id = $member_arr['user_id'];
            }
            else
            {
                $this->return['msg'] =  "该用户不存在";
                $this->output();
            }
        }
        $this->set_user_id($user_id);
        $user_info = Api::run('getMemberInfo',$user_id);
        $this->return['success'] = 1;
        unset($user_info['password']);
        unset($user_info['id']);
        unset($user_info['user_id']);
        //过滤一些空元素
        $this->return['data'] = $user_info;
        $this->output();
    }
    /**
     * 通过goods_id 、goods_type 进行购物车删除
     */
    public function remove_cart()
    {
        $goods_id  = IFilter::act(IReq::get('goods_id'),'int');
        $product_id  = IFilter::act(IReq::get('product_id'),'int');
        $type = $product_id?'product':'goods';
        $goods_id = $product_id?$product_id:$goods_id;
        if(!$goods_id)
        {
            $this->return['msg'] = "必要参数不能少";
            $this->output();
        }
        $cartObj   = new Cart();
        $delResult = $cartObj->del($goods_id,$type);
        if($delResult === false)
        {
            $this->return['msg'] = "购物车中没有此商品";
        }
        else
        {
            $this->return['success'] = 1;
            $this->return['msg'] = "删除购物车成功";
        }
        $this->output();
    }
    /**
     * @return json
     * 模拟商品添加到购物车
     * 传入参数$goods_id，$goods_num，$type
     * $goods_id 其实是product ID
     */
    public function shop_cart()
    {
        $goods_id     = IFilter::act(IReq::get('goods_id'),'int');//商品的ID
        $goods_num    = IFilter::act(IReq::get('goods_num'),'int');//购买商品的数量
        $product_id   = IFilter::act(IReq::get('product_id'),'int');//商品对应的规格ID
        //非添加购物车操作，直接返回整个购物车商品
        $type         = $product_id ? 'product' : 'goods';//判断类型
        $goods_id     = $product_id ? $product_id : $goods_id;//判断类型
        $user_id      = $this->get_user_id();
        $this->return = $this->goodsCartFormat($goods_id,$type,$goods_num,$user_id);
        $this->output();
    }
    /**
     * 创建购物车
     * @param $goods_id
     * @param $product_id
     * @param $goods_num
     * @param $user_id
     */
    private function goodsCartFormat($goods_id,$type,$goods_num,$user_id)
    {
        $return['total'] = 0;
        $result_all = array();
        $return['success'] = 1;
        if (!$goods_id && !$goods_num)
        {
            $countSumObj = new CountSum($user_id);
            $result_all = $countSumObj->cart_count();
            $return['data'] = $result_all['goodsList'];
        }
        else
        {
            $cartObj = new Cart();
            //添加购物车失败，并抛出异常
            if ($cartObj->add($goods_id, $goods_num, $type) === false)
            {
                $return['success'] = 0;
                $return['msg'] = $cartObj->getError();
            }
            else
            {
                $countSumObj = new CountSum($user_id);
                $result_all = $countSumObj->cart_count();
                $return['data'] = $result_all['goodsList'];
            }
        }
        if(isset($return['data']) && $return['data'])
        {
            foreach ($return['data'] as $key=>&$goods)
            {
                unset($goods['cost_price'],$goods['point'],$goods['exp'],$goods['is_cbe'],$goods['commission'],$goods['store_nums'],$goods['my_commission'],$goods['weight'],$goods['seller_id']);
                $goods['group_price'] = bcsub($goods['sell_price'],$goods['reduce'],2);
            }
            $return['total'] = $result_all['final_sum'];
        }
        return $return;
    }
    //购物车存储数据加码
    private function encode($data)
    {
        return str_replace(array('"',','),array('&','$'),JSON::encode($data));
    }
    //购物车存储数据解码
    private function decode($data)
    {
        return JSON::decode(str_replace(array('&','$'),array('"',','),$data));
    }
    public static function install()
    {
        //创建挂单表
        $hangOrderDB = new IModel('hang_order');
        if(!$hangOrderDB->exists())
        {
            $data = array(
                "comment" => '商户挂单表',
                "column"  => array(
                    "id"         	=> array("type" => "int(11) unsigned",'auto_increment' => 1),
                    "user_id"       => array("type" => "int(11)","comment" => "会员ID"),
                    "seller_id"   	=> array("type" => "int(11)","comment" => "商户ID"),
                    "content"       => array("type" => "varchar(255)","comment" => "挂单详情"),
                    "create_time"   => array("type" => "varchar(50)","comment" => "挂单时间"),
                    "operator_id"   => array("type" => "int(11)",'default'=>0,"comment" => "操作人ID"),
                    "name"          => array("type" => "varchar(50)",'default'=>'null',"comment" => "非登录会员"),
                    "seller_guide_id"   => array("type" => "int(11)",'default'=>'0',"comment" => "导购员ID"),
                ),
                "index" => array("primary" => "id"),
            );
            $hangOrderDB->setData($data);
            $hangOrderDB->createTable();
        }
        //插入导购员与挂单关系表
        $shoppingGuideDB = new IModel('shopping_guide');
        if(!$shoppingGuideDB->exists())
        {
            $data = array(
                "comment" => '商户导购员表',
                "column"  => array(
                    "id"         	      => array("type" => "int(11) unsigned",'auto_increment' => 1),
                    "seller_id"           => array("type" => "int(11)","comment" => "商户ID"),
                    "guide_name"          => array("type" => "varchar(50)","comment" => "导购员名字"),
                    "guide_income"        => array("type" => "decimal(9,2)",'default'=>'0.00',"comment" => "导购员收益"),
                    "guide_amount"        => array("type" => "decimal(9,2)",'default'=>'0.00',"comment" => "导购员销售总额"),
                    "guide_create_time"   => array("type" => "timestamp default CURRENT_TIMESTAMP","comment" => "创建时间"),
                ),
                "index" => array("primary" => "id"),
            );
            $shoppingGuideDB->setData($data);
            $shoppingGuideDB->createTable();
        }
        //插入导购员与订单关系表
        $guideOrderRelationDB = new IModel('guide_order_relation');
        if(!$guideOrderRelationDB->exists())
        {
            $data = array(
                "comment" => '导购员订单关系表',
                "column"  => array(
                    "id"               => array("type" => "int(11) unsigned",'auto_increment' => 1),
                    "seller_guide_id"  => array("type" => "int(11)","comment" => "导购员ID"),
                    "order_id"         => array("type" => "varchar(20)","comment" => "订单ID"),
                ),
                "index" => array("primary" => "id"),
            );
            $guideOrderRelationDB->setData($data);
            $guideOrderRelationDB->createTable();
        }
        return true;
    }
    public static function uninstall()
    {
        $shoppingGuideDB = new IModel('shopping_guide');
        $hangOrderDB = new IModel('hang_order');
        $guideOrderRelationDB = new IModel('guide_order_relation');
        if($hangOrderDB->exists())
        {
            $hangOrderDB->dropTable();
        }
        if($shoppingGuideDB->exists())
        {
            $shoppingGuideDB->dropTable();
        }
        if($guideOrderRelationDB->exists())
        {
            $guideOrderRelationDB->dropTable();
        }
        return true;
    }
    /**
     * @brief 插件名字
     * @return string
     */
    public static function name()
    {
        return "网页端收银系统";
    }
    /**
     * @brief 插件功能描述
     * @return string
     */
    public static function description()
    {
        return "商家通过收银系统进行线下收款";
    }
    public static function configName()
    {
        return array();
    }
    /**
     * 输出json格式
     */
    private function output()
    {
        echo JSON::encode($this->return);exit;
    }
}
