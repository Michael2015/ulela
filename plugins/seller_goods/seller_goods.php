<?php
/**
 * @copyright (c) 2017 dyg.cn
 * @file seller_goods.php
 * @brief 商户的商品跳转和同步主站
 * @author jzw
 * @date 2017/3/17 16:15:21
 * @version 1.0
 */
class seller_goods extends pluginBase
{
	//插件注册
	public function reg()
	{
		//注册触发事件
		plugin::reg("onCreateAction@goods@goods_update",$this,"goodsUpdate");
		plugin::reg("onCreateAction@goods@goods_del",$this,"goods_recycle_del");
		plugin::reg("onCreateAction@goods@goods_stats",$this,"goodsStats");
		plugin::reg("onCreateAction@goods@goods_recycle_restore",$this,"goodsRecycle");
		plugin::reg("onBeforeCreateAction@goods@sync_goods", function(){
			self::controller()->sync_goods=$this->syncGoods();
		});
		plugin::reg("onBeforeCreateAction@goods@is_share_is_copy", function(){
			self::controller()->is_share_is_copy=$this->is_share_is_copy();
		});
	}
	//商品保存更改
	public function goodsUpdate()
	{
		if ($_POST)
		{
			$postData = $_POST;

			//主站更新共享商品
			if (isset($postData['id']) && $postData['id'] && intval($postData['seller_id']) == 0 && intval($postData['is_share'])) 
			{
				$id = $postData['id'];

				unset($postData['id']);
				unset($postData['callback']);

				//获取关联商品
				$sellerGoodsRelationDB = new IModel('seller_goods_relation');
				$_seller_goods = $sellerGoodsRelationDB->query("goods_id = ".$id);

				$goodsObject = new goods_class();

				$goodsDB = new IModel("goods");
				$productDB = new IModel("products");
				
				foreach ($_seller_goods as $_sg)
				{
					//查询原商品库存
					$_old_goods = $goodsDB->getObj("id = " . $_sg['seller_goods_id'], 'store_nums');

					if ($_old_goods)
					{
						//获取原商品库存
						$postData['store_nums'] = $_old_goods['store_nums'];

						//是否存在货品
						$_old_products = $productDB->query("goods_id = " . $_sg['seller_goods_id']);

						//获取原货品库存
						if ($_old_products)
						{
							foreach ($_old_products as $_op)
							{
								foreach ($postData['_goods_no'] as $_key => $_value)
								{
									if ($_value == $_op['products_no'])
									{
										$postData['_store_nums'][$_key] = $_op['store_nums'];
									}
								}
							}
						}

						//商家ID
						$postData['seller_id'] = $_sg['seller_id'];

						//更新子站商品
						$goodsObject->update($_sg['seller_goods_id'],$postData);
					}
				}
			}
		}
	}
	//判断是否共享，判断时候是否是复制出来的
	function is_share_is_copy()
	{
		$goodsDB = new IModel('goods');
		$goods_id = IFilter::act(IReq::get('goods_id'));
		$goods_id_arr = explode(',',$goods_id);
		$data['status'] = 1;
		foreach ($goods_id_arr as $goodsId)
		{
            //判断是否含有不共享的商品
			$is_share_is_copy = $goodsDB->query(" id = ".$goodsId." AND  (is_share = 0 OR seller_id <> 0 OR is_del <> 0) ",'id','','1');
			if($is_share_is_copy)
			{
				$data['status'] = 0;
				break;
			}
		}
		echo json_encode($data);
	}

	//垃圾站彻底删除
	public function goods_recycle_del()
	{
		  //post数据
		$id = IFilter::act(IReq::get('id'),'int');
        //生成goods对象
		$goodsDB = new IModel('goods');
        //生成relation对象
		$relationDB = new IModel('seller_goods_relation');
        //生成products对象
		$productsDB = new IModel('products');
        //生成分类category表
		$categoryDB = new IModel('category_extend');
		//商品属性表
		$goodsAttrDB = new IModel('goods_attribute');
		//商品促销表
		$commendDB = new IModel('commend_goods');
		//商品图片表
		$photoRelationDB = new IModel('goods_photo_relation');
		//商品价格表
		$groupPriceDB = new IModel('group_price');
		//商品真实价格表
		$groupRealPriceDB = new IModel('group_real_price');
		if($id)
		{
			if(is_array($id))
			{
				foreach($id as $key => $val)
				{
					$seller_id = $goodsDB->query(' id = '.$val,'seller_id','',1)[0]['seller_id'];
                    $relationDB->del("seller_goods_id = ".$val." AND seller_id = ".$seller_id);//删除seller_goods_relation表复制后的数据
                    $productsDB->del("goods_id = ".$val);//删除product表复制后的数据
                    $categoryDB->del('goods_id = '.$val);//删除category分类表
                    $goodsAttrDB->del('goods_id = '.$val);//删除商品属性表
                    $commendDB->del('goods_id = '.$val);//删除商品促销表
                    $photoRelationDB->del('goods_id = '.$val);//删除商品图片表
                    $groupPriceDB->del('goods_id = '.$val);//删除商品价格表
                    $groupRealPriceDB->del('goods_id = '.$val);//删除商品真实价格表
                }
            }
        }

    }
	//商品上下架
    public function goodsStats()
    {

    }

	//商品从垃圾站还原
    public function goodsRecycle()
    {

    }
	//商品同步
    public function syncGoods()
    {
		 //获取同步商品所有ID
    	$goods_id = IFilter::act(IReq::get('goods_id'));
        //获取同步商铺所有ID
    	$seller_id_post = IFilter::act(IReq::get('seller_id'));
        //操作类型
    	$module = IFilter::act(IReq::get('module'));
    	$sellerDB = new IModel('seller');
        //查找所有seller_id/seller_name
    	$seller_list = $sellerDB->query(' 1 ','seller_name,id',' id DESC');
        //将seller_id 放在一个数组里面
    	while(list($key,$value) = each($seller_list)){
    		$seller_id_all[] = $value['id'];
    	}
        //商品同步到商铺操作
    	if($goods_id && $seller_id_post && $module == 'goods_sync')
    	{
    		$goodsDB = new IModel('goods');
    		$relationDB = new IModel('seller_goods_relation');
    		$productsDB = new IModel('products');
    		$categoryDB = new IModel('category_extend');
    		$goodsAttrDB = new IModel('goods_attribute');
    		$commendDB = new IModel('commend_goods');
    		$photoRelationDB = new IModel('goods_photo_relation');
    		$groupPriceDB = new IModel('group_price');
    		$groupRealPriceDB = new IModel('group_real_price');
            //找出哪些是需要同步的商铺、哪些是不需要同步的
    		$goods_id_arr = explode(',',$goods_id);
    		if(is_array($goods_id_arr) &&  $goods_id_arr )
    		{
                //遍历所有商品
    			foreach ($goods_id_arr as $k1=>$goodsId)
    			{
                    //一、遍历所有【选中】商铺中是否已经存在当前商品
    				foreach ($seller_id_post as $k2=>$sellerId)
    				{
    					$goods_seller_relation_query_result = $relationDB->query(' goods_id = '.$goodsId.' AND seller_id = '.$sellerId,'id','','1');
                        //没有复制过的商品才能被同步  如果被复制过是不能被复制的
    					if(empty($goods_seller_relation_query_result))
    					{
    						$goods_query_result = $goodsDB->query(' id = '.$goodsId,'*','','1')[0];
                            unset($goods_query_result['id']);//去掉旧商品ID
                            $goods_query_result['seller_id'] = $sellerId;
                            $goodsDB->setData($goods_query_result);//复制新商品
                            if($goods_new_id = $goodsDB->add())
                            {
                                $insertData['goods_id'] = $goodsId;//旧商品ID
                                $insertData['seller_id'] = $sellerId;//复制后新商品ID
                                $insertData['seller_goods_id'] = $goods_new_id;//复制后新商品ID
                                $relationDB->setData($insertData);
                                $relationDB->add();//插入商品-商铺同步关系表

                                //插入分类表,处理商品分类
                                $category_query_result = $categoryDB->query(' goods_id ='.$goodsId,'*');
                                if($category_query_result)
                                {
                                	foreach($category_query_result as $k3=>$cat)
                                	{
                                		$categoryDB->setData(array('goods_id' => $goods_new_id,'category_id' => $cat['category_id']));
                                		$categoryDB->add();
                                	}
                                }
                                $product_query_result = $productsDB->query(' goods_id ='.$goodsId,'*');
                                //插入SKU表
                                if($product_query_result)
                                {
                                	foreach ($product_query_result as $pro)
                                	{
                                		unset($pro['id']);
                                        $pro['goods_id'] = $goods_new_id;//更新goodsID
                                        $productsDB->setData($pro);
                                        $productsDB->add();//更新product表
                                    }
                                }
                                //插入商品属性
                                $attr_query_result = $goodsAttrDB->query(' goods_id ='.$goodsId,'*');
                                if($attr_query_result)
                                {
                                	foreach ($attr_query_result as $attr)
                                	{
                                		unset($attr['id']);
                                        $attr['goods_id'] = $goods_new_id;//更新goodsID
                                        $goodsAttrDB->setData($attr);
                                        $goodsAttrDB->add();//更新product表
                                    }
                                }
                                //处理商品促销
                                $commend_query_result = $commendDB->query(' goods_id ='.$goodsId,'*');
                                if($commend_query_result)
                                {
                                	foreach ($commend_query_result as $comd)
                                	{
                                		unset($comd['id']);
                                        $comd['goods_id'] = $goods_new_id;//更新goodsID
                                        $commendDB->setData($comd);
                                        $commendDB->add();//更新product表
                                    }
                                }
                                //处理商品图片
                                $photo_query_result = $photoRelationDB->query(' goods_id ='.$goodsId,'*');
                                if($photo_query_result)
                                {
                                	foreach ($photo_query_result as $photo)
                                	{
                                		unset($photo['id']);
                                        $photo['goods_id'] = $goods_new_id;//更新goodsID
                                        $photoRelationDB->setData($photo);
                                        $photoRelationDB->add();//更新product表
                                    }
                                }
                                //处理会员组的价格
                                $price_query_result = $groupPriceDB->query(' goods_id ='.$goodsId,'*');
                                if($price_query_result)
                                {
                                	foreach ($price_query_result as $price)
                                	{
                                		unset($price['id']);
                                        $price['goods_id'] = $goods_new_id;//更新goodsID
                                        $groupPriceDB->setData($price);
                                        $groupPriceDB->add();//更新product表
                                    }
                                }
                                 //处理会员组真实价格
                                $real_price_query_result = $groupRealPriceDB->query(' goods_id ='.$goodsId,'*');
                                if($real_price_query_result)
                                {
                                	foreach ($real_price_query_result as $real_price)
                                	{
                                		unset($real_price['id']);
                                        $real_price['goods_id'] = $goods_new_id;//更新goodsID
                                        $groupRealPriceDB->setData($real_price);
                                        $groupRealPriceDB->add();//更新product表
                                    }
                                }

                            }
                        }
                    }
                }
            }
            die('<script type="text/javascript">parent.artDialogCallback();</script>');
        }
        $this->redirect('sync_goods',array('seller_list'=>$seller_list,'goodsid_set'=>$goods_id));
    }
    public static function name()
    {
    	return "主站商品与子站自动关联更新";
    }

    public static function description()
    {
    	return "子站商品各自独立，需要和主站进行关联并自动更新";
    }

    public static function install()
    {
    	$sellerGoodsRelationDB = new IModel('seller_goods_relation');
    	if($sellerGoodsRelationDB->exists())
    	{
    		return true;
    	}
    	$data = array(
    		"comment" => self::name(),
    		"column"  => array(
    			"id"         	=> array("type" => "int(11) unsigned",'auto_increment' => 1),
    			"goods_id"       => array("type" => "int(11)","comment" => "主站的商品ID"),
    			"seller_id"   		=> array("type" => "int(11)","comment" => "商户ID"),
    			"seller_goods_id"     => array("type" => "int(11)","comment" => "商户对应的商品ID"),
    			),
    		"index" => array("primary" => "id"),
    		);
    	$sellerGoodsRelationDB->setData($data);
    	$sellerGoodsRelationDB->createTable();

    	return true;
    }

    public static function uninstall()
    {
    	$sellerGoodsRelationDB = new IModel('seller_goods_relation');
    	return $sellerGoodsRelationDB->dropTable();
    }

    public static function configName()
    {
    	return array();
    }

}