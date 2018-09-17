<?php
/**
 * EPay工具类
 *
 * @author xiezz
 * @version 1.1.2
 */
 
// 以下四个常量为SDK出现错误时，返回的字符串，可以根据需要自己设置（注意不是服务端返回的）
// 通讯失败时返回的报文
define('TXN_ERROR_RESULT', "{\"errcode\":\"EPAY_29001\",\"errmsg\":\"[EPAY_29001]通讯错误或超时，交易未决\"}");
// 系统异常时返回的报文
define('SYS_ERROR_RESULT', "{\"errcode\":\"EPAY_29099\",\"errmsg\":\"[EPAY_29099]未知错误，请检查是否为最新版本SDK或是否配置错误\"}");
// 对账文件下载，写入文件异常返回报文
define('FILE_ERROR_RESULT', "{\"errcode\":\"EPAY_29002\",\"errmsg\":\"[EPAY_29002]写入对账文件失败\"}");
// 验签失败
define('SIGN_ERROR_RESULT', "{\"errcode\":\"EPAY_29098\",\"errmsg\":\"[EPAY_29098]应答消息验签失败，交易未决\"}");
// 对账文件下载，下载成功返回报文
define('SUCCESS_RESULT', "{\"errcode\":\"EPAY_00000\",\"errmsg\":\"[EPAY_00000]下载成功\"}");

class EPay_util {
	
	/**
	 * 获取当前系统日期
	 * @return string	当前日期，20150801格式
	 */
	public static function getDate() {
		
		return date('Ymd');
	}
	
	/**
	 * 获取当前系统日期时间
	 * @return string	当前日期时间，20150801010203格式
	 */
	public static function getDateTime() {
		
		return date('YmdHis');
	}
	
	/**
	 * HTTP请求通讯器
	 * @param string	$url				通讯URL
	 * @param array		$param_array		参数列表
	 * @param boolean	$skip_ssl_verify	是否需要验证服务器证书，true-验证，false-不验证
	 * @param string	$save_file_name		结果保存至以该参数命名的文件，null-不保存直接返回内容
	 * @return string						服务端返回的结果（若$save_file_name不为null，下载成功返回SUCCESS_RESULT，下载成功写入文件失败返回FILE_ERROR_RESULT，下载失败返回服务端结果）
	 */
	public static function getHttpPostResponse($url, $param_array, $skip_ssl_verify = false, $save_file_name = null, $proxy_ip = null, $proxy_port = null) {
		
		$curl = curl_init($url);
		if(!$skip_ssl_verify) {
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__).'/ca-bundle.crt');	// 信任CA证书相对路径，如果不是在这里，需要修改该变更
		} else {
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		}
		
		if($proxy_ip) {
			curl_setopt($curl, CURLOPT_PROXY, $proxy_ip);
			curl_setopt($curl, CURLOPT_PROXYPORT, $proxy_port);
		}

		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($param_array));
		$response = curl_exec($curl);
		
		//需要调试时可以去掉以下两行的注释
		//var_dump(curl_error($curl));
		//var_dump(curl_getinfo($curl));
		
		$header = curl_getinfo($curl);
		curl_close($curl);
		
		if($header['http_code'] >= 300 || $header['http_code'] < 100)
			return TXN_ERROR_RESULT;

		//文件下载，且成功
		if($save_file_name && $header['content_type'] === 'application/octet-stream') {
			if(false === file_put_contents($save_file_name, $response))
				return FILE_ERROR_RESULT;
			else
				return SUCCESS_RESULT;
		} else {		//返回json或下载失败
			return $response;
		}
	}
	
	/**
	 * 获取服务器IP地址
	 * @return string	服务器IP地址
	 */
	public static function getLocalIp() {
		if(isset($_ENV["HOSTNAME"]))
			$machineName = $_ENV["HOSTNAME"];
		else if(isset($_ENV["COMPUTERNAME"]))
			$machineName = $_ENV["COMPUTERNAME"];
		else $machineName = '';
		return gethostbyname($machineName);
	}
}
