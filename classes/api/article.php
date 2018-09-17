<?php
/**
 * @copyright (c) 2011 jooyea.cn
 * @file article.php
 * @brief 公告api方法
 * @author chendeshan
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APIArticle
{
	//获取文章分类数据
	public function getArticleCategoryInfo($catId)
	{
		$db = new IModel('article_category');
		return $db->getObj('id = '.$catId);
	}

	//文章列表
	public function getArticleList()
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('article');
		$query->where = 'visibility = 1 ';
		$query->order = 'id desc';
		$query->page  = $page;
		return $query;
	}
	public function getArticleListByCatid($category_id)
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('article');
		$query->where = 'category_id = '.$category_id.' and visibility = 1';
		$query->order = 'id desc';
		$query->page  = $page;
		return $query;
	}

}