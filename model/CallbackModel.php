<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/29
 * Time: 11:45
 */
require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/StorageAdv.php");


class CallbackModel
{
    const TIMEOUT = 3;
    const DEBUG = false;
    const SECOND_KEY = 'user_ip';
    const COMMON_CLICK_LOG = 'common_click_log';
    const ADVER_TABLE = 'adver_callback_config';
    const CHANNEL_TABLE = 'channel_callback_config';
    private $redis = '';
    private $scylla = '';
    protected $muid = ['ios' => 'idfa', 'android' => 'imei'];
    protected $stype = ['register' => 1, 'pay' => 2, 'install' => 3, 'open' => 4, 'login' => 5];

    public function __construct()
    {
        $this->redis = RedisModel::instance();
//        $this->scylla = CassandraDb::instance();
    }

    /*
     * 获取后台配置信息
     * @param $channel_code 广告商
     * @param $gid 游戏ID
     * @advter_id  广告位id
     * */
    public function getKey($channel_code, $gid, $advter_id = 0)
    {
        $expires_time = $this->redis->hget('config_expires', 'channel_callback_expires');

        if (empty($expires_time) || $expires_time < time() - 300) {

            $db = Database::instance();
            $db->table('tbl_channel_callback_config', 'adv');
            $db->select(array('condition' => ' where `status`=1'));
            $configs = $db->get();

            $this->redis->del('channel_callback_config');
            foreach ($configs as $key => $value) {
                $key = $value['channel_code'] . '_' . $value['game_id'] . '_' . $value['adver_id'];
                $this->redis->hset('channel_callback_config', $key, json_encode($value));
            }
            $this->redis->hset('config_expires', 'channel_callback_expires', time());
        }

        $key = $channel_code . '_' . $gid . '_0';
        $keyarr = $this->redis->hget('channel_callback_config', $key);

        if (empty($keyarr) && !empty($advter_id)) {
            $key = $channel_code . '_' . $gid . '_' . $advter_id;
            $keyarr = $this->redis->hget('channel_callback_config', $key);
        }

        if (empty($keyarr)) {
            return false;
        } else {
            return json_decode($keyarr, true);
        }
    }



    /*
     * 获取广点通token
     * @param $channel_code 广告商
     * @param $gid 游戏ID
     * @advter_id  广告位id
     * */
    public function getGdtToken($account_id,$cache = true)
    {
        if(empty($account_id)) return false;

        if ($cache == false){
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $model = Database::instance($config['adv_system']);
            $model->query("SELECT * from adv_system.tbl_channel_accounts where id = {$account_id}");
            $rs = $model->get();
            $model->disconnect();
            if(isset($rs[0]) && $rs[0]) {
                $account_info = json_encode($rs[0]);
                $this->redis->set($account_id,$account_info,3600);
            }
        } else {
            $account_info = $this->redis->get($account_id);
            if (!$account_info){
                $account_info = $this->getGdtToken($account_id,false);
                $account_info = json_encode($account_info);
            }
        }
        return $account_info ? json_decode($account_info,true) : [];
    }


    /*
    * 处理点击数据
    * @param $device   设备 0:ios 1:android
    * @param $sign_key 主键key
    * */
    public function clickInfo($click_info)
    {
        $extra_str = json_decode($click_info['extrainfo']);
        $tmp_arr = explode('&', $extra_str);
        foreach ($tmp_arr as $key => $value) {
            $tmp = explode('=', $value);
            $extra_arr[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
        }
        return $extra_arr;
    }


    /*
     * 获取点击数据
     * @param $device   设备 0:ios 1:android
     * @param $sign_key 主键key
     * */
    public function getClickInfo($sign_key, $exp_time = 7)
    {
//        $this->scylla->ConnectCluster();

        $map = [['sign_key', '=', $sign_key]];

        try {
            $info = $this->scylla->Select('*', static::COMMON_CLICK_LOG, $map, 1);
        } catch (Exception $e) {
            $this->log('【' . date('Ymd H:i:s') . '】' . $e->getMessage() . "\r\n", $dir = 'cass/');
        }
        if (empty($info[0])) return false;
        if ($info[0]['extrainfo']) {
            $extra_str = json_decode($info[0]['extrainfo']);
            $tmp_arr = explode('&', $extra_str);
            foreach ($tmp_arr as $key => $value) {
                $tmp = explode('=', $value);
                $extra_arr[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
            }
            $info[0]['extrainfo'] = $extra_arr;
        }
        if (!isset($info[0]['extrainfo']['callback'])) $info[0]['extrainfo']['callback'] = '';
        return $info[0]['extrainfo'];
    }

    /*
    * 获取点击数据
    * */
    public function changeClickInfo($data)
    {
        $common_log = $data['common_log'];
        $extra_str = json_decode($common_log['extrainfo']);
        $tmp_arr = explode('&', $extra_str);
        foreach ($tmp_arr as $key => $value) {
            $tmp = explode('=', $value);
            $extra_arr[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
        }
        $common_log = array_merge($common_log,(array)$extra_arr);
        return $common_log;
    }

    /*
     * 获取后台广告回传配置
     * */
    public function getCallbackConfig($v)
    {
        $key_list = ['reg_back' => 1, 'act_back' => 1, 'pay_back' => 1, 'payback_percen' => 0];
        return $key_list;

        $expires_time = $this->redis->hget('config_expires', 'adver_callback_expires');
        if (empty($expires_time) || $expires_time < time() - 300) {

            $db = Database::instance();
            $db->table('tbl_adver_callback_config', 'adv');
            $db->select(array('condition' => " where `status`=1 ", 'field' => ['game_id', 'advter_ver', 'reg_back', 'act_back', 'pay_back', 'payback_percen']));
            $advter_ver_configs = $db->get();
            $this->redis->del(static::ADVER_TABLE);
            foreach ($advter_ver_configs as $key => $value) {
                $key = $value['game_id'] . '_' . $value['advter_ver'];
                $this->redis->hset(static::ADVER_TABLE, $key, json_encode($value));
            }
            $this->redis->hset('config_expires', 'adver_callback_expires', time());
        }
        $key = $v['gid'] . '_' . ($v['device'] == 'ios' ? $v['advter_id'] : (isset($v['ver']) ? $v['ver'] : 0)); //游戏_推广包(位)

        $keyarr = $this->redis->hget(static::ADVER_TABLE, $key);
        if (empty($keyarr)) {
            $key = $v['gid'] . '_0';
            $keyarr = $this->redis->hget(static::ADVER_TABLE, $key);
        }


        if (empty($keyarr)) {
            $key_list = ['reg_back' => 1, 'act_back' => 1, 'pay_back' => 0, 'payback_percen' => 0];
        } else {
            $key_list = json_decode($keyarr, true);
        }
        return $key_list;
    }

    /**
     * 查询点击数据
     * @param $data 回传信息$v
     */
    public function get_click_param($data)
    {
        if(!isset($data['sign_key'])) {
            return false;
        }
        //根据设备号查询
        $click_info = self::getClickInfo($data['sign_key']);
        return $click_info;
    }


    protected function check_callback($key_list, &$v)
    {
        if (!in_array($v['stype'], ['register', 'install', 'pay'])) return false;

        if ($v['stype'] == 'install' && $key_list['act_back'] != 1) return false;

        if ($v['stype'] == 'register' && $key_list['reg_back'] != 1) return false;

        if ($v['stype'] == 'pay' && $key_list['pay_back'] != 1) return false;

        //判断付费百分比是否有设置，有则转换付费金额
        if ($v['stype'] == 'pay' && isset($v['amount']) && !empty($key_list['payback_percen'])) {
            $v['amount'] = $v['amount'] * ($key_list['payback_percen'] / 100);
        }

        return true;

    }


    /*将上报结果数据入库
     * @param $data $v 默认数据
     * @param $channel_code 回调广告名
     * @param $callback_url 回调地址
     * @param $callback_result 回调返回值
     * @param $callback_status 回调结果 true/false
     * @param $param 请求参数
     * */
    public function ins_db_log($data, $channel_code, $callback_url, $callback_result, $callback_status,$param = '')
    {
        $callback_result = is_array($callback_result) ? json_encode($callback_result) : $callback_result;
        $filename = isset($callback_status) ? 'success' : 'fail';

        $this->log($callback_url . '@' . json_encode($data) . "@" . json_encode($callback_result) . "@" .json_encode($param). "\r\n", "channel_callback/$channel_code/{$filename}/" . date('ymd') . '/');

        $data['dateline'] = time();
        $data['channel_code'] = $channel_code;
        $data['callback_url'] = $callback_url."@".json_encode($param);
        $data['callback_result'] = $callback_result;
        $data['callback_status'] = $callback_status ? 1 : 0;
        $data['stype'] = $this->stype[$data['stype']];
        $data['device'] = 'ios' == $data['device'] ? 1 : 2;
        $data['channel_oaid'] = $data['common_log']['oaid'];
        $data['channel_idfa'] = $data['common_log']['idfa'];
        $data['channel_ver'] = $data['common_log']['channel_ver'];
        $data['sign_key'] = $data['common_log']['id'];

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
        }
    }


    public function log($msg, $dir = '', $file = '')
    {
        $maxsize = 2 * 1024 * 1024;
        $base_dir = dirname(dirname(__FILE__)) . '/log/';
        !empty($dir) && $base_dir .= $dir;

        if (!is_dir($base_dir)) {
            mkdir($base_dir, 0777, true);
        }

        empty($file) && $file = date('Ymd') . '.log';

        $path = $base_dir . $file;
        //检测文件大小，默认超过2M则备份文件重新生成 2*1024*1024
        if (is_file($path) && $maxsize <= filesize($path))
            rename($path, dirname($path) . '/' . time() . '-' . basename($path));

        error_log($msg, 3, $path);
    }

    protected function getCurl($url, $time = 120, $http_code = false)
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

    protected function postCurl($url, $data = '', $time = 120)
    {
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
            CURLOPT_SSL_VERIFYPEER => 0, // 跳过证书检查
            CURLOPT_SSL_VERIFYHOST => 0  // 从证书中检查SSL加密算法是否存在
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opt);
        $contents = curl_exec($ch);
        curl_close($ch);

        return $contents;
    }

    public function fetchUrl($url, $time = 3, $http_code = false)
    {
        if (static::DEBUG) {
            return json_encode(array('ret' => 0));
        }

        $curl_opt = array(
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => ($time ? $time : static::TIMEOUT),
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

    public function curl_init_post($url, $params, $timeout = 5,$header = ['Content-Type: application/x-www-form-urlencoded'])
    {
        if (static::DEBUG) {
            return json_encode(array('status' => 0, 'ret' => 0));
        }

        empty($timeout) && $timeout = static::TIMEOUT;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //定义请求类型
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $returnTransfer = curl_exec($ch);
        curl_close($ch);
        return $returnTransfer;
    }

    /* 创建微信应用行为源 */
    protected function setWechatUserAction($access_token, $click_info)
    {
        $redisTable = "WechatAction";

        if (!isset($click_info['appid'])) return true;

        $redisKey = $redisTable . $click_info['appid'];

        $userAction = $this->redis->hget($redisTable, $redisKey);
        if (!$userAction) {
            $url = "https://api.weixin.qq.com/marketing/user_action_sets/add?version=v1.0&access_token={$access_token}";

            $data = [
                'type' => strtoupper($click_info['app_type']),
                'mobile_app_id' => $click_info['appid'],
                'name' => 'click' . $click_info['appid'],
                'description' => 'click',
            ];

            if($data['type'] == 'UNIONANDROID')
            {
                $data['type'] = 'ANDROID';
            }

            if($data['type'] != 'ANDROID' && $data['type'] != 'IOS')
            {
                $data['type'] = 'ANDROID';
            }

            $data = json_encode($data);

            $userActionRes = $this->postCurl($url, $data);
            $userActionRes = json_decode($userActionRes, true);
            if ($userActionRes['errcode'] == 42001){//令牌过期
                return false;
            }

            if ($userActionRes['errcode'] == 0) {
                $userAction = $userActionRes['data']['user_action_set_id'];
                $this->redis->hset($redisTable, $redisKey, $userAction);

            } elseif ($userActionRes['errcode'] == 900351000) { //Action已存在
                $userAction = substr($userActionRes['errmsg'], -10);
                $this->redis->hset($redisTable, $redisKey, $userAction);
            } else {
                $this->log(json_encode($userActionRes)."\r\n\r\n",'channel_callback/','token.log');
            }
        }
        return $userAction;
    }

    /*获取Wechat公众平台的AccessToken*/
    public function getWechatAccessToken($account_id,$cache = true)
    {
        $wechat_cache = 'wechat_token:'.$account_id;
        if ($cache){
            $wechat_cache_token = $this->redis->get($wechat_cache);
            if (!$wechat_cache_token){
                $wechat_cache_token = $this->getWechatAccessToken($account_id,false);
            }
        } else {
            $account_key = [
                3902=>[
                    'appid' => 'wxa1c6797acd365671',
                    'secret' => 'e222f0fb392a2543c3ec04eec7121ba8',
                ],
                3836=>[
                    'appid' => 'wx49c7b3a2808aaa70',
                    'secret' => 'e94eae486abe8bfb824f978b9f8d4cf7',
                ]
            ];

            $key = $account_key[$account_id];
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$key['appid']}&secret={$key['secret']}";
            $accessTokenResJson = $this->getCurl($url);
            $accessTokenRes = json_decode($accessTokenResJson, true);
            if (!isset($accessTokenRes['access_token'])) return false;
            $wechat_cache_token = $accessTokenRes['access_token'];
            $this->redis->set($wechat_cache,$wechat_cache_token,3600);
        }
        return $wechat_cache_token;
    }

    //检查是否有付费回传
    public function checkPay($v)
    {
        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $db = Database::instance($config['sdklogs']);
        if (!isset($v['uid'])) return false;
        $res = $db->table('49app_data_report_log')->query("select * from 49app_data_report_log where uid = {$v['uid']} and stype = 2 and callback_status = 1")->get();
        empty($res) ? false : true;
    }

    public function checkUserTime($v)
    {
        $storage = new storage();
        $res = $storage->getGameLoginUserInformation($v['uid'],$v['gid'],1);
        if (!$res) return false;
        $pay_time = date("Ymd",$v['finish_time']);
        $reg_time = date("Ymd",$res['dateline']);
        if ($pay_time != $reg_time) return false;
        return true;
    }

    //检查快手是否上报
    public function checkKuaishouSubmit($data)
    {
        $storageAdv = new storageAdv();
        $game_list = $storageAdv->getGameSubList($data['ver_id']);
        if (isset($game_list) && $game_list['sdk_app'] && $game_list['ks_appname']){
            return false;
        } else {
            return true;
        }
    }

}