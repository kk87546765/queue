<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
//die('error');

define('ROOT_PATH',dirname(__FILE__));
define('LIST_DISASTER','clickDis');

require_once(dirname(dirname(__FILE__)) . "/model/Queue.php");
$queue   = new queue();
$list = $queue->getDisList('clickList', 0, false);
print_r($list);exit;
$res = $queue->popList();

print_r($res);exit;





$op = isset($_GET['op']) ? trim($_GET['op']) : '';
if ($op == 'getRedis'){
    // 读取缓存
    $reids = RedisModel::instance();
    $key = isset($_REQUEST['key']) ? trim($_REQUEST['key']) : date('Y-m-d-H',time());
    $key_bak = LIST_DISASTER.$key;

    $list = $reids->lranges($key_bak, 0, -1);

    var_dump($list);
    exit();

}elseif($op == 'callback'){

    // 测试回调
    $testAction = 'douyu';

    $report = new ChannelCallback();

    $data = [
        'stype'=>isset($_REQUEST['stype']) ? trim($_REQUEST['stype']) : '',
        'advter_id'=>isset($_REQUEST['advter_id']) ? trim($_REQUEST['advter_id']) : 0,
        'idfa'=>isset($_REQUEST['idfa']) ? trim($_REQUEST['idfa']) : '',
        'idfv'=>isset($_REQUEST['idfv']) ? trim($_REQUEST['idfv']) : '',
        'finish_time'=>isset($_REQUEST['finish_time']) ? trim($_REQUEST['finish_time']) : time(),
        'gid'=>isset($_REQUEST['gid']) ? trim($_REQUEST['gid']) : 0,
        'ip'=>isset($_REQUEST['ip']) ? trim($_REQUEST['ip']) : '127.0.0.1',
        'device'=>isset($_REQUEST['device']) ? trim($_REQUEST['device']) : '',
        'uid'=>isset($_REQUEST['uid']) ? trim($_REQUEST['uid']) : '',
        'appstore_id'=>isset($_REQUEST['appstore_id']) ? trim($_REQUEST['appstore_id']) : 0,
        'channel_code'=>isset($_REQUEST['channel_code']) ? trim($_REQUEST['channel_code']) : 0,
    ];

    $rs = $report->$testAction($data);
    var_dump($rs);
    exit();
}
