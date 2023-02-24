<?php
/**
 * 广告信息联调
 * Created by PhpStorm.
 * User: lizc
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");

class submitTw
{
    protected $db ,$storage ,$redis;
//    protected $url = 'http://47.99.181.86/log.gif?'; //测试地址
    protected $url =  'http://zw-datahub.zeda1.com/log.gif?';

    public function index($type,$data,$order_info = [])
    {
        $this->redis = RedisModel::instance();
        $this->storage = new storage();
        $time = strtotime('2021-04-13 15:00:00');
        switch ($type){
            case 'act':
                if ($data['dateline'] > $time ) return true;
                $this->act($data); break;
            case 'reg':
                if ($data['dateline'] > $time ) return true;
                $this->reg($data); break;
            case 'login':
                $this->login($data); break;
            case 'pay':
//                $this->pay($data,$order_info); break;
        }

    }

    public function act($data)
    {
        $game_list = $this->storage->getInfo($data['ver_id']);
        $param = [
            'platform'=>'ZW',
            'activity'=>'ods_device_action_log',
            'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
            'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $data['game_id'],
            'action_time'=>$data['dateline'],
            'site_id'=>$data['ver_id'], //广告位id
            'os'=>isset($data['idfa']) && !empty($data['idfa']) ? 'IOS':'安卓',
            'device_id'=>isset($data['idfa']) && !empty($data['idfa']) ? $data['idfa'] : $data['android_id'].'-'.$data['imei'],
            'oaid'=>isset($data['oaid']) ? $data['oaid'] :'',
            'ip'=>$data['ip'],
            'sdk_version'=>$data['sdk_version'],
            'system_version'=> $data['version'],//手机系统版本
            'action_id'=>1
        ];
//        $url = $this->url . http_build_query($param);
//        $rs = fetchUrl($url, json_encode($data),true);
//        $this->ins_db_log($data,'install',$url,$rs,$rs);
        $rs = $this->curl($this->url,$param,$data,[],true,30,30,'install');
    }

    public function reg($data)
    {
        $game_list = $this->storage->getInfo($data['ver_id']);
        $param = [
            'platform'=>'ZW',
            'activity'=>'ods_uid_reg_log',
            'reg_type'=>1,
            'uid'=>$data['uid'],
            'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
            'user_name'=>$data['user_name'],
            'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $data['game_id'],
            'reg_time'=>$data['dateline'],
            'site_id'=>$data['ver_id'], //广告位id
            'os'=>$data['device'] == 1 ? 'IOS':'安卓',
            'device_id'=>$data['device'] == 1 ? $data['idfa'] : $data['android_id'].'-'.$data['imei'],
            'oaid'=>$data['device'] == 1 ? '':$data['oaid'],
            'ip'=>$data['regIp'],
            'sdk_version'=>$data['sdk_version'],
            'device_model'=>isset($data['postData']['data']['phoneModel']) ? $data['postData']['data']['phoneModel'] : '' ,//手机机型
            'device_brand'=>isset($data['postData']['data']['manufacturer']) ? $data['postData']['data']['manufacturer'] : '',//手机厂商
            'system_version'=>isset($data['postData']['data']['androidVersion']) ? $data['postData']['data']['androidVersion'] : '',//手机系统版本
        ];
        $url = $this->url . http_build_query($param);
        $rs = fetchUrl($url, json_encode($data),true);
        $this->ins_db_log($data,'register',$url,$rs,$rs);
    }

    public function login($data)
    {
        $game_list = $this->storage->getInfo($data['ver_id']);
        $user_info = $this->storage->getUserInformation($data['uid'],$data['game_id']);

        $param = [
            'platform'=>'ZW',
            'activity'=>'ods_uid_login_log',
            'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
            'uid'=>$data['uid'],
            'user_name'=>$data['user_name'],
            'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $data['game_id'],
            'login_time'=>$data['login_time'],
            'reg_time'=>isset($user_info['dateline']) ? $user_info['dateline'] : $data['login_time'],
            'site_id'=>$data['ver_id'], //广告位id
            'os'=>$data['device'] == 1 ? 'IOS':'安卓',
            'device_id'=>$data['device'] == 1 ? $data['idfa'] : $data['android_id'].'-'.$data['imei'],
            'oaid'=>$data['device'] == 1 ? '':$data['oaid'],
            'ip'=>$data['ip'],
            'sdk_version'=>$data['sdk_version'],
            'system_version'=> $data['android_version'],//手机系统版本
        ];
//        $url = $this->url . http_build_query($param);
//        $rs = fetchUrl($url, json_encode($data),true);
        $rs = $this->curl($this->url,$param,$data,[],true,30,30,'login');
    }
    public function pay($data,$order_list)
    {
        $game_list = $this->storage->getInfo($order_list['sonChannel']);
        $user_info = $this->storage->getUserInformation($order_list['Uid'],$order_list['gameId']);
        if (!$user_info) $user_info = $this->storage->getLoginUserInfo($order_list['Uid'],$order_list['gameId'],$order_list['sonChannel']);
        $param = [
            'platform'=>'ZW',
            'activity'=>'ods_pay_order_log',
            'uid'=>$order_list['Uid'],
            'agent_id'=>isset($game_list['qd_id']) ? $game_list['qd_id'] : 0,
            'user_name'=>$order_list['userName'],
            'game_id'=>isset($game_list['game_sub_id']) ? $game_list['game_sub_id'] : $data['game_id'],
            'device_id' => $data['device'] == 1 ? $user_info['idfa'] : $user_info['android_id'].'-'.$user_info['imei'],
            'server_id'=>$order_list['serverId'],
            'server_name'=>$order_list['serverName'],
            'role_name'=>$order_list['roleName'],
            'role_id'=>$order_list['roleID'],
            'role_level'=>$order_list['roleLevel'],
            'pay_money'=>$order_list['payMoney'],
            'cp_order_id'=>$order_list['cpOrder'],
            'order_id'=>$order_list['orderId'],
            'order_status_id'=>1,
            'product_id'=>$order_list['goodsID'],
            'product_name'=>$order_list['goodsName'],
            'pay_time'=>$order_list['paySucTime'],
            'reg_time'=>$user_info['dateline'],
            'site_id'=>$order_list['sonChannel'], //广告位id
            'os'=>$data['device'] == 1 ? 'IOS':'安卓',
            'system_version'=>$user_info['version'],
            'oaid'=>$user_info['oaid'],
            'sdk_version'=>$user_info['sdk_version'],
            'pay_way_id'=>$order_list['payWay'],
            'ip'=> long2ip($order_list['orderIp']),
        ];
        $url = $this->url . http_build_query($param);
        $rs = fetchUrl($url, json_encode($data),true);
        $this->ins_db_log($data,'pay',$url,$rs,$rs);

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
            $this->ins_db_log($data,$stype,$url,$error,0);
        } else {
            curl_close($ch);
        }
        return $returnTransfer;
    }
}


