<?php

require_once(dirname(__FILE__).'/epay_util.class.php');

/**
 * 收付直通车代收接口类<br />
 * <b>[注意]</b> 实例化该类时需要传入EPay参数，示例参数请见epay.config.php文件<br />
 * 收付直通车代收模块中的所有接口都在该类中，商户可以直接调用该类中的API方法<br />
 * 该SDK中代码仅供学习和参考，商户可以自己根据收付直通车代收接口文档进行编码实现，而不使用该SDK中的实现<br />
 * 各字段长度、类型等请参考收付直通车代收接口文档中的定义，SDK不检查这些长度，而直接发送给收付直通车<br />
 * [重要]各传入参数SDK都不作任何检查、过滤，请务必在传入前进行安全检查或过滤，特别是生成HTML代码的方法，务必保证传入参数的安全性，否则会导致安全问题。
 *
 * @author xiezz
 * @version 1.1.2
 */
Class EPay {

    // EPay配置参数，示例中在epay.config.php中定义
    private $epay_config;

    // 构造函数
    public function __construct($epay_config) {
        $this -> epay_config = $epay_config;
    }

    // 兼容旧版本PHP的构造函数
    public function EPay($epay_config) {
        $this -> __construct($epay_config);
    }

    // 快捷支付API地址，测试环境地址可根据需要修改
    const EP_PROD_API		= "https://pay.cib.com.cn/acquire/easypay.do";
    const EP_DEV_API		= "https://3gtest.cib.com.cn:37031/acquire/easypay.do";

    // 网关支付API地址，测试环境地址可根据需要修改
    const GP_PROD_API		= "https://pay.cib.com.cn/acquire/cashier.do";
    const GP_DEV_API		= "https://3gtest.cib.com.cn:37031/acquire/cashier.do";

    // 智能代付API地址，测试环境地址可根据需要修改
    const PY_PROD_API		= "https://pay.cib.com.cn/payment/api";
    const PY_DEV_API		= "https://3gtest.cib.com.cn:37031/payment/api";


    private static $sign_type = array(
        'cib.epay.acquire.easypay.acctAuth' => 'SHA1',
        'cib.epay.acquire.easypay.quickAuthSMS' => 'RSA',
        'cib.epay.acquire.checkSms' => 'RSA',
        'cib.epay.acquire.easypay.cancelAuth' => 'SHA1',
        'cib.epay.acquire.easypay.acctAuth.query' => 'SHA1',
        'cib.epay.acquire.easypay' => 'RSA',
        'cib.epay.acquire.easypay.query' => 'SHA1',
        'cib.epay.acquire.easypay.refund' => 'RSA',
        'cib.epay.acquire.easypay.refund.query' => 'SHA1',
        'cib.epay.acquire.authAndPay' => 'RSA',
        'cib.epay.acquire.easypay.quickAuth' => 'RSA',

        'cib.epay.acquire.cashier.netPay' => 'SHA1',
        'cib.epay.acquire.cashier.quickNetPay' => 'SHA1',
        'cib.epay.acquire.cashier.query' => 'SHA1',
        'cib.epay.acquire.cashier.refund' => 'RSA',
        'cib.epay.acquire.cashier.refund.query' => 'SHA1',

        'cib.epay.payment.getMrch' => 'RSA',
        'cib.epay.payment.pay' => 'RSA',
        'cib.epay.payment.get' => 'RSA',
    		
    	'cib.epay.acquire.settleFile' => 'SHA1',
    	'cib.epay.payment.receiptFile' => 'SHA1'
    );

    /**
     * 生成签名MAC字符串（包含SHA1算法和RSA算法）
     * @param array $param_array	参数列表（若包含mac参数名，则忽略该项）
     * @param string	$commkey	商户秘钥（加密算法为SHA1时使用，否则置null）
     * @param string	$cert		商户证书（加密算法为RSA时使用，否则置null）
     * @param string 	$cert_pwd	商户证书密码（加密算法为RSA时使用）
     * @return string				MAC字符串
     */
    public static function Signature($param_array, $commkey = null, $cert = null, $cert_pwd = '123456') {

        ksort($param_array);
        reset($param_array);
        $signstr = '';
        foreach ($param_array as $k => $v) {

            if(strcasecmp($k, 'mac') == 0) continue;
            $signstr .= "{$k}={$v}&";
        }

        if(array_key_exists('sign_type', $param_array) && $param_array['sign_type'] === 'RSA') {
            $signstr = substr($signstr, 0, strlen($signstr) - 1);
            if (false !== ($keystore = file_get_contents($cert)) &&
                openssl_pkcs12_read($keystore, $cert_info, $cert_pwd) &&
                openssl_sign($signstr, $sign, $cert_info['pkey'], 'sha1WithRSAEncryption')) {
                return base64_encode($sign);
            } else {
                return 'SIGNATURE_RSA_CERT_ERROR';
            }
        } else {		/* 默认SHA1方式 */
            $signstr .= $commkey;
            return strtoupper(sha1($signstr));
        }
    }

    /**
     * 验证服务器返回的信息中签名的正确性
     * @param array		$param_array	参数列表（必须包含mac参数）
     * @param string	$commkey		商户秘钥
     * @param string	$cert			商户证书路径
     * @return boolean					true-验签通过，false-验签失败
     */
    public static function VerifyMac($param_array, $commkey = null, $cert = null) {

        if(!array_key_exists('mac', $param_array) || !$param_array['mac'])
            return false;
        if(array_key_exists('sign_type', $param_array) && $param_array['sign_type'] === 'RSA') {
            ksort($param_array);
            reset($param_array);
            $signstr = '';
            foreach ($param_array as $k => $v) {

                if(strcasecmp($k, 'mac') == 0) continue;
                $signstr .= "{$k}={$v}&";
            }
            $signstr = substr($signstr, 0, strlen($signstr) - 1);

            $pubKey = openssl_pkey_get_public(file_get_contents($cert));
            $result = openssl_verify($signstr, base64_decode($param_array['mac']), $pubKey, 'sha1WithRSAEncryption');
            openssl_free_key($pubKey);
            return (1 === $result ? true : false);
        } else {		/* 默认SHA1方式 */
            $mac = EPay::Signature($param_array, $commkey);
            if(strcasecmp($mac, $param_array['mac']) == 0)
                return true;
            else
                return false;
        }
    }

    /**
     * POST通讯模式通讯
     * @param string	$url			接口URL
     * @param array		$param_array	post参数列表
     * @param string	$save_file_name	保存至该参数命名的文件（覆盖），为null时直接返回结果
     * @return mixed					响应内容
     */
    protected function postService($url, $param_array, $save_file_name) {

        if(array_key_exists('service', $param_array) && array_key_exists($param_array['service'], EPay::$sign_type))
            $param_array['sign_type'] = EPay::$sign_type[$param_array['service']];
        $param_array['mac'] = $this -> Signature($param_array, $this -> epay_config['commKey'], $this -> epay_config['mrch_cert'], $this -> epay_config['mrch_cert_pwd']);
        $response = null;
        if($this -> epay_config['isDevEnv'])
            $response = EPay_util::getHttpPostResponse($url, $param_array, true, $save_file_name, $this -> epay_config['proxy_ip'], $this -> epay_config['proxy_port']);
        else
            $response = EPay_util::getHttpPostResponse($url, $param_array, false, $save_file_name, $this -> epay_config['proxy_ip'], $this -> epay_config['proxy_port']);

        if(!$response)
            return SYS_ERROR_RESULT;
        else {
            if(TXN_ERROR_RESULT !== $response && SYS_ERROR_RESULT !== $response && FILE_ERROR_RESULT !== $response && SUCCESS_RESULT !== $response) {
                if($this -> epay_config['needChkSign']
                    && !$this -> VerifyMac(json_decode($response, true), $this -> epay_config['commKey'], ($this -> epay_config['isDevEnv'] ? $this -> epay_config['epay_cert_test'] : $this -> epay_config['epay_cert_prod'])))
                    return SIGN_ERROR_RESULT;
            }
            return $response;
        }
    }

    /**
     * 生成跳转HTML页面方法
     * @param string $url				接口URL
     * @param array $param_array		参数列表
     * @return string					跳转页面html源代码
     */
    protected function redirectService($url, $param_array) {

        $param_array['mac'] = $this -> Signature($param_array, $this -> epay_config['commKey']);

        $html = '';
        $html .= "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">";
        $html .= "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>收付直通车跳转接口</title></head>";
        $html .= "<form id=\"epayredirect\" name=\"epayredirect\" action=\"{$url}\" method=\"post\">";

        foreach ($param_array as $k => $v) {
            $html .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\"/>";
        }

        $html .= "<input type=\"submit\" value=\"submit\" style=\"display:none;\"></form>";
        $html .= "<script>document.forms[\"epayredirect\"].submit();</script>";
        $html .= "<body></body></html>";

        return $html;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * 快捷支付账户认证接口（异步接口）<br />
     * 该方法将生成跳转页面的全部HTML代码，商户直接输出该HTML代码至某个URL所对应的页面中，即可实现跳转，可以参考示例epay_redirect.php中的用法<br />
     * [重要]各传入参数SDK都不作任何检查、过滤，请务必在传入前进行安全检查或过滤，保证传入参数的安全性，否则会导致安全问题。
     * @param string $trac_no		商户跟踪号
     * @param string $acct_type		卡类型：0-储蓄卡,1-信用卡,2-企业账户
     * @param string $bank_no		人行联网行号
     * @param string $card_no		账号
     * @param string $user_name		姓名（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $cert_no		证件号码（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $card_phone	联系电话（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $expireDate	信用卡有效期（仅信用卡有效，格式MMYY，可选，若为非null，则用户界面显示该值且不可输）
     * @param string $cvn			信用卡CVN（仅信用卡有效，可选，若为非null，则用户界面显示该值且不可输）
     * @return	string				跳转页面HTML代码
     */
    public function epAuth($trac_no, $acct_type, $bank_no, $card_no, $user_name = null, $cert_no = null, $card_phone = null, $expireDate = null, $cvn = null) {

        $param_array = array();

        $param_array['trac_no']		= $trac_no;
        $param_array['acct_type']	= $acct_type;
        $param_array['bank_no']		= $bank_no;
        $param_array['card_no']		= $card_no;

        if($user_name) $param_array['user_name'] = $user_name;
        if($cert_no) {
            $param_array['cert_no'] = $cert_no;
            $param_array['cert_type'] = '0';
        }
        if($card_phone) $param_array['card_phone'] = $card_phone;

        if($expireDate) $param_array['expireDate'] = $expireDate;
        if($cvn) $param_array['cvn'] = $cvn;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.acctAuth';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> redirectService(EPay::EP_DEV_API, $param_array);
        else
            return $this -> redirectService(EPay::EP_PROD_API, $param_array);
    }

    /**
     * 快捷支付认证接口（同步接口，需短信确认）
     * @param string $trac_no		商户跟踪号
     * @param string $acct_type		卡类型：0-储蓄卡,1-信用卡
     * @param string $bank_no		人行联网行号
     * @param string $card_no		账号
     * @param string $user_name		姓名
     * @param string $cert_no		证件号码
     * @param string $card_phone	联系电话
     * @param string $expireDate	信用卡有效期（仅信用卡时必输，格式MMYY）
     * @param string $cvn			信用卡CVN（仅信用卡时必输）
     * @return	string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epAuthSyncWithSms($trac_no, $acct_type, $bank_no, $card_no, $user_name, $cert_no, $card_phone, $expireDate = null, $cvn = null) {

        $param_array = array();

        $param_array['trac_no']		= $trac_no;
        $param_array['acct_type']	= $acct_type;
        $param_array['bank_no']		= $bank_no;
        $param_array['card_no']		= $card_no;
        $param_array['user_name']	= $user_name;
        $param_array['cert_no']		= $cert_no;
        $param_array['card_phone']	= $card_phone;

        if($expireDate !== null)
            $param_array['expireDate']	= $expireDate;
        if($cvn !== null)
            $param_array['cvn']			= $cvn;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.quickAuthSMS';
        $param_array['ver']			= '01';
        $param_array['cert_type']	= '0';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷认证短信验证码确认接口
     * @param string $trac_no		发起同步认证时的商户跟踪号
     * @param string $sms_code		6位数字短信验证码
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epAuthCheckSms($trac_no, $sms_code) {

        $param_array = array();

        $param_array['trac_no']		= $trac_no;
        $param_array['sms_code']	= $sms_code;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.checkSms';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷支付账户解绑接口
     * @param string $card_no		账号
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epAuthCancel($card_no) {

        $param_array = array();

        $param_array['card_no']		= $card_no;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.cancelAuth';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷支付账户认证结果查询接口
     * @param string $trac_no		商户跟踪号
     * @return	string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epAuthQuery($trac_no) {

        $param_array = array();

        $param_array['trac_no']		= $trac_no;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.acctAuth.query';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷支付交易接口
     * @param string $order_no		订单号
     * @param string $order_amount	金额，单位元，两位小数，例：8.00
     * @param string $order_title	订单标题
     * @param string $order_desc	订单描述
     * @param string $card_no		支付卡号
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epPay($order_no, $order_amount, $order_title, $order_desc, $card_no) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_amount']= $order_amount;
        $param_array['order_title']	= $order_title;
        $param_array['order_desc']	= $order_desc;
        $param_array['card_no']		= $card_no;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay';
        $param_array['ver']			= '01';
        $param_array['sub_mrch']	= $this -> epay_config['sub_mrch'];
        $param_array['cur']			= 'CNY';
        $param_array['order_time']	= EPay_util::getDateTime();
        $param_array['order_ip']	= EPay_util::getLocalIp();
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷支付交易查询接口
     * @param string $order_no		订单号
     * @param string $order_date	订单日期，格式yyyyMMdd，为null时使用当前日期
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epQuery($order_no, $order_date = null) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= $order_date ? $order_date : EPay_util::getDate();

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.query';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷支付退款交易接口
     * @param string $order_no		待退款订单号
     * @param string $order_date	订单下单日期，格式yyyyMMdd
     * @param string $order_amount	退款金额（不能大于原订单金额）
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epRefund($order_no, $order_date, $order_amount) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= $order_date;
        $param_array['order_amount']= $order_amount;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.refund';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 快捷支付退款交易结果查询接口
     * @param string $order_no		退款的订单号
     * @param string $order_date	订单日期，格式yyyyMMdd，为null时使用当前日期
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function epRefundQuery($order_no, $order_date = null) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= $order_date ? $order_date : EPay_util::getDate();

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.easypay.refund.query';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::EP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::EP_PROD_API, $param_array, null);
    }

    /**
     * 无绑定账户快捷支付跳转页面生成接口<br />
     * 该方法将生成跳转页面的全部HTML代码，商户直接输出该HTML代码至某个URL所对应的页面中，即可实现跳转，可以参考epay_redirect.php中相关示例<br />
     * [重要]各传入参数SDK都不作任何检查、过滤，请务必在传入前进行安全检查或过滤，保证传入参数的安全性，否则会导致安全问题。
     * 参数bank_no,acct_type,card_no需要全为null或者全不为null。
     * @param string $order_no		订单号
     * @param string $order_amount	金额，单位元，两位小数，例：8.00
     * @param string $order_title	订单标题
     * @param string $order_desc	订单描述
     * @param string $remote_ip		客户端IP地址
     * @param string $bank_no		人行联网行号（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $acct_type		卡类型：0-储蓄卡,1-信用卡（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $card_no		账号（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $user_name		姓名（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $cert_no		证件号码（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $card_phone	联系电话（可选，若为非null，则用户界面显示该值且不可输）
     * @param string $expireDate	信用卡有效期（仅信用卡有效，格式MMYY，可选，若为非null，则用户界面显示该值且不可输）
     * @param string $cvn			信用卡CVN（仅信用卡有效，可选，若为非null，则用户界面显示该值且不可输）
     * @return string				跳转页面HTML代码
     */
    public function epAuthPay($order_no, $order_amount, $order_title, $order_desc, $remote_ip,
            $bank_no = null, $acct_type = null, $card_no = null, $user_name = null, $cert_no = null, $card_phone = null, $expireDate = null, $cvn = null) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_amount']= $order_amount;
        $param_array['order_title']	= $order_title;
        $param_array['order_desc']	= $order_desc;
        $param_array['order_ip']	= $remote_ip;

        if($bank_no !== null) $param_array['bank_no'] = $bank_no;
        if($acct_type !== null) $param_array['acct_type'] = $acct_type;
        if($card_no !== null) $param_array['card_no'] = $card_no;
        if($user_name !== null) $param_array['user_name'] = $user_name;
        if($cert_no !== null) {
            $param_array['cert_no'] = $cert_no;
            $param_array['cert_type'] = '0';
        }
        if($card_phone !== null) $param_array['card_phone'] = $card_phone;
        if($expireDate !== null) $param_array['expireDate'] = $expireDate;
        if($cvn !== null) $param_array['cvn'] = $cvn;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.authAndPay';
        $param_array['ver']			= '01';
        $param_array['sub_mrch']	= $this -> epay_config['sub_mrch'];
        $param_array['cur']			= 'CNY';
        $param_array['order_time']	= EPay_util::getDateTime();
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> redirectService(EPay::EP_DEV_API, $param_array);
        else
            return $this -> redirectService(EPay::EP_PROD_API, $param_array);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * 网关支付交易跳转页面生成接口<br />
     * 该方法将生成跳转页面的全部HTML代码，商户直接输出该HTML代码至某个URL所对应的页面中，即可实现跳转，可以参考epay_redirect.php中示例<br />
     * [重要]各传入参数SDK都不作任何检查、过滤，请务必在传入前进行安全检查或过滤，保证传入参数的安全性，否则会导致安全问题。
     * @param string $order_no		订单号
     * @param string $order_amount	金额，单位元，两位小数，例：8.00
     * @param string $order_title	订单标题
     * @param string $order_desc	订单描述
     * @param string $remote_ip		客户端IP地址
     * @return string				跳转页面HTML代码
     */
    public function gpPay($order_no, $order_amount, $order_title, $order_desc, $remote_ip) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_amount']= $order_amount;
        $param_array['order_title']	= $order_title;
        $param_array['order_desc']	= $order_desc;
        $param_array['order_ip']	= $remote_ip;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.cashier.netPay';
        $param_array['ver']			= '01';
        $param_array['sub_mrch']	= $this -> epay_config['sub_mrch'];
        $param_array['cur']			= 'CNY';
        $param_array['order_time']	= EPay_util::getDateTime();
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> redirectService(EPay::GP_DEV_API, $param_array);
        else
            return $this -> redirectService(EPay::GP_PROD_API, $param_array);
    }

    /**
     * 网关支付交易查询接口
     * @param string $order_no		订单号
     * @param string $order_date	订单日期，格式yyyyMMdd，为null时使用当前日期
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function gpQuery($order_no, $order_date = null) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= $order_date ? $order_date : EPay_util::getDate();

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.cashier.query';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::GP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::GP_PROD_API, $param_array, null);
    }

    /**
     * 网关支付退款交易接口
     * @param string $order_no		待退款订单号
     * @param string $order_date	订单下单日期，格式yyyyMMdd
     * @param string $order_amount	退款金额（不能大于原订单金额）
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function gpRefund($order_no, $order_date, $order_amount) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= $order_date;
        $param_array['order_amount']= $order_amount;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.cashier.refund';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::GP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::GP_PROD_API, $param_array, null);
    }

    /**
     * 网关支付退款交易结果查询接口
     * @param string $order_no		退款的订单号
     * @param string $order_date	订单日期，格式yyyyMMdd，为null时使用当前日期
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function gpRefundQuery($order_no, $order_date = null) {

        $param_array = array();

        $param_array['order_no']	= $order_no;
        $param_array['order_date']	= $order_date ? $order_date : EPay_util::getDate();

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.cashier.refund.query';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::GP_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::GP_PROD_API, $param_array, null);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * 智能代付单笔付款接口
     *
     * @param string $order_no		订单号
     * @param string $to_bank_no	收款行行号
     * @param string $to_acct_no	收款人账户
     * @param string $to_acct_name	收款人户名
     * @param string $acct_type		账户类型：0-储蓄卡,1-信用卡,2-对公账户
     * @param string $trans_amt		付款金额
     * @param string $trans_usage	用途
     * @return string				json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function pyPay($order_no, $to_bank_no, $to_acct_no, $to_acct_name, $acct_type, $trans_amt, $trans_usage) {

        $param_array = array();

        $param_array['order_no'] = $order_no;
        $param_array['to_bank_no'] = $to_bank_no;
        $param_array['to_acct_no'] = $to_acct_no;
        $param_array['to_acct_name'] = $to_acct_name;
        $param_array['acct_type'] = $acct_type;
        $param_array['trans_amt'] = $trans_amt;
        $param_array['trans_usage'] = $trans_usage;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.payment.pay';
        $param_array['ver']			= '02';
        $param_array['sub_mrch']	= $this -> epay_config['sub_mrch'];
        $param_array['cur']			= 'CNY';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::PY_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::PY_PROD_API, $param_array, null);
    }

    /**
     * 智能代付单笔订单查询接口
     *
     * @param string $order_no      订单号
     * @return string               json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function pyQuery($order_no) {

        $param_array = array();

        $param_array['order_no'] = $order_no;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.payment.get';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::PY_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::PY_PROD_API, $param_array, null);
    }

    /**
     * 智能代付商户信息查询接口
     *
     * @return string               json格式结果，返回结果包含字段请参看收付直通车代收接口文档
     */
    public function pyGetMrch() {

        $param_array = array();

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.payment.getMrch';
        $param_array['ver']			= '02';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            return $this -> postService(EPay::PY_DEV_API, $param_array, null);
        else
            return $this -> postService(EPay::PY_PROD_API, $param_array, null);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * 对账文件下载接口
     * @param string $rcpt_type			回单类型：0-快捷入账回单；1-快捷出账回单；2-快捷手续费回单；3-网关支付入账回单；4-网关支付出账回单；5-网关支付手续费回单；6-代付入账回单；7-代付出账回单；8-代付手续费回单
     * @param string $trans_date		交易日期，格式yyyyMMdd
     * @param string $save_file_name	保存下载内容至以该变量为名的文件
     * @return string					当下载成功时，返回SUCCESS_RESULT常量值；当下载失败时，返回失败信息json字符串
     */
    public function dlSettleFile($rcpt_type, $trans_date, $save_file_name) {

        $param_array = array();

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['ver']			= '01';
        $param_array['trans_date']	= $trans_date;
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($rcpt_type === '6' || $rcpt_type === '7' || $rcpt_type === '8') {
            if($rcpt_type === '6') $param_array['rcpt_type'] = '0';
            else if($rcpt_type === '7') $param_array['rcpt_type'] = '1';
            else $param_array['rcpt_type'] = '2';

            $param_array['service']		= 'cib.epay.payment.receiptFile';
            if($this -> epay_config['isDevEnv'])
                $response = $this -> postService(EPay::PY_DEV_API, $param_array, $save_file_name);
            else
                $response = $this -> postService(EPay::PY_PROD_API, $param_array, $save_file_name);
        } else {
            $param_array['rcpt_type']	= $rcpt_type;
            $param_array['service']		= 'cib.epay.acquire.settleFile';
            if($this -> epay_config['isDevEnv'])
                $response = $this -> postService(EPay::GP_DEV_API, $param_array, $save_file_name);
            else
                $response = $this -> postService(EPay::GP_PROD_API, $param_array, $save_file_name);
        }
        return $response;
    }

    /**
     * 行号文件下载接口
     * @param string $download_type		文件类型：01-行号文件
     * @param string $save_file_name	保存下载内容至以该变更为名的文件
     * @return string					当下载成功时，返回SUCCESS_RESULT常量值；当下载失败时，返回失败信息json字符串
     */
    public function dlFile($download_type, $save_file_name) {

        $param_array = array();

        $param_array['download_type']	= $download_type;

        $param_array['appid']		= $this -> epay_config['appid'];
        $param_array['service']		= 'cib.epay.acquire.download';
        $param_array['ver']			= '01';
        $param_array['timestamp']	= EPay_util::getDateTime();

        if($this -> epay_config['isDevEnv'])
            $response = $this -> postService(EPay::GP_DEV_API, $param_array, $save_file_name);
        else
            $response = $this -> postService(EPay::GP_PROD_API, $param_array, $save_file_name);

        return $response;
    }
}

