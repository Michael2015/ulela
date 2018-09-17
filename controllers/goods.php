<?php
/**
 * @brief 商品模块
 * @class Goods
 * @note  后台
 */
class Goods extends IController implements adminAuthorization
{
    public $checkRight  = 'all';
    public $layout = 'admin';
    private $data = array();

    public function init()
    {

    }
    /**
     * 选择规格数据
     */
    function select_spec()
    {
        $this->layout = '';
        $this->redirect('select_spec');
    }
    /**
     * @brief 商品添加中图片上传的方法
     */
    public function goods_img_upload()
    {

        //调用文件上传类
        $photoObj = new PhotoUpload();
        $photo    = current($photoObj->run());

        //判断上传是否成功，如果float=1则成功
        if($photo['flag'] == 1)
        {
            $result = array(
                'flag'=> 1,
                'img' => $photo['img']
            );
        }
        else
        {
            $result = array('flag'=> $photo['flag']);
        }
        echo JSON::encode($result);
    }
    /**
     * @brief 商品模型添加/修改
     */
    public function model_update()
    {
        // 获取POST数据
        $model_id   = IFilter::act(IReq::get("model_id"),'int');
        $model_name = IFilter::act(IReq::get("model_name"));
        $attribute  = IFilter::act(IReq::get("attr"));
        $spec       = IFilter::act(IReq::get("spec"));

        //初始化Model类对象
        $modelObj = new Model();

        //更新模型数据
        $result = $modelObj->model_update($model_id,$model_name,$attribute,$spec);

        if($result)
        {
            $this->redirect('model_list');
        }
        else
        {
            //处理post数据，渲染到前台
            $result = $modelObj->postArrayChange($attribute,$spec);
            $this->data = array(
                'id'         => $model_id,
                'name'       => $model_name,
                'model_attr' => $result['model_attr'],
                'model_spec' => $result['model_spec']
            );
            $this->setRenderData($this->data);
            $this->redirect('model_edit',false);
        }
    }
    /**
     * @brief 商品模型修改
     */
    public function model_edit()
    {
        // 获取POST数据
        $id = IFilter::act(IReq::get("id"),'int');
        if($id)
        {
            //初始化Model类对象
            $modelObj = new Model();
            //获取模型详细信息
            $model_info = $modelObj->get_model_info($id);
            //向前台渲染数据
            $this->setRenderData($model_info);
        }
        $this->redirect('model_edit');
    }

    /**
     * @brief 商品模型删除
     */
    public function model_del()
    {
        //获取POST数据
        $id = IFilter::act(IReq::get("id"),'int');
        $id = !is_array($id) ? array($id) : $id;

        if($id)
        {
            foreach($id as $key => $val)
            {
                //初始化goods_attribute表类对象
                $goods_attrObj = new IModel("goods_attribute");

                //获取商品属性表中的该模型下的数量
                $attrData = $goods_attrObj->query("model_id = ".$val);
                if($attrData)
                {
                    $this->redirect('model_list',false);
                    Util::showMessage("无法删除此模型，请确认该模型下以及回收站内都无商品");
                }

                //初始化Model表类对象
                $modelObj = new IModel("model");

                //删除商品模型
                $result = $modelObj->del("id = ".$val);
            }
        }
        $this->redirect('model_list');
    }
    /*
     * @breif 后台添加给商品规格
     * */
    function search_spec()
    {
        $this->layout = '';
        $data = array();

        //获得model_id的值
        $model_id = IFilter::act(IReq::get('model_id'),'int');
        $goods_id = IFilter::act(IReq::get('goods_id'),'int');
        $specId   = '';

        //1,获取商品的规格数据
        if($goods_id)
        {
            $tb_goods = new IModel('goods');
            $goods_info = $tb_goods->getObj('id = '.$goods_id,'spec_array');
            $data['goodsSpec'] = JSON::decode($goods_info['spec_array']);
            if($data['goodsSpec'])
            {
                foreach($data['goodsSpec'] as $item)
                {
                    $specId .= $item['id'].',';
                }
            }
        }
        //2,获取模型的规格数据
        if(!$specId && $model_id)
        {
            $modelObj  = new IModel('model');
            $modelInfo = $modelObj->getObj('id = '.$model_id,'spec_ids');
            $specId    = $modelInfo['spec_ids'] ? $modelInfo['spec_ids'] : '';
        }

        if($specId)
        {
            $specObj = new IModel('spec');
            $data['specData'] = $specObj->query('id in ('.trim($specId,',').')');
        }

        $this->setRenderData($data);
        $this->redirect("search_spec");
    }
    /**
     * @breif 后台添加为每一件商品添加会员价
     * */
    function member_price()
    {
        $this->layout = '';

        $goods_id   = IFilter::act(IReq::get('goods_id'),'int');
        $product_id = IFilter::act(IReq::get('product_id'),'int');
        $sell_price = IFilter::act(IReq::get('sell_price'),'float');

        $date = array(
            'sell_price' => $sell_price
        );

        if($goods_id)
        {
            $where  = 'goods_id = '.$goods_id;
            $where .= $product_id ? ' and product_id = '.$product_id : '';

            $priceRelationObject = new IModel('group_price');
            $priceData = $priceRelationObject->query($where);
            $date['price_relation'] = $priceData;
        }

        $this->setRenderData($date);
        $this->redirect('member_price');
    }
    /**
     * @brief 商品添加和修改视图
     */
    public function goods_edit()
    {
        $goods_id = IFilter::act(IReq::get('id'),'int');

        //初始化数据
        $goods_class = new goods_class();

        //获取所有商品扩展相关数据
        $data = $goods_class->edit($goods_id);

        if($goods_id && !$data)
        {
            die("没有找到相关商品！");
        }

        //dyg_jzw 20150819 当前商品的返利设置
        if($data['form']['commission'])
        {
            $data['form']['commission'] = json_decode($data['form']['commission'], true);
        }

        $this->setRenderData($data);
        $this->redirect('goods_edit');
    }
    /**
     * @brief 保存修改商品信息
     */
    function goods_update()
    {
        $id       = IFilter::act(IReq::get('id'),'int');
        $callback = IReq::get('callback');
        $callback = strpos($callback,'goods/goods_list') === false ? '' : $callback;

        //检查表单提交状态
        if(!$_POST)
        {
            die('请确认表单提交正确');
        }

        //初始化商品数据
        unset($_POST['id']);
        unset($_POST['callback']);

        $goodsObject = new goods_class();
        $goodsObject->update($id,$_POST);
        //记录日志
        $logObj = new log('db');
        $logObj->write('operation',array("管理员:".ISafe::get('admin_name'),"修改商品信息","商品ID：".$id."，名称：".IFilter::act(IReq::get('name'))));

        $callback ? $this->redirect($callback) : $this->redirect("goods_list");
    }

    /**
     * @brief 删除商品
     */
    function goods_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');

        //生成goods对象
        $tb_goods = new IModel('goods');
        $tb_goods->setData(array('is_del'=>1));
        if($id)
        {
            $tb_goods->update(Util::joinStr($id));
        }
        else
        {
            die('请选择要删除的数据');
        }
        $this->redirect("goods_list");
    }
    /**
     * @brief 商品上下架
     */
    function goods_stats()
    {
        //post数据
        $id   = IFilter::act(IReq::get('id'),'int');
        $type = IFilter::act(IReq::get('type'));

        //生成goods对象
        $tb_goods = new IModel('goods');
        if($type == 'up')
        {
            $updateData = array('is_del' => 0,'up_time' => ITime::getDateTime(),'down_time' => null);
        }
        else if($type == 'down')
        {
            $updateData = array('is_del' => 2,'up_time' => null,'down_time' => ITime::getDateTime());
        }
        else if($type == 'check')
        {
            $updateData = array('is_del' => 3,'up_time' => null,'down_time' => null);
        }

        $tb_goods->setData($updateData);

        if($id)
        {
            $tb_goods->update(Util::joinStr($id));
        }
        else
        {
            Util::showMessage('请选择要操作的数据');
        }

        if(IClient::isAjax() == false)
        {
            $this->redirect("goods_list");
        }
    }
    /**
     * @brief 商品彻底删除
     * */
    function goods_recycle_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');

        //生成goods对象
        $goods = new goods_class();
        $goodsDB = new IModel('goods');
        //生成relation对象
        $relationDB = new IModel('seller_goods_relation');
        //生成products对象
        $productsDB = new IModel('products');
        //生成分类category表
        $categoryDB = new IModel('category_extend');
        if($id)
        {
            if(is_array($id))
            {
                foreach($id as $key => $val)
                {
                    $seller_id = $goodsDB->query(' id = '.$val,'seller_id','',1)[0]['seller_id'];
                    $relationDB->del("seller_goods_id = ".$val." AND seller_id = ".$seller_id);//删除seller_goods_relation表复制后的数据
                    $productsDB->del("goods_id = ".$val);//删除product表复制后的数据
                    $categoryDB->del('goods_id = '.$val);//删除category分类表
                    $goods->del($val);
                }
            }
            else
            {
                $goods->del($id);
            }
        }

        $this->redirect("goods_recycle_list");
    }
    /**
     * @brief 商品还原
     * */
    function goods_recycle_restore()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');
        //生成goods对象
        $tb_goods = new IModel('goods');
        $tb_goods->setData(array('is_del'=>0));
        if($id)
        {
            $tb_goods->update(Util::joinStr($id));
        }
        else
        {
            Util::showMessage('请选择要删除的数据');
        }
        $this->redirect("goods_recycle_list");
    }

    /**
     * @brief 商品列表
     */
    function goods_list()
    {
        //搜索条件
        $search = IReq::get('search');
        $page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;

        //条件筛选处理
        list($join,$where) = goods_class::getSearchCondition($search);

        //拼接sql
        $goodsHandle = new IQuery('goods as go');
        $goodsHandle->order    = "go.id desc";
        $goodsHandle->fields   = "distinct go.id,go.name,go.sell_price,go.market_price,go.store_nums,go.img,go.is_del,go.seller_id,go.is_share,go.sort";
        $goodsHandle->page     = $page;
        $goodsHandle->where    = $where;
        $goodsHandle->join     = $join;

        $this->search      = $search;
        $this->goodsHandle = $goodsHandle;
        $this->redirect("goods_list");
    }
    //判断是否共享，判断时候是否是复制出来的
    function is_share_is_copy()
    {
    }
    //商品同步
    function sync_goods()
    {

    }
    //商品导出 Excel
    public function goods_report()
    {
        //搜索条件
        $search = IReq::get('search');
        //条件筛选处理
        list($join,$where) = goods_class::getSearchCondition($search);
        //拼接sql
        $goodsHandle = new IQuery('goods as go');
        $goodsHandle->order    = "go.id desc";
        $goodsHandle->fields   = "go.id, go.name,go.sell_price,go.store_nums,go.sale,go.is_del,go.create_time";
        $goodsHandle->join     = $join;
        $goodsHandle->where    = $where;
        $goodsList = $goodsHandle->find();

        //构建 Excel table;
        $reportObj = new report('goods');
        $reportObj->setTitle(array("商品名称","分类","售价","库存","销量","发布时间","状态"));
        foreach($goodsList as $k => $val)
        {
            $insertData = array(
                $val['name'],
                goods_class::getGoodsCategory($val['id']),
                $val['sell_price'],
                $val['store_nums'],
                $val['sale'],
                $val['create_time'],
                goods_class::statusText($val['is_del']),
            );
            $reportObj->setData($insertData);
        }
        $reportObj->toDownload();
    }

    /**
     * @brief 商品分类添加、修改
     */
    function category_edit()
    {
        $category_id = IFilter::act(IReq::get('cid'),'int');
        if($category_id)
        {
            $categoryObj = new IModel('category');
            $this->categoryRow = $categoryObj->getObj('id = '.$category_id);
        }
        $this->redirect('category_edit');
    }

    /**
     * @brief 保存商品分类
     */
    function category_save()
    {
        //获得post值
        $category_id = IFilter::act(IReq::get('id'),'int');
        $name = IFilter::act(IReq::get('name'));
        $parent_id = IFilter::act(IReq::get('parent_id'),'int');
        $visibility = IFilter::act(IReq::get('visibility'),'int');
        $sort = IFilter::act(IReq::get('sort'),'int');
        $title = IFilter::act(IReq::get('title'));
        $keywords = IFilter::act(IReq::get('keywords'));
        $descript = IFilter::act(IReq::get('descript'));

        if(!$name)
        {
            $this->redirect('category_list');
        }

        $tb_category = new IModel('category');
        $category_info = array(
            'name'      => $name,
            'parent_id' => $parent_id,
            'sort'      => $sort,
            'visibility'=> $visibility,
            'keywords'  => $keywords,
            'descript'  => $descript,
            'title'     => $title
        );
        $tb_category->setData($category_info);
        if($category_id)									//保存修改分类信息
        {
            $where = "id=".$category_id;
            $tb_category->update($where);
        }
        else												//添加新商品分类
        {
            $tb_category->add();
        }
        $this->redirect('category_list');
    }

    /**
     * @brief 删除商品分类
     */
    function category_del()
    {
        $category_id = IFilter::act(IReq::get('cid'),'int');
        if($category_id)
        {
            $tb_category = new IModel('category');
            $catRow      = $tb_category->getObj('parent_id = '.$category_id);

            //要删除的分类下还有子节点
            if(!empty($catRow))
            {
                $this->category_list();
                Util::showMessage('无法删除此分类，此分类下还有子分类，或者回收站内还留有子分类');
                exit;
            }

            if($tb_category->del('id = '.$category_id))
            {
                $tb_category_extend  = new IModel('category_extend');
                $tb_category_extend->del('category_id = '.$category_id);
                $this->redirect('category_list');
            }
            else
            {
                $this->category_list();
                $msg = "没有找到相关分类记录！";
                Util::showMessage($msg);
            }
        }
        else
        {
            $this->category_list();
            $msg = "没有找到相关分类记录！";
            Util::showMessage($msg);
        }
    }

    /**
     * @brief 商品分类列表
     */
    function category_list()
    {
        $isCache = false;
        $tb_category = new IModel('category');
        $cacheObj = new ICache('file');
        $data = $cacheObj->get('sortdata');
        if(!$data)
        {
            $goods = new goods_class();
            $data = $goods->sortdata($tb_category->query(false,'*','sort asc'));
            $isCache ? $cacheObj->set('sortdata',$data) : "";
        }
        $this->data['category'] = $data;
        $this->setRenderData($this->data);
        $this->redirect('category_list',false);
    }

    //修改规格页面
    function spec_edit()
    {
        $this->layout = '';

        $id        = IFilter::act(IReq::get('id'),'int');
        $seller_id = IFilter::act(IReq::get('seller_id'),'int');

        $dataRow = array(
            'id'        => '',
            'name'      => '',
            'type'      => '',
            'value'     => '',
            'note'      => '',
            'seller_id' => $seller_id,
        );

        if($id)
        {
            $obj     = new IModel('spec');
            $dataRow = $obj->getObj("id = {$id}");
        }

        $this->setRenderData($dataRow);
        $this->redirect('spec_edit');
    }

    //增加或者修改规格
    function spec_update()
    {
        $id         = IFilter::act(IReq::get('id'),'int');
        $name       = IFilter::act(IReq::get('name'));
        $specType   = IFilter::act(IReq::get('type'));
        $showValue  = $specType == 1 ? IReq::get('showText') : IReq::get('showImage');
        $note       = IFilter::act(IReq::get('note'));
        $seller_id  = IFilter::act(IReq::get('seller_id'),'int');
        $valueData  = IReq::get('valueData');

        //组合规格显示数据和数据实际数据
        $valueData = $valueData ? array_filter($valueData) : "";
        $showValue = $showValue ? array_filter($showValue) : "";
        $value = $valueData && $showValue && count($valueData) == count($showValue) ? array_filter(array_combine($valueData,$showValue)) : "";
        //要插入的数据
        if(is_array($value))
        {
            $value = $value ? IFilter::act(JSON::encode($value)) : '';
        }

        if(!$value)
        {
            die( JSON::encode(array('flag' => 'fail','message' => '规格值和提示信息不能为空，请填写完整')) );
        }

        if(!$name)
        {
            die( JSON::encode(array('flag' => 'fail','message' => '规格名称不能为空')) );
        }

        $editData = array(
            'id'        => $id,
            'name'      => $name,
            'value'     => $value,
            'type'      => $specType,
            'note'      => $note,
            'seller_id' => $seller_id,
        );

        //执行操作

        $obj = new IModel('spec');

        $obj->setData($editData);

        //更新修改
        if($id)
        {
            $where = 'id = '.$id;
            if($seller_id)
            {
                $where .= ' and seller_id = '.$seller_id;
            }
            $result = $obj->update($where);
        }
        //添加插入
        else
        {
            $result = $obj->add();
        }

        //执行状态
        if($result===false)
        {
            die( JSON::encode(array('flag' => 'fail','message' => '数据库更新失败')) );
        }
        else
        {
            //获取自动增加ID
            $editData['id'] = $id ? $id : $result;
            $editData['id'] = strval($editData['id']);
            $editData['value'] = IFilter::stripSlash($editData['value']);
            die( JSON::encode(array('flag' => 'success','data' => $editData)) );
        }
    }

    //批量删除规格
    function spec_del()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $obj = new IModel('spec');
            $obj->setData(array('is_del'=>1));
            $obj->update(Util::joinStr($id));
            $this->redirect('spec_list');
        }
        else
        {
            $this->redirect('spec_list',false);
            Util::showMessage('请选择要删除的规格');
        }
    }
    //彻底批量删除规格
    function spec_recycle_del()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $obj = new IModel('spec');
            $obj->del(Util::joinStr($id));
            $this->redirect('spec_recycle_list');
        }
        else
        {
            $this->redirect('spec_recycle_list',false);
            Util::showMessage('请选择要删除的规格');
        }
    }
    //批量还原规格
    function spec_recycle_restore()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $obj = new IModel('spec');
            $obj->setData(array('is_del'=>0));
            $obj->update(Util::joinStr($id));
            $this->redirect('spec_recycle_list');
        }
        else
        {
            $this->redirect('spec_recycle_list',false);
            Util::showMessage('请选择要还原的规格');
        }
    }
    //规格图片删除
    function spec_photo_del()
    {
        $id = IReq::get('id','post');
        if(isset($id[0]) && $id[0]!='')
        {
            $obj = new IModel('spec_photo');
            $id_str = '';
            foreach($id as $rs)
            {
                if($id_str!='')
                {
                    $id_str.=',';
                }
                $id_str.=$rs;

                $photoRow = $obj->getObj('id = '.$rs,'address');
                if(file_exists($photoRow['address']))
                {
                    unlink($photoRow['address']);
                }
            }

            $where = ' id in ('.$id_str.')';
            $obj->del($where);
            $this->redirect('spec_photo');
        }
        else
        {
            $this->redirect('spec_photo',false);
            Util::showMessage('请选择要删除的id值');
        }
    }

    /**
     * @brief 分类排序
     */
    function category_sort()
    {
        $category_id = IFilter::act(IReq::get('id'),'int');
        $sort = IFilter::act(IReq::get('sort'),'int');

        $flag = 0;
        if($category_id)
        {
            $tb_category = new IModel('category');
            $category_info = $tb_category->getObj('id='.$category_id);
            if(count($category_info)>0)
            {
                if($category_info['sort']!=$sort)
                {
                    $tb_category->setData(array('sort'=>$sort));
                    if($tb_category->update('id='.$category_id))
                    {
                        $flag = 1;
                    }
                }
            }
        }
        echo $flag;
    }
    /**
     * @brief 品牌分类排序
     */
    public function brand_sort()
    {
        $brand_id = IFilter::act(IReq::get('id'),'int');
        $sort = IFilter::act(IReq::get('sort'),'int');
        $flag = 0;
        if($brand_id)
        {
            $tb_brand = new IModel('brand');
            $brand_info = $tb_brand->getObj('id='.$brand_id);
            if(count($brand_info)>0)
            {
                if($brand_info['sort']!=$sort)
                {
                    $tb_brand->setData(array('sort'=>$sort));
                    if($tb_brand->update('id='.$brand_id))
                    {
                        $flag = 1;
                    }
                }
            }
        }
        echo $flag;
    }

    //修改排序
    public function ajax_sort()
    {
        $id   = IFilter::act(IReq::get('id'),'int');
        $sort = IFilter::act(IReq::get('sort'),'int');

        $goodsDB = new IModel('goods');
        $goodsDB->setData(array('sort' => $sort));
        $goodsDB->update("id = {$id}");
    }

    //更新库存
    public function update_store()
    {
        $data     = IFilter::act(IReq::get('data'),'int'); //key => 商品ID或货品ID ; value => 库存数量
        $goods_id = IFilter::act(IReq::get('goods_id'),'int');//存在即为货品
        $goodsSum = array_sum($data);

        if(!$data)
        {
            die(JSON::encode(array('result' => 'fail','data' => '商品数据不存在')));
        }

        //货品方式
        if($goods_id)
        {
            $productDB = new IModel('products');
            foreach($data as $key => $val)
            {
                $productDB->setData(array('store_nums' => $val));
                $productDB->update('id = '.$key);
            }
        }
        else
        {
            $goods_id = key($data);
        }

        $goodsDB = new IModel('goods');
        $goodsDB->setData(array('store_nums' => $goodsSum));
        $goodsDB->update('id = '.$goods_id);

        die(JSON::encode(array('result' => 'success','data' => $goodsSum)));
    }

    //更新商品价格
    public function update_price()
    {
        $data     = IFilter::act(IReq::get('data')); //key => 商品ID或货品ID ; value => 库存数量
        $goods_id = IFilter::act(IReq::get('goods_id'),'int');//存在即为货品

        if(!$data)
        {
            die(JSON::encode(array('result' => 'fail','data' => '商品数据不存在')));
        }

        //货品方式
        if($goods_id)
        {
            $productDB  = new IModel('products');
            $updateData = current($data);
            foreach($data as $pid => $item)
            {
                $productDB->setData($item);
                $productDB->update("id = ".$pid);
            }
        }
        else
        {
            $goods_id   = key($data);
            $updateData = current($data);
        }

        $goodsDB = new IModel('goods');
        $goodsDB->setData($updateData);
        $goodsDB->update('id = '.$goods_id);

        die(JSON::encode(array('result' => 'success','data' => number_format($updateData['sell_price'],2))));
    }

    //更新商品推荐标签
    public function update_commend()
    {
        $data = IFilter::act(IReq::get('data')); //key => 商品ID或货品ID ; value => commend值 1~4
        if(!$data)
        {
            die(JSON::encode(array('result' => 'fail','data' => '商品数据不存在')));
        }

        $goodsCommendDB = new IModel('commend_goods');

        //清理旧的commend数据
        $goodsIdArray = array_keys($data);
        $goodsCommendDB->del("goods_id in (".join(',',$goodsIdArray).")");

        //插入新的commend数据
        foreach($data as $id => $commend)
        {
            foreach($commend as $k => $value)
            {
                if($value > 0)
                {
                    $goodsCommendDB->setData(array('commend_id' => $value,'goods_id' => $id));
                    $goodsCommendDB->add();
                }
            }
        }
        die(JSON::encode(array('result' => 'success')));
    }

    //商品共享
    public function goods_share()
    {
        $idArray = explode(',',IReq::get('id'));
        $id      = IFilter::act($idArray,'int');

        $goodsDB = new IModel('goods');
        $goodsData = $goodsDB->query('id in ('.join(',',$id).')');

        foreach($goodsData as $key => $val)
        {
            $is_share = $val['is_share'] == 1 ? 0 : 1;
            $goodsDB->setData(array('is_share' => $is_share));
            $goodsDB->update('id = '.$val['id'].' and seller_id = 0');
        }
    }

    /**
     * @brief 商品批量设置
     */
    function goods_setting()
    {
        $idArray   = explode(',',IReq::get('id'));
        $id        = IFilter::act($idArray,'int');
        $seller_id = IFilter::act(IReq::get('seller_id'),'int');

        if (empty($id))
        {
            exit('请选择您要操作的商品');
        }
        $data = array();
        $data['goods_id']  = implode(",", $id);
        $data['seller_id'] = $seller_id;

        $this->layout = '';
        $this->setRenderData($data);
        $this->redirect('goods_setting');
    }

    /**
     * @brief 保存商品批量设置
     */
    function goods_setting_save()
    {
        $idArray   = explode(',',IReq::get('goods_id', 'post'));
        $seller_id = IFilter::act(IReq::get('seller_id'),'int');
        $idArray   = IFilter::act($idArray,'int');

        if (empty($idArray))
        {
            exit('请首先选择您要操作的商品');
        }

        $goodsObject = new goods_class($seller_id);
        $goodsObject->multiUpdate($idArray, $_POST);
        die('<script type="text/javascript">parent.artDialogCallback();</script>');
    }
    /**
     * @brief  通过goods_no或者拼音查找该商品
     */
    function search_goods_by_goodsno()
    {

    }
    //添加模板
    public function edit_template()
    {
        if(plugin::getItems('template_manage')['is_open']!=1 || plugin::getItems('template_manage')['is_install']!=1)
        {
            exit('商城模板插件还没安装或者开启');
        }
        $tplDB = new IModel('template');
        $id = IFilter::act(IReq::get('id'),'int');
        //更新
        if($_POST)
        {
            unset($_POST['id']);
            $_POST['tpl_cid'] = isset($_POST['tpl_cid'])?json_encode($_POST['tpl_cid']):'';
            //更新模板数据
            if($id)
            {
                $_POST['tpl_edit_time'] = date('Y-m-d H:i:s');
                $tplDB->setData($_POST);
                $tplDB->update('id = '.$id);
            }
            //添加模板
            else
            {
                $_POST['tpl_create_time'] = date('Y-m-d H:i:s');
                $tplDB->setData($_POST);
                $tplDB->add();
            }
            $this->redirect('template_list',true);
        }
        //编辑
        if($id)
        {
             $tmlData = $tplDB->getObj(' id = '.$id);
             $this->setRenderData($tmlData);
        }
        $this->redirect('edit_template');
    }
    //模板列表
    public function template_list()
    {
        if(plugin::getItems('template_manage')['is_open']!=1 || plugin::getItems('template_manage')['is_install']!=1)
        {
            exit('商城模板插件还没安装或者开启');
        }
        $page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $templateHandle = new IQuery('template as tpl');
        $templateHandle->order    = "tpl.tpl_sort desc,tpl.tpl_create_time desc";
        $templateHandle->fields   = "*";
        $templateHandle->page     = $page;
        $templateHandle->where    = 1;
        $this->templateHandle = $templateHandle;
        $this->redirect('template_list');
    }
    //删除模板
    public function template_del()
    {
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');
        //生成template对象
        $tb_template = new IModel('template');
        if($id)
        {
            $tb_template->del('id = '.$id);
        }
        else
        {
            die('请选择要删除的数据');
        }
        $this->redirect("template_list");
    }
}
