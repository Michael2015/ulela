<?php
/**
 * @copyright Copyright(c) 2016 aircheng.com
 * @file _initData.php
 * @brief 初始化数据
 * @author nswe
 * @date 2016/6/8 10:08:23
 * @version 4.5
 */
class _initData extends pluginBase
{
	public function reg()
	{
		plugin::reg("onCreateController",$this,"webSiteConfig");
		plugin::reg("onCreateView",$this,"themeConfig");
		plugin::reg("onFinishView",$this,"jsGlobal");
	}

	//初始化网站配置数据
	public function webSiteConfig()
	{
		$configObj = new Config("site_config");
		self::controller()->_siteConfig = $configObj;
	}

	//初始化主题模板数据
	public function themeConfig()
	{

	}

	//初始化js全局变量
	public function jsGlobal()
	{
		//全局JS提示信息
		$_msg = IReq::get('_msg') ? IFilter::act(IReq::get('_msg')) : "";
		if($_msg)
		{
			$msgArray = array(
				"success" => "操作成功",
				"fail"    => "操作失败",
			);
			if(isset($msgArray[$_msg]))
			{
echo <<< EOF
<script type="text/javascript">
alert("{$msgArray[$_msg]}");
</script>
EOF;
			}
		}

		//全局JS函数和变量
		$url       = IUrl::creatUrl('_controller_/_action_/_paramKey_/_paramVal_');
		$themePath = IWeb::$app->getController()->getWebViewPath();
		$skinPath  = IWeb::$app->getController()->getWebSkinPath();

echo <<< EOF
<script type="text/javascript">
_webUrl = "$url";_themePath = "$themePath";_skinPath = "$skinPath";
//创建URL地址
function creatUrl(param)
{
	var urlArray   = [];
	var _tempArray = param.split("/");
	for(var index in _tempArray)
	{
		if(_tempArray[index])
		{
			urlArray.push(_tempArray[index]);
		}
	}

	if(urlArray.length >= 2)
	{
		var iwebshopUrl = _webUrl.replace("_controller_",urlArray[0]).replace("_action_",urlArray[1]);

		//存在URL参数情况
		if(urlArray.length >= 4)
		{
			iwebshopUrl = iwebshopUrl.replace("_paramKey_",urlArray[2]);

			//卸载原数组中已经拼接的数据
			urlArray.splice(0,3);

			if(iwebshopUrl.indexOf("?") == -1)
			{
				iwebshopUrl = iwebshopUrl.replace("_paramVal_",urlArray.join("/"));
			}
			else
			{
				var _paramVal_ = "";
				for(var i in urlArray)
				{
					if(i == 0)
					{
						_paramVal_ += urlArray[i];
					}
					else if(i%2 == 0)
					{
						_paramVal_ += "="+urlArray[i];
					}
					else
					{
						_paramVal_ += "&"+urlArray[i];
					}
				}
				iwebshopUrl = iwebshopUrl.replace("_paramVal_",_paramVal_);
			}
		}
		return iwebshopUrl;
	}
	return '';
}

//切换验证码
function changeCaptcha()
{
	$('#captchaImg').prop('src',creatUrl("site/getCaptcha/random/"+Math.random()));
}
</script>
EOF;
	}
}