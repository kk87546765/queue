<?php
/**
 * 登陆数据
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


class handleSign
{
    public function start($data)
    {
        $this->changeParam($data);
        $this->writeLog($data);
        $this->insertDb($data);
        $this->insertMemberProductLogin($data);
        $this->updateRegLoginTime($data);
        $this->insDeviceLogin($data);
        $submitTw = new submitTw(); //贪玩数据上报
        $submitTw->index('login',$data);
        return true;
    }

    /*
     * 参数转换
     * */
    public function changeParam(&$data)
    {
        $fixed = ['oaid'=>'udid','user_name'=>'userAcount','idfa'=>'regImei', 'android_id'=>'androidId','login_ver_id'=>'ver_id', 'game_id'=>'appid','uid'=>'uId','dateline'=>'regTime','version'=>'android_version'];
        foreach ($data as $k=>$v){
            foreach ($fixed as $key=>$val){
                if ($k == $val) $data[$key] = $v; //参数转化
            }
        }
    }

    public function writeLog($data)
    {
        $write = new writeFile();
        $write->write($data,4);
    }

    public function insertDb($data)
    {
        $db = new storage();
        $user_info = $db->getUserInformation($data['uid'],$data['game_id']);  //获取注册时间和注册广告位
        $ver_list = $db->getInfo($data['ver_id']);
        $data['game_sub_id'] = isset($ver_list['game_sub_id'])?$ver_list['game_sub_id']:0;
        $data['reg_ver_id']    = isset($user_info['ver_id'])?$user_info['ver_id']:10000;
        if ($data['device'] == 1){
            $data['login_ver_id']  = isset($user_info['ver_id'])?$user_info['ver_id']:10000;
        }
        $data['dateline']    = isset($user_info['dateline'])?$user_info['dateline'] : $data['login_time'];
        $data['month'] = isset($data['login_time'])?date('m',$data['login_time']):0;
        $res = $db->saveLogin($data);
        if(!is_array($res)){
            echo $res.__METHOD__;
            return false;
        }else{
            return true;
        }
    }

    public function insertMemberProductLogin($data)
    {
        if (!$data['uid']) return true;

        $db = new storage();
        $res = $db->saveGameList($data);
        if(!is_array($res)){
            return false;
        }else{
            return true;
        }
    }

    public function updateRegLoginTime($data)
    {
        $db = new storage();
        $db->updateRegLoginTime($data);
    }

    public function insDeviceLogin($data)
    {
        $db = new storage();
        $db->insDeviceLogin($data);
    }

}