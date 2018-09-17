<?php
/**
 * @copyright (c) 2017 dyg.cn by yangmz
 * @file auto_ticket
 * @brief  商城优惠券自动派发
 * @author yangmz
 * @date 2017/6/27 15:20:56
 * @version 1.0
 */
class send_ticket_activity extends pluginBase
{
    const SALT = "dygshop";
    //插件注册
    public function reg()
    {
        //优惠券活动派发入口
        plugin::reg("onBeforeCreateAction@block@user_add_ticket",function()
        {
            self::controller()->user_add_ticket = function(){$this->user_add_ticket();};
        });
        //签名
        plugin::reg("ticket_sign",$this,"ticket_sign");
    }
    //签名
    public function ticket_sign($ticket_id)
    {
        if(!$ticket_id)
        {
            return false;
        }
        $salt = self::SALT;//加密盐值
        return  substr(IHash::md5($ticket_id.$salt),0,6);
    }
    //签名认证
    private function ticket_sign_verify($ticket_id,$sign)
    {
        return $this->ticket_sign($ticket_id) === $sign ? true : false;
    }
    //发券并领取
    public function user_add_ticket()
    {
        if(!$user = self::controller()->user)
        {
            header('Location:/simple/login');
        }
        $ticketID = IReq::get('tid');//提交的ticket_id
        $sign = IReq::get('sign');//提交的ticket_id
        if(!$this->ticket_sign_verify($ticketID,$sign))
        {
            IError::show('非法券ID');
        }
        $userInfo = Api::run('getMemberInfo',$user['user_id']);
        if(!$userInfo)
        {
            IError::show('未知身份');
        }
        $group_id = $userInfo['group_id'];//会员等级

        if(!$ticketID)
        {
            IError::show('缺少参数');
        }
        $ticketDB = new IModel('ticket');
        $current_time = date('Y-m-d H:i:s',time());
        $ticket = $ticketDB->getObj(" id = $ticketID  AND '$current_time' > start_time AND end_time >'$current_time'",'id,name,value,start_time,end_time,point,got_limit,is_open,user_level_constraint');
        if($ticket && $ticket['is_open'] == 1)
        {
            $propObj   = new IModel('prop');
            if($ticket['point'] > $userInfo['point'])
            {
                IError::show('您的积分不够');
            }
            if ($ticket['got_limit'])
            {
                //查询用户已兑换的张数
                $_got_sum = $propObj->getObj("type = '0' and `condition` = '{$ticket['id']}' and user_id = '".$user['user_id']."'", "count(id) as total_change");
                if ($_got_sum['total_change'] >= $ticket['got_limit'])
                {
                    IError::show('对不起，您已超过此类代金券的兑换上限');
                }
            }
            $user_level_constraint = ';'.$ticket['user_level_constraint'].';';
            if(!strstr($user_level_constraint,';'.$group_id.';'))
            {
                IError::show('会员等级不符合');
            }
            $memberObj = new IModel('member');
            $dataArray = array(
                'condition' => $ticketID,
                'name'      => $ticket['name'],
                'card_name' => IHash::random(16,'int'),
                'card_pwd'  => IHash::random(8),
                'value'     => $ticket['value'],
                'start_time'=> $ticket['start_time'],
                'end_time'  => $ticket['end_time'],
                'is_send'   =>1,
                'user_id'   =>$user['user_id'],
            );
            $propObj->setData($dataArray);
            if($insert_id = $propObj->add())
            {
                $memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$insert_id},')");
                $memberObj->setData($memberArray);
                $memberObj->update('user_id = '.$user['user_id'],'prop');
                header("Location:/ucenter/redpacket");
            }
            else
            {
                IError::show('未知原因，生成券失败');
            }
        }
        else
        {
            IError::show('找不到该券,或已经过期');
        }
    }
    public static function name()
    {
        return "商城优惠券派发活动";
    }
    public static function description()
    {
        return "商城优惠券派发活动，任何登录的用户都可以领取并消费使用。";
    }
    public static function install()
    {
        $ticketDB = new IModel('ticket');
        if($ticketDB->exists())
        {
            $sql = "
              ALTER TABLE `iwebshop_ticket` ADD (
              `is_open` tinyint(1) NULL default 0  COMMENT '是否为公开券',
              `user_level_constraint` VARCHAR(50) NULL COMMENT '会员等级约束');";
            IDBFactory::getDB()->query($sql);
            return true;
        }
        return false;
    }
    public static function uninstall()
    {
        $ticketDB = new IModel('ticket');
        if($ticketDB->exists())
        {
            $sql = "ALTER TABLE `iwebshop_ticket` DROP column  `is_open`, DROP column  `user_level_constraint`;";
            IDBFactory::getDB()->query($sql);
            return true;
        }
        return false;
    }
    public static function configName()
    {
        return array();
    }

}