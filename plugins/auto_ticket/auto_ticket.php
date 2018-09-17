<?php
/**
 * @copyright (c) 2017 dyg.cn by yangmz
 * @file auto_ticket
 * @brief  商城优惠券自动派发
 * @author yangmz
 * @date 2017/6/27 15:20:56
 * @version 1.0
 */
class auto_ticket extends pluginBase
{
    //插件注册
    public function reg()
    {
        //后台插件左侧导航栏设计
        plugin::reg("onSystemMenuCreate",function()
        {
            $link = "/plugins/ticket_list";
            Menu::$menu["插件"]["插件管理"][$link] = $this->name();
        });
        //生成微信代金券
        plugin::reg("onBeforeCreateAction@plugins@ticket_list",function()
        {
            self::controller()->ticket_list = function(){$this->ticket_list();};
        });
        //编辑优惠券自动发放条件
        plugin::reg("onBeforeCreateAction@plugins@ticket_edit",function()
        {
            self::controller()->ticket_edit = function(){$this->ticket_edit();};
        });
        //更新优惠券自动发放条件
        plugin::reg("onBeforeCreateAction@plugins@ticket_update",function()
        {
            self::controller()->ticket_update = function(){$this->ticket_update();};
        });
        //删除优惠券自动发放条件
        plugin::reg("onBeforeCreateAction@plugins@ticket_del",function()
        {
            self::controller()->ticket_del = function(){$this->ticket_del();};
        });
        //登录成功后，自动发券
        plugin::reg("onCreateAction@ucenter@index",$this,"add_ticket");
    }
    //登录成功后，自动发券
    public function add_ticket()
    {
        $ticketDB = new IModel('auto_ticket');
        $current_time = date('Y-m-d H:i:s',time());
        $ticket_list = $ticketDB->query("'$current_time' > start_time AND end_time >'$current_time'",'*');
        if($ticket_list)
        {
            $propObj   = new IModel('prop');
            $ticketObj = new IModel('ticket');
            $memberObj = new IModel('member');
            foreach ($ticket_list as $item)
            {
                $user_id = self::controller()->user['user_id'];//登录用户ID
                $ticket_id = $item['ticket_id'];//优惠活动ID
                $where     = 'id = '.$ticket_id;
                $ticketRow = $ticketObj->getObj($where);
                if($ticketRow && !$propObj->getObj('user_id = '.$user_id." AND `condition` = $ticket_id",'id'))
                {
                    $dataArray = array(
                        'condition' => $ticket_id,
                        'name'      => $ticketRow['name'],
                        'card_name' => IHash::random(16,'int'),
                        'card_pwd'  => IHash::random(8),
                        'value'     => $ticketRow['value'],
                        'start_time'=> $ticketRow['start_time'],
                        'end_time'  => $ticketRow['end_time'],
                        'is_send'   =>1,
                        'user_id'   =>$user_id,
                    );
                    $propObj->setData($dataArray);
                    $insert_id = $propObj->add();
                    //更新用户prop字段
                    $memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$insert_id},')");
                    $memberObj->setData($memberArray);
                    $memberObj->update('user_id = '.$user_id,'prop');
                }
            }
        }
    }
    //删除优惠券自动发放条件
    public function ticket_del()
    {
        $id = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            $ticketDB = new IModel('auto_ticket');
            $ticketDB->del('id = '.$id);
        }
        $this->redirect('ticket_list',true);
    }
    //更新优惠券自动发放条件
    public  function ticket_update()
    {
        $id          = IFilter::act(IReq::get('id'),'int');
        $name        = IFilter::act(IReq::get('name'));
        $start_time    = IFilter::act(IReq::get('start_time'));
        $end_time       = IFilter::act(IReq::get('end_time'));
        $ticket_id    = IFilter::act(IReq::get('ticket_id'),'int');

        $updateData  = array(
            'name'        => $name,
            'start_time'    => $start_time,
            'end_time'       => $end_time,
            'ticket_id'    => $ticket_id,
        );

        $ticketDB = new IModel('auto_ticket');
        $ticketDB->setData($updateData);
        if($id)
        {
            $ticketDB->update('id = '.$id);
        }
        else
        {
            $ticketDB->add();
        }
        $this->redirect('ticket_list',true);
    }
    //编辑优惠券自动发放条件
    public  function ticket_edit()
    {
        $id     = IFilter::act(IReq::get('id'),'int');
        $ticketRow = array();
        if($id)
        {
            $ticketDB = new IModel('auto_ticket');
            $ticketRow= $ticketDB->getObj('id = '.$id);
        }
        $this->redirect('ticket_edit',array('ticketData' => $ticketRow));
    }
    //生成微信代金券
    public  function ticket_list()
    {
        $this->redirect('ticket_list');
    }
    public static function name()
    {
        return "商城优惠券自动派发";
    }
    public static function description()
    {
        return "商城优惠券自动派发，新用户老用户登录自动领取商城优惠券";
    }
    public static function install()
    {
        $ticketDB = new IModel('auto_ticket');
        if($ticketDB->exists())
        {
            return true;
        }
        $data = array(
            "comment" => self::name(),
            "column"  => array(
                "id"         => array("type" => "int(11) unsigned",'auto_increment' => 1),
                "name"       => array("type" => "varchar(50)","comment" => "活动名称"),
                "start_time"       => array("type" => "datetime","comment" => "优惠券领取开始时间"),
                "end_time"   => array("type" => "datetime","comment" => "优惠券领取结束时间"),
                "ticket_id"      => array("type" => "int","comment" => "优惠券的ID"),
            ),
            "index" => array("primary" => "id","key" => "id"),
        );
        $ticketDB->setData($data);
        $ticketDB->createTable();
        return true;
    }
    public static function uninstall()
    {
        $ticketDB = new IModel('auto_ticket');
        return $ticketDB->dropTable();
    }
    public static function configName()
    {
        return array();
    }

}