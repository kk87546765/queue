<?php

class MongoDb
{
    public static $_instance = null;
    public $conf = null;
    public $handle = null;
    public $_and = [];
    public $_or = [];
    public $collection = "";
    public $limit = 0;
    public $skip = 0;
    public $_field = [];
    public $_sort = [];

    public static function getInstance($conf = null){
        if(self::$_instance === null){
            self::$_instance = new self($conf);
        }
        return self::$_instance;
    }

    private function __construct($config=null)
    {
        if (!$config){
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $config = $config['mongodb'];
        }
        $this->conf = $config;
        if(!isset($this->conf["type"]) || empty($this->conf["type"])){
            $this->conf["type"] = "mongodb";
        }
        $this->connect();
    }

    public function connect(){
        /*$uri = 'mongodb://' . $this->conf["db_user"] . ':' . $this->conf["db_pwd"] . '@' .
            $this->conf['db_host1'] . ',' . $this->conf['db_host2'] . '/?replicaSet=' . $this->conf['db_replname'];*/
        $uri = 'mongodb://' . $this->conf["db_user"] . ':' . $this->conf["db_pwd"] . '@' .
            $this->conf['db_host1'] . '/?replicaSet=' . $this->conf['db_replname'];
        try {
            $this->handle = new MongoDB\Driver\Manager($uri);
        }catch (Exception $exception){
            $this->echoError($exception);
        }
    }

    public function collection($collection){
        $this->collection = $this->conf["db_name"] . "." . $collection;
        return $this;
    }

    public function where(array $where){
        if(empty($where)){
            $this->echoError(new Exception("param where is empty"));
        }
        $this->_and = array_merge($this->_and,$where);
        return $this;
    }

    public function orWhere(array $where){
        if(empty($where)){
            $this->echoError(new Exception("param where is empty"));
        }
        $this->_or = array_merge($this->_or,$where);
        return $this;
    }

    public function limit($limit){
        $this->limit = $limit;
        return $this;
    }

    public function skip($skip){
        $this->skip = $skip;
        return $this;
    }

    public function field( $field, $_id=true){
        if(!empty($field)){
            $fieldArr = explode(",",$field);
            if(is_array($fieldArr)){
                foreach($fieldArr as $val){
                    $this->_field[$val] = 1;
                }
            }
        }
        if(!$_id){
            $this->_field["_id"] = 0;
        }
        return $this;
    }

    public function sort( $field, $sort){
        $this->_sort["field"] = $field;
        $this->_sort["rule"] = $sort;
        return $this;
    }

    public function find(){
        return $this->query();
    }

    public function findOne(){
        $this->limit = 1;
        $this->skip = 0;
        return $this->query();
    }

    public function count(){
        $filter = $this->getWhere();
        $aggregate = [
            "aggregate"=>str_replace($this->conf["db"].".","",$this->collection),
            "pipeline" => [
                ['$match'=>$filter],
                ['$count'=>"count"],
            ],
            "cursor" => (object)array()
        ];
        $command = new \MongoDB\Driver\Command($aggregate);
        $result = $this->command($this->conf["db"],$command);
        return $result ? $result->toArray()[0]->count : false;
    }

    public function sum( $field){
        $filter = $this->getWhere();
        $aggregate = [
            "aggregate"=>str_replace($this->conf["db"].".","",$this->collection),
            "pipeline" => [
                ['$match' => $filter],
                [
                    '$group' => [
                        '_id' => '',
                        'total' => ['$sum' => '$' . $field],
                    ]
                ],
            ],
            "cursor" => (object)array()
        ];
        $command = new \MongoDB\Driver\Command($aggregate);
        $result = $this->command($this->conf["db"],$command);
        return $result ? $result->toArray()[0]->total : false;
    }

    public function getWhere(){
        $filter = [];
        if(!empty($this->_and)){
            $filter = array_merge($filter,$this->_and);
        }
        if(!empty($this->_or)){
            foreach($this->_or as $key =>$val) {
                $filter['$or'][][$key] = $val;
            }
        }
        return $filter;
    }

    public function getQuery(){
        $filter = $this->getWhere();
        $queryOptions = [];
        if(!empty($this->_field)){
            $queryOptions["projection"] = $this->_field;
        }
        if(!empty($this->_sort)){
            $queryOptions["sort"] = [$this->_sort["field"]=>$this->_sort["rule"]];
        }
        if($this->limit > 0){
            $queryOptions["limit"] = $this->limit;
        }
        if($this->skip > 0){
            $queryOptions["skip"] = $this->skip;
        }
        $query = new MongoDB\Driver\Query($filter,$queryOptions);
        return $query;
    }

    public function query(){
        $query = $this->getQuery();
        try {
            $result = $this->handle->executeQuery($this->collection,$query);
        }catch (\Exception $exception){
            $this->echoError($exception);
            $result = false;
        }
        $this->init();
        return $result?:false;
    }

    /**
     * @param array $data
     * @param bool $batch ????????????
     * @return mixed
     */
    public function insert(array $data, $batch=false){
        $write = new MongoDB\Driver\BulkWrite();
        if($batch){
            foreach($data as $val){
                $write->insert($val);
            }
        }else{
            $write->insert($data);
        }
        $result = $this->execute($this->collection,$write,$data);
        return $result ? $result->getInsertedCount() : false;
    }

    /**
     * @param array $update
     * @param bool $multi true ?????????????????? false ????????????
     * @param bool $upsert true ?????????????????????
     * @return bool
     * @throws Exception
     */
    public function update(array $update, $multi = false,  $upsert = true){
        if(empty($this->_and)){
            $this->echoError(new Exception("update where is empty"));
        }
        $write = new MongoDB\Driver\BulkWrite();
        $write->update(
            $this->_and,
            ['$set'=>$update],
            ['multi' => $multi, 'upsert' => $upsert]
        );
        $result = $this->execute($this->collection,$write);
        return $result ? $result->getUpsertedCount() + $result->getMatchedCount() : false;
    }

    public function delete( $all=false){
        if(empty($this->_and)){
            $this->echoError(new Exception("delete where is empty"));
        }
        $write = new MongoDB\Driver\BulkWrite();
        $write->delete($this->_and,['limit'=>$all]);
        $result = $this->execute($this->collection,$write);
        return $result ? $result->getDeletedCount() : false;
    }

    /**
     * @param array $pipeline
     * $pipeline ???????????????
     * [
     *    [
     *        '$match' => [
     *            'time' => ['$lt'=>1598864580]
     *         ],
     *    ],
     *    [
     *         '$group' => [
     *             "_id"=>'$time', "total" => ['$sum' => 1]
     *         ],
     *     ],
     *     [
     *         '$limit' => 3
     *     ],
     *     [
     *         '$sort'  => ['total' => -1]
     *     ]
     * ]
     * @return bool
     */
    public function aggregate(array $pipeline){
        $aggregate = [
            "aggregate"=>str_replace($this->conf["db"].".","",$this->collection),
            "pipeline" => $pipeline,
            "cursor" => (object)array()
        ];
        $command = new \MongoDB\Driver\Command($aggregate);
        $result = $this->command($this->conf["db"],$command);
        return $result ? $result->toArray() : false;
    }

    public function execute($namespace,$object,$data = []){
        try {
            $result = $this->handle->executeBulkWrite($namespace,$object);
        }catch (\Exception $exception){
            $this->echoError($exception,$data);
        }
        $this->init();
        return $result;
    }

    public function command($db,$command){
        try {
            $result = $this->handle->executeCommand($db,$command);
        }catch (\Exception $exception){
            $this->echoError($exception);
        }
        $this->init();
        return $result;
    }

    public function echoError(\Exception $exception,$data = []){
        echo  $exception->getMessage();
    }

    public function init(){
        $this->_and = [];
        $this->_or = [];
        $this->collection = "";
        $this->limit = 0;
        $this->skip = 0;
        $this->_field = [];
        $this->_sort = [];
    }

}

//$db = MongoDB::getInstance([
//    "type" => "mongodb",
//    "host" => "127.0.0.1",
//    "port" => "27017",
//    "db" => "db",
//    "user" => "",
//    "password" => ""
//]);
//??????
//$result = $db->collection("message")->Where(["time"=>['$lte'=>1598864449]])->sort("time",-1)->find();
//$result = $db->collection("message")->Where(["time"=>['$lte'=>1598864449]])->count();
//$result = $db->collection("message")->Where(["time"=>['$lte'=>1598864449]])->sum("time");
//??????
//$result = $db->collection("message")->insert([
//    "from" => "a",
//    "type" => "write",
//    "content" => "??????",
//    "time" => time(),
//]);
//??????
//$result = $db->collection("message")->where(["from"=>"a"])->update(["type"=>"ssd"]);
//??????
//$result = $db->collection("message")->where(["from"=>"a"])->delete();
//aggregate ??????
//$result = $db->collection("message")->aggregate([
//    ['$match'=>['time'=>['$gte'=>1598955498]]],
//    ['$group' => ["_id"=>'$time', "total" => ['$sum' => 1]]]
//]);
//var_dump($result);