<?php
defined('WWW_ROOT') or exit('No direct script access allowed');

/**
 * 核心文件
 */ 
define('VERSION','3.0.4');

$GLOBALS = array();
define('SYSTEM_NAME','wsdcms');
define('IN_WZ',true);

if(ERROR_REPORT==1){
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} elseif(ERROR_REPORT==0){
    error_reporting(0);
} else{
    error_reporting(E_ALL);
}

ini_set('display_errors',1); //开启php显示详细错误
/**
 * 当PHP程序执行完成后，自动执行register_shutdown_function函数，该函数需要一个参数，用来指定由谁处理这些后续的工作。 
 * 程序执行完成，分为以下几种情况：
 * 第一种：php代码执行过程中发生错误
 * 第二种：php代码顺利执行成功
 * 第三种：php代码运行超时
 * 第四种：页面被用户强制停止
 * 
 * 使用步骤
 * 1.自定义一个类
 * 2.引入注册函数  
 * 例：
 * class CustomHandle {
 *      public function systemError(){}
 * }
 * register_shutdown_function(array('CustomHandle','systemError'))
 * 
 *  */
register_shutdown_function('running_fatal'); //注册在关闭时执行的函数
set_error_handler('log_error');//函数设置用户自定义的错误处理函数
set_exception_handler('log_exception');//函数设置用户自定义的异常处理函数


$GLOBALS['_startTime'] = microtime(true);//开始运行时间


if(version_compare(PHP_VERSION,'5.4.0','<')){
    ini_set('magic_quotes_runtime',0);//如果启用了 magic_quotes_runtime，大多数返回任何形式外部数据的函数，包括数据库和文本段将会用反斜线转义引号。 如果启用了magic_quotes_sybase，单引号会被单引号转义而不是反斜线。5.4移除
    //define('MAGIC_QUOTES_GPC',get_magic_quotes_gpc() ? 1 : 0);//get_magic_quotes_gpc()自 PHP 7.4.0 起弃用，自 PHP 8.0.0 起移除
}else{
    define('MAGIC_QUOTES_GPC',0);
}


define('IS_WIN',strstr(PHP_OS,'WIN') ? 1: 0); //定义操作系统类型
define('IS_CLI',PHP_SAPI=='cli'? 1 : 0);
define('SYS_TIME',time()); //定义系统时间戳
define('HTTP_REFERER',isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');  //获取前一页面的 URL 地址


//设置本地时区
date_default_timezone_set(TIME_ZONE);
//输出页面字符集
header('Content-type: text/html; charset='.CHARSET);
if(extension_loaded("zlib") && !ob_start("ob_gzhandler")) ob_start();

//将GET，POST参数全部转给GLOBALS，然后注销get/post
set_globals();
load_function('common');
autoload();
$_POST['SUPPORT_MOBILE'] = SUPPORT_MOBILE;      //这里为什么不用GLOBALS呢？



/**
 * 加载类函数
 * @param string $class 类名称
 * @param string $m 模块英文名
 * @param string $param 初始化参数
 * @return class
 */
function load_class($class, $m = 'core', $param = NULL){
    static $static_class = array();

    //判断是否存在类，存在则直接返回
    if(isset($static_class[$class])){
        return $static_class[$class];
    }
    $name = FALSE;
    
    if(file_exists(COREFRAME_ROOT.'app/'.$m.'/libs/class/'.$class.'.class.php')){
        $name = 'WUZHI_'.$class;
        if(class_exists($name, FALSE) === FALSE){
            require_once(COREFRAME_ROOT.'app/'.$m.'/libs/class/'.$class.'.class.php');
        }
    }
    //如果存在扩展类，则初始化扩展类
    if($class != 'application' && $class != 'admin' && file_exists(COREFRAME_ROOT.'app/'.$m.'/libs/class/EXT_'.$class.'.class.php')){
        $name = 'EXT_'.$class;
        if(class_exists($name, FALSE) === FALSE){
            require_once(COREFRAME_ROOT.'app/'.$m.'/libs/class/EXT_'.$class.'.class.php');
        }
    }

    if($name === FALSE){
        $full_dir = '';
        if(OPEN_DEBUG) $full_dir = COREFRAME_ROOT.'app/'.$m.'/libs/class/';
        echo 'Unable to locate the specified class: '.$full_dir.$class.'.class.php';
        exit();
    }

    $static_class[$class] = isset($param) ? new $name($param) : new $name;
    return $static_class[$class];

}
/**
 * 加载类函数
 * @param string $filename 名称
 * @param string $m 模块英文名
 */
function load_function($filename, $m = 'core'){
    static $static_func = array();
    //判断是否粗加载过，存在则直接返回
    if(isset($static_func[$filename])){
        return true;
    }
    require_once(COREFRAME_ROOT.'app/'.$m.'/libs/function/'.$filename.'.function.php');
}

/**
 * 加载类函数
 * @param string $filename 文件名称
 * @param string $param 参数名称
 * @return array|string
 */
function get_config($filename,$param = ''){
    static $config;
    if(isset($config[$filename])) return $param ? $config[$filename][$param] : $config[$filename];
    if(file_exists(WWW_ROOT.'configs/'.$filename.'.php')){
        $config[$filename] = include WWW_ROOT.'configs/'.$filename.'.php';
    }else{
        $full_dir = '';
        if(OPEN_DEBUG) $full_dir = WWW_ROOT.'configs/';
        echo 'Unable to locate the specified config: '.$full_dir.$filename.'.php';
        exit();
    }
    return $param ? $config[$filename][$param] : $config[$filename];
}




function autoload(){
    $path = COREFRAME_ROOT.'extend/function/*.func.php';
    $auto_funcs = glob($path);//返回一个包含匹配指定模式的文件名或目录的数组
    if(!empty($auto_funcs) && is_array($auto_funcs)){
        foreach ($auto_funcs as $func_path) {
            include $func_path;
        }
    }
}

/**
 * 检查GLOBALS中是否存在变量
 * @param $key
 * @param int $check_sql 是否sql_replace过滤
 * @return mixed|string
 */
function input($key,$check_sql = 1){
    if(isset($GLOBALS[$key])){
        return $check_sql ? sql_replace($GLOBALS[$key]) : $GLOBALS[$key];
    }else{
        return '';
    }
}

/**

 * 过滤SQL关键字，mysql入库字段过滤

 * @param $val 要过滤的字符串

 * @return mixed

 */
function sql_replace($val){
	$val = str_replace("\t", '', $val);
	$val = str_replace("%20", '', $val);
	$val = str_replace("%27", '', $val);
	$val = str_replace("*", '', $val);
	$val = str_replace("'", '', $val);
	$val = str_replace("\"", '', $val);
	$val = str_replace("/", '', $val);
	$val = str_replace(";", '', $val);
	$val = str_replace("#", '', $val);
	$val = str_replace("--", '', $val);
	$val = addslashes($val);
	return $val;
}

function set_globals(){
    if(isset($_GET)){
        foreach ($_GET as $_key => $_value) {
            $GLOBALS[$_key] = gpc_stripslashes($_value);
            $GLOBALS[$_key] = strip_tags($_value);//剥去字符串中的 HTML、XML 以及 PHP 的标签
        }
        $_GET = array();
    }
    if(isset($_POST)){
        foreach ($_POST as $_key => $_value) {
            $GLOBALS[$_key] = gpc_stripslashes($_value);
            $GLOBALS[$_key] = strip_tags($_value);//剥去字符串中的 HTML、XML 以及 PHP 的标签
        }
        $_POST = array();
    }
    if(isset($GLOBALS['page'])){
        $GLOBALS['page'] = max(intval($GLOBALS['page']),1);
        $GLOBALS['page'] = min(intval($GLOBALS['page'],100000000));
    }else{
        $GLOBALS['page'] = 1;
    }
    $_COOKIE = gpc_stripslashes($_COOKIE);
    $GLOBALS['_groupid'] = get_cookie('groupid');

}

function p_stripslashs($string){
    if(!is_array($string)){
        return stripslashes($string);//函数删除由 addslashes() 函数添加的反斜杠
    }
    return array_map('p_stripslashs',$string);//将用户自定义函数作用到数组中的每个值上，并返回用户自定义函数作用后的带有新的值的数组
}

function gpc_stripslashes($data){
    if(MAGIC_QUOTES_GPC){
        return p_stripslashs($data);
    }else{
        return $data;
    }
}

/**
 * 设置cookie
 * @param string $string     变量名
 * @param string $value   变量值
 * @param int $time    过期时间
 * @param bool $encrypt = true    是否加密存储
 */
function set_cookie($string,$value = '', $time = 0, $encrypt = true){
    $time = $time > 0 ? $time : ($value == '' ? SYS_TIME - 3600 : 0);
    $s = $_SERVER['SERVER_PORT'] == '443' ? 1 : 0;
    $string = COOKIE_PRE.$string;
    if($encrypt) $value = encode($value);
    setcookie($string, $value, $time, COOKIE_PATH, COOKIE_DOMAIN, $s);
}

/**
 * 获取通过 set_cookie 设置的 cookie 变量
 * @param string $string 变量名
 * @param string $default 默认值
 * @return mixed 成功则返回cookie 值，否则返回 false
 */
function get_cookie($string, $default = '', $encrypt = true){
    $string = COOKIE_PRE.$string;
    return isset($_COOKIE[$string]) ? decode($_COOKIE[$string]) : $default;
}

/**
 *
 * @param string $string 变量名
 * @return array
 */
function p_unserialize($string) {
    if(($ret = unserialize($string)) === false) {
        $ret = unserialize(stripslashes($string));
    }
    return $ret;
}
    
/**
 * 加密字符串
 *
 * @param $string
 * @param string $key
 */
function encode($string,$key = ''){
    $encode = load_class('encrypt');
    return $encode->encode($string,$key);
}
/**
 * 解密字符串
 *
 * @param $string
 * @param string $key
 */
function decode($string,$key = '') {
    $encode = load_class('encrypt');
    return $encode->decode($string,$key);
}


/**
 * Error handler, passes flow over the exception logger with new ErrorException.
 */
function log_error( $num, $str, $file, $line, $context = null ) {

    if(ERROR_REPORT<2 && $num==8) return '';
    log_exception( new ErrorException( $str, 0, $num, $file, $line ));
}


/**
* Uncaught exception handler.
 */
function log_exception( Exception $e) {
    $file = str_replace(rtrim(COREFRAME_ROOT,'/'),'coreframe->',$e->getFile());
    $file = str_replace(rtrim(WWW_ROOT,'/'),'www->',$file);
    $file = str_replace(rtrim(CACHE_ROOT,'/'),'caches->',$file);
    $data = array();
    $data['type'] = get_class($e);//get_class() 返回对象的类名
    $data['msg'] = $e->getMessage();
    $data['file'] = $file;
    $data['line'] = $e->getLine();
    $data['version'] = VERSION;
    $data['php_version'] = PHP_VERSION;
    $data['referer'] = URL();

    if (ERROR_REPORT) {
        if(IS_CLI==0) {
            print "<!DOCTYPE html><div style='text-align: center;'>";
            print "<h5 style='color: rgb(190, 50, 50);'>WuzhiCMS Exception Occured:</h5>";
            print "<table style='width: 800px; display: inline-block;'>";
            print "<tr style='background-color:rgb(230,230,230);text-align:left;'><th style='width: 80px;'>Type</th><td>" . $data['type'] . "</td></tr>";
            print "<tr style='background-color:rgb(240,240,240);text-align:left;'><th>Message</th><td>{$data['msg']}</td></tr>";
            print "<tr style='background-color:rgb(230,230,230);text-align:left;'><th>File</th><td>{$file}</td></tr>";
            print "<tr style='background-color:rgb(240,240,240);text-align:left;'><th>Line</th><td>{$data['line']}</td></tr>";
            print "<tr style='background-color:rgb(230,230,230);'><th colspan='2'><a href='http://www.wuzhicms.com/index.php?m=help&f=logerror&msg={$data['msg']}&file={$data['file']}&line={$data['line']}' target='_blank'>Need Help?</a></th></tr>";
            print "</table></div>";
        } else {
            print "------------- WuzhiCMS Exception Occured:------------- \r\n";
            print "Type: {$data['type']} \r\n";
            print "Message: {$data['msg']} \r\n";
            print "File: {$data['file']} \r\n";
            print "Line: {$data['line']} \r\n";
            print date('Y-m-d H:i:s')."\r\n";
        }

        if(OPEN_DEBUG) exit();
    } else {
        $message = "Time: " . date('Y-m-d H:i:s') . "; Type: " . $data['type'] . "; Message: {$e->getMessage()}; File: {$data['file']}; Line: {$data['line']};";
        @file_put_contents(CACHE_ROOT. "logs/error-".CACHE_EXT.'-'.date("ym").".log", $message . PHP_EOL, FILE_APPEND );
    }
}

/**
 * 完整url链接
 *
 * @return string
 */
function URL(){
    $http_url = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    if(isset($_SERVER['HTTP_HOST'])){
        $http_url .=$_SERVER['HTTP_HOST'];
    }else{
        $http_url .= $_SERVER["SERVER_NAME"];
    }
    if (isset($_SERVER['REQUEST_URI'])) {
		$http_url .= $_SERVER['REQUEST_URI'];
	} else {
		if (isset($_SERVER['PHP_SELF'])) {
			$http_url .= $_SERVER['PHP_SELF'];
		} else {
			$http_url .= $_SERVER['SCRIPT_NAME'];
		}
		if (isset($_SERVER['QUERY_STRING'])) {
			$http_url .= $_SERVER['QUERY_STRING'];
		} else {
			$http_url .= isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
		}
	}
	return $http_url;
}


/**
 * Checks for a fatal error, work around for set_error_handler not working on fatal errors.
 */
function running_fatal() {
    $error = error_get_last();
    if($error && ($error["type"] == E_ERROR || $error["type"] == 4)) log_error( $error["type"], $error["message"], $error["file"], $error["line"] );
}
?>