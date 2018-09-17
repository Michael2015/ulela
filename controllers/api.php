<?php
/**
 * @brief 活动api
 * author dyg_jzw
 * create at 2016-12-21 09:48:00
 */
class Api extends IController
{

    const HOST = 'http://119.23.239.216';

    private $result = ['code'=>200,'msg'=>'success','data'=>[]];
	//擂台游戏 - 获取擂主
	public function get_banner()
	{
        $data = array_merge(IWeb::$app->config,$this->_siteConfig->getInfo(),array("form_index" => IFilter::act(IReq::get('form_index'))));
        $index_slide = unserialize($data['index_slide']);
        array_walk($index_slide,function(&$v)
        {
            $v['img'] = self::HOST.'/'.$v['img'];

        });
        $this->result['data']=$index_slide;
        echo json_encode($this->result);
	}
}