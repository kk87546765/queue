<?php

ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
ini_set('memory_limit','1024M');
error_reporting(-1);                    //打印出所有的 错误信息
set_time_limit(0);

require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");

class submitTw
{
    protected $db ,$storage ,$redis, $s_time ,$e_time;
//    protected $url = 'http://47.99.181.86/log.gif?'; //测试地址
    protected $url =  'http://zw-datahub.zeda1.com/log.gif?';

    public function index()
    {
        $this->db = Database::instance();
        $this->redis = RedisModel::instance();
        $this->storage = new storage();
        $this->s_time = isset($_GET['time']) ? strtotime($_GET['time']) : time();
        $this->e_time = $this->s_time - 200;

        echo date('Y-m-d H:i:s',time());
        $this->act();
        $this->reg();
        $this->pay();
//        $this->login();
        $this->repeat();
        echo date('Y-m-d H:i:s',time());
    }

    public function act()
    {
        $sql = "SELECT a.* from tbl_activate_log as a where a.dateline >= {$this->e_time} and a.dateline <= {$this->s_time}";
        $this->db->query($sql);
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $game_list = $this->storage->getInfo($v['ver_id']);
            $param = [
                'platform'=>'ZW',
                'activity'=>'ods_device_action_log',
                'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
                'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $v['game_id'],
                'action_time'=>$v['dateline'],
                'site_id'=>$v['ver_id'], //广告位id
                'os'=>$v['game_id'] == 103085 ? 'IOS':'安卓',
                'device_id'=>$v['game_id'] == 103085 ? $v['idfa'] : $v['android_id'].'-'.$v['imei'],
                'oaid'=>isset($data['oaid']) ? $v['oaid'] :'',
                'ip'=>$v['ip'],
                'sdk_version'=>$v['sdk_version'],
                'system_version'=> $v['version'],//手机系统版本
                'action_id'=>1
            ];
            $this->curl($this->url,$param,$v,[],true,30,30,'install');
        }
    }

    public function reg()
    {
        $sql = "SELECT a.* from tbl_register_log as a  where a.dateline >= {$this->e_time} and a.dateline <= {$this->s_time}";
        $this->db->query($sql);
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $game_list = $this->storage->getInfo($v['ver_id']);
            $param = [
                'platform'=>'ZW',
                'activity'=>'ods_uid_reg_log',
                'reg_type'=>1,
                'uid'=>$v['uid'],
                'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
                'user_name'=>$v['user_name'],
                'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $v['game_id'],
                'reg_time'=>$v['dateline'],
                'site_id'=>$v['ver_id'], //广告位id
                'os'=>$v['device'] == 1 ? 'IOS':'安卓',
                'device_id'=>$v['device'] == 1 ? $v['idfa'] : $v['android_id'].'-'.$v['imei'],
                'oaid'=>$v['device'] == 1 ? '':$v['oaid'],
                'ip'=>$v['ip'],
                'sdk_version'=>$v['sdk_version'],
//                'device_model'=>isset($v['postData']['data']['phoneModel']) ? $v['postData']['data']['phoneModel'] : '' ,//手机机型
//                'device_brand'=>isset($v['postData']['data']['manufacturer']) ? $v['postData']['data']['manufacturer'] : '',//手机厂商
//                'system_version'=>isset($v['postData']['data']['androidVersion']) ? $v['postData']['data']['androidVersion'] : '',//手机系统版本
            ];
            $this->curl($this->url,$param,$v,[],true,30,30,'register');
        }

    }

    public function login()
    {
        $sql = "SELECT a.*,d.user_name from tbl_login_log as a 
                LEFT JOIN asgardstudio_admin.games as b on a.game_id = b.gameId 
                LEFT JOIN asgardstudio_admin.games_product as c on b.productId = c.productId
                LEFT join tbl_register_log as d on a.uid = d.uid
                where a.login_time >= {$this->e_time} and a.login_time <= {$this->s_time}  
                and c.cpId = 'tanwan'";
        $this->db->query($sql);
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $game_list = $this->storage->getInfo($v['login_ver_id']);
            $param = [
                'platform'=>'ZW',
                'activity'=>'ods_uid_login_log',
                'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
                'uid'=>$v['uid'],
                'user_name'=>$v['user_name'],
                'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $v['game_id'],
                'login_time'=>$v['login_time'],
                'reg_time'=>$v['dateline'],
                'site_id'=>$v['login_ver_id'], //广告位id
                'os'=>$v['device'] == 1 ? 'IOS':'安卓',
                'device_id'=>$v['device'] == 1 ? $v['idfa'] : $v['android_id'].'-'.$v['imei'],
                'oaid'=>$v['device'] == 1 ? '':$v['oaid'],
                'ip'=>$v['ip'],
                'sdk_version'=>$v['sdk_version'],
                'system_version'=> $v['version'],//手机系统版本
            ];
            $this->curl($this->url,$param,$v,[],true,30,30,'login');
        }
    }

    public function pay()
    {
        $sql = "SELECT * from asgardstudio_order.order_proccess as a where a.paySucTime >= {$this->e_time} and a.paySucTime <= {$this->s_time} and  a.orderResult = 1";
        $this->db->query($sql);
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $game_list = $this->storage->getInfo($v['sonChannel']);
            $user_info = $this->storage->getUserInformation($v['Uid'],$v['gameId']);
            if (!$user_info) $user_info = $this->storage->getLoginUserInfo($v['Uid'],$v['gameId'],$v['sonChannel']);
            $param = [
                'platform'=>'ZW',
                'activity'=>'ods_pay_order_log',
                'uid'=>$v['Uid'],
                'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
                'user_name'=>$v['userName'],
                'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $v['gameId'],
                'device_id' => $v['gameId'] == 103085 ? $user_info['idfa'] : $user_info['android_id'].'-'.$user_info['imei'],
                'server_id'=>$v['serverId'],
                'server_name'=>$v['serverName'],
                'role_name'=>$v['roleName'],
                'role_id'=>$v['roleID'],
                'role_level'=>$v['roleLevel'],
                'pay_money'=>$v['payMoney'],
                'cp_order_id'=>$v['cpOrder'],
                'order_id'=>$v['orderId'],
                'order_status_id'=>1,
                'product_id'=>$v['goodsID'],
                'product_name'=>$v['goodsName'],
                'pay_time'=>$v['paySucTime'],
                'reg_time'=>$user_info['dateline'],
                'site_id'=>$v['sonChannel'], //广告位id
                'os'=>$v['gameId'] == 103085 ? 'IOS':'安卓',
                'system_version'=>$user_info['version'],
                'oaid'=>$user_info['oaid'],
                'sdk_version'=>$user_info['sdk_version'],
                'pay_way_id'=>$v['payWay'],
                'ip'=> long2ip($v['orderIp']),
            ];
            $this->curl($this->url,$param,$v,[],true,30,30,'pay');
        }
    }

    public function repeat(){
        $sql = "SELECT * from data_collection.tbl_data_report_log where  `channel_code` = 'TW' and callback_status = 0 and dateline >=UNIX_TIMESTAMP('2021-04-13')";
        $this->db->query($sql);
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $this->curl($v['callback_url'],[],$v,[],true,30,30,'repeat');
        }
    }

    public function ins_db_log($data,$type, $callback_url, $callback_result, $callback_status)
    {
        $callback_result = is_array($callback_result) ? json_encode($callback_result) : $callback_result;
        $stype = ['register' => 1, 'pay' => 2, 'install' => 3, 'open' => 4, 'login' => 5];

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
            curl_close($ch);
            $error = "cURL error ({$errno}):\n {$error_message}";
            if ($stype != 'repeat'){
                $this->ins_db_log($data,$stype,$url,$error,0);
            }
        } else {
            if ($stype == 'repeat'){
                $sql = "update data_collection.tbl_data_report_log set callback_status = 1 where  `channel_code` = 'TW' and callback_status = 0  and id = {$data['id']}";
                $this->db->query($sql);
            }
            curl_close($ch);
        }
        return $returnTransfer;
    }
}

$mod = new submitTw();
$mod->index();

