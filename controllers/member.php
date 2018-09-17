<?php
/**
 * @brief 会员模块
 * @class Member
 * @note  后台
 */
class Member extends IController implements adminAuthorization
{
	public $checkRight  = 'all';
    public $layout='admin';
	private $data = array();

	function init()
	{

	}

	/**
	 * @brief 添加会员
	 */
	function member_edit()
	{
		$uid  = IFilter::act(IReq::get('uid'),'int');

		//编辑会员信息读取会员信息
		if($uid)
		{
			$userDB = new IQuery('user as u');
			$userDB->join = 'left join member as m on u.id = m.user_id';
			$userDB->where= 'u.id = '.$uid;
			$userInfo = $userDB->find();

			if($userInfo)
			{
				$this->userInfo = current($userInfo);
			}
			else
			{
				$this->member_list();
				Util::showMessage("没有找到相关记录！");
				exit;
			}
		}
		$this->redirect('member_edit');
	}

	//保存会员信息
	function member_save()
	{
		$user_id    = IFilter::act(IReq::get('user_id'),'int');
		$user_name  = IFilter::act(IReq::get('username'));
		$email      = IFilter::act(IReq::get('email'));
		$password   = IFilter::act(IReq::get('password'));
		$repassword = IFilter::act(IReq::get('repassword'));
		$group_id   = IFilter::act(IReq::get('group_id'),'int');
		$truename   = IFilter::act(IReq::get('true_name'));
		$sex        = IFilter::act(IReq::get('sex'),'int');
		$telephone  = IFilter::act(IReq::get('telephone'));
		$mobile     = IFilter::act(IReq::get('mobile'));
		$province   = IFilter::act(IReq::get('province'),'int');
		$city       = IFilter::act(IReq::get('city'),'int');
		$area       = IFilter::act(IReq::get('area'),'int');
		$contact_addr = IFilter::act(IReq::get('contact_addr'));
		$zip        = IFilter::act(IReq::get('zip'));
		$qq         = IFilter::act(IReq::get('qq'));
		$exp        = IFilter::act(IReq::get('exp'),'int');
		$point      = IFilter::act(IReq::get('point'),'int');
		$status     = IFilter::act(IReq::get('status'),'int');

		$_POST['area'] = "";
		if($province && $city && $area)
		{
			$_POST['area'] = array($province,$city,$area);
		}

		if(!$user_id && $password == '')
		{
			$this->setError('请输入密码！');
		}

		if($password != $repassword)
		{
			$this->setError('两次输入的密码不一致！');
		}

		//创建会员操作类
		$userDB   = new IModel("user");
		$memberDB = new IModel("member");
		if($userDB->getObj("username='".$user_name."' and id != ".$user_id))
		{
			$this->setError('用户名重复');
		}
		if($email && $memberDB->getObj("email='".$email."' and user_id != ".$user_id))
		{
			$this->setError('邮箱重复');
		}
		if($mobile && $memberDB->getObj("mobile='".$mobile."' and user_id != ".$user_id))
		{
			$this->setError('手机号码重复');
		}
		//操作失败表单回填
		if($errorMsg = $this->getError())
		{
			$this->userInfo = $_POST;
			$this->redirect('member_edit',false);
			Util::showMessage($errorMsg);
		}

		$member = array(
			'email'        => $email,
			'true_name'    => $truename,
			'telephone'    => $telephone,
			'mobile'       => $mobile,
			'area'         => $_POST['area'] ? ",".join(",",$_POST['area'])."," : "",
			'contact_addr' => $contact_addr,
			'qq'           => $qq,
			'sex'          => $sex,
			'zip'          => $zip,
			'exp'          => $exp,
			'point'        => $point,
			'group_id'     => $group_id,
			'status'       => $status,
		);


		//添加新会员
		if(!$user_id)
		{
			$user = array(
				'username' => $user_name,
				'password' => md5($password),
			);
			$userDB->setData($user);
			$user_id = $userDB->add();

			$member['user_id'] = $user_id;
			$member['time']    = ITime::getDateTime();

			$memberDB->setData($member);
			$memberDB->add();
		}
		//编辑会员
		else
		{
			$user = array(
				'username' => $user_name,
			);
			//修改密码
			if($password)
			{
				$user['password'] = md5($password);
			}
			$userDB->setData($user);
			$userDB->update('id = '.$user_id);

			$member_info = $memberDB->getObj('user_id='.$user_id);

			//修改积分记录日志
			if($point != $member_info['point'])
			{
				$ctrlType = $point > $member_info['point'] ? '增加' : '减少';
				$diffPoint= $point-$member_info['point'];

				$pointObj = new Point();
				$pointConfig = array(
					'user_id' => $user_id,
					'point'   => $diffPoint,
					'log'     => '管理员'.$this->admin['admin_name'].'将积分'.$ctrlType.$diffPoint.'积分',
				);
				$pointObj->update($pointConfig);

				//dyg_jzw 20151014 增加管理员日志
				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'], "修改账户积分", "会员账户ID[".$member_info['user_id']."]积分由 ".$member_info['point']." 修改为 ".$point));
			}

			//修改经验值记录日志
			if($exp != $member_info['exp'])
			{
				$ctrlType = $exp > $member_info['exp'] ? '增加' : '减少';
				$diffExp = $exp - $member_info['exp'];

				$expObj = new Exp();
				$expConfig = array(
					'user_id' => $user_id,
					'point'   => $diffExp,
					'log'     => '管理员'.$this->admin['admin_name'].'为账户ID['.$user_id.']'.$ctrlType.$diffExp.'经验值',
				);
				$expObj->update($expConfig);

				//dyg_jzw 20151014 增加管理员日志
				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'], "修改账户经验值", "会员账户ID[".$member_info['user_id']."]经验值由 ".$member_info['exp']." 修改为 ".$exp));
			}

			//修改会员组记录
			if($group_id != $member_info['group_id'])
			{
				//dyg_jzw 20151014 增加管理员日志
				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'], "修改账户等级", "会员账户ID[".$member_info['user_id']."]GroupID由 [".$member_info['group_id']."] 修改为 [".$group_id.']'));
			}

			$memberDB->setData($member);
			$memberDB->update("user_id = ".$user_id);			

		}
		$this->redirect('member_list');
	}

	/**
	 * @brief 会员列表
	 */
	function member_list()
	{
		$search = IFilter::act(IReq::get('search'),'strict');
		$keywords = IFilter::act(IReq::get('keywords'));
		$where = ' 1 ';
		if($search && $keywords)
		{
			//$where .= " and $search like '%{$keywords}%' ";
			//dyg_jzw 修改条件为精确
			$where .= " and $search = '{$keywords}' ";
		}
		$this->data['search'] = $search;
		$this->data['keywords'] = $keywords;
		$this->data['where'] = $where;
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$group      = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
		}
		$this->data['group'] = $group;
		$this->setRenderData($this->data);
		$this->redirect('member_list');
	}

	/**
	 * 经验值日志记录
	 * dyg_jzw 20160417
	 */
	function exp_log_list()
	{
		$page		= IFilter::act(IReq::get('page'),'int');
		$page 		= $page ? $page : 1;
		$user_id  	= IFilter::act(IReq::get('user_id'),'int');

		$where      = "1";
		
		if($user_id)
		{
			$where .= ' and user_id = '.$user_id;
		}

		$expLogObj = new IQuery('exp_log');
		$expLogObj->where = $where;
		$expLogObj->order = 'id desc';
		$expLogObj->page  = $page;
		
		$this->expLogObj 	 = $expLogObj;
		$this->expLogList = $expLogObj->find();
		$this->user_id = $user_id?$user_id:'';
		
		$this->redirect('exp_log_list');
	}

	/**
	 * 用户经验值更新
	 * dyg_jzw 20160418
	 */
	function exp_save()
	{
		$exp_log 	= IFilter::act(IReq::get('exp_log'));
		$exp_num 	= IFilter::act(IReq::get('exp_num'), 'int');
		$username  	= IFilter::act(IReq::get('username'));

		if ($username)
		{
			//查找用户信息
			$userDB = new IModel("user");
			if( ! $user_info = $userDB->getObj("username='".$username."'"))
			{
				$this->redirect('/member/exp_log_list');
			}
		}

		if ($exp_log && $username) //增加或减少经验值
		{

			//更新用户信息
			$exp_config = array(
							'user_id' 	=> 	$user_info['id'],
							'exp'		=>	$exp_num,
							'log'		=> 	$exp_log
						);

			$expLog = new Exp();
			if( ! $expLog->update($exp_config))
			{
				$this->redirect('/member/exp_log_list');
			}
		}
		
		$this->redirect('/member/exp_log_list?user_id='.$user_info['id']);
	}

	//dyg_jzw 20160913
	//邀请人设置功能
	function member_inviter_edit()
	{
		//检查表单提交状态
		if($_POST)
		{
			$username = IFilter::act(IReq::get('username'));
			$inviter = IFilter::act(IReq::get('inviter'));

			if ($username && $inviter)
			{
				$userDB = new IModel("user");
				$is_exist = $userDB->query("username in ('{$username}','{$inviter}')");

				if ($is_exist && count($is_exist) == 2)
				{
					//修改邀请人
					$update = array('inviter' => $inviter);
					$userDB->setData($update);
					$userDB->update("username = '{$username}'");
				}
				else
				{
					Util::showMessage("被邀用户名或邀请人用户名不存在");
				}
			}
			else
			{
				Util::showMessage("表单填写不完整");
			}
		}
		
		$this->redirect('/member/inviter_edit');
	}

	//dyg_jzw 20161012
	//佣金手动发放
	function member_commission_add()
	{
		//检查表单提交状态
		if($_POST)
		{
			$username = IFilter::act(IReq::get('username'));
			$money = floatval(IFilter::act(IReq::get('money')));
			$message = IFilter::act(IReq::get('message'));
			$type = IFilter::act(IReq::get('type'));

			if ($username && $money)
			{
				$userDB = new IModel("user");
				$userRow = $userDB->getObj("username = '{$username}'");

				if ($userRow)
				{
					$accLog = new AccountLog();
	                $config=array(
	                    'user_id'  => $userRow['id'],
	                    'event'    => $type,
	                    'note'     => $message,
	                    'num'      => $money
	                );
	                $is_success = $accLog->write($config);
				}
				else
				{
					Util::showMessage("用户名不存在");
				}
			}
			else
			{
				Util::showMessage("表单填写不完整");
			}
		}
		
		$this->redirect('/member/commission_add');
	}

	/**
	 * 用户余额管理页面
	 */
	function member_balance()
	{
		$this->layout = '';
		$this->redirect('member_balance');
	}

	/**
	 * @brief 删除至回收站
	 */
	function member_reclaim()
	{
		$user_ids = IReq::get('check');
		$user_ids = is_array($user_ids) ? $user_ids : array($user_ids);
		$user_ids = IFilter::act($user_ids,'int');
		if($user_ids)
		{
			$ids = implode(',',$user_ids);
			if($ids)
			{
				$tb_member = new IModel('member');
				$tb_member->setData(array('status'=>'2'));
				$where = "user_id in (".$ids.")";
				$tb_member->update($where);
			}
		}
		$this->member_list();
	}
	//批量用户余额操作
    function member_recharge()
    {
    	$id       = IFilter::act(IReq::get('check'),'int');
    	$balance  = IFilter::act(IReq::get('balance'),'float');
    	$type     = IFIlter::act(IReq::get('type')); //操作类型 recharge充值,withdraw提现金
    	$note     = IFIlter::act(IReq::get('note')); //dyg_jzw 20160704 加入备注输入
    	$even     = '';

    	if(!$id)
    	{
			die(JSON::encode(array('flag' => 'fail','message' => '请选择要操作的用户')));
			return;
    	}

    	//执行写入操作
    	$id = is_array($id) ? join(',',$id) : $id;
    	$memberDB = new IModel('member');
    	$memberData = $memberDB->query('user_id in ('.$id.')');

		foreach($memberData as $value)
		{
			//用户余额进行的操作记入account_log表
			$log = new AccountLog();
			$config=array
			(
				'user_id'  => $value['user_id'],
				'admin_id' => $this->admin['admin_id'],
				'event'    => $type,
				'num'      => $balance,
				'note'	   => "管理员[{$this->admin['admin_name']}]操作，备注：".$note
			);
			$re = $log->write($config);
			if($re == false)
			{
				die(JSON::encode(array('flag' => 'fail','message' => $log->error)));
			}
		}
		die(JSON::encode(array('flag' => 'success')));
    }
	/**
	 * @brief 用户组添加
	 */
	function group_edit()
	{
		$gid = (int)IReq::get('gid');
		//编辑会员等级信息 读取会员等级信息
		if($gid)
		{
			$tb_user_group = new IModel('user_group');
			$group_info = $tb_user_group->query("id=".$gid);

			if(is_array($group_info) && ($info=$group_info[0]))
			{
				$this->data['group'] = array(
					'group_id'	=>	$info['id'],
					'group_name'=>	$info['group_name'],
					'discount'	=>	$info['discount'],
					'minexp'	=>	$info['minexp'],
					'maxexp'	=>	$info['maxexp'],
					'point_times'	=>	$info['point_times'], //dyg_jzw 20150618
				);
			}
			else
			{
				$this->redirect('group_list',false);
				Util::showMessage("没有找到相关记录！");
				return;
			}
		}
		$this->setRenderData($this->data);
		$this->redirect('group_edit');
	}

	/**
	 * @brief 保存用户组修改
	 */
	function group_save()
	{
		$group_id = IFilter::act(IReq::get('group_id'),'int');
		$maxexp   = IFilter::act(IReq::get('maxexp'),'int');
		$minexp   = IFilter::act(IReq::get('minexp'),'int');
		$discount = IFilter::act(IReq::get('discount'),'float');
		$group_name = IFilter::act(IReq::get('group_name'));
		$point_times = IFilter::act(IReq::get('point_times'),'float'); //dyg_jzw 20150618

		$group = array(
			'maxexp' => $maxexp,
			'minexp' => $minexp,
			'discount' => $discount,
			'group_name' => $group_name,
			'point_times'	=>	$point_times, //dyg_jzw 20150618
		);

		if($discount > 100)
		{
			$errorMsg = '折扣率不能大于100';
		}

		if($maxexp <= $minexp)
		{
			$errorMsg = '最大经验值必须大于最小经验值';
		}

		if(isset($errorMsg) && $errorMsg)
		{
			$group['group_id'] = $group_id;
			$data = array($group);

			$this->setRenderData($data);
			$this->redirect('group_edit',false);
			Util::showMessage($errorMsg);
			exit;
		}
		$tb_user_group = new IModel("user_group");
		$tb_user_group->setData($group);

		if($group_id)
		{
			$affected_rows = $tb_user_group->update("id=".$group_id);
			$this->redirect('group_list');
		}
		else
		{
			$tb_user_group->add();
			$this->redirect('group_list');
		}
	}

	/**
	 * @brief 删除会员组
	 */
	function group_del()
	{
		$group_ids = IReq::get('check');
		$group_ids = is_array($group_ids) ? $group_ids : array($group_ids);
		$group_ids = IFilter::act($group_ids,'int');
		if($group_ids)
		{
			$ids = implode(',',$group_ids);
			if($ids)
			{
				$tb_user_group = new IModel('user_group');
				$where = "id in (".$ids.")";
				$tb_user_group->del($where);
			}
		}
		$this->redirect('group_list');
	}

	/**
	 * @brief 回收站
	 */
	function recycling()
	{
		$search = IReq::get('search');
		$keywords = IReq::get('keywords');
		$search_sql = IFilter::act($search,'strict');
		$keywords = IFilter::act($keywords,'strict');

		$where = ' 1 ';
		if($search && $keywords)
		{
			$where .= " and $search_sql like '%{$keywords}%' ";
		}
		$this->data['search'] = $search;
		$this->data['keywords'] = $keywords;
		$this->data['where'] = $where;
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$data_group = is_array($data_group) ? $data_group : array();
		$group = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
		}
		$this->data['group'] = $group;
		$this->setRenderData($this->data);
		$this->redirect('recycling');
	}

	/**
	 * @brief 彻底删除会员
	 */
	function member_del()
	{
		$user_ids = IReq::get('check');
		$user_ids = is_array($user_ids) ? $user_ids : array($user_ids);
		$user_ids = IFilter::act($user_ids,'int');
		if($user_ids)
		{
			$ids = implode(',',$user_ids);

			if($ids)
			{
				$tb_member = new IModel('member');
				$where = "user_id in (".$ids.")";
				$tb_member->del($where);

				$tb_user = new IModel('user');
				$where = "id in (".$ids.")";
				$tb_user->del($where);

				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"删除了用户","被删除的用户ID为：".$ids));
			}
		}
		$this->redirect('member_list');
	}

	/**
	 * @brief 从回收站还原会员
	 */
	function member_restore()
	{
		$user_ids = IReq::get('check');
		$user_ids = is_array($user_ids) ? $user_ids : array($user_ids);
		if($user_ids)
		{
			$user_ids = IFilter::act($user_ids,'int');
			$ids = implode(',',$user_ids);
			if($ids)
			{
				$tb_member = new IModel('member');
				$tb_member->setData(array('status'=>'1'));
				$where = "user_id in (".$ids.")";
				$tb_member->update($where);
			}
		}
		$this->redirect('recycling');
	}

	//[提现管理] 删除
	function withdraw_del()
	{
		$id = IFilter::act(IReq::get('id'));

		if($id)
		{
			$id = IFilter::act($id,'int');
			$withdrawObj = new IModel('withdraw');

			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}

			$withdrawObj->del($where);
			$this->redirect('withdraw_recycle');
		}
		else
		{
			$this->redirect('withdraw_recycle',false);
			Util::showMessage('请选择要删除的数据');
		}
	}

	//[提现管理] 回收站 删除,恢复
	function withdraw_update()
	{
		$id   = IFilter::act( IReq::get('id') , 'int' );
		$type = IReq::get('type') ;

		if(!empty($id))
		{
			$withdrawObj = new IModel('withdraw');

			$is_del = ($type == 'res') ? '0' : '1';
			$dataArray = array(
				'is_del' => $is_del
			);

			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}

			$dataArray = array(
				'is_del' => $is_del,
			);

			$withdrawObj->setData($dataArray);
			$withdrawObj->update($where);
			$this->redirect('withdraw_list');
		}
		else
		{
			if($type == 'del')
			{
				$this->redirect('withdraw_list',false);
			}
			else
			{
				$this->redirect('withdraw_recycle',false);
			}
			Util::showMessage('请选择要删除的数据');
		}
	}

	//[提现管理] 详情展示
	function withdraw_detail()
	{
		$id = IFilter::act( IReq::get('id'),'int' );

		if($id)
		{
			$withdrawObj = new IModel('withdraw');
			$where       = 'id = '.$id;
			$this->withdrawRow = $withdrawObj->getObj($where);

			$userDB = new IModel('user as u,member as m');
			$this->userRow = $userDB->getObj('u.id = m.user_id and u.id = '.$this->withdrawRow['user_id']);
			$this->redirect('withdraw_detail',false);
		}
		else
		{
			$this->redirect('withdraw_list');
		}
	}

	//[提现管理] 修改提现申请的状态
	function withdraw_status()
	{
		$id      = IFilter::act( IReq::get('id'),'int');
		$re_note = IFilter::act( IReq::get('re_note'),'string');
		$status  = IFilter::act(IReq::get('status'),'int');

		if($id)
		{
			$withdrawObj = new IModel('withdraw');
			$dataArray = array(
				're_note'=> $re_note,
				'status' => $status,
			);

			$withdrawObj->setData($dataArray);
			$where = "`id`= {$id} AND `status` = 0";
			$re = $withdrawObj->update($where);

			if($re)
			{
				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改了提现申请","ID值为：".$id));

				$withdrawRow = $withdrawObj->getObj('id = '.$id);

				//提现成功
				if($status == 2)
				{
					//dyg_jzw 20150817 提现成功无需再扣除余额, 因为申请时已冻结
					/*
					//用户余额进行的操作记入account_log表
					$log = new AccountLog();
					$config=array
					(
						'user_id'  => $withdrawRow['user_id'],
						'admin_id' => $this->admin['admin_id'],
						'event'    => "withdraw",
						'num'      => $withdrawRow['amount'],
					);
					$result = $log->write($config);
					$result ? "" : die($log->error);
					*/
				}
				else if($status == -1) //dyg_jzw 20150817 拒绝提现, 需要返回提现的冻结余额
				{
					//用户余额进行的操作记入account_log表
				        $accountLog = new AccountLog();
				        $config=array(
				            'user_id'  => $withdrawRow['user_id'],
							'admin_id' => $this->admin['admin_id'],
				            'event'    => 'deny_apply_withdraw',
				            'num'      => $withdrawRow['amount'],
				        );
				        $result = $accountLog->write($config);
					is_string($result) ? die($result) : "";
				}
			}
			$this->withdraw_detail();
			Util::showMessage("更新成功");
		}
		else
		{
			$this->redirect('withdraw_list');
		}
	}

	/**
	 * @brief 商家修改页面
	 */
	public function seller_edit()
	{
		$seller_id = IFilter::act(IReq::get('id'),'int');

		//修改页面
		if($seller_id)
		{
			$sellerDB        = new IModel('seller');
			$this->sellerRow = $sellerDB->getObj('id = '.$seller_id);
		}
		$this->redirect('seller_edit');
	}

	/**
	 * @brief 商户的增加动作
	 */
	public function seller_add()
	{
		$seller_id   = IFilter::act(IReq::get('id'),'int');
		$seller_name = IFilter::act(IReq::get('seller_name'));
		$email       = IFilter::act(IReq::get('email'));
		$password    = IFilter::act(IReq::get('password'));
		$repassword  = IFilter::act(IReq::get('repassword'));
		$truename    = IFilter::act(IReq::get('true_name'));
		$phone       = IFilter::act(IReq::get('phone'));
		$mobile      = IFilter::act(IReq::get('mobile'));
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$cash        = IFilter::act(IReq::get('cash'),'float');
		$is_vip      = IFilter::act(IReq::get('is_vip'),'int');
		$is_lock     = IFilter::act(IReq::get('is_lock'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$account     = IFilter::act(IReq::get('account'));
		$server_num  = IFilter::act(IReq::get('server_num'));
		$home_url    = IFilter::act(IReq::get('home_url'));
		$sort        = IFilter::act(IReq::get('sort'),'int');

		if(!$seller_id && $password == '')
		{
			$errorMsg = '请输入密码！';
		}

		if($password != $repassword)
		{
			$errorMsg = '两次输入的密码不一致！';
		}

		//创建商家操作类
		$sellerDB = new IModel("seller");

		if($sellerDB->getObj("seller_name = '{$seller_name}' and id != {$seller_id}"))
		{
			$errorMsg = "登录用户名重复";
		}
		else if($sellerDB->getObj("true_name = '{$truename}' and id != {$seller_id}"))
		{
			$errorMsg = "商户真实全程重复";
		}

		//操作失败表单回填
		if(isset($errorMsg))
		{
			$this->sellerRow = $_POST;
			$this->redirect('seller_edit',false);
			Util::showMessage($errorMsg);
		}

		//待更新的数据
		$sellerRow = array(
			'true_name' => $truename,
			'account'   => $account,
			'phone'     => $phone,
			'mobile'    => $mobile,
			'email'     => $email,
			'address'   => $address,
			'is_vip'    => $is_vip,
			'is_lock'   => $is_lock,
			'cash'      => $cash,
			'province'  => $province,
			'city'      => $city,
			'area'      => $area,
			'server_num'=> $server_num,
			'home_url'  => $home_url,
			'sort'      => $sort,
		);

		//商户资质上传
		if(isset($_FILES['paper_img']['name']) && $_FILES['paper_img']['name'])
		{
			$uploadObj = new PhotoUpload();
			$uploadObj->setIterance(false);
			$photoInfo = $uploadObj->run();
			if(isset($photoInfo['paper_img']['img']) && file_exists($photoInfo['paper_img']['img']))
			{
				$sellerRow['paper_img'] = $photoInfo['paper_img']['img'];
			}
		}

		//添加新会员
		if(!$seller_id)
		{
			$sellerRow['seller_name'] = $seller_name;
			$sellerRow['password']    = md5($password);
			$sellerRow['create_time'] = ITime::getDateTime();

			$sellerDB->setData($sellerRow);
			$sellerDB->add();
		}
		//编辑会员
		else
		{
			//修改密码
			if($password)
			{
				$sellerRow['password'] = md5($password);
			}

			$sellerDB->setData($sellerRow);
			$sellerDB->update("id = ".$seller_id);
		}
		$this->redirect('seller_list');
	}
	/**
	 * @brief 商户的删除动作
	 */
	public function seller_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$sellerDB = new IModel('seller');
		$data = array('is_del' => 1);
		$sellerDB->setData($data);

		if(is_array($id))
		{
			$sellerDB->update('id in ('.join(",",$id).')');
		}
		else
		{
			$sellerDB->update('id = '.$id);
		}
		$this->redirect('seller_list');
	}
	/**
	 * @brief 商户的回收站删除动作
	 */
	public function seller_recycle_del()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$sellerDB = new IModel('seller');
		$goodsDB  = new IModel('goods');
		$merch_ship_infoDB = new IModel('merch_ship_info');
		$specDB = new IModel('spec');

		if(is_array($id))
		{
			$id = join(",",$id);
		}
		
		$sellerDB->del('id in ('.$id.')');
		$goodsDB->del('seller_id in ('.$id.')');
		$merch_ship_infoDB->del('seller_id in ('.$id.')');
		$specDB->del('seller_id in ('.$id.')');

		$this->redirect('seller_recycle_list');
	}
	/**
	 * @brief 商户的回收站恢复动作
	 */
	public function seller_recycle_restore()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$sellerDB = new IModel('seller');
		$data = array('is_del' => 0);
		$sellerDB->setData($data);
		if(is_array($id))
		{
			$sellerDB->update('id in ('.join(",",$id).')');
		}
		else
		{
			$sellerDB->update('id = '.$id);
		}

		$this->redirect('seller_recycle_list');
	}
	//商户状态ajax
	public function ajax_seller_lock()
	{
		$id   = IFilter::act(IReq::get('id'));
		$lock = IFilter::act(IReq::get('lock'));
		$sellerObj = new IModel('seller');
		$sellerObj->setData(array('is_lock' => $lock));
		$sellerObj->update("id = ".$id);

		//短信通知状态修改
		$sellerRow = $sellerObj->getObj('id = '.$id);
		if(isset($sellerRow['mobile']) && $sellerRow['mobile'])
		{
			$result = $lock == 0 ? "正常" : "锁定";
			$content = smsTemplate::sellerCheck(array('{result}' => $result));

			//dyg_jzw 20150811 添加短信发送设置
			$config = new Config('site_config');
			$sms_send_config = explode(';', $config->sms_send_config);

			if($sms_send_config && in_array('sms_seller_status', $sms_send_config))
			{
				$result = Hsms::send($sellerRow['mobile'],$content,0);
			}
			
		}
	}
}