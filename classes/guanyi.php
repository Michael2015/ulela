<?php
/**
 * @copyright (c) 2016 [group]
 * @file guanyi.php
 * @brief 管易添单处理
 * @author dyg_jzw
 * @date 20160216
 * @version 1.0
 */
class Guanyi
{
	private static $guanyi_url = 'http://api.guanyierp.com/rest/erp_open';
	private static $guanyi_appkey = '174560';
	private static $guanyi_sessionkey = '3827c6112429468892611beecadc00ff';
	private static $guanyi_secret = '2d74c93763b44fe690a876981b265b49';

	//错误信息
	private $error  = '';

	//新增订单
	public static function addOrder($order, $user, $goods) 
	{
	    $data = array();
	    $data['appkey'] = self::$guanyi_appkey;
	    $data['sessionkey'] = self::$guanyi_sessionkey;
	    $data['method'] = 'gy.erp.trade.add';
	    $data['order_type_code'] = 'Sales'; //订单类型
	    $data['refund'] = 0; //退款状态
	    $data['warehouse_code'] = '001'; //仓库code
	    $data['cod'] = false; //货到付款
	    $data['shop_code'] = '10'; //店铺code

	    //根据快递方式 填写物流公司code
	    switch ($order['distribution']) 
	    {
	    	case 7: //联邦快递
	    		$data['express_code'] = 'fedex'; 
	    		break;
	    	case 6: //圆通快递
	    		$data['express_code'] = 'yto';
	    		break;
	    	case 5: //申通快递
	    		$data['express_code'] = 'sto';
	    		break;
	    	case 4: //顺丰快递
	    		$data['express_code'] = 'sf';
	    		break;
	    	case 9: //顺丰到付
	    		$data['express_code'] = 'sfdf';
	    		break;
	    	case 3: //自提
	    	case 2: //货到付款
	    		$data['express_code'] = 'dummy';
	    		break;
	    	case 8: //EMS标准
	    		$data['express_code'] = 'ems';
	    		break;
	    	case 1: //EMS经济
	    	default:
	    		$data['express_code'] = 'eyb';
	    		break;
	    }

	    $data['platform_code'] = $order['order_no']; //平台单号
	    $data['deal_datetime'] = $order['create_time']; //拍单时间
	    $data['pay_datetime'] = $order['pay_time']; //付款时间
	    $data['post_fee'] = floatval($order['real_freight']); //物流费用
	    $data['cod_fee'] = 0; //货到付款费用
	    $data['discount_fee'] = floatval($order['promotions'] - $order['discount']); //让利金额
	    //$data['tag_code'] = ''; //标记代码
	    //$data['plan_delivery_date'] = ''; //预计发货时间
	    $data['vip_code'] = $user['username']; //会员code
	    $data['buyer_memo'] =  str_replace("/\s/", " ", $order['postscript']); //买家留言

	    if ($order['distribution'] == 3) 
	    {
	    	$data['seller_memo'] = '自提验证码: '.$order['checkcode']; //卖家备注
	    }
	    else
	    {
	    	$data['seller_memo'] = '';
	    }

	    if ($order['takeself'] > 0) //根据自提点标记订单
	    {
	    	switch ($order['takeself'])
	    	{
	    		case 1:
	    			$data['tag_code'] = 'zt_kejiyuan';
	    			break;
	    		case 2:
	    			$data['tag_code'] = 'zt_ruyuan';
	    			$data['warehouse_code'] = '003'; //自动分配至乳源仓库
	    			$data['seller_memo'] = '【乳源】'.$data['seller_memo'];
	    			break;
	    		case 3:
	    			$data['tag_code'] = 'zt_yidu';
	    			break;
	    	}
	    }
	    
	    $data['seller_memo_late'] = '';
	    $data['receiver_name'] = $order['accept_name']; //收货人姓名
	    $data['receiver_phone'] = $order['telphone']; //收货人电话
	    $data['receiver_mobile'] = $order['mobile']; //收货人手机
	    $data['receiver_address'] = $order['address']; //收货人地址

	    //根据地址id，获取地址信息
	    $area_data = area::name($order['province'],$order['city'],$order['area']);
	    $data['receiver_province'] = $area_data[$order['province']]; //收货人-省
	    $data['receiver_city'] = $area_data[$order['city']]; //收货人-市
	    $data['receiver_district'] = $area_data[$order['area']]; //收货人-区

	    //商品明细
	    $details = array();
	    if ($goods) 
	    {
	    	//获取用户信息
	    	if ($user['username'] != 'guest')
	    	{
	    		$memberObj  = new IModel('member');
				$memberRow  = $memberObj->getObj('user_id = '.$order['user_id'],'group_id');
	    	}

	    	foreach ($goods as $_order_good) 
		    {
		    	//是否业务员订单
		    	if (isset($memberRow) && intval($memberRow['group_id']) == 15)
		    	{
		    		$data['tag_code'] = 'yewuyuan'; //标记代码
		    	}

		    	//是否含有鲜虫草
		    	if ($_order_good['goods_id'] == 35 || $_order_good['goods_id'] == 133 || $_order_good['goods_id'] == 193)
		    	{
		    		$data['tag_code'] = 'xiancao'; //标记代码
		    		$data['warehouse_code'] = '001'; //鲜草只能是总仓
		    	}

		    	//规格json解析
		    	$goods_array = json_decode($_order_good['goods_array'], true);
		    	$details[] = array(
			        'item_code' => $goods_array['goodsno'], //商品代码
			        'oid' => $order['order_no'].'_'.$_order_good['goods_id'], //子订单号ID
			        //'sku_code' => '',
			        'price' => $_order_good['real_price'], //实际单价
			        'qty' => $_order_good['goods_nums'], //数量
			        'refund' => 0, //是否退款
			        'note' => $goods_array['name'].' '.$goods_array['value']
			    );
		    }
	    }

	    //dyg_jzw 20170112 购买虫草复方 赠送优惠券
	    /*$_fufang = array(72);
	    if (time() > strtotime("2017-01-12 08:00:00") && time() < strtotime("2017-01-23 00:00:00"))
	    {
	    	$propObj = new IModel('prop');
	    	$memberObj  = new IModel('member');

	    	foreach ($goods as $_order_good)
	    	{
	    		$goods_array = json_decode($_order_good['goods_array'], true);

	    		if (in_array($_order_good['goods_id'], $_fufang))
	    		{
	    			$count = $_order_good['goods_nums'];

	    			$tickets = $propObj->query('type=0 AND `condition`=1 AND is_userd=0 AND is_send=0', '*', '', $count);

	    			//修改优惠券 发放给用户
	    			$ticket_ids = array();
	    			foreach($tickets as $_ticket)
	    			{
	    				$dataArray = array(
                            'is_send'   => 1,
                            'user_id'   => $order['user_id']
                        );
                        $propObj->setData($dataArray);
                        $propObj->update('id = '.$_ticket['id']);

                        $ticket_ids[] = $_ticket['id'];
	    			}
	    			$ticket_ids = implode(",", $ticket_ids);

                    //更新用户prop字段
                    $memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$ticket_ids},')");
                    $memberObj->setData($memberArray);

                    $result = $memberObj->update('user_id = '.$order['user_id'],'prop');
	    		}
	    	}
		}*/

	    //dyg_jzw 20161225 年末冬季套装抽奖
	    /*$_winter_goods = array(174, 170, 175, 171, 176, 172, 168, 173, 169);

	    if (time() > strtotime("2016-12-26 10:00:00") && time() < strtotime("2017-01-01 00:00:00"))
	    {
	    	//获取奖品库存
	    	$mem_cache = new ICache('memcache');
	    	$store_winter_prize = $mem_cache->get('store_winter_prize');

	    	//初始化库存
	    	$store_winter_prize = $store_winter_prize ? $store_winter_prize : array(
	    																			'1202000229' => 57,
	    																			'1202000227' => 28,
	    																			'1202000233' => 109, //北美盒装
	    																			'1202000236' => 6, //豆乳盒装
	    																			'1202000235' => 94, //圣白莲盒装
	    																			'1202000154' => 92,
	    																			'1202000286' => 38,
	    																			'1201990030' => 30,
	    																			'1201010085' => 3536,
	    																			'1202000351' => 5000
	    																		);

	    	//优惠券获取
	    	$couponDB = new IModel('coupon_code');

	    	//中奖结果
			$got_prize_list = array(
									'150quan' => array(),
									'yue1' => array('name' => '约随机单片', 'nums'=>0, 'no'=>array('1202000229','1202000227')),
									'yue3' => array('name' => '约随机盒装', 'nums'=>0, 'no'=>array('1202000236','1202000233','1202000235')),
									'ka1' => array('name' => '卡帕藻单片', 'nums'=>0, 'no'=>array('1202000154')),
									'shui1' => array('name' => '调理水体验装', 'nums'=>0, 'no'=>array('1202000286')),
									'mi' => array('name' => '西藏大米1500g', 'nums'=>0, 'no'=>array('1201990030')),
									'chongcao' => array('name' => '50%虫草礼盒', 'nums'=>0, 'no'=>array('1201010085')),
									'hushou' => array('name' => '护手霜', 'nums'=>0, 'no'=>array('1202000351'))
								);
			

	    	//是否购买了冬季套装
	    	foreach ($goods as $_order_good)
	    	{
	    		$goods_array = json_decode($_order_good['goods_array'], true);

	    		if (in_array($_order_good['goods_id'], $_winter_goods))
	    		{
	    			//购买单品还是5件套
	    			if (substr($goods_array['goodsno'], -2) == "x5")
	    			{
	    				$prize_list = array();
	    				//5件套
	    				if ($_order_good['real_price'] <= 550)
	    				{
	    					$prize_list = array(
	    									'yue1' => 50,
	    									'yue3' => 650,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 20,
	    									'hushou' => 130
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 750)
	    				{
	    					$prize_list = array(
	    									'yue1' => 50,
	    									'yue3' => 500,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 100,
	    									'hushou' => 200
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 1000)
	    				{
	    					$prize_list = array(
	    									'yue1' => 50,
	    									'yue3' => 450,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 150,
	    									'hushou' => 200
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 1500)
	    				{
	    					$prize_list = array(
	    									'yue1' => 50,
	    									'yue3' => 400,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 200,
	    									'hushou' => 200
	    								);
	    				}
	    				elseif($_order_good['real_price'] > 1500)
	    				{
	    					$prize_list = array(
	    									'yue1' => 50,
	    									'yue3' => 350,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 250,
	    									'hushou' => 200
	    								);
	    				}
	    			}
	    			else
	    			{
	    				//单件套
	    				if ($_order_good['real_price'] <= 110 && $_order_good['real_price'] >= 50)
	    				{
	    					$prize_list = array(
	    									'150quan' => 300,
	    									'yue1' => 50,
	    									'yue3' => 50,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 5,
	    									'hushou' => 445
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 150)
	    				{
	    					$prize_list = array(
	    									'150quan' => 300,
	    									'yue1' => 50,
	    									'yue3' => 100,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 20,
	    									'hushou' => 380
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 150)
	    				{
	    					$prize_list = array(
	    									'150quan' => 300,
	    									'yue1' => 50,
	    									'yue3' => 100,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 20,
	    									'hushou' => 380
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 200)
	    				{
	    					$prize_list = array(
	    									'150quan' => 300,
	    									'yue1' => 50,
	    									'yue3' => 200,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 30,
	    									'hushou' => 270
	    								);
	    				}
	    				elseif($_order_good['real_price'] <= 300)
	    				{
	    					$prize_list = array(
	    									'150quan' => 300,
	    									'yue1' => 50,
	    									'yue3' => 300,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 40,
	    									'hushou' => 160
	    								);
	    				}
	    				elseif($_order_good['real_price'] > 300)
	    				{
	    					$prize_list = array(
	    									'150quan' => 300,
	    									'yue1' => 50,
	    									'yue3' => 400,
	    									'ka1' => 50,
	    									'shui1' => 50,
	    									'mi' => 50,
	    									'chongcao' => 50,
	    									'hushou' => 50
	    								);
	    				}

	    			}

	    			//生成中间几率
	    			if ($prize_list)
	    			{
	    				$prize_hit_nums = array();
	    				$_index = 0;
	    				foreach ($prize_list as $key => $value)
	    				{
		    				$prize_hit_nums[$key] = array('min' => $_index+1, 'max' => $value + $_index);
		    				$_index += $value;
		    			}

		    			for ($i=0; $i < $_order_good['goods_nums']; $i++)
		    			{ 
		    				//是否中奖优惠券
		    				$is_coupon = false;

		    				//未设置券概率，则100%获得150的券
		    				if (!isset($prize_list['150quan']))
		    				{
		    					$is_coupon = true;
		    				}

		    				//是否中奖
		    				$rand_num = mt_rand(1,1000);

		    				foreach ($prize_hit_nums as $_prize => $_hit)
		    				{
		    					if ($rand_num >= $_hit['min'] && $rand_num <= $_hit['max'])
		    					{
		    						//中奖优惠券
		    						if ($_prize == '150quan')
		    						{
		    							$is_coupon = true;
		    						}
		    						else
		    						{
		    							$got_prize_list[$_prize]['nums']++; 
		    						}
		    						break;
		    					}
		    				}

		    				if ($is_coupon)
		    				{
		    					//获取数据库的券代码
		    					$couponRow = $couponDB->getObj("is_send = 0 and type='youzan'");
		    					if ($couponRow)
		    					{
		    						$got_prize_list['150quan'][] = $couponRow['code'];
		    						//标记发送
		    						$couponDB->setData(array(
		    											'user_id' => $order['user_id'],
		    											'is_send' => 1,
		    											'send_time' => date("Y-m-d H:i:s")
		    										));
		    						$couponDB->update("id = ".$couponRow['id']);
		    					}
		    				}
		    			}	
	    			}
	    		}
	    	}

	    	//奖品发放
			$coupon_list = array();
			$order_prize_list = array();
			foreach ($got_prize_list as $_prize => $value)
			{
				if ($_prize == '150quan')
				{
					$coupon_list = $value;
				}
				elseif($value['nums'] > 0)
				{
					//中奖该商品的数量
					$prize_num = $value['nums'];

					//是否需要护手霜来替代库存不足问题
					$is_tmp_hushou = 0; 

					//是否发放了奖品
					$is_got_prize = false;

					foreach ($value['no'] as $_item_no)
					{
						//奖品库存是否大于0
						if ($store_winter_prize[$_item_no] > 0)
						{
							$is_got_prize = true;

							if ($prize_num < $store_winter_prize[$_item_no])
							{
								$got_prize_num = $prize_num;
							}
							else //库存不足，需要护手霜来发放
							{
								$got_prize_num = $store_winter_prize[$_item_no];
								$is_tmp_hushou = $prize_num - $got_prize_num;
							}

							//扣除奖品库存
							$store_winter_prize[$_item_no] -= $got_prize_num;

							//护手霜合并数量
							if ($_item_no == '1202000351')
							{
								$is_tmp_hushou += $got_prize_num;
							}
							else
							{
								$order_prize_list[] = $value['name']."×".$got_prize_num;
								//添加赠品到管易
								$details[] = array(
							        'item_code' => $_item_no, //商品代码
							        'oid' => $order['order_no'].'_'.$_item_no, //子订单号ID
							        'price' => 0, //实际单价
							        'qty' => $got_prize_num , //数量
							        'refund' => 0, //是否退款
							        'note' => '2016冬季套餐年终活动中奖赠品'
							    );

							    if ($_prize == "chongcao")
							    {
							    	//如果中奖虫草复方，发送到企业号通知
								    require_once(dirname(__FILE__)."/../plugins/myteam_wechat_qy/wechat_qy/wechat_qy.php");
									$wechat_qy = new wechat_qy();

									$wechat_qy_corpid = "wx831312951ed9bf55";
									$wechat_qy_secret = "7t0hVGOa-ibA3PtzscfgQSHVinnH7C5_6PlbZfEuTNV7HSi8PYuQF_HHK37BkNuK";

									$message = "恭喜".$user['username']."参与冬季套装大抽奖，抽中了价值2998元的【50%冬虫夏草复方礼盒】".$got_prize_num."盒！\n本活动100%中奖，将持续至2016-12-31 23:59:59。（偷偷告诉你购买【五套优惠装】中奖概率更高哦！）";

									//发送微信企业号信息
									$message_body = array(
										                'touser' => '@all',
										                'msgtype' => 'text',
										                'agentid' => '2',
										                'text' => array(
										                    'content' => $message
										                )
										            );

									$wechat_qy->access_request($wechat_qy_corpid, $wechat_qy_secret, 'message/send', $wechat_qy->replace_post($message_body));
							    }  
							}

							
						    break;
						}
					}

					//预设的库存不足，发放护手霜
					if (!$is_got_prize)
					{
						$is_tmp_hushou += $prize_num;
					}

					if ($is_tmp_hushou)
					{
						$details[] = array(
						        'item_code' => '1202000351', //商品代码
						        'oid' => $order['order_no'].'_1202000351', //子订单号ID
						        'price' => 0, //实际单价
						        'qty' => $is_tmp_hushou , //数量
						        'refund' => 0, //是否退款
						        'note' => '2016冬季套餐年终活动中奖赠品'
						    );
					}
				}
			}

			//发送短信通知中奖
			$sms_mobile = $user['username'];
			if ($coupon_list)
			{
				$coupon_str =  implode(",", $coupon_list);
				$smsContent = "您获得".count($coupon_list)."张\"东阳光鲜草特级品礼盒装\"150元抵用券：{$coupon_str} 每次只可使用一个，微店(http://t.cn/RIjaOuH)使用，有效期至2016.12.31。温馨提醒：可先下单使用再预约提货时间";
				Hsms::send($sms_mobile, $smsContent, 0);
			}

			if ($order_prize_list)
			{
				$prize_str =  implode(",", $order_prize_list);
				$smsContent = "您的订单（尾号".substr($order['order_no'], -4)."）参与了年终100%得奖活动，恭喜获得随单赠品：".$prize_str;
				Hsms::send($sms_mobile, $smsContent, 0);
			}
			//库存保存
			$mem_cache->set('store_winter_prize', $store_winter_prize, 3600*24*30);

	    }*/


	    //dyg_jzw 20161109 是否随机送赋活眼膜单片
	    //赋活眼膜单片的库存
	    /*$mem_cache = new ICache('memcache');
	    $store_1202000218 = $mem_cache->get('store_1202000218');

	    //初始化
	    $store_1202000218 = ($store_1202000218 === null) ? 464 : $store_1202000218;

	    if (time() > strtotime("2016-11-10 10:00:00") && time() < strtotime("2016-11-21 00:00:00") && $store_1202000218 && !$order['is_cbe'] )
	    {
	    	//抽奖次数
	    	$award_times = 0;

	    	//中奖次数
	    	$hit_award = 0;

	    	//订单金额
	    	if($order['order_amount'] > 2000)
	    	{
	    		$award_times = 13;
	    	}
	    	elseif($order['order_amount'] > 1500)
	    	{
	    		$award_times = 12;
	    	}
	    	elseif($order['order_amount'] > 1000)
	    	{
	    		$award_times = 11;
	    	}
	    	else
	    	{
	    		$award_times = floor($order['order_amount'] / 100) + 1;
	    	}

	    	//中奖数字
	    	$hit_array = array(2,3,5,9);

	    	for ( $times = 0; $times < $award_times; $times++ )
	    	{ 
	    		$rand_num = mt_rand(1,10);

	    		if (in_array($rand_num, $hit_array))
	    		{
	    			$hit_award++;
	    		}
	    	}

	    	if ($hit_award) //中奖
	    	{
	    		//库存减少
	    		if ($hit_award > $store_1202000218)
	    		{
	    			$hit_award = $store_1202000218;
	    		}
	    		$store_1202000218 = $store_1202000218 - $hit_award;
	    		$mem_cache->set('store_1202000218', $store_1202000218, 3600*24*30);

	    		//通知中奖
	    		$smsContent = smsTemplate::award_hit_201611(array(
	    															'{orderNo}' => substr($order['order_no'], 16),
	    															'{hit_times}' => $hit_award,
	    															'{unit}' => '对',
	    															'{goods_name}' => 'RUNA赋活修复眼膜',
	    															'{others}' => '，产品期限至2016/12/18，请尽早使用',
	    														));
						
	    		
	    		//管易增加赠品
	    		$details[] = array(
			        'item_code' => '1202000218', //商品代码
			        'oid' => $order['order_no'].'_1202000218', //子订单号ID
			        'price' => 0, //实际单价
			        'qty' => $hit_award, //数量
			        'refund' => 0, //是否退款
			        'note' => '【双11随单送】赋活眼膜单片'
			    );
	    	}
	    	else //未中奖
	    	{
	    		$smsContent = smsTemplate::award_unhit_201611(array(
	    															'{orderNo}' => substr($order['order_no'], 16)
	    														));
	    	}
	    	$sms_mobile = $user['username'];
	    	Hsms::send($sms_mobile, $smsContent, 0);
	    }*/

	    $data['details'] = $details;

	    //是否跨境电商
	    if ($order['is_cbe'])
	    {
	    	$data['tag_code'] = 'cbe'; //标记代码
	    }

	    //支付明细
	    $payments = array();
	    switch ($order['pay_type']) 
	    {
	    	case 0: //货到付款
	    		$pay_type_code = 'cod';
	    		break;
	    	case 1: //预存款
	    		$pay_type_code = 'balance';
	    		break;
	    	case 2: //网银在线
	    		$pay_type_code = 'wangyin';
	    		break;
	    	case 3: //银联支付
	    	case 4: 
	    	case 16:
	    		$pay_type_code = 'yinlian';
	    		break;
	    	case 5: //财付通
	    		$pay_type_code = 'caifutong';
	    		break;
	    	case 7: //支付宝
	    	case 8: //支付宝
	    	case 9: //支付宝
	    	case 10: //支付宝
	    	case 15: //支付宝
	    		$pay_type_code = 'zhifubao';
	    		break;
	    	case 12: //微信
	    	case 13:
	    		$pay_type_code = 'weixin';
	    		break;
	    	case 14: //线下支付
	    	default:
	    		$pay_type_code = 'cash';
	    		break;
	    }

	    //支付信息
	    $payments[] = array(
	        'pay_type_code' => $pay_type_code,
	        'payment' => floatval($order['order_amount']),
	        'pay_code' => $order['trade_no'],
	        'paytime' => strtotime($order['pay_time']) * 1000
	    );
	    $data['payments'] = $payments;

	    //发票信息
	    $invoices = array();
	    if ($order['invoice']) 
	    {
	    	$invoices[] = array(
		        'invoice_type' => 1,
		        'invoice_title' => $order['invoice_title'],
		        'invoice_content' => '明细',
		        'invoice_amount' => $order['order_amount']
		    );
	    }
	    else
	    {
	    	$invoices[] = array(
		        'invoice_type' => 1
		    );
	    }
	    
	    $data['invoices'] = $invoices;
	    $data['sign'] = self::sign($data, self::$guanyi_secret);

	    //print_r($data);

	    return json_decode(self::mycurl(self::$guanyi_url, $data), true);
	}

	//更新订单退款状态
	public static function updateOrderRefund($order_no, $goods_id, $refund_state) 
	{
	    $data = array();
	    $data['appkey'] = self::$guanyi_appkey;
	    $data['sessionkey'] = self::$guanyi_sessionkey;
	    $data['method'] = 'gy.erp.trade.refund.update';
	    $data['tid'] = $order_no;
	    $data['oid'] = $order_no.'_'.$goods_id;
	    $data['refund_state'] = intval($refund_state);
	    $data['sign'] = self::sign($data, self::$guanyi_secret);
	    return json_decode(self::mycurl(self::$guanyi_url, $data), true);
	}

	//获取订单
	public static function getSendOrder($order_no)
	{
		$data = array();
	    $data['appkey'] = self::$guanyi_appkey;
	    $data['sessionkey'] = self::$guanyi_sessionkey;
	    $data['method'] = 'gy.erp.trade.deliverys.get';
	    $data['outer_code'] = $order_no;
	    $data['sign'] = self::sign($data, self::$guanyi_secret);
	    return json_decode(self::mycurl(self::$guanyi_url, $data),true);
	}

	//根据商品代码查询库存
	public static function getStock($page = 1)
	{
		$data = array();
	    $data['appkey'] = self::$guanyi_appkey;
	    $data['sessionkey'] = self::$guanyi_sessionkey;
	    $data['method'] = 'gy.erp.stock.get';
	    $data['page_no'] = $page;
	    $data['page_size'] = 100; //每页大小
	    $data['warehouse_code'] = '001'; //	仓库code
	    $data['sign'] = self::sign($data, self::$guanyi_secret);
	    return json_decode(self::mycurl(self::$guanyi_url, $data),true);
	}

	private static function mycurl($url, $data) 
	{
	    /*$data_string = self::json_encode_ch($data);
	    //echo 'request: ' . $data_string . "\n";
	    $data_string = urlencode($data_string);
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	        'Content-Type:text/json;charset=utf-8',
	        'Content-Length:' . strlen($data_string)
	    ));
	    $content = curl_exec($ch);
	    curl_close($ch);

	    return $content;*/

	    //测试不上传
    	return json_encode(array("success"=>true));
	}

	private static function sign($data, $secret) 
	{
	    if (empty($data)) {
	        return "";
	    }
	    unset($data['sign']); //可选，具体看传参
	    $data = self::json_encode_ch($data);
	    $sign = strtoupper(md5($secret . $data . $secret));
	    return $sign;
	}

	private static function json_encode_ch($arr) 
	{
	    return urldecode(json_encode(self::url_encode_arr($arr)));
	}

	private static function url_encode_arr($arr) 
	{
	    if (is_array($arr)) {
	        foreach ($arr as $k => $v) {
	            $arr[$k] = self::url_encode_arr($v);
	        }
	    } elseif (!is_numeric($arr) && !is_bool($arr)) {
	        $arr = urlencode($arr);
	    }
	    return $arr;
	}
}