<?php
/**
 * @copyright (c) 2016 dyg.cn
 * @file myapi.php
 * @brief 自定义api方法
 * @author dyg_jzw
 * @date 2016/11/28 10:36
 * @version 1.0
 */
class Myapi
{
    //dyg_jzw 20161010 获取商品是否推荐商品
    public function getCommendIdByGoods($goods_id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCommendIdByGoods_".$goods_id))
        {
            $query = new IQuery('commend_goods as co');
            $query->where = "co.goods_id = ".$goods_id;
            $query->order = 'commend_id ASC';
            $result = $query->find();

            $cache->set("getCommendIdByGoods_".$goods_id, $result, 3600*24);
        }

        return $result;
    }

    //dyg_jzw 20161122 最多访问量的商品
    public function getMostVisit($category_id, $limit = 10)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getMostVisit_".$category_id.'_'.$limit))
        {
            $query = new IQuery('category_extend as c');
            $query->join = "left join goods as go on go.id = c.goods_id";
            $query->where = "go.is_del = 0 and c.category_id = ".$category_id;
            $query->fields = "go.id, go.img, go.sell_price, go.name";
            $query->order = "go.visit DESC";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getMostVisit_".$category_id.'_'.$limit, $result, 3600*24);
        }

        return $result;
    }

    //dyg_jzw 20161128 商品是否可评价
    public function getOrderComment($order_no, $goods_id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getOrderComment_".$order_no.'_'.$goods_id))
        {
            $query = new IQuery('comment as c');
            $query->where = "c.order_no = '{$order_no}' and c.goods_id = '{$goods_id}'";
            $result = $query->find();
            $result = current($result);

            $cache->set("getOrderComment_".$order_no.'_'.$goods_id, $result, 3600*24);
        }

        return $result;
    }

    //dyg_jzw 20161128 获取商品评分情况
    public function getGoodsPointInfo($goods_id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getGoodsPointInfo_".$goods_id))
        {
            $result = Comment_Class::get_comment_info($goods_id);

            $cache->set("getGoodsPointInfo_".$goods_id, $result, 3600*24*7);
        }
        return $result;
    }

    //新品列表
    public function getCommendNew($limit = 10)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCommendNew_".$limit))
        {
            $query = new IQuery('commend_goods as co');
            $query->join = "left join goods as go on co.goods_id = go.id";
            $query->where = "co.commend_id = 1 and go.is_del = 0 AND go.id is not null";
            $query->fields = "go.img,go.sell_price,go.name,go.id,go.market_price,go.keywords";
            $query->order = "sort asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getCommendNew_".$limit, $result, 3600*24);
        }

        return $result;
    }


    //特价商品列表
    public function getCommendPrice($limit = 10)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCommendPrice_".$limit))
        {
            $query = new IQuery('commend_goods as co');
            $query->join = "left join goods as go on co.goods_id = go.id";
            $query->where = "co.commend_id = 2 and go.is_del = 0 AND go.id is not null";
            $query->fields = "go.img,go.sell_price,go.name,go.id,go.market_price,go.keywords";
            $query->order = "sort asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getCommendPrice_".$limit, $result, 3600*24);
        }

        return $result;
    }

    //热卖商品列表
    public function getCommendHot($limit = 10)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCommendHot_".$limit))
        {
            $query = new IQuery('commend_goods as co');
            $query->join = "left join goods as go on co.goods_id = go.id";
            $query->where = "co.commend_id = 3 and go.is_del = 0 AND go.id is not null";
            $query->fields = "go.img,go.sell_price,go.name,go.id,go.market_price,go.keywords";
            $query->order = "sort asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getCommendHot_".$limit, $result, 3600*24);
        }
        return $result;
    }


    //推荐商品列表
    public function getCommendRecom($limit = 100)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCommendRecom_".$limit))
        {
            $query = new IQuery('commend_goods as co');
            $query->join = "left join goods as go on co.goods_id = go.id";
            $query->where = "co.commend_id = 4 and go.is_del = 0 AND go.id is not null";
            $query->fields = "go.img,go.sell_price,go.name,go.id,go.market_price,go.keywords";
            $query->order = "sort asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getCommendRecom_".$limit, $result, 3600*24);
        }

        return $result;
    }

    //根据商品分类取得商品列表
    public function getCategoryExtendList($category_id, $limit = 10)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCategoryExtendList_".$category_id.'_'.$limit))
        {
            $query = new IQuery('category_extend as ca');
            $query->join = "left join goods as go on go.id = ca.goods_id";
            $query->where = "ca.category_id in({$category_id}) and go.is_del = 0";
            $query->fields = "go.id,go.name,go.img,go.sell_price,go.market_price,go.keywords";
            $query->order = "go.sort asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getCategoryExtendList_".$category_id.'_'.$limit, $result, 3600*24);
        }

        return $result;
    }

    //所有一级分类
    public function getCategoryListTop()
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCategoryListTop"))
        {
            $query = new IQuery('category');
            $query->where = "parent_id = 0 and visibility = 1";
            $query->order = "sort asc";
            $result = $query->find();

            $cache->set("getCategoryListTop", $result, 3600*24);
        }

        return $result;
    }

    //根据一级分类输出二级分类列表
    public function getCategoryByParentid($parent_id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getCategoryByParentid_".$parent_id))
        {
            $query = new IQuery('category');
            $query->where = "parent_id = {$parent_id} and visibility = 1";
            $query->order = "sort asc";
            $result = $query->find();

            $cache->set("getCategoryByParentid_".$parent_id, $result, 3600*24);
        }

        return $result;
    }

    //热门关键词列表
    public function getKeywordList($limit=50)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getKeywordList_".$limit))
        {
            $query = new IQuery('keyword');
            $query->where = "hot = 1";
            $query->order = "`order` asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getKeywordList_".$limit, $result, 3600*24);
        }

        return $result;
    }


    //帮助中心底部列表
    public function getHelpCategoryFoot($limit=50)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getHelpCategoryFoot_".$limit))
        {
            $query = new IQuery('help_category');
            $query->where = "position_foot = 1";
            $query->order = "sort ASC";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getHelpCategoryFoot_".$limit, $result, 3600*24);
        }

        return $result;
    }

    //取帮助中心列表
    public function getHelpListByCatidAll($cat_id, $limit = 50)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getHelpListByCatidAll_".$cat_id.'_'.$limit))
        {
            $query = new IQuery('help');
            $query->where = "cat_id = {$cat_id}";
            $query->order = "sort asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getHelpListByCatidAll_".$cat_id.'_'.$limit, $result, 3600*24);
        }

        return $result;
    }

    //查找商品的分类
    public function getGoodsCategoryExtend($word, $limit = 20)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getGoodsCategoryExtend_".$word))
        {
            $query = new IQuery('goods as go');
            $query->join = "left join category_extend as ca on go.id = ca.goods_id left join category as c on c.id = ca.category_id";
            $query->where = "c.id <> 0 and go.is_del = 0 and go.name like '%{$word}%' or FIND_IN_SET('%{$word}%',search_words)"; //dyg_jzw 20161213增加不可搜索隐藏分类
            $query->fields = "c.name,c.id,count(*) as num";
            $query->group = "ca.category_id";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getGoodsCategoryExtend_".$word, $result, 3600*24*3);
        }

        return $result;
    }

    //导航列表
    public function getGuideList($limit = 20)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getGuideList_".$limit))
        {
            $query = new IQuery('guide');
            $query->order = "`order` asc";
            $query->limit = $limit;
            $result = $query->find();

            $cache->set("getGuideList_".$limit, $result, 3600*24*3);
        }

        return $result;
    }

    //dyg_jzw 20161215 获取团购或限时抢购的列表
    public function getRegimentOrTimeBuy($group_id, $goods_id, $seller_id = 0)
    {

        $cache = new ICache('memcache');
        $result = array(
            'timebuy' => array(), //限时抢购活动
            'regiment' => array() //团购活动
        );

        //获取所有限时抢购列表
        if(! $timeBuyList = $cache->get("allTimeBuyList_".$seller_id))
        {
            $query = new IQuery('promotion');
            $query->where = "seller_id = {$seller_id} and is_close = 0";
            $timeBuyList = $query->find();

            $cache->set("allTimeBuyList_".$seller_id, $timeBuyList, 3600*24*30);
        }

        //是否存在限时抢购活动
        if ($timeBuyList)
        {
            foreach ($timeBuyList as $_time_item)
            {
                //限时抢购活动是否为显示抢购 //活动是否开始
                if ($_time_item['award_type'] == 0 && strtotime($_time_item['start_time']) < time() && strtotime($_time_item['end_time']) > time())
                {
                    //活动用户组是否符合
                    if ($_time_item['user_group'] == 'all' || in_array($group_id, explode(",", $_time_item['user_group'])))
                    {
                        //商品是否符合
                        if ($_time_item['condition'] == $goods_id)
                        {
                            $result['timebuy'][] = $_time_item;
                        }
                    }
                }
            }
        }

        //获取所有团购活动
        if(! $regimentList = $cache->get("allRegimentList_".$seller_id))
        {
            $query = new IQuery('regiment');
            $query->where = "seller_id = {$seller_id} and is_close = 0";
            $regimentList = $query->find();

            $cache->set("allRegimentList_".$seller_id, $regimentList, 3600*24*30);
        }

        //是否存在团购活动
        if ($regimentList)
        {
            foreach ($regimentList as $_regiment_item)
            {
                //限时抢购活动是否为显示抢购 //活动是否开始
                if (strtotime($_regiment_item['start_time']) < time() && strtotime($_regiment_item['end_time']) > time())
                {
                    //商品是否符合
                    if ($_regiment_item['goods_id'] == $goods_id)
                    {
                        $result['regiment'][] = $_regiment_item;
                    }
                }
            }
        }

        return $result;
    }

    //dyg_jzw 20161222 获取地区名称
    public function getAreasName($area_ids)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getAreasName_".md5(json_encode($area_ids))))
        {
            $areaDB     = new IModel('areas');
            $areaData   = $areaDB->query("area_id in (".trim(join(',',$area_ids),",").")");

            $result = array();
            foreach($areaData as $value)
            {
                $result[$value['area_id']] = $value['area_name'];
            }

            $cache->set("getAreasName_".md5(json_encode($area_ids)), $result, 3600*24*30);
        }

        return $result;
    }

    //获取代金券详情
    public function getTicketInfo($id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getTicketInfo_".$id))
        {
            $tickObj = new IModel('ticket');
            $result = $tickObj->getObj('id = '.$id);

            $cache->set("getTicketInfo_".$id, $result, 3600*24*30);
        }
        return $result;
    }


    //获取代金券使用情况
    public function checkTicketUseful($tid, $goods_result, $seller_ids, $code = null)
    {
        $propObj   = new IModel('prop');
        $ticketRow = array("can_use" => false);

        if ($tid)
        {
            $ticketRow = $propObj->getObj('id = '.$tid.' and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1');
        }
        elseif ($code)
        {
            $ticketRow = $propObj->getObj("card_name = '".$code."' and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1 ");
        }
        $_can_use_sum = 0; //符合商品的金额汇总

        if (! is_array($seller_ids))
        {
            $seller_ids = array($seller_ids);
        }
        if($ticketRow)
        {
            if($ticketRow['seller_id'] == 0 || in_array($ticketRow['seller_id'], $seller_ids))
            {
                /*
                 * dyg_jzw 20161226 代金券使用限制功能
                 *
                */

                //获取代金券详情
                $tickInfo =  $this->getTicketInfo($ticketRow['condition']);

                $ticketRow['name'] = $tickInfo['name'];

                if ($tickInfo['goods_ids']) //存在使用限制的商品
                {
                    $tick_goods_arr = explode(",", $tickInfo['goods_ids']);

                    //查找是否有符合的商品
                    foreach($goods_result['goodsList'] as $_item)
                    {
                        if (in_array($_item['goods_id'].'_'.$_item['product_id'], $tick_goods_arr))
                        {
                            $_can_use_sum += $_item['sum'];
                        }
                    }
                }
                else //无商品要求
                {
                    //无商品要求, 则整单金额都可以使用
                    $_can_use_sum = $goods_result['final_sum'];
                }

                //代金券是否有金额要求
                if ( $tickInfo['at_least_money'] == 0 || ($tickInfo['at_least_money'] > 0 && $_can_use_sum >= $tickInfo['at_least_money'])) //有满额要求, 且符合商品金额大于满额要求 //无满额要求, 则直接抵扣
                {
                    $ticketRow['can_use'] = true;
                }
                else
                {
                    $ticketRow['can_use'] = false;
                }
            }
        }
        return $ticketRow;
    }
    //订单ID是否支持积分支付
    public function checkPointPay($order_id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("checkPointPay_".$order_id))
        {
            $query = new IQuery('order_goods as og');
            $query->join = "left join goods as go on go.id = og.goods_id";
            $query->where = "og.order_id = " . intval($order_id);
            $query->fields = "go.is_point";
            $goodsIsPoint = $query->find();
            $result = true;
            foreach ($goodsIsPoint as $_goods_is_point)
            {
                if (! $_goods_is_point['is_point'])
                {
                    $result = false;
                    break;
                }
            }
            $cache->set("checkPointPay_".$order_id, $result, 60*10);
        }
        return $result;
    }
    //dyg_jzw 20170412 获取商户标签信息
    public function getSellTag($tag_id)
    {
        $cache = new ICache('memcache');
        if(! $result = $cache->get("getSellTag_".$tag_id))
        {
            $sellerTagDB = new IModel('seller_tag');
            $result = $sellerTagDB->getObj("id = ".$tag_id);
            if ($result)
            {
                $cache->set("getSellTag_".$tag_id, $result, 3600*24*7);
            }
        }
        return $result;
    }


    //根据商品模式ID查找不同字母的地区

    /**
     * 根据城市首字母获取城市信息
     * @param  string $type hot:热门城市 normal根据char查询
     * @param  array/string $char 首字母数组/字母(小写)
     * @return array 城市信息
     */
    public function getCityByChar($type, $char = null)
    {
        //热门城市的id
        $hot_array = array(
            array('area_id' => '110100', 'area_name' => '北京市'),
            array('area_id' => '310100', 'area_name' => '上海市'),
            array('area_id' => '330100', 'area_name' => '杭州市'),
            array('area_id' => '440100', 'area_name' => '广州市'),
            array('area_id' => '120100', 'area_name' => '天津市'),
            array('area_id' => '320500', 'area_name' => '苏州市'),
            array('area_id' => '510100', 'area_name' => '成都市'),
            array('area_id' => '440300', 'area_name' => '深圳市'),
            array('area_id' => '320100', 'area_name' => '南京市'),
            array('area_id' => '500100', 'area_name' => '重庆市'),
            array('area_id' => '360100', 'area_name' => '南昌市'),
            array('area_id' => '420100', 'area_name' => '武汉市'),
        );
        if($type == 'normal')
        {
            $cache = new ICache('memcache');
            //加入单引号用于数据库查询
            $char = str_split($char);
            foreach ($char as $_key => $_val)
            {
                $char[$_key] = "'" . $_val . "'";
            }
            //查询缓存
            if (!$result = $cache->get("getCityByChar_".md5(json_encode($char))))
            {
                //查询数据库 非省非区
                $areaDB = new IModel('areas');
                $result = $areaDB->query("parent_id <> 0 AND parent_id % 10000 = 0 AND first_char in (". implode(",", $char) .")",'area_id,area_name,first_char', 'first_char ASC, sort ASC');
                $cache->set("getCityByChar_".md5(json_encode($char)), $result, 3600*24*30);
            }
             return $result;
        }
        elseif ($type == 'hot') //根据热门城市名称搜索
        {
            return $hot_array;
        }
    }
    /**
     * 获取城市的地区信息
     * @param  int $area_id 城市地区id
     * @return array 地区数组
     */
    public function getCityChild($area_id)
    {
        $cache = new ICache('memcache');
        if (!$result = $cache->get("getCityChild_".$area_id))
        {
            $areaDB = new IModel('areas');
            $result = $areaDB->query("parent_id = ".$area_id, 'area_id,area_name', 'sort ASC');
            $cache->set("getCityChild_".$area_id, $result, 3600*24*30);
        }
        return $result;
    }

}