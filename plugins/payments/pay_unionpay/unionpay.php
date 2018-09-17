<?php
/**
 * @copyright Copyright(c) 2015 www.aircheng.com
 * @file  unionpay.php
 * @brief 中国银联支付接口
 * @author dabao
 * @date 2015-05-25
 * @version 0.1
 */

 /**
 * @class unionpay
 * @brief 中国银联支付接口
 */
include_once(dirname(__FILE__)."/common.php");
class unionpay extends paymentPlugin
{

    public $name = '中国银联';//插件名称

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
	public function getSubmitUrl()
	{
		//return 'https://101.231.204.80:5000/gateway/api/frontTransReq.do'; //测试环境请求地址
		return 'https://gateway.95516.com/gateway/api/frontTransReq.do'; //生产环境
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
		if (isset ( $callbackData['signature'] ))
		{
			if (Common::verify ( $callbackData ))
			{
				if($callbackData['respCode'] == "00")
				{
					$orderNo = $callbackData['orderId'];//订单号
					$money   = $callbackData['txnAmt']/100;

					//记录回执流水号
					if(isset($callbackData['queryId']) && $callbackData['queryId'])
					{
						$this->recordTradeNo($orderNo,$callbackData['queryId']);
					}
					return true;
				}
				$message = '状态码不正确:'.$callbackData['respCode'];
			}
			else
			{
				$message = '签名不正确';
			}
		}
		else
		{
			$message = '签名为空';
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
		Common::setCertPwd($payment['M_certPwd']);
		$return = array(
			'version' => '5.0.0',				//版本号
			'encoding' => 'utf-8',				//编码方式
			'certId' => Common::getSignCertId (),			//证书ID
			'txnType' => '01',				//交易类型
			'txnSubType' => '01',				//交易子类 01消费
			'bizType' => '000201',				//业务类型
			'frontUrl' =>  $this->callbackUrl,//SDK_FRONT_NOTIFY_URL,  		//前台通知地址
			'backUrl' => $this->serverCallbackUrl,//SDK_BACK_NOTIFY_URL,		//后台通知地址
			'signMethod' => '01',		//签名方法
			'channelType' => '07',		//渠道类型，07-PC，08-手机
			'accessType' => '0',		//接入类型
			'merId' => $payment['M_merId'],		        //商户代码，请改自己的测试商户号
			'currencyCode' => '156',	//交易币种
			'defaultPayType' => '0001',	//默认支付方式
			'txnTime' => date('YmdHis')	//订单发送时间
		);

		$return['orderId']     = $payment['M_OrderNO'];	//商户订单号
		$return['txnAmt']      = $payment['M_Amount']*100;		//交易金额，单位分
		$return['reqReserved'] = $payment['M_OrderId'];	//订单发送时间'透传信息'; //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现
		// 签名
		Common::sign ( $return );
        return $return;
	}

	/**
	 * @param 获取配置参数
	 */
	public function configParam()
	{
		$result = array(
			'M_merId'  => '商户代码（merId）',
			'M_certPwd' => '签名证书密码(certPwd)',
		);
		return $result;
	}
}
