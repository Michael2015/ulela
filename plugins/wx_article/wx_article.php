<?php
/**
 * @copyright (c) 2017 dyg.cn by yangmz
 * @file auto_ticket
 * @brief  商城优惠券自动派发
 * @author yangmz
 * @date 2017/6/27 15:20:56
 * @version 1.0
 */
class wx_article extends pluginBase
{
    private $sougou_home_url =  'http://weixin.sogou.com/weixin?type=1&query=#mp_id#&ie=utf8&_sug_=n&_sug_type_=';


    //插件注册
    public function reg()
    {
        //微信测试入口
        plugin::reg("onBeforeCreateAction@block@wx_spider",function()
        {
            self::controller()->wx_spider = function(){$this->wx_spider();};
        });
    }
    public function wx_spider()
    {
        set_time_limit(0);
        $artcileDB = new IModel('article');
        $wx_mps = $this->config()['wxid'];
        if($wx_mps)
        {
            $wx_mp_ids = explode(',',$wx_mps);
            foreach ($wx_mp_ids as $mid)
            {
                $sougou_url = str_replace('#mp_id#',$mid,$this->sougou_home_url);
                //爬取首页
                $home_page = $this->do_curl($sougou_url);
                if(preg_match('#<a[^>]*uigs="account_name_0"[^>]*href="(.*?)"[^>]*>.*?<\/a>#',$home_page,$match))
                {
                    $sougou_list_url = htmlspecialchars_decode($match[1]);
                    //爬取列表页
                    if($sougou_list_url)
                    {
                        $sougou_list_page = $this->do_curl($sougou_list_url);
                        if(preg_match('#{"list":(.*)}#',$sougou_list_page,$match))
                        {
                            $list_arr = json_decode($match[1],true);
                            foreach($list_arr as $k=>$v)
                            {
                                //如果抓取过的文章直接跳过
                                if($artcileDB->getObj(" title = '{$v['app_msg_ext_info']['title']}'",'id'))
                                {
                                    continue;
                                }
                                $data['title'] = $v['app_msg_ext_info']['title'];
                                $data['description'] = $v['app_msg_ext_info']['digest'];
                                $data['create_time'] = ITime::getDateTime();
                                $data['category_id'] = 1;
                                $content_url = htmlspecialchars_decode('https://mp.weixin.qq.com'.$v['app_msg_ext_info']['content_url']);
                                $article_content = $this->do_curl($content_url);
                                if(preg_match('#<div class="rich_media_content " id="js_content">(.*?)<\/div>#s',$article_content,$match))
                                {
                                    $content = trim($match[1]);
                                    if(preg_match_all('#data-src="(.*?)"#', $content, $images))
                                    {
                                        foreach ($images[1] as $img_url)
                                        {
                                            $content = str_replace($img_url, 'http://www.img.my/?url='.$img_url, $content);
                                        }
                                        $content = str_replace('data-src', 'src', $content);
                                    }

                                    $data['content'] = $content;
                                }
                                $artcileDB->setData($data);
                                $artcileDB->add();
                            }
                        }
                    }
                }
                //slepp 10s after every mp
                sleep(10);
            }
        }
    }
    private function do_curl($url)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_HEADER,0);
        $output = curl_exec($ch);
        return $output;
    }

    public static function name()
    {
        return "同步微信搜狗文章";
    }
    public static function description()
    {
        return "爬取微信公众号文章,并同步到商城文章中.";
    }
    public static function install()
    {
        return true;
    }
    public static function uninstall()
    {
        return true;
    }
    public static function configName()
    {
        return array
        (
            "wxid" => array("name" => "微信公众号", "type" => "text"),//微信公众号ID
        );
    }

}