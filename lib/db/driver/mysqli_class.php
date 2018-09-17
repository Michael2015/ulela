<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file mysqli_class.php
 * @brief mysqli数据库应用
 * @author nswe
 * @date 2015/7/1 15:05:26
 * @version 3.5
 */

/**
 * @class IMysqli
 * @brief MYSQLI数据库应用
 */
class IMysqli extends IDB
{
	//当前数据库连接资源,读或写
	public $linkRes = false;

	/**
	 * @brief 数据库连接
	 * @param array $dbinfo 数据库的连接配制信息 [0]ip地址 [1]用户名 [2]密码 [3]数据库
	 * @return bool or resource 值: false:链接失败; resource类型:链接的资源句柄;
	 */
	public function connect($dbinfo)
	{
		$hostArray = explode(':',$dbinfo['host']);
		$hostPort  = isset($hostArray[1]) ? $hostArray[1] : ini_get("mysqli.default_port");

	  	$this->linkRes = new mysqli($hostArray[0],$dbinfo['user'],$dbinfo['passwd'],$dbinfo['name'],$hostPort);
	  	if($this->linkRes->connect_error)
	  	{
	  		throw new IException($this->linkRes->connect_error,1000);
	  		return false;
	  	}
	  	else
	  	{
		  	$DBCharset = isset(IWeb::$app->config['DB']['charset']) ? IWeb::$app->config['DB']['charset'] : 'utf8';
		  	$this->linkRes->set_charset($DBCharset);
		  	$this->linkRes->query("SET SESSION sql_mode = '' ");
		  	$this->linkRes->query("set global tx_isolation='read-uncommitted' ");
		  	return $this->linkRes;
	  	}
	}

	/**
	* @brief MYSQL的SQL执行的系统入口
	* @param string $sql 要执行的SQL语句
	* @return mixed
	*/
	public function doSql($sql)
	{
		//读操作
		$readyConf = array('select','show','describe');
		if(in_array(self::$sqlType,$readyConf))
		{
			return $this->read($sql,MYSQLI_ASSOC);
		}
		//写操作
		else
		{
			return $this->write($sql);
		}
	}

	/**
	* @brief 获取数据库内容
	* @param $sql SQL语句
	* @param $type 返回数据的键类型
	* @return array 查询结果集
	*/
	private function read($sql,$type = MYSQLI_ASSOC)
	{
		$result   = array();
		$resource = $this->linkRes->query($sql);

		if($resource)
		{
			while($data = $resource->fetch_array($type))
			{
				$result[] = $data;
			}
			$resource->free();
			return $result;
		}
		else
		{
			return false;
		}
	}

	/**
	* @brief 写入操作
	* @param string $sql SQL语句
	* @return int or bool 失败:false; 成功:影响的结果数量;
	*/
	private function write($sql)
	{
		$result = $this->linkRes->query($sql);

		if($result==true)
		{
			switch(self::$sqlType)
			{
				case "insert":
				{
					return $this->linkRes->insert_id;
				}
				break;

				case "update":
				{
					return $this->linkRes->affected_rows;
				}
				break;
				default:
				{
					return $result;
				}
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * @brief 开启事务处理
	 * @param boolean
	 */
	public function autoCommit()
	{
		$dataArray = $this->read("SELECT @@tx_isolation as isolation");
		if($dataArray && $dataArray[0]['isolation'] == "READ-UNCOMMITTED")
		{
		return $this->linkRes->autocommit(false);
		}
		return $this->linkRes->autocommit(true);
	}

	/**
	 * @brief 提交事务
	 * @param boolean
	 */
	public function commit()
	{
		return $this->linkRes->commit();
	}

	/**
	 * @brief 回滚事务
	 * @param boolean
	 */
	public function rollback()
	{
		return $this->linkRes->rollback();
	}
}