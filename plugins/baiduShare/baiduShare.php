<?php
/**
 * @copyright (c) 2016 aircheng.com
 * @file baiduShare.php
 * @brief 百度分享插件
 * @author nswe
 * @date 2016/6/26 18:08:50
 * @version 4.5
 */
class baiduShare extends pluginBase
{
	public static function name()
	{
		return "商品分享插件";
	}

	public static function description()
	{
		return "在商品详情页面中把商品信息分享到各大主流网站，分享功能必须要把网站部署到互联网上才可以正常使用";
	}

	public function reg()
	{
		plugin::reg("onFinishView@site@products",function(){$this->show();});
	}

	public function show()
	{
echo <<< OEF
<script type="text/javascript" id="bdshare_js" data="type=slide&amp;img=0&amp;pos=right&amp;uid=0" ></script>
<script type="text/javascript" id="bdshell_js"></script>
<script type="text/javascript">
document.getElementById("bdshell_js").src = "http://bdimg.share.baidu.com/static/js/shell_v2.js?cdnversion=" + Math.ceil(new Date()/3600000);
</script>
OEF;
	}
}