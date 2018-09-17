<?php
/**
 * @copyright Copyright(c) 2016 dyg.cn
 * @file yijifu.php
 * @brief 易极付插件类
 * @author ken
 * @date 2016-03-07
 * @version 1.0
 * @note
 */

/**
 * @class alipay
 * @brief 支付宝插件类[担保+即时到帐双接口]
 */
class yijifu extends paymentPlugin
{
	//支付插件名称
    public $name = '易极付';
    public $e_preid = '077'; //e码头订单前缀

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
	public function getSubmitUrl()
	{
		return 'https://api.yiji.com';
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
			if ($callbackData['success'] && ($callbackData['tradeStatus'] == 'wait_seller_send_goods' || $callbackData['tradeStatus'] == 'trade_finished'))
			{
				//回传数据
				if (isset($callbackData['outOrderNo'])) 
				{
					$orderNo = substr($callbackData['outOrderNo'], 3);
				}
				else if($callbackData['orderNo']) 
				{
					$orderNo = substr($callbackData['orderNo'], 0, strpos($callbackData['orderNo'], '_'));
				}

				//dyg_jzw 20160728 从数据库读取支付总金额
				$orderDB  = new IModel('order');
				$orderRow = $orderDB->getObj("order_no = '".$orderNo."'");
				$money = $orderRow['order_amount'];

				//记录等待发货流水号
				if($callbackData['resultCode'] == 'EXECUTE_SUCCESS' && isset($callbackData['tradeNo']))
				{
					$this->recordTradeNo($orderNo, $callbackData['tradeNo']);
					return true;
				}
			}
			elseif($callbackData['tradeStatus'] == 'wait_buyer_pay')
			{
				$message = '未成功支付';
			}
			else
			{
				$message = $callbackData['resultMessage'];
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
		$return = array();

		//基本参数
		$return['service'] = 'commonTradePay';
		$return['partnerId'] = $payment['M_PartnerId'];
		$return['sellerUserId'] = $payment['M_PartnerId'];
		$return['signType'] = 'MD5';
		$return['version'] = '2.0';

		$return['returnUrl'] = $this->callbackUrl; //页面成功跳转返回URL
		$return['notifyUrl'] = $this->serverCallbackUrl; //异步通知URL

		//业务参数
		$return['tradeName'] = '阳光全球购';
		$return['orderNo'] = $payment['M_OrderNO'].'_'.rand(0,999999);
		$return['outOrderNo'] = $this->e_preid.$payment['M_OrderNO'];
		$return['buyerUserName'] = $payment['P_Name']; //买家用户名
		$return['tradeMemo'] = $payment['M_Remark']; //交易备注

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
			$goodsClauses[] = array(
                                'outId' => $goods_array['goodsno'], //商品的外部ID
                                'name' => rtrim($goods_array['name'].' '.$goods_array['value']), //商品名称
                                'price' => $_orderGoods['real_price'], //商品单价
                                'quantity' => $_orderGoods['goods_nums'], //商品数量
                            );
		}
		$return['goodsClauses'] = json_encode($goodsClauses);

		//附带费用 - 运费
		/*$incidentalFeeClauses[] = array(
									'feeType' => 'POSTAGE', //邮费
									'payerRole' => 'TRADE_BUYER', //付款方角色 - 买家
									'payeeUserId' => $payment['M_PartnerId'], //收款方会员号
									'feeAmount' => $orderRow['real_freight'], //费用金额
								);
		$return['incidentalFeeClauses'] = json_encode($incidentalFeeClauses);*/

		$return['tradeAmount'] = $orderRow['real_amount']; //交易额
        $return['currency'] = 'CNY'; //币种

		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($return);

		//对待签名参数数组排序
		$return = $this->argSort($para_filter);

		//生成签名结果
		$mysign = $this->buildMysign($return, $payment['M_PartnerKey']);

		//签名结果与签名方式加入请求提交参数组中
		$return['sign'] = $mysign;

		return $return;
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
	 * @param 获取配置参数
	 */
	public function configParam()
	{
		$result = array(
			'M_PartnerId'  => '易极付商户ID',
			'M_PartnerKey' => '易极付商户密钥',
			'M_Email'      => '绑定账号(邮箱，手机号)',
		);
		return $result;
	}
}