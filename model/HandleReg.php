<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once(dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");
require_once(dirname(dirname(__FILE__)) . "/api/submitTw.php");


class handleReg
{
    private $common_click_log = [];

    public function start($data)
    {
        $db = new storage();
        $this->changeParam($data);
        $this->writeLog($data);
        $this->common_click_log = $db->getNewCommonLog($data); //数据匹配，获取点击数据
//        $this->common_click_log = $db->getCommonLog($data); //数据匹配，获取点击数据
        $this->insertDb($data); //插入数据库
        $this->updateUserVerid($data);
        $this->sendChannel($data); //数据上报
        if ($data['device'] == 1){ //IOS，激活上报
            $this->sendActChannel($data);
        }
        $submitTw = new submitTw(); //贪玩数据上报
        $submitTw->index('reg',$data);
        return true;
    }

    /*
     * 参数转换
     * */
    public function changeParam(&$data)
    {
        $fixed = ['oaid'=>'regImei', 'imei'=>'imei1','user_name'=>'userAcount','idfa'=>'idfa', 'android_id'=>'androidId', 'ver_id'=>'channelId', 'game_id'=>'regGameId','uid'=>'userId','ip'=>'regIp','dateline'=>'regTime','version'=>'android_version'];
        foreach ($data as $k=>$v){
            foreach ($fixed as $key=>$val){
                if ($k == $val) $data[$key] = $v; //参数转化
            }
        }
    }

    /**
     * 写入日志信息
     * */
    public function writeLog($data)
    {
        $write = new writeFile();
        $write->write($data,3);
    }


    public function insertDb($data)
    {
        $db = new storage();
        if ($data['device'] == 1 && in_array($data['ver_id'],[0,10000]) ){
                $data['ver_id'] = isset($this->common_click_log['channel_ver'])?$this->common_click_log['channel_ver']:10000;
//                $os_type = $db->check_os_type($data['ver_id']);
        }
        $ver_list = $db->getInfo($data['ver_id']);
        $data['game_sub_id'] = isset($ver_list['game_sub_id'])?$ver_list['game_sub_id']:0;
        $data['create_time'] = time();
        $data['month'] = isset($data['dateline'])?date('m',$data['dateline']):0;
        $data['aid']       = isset($this->common_click_log['aid'])?$this->common_click_log['aid']:0;
        $data['cid']       = isset($this->common_click_log['cid'])?$this->common_click_log['cid']:0;
        $data['login_time'] = isset($data['dateline']) ? $data['dateline'] : 0;
        $data['login_ver_id'] = isset($data['ver_id']) ? $data['ver_id'] : 10000;
        $data['channel_ver']       = isset($this->common_click_log['channel_ver'])?$this->common_click_log['channel_ver']:0;
        $data['channel_type']      = isset($this->common_click_log['channel_type'])?$this->common_click_log['channel_type']:0;
        $res = $db->saveReg($data);
        if(!is_array($res)){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 数据上报
     * */
    public function sendChannel($data)
    {
        $data['stype']       = 'register';
        $data['common_log'] = $this->common_click_log;
        $model = new Report();
        $res = $model->run($data);
        return $res;
    }

    public function updateUserVerid($data)
    {
        if (!$this->common_click_log) return true;
        $db = new storageAdv();
        $db->updateUserInfo($data,$this->common_click_log);
    }

    /**
     * 报送
     * @param $data
     * @return bool
     */
    public function sendActChannel($data)
    {
        $data['stype']       = 'install';
        $data['common_log'] = $this->common_click_log;
        $model = new Report();
        $res = $model->run($data);
        return $res;
    }

}