<?php
require_once(dirname(dirname(__FILE__)) . "/db/class.db_test.php");

$config = require(dirname(dirname(__FILE__)) . "/config/config.php");
//if(!$config['test'])exit('no_db_config');
//echo $config['test']['db_name']."<br>";

$db = Database::instance($config['sdklogs']);

$date=$_GET['date'];
if(!$date)exit('no_date');
$startTime=strtotime($date);
$endTime=$startTime+86400;
echo $date.":<br>";
echo "startTime:".date("Y-m-d H:i:s")."<br>";
/*
$res=$db->query("select * from 49app_h5game_create_role where dateline>='{$startTime}' and dateline<'{$endTime}'")->get();

$db->execute("START TRANSACTION");
$i=0;
foreach($res as $v){

    $data=$v;
    $data["ver"]=$v["advter_id"];
    unset($data["id"],$data["advter_id"]);
    $db->table('49app_game_create_role')->insert($data);

    $i++;
    if($i%3000==0){
        $db->execute("COMMIT");
        $db->execute("START TRANSACTION");
    }

}
$db->execute("COMMIT");
*/
$sql="INSERT ignore INTO 49app_game_create_role(gid,uid,roleid,rolename,sid,ver,dateline) 
(SELECT gid,uid,roleid,rolename,sid,advter_id,dateline FROM 49app_h5game_create_role where dateline>='{$startTime}' and dateline<'{$endTime}')";
$db->execute($sql);

$sql="SELECT count(id) as c from 49app_game_create_role where dateline>='{$startTime}' and dateline<'{$endTime}'";
$res=$db->query($sql)->get();
echo "Count:".$res[0]['c']."<br>";

echo "endTime:".date("Y-m-d H:i:s")."<br><br>";

?>