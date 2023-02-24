<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/31
 * Time: 16:59
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");

class twModel{

    protected $db,$redis,$time,$today,$yesterday,$s_time,$e_time;
//    protected $url =  'http://47.99.181.86/log.gif?'; //测试地址
    protected $url =  'http://zw-datahub.zeda1.com/log.gif?';

    public function __construct()
    {
        $this->db = Database::instance();
        $this->redis = RedisModel::instance();
        $this->storage = new storage();
        $this->s_time = isset($_GET['time']) ? strtotime($_GET['time']) : time();
        $this->e_time = $this->s_time - 200;
        $this->today = date('Y-m-d',$this->s_time);
        $yesterday = $this->s_time - 3600*24;
        $this->yesterday = date('Y-m-d',$yesterday);
    }


    public function curl($url, $params,$data, $header = [], $get = false, $timeout = 120, $response_time_out = 120, $stype = '')
    {
        $ch = curl_init();
        //对json进行处理
        if (!$get) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            $url .= empty($params) ? '' :  http_build_query($params);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //定义请求类型
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $response_time_out);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $returnTransfer = curl_exec($ch);
        if ($returnTransfer === false) {
            $errno = curl_errno($ch);
            $error_message = curl_strerror($errno);
            $error = $url."cURL error ({$errno}):\n {$error_message}";
            echo $error;
            if ($stype != 'repeat'){
                $this->ins_db_log($data,$stype,$url,$error,0);
            }
            return false;
        } else {
            if ($stype == 'repeat'){
                $sql = "update data_collection.tbl_data_report_log set callback_status = 1 where  `channel_code` = 'TW' and callback_status = 0  and id = {$data['id']}";
                $this->db->query($sql);
            }
        }
        curl_close($ch);
        return $returnTransfer;
    }

    public function ins_db_log($data,$type, $callback_url, $callback_result, $callback_status)
    {
        $callback_result = is_array($callback_result) ? json_encode($callback_result) : $callback_result;
        $stype = ['register' => 1, 'pay' => 2, 'install' => 3, 'open' => 4, 'login' => 5 ,'game'=>6,'cost'=>7,'ver'=>8];

        $data['dateline'] = time();
        $data['channel_code'] = 'TW';
        $data['callback_url'] = $callback_url;
        $data['callback_result'] = $callback_result;
        $data['callback_status'] = $callback_status == 200 ? 1 : 0;
        $data['stype'] = $stype[$type];

        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $db = Database::instance($config['data_collection']);
        $validate = array('dateline', 'stype', 'ver_id','channel_ver','channel_oaid','channel_idfa','sign_key', 'imei', 'idfa','oaid','android_id', 'game_id', 'uid', 'ip', 'device', 'channel_code', 'callback_url', 'callback_result', 'callback_status', 'order_id');
        $save_data = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $validate)) {
                $save_data[$k] = !empty($v) ? $v : '';
            }
        }
        if (!empty($save_data)) {
            $res = $db->table('data_collection.tbl_data_report_log')->insert($save_data);
        } else {
            echo $callback_url;
        }
    }

}