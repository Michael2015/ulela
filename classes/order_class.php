<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file Order_Class.php
 * @brief 订单中相关的
 * @author relay
 * @date 2011-02-24
 * @version 0.6
 */
class Order_Class
{
	/**
	 * @brief 产生订单ID
	 * @return string 订单ID
	 */
	public static function createOrderNum()
	{
		$newOrderNo = date('YmdHis').rand(100000,999999);
		$orderDB = new IModel('order');
		if($orderDB->getObj('order_no = "'.$newOrderNo.'"'))
		{
			return self::createOrderNum();
		}
		return $newOrderNo;
	}

	/**
	 * 添加评论商品的机会
	 * @param $order_id 订单ID
	 */
	public static function addGoodsCommentChange($order_id)
	{
		//获取订单对象
		$orderDB  = new IModel('order');
		$orderRow = $orderDB->getObj('id = '.$order_id);

		//获取此订单中的商品种类
		$orderGoodsDB        = new IQuery('order_goods');
		$orderGoodsDB->where = 'order_id = '.$order_id.' and is_send = 1';
		$orderGoodsDB->group = 'goods_id';
		$orderList           = $orderGoodsDB->find();

		//可以允许进行商品评论
		$commentDB = new IModel('comment');
		$goodsDB   = new IModel('goods');

		//对每类商品进行评论开启
		foreach($orderList as $val)
		{
			$issetGoods = $goodsDB->getObj('id = '.$val['goods_id']);
			if($issetGoods)
			{
				$attr = array(
					'goods_id' => $val['goods_id'],
					'order_no' => $orderRow['order_no'],
					'user_id'  => $orderRow['user_id'],
					'time'     => ITime::getDateTime(),
					'seller_id'=> $val['seller_id'],
				);
				$commentDB->setData($attr);
				$commentDB->add();
			}
		}
	}

	/**
	 * 支付成功后修改订单状态
	 * @param $orderNo  string 订单编号
	 * @param $admin_id int    管理员ID
	 * @param $note     string 收款的备注
	 * @return false or int order_id
	 */
	public static function updateOrderStatus($orderNo,$admin_id = '',$note = '')
	{
		//获取订单信息
		$orderObj  = new IModel('order');
		$orderRow  = $orderObj->getObj('order_no = "'.$orderNo.'"');

		if(empty($orderRow))
		{
			return false;
		}

		if($orderRow['pay_status'] == 1)
		{
			return $orderRow['id'];
		}
		else if($orderRow['pay_status'] == 0)
		{
			$dataArray = array(
				'status'     => ($orderRow['status'] == 5) ? 5 : 2,
				'pay_time'   => ITime::getDateTime(),
				'pay_status' => 1,
			);
			$orderRow['pay_time'] = $dataArray['pay_time']; //dyg_jzw 20160217 增加支付时间(用于管易)

			$orderObj->setData($dataArray);
			$is_success = $orderObj->update('order_no = "'.$orderNo.'"');
			if($is_success == '')
			{
				return false;
			}

			//删除订单中使用的道具
			$ticket_id = trim($orderRow['prop']);
			if($ticket_id != '')
			{
				$propObj  = new IModel('prop');
				$propData = array('is_userd' => 1);
				$propObj->setData($propData);
				$propObj->update('id = '.$ticket_id);
                //微信卡券的核销
                plugin::trigger('consumeCode',$orderNo);

			}

			//注册用户进行奖励
			if($orderRow['user_id'])
			{
				$user_id = $orderRow['user_id'];

				//获取用户信息
				$memberObj  = new IModel('member');
				$memberRow  = $memberObj->getObj('user_id = '.$user_id,'prop,group_id');

				//(1)删除订单中使用的道具
				if($ticket_id != '')
				{
					$finnalTicket = str_replace(','.$ticket_id.',',',',','.trim($memberRow['prop'],',').',');
					$memberData   = array('prop' => $finnalTicket);
					$memberObj->setData($memberData);
					$memberObj->update('user_id = '.$user_id);
				}

				if($memberRow)
				{
					//(2)进行促销活动奖励
			    	$proObj = new ProRule($orderRow['real_amount'],$orderRow['seller_id']);
			    	$proObj->setUserGroup($memberRow['group_id']);
			    	$proObj->setAward($user_id);

			    	//dyg_jzw 20150806
			    	//(3)增加经验值 & 经验值记录
					$expConfig = array(
						'user_id' => $user_id,
						'exp'   => $orderRow['exp'],
						'log'     => '成功购买了订单号:'.$orderRow['order_no'].'中的商品，增加经验值'.$orderRow['exp']
					);
					$expObj = new Exp();
					$expObj->update($expConfig);

					//(4)增加积分
					$pointConfig = array(
						'user_id' => $user_id,
						'point'   => $orderRow['point'],
						'log'     => '成功购买了订单号:'.$orderRow['order_no'].'中的商品，奖励积分'.$orderRow['point'],
					);
					$pointObj = new Point();
					$pointObj->update($pointConfig);

					//dyg_jzw 20150813 实付商品总金额
					$real_pay_amount = $orderRow['real_amount'];

					//dyg_jzw 20150623 增加累计消费总计
					$memberData = array(
			    		'total_consume'   => 'total_consume + '.$real_pay_amount,
			    	);
					$memberObj->setData($memberData);
					$memberObj->update('user_id = '.$user_id, 'total_consume');

					/*
					 * dyg_jzw 20150813
					 * 增加预存款冻结
					 */
					//------------------ 
					//获取下单人id
					if ($user_id != 0) 
					{
						//获取是否有推荐人
						$userObj  = new IModel('user');
						$userRow  = $userObj->getObj('id = '.$user_id,'username,inviter');

						//存在推荐人、当前下单人有等级、返佣>0
						if ($userRow['inviter'] && $memberRow['group_id']  && $orderRow['commission'] > 0) 
						{
							//获取推荐人的用户ID
							$inviterUser = $userObj->getObj('username = "'.$userRow['inviter'].'"','id');
							
							//dyg_jzw 20150718 公共邀请号不参与返利，索要发票无法返利
							if($inviterUser['id'] != 10000 && $orderRow['invoice'] != 1)
							{
								//dyg_jzw 20160811 根据返佣笔数来确定返佣比例, 第1笔返佣减4%, 第2~4笔返佣减2%
								//获取当前返佣笔数
								$accountFreezeObj = new IModel('account_freeze_log');
								$accountFreezeList = $accountFreezeObj->query("user_id = {$user_id} and type=0 and status <> 2","id");
								$count_commission_list = count($accountFreezeList);

								//获取order_goods的返佣
								$orderGoodsDB = new IModel('order_goods');
								$orderGoodsList = $orderGoodsDB->query('order_id = '.$orderRow['id']);
								
								$order_del_commission = 0;

								foreach ($orderGoodsList as $_ogs)
								{
									if ($_ogs['commission'] > 0)
									{
										$order_goods_had_commission = $_ogs['goods_nums'] * $_ogs['real_price'] - $_ogs['my_commission'];
										
										//是否需要根据返佣笔数减少返佣
										$order_goods_del_commission = 0;

										if ($count_commission_list == 0) 
										{
											$order_goods_del_commission = $order_goods_had_commission * 0.04;
										}
										else if ($count_commission_list > 0 && $count_commission_list < 4)
										{
											$order_goods_del_commission = $order_goods_had_commission * 0.02;
										}

										if ($order_goods_del_commission) //订单商品减少返佣
										{
											//更新order_goods的返佣字段
											$orderGoodsDB->setData(array(
												'commission' => 'commission - ' . floatval($order_goods_del_commission),
											));
											$orderGoodsDB->update('id='.$_ogs['id'], 'commission');

											$order_del_commission += $order_goods_del_commission;
										}	
									}
								}

								if ($order_del_commission) //订单减少返佣汇总
								{
									$orderRow['commission'] -= $order_del_commission;

									//更新order返佣字段
									$orderObj->setData(array(
										'commission' => 'commission - ' . floatval($order_del_commission),
									));
									$orderObj->update('id='.$orderRow['id'], 'commission');
								}

								//给推荐人相应的返佣金额(冻结)
								$dataArray = array(
									'balance_freeze' => 'balance_freeze + '.$orderRow['commission']
								);
								$memberObj->setData($dataArray);
								$is_success = $memberObj->update('user_id = '.$inviterUser['id'],'balance_freeze');

								if($is_success)
								{
									//添加冻结返佣日志
									$log = new AccountLog();
									$freeze_log=array(
										'user_id'	=> $user_id,
										'to_user_id'  => $inviterUser['id'],
										'order_id'	=> $orderRow['id'],
										'num'      => $orderRow['commission'],
										'note'	=>	'团队成员['.$userRow['username'].']成功下单['.substr_replace($orderNo, '****',14,4).'] 有效金额￥'.($real_pay_amount - $orderRow['my_commission'])
									);
									$re = $log->freeze_add($freeze_log);
								}
							}
							else //dyg_jzw 20160328 如果是公共邀请码, 返佣修改为0
							{
								$updateOrderArray = array(
														'commission'	=> 0
													);
								$orderObj->setData($updateOrderArray);
								$orderObj->update('order_no = "'.$orderNo.'"');
							}
						}

						//dyg_jzw 20161216 高返模式自身的返利
						if ($orderRow['my_commission'] > 0) 
						{
							//给用户本身的高返金额(冻结)
							$dataArray = array(
								'balance_freeze' => 'balance_freeze + '.$orderRow['my_commission']
							);
							$memberObj->setData($dataArray);
							$is_success = $memberObj->update('user_id = '.$user_id,'balance_freeze');

							if($is_success)
							{
								//添加冻结返佣日志
								$log = new AccountLog();
								$freeze_log=array(
									'user_id'	=> $user_id,
									'to_user_id'  => $user_id,
									'order_id'	=> $orderRow['id'],
									'num'      => $orderRow['my_commission'],
									'note'	=>	'我['.$userRow['username'].']成功下单['.$orderNo.'] 有效金额￥'.$real_pay_amount
								);
								$re = $log->freeze_add($freeze_log, 1);
							}
						}
					}
					//------------------
				}
			}

			//插入收款单
			$collectionDocObj = new IModel('collection_doc');
			$collectionData   = array(
				'order_id'   => $orderRow['id'],
				'user_id'    => $orderRow['user_id'],
				'amount'     => $orderRow['order_amount'],
				'time'       => ITime::getDateTime(),
				'payment_id' => $orderRow['pay_type'],
				'pay_status' => 1,
				'if_del'     => 0,
				'note'       => $note,
				'admin_id'   => $admin_id ? $admin_id : 0
			);

			$collectionDocObj->setData($collectionData);
			$collectionDocObj->add();

			//促销活动订单
			if($orderRow['type'] != 0)
			{
				Active::payCallback($orderNo,$orderRow['type']);
			}

			//非货到付款的支付方式
			if($orderRow['pay_type'] != 0)
			{
				//减少库存量
				if (! isset($orderGoodsList)) //dyg_jzw 20160831 以上已获取订单商品列表
				{
					$orderGoodsDB = new IModel('order_goods');
					$orderGoodsList = $orderGoodsDB->query('order_id = '.$orderRow['id']);
				}
				
				$orderGoodsListId = array();
				foreach($orderGoodsList as $key => $val)
				{
					$orderGoodsListId[] = $val['id'];
				}
				self::updateStore($orderGoodsListId,'reduce');
			}

			//dyg_jzw 20150811 添加短信发送设置
			$config = new Config('site_config');
			$sms_send_config = explode(';', $config->sms_send_config);

			//自提点或付款成功 短信发送
			$mobile_code = self::sendTakeself($orderNo); //dyg_jzw 20160216 获取自提验证码
			$orderRow['checkcode'] = $mobile_code? $mobile_code : '';
			$mobile = "";

			//dyg_jzw 20150811 添加短信发送设置
			//订单付款后短信通知管理员进行订单处理
			if($sms_send_config && in_array('sms_admin_order', $sms_send_config))
			{
				if($orderRow['seller_id'] > 0)
				{
					$sellerObj = new IModel('seller');
					$sellerRow = $sellerObj->getObj("id = ".$orderRow['seller_id']);
					$mobile    = $sellerRow['mobile'] ? $sellerRow['mobile'] : "";
				}
				else
				{
					$config = new Config('site_config');
					$mobile = $config->mobile ? $config->mobile : "";
				}
				$smsContent = smsTemplate::payFinishToAdmin(array('{orderNo}' => $orderNo));
				Hsms::send($mobile,$smsContent,0);
			}

			//dyg_jzw 20160308 是否跨境订单 上传支付单->易极付
			if ($orderRow['is_cbe'] == 1)
			{
				$cbeInstance = new Cbe();
				$send_result = $cbeInstance->yi_upload_payment($orderRow);

				if (! $send_result['success']) 
				{
					$smtp  = new SendMail();
					$smtp->send('263436934@qq.com', '易极付上传支付单出错 订单号:'.$orderNo, json_encode($send_result));
				}

			}

			//dyg_jzw 20160216 插入到管易上面
			if (empty($orderRow['seller_id']))
			{
				if (! $orderRow['user_id'])  //游客下单
				{
					$userRow = array('username' => 'guest');
				}
				$isGuanyiSended = Guanyi::addOrder($orderRow, $userRow, $orderGoodsList);

				if ($isGuanyiSended['success']) 
				{
					$dataArray = array(
						'sended_guanyi'	=> 1,
					);

					$orderObj->setData($dataArray);
					$orderObj->update('order_no = "'.$orderNo.'"');
				}
				else //更新失败 输出错误
				{
					$smtp  = new SendMail();
					$smtp->send('263436934@qq.com', '管易新增订单同步失败'.$orderNo, json_encode($isGuanyiSended));
				}
			}

			return $orderRow['id'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * @brief 自提点短信发送
	 * @param string $orderNo 订单编号
	 */
	public static function sendTakeself($orderNo)
	{
		//获取订单信息
		$orderObj  = new IModel('order');
		$orderRow  = $orderObj->getObj('order_no = "'.$orderNo.'"');

		if(empty($orderRow))
		{
			return false;
		}

		//dyg_jzw 20150811 添加短信发送设置
		$config = new Config('site_config');
		$sms_send_config = explode(';', $config->sms_send_config);

		//自提方式短信验证提醒
		if($orderRow['takeself'] > 0)
		{
			$takeselfObj = new IModel('takeself');
			$takeselfRow = $takeselfObj->getObj('id = '.$orderRow['takeself']);
			if($takeselfRow)
			{
				$mobile_code = rand(100000,999999);
				$orderObj->setData(array('checkcode' => $mobile_code));
				$checkResult = $orderObj->update('id = '.$orderRow['id']);
				if($checkResult)
				{

					if($sms_send_config && in_array('sms_takeself', $sms_send_config))
					{
						$smsContent = smsTemplate::takeself(array('{orderNo}' => $orderRow['order_no'],'{address}' => $takeselfRow['address'],'{mobile_code}' => $mobile_code,'{phone}' => $takeselfRow['phone'],'{name}' => $takeselfRow['name']));
						
						//dyg_jzw 20161008 自提订单只向下单人账号发送自提验证码
						$userObj  = new IModel('user');
						$userRow  = $userObj->getObj('id = '.$orderRow['user_id']);

						if (IValidate::mobi($userRow['username']) == false) //注册账号不是手机号
						{
						 	$mobile = $orderRow['mobile'];
						}
						else
						{
							$mobile = $userRow['username'];
						}

						Hsms::send($mobile, $smsContent, 0);
					}
				}
				//dyg_jzw 20160216 增加自提点验证码
				return $mobile_code;
			}
		}
		//普通付款通知
		else
		{
			if($sms_send_config && in_array('sms_order_pay', $sms_send_config))
			{
				$smsContent = smsTemplate::payFinishToUser(array('{orderNo}' => $orderNo));
				Hsms::send($orderRow['mobile'],$smsContent,0);
			}
		}
	}

	/**
	 * 订单商品数量更新操作[公共]
	 * @param array $orderGoodsId ID数据
	 * @param string $type 增加或者减少 add 或者 reduce
	 */
	public static function updateStore($orderGoodsId,$type = 'add')
	{
		if(!is_array($orderGoodsId))
		{
			$orderGoodsId = array($orderGoodsId);
		}

		$newStoreNums  = 0;
		$updateGoodsId = array();
		$orderGoodsObj = new IModel('order_goods');
		$goodsObj      = new IModel('goods');
		$productObj    = new IModel('products');
		$goodsList     = $orderGoodsObj->query('id in('.join(",",$orderGoodsId).') and is_send = 0','goods_id,product_id,goods_nums,seller_id');

		foreach($goodsList as $key => $val)
		{
			//货品库存更新
			if($val['product_id'] != 0)
			{
				$productsRow = $productObj->getObj('id = '.$val['product_id'],'store_nums');
				if(!$productsRow)
				{
					continue;
				}
				$localStoreNums = $productsRow['store_nums'];

				//同步更新所属商品的库存量
				if(in_array($val['goods_id'],$updateGoodsId) == false)
				{
					$updateGoodsId[] = $val['goods_id'];
				}

				$newStoreNums = ($type == 'add') ? $localStoreNums + $val['goods_nums'] : $localStoreNums - $val['goods_nums'];
				$newStoreNums = $newStoreNums > 0 ? $newStoreNums : 0;

				$productObj->setData(array('store_nums' => $newStoreNums));
				$productObj->update('id = '.$val['product_id'],'store_nums');
			}
			//商品库存更新
			else
			{
				$goodsRow = $goodsObj->getObj('id = '.$val['goods_id'],'store_nums');
				if(!$goodsRow)
				{
					continue;
				}
				$localStoreNums = $goodsRow['store_nums'];

				$newStoreNums = ($type == 'add') ? $localStoreNums + $val['goods_nums'] : $localStoreNums - $val['goods_nums'];
				$newStoreNums = $newStoreNums > 0 ? $newStoreNums : 0;

				$goodsObj->setData(array('store_nums' => $newStoreNums));
				$goodsObj->update('id = '.$val['goods_id'],'store_nums');
			}
			//库存减少销售量增加，两者成反比
			$saleData = ($type == 'add') ? -$val['goods_nums'] : $val['goods_nums'];

			//更新goods商品销售量sale字段
			$goodsObj->setData(array('sale' => 'sale + '.$saleData));
			$goodsObj->update('id = '.$val['goods_id'],'sale');

			//更新seller商家销售量sale字段
			$sellerDB = new IModel('seller');
			$sellerDB->setData(array('sale' => 'sale + '.$saleData));
			$sellerDB->update('id = '.$val['seller_id'],'sale');
		}

		//更新统计goods的库存
		if($updateGoodsId)
		{
			foreach($updateGoodsId as $val)
			{
				$totalRow = $productObj->getObj('goods_id = '.$val,'SUM(store_nums) as store');
				$goodsObj->setData(array('store_nums' => $totalRow['store']));
				$goodsObj->update('id = '.$val);
			}
		}
	}

	/**
	 * @brief 获取订单扩展数据资料
	 * @param $order_id int 订单的id
	 * @param $user_id int 用户id
	 * @return array()
	 */
	public function getOrderShow($order_id,$user_id = 0,$seller_id = 0)
	{
		$where = 'id = '.$order_id;
		if($user_id !== 0)
		{
			$where .= ' and user_id = '.$user_id;
		}

		if($seller_id !== 0)
		{
			$where .= ' and seller_id = '.$seller_id;
		}

		$data = array();

		//获得对象
		$tb_order = new IModel('order');
 		$data = $tb_order->getObj($where);
 		if($data)
 		{
	 		$data['order_id'] = $order_id;

	 		//获取配送方式
	 		$tb_delivery = new IModel('delivery');
	 		$delivery_info = $tb_delivery->getObj('id='.$data['distribution']);
	 		if($delivery_info)
	 		{
	 			$data['delivery'] = $delivery_info['name'];

	 			//自提点读取
	 			if($data['takeself'])
	 			{
	 				$data['takeself'] = self::getTakeselfInfo($data['takeself']);
	 			}
	 		}

	 		$areaData = area::name($data['province'],$data['city'],$data['area']);
	 		if(isset($areaData[$data['province']]) && isset($areaData[$data['city']]) && isset($areaData[$data['area']]))
	 		{
		 		$data['province_str'] = $areaData[$data['province']];
		 		$data['city_str']     = $areaData[$data['city']];
		 		$data['area_str']     = $areaData[$data['area']];
	 		}

	        //物流单号
	    	$tb_delivery_doc = new IQuery('delivery_doc as dd');
	    	$tb_delivery_doc->join   = 'left join freight_company as fc on dd.freight_id = fc.id';
	    	$tb_delivery_doc->fields = 'dd.id,dd.delivery_code,fc.freight_name';
	    	$tb_delivery_doc->where  = 'order_id = '.$order_id;
	    	$delivery_info = $tb_delivery_doc->find();
	    	if($delivery_info)
	    	{
	    		$temp = array('freight_name' => array(),'delivery_code' => array(),'delivery_id' => array());
	    		foreach($delivery_info as $key => $val)
	    		{
	    			$temp['delivery_id'][]   = $val['id'];
	    			$temp['freight_name'][]  = $val['freight_name'];
	    			$temp['delivery_code'][] = $val['delivery_code'];
	    		}
	    		$data['freight']['id']            = current($temp['delivery_id']);
    			$data['freight']['freight_name']  = join(",",$temp['freight_name']);
    			$data['freight']['delivery_code'] = join(",",$temp['delivery_code']);
	    	}

	 		//获取支付方式
	 		$tb_payment = new IModel('payment');
	 		$payment_info = $tb_payment->getObj('id='.$data['pay_type']);
	 		if($payment_info)
	 		{
	 			$data['payment'] = $payment_info['name'];
	 			$data['paynote'] = $payment_info['note'];
	 		}

	 		//获取商品总重量和总金额
	 		$tb_order_goods = new IModel('order_goods');
	 		$order_goods_info = $tb_order_goods->query('order_id='.$order_id);
	 		$data['goods_amount'] = 0;
	 		$data['goods_weight'] = 0;

	 		if($order_goods_info)
	 		{
	 			foreach ($order_goods_info as $value)
	 			{
	 				$data['goods_amount'] += $value['real_price']   * $value['goods_nums'];
	 				$data['goods_weight'] += $value['goods_weight'] * $value['goods_nums'];
	 			}
	 		}

	 		//获取用户信息
	 		$query = new IQuery('user as u');
	 		$query->join = ' left join member as m on u.id=m.user_id ';
	 		$query->fields = 'u.username,m.email,m.mobile,m.contact_addr,m.true_name';
	 		$query->where = 'u.id='.$data['user_id'];
	 		$user_info = $query->find();
	 		if($user_info)
	 		{
	 			$user_info = current($user_info);
	 			$data['username']     = $user_info['username'];
	 			$data['email']        = $user_info['email'];
	 			$data['u_mobile']     = $user_info['mobile'];
	 			$data['contact_addr'] = $user_info['contact_addr'];
	 			$data['true_name']    = $user_info['true_name'];
	 		}
 		}
 		return $data;
	}

	/**
	 * 获取自提点基本信息
	 * @param $id int 自提点id
	 */
	public static function getTakeselfInfo($id)
	{
		$takeselfObj = new IModel('takeself');
		$takeselfRow = $takeselfObj->getObj('id = '.$id);
		if(!$takeselfRow)
		{
			return '';
		}

		$temp = area::name($takeselfRow['province'],$takeselfRow['city'],$takeselfRow['area']);
		$takeselfRow['province_str'] = $temp[$takeselfRow['province']];
		$takeselfRow['city_str']     = $temp[$takeselfRow['city']];
		$takeselfRow['area_str']     = $temp[$takeselfRow['area']];
		return $takeselfRow;
	}

	/**
	 * 获取订单基本信息
	 * @param $orderIdString string 订单ID序列
	 * @param $seller_id int 商家ID
	 */
	public function getOrderInfo($orderIdString,$seller_id = 0)
	{
		$orderObj    = new IModel('order');
		$areaIdArray = array();
		$where       = 'id in ('.$orderIdString.')';
		if(!IWeb::$app->getController()->admin['admin_id'])
		{
			$where .= 'and seller_id = '.$seller_id;
		}
		$orderList = $orderObj->query($where);

		if(!$orderList)
		{
			IError::show(403,"无查阅订单权限");
		}

		foreach($orderList as $key => $val)
		{
			$temp = area::name($val['province'],$val['city'],$val['area']);
			$orderList[$key]['province_str'] = $temp[$val['province']];
			$orderList[$key]['city_str']     = $temp[$val['city']];
			$orderList[$key]['area_str']     = $temp[$val['area']];
		}

		return $orderList;
	}

	/**
	 * @brief 把订单商品同步到order_goods表中
	 * @param $order_id 订单ID
	 * @param $goodsInfo 商品和货品信息（购物车数据结构,countSum 最终生成的格式）
	 */
	public function insertOrderGoods($order_id,$goodsResult = array())
	{
		$orderGoodsObj = new IModel('order_goods');

		//清理旧的关联数据
		$orderGoodsObj->del('order_id = '.$order_id);

		$goodsArray = array(
			'order_id' => $order_id
		);

		if(isset($goodsResult['goodsList']))
		{
			foreach($goodsResult['goodsList'] as $key => $val)
			{
				//拼接商品名称和规格数据
				$specArray = array('name' => $val['name'],'goodsno' => $val['goods_no'],'value' => '');

				if(isset($val['spec_array']))
				{
					$spec = block::show_spec($val['spec_array']);
					foreach($spec as $skey => $svalue)
					{
						$specArray['value'] .= $skey.':'.$svalue.',';
					}
					$specArray['value'] = trim($specArray['value'],',');
				}

				$goodsArray['product_id']  = $val['product_id'];
				$goodsArray['goods_id']    = $val['goods_id'];
				$goodsArray['img']         = $val['img'];
				$goodsArray['goods_price'] = $val['sell_price'];
				$goodsArray['real_price']  = $val['sell_price'] - $val['reduce'];
				$goodsArray['goods_nums']  = $val['count'];
				$goodsArray['goods_weight']= $val['weight'];
				$goodsArray['goods_array'] = IFilter::addSlash(JSON::encode($specArray));
				$goodsArray['seller_id']   = $val['seller_id'];				
				$goodsArray['commission'] = $val['commission']; //dyg_jzw 20150819 插入订单商品的返佣
				$goodsArray['my_commission'] = $val['my_commission']; //dyg_jzw 20161215 插入订单商品的高返自身返利

				$orderGoodsObj->setData($goodsArray);
				$orderGoodsObj->add();
			}
		}
	}
	/**
	 * 获取订单状态
	 * @param $orderRow array('status' => '订单状态','pay_type' => '支付方式ID','distribution_status' => '配送状态','pay_status' => '支付状态')
	 * @return int 订单状态值 0:未知; 1:未付款等待发货(货到付款); 2:等待付款(线上支付); 3:已发货(已付款); 4:已付款等待发货; 5:已取消; 6:已完成(已付款,已收货); 7:全部退款; 8:部分发货(货到付款+已经付款); 9:部分退款(未发货+部分发货); 10:部分退款(全部发货); 11:已发货(货到付款); 12:未处理的退款申请
	 */
	public static function getOrderStatus($orderRow)
	{
		//1,刚生成订单,未付款
		if($orderRow['status'] == 1)
		{
			//选择货到付款
			if($orderRow['pay_type'] == 0)
			{
				if($orderRow['distribution_status'] == 0)
				{
					return 1;
				}
				else if($orderRow['distribution_status'] == 1)
				{
					return 11;
				}
				else if($orderRow['distribution_status'] == 2)
				{
					return 8;
				}
			}
			//选择在线支付
			else
			{
				return 2;
			}
		}
		//2,已经付款
		else if($orderRow['status'] == 2)
		{
			$refundDB  = new IModel('refundment_doc');
			$refundRow = $refundDB->getObj('order_no = "'.$orderRow['order_no'].'" and if_del = 0 and pay_status = 0');
			if($refundRow)
			{
				return 12;
			}
			if($orderRow['distribution_status'] == 0)
			{
				return 4;
			}
			else if($orderRow['distribution_status'] == 1)
			{
				return 3;
			}
			else if($orderRow['distribution_status'] == 2)
			{
				return 8;
			}
		}
		//3,取消或者作废订单
		else if($orderRow['status'] == 3 || $orderRow['status'] == 4)
		{
			return 5;
		}
		//4,完成订单
		else if($orderRow['status'] == 5)
		{
			return 6;
		}
		//5,退款
		else if($orderRow['status'] == 6)
		{
			return 7;
		}
		//6,部分退款
		else if($orderRow['status'] == 7)
		{
			//发货
			if($orderRow['distribution_status'] == 1)
			{
				return 10;
			}
			//未发货
			else
			{
				return 9;
			}
		}
		return 0;
	}

	//获取订单支付状态
	public static function getOrderPayStatusText($orderRow)
	{
		if($orderRow['status'] == '6')
		{
			return '全部退款';
		}

		if($orderRow['status'] == '7')
		{
			return '部分退款';
		}

		if($orderRow['pay_status'] == 0)
		{
			return '未付款';
		}

		if($orderRow['pay_status'] == 1)
		{
			return '已付款';
		}
		return '未知';
	}

	//获取订单类型
	public static function getOrderTypeText($orderRow)
	{
		switch($orderRow['type'])
		{
			case "1":
			{
				return '团购订单';
			}
			break;

			case "2":
			{
				return '抢购订单';
			}
			break;

			default:
			{
				return '普通订单';
			}
		}
	}

	//获取订单配送状态
	public static function getOrderDistributionStatusText($orderRow)
	{
		if($orderRow['status'] == 5)
		{
			return '已收货';
		}
		else if($orderRow['distribution_status'] == 1)
		{
			return '已发货';
		}
		else if($orderRow['distribution_status'] == 0)
		{
			return '未发货';
		}
		else if($orderRow['distribution_status'] == 2)
		{
			return '部分发货';
		}
	}

	/**
	 * 获取订单状态问题说明
	 * @param $statusCode int 订单的状态码
	 * @return string 订单状态说明
	 */
	public static function orderStatusText($statusCode)
	{
		$result = array(
			0 => '未知',
			1 => '等待发货',
			2 => '等待付款',
			3 => '已发货',
			4 => '等待发货',
			5 => '已取消',
			6 => '已完成',
			7 => '已退款',
			8 => '部分发货',
			9 => '部分退款',
			10=> '部分退款',
			11=> '已发货',
			12=> '申请退款',
		);
		return isset($result[$statusCode]) ? $result[$statusCode] : '';
	}

	/**
	 * @breif 订单的流向
	 * @param $orderRow array 订单数据
	 * @return array('时间' => '事件')
	 */
	public static function orderStep($orderRow)
	{
		$result = array();

		//1,创建订单
		$result[$orderRow['create_time']] = '订单创建';

		//2,订单支付
		if($orderRow['pay_status'] > 0)
		{
			$result[$orderRow['pay_time']] = '订单付款  '.$orderRow['order_amount'].'元';//dyg_jzw 20150820 修改提示
		}

		//3,订单配送
        if($orderRow['distribution_status'] > 0)
        {
        	$result[$orderRow['send_time']] = '订单发货完成';
    	}

		//4,订单完成
        if($orderRow['status'] == 5)
        {
        	$result[$orderRow['completion_time']] = '订单完成';
        }

        ksort($result);
        return $result;
	}

	/**
	 * @brief 商品发货接口
	 * @param string $order_id 订单id
	 * @param array $order_goods_relation 订单与商品关联id
	 * @param int $sendor_id 操作者id
	 * @param string $sendor 操作者所属 admin,seller
	 */
	public static function sendDeliveryGoods($order_id,$order_goods_relation,$sendor_id,$sendor = 'admin')
	{
		$order_no = IFilter::act(IReq::get('order_no'));
		//检查此订单是否存在未处理的退款申请
		$refundDB = new IModel('refundment_doc');
		$refundRow= $refundDB->getObj('order_no = "'.$order_no.'" and pay_status = 0 and if_del = 0');
		if($refundRow)
		{
			return "此订单有未处理的退款申请";
		}

	 	$paramArray = array(
	 		'order_id'      => $order_id,
	 		'user_id'       => IFilter::act(IReq::get('user_id'),'int'),
	 		'name'          => IFilter::act(IReq::get('name')),
	 		'postcode'      => IFilter::act(IReq::get('postcode'),'int'),
	 		'telphone'      => IFilter::act(IReq::get('telphone')),
	 		'province'      => IFilter::act(IReq::get('province'),'int'),
	 		'city'          => IFilter::act(IReq::get('city'),'int'),
	 		'area'          => IFilter::act(IReq::get('area'),'int'),
	 		'address'       => IFilter::act(IReq::get('address')),
	 		'mobile'        => IFilter::act(IReq::get('mobile')),
	 		'freight'       => IFilter::act(IReq::get('freight'),'float'),
	 		'delivery_code' => IFilter::act(IReq::get('delivery_code')),
	 		'delivery_type' => IFilter::act(IReq::get('delivery_type')),
	 		'note'          => IFilter::act(IReq::get('note'),'text'),
	 		'time'          => ITime::getDateTime(),
	 		'freight_id'    => IFilter::act(IReq::get('freight_id'),'int'),
	 	);

	 	switch($sendor)
	 	{
	 		case "admin":
	 		{
	 			$paramArray['admin_id'] = $sendor_id;

	 			$adminDB = new IModel('admin');
	 			$sendorData = $adminDB->getObj('id = '.$sendor_id);
	 			$sendorName = $sendorData['admin_name'];
	 			$sendorSort = '管理员';
	 		}
	 		break;

	 		case "seller":
	 		{
	 			$paramArray['seller_id'] = $sendor_id;

	 			$sellerDB = new IModel('seller');
	 			$sendorData = $sellerDB->getObj('id = '.$sendor_id);
	 			$sendorName = $sendorData['true_name'];
	 			$sendorSort = '加盟商户';
	 		}
	 		break;
	 	}

		//订单对象
		$tb_order   = new IModel('order');
		$tbOrderRow = $tb_order->getObj('id = '.$order_id);

    	//发送短信,生成订单日志,生成发货单
    	//1,自提点领取
    	if($tbOrderRow['takeself'] > 0)
    	{
    		//自提商品不需要发货单
    		$deliveryId = 0;

    		//短信内容
    		$takeDB = new IModel('takeself');
    		$takeRow = $takeDB->getObj('id = '.$tbOrderRow['takeself']);
	    	$replaceData = array(
	    		'{user_name}' => $paramArray['name'],
	    		'{order_no}'  => $order_no,
	    		'{takeself}'  => $takeRow['name'],
	    	);
    		$mobileMsg = smsTemplate::takeselfGoods($replaceData);

    		//订单日志内容
			$logArray = array(
				'order_id' => $order_id,
				'user'     => $sendorName,
				'action'   => '自提',
				'result'   => '成功',
				'note'     => '订单【'.$order_no.'】由【'.$sendorSort.'】'.$sendorName.'处理自提',
				'addtime'  => ITime::getDateTime(),
			);
    	}
    	//2,普通发货物流
    	else
    	{
		 	//插入发货单
	 		$tb_delivery_doc = new IModel('delivery_doc');
	 		$tb_delivery_doc->setData($paramArray);
	 		$deliveryId = $tb_delivery_doc->add();

    		//短信内容
	    	$freightDB  = new IModel('freight_company');
	    	$freightRow = $freightDB->getObj('id = '.$paramArray['freight_id']);
	    	$replaceData = array(
	    		'{user_name}'        => $paramArray['name'],
	    		'{order_no}'         => $order_no,
	    		'{sendor}'           => '['.$sendorSort.']'.$sendorName,
	    		'{delivery_company}' => $freightRow['freight_name'],
	    		'{delivery_no}'      => $paramArray['delivery_code'],
	    	);
	    	$mobileMsg = smsTemplate::sendGoods($replaceData);

	    	//订单日志内容
			$logArray = array(
				'order_id' => $order_id,
				'user'     => $sendorName,
				'action'   => '发货',
				'result'   => '成功',
				'note'     => '订单【'.$order_no.'】由【'.$sendorSort.'】'.$sendorName.'发货',
				'addtime'  => ITime::getDateTime(),
			);
    	}

    	//dyg_jzw 20150811 添加短信发送设置
		$config = new Config('site_config');
		$sms_send_config = explode(';', $config->sms_send_config);

		if($sms_send_config && in_array('sms_order_send', $sms_send_config))
		{
			//短信发送
    		Hsms::send($paramArray['mobile'], $mobileMsg, 0);

    		//dyg_jzw 20160517 发送短信给下单人, 如果下单人和收货人不同
	    	$memberDB  = new IModel('member');
	    	$memberRow = $memberDB->getObj('user_id = '.$tbOrderRow['user_id']);

	    	if ($memberRow['mobile'] && $memberRow['mobile'] != $paramArray['mobile'])
	    	{
	    		Hsms::send($memberRow['mobile'],$mobileMsg,0);
	    	}
		}
    	
		//订单日志记录
    	$tb_order_log = new IModel('order_log');
    	$tb_order_log->setData($logArray);
    	$sendResult = $tb_order_log->add();
    	if(!$sendResult)
    	{
    		$tb_order_log->rollback();
    		die('订单日志生成错误');
    	}
		//如果支付方式为货到付款，则减少库存
		if($tbOrderRow['pay_type'] == 0)
		{
		 	//减少库存量
		 	self::updateStore($order_goods_relation,'reduce');
		}

		//更新发货状态
	 	$orderGoodsDB = new IModel('order_goods');
	 	$orderGoodsRow = $orderGoodsDB->getObj('is_send = 0 and order_id = '.$order_id,'count(*) as num');
		$sendStatus = 2;//部分发货
	 	if(count($order_goods_relation) >= $orderGoodsRow['num'])
	 	{
	 		$sendStatus = 1;//全部发货
	 	}

	 	foreach($order_goods_relation as $key => $val)
	 	{
	 		//商家发货检查商品所有权
	 		if(isset($paramArray['seller_id']))
	 		{
	 			$orderGoodsData = $orderGoodsDB->getObj("id = ".$val);
	 			$goodsDB = new IModel('goods');
	 			$sellerResult = $goodsDB->getObj("id = ".$orderGoodsData['goods_id']." and seller_id = ".$paramArray['seller_id']);
	 			if(!$sellerResult)
	 			{
	 				$goodsDB->rollback();
	 				die('发货的商品信息与商家不符合');
	 			}
	 		}

	 		$orderGoodsDB->setData(array(
	 			"is_send"     => 1,
	 			"delivery_id" => $deliveryId,
	 		));
	 		if(!$orderGoodsDB->update(" id = {$val} "))
	 		{
	 			$orderGoodsDB->rollback();
				die('订单商品发货状态修改失败');
	 		}
	 	}

	 	//更新发货状态
	 	$orderUpdate = array(
	 						'distribution_status' => $sendStatus,
	 						'send_time'           => ITime::getDateTime(),
	    				);

 		//如果全部发货之前已存在 "部分退款" 那么更新订单状态以允许“确认收货”按钮可用
 		if($tbOrderRow['status'] == 7 && $sendStatus == 1)
	    {
 			$orderUpdate['status'] = 2;
	    }
	 	$tb_order->setData($orderUpdate);

	 	if(!$tb_order->update('id='.$order_id))
	 	{
	 		$tb_order->rollback();
	 		die('订单更新失败');
	    }

    	//同步发货接口，如支付宝担保交易等
    	if($sendResult && $sendStatus == 1)
    	{
    		sendgoods::run($order_id);
    	}

    	return true;
	}

	/**
	 * @biref 是否可以发货操作
	 * @param array $orderRow 订单对象
	 */
	public static function isGoDelivery($orderRow)
	{
		/* 1,已经完全发货
		 * 2,非货到付款，并且没有支付*/
		if($orderRow['distribution_status'] == 1 || ($orderRow['pay_type'] != 0 && $orderRow['pay_status'] == 0) || self::getOrderStatus($orderRow) == 12)
		{
			return false;
		}
		return true;
	}

	/**
	 * @brief 获取商品发送状态
	 */
	public static function goodsSendStatus($is_send)
	{
		$data = array(0 => '未发货',1 => '已发货',2 => '已退货');
		return isset($data[$is_send]) ? $data[$is_send] : '';
	}

	//获取订单商品信息
	public static function getOrderGoods($order_id)
	{
		$orderGoodsObj        = new IQuery('order_goods');
		$orderGoodsObj->where = "order_id = ".$order_id;
		$orderGoodsObj->fields = 'id,goods_array,goods_id,product_id,goods_nums';
		$orderGoodsList = $orderGoodsObj->find();
		$goodList = array();
		foreach($orderGoodsList as $good)
		{
			$temp = JSON::decode($good['goods_array']);
			$temp['goods_nums'] = $good['goods_nums'];
			$goodList[] = $temp;
		}
		return $goodList;
	}

	/**
	 * @brief 返回检索条件相关信息
	 * @param int $search 条件数组
	 * @return array 查询条件（$join,$where）数据组
	 */
	public static function getSearchCondition($search)
	{
		$join  = "left join delivery as d on o.distribution = d.id left join payment as p on o.pay_type = p.id left join user as u on u.id = o.user_id";
		$where = "if_del = 0";
		//条件筛选处理
		if(isset($search['is_seller']))
		{
			$is_seller = IFilter::act($search['is_seller']);
			if($is_seller == "self")
			{
				$where .= " and o.seller_id = 0 ";
			}
			else if($is_seller == "seller")
			{
				$where .= " and o.seller_id != 0 ";
			}
		}

		if(isset($search['pay_status']) && $search['pay_status'] !== '')
		{
			$pay_status = IFilter::act($search['pay_status'], 'int');
			$where .= " and o.pay_status = ".$pay_status;
		}

		if(isset($search['distribution_status']) && $search['distribution_status'] !== '')
		{
			$distribution_status = IFilter::act($search['distribution_status'], 'int');
			$where .= " and o.distribution_status = ".$distribution_status;
		}

		if(isset($search['status']) && $search['status'] !== '')
		{
			$status = IFilter::act($search['status'], 'int');
			$where .= " and o.status = ".$status;
		}

		if(isset($search['name']) && isset($search['keywords']))
		{
			$name = IFilter::act($search['name'], 'string');
			$keywords = IFilter::act($search['keywords'], 'string');
			if ($name && $keywords)
			{
				switch ($name)
				{
					case "seller_name":
					{
						$sellerObj = new IModel('seller');
						$sellerRow = $sellerObj->getObj('true_name = "'.$keywords.'"');
						$where .= $sellerRow ? " and o.seller_id = ".$sellerRow['id'] : " and null ";
					}
					break;
					
					//dyg_jzw 添加下单人搜索
					case 'buyer_name':
						$where .= " and u.username = '".$search['keywords']."'";
					break;

					default:
						$where .= " and o.".$name." = '".$keywords."'";
					break;
				}
			}
		}

		// 高级筛选
		if (isset($search['adv_search']) && 1 == $search['adv_search'])
		{
			// 订单总额
			if (isset($search['order_amount']) && !empty($search['order_amount']))
			{
				$order_amount = explode(",", $search['order_amount']);
				$order_amount_0 = IFilter::act($order_amount[0], 'float');
				if (isset($order_amount[1]))
				{
					$order_amount_1 = IFilter::act($order_amount[1], 'float');
				}
				else
				{
					$order_amount_1 = 0;
				}
				if ($order_amount_0 == $order_amount_1)
				{
					$where .= " and o.order_amount = $order_amount_0 ";
				}
				else if ($order_amount_0 > $order_amount_1)
				{
					$where .= " and o.order_amount between $order_amount_1 and $order_amount_0 ";
				}
				else
				{
					$where .= " and o.order_amount between $order_amount_0 and $order_amount_1 ";
				}
			}
			// 发货时间
			if (isset($search['send_time']) && !empty($search['send_time']))
			{
				$send_time = explode(",", $search['send_time']);
				// 验证日期
				$is_check_0 = ITime::checkDateTime($send_time[0]);
				$is_check_1 = false;
				if (isset($send_time[1]))
				{
					$is_check_1 = ITime::checkDateTime($send_time[1]);
				}
				if ($is_check_0 && $is_check_1)
				{
					// 是否相等
					if ($send_time[0] == $send_time[1])
					{
						$where .= " and o.send_time between '".$send_time[0]." 00:00:00' and '".$send_time[0]." 23:59:59'";
					}
					else
					{
						$difference = ITime::getDiffSec($send_time[0].' 00:00:00', $send_time[1].' 00:00:00');
						if (0 < $difference)
						{
							$where .= " and o.send_time between '".$send_time[1]." 00:00:00' and '".$send_time[0]." 23:59:59'";
						}
						else
						{
							$where .= " and o.send_time between '".$send_time[0]." 00:00:00' and '".$send_time[1]." 23:59:59'";
						}
					}
				}
				elseif ($is_check_0)
				{
					$where .= " and o.send_time between '".$send_time[0]." 00:00:00' and '".$send_time[0]." 23:59:59'";
				}
			}
			// 下单时间
			if (isset($search['create_time']) && !empty($search['create_time']))
			{
				$create_time = explode(",", $search['create_time']);
				// 验证日期
				$is_check_0 = ITime::checkDateTime($create_time[0]);
				$is_check_1 = false;
				if (isset($create_time[1]))
				{
					$is_check_1 = ITime::checkDateTime($create_time[1]);
				}
				if ($is_check_0 && $is_check_1)
				{
					// 是否相等
					if ($create_time[0] == $create_time[1])
					{
						$where .= " and o.create_time between '".$create_time[0]." 00:00:00' and '".$create_time[0]." 23:59:59'";
					}
					else
					{
						$difference = ITime::getDiffSec($create_time[0].' 00:00:00', $create_time[1].' 00:00:00');
						if (0 < $difference)
						{
							$where .= " and o.create_time between '".$create_time[1]." 00:00:00' and '".$create_time[0]." 23:59:59'";
						}
						else
						{
							$where .= " and o.create_time between '".$create_time[0]." 00:00:00' and '".$create_time[1]." 23:59:59'";
						}
					}
				}
				elseif ($is_check_0)
				{
					$where .= " and o.create_time between '".$create_time[0]." 00:00:00' and '".$create_time[0]." 23:59:59'";
				}
			}
			// 支付时间 dyg_jzw 20161013
 			if (isset($search['pay_time']) && !empty($search['pay_time']))
			{
				$pay_time = explode(",", $search['pay_time']);
				// 验证日期
				$is_check_0 = ITime::checkDateTime($pay_time[0]);
				$is_check_1 = false;
				if (isset($pay_time[1]))
				{
					$is_check_1 = ITime::checkDateTime($pay_time[1]);
				}
				if ($is_check_0 && $is_check_1)
				{
					// 是否相等
					if ($pay_time[0] == $pay_time[1])
					{
						$where .= " and o.pay_time between '".$pay_time[0]." 00:00:00' and '".$pay_time[0]." 23:59:59'";
					}
					else
					{
						$difference = ITime::getDiffSec($pay_time[0].' 00:00:00', $pay_time[1].' 00:00:00');
						if (0 < $difference)
						{
							$where .= " and o.pay_time between '".$pay_time[1]." 00:00:00' and '".$pay_time[0]." 23:59:59'";
						}
						else
						{
							$where .= " and o.pay_time between '".$pay_time[0]." 00:00:00' and '".$pay_time[1]." 23:59:59'";
						}
					}
				}
				elseif ($is_check_0)
				{
					$where .= " and o.pay_time between '".$pay_time[0]." 00:00:00' and '".$pay_time[0]." 23:59:59'";
				}
			}
			// 完成时间
			if (isset($search['completion_time']) && !empty($search['completion_time']))
			{
				$completion_time = explode(",", $search['completion_time']);
				// 验证日期
				$is_check_0 = ITime::checkDateTime($completion_time[0]);
				$is_check_1 = false;
				if (isset($completion_time[1]))
				{
					$is_check_1 = ITime::checkDateTime($completion_time[1]);
				}
				if ($is_check_0 && $is_check_1)
				{
					// 是否相等
					if ($completion_time[0] == $completion_time[1])
					{
						$where .= " and o.completion_time between '".$completion_time[0]." 00:00:00' and '".$completion_time[0]." 23:59:59'";
					}
					else
					{
						$difference = ITime::getDiffSec($completion_time[0].' 00:00:00', $completion_time[1].' 00:00:00');
						if (0 < $difference)
						{
							$where .= " and o.completion_time between '".$completion_time[1]." 00:00:00' and '".$completion_time[0]." 23:59:59'";
						}
						else
						{
							$where .= " and o.completion_time between '".$completion_time[0]." 00:00:00' and '".$completion_time[1]." 23:59:59'";
						}
					}
				}
				elseif ($is_check_0)
				{
					$where .= " and o.completion_time between '".$completion_time[0]." 00:00:00' and '".$completion_time[0]." 23:59:59'";
				}
			}
		}
		$results = array($join,$where);
		unset($join,$where);
		return $results;
	}

	/**
	 * @brief 是否允许退款申请
	 * @param array $orderRow 订单表的数据结构
	 * @param array $orderGoodsIds 订单与商品关系表ID数组
	 * @return boolean true or false
	 */
	public static function isRefundmentApply($orderRow,$orderGoodsIds = array())
	{
		if(!is_array($orderGoodsIds))
		{
			return "退款商品ID数据类型错误";
		}

		//要退款的orderGoodsId关联信息
		if($orderGoodsIds)
		{
			$order_id     = $orderRow['id'];
			$goodsOrderDB = new IModel('order_goods');
			$refundsDB    = new IModel('refundment_doc');

			foreach($orderGoodsIds as $key => $val)
			{
				$goodsOrderRow = $goodsOrderDB->getObj('id = '.$val.' and order_id = '.$order_id);
				if($goodsOrderRow && $goodsOrderRow['is_send'] == 2)
				{
					return "有商品已经做了退款处理";
				}

				if( $refundsDB->getObj('if_del = 0 and pay_status = 0 and FIND_IN_SET('.$val.',order_goods_id)') )
				{
					return "您已经对此商品提交了退款申请，请耐心等待";
				}
			}

    		//判断是否已经生成了结算申请或者已经结算了
    		$billObj = new IModel('bill');
    		$billRow = $billObj->getObj('FIND_IN_SET('.$order_id.',order_ids)');
    		if($billRow)
    		{
    			return '此订单金额已被商家结算完毕，请直接与商家联系退款';
    		}
    		return true;
		}
		else
		{
			//已经付款,并且未全部退款，未正在发货中
			if($orderRow['pay_status'] == 1 && $orderRow['status'] != 6 && self::getOrderStatus($orderRow) != 3)
			{
				//dyg_jzw 20150729 订单已完成，一定时间后不允许退款
				if ($orderRow['status'] == 5) 
				{
					$siteConfig = new Config('site_config');
					$order_refund_time = isset($siteConfig->order_refund_time) ? intval($siteConfig->order_refund_time) : 20;

					//如果完成时间+限期(天数) 小于当前时间，则不允许退款
					if ( strtotime($orderRow['completion_time']) + $order_refund_time*24*3600 < time()) 
					{
						return false;
					}
				}
				//------------------

				return true;
			}
			return false;
		}
	}

	/**
	 * @brief 退款状态
	 * @param int $pay_status 退款单状态数值
	 * @return string 状态描述
	 */
	public static function refundmentText($pay_status)
	{
		$result = array('0' => '申请退款', '1' => '退款失败', '2' => '退款成功');
		return isset($result[$pay_status]) ? $result[$pay_status] : '';
	}

	/**
	 * @brief 还原重置订单所使用的道具
	 * @param int $order 订单ID
	 */
	public static function resetOrderProp($order_id)
	{
		$orderDB   = new IModel('order');
		$orderList = $orderDB->query('id in ( '.$order_id.' ) and pay_status = 0 and prop is not null');
		foreach($orderList as $key => $orderRow)
		{
			if(isset($orderRow['prop']) && $orderRow['prop'])
			{
				$propDB = new IModel('prop');
				$propDB->setData(array('is_close' => 0));
				$propDB->update('id = '.$orderRow['prop']);
			}
		}
	}

	/**
	 * @brief 商家对退款申请的处理权限
	 * @param int $refundId 退款单ID
	 * @param int $seller_id 商家ID
	 * @return int 退款权限状态, 0:无权查看；1:只读；2：可读可写
	 */
	public static function isSellerRefund($refundId,$seller_id)
	{
		$refundDB = new IModel('refundment_doc');
		$refundRow= $refundDB->getObj('id = '.$refundId.' and seller_id = '.$seller_id);
		if($refundRow)
		{
			$orderDB = new IModel('order');
			$orderRow= $orderDB->getObj('id = '.$refundRow['order_id']);
			if($orderRow['is_checkout'] == 1)
			{
				return 1;
			}
			else
			{
				return 2;
			}
		}
		return 0;
	}

	/**
	 * @brief 订单退款操作
	 * @param int $refundId 退款单ID
	 * @param int $authorId 操作人ID
	 * @param string $type admin:管理员;seller:商家
	 * @param int    $way 退款方式， balance:退款余额; other:其他方式退款; origin,原路退回
	 * @return boolean
	 */
	public static function refund($refundId,$authorId,$type = 'admin',$way = 'balance')
	{
		$orderGoodsDB= new IModel('order_goods');
		$refundDB    = new IModel('refundment_doc');
		$goodsDB     = new IModel('goods');
		$memberDB    = new IModel('member');
		$tb_order    = new IModel('order');
		$productDB	 = new IModel('products'); //dyg_jzw 20170220 增加products表的积分退还

		$where = 'id = '.$refundId;
		if($type == "seller")
		{
			$where .= ' and seller_id = '.$authorId;
		}
		$refundsRow = $refundDB->getObj($where);
		if(!$refundsRow)
		{
			return "退款申请信息不存在";
		}

		if(!$refundsRow['order_goods_id'])
		{
			return "退款商品信息为空";
		}

		$orderGoodsList = $orderGoodsDB->query('id in ('.$refundsRow['order_goods_id'].') and is_send != 2');
		$order_goods_id = explode(",",$refundsRow['order_goods_id']);
		if(count($orderGoodsList) != count($order_goods_id))
		{
			return "要退款商品的状态不正确";
		}
		
		$order_id = $refundsRow['order_id'];
		$order_no = $refundsRow['order_no'];
		$user_id  = $refundsRow['user_id'];

		//获取用户信息
		$memberObj = $memberDB->getObj('user_id = '.$user_id); //dyg_jzw 获取更多信息 下面需要
		
		if($way == 'balance' && !$memberObj)
		{
			return "退款到余额的用户不存在";
		}

		//用户组积分倍数
		$group_point_times = 1;

		if ($memberObj['group_id']) 
		{
			//dyg_jzw 订单持有者所在用户组的信息
			$groupDB = new IModel('user_group');
			$groupRow = $groupDB->getObj('id = '.$memberObj['group_id']);

			if ($groupRow) 
			{
				$group_point_times = isset($groupRow['point_times']) ? $groupRow['point_times'] : 1;
			}
		}
		
		//退款金额校验
		//(1)校验商品金额；
		$autoMount = 0;
		foreach($orderGoodsList as $key => $val)
		{
			$autoMount += $val['goods_nums'] * $val['real_price'];
		}

		if($refundsRow['amount'] > $autoMount)
		{
			return "退款金额不能大于商品金额";
		}

		//(2)校验订单金额
		$orderRow = $tb_order->getObj('id = '.$order_id);
		if($refundsRow['amount'] > $orderRow['order_amount'])
		{
			return "退款金额不能大于实际用户支付的订单金额";
		}

		//累计各项数据进行还原操作 //dyg_jzw 20160726 修复退款校验bug
		$orderRow['exp']  = 0;
		$orderRow['point']  = 0;
		$orderRow['commission']  = 0;
		$orderRow['my_commission']  = 0; //dyg_jzw 20161216 高返模式退款

		foreach($orderGoodsList as $key => $val)
		{

			//库存增加
			self::updateStore($val['id'],'add');

			//更新退款状态
			$orderGoodsDB->setData(array('is_send' => 2));
			$orderGoodsDB->update('id = '.$val['id']);

			//退款积分,经验
			$goodsRow = $goodsDB->getObj('id = '.$val['goods_id']);
			$orderRow['exp']   += intval($goodsRow['exp']  * $val['goods_nums'] * $val['real_price']); //dyg_jzw 20160128 每消费1元，累计的经验值
			
			//dyg_jzw 20170220 积分退还支持products单独设置
			if($val['product_id'])
			{
				//存在product
				$productRow = $productDB->getObj('id = '.$val['product_id']);
				$orderRow['point'] += intval($productRow['point'] * $val['goods_nums'] * $group_point_times); //dyg_jzw 20160128 积分倍数
			}
			else
			{
				//存在goods
				$orderRow['point'] += intval($goodsRow['point'] * $val['goods_nums'] * $group_point_times); //dyg_jzw 20160128 积分倍数
			}
			
			$orderRow['commission'] += $val['commission']; //dyg_jzw 20160128 增加退款单的佣金汇总
			$orderRow['my_commission'] += $val['my_commission'];
		}
		
		//如果管理员（商家）自定义了退款金额。否则就使用默认的付款商品金额
		$amount = $refundsRow['amount'] == 0 ? $autoMount : $refundsRow['amount'];

		
		//更新order表状态,查询是否订单中还有未退款的商品，判断是订单退款状态：全部退款或部分退款
		$isSendData = $orderGoodsDB->getObj('order_id = '.$order_id.' and is_send != 2');
		$orderStatus = 6;//全部退款
		if($isSendData)
		{
			$orderStatus = 7;//部分退款
		}
		
		$tb_order->setData(array('status' => $orderStatus));
		$tb_order->update('id='.$order_id);

		/**
		 * 当订单为全部退款的状态且未手动输入退款金额(需要系统自动计算退款金额)的时候
		 * 退款金额 = 订单支付总金额 + 运费(是否发货) - 此订单之前已经退款金额
		 *
		 * 进行用户的余额增加操作,订单中的积分,经验的减少操作
		 */
		if($orderStatus == 6)
		{
			Order_class::resetOrderProp($order_id);

			//系统自动计算退款价格
			if($refundsRow['amount'] == 0)
			{
				$amount = $orderRow['order_amount'];

				//在订单商品已经发货的情况下,扣除运费
				$isDeliveryData = $orderGoodsDB->getObj('order_id = '.$order_id.' and delivery_id > 0');
				if($isDeliveryData)
				{
					$amount -= $orderRow['real_freight'];
				}

				//检查之前已经退款的订单
				$hasRefundData = $refundDB->query("order_id = ".$refundsRow['order_id']." and pay_status = 2 and id != ".$refundId,"amount");
				if($hasRefundData)
				{
					foreach($hasRefundData as $value)
					{
						$amount -= $value['amount'];
					}
				}
			}
		}

		//最后检验
		if($amount < 0 || $amount > $orderRow['order_amount'])
		{
			$tb_order->rollback();
			return '请手动填写要退款的金额';
		}
		//如果是商家自己处理的货到付款订单必须用其他方式退款,防止商家和买家刷余额
		if($orderRow['pay_type'] == 0 && $type == "seller")
		{
			$way = 'other';
		}
		//更新退款表
		$updateData = array(
			'amount'       => $amount,
			'pay_status'   => 2,
			'dispose_time' => ITime::getDateTime(),
			'way'          => $way,
		);
		$refundDB->setData($updateData);
		$refundDB->update('id = '.$refundId);

		//更新用户的信息
		//dyg_jzw 20150806 增加扣除经验值的日志记录
		$expConfig = array(
			'user_id'	=> $user_id,
			'exp'		=> '-'.$orderRow['exp'],
			'log'		=> '订单【'.$orderRow['order_no'].'】退款，扣除经验值'.$orderRow['exp']
		);
		$expObj = new Exp();
		$expObj->update($expConfig);

		//dyg_jzw 20150623 累计消费总计扣除相应款项
		$total_consume = $memberObj['total_consume'] - $autoMount;

		$memberDB->setData(array(
			'total_consume'     => $total_consume   <= 0 ? 0 : $total_consume, //dyg_jzw 20150623 累计消费总计扣除相应款项
		));
		$isSuccess = $memberDB->update('user_id = '.$user_id);

		//积分记录日志
		$pointConfig = array(
			'user_id' => $user_id,
			'point'   => '-'.$orderRow['point'],
			'log'     => '退款订单号:'.$orderRow['order_no'].'中的商品，减掉积分 -'.$orderRow['point'],
		);
		$pointObj = new Point();
		$pointObj->update($pointConfig);

		//dyg_jzw 20150813 发生退款，将扣除部分冻结预存款
		$accountFreezeObj = new IModel('account_freeze_log');
		//是否存在冻结预存款
		$accountFreezeRow = $accountFreezeObj->getObj("user_id = {$user_id} and type=0 and order_id = {$order_id}"); //dyg_jzw 20161216 增加冻结类型

		if($accountFreezeRow && $accountFreezeRow['status'] != 1) //未成功返还状态 
		{
			//冻结预存款取消返回
			$cancel_commission = 0;

			$log = new AccountLog();

			if ($orderStatus == 6) //完全退款
			{
				//取消冻结预存款
				$cancel_commission = $accountFreezeRow['amount'];
				$log->freeze_cancel($accountFreezeRow['id']);
			}
			else if ($orderStatus == 7 && $orderRow['commission'] > 0) //部分退款
			{
				//退款部分的预存款
				$cancel_commission = $orderRow['commission'];
				//部分取消冻结预存款
				$log->freeze_part_cancel($accountFreezeRow['id'], $cancel_commission);
			}
			
			if ($cancel_commission > 0) 
			{
				//推荐用户冻结预存款减少
				$dataArray = array(
					'balance_freeze' => 'balance_freeze - '.$cancel_commission
				);
				$memberDB->setData($dataArray);
				$memberDB->update('user_id = '.$accountFreezeRow['to_user_id'],'balance_freeze');
			}
			
		}

		//dyg_jzw 20161216 是否存在【高返】自身返利
		$myFreezeRow = $accountFreezeObj->getObj("user_id = {$user_id} and type=1 and order_id = {$order_id}"); //dyg_jzw 20161216 增加冻结类型
		if($myFreezeRow && $myFreezeRow['status'] != 1) //未成功返还状态 
		{
			//冻结【高返】预存款取消返回
			$cancel_commission = 0;

			$log = new AccountLog();

			if ($orderStatus == 6) //完全退款
			{
				//取消冻结预存款
				$cancel_commission = $myFreezeRow['amount'];
				$log->freeze_cancel($myFreezeRow['id']);
			}
			else if ($orderStatus == 7 && $orderRow['my_commission'] > 0) //部分退款
			{
				//退款部分的预存款
				$cancel_commission = $orderRow['my_commission'];
				
				//相同金额, 直接取消
				if ($cancel_commission == $myFreezeRow['amount'])
				{
					$log->freeze_cancel($myFreezeRow['id']);
				}
				else
				{
					//部分取消冻结预存款
					$log->freeze_part_cancel($myFreezeRow['id'], $cancel_commission);
				}
			}
			
			if ($cancel_commission > 0) 
			{
				//用户自身冻结预存款减少
				$dataArray = array(
					'balance_freeze' => 'balance_freeze - '.$cancel_commission
				);
				$memberDB->setData($dataArray);
				$memberDB->update('user_id = '.$myFreezeRow['to_user_id'],'balance_freeze');
			}
			
		}

		//生成订单日志
		if($type == 'admin')
		{
			$adminObj  = new IModel('admin');
			$adminRow  = $adminObj->getObj('id = '.$authorId);
			$authorName= $adminRow['admin_name'];
		}
		else if($type == 'seller')
		{
			$sellerObj = new IModel('seller');
			$sellerRow = $sellerObj->getObj('id = '.$authorId);
			$authorName= $sellerRow['seller_name'];
		}
		$tb_order_log = new IModel('order_log');
		$tb_order_log->setData(array(
			'order_id' => $order_id,
			'user'     => $authorName,
			'action'   => '退款',
			'result'   => '成功',
			'note'     => '订单【'.$order_no.'】退款，退款金额：￥'.$amount,
			'addtime'  => ITime::getDateTime(),
		));
		$refundResult = $tb_order_log->add();

		//处理退款金额最终流向
		switch($way)
		{
			case "balance":
			{
				//用户余额进行的操作记入account_log表
				$log = new AccountLog();
				$config = array(
					'user_id'  => $user_id,
					'event'    => 'drawback', //withdraw:提现,pay:余额支付,recharge:充值,drawback:退款到余额
					'num'      => $amount, //整形或者浮点，正为增加，负为减少
					'order_no' => $order_no // drawback类型的log需要这个值
				);

				if($type == 'admin')
				{
					$config['admin_id'] = $authorId;
				}
				else if($type == 'seller')
				{
					$config['seller_id'] = $authorId;
				}
				$log->write($config);
			}
			break;

			case "other":
			{

			}
			break;

			case "origin":
			{

			}
			break;
		}
		return $refundResult;
	}
	
	/**
	 * @brief 检查订单是否重复
	 * @param array $checkData 检查的订单数据
	 * @param array $goodsList 购买的商品数据信息
	 */
	public static function checkRepeat($checkData,$goodsList)
	{
    	$checkWhere = array();
    	foreach($checkData as $key => $val)
    	{
    		if(!$val)
    		{
				return "请仔细填写订单所需内容";
    		}
    		$checkWhere[] = "`".$key."` = '".$val."'";
    	}
    	$checkWhere[] = " NOW() < date_add(create_time,INTERVAL 2 MINUTE) "; //在有限时间段内生成的订单
    	$checkWhere[] = " pay_status != 1 ";//是否付款
		$where = join(" and ",$checkWhere);

		//查询订单数据库
		$orderObj  = new IModel('order');
    	$orderList = $orderObj->query($where);

    	//有重复下单的嫌疑
    	if($orderList)
    	{
    		//当前购买的
    		$nowBuy = "";
    		foreach($goodsList as $key => $val)
    		{
    			$nowBuy .= $val['goods_id']."@".$val['product_id'];
    		}

			//已经购买的
			$orderGoodsDB = new IModel('order_goods');
			foreach($orderList as $key => $val)
			{
	    		$isBuyed = "";
	    		$orderGoodsList = $orderGoodsDB->query("order_id = ".$val['id']);
	    		foreach($orderGoodsList as $k => $item)
	    		{
	    			$isBuyed .= $item['goods_id']."@".$item['product_id'];
	    		}

	    		if($nowBuy == $isBuyed)
	    		{
					return "您所提交的订单重复，频率太高，请稍候再试...";
	    		}
			}
    	}
    	return true;
	}
	/**
	 * @brief  设置批量子订单
	 * @param  array $orderKey   批量订单KEY
	 * @param  array $orderArray 订单号数组
	 * @return boolean
	 */
	public static function setBatch($orderKey,$orderArray)
	{
		$cacheObj = new ICache('file');
		return $cacheObj->set($orderKey,$orderArray);
	}
	/**
	 * @brief  获取批量子订单
	 * @param  array $orderKey 批量订单KEY
	 * @return array 订单号数组array('订单号' => '金额')
	 */
	public static function getBatch($orderKey)
	{
		$result   = array();//订单号=>订单金额
		$cacheObj = new ICache('file');
		$orderList= $cacheObj->get($orderKey);
		if($orderList)
		{
			$orderDB = new IModel('order');
			foreach($orderList as $key => $val)
			{
				$orderRow = $orderDB->getObj('order_no = "'.$val.'"');
				if($orderRow)
				{
					$result[$val] = $orderRow['order_amount'];
				}
			}
		}
		return $result;
	}
	/**
	 * @brief 获取退款方式文字
	 * @param string $code 编码
	 */
	public static function refundWay($code)
	{
		$result = array('balance' => '余额退款','other' => '其他方式','origin' => '原路退款');
		return isset($result[$code]) ? $result[$code] : "未知";
	}
}