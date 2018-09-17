<?php

/**
 * @brief 订单模块
 * @class Order
 * @note  后台
 */
class Order extends IController implements adminAuthorization
{
    public $checkRight = 'all';
    public $layout = 'admin';

    function init()
    {

    }

    /**
     * @brief查看订单
     */
    public function order_show()
    {
        //获得post传来的值
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $data = array();
        if ($order_id) {
            $order_show = new Order_Class();
            $data = $order_show->getOrderShow($order_id);

            if ($data) {
                //获取地区
                $data['area_addr'] = join('&nbsp;', area::name($data['province'], $data['city'], $data['area']));

                $this->setRenderData($data);
                $this->redirect('order_show', false);
            }
        }
        if (!$data) {
            $this->redirect('order_list');
        }
    }

    /**
     * @brief查看收款单
     */
    public function collection_show()
    {
        //获得post传来的收款单id值
        $collection_id = IFilter::act(IReq::get('id'), 'int');
        $data = array();
        if ($collection_id) {
            $tb_collection = new IQuery('collection_doc as c ');
            $tb_collection->join = ' left join order as o on c.order_id=o.id left join payment as p on c.payment_id = p.id left join user as u on u.id = c.user_id';
            $tb_collection->fields = 'o.order_no,p.name as pname,o.create_time,p.type,u.username,c.amount,o.pay_time,c.admin_id,c.note';
            $tb_collection->where = 'c.id=' . $collection_id;
            $collection_info = $tb_collection->find();
            if ($collection_info) {
                $data = $collection_info[0];

                $this->setRenderData($data);
                $this->redirect('collection_show', false);
            }
        }
        if (count($data) == 0) {
            $this->redirect('order_collection_list');
        }
    }

    /**
     * @brief查看退款单
     */
    public function refundment_show()
    {
        //获得post传来的退款单id值
        $refundment_id = IFilter::act(IReq::get('id'), 'int');
        $data = array();
        if ($refundment_id) {
            $tb_refundment = new IQuery('refundment_doc as c');
            $tb_refundment->join = ' left join order as o on c.order_id=o.id left join user as u on u.id = c.user_id';
            $tb_refundment->fields = 'o.order_no,o.create_time,u.username,c.*';
            $tb_refundment->where = 'c.id=' . $refundment_id;
            $refundment_info = $tb_refundment->find();
            if ($refundment_info) {
                $data = current($refundment_info);
                $this->setRenderData($data);
                $this->redirect('refundment_show', false);
            }
        }

        if (!$data) {
            $this->redirect('order_refundment_list');
        }
    }

    /**
     * @brief查看申请退款单
     */
    public function refundment_doc_show()
    {
        //获得post传来的申请退款单id值
        $refundment_id = IFilter::act(IReq::get('id'), 'int');
        if ($refundment_id) {
            $refundsDB = new IModel('refundment_doc');
            $data = $refundsDB->getObj('id = ' . $refundment_id);
            if ($data) {
                $this->setRenderData($data);
                $this->redirect('refundment_doc_show', false);
                return;
            }
        }

        $this->redirect('refundment_list');
    }

    //删除申请退款单
    public function refundment_doc_del()
    {
        //获得post传来的申请退款单id值
        $refundment_id = IFilter::act(IReq::get('id'), 'int');
        if (is_array($refundment_id)) {
            $refundment_id = implode(",", $refundment_id);
        }
        if ($refundment_id) {
            $tb_refundment_doc = new IModel('refundment_doc');
            $tb_refundment_doc->setData(array('if_del' => 1));
            $tb_refundment_doc->update("id IN ($refundment_id)");
        }

        $logObj = new log('db');
        $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "退款单移除到回收站", '移除的ID：' . $refundment_id));

        $this->redirect('refundment_list');
    }

    /**
     * @brief更新申请退款单
     */
    public function refundment_doc_show_save()
    {
        //获得post传来的退款单id值
        $refundment_id = IFilter::act(IReq::get('id'), 'int');
        $pay_status = IFilter::act(IReq::get('pay_status'), 'int');
        $dispose_idea = IFilter::act(IReq::get('dispose_idea'), 'text');

        //获得refundment_doc对象
        $tb_refundment_doc = new IModel('refundment_doc');
        $tb_refundment_doc->setData(array(
            'pay_status' => $pay_status,
            'dispose_idea' => $dispose_idea,
            'dispose_time' => ITime::getDateTime(),
            'admin_id' => $this->admin['admin_id'],
        ));

        if ($refundment_id) {
            $tb_refundment_doc->update('id=' . $refundment_id);

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "修改了退款单", '修改的ID：' . $refundment_id));
        }
        $this->redirect('refundment_list');
    }

    /**
     * @brief查看发货单
     */
    public function delivery_show()
    {
        //获得post传来的发货单id值
        $delivery_id = IFilter::act(IReq::get('id'), 'int');
        $data = array();
        if ($delivery_id) {
            $tb_delivery = new IQuery('delivery_doc as c ');
            $tb_delivery->join = ' left join order as o on c.order_id=o.id left join delivery as p on c.delivery_type = p.id left join user as u on u.id = c.user_id';
            $tb_delivery->fields = 'c.id as id,o.order_no,c.order_id,p.name as pname,o.create_time,u.username,c.name,c.province,c.city,c.area,c.address,c.mobile,c.telphone,c.postcode,c.freight,c.delivery_code,c.time,c.note ';
            $tb_delivery->where = 'c.id=' . $delivery_id;
            $delivery_info = $tb_delivery->find();
            if ($delivery_info) {
                $data = current($delivery_info);
                $data['country'] = join("-", area::name($data['province'], $data['city'], $data['area']));

                $this->setRenderData($data);
                $this->redirect('delivery_show', false);
            }
        }

        if (!$data) {
            $this->redirect('order_delivery_list');
        }
    }

    /**
     * @brief 发货单导出 dyg_jzw 20160517
     */
    public function order_deliver_output()
    {
        $startDate = IFilter::act(IReq::get('startDate'));
        $endDate = IFilter::act(IReq::get('endDate'));

        $where = "";
        if ($startDate) {
            $where .= " and c.time >= '{$startDate}' ";
        }

        if ($endDate) {
            $temp = $endDate . ' 23:59:59';
            $where .= " and c.time <= '{$temp}' ";
        }

        $tb_delivery = new IQuery('delivery_doc as c ');
        $tb_delivery->join = ' left join order as o on c.order_id=o.id left join user as m on m.id = c.user_id left join freight_company as fr on c.freight_id=fr.id';
        $tb_delivery->fields = 'o.order_no,c.name,c.delivery_code,fr.freight_name,c.id,c.time,c.freight,m.username';
        $tb_delivery->where = 'c.if_del=0' . $where;
        $delivery_list = $tb_delivery->find();

        if ($delivery_list) {
            $strTable = '<table border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td align="center" width="*">订单号</td>';
            $strTable .= '<th align="center" width="10%">收货人</th>';
            $strTable .= '<th align="center" width="20%">物流单号</th>';
            $strTable .= '<th align="center" width="15%">物流公司</th>';
            $strTable .= '<th align="center" width="10%">运费</th>';
            $strTable .= '<th align="center" width="10%">会员名</th>';
            $strTable .= '<th align="center" width="13%">完成时间</th>';
            $strTable .= '</tr>';

            foreach ($delivery_list as $item) {
                $strTable .= '<tr>';
                $strTable .= '<td>' . $item['order_no'] . '</td>';
                $strTable .= '<td>' . $item['name'] . '</td>';
                $strTable .= '<td>' . $item['delivery_code'] . '</td>';
                $strTable .= '<td>' . $item['freight_name'] . '</td>';
                $strTable .= '<td>' . $item['freight'] . '</td>';
                $strTable .= '<td>' . ($item['username'] == '' ? '游客' : $item['username']) . '</td>';
                $strTable .= '<td>' . $item['time'] . '</td>';
                $strTable .= '</tr>';
            }

            $strTable .= '</table>';
            $reportObj = new report();
            $reportObj->setFileName('delivery_doc_list_from_' . $startDate . '_to_' . $endDate);
            $reportObj->toDownload($strTable);
            exit();
        }
    }

    /**
     * @brief 支付订单页面collection_doc
     */
    public function order_collection()
    {
        //去掉左侧菜单和上部导航
        $this->layout = '';
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $data = array();
        if ($order_id) {
            $order_show = new Order_Class();
            $data = $order_show->getOrderShow($order_id);
        }
        $this->setRenderData($data);
        $this->redirect('order_collection');
    }

    /**
     * @brief 保存支付订单页面collection_doc
     */
    public function order_collection_doc()
    {
        //获得订单号
        $order_no = IFilter::act(IReq::get('order_no'));
        $note = IFilter::act(IReq::get('note'));

        if (Order_Class::updateOrderStatus($order_no, $this->admin['admin_id'], $note)) {
            //生成订单日志
            $tb_order_log = new IModel('order_log');
            $tb_order_log->setData(array(
                'order_id' => IFilter::act(IReq::get('id'), 'int'),
                'user' => $this->admin['admin_name'],
                'action' => '付款',
                'result' => '成功',
                'note' => '订单【' . $order_no . '】付款' . IFilter::act(IReq::get('amount'), 'float') . '元',
                'addtime' => ITime::getDateTime(),
            ));
            $tb_order_log->add();

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "订单更新为已付款", "订单号：" . $order_no . '，已经确定付款'));
            echo '<script type="text/javascript">parent.actionCallback();</script>';
        } else {
            echo '<script type="text/javascript">parent.actionFailCallback();</script>';
        }
    }

    /**
     * @brief 退款单页面
     */
    public function order_refundment()
    {
        //去掉左侧菜单和上部导航
        $this->layout = '';
        $orderId = IFilter::act(IReq::get('id'), 'int');
        $refundsId = IFilter::act(IReq::get('refunds_id'), 'int');

        if ($orderId) {
            $orderDB = new Order_Class();
            $data = $orderDB->getOrderShow($orderId);

            //已经存退款申请
            if ($refundsId) {
                $refundsDB = new IModel('refundment_doc');
                $refundsRow = $refundsDB->getObj('id = ' . $refundsId);
                $data['refunds'] = $refundsRow;
            }
            $this->setRenderData($data);
            $this->data = $data;
            $this->redirect('order_refundment');
            return;
        }
        die('订单数据不存在');
    }

    /**
     * @brief 保存退款单页面
     */
    public function order_refundment_doc()
    {
        $refunds_id = IFilter::act(IReq::get('refunds_id'), 'int');
        $amount = IFilter::act(IReq::get('amount'), 'float');
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $order_no = IFilter::act(IReq::get('order_no'));
        $user_id = IFilter::act(IReq::get('user_id'), 'int');
        $order_goods_id = IFilter::act(IReq::get('order_goods_id'), 'int'); //要退款的商品,如果是用户已经提交的退款申请此数据为NULL,需要获取出来
        $way = IFilter::act(IReq::get('way'));

        //访客订单不能退款到余额中
        if (!$user_id && $way == "balance") {
            die('<script text="text/javascript">parent.actionCallback("游客无法退款");</script>');
        }

        //1,退款单存在更新退款价格
        $tb_refundment_doc = new IModel('refundment_doc');

        if ($refunds_id) {
            $updateData = array('amount' => $amount);
            $tb_refundment_doc->setData($updateData);
            $tb_refundment_doc->update("id = " . $refunds_id);
        } //2,无退款申请单，必须生成退款单
        else {
            if (!$order_goods_id) {
                die('<script text="text/javascript">parent.actionCallback("请选择要退款的商品");</script>');
            }

            $orderDB = new IModel('order');
            $orderRow = $orderDB->getObj("id = " . $order_id);

            //插入refundment_doc表
            $updateData = array(
                'amount' => $amount,
                'order_no' => $order_no,
                'order_id' => $order_id,
                'admin_id' => $this->admin['admin_id'],
                'pay_status' => 2,
                'dispose_time' => ITime::getDateTime(),
                'dispose_idea' => '退款成功',
                'user_id' => $user_id,
                'time' => ITime::getDateTime(),
                'seller_id' => $orderRow['seller_id'],
                'order_goods_id' => join(",", $order_goods_id),
            );
            $tb_refundment_doc->setData($updateData);
            $refunds_id = $tb_refundment_doc->add();
        }

        $result = Order_Class::refund($refunds_id, $this->admin['admin_id'], 'admin', $way);
        if (is_string($result)) {
            $tb_refundment_doc->rollback();
            die('<script text="text/javascript">parent.actionCallback("' . $result . '");</script>');
        } else {
            //记录操作日志
            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "订单更新为退款", '订单号：' . $order_no));
            die('<script text="text/javascript">parent.actionCallback();</script>');
        }
    }

    /**
     * @brief 保存订单备注
     */
    public function order_note()
    {
        //获得post数据
        $order_id = IFilter::act(IReq::get('order_id'), 'int');
        $note = IFilter::act(IReq::get('note'), 'text');

        //获得order的表对象
        $tb_order = new IModel('order');
        $tb_order->setData(array(
            'note' => $note
        ));
        $tb_order->update('id=' . $order_id);
        IReq::set('id', $order_id);
        $this->order_show();

    }

    /**
     * @brief 保存顾客留言
     */
    public function order_message()
    {
        //获得post数据
        $order_id = IFilter::act(IReq::get('order_id'), 'int');
        $user_id = IFilter::act(IReq::get('user_id'), 'int');
        $title = IFilter::act(IReq::get('title'));
        $content = IFilter::act(IReq::get('content'), 'text');

        //获得message的表对象
        $tb_message = new IModel('message');
        $tb_message->setData(array(
            'title' => $title,
            'content' => $content,
            'time' => ITime::getDateTime(),
        ));
        $message_id = $tb_message->add();
        //获的mess类
        $message = new Mess($user_id);
        $message->writeMessage($message_id);
        IReq::set('id', $order_id);
        $this->order_show();
    }

    /**
     * @brief 完成或作废订单页面
     **/
    public function order_complete()
    {
        //去掉左侧菜单和上部导航
        $this->layout = '';
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $type = IFilter::act(IReq::get('type'), 'int');
        $order_no = IFilter::act(IReq::get('order_no'));

        //oerder表的对象
        $tb_order = new IModel('order');
        $tb_order->setData(array(
            'status' => $type,
            'completion_time' => ITime::getDateTime(),
        ));
        $tb_order->update('id=' . $order_id);

        //生成订单日志
        $tb_order_log = new IModel('order_log');
        $action = '作废';
        $note = '订单【' . $order_no . '】作废成功';

        if ($type == '5') {
            $action = '完成';
            $note = '订单【' . $order_no . '】完成成功';

            //完成订单并且进行支付
            Order_Class::updateOrderStatus($order_no);

            //增加用户评论商品机会
            Order_Class::addGoodsCommentChange($order_id);

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "订单更新为完成", '订单号：' . $order_no));
        } else {
            Order_class::resetOrderProp($order_id);

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "订单更新为作废", '订单号：' . $order_no));
        }

        $tb_order_log->setData(array(
            'order_id' => $order_id,
            'user' => $this->admin['admin_name'],
            'action' => $action,
            'result' => '成功',
            'note' => $note,
            'addtime' => ITime::getDateTime(),
        ));
        $tb_order_log->add();
        die('success');
    }

    /**
     * @brief 发货订单页面
     */
    public function order_deliver()
    {
        //去掉左侧菜单和上部导航
        $this->layout = '';
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $data = array();
        if ($order_id) {
            $order_show = new Order_Class();
            $data = $order_show->getOrderShow($order_id);
        }
        $this->setRenderData($data);
        $this->redirect('order_deliver');
    }

    /**
     * @brief 发货操作
     */
    public function order_delivery_doc()
    {
        //获得post变量参数
        $order_id = IFilter::act(IReq::get('id'), 'int');

        //发送的商品关联
        $sendgoods = IFilter::act(IReq::get('sendgoods'));

        if (!$sendgoods) {
            die('<script type="text/javascript">parent.actionCallback("请选择要发货的商品");</script>');
        }

        $result = Order_Class::sendDeliveryGoods($order_id, $sendgoods, $this->admin['admin_id']);

        if ($result === true) {
            die('<script type="text/javascript">parent.actionCallback();</script>');
        }
        die('<script type="text/javascript">parent.actionCallback("' . $result . '");</script>');
    }

    /**
     * @brief 保存修改订单
     */
    public function order_update()
    {
        //获取必要数据
        $order_id = IFilter::act(IReq::get('id'), 'int');

        //生成order数据
        $dataArray = array();
        $dataArray['invoice_title'] = IFilter::act(IReq::get('invoice_title'));
        $dataArray['invoice'] = IFilter::act(IReq::get('invoice'), 'int');
        $dataArray['pay_type'] = IFilter::act(IReq::get('pay_type'), 'int');
        $dataArray['accept_name'] = IFilter::act(IReq::get('accept_name'));
        $dataArray['postcode'] = IFilter::act(IReq::get('postcode'));
        $dataArray['telphone'] = IFilter::act(IReq::get('telphone'));
        $dataArray['province'] = IFilter::act(IReq::get('province'), 'int');
        $dataArray['city'] = IFilter::act(IReq::get('city'), 'int');
        $dataArray['area'] = IFilter::act(IReq::get('area'), 'int');
        $dataArray['address'] = IFilter::act(IReq::get('address'));
        $dataArray['mobile'] = IFilter::act(IReq::get('mobile'));
        $dataArray['discount'] = $order_id ? IFilter::act(IReq::get('discount'), 'float') : 0;
        $dataArray['postscript'] = IFilter::act(IReq::get('postscript'));
        $dataArray['distribution'] = IFilter::act(IReq::get('distribution'), 'int');
        $dataArray['accept_time'] = IFilter::act(IReq::get('accept_time'));
        $dataArray['takeself'] = IFilter::act(IReq::get('takeself'));
        $dataArray['real_freight'] = IFilter::act(IReq::get('real_freight'));
        $dataArray['note'] = IFilter::act(IReq::get('note'));

        //设置订单持有者
        $username = IFilter::act(IReq::get('username'));
        $userDB = new IModel('user');
        $userRow = $userDB->getObj('username = "' . $username . '"');
        $dataArray['user_id'] = isset($userRow['id']) ? $userRow['id'] : 0;

        //拼接要购买的商品或货品数据,组装成固有的数据结构便于计算价格
        $goodsId = IFilter::act(IReq::get('goods_id'));
        $productId = IFilter::act(IReq::get('product_id'));
        $num = IFilter::act(IReq::get('goods_nums'));

        $goodsArray = array();
        $productArray = array();
        if (!$goodsId) {
            IError::show("商品信息不存在");
            exit;
        }

        //dyg_jzw 20160905 是否没有地址信息
        if (empty($dataArray['province']) || empty($dataArray['city']) || empty($dataArray['area'])) {
            IError::show("地址信息不完整");
            exit;
        }

        foreach ($goodsId as $key => $goods_id) {
            $pid = $productId[$key];
            $nVal = $num[$key];

            if ($pid > 0) {
                $productArray[$pid] = $nVal;
            } else {
                $goodsArray[$goods_id] = $nVal;
            }
        }

        //开始算账
        $countSumObj = new CountSum($dataArray['user_id']);
        $cartObj = new Cart();
        $goodsResult = $countSumObj->goodsCount($cartObj->cartFormat(array("goods" => $goodsArray, "product" => $productArray)));
        $orderData = $countSumObj->countOrderFee($goodsResult, $dataArray['province'], $dataArray['distribution'], $dataArray['pay_type'], $dataArray['invoice'], $dataArray['discount']);
        if (is_string($orderData)) {
            IError::show(403, $orderData);
            exit;
        }

        //根据商品所属商家不同批量生成订单
        foreach ($orderData as $seller_id => $goodsResult) {
            //运费自定义
            if (is_numeric($dataArray['real_freight']) && $goodsResult['deliveryPrice'] != $dataArray['real_freight']) {
                $goodsResult['orderAmountPrice'] += $dataArray['real_freight'] - $goodsResult['deliveryPrice'];
                $goodsResult['deliveryPrice'] = $dataArray['real_freight'];
            }
            $dataArray['payable_freight'] = $goodsResult['deliveryOrigPrice'];
            $dataArray['payable_amount'] = $goodsResult['sum'];
            $dataArray['real_amount'] = $goodsResult['final_sum'];
            $dataArray['real_freight'] = $goodsResult['deliveryPrice'];
            $dataArray['insured'] = $goodsResult['insuredPrice'];
            $dataArray['pay_fee'] = $goodsResult['paymentPrice'];
            $dataArray['taxes'] = $goodsResult['taxPrice'];
            $dataArray['promotions'] = $goodsResult['proReduce'] + $goodsResult['reduce'];
            $dataArray['order_amount'] = $goodsResult['orderAmountPrice'];
            $dataArray['exp'] = $goodsResult['exp'];
            $dataArray['point'] = $goodsResult['point'];

            //dyg_jzw 20151010 修改订单后更新佣金 如果手动改变折扣 返佣为0
            $dataArray['commission'] = $goodsResult['order_commission'];

            //dyg_jzw 20161215 高返模式自身的返利
            $dataArray['my_commission'] = $goodsResult['order_my_commission'];


            //商家ID
            $dataArray['seller_id'] = $seller_id;

            //生成订单
            $orderDB = new IModel('order');

            //修改操作
            if ($order_id) {
                //获取订单信息
                $orderRow = $orderDB->getObj('id = ' . $order_id);

                //修改订单不能加入其他商家产品
                if (count($orderData) != 1 || $orderRow['seller_id'] != $seller_id) {
                    IError::show(403, "此订单中不能混入其他商家的商品");
                    exit;
                }

                $orderDB->setData($dataArray);
                $orderDB->update('id = ' . $order_id);

                //记录日志信息
                $logObj = new log('db');
                $logObj->write('operation', array("管理员:" . $this->admin['admin_name'], "修改了订单信息", '订单号：' . $orderRow['order_no']));

                //dyg_jzw 20150814 添加订单日志
                //生成订单日志
                $tb_order_log = new IModel('order_log');
                $action = '修改';
                $note = "管理员:" . $this->admin['admin_name'] . '修改了订单【' . $orderRow['order_no'] . '】';

                $tb_order_log->setData(array(
                    'order_id' => $order_id,
                    'user' => $this->admin['admin_name'],
                    'action' => $action,
                    'result' => '成功',
                    'note' => $note,
                    'addtime' => ITime::getDateTime(),
                ));
                $tb_order_log->add();

            } //添加操作
            else {
                $dataArray['create_time'] = ITime::getDateTime();
                $dataArray['order_no'] = Order_Class::createOrderNum();

                $orderDB->setData($dataArray);
                $order_id = $orderDB->add();

                //记录日志信息
                $logObj = new log('db');
                $logObj->write('operation', array("管理员:" . $this->admin['admin_name'], "添加了订单信息", '订单号：' . $dataArray['order_no']));
            }

            //同步order_goods表
            $orderInstance = new Order_Class();
            $orderInstance->insertOrderGoods($order_id, $goodsResult['goodsResult']);
        }

        $this->redirect('order_list');
    }

    /**
     * @brief 修改订单
     */
    public function order_edit()
    {
        $data = array();

        //获得order_id的值
        $order_id = IFilter::act(IReq::get('id'), 'int');
        if ($order_id) {
            $orderDB = new IModel('order');
            $data = $orderDB->getObj('id = ' . $order_id);
            if (Order_class::getOrderStatus($data) >= 3) {
                IError::show(403, "当前订单状态不允许修改");
            }

            $this->orderRow = $data;

            //存在自提点
            if ($data['takeself']) {
                $takeselfObj = new IModel('takeself');
                $takeselfRow = $takeselfObj->getObj('id = ' . $data['takeself']);
                $dataArea = area::name($takeselfRow['province'], $takeselfRow['city'], $takeselfRow['area']);
                $takeselfRow['province_str'] = $dataArea[$takeselfRow['province']];
                $takeselfRow['city_str'] = $dataArea[$takeselfRow['city']];
                $takeselfRow['area_str'] = $dataArea[$takeselfRow['area']];
                $this->takeself = $takeselfRow;
            }

            //获取订单中的商品信息
            $orderGoodsDB = new IQuery('order_goods as og');
            $orderGoodsDB->join = "left join goods as go on og.goods_id = go.id left join products as p on p.id = og.product_id";
            $orderGoodsDB->fields = "go.id,go.name,p.spec_array,p.id as product_id,og.real_price,og.goods_nums,go.goods_no,p.products_no";
            $orderGoodsDB->where = "og.order_id = " . $order_id;
            $this->orderGoods = $orderGoodsDB->find();

            //获取用户名
            if ($data['user_id']) {
                $userDB = new IModel('user');
                $userRow = $userDB->getObj("id = " . $data['user_id']);
                $this->username = isset($userRow['username']) ? $userRow['username'] : '';
            }
        }
        $this->redirect('order_edit');
    }

    /**
     * @brief 订单列表
     */
    public function order_list()
    {
        //搜索条件
        $search = IReq::get('search');
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'), 'int') : 1;
        //条件筛选处理
        list($join, $where) = order_class::getSearchCondition($search);
        //拼接sql
        $orderHandle = new IQuery('order as o');
        $orderHandle->order = "o.id desc";
        $orderHandle->fields = "o.*,d.name as distribute_name,p.name as payment_name";
        $orderHandle->page = $page;
        $orderHandle->where = $where;
        $orderHandle->join = $join;

        $this->search = $search;
        $this->orderHandle = $orderHandle;

        $this->redirect("order_list");
    }

    /**
     * @brief 订单删除功能_删除到回收站
     */
    public function order_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');

        //生成order对象
        $tb_order = new IModel('order');
        $tb_order->setData(array('if_del' => 1));
        if ($id) {
            $id = $tb_order->update(Util::joinStr($id));

            //获取订单编号
            $orderRs = $tb_order->query('id in (' . $id . ')', 'order_no');
            $orderData = array();
            foreach ($orderRs as $val) {
                $orderData[] = $val['order_no'];
            }

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "订单移除到回收站内", '订单号：' . join(',', $orderData)));

        }
        $this->redirect('order_list');
    }

    /**
     * @brief 收款单删除功能_删除到回收站
     */
    public function collection_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('collection_doc');
        $tb_order->setData(array('if_del' => 1));
        if ($id) {
            $tb_order->update(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "收款单移除到回收站内", '收款单ID：' . join(',', $id)));

            $this->redirect('order_collection_list');
        } else {
            $this->redirect('order_collection_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 收款单删除功能_删除回收站中的数据，彻底删除
     */
    public function collection_recycle_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('collection_doc');
        if ($id) {
            $tb_order->del(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "删除回收站内的收款单", '收款单ID：' . join(',', $id)));

            $this->redirect('collection_recycle_list');
        } else {
            $this->redirect('collection_recycle_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 还原还款单列表
     */
    public function collection_recycle_restore()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('collection_doc');
        $tb_order->setData(array('if_del' => 0));
        if ($id) {
            $tb_order->update(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "恢复了回收站内的收款单", '收款单ID：' . join(',', $id)));

            $this->redirect('collection_recycle_list');
        } else {
            $this->redirect('collection_recycle_list', false);
            Util::showMessage('请选择要还原的数据');
        }
    }

    /**
     * @brief 退款单删除功能_删除到回收站
     */
    public function refundment_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('refundment_doc');
        $tb_order->setData(array('if_del' => 1));
        if (!empty($id)) {
            $tb_order->update(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "退款单移除到回收站内", '退款单ID：' . join(',', $id)));

            $this->redirect('order_refundment_list');
        } else {
            $this->redirect('order_refundment_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 退款单删除功能_删除回收站中的数据，彻底删除
     */
    public function refundment_recycle_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('refundment_doc');
        if (!empty($id)) {
            $tb_order->del(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "删除了回收站内的退款单", '退款单ID：' . join(',', $id)));

            $this->redirect('refundment_recycle_list');
        } else {
            $this->redirect('refundment_recycle_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 还原还款单列表
     */
    public function refundment_recycle_restore()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('refundment_doc');
        $tb_order->setData(array('if_del' => 0));
        if (!empty($id)) {
            $tb_order->update(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "还原了回收站内的还款单", '还款单ID：' . join(',', $id)));

            $this->redirect('refundment_recycle_list');
        } else {
            $this->redirect('refundment_recycle_list', false);
            Util::showMessage('请选择要还原的数据');
        }
    }

    /**
     * @brief 发货单删除功能_删除到回收站
     */
    public function delivery_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('delivery_doc');
        $tb_order->setData(array('if_del' => 1));
        if (!empty($id)) {
            $tb_order->update(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "发货单移除到回收站内", '发货单ID：' . join(',', $id)));

            $this->redirect('order_delivery_list');
        } else {
            $this->redirect('order_delivery_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 发货单删除功能_删除回收站中的数据，彻底删除
     */
    public function delivery_recycle_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('delivery_doc');
        if (!empty($id)) {
            $tb_order->del(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "删除了回收站中的发货单", '发货单ID：' . join(',', $id)));

            $this->redirect('delivery_recycle_list');
        } else {
            $this->redirect('delivery_recycle_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 还原发货单列表
     */
    public function delivery_recycle_restore()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('delivery_doc');
        $tb_order->setData(array('if_del' => 0));
        if (!empty($id)) {
            $tb_order->update(Util::joinStr($id));

            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "还原了回收站中的发货单", '发货单ID：' . join(',', $id)));

            $this->redirect('delivery_recycle_list');
        } else {
            $this->redirect('delivery_recycle_list', false);
            Util::showMessage('请选择要还原的数据');
        }
    }

    /**
     * @brief 订单删除功能_删除回收站中的数据，彻底删除
     */
    public function order_recycle_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');

        //生成order对象
        $tb_order = new IModel('order');

        if ($id) {
            $id = is_array($id) ? join(',', $id) : $id;

            Order_class::resetOrderProp($id);

            //删除订单
            $tb_order->del('id in (' . $id . ')');

            //记录日志
            $logObj = new log('db');
            $logObj->write('operation', array("管理员:" . ISafe::get('admin_name'), "删除回收站中退货单", '退货单ID：' . $id));

            $this->redirect('order_recycle_list');
        } else {
            $this->redirect('order_recycle_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 还原订单列表
     */
    public function order_recycle_restore()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //生成order对象
        $tb_order = new IModel('order');
        $tb_order->setData(array('if_del' => 0));
        if (!empty($id)) {
            $tb_order->update(Util::joinStr($id));
            $this->redirect('order_recycle_list');
        } else {
            $this->redirect('order_recycle_list', false);
            Util::showMessage('请选择要还原的数据');
        }
    }

    /**
     * @brief 订单打印模板修改
     */
    public function print_template()
    {
        //获取根目录路径
        $path = $this->getViewPath() . $this->getId();
        //获取 购物清单模板
        $ifile_shop = new IFile($path . '/shop_template.html');
        $arr['ifile_shop'] = $ifile_shop->read();
        //获取 配货单模板
        $ifile_pick = new IFile($path . "/pick_template.html");
        $arr['ifile_pick'] = $ifile_pick->read();

        $this->setRenderData($arr);
        $this->redirect('print_template');
    }

    /**
     * @brief 订单打印模板修改保存
     */
    public function print_template_update()
    {
        // 获取POST数据
        $con_shop = IReq::get("con_shop");
        $con_pick = IReq::get("con_pick");

        //获取根目录路径
        $path = $this->getViewPath() . $this->getId();
        //保存 购物清单模板
        $ifile_shop = new IFile($path . '/shop_template.html', 'w');
        if (!($ifile_shop->write($con_shop))) {
            $this->redirect('print_template', false);
            Util::showMessage('保存购物清单模板失败！');
        }
        //保存 配货单模板
        $ifile_pick = new IFile($path . "/pick_template.html", 'w');
        if (!($ifile_pick->write($con_pick))) {
            $this->redirect('print_template', false);
            Util::showMessage('保存配货单模板失败！');
        }
        //保存 合并单模板
        $ifile_merge = new IFile($path . "/merge_template.html", 'w');
        if (!($ifile_merge->write($con_shop . $con_pick))) {
            $this->redirect('print_template', false);
            Util::showMessage('购物清单和配货单模板合并失败！');
        }

        $this->setRenderData(array('where' => ''));
        $this->redirect('order_list');
    }

    //购物单
    public function shop_template()
    {
        $this->layout = 'print';
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $seller_id = IFilter::act(IReq::get('seller_id'), 'int');

        $tb_order = new IModel('order');
        $where = $seller_id ? 'id=' . $order_id . ' and seller_id = ' . $seller_id : 'id=' . $order_id;
        $data = $tb_order->getObj($where);
        if (!$data) {
            IError::show(403, "您没有权限查阅该订单");
        }

        if ($data['seller_id']) {
            $sellerObj = new IModel('seller');
            $config_info = $sellerObj->getObj('id = ' . $data['seller_id']);

            $data['set']['name'] = isset($config_info['true_name']) ? $config_info['true_name'] : '';
            $data['set']['phone'] = isset($config_info['phone']) ? $config_info['phone'] : '';
            $data['set']['email'] = isset($config_info['email']) ? $config_info['email'] : '';
            $data['set']['url'] = isset($config_info['home_url']) ? $config_info['home_url'] : '';
        } else {
            $config = new Config("site_config");
            $config_info = $config->getInfo();

            $data['set']['name'] = isset($config_info['name']) ? $config_info['name'] : '';
            $data['set']['phone'] = isset($config_info['phone']) ? $config_info['phone'] : '';
            $data['set']['email'] = isset($config_info['email']) ? $config_info['email'] : '';
            $data['set']['url'] = isset($config_info['url']) ? $config_info['url'] : '';
        }

        $data['address'] = join('&nbsp;', area::name($data['province'], $data['city'], $data['area'])) . "&nbsp;" . $data['address'];
        $this->setRenderData($data);
        $this->redirect("shop_template");
    }

    //发货单
    public function pick_template()
    {
        $this->layout = 'print';
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $seller_id = IFilter::act(IReq::get('seller_id'), 'int');

        $tb_order = new IModel('order');
        $where = $seller_id ? 'id=' . $order_id . ' and seller_id = ' . $seller_id : 'id=' . $order_id;
        $data = $tb_order->getObj($where);
        if (!$data) {
            IError::show(403, "您没有权限查阅该订单");
        }
        //获取地区
        $data['address'] = join('&nbsp;', area::name($data['province'], $data['city'], $data['area'])) . "&nbsp;" . $data['address'];

        $this->setRenderData($data);
        $this->redirect('pick_template');
    }

    //合并购物单和发货单
    public function merge_template()
    {
        $this->layout = 'print';
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $seller_id = IFilter::act(IReq::get('seller_id'), 'int');

        $tb_order = new IModel('order');
        $where = $seller_id ? 'id=' . $order_id . ' and seller_id = ' . $seller_id : 'id=' . $order_id;
        $data = $tb_order->getObj($where);
        if (!$data) {
            IError::show(403, "您没有权限查阅该订单");
        }
        if ($data['seller_id']) {
            $sellerObj = new IModel('seller');
            $config_info = $sellerObj->getObj('id = ' . $data['seller_id']);

            $data['set']['name'] = isset($config_info['true_name']) ? $config_info['true_name'] : '';
            $data['set']['phone'] = isset($config_info['phone']) ? $config_info['phone'] : '';
            $data['set']['email'] = isset($config_info['email']) ? $config_info['email'] : '';
            $data['set']['url'] = isset($config_info['home_url']) ? $config_info['home_url'] : '';
        } else {
            $config = new Config("site_config");
            $config_info = $config->getInfo();

            $data['set']['name'] = isset($config_info['name']) ? $config_info['name'] : '';
            $data['set']['phone'] = isset($config_info['phone']) ? $config_info['phone'] : '';
            $data['set']['email'] = isset($config_info['email']) ? $config_info['email'] : '';
            $data['set']['url'] = isset($config_info['url']) ? $config_info['url'] : '';
        }

        //获取地区
        $data['address'] = join('&nbsp;', area::name($data['province'], $data['city'], $data['area'])) . "&nbsp;" . $data['address'];

        $this->setRenderData($data);
        $this->redirect("merge_template");
    }

    /**
     * @brief 添加/修改发货信息
     */
    public function ship_info_edit()
    {
        // 获取POST数据
        $id = IFilter::act(IReq::get("sid"), 'int');
        if ($id) {
            $tb_ship = new IModel("merch_ship_info");
            $ship_info = $tb_ship->getObj("id=" . $id . " and seller_id = 0");
            if ($ship_info) {
                $this->data = $ship_info;
            } else {
                die('数据不存在');
            }
        }
        $this->setRenderData($this->data);
        $this->redirect('ship_info_edit');
    }

    /**
     * @brief 设置发货信息的默认值
     */
    public function ship_info_default()
    {
        $id = IFilter::act(IReq::get('id'), 'int');
        $default = IFilter::string(IReq::get('default'));
        $tb_merch_ship_info = new IModel('merch_ship_info');
        if ($default == 1) {
            $tb_merch_ship_info->setData(array('is_default' => 0));
            $tb_merch_ship_info->update("seller_id = 0");
        }
        $tb_merch_ship_info->setData(array('is_default' => $default));
        $tb_merch_ship_info->update("id = " . $id . " and seller_id = 0");
        $this->redirect('ship_info_list');
    }

    /**
     * @brief 保存添加/修改发货信息
     */
    public function ship_info_update()
    {
        // 获取POST数据
        $id = IFilter::act(IReq::get('sid'), 'int');
        $ship_name = IFilter::act(IReq::get('ship_name'));
        $ship_user_name = IFilter::act(IReq::get('ship_user_name'));
        $sex = IFilter::act(IReq::get('sex'), 'int');
        $province = IFilter::act(IReq::get('province'), 'int');
        $city = IFilter::act(IReq::get('city'), 'int');
        $area = IFilter::act(IReq::get('area'), 'int');
        $address = IFilter::act(IReq::get('address'));
        $postcode = IFilter::act(IReq::get('postcode'), 'int');
        $mobile = IFilter::act(IReq::get('mobile'));
        $telphone = IFilter::act(IReq::get('telphone'));
        $is_default = IFilter::act(IReq::get('is_default'), 'int');

        $tb_merch_ship_info = new IModel('merch_ship_info');

        //判断是否已经有了一个默认地址
        if (isset($is_default) && $is_default == 1) {
            $tb_merch_ship_info->setData(array('is_default' => 0));
            $tb_merch_ship_info->update('seller_id = 0');
        }
        //设置存储数据
        $arr['ship_name'] = $ship_name;
        $arr['ship_user_name'] = $ship_user_name;
        $arr['sex'] = $sex;
        $arr['province'] = $province;
        $arr['city'] = $city;
        $arr['area'] = $area;
        $arr['address'] = $address;
        $arr['postcode'] = $postcode;
        $arr['mobile'] = $mobile;
        $arr['telphone'] = $telphone;
        $arr['is_default'] = $is_default;
        $arr['is_del'] = 1;
        $arr['seller_id'] = 0;

        $tb_merch_ship_info->setData($arr);
        //判断是添加还是修改
        if ($id) {
            $tb_merch_ship_info->update('id=' . $id . " and seller_id = 0");
        } else {
            $tb_merch_ship_info->add();
        }
        $this->redirect('ship_info_list');
    }

    /**
     * @brief 删除发货信息到回收站中
     */
    public function ship_info_del()
    {
        // 获取POST数据
        $id = IFilter::act(IReq::get('id'), 'int');

        //加载 商家发货点信息
        $tb_merch_ship_info = new IModel('merch_ship_info');
        $tb_merch_ship_info->setData(array('is_del' => 0));
        if ($id) {
            $tb_merch_ship_info->update(Util::joinStr($id) . " and seller_id = 0");
            $this->redirect('ship_info_list');
        } else {
            $this->redirect('ship_info_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    /**
     * @brief 还原回收站的信息到列表
     */
    public function recycle_restore()
    {
        // 获取POST数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //加载 商家发货点信息
        $tb_merch_ship_info = new IModel('merch_ship_info');
        $tb_merch_ship_info->setData(array('is_del' => 1));
        if ($id) {
            $tb_merch_ship_info->update(Util::joinStr($id) . " and seller_id = 0");
            $this->redirect('ship_recycle_list');
        } else {
            $this->redirect('ship_recycle_list', false);
        }
    }

    /**
     * @brief 删除收货地址的信息
     */
    public function recycle_del()
    {
        // 获取POST数据
        $id = IFilter::act(IReq::get('id'), 'int');
        //加载 商家发货点信息
        $tb_merch_ship_info = new IModel('merch_ship_info');
        if ($id) {
            $tb_merch_ship_info->del(Util::joinStr($id) . ' and seller_id = 0');
            $this->redirect('ship_recycle_list');
        } else {
            $this->redirect('ship_recycle_list', false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    //快递单背景图片上传
    public function expresswaybill_upload()
    {
        $result = array(
            'isError' => true,
        );

        if (isset($_FILES['attach']['name']) && $_FILES['attach']['name'] != '') {
            $photoObj = new PhotoUpload();
            $photo = $photoObj->run();

            $result['isError'] = false;
            $result['data'] = $photo['attach']['img'];
        } else {
            $result['message'] = '请选择图片';
        }

        echo '<script type="text/javascript">parent.photoUpload_callback(' . JSON::encode($result) . ');</script>';
    }

    //快递单添加修改
    public function expresswaybill_edit()
    {
        $id = intval(IReq::get('id'));

        $this->expressRow = array();

        //修改模式
        if ($id) {
            $expressObj = new IModel('expresswaybill');
            $this->expressRow = $expressObj->getObj('id = ' . $id);
        }

        $this->redirect('expresswaybill_edit');
    }

    //快递单添加修改动作
    public function expresswaybill_edit_act()
    {
        $id = intval(IReq::get('id'));
        $printExpress = IReq::get('printExpress');
        $name = IFilter::act(IReq::get('express_name'));
        $width = intval(IReq::get('width'));
        $height = intval(IReq::get('height'));
        $background = IFilter::act(IReq::get('printBackground'));
        $background = ltrim($background, IUrl::creatUrl(''));

        if (!$printExpress) {
            $printExpress = array();
        }

        if (!$name) {
            die('快递单的名称不能为空');
        }

        $expressObj = new IModel('expresswaybill');

        $data = array(
            'config' => serialize($printExpress),
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'background' => $background,
        );

        $expressObj->setData($data);

        //修改模式
        if ($id) {
            $is_result = $expressObj->update('id = ' . $id);
        } else {
            $is_result = $expressObj->add();
        }
        echo $is_result === false ? '操作失败' : 'success';
    }

    //快递单删除
    public function expresswaybill_del()
    {
        $id = intval(IReq::get('id'));
        $expressObj = new IModel('expresswaybill');
        $expressObj->del('id = ' . $id);
        $this->redirect('print_template/tab_index/3');
    }

    //选择快递单打印类型
    public function expresswaybill_template()
    {
        $this->layout = 'print';
        $seller_id = IFilter::act(IReq::get('seller_id'), 'int');
        $data = array();

        //获得order_id的值
        $order_id = IFilter::act(IReq::get('id'), 'int');
        $order_id = is_array($order_id) ? join(',', $order_id) : $order_id;

        if (!$order_id) {
            $this->redirect('order_list');
            return;
        }

        $ord_class = new Order_Class();
        $orderInfo = $ord_class->getOrderInfo($order_id, $seller_id);

        //dyg_jzw 20150827 增加配送方式显示
        foreach ($orderInfo as $_key => $_row) {
            //获取配送方式
            $tb_delivery = new IModel('delivery');
            $delivery_info = $tb_delivery->getObj('id=' . $_row['distribution']);
            if ($delivery_info) {
                $orderInfo[$_key]['delivery_name'] = $delivery_info['name'];
            }
        }

        $this->orderInfo = $orderInfo;

        $this->redirect('expresswaybill_template');
    }

    //打印快递单
    public function expresswaybill_print()
    {
        $config_conver = array();
        $this->layout = 'print';

        $order_id = IFilter::act(IReq::get('order_id'));
        $seller_id = IFilter::act(IReq::get('seller_id'), 'int');
        $express_id = intval(IReq::get('express_id'));
        $expressObj = new IModel('expresswaybill');
        $expressRow = $expressObj->getObj('id = ' . $express_id);

        if (empty($expressRow)) {
            die('不存在此快递单信息');
        }

        $expressConfig = unserialize($expressRow['config']);
        $expresswaybillObj = new Expresswaybill();

        $config_conver = $expresswaybillObj->conver($expressConfig, $order_id, $seller_id);
        $this->config_conver = str_replace('trackingLeft', 'letterSpacing', $config_conver);
        if (!$this->config_conver) {
            die('快递单模板配置不正确');
        }
        $this->order_id = $order_id;
        $this->expressRow = $expressRow;
        $this->redirect('expresswaybill_print');
    }

    //订单导出excel 参考订单列表
    public function order_report()
    {
        //搜索条件
        $search = IReq::get('search');
        //条件筛选处理
        list($join, $where) = order_class::getSearchCondition($search);
        //拼接sql
        $orderHandle = new IQuery('order as o');
        $orderHandle->order = "o.id desc";
        $orderHandle->fields = "o.*,d.name as distribute_name,p.name as payment_name";
        $orderHandle->join = $join;
        $orderHandle->where = $where;
        $orderList = $orderHandle->find();

        $reportObj = new report('order');
        $reportObj->setTitle(array("订单编号", "日期", "收货人", "收货地址", "电话", "订单金额", "支付方式", "支付状态", "发货状态", "商品信息"));

        foreach ($orderList as $k => $val) {
            $orderGoods = Order_class::getOrderGoods($val['id']);
            $strGoods = "";
            foreach ($orderGoods as $good) {
                $strGoods .= "商品编号：" . $good['goodsno'] . " 商品名称：" . $good['name'] . " 商品数量：" . $good['goods_nums'];
                if (isset($good['value']) && $good['value']) {
                    $strGoods .= " 规格：" . $good['value'];
                }
                $strGoods .= "<br />";
            }
            $insertData = array(
                "'" . $val['order_no'],
                $val['create_time'],
                $val['accept_name'],
                join('&nbsp;', area::name($val['province'], $val['city'], $val['area'])) . $val['address'],
                $val['telphone'] . '&nbsp;' . $val['mobile'],
                $val['order_amount'],
                $val['payment_name'],
                Order_Class::getOrderPayStatusText($val),
                Order_Class::getOrderDistributionStatusText($val),
                $strGoods,
            );
            $reportObj->setData($insertData);
        }
        $reportObj->toDownload();
    }
}