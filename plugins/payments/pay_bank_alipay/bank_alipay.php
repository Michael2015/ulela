<?php
/**
 * @copyright Copyright(c) 2015 aircheng.com
 * @file bank_alipay.php
 * @brief 支付宝插件类[网银直连]
 * @author nswe
 * @date 2015/5/11 15:43:10
 * @version 0.6
 * @note
 */

 /**
 * @class bank_alipay
 * @brief 支付宝插件类
 */
class bank_alipay extends paymentPlugin
{
	//支付插件名称
    public $name = '支付宝';

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
	public function getSubmitUrl()
	{
		return 'https://mapi.alipay.com/gateway.do?_input_charset=utf-8';
	}

	/**
	 * @see paymentplugin::notifyStop()
	 */
	public function notifyStop()
	{
		echo "success";
	}

	/**
	 * @see paymentplugin::callback()
	 */
	public function callback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
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
			$orderNo = $callbackData['out_trade_no'];
			$money   = $callbackData['total_fee'];

			//记录回执流水号
			if(isset($callbackData['trade_no']) && $callbackData['trade_no'])
			{
				$this->recordTradeNo($orderNo,$callbackData['trade_no']);
			}

			if($callbackData['trade_status'] == 'TRADE_FINISHED' || $callbackData['trade_status'] == 'TRADE_SUCCESS')
			{
				return true;
			}
		}
		else
		{
			$message = '签名不正确';
		}
		return false;
	}

	/**
	 * @see paymentplugin::serverCallback()
	 */
	public function serverCallback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
	{
		return $this->callback($callbackData,$paymentId,$money,$message,$orderNo);
	}

	/**
	 * @see paymentplugin::getSendData()
	 */
	public function getSendData($payment)
	{
		$defaultbank = IFilter::act(IReq::get('defaultbank'));
		$return = array();

		//基本参数
		$return['service'] = 'create_direct_pay_by_user';
		$return['partner'] = $payment['M_PartnerId'];
		$return['seller_email'] = $payment['M_Email'];
		$return['_input_charset'] = 'utf-8';
		$return['payment_type'] = 1;
		$return['return_url'] = $this->callbackUrl;
		$return['notify_url'] = $this->serverCallbackUrl;
		$return['defaultbank']= $defaultbank;
		$return['paymethod']= 'bankPay';

		//业务参数
		$return['subject'] = $payment['R_Name'];
		$return['out_trade_no'] = $payment['M_OrderNO'];
		$return['total_fee'] = number_format($payment['M_Amount'], 2, '.', '');

		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($return);

		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);

		//生成签名结果
		$mysign = $this->buildMysign($para_sort, $payment['M_PartnerKey']);

		//签名结果与签名方式加入请求提交参数组中
		$return['sign'] = $mysign;
		$return['sign_type'] = 'MD5';

		return $return;
	}

	/**
	 * @see paymentplugin::doPay()
	 */
	public function doPay($sendData)
	{
		//是否选择了银行
		if(isset($sendData['defaultbank']) && $sendData['defaultbank'])
		{
			parent::doPay($sendData);
		}
		else
		{
			$bankList = array(
				'ABC'    => '中国农业银行',
				'ICBCB2C'=> '中国工商银行',
				'CCB'    => '中国建设银行',
				'SPDB'   => '上海浦东发展银行',
				'BOCB2C' => '中国银行',
				'CMB'    => '招商银行',
				'CIB'    => '兴业银行',
				'GDB'    => '广发银行',
				'CMBC'   => '中国民生银行',
				//'HZCBB2C' => '杭州银行',
				'CEB'    => '中国光大银行',
				//'SHBANK' => '上海银行',
				//'NBBANK' => '宁波银行',
				'SPABANK'=> '平安银行',
				'POSTGC' => '中国邮政储蓄银行',
				'COMM'   => '交通银行',
				'BJBANK' => '北京银行',
				//'CITIC'  => '中信银行',
			);
			$paramArray = array_merge($_POST,$_GET);
			unset($paramArray['controller'],$paramArray['action']);
			$urlParam = http_build_query($paramArray);
			$postUrl  = IUrl::getHost().IUrl::creatUrl('/block/doPay?'.$urlParam);
			$urlPath  = IUrl::creatUrl().'plugins/payments/pay_bank_alipay/banklogo';
			include(dirname(__FILE__).'/template/selectBank.php');
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
			if($key == "sign" || $key == "sign_type" || $val == "")
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
		if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
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
			'M_PartnerId'  => '合作者身份（PID）',
			'M_PartnerKey' => '安全校验码（Key）',
			'M_Email'      => '登录账号',
		);
		return $result;
	}
}