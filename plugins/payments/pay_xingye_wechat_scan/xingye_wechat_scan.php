<?php
/**
 * @class xingye_scan
 * @brief  兴业银行-微信扫码支付
 * @date 2017-2-27 16:40
 * @author  yangmz
 */
class xingye_wechat_scan extends paymentPlugin
{
    //支付插件名称
    public $name = '兴业银行微信扫码支付';

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
        $postXML      = file_get_contents("php://input");
        $callbackData = $this->converArray($postXML);
        //将xml格式转Object
        if(isset($callbackData['status']) && $callbackData['status'] == 0 && $callbackData['result_code'] == 0)
        {
            //除去待签名参数数组中的空值和签名参数
            $para_filter = $this->paraFilter($callbackData);

            //对待签名参数数组排序
            $para_sort = $this->argSort($para_filter);

            //生成签名结果
            $mysign = $this->buildMysign($para_sort,Payment::getConfigParam($paymentId,'M_PartnerKey'));

            if($callbackData['sign'] == $mysign)
            {
                //回传数据
                $orderNo = strstr($callbackData['out_trade_no'],"_",true);
                $orderNo = $orderNo ? $orderNo : $callbackData['out_trade_no'];
                $money = $callbackData['total_fee']/100;
                //记录等待发货流水号
                if(isset($callbackData['out_transaction_id']) && $callbackData['out_transaction_id'])
                {
                    $this->recordTradeNo($orderNo,$callbackData['out_transaction_id']);
                }
                return true;
            }
            else
            {
                $message = '签名不正确';
            }
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
        $return['service'] = 'pay.weixin.native';
        $return['mch_id'] = $payment['M_PartnerId'];
        $return['out_trade_no'] = $payment['M_OrderNO'].'_'.rand(0,999999);
        $return['notify_url'] = $this->serverCallbackUrl; //异步通知URL
        $return['body'] = $payment['P_Name'];
        $return['nonce_str'] = md5(time().mt_rand(0,1000));//随机字符串，不长于32 位
        $return['total_fee'] = $payment['M_Amount']*100; //交易额  最小单位是分
        $return['mch_create_ip'] = $_SERVER['REMOTE_ADDR']; //客户端ip

        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($return);

        //对待签名参数数组排序
        $return = $this->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildMysign($return, $payment['M_PartnerKey']);
        //签名结果与签名方式加入请求提交参数组中
        $return['sign'] = $mysign;
        $result = $this->sendPost($this->getSubmitUrl(),$this->converXML($return));
        //判断返回结果状态
        if($result)
        {
            $result = $this->converArray($result);
            //一般有返回有status 参数，0 表示调用成功；非0 表示调用失败。
            if($result['status'] == 0 && $result['result_code'] == 0)
            {
                return $result;
            }
            else
            {
                return null;
            }
        }
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
            $message =  'API接口请求失败';
            die($message);
        }
    }
    /**
     * @brief 执行退款接口
     * @param array $payment 退款信息接口
     */
    public function doRefund($payment)
    {
        $return = array();
        //基本参数
        $return['service']          = 'unified.trade.refund';
        $return['mch_id']         = $payment['mch_id'];
        $return['nonce_str']      = rand(100000,999999);
        $return['transaction_id'] = $payment['M_TransactionId'];
        $return['out_refund_no']  = $payment['M_RefundNo'];
        $return['total_fee']      = $payment['M_Amount']*100;
        $return['refund_fee']     = $payment['M_Refundfee']*100;
        $return['op_user_id']     = $payment['mch_id'];//操作员帐号,默认为商户号
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($return);
        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);
        //生成签名结果
        $mysign = $this->buildMysign($para_sort, $payment['key']);
        //签名结果与签名方式加入请求提交参数组中
        $return['sign'] = $mysign;
        $xmlData     = $this->converXML($return);
        $response    = self::sendPost($this->getSubmitUrl(),$xmlData);
        $resultArray = $this->converArray($response);
        if($resultArray['return_code'] == 0 && $resultArray['status'] == 0)
        {
            if(isset($resultArray['refund_id']) && $resultArray['refund_id'])
            {
                $this->recordRefundTradeNo($payment['M_RefundId'],$resultArray['refund_id']);
            }
            return true;
        }
        else
        {
            return $resultArray['message'];
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
     * @brief 从xml到array转换数据格式
     * @param xml $xmlData
     * @return array
     */
    private function converArray($xmlData)
    {
        $result = array();
        $xmlHandle = xml_parser_create();
        xml_parse_into_struct($xmlHandle, $xmlData, $resultArray);

        foreach($resultArray as $key => $val)
        {
            if($val['tag'] != 'XML')
            {
                $result[$val['tag']] = $val['value'];
            }
        }
        return array_change_key_case($result);
    }
    /**
     * @param $data
     * @return string
     * 这里的xml数据  一定要保持与sign签名时用的参数一致
     */
    private function converXML($arrayData)
    {
        $xml = '<xml>';
        foreach($arrayData as $key => $val)
        {
            $xml .= '<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
        }
        $xml .= '</xml>';
        return $xml;
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