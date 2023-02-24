<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
require_once(dirname(dirname(__FILE__)) . "/db/CassandraDb.php");


class test
{

    private $cass;

    public function __construct()
    {
        $this->cass = CassandraDb::instance();
        $this->cass->ConnectCluster();
    }

    public function index($keyspace)
    {
        $res = $this->cass->CreateKeyspace($keyspace);
        print_r($res);
    }

    public function DropKeyspace($keyspace)
    {
        $this->cass->DropKeyspace($keyspace);
    }


    public function table()
    {
        $res = $this->cass->GetTableList();
        print_r($res);
    }

    public function UseKeyspace($keyspace)
    {
        $this->cass->execute('USE ' . $keyspace);
    }

    public function cretatable()
    {
        $fields = [
            'id' => 'int',
            'name' => 'varchar'
        ];
        $this->cass->CreateTable('member', $fields, 'id');
    }

    public function GetTotal($TableName)
    {
        $num = $this->cass->GetTotal($TableName);
        echo $num;
    }

    public function Insert($Table, $FieldsWithArgs)
    {
        $res = $this->cass->Insert($Table, $FieldsWithArgs);
        return $res;
    }

    public function Select($fields, $table, $TableFilters='', $limit = false)
    {
        $res = $this->cass->Select($fields, $table, $TableFilters, $limit);

        var_dump($res);
    }

    public function delete($map)
    {
        $this->cass->Delete('member', $map);
    }

    public function update($data, $map)
    {
        $this->cass->Update('member', $data, $map);
    }

    public function exec($sql)
    {
        $this->cass->exec($sql);
    }

    public function deltable($table)
    {
        $this->cass->DropTable($table);
    }

    public function createIndex($IndexName, $TableName, $FieldName)
    {
        $this->cass->CreateIndex($IndexName, $TableName, $FieldName);
    }



}
$job = new test();
$key = uniqid();
$data = [
    'key' => $key, 'log_key' => 'test',

];
$res = $job->Insert('check_connect', $data);
var_dump($res);








