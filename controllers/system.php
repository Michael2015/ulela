<?php
/**
 * @brief 系统模块
 * @class System
 * @note  后台
 */
class System extends IController implements adminAuthorization
{
    public $checkRight  = array('check' => 'all','uncheck' => array('default','admin_repwd','admin_repwd_act','navigation','navigation_update','navigation_del','navigation_edit','navigation_recycle','navigation_recycle_del','navigation_recycle_restore'));
    public $layout      = 'admin';

    public function init()
    {

    }

    //邮件发送测试
    function test_sendmail()
    {
        $site_config                 = array();
        $site_config['email_type']   = IReq::get('email_type');
        $site_config['mail_address'] = IReq::get('mail_address');
        $site_config['smtp']         = IReq::get('smtp');
        $site_config['smtp_user']    = IReq::get('smtp_user');
        $site_config['smtp_pwd']     = IReq::get('smtp_pwd');
        $site_config['smtp_port']    = IReq::get('smtp_port');
        $site_config['email_safe']   = IReq::get('email_safe');
        $test_address                = IReq::get('test_address');

        $smtp = new SendMail($site_config);
        if($error = $smtp->getError())
        {
            $result = array('isError'=>true,'message' => $error);
        }
        else
        {
            $title    = 'email test';
            $content  = '您好，这是来自iWebShop系统的测试邮件，如果您能收到此邮件那么恭喜您，系统邮件服务正常。';
            if($smtp->send($test_address,$title,$content))
            {
                $result = array('isError'=>false,'message' => '恭喜你！测试通过');
            }
            else
            {
                $result = array('isError'=>true,'message' => $smtp->getError());
            }
        }
        echo JSON::encode($result);
    }

    //列出控制器
    function list_controller()
    {
        $planPath = $this->app->getBasePath().'controllers';
        $planList = array();
        $dirRes   = opendir($planPath);

        while(false !== ($dir = readdir($dirRes)))
        {
            if($dir[0] == ".")
            {
                continue;
            }
            $planList[] = basename($dir,'.php');
        }
        echo JSON::encode($planList);
    }

    //列出某个控制器的action动作和视图
    function list_action()
    {
        $ctrlId     = IReq::get('ctrlId');
        if($ctrlId != '')
        {
            $baseContrl = get_class_methods('IController');
            $advContrl  = get_class_methods($ctrlId);
            $diffArray  = array_diff($advContrl,$baseContrl);
            echo JSON::encode($diffArray);
        }
    }

    /**
     * @brief 配送方式修改
     */
    public function delivery_edit()
    {
        $data = array();
        $id = IFilter::act(IReq::get('id'),'int');

        if($id)
        {
            $delivery = new IModel('delivery');
            $data = $delivery->getObj('id = '.$id);
        }

        //获取省份
        $areaData = array();
        $areaDB = new IModel('areas');
        $areaList = $areaDB->query('parent_id = 0');
        foreach($areaList as $val)
        {
            $areaData[$val['area_id']] = $val['area_name'];
        }
        $this->areaList  = $areaList;
        $this->data_info = $data;
        $this->area      = $areaData;
        $this->redirect('delivery_edit');
    }

    /**
     * @brief 配送方式删除和还原
     */
    public function delivery_operate()
    {
        $id = IReq::get('id');
        $op = IReq::get('op');
        if(is_array($id)) $id = implode(',',$id);

        if(empty($id))
        {
            if($op == 'del' || $op == 'recover')
            {
                $this->redirect('delivery_recycle',false);
            }
            else
            {
                $this->redirect('delivery',false);
            }
            Util::showMessage('请选择要操作的选项');
            exit;
        }

        $delivery     =  new IModel('delivery');
        $deliveryData = $delivery->query('id in ('.$id.')','name');
        $deliveryName = array();
        foreach($deliveryData as $val)
        {
            $deliveryName[] = $val['name'];
        }

        $logObj = new log('db');

        //物理删除
        if($op=='del')
        {
            $delivery->del('id in('.$id.')');

            $logObj->write('operation',array("管理员:".$this->admin['admin_name'],"删除了回收站中的配送方式","被删除的配送方式为：".join(',',$deliveryName)));

            $this->redirect('delivery_recycle');
        }
        else if($op =='recover')//还原
        {
            $delivery->setData(array('is_delete'=>0));
            if($delivery->update('id in('.$id.')'))
            {
                $logObj->write('operation',array('管理员:'.$this->admin['admin_name'],'恢复了回收站中的配送方式','被恢复的配送方式为：'.join(',',$deliveryName)));
            }

            $this->redirect('delivery_recycle');
        }
        else//逻辑删除
        {
            $delivery->setData(array('is_delete'=>1));
            if($delivery->update('id in('.$id.')'))
            {
                $logObj->write('operation',array("管理员:".$this->admin['admin_name'],"把配送方式移除到回收站中","被移除到回收站中的配送方式为：".join(',',$deliveryName)));
            }

            $this->redirect('delivery');
        }
    }

    /**
     * 配送方式修改
     */
    public function delivery_update()
    {
        $delivery = new IModel('delivery');
        //ID
        $id   = IFilter::act(IReq::get('id'),'int');
        //配送方式名称
        $name = IFilter::act(IReq::get('name'));
        //类型
        $type = IFilter::act(IReq::get('type'),'int');
        //首重重量
        $first_weight = IFilter::act(IReq::get('first_weight'),'float');
        //续重重量
        $second_weight = IFilter::act(IReq::get('second_weight'),'float');
        //首重价格
        $first_price = IFilter::act(IReq::get('first_price'),'float');
        //续重价格
        $second_price = IFilter::act(IReq::get('second_price'),'float');
        //是否支持物流保价
        $is_save_price = IFilter::act(IReq::get('is_save_price'),'int');
        //地区费用类型
        $price_type = IFilter::act(IReq::get('price_type'),'int');
        //启用默认费用
        $open_default = IFilter::act(IReq::get('open_default'),'int');
        //支持的配送地区ID
        $area_groupid = serialize(IReq::get('area_groupid'));
        //配送地址对应的首重价格
        $firstprice = serialize(IReq::get('firstprice'));
        //配送地区对应的续重价格
        $secondprice = serialize(IReq::get('secondprice'));
        //排序
        $sort = IFilter::act(IReq::get('sort'),'int');
        //状态
        $status = IFilter::act(IReq::get('status'),'int');
        //描述
        $description = IFilter::act(IReq::get('description'),'text');
        //保价费率
        $save_rate = IFilter::act(IReq::get('save_rate'),'float');
        //最低保价
        $low_price = IFilter::act(IReq::get('low_price'),'float');

        //dyg_jzw 20160309 是否跨境电商专用
        $is_cbe = IFilter::act(IReq::get('is_cbe'),'int');

        $data = array(
            'name'         => $name,
            'type'         => $type,
            'first_weight' => $first_weight,
            'second_weight'=> $second_weight,
            'first_price'  => $first_price,
            'second_price' => $second_price,
            'is_save_price'=> $is_save_price,
            'price_type'   => $price_type,
            'open_default' => $open_default,
            'area_groupid' => $area_groupid,
            'firstprice'   => $firstprice,
            'secondprice'  => $secondprice,
            'sort'         => $sort,
            'status'       => $status,
            'description'  => $description,
            'save_rate'    => $save_rate,
            'low_price'    => $low_price,
            'is_cbe'	   => $is_cbe, //dyg_jzw 20160309 是否跨境电商专用
        );
        //如果选择指定地区配送就必须要选择地区
        if($price_type == 1 && !$area_groupid)
        {
            die('请设置配送地区');
        }
        $delivery->setData($data);
        $logObj = new log('db');

        if($id=="")
        {
            if($delivery->add())
            {
                $logObj->write('operation',array("管理员:".$this->admin['admin_name'],"添加了配送方式",'添加的配送方式为：'.$name));
            }
        }
        else
        {
            if($delivery->update('id = '.$id))
            {
                $logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改了配送方式",'修改的配送方式为：'.$name));
            }
        }
        $this->redirect('delivery');
    }

    /**
     * 添加/修改支付方式插件
     */
    function payment_edit()
    {
        $payment_id = IFilter::act(IReq::get("id"),'int');
        $paymentRow = array();
        $paymentObj = new IModel('payment');
        $paymentRow = $paymentObj->getObj("id = ".$payment_id);
        if($paymentRow)
        {
            $this->paymentRow = $paymentRow;
            $this->redirect('payment_edit');
        }
        if(!$paymentRow)
        {
            IError::show(403,"支付方式不存在");
        }
    }

    /**
     * @brief 更新支付方式插件
     */
    function payment_update()
    {
        //获取Post数据
        $payment_id    = IFilter::act(IReq::get("id"),'int');
        $name          = IFilter::act(IReq::get("name"));
        $poundage_type = IFilter::act(IReq::get("poundage_type"),'int');
        $poundage      = IFilter::act(IReq::get("poundage"),'float');
        $order         = IFilter::act(IReq::get("order"),'int');
        $note          = IFilter::act(IReq::get('note'),'text');
        $status        = IFilter::act(IReq::get('status'),'int');
        $client_type   = IFilter::act(IReq::get('client_type'),'int');
        $config_param  = array();

        $paymentInstance = Payment::createPaymentInstance($payment_id);
        $configParam     = $paymentInstance->configParam();
        foreach($configParam as $key => $val)
        {
            $config_param[$key] = IFilter::act(IReq::get($key));
        }
        $config_param = IFilter::act(JSON::encode($config_param));

        $updateData = array(
            'name'          => $name,
            'poundage_type' => $poundage_type,
            'poundage'      => $poundage,
            'order'         => $order,
            'note'          => $note,
            'status'        => $status,
            'config_param'  => $config_param,
            'client_type'   => $client_type,
        );

        $paymentDB = new IModel('payment');
        $paymentDB->setData($updateData);
        $paymentDB->update('id = '.$payment_id);

        //日志记录
        $logObj = new log('db');
        $logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改了支付方式",'修改的支付方式为：'.$name));

        $this->redirect('payment_list');
    }
    //[网站管理][站点设置]保存
    function save_conf()
    {
        if(!$_POST)
        {
            $this->redirect('conf_base');
        }

        //错误信息
        $form_index = IFilter::act(IReq::get('form_index'));
        switch($form_index)
        {
            case "base_conf":
            {
                if(isset($_FILES['logo']['name']) && $_FILES['logo']['name']!='')
                {
                    $uploadObj = new PhotoUpload();
                    $uploadObj->setIterance(false);
                    $photoInfo = $uploadObj->run();
                    if(isset($photoInfo['logo']['img']) && file_exists($photoInfo['logo']['img']))
                    {
                        $_POST['logo'] = $photoInfo['logo']['img'];
                    }
                }
            }
                break;

            case "site_footer_conf":
            {
                $_POST['site_footer_code']=preg_replace('![\\r\\n]+!',"",$_POST['site_footer_code']);
            }
                break;

            case "index_slide":
            case "index_slide_mobile":
            {
                $config_slide = array();
                if(isset($_POST['slide_name']))
                {
                    foreach($_POST['slide_name'] as $key => $value)
                    {
                        $config_slide[$key]['name'] = $value;
                        $config_slide[$key]['url']  = $_POST['slide_url'][$key];
                        $config_slide[$key]['img']  = $_POST['slide_img'][$key];
                    }
                }

                if( isset($_FILES['slide_pic']) )
                {
                    $uploadObj = new PhotoUpload();
                    $uploadObj->setIterance(false);
                    $slideInfo = $uploadObj->run();

                    if(isset($slideInfo['slide_pic']['flag']))
                    {
                        $slideInfo['slide_pic'] = array($slideInfo['slide_pic']);
                    }

                    if(isset($slideInfo['slide_pic']))
                    {
                        foreach($slideInfo['slide_pic'] as $key=>$value)
                        {

                            if($value['flag']==1)
                            {
                                $config_slide[$key]['img']=$value['img'];
                            }
                        }
                    }

                }
                $_POST = array($form_index => serialize( $config_slide ));
            }
                break;

            //导航写入数据库，不需要记录配置文件
            case "guide_conf":
            {
                $guideName = IFilter::act(IReq::get('guide_name'));
                $guideLink = IFilter::act(IReq::get('guide_link'));
                $data      = array();

                $guideObj = new IModel('guide');

                if(!empty($guideName))
                {
                    foreach($guideName as $key => $val)
                    {
                        if(!empty($val) && !empty($guideLink[$key]))
                        {
                            $data[$key]['name'] = $val;
                            $data[$key]['link'] = $guideLink[$key];
                        }
                    }
                }

                //清空导航栏
                $guideObj->del('all');

                if($data)
                {
                    //插入数据
                    foreach($data as $order => $rs)
                    {
                        $dataArray = array(
                            'order' => $order,
                            'name'  => $rs['name'],
                            'link'  => $rs['link'],
                        );
                        $guideObj->setData($dataArray);
                        $guideObj->add();
                    }

                }
            }
                break;

            case "other_conf":
            {
                if( isset($_POST['auto_finish']) && $_POST['auto_finish']=="" )
                {
                    $_POST['auto_finish']=="0";
                }
            }
                break;

            case "mail_conf":
                break;
            case "system_conf":
                break;
        }

        //获取输入的数据
        $inputArray = $_POST;

        if($form_index == 'system_conf')
        {
            //写入的配置文件
            $configFile = IWeb::$app->getBasePath().'config/config.php';
            Config::edit($configFile,$inputArray);
        }
        else
        {
            $siteObj = new Config('site_config');
            $siteObj->write($inputArray);
        }
        $this->redirect('/system/conf_base/form_index/'.$form_index);
    }
    //网站设置页面
    function conf_base()
    {
        $this->confRow = array_merge(IWeb::$app->config,$this->_siteConfig->getInfo(),array("form_index" => IFilter::act(IReq::get('form_index'))));
        $this->redirect('conf_base');
    }

    //[权限管理][管理员]管理员添加，修改[单页]
    function admin_edit()
    {
        $id =IFilter::act( IReq::get('id'),'int' );
        if($id)
        {
            $adminObj = new IModel('admin');
            $where = 'id = '.$id;
            $this->adminRow = $adminObj->getObj($where);
        }
        $this->redirect('admin_edit');
    }

    //[权限管理][管理员]检查admin_user唯一性
    function check_admin($name = null,$id = null)
    {
        //php校验$name!=null , ajax校验 $name == null
        $admin_name = ($name==null) ? IReq::get('admin_name','post') : $name;
        $admin_id   = ($id==null)   ? IReq::get('admin_id','post')   : $id;
        $admin_name = IFilter::act($admin_name);
        $admin_id = intval($id);


        $adminObj = new IModel('admin');
        if($admin_id)
        {
            $where = 'admin_name = "'.$admin_name.'" and id != '.$admin_id;
        }
        else
        {
            $where = 'admin_name = "'.$admin_name.'"';
        }

        $adminRow = $adminObj->getObj($where);

        if(!empty($adminRow))
        {
            if($name != null)
            {
                return false;
            }
            else
            {
                echo '-1';
            }
        }
        else
        {
            if($name != null)
            {
                return true;
            }
            else
            {
                echo '1';
            }
        }
    }

    //[权限管理][管理员]管理员添加，修改[动作]
    function admin_edit_act()
    {
        $id = IFilter::act( IReq::get('id','post') );
        $adminObj = new IModel('admin');


        $dataArray = array(
            'id'         => $id,
            'admin_name' => IFilter::string( IReq::get('admin_name','post') ),
            'role_id'    => IFilter::act( IReq::get('role_id','post') ),
            'email'      => IFilter::string( IReq::get('email','post') ),
        );

        //检查管理员name唯一性
        $isPass = $this->check_admin($dataArray['admin_name'],$id);
        if($isPass == false)
        {
            $this->setError($dataArray['admin_name'].'管理员已经存在,请更改名字');
        }

        //提取密码 [ 密码设置 ]
        $password   = IReq::get('password','post');
        $repassword = IReq::get('repassword','post');

        if($password || $repassword)
        {
            if(!$password || !$repassword || $password != $repassword)
            {
                $this->setError('密码不能为空,并且二次输入的必须一致');
            }
            else
            {
                $dataArray['password'] = md5($password);
            }
        }

        //有错误
        if($this->getError())
        {
            $this->adminRow = $dataArray;
            $this->redirect('admin_edit',false);
            Util::showMessage($this->getError());
        }

        //修改操作
        if($id)
        {
            $where = 'id = '.$id;
            $adminObj->setData($dataArray);
            $adminObj->update($where);

            //修改为自身密码时
            if($id == $this->admin['admin_id'])
            {
                //同步更新safe
                ISafe::set('admin_name',$dataArray['admin_name']);
                if(isset($dataArray['password']))
                {
                    ISafe::set('admin_pwd',$dataArray['password']);
                }
            }
        }
        //添加操作
        else
        {

            $dataArray['create_time'] = ITime::getDateTime();
            $adminObj->setData($dataArray);
            $adminObj->add();
        }
        $this->redirect('admin_list');
    }

    //[权限管理][管理员]管理员更新操作[回收站操作][物理删除]
    function admin_update()
    {
        $id = IFilter::act( IReq::get('id') ,'int' );

        if($id == 1 || (is_array($id) && in_array(1,$id)))
        {
            $this->redirect('admin_list',false);
            Util::showMessage('不允许删除系统初始化管理员');
        }

        //是否为回收站操作
        $isRecycle = IReq::get('recycle');

        if(!empty($id))
        {
            $obj   = new IModel('admin');
            $where = Util::joinStr($id);

            if($isRecycle === null)
            {
                $obj->del($where);
                $this->redirect('admin_recycle');
            }
            else
            {
                //回收站操作类型
                $is_del = ($isRecycle == 'del') ? 1 : 0;
                $obj->setData(array('is_del' => $is_del));
                $obj->update($where);
                $this->redirect('admin_list');
            }
        }
        else
        {
            if($isRecycle == 'del')
                $this->redirect('admin_list',false);
            else
                $this->redirect('admin_recycle',false);

            Util::showMessage('请选择要操作的管理员ID');
        }
    }

    //[权限管理][角色] 角色更新操作[回收站操作][物理删除]
    function role_update()
    {
        $id = IFilter::act( IReq::get('id'),'int' );

        //是否为回收站操作
        $isRecycle = IReq::get('recycle');

        if(!empty($id))
        {
            $obj   = new IModel('admin_role');
            $where = Util::joinStr($id);

            if($isRecycle === null)
            {
                $obj->del($where);
                $this->redirect('role_recycle');
            }
            else
            {
                //回收站操作类型
                $is_del    = ($isRecycle == 'del') ? 1 : 0;
                $obj->setData(array('is_del' => $is_del));
                $obj->update($where);
                $this->redirect('role_list');
            }
        }
        else
        {
            if($isRecycle == 'del')
                $this->redirect('role_list',false);
            else
                $this->redirect('role_recycle',false);

            Util::showMessage('请选择要操作的角色ID');
        }
    }

    //[权限管理][角色] 角色修改,添加 [单页]
    function role_edit()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        if($id)
        {
            $adminObj = new IModel('admin_role');
            $where = 'id = '.$id;
            $this->roleRow = $adminObj->getObj($where);
        }

        //获取权限码分组形势
        $rightObj  = new IModel('right');
        $rightData = $rightObj->query('is_del = 0','*','name asc');

        $rightArray     = array();
        $rightUndefined = array();
        foreach($rightData as $key => $item)
        {
            preg_match('/\[.*?\]/',$item['name'],$localPre);
            if(isset($localPre[0]))
            {
                $arrayKey = trim($localPre[0],'[]');
                $rightArray[$arrayKey][] = $item;
            }
            else
            {
                $rightUndefined[]      = $item;
            }
        }

        $this->rightArray     = $rightArray;
        $this->rightUndefined = $rightUndefined;

        $this->redirect('role_edit');
    }

    //[权限管理][角色] 角色修改,添加 [动作]
    function role_edit_act()
    {
        $id = IFilter::act( IReq::get('id','post') );
        $roleObj = new IModel('admin_role');

        //要入库的数据
        $dataArray = array(
            'id'     => $id,
            'name'   => IFilter::string( IReq::get('name','post') ),
            'rights' => null,
        );

        //检查权限码是否为空
        $rights = IFilter::act( IReq::get('right','post') );
        if(!$rights || $rights[0]=='')
        {
            $this->redirect("/system/role_edit/id/{$id}/_msg/fail");
            exit;
        }

        //拼接权限码
        $rightsArray = array();
        $rightObj    = new IModel('right');
        $rightList   = $rightObj->query('id in ('.join(",",$rights).')','`right`');
        foreach($rightList as $key => $val)
        {
            $rightsArray[] = trim($val['right'],',');
        }

        $dataArray['rights'] = empty($rightsArray) ? '' : ','.join(',',$rightsArray).',';
        $roleObj->setData($dataArray);
        if($id)
        {
            $where = 'id = '.$id;
            $roleObj->update($where);
        }
        else
        {
            $roleObj->add();
        }
        $this->redirect('role_list');
    }

    //[权限管理][权限] 权限修改，添加[单页]
    function right_edit()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        if($id)
        {
            $adminObj = new IModel('right');
            $where = 'id = '.$id;
            $this->rightRow = $adminObj->getObj($where);
        }

        $this->redirect('right_edit');
    }

    //[权限管理][权限] 权限修改，添加[动作]
    function right_edit_act()
    {
        $id    = IFilter::act( IReq::get('id','post') );
        $right = IFilter::act( array_unique(IReq::get('right')) );
        $name  = IFilter::act( IReq::get('name','post') );

        if(!$right)
        {
            $this->rightRow = array(
                'id'   => $id,
                'name' => $name,
            );
            $this->redirect('right_edit',false);
            Util::showMessage('权限码不能为空');
            exit;
        }

        $dataArray = array(
            'id'    => $id,
            'name'  => $name,
            'right' => join(',',$right),
        );

        $rightObj = new IModel('right');
        $rightObj->setData($dataArray);
        if($id)
        {
            $where = 'id = '.$id;
            $rightObj->update($where);
        }
        else
        {
            $rightObj->add();
        }
        $this->redirect('right_list');
    }

    //[权限管理][权限] 权限更新操作 [回收站操作][物理删除]
    function right_update()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        //是否为回收站操作
        $isRecycle = IReq::get('recycle');

        if(!empty($id))
        {
            $obj   = new IModel('right');
            $where = Util::joinStr($id);

            if($isRecycle === null)
            {
                $obj->del($where);
                $this->redirect('right_recycle');
            }
            else
            {
                //回收站操作类型
                $is_del    = ($isRecycle == 'del') ? 1 : 0;
                $obj->setData(array('is_del' => $is_del));
                $obj->update($where);
                $this->redirect('right_list');
            }
        }
        else
        {
            if($isRecycle == 'del')
                $this->redirect('right_list',false);
            else
                $this->redirect('right_recycle',false);

            Util::showMessage('请选择要操作的权限ID');
        }
    }



    //清理缓存
    function clearCache()
    {
        $runtimePath = IWeb::$app->getBasePath().'runtime';
        $result      = IFile::clearDir($runtimePath);

        if($result == true)
            echo 1;
        else
            echo -1;
    }

    //主题列表
    function conf_ui()
    {
        $themeType = IReq::get('type') ? IFilter::act(IReq::get('type')) : 'site';
        $themeList = themeroute::themeTypeList($themeType);
        if(!$themeList)
        {
            IError::show(403,'主题信息不存在');
        }
        $themeTypeName = themeroute::themeTypeTxt($themeType);
        $this->setRenderData(
            array('themeTypeName' => $themeTypeName,'themeList' => $themeList)
        );
        $this->redirect('conf_ui');
    }
    //启用主题
    function applyTheme()
    {
        $type = IFilter::act(IReq::get('type'));
        $issetConfig = IWeb::$app->config['theme'];
        foreach(IClient::supportClient() as $key => $client)
        {
            $clientTheme = IReq::get($client);
            if($clientTheme)
            {
                $clientData = JSON::decode($clientTheme);

                //配置文件中是否已经存在
                if(isset( $issetConfig[$client] ))
                {
                    //此次启用的主题类型
                    $themeType = themeroute::themeType( key($clientData) );
                    if(!$themeType)
                    {
                        die($clientTheme."无法识别主题类型");
                    }
                    foreach($issetConfig[$client] as $theme => $skin)
                    {
                        $issetThemeType = themeroute::themeType($theme);
                        if(!$issetThemeType || $themeType == $issetThemeType)
                        {
                            unset($issetConfig[$client][$theme]);
                        }
                    }
                }
                else
                {
                    $issetConfig[$client] = array();
                }
                $issetConfig[$client] = array_merge($issetConfig[$client],$clientData);
            }
        }

        if($issetConfig != IWeb::$app->config['theme'])
        {
            IWeb::$app->config['theme'] = $issetConfig;
            Config::edit('config/config.php',array("theme" => IWeb::$app->config['theme']));
        }
        $this->redirect('/system/conf_ui/type/'.$type.'/_msg/success');
    }
    //管理员添加快速导航
    function navigation_edit()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $navigationObj = new IModel('quick_naviga');
            $where = 'id = '.$id;
            $this->navigationRow = $navigationObj->getObj($where);
        }
        $this->redirect('navigation_edit');
    }
    //保存管理员添加快速导航
    function navigation_update()
    {
        $id = IFilter::act(IReq::get('id','post'),'int');
        $navigationObj = new IModel('quick_naviga');
        $navigationObj->setData(array(
            'admin_id'=>$this->admin['admin_id'],
            'naviga_name'=>IFilter::act(IReq::get('naviga_name')),
            'url'=>IFilter::act(IReq::get('url')),
        ));
        if($id)
        {
            $navigationObj->update('id='.$id);
        }
        else
        {
            $navigationObj->add();
        }
        $this->redirect('navigation');
    }
    /**
     * @brief 删除管理员快速导航到回收站
     */
    function navigation_del()
    {
        $ad_id = $this->admin['admin_id'];
        $data['ad_id'] = $ad_id;
        $this->setRenderData($data);
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');
        //生成order对象
        $tb_order = new IModel('quick_naviga');
        $tb_order->setData(array('is_del'=>1));
        if(!empty($id))
        {
            if(is_array($id) && isset($id[0]) && $id[0]!='')
            {
                $id_str = join(',',$id);
                $where = ' id in ('.$id_str.')';
            }
            else
            {
                $where = 'id = '.$id;
            }
            $tb_order->update($where);
            $this->redirect('navigation');
        }
        else
        {
            $this->redirect('navigation',false);
            Util::showMessage('请选择要删除的数据');
        }
    }
    //管理员快速导航_回收站
    function navigation_recycle()
    {
        $data = array();
        $ad_id = $this->admin['admin_id'];
        $data['ad_id'] = $ad_id;
        $this->setRenderData($data);
        $this->redirect('navigation_recycle');
    }
    //彻底删除快速导航
    function navigation_recycle_del()
    {
        $ad_id = $this->admin['admin_id'];
        $data['ad_id'] = $ad_id;
        $this->setRenderData($data);
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');
        //生成order对象
        $tb_order = new IModel('quick_naviga');
        if(!empty($id))
        {
            if(is_array($id) && isset($id[0]) && $id[0]!='')
            {
                $id_str = join(',',$id);
                $where = ' id in ('.$id_str.')';
            }
            else
            {
                $where = 'id = '.$id;
            }
            $tb_order->del($where);
            $this->redirect('navigation_recycle');
        }
        else
        {
            $this->redirect('navigation_recycle',false);
            Util::showMessage('请选择要删除的数据');
        }
    }
    //恢复快速导航
    function navigation_recycle_restore()
    {
        $ad_id = $this->admin['admin_id'];
        $data['ad_id'] = $ad_id;
        $this->setRenderData($data);
        //post数据
        $id = IFilter::act(IReq::get('id'),'int');
        //生成order对象
        $tb_order = new IModel('quick_naviga');
        $tb_order->setData(array('is_del'=>0));
        if(!empty($id))
        {
            if(is_array($id) && isset($id[0]) && $id[0]!='')
            {
                $id_str = join(',',$id);
                $where = ' id in ('.$id_str.')';
            }
            else
            {
                $where = 'id = '.$id;
            }
            $tb_order->update($where);
            $this->redirect('navigation_recycle');
        }
        else
        {
            $this->redirect('navigation_recycle',false);
            Util::showMessage('请选择要还原的数据');
        }
    }
    /**
     * 添加物流公司
     * */
    public function freight_edit()
    {
        $id   = IFilter::act(IReq::get('id'),'int');
        $data = array();
        if($id)
        {
            $tb_freight = new IModel('freight_company');
            $data = $tb_freight->getObj('id='.$id);
        }

        $this->data = $data;
        $this->redirect('freight_edit');
    }
    /**
     * 保存添加或修改的物流公司
     * */
    public function freight_update()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        $freight_type = IReq::get('freight_type');
        $freight_name = IReq::get('freight_name');
        $url = IReq::get('url');
        $sort = IReq::get('sort');

        $tb_freight = new IModel('freight_company');
        $tb_freight->setData(array(
            'freight_type' => $freight_type,
            'freight_name' => $freight_name,
            'url'		   => $url,
            'sort'		   => $sort
        ));

        if($id)
        {
            $tb_freight->update('id='.$id);
        }
        else
        {
            $tb_freight->add();
        }
        $this->redirect('freight_list');
    }
    /**
     * 逻辑删除物流公司
     * */
    function freight_del()
    {
        $id = IReq::get('id');
        if(!empty($id))
        {
            $obj = new IModel('freight_company');
            $obj->setData(array('is_del'=>1));
            if(is_array($id) && isset($id[0]) && $id[0]!='')
            {
                $id_str = join(',',$id);
                $where = ' id in ('.$id_str.')';
            }
            else
            {
                $where = 'id = '.$id;
            }
            $obj->update($where);
            $this->redirect('freight_list');
        }
        else
        {
            $this->redirect('freight_list',false);
            Util::showMessage('请选择要删除的物流公司');
        }
    }
    /**
     * 物流公司回收站还原
     * */
    public function freight_recycle_restore()
    {
        $id = IReq::get('id');
        if(!empty($id))
        {
            $obj = new IModel('freight_company');
            $obj->setData(array('is_del'=>0));
            if(is_array($id) && isset($id[0]) && $id[0]!='')
            {
                $id_str = join(',',$id);
                $where = ' id in ('.$id_str.')';
            }
            else
            {
                $where = 'id = '.$id;
            }
            $obj->update($where);
            $this->redirect('freight_recycle');
        }
        else
        {
            $this->redirect('freight_recycle',false);
            Util::showMessage('请选择要还原的物流公司');
        }
    }
    /**
     * 物流公司回收站彻底删除
     * */
    public function freight_recycle_del()
    {
        $id = IReq::get('id');
        if(!empty($id))
        {
            $obj = new IModel('freight_company');
            $obj->setData(array('is_del'=>0));
            if(is_array($id) && isset($id[0]) && $id[0]!='')
            {
                $id_str = join(',',$id);
                $where = ' id in ('.$id_str.')';
            }
            else
            {
                $where = 'id = '.$id;
            }
            $obj->del($where);
            $this->redirect('freight_recycle');
        }
        else
        {
            $this->redirect('freight_recycle',false);
            Util::showMessage('请选择要删除的物流公司');
        }
    }
    //修改oauth单页
    public function oauth_edit()
    {
        $id = IFilter::act(IReq::get('id'));
        if($id == 0)
        {
            $this->redirect('oauth_list',false);
            Util::showMessage('请选择要修改的登录平台');exit;
        }

        $oauthDBObj = new IModel('oauth');
        $oauthRow = $oauthDBObj->getObj('id = '.$id);
        if(empty($oauthRow))
        {
            $this->redirect('oauth_list',false);
            Util::showMessage('请选择要修改的登录平台');exit;
        }

        //获取字段数据
        $oauthObj           = new Oauth($id);
        $oauthRow['fields'] = $oauthObj->getFields();

        $this->oauthRow = $oauthRow;
        $this->redirect('oauth_edit',false);
    }

    //修改oauth动作
    public function oauth_edit_act()
    {
        $id = IFilter::act(IReq::get('id'));
        if($id == 0)
        {
            $this->redirect('oauth_list',false);
            Util::showMessage('请选择要修改的登录平台');exit;
        }

        $oauthDBObj = new IModel('oauth');
        $oauthRow = $oauthDBObj->getObj('id = '.$id);
        if(empty($oauthRow))
        {
            $this->redirect('oauth_list',false);
            Util::showMessage('请选择要修改的登录平台');exit;
        }

        $dataArray = array(
            'name'        => IFilter::act(IReq::get('name')),
            'is_close'    => IFilter::act(IReq::get('is_close')),
            'description' => IFilter::act(IReq::get('description')),
            'config'      => array(),
        );

        //获取字段数据
        $oauthObj    = new Oauth($id);
        $oauthFields = $oauthObj->getFields();

        if(!empty($oauthFields))
        {
            $parmsArray = array_keys($oauthFields);
            foreach($parmsArray as $val)
            {
                $dataArray['config'][$val] = IFilter::act(IReq::get($val));
            }
        }

        $dataArray['config'] = serialize($dataArray['config']);
        $oauthDBObj->setData($dataArray);
        $oauthDBObj->update('id = '.$id);
        $this->redirect('oauth_list');
    }

    /**
     * @brief 地域更新
     */
    public function area_update()
    {
        $area_id   = IFilter::act(IReq::get('area_id'));
        $area_name = IFilter::act(IReq::get('area_name'));
        $area_sort = IFilter::act(IReq::get('area_sort'));
        $parent_id = IFilter::act(IReq::get('parent_id'));

        $areasDB = new IModel('areas');

        //添加
        if($parent_id !== '')
        {
            $addData = array('parent_id' => $parent_id,'area_name' => $area_name,'sort' => 99);
            $areasDB->setData($addData);
            $area_id = $areasDB->add();
            $addData['area_id'] = $area_id;
            die(JSON::encode(array('isSuccess' => true,'data' => $addData)));
        }
        //修改
        else
        {
            $updateData = array();
            if($area_name)
            {
                $updateData['area_name'] = $area_name;
            }

            if($area_sort)
            {
                $updateData['sort'] = $area_sort;
            }
            $areasDB->setData($updateData);
            $areasDB->update('area_id = '.$area_id);
        }
    }

    /**
     * @brief 地域删除
     */
    public function area_del()
    {
        $area_id = IFilter::act(IReq::get('id'),'int');
        $areasDB = new IModel('areas');
        $areasDB->del('area_id = '.$area_id);
    }


    /**
     * 自提点添加和修改
     */
    public function takeself_update()
    {
        $id       = IFilter::act(IReq::get('id'),'int');
        $name     = IFilter::act(IReq::get('name'));
        $sort     = IFilter::act(IReq::get('sort'),'int');
        $province = IFilter::act(IReq::get('province'),'int');
        $city     = IFilter::act(IReq::get('city'),'int');
        $area     = IFilter::act(IReq::get('area'),'int');
        $address  = IFilter::act(IReq::get('address'));
        $phone    = IFilter::act(IReq::get('phone'));
        $mobile   = IFilter::act(IReq::get('mobile'));

        $takeselfDB = new IModel('takeself');
        $data = array(
            'name'         => $name,
            'sort'         => $sort,
            'province'     => $province,
            'city'         => $city,
            'area'         => $area,
            'address'      => $address,
            'phone'        => $phone,
            'mobile'       => $mobile,
        );
        $takeselfDB->setData($data);
        if($id)
        {
            $takeselfDB->update('id='.$id);
        }
        else
        {
            $takeselfDB->add();
        }

        $this->redirect("takeself_list");
    }


    /**
     * 自提点添加和修改视图
     */
    public function takeself_edit()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $takeselfDB        = new IModel('takeself');
            $this->takeselfRow = $takeselfDB->getObj('id = '.$id );
        }
        $this->redirect("takeself_edit");
    }

    //删除自提点
    public function takeself_operate()
    {
        $id = IFilter::act(IReq::get('id'));
        if(is_array($id))
        {
            $id = join(',',$id);
        }

        if($id)
        {
            $takeself =  new IModel('takeself');
            $takeself->del('id in('.$id.')');
            $this->redirect('takeself_list');
        }
        else
        {
            $this->redirect('takeself_list',false);
            Util::showMessage('请选择要操作的选项');
        }
    }

    /**
     *修改管理员密码
     */
    function admin_repwd_act()
    {
        //提取密码 [ 密码设置 ]
        $password   = IReq::get('password','post');
        $repassword = IReq::get('repassword','post');

        if($password && $password === $repassword)
        {
            $passwordMd5 = md5($password);
            $adminObj = new IModel('admin');
            $adminObj->setData(array('password' => $passwordMd5));
            $adminObj->update('id = '.$this->admin['admin_id']);

            //同步更新safe
            ISafe::set('admin_pwd',$passwordMd5);

            $this->redirect('default');
        }
        else
        {
            $message = '密码不能为空,并且二次输入的必须一致';
            $this->redirect('admin_repwd',false);
            Util::showMessage($message);
        }
    }



    // 短信发送测试
    function test_sendhsms()
    {
        $siteConfig = new Config('site_config');
        if( !$siteConfig->sms_username || !$siteConfig->sms_pwd )
        {
            die( JSON::encode(array('isError' => true,'message' => '请先<保存>短信配置')) );
        }
        $mobile = IFilter::act(IReq::get('mobile'));
        if($mobile === null || !IValidate::mobi($mobile))
        {
            die( JSON::encode(array('isError' => true,'message' => '请输入正确的手机号码')) );
        }
        $mobile_code = rand(10000,99999);
        $content     = smsTemplate::checkCode(array('{mobile_code}' => $mobile_code));
        $send_result = Hsms::send($mobile,$content);
        if($send_result == 'success')
        {
            $result = array('isError' => false,'message' => '恭喜你！测试通过');
        }
        else
        {
            $result = array('isError' => true,'message' => $send_result);
        }
        echo JSON::encode($result);
    }

    //支付列表
    function payment_list()
    {
        //重置货到付款预留
        $paymentDB = new IModel('payment');
        $paymentDB->setData(array('id' => 0));
        $paymentDB->update('class_name = "freight_collect"');
        $this->redirect('payment_list');
    }

    //dyg_jzw 20160216 查询管易系统上是否已发货
    /*public function guanyi_order_check()
    {
        //dyg_jzw 20160216 订单查询状态是否已发货
        //获取已添加到管易，而且未发货的订单
        $orderModel = new IModel('order');
        $gy_ready_orders = $orderModel->query('sended_guanyi = 1 AND distribution_status = 0');
        foreach ($gy_ready_orders as $orderRow)
        {
            $order_no = $orderRow['order_no'];

            //向管易查询是否已发货
            $gy_result = Guanyi::getOrder($order_no);

            if ($gy_result['success'])
            {
                $gy_order = $gy_result['orders'][0]; //订单信息

                if ($gy_order['delivery_state']) //已发货
                {

                    $gy_deliverys = $gy_order['deliverys'][0]; //发货信息

                    //处理物流公司选择
                    switch ($gy_deliverys['express_code'])
                    {
                        case 'FEDEX':
                            $freight_id = 17;
                            break;
                        case 'YTO':
                            $freight_id = 4;
                            break;
                        case 'STO':
                            $freight_id = 2;
                            break;
                        case 'SF':
                            $freight_id = 5;
                            break;
                        case 'DUMMY':
                            $freight_id = 0;
                            break;
                        case 'EMS':
                            $freight_id = 1;
                            break;
                    }

                    //****** 本商城标记发货 *****
                    //获取此订单所有关联商品
                    $orderGoodsDB = new IModel('order_goods');
                    $orderGoodsList = $orderGoodsDB->query('order_id = '.$orderRow['id']);
                    $order_goods_relation = array();
                    foreach($orderGoodsList as $key => $val)
                    {
                        $order_goods_relation[] = $val['id'];
                    }

                    //检查此订单是否存在未处理的退款申请
                    $refundDB = new IModel('refundment_doc');
                    $refundRow= $refundDB->getObj('order_no = "'.$order_no.'" and pay_status = 0 and if_del = 0');

                    //有退款处理中的订单
                    if($refundRow)
                    {
                        //发送邮件提醒管理员
                        $smtp  = new SendMail();
                        $error = $smtp->getError();

                        if($error)
                        {
                            $return = array(
                                'isError' => true,
                                'message' => $error,
                            );
                            echo JSON::encode($return);
                            exit;
                        }

                        $siteConfigObj = new Config("site_config");
                        $site_config   = $siteConfigObj->getInfo();
                        $email       = isset($site_config['email']) ? $site_config['email'] : 'jiangzhaowei@dyg.cn';
                        $smtp->send($email, '【紧急】东阳光商城发送了一单退款中的订单！', '订单号：'.$order_no.'，已点击发货，请及时拦截！');
                    }

                    //添加发货单
                     $paramArray = array(
                         'order_id'      => $orderRow['id'],
                         'user_id'       => $orderRow['user_id'],
                         'name'          => $orderRow['accept_name'],
                         'postcode'      => $orderRow['postcode'],
                         'telphone'      => $orderRow['telphone'],
                         'province'      => $orderRow['province'],
                         'city'          => $orderRow['city'],
                         'area'          => $orderRow['area'],
                         'address'       => $orderRow['address'],
                         'mobile'        => $orderRow['mobile'],
                         'freight'       => $orderRow['real_freight'],
                         'delivery_code' => $gy_deliverys['mail_no'],
                         'delivery_type' => $orderRow['distribution'],
                         'note'          => $gy_order['seller_memo'].' '.$gy_order['seller_memo_late'],
                         'time'          => ITime::getDateTime(),
                         'freight_id'    => $freight_id,
                     );

                     //获得delivery_doc表的对象
                     $tb_delivery_doc = new IModel('delivery_doc');
                     $tb_delivery_doc->setData($paramArray);
                     $deliveryId = $tb_delivery_doc->add();

                    //如果支付方式为货到付款，则减少库存
                    if($orderRow['pay_type'] == 0)
                    {
                         //减少库存量
                         self::updateStore($order_goods_relation,'reduce');
                    }

                    //更新发货状态
                     $sendStatus = 1;//全部发货
                     foreach($order_goods_relation as $key => $val)
                     {
                         //标记发货
                         $orderGoodsDB->setData(array(
                             "is_send"     => 1,
                             "delivery_id" => $deliveryId,
                         ));
                         $orderGoodsDB->update(" id = {$val} ");
                     }

                     //更新订单发货状态
                     $orderModel->setData(array
                     (
                         'distribution_status' => $sendStatus,
                         'send_time'           => ITime::getDateTime(),
                     ));
                     $orderModel->update('id='.$orderRow['id']);

                     //发货员名字
                     $sendorName = 'dyg_cn';
                     $sendorSort = '管理员';

                     //生成订单日志
                    $tb_order_log = new IModel('order_log');
                    $tb_order_log->setData(array(
                        'order_id' => $orderRow['id'],
                        'user'     => $sendorName,
                        'action'   => '发货',
                        'result'   => '成功',
                        'note'     => '订单【'.$order_no.'】由【'.$sendorSort.'】'.$sendorName.'发货',
                        'addtime'  => date('Y-m-d H:i:s')
                    ));
                    $sendResult = $tb_order_log->add();

                    //获取货运公司
                    $freightDB  = new IModel('freight_company');
                    $freightRow = $freightDB->getObj('id = '.$paramArray['freight_id']);

                    //dyg_jzw 20150811 添加短信发送设置
                    $config = new Config('site_config');
                    $sms_send_config = explode(';', $config->sms_send_config);

                    if($sms_send_config && in_array('sms_order_send', $sms_send_config))
                    {
                        //发送短信
                        $replaceData = array(
                            '{user_name}'        => $paramArray['name'],
                            '{order_no}'         => $order_no,
                            '{sendor}'           => '['.$sendorSort.']'.$sendorName,
                            '{delivery_company}' => $paramArray['delivery_code']?$freightRow['freight_name']:'自提', //dyg_jzw 修订短信提醒
                            '{delivery_no}'      => $paramArray['delivery_code'],
                        );
                        $mobileMsg = smsTemplate::sendGoods($replaceData);
                        Hsms::send($paramArray['mobile'],$mobileMsg,0);
                    }

                    //同步发货接口，如支付宝担保交易等
                    if($sendResult && $sendStatus == 1)
                    {
                        sendgoods::run($orderRow['id']);
                    }
                }
            }
        }
    }*/

    //dyg_jzw 20160216 手动添加到管易的单
    public function guanyi_addorder()
    {
        //获取订单号
        $order_no = IFilter::act(IReq::get('orderno'));
        $orderObj  = new IModel('order');

        //根据日期区间
        $start = IFilter::act(IReq::get('start'));
        $end   = IFilter::act(IReq::get('end'));
        $_date = statistics::dateParse($start,$end);//处理起始和终止时间
        $startDate = $_date[0]; //开始时间
        $endDate = $_date[1]; //结束时间

        $this->msg = '';//提示信息

        if ($start && $end)
        {
            $this->startDate = $startDate;
            $this->endDate = $endDate;
            $orderArray = $orderObj->query("create_time > '".$startDate."' AND create_time < '".$endDate."' AND sended_guanyi = 0");
        }
        elseif ($order_no)
        {
            $orderRow  = $orderObj->getObj('order_no = "'.$order_no.'"');
            $orderArray = array();
            $orderArray[] = $orderRow;
        }

        if (isset($orderArray))
        {
            foreach ($orderArray as $_order)
            {
                //已支付 未发货的订单
                if ($_order['status'] == 2 && $_order['distribution_status'] == 0 && $_order['sended_guanyi'] != 1)
                {
                    //获取用户信息
                    if ($_order['user_id']) //会员下单
                    {

                        $userObj  = new IModel('user');
                        $userRow  = $userObj->getObj('id = '.$_order['user_id'],'username');
                    }
                    else //游客下单
                    {
                        $userRow = array('username' => 'guest');
                    }

                    //获取订单商品信息
                    if($_order['pay_type'] != 0)
                    {
                        $orderGoodsDB = new IModel('order_goods');
                        $orderGoodsList = $orderGoodsDB->query('order_id = '.$_order['id']);
                    }

                    //print_r($_order);

                    //添加到管易上
                    $isGuanyiSended = Guanyi::addOrder($_order, $userRow, $orderGoodsList);
                    if ($isGuanyiSended['success'])
                    {
                        $dataArray = array(
                            'sended_guanyi'	=> 1,
                        );

                        $orderObj->setData($dataArray);
                        $orderObj->update('order_no = "'.$_order['order_no'].'"');

                        $this->msg .= '<br>订单['.$_order['order_no'].']添加成功，管易单号'.$isGuanyiSended['code'];
                    }
                    else
                    {
                        $this->msg = '添加失败，错误代码: '.json_encode($isGuanyiSended);
                    }
                }
            }
        }

        $this->redirect("guanyi_addorder");
    }

    public function cancel_e_order()
    {
        //获取订单号
        $order_no = IFilter::act(IReq::get('orderno'));

        //发送请求给e码头
        if ($order_no)
        {
            $this->msg = '';
            $cbeInstance = new Cbe(true);
            $this->msg = '<p>e码头返回: '.$cbeInstance->e_cancel_order($order_no)."</p>";

            $result = $cbeInstance->yi_refund($order_no);
            $this->msg .= '<p>易极付返回: '.$result['resultMessage']."</p>";
        }


        $this->redirect("cancel_e_order");
    }

    /**
     * 取消兴业银行直付通订单
     */
    public function cancel_direct_bill_order()
    {
        $order_no = IFilter::act(IReq::get('order_no'));//获取订单号
        $rand_str = IFilter::act(IReq::get('rand_str'));//获取订单号
        $method = IFilter::act(IReq::get('method'));//判断是否是ajax提交过来
        $newArr = array();
        if(isset($order_no) && $order_no)
        {
            $orderDB  = new IModel('order');
            $return_data = $orderDB->query(" status = 5 AND order_no like '%{$order_no}%' ","order_no,trade_no");
            if(is_array($return_data) && $return_data)
            {
                foreach ($return_data  as $k=>$v)
                {
                    $trade_no= $v['trade_no'];
                    $data_arr = array();
                    //找出订单号，随机尾号，订单时间，下单金额
                    if($trade_no && substr_count($trade_no,"|") == 1)
                    {
                        $trade_info = explode('|',$trade_no);
                        $data_arr['rand_str'] = $trade_info[0];
                        $data_arr['order_no'] = $v['order_no'];
                        $newArr[$k] = $data_arr;
                    }
                }
            }
        }
        if(isset($method) && $method=='ajax')
        {
            $order_no = $order_no.'_'.$rand_str;//订单号
            $paymentInstance = Payment::createPaymentInstance(22);//兴业银行直付通
            //先查询订单支付状态再退款
            $refundQueryData = $paymentInstance->getRefundQueryData($order_no);
            if($refundQueryData)
            {
                //根据订单号、订单日期，查询网关支付订单支付状态
                $returnData =   $paymentInstance->sendPost($paymentInstance->getSubmitUrl(),$refundQueryData);
                $returnDataJson = json_decode($returnData);
                //【失败结果】
                if(isset($returnDataJson->errcode) && $returnDataJson->errcode =='EPAY_20310')
                {
                    echo $returnData;exit;
                }
                //【成功结果】
                else
                {
                    $order_amount = $returnDataJson->order_amount;
                    $order_date = substr($order_no,0,8);
                    $trans_status  = $returnDataJson->trans_status;
                    //0-待发起；1-成功；2-失败；3-支付中；4-已撤销；5-已退款
                    $status['errcode'] = 1;
                    switch ($trans_status)
                    {
                        case 0:
                            $status['errmsg'] = "订单待发起，不能退款";
                            break;
                        case 1:
                            $getRefundData = $paymentInstance->getRefundData($order_no,$order_date,$order_amount);
                            if($getRefundData)
                            {
                                $status['errcode'] = 0;
                                $paymentInstance->sendPost($paymentInstance->getSubmitUrl(),$getRefundData);
                            }
                            break;
                        case 2:
                            $status['errmsg'] = "订单失败，不能退款";
                            break;
                        case 3:
                            $status['errmsg'] = "订单支付中，不能退款";
                            break;
                        case 4:
                            $status['errmsg'] = "订单已撤销，不能退款";
                            break;
                        case 5:
                            $status['errmsg'] = "订单已退款，不能退款";
                            break;
                    }
                    echo  json_encode($status);exit;
                }
            }
        }
        $this->orderList = $newArr;//输出到模板
        $this->redirect("cancel_direct_bill_order");
    }
}
