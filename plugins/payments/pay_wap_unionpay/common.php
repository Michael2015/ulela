<?php
class Common
{
	// cvn2加密 1：加密 0:不加密
	const SDK_CVN2_ENC = 0;
	// 有效期加密 1:加密 0:不加密
	const SDK_DATE_ENC = 0;
	// 卡号加密 1：加密 0:不加密
	const SDK_PAN_ENC = 0;

	// ######(以下配置为PM环境：入网测试环境用，生产环境配置见文档说明)#######
	// 签名证书（私钥）路径
	const SDK_SIGN_CERT_PATH = 'key/700000000000001_acp.pfx';
	// 签名证书密码
	static $SDK_SIGN_CERT_PWD = '000000';
	// 验签证书
	const SDK_ENCRYPT_CERT_PATH = 'key/verify_sign_acp.cer';

	// 验签证书路径（请配到文件夹，不要配到具体文件）
	const SDK_VERIFY_CERT_DIR ='key';

	static function setCertPwd($cert_pwd){
		self::$SDK_SIGN_CERT_PWD = $cert_pwd;
	}

	/**
	 * 数组 排序后转化为字体串
	 *
	 * @param array $params
	 * @return string
	 */
	static function coverParamsToString($params)
	{
		$sign_str = '';
		// 排序
		ksort ( $params );
		foreach ( $params as $key => $val )
		{
			if ($key == 'signature')
			{
				continue;
			}
			$sign_str .= sprintf ( "%s=%s&", $key, $val );
			// $sign_str .= $key . '=' . $val . '&';
		}
		return substr ( $sign_str, 0, strlen ( $sign_str ) - 1 );
	}

	/**
	 * 字符串转换为 数组
	 *
	 * @param unknown_type $str
	 * @return multitype:unknown
	 */
	static function coverStringToArray($str) {
		$result = array ();

		if (! empty ( $str ))
		{
			$temp = preg_split ( '/&/', $str );
			if (! empty ( $temp ))
			{
				foreach ( $temp as $key => $val ) {
					$arr = preg_split ( '/=/', $val, 2 );
					if (! empty ( $arr )) {
						$k = $arr ['0'];
						$v = $arr ['1'];
						$result [$k] = $v;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * 处理返回报文 解码客户信息 , 如果编码为utf-8 则转为utf-8
	 *
	 * @param unknown_type $params
	 */
	static function deal_params(&$params)
	{
		/**
		 * 解码 customerInfo
		 */
		if (! empty ( $params ['customerInfo'] ))
		{
			$params ['customerInfo'] = base64_decode ( $params ['customerInfo'] );
		}

		if (! empty ( $params ['encoding'] ) && strtoupper ( $params ['encoding'] ) == 'utf-8')
		{
			foreach ( $params as $key => $val )
			{
				$params [$key] = iconv ( 'utf-8', 'UTF-8', $val );
			}
		}
	}

	/**
	 * 压缩文件 对应java deflate
	 *
	 * @param unknown_type $params
	 */
	static function deflate_file(&$params)
	{
		foreach ( $_FILES as $file )
		{
			if (file_exists ( $file ['tmp_name'] ))
			{
				$params ['fileName'] = $file ['name'];

				$file_content = file_get_contents ( $file ['tmp_name'] );
				$file_content_deflate = gzcompress ( $file_content );

				$params ['fileContent'] = base64_encode ( $file_content_deflate );
			}
			else
			{
				exit(">>>>文件上传失败<<<<<");
			}
		}
	}

	/**
	 * 处理报文中的文件
	 *
	 * @param unknown_type $params
	 */
	static function deal_file($params)
	{
		if (isset ( $params ['fileContent'] ))
		{
			$fileContent = $params ['fileContent'];

			if (empty ( $fileContent ))
			{
				exit ( '文件内容为空' );
			}
			else
			{
				// 文件内容 解压缩
				$content = gzuncompress ( base64_decode ( $fileContent ) );
				$root = self::SDK_FILE_DOWN_PATH;
				$filePath = null;
				if (empty ( $params ['fileName'] ))
				{
					exit( "文件名为空" );
					$filePath = $root . $params ['merId'] . '_' . $params ['batchNo'] . '_' . $params ['txnTime'] . '.txt';
				}
				else
				{
					$filePath = $root . $params ['fileName'];
				}
				$handle = fopen ( $filePath, "w+" );
				if (! is_writable ( $filePath ))
				{
					exit ( "文件:" . $filePath . "不可写，请检查！" );
				}
				else
				{
					file_put_contents ( $filePath, $content );
					exit ( "文件位置 >:" . $filePath );
				}
				fclose ( $handle );
			}
		}
	}

	/**
	 * 构造自动提交表单
	 *
	 * @param unknown_type $params
	 * @param unknown_type $action
	 * @return string
	 */
	static function create_html($params, $action)
	{
		$encodeType = isset ( $params ['encoding'] ) ? $params ['encoding'] : 'UTF-8';
		$html = <<<eot
<html>
	<head>
	    <meta http-equiv="Content-Type" content="text/html; charset={$encodeType}" />
	</head>
	<body  onload="javascript://document.pay_form.submit();">
	    <form id="pay_form" name="pay_form" action="{$action}" method="post">

	eot;
		foreach ( $params as $key => $value ) {
			$html .= "    <input type=\"hidden\" name=\"{$key}\" id=\"{$key}\" value=\"{$value}\" />\n";
		}
		$html .= <<<eot
	    <input type="submit" type="hidden">
	    </form>
	</body>
</html>
eot;
		return $html;
	}


	/*********************************
	* securUtil
	**********************************
	*/

	/**
	 * 签名
	 *
	 * @param String $params_str
	 */
	static function sign(&$params)
	{
		if(isset($params['transTempUrl']))
		{
			unset($params['transTempUrl']);
		}
		// 转换成key=val&串
		$params_str = self::coverParamsToString ( $params );

		$params_sha1x16 = sha1 ( $params_str, FALSE );
		// 签名证书路径
		$cert_path = self::SDK_SIGN_CERT_PATH;
		$private_key = self::getPrivateKey ( $cert_path );
		// 签名
		$sign_falg = openssl_sign ( $params_sha1x16, $signature, $private_key, OPENSSL_ALGO_SHA1 );
		if ($sign_falg)
		{
			$signature_base64 = base64_encode ( $signature );
			$params ['signature'] = $signature_base64;
		}
		else
		{
			exit ( ">>>>>签名失败<<<<<<<" );
		}
	}

	/**
	 * 验签
	 *
	 * @param String $params_str
	 * @param String $signature_str
	 */
	static function verify($params)
	{
		$public_key = self::getPulbicKeyByCertId ( $params ['certId'] );
		//	echo $public_key.'<br/>';
		// 签名串
		$signature_str = $params ['signature'];
		unset ( $params ['signature'] );
		$params_str = self::coverParamsToString ( $params );
		$signature = base64_decode ( $signature_str );
		//	echo date('Y-m-d',time());
		$params_sha1x16 = sha1 ( $params_str, FALSE );
		$isSuccess = openssl_verify ( $params_sha1x16, $signature,$public_key, OPENSSL_ALGO_SHA1 );
		return $isSuccess;
	}

	/**
	 * 根据证书ID 加载 证书
	 *
	 * @param unknown_type $certId
	 * @return string NULL
	 */
	static function getPulbicKeyByCertId($certId)
	{
		// 证书目录
		$cert_dir = self::SDK_VERIFY_CERT_DIR;
		$handle = opendir ( dirname(__FILE__)."/".$cert_dir );
		if ($handle)
		{
			while (( $file = readdir ( $handle ) )!== false)
			{
				clearstatcache ();
				$filePath = $cert_dir . '/' . $file;
				if (is_file ( dirname(__FILE__)."/".$filePath ))
				{
					if (pathinfo ( $file, PATHINFO_EXTENSION ) == 'cer')
					{
						if (self::getCertIdByCerPath ( $filePath ) == $certId)
						{
							closedir ( $handle );
							return self::getPublicKey ( $filePath );
						}
					}
				}
			}
		}
		else
		{
			exit ( '证书目录 ' . $cert_dir . '不正确' );
		}
		closedir ( $handle );
		die("在证书目录中没有找到 <公钥验签证书> ");
	}

	/**
	 * 取证书ID(.pfx)
	 *
	 * @return unknown
	 */
	static function getCertId($cert_path)
	{
		$pkcs12certdata = file_get_contents ( dirname(__FILE__).'/'.$cert_path );
		openssl_pkcs12_read ( $pkcs12certdata, $certs, self::$SDK_SIGN_CERT_PWD );
		$x509data = $certs ['cert'];
		openssl_x509_read ( $x509data );
		$certdata = openssl_x509_parse ( $x509data );
		$cert_id = $certdata ['serialNumber'];
		return $cert_id;
	}

	/**
	 * 取证书ID(.cer)
	 *
	 * @param unknown_type $cert_path
	 */
	static function getCertIdByCerPath($cert_path)
	{
		$x509data = file_get_contents (dirname(__FILE__).'/'.$cert_path );
		openssl_x509_read ( $x509data );
		$certdata = openssl_x509_parse ( $x509data );
		$cert_id = $certdata ['serialNumber'];
		return $cert_id;
	}

	/**
	 * 签名证书ID
	 *
	 * @return unknown
	 */
	static function getSignCertId()
	{
		// 签名证书路径
		return self::getCertId ( self::SDK_SIGN_CERT_PATH );
	}
	static function getEncryptCertId()
	{
		// 签名证书路径
		return self::getCertIdByCerPath ( self::SDK_ENCRYPT_CERT_PATH );
	}

	/**
	 * 取证书公钥 -验签
	 *
	 * @return string
	 */
	static function getPublicKey($cert_path)
	{
		return file_get_contents ( dirname(__FILE__).'/'.$cert_path );
	}
	/**
	 * 返回(签名)证书私钥 -
	 *
	 * @return unknown
	 */
	static function getPrivateKey($cert_path)
	{
		$pkcs12 = file_get_contents ( dirname(__FILE__).'/'.$cert_path );
		openssl_pkcs12_read ( $pkcs12, $certs, self::$SDK_SIGN_CERT_PWD );
		return $certs ['pkey'];
	}

	/**
	 * 加密 卡号
	 *
	 * @param String $pan
	 *        	卡号
	 * @return String
	 */
	static function encryptPan($pan)
	{
		$cert_path = MPI_ENCRYPT_CERT_PATH;
		$public_key = getPublicKey ( $cert_path );

		openssl_public_encrypt ( $pan, $cryptPan, $public_key );
		return base64_encode ( $cryptPan );
	}
	/**
	 * pin 加密
	 *
	 * @param unknown_type $pan
	 * @param unknown_type $pwd
	 * @return Ambigous <number, string>
	 */
	static function encryptPin($pan, $pwd)
	{
		$cert_path = self::SDK_ENCRYPT_CERT_PATH;
		$public_key = getPublicKey ( $cert_path );

		return EncryptedPin ( $pwd, $pan, $public_key );
	}
	/**
	 * cvn2 加密
	 *
	 * @param unknown_type $cvn2
	 * @return unknown
	 */
	static function encryptCvn2($cvn2)
	{
		$cert_path = self::SDK_ENCRYPT_CERT_PATH;
		$public_key = getPublicKey ( $cert_path );

		openssl_public_encrypt ( $cvn2, $crypted, $public_key );

		return base64_encode ( $crypted );
	}
	/**
	 * 加密 有效期
	 *
	 * @param unknown_type $certDate
	 * @return unknown
	 */
	static function encryptDate($certDate)
	{
		$cert_path = self::SDK_ENCRYPT_CERT_PATH;
		$public_key = getPublicKey ( $cert_path );

		openssl_public_encrypt ( $certDate, $crypted, $public_key );

		return base64_encode ( $crypted );
	}

	/**
	 * 加密 数据
	 *
	 * @param unknown_type $certDatatype
	 * @return unknown
	 */
	static function encryptDateType($certDataType)
	{
		$cert_path = self::SDK_ENCRYPT_CERT_PATH;
		$public_key = getPublicKey ( $cert_path );
		openssl_public_encrypt ( $certDataType, $crypted, $public_key );
		return base64_encode ( $crypted );
	}



	/*********************************
	* PublicEncrypte.Util
	**********************************
	*/

	static function EncryptedPin($sPin, $sCardNo ,$sPubKeyURL)
	{

		$sPubKeyURL = trim(self::SDK_ENCRYPT_CERT_PATH," ");
	//	$log->LogInfo("DisSpaces : " . PubKeyURL);
		$fp = fopen($sPubKeyURL, "r");
		if ($fp != NULL)
		{
			$sCrt = fread($fp, 8192);
			fclose($fp);
		}
		$sPubCrt = openssl_x509_read($sCrt);
		if ($sPubCrt === FALSE)
		{
			print("openssl_x509_read in false!");
			return (-1);
		}
		//	$sPubKeyId = openssl_x509_parse($sCrt);
		$sPubKey = openssl_x509_parse($sPubCrt);
		//	openssl_x509_free($sPubCrt);
		//	print_r(openssl_get_publickey($sCrt));

		$sInput = Pin2PinBlockWithCardNO($sPin, $sCardNo);
		if ($sInput == 1)
		{
			print("Pin2PinBlockWithCardNO Error ! : " . $sInput);
			return (1);
		}
		$iRet = openssl_public_encrypt($sInput, $sOutData, $sCrt, OPENSSL_PKCS1_PADDING);
		if ($iRet === TRUE)
		{
			$sBase64EncodeOutData = base64_encode($sOutData);
			//print("PayerPin : " . $sBase64EncodeOutData);
			return $sBase64EncodeOutData;
		}
		else
		{
			print("openssl_public_encrypt Error !");
			return (-1);
		}
	}


	/**
	* PinBlock
	*/

		/**
	 * Author: gu_yongkang
	 * data: 20110510
	 * 密码转PIN
	 * Enter description here ...
	 * @param $spin
	 */
	static function  Pin2PinBlock( &$sPin )
	{
	//	$sPin = "123456";
		$iTemp = 1;
		$sPinLen = strlen($sPin);
		$sBuf = array();
		//密码域大于10位
		$sBuf[0]=intval($sPinLen, 10);

		if($sPinLen % 2 ==0)
		{
			for ($i=0; $i<$sPinLen;)
			{
				$tBuf = substr($sPin, $i, 2);
				$sBuf[$iTemp] = intval($tBuf, 16);
				unset($tBuf);
				if ($i == ($sPinLen - 2))
				{
					if ($iTemp < 7)
					{
						$t = 0;
						for ($t=($iTemp+1); $t<8; $t++)
						{
							$sBuf[$t] = 0xff;
						}
					}
				}
				$iTemp++;
				$i = $i + 2;	//linshi
			}
		}
		else
		{
			for ($i=0; $i<$sPinLen;)
			{
				if ($i ==($sPinLen-1))
				{
					$mBuf = substr($sPin, $i, 1) . "f";
					$sBuf[$iTemp] = intval($mBuf, 16);
					unset($mBuf);
					if (($iTemp)<7)
					{
						$t = 0;
						for ($t=($iTemp+1); $t<8; $t++)
						{
							$sBuf[$t] = 0xff;
						}
					}
				}
				else
				{
					$tBuf = substr($sPin, $i, 2);
					$sBuf[$iTemp] = intval($tBuf, 16);
					unset($tBuf);
				}
				$iTemp++;
				$i = $i + 2;
			}
		}
		return $sBuf;
	}
	/**
	 * Author: gu_yongkang
	 * data: 20110510
	 * Enter description here ...
	 * @param $sPan
	 */
	static function FormatPan(&$sPan)
	{
		$iPanLen = strlen($sPan);
		$iTemp = $iPanLen - 13;
		$sBuf = array();
		$sBuf[0] = 0x00;
		$sBuf[1] = 0x00;
		for ($i=2; $i<8; $i++)
		{
			$tBuf = substr($sPan, $iTemp, 2);
			$sBuf[$i] = intval($tBuf, 16);
			$iTemp = $iTemp + 2;
		}
		return $sBuf;
	}

	static function Pin2PinBlockWithCardNO(&$sPin, &$sCardNO)
	{
		global $log;
		$sPinBuf = Pin2PinBlock($sPin);
		$iCardLen = strlen($sCardNO);
		if ($iCardLen <= 10)
		{
			return (1);
		}
		elseif ($iCardLen==11)
		{
			$sCardNO = "00" . $sCardNO;
		}
		elseif ($iCardLen==12)
		{
			$sCardNO = "0" . $sCardNO;
		}
		$sPanBuf = FormatPan($sCardNO);
		$sBuf = array();

		for ($i=0; $i<8; $i++)
		{
			//$sBuf[$i] = $sPinBuf[$i] ^ $sPanBuf[$i];	//十进制
			//$sBuf[$i] = vsprintf("%02X", ($sPinBuf[$i] ^ $sPanBuf[$i]));
			$sBuf[$i] = vsprintf("%c", ($sPinBuf[$i] ^ $sPanBuf[$i]));
		}
		unset($sPinBuf);
		unset($sPanBuf);
		//return $sBuf;
		$sOutput = implode("", $sBuf);	//数组转换为字符串
		return $sOutput;
	}
}
?>