<?php
/**
 * 统一执行脚本地址,每小时执行一次
 * url: http://clicksys.queue.49app.com/cron/callsp.php
 * Created by PhpStorm.
 * User: lizc
 * Date: 2020/01/17
 * Time: 17:05
 */


ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);
set_time_limit(0);
ini_set('memory_limit', '4095M');


require_once((dirname(__FILE__)) . "/resetAdvterId.php");

$resetAdvterId = new resetAdvterId(); //广告数据重新归因
$resetAdvterId->index();
