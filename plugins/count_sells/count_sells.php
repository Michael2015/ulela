<?php
/**
 * @copyright (c) 2016 dyg.cn
 * @file count_sells.php
 * @brief 统计业绩设置
 * @author jzw
 * @date 2016/9/12
 * @version 1.0
 */
class count_sells extends pluginBase
{
	//插件注册
	public function reg()
	{
		//后台管理团队信息
		plugin::reg("onSystemMenuCreate",function(){
			$link = "/plugins/count_sells_list";
			Menu::$menu["插件"]["插件管理"][$link] = $this->name();
		});

		plugin::reg("onBeforeCreateAction@plugins@count_sells_list",function(){
			self::controller()->count_sells_list = function(){$this->count_sells_list();};
		});

		plugin::reg("onBeforeCreateAction@plugins@count_sells_edit",function(){
			self::controller()->count_sells_edit = function(){$this->count_sells_edit();};
		});

		plugin::reg("onBeforeCreateAction@plugins@count_sells_update",function(){
			self::controller()->count_sells_update = function(){$this->count_sells_update();};
		});

		plugin::reg("onBeforeCreateAction@plugins@count_sells_del",function(){
			self::controller()->count_sells_del = function(){$this->count_sells_del();};
		});

		//个人考核业绩计算
		plugin::reg("doCountSells",$this,"doCountSells");
	}

	//编辑团队信息
	public function count_sells_edit()
	{
		$id     = IFilter::act(IReq::get('id'),'int');
		$uncountData = array();
		if($id)
		{
			$countDB = new IModel('count_sells');
			$uncountRow = $countDB->getObj('id = '.$id);

			//获取用户username
			$userDB = new IModel('user');
			$userRow = $userDB->getObj("id = '{$uncountRow['user_id']}'");

			$uncountData = array(
								'id' => $id."",
								'username' => $userRow['username'],
								'count_start' => date("Y-m-d", strtotime($uncountRow['datetime']))
							);
		}
		$this->redirect('count_sells_edit',array('uncountData' => $uncountData));
	}

	//更新团队信息
	public function count_sells_update()
	{
		$id          = IFilter::act(IReq::get('id'),'int');
		$username    = trim(IFilter::act(IReq::get('username')));
		$count_start = IFilter::act(IReq::get('count_start'));

		//获取用户ID
		$userDB = new IModel('user');
		$userRow = $userDB->getObj("username = '{$username}'");

		if (empty($userRow))
		{
			die('用户名不存在');
		}

		$updateData  = array(
			'user_id'     => $userRow['id'],
			'datetime'    => date("Y-m-d", strtotime($count_start))
		);

		$countDB = new IModel('count_sells');
		$countDB->setData($updateData);
		if($id)
		{
			$countDB->update('id = '.$id);
		}
		else
		{
			$countDB->add();
		}
		$this->redirect('count_sells_list',true);
	}

	//删除团队信息
	public function count_sells_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$countDB = new IModel('count_sells');
			$countDB->del('id = '.$id);
		}
		$this->redirect('count_sells_list',true);
	}

	//不考核业绩列表
	public function count_sells_list()
	{
		$db        = new IQuery('count_sells as cs');
		$db->join  = "left join user as u on u.id = cs.user_id left join member as m on m.user_id = cs.user_id ";
		$db->fields= "cs.*,u.username,m.true_name";
		$db->order = "cs.id desc";

		$uncount_list = $db->find();
		$this->redirect('count_sells_list', array("uncount_list"=>$uncount_list));
	}

	public static function name()
	{
		return "业绩考核设置";
	}

	public static function description()
	{
		return "每月考核业绩目标与时间，以及暂时不考核的列表，考核格式: 分类1,分类2@200|分类3@500";
	}

	public static function install()
	{
		$countDB = new IModel('count_sells');
		if($countDB->exists())
		{
			return true;
		}
		$data = array(
			"comment" => self::name(),
			"column"  => array(
				"id"         => array("type" => "int(11) unsigned",'auto_increment' => 1),
				"user_id"    => array("type" => "int(11)","comment" => "队长用户ID"),
				"datetime"   => array("type" => "datetime","comment" => "不考核截止时间")
			),
			"index" => array("primary" => "id","key" => "user_id"),
		);
		$countDB->setData($data);
		return $countDB->createTable();
	}

	public static function uninstall()
	{
		$countDB = new IModel('count_sells');
		return $countDB->dropTable();
	}

	public static function configName()
	{
		//会员组
		$groupDB = new IModel('user_group');
		$group_list = $groupDB->query(NULL, 'id, group_name', 'group_name asc');

		$result = array();

		$count_month = array();
		for ($i=1; $i < 13; $i++)
		{ 
			$count_month[$i] = $i;
		}

		foreach ($group_list as $_group)
		{
			//考核间隔
			$result["count_month_group_".$_group['id']] = array("name" => "【".$_group['group_name']."】每X月考核","type" => "select", "value" => $count_month);
			//考核业绩
			$result["count_value_group_".$_group['id']] = array("name" => "【".$_group['group_name']."】考核分类@元","type" => "text");
			//扣除经验值
			$result["count_exp_group_".$_group['id']] = array("name" => "【".$_group['group_name']."】不通过扣除经验值","type" => "text");
		}

		return $result;
	}

	//个人考核业绩计算
	public function doCountSells()
	{
		//获取插件配置
		$defConfig= $this->config();

		//前置类
		$memCache = new ICache('memcache');
		$memberDB = new IModel('member');
		$catDB = new IModel('category_extend');
		$orderDB = new IModel('order');
		$orderGoodsDB = new IModel('order_goods');
		$expObj = new Exp();

		//获取所有会员组
		$userGroupDB = new IModel('user_group');
		$group_list = $userGroupDB->query();

		//获取无需考核的会员
		$countDB = new IModel('count_sells');
		$count_date = date("Y-m-01 H:00:00", strtotime("-1 month"));
		$uncount_list = $countDB->query("datetime > '{$count_date}'");
		$uncount_uids = array();
		foreach ($uncount_list as $_uncount)
		{
			$uncount_uids[] = $_uncount['user_id'];
		}

		//当前月份-1
		$now_month = intval(date("m", strtotime("-1 month")));

		//按组别考核业绩
		foreach ($group_list as $_group)
		{
			//是否设定考核目标
			if (isset($defConfig['count_value_group_'.$_group['id']]) && $defConfig['count_value_group_'.$_group['id']])
			{
				//是否满足考核周期
				$count_month = intval($defConfig['count_month_group_'.$_group['id']]);

				if ($now_month % $count_month == 0)
				{
					//本组是否已考核
        			if ($memCache->get('count_sells_month_'.$now_month.'_group_'.$_group['id']))
        			{
        				continue;
        			}

        			//获取考核类目
        			$count_cats_arr = explode("|", $defConfig['count_value_group_'.$_group['id']]);
        			$count_cats_list = array();
        			foreach ($count_cats_arr as $_cats_arr)
        			{
        				$_tmp = explode("@", $_cats_arr);

        				$_goods_ids = array();
        				if ($_tmp)
        				{
        					if ($_tmp[0] == 0) //0代表所有分类
        					{
        						$goods_ids = $catDB->query();
        					}
        					else
        					{
        					//获取符合考核类目的产品goods_id
							$goods_ids = $catDB->query("category_id in ({$_tmp[0]})");
        					}
							foreach ($goods_ids as $_goods)
							{
								$_goods_ids[] = $_goods['goods_id'];
							}
        				}
        				
        				$count_cats_list[$_tmp[0]] = array(
        											'cat_ids' => $_tmp[0],
        											'limit_count' => $_tmp[1],
        											'goods_ids' => $_goods_ids
        										);
        			}

					//获取该组下的会员
					$user_list = $memberDB->query("group_id = {$_group['id']}", "user_id");
					$all_user_ids = array();
					foreach ($user_list as $_user)
					{
						//是否无须考核
						if (! in_array($_user['user_id'], $uncount_uids))
						{
							$all_user_ids[] = $_user['user_id'];
						}
					}
					$all_user_ids = implode(',', $all_user_ids);

					//考核周期
					$startDate = date("Y-m-01 00:00:00", strtotime("-".$count_month." month"));
					$endDate = date("Y-m-01 00:00:00");

					//获取所有已支付或已完成的订单
					$all_orders = $orderDB->query("user_id in ({$all_user_ids}) and if_del = 0 and status in (2,5,7) and pay_time > '{$startDate}' and pay_time < '{$endDate}'", 'id, user_id');

					$all_order_ids = array();
					$order_to_user = array(); //用户与订单的对应关系
					foreach ($all_orders as $_order)
					{
						$all_order_ids[] = $_order['id'];
						$order_to_user[$_order['id']] = $_order['user_id'];
					}
					$all_order_ids = implode(',', $all_order_ids);

					//根据订单获取全部订单商品
					$all_order_goods = $orderGoodsDB->query("order_id in ({$all_order_ids}) and is_send <> 2");

					$user_count_sells = array();

					//订单商品分类
					foreach ($all_order_goods as $_order_goods)
					{
						$_user_id = $order_to_user[$_order_goods['order_id']];

						//根据业绩考核分类进行
						if ( ! isset($user_count_sells[$_user_id]))
						{
							$user_count_sells[$_user_id] = array('0' => array('sell'=>0)); //大类分类id => 业绩
						}

						//是否符合统计分类
						foreach ($count_cats_list as $_c_cats_list)
						{
							if (empty($_c_cats_list['goods_ids']) || in_array($_order_goods['goods_id'], $_c_cats_list['goods_ids']) )
							{
								if ( ! isset($user_count_sells[$_user_id][$_c_cats_list['cat_ids']]))
								{
									$user_count_sells[$_user_id][$_c_cats_list['cat_ids']] = array('sell'=>0);
								}
								$user_count_sells[$_user_id][$_c_cats_list['cat_ids']]['sell'] += $_order_goods['real_price'] * $_order_goods['goods_nums'];
							}
						}
					}

					/**
			    	 * 非本期支付的退款明细
			    	 */
					$refundObj = new IQuery('order_goods as og');
					$refundObj->fields = 'og.*, o.user_id';
					$refundObj->join   = 'left join refundment_doc as rc on find_in_set(og.id ,rc.order_goods_id)
										left join order as o on o.id=rc.order_id';

					$refundObj->where  = "rc.user_id in ({$all_user_ids}) and rc.pay_status = 2 and rc.dispose_time >= '{$startDate}' and rc.dispose_time < '{$endDate}' and rc.if_del = 0  and o.pay_time < '{$startDate}'";

					$refund_arr = $refundObj->find();

					foreach ($refund_arr as $_r_order_goods)
					{
						$_user_id = $_r_order_goods['user_id'];

						//根据业绩考核分类进行
						if ( ! isset($user_count_sells[$_user_id]))
						{
							$user_count_sells[$_user_id] = array('0' => 0); //大类分类id => 业绩
						}

						//是否符合统计分类
						foreach ($count_cats_list as $_c_cats_list)
						{
							if (empty($_c_cats_list['goods_ids']) || in_array($_r_order_goods['goods_id'], $_c_cats_list['goods_ids']))
							{
								if ( ! isset($user_count_sells[$_user_id][$_c_cats_list['cat_ids']]['refund']))
								{
									$user_count_sells[$_user_id][$_c_cats_list['cat_ids']]['refund'] = 0;
								}
								$user_count_sells[$_user_id][$_c_cats_list['cat_ids']]['refund'] += $_r_order_goods['real_price'] * $_r_order_goods['goods_nums'];
							}
						}
					}

					//是否符合考核标准
					foreach ($user_count_sells as $_user_id => $_user_sells)
					{
						$is_ok = false;
						//echo "会员ID: {$_user_id}\n";
						//print_r($_user_sells);
						foreach ($_user_sells as $_cat_ids => $_count_money)
						{
							if ($count_cats_list[$_cat_ids])
							{
								$_total_money = $_count_money['sell'] - (isset($_count_money['refund']) ? $_count_money['refund'] : 0);
							
								if($count_cats_list[$_cat_ids]['limit_count'] <= $_total_money)
								{
									//达标
									$is_ok = true;
								}
							}
						}

						if (! $is_ok)
						{
							if (! $memCache->get('count_sells_month_'.$now_month.'_user_'.$_user_id))
							{
								//扣除经验值
								$memCache->set('count_sells_month_'.$now_month.'_user_'.$_user_id, 1, 60*60*24*30);

								$expConfig = array(
									'user_id' 	=> $_user_id,
									'exp'   	=> '-'.$defConfig['count_exp_group_'.$_group['id']],
									'log'     	=> '上期消费未满足要求，系统自动扣除'
								);
								$expObj->update($expConfig);
							}

							echo "用户[{$_user_id}]业绩不达标，扣除经验值{$defConfig['count_exp_group_'.$_group['id']]}\n";
						}
						
					}
				}
			}

			$memCache->set('count_sells_month_'.$now_month.'_group_'.$_group['id'], 1, 60*60*24);
		}
	}
	
}