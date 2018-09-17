<?php
/**
 * @brief 用户中心模块
 * @class Ucenter
 * @note  前台
 */
class Ucenter extends IController implements userAuthorization
{
    public $layout = 'ucenter';

    public function init()
    {

    }
    public function index()
    {
        //获取用户基本信息
        $user = Api::run('getMemberInfo',$this->user['user_id']);

        //获取用户各项统计数据
        $statistics = Api::run('getMemberTongJi',$this->user['user_id']);

        //获取用户站内信条数
        $msgObj = new Mess($this->user['user_id']);
        $msgNum = $msgObj->needReadNum();

        //获取用户代金券
        $propIds = trim($user['prop'],',');
        $propIds = $propIds ? $propIds : 0;
        $propData= Api::run('getPropTongJi',$propIds);

        $this->setRenderData(array(
            "user"       => $user,
            "statistics" => $statistics,
            "msgNum"     => $msgNum,
            "propData"   => $propData,
        ));

        $this->initPayment();
        $this->redirect('index');
    }

    //[用户头像]上传
    function user_ico_upload()
    {
        $result = array(
            'isError' => true,
        );

        if(isset($_FILES['attach']['name']) && $_FILES['attach']['name'] != '')
        {
            $photoObj = new PhotoUpload();
            $photo    = $photoObj->run();

            if(isset($photo['attach']['img']) && $photo['attach']['img'])
            {
                $user_id   = $this->user['user_id'];
                $user_obj  = new IModel('user');
                $dataArray = array(
                    'head_ico' => $photo['attach']['img'],
                );
                $user_obj->setData($dataArray);
                $where  = 'id = '.$user_id;
                $isSuss = $user_obj->update($where);

                if($isSuss !== false)
                {
                    $result['isError'] = false;
                    $result['data'] = IUrl::creatUrl().$photo['attach']['img'];
                    ISafe::set('head_ico',$dataArray['head_ico']);
                }
                else
                {
                    $result['message'] = '上传失败';
                }
            }
            else
            {
                $result['message'] = '上传失败';
            }
        }
        else
        {
            $result['message'] = '请选择图片';
        }
        echo '<script type="text/javascript">parent.callback_user_ico('.JSON::encode($result).');</script>';
    }

    /**
     * @brief 我的订单列表
     */
    public function order()
    {
        $this->initPayment();
        $this->redirect('order');

    }
    /**
     * @brief 初始化支付方式
     */
    private function initPayment()
    {
        $payment = new IQuery('payment');
        $payment->fields = 'id,name,type';
        $payments = $payment->find();
        $items = array();
        foreach($payments as $pay)
        {
            $items[$pay['id']]['name'] = $pay['name'];
            $items[$pay['id']]['type'] = $pay['type'];
        }
        $this->payments = $items;
    }
    /**
     * @brief 订单详情
     * @return String
     */
    public function order_detail()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        $orderObj = new order_class();
        $this->order_info = $orderObj->getOrderShow($id,$this->user['user_id']);

        if(!$this->order_info)
        {
            IError::show(403,'订单信息不存在');
        }
        $orderStatus = Order_Class::getOrderStatus($this->order_info);
        $this->setRenderData(array('orderStatus' => $orderStatus));
        $this->redirect('order_detail',false);
    }

    //操作订单状态
    public function order_status()
    {
        $op    = IFilter::act(IReq::get('op'));
        $id    = IFilter::act( IReq::get('order_id'),'int' );
        $model = new IModel('order');

        switch($op)
        {
            case "cancel":
            {
                $model->setData(array('status' => 3));
                if($model->update("id = ".$id." and distribution_status = 0 and status = 1 and user_id = ".$this->user['user_id']))
                {
                    order_class::resetOrderProp($id);
                }
            }
                break;

            case "confirm":
            {
                $model->setData(array('status' => 5,'completion_time' => ITime::getDateTime()));
                if($model->update("id = ".$id." and distribution_status = 1 and user_id = ".$this->user['user_id']))
                {
                    $orderRow = $model->getObj('id = '.$id);

                    //确认收货后进行支付
                    Order_Class::updateOrderStatus($orderRow['order_no']);

                    //增加用户评论商品机会
                    Order_Class::addGoodsCommentChange($id);

                    //确认收货以后直接跳转到评论页面
                    $this->redirect('evaluation');
                }
            }
                break;
        }
        $this->redirect("order_detail/id/$id");
    }
    /**
     * @brief 我的地址
     */
    public function address()
    {
        //取得自己的地址
        $query = new IQuery('address');
        $query->where = 'user_id = '.$this->user['user_id'];
        $address = $query->find();
        $areas   = array();

        if($address)
        {
            foreach($address as $ad)
            {
                $temp = area::name($ad['province'],$ad['city'],$ad['area']);
                if(isset($temp[$ad['province']]) && isset($temp[$ad['city']]) && isset($temp[$ad['area']]))
                {
                    $areas[$ad['province']] = $temp[$ad['province']];
                    $areas[$ad['city']]     = $temp[$ad['city']];
                    $areas[$ad['area']]     = $temp[$ad['area']];
                }
            }
        }

        $this->areas = $areas;
        $this->address = $address;
        $this->redirect('address');
    }
    /**
     * @brief 收货地址管理
     */
    public function address_edit()
    {
        $id          = IFilter::act(IReq::get('id'),'int');
        $accept_name = IFilter::act(IReq::get('accept_name'),'name');
        $accept_id = IFilter::act(IReq::get('accept_id')); //dyg_jzw 20160313 跨境电商专用

        $province    = IFilter::act(IReq::get('province'),'int');
        $city        = IFilter::act(IReq::get('city'),'int');
        $area        = IFilter::act(IReq::get('area'),'int');
        $address = IFilter::act(IReq::get('address'));
        $zip         = IFilter::act(IReq::get('zip'),'zip');
        $telphone    = IFilter::act(IReq::get('telphone'),'phone');
        $mobile      = IFilter::act(IReq::get('mobile'),'mobile');
        $default = IReq::get('is_default')!= 1 ? 0 : 1;
        $user_id = $this->user['user_id'];

        $model = new IModel('address');
        $data = array(
            'user_id'=>$user_id,
            'accept_name'=>$accept_name,
            'accept_id'=>$accept_id, //dyg_jzw 20160313 跨境电商专用
            'province'=>$province,
            'city'=>$city,
            'area'=>$area,
            'address'=>$address,
            'zip'=>$zip,
            'telphone'=>$telphone,
            'mobile'=>$mobile,
            'is_default'=>$default
        );

        //dyg_jzw 20160704 增加必要信息的校验
        $checkArray = $data;
        unset($checkArray['telphone'], $checkArray['zip'], $checkArray['user_id'], $checkArray['accept_id'], $checkArray['is_default']); //dyg_jzw 20160613 身份照非必填
        foreach($checkArray as $key => $val)
        {
            if(!$val)
            {
                IError::show(403,"地址信息不全，请仔细填写收货地址");
            }
        }

        //如果设置为首选地址则把其余的都取消首选
        if($default==1)
        {
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$this->user['user_id']);
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
        $this->redirect('address');
    }
    /**
     * @brief 收货地址删除处理
     */
    public function address_del()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $model = new IModel('address');
        $model->del('id = '.$id.' and user_id = '.$this->user['user_id']);
        $this->redirect('address');
    }
    /**
     * @brief 设置默认的收货地址
     */
    public function address_default()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $default = IFilter::act(IReq::get('is_default'));
        $model = new IModel('address');
        if($default == 1)
        {
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$this->user['user_id']);
        }
        $model->setData(array('is_default' => $default));
        $model->update("id = ".$id." and user_id = ".$this->user['user_id']);
        $this->redirect('address');
    }
    /**
     * @brief 退款申请页面
     */
    public function refunds_update()
    {
        $order_goods_id = IFilter::act( IReq::get('order_goods_id'),'int' );
        $order_id       = IFilter::act( IReq::get('order_id'),'int' );
        $user_id        = $this->user['user_id'];
        $content        = IFilter::act(IReq::get('content'),'text');
        $message        = '';

        if(!$content || !$order_goods_id)
        {
            $message = "请填写退款理由和选择要退款的商品";
            $this->redirect('refunds',false);
            Util::showMessage($message);
        }

        $orderDB = new IModel('order');
        $orderRow = $orderDB->getObj("id = ".$order_id." and user_id = ".$user_id);

        $refundResult = Order_Class::isRefundmentApply($orderRow,$order_goods_id);


        //判断退款申请是否有效
        if($refundResult === true)
        {
            //退款单数据
            $updateData = array(
                'order_no' => $orderRow['order_no'],
                'order_id' => $order_id,
                'user_id'  => $user_id,
                'time'     => ITime::getDateTime(),
                'content'  => $content,
                'seller_id'      => $orderRow['seller_id'],
                'order_goods_id' => join(",",$order_goods_id),
            );

            //dyg_jzw 20160218 退款标记到管易
            foreach ($order_goods_id as $_order_goods_id)
            {
                $return = Guanyi::updateOrderRefund($orderRow['order_no'], $_order_goods_id, 1);
            }
            //退款申请写入数据库
            $refundsDB = new IModel('refundment_doc');
            $refundsDB->setData($updateData);
            $refundsDB->add();
            //dyg_jzw 20160901 添加邮件提醒
            $smtp  = new SendMail();
            $siteConfigObj = new Config("site_config");
            $site_config   = $siteConfigObj->getInfo();
            $email       = isset($site_config['email']) ? $site_config['email'] : 'jiangzhaowei@dyg.cn';
            $smtp->send($email, '【申请退款】'.$orderRow['order_no'].'申请退款，注意是否配货发出！', ($orderRow['is_cbe']?'跨境订单！':'') . '订单号：'.$orderRow['order_no'].'，已申请退款<br>收货人：'.$orderRow['accept_name'].'<br>请确认是否已配货发出并及时处理');
            $this->redirect('refunds');
        }
        else
        {
            $message = $refundResult;
            $this->redirect('refunds',false);
            Util::showMessage($message);
        }
    }
    /**
     * @brief 退款申请删除
     */
    public function refunds_del()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $model = new IModel("refundment_doc");

        //dyg_jzw 20160218 获取退款信息
        $refundRow = $model->getObj("id = ".$id." and user_id = ".$this->user['user_id']);
        if($refundRow)
        {
            $return = Guanyi::updateOrderRefund($refundRow['order_no'], $refundRow['goods_id'], 0);

            if ($return['success'])
            {
                $model->del("id = ".$id." and user_id = ".$this->user['user_id']);
                $this->redirect('refunds');
            }
        }
        else
        {
            $this->redirect('refunds',false);
            Util::showMessage("退款信息不存在");
        }
    }
    /**
     * @brief 查看退款申请详情
     */
    public function refunds_detail()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $refundDB = new IModel("refundment_doc");
        $refundRow = $refundDB->getObj("id = ".$id." and user_id = ".$this->user['user_id']);
        if($refundRow)
        {
            //获取商品信息
            $orderGoodsDB = new IModel('order_goods');
            $orderGoodsList = $orderGoodsDB->query("id in (".$refundRow['order_goods_id'].")");
            if($orderGoodsList)
            {
                $refundRow['goods'] = $orderGoodsList;
                $this->data = $refundRow;
            }
            else
            {
                $this->redirect('refunds',false);
                Util::showMessage("没有找到要退款的商品");
            }
            $this->redirect('refunds_detail');
        }
        else
        {
            $this->redirect('refunds',false);
            Util::showMessage("退款信息不存在");
        }
    }
    /**
     * @brief 查看退款申请详情
     */
    public function refunds_edit()
    {
        $order_id = IFilter::act(IReq::get('order_id'),'int');
        if($order_id)
        {
            $orderDB  = new IModel('order');
            $orderRow = $orderDB->getObj('id = '.$order_id.' and user_id = '.$this->user['user_id']);
            if($orderRow)
            {
                $this->orderRow = $orderRow;
                $this->redirect('refunds_edit');
                return;
            }
        }
        $this->redirect('refunds');
    }

    /**
     * @brief 建议中心
     */
    public function complain_edit()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $title = IFilter::act(IReq::get('title'),'string');
        $content = IFilter::act(IReq::get('content'),'string' );
        $user_id = $this->user['user_id'];
        $model = new IModel('suggestion');
        $model->setData(array('user_id'=>$user_id,'title'=>$title,'content'=>$content,'time'=>ITime::getDateTime()));
        if($id =='')
        {
            $model->add();
        }
        else
        {
            $model->update('id = '.$id.' and user_id = '.$this->user['user_id']);
        }
        $this->redirect('complain');
    }
    //站内消息
    public function message()
    {
        $msgObj = new Mess($this->user['user_id']);
        $msgIds = $msgObj->getAllMsgIds();
        $msgIds = $msgIds ? $msgIds : 0;
        $this->setRenderData(array('msgIds' => $msgIds,'msgObj' => $msgObj));
        $this->redirect('message');
    }
    /**
     * @brief 删除消息
     * @param int $id 消息ID
     */
    public function message_del()
    {
        $id = IFilter::act( IReq::get('id') ,'int' );
        $msg = new Mess($this->user['user_id']);
        $msg->delMessage($id);
        $this->redirect('message');
    }
    public function message_read()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $msg = new Mess($this->user['user_id']);
        echo $msg->writeMessage($id,1);
    }

    //[修改密码]修改动作
    function password_edit()
    {
        $user_id    = $this->user['user_id'];

        $fpassword  = IReq::get('fpassword');
        $password   = IReq::get('password');
        $repassword = IReq::get('repassword');

        $userObj    = new IModel('user');
        $where      = 'id = '.$user_id;
        $userRow    = $userObj->getObj($where);

        if(!preg_match('|\w{6,32}|',$password))
        {
            $message = '密码格式不正确，请重新输入';
        }
        else if($password != $repassword)
        {
            $message  = '二次密码输入的不一致，请重新输入';
        }
        else if(md5($fpassword) != $userRow['password'])
        {
            $message  = '原始密码输入错误';
        }
        else
        {
            $passwordMd5 = md5($password);
            $dataArray = array(
                'password' => $passwordMd5,
            );

            $userObj->setData($dataArray);
            $result  = $userObj->update($where);
            if($result)
            {
                ISafe::set('user_pwd',$passwordMd5);
                $message = '密码修改成功';
            }
            else
            {
                $message = '密码修改失败';
            }
        }

        $this->redirect('password',false);
        Util::showMessage($message);
    }

    //[个人资料]展示 单页
    function info()
    {
        $user_id = $this->user['user_id'];

        $userObj       = new IModel('user');
        $where         = 'id = '.$user_id;
        $this->userRow = $userObj->getObj($where);

        $memberObj       = new IModel('member');
        $where           = 'user_id = '.$user_id;
        $this->memberRow = $memberObj->getObj($where);

        //dyg_jzw 20160128 增加会员组显示
        if ($this->memberRow['group_id'])
        {
            //获取所有会员组别信息
            $userGroupObj = new IModel('user_group');
            $where         = 'id = '.$this->memberRow['group_id'];
            $this->userGroupRow = $userGroupObj->getObj($where);
        }
        else
        {
            $this->userGroupRow = array('group_name'=>'暂无');
        }

        $this->redirect('info');
    }

    //[个人资料] 修改 [动作]
    function info_edit_act()
    {
        $email     = IFilter::act( IReq::get('email'),'string');
        $mobile    = IFilter::act( IReq::get('mobile'),'string');
        $user_id   = $this->user['user_id'];

        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;

        if($email)
        {
            $memberRow = $memberObj->getObj('user_id != '.$user_id.' and email = "'.$email.'"');
            if($memberRow)
            {
                IError::show('此邮箱已被使用，请更换其他邮箱');
            }
        }
        if($mobile)
        {
            $memberRow = $memberObj->getObj('user_id != '.$user_id.' and mobile = "'.$mobile.'"');
            if($memberRow)
            {
                IError::show('此手机号已被使用，请更换其他手机号');
            }
        }
        //地区
        $province = IFilter::act( IReq::get('province','post') ,'string');
        $city     = IFilter::act( IReq::get('city','post') ,'string' );
        $area     = IFilter::act( IReq::get('area','post') ,'string' );
        $areaArr  = array_filter(array($province,$city,$area));//去掉空值

        //dyg_jzw20150826 修正手机版生日提交问题
        $birthday = IFilter::act( IReq::get('birthday') ) ? IFilter::act( IReq::get('birthday') ) : IFilter::act( IReq::get('year'), 'int') . '-' . IFilter::act( IReq::get('month'), 'int') . '-' . IFilter::act( IReq::get('day'), 'int');

        $dataArray       = array(
            //'email'        => $email,  //dyg_jzw 20161108 email不可填写
            'true_name'    => IFilter::act( IReq::get('true_name') ,'string'),
            'sex'          => IFilter::act( IReq::get('sex'),'int' ),
            'birthday'     => date("Y-m-d", strtotime($birthday)),
            'zip'          => IFilter::act( IReq::get('zip') ,'string' ),
            'qq'           => IFilter::act( IReq::get('qq') , 'string' ),
            'contact_addr' => IFilter::act( IReq::get('contact_addr'), 'string'),
            'mobile'       => $mobile,
            'telephone'    => IFilter::act( IReq::get('telephone'),'string'),
            'area'         => $areaArr ? ",".join(",",$areaArr)."," : "",
        );

        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $this->info();
    }

    //[账户余额] 展示[单页]
    function withdraw()
    {
        $user_id   = $this->user['user_id'];

        $memberObj = new IModel('member','balance');
        $where     = 'user_id = '.$user_id;
        $this->memberRow = $memberObj->getObj($where);
        $this->redirect('withdraw');
    }

    //[账户余额] 提现动作
    function withdraw_act()
    {
        $user_id = $this->user['user_id'];
        $amount  = round(IFilter::act( IReq::get('amount','post') ,'float'), 2); //dyg_jzw 20150817 修改提现支持float类型
        $message = '';

        $dataArray = array(
            'name'   => IFilter::act( IReq::get('name','post') ,'string'),
            'note'   => IFilter::act( IReq::get('note','post'), 'string'),
            'amount' => $amount,
            'user_id'=> $user_id,
            'time'   => ITime::getDateTime(),
        );

        $mixAmount = 0;
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $memberRow = $memberObj->getObj($where,'balance');

        //dyg_jzw 20150818 修改余额显示
        $this->memberRow = array('balance' => $memberRow['balance']);

        //提现金额范围
        if($amount <= $mixAmount)
        {
            $message = '提现的金额必须大于'.$mixAmount.'元';
        }
        else if($amount > $memberRow['balance'])
        {
            $message = '提现的金额不能大于您的帐户余额';
        }
        else
        {
            //增加提现申请
            $withDrawObj = new IModel('withdraw');
            $withDrawObj->setData($dataArray);
            $is_success = $withDrawObj->add();

            //dyg_jzw 20150817 提现暂扣余额
            if ($is_success)
            {
                $userObj    = new IModel('user');
                $where      = 'id = '.$user_id;
                $userRow    = $userObj->getObj($where);

                //添加日志
                $accountLog = new AccountLog();
                $config=array(
                    'user_id'  => $user_id,
                    'event'    => 'apply_withdraw',
                    'note'     => '用户['.$userRow['username'].']申请提现，暂扣余额：'.$amount.'元',
                    'num'      => $amount,
                );
                $accountLog->write($config);

                //dyg_jzw 20150818 修改余额显示
                $this->memberRow = array('balance' => $memberRow['balance'] - $amount);
            }
        }

        if($message != '')
        {
            $this->withdrawRow = $dataArray;
            $this->redirect('withdraw',false);
            Util::showMessage($message);
        }
        else
        {
            $this->redirect('withdraw');
        }
    }

    //[账户余额] 提现详情
    function withdraw_detail()
    {
        $user_id = $this->user['user_id'];

        $id  = IFilter::act( IReq::get('id'),'int' );
        $obj = new IModel('withdraw');
        $where = 'id = '.$id.' and user_id = '.$user_id;
        $this->withdrawRow = $obj->getObj($where);
        $this->redirect('withdraw_detail');
    }

    //[提现申请] 取消
    function withdraw_del()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $user_id = $this->user['user_id'];
        if($id)
        {
            $withdrawObj = new IModel('withdraw');
            $withDrawRow = $withdrawObj->getObj('id = '.$id.' and user_id = '.$user_id);

            //标记取消提现
            $dataArray   = array('is_del' => 1);
            $where = 'id = '.$id.' and is_del = 0 and user_id = '.$user_id; //dyg_jzw 20150817 增加校验用户id, 以防出bug
            $withdrawObj->setData($dataArray);
            $is_success = $withdrawObj->update($where);

            //dyg_jzw 20150817 用户取消申请提现
            if ($is_success)
            {
                $userObj    = new IModel('user');
                $where      = 'id = '.$user_id;
                $userRow    = $userObj->getObj($where);

                //添加日志
                $accountLog = new AccountLog();
                $config=array(
                    'user_id'  => $user_id,
                    'event'    => 'cancel_apply_withdraw',
                    'note'     => '用户['.$userRow['username'].']取消提现申请，返还余额：'.$withDrawRow['amount'].'元',
                    'num'      => $withDrawRow['amount'],
                );
                $accountLog->write($config);
            }
        }
        $this->redirect('withdraw');
    }

    //[余额交易记录]
    function account_log()
    {
        $user_id   = $this->user['user_id'];

        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $this->memberRow = $memberObj->getObj($where);
        $this->redirect('account_log');
    }

    //[收藏夹]备注信息
    function edit_summary()
    {
        $user_id = $this->user['user_id'];

        $id      = IFilter::act( IReq::get('id'),'int' );
        $summary = IFilter::act( IReq::get('summary'),'string' );

        //ajax返回结果
        $result  = array(
            'isError' => true,
        );

        if(!$id)
        {
            $result['message'] = '收藏夹ID值丢失';
        }
        else if(!$summary)
        {
            $result['message'] = '请填写正确的备注信息';
        }
        else
        {
            $favoriteObj = new IModel('favorite');
            $where       = 'id = '.$id.' and user_id = '.$user_id;

            $dataArray   = array(
                'summary' => $summary,
            );

            $favoriteObj->setData($dataArray);
            $is_success = $favoriteObj->update($where);

            if($is_success === false)
            {
                $result['message'] = '更新信息错误';
            }
            else
            {
                $result['isError'] = false;
            }
        }
        echo JSON::encode($result);
    }








    //[收藏夹]删除
    function favorite_del()
    {
        $user_id = $this->user['user_id'];
        $id      = IReq::get('id');

        if(!empty($id))
        {
            $id = IFilter::act($id,'int');

            $favoriteObj = new IModel('favorite');

            if(is_array($id))
            {
                $idStr = join(',',$id);
                $where = 'user_id = '.$user_id.' and id in ('.$idStr.')';
            }
            else
            {
                $where = 'user_id = '.$user_id.' and id = '.$id;
            }

            $favoriteObj->del($where);
            $this->redirect('favorite');
        }
        else
        {
            $this->redirect('favorite',false);
            Util::showMessage('请选择要删除的数据');
        }
    }

    //dyg_jzw 20150806
    //[我的经验值] 单页展示
    function experience()
    {
        $memberObj         = new IModel('member');
        $where             = 'user_id = '.$this->user['user_id'];
        $this->memberRow   = $memberObj->getObj($where,'exp, group_id');

        //获取所有会员组别信息
        $userGroupObj = new IModel('user_group');
        $userGroupList = $userGroupObj->query();

        $user_group_arr = array();
        foreach ($userGroupList as $_group)
        {
            $user_group_arr[$_group['id']] = $_group['group_name'];
        }
        $this->user_group_arr = $user_group_arr;

        //各等级升级经验值
        $this->level_up = array(
            '13' => 0,
            '14' => 900,
            '17' => 3000,
            '18' => 6000,
            '22' => 20000,
            '1' => 30000,
            '2' => 80000,
            '4' => 380000,
            '8' => 8000000
        );

        $this->redirect('experience',false);
    }

    //[我的积分] 单页展示
    function integral()
    {
        /*获取积分增减的记录日期时间段*/
        $this->historyTime = IFilter::string( IReq::get('history_time','post') );
        $defaultMonth = 3;//默认查找最近3个月内的记录

        $lastStamp    = ITime::getTime(ITime::getNow('Y-m-d')) - (3600*24*30*$defaultMonth);
        $lastTime     = ITime::getDateTime('Y-m-d',$lastStamp);

        if($this->historyTime != null && $this->historyTime != 'default')
        {
            $historyStamp = ITime::getDateTime('Y-m-d',($lastStamp - (3600*24*30*$this->historyTime)));
            $this->c_datetime = 'datetime >= "'.$historyStamp.'" and datetime < "'.$lastTime.'"';
        }
        else
        {
            $this->c_datetime = 'datetime >= "'.$lastTime.'"';
        }

        $memberObj         = new IModel('member');
        $where             = 'user_id = '.$this->user['user_id'];
        $this->memberRow   = $memberObj->getObj($where,'point');
        $this->redirect('integral',false);
    }

    //[我的积分]积分兑换代金券 动作
    function trade_ticket()
    {
        $ticketId = IFilter::act( IReq::get('ticket_id','post'),'int' );
        $message  = '';
        if(intval($ticketId) == 0)
        {
            $message = '请选择要兑换的代金券';
        }
        else
        {
            $nowTime   = ITime::getDateTime();
            $ticketObj = new IModel('ticket');
            $ticketRow = $ticketObj->getObj('id = '.$ticketId.' and point > 0 and start_time <= "'.$nowTime.'" and end_time > "'.$nowTime.'"');
            if(empty($ticketRow))
            {
                $message = '对不起，此代金券不能兑换';
            }
            else
            {
                $memberObj = new IModel('member');
                $where     = 'user_id = '.$this->user['user_id'];
                $memberRow = $memberObj->getObj($where,'point');

                if($ticketRow['point'] > $memberRow['point'])
                {
                    $message = '对不起，您的积分不足，不能兑换此类代金券';
                }
                else
                {
                    //dyg_jzw 20161227 是否限制兑换
                    $can_change = false;
                    if ($ticketRow['got_limit'])
                    {
                        //查询用户已兑换的张数
                        $propObj = new IModel("prop");
                        $_got_sum = $propObj->getObj("type = '0' and `condition` = '{$ticketRow['id']}' and user_id = '".$this->user['user_id']."'", "count(id) as total_change");

                        if ($_got_sum['total_change'] >= $ticketRow['got_limit'])
                        {
                            $message = '对不起，您已超过此类代金券的兑换上限';
                        }
                        else
                        {
                            $can_change = true;
                        }
                    }
                    else
                    {
                        $can_change = true;
                    }

                    if ($can_change)
                    {
                        //生成红包
                        $dataArray = array(
                            'condition' => $ticketRow['id'],
                            'name'      => $ticketRow['name'],
                            'card_name' => IHash::random(16,'int'),
                            'card_pwd'  => IHash::random(8),
                            'value'     => $ticketRow['value'],
                            'start_time'=> $ticketRow['start_time'],
                            'end_time'  => $ticketRow['end_time'],
                            'is_send'   => 1,
                            'user_id'   => $this->user['user_id'], //dyg_jzw 20161227 增加用户ID标注
                        );
                        $propObj = new IModel('prop');
                        $propObj->setData($dataArray);
                        $insert_id = $propObj->add();

                        //更新用户prop字段
                        $memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$insert_id},')");
                        $memberObj->setData($memberArray);

                        $result = $memberObj->update('user_id = '.$this->user["user_id"],'prop');

                        //代金券成功
                        if($result)
                        {
                            $pointConfig = array(
                                'user_id' => $this->user['user_id'],
                                'point'   => '-'.$ticketRow['point'],
                                'log'     => '积分兑换代金券“'.$ticketRow['name'].'”，扣除 '.$ticketRow['point'].' 积分',
                            );
                            $pointObj = new Point;
                            $pointObj->update($pointConfig);
                        }
                    }

                }
            }
        }

        //展示
        if($message != '')
        {
            $this->integral();
            Util::showMessage($message);
        }
        else
        {
            $this->redirect('redpacket');
        }
    }

    /**
     * 余额付款
     * T:支付失败;
     * F:支付成功;
     */
    function payment_balance()
    {
        $urlStr  = '';
        $user_id = intval($this->user['user_id']);

        $return['attach']     = IReq::get('attach');
        $return['total_fee']  = IReq::get('total_fee');
        $return['order_no']   = IReq::get('order_no');
        $return['sign']       = IReq::get('sign');


        $paymentDB  = new IModel('payment');
        $paymentRow = $paymentDB->getObj('class_name = "balance" ');
        if(!$paymentRow)
        {
            IError::show(403,'余额支付方式不存在');
        }

        $paymentInstance = Payment::createPaymentInstance($paymentRow['id']);
        $payResult       = $paymentInstance->callback($return,$paymentRow['id'],$money,$message,$orderNo);
        if($payResult == false)
        {
            IError::show(403,$message);
        }

        $memberObj = new IModel('member');
        $memberRow = $memberObj->getObj('user_id = '.$user_id);

        if(empty($memberRow))
        {
            IError::show(403,'用户信息不存在');
        }
        if($memberRow['balance'] < $return['total_fee'])
        {
            IError::show(403,'账户余额不足');
        }

        //检查订单状态
        $orderObj = new IModel('order');

        $orderRow = $orderObj->getObj('order_no  = "'.$return['order_no'].'" and pay_status = 0 and status = 1 and user_id = '.$user_id);


        if(!$orderRow)
        {
            IError::show(403,'订单号【'.$return['order_no'].'】已经被处理过，请查看订单状态');
        }

        //扣除余额并且记录日志
        $logObj = new AccountLog();
        $config = array(
            'user_id'  => $user_id,
            'event'    => 'pay',
            'num'      => $return['total_fee'],
            'order_no' => str_replace("_",",",$return['attach']),
        );
        $is_success = $logObj->write($config);
        if(!$is_success)
        {
            $orderObj->rollback();
            IError::show(403,$logObj->error ? $logObj->error : '用户余额更新失败');
        }

        //订单批量结算缓存机制
        $moreOrder = Order_Class::getBatch($orderNo);
        if($money >= array_sum($moreOrder))
        {
            foreach($moreOrder as $key => $item)
            {
                $order_id = Order_Class::updateOrderStatus($key);
                if(!$order_id)
                {
                    $orderObj->rollback();
                    IError::show(403,'订单修改失败');
                }
            }
        }
        else
        {
            $orderObj->rollback();
            IError::show(403,'付款金额与订单金额不符合');
        }

        //支付成功结果
        $this->redirect('/site/success/message/'.urlencode("支付成功").'/?callback=/ucenter/order');

    }

    /**
     * 积分付款
     * dyg_jzw 20170220新增
     * T:支付失败;
     * F:支付成功;
     */
    function payment_pointpay()
    {
        $urlStr  = '';
        $user_id = intval($this->user['user_id']);

        $return['attach']     = IReq::get('attach');
        $return['total_fee']  = IReq::get('total_fee');
        $return['order_no']   = IReq::get('order_no');
        $return['order_id']   = IReq::get('order_id');
        $return['sign']       = IReq::get('sign');


        $paymentDB  = new IModel('payment');
        $paymentRow = $paymentDB->getObj('class_name = "pointpay"');
        if(!$paymentRow)
        {
            IError::show(403,'积分支付方式不存在');
        }

        $paymentInstance = Payment::createPaymentInstance($paymentRow['id']);
        $payResult       = $paymentInstance->callback($return,$paymentRow['id'],$money,$message,$orderNo);
        if($payResult == false)
        {
            IError::show(403,$message);
        }

        $memberObj = new IModel('member');
        $memberRow = $memberObj->getObj('user_id = '.$user_id);

        if(empty($memberRow))
        {
            IError::show(403,'用户信息不存在');
        }
        if($memberRow['point'] && $memberRow['point'] < $return['total_fee']*100) //金额:积分=1:100
        {
            IError::show(403,'账户积分不足');
        }

        //检查订单状态
        $orderObj = new IModel('order');

        $orderRow = $orderObj->getObj('order_no  = "'.$return['order_no'].'" and pay_status = 0 and status = 1 and user_id = '.$user_id);


        if(!$orderRow)
        {
            IError::show(403,'订单号【'.$return['order_no'].'】已经被处理过，请查看订单状态');
        }

        //扣除余额并且记录日志
        $logObj = new Point;
        $config = array(
            'user_id'   => $user_id,
            'point'     => '-'.($return['total_fee']*100),
            'log'       => "积分成功支付订单:".$return['order_no']."，扣除积分".($return['total_fee']*100),
        );
        $is_success = $logObj->update($config);
        if(!$is_success)
        {
            $orderObj->rollback();
            IError::show(403,$logObj->error ? $logObj->error : '用户积分更新失败');
        }

        //订单批量结算缓存机制
        $moreOrder = Order_Class::getBatch($orderNo);
        if($money >= array_sum($moreOrder))
        {
            foreach($moreOrder as $key => $item)
            {
                $order_id = Order_Class::updateOrderStatus($key);
                if(!$order_id)
                {
                    $orderObj->rollback();
                    IError::show(403,'订单修改失败');
                }
            }
        }
        else
        {
            $orderObj->rollback();
            IError::show(403,'付款金额与订单金额不符合');
        }

        //支付成功结果
        $this->redirect('/site/success/message/'.urlencode("支付成功").'/?callback=/ucenter/order');

    }

    //我的代金券
    function redpacket()
    {
        $member_info = Api::run('getMemberInfo',$this->user['user_id']);
        $propIds     = trim($member_info['prop'],',');
        $propIds     = $propIds ? $propIds : 0;
        $this->setRenderData(array('propId' => $propIds));
        $this->redirect('redpacket');
    }
    //删除、转赠 异步消息处理
    function wechat_ticket_callback()
    {

    }
    /**
     * 销售组队模块
     * 我的团队 (显示我的下级组员与他们的累计消费情况)
     * dyg_jzw 20150623
     */

    function myteam()
    {
        $username = $this->user['username'];

        //是否存在下级会员
        $query = new IQuery('user');
        $query->where = 'inviter = "'.$username.'"';
        $down_users = $query->find();

        $team_users_arr = array();

        if ($down_users)
        {
            //获取用户的所在组别和累计消费情况
            $uids = array();

            foreach ($down_users as $_users)
            {
                $team_users_arr[$_users['id']]['username'] = $_users['username'];
                $uids[] = $_users['id'];
            }

            //获取用户更多信息
            $memberObj   = new IModel('member');
            $memberList  = $memberObj->query('user_id in (' . implode(',', $uids) . ')', 'user_id, true_name, group_id, time, total_consume');

            foreach ($memberList as $_member)
            {
                $team_users_arr[$_member['user_id']]['info'] = $_member;
            }

            //获取所有会员组别信息
            $userGroupObj = new IModel('user_group');
            $userGroupList = $userGroupObj->query();

            $user_group_arr = array();
            foreach ($userGroupList as $_group)
            {
                $user_group_arr[$_group['id']] = $_group['group_name'];
            }

            $this->team_users_arr = $team_users_arr;
            $this->user_group_arr = $user_group_arr;

            //消费提成
            $this->fanli = array(
                '13' => '16~20',
                '14' => '11~15',
                '17' => '8~12',
                '18' => '6~10',
                '22' => '5~9',
                '1' => '4~8',
                '2' => '2~6',
                '4' => '1~5'
            );
        }

        $this->redirect('myteam');
    }

    /**
     * 销售组队模块
     * 我的团队 (显示我的下级组员与他们的累计消费情况)
     * dyg_jzw 20150623
     */

    function jointeam()
    {
        $this->redirect('jointeam');
    }

    /**
     * 团队销售记录(预存款冻结记录)
     * dyg_jzw 20150813
     *
     */
    function freeze_log()
    {
        //获取用户的余额和冻结金额
        $memberObj         = new IModel('member');
        $where             = 'user_id = '.$this->user['user_id'];
        $this->memberRow   = $memberObj->getObj($where,'balance, balance_freeze');

        //获取订单自动更新插件的config
        $auto_param = Plugin::getItems('orderAutoUpdate');

        //订单自动确认时间(分钟)
        $this->order_finish_time = isset($auto_param['config_param']['order_finish_time']) ? intval($auto_param['config_param']['order_finish_time']) : 7*24*60;
        //订单自动返佣时间(分钟)
        $this->order_reward_time = isset($auto_param['config_param']['order_commission_time']) ? intval($auto_param['config_param']['order_commission_time']) : 31*24*60;
        //订单自动高返时间(分钟)
        $this->order_my_reward_time = isset($auto_param['config_param']['order_my_commission_time']) ? intval($auto_param['config_param']['order_my_commission_time']) : 60*24*60;

        $this->redirect('freeze_log',false);
    }
    /**
     * 最近6个月（本月不算），前7个月~前12个月  消费情况
     */
    function my_consume()
    {
        $cache =  new IMemCache();
        $orderObj    = new IModel('order');
        $condition   = IFilter::act(IReq::get('condition'),'int');
        //今天开始，倒回6个月
        $six_month_ago   = date('Y-m-01',strtotime('-6 months'));
        //从上个月01:00:00:00  开始算
        //date("Y-m-d") == date("Y-m-d 00:00:00")
        $last_month_ago  = date('Y-m-d',strtotime(date('Y-m-01')));
        //前12个月
        $twelve_month_ago   = date('Y-m-d',strtotime(date('Y-m-01').'-12 months'));
        //前7个月
        $seven_month_ago   = date('Y-m-d',strtotime(date('Y-m-01').'-6 months'));
        //最近6个月（本月除外），每个月的时间
        for ($i = 1;$i < 6;$i++)
        {
            $month[] = date('Y-m',strtotime('-'.$i.' months'));
        }
        /*  if($cache->get('newArray_'.$this->user['user_id'].'_'.$condition) && $cache->get('catArray_'.$this->user['user_id'].'_'.$condition))
          {
              $this->catArray = $cache->get('catArray_'.$this->user['user_id'].'_'.$condition);
              $this->newArray = $cache->get('newArray_'.$this->user['user_id'].'_'.$condition);
              $this->condition = $condition;
              $this->months = $month;
              $this->redirect('my_consume',false);
          }*/
        if($condition == -6)
        {
            $filter_time  = "create_time between  '$six_month_ago' and '$last_month_ago'";
        }
        elseif ($condition == -12)
        {
            $filter_time  = "create_time between  '$twelve_month_ago' and '$seven_month_ago'";
        }
        else
        {
            $condition2 = $condition+1;
            $filter_time  = "create_time between  '".date('Y-m-01',strtotime("-$condition2 months"))."' and  '".date('Y-m-d',strtotime(date("Y-m-01")."-$condition months"))."'";
        }
        //筛选条件是什么？
        $where = ' user_id = '.$this->user['user_id']. ' and pay_status = 1  and if_del = 0 and '.$filter_time;
        //按时间分组
        $where .= ' group by DATE_FORMAT(create_time,\'%Y-%m-%d\')';
        //选择日期并查询出改日期下所有订单ID
        //分组统计总运费 总金额
        $resultSet  = $orderObj->query($where,'DATE_FORMAT(create_time,\'%Y-%m-%d\') as date,group_concat(id) as o_id');
        $newArray = array();
        $catArray = array();
        if($resultSet)
        {
            $orderGoodsDB = new IModel('order_goods');
            foreach ($resultSet as $key=>$value)
            {
                $order_ids = explode(',',$value['o_id']);
                $sum  = 0;
                $keysArray = array();
                foreach ($order_ids as $oid)
                {
                    $all_order_goods = $orderGoodsDB->query('order_id = '.$oid.' and is_send <> 2','goods_id,real_price,goods_nums');
                    //如果为空，直接跳过下面操作
                    if(!$all_order_goods)
                    {
                        continue;
                    }
                    foreach ($all_order_goods as $goods)
                    {
                        $catInfo =  goods_class::getGoodsCategory($goods['goods_id']);
                        //取其中一个分类
                        $first_cat = $catInfo?strstr($catInfo, ',', true):"未知分类";
                        //取出一个分类，形成新的数组
                        $catArray[] = $first_cat?$first_cat:'未知分类';
                        //计算订单金额
                        $sum = bcadd($sum ,$goods['real_price']*$goods['goods_nums'],2);
                        if(array_key_exists($first_cat, $keysArray))
                        {
                            $keysArray[$first_cat] = bcadd($keysArray[$first_cat],bcmul($goods['real_price'],$goods['goods_nums'],2),2);
                        }
                        else
                        {
                            $keysArray[$first_cat] = bcmul($goods['real_price'],$goods['goods_nums'],2);
                        }
                    }
                    $newArray[$value['date']]['cat'] = $keysArray;
                    $newArray[$value['date']]['o_id'] =  $value['o_id'];
                    $newArray[$value['date']]['amount'] =  $sum;
                }
            }
        }
        $catArray = array_unique($catArray);
        $this->catArray = $catArray;
        $this->newArray = $newArray;
        $this->condition = $condition;
        $this->month = $month;
        $cache->set('newArray_'.$this->user['user_id'].'_'.$condition,$newArray,3600);
        $cache->set('catArray_'.$this->user['user_id'].'_'.$condition,$catArray,3600);
        $this->redirect('my_consume',false);
    }


}