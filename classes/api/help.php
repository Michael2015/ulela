<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file notice.php
 * @brief 公告api方法
 * @author chendeshan
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APIHelp
{

	//帮助中心列表
	public function getHelpList()
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('help');
		$query->order = 'sort desc';
		$query->page  = $page;
		return $query;
	}
	//根据分类取帮助中心列表
	public function getHelpListByCatId($catId)
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('help');
		$query->where="cat_id = ".$catId;
		$query->order = 'sort desc';
		$query->page  = $page;
		return $query;
	}

}