<?php

/*require dirname(__FILE__).'/db/CassandraDb.php';

$cass = new CassandraDb();
// $cass->ConnectCluster();
// $res = $cass->CreateKeyspace('test');

$cass->ConnectCluster('click');
$table = 'match_android_channel_imei';
$map = [];

$res = $cass->Select('*', $table, $map);
var_dump($res);exit;*/
require '../model/ChannelCallback.php';
require '../db/RedisModel.class.php';
$redis = RedisModel::instance();
$model = new ChannelCallback();

require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
$config = require(dirname(dirname(__FILE__)) . "/config/config.php");
$db = Database::instance($config['sdklogs']);
$advter_id = $_GET['advter_id'];
$page = $_GET['page'];
$page_num = $_GET['page_num'];
$ver = $_GET['ver'];
$ver = explode(',', $ver);
$ver = implode("','", $ver);
$dataline = $_GET['dateline'];
$begin = ($page - 1) * $page_num;
$device = isset($_GET['device']) ? $_GET['device'] : 'android';

if ($device == 'android'){
    $sql = "select * from 49app_new_register_android_log where open_ver in ('{$ver}') and dateline > $dataline and advter_id = $advter_id limit {$begin},$page_num";
} else {
    $sql = "select * from 49app_ios_member_product_login where  dateline > $dataline and advter_id = $advter_id limit {$begin},$page_num";
}
if (isset($_GET['debug']) && $_GET['debug'] == 1){
    echo $sql;exit;
}
$data = $db->query($sql)->get();
$insert = [];
foreach ($data as $k => $v){
    $insert['stype']       = 'register';
    $insert['advter_id']   = $advter_id;
    $insert['imei']        = isset($v['imei']) ? $v['imei'] : '';
    $insert['idfa']        = isset($v['idfa']) ? $v['idfa'] : '';
    $insert['finish_time'] = time();
    $insert['gid']         = $v['gid'];
    $insert['uid']         = $v['uid'];
    $insert['device']      = $device;
    $insert['ver']         = isset($v['open_ver']) ? $v['open_ver'] : '';
    $insert['channel_code'] = 'gdtnew';
    gdtnew_replay($insert, $model, $redis);
}
//if($_GET['lala'] == 1){
//    $mktqq = $_GET['mktqq'];
//    $redis_key = 'chuwn_test_' . $mktqq; //开发者id_qq账户
//    $redis_access_token = $redis->get($redis_key);
//    var_dump($redis_access_token);exit;
//}elseif($_GET['lala'] == 2){
//    $mktqq = $_GET['mktqq'];
//    $redis_key = 'chuwn_test_' . $mktqq; //开发者id_qq账户
//    $redis_access_token = $redis->set($redis_key, 'test456', 3600);
//    var_dump($redis_access_token);exit;
//}
function gdtnew_replay($v, $model, $redis){
    $action = array(
        'register' => 'ACTIVATE_APP',//激活
        'pay' => 'PURCHASE' //购买
    );
    if(empty($v['gid']) || !in_array($v['stype'],['register','pay'])) return true;

    if ((isset($v['imei']) && $v['imei'] == 'null') || ($v['device'] == 'android' && strpos($v['ver'],'001'))){
        return true;
    }

    if ($v['stype'] == 'pay'){
        if ( isset($v['ver']) && in_array($v['ver'],['dndsxygl016'])){    //回传充值数据
            $action_param = array(
                'type' => 'ANDROID',
                'value' => intval($v['amount']), //单位分
            );
        }elseif($v['device'] == 'ios' && in_array($v['advter_id'], [3947,3943,4175,3941])){
            $action_param = array(
                'type' => 'IOS',
                'value' => intval($v['amount']), //单位分
            );
        } else {
            return true;
        }
    }
    // 获取参数配置
    $conf = $model->getKey($v['channel_code'], $v['gid'], $v['advter_id']);
    if(empty($conf['sign_key']) || empty($conf['encrypt_key'])) return true;

    if($v['device'] == 'ios') {
        $muid = strtoupper(md5($v['idfa']));
    } elseif($v['device'] == 'android') {
        $muid = strtoupper(md5($v['imei']));
    }

    //access_token放redis缓存
    //sign_key 存放 qq账号_qq账号id_refreshtoken
    //encrypt_key 存放   用户行为源类型_行为源id   用户行为源类型1安卓  0ios  2web
    //extrainfo 存放   应用宝游戏id
    list($account_uin, $account_id, $refresh_token) = explode('_', $conf['sign_key']);
    list($action_type, $user_action_set_id) = explode('_', $conf['encrypt_key']);

    $redis_key = 'mktgdt_1107035072_' . $account_uin; //开发者id_qq账户
    $redis_access_token = $redis->get($redis_key);

    if (isset($_GET['debug']) && $_GET['debug'] == 2){
        var_dump($redis_access_token);exit;
    }
    if (isset($_GET['debug']) && $_GET['debug'] == 3){
        var_dump($conf);exit;
    }
    if (isset($_GET['debug']) && $_GET['debug'] == 4){

        $redis_access_token = $_GET['access_token'];
    }
    if (isset($_GET['debug']) && $_GET['debug'] ==5 ){

        $res = $redis->set($redis_key, $_GET['access_token'], 86000);
        var_dump($res);exit;
    }
    if (isset($_GET['debug']) && $_GET['debug'] ==6){
        $refresh_token = 'bee8915e318a498433c177a6b65b34e0';
        $redis_access_token = refresh_token($refresh_token, $account_uin);
        var_dump($redis_access_token);exit;
    }


    if(empty($redis_access_token)){ //刷新
        echo 'empty';exit;
    }



    $get_data = array(
        'access_token' => $redis_access_token,
        'timestamp' => time(),
        'nonce' => substr(md5(microtime() . mt_rand(0, 1000)), 8, 16),
    );
    //上报行为数据
    $uri = "https://api.e.qq.com/v1.0/user_actions/add?";
    $data = array(
        'account_id' => $account_id,
        'user_action_set_id' => $user_action_set_id,
        'actions' => array(0 => array('action_time' => $get_data['timestamp'],'user_id' => array('hash_imei' => $muid,), 'action_type' => $action[$v['stype']], )),//注册 REGISTER  充值 PURCHASE  激活ACTIVATE_APP
    );
    if($v['device'] == 'ios') { //ios上报参数有变
        $data['actions'][0]['user_id'] = array('hash_idfa' => $muid);
    }
    if ($v['stype'] == 'pay'){ //支付将金额带入自定义行为参数中
        $data['actions'][0]['action_param'] = $action_param;
    }

    $url = $uri . http_build_query($get_data);
    $rs = postCurl($url, json_encode($data));

    $rs = json_decode($rs, true);
    if(empty($rs) || 0 !== $rs['code']){
        print_r($rs);
        echo '上报失败';exit;
    }

    if($rs['code'] == 0){
        echo 1;
        return true;
    }
}

function refresh_token($refresh_token = '', $account_uin = '')
{

    $client_id = 1107035072;
    $client_secret = 'efkfnzfrsLiGXfzo';

    $url = 'https://api.e.qq.com/oauth/token?';
    $data = array(
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
    );
    $url .= http_build_query($data);
    $ret = getCurl($url);
    var_dump($ret);
    $ret = json_decode($ret, true);
    if (!empty($ret) && 0 === $ret['code']) {
        $redis_key = 'mktgdt_' . $client_id . '_' . $account_uin; //开发者id_qq账户
        $this->redis->set($redis_key, $ret['data']['access_token'], 86000);

        return $ret['data']['access_token'];
    }

    return false;
}

function postCurl($url, $data='', $time=120){
    $header = array(
        'Content-type: application/json',
    );
    $curl_opt = array(
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_URL => $url,
        CURLOPT_AUTOREFERER => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_CONNECTTIMEOUT => 0,
        CURLOPT_TIMEOUT => $time,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER=> 0, // 跳过证书检查
        CURLOPT_SSL_VERIFYHOST=> 0  // 从证书中检查SSL加密算法是否存在
    );

    $ch = curl_init();
    curl_setopt_array($ch, $curl_opt);
    $contents = curl_exec($ch);
    curl_close($ch);

    return $contents;
}


function getCurl($url, $time = 120, $http_code = false)
{
    $curl_opt = array(
        CURLOPT_URL => $url,
        CURLOPT_AUTOREFERER => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_CONNECTTIMEOUT => 0,
        CURLOPT_TIMEOUT => $time,
        CURLOPT_SSL_VERIFYPEER => 0, // 跳过证书检查
        CURLOPT_SSL_VERIFYHOST => 0  // 从证书中检查SSL加密算法是否存在
    );

    $ch = curl_init();
    curl_setopt_array($ch, $curl_opt);
    $contents = curl_exec($ch);
    if ($http_code) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($contents);
        return $http_code;
    }
    curl_close($ch);

    return $contents;
}
//$array = array(
//    'stype' => 'register', //pay
//    'advter_id' => 4987,
//    'imei' => '78656D5A-EDCB-4716-8DB8-01505681C2EC',
//    'finish_time' => time(),
//    'gid' => '100361',
//    'ip' => '61.135.152.130',
//    'device' => 'ios',
//    'ver' => '',
//    'uid' => '123456789',
//    'deviceinfo' => '',
//    'systeminfo' => '',
//    'netinfo' => '',
//    'screen' => '',
//    'appstore_id' => '100361',
//    'channel_code' => 'zhihu',
//    'idfa' => '78656D5A-EDCB-4716-8DB8-01505681C2EC',
//);
//$res = $model->zhihu($array);
//var_dump($res);


