<?php
/**
 * @copyright (c) 2011 jooyea.cn
 * @file seller.php
 * @brief 商家API
 * @author chendeshan
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APISeller
{
	//商户信息
	public function getSellerInfo($id)
	{
		$query = new IModel('seller');
		$info  = $query->getObj("id=".$id);
		return $info;
	}

	//获取商户列表
	public function getSellerList()
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('seller');
		$query->where = 'is_del = 0 and is_lock = 0';
		$query->order = 'sort asc';
		$query->page  = $page;
		return $query;
	}
}