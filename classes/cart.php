<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file cart.php
 * @brief 购物车类库
 * @author chendeshan
 * @date 2016/1/16 21:58:40
 * @version 4.3
 */

/**
 * @class Cart
 * @brief 购物车类库
 */
class Cart extends IInterceptorBase
{
	/*购物车简单cookie存储结构
	* array [goods]=>array(商品主键=>数量) , [product]=>array( 货品主键=>数量 )
	*/
	private $cartStruct = array( 'goods' => array() , 'product' => array() );

	/*购物车复杂存储结构
	* [id]   :array  商品id值;
	* [count]:int    商品数量;
	* [info] :array  商品信息 [goods]=>array( ['id']=>商品ID , ['data'] => array( [商品ID]=>array ( [sell_price]价格, [count]购物车中此商品的数量 ,[type]类型goods,product ,[goods_id]商品ID值 ) ) ) , [product]=>array( 同上 ) , [count]购物车商品和货品数量 , [sum]商品和货品总额 ;
	* [sum]  :int    商品总价格;
	*/
	private $cartExeStruct = array('goods' => array('id' => array(), 'data' => array() ),'product' => array( 'id' => array() , 'data' => array()),'count' => 0,'sum' => 0);

	//购物车名字前缀
	private $cartName    = 'shoppingcart';

	//购物车中最多容纳的数量
	private $maxCount    = 100;

	//错误信息
	private $error       = '';

	//购物车的存储方式
	private $saveType    = 'session';

	public  $user_id;


	/**
	 * 获取新加入购物车的数据
	 * @param $cartInfo cartStruct
	 * @param $gid 商品或者货品ID
	 * @param $num 数量
	 * @param $type goods 或者 product
	 */
	private function getUpdateCartData($cartInfo,$gid,$num,$type)
	{
		$gid = intval($gid);
		$num = intval($num);

		if($type != 'goods')
		{
			$type = 'product';
		}
		//获取基本的商品数据
		$goodsRow = $this->getGoodInfo($gid,$type);
		$normal_or_cbe = ''; //dyg_jzw 20161213 是否跨境商品
		if($goodsRow)
		{
			if ($goodsRow['is_cbe']) //dyg_jzw 20160304 跨境商品
			{
				$normal_or_cbe = 'cbe_ids';

				//购物车含有【非】跨境电商商品
				if (isset($cartInfo['normal_ids']) && count($cartInfo['normal_ids']) > 0)
				{
					$this->error = '购物车含【非全球购商品】，普通商品和全球购商品不能一起下单，请清空购物车并分开下单';
					return false;
				}

				//跨境电商购物车不可超过10个
				if (isset($cartInfo['cbe_ids']) && count($cartInfo['cbe_ids']) >= 10)
				{
					$this->error = '加入购物车失败, 海外购物单笔最多只能容纳10种商品, 请分开下单';
					return false;
				}
			}
			else //dyg_jzw 20160304 非跨境商品
			{
				$normal_or_cbe = 'normal_ids';

				//购物车含有跨境电商商品
				if (isset($cartInfo['cbe_ids']) && count($cartInfo['cbe_ids']) > 0)
				{
					$this->error = '购物车含【全球购商品】，普通商品和全球购商品不能一起下单，请分开下单';
					return false;
				}
			}

			//购物车中已经存在此类商品
			if(isset($cartInfo[$type][$gid]))
			{
				if($goodsRow['store_nums'] < $cartInfo[$type][$gid] + $num)
				{
					$this->error = '该商品库存不足';
					return false;
				}

				//dyg_jzw 20160223 修正可能出现负数量的情况
				if ($num + $cartInfo[$type][$gid] < 1) 
				{
					$cartInfo[$type][$gid] = 1;
				}
				else
				{
					$cartInfo[$type][$gid] += $num;
					$cartInfo[$normal_or_cbe][$gid] += $num; //dyg_jzw 20161213 跨境电商产品识别
				}
			}
			else //购物车中不存在此类商品
			{
				if($goodsRow['store_nums'] < $num)
				{

					$this->error = '该商品库存不足';
					return false;
				}
				//dyg_jzw 20160223 修正可能出现负数量的情况
				if ($num <= 0) 
				{
					$cartInfo[$type][$gid] = 1;
				}
				else
				{
					$cartInfo[$type][$gid] = $num;
					$cartInfo[$normal_or_cbe][$gid] = $num; //dyg_jzw 20161213 跨境电商产品识别
				}
			}

			return $cartInfo;
		}
		else
		{
			$this->error = '该商品库存不足';
			return false;
		}
	}

	/**
	 * @brief 将商品或者货品加入购物车
	 * @param $gid  商品或者货品ID值
	 * @param $num  购买数量
	 * @param $type 加入类型 goods商品; product:货品;
	 */
	public function add($gid, $num = 1 ,$type = 'goods')
	{
		//规格必填
		if($type == "goods")
		{
			$productsDB = new IModel('products');
			if($productsDB->getObj('goods_id = '.$gid))
			{
				$this->error = '请先选择商品的规格';
				return false;
			}
		}
		//购物车中已经存在此商品
		$cartInfo = $this->getMyCartStruct();

		if($this->getCartSort($cartInfo) >= $this->maxCount)
		{
			$this->error = '加入购物车失败,购物车中最多只能容纳'.$this->maxCount.'种商品';
			return false;
		}
		else
		{
			$cartInfo = $this->getUpdateCartData($cartInfo,$gid,$num,$type);
			if($cartInfo === false)
			{
				return false;
			}
			else
			{
				return $this->setMyCart($cartInfo);
			}
		}
	}

	//计算商品的种类
	private function getCartSort($mycart)
	{
		$sumSort   = 0;
		$sortArray = array('goods','product');

		foreach($sortArray as $sort)
		{
			if(isset($mycart[$sort]))
			{
			$sumSort += count($mycart[$sort]);
			}
		}
		return $sumSort;
	}

	//删除商品
	public function del($gid , $type = 'goods')
	{
		$cartInfo = $this->getMyCartStruct();
		if($type != 'goods')
		{
			$type = 'product';
		}

		//删除商品数据
		if(isset($cartInfo[$type][$gid]))
		{
			unset($cartInfo[$type][$gid]);
			
			//dyg_jzw 20160307 删除是否海外商品标记
			unset($cartInfo['cbe_ids'][$gid]);
			unset($cartInfo['normal_ids'][$gid]);
			$this->setMyCart($cartInfo);
		}
		else
		{
			$this->error = '购物车中没有此商品';
			return false;
		}
	}

	//根据 $gid 获取商品信息
	private function getGoodInfo($gid, $type = 'goods')
	{
		$dataArray = array();
		//商品方式
		if($type == 'goods')
		{
			$goodsObj  = new IModel('goods');
			$dataArray = $goodsObj->getObj('id = '.$gid.' and is_del = 0','id as goods_id, sell_price, store_nums, is_cbe'); //dyg_jzw 20160304 增加跨境电商标记-is_cbe
			if($dataArray)
			{
				$dataArray['id'] = $dataArray['goods_id'];
			}
		}

		//货品方式
		else
		{
			$productObj = new IQuery('products as pro , goods as go');
			$productObj->fields = ' go.id as goods_id, go.is_cbe, pro.sell_price, pro.store_nums, pro.id '; //dyg_jzw 20160304 增加跨境电商标记-is_cbe
			$productObj->where  = ' pro.id = '.$gid.' and go.is_del = 0 and pro.goods_id = go.id';
			$productRow = $productObj->find();
			if($productRow)
			{
				$dataArray = $productRow[0];
			}
		}

		return $dataArray;
	}

	/**
	 * 获取当前购物车简单信息
	 * @return 获取cartStruct数据结构
	 * dyg_jzw 20160304 修改购物车结构，增加跨境电商商品标记
	 */
	private function getMyCartStruct()
	{
		$cartResult = array();
		//获取临时购物车存储temp策略
		$cartName  = $this->getCartName();
		$tempData     = ($this->saveType == 'session') ? ISession::get($cartName) : ICookie::get($cartName);
		if($tempData)
		{
			$cartResult = $this->decode($tempData);
		}

		//已经登录用户采用db策略
		$cartDBData = array();
		//$user_id    = IWeb::$app->getController()->user['user_id'];
        $user_id = 1;
		if($user_id)
		{
			$cartDB  = new IModel('goods_car');
			$cartRow = $cartDB->getObj('user_id = '.$user_id);

			//db存在购物车
			if($cartRow)
			{
				if($cartRow['content'])
				{
					$cartDBData = $this->decode($cartRow['content']);
					if($cartResult)
					{
						foreach($cartResult as $type => $val)
						{
							//dyg_jzw 20161213 跨境电商标记拦截
							if($type == 'goods' || $type == 'product')
							{
								foreach($val as $idVal => $numVal)
								{
									$cartDBData = $this->getUpdateCartData($cartDBData,$idVal,$numVal,$type);
								}
							}
						}
					}
					$cartResult = $cartDBData;
				}
				$cartDB->setData(array('content' => $this->encode($cartResult),'user_id' => $user_id,'create_time' => ITime::getDateTime()));
				$cartDB->update("user_id = ".$user_id);
			}
			//db没有购物车,并且有临时temp购物车
			else if($cartResult)
			{
				$cartDB->setData(array('content' => $tempData,'user_id' => $user_id,'create_time' => ITime::getDateTime()));
				$cartDB->add();
			}

			$cartName = $this->getCartName();
			$this->saveType == 'session' ? ISession::clear($cartName) : ICookie::clear($cartName);
		}
		//print_r($cartResult);
		return $cartResult ? $cartResult : $this->cartStruct;
	}
	/**
	 * 获取当前购物车完整信息
	 * @return 获取cartExeStruct数据结构
	 */
	public function getMyCart()
	{
		$cartValue = $this->getMyCartStruct();
		return $this->cartFormat($cartValue);
	}

	//清空购物车
	public function clear()
	{
		//1,清空db
		$user_id = $this->user_id;
		if($user_id)
		{
			$cartDB  = new IModel('goods_car');
			$cartRow = $cartDB->getObj('user_id = '.$user_id);
			if($cartRow)
			{
				$cartDB->del('user_id = '.$user_id);
			}
		}
		//2,清空临时temp
		$cartName = $this->getCartName();
		$this->saveType == 'session' ? ISession::clear($cartName) : ICookie::clear($cartName);
	}

	//清空购物车拦截器 解决cookie header头延迟发送问题
	public static function onFinishAction($ctrlObj,$actionObj)
	{
		$cartObj = new Cart();
		$cartObj->clear();
	}

	//写入购物车
	private function setMyCart($goodsInfo)
	{
		$goodsInfo = $this->encode($goodsInfo);

		//1,用户存在写入db
		$user_id = $this->user_id;
		if($user_id)
		{
			$cartDB = new IModel('goods_car');
			$cartDB->setData(array('content' => $goodsInfo,'user_id' => $user_id,'create_time' => ITime::getDateTime()));
			$result = $cartDB->getObj('user_id = '.$user_id) ? $cartDB->update('user_id = '.$user_id) : $cartDB->add();
		}
		//2,访客写入temp临时
		else
		{
			$cartName = $this->getCartName();
			$result   = ($this->saveType == 'session') ? ISession::set($cartName,$goodsInfo) : ICookie::set($cartName,$goodsInfo,'7200');
		}
		return $result;
	}

	/**
	 * @brief  把cookie的结构转化成为程序所用的数据结构
	 * @param  $cartValue 购物车cookie存储结构
	 * @return array : [goods]=>array( ['id']=>商品ID , ['data'] => array( [商品ID]=>array ([name]商品名称 , [img]图片地址 , [sell_price]价格, [count]购物车中此商品的数量 ,[type]类型goods,product , [goods_id]商品ID值 ) ) ) , [product]=>array( 同上 ) , [count]购物车商品和货品数量 , [sum]商品和货品总额 ;
	 */
	public function cartFormat($cartValue)
	{
		//初始化结果
		$result = $this->cartExeStruct;

		$goodsIdArray = array();

		if(isset($cartValue['goods']) && $cartValue['goods'])
		{
			$goodsIdArray = array_keys($cartValue['goods']);
			$result['goods']['id'] = $goodsIdArray;
			foreach($goodsIdArray as $gid)
			{
				$result['goods']['data'][$gid] = array(
					'id'       => $gid,
					'type'     => 'goods',
					'goods_id' => $gid,
					'count'    => $cartValue['goods'][$gid],
				);

				//购物车中的种类数量累加
				$result['count'] += $cartValue['goods'][$gid];
			}
		}
		if(isset($cartValue['product']) && $cartValue['product'])
		{
			$productIdArray          = array_keys($cartValue['product']);
			$result['product']['id'] = $productIdArray;

			$productObj     = new IModel('products');
			$productData    = $productObj->query('id in ('.join(",",$productIdArray).')','id,goods_id,sell_price');
			//dyg_jzw 20161219 检查是否有不存在的商品
			if (count($productIdArray) != count($productData))
			{
				$_tmp_pids = array();
				foreach($productData as $proVal)
				{
					$_tmp_pids[] = $proVal['id'];
				}

				foreach($productIdArray as $_pid)
				{
					if ( ! in_array($_pid, $_tmp_pids))
					{
						$this->del($_pid, 'product');
					}
				}
			}

			foreach($productData as $proVal)
			{
				$result['product']['data'][$proVal['id']] = array(
					'id'         => $proVal['id'],
					'type'       => 'product',
					'goods_id'   => $proVal['goods_id'],
					'count'      => $cartValue['product'][$proVal['id']],
					'sell_price' => $proVal['sell_price'],
				);

				if(!in_array($proVal['goods_id'],$goodsIdArray))
				{
					$goodsIdArray[] = $proVal['goods_id'];
				}

				//购物车中的种类数量累加
				$result['count'] += $cartValue['product'][$proVal['id']];
			}
		}

		if($goodsIdArray)
		{
			$goodsArray = array();

			$goodsObj   = new IModel('goods');
			$goodsData  = $goodsObj->query('id in ('.join(",",$goodsIdArray).')','id,name,img,sell_price');
			foreach($goodsData as $goodsVal)
			{
				$goodsArray[$goodsVal['id']] = $goodsVal;
			}

			//dyg_jzw 20161219 检查是否有不存在的商品
			if (count($goodsIdArray) != count($goodsData))
			{
				foreach($goodsIdArray as $_gid)
				{
					if ( ! array_key_exists($_gid, $goodsArray))
					{
						$this->del($_gid, 'goods');
					}
				}
			}

			foreach($result['goods']['data'] as $key => $val)
			{
				if(isset($goodsArray[$val['goods_id']]))
				{
					$result['goods']['data'][$key]['img']        = Thumb::get($goodsArray[$val['goods_id']]['img'],120,120);
					$result['goods']['data'][$key]['name']       = $goodsArray[$val['goods_id']]['name'];
					$result['goods']['data'][$key]['sell_price'] = $goodsArray[$val['goods_id']]['sell_price'];

					//购物车中的金额累加
					$result['sum']   += $goodsArray[$val['goods_id']]['sell_price'] * $val['count'];
				}
			}

			foreach($result['product']['data'] as $key => $val)
			{
				if(isset($goodsArray[$val['goods_id']]))
				{
					$result['product']['data'][$key]['img']  = Thumb::get($goodsArray[$val['goods_id']]['img'],120,120);
					$result['product']['data'][$key]['name'] = $goodsArray[$val['goods_id']]['name'];

					//购物车中的金额累加
					$result['sum']   += $result['product']['data'][$key]['sell_price'] * $val['count'];
				}
			}
		}
		return $result;
	}

	//[私有]获取购物车名字
	private function getCartName()
	{
		return $this->cartName;
	}

	//获取错误信息
	public function getError()
	{
		return $this->error;
	}
	//购物车存储数据编码
	private function encode($data)
	{
		return str_replace(array('"',','),array('&','$'),JSON::encode($data));
	}
	//购物车存储数据解码
	private function decode($data)
	{
		return JSON::decode(str_replace(array('&','$'),array('"',','),$data));
	}
}