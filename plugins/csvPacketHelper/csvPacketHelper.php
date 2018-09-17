<?php
/**
 * @brief CSV商品导入插件
 * @author nswe
 * @date 2016/3/9 0:42:24
 */
class csvPacketHelper extends pluginBase
{
	//注册事件
	public function reg()
	{
		//后台管理
		plugin::reg("onSystemMenuCreate",function(){
			$link = "/plugins/systemCSVImport";
			Menu::$menu["插件"]["插件管理"][$link] = $this->name();
		});

		plugin::reg("onBeforeCreateAction@plugins@systemCSVImport",function(){
			self::controller()->systemCSVImport = function(){$this->redirect("systemCSVImport");};
		});
		plugin::reg("onBeforeCreateAction@plugins@csvImport",function(){
			self::controller()->csvImport = function(){$this->csvImport("systemCSVImport");};
		});

		//商家管理开启
		plugin::reg("onSellerMenuCreate",function(){
			$link = "/seller/sellerCSVImport";
			menuSeller::$menu["商品模块"][$link] = $this->name();
		});

		plugin::reg("onBeforeCreateAction@seller@sellerCSVImport",function(){
			self::controller()->sellerCSVImport = function(){$this->redirect("sellerCSVImport");};
		});

		plugin::reg("onBeforeCreateAction@seller@csvImport",function(){
			self::controller()->csvImport = function(){$this->csvImport("sellerCSVImport");};
		});
	}

	/**
	 * @brief 开始运行
	 */
	public function csvImport($returnUrl)
	{
		set_time_limit(0);
		ini_set("max_execution_time",0);
		$seller_id = self::controller()->seller ? self::controller()->seller['seller_id'] : 0;

		$csvType  = IReq::get('csvType');
		$category = IFilter::act(IReq::get('category'),'int');
		$pluginDir= $this->path();

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

		$dirHandle = opendir($zipDir);
		while(false !== ($fileName = readdir($dirHandle)))
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
						foreach($collectImage as $from => $to)
						{
							$from = str_replace("\\","/",$from);
							$to   = str_replace("\\","/",$to);
							if(!is_file($from))
							{
								continue;
							}
							IFile::xcopy($from,$to);
						}
					}

					//处理商品详情图片
					$toDir = IUrl::creatUrl().dirname($to);
					$goodsContent = preg_replace("|src=\".*?(?=/contentPic/)|","src=\"$toDir",trim($val[$titleToCols['content']],"'\""));

					//插入GOODS表数据
					$insertData = array(
						'name'         => IFilter::act(trim($val[$titleToCols['name']],'"\'')),
						'goods_no'     => goods_class::createGoodsNo(),
						'sell_price'   => IFilter::act($val[$titleToCols['sell_price']],'float'),
						'market_price' => IFilter::act($val[$titleToCols['sell_price']],'float'),
						'up_time'      => ITime::getDateTime(),
						'create_time'  => ITime::getDateTime(),
						'store_nums'   => IFilter::act($val[$titleToCols['store_nums']],'int'),
						'content'      => IFilter::addSlash($goodsContent),
						'img'          => isset($val['mainPic']) ? current($val['mainPic']) : '',
						'seller_id'    => $seller_id,
						'weight'       => $val[$titleToCols['weight']],
					);
					$goodsObject->setData($insertData);
					$goods_id = $goodsObject->add();

					//货品处理
					if(isset($val['products']) && $val['products'])
					{
						$goodsSpec = array();
						foreach($val['products'] as $k => $pVal)
						{
							//整理goods表spec_array数据
							foreach($pVal['spec_array'] as $specVal)
							{
								if(!isset( $goodsSpec[$specVal['id']] ))
								{
									$goodsSpec[$specVal['id']] = array(
										'id'    => $specVal['id'],
										'name'  => $specVal['name'],
										'type'  => $specVal['type'],
										'value' => array(),
									);
								}

								if(!in_array(array($specVal['tip'] => $specVal['value']),$goodsSpec[$specVal['id']]['value']))
								{
									$goodsSpec[$specVal['id']]['value'][] = array($specVal['tip'] => $specVal['value']);
								}
							}

							//更新插入products表
							$products_no = $insertData['goods_no'].'-'.($k+1);
							$weight      = $insertData['weight'];
							$productsDB  = new IModel('products');
							$productsDB->setData(array(
								'goods_id'    => $goods_id,
								'products_no' => $products_no,
								'spec_array'  => IFilter::act(JSON::encode($pVal['spec_array'])),
								'store_nums'  => $pVal['store_nums'],
								'market_price'=> $pVal['sell_price'],
								'sell_price'  => $pVal['sell_price'],
								'cost_price'  => $pVal['sell_price'],
								'weight'      => $weight,
							));
							$productsDB->add();
						}

						//更新商品表
						$goodsObject->setData(array( 'spec_array' => IFilter::act(JSON::encode($goodsSpec)) ));
						$goodsObject->update("id = ".$goods_id);
					}

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
					if(isset($val['mainPic']) && $val['mainPic'])
					{
						foreach($val['mainPic'] as $photoFile)
						{
							if(!is_file($photoFile))
							{
								continue;
							}
							$md5Code = md5_file($photoFile);
							$photoRow= $photoDB->getObj('id = "'.$md5Code.'"');
							if(!$photoRow || !is_file($photoRow['img']))
							{
								$photoDB->del('id = "'.$md5Code.'"');

								$photoDB->setData(array("id" => $md5Code,"img" => $photoFile));
								$photoDB->add();
							}
							$photoRelationDB->setData(array('goods_id' => $goods_id,'photo_id' => $md5Code));
							$photoRelationDB->add();
						}
					}
				}
			}
		}
		//清理csv文件数据
		IFile::rmdir($uploadCsvDir,true);
		$this->redirect($returnUrl,array('result' => 'success'));
	}

	/**
	 * @brief 插件名字
	 * @return string
	 */
	public static function name()
	{
		return "商品CSV数据导入";
	}

	/**
	 * @brief 插件描述
	 * @return string
	 */
	public static function description()
	{
		return "由淘宝助手或者甩手工具箱产生的4.6版本CSV数据包直接导入到iWebShop系统中，管理后台和商家后台都可以用";
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
		$this->dataLine[0][0] = IString::clearBom($this->dataLine[0][0]);
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
	 * @return array(taoBaoTitleToColsMapping,mainPic(商品主图),products(货品数据))
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