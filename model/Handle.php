<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 15:04
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

class handle extends CommonModel
{

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
        $this->level = new handleLevel();
        $this->userinfo = new handleUserinfo();
        parent::__construct();
    }

    /**
     * 运行脚本
     * @param null $time 跑的时间
     */
    public function run($time = null)
    {
        if($time == null)
        {
            $time = time() - 3600;
        }

        $queue = new queue();
        $key = static::LIST_DISASTER.date('Y-m-d-H',$time);
        $i = 0;
        while (true)
        {
            $res = $queue->getDisList($key);
            if(!$res)
            {
                break;
            }

            foreach ($res as $v)
            {
                $data = json_decode($v,true);
                $status = $this->handleType($data);
                //处理完成后结束事务
                if($status == true)
                {
                    $i ++;
                    $queue->popListEnd($v, $key);
                    echo "已处理{$i}条数据\n";
                }
            }
        }
    }







}