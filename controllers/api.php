<?php
/*
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


    //获取一级分类
    public function get_category()
    {
        $catObj = new IModel('category');
        $ids = $catObj->query('', 'parent_id');
        $catRow = $catObj->query('parent_id = 0 and  id in ('.implode(',',array_column($ids,'parent_id')).')', 'id,name,sort');
        $this->result['data'] = $catRow;
        echo json_encode($this->result);
    }
    //获取二级分类
    public function get_children_category()
    {
        $catObj = new IModel('category');
        $parentId = IFilter::act(IReq::get('parent_id'), 'int');//父级id
        if(!$parentId)
        {
            $this->result['msg'] = 'fail,缺少参数';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }

        $catRow = $catObj->query('parent_id = '.$parentId, 'id,name,sort');
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
        preg_match_all('#<img.*?>#',$goods_info['content'],$match);

        $content = [];
        if($match)
        {
            foreach ($match as $img)
            {
                preg_match_all('#<img\s+src="(.*?)"\s+alt="(.*?)"[^>]+>#',$img[0],$src);
                if($src)
                {
                    $content[] = ['text'=>$src[2][0],'img'=>$src[1][0]];
                }
            }
        }

        if($goods_info)
        {
            $data['id'] = $goods_info['id'];
            $data['sell_price'] = $goods_info['sell_price'];
            $data['store_nums'] = $goods_info['store_nums'];
            $data['img'] = $goods_info['img'];
            $data['content'] = $content;
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

    //添加地址
    public function add_edit_address()
    {
        $id          = IFilter::act(IReq::get('id'),'int');
        $token          = IFilter::act(IReq::get('token'),'string');
        $accept_name = IFilter::act(IReq::get('accept_name'),'name');
        $address = IFilter::act(IReq::get('address'));
        $mobile      = IFilter::act(IReq::get('mobile'),'mobile');
        $zone      = IFilter::act(IReq::get('zone'),'zone');
        $default = IReq::get('is_default')!= 1 ? 0 : 1;
        $model = new IModel('address');
        $data = array(
            'user_id'=>1,
            'accept_name'=>$accept_name,
            'province'=>'',
            'city'=>'',
            'area'=>'',
            'address'=>$address,
            'mobile'=>$mobile,
            'zone'=>$zone,
            'is_default'=>$default
        );
        if (empty($accept_name) || empty($address) || empty($mobile) || empty($zone))
        {

            $this->result['msg'] = '添加失败,信息不全';
            $this->result['code'] = 0;
            echo json_encode($this->result);exit;
        }
        $model->setData($data);

        if($id == '')
        {
            $model->add();
        }
        else
        {
            $model->update('id = '.$id);
        }
        $this->result['msg'] = '成功';
        echo json_encode($this->result);exit;
    }

    //获取地址列表
    public function get_my_address()
    {
        $user_id = 1;
        $query = new IQuery('address');
        $query->where = 'user_id = '.$user_id;
        $query->fields = 'zone,id,address,accept_name,mobile,is_default';
        $address = $query->find();
        $this->result['data'] = $address;
        echo json_encode($this->result);exit;
    }

    //获取单个地址
    public function get_address_by_id()
    {

        $id          = IFilter::act(IReq::get('id'),'int');
        $user_id = 1;
        $query = new IQuery('address');
        $query->where = 'user_id = '.$user_id.' and id='.$id;
        $query->fields = 'zone,id,address,accept_name,mobile,is_default';
        $address = $query->find();
        if($address)
        {
            $this->result['data'] = $address[0];
            echo json_encode($this->result);exit;
        }
    }

    //设置默认地址
    public function set_address_default()
    {
        $user_id = 1;
        $id = IFilter::act( IReq::get('id'),'int' );
        $default = IFilter::act(IReq::get('is_default'));
        $model = new IModel('address');
        if($default == 1)
        {
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$user_id);
        }
        $model->setData(array('is_default' => $default));
        $model->update("id = ".$id." and user_id = ".$user_id);
        $this->result['msg'] = '设置成功';
        echo json_encode($this->result);exit;
    }
    //删除地址

    public function del_my_address()
    {
        $user_id = 1;
        $id = IFilter::act( IReq::get('id'),'int' );
        $model = new IModel('address');
        $model->del('id = '.$id.' and user_id = '.$user_id);
        $this->result['msg'] = '删除成功';
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
