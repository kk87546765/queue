<?php

require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");

/*ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/

class wechat extends CallbackModel
{
    const TIMEOUT = 3;
    const DEBUG = false;
    public $device;
    public $CallbackModel;
    protected $status = false;

    public function __construct($v = [])
    {
        if (!empty($v)) {
            $this->device = $v['device'] == 2 ? 'android':'ios' ;
            $this->CallbackModel = new CallbackModel();
        }
    }

    public function index($data)
    {
        self::__construct($data);

        if ($this->device == 'android') return true; //安卓不上报

        $action = [
            'register' => 'REGISTER',//激活
            'install' => 'ACTIVATE_APP',//激活
            'pay' => 'PURCHASE', //购买
        ];

        $click_info = $this->clickInfo($data['common_log']); //原始点击参数

        $accessToken = $this->CallbackModel->getWechatAccessToken($data['account_id'],true);

        $userAcion = $this->CallbackModel->setWechatUserAction($accessToken, $click_info);

        $data['stype'] == 'pay' && $action_param = ['value' => intval($data['amount'] * 100)];

        $postData = [
            'user_action_set_id' => $userAcion,
            'actions' => [
                [
                    'action_time' => time(),
                    'user_id' => [
                        'hash_' . $this->muid[$this->device] =>$click_info['muid'],
                        'oaid'=>$click_info['oaid'],
                    ],
                    'action_type' => $action[$data['stype']],
                    'trace' => ['click_id' => $click_info['click_id']],
                ]
            ],
        ];

        if (in_array($data['stype'], ['pay'])) { //自定义行为参数
            $postData['actions'][0]['action_param'] = $action_param;
        }

        $postUrl = "https://api.weixin.qq.com/marketing/user_actions/add?version=v1.0&access_token={$accessToken}";

        $rs = $this->CallbackModel->postCurl($postUrl, json_encode($postData));
        $rs = json_decode($rs, true);
        if ($rs['errcode'] == 42001 || $rs['errcode'] == 40001) {
            $accessToken = $this->CallbackModel->getWechatAccessToken($data['account_id'],false);
            $postUrl = "https://api.weixin.qq.com/marketing/user_actions/add?version=v1.0&access_token={$accessToken}";
            $rs = $this->CallbackModel->postCurl($postUrl, json_encode($postData));
            $rs = json_decode($rs, true);
        }
        $rs['errcode'] == 0 && $this->status = true;

        $this->ins_db_log($data, __CLASS__, $postUrl, $rs, $this->status);
        return $this->status;
    }
}