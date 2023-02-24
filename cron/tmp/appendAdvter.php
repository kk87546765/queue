<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/26
 * Time: 17:05
 */
require_once(dirname(dirname(dirname(__FILE__))) . "/db/class.db.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/db/class.dbFusion.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/db/RedisModel.class.php");

class appendAdvter
{
    protected $db;
    protected $dbOld;
    protected $redis;


    public function __construct()
    {
        $this->redis = RedisModel::instance();
        $this->db = Database::instance();
        $this->dbOld = DatabaseFusion::instance();

    }

    public function setAdvter()
    {

        $cursor = $this->redis->get('set_member_advter_regwhere');
        if(!$cursor)
        {
            $cursor = 0;
        }
        $status = true;

        while ($status == true)
        {
            $sql = "select id,advter_id,uid from 49app_new_register_ios_log where id>{$cursor} and advter_id>0 order by id asc limit 10000";

            $mem_res = $this->db->query($sql)->get();

            $num = count($mem_res);
            $key = $num - 1;
            if($key == -1)
            {
                $last_id = $cursor;
                $status = false;
            }else{
                $last_id = $mem_res[$key]['id'];
                $cursor = $last_id;
            }



            $this->dbOld->transaction();
            foreach ($mem_res as $k => $v)
            {
                $this->dbOld->table('mei.49app_common_member')->update(['reg_where'=>(int)$v['advter_id']], ['uid'=>$v['uid']]);
            }
            $this->dbOld->commit();

            echo "set last_id = {$last_id}, update to {$num}\n";


            $this->redis->set('set_member_advter_regwhere',$last_id);


        }





    }



}


$mod = new appendAdvter();
$mod->setAdvter();

