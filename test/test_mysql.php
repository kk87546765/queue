<?php
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);                    //打印出所有的 错误信息
//require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/db/class.db_click.php");



class test
{

    private $db;

    public function __construct()
    {
        $this->db = ClickDatabase::instance();
    }

    public function test_select()
    {
        $res = $this->db->table('tbl_click_log')->select(['limit'=>1]);
        print_r($res);exit;
    }

    public function test_insert()
    {

    }

    public function insert()
    {
        $sql = "INSERT INTO `tbl_register_log`(`id`, `month`, `uid`, `user_name`, `prodt_id`, `game_id`, `ver_id`, `channel_ver`, `dateline`, `login_time`, `login_ver_id`, `idfa`, `oaid`, `android_id`, `imei`, `udid`, `ip`, `is_sole`, `channel_type`, `aid`, `cid`, `sdk_version`, `version`, `device`, `create_time`) VALUES (733842, 1, 11160716, '6732226580', 0, 103077, 49962, 0, 1609916176, 1609916214, 49962, '', 'deebcf1cbd77b3f4-865166027984122', 'deebcf1cbd77b3f4', '865166027984122', 'deebcf1cbd77b3f4-865166027984122', '140.250.176.239', 0, 0, '0', '0', '1.0', '7.1.2', 2, 1609916176)";
        $res = $this->db->query($sql);
        print_r($res);
    }

    public function update()
    {
        $sql = "UPDATE tbl_register_log set idfa = '1245' where uid = 11160719";
        $res = $this->db->query($sql);
        print_r($res);
    }

}
$job = new test();
$res = $job->test_select();
//$res = $job->insert();
//$res = $job->update();

var_dump($res);








