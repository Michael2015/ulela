<?php

/**
 * @brief 活动api
 * author dyg_jzw
 * create at 2016-12-21 09:48:00
 */
class Api extends IController
{

    const HOST = 'http://119.23.239.216';

    private $result = ['code' => 200, 'msg' => 'success', 'data' => []];

    //banner - 获取banner
    public function get_banner()
    {
        $data = array_merge(IWeb::$app->config, $this->_siteConfig->getInfo(), array("form_index" => IFilter::act(IReq::get('form_index'))));
        $index_slide = unserialize($data['index_slide']);
        array_walk($index_slide, function (&$v) {
            $v['img'] = self::HOST . '/' . $v['img'];

        });
        $this->result['data'] = $index_slide;
        echo json_encode($this->result);
    }

    //获取分类
    public function get_category()
    {
        $catObj = new IModel('category');
        $catRow = $catObj->query('parent_id = 0', 'id,name,sort');
        $this->result['data'] = $catRow;
        echo json_encode($this->result);
    }

    //根据分类id 获取对应的商品
    public function get_category_goods()
    {
        $catId = IFilter::act(IReq::get('cat_id'), 'int');//分类id

        $goodsObj = search_goods::find(array('category_extend' => $catId), 20);
        $resultData = $goodsObj->find();

        if ($resultData) {
            array_walk($resultData, function (&$v) {
                $v['img'] = self::HOST . '/' . $v['img'];

            });
        }
        $this->result['data'] = $resultData;
        echo json_encode($this->result);
    }


}