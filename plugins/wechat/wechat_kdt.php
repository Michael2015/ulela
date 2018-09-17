<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file wechat.php
 * @brief 微信API接口
 * @date 2015/3/27 21:59:41
 * @version 3.1
 */

/*
	与口袋通对接
	by dyg_jzw 20150710
*/

class wechat
{
	//配置数组
	public $config = array(
		'wechat_Token'     => '',
		'wechat_AppID'     => '',
		'wechat_AppSecret' => '',
		'wechat_AutoLogin' => '',
	);

	//构造函数
	public function __construct()
	{
		//获取参数配置
		$siteConfigObj = new Config("site_config");
		$this->config['wechat_Token']     = $siteConfigObj->wechat_Token;
		$this->config['wechat_AppID']     = $siteConfigObj->wechat_AppID;
		$this->config['wechat_AppSecret'] = $siteConfigObj->wechat_AppSecret;
		$this->config['wechat_AutoLogin'] = $siteConfigObj->wechat_AutoLogin;
	}


    //处理微信服务器的请求接口
    public function response()
    {
    	//口袋通回调处理
    	$user_openid = IReq::get('openid');
    	$user_nickname = IReq::get('nickname');
    	$sex = IReq::get('sex');
    	$state = IReq::get('state');
    	$sign = IReq::get('sign');
		$time = IReq::get('time');

    	
    	if ($user_openid && $user_nickname && $sign && abs(time() - $time) < 60*15  ) 
    	{
    		//自定义签名算法
    		if ($sign == md5($user_openid . $user_nickname . $time . '1sd%^&)$@SDGbnvg43sd5f3#%RDgsdf&^sdgfas')) 
    		{
    			//保存openid为其他wechat应用使用
				$this->setOpenId($user_openid);
				ISafe::set('wechat_nickname',$user_nickname);

    			$userData = array(
	    					'openid' => $user_openid,
	    					'nickname' => $user_nickname,
	    					'sex' => $sex
	    				);

    			//自动绑定和登录
    			$this->bindUser($userData);
    			$this->login($userData);				

				if (empty($state)) 
				{
					$state = '/';
				}

				header('location: '.urldecode($state));
    		}
    		else
    		{
    			die('验证失败！');
    		}
    	}
    }



	/**
	 * @brief 绑定微信账号到用户系统
	 * @param array $userData array(headimgurl,sex,nickname,openid)
	 */
	public function bindUser($userData)
	{
		if(!isset($userData['openid']))
		{
			throw new IException("未获取到用户的OPENID数据");
		}

		$oauthUserDB = new IModel('oauth_user as ou,user as u');
		$oauthRow = $oauthUserDB->getObj("ou.oauth_user_id = '".$userData['openid']."' and ou.oauth_id = 5 and ou.user_id = u.id");

		//用户未绑定
		if(!$oauthRow)
		{
			//是否已登录
			if ($user_id = IWeb::$app->getController()->user['user_id']) 
			{
				//已登录 插入oauth_user关系表
                $oauthUserData = array(
                    'oauth_user_id' => $userData['openid'],
                    'oauth_id'      => 5,
                    'user_id'       => $user_id,
                    'datetime'      => ITime::getDateTime(),
                );
                $oauthUserDB->setData($oauthUserData);
                $oauthUserDB->add();
			}
			else //未登录，要求用户登录绑定 
			{
				header('location: /simple/login?wx_nickname='.urlencode(ISafe::get('wechat_nickname')));
				exit();
			}
		}
	}

	//登录或绑定
	public function login($userData)
	{
		$oauthUserDB = new IModel('oauth_user');
		$oauthRow = $oauthUserDB->getObj("oauth_user_id = '".$userData['openid']."' and oauth_id = 5");
		$userRow  = array();

		//用户已绑定
		if($oauthRow)
		{
			$userDB = new IModel('user');
			$userRow = $userDB->getObj('id = '.$oauthRow['user_id']);

			if(!$userRow)
			{
				$oauthUserDB->del("oauth_user_id = '".$userData['openid']."' and oauth_id = 5");
				die('无法获取微信用户与商城的绑定信息');
			}

			$user = CheckRights::isValidUser($userRow['username'],$userRow['password']);
			if($user)
			{
				CheckRights::loginAfter($user);
			}
			else
			{
				die('<h1>该用户'.$userRow['username'].'被移至回收站内无法进行登录</h1>');
			}
		}	
	}

	//获取openid
	public function getOpenId()
	{
		return ISafe::get('wechat_openid');
	}

	//设置openid
	public function setOpenId($openid)
	{
		ISafe::set('wechat_openid',$openid);
	}

	/**
	 * @breif oauth路径处理
	 * @param string $url 网址路径
	 * @return string 处理后oauth的URL
	 */
	public function oauthUrl($url)
	{
		$url = urlencode($url);
		$apiUrl = "http://kenjey01.sinaapp.com/mobile/dygshop/oauth2?state=".$url."#wechat_redirect";
		return $apiUrl;
	}

}