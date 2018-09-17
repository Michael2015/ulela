<?php
/**
 * @brief 消息模块
 * @class Message
 * @note  后台
 */
class Message extends IController implements adminAuthorization
{
	public $checkRight = 'all';
	public $layout     = 'admin';
	private $data      = array();

	function init()
	{

	}

	//删除电子邮箱订阅
	function registry_del()
	{
		$ids = IFilter::act(IReq::get('id'),'int');
		if(!$ids)
		{
			$this->redirect('registry_list',false);
			Util::showMessage('请选择要删除的邮箱');
			exit;
		}

		if(is_array($ids))
		{
			$ids = join(',',$ids);
		}

		$registryObj = new IModel('email_registry');
		$registryObj->del('id in ('.$ids.')');
		$this->redirect('registry_list');
	}

	/**
	 * @brief 删除登记的到货通知邮件
	 */
	function notify_del()
	{
		$notify_ids = IFilter::act(IReq::get('check'),'int');
		if($notify_ids)
		{
			$ids = join(',',$notify_ids);
			$tb_notify = new IModel('notify_registry');
			$where = "id in (".$ids.")";
			$tb_notify->del($where);
		}
		$this->redirect('notify_list');
	}

	/**
	 * @brief 发送到货通知邮件
	 */
	function notify_email_send()
	{
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

		$notify_ids = IFilter::act(IReq::get('notifyid'));

		//dyg_jzw 20161128 如果不指定 则获取未通知的情况
		if (empty($notify_ids))
		{
			$notifyObj = new IQuery("notify_registry as notify");
			$notifyObj->where = "notify_status < 1 and email <> ''";
			$notifyObj->fields = 'id';
			$unnotify_list = $notifyObj->find();

			if ($unnotify_list)
			{
				$notify_ids = array();
				foreach ($unnotify_list as $_unnotify)
				{
					$notify_ids[] = $_unnotify['id'];
				}
			}
		}

		if($notify_ids && is_array($notify_ids))
		{
			$ids = join(',',$notify_ids);
			$query = new IQuery("notify_registry as notify");
			$query->join   = "right join goods as goods on notify.goods_id=goods.id ";
			$query->fields = "notify.*,goods.name as goods_name,goods.store_nums";
			$query->where  = "notify.id in(".$ids.") and goods.store_nums>0 ";
			$items = $query->find();

			//库存大于0，且处于未发送邮件状态的 发送通知
			$succeed = 0;
			$failed  = 0;
			$tb_notify_registry = new IModel('notify_registry');

			foreach($items as $value)
			{
				// 十进制转换为二进制
				$notify_status = decbin($value['notify_status']);
				// 是否已发送邮件
				if (1 == ($notify_status & 1)) {
					$failed++;
					continue;
				}
				
				$siteConfig = new Config("site_config"); //dyg_jzw 20161124增加设置载入
				$body   = mailTemplate::notify(array('{goodsName}' => $value['goods_name'],'{url}' => IUrl::getHost().IUrl::creatUrl('/site/products/id/'.$value['goods_id'])));
				$status = $smtp->send($value['email'],"【{$siteConfig->name}】到货通知",$body);

				if($status)
				{
					//发送成功
					$succeed++;
					$notify_status = $notify_status | 1;
					// 二进制转换为十进制
					$notify_status = bindec($notify_status);
					$data = array('notify_time' => ITime::getDateTime(),'notify_status' => $notify_status);
					$tb_notify_registry->setData($data);
					$tb_notify_registry->update('id='.$value['id']);
				}
				else
				{
					//发送失败
					$failed++;
				}
			}
		}
		$return = array(
			'isError' => false,
			'count'   => count($items),
			'succeed' => $succeed,
			'failed'  => $failed,
		);
		echo JSON::encode($return);
	}
	/**
	 * @brief 发送到货通知短信
	 */
	function notify_sms_send()
	{
		$notify_ids = IFilter::act(IReq::get('notifyid'));

		//dyg_jzw 20161128 如果不指定 则获取未通知的情况
		if (empty($notify_ids))
		{
			$notifyObj = new IQuery("notify_registry as notify");
			$notifyObj->where = "notify_status < 2 and mobile <> ''";
			$notifyObj->fields = 'id';
			$unnotify_list = $notifyObj->find();

			if ($unnotify_list)
			{
				$notify_ids = array();
				foreach ($unnotify_list as $_unnotify)
				{
					$notify_ids[] = $_unnotify['id'];
				}
			}
		}

		if($notify_ids && is_array($notify_ids))
		{
			$ids = join(',',$notify_ids);
			$query = new IQuery("notify_registry as notify");
			$query->join   = "right join goods as goods on notify.goods_id=goods.id ";
			$query->fields = "notify.*,goods.name as goods_name,goods.store_nums";
			$query->where  = "notify.id in(".$ids.") and goods.store_nums>0 ";
			$items = $query->find();
			//库存大于0，且处于未发送短信状态的 发送通知
			$succeed = 0;
			$failed  = 0;
			$tb_notify_registry = new IModel('notify_registry');
			foreach($items as $value)
			{
				// 十进制转换为二进制
				$notify_status = decbin($value['notify_status']);
				// 是否已发送短信
				if (10 == ($notify_status & 10)) {
					$failed++;
					continue;
				}
				$content = smsTemplate::notify(array('{goodsName}' => $value['goods_name'],'{url}' => IUrl::getHost().IUrl::creatUrl('/site/products/id/'.$value['goods_id'])));
				$send_result = Hsms::send($value['mobile'], $content, 0);
				if($send_result == 'success')
				{
					//发送成功
					$succeed++;
					$notify_status = $notify_status | 10;
					// 二进制转换为十进制
					$notify_status = bindec($notify_status);
					$data = array('notify_time' => ITime::getDateTime(),'notify_status' => $notify_status);
					$tb_notify_registry->setData($data);
					$tb_notify_registry->update('id='.$value['id']);
				}
				else
				{
					//发送失败
					$failed++;
				}
			}
		}

		$return = array(
			'isError' => false,
			'count'   => count($items),
			'succeed' => $succeed,
			'failed'  => $failed,
		);
		echo JSON::encode($return);
	}

	/**
	 * @brief 发送信件
	 */
	function registry_message_send()
	{
		set_time_limit(0);
		$ids     = IFilter::act(IReq::get('ids'),'int');
		$title   = IFilter::act(IReq::get('title'));
		$content = IReq::get("content");

		$smtp  = new SendMail();
		$error = $smtp->getError();

		$list = array();
		$tb   = new IModel("email_registry");

		$ids_sql = "1";
		if($ids)
		{
			$ids_sql = "id IN ({$ids})";
		}

		$start = 0;
		$query = new IQuery("email_registry");
		$query->fields = "email";
		$query->order  = "id DESC";
		$query->where  = $ids_sql;

		do
		{
			$query->limit = "{$start},50";
			$list = $query->find();
			if(!$list)
			{
				die('没有要发送的邮箱数据');
				break;
			}
			$start += 51;

			$to = array_pop($list);
			$to = $to['email'];
			$bcc = array();
			foreach($list as $value)
			{
				$bcc[] = $value['email'];
			}
			$bcc = join(";",$bcc);
			$result = $smtp->send($to,$title,$content,$bcc);
			if(!$result)
			{
				die('发送失败');
			}
		}
		while(count($list)>=50);
		echo "success";
	}
	/**
	 * @brief 营销短信列表
	 */
	function marketing_sms_list()
	{
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$data_group = is_array($data_group) ? $data_group : array();
		$group      = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
}
		$this->data['group'] = $group;

		$this->setRenderData($this->data);
		$this->redirect('marketing_sms_list');
	}
	/**
	 * @brief 发送营销短信
	 */
	function marketing_sms_send()
	{
		$this->layout = '';
		$this->redirect('marketing_sms_send');
	}
	/**
	 * @brief 发送短信
	 */
	function start_marketing_sms()
	{
		set_time_limit(0);
		$toUser  = IFilter::act(IReq::get('toUser'));
		$content = IFilter::act(IReq::get('content'));
		if(!$content)
		{
			die('<script type="text/javascript">parent.startMarketingSmsCallback(0);</script>');
		}
		$list = array();
		$offset = 0;
		// 单次发送数量
		$length = 50;
		$succeed = 0;
		$query = new IQuery("member");
		$query->fields = "mobile";
		$query->order  = "user_id DESC";
		if(!empty($toUser))
		{
			$user_array = array();
			$user_array = explode(",", $toUser);
			$user_count = count($user_array);
			$query_num = ceil($user_count / $length);
			for ($i=0; $i<$query_num; $i++)
			{
				$user_ids = array_slice($user_array, $offset, $length);
				$user_id_string = implode(",", $user_ids);
				$offset += $length;
				$query->where  = "user_id IN ({$user_id_string}) AND `mobile` IS NOT NULL AND `mobile`!='' ";
				$list = $query->find();
				if (!empty($list))
				{
					$mobile_array = array();
					foreach ($list as $value)
					{
						if(false != IValidate::mobi($value['mobile']))
						{
							$mobile_array[] = $value['mobile'];
						}
					}
					unset($list);
					$mobile_count = count($mobile_array);
					if (0 < $mobile_count)
					{
						$mobiles = implode(",", $mobile_array);
						$send_result = Hsms::send($mobiles, $content, 0);
						if($send_result == 'success')
						{
							$succeed += $mobile_count;
						}
					}
				}
			}
		}
		else
		{
			// 默认为所有用户
			$query->where  = " `mobile` IS NOT NULL AND `mobile`!='' ";
			$list = $query->find();
			if (!empty($list))
			{
				$mobile_array = array();
				foreach ($list as $value)
				{
					if(false != IValidate::mobi($value['mobile']))
					{
						$mobile_array[] = $value['mobile'];
					}
				}
				unset($list);
				$mobile_count = count($mobile_array);
				if (0 < $mobile_count)
				{
					$send_num = ceil($mobile_count / $length);
					for ($i=0; $i<$send_num; $i++)
					{
						$mobiles = array_slice($mobile_array, $offset, $length);
						$mobile_string = implode(",", $mobiles);
						$send_result = Hsms::send($mobile_string, $content, 0);
						if($send_result == 'success')
						{
							$succeed += count($mobiles);
						}
						$offset += $length;
					}
				}
			}
		}
		//获得marketing_sms的表对象
		$tb_marketing_sms =  new IModel('marketing_sms');
		$tb_marketing_sms->setData(array(
			'content'=>$content,
			'send_nums' =>$succeed,
			'time'=> ITime::getDateTime(),
		));
		$tb_marketing_sms->add();
		die('<script type="text/javascript">parent.startMarketingSmsCallback(1);</script>');
	}
	/**
	 * @brief 删除营销短信
	 */
	function marketing_sms_del()
	{
		$refer_ids = IReq::get('check');
		$refer_ids = is_array($refer_ids) ? $refer_ids : array($refer_ids);
		if($refer_ids)
		{
			$ids = implode(',',$refer_ids);
			if($ids)
			{
				$tb_refer = new IModel('marketing_sms');
				$where = "id in (".$ids.")";
				$tb_refer->del($where);
			}
		}
		$this->marketing_sms_list();
	}
}