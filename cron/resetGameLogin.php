<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/14
 * Time: 11:02
 */

require_once(dirname(dirname(__FILE__)) . "/model/HandleSign.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");


$reg = new handleSign();
$db_storage = new storage();
$db=Database::instance();

if (isset($_GET['debug']) && $_GET['debug'] =='changeData'){
    changeDate($db); //修改首登时间
    exit;
}


$sql = "SELECT *
            FROM
                49app_new_register_ios_log
            WHERE
                gid = 100058
            AND dateline >= UNIX_TIMESTAMP('2019-1-10')
            AND dateline < UNIX_TIMESTAMP('2019-1-15')
            AND uid NOT IN (
                SELECT
                    uid
                FROM
                    49app_ios_member_game_login
                WHERE
                    gid = 100058
                AND dateline >= UNIX_TIMESTAMP('2019-1-10')
                AND dateline < UNIX_TIMESTAMP('2019-1-15')
            )
            ORDER BY
                dateline ASC";

$list = $db->query($sql)->get();
foreach ($list as $k => $data)
{
    $insert['uid']          = isset($data['uid'])?$data['uid']:'';
    $advter_id = $db_storage->getIosAdvterId($insert['uid']);
    $insert['month']        = isset($data['dateline'])?date('m',$data['dateline']):0;
    $insert['advter_id']    = $advter_id;
    $insert['username']     = isset($data['username'])?$data['username']:'';
    $insert['mac']          = isset($data['mac'])?$data['mac']:'';
    $insert['version']      = isset($data['version'])?$data['version']:'';
    $insert['gid']          = isset($data['gid'])?$data['gid']:0;
    $insert['idfa']         = isset($data['idfa'])?$data['idfa']:'';
    $insert['idfv']         = isset($data['idfv'])?$data['idfv']:'';
    $insert['sole_udid']    = isset($data['idfv'])?$data['idfv']:'';
    $insert['user_agent']   = '';
    $insert['user_ip']      = isset($data['user_ip'])?$data['user_ip']:'127.0.0.1';
    $insert['server_id']      = isset($data['serverId'])?$data['serverId']:'';

    $ipArr                  = IpToLocation($insert['user_ip']);
    $insert['country']      = $ipArr['country'];
    $insert['area']         = $ipArr['area'];
    $insert['dateline']     = isset($data['dateline'])?$data['dateline']:0;
    $insert['reg_date']     = isset($data['reg_date'])?$data['reg_date']:0;
    $insert['serial']       = isset($data['serialId'])?$data['serialId']:'';

    $gamelogin = $insert;
    $gamelogin['login_ver']    = 'App Store_hlw';
    $gamelogin['reg_ver']      = 'App Store_hlw';
    //游戏初次登录记录
    $res = $db_storage->saveIosGameList($gamelogin);

    if (isset($_GET['debug']) && $_GET['debug'] =='lizc'){
        var_dump($gamelogin);
        var_dump($res);
        exit;
    }
    if(!$res){
        echo " - err <br>\r\n";
        break;
    }
    echo " - ok <br>\r\n";

}



function changeDate($db)
{
    $date_sql = "SELECT
                a.uid,
                a.dateline as time1,
                b.dateline as time2
            FROM
                sdklogs_new.`49app_new_register_ios_log` AS a
            INNER JOIN sdklogs_new.`49app_ios_member_game_login` AS b ON a.uid = b.uid
            WHERE
                a.gid = 100058
                AND a.dateline >= UNIX_TIMESTAMP('2019-01-10')
                AND a.dateline < UNIX_TIMESTAMP('2019-01-12')
                AND b.gid = 100058
                AND a.dateline >= UNIX_TIMESTAMP('2019-01-10')";


    $list = $db->query($date_sql)->get();
    try {
        $db->transaction();
        $num = 0;
        foreach ($list as $k => $v) {
            if ($v['time1'] != $v['time2'] && ($v['time2'] - $v['time1'] > 600)) { //注册时间与首登时间相差一小时以上,修改
                $update_sql = "update sdklogs_new.`49app_ios_member_game_login` set dateline = {$v['time1']} where uid = '{$v['uid']}' and gid = 100058 ";
                $list = $db->query($update_sql)->get();
                $num++;
                if ($num % 10 == 0) {
                    $db->commit();
                    $db->transaction();
                }
            }
        }
        $db->commit();
        echo $num;
    } catch (PDOException $e) {
        $content = 'Insert Failure Log  : ' . date('Y-m-d H:i', time()) . 'error:' . $e->getMessage() . "\r\n";
        echo $content;
        $db->rollBack();
    }
}