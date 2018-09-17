<?php
/**
 * @copyright (c) 2017 dyg.cn
 * @file seller_router.php
 * @brief 商户自动跳转处理
 * @author jzw
 * @date 2017/3/16 9:15:21
 * @version 1.0
 */
class seller_router extends pluginBase
{
    private $flag_seller = 'buy_seller'; //标记推荐商户id
    private $flag_sales = 'buy_sales'; //标记推荐业务员id
    private $flag_save_type = 'session'; //标记储存类型
    //插件注册
    public function reg()
    {
        define('PI',3.1415926535898);
        define('EARTH_RADIUS',6371);
        //后台管理IP路由
        plugin::reg("onSystemMenuCreate",function(){
            $link = "/plugins/ip_router_list";
            $link = "javascript:art.dialog.open('".IUrl::creatUrl($link)."',{title:'".$this->name()."',width:'100%',height:'100%',id:'seller_router'});";
            Menu::$menu["插件"]["插件管理"][$link] = $this->name();
        });

        plugin::reg("onBeforeCreateAction@plugins@ip_router_list",function(){
            self::controller()->ip_router_list = function(){$this->ip_router_list();};
        });

        plugin::reg("onBeforeCreateAction@plugins@ip_router_edit",function(){
            self::controller()->ip_router_edit = function(){$this->ip_router_edit();};
        });

        plugin::reg("onBeforeCreateAction@plugins@ip_router_update",function(){
            self::controller()->ip_router_update = function(){$this->ip_router_update();};
        });

        plugin::reg("onBeforeCreateAction@plugins@ip_router_del",function(){
            self::controller()->ip_router_del = function(){$this->ip_router_del();};
        });

        //获取推荐参数或根据ip分配
        plugin::reg("onCreateController",$this,"setSeller");

        //商品自动跳转钩子
        plugin::reg('onBeforeCreateAction@site@products',$this,"goodsRedirect");

        //获取当前商户ID和业务员ID
        plugin::reg("getSellerId",$this,"getSellerId");
        plugin::reg("getSalesId",$this,"getSalesId");
    }
    public function setSeller()
    {
        //先查询是否有商户
        $seller_id = $this->getSellerId();
        $sales_id = $this->getSalesId();

        //获取推荐参数
        $tag_id = IFilter::act(IReq::get('t'),'int'); //商户标签推荐
        $_s_id = IFilter::act(IReq::get('s'),'int'); //业务员推荐

        //获取tag对应的seller
        if ($tag_id) //优先判断商户的推荐
        {
            $tag_info = Api::run('getSellTag', $tag_id);

            //没有商户；或已存在推荐商户，但是和本次进入不一样。则替换为本次推广的商户
            if (empty($seller_id) || ($seller_id && $tag_info && $tag_info['seller_id'] != $seller_id))
            {
                $seller_id = $tag_info['seller_id']; //标记商户推荐
                $sales_id = 0;
            }
        }
        elseif($_s_id) //判断业务员的推荐
        {
            //没有业务员；或已存在推荐业务员，但是和本次进入不一样。则替换为本次推广的业务员
            if (empty($sales_id) || ($sales_id && $_s_id != $sales_id))
            {
                $memberDB = new IModel("member");
                $memberRow = $memberDB->getObj("user_id = ".$_s_id." AND is_sales = 1");
                if ($memberRow)
                {
                    $seller_id = $memberRow['seller_id']; //业务员所属的商户ID
                    $sales_id = $_s_id;
                }
            }
        }
        if (empty($seller_id))
        {
            //根据ip地址获取商户
            $seller_id = $this->getSellerIdByIp('112.93.131.2');
            $sales_id = 0;
        }
        $area_id = IFilter::act(IReq::get('area_id'),'int'); //区号
        if ($area_id)
        {
            //根据area_id获取商户
            $seller_id = $this->getSellerIdByAreaId($area_id);
        }
        //切换商户 保存到session
        ISafe::set($this->flag_sales, $sales_id, $this->flag_save_type);
        ISafe::set($this->flag_seller, $seller_id, $this->flag_save_type);
    }

    //获取当前商户id
    public function getSellerId()
    {
        return intval(ISafe::get($this->flag_seller, $this->flag_save_type));
    }

    //获取当前业务员id
    public function getSalesId()
    {
        return intval(ISafe::get($this->flag_sales, $this->flag_save_type));
    }

    /**
     * 输出当前的地区信息
     * @return string json格式的地区信息
     */
    public function printAreaInfo()
    {
    }

    /**
     * 前台提交的地区id, 后台判断后保存商户和地址信息
     * @param  int $area_id 区县一级的地区id
     * @return [type]          [description]
     */
    public function getSellerIdByAreaId($area_id)
    {
        //根据地区id 获取三级中文名称
        $areaDB = new IModel('areas_new');
        $county_arr = $areaDB->query("area_id = ".$area_id,'parent_id,area_name','',1);
        if($county_arr)
        {
            $county_name = $county_arr[0]['area_name'];
            $city_id = $county_arr[0]['parent_id'];
            $city_arr = $areaDB->query("area_id = ".$city_id,'parent_id,area_name','',1);
            $city_name = $city_arr[0]['area_name'];
            $province_id = $city_arr[0]['parent_id'];
            $province_arr = $areaDB->query("area_id = ".$province_id,'area_name','',1);
            $province_name = $province_arr[0]['area_name'];
        }
        $area_name = $county_name.$city_name.$province_name;
        //保存城市
        /*$area_json['county_name'] = $county_name;
        $area_json['area_id'] = $area_id;*/
        //城市信息拼凑json
        ISafe::set('area_info',$county_name, $this->flag_save_type);
        //调用百度api获取该地区的坐标
        $baidu_url = "http://api.map.baidu.com/geocoder/v2/?output=json&ak=SZnjqkp0szEZaxLjDTCODkvo&address=".urlencode($area_name);
        $location_info = file_get_contents($baidu_url);
        $seller_id = 0;
        if($location_info)
        {
            $location_object = json_decode($location_info);//获取返回json信息
            if($location_object->status == 0)
            {
                $lng_x =  $location_object->result->location->lng;//获取经度
                $lat_y =  $location_object->result->location->lat;//获取纬度
                $seller_id =  $this->allRouter($lng_x,$lat_y);//根据经纬度找出最近的seller
            }
        }
        return $seller_id;
        //根据坐标来获取商户
    }
    //根据ip地址分配商户
    private function getSellerIdByIp($ip)
    {
        $loc_arr = file_get_contents("http://api.map.baidu.com/location/ip?ak=SZnjqkp0szEZaxLjDTCODkvo&coor=bd09ll&ip=".$ip);
        $loc_arr = json_decode($loc_arr, true);
        $client_x= $loc_arr['content']['point']['x'];
        $client_y= $loc_arr['content']['point']['y'];
        return $this->allRouter($client_x,$client_y);
    }
    //根据IP或者area_id获取最近的sellerID
    private function allRouter($client_x,$client_y)
    {
        //根据ip获取坐标地址
        $seller_id = 0;
        $ipRouterDB = new IModel('ip_router');
        //查询所有门店
        $sellerList = $ipRouterDB->query(' 1 ',' id,loc_x,loc_y,seller_id,weight');//查询所有商户的坐标信息
        //计算所有门店距离单位（km）
        if($sellerList && is_array($sellerList))
        {
            $distance_arr = array();
            foreach ($sellerList as $key => $value)
            {
                $new_array[$value['id']] = $value['seller_id'];
                $new_temp = $this->getDistance($client_x,$client_y,$value['loc_x'],$value['loc_y']);
                if($new_temp - $value['weight'] <= 0)//判断是够在商家服务范围之内 weight 单位是KM
                {
                    $distance_arr[$value['id']] = $new_temp;
                }
            }
            if($distance_arr)
            {
                $min_distance_id = array_search(min($distance_arr),$distance_arr);//找出距离最短的且服务范围在里面
                $seller_id = $new_array[$min_distance_id];
            }
        }
        return $seller_id;
    }

    //返回两点坐标距离的公里数
    private function getDistance($lng1, $lat1, $lng2, $lat2)
    {
        $radLat1 = $lat1 * (PI / 180);
        $radLat2 = $lat2 * (PI / 180);
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * (PI / 180)) - ($lng2 * (PI / 180));
        $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)));
        $s = $s * EARTH_RADIUS;
        $s = round($s * 10000) / 10000;
        return $s;
    }

    //编辑IP路由页面
    public function ip_router_edit()
    {
        $id     = IFilter::act(IReq::get('id'),'int');
        $ipRouterRow = array();
        if($id)
        {
            $ipRouterDB = new IModel('ip_router');
            $ipRouterRow= $ipRouterDB->getObj('id = '.$id);
        }
        $this->view('ip_router_edit',array('ipRouterData' => $ipRouterRow));
    }

    //更新IP路由信息
    public function ip_router_update()
    {
        $id          = IFilter::act(IReq::get('id'),'int');
        $loc_x        = IFilter::act(IReq::get('loc_x'));
        $loc_y    = IFilter::act(IReq::get('loc_y'));
        $seller_id       = IFilter::act(IReq::get('seller_id'), 'int');
        $weight    = IFilter::act(IReq::get('weight'));

        $updateData  = array(
            'loc_x'        => $loc_x,
            'loc_y'    => $loc_y,
            'seller_id'       => $seller_id,
            'weight'    => $weight,
        );

        $ipRouterDB = new IModel('ip_router');
        $ipRouterDB->setData($updateData);
        if($id)
        {
            $ipRouterDB->update('id = '.$id);
        }
        else
        {
            $ipRouterDB->add();
        }

        $this->view('ip_router_list');
    }

    //删除IP路由信息
    public function ip_router_del()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        if($id)
        {
            $ipRouterDB = new IModel('ip_router');
            $ipRouterDB->del('id = '.$id);
        }

        $this->view('ip_router_list');
    }

    //ip地区分配列表信息
    public function ip_router_list()
    {
        $this->view('ip_router_list');
    }


    //商品根据关联表自动跳转
    public function goodsRedirect()
    {
        $goods_id = IFilter::act(IReq::get('id'),'int');

        //查询是否有商户
        $seller_id = ISafe::get($this->flag_seller, $this->flag_save_type);

        if ($seller_id)
        {
            //查询商品是否有关联此商户
            $sellerGoodsRelationDB = new IModel('seller_goods_relation');
            $_relation = $sellerGoodsRelationDB->getObj("goods_id = " . $goods_id . " AND seller_id = " . $seller_id);

            if ($_relation)
            {
                header("Location: " . IUrl::creatUrl("/site/products/id/".$_relation['seller_goods_id']));
            }
        }
    }

    public static function name()
    {
        return "商户自动跳转处理插件";
    }

    public static function description()
    {
        return "根据推荐参数、IP地址等对访问客户跳转商户以及对应商品";
    }

    public static function install()
    {
        $ipRouterDB = new IModel('ip_router');
        if($ipRouterDB->exists())
        {
            return true;
        }
        $data = array(
            "comment" => self::name(),
            "column"  => array(
                "id"         	=> array("type" => "int(11) unsigned",'auto_increment' => 1),
                "loc_x"       => array("type" => "decimal(17,14)","comment" => "百度坐标X"),
                "loc_y"   		=> array("type" => "decimal(17,14)","comment" => "百度坐标Y"),
                "seller_id"     => array("type" => "int(11)","comment" => "商户id"),
                "weight"   		=> array("type" => "decimal(10,2)", "default" => 0 , "comment" => "覆盖半径公里数,支持2位小数"),
            ),
            "index" => array("primary" => "id"),
        );
        $ipRouterDB->setData($data);
        $ipRouterDB->createTable();

        return true;
    }

    public static function uninstall()
    {
        $ipRouterDB = new IModel('ip_router');
        return $ipRouterDB->dropTable();
    }

    public static function configName()
    {
        return array();
    }
}