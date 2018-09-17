<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file goods.php
 * @author chendeshan
 * @date 2011-9-30 13:49:22
 * @version 0.6
 */
class APIGoods
{
	//获取全部商品特价活动
	public function getSaleList()
	{
		$promoDB   = new IModel('promotion');
		$promoList = $promoDB->query("is_close = 0 and award_type = 7","*","sort asc");
		$goodsDB   = new IModel('goods');

		foreach($promoList as $key => $val)
		{
			$intro = JSON::decode($val['intro']);
			$intro = array_keys($intro);
			$intro = join(",",$intro);
			$promoList[$key]['goodsList'] = $goodsDB->query("id in (".$intro.") and is_del = 0","id,name,sell_price,sort,img,market_price,sale","sort asc");
		}
		return $promoList;
	}
	//根据id获取单个商品特价活动
	public function getSaleRow($id)
	{
		$promoDB  = new IModel('promotion');
		$promoRow = $promoDB->getObj("is_close = 0 and award_type = 7 and id = {$id}");
		if($promoRow)
		{
			$intro = JSON::decode($promoRow['intro']);
			$intro = array_keys($intro);
			$intro = join(",",$intro);
			$goodsDB = new IModel('goods');
			$promoRow['goodsList'] = $goodsDB->query("id in (".$intro.") and is_del = 0","id,name,sell_price,sort,img,market_price","sort asc");
		}
		return $promoRow;
	}
}