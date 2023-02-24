<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
/**
 * Created by PhpStorm.
 * User: Administrator
 */
require_once(dirname(dirname(__FILE__)) . "/model/CommonModel.php");
require_once(dirname(dirname(__FILE__)) . "/model/Queue.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleOpen.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleAct.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleReg.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleSign.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandlePay.php");

class worker extends CommonModel
{
    const PROCESS = 10; //worker运行时间
    protected $open;
    protected $act;
    protected $reg;
    protected $sign;
    protected $pay;

    public function __construct()
    {
        $this->open = new handleOpen();
        $this->act = new handleAct();
        $this->reg = new handleReg();
        $this->sign = new handleSign();
        $this->pay = new handlePay();
        parent::__construct();
    }


    public function run()
    {
        $queue   = new queue();

        $res = $queue->popList();

        if (!$res) die('not data');

        $data = json_decode($res,true);

        print_r($data);

        if (!$data['data']) die('data is null');

        $status = $this->handleType($data);

        var_dump($status);exit;
    }
}

$res = new worker();
$res->run();