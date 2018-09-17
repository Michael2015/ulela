<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file smstemplate.php
 * @brief 短信发送模板
 * @author chendeshan
 * @date 2014/8/11 9:51:51
 * @version 2.9
 */

 /**
 * @class smsTemplate
 * @brief 短信发送模板
 */
class smsTemplate
{
	//短信模板信息不存在
	public static function __callStatic($funcname, $arguments)
	{
		return "";
	}
	/**
	 * @brief 订单发送海关成功的信息模板
	 * @param array $data 替换的数据
	 */
	public static function sendHaiGuan($data = null)
	{
		$templateString = "您好{user_name}，您的订单（{order_no}）已成功发往保税区处理，请您再耐心等待。感谢您对阳光全球购的支持！";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 订单发货的信息模板
	 * @param array $data 替换的数据
	 */
	public static function sendGoods($data = null)
	{
		$templateString = "您好{user_name}，您的订单（{order_no}）已由{sendor}发货，物流单号:{delivery_no}（{delivery_company}）";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 手机找回密码模板
	 * @param array $data 替换的数据
	 */
	public static function findPassword($data = null)
	{
		$templateString = "正在找回密码，您的验证码是{mobile_code}（30分钟内有效），如非本人操作，请忽略本短信";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 手机短信校验码
	 * @param array $data 替换的数据
	 */
	public static function checkCode($data = null)
	{
		$templateString = "您的验证码为:{mobile_code}，如非本人操作，请忽略本短信";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 自提点确认短信
	 * @param array $data 替换的数据
	 */
	public static function takeself($data = null)
	{
		$templateString = "您的订单（{orderNo}）已付款成功，{name}自提地址:{address}，领取验证码:{mobile_code}，请等待发货后前往领取（本信息转发无效）";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 商户注册提示管理员
	 * @param array $data 替换的数据
	 */
	public static function sellerReg($data = null)
	{
		$templateString = "{true_name}，申请加盟到平台，请尽快登录后台进行处理";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 商户处理结果
	 * @param array $data 替换的数据
	 */
	public static function sellerCheck($data = null)
	{
		$templateString = "您的加盟商状态已经被修改为:{result}状态，请登录您的商户后台查看具体的详情";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 订单付款通知管理员
	 */
	public static function payFinishToAdmin($data = null)
	{
		$templateString = "订单:{orderNo}，已经付款，请尽快登录后台进行处理";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 订单付款通知管理员
	 */
	public static function payFinishToUser($data = null)
	{
		$templateString = "您的订单号:{orderNo}，已付款成功，稍后我们会尽快为您服务！";
		return strtr($templateString,$data);
	}
	/**
	 * @brief 到货通知模板
	 * @param array $data 替换的数据
	 */
	public static function notify($data = null)
	{
		$templateString = "尊敬的用户，您关注的 <{goodsName}> 现已到货！请登录查看{url}";
		return strtr($templateString,$data);
	}

	//dyg_jzw 20161109 随单送赋活单片活动
	public static function award_hit_201611($data = null)
	{
		$templateString = "恭喜！您的订单（尾号{orderNo}）在本次随单送活动获得 {hit_times}{unit}{goods_name}{others}";
		return strtr($templateString,$data);
	}

	public static function award_unhit_201611($data = null)
	{
		$templateString = "很抱歉，您的订单（尾号{orderNo}）未能在本次随单送活动命中任何奖品。不要灰心再接再厉，每单都有机会哦！感谢您的支持！";
		return strtr($templateString,$data);
	}
	
	/**
	 * @brief 订单已被自提信息模板
	 * @param array $data 替换的数据
	 */
	public static function takeselfGoods($data = null)
	{
		$templateString = "您好{user_name}，订单[{order_no}]在{takeself}已经成功提货";
		return strtr($templateString,$data);
	}
}