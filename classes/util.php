<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file util.php
 * @brief 公共函数类
 * @author kane
 * @date 2011-01-13
 * @version 0.6
 * @note
 */

 /**
 * @class Util
 * @brief 公共函数类
 */
class Util
{
	/**
	 * @brief 显示错误信息（dialog框）
	 * @param string $message	错误提示字符串
	 */
	public static function showMessage($message)
	{
		//dyg_jzw 20161205 修改提示语句
		//echo '<script type="text/javascript">art.dialog.tips("'.$message.'")</script>';
		echo '<script type="text/javascript">tips("'.$message.'")</script>';
		exit;
	}

	/**
	 * 处理二维数组
	 *
	 * 根据第二维某个索引的值来设置相应的第一维数组的key
	 * 如原来是
	 * array(array('id'=>'a','data'=>'') ,array('id'=>1000,'data'=>'')  )
	 * 按照id索引处理后：
	 * array('a'=>array('id'=>'a','data'=>'') ,1000=>array('id'=>1000,'data'=>'')  )
	 *
	 * @author walu
	 * @param array $arr	待处理的二维数组
	 * @param array $key	获取第二维值的索引
	 * @return array
	 */
	public static function array_rekey($arr,$key='id')
	{
		$fun_re=array();
		foreach($arr as $value)
		{
			$fun_re[$value[$key]]=$value;
		}
		return $fun_re;
	}
	//字符串拼接
	public static function joinStr($id)
	{
		if(is_array($id))
		{
			$where = "id in (".join(',',$id).")";
		}
		else
		{
			$where = 'id = '.$id;
		}
		return $where;
	}

	/**
	 * 商品价格格式化
	 * @param $price float 商品价
	 * @return float 格式化后的价格
	 */
	public static function priceFormat($price)
	{
		return round($price,2);
	}

	/**
	 * 检索自动执行
	 * @param array $search 查询拼接规则， key(字段) => like,likeValue(数据)
	 */
	public static function search($search)
	{
		$where = array(1);
		if($search && is_array($search))
		{


		//like子句处理
		if(isset($search['like']) && $search['likeValue'])
		{
			$search['like']      = IFilter::act($search['like'],"strict");
			$search['likeValue'] = IFilter::act($search['likeValue']);

			$where[] = $search['like']." like '%".$search['likeValue']."%' ";
		}
		unset($search['like']);
		unset($search['likeValue']);

		//自定义子句处理
		foreach($search as $key => $val)
		{
			$key = IFilter::act($key,'strict');
			$val = IFilter::act($val,'strict');

			if($val === '' || $key === '' || $val == 'favicon.ico')
			{
				continue;
			}

			if( strpos($key,'num') !== false || in_array($val[0],array("<",">","=")) )
			{
				$where[] = $key." ".$val;
			}
			else
			{
				$where[] = $key."'".$val."'";
				}
			}
		}
		return join(" and ",$where);
	}

	/**
	 * @brief 获取$_GET指定的参数
	 * @param $urlKey 参数名称
	 * @return mixed
	 */
	public static function getUrlParam($urlKey)
	{
		$tempGet = $_GET;
		foreach($tempGet as $key => $val)
		{
			if($key != $urlKey)
			{
				unset($tempGet[$key]);
			}
		}
		return $tempGet;
	}

	/**
	 * @brief 计算折扣率
	 * @param $originalPrice float 原价
	 * @param $nowPrice float 现价
	 * @return float 折扣数
	 */
	public static function discount($originalPrice,$nowPrice)
	{
		if($originalPrice >= $nowPrice)
		{
		return round(($originalPrice - $nowPrice)/$originalPrice * 10,1);
		}
		return "";
	}

	/**
	 * 商品检索
	 * @param array $search 条件数组
	 * @return string 拼接的WHERE条件语句
	 */
	public static function goodsSearch($search)
	{
		if (!$search)
		{
			return '';
		}
		$where = array();

		// 商品名称
		if(isset($search['name']))
		{
			$name = IFilter::act($search['name'], 'string');
			if ('' != $name)
			{
				$where[] = "go.name like '%".$name."%' ";
			}
		}

		// 商品货号
		if (isset($search['goods_no']) && !empty($search['goods_no']))
		{
			$goods_no = IFilter::act($search['goods_no'], 'string');
			if ('' != $goods_no)
			{
				$where[] = "go.goods_no like '%".$goods_no."%' ";
			}
		}

		// 商品分类
		if (isset($search['category_id']) && !empty($search['category_id']))
		{
			$category_id = IFilter::act($search['category_id'], 'int');
			if (0 < $category_id)
			{
				$where[] = "ce.category_id='$category_id' ";
			}
		}

		// 商品状态
		if (isset($search['is_del']) && '' != $search['is_del'])
		{
			$is_del = IFilter::act($search['is_del'], 'int');
			if (in_array($is_del, array(0,2,3)))
			{
				$where[] = "go.is_del='$is_del' ";
			}
		}

		// 商品库存
		if (isset($search['store_nums']) && '' != $search['store_nums'])
		{
			$store_nums = IFilter::act($search['store_nums'], 'int');
			switch ($store_nums)
			{
				case 0:
					// 无货
					$where[] = "go.store_nums<1 ";
					break;
				case 1:
					// 低于10
					$where[] = "go.store_nums>=1 and go.store_nums<10 ";
					break;
				case 2:
					// 10-100
					$where[] = "go.store_nums>=10 and go.store_nums<100 ";
					break;
				case 3:
					// 100以上
					$where[] = "go.store_nums>100 ";
					break;
			}
		}

		// 商品品牌
		if (isset($search['brand_id']) && '' != $search['brand_id'])
		{
			$brand_id = IFilter::act($search['brand_id'], 'int');
			if (0 < $brand_id)
			{
				$where[] = "go.brand_id='$brand_id' ";
			}
		}

		// 商品价格
		if (isset($search['sell_price_start']) && '' != $search['sell_price_start'])
		{
			$sell_price_start = IFilter::act($search['sell_price_start'], 'float');
		}
		else
		{
			$sell_price_start = 0;
		}
		if (isset($search['sell_price_end']) && '' != $search['sell_price_end'])
		{
			$sell_price_end = IFilter::act($search['sell_price_end'], 'float');
		}
		else
		{
			$sell_price_end = 0;
		}
		if (0 == $sell_price_start && 0 == $sell_price_end)
		{
			// 无效条件
		}
		else
		{
			if ($sell_price_start == $sell_price_end)
			{
				$where[] = "go.sell_price='$sell_price_start' ";
			}
			elseif ($sell_price_start > $sell_price_end)
			{
				$where[] = "go.sell_price between $sell_price_end and $sell_price_start ";
			}
			else
			{
				$where[] = "go.sell_price between $sell_price_start and $sell_price_end ";
			}
		}

		// 创建时间
		if (isset($search['create_time_start']) && '' != $search['create_time_start'])
		{
			$create_time_start = IFilter::act($search['create_time_start'], 'string');
			// 验证日期
			$is_check_create_time_start = ITime::checkDateTime($create_time_start);
		}
		else
		{
			$is_check_create_time_start = false;
		}
		if (isset($search['create_time_end']) && '' != $search['create_time_end'])
		{
			$create_time_end = IFilter::act($search['create_time_end'], 'string');
			// 验证日期
			$is_check_create_time_end = ITime::checkDateTime($create_time_end);
		}
		else
		{
			$is_check_create_time_end = false;
		}
		if ($is_check_create_time_start && $is_check_create_time_end)
		{
			if ($create_time_start == $create_time_end)
			{
				$where[] = "go.create_time between '".$create_time_start." 00:00:00' and '".$create_time_start." 23:59:59' ";
			}
			else
			{
				$difference = ITime::getDiffSec($create_time_start.' 00:00:00', $create_time_end.' 00:00:00');
				if (0 < $difference)
				{
					$where[] = "go.create_time between '".$create_time_end." 00:00:00' and '".$create_time_start." 23:59:59' ";
				}
				else
				{
					$where[] = "go.create_time between '".$create_time_start." 00:00:00' and '".$create_time_end." 23:59:59' ";
				}
			}
		}
		elseif ($is_check_create_time_start && false == $is_check_create_time_end)
		{
			$where[] = "go.create_time between '".$create_time_start." 00:00:00' and '".$create_time_start." 23:59:59' ";
		}
		elseif (false == $is_check_create_time_start && $is_check_create_time_end)
		{
			$where[] = "go.create_time between '".$create_time_end." 00:00:00' and '".$create_time_end." 23:59:59' ";
		}
		else
		{
			// 无效条件
		}

		return implode(" and ", $where);
	}

	/**
	 * 订单检索
	 * @param array $search 条件数组
	 * @return string 拼接的WHERE条件语句
	 */
	public static function orderSearch($search)
	{
		if (!$search)
		{
			return '';
		}
		$where = array();

		// 订单号
		if (isset($search['order_no']) && '' != $search['order_no'])
		{
			$order_no = IFilter::act($search['order_no'], 'string');
			if ('' != $order_no)
			{
				$where[] = "o.order_no='$order_no' ";
			}
		}

		// 收货人
		if (isset($search['accept_name']) && '' != $search['accept_name'])
		{
			$accept_name = IFilter::act($search['accept_name'], 'string');
			if ('' != $accept_name)
			{
				$where[] = "o.accept_name='$accept_name' ";
			}
		}

		// 支付状态
		if (isset($search['pay_status']) && '' != $search['pay_status'])
		{
			$pay_status = IFilter::act($search['pay_status'], 'int');
			if (in_array($pay_status, array(0,1,2)))
			{
				$where[] = "o.pay_status='$pay_status' ";
			}
		}

		// 发货状态
		if (isset($search['distribution_status']) && '' != $search['distribution_status'])
		{
			$distribution_status = IFilter::act($search['distribution_status'], 'int');
			if (in_array($distribution_status, array(0,1,2)))
			{
				$where[] = "o.distribution_status='$distribution_status' ";
			}
		}

		// 订单状态
		if (isset($search['status']) && '' != $search['status'])
		{
			$status = IFilter::act($search['status'], 'int');
			if (in_array($status, array(1,2,3,4,5)))
			{
				$where[] = "o.status='$status' ";
			}
		}

		// 订单总额
		if (isset($search['order_amount_start']) && '' != $search['order_amount_start'])
		{
			$order_amount_start = IFilter::act($search['order_amount_start'], 'float');
		}
		else
		{
			$order_amount_start = 0;
		}
		if (isset($search['order_amount_end']) && '' != $search['order_amount_end'])
		{
			$order_amount_end = IFilter::act($search['order_amount_end'], 'float');
		}
		else
		{
			$order_amount_end = 0;
		}
		if (0 == $order_amount_start && 0 == $order_amount_end)
		{
			// 无效条件
		}
		else
		{
			if ($order_amount_start == $order_amount_end)
			{
				$where[] = "o.order_amount='$order_amount_start' ";
			}
			elseif ($order_amount_start > $order_amount_end)
			{
				$where[] = "o.order_amount between $order_amount_end and $order_amount_start ";
			}
			else
			{
				$where[] = "o.order_amount between $order_amount_start and $order_amount_end ";
			}
		}

		// 下单时间
		if (isset($search['create_time_start']) && '' != $search['create_time_start'])
		{
			$create_time_start = IFilter::act($search['create_time_start'], 'string');
			// 验证日期
			$is_check_create_time_start = ITime::checkDateTime($create_time_start);
		}
		else
		{
			$is_check_create_time_start = false;
		}
		if (isset($search['create_time_end']) && '' != $search['create_time_end'])
		{
			$create_time_end = IFilter::act($search['create_time_end'], 'string');
			// 验证日期
			$is_check_create_time_end = ITime::checkDateTime($create_time_end);
		}
		else
		{
			$is_check_create_time_end = false;
		}
		if ($is_check_create_time_start && $is_check_create_time_end)
		{
			if ($create_time_start == $create_time_end)
			{
				$where[] = "o.create_time between '".$create_time_start." 00:00:00' and '".$create_time_start." 23:59:59' ";
			}
			else
			{
				$difference = ITime::getDiffSec($create_time_start.' 00:00:00', $create_time_end.' 00:00:00');
				if (0 < $difference)
				{
					$where[] = "o.create_time between '".$create_time_end." 00:00:00' and '".$create_time_start." 23:59:59' ";
				}
				else
				{
					$where[] = "o.create_time between '".$create_time_start." 00:00:00' and '".$create_time_end." 23:59:59' ";
				}
			}
		}
		elseif ($is_check_create_time_start && false == $is_check_create_time_end)
		{
			$where[] = "o.create_time between '".$create_time_start." 00:00:00' and '".$create_time_start." 23:59:59' ";
		}
		elseif (false == $is_check_create_time_start && $is_check_create_time_end)
		{
			$where[] = "o.create_time between '".$create_time_end." 00:00:00' and '".$create_time_end." 23:59:59' ";
		}
		else
		{
			// 无效条件
		}

		// 发货时间
		if (isset($search['send_time_start']) && '' != $search['send_time_start'])
		{
			$send_time_start = IFilter::act($search['send_time_start'], 'string');
			// 验证日期
			$is_check_send_time_start = ITime::checkDateTime($send_time_start);
		}
		else
		{
			$is_check_send_time_start = false;
		}
		if (isset($search['send_time_end']) && '' != $search['send_time_end'])
		{
			$send_time_end = IFilter::act($search['send_time_end'], 'string');
			// 验证日期
			$is_check_send_time_end = ITime::checkDateTime($send_time_end);
		}
		else
		{
			$is_check_send_time_end = false;
		}
		if ($is_check_send_time_start && $is_check_send_time_end)
		{
			if ($send_time_start == $send_time_end)
			{
				$where[] = "o.send_time between '".$send_time_start." 00:00:00' and '".$send_time_start." 23:59:59' ";
			}
			else
			{
				$difference = ITime::getDiffSec($send_time_start.' 00:00:00', $send_time_end.' 00:00:00');
				if (0 < $difference)
				{
					$where[] = "o.send_time between '".$send_time_end." 00:00:00' and '".$send_time_start." 23:59:59' ";
				}
				else
				{
					$where[] = "o.send_time between '".$send_time_start." 00:00:00' and '".$send_time_end." 23:59:59' ";
				}
			}
		}
		elseif ($is_check_send_time_start && false == $is_check_send_time_end)
		{
			$where[] = "o.send_time between '".$send_time_start." 00:00:00' and '".$send_time_start." 23:59:59' ";
		}
		elseif (false == $is_check_send_time_start && $is_check_send_time_end)
		{
			$where[] = "o.send_time between '".$send_time_end." 00:00:00' and '".$send_time_end." 23:59:59' ";
		}
		else
		{
			// 无效条件
		}

		// 完成时间
		if (isset($search['completion_time_start']) && '' != $search['completion_time_start'])
		{
			$completion_time_start = IFilter::act($search['completion_time_start'], 'string');
			// 验证日期
			$is_check_completion_time_start = ITime::checkDateTime($completion_time_start);
		}
		else
		{
			$is_check_completion_time_start = false;
		}
		if (isset($search['completion_time_end']) && '' != $search['completion_time_end'])
		{
			$completion_time_end = IFilter::act($search['completion_time_end'], 'string');
			// 验证日期
			$is_check_completion_time_end = ITime::checkDateTime($completion_time_end);
		}
		else
		{
			$is_check_completion_time_end = false;
		}
		if ($is_check_completion_time_start && $is_check_completion_time_end)
		{
			if ($completion_time_start == $completion_time_end)
			{
				$where[] = "o.completion_time between '".$completion_time_start." 00:00:00' and '".$completion_time_start." 23:59:59' ";
			}
			else
			{
				$difference = ITime::getDiffSec($completion_time_start.' 00:00:00', $completion_time_end.' 00:00:00');
				if (0 < $difference)
				{
					$where[] = "o.completion_time between '".$completion_time_end." 00:00:00' and '".$completion_time_start." 23:59:59' ";
				}
				else
				{
					$where[] = "o.completion_time between '".$completion_time_start." 00:00:00' and '".$completion_time_end." 23:59:59' ";
				}
			}
		}
		elseif ($is_check_completion_time_start && false == $is_check_completion_time_end)
		{
			$where[] = "o.completion_time between '".$completion_time_start." 00:00:00' and '".$completion_time_start." 23:59:59' ";
		}
		elseif (false == $is_check_completion_time_start && $is_check_completion_time_end)
		{
			$where[] = "o.completion_time between '".$completion_time_end." 00:00:00' and '".$completion_time_end." 23:59:59' ";
		}
		else
		{
			// 无效条件
		}

		return implode(" and ", $where);
	}
}