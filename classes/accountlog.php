<?php
/**
 * @copyright (c) 2015 www.aircheng.com
 * @file accountlog.php
 * @brief 账户日志管理
 * @author nswe
 * @date 2015/1/26 11:11:50
 * @version 3.0.0
 */

/**
 * 将对用户余额进行的操作记入account_log表
 *
 * $user_id = 用户id
 *
 * $log = new AccountLog();
 * $config=array(
 *      'user_id'   => 用户ID
 *      'seller_id' => 商户ID
 *		'admin_id'  => 管理员ID
 *		'event'     => 操作类别 withdraw:提现,pay:余额支付,recharge:充值,drawback:退款到余额
 *		'note'      => 备注信息 如果不设置的话则根据event类型自动生成，如果设置了则不再对数据完整性进行检测，比如是否设置了管理员id、订单信息等
 *		'num'       => 金额     整形或者浮点，正为增加，负为减少
 * 		'order_no'  => 订单号   drawback类型的log需要这个值
 * 	);
 * $re = $log->write($config);
 *
 * 如果$re是字符串表示错误信息
 *
 * @author nswe
 */
class AccountLog
{
	private $user     = null;
	private $admin    = null;
	private $seller   = null;
	private $config   = null;
	private $event    = null;
	private $amount   = 0;
	private $noteData = "";
	public  $error    = "";//错误信息

	private $allow_event = array(
		'recharge'=> 1,//充值到余额
		'withdraw'=> 2,//从余额提现
		'pay'     => 3,//从余额支付
		'drawback'=> 4,//退款到余额
		'apply_withdraw' => 5,//dyg_jzw 20150817 用户申请余额提现
		'cancel_apply_withdraw' => 6, //dyg_jzw 20150817 用户取消余额提现申请
		'deny_apply_withdraw' => 7, //dyg_jzw 20150817 拒绝用户提现申请,返还冻结款
		'add_commission' => 8, //dyg_jzw 20180928 增加用户佣金
	);

	/**
	 * 写入日志并且更新账户余额
	 * @param array $config config数据类型
	 * @return string|bool
	 */
	public function write($config)
	{
		if(isset($config['user_id']))
		{
			$this->setUser($config['user_id']);
		}
		else
		{
			$this->error = "用户信息不存在";
			return false;
		}

		isset($config['seller_id']) ? $this->setSeller($config['seller_id']) : "";
		isset($config['admin_id'])  ? $this->setAdmin($config['admin_id'])   : "";
		isset($config['event'])     ? $this->setEvent($config['event'])      : "";

		if( isset($config['num']) && is_numeric($config['num']) )
		{
			$this->amount = abs(round($config['num'],2));

			//金额正负值处理
			if(in_array($this->allow_event[$this->event],array(2,3,5))) //dyg_jzw 20150925 增加提现申请, 暂扣余额
			{
				$this->amount = '-'.abs($this->amount);
			}
		}
		else
		{
			$this->error = "金额必须大于0元";
			return false;
		}

		$this->config   = $config;
		$this->noteData = isset($config['note']) ? $config['note'] : $this->note();

		//写入数据库
		$finnalAmount = $this->user['balance'] + $this->amount;
		if($finnalAmount < 0)
		{
			$this->error = "用户余额不足";
			return false;
		}
		//对用户余额进行更新
		$memberDB = new IModel('member');
		$memberDB->setData(array("balance" => $finnalAmount));
		$isChBalance = $memberDB->update("user_id = ".$this->user['id']);
		if(!$isChBalance)
		{
			$this->error = "用户余额数据更新失败";
			return false;
		}

		$tb_account_log = new IModel("account_log");
		$insertData = array(
			'admin_id'  => $this->admin ? $this->admin['id'] : 0,
			'user_id'   => $this->user['id'],
			'event'     => $this->allow_event[$this->event],
			'note'      => $this->noteData,
			'amount'    => $this->amount,
			'amount_log'=> $finnalAmount,
			'type'      => $this->amount >= 0 ? 0 : 1,
			'time'      => ITime::getDateTime(),
		);
		$tb_account_log->setData($insertData);
		$result = $tb_account_log->add();

		//后台管理员操作记录
		if($insertData['admin_id'])
		{
			$logObj = new log('db');
			$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"对账户金额进行了修改",$insertData['note']));
		}
		return $result;
	}

	//设置用户信息
	private function setUser($user_id)
	{
		$user_id = intval($user_id);
		$query = new IQuery("user AS u");
		$query->join = "left join member AS m ON u.id = m.user_id";
		$query->where = "u.id = {$user_id} ";

		$user = $query->find();
		if(!$user)
		{
			throw new IException("用户信息不存在");
		}
		else
		{
			$this->user = current($user);
		}
		return $this;
	}

	/**
	 * 设置管理员信息
	 *
	 * @param int $admin_id
	 * @return Object
	 */
	private function setAdmin($admin_id)
	{
		$admin_id = intval($admin_id);
		$tb_admin = new IModel("admin");
		$admin = $tb_admin->getObj(" id = {$admin_id} ");
		if(!$admin)
		{
			throw new IException("管理员信息不存在");
		}
		else
		{
			$this->admin = $admin;
		}
		return $this;
	}

	/**
	 * 设置商户信息
	 *
	 * @param int $admin_id
	 * @return Object
	 */
	private function setSeller($seller_id)
	{
		$admin_id  = intval($seller_id);
		$sellerDB  = new IModel("seller");
		$sellerRow = $sellerDB->getObj(" id = {$seller_id} ");
		if(!$sellerRow)
		{
			throw new IException("商家信息不存在");
		}
		else
		{
			$this->seller = $sellerRow;
		}
		return $this;
	}

	/**
	 * 设置操作类别
	 *
	 * @param string $event_key
	 * @return Object
	 */
	private function setEvent($event_key)
	{
		if(!isset($this->allow_event[$event_key]))
		{
			throw new IException("事件未定义");
		}
		else
		{
			$this->event = $event_key;
		}
		return $this;
	}

	/**
	 * 生成note信息
	 */
	private function note()
	{
		$note = "";
		switch($this->event)
		{
			//提现
			case 'withdraw':
			{
				if($this->admin == null)
				{
					throw new IException("管理员信息不存在，无法提现");
				}
				$note .= "管理员[{$this->admin['id']}]给用户[{$this->user['id']}]{$this->user['username']}提现，金额：{$this->amount}元";
			}
			break;

			//支付
			case 'pay':
			{
				$note .= "用户[{$this->user['id']}]{$this->user['username']}使用余额支付购买，订单[{$this->config['order_no']}]，金额：{$this->amount}元";
			}
			break;

			//充值
			case 'recharge':
			{
				if($this->admin)
				{
					$note .= "管理员[{$this->admin['id']}]给";
				}
				$note .= "用户[{$this->user['id']}]{$this->user['username']}充值，金额：{$this->amount}元";
			}
			break;

			//退款
			case 'drawback':
			{
				if(!isset($this->config['order_no']))
				{
					throw new IException("退款操作未设置订单号");
				}

				if($this->seller)
				{
					$note .= "商户[{$this->seller['seller_name']}]操作";
				}

				if($this->admin)
				{
					$note .= "管理员[{$this->admin['admin_name']}]操作";
				}
				$note .= "订单[{$this->config['order_no']}]退款到用户[{$this->user['id']}]{$this->user['username']}余额，金额：{$this->amount}元";
			}
			break;

			//申请提现
			case 'apply_withdraw':
			{
				$note .= "用户[{$this->user['id']}]{$this->user['username']}申请余额提现，冻结金额：{$this->amount}元";
			}
			break;

			//取消提现
			case 'cancel_apply_withdraw':
			{
				$note .= "用户[{$this->user['id']}]{$this->user['username']}取消余额提现，解冻金额：{$this->amount}元";
			}
			break;

			//拒绝提现
			case 'deny_apply_withdraw':
			{
				if($this->admin)
				{
					$note .= "管理员[{$this->admin['id']}]拒绝";
				}
				$note .= "用户[{$this->user['id']}]{$this->user['username']}的余额提现申请，返还金额：{$this->amount}元";
			}
			break;

			default:
			{
				throw new IException("未定义事件类型");
			}
		}
		return $note;
	}

	/**
	 * @brief 商户结算单模板
	 * @param array $countData 替换的数据
	 */
	public static function sellerBillTemplate($countData = null)
	{
		$replaceData = array(
			'{startTime}'        => $countData['start_time'],
			'{endTime}'          => $countData['end_time'],
			'{orderAmountPrice}' => $countData['orderAmountPrice'],
			'{refundFee}'        => $countData['refundFee'],
			'{countFee}'         => $countData['countFee'],
			'{orgCountFee}'      => $countData['orgCountFee'],
			'{orderNum}'         => $countData['orderNum'],
			'{platformFee}'      => $countData['platformFee'],
			'{orderNoList}'      => join(",",$countData['orderNoList']),
			'{commissionPer}'    => $countData['commissionPer'],
			'{commission}'       => $countData['commission'],
		);

		$templateString = "结算起止时间：【{startTime}】到【{endTime}】，订单号：【{orderNoList}】，订单数量共计：【{orderNum}单】，商家实际结算金额：【￥{countFee}】，结算金额计算明细：【订单总金额：￥{orderAmountPrice}】-【退款总金额：￥{refundFee}】+【平台促销活动金额：￥{platformFee}】-【结算手续费：￥{commission}】";
		return strtr($templateString,$replaceData);
	}
	
	//[账户余额] 提现状态判定
    public static function getWithdrawStatus($status)
    {
    	$data = array(
    		'0'  => '未处理',
    		'-1' => '提现失败',
    		'1'  => '处理中',
    		'2'  => '提现成功',
    	);
    	return isset($data[$status]) ? $data[$status] : '未知状态';
    }

	/** 
	 * dyg_jzw 20150813
	 * @brief 预存款冻结日志操作
	 * type 0:向上返佣  1:高返自身返利
	 */
	//增加冻结日志
	public function freeze_add($data, $type = 0)
	{
		$tb_account_freeze_log = new IModel("account_freeze_log");

		$arr = array(
				'user_id' => $data['user_id'], //用户id
				'to_user_id' => $data['to_user_id'], //返还至用户id
				'type' => $type,
				'order_id' => $data['order_id'], //订单id
				'status' => 0, //初始状态为未返还
				'time' => date('Y-m-d H:i:s'),
				'amount' => $data['num'], //返还金额
				'note' => $data['note']
			);

		$tb_account_freeze_log->setData($arr);
		return $tb_account_freeze_log->add();
	}

	//冻结余额解冻
	public function freeze_done($log_id)
	{
		$tb_account_freeze_log = new IModel("account_freeze_log");

		$tb_account_freeze_log->setData(array(
			'status' => 1,
			'finish_time' => date('Y-m-d H:i:s')
		));
		return $tb_account_freeze_log->update('id='.$log_id);
	}

	//冻结余额取消返回
	public function freeze_cancel($log_id)
	{
		$tb_account_freeze_log = new IModel("account_freeze_log");

		$tb_account_freeze_log->setData(array(
			'status' => 2,
			'amount' => 0,
			'finish_time' => date('Y-m-d H:i:s')
		));
		return $tb_account_freeze_log->update('id='.$log_id);
	}

	public function freeze_part_cancel($log_id, $reduce_num)
	{
		$tb_account_freeze_log = new IModel("account_freeze_log");

		$tb_account_freeze_log->setData(array(
			'amount' => 'amount - ' . floatval($reduce_num),
		));
		return $tb_account_freeze_log->update('id='.$log_id, 'amount');
	}

}
