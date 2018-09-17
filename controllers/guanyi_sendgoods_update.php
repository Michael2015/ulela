<?php
/**
 * @brief 管易发货同步器
 */
class Guanyi_sendgoods_update extends IController
{
	/*public function test()
	{
		$orderNo = '20170112095359426955';
		$moreOrder = Order_Class::getBatch($orderNo);

		var_dump($moreOrder);
		var_dump(array_sum($moreOrder));

		//if($money >= array_sum($moreOrder))
		//{
			foreach($moreOrder as $key => $item)
			{
				$order_id = Order_Class::updateOrderStatus($key);
				if(!$order_id)
				{
					//IError::show(403,'订单修改失败');
					$message = '订单修改失败';
				}
			}
			$message = '支付成功';
			//$this->redirect('/site/success/message/'.urlencode("支付成功").'/?callback=/ucenter/order');
			//return;
		//}
		//else
		//{
			//$message = '付款金额与订单金额不符合';
		//}
		
		echo $message;
	}*/

	public function add_yangguang()
	{
		$_post = IReq::get('data');

		if ($_post)
		{
			$lines = explode("\n", $_post);

			foreach ($lines as $_item)
			{
				$_row = explode(",", $_item);

				$username = trim($_row[0]);
				$password = trim($_row[1]);

				$is_success = true;

				if(IValidate::mobi($username) == false)
				{
					$message = "手机号格式不正确";
					$is_success = false;
				}
				else
				{
					$userObj = new IModel('user');
					$userRow = $userObj->getObj("username = '{$username}'");
					if($userRow)
					{
						//此手机号已经被注册, 如果是普通会员则升级成为阳光会员V1
						$memberObj = new IModel('member');
						$memberRow = $memberObj->getObj("user_id = ".$userRow['id']);

						if ($memberRow['group_id'] == 13 || empty($memberRow['group_id']))
						{
							$updateData = array(
												'exp' => '900',
												'group_id' => '14'
											);
							$memberObj->setData($updateData);
							$memberObj->update('user_id ='.$userRow['id']);

							$message = "已注册，现在升级为阳光会员V1";
						}
						else
						{
							$message = "已注册，无需升级";
							$is_success = false;
						}
					}
					else
					{
						//插入user表
						$userArray = array(
							'username' => $username,
							'password' => md5($password)
						);
						$userObj->setData($userArray);
						$user_id = $userObj->add();
						if(!$user_id)
						{
							$message = "用户创建失败";
							$is_success = false;
						}
						else
						{
							//插入member表
							$memberArray = array(
								'user_id' => $user_id,
								'time'    => ITime::getDateTime(),
								'status'  => 1,
								'mobile'  => $username, //dyg_jzw 20160604 注册用户名修改为手机号
								'exp' => '900',
								'group_id' => '14'
							);
							$memberObj = new IModel('member');
							$memberObj->setData($memberArray);
							$memberObj->add();

							$message = "创建成功";

						}
					}
				}

				//发送短信
				if ($is_success)
				{
					$smsContent = "恭喜您在线下活动登记的账号已成功开通！并已升级为价值199元的阳光会员，享受护肤品、全球购、保健品等全场折扣特权！ 网址: mall.dyg.cn（可登陆修改密码，忘记密码请自行找回）";
					$send_result = Hsms::send($username, $smsContent, 0);
					if($send_result == 'success')
					{
						$message .= " 已发送通知短信";
					}
				}
		
				echo '<p>'.$username." - ".$message."</p>";

			}
		}
		else
		{
			echo '<html>
				<head>
					<meta http-equiv="content-type" content="text/html;charset=utf-8" />
				</head> 
				<body>
					<form action="" method="post" target="_blank">
						<textarea name="data" rows="10" width="600"></textarea><br>
						<p>格式(一行一账号): username,password</p><br>
						<input type="submit" value="submit">
					</form>
				</body>
			</html>';
		}
	}


	//运行发送团队业绩信息
	public function test_20160907()
	{
		//...		
	}

	public function send_team_score()
	{
		//调用插件
    	$result = plugin::trigger("sendTeamScore");
	}

	public function count_sells_by_cron()
	{
		//调用插件
    	$result = plugin::trigger("doCountSells");
	}

	//自动更新订单状态 插件触发
	public function auto_update_order()
	{
		echo 'done';
	}

	//获取跨境电商已发货订单
	public function get_cbe_delivery_20161008()
	{
		$start = date('Y-m-d H:i:s', strtotime($_GET['start']));
		$end = date('Y-m-d H:i:s', strtotime($_GET['end']));

		$deliveryDB = new IQuery('delivery_doc as dd, order as o');
		$deliveryDB->fields = "o.order_no, o.mobile, o.order_amount, o.pay_time, o.send_time, og.*";
		$deliveryDB->where = "dd.order_id = o.id and dd.time > '{$start}' and dd.time < '{$end}' and o.is_cbe > 0";
		$deliveryDB->join = "left join order_goods as og on og.order_id = o.id";


		$list = $deliveryDB->find();

		$result = array();

		if ($list)
		{
			
			foreach ($list as $_item)
			{
				if (! isset($result[$_item['order_no']]))
				{
					$result[$_item['order_no']]['order_no'] = $_item['order_no'];
					$result[$_item['order_no']]['mobile'] = $_item['mobile'];
					$result[$_item['order_no']]['order_amount'] = $_item['order_amount'];
					$result[$_item['order_no']]['pay_time'] = $_item['pay_time'];
					$result[$_item['order_no']]['send_time'] = $_item['send_time'];
				}
				$result[$_item['order_no']]['goods'][] = $_item;
			}
		}

		echo json_encode($result);

	}

	/**
	 * @brief 管易发货同步器
	 */
	public function index20160217()
	{
		$part_size = 40;

		$mem_cache = new ICache('memcache');
        $has_run = intval($mem_cache->get('guanyi_update_sended'));

        if ($has_run)
        {
        	return true;
        }
        else
        {
        	$mem_cache->set('guanyi_update_sended', 1, 60*2);
        }

		//dyg_jzw 20160216 订单查询状态是否已发货
		//获取已添加到管易，而且未发货的订单
		$orderModel = new IModel('order');
		$gy_ready_orders = $orderModel->query('sended_guanyi = 1 AND distribution_status = 0 AND status in (2,5,7)', '*', 'id', 'asc');

		if (! $gy_ready_orders)
		{
			return true;
		}

		$arr_part = ceil(count($gy_ready_orders) / $part_size);
		$now_minute = intval(date("i")/2);

		$index = $now_minute % $arr_part;

		$gy_ready_orders_part = array_slice($gy_ready_orders, $index * $part_size, $part_size);

		foreach ($gy_ready_orders_part as $orderRow)
		{
			$order_no = $orderRow['order_no'];
			
			//向管易查询是否已发货
			$gy_result = Guanyi::getSendOrder($order_no);

			if ($gy_result['success'] && ! empty($gy_result['deliverys'])) 
			{

				$gy_order = $gy_result['deliverys'][0]; //订单信息

				if ($gy_order['delivery_statusInfo']['delivery'] == 2) //已发货
				{
					echo "send_order: {$order_no}\n";

					$gy_deliverys = $gy_order; //发货信息

					//处理物流公司选择
					switch ($gy_deliverys['express_code']) 
					{
						case 'FEDEX':
							$freight_id = 17;
							break;
						case 'YTO':
							$freight_id = 4;
							break;
						case 'STO':
							$freight_id = 2;
							break;
						case 'SF':
						case 'SFDF':
							$freight_id = 5;
							break;
						case 'DUMMY':
							$freight_id = 0;
							break;
						case 'EMS':
						case 'EYB':
							$freight_id = 1;
							break;
					}

					//****** 本商城标记发货 *****
					//获取此订单所有关联商品
					$orderGoodsDB = new IModel('order_goods');
					$orderGoodsList = $orderGoodsDB->query('order_id = '.$orderRow['id']);
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
						$smtp->send($email, '【紧急】东阳光商城发送了一单退款中的订单！', '订单号：'.$order_no.'，已点击发货，请及时拦截！');
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
				 		'delivery_code' => IFilter::act($gy_deliverys['express_no'],'string'),
				 		'delivery_type' => IFilter::act($orderRow['distribution'],'string'),
				 		'note'          => IFilter::act($gy_order['seller_memo'],'string').' '.IFilter::act($gy_order['seller_memo_late'],'string'),
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
					 	$orderModel->setData(array
					 	(
					 		'distribution_status' => $sendStatus,
					 		'send_time'           => ITime::getDateTime(),
					 	));
					 	$orderModel->update('id='.$orderRow['id']);

					 	//发货员名字
					 	$sendorName = $gy_order['deliverys']['warehouse_name'];
		 				$sendorSort = $gy_order['deliverys']['delivery_statusInfo']['delivery_name'];

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
					    	Hsms::send($paramArray['mobile'],$mobileMsg,0);
					    }

				    	//同步发货接口，如支付宝担保交易等
				    	if($sendResult && $sendStatus == 1)
				    	{
				    		sendgoods::run($orderRow['id']);
				    	}
			    	}
				}
			}
			else if(isset($gy_result['success']) && ! $gy_result['success'] && $gy_result['errorCode'] != 'ERR')
			{
				//发货失败
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
				$smtp->send($email, '管易已发货订单同步商城获取失败', json_encode($gy_result));
			}
		}

		$mem_cache->set('guanyi_update_sended', 0, 60*2);
	}

	public function updatestock20160229()
	{
		$page = 1;

		$goodsObj      = new IModel('goods');
		$productObj    = new IModel('products');

		//床垫和马桶不同步
		$unupdate_pre = array(
								'1203', //马桶编码前缀
								'1104', //床垫编码前缀
							); 

		$goods_id_array = array();

		$product_goods_stocks = array();

		while (1)
		{
			$gy_result = Guanyi::getStock($page);

			if (! $gy_result['success']) //获取失败
			{
				echo 'Get Fail! Msg:';
				print_r($gy_result);
				break;
			}

			//库存处理
			foreach ($gy_result['stocks'] as $_stock) 
			{
				//不同步的编码
				$is_unupdate = false;
				foreach ($unupdate_pre as $_unupdate)
				{
					if(strpos($_stock['item_code'], $_unupdate) === 0)
					{
						$is_unupdate = true;
						break;
					}
				}

				if ($is_unupdate)
				{
					continue;
				}

				if ($_stock['del'] != 1) //状态正常
				{
					//更新product
					$productRow = $productObj->query("products_no = '".$_stock['item_code']."'",'id, goods_id, store_nums');

					//修复负库存导致的库存提示错误
					if ($_stock['salable_qty'] < 0)
					{
						$_stock['salable_qty'] = 0;
					}

					//有goods的商品
					$goodsRow =  $goodsObj->query("goods_no = '".$_stock['item_code']."'");

					if ($goodsRow)
					{
						//初始库存
						$goodsObj->setData(array('store_nums' => $_stock['salable_qty']));
						$goodsObj->update("goods_no = '".$_stock['item_code']."'",'store_nums');
					}
					if ($productRow) //更新product 并goods叠加数量
					{
						foreach ($productRow as $_p)
						{
							$productObj->setData(array('store_nums' => intval($_stock['salable_qty'])));
							$productObj->update('id = '.$_p['id'],'store_nums');

							if (! isset($product_goods_stocks[$_p['goods_id']]))
							{
								$product_goods_stocks[$_p['goods_id']] = 0;
							}

							$product_goods_stocks[$_p['goods_id']] += $_stock['salable_qty'];
						}
					}
				}
			}

			//是否翻页
			if ($gy_result['total'] > $page * 100) 
			{
				$page++;
			}
			else
			{
				break;
			}

		}

		if ($product_goods_stocks)
		{
			foreach ($product_goods_stocks as $_goods_id => $_nums)
			{
				$goodsObj->setData(array('store_nums' => $_nums));
				$goodsObj->update("id = '".$_goods_id."'",'store_nums');
			}
		}

		//触发到货提醒
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_TIMEOUT,1);

		$url = IUrl::getHost().IUrl::creatUrl("/message/notify_email_send"); 
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_exec($ch);

		$url = IUrl::getHost().IUrl::creatUrl("/message/notify_sms_send"); 
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_exec($ch);
		curl_close($ch);

	}
}