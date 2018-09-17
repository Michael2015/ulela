<?php
/**
 * @class scan_wechat
 * @brief 扫一扫微信支付
 * @date 2015/4/21 15:45:40
 */
class scan_wechat extends paymentPlugin
{
	//支付插件名称
    public $name = '扫一扫微信支付';

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
	public function getSubmitUrl()
	{
		return 'https://api.mch.weixin.qq.com/pay/unifiedorder';
	}

	/**
	 * @see paymentplugin::notifyStop()
	 */
	public function notifyStop()
	{
		die("<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>");
	}

	/**
	 * @see paymentplugin::callback()
	 */
	public function callback($callbackData,&$paymentId,&$money,&$message,&$orderNo){}

	/**
	 * @see paymentplugin::serverCallback()
	 */
	public function serverCallback($callbackData,&$paymentId,&$money,&$message,&$orderNo)
	{
		$postXML      = file_get_contents("php://input");
		$callbackData = $this->converArray($postXML);

		if(isset($callbackData['return_code']) && $callbackData['return_code'] == 'SUCCESS')
		{
			//除去待签名参数数组中的空值和签名参数
			$para_filter = $this->paraFilter($callbackData);

			//对待签名参数数组排序
			$para_sort = $this->argSort($para_filter);

			//生成签名结果
			$mysign = $this->buildMysign($para_sort,Payment::getConfigParam($paymentId,'key'));

			//验证签名
			if($mysign == $callbackData['sign'])
			{
				if($callbackData['result_code'] == 'SUCCESS')
				{
					$orderNo = strstr($callbackData['out_trade_no'],"_",true);
					$orderNo = $orderNo ? $orderNo : $callbackData['out_trade_no'];
					$money   = $callbackData['total_fee']/100;
					//记录回执流水号
					if(isset($callbackData['transaction_id']) && $callbackData['transaction_id'])
					{
						$this->recordTradeNo($orderNo,$callbackData['transaction_id']);
					}
					return true;
				}
				else
				{
					$message = $callbackData['err_code_des'];
				}
			}
			else
			{
				$message = '签名不匹配';
			}
		}

		$message = $message ? $message : $callbackData['message'];
		return false;
	}

	/**
	 * @see paymentplugin::getSendData()
	 */
	public function getSendData($payment)
	{
		$return = array();

		//基本参数
		$return['appid']            = $payment['appid'];
		$return['mch_id']           = $payment['mch_id'];
		$return['nonce_str']        = rand(100000,999999);
		$return['body']             = $payment['R_Name']; //dyg_jzw 20151021 修改微信支付显示的商品名
		$return['out_trade_no']     = $payment['M_OrderNO']."_".rand(100,999);
		$return['total_fee']        = $payment['M_Amount']*100;
		$return['spbill_create_ip'] = IClient::getIp();
		$return['notify_url']       = $this->serverCallbackUrl;
		$return['trade_type']       = 'NATIVE';

		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($return);

		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);

		//生成签名结果
		$mysign = $this->buildMysign($para_sort, $payment['key']);

		//签名结果与签名方式加入请求提交参数组中
		$return['sign'] = $mysign;

		$xmlData = $this->converXML($return);
		$result  = $this->curlSubmit($xmlData);

		//进行与支付订单处理
		$resultArray = $this->converArray($result);
		if(is_array($resultArray))
		{
		//处理正确
		if(isset($resultArray['return_code']) && $resultArray['return_code'] == 'SUCCESS')
			{
				$resultArray['amount']   = $payment['M_Amount'];
				$resultArray['key']      = $payment['key'];
				$resultArray['order_no'] = $payment['M_OrderNO'];
				return $resultArray;
			}
			else
			{
				die($resultArray['return_msg']);
			}
		}
		else
		{
			die($result);
		}
		return null;
	}

	/**
	 * @brief 提交数据
	 * @param xml $xmlData 要发送的xml数据
	 * @return xml 返回数据
	 */
	private function curlSubmit($xmlData)
	{
		//接收xml数据的文件
		$url = $this->getSubmitUrl();

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/xml', 'Content-Type: application/xml'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0); //dyg_jzw 20150814
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}

	/**
	 * @see paymentplugin::doPay()
	 */
	public function doPay($sendData)
	{
		if(isset($sendData['code_url']) && $sendData['code_url'])
		{
			$sendData['code_img'] = "http://s.jiathis.com/qrcode.php?url=".$sendData['code_url'];
			if(stripos($sendData['order_no'],'recharge') !== false)
			{
				$sendData['url'] = IUrl::getHost().IUrl::creatUrl('/ucenter/account_log');
			}
			else
			{
				$sendData['url'] = IUrl::getHost().IUrl::creatUrl('/ucenter/order');
			}
			include(dirname(__FILE__).'/template/pay.php');
		}
		else
		{
			$message = $sendData['err_code_des'] ? $sendData['err_code_des'] : '微信下单API接口失败';
			die($message);
		}
	}

	/**
	 * @brief 从array到xml转换数据格式
	 * @param array $arrayData
	 * @return xml
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
		$prestr = $prestr.'&key='.$key;
		//把最终的字符串签名，获得签名结果
		$mysgin = md5($prestr);
		return strtoupper($mysgin);
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
			'mch_id'    => '商户号',
			'appid'     => '商户公众号AppID',
			'appsecret' => '商户公众号AppSecret',
			'key'       => '商户支付密钥',
		);
		return $result;
	}
}