<?php
/**
 * @brief 动态生成缩略图类
 */
class Thumb
{
	//缩略图路径
	public static $thumbDir = "runtime/_thumb/";

	/**
	 * @brief 获取缩略图物理路径
	 */
	public static function getThumbDir()
	{
		return IWeb::$app->getBasePath().self::$thumbDir;
	}

	/**
	 * @brief 生成缩略图
	 * @param string $imgSrc 图片路径
	 * @param int $width 图片宽度
	 * @param int $height 图片高度
	 * @return string WEB图片路径名称
	 */
    public static function get($imgSrc,$width=100,$height=100)
    {
    	if($imgSrc == '')
    	{
    		return '';
    	}

		//远程图片
		if(strpos($imgSrc,"http") === 0)
		{
			$sourcePath = $imgSrc;
			$urlArray   = parse_url($imgSrc);
			if(!isset($urlArray['path']))
			{
				return '';
			}
			$dirname = dirname($urlArray['path']);
		}
		//本地图片
		else
		{
			$sourcePath = IWeb::$app->getBasePath().$imgSrc;
			if(is_file($sourcePath) == false)
			{
				return '';
			}
			$dirname    = dirname($imgSrc);
		}

		//缩略图文件名
		$preThumb      = "{$width}_{$height}_";
		$thumbFileName = $preThumb.basename($imgSrc);

		//缩略图目录
		$thumbDir    = self::getThumbDir().trim($dirname,"/")."/";
		$webThumbDir = self::$thumbDir.trim($dirname,"/")."/";
		if(is_file($thumbDir.$thumbFileName) == false)
		{
			IImage::thumb($sourcePath,$width,$height,$preThumb,$thumbDir);
		}
		return $webThumbDir.$thumbFileName;
    }
}