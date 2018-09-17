<?php
/**
 * @brief 商品采集器与iwebshop外观模式
 * @author chendeshan
 * @date 2014/1/2 8:25:15
 */
class collect_facade
{
	//错误信息
	public static $error = '';

	//插入的商品分类id数组
	public static $category = array();

	//采集实例化
	private static $instance = null;

	//商家ID
	public static $seller_id = 0;

	//创建采集
	public static function createInstance()
	{
		if(self::$instance == null)
		{
			$pluginFile = IWeb::$app->getBasePath().'plugins/collect/collect.php';
			if(is_file($pluginFile))
			{
				include_once($pluginFile);
				self::$instance = new collect();
			}
			else
			{
				self::$error = '当前版本没有此功能 <a href="http://www.aircheng.com/buy" target="_blank">立即购买</a>';
			}
		}
		return self::$instance;
	}

	/**
	 * @brief 处理接口采集后的返回值
	 * @param $result array('result' => 'success' or 'fail','data' => array(采集数据),'msg' => '错误消息')
	 * @return string or false
	 */
	private static function response($result)
	{
		if(isset($result['result']) && $result['result'] == 'success')
		{
			return $result['data'];
		}
		else
		{
			$msg = isset($result['msg']) ? $result['msg'] : '没有采集到数据信息';
			self::$error = $msg;
			return false;
		}
	}

	/**
	 * @brief 运行采集列表功能
	 * @param string $url 采集器url地址
	 * @param int $start 开始采集的商品位置
	 * @param int $end 截止采集的商品位置
	 */
	public static function many($url,$start = 1,$end = 5)
	{
		if($instance = self::createInstance())
		{
			$goodsData = $instance->collect($url,$start,$end);
			$result    = self::response($goodsData);
			if($result)
			{
				return self::insert($result);
			}
		}
		return array('result' => 'fail','msg' => self::$error);
	}

	/**
	 * @brief 采集商品详情页面信息
	 * @param string $url 商品详情地址
	 * @return array('result' => success or fail,'msg' => '错误信息','data' => 数据信息)
	 */
	public static function runDetail($url)
	{
		if($instance = self::createInstance())
		{
			$goodsData = $instance->collect($url);
			$result    = self::response($goodsData);

			if($result)
			{
				return array('result' => 'success','data' => stripcslashes($result['content']));
			}
		}
		return array('result' => 'fail','msg' => self::$error);
	}

	/**
	 * @brief 采集单个商品信息
	 * @param string $url 商品详情地址
	 * @return array('result' => success or fail,'msg' => '错误信息','data' => 数据信息)
	 */
	public static function once($url)
	{
		if($instance = self::createInstance())
		{
			$goodsData = $instance->collect($url);
			$result    = self::response($goodsData);
			if($result)
			{
				return self::insert(array($result));
			}
		}
		return array('result' => 'fail','msg' => self::$error);
	}

	/**
	 * @brief 把采集的结果插入到商城系统中
	 * @param array $collectResult 采集后的结果集
	 * array( 'goods_no' => '商品编号','up_time' => '上架时间','weight' => '重量','unit' => '计量单位','name' => '商品名字','price' => '商品价格','marketprice' => '市场价格','img' => array(商品图片),'content' => '商品详情';
	 */
	private static function insert($collectResult)
	{
		//实例化对象
		$catObj    = new IModel('category');
		$catExtObj = new IModel('category_extend');
		$attrObj   = new IModel('attribute');
		$goodsObj  = new IModel('goods');
		$goodsAttrObj = new IModel('goods_attribute');
		$photoObj     = new IModel('goods_photo');
		$photoRelObj  = new IModel('goods_photo_relation');

		//处理商品数据
		foreach($collectResult as $key => $val)
		{
			//商品添加
			$goodsObj->setData(array(
				'name'         => IFilter::act($val['name']),
				'sell_price'   => $val['price'],
				'market_price' => $val['marketprice'],
				'goods_no'     => IFilter::act($val['goods_no']),
				'up_time'      => $val['up_time'],
				'content'      => $val['content'],
				'store_nums'   => 100,
				'weight'       => $val['weight'],
				'unit'         => $val['unit'],
				'create_time'  => date('Y-m-d H:i:s'),
				'seller_id'    => self::$seller_id,
			));
			$goods_id = $goodsObj->add();

			//商品图片拷贝
			if(isset($val['img']) && $val['img'])
			{
				foreach($val['img'] as $img)
				{
					$md5Val     = md5_file($img);
					$photoData  = $photoObj->getObj('id = "'.$md5Val.'"');

					//如果图库中没有图片数据就要拷贝
					if(!$photoData)
					{
						$destFile   = PhotoUpload::hashDir().'/'.basename($img);
						$copyResult = IFile::copy($img,IWeb::$app->getBasePath()."/".$destFile);
						if($copyResult)
						{
							$photoObj->setData(array('id' => $md5Val,'img' => $destFile));
							$photoObj->add();
						}
					}

					//商品图片关联
					$photoRelObj->setData(array('goods_id' => $goods_id,'photo_id' => $md5Val));
					$photoRelObj->add();
				}

				$imgVal = isset($destFile) ? $destFile : $photoData['img'];
				$goodsObj->setData(array('img' => $imgVal));
				$goodsObj->update('id = '.$goods_id);
			}

			//商品与商品分类关联
			if(self::$category)
			{
				foreach(self::$category as $catId)
				{
					$catExtObj->setData(array('goods_id' => $goods_id,'category_id' => $catId));
					$catExtObj->add();
				}
			}
		}
		return array('result' => 'success');
	}
}

abstract class collectBase
{
	/**
	 * @brief 采集商品信息
	 * @param string $url 采集器url地址
	 * @param int $start 开始采集的商品位置
	 * @param int $end 截止采集的商品位置
	 * @return array( 'goods_no' => '商品编号','up_time' => '上架时间','weight' => '重量','unit' => '计量单位','name' => '商品名字','price' => '商品价格','marketprice' => '市场价格','img' => array(商品图片),'content' => '商品详情';
	 */
	abstract public function collect($url,$start = 1,$end = 5);
}