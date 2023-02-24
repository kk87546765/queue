<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 15:27
 * 运行脚本 php handTask.php "2017-10-19 06:00:00"
 */
require_once(dirname(dirname(__FILE__)) . "/model/Handle.php");

if(isset($argv[1]) || isset($_GET['time']))
{
    $date_h = isset($argv[1]) ? $argv[1] : $_GET['time'] ;
    $time = strtotime($date_h);
}else{
    $time = null;
}

$task = new handle();
$task->run($time);