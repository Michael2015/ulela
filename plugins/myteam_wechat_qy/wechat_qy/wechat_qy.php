<?php


class wechat_qy
{
    const WEIXIN_API = 'https://qyapi.weixin.qq.com/cgi-bin/';

    public function access_request($app_id, $app_secret, $url, $contents = NULL)
    {
        if (!$app_id OR !$app_secret)
        {
            return false;
        }

        $url = self::WEIXIN_API . $url . '?access_token=' . $this->get_access_token($app_id, $app_secret);

        $result = $this->request($url, $contents);

        if (!$result)
        {
            return false;
        }

        $result = json_decode($result, true);

        if ($result['errcode'] == 40001)
        {
            $this->refresh_access_token($app_id, $app_secret);
        }

        return $result;
    }

    public function replace_post($subject)
    {
        $subject = $this->array_urlencode($subject);

        if (!$subject)
        {
            return false;
        }

        return urldecode(json_encode($subject));
    }

    public function array_urlencode($array)
    {
        if (!$array OR !is_array($array))
        {
            return false;
        }

        $new_array = array();

        foreach ($array as $key => $value)
        {
            $new_array[urlencode($key)] = is_array($value) ? $this->array_urlencode($value) : urlencode($value);
        }

        return $new_array;
    }

    public function refresh_access_token($app_id, $app_secret)
    {
        if (!$app_id OR !$app_secret)
        {
            return false;
        }

        $cached_token = 'weixin_access_token_' . md5($app_id . $app_secret);

        $memCache = new ICache('memcache');
        $memCache->del($cached_token);

        return $this->get_access_token($app_id, $app_secret);
    }

    public function get_access_token($app_id, $app_secret)
    {
        if (!$app_id OR !$app_secret)
        {
            return false;
        }

        $cached_token = 'weixin_access_token_' . md5($app_id . $app_secret);

        $memCache = new ICache('memcache');
        $access_token = $memCache->get($cached_token);

        if ($access_token)
        {
            return $access_token;
        }

        $result = $this->request(self::WEIXIN_API . 'gettoken?corpid=' . $app_id . '&corpsecret=' . $app_secret);

        if (!$result)
        {
            return false;
        }

        $result = json_decode($result, true);

        if (!$result['access_token'])
        {
            return false;
        }

        $memCache->set($cached_token, $result['access_token'], 3600);

        return $result['access_token'];
    }

    public function request($url, $post_data = NULL)
    {
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($post_data !== NULL) 
        {
            curl_setopt($ch, CURLOPT_POST, 1);

            $post_str = '';
            if(is_array($post_data))
            {
                foreach ($post_data as $post_key => $post_value) 
                {
                    $post_str .= '&' . $post_key .'='.urlencode($post_value);
                }
                $post_str = substr($post_str, 1);
            }
            else
            {
                $post_str = $post_data;
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_str);
        }

        $content = curl_exec($ch); //执行curl并赋值给$content
        curl_close($ch); //关闭curl

        return $content;
    }
}
