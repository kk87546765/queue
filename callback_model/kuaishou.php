<?php

require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");
require_once(dirname(dirname((__FILE__))) . "/model/StorageAdv.php");

/*ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/

class kuaishou extends CallbackModel
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
//        if ($this->device == 'android') return true; //安卓不上报
        $action = [
            'install' => 1,//激活
            'register' => 2,//注册
            'pay' => 3, //购买
        ];

        self::__construct($data);

        $is_submit = $this->CallbackModel->checkKuaishouSubmit($data);
        if ($is_submit == false) return true;

        $purchase_amount = '';
        $event_time = time() . '000';
        $event_type = $action[$data['stype']];

        $click_info = $this->clickInfo($data['common_log']); //原始点击参数

        if ($data['stype'] == 'pay') {
            $amount = isset($data['amount']) ? intval($data['amount']) : 1;
            $purchase_amount = '&purchase_amount=' . $amount;
        }
        if (!isset($click_info['callback'])) return true;
        $url = urldecode($click_info['callback']) . "&event_type={$event_type}&event_time={$event_time}{$purchase_amount}";
        $rs = $this->CallbackModel->fetchUrl($url, static::TIMEOUT);
        $rs = json_decode($rs, true);
        $rs['result'] == 1 && $this->status = true;
        $this->ins_db_log($data, __CLASS__, $url, $rs, $this->status);
        return $this->status;
    }
}