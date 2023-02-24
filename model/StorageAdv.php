<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/8
 * Time: 15:17
 */

require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/common/common.php");

//自定义连接数据库
class storageAdv
{
    protected $db,$redis;
    protected $config = [];

    public function __construct()
    {
        $this->config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $this->redis = RedisModel::instance();
    }

    public function getInfo($ver_id = 0){
        if(empty($ver_id)) return false;
        $channel_ver_info = $this->redis->get($ver_id);
        $channel_ver_info = false;
        if(!$channel_ver_info){
            $model = Database::instance($this->config['adv_system']);
            $model->disconnect();
            $model = Database::instance($this->config['adv_system']);
            $model->query("SELECT * from adv_system.tbl_advert_channel_ver_info as a left  join adv_system.tbl_channel_click_config as b  on a.chn_config_id = b.id where a.id = {$ver_id}");
            $rs = $model->get();
            if(isset($rs[0]) && $rs[0]) {
                $channel_ver_info = json_encode($rs[0]);
                $this->redis->set($ver_id,$channel_ver_info,3600);
            }
            $model->disconnect();
        }
        return $channel_ver_info ? json_decode($channel_ver_info,true) : [];
    }

    public function updateUserInfo($data,$click_list)
    {
        $uid = $data['uid'];
        $user_name = $data['user_name'];
        $ver_id = (int)$click_list['channel_ver'];

//        if (!$uid || !$user_name || !$click_list || $data['device'] == 2 || $data['ver_id'] != 10000 || $data['ver_id'] != 0) return true;
        if (!$uid || !$user_name || !$click_list || $data['device'] == 2 || !in_array($data['ver_id'],[0,10000])) return true;
        $table_index = userTabIndex($user_name);

        $model = Database::instance($this->config['asjd_user']);
        $model->query("UPDATE reg_record SET channelId = {$ver_id}, sonChannel = {$ver_id} WHERE userId = {$uid}");
        $model->query("UPDATE user_index SET channelId = {$ver_id}, sonChannel = {$ver_id} WHERE uId = {$uid}");
        $model->query("UPDATE user_$table_index SET channelId = {$ver_id}, sonChannel = {$ver_id} WHERE uid = {$uid}");
        $model->disconnect();
    }


    public function checkOrder($order_id)
    {
        $model = Database::instance($this->config['asjd_user']);
        $model->disconnect();
        $model = Database::instance($this->config['asjd_user']);
        $model->query("SELECT * FROM `asgardstudio_order`.`order_proccess` WHERE `orderId` = '{$order_id}' ");
        $rs = $model->get();
        $model->disconnect();
        return $rs ? $rs[0] : [];
    }

    public function getVerInfo($ver_id = 0){
        if(empty($ver_id)) return false;
        $key = 'pay_ver_list'.$ver_id;
        $channel_ver_info = $this->redis->get($key);
        if(!$channel_ver_info){
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $model = Database::instance($config['adv_system']);
            $model->query("SELECT * from adv_system.tbl_advert_channel_ver_info where id = {$ver_id}");
            $rs = $model->get();
            $model->disconnect();
            if(isset($rs[0]) && $rs[0]) {
                $channel_ver_info = json_encode($rs[0]);
                $this->redis->set($key,$channel_ver_info,3600*12);
            }
        }
        return $channel_ver_info ? json_decode($channel_ver_info,true) : [];
    }

    public function getGameList($game_id)
    {
        $game_list = $this->redis->get($game_id);
        if(!$game_list){
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $model = Database::instance($config['asjd_user']);
            $model->query("SELECT * FROM `asgardstudio_admin`.`games` WHERE `gameId` = '{$game_id}' ");
            $rs = $model->get();
            if(isset($rs[0]) && $rs[0]) {
                $game_list = json_encode($rs[0]);
                $this->redis->set($game_id,$game_list,3600*12);
            }
        }
        return $game_list ? json_decode($game_list,true) : [];
    }

    public function getGameSubList($ver_id)
    {
        $key = 'ver_game_sub'.$ver_id;
        $game_sub_list = $this->redis->get($key);
        if(!$game_sub_list){
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $model = Database::instance($config['asjd_user']);
            $model->query("SELECT *,b.app_id as sdk_app FROM adv_system.tbl_advert_channel_ver_info AS a LEFT JOIN asgardstudio_admin.games_sub AS b ON a.game_sub_id = b.Id 
                                WHERE a.id = '{$ver_id}' ");
            $rs = $model->get();
            if(isset($rs[0]) && $rs[0]) {
                $game_sub_list = json_encode($rs[0]);
                $this->redis->set($key,$game_sub_list,3600*12);
            }
        }
        return $game_sub_list ? json_decode($game_sub_list,true) : [];
    }

}