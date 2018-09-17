<?php
/**
 * @copyright (c) 2014 jooyea.cn
 * @file words_facade.php
 * @brief 分词类
 * @author dabao
 * @date 2014/12/22 20:17:33
 * @version 0.1
 */
class words_facade
{
	public static $wordsName = 'scws';
	public static $instance  = null;

	/**
	 * @brief 创建分词类库实例
	 */
	private static function createInstance()
	{
		if(self::$instance == null)
		{
			switch(self::$wordsName)
			{
				default:
				{
					$classFile = IWeb::$app->getBasePath().'plugins/words/scws.php';
					if(is_file($classFile))
					{
						include_once($classFile);
						self::$instance = new scws();
					}
				}
				break;
			}
		}
		return self::$instance;
	}

	/**
	 * @brief 运行分词
	 * @param string $content 要分词的内容
	 * @return array 词语
	 */
	public static function run($content)
	{
		$instance = self::createInstance();
		if($instance)
		{
			return $instance->run($content);
		}
		return $content;
	}
}

/**
 * @brief 分词接口
 */
interface wordsPart_inter
{
	/**
	 * @brief 运行分词
	 * @param string $content 要分词的内容
	 * @return array 词语
	 */
	public function run($content);

	/**
	 * @brief 处理规范统一的结果集
	 * @param string $result 要处理的返回值
	 * @return array 返回结果 array('result' => 'success 或者 fail','data' => array('分词数据'))
	 */
	public function response($result);
}