<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
ini_set('memory_limit','1024M');
error_reporting(-1);                    //打印出所有的 错误信息
set_time_limit(0);

require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");

$config = require(dirname(dirname(__FILE__)) . "/config/config.php");

$db = Database::instance();

$start_date=$_GET['start_date'];
$end_date=$_GET['end_date'];
$type=$_GET['type'];

$start_time=strtotime($start_date);
$end_time=strtotime($end_date);
if($start_time>$end_time || ($end_time-$start_time)>86400){
    exit('date_err:'.$start_date."-".$end_date);
}
if($type!='android' && $type!='ios'){
    exit('type_err:'.$type);
}

$where="";
$table="sdklogs_new.49app_android_member_product_login";
if($type=='ios') {
    $table="sdklogs_new.49app_ios_member_product_login";
    $where=" and ver not like 'h5_chn_%' ";
}


$sql="select * from ".$table." where login_time>=".$start_time." and login_time<".$end_time." ".$where;
$list=$db->query($sql)->get();

$db->transaction();
$i=0;
$success=0;
foreach($list as $data){

    $data['start_time']=$data['dateline'];
    unset($data['id'],$data['dateline']);

    $fieldArr=array_keys($data);
    $fieldStr=implode(",",$fieldArr);

    $valueArr=array_values($data);
    $valueStr="'".implode("','",$valueArr)."'";

    //更新15日内的最后登录时间
    $sql="UPDATE ".$table."_plus SET login_time=".$data['login_time']." 
          WHERE uid='".$data['uid']."' AND prodt_id='".$data['prodt_id']."' ";
    $res=$db->execute($sql);
    //echo $sql."<br>";var_dump($res);
    if($res && $res['affected_row']==0) {//更新失败，添加记录

        $sql = "INSERT INTO ".$table."_plus ($fieldStr) 
                  SELECT $valueStr FROM DUAL WHERE 
                  NOT EXISTS (
                      SELECT id FROM ".$table."_plus 
                      WHERE uid='" . $data['uid'] . "' AND prodt_id='" . $data['prodt_id'] . "' LIMIT 1 )";
        $res = $db->execute($sql);

    }
    if($res && $res['affected_row']==1){
        $success++;
    }

    $i++;
    if ($i % 200 == 0) {
        $db->commit();
        $db->transaction();
    }
}
$db->commit();
//$db->rollBack();

echo "success:".$success;



?>