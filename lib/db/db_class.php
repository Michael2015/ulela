<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file db_class.php
 * @brief 数据库抽象父类
 * @author chendeshan
 * @date 2015/7/1 15:09:49
 * @version 4.4
 */

/**
* @class IDB
* @brief 数据库底层抽象类
*/
abstract class IDB
{
	//数据库写操作连接资源
	private static $wTarget = NULL;

	//数据库读操作连接资源
	private static $rTarget = NULL;

	//缓存类库实例
	private static $cacheTarget = NULL;

	//SQL类型
	protected static $sqlType = '';

	//原生态SQL语句
	private $sql;

	//记录执行日志 0:关闭; 1:开启;
	public $log;

	//缓存类型
	public $cache;

	//是否输出SQL
	public $debug;

	/**
	 * @brief 初始化基本配置
	 */
	public function init()
	{
		$this->cache = "";
		$this->log   = isset(IWeb::$app->config['sqlLog'])   ? IWeb::$app->config['sqlLog']   : 0;
		$this->debug = isset(IWeb::$app->config['sqlDebug']) ? IWeb::$app->config['sqlDebug'] : 0;
	}

	/**
	* @brief 获取SQL语句的类型,类型：select,update,insert,delete
	* @param string $sql 执行的SQL语句
	* @return string SQL类型
	*/
	private function getSqlType($sql)
	{
		$strArray = explode(' ',trim($sql),2);
		return strtolower($strArray[0]);
	}

	/**
	 * @brief 设置数据库读写分离并且执行SQL语句
	 * @param string $sql 要执行的SQL语句
	 * @return int or bool SQL语句执行的结果
	 */
    public function query($sql)
    {
    	$this->sql = $sql;

    	if($this->debug == 1)
    	{
			$this->debug();
    	}

    	if($this->log == 1)
    	{
			$this->log();
    	}

		//取得SQL类型
        self::$sqlType = $this->getSqlType($sql);

		//读方式
        if(self::$sqlType=='select' || self::$sqlType=='show')
        {
            //如果启用了缓存机制优先读取缓存数据
            if($this->cache)
            {
            	self::$cacheTarget = $this->getCacheTarget($this->cache);
            	$cacheKey          = md5($sql);
            	$result            = self::$cacheTarget->get($cacheKey);
            	if($result)
            	{
            		return $result;
            	}
            }

			//连接数据库
            if(self::$rTarget == NULL)
            {
				//多数据库支持并且读写分离
                if(isset(IWeb::$app->config['DB']['read']))
                {
					//获取ip地址
					$ip = IClient::getIP();
                    self::$rTarget = $this->connect(IHash::hash(IWeb::$app->config['DB']['read'],$ip));
                }
                //单数据库
                else
                {
                	self::$rTarget = $this->connect(IWeb::$app->config['DB']);
                }
            }
            $this->switchLink("r");
            $result = $this->doSql($sql);
            if($result === false)
            {
				throw new IException("{$sql}\n -- ".$this->linkRes->error,1000);
				return false;
            }

            //如果启用了缓存机制则保存结果数据
            isset($cacheKey) ? self::$cacheTarget->set($cacheKey,$result) : "";
            return $result;
        }
        //写方式
        else
        {
            if(self::$wTarget == NULL)
            {
				//多数据库支持并且读写分离
                if(isset(IWeb::$app->config['DB']['write']))
                {
                	self::$wTarget = $this->connect(IWeb::$app->config['DB']['write']);
                }
                else
                {
                	self::$wTarget = $this->connect(IWeb::$app->config['DB']);
                }

                //写链接启用事务
                $this->switchLink("w");
                $this->autoCommit();
            }
            $this->switchLink("w");
            $result = $this->doSql($sql);
            if($result === false)
            {
            	$errorMsg = $this->linkRes->error;
            	$this->rollback();
				throw new IException("{$sql}\n -- ".$errorMsg,1000);
				return false;
            }
            return $result;
        }
    }

	//析构函数
    public function __destruct()
    {
    	if(self::$wTarget)
    	{
    		$this->switchLink("w");
    		$this->commit();
    	}
    }

    //切换读写链接
    public function switchLink($type)
    {
    	return $this->linkRes = ($type == 'r') ? self::$rTarget : self::$wTarget;
    }

	//获取原生态SQL语句
    public function getSql()
    {
		return $this->sql;
    }

	//保存日志
    private function log()
    {
		//SQL语句
		list($usec, $sec) = explode(" ", microtime());
		$time   = ITime::getDateTime()." ".$usec;
		$string = $this->getSql();

		//获取引用堆栈
		$traceData      = array();
		$traceDataArray = debug_backtrace(false);
		array_shift($traceDataArray);
		array_shift($traceDataArray);
		$traceData = IException::formatTrace($traceDataArray);
		$logArray  = array(
			"SQL: ".$string,
			"TIME: ".$time,
			join("\n",$traceData)
		);
		$logString = join("\n",$logArray);
		$logString = "<SQL_BLOCK>\n".$logString."\n</SQL_BLOCK>\n\n";

		//创建文件记录日志
		$logInstance = new IFileLog("sql/".date("y-m-d").".log");
		return $logInstance->write($logString);
    }

	//打印调试SQL
    private function debug()
    {
		echo $this->getSql();
    }

	/**
	 * @brief 获取缓存实例对象
	 * @param $type string 缓存类型file,memcache
	 & @return cache 缓存对象
	 */
    public function getCacheTarget($type)
    {
    	if(self::$cacheTarget && self::$cacheTarget->getCacheType() == $type)
    	{
			return self::$cacheTarget;
    	}
    	return new ICache($type);
    }

	//数据库连接
    abstract public function connect($dbinfo);

	//执行sql通用接口
    abstract public function doSql($sql);
}