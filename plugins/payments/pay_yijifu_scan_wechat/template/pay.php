<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<title>微信支付</title>
	<style type='text/css'>
		.main{margin-left: auto;margin-right: auto;margin-top:20px;margin-bottom:20px;}
		body{TEXT-ALIGN: center;}
	</style>
</head>

<body>
	<h2>请使用微信扫一扫进行支付</h2>

	<div class="main">
		<img width="300" src="<?php echo $sendData['code_img'];?>" />
	</div>

	<a href="<?php echo $sendData['url'];?>">已经支付</a>
</body>

</html>