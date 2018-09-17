<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>选择支付银行</title>
	<link rel="stylesheet" href="http://cdn.bootcss.com/bootstrap/3.3.4/css/bootstrap.min.css">
</head>

<body>
	<form action='<?php echo $postUrl;?>' method='post'>
		<div class="container">
			<div class="panel panel-primary" style="margin-top:10px">
				<div class="panel-heading">
					<h3 class="panel-title">选择支付银行</h3>
				</div>

				<div class="panel-body">
					<div class="row">
						<?php foreach($bankList as $key => $val)
						{
							$fileName = $urlPath.'/'.$key.'.gif';
						?>
						<div class="col-md-2">
							<div class="radio">
							<label>
								<input name='defaultbank' type='radio' title='<?php echo $val;?>' value='<?php echo $key;?>' />
								<img src='<?php echo $fileName;?>' title='<?php echo $val;?>' />
							</label>
							</div>
						</div>
						<?php }?>
					</div>

					<div class="row">
						<div class="col-md-12">
							<input class="btn btn-success" type="submit" value="立即支付" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</body>

</html>