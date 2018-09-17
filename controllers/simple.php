<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file Simple.php
 * @brief
 * @author webning
 * @date 2011-03-22
 * @version 0.6
 * @note
 */
/**
 * @brief Simple
 * @class Simple
 * @note
 */
class Simple extends IController
{
    public $layout='site_mini';

    function init()
    {

    }

    function login()
    {
        //如果已经登录，就跳到ucenter页面
        if($this->user)
        {
            $this->redirect("/ucenter/index");
        }
        else
        {
            $this->redirect('login');
        }
    }

    //退出登录
    function logout()
    {
        plugin::trigger('clearUser');
        $this->redirect('login');
    }

    //用户注册
    function reg_act()
    {
        //调用_userInfo注册插件
        $result = plugin::trigger("userRegAct",$_POST);
        if(is_array($result))
        {
            //自定义跳转页面
            $this->redirect('/site/success?message='.urlencode("注册成功！"));
        }
        else
        {
            $this->email = $_POST['email'];
            $this->username = $_POST['username'];
            $this->inviter = $_POST['inviter'];
            $this->setRenderData(array('email'=>$this->email,'username'=>$this->username,'inviter'=>$this->inviter));
            $this->setError($result);
            $this->redirect('reg',false);
            Util::showMessage($result);
        }
    }

    //用户登录
    function login_act()
    {
        //调用_userInfo登录插件
        $result = plugin::trigger('userLoginAct',$_POST);
        if(is_array($result))
        {
            //自定义跳转页面
            $callback = plugin::trigger('getCallback');
            if($callback)
            {
                $this->redirect($callback);
            }
            else
            {
                $this->redirect('/ucenter/index');
            }
        }
        else
        {
            $this->setError($result);
            $this->redirect('login',false);
            Util::showMessage($result);
        }
    }

    //商品加入购物车[ajax]
    function joinCart()
    {
        $link      = IReq::get('link');
        $goods_id  = IFilter::act(IReq::get('goods_id'),'int');
        $goods_num = IReq::get('goods_num') === null ? 1 : intval(IReq::get('goods_num'));
        $type      = IFilter::act(IReq::get('type'));

        //加入购物车
        $cartObj   = new Cart();
        $addResult = $cartObj->add($goods_id,$goods_num,$type);

        if($link != '')
        {
            if($addResult === false)
            {
                $this->cart(false);
                Util::showMessage($cartObj->getError());
            }
            else
            {
                $this->redirect($link);
            }
        }
        else
        {
            if($addResult === false)
            {
                $result = array(
                    'isError' => true,
                    'message' => $cartObj->getError(),
                );
            }
            else
            {
                $result = array(
                    'isError' => false,
                    'message' => '添加成功',
                );
            }
            echo JSON::encode($result);
        }
    }

    //根据goods_id获取货品
    function getProducts()
    {
        $id           = IFilter::act(IReq::get('id'),'int');
        $productObj   = new IModel('products');
        $productsList = $productObj->query('goods_id = '.$id,'sell_price,id,spec_array,goods_id','store_nums desc',7);
        if($productsList)
        {
            foreach($productsList as $key => $val)
            {
                $productsList[$key]['specData'] = Block::show_spec($val['spec_array']);
            }
        }
        echo JSON::encode($productsList);
    }

    //删除购物车
    function removeCart()
    {
        $link      = IReq::get('link');
        $goods_id  = IFilter::act(IReq::get('goods_id'),'int');
        $type      = IFilter::act(IReq::get('type'));

        $cartObj   = new Cart();
        $cartInfo  = $cartObj->getMyCart();
        $delResult = $cartObj->del($goods_id,$type);

        if($link != '')
        {
            if($delResult === false)
            {
                $this->cart(false);
                Util::showMessage($cartObj->getError());
            }
            else
            {
                $this->redirect($link);
            }
        }
        else
        {
            if($delResult === false)
            {
                $result = array(
                    'isError' => true,
                    'message' => $cartObj->getError(),
                );
            }
            else
            {
                $goodsRow = $cartInfo[$type]['data'][$goods_id];
                $cartInfo['sum']   -= $goodsRow['sell_price'] * $goodsRow['count'];
                $cartInfo['count'] -= $goodsRow['count'];

                $result = array(
                    'isError' => false,
                    'data'    => $cartInfo,
                );
            }

            echo JSON::encode($result);
        }
    }

    //清空购物车
    function clearCart()
    {
        $cartObj = new Cart();
        $cartObj->clear();
        $this->redirect('cart');
    }

    //购物车div展示
    function showCart()
    {
        $cartObj  = new Cart();
        $cartList = $cartObj->getMyCart();
        $data['data'] = array_merge($cartList['goods']['data'],$cartList['product']['data']);
        $data['count']= $cartList['count'];
        $data['sum']  = $cartList['sum'];
        echo JSON::encode($data);
    }

    //购物车页面及商品价格计算[复杂]
    function cart($redirect = false)
    {
        //防止页面刷新
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);

        //开始计算购物车中的商品价格
        $countObj = new CountSum();
        $result   = $countObj->cart_count();

        if(is_string($result))
        {
            IError::show($result,403);
        }

        //返回值
        $this->final_sum = $result['final_sum'];
        $this->promotion = $result['promotion'];
        $this->proReduce = $result['proReduce'];
        $this->sum       = $result['sum'];
        $this->goodsList = $result['goodsList'];
        $this->count     = $result['count'];
        $this->reduce    = $result['reduce'];
        $this->weight    = $result['weight'];

        //渲染视图
        $this->redirect('cart',$redirect);
    }

    //计算促销规则[ajax]
    function promotionRuleAjax()
    {
        $goodsId   = IFilter::act(IReq::get("goodsId"),'int');
        $productId = IFilter::act(IReq::get("productId"),'int');
        $num       = IFilter::act(IReq::get("num"),'int');

        if(!$goodsId || !$num)
        {
            return;
        }
        $goodsArray  = array();
        $productArray= array();

        foreach($goodsId as $key => $goods_id)
        {
            $pid = $productId[$key];
            $nVal= $num[$key];

            if($pid > 0)
            {
                $productArray[$pid] = $nVal;
            }
            else
            {
                $goodsArray[$goods_id] = $nVal;
            }
        }

        $countSumObj    = new CountSum();
        $cartObj        = new Cart();
        $countSumResult = $countSumObj->goodsCount($cartObj->cartFormat(array("goods" => $goodsArray,"product" => $productArray)));
        echo JSON::encode($countSumResult);
    }


    //填写订单信息cart2
    function cart2()
    {
        $id        = IFilter::act(IReq::get('id'),'int');
        $type      = IFilter::act(IReq::get('type'));//goods,product
        $promo     = IFilter::act(IReq::get('promo'));
        $active_id = IFilter::act(IReq::get('active_id'),'int');
        $buy_num   = IReq::get('num') ? abs(IFilter::act(IReq::get('num'),'int')) : 1;
        $tourist   = IReq::get('tourist');//游客方式购物

        //dyg_jzw 20160303 是否跨境电商支付
        $this->is_cbe = false;

        //必须为登录用户
        if($tourist === null && $this->user['user_id'] == null)
        {
            if($id == 0 || $type == '')
            {
                $this->redirect('/simple/login?tourist&callback=/simple/cart2');
            }
            else
            {
                $url  = '/simple/login?tourist&callback=/simple/cart2/id/'.$id.'/type/'.$type.'/num/'.$buy_num;
                $url .= $promo     ? '/promo/'.$promo         : '';
                $url .= $active_id ? '/active_id/'.$active_id : '';
                $this->redirect($url);
            }
        }

        //游客的user_id默认为0
        $user_id = ($this->user['user_id'] == null) ? 0 : $this->user['user_id'];

        //计算商品
        $countSumObj = new CountSum($user_id);

        $result = $countSumObj->cart_count($id,$type,$buy_num,$promo,$active_id);
        if($countSumObj->error)
        {
            IError::show(403,$countSumObj->error);
        }

        //dyg_jzw 20160307 判断是否跨境电商
        if ($id && $type) //单品购买
        {
            //获取商品信息
            if ($type == 'goods')
            {
                $goodsObj  = new IModel('goods');
                $goods_product_info = $goodsObj->getObj('id = '.$id.' and is_del = 0','id as goods_id, is_cbe');
            }
            else
            {
                $productObj = new IQuery('products as pro , goods as go');
                $productObj->fields = ' go.id as goods_id, go.is_cbe, pro.id ';
                $productObj->where  = ' pro.id = '.$id.' and go.is_del = 0 and pro.goods_id = go.id';
                $productRow = $productObj->find();
                if($productRow)
                {
                    $goods_product_info = $productRow[0];
                }
            }

            if ($goods_product_info['is_cbe'])
            {
                $this->is_cbe = true;
            }
        }
        else //购物车数据
        {
            if ($result['is_cbe'])
            {
                $this->is_cbe = true;
            }
        }

        //获取收货地址
        $addressObj  = new IModel('address');
        $addressList = $addressObj->query('user_id = '.$user_id,"*","is_default desc,CONVERT( accept_name USING gbk ) COLLATE gbk_chinese_ci ASC");

        //更新$addressList数据
        foreach($addressList as $key => $val)
        {
            $temp = area::name($val['province'],$val['city'],$val['area']);
            if(isset($temp[$val['province']]) && isset($temp[$val['city']]) && isset($temp[$val['area']]))
            {
                $addressList[$key]['province_val'] = $temp[$val['province']];
                $addressList[$key]['city_val']     = $temp[$val['city']];
                $addressList[$key]['area_val']     = $temp[$val['area']];
                if($val['is_default'] == 1)
                {
                    //dyg_jzw 20160309 如果是跨境订单则检测身份证是否完善
                    if ($this->is_cbe && !empty($val['accept_id']))
                    {
                        $this->defaultAddressId = $val['id'];
                    }
                }
            }
        }
        //获取习惯方式
        $memberObj = new IModel('member');
        $memberRow = $memberObj->getObj('user_id = '.$user_id,'custom');
        if(isset($memberRow['custom']) && $memberRow['custom'])
        {
            $this->custom = unserialize($memberRow['custom']);

            //dyg_jzw 20160309 跨境电商没有自提点
            if ($this->is_cbe)
            {
                $custom = $this->custom;
                $custom['takeself'] = 0;
                $this->custom = $custom;
            }
            else if(isset($this->custom['takeself']) && $this->custom['takeself'] )  //dyg_jzw 20150815 获取自提点地址
            {
                $takeselfObj = new IModel('takeself');
                $this->takeselfRow = $takeselfObj->getObj('id = '.$this->custom['takeself']);
            }

        }
        else
        {
            $this->custom = array(
                'payment'  => '',
                'delivery' => '',
                'takeself' => '',
            );
        }

        //返回值
        $this->gid       = $id;
        $this->type      = $type;
        $this->num       = $buy_num;
        $this->promo     = $promo;
        $this->active_id = $active_id;
        $this->final_sum = $result['final_sum'];
        $this->promotion = $result['promotion'];
        $this->proReduce = $result['proReduce'];
        $this->sum       = $result['sum'];
        $this->goodsList = $result['goodsList'];
        $this->count       = $result['count'];
        $this->reduce      = $result['reduce'];
        $this->weight      = $result['weight'];
        $this->freeFreight = $result['freeFreight'];
        $this->seller      = $result['seller'];

        //dyg_jzw 20161216 高返自身返利
        $this->my_commission = $result['my_commission'];

        //收货地址列表
        $this->addressList = $addressList;

        //获取商品税金
        $this->goodsTax    = $result['tax'];
        //只有鲜虫草大客户 和  鲜虫草大客户(S)  才显示弹窗
        $user_info = Api::run('getMemberInfo',$this->user['user_id']);
        if($user_info)
        {
            $this->isLayer = false;
            $group_id = $user_info['group_id'];
            //只有鲜虫草大客户 和  鲜虫草大客户(S)  才显示弹窗
            if($group_id == 10 || $group_id == 12)
            {
                 $this->isLayer = true;
            }
        }
        //渲染页面
        $this->redirect('cart2');
    }

    /**
     * 生成订单
     */
    function cart3()
    {
        $address_id    = IFilter::act(IReq::get('radio_address'),'int');
        $delivery_id   = IFilter::act(IReq::get('delivery_id'),'int');
        $accept_time   = IFilter::act(IReq::get('accept_time'));
        $payment       = IFilter::act(IReq::get('payment'),'int');
        $order_message = IFilter::act(IReq::get('message'));
        $ticket_id     = IFilter::act(IReq::get('ticket_id'),'int');
        $taxes         = IFilter::act(IReq::get('taxes'),'float');
        $tax_title     = IFilter::act(IReq::get('tax_title'));
        $gid           = IFilter::act(IReq::get('direct_gid'),'int');
        $num           = IFilter::act(IReq::get('direct_num'),'int');
        $type          = IFilter::act(IReq::get('direct_type'));//商品或者货品
        $promo         = IFilter::act(IReq::get('direct_promo'));
        $active_id     = IFilter::act(IReq::get('direct_active_id'),'int');
        $takeself      = IFilter::act(IReq::get('takeself'),'int');
        $order_type    = 0;
        $dataArray     = array();
        $user_id       = ($this->user['user_id'] == null) ? 0 : $this->user['user_id'];

        //支付方式默认为预存款
        $payment = 1;

        //获取商品数据信息
        $countSumObj = new CountSum($user_id);
        $goodsResult = $countSumObj->cart_count($gid,$type,$num,$promo,$active_id);

        if($countSumObj->error)
        {
            IError::show(403,$countSumObj->error);
        }

        //处理收件地址
        //1,访客; 2,注册用户
        if($user_id == 0)
        {
            $addressRow = ISafe::get('address');
        }
        else
        {
            $addressDB = new IModel('address');
            $addressRow= $addressDB->getObj('id = '.$address_id.' and user_id = '.$user_id);
        }

        if(!$addressRow)
        {
            IError::show(403,"收货地址信息不存在");
        }

        //检查地址信息是否存在
        $is_address_correct = Api::run('getAreasName', array($addressRow['province'], $addressRow['city'], $addressRow['area']));

        if (count($is_address_correct) < 3)
        {
            IError::show(403,"您的收货地址由于数据更新已失效，请重新修改更新地址");
        }

        $accept_name   = IFilter::act($addressRow['accept_name'],'name');
        $province      = $addressRow['province'];
        $city          = $addressRow['city'];
        $area          = $addressRow['area'];
        $address       = IFilter::act($addressRow['address']);
        $mobile        = IFilter::act($addressRow['mobile'],'mobile');
        $telphone      = IFilter::act($addressRow['telphone'],'phone');
        $zip           = IFilter::act($addressRow['zip'],'zip');
        $accept_id     = $addressRow['accept_id'];

        //dyg_jzw 20160704修正地址不完全导致的bug
        if (empty($province) || empty($city) || empty($area) || empty($address) || empty($mobile))
        {
            IError::show(403, '地址信息不完整，请重新填写');
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
            IError::show(403,$result);
        }


        //配送方式,判断是否为货到付款
        $deliveryObj = new IModel('delivery');
        $deliveryRow = $deliveryObj->getObj('id = '.$delivery_id);
        if(!$deliveryRow)
        {
            IError::show(403,'配送方式不存在');
        }

        if($deliveryRow['type'] == 0)
        {
            if($payment == 0)
            {
                IError::show(403,'请选择正确的支付方式');
            }
        }
        else if($deliveryRow['type'] == 1)
        {
            $payment = 0;
        }
        else if($deliveryRow['type'] == 2)
        {
            if($takeself == 0)
            {
                IError::show(403,'请选择正确的自提点');
            }
        }
        //如果不是自提方式自动清空自提点
        if($deliveryRow['type'] != 2)
        {
            $takeself = 0;
        }


        if(!$gid)
        {
            //清空购物车
            IInterceptor::reg("cart@onFinishAction");
        }

        //判断商品是否存在
        if(is_string($goodsResult) || empty($goodsResult['goodsList']))
        {
            IError::show(403,'商品数据错误');
        }

        //加入促销活动
        if($promo && $active_id)
        {
            $activeObject = new Active($promo,$active_id,$user_id,$gid,$type,$num);
            $order_type = $activeObject->getOrderType();
        }

        //dyg_jzw 20170119支付方式默认不校验
        /*
		$paymentObj = new IModel('payment');
		$paymentRow = $paymentObj->getObj('id = '.$payment,'type,name');
		if(!$paymentRow)
		{
			IError::show(403,'支付方式不存在');
		}
		$paymentName= $paymentRow['name'];
		$paymentType= $paymentRow['type'];
        */

        //最终订单金额计算
        $orderData = $countSumObj->countOrderFee($goodsResult,$province,$delivery_id,$payment,$taxes,0,$promo,$active_id);

        if(is_string($orderData))
        {
            IError::show(403,$orderData);
            exit;
        }

        //根据商品所属商家不同批量生成订单
        $orderIdArray  = array();
        $orderNumArray = array();
        $final_sum     = 0;
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
                'postscript'          => $order_message,
                'accept_time'         => $accept_time,
                'exp'                 => $goodsResult['exp'],
                'point'               => $goodsResult['point'],
                'type'                => $order_type,

                //商品价格
                'payable_amount'      => $goodsResult['sum'],
                'real_amount'         => $goodsResult['final_sum'],

                //运费价格
                'payable_freight'     => $goodsResult['deliveryOrigPrice'],
                'real_freight'        => $goodsResult['deliveryPrice'],

                //手续费
                'pay_fee'             => $goodsResult['paymentPrice'],

                //税金
                'invoice'             => $tax_title ? 1 : 0,
                'invoice_title'       => $tax_title,
                'taxes'               => $goodsResult['taxPrice'],

                //优惠价格
                'promotions'          => $goodsResult['proReduce'] + $goodsResult['reduce'],

                //订单应付总额
                'order_amount'        => $goodsResult['orderAmountPrice'],

                //订单保价
                'insured'             => $goodsResult['insuredPrice'],

                //自提点ID
                'takeself'            => $takeself,

                //促销活动ID
                'active_id'           => $active_id,

                //商家ID
                'seller_id'           => $seller_id,

                //备注信息
                'note'                => '',

                //dyg_jzw 20150819 佣金合计
                'commission'          => $goodsResult['order_commission'],

                //dyg_jzw 20161215 高返自身返利
                'my_commission'          => $goodsResult['order_my_commission'],

                //dyg_jzw 20160307 是否跨境订单
                'is_cbe'              => $goodsResult['is_cbe'],
            );

            //获取红包减免金额
            if($ticket_id)
            {
                //仅能使用一张优惠券
                if (count($ticket_id) > 1)
                {
                    IError::show(403,'每单仅可使用一张代金券');
                }

                $memberObj = new IModel('member');
                $memberRow = $memberObj->getObj('user_id = '.$user_id,'prop,custom');

                //dyg_jzw 20161226
                $tickObj = new IModel('ticket');

                foreach($ticket_id as $tk => $tid)
                {
                    //游客手动添加或注册用户道具中已有的代金券
                    if(ISafe::get('ticket_'.$tid) == $tid || stripos(','.trim($memberRow['prop'],',').',',','.$tid.',') !== false)
                    {
                        //API获取是否可用
                        $ticketRow = Api::run('checkTicketUseful', $tid, $goodsResult, $seller_id);
                        if($ticketRow['can_use'])
                        {
                            $propObj   = new IModel('prop');
                            //抵扣处理
                            $ticketRow['value']         = $ticketRow['value'] >= $goodsResult['final_sum'] ? $goodsResult['final_sum'] : $ticketRow['value'];
                            $dataArray['prop']          = $tid;
                            $dataArray['promotions']   += $ticketRow['value'];
                            $dataArray['order_amount'] -= $ticketRow['value'];
                            $goodsResult['promotion'][] = array("plan" => "代金券","info" => "使用了￥".$ticketRow['value']."代金券");

                            //锁定红包状态
                            $propObj->setData(array('is_close' => 2));
                            $propObj->update('id = '.$tid);

                            //dyg_jzw 20150819 使用代金卷 取消返佣
                            $dataArray['commission'] = 0;

                            unset($ticket_id[$tk]);
                            break;
                        }
                        else
                        {
                            IError::show(403,'代金券不可用');
                        }

                        /*if($ticketRow['seller_id'] == 0 || $ticketRow['seller_id'] == $seller_id)
                        {
                            $ticketRow['value']         = $ticketRow['value'] >= $goodsResult['final_sum'] ? $goodsResult['final_sum'] : $ticketRow['value'];
                            $dataArray['prop']          = $tid;
                            $dataArray['promotions']   += $ticketRow['value'];
                            $dataArray['order_amount'] -= $ticketRow['value'];
                            $goodsResult['promotion'][] = array("plan" => "代金券","info" => "使用了￥".$ticketRow['value']."代金券");

                            //锁定红包状态
                            $propObj->setData(array('is_close' => 2));
                            $propObj->update('id = '.$tid);

                            unset($ticket_id[$tk]);
                            break;

                            //dyg_jzw 20150819 使用代金卷 取消返佣
                            $dataArray['commission'] = 0;
                        }*/
                    }
                }
            }

            //促销规则
            if(isset($goodsResult['promotion']) && $goodsResult['promotion'])
            {
                foreach($goodsResult['promotion'] as $key => $val)
                {
                    $dataArray['note'] .= join("，",$val)."。";
                }
            }

            $dataArray['order_amount'] = $dataArray['order_amount'] <= 0 ? 0 : $dataArray['order_amount'];


            if ($goodsResult['is_cbe'])
            {
                //dyg_jzw 20160309 检查实名是否正确
                $mem_cache = new ICache('memcache');
                $has_check_times = intval($mem_cache->get('cbe_realname_'.$user_id));
                if ($has_check_times >= 3)  //限制每天出错次数3次
                {
                    IError::show(403,'实名未通过，已超过当天最大查询次数！请明天再试');
                }
                else
                {
                    $cbeInstance = new Cbe();
                    $realname_query = $cbeInstance->yi_realname_query($dataArray['order_no'], $dataArray['accept_name'], $dataArray['accept_id']);

                    if (! $realname_query['success'] || $realname_query['realNameQueryResult'] != 'pass' )
                    {
                        //加入缓存到明天凌晨
                        $mem_cache->set('cbe_realname_'.$user_id, ++$has_check_times, strtotime(date("Y-m-d 00:00:00", strtotime("next day"))) - time());
                        IError::show(403,'实名未通过，收货信息必须为真实姓名和证件号！ 还可以尝试'.(3-$has_check_times).'次');
                    }

                    //检查是否单笔订单超过2000元
                    if ($goodsResult['orderAmountPrice'] > 2000)
                    {
                        IError::show(403,'根据海关相关规定，个人跨境电商单笔订单限制最高2000元，请返回重新选购。');
                    }

                    //全年是否超过2w元
                    if (! $cbeInstance->check_cbe_count($dataArray['accept_id'], $goodsResult['orderAmountPrice']))
                    {
                        IError::show(403,'根据海关相关规定，个人跨境电商全年累计限额不得超过20000元，当前身份证已超限');
                    }
                }

            }

            //生成订单插入order表中
            if (! $goodsResult['is_cbe'] || ($goodsResult['is_cbe'] && $realname_query['success'] && $realname_query['realNameQueryResult'] == 'pass'))
            {
                $orderObj  = new IModel('order');
                $orderObj->setData($dataArray);
                $order_id = $orderObj->add();
            }

            if($order_id == false)
            {
                IError::show(403,'订单生成错误');
            }

            /*将订单中的商品插入到order_goods表*/
            $orderInstance = new Order_Class();
            $orderInstance->insertOrderGoods($order_id,$goodsResult['goodsResult']);

            //订单金额小于等于0直接免单
            if($dataArray['order_amount'] <= 0)
            {
                Order_Class::updateOrderStatus($dataArray['order_no']);
            }
            else
            {
                $orderIdArray[]  = $order_id;
                $orderNumArray[] = $dataArray['order_no'];
                $final_sum      += $dataArray['order_amount'];
            }
        }


        if (!isset($memberRow))
        {
            $memberObj = new IModel('member');
            $memberRow = $memberObj->getObj('user_id = '.$user_id,'custom');
        }

        //记录用户默认习惯的数据
        if ($memberRow['custom'])
        {
            $_custom = unserialize($memberRow['custom']);
        }
        else
        {
            $_custom = array('payment' => $payment); //默认习惯为预存款
        }

        $memberData = array(
            'custom' => serialize(
                array(
                    'payment'  => $_custom['payment'],
                    'delivery' => $delivery_id,
                    'takeself' => $takeself, //dyg_jzw 20160613 增加自提点记忆
                )
            ),
        );

        $memberObj->setData($memberData);
        $memberObj->update('user_id = '.$user_id);

        //收货地址的处理
        if($user_id)
        {
            $addressDefRow = $addressDB->getObj('user_id = '.$user_id.' and is_default = 1');
            if(!$addressDefRow)
            {
                $addressDB->setData(array('is_default' => 1));
                $addressDB->update('user_id = '.$user_id.' and id = '.$address_id);
            }
        }
        //dyg_jzw 20170119 跳转至收银台 不再显示cart3页面
        if($final_sum <= 0) //订单金额为0时，订单自动完成
        {
            $this->redirect('/site/success/message/'.urlencode("订单确认成功，等待发货"));
        }
        else //跳转至收银台
        {
            $this->redirect('/block/payment/id/'.$order_id);
        }

        /*
		//获取备货时间
		$this->stockup_time = $this->_siteConfig->stockup_time ? $this->_siteConfig->stockup_time : 2;

		//数据渲染
		$this->order_id    = join("_",$orderIdArray);
		$this->final_sum   = $final_sum;
		$this->order_num   = join(",",$orderNumArray);
		$this->payment     = $paymentName;
		$this->paymentType = $paymentType;
		$this->delivery    = $deliveryRow['name'];
		$this->tax_title   = $tax_title;
		$this->deliveryType= $deliveryRow['type'];
		plugin::trigger('setCallback','/ucenter/order');
		//订单金额为0时，订单自动完成
		if($this->final_sum <= 0)
		{
			$this->redirect('/site/success/message/'.urlencode("订单确认成功，等待发货"));
		}
		else
		{
			$this->setRenderData($dataArray);
			$this->redirect('cart3');
		}
        */
    }

    //dyg_jzw 20161124 订阅到货通知需要登陆
    function arrival()
    {

        if(! $this->user['user_id'])
        {
            $this->redirect('/simple/login');
        }
        else
        {
            $this->redirect('arrival');
        }

    }

    //到货通知处理动作
    function arrival_notice()
    {
        $user_id  = $this->user['user_id'];
        $email    = IFilter::act(IReq::get('email'));
        $mobile   = IFilter::act(IReq::get('mobile'));
        $goods_id = IFilter::act(IReq::get('goods_id'),'int');
        $register_time = ITime::getDateTime();

        if(! $this->user['user_id'])
        {
            $this->redirect('/simple/login');
        }

        if(!$goods_id)
        {
            IError::show(403,'商品ID不存在');
        }

        $model = new IModel('notify_registry');
        $obj = $model->getObj("email = '{$email}' and user_id = '{$user_id}' and goods_id = '$goods_id'");
        if(empty($obj))
        {
            $model->setData(array('email'=>$email,'user_id'=>$user_id,'mobile'=>$mobile,'goods_id'=>$goods_id,'register_time'=>$register_time));
            $model->add();
        }
        else
        {
            $model->setData(array('email'=>$email,'user_id'=>$user_id,'mobile'=>$mobile,'goods_id'=>$goods_id,'register_time'=>$register_time,'notify_status'=>0));
            $model->update('id = '.$obj['id']);
        }
        $this->redirect('/site/success',true);
    }

    /**
     * dyg_jzw 20150812 找回密码(邮箱/手机号)统一判断
     *
     */
    function find_password_email_or_mobi()
    {
        $email    = IFilter::act(IReq::get('email','post'));
        $captcha    = IFilter::act(IReq::get('captcha','post'));

        if($captcha != ISafe::get('captcha'))
        {
            IError::show(403,"验证码输入不正确");
        }
        else if($email === null || (IValidate::email($email) ===false && IValidate::mobi($email)==false))
        {
            IError::show(403,"请输入正确的邮箱地址或手机号码");
        }

        if (IValidate::email($email) !== false)
        {
            $this->find_password_email($email);
        }
        else if(IValidate::mobi($email) !== false)
        {
            //发送验证码 并跳转至验证码输入页
            $this->redirect("/simple/find_password_m/mobile/".base64_encode($email));
        }

    }

    /**
     * @brief 邮箱找回密码进行
     */
    //dyg_jzw 20150812添加邮件参数
    function find_password_email($email)
    {
        //dyg_jzw 20150812 取消用户名 只需要邮箱找回密码
        /*$username = IReq::get('username');
        if($username === null || !Util::is_username($username)  )
        {
            IError::show(403,"请输入正确的用户名");
        }*/

        //$email    = IFilter::act(IReq::get('email','post'));

        if($email === null || !IValidate::email($email ))
        {
            IError::show(403,"请输入正确的邮箱地址");
        }

        $tb_user  = new IModel("member");

        //dyg_jzw 20150812 取消用户名 只需要邮箱找回密码
        //$username = IFilter::act($username);
        //$user     = $tb_user->getObj(" username='{$username}' AND email='{$email}' ");

        $user     = $tb_user->getObj(" email='{$email}' ");
        if(!$user)
        {
            IError::show(403,"对不起，邮箱不存在");
        }
        $hash = IHash::md5( microtime(true) .mt_rand());

        //重新找回密码的数据
        $tb_find_password = new IModel("find_password");
        $tb_find_password->setData( array( 'hash' => $hash ,'user_id' => $user['user_id'] , 'addtime' => time() ) );

        if($tb_find_password->query("`hash` = '{$hash}'") || $tb_find_password->add())
        {
            $url     = IUrl::getHost().IUrl::creatUrl("/simple/restore_password/hash/{$hash}/user_id/".$user['user_id']);
            $content = mailTemplate::findPassword(array("{url}" => $url));

            $siteConfig = new Config("site_config"); //dyg_jzw 20161124增加设置载入
            $smtp   = new SendMail();
            $result = $smtp->send($user['email'], "【{$siteConfig->name}】您的密码找回",$content);

            if($result===false)
            {
                IError::show(403,"发信失败,请重试！或者联系管理员查看邮件服务是否开启");
            }
        }
        else
        {
            IError::show(403,"生成HASH重复，请重试");
        }
        $message = "密码重置邮件已经发送，请到您的邮箱中去激活！如果收不到请查看垃圾箱，或重新发送";
        $this->redirect("/site/success/message/".urlencode($message));
    }

    //手机短信找回密码
    function find_password_mobile()
    {
        //dyg_jzw 20150812 以手机号替换用户名
        /*$username = IReq::get('username');
        if($username === null || !Util::is_username($username)  )
        {
            IError::show(403,"请输入正确的用户名");
        }*/

        $mobile = IFilter::act(IReq::get('mobile'));
        if($mobile === null || !IValidate::mobi($mobile))
        {
            IError::show(403,"请输入正确的电话号码");
        }

        $mobile_code = IFilter::act(IReq::get('mobile_code'));
        if($mobile_code === null)
        {
            IError::show(403,"请输入短信校验码");
        }

        //dyg_jzw 20150812 以手机号替换用户名
        //$userDB = new IModel('user as u , member as m');
        //$userRow = $userDB->getObj('u.username = "'.$username.'" and m.mobile = "'.$mobile.'" and u.id = m.user_id');

        $userDB = new IModel('user as u');
        $userRow = $userDB->getObj("u.username = '".$mobile."'");

        if($userRow)
        {
            $findPasswordDB = new IModel('find_password');
            //dyg_jzw 20150812 修改以下$userRow['user_id']为$userRow['id']
            $dataRow = $findPasswordDB->getObj('user_id = '.$userRow['id'].' and hash = "'.$mobile_code.'"');
            if($dataRow)
            {
                //短信验证码已经过期
                if(time() - $dataRow['addtime'] > 1800)
                {
                    $findPasswordDB->del("user_id = ".$userRow['user_id']);
                    IError::show(403,"您的短信校验码已经过期了，请重新找回密码");
                }
                else
                {
                    $this->redirect('/simple/restore_password/hash/'.$mobile_code.'/user_id/'.$userRow['id']);
                }
            }
            else
            {
                IError::show(403,"您输入的短信校验码错误");
            }
        }
        else
        {
            IError::show(403,"手机号码不存在");
        }
    }

    //发送手机验证码短信
    function send_message_mobile()
    {
        //dyg_jzw 20150812 只保留手机号
        //$username = IFilter::act(IReq::get('username'));
        $mobile = IFilter::act(IReq::get('mobile'));

        /*if($username === null || !Util::is_username($username))
        {
            die("请输入正确的用户名");
        }*/

        if($mobile === null || !IValidate::mobi($mobile))
        {
            die("请输入正确的手机号码");
        }

        //dyg_jzw 20150812 以手机号替换用户名
        //$userDB = new IModel('user as u , member as m');
        //$userRow = $userDB->getObj('u.username = "'.$username.'" and m.mobile = "'.$mobile.'" and u.id = m.user_id');

        $userDB = new IModel('user as u');
        $userRow = $userDB->getObj("u.username = '".$mobile."'");

        if($userRow)
        {
            $findPasswordDB = new IModel('find_password');
            //dyg_jzw 20150812 修改以下$userRow['user_id']为$userRow['id']
            $dataRow = $findPasswordDB->query('user_id = '.$userRow['id'],'addtime','id desc');
            $dataRow = current($dataRow);

            //120秒是短信发送的间隔
            if( isset($dataRow['addtime']) && (time() - $dataRow['addtime'] <= 120) )
            {
                die("tooshort");
            }
            $mobile_code = rand(100000,999999);
            $findPasswordDB->setData(array(
                'user_id' => $userRow['id'], //dyg_jzw 20150812 修改$userRow['user_id']为$userRow['id']
                'hash'    => $mobile_code,
                'addtime' => time(),
            ));
            if($findPasswordDB->add())
            {
                //dyg_jzw 20150811 添加短信发送设置
                $config = new Config('site_config');
                $sms_send_config = explode(';', $config->sms_send_config);

                if($sms_send_config && in_array('sms_find_password', $sms_send_config))
                {
                    $content = smsTemplate::findPassword(array('{mobile_code}' => $mobile_code));
                    $result = Hsms::send($mobile,$content,0);
                    if($result == 'success')
                    {
                        die('success');
                    }
                }
                die('fail');
            }
        }
        else
        {
            die('notfound');
        }
    }

    /**
     * @brief 邮箱链接激活验证
     */
    function restore_password()
    {
        $hash = IFilter::act(IReq::get("hash"));
        $user_id = IFilter::act(IReq::get("user_id"),'int');
        if(!$hash)
        {
            IError::show(403,"找不到校验码");
        }
        $tb = new IModel("find_password");
        $addtime = time() - 3600*72;
        $where  = " `hash`='$hash' AND addtime > $addtime ";
        $where .= $this->user['user_id'] ? " and user_id = ".$this->user['user_id'] : "";

        $row = $tb->getObj($where);
        if(!$row)
        {
            IError::show(403,"校验码已经超时");
        }

        if($row['user_id'] != $user_id)
        {
            IError::show(403,"验证码不属于此用户");
        }
        $this->formAction = IUrl::creatUrl("/simple/do_restore_password/hash/$hash/user_id/".$user_id);
        $this->redirect("restore_password");
    }

    /**
     * @brief 执行密码修改重置操作
     */
    function do_restore_password()
    {
        $hash = IFilter::act(IReq::get("hash"));
        $user_id = IFilter::act(IReq::get("user_id"),'int');
        if(!$hash)
        {
            IError::show(403,"找不到校验码");
        }
        $tb = new IModel("find_password");
        $addtime = time() - 3600*72;
        $where  = " `hash`='$hash' AND addtime > $addtime ";
        $where .= $this->user['user_id'] ? " and user_id = ".$this->user['user_id'] : "";

        $row = $tb->getObj($where);
        if(!$row)
        {
            IError::show(403,"校验码已经超时");
        }
        if($row['user_id'] != $user_id)
        {
            IError::show(403,"验证码不属于此用户");
        }

        //开始修改密码
        $pwd   = IReq::get("password");
        $repwd = IReq::get("repassword");
        if($pwd == null || strlen($pwd) < 6 || $repwd!=$pwd)
        {
            IError::show(403,"新密码至少六位，且两次输入的密码应该一致。");
        }
        $pwd = md5($pwd);
        $tb_user = new IModel("user");
        $tb_user->setData(array("password" => $pwd));
        $re = $tb_user->update("id='{$row['user_id']}'");
        if($re !== false)
        {
            $message = "修改密码成功";
            $tb->del("`hash`='{$hash}'");
            $this->redirect("/site/success/message/".urlencode($message));
            return;
        }
        IError::show(403,"密码修改失败，请重试");
    }

    //添加收藏夹
    function favorite_add()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'),'int');
        $message  = '';

        //dyg_jzw 20161208加入是否收藏成功的提示
        $is_success = false;

        if($goods_id == 0)
        {
            $message = '商品id值不能为空';
        }
        else if(!isset($this->user['user_id']) || !$this->user['user_id'])
        {
            $message = '请先登录';
        }
        else
        {
            $favoriteObj = new IModel('favorite');
            $goodsRow    = $favoriteObj->getObj('user_id = '.$this->user['user_id'].' and rid = '.$goods_id);
            if($goodsRow)
            {
                $message = '您已经收藏过此件商品';
                $is_success = true;
            }
            else
            {
                $catObj = new IModel('category_extend');
                $catRow = $catObj->getObj('goods_id = '.$goods_id);
                $cat_id = $catRow ? $catRow['category_id'] : 0;

                $dataArray   = array(
                    'user_id' => $this->user['user_id'],
                    'rid'     => $goods_id,
                    'time'    => ITime::getDateTime(),
                    'cat_id'  => $cat_id,
                );
                $favoriteObj->setData($dataArray);
                $favoriteObj->add();
                $message = '收藏成功';
                $is_success = true;

                //商品收藏信息更新
                $goodsDB = new IModel('goods');
                $goodsDB->setData(array("favorite" => "favorite + 1"));
                $goodsDB->update("id = ".$goods_id,'favorite');
            }
        }
        $result = array(
            'success' => $is_success,
            'message' => $message,
        );

        echo JSON::encode($result);
    }

    //获取oauth登录地址
    public function oauth_login()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $oauthObj = new Oauth($id);
            $result   = array(
                'isError' => false,
                'url'     => $oauthObj->getLoginUrl(),
            );
        }
        else
        {
            $result   = array(
                'isError' => true,
                'message' => '请选择要登录的平台',
            );
        }
        echo JSON::encode($result);
    }

    //第三方登录回调
    public function oauth_callback()
    {
        $oauth_name = IFilter::act(IReq::get('oauth_name'));
        $oauthObj   = new IModel('oauth');
        $oauthRow   = $oauthObj->getObj('file = "'.$oauth_name.'"');

        if(!$oauth_name && !$oauthRow)
        {
            IError::show(403,"{$oauth_name} 第三方平台信息不存在");
        }
        $id       = $oauthRow['id'];
        $oauthObj = new Oauth($id);
        $result   = $oauthObj->checkStatus($_GET);

        if($result === true)
        {
            $oauthObj->getAccessToken($_GET);
            $userInfo = $oauthObj->getUserInfo();

            if(isset($userInfo['id']) && isset($userInfo['name']) && $userInfo['id'] && $userInfo['name'])
            {
                $this->bindUser($userInfo,$id);
                return;
            }
        }
        else
        {
            IError::show("回调URL参数错误");
        }
    }

    //同步绑定用户数据
    public function bindUser($userInfo,$oauthId)
    {
        $userObj      = new IModel('user');
        $oauthUserObj = new IModel('oauth_user');
        $oauthUserRow = $oauthUserObj->getObj("oauth_user_id = '{$userInfo['id']}' and oauth_id = '{$oauthId}' ",'user_id');
        if($oauthUserRow)
        {
            //清理oauth_user和user表不同步匹配的问题
            $tempRow = $userObj->getObj("id = '{$oauthUserRow['user_id']}'");
            if(!$tempRow)
            {
                $oauthUserObj->del("oauth_user_id = '{$userInfo['id']}' and oauth_id = '{$oauthId}' ");
            }
        }

        //存在绑定账号oauth_user与user表同步正常！
        if(isset($tempRow) && $tempRow)
        {
            $userRow = plugin::trigger("isValidUser",array($tempRow['username'],$tempRow['password']));
            plugin::trigger("userLoginCallback",$userRow);
            $callback = plugin::trigger('getCallback');
            $callback = $callback ? $callback : "/ucenter/index";
            $this->redirect($callback);
        }
        //没有绑定账号
        else
        {
            $userCount = $userObj->getObj("username = '{$userInfo['name']}'",'count(*) as num');

            //没有重复的用户名
            if($userCount['num'] == 0)
            {
                $username = $userInfo['name'];
            }
            else
            {
                //随即分配一个用户名
                $username = $userInfo['name'].$userCount['num'];
            }
            $userInfo['name'] = $username;
            ISession::set('oauth_id',$oauthId);
            ISession::set('oauth_userInfo',$userInfo);
            $this->setRenderData($userInfo);
            $this->redirect('bind_user',false);


        }
    }

    //执行绑定已存在用户
    public function bind_exists_user()
    {
        $login_info     = IReq::get('login_info');
        $password       = IReq::get('password');
        $oauth_id       = IFilter::act(ISession::get('oauth_id'));
        $oauth_userInfo = IFilter::act(ISession::get('oauth_userInfo'));

        if(!$oauth_id || !$oauth_userInfo || !isset($oauth_userInfo['id']))
        {
            IError::show("缺少oauth信息");
        }

        if($userRow = plugin::trigger("isValidUser",array($login_info,md5($password))))
        {
            $oauthUserObj = new IModel('oauth_user');

            //插入关系表
            $oauthUserData = array(
                'oauth_user_id' => $oauth_userInfo['id'],
                'oauth_id'      => $oauth_id,
                'user_id'       => $userRow['user_id'],
                'datetime'      => ITime::getDateTime(),
            );
            $oauthUserObj->setData($oauthUserData);
            $oauthUserObj->add();

            plugin::trigger("userLoginCallback",$userRow);

            //自定义跳转页面
            $this->redirect('/site/success?message='.urlencode("登录成功！"));
        }
        else
        {
            $this->setError("用户名和密码不匹配");
            $_GET['bind_type'] = 'exists';
            $this->redirect('bind_user',false);
            Util::showMessage("用户名和密码不匹配");
        }
    }

    //执行绑定注册新用户用户
    public function bind_not_exists_user()
    {
        $oauth_id       = IFilter::act(ISession::get('oauth_id'));
        $oauth_userInfo = IFilter::act(ISession::get('oauth_userInfo'));

        if(!$oauth_id || !$oauth_userInfo || !isset($oauth_userInfo['id']))
        {
            IError::show("缺少oauth信息");
        }

        //调用_userInfo注册插件
        $result = plugin::trigger('userRegAct',$_POST);
        if(is_array($result))
        {
            //插入关系表
            $oauthUserObj = new IModel('oauth_user');
            $oauthUserData = array(
                'oauth_user_id' => $oauth_userInfo['id'],
                'oauth_id'      => $oauth_id,
                'user_id'       => $result['id'],
                'datetime'      => ITime::getDateTime(),
            );
            $oauthUserObj->setData($oauthUserData);
            $oauthUserObj->add();
            $this->redirect('/site/success?message='.urlencode("注册成功！"));
        }
        else
        {
            $this->setError($result);
            $this->redirect('bind_user',false);
            Util::showMessage($result);
        }
    }

    /**
     * @brief 商户的增加动作
     */
    public function seller_reg()
    {
        $seller_name = IValidate::name(IReq::get('seller_name')) ? IReq::get('seller_name') : "";
        $email       = IValidate::email(IReq::get('email'))      ? IReq::get('email')       : "";
        $truename    = IValidate::name(IReq::get('true_name'))   ? IReq::get('true_name')   : "";
        $phone       = IValidate::phone(IReq::get('phone'))      ? IReq::get('phone')       : "";
        $mobile      = IValidate::mobi(IReq::get('mobile'))      ? IReq::get('mobile')      : "";
        $home_url    = IValidate::url(IReq::get('home_url'))     ? IReq::get('home_url')    : "";

        $password    = IFilter::act(IReq::get('password'));
        $repassword  = IFilter::act(IReq::get('repassword'));
        $province    = IFilter::act(IReq::get('province'),'int');
        $city        = IFilter::act(IReq::get('city'),'int');
        $area        = IFilter::act(IReq::get('area'),'int');
        $address     = IFilter::act(IReq::get('address'));

        if($password == '')
        {
            $errorMsg = '请输入密码！';
        }

        if($password != $repassword)
        {
            $errorMsg = '两次输入的密码不一致！';
        }

        if(!$seller_name)
        {
            $errorMsg = '填写正确的登陆用户名';
        }

        if(!$truename)
        {
            $errorMsg = '填写正确的商户真实全称';
        }

        //创建商家操作类
        $sellerDB = new IModel("seller");
        if($seller_name && $sellerDB->getObj("seller_name = '{$seller_name}'"))
        {
            $errorMsg = "登录用户名重复";
        }
        else if($truename && $sellerDB->getObj("true_name = '{$truename}'"))
        {
            $errorMsg = "商户真实全称重复";
        }

        //操作失败表单回填
        if(isset($errorMsg))
        {
            $this->sellerRow = IFilter::act($_POST,'text');
            $this->redirect('seller',false);
            Util::showMessage($errorMsg);
        }

        //待更新的数据
        $sellerRow = array(
            'true_name' => $truename,
            'phone'     => $phone,
            'mobile'    => $mobile,
            'email'     => $email,
            'address'   => $address,
            'province'  => $province,
            'city'      => $city,
            'area'      => $area,
            'home_url'  => $home_url,
            'is_lock'   => 1,
        );

        //商户资质上传
        if(isset($_FILES['paper_img']['name']) && $_FILES['paper_img']['name'])
        {
            $uploadObj = new PhotoUpload();
            $uploadObj->setIterance(false);
            $photoInfo = $uploadObj->run();
            if(isset($photoInfo['paper_img']['img']) && file_exists($photoInfo['paper_img']['img']))
            {
                $sellerRow['paper_img'] = $photoInfo['paper_img']['img'];
            }
        }

        $sellerRow['seller_name'] = $seller_name;
        $sellerRow['password']    = md5($password);
        $sellerRow['create_time'] = ITime::getDateTime();

        $sellerDB->setData($sellerRow);
        $sellerDB->add();

        //dyg_jzw 20150811 添加短信发送设置
        $siteConfig = new Config('site_config');
        $sms_send_config = explode(';', $siteConfig->sms_send_config);


        //短信通知商城平台
        if($sms_send_config && in_array('sms_seller_reg', $sms_send_config))
        {
            if($siteConfig->mobile)
            {
                $content = smsTemplate::sellerReg(array('{true_name}' => $truename));
                $result = Hsms::send($siteConfig->mobile,$content,0);
            }
        }

        $this->redirect('/site/success?message='.urlencode("申请成功！请耐心等待管理员的审核"));
    }

    //添加地址ajax
    function address_add()
    {
        $id          = IFilter::act(IReq::get('id'),'int');
        $accept_name = IFilter::act(IReq::get('accept_name'));
        $province    = IFilter::act(IReq::get('province'),'int');
        $city        = IFilter::act(IReq::get('city'),'int');
        $area        = IFilter::act(IReq::get('area'),'int');
        $address     = IFilter::act(IReq::get('address'));
        $zip         = IFilter::act(IReq::get('zip'));
        $telphone    = IFilter::act(IReq::get('telphone'));
        $mobile      = IFilter::act(IReq::get('mobile'));

        //dyg_jzw 20160723 手机号校验


        //dyg_jzw 20160309 增加身份证号保存
        $accept_id = IFilter::act(IReq::get('accept_id'));

        $user_id     = $this->user['user_id'];

        if(!$user_id)
        {
            die(JSON::encode(array('data' => null)));
        }

        //整合的数据，检查数据库中是否存在此收货地址
        $sqlData = array(
            'user_id'     => $user_id,
            'accept_name' => $accept_name,
            'accept_id'   => $accept_id, //dyg_jzw 20160309 增加身份证号保存
            'zip'         => $zip,
            'telphone'    => $telphone,
            'province'    => $province,
            'city'        => $city,
            'area'        => $area,
            'address'     => $address,
            'mobile'      => $mobile,
        );

        $checkArray = $sqlData;
        unset($checkArray['telphone'],$checkArray['zip'],$checkArray['user_id'],$checkArray['accept_id']); //dyg_jzw 20160613 身份照非必填
        foreach($checkArray as $key => $val)
        {
            if(!$val)
            {
                $result = array('result' => false,'msg' => '请仔细填写收货地址');
                die(JSON::encode($result));
            }
        }

        if($user_id)
        {
            $model = new IModel('address');
            $model->setData($sqlData);
            if($id)
            {
                $model->update("id = ".$id." and user_id = ".$user_id);
            }
            else
            {
                $id = $model->add();
            }
            $sqlData['id'] = $id;
        }
        //访客地址保存
        else
        {
            ISafe::set("address",$sqlData);
        }

        $areaList = area::name($province,$city,$area);
        $sqlData['province_val'] = $areaList[$province];
        $sqlData['city_val']     = $areaList[$city];
        $sqlData['area_val']     = $areaList[$area];
        $result = array('data' => $sqlData);
        die(JSON::encode($result));
    }
}
