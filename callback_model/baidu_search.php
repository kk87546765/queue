<?php

require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");

/*ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/

class baidu_search extends CallbackModel
{
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
        $action = [
            'register' => 'register',//注册
            'install' => 'activate',//激活
            'pay' => 'orders', //购买
        ];

        self::__construct($data);

        // 获取参数配置
//        $conf = $this->CallbackModel->getKey('bdxxl', $v['gid'], $v['advter_id']);
//        if (empty($conf['sign_key'])) return true;
        $conf['sign_key'] = 'MzI2NjMyMzk=';

        $click_info = $this->clickInfo($data['common_log']); //原始点击参数

        if (!isset($click_info['callback_url']) || empty($click_info['callback_url'])) return true;

        $a_type = $action[$data['stype']];
        $a_value = $data['stype'] == 'pay' ? intval($data['amount'] * 100) : 0; //单位分

        $url = urldecode($click_info['callback_url']);
        $url = str_replace('{{ATYPE}}', $a_type, $url);
        $url = str_replace('{{AVALUE}}', $a_value, $url);
        $sign = md5($url . $conf['sign_key']);
        $url = $url . '&sign=' . $sign;

        $rs = $this->CallbackModel->fetchUrl($url);
        $rs = json_decode($rs, true);
        isset($rs['error_code']) && $rs['error_code'] == 0 && $this->status = true;

        $this->ins_db_log($data, __CLASS__, $url, $rs, $this->status);
        return $this->status;
    }


}