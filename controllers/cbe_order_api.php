<?php
/**
 * @brief 跨境电商订单接口
 */
class Cbe_order_api extends IController
{
	private $session_key = "ee7ab2660dc7d11d2158e3ae371111bf"; //签名key

	/**
	 * @brief 自用订单接口
	 */
	public function index201603f45khirobk45njbv0egrk23r()
	{
		//获取校验参数
		$time = IFilter::act(IReq::get('time'));
		$sign = IFilter::act(IReq::get('sign'));

		//校验签名
		$check = $this->checkSign($time, $sign);
		if (! $check) 
		{
			echo 'fail:sign_wrong';
			exit();
		}

		//获取方法
		$method = IFilter::act(IReq::get('method'));

		//获取订单方法
		switch ($method) 
		{
		 	case 'getOrder':
		 		$this->getOrder(0);
		 		break;
		 	case 'getOrderWithPayment':
		 		$this->getOrder(1);
		 		break;
		 	case 'getNewOrder':
		 		$this->getOrder(0, 1);
		 		break;
		 	case 'getNewOrderWithPayment':
		 		$this->getOrder(1, 1);
		 		break;
		 	case 'doneHaiGuan':
		 		$this->doneHaiGuan();
		 		break;
		 	default:
		 		break;
		}
	}

	public function yi_payment_callback()
	{
		$cbeInstance = new Cbe();

		//执行接口回调函数
		$callbackData = array_merge($_POST,$_GET);
		unset($callbackData['controller']);
		unset($callbackData['action']);
		unset($callbackData['_id']);

		$return = $cbeInstance->yi_upload_payment_callback($callbackData);

		if ($return !== true) 
		{
			$smtp  = new SendMail();
			$smtp->send('263436934@qq.com', '易极付异步出错', json_encode($callbackData));
		}
		else
		{
			echo 'success';
		}
	}

	//监控器获取订单信息 is_payment 是否发送支付单
	private function getOrder($is_payment = 0, $is_new = 0)
	{
		//是否单独获取订单号
		$orderNo = IFilter::act(IReq::get('orderNo'));

		$orderObj  = new IModel('order');

		if ($orderNo) 
		{
			//单独获得订单号，补发支付单，并返回订单信息
			$orderRow  = $orderObj->getObj('order_no = "'.$orderNo.'"');

			if ($orderRow && $orderRow['is_cbe']) 
			{	
				$cbeInstance = new Cbe(true);
				if ($is_payment) 
				{
					//支付单重新上传
					$send_result = $cbeInstance->yi_upload_payment($orderRow);

					if ($send_result['success'] || strpos($send_result['resultMessage'], '支付单已上传')) 
					{
						//订单更新状态为支付单已上传
		                $orderObj->setData(array('is_cbe' => 2));
		                $orderObj->update('order_no = "'.$orderNo.'" and is_cbe = 1');
					}
					else
					{
						$smtp  = new SendMail();
						$smtp->send('263436934@qq.com', '支付单补发失败', json_encode($send_result));
						exit;
					}
				}

				//订单信息生成xml
				//文件内容 = MessageID+XMLContent
				if ($is_new) //海关统一版报文生成
				{
					echo $cbeInstance->new_sea_creat_XML($orderRow);
				}
				else
				{
					echo $cbeInstance->sea_creat_XML($orderRow);
				}
			}
			else
			{
				echo 'fail:no_order';
			}
		}
		else
		{
			//不指定单号，获取已发送支付单的订单
			$orderList  = $orderObj->query('status = 2 AND is_cbe = 2 AND if_del = 0', 'order_no');

			if ($orderList) 
			{
				$order_no_list = "";
				foreach ($orderList as $_order) 
				{
					$order_no_list .= ','.$_order['order_no'];
				}		

				echo substr($order_no_list, 1);
			}
		}
	}

	//监控器完成海关上传, 下一步发送订单给e码头
	private function doneHaiGuan()
	{
		//是否单独获取订单号
		$orderNo = IFilter::act(IReq::get('orderNo'));

		//删除前面三位
		$orderNo = substr($orderNo, 3);

		//查询订单信息
        $orderObj = new IModel('order');
        $orderRow = $orderObj->getObj("order_no = '".$orderNo."'");

		if (empty($orderNo) || empty($orderRow))
		{
			echo 'fail:no_order';
			exit();
		}

		//修改订单状态为已上传支付单+海关
        $orderObj->setData(array('is_cbe' => 3));
        $orderObj->update('order_no = "'.$orderNo.'"');

        //发送订单给e码头
		$cbeInstance = new Cbe(true);
		$result = $cbeInstance->e_send_order($orderRow);

		$xml = (array)simplexml_load_string($result);

		if ($xml['result'] == 'true') 
		{
			//发送成功，标记订单状态为 为已上传支付单+海关
			$orderObj->setData(array('is_cbe' => 4));
        	$orderObj->update('order_no = "'.$orderNo.'"');
		}
		else
		{
			//添加订单备注,发送失败
			$orderObj->setData(array('note' => $xml['notes']));
        	$orderObj->update('order_no = "'.$orderNo.'"');
		}

		echo (($xml['result']=='true')?'success':'fail:').$xml['notes'];

	}


	public function e_order_status_20160408_23rndgf90345yndgfn23rfsd()
	{
		$json = file_get_contents('php://input');

		$e_order = json_decode($json, true);

		if ($e_order['type'] == 'ORDER_UPDATE') 
		{
			//获取订单信息
			$order_no = substr($e_order['data']['externalNo'], 3);

			$orderDB = new IModel('order');
			$orderRow = $orderDB->getObj('order_no = "'.$order_no.'"');
			$order_id = $orderRow['id'];
				

			//1（运单号已绑定，发送海关中） 3（海关发送成功） 4（已拣货） 5（出货） 7（海关发送失败，等待重试 8（订单已取消） -1（退单）
			if ($e_order['data']['status'] == 3)
			{
				//发送短信
		    	$replaceData = array(
		    		'{user_name}'        => $orderRow['accept_name'],
		    		'{order_no}'         => $order_no
		    	);
		    	$mobileMsg = smsTemplate::sendHaiGuan($replaceData);
			}
			elseif ($e_order['data']['status'] == 5)
			{
				//处理物流公司选择
				switch ($e_order['data']['carrier']) 
				{
					case 'ZTO':
						$freight_id = 19;
						break;
					case 'YTO':
						$freight_id = 4;
						break;
					case 'STO':
						$freight_id = 2;
						break;
					case 'SF':
						$freight_id = 5;
						break;
					case 'EMS':
					case 'CPAM':
						$freight_id = 1;
						break;
				}

				$mail_no = $e_order['data']['waybillNo']; //快递单号

				//****** 本商城标记发货 *****
				//获取此订单所有关联商品
				$orderGoodsDB = new IModel('order_goods');
				$orderGoodsList = $orderGoodsDB->query("order_id = '".$order_id."'");
				$order_goods_relation = array();
				foreach($orderGoodsList as $key => $val)
				{
					$order_goods_relation[] = $val['id'];
				}

				//检查此订单是否存在未处理的退款申请
				$refundDB = new IModel('refundment_doc');
				$refundRow= $refundDB->getObj('order_no = "'.$order_no.'" and pay_status = 0 and if_del = 0');
				
				//有退款处理中的订单
				if($refundRow)
				{
					//发送邮件提醒管理员
					$smtp  = new SendMail();
					$error = $smtp->getError();

					if($error)
					{
						$return = array(
							'isError' => true,
							'message' => $error,
						);
						echo JSON::encode($return);
						exit;
					}

					$siteConfigObj = new Config("site_config");
					$site_config   = $siteConfigObj->getInfo();
					$email       = isset($site_config['email']) ? $site_config['email'] : 'jiangzhaowei@dyg.cn';
					$smtp->send($email, '【紧急】e码头发送了一单退款中的海外购订单！', '订单号：'.$order_no.'，已点击发货，请及时拦截！');
				}

				//添加发货单
			 	$paramArray = array(
			 		'order_id'      => IFilter::act($orderRow['id'],'int'),
			 		'user_id'       => IFilter::act($orderRow['user_id'],'int'),
			 		'name'          => IFilter::act($orderRow['accept_name'],'string'),
			 		'postcode'      => IFilter::act($orderRow['postcode'],'string'),
			 		'telphone'      => IFilter::act($orderRow['telphone'],'string'),
			 		'province'      => IFilter::act($orderRow['province'],'int'),
			 		'city'          => IFilter::act($orderRow['city'],'int'),
			 		'area'          => IFilter::act($orderRow['area'],'int'),
			 		'address'       => IFilter::act($orderRow['address'],'string'),
			 		'mobile'        => IFilter::act($orderRow['mobile'],'string'),
			 		'freight'       => IFilter::act($orderRow['real_freight'],'float'),
			 		'delivery_code' => $mail_no,
			 		'delivery_type' => IFilter::act($orderRow['distribution'],'string'),
			 		'note'          => $e_order['message'],
			 		'time'          => ITime::getDateTime(),
			 		'freight_id'    => $freight_id,
			 	);

			 	//获得delivery_doc表的对象
			 	$tb_delivery_doc = new IModel('delivery_doc');
			 	$tb_delivery_doc->setData($paramArray);
			 	$deliveryId = $tb_delivery_doc->add();

				//如果支付方式为货到付款，则减少库存
				if ($deliveryId) 
				{
					if($orderRow['pay_type'] == 0)
					{
					 	//减少库存量
					 	self::updateStore($order_goods_relation,'reduce');
					}

					//更新发货状态
				 	$sendStatus = 1;//全部发货
				 	foreach($order_goods_relation as $key => $val)
				 	{
				 		//标记发货
				 		$orderGoodsDB->setData(array(
				 			"is_send"     => 1,
				 			"delivery_id" => $deliveryId,
				 		));
				 		$orderGoodsDB->update(" id = {$val} ");
				 	}

				 	//更新订单发货状态
				 	$orderDB->setData(array
				 	(
				 		'distribution_status' => $sendStatus,
				 		'send_time'           => ITime::getDateTime(),
				 	));
				 	$orderDB->update('id='.$orderRow['id']);

				 	//发货员名字
				 	$sendorName = 'dyg_cn';
	 				$sendorSort = '管理员';

				 	//生成订单日志
			    	$tb_order_log = new IModel('order_log');
			    	$tb_order_log->setData(array(
			    		'order_id' => $orderRow['id'],
			    		'user'     => $sendorName,
			    		'action'   => '发货',
			    		'result'   => '成功',
			    		'note'     => '订单【'.$order_no.'】由【'.$sendorSort.'】'.$sendorName.'发货',
			    		'addtime'  => date('Y-m-d H:i:s')
			    	));
			    	$sendResult = $tb_order_log->add();

					//获取货运公司
			    	$freightDB  = new IModel('freight_company');
			    	$freightRow = $freightDB->getObj('id = '.$paramArray['freight_id']);

			    	//dyg_jzw 20150811 添加短信发送设置
					$config = new Config('site_config');
					$sms_send_config = explode(';', $config->sms_send_config);

					if($sms_send_config && in_array('sms_order_send', $sms_send_config))
					{
				    	//发送短信
				    	$replaceData = array(
				    		'{user_name}'        => $paramArray['name'],
				    		'{order_no}'         => $order_no,
				    		'{sendor}'           => '['.$sendorSort.']'.$sendorName,
				    		'{delivery_company}' => $paramArray['delivery_code']?$freightRow['freight_name']:'自提', //dyg_jzw 修订短信提醒
				    		'{delivery_no}'      => $paramArray['delivery_code'],
				    	);
				    	$mobileMsg = smsTemplate::sendGoods($replaceData);
				    	
				    }

			    	//同步发货接口，如支付宝担保交易等
			    	if($sendResult && $sendStatus == 1)
			    	{
			    		sendgoods::run($orderRow['id']);
			    	}
		    	}
			}
			else
			{
				//其他状态
				$siteConfigObj = new Config("site_config");
				$site_config   = $siteConfigObj->getInfo();
				$email       = isset($site_config['email']) ? $site_config['email'] : 'jiangzhaowei@dyg.cn';
				$smtp->send($email, 'e码头发送订单非正常状态', '订单号：' . $order_no . '，状态：' . $e_order['data']['status'] . '，提示信息：' . $e_order['message']);
			}

			if (isset($mobileMsg) && $mobileMsg)
			{
				Hsms::send($paramArray['mobile'], $mobileMsg, 0);
			}
		}
	}

	private function checkSign($time, $sign)
	{
		//时间误差不允许大于10分钟
		if (abs($time - time()) < 600 && $sign == md5('time='.$time.$this->session_key))
		{
			return true;
		}
		return false;
	}




}