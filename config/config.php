<?php return array (
  'logs' => 
  array (
    'type' => 'db',
  ),
  'DB' => 
  array (
    'type' => 'mysqli',
    'tablePre' => 'iwebshop_',
    'read' => 
    array (
      0 => 
      array (
        'host' => '127.0.0.1:3306',
        'user' => 'root',
        'passwd' => 'Ulela123',
        'name' => 'iwebshop',
      ),
    ),
    'write' => 
    array (
      'host' => '127.0.0.1:3306',
      'user' => 'root',
      'passwd' => 'Ulela123',
      'name' => 'iwebshop',
    ),
  ),
  'interceptor' => 
  array (
    0 => 'themeroute@onCreateController',
    1 => 'layoutroute@onCreateView',
    2 => 'plugin',
  ),
  'langPath' => 'language',
  'viewPath' => 'views',
  'skinPath' => 'skin',
  'classes' => 'classes.*',
  'rewriteRule' => 'url',
  'theme' => 
  array (
    'pc' => 
    array (
      'sysdefault' => 'blue',
      'sysseller' => 'default',
      'xiaomi' => 'default',
    ),
    'mobile' => 
    array (
      'sysdefault' => 'blue',
      'sysseller' => 'default',
      'mobile2' => 'default',
    ),
  ),
  'skin' => 
  array (
    'pc' => 'default',
    'mobile' => 'default',
  ),
  'timezone' => 'Etc/GMT-8',
  'upload' => 'upload',
  'dbbackup' => 'backup/database',
  'safe' => 'session',
  'lang' => 'zh_sc',
  'debug' => '2',
  'configExt' => 
  array (
    'site_config' => 'config/site_config.php',
  ),
  'encryptKey' => '37baaf2d328cdec0bd6a6173472pp0a2',
  'authorizeCode' => '',
)?>
