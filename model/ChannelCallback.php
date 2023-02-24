<?php
require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname((__FILE__)) . "/CallbackModel.php");

/*ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/

class ChannelCallback extends CallbackModel
{
    const TIMEOUT = 3;
    const DEBUG = false;
    public $redis;
    public $device;
    public $CallbackModel;
    protected $status = false;
    protected $muid = ['ios' => 'idfa', 'android' => 'imei'];
    protected $active = [100793, 100797]; //2019-01-09,用激活数据回传

    public function __construct($v = [])
    {
        $this->redis = RedisModel::instance();

        if (!empty($v)) {
//            $this->device = $v['device'];
            $this->device = 2;
            $this->CallbackModel = new CallbackModel();
        }
    }

    public function Wechat($v)
    {
        $action = [
            'register' => 'ACTIVATE_APP',//激活
            'install' => 'ACTIVATE_APP',//激活
            'pay' => 'PURCHASE', //购买
            'open' => 'START_APP', //启动
            'login' => 'START_APP',//次日留存
        ];

        self::__construct($v);

        $v['stype'] == 'pay' && isset($v['pay_advter_id']) && $v['advter_id'] = $v['pay_advter_id'];

        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);

        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        $accessToken = $this->CallbackModel->getWechatAccessToken();

        if (!$accessToken){
            $accessToken = $this->refreshAccessToken();
        }

        $userAcion = $this->CallbackModel->setWechatUserAction($accessToken, $click_info);

        $v['stype'] == 'pay' && $action_param = ['value' => intval($v['amount'] * 100)];

        $v['stype'] == 'login' && $action_param = ['length_of_stay' => 1];

        $postData = [
            'user_action_set_id' => $userAcion,
            'actions' => [
                [
                    'action_time' => time(),
                    'user_id' => ['hash_' . $this->muid[$this->device] => $muid],
                    'action_type' => $action[$v['stype']],
                    'trace' => ['click_id' => $click_info['click_id']],
                ]
            ],
        ];

        if (in_array($v['stype'], ['pay', 'login'])) { //自定义行为参数
            $postData['actions'][0]['action_param'] = $action_param;
        }
        $postUrl = "https://api.weixin.qq.com/marketing/user_actions/add?version=v1.0&access_token={$accessToken}";

        $rs = $this->CallbackModel->postCurl($postUrl, json_encode($postData));
        $rs = json_decode($rs, true);
        if ($rs['errcode'] == 42001 || $rs['errcode'] == 40001){
            $accessToken = $this->refreshAccessToken();
            $postUrl = "https://api.weixin.qq.com/marketing/user_actions/add?version=v1.0&access_token={$accessToken}";

            $rs = $this->CallbackModel->postCurl($postUrl, json_encode($postData));
            $rs = json_decode($rs, true);
        }

        $rs['errcode'] == 0 && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $postUrl, $rs, $this->status);
        return $this->status;
    }

    public function UCHuiChuan($v)
    {
        self::__construct($v);
        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);
        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;
        if (!isset($click_info['callback'])) return true;

        $click_info['callback'] = urldecode($click_info['callback']);
        $rs = $this->fetchUrl($click_info['callback'], static::TIMEOUT, true);

        $rs == 200 && $this->status = true;
        $this->ins_db_log($v, __FUNCTION__, $click_info['callback'], $rs, $this->status);
        return $this->status;
    }


    public function Jrtt_plan_b($v)
    {
        if ($v['stype'] == 'register' && in_array($v['gid'],[101749,101201,101799,101963,102145,102143])){ //2019-11-15 链端游戏，没激活数据，用注册代替激活回传
            $v['stype'] = 'install';
            $this->Jrtt_plan_b($v);
            $v['stype'] = 'register';
        }
        self::__construct($v);
        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);

        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        // 获取参数配置
        $conf = $this->getKey($v['channel_code'], $v['gid'], $v['advter_id']);
        if (empty($conf['sign_key'])) return true;

        $url = 'http://ad.toutiao.com/track/activate/?';
        $time = time();
        $key = $conf['sign_key'];
        $event_type = $v['stype'] == 'pay' ? 2 : ($v['stype'] == 'register' ? 1 : 0);

        $url = $url . "callback={$click_info['callback']}&muid={$muid}&os={$click_info['os']}&conv_time={$time}&event_type={$event_type}";

        $signature = md5($url . $key);
        $callback_url = ($url . "&signature={$signature}");
        $rs = $this->fetchUrl($callback_url);
        $this->log($callback_url . '@' . json_encode($rs) . "#" . (is_array($v) ? json_encode($v) : $v) . "\r\n\r\n", 'channel_callback/' . __FUNCTION__ . '/' . date('ymd') . '/');
        $rs = json_decode($rs, true);
        $rs['ret'] == 0 && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $callback_url, $rs, $this->status);
        return $this->status;
    }

    public function jrtt($v)
    {
        return true;
        /*if ($v['stype'] == 'register' ){ //2019-11-15 链端游戏，没激活数据，用注册代替激活回传
            $v['stype'] = 'install';
            $this->jrtt($v);
            $v['stype'] = 'register';
        }*/
        self::__construct($v);
//        $back_config = $this->CallbackModel->getCallbackConfig($v); //todo 配置
//        $back_res = $this->check_callback($back_config, $v);
//        if (!$back_res) return true;
        $click_info = $this->CallbackModel->changeClickInfo($v);
        if (empty($click_info)) return true;

        // 获取参数配置 todo
        $conf = $this->getKey($v['channel_code'], $v['gid'], $v['ver_id']);
        if (empty($conf['sign_key'])) return true;
        $url = 'http://ad.toutiao.com/track/activate/?';
        $time = time();
        $conf['sign_key'] = 'test';
        $key = $conf['sign_key']; //todo
        $event_type = $v['stype'] == 'pay' ? 2 : ($v['stype'] == 'register' ? 1 : 0);
        $v['device'] = $click_info['ostype'];

        $param = [
            'conv_time'=>$time,
            'os'=>$click_info['os'],
            'event_type'=>$event_type,
            'callback'=>$click_info['callback'],
        ];

        if ($click_info['ostype'] == 1 ){
            $param['muid'] = $param['idfa'] = $v['idfa'];
        } else {
            $param['oaid'] = $v['oaid'];
            $param['imei'] = $v['imei'];
            $param['muid'] = isset($v['imei1']) ? md5($v['imei1']) :'';
        }
        $url = $url . http_build_query($param);
        $signature = md5($url . $key);
        $callback_url = ($url . "&signature={$signature}");
        $rs = [
            'ret'=>0,
        ];
        //todo
//        $rs = $this->fetchUrl($callback_url);
//        $this->log($callback_url . '@' . json_encode($rs) . "#" . (is_array($v) ? json_encode($v) : $v) . "\r\n\r\n", 'channel_callback/' . __FUNCTION__ . '/' . date('ymd') . '/');
//        $rs = json_decode($rs, true);
        $rs['ret'] == 0 && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $callback_url, $rs, $this->status);
        return $this->status;
    }

    public function Bdxxl($v)
    {
        self::__construct($v);
        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);

        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);

        // 获取参数配置
        $conf = $this->CallbackModel->getKey('bdxxl', $v['gid'], $v['advter_id']);
        if (empty($conf['sign_key'])) return true;

        if (!isset($click_info['callback_url']) || empty($click_info['callback_url'])) return true;

        $a_type = $v['stype'] == 'pay' ? 'orders' : 'activate';
        $a_value = $v['stype'] == 'pay' ? intval($v['amount'] * 100) : 0; //单位分

        $url = urldecode($click_info['callback_url']);
        $url = str_replace('{{ATYPE}}', $a_type, $url);
        $url = str_replace('{{AVALUE}}', $a_value, $url);
        $sign = md5($url . $conf['sign_key']);
        $url = $url . '&sign=' . $sign;

        $rs = $this->CallbackModel->fetchUrl($url);
        $rs = json_decode($rs, true);
        isset($rs['error_code']) && $rs['error_code'] == 0 && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $url, $rs, $this->status);
        return $this->status;
    }



    public function newkuaishou($v)
    {
        if ($v['stype'] == 'install') return true; //2020-08-05 快手默认回传激活,只用注册数据回传即可

        self::__construct($v);
        $back_config = $this->CallbackModel->getCallbackConfig($v);
        $back_res = $this->check_callback($back_config, $v);
        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        $event_type = 1; //默认激活
        $event_time = time() . '000';
        $purchase_amount = '';

        if ($v['stype'] == 'pay') {
            $event_type = 3; //付费
            $purchase_amount = '&purchase_amount=' . intval($v['amount']);
        }

        $click_info['callback'] = urldecode($click_info['callback']) . "&event_type={$event_type}&event_time={$event_time}{$purchase_amount}";
        $rs = $this->CallbackModel->fetchUrl($click_info['callback'], static::TIMEOUT);
        $rs = json_decode($rs, true);
        $rs['result'] == 1 && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $click_info['callback'], $rs, $this->status);
        return $this->status;
    }

    public function gdtnew($v)
    {
        $action = [
            'register' => 'REGISTER',//注册
            'install' => 'ACTIVATE_APP',//激活
            'pay' => 'PURCHASE', //购买
            'open' => 'START_APP', //启动
            'login' => 'START_APP',//次日留存
        ];

        self::__construct($v);

        if (!in_array($v['package_name'],['com.jiuzhoulh.gdt.lmts'])) return true;

        //单位分
        $v['stype'] == 'pay' && $action_param = ['type' => strtoupper($this->device), 'value' => intval($v['amount'] * 100)];//广点通单位为分

        $account_id = 18341639;//固定账号
        $user_action_set_id = 1111177562;//行为源
        $token_list = $this->CallbackModel->getGdtToken($account_id);
        if (!$token_list) return true;
        $click_param = $this->clickInfo($v['common_log']); //原始点击参数

        $get_data = array(
            'access_token' => $token_list['access_token'],
            'timestamp' => time(),
            'nonce' => substr(md5(microtime() . mt_rand(0, 1000)), 8, 16),
        );
        //上报行为数据
        $uri = "https://api.e.qq.com/v1.1/user_actions/add?";
        $data = [
            'account_id' => $token_list['channel_account_id'],
            'user_action_set_id' => $user_action_set_id,
            'actions' => [
                [
                    'action_time' => $get_data['timestamp'],
                    'user_id' => [
                        'hash_imei' => $click_param['muid'],
                        'oaid'=>  $click_param['oaid'],
                        ],
                    'action_type' => $action[$v['stype']],
                    'trace' => [
                        'click_id' => $click_param['click_id'],
                    ],
                ]
            ],//注册 REGISTER  充值 PURCHASE  激活ACTIVATE_APP
        ];
        if ($v['device'] == 'ios') { //ios上报参数有变
            $data['actions'][0]['user_id'] = array('hash_idfa' => $click_param['muid']);
        }

        if (in_array($v['stype'], ['pay', 'login'])) { //自定义行为参数
            $data['actions'][0]['action_param'] = $action_param;
        }
        $url = $uri . http_build_query($get_data);
        $rs = $this->postCurl($url, json_encode($data));
        $rs = json_decode($rs, true);
        if (empty($rs) || 0 !== $rs['code']) {
            $this->log($url . "@" . json_encode($rs) . '#' . (is_array($v) ? json_encode($v) : $v) . "\r\n\r\n", 'channel_callback/' . __FUNCTION__ . '/refresh/' . date('ymd') . '/');
            $token_list = $this->CallbackModel->getGdtToken($account_id,false);
            $get_data['access_token'] = $token_list['channel_account_id'];
            $get_data['nonce'] = substr(md5(microtime() . mt_rand(0, 1000)), 8, 16);
            $url = $uri . http_build_query($get_data);
            $rs = $this->postCurl($url, json_encode($data));
            $rs = json_decode($rs, true);
        }
        $rs['code'] == 0 && $this->status = true;
        $this->ins_db_log($v, __FUNCTION__, $url, $rs, $this->status,$data);
        return $this->status;
    }


    public function gdtnew_open_mkt($v)
    {
        $action = [
            'register' => 'ACTIVATE_APP',//激活
            'install' => 'ACTIVATE_APP',//激活
            'pay' => 'PURCHASE', //购买
            'open' => 'START_APP', //启动
            'login' => 'START_APP',//次日留存
        ];

        if (in_array($v['gid'], $this->active) && $v['stype'] == 'install') $v['stype'] = 'register'; //临时处理 2018-12-07

        $v['stype'] == 'pay' && isset($v['ios_advter_id']) && $v['advter_id'] = $v['ios_advter_id'];

        if ((isset($v['imei']) && $v['imei'] == 'null') || ($v['device'] == 'android' && isset($v['ver']) && strpos($v['ver'], '001'))) return true; //主包默认不回传

        self::__construct($v);

        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);

        if (($v['stype'] == 'open' && $back_config['act_back'] == 1 && $this->device == 'ios') || ($v['stype'] == 'login' && $this->device == 'ios')) {
            $back_res = true;
        }
        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        $accessToken = $this->CallbackModel->getWechatAccessToken();

        if (!$accessToken){
            $accessToken = $this->refreshAccessToken();
        }

        $userAcion = $this->CallbackModel->setWechatUserAction($accessToken, $click_info);
        if ($userAcion == 42001){ //token过期
            $accessToken = $this->refreshAccessToken();
        }

        //单位分
        $v['stype'] == 'pay' && $action_param = ['type' => strtoupper($this->device), 'value' => intval($v['amount'] * 100)];//广点通单位为分

        $v['stype'] == 'login' && $action_param = ['type' => strtoupper($this->device), 'length_of_stay' => 1, 'claim_type' => 4];

        $postData = [
            'user_action_set_id' => $userAcion,
            'actions' => [
                [
                    'action_time' => time(),
                    'user_id' => ['hash_' . $this->muid[$this->device] => $muid],
                    'action_type' => $action[$v['stype']],
                    'trace' => ['click_id' => $click_info['click_id']],
                ]
            ],
        ];

        if (in_array($v['stype'], ['pay', 'login'])) { //自定义行为参数
            $postData['actions'][0]['action_param'] = $action_param;
        }
        $postUrl = "https://api.weixin.qq.com/marketing/user_actions/add?version=v1.0&access_token={$accessToken}";

        $rs = $this->CallbackModel->postCurl($postUrl, json_encode($postData));
        $rs = json_decode($rs, true);
        if ($rs['errcode'] == 42001){
            $accessToken = $this->refreshAccessToken();
            $postUrl = "https://api.weixin.qq.com/marketing/user_actions/add?version=v1.0&access_token={$accessToken}";

            $rs = $this->CallbackModel->postCurl($postUrl, json_encode($postData));
            $rs = json_decode($rs, true);
        }

        $rs['errcode'] == 0 && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $postUrl, $rs, $this->status);
        return $this->status;
    }


    public function youku($v)
    {
        self::__construct($v);
        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);
        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        $conf = $this->getKey($v['channel_code'], $v['gid'], $v['advter_id']);
        if (empty($conf)) return true;

        $getData['system'] = 0;
        $getData['appkey'] = $conf['sign_key'];
        $getData['source'] = $conf['encrypt_key'];
        $getData['eventtime'] = time();
        $getData['eventtype'] = 1;
        $getData['clickid'] = $click_info['click_id'];
        $getData['os'] = $v['device'];

        if ($muid == '31C18D24-5263-47CE-821B-C8F863B1EA7F') {
            // 测试
            $url = 'https://bld-yartb.alimama.com/callback?';
            $getData['source'] = 'test_source';
            $getData['appkey'] = 'cf162bcf972845019f64b1f954ba1aedbb40adca';
            $getData['clickid'] = '1234567';
        } else {
            // 正式
            $url = 'https://bld-yartb.alimama.com/callback?';
        }

        $exit = '';
        foreach ($getData as $key => $val) {
            $exit .= $key . '=' . $val . '&';
        }
        $exit = trim($exit, '&');
        $callback = $url . $exit;

        $rs = $this->getCurl($callback);
        $rs = json_decode($rs, true);

        isset($rs['resultCode']) && $rs['resultCode'] == 200 && $this->status = true;
        $this->ins_db_log($v, __FUNCTION__, $callback, $rs, $this->status);
        return $this->status;
    }


    //自定义渠道
    public function Custom($v)
    {
        self::__construct($v);
        $back_config = $this->CallbackModel->getCallbackConfig($v);

        $back_res = $this->check_callback($back_config, $v);

        if (!$back_res) return true;

        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        $url = urldecode($click_info['callback']);

        $v['stype'] == 'pay' && $url = $url . "&conv_type=APP_PAY&conv_value={$v['amount']}";

        $rs = $this->CallbackModel->fetchUrl($url, static::TIMEOUT);
        $rs = json_decode($rs, true);
        $rs['code'] == '0' && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $url, $rs, $this->status);
        return $this->status;
    }


    private function refresh_token($refresh_token = '', $account_uin = '')
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
        $ret = $this->getCurl($url);
        $ret = json_decode($ret, true);
        if (!empty($ret) && 0 === $ret['code']) {
            $redis_key = 'mktgdt_' . $client_id . '_' . $account_uin; //开发者id_qq账户
            $this->redis->set($redis_key, $ret['data']['access_token'], 86000);

            return $ret['data']['access_token'];
        }

        return false;
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

    public function getKey($channel_code, $gid, $advter_id = 0)
    {
        $key_prefix = "channel：callback：cache:";

        $callback_cache_list = $this->redis->get($key_prefix.$channel_code.'_'.$gid.'_'.$advter_id);
        if (!$callback_cache_list){
            $db = Database::instance();
            $db->table('tbl_channel_callback_config', 'adv');
            $db->select(array('condition' => " where `status`=1 and game_id = '$gid' and channel_code = '$channel_code' and adver_id = $advter_id"));
            $configs = $db->get();
            $callback_cache_list = [];
            foreach ($configs as $key => $value) {
                $callback_cache_list = $value;
                $key = $value['channel_code'].'_'.$value['game_id'].'_'.$value['adver_id'];
                $this->redis->set($key_prefix.$key, json_encode($value),300);
            }
        }
        return is_array($callback_cache_list) ? $callback_cache_list : json_decode($callback_cache_list,true);
    }


    //安卓头条付费回传
    public function jrttCallbackPay($v)
    {
        self::__construct($v);

        if ($v['stype'] != 'pay') return true;

        if ( in_array($v['gid'], [447,1075])){ //2020-06-16  开启累计金额回传
            $v['advter_id'] = isset($v['pay_advter_id']) ? $v['pay_advter_id'] : $v['advter_id'];
        }
        $muid = strtoupper(md5($v[$this->muid[$this->device]]));

        $click_info = $this->CallbackModel->get_click_param($v, $muid);
        if (empty($click_info)) return true;

        $conf = $this->getKey($v['channel_code'], $v['gid'], $v['advter_id']);

        if (empty($conf)) return true;

        $url = 'http://mcs.snssdk.com/v2/event/json';

        $header = [
            'Content-type: application/json',
            'X-MCS-AppKey:' . $conf['sign_key'],
        ];

        $params = [
            'user' => [
                'user_unique_id' => "{$v['uid']}",
                'udid' => $v['imei'],
                'openudid' => isset($click_info['aandroidid']) ? $click_info['aandroidid'] : 'openudid' ,
                'build_serial' => $v['imei'],
            ],
            'header' => [
                'app_package' => $conf['encrypt_key'],
            ],
            'events' => [
                [
                    'event' => 'purchase',
                    'params' => json_encode([
                        'is_server' => 'yes',
                        'is_success' => 'yes',
                        'currency_amount' => intval($v['amount']),
                    ]),
                    'local_time_ms' => (float)(time().'000'),
                ]
            ],
        ];

        $params = json_encode($params);
        $rs = $this->curl_init_post($url, $params, 30, $header);
        $rs = json_decode($rs, true);
        $rs['message'] == 'success' && $this->status = true;

        $this->ins_db_log($v, __FUNCTION__, $url, $rs, $this->status,$params);

        return true;

    }

}