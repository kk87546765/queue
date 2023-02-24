<?php
// namespace cassandra;
class CassandraDb {
    private $ip;    //主机
    private $port;     //端口
    private $session;  //连接
    private $defaultKeyspace; //默认键空间
    private $cluster;
    private $BatchMode;  //是否批量操作
    static private $instance;

    private function __construct()
    {
        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $dc_host = $config['scylla_host'];
        $config = $config['cassandra'];
        $this->BatchMode = false;
        $this->ip = $config['db_host'];
        $this->port = $config['db_port'];
        $this->defaultKeyspace = $config['db_name'];
        //$this->defaultKeyspace = 'system';


        $datacenter  = !empty($config['datacenter']) ? $config['datacenter'] : '';

        $this->cluster = Cassandra::cluster()
               ->withContactPoints($this->ip)
               ->withPort($this->port)
               ->withConnectionsPerHost(1,1)
               ->withDefaultTimeout(2)
               ->withConnectTimeout(2)
               ->withRequestTimeout(2)
               ->withWhiteListHosts($this->ip)
               ->withTCPKeepalive(10);
        if ($datacenter) {
            $this->cluster->withDatacenterAwareRoundRobinLoadBalancingPolicy($datacenter, 0, true);
        }
        $this->cluster = $this->cluster->build();
    }

    private function __clone(){}

    static public function instance(){
        try{
            if(!isset(self::$instance)){
                self::$instance = new CassandraDb();
                return self::$instance;
            }else{
                return self::$instance;
            }
        } catch (Exception $e){
            self::$instance = new CassandraDb();
            return self::$instance;
        }
    }


    /**
     * 连接数据库
     * @param string $keyspace 数据库名称
     */
    public function ConnectCluster( $keyspace = '' ) {
        if($keyspace == '')
            $this->session = $this->cluster->connect($this->defaultKeyspace);
        else
            $this->session = $this->cluster->connect( $keyspace );
    }

    /**
     * [CreateKeyspace 创建键空间]
     * @Author   shirs
     * @DateTime 2017-05-12T09:48:47+0800
     * @param    [string]                   $keyspace [键空间名称]
     * @param    string                   $class    [策略名称]
     * @param    integer                  $factor   [副本因子]
     */
    public function CreateKeyspace($keyspace,$class = 'SimpleStrategy',$factor = 1)
    {
        $cql = "CREATE KEYSPACE " . $keyspace . "
                WITH replication = {
                  'class': '" . $class . "',
                  'replication_factor': " . $factor . "
                }";

        return $this->exec($cql,'SIMPLE_STATEMENT');
    }


    /**
     * [DropKeyspace 删除数据库]
     * @Author   shirs
     * @DateTime 2017-05-12T16:49:59+0800
     * @param    [string]                   $keyspace [数据库名称]
     */
    public function DropKeyspace($keyspace)
    {
         $DropStatement = "DROP KEYSPACE ".$keyspace.";";
         return $this->exec( $DropStatement, 'SIMPLE_STATEMENT');
     }

    /**
     * 获取所有keyspace列表
     * @return array
     */
    public function GetList() {
        $Schema = $this->session->schema();
        $KeyspaceDetails = array();
        foreach ($Schema->keyspaces() as $keyspace) {
            $KeyspaceDetails[] = array(
                'KeyspaceName'          => $keyspace->name(),
                'ReplicationClassName'  => $keyspace->replicationClassName(),
                'DurableWrites'  => $keyspace->hasDurableWrites() ? 'true' : 'false',
            );
        }
        return $KeyspaceDetails;
    }

    /**
     * [GetTableList 获取所有keyspace中table列表]
     * @return array
     */
    public function GetTableList()
    {
        $keyspace  = 'click';
        $session   = $this->cluster->connect($keyspace);        // create session, optionally scoped to a keyspace
        $statement = new Cassandra\SimpleStatement(       // also supports prepared and batch statements
            'SELECT keyspace_name, columnfamily_name FROM schema_columnfamilies'
        );
        $future    = $session->executeAsync($statement);  // fully asynchronous and easy parallel execution
        $result    = $future->get();                      // wait for the result, with an optional timeout
        foreach ($result as $row) {                       // results and rows implement Iterator, Countable and ArrayAccess
            printf("The keyspace %s has a table called %s\n", $row['keyspace_name'], $row['columnfamily_name']);
        }
    }

   /**
     * [useKeyspace 切换数据库]
     * @param    [type]                   $keyspace [数据库名称]
     */
    public function UseKeyspace($keyspace)
    {
        $this->session->execute('USE ' . $keyspace);
    }

    /**
     * 创建表
     * @param string $Name 表名
     * @param array $Fields 表字段
     * @param string $primary 主键
     * @return boolean
     */
    public function CreateTable($Name, $Fields, $primary) {
        if(!is_array($Fields))
            return false;
        
        $CreateQuery = "CREATE TABLE ".$Name." ";
        $FieldList = "";
        foreach($Fields as $FieldName=>$FieldType) {
            if($FieldName == $primary ){
                $FieldList .= ($FieldList<>''?', ':'').$FieldName." ".$FieldType." primary key";
            }else{
                $FieldList .= ($FieldList<>''?', ':'').$FieldName." ".$FieldType;
            }
        }

        //$primaryList = ",PRIMARY KEY ({$primary})";
        $CreateQuery = $CreateQuery." (".$FieldList.");";

        return $this->exec( $CreateQuery, 'SIMPLE_STATEMENT' );
    }

    /**
     * 创建数据库
     * @param $CreateQuery
     * @return type
     */
    public function CreateStickTable($CreateQuery)
    {
        return $this->exec( $CreateQuery, 'SIMPLE_STATEMENT' );
    }

    

    /**
     * Create Index 创建索引
     * @param string $IndexName 索引名称
     * @param sting $TableName  表名称
     * @param string $FieldName 字段名称
     * @return type
     */
     public function CreateIndex($IndexName, $TableName, $FieldName) {
         $CreateStatement = "CREATE INDEX ".$IndexName." ON ".$TableName." (".$FieldName.");";
         return $this->exec( $CreateStatement, 'SIMPLE_STATEMENT' );
     }
    
    /**
     * Drop Index 删除索引
     * @param type $IndexName 索引名称
     * @return type
     */
    // public function DropIndex($IndexName) {
    //     $DropStatement = "DROP INDEX ".$IndexName.";";   
    //     return $this->exec( $DropStatement, 'SIMPLE_STATEMENT' );
    // }
    
    /**
     * Drop Table 删除表
     * @param string $TableName 表名
     * @return type
     */
     public function DropTable($TableName) {
         $DropStatement = "DROP TABLE ".$TableName.";";
         return $this->exec( $DropStatement, 'SIMPLE_STATEMENT' );
     }

    /**
     * [TruncateTable 清空表]
     * @param string $TableName 表名
     * @Author   shirs
     * @DateTime 2017-05-03T11:04:48+0800
     */
     public function TruncateTable($TableName)
     {
         $QueryString = "truncate " . $TableName;
         return $this->exec($QueryString, 'SIMPLE_STATEMENT');
     }

    /**
     * [GetTotal 获取表总记录数]
     * @Author   shirs
     * @DateTime 2017-05-03T11:10:10+0800
     * @param    [string]                   $TableName [表名]
     * @return int 记录数
     */
    public function GetTotal($TableName)
    {
        $QueryString = "select count(1) from " . $TableName;
        $res = $this->exec($QueryString, 'SIMPLE_STATEMENT');
        return $res[0]['count'];
    }

    /**
     * Insert into table 添加数据
     * @param string $Table 表名
     * @param type $FieldsWithArgs 数组（要添加的数据）
     * @param int $CacheTime 缓存时间
     * @return type
     */
    public function Insert($Table, $FieldsWithArgs, $CacheTime = 0) {
        if(!is_array($FieldsWithArgs))
            return false;
        
        $Fields = $Args = "";
        foreach($FieldsWithArgs as $Field=>$Arg) {
            $Fields .= ($Fields<>''?', ':'') . $Field;
            
            if(is_array($Arg)) {
                $ArgList = '';
                foreach($Arg as $ListVal) {
                    $ArgList .= ($ArgList<>''?',':'')."'".$ListVal."'";
                }

                $Args .= ($Args<>''?', ':'') . '['.$ArgList.']';
            }
            else 
                $Args .= ($Args<>''?', ':'') . (is_int($Arg) ? $Arg : "'" . $Arg . "'");   
            
        }

        $cache = '';
        if($CacheTime > 0)
        {
            $cache = " USING TTL {$CacheTime} ";
        }

        
        $QueryString = 'INSERT INTO ' . $Table . ' ('.$Fields.') VALUES (' . $Args .') '.$cache;

        
        return $this->BatchMode==true ? $QueryString : $this->exec( $QueryString );
    }
    
    
    /**
     * Update table 修改数据
     * @param string $Table 表名
     * @param type $UpdateFieldsWithArgs 更新数据数组
     * @param type $FilterFieldsWithArgs 条件数组
     * * @param type $type 值为update是正常修改，值为count为计数增加
     * @return boolean
     */
    public function Update($Table, $UpdateFieldsWithArgs, $FilterFieldsWithArgs, $type = 'update') {
        if(!is_array($UpdateFieldsWithArgs))
            return false;
        
        $Fields = $Args = $UpdateFields = "";
        foreach($UpdateFieldsWithArgs as $Field=>$Arg) {            
            if(is_array($Arg)) {
                $ArgList = '';
                foreach($Arg as $ListVal) {
                    $ArgList .= ($ArgList<>''?',':'')."'".$ListVal."'";
                }
                $Args = '[' . $ArgList . ']';
            }
            else{
                if($type == 'count'){
                    $Args = $Arg;
                }else{
                    $Args = is_int($Arg) ? $Arg : "'" . $Arg . "'";
                }

            }

            
            if(strpos($Field, ':') !== false) {
                list($Field, $type) = explode(":", $Field);
                
                if($type == 'BEFORE') {
                    $UpdateFields .= ($UpdateFields<>''?', ':'') . $Field.'='.$Args.'+'.$Field;
                }
                else if($type == 'AFTER') {
                    $UpdateFields .= ($UpdateFields<>''?', ':'') . $Field.'='.$Field.'+'.$Args;
                }
                else {
                    $UpdateFields .= ($UpdateFields<>''?', ':'') . $Field.'='.$Args;    
                }
            }
            else
                $UpdateFields .= ($UpdateFields<>''?', ':'') . $Field.'='.$Args;
        }

        $FilterFields = "";
        foreach($FilterFieldsWithArgs as $Field=>$Arg) {
            $FilterFields .= ($FilterFields<>''?' AND ':'') . $Field.'='.(is_int($Arg) ? $Arg : "'" . $Arg . "'");
        }

        $QueryString = 'UPDATE ' . $Table . ' SET '.$UpdateFields.' WHERE '.$FilterFields;


        return $this->BatchMode==true ? $QueryString : $this->exec( $QueryString );
    }
    
    
    /**
     * 查询数据 
     * @param string $fields 要查的字段
     * @param string $table 表名
     * @param array TableFilters 条件数组（二维）$map = [['id','=',1]];
     * @param int $limit 条数
     * @return array
     */
    public function Select($fields, $table, $TableFilters='', $limit = false) {
        
        $QueryString = "SELECT ".$fields." FROM ".$table ;
        
        #Add Where value
        if(is_array($TableFilters) && sizeof($TableFilters) > 0) {
            if($FilterOptions = self::GetFilterQuery($TableFilters)) {
                $QueryString .= " WHERE " . $FilterOptions;
            }
        }
        if($limit){
            $QueryString .=" limit {$limit}";
        }

        return $this->ExecSelectQuery($QueryString, false);
    }

     /**
     * 执行cql
     * @param type $QueryString 
     * @param type $limit
     * @return array
     */
    public function ExecSelectQuery($QueryString, $limit=false) {
        if($limit !== false) {
            $execOptions = new Cassandra\ExecutionOptions(array('page_size' => $limit));
            $execResult = $this->exec($QueryString, 'SIMPLE_STATEMENT', $execOptions);
        }
        else 
            $execResult = $this->exec($QueryString, 'SIMPLE_STATEMENT');

        # Return false if invalid object
        if(!is_object($execResult))
            return false;
        
        $finalResult = array();

        # Loop the result
        $i = 0;
        while ($execResult) {
            foreach($execResult as $row){
                if(is_array($row)) {
                    foreach($row as $key=>$val) {
                        if (is_object($val)) {
                            // $finalResult[$i][$key] = array(
                            //     'type' => $val->type()->valueType()->name(),
                            //     'value' => $val->values()
                            // );
                            $name = $val->type()->name();
                            if (in_array($name,array('bigint','decimal','float','varint'))) {
                                $finalResult[$i][$key] = $val->value();
                            }
                            if (in_array($name,array('timestamp'))) {
                                // echo "<pre/>";
                                // print_r($val);
                                // echo $val->seconds;
                                // $finalResult[$i][$key] = $val->seconds();
                                $val = (array)$val;
                                $finalResult[$i][$key] = $val['seconds'];
                            }
                            if (in_array($name,array('inet'))) {
                                $finalResult[$i][$key] = $val->address();
                            }
                            if (in_array($name,array('uuid','timeuuid'))) {
                                $finalResult[$i][$key] = $val->uuid();
                            }
                            if (in_array($name,array('blob'))) {
                                $finalResult[$i][$key] = $val->bytes();
                            }
                        } else {
                            $finalResult[$i][$key] = $val;
                        }
                    }
                    $i++;
                }
            }

            $execResult = $execResult->nextPage();
        }
        
        //Return final result
        return $finalResult;
    }

    /**
     * Delete from table 删除数据
     * @param string $Table 
     * @param array $FilterFieldsWithArgs 条件数组
     * @param array $Fields 要删除的列
     * @return type
     */
    public function Delete($Table, $FilterFieldsWithArgs, $Fields=false) {
        
        $FilterFields = "";
        foreach($FilterFieldsWithArgs as $Field=>$Arg) {
            if(is_array($Arg)){
                $FilterFields .= ($FilterFields<>''?' AND ':'') . $Field.$Arg[0].(is_int($Arg[1]) ? $Arg[1] : "'" . $Arg[1] . "'");
            }else{
                $FilterFields .= ($FilterFields<>''?' AND ':'') . $Field.'='.(is_int($Arg) ? $Arg : "'" . $Arg . "'");
            }
        }
        
        $TargetFields = '';
        if(is_array($Fields)) {
            $TargetFields = implode(",", $Fields);
        }
        
        $QueryString = 'DELETE ' . $TargetFields . ' FROM ' . $Table . ' WHERE '.$FilterFields;

        return $this->BatchMode==true ? $QueryString : $this->exec( $QueryString );
    }
    
    /**
     * Execute batch statement
     * @param array $Statements
     * @param type $Type
     * @return boolean
     */
    public function Batch(Array $Statements, $Type='BATCH_LOGGED') {
        if(!is_array($Statements))
            return false;
        
        // Instantiate batch
        if($Type == 'BATCH_UNLOGGED')
            $Batch = new Cassandra\BatchStatement(Cassandra::BATCH_UNLOGGED);
        else if($Type == 'BATCH_COUNTER')
            $Batch = new Cassandra\BatchStatement(Cassandra::BATCH_COUNTER);
        else
            $Batch = new Cassandra\BatchStatement(Cassandra::BATCH_LOGGED); 
        
        $this->BatchMode = true;
        foreach($Statements as $BatchRow) {
            $QueryString = '';
            // Get Insert statement
            if(isset($BatchRow['INSERT']))
                $QueryString = $this->Insert($BatchRow['INSERT'][0], $BatchRow['INSERT'][1]);

            // Get update statement
            if(isset($BatchRow['UPDATE']))
                $QueryString = $this->Update($BatchRow['UPDATE'][0], $BatchRow['UPDATE'][1], $BatchRow['UPDATE'][2]);

            // Get delete statement
            if(isset($BatchRow['DELETE']))
                $QueryString = $this->Delete($BatchRow['DELETE'][0], $BatchRow['DELETE'][1], $BatchRow['DELETE'][2]);
            
            //Load query on batch Query
            if($QueryString !== false) {
                $BatchStatement = new Cassandra\SimpleStatement( $QueryString );
                $Batch->add( $BatchStatement );
            }
        }
        $this->BatchMode = false;
        return $this->exec( $Batch, 'READY' );
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

    /**
     * Execute Query
     * @param type $Statement
     * @return type
     */
    public function exec( $Statement, $DataType = 'RAW', $OptionalParam = false ) {

       /* if($DataType == 'RAW')
            $ExeStatement = $this->session->prepare( $Statement );
        else if($DataType == 'SIMPLE_STATEMENT')
            $ExeStatement = new Cassandra\SimpleStatement( $Statement );
        else
            $ExeStatement = $Statement;*/

        $ExeStatement = new Cassandra\SimpleStatement( $Statement );

        if (!method_exists($this->session,'execute')) {
            return false;
        }

        if(!$OptionalParam)
            $result = $this->session->execute( $ExeStatement );
        else
            $result = $this->session->execute( $ExeStatement , $OptionalParam );

          return $result;
    }

    



}
