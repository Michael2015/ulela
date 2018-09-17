<?php
/**
 * @copyright Copyright(c) 2017 dyg.cn
 * @file pay_pointpay.php
 * @brief 账户积分支付接口
 * @author dyg_jzw
 * @date 2017-02-20
 * @version 1.0
 * @note
 */

 /**
 * @class point
 * @brief 账户积分支付接口
 */
class pointpay extends paymentPlugin
{
    //插件名称
    public $name = '账户积分支付';

    /**
     * @see paymentplugin::getSubmitUrl()
     */
    public function getSubmitUrl()
    {
        return IUrl::getHost() . IUrl::creatUrl('/ucenter/payment_pointpay');
    }

    /**
     * @see paymentplugin::getSendData()
     */
    public function getSendData($payment)
    {
        $partnerId  = $payment['M_PartnerId'];
        $partnerKey = $payment['M_PartnerKey'];

        $return['attach']     = $payment['M_BatchOrderNO'];
        $return['total_fee']  = $payment['M_Amount'];
        $return['order_no']   = $payment['M_OrderNO'];
        $return['order_id']   = $payment['M_OrderId'];

        $urlStr = '';

        ksort($return);
        foreach($return as $key => $val)
        {
            $urlStr .= $key.'='.urlencode($val).'&';
        }

        $encryptKey = isset(IWeb::$app->config['encryptKey']) ? IWeb::$app->config['encryptKey'] : 'iwebshop';
        $urlStr .= $partnerKey . $encryptKey;
        $return['sign'] = md5($urlStr);

        return $return;
    }

    /**
     * @see paymentplugin::callback()
     */
    public function callback($ExternalData,&$paymentId,&$money,&$message,&$orderNo)
    {
        $partnerKey = Payment::getConfigParam($paymentId,'M_PartnerKey');

        if(stripos($ExternalData['order_no'],'recharge') !== false)
        {
            $message = '积分支付方式不能用于在线充值';
            return false;
        }
        if(!$ExternalData['order_no'] || !$ExternalData['order_id'] || !$ExternalData['attach'] || !$ExternalData['total_fee'] || !$ExternalData['sign'] || $ExternalData['total_fee'] < 0)
        {
            $message = '缺少必要参数';
            return false;
        }

        //dyg_jzw 20170220 是否存在不允许积分支付的商品
        $query = new IQuery('order_goods as og');
        $query->join = "left join goods as go on go.id = og.goods_id";
        $query->where = "og.order_id = ".$ExternalData['order_id'];
        $query->fields = "go.is_point";
        $goodsIsPoint = $query->find();

        $is_point = 1;
        foreach ($goodsIsPoint as $_goods_is_point)
        {
            if (! $_goods_is_point['is_point'])
            {
                $is_point = 0;
                break;
            }
        }

        if (!$is_point)
        {
            $message = '存在不支持积分支付的商品';
            return false;
        }

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
        $testStr = join('&',$temp).'&'.$partnerKey.$encryptKey;

        $orderNo = $ExternalData['order_no'];
        $money   = $ExternalData['total_fee'];

        if($ExternalData['sign'] == md5($testStr))
        {
                    $this->recordTradeNo($orderNo,$orderNo);
                    return true;

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
    public function serverCallback($ExternalData,&$paymentId,&$money,&$message,&$orderNo)
    {
        return $this->callback($ExternalData,$paymentId,$money,$message,$orderNo);
    }

    /**
     * @see paymentplugin::notifyStop()
     */
    public function notifyStop()
    {
        echo "success";
    }
}