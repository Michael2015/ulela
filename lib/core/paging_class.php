<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file paging_class.php
 * @brief 分页处理类
 * @author nswe
 * @date 2016/4/1 0:23:23
 * @version 4.4
 * @note
 */
/**
 * @brief IPaging 分页处理类
 * @class IPaging
 * @note
 */
class IPaging
{
	private $dbo;
	private $sql;//未分页前的SQL原生语句
	public $rows;
	public $index;//当前页数
	public $totalpage;//总页数
	public $pagesize;//每页的条数
	public $firstpage;//第一页
	public $lastpage;//最后一页
	public $pagelength;//要展示的页面数
    /**
     * @brief 构造函数
     * @param string $sql 要分页的SQL语句
     * @param int $pagesize 每页的记录
     * @param int $pagelength 展示pageBar的页数
     * @param db  $dbo 数据库实例对象
     */
	public function __construct($sql="",$pagesize=20,$pagelength=10,$dbo = null)
	{
		$this->pagesize  = $pagesize;
		$this->pagelength= $pagelength;
		$this->dbo       = $dbo ? $dbo : IDBFactory::getDB();
		if($sql)
		{
			$this->setSql($sql);
		}
	}
    /**
     * @brief 分析要分页的SQl语句
     * @param string $sql SQL语句
     */
	public function setSql($sql)
	{
		$this->sql = $sql;
		$endstr    = strstr($this->sql,'from');

        if(strpos($sql,'GROUP BY') === false)
        {
	        $endstr = preg_replace('/^(.*)order\s+by.+$/i','$1',$endstr);
        	$count  = $this->dbo->query("select count(*) as total ".$endstr);
        }
        else
        {
        	//获取分组的字段
	        preg_match("|(?<=GROUP BY)\s+[\w\.]+|",$endstr,$match);
	        $groupCol = $match ? current($match) : "";
			$groupCol = trim($groupCol);
	        $endstr = preg_replace('/^(.*)GROUP\s+BY.+$/i','$1',$endstr);
        	$count  = $this->dbo->query("select count(DISTINCT {$groupCol}) as total ".$endstr);
        }

		$this->rows=isset($count[0]['total']) ? $count[0]['total'] : 0;
		$this->firstpage=1;
		$this->totalpage=floor(($this->rows-1)/$this->pagesize)+1;
		$this->lastpage=$this->firstpage+$this->totalpage-1;
		if($this->lastpage>$this->totalpage)$this->lastpage=$this->totalpage;
	}
    /**
     * @brief 得到对应要查询分页的数据内容
     * @param int  $page要查询的页数
     * @return Array 数据
     */
	public function getPage($page)
	{
		$page=intval($page);
		$this->index=$page;
		if($page<=0)$this->index=1;
		if($this->totalpage>0)
		{
			if($page>$this->totalpage)$this->index=$this->totalpage;
			$this->firstpage=$this->index-floor($this->pagelength/2);
			if($this->firstpage<=0)$this->firstpage=1;
			$this->lastpage=$this->firstpage+$this->pagelength-1;
			if($this->lastpage>$this->totalpage)
			{
				$this->lastpage=$this->totalpage;
				$this->firstpage=($this->totalpage-$this->pagelength+1)>1?$this->totalpage-$this->pagelength+1:1;
			}
			$wholeSql = $this->sql." limit ".($this->index-1)*$this->pagesize.",".($this->pagesize);
			return $this->dbo->query($wholeSql);
		}
		return array();
	}
    /**
     * @brief 获取当前分页数
	 * @return int 分页数
	 */
	public function getIndex()
	{
		return $this->index;
	}
    /**
     * @brief 获取分页总数
	 * @return int 分页总数
	 */
	public function getTotalPage()
	{
		return $this->totalpage;
	}
    /**
     * @brief 设置展示的分页数量
	 * @return int 分页数量
	 */
	public function setPageLength($legth)
	{
		$this->pagelength=$legth;
	}
    /**
     * @brief 获取展示的分页数量
	 * @return int 分页长度
	 */
	public function getPageLength()
	{
		return $this->pagelength;
	}
    /**
     * @brief 设置每页的数据条数
     * @return int 数据条数
	 */
	public function setPageSize($size)
	{
		$this->pagesize  = $size;
		$this->totalpage = floor(($this->rows-1)/$this->pagesize)+1;
	}
    /**
     * @brief 得到单页要展示的数据条数
     * @return int 数据条数
     */
	public function getPageSize()
	{
		return $this->pagesize;
	}
    /**
     * @brief 当前pageBar的第一页
     * @return int 当前pageBar的第一页
     */
	public function getFirstPage()
	{
		return $this->firstpage;
	}
    /**
     * @brief 当前pageBar最得最后一页的页数
     * @return int 当前pageBar最后一页的页数
     */
	public function getLastPage()
	{
		return $this->lastpage;
	}
    /**
     * @brief 获取处理后的分页URL
     * @return string URL地址
     */
	public function getPageBarUrl()
	{
		$url   = IUrl::getUri();
		$parse = parse_url($url);
		if(isset($parse['query']))
		{
			//把url字符串解析为数组
			parse_str($parse['query'],$params);
			//删除数组下标为page的值
			unset($params['page']);
			//再次构建url
			$url = $parse['path'].'?'.http_build_query($params);
		}
		$url .= strpos($url,"?") === false ? "?page=" : "&page=";
		return $url;
	}
    /**
     * @brief 取得pageBar
     * @param string $url 点击分页按钮跳转的URL地址，为空表示当前URL
     * @param string $attrs URL后接参数
     * @return string pageBar的对应HTML代码
     */
	public function getPageBar($url='', $attrs='')
	{
		//数据不存在直接返回空
		if($this->totalpage == 0)
        {
			return;
        }

		//URL参数是否存在
		$baseUrl = $url ? $url  : $this->getPageBarUrl();
        $baseUrl = IFilter::act($baseUrl,'text');
		$flag    = $url ? false : true;

		//HTML内容拼接开始
		//1,首页
        $attr = str_replace('[page]',1,$attrs);
		$href   = $baseUrl.($flag ? 1 : "");
		$result = "<div class='pages_bar'><a href='{$href}' {$attr}>首页</a>";

		//2,上一页
		if($this->firstpage > 1)
		{
        $attr = str_replace('[page]',$this->getIndex()-1,$attrs);
	        $href = $baseUrl.($flag ? $this->getIndex()-1 : "");
			$result .= "<a href='{$href}' {$attr}>上一页</a>";
		}

		//3,中间循环分页
		for($i=$this->firstpage;$i<=$this->lastpage;$i++)
		{
            $attr = str_replace('[page]',$i,$attrs);
            $href = $baseUrl.($flag ? $i : "");
			if($i==$this->index)
			{
				$result .= "<a class='current_page' href='{$href}' {$attr}>{$i}</a>";
			}
			else
			{
				$result .= "<a href='{$href}' {$attr}>{$i}</a>";
			}
		}

		//4,下一页
		if($this->lastpage < $this->totalpage)
		{
        $attr = str_replace('[page]',$this->getIndex()+1,$attrs);
	        $href    = $baseUrl.($flag ? $this->getIndex()+1 : "");
			$result .= "<a href='{$href}' {$attr}>下一页</a>";

		}
		//5,尾页
		$attr = str_replace('[page]',$this->totalpage,$attrs);
		$href = $baseUrl.($flag ? $this->totalpage : "");
		$result .= "<a href='{$href}' {$attr}>尾页</a>";
		//6,统计数据
		$result .= "<span>当前第{$this->index}页/共{$this->totalpage}页</span></div>";
		return $result;
	}
}