<?php
/**
 * @copyright (c) 2016 aircheng.com
 * @file dbfactory.php
 * @brief 数据库工厂类
 * @author chendeshan
 * @date 2016/3/31 21:00:11
 * @version 4.4
 */

/**
* @class IDBFactory
* @brief 数据库工厂
*/
class IDBFactory
{
	//数据库对象
	public static $instance   = NULL;

	//默认的数据库连接方式
	private static $defaultDB = 'mysqli';

	/**
	 * @brief 创建对象
	 * @return object 数据库对象
	 */
	public static function getDB()
	{
		//单例模式
		if(self::$instance != NULL && is_object(self::$instance))
		{
			self::$instance->init();
			return self::$instance;
		}

		//获取数据库配置信息
		if(!isset(IWeb::$app->config['DB']) || IWeb::$app->config['DB'] == null)
		{
			throw new IException('can not find DB info in config.php');
		}
		$dbinfo = IWeb::$app->config['DB'];

		//数据库类型
		$dbType = isset($dbinfo['type']) ? $dbinfo['type'] : self::$defaultDB;

		switch($dbType)
		{
			case "mysqli":
			{
				return self::$instance = new IMysqli();
			}
			break;

			case "mysql":
			{
				return self::$instance = new IMysql();
			}
			break;
		}
	}
    private function __construct(){}
    private function __clone(){}
}