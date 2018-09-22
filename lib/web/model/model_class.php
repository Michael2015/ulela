<?php
/**
 * @copyright (c) 2016 aircheng.com
 * @file IModel.php
 * @brief 数据处理
 * @author nswe
 * @date 2016/3/10 16:52:58
 * @version 4.4
 */

/**
 * @class IModel
 * @brief 数据表对象
 */
class IModel
{
	//数据库操作对象
	private $db = NULL;

	//数据表名称
	private $tableName = '';

	//要更新的表数据,key:对应表字段; value:数据;
	private $tableData = array();

	//表前缀
	private $tablePre  = '';

	/**
	 * @brief 构造函数,创建数据库对象
	 * @param string $tableName 表名称(当多表操作时以逗号分隔,如：user,goods);
	 */
	public function __construct($tableName)
	{
		$this->db       = IDBFactory::getDB();
		$this->tablePre = isset(IWeb::$app->config['DB']['tablePre']) ? IWeb::$app->config['DB']['tablePre'] : '';

		//多表处理
		if(stripos($tableName,','))
		{
			$tables = explode(',',$tableName);
			foreach($tables as $val)
			{
				if($this->tableName != '')
					$this->tableName .= ',';

				$this->tableName .= $this->tablePre.trim($val);
			}
		}

		//单表处理
		else
		{
			$this->tableName = $this->tablePre.$tableName;
		}
	}

	/**
	 * @brief 设置要更新的表数据
	 * @param array $data key:字段名; value:字段值;
	 */
	public function setData($data)
	{
		if(is_array($data))
		{
			$this->tableData = $data;
		}
		else
			return false;
	}

	/**
	 * @brief 更新
	 * @param  string $where 更新条件
	 * @param  array  $except 非普通数据形式(key值)
	 * @return int or bool int:影响的条数; bool:false错误
	 */
	public function update($where,$except=array())
	{
		$except    = is_array($except) ? $except : array($except);
		$updateArr = array();
		$where     = (strtolower($where) == 'all') ? '' : ' WHERE '.$where;
		foreach($this->tableData as $key => $val)
		{
			if(!in_array($key,$except))
			{
				$val = IFilter::stripSlash($val);
				$val = IFilter::addSlash($val);

				$key = IFilter::stripSlash($key);
				$key = IFilter::addSlash($key);

				$updateArr[] = " `{$key}` = '{$val}' ";
			}
			else
			{
				$updateArr[] = " `{$key}` = {$val} ";
			}
		}
		$sql = 'UPDATE '.$this->tableName.' SET '.join(",",$updateArr) . $where;
		return $this->db->query($sql);
	}

	/**
	 * @brief 添加
	 * @return int or bool int:插入的自动增长值 bool:false错误
	 */
	public function add()
	{
		$insertCol = array();
		$insertVal = array();

		foreach($this->tableData as $key => $val)
		{
			$key = IFilter::stripSlash($key);
			$key = IFilter::addSlash($key);

			$val = IFilter::stripSlash($val);
			$val = IFilter::addSlash($val);

			$insertCol[] = '`'.$key.'`';
			$insertVal[] = '\''.$val.'\'';
		}
		$sql = 'INSERT INTO '.$this->tableName.' ( '.join(',',$insertCol).' ) VALUES ( '.join(',',$insertVal).' ) ';
		return $this->db->query($sql);
	}

	/**
	 * @brief 删除
	 * @param string $where 删除条件
	 * @return int or bool int:删除的记录数量 bool:false错误
	 */
	public function del($where)
	{
		$where = (strtolower($where) == 'all') ? '' : ' WHERE '.$where;
		$sql   = 'DELETE FROM '.$this->tableName.$where;
		return $this->db->query($sql);
	}

	/**
	 * @brief 获取单条数据
	 * @param string $where 查询条件
	 * @param array or string $cols 查询字段,支持数组格式,如array('cols1','cols2')
	 * @return array 查询结果
	 */
	public function getObj($where = false,$cols = '*')
	{
		$result = $this->query($where,$cols,'',1);
		if(empty($result))
		{
			return array();
		}
		else
		{
			return $result[0];
		}
	}

	/**
	 * @brief 获取多条数据
	 * @param string $where 查询条件
	 * @param array or string $cols 查询字段,支持数组格式,如array('cols1','cols2')
	 * @param array or string $orderBy 排序字段 DESC:倒序; ASC:正序;
	 * @param array or int $limit 显示数据条数 默认(5000)
	 * @return array 查询结果
	 */
	public function query($where=false,$cols='*',$orderBy='',$limit=50000)
	{
		$where = preg_replace('/from\s+(\S+)/i',"from {$this->tablePre}$1 ",$where);

		//字段拼接
		if(is_array($cols))
		{
			$colStr = join(',',$cols);
		}
		else
		{
			$colStr = ($cols=='*' || !$cols) ? '*' : $cols;
		}

		$sql = 'SELECT '.$colStr.' FROM '.$this->tableName;

		//条件拼接
		if($where != false) $sql.=' WHERE '.$where;

		//排序拼接
		if($orderBy)
		{
			$sql.= ' ORDER BY '.$orderBy;
		}

		//条数拼接
		if($limit != 'all')
		{
			//$limit = intval($limit);
			$limit = $limit ? $limit : 5000;
			$sql.=' limit ' . $limit;
		}

		return $this->db->query($sql);
	}

	/**
	 * @brief 写操作回滚
	 */
	public function rollback()
	{
		$this->db->switchLink("w");
		return $this->db->rollback();
	}

	/**
	 * @brief 获取原生态SQL
	 */
	public function getSql()
	{
		return $this->db->getSql();
	}

	/**
	 * @brief 创建表
	 * @see $this->tableData数据格式：array(
	 	"column" => 字段配置array("type" => 数据类型,"default" => 默认值,"comment" => 字段注释,"auto_increment" => 数值自增长)
	 	"comment"=> 表注释
	 	"index"  => 表索引array("索引类型:PRIMARY,KEY,UNIQUE" => "字段名称")
	 )
	 */
	public function createTable()
	{
		$sqlTemplate = "CREATE TABLE `".$this->tableName."` ( {__DATA__} ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='{__COMMENT__}';";
		$sqlArray    = array();

		foreach($this->tableData as $key => $val)
		{
			switch($key)
			{
				//表字段
				case "column":
				{
					foreach($val as $name => $item)
					{
						//字段结构
						$tempSql = array();

						//字段名字
						$tempSql[] = "`{$name}`";

						//字段数据类型
						$tempSql[] = isset($item['type']) ? $item['type'] : "varchar(255)";

						//字段默认值
						if(isset($item['default']))
						{
							$tempSql[] = $item['default'] === null ? "default null" : "default '".$item['default']."'";
						}
						else
						{
							$tempSql[] = "NOT NULL";
						}
						//字段自动增长
						$tempSql[] = isset($item['auto_increment']) ? "auto_increment" : "";

						//字段注释
						$tempSql[] = isset($item['comment']) ? "COMMENT '".$item['comment']."'" : "";

						//拼接数据
						$sqlArray[] = join(" ",$tempSql);
					}
				}
				break;

				//表注释
				case "comment":
				{
					$sqlTemplate = str_replace("{__COMMENT__}",$val,$sqlTemplate);
				}
				break;

				//表索引
				case "index":
				{
					foreach($val as $index => $columnName)
					{
						$columnName = "`".str_replace(",","`,`",$columnName)."`";
						switch($index)
						{
							case "primary":
							{
								$sqlArray[] = "PRIMARY KEY (".$columnName.")";
							}
							break;

							case "key":
							case "index":
							{
								$sqlArray[] = "KEY (".$columnName.")";
							}
							break;

							case "unique":
							{
								$sqlArray[] = "UNIQUE KEY (".$columnName.")";
							}
							break;
						}
					}
				}
				break;
			}
		}
		$sql = str_replace("{__DATA__}",join(",",$sqlArray),$sqlTemplate);
		return $this->db->query($sql);
	}

	/**
	 * @brief 卸载表
	 */
	public function dropTable()
	{
		$sql = "DROP TABLE IF EXISTS `".$this->tableName."`;";
		return $this->db->query($sql);
	}

	/**
	 * @brief 表是否存在
	 * @return boolean
	 */
	public function exists()
	{
		$sql    = "SHOW TABLES like '".$this->tableName."';";
		$result = $this->db->query($sql);
		if($result)
		{
			$result = current($result);
		}
		return $result ? true : false;
	}

	/**
	 * @brief replace into操作当主键存在update，否则add
	 * @return boolean
	 */
	public function replace()
	{
		$insertCol = array();
		$insertVal = array();

		foreach($this->tableData as $key => $val)
		{
			$key = IFilter::stripSlash($key);
			$key = IFilter::addSlash($key);

			$val = IFilter::stripSlash($val);
			$val = IFilter::addSlash($val);

			$insertCol[] = '`'.$key.'`';
			$insertVal[] = '\''.$val.'\'';
		}
		$sql = 'REPLACE INTO '.$this->tableName.' ( '.join(',',$insertCol).' ) VALUES ( '.join(',',$insertVal).' ) ';
		return $this->db->query($sql);
	}
}