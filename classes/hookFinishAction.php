<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file hookFinishAction.php
 * @brief 对action动作进行拦截，对部分需要钩子的action设置代码调用
 *        钩子名称为： function 控制器ID_动作ID,遇到此动作时优先调用钩子方法
 * @author nswe
 * @date 2015/5/26 23:01:46
 * @version 3.2
 */
class hookFinishAction extends IInterceptorBase
{
	//根据控制器ID和动作ID生成钩子方法名
	public static function getHookRule()
	{
		$ctrlId  = IWeb::$app->getController()->getId();
		$actionId= IWeb::$app->getController()->getAction()->getId();
		return join('_',array($ctrlId,$actionId));
	}

	//createAction拦截器统一入口
	public static function onFinishAction()
	{
		$hookName = self::getHookRule();
		if(method_exists(__CLASS__,$hookName))
		{
			call_user_func(array(__CLASS__,$hookName));
		}
	}
	//获取最新版本信息
	public static function system_default()
	{
		$param = array("host" => $_SERVER['HTTP_HOST']);
		$code  = isset(IWeb::$app->config['authorizeCode']) ? IWeb::$app->config['authorizeCode'] : "";
		if($code)
		{
			$param['code'] = $code;
		}
		$url = "http://product.aircheng.com/proxy/common?".http_build_query($param);
		echo '<script type="text/javascript" src="'.$url.'"></script>';
	}
}