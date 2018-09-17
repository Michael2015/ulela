<?php
/**
 * @copyright (c)  dyg.cn
 * @file area.php
 * @brief 地区PI
 * @author yangmz
 * @date 2017.03.21 10:18
 * @version 1.0
 */
class APIArea
{
    //根据商品模式ID查找不同字母的地区
    public function getCityByChar($model,$character)
    {
        $result = array();
        $areaDB = new IModel('areas');
        $cache = new ICache('memcache');
        //如果 $model ='normal' 就是根据首字母来查询的
        //如果 $model ='hot'   就是查询热门城市
        if($model == 'normal')
        {
            $charStr = "";
            if(strstr($character,','))
            {
                $charStr = str_replace(",",'',$character);
                $char_quotes = preg_replace("#([a-zA-Z])#","'$1'",$character);
            }
            else
            {
                $charStr = $character;
            }
            if(!$cache->get("getAreaById_".$charStr))
            {
                $queryData = $areaDB->query(" `parent_id`<> 0 AND `parent_id` % 10000 = 0 AND  `first_char` in (".$char_quotes.") GROUP BY `first_char`",' GROUP_CONCAT(`area_id`) AS area_id,GROUP_CONCAT(`first_char`) AS first_char,GROUP_CONCAT(`area_name`) AS area_name');
                $cache->set("getAreaById_".$charStr,$queryData,3600*24*30);
            }else{
                $queryData = $cache->get("getAreaById_".$charStr);
            }
            //处理分组出来的数据 area_id 和 area_name
            foreach ($queryData as $key=>$value)
            {
                $char = strtoupper($value['first_char'][0]);
                $result[$char] = array();
                if($value['area_id'])
                {
                    $id_arr = explode(',',$value['area_id']);
                    $name_arr = explode(',',$value['area_name']);
                    foreach ($id_arr as $k=>$v)
                    {
                        $result[$char][$v] = $name_arr[$k];
                    }
                }
            }
        }
        //根据热门城市名称搜索
        elseif ($model == 'hot')
        {
            $city_arr = array();
            if(strstr($character,','))
                $city_arr = explode(',',$character);
            else
                $city_arr = $character;
            if($cache->get("getAreaByName_".MD5($character)))
            {
                if($city_arr && is_array($city_arr))
                {
                    foreach ($city_arr as  $k=>$v)
                    {
                        $city_arr = $areaDB->query("`parent_id` % 10000 = 0 AND  area_name like '%{$v}%' ",'area_id,area_name','',1)[0];//查找所有匹配的热门城市
                        if($city_arr)
                        {
                            $province_area_id = $city_arr['area_id'];//省级
                            $result[$k] = $city_arr;
                            $city_arr = $areaDB->query("`parent_id` = {$province_area_id} ",'area_id,area_name','',100);//市级
                            $district_arr = array();
                            foreach ($city_arr as $kk=>$district)
                            {
                                $district_id = $district['area_id'];//区或县级
                                $district_arr[$district['are']] = $areaDB->query("`parent_id` = {$district_id} ",'area_id,area_name','',100);//市级
                            }
                            $result[$k]['child'] = $district_arr;
                        }
                    }
                }
                $cache->set("getAreaByName_".MD5($character),$result,3600*24*30);
            }
            else
            {
                $result = $cache->get("getAreaByName_".MD5($character));
            }

        }
        return $result ;
    }
}