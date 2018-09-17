<?php
/**
 * @brief 营销模块
 * @class Market
 * @note  后台
 */
class Market extends IController implements adminAuthorization
{
	public $checkRight  = 'all';
	public $layout = 'admin';

	function init()
	{

	}

	//修改代金券状态is_close和is_send
	function ticket_status()
	{
		$status    = IFilter::act(IReq::get('status'));
		$id        = IFilter::act(IReq::get('id'),'int');
		$ticket_id = IFilter::act(IReq::get('ticket_id'));

		if(!empty($id) && $status != null && $ticket_id != null)
		{
			$ticketObj = new IModel('prop');
			if(is_array($id))
			{
				foreach($id as $val)
				{
					$where = 'id = '.$val;
					$ticketRow = $ticketObj->getObj($where,$status);
					if($ticketRow[$status]==1)
					{
						$ticketObj->setData(array($status => 0));
					}
					else
					{
						$ticketObj->setData(array($status => 1));
					}
					$ticketObj->update($where);
				}
			}
			else
			{
				$where = 'id = '.$id;
				$ticketRow = $ticketObj->getObj($where,$status);
				if($ticketRow[$status]==1)
				{
					$ticketObj->setData(array($status => 0));
				}
				else
				{
					$ticketObj->setData(array($status => 1));
				}
				$ticketObj->update($where);
			}
			$this->redirect('ticket_more_list/ticket_id/'.$ticket_id);
		}
		else
		{
			$this->ticket_id = $ticket_id;
			$this->redirect('ticket_more_list',false);
			Util::showMessage('请选择要修改的id值');
		}
	}

	//[代金券]添加,修改[单页]
	function ticket_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$ticketObj       = new IModel('ticket');
			$where           = 'id = '.$id;
			$ticketRow = $ticketObj->getObj($where);

			//dyg_jzw 20161226 是否有限制商品
			if ($ticketRow['goods_ids'])
			{
				//获取商品信息
				$goodsObj = new IModel("goods");
				$goods_list = $goodsObj->query("id in (". trim($ticketRow['goods_ids'], ",") .")", "id, name");
				$ticketRow['goods_list'] = $goods_list;
			}
			$this->ticketRow = $ticketRow;
		}
		$userGroup = new IModel('user_group');
        $all_group = $userGroup->query('1 ','group_name,id');
        $this->setRenderData(array('all_group'=>$all_group));
		$this->redirect('ticket_edit');
	}

	//[代金券]添加,修改[动作]
	function ticket_edit_act()
	{
        $is_open = IFilter::act(IReq::get('is_open', 'post'), 'int');
        $user_level_constraint = IReq::get('user_level_constraint', 'post');
        if($is_open && plugin::getItems('send_ticket_activity')['is_open'] == 0)
        {
            die('必须开启send_ticket_activity插件');
        }
		$id        = IFilter::act(IReq::get('id'),'int');
		$ticketObj = new IModel('ticket');
		$dataArray = array(
			'name'      => IFilter::act(IReq::get('name','post')),
			'value'     => IFilter::act(IReq::get('value','post')),
			'start_time'=> IFilter::act(IReq::get('start_time','post')),
			'end_time'  => IFilter::act(IReq::get('end_time','post')),
			'point'     => IFilter::act(IReq::get('point','post')),
			'goods_ids' => IFilter::act(IReq::get('goods_ids','post')), //dyg_jzw 20161226 增加代金券使用限制
			'at_least_money' => IFilter::act(IReq::get('at_least_money','post')),
			'got_limit' => IFilter::act(IReq::get('got_limit','post')),
            'is_wechat_ticket' => IFilter::act(IReq::get('is_wechat_ticket','post')),//增加添加微信优惠券的判断方式
			'readme' => IFilter::act(IReq::get('readme','post')),
            'is_open'=>$is_open,
            'user_level_constraint'=>implode(';',$user_level_constraint),
		);
		$dataArray['goods_ids'] = trim($dataArray['goods_ids'], ",");

		$ticketObj->setData($dataArray);
		if($id)
		{
			//dyg_jzw 20170111删除缓存
			$cache = new ICache('memcache');
			$cache->del("getTicketInfo_".$id);

			$where = 'id = '.$id;
			$ticketObj->update($where);
		}
		else
		{
			$ticketObj->add();
		}
		$this->redirect('ticket_list');
	}

	//[代金券]生成[动作]
	function ticket_create()
	{
		$propObj   = new IModel('prop');
		$prop_num  = intval(IReq::get('num'));
		$ticket_id = intval(IReq::get('ticket_id'));

		if($prop_num && $ticket_id)
		{
			$prop_num  = ($prop_num > 5000) ? 5000 : $prop_num;
			$ticketObj = new IModel('ticket');
			$where     = 'id = '.$ticket_id;
			$ticketRow = $ticketObj->getObj($where);

			for($item = 0; $item < intval($prop_num); $item++)
			{
				$dataArray = array(
					'condition' => $ticket_id,
					'name'      => $ticketRow['name'],
					'card_name' => IHash::random(16,'int'),
					'card_pwd'  => IHash::random(8),
					'value'     => $ticketRow['value'],
					'start_time'=> $ticketRow['start_time'],
					'end_time'  => $ticketRow['end_time'],
				);

				//判断code码唯一性
				$where = 'card_name = \''.$dataArray['card_name'].'\'';
				$isSet = $propObj->getObj($where);
				if(!empty($isSet))
				{
					$item--;
					continue;
				}
				$propObj->setData($dataArray);
				$propObj->add();
			}
			$logObj = new Log('db');
			$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"生成了代金券","面值：".$ticketRow['value']."元，数量：".$prop_num."张"));
		}
		$this->redirect('ticket_list');
	}

	//[代金券]删除
	function ticket_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if(!empty($id))
		{
			$ticketObj = new IModel('ticket');
			$propObj   = new IModel('prop');
			$propRow   = $propObj->getObj(" `type` = 0 and `condition` = {$id} and (is_close = 2 or (is_userd = 0 and is_send = 1)) ");

			if($propRow)
			{
				$this->redirect('ticket_list',false);
				Util::showMessage('无法删除代金券，其下还有正在使用的代金券');
				exit;
			}

			$where = "id = {$id} ";
			$ticketRow = $ticketObj->getObj($where);
			if($ticketObj->del($where))
			{
				$where = " `type` = 0 and `condition` = {$id} ";
				$propObj->del($where);

				$logObj = new Log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"删除了一种代金券","代金券名称：".$ticketRow['name']));
			}
			$this->redirect('ticket_list');
		}
		else
		{
			$this->redirect('ticket_list',false);
			Util::showMessage('请选择要删除的id值');
		}
	}

	//[代金券详细]删除
	function ticket_more_del()
	{
		$id        = IFilter::act(IReq::get('id'),'int');
		$ticket_id = IFilter::act(IReq::get('ticket_id'),'int');
		if($id)
		{
			$ticketObj = new IModel('ticket');
			$ticketRow = $ticketObj->getObj('id = '.$ticket_id);
			$logObj    = new Log('db');
			$propObj   = new IModel('prop');
			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"批量删除了实体代金券","代金券名称：".$ticketRow['name']."，数量：".count($id)));
			}
			else
			{
				$where = 'id = '.$id;
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"删除了1张实体代金券","代金券名称：".$ticketRow['name']));
			}
			$propObj->del($where);
			$this->redirect('ticket_more_list/ticket_id/'.$ticket_id);
		}
		else
		{
			$this->ticket_id = $ticket_id;
			$this->redirect('ticket_more_list',false);
			Util::showMessage('请选择要删除的id值');
		}
	}

	//[代金券详细] 列表
	function ticket_more_list()
	{
		$this->ticket_id = IFilter::act(IReq::get('ticket_id'),'int');
		$this->redirect('ticket_more_list');
	}

	//[代金券] 输出excel表格
	function ticket_excel()
	{
		//代金券excel表存放地址
		$ticket_id = IFilter::act(IReq::get('id'));

		if($ticket_id)
		{

			$propObj = new IModel('prop');
			$where   = 'type = 0';
			$ticket_id_array = is_array($ticket_id) ? $ticket_id : array($ticket_id);

			//当代金券数量没有时不允许备份excel
			foreach($ticket_id_array as $key => $tid)
			{
				if(statistics::getTicketCount($tid) == 0)
				{
					unset($ticket_id_array[$key]);
				}
			}

			if($ticket_id_array)
			{
				$id_num_str = join('","',$ticket_id_array);
			}
			else
			{
				$this->redirect('ticket_list',false);
				Util::showMessage('实体代金券数量为0张，无法备份');
				exit;
			}

			$where.= ' and `condition` in("'.$id_num_str.'")';

			$propList = $propObj->query($where,'*','`condition` asc',10000);
			$ticketFile = "ticket_".join("_",$ticket_id_array);
			$reportObj = new report($ticketFile);
			$reportObj->setTitle(array("名称","卡号","密码","面值","已被使用","是否关闭","是否发送","开始时间","结束时间"));
			foreach($propList as $key => $val)
			{
				$is_userd = ($val['is_userd']=='1') ? '是':'否';
				$is_close = ($val['is_close']=='1') ? '是':'否';
				$is_send  = ($val['is_send']=='1') ? '是':'否';

				$insertData = array(
					$val['name'],
					$val['card_name'],
					$val['card_pwd'],
					$val['value'].'元',
					$is_userd,
					$is_close,
					$is_send,
					$val['start_time'],
					$val['end_time'],
				);
				$reportObj->setData($insertData);
			}

			$reportObj->toDownload();
		}
		else
		{
			$this->redirect('ticket_list',false);
			Util::showMessage('请选择要操作的文件');
		}
	}

	//[代金券]获取代金券数据
	function getTicketList()
	{
		$ticketObj  = new IModel('ticket');
		$ticketList = $ticketObj->query();
		echo JSON::encode($ticketList);
	}

	//[促销活动] 添加修改 [单页]
	function pro_rule_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$promotionObj = new IModel('promotion');
			$where = 'id = '.$id;
			$this->promotionRow = $promotionObj->getObj($where);
		}
		$this->redirect('pro_rule_edit');
	}

	//[促销活动] 添加修改 [动作]
	function pro_rule_edit_act()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$user_group   = IFilter::act(IReq::get('user_group','post'));
		$promotionObj = new IModel('promotion');
		if(is_string($user_group))
		{
			$user_group_str = $user_group;
		}
		else
		{
			$user_group_str = ",".join(',',$user_group).",";
		}

		$dataArray = array(
			'name'       => IFilter::act(IReq::get('name','post')),
			'condition'  => IFilter::act(IReq::get('condition','post')),
			'is_close'   => IFilter::act(IReq::get('is_close','post')),
			'start_time' => IFilter::act(IReq::get('start_time','post')),
			'end_time'   => IFilter::act(IReq::get('end_time','post')),
			'intro'      => IFilter::act(IReq::get('intro','post')),
			'award_type' => IFilter::act(IReq::get('award_type','post')),
			'type'       => 0,
			'user_group' => $user_group_str,
			'award_value'=> IFilter::act(IReq::get('award_value','post')),
		);

		$promotionObj->setData($dataArray);

		if($id)
		{
			$where = 'id = '.$id;
			$promotionObj->update($where);
		}
		else
		{
			$promotionObj->add();
		}
		$this->redirect('pro_rule_list');
	}

	//[促销活动] 删除
	function pro_rule_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if(!empty($id))
		{
			$promotionObj = new IModel('promotion');
			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$promotionObj->del($where);
			$this->redirect('pro_rule_list');
		}
		else
		{
			$this->redirect('pro_rule_list',false);
			Util::showMessage('请选择要删除的促销活动');
		}
	}

	//[限时抢购]添加,修改[单页]
	function pro_speed_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$promotionObj = new IModel('promotion');
			$where = 'id = '.$id;
			$promotionRow = $promotionObj->getObj($where);
			if(empty($promotionRow))
			{
				$this->redirect('pro_speed_list');
			}

			//促销商品
			$goodsObj = new IModel('goods');
			$goodsRow = $goodsObj->getObj('id = '.$promotionRow['condition'],'id,name,sell_price,img');
			if($goodsRow)
			{
				$result = array(
					'isError' => false,
					'data'    => $goodsRow,
				);
			}
			else
			{
				$result = array(
					'isError' => true,
					'message' => '关联商品被删除，请重新选择要抢购的商品',
				);
			}

			$promotionRow['goodsRow'] = JSON::encode($result);
			$this->promotionRow = $promotionRow;
		}
		$this->redirect('pro_speed_edit');
	}

	//[限时抢购]添加,修改[动作]
	function pro_speed_edit_act()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		$condition   = IFilter::act(IReq::get('condition','post'));
		$award_value = IFilter::act(IReq::get('award_value','post'));
		$user_group  = IFilter::act(IReq::get('user_group','post'));

		if(is_string($user_group))
		{
			$user_group_str = $user_group;
		}
		else
		{
			$user_group_str = ",".join(',',$user_group).",";
		}

		//dyg_jzw 20161215 删除缓存
		$cache = new ICache('memcache');
		$cache->del('allTimeBuyList_0');

		$dataArray = array(
			'id'         => $id,
			'name'       => IFilter::act(IReq::get('name','post')),
			'condition'  => $condition,
			'award_value'=> $award_value,
			'is_close'   => IFilter::act(IReq::get('is_close','post')),
			'is_point'   => IFilter::act(IReq::get('is_point','post')), //dyg_jzw 20150812 是否赠送积分
			'is_exp'   => IFilter::act(IReq::get('is_exp','post')), //dyg_jzw 20150812 是否赠送经验值
			'is_commission'   => IFilter::act(IReq::get('is_commission','post')), //dyg_jzw 20161215 是否赠送佣金
			'start_time' => IFilter::act(IReq::get('start_time','post')),
			'end_time'   => IFilter::act(IReq::get('end_time','post')),
			'intro'      => IFilter::act(IReq::get('intro','post')),
			'type'       => 1,
			'award_type' => 0,
			'user_group' => $user_group_str,
		);

		if(!$condition || !$award_value)
		{
			$this->promotionRow = $dataArray;
			$this->redirect('pro_speed_edit',false);
			Util::showMessage('请添加促销的商品，并为商品填写价格');
		}

		$proObj = new IModel('promotion');
		$proObj->setData($dataArray);
		if($id)
		{
			$where = 'id = '.$id;
			$proObj->update($where);
		}
		else
		{
			$proObj->add();
		}
		$this->redirect('pro_speed_list');
	}

	//[限时抢购]删除
	function pro_speed_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if(!empty($id))
		{
			$propObj = new IModel('promotion');
			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$where .= ' and type = 1';
			$propObj->del($where);
			$this->redirect('pro_speed_list');
		}
		else
		{
			$this->redirect('pro_speed_list',false);
			Util::showMessage('请选择要删除的id值');
		}
	}

	//[团购]添加修改[单页]
	function regiment_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$regimentObj = new IModel('regiment');
			$where       = 'id = '.$id;
			$regimentRow = $regimentObj->getObj($where);
			if(!$regimentRow)
			{
				$this->redirect('regiment_list');
			}

			//促销商品
			$goodsObj = new IModel('goods');
			$goodsRow = $goodsObj->getObj('id = '.$regimentRow['goods_id']);

			$result = array(
				'isError' => false,
				'data'    => $goodsRow,
			);
			$regimentRow['goodsRow'] = JSON::encode($result);
			$this->regimentRow = $regimentRow;
		}
		$this->redirect('regiment_edit');
	}

	//[团购]添加修改[动作]
	function regiment_edit_act()
	{
		$id      = IFilter::act(IReq::get('id'),'int');
		$goodsId = IFilter::act(IReq::get('goods_id'),'int');

		$dataArray = array(
			'id'        	=> $id,
			'title'     	=> IFilter::act(IReq::get('title','post')),
			'start_time'	=> IFilter::act(IReq::get('start_time','post')),
			'end_time'  	=> IFilter::act(IReq::get('end_time','post')),
			'is_close'      => IFilter::act(IReq::get('is_close','post')),
			'is_point'   => IFilter::act(IReq::get('is_point','post')), //dyg_jzw 20150812 是否赠送积分
			'is_exp'   => IFilter::act(IReq::get('is_exp','post')), //dyg_jzw 20150812 是否赠送经验值
			'is_commission'   => IFilter::act(IReq::get('is_commission','post')), //dyg_jzw 20161215 是否赠送佣金
			'intro'     	=> IFilter::act(IReq::get('intro','post')),
			'goods_id'      => $goodsId,
			'store_nums'    => IFilter::act(IReq::get('store_nums','post')),
			'limit_min_count' => IFilter::act(IReq::get('limit_min_count','post'),'int'),
			'limit_max_count' => IFilter::act(IReq::get('limit_max_count','post'),'int'),
			'regiment_price'=> IFilter::act(IReq::get('regiment_price','post')),
			'sort'          => IFilter::act(IReq::get('sort','post')),
		);
		$dataArray['limit_min_count'] = $dataArray['limit_min_count'] <= 0 ? 1 : $dataArray['limit_min_count'];
		$dataArray['limit_max_count'] = $dataArray['limit_max_count'] <= 0 ? $dataArray['store_nums'] : $dataArray['limit_max_count'];

		//dyg_jzw 20161215 删除缓存
		$cache = new ICache('memcache');
		$cache->del('allRegimentList_0');

		if($goodsId)
		{
			$goodsObj = new IModel('goods');
			$where    = 'id = '.$goodsId;
			$goodsRow = $goodsObj->getObj($where);

			//处理上传图片
			if(isset($_FILES['img']['name']) && $_FILES['img']['name'] != '')
			{
				$uploadObj = new PhotoUpload();
				$photoInfo = $uploadObj->run();
				$dataArray['img'] = $photoInfo['img']['img'];
			}
			else
			{
				$dataArray['img'] = $goodsRow['img'];
			}

			$dataArray['sell_price'] = $goodsRow['sell_price'];
		}
		else
		{
			$this->regimentRow = $dataArray;
			$this->redirect('regiment_edit',false);
			Util::showMessage('请选择要关联的商品');
		}

		$regimentObj = new IModel('regiment');
		$regimentObj->setData($dataArray);

		if($id)
		{
			$where = 'id = '.$id;
			$regimentObj->update($where);
		}
		else
		{
			$regimentObj->add();
		}
		$this->redirect('regiment_list');
	}

	//[团购]删除
	function regiment_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$regObj = new IModel('regiment');
			if(is_array($id))
			{
				$id    = join(',',$id);
			}
			$where = ' id in ('.$id.')';
			$regObj->del($where);
			$this->redirect('regiment_list');
		}
		else
		{
			$this->redirect('regiment_list',false);
			Util::showMessage('请选择要删除的id值');
		}
	}

	//账户余额记录
	function account_list()
	{
		$page       = intval(IReq::get('page')) ? IReq::get('page') : 1;
		$username	= IReq::get('username'); //dyg_jzw 20160330 增加单一用户查询
		$is_out       = intval(IReq::get('is_out')) ? IReq::get('is_out') : 0; //dyg_jzw 20160603 是否导出 

		$event      = intval(IReq::get('event'));
		$startDate  = IFilter::act(IReq::get('startDate'));
		$endDate    = IFilter::act(IReq::get('endDate'));

		$where = "1";
		if (isset($username) && $username) 
		{
			//获取用户id
			$userObj = new IModel("user");
			$userRow = $userObj->getObj("username='{$username}'");
			
			if($userRow)
			{
				$where      .= " and user_id = ".$userRow['id'];
				$this->username	= $userRow['username'];	
			}
		}
		
		if($startDate)
		{
			$where .= " and time >= '{$startDate}' ";
		}

		if($endDate)
		{
			$temp   = $endDate.' 23:59:59';
			$where .= " and time <= '{$temp}' ";
		}

		if($event)
		{
			$where .= " and event = $event ";
		}

		$accountObj = new IQuery('account_log');
		$accountObj->where = $where;
		$accountObj->order = 'id desc';

		//dyg_jzw 20160603 是否导出
		if(! $is_out)
		{
			$accountObj->page  = $page;
		}

		$this->accountObj  = $accountObj;
		$this->event       = $event;
		$this->startDate   = $startDate;
		$this->endDate     = $endDate;
		$this->accountList = $accountObj->find();

		if (! $is_out)
		{
			$this->redirect('account_list');
		}
		else
		{
			$strTable ='<table width="500" border="1">';
			$strTable .= '<tr>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="150px">操作管理员ID</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">目标用户ID</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">内容</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">类型</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">金额</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">时间</td>';
			$strTable .= '</tr>';

			foreach ($this->accountList as $_item) 
			{
				$strTable .= '<tr>';
				$strTable .= '<td>'.$_item['admin_id'].'</td>';
				$strTable .= '<td>'.$_item['user_id'].'</td>';
				$strTable .= '<td>'.$_item['note'].'</td>';

				if($_item['event'] == 1)
				{
					$event_name = '充值';
				}
				elseif($_item['event'] == 2)
				{
					$event_name = '提现';
				}
				elseif($_item['event'] == 3)
				{
					$event_name = '消费';
				}
				elseif($_item['event'] == 4)
				{
					$event_name = '退款';
				}
				elseif($_item['event'] == 8)
				{
					$event_name = '佣金';
				}
				$strTable .= '<td>'.$event_name.'</td>';

				$strTable .= '<td>'.$_item['amount'].'</td>';
				$strTable .= '<td>'.$_item['time'].'</td>';
				$strTable .= '</tr>';
			}
			$strTable .= '</table>';
			$reportObj = new report();
			$reportObj->setFileName('account_log_list_'.date('Ymd-Hi'));
			$reportObj->toDownload($strTable);
		}

		
	}

	//dyg_jzw 20160330 增加用户余额列表
	function account_log_list()
	{
		$end_time       = IReq::get('end_time') ? IReq::get('end_time') : 0;
		$page       = intval(IReq::get('page')) ? IReq::get('page') : 1;
		$is_export 	= intval(IReq::get('export')) ? IReq::get('export') : 0;

		//是否有截止日期
		if ($end_time)
		{
			$end_time = date('Y-m-d H:i:s', strtotime($end_time) + 3600*24);
		}
		else
		{
			$end_time = ITime::getDateTime();
		}

		//获取全部用户的余额日志
		$memberObj = new IQuery('user as u, account_log as a');
		$memberObj->fields = 'u.username , a.*';
		$memberObj->order = 'a.user_id asc';
		$memberObj->where = "a.user_id = u.id and a.id in (select max(id) from account_log where time <= '{$end_time}' group by user_id)";
		
		if (! $is_export) 
		{
			$memberObj->page  = $page;
		}

		$this->memberObj  = $memberObj;
		$this->memberList = $memberObj->find();

		if ($is_export) 
		{
			$strTable ='<table width="500" border="1">';
			$strTable .= '<tr>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="150px">用户ID</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户账号</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
			$strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后更新日期</td>';
			$strTable .= '</tr>';

			foreach ($this->memberList as $_member) 
			{
				$strTable .= '<tr>';
				$strTable .= '<td>'.$_member['user_id'].'</td>';
				$strTable .= '<td>'.$_member['username'].'</td>';
				$strTable .= '<td>'.$_member['amount_log'].'</td>';
				$strTable .= '<td>'.$_member['time'].'</td>';
				$strTable .= '</tr>';
			}
			$strTable .= '</table>';
			$reportObj = new report();
			$reportObj->setFileName('account_log_list_'.date('Ymd-Hi'));
			$reportObj->toDownload($strTable);
		}
		else
		{
			$this->redirect('account_log_list');
		}

		
	}

	//后台操作记录
	function operation_list()
	{
		$page       = intval(IReq::get('page')) ? IReq::get('page') : 1;
		$startDate  = IFilter::act(IReq::get('startDate'));
		$endDate    = IFilter::act(IReq::get('endDate'));

		$where      = "1";
		if($startDate)
		{
			$where .= " and datetime >= '{$startDate}' ";
		}

		if($endDate)
		{
			$temp   = $endDate.' 23:59:59';
			$where .= " and datetime <= '{$temp}' ";
		}

		$operationObj = new IQuery('log_operation');
		$operationObj->where = $where;
		$operationObj->order = 'id desc';
		$operationObj->page  = $page;

		$this->operationObj  = $operationObj;
		$this->startDate     = $startDate;
		$this->endDate       = $endDate;
		$this->operationList = $operationObj->find();

		$this->redirect('operation_list');
	}

	//清理后台管理员操作日志
	function clear_log()
	{
		$type  = IReq::get('type');
		$month = intval(IReq::get('month'));
		if(!$month)
		{
			die('请填写要清理日志的月份');
		}

		$diffSec = 3600*24*30*$month;
		$lastTime= strtotime(date('Y-m')) - $diffSec;
		$dateStr = date('Y-m',$lastTime);

		switch($type)
		{
			case "account":
			{
				$logObj = new IModel('account_log');
				$logObj->del("time <= '{$dateStr}'");
				$this->redirect('account_list');
				break;
			}
			case "operation":
			{
				$logObj = new IModel('log_operation');
				$logObj->del("datetime <= '{$dateStr}'");
				$this->redirect('operation_list');
				break;
			}
			default:
				die('缺少类别参数');
		}
	}

	//修改结算账单
	public function bill_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$billDB = new IModel('bill');
		$this->billRow = $billDB->getObj('id = '.$id);
		$this->redirect('bill_edit');
	}

	//结算单修改
	public function bill_update()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$pay_content = IFilter::act(IReq::get('pay_content'));
		$is_pay = IFilter::act(IReq::get('is_pay'),'int');

		if($id)
		{
			$data = array(
				'admin_id' => $this->admin['admin_id'],
				'pay_content' => $pay_content,
				'is_pay' => $is_pay,
			);

			$billDB = new IModel('bill');

			$data['pay_time'] = ($is_pay == 1) ? ITime::getDateTime() : "";

			$billRow= $billDB->getObj('id = '.$id);
			if(isset($billRow['order_ids']) && $billRow['order_ids'])
			{
				//更新订单商品关系表中的结算字段
				$orderDB = new IModel('order');
				$orderIdArray = explode(',',$billRow['order_ids']);
				foreach($orderIdArray as $key => $val)
				{
					$orderDB->setData(array('is_checkout' => $is_pay));
					$orderDB->update('id = '.$val);
				}
			}

			$billDB->setData($data);
			$billDB->update('id = '.$id);
		}
		$this->redirect('bill_list');
	}

	//结算单删除
	public function bill_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$billDB = new IModel('bill');
			$billDB->del('id = '.$id.' and is_pay = 0');
		}

		$this->redirect('bill_list');
	}

	//导出用户统计数据
	public function user_report(){
		$start = IFilter::act(IReq::get('start'));
		$end   = IFilter::act(IReq::get('end'));






		$memberQuery= new IQuery('member as m');
		$memberQuery->join   = "left join user as u on m.user_id=u.id";
		$memberQuery->fields = "u.username,m.time,m.email,m.mobile";

		$memberQuery->where  = "m.time between '".$start."' and '".$end." 23:59:59'";
			$memberList = $memberQuery->find();

		$reportObj = new report('user');
		$reportObj->setTitle(array("日期","用户名","邮箱","手机号"));
		foreach($memberList as $k => $val)
		{
			$insertData = array($val['time'],$val['username'],$val['email'],$val['mobile']);
			$reportObj->setData($insertData);
		}

		$reportObj->toDownload();
	}

	//导出人均消费数据
	public function spanding_report()
	{
		$start = IFilter::act(IReq::get('start'));
		$end   = IFilter::act(IReq::get('end'));

		$reportObj = new report('spanding');
		$reportObj->setTitle(array("日期","人均消费金额"));

		$db = new IQuery('collection_doc');
		$db->fields   = "sum(amount)/count(*) as count,`time`,DATE_FORMAT(`time`,'%Y-%m-%d') as `timeData`";
		$db->where    = "pay_status = 1";
		$db->group    = "DATE_FORMAT(`time`,'%Y-%m-%d') having `time` >= '{$start}' and `time` < '{$end} 23:59:59'";
		$spandingList = $db->find();
		foreach($spandingList as $k => $val)
		{
			$insertData = array($val['timeData'],$val['count']);
			$reportObj->setData($insertData);
		}

		$reportObj->toDownload();
	}

	//导出销售数据
	public function amount_report()
	{
		$start = IFilter::act(IReq::get('start'));
		$end   = IFilter::act(IReq::get('end'));

		$reportObj = new report('amount');
		$reportObj->setTitle(array("完成订单日期","订单量","商品销售额","商品销售成本","商品销售毛利"));

		$orderDB   = new IModel('order');
		$orderList = $orderDB->query(" `completion_time` between '{$start}' and '{$end} 23:59:59' "," DATE_FORMAT(`completion_time`,'%Y-%m-%d') as ctime,id ","id asc");
		if($orderList)
		{
			//按照订单时间组合订单ID
			$ids = array();
			foreach($orderList as $key => $val)
			{
				if(!isset($ids[$val['ctime']]))
				{
					$ids[$val['ctime']] = array();
				}
				$ids[$val['ctime']][] = $val['id'];
			}
			//获取订单数据
			$db        = new IQuery('order_goods as og');
			$db->join  = "left join goods as go on go.id = og.goods_id left join products as p on p.id = og.product_id ";
			$db->fields= "og.*,go.cost_price as go_cost,p.cost_price as p_cost";
			$db->order = "og.order_id asc";
			$result    = array();
			foreach($ids as $ctime => $idArray)
			{
				$db->where = "og.order_id in (".join(',',$idArray).") and og.is_send = 1";
				$orderList = $db->find();
				$result[$ctime] = array("orderNum" => count($idArray),"goods_sum" => 0,"goods_cost" => 0,"goods_diff" => 0);
				foreach($orderList as $key => $val)
				{
					$result[$ctime]['goods_sum']  += $val['real_price'] * $val['goods_nums'];
					$cost = $val['p_cost'] ? $val['p_cost'] : $val['go_cost'];
					$result[$ctime]['goods_cost'] += $cost * $val['goods_nums'];
				}
				$result[$ctime]['goods_diff'] += $result[$ctime]['goods_sum'] - $result[$ctime]['goods_cost'];
			}

		foreach($result as $ctime => $val)
		{
				$insertData = array(
					$ctime,
					$val['orderNum'],
					$val['goods_sum'],
					$val['goods_cost'],
					$val['goods_diff'],
				);
				$reportObj->setData($insertData);
		}
		}
		$reportObj->toDownload();
	}
	
	//[特价商品]添加,修改[单页]
	function sale_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$promotionObj = new IModel('promotion');
			$where = 'id = '.$id.' and award_type = 7';
			$this->promotionRow = $promotionObj->getObj($where);
			if(!$this->promotionRow)
			{
				IError::show("信息不存在");
			}
		}
		$this->redirect('sale_edit');
	}

	//[特价商品]添加,修改[动作]
	function sale_edit_act()
	{
		$id           = IFilter::act(IReq::get('id'),'int');
		$award_value  = IFilter::act(IReq::get('award_value'),'int');
		$type         = IFilter::act(IReq::get('type'));
		$is_close     = IFilter::act(IReq::get('is_close','post'));
		$intro        = array();//商品ID => 促销金额(或者折扣率)

		$proObj = new IModel('promotion');
		if($id)
		{
			//获取旧数据和原始价格
			$proRow = $proObj->getObj("id = ".$id);
			if(!$proRow)
			{
				IError::show('特价活动不存在');
			}

			if($proRow['is_close'] == 0)
			{
				$tempUpdate = JSON::decode($proRow['intro']);
				if($tempUpdate)
				{
					foreach($tempUpdate as $gid => $g_discount)
					{
						goods_class::goodsDiscount($gid,$g_discount,"constant","add");
					}
				}
			}
		}

		switch($type)
		{
			case 2:
			{
				$category = IFilter::act(IReq::get('category'),'int');
				if(!$category)
				{
					IError::show(403,'商品分类信息没有设置');
				}
				$condition = join(",",$category);
				$goodsData = Api::run("getCategoryExtendList",array("#categroy_id#",$condition),500);
				foreach($goodsData as $key => $val)
				{
					$intro[$val['id']] = $val['sell_price'] - $val['sell_price']*$award_value/100;
				}
			}
			break;

			case 3:
			{
				$gid = IFilter::act(IReq::get('goods_id'),'int');
				if(!$gid)
				{
					IError::show(403,'商品信息没有设置');
				}
				$condition   = join(",",$gid);
				$goodsDB     = new IModel('goods');
				$goodsData   = $goodsDB->query('id in ('.$condition.')');
				$goods_price = IFilter::act(IReq::get('goods_price'),'float');

				foreach($goodsData as $key => $val)
				{
					if(isset( $goods_price[$val['id']] ))
					{
						$intro[$val['id']] = $val['sell_price'] - $goods_price[$val['id']];
					}
				}
			}
			break;

			case 4:
			{
				$condition = IFilter::act(IReq::get('brand_id'),'int');
				if(!$condition)
				{
					IError::show(403,'品牌信息没有设置');
				}
				$goodsDB   = new IModel('goods');
				$goodsData = $goodsDB->query("brand_id = ".$condition,"*","sort asc",500);
				foreach($goodsData as $key => $val)
				{
					$intro[$val['id']] = $val['sell_price'] - $val['sell_price']*$award_value/100;
				}
			}
			break;
		}

		if(!$intro)
		{
			IError::show(403,'商品信息不存在，请确定你选择的条件有商品');
		}

		//去掉重复促销的商品
		$proData = $proObj->query("award_type = 7 and id != ".$id);
		foreach($proData as $key => $val)
		{
			$temp  = JSON::decode($val['intro']);
			$intro = array_diff_key($intro,$temp);
		}

		if(!$intro)
		{
			IError::show(403,'商品不能重复设置特价');
		}

		$dataArray = array(
			'name'       => IFilter::act(IReq::get('name','post')),
			'condition'  => $condition,
			'award_value'=> $award_value,
			'is_close'   => $is_close,
			'start_time' => ITime::getDateTime(),
			'intro'      => JSON::encode($intro),
			'type'       => $type,
			'award_type' => 7,
			'sort'       => IFilter::act(IReq::get('sort'),'int'),
		);

		$proObj->setData($dataArray);
		if($id)
		{
			$where = 'id = '.$id;
			$proObj->update($where);
		}
		else
		{
			$proObj->add();
		}

		//开启
		if($is_close == 0)
		{
			$tempUpdate = $intro;
			if($tempUpdate)
			{
				foreach($tempUpdate as $gid => $g_discount)
				{
					goods_class::goodsDiscount($gid,$g_discount,"constant","reduce");
				}
			}
		}
		$this->redirect('sale_list');
	}

	//[特价商品]删除
	function sale_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$proObj = new IModel('promotion');
			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = ' id = '.$id;
			}
			$where .= ' and award_type = 7 ';

			//恢复特价商品价格
			$proList = $proObj->query($where);
			foreach($proList as $key => $val)
			{
				if($val['is_close'] == 0)
				{
					$tempUpdate = JSON::decode($val['intro']);
					if($tempUpdate)
					{
						foreach($tempUpdate as $gid => $g_discount)
						{
							goods_class::goodsDiscount($gid,$g_discount,"constant","add");
						}
					}
				}
			}
			$proObj->del($where);
			$this->redirect('sale_list');
		}
		else
		{
			$this->redirect('sale_list',false);
			Util::showMessage('请选择要删除的id值');
		}
	}
	
	//商家销售报表明细导出
	function sellerReport()
	{
		$where  = util::search(IReq::get('search'));
		$billDB = new IQuery('bill as b');
		$billDB->join   = "left join seller as s on s.id = b.seller_id";
		$billDB->where  = $where;
		$billDB->fields = "b.*,s.email,s.true_name";
		$billDB->group  = "b.seller_id";
		$billData       = $billDB->find();

		$reportObj = new report('seller_bill');
		$reportObj->setTitle(array("收款人email","收款人姓名","付款金额（元）","付款理由"));
		if($billData)
		{
			foreach($billData as $key => $val)
			{
				$insertData = array(
					$val['email'],
					$val['true_name'],
					$val['amount'],
					$val['start_time']."至".$val['end_time']."货款",
				);
				$reportObj->setData($insertData);
			}
		}
		$reportObj->toDownload();
	}

	//用户充值记录
	public function account_recharge()
	{
		$page       = intval(IReq::get('page')) ? IReq::get('page') : 1;

		$search = IFilter::act(IReq::get('search'),'strict');
		$keywords = IFilter::act(IReq::get('keywords'),'strict');

		$startDate  = IFilter::act(IReq::get('startDate'));
		$endDate    = IFilter::act(IReq::get('endDate'));

		$where      = "1";
		if($startDate)
		{
			$where .= " and time >= '{$startDate}' ";
		}

		if($endDate)
		{
			$temp   = $endDate.' 23:59:59';
			$where .= " and time <= '{$temp}' ";
		}

		if($keywords)
		{
			switch ($search) 
			{
				case 'username':
					$where .= " and u.username = '".$keywords."'";
					break;
				case 'recharge_no':
					$where .= " and o.recharge_no = '".$keywords."'";
					break;
			}
		}

		$onlineRechargeObj = new IQuery('online_recharge AS o');
		$onlineRechargeObj->fields = 'u.username, o.*';
		$onlineRechargeObj->join = 'left join user AS u ON u.id = o.user_id';
		$onlineRechargeObj->where = $where;
		$onlineRechargeObj->order = 'o.id desc';
		$onlineRechargeObj->page  = $page;

		$this->onlineRechargeObj  = $onlineRechargeObj;
		$this->search       = $search;
		$this->keywords       = $keywords;
		$this->startDate   = $startDate;
		$this->endDate     = $endDate;
		$this->onlineRechargeList = $onlineRechargeObj->find();

		$this->redirect('account_recharge');		
	}

	//dyg_jzw 20150919 销售报表统计
	public function total_list()
	{
		$start = IFilter::act(IReq::get('startDate'));
		$end   = IFilter::act(IReq::get('endDate'));
		$export   = IFilter::act(IReq::get('export'), 'int');

		//付款状态
		$pay_status   = IFilter::act(IReq::get('pay_status'), 'int');

		//付款方式
		$pay_type = IFilter::act(IReq::get('pay_type'), 'int');

		//时间类型
		$time_type = IFilter::act(IReq::get('time_type'), 'int');
		if ($time_type == 1) 
		{
			$time_type = 'pay_time';
		}
		else
		{
			$time_type = 'send_time';
		}


		$pay_type_sql = '';
		if ($pay_type > 0)
		{
			$pay_type_sql = ' and o.pay_type = ' . intval($pay_type);
		}

		//配送状态
		$distribution_status   = IFilter::act(IReq::get('distribution_status'), 'int');
		
		if ($distribution_status == 0)
		{
			$distribution_status = '0,1,2';
		}
		if ($distribution_status == 3) //未发货状态为3
		{
			$distribution_status = 0;
		}

		//用户id
		$user_id_sql = '';
		$_user_id = IFilter::act(IReq::get('user_id'), 'int');
		if ($_user_id)
		{
			$user_id_sql = 'o.user_id = ' . $_user_id . ' AND ';
		}

		//获取时间段
		$_date       = statistics::dateParse($start,$end);

		$startArray = explode('-',$_date[0]);
		$endArray   = explode('-',$_date[1]);
		$startCondition = $startArray[0].'-'.$startArray[1].'-'.$startArray[2];
		$endCondition   = $endArray[0].'-'.$endArray[1].'-'.$endArray[2];

		$this->startDate = $startCondition;
		$this->endDate = $endArray[0].'-'.$endArray[1].'-'.$endArray[2];

		//查询数据
		$start = date("Y-m-d H:i:s", strtotime($startCondition));
		$end = date("Y-m-d H:i:s", strtotime("+1 day", strtotime($endCondition)));

		$seller_id = 0;

		$orderObj = new IQuery('order as o');
		$orderObj->fields = 'o.order_no, u.username, o.trade_no, o.create_time, o.send_time, o.pay_time, pay.name as pay_name, o.order_amount, o.distribution_status, o.accept_name, o.note, og.goods_array, og.is_send, og.goods_nums, og.real_price, o.commission, p.name as prop_name, pm.name as promotion_name, o.discount, o.real_amount, o.real_freight, o.insured, o.taxes';
		$orderObj->join   = 'left join order_goods as og on og.order_id=o.id 
						left join user as u on u.id=o.user_id
						left join prop as p on p.id=o.prop
						left join promotion as pm on pm.id=o.active_id
						left join payment as pay on pay.id=o.pay_type';
		$orderObj->where  = "{$user_id_sql}o.status in (2,5,6,7) and o.if_del = 0 and o.seller_id = {$seller_id} and o.pay_status = {$pay_status}{$pay_type_sql} and o.{$time_type}>='{$start}' and o.{$time_type}<'{$end}' and o.distribution_status in ({$distribution_status})";
		$orderObj->order = 'o.send_time DESC, o.id DESC';

		$result = $orderObj->find();

		//商品汇总
		$result_arr = array();

		if ($result) 
		{
			foreach ($result as $_key => $_res) 
			{
				$_goods_arr = json_decode($_res['goods_array'], true);

				//创建key
				$result_arr[$_goods_arr['goodsno']]['goodsno'] = $_goods_arr['goodsno']; //商品编码
				$result_arr[$_goods_arr['goodsno']]['name'] = $_goods_arr['name'].' '.$_goods_arr['value']; //商品名称
				
				//总数量汇总 初始化数量
				if (!isset($result_arr[$_goods_arr['goodsno']]['goods_nums'])) 
				{
					$result_arr[$_goods_arr['goodsno']]['goods_nums'] = 0;
				}

				//运费增加保价的税金
				$result[$_key]['real_freight'] = $_res['real_freight'] += $_res['insured'] + $_res['taxes'];

				if ($_res['is_send'] == 2) //状态2为退货, 修改商品数量和订单金额
				{
					$result[$_key]['goods_nums'] = $_res['goods_nums'] = 0;
					$result[$_key]['real_freight'] = $_res['real_freight'] = 0;
				}


				$result_arr[$_goods_arr['goodsno']]['goods_nums'] += $_res['goods_nums']; //商品数量
				$result_arr[$_goods_arr['goodsno']]['pay_type'][md5($_res['pay_name'])]['name'] = $_res['pay_name']; //支付方式
				$goods_amount = $_res['real_price']*$_res['goods_nums'] + (($_res['real_price']*$_res['goods_nums']/$_res['real_amount'])*$_res['discount']); //商品总价合计
				
				//支付方式 商品数量汇总初始化
				if (!isset($result_arr[$_goods_arr['goodsno']]['pay_type'][md5($_res['pay_name'])]['total_num']))  
				{
					$result_arr[$_goods_arr['goodsno']]['pay_type'][md5($_res['pay_name'])]['total_num'] = 0;
				}
				$result_arr[$_goods_arr['goodsno']]['pay_type'][md5($_res['pay_name'])]['total_num'] += $_res['goods_nums']; //支付方式数量合计
				if (!isset($result_arr[$_goods_arr['goodsno']]['total'])) 
				{
					$result_arr[$_goods_arr['goodsno']]['total'] = 0;
				}
				$result_arr[$_goods_arr['goodsno']]['total'] += $goods_amount; //订单合计
			}
		}

		//是否导出
		if ($export) 
		{
			//加载导出类
			$classFile = IWeb::$app->getBasePath().'plugins/PHPExcel/my_export.php';
			require($classFile);

			$exportObj = new my_export();
			$exportObj->export($result, $start, $end);
		}
		else
		{
			$this->result = $result_arr;
		}

		$this->redirect('total_list');

	}

	/**
	 * 2016-4-18
	 * by dyg_cjs
	 * 消费排名
	 */
	public function spending_rank()
	{
		$page       = intval(IReq::get('page')) ? IReq::get('page') : 1;
		$startDate = IFilter::act(IReq::get('start'));
		$endDate   = IFilter::act(IReq::get('end'));

		//获取时间段
		$_date       = statistics::dateParse($startDate,$endDate);
		$startCondition = $_date[0];
		$endCondition   = $_date[1];
		$result         = array();

		$where = "pay_status = 1";
		if ($startDate) 
		{
			$where .= " and c.time >= '{$startCondition}'";
		}
		if ($endDate) 
		{
			$where .= " and c.time < '{$endCondition}'";
		}
		
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$group      = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
		}
		$this->group = $group;
		
		$db = new IQuery('collection_doc as c');
		$db->fields = "u.username as username, u.id as user_id, m.group_id as group_id, m.true_name, sum(amount) as count";
		$db->where  = $where;
		$db->join = 'inner join user as u on c.user_id=u.id inner join member as m on c.user_id=m.user_id';
		$db->group   = "c.user_id";
		$db->order	= 'count desc';
		$db->page  = $page;
		
		$this->spendingList = $db->find();
		$this->startDate    = $startDate;
		$this->endDate      = $endDate;	
		$this->db			= $db;
		
		$this->redirect('spending_rank');
	}

	public function team_count()
	{
		$startDate = IFilter::act(IReq::get('start'));
		$endDate   = IFilter::act(IReq::get('end'));
		$cats = IFilter::act(IReq::get('cats'));

		if ($startDate && $endDate)
		{
			//获取时间段
			$_date       = statistics::dateParse($startDate,$endDate);
			$this->startDate = $startCondition = $_date[0];
			$this->endDate = $endCondition   = $_date[1];

			//加载导出类
			$classFile = IWeb::$app->getBasePath().'plugins/PHPExcel/team_export.php';
			require($classFile);

			$exportObj = new team_export();
			$exportObj->export($cats, $startCondition, $endCondition);
		}
		else
		{
			//获取分类
			$catDB = new IModel('category');
			$this->cat_arr = $catDB->query('seller_id = 0', '*', 'parent_id ASC');

			//默认统计的分类
			$this->normal_cats = array(1,55,100,44);

			$this->redirect('team_count');
		}
		
	}

}
