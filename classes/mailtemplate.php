<?php
/**
 * @copyright (c) 2014 aircheng.com
 * @file sendmail.php
 * @brief 邮件数据模板
 * @author chendeshan
 * @date 2014/11/28 23:20:51
 * @version 2.9
 */
class mailTemplate
{
	/**
	 * @brief 找回密码模板
	 * @param array $param 模版参数
	 * @return string
	 */
	public static function findPassword($param)
	{
		$siteConfig = new Config("site_config");
		//$templateString = "您好，您在{$siteConfig->name}申请找回密码的操作，点击下面这个链接进行密码重置：<a href='{url}'>{url}</a>。<br />如果不能点击，请您把它复制到地址栏中打开。";
		
		$templateString = '<div style="margin: -15px; padding: 8vh 0 2vh;color: #a6aeb3; background-color: #f7f9fa; text-align: center; font-family:NotoSansHans-Regular,\'Microsoft YaHei\',Arial,sans-serif; -webkit-font-smoothing: antialiased;">
    
    <div style="width: 750px; max-width: 85%; margin: 0 auto; background-color: #fff; -webkit-box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);-moz-box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);">
        
        <div style="padding: 50px 10%; text-align: left; font-size: 16px;line-height: 16px;">
            <a href="'.$siteConfig->url.'" target="_blank" style="vertical-align: top;text-decoration:none;color:#E8B140">
            	<strong>'.$siteConfig->name.'</strong>
            </a>
        </div>

        
        <h1 style="margin:32px auto; max-width: 95%; color: #0e2026;">找回密码</h1>

        <p style="width: 750px; max-width: 90%; margin: 32px auto;padding: 0;">
            您好，您在'.$siteConfig->name.'申请找回密码的操作<br>
            你可以在 72 小时内点击下面的「重设密码」按钮来进行密码修改。<br>
        </p>

        
        <div>
            <a style="display: inline-block;
    margin-bottom: 0; font-weight: normal; text-align: center; vertical-align: middle; cursor: pointer; background-image: none; border: 1px solid #E8B140; white-space: nowrap; padding: 7px 0; font-size: 16px; line-height: 2; border-radius: 3px; min-width: 128px; color: #fff; background-color: #E8B140; -webkit-user-select: none; -moz-user-select: none; user-select: none; outline: none; text-decoration: none;" href="{url}" target="_blank">
                重设密码
            </a>
        </div>

        
        <div style="width: 500px; max-width: 90%;margin: 110px auto; font-size: 14px;">
            <div style="margin: 8px 0;">如果按钮无效，请将以下链接复制到浏览器地址栏完成密码找回。</div>
            <div>
                <a href="{url}" style="color: #E8B140; word-break: break-all" target="_blank">
                	{url}
                </a>
            </div>
        </div>

        
        <div style="padding-bottom: 40px;font-size: 14px;">
            <div style="width: 420px; max-width: 90%;margin: 40px auto;">
                '.$siteConfig->index_seo_description.'
            </div>
            <div>
                <span style="color: #76858c;">邮件联系：</span>
                <a style="color:#E8B140; text-decoration: none;" href="mailto:'.$siteConfig->email.'" target="_blank">
                    '.$siteConfig->email.'
                </a>
            </div>
        </div>

    </div>

    <div style="margin: 16px 0; font-size: 14px;">
        <span style="color: #76858c;">更多可访问</span>
        <a href="'.$siteConfig->url.'" target="_blank" style="color:#E8B140; text-decoration: none;">
            '.$siteConfig->url.'
        </a>
    </div>
</div>';

		return strtr($templateString,$param);
	}

	/**
	 * @brief 验证邮件模板
	 * @param array $param 模版参数
	 * @return string
	 */
	public static function checkMail($param)
	{
		$siteConfig = new Config("site_config");
		//$templateString = "感谢您注册{$siteConfig->name}服务，点击下面这个链接进行邮箱验证并激活您的帐号：<a href='{url}'>{url}</a>。<br />如果不能点击，请您把它复制到地址栏中打开。";
		
		$templateString = '<div style="margin: -15px; padding: 8vh 0 2vh;color: #a6aeb3; background-color: #f7f9fa; text-align: center; font-family:NotoSansHans-Regular,\'Microsoft YaHei\',Arial,sans-serif; -webkit-font-smoothing: antialiased;">
    
    <div style="width: 750px; max-width: 85%; margin: 0 auto; background-color: #fff; -webkit-box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);-moz-box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);">
        
        <div style="padding: 50px 10%; text-align: left; font-size: 16px;line-height: 16px;">
            <a href="'.$siteConfig->url.'" target="_blank" style="vertical-align: top;text-decoration:none;color:#E8B140">
            	<strong>'.$siteConfig->name.'</strong>
            </a>
        </div>

        
        <h1 style="margin:32px auto; max-width: 95%; color: #0e2026;">欢迎加入 '.$siteConfig->name.'</h1>

        <p style="width: 750px; max-width: 90%; margin: 32px auto;padding: 0;">
            在开始使用之前，请确认你的邮箱账号<br>
            请你点击下面的「确认账号」按钮来进行确认。<br>
        </p>

        
        <div>
            <a style="display: inline-block;
    margin-bottom: 0; font-weight: normal; text-align: center; vertical-align: middle; cursor: pointer; background-image: none; border: 1px solid #E8B140; white-space: nowrap; padding: 7px 0; font-size: 16px; line-height: 2; border-radius: 3px; min-width: 128px; color: #fff; background-color: #E8B140; -webkit-user-select: none; -moz-user-select: none; user-select: none; outline: none; text-decoration: none;" href="{url}" target="_blank">
                确认账号
            </a>
        </div>

        
        <div style="width: 500px; max-width: 90%;margin: 110px auto; font-size: 14px;">
            <div style="margin: 8px 0;">如果按钮无效，请将以下链接复制到浏览器地址栏完成激活。</div>
            <div>
                <a href="{url}" style="color: #E8B140; word-break: break-all" target="_blank">
                	{url}
                </a>
            </div>
        </div>

        
        <div style="padding-bottom: 40px;font-size: 14px;">
            <div style="width: 420px; max-width: 90%;margin: 40px auto;">
                '.$siteConfig->index_seo_description.'
            </div>
            <div>
                <span style="color: #76858c;">邮件联系：</span>
                <a style="color:#E8B140; text-decoration: none;" href="mailto:'.$siteConfig->email.'" target="_blank">
                    '.$siteConfig->email.'
                </a>
            </div>
        </div>

    </div>

    <div style="margin: 16px 0; font-size: 14px;">
        <span style="color: #76858c;">更多可访问</span>
        <a href="'.$siteConfig->url.'" target="_blank" style="color:#E8B140; text-decoration: none;">
            '.$siteConfig->url.'
        </a>
    </div>
</div>';
		return strtr($templateString,$param);
	}

	/**
	 * @brief 到货通知邮件模板
	 * @param array $param 模版参数
	 * @return string
	 */
	public static function notify($param)
	{
		$siteConfig = new Config("site_config");
		//$templateString = "尊敬的用户，您需要购买的 <{goodsName}> 现已全面到货，机不可失，从速购买！ <a href='{url}' target='_blank'>立即购买</a>";
		
		$templateString = '<div style="margin: -15px; padding: 8vh 0 2vh;color: #a6aeb3; background-color: #f7f9fa; text-align: center; font-family:NotoSansHans-Regular,\'Microsoft YaHei\',Arial,sans-serif; -webkit-font-smoothing: antialiased;">
    
    <div style="width: 750px; max-width: 85%; margin: 0 auto; background-color: #fff; -webkit-box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);-moz-box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);box-shadow: 0 2px 16px 0 rgba(118,133,140,0.22);">
        
        <div style="padding: 50px 10%; text-align: left; font-size: 16px;line-height: 16px;">
            <a href="'.$siteConfig->url.'" target="_blank" style="vertical-align: top;text-decoration:none;color:#E8B140">
            	<strong>'.$siteConfig->name.'</strong>
            </a>
        </div>

        
        <h1 style="margin:32px auto; max-width: 95%; color: #0e2026;">到货通知</h1>
		
        <p style="width: 750px; max-width: 90%; margin: 32px auto;padding: 0;">
        	您需要购买的 <a style="color:#E8B140;font-weight:700" href="{url}">{goodsName}</a><br>
            现已全面到货，机不可失，从速购买！
        </p>

        
        <div>
            <a style="display: inline-block;
    margin-bottom: 0; font-weight: normal; text-align: center; vertical-align: middle; cursor: pointer; background-image: none; border: 1px solid #FF2626; white-space: nowrap; padding: 7px 0; font-size: 16px; line-height: 2; border-radius: 3px; min-width: 128px; color: #fff; background-color: #FF2626; -webkit-user-select: none; -moz-user-select: none; user-select: none; outline: none; text-decoration: none;" href="{url}" target="_blank">
                马上购买
            </a>
        </div>

        
        <div style="width: 500px; max-width: 90%;margin: 110px auto; font-size: 14px;">
            <div style="margin: 8px 0;">如果按钮无效，请将以下链接复制到浏览器地址栏进行购买。</div>
            <div>
                <a href="{url}" style="color: #E8B140; word-break: break-all" target="_blank">
                	{url}
                </a>
            </div>
        </div>

        
        <div style="padding-bottom: 40px;font-size: 14px;">
            <div style="width: 420px; max-width: 90%;margin: 40px auto;">
                '.$siteConfig->index_seo_description.'
            </div>
            <div>
                <span style="color: #76858c;">邮件联系：</span>
                <a style="color:#E8B140; text-decoration: none;" href="mailto:'.$siteConfig->email.'" target="_blank">
                    '.$siteConfig->email.'
                </a>
            </div>
        </div>

    </div>

    <div style="margin: 16px 0; font-size: 14px;">
        <span style="color: #76858c;">更多可访问</span>
        <a href="'.$siteConfig->url.'" target="_blank" style="color:#E8B140; text-decoration: none;">
            '.$siteConfig->url.'
        </a>
    </div>
</div>';

		return strtr($templateString,$param);
	}
}
