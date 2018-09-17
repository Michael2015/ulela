<?php
/**
 * @class yijifu_scan_wechat
 * @brief 易极付扫一扫微信支付
 * @date 2016/4/7 15:45:40
 */
class yijifu_scan_wechat extends paymentPlugin
{
	//支付插件名称
    public $name = '易极付微信扫码支付';
    public $e_preid = '077'; //e码头订单前缀

	/**
	 * @see paymentplugin::getSubmitUrl()
	 */
	public function getSubmitUrl()
	{
		return 'https://api.yiji.com';
		//return 'https://openapi.yijifu.net/gateway.html';
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
		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($callbackData);

		//对待签名参数数组排序
		$para_sort = $this->argSort($para_filter);

		//生成签名结果
		$mysign = $this->buildMysign($para_sort,Payment::getConfigParam($paymentId,'M_PartnerKey'));

		if($callbackData['sign'] == $mysign)
		{
			if ($callbackData['success'])
			{
				//回传数据
				if ($callbackData['outOrderNo']) 
				{
					$orderNo = substr($callbackData['outOrderNo'], 3);
				}

				//dyg_jzw 20160728 从数据库读取支付总金额
				$orderDB  = new IModel('order');
				$orderRow = $orderDB->getObj("order_no = '".$orderNo."'");
				$money = $orderRow['order_amount'];

				//记录等待发货流水号
				if(($callbackData['tradeStatus'] == 'trade_finished' || $callbackData['tradeStatus'] == 'wait_seller_send_goods') && isset($callbackData['tradeNo']))
				{

					if ($callbackData['tradeNo'])
					{
						$this->recordTradeNo($orderNo, $callbackData['tradeNo']);

						return true;
					}
					else
					{
						$message = 'tradeNo为空';

						$smtp  = new SendMail();
						$smtp->send('263436934@qq.com', '易极付支付订单tradeNo为空 order:'.$orderNo, json_encode($callbackData));
					}
					
				}
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
	 * @see paymentplugin::getSendData()
	 */
	public function getSendData($payment)
	{
		$return = array();

		//基本参数
		$return['service'] = 'commonWchatTrade';
		$return['partnerId'] = $payment['M_PartnerId'];
		$return['sellerUserId'] = $payment['M_PartnerId'];
		$return['signType'] = 'MD5';
		$return['version'] = '1.0';

		//$return['returnUrl'] = $this->callbackUrl; //页面成功跳转返回URL
		$return['notifyUrl'] = $this->serverCallbackUrl; //异步通知URL

		//业务参数
		$return['tradeName'] = '阳光全球购';
		$return['orderNo'] = $payment['M_OrderNO'].'_'.rand(0,999999);
		$return['outOrderNo'] = $this->e_preid . $payment['M_OrderNO'];
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
			
			//组合商品 获取商品信息
			if (strpos($goods_array['goodsno'], "-"))
            {
				$goodsObj = new IModel('goods');
				$productObj = new IModel('products');
		
                //查询组合的市场价
                $_productRow = $productObj->getObj("goods_id ='".$_orderGoods['goods_id']."' and products_no='".$goods_array['goodsno']."'", "*", "id DESC");

                if ($_productRow) //是否存在多规格的货品
                {
                    $_group_market_price = $_productRow['market_price'];
                }
                else //单一组合商品, 查询goods表
                {
                    $_goodsRow = $goodsObj->getObj("id ='".$_orderGoods['goods_id']."' and goods_no='".$goods_array['goodsno']."' and is_del = 0");
                    $_group_market_price = $_goodsRow['market_price'];
                }

                $_goods_list = explode("|", $goods_array['goodsno']);

                foreach ($_goods_list as $_item)
                {
                    $_goods_row = explode("-", $_item);
                    $_child_goods_no = $_goods_row[0];
                    $_child_goods_nums = $_goods_row[1];

                    //查询商品价格和税率
                    $_child_goods = $goodsObj->getObj("goods_no = '{$_child_goods_no}' and is_del = 0");

					//添加商品明细
					$goodsClauses[] = array(
                                'outId' => trim($_child_goods_no), //商品的外部ID
                                'name' => trim($_child_goods['name']), //商品名称
                                'price' => round( $_child_goods['market_price'] / $_group_market_price * $_orderGoods['real_price'], 2), //商品单价
                                'quantity' => $_orderGoods['goods_nums'] * $_child_goods_nums, //商品数量
                            );
                }
            }
			else
			{
				$goodsClauses[] = array(
                                'outId' => $goods_array['goodsno'], //商品的外部ID
                                'name' => rtrim($goods_array['name'].' '.$goods_array['value']), //商品名称
                                'price' => $_orderGoods['real_price'], //商品单价
                                'quantity' => $_orderGoods['goods_nums'], //商品数量
                            );
			}
			
			
		}
		$return['goodsClauses'] = json_encode($goodsClauses);

		$return['tradeAmount'] = $orderRow['real_amount']; //交易额
        $return['currency'] = 'CNY'; //币种
        $return['userEndIp'] = $_SERVER['REMOTE_ADDR']; //客户端ip

		//除去待签名参数数组中的空值和签名参数
		$para_filter = $this->paraFilter($return);

		//对待签名参数数组排序
		$return = $this->argSort($para_filter);

		//生成签名结果
		$mysign = $this->buildMysign($return, $payment['M_PartnerKey']);

		//签名结果与签名方式加入请求提交参数组中
		$return['sign'] = $mysign;

		$result = $this->sendPost($this->getSubmitUrl(), $return);

		return json_decode($result, true);
	}

	/**
	 * @see paymentplugin::doPay()
	 */
	public function doPay($sendData)
	{
		if(isset($sendData['scanCodeImageUrl']) && $sendData['scanCodeImageUrl'])
		{
			$sendData['code_img'] = 'http://s.jiathis.com/qrcode.php?url='.urlencode($sendData['scanCodeImageUrl']);
			$sendData['url'] = IUrl::getHost().IUrl::creatUrl('/ucenter/order');
			include(dirname(__FILE__).'/template/pay.php');
		}
		else
		{
			$message = $sendData['resultMessage'] ? $sendData['resultMessage'] : '微信下单API接口失败';
			die($message);
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