<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/8
 * Time: 15:17
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/Cassmember.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");
require_once (dirname(__FILE__)) . "/Report.php";

class storageRoleinfo
{
    protected $db;

    public function __construct()
    {
        $config = require(dirname(dirname(__FILE__)) . "/config/db_config.php");
        $this->db = Database::instance($config['data_collection_user']);
        $this->disconnect();
        $this->db = Database::instance($config['data_collection_user']);
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

    public function getLoginInfo($uid,$game_id)
    {
        $whereStr = 'uid = '.$uid .' and  game_id = '.$game_id;
        $res = $this->db->query("select login_time from data_collection.tbl_login_log where ".$whereStr." order by login_time DESC ")->get();
        return isset($res[0])?$res[0]['login_time']:0;
    }

    public function getRoleInfo($keyData)
    {
        $whereStr = '';
        foreach ($keyData as $key => $vaule){
            $whereStr .= $key ."='" .$vaule ."' and ";
        }
        $whereStr .= '1';
        $res = $this->db->query("select * from user_roleinfo where ".$whereStr." ")->get();
        return isset($res[0])?$res[0]:[];
    }

    public function saveRoleinfo($keyData,$updateData)
    {
        $record = $this->getRoleInfo($keyData);
        foreach($updateData as $key=>$value){
            if($value == null){
                $updateData[$key] = '';
            }
        }
        if(empty($record)){
            $res = $this->db->table('user_roleinfo')->insert($updateData);
        }else{
            //不重复更新创角时间
            if($record['role_create_dateline'] > 0 && isset($updateData['role_create_dateline'])) {
                unset($updateData['role_create_dateline']);
            }
            $res = $this->db->table('user_roleinfo')->update($updateData,$keyData);
        }
        return $res;
    }

}