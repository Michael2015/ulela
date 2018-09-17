<?php
/**
 * @brief 活动api
 * author dyg_jzw
 * create at 2016-12-21 09:48:00
 */
class Activity_api extends IController
{
	public $today_start = null; //今天开始时间 格式 YYYY-mm-dd HH:ii:ss
	public $today_end = null; //今天结束时间 格式 YYYY-mm-dd HH:ii:ss

	//擂台游戏 - 获取擂主
	public function ring_get_top_user()
	{
		$this->today_start = date("Y-m-d 07:00:00");
		$this->today_end = date("Y-m-d 23:59:59");

		//获取今天所有带50%虫草复方的order_goods
		$orderGoodsDB = new IQuery('order as o');
		$orderGoodsDB->join = 'left join order_goods as og on og.order_id = o.id';
		$orderGoodsDB->where = "o.pay_time >= '{$this->today_start}' AND o.pay_time <= '{$this->today_end}' AND o.pay_status = 1 AND og.goods_id=72 AND og.goods_nums>2";
		$orderGoodsDB->fields = 'o.id, o.order_no, o.user_id, o.pay_time, og.goods_nums';
		$orderGoodsDB->order = 'o.pay_time ASC';

		$orderList = $orderGoodsDB->find();

		if ($orderList)
		{
			$max_nums = 0;
			$max_row = array();
			foreach($orderList as $_item)
			{
				if ($_item['goods_nums'] > $max_nums)
				{
					$max_nums = $_item['goods_nums'];
					$max_row = $_item;
				}
			}

			//获取当前擂主的订单id
			$cache = new ICache('memcache');
			$top_order_id = $cache->get('ring_get_top_order_id');

			//获取擂主的用户信息
			$userDB = new IModel('user');
			$userRow = $userDB->getObj('id = '.$max_row['user_id'], 'username');

			//是否和之前的擂主不同
			if ($top_order_id != $max_row['id'])
			{
				//查询数据库是否有记录
				$activityDB = new IModel('activity_ring');
				$is_existed = $activityDB->getObj('order_id = '.$max_row['id']);
				
				//插入到数据库
				if (! $is_existed)
				{
					$insert = array(
								'order_id' => $max_row['id'],
								'user_id' => $max_row['user_id'],
								'username' => $userRow['username'],
								'create_time' => $max_row['pay_time']
							);
					$activityDB->setData($insert);
					$activityDB->add();
				}

				//缓存更新
				$cache->set('ring_get_top_order_id', $max_row['id'], 3600*24);
			}

			$result = array(
							'status' => 1,
							'username' => substr_replace($userRow['username'],'***',-4,3),
							'got_time' => $max_row['pay_time'],
							'now_time' => date("Y-m-d H:i:s"),
							'goods_nums' => $max_row['goods_nums']
						);
		}
		else
		{
			//当天没有擂主
			$result = array(
							'status' => 0
						);
		}

		echo json_encode($result);
	}

	//剩余时间(s)
	public function ring_get_timeleft()
	{
		$now_time = time();
		$start_time = strtotime("2016-12-26 07:00:00");
		$end_time = strtotime("2017-01-01 00:00:00");

		$result['status'] = 0; //0未开始 -1已结束 1正常
		if ($now_time > $end_time) //活动已结束开始
		{
			$result['status'] = -1;
		}
		elseif($now_time < $end_time && $now_time > $start_time)
		{
			//活动正常
			$result['status'] = 1;
			$result['time'] = $end_time - $now_time;
		}

		echo json_encode($result);
	}

	//获取今日的擂台记录
	public function ring_get_history_users()
	{
		$this->today_start = date("Y-m-d 07:00:00");
		$this->today_end = date("Y-m-d 23:59:59");

		$result = array('status'=>0, 'list' => array());

		$activityRingDB = new IModel('activity_ring');
		$activityRingList = $activityRingDB->query("create_time > '{$this->today_start}' and create_time < '{$this->today_end}'", '*', 'id ASC');

		if ($activityRingList)
		{
			$result['status'] = 1;

			foreach($activityRingList as $_key => $_item)
			{
				if (isset($activityRingList[$_key+1]))
				{
					//擂主持续时间(秒)
					$lastTime = strtotime($activityRingList[$_key+1]['create_time']) - strtotime($_item['create_time']);
				}
				else
				{
					$lastTime = 0;
				}

				//持续时间的奖项
				if ($lastTime > 3600*8)
				{
					$prize = 1;
				}
				elseif ($lastTime > 3600*5)
				{
					$prize = 2;
				}
				elseif ($lastTime > 3600*2)
				{
					$prize = 3;
				}
				else
				{
					$prize = 0;
				}

				$result['list'][] = array(
										'username' => substr_replace($_item['username'],'***',-4,3),
										'last_time' => $lastTime,
										'prize' => $prize
									);
			}

			//list倒序
			$result['list'] = array_reverse($result['list']);
		}

		echo json_encode($result);
	}
}