<?php
/**
 * Created by PhpStorm.
 * User: yangmingzhao
 * Date: 2017-10-13
 * Time: 10:49
 */
class Xsrf
{
    //生成令牌
    public static function makeToken()
    {
        //种子库
        $seed_arr = array(0,1,2,3,4,5,6,7,8,9,'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
        shuffle($seed_arr);
        $str = '';
        //组合6位随机字符串
        for ($i = 0;$i<6;$i++)
        {
            //随机数组下标
            $seed = rand(0,count($seed_arr));
            $str .= $seed_arr[$seed];
        }
        //将令牌写入session
        ISafe::set('_xsrf',$token,'session');
        return $token;
    }
    //验证令牌是否有效
    public static function verifyToken($post_token)
    {
         $token =  ISafe::get('_xsrf','session');
         //如果没有token
         if($token == null)
         {
              return false;
         }
         if($post_token !== $token)
         {
             return false;
         }
         return true;
    }
    //删除令牌
    public static function removeToken()
    {
        ISafe::clear('_xsrf','session');
    }
}