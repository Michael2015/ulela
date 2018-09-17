<?php
/**
 * @copyright (c) 2011 jooyea.cn
 * @file notice.php
 * @brief 公告api方法
 * @author chendeshan
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APINotice
{
	//公告列表
	public function getNoticeList()
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('announcement');
		$query->order = 'id desc';
		$query->page  = $page;
		return $query;
	}
}