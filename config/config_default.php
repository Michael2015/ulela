<?php
return array(
	'logs'=>array(
		'path'=>'backup/logs/log',
		'type'=>'file'
	),
	'DB'=>array(
		'type'=>'mysqli',
        'tablePre'=>'{TABLE_PREFIX}',
		'read'=>array(
			array('host'=>'{DB_R_ADDRESS}','user'=>'{DB_R_USER}','passwd'=>'{DB_R_PWD}','name'=>'{DB_R_NAME}'),
		),

		'write'=>array(
			'host'=>'{DB_W_ADDRESS}','user'=>'{DB_W_USER}','passwd'=>'{DB_W_PWD}','name'=>'{DB_W_NAME}',
		),
	),
	'interceptor' => array('themeroute@onCreateController','layoutroute@onCreateView','hookCreateAction@onCreateAction','hookFinishAction@onFinishAction'),
	'langPath' => 'language',
	'viewPath' => 'views',
	'skinPath' => 'skin',
    'classes' => 'classes.*',
    'rewriteRule' =>'url',
	'theme' => array('pc' => array('default' => 'default','sysdefault' => 'green','sysseller' => 'green'),'mobile' => array('mobile' => 'default','sysdefault' => 'default','sysseller' => 'default')),
	'timezone'	=> 'Etc/GMT-8',
	'upload' => 'upload',
	'dbbackup' => 'backup/database',
	'safe' => 'cookie',
	'lang' => 'zh_sc',
	'debug'=> false,
	'configExt'=> array('site_config'=>'config/site_config.php'),
	'encryptKey'=>'{ENCRYPTKEY}',
	'authorizeCode' => '',
);
?>