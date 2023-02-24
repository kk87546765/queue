<?php

require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");

/*ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/

class gdtnew extends CallbackModel
{
    public $device;
    public $CallbackModel;
    protected $status = false;
    protected $action_set_id = '1111677062';
    const ACTION_SET_ID = '1111677062';

    public function __construct($v = [])
    {
        if (!empty($v)) {
            $this->device = $v['device'] == 2 ? 'android':'ios' ;
            $this->CallbackModel = new CallbackModel();
        }
    }

    public function index($v)
    {
        self::__construct($v);
        if ($this->device == 'android') return true; //安卓不上报
        if ($v['game_sub_id'] == 438 ) $this->action_set_id = '1111612909'; //跟换行为源

        $action = [
            'register' => 'REGISTER',//注册
            'install' => 'ACTIVATE_APP',//激活
            'pay' => 'PURCHASE', //购买
            'open' => 'START_APP', //启动
            'login' => 'START_APP',//次日留存
        ];

        //单位分
        $v['stype'] == 'pay' && $action_param = ['type' => strtoupper($this->device), 'value' => intval($v['amount'] * 100)];//广点通单位为分

        $v['account_id'] = '3427';
        $token_list = $this->CallbackModel->getGdtToken($v['account_id']);
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
            'user_action_set_id' => $this->action_set_id,
            'actions' => [
                [
                    'action_time' => $get_data['timestamp'],
                    'user_id' => [
                        'hash_imei' => $click_param['muid'],
                        'oaid' => isset($click_param['oaid']) ? $click_param['oaid'] :'',
                    ],
                    'action_type' => $action[$v['stype']],
                    'trace' => [
                        'click_id' => $click_param['click_id'],
                    ],
                ]
            ],//注册 REGISTER  充值 PURCHASE  激活ACTIVATE_APP
        ];
        if ($v['device'] == 1) { //ios上报参数有变
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
            $token_list = $this->CallbackModel->getGdtToken($v['account_id'], false);
            $get_data['access_token'] = $token_list['channel_account_id'];
            $get_data['nonce'] = substr(md5(microtime() . mt_rand(0, 1000)), 8, 16);
            $url = $uri . http_build_query($get_data);
            $rs = $this->postCurl($url, json_encode($data));
            $rs = json_decode($rs, true);
        }
        $rs['code'] == 0 && $this->status = true;
        $this->ins_db_log($v, __CLASS__, $url, $rs, $this->status, $data);
        return $this->status;
    }
}