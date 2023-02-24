<?php


ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);
set_time_limit(0);
require dirname(dirname(__FILE__)).'/db/RedisModel.class.php';

class gdtTokenCache{

    public static function tokenCache()
    {
        $data = $_REQUEST;
        if (!$data)  die('error');

        $redis = RedisModel::instance();

        $key = "cache:gdt:";

        foreach ($data as $k=>$v){
            $redis->set($key.$k,$v,600);
        }
    }
}
$res = gdtTokenCache::tokenCache();
