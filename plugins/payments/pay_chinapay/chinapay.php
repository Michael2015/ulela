<?php
/**
 * @copyright Copyright(c) 2015 www.aircheng.com
 * @file  chinapay.php
 * @brief 银联在线支付接口
 * @author dabao
 * @date 2015-06-01
 * @version 0.1
 */

 /**
 * @class chinapay
 * @brief 银联在线支付接口
 */

//加载 netpayclient 组件
include_once(dirname(__FILE__)."/netpayclient.php");
class chinapay extends paymentPlugin
{

    public $name 	= '银联在线支付';//插件名称

    const PRI_KEY = 'key/MerPrK.key'; //私钥文件，在CHINAPAY申请商户号时获取，请相应修改此处，可填相对路径
    const PUB_KEY = 'key/PgPubk.key';//公钥文件

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
	public function getSubmitUrl()
	{
		//return 'http://payment-test.ChinaPay.com/pay/TransGet'; //测试环境请求地址
		return 'https://payment.chinaPay.com/pay/TransGet'; //生产环境
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
		//导入公钥文件
		$flag = buildKey(self::PUB_KEY);
		if(!$flag)
		{
			exit ("导入公钥文件失败！");
		}
		//获取交易应答的各项值
		$merid = $callbackData["merid"];
		$orderno = $callbackData["orderno"];
		$transdate = $callbackData["transdate"];
		$amount = $callbackData["amount"];
		$currencycode = $callbackData["currencycode"];
		$transtype = $callbackData["transtype"];
		$status = $callbackData["status"];
		$checkvalue = $callbackData["checkvalue"];
		$gateId = $callbackData["GateId"];
		$priv1 = $callbackData["Priv1"];

		//验证签名值，true 表示验证通过
		$flag = verifyTransResponse($merid, $orderno, $amount, $currencycode, $transdate, $transtype, $status, $checkvalue);
		if(!$flag)
		{
			$message = "验证签名失败";
		}
		else
		{
			//交易状态为1001表示交易成功，其他为各类错误，如卡内余额不足等
			if ($status == '1001')
			{
				$orderNo = $callbackData['Priv1'];//订单号,参考getSendData();
				$money   = $amount/100;
				//记录回执流水号
				if(isset($callbackData['orderno']) && $callbackData['orderno'])
				{
					$this->recordTradeNo($orderNo,$callbackData['orderno']);
				}
				return true;

			}
			else
			{
				$message = "交易失败！";
			}
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
		//导入私钥文件, 返回值即为您的商户号，长度15位
		$merid = buildKey(self::PRI_KEY);
		if(!$merid)
		{
			exit ("导入私钥文件失败！");
		}
		$return = array(
			'MerId' => $merid,
			'CuryId' => "156",////货币代码，3位，境内商户固定为156，表示人民币
			'TransDate' =>  date('Ymd'),//订单日期，本例采用当前日期
			'TransType' => '0001',//交易类型，0001 表示支付交易，0002 表示退款交易
			'Version' => '20141120',//接口版本号，全报文签名接口版本为20141120
			'PageRetUrl' => $this->callbackUrl,//页面返回地址(您服务器上可访问的URL)，最长80位，当用户完成支付后，银行页面会自动跳转到该页面，并POST订单结果信息
			'BgRetUrl' => $this->serverCallbackUrl,//后台返回地址(您服务器上可访问的URL)，最长80位，当用户完成支付后，我方服务器会POST订单结果信息到该页面
			'GateId' => '',//支付网关号，4位，上线时建议留空，以跳转到银行列表页面由用户自由选择，选用0001农商行网关便于测试
		);
		$return['Priv1'] = $payment['M_OrderNO'];//备注，最长60位，交易成功后会原样返回，可用于额外的订单跟踪等
		$return['OrdId'] = padstr($payment['M_OrderId'],16);	//商户订单号,定长16位
		$return['TransAmt'] = padstr($payment['M_Amount']*100,12);		//订单金额，定长12位，以分为单位，不足左补0

		// 签名
		//按次序组合订单信息为待签名串
		$plain = $return['MerId'] . $return['OrdId'] . $return['TransAmt'] . $return['CuryId'] . $return['TransDate'] . $return['TransType'] . $return['Version'] . $return['BgRetUrl'] . $return['PageRetUrl'] . $return['GateId'] . $return['Priv1'];
		//生成签名值
		$chkvalue = sign($plain);
		if (!$chkvalue)
		{
			exit ("签名失败！");
		}
		$return['ChkValue'] = $chkvalue;
        return $return;
	}

	/**
	 * @param 获取配置参数
	 */
	public function configParam()
	{
		$result = array(
			'M_merId'  => '商户代码（merId）',
		);
		return $result;
	}
}