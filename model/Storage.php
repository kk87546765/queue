<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/8
 * Time: 15:17
 */
require_once(dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/Cassmember.php");
require_once(dirname(dirname(__FILE__)) . "/model/Mysqlmember.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");


class storage
{
    protected $db,$redis;
    public function __construct()
    {
        $this->db = Database::instance();
        $this->redis = RedisModel::instance();
    }

    public function disconnect()
    {
        $this->db->disconnect();
    }

    /**
     * 返回db的链接符
     * @return Database
     */
    public function dbHandle()
    {
        return $this->db;
    }

    public function getUserInfo($uid)
    {
        $res = $this->db->query("select * from data_collection.tbl_register_log where uid={$uid}")->get();
        $info = isset($res[0])?$res[0]:[];
        return $info;
    }

    public function check_order($uid,$order_id)
    {
        if (!$order_id) return true;
        $sql = "SELECT * FROM data_collection.tbl_data_report_log where uid = {$uid} and order_id = '{$order_id}' and stype = 2  and callback_status = 1 limit 1";
        $res = $this->db->query($sql)->get();
        $info = isset($res[0])? true: false;
        return $info;
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


    /**
     * 判断是否设备首登,并入库
     * @param $cass
     * @param $gid
     * @param $udid_idfa
     * @param $ctime
     * @return int
     */
    public function isGameDeviceFirstLogin($cass,$gid,$udid_idfa,$ctime){
        $prodt_id=$this->getProductId((int)$gid);

        $key=md5($udid_idfa."_".(int)$prodt_id);
        $is_sole=0;
        $map = [
            ["udid_idfa_prodt", '=', $key]
        ];
        $firstLoginTime=$cass->matchGameDevice($map);
        if($firstLoginTime==0){
            $data=[
                "udid_idfa_prodt"=>$key
            ];
            $cass->addGameDevice($data);
            $is_sole=1;
        }
        return $is_sole;
    }

    /**
     * 获取素材Id对应的包号
     * @param $cid
     */
    public function getVerCid($cid){
        $redisTable="advsystem:cid_list";
        $res = '';
        $key = $cid;
        $key_timeout=$cid."_timeout";

        $cid_list = $this->redis->hget($redisTable,$key);
        $cid_timeout = $this->redis->hget($redisTable,$key_timeout);
        if (isset($_GET['debug']) && $_GET['debug'] == 1 ){
            var_dump($cid,$cid_list,$cid_timeout,(time()-$cid_timeout));
        }
        if($cid_list && (time()-$cid_timeout)<=1800){
            return $cid_list;
        }
        $list=$this->db->query("select * from adv.tbl_ad_channel_creative where cid='$cid'")->get();
        if($list){
            $res = json_encode($list[0],true);
            $this->redis->hset($redisTable,$key,$res);
            $this->redis->hset($redisTable,$key_timeout,time());
        }
        return $res;
    }

    /**
     * 获取cid、
     * @param $device 设备类型 1:ios 2:android
     * */
    public function getCid($info,$device = 1)
    {
        $list = ['cid'=>0,'aid'=>0,'channel_type'=>0];
        try{
            $cass = new cassmember();
            if ($device == 1){
                $creative_plan_id = $cass->matchCreativeIos($info);
            } else {
                $creative_plan_id = $cass->matchCreativeAndroid($info);
            }
            if ($creative_plan_id){
                $list['cid'] = $cid = isset($creative_plan_id['cid']) ? $creative_plan_id['cid'] : 0;
                if (isset($creative_plan_id['aid'])){
                    $aid_list = explode('_', $creative_plan_id['aid']);
                    $list['aid'] = isset($aid_list['0']) ? $aid_list['0'] : 0;
                    $list['channel_type'] = isset($aid_list['1']) ? $aid_list['1'] : 1;
                }
                $res = json_decode($this->getVerCid($cid),true);
                if ($res && $res['ver'] != $info['ver']){  //归因结果跟投放结果不一致，判断为归因失败
                    $list = ['cid'=>0,'aid'=>0,'channel_type'=>0];
                }
            }
        } catch(\Exception $e){
            $this->log('【'.date('Ymd H:i:s').'】'.$e->getMessage()."\r\n", $dir = 'cass/login/dberror/');
        }
        return $list;
    }

    //获取基础点击数据
    public function getCommonLog($data)
    {
        $cass = new cassmember();
        $match_str = $cass->matchChn($data); //归因，获取common_sign_key
        if (!$match_str) return false;

        $table = 'common_click_log';
        $map = [['sign_key', '=', $match_str['log_key']]];

        if($common_log = $cass->getData($table,$map)){
            return $common_log[0];
        }
        return false;
    }

    //获取基础点击数据
    public function getNewCommonLog($data)
    {
        $mysqlMember = new mysqlMember();
        $match_str = $mysqlMember->matchChn($data); //归因，获取common_sign_key
        if (!$match_str) return false;

        if($common_log = $mysqlMember->getClickInfo($match_str)){
            if ($this->check_ver($data,$common_log)){
                return $common_log;
            }
        }
        return false;
    }

    public function check_ver($data,$common_log)
    {
        if ($data['device'] == 1 && $data['game_id'] != $common_log['game_id']) return false; //ios归因，游戏不一致

        return true;
    }

    public function getInfo($ver_id = 0){
        if(empty($ver_id)) return false;
        $key = 'new_ver_info_key'.$ver_id;
        $channel_ver_info = $this->redis->get($key);
        if(!$channel_ver_info){
            $config = require(dirname(dirname(__FILE__)) . "/config/db_config.php");
            $model = Database::instance($config['adv_system']);
            $model->query("SELECT channel_ver,callback_func,game_sub_id,qd_id from adv_system.tbl_advert_channel_ver_info as a left  join adv_system.tbl_channel_click_config as b  on a.chn_config_id = b.id where a.id = {$ver_id}");
            $rs = $model->get();
//            $model->disconnect();
            if(isset($rs[0]) && $rs[0]) {
                $channel_ver_info = [
                    'qd_id'=>$rs[0]['qd_id'] ? $rs[0]['qd_id'] : 0,
                    'game_sub_id'=>$rs[0]['game_sub_id'] ? $rs[0]['game_sub_id'] : 0,
                    'channel_ver' => $rs[0]['channel_ver'] ? $rs[0]['channel_ver'] : '',
                    'callback_func' => isset($rs[0]['callback_func']) ? $rs[0]['callback_func'] : '',
                ];
                $channel_ver_info = json_encode($channel_ver_info);
                $this->redis->set($key,$channel_ver_info,3600*12);
            }
        }
        return $channel_ver_info ? json_decode($channel_ver_info,true) : [];
    }

    /**
     * 获取安卓用户信息
     * @param $uid
     * @param $gid
     * @return mixed
     */
    public function getUserInformation($uid,$game_id)
    {
        $key = 'user_info_'.$uid.'_'.$game_id;
        $user_info = $this->redis->get($key);
        if(!$user_info) {
            $res = $this->db->query("select ver_id,udid,imei,oaid,idfa,dateline,android_id,version,sdk_version from data_collection.tbl_register_log where uid={$uid} and game_id={$game_id} ")->get();
            $information = isset($res[0]) ? $res[0] : [];
            $user_info = json_encode($information);
            $this->redis->set($key,$user_info,3600*12);
        }
        return $user_info ? json_decode($user_info,true) : [];
    }

    /**
     * 写入注册日志
     * @return array
     */
    public function saveReg($data)
    {
        $validate = array('month','uid','prodt_id','game_id','game_sub_id','ver_id','channel_ver','dateline', 'login_time', 'idfa', 'oaid', 'android_id', 'imei', 'udid', 'ip', 'is_sole', 'channel_type', 'aid','cid','version','device','user_name','sdk_version','create_time','login_time','login_ver_id');
        $save_data = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $validate)) {
                $save_data[$k] = !is_null($v) ? $v : '';
            }
        }
        $db = Database::instance();
        $res = $db->table('data_collection.tbl_register_log')->insert($save_data);
        $this->disconnect();
        return $res;
    }

    /**
     * 写入登录日志
     * @return array
     */
    public function saveLogin($data)
    {
        $validate = array('month','uid','ip','imei','idfa','oaid', 'android_id', 'udid', 'login_time', 'dateline', 'reg_ver_id', 'login_ver_id', 'game_id','game_sub_id', 'device_info', 'first', 'sdk_type','user_agent','version','device','sdk_version','cid','aid','channel_type');
        $save_data = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $validate)) {
                $save_data[$k] = !is_null($v) ? $v : '';
            }
        }
        $db = Database::instance();
        $res = $db->table('data_collection.tbl_login_log')->insert($save_data);
        return $res;
    }

    /**
     * 写入激活日志
     * @return array
     */
    public function saveAct($data)
    {
        $validate = array('month','ver_id','channel_ver','udid','ip','game_id','dateline', 'imei', 'idfa', 'idfa', 'oaid', 'android_id', 'version', 'sdk_version','aid','cid','channel_type');
        $save_data = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $validate)) {
                $save_data[$k] = !is_null($v) ? $v : '';
            }
        }
        $res = $this->db->table('data_collection.tbl_activate_log')->insert($save_data);
        return $res;
    }

    /**
     * 保存用户登录游戏的信息-首登信息
     * @param $data
     */
    public function saveGameList($data)
    {
        $ver_id = is_numeric($data['ver_id']) ? $data['ver_id'] : 10000;
        if ($data['device'] == 1 ){
            $status = $this->getIosMemberInfo($data['uid'],$data['game_id']);
        } else {
            $status = $this->getMemberInfo($data['uid'],$ver_id,$data['game_id']);
        }
        if(!$status)
        {
            $user_info = $this->getUserInfo($data['uid']);
            $common_click_log = $this->getNewCommonLog($data); //数据匹配，获取点击数据
            if ($data['device'] == 1 && $common_click_log && in_array($data['ver_id'],[0,10000])) $ver_id = $common_click_log['channel_ver'];
            $db_add = [
                'ip'=>$data['ip'],
                'ver_id'=>$ver_id,
                'uid'=>$data['uid'],
                'game_id'=>$data['game_id'],
                'dateline'=>$data['login_time'],
                'login_time'=>$data['login_time'],
                'imei'=>isset($data['imei']) ? $data['imei'] : '',
                'udid'=>isset($data['udid']) ? $data['udid'] : '',
                'idfa'=>isset($data['idfa']) ? $data['idfa'] : '',
                'oaid'=>isset($data['oaid']) ? $data['oaid'] : '',
                'android_id'=>isset($data['android_id']) ? $data['android_id'] : '',
                'aid'=>$common_click_log ? $common_click_log['aid'] : 0,
                'cid'=>$common_click_log ? $common_click_log['cid'] : 0,
                'reg_ver_id'=>$user_info ? $user_info['ver_id'] : $ver_id,
                'channel_ver'=> $common_click_log ? $common_click_log['channel_ver'] : 10000,
                'channel_type'=>$common_click_log ? $common_click_log['channel_type'] : 0,
            ];
            $res = $this->saveMemberLogin($db_add);
            return $res;
        }else{
            $update = [
                'login_time'=>$data['login_time'],
            ];
            if ($data['device'] == 1){
                $update_map = ['uid'=>$data['uid'], 'game_id'=>$data['game_id']];
            } else {
                $update_map = ['uid'=>$data['uid'], 'ver_id'=>$ver_id];
            }
            $res = $this->db->table('data_collection.tbl_member_game_login')->update($update,$update_map);
            return $res;
        }
    }

    public function getMemberInfo($uid,$ver_id,$game_id)
    {
        $res = $this->db->query("select * from data_collection.tbl_member_game_login where uid={$uid} and ver_id={$ver_id} and game_id={$game_id}")->get();
        $information = isset($res[0])?$res[0]:null;
        return $information;
    }

    public function getIosMemberInfo($uid,$game_id)
    {
        $res = $this->db->query("select * from data_collection.tbl_member_game_login where uid={$uid} and game_id={$game_id}")->get();
        $information = isset($res[0])?$res[0]:null;
        return $information;
    }


    /**
     * 写入激活日志
     * @return array
     */
    public function saveMemberLogin($data)
    {
        $validate = array('uid','prodt_id','game_id','ver_id','reg_ver_id','channel_ver', 'dateline', 'login_time', 'imei', 'oaid', 'android_id', 'idfa', 'udid','aid','cid','channel_type','ip','is_sole');
        $save_data = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $validate)) {
                $save_data[$k] = !is_null($v) ? $v : 0;
            }
        }
        $res = $this->db->table('data_collection.tbl_member_game_login')->insert($save_data);
        return $res;
    }

    public function updateRegLoginTime($data)
    {
        if ($data['device'] == 1){
            $user_info = $this->getUserInformation($data['uid'],$data['game_id']);  //获取注册时间和注册广告位
            $data['ver_id'] = isset($user_info['ver_id'])?$user_info['ver_id']:10000;
        }

        $update = [
            'login_time'=>$data['login_time'],
            'login_ver_id'=>$data['ver_id'],
        ];
        $update_map = [
            'uid'=>$data['uid'],
        ];
        $res = $this->db->table('data_collection.tbl_register_log')->update($update,$update_map);
        return $res;
    }

    //写入设备登录表
    public function insDeviceLogin($data)
    {
        $res = $this->db->query("select * from data_collection.tbl_device_login where udid = '{$data['udid']}' and game_id = {$data['game_id']}")->get();
        if (!isset($res[0])){
            $save_data = [
                'udid'=>$data['udid'],
                'game_id'=>$data['game_id'],
                'dateline'=>$data['login_time'],
            ];
            $this->db->table('data_collection.tbl_device_login')->insert($save_data);
        }
    }

    public function is_TW_submit()
    {
        $is_submit = false;
        $num = rand(0,9);
        if (in_array($num,[1,2])){
            $is_submit = true;
        }
        return $is_submit;
    }

    public function check_os_type($ver)
    {

    }

    /**
     * 获取安卓用户信息
     * @param $uid
     * @param $gid
     * @return mixed
     */
    public function getLoginUserInfo($uid,$game_id,$ver_id)
    {
        $key = 'login_user_info_'.$uid.'_'.$game_id.'_'.$ver_id;
        $user_info = $this->redis->get($key);
        if(!$user_info) {
            $res = $this->db->query("select ver_id,udid,imei,oaid,idfa,dateline,android_id,'' as version , '' as sdk_version from data_collection.tbl_member_game_login where uid={$uid} and game_id={$game_id} and ver_id = {$ver_id} ")->get();
            $information = isset($res[0]) ? $res[0] : [];
            $user_info = json_encode($information);
            $this->redis->set($key,$user_info,3600*12);
        }
        return $user_info ? json_decode($user_info,true) : [];
    }

    public function getProductList($game_id)
    {
        $key = 'product_key'.$game_id;
        $product_list = $this->redis->get($key);
        if(!$product_list){
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $model = Database::instance($config['asjd_user']);
            $model->query("SELECT * FROM `asgardstudio_admin`.`games` as a left join  asgardstudio_admin.games_product as b on a.productId = b.productId where  a.`gameId` = '{$game_id}' ");
            $rs = $model->get();
            if(isset($rs[0]) && $rs[0]) {
                $product_list = json_encode($rs[0]);
                $this->redis->set($key,$product_list,3600*12);
            }
        }
        return $product_list ? json_decode($product_list,true) : [];
    }

}