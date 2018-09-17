<?php

/*/**
 * @copyright (c) 2017 dyg.cn by yangmz
 * @file wechat.php
 * @brief 打通微信优惠券和商城优惠券
 * @author jzw
 * @date 2017/6/23 17:23:56
 * @version 1.0
 */

class wx_ticket extends pluginBase
{
    //插件注册
    public function reg()
    {
        //生成微信代金券
        plugin::reg("onBeforeCreateAction@market@ticket_edit_act", function ()
        {
            self::controller()->ticket_edit_act = function ()
            {
                $this->ticket_edit_act();
            };
        });
        //生成微信优惠券二维码
        plugin::reg("onBeforeCreateAction@ucenter@getQrCode", function ()
        {
            self::controller()->getQrCode = function () {
                $this->getQrCode();
            };
        });
        //微信优惠券的领取   这个回调链接必须跟微信公众号接口配置信息所填写的URL一致
        plugin::reg("onBeforeCreateAction@ucenter@wechat_callback", function () {
            self::controller()->wechat_callback = function () {
                $this->wechat_callback();
            };
        });
        //微信优惠券点击“立即使用”的时候，跳转到/block/getCode，记录code，并在用户登录之后将优惠券发给用户
        plugin::reg("onCreateController@ucenter@index", $this, "getCode");
        //微信卡券的核销 已经在order_class updateOrderStatus方法处理  注入核销事件
        plugin::reg("consumeCode", $this, 'consumeCode');
        //退款的时候，重新给用户增加一张优惠券
        plugin::reg("addCode", $this, 'addCode');
    }
    //退款的时候，重新给用户增加一张优惠券
    public function addCode($orderId)
    {
        $orderObj = new IModel('order');
        if($propId = $orderObj->getObj('order_no = '.$orderId,'prop')['prop'])
        {
            $propObj = new IModel('prop');
            $propSet = $propObj->getObj('id = '.$propId,'*');
            if($ticketId = $propSet['condition'])
            {
                $ticketObj = new IModel('ticket');
                $ticketArr = $ticketObj->getObj('id = '.$ticketId,'is_wechat_ticket,point,name');
                if($ticketArr['is_wechat_ticket'])//判断是否是微信卡券
                {
                    unset($propSet['id']);
                    unset($propSet['card_name']);
                    unset($propSet['card_pwd']);
                    $propSet['card_name'] = IHash::random(16,'int');
                    $propSet['card_pwd'] = IHash::random(8);
                    $propObj->setData($propSet);
                    $insert_id = $propObj->add();
                    //更新用户prop字段
                    $memberObj = new IModel('member');
                    $memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$insert_id},')");
                    $memberObj->setData($memberArray);
                    $result = $memberObj->update('user_id = '.$propSet['user_id'],'prop');
                    //代金券成功
                    if($result)
                    {
                        $pointConfig = array(
                            'user_id' => $propSet['user_id'],
                            'point'   => '-'.$ticketArr['point'],
                            'log'     => '积分兑换代金券“'.$ticketArr['name'].'”，扣除 '.$ticketArr['point'].' 积分',
                        );
                        $pointObj = new Point;
                        $pointObj->update($pointConfig);
                    }
                }
            }
        }
    }
    //微信卡券的核销 ,根据订单号进行核销微信卡券
    public function consumeCode($orderId)
    {
        include_once(dirname(__FILE__) . "/wechat_ticket.php");
        //根据订单号查找prop_id
        $orderObj = new  IModel('order');
        $propIdArr = $orderObj->getObj('order_no = '.$orderId,'prop');
        if($propIdArr)
        {
            $propObj = new  IModel('prop');//找到card_name
            $resultSet = $propObj->getObj('id = '.$propIdArr['prop'],'card_name,`condition`');
            if($resultSet)
            {
                $code = $resultSet['card_name'];
                $condition = $resultSet['condition'];
                $ticketObj = new  IModel('ticket');//找到card_id
                if ($ticket = $ticketObj->getObj(' id = ' . $condition, 'wx_ticket_cardid')) {
                    $wetchatTicketObj = new wechat_ticket($this->config()['appid'], $this->config()['appserect']);
                    if($wetchatTicketObj->checkConsume($code,$ticket['wx_ticket_cardid']))//微信卡券查询接口
                    {
                        $wetchatTicketObj->consumeCard($code,$ticket['wx_ticket_cardid']);//微信卡券核销
                    }
                }
            }
        }
    }

    //微信优惠券点击“立即使用”的时候，跳转到/ucenter/index，记录code，并在用户登录之后将优惠券发给用户
    public function getCode()
    {
        include_once(dirname(__FILE__) . "/wechat_ticket.php");
        $encrypt_code = IReq::get('encrypt_code');//这个是加密过的code
        if ($encrypt_code) {
            $ticketObj = new wechat_ticket($this->config()['appid'], $this->config()['appserect']);
            $cardCode = $ticketObj->getCardCode($encrypt_code);
            $propDB = new IModel('prop');
            if (self::controller()->user) {
                $user_id = self::controller()->user['user_id'];
                $updateData = array('is_close' => 0, 'user_id' => $user_id);//这张优惠券重新发给优惠券领取的用户
                $propDB->setData($updateData);
                $propDB->update("card_name = " . $cardCode);
            }
            //未登录的时候记录邀请人
            else
            {
                //通过code找到user_id
               $user_info =  $propDB->getObj('card_name = '.$cardCode,'user_id');
               $user_id   = $user_info['user_id'];
               //查出username
               $username =  Api::run('getMemberInfo',$user_id)['username'];
               ISafe::set('wx_ticket_user_name',$username,'cookie');
            }
        }
    }

    //微信优惠券的领取   这个回调链接必须跟微信公众号接口配置信息所填写的URL一致
    private function wechat_callback()
    {
        include_once(dirname(__FILE__) . "/wechat_ticket_callback.php");
        $wechatObj = new wechatCallbackapi();//引用微信sdk
        if ($wechatObj->valid())//判断验证成功
        {
            $propDB = new IModel('prop');
            $responseMsg = $wechatObj->getCallbankInfo();
            $event = $responseMsg ? $responseMsg['event'] : '';
            $cardCode = $responseMsg ? $responseMsg['code'] : '';
            if ($event == 'user_gifting_card')//转赠事件推送
            {
                if (!$ticket = $propDB->getObj("card_name = " . $cardCode . " AND is_userd = 1", 'id,user_id'))//判断这样券有没有被使用过
                {
                    $updateData = array('is_close' => 0, 'user_id' => null);//这张优惠券被释放出来
                    $propDB->setData($updateData);
                    $propDB->update("card_name = " . $cardCode);
                }
                /* else
                 {
                     $ticket_id = $ticket['id'];
                     $user_id = $ticket['user_id'];
                     $memberObj = new IModel('member');//转赠之后更新member表的prop字段
                     $memberDataArray = array('prop' => "replace(`prop`,$ticket_id.',','')");
                     $propDB->setData($memberDataArray);
                     $memberObj->update('user_id = '.$user_id);
                 }*/
            } else if ($event == 'user_del_card')//删除事件推送
            {
                $updateData = array('is_close' => 1);//这张优惠券被锁定
                $propDB->setData($updateData);
                $propDB->update("card_name = " . $cardCode);
            }
        }
    }

    //生产微信代金券
    public function ticket_edit_act()
    {
        //如果is_open  ==1 必须开启send_ticket_activity插件
        $is_open = IFilter::act(IReq::get('is_open', 'post'), 'int');
        $user_level_constraint = IReq::get('user_level_constraint', 'post');
        if($is_open && plugin::getItems('send_ticket_activity')['is_open'] == 0)
        {
            die('必须开启send_ticket_activity插件');
        }
        $id = IFilter::act(IReq::get('id'), 'int');
        $ticketObj = new IModel('ticket');
        $is_wechat_ticket = IFilter::act(IReq::get('is_wechat_ticket', 'post'), 'int');
        $wx_ticket_color = IFilter::act(IReq::get('wx_ticket_color', 'post'), 'string');
        $wx_ticket_title = IFilter::act(IReq::get('wx_ticket_title', 'post'), 'string');
        $wx_ticket_sub_title = IFilter::act(IReq::get('wx_ticket_sub_title', 'post'), 'string');
        $wx_ticket_tel = IFilter::act(IReq::get('wx_ticket_tel', 'post'), 'string');
        $readme = IFilter::act(IReq::get('readme', 'post'));
        $start_time = IFilter::act(IReq::get('start_time', 'post'));
        $end_time = IFilter::act(IReq::get('end_time', 'post'));
        $name = IFilter::act(IReq::get('name', 'post'));//代金券面额值
        $wx_ticket_cardid = IFilter::act(IReq::get('wx_ticket_cardid', 'post'));//代金券面额值
        $at_least_money = IFilter::act(IReq::get('at_least_money', 'post'), 'int');
        $value = IFilter::act(IReq::get('value', 'post'), 'int');
        $dataArray = array(
            'name' => $name,//代金券名称 如：满200减20
            'value' => $value,//代金券面额值
            'start_time' => $start_time,
            'end_time' => $end_time,
            'point' => IFilter::act(IReq::get('point', 'post')),//兑换优惠券所需积分,如果是0表示禁止兑换
            'goods_ids' => IFilter::act(IReq::get('goods_ids', 'post')), //dyg_jzw 20161226 增加代金券使用限制
            'at_least_money' => $at_least_money, //满多少可以使用
            'got_limit' => IFilter::act(IReq::get('got_limit', 'post')),
            'readme' => $readme,
            'is_wechat_ticket' => $is_wechat_ticket,//增加添加微信优惠券的判断方式
            'wx_ticket_color' => $wx_ticket_color,//微信颜色
            'wx_ticket_title' => $wx_ticket_title,//主标题
            'wx_ticket_sub_title' => $wx_ticket_sub_title,//卡券副标题
            'wx_ticket_tel' => $wx_ticket_tel,//商家联系电话
            'wx_ticket_cardid' => $is_wechat_ticket & !$wx_ticket_cardid ? $this->getCardId($value, $at_least_money, $name, $wx_ticket_color, $wx_ticket_title, $wx_ticket_sub_title, $wx_ticket_tel, $readme, $start_time, $end_time) : $wx_ticket_cardid,//微信card ID
            'is_open'=>$is_open,
            'user_level_constraint'=>implode(';',$user_level_constraint),
        );
        $dataArray['goods_ids'] = trim($dataArray['goods_ids'], ",");
        $ticketObj->setData($dataArray);
        if ($id)
        {
            //dyg_jzw 20170111删除缓存
            $cache = new ICache('memcache');
            $cache->del("getTicketInfo_" . $id);

            $where = 'id = ' . $id;
            $ticketObj->update($where);
        }
        else
        {
            $ticketObj->add();
        }
        header("Location:/market/ticket_list");
    }

    //生成card ID
    public function getCardId($value, $at_least_money, $name, $wx_ticket_color, $wx_ticket_title, $wx_ticket_sub_title, $wx_ticket_tel, $readme, $start_time, $end_time)
    {
        include_once(dirname(__FILE__) . "/wechat_ticket.php");
        $base_info = new BaseInfo("http://chuantu.biz/t5/55/1490952512x2890174195.png", $wx_ticket_title,
            2, $name, $wx_ticket_color, "使用时向服务员出示此券", $wx_ticket_tel, $readme, new DateInfo(1, strtotime($start_time), strtotime($end_time)), new Sku(1000000));
        $base_info->set_sub_title($wx_ticket_sub_title);
        $base_info->set_use_limit(1);
        $base_info->set_get_limit(1);
        $base_info->set_use_custom_code(true);
        //$base_info->code = "123456789012";
        $base_info->set_bind_openid(false);
        $base_info->set_can_share(false);
        $base_info->set_url_name_type(1);
        $base_info->center_title = "马上使用";
        $base_info->center_url = $this->config()['callbackurl'];
        $base_info->custom_url_name = "东阳光官网";
        $base_info->set_custom_url("http://dyg.cn/");
        $card = new Card("CASH", $base_info);
        $card->get_card()->set_least_cost($at_least_money * 100);//代金券专用，表示起用金额（单位为分）,如果无起用门槛则填0。
        $card->get_card()->set_reduce_cost($value * 100);//代金券专用，表示减免金额。（单位为分）
        $ticketObj = new wechat_ticket($this->config()['appid'], $this->config()['appserect']);
        return $ticketObj->getCartId($card->toJson());//返回card ID
    }

    //生成微信优惠券二维码
    public function getQrCode()
    {
        include_once(dirname(__FILE__) . "/wechat_ticket.php");
        $propDB = new  IModel('prop');
        $ticket_id = IReq::get('ticket_id');//获取优惠券的ID
        if ($ticket_id && $resultSet = $propDB->getObj('id = ' . $ticket_id, 'id,card_name,is_close,`condition`'))
        {
            if ($resultSet['is_close'] != 0)
            {
                echo json_encode(array('error' => 1));
                exit;
            }
            $ticketObj = new IModel('ticket');
            if ($ticket = $ticketObj->getObj(' id = ' . $resultSet['condition'], 'wx_ticket_cardid'))
            {
                $ticketObj = new wechat_ticket($this->config()['appid'], $this->config()['appserect']);
                echo json_encode(array('qrcode_url' => $ticketObj->getQrcode($ticket['wx_ticket_cardid'], $resultSet['card_name'])));
            }
        }
    }

    //获取配置信息
    public function config()
    {
        return parent::config(); // TODO: Change the autogenerated stub
    }

    public static function name()
    {
        return "微信优惠券";
    }

    public static function description()
    {
        return "打通商城优惠券和微信优惠券，包括生成、发放、领取、核销、删除优惠券等等";
    }

    public static function install()
    {
        $sql = "
              ALTER TABLE `iwebshop_ticket` ADD (
              `is_wechat_ticket` tinyint(1) NULL default 0  COMMENT '是否为微信卡券',
              `wx_ticket_color` VARCHAR(10) NULL COMMENT '卡券的颜色',
              `wx_ticket_title` VARCHAR(9) NULL COMMENT '卡券的标题',
              `wx_ticket_sub_title` VARCHAR(9) NULL COMMENT '卡券的副标题',
              `wx_ticket_tel` VARCHAR(20) NULL COMMENT '卡券的商家联系电话',
              `wx_ticket_cardid` VARCHAR(32) NULL COMMENT '卡券的ID');
";
        IDBFactory::getDB()->query($sql);
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
            "appid" => array("name" => "APPID", "type" => "text"),//微信公众号APPID
            "appserect" => array("name" => "APPSERECT", "type" => "text"),//微信公众号APPSERECT
            "callbackurl" => array("name" => "CALLBACKURL", "type" => "text"),//微信公众号点击“立即使用”跳转的链接
        );
    }
}