<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file goods_class.php
 * @brief 商品管理类库
 * @author nswe
 * @date 2014/8/18 11:53:43
 * @version 2.6
 */
class goods_class
{
	//算账类库
	private static $countsumInstance = null;

	//商户ID
	public $seller_id = '';

	//构造函数
	public function __construct($seller_id = '')
	{
		$this->seller_id = $seller_id;
	}
	/**
	 * 获取商品价格
	 * @param int $goods_id 商品ID
	 * @param float $sell_price 商品销售价
	 */
	public static function price($goods_id,$sell_price)
	{
		if(self::$countsumInstance == null)
		{
			self::$countsumInstance = new CountSum();
		}
		$price = self::$countsumInstance->getGroupPrice($goods_id);
		return $price ? $price : $sell_price;
	}

	/**
	 * 生成商品货号
	 * @return string 货号
	 */
	public static function createGoodsNo()
	{
		$config = new Config('site_config');
		return $config->goods_no_pre.time().rand(10,99);
	}

	/**
	 * @brief 修改商品数据
	 * @param int $id 商品ID
	 * @param array $postData 商品所需数据,键名分为"_"前缀和非"_"前缀，非"_"前缀的是goods表的字段
	 */
	public function update($id,$postData)
	{
		$goodsUpdateData = array();//更新到goods表的字段数据
		$nowDataTime = ITime::getDateTime();

		foreach($postData as $key => $val)
		{

			//数据过滤分组
			if(strpos($key,'attr_id_') !== false)
			{
				$goodsAttrData[ltrim($key,'attr_id_')] = IFilter::act($val);
			}
			//对应goods表字段
			else if($key[0] != '_')
			{
				$goodsUpdateData[$key] = IFilter::addSlash($val);
			}
		}

		//商家发布商品默认设置
		if($this->seller_id)
		{
			$goodsUpdateData['seller_id'] = $this->seller_id;
			$goodsUpdateData['is_del'] = $goodsUpdateData['is_del'] == 2 ? 2 : 3;

			//如果商户是VIP则无需审核商品
			if($goodsUpdateData['is_del'] == 3)
			{
				$sellerDB = new IModel('seller');
				$sellerRow= $sellerDB->getObj('id = '.$this->seller_id);
				if($sellerRow['is_vip'] == 1)
				{
					$goodsUpdateData['is_del'] = 0;
				}
			}
		}

		//上架或者下架处理
		if(isset($goodsUpdateData['is_del']))
		{
			//上架
			if($goodsUpdateData['is_del'] == 0)
			{
				$goodsUpdateData['up_time']   = $nowDataTime;
				$goodsUpdateData['down_time'] = null;
			}
			//下架
			else if($goodsUpdateData['is_del'] == 2)
			{
				$goodsUpdateData['up_time']  = null;
				$goodsUpdateData['down_time']= $nowDataTime;
			}
			//审核或者删除
			else
			{
				$goodsUpdateData['up_time']   = null;
				$goodsUpdateData['down_time'] = null;
			}
		}

		//是否存在货品
		$goodsUpdateData['spec_array'] = '';
		if(isset($postData['_spec_array']))
		{
			//生成goods中的spec_array字段数据
			$goods_spec_array = array();
			foreach($postData['_spec_array'] as $key => $val)
			{
				foreach($val as $v)
				{
					$tempSpec = JSON::decode($v);
					if(!isset($goods_spec_array[$tempSpec['id']]))
					{
						$goods_spec_array[$tempSpec['id']] = array('id' => $tempSpec['id'],'name' => $tempSpec['name'],'type' => $tempSpec['type'],'value' => array());
					}
					if(!in_array(array($tempSpec['tip'] => $tempSpec['value']),$goods_spec_array[$tempSpec['id']]['value']))
					{
						$goods_spec_array[$tempSpec['id']]['value'][] = array($tempSpec['tip'] => $tempSpec['value']);
				}
			}
			}
			$goodsUpdateData['spec_array'] = IFilter::addSlash(JSON::encode($goods_spec_array));
		}

		//用sell_price最小的货品填充商品表
		$defaultKey = array_search(min($postData['_sell_price']),$postData['_sell_price']);
		//赋值goods表默认数据
		$goodsUpdateData['goods_no']     = isset($postData['_goods_no'][$defaultKey])     ? $postData['_goods_no'][$defaultKey]     : '';
		$goodsUpdateData['market_price'] = isset($postData['_market_price'][$defaultKey]) ? $postData['_market_price'][$defaultKey] : 0;
		$goodsUpdateData['sell_price']   = isset($postData['_sell_price'][$defaultKey])   ? $postData['_sell_price'][$defaultKey]   : 0;
		$goodsUpdateData['cost_price']   = isset($postData['_cost_price'][$defaultKey])   ? $postData['_cost_price'][$defaultKey]   : 0;
		$goodsUpdateData['weight']       = isset($postData['_weight'][$defaultKey])       ? $postData['_weight'][$defaultKey]       : 0;
		$goodsUpdateData['store_nums']   = array_sum($postData['_store_nums']);

		//dyg_jzw 20170220 增加sku购买增加积分功能
		$goodsUpdateData['point']       = isset($postData['_point'][$defaultKey])       ? $postData['_point'][$defaultKey]       : 0;

		//dyg_jzw 20160316 跨境电商商品的海关数据
		$goodsUpdateData['cbe_goodsno']     = isset($postData['cbe_goodsno'])     ? $postData['cbe_goodsno']     : '';
		$goodsUpdateData['cbe_unitno']     = isset($postData['cbe_unitno'])     ? $postData['cbe_unitno']     : '';
		$goodsUpdateData['cbe_taxes']     = isset($postData['cbe_taxes'])     ? $postData['cbe_taxes']     : '';
		$goodsUpdateData['cbe_gcode']     = isset($postData['cbe_gcode'])     ? $postData['cbe_gcode']     : '';
		$goodsUpdateData['cbe_gmodel']     = isset($postData['cbe_gmodel'])     ? $postData['cbe_gmodel']     : '';
		$goodsUpdateData['cbe_ciqgmodel']     = isset($postData['cbe_ciqgmodel'])     ? $postData['cbe_ciqgmodel']     : '';
		$goodsUpdateData['cbe_country']     = isset($postData['cbe_country'])     ? $postData['cbe_country']     : '';

		//dyg_jzw 20150819 增加返利设置
		$goodsUpdateData['commission'] = json_encode( isset($postData['commission']) ? $postData['commission'] : NULL);

		//处理商品
		$goodsDB = new IModel('goods');
		if($id)
		{
			$goodsDB->setData($goodsUpdateData);

			$where = " id = {$id} ";
			if($this->seller_id)
			{
				$where .= " and seller_id = ".$this->seller_id;
			}

			if($goodsDB->update($where) === false)
			{
				$goodsDB->rollback();
				die("更新商品错误");
			}
		}
		else
		{
			$goodsUpdateData['create_time'] = $nowDataTime;
			$goodsDB->setData($goodsUpdateData);
			$id = $goodsDB->add();
			if(!$id)
			{
				$goodsDB->rollback();
				die("添加商品失败");
			}
		}

		//处理商品属性
		$goodsAttrDB = new IModel('goods_attribute');
		$goodsAttrDB->del('goods_id = '.$id);
		if($goodsUpdateData['model_id'] > 0 && isset($goodsAttrData) && $goodsAttrData)
		{
			foreach($goodsAttrData as $key => $val)
			{
				$attrData = array(
					'goods_id' => $id,
					'model_id' => $goodsUpdateData['model_id'],
					'attribute_id' => $key,
					'attribute_value' => is_array($val) ? join(',',$val) : $val
				);
				$goodsAttrDB->setData($attrData);
				$goodsAttrDB->add();
			}
		}

		//是否存在货品
		$productsDB = new IModel('products');
		$productsDB->del('goods_id = '.$id);
		if(isset($postData['_spec_array']))
		{
			$productIdArray = array();

			//创建货品信息
			foreach($postData['_goods_no'] as $key => $rs)
			{
				$productsData = array(
					'goods_id' => $id,
					'products_no' => $postData['_goods_no'][$key],
					'store_nums' => $postData['_store_nums'][$key],
					'market_price' => $postData['_market_price'][$key],
					'sell_price' => $postData['_sell_price'][$key],
					'cost_price' => $postData['_cost_price'][$key],
					'weight' => $postData['_weight'][$key],
					'point'	=> $postData['_point'][$key], //dyg_jzw 20170220 sku购买赠送积分
					'spec_array' => "[".join(',',IFilter::addSlash($this->specArraySort($postData['_spec_array'][$key])))."]"
				);
				$productsDB->setData($productsData);
				$productIdArray[$key] = $productsDB->add();
			}
		}

		//处理商品分类
		$categoryDB = new IModel('category_extend');
		$categoryDB->del('goods_id = '.$id);
		if(isset($postData['_goods_category']) && $postData['_goods_category'])
		{
			foreach($postData['_goods_category'] as $item)
			{
				$categoryDB->setData(array('goods_id' => $id,'category_id' => $item));
				$categoryDB->add();
			}
		}

		//处理商品促销
		$commendDB = new IModel('commend_goods');
		$commendDB->del('goods_id = '.$id);
		if(isset($postData['_goods_commend']) && $postData['_goods_commend'])
		{
			foreach($postData['_goods_commend'] as $item)
			{
				$commendDB->setData(array('goods_id' => $id,'commend_id' => $item));
				$commendDB->add();
			}
		}

		//处理商品关键词
		keywords::add($goodsUpdateData['search_words']);

		//处理商品图片
		$photoRelationDB = new IModel('goods_photo_relation');
		$photoRelationDB->del('goods_id = '.$id);
		$postData['_imgList'] = trim($postData['_imgList'],',');
		if(isset($postData['_imgList']) && $postData['_imgList'])
		{
			$postData['_imgList'] = explode(",",$postData['_imgList']);
			$postData['_imgList'] = array_filter($postData['_imgList']);
			if($postData['_imgList'])
			{
			$photoDB = new IModel('goods_photo');
				foreach($postData['_imgList'] as $key => $val)
			{
					$photoPic = $photoDB->getObj('img = "'.$val.'"','id');
					if($photoPic)
				{
						$photoRelationDB->setData(array('goods_id' => $id,'photo_id' => $photoPic['id']));
					$photoRelationDB->add();
					}
				}
			}
		}

		//处理会员组的价格
		$groupPriceDB = new IModel('group_price');
		$groupPriceDB->del('goods_id = '.$id);

		//dyg_jzw 20161214 会员组返还后的真正价格
		$groupRealPriceDB = new IModel('group_real_price');
		$groupRealPriceDB->del('goods_id = '.$id);

		if(isset($productIdArray) && $productIdArray)
		{
			foreach($productIdArray as $index => $value)
			{
				if(isset($postData['_groupPrice'][$index]) && $postData['_groupPrice'][$index])
				{
					$temp = JSON::decode($postData['_groupPrice'][$index]);
					foreach($temp as $k => $v)
					{
						$groupPriceDB->setData(array(
							'goods_id' => $id,
							'product_id' => $value,
							'group_id' => $k,
							'price' => $v
						));
						$groupPriceDB->add();
					}
				}

				//dyg_jzw 20161214 增加会员组返还后的真正价格
				if(isset($postData['_groupRealPrice'][$index]) && $postData['_groupRealPrice'][$index])
				{
					$temp = JSON::decode($postData['_groupRealPrice'][$index]);
					foreach($temp as $k => $v)
					{
						$groupRealPriceDB->setData(array(
							'goods_id' => $id,
							'product_id' => $value,
							'group_id' => $k,
							'real_price' => $v
						));
						$groupRealPriceDB->add();
					}
				}
			}
		}
		else
		{
			if(isset($postData['_groupPrice'][0]) && $postData['_groupPrice'][0])
			{
				$temp = JSON::decode($postData['_groupPrice'][0]);
				foreach($temp as $k => $v)
				{
					$groupPriceDB->setData(array(
						'goods_id' => $id,
						'group_id' => $k,
						'price' => $v
					));
					$groupPriceDB->add();
				}
			}

			//dyg_jzw 20161214 增加会员组返还后的真正价格
			if(isset($postData['_groupRealPrice'][0]) && $postData['_groupRealPrice'][0])
			{
				$temp = JSON::decode($postData['_groupRealPrice'][0]);
				foreach($temp as $k => $v)
				{
					$groupRealPriceDB->setData(array(
						'goods_id' => $id,
						'group_id' => $k,
						'real_price' => $v
					));
					$groupRealPriceDB->add();
				}
			}
		}
		return $id;
	}

	/**
	* @brief 删除与商品相关表中的数据
	*/
	public function del($goods_id)
	{
		$goodsWhere = " id = '{$goods_id}' ";
		if($this->seller_id)
		{
			$goodsWhere .= " and seller_id = ".$this->seller_id;
		}

		//图片清理
		$tb_photo_relation = new IModel('goods_photo_relation');
		$photoMD5Data      = $tb_photo_relation->query('goods_id = '.$goods_id);

		$tb_photo          = new IModel('goods_photo');
		foreach($photoMD5Data as $key => $md5)
		{
			//图片是否被其他商品共享占用
			$isUserd = $tb_photo_relation->getObj('photo_id = "'.$md5['photo_id'].'" and goods_id != '.$goods_id);
			if(!$isUserd)
			{
				$imgData = $tb_photo->getObj('id = "'.$md5['photo_id'].'"');
				isset($imgData['img']) ? IFile::unlink($imgData['img']) : "";
				$tb_photo->del('id = "'.$md5['photo_id'].'"');
			}
		}
		$tb_photo_relation->del('goods_id = '.$goods_id);

		//删除商品表
		$tb_goods = new IModel('goods');
		$goodsRow = $tb_goods->getObj($goodsWhere,"content");
		if(isset($goodsRow['content']) && $goodsRow['content'])
		{

		}
		$tb_goods ->del($goodsWhere);
	}
	/**
	 * 获取编辑商品所有数据
	 * @param int $id 商品ID
	 */
	public function edit($id)
	{
		$id = IFilter::act($id,'int');
		$goodsWhere = " id = {$id} ";
		if($this->seller_id)
		{
			$goodsWhere .= " and seller_id = ".$this->seller_id;
		}

		//获取商品
		$obj_goods = new IModel('goods');
		$goods_info = $obj_goods->getObj($goodsWhere);

		if(!$goods_info)
		{
			return null;
		}

		//获取商品的会员价格
		$groupPriceDB = new IModel('group_price');
		$goodsPrice   = $groupPriceDB->query("goods_id = ".$goods_info['id']." and product_id is NULL ");
		$temp = array();
		foreach($goodsPrice as $key => $val)
		{
			$temp[$val['group_id']] = $val['price'];
		}
		$goods_info['groupPrice'] = $temp ? JSON::encode($temp) : '';

		//dyg_jzw 20161214获取商品的会员返还后真正价格
		$groupRealPriceDB = new IModel('group_real_price');
		$goodsRealPrice   = $groupRealPriceDB->query("goods_id = ".$goods_info['id']." and product_id is NULL ");
		$temp = array();
		foreach($goodsRealPrice as $key => $val)
		{
			$temp[$val['group_id']] = $val['real_price'];
		}
		$goods_info['groupRealPrice'] = $temp ? JSON::encode($temp) : '';

		//赋值到FORM用于渲染
		$data = array('form' => $goods_info);

		//获取货品
		$productObj = new IModel('products');
		$product_info = $productObj->query('goods_id = '.$id);
		if($product_info)
		{
			//获取货品会员价格
			foreach($product_info as $k => $rs)
			{
				$temp = array();
				$productPrice = $groupPriceDB->query('product_id = '.$rs['id']);
				foreach($productPrice as $key => $val)
				{
					$temp[$val['group_id']] = $val['price'];
				}
				$product_info[$k]['groupPrice'] = $temp ? JSON::encode($temp) : '';

				//dyg_jzw 20161214获取商品的会员返还后真正价格
				$temp = array();
				$productRealPrice = $groupRealPriceDB->query('product_id = '.$rs['id']);
				foreach($productRealPrice as $key => $val)
				{
					$temp[$val['group_id']] = $val['real_price'];
				}
				$product_info[$k]['groupRealPrice'] = $temp ? JSON::encode($temp) : '';
			}
			$data['product'] = $product_info;
		}

		//加载推荐类型
		$tb_commend_goods = new IModel('commend_goods');
		$commend_goods = $tb_commend_goods->query('goods_id='.$goods_info['id'],'commend_id');
		if($commend_goods)
		{
			foreach($commend_goods as $value)
			{
				$data['goods_commend'][] = $value['commend_id'];
			}
		}

		//相册
		$tb_goods_photo = new IQuery('goods_photo_relation as ghr');
		$tb_goods_photo->join = 'left join goods_photo as gh on ghr.photo_id=gh.id';
		$tb_goods_photo->fields = 'gh.img';
		$tb_goods_photo->where = 'ghr.goods_id='.$goods_info['id'];
		$tb_goods_photo->order = 'ghr.id asc';
		$data['goods_photo'] = $tb_goods_photo->find();

		//扩展基本属性
		$goodsAttr = new IQuery('goods_attribute');
		$goodsAttr->where = "goods_id=".$goods_info['id']." and attribute_id != '' ";
		$attrInfo = $goodsAttr->find();
		if($attrInfo)
		{
			foreach($attrInfo as $item)
			{
				//key：属性名；val：属性值,多个属性值以","分割
				$data['goods_attr'][$item['attribute_id']] = $item['attribute_value'];
			}
		}

		//商品分类
		$categoryExtend = new IQuery('category_extend');
		$categoryExtend->where = 'goods_id = '.$goods_info['id'];
		$tb_goods_photo->fields = 'category_id';
		$cateData = $categoryExtend->find();
		if($cateData)
		{
			foreach($cateData as $item)
			{
				$data['goods_category'][] = $item['category_id'];
			}
		}
		return $data;
	}
	/**
	 * @param
	 * @return array
	 * @brief 无限极分类递归函数
	 */
	public static function sortdata($catArray, $id = 0 , $prefix = '')
	{
		static $formatCat = array();
		static $floor     = 0;

		foreach($catArray as $key => $val)
		{
			if($val['parent_id'] == $id)
			{
				$str         = self::nstr($prefix,$floor);
				$val['name'] = $str.$val['name'];

				$val['floor'] = $floor;
				$formatCat[]  = $val;

				unset($catArray[$key]);

				$floor++;
				self::sortdata($catArray, $val['id'] ,$prefix);
				$floor--;
			}
		}
		return $formatCat;
	}

	/**
	 * @brief 根据商品分类的父类ID进行数据归类
	 * @param array $categoryData 商品category表的结构数组
	 * @return array
	 */
	public static function categoryParentStruct($categoryData)
	{
		$result = array();
		foreach($categoryData as $key => $val)
		{
			if(isset($result[$val['parent_id']]) && is_array($result[$val['parent_id']]))
			{
				$result[$val['parent_id']][] = $val;
			}
			else
			{
				$result[$val['parent_id']] = array($val);
			}
		}
		return $result;
	}

	/**
	 * @brief 计算商品的价格区间
	 * @param $min          最小价格
	 * @param $max          最大价格
	 * @param $showPriceNum 展示分组最大数量
	 * @return array        价格区间分组
	 */
	public static function getGoodsPrice($min,$max,$showPriceNum = 5)
	{
		$goodsPrice = array("min" => $min,"max" => $max);
		if($goodsPrice['min'] == null && $goodsPrice['max'] == null)
		{
			return array();
		}

		if($goodsPrice['min'] <= 0)
		{
			$minPrice = 1;
			$result = array('0-'.$minPrice);
		}
		else
		{
			$minPrice = ceil($goodsPrice['min']);
			$result = array('1-'.$minPrice);
		}

		//商品价格计算
		$perPrice = ceil(($goodsPrice['max'] - $minPrice)/($showPriceNum - 1));

		if($perPrice > 0)
		{
			for($addPrice = $minPrice+1; $addPrice < $goodsPrice['max'];)
			{
				$stepPrice = $addPrice + $perPrice;
				$stepPrice = substr(intval($stepPrice),0,1).str_repeat('9',(strlen(intval($stepPrice)) - 1));
				$result[]  = $addPrice.'-'.$stepPrice;
				$addPrice  = $stepPrice + 1;
			}
		}
		return $result;
	}

	//处理商品列表显示缩进
	public static function nstr($str,$num=0)
	{
		$return = '';
		for($i=0;$i<$num;$i++)
		{
			$return .= $str;
		}
		return $return;
	}

	/**
	 * @brief  根据分类ID获取其全部父分类数据(自下向上的获取数据)
	 * @param  int   $catId  分类ID
	 * @return array $result array(array(父分类1_ID => 父分类2_NAME),....array(子分类ID => 子分类NAME))
	 */
	public static function catRecursion($catId)
	{
		$result = array();
		$catDB  = new IModel('category');
		$catRow = $catDB->getObj("id = '{$catId}'");
		while(true)
		{
			if($catRow)
			{
				array_unshift($result,array('id' => $catRow['id'],'name' => $catRow['name']));
				$catRow = $catDB->getObj('id <> 0 and id = '.$catRow['parent_id']); //dyg_jzw 20161214 不获取id为0的隐藏分类
			}
			else
			{
				break;
			}
		}
		return $result;
	}

	/**
	 * @brief 优先获取子分类，如果为空再获取其兄弟分类
	 * @param int $catId 分类ID
	 * @return array
	 */
	public static function catTree($catId)
	{
		$result    = array();
		$catDB     = new IModel('category');
		$childList = $catDB->query("parent_id = '{$catId}'","*","sort asc");
		if(!$childList)
		{
			$catRow = $catDB->getObj("id = '{$catId}'");
			$childList = $catDB->query('parent_id = '.$catRow['parent_id'],"*","sort asc");
		}
		return $childList;
	}

	/**
	 * @brief 获取子分类可以无限递归获取子分类
	 * @param int $catId 分类ID
	 * @param int $level 层级数
	 * @return string 所有分类的ID拼接字符串
	 */
	public static function catChild($catId,$level = 1)
	{
		if($level == 0)
		{
			return $catId;
		}

		$temp   = array();
		$result = array($catId);
		$catDB  = new IModel('category');

		while(true)
		{
			$id = current($result);
			if(!$id)
			{
				break;
			}
			$temp = $catDB->query('parent_id = '.$id);
			foreach($temp as $key => $val)
			{
				if(!in_array($val['id'],$result))
				{
					$result[] = $val['id'];
				}
			}
			next($result);
		}
		return join(',',$result);
	}

	/**
	 * @brief 返回商品状态
	 * @param int $is_del 商品状态
	 * @return string 状态文字
	 */
	public static function statusText($is_del)
	{
		$date = array('0' => '上架','1' => '删除','2' => '下架','3' => '等审');
		return isset($date[$is_del]) ? $date[$is_del] : '';
	}

	public static function getGoodsCategory($goods_id){

		$gcQuery         = new IQuery('category_extend as ce');
		$gcQuery->join   = "left join category as c on c.id = ce.category_id";
		$gcQuery->where  = "ce.goods_id = '{$goods_id}'";
		$gcQuery->fields = 'c.name';

		$gcList = $gcQuery->find();
		$strCategoryNames = '';
		foreach($gcList as $val){
			$strCategoryNames .= $val['name'] . ',';
		}
		unset($gcQuery,$gcList);
		return $strCategoryNames;
	}

	/**
	 * @brief 返回检索条件相关信息
	 * @param int $search 条件数组
	 * @return array 查询条件（$join,$where）数据组
	 */
	public static function getSearchCondition($search)
	{
		$join  = array();
		$where = array();

		//条件筛选处理
		if(isset($search['name']) && isset($search['keywords']))
		{
			$name     = IFilter::act($search['name'], 'string');
			$keywords = IFilter::act($search['keywords'], 'string');
			if($keywords)
			{
				if($name == "seller.true_name")
				{
					$sellerDB = new IModel('seller');
					$sellerRow= $sellerDB->getObj('true_name like "%'.$keywords.'%"');
					$seller_id= isset($sellerRow['id']) ? $sellerRow['id'] : "NULL";
					$where[]  = "go.seller_id = ".$seller_id;
				}
				else
				{
					$where[] = $name." like '%".$keywords."%'";
				}
			}
		}

		if(isset($search['category_id']))
		{
			$category_id = IFilter::act($search['category_id'], 'int');
			if (0 < $category_id)
			{
				$join[]  = "left join category_extend as ce on ce.goods_id = go.id";
				$where[] = "ce.category_id = ".$category_id;
			}
		}

		if(isset($search['is_del']) && $search['is_del'] !== '')
		{
			$is_del  = IFilter::act($search['is_del'], 'int');
			$where[] = "go.is_del = ".$is_del;
		}
		else
		{
			$where[] = "go.is_del != 1";
		}

		if(isset($search['store_nums']))
		{
			$store_nums = IFilter::act($search['store_nums'], 'string');
			if ('' != $store_nums)
			{
				$store_nums = htmlspecialchars_decode($store_nums);
				$where[] = $store_nums;
			}
		}

		if(isset($search['commend_id']))
		{
			$commend_id = IFilter::act($search['commend_id'], 'int');
			if (0 < $commend_id)
			{
				$join[] = "left join commend_goods as cg on go.id = cg.goods_id";
				$where[]= "cg.commend_id = ".$commend_id;
			}
		}

		if(isset($search['seller_id']))
		{
			$seller_id = IFilter::act($search['seller_id'], 'string');
			if ('' != $seller_id)
			{
				$where[] = "go.seller_id ".$seller_id;
			}
		}
		// 高级筛选
		if (isset($search['adv_search']) && 1 == $search['adv_search'])
		{
			if (isset($search['brand_id']) && !empty($search['brand_id']))
			{
				$brand_id = IFilter::act($search['brand_id'], 'int');
				$where[] = "go.brand_id = ".$brand_id;
			}
			if (isset($search['sell_price']) && !empty($search['sell_price']))
			{
				$sell_price = explode(",", $search['sell_price']);
				$sell_price_0 = IFilter::act($sell_price[0], 'float');
				if (isset($sell_price[1]))
				{
					$sell_price_1 = IFilter::act($sell_price[1], 'float');
				}
				else
				{
					$sell_price_1 = 0;
				}
				if ($sell_price_0 == $sell_price_1)
				{
					$where[] = "go.sell_price = $sell_price_0";
				}
				else if ($sell_price_0 > $sell_price_1)
				{
					$where[] = "go.sell_price between $sell_price_1 and $sell_price_0";
				}
				else
				{
					$where[] = "go.sell_price between $sell_price_0 and $sell_price_1";
				}
			}
			if (isset($search['create_time']) && !empty($search['create_time']))
			{
				$create_time = explode(",", $search['create_time']);
				// 验证日期
				$is_check_0 = ITime::checkDateTime($create_time[0]);
				$is_check_1 = false;
				if (isset($create_time[1]))
				{
					$is_check_1 = ITime::checkDateTime($create_time[1]);
				}
				if ($is_check_0 && $is_check_1)
				{
					// 是否相等
					if ($create_time[0] == $create_time[1])
					{
						$where[] = "go.create_time between '".$create_time[0]." 00:00:00' and '".$create_time[0]." 23:59:59'";
					}
					else
					{
						$difference = ITime::getDiffSec($create_time[0].' 00:00:00', $create_time[1].' 00:00:00');
						if (0 < $difference)
						{
							$where[] = "go.create_time between '".$create_time[1]." 00:00:00' and '".$create_time[0]." 23:59:59'";
						}
						else
						{
							$where[] = "go.create_time between '".$create_time[0]." 00:00:00' and '".$create_time[1]." 23:59:59'";
						}
					}
				}
				elseif ($is_check_0)
				{
					$where[] = "go.create_time between '".$create_time[0]." 00:00:00' and '".$create_time[0]." 23:59:59'";
				}
			}
		}
		$results = array(join("  ",$join),join(" and ",$where));
		unset($join,$where);
		return $results;
	}

	/**
	 * @brief 检查商品或者货品的库存是否充足
	 * @param $buy_num 检查数量
	 * @param $goods_id 商品id
	 * @param $product_id 货品id
	 * @result array() true:满足数量; false:不满足数量
	 */
	public static function checkStore($buy_num,$goods_id,$product_id = 0)
	{
		$data = $product_id ? Api::run('getProductInfo',array('#id#',$product_id)) : Api::run('getGoodsInfo',array('#id#',$goods_id));

		//库存判断
		if(!$data || $buy_num <= 0 || $buy_num > $data['store_nums'])
		{
			return false;
		}
		return true;
	}
	/**
	 * @brief 商品根据折扣更新价格
	 * @param string or int $goods_id 商品id
	 * @param float $discount 折扣
	 * @param string $discountType 打折的类型： percent 百分比, constant 常数
	 * @param string reduce or add 减少或者增加
	 */
	public static function goodsDiscount($goods_id,$discount,$discountType = "percent",$type = "reduce")
	{
		//减少
		if($type == "reduce")
		{
			if($discountType == "percent")
			{
				$updateData = array("sell_price" => "sell_price * ".$discount/100);
			}
			else
			{
				$updateData = array("sell_price" => "sell_price - ".$discount);
			}
		}
		//增加
		else
		{
			if($discountType == "percent")
			{
				$updateData = array("sell_price" => "sell_price / ".$discount/100);
			}
			else
			{
				$updateData = array("sell_price" => "sell_price + ".$discount);
			}
		}
		//更新商品
		$goodsDB = new IModel('goods');
		$goodsDB->setData($updateData);
		$goodsDB->update("id in (".$goods_id.")","sell_price");
		//更新货品
		$productDB = new IModel('products');
		$productDB->setData($updateData);
		$productDB->update("goods_id in (".$goods_id.")","sell_price");
	}

	/**
	 * @brief 批量修改商品数据
	 * @param array $idArray 商品ID数组
	 * @param array $paramData 商品设置数据
	 */
	public function multiUpdate($idArray,$paramData)
	{
		$goods_id   = implode(",", $idArray);
		$updateData = array();

		// 所属商户(只有管理员才可以设置)
		if ($this->seller_id == 0 && isset($paramData['sellerid']) && '-1' != $paramData['sellerid'])
		{
			$updateData['seller_id'] = IFilter::act($paramData['sellerid'], 'int');
		}
		// 市场价格
		$market_price = IFilter::act($paramData['market_price'], 'float');
		if (0 < $market_price)
		{
			$market_price_operator = $this->getOperator($paramData['market_price_type']);
			$updateData['market_price'] = "market_price".$market_price_operator.$market_price;
		}
		// 销售价格
		$sell_price = IFilter::act($paramData['sell_price'], 'float');
		if (0 < $sell_price)
		{
			$sell_price_operator = $this->getOperator($paramData['sell_price_type']);
			$updateData['sell_price'] = "sell_price".$sell_price_operator.$sell_price;
		}
		// 成本价格
		$cost_price = IFilter::act($paramData['cost_price'], 'float');
		if (0 < $cost_price)
		{
			$cost_price_operator = $this->getOperator($paramData['cost_price_type']);
			$updateData['cost_price'] = "cost_price".$cost_price_operator.$cost_price;
		}
		// 库存
		$store_nums = IFilter::act($paramData['store_nums'], 'int');
		if (0 < $store_nums)
		{
			$store_nums_operator = $this->getOperator($paramData['store_nums_type']);
			$updateData['store_nums'] = "store_nums".$store_nums_operator.$store_nums;
		}
		// 积分
		$point = IFilter::act($paramData['point'], 'int');
		if (0 < $point)
		{
			$point_operator = $this->getOperator($paramData['point_type']);
			$updateData['point'] = "point".$point_operator.$point;
		}
		// 经验
		$exp = IFilter::act($paramData['exp'], 'int');
		if (0 < $exp)
		{
			$exp_operator = $this->getOperator($paramData['exp_type']);
			$updateData['exp'] = "exp".$exp_operator.$exp;
		}
		// 商品品牌
		if ('-1' != $paramData['brand_id'])
		{
			$updateData['brand_id'] = IFilter::act($paramData['brand_id'], 'int');
		}

		// 批量更新商品
		if (!empty($updateData))
		{
			$except = array('market_price','sell_price','cost_price','store_nums','point','exp');
			$goodsDB = new IModel('goods');
			$goodsDB->setData($updateData);
			$where = "id in (".$goods_id.")";
			$where.= $this->seller_id ? " and seller_id = ".$this->seller_id : "";
			$result = $goodsDB->update($where,$except);
			// 批量更新货品表
			$exceptProducts = array('store_nums','market_price','sell_price','cost_price');
			$updateDataProducts = array();
			foreach ($updateData as $key => $value)
			{
				if (in_array($key, $exceptProducts))
				{
					$updateDataProducts[$key] = $value;
				}
			}
			if (0 < count($updateDataProducts))
			{
				$productsDB = new IModel('products');
				$productsDB->setData($updateDataProducts);
				$whereProducts = "goods_id in (".$goods_id.")";
				$resultProducts = $productsDB->update($whereProducts, $exceptProducts);
				$productObj = new IQuery('products as pro');
				$productObj->where = $whereProducts;
				$productObj->fields = "pro.goods_id, sum(pro.store_nums) AS sum_store_nums, min(pro.market_price) as min_market_price, min(pro.sell_price) as min_sell_price, min(pro.cost_price) as min_cost_price";
				$productObj->group = "pro.goods_id";
				$productList = $productObj->find();
				foreach ($productList as $key => $val)
				{
					$tempData = array(
						'store_nums' => $val['sum_store_nums'],
						'market_price' => $val['min_market_price'],
						'sell_price' => $val['min_sell_price'],
						'cost_price' => $val['min_cost_price']
					);
					$goodsDB->setData($tempData);
					$tempWhere = "id=".$val['goods_id']."";
					$tempResult = $goodsDB->update($tempWhere);
				}
			}
		}

		// 商品分类
		if (isset($paramData['category']) && !empty($paramData['category']))
		{
			$categoryDB = new IModel('category_extend');
			$categoryDB->del('goods_id in ('.$goods_id.')');
			$categoryArray = IFilter::act($paramData['category'], 'int');
			foreach ($idArray as $gid)
			{
				foreach ($categoryArray as $category_id)
				{
					$categoryDB->setData(array('goods_id' => $gid, 'category_id' => $category_id));
					$categoryDB->add();
				}
			}
		}
		return true;
	}

	/**
	 * @brief 获取运算符号
	 * @param string $type 	运算类型 1-增加 2-减少
	 * @return string 		运算符号
	 */
	protected function getOperator($type)
	{
		return '2'==$type ? '-' : '+';
	}
	//货品products表spec_array排序
	public function specArraySort($specArray)
	{
		foreach($specArray as $key => $value)
		{
			$value        = JSON::decode($value);
			$temp         = array(
				'id'    => $value['id'],
				'type'  => $value['type'],
				'value' => $value['value'],
				'name'  => $value['name'],
				'tip'   => $value['tip'],
			);
			$specArray[$key] = JSON::encode($temp);
		}
		return $specArray;
	}
}