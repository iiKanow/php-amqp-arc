<?php
// namespace app\controller;

// require_once(__DIR__.'/Amqp.php');
// $amqp = new Amqp();
// $amqp->init();


//判断是否cli请求
function is_cli(){
	return preg_match("/cli/i", php_sapi_name()) ? true : false;
}


if (is_cli()) {
	$class = $_SERVER['argv'][1];
	$func = $_SERVER['argv'][2];
} else {
	$class = $_GET['class'];
	$func = $_GET['func'];
}
require_once(__DIR__.'/'.$class.'.php');
if (!class_exists($class)) exit('类文件不存在');
if (!method_exists($class, $func)) exit('方法未定义');
$consumer = new $class();
// var_dump($consumer);die;
call_user_func(array($consumer, $func));
