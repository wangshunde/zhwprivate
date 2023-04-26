<?php
/**
 * 程序入口文件
 * 
 */

// 检测PHP环境
if(PHP_VERSION < '5.2.0') die('Rquire PHP > 5.2.0');

//定义当前的网站物理路径
define('WWW_ROOT',dirname(__FILE__).'/');

require './configs/web_config.php';

require COREFRAME_ROOT.'core.php';


?>