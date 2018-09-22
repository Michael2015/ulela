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

    //banner - 获取首页banner
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


    //banner - 获取列表banner
    public function get_list_banner()
    {
        $data = array_merge(IWeb::$app->config, $this->_siteConfig->getInfo(), array("form_index" => IFilter::act(IReq::get('form_index'))));
        $index_slide = unserialize($data['index_slide_mobile']);
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

    //获取商品详情
    public function get_goods_detail()
    {
        $goods_id = IFilter::act(IReq::get('id'),'int');
        if(!$goods_id)
        {
            $this->result['msg'] = 'fail';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }
        //使用商品id获得商品信息
        $tb_goods = new IModel('goods');
        $goods_info = $tb_goods->getObj('id='.$goods_id." AND is_del=0");
        $data = [];
        if($goods_info)
        {
            $data['id'] = $goods_info['id'];
            $data['sell_price'] = $goods_info['sell_price'];
            $data['store_nums'] = $goods_info['store_nums'];
            $data['img'] = $goods_info['img'];
            $data['content'] = $goods_info['content'];
            $data['description'] = $goods_info['description'];
            $data['spec_array'] = $goods_info['spec_array'];
        }
        $this->result['data'] = $data;
        echo json_encode($this->result);
    }

    //根据商品id，获取对应的sku值
    public function get_goods_attr()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'),'int');
        if(!$goods_id)
        {
            $this->result['msg'] = 'fail,缺少goods_id参数';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }

        $attr_ids = IFilter::act(IReq::get('attr_ids'),'string');
        if(!$attr_ids)
        {
            $this->result['msg'] = 'fail,缺少attr_ids参数';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }

        $tb_products = new IModel('products');
        $pro_list = $tb_products->query('goods_id ='.$goods_id,'id,goods_id,sell_price,spec_array');

        $data = [];
        foreach ( $pro_list as  $product)
        {
            $spec_array = json_decode($product['spec_array'],true);
            $attr = '';
            foreach ($spec_array as $spec)
            {
                $attr .= $spec['tip'].'-';
            }
            if($attr_ids === trim($attr,'-'))
            {
                $data = $product;
                break;
            }
        }
        $this->result['data'] = $data;
        echo json_encode($this->result);
    }

    //加入购物车
    public function add_to_cart()
    {
        $goods_id  = IFilter::act(IReq::get('goods_id'),'int');
        if(!$goods_id)
        {
            $this->result['msg'] = 'fail,缺少goods_id参数';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }

        $goods_num = IReq::get('goods_num') === null ? 1 : intval(IReq::get('goods_num'));
        $type = 'products';

        $cartObj   = new Cart();
        $addResult = $cartObj->add($goods_id,$goods_num,$type);
        if($addResult === false)
        {
            $this->result['msg'] = $cartObj->getError();
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }
        $this->result['msg'] = '添加成功!';
        echo json_encode($this->result);exit;
    }

    //获取购物车
    public function  get_cart()
    {
        $cartObj  = new Cart();
        $cartList = $cartObj->getMyCart();
        $data['goods_item'] = $cartList['product']['data'];


        $tb_products = new IModel('products');
        array_walk($data['goods_item'],function(&$v) use ($tb_products){
            $v['spec_array'] = $tb_products->getObj('id ='.$v['id'],'spec_array')['spec_array'];
        });
        $data['count']= $cartList['count'];
        $data['sum']  = $cartList['sum'];
        $this->result['data'] = $data;
        echo json_encode($this->result);exit;
    }


    //检查登录接口

    public function login()
    {
        $code  = IFilter::act(IReq::get('code'),'string');
        if(!$code)
        {
            $this->result['msg'] = 'fail,缺少code参数';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }
        extract(miniprogram::login($code));
        if($code === 200)
        {
            $this->result['data'] = ['token'=>$token];
        }
        $this->result['msg']  =  $msg;
        $this->result['code'] =  $code;
        echo json_encode($this->result);exit;
    }





}
