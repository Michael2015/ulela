<?php
/**
 * @copyright Copyright(c) 2016 aircheng.com
 * @file menuSeller.php
 * @brief 商家系统菜单管理
 * @author nswe
 * @date 2016/3/8 9:30:34
 * @version 4.4
 */
class menuSeller
{
    //菜单的配制数据
	public static $menu = array
	(
		"统计结算模块" => array(
			"/seller/index" => "管理首页",
			"/seller/account" => "销售额统计",
			"/seller/order_goods_list" => "货款明细列表",
			"/seller/bill_list" => "货款结算申请",
		),

		"商品模块" => array(
			"/seller/goods_list" => "商品列表",
			"/seller/goods_edit" => "添加商品",
			"/seller/share_list" => "平台共享商品",
			"/seller/refer_list" => "商品咨询",
			"/seller/comment_list" => "商品评价",
			"/seller/refundment_list" => "商品退款",
			"/seller/spec_list" => "规格列表",
		),

		"订单模块" => array(
			"seller/order_list" => "订单列表",
		),

		"营销模块" => array(
			"/seller/regiment_list" => "团购",
			"/seller/pro_rule_list" => "促销活动列表",
		),

		"配置模块" => array(
			"/seller/delivery" => "物流配送",
			"/seller/message_list" => "消息通知",
			"/seller/ship_info_list" => "发货地址",
			"/seller/seller_edit" => "资料修改",
		),
	);

    /**
     * @brief 根据权限初始化菜单
     * @param int $roleId 角色ID
     * @return array 菜单数组
     */
    public static function init($roleId = "")
    {
		//菜单创建事件触发
		plugin::trigger("onSellerMenuCreate");
		return self::$menu;
    }
}