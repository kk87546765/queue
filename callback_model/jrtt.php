<?php

require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname(dirname((__FILE__))) . "/model/CallbackModel.php");

/*ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/

class jrtt extends CallbackModel
{
    const TIMEOUT = 3;
    const DEBUG = false;
    public $device;
    public $CallbackModel;
    protected $is_reg = false;
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

        if ($data['stype'] == 'install' && $this->is_reg == false ) return true;//激活不上报

        //注册替代激活上报
        if ($data['stype'] == 'register'){
            $data['stype'] = 'install';
            $this->is_reg = true;
            $this->index($data);
            $data['stype'] = 'register';
        }

        $action = [
            'register' => 1,//注册
            'install' => 0,//激活
            'pay' => 2, //购买
        ];

        self::__construct($data);

        $click_info = $this->clickInfo($data['common_log']); //原始点击参数

        $url = 'http://ad.toutiao.com/track/activate/?';
        $event_type = $action[$data['stype']];

        $props = [];
        $data['stype'] == 'pay' && $props = ['pay_amount' => intval($data['amount'] * 100)];

        $param = [
            'conv_time'=>time(),
            'os'=>$click_info['os'],
            'event_type'=>$event_type,
            'callback'=>$click_info['callback'],
            'props'=>json_encode($props)
        ];

        if ($click_info['ostype'] == 1){
            $param['muid'] = $param['idfa'] = $click_info['idfa'];
            $param['caid1'] = isset($click_info['caid']) ? $click_info['caid'] : '';
        } else {
            $param['oaid'] = $data['oaid'];
            $param['imei'] = $data['imei'];
            $param['muid'] = isset($data['imei']) ? md5($data['imei']) :'';
        }
        $callback_url = $url . http_build_query($param);
        $rs = $this->fetchUrl($callback_url);
        $rs = json_decode($rs, true);
        $rs['ret'] == 0 && $this->status = true;
        $this->ins_db_log($data, __CLASS__, $callback_url, $rs, $this->status);
        return $this->status;
    }

}