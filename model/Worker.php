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
require_once(dirname(dirname(__FILE__)) . "/model/HandleLevel.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleUserinfo.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleClick.php");

class worker extends CommonModel
{
    const PROCESS = 10; //worker运行时间
    protected $open;
    protected $act;
    protected $reg;
    protected $sign;
    protected $pay;
    protected $level;
    protected $userinfo;

    public function __construct()
    {
        $this->open = new handleOpen();
        $this->act = new handleAct();
        $this->reg = new handleReg();
        $this->sign = new handleSign();
        $this->pay = new handlePay();
        $this->level = new handleLevel();
        $this->userinfo = new handleUserinfo();
        $this->click = new handleClick();
        parent::__construct();
    }


    public function run()
    {
        $queue   = new queue();
        $start_time = time();
        while (true)
        {
            $time = time() - $start_time;
//            为了避免一个worker运行时间太长设置脚本完成时间
            if ($time > static::PROCESS)
            {
                break;
            }
            $res = $queue->popList();

            if($res == false)
            {
                sleep(3);
                continue;
            }
            $data = json_decode($res,true);

            $status = $this->handleType($data);
            //处理完成后结束事务
            if($status == true)
            {
                if (date('i',time()) == '01'){
                    $queue->popListEnd($res,static::LIST_DISASTER.date('Y-m-d-H',time()-3600));
                }
                $queue->popListEnd($res);
            }

        }


    }



}