<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once (dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/model/StorageRoleinfo.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");

class handleUserinfo
{
    private $common_click_log = [];

    public function start($data)
    {
        $db = new storageRoleinfo();
        $this->dealData($data, $db);
        $db->disconnect();
        return true;
//        $this->writeLog($data);
    }

    public function dealData($data, $db)
    {
        $type = isset($data['type']) ? $data['type'] : 0;
        $keyData = [
            'uid' => $data['Uid'],
            'reg_gameid' => $data['regGameId'],
            'login_gameid' => $data['gameId'],
            'role_id' => $data['roleID'],
        ];
        //数据有错误则跳出
        foreach ($keyData as $k =>$v){
            if($v == '' || $v == 0){
                return true;
            }
        }
        $updateData = $keyData;
        $updateData['user_account'] = $data['userName'];
        $updateData['reg_ver_id'] = $data['channelId'];
        $updateData['reg_dateline'] = $data['regDateline'];
        $updateData['login_ver_id'] = $data['loginVerid'];
        $updateData['login_ip'] = $data['loginIp'];
        $updateData['pay_level'] = $data['payLevel'];
        $updateData['role_id'] = $data['roleID'];
        $updateData['role_name'] = $data['roleName'];
        $updateData['role_level'] = $data['roleLevel'];
        $updateData['server_id'] = $data['serverId'];
        $updateData['server_name'] = $data['serverName'];
        if (isset($data['roleZsLevel'])) $updateData['role_zs_level'] = $data['roleZsLevel'];
        if (isset($data['roleCreatetime']) || $data['roleCreatetime'] != 0) $updateData['role_create_dateline'] = $data['roleCreatetime'];

        switch ($type) {
            case 1://选择区服
                return true;
                break;
            case 2://创角
                if (!isset($data['roleCreatetime']) || $data['roleCreatetime'] == '' || $data['roleCreatetime'] == 0) {
                    $updateData['role_create_dateline'] = $data['enterTime'];
                }
                $updateData['login_last_dateline'] = $data['enterTime'];
                break;
            case 3://登录
                $updateData['login_last_dateline'] = $data['enterTime'];
                break;
            case 4://升级
                $updateData['levelup_time'] = $data['enterTime'];
                break;
            case 0://无记录
                //旧的数据没有 创角时间，就用注册时间 最后登录时间
                $updateData['role_create_dateline'] = $data['regDateline'];

                $logintime = $db->getLoginInfo($keyData['uid'], $keyData['login_gameid']);
                if($logintime > 0) {
                    $updateData['login_last_dateline'] = $logintime;
                }
                break;
        }
        return $db->saveRoleinfo($keyData, $updateData);
    }

    /**
     * 写入日志信息
     * */
    public function writeLog($data)
    {
        $write = new writeFile();
        $write->write($data, 301);
    }

}