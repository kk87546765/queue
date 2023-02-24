<?php
//落地页点击浏览统计
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
ini_set('memory_limit','1024M');
error_reporting(-1);                    //打印出所有的 错误信息

require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");

class Langding
{
    protected $db,$redis,$time,$today,$yesterday;

    public function index()
    {
        $this->db = Database::instance();
        $this->redis = RedisModel::instance();

        $this->time = isset($_GET['time']) ? strtotime($_GET['time']) : time();
        $this->today = strtotime(date('Ymd',$this->time));

        $key = 'ver_list_'.$this->today;
        $ver_today_list = $this->redis->hGetAll($key);
        $this->setLandingHour($ver_today_list);
        $this->setLandingDay($ver_today_list);


    }

    public function setLandingHour($list){
        if (!$list) return false;

        $res = $this->db->table('tbl_langding_data_hour')->select(array('condition'=>" where dateline >= '$this->today'"));
        $ver_list = $this->db->get();

        $ver_host_key_list = [];
        foreach ($ver_list as $k=>$v){
            $ver_host_key_list[] = $v['ver_id'].'_'.$v['dateline'].'_'.$v['device'];
        }

        $this->db->transaction();
        $num = 0;
        $hour = 23;
        foreach ($list as $k=>$v){
            $ver_ip_visit_list_ios = $ver_ip_visit_list_android = $ver_ip_click_list_ios = $ver_ip_click_list_android = [];

            for($i = 0 ;$i <= $hour ; $i++){
                $time = $this->today + $i*3600;
                $ver_visit_key_ios = 'ver_visit_ip_list_'.$time.'_'.$k.'_1'; //获取IOS的浏览ip数
                $ver_visit_ios = $this->redis->hGetAll($ver_visit_key_ios);

                $ver_visit_key_android = 'ver_visit_ip_list_'.$time.'_'.$k.'_2';//获取android的浏览ip数
                $ver_visit_android = $this->redis->hGetAll($ver_visit_key_android);

                $ver_click_key_ios = 'ver_click_ip_list_'.$time.'_'.$k.'_1'; //获取IOS的点击ip数
                $ver_click_ios = $this->redis->hGetAll($ver_click_key_ios);

                $ver_click_key_android = 'ver_click_ip_list_'.$time.'_'.$k.'_2';//获取android的点击ip数
                $ver_click_android = $this->redis->hGetAll($ver_click_key_android);


                if ($ver_visit_ios) $ver_ip_visit_list_ios[$time] = $ver_visit_ios;
                if ($ver_visit_android) $ver_ip_visit_list_android[$time] = $ver_visit_android;
                if ($ver_click_ios) $ver_ip_click_list_ios[$time] = $ver_click_ios;
                if ($ver_click_android) $ver_ip_click_list_android[$time] = $ver_click_android;
            }

            //组装数据
            if ($ver_ip_visit_list_android){
                foreach ($ver_ip_visit_list_android as $key=>$val){
                    $android_visit_list_key = "{$k}_{$key}_2";
                    $ins_param[$android_visit_list_key] = [
                        'device'=>2,
                        'ver_id'=>$k,
                        'dateline'=>$key,
                        'visit'=>count($val),
                    ];
                }
            }

            if ($ver_ip_visit_list_ios){
                foreach ($ver_ip_visit_list_ios as $key=>$val){
                    $ios_visit_list_key = "{$k}_{$key}_1";
                    $ins_param[$ios_visit_list_key] = [
                        'device'=>1,
                        'ver_id'=>$k,
                        'dateline'=>$key,
                        'visit'=>count($val),
                    ];
                }
            }

            //将点击数据合并到主数组内
            foreach ($ver_ip_click_list_ios as $key=>$val){
                $ios_click_list_key = "{$k}_{$key}_1";
                if (isset($ins_param[$ios_click_list_key])){
                     $ins_param[$ios_click_list_key]['click'] = count($val);
                }
            }

            //将点击数据合并到主数组内
            foreach ($ver_ip_click_list_android as $key=>$val){
                $android_click_list_key = "{$k}_{$key}_2";
                if (isset($ins_param[$android_click_list_key])){
                    $ins_param[$android_click_list_key]['click'] = count($val);
                }
            }

            foreach ($ins_param as $key=>$value){
                $ins_key = $value['ver_id'].'_'.$value['dateline'].'_'.$value['device'];
                if (in_array($ins_key,$ver_host_key_list)){
                    $where_param = [
                        'ver_id'=>$value['ver_id'],
                        'device'=>$value['device'],
                        'dateline'=>$value['dateline'],
                    ];
                    $update_param = [
                        'click'=>isset($value['click']) ? $value['click'] :0 ,
                        'visit'=>isset($value['visit']) ? $value['visit'] :0 ,
                    ];
                    $this->db->table('tbl_langding_data_hour')->update($update_param,$where_param);
                } else {
                    $this->db->table('tbl_langding_data_hour')->insert($value);
                }

            }

            $num++;
            if ($num % 100 == 0 ){
                $this->db->commit();
                $this->db->beginTransaction();
            }
        }
        $this->db->commit();
    }

    public function setLandingDay($list){
        if (!$list) return false;

        $res = $this->db->table('tbl_langding_data')->select(array('condition'=>" where dateline = '$this->today'"));
        $ver_list = $this->db->get();

        $ver_host_key_list = [];
        foreach ($ver_list as $k=>$v){
            $ver_host_key_list[] = $v['ver_id'].'_'.$v['dateline'].'_'.$v['device'];
        }

        $this->db->transaction();
        $num = 0;
        $hour = 23;
        foreach ($list as $k=>$v){
            $ver_ip_visit_list_ios = $ver_ip_visit_list_android = $ver_ip_click_list_ios = $ver_ip_click_list_android = [];

            $time = $this->today;
            $ver_visit_key_ios = 'ver_visit_ip_list_'.$time.'_'.$k.'_1'; //获取IOS的浏览ip数
            $ver_visit_ios = $this->redis->hGetAll($ver_visit_key_ios);

            $ver_visit_key_android = 'ver_visit_ip_list_'.$time.'_'.$k.'_2';//获取android的浏览ip数
            $ver_visit_android = $this->redis->hGetAll($ver_visit_key_android);

            $ver_click_key_ios = 'ver_click_ip_list_'.$time.'_'.$k.'_1'; //获取IOS的点击ip数
            $ver_click_ios = $this->redis->hGetAll($ver_click_key_ios);

            $ver_click_key_android = 'ver_click_ip_list_'.$time.'_'.$k.'_2';//获取android的点击ip数
            $ver_click_android = $this->redis->hGetAll($ver_click_key_android);

            if ($ver_visit_ios) $ver_ip_visit_list_ios[$time] = $ver_visit_ios;
            if ($ver_visit_android) $ver_ip_visit_list_android[$time] = $ver_visit_android;
            if ($ver_click_ios) $ver_ip_click_list_ios[$time] = $ver_click_ios;
            if ($ver_click_android) $ver_ip_click_list_android[$time] = $ver_click_android;


            //组装数据
            if ($ver_ip_visit_list_android){
                foreach ($ver_ip_visit_list_android as $key=>$val){
                    $android_visit_list_key = "{$k}_{$key}_2";
                    $ins_param[$android_visit_list_key] = [
                        'device'=>2,
                        'ver_id'=>$k,
                        'dateline'=>$key,
                        'visit'=>count($val),
                    ];
                }
            }

            if ($ver_ip_visit_list_ios){
                foreach ($ver_ip_visit_list_ios as $key=>$val){
                    $ios_visit_list_key = "{$k}_{$key}_1";
                    $ins_param[$ios_visit_list_key] = [
                        'device'=>1,
                        'ver_id'=>$k,
                        'dateline'=>$key,
                        'visit'=>count($val),
                    ];
                }
            }

            //将点击数据合并到主数组内
            foreach ($ver_ip_click_list_ios as $key=>$val){
                $ios_click_list_key = "{$k}_{$key}_1";
                if (isset($ins_param[$ios_click_list_key])){
                    $ins_param[$ios_click_list_key]['click'] = count($val);
                }
            }

            //将点击数据合并到主数组内
            foreach ($ver_ip_click_list_android as $key=>$val){
                $android_click_list_key = "{$k}_{$key}_2";
                if (isset($ins_param[$android_click_list_key])){
                    $ins_param[$android_click_list_key]['click'] = count($val);
                }
            }

            foreach ($ins_param as $key=>$value){
                $ins_key = $value['ver_id'].'_'.$value['dateline'].'_'.$value['device'];
                if (in_array($ins_key,$ver_host_key_list)){
                    $where_param = [
                        'ver_id'=>$value['ver_id'],
                        'device'=>$value['device'],
                        'dateline'=>$value['dateline'],
                    ];
                    $update_param = [
                        'click'=>isset($value['click']) ? $value['click'] :0 ,
                        'visit'=>isset($value['visit']) ? $value['visit'] :0 ,
                    ];
                    $this->db->table('tbl_langding_data')->update($update_param,$where_param);
                } else {
                    $this->db->table('tbl_langding_data')->insert($value);
                }

            }

            $num++;
            if ($num % 100 == 0 ){
                $this->db->commit();
                $this->db->beginTransaction();
            }
        }
        $this->db->commit();
    }

}


$mod = new Langding();
$mod->index();
?>