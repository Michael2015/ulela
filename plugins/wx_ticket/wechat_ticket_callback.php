<?php
/**
 * wechacallback php
 * update time: 20170401
 * author:yangmz
 */
define("TOKEN", "042997");
class wechatCallbackapi
{
    public function valid()
    {
        if($this->checkSignature())
        {
            return true;
        }
        else
        {
            return false;
        }

    }
    public function getCallbankInfo()
    {
        //获取微信提交过来的数据
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        //处理xml格式的数据
        $msg = array();
        if (!empty($postStr))
        {
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $msg['event'] = $postObj->Event;
            $msg['code'] = $postObj->UserCardCode;
            if($postObj->Event = "user_get_card")
            {
            $msg['code'] = $postObj->OldUserCardCode;//为保证安全，微信会在转赠发生后变更该卡券的code号，该字段表示转赠前的code。
            }
            return $msg;
        }
        else
        {
            return null;
        }
    }
    //验证 ＴＯＫＥＮ
    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
}
?>