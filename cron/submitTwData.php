<?php
//落地页点击浏览统计
ini_set('display_errors', 1);            //错误信息
ini_set('display_startup_errors', 1);    //php启动错误信息
ini_set('memory_limit','1024M');
error_reporting(-1);                    //打印出所有的 错误信息
set_time_limit(0);

require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/twModel.php");

class submitTwData extends twModel
{

    public function index()
    {
        $this->e_time = $this->s_time - 700;
        echo date('Y-m-d H:i:s',time());
        $this->submitCost();
        $this->submitGame();
        $this->submitVer();
//        $this->submitChannel();
        echo date('Y-m-d H:i:s',time());

    }

    public function submitCost()
    {
        $this->db->query("SELECT *,ver_id,sum(cost) as sum_cost,sum(cost_ago) sum_cost_ago FROM adv_statis.tbl_ad_channel_creative_cost where cdate >= '{$this->yesterday}' group by ver_id,gid");
        $res = $this->db->get();
        $storage = new storage();
        foreach ($res as $k=>$v){
            $key = "cost:{$v['ver_id']}:{$v['type']}";
            $cid_cost = $this->redis->get($key);
            if ($cid_cost == $v['sum_cost_ago']) continue; //成本相同，不传

            $game_list = $storage->getInfo($v['ver_id']);
            $param = [
                'platform'=>'ZW',
                'agent_id'=>$game_list['qd_id'],
                'ad_param'=>'ods_ad_agent_pay_day_log',
                'game_id'=>$game_list['game_sub_id'],
                'site_id'=>$v['ver_id'],
                'tdate'=>$v['cdate'],
                'add_type'=>2,
                'ori_money'=>$v['sum_cost_ago'],
                'money'=>$v['sum_cost'],
                'ad_type'=>$v['type'],
                'nature'=>2
            ];
            $res = $this->curl($this->url,$param,$v,[],true,30,30,'cost');
            if ($res !== false){
                $this->redis->set($key,$v['sum_cost_ago'],3600*36);
            }
        }
    }


    public function submitGame(){

        $time = strtotime("-1 hour");
        $this->db->query("SELECT
                                a.Id,a.package,a.name,a.gameId,b.gameName,c.productId,c.productName,b.IosOrAndroid
                            FROM
                                asgardstudio_admin.games_sub as a
                                LEFT JOIN asgardstudio_admin.games as b ON a.gameId = b.gameId
                                LEFT JOIN asgardstudio_admin.games_product as c on b.productId = c.productId
                                where a.create_time >= {$time}");
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $param = [
                'platform'=>'ZW',
                'plat_name'=>'手游',
                'dim_param'=>'dim_game_id',
                'root_game_id'=>$v['productId'],
                'root_game_name'=>$v['productName'],
                'main_game_id'=>$v['gameId'],
                'main_game_name'=>$v['gameName'],
                'game_id'=>$v['Id'],
                'game_name'=>$v['name'],
                'app_name'=>$v['name'],
                'app_package_name'=>$v['package'],
                'is_channel'=>0,
                'is_sale'=>0,
                'os'=>'安卓',
                'plat_id'=>2
            ];
            $this->curl($this->url,$param,$v,[],true,30,30,'game');
        }
    }

    public function submitChannel()
    {
        $time = strtotime($this->today);
        $this->db->query("SELECT * FROM adv_system.tbl_qd  where add_time >= {$time}");
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $key = "submit_tw_channel:{$v['id']}";
            $is_submit = $this->redis->get($key);
            if ($is_submit) continue;

            $param = [
                'platform'=>'ZW',
                'dim_param'=>'dim_channel_id',
                'agent_id'=>$v['id'],
                'agent_name'=>$v['title'],
                'type'=>1,
            ];
            $url = $this->url . http_build_query($param);
            $rs = fetchUrl($url, json_encode($param),true);
            if ($rs == 200 )  $this->redis->set($key,1,3600*24);
        }
    }

    public function submitVer()
    {
        $channel_type = [1=>'今日头条',2=>'广点通',3=>'百度',4=>'UC',5=>'快手',6=>'微信',7=>'公众号',8=>'抖音kol达人',9=>'自有量',998=>'冰雪平台',999=>'自营平台'];//投放渠道列表
        $tw_channel = [1=>5,2=>1,3=>4,4=>3,5=>17,6=>2,7=>168,8=>24,9=>7,998=>167,999=>166]; //贪玩对应关系表
        $channel = [1,2,3,4,5,6];
        $time = strtotime("-2 hour");

        $sql = "SELECT *,a.id as ver_id,c.title,a.game_sub_id as game_sub_id FROM adv_system.tbl_advert_channel_ver_info as a
                left join adv_system.tbl_channel_accounts as b on a.account_id = b.id
                left join adv_system.tbl_qd as c on a.qd_id = c.id
                where  a.create_time >= {$time}
                ";
        $this->db->query($sql);
        $res = $this->db->get();
        foreach ($res as $k=>$v){
            $key = "submit_tw_ver_id:{$v['ver_id']}";
            $is_submit = $this->redis->get($key);
            if ($is_submit) continue;
            $param = [
                'platform'=>'ZW',
                'dim_param'=>'dim_site_id_map',
                'agent_group_id'=>$tw_channel[$v['type']],
                'agent_group_name'=>$channel_type[$v['type']],
                'agent_leader'=>$v['use_username'],
                'site_id'=>$v['ver_id'],
                'site_name'=>$v['ver_id'],
                'account_id'=>in_array($v['type'],$channel) && $v['account_id'] != 3420 ? $v['channel_account_id'] : $v['qd_id'],
                'account_name'=>in_array($v['type'],$channel) && $v['account_id'] != 3420 ? $v['channel_account_name'] : $v['title'],
                'create_time'=>$v['create_time'],
                'game_id'=>$v['game_sub_id']
            ];
            $res = $this->curl($this->url,$param,$v,[],true,30,30,'ver');
            if ($res !== false ){
                $this->redis->set($key,1,7000);
            }
        }
    }
}

$mod = new submitTwData();
$mod->index();
?>