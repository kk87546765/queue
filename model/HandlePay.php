<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once(dirname(__FILE__)) . "/Report.php";
require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");
require_once(dirname(dirname(__FILE__)) . "/api/submitTw.php");

class handlePay
{

    private $common_click_log = [];

    public function start($data)
    {
        $this->writeLog($data);
        $this->changeParam($data);
        $this->sendChannel($data['order_info']);
        $storageAdv = new storageAdv();
        $order_list = $storageAdv->checkOrder($data['order_info']['orderId']);
        $this->dataReport($data['order_info'],$order_list);
        $submitTw = new submitTw();
        $submitTw->index('pay',$data,$order_list);
        return true;
    }

    /*
     * 参数转换
     * */
    public function changeParam(&$data)
    {
        $fixed = ['oaid'=>'regImei', 'imei'=>'imei1','user_name'=>'userAcount','idfa'=>'idfa', 'android_id'=>'androidId', 'ver_id'=>'channelId', 'game_id'=>'gameId','uid'=>'Uid','ip'=>'regIp','dateline'=>'regTime','version'=>'android_version'];
        foreach ($data['order_info'] as $k=>$v){
            foreach ($fixed as $key=>$val){
                if ($k == $val) $data[$key] = $v; //参数转化
            }
        }
    }


    public function writeLog($data)
    {
        $write = new writeFile();
        $write->write($data,5);
    }

    public function sendChannel($data)
    {
        if (!isset($data['Uid'])) return true;
        $db = new storage();
        $user_info = $db->getUserInfo($data['Uid']);

        $this->common_click_log = $db->getNewCommonLog($user_info); //数据匹配，获取点击数据
        $data = array_merge($data,$user_info);
        $data['stype']       = 'pay';
        $data['amount'] = $data['payMoney'];
        $data['order_id'] = $data['orderId'];
        $data['common_log'] = $this->common_click_log;

        $check_res = $db->check_order($data['Uid'],$data['order_id']);
        if ($check_res) return true;
        $db->disconnect();

        $model = new Report();
        $res = $model->run($data);
        return $res;
    }

    //冰雪数据上报
    public function dataReport($data,$order_list)
    {
        $storageAdv = new storageAdv();
        if (!$order_list) return true;
        $ver_id = $order_list['sonChannel'];
        $ver_list = $storageAdv->getVerInfo($ver_id);

        if ($ver_list  && $ver_list['type'] == 998) { //冰雪订单上报
            $game_list = $storageAdv->getGameList($order_list['gameId']);
            $uri = 'https://gameapi.bingxuer.com/pay/record?';
            $data = [
                'platform' => 'zw',
                'uid' => $order_list['Uid'],
                'trade_no' => $order_list['orderId'],//掌玩订单号
                'game_name' => $ver_list['product_name'],//游戏名称
                'game_id' => $game_list['productId'],//产品id
                'channel' => $order_list['sonChannel'],//渠道号
                'type' => $order_list['payWay'] == 10 ? 1 : 2,//支付类型：1-微信，2-支付宝
                'amount' => $order_list['payMoney'] * 100,//消耗金额，单位分
                'req_time' => time(),
                'key' => 'B75NrlL83EuOejL9lufZEVAfOX6fw5ty',
            ];
            ksort($data);
            $data['sign'] = md5(http_build_query($data));
            unset($data['key']);

            $url = $uri . http_build_query($data);
            $res = fetchUrl($url);
            setLog($url . "@" . $res . '#'  . "\r\n\r\n", 'channel_callback/' . __FUNCTION__ . '/' . date('ymd') . '/');
        }
    }

}