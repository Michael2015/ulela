<?php
/**
 * @copyright (c) 2016 dyg.cn
 * @file myteam_wechat_qy.php
 * @brief 团队业绩对接微信企业号管理
 * @author jzw
 * @date 2016/8/27
 * @version 1.0
 */
class myteam_wechat_qy extends pluginBase
{
	//插件注册
	public function reg()
	{
		//后台管理团队信息
		plugin::reg("onSystemMenuCreate",function(){
			$link = "/plugins/myteam_tag_list";
			Menu::$menu["插件"]["插件管理"][$link] = $this->name();
		});

		plugin::reg("onBeforeCreateAction@plugins@myteam_tag_list",function(){
			self::controller()->myteam_tag_list = function(){$this->myteam_tag_list();};
		});

		plugin::reg("onBeforeCreateAction@plugins@myteam_tag_edit",function(){
			self::controller()->myteam_tag_edit = function(){$this->myteam_tag_edit();};
		});

		plugin::reg("onBeforeCreateAction@plugins@myteam_tag_update",function(){
			self::controller()->myteam_tag_update = function(){$this->myteam_tag_update();};
		});

		plugin::reg("onBeforeCreateAction@plugins@myteam_tag_del",function(){
			self::controller()->myteam_tag_del = function(){$this->myteam_tag_del();};
		});

		//发送团队业绩消息
		plugin::reg("sendTeamScore",$this,"sendTeamScore");
	}

	//编辑团队信息
	public function myteam_tag_edit()
	{
		$id     = IFilter::act(IReq::get('id'),'int');
		$myteamRow = array();
		if($id)
		{
			$myteamDB = new IModel('myteam_tag');
			$myteamRow = $myteamDB->getObj('id = '.$id);
		}
		$this->redirect('myteam_tag_edit',array('myteamData' => $myteamRow));
	}

	//更新团队信息
	public function myteam_tag_update()
	{
		$id          = IFilter::act(IReq::get('id'),'int');
		$teamname    = trim(IFilter::act(IReq::get('teamname')));
		$username    = trim(IFilter::act(IReq::get('username')));
		$tag_id      = IFilter::act(IReq::get('tag_id'),'int');

		//获取用户ID
		$userDB = new IModel('user');
		$userRow = $userDB->getObj("username = '{$username}'");

		if (empty($userRow))
		{
			die('用户名不存在');
		}

		$updateData  = array(
			'teamname'    => $teamname,
			'username'    => $username,
			'tag_id'      => $tag_id,
			'user_id'     => $userRow['id'],
			'datetime'    => ITime::getDateTime()
		);

		$myteamDB = new IModel('myteam_tag');
		$myteamDB->setData($updateData);
		if($id)
		{
			$myteamDB->update('id = '.$id);
		}
		else
		{
			$myteamDB->add();
		}
		$this->redirect('myteam_tag_list',true);
	}

	//删除团队信息
	public function myteam_tag_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		if($id)
		{
			$myteamDB = new IModel('myteam_tag');
			$myteamDB->del('id = '.$id);
		}
		$this->redirect('myteam_tag_list',true);
	}

	//团队标签列表信息
	public function myteam_tag_list()
	{
		$this->redirect('myteam_tag_list');
	}

	public static function name()
	{
		return "团队业绩对接微信企业号管理";
	}

	public static function description()
	{
		return "每天推送团队业绩至企业号的团队";
	}

	public static function install()
	{
		$myteamDB = new IModel('myteam_tag');
		if($myteamDB->exists())
		{
			return true;
		}
		$data = array(
			"comment" => self::name(),
			"column"  => array(
				"id"         => array("type" => "int(11) unsigned",'auto_increment' => 1),
				"teamname"   => array("type" => "varchar(64)","comment" => "队伍名称"),
				"user_id"    => array("type" => "int(11)","comment" => "队长用户ID"),
				"username"   => array("type" => "varchar(64)","comment" => "队长用户名"),
				"tag_id"     => array("type" => "int(11)","comment" => "企业号对应标签ID"),
				"datetime"   => array("type" => "datetime","comment" => "创建时间"),
			),
			"index" => array("primary" => "id","key" => "user_id"),
		);
		$myteamDB->setData($data);
		return $myteamDB->createTable();
	}

	public static function uninstall()
	{
		$myteamDB = new IModel('myteam_tag');
		return $myteamDB->dropTable();
	}

	public static function configName()
	{
		return array(
			"wechat_qy_appid" => array("name" => "企业号发送APP_Id","type" => "text"),
			"wechat_qy_corpid" => array("name" => "企业号发送APP_Corpid","type" => "text"),
			"wechat_qy_secret" => array("name" => "企业号发送APP_Secret","type" => "text"),
			"count_cats" => array("name" => "统计的分类ID(半角逗号隔开)","type" => "text"),
		);
	}

	//运行发送团队业绩信息
	public function sendTeamScore()
	{
		//加载企业号发送class
		require_once(dirname(__FILE__)."/wechat_qy/wechat_qy.php");
		$wechat_qy = new wechat_qy();

		//获取插件配置
		$defConfig= $this->config();

		//获取所有队伍
		$myteamDB = new IModel('myteam_tag');
		$myteam_list = $myteamDB->query();

		$userDB = new IModel('user');
		$orderDB = new IModel('order');
		$orderGoodsDB = new IModel('order_goods');

		$yesterday_start = date("Y-m-d 00:00:00", strtotime("-1 day"));
		$yesterday_end = date("Y-m-d 23:59:59", strtotime("-1 day"));

		//需统计产品分类
		$catDB = new IModel('category_extend');
		$cats = $defConfig['count_cats'];
		$cats = implode(',', explode(',', $cats));

		$goods_ids = $catDB->query("category_id in ({$cats})");

		//所有符合统计分类的产品id
		$all_goods = array();
		foreach ($goods_ids as $_goods)
		{
			$all_goods[$_goods['goods_id']] = $_goods['goods_id'];
		}
		$all_goods = implode(',', $all_goods);

		//所有团队业绩统计
		$count_all_team = array();

		foreach ($myteam_list as $_team)
		{
			$count_all_team[$_team['id']] = array(
									'name' => $_team['teamname'], //队名
									'members' => array(), //队员
									'score' => 0 //总成绩
								);

			//获取队长下面的各位队员
			$team_users = $userDB->query("inviter = '{$_team['username']}'", 'id, username');
			//添加队长信息
			$team_users[] = array(
								'id' => $_team['user_id'],
								'username' => $_team['username']
							);

			//每位成员昨天的消费金额
			foreach ($team_users as $_user)
			{
				$count_all_team[$_team['id']]['members'][$_user['id']] = array(
																		'username' => $_user['username'],
																		'score' => 0
																	);

				//获取已支付或已完成的订单
				$order_list = $orderDB->query("user_id = '{$_user['id']}' and if_del = 0 and status in (2,5) and pay_time > '{$yesterday_start}' and pay_time < '{$yesterday_end}'", 'id');


				if ($order_list)
				{
					$order_ids = array();

					foreach ($order_list as $_order)
					{
						$order_ids[] = $_order['id'];
					}
					$order_ids = implode(',', $order_ids);

					//获取订单商品
					$order_goods_list = $orderGoodsDB->query("order_id in ({$order_ids}) and goods_id in ({$all_goods}) and is_send <> 2");


					if ($order_goods_list)
					{
						//统计订单商品的实付金额
						$_tmp_score = 0;
						foreach ($order_goods_list as $_order_goods)
						{
							$_tmp_score += $_order_goods['real_price'] * $_order_goods['goods_nums'];
						}

						$count_all_team[$_team['id']]['members'][$_user['id']]['score'] = $_tmp_score;
						$count_all_team[$_team['id']]['score'] += $_tmp_score;
					}
				}				
			}

			$team_score_order = $this->my_sort($count_all_team[$_team['id']]['members'], 'score', SORT_DESC);

			$message = "队伍[{$_team['teamname']}] ".substr($yesterday_start, 0, 10)." 销售合计：{$count_all_team[$_team['id']]['score']}\n\n尾号 | 销售额\n";

			foreach ($team_score_order as $_score_order)
			{
				$message .= substr($_score_order['username'], -4) . "【{$_score_order['score']}】\n";
			}
			$message .= '(仅本队查看)以上为实时数据，确认团队业绩以官方为准';

			//发送微信企业号信息
			$message_body = array(
				                'totag' => $_team['tag_id'],
				                'msgtype' => 'text',
				                'agentid' => $defConfig['wechat_qy_appid'],
				                'text' => array(
				                    'content' => $message
				                )
				            );

			$wechat_qy->access_request($defConfig['wechat_qy_corpid'], $defConfig['wechat_qy_secret'], 'message/send', $wechat_qy->replace_post($message_body));

		}

		$all_team_score_order = $this->my_sort($count_all_team, 'score', SORT_DESC);

		$message = "阳光组队 ".substr($yesterday_start, 0, 10)." 销售排名：\n\n";

		//发送全部队伍汇总金额
		foreach ($all_team_score_order as $key => $_team)
		{
			$_team['score'] = intval($_team['score']);
			$message .= 'No.'.($key+1) . "【{$_team['name']}】￥" . str_pad(substr($_team['score'], 0, 1), strlen($_team['score']), '*') ."\n"; 
		}

		//发送微信企业号信息
		$message_body = array(
			                'touser' => '@all',
			                'msgtype' => 'text',
			                'agentid' => $defConfig['wechat_qy_appid'],
			                'text' => array(
			                    'content' => $message
			                )
			            );

		$wechat_qy->access_request($defConfig['wechat_qy_corpid'], $defConfig['wechat_qy_secret'], 'message/send', $wechat_qy->replace_post($message_body));


	}


	private function my_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){   
        if(is_array($arrays)){   
            foreach ($arrays as $array){   
                if(is_array($array)){   
                    $key_arrays[] = $array[$sort_key];   
                }else{   
                    return false;   
                }   
            }   
        }else{   
            return false;   
        }  
        array_multisort($key_arrays,$sort_order,$sort_type,$arrays);   
        return $arrays;   
    }

	
}