<?php
/**
 * Created by PhpStorm.
 * User: yangmz
 * Date: 2017-5-10
 * Time: 11:46
 * @brief web收银端
 */
class template_manage extends pluginBase
{
    //插件注册
    public function reg()
    {
    }
    public static function install()
    {
        $templateDB = new IModel('template');
        if(!$templateDB->exists())
        {
            $data = array(
                "comment" => '商城详情页模板表',
                "column"  => array(
                    "id"         	  => array("type" => "int(11) unsigned",'auto_increment' => 1),
                    "tpl_name"        => array("type" => "varchar(50)","comment" => "模板名称"),
                    "tpl_type"   	  => array("type" => "tinyint(1) unsigned","comment" => "1:PC详情头部 2:移动详情头部 3:PC详情尾部 4:移动详情尾部"),
                    "tpl_cid"         => array("type" => "varchar(255)","comment" => "商品分类ID"),
                    "tpl_is_display"  => array("type" => "tinyint(1)","comment" => "0:"),
                    "tpl_create_time" => array("type" => "datetime","comment" => "创建时间"),
                    "tpl_edit_time"   => array("type" => "datetime","comment" => "修改时间"),
                    "tpl_content"     => array("type" => "text","comment" => "模板内容"),
                    "tpl_sort"        => array("type" => "tinyint(3)","default"=>0,"comment" => "排序,数值越高越前"),
                ),
                "index" => array("primary" => "id"),
            );
            $templateDB->setData($data);
            $templateDB->createTable();
        }
        return true;
    }
    public static function uninstall()
    {
        $templateDB = new IModel('template');
        if($templateDB->exists())
        {
            $templateDB->dropTable();
        }
        return true;
    }
    /**
     * @brief 插件名字
     * @return string
     */
    public static function name()
    {
        return "商城模板管理";
    }
    /**
     * @brief 插件功能描述
     * @return string
     */
    public static function description()
    {
        return "商城可以根据商品不同的分类进行自定义PC端和移动端详情页头部、尾部模板";
    }
    public static function configName()
    {
        return array();
    }
}
