<?php
/**
 * @copyright (c) 2011 [group]
 * @file exp.php
 * @brief 经验日志记录处理类
 * @author dyg_jzw
 * @date 20150820
 * @version 1.0
 */
class Exp
{
	//错误信息
	private $error  = '';

	/**
	 * @brief 经验操作的构造函数
	 * @param array $config => array('user_id' => 用户ID , 'exp' => 经验增减(正，负区分) , 'log' => 日志记录内容)
	 */
	public function update($config)
	{
		if(!isset($config['user_id']) || intval($config['user_id']) <= 0)
		{
			$this->error = '用户ID不能为空';
		}
		else if(!isset($config['exp']) || intval($config['exp']) == 0)
		{
			$this->error = '经验值格式不正确';
		}
		else if(!isset($config['log']))
		{
			$this->error = '经验值日志内容不正确';
		}
		else
		{
			$is_success = $this->editExp($config['user_id'],$config['exp']);
			if($is_success)
			{
				if(!$this->writeLog($config))
				{
					$this->error = '记录经验值失败';
				}
			}
			else
			{
				$this->error = '经验值更新失败';
			}
		}

		return $this->error == '' ? true:false;
	}

	//返回错误信息
	public function getError()
	{
		return $this->error;
	}

	/**
	 * @brief 日志记录
	 * @param array $config => array('user_id' => 用户ID , 'exp' => 经验值增减(正，负区分) , 'log' => 日志记录内容)
	 */
	private function writeLog($config)
	{
		//修改expLog表
		$expLogObj    = new IModel('exp_log');
		$expLogArray = array(
			'user_id' => $config['user_id'],
			'datetime'=> ITime::getDateTime(),
			'value'   => $config['exp'],
			'intro'   => $config['log'],
		);
		$expLogObj->setData($expLogArray);
		return $expLogObj->add();
	}

	/**
	 * @brief 积分更新
	 * @param int $user_id 用户ID
	 * @param int $exp   经验值数(正，负)
	 */
	private function editExp($user_id,$exp)
	{
		$memberObj   = new IModel('member');
		$memberArray = array('exp' => 'exp + '.$exp);
		$memberObj->setData($memberArray);
		return $memberObj->update('user_id = '.$user_id,'exp');
	}

}