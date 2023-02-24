<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/30
 * Time: 11:45
 * 点击系统的激活匹配类，使用scylladb
 */
require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");

class ActivateModel {

    private $cass;
    const MATCH_CONFIG_TABLE = 'match_rule';  //渠道匹配规则配置表
    const MATCH_CHN_IOS_IDFA = 'match_ios_channel_idfa';
    const MATCH_CHN_IOS_MAC = 'match_ios_channel_mac';
    const MATCH_CHN_IOS_IP = 'match_ios_channel_ip';
    const MATCH_CHN_ANDROID_IMEI = 'match_android_channel_imei';
    const MATCH_CHN_ANDROID_UDID = 'match_android_channel_udid';
    const MATCH_CHN_ANDROID_IP = 'match_android_channel_ip';
    const MATCH_ACT_IOS = 'match_activate_ios';
    const MATCH_ACT_ANDROID = 'match_activate_android';
    const EFFECT_DAY = 90; //激活生效时间

    public function __construct()
    {
        try{
            $this->cass = CassandraDb::instance();
            $this->cass->ConnectCluster();
        }catch (Exception $e){

        }
    }


    /**
     * 匹配激活
     * @param $data 数组，传入gid,与 idfa or udid
     * @param $type 区分IOS安卓
     * @return bool
     */
    public function matchActivate($data, $type)
    {
            if($type == 'ios')
            {
                return $this->addData($data, static::MATCH_ACT_IOS,'idfa');
            }else{
                return $this->addData($data, static::MATCH_ACT_ANDROID,'udid');
            }
    }


    /**
     * 匹配设备的推广ID
     * @param $device
     * @param $type
     * @return advter_id or false
     */
    public function matchChannel($device,$type)
    {

        $device = $this->channelRule($device);
        if($type == 'ios'){
            return $this->matchChnIos($device);
        }else{
            return $this->matchChnAndroid($device);
        }

    }



     protected function matchChnIos($device)
    {

        foreach ($device as $k => $v)
        {
            switch ($k)
            {
                case 'idfa':
                    if($v == '00000000-0000-0000-0000-000000000000'){
                        break;
                    }
                    $table = static::MATCH_CHN_IOS_IDFA;
                    $idfa_arr = [
                        strtoupper(md5($v)) . '_' . $device['appId'],
                    ];
                    foreach ($idfa_arr as $key => $val) {
                        $map = [['idfa','=',$val]];
                        if($res = $this->getData($table, $map)){
                            return $res[0]['channel'];
                        }
                    }
                    break;
                case 'ip':
                    $table = static::MATCH_CHN_IOS_IP;
                    $map = [['ip','=',$v.'_'.$device['appId']]];
                    if($res = $this->getData($table, $map)){
                        return $res[0]['channel'];
                    }
                    break;
            }
        }

        return false;

    }

    protected function matchChnAndroid($device)
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
                    var_dump($imei_arr);exit;
                    foreach ($imei_arr as $key => $val) {
                        $map = [['imei','=',$val]];
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

    /**
     * 返回匹配需要数组
     * @param $device
     * @return mixed
     */
    protected function channelRule($device)
    {
        return $device;
    }

    /**
     * 查询cassandra
     * @param $table
     * @param $map
     * @return array
     */
    protected function getData($table, $map)
    {
        try{
            $res = $this->cass->Select('*', $table, $map,1);
            return $res;
        }catch (Exception $e){
            return false;
        }

    }


    /**
     * 将数据添加到cassandra
     * @param $data
     * @param $table
     * @param $field
     * @return bool
     */
    protected function addData($data, $table,$field)
    {
        $map = [
            ['gid','=',$data['gid']],
            [$field,'=',$data[$field]]
        ];
        try{
            $res = $this->cass->Select('*',$table, $map);
        }catch (Exception $e){
            return false;
        }
        if(count($res)>0){
            return false;
        }
        $matchData = [
            'gid'=>$data['gid'],
            $field=>$data[$field]
        ];

        $time = static::EFFECT_DAY * 86400;

        try{
            $this->cass->Insert($table, $matchData, $time);
        }catch (Exception $e){
            return false;
        }

        return true;
    }




}