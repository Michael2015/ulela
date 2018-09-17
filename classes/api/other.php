<?php
/**
 * @copyright (c) 2014 aircheng.com
 * @file other.php
 * @brief 其他api方法
 * @author chendeshan
 * @date 2016/4/11 12:54:16
 * @version 4.4
 */
class APIOther
{
	//获取促销规则
	public function getProrule($seller_id = 0)
	{
		$proRuleObj = new ProRule(999999999,$seller_id);
		$proRuleObj->isGiftOnce = false;
		$proRuleObj->isCashOnce = false;
		return $proRuleObj->getInfo();
	}

	//获取支付方式
	public function getPaymentList($is_cbe = false, $is_point = false) //dyg_jzw 20160303 是否跨境电商付款 //dyg_jzw 20170220 是否支持积分支付
	{
		$user_id = IWeb::$app->getController()->user['user_id'];
		$where = 'status = 0';

		if(!$user_id)
		{
			$where .= " and class_name != 'balance'";
		}

		//dyg_jzw 20160307 是否跨境订单
		if ($is_cbe)
		{
			$where .= " and is_cbe = 1";
		}
		else
		{
			switch(IClient::getDevice())
			{
				//移动支付
				case IClient::MOBILE:
				{

					//如果是微信客户端,必须用微信专用支付
					if(IClient::isWechat() == true)
					{
						//dyg_jzw 20160307 非跨境订单微信端
						$where .= " and is_cbe = 0 and (class_name in ( 'wap_wechat','balance' ) or ( type = 2 and client_type in(2,3) )) ";
					}
					else
					{
						//dyg_jzw 20160307 非跨境订单普通移动端
						$where .= " and is_cbe = 0 and client_type in(2,3) and class_name !=  'wap_wechat' ";
					}
				}
				break;

				//pc支付
				case IClient::PC:
				{
					//dyg_jzw 20160307 非跨境订单
					$where .= ' and is_cbe = 0 and client_type in(1,3) ';
				}
				break;
			}

			//dyg_jzw 20170220 增加积分支付方式显示
			if ($is_point)
			{
				$where .= " or class_name = 'pointpay'";
			}
			else
			{
				$where .= " and class_name != 'pointpay'";
			}
		}

		
		$paymentDB = new IModel('payment');
		return $paymentDB->query($where,"*","`order` asc");
	}

	//线上充值的支付方式
	public function getPaymentListByOnline()
	{
		$where = " type = 1 and status = 0 and class_name not in ('balance','offline') ";
		switch(IClient::getDevice())
		{
			//移动支付
			case IClient::MOBILE:
			{

				//如果是微信客户端,必须用微信专用支付
				if(IClient::isWechat() == true)
				{
					$where .= " and (class_name in ( 'wap_wechat','balance' ) or ( type = 2 and client_type in(2,3) )) ";
				}
				else
				{
					$where .= " and client_type in(2,3) and class_name !=  'wap_wechat' ";
				}
			}
			break;

			//pc支付
			case IClient::PC:
			{
				$where .= ' and client_type in(1,3) ';
			}
			break;
		}

		$paymentDB = new IModel('payment');
		return $paymentDB->query($where,"*","`order` asc");
	}

	//获取banner数据
	public function getBannerList($type='pc')
	{
		$siteConfigObj = new Config("site_config");
		$site_config   = $siteConfigObj->getInfo();

		//读取本地serialize信息
		if($type == 'pc' && isset($site_config['index_slide']) && $site_config['index_slide']) 
		{
			return unserialize($site_config['index_slide']);
		}
		if($type == 'mobile' && isset($site_config['index_slide_mobile']) && $site_config['index_slide_mobile']) //dyg_jzw 20161212加入手机版幻灯片
		{
			return unserialize($site_config['index_slide_mobile']);
		}
		$cacheObj = new ICache('memcache'); //dyg_jzw 20161128 默认memcache缓存
		$defaultBanner = $cacheObj->get('defaultBanner');
		if(!$defaultBanner)
		{
		$defaultBanner = file_get_contents("http://product.aircheng.com/proxy/defaultBanner");
			$cacheObj->set('defaultBanner',$defaultBanner);
		}
		return JSON::decode($defaultBanner);
	}
	//获取默认广告位数据
	public function getAdRow($adName)
	{
		$isCache   = true;
		$cacheObj  = new ICache('memcache'); //dyg_jzw 20161128 默认memcache缓存
		$defaultAd = $cacheObj->get('ad'.$adName);
		if(!$defaultAd || $isCache == false)
		{
			$ch = curl_init("http://product.aircheng.com/proxy/getAdRow");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "name=".$adName);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$defaultAd = curl_exec($ch);
			$cacheObj->set('ad'.$adName,$defaultAd);
		}
		return $defaultAd;
	}
}