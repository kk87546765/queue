<?php
/**
 * 广告信息重新报送
 * Created by PhpStorm.
 * User: lizc
 * Date: 2018/05/30
 * Time: 17:05
 */
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/ActivateModel.php");
require_once(dirname(dirname(__FILE__)) . "/model/Report.php");

ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
error_reporting(-1);
set_time_limit(0);
ini_set('memory_limit','4095M');

class repeatSubmit
{
    protected $db;
    protected $time = 1;

    public function index()
    {
        $callb    = new ChannelCallback();
        $report   = new Report();

        $config = require(dirname(dirname(__FILE__)) . "/config/config.php");
        $this->db = Database::instance($config['sdklogs']);

        $stime = $_GET['stime'];
        $etime = $_GET['etime'];

        $stype = isset($_GET['stype']) ? $_GET['stype'] : 'register';

        $advter_id = isset($_GET['advter_id']) ?  $_GET['advter_id'] : 0;

        if ($advter_id){
            $str_advter_id = " and   advter_id =  {$advter_id} ";
        } else {
            $str_advter_id = " and  advter_id != 0";
        }

        $android_sql = isset($_GET['android_sql']) ?  $_GET['android_sql'] : 0;

        $callback_status = isset($_GET['callback_status']) ?  $_GET['callback_status'] : 0;

        switch ($stype){
            case 'register':$submit_type = 1;break;
            case 'pay';$submit_type = 2;break;
            case 'install';$submit_type = 3;break;
            default:$submit_type = 1;break;
        }

        if (isset($_GET['device']) && $_GET['device'] == 'android'){
            if ($android_sql == 0 ){
                //$sql = "select uid,advter_id, ip, reg_ver as  ver,gid,imei,dateline from 49app_android_member_product_login where dateline >=$stime  and dateline <=$etime $str_advter_id ";
                $sql="SELECT 	l.uid,	l.advter_id,	l.ip,	l.reg_ver AS ver,	l.gid,	l.imei,	l.dateline ,sum(amount) as amount 
                    FROM	49app_android_member_product_login as l	left join mei.49app_pay_order as o on l.uid=o.uid 
                    WHERE
                        l.dateline >= UNIX_TIMESTAMP($stime)
                        AND l.dateline <= UNIX_TIMESTAMP('2020-07-16') 
                        
                        and o.dateline >= UNIX_TIMESTAMP('2020-07-16')
                        AND o.dateline <= UNIX_TIMESTAMP('2020-07-16') 
                        and l.ver in ('ttbld025','ttbld026') 
                        and o.status=0 
                        group by  l.uid ";
            } else {
                $sql = "select uid,advter_id, ip,ver,gid,muid as imei,dateline from 49app_data_report_log where dateline >=$stime  and dateline <=$etime and stype = $submit_type and callback_status = $callback_status $str_advter_id ";
            }
            $key = 'getAndroidInfo';
            $device = 'android';
        } else {
            if ($android_sql == 0 ){
                //$sql = "select uid,advter_id, ip, reg_ver as  ver,gid,imei,dateline from 49app_android_member_product_login where dateline >=$stime  and dateline <=$etime $str_advter_id ";
                $sql="SELECT 	l.uid,	l.advter_id,	l.ip,	l.reg_ver AS ver,	l.gid,	l.idfa,	l.dateline ,sum(amount) as amount 
                    FROM	49app_ios_member_product_login as l	left join mei.49app_pay_order as o on l.uid=o.uid 
                    WHERE
                        l.dateline >= UNIX_TIMESTAMP('{$stime}')
                        AND l.dateline <= UNIX_TIMESTAMP('{$etime}') 
                        
                        and o.dateline >= UNIX_TIMESTAMP('{$stime}')
                        AND o.dateline <= UNIX_TIMESTAMP('{$etime}') 
                        and l.advter_id  = $advter_id
                        and o.status=0 
                        group by  l.uid ";
            } else {
                $sql = "select uid,advter_id,ip,reg_ver ver,idfa,gid,dateline from 49app_ios_member_product_login where dateline >=$stime  and dateline <=$etime $str_advter_id ";
            }
            $key = 'getAdvterInfo';
            $device = 'ios';
        }

        if (isset($_GET['debug']) && $_GET['debug'] == '1'){
            echo $sql;exit;
        }

        $mem_res = $this->db->query($sql)->get();
        $num = 0;
        echo '---统计开始（'.date('Y-m-d H:i:s').'）---<br>';
        echo '总数据量:'.count($mem_res).'<br>';
        foreach ($mem_res as $k=>$v){
            $advter_info = $report->$key($v['advter_id']);      //获取推广包信息
            if (isset($_GET['debug']) && $_GET['debug'] == '2'){
                var_dump($advter_info);exit;
            }
            if($advter_info && isset($advter_info['channel_from']) && !empty($advter_info['channel_from'])) {

                $channel_config = $report->getChannelConfig($advter_info['channel_from']);  // 获取渠道配置

                if (isset($_GET['debug']) && $_GET['debug'] == '3'){
                    var_dump($channel_config);exit;
                }

                if(isset($channel_config['callback_func']) && $channel_config['callback_func']){

                    $param = [
                        'device'        => $device,
                        'stype'         => $stype,
                        'advter_id'     => $v['advter_id'],
                        'gid'           => $v['gid'],
                        'appstore_id'   => $advter_info['appstore_id'],
                        'channel_code'  => $channel_config['channel_code'],
                        'finish_time'   => $v['dateline'],
                        'amount' => $v['amount'],
                    ];

                    $data = array_merge($v,$param);
                    $callback_func = $channel_config['callback_func'];  // 调用渠道回调函数
                    try{
                        $channel_rs = $callb->$callback_func($data);
                        var_dump($channel_rs);
                        $num ++;
                    } catch (Exception $e){
                        $channel_rs = false;
                    }
                    if (isset($_GET['debug']) && $_GET['debug'] == '4'){
                        var_dump($data,$channel_rs);exit;
                    }
                }
            }
        }
        echo '---统计结束（'.date('Y-m-d H:i:s').'）---<br>';
        echo $num;
    }
}


$mod = new repeatSubmit();
$mod->index();
