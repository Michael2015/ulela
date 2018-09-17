<?php
/**
 * @copyright Copyright(c) 2014 aircheng.com
 * @file freight_facade.php
 * @author nswe
 * @date 2014/4/18 16:22:33
 * @version 1.0.0
 */

/**
 * @class freight_facade
 * @brief 快递跟踪接口类
 */
class freight_facade
{
	//使用的物流接口
	public static $freightInterface = 'hqepay';

	/**
	 * @brief 开始快递跟踪
	 * @param $ShipperCode    string 物流公司编码
	 * @param $LogisticCode   string 物流单号
	 */
	public static function line($ShipperCode,$LogisticCode)
	{
		if( $freightObj = self::createObject() )
		{
			return $freightObj->line($ShipperCode,$LogisticCode);
		}
	}

	/**
	 * @brief 创建物流接口实力
	 * @return object 快递跟踪类实例
	 */
	private static function createObject()
	{
		//类库路径
		$basePath   = IWeb::$app->getBasePath().'plugins/freight/';

		//配置参数
		$siteConfig = new Config('site_config');

		switch(self::$freightInterface)
		{
			default:
			{
				include($basePath.'hqepay.php');
				return new Hqepay($siteConfig->freightid,$siteConfig->freightkey);
			}
		}
	}
}

/**
 * @brief 物流开发接口
 */
interface freight_inter
{
	/**
	 * @brief 物流快递轨迹查询
	 * @param $ShipperCode string 物流公司快递号
	 * @param $LogisticCode string 快递单号
	 */
	public function line($ShipperCode,$LogisticCode);

	/**
	 * @brief 处理返回数据统一数据格式
	 * @param $result 结果处理
	 * @return array 通用的结果集 array('result' => 'success或者fail','data' => array( array('time' => '时间','station' => '地点'),......),'reason' => '失败原因')
	 */
	public function response($result);
}