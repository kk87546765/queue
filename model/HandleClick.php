<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/11
 * Time: 17:27
 */
require_once(dirname(dirname(__FILE__)) . "/common/common.php");
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once(dirname(dirname(__FILE__)) . "/model/WriteFile.php");
require_once(dirname(dirname(__FILE__)) . "/db/mongodb.php");

class handleClick
{

    public function start($data)
    {
        $ctime = new MongoDB\BSON\UTCDateTime(time()*1000);
        $data['ctime'] = $ctime;
        $mongo = MongoDB::getInstance();
        $ins_res = $mongo->collection('click_log')->Insert($data);
        return $ins_res;
    }
}