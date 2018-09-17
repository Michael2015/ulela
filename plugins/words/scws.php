<?php
/**
 * @copyright (c) 2014 jooyea.cn
 * @file scws.php
 * @brief 接口分词类
 * @author dabao
 * @date 2014/12/21 22:10:12
 * @version 0.1
 */
class scws implements wordsPart_inter
{
	/**
	 * @brief 获取提交按钮
	 * @return string
	 */
	public function getSubmitUrl()
	{
		return 'http://www.xunsearch.com/scws/api.php';
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
				$data[] = $val['word'];
			}
			return array('result' => 'success','data' => $data);
		}
		else
		{
			return array('result' => 'fail','data' => array());
		}
	}
}