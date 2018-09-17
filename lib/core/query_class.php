<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file query_class.php
 * @brief 系统统一查询类文件，处理复杂的查询问题
 * @author nswe
 * @date 2016/3/31 21:37:40
 * @version 4.6
 * @update 2016/8/24 8:50:10 升级分页处理，去掉默认读取5000条数据的限制
 */
/**
 * @brief IQuery 系统统一查询类
 * @class IQuery
 * @note
 */
class IQuery
{
	private $dbo      = null;
	private $sql      = array('table'=>'','fields'=>'*','where'=>'','join'=>'','group'=>'','having'=>'','order'=>'','limit'=>'');
	private $tablePre = '';
	public  $paging   = null;//分页类库

    /**
     * @brief 构造函数
     * @param string $name     表名
     */
	public function __construct($name)
	{
		$this->tablePre = IWeb::$app->config['DB']['tablePre'];
		$this->table    = $name;
		$this->dbo=IDBFactory::getDB();
	}
    /**
     * @brief 给表添加表前缀
     * @param string $name 可以是多个表名用逗号(,)分开
     */
	public function setTable($name)
	{
		if(strpos($name,',') === false)
		{
			$this->sql['table']= $this->tablePre.trim($name);
		}
		else
		{
			$tables = explode(',',$name);
			foreach($tables as $key=>$value)
			{
				$tables[$key] = $this->tablePre.trim($value);
			}
			$this->sql['table'] = implode(',',$tables);
		}
	}
    /**
     * @brief 取得表前缀
     * @return String 表前缀
     */
    public function getTablePre()
    {
        return $this->tablePre;
    }
    /**
     * @brief 设置where子句数据
     * @return String
     */
    public function setWhere($str)
    {
    	if($str)
    	{
    		$exp = array('/from\s+(\S+)(?=$|\s+where)/i','/(\w+)(?=\s+as\s+\w+(,|\)|\s))/i');
    		$rep = array("from {$this->tablePre}$1 ","{$this->tablePre}$1 ");
    		$this->sql['where'] = 'where '.preg_replace($exp,$rep,$str);
    	}
    }
    /**
     * @brief 取得where子句数据
     * @return String
     */
    public function getWhere()
    {
    	return ltrim($this->sql['where'],'where ');
    }
    /**
     * @brief 实现属性的直接存
     * @param string $name
     * @param string $value
     */
    private function setJoin($str)
    {
		$this->sql['join'] = preg_replace('/(\w+)(?=\s+as\s+\w+(,|\)|\s))/i',"{$this->tablePre}$1 ",$str);
    }
	public function __set($name,$value)
	{
		switch($name)
		{
			case 'table':$this->setTable($value);break;
			case 'fields':$this->sql['fields'] = $value;break;
			case 'where':$this->setWhere($value);break;
			case 'join':$this->setJoin($value);break;
			case 'group':$this->sql['group'] = 'GROUP BY '.$value;break;
			case 'having':$this->sql['having'] = 'having '.$value;break;
			case 'order':$this->sql['order'] = 'order by '.$value;break;
			case 'limit':$value == 'all' ? ($this->sql['limit'] = '') : ($this->sql['limit'] = 'limit '.$value);break;
            case 'page':$this->sql['page'] =intval($value); break;
            case 'pagesize':$this->sql['pagesize'] =intval($value); break;
            case 'pagelength':$this->sql['pagelength'] =intval($value); break;
			case 'cache':
			{
				$this->dbo->cache = $value;
			}
			break;
			case 'debug':
			{
				$this->dbo->debug = $value;
			}
			break;
			case 'log':
			{
				$this->dbo->log = $value;
			}
			break;
		}
	}
    /**
     * @brief 实现属性的直接取
     * @param mixed $name
     * @return String
     */
	public function __get($name)
	{
		if(isset($this->sql[$name]))return $this->sql[$name];
	}

    public function __isset($name)
    {
        if(isset($this->sql[$name]))return true;
    }
    /**
     * @brief 取得查询结果
     * @return array
     */
	public function find()
	{
		$sql    = $this->getSql();
		$result = array();

		//分页SQL处理
        if($this->page)
        {
			$pagesize     = isset($this->pagesize)  ? intval($this->pagesize)  :20;
            $pagelength   = isset($this->pagelength)? intval($this->pagelength):10;
			$this->paging = new IPaging($sql,$pagesize,$pagelength,$this->dbo);
			$result       = $this->paging->getPage($this->page);
		}
		else
        {
            $result = $this->dbo->query($sql);
        }
        return $result;
	}
	/**
	 * @brief 分页展示
	 * @param string $url   点击分页按钮要跳转的URL地址，如果为空表示当前URL地址
	 * @param string $attrs URL后接参数
	 * @return string pageBar的对应HTML代码
	 */
    public function getPageBar($url='',$attrs='')
    {
        return $this->paging->getPageBar($url,$attrs);
    }

	/**
	 * @brief 获取原生态的SQL
	 * @return sql语句
	 */
    public function getSql()
    {
    	return "select $this->fields from $this->table $this->join $this->where $this->group $this->having $this->order $this->limit";
    }
}