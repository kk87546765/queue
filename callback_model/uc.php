<?php

require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");

class uc extends CallbackModel
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
        return true;
        self::__construct($data);

        $click_info = $this->clickInfo($data['common_log']); //原始点击参数
        $callback_url = urldecode($click_info['callback_url']);
        $rs = $this->fetchUrl($callback_url, static::TIMEOUT, true);
        $rs == 200 && $this->status = true;
        $this->ins_db_log($data, __CLASS__, $callback_url, $rs, $this->status);
        return $this->status;
    }
}