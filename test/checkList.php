<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
require_once dirname(dirname(__FILE__)) . "/model/Queue.php";
require_once dirname(dirname(__FILE__)) . "/db/RedisModel.class.php";

/*查看当前队列的数据情况*/

$redis = RedisModel::instance();

echo "<pre>";
$num = isset($_GET['num']) ? $_GET['num'] : 100;

$key = 'clickList';
$res_list = getDisList($redis,$key,$num);
print_r($res_list);

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