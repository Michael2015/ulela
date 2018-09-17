<?php

class Cbe extends XMLWriter
{


    //--------------------------易极付的共有变量----------------------------------
    //public $yi_request_url = "http://openapi.yijifu.net/gateway.html";//易极付请求url
    public $yi_request_url = "https://api.yiji.com";//易极付正式接口请求url
    public $yi_partner_id = '20160219020010430873'; //易极付商户ID (实名测试)
    public $yi_partner_id_2 = '20160222020010454259'; //国内商户id(退款用)
    public $yi_session_key = 'eb40c27374c8b20d3eecb2f200b1df6e'; //易极付款商户密钥 (实名测试)
    public $yi_session_key_2 = '27a1f4eafc85208c1cba12b6959383a3'; //易极付款商户密钥 (退款用)
    public $yi_payment_notify_url = '';//易极付支付单回调URL

    //--------------------------e码头的公有变量-----------------------------------
    public $e_shop_id = '77';
    public $e_key_id = 'dygapi';              //e码头的密钥id
    public $e_key = '7CLLMB3M50POT1R0TVG9GNRI057TYWPL';   //e码头的密钥
    public $e_url = 'https://wms.ds-bay.com';   //e码头网址 末尾不带'/'
    public $e_payment_company_id = '5801646760'; //支付企业海关登记号，由支付企业提供

    //--------------------------海关对接的公有变量-----------------------------------
    public $h_zd_code = '076927558'; //9位组织代码
    public $h_sender_id = 'BBM076927558';
    public $h_cop_code = '440313T001'; //企业海关注册代码
    public $h_new_sender_id = 'DXPENT0000013198'; //统一版海关企业客户端ID号 
    public $h_com_entname = '前海东阳光（深圳）电子商务有限公司'; //电商企业在海关备案时填写的名称
    public $h_com_entcode = '440313T001'; //电商企业在海关的备案编号或代码
    public $h_eshop_entname = '前海东阳光（深圳）电子商务有限公司'; //电商平台在海关备案时填写的名称
    public $h_eshop_entcode = '0769275580'; //电商平台在海关的备案编号或代码
    public $h_app_uid = '8910000488902'; //电子口岸持卡人IC卡或IKEY编号或电子口岸传输用户标识
    public $h_app_uname = '李少军'; //电子口岸持卡人姓名或电子口岸传输用户名称


    /**
    * @brief 构造函数
    * @param $payment_id 支付方式ID
    */
    public function __construct($is_xml = false)
    {
        //易极付支付单回调URL
        $this->yi_payment_notify_url = IUrl::getHost().IUrl::creatUrl("/cbe_order_api/yi_payment_callback"); 
        
        if ($is_xml)
        {
            $this->openMemory();
            $this->setIndent(true);
            $this->setIndentString(' ');
            $this->startDocument('1.0', 'UTF-8');

            /*if($prm_xsltFilePath){
                $this->writePi('xml-stylesheet', 'type="text/xsl" href="'.$prm_xsltFilePath.'"');
            }*/
        }
    }

    /**
     * @brief 根据身份证号查询今年累计消费
     * @param string $certNo 身份证号
     * @param float
     */
    public function check_cbe_count($certNo, $amount)
    {
        $countObj    = new IModel('cbe_count');

        //是否存在该身份证号
        $countRow = $countObj->getObj("cert_no = '".strtolower($certNo)."'");

        if ($countRow)
        {
            //存在记录，是否跨年
            if (date("Y", strtotime($countRow['update_time'])) != date("Y", strtotime(ITime::getDateTime())))
            {
                //跨年记录更新为0
                $countArray = array('amount' => 0);
                $countObj->setData($countArray);
                $countObj->update('id = '.$countRow['id']);
            }
            else //同一年内，是否超过2万元
            {
                if ($countRow['amount'] + $amount > 20000)
                {
                    //超过2万元, 返回失败状态
                    return false;
                }
                else
                {
                    //更新累计金额
                    $countArray = array('amount' => $countRow['amount'] + $amount);
                    $countObj->setData($countArray);
                    $countObj->update('id = '.$countRow['id']);
                }
            }
        }
        else
        {
            //不存在则新建
            $countArray = array(
                            'cert_no' => $certNo,
                            'amount' => $amount,
                            'update_time' => ITime::getDateTime()
                        );
            $countObj->setData($countArray);
            $countObj->add();
        }

        return true;
    }

    /**brief 易极付实名查询
     * @param http://apidoc.yiji.com/website/api_detail.html?sericeNo=realNameQuery_1.0&id=402880fe51e73a0c0152635b49ab0219&sericeName=%E6%98%93%E6%B1%87%E9%80%9A%E5%AE%9E%E5%90%8D%E6%9F%A5%E8%AF%A2&schemeName=%E8%B7%A8%E5%A2%83%E6%94%AF%E4%BB%98#realNameQuery_1.0
     */
    public function yi_realname_query($orderNo, $realName, $certNo)
    {
        $postData['service'] = "realNameQuery";
        $postData['partnerId'] = $this->yi_partner_id;
        $postData['orderNo'] = str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $orderNo;
        $postData['signType'] = 'MD5';
        $postData['realName'] = $realName;
        $postData['certNo'] = $certNo;
        $postData['version'] = '1.0';

        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($postData);

        //对待签名参数数组排序
        $postData = $this->argSort($para_filter);

        //生成签名结果
        $postData['sign'] = $this->buildMysign($postData, $this->yi_session_key);

        //发送请求
        $result = $this->sendPost($this->yi_request_url, $postData);

        if ($result != null || $result != "") 
        {
            return json_decode($result, true);
        } 
        else 
        {
            return array('success'=> false);
        } 
    }

    //易极付退款接口
    public function yi_refund($order_no)
    {
        $orderObj = new IModel('order');
        $orderRow = $orderObj->getObj("order_no = '{$order_no}'");

        //商品税率合计
        /*$taxes = 0; //税费
        foreach ($orderGoodsList as $_goods) 
        {
            $taxes += $_goods['goods_nums'] * $_goods['real_price'] * $_goods['cbe_taxes'] / 100;
        }*/

        $postData['service'] = "tradeRefund";
        $postData['partnerId'] = $this->yi_partner_id_2;
        $postData['orderNo'] = $orderRow['order_no'].'_'.rand(0,999999);
        //$postData['notifyUrl'] = $this->yi_payment_notify_url; //支付单异步通知URL
        $postData['signType'] = 'MD5';
        $postData['version'] = '1.0';
        
        $postData['tradeNo'] = $orderRow['trade_no'];
        $postData['outOrderNo'] = str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $orderRow['order_no'];
        
        $postData['refundType'] = 'ALL_REFUND';
        $postData['refundTime'] = date("Y-m-d");
        $postData['refundAmount'] = $orderRow['order_amount'];
        $postData['refundReason'] = '取消交易';
        
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($postData);

        //对待签名参数数组排序
        $postData = $this->argSort($para_filter);

        //生成签名结果
        $postData['sign'] = $this->buildMysign($postData, $this->yi_session_key_2);

        //发送请求
        $result = $this->sendPost($this->yi_request_url, $postData);

        if ($result != null || $result != "") 
        {
            return json_decode($result, true);
        } 
        else 
        {
            return array('success'=> false, 'mes' => $result);
        }
    }

    /**brief 易极付上传支付单
     * @param http://apidoc.yiji.com/website/api_detail.html?sericeNo=yhtMultiPaymentBillUpload_1.0&id=402880fe51e73a0c0152635b49ab0219&sericeName=%E8%B7%A8%E5%A2%83%E6%94%AF%E4%BB%98%E5%8D%95%E4%B8%8A%E4%BC%A0%E6%9C%8D%E5%8A%A1&schemeName=%E8%B7%A8%E5%A2%83%E6%94%AF%E4%BB%98#yhtMultiPaymentBillUpload_1.0
     */
    public function yi_upload_payment($orderRow)
    {
        $orderGoodsObj = new IModel('order_goods as og, goods as g');
        $orderGoodsList = $orderGoodsObj->query('og.order_id='.$orderRow['id'].' and og.goods_id = g.id', 'og.*, g.cbe_taxes');

        //商品税率合计
        /*$taxes = 0; //税费
        foreach ($orderGoodsList as $_goods) 
        {
            $taxes += $_goods['goods_nums'] * $_goods['real_price'] * $_goods['cbe_taxes'] / 100;
        }*/

        $postData['service'] = "singlePaymentUpload";
        $postData['partnerId'] = $this->yi_partner_id;
        $postData['orderNo'] = $orderRow['order_no'].'_'.rand(0,999999);
        $postData['notifyUrl'] = $this->yi_payment_notify_url; //支付单异步通知URL
        $postData['signType'] = 'MD5';
        $postData['version'] = '1.0';

        $postData['orderFlowType']  = 'NORMAL'; //A：特殊类型 B：一般类型
        $postData['eshopEntName']   = $this->h_com_entname; //商户在海关备案时填写的名称
        $postData['eshopEntCode']   = $this->h_com_entcode; //商户在海关的备案编号或代码
        
        $postData['customsCode'] = 'SZ_5300';
        $postData['outOrderNo'] = str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $orderRow['order_no'];
        $postData['tradeNo'] = '["'.$orderRow['trade_no'].'"]';
        $postData['payerDocType'] = 'Identity_Card';
        $postData['payerId'] = $orderRow['accept_id'];
        $postData['payerName'] = $orderRow['accept_name'];
        $postData['goodsCurrency'] = 'CNY';
        $postData['goodsAmount'] = $orderRow['order_amount'];
        $postData['taxCurrency'] = 'CNY';
        $postData['taxAmount'] = '0';
        $postData['freightCurrency'] = 'CNY';
        $postData['freightAmount'] = '0';
        $postData['appStatus'] = 'DECLARE';
        
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($postData);

        //对待签名参数数组排序
        $postData = $this->argSort($para_filter);

        //生成签名结果
        $postData['sign'] = $this->buildMysign($postData, $this->yi_session_key);

        //发送请求
        $result = $this->sendPost($this->yi_request_url, $postData);

        if ($result != null || $result != "") 
        {
            return json_decode($result, true);
        } 
        else 
        {
            return array('success'=> false, 'mes' => $result);
        }

    }

    /**brief 易极付上传支付单回调
     * @param http://apidoc.yiji.com/website/api_detail.html?sericeNo=yhtMultiPaymentBillUpload_1.0&id=402880fe51e73a0c0152635b49ab0219&sericeName=%E8%B7%A8%E5%A2%83%E6%94%AF%E4%BB%98%E5%8D%95%E4%B8%8A%E4%BC%A0%E6%9C%8D%E5%8A%A1&schemeName=%E8%B7%A8%E5%A2%83%E6%94%AF%E4%BB%98#yhtMultiPaymentBillUpload_1.0
     */
    public function yi_upload_payment_callback($callbackData)
    {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($callbackData);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildMysign($para_sort, $this->yi_session_key);

        if($callbackData['sign'] == $mysign)
        {
            if ($callbackData['success'] == 'true')   
            {
                if (strtoupper($callbackData['status']) == 'SUCCESS') 
                {
                    //订单更新状态为支付单已上传
                    $orderDB  = new IModel('order');
                    $orderDB->setData(array('is_cbe' => 2));
                    if($orderDB->update("is_cbe = 1 AND order_no = '".substr($callbackData['outOrderNo'], 3)."'"))
                    {
                        return true;
                    }
                    else
                    {
                        $callbackData['cbe_error'] = 'update error. It may be updated.';
                        return true;
                    }                    
                }
            }
        }
        else
        {
            $callbackData['cbe_error'] = 'sign fail. It should be '.$mysign;
        }

        return $callbackData;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    private function paraFilter($para)
    {
        $para_filter = array();
        foreach($para as $key => $val)
        {
            if($key == "sign" || $val == "")
            {
                continue;
            }
            else
            {
                $para_filter[$key] = $para[$key];
            }
        }
        return $para_filter;
    }

    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    private function argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }

    /**
     * 生成签名结果
     * @param $sort_para 要签名的数组
     * @param $key 支付宝交易安全校验码
     * @param $sign_type 签名类型 默认值：MD5
     * return 签名结果字符串
     */
    private function buildMysign($sort_para, $key, $sign_type = "MD5")
    {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($sort_para);

        //把拼接后的字符串再与安全校验码直接连接起来
        $prestr = $prestr.$key;

        //把最终的字符串签名，获得签名结果
        $mysgin = md5($prestr);
        return $mysgin;
    }

    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    private function createLinkstring($para)
    {
        $arg  = "";
        foreach($para as $key => $val)
        {
            $arg.=$key."=".$val."&";
        }

        //去掉最后一个&字符
        $arg = trim($arg,'&');

        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc())
        {
            $arg = stripslashes($arg);
        }

        return $arg;
    }




    /**
     * @param $contents   订单内容参数，是一个数组
     * -----------------该数组字段没有写不为空，都是可为空的---------------------
     * $contents = array(
            'shopId' => '商户编号',不为空
            'externalNo' => '第三方订单ID。必须与上传海关数据平台的订单号一致第三方订单号。为避免冲突，前1、2、3位为商户编号',不为空
            'shipToName' => '收件人姓名',不为空
            'shipToId'=> '收件人证件号',不为空
            'shipToPhone' => '收件人电话',不为空
            'billDate' => '下单时间',不为空
            'paymentDateTime' => '付款时间',不为空
            'countryNo' => '国家名称',不为空
            'provinceName' => '省名',不为空
            'cityName' => '城市',不为空
            'regionName' => '区县',
            'shipToAddr' => '详细地址',不为空
            'zipCode' => '邮政编码',
            'expressType' => '快递单类型代码0标准1特快，2经济 3 电商特惠',不为空
            'carrierKey' => '物流公司编号(快递公司标志码 2：EMS，3：顺丰速运，4：中通速，5：圆通速递，6:邮政小包)',不为空
            'overrideLogisticFee' => '支付总金额，应该和发送海关信息中支付总额相同',
            'extraTag' => '将于订单更新回调接口原样返回',
            'declaredPostalTax' => '申报用行邮税（与财务计费无关）',
            'payment' => '支付总金额',不为空
            'remark' => '仅供演示数据',
            'item' =>商品详情
                array(
                    'sku' => '产品SKU',不为空
                    'taxPrice' => '销售单价',不为空
                    'expectedqty' => '数量',不为空
                ),
            'payOrderId' => '支付单号（由支付企业回传）',不为空
            'payEntCode' => '支付企业海关登记号，由支付企业提供',不为空
    );
     * @return string 发送订单给e码头,返回状态回执
     */
    public function e_send_order($orderRow)
    {
        $orderGoodsObj = new IModel('order_goods as og, goods as g');
        $orderGoodsList = $orderGoodsObj->query('og.order_id='.$orderRow['id'].' and og.goods_id = g.id', 'og.*, g.cbe_taxes, g.market_price');

        $goodsObj = new IModel('goods');
        $productObj = new IModel('products');
        
        //商品信息
        $_order_items = array();
        //$taxes = 0; //税费
        foreach ($orderGoodsList as $_goods) 
        {
            $goods_arr = json_decode($_goods['goods_array'], true);

            //是否组合商品 20161121
            if (strpos($goods_arr['goodsno'], "-"))
            {
                //查询组合的市场价
                $_productRow = $productObj->getObj("goods_id ='".$_goods['goods_id']."' and products_no='".$goods_arr['goodsno']."'", "*", "id DESC");

                if ($_productRow) //是否存在多规格的货品
                {
                    $_group_market_price = $_productRow['market_price'];
                }
                else //单一组合商品, 查询goods表
                {
                    $_goodsRow = $goodsObj->getObj("id ='".$_goods['goods_id']."' and goods_no='".$goods_arr['goodsno']."' and is_del = 0");
                    $_group_market_price = $_goodsRow['market_price'];
                }

                $_goods_list = explode("|", $goods_arr['goodsno']);

                foreach ($_goods_list as $_item)
                {
                    $_goods_row = explode("-", $_item);
                    $_child_goods_no = $_goods_row[0];
                    $_child_goods_nums = $_goods_row[1];

                    //查询商品价格和税率
                    $_child_goods = $goodsObj->getObj("goods_no = '{$_child_goods_no}' and is_del <> 1");

                    //添加商品明细
                    $_order_items[] = array(
                                'sku' => trim($_child_goods_no),
                                'taxPrice' => round( $_child_goods['market_price'] / $_group_market_price * $_goods['real_price'] / (1 + $_child_goods['cbe_taxes'] / 100), 2),
                                                    // 实际支付价              /    商品数量
                                'expectedqty' => $_child_goods_nums * $_goods['goods_nums'],
                                'uom' => '个', //实际不调用，固定一个即可
                            ); 
                }
            }
            else
            {
                //海关信息是否存在
                if (empty($_goods['cbe_goodsno']) || empty($_goods['cbe_taxes']))
                {
                    $_tmp_goods = $goodsObj->getObj("goods_no = '{$goods_arr['goodsno']}' and is_del <> 1 and cbe_goodsno is not null");
                    $_goods['cbe_taxes'] = $_tmp_goods['cbe_taxes'];
                }

                $_order_items[] = array(
                                'sku' => trim($goods_arr['goodsno']),
                                'taxPrice' => round($_goods['real_price'] / (1 + $_goods['cbe_taxes'] / 100), 2), //未含税单价
                                'expectedqty' => $_goods['goods_nums'],
                                'uom' => '个', //实际不调用，固定一个即可
                            );
            }
        }

        //是否有相同sku
        $order_items = array();
        foreach ($_order_items as $_item)
        {
            if (! isset($order_items[$_item['sku']]))
            {
                $order_items[$_item['sku']] = array(
                                'sku' => $_item['sku'],
                                'taxPrice' => 0, //未含税单价
                                'expectedqty' => 0,
                                'uom' => '个', //实际不调用，固定一个即可
                            );
            }

            $order_items[$_item['sku']]['taxPrice'] = round(($order_items[$_item['sku']]['taxPrice'] * $order_items[$_item['sku']]['expectedqty'] + $_item['taxPrice'] * $_item['expectedqty']) / ($order_items[$_item['sku']]['expectedqty'] + $_item['expectedqty']), 2);
            $order_items[$_item['sku']]['expectedqty'] += $_item['expectedqty'];
        }

        $order_items2 = array();
        foreach ($order_items as $value)
        {
            $order_items2[] = $value;
        }
        //根据城市判断区县代码是否填写
        $no_area_citys = array('620200','441900','442000', '429021', '429006', '429005', '429004');
        if (in_array($orderRow['city'], $no_area_citys))
        {
            //获取地区名称
            $areaName = area::name($orderRow['area']);
            $orderRow['address'] = $areaName[$orderRow['area']] . $orderRow['address'];
            $orderRow['area'] = '';
        }
        
        $contents = array(
                        'shopId'        => $this->e_shop_id, //商户编号
                        'externalNo'    => str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $orderRow['order_no'], //'第三方订单ID。必须与上传海关数据平台的订单号一致第三方订单号。为避免冲突，前1、2、3位为商户编号',不为空
                        'shipToName'    => $orderRow['accept_name'], //收件人姓名,不为空
                        'shipToId'      => $orderRow['accept_id'], //收件人证件号,不为空
                        'shipToPhone'   => $orderRow['mobile'], //收件人电话,不为空
                        'billDate'      => $orderRow['create_time'], //下单时间,不为空
                        'paymentDateTime' => $orderRow['pay_time'], //付款时间,不为空
                        'countryNo'     => '中国', //国家名称,不为空
                        // 'provinceName'  => $address_str[$orderRow['province']], //省名,不为空
                        // 'cityName'      => $address_str[$orderRow['city']], //城市,不为空
                        // 'regionName'    => '', //区县
                        'provinceCode' => $orderRow['province'], //省级行政区划代码
                        'cityCode' => $orderRow['city'], //省级行政区划代码
                        'regionCode' => $orderRow['area'], //省级行政区划代码
                        'shipToAddr'    => $orderRow['address'], //详细地址,不为空
                        'zipCode'       => $orderRow['postcode'], //邮政编码,
                        'expressType'   => '0', //快递单类型代码0标准1特快，2经济 3 电商特惠,不为空
                        'carrierKey'    => '4', //物流公司编号(快递公司标志码 2：EMS，3：顺丰速运，4：中通速，5：圆通速递，6:邮政小包),不为空
                        'overrideLogisticFee'   => 0, //$orderRow['real_freight'], //申报用物流费（与财务计费无关）
                        'declaredPostalTax'     => '0.00', //round($taxes, 2), //申报用行邮税（与财务计费无关）, 实际中未使用
                        'payment'       => $orderRow['real_amount'], //支付总金额，应该和发送海关信息中支付总额相同,不为空
                        'remark'        => '', //备注
                        'item'          => $order_items2,
                        'payOrderId'    => $orderRow['trade_no'], //支付单号（由支付企业回传）,不为空
                        'payEntCode'    => $this->e_payment_company_id, //支付企业海关登记号，由支付企业提供,不为空
                );
        if ($contents['regionCode'] == '')
        {
            unset($contents['regionCode']);
        }
        
        $this->setElement('orderRequest', $contents);

        // 请求的地址
        $url = $this->e_url.'/transceiver/sendOrderInfo.shtml';

        // 报文内容
        $document = $this->getDocument();

        // POST 请求数据
        $data = array(
            "userid" => strtoupper(md5($this->e_key_id)), // 用户id
            "sign" => strtoupper(md5($this->e_key_id. $this->e_key . $document)), // 用户id + 用户密钥 + 报文内容
            "msg" => $document, // XML payload
        );

        $result = $this->sendPost($url, http_build_query($data));

        return $result;
    }

    //取消e码头订单
    public function e_cancel_order($order_no)
    {
        $contents = array(
                        'shopId'        => $this->e_shop_id, //商户编号
                        'externalNo'    => str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $order_no, //'第三方订单ID。必须与上传海关数据平台的订单号一致第三方订单号。为避免冲突，前1、2、3位为商户编号',不为空
                );

        $this->setElement('orderRequest', $contents);

        // 请求的地址
        $url = $this->e_url.'/transceiver/cancleOrderInfo.shtml';

        // 报文内容
        $document = $this->getDocument();

        // POST 请求数据
        $data = array(
            "userid" => strtoupper(md5($this->e_key_id)), // 用户id
            "sign" => strtoupper(md5($this->e_key_id. $this->e_key . $document)), // 用户id + 用户密钥 + 报文内容
            "msg" => $document, // XML payload
        );

        $result = $this->sendPost($url, http_build_query($data));

        return $result;
    }

    /**
     * @param $MessageHead   海关订单报文头，是一个数组
     *  --------------以下数组没有标记可为空的，是必填的-----------------------------
     * $MessageHead=array(
           'MessageID'=>报文唯一编号,不为空
           'MessageType'=>报文类型名称,不为空，默认“CEB301”
           'OrgCode'=>企业组织机构代码,不为空
           'SenderID'=>发送方,BMM+9位数字，海关客户端中可查看，不为空
           'ReceiverID'=>接收方,不为空，使用默认值
           'SendTime'=>发送时间,不为空
           'Version'=>版本号,不为空，默认“1.0”
           'SignerInfo'=>证书号,可为空
           'Sign'=>加签信息，可为空

     * ),
     * @param $OrderBillHead  海关订单表头，是一个数组
     * $OrderBillHead=array(
            'appType'=> 申报类型:1-新增 2-变更 3-删除,默认为1,
            'appTime'=>申报时间,格式:YYYYMMDDhhmmss,
            'appStatus'=>业务状态:1-暂存,2-申报,默认为2,
            'appUid'=>用户编号,电子口岸持卡人IC卡或IKEY编号或电子口岸传输用户标识,
            'appUname'=>电子口岸持卡人姓名或电子口岸传输用户名称,
            'orderNo'=>电商平台的原始订单编号,
            'ebpCode'=>电商平台的海关备案编码（10位）,
            'ebpName'=>电商平台的海关备案名称,
            'ebcCode'=>电商企业的海关备案编码(10位),
            'ebcName'=>电商企业的海关备案名称,
            'agentCode'=>代理清单报关企业的海关备案编码(10位),可为空
            'agentName'=>代理清单报关企业的海关备案名称,可为空
            'goodsValue'=>填写实际的商品总价，保留2位小数,
            'freight'=>填写实际的运费总价，保留2位小数,
            'currency'=>币制,按照海关编码填写，3位数字
            'consignee'=>收货人名称,
            'consigneeAddress'=>收货人地址,
            'consigneeTelephone'=>收货人电话,
            'consigneeCountry'=>收货人所在国,按照海关编码填写，3位数,
            'ProAmount'=>优惠金额,可为空
            'ProRemark'=>优惠信息说明,可为空
            'note'=>备注，可为空

     * ),
     * @param $OrderBillList  海关订单表体，是一个数组,可以有多条订单
     * $OrderBillList[]=array(
            'gnum'=>商品序号,
            'itemNo'=>企业商品货号,
            'gno'=>海关商品备案编号,
            'gcode'=>海关商品编码,可为空
            'gname'=>商品名称,
            'gmodel'=>规格型号,
            'barCode'=>条形码,可为空
            'brand'=>品牌,
            'unit'=>计量单位,按照海关编码填写3位数字,
            'currency'=>币制,
            'qty'=>成交数量,
            'price'=>单价,
            'total'=>总价,
            'giftFlag'=>是否赠品:0-否，1-是,
            'note'=>备注，可为空

     * ),
     *
     * @return string  根据海关订单的一些信息，生成xml文件，并返回xml文件的文件名
     */
    public function sea_creat_XML($orderRow)
    {
        //查询订单信息
        $orderGoodsObj = new IModel('order_goods as og, goods as g');
        $orderGoodsList = $orderGoodsObj->query('og.order_id='.$orderRow['id'].' and og.goods_id = g.id', 'og.*, g.cbe_goodsno, g.cbe_unitno, g.brand_id, g.cbe_taxes');

        $goodsObj = new IModel('goods');

        $now_date = new DateTime();
        $YYYYMMDDhhmmssSSS = $now_date->format('YmdHis') . substr($now_date->format('u'), 3);

        $MessageHead = array(
                           'MessageID'  =>  'CEB301'.$this->h_zd_code.$YYYYMMDDhhmmssSSS.rand(1000,9999),
                           'MessageType'=>  'CEB301',
                           'OrgCode'    =>  $this->h_zd_code,
                           'SenderID'   =>  $this->h_sender_id,
                           'ReceiverID' =>  'BBM000000001',
                           'SendTime'   =>  $YYYYMMDDhhmmssSSS,
                           'Version'    =>  '1.0',
                           'SignerInfo' =>   '',
                           'Sign'       =>  ''
                        );

        //获取地址
        $address_str = join(' ',area::name($orderRow['province'],$orderRow['city'],$orderRow['area']));
 
        $OrderBillHead = array(
                            'appType'   => '1', //申报类型:1-新增 2-变更 3-删除,默认为1
                            'appTime'   => substr($YYYYMMDDhhmmssSSS, 0, 14),
                            'appStatus' => '2', //业务状态:1-暂存,2-申报,默认为2
                            'appUid'    => $this->h_app_uid,//用户编号,电子口岸持卡人IC卡或IKEY编号或电子口岸传输用户标识
                            'appUname'  => $this->h_app_uname,//电子口岸持卡人姓名或电子口岸传输用户名称
                            'orderNo'   => str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $orderRow['order_no'],
                            'ebpCode'   => $this->h_eshop_entcode,
                            'ebpName'   => $this->h_eshop_entname,
                            'ebcCode'   => $this->h_com_entcode,
                            'ebcName'   => $this->h_com_entname,
                            'agentCode' => '4403660003',
                            'agentName' => '深圳前海电商供应链管理有限公司',
                            'goodsValue'=> $orderRow['order_amount'],
                            'freight'   => $orderRow['real_freight'],
                            'currency'  => '142', //币制,按照海关编码填写
                            'consignee' => $orderRow['accept_name'],
                            'consigneeAddress'  => $address_str.$orderRow['address'],
                            'consigneeTelephone'=> $orderRow['mobile'],
                            'consigneeCountry'  => '142',
                            'ProAmount' => '0.00', //优惠金额
                            'ProRemark' => '', //优惠信息说明
                            'note'      => '' //备注
                        );
        $OrderBillList = array();

        $brandObj = new IModel('brand');
        $productObj = new IModel('products');

        foreach ($orderGoodsList as $key => $_goods)
        {
            //商品数据
            $goods_arr = json_decode($_goods['goods_array'], true);
            
            //是否组合商品 20161121
            if (strpos($goods_arr['goodsno'], "-"))
            {
                //查询组合的市场价
                $_productRow = $productObj->getObj("goods_id ='".$_goods['goods_id']."' and products_no='".$goods_arr['goodsno']."'", "*", "id DESC");

                if ($_productRow) //是否存在多规格的货品
                {
                    $_group_market_price = $_productRow['market_price'];
                }
                else //单一组合商品, 查询goods表
                {
                    $_goodsRow = $goodsObj->getObj("id ='".$_goods['goods_id']."' and goods_no='".$goods_arr['goodsno']."' and is_del = 0");
                    $_group_market_price = $_goodsRow['market_price'];
                }

                $_goods_list = explode("|", $goods_arr['goodsno']);

                foreach ($_goods_list as $_item)
                {
                    $_goods_row = explode("-", $_item);
                    $_child_goods_no = $_goods_row[0];
                    $_child_goods_nums = $_goods_row[1];

                    //查询商品价格和税率
                    $_child_goods = $goodsObj->getObj("goods_no = '{$_child_goods_no}' and is_del <> 1 and cbe_goodsno is not null");

                    //品牌信息
                    $brand_info = $brandObj->getObj('id='.$_child_goods['brand_id']);

                    if($brand_info)
                    {
                        $_goods['brand_name'] = $brand_info['name'];
                    }                  

                    //未完税价格
                    $un_taxes_price = round( $_child_goods['market_price'] / $_group_market_price * $_goods['real_price'] / (1 + $_child_goods['cbe_taxes'] / 100), 2);
                    $OrderBillList[] = array(
                                    'gnum'      => str_pad($key+1, 4, "0", STR_PAD_LEFT),
                                    'itemNo'    => trim($_child_goods_no),
                                    'gno'       => str_pad(trim($_child_goods['cbe_goodsno']), 18, "0", STR_PAD_LEFT), //商品海关备案号
                                    'gcode'     => '', //海关商品编码，不填
                                    'gname'     => $_child_goods['name'],
                                    'gmodel'    => '无属性介绍',
                                    'barCode'   => '', //条形码
                                    'brand'     => $_goods['brand_name'], //商品品牌名
                                    'unit'      => str_pad($_child_goods['cbe_unitno'], 3, "0", STR_PAD_LEFT),//计量单位,按照海关编码填写3位数字
                                    'currency'  => '142',
                                    'qty'       => $_child_goods_nums * $_goods['goods_nums'],
                                    'price'     => $un_taxes_price, //未完税价格
                                    'total'     => $_child_goods_nums * $_goods['goods_nums'] * $un_taxes_price, //未完税价格
                                    'giftFlag'  => '0',//是否赠品:0-否，1-是
                                    'note'      => ''

                                );
                }
            }
            else
            {
                //海关信息是否存在
                if (empty($_goods['cbe_goodsno']) || empty($_goods['cbe_taxes']))
                {
                    $_tmp_goods = $goodsObj->getObj("goods_no = '{$goods_arr['goodsno']}' and is_del <> 1 and cbe_goodsno is not null");
                    $_goods['cbe_taxes'] = $_tmp_goods['cbe_taxes'];
                    $_goods['cbe_goodsno'] = $_tmp_goods['cbe_goodsno'];
                    $_goods['cbe_unitno'] = $_tmp_goods['cbe_unitno'];
                }

                $brand_info = $brandObj->getObj('id='.$_goods['brand_id']);
                if($brand_info)
                {
                    $_goods['brand_name'] = $brand_info['name'];
                }

                //未完税价格
                $un_taxes_price = round($_goods['real_price'] / (1 + $_goods['cbe_taxes'] / 100), 2);
                $OrderBillList[] = array(
                                'gnum'      => str_pad($key+1, 4, "0", STR_PAD_LEFT),
                                'itemNo'    => trim($goods_arr['goodsno']),
                                'gno'       => str_pad(trim($_goods['cbe_goodsno']), 18, "0", STR_PAD_LEFT), //商品海关备案号
                                'gcode'     => '', //海关商品编码，不填
                                'gname'     => $goods_arr['name'],
                                'gmodel'    => $goods_arr['value']?$goods_arr['value']:'无属性介绍',
                                'barCode'   => '', //条形码
                                'brand'     => $_goods['brand_name'], //商品品牌名
                                'unit'      => str_pad($_goods['cbe_unitno'], 3, "0", STR_PAD_LEFT),//计量单位,按照海关编码填写3位数字
                                'currency'  => '142',
                                'qty'       => $_goods['goods_nums'],
                                'price'     => $un_taxes_price, //未完税价格
                                'total'     => $_goods['goods_nums']*$un_taxes_price, //未完税价格
                                'giftFlag'  => '0',//是否赠品:0-否，1-是
                                'note'      => ''

                            );
            }
        }

        $contents = array(
            'MessageHead' => $MessageHead,
            'MessageBody' =>
                   array(
                      "OrderBillHead"=>$OrderBillHead,
                       "OrderBillList"=>$OrderBillList
                   ),

        );
        $this->setElement('CEB301Message', $contents);
        $document = $this->getDocument();
        return $MessageHead['MessageID'].$document;

    }


    public function new_sea_creat_XML($orderRow)
    {
        //查询订单信息
        $orderGoodsObj = new IModel('order_goods as og, goods as g');
        $orderGoodsList = $orderGoodsObj->query('og.order_id='.$orderRow['id'].' and og.goods_id = g.id', 'og.*, g.cbe_goodsno, g.cbe_unitno, g.brand_id, g.cbe_taxes,g.cbe_gcode, g.cbe_gmodel, g.cbe_ciqgmodel, g.cbe_country,g.unit');

        $goodsObj = new IModel('goods');

        $now_date = new DateTime();
        $YYYYMMDDhhmmssSSS = $now_date->format('YmdHis') . substr($now_date->format('u'), 3);

        //报文命名
        $messageFilename = "CEB_CEB311_" . $this->h_new_sender_id . "_EPORT_".$YYYYMMDDhhmmssSSS.rand(1000,9999);

        $mess_guid = $this->create_guid();

        $MessageHead = array(
                           'MessageID'  =>  $mess_guid,
                           'MessageType'=>  'CEB311',
                           'OrgCode'    =>  $this->h_zd_code,
                           'CopCode'    =>  $this->h_cop_code,
                           'CopName'    =>  $this->h_com_entname,
                           'SenderID'   =>  $this->h_new_sender_id,
                           'ReceiverID' =>  'EPORT',
                           'ReceiverDepartment' => 'CQ',
                           'SendTime'   =>  $YYYYMMDDhhmmssSSS,
                           'Version'    =>  '1.0',
                        );

        //获取地址
        $address_str = join(' ',area::name($orderRow['province'],$orderRow['city'],$orderRow['area']));
 
        
        $OrderList = array();

        $brandObj = new IModel('brand');
        $productObj = new IModel('products');

        $_goods_total = 0; //商品未完税价格合计
        $_gnum = 0; //序号计数

        foreach ($orderGoodsList as $key => $_goods)
        {
            //商品数据
            $goods_arr = json_decode($_goods['goods_array'], true);
            
            //是否组合商品 20161121
            if (strpos($goods_arr['goodsno'], "-"))
            {
                //查询组合的市场价
                $_productRow = $productObj->getObj("goods_id ='".$_goods['goods_id']."' and products_no='".$goods_arr['goodsno']."'", "*", "id DESC");

                if ($_productRow) //是否存在多规格的货品
                {
                    $_group_market_price = $_productRow['market_price'];
                }
                else //单一组合商品, 查询goods表
                {
                    $_goodsRow = $goodsObj->getObj("id ='".$_goods['goods_id']."' and goods_no='".$goods_arr['goodsno']."' and is_del = 0");
                    $_group_market_price = $_goodsRow['market_price'];
                }

                $_goods_list = explode("|", $goods_arr['goodsno']);

                foreach ($_goods_list as $_item)
                {
                    $_goods_row = explode("-", $_item);
                    $_child_goods_no = $_goods_row[0];
                    $_child_goods_nums = $_goods_row[1];

                    //查询商品价格和税率
                    $_child_goods = $goodsObj->getObj("goods_no = '{$_child_goods_no}' and is_del <> 1 and cbe_goodsno is not null");

                    //品牌信息
                    $brand_info = $brandObj->getObj('id='.$_child_goods['brand_id']);

                    if($brand_info)
                    {
                        $_goods['brand_name'] = $brand_info['name'];
                    }                  

                    //未完税价格
                    $un_taxes_price = round( $_child_goods['market_price'] / $_group_market_price * $_goods['real_price'] / (1 + $_child_goods['cbe_taxes'] / 100), 2);
                    $OrderList[] = array(
                                    'gnum'      => str_pad(++$_gnum, 4, "0", STR_PAD_LEFT),
                                    'itemNo'    => trim($_child_goods_no),
                                    'itemName'  => $_child_goods['name'],
                                    'itemDescribe'  => '',
                                    'barCode'   => '',
                                    'unit'      => str_pad($_child_goods['cbe_unitno'], 3, "0", STR_PAD_LEFT),//计量单位,按照海关编码填写3位数字
                                    'qty'       => $_child_goods_nums * $_goods['goods_nums'],
                                    'price'     => $un_taxes_price, //未完税价格
                                    'totalPrice'=> $_child_goods_nums * $_goods['goods_nums'] * $un_taxes_price, //未完税价格
                                    'currency'  => '142',
                                    'country'   => str_pad($_child_goods['cbe_country'], 3, "0", STR_PAD_LEFT),//原产国编码,按照海关编码填写3位数字
                                    'ciqGno'    => $_child_goods['cbe_ciqgmodel'], //检验检疫商品备案号
                                    'gcode'     => $_child_goods['cbe_gcode'],  //商品编码
                                    'gmodel'    => $_child_goods['cbe_gmodel'],  //商品编码
                                    'ciqGmodel' => $_child_goods['unit'],  //商品编码
                                    'brand'     => $_goods['brand_name'], //商品品牌名
                                    'note'      => '',
                                );
                    $_goods_total += $_child_goods_nums * $_goods['goods_nums'] * $un_taxes_price;
                }
            }
            else
            {
                //海关信息是否存在
                if (empty($_goods['cbe_goodsno']) || empty($_goods['cbe_taxes']))
                {
                    $_tmp_goods = $goodsObj->getObj("goods_no = '{$goods_arr['goodsno']}' and is_del <> 1 and cbe_goodsno is not null");
                    $_goods['cbe_taxes'] = $_tmp_goods['cbe_taxes'];
                    $_goods['cbe_goodsno'] = $_tmp_goods['cbe_goodsno'];
                    $_goods['cbe_unitno'] = $_tmp_goods['cbe_unitno'];
                    $_goods['cbe_country'] = $_tmp_goods['cbe_country'];
                    $_goods['cbe_gcode'] = $_tmp_goods['cbe_gcode'];
                    $_goods['cbe_gmodel'] = $_tmp_goods['cbe_gmodel'];
                    $_goods['cbe_ciqgmodel'] = $_tmp_goods['cbe_ciqgmodel'];
                }

                $brand_info = $brandObj->getObj('id='.$_goods['brand_id']);
                if($brand_info)
                {
                    $_goods['brand_name'] = $brand_info['name'];
                }

                //未完税价格
                $un_taxes_price = round($_goods['real_price'] / (1 + $_goods['cbe_taxes'] / 100), 2);
                $OrderList[] = array(
                                'gnum'      => str_pad(++$_gnum, 4, "0", STR_PAD_LEFT),
                                'itemNo'    => trim($goods_arr['goodsno']),
                                'itemName'  => $goods_arr['name'],
                                'itemDescribe'  => '',
                                'barCode'   => '',
                                'unit'      => str_pad($_goods['cbe_unitno'], 3, "0", STR_PAD_LEFT),//计量单位,按照海关编码填写3位数字
                                'qty'       => $_goods['goods_nums'],
                                'price'     => $un_taxes_price, //未完税价格
                                'totalPrice'=> $_goods['goods_nums']*$un_taxes_price, //未完税价格
                                'currency'  => '142',
                                'country'   => str_pad($_goods['cbe_country'], 3, "0", STR_PAD_LEFT),//原产国编码,按照海关编码填写3位数字
                                'ciqGno'    => $_goods['cbe_ciqgmodel'], //检验检疫商品备案号
                                'gcode'     => $_goods['cbe_gcode'],  //商品编码
                                'gmodel'    => $_goods['cbe_gmodel'],  //商品编码
                                'ciqGmodel' => $_goods['unit'],  //商品编码
                                'brand'     => $_goods['brand_name'], //商品品牌名
                                'note'      => '',
                            );
                $_goods_total += $_goods['goods_nums']*$un_taxes_price;
            }
        }

        //是否有相同sku
        $_no = 0;
        $_orderList = array();
        foreach ($OrderList as $_item)
        {
            if (! isset($_orderList[$_item['itemNo']]))
            {
                $_orderList[$_item['itemNo']] = $_item;
                $_orderList[$_item['itemNo']]['gnum'] = str_pad(++$_no, 4, "0", STR_PAD_LEFT);
                $_orderList[$_item['itemNo']]['qty'] = 0; //初始化数量
                $_orderList[$_item['itemNo']]['price'] = 0; //初始化未完税价格
                $_orderList[$_item['itemNo']]['totalPrice'] = 0; //初始化未完税总价
            }
            $_orderList[$_item['itemNo']]['price'] = round(($_orderList[$_item['itemNo']]['price'] * $_orderList[$_item['itemNo']]['qty'] + $_item['price'] * $_item['qty']) / ($_orderList[$_item['itemNo']]['qty'] + $_item['qty']), 2);
            $_orderList[$_item['itemNo']]['totalPrice'] += $_item['price'] * $_item['qty'];
            $_orderList[$_item['itemNo']]['qty'] += $_item['qty'];
        }
        $OrderList = array();
        foreach ($_orderList as $value)
        {
            $OrderList[] = $value;
        }
        $order_guid = $this->create_guid();
        $OrderHead = array(
                            'guid'      => $order_guid,
                            'appType'   => '1', //申报类型:1-新增 2-变更 3-删除,默认为1
                            'appTime'   => substr($YYYYMMDDhhmmssSSS, 0, 14),
                            'appStatus' => '2', //业务状态:1-暂存,2-申报,默认为2
                            //'appUid'    => $this->h_app_uid,//用户编号,电子口岸持卡人IC卡或IKEY编号或电子口岸传输用户标识
                            //'appUname'  => $this->h_app_uname,//电子口岸持卡人姓名或电子口岸传输用户名称
                            'orderType' => 'I', //电子订单类型：I 进口
                            'orderNo'   => str_pad($this->e_shop_id, 3, "0", STR_PAD_LEFT) . $orderRow['order_no'],
                            'ebpCode'   => $this->h_cop_code,
                            'ebpName'   => $this->h_eshop_entname,
                            'ebcCode'   => $this->h_cop_code,
                            'ebcName'   => $this->h_com_entname,
                            //'agentCode' => '4403660003',
                            //'agentName' => '深圳前海电商供应链管理有限公司',
                            'goodsValue'=> $_goods_total,
                            'freight'   => $orderRow['real_freight'],
                            'discount'  => '0', //使用积分、虚拟货币、代金券等非现金支付金额，无则填写“0”
                            'taxTotal'  => $orderRow['order_amount'] - $_goods_total, //代扣税费
                            'acturalPaid' => $orderRow['order_amount'],
                            'currency'  => '142', //币制,按照海关编码填写
                            'buyerRegNo' => $orderRow['user_id'], //订购人的交易平台注册号 
                            'buyerName' => $orderRow['accept_name'],
                            'buyerIdType' => '1', //1-身份证，2-其它。限定为身份证，填写“1”
                            'buyerIdNumber' => strtoupper($orderRow['accept_id']), //订购人的身份证号 
                            'consignee' => $orderRow['accept_name'],
                            'consigneeTelephone'=> $orderRow['mobile'],
                            'consigneeAddress'  => $address_str.$orderRow['address'],
                            //'consigneeDistrict'  => '',
                            //'ProAmount' => '0.00', //优惠金额
                            //'ProRemark' => '', //优惠信息说明
                            'note'      => '', //备注
                            //'payCode'   => '',
                            //'payName'   => '',
                            //'payTransactionId'   => '',
                            //'batchNumbers'   => '',
                        );

        $contents = array(
            'MessageHead' => $MessageHead,
            'Order' =>
                   array(
                      "OrderHead"=>$OrderHead,
                       "OrderList"=>$OrderList
                   ),

        );
        $this->setElement('CEB311Message', $contents);
        $document = $this->getDocument();
        //替换表头
        $document = str_replace('<CEB311Message>', '<CEB311Message xmlns="http://www.chinaport.gov.cn/ceb" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" guid="'.$mess_guid.'" version="1.0">', $document);
        
        return $messageFilename.$document;
    }

//-----------------------------------------------------------------------------------------------
//-----------------------------------------POST请求函数------------------------------------------
//-----------------------------------------------------------------------------------------------


    //发送post请求函数
    function sendPost($url, $params = false, $is_header = false)
    {
        $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
        $ch = curl_init();
        if ($ssl) 
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, $is_header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


//-----------------------------------------------------------------------------------------------
//-----------------------------以下部分是xml函数部分，不需要去了解操作------------------------------
//-----------------------------------------------------------------------------------------------

    /**
     * Create anlement with text into current xml document.
     * @access public
     * @param string $prm_elementName An element's name
     * @param string $prm_ElementText An element's text
     * @return null
     */
    public function setTextElement($prm_elementName, $prm_ElementText)
    {
        $this->startElement($prm_elementName);
        $this->text($prm_ElementText);
        $this->endElement();
    }

    /**
     * Construct elements and texts from an array recursively.
     * If the keys in an array are numbers, then this array will be exploded into multiple elements with the same name.
     * @access public
     * @param array $prm_elementName Contains element name
     * @param array $prm_element Contains attributes and texts
     * @return null
     */
    public function setElement($prm_elementName, $prm_element)
    {
        if(!is_array($prm_element))
        {
            $this->setTextElement($prm_elementName,  $prm_element);
            return;
        }

        $rootAdded = false;
        foreach ($prm_element as $index => $element)
        {
            if(is_numeric($index))
            {
                $this->setElement($prm_elementName, $element);
                continue;
            }

            if(!$rootAdded)
            {
                $rootAdded = true;
                $this->startElement($prm_elementName);
            }
            $this->setElement($index, $element);
        }

        if($rootAdded)
        {
            $this->endElement();
        }
    }

    /**
     * Return the content of current xml document.
     * @access public
     * @param null
     * @return string Xml document
     */
    public function getDocument()
    {
        $this->endElement();
        $this->endDocument();
        return $this->outputMemory();
    }

    /**
     * Output the content of current xml document.
     * @access public
     * @param null
     */
    public function output()
    {
        header('Content-type: text/xml');
        echo $this->getDocument();
    }

//-----------------------------------------------------------------------------------------------
//-------------------------------------生成唯一guid函数------------------------------------------
//-----------------------------------------------------------------------------------------------

    private function create_guid()
    {     
        $guid = '';
        $uid = uniqid("", true);
        $rand = rand(1,99999);
        $hash = strtoupper(hash('ripemd128', $uid . md5($rand)));
        $guid = substr($hash,  0,  8) . 
                '-' .
                substr($hash,  8,  4) .
                '-' .
                substr($hash, 12,  4) .
                '-' .
                substr($hash, 16,  4) .
                '-' .
                substr($hash, 20, 12);
        return $guid;
    }

}