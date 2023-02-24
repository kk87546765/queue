<?php
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/CallbackModel.php");


ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);
set_time_limit(0);
ini_set('memory_limit','4095M');

/*$gid=$_GET['gid'];
$redisSid=$_GET['sid'];
echo "sid:".$redisSid.":";
//$prodt_id=getProductId($gid,$redisSid);

$storage=new storage();
$prodt_id=$storage->getProductId((int)$gid);

echo $prodt_id;

/**
 * 获取游戏的产品id
 * @param $gid
 */
$access_token = [];
$redis = RedisModel::instance();

$account = isset($_GET['account']) ? $_GET['account'] : '';

$CallbackModel = new CallbackModel();

if (isset($_GET['debug']) && $_GET['debug'] =='gettoken'){
    $access_token = $CallbackModel->getGdtCacheToken($account);
}

if (isset($_GET['debug']) && $_GET['debug'] == 'refresh'){
    $access_token = $CallbackModel->gdtRefreshToken($account);
}
var_dump($account,$access_token);exit;

//$redis->del('WechatAction');

var_dump($redis->hGetAll('WechatAction'));
var_dump($redis->hget('WechatAction','WechatAction1110004492'));
var_dump($redis->hget('WechatAction','WechatAction1110005070'));

exit;
function getProductId($gid,$redisSid){
    $db = Database::instance();
    $redis = RedisModel::instance($redisSid);

    $key="clicksys:prodt:game:".(int)$gid;

    $prodt_id = $redis->get($key);
    if((int)$prodt_id>0){
        echo "cache:";
        return (int)$prodt_id;
    }

    $table="yunying.tbl_game";
    if($gid>=100000){//ios游戏
        $table="fx_advios.tbl_game";
    }

    $prodt_id=0;
    $res=$db->query("select prodt_id from ".$table." where game_id=".(int)$gid)->get();
    if($res && isset($res[0]['prodt_id'])){
        $prodt_id=$res[0]['prodt_id'];
        $redis->set($key,$prodt_id);
    }
    return $prodt_id;
}
?>