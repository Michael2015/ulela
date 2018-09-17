<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file notice.php
 * @brief 用户中心api方法
 * @author chendeshan
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APIUcenter
{

    ///用户中心-账户余额
    public function getUcenterAccoutLog($userid)
    {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('account_log');
        $query->where="user_id = ".$userid;
        $query->order = 'id desc';
        $query->page  = $page;
        return $query;
    }
    //用户中心-我的建议
    public function getUcenterSuggestion($userid)
    {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('suggestion');
        $query->where="user_id = ".$userid;
        $query->page  = $page;
        $query->order = 'id desc';
        return $query;
    }
    //用户中心-商品讨论
    public function getUcenterConsult($userid)
    {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('refer as r');
        $query->join   = "join goods as go on r.goods_id = go.id ";
        $query->where  = "r.user_id =". $userid;
        $query->fields = "time,name,question,status,answer,admin_id,go.id as gid,reply_time";
        $query->page   = $page;
        $query->order = 'r.id desc';
        return $query;
    }
    //用户中心-商品评价
    public function getUcenterEvaluation($userid,$status = '')
    {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('comment as c');
        $query->join   = "left join goods as go on c.goods_id = go.id ";
        //dyg_jzw 20160627 自定义评论不显示在用户中心
        $query->where  = ($status === '') ? "c.order_no <> '0' and c.status= 0 and c.user_id = ".$userid : "c.order_no <> '0' and c.user_id = ".$userid." and c.status = ".$status;
        $query->fields = "go.name,go.img,c.*"; //dyg_jzw 增加商品的img属性
        $query->page   = $page;
        $query->order = 'c.id desc';
        return $query;
    }

    //用户中心-用户信息
    public function getMemberInfo($userid){
        $tb_member = new IModel('member as m,user as u');
        $info = $tb_member->getObj("m.user_id = u.id and m.user_id=".$userid);
        $info['group_name'] = "";
        if($info['group_id'])
        {
            $userGroup = new IModel('user_group');
            $groupRow  = $userGroup->getObj('id = '.$info['group_id']);
            $info['group_name'] = $groupRow ? $groupRow['group_name'] : "";
        }
        return $info;
    }
    //用户中心-个人主页统计
    public function getMemberTongJi($userid){

        $cache = new ICache('memcache');
        if(! $result = $cache->get("getMemberTongJi_".$userid))
        {
            $result = array();
            $query = new IQuery('order');
            $query->fields = "count(id) as num";
            $query->where  = "user_id = ".$userid." and if_del = 0";
            $info = $query->find();
            $result['num'] = $info[0]['num'];
            $query->fields = "sum(order_amount) as amount";
            $query->where  = "user_id = ".$userid." and status in (2,5) and if_del = 0"; //dyg_jzw 累计消费将已付款的订单都统计
            $info = $query->find();
            $result['amount'] = $info[0]['amount'];

            $cache->set("getMemberTongJi_".$userid, $result, 60*5);
        }

        return $result;
    }
    //用户中心-代金券统计
    public function getPropTongJi($propIds){
        $query = new IQuery('prop');
        $query->fields = "count(id) as prop_num";
        $query->where  = "id in (".$propIds.") and type = 0";
        $info = $query->find();
        return $info[0];
    }
    //用户中心-积分列表
    public function getUcenterPointLog($userid,$c_datetime)
    {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('point_log');
        $query->where  = "user_id = ".$userid." and ".$c_datetime;
        $query->page   = $page;
        $query->order= "id desc";
        return $query;
    }
    //用户中心-信息列表
    public function getUcenterMessageList($msgIds){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('message');
        $query->where= "id in(".$msgIds.")";
        $query->order= "id desc";
        $query->page = $page;
        return $query;
    }
    //用户中心-订单列表
    public function getOrderList($userid,$status = null){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('order as o');
        $where = "user_id =".$userid." and if_del= 0";
        if($status)
        {
            //未付款
            if($status == 1)
            {
                $where .= ' and status = 1';
            }
            //已付款，待发货
            if($status == 2)
            {
                $where .= ' and status = 2 and distribution_status = 0';
            }
            //已发货(已付款)
            elseif($status == 3)
            {
                $where .=' and status = 2 and distribution_status = 1';
            }
            //已完成，等待评价
            elseif($status == 4)
            {
                $where .=' and o.status = 5';
                $where .=' and o.order_no in (select c.order_no from comment as c where c.status = 0 and c.order_no = o.order_no and o.user_id = c.user_id)';
            }
            //已完成
            elseif($status == 5)
            {
                $where .=' and status = 5';
            }
        }
        $query->where = $where;
        $query->order = "id desc";
        $query->page  = $page;
        return $query;
    }
    //用户中心-我的代金券
    public function getPropList($ids,$status = null){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('prop as p');
        //dyg_jzw 20161227 增加代金券详情
        $query->join   = "left join ticket as t on t.id=p.`condition`";
        $query->fields = "p.*, t.name, t.at_least_money, t.readme,t.is_wechat_ticket";
        $where  = "p.id in(".$ids.") and p.is_send = 1 and p.type=0";
        $query->page   = $page;
        if($status !== null)
        {
            //优惠券可用
            if($status == 1)
            {
                $where .= " and  NOW() between p.start_time and p.end_time and p.type = 0 and p.is_close = 0 and p.is_userd = 0";
            }
            //已经使用 或者锁定中
            elseif($status == 2)
            {
                $where .= " and  (p.is_userd = 1 or is_close = 2)";
            }
            //guoqi
            elseif($status == 3)
            {
                $where .= " and  (p.is_userd = 1 or is_close = 1)";
            }
        }
        $query->where = $where;
        return $query;
    }
    //用户中心-退款记录
    public function getRefundmentDocList($userid){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('refundment_doc');
        $query->where = "user_id = ".$userid;
        $query->order = "id desc";
        $query->page  = $page;
        return $query;
    }
    //用户中心-提现记录
    public function getWithdrawList($userid){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('withdraw');
        $query->where = "user_id = ".$userid." and is_del = 0";
        $query->order = "id desc";
        $query->page  = $page;
        return $query;
    }

    //dyg_jzw 20150806
    //用户中心-经验值记录
    public function getExpList($userid) {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('exp_log');
        $query->where  = "user_id = ".$userid;
        $query->page   = $page;
        $query->order= "id desc";
        return $query;
    }

    //dyg_jzw 20150806
    //用户中心-预存款冻结记录
    public function getFreezeLogList($userid) {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('account_freeze_log');
        $query->where  = "to_user_id = ".$userid;
        $query->page   = $page;
        $query->order= "id desc";
        return $query;
    }

    //[收藏夹]获取收藏夹数据
    public function getFavorite($userid,$cat = '')
    {
        //获取收藏夹信息
        $page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $cat_id = IFilter::act($cat,'int');

        $favoriteObj = new IQuery("favorite as f");
        $favoriteObj->join  = "left join goods as go on go.id = f.rid";
        $favoriteObj->fields= " f.*,go.name,go.id as goods_id,go.img,go.store_nums,go.sell_price,go.market_price";

        $where = 'user_id = '.$userid;
        $where.= $cat_id ? ' and cat_id = '.$cat_id : "";

        $favoriteObj->where = $where;
        $favoriteObj->page  = $page;
        return $favoriteObj;
    }

}