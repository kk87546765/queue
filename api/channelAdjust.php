<?php
/**
 * 广告信息联调
 * Created by PhpStorm.
 * User: lizc
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/Report.php");

ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);
set_time_limit(0);
ini_set('memory_limit','4095M');

class repeatSubmit
{
    protected $db;

    public function index()
    {
        $data = $_REQUEST;   //接收参数
        if (!$data['ver_id']) return ['status' => false, 'msg' => "广告位ID位为空！", 'data' => []];
        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $db = Database::instance($config['adv_system']);

        $sql="SELECT * from tbl_advert_channel_ver_info where id = {$data['ver_id']}";
        $res = $db->query($sql)->get();
        if (!$res) return ['status' => false, 'msg' => "广告位不存在", 'data' => []];

        $param = [
            'ver_id'=>$data['ver_id'],
            'game_id'=>$res[0]['game_id'],
        ];

        isset($data['ip']) && $data['ip'] && $param['ip'] = $data['ip'];
        isset($data['imei']) && $data['imei'] && $param['imei'] = $data['imei'];
        isset($data['oaid']) && $data['oaid'] && $param['oaid'] = $data['oaid'];
        isset($data['idfa']) && $data['idfa'] && $param['idfa'] = $data['idfa'];
        isset($data['device']) && $data['device'] && $param['device'] = $data['device'];
        $db = new storage();
        $common_click_log = $db->getNewCommonLog($param); //数据匹配，获取点击数据
        $db->disconnect();
        if (!$common_click_log) return ['status' => false, 'msg' => "找不到点击数据", 'data' => []];

        $param = $data;
        $param['common_log'] = $common_click_log;

        $model = new Report();
        $res = $model->run($param);
        if ($res) return ['status' => true, 'msg' => "联调成功", 'data' => []];
    }

}


$mod = new repeatSubmit();
$res = $mod->index();
echo json_encode($res);
