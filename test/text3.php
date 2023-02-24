<?php


require_once dirname(dirname(__FILE__)).'/model/ChannelCallback.php';
require_once dirname(dirname(__FILE__)).'/model/CallbackModel.php';
require_once dirname(dirname(__FILE__)).'/db/RedisModel.class.php';

$cache = isset($_GET['cache']) ? true : false;
$token = isset($_GET['token']) ? $_GET['token'] : false;
$redis = RedisModel::instance();
$wechat_cache = 'wechat_token:';


if ($_GET['debug'] == 1){
    $CallbackModel = new CallbackModel();
    $res = $CallbackModel->getWechatAccessToken($cache);
} elseif ($_GET['debug'] == 2) {
    $res = $redis->get($wechat_cache);
} elseif ($_GET['debug'] == 3){
    $this->redis->set($wechat_cache,$token,3600);
}
var_dump($res);

