<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/29
 * Time: 12:03
 */
require_once(dirname(__FILE__) . "/model/Guard.php");

if(isset($argv[1]))
{
    $num = $argv[1];
}else{
    $num = 10;
}

$guard = new guard($num);
$guard->start();