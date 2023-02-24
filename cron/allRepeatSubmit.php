<?php
/**
 * 广告信息重新报送
 * Created by PhpStorm.
 * User: lizc
 * Date: 2018/05/30
 * Time: 17:05
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/ActivateModel.php");
require_once(dirname(dirname(__FILE__)) . "/model/Report.php");


ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);
set_time_limit(0);
ini_set('memory_limit','4095M');

class repeatSubmit
{
    protected $db;
    protected $time = 1;

    public function index()
    {
        $callb    = new ChannelCallback();
        $report   = new Report();

        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $this->db = Database::instance($config['users']);

        $time = isset($_GET['stime']) ? strtotime($_GET['stime']) :  time() - 60000;

        $sql="SELECT userId,userAcount,channelId,regGameId,regImei,imei1,imei2,androidId,regTime,regIp
                FROM  asgardstudio_users.reg_record
                WHERE regTime >= '{$time}' ";

        if (isset($_GET['debug']) && $_GET['debug'] == '1'){
            echo $sql;exit;
        }

        $fixed = ['oaid'=>'regImei', 'imei'=>'imei1', 'idfa'=>'regImei', 'android_id'=>'androidId', 'ver_id'=>'channelId', 'game_id'=>'regGameId',];

        echo "<pre>";
        $mem_res = $this->db->query($sql)->get();
        $this->db->disconnect();
        $num = 0;
        echo '---统计开始（'.date('Y-m-d H:i:s').'）---<br>';
        echo '总数据量:'.count($mem_res).'<br>';
        foreach ($mem_res as $k=>$v){
            if ($v['channelId'] == 0 ) continue;
            foreach ($fixed as $key=>$val){
                if (isset($v[$val]))  $v[$key] = $v[$val]; //参数转化
            }

            $device = [
                'oaid'=>$v['regImei'],
                'imei'=>$v['imei1'],
                'idfa'=>$v['regImei'],
                'android_id'=>$v['androidId'],
                'ip'=>long2ip($v['regIp']),
                'ver_id'=>$v['channelId'],
                'game_id'=>$v['regGameId'],
            ];
            $cassmember = new cassmember();
            $match_str = $cassmember->matchChn($device);
            if (!$match_str) continue;

            $v['stype'] = 'register';
            $v['ip'] = long2ip($v['regIp']);
            $v['sign_key'] = $match_str['log_key'];

//            $callback_func = $channel_config['callback_func'];  // 调用渠道回调函数
            $callback_func = 'jrtt';  // 调用渠道回调函数
            try{
                $channel_rs = $callb->$callback_func($v);
            } catch (Exception $e){
                $channel_rs = false;
            }
            if (isset($_GET['debug']) && $_GET['debug'] == '4'){
                var_dump($v,$channel_rs);exit;
            }
        }
        echo '---统计结束（'.date('Y-m-d H:i:s').'）---<br>';
        echo $num;
    }
}


$mod = new repeatSubmit();
$mod->index();
