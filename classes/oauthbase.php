<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file OauthBase.php
 * @brief oauth协议登录基础类
 * @author chendeshan
 * @date 2011-7-18 9:34:18
 * @version 0.6
 */

/**
 * @class Oauth
 * @brief oauth协议登录基础类
 */
abstract class OauthBase
{
	//获取回调URL地址
	protected function getReturnUrl()
	{
		$className = strtolower( get_class($this) );
		return IUrl::getHost().IUrl::creatUrl('/simple/oauth_callback/oauth_name/'.$className);
	}

	abstract public function getLoginUrl();
	abstract public function checkStatus($parms);
	abstract public function getAccessToken($parms);
	abstract public function getUserInfo();
	abstract public function getFields();
}
