<?php
/**
 * Class Database
 *
 * @author me@habibhadi.com
 * @version 2.0.0
 */

class Database
{
    /** @var PDO $pdo */
    protected $pdo;
    protected $result = false;
    protected $total;
    protected $dbErrorMsg = 'Mysql: ';
    protected $tableName = NULL;
    protected $dbname = '';
    protected $fetchMethod = PDO::FETCH_OBJ;
    protected $queryDebug = [];
    static private $instance;

    /**
     * Start connection with database
     *
     * @param array $config host, name, username, password
     * @return bool|PDO
     */
    private function __construct($config = [])
    {
        if (count($config) == 0) {
            $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
            $config = $config['sdklogs'];
        }


        try {
            $dsn = "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'];
            $opt = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"
            );

            $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pwd'], $opt);

            return $this->pdo;
        } catch (PDOException $ex) {
            echo $this->dbErrorMsg . $ex->getMessage();
            exit();
        }
    }

    private function __clone(){}

    static public function instance($config = []){
        if(!isset(self::$instance)){
            self::$instance = new Database($config);
            return self::$instance;
        }else{
            return self::$instance;
        }
    }


    /**
     * Disconnect from database
     *
     * @return bool
     */
    public function disconnect()
    {
        $this->pdo = NULL;
        self::$instance = NULL;

        return true;
    }


    /**
     * Get all result
     *
     * @return mixed
     */
    public function get()
    {
        return $this->result;
    }


    /**
     * Get first data
     *
     * @return null
     */
    public function first()
    {
        if ($this->result) {
            return count($this->result) > 0 ? $this->result[0] : NULL;
        }

        return NULL;
    }


    public function debug()
    {
        return $this->queryDebug;
    }

    public function total()
    {
        return $this->total;
    }


    /**
     * If string starts with
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected function startsWith($haystack, $needle)
    {
        $haystack = strtoupper($haystack);
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
    }


    /**
     * General query
     *
     * @param $queryString
     * @param int $method
     * @return $this
     */
    public function query($queryString, $method = NULL)
    {
        if (!$method) {
            $method = $this->fetchMethod;
        }

        try {
            $qry = $this->pdo->prepare($queryString);
            $qry->execute();
            $qry->setFetchMode($method);

            // stroring data arrary into $result
            if ($this->startsWith($queryString, "SELECT")) {
                $this->result = $qry->fetchAll(PDO::FETCH_ASSOC);
            }



            // total row count
            $this->total = $qry->rowCount();


            $this->queryDebug = ['string' => $queryString, 'value' => NULL, 'method' => $method];

        } catch (PDOException $ex) {
            echo $this->dbErrorMsg . $ex->getMessage();
            exit();
        }

        return $this;
    }


    /**
     * Select table
     *
     * @param $tableName
     * @return $this
     */
    public function table($tableName, $dbname = '')
    {
        $this->tableName = $tableName;
        $this->dbname = $dbname ? $dbname.'.' : '';
        return $this;
    }


    /**
     * Select query
     *
     * @param array $qryArray
     * @return $this
     */
    public function select($qryArray = [])
    {
        $fetchFields = (isset($qryArray['field']) && count($qryArray['field'])>0) ? implode(', ',$qryArray['field']): '*';

        $qryStr = 'SELECT '.$fetchFields.' FROM '.$this->dbname.'`'.$this->tableName.'` '.((isset($qryArray['condition']) && $qryArray['condition']!=NULL)?$qryArray['condition']:'');

        if(isset($qryArray['groupby']) && $qryArray['groupby'] != NULL) {
            $qryStr .= ' GROUP BY '.$qryArray['groupby'];
        }

        if(isset($qryArray['orderby']) && $qryArray['orderby'] != NULL) {
            $qryStr .= ' ORDER BY '.$qryArray['orderby'];
        }

        if(isset($qryArray['limit']) && $qryArray['limit'] != NULL) {
            $qryStr .= ' LIMIT '.$qryArray['limit'];
        }

        try {
            $qry = $this->pdo->prepare($qryStr);
            $qry->execute();

            if(isset($qryArray['method']) && $qryArray['method']!=NULL) {
                $qry->setFetchMode($qryArray['method']);
            }
            else {
                $qry->setFetchMode($this->fetchMethod);
            }

            $this->result = $qry->fetchAll(PDO::FETCH_ASSOC);

            $this->total = $qry->rowCount();

            $this->queryDebug = ['string' => $qryStr, 'value' => NULL, 'method' => (isset($qryArray['method']) ? $qryArray['method'] : $this->fetchMethod)];
        }
        catch (PDOException $ex){
            echo $this->dbErrorMsg . $ex->getMessage(). ' query: '.$qryStr;
            exit();
        }

        return $this;
    }


    /**
     * Insert operation
     *
     * @param array $dataArray
     * @param array $unique
     * @return array
     */
    public function insert($dataArray = [], $unique = [])
    {
        $fields = [];
        $executeArray = [];
        $duplicate = false;

        // populating field array
        foreach($dataArray as $key=>$val){
            $fields[] = ':'.$key;
            $executeArray[':'.$key] = $val;

        }

        // generating field string
        $fields_str = implode(',',$fields);
        $rawFieldsStr = implode(',', str_replace(':','',$fields));

        // checking wheather same value exists or not
        if( count($unique) > 0 ){
            $condition = array();
            foreach($unique as $fieldName){
                $condition[] = $fieldName." = '".$dataArray[$fieldName]."' ";
            }

            $cQryStr = "SELECT ".$unique[0]." FROM ".$this->tableName." WHERE ".implode('AND ',$condition);
            $cQry = $this->pdo->query($cQryStr);

            // checking duplicate
            if( $cQry->rowCount() > 0 ) $duplicate = true;
            else $duplicate = false;
        }

        $affectedRow = 0;
        $lastInsertedId = 0;

        // processing insertsion while there is no duplicated value
        if(!$duplicate) {
            $qryStr = 'INSERT ignore INTO '.$this->tableName.' ('.$rawFieldsStr.') VALUES('.$fields_str.')';

            try {
                // query
                $qry = $this->pdo->prepare($qryStr);
                $qry->execute($executeArray);

                // affected row
                $affectedRow = $qry->rowCount();

                // last inseretd id
                $lastInsertedId = $this->pdo->lastInsertId();

                $this->queryDebug = ['string' => $qryStr, 'value' => $executeArray, 'method' => false];
            }
            catch (PDOException $ex){
                echo $this->dbErrorMsg . $ex->getMessage();


            }
        }

        // returning insert log
        return [
            'affected_row' => $affectedRow,
            'inserted_id' => $lastInsertedId,
            'is_duplicate' => (bool) $duplicate
        ];
    }

    public function update($dataArray = [], $where, $unique = [])
    {
        $tableName = $this->tableName;

        $fields = [];
        $executeArray = [];

        // populating field array
        foreach($dataArray as $key=>$val){
            $fields[] = $key.' = :'.$key;
            $executeArray[':'.$key] = $val;

        }

        // generating field string
        $fields_str = implode(', ',$fields);

        // checking wheather same value exists or not
        if( count($unique) > 0 ){
            $condition = [];

            foreach($unique as $fieldName){
                $condition[] = $fieldName." = '".$dataArray[$fieldName]."' ";
            }

            $extendedCondition = [];

            if( is_array($where) && count($where) > 0 ){
                foreach($where as $whereKey=>$whereVal){
                    $extendedCondition[] = $whereKey." != '".$whereVal."' ";
                }
            }

            $cQryStr = "SELECT ".$unique[0]." FROM ".$tableName." WHERE ".implode('AND ',$condition);
            if( count($extendedCondition) > 0 ) $cQryStr .= "AND ".implode('AND ', $extendedCondition);

            $cQry = $this->pdo->query($cQryStr);

            // checking duplicate
            if( $cQry->rowCount() > 0 ) $duplicate = true;
            else $duplicate = false;
        }
        else {
            $duplicate = false;
        }

        $affectedRow = 0;

        // processing query while there is no duplicated value
        if(!$duplicate && ($where!=NULL || (is_array($where) && count($where)>0)) ) {

            if(is_array($where)) {
                $affectedTo = [];

                foreach($where as $key=>$val){
                    $affectedTo[] = $key." = '".$val."'";
                }

                $whereCond = ' WHERE '.implode(" AND ", $affectedTo);
            }
            else {
                $whereCond = ' WHERE '.$where;
            }

            $qryStr = 'UPDATE '.$tableName.' SET '.$fields_str.$whereCond;

            try {
                // query
                $qry = $this->pdo->prepare($qryStr);
                $qry->execute($executeArray);

                // affected row
                $affectedRow = $qry->rowCount();

                $this->queryDebug = ['string' => $qryStr, 'value' => $executeArray, 'method' => false];

            }
            catch (PDOException $ex){
                echo $this->dbErrorMsg . $ex->getMessage();
                exit();
            }
        }
        else {
            $this->queryDebug = ['string' => $cQryStr, 'value' => NULL, 'method' => $this->fetchMethod];
        }

        // returning insert log
        return [
            'affected_row' => $affectedRow,
            'is_duplicate' => (bool) $duplicate
        ];
    }


    /**
     * Delete operation
     *
     * @param $where
     * @return array
     */
    public function delete($where)
    {
        $tableName = $this->tableName;

        $affectedRow = 0;
        if($where!=NULL || (is_array($where) && count($where)) > 0 ){
            if(is_array($where)) {
                $affectedTo = array();
                foreach($where as $key=>$val){
                    $affectedTo[] = $key." = '".$val."'";
                }
                $whereCond = 'WHERE '.implode(" AND ", $affectedTo);
            }
            else {
                $whereCond = 'WHERE '.$where;
            }

            $qryStr = 'DELETE FROM '.$tableName.' '.$whereCond;

            try {
                // query
                $qry = $this->pdo->prepare($qryStr);
                $qry->execute();

                // affected row
                $affectedRow = $qry->rowCount();

                $this->queryDebug = ['string' => $qryStr, 'value' => NULL, 'method' => false];

            }
            catch (PDOException $ex){
                echo $this->dbErrorMsg . $ex->getMessage();
                exit();
            }
        }

        return [
            'affected_row' => $affectedRow
        ];
    }

    /**
     * ??????????????????
     * @param $qryStr
     * @return array
     */
    public function execute($qryStr)
    {
        try {
            // query
            $qry = $this->pdo->prepare($qryStr);
            $qry->execute();

            // affected row
            $affectedRow = $qry->rowCount();

            $this->queryDebug = ['string' => $qryStr, 'value' => NULL, 'method' => false];

        }
        catch (PDOException $ex){
            echo $this->dbErrorMsg . $ex->getMessage();
            exit();
        }

        return [
            'affected_row' => $affectedRow
        ];

    }

    /**
     * ????????????
     * @return bool
     */
    public function transaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * ????????????
     * @return bool
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * ????????????
     * @return bool
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }


}
