<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 14:10
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db_click.php");
require_once(dirname(dirname(__FILE__)) . "/common/common.php");

class mysqlMember
{
    protected $db;

    public function matchChn($device)
    {
        $this->db = ClickDatabase::instance();
        $where_ver = $where_game = [];
        $where_game_str = $where_ver_str = 'where ';
        foreach ($device as $k => $v)
        {
            if (!$v) continue;
            switch ($k)
            {
                case 'oaid':
                    $str = checkParam($v);
                    $where_ver['oaid_ver_id_key'] = $oaid_ver_id_key =  md5($str.$device['ver_id']);
                    $where_game['oaid_game_id_key'] = $oaid_game_id_key = md5($str.$device['game_id']);
                    break;
                case 'imei':
                    $str = checkParam($v);
                    $where_ver['imei_ver_id_key'] =  md5($str.$device['ver_id']);
                    $where_game['imei_game_id_key'] =  md5($str.$device['game_id']);
                    break;
                case 'idfa':
                    $str = checkParam($v);
                    $where_ver['idfa_ver_id_key'] =  md5($str.$device['ver_id']);
                    $where_game['idfa_game_id_key'] =  md5($str.$device['game_id']);
                    break;
                case 'ip':
//                    $str = checkParam($v);
                    $where_ver['user_ip_ver_id_key'] =  md5($v.$device['ver_id']);
                    $user_ip_game_id_key =  md5($v.$device['game_id']);
                    break;
            }
        }
        if (empty($where_ver) && empty($where_game)) return false;

        foreach ($where_ver as $key=>$value){
            $where_ver_str .= " $key = '{$value}' or ";
        }

        foreach ($where_game as $key=>$value){
            $where_game_str .= " $key = '{$value}' or ";
        }

        $where_ver_str = rtrim($where_ver_str,' or ');
        $where_game_str = rtrim($where_game_str,' or ');

        if ($where_ver){
            $sql = "SELECT * FROM click_log.tbl_match_channel_log $where_ver_str order by id desc limit 1";
            $ver_res = $this->db->query($sql)->get();
            if (isset($ver_res[0])) return $ver_res[0];
        }

        if ($where_game){
            $sql = "SELECT * FROM click_log.tbl_match_channel_log $where_game_str order by id desc limit 1";
            $game_res = $this->db->query($sql)->get();
            if (isset($game_res[0])) return $game_res[0];
        }

        $sql = "SELECT * FROM click_log.tbl_match_channel_log where user_ip_game_id_key = '{$user_ip_game_id_key}' order by id desc limit 1";
        $game_ip_res = $this->db->query($sql)->get();
        if (isset($game_ip_res[0])) return $game_ip_res[0];

        return false;
    }


    //获取点击数据
    public function getClickInfo($data)
    {
        $this->db = ClickDatabase::instance();
        $sql = "SELECT *,ver_id as channel_ver FROM click_log.tbl_click_log where id = {$data['click_log_id']}";
        $res = $this->db->query($sql)->get();
        if (isset($res[0])) return $res[0];
    }

}