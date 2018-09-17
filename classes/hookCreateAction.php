<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file hookCreateAction.php
 * @brief 对action动作进行拦截，对部分需要钩子的action设置代码调用
 *        钩子名称为： function 控制器ID_动作ID,遇到此动作时优先调用钩子方法
 * @author nswe
 * @date 2015/5/26 23:01:46
 * @version 3.2
 */
//
//
//
//
//
//
//
//
//
//
//
// dyg_jzw 已经弃用 自动更新订单转为orderAutoUpdate的插件 20161216
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
class hookCreateAction extends IInterceptorBase
{
	//根据控制器ID和动作ID生成钩子方法名
	public static function getHookRule()
	{
		$ctrlId  = IWeb::$app->getController()->getId();
		$actionId= IWeb::$app->getController()->getAction()->getId();
		return join('_',array($ctrlId,$actionId));
	}

	//createAction拦截器统一入口
	public static function onCreateAction()
	{
		if(IClient::isWechat() == true)
		{
			wechat_facade::oauthLogin();
		}
		$hookName = self::getHookRule();
		if(method_exists(__CLASS__,$hookName))
		{
			call_user_func(array(__CLASS__,$hookName));
		}
	}

	//后台订单列表
	public static function order_order_list()
	{
		self::ucenter_order();
	}

	//用户中心订单列表
	public static function ucenter_order()
	{
		$siteConfig = new Config('site_config');
		$order_cancel_time = $siteConfig->order_cancel_time !== "" ? intval($siteConfig->order_cancel_time) : 7;
		$order_finish_time = $siteConfig->order_finish_time !== "" ? intval($siteConfig->order_finish_time) : 20;

		//订单的 自动完成 和 自动关闭
		$orderModel = new IModel('order');
		$orderCancelData  = $order_cancel_time >= 0 ? $orderModel->query(" if_del = 0 and pay_type != 0 and status in(1) and datediff(NOW(),create_time) >= {$order_cancel_time} ","id,order_no,4 as type_data") : array();
		$orderCreateData  = $order_finish_time >= 0 ? $orderModel->query(" if_del = 0 and distribution_status = 1 and status in(1,2) and datediff(NOW(),send_time) >= {$order_finish_time} ","id,order_no,5 as type_data") : array();

		$resultData = array_merge($orderCreateData,$orderCancelData);
		if($resultData)
		{
			foreach($resultData as $key => $val)
			{
				$type     = $val['type_data'];
				$order_id = $val['id'];
				$order_no = $val['order_no'];

				//order表的对象
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
	}
}