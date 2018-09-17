<?php
/**
 * @copyright Copyright(c) 2016 aircheng.com
 * @file orderAutoUpdate.php
 * @brief 订单自动更新
 * @notice 未付款取消，发货后自动确认收货
 * @author nswe
 * @date 2016/2/28 10:57:26
 * @version 4.4
 */
class orderAutoUpdate extends pluginBase
{
	//注册事件
	public function reg()
	{
		// plugin::reg("onCreateAction@order@order_list",$this,"orderUpdate");
		// plugin::reg("onCreateAction@ucenter@order",$this,"orderUpdate");
		
		// dyg_jzw 20161216 修改为cron触发
		plugin::reg("onCreateAction@guanyi_sendgoods_update@auto_update_order",$this,"orderUpdate");
	}

	//订单自动更新
	public function orderUpdate()
	{
		//获取配置信息
		$configData = $this->config();

		//按照分钟计算
		$order_cancel_time = (isset($configData['order_cancel_time']) && $configData['order_cancel_time']) ? intval($configData['order_cancel_time']) : 7*24*60;
		$order_finish_time = (isset($configData['order_finish_time']) && $configData['order_finish_time']) ? intval($configData['order_finish_time']) : 20*24*60;

		$orderModel = new IModel('order');

		//dyg_jzw 20150729 自动返佣时间
		$order_reward_time = (isset($configData['order_commission_time']) && $configData['order_commission_time']) ? intval($configData['order_commission_time']) : 31;
		$orderRewardData = $orderModel->query(" commission > 0 and status = 5 and if_del = 0  and is_reward = 0 and datediff(NOW(),completion_time) >= {$order_reward_time}","id,order_no,user_id,order_amount");

		//dyg_jzw 20161216 自动高返时间
		$order_my_reward_time = (isset($configData['order_my_commission_time']) && $configData['order_my_commission_time']) ? intval($configData['order_my_commission_time']) : 60;
		$orderMyRewardData = $orderModel->query(" my_commission > 0 and status in (5,7) and if_del = 0 and is_my_reward = 0 and datediff(NOW(),completion_time) >= {$order_my_reward_time}","id,order_no,user_id,order_amount");

		$orderCancelData  = $order_cancel_time >= 0 ? $orderModel->query(" if_del = 0 and pay_type != 0 and status in(1) and timestampdiff(minute,create_time,NOW()) >= {$order_cancel_time} ","id,order_no,4 as type_data") : array();
		$orderCreateData  = $order_finish_time >= 0 ? $orderModel->query(" if_del = 0 and distribution_status = 1 and status in(1,2) and timestampdiff(minute,send_time,NOW()) >= {$order_finish_time} ","id,order_no,5 as type_data") : array();

		$resultData = array_merge($orderCreateData,$orderCancelData);
		if($resultData)
		{
			foreach($resultData as $key => $val)
			{
				$type     = $val['type_data'];
				$order_id = $val['id'];
				$order_no = $val['order_no'];

				//oerder表的对象
				$tb_order = new IModel('order');
				$tb_order->setData(array(
					'status'          => $type,
					'completion_time' => ITime::getDateTime(),
				));
				$tb_order->update('id='.$order_id);

				//生成订单日志
				$tb_order_log = new IModel('order_log');

				//订单自动完成
				if($type=='5')
				{
					$action = '完成';
					$note   = '订单【'.$order_no.'】完成成功';

					//完成订单并且进行支付
					Order_Class::updateOrderStatus($order_no);

					//增加用户评论商品机会
					Order_Class::addGoodsCommentChange($order_id);

					$logObj = new log('db');
					$logObj->write('operation',array("系统自动","订单更新为完成",'订单号：'.$order_no));
				}
				//订单自动作废
				else
				{
					$action = '作废';
					$note   = '订单【'.$order_no.'】作废成功';

					//订单重置取消
					Order_class::resetOrderProp($order_id);

					$logObj = new log('db');
					$logObj->write('operation',array("系统自动","订单更新为作废",'订单号：'.$order_no));
				}

				$tb_order_log->setData(array(
					'order_id' => $order_id,
					'user'     => "系统自动",
					'action'   => $action,
					'result'   => '成功',
					'note'     => $note,
					'addtime'  => ITime::getDateTime(),
				));
				$tb_order_log->add();
			}
		}

		/*
		 * dyg_jzw 20150729 
		 * 自动返佣
		 */
		if ($orderRewardData) 
		{
			$refundmentModel = new IModel('refundment_doc');

			foreach ($orderRewardData as $key => $orderRow) 
			{
				//查询是否申请了退款
				$is_refund = $refundmentModel->query('order_id = '.$orderRow['id'].' AND if_del = 0');
				
				if ($is_refund) 
				{
					continue; //有退款申请则不返佣
				}


				$order_id = $orderRow['id'];
				$order_no = $orderRow['order_no'];
				$user_id = $orderRow['user_id'];

				/*
				 * dyg_jzw 20150728 
				 * 订单完成后 增加返佣
				 */
				//------------------
				//获取冻结余额表
				$accountFreezeObj = new IModel('account_freeze_log');
				$accountFreezeRow = $accountFreezeObj->getObj("user_id = {$user_id} and type = 0 and order_id = {$order_id} and status = 0","id,to_user_id,user_id,amount");

				if ($accountFreezeRow) 
				{
					//下单用户
					$userObj  = new IModel('user');
					$userRow  = $userObj->getObj('id = '.$accountFreezeRow['user_id'],'username');
						
					//给推荐人相应的返佣金额 添加日志
	                $accLog = new AccountLog();
	                $config=array(
	                    'user_id'  => $accountFreezeRow['to_user_id'],
	                    'event'    => 'add_commission',
	                    'note'     => '团队成员['.$userRow['username'].']完成订单['.substr_replace($order_no, '****',14,4).']的奖励',
	                    'num'      => $accountFreezeRow['amount']
	                );
	                $is_success = $accLog->write($config);
				
					if($is_success)
					{
						//添加返佣标记
						$orderObj = new IModel('order');
						$orderObj->setData(array(
							'is_reward' => 1
						));
						$re = $orderObj->update('id='.$order_id);

						if ($re) 
						{
							//冻结预存款日志更新状态
							$accLog->freeze_done($accountFreezeRow['id']);

							//推荐用户冻结预存款减少
							$dataArray = array(
								'balance_freeze' => 'balance_freeze - '.$accountFreezeRow['amount']
							);
							$memberObj = new IModel('member');
							$memberObj->setData($dataArray);
							$memberObj->update('user_id = '.$accountFreezeRow['to_user_id'],'balance_freeze');
						}
						
					}
				}
				//------------------
			}
		}

		/*
		 * dyg_jzw 20161216 
		 * 自动高返返利
		 */
		if ($orderMyRewardData) 
		{
			$refundmentModel = new IModel('refundment_doc');

			foreach ($orderMyRewardData as $key => $orderRow) 
			{
				//查询是否申请了退款
				$is_refund = $refundmentModel->query('order_id = '.$orderRow['id'].' AND pay_status = 0 AND if_del = 0');
				
				if ($is_refund) 
				{
					continue; //有退款申请则暂不高返
				}


				$order_id = $orderRow['id'];
				$order_no = $orderRow['order_no'];
				$user_id = $orderRow['user_id'];

				/*
				 * dyg_jzw 20161216 
				 * 订单完成后 增加高返
				 */
				//------------------
				//获取冻结余额表
				$accountFreezeObj = new IModel('account_freeze_log');
				$accountFreezeRow = $accountFreezeObj->getObj("user_id = {$user_id} and order_id = {$order_id} and type = 1 and status = 0","id,to_user_id,user_id,amount");

				if ($accountFreezeRow) 
				{
					//下单用户
					$userObj  = new IModel('user');
					$userRow  = $userObj->getObj('id = '.$accountFreezeRow['user_id'],'username');
						
					//给推荐人相应的返佣金额 添加日志
	                $accLog = new AccountLog();
	                $config=array(
	                    'user_id'  => $accountFreezeRow['to_user_id'],
	                    'event'    => 'add_commission',
	                    'note'     => '我['.$userRow['username'].']完成订单['.$order_no.']的奖励',
	                    'num'      => $accountFreezeRow['amount']
	                );
	                $is_success = $accLog->write($config);
				
					if($is_success)
					{
						//添加高返标记
						$orderObj = new IModel('order');
						$orderObj->setData(array(
							'is_my_reward' => 1
						));
						$re = $orderObj->update('id='.$order_id);

						if ($re) 
						{
							//冻结预存款日志更新状态
							$accLog->freeze_done($accountFreezeRow['id']);

							//用户自身冻结预存款减少
							$dataArray = array(
								'balance_freeze' => 'balance_freeze - '.$accountFreezeRow['amount']
							);
							$memberObj = new IModel('member');
							$memberObj->setData($dataArray);
							$memberObj->update('user_id = '.$accountFreezeRow['to_user_id'],'balance_freeze');
						}
						
					}
				}
				//------------------
			}
		}
	}

	/**
	 * @brief 默认插件参数信息，写入到plugin表config_param字段
	 * @return array
	 */
	public static function configName()
	{
		return array(
			"order_finish_time" => array("name" => "已发货订单 X(分钟)自动完成","type" => "text","pattern" => "int"),
			"order_cancel_time" => array("name" => "未付款订单 X(分钟)自动取消","type" => "text","pattern" => "int"),
			"order_commission_time" => array("name" => "确认收货后 X(天)自动向上返佣","type" => "text","pattern" => "int"),
			"order_my_commission_time" => array("name" => "确认收货后 X(天)自动高返","type" => "text","pattern" => "int"),
		);
	}

	/**
	 * @brief 插件名字
	 * @return string
	 */
	public static function name()
	{
		return "订单自动完成、取消和返佣";
	}

	/**
	 * @brief 插件描述
	 * @return string
	 */
	public static function description()
	{
		return "订单自动完成和取消。1，已经发货的订单会在X分钟后自动完成；2，未付款的订单会在X分钟后自动取消；3，确认收货后自动返佣";
	}
}