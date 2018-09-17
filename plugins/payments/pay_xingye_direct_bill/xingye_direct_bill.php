<?php
/**
 * @class xingye_scan
 * @brief  兴业银行-微信扫码支付
 * @date 2017-2-27 16:40
 * @author  yangmz
 */
class xingye_direct_bill extends paymentPlugin
{
    // 快捷支付API地址，测试环境地址可根据需要修改
    // 网关支付API地址，测试环境地址可根据需要修改
    const GP_PROD_API		= "https://pay.cib.com.cn/acquire/cashier.do";
    const GP_DEV_API		= "https://3gtest.cib.com.cn:37031/acquire/cashier.do";
    const PAYMENT_ID = 22;//定义支付方式ID常量
    private $sub_mrch = "东阳光大健康电商";
    /**
     * @see paymentplugin::getSubmitUrl()
     * 兴业银行的支付接口
     */
    public function getSubmitUrl()
    {
        return self::GP_DEV_API;
    }
    /**
     * @see paymentplugin::notifyStop()
     */
    public function notifyStop()
    {
        echo "success";
    }
    // 在发送报文给收付直通车时，会使用该密钥进行签名（RSA算法方式）
    private function getMrchCert()
    {
        return dirname(__FILE__).'/key/appsvr_clientQ0000898.pfx';
    }
    private  function  getMrchCertPwd()
    {
        return "123456";
    }
    /**
     * 异步
     * @see paymentplugin::serverCallback()
     */
    public function serverCallback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
    {
        return $this->callback($callbackData,$paymentId,$money,$message,$orderNo);
    }
    /**
     * @see paymentplugin::callback()
     * 同步
     */
    public function callback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
    {
        $mysign = $this->buildSignature($callbackData, Payment::getConfigParam($paymentId, 'M_PartnerKey'));
        if ($callbackData['mac'] === $mysign)
        {
            if ("NOTIFY_ACQUIRE_SUCCESS" === $callbackData["event"])  //// 支付成功通知
            {
                $order_no = $callbackData["order_no"];
                $orderNo = strstr($order_no, "_", true);
                $money = $callbackData['order_amount'];
                //记录等待发货流水号
                if (isset($callbackData['sno']) && $callbackData['sno'])
                {
                    //保存订单号尾数|流水号|下单时间|订单金额
                    $this->recordTradeNo($orderNo, substr($order_no, strpos($order_no,'_')+1)."|".$callbackData['sno']);
                }
                return true;
            }
        }
        else
        {
            $message = "支付失败";
        }
        return false;
    }

    /**
     * @param $order_no
     * 由于在订单提交后需要提供给用户选择银行的时间，在此期间若发起查询，收付直通
     * 车将返回“20310-订单不存在”，此时该订单不能当作失败处理，所以：
     * 请勿在订单提交后立即做查询操作，建议半个小时后再进行查询。
     */
    public function getRefundQueryData($order_no)
    {
        $partnerId =  Payment::getConfigParam(self::PAYMENT_ID,'M_PartnerId');
        $partnerKey = Payment::getConfigParam(self::PAYMENT_ID,'M_PartnerKey');
        $param_array = array();
        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= substr($order_no,0,8);//订单提交时间
        $param_array['appid']		= $partnerId;
        $param_array['service']		= 'cib.epay.acquire.cashier.query';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= date('YmdHis');
        $param_array['sign_type']   = 'SHA1';//只支持RSA
        $param_array['mac'] = $this->buildSignature($param_array, $partnerKey);
        return $param_array;
    }
    /**
     * 网关支付退款交易接口
     * @param string $order_no		待退款订单号
     * @param string $order_date	订单下单日期，格式yyyyMMdd
     * @param string $order_amount	退款金额（不能大于原订单金额）
     */
    public function getRefundData($order_no,$order_time,$order_amount)
    {
        $partnerId =  Payment::getConfigParam(self::PAYMENT_ID,'M_PartnerId');
        $partnerKey = Payment::getConfigParam(self::PAYMENT_ID,'M_PartnerKey');
        $param_array = array();
        $param_array['order_no']	= $order_no;//订单编号
        $param_array['order_date']	= $order_time;//订单提交时间。格式：yyyyMMdd
        $param_array['order_amount']= $order_amount;//退款金额，单位为元，即：十位整数，两位小数；退款金额不能大于原始订单金额
        $param_array['appid']		= $partnerId;//兴业银行分配给商户的AppID
        $param_array['service']		= 'cib.epay.acquire.cashier.refund';
        $param_array['ver']			= '02';//接口版本号，固定02
        $param_array['timestamp']	= date('YmdHis');
        $param_array['sign_type']   = 'RSA';//只支持RSA
        $param_array['mac'] = $this->buildSignature($param_array, $partnerKey,$this->getMrchCert(),$this->getMrchCertPwd());
        return $param_array;
    }
    /**
     *json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     * @see paymentplugin::getSendData()
     * @param $payment
     * @return array
     */
    public function getSendData($payment)
    {
        //构造要请求的参数数组，无需改动
        $param_array = array(
            "timestamp"      => date("YmdHis"),
            "appid"          => $payment['M_PartnerId'],
            "service"        => 'cib.epay.acquire.cashier.netPay',
            "ver"            => '01',
            "sign_type"      => "SHA1",
            "sub_mrch"       => $payment['R_Name'], //二级商户名称
            "order_no"       => $payment['M_OrderNO'].'_'.rand(100000,999999),
            "order_amount"   => $payment['M_Amount'],
            "cur"            => "CNY",
            "order_title"    => $payment['R_Name'].' - 支付订单'.$payment['M_OrderNO'],
            "order_desc"     => $payment['R_Name'].' - 支付订单'.$payment['M_OrderNO'],
            "order_time"     => date("YmdHis"),
            "order_ip"       => IClient::getIp(),
        );
        $param_array['mac'] = $this->buildSignature($param_array, $payment['M_PartnerKey']);
        return $param_array;
    }

    /**
     * @see paymentplugin::doPay()
     */
    public function doPay($sendData)
    {
        parent::doPay($sendData);
    }
    /**
     * 生成签名MAC字符串（包含SHA1算法和RSA算法）
     * @param array $param_array	参数列表（若包含mac参数名，则忽略该项）
     * @param string	$commkey	商户秘钥（加密算法为SHA1时使用，否则置null）
     * @param string	$cert		商户证书（加密算法为RSA时使用，否则置null）
     * @param string 	$cert_pwd	商户证书密码（加密算法为RSA时使用）
     * @return string				MAC字符串
     */
    public function buildSignature($param_array, $commkey = null, $cert = null, $cert_pwd = '123456')
    {
        $param_array = $this->paraFilter($param_array);
        $param_array = $this->argSort($param_array);
        $signstr = $this->createLinkstring($param_array);

        if(array_key_exists('sign_type', $param_array) && $param_array['sign_type'] === 'RSA')
        {
            if (false !== ($keystore = file_get_contents($cert)) &&
                openssl_pkcs12_read($keystore, $cert_info, $cert_pwd) &&
                openssl_sign($signstr, $sign, $cert_info['pkey'], 'sha1WithRSAEncryption'))
            {
                return base64_encode($sign);
            }
            else
            {
                return 'SIGNATURE_RSA_CERT_ERROR';
            }
        }
        else
        {
            /* 默认SHA1方式 */
            $signstr .= '&'.$commkey;
            return strtoupper(sha1($signstr));
        }
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
            if($key == "sign" || $key == "mac" || $val == "")
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
        if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
        {
            $arg = stripslashes($arg);
        }
        return $arg;
    }
    /**
     * @return array
     */
    public function configParam()
    {
        $result = array(
            'M_PartnerId' => '商户ID',
            'M_PartnerKey'=> '商户支付密钥',
        );
        return $result;
    }
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
}