<?php
/**
 * @copyright (c) 2016 aircheng.com
 * @file bosonWords.php
 * @brief 玻森分词系统
 * @see http://bosonnlp.com/
 * @author nswe
 * @date 2016/12/29 23:27:03
 * @version 4.7
 */
class bosonWords extends pluginBase implements wordsPart_inter
{
	public static function name()
	{
		return "玻森商品分词插件";
	}

	public static function description()
	{
		return "玻森数据专业关注数据分词技术！官网地址：<a href='http://bosonnlp.com' target='_blank'>http://bosonnlp.com/</a> 可以用于：(1)商品添加修改时对名称进行分词; (2)根据商品名称的分词形式进行查询";
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
			$this->goodsEditNameWord("/goods/goods_tags_words");
		});

		plugin::reg("onFinishView@seller@goods_edit",function(){
			$this->goodsEditNameWord("/seller/goods_tags_words");
		});

		//商品查询分词
		plugin::reg("onSearchGoodsWordsPart",$this,"run");
	}

	/**
	 * @brief 商品名称分词
	 * @param string $subUrl 提交地址
	 * @return string
	 */
	public function goodsEditNameWord($subUrl)
	{
		$url = IUrl::creatUrl($subUrl);

echo <<< OEF
<script type="text/javascript">
function wordsPart()
{
	var goodsName = $('input[name="name"]').val();
	if(goodsName)
	{
		$.getJSON("$url",{"content":goodsName},function(json)
		{
			if(json.result == 'success')
			{
				$('input[name="search_words"]').val(json.data);
			}
		});
	}
}

//绑定页面中的控件
$("input[name='name']").on("blur",wordsPart);
</script>
OEF;
	}

	/**
	 * @brief 获取提交按钮
	 * @return string
	 */
	public function getSubmitUrl()
	{
		return 'http://api.bosonnlp.com/tag/analysis';
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

	//插件默认配置
	public static function configName()
	{
		return 	array(
			'API_TOKEN' => array("name" => "API_TOKEN","type" => "text"),
		);
	}

	/**
	 * @brief 运行分词
	 * @param string $content 要分词的内容
	 * @return array 词语
	 */
	public function run($content)
	{
		$result = $this->curlSend(self::getSubmitUrl(),$content);
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
		//获取参数配置
		$API_TOKEN  = "t3skvsZF.11903.abEqecPajvli";
		$configData = $this->config();
		if(isset($configData['API_TOKEN']) && $configData['API_TOKEN'])
		{
			$API_TOKEN = $configData['API_TOKEN'];
		}

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => array(
				"Accept:application/json",
				"Content-Type: application/json",
				"X-Token: $API_TOKEN",
			),
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode(array($postData), JSON_UNESCAPED_UNICODE),
			CURLOPT_RETURNTRANSFER => true,
		));

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	/**
	 * @brief 处理规范统一的结果集
	 * @param string $result 要处理的返回值
	 * @return array 返回结果 array('result' => 'success 或者 fail','data' => array('分词数据'))
	 */
	public function response($result)
	{
		$resultArray = JSON::decode($result);
		if(!is_array($resultArray))
		{
			return array('result' => 'fail','data' => array());
		}
		$resultArray = current($resultArray);
		if(isset($resultArray['word']) && $resultArray['word'])
		{
			$data = array();
			foreach($resultArray['word'] as $key => $val)
			{
				if(strlen($val) >= 6)
				{
					$data[] = $val;
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