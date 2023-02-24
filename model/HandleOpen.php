<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once (dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");

class handleOpen
{

    public function start($data)
    {

        $db = new storage();
        $db_old = new storageOld();
        $db_status = $this->insertDb($data, $db, $db_old);
//        $send_status = $this->sendChannel($data,$db);

        if ($db_status === true) {
            $this->writeLog($data);
            return true;
        } else {
            return false;
        }
    }


    /**
     * 写入日志
     * @param $data
     */
    public function writeLog($data)
    {
        $write = new writeFile();
        $write->write($data, 1);

    }


    /**
     * 将打开写入数据库
     * @param $data
     * @param $db
     * @return bool
     */
    public function insertDb($data, $db, $db_old)
    {
        $insert = [];

        if ($data['device'] == 'ios') {

            $insert['month'] = isset($data['dateline']) ? date('m', $data['dateline']) : 0;
            $insert['advter_id'] = isset($data['advter_id']) ? $data['advter_id'] : 0;
            $insert['mac'] = isset($data['mac']) ? $data['mac'] : '';
            $insert['version'] = isset($data['version']) ? $data['version'] : '';
            $insert['gid'] = isset($data['appId']) ? $data['appId'] : 0;
            $insert['idfa'] = isset($data['idfa']) ? $data['idfa'] : '';
            $insert['idfv'] = isset($data['idfv']) ? $data['idfv'] : '';
            $insert['sole_udid'] = isset($data['sole_udid']) ? $data['sole_udid'] : '';
            $insert['user_agent'] = isset($data['useragent']) ? $data['useragent'] : '';
            $insert['user_ip'] = isset($data['user_ip']) ? $data['user_ip'] : '127.0.0.1';
            $ipArr = IpToLocation($insert['user_ip']);
            $insert['country'] = $ipArr['country'];
            $insert['area'] = $ipArr['area'];
            $insert['dateline'] = isset($data['dateline']) ? $data['dateline'] : 0;
            $insert['serial'] = isset($data['serialId']) ? $data['serialId'] : '';

            if ($insert['idfa'] == '00000000-0000-0000-0000-000000000000') {
                $insert['idfa'] = $insert['user_ip'];
            }

            $res = $db->saveOpenIos($insert);
        } else {

            $insert['month'] = isset($data['dateline']) ? date('m', $data['dateline']) : 0;
            $insert['advter_id'] = isset($data['advter_id']) ? $data['advter_id'] : 0;
            $insert['udid'] = isset($data['udid']) ? $data['udid'] : '';
            $insert['ip'] = isset($data['user_ip']) ? $data['user_ip'] : '127.0.0.1';
            $insert['open_ver'] = isset($data['ver']) ? $data['ver'] : '';
            $insert['game_id'] = isset($data['appId']) ? $data['appId'] : 0;
            $insert['server_id'] = 0;
            $insert['create_date'] = isset($data['dateline']) ? $data['dateline'] : 0;
            $insert['imei'] = isset($data['imeiId']) ? $data['imeiId'] : '';

            $res = $db->saveOpenAndroid($insert);
        }

        if (!is_array($res)) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * 报送
     * @param $data
     * @return bool
     */
    public function sendChannel($data, $db)
    {
        $insert = [];
        $model = new Report();

        if ($data['device'] == 'ios') {
            $insert['stype'] = 'open';
            $insert['advter_id'] = isset($data['advter_id']) ? $data['advter_id'] : 0;
            $insert['idfa'] = isset($data['idfa']) ? $data['idfa'] : '';
            $insert['idfv'] = isset($data['idfv']) ? $data['idfv'] : '';
            $insert['finish_time'] = isset($data['dateline']) ? $data['dateline'] : 0;
            $insert['gid'] = isset($data['appId']) ? $data['appId'] : 0;
            $insert['ip'] = isset($data['user_ip']) ? $data['user_ip'] : '127.0.0.1';
            $insert['device'] = isset($data['device']) ? $data['device'] : 'ios';

            $insert['deviceinfo'] = isset($data['deviceinfo']) ? $data['deviceinfo'] : '';
            $insert['systeminfo'] = isset($data['systeminfo']) ? $data['systeminfo'] : '';
            $insert['netinfo'] = isset($data['netinfo']) ? $data['netinfo'] : '';
            $insert['screen'] = isset($data['screen']) ? $data['screen'] : '';

        } else {
            $insert['stype'] = 'open';
            $insert['advter_id'] = isset($data['advter_id']) ? $data['advter_id'] : 0;
            $insert['imei'] = isset($data['imeiId']) ? $data['imeiId'] : '';
            $insert['finish_time'] = isset($data['dateline']) ? $data['dateline'] : 0;
            $insert['gid'] = isset($data['appId']) ? $data['appId'] : 0;
            $insert['ip'] = isset($data['user_ip']) ? $data['user_ip'] : '127.0.0.1';
            $insert['device'] = isset($data['device']) ? $data['device'] : 'android';

            $insert['deviceinfo'] = isset($data['deviceinfo']) ? $data['deviceinfo'] : '';
            $insert['systeminfo'] = isset($data['systeminfo']) ? $data['systeminfo'] : '';
            $insert['netinfo'] = isset($data['netinfo']) ? $data['netinfo'] : '';
            $insert['screen'] = isset($data['screen']) ? $data['screen'] : '';

        }

        $res = $model->run($insert);
        return $res;

    }

}