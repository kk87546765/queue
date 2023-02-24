<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once(dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");

class handleLevel
{
    const TABLE_NAME = 'tbl_role_levelup_log';

    public function start($data)
    {

        $config = require(dirname(dirname(__FILE__)) . "/config/db_config.php");
        $db = Database::instance($config['data_collection_reward']);

        $db->disconnect();
        $db = Database::instance($config['data_collection_reward']);

        $db_status = $this->checkUserLevel($data,$db);

        $db->disconnect();

//        $this->writeLog($data);


//        if(empty($cp_data)) return false;
        return true;
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
        $write->write($data, 300);

    }


    /**
     * 将打开写入数据库
     * @param $data
     * @param $db
     * @return bool
     */
    public function insertDb($data, $db)
    {

        if(method_exists (new self(),'insertDb'.$data['appid'])){
            $function = 'insertDb'.$data['appid'];
            $res = $this->$function($data,$db);
            return $res;
        }else{
            return false;
        }

    }

    public function insertDb_103071($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103071_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    //获取用户表索引值
    function userTabIndex($uname, $num = 255)
    {
        $uname = strtolower($uname);
        $c1 = substr($uname, 0, 1);
        $c2 = substr($uname, -1);
        $n = ord($c1) + ord($c2);
        $l = strlen($uname);
        $n += $l * $l;
        return $n % $num;
    }

    function checkUserLevel($data,$db)
    {

        if(method_exists (new self(),'checkUserLevel_'.$data['appid'])){
            $function = 'checkUserLevel_'.$data['appid'];
            $res = $this->$function($data,$db);
            return $res;
        }else{
            return false;
        }


    }

    //网赚
    function checkUserLevel_103071($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;
        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103071($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103071($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103071_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103071($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }
                $info_data = $rec_data_arr['data'][0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103071($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103071_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }


    function checkCpData_103071($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;
        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);

        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


//    //网赚
//    function checkUserLevel_103071($data)
//    {
//        //1转之后查cp数据
//        if($data['trans_level'] < 1){
////            return false;
//        }
//        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;
//        $url_data['trans_level'] = $data['trans_level'] ;
//        $url_data['level'] = $data['role_level'] ;
//        $url_data['role_id'] = $data['role_id'] ;
//        $url_data['uid'] = $data['uid'] ;
//
//        $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
//        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
//        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
//
//        $url_data['sign'] = $sign;
//        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';
//
//        $rec_data = $this->curl_init_post($url,$url_data);
//
//        $rec_data_arr = json_decode($rec_data,true);
//
//        if($rec_data_arr['code'] == 0){
//
//            $info_data = $rec_data_arr['data'][0];
//
//            $trans_limit = isset($limit[$data['trans_level']])? $limit[$data['trans_level']] : 1;
//
//            if(abs($data['trans_level'] - $info_data['trans_level']) <= 1 && abs($url_data['level'] - $info_data['role_level']) <= $trans_limit  ){ //和cp数据对比，同转生
//
//                return $info_data;
//            }else{
//                return false;
//            }
//        }else{
//            return false;
//        }
//    }

    function checkUserLevel_10036($data)
    {

        //1转之后查cp数据
        if($data['trans_level'] < 1){
//            return false;
        }

        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;
        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

        $limit = [1=>5,2=>3,3=>3,4=>3,5=>3,6=>3,7=>3,8=>3,9=>3,10=>3,11=>3,12=>3];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);

        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        if($rec_data_arr['code'] == 0){

            $info_data = $rec_data_arr['data'][0];

            $trans_limit = isset($limit[$data['trans_level']])? $limit[$data['trans_level']] : 1;

            if(abs($data['trans_level'] - $info_data['trans_level']) <= 1 && abs($url_data['level'] - $info_data['role_level']) <= $trans_limit  ){ //和cp数据对比，同转生

                return $info_data;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }


    function curl_init_post($url, $params,$timeout = 180, $header = array())
    {
        $ch = curl_init();
        // 设置 curl 相应属性
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        if($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //定义请求类型
        }
        $returnTransfer = curl_exec($ch);
        curl_close($ch);
        return $returnTransfer;
    }




    //网赚
    function checkUserLevel_103075($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103075($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103075($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103075_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103075($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }

                    $info_data = $rec_data_arr['data'][0];

                }else{
                    return false;
                }

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103075($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103075_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103075($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103071;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103075($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103075_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }






    //网赚
    function checkUserLevel_103077($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103077($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103077($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103077_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103077($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }
                $info_data = $rec_data_arr['data'][0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103077($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103077_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103077($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103071;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103077($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103077_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }



    /************************103083(555)开始*********************************/



    //网赚
    function checkUserLevel_103083($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103083($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103083($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103083_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103083($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }

                    $info_data = $rec_data_arr['data'][0];
                }else{
                    return false;
                }
                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103083($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103083_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103083($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103071;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103083($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103083_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }
    /************************103083结束*********************************/



    /************************103085(555)开始*********************************/



    //网赚
    function checkUserLevel_103085($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103085($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103085($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103085_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103085($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = $rec_data_arr['data'][0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103085($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103085_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103085($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103071;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103085($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103085_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }
    /************************103085结束*********************************/


    /************************103091(555)开始*********************************/



    //网赚
    function checkUserLevel_103091($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103091($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103091($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103091_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103091($data);

            if ($rec_data_arr['code'] == 0) {

                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }
                $info_data = $rec_data_arr['data'][0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103091($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103091_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103091($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103071;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyu2/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103091($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103091_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }
    /************************103091结束*********************************/



    /************************103087(至尊GM)开始*********************************/

    function checkUserLevel_103087($data,$db)
    {


        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103087($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['rebirth_level'=>0,'level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常


                if (abs((int)$data['trans_level'] - $info_data['rebirth_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['rebirth_level'];
                    //插入转生表数据
                    $this->insertDb_103087($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (((int)$data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103087_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103087($data);

            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                    $info_data = $rec_data_arr['data'][0];

                }else{
                    return false;
                }

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs((int)$data['trans_level'] - $info_data['rebirth_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['rebirth_level'];
                    //插入转生表数据
                    $this->insertDb_103087($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (((int)$data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['level'];
                    //插入等级日志表数据
                    $this->insertDb_103087_2($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103087($data)
    {

//        $uid = 11700725; //聚合用户id
//        $server_id = '500001';//区服id

        $uid = $data['uid'];
        $server_id = $data['server_id'];
        //uid转换

        $url = 'http://data-service.tanwan.com/customerService/user-info/search-uid';
        $secret = 'skHVv95EI7DG1sAzdNZZxlFDyG3IKCre';
        $data = [
            'from' => 'zhangWan',
            'ts' => time(),
            'openid_list'=> json_encode([$uid])
        ];
        ksort($data);
        $data['sign'] = md5($secret . $data['ts']);
        //echo $url . '?' . http_build_query($data);exit;


        list($res, $resData) = $this->post_curl($url . '?' . http_build_query($data), []);

        $result_arr = json_decode($resData, true);

        if($res === 200 && $result_arr['code'] === 200 && !empty($result_arr['data'])){//证明有用户uid返回

            $url = 'http://oapi.jinzegs.com/api/ServerRole/getPlayerRoleList';
            $secret = 'EqLX2zFrnrGh3ARR';
            $data = [
                'game_id' => 8,
                'channel_id' => 5,
                'srv_id' => $server_id,
                'username' => $result_arr['data'][$uid],
                'time' => time(),
            ];
            ksort($data);
            $data['sign'] = md5(urldecode(http_build_query($data)) . $secret);
            //    echo $url . '?' . http_build_query($data);exit;
            list($res, $resData) = $this->post_curl($url, $data);
            $result_arr = json_decode($resData, true);


            return $result_arr;
        }else{
            return false;
        }
    }


    public function insertDb_103087($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = !empty($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = !empty($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103087_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    //模拟post提交函数
    function post_curl($post_url, $post_arr, $cookie = false, $header = false)
    {
        is_array($post_arr) && $post_arr = urldecode(http_build_query($post_arr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_arr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        if (strpos($post_url, 'https') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $header && curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $cookie && curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($ch);
        return [$httpCode, $response];
    }
    /************************103087(至尊GM)结束*********************************/


    /************************103092(555专服，接口用https://lcwslogpy.guyuncq.com/youyuzf/api?m=Player&fn=actors,appid用103092)开始*********************************/



    //网赚
    function checkUserLevel_103092($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103092($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103092($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103092_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103092($data);

            if ($rec_data_arr['code'] == 0) {

                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }
                $info_data = $rec_data_arr['data'][0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103092($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103092($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103092($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103092;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/youyuzf/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103092($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103092_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }
    /************************103092结束*********************************/

    /************************103097(555专服返利板，接口用https://lcwslogpy.guyuncq.com/zhangwxfb/api?m=Player&fn=actors,appid用103097)开始*********************************/



    //网赚
    function checkUserLevel_103097($data,$db)
    {

        $sql = "select  `role_id`,`server_id`,max(`trans_level`) as trans_level  from tbl_role_trans_level_log where appid='{$data['appid']}' and uid={$data['sdk_uid']} limit 1";
        $res = $db->query($sql)->get();

        $data['cp_role_level'] = 0;

        //判断游戏id和uid是否已存在
        if (isset($res[0]['trans_level'])) {

            //判断上报数据是否和存在的数据相匹配
            if ($data['role_id'] != $res[0]['role_id'] || $data['server_id'] != $res[0]['server_id']) {
                return false;
            }

            $rec_data_arr = $this->checkCpData_103097($data);


            if ($rec_data_arr['code'] == 0) {
                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }

                $info_data = isset($rec_data_arr['data'][0]) ? $rec_data_arr['data'][0] : ['trans_level'=>0,'role_level'=>0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103097($data, $db);
                }


                //判断cp角色等级数据和上报角色等级数据差距是否正常,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    if($data['type'] != 'login'){
                        $this->insertDb_103097_2($data, $db);
                    }

                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        } else {

            $rec_data_arr = $this->checkCpData_103097($data);

            if ($rec_data_arr['code'] == 0) {

                if(is_array($rec_data_arr['data'])) {
                    foreach ($rec_data_arr['data'] as $k1 => $v1) {
                        if ($v1['role_id'] == $data['role_id']) {
                            $rec_data_arr['data'][0] = $v1;
                            break;
                        }
                    }
                }else{
                    return false;
                }
                $info_data = $rec_data_arr['data'][0];

                //判断cp转生等级数据和上报转生等级数据差距是否正常
                if (abs($data['trans_level'] - $info_data['trans_level']) <= 1) { //和cp数据对比
                    $data['cp_trans_level'] = $info_data['trans_level'];
                    //插入转生表数据
                    $this->insertDb_103097($data, $db);
                }

                //判断cp角色等级数据和上报角色等级数据差距是否正常 ,并且0转80级前都能插入
                if (($data['trans_level'] == 0 && $data['role_level']<= 80) || abs($info_data['role_level'] - $data['role_level']) <= 10) {
                    $data['cp_role_level'] = $info_data['role_level'];
                    //插入等级日志表数据
                    $this->insertDb_103097($data, $db);
                }

                $db->disconnect();

                return true;

            } else {
                return false;
            }
        }
    }



    function checkCpData_103097($data)
    {
        //数据都匹配的话则进一步查询cp匹配
        $url_data['app_id'] = $data['appid'] ? $data['appid'] : 0 ;

        $url_data['app_id'] = 103097;

        $url_data['trans_level'] = $data['trans_level'] ;
        $url_data['level'] = $data['role_level'] ;
        $url_data['role_id'] = $data['role_id'] ;
        $url_data['uid'] = $data['uid'] ;

//            $limit = [0=>10,1=>5,2=>5,3=>5,4=>5,5=>5,6=>5,7=>5,8=>5,9=>5,10=>5,11=>5,12=>5];//转生等级浮动配置
        $key = 'd7ac7590e20489d4a4ee342e5552ece7';
        $sign = md5($url_data['app_id'].$url_data['trans_level'].$url_data['level'].$url_data['role_id'].$url_data['uid'].$key);
        $url_data['sign'] = $sign;
        $url = 'https://lcwslogpy.guyuncq.com/zhangwxfb/api?m=Player&fn=actors';

        $rec_data = $this->curl_init_post($url,$url_data);

        $rec_data_arr = json_decode($rec_data,true);

        return $rec_data_arr;
    }


    public function insertDb_103097($data, $db)
    {
        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;
        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;

        $sql = "INSERT ignore tbl_role_trans_level_log
        (uid,appid,role_id,server_id,trans_level,cp_trans_level,add_time,levelup_time) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},
        {$insert['trans_level']},{$insert['cp_trans_level']},{$insert['add_time']},{$insert['levelup_time']}
        ) ";

        $res = $db->execute($sql);

        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }


    public function insertDb_103097_2($data, $db)
    {

        $insert = [];

        $insert['uid'] = isset($data['sdk_uid']) ? $data['sdk_uid']: 0;
        $insert['appid'] = isset($data['appid']) ? $data['appid'] : 0;
        $insert['role_id'] = isset($data['role_id']) ? $data['role_id'] : 0;

        $insert['server_id'] = isset($data['server_id']) ? $data['server_id'] : 0;
        $insert['role_level'] = isset($data['role_level']) ? $data['role_level'] : 0;
        $insert['add_time'] = time();
        $insert['levelup_time'] = isset($data['levelup_time']) ? $data['levelup_time'] : 0;
        $insert['trans_level'] = isset($data['trans_level']) ? $data['trans_level'] : 0;
        $insert['extension'] = isset($data['extension']) ? $data['extension'] : '';
        $insert['cp_role_level'] = isset($data['cp_role_level']) ? $data['cp_role_level'] : 0;
        $insert['cp_trans_level'] = isset($data['cp_trans_level']) ? $data['cp_trans_level'] : 0;
//        $insert['update_time'] = isset($data['update_time']) ? $data['update_time'] : 0;

        $index = $this->userTabIndex($insert['uid']);

        $sql = "INSERT INTO " .self::TABLE_NAME.$index.
            "(uid,appid,role_id,server_id,role_level,add_time,levelup_time,extension,cp_role_level) VALUES
        ({$insert['uid']},{$insert['appid']},{$insert['role_id']},{$insert['server_id']},{$insert['role_level']},
        {$insert['add_time']},{$insert['levelup_time']},'{$insert['extension']}',{$insert['cp_role_level']}
        ) ON DUPLICATE KEY UPDATE `levelup_time`= {$data['levelup_time']}
        ";

        $res = $db->execute($sql);

        $db->disconnect();
        if ($res['affected_row'] >= 0) {
            return true;
        } else {
            return false;
        }
    }
    /************************103097结束*********************************/
}