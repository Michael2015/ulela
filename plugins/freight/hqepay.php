<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file hqepay.php
 * @brief Hqepay的快递查询接口
 * @date 2014-18-18 9:34:18
 * @version 0.6
 */

/**
 * @class Hqepay
 * @brief Line的协议接口
 */
class Hqepay implements freight_inter
{
	private $appid  = '1232779';
	private $appkey = 'ac45f461-8c1a-4518-87b1-bb8e835a2f9d';

	/**
	 * @brief 构造函数从配置中获取id和key
	 * @param $id string 物流接口id码
	 * @param $key string 物流接口key码
	 */
	public function __construct($id,$key)
	{
		$this->appid  = $id ? $id : '1232779';
		$this->appkey = $key ? $key : 'ac45f461-8c1a-4518-87b1-bb8e835a2f9d';
	}

	/**
	 * @brief 获取api提交的url地址
	 * @return string url地址
	 */
	public function getSubmitUrl()
	{
		return 'http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';
	}

	/**
	 * @brief 获取物流轨迹线路
	 * @param $ShipperCode string 物流公司代号
	 * @param $LogisticCode string 物流单号
	 * @return string array 轨迹数据
	 */
	public function line($ShipperCode,$LogisticCode)
	{
		$params = array(
			'ShipperCode' => $ShipperCode,
			'LogisticCode'=> $LogisticCode,
		);

		$sendData = JSON::encode($params);
		$curlData = array(
			'RequestData' => $sendData,
			'EBusinessID' => $this->appid,
			'RequestType' => '1002',
			'DataType'    => 2,
			'DataSign'    => base64_encode(md5($sendData.$this->appkey)),
		);
		$result     = $this->curlSend($this->getSubmitUrl(),$curlData);
		$resultJson = JSON::decode($result);

		if(!$resultJson)
		{
			die(var_export($result));
		}
		return $this->response($resultJson);
	}

	/**
	 * @brief 处理返回数据统一数据格式
	 * @param $result 结果处理
	 * @return array 通用的结果集 array('result' => 'success或者fail','data' => array( array('time' => '时间','station' => '地点'),......),'reason' => '失败原因')
	 */
	public function response($result)
	{
		$status = "fail";
		$data   = array();
		$message= "此单号无跟踪记录";

		if(isset($result['Traces']) && $result['Traces'])
		{
			foreach($result['Traces'] as $key => $val)
			{
				$data[$key]['time']   = $val['AcceptTime'];
				$data[$key]['station']= $val['AcceptStation'];
			}
			$status = "success";
		}

		if(isset($result['Message']))
		{
			$message = $result['Message'];
		}
		else if(isset($result['Reason']))
		{
			$message = $result['Reason'];
		}
		return array('result' => $status,'data' => $data,'reason' => $message);
	}

	/**
	 * @brief CURL模拟提交数据
	 * @param $url string 提交的url
	 * @param $data array 要发送的数据
	 * @return mixed 返回的数据
	 */
	private function curlSend($url,$data)
	{
		$data = $this->encodeData($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0); //dyg_jzw 20160413修复curl设置错误
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
		return curl_exec($ch);
	}

	//进行数据的string字符串编码
	private function encodeData($datas)
	{
	    $temps = array();
	    foreach ($datas as $key => $value) {
	        $temps[] = sprintf('%s=%s', $key, $value);
	    }
	    $post_data = join('&', $temps);
	    return $post_data;
	}
}
