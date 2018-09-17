<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file hsms.php
 * @brief 短信发送接口
 * @author nswe
 * @date 2015/5/30 16:23:21
 * @version 3.3
 */

 /**
 * @class Hsms
 * @brief 短信发送接口
 */
class Hsms
{
	private static $smsInstance = null;

	//每次用户主动（非系统）发送的短信间隔
	private static $sendStep = 50;

	/**
	 * @brief 获取config用户配置
	 * @return array
	 */
	private static function getPlatForm()
	{
		$siteConfigObj = new Config("site_config");
		return $siteConfigObj->sms_platform;
	}

	/**
	 * @brief 发送短信
	 * @param string $mobiles 多个手机号为用半角,分开，如13899999999,13688888888（最多200个）
	 * @param string $content 短信内容
	 * @param int $delay 延迟设置
	 * @return success or fail
	 */
	public static function send($mobiles, $content, $delay = 1)
	{
		if(!$content)
		{
			return "短信内容不能为空";
		}
		if( !isset($_SERVER['HTTP_USER_AGENT']) )
		{
			return "非客户端访问";
		}
		if(IClient::getIp() == '')
		{
			return "ip信息不合法";
		}
		$mobile_array = explode(",", $mobiles);
		foreach ($mobile_array as $key => $val)
		{
			if(false === IValidate::mobi($val))
			{
				unset($mobile_array[$key]);
			}
		}
		if(!$mobile_array)
		{
			return "非法手机号码";
		}
		if(count($mobile_array) > 200)
		{
			return "手机号超过200个";
		}
		//延迟机制
		if($delay == 1)
		{
			$cacheObj = new ICache();
			$smsTime = $cacheObj->get('smsDelay');
			if($smsTime && time() - $smsTime < self::$sendStep)
			{
				return "短信发送频率太快，请稍候再试...";
			}
			//更新发送时间
			$cacheObj->set('smsDelay',time());
		}
		if(self::$smsInstance == null)
		{
			$platform = self::getPlatForm();
			switch($platform)
			{
				case "zhutong":
				{
					$classFile = IWeb::$app->getBasePath().'plugins/hsms/zhutong.php';
					require($classFile);
					self::$smsInstance = new zhutong();
				}
				break;

				//dyg_jzw 20150811 增加云片短信接口
				case "yunpian":
				{
					$classFile = IWeb::$app->getBasePath().'plugins/hsms/yunpian.php';
					require($classFile);
					self::$smsInstance = new yunpian();
				}
				break;

				default:
				{
					$classFile = IWeb::$app->getBasePath().'plugins/hsms/haiyan.php';
					require($classFile);
					self::$smsInstance = new haiyan();
				}
			}
		}

		return self::$smsInstance->send($mobiles, $content);
	}
}

/**
 * @brief 短信抽象类
 */
abstract class hsmsBase
{
	//短信发送接口
	abstract public function send($mobile,$content);

	//短信发送结果接口
	abstract public function response($result);

	//短信配置参数
	abstract public function getParam();
}