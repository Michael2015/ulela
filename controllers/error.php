<?php
/**
 * @copyright (c) 2011 jooyea.cn
 * @file error.php
 * @brief 错误处理类
 * @author chendeshan
 * @date 2010-12-16
 * @version 0.6
 */
class Error extends IController
{
	public function error404()
	{
		$heading = '文件不存在';
		$data    = '请确定要访问的页面在是否存在';
		$this->redirect('/site/error/?msg='.urlencode($data).'&heading='.urlencode($heading));
	}

	public function error403($data)
	{
		$heading = '操作发生错误';
		if(is_array($data))
		{
			$data = isset($data['message']) ? $data['message'] : '';
		}
		$this->redirect('/site/error/?msg='.urlencode($data).'&heading='.urlencode($heading));
	}

	public function error503()
	{
		$data = '您无权限操作此功能';
		$this->redirect('/block/error/?msg='.urlencode($data));
	}
}
