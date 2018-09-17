<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file countsum.php
 * @brief 计算购物车中的商品价格
 * @author chendeshan
 * @date 2011-02-24
 * @version 0.6
 */
class CountSum
{
	//用户ID
	public $user_id = 0;

	//用户组ID
	public $group_id = '';

	//用户组折扣
	public $group_discount = '';

	//错误信息
	public $error = '';
	/**
	 * 构造函数
	 */
	public function __construct($user_id = 0)
	{
		if($user_id)
		{
			$this->user_id = $user_id;
		}
		else
		{
			$userCheckRights = IWeb::$app->getController()->user;
			$this->user_id = ( isset($userCheckRights['user_id']) && $userCheckRights['user_id'] ) ? $userCheckRights['user_id'] : 0;
		}

		//获取用户组ID及组的折扣率
		if($this->user_id)
		{
			$groupObj = new IModel('member as m , user_group as g');
			$groupRow = $groupObj->getObj('m.user_id = '.$this->user_id.' and m.group_id = g.id','g.*');
			if($groupRow)
			{
				$this->group_id       = $groupRow['id'];
				$this->group_discount = $groupRow['discount'] * 0.01;
			}
		}
	}

	/**
	 * 获取会员组价格
	 * @param $id   int    商品或货品ID
	 * @param $type string goods:商品; product:货品 goods_arr:多个规格的商品
	 * edited by dyg_jzw 20150805
	 * @return float 价格
	 */
	public function getGroupPrice($id,$type = 'goods')
	{
		if(!$this->group_id)
		{
			return null;
		}

		//1,查询特定商品的组价格
		$groupPriceDB = new IModel('group_price');
		if($type == 'goods_arr')
		{
			$discountArr = $groupPriceDB->query('goods_id = '.$id.' and group_id = '.$this->group_id,'product_id,price');
		}
		else if($type == 'goods')
		{
			$discountRow = $groupPriceDB->getObj('goods_id = '.$id.' and group_id = '.$this->group_id,'price');
		}
		else
		{
			$discountRow = $groupPriceDB->getObj('product_id = '.$id.' and group_id = '.$this->group_id,'price');
		}

		if((isset($discountRow) && $discountRow))
		{
			return $discountRow['price'];
		}
		else if ((isset($discountArr) && $discountArr)) 
		{
			return $discountArr;
		}

		//2,根据会员折扣率计算商品折扣
		if($this->group_discount)
		{
			if($type == 'goods_arr')
			{
				$productDB  = new IModel('products');
				$productArr = $productDB->query('goods_id = '.$id,'id,sell_price');

				if ($productArr) 
				{
					$price_arr = array();
					foreach ($productArr as $_product) 
					{
						$price_arr[] = array(
											'product_id' => $_product['id'],
											'price' => $_product['sell_price']* $this->group_discount
										);
					}
					return $price_arr;
				}
			}
			else if($type == 'goods')
			{
				$goodsDB  = new IModel('goods');
				$goodsRow = $goodsDB->getObj('id = '.$id,'sell_price');
				return $goodsRow ? Util::priceFormat($goodsRow['sell_price'] * $this->group_discount) : null;
			}
			else
			{
				$productDB  = new IModel('products');
				$productRow = $productDB->getObj('id = '.$id,'sell_price');
				return $productRow ? Util::priceFormat($productRow['sell_price'] * $this->group_discount) : null;
			}
		}
		return null;
	}

	/**
	 * 获取会员组返后真正价格
	 * @param $id   int    商品或货品ID
	 * @param $type string goods:商品; product:货品
	 * @return float 价格
	 */
	public function getGroupRealPrice($id,$type = 'goods')
	{
		if(!$this->group_id)
		{
			return null;
		}

		//1,查询特定商品的组价格
		$groupPriceDB = new IModel('group_real_price');
		if($type == 'goods_arr')
		{
			$discountArr = $groupPriceDB->query('goods_id = '.$id.' and group_id = '.$this->group_id,'product_id,real_price');
		}
		elseif($type == 'goods')
		{
			$discountRow = $groupPriceDB->getObj('goods_id = '.$id.' and group_id = '.$this->group_id,'real_price');
		}
		else
		{
			$discountRow = $groupPriceDB->getObj('product_id = '.$id.' and group_id = '.$this->group_id,'real_price');
		}

		if((isset($discountRow) && $discountRow))
		{
			return $discountRow['real_price'];
		}
		else if ((isset($discountArr) && $discountArr)) 
		{
			return $discountArr;
		}

		return null;
	}

	/**
	 * @brief 计算商品价格
	 * @param Array $buyInfo ,购物车格式
	 * @promo string 活动类型 团购，抢购
	 * @active_id int 活动ID
	 * @return array or bool
	 */
	public function goodsCount($buyInfo,$promo='',$active_id='')
	{
		$this->sum           = 0;       //原始总额(优惠前)
		$this->final_sum     = 0;       //应付总额(优惠后)
    	$this->weight        = 0;       //总重量
    	$this->reduce        = 0;       //减少总额
    	$this->count         = 0;       //总数量
    	$this->promotion     = array(); //促销活动规则文本
    	$this->proReduce     = 0;       //促销活动规则优惠额
    	$this->point         = 0;       //增加积分
    	$this->exp           = 0;       //增加经验
    	$this->freeFreight   = array(); //商家免运费,免运费的商家ID,自营ID为0
    	$this->tax           = 0;       //商品税金
    	$this->seller        = array(); //商家商品总额统计, 商家ID => 商品金额

    	$this->commission = 0; //dyg_jzw 20150819 订单返佣合计
    	$this->is_cbe = 0; //dyg_jzw 20160307 是否跨境电商订单

    	$this->my_commission = 0; //dyg_jzw 20161215 高返模式的自身返利


		$user_id      = $this->user_id;
		$group_id     = $this->group_id;
    	$goodsList    = array();
    	$productList  = array();

    	$cbe_ids = array(); //dyg_jzw 20160307 跨境商品标记
    	$normal_ids = array(); //dyg_jzw 20160307 非跨境商品标记

    	/**
		 * 增加不同会员等级不同的积分倍数的设定
		 * author: dyg_jzw 20150812
		 */
    	//用户组积分倍数
		$group_point_times = 1;

		if ($user_id) 
		{
			//dyg_jzw 订单持有者的所在的用户组
			$memberObj = new IModel('member');
			$memberRow = $memberObj->getObj('user_id = '.$user_id);

			if (isset($memberRow['group_id']) && $memberRow['group_id']) 
			{
				//dyg_jzw 订单持有者所在用户组的信息
				$groupDB = new IModel('user_group');
				$groupRow = $groupDB->getObj('id = '.$memberRow['group_id']);

				if ($groupRow) 
				{
					$group_point_times = isset($groupRow['point_times']) ? $groupRow['point_times'] : 1;
				}
			}
		}
		
		//活动购买情况
    	if($promo && $active_id)
    	{
    		$ac_type    = isset($buyInfo['goods']) && $buyInfo['goods']['id'] ? "goods" : "product";
    		$ac_id      = current($buyInfo[$ac_type]['id']);
    		$ac_buy_num = $buyInfo[$ac_type]['data'][$ac_id]['count'];

			//开启促销活动
	    	$activeObject = new Active($promo,$active_id,$user_id,$ac_id,$ac_type,$ac_buy_num);
	    	$activeResult = $activeObject->checkValid();
	    	if($activeResult === true)
	    	{
	    		$typeRow  = $activeObject->originalGoodsInfo;
	    		$disPrice = $activeObject->activePrice;

	    		//dyg_jzw 20150812 是否赠送积分
	    		$is_point = $activeObject->is_point;
	    		//dyg_jzw 20150812 是否赠送经验值
	    		$is_exp = $activeObject->is_exp;
	    		//dyg_jzw 20161215 是否向上返还佣金
	    		$is_commission = $activeObject->is_commission;

				//设置优惠价格，如果不存在则优惠价等于商品原价
				$typeRow['reduce'] = $typeRow['sell_price'] - $disPrice;
				$typeRow['count']  = $ac_buy_num;
				$current_sum_all   = $typeRow['sell_price'] * $ac_buy_num;
    			$current_reduce_all= $typeRow['reduce']     * $ac_buy_num;
				$typeRow['sum']    = $current_sum_all - $current_reduce_all;

    			if(!isset($this->seller[$typeRow['seller_id']]))
    			{
    				$this->seller[$typeRow['seller_id']] = 0;
    			}
    			$this->seller[$typeRow['seller_id']] += $typeRow['sum'];

    			//全局统计
		    	$this->weight += $typeRow['weight'] * $ac_buy_num;
		    	if($is_point) //dyg_jzw 20150812 根据活动设定是否赠送积分
		    	{
		    		//$this->point  += $typeRow['point']  * $ac_buy_num;
		    		$this->point  += intval($typeRow['point'] * $group_point_times * $ac_buy_num); //dyg_jzw 20150812 用户组积分倍数
		    		
		    	}
		    	if($is_exp) //dyg_jzw 20150812 根据活动设定是否赠送经验值
		    	{
		    		//$this->exp    += $typeRow['exp'] * $ac_buy_num;
		    		$this->exp    += intval($typeRow['exp'] * $typeRow['sum']); //dyg_jzw 20150812 按每消费1元获得经验值
		    	}
		    	if ($is_commission) //dyg_jzw 20161215 是否向上返佣
		    	{
		    		/*
 					 * dyg_jzw 20161215 计算佣金
	    			 */
	    			$tmp_commission = $typeRow['commission'];
	    			$typeRow['commission'] = 0;

	    			if (isset($memberRow['group_id']) && $memberRow['group_id'] && $tmp_commission) 
	    			{
	    				$group_commission = json_decode($tmp_commission, true);

	    				//当前用户组是否设置了返佣比例
	    				if (isset($group_commission[$memberRow['group_id']]) && $group_commission[$memberRow['group_id']]) 
	    				{
	    					$typeRow['commission'] = intval($typeRow['sum'] * $group_commission[$memberRow['group_id']]) / 100;
	    				}
	    			}
	    			$this->commission += $typeRow['commission'];
		    	}
		    	else
		    	{
		    		$typeRow['commission'] = 0;
		    		$this->commission = 0;
		    	}

		    	//dyg_jzw 20161215 抢购或团购没有高返模式
		    	/*$typeRow['my_commission'] = 0;
		    	$this->my_commission = 0;*/

		    	$this->sum    += $current_sum_all;
		    	$this->reduce += $current_reduce_all;
		    	$this->count  += $ac_buy_num;
		    	$this->tax    += self::getGoodsTax($typeRow['sum'],$typeRow['seller_id']);

		    	$typeRow == "goods" ? ($goodsList[] = $typeRow) : ($productList[] = $typeRow);
	    	}
	    	else
	    	{
	    		//报错信息
				$this->error .= $activeResult;
				return $activeResult;
	    	}
    	}
    	else
    	{
			/*开始计算goods和product的优惠信息 , 会根据条件分析出执行以下哪一种情况:
			 *(1)查看此商品(货品)是否已经根据不同会员组设定了优惠价格;
			 *(2)当前用户是否属于某个用户组中的成员，并且此用户组享受折扣率;
			 *(3)优惠价等于商品(货品)原价;
			 */

			/**
			 * dyg_jzw 20150819 每个商品不同的返佣比例
			 * 计算出每个订单商品的返佣金额
			 */

			//获取商品或货品数据
			/*Goods 拼装商品优惠价的数据*/
	    	if(isset($buyInfo['goods']['id']) && $buyInfo['goods']['id'])
	    	{
	    		//购物车中的商品数据
	    		$goodsIdStr = join(',',$buyInfo['goods']['id']);
	    		$goodsObj   = new IModel('goods as go');
	    		//dyg_jzw 20150819 获取返佣设置 go.commission
	    		//dyg_jzw 20160307 增加是否跨境商品字段 go.is_cbe
	    		$goodsList  = $goodsObj->query('go.id in ('.$goodsIdStr.')','go.name,go.cost_price,go.id as goods_id,go.img,go.sell_price,go.point,go.weight,go.store_nums,go.exp,go.goods_no,0 as product_id,go.seller_id, go.commission, go.is_cbe');

	    		//开始优惠情况判断
	    		foreach($goodsList as $key => $val)
	    		{
	    			//检查库存
	    			if($buyInfo['goods']['data'][$val['goods_id']]['count'] <= 0 || $buyInfo['goods']['data'][$val['goods_id']]['count'] > $val['store_nums'])
	    			{
	    				$goodsList[$key]['name'] .= "【无库存】";
	    				$this->error .= "<商品：".$val['name']."> 购买数量超出库存，请重新调整购买数量。";
	    			}

	    			$groupPrice                = $this->getGroupPrice($val['goods_id'],'goods');

	    			$goodsList[$key]['reduce'] = $groupPrice === null ? 0 : $val['sell_price'] - $groupPrice;
	    			$goodsList[$key]['count']  = $buyInfo['goods']['data'][$val['goods_id']]['count'];
	    			$current_sum_all           = $goodsList[$key]['sell_price'] * $goodsList[$key]['count'];
	    			$current_reduce_all        = $goodsList[$key]['reduce']     * $goodsList[$key]['count'];
	    			$goodsList[$key]['sum']    = $current_sum_all - $current_reduce_all;

	    			//dyg_jzw 20161215 高返自身的返点
	    			$goodsList[$key]['my_commission'] = 0;

	    			//dyg_jzw 20161215 获取会员返还后真正价格
	    			$current_realsum_all = 0;
	    			$groupRealPrice				= $this->getGroupRealPrice($val['goods_id'],'goods');
	    			if ($groupRealPrice > 0)
	    			{
	    				//真正购买的金额
	    				$current_realsum_all	= $groupRealPrice * $goodsList[$key]['count'];
	    				if ($goodsList[$key]['sum'] > $current_realsum_all)
	    				{
	    					$goodsList[$key]['my_commission'] = $goodsList[$key]['sum'] - $current_realsum_all;
	    				}
	    			}


	    			if(!isset($this->seller[$val['seller_id']]))
	    			{
	    				$this->seller[$val['seller_id']] = 0;
	    			}
	    			$this->seller[$val['seller_id']] += $goodsList[$key]['sum'];

	    			/*
 					 * dyg_jzw 20150819 计算佣金
	    			 */
	    			$goodsList[$key]['commission'] = 0;

	    			if (isset($memberRow['group_id']) && $memberRow['group_id'] && $val['commission']) 
	    			{
	    				$group_commission = json_decode($val['commission'], true);

	    				//当前用户组是否设置了返佣比例
	    				if (isset($group_commission[$memberRow['group_id']]) && $group_commission[$memberRow['group_id']]) 
	    				{
	    					if ($current_realsum_all)
	    					{
	    						if ($goodsList[$key]['sum'] > $current_realsum_all)
	    						{
	    							$goodsList[$key]['commission'] = intval($current_realsum_all * $group_commission[$memberRow['group_id']]) / 100;
	    						}
	    					}
	    					else
	    					{
	    						$goodsList[$key]['commission'] = intval($goodsList[$key]['sum'] * $group_commission[$memberRow['group_id']]) / 100;
	    					}
	    				}
	    			}

	    			//dyg_jzw 20160307 商品进行是否跨境分类
	    			if ($val['is_cbe'])
	    			{
	    				$cbe_ids[] = $val['goods_id'];
	    			}
	    			else
	    			{
	    				$normal_ids[] = $val['goods_id'];
	    			}

	    			//全局统计
			    	$this->weight += $val['weight'] * $goodsList[$key]['count'];
			    	//$this->point  += $val['point']  * $goodsList[$key]['count'];
			    	$this->point  += intval($val['point'] * $group_point_times * $goodsList[$key]['count']); //dyg_jzw 20150812 增加用户组积分倍数
			    	$goodsList[$key]['point'] = intval($val['point'] * $group_point_times); //dyg_jzw 20170220 购物车结构的积分显示
			    	//$this->exp    += $val['exp']    * $goodsList[$key]['count'];
			    	$this->exp    += intval($val['exp']    * $goodsList[$key]['sum']); //dyg_jzw 修改为每消费1元获得经验值

			    	$this->sum    += $current_sum_all;
			    	$this->reduce += $current_reduce_all;
			    	$this->count  += $goodsList[$key]['count'];
			    	$this->tax    += self::getGoodsTax($goodsList[$key]['sum'],$val['seller_id']);

			    	//dyg_jzw 20150819 订单返佣合计
			    	$this->commission += $goodsList[$key]['commission']; 

			    	//dyg_jzw 20161215 高返自身返利
			    	$this->my_commission += $goodsList[$key]['my_commission']; 
			    }
	    	}

			/*Product 拼装商品优惠价的数据*/
	    	if(isset($buyInfo['product']['id']) && $buyInfo['product']['id'])
	    	{
	    		//购物车中的货品数据
	    		$productIdStr = join(',',$buyInfo['product']['id']);
	    		$productObj   = new IQuery('products as pro,goods as go');
	    		$productObj->where  = 'pro.id in ('.$productIdStr.') and go.id = pro.goods_id';
	    		//dyg_jzw 20150819 获取返佣设置 go.commission
	    		//dyg_jzw 20160307 增加跨境商品字段 go.is_cbe
	    		//$productObj->fields = 'pro.sell_price,pro.cost_price,pro.weight,pro.id as product_id,pro.spec_array,pro.goods_id,pro.store_nums,pro.products_no as goods_no,go.name,go.point,go.exp,go.img,go.seller_id, go.commission, go.is_cbe';
	    		$productObj->fields = 'pro.sell_price,pro.cost_price,pro.weight,pro.id as product_id,pro.spec_array,pro.goods_id,pro.store_nums,pro.products_no as goods_no,go.name,pro.point,go.exp,go.img,go.seller_id, go.commission, go.is_cbe'; //dyg_jzw 20170220 获取products的point
	    		$productList  = $productObj->find();

	    		//开始优惠情况判断
	    		foreach($productList as $key => $val)
	    		{
	    			//检查库存
	    			if($buyInfo['product']['data'][$val['product_id']]['count'] <= 0 || $buyInfo['product']['data'][$val['product_id']]['count'] > $val['store_nums'])
	    			{
	    				$productList[$key]['name'] .= "【无库存】";
	    				$this->error .= "<货品：".$val['name']."> 购买数量超出库存，请重新调整购买数量。";
	    			}

	    			$groupPrice                  = $this->getGroupPrice($val['product_id'],'product');
					$productList[$key]['reduce'] = $groupPrice === null ? 0 : $val['sell_price'] - $groupPrice;
	    			$productList[$key]['count']  = $buyInfo['product']['data'][$val['product_id']]['count'];
	    			$current_sum_all             = $productList[$key]['sell_price']  * $productList[$key]['count'];
	    			$current_reduce_all          = $productList[$key]['reduce']      * $productList[$key]['count'];
	    			$productList[$key]['sum']    = $current_sum_all - $current_reduce_all;
	    			
	    			//dyg_jzw 20161215 高返自身的返点
	    			$productList[$key]['my_commission'] = 0;

	    			//dyg_jzw 20161215 获取会员返还后真正价格
	    			$current_realsum_all = 0;
	    			$groupRealPrice				= $this->getGroupRealPrice($val['product_id'],'product');
	    			if ($groupRealPrice > 0)
	    			{
	    				//真正购买的金额
	    				$current_realsum_all	= $groupRealPrice * $productList[$key]['count'];
	    				if ($productList[$key]['sum'] > $current_realsum_all)
	    				{
	    					$productList[$key]['my_commission'] = $productList[$key]['sum'] - $current_realsum_all;
	    				}
	    			}

	    			if(!isset($this->seller[$val['seller_id']]))
	    			{
	    				$this->seller[$val['seller_id']] = 0;
	    			}
	    			$this->seller[$val['seller_id']] += $productList[$key]['sum'];

	    			/*
 					 * dyg_jzw 20150819 计算佣金
	    			 */
	    			$productList[$key]['commission'] = 0;

	    			if (isset($memberRow['group_id']) && $memberRow['group_id'] && $val['commission']) 
	    			{
	    				$group_commission = json_decode($val['commission'], true);

	    				//当前用户组是否设置了返佣比例
	    				if (isset($group_commission[$memberRow['group_id']]) && $group_commission[$memberRow['group_id']]) 
	    				{
	    					if ($current_realsum_all)
	    					{
	    						if ($productList[$key]['sum'] > $current_realsum_all)
	    						{
	    							$productList[$key]['commission'] = intval($current_realsum_all * $group_commission[$memberRow['group_id']]) / 100;
	    						}
	    					}
	    					else
	    					{
	    						$productList[$key]['commission'] = intval($productList[$key]['sum'] * $group_commission[$memberRow['group_id']]) / 100;
	    					}
	    				}
	    			}

	    			//dyg_jzw 20160307 商品进行是否跨境分类
	    			if ($val['is_cbe'])
	    			{
	    				$cbe_ids[] = $val['goods_id'];
	    			}
	    			else
	    			{
	    				$normal_ids[] = $val['goods_id'];
	    			}

	    			//全局统计
			    	$this->weight += $val['weight'] * $productList[$key]['count'];
			    	//$this->point  += $val['point']  * $productList[$key]['count'];
			    	$this->point  += intval($val['point'] * $group_point_times * $productList[$key]['count']); //dyg_jzw 20150812 增加用户组积分倍数
			    	$productList[$key]['point'] = intval($val['point'] * $group_point_times); //dyg_jzw 20170220 购物车结构的积分显示
			    	//$this->exp    += $val['exp']    * $productList[$key]['count'];
			    	$this->exp    += intval($val['exp']    * $productList[$key]['sum']); //dyg_jzw 修改为每消费1元获得经验值

			    	$this->sum    += $current_sum_all;
			    	$this->reduce += $current_reduce_all;
			    	$this->count  += $productList[$key]['count'];
			    	$this->tax    += self::getGoodsTax($productList[$key]['sum'],$val['seller_id']);

			    	//dyg_jzw 20150819 订单返佣合计
			    	$this->commission += $productList[$key]['commission'];

			    	//dyg_jzw 20161215 高返自身返利
			    	$this->my_commission += $productList[$key]['my_commission'];  
			    }
	    	}

	    	//总金额满足的促销规则
	    	if($user_id)
	    	{
	    		//计算每个商家促销规则
	    		foreach($this->seller as $seller_id => $sum)
	    		{
			    	$proObj = new ProRule($sum,$seller_id);
			    	$proObj->setUserGroup($group_id);
			    	if($proObj->isFreeFreight() == true)
			    	{
			    		$this->freeFreight[] = $seller_id;
			    	}
			    	$this->promotion = array_merge($proObj->getInfo(),$this->promotion);
			    	$this->proReduce += $sum - $proObj->getSum();
	    		}
	    	}
    	}
    	$this->final_sum = $this->sum - $this->reduce - $this->proReduce;
    	$this->final_sum = $this->final_sum <= 0 ? 0 : $this->final_sum;
    	$resultList      = array_merge($goodsList,$productList);
    	if(!$resultList)
    	{
    		$this->error .= "商品信息不存在。";
    	}

    	//dyg_jzw 20160307 是否跨境商品与非跨境电商混杂
    	if (count($normal_ids) && count($cbe_ids)) 
    	{
    		$this->error .= "海外商品与其他商品混合。";
    	}
    	else if (empty($normal_ids) && !empty($cbe_ids))
    	{
    		$this->is_cbe = 1;
    	}
		
    	return array(
    		'final_sum'  => $this->final_sum,
    		'promotion'  => $this->promotion,
    		'proReduce'  => $this->proReduce,
    		'sum'        => $this->sum,
    		'goodsList'  => $resultList,
    		'count'      => $this->count,
    		'reduce'     => $this->reduce,
    		'weight'     => $this->weight,
    		'point'      => $this->point,
    		'exp'        => $this->exp,
    		'tax'        => $this->tax,
    		'freeFreight'=> $this->freeFreight,
    		'seller'     => $this->seller,
    		'commission' => $this->commission, //dyg_jzw 20150819 增加返佣合计
    		'my_commission' => $this->my_commission, //dyg_jzw 20161215 增加高返自身返利
    		'is_cbe'	 => $this->is_cbe, //dyg_jzw 20160307 跨境订单 状态0:非跨境 1:创建跨境订单
    	);
	}

	//购物车计算
	public function cart_count($id = '',$type = '',$buy_num = 1,$promo='',$active_id='')
	{
		//单品购买
		if($id && $type)
		{
			$type = ($type == "goods") ? "goods" : "product";

			//规格必填
			if($type == "goods")
			{
				$productsDB = new IModel('products');
				if($productsDB->getObj('goods_id = '.$id))
				{
					$this->error .= '请先选择商品的规格';
					return $this->error;
				}
			}

    		$buyInfo = array(
    			$type => array('id' => array($id),'data' => array($id => array('count' => $buy_num)),'count' => $buy_num)
    		);
		}
		else
		{
			//获取购物车中的商品和货品信息
	    	$cartObj = new Cart();
	    	$buyInfo = $cartObj->getMyCart();
		}
    	return $this->goodsCount($buyInfo,$promo,$active_id);
    }

    /**
     * 计算订单信息,其中部分计算都是以商品原总价格计算的$goodsSum
     * @param $goodsResult array CountSum结果集
     * @param $province_id int 省份ID
     * @param $delievery_id int 配送方式ID
     * @param $payment_id int 支付ID
     * @param $is_invoice int 是否要发票
     * @param $discount float 订单的加价或者减价
     * @param $promo string 促销活动
     * @param $active_id int 促销活动ID
     * @return $result 最终的返回数组
     */
    public function countOrderFee($goodsResult,$province_id,$delivery_id,$payment_id,$is_invoice,$discount = 0,$promo = '',$active_id = '')
    {
    	//根据商家进行商品分组
    	$sellerGoods = array();
    	foreach($goodsResult['goodsList'] as $key => $val)
    	{
    		if(!isset($sellerGoods[$val['seller_id']]))
    		{
    			$sellerGoods[$val['seller_id']] = array();
    		}
    		$sellerGoods[$val['seller_id']][] = $val;
    	}

		$cartObj = new Cart();
    	foreach($sellerGoods as $seller_id => $item)
    	{
    		$num          = array();
    		$productID    = array();
    		$goodsID      = array();
    		$goodsArray   = array();
    		$productArray = array();
    		foreach($item as $key => $val)
    		{
    			$goodsID[]   = $val['goods_id'];
    			$productID[] = $val['product_id'];
    			$num[]       = $val['count'];
	    		if($val['product_id'] > 0)
	    		{
	    			$productArray[$val['product_id']] = $val['count'];
	    		}
	    		else
	    		{
	    			$goodsArray[$val['goods_id']] = $val['count'];
	    		}
    		}
    		$sellerData = $this->goodsCount($cartObj->cartFormat(array("goods" => $goodsArray,"product" => $productArray)),$promo,$active_id);
	    	if(is_string($sellerData))
	    	{
				return $sellerData;
	    	}

	    	$deliveryList = Delivery::getDelivery($province_id,$delivery_id,$goodsID,$productID,$num);
	    	if(is_string($deliveryList))
	    	{
				return $deliveryList;
	    	}
			//有促销免运费活动
			if(isset($sellerData['freeFreight']) && $sellerData['freeFreight'])
			{
				foreach($sellerData['freeFreight'] as $sid)
				{
					if(isset($deliveryList['seller_price'][$sid]))
					{
						$deliveryList['price'] -= $deliveryList['seller_price'][$sid];
						$deliveryList['seller_price'][$sid] = 0;
					}
				}
	    	}

	    	$extendArray = array(
	    		'deliveryOrigPrice' => $deliveryList['org_price'],
	    		'deliveryPrice'     => $deliveryList['price'],
	    		'insuredPrice'      => $deliveryList['protect_price'],
	    		'taxPrice'          => $is_invoice == true ? $sellerData['tax'] : 0,
	    		'paymentPrice'      => $payment_id != 0 ? self::getGoodsPaymentPrice($payment_id,$sellerData['final_sum']) : 0,
	    		'goodsResult'       => $sellerData,
	    		'orderAmountPrice'  => 0,
	    		'order_commission'	=> $discount < 0 ? 0 : $goodsResult['commission'], //dyg_jzw 20150819 订单合计返佣
	    		'order_my_commission'	=> $goodsResult['my_commission'], //dyg_jzw 20161215 高返模式自身返利
	    	);
	    	$orderAmountPrice = array_sum(array(
		    	$sellerData['final_sum'],
		    	$deliveryList['price'],
		    	$deliveryList['protect_price'],
		    	$extendArray['taxPrice'],
		    	$extendArray['paymentPrice'],
		    	$discount,
	    	));

			$extendArray['orderAmountPrice'] = $orderAmountPrice <= 0 ? 0 : round($orderAmountPrice,2);
			$sellerGoods[$val['seller_id']]  = array_merge($sellerData,$extendArray);
    	}
    	return $sellerGoods;
    }

    /**
     * 获取商品的税金
     * @param $goodsSum float 商品总价格
     * @param $seller_id int 商家ID
     * @return $goodsTaxPrice float 商品的税金
     */
    public static function getGoodsTax($goodsSum,$seller_id = 0)
    {
    	if($seller_id)
    	{
    		$sellerDB = new IModel('seller');
    		$sellerRow= $sellerDB->getObj('id = '.$seller_id);
    		$tax_per  = $sellerRow['tax'];
    	}
    	else
    	{
			$siteConfigObj = new Config("site_config");
			$site_config   = $siteConfigObj->getInfo();
			$tax_per       = isset($site_config['tax']) ? $site_config['tax'] : 0;
    	}
		$goodsTaxPrice = $goodsSum * ($tax_per * 0.01);
		return round($goodsTaxPrice,2);
    }

    /**
     * 获取商品金额的支付费用
     * @param $payment_id int 支付方式ID
     * @param $goodsSum float 商品总价格
     * @return $goodsPayPrice
     */
    public static function getGoodsPaymentPrice($payment_id,$goodsSum)
    {
		$paymentObj = new IModel('payment');
		$paymentRow = $paymentObj->getObj('id = '.$payment_id,'poundage,poundage_type');

		if($paymentRow)
		{
			if($paymentRow['poundage_type'] == 1)
			{
				//按照百分比
				return $goodsSum * ($paymentRow['poundage'] * 0.01);
			}
			//按照固定金额
			return $paymentRow['poundage'];
		}
		return 0;
    }

	/**
	 * @brief 获取商户订单货款结算
	 * @param int $seller_id 商户ID
	 * @param datetime $start_time 订单开始时间
	 * @param datetime $end_time 订单结束时间
	 * @param string $is_checkout 是否已经结算 0:未结算; 1:已结算; null:不限
	 * @param IQuery 结果集对象
	 */
    public static function getSellerGoodsFeeQuery($seller_id = '',$start_time = '',$end_time = '',$is_checkout = '')
    {
    	$where  = "status in (5,6,7) and pay_type != 0 and pay_status = 1 and distribution_status in (0,1,2)";
    	$where .= $is_checkout !== '' ? " and is_checkout = ".$is_checkout : "";
    	$where .= $seller_id          ? " and seller_id = ".$seller_id : "";
    	$where .= $start_time         ? " and create_time >= '{$start_time}' " : "";
    	$where .= $end_time           ? " and create_time <= '{$end_time}' "   : "";

    	$orderGoodsDB = new IQuery('order');
    	$orderGoodsDB->order = "id desc";
    	$orderGoodsDB->where = $where;
    	return $orderGoodsDB;
    }

	/**
	 * @brief 计算商户货款及其他费用
	 * @param array $orderList 订单数据关联
	 * @return array(
	 * 'orderAmountPrice' => 订单金额（去掉pay_fee支付手续费）,'refundFee' => 退款金额, 'orgCountFee' => 原始结算金额,
	 * 'countFee' => 实际结算金额, 'platformFee' => 平台促销活动金额(代金券等平台补贴给商家),'commission' => '手续费' ,'commissionPer' => '手续费比率',
	 * 'orderNum' => 订单数量, 'order_ids' => 订单IDS,'orderNoList' => 订单编号
	 * ),
	 */
    public static function countSellerOrderFee($orderList)
    {
    	$result = array(
			'orderAmountPrice' => 0,
			'refundFee'        => 0,
			'orgCountFee'      => 0,
			'countFee'         => 0,
			'platformFee'      => 0,
			'commission'       => 0,
			'commissionPer'    => 0,
			'orderNum'         => count($orderList),
			'order_ids'        => array(),
			'orderNoList'      => array(),
    	);

    	if($orderList && is_array($orderList))
    	{
    		$refundObj = new IModel("refundment_doc");
    		$propObj   = new IModel("prop");
    		foreach($orderList as $key => $item)
    		{
    			//检查平台促销活动
    			//1,代金券
    			if($item['prop'])
    			{
    				$propRow = $propObj->getObj('id = '.$item['prop'].' and type = 0');
    				if($propRow && $propRow['seller_id'] == 0)
    				{
    					$propRow['value'] = min($item['real_amount'],$propRow['value']);
    					$result['platformFee'] += $propRow['value'];
    				}
    			}

    			$result['orderAmountPrice'] += $item['order_amount'] - $item['pay_fee'];
    			$result['order_ids'][]       = $item['id'];
    			$result['orderNoList'][]     = $item['order_no'];

    			//是否存在退款
    			$refundList = $refundObj->query("order_id = ".$item['id'].' and pay_status = 2');
    			foreach($refundList as $k => $val)
    			{
    				$result['refundFee'] += $val['amount'];
    			}
    		}
    	}

		//应该结算金额
		$result['orgCountFee'] = $result['orderAmountPrice'] - $result['refundFee'] + $result['platformFee'];

		//获取结算手续费
		$siteConfigData = new Config('site_config');
		$result['commissionPer'] = $siteConfigData->commission ? $siteConfigData->commission : 0;
		$result['commission']    = round($result['orgCountFee'] * $result['commissionPer']/100,2);

		//最终结算金额
		$result['countFee'] = $result['orgCountFee'] - $result['commission'];

    	return $result;
    }
}