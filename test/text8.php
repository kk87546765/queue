<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
require_once dirname(dirname(__FILE__)) . "/model/Queue.php";
require_once dirname(dirname(__FILE__)) . "/db/RedisModel.class.php";


echo "<br>----------分割线----------<br>";


$redis_66 = RedisModel::instance();

echo "<pre>";
$new_time = $_GET['day'];
$s = $_GET['s'] ? $_GET['s'] : '12';
$new_time = strtotime($new_time);
$day = $new_time + 3600*$s;

$time = date('H',$day);
for($i=1;$i<=$time;$i++){
    $key = 'clickDis'.date('Y-m-d-H',$day-3600*$i);
    $res = getDisList($redis_66,$key);
    echo $key;
    echo "<br>----------66分割线----------<br>";
    print_r($res);
}



function getDisList($redis, $key = null,$num = 100, $debug = false)
{
    if($key == null)
    {
        $key = $this->key_bak;
    }
    if($debug == true)
    {
        echo $key;
    }
    return $redis->lranges($key,0,$num);

}