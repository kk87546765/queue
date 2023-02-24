<?php
/**
 * Created by PhpStorm.
 * User: 49you
 * Date: 2020/6/23
 * Time: 14:23
 */


//require_once(dirname(dirname(__FILE__))) . "/controller/advSystem.php";
//
//$data = [];
//$res = advSystem::advSystemUser($data);
//var_dump($res);exit;


require_once(dirname(dirname(__FILE__)) . "/model/Storage.php");

$data = $_GET;

$device = isset($data['game_id']) && $data['game_id'] >= 100000 ? 1 : 2;

$storage = new storage();

$info = [
    'ip' => isset($data['ip']) ? $data['ip'] : '',
    'imei' => isset($data['imei']) ? $data['imei'] : '',
    'udid' => isset($data['udid']) ? $data['udid'] : '',
    'idfa' => isset($data['idfa']) ? $data['idfa'] : '',
    'appId'=>isset($data['game_id']) ? $data['game_id'] : '',
    'ver' => isset($data['package_ver']) ? $data['package_ver'] : '',
];


print_r($device);

$cid_list = $storage->getCid($info,$device);

var_dump($cid_list);
