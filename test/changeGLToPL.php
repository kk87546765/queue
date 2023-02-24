<?php
//游戏首登表 转数据到 新的产品首登表
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
ini_set('memory_limit','1024M');
error_reporting(-1);                    //打印出所有的 错误信息
set_time_limit(0);


require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");
require_once(dirname(dirname(__FILE__)) . '/db/CassandraDb.php');

$action=isset($_GET["action"])?$_GET["action"]:"";
$sdate=isset($_GET["sdate"])?$_GET["sdate"]:date("Y-m-d");
$stime=strtotime($sdate);
$edate=date("Y-m-d",strtotime($sdate." +1 day"));
$etime=strtotime($edate);
$finaldate=isset($_GET["finaldate"])?$_GET["finaldate"]:'';

echo $sdate." - ".$edate."<br>";
switch($action){
    case "android":
        change('android',$stime,$etime);
        break;
    case "ios":
        change('ios',$stime,$etime);
        break;
    default:
        echo "No Action!";
        break;
}



function change($type,$stime,$etime){

    $db=Database::instance();
    $db->transaction();

    $cass = CassandraDb::instance();
    $cass->ConnectCluster();

    $insertTable='';
    if($type=='android'){
        $sql="select l.*,g.prodt_id 
            from sdklogs_new.49app_android_member_game_login as l  
            left join yunying.tbl_game as g on l.gid=g.game_id 
            where l.dateline>=".$stime." and l.dateline<".$etime." 
          group by l.uid,g.prodt_id order by  dateline";

        $insertTable='sdklogs_new.49app_android_member_product_login';
    }else{
        $sql="select l.*,g.prodt_id 
                from sdklogs_new.49app_ios_member_game_login as l  
                left join fx_advios.tbl_game as g on l.gid=g.game_id 
                where l.dateline>=".$stime." and l.dateline<".$etime."
                group by l.uid,g.prodt_id order by  dateline";
        $insertTable='sdklogs_new.49app_ios_member_product_login';
    }

    //echo $sql."<br><br>";
    $list = $db->query($sql)->get();
    if(count($list)==0){
        echo 'Count(0),End';
        return;
    }

    echo "total=".count($list)."<br>\r\n";
    $num=0;
    foreach($list as $k=>$v){
        //echo $k."#uid:".$v['uid']."#prodt_id:".$v['prodt_id'];

        //查找是否 click.app_common_member_product 是否存在记录,不存在则插入
        //$res=cassMemberProduct($cass,$v);
        //echo "#MP:".$res;

        //if($res===false)exit('cass app_common_member_product error');
        //if($res=='not_exists') {//不存在，可以插入数据库

            $is_sole=1;
            //$udid_idfa='';
            //if($type=='android')$udid_idfa=$v['udid'];
            //else $udid_idfa=$v['idfa'];
            //$res=cassProductDevice($cass,$udid_idfa,$v);
            //if($res=='exists')$is_sole=0;

            //插入db
            $insert = $v;
            $insert['is_sole']=$is_sole;
            $insert["prodt_id"]=(int)$insert["prodt_id"];

            $fields = [];
            $executeArray = [];
            foreach($insert as $key=>$val){
                $fields[] = $key;
                $executeArray[] = str_replace("'","\'",$val);
            }
            $fields_str = implode(',',$fields);
            $rawFieldsStr = implode("','", $executeArray);
            $qryStr = "INSERT IGNORE INTO ".$insertTable." (".$fields_str.") VALUES('".$rawFieldsStr."')";
            $res = $db->execute($qryStr);
            if (!$res) {
                exit('insert db error');
            }

            $num++;
            if($num%5000 == 0)
            {
                $db->commit();
                $db->transaction();
            }
        //}
        //echo " - ok <br>\r\n";
    }
    $db->commit();
    echo " <br>\r\n ALL FINISH(".$k.")";
}

//搜索cass member_product表，没有找到则插入记录
function cassMemberProduct($cass,$v){
    $map=[
        ["uid",'=',(int)$v['uid']],
        ["prodt_id",'=',(int)$v['prodt_id']]
    ];
    try{
        $cassMP=$status =  $cass->Select('*','app_common_member_product', $map);
    }catch(\Exception $e){
        return false;
    }
    if($cassMP==false || !$cassMP) {
        $data = [
            "uid" => (int)$v['uid'],
            "prodt_id" => (int)$v['prodt_id'],
            "ctime" => (int)$v["dateline"],
            "gid" => (int)$v['gid'],
            "ver" => $v['ver']
        ];
        try{
            $res=$cass->Insert('app_common_member_product', $data);
        }catch(\Exception $e){
            return false;
        }
        if($res){
            return 'not_exists';
        }
    }
    return 'exists';
}

//搜索cass match_product_device_sole，没有找到则插入记录
function cassProductDevice($cass,$udid_idfa,$v){
    $table='match_product_device_sole';
    $map = [
        ["udid_idfa", '=', $udid_idfa],
        ["prodt_id", '=', (int)$v['prodt_id']]
    ];
    try{
        $cassProdtDev = $status = $cass->Select('*', $table, $map);
    }catch(\Exception $e){
        return false;
    }
    if ($cassProdtDev == false || !$cassProdtDev) {
        $data = [
            "udid_idfa" => $udid_idfa,
            "prodt_id" => (int)$v['prodt_id'],
            "ctime" => (int)$v["dateline"],
            "gid" => (int)$v['gid'],
        ];
        try{
            $res=$cass->Insert($table, $data);
        }catch(\Exception $e){
            return false;
        }
        if($res){
            return 'not_exists';
        }
    }
    return 'exists';
}

?>
