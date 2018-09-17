<?php
/**
 * @copyright (c) 2017 dyg.cn
 * @file seller_group.php
 * @brief 商户联盟插件
 * @author jzw
 * @date 2017-03-24 10:00:00
 * @version 1.0
 */
class seller_group extends pluginBase
{
	public static function name()
	{
		return "商户联盟管理";
	}

	public static function description()
	{
		return "商户可以互相结为联盟，当推荐商户为商盟成员则根据设定权重进行利益分成";
	}

	public static function install()
	{
		$sellerGroupDB = new IModel('seller_group');
		$sellerGroupRelationDB = new IModel('seller_group_relation');

		if($sellerGroupDB->exists() && $sellerGroupRelationDB->exists())
		{
			return true;
		}

		$data = array(
			"comment" => self::name(),
			"column"  => array(
				"id" => array("type" => "int(11) unsigned",'auto_increment' => 1),
				"name" => array("type" => "varchar(32)","comment" => "商盟名称"),
			),
			"index" => array("primary" => "id"),
		);
		$sellerGroupDB->setData($data);
		$sellerGroupDB->createTable();

		$data = array(
			"comment" => self::name().'记录关系',
			"column"  => array(
				"id" => array("type" => "int(11) unsigned",'auto_increment' => 1),
				"seller_group_id" => array("type" => "int(11) unsigned","comment" => "商盟ID"),
				"seller_id" => array("type" => "int(11)","comment" => "商户ID"),
				"weight" => array("type" => "decimal(4,3)","comment" => "权重划分"),
			),
			"index" => array("primary" => "id", "key" => "seller_id"),
		);
		$sellerGroupRelationDB->setData($data);
		$sellerGroupRelationDB->createTable();

		//改变商盟的id 从10000000开始
		$sellerGroupDB->setData(array(
			"id" => "10000000",
			"name" => "test"
		));
		$sellerGroupDB->add();
		$sellerGroupDB->del("id = 10000000");

		return true;
	}

	public static function uninstall()
	{
		$sellerGroupDB = new IModel('seller_group');
		$sellerGroupRelationDB = new IModel('seller_group_relation');

		$sellerGroupDB->dropTable();
		return $sellerGroupRelationDB->dropTable();
	}

	public function reg()
	{
		//后台管理
		plugin::reg("onSystemMenuCreate",function(){
			$link = "/plugins/seller_group_list";
			$link = "javascript:art.dialog.open('".IUrl::creatUrl($link)."',{title:'".$this->name()."',width:'100%',height:'100%',id:'seller_group'});";
			Menu::$menu["插件"]["插件管理"][$link] = $this->name();
		});
		plugin::reg("onBeforeCreateAction@plugins@seller_group_list",function(){
			self::controller()->seller_group_list = function(){$this->seller_group_list();};
		});
		plugin::reg("onBeforeCreateAction@plugins@seller_group_edit",function(){
			self::controller()->seller_group_edit = function(){$this->seller_group_edit();};
		});
		plugin::reg("onBeforeCreateAction@plugins@seller_group_update",function(){
			self::controller()->seller_group_update = function(){$this->seller_group_update();};
		});
		plugin::reg("onBeforeCreateAction@plugins@seller_group_del",function(){
			self::controller()->seller_group_del = function(){$this->seller_group_del();};
		});

		
	}

	public static function configName()
	{
		return array();
	}

	//商户联盟列表
	public function seller_group_list()
	{
		$this->view('seller_group_list');
	}

	//编辑商户联盟
	public function seller_group_edit()
	{
		$id     = IFilter::act(IReq::get('id'),'int');
		$sellerGroupRow = array();
		if($id)
		{
			$sellerGroupDB = new IModel('seller_group');
			$sellerGroupRow = $sellerGroupDB->getObj('id = '.$id);

			if ($sellerGroupRow)
			{
				//获取商盟下的商户列表
				$sellerGroupRelationDB = new IModel('seller_group_relation');
				$_seller = $sellerGroupRelationDB->query("seller_group_id = ".$sellerGroupRow['id']);

				$sellerGroupRow['seller'] = $_seller;
			}
		}
		$this->view('seller_group_edit',array('sellerGroupData' => $sellerGroupRow));
	}

	//保存商盟信息
	public function seller_group_update()
	{
		$group_id = IFilter::act(IReq::get('id'), 'int');
		$group_name = IFilter::act(IReq::get('name'));

		$seller_id_arr = IFilter::act(IReq::get('seller_id'), 'int');
		$weight_arr   = IFilter::act(IReq::get('weight'), 'float');

		//权重总和是否等于1
		$is_one = false;
		if ($weight_arr)
		{
			$_count_one = 0;
			foreach ($weight_arr as $_w)
			{
				$_count_one += $_w;
			}

			if ($_count_one == 1)
			{
				$is_one = true;
			}
		}

		if(!$seller_id_arr || !$weight_arr || !$group_name || !$is_one)
		{
			$this->seller_group_edit();
			return;
		}

		$data        = array();
		foreach($seller_id_arr as $key => $val)
		{
			$data[] = array('seller_id' => $seller_id_arr[$key],'weight' => $weight_arr[$key]);
		}

		//保存数据库
		$sellerGroupRelationDB = new IModel('seller_group_relation');
		$sellerGroupDB = new IModel('seller_group');
		if ($group_id)
		{
			//已有商盟ID
			if($sellerGroupDB->getObj('id = '.$group_id))
			{
				$sellerGroupDB->setData(array('name' => $group_name));
				$sellerGroupDB->update('id = '.$group_id);

				//删除原有关系
				$sellerGroupRelationDB->del('seller_group_id = '. $group_id);
			}
		}
		else
		{
			//新建商盟
			$sellerGroupDB->setData(array(
				'name' => $group_name
			));
			$group_id = $sellerGroupDB->add();
		}

		//插入商盟关系
		foreach ($data as $_d)
		{
			$sellerGroupRelationDB->setData(array(
				'seller_group_id' => $group_id,
				'seller_id' => $_d['seller_id'],
				'weight' => $_d['weight']
			));
			$sellerGroupRelationDB->add();
		}

		$this->view('seller_group_list');
	}

	public function seller_group_del()
	{
		$group_id = IFilter::act(IReq::get('id'), 'int');

		//获取商盟信息
		$sellerGroupRelationDB = new IModel('seller_group_relation');
		$sellerGroupDB = new IModel('seller_group');

		if($sellerGroupDB->getObj('id = '.$group_id))
		{
			$sellerGroupDB->del('id = '.$group_id);
			$sellerGroupRelationDB->del('seller_group_id = '. $group_id);
		}

		$this->view('seller_group_list');
	}
}