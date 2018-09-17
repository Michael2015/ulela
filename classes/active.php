<?php
/**
 * @copyright Copyright(c) 2015-2016 aircheng.com
 * @file active.php
 * @brief 促销活动处理类
 * @author nswe
 * @date 2016/6/20 14:59:38
 * @version 4.5
 */
class Active
{
	//活动的类型,groupon(团购),time(限时抢购)
	private $promo;

	//参加活动的用户ID
	private $user_id;

	//活动的ID编号
	private $active_id;

	//商品ID 或 货品ID
	private $id;

	//goods 或 product
	private $type;

	//购买数量
	private $buy_num;

	//原始的商品或者货品数据
	public $originalGoodsInfo;

	//活动价格
	public $activePrice;

	//dyg_jzw 20150812 是否赠送积分
	public $is_point;

	//dyg_jzw 20150812 是否赠送经验值
	public $is_exp;

	//dyg_jzw 20161215 是否向上返佣
	public $is_commission;

	/**
	 * @brief 构造函数创建活动
	 * @param $promo string 活动的类型,groupon(团购),time(限时抢购)
	 * @param $activeId int 活动的ID编号
	 * @param $user_id int 用户的ID编号
	 * @param $id  int 根据$type的不同而表示：商品id,货品id
	 * @param $type string 商品：goods; 货品：product
	 * @param $buy_num int 购买的数量；默认1
	 */
	public function __construct($promo,$active_id,$user_id = 0,$id,$type = "goods",$buy_num = 1)
	{
		$this->promo     = $promo;
		$this->active_id = $active_id;
		$this->user_id   = $user_id;
		$this->id        = $id;
		$this->type      = $type;
		$this->buy_num   = $buy_num;
	}

	/**
	 * @brief 检查活动的合法性
	 * @return string(有错误) or true(处理正确)
	 */
	public function checkValid()
	{
		if(!$this->id)
		{
			return "商品ID不存在";
		}
		$goodsData = ($this->type == 'product') ? Api::run('getProductInfo',array('#id#',$this->id)) : Api::run('getGoodsInfo',array('#id#',$this->id));

		//库存判断
		if(!$goodsData || $this->buy_num <= 0 || $this->buy_num > $goodsData['store_nums'])
		{
			return "购买的数量不正确或大于商品的库存量";
		}

		$this->originalGoodsInfo = $goodsData;
		$this->activePrice       = $goodsData['sell_price'];
		$goods_id                = $goodsData['goods_id'];

		//具体促销活动的合法性判断
		switch($this->promo)
		{
			//团购
			case "groupon":
			{
				if(!$this->user_id)
				{
					return "参加团购活动请您先登录";
				}

				$regimentRow = Api::run('getRegimentRowById',array("#id#",$this->active_id));
				if($regimentRow)
				{
					if($regimentRow['goods_id'] != $goodsData['goods_id'])
					{
						return "该商品没有参与团购活动";
					}
					
					//dyg_jzw 20150812 是否赠送积分
					$this->is_point = $regimentRow['is_point'];
					//dyg_jzw 20150812 是否赠送经验值
					$this->is_exp = $regimentRow['is_exp'];
					//dyg_jzw 20161215 是否向上返佣
					$this->is_commission = $regimentRow['is_commission'];

					if($regimentRow['store_nums'] <= $regimentRow['sum_count'])
					{
						return "团购商品已经销售一空";
					}

					//检查次团购订单
					$orderDB   = new IModel('order as o,order_goods as og');
					$orderData = $orderDB->query('o.user_id = '.$this->user_id.' and o.type = 1 and o.id = og.order_id and active_id = '.$this->active_id);
					$hasBugNum = 0;
					foreach($orderData as $key => $val)
					{
						$orderStatus = Order_class::getOrderStatus($val);
						if(in_array($orderStatus,array(2,1,11)))
						{
							return "您参与的该团购订单还没有完成";
						}

						if(in_array($orderStatus,array(3,4,6)))
						{
							$hasBugNum += $val['goods_nums'];
						}
					}

					//批量购买(薄利多销)
					if($regimentRow['limit_min_count'] > 0)
					{
						if($this->buy_num < $regimentRow['limit_min_count'])
						{
							return "购买数量必须超过 ".$regimentRow['limit_min_count']." 件才能下单";
						}
					}

					//限制购买(限购，要多人参与)
					if($regimentRow['limit_max_count'] > 0)
					{
						if($this->buy_num > $regimentRow['limit_max_count'])
						{
							return "购买数量不能超过 ".$regimentRow['limit_max_count']." 件";
						}

						if(($hasBugNum + $this->buy_num) > $regimentRow['limit_max_count'])
						{
							return "此团购为限购活动，您累计购买数量不能超过".$regimentRow['limit_max_count'];
						}
					}

					if($this->buy_num > $regimentRow['store_nums'])
					{
						return "购买数量超过了团购剩余量";
					}

					$this->activePrice = $regimentRow['regiment_price'];
				}
				else
				{
					return "当前时间段内不存在此团购活动";
				}
				return true;
			}
			break;

			//抢购
			case "time":
			{
				$promotionRow = Api::run('getPromotionRowById',array("#id#",$this->active_id));
				if($promotionRow)
				{
					if($promotionRow['condition'] != $goodsData['goods_id'])
					{
						return "该商品没有参与抢购活动";
					}
					
					//dyg_jzw 20150812 是否赠送积分
					$this->is_point = $promotionRow['is_point'];
					//dyg_jzw 20150812 是否赠送经验值
					$this->is_exp = $promotionRow['is_exp'];
					//dyg_jzw 20161215 是否向上返佣
					$this->is_commission = $promotionRow['is_commission'];

					$memberObj = new IModel('member');
					$memberRow = $memberObj->getObj('user_id = '.$this->user_id,'group_id');
					if($promotionRow['user_group'] == 'all' || (isset($memberRow['group_id']) && stripos(','.$promotionRow['user_group'].',',$memberRow['group_id'])!==false))
					{
						$this->activePrice = $promotionRow['award_value'];
					}
					else
					{
						return "此活动仅限指定的用户组";
					}
				}
				else
				{
					return "不存在此限时抢购活动";
				}
				return true;
			}
			break;
		}
		return "未知促销活动";
	}

	/**
	 * @brief 促销活动对应order_type的值
	 */
	public function getOrderType()
	{
		$result = array('groupon' => 1,'time' => 2);
		return isset($result[$this->promo]) ? $result[$this->promo] : 0;
	}

	/**
	 * @brief 订单付款后的回调
	 * @param $orderNo string 订单号
	 * @param $orderType 订单类型 1:团购; 2:抢购;
	 */
	public static function payCallback($orderNo,$orderType)
	{
		switch($orderType)
		{
			//团购
			case "1":
			{
				$tableModel = new IModel('order as o,order_goods as og');
				$orderRow   = $tableModel->getObj("o.order_no = '{$orderNo}' and o.id = og.order_id and o.type = 1","og.goods_nums,o.active_id");
				if($orderRow)
				{
					$regimentModel = new IModel('regiment');
					$regimentModel->setData(array('sum_count' => 'sum_count + '.$orderRow['goods_nums']));
					$regimentModel->update('id = '.$orderRow['active_id'],array('sum_count'));
				}
			}
			break;

			//抢购
			case "2":
			{

			}
			break;
		}
	}
	//获取活动数据
	public function data()
	{
		switch($this->promo)
		{
			case "groupon":
			{
				$data = Api::run("getRegimentRowById",array("#id#",$this->active_id));
				if($data && $data['goods_id'] ==  $this->id)
				{
					return $data;
				}
				return "团购活动不存在";
			}
			break;
			case "time":
			{
				$data = Api::run("getPromotionRowById",array("#id#",$this->active_id));
				if($data && $data['condition'] == $this->id)
				{
					return $data;
				}
				return "限时抢购活动不存在";
			}
			break;
		}
	}
}