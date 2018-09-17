<?php
/**
 * @copyright Copyright(c) 2016 aircheng.com
 * @file _goodsCategoryWidget.php
 * @brief 商品分类视图插件
 * @author nswe
 * @version 4.4
 * @date 2016/3/26 9:47:48
 */
class _goodsCategoryWidget extends pluginBase
{
	public function reg()
	{
		plugin::reg("goodsCategoryWidget",$this,"showWidget");
		plugin::reg("onBeforeCreateAction@block@goods_category",function(){
			self::controller()->goods_category = function(){$this->view("goodsCategory");};
		});
	}

	/**
	 * @brief 显示插件内容
	 * @param array $param 配置array('name' => 控件name值,'value' => 分类ID字符串)
	 */
	public function showWidget($param)
	{
		//默认商品分类数据
		if(isset($param['value']) && $param['value'])
		{
			$param['value'] = IFilter::act($param['value'],'int');
			$idString   = is_array($param['value']) ? join(",",$param['value']) : $param['value'];
			$categoryDB = new IModel('category');
			$cateData   = $categoryDB->query("id in (".$idString.")");
			if($cateData)
			{
				$param['default'] = $cateData;
			}
		}

		$this->view("goodsCategoryWidget",$param);
	}
}