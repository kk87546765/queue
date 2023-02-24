<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/10/11
 * Time: 11:12
 */
require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");

class Activate{

    protected $cass;
    public function __construct()
    {
        $this->cass = CassandraDb::instance();
        $this->cass->ConnectCluster();
    }


    /**
     * 将数据添加到cassandra
     * @param $data
     * @param $table
     * @param $field
     * @param $day
     * @return bool
     */
    public function addData($data, $table,$field, $day)
    {
        $map = [
            ['gid','=',$data['gid']],
            [$field,'=',$data[$field]]
        ];
        $res = $this->cass->Select('*',$table, $map);
        if(count($res)>0){
            return false;
        }
        $matchData = [
            'gid'=>$data['gid'],
            $field=>$data[$field]
        ];

        $time = $day * 86400;
        $this->cass->Insert($table, $matchData, $time);

        return true;
    }



}