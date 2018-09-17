<?php
/**
 * @class alipay_quick
 * @brief 支付宝JS支付
 * @date 2017-3-3 14:40
 * @author  yangmz
 */
class  alipay_quick extends paymentPlugin
{
    //支付插件名称
    public $name = '支付宝JS支付';

    /**
     * @see paymentplugin::getSubmitUrl()
     * 兴业银行的支付接口
     */
    public function getSubmitUrl()
    {
        return 'https://pay.swiftpass.cn/pay/gateway';
    }

    /**
     * @see paymentplugin::notifyStop()
     */
    public function notifyStop()
    {
        echo "success";
    }

    /**
     * @see paymentplugin::serverCallback()
     */
    public function serverCallback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
    {
        return $this->callback($callbackData,$paymentId,$money,$message,$orderNo);
    }
    /**
     * @see paymentplugin::callback()
     */
    public function callback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
    {
        if(empty($callbackData))
            return false;
        //将xml格式转Object
        $obj = simplexml_load_string($callbackData,'SimpleXMLElement', LIBXML_NOCDATA);
        //将object 转  array
        $callbackData = json_decode(json_encode($obj),true);
        $newArray = array();
        $newArray['service'] = $callbackData['trade_type'];
        $newArray['mch_id'] = $callbackData['mch_id'];
        $newArray['out_trade_no'] = $callbackData['out_trade_no'];
        $newArray['body'] = '东阳光全球购';
        $newArray['notify_url'] = $this->xingyeCallbackUrl;
        $newArray['nonce_str'] = $callbackData['nonce_str'];
        $newArray['total_fee'] = $callbackData['total_fee'];
        $newArray['mch_create_ip'] = $_SERVER['REMOTE_ADDR']; //客户端ip

        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($newArray);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildMysign($para_sort,Payment::getConfigParam($paymentId,'M_PartnerKey'));

        if($callbackData['sign'] == $mysign)
        {
            if ($callbackData['result_code'] == 0 && $callbackData['status'] == 0) //成功
            {
                //回传数据
                $orderNo = strstr($callbackData['out_trade_no'],"_",true);
                $orderNo = $orderNo ? $orderNo : $callbackData['out_trade_no'];

                //dyg_jzw 20160728 从数据库读取支付总金额
                $orderDB  = new IModel('order');
                $orderRow = $orderDB->getObj("order_no = '".$orderNo."'");
                $money = $orderRow['total_fee']/100;
                //记录等待发货流水号
                if(isset($callbackData['out_transaction_id']) && $callbackData['out_transaction_id'])
                {
                    $this->recordTradeNo($orderNo,$callbackData['out_transaction_id']);
                }
                return true;
            }
            else
            {
                $message = "支付失败";
            }
        }
        else
        {
            $message = '签名不正确';
        }
        return false;
    }

    /**
     * @see paymentplugin::getSendData()
     */
    public function getSendData($payment)
    {
        $return = array();
        //基本参数
        $return['service'] = 'pay.alipay.nativev2';
        $return['mch_id'] = $payment['M_PartnerId'];
        $return['out_trade_no'] = $payment['M_OrderNO'].'_'.rand(0,999999);
        $return['body'] = '东阳光全球购';
        $return['notify_url'] = $this->serverCallbackUrl; //异步通知URL

        //获取订单信息
        $orderDB  = new IModel('order');
        $orderRow = $orderDB->getObj('id = '.$payment['M_OrderId']);

        //获取订单商品信息
        $orderGoodsDB = new IModel('order_goods');
        $orderGoodsList = $orderGoodsDB->query('order_id = '.$payment['M_OrderId']);
        //商品条款
        foreach ($orderGoodsList as $_orderGoods)
        {
            $goods_array = json_decode($_orderGoods['goods_array'], true);
            //组合商品 获取商品信息
            if (strpos($goods_array['goodsno'], "-"))
            {
                $goodsObj = new IModel('goods');
                $productObj = new IModel('products');

                //查询组合的市场价
                $_productRow = $productObj->getObj("goods_id ='".$_orderGoods['goods_id']."' and products_no='".$goods_array['goodsno']."'", "*", "id DESC");

                if ($_productRow) //是否存在多规格的货品
                {
                    $_group_market_price = $_productRow['market_price'];
                }
                else //单一组合商品, 查询goods表
                {
                    $_goodsRow = $goodsObj->getObj("id ='".$_orderGoods['goods_id']."' and goods_no='".$goods_array['goodsno']."' and is_del = 0");
                    $_group_market_price = $_goodsRow['market_price'];
                }

                $_goods_list = explode("|", $goods_array['goodsno']);

                foreach ($_goods_list as $_item)
                {
                    $_goods_row = explode("-", $_item);
                    $_child_goods_no = $_goods_row[0];
                    $_child_goods_nums = $_goods_row[1];

                    //查询商品价格和税率
                    $_child_goods = $goodsObj->getObj("goods_no = '{$_child_goods_no}' and is_del = 0");

                    //添加商品明细
                    $goodsClauses[] = array(
                        'outId' => trim($_child_goods_no), //商品的外部ID
                        'name' => isset($_child_goods['name'])?trim($_child_goods['name']):'', //商品名称
                        'price' => round( $_child_goods['market_price'] / $_group_market_price * $_orderGoods['real_price'], 2), //商品单价
                        'quantity' => $_orderGoods['goods_nums'] * $_child_goods_nums, //商品数量
                    );
                }
            }
            else
            {
                $goodsClauses[] = array(
                    'outId' => $goods_array['goodsno'], //商品的外部ID
                    'name' => rtrim($goods_array['name'].' '.$goods_array['value']), //商品名称
                    'price' => $_orderGoods['real_price'], //商品单价
                    'quantity' => $_orderGoods['goods_nums'], //商品数量
                );
            }
        }
        //$return['attach'] = json_encode($goodsClauses);//扩展参数
        $return['nonce_str'] = md5(time().mt_rand(0,1000));//随机字符串，不长于32 位
        $return['total_fee'] = 1;//$orderRow['real_amount']*100; //交易额  最小单位是分
        $return['mch_create_ip'] = $_SERVER['REMOTE_ADDR']; //客户端ip

        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($return);

        //对待签名参数数组排序
        $return = $this->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildMysign($return, $payment['M_PartnerKey']);

        //签名结果与签名方式加入请求提交参数组中
        $return['sign'] = $mysign;
        $result = $this->sendPost($this->getSubmitUrl(),$this->formatXml($return));
        $result = $this->xmlDecode($result);
        return $result;
    }

    /**
     * @see paymentplugin::doPay()
     */
    public function doPay($sendData)
    {
        if(isset($sendData['code_img_url']) && $sendData['code_img_url'] && $sendData['status'] == 0)
        {
            $sendData['code_img'] = $sendData['code_img_url'];
            $sendData['url'] = IUrl::getHost().IUrl::creatUrl('/ucenter/order');
            include(dirname(__FILE__).'/template/pay.php');
        }
        else
        {
            $message =  '兴业银行支付宝接口失败';
            die($message);
        }
    }

//-----------------------------------------------------------------------------------------------
//-----------------------------------------POST请求函数------------------------------------------
//-----------------------------------------------------------------------------------------------


    //发送post请求函数
    function sendPost($url, $params = false, $is_header = false)
    {
        $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
        $ch = curl_init();
        if ($ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
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

    /**
     * @param $xml
     * @return array
     */
    private function xmlDecode($xml){
        $return_data = array();
        $xml_object = simplexml_load_string($xml,'SimpleXMLElement', LIBXML_NOCDATA);
        foreach($xml_object->children() as $key=>$value)
        {
            $return_data[$key] = (string)$value;
        }
        return $return_data;
    }
    /**
     * @param $data
     * @return string
     * 这里的xml数据  一定要保持与sign签名时用的参数一致
     */
    private function formatXml($data){
        $xml = "<xml><body><![CDATA[%s]]></body>
<mch_create_ip><![CDATA[%s]]></mch_create_ip>
<mch_id><![CDATA[%s]]></mch_id>
<nonce_str><![CDATA[%s]]></nonce_str>
<notify_url><![CDATA[%s]]></notify_url>
<out_trade_no><![CDATA[%s]]></out_trade_no>
<service><![CDATA[%s]]></service>
<sign><![CDATA[%s]]></sign>
<total_fee><![CDATA[%s]]></total_fee>
</xml>";
        return sprintf($xml,$data['body'],$data['mch_create_ip'],$data['mch_id'],$data['nonce_str'],$data['notify_url'],$data['out_trade_no'],$data['service'],$data['sign'],$data['total_fee']);
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
    private function buildMysign($sort_para,$key,$sign_type = "MD5")
    {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($sort_para);
        //把拼接后的字符串再与安全校验码直接连接起来
        $prestr = $prestr."&key=".$key;

        //把最终的字符串签名，获得签名结果
        $mysgin = strtoupper(md5($prestr));
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
     * @param 获取配置参数
     */
    public function configParam()
    {
        $result = array(
            'M_PartnerId'  => '兴业银行商户ID',
            'M_PartnerKey' => '兴业银行商户密钥',
            'M_Email'      => '绑定账号(邮箱，手机号)',
        );
        return $result;
    }
}