<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file wechat.php
 * @brief 微信API接口 dyg_jzw 20160614
 * @date 2016/2/19 10:42:25
 * @version 4.4
 */
class wechat_dyg extends pluginBase
{
	public $wechat_fc = null;

	//获取配置参数
	private function initConfig()
	{
		//缺少SSL组件
		if(!extension_loaded("OpenSSL"))
		{
			$this->setError = "您的环境缺少OpenSSL组件，这是调用微信API所必须的";
			return false;
		}

		if(class_exists("wechat_facade"))
		{
			$this->wechat_fc = new wechat_facade();
			return true;
		}
		else
		{
			$this->setError("口袋通对接插件不完全, 缺失wechat_facade");
			return false;
		}
	}


	//获取openid
	public static function getOpenId()
	{
		return $this->wechat_fc->getOpenId();
	}


    //处理微信服务器的请求接口
    public function response()
    {
    	$this->wechat_fc->response();
    }

	/**
	 * @brief 获取自定义菜单
	 * @return array
	 */
	public function getMenu()
	{
		$this->wechat_fc->getMenu();
	}

	//插件注册
	public function reg()
	{
		if(IClient::isWechat() == true)
		{
			if($this->initConfig() == false)
			{
				throw new IException($this->getError());
			}
			plugin::reg("onCreateView",$this,"oauthLogin");
		}

		plugin::reg("onBeforeCreateAction@block@wechat",function(){
			if($this->initConfig() == false)
			{
				throw new IException($this->getError());
			}
			self::controller()->wechat = function(){$this->response();};
		});

		plugin::reg("onSystemMenuCreate",function(){
			$link = "/plugins/wechat_menu";
			Menu::$menu["插件"]["插件管理"][$link] = $this->name();
		});
	}

	//插件名称
	public static function name()
	{
		return "微信插件_对接口袋通专用";
	}

	//插件描述
	public static function description()
	{
		return "微信免登录，微信支付，对接口袋通专用";
	}

	//插件默认配置
	public static function configName()
	{
		return 	array(
			
		);
	}

	/**
	 * @brief 进行oauth静默登录
	 */
	public function oauthLogin()
	{
		$this->wechat_fc->oauthLogin();
	}
}