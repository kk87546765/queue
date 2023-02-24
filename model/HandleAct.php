<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once(dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");
require_once(dirname(dirname(__FILE__)) . "/api/submitTw.php");

class handleAct
{
    private $common_click_log = [];

    public function start($data)
    {
        $db = new storage();
        $this->changeParam($data);
        $this->writeLog($data);

        $this->common_click_log = $db->getNewCommonLog($data); //mysql归因
        if (!$this->common_click_log){
//            $this->common_click_log = $db->getCommonLog($data); //数据匹配，获取点击数据
        }
        $this->insertDb($data); //插入数据库
        $db->disconnect();
        $this->sendChannel($data); //数据上报
        $submitTw = new submitTw(); //贪玩数据上报
        $submitTw->index('act',$data);
        return true;
    }

    /*
     * 参数转换
     * */
    public function changeParam(&$data)
    {
        $fixed = ['version'=>'android_version'];
        foreach ($data as $k=>$v){
            foreach ($fixed as $key=>$val){
                if ($k == $val) $data[$key] = $v; //参数转化
            }
        }
    }


    /**
     * 写入日志
     * @param $data
     */
    public function writeLog($data)
    {
        $write = new writeFile();
        $write->write($data,2);
    }


    /**
     * 将打开写入数据库
     * @param $data
     * @param $db
     * @return bool
     */
    public function insertDb($data)
    {
        if ($data['device'] == 1 && in_array($data['ver_id'],[0,10000]) ){
            $data['ver_id'] = isset($this->common_click_log['channel_ver'])?$this->common_click_log['channel_ver']:10000;
        }
        $data['month'] = isset($data['dateline'])?date('m',$data['dateline']):0;
        $data['aid']       = isset($this->common_click_log['aid'])?$this->common_click_log['aid']:0;
        $data['cid']       = isset($this->common_click_log['cid'])?$this->common_click_log['cid']:0;
        $data['channel_ver']       = isset($this->common_click_log['channel_ver'])?$this->common_click_log['channel_ver']:0;
        $data['channel_type']      = isset($this->common_click_log['channel_type'])?$this->common_click_log['channel_type']:0;
        $db = new storage();
        $res = $db->saveAct($data);
        if(!is_array($res)){
            echo $res.__METHOD__;
            return false;
        }else{
            return true;
        }
    }


    /**
     * 报送
     * @param $data
     * @return bool
     */
    public function sendChannel($data)
    {
        $data['stype']       = 'install';
        $data['common_log'] = $this->common_click_log;
        $model = new Report();
        $res = $model->run($data);
        return $res;
    }

}