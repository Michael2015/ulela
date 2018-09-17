<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file wechatOauth.php
 * @brief wechat 的oauth协议登录接口
 * @author nswe
 * @date 2016/4/27 7:41:04
 * @version 4.3
 */

/**
 * @class wechatOauth
 * @brief wechat的oauth协议接口
 */
class wechatOauth extends OauthBase
{
	private $AppID     = '';
	private $AppSecret = '';

	public function __construct($config)
	{
		$this->AppID     = isset($config['AppID'])     ? $config['AppID']     : "";
		$this->AppSecret = isset($config['AppSecret']) ? $config['AppSecret'] : "";
	}

	public function getFields()
	{
		return array(
			'AppID' => array(
				'label' => 'AppID',
				'type'  => 'string',
			),
			'AppSecret'=>array(
				'label' => 'AppSecret',
				'type'  => 'string',
			),
		);
	}

	//获取登录url地址
	public function getLoginUrl()
	{
		$urlparam = array(
			"appid=".$this->AppID,
			"redirect_uri=".urlencode(parent::getReturnUrl()),
			"response_type=code",
			"scope=snsapi_login",
			"state=".rand(100,999),
		);
		$url = "https://open.weixin.qq.com/connect/qrconnect?".join("&",$urlparam)."#wechat_redirect";
		return $url;
	}

	//获取进入令牌
	public function getAccessToken($parms)
	{
		$urlparam = array(
			"appid=".$this->AppID,
			"secret=".$this->AppSecret,
			"code=".$parms['code'],
			"grant_type=authorization_code",
		);
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?".join("&",$urlparam);

		//模拟post提交
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$result = curl_exec($ch);
		$tokenInfo = JSON::decode($result);
		if(!$tokenInfo || !isset($tokenInfo['access_token']))
		{
			die(var_export($result));
		}

		//获取用户信息
		$unid = $tokenInfo['openid'];
		//$unid = isset($tokenInfo['unionid']) ? $tokenInfo['unionid'] : $tokenInfo['openid'];
		$name = substr($unid,-8);

		//获取微信用户信息
		$wechatUser = $this->apiUserInfo($tokenInfo);
		if($wechatUser && isset($wechatUser['nickname']))
		{
			$wechatName = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u',"",$wechatUser['nickname']));
			$name = $wechatName ? $wechatName : $name;
		}
		ISession::set('wechat_user_nick',$name);
		ISession::set('wechat_user_id',$unid);
	}

	//获取用户数据
	public function getUserInfo()
	{
		$userInfo = array();
		$userInfo['id']   = ISession::get('wechat_user_id');
		$userInfo['name'] = ISession::get('wechat_user_nick');
		$userInfo['sex']  = 1;
		return $userInfo;
	}

	public function checkStatus($parms)
	{
		if(isset($parms['error']) || !isset($parms['code']))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * @brief 获取用户的基本信息
	 * @param array $userData
	 * {
			"access_token": "****",
			"expires_in": 7200,
			"refresh_token": "****",
			"openid": "****",
			"scope": "snsapi_userinfo"
	 * }
	 * @return array
	 */
	public function apiUserInfo($oauthAccess)
	{
		$openid      = $oauthAccess['openid'];
		$accessToken = $oauthAccess['access_token'];
		$scope       = $oauthAccess['scope'];
		$urlparam    = array(
			'access_token='.$accessToken,
			'openid='.$openid,
		);
		//根据不同的授权类型运行不同的接口
		$apiUrl = "https://api.weixin.qq.com/sns/userinfo?";
		$apiUrl .= join("&",$urlparam);
		$json    = file_get_contents($apiUrl);
		if(stripos($json,"errcode") !== false)
		{
			return null;
		}
		return JSON::decode($json);
	}
}