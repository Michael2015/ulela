<?php

/**
 * @brief 小程序
 */
class miniprogram
{
    const APP_ID = 'wxd9ce59a34584ead0';
    const APP_SECRET = '9a1c5147d022f4cc131e72f362b7ac85';
    const APP_URL = "https://api.weixin.qq.com/sns/jscode2session";

    /**
     *小程序检查是否登录
     */
    public static function login($code)
    {
        $params['appid'] = self::APP_ID;
        $params['secret'] = self::APP_SECRET;
        $params['js_code'] = $code;
        $params['grant_type'] = 'authorization_code';
        // 微信API返回的session_key 和 openid
        $arr = self::httpCurl(self::APP_URL, $params, 'POST');
        $arr = json_decode($arr, true);
        // 判断是否成功
        if (isset($arr['errmsg']) && !empty($arr['errmsg'])) {
            return ['code' => '0', 'msg' => $arr['errmsg']];
        }
        $openid = $arr['openid'];
        $session_key = $arr['session_key'];
        // 从数据库中查找是否有该openid

        $tb_user_token = new IModel('user_token');
        $user_info = $tb_user_token->getObj('openid =' . $openid);

        //生成随机token
        $token = uniqid();

        // 如果openid存在，更新openid_time,返回登录成功信息及手机号
        if ($user_info) {

            $tb_user_token->setData(['login_time' => date('Y-m-d H:i:s'),'token'=>$token]);
            $update = $tb_user_token->update('openid=' . $openid);
            if($update)
            {
                return ['code' => '200', 'msg' => '成功','token'=>$token];
            }
            else
            {
                return ['code' => '0', 'msg' => '数据更新失败','token'=>''];
            }
            // openid存在，先判断openid_time,与现在的时间戳相比，如果相差大于4个小时，则则返回登录失败信息，使客户端跳转登录页，如果相差在四个小时之内，则更新openid_time，然后返回登录成功信息及手机号；
            // 根据openid查询到所在条数据
            // 计算openid_time与现在时间的差值
           /* $time = time() - strtotime($user_info['login_time']);
            $time = $time / 3600;
            // 如果四个小时没更新过，则登陆态消失，返回失败，重新登录
            if ($time > 4) {
                return ['code' => '102', 'msg' => '登录失败,时间过期'];
            } else {
                // 根据手机号更新openid时间
                $tb_user_token->setData(['login_time' => date('Y-m-d H:i:s')]);
                $update = $tb_user_token->update('openid=' . $openid);
                if($update)
                {
                    return ['code' => '200', 'msg' => '成功'];
                }
                // 判断是否更新成功
                return ['code' => '103', 'msg' => '登录失败,更新失败'];
            }*/
        } else {
            $tb_user_token->setData(['login_time' => date('Y-m-d H:i:s'),'openid'=>$openid,'session_key'=>$session_key,'token'=>$token]);
            $tb_user_token->add();
            return ['code' => '200', 'msg' => '成功','token'=>$token];
        }
    }

    //登录接口

    public static function httpCurl($url, $params, $method = 'GET', $header = array(), $multi = false)
    {
        date_default_timezone_set('PRC');
        $opts = array(
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_COOKIESESSION => true,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_COOKIE
            => session_name() . '=' . session_id(),
        );
        /* 根据请求类型设置特定参数 */
        switch (strtoupper($method)) {
            case 'GET':
                // $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                // 链接后拼接参数  &  非？
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                throw new Exception('不支持的请求方式！');
        }
        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) throw new Exception('请求发生错误：' . $error);
        return $data;
    }


}