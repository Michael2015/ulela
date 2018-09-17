<?php
/**
 * @brief csv商品导入外观模式
 * @author chendeshan
 * @date 2014/1/2 8:32:57
 */
class csvimport_facade
{
	//商家ID
	public static $seller_id = 0;

	/**
	 * @brief 开始运行
	 */
	public static function run()
	{
		set_time_limit(0);
		ini_set("max_execution_time",0);

		$csvType  = IReq::get('csvType');
		$category = IFilter::act(IReq::get('category'),'int');

		$pluginDir = IWeb::$app->getBasePath().'plugins/csvPacketHelper/';
		if(!file_exists($pluginDir))
		{
			die('此功能仅供授权版本使用，请您购买商业授权');
		}

		if(!class_exists('ZipArchive'))
		{
			die('服务器环境中没有安装zip扩展，无法使用此功能');
		}

		if(extension_loaded('mbstring') == false)
		{
			die('服务器环境中没有安装mbstring扩展，无法使用此功能');
		}

		//处理上传
		$uploadInstance = new IUpload(9999999,array('zip'));
		$uploadCsvDir   = 'runtime/cvs/'.date('YmdHis');
		$uploadInstance->setDir($uploadCsvDir);
		$result = $uploadInstance->execute();

		if(!isset($result['csvPacket']))
		{
			die('请上传指定大小的csv数据包');
		}

		if(($packetData = current($result['csvPacket'])) && $packetData['flag'] != 1)
		{
			$message = $uploadInstance->errorMessage($packetData['flag']);
			die($message);
		}

		$zipPath = $packetData['fileSrc'];
		$zipDir  = dirname($zipPath);
		$imageDir= IWeb::$app->config['upload'].'/'.date('Y/m/d');
		file_exists($imageDir) ? '' : IFile::mkdir($imageDir);

		//解压缩包
		$zipObject = new ZipArchive();
		$zipObject->open($zipPath);
		$isExtract = $zipObject->extractTo($zipDir);
		$zipObject->close();

		if($isExtract == false)
		{
			$message = '解压缩到目录'.$zipDir.'失败！';
			die($message);
		}

		//实例化商品
		$goodsObject     = new IModel('goods');
		$photoRelationDB = new IModel('goods_photo_relation');
		$photoDB         = new IModel('goods_photo');
		$cateExtendDB    = new IModel('category_extend');

		//获得配置文件中的数据
		$config = new Config("site_config");

		$dirHandle = opendir($zipDir);
		while($fileName = readdir($dirHandle))
		{
			if(strpos($fileName,'.csv') !== false)
			{
				//创建解析对象
				switch($csvType)
				{
					case "taobao":
					{
						include_once($pluginDir.'taoBaoPacketHelper.php');
						$helperInstance = new taoBaoPacketHelper($zipDir.'/'.$fileName,$imageDir);
						$titleToCols    = taoBaoTitleToColsMapping::$mapping;
					}
					break;

					default:
					{
						$message = "请选择csv数据包的格式";
						die($message);
					}
				}
				//从csv中解析数据
				$collectData = $helperInstance->collect();

				//插入商品表
				foreach($collectData as $key => $val)
				{
					$collectImage = isset($val[$titleToCols['img']]) ? $val[$titleToCols['img']] : '';

					//有图片处理
					if($collectImage)
					{
						//图片拷贝
						$_FILES = array();
						foreach($collectImage as $image)
						{
							foreach($image as $from => $to)
							{
								if(!is_file($from))
								{
									continue;
								}

								IFile::xcopy($from,$to);

								//构造$_FILES全局数组
								$_FILES[] = array(
									'size'     => 100,
									'tmp_name' => $to,
									'name'     => basename($to),
									'error'    => 0
								);
							}
						}
						//调用文件上传类
						$photoObj = new PhotoUpload();
						$uploadImg = $photoObj->run(true);
						$showImg   = current($uploadImg);
					}

					//处理商品详情图片
					$toDir = IUrl::creatUrl().dirname($to);
					$goodsContent = preg_replace("|src=\".*?(?=/contentPic/)|","src=\"$toDir",trim($val[$titleToCols['content']],"'\""));

					$insertData = array(
						'name'         => IFilter::act(trim($val[$titleToCols['name']],'"\'')),
						'goods_no'     => goods_class::createGoodsNo(),
						'sell_price'   => IFilter::act($val[$titleToCols['sell_price']],'float'),
						'market_price' => IFilter::act($val[$titleToCols['sell_price']],'float'),
						'up_time'      => ITime::getDateTime(),
						'create_time'  => ITime::getDateTime(),
						'store_nums'   => IFilter::act($val[$titleToCols['store_nums']],'int'),
						'content'      => IFilter::addSlash($goodsContent),
						'img'          => isset($showImg['img']) ? $showImg['img'] : '',
						'seller_id'    => self::$seller_id,
					);

					$goodsObject->setData($insertData);
					$goods_id = $goodsObject->add();

					//处理商品分类
					if($category)
					{
						foreach($category as $catId)
						{
							$cateExtendDB->setData(array('goods_id' => $goods_id,'category_id' => $catId));
							$cateExtendDB->add();
						}
					}

					//处理商品图片
					if($uploadImg)
					{
						$imgArray = array();
						foreach($uploadImg as $temp)
						{
							if(isset($temp['img']) && $temp['img'])
							{
								$imgArray[] = $temp['img'];
							}
						}

						if($imgArray)
						{
							$photoData = $photoDB->query('img in ("'.join('","',$imgArray).'")','id');
							if($photoData)
							{
								foreach($photoData as $item)
								{
									$photoRelationDB->setData(array('goods_id' => $goods_id,'photo_id' => $item['id']));
									$photoRelationDB->add();
								}
							}
						}
					}
				}
			}
		}
		//清理csv文件数据
		IFile::rmdir($uploadCsvDir,true);
		die('<script type="text/javascript">parent.artDialogCallback();</script>');
	}
}

/**
 * @brief data packet help abstract
 * @date 2013/8/31 23:15:30
 * @author nswe
 */
abstract class packetHelper
{
	//csv source image path
	protected $sourceImagePath;

	//csv target image path
	protected $targetImagePath;

	//csv file convert array data
	protected $dataLine;

	//csv separator
	protected $separator = ",";

	/**
	 * constructor,open the csv packet date file
	 * @param string $csvFile csv file name
	 * @param string $targetImagePath create csv image path
	 */
	public function __construct($csvFile,$targetImagePath)
	{
		if(!preg_match('|^[\w\-]+$|',basename($csvFile,'.csv')))
		{
			throw new Exception('the csv file name must use english');
		}

		if(!file_exists($csvFile))
		{
			throw new Exception('the csv file is not exists!');
		}

		if(!is_dir($targetImagePath))
		{
			throw new Exception('the save csv image dir is not exists!');
		}

		if(IString::isUTF8(file_get_contents($csvFile)) == false)
		{
			die("zip包里面的CSV文件编码格式错误，必须修改为UTF-8格式");
		}

		//read csv file into dataLine array
		setlocale(LC_ALL, 'en_US.UTF-8');
		$fileHandle = fopen($csvFile,'r');
		while($tempRow = fgetcsv($fileHandle,0,$this->separator))
		{
			$this->dataLine[] = $tempRow;
		}

		$this->sourceImagePath = dirname($csvFile).'/'.basename($csvFile,'.csv');
		$this->targetImagePath = $targetImagePath;

		if(!$this->dataLine)
		{
			throw new Exception('the csv file is empty!');
		}
	}
	/**
	 * delete useless line until csv title position
	 * @param array $dataLine csv line array
	 * @param array $title csv title
	 * @return array
	 */
	protected function seekStartLine(&$dataLine,$title)
	{
		foreach($dataLine as $lineNum => $lineContent)
		{
			unset($dataLine[$lineNum]);
			if(in_array(current($title),$lineContent))
			{
				break;
			}
		}
		return $dataLine;
	}
	/**
	 * the mapping with column's num
	 * @param array $dataLine csv line array
	 * @param array $titleArray csv title
	 * @return array key and cols mapping
	 */
	protected function getColumnNum(&$dataLine,$titleArray)
	{
		$titleMapping  = array();
		foreach($dataLine as $key => $colsArray)
		{
			//find the csv title line
			if(in_array(current($titleArray),$colsArray))
			{
				foreach($titleArray as $name)
				{
					$findKey = array_search($name,$colsArray);
					if($findKey !== false)
					{
						$titleMapping[$findKey] = $name;
					}
				}
				break;
			}
		}
		if(!$titleMapping)
		{
			throw new Exception('can not find the mapping colum');
		}
		return $titleMapping;
	}
	/**
	 * get data from csv file
	 * @return array
	 */
	public function collect()
	{
		$mapping  = $this->getColumnNum($this->dataLine,$this->getDataTitle());
		$dataLine = $this->seekStartLine($this->dataLine,$this->getDataTitle());

		$result    = array();
		$temp      = array();

		foreach($dataLine as $lineNum => $lineContent)
		{
			foreach($mapping as $key => $title)
			{
				$temp[$title] = $this->runCallback($lineContent[$key],$title);
			}
			$result[] = $temp;
		}
		return $result;
	}
	/**
	 * run title callback function
	 * @return mix
	 */
	public function runCallback($content,$title)
	{
		$configCallback = $this->getTitleCallback();
		if(isset($configCallback[$title]))
		{
			return call_user_func(array($this,$configCallback[$title]),$content);
		}
		return $content;
	}
	/**
	 * get data image path
	 * @return string
	 */
	public function getImagePath()
	{
		return $this->imagePath;
	}

	/**
	 * get useful column in csv file
	 * @return array
	 */
	abstract public function getDataTitle();
	/**
	 * get function config from title callback
	 * @return array
	 */
	abstract public function getTitleCallback();
}