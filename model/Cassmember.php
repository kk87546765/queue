<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 14:10
 */
require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");
require_once(dirname(dirname(__FILE__)) . "/common/common.php");

class cassmember
{
    /*const MATCH_CHN_IOS_IDFA = 'match_ios_channel_idfa';
    const MATCH_CHN_IOS_MAC = 'match_ios_channel_mac';
    const MATCH_CHN_IOS_IP = 'match_ios_channel_ip';
    const MATCH_GAME_DEVICE_SOLE = 'match_device_product_sole';//原来是 match_game_device_sole
    const MATCH_CHN_ANDROID_IMEI = 'match_android_channel_imei';
    const MATCH_CHN_ANDROID_UDID = 'match_android_channel_udid';
    const MATCH_CHN_ANDROID_IP = 'match_android_channel_ip';

    const MATCH_CREATIVE_ANDROID_IMEI = 'match_android_channel_imei_creative';
    const MATCH_CREATIVE_ANDROID_OAID = 'match_android_channel_oaid_creative';
    const MATCH_CREATIVE_ANDROID_IP = 'match_android_channel_ip_creative';
    const MATCH_CREATIVE_IOS_IDFA = 'match_ios_channel_idfa_creative';
    const MATCH_CREATIVE_IOS_IP = 'match_ios_channel_ip_creative';*/

    const CHECK_CONNECT   = 'check_connect';
    const MATCH_IDFA = 'match_channel_idfa';
    const MATCH_IMEI = 'match_channel_imei';
    const MATCH_OAID = 'match_channel_oaid';
    const MATCH_IP   = 'match_channel_ip';
    const MATCH_ANDROID_ID = 'match_channel_android_id';

    protected $cass;

    const MEMBER_GAME = 'app_common_member_product';//用户产品登录表， 原来是 app_common_member_game

    public function __construct()
    {
        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $config_key = [1,2,3];
        shuffle( $config_key);
        $i = 0;
        $flag = false;
        foreach ($config_key as $k=>$v){
            $key = 'cassandra';
            try{
                $scylla_key = $key.$v;
                $scylla_config = $config[$scylla_key];
                $this->cass = CassandraDb::instance($scylla_config);
                $check_conncet = $this->check_connect();
                if (!$check_conncet)  $this->cass->ConnectCluster();
                $flag = true;
                break;
            }catch(\Exception $e){
                $i ++;
                CassandraDb::clean_instance();
                $this->log(date('Y-m-d H:i:s').$scylla_key.':error.'.$e->getMessage().',connect_times=' . $i . "\r\n", $dir = 'cass/login/');
            }
        }
        if (true === $flag && $i > 1)
        {
            $this->log(date('Ymd H:i:s').'retry_success,connect_times=' . $i . "\r\n", $dir = 'cass/login/');
        }

    }

    //检查能否查询成功
    public function check_connect()
    {
        $test_data = $this->cass->Select('*', static::CHECK_CONNECT, '', 1);
        return is_array($test_data) ? true : false;
    }

    public function getMemberGame($data)
    {
        $sql = self::getSelectLastSql('*', static::MEMBER_GAME, $data);
        try{
            $status =  $this->cass->Select('*', static::MEMBER_GAME, $data);
        }catch(\Exception $e){
            $this->log('【'.date('Ymd H:i:s').'】'.$e->getMessage().' SQL:'.$sql."\r\n", $dir = 'cass/login/');
            $status = false;
        }

        return $status;

    }

    public function addMemberGame($data)
    {
        try{
            $status =  $this->cass->Insert(static::MEMBER_GAME, $data);
        }catch(\Exception $e){
            $this->log('【'.date('Ymd H:i:s').'】'.$e->getMessage()."\r\n", $dir = 'cass/login/');
            $status = false;
        }
        return $status;
    }

    public function delMemberGame($map)
    {
        $this->cass->Delete(static::MEMBER_GAME,$map);
    }

    /**
     * 查找首登设备时间
     * @param $prodt_id
     * @param $udid_idfa
     * @return int
     */
    public function matchGameDevice($map){
        $res = $this->getData(static::MATCH_GAME_DEVICE_SOLE, $map);
        if($res){
            return 1;
        }else{
            return 0;
        }
    }



    /**
     * 添加首登设备记录
     * @param $map
     */
    public function addGameDevice($data){
        try{
            $status =  $this->cass->Insert(static::MATCH_GAME_DEVICE_SOLE, $data);
        }catch(\Exception $e){
            $this->log('【'.date('Ymd H:i:s').'】'.$e->getMessage()."\r\n", $dir = 'cass/login/');
            $status = false;
        }
        return $status;
    }

    /**
     * 更新首登设备记录
     * @param $data
     * @param $map
     * @return bool
     */
    public function updateGameDevice($data,$map){
        try{
            $status =  $this->cass->Update(static::MATCH_GAME_DEVICE_SOLE, $data,$map);
        }catch(\Exception $e){
            $this->log('【'.date('Ymd H:i:s').'】'.$e->getMessage()."\r\n", $dir = 'cass/login/');
            $status = false;
        }
        return $status;
    }


    /**
     * 查询cassandra
     * @param $table
     * @param $map
     * @return array
     */
    public function getData($table, $map)
    {
        $sql = self::getSelectLastSql('*', $table, $map,1);
        try{
            $res = $this->cass->Select('*', $table, $map);
        }catch (\Exception $e){
            $this->log(date('Ymd H:i:s').$e->getMessage().' SQL:'.$sql."\r\n", $dir = 'cass/login/');
            $res = false;
        }
        return $res;
    }

    /*
     * 获取scylla查询sql
     * @param string $fields 要查的字段
     * @param string $table 表名
     * @param array TableFilters 条件数组（二维）$map = [['id','=',1]];
     * @param int $limit 条数
     * @return array
     * */
    public function getSelectLastSql($fields,$table, $TableFilters='',$limit = false){
        $QueryString = "SELECT ".$fields." FROM ".$table ;

        if(is_array($TableFilters) && sizeof($TableFilters) > 0) {
            if($FilterOptions = self::GetFilterQuery($TableFilters)) {
                $QueryString .= " WHERE " . $FilterOptions;
            }
        }
        if($limit){
            $QueryString .=" limit {$limit}";
        }
        return $QueryString;
    }

    /**
     * Create filter query from table
     * @param type $Fields
     * @return boolean|string
     */
    public static function GetFilterQuery($Fields) {
        if(!is_array($Fields))
            return false;
        $FilterString = "";
        foreach($Fields as $key=>$val) {
            $FieldValue = isset($val[2]) ? $val[2] : '';
            $FieldValue = is_int($FieldValue) ? $FieldValue : ($FieldValue<>'' ? "'".$FieldValue."'" : '');
            $RowString = ($FilterString<>'' ? (isset($val[3]) ? " ".$val[3] : ' AND ' ) : "");
            $RowString .= " " .$val[0] ." ".$val[1]." ".(strlen($val[1]) > 3 ? '' : $FieldValue);
            $FilterString .= $RowString;
        }
        return $FilterString;
    }

    public function log($msg, $dir = '', $file = '') {
        $maxsize = 2 * 1024 * 1024;
        $base_dir = dirname(dirname(__FILE__)).'/log/';
        !empty($dir) && $base_dir .= $dir;

        if(!is_dir($base_dir)) {
            mkdir($base_dir, 0777, true);
        }

        empty($file) && $file = date('Ymd').'.log';

        $path = $base_dir.$file;
        //检测文件大小，默认超过2M则备份文件重新生成 2*1024*1024
        if(is_file($path) && $maxsize <= filesize($path) )
            rename($path,dirname($path).'/'.time().'-'.basename($path));

        error_log($msg, 3, $path);
    }

    public function matchChnAndroid($device)
    {
        foreach ($device as $k => $v)
        {
            switch ($k)
            {
                case 'imei':
                    $table = static::MATCH_CHN_ANDROID_IMEI;
                    $imei_arr = [
                        strtoupper(md5($v)) . '_' . $device['ver'],
                        strtoupper(md5($v)) . '_' . $device['appId'],
                    ];
                    foreach ($imei_arr as $key => $val) {
                        $map = [['imei','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0]['channel'];
                        }
                    }
                    break;
                case 'udid':
                    $table = static::MATCH_CHN_ANDROID_UDID;
                    $udid_arr = [
                        strtoupper(md5($v)) . '_' . $device['ver'],
                        strtoupper(md5($v)) . '_' . $device['appId'],
                    ];
                    foreach ($udid_arr as $key => $val) {
                        $map = [['udid','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0]['channel'];
                        }
                    }
                    break;
                case 'ip':
                    $table = static::MATCH_CHN_ANDROID_IP;
                    $ip_arr = [
                        $v . '_' . $device['ver'],
                        $v . '_' . $device['appId'],
                    ];
                    foreach ($ip_arr as $key => $val) {
                        $map = [['ip','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0]['channel'];
                        }
                    }
                    break;
            }
        }

        return false;

    }


    public function matchCreativeAndroid($device)
    {
        foreach ($device as $k => $v)
           {
            switch ($k)
            {
                case 'imei':
                    $table = static::MATCH_CREATIVE_ANDROID_IMEI;
                    $imei_arr = [
                        strtoupper(md5($v)) . '_' . $device['ver'],
                        strtoupper(md5($v)) . '_' . $device['appId'],
                    ];
                    foreach ($imei_arr as $key => $val) {
                        $map = [['key','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0];
                        }
                    }
                    break;
                case 'udid':
                    $table = static::MATCH_CREATIVE_ANDROID_OAID;
                    $udid_arr = [
                        strtoupper(md5($v)) . '_' . $device['ver'],
                        strtoupper(md5($v)) . '_' . $device['appId'],
                    ];
                    foreach ($udid_arr as $key => $val) {
                        $map = [['key','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0];
                        }
                    }
                    break;

                case 'ip':
                    $table = static::MATCH_CREATIVE_ANDROID_IP;
                    $ip_arr = [
                        $v . '_' . $device['ver'],
                        $v . '_' . $device['appId'],
                    ];
                    foreach ($ip_arr as $key => $val) {
                        $map = [['key','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0];
                        }
                    }
                    break;
            }
        }

        return false;
    }

    public function matchCreativeIos($device)
    {
        foreach ($device as $k => $v)
        {
            switch ($k)
            {
                case 'idfa':
                    $table = static::MATCH_CREATIVE_IOS_IDFA;
                    $imei_arr = [
                        strtoupper(md5($v)) . '_' . $device['appId'],
                    ];
                    foreach ($imei_arr as $key => $val) {
                        $map = [['key','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0];
                        }
                    }
                    break;
                case 'ip':
                    $table = static::MATCH_CREATIVE_IOS_IP;
                    $ip_arr = [
                        $v . '_' . $device['appId'],
                    ];
                    foreach ($ip_arr as $key => $val) {
                        $map = [['key','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0];
                        }
                    }
                    break;
            }
        }

        return false;
    }



    public function matchChn($device)
    {
        foreach ($device as $k => $v)
        {
            if (!$v) continue;
            switch ($k)
            {
                case 'oaid':
                    $table = static::MATCH_OAID;
                    $str = checkParam($v);
                    $res = $this->getSignKey($device,$table,$str);
                    if ($res) return $res;
                    break;
                case 'imei':
                    $table = static::MATCH_IMEI;
                    $str = checkParam($v);
                    $res = $this->getSignKey($device,$table,$str);
                    if ($res) return $res;
                    break;
                case 'idfa':
                    $table = static::MATCH_IDFA;
                    $str = checkParam($v);
                    $res = $this->getSignKey($device,$table,$str);
                    if ($res) return $res;
                    break;
                /*case 'android_id':
                    $table = static::MATCH_ANDROID_ID;
                    $str = checkParam($v);
                    $res = $this->getSignKey($device,$table,$str);
                    if ($res) return $res;
                    break;*/
                case 'ip':
                    $table = static::MATCH_IP;
                    $res = $this->getSignKey($device,$table,$v);
                    if ($res) return $res;
                    break;
            }
        }

        return false;
    }

    public function getSignKey($device,$table,$str){
        $arr = [
            md5($str.$device['ver_id']),
            md5($str.$device['game_id']),
        ];
        foreach ($arr as $key => $val) {
            $map = [['key','=',$val]];
            if($res = $this->getData($table, $map)){
                return $res[0];
            }
        }
        return false;
    }

}