<?php
/**
 * @brief 升级更新控制器
 */
class Update extends IController
{
	/**
	 * @brief iwebshop16060600 版本升级更新
	 */
	public function index()
	{
		set_time_limit(0);

		$specDB  = new IModel('spec');

		//修正商品表的spec_array信息
		$goodsDB = new IModel('goods');
		$goodsList = $goodsDB->query('spec_array is not null');
		foreach($goodsList as $key => $val)
		{
			$specArray = JSON::decode($val['spec_array']);
			if($specArray)
			{
				foreach($specArray as $k => $v)
				{
					if(isset($v['value']) && $v['value'] && is_string($v['value']))
					{
						//获取规格数据
						$specRow = $specDB->getObj('id = '.$v['id']);
						$specBox = $specRow && $specRow['value'] ? JSON::decode($specRow['value']) : array();

						$valueArray = explode(",",$v['value']);
						$specArray[$k]['value'] = array();
						foreach($valueArray as $valItem)
						{
							$tip = $this->searchSpec($valItem,$specBox);
							if($tip === null)
							{
								$specArray[$k]['value'][] = array($valItem);
							}
							else
							{
								$specArray[$k]['value'][] = array($tip => $valItem);
							}
						}
					}
				}
				$goodsDB->setData(array('spec_array' => IFilter::act(JSON::encode($specArray))));
				$goodsDB->update('id = '.$val['id']);
			}
		}

		//修正货品表的spec_array信息
		$productsDB = new IModel('products');
		$proList    = $productsDB->query();
		foreach($proList as $key => $val)
		{
			$specArray = JSON::decode($val['spec_array']);
			if($specArray)
			{
				foreach($specArray as $k => $v)
				{
					if(!isset($v['tip']))
					{
						//获取规格数据
						$specRow = $specDB->getObj('id = '.$v['id']);
						$specBox = $specRow && $specRow['value'] ? JSON::decode($specRow['value']) : array();
						$specArray[$k]['tip'] = $this->searchSpec($v['value'],$specBox);
					}
				}
				$productsDB->setData(array('spec_array' => IFilter::act(JSON::encode($specArray))));
				$productsDB->update('id = '.$val['id']);
			}
		}

		$sql = array(
			"DROP TABLE IF EXISTS `{pre}session`;",
			"CREATE TABLE `{pre}session` (
			  `id` char(32) NOT NULL COMMENT 'session的唯一id',
			  `value` text COMMENT 'session序列化数据',
			  `time` datetime NOT NULL COMMENT '存储时间',
			  PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='session会话表';",
			"ALTER TABLE  `{pre}bill` ADD  `amount` decimal(15,2) NOT NULL default '0.00' COMMENT '结算的金额';",
		);

		foreach($sql as $key => $val)
		{
			IDBFactory::getDB()->query( $this->_c($val) );
		}

		die("升级成功!! V4.6版本");
	}

	public function _c($sql)
	{
		return str_replace('{pre}',IWeb::$app->config['DB']['tablePre'],$sql);
	}

	//查询规格标准
	public function searchSpec($specVal,$specValueArray)
	{
		foreach($specValueArray as $tip => $item)
		{
			if($item == $specVal && !is_numeric($tip))
			{
				return $tip;
			}
		}
		return "";
	}
}