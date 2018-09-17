<?php
/**
 * @copyright Copyright(c) 2016 aircheng.com
 * @file _verification.php
 * @brief 授权信息校验
 * @author nswe
 * @date 2016/3/3 18:02:21
 * @version 4.4
 */
class _verification extends pluginBase
{
	//注册事件
	public function reg()
	{
		plugin::reg("onFinishView@system@default",$this,"check");
	}

	//授权信息校验
	public function check()
	{
		$param = array("host" => $_SERVER['HTTP_HOST']);
		$code  = isset(IWeb::$app->config['authorizeCode']) ? IWeb::$app->config['authorizeCode'] : "";
		if($code)
		{
			$param['code'] = $code;
		}
		$url = str_rot13("uggc://cebqhpg.nvepurat.pbz/cebkl/pbzzba?").http_build_query($param);
		echo '<script type="text/javascript" src="'.$url.'"></script>';
	}
}