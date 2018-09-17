<?php
/**
 * @copyright (c) 2016 aircheng.com
 * @file words.php
 * @brief 接口分词类
 * @author nswe
 * @date 2016/3/7 10:37:40
 * @version 4.4
 */
class words extends pluginBase implements wordsPart_inter
{
	public static function name()
	{
		return "SCWS商品分词插件";
	}

	public static function description()
	{
		return "1,商品添加修改时对名称进行分词; 2,商品列表查询分词";
	}

	public function reg()
	{
		plugin::reg("onBeforeCreateAction@goods@goods_tags_words",function(){
			self::controller()->goods_tags_words = function(){$this->goods_tags_words();};
		});

		plugin::reg("onBeforeCreateAction@seller@goods_tags_words",function(){
			self::controller()->goods_tags_words = function(){$this->goods_tags_words();};
		});

		plugin::reg("onFinishView@goods@goods_edit",function(){
			$this->view("bindJs","/goods/goods_tags_words");
		});

		plugin::reg("onFinishView@seller@goods_edit",function(){
			$this->view("bindJs","/seller/goods_tags_words");
		});

		//商品查询分词
		plugin::reg("onSearchGoodsWordsPart",$this,"run");
	}

	/**
	 * @brief 获取提交按钮
	 * @return string
	 */
	public function getSubmitUrl()
	{
		return 'http://www.xunsearch.com/scws/api.php';
	}

	//商品标签分词
	public function goods_tags_words()
	{
		$content = IFilter::act(IReq::get('content'));
		$words   = $this->run($content);

		$result = array('result' => 'fail');

		if(isset($words['data']) && $words['data'])
		{
			$result = array(
				'result' => 'success',
				'data'   => join(",",$words['data']),
			);

		}
		die( JSON::encode($result) );
	}

	/**
	 * @brief 运行分词
	 * @param string $content 要分词的内容
	 * @return array 词语
	 */
	public function run($content)
	{
		$postData = array(
			'data'       => $content,
			'respond'    => 'json',//php/json/xml,其中 php是指用php序列化后的结果
			'charset'    => 'utf8',//待分词的字符串编码 gbk/utf8，默认是utf8
			'ignore'     => 'yes',//是否忽略标点符号(yes/no，默认为 no)
			'duality'    => 'yes',// 是否散字自动二元(yes/no，默认为 no)
			'traditional'=> 'no',// 是否采用繁体字库(yes/no，默认为 no，仅当 charset 为 utf8 时有效)
            'multi'      => 1,// 复合分词的级别(整数值 1~15：0x01-最短词；0x02-二元；0x04-重要单字；0x08-全部单字) 默认为0，如有需要建议设置为 3
		);
		$result = $this->curlSend(self::getSubmitUrl(),$postData);
		return $this->response($result);
	}

	/**
	 * @brief 发送curl组建数据
	 * @param string $url 提交的api网址
	 * @param array $post 发送的接口参数
	 * @return mixed 返回的数据
	 */
	private function curlSend($url,$postData)
	{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_URL, $url);
        return curl_exec($ch);
	}

	/**
	 * @brief 处理规范统一的结果集
	 * @param string $result 要处理的返回值
	 * @return array 返回结果 array('result' => 'success 或者 fail','data' => array('分词数据'))
	 */
	public function response($result)
	{
		$resultArray = JSON::decode($result);
		if(isset($resultArray['status']) && $resultArray['status'] == 'ok')
		{
			$data = array();
			foreach($resultArray['words'] as $key => $val)
			{
				if($val['idf'] > 0)
				{
					$data[] = $val['word'];
				}
			}
			return array('result' => 'success','data' => $data);
		}
		else
		{
			return array('result' => 'fail','data' => array());
		}
	}
}