<?php
class Sku{
	function __construct($quantity){
		$this->quantity = $quantity;
	}
};
class DateInfo{
	function __construct($type, $arg0, $arg1 = null)
	{
		if (!is_int($type) )
			exit("DateInfo.type must be integer");
		$this->type = $type;
		if ( $type == 1 )  //固定日期区间
		{
			if (!is_int($arg0) || !is_int($arg1))
				exit("begin_timestamp and  end_timestamp must be integer");
			$this->begin_timestamp = $arg0;
			$this->end_timestamp = $arg1;
		}
		else if ( $type == 2 )  //固定时长（自领取后多少天内有效）
		{
			if (!is_int($arg0))
				exit("fixed_term must be integer");
			$this->fixed_term = $arg0;
		}else
		exit("DateInfo.tpye Error");
	}
};
class BaseInfo{
	function __construct($logo_url, $brand_name, $code_type, $title, $color, $notice, $service_phone,
		$description, $date_info, $sku)
	{
		if (! $date_info instanceof DateInfo )
			exit("date_info Error");
		if (! $sku instanceof Sku )
			exit("sku Error");
		if (! is_int($code_type) )
			exit("code_type must be integer");
		$this->logo_url = $logo_url;
		$this->brand_name = $brand_name;
		$this->code_type = $code_type;
		$this->title = $title;
		$this->color = $color;
		$this->notice = $notice;
		$this->service_phone = $service_phone;
		$this->description = $description;
		$this->date_info = $date_info;
		$this->sku = $sku;
	}
	function set_sub_title($sub_title){
		$this->sub_title = $sub_title;
	}
	function set_use_limit($use_limit){
		if (! is_int($use_limit) )
			exit("use_limit must be integer");
		$this->use_limit = $use_limit;
	}
	function set_get_limit($get_limit){
		if (! is_int($get_limit) )
			exit("get_limit must be integer");
		$this->get_limit = $get_limit;
	}
	function set_use_custom_code($use_custom_code){
		$this->use_custom_code = $use_custom_code;
	}
	function set_bind_openid($bind_openid){
		$this->bind_openid = $bind_openid;
	}
	function set_can_share($can_share){
		$this->can_share = $can_share;
	}
	function set_location_id_list($location_id_list){
		$this->location_id_list = $location_id_list;
	}
	function set_url_name_type($url_name_type){
		if (! is_int($url_name_type) )
			exit( "url_name_type must be int" );
		$this->url_name_type = $url_name_type;
	}
	function set_custom_url($custom_url){
		$this->custom_url = $custom_url;
	}
};
class CardBase{
	public function __construct($base_info){
		$this->base_info = $base_info;
	}
};
class GeneralCoupon extends CardBase{
	function set_default_detail($default_detail){
		$this->default_detail = $default_detail;
	}
};
class Groupon extends CardBase{
	function set_deal_detail($deal_detail){
		$this->deal_detail = $deal_detail;
	}
};
class Discount extends CardBase{
	function set_discount($discount){
		$this->discount = $discount;
	}
};
class Gift extends CardBase{
	function set_gift($gift){
		$this->gift = $gift;
	}
};
class Cash extends CardBase{
	function set_least_cost($least_cost){
		$this->least_cost = $least_cost;
	}
	function set_reduce_cost($reduce_cost){
		$this->reduce_cost = $reduce_cost;
	}
};
class MemberCard extends CardBase{
	function set_supply_bonus($supply_bonus){
		$this->supply_bonus = $supply_bonus;
	}
	function set_supply_balance($supply_balance){
		$this->supply_balance = $supply_balance;
	}
	function set_bonus_cleared($bonus_cleared){
		$this->bonus_cleared = $bonus_cleared;
	}
	function set_bonus_rules($bonus_rules){
		$this->bonus_rules = $bonus_rules;
	}
	function set_balance_rules($balance_rules){
		$this->balance_rules = $balance_rules;
	}
	function set_prerogative($prerogative){
		$this->prerogative = $prerogative;
	}
	function set_bind_old_card_url($bind_old_card_url){
		$this->bind_old_card_url = $bind_old_card_url;
	}
	function set_activate_url($activate_url){
		$this->activate_url = $activate_url;
	}
};
class ScenicTicket extends CardBase{
	function set_ticket_class($ticket_class){
		$this->ticket_class = $ticket_class;
	}
	function set_guide_url($guide_url){
		$this->guide_url = $guide_url;
	}
};
class MovieTicket extends CardBase{
	function set_detail($detail){
		$this->detail = $detail;
	}
};
class Card{  //工厂
	private	$CARD_TYPE = Array("GENERAL_COUPON",
		"GROUPON", "DISCOUNT",
		"GIFT", "CASH", "MEMBER_CARD",
		"SCENIC_TICKET", "MOVIE_TICKET" );

	function __construct($card_type, $base_info)
	{
		if (!in_array($card_type, $this->CARD_TYPE))
			exit("CardType Error");
		if (! $base_info instanceof BaseInfo )
			exit("base_info Error");
		$this->card_type = $card_type;
		switch ($card_type)
		{
			case $this->CARD_TYPE[0]:
			$this->general_coupon = new GeneralCoupon($base_info);
			break;
			case $this->CARD_TYPE[1]:
			$this->groupon = new Groupon($base_info);
			break;
			case $this->CARD_TYPE[2]:
			$this->discount = new Discount($base_info);
			break;
			case $this->CARD_TYPE[3]:
			$this->gift = new Gift($base_info);
			break;
			case $this->CARD_TYPE[4]:
			$this->cash = new Cash($base_info);
			break;
			case $this->CARD_TYPE[5]:
			$this->member_card = new MemberCard($base_info);
			break;
			case $this->CARD_TYPE[6]:
			$this->scenic_ticket = new ScenicTicket($base_info);
			break;
			case $this->CARD_TYPE[8]:
			$this->movie_ticket = new MovieTicket($base_info);
			break;
			default:
			exit("CardType Error");
		}
		return true;
	}
	function get_card()
	{
		switch ($this->card_type)
		{
			case $this->CARD_TYPE[0]:
			return $this->general_coupon;
			case $this->CARD_TYPE[1]:
			return $this->groupon;
			case $this->CARD_TYPE[2]:
			return $this->discount;
			case $this->CARD_TYPE[3]:
			return $this->gift;
			case $this->CARD_TYPE[4]:
			return $this->cash;
			case $this->CARD_TYPE[5]:
			return $this->member_card;
			case $this->CARD_TYPE[6]:
			return $this->scenic_ticket;
			case $this->CARD_TYPE[8]:
			return $this->movie_ticket;
			default:
			exit("GetCard Error");
		}
	}
	function toJson()
	{
		return "{ \"card\":" . urldecode(json_encode($this,JSON_UNESCAPED_UNICODE)) . "}";
	}
};
/*class Signature{
	function __construct(){
		$this->data = array();
	}
	function add_data($str){
		array_push($this->data, (string)$str);
	}
	function get_signature(){
		sort( $this->data, SORT_STRING );
		return sha1( implode( $this->data ) );
	}
};*/
class wechat_ticket{
	private  $appid;
    private  $appserect;
	function __construct($appid,$appserect)
    {
        $this->appserect = $appserect;
        $this->appid = $appid;
    }
    //根据APPID和appserect获取access_token
	private  function getAccessToken()
	{
		$access_token_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appserect;
		$content_json = self::doCurl($access_token_url);
		$content_object = json_decode($content_json);
		return $content_object?$content_object->access_token:"";
	}
	//创建卡券
	public  function  getCartId($setCartJson)
	{
		$create_cart_api_url = "https://api.weixin.qq.com/card/create?access_token=".$this->getAccessToken();
		$return_data = self::doCurl($create_cart_api_url,$setCartJson);//api形式生成卡券
		$return_data_object = json_decode($return_data);
        return $return_data_object->errcode ==0?$return_data_object->card_id:'';
	}

	//设置卡券各类参数
	private static function  setCartJson($start_time,$end_time,$callbackurl)
	{
		//$logo_url, $brand_name, $code_type, $title, $color, $notice, $service_phone,$description, $date_info, $sku
		$base_info = new BaseInfo( "http://chuantu.biz/t5/55/1490952512x2890174195.png", "东阳光鲜草",
			2, "50元代金券", "Color070", "使用时向服务员出示此券", "020-88888888",
			"不可与其他优惠同享\n 如需团购券发票，请在消费时向商户提出\n 店内均可使用，仅限堂食\n 餐前不可打包，餐后未吃完，可打包\n 本团购券不限人数，建议2人使用，超过建议人数须另收酱料费5元/位\n 本单谢绝自带酒水饮料", new DateInfo(1,$start_time,$end_time), new Sku(50000000) );
		$base_info->set_sub_title( "99999代金券" );
		$base_info->set_use_limit( 1 );
		$base_info->set_get_limit( 1 );
		$base_info->set_use_custom_code(true);
		//$base_info->code = "123456789012";
		$base_info->set_bind_openid( false );
		$base_info->set_can_share( false );
		$base_info->set_url_name_type( 1 );
		$base_info->center_title = "马上使用";
		$base_info->center_url = $callbackurl;
		$base_info->custom_url_name = "东阳光官网";
		$base_info->set_custom_url( "http://dyg.cn/" );
		$card = new Card("DISCOUNT", $base_info);
		$card->get_card()->set_discount(1);
		return $card->toJson();
	}
	//获取code
	public function getCardCode($encrypt_code)
	{
		$get_code_api_url = "https://api.weixin.qq.com/card/code/decrypt?access_token=".$this->getAccessToken();
		$post_data = array('encrypt_code'=>$encrypt_code);
		$return_data_json  = self::doCurl($get_code_api_url,json_encode($post_data));
		$return_data_object = json_decode($return_data_json);
		return $return_data_object?$return_data_object->code:'';
	}
	//查询Code接口
	public function checkConsume($code,$cardid)
	{
		$check_consume_api_url = "https://api.weixin.qq.com/card/code/get?access_token=".$this->getAccessToken();
		$post_data_arr = array(
			'card_id'=>$cardid,
			'code'=>$code,
			'check_consume'=>true
			);
		$return_data_json  = self::doCurl($check_consume_api_url,json_encode($post_data_arr));
		return $return_data_json->errcode == 0?true:false;
	}
    //核销卡券
	public function consumeCard($card_code,$cardid)
	{
		$consume_card_api_url = "https://api.weixin.qq.com/card/code/consume?access_token=".$this->getAccessToken();
		$post_data = array('code'=>$card_code,'card_id'=>$cardid);
		$return_data_json  = self::doCurl($consume_card_api_url,json_encode($post_data));

		return $return_data_json;
	}
	//获取卡券的二维码
	public  function getQrcode($cardid,$code)
	{
		$get_qrcode_url = "https://api.weixin.qq.com/card/qrcode/create?access_token=".$this->getAccessToken();
		$post_data_arr = array(
			'action_name'=>"QR_CARD",
			'action_info'=>array('card'=>array('card_id'=>$cardid,'code'=>$code,'is_unique_code'=>true)),
			);
		$return_data_json  = self::doCurl($get_qrcode_url,json_encode($post_data_arr));
		$return_data_object = json_decode($return_data_json);
		return isset($return_data_object->show_qrcode_url)?$return_data_object->show_qrcode_url:'';
	}
	//CURL 操作
	private static function doCurl($url,$postData=null)
	{
		if(!$url){
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
        //处理https请求
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		//post请求带参数
		if($postData){
            curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
};
?>