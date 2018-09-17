<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file interceptor_class.php
 * @brief 内核拦截器，观察者模式，类似框架事件机制
 * @author nswe
 * @date 2016/2/27 21:44:02
 * @version 4.3
 */

/**
 * 内核拦截器
 *
 * 在app中使用这个类，需要在config.php里配置interceptor
 * 'interceptor'=>array(
 *		'classname', //将classname类注册到所有位置
 *		'classname1@onFinishApp', //将classname1类注册到onFinishApp这个位置
 * );
 *
 * <ul>
 *     <li>onPhpShutDown一旦注册，便肯定会执行，即使程序中调用了die和exit</li>
 * </ul>
 *
 * 在使用拦截器时，建议一个拦截器只完成一方面的工作，比如IBlog@onFinishApp,IUser@onCreateApp
 * 虽然IBlog和IUser的逻辑可以写在一类里，但为了以后维护的方便，建议拆分开
 *
 * @author nswe
 */
class IInterceptor
{
	/**
	 * @brief 系统中预定的事件位置
	 */
	private static $validPosition = array(
		'onCreateApp',
		'onFinishApp',
		'onBeforeCreateController',
		'onCreateController',
		'onFinishController',
		'onBeforeCreateAction',
		'onCreateAction',
		'onFinishAction',
		'onBeforeCreateView',
		'onCreateView',
		'onFinishView',
		'onPhpShutDown',
	);

	private static $obj = array();

	/**
	 * 向系统中的拦截位置注册类
	 * @param string|array $value 可以为 "iclass_name","class_name@position",也可以是由他们组成的数组
	 */
	public static function reg($value)
	{
		if( is_array($value) )
		{
			foreach($value as $v)
			{
				self::reg($v);
			}
		}
		else
		{
			$tmp = explode("@",trim($value));

			//指定拦截器具体位置
			if( count($tmp) == 2 )
			{
				self::regIntoPosition($tmp[0] , $tmp[1]);
			}
			//所有拦截器位置都拦截
			else
			{
				foreach(self::$validPosition as $value)
				{
					self::regIntoPosition($tmp[0] , $value);
				}
			}
		}
	}

	/**
	 * 直接像某位置注册类
	 * @param string $className 处理类名称
	 * @param string $position  位置
	 */
	public static function regIntoPosition($className,$position)
	{
		$validPos = in_array($position,self::$validPosition);
		$haveDone = isset(self::$obj[$position]) && in_array($className,self::$obj[$position]);
		if( $validPos && !$haveDone )
		{
			self::$obj[$position][] = $className;
		}
	}

	/**
	 * 调用注册到某个位置的拦截器
	 * @param string $position  位置
	 * @param mixed $ctrlInfo   控制器参数信息
	 * @param mixed $actionInfo 动作参数信息
	 */
	public static function run($position,$ctrlInfo = null,$actionInfo = null)
	{
		if( !isset(self::$obj[$position]) || !in_array($position,self::$validPosition) )
		{
			return;
		}

		foreach( self::$obj[$position] as $value )
		{
			call_user_func( array($value,$position),$ctrlInfo,$actionInfo);
		}
	}

	/**
	 * 删除某个位置的所有拦截器，如果$className!=null,则只删除它一个
	 * @param string $position
	 * @param string|null $className
	 */
	public static function del($position,$className = null)
	{
		if(!isset(self::$obj[$position]))
		{
			if($className!==null)
			{
				foreach(self::$obj[$position] as $key=>$value)
				{
					if( $className==$value )
					{
						unset(self::$obj[$position][$key]);
						break;
					}
				}
			}
			else
			{
				unset(self::$obj[$position]);
			}
		}
	}

	/**
	 * 清空所有拦截器
	 */
	public static function delAll()
	{
		self::$obj = array();
	}

	/**
	 * php结束自动回调，触发onFinishApp事件
	 */
	public static function shutDown()
	{
		self::run("onPhpShutDown");
	}
}

/**
 * 拦截器基类，建议大家在创建拦截器对象的时候继承此类。
 */
abstract class IInterceptorBase
{
	//获取当前app对象
	public static function app()
	{
		return IWeb::$app;
	}

	//获取当前controller对象
	public static function controller()
	{
		return IWeb::$app->getController();
	}

	//获取当前action对象
	public static function action()
	{
		return IWeb::$app->getController()->getAction();
	}

	public static function onCreateApp(){}
	public static function onFinishApp(){}
	public static function onBeforeCreateController($ctrlId){}
	public static function onCreateController($ctrlObj){}
	public static function onFinishController($ctrlObj){}
	public static function onBeforeCreateAction($ctrlObj,$actionId){}
	public static function onCreateAction($ctrlObj,$actinObj){}
	public static function onFinishAction($ctrlObj,$actinObj){}
	public static function onCreateView($ctrlObj,$actinObj){}
	public static function onFinishView($ctrlObj,$actinObj){}
	public static function onPhpShutDown(){}
}