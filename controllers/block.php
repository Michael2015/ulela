<?php
/**
 * @brief 公共模块
 * @class Block
 */
class Block extends IController
{
    public $layout='';

    public function init()
    {

    }

    /**
     * @brief Ajax获取规格值
     */
    function spec_value_list()
    {
        // 获取POST数据
        $spec_id = IFilter::act(IReq::get('id'),'int');

        //初始化spec商品模型规格表类对象
        $specObj = new IModel('spec');
        //根据规格编号 获取规格详细信息
        $specData = $specObj->getObj("id = $spec_id");
        if($specData)
        {
            echo JSON::encode($specData);
        }
        else
        {
            //返回失败标志
            echo '';
        }
    }

    //列出筛选商品
    function goods_list()
    {
        //商品检索条件
        $show_num    = IFilter::act( IReq::get('show_num'),'int');
        $keywords    = IFilter::act( IReq::get('keywords') );
        $cat_id      = IFilter::act( IReq::get('category_id'),'int');
        $min_price   = IFilter::act( IReq::get('min_price'),'float');
        $max_price   = IFilter::act( IReq::get('max_price'),'float');
        $goods_no    = IFilter::act( IReq::get('goods_no'));
        $is_products = IFilter::act( IReq::get('is_products'),'int');
        $seller_id   = IFilter::act( IReq::get('seller_id'),'int');
        $goods_id    = IFilter::act( IReq::get('goods_id'),'int');

        //货号处理 商品货号或者货品货号2种情况
        if($goods_no)
        {
            $productDB   = new IModel('products');
            $productData = $productDB->query("products_no = '".$goods_no."'");
            if($productData)
            {
                foreach($productData as $key => $item)
                {
                    $goods_id .= ",".$item['goods_id'];
                }
                //找到货品后清空货号数据
                $goods_no = "";
            }
        }
        //查询条件
        $table_name = 'goods as go';
        $fields     = 'go.id as goods_id,go.name,go.img,go.store_nums,go.goods_no,go.sell_price,go.spec_array';

        $where   = 'go.is_del = 0';
        $where  .= $goods_id  ? ' and go.id          in ('.$goods_id.')' : '';
        $where  .= $seller_id ? ' and go.seller_id    = '.$seller_id     : '';
        $where  .= $goods_no  ? ' and go.goods_no     = "'.$goods_no.'"' : '';
        $where  .= $min_price ? ' and go.sell_price  >= '.$min_price     : '';
        $where  .= $max_price ? ' and go.sell_price  <= '.$max_price     : '';
        $where  .= $keywords  ? ' and go.name like    "%'.$keywords.'%"' : '';

        //分类筛选
        if($cat_id)
        {
            $table_name .= ' ,category_extend as ca ';
            $where      .= " and ca.category_id = {$cat_id} and go.id = ca.goods_id ";
        }

        //获取商品数据
        $goodsDB = new IModel($table_name);
        $data    = $goodsDB->query($where,$fields,'go.id desc',$show_num);

        //包含货品信息
        if($is_products)
        {
            if($data)
            {
                $goodsIdArray = array();
                foreach($data as $key => $val)
                {
                    //有规格有货品
                    if($val['spec_array'])
                    {
                        $goodsIdArray[$key] = $val['goods_id'];
                        unset($data[$key]);
                    }
                }

                if($goodsIdArray)
                {
                    $productFields = "pro.goods_id,go.name,go.img,pro.id as product_id,pro.products_no as goods_no,pro.spec_array,pro.sell_price,pro.store_nums";
                    $productDB     = new IModel('goods as go,products as pro');
                    $productDate   = $productDB->query('go.id = pro.goods_id and pro.goods_id in('.join(',',$goodsIdArray).')',$productFields);
                    $data = array_merge($data,$productDate);
                }
            }
        }

        $this->data = $data;
        $this->type = IFilter::act(IReq::get('type'));//页面input的type类型，比如radio，checkbox
        $this->redirect('goods_list');
    }
    /**
     * @brief 获取地区
     */
    public function area_child()
    {
        $parent_id = intval(IReq::get("aid"));
        $areaDB    = new IModel('areas');
        $data      = $areaDB->query("parent_id=$parent_id",'*','sort asc');
        echo JSON::encode($data);
    }

    //[公共方法]通过解析products,goods表中的spec_array转化为格式：key:规格名称;value:规格值
    public static function show_spec($specJson,$param = array())
    {
        $specArray = JSON::decode($specJson);
        $spec      = array();
        if($specArray)
        {
            $imgSize = isset($param['size']) ? $param['size'] : 20;
            foreach($specArray as $val)
            {
                //goods表规格数据
                if(is_array($val['value']))
                {
                    foreach($val['value'] as $tip => $sval)
                    {
                        if(!isset($spec[$val['name']]))
                        {
                            $spec[$val['name']] = array();
                        }
                        list($tip,$specVal) = each($sval);
                        if($val['type'] == 1)
                        {
                            $spec[$val['name']][] = $specVal;
                        }
                        else
                        {
                            $spec[$val['name']][] = '<img src="'.IUrl::creatUrl().$specVal.'" style="border: 1px solid #ddd;width:'.$imgSize.'px;height:'.$imgSize.'px;" title="'.$tip.'" />';
                        }
                    }
                    $spec[$val['name']] = join("&nbsp;&nbsp;",$spec[$val['name']]);
                }
                //goods表老版本格式逗号分隔符
                else if(strpos($val['value'],",") && $val['value'] = explode(",",$val['value']))
                {
                    foreach($val['value'] as $tip => $sval)
                    {
                        if(!isset($spec[$val['name']]))
                        {
                            $spec[$val['name']] = array();
                        }
                        if($val['type'] == 1)
                        {
                            $spec[$val['name']][] = $sval;
                        }
                        else
                        {
                            $spec[$val['name']][] = '<img src="'.IUrl::creatUrl().$sval.'" style="border: 1px solid #ddd;width:'.$imgSize.'px;height:'.$imgSize.'px;" />';
                        }
                    }
                    $spec[$val['name']] = join("&nbsp;&nbsp;",$spec[$val['name']]);
                }
                //products表规格数据
                else
                {
                    if($val['type'] == 1)
                    {
                        $spec[$val['name']] = $val['value'];
                    }
                    else
                    {
                        $tip = isset($val['tip']) ? $val['tip'] : "";
                        $spec[$val['name']] = '<img src="'.IUrl::creatUrl().$val['value'].'" style="border: 1px solid #ddd;width:'.$imgSize.'px;height:'.$imgSize.'px;" title="'.$tip.'" />';
                    }
                }
            }
        }
        return $spec;
    }
    /**
     * @brief 获得配送方式ajax
     */
    public function order_delivery()
    {
        $productId    = IFilter::act(IReq::get("productId"),'int');
        $goodsId      = IFilter::act(IReq::get("goodsId"),'int');
        $province     = IFilter::act(IReq::get("province"),'int');
        $distribution = IFilter::act(IReq::get("distribution"),'int');
        $num          = IReq::get("num") ? IFilter::act(IReq::get("num"),'int') : 1;
        $data         = array();
        if($distribution)
        {
            $data = Delivery::getDelivery($province,$distribution,$goodsId,$productId,$num);
        }
        else
        {
            //dyg_jzw 20160423 增加跨境电商商品判断
            $goodsId    = is_array($goodsId)  ? $goodsId   : array($goodsId);

            $goodsDB   = new IModel('goods');
            $goodRow = $goodsDB->getObj('id = '.$goodsId[0]);

            $delivery     = new IModel('delivery');
            $deliveryList = $delivery->query('is_delete = 0 and status = 1 and is_cbe='.$goodRow['is_cbe'], '*', 'sort ASC'); //dyg_jzw 20160328 前台增加排序方式
            foreach($deliveryList as $key => $item)
            {
                $data[$item['id']] = Delivery::getDelivery($province,$item['id'],$goodsId,$productId,$num);
            }
        }
        echo JSON::encode($data);
    }
    /**
     * @brief 【重要】进行支付支付方法
     */
    public function doPay()
    {
        //获得相关参数
        $order_id   = IReq::get('order_id');
        $recharge   = IReq::get('recharge');
        $payment_id = IFilter::act(IReq::get('payment_id'),'int');

        if($order_id)
        {
            $order_id = explode("_",IReq::get('order_id'));
            $order_id = IFilter::act($order_id,'int');

            //获取订单信息
            $orderDB  = new IModel('order');
            $orderRow = $orderDB->getObj('id = '.current($order_id));

            if(empty($orderRow))
            {
                IError::show(403,'要支付的订单信息不存在');
            }
            $payment_id = $orderRow['pay_type'];
        }

        //获取支付方式类库
        $paymentInstance = Payment::createPaymentInstance($payment_id);


        //在线充值
        if($recharge !== null)
        {
            $recharge   = IFilter::act($recharge,'float');
            $paymentRow = Payment::getPaymentById($payment_id);

            //account:充值金额; paymentName:支付方式名字
            $reData   = array('account' => $recharge , 'paymentName' => $paymentRow['name']);
            $sendData = $paymentInstance->getSendData(Payment::getPaymentInfo($payment_id,'recharge',$reData));
        }
        //订单支付
        else if($order_id)
        {
            $sendData = $paymentInstance->getSendData(Payment::getPaymentInfo($payment_id,'order',$order_id));
        }
        else
        {
            IError::show(403,'发生支付错误');
        }
        $paymentInstance->doPay($sendData);
    }

    /**
     * @brief dyg_jzw 20170113 增加支付收银台
     */
    public function payment()
    {
        //订单ID
        $order_id = IFilter::act(IReq::get('id'),'int');

        //获取订单信息
        $orderDB = new IModel("order");
        $orderRow = $orderDB->getObj("id = ".$order_id);

        if ($orderRow && $orderRow['user_id'] == $this->user['user_id'])
        {
            //是否已支付
            if ($orderRow['pay_status'] > 0)
            {
                $this->redirect('/ucenter/order_detail/id/'.$order_id);
            }
            elseif($orderRow['status'] != 1)
            {
                IError::show(403,'订单已被取消，无法支付');
            }
            else
            {
                $this->orderRow = $orderRow;

                //dyg_jzw 20170220 获取订单商品判断是否支持积分支付
                $query = new IQuery('order_goods as og');
                $query->join = "left join goods as go on go.id = og.goods_id";
                $query->where = "og.order_id = ".$orderRow['id'];
                $query->fields = "go.is_point";
                $goodsIsPoint = $query->find();

                $is_point = 1;
                foreach ($goodsIsPoint as $_goods_is_point)
                {
                    if (! $_goods_is_point['is_point'])
                    {
                        $is_point = 0;
                        break;
                    }
                }

                //获取支付方式
                $is_cbe = $orderRow['is_cbe'] ? 1 : 0;
                $this->paymentList = Api::run('getPaymentList', $is_cbe, $is_point);

                //获取配送方式
                $deliveryObj = new IModel('delivery');
                $deliveryRow = $deliveryObj->getObj('id = '.$orderRow['distribution']);
                $this->delivery = $deliveryRow['name'];

                //地区信息
                $this->area = Api::run('getAreasName', array($orderRow['province'], $orderRow['city'], $orderRow['area']));

                //获取支付习惯方式
                $memberObj = new IModel('member');
                $memberRow = $memberObj->getObj('user_id = '.$orderRow['user_id'],'custom');
                if(isset($memberRow['custom']) && $memberRow['custom'])
                {
                    $this->custom = unserialize($memberRow['custom']);
                }
                else
                {
                    $this->custom = array(
                        'payment'  => ''
                    );
                }

                if (IClient::getDevice() == 'mobile')
                {
                    $this->redirect('payment_m',false);
                }
                else
                {
                    $this->redirect('payment_pc',false);
                }
            }
        }
        else
        {
            $this->redirect('/simple/login');
        }
    }

    /**
     * @brief dyg_jzw 20170113 增加支付收银台
     */
    public function payment_act()
    {
        //订单ID
        $order_id      = IFilter::act(IReq::get('id'),'int');

        //支付方式
        $payment      = IFilter::act(IReq::get('payment'),'int');

        //获取订单信息
        $orderDB = new IModel("order");
        $orderRow = $orderDB->getObj("id = ".$order_id);

        if ($orderRow && $orderRow['user_id'] == $this->user['user_id'])
        {
            //是否已支付
            if ($orderRow['pay_status'] > 0)
            {
                $this->redirect('/ucenter/order_detail/id/'.$order_id);
            }
            elseif($orderRow['status'] != 1)
            {
                IError::show(403,'订单已被取消，无法支付');
            }
            else
            {
                $paymentObj = new IModel('payment');
                $paymentRow = $paymentObj->getObj('id = '.$payment,'type,name');
                if(!$paymentRow || $payment == 0) //不支持货到付款
                {
                    IError::show(403,'支付方式不存在');
                }

                //保存用户习惯的支付方式
                $memberObj = new IModel('member');
                $memberRow = $memberObj->getObj('user_id = '.$orderRow['user_id'],'custom');

                $_custom = unserialize($memberRow['custom']);
                $memberData = array(
                    'custom' => serialize(
                        array(
                            'payment'  => $payment,
                            'delivery' => $_custom['delivery'],
                            'takeself' => $_custom['takeself'],
                        )
                    ),
                );
                $memberObj->setData($memberData);
                $memberObj->update('user_id = '.$orderRow['user_id']);

                //修改支付方式
                $orderDB->setData(array('pay_type' => $payment));
                $orderDB->update('id = '.$order_id);

                //跳转支付
                $this->redirect('/block/doPay/order_id/'.$order_id);
            }
        }
        else
        {
            $this->redirect('/simple/login');
        }

    }

    /**
     * @brief 【重要】支付回调[同步]
     */
    public function callback()
    {
        //从URL中获取支付方式
        $payment_id      = IFilter::act(IReq::get('_id'),'int');
        $paymentInstance = Payment::createPaymentInstance($payment_id);

        if(!is_object($paymentInstance))
        {
            IError::show(403,'支付方式不存在');
        }

        //初始化参数
        $money   = '';
        $message = '支付失败';
        $orderNo = '';

        //执行接口回调函数
        $callbackData = array_merge($_POST,$_GET);
        unset($callbackData['controller']);
        unset($callbackData['action']);
        unset($callbackData['_id']);

        $return = $paymentInstance->callback($callbackData,$payment_id,$money,$message,$orderNo);

        //支付成功
        if($return == 1)
        {
            //充值方式
            if(stripos($orderNo,'recharge') !== false)
            {
                $tradenoArray = explode('recharge',$orderNo);
                $recharge_no  = isset($tradenoArray[1]) ? $tradenoArray[1] : 0;
                if(payment::updateRecharge($recharge_no))
                {
                    $this->redirect('/site/success/message/'.urlencode("充值成功").'/?callback=/ucenter/account_log');
                    return;
                }
                IError::show(403,'充值失败');
            }
            else
            {
                //订单批量结算缓存机制
                $moreOrder = Order_Class::getBatch($orderNo);
                if($money >= array_sum($moreOrder))
                {
                    foreach($moreOrder as $key => $item)
                    {
                        $order_id = Order_Class::updateOrderStatus($key);
                        if(!$order_id)
                        {
                            IError::show(403,'订单修改失败');
                        }
                    }
                    $this->redirect('/site/success/message/'.urlencode("支付成功").'/?callback=/ucenter/order');
                    return;
                }
                $message = '付款金额与订单金额不符合';
            }
        }
        //支付失败
        $message = $message ? $message : '支付失败';
        IError::show(403,$message);
    }

    /**
     * @brief 【重要】支付回调[异步]
     */
    function server_callback()
    {
        //从URL中获取支付方式
        $payment_id      = IFilter::act(IReq::get('_id'),'int');
        $paymentInstance = Payment::createPaymentInstance($payment_id);

        if(!is_object($paymentInstance))
        {
            die('fail');
        }

        //初始化参数
        $money   = '';
        $message = '支付失败';
        $orderNo = '';

        //执行接口回调函数
        $callbackData = array_merge($_POST,$_GET);
        unset($callbackData['controller']);
        unset($callbackData['action']);
        unset($callbackData['_id']);
        if(empty($callbackData)){
            $callbackData  = $GLOBALS["HTTP_RAW_POST_DATA"];
        }
        $return = $paymentInstance->serverCallback($callbackData,$payment_id,$money,$message,$orderNo);
        //支付成功
        if($return == 1)
        {
            //充值方式
            if(stripos($orderNo,'recharge') !== false)
            {
                $tradenoArray = explode('recharge',$orderNo);
                $recharge_no  = isset($tradenoArray[1]) ? $tradenoArray[1] : 0;
                if(payment::updateRecharge($recharge_no))
                {
                    $paymentInstance->notifyStop();
                }
            }
            else
            {
                //订单批量结算缓存机制
                $moreOrder = Order_Class::getBatch($orderNo);
                if($money >= array_sum($moreOrder))
                {
                    foreach($moreOrder as $key => $item)
                    {
                        $order_id = Order_Class::updateOrderStatus($key);
                        if(!$order_id)
                        {
                            throw new IException("异步支付回调修改状态错误，订单ID：".$order_id);
                        }
                    }
                    $paymentInstance->notifyStop();
                }
            }
        }
        //支付失败
        else
        {
            die('fail');
        }
    }

    /**
     * @brief 【重要】支付中断处理
     */
    public function merchant_callback()
    {
        $this->redirect('/site/index');
    }

    /**
     * @brief 根据省份名称查询相应的province
     */
    public function searchProvince()
    {
        $province = IFilter::act(IReq::get('province'));

        $tb_areas = new IModel('areas');
        $areas_info = $tb_areas->getObj('parent_id = 0 and area_name like "%'.$province.'%"','area_id');
        $result = array('flag' => 'fail','area_id' => 0);
        if($areas_info)
        {
            $result = array('flag' => 'success','area_id' => $areas_info['area_id']);
        }
        echo JSON::encode($result);
    }
    //添加实体代金券
    function add_download_ticket()
    {
        $isError = true;

        $ticket_num = IFilter::act(IReq::get('ticket_num'));
        $ticket_pwd = IFilter::act(IReq::get('ticket_pwd'));

        //代金券状态是否正常
        $propObj = new IModel('prop');
        $propRow = $propObj->getObj('card_name = "'.$ticket_num.'" and card_pwd = "'.$ticket_pwd.'" and type = 0 and is_userd = 0 and is_send = 1 and is_close = 0 and NOW() between start_time and end_time');
        if(!$propRow)
        {
            $message = '代金券不可用，请确认代金券的卡号密码并且此代金券从未被使用过';
            $result = array(
                'isError' => $isError,
                'message' => $message,
            );
            die(JSON::encode($result));
        }

        //代金券是否已经被领取
        $memberObj = new IModel('member');
        $isRev     = $memberObj->query('FIND_IN_SET('.$propRow['id'].',prop)');
        if($isRev)
        {
            $message = '代金券已经被领取';
            $result = array(
                'isError' => $isError,
                'message' => $message,
            );
            die(JSON::encode($result));
        }

        //登录用户
        $isError = false;
        $message = '添加成功';
        if($this->user['user_id'])
        {
            $memberRow = $memberObj->getObj('user_id = '.$this->user['user_id'],'prop');
            if($memberRow['prop'] == '')
            {
                $propUpdate = ','.$propRow['id'].',';
            }
            else
            {
                $propUpdate = $memberRow['prop'].$propRow['id'].',';
            }

            $dataArray = array('prop' => $propUpdate);
            $memberObj->setData($dataArray);
            $memberObj->update('user_id = '.$this->user['user_id']);
        }
        //游客方式
        else
        {
            ISafe::set("ticket_".$propRow['id'],$propRow['id']);
        }

        $result = array(
            'isError' => $isError,
            'data'    => $propRow,
            'message' => $message,
        );

        die(JSON::encode($result));
    }

    private function alert($msg)
    {
        header('Content-type: text/html; charset=UTF-8');
        echo JSON::encode(array('error' => 1, 'message' => $msg));
        exit;
    }
    /**
     * 筛选用户
     */
    public function filter_user()
    {
        $where   = array();
        $userIds = '';
        $search  = IFilter::act(IReq::get('search'),'strict');
        $search  = $search ? $search : array();

        foreach($search as $key => $val)
        {
            if($val)
            {
                $where[] = $key.'"'.$val.'"';
            }
        }

        //有筛选条件
        if($where)
        {
            $userDB = new IQuery('user as u');
            $userDB->join  = 'left join member as m on u.id = m.user_id';
            $userDB->fields= 'u.id';
            $userDB->where = join(" and ",$where);
            $userData      = $userDB->find();
            $tempArray     = array();
            foreach($userData as $key => $item)
            {
                $tempArray[] = $item['id'];
            }
            $userIds = join(',',$tempArray);

            if(!$userIds)
            {
                die('<script type="text/javascript">alert("没有找到用户信息,请重新输入条件");window.history.go(-1);</script>');
            }
        }

        die('<script type="text/javascript">parent.searchUserCallback("'.$userIds.'");</script>');
    }
    /*
     * @breif ajax添加商品扩展属性
     * */
    function attribute_init()
    {
        $id = IFilter::act(IReq::get('model_id'),'int');
        $tb_attribute = new IModel('attribute');
        $attribute_info = $tb_attribute->query('model_id='.$id);
        echo JSON::encode($attribute_info);
    }

    //获取商品数据
    public function getGoodsData()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        $productDB = new IQuery('products as p');
        $productDB->join  = 'left join goods as go on go.id = p.goods_id';
        $productDB->where = 'go.id = '.$id;
        $productDB->fields= 'p.*,go.name';
        $productData = $productDB->find();

        if(!$productData)
        {
            $goodsDB   = new IModel('goods');
            $productData = $goodsDB->query('id = '.$id);
        }
        echo JSON::encode($productData);
    }

    //获取商品的推荐标签数据
    public function goodsCommend()
    {
        //商品字符串的逗号间隔
        $id = IFilter::act(IReq::get('id'));
        if($id)
        {
            $idArray = explode(",",$id);
            $idArray = IFilter::act($idArray,'int');
            $id = join(',',$idArray);
        }

        $goodsDB = new IModel('goods');
        $goodsData = $goodsDB->query("id in (".$id.")","id,name");

        $goodsCommendDB = new IModel('commend_goods');
        foreach($goodsData as $key => $val)
        {
            $goodsCommendData = $goodsCommendDB->query("goods_id = ".$val['id']);
            foreach($goodsCommendData as $k => $v)
            {
                $goodsData[$key]['commend'][$v['commend_id']] = 1;
            }
        }
        die(JSON::encode($goodsData));
    }

    //获取自提点数据
    public function getTakeselfList()
    {
        $id   = IFilter::act(IReq::get('id'),'int');
        $type = IFilter::act(IReq::get('type'));
        $takeselfObj = new IQuery('takeself');

        switch($type)
        {
            case "province":
            {
                $where = "province = ".$id;
                $takeselfObj->group = 'city';
            }
                break;

            case "city":
            {
                $where = "city = ".$id;
                $takeselfObj->group = 'area';
            }
                break;

            case "area":
            {
                $where = "area = ".$id;
            }
                break;
        }

        $takeselfObj->where = $where;
        $takeselfList = $takeselfObj->find();

        foreach($takeselfList as $key => $val)
        {
            $temp = area::name($val['province'],$val['city'],$val['area']);
            $takeselfList[$key]['province_str'] = $temp[$val['province']];
            $takeselfList[$key]['city_str']     = $temp[$val['city']];
            $takeselfList[$key]['area_str']     = $temp[$val['area']];
        }
        die(JSON::encode($takeselfList));
    }

    //物流轨迹查询
    public function freight()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        if($id)
        {
            $tb_freight = new IQuery('delivery_doc as d');
            $tb_freight->join  = 'left join freight_company as f on f.id = d.freight_id';
            $tb_freight->where = 'd.id = '.$id;
            $tb_freight->fields= 'd.*,f.freight_type';
            $freightData = $tb_freight->find();

            if($freightData)
            {
                $freightData = current($freightData);
                if($freightData['freight_type'] && $freightData['delivery_code'])
                {
                    $result = freight_facade::line($freightData['freight_type'],$freightData['delivery_code']);
                    if($result['result'] == 'success')
                    {
                        $this->data = $result['data'];
                        $this->redirect('freight');
                        return;
                    }
                    else
                    {
                        die(isset($result['reason']) ? $result['reason'] : '物流接口发生错误');
                    }
                }
                else
                {
                    die('缺少物流信息');
                }
            }
        }
        die('发货单信息不存在');
    }
    /**
     * 筛选商户
     */
    public function filter_seller()
    {
        $where   = '';
        $sellerIds = '';
        $search  = IFilter::act(IReq::get('search'),'strict');
        $search  = $search ? $search : array();

        foreach($search as $key => $val)
        {
            if($val)
            {
                $where .= $key.'"'.$val.'"';
            }
        }

        //有筛选条件
        if($where)
        {
            $sellerDB = new IQuery('seller');
            $sellerDB->fields= 'id';
            $sellerDB->where = $where;
            $sellerData      = $sellerDB->find();
            $tempArray     = array();
            foreach($sellerData as $key => $item)
            {
                $tempArray[] = $item['id'];
            }
            $sellerIds = join(',',$tempArray);

            if(!$sellerIds)
            {
                die('<script type="text/javascript">alert("没有找到商户信息,请重新输入条件");window.history.go(-1);</script>');
            }
        }

        die('<script type="text/javascript">parent.searchSellerCallback("'.$sellerIds.'");</script>');
    }

    //收货地址弹出框
    public function address()
    {
        $user_id = $this->user['user_id'];
        $id      = IFilter::act(IReq::get('id'),'int');
        $this->is_cbe      = IFilter::act(IReq::get('is_cbe'),'int'); //dyg_jzw 20160613修改跨境电商地址
        if($user_id)
        {
            if($id)
            {
                $addressDB        = new IModel('address');
                $this->addressRow = $addressDB->getObj('user_id = '.$user_id.' and id = '.$id);
                if ($this->addressRow['accept_id'])
                {
                    $this->is_cbe = 1;
                }
            }
        }
        else
        {
            $this->addressRow = ISafe::get('address');
        }
        $this->redirect('address');
    }

    //代金券弹出框
    public function ticket()
    {
        $this->prop       = array();
        $this->sellerInfo = IFilter::act(IReq::get('sellerString'));
        $user_id          = $this->user['user_id'];

        //获取代金券
        if($user_id)
        {
            //-------------------
            //dyg_jzw 20170105 获取直接购买的参数
            $gid = IFilter::act(IReq::get('direct_gid'), 'int');
            $type = IFilter::act(IReq::get('direct_type'));
            $num = IFilter::act(IReq::get('direct_num'), 'int');
            $promo = IFilter::act(IReq::get('direct_promo'));
            $active_id = IFilter::act(IReq::get('direct_active_id'), 'int');

            //获取商品数据信息
            $countSumObj = new CountSum($user_id);
            $goodsResult = $countSumObj->cart_count($gid,$type,$num,$promo,$active_id);

            //-------------------

            $memberObj = new IModel('member');
            $memberRow = $memberObj->getObj('user_id = '.$user_id,'prop');

            if(isset($memberRow['prop']) && ($propId = trim($memberRow['prop'],',')))
            {
                $prop_ids = explode(",", trim($memberRow['prop'],','));
                $prop = array();

                //dyg_jzw 20170105 计算代金券是否可用
                $seller_ids = explode("_", $this->sellerInfo);

                foreach($prop_ids as $tid)
                {
                    $ticketRow = Api::run('checkTicketUseful', $tid, $goodsResult, $seller_ids);

                    if ($ticketRow)
                    {
                        $prop[] = $ticketRow;
                    }

                }

                $this->prop = $prop;
            }
        }
        $this->redirect('ticket');
    }

    public function ticket_json()
    {
        $sellerInfo = IFilter::act(IReq::get('sellerString'));
        $user_id          = $this->user['user_id'];

        $flag = 0;
        //获取代金券
        if($user_id)
        {
            //-------------------
            //dyg_jzw 20170105 获取直接购买的参数
            $gid = IFilter::act(IReq::get('direct_gid'), 'int');
            $type = IFilter::act(IReq::get('direct_type'));
            $num = IFilter::act(IReq::get('direct_num'), 'int');
            $promo = IFilter::act(IReq::get('direct_promo'));
            $active_id = IFilter::act(IReq::get('direct_active_id'), 'int');
            $ticket_code = IFilter::act(IReq::get('ticket_code'));//通过券码查找

            //获取商品数据信息
            $countSumObj = new CountSum($user_id);
            $goodsResult = $countSumObj->cart_count($gid,$type,$num,$promo,$active_id);
            $prop = array();
            //-------------------
            $memberObj = new IModel('member');
            $memberRow = $memberObj->getObj('user_id = '.$user_id,'prop');
            if(isset($memberRow['prop']) && ($propId = trim($memberRow['prop'],',')))
            {
                $result['success'] = 1;
                $prop_ids = explode(",", trim($memberRow['prop'],','));
                //dyg_jzw 20170105 计算代金券是否可用
                $seller_ids = explode("_", $sellerInfo);
                $card_name_arr = array();
                foreach($prop_ids as $tid)
                {
                    $ticketRow = Api::run('checkTicketUseful', $tid, $goodsResult, $seller_ids);
                    if ($ticketRow)
                    {
                        $prop[] = $ticketRow;
                        $card_name_arr[] = $ticketRow['card_name'];
                    }
                }
                //-----------------------------------------------
                //通过输入券码兑换此券
                if($ticket_code && !in_array($ticket_code,$card_name_arr))
                {
                    $ticketOneROW = Api::run('checkTicketUseful', 0 , $goodsResult, explode("_", $sellerInfo),$ticket_code,$user_id);
                    if($ticketOneROW)
                    {
                        $prop[] = $ticketOneROW;
                    }
                    else
                    {
                        $result['success'] = 0;
                    }
                }
                foreach ($prop as &$val)
                {
                    unset($val['card_pwd']);
                    unset($val['type']);
                    unset($val['condition']);
                    unset($val['img']);
                }
                $result['tickets'] = $prop;
                echo json_encode($result);
            }

        }

    }
    //地址请求返回JSON
    public function address_json()
    {
        $str_char = IFilter::act(IReq::get('char'));
        $result = array('success'=>0);
        if(is_array($str_char) && $str_char)
        {
            $result['hot'] = Api::run('getCityByChar','hot','');
            foreach ($str_char as $k=>$char)
            {
                $result['normal'][$char] = Api::run('getCityByChar','normal',$char);
            }
            $result['success'] = 1;
        }
        echo json_encode($result);
    }
    //区县请求返回JSON
    public function address_json2()
    {
        $area_id = IFilter::act(IReq::get('city_id'));
        $result = array('success'=>0);
        if($area_id)
        {
            $result['normal'] = Api::run('getCityChild',$area_id);
            $result['success'] = 1;
        }
        echo json_encode($result);
    }
    //根据选择的area_id 返回seller_id
    public function get_sellerid_json()
    {
        $result = array('success'=>1);
        $result['seller_id'] = ISafe::get('buy_seller','session');
        echo json_encode($result);
    }
}