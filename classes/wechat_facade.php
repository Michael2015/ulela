<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file wechat_facade.php
 * @brief 微信封装类库
 * @author nswe
 * @date 2015/3/29 15:15:14
 * @version 3.1
 */
class wechat_facade
{
	//实例
	public static $instance = null;

	/**
	 * @brief 创建分词类库实例
	 */
	private static function createInstance()
	{
		if(self::$instance == null)
		{
			//dyg_jzw 修改wechat类 使其可对接口袋通
			$classFile = IWeb::$app->getBasePath().'plugins/wechat_dyg/wechat_kdt.php';
			if(is_file($classFile))
			{
				include_once($classFile);
				self::$instance = new wechat();
			}
			else
			{
				die('您的版本不支持微商城');
			}
		}
		return self::$instance;
	}

	//微信推送的相应
	public static function response()
	{
		$instance = self::createInstance();
		return $instance->response();
	}

	//微信获取菜单
	public static function getMenu()
	{
		$instance = self::createInstance();
		return $instance->getMenu();
	}

	/**
	 * @brief 微信设置菜单
	 * @param json $menuData
	 */
	public static function setMenu($menuData)
	{
		$instance = self::createInstance();
		return $instance->setMenu($menuData);
	}

	/**
	 * @brief 获取openid数据
	 */
	public static function getOpenId()
	{
		return self::createInstance()->getOpenId();
	}

	/**
	 * @brief 进行oauth登录
	 */
	public static function oauthLogin()
	{
		$wechatObj = self::createInstance();
		$openid    = $wechatObj->getOpenId();
		$localUrl  = IUrl::getUrl();
		$url       = $wechatObj->oauthUrl($localUrl);

		if($openid == '')
		{
			header('location: '.$url);
		}
		else if($wechatObj->config['wechat_AutoLogin'] == 1 && CheckRights::getUser() == null)
		{
			$wechatObj->bindUser(array("openid" => $openid));
			$wechatObj->login(array("openid" => $openid));
			header('location: '.$url);
		}
	}
}