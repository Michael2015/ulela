<?php
/**
 * @copyright (c) 2015 dyg_jzw
 * @file yunpian.php
 * @brief **短信发送接口
 * @author nswe
 * @date 2015/8/11
 * @version 1.0
 */

 /**
 * @class yunpian
 * @brief 云片短信发送接口 http://www.yunpian.com/
 */
class yunpian extends hsmsBase
{
	private $submitUrl = "http://yunpian.com/v1/sms/send.json";

	/**
	 * @brief 获取config用户配置
	 * @return array
	 */
	private function getConfig()
	{
		//如果后台没有设置的话，这里手动配置也可以
		$siteConfigObj = new Config("site_config");

		return array(
			'apikey'	=> $siteConfigObj->sms_userid,
			'sign'		=> $siteConfigObj->name
		);
	}

	/**
	 * @brief 发送短信
	 * @param string $mobile
	 * @param string $content
	 * @return
	 */
	public function send($mobile,$content)
	{
		$config = self::getConfig();

		$apikey = $config['apikey'];
		$sign = $config['sign'];

		$url    = $this->submitUrl;
		
		$encoded_text = urlencode("【".$sign."】".$content);

		$post_string="apikey=$apikey&text=$encoded_text&mobile=$mobile";

		$result = $this->sock_post($url, $post_string);

		return $this->response($result);
	}

	/**
	 * @brief 获取参数
	 */
	public function getParam()
	{
		return array(
			"username" => "用户名",
			"userpwd"  => "密码",
			"usersign" => "短信签名",
		);
	}

	/**
	 * @brief 解析结果
	 * @param $result 发送结果
	 * @return string success or fail
	 */
	public function response($result)
	{
		$result = json_decode($result, true);

		if ($result['msg'] == "OK") 
		{
			return 'success';
		}
		else
		{
			return 'fail';
		}
	}

	/**
	* url 为服务的url地址
	* query 为请求串
	*/
	private function sock_post($url,$query){
		$data = "";
		$info=parse_url($url);
		$fp=fsockopen($info["host"],80,$errno,$errstr,30);
		if(!$fp){
			return $data;
		}
		$head="POST ".$info['path']." HTTP/1.0\r\n";
		$head.="Host: ".$info['host']."\r\n";
		$head.="Referer: http://".$info['host'].$info['path']."\r\n";
		$head.="Content-type: application/x-www-form-urlencoded\r\n";
		$head.="Content-Length: ".strlen(trim($query))."\r\n";
		$head.="\r\n";
		$head.=trim($query);
		$write=fputs($fp,$head);
		$header = "";
		while ($str = trim(fgets($fp,4096))) {
			$header.=$str;
		}
		while (!feof($fp)) {
			$data .= fgets($fp,4096);
		}
		return $data;
	}
}