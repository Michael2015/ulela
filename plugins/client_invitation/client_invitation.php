<?php
/**
 * @copyright (c) 2017 dyg.cn
 * @file seller_router.php
 * @brief 商户自动跳转处理
 * @author jzw
 * @date 2017/3/16 9:15:21
 * @version 1.0
 */
class client_invitation extends pluginBase
{
    //插件注册
    public function reg()
    {
        //欢迎页
        plugin::reg("onBeforeCreateAction@invitation@welcome",function(){
            self::controller()->welcome = function(){$this->welcome();};
        });
        //生产邀请客户名称
        plugin::reg("onBeforeCreateAction@invitation@invite_client",function(){
            self::controller()->invite_client = function(){$this->invite_client();};
        });
        //生产邀请结果页
        plugin::reg("onBeforeCreateAction@invitation@invite_client_result",function(){
            self::controller()->invite_client_result = function(){$this->invite_client_result();};
        });
    }
    //欢迎页
    public function welcome()
    {
        $this->redirect('welcome');
    }
    //添加参数
    public function invite_client()
    {
        $client_name = IFilter::act(IReq::get('client_name'),'string');
        $sign = substr(IHash::md5($client_name.'client'),0,6);
        header('Location: /invitation/invite_client_result?is_reset=1&client_name='.$client_name.'&sign='.$sign);
    }
    //根据名字生产水印图片
    public function invite_client_result()
    {
        plugin::trigger("wxJsApi");
        $client_name = IFilter::act(IReq::get('client_name'),'string');
        $sign = IFilter::act(IReq::get('sign'),'string');
        $is_reset= IFilter::act(IReq::get('is_reset'),'int');
        if(!$client_name)
        {
            IError::show(403,'缺少参数');
        }
        //验证签名
        if($sign !== substr(IHash::md5($client_name.'client'),0,6))
        {
            IError::show(403,'签名验证失败');
        }
        //统计名字的多少
        $name_str = mb_strlen($client_name);
        $fontsize = '700';
        switch ($name_str)
        {
            case 2:
                $dx = '218';
                $dy = '200';
                break;
            case 3:
                $dx = '200';
                $dy = '-182';
                break;
            case 4:
                $dx = '200';
                $dy = '-182';
                $fontsize = '550';
                break;
            default:
                $dx = '200';
                $dy = '-182';
                break;
        }
        $qiuniu_image_url = 'https://static2.dygmall.com/gaoerfu_20171102.jpg?watermark/2/text/'.str_replace(array('+','/'),array('-','_'),base64_encode($client_name)).'/font/6buR5L2T/fontsize/'.$fontsize.'/fill/I0ZGRkZGRg==/dissolve/100/gravity/West/dx/'.$dx.'/dy/'.$dy;
        $this->redirect('invite_client_result',array('client_invitation'=>$qiuniu_image_url,'client_name'=>$client_name,'is_reset'=>$is_reset,'sign'=>$sign));
    }
    public static function name()
    {
        return "大客户邀请函自动签名";
    }

    public static function description()
    {
        return "大客户根据不同的名字生成邀请函";
    }

    public static function install()
    {
        return true;
    }

    public static function uninstall()
    {
        return true;
    }

    public static function configName()
    {
        return array();
    }
}