<?php
/**
 * @copyright Copyright(c) 2011 jooyea.cn
 * @file pay_point.php
 * @brief 账户积分支付接口
 * @author dyg_jzw
 * @date 2015-10-14
 * @version 0.6
 * @note
 */

 /**
 * @class point
 * @brief 账户积分支付接口
 */
class point extends paymentPlugin
{
	//插件名称
    public $name = '账户积分支付';

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
    public function getSubmitUrl()
    {
    	return IUrl::getHost() . IUrl::creatUrl('/ucenter/payment_point');
    }

	/**
	 * @see paymentplugin::getSendData()
	 */
    public function getSendData($payment)
    {
    	$partnerId  = $payment['M_PartnerId'];
    	$partnerKey = $payment['M_PartnerKey'];
		$user_id    = ISafe::get('user_id');

		$return['attach']     = $payment['M_Paymentid'];
		$return['total_fee']  = $payment['M_Amount'];
		$return['order_no']   = $payment['M_OrderNO'];
		$return['return_url'] = $this->callbackUrl;

		$urlStr = '';

		ksort($return);
		foreach($return as $key => $val)
		{
			$urlStr .= $key.'='.urlencode($val).'&';
		}

		$encryptKey = isset(IWeb::$app->config['encryptKey']) ? IWeb::$app->config['encryptKey'] : 'iwebshop';
		$urlStr .= $user_id . $partnerKey . $encryptKey;
		$return['sign'] = md5($urlStr);

        return $return;
    }

	/**
	 * @see paymentplugin::callback()
	 */
    public function callback($ExternalData,&$paymentId,&$money,&$message,&$orderNo)
    {
        $partnerKey = Payment::getConfigParam($paymentId,'M_PartnerKey');
        $user_id    = ISafe::get('user_id');

		ksort($ExternalData);

		$temp = array();
        foreach($ExternalData as $k => $v)
        {
            if($k!='sign')
            {
                $temp[] = $k.'='.urlencode($v);
            }
        }

        $encryptKey = isset(IWeb::$app->config['encryptKey']) ? IWeb::$app->config['encryptKey'] : 'iwebshop';
        $testStr = join('&',$temp).'&'.$user_id.$partnerKey.$encryptKey;

        $orderNo = $ExternalData['order_no'];
        $money   = $ExternalData['total_fee'];

        if($ExternalData['sign'] == md5($testStr))
        {
            //支付单号
            switch($ExternalData['is_success'])
            {
                case 'T':
                {
                	return true;
                }
                break;

                case 'F':
                {
                	return false;
                }
                break;
            }
        }
        else
        {
        	$message = '校验码不正确';
        }
        return false;
    }

	/**
	 * @see paymentplugin::serverCallback()
	 */
    public function serverCallback($ExternalData,&$paymentId,&$money,&$message,&$orderNo){}

	/**
	 * @see paymentplugin::notifyStop()
	 */
    public function notifyStop(){}
}