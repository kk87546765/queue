<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/7
 * Time: 16:23
 */
require_once(dirname(dirname(__FILE__)) . "/model/CommonModel.php");
class writeFile extends CommonModel
{
    const ROUTE_OPEN = 'open/'; //打开的存储地址
    const ROUTE_ACT  = 'act/';   //激活的存储地址
    const ROUTE_REG  = 'reg/';   //注册的存储地址
    const ROUTE_SIGN  = 'sign/'; //登录的存储地址
    const ROUTE_PAY  = 'pay/';   //充值的存储地址
    const ROUTE_LEVEL  = 'level/';   //升级的存储地址
    const ROUTE_USERINFO  = 'userinfo/';   //升级的存储地址
    const ROOT_ROUTE = '/home/wwwroot/clicksys_queue/log/click/'; //根目录

    /**
     * 写入
     * @param $data
     * @param $type
     */
    public function write($data, $type)
    {
        $type_route = $this->returnTypeRoute($type);
        $date_dir = date('Y-m-d',time()).'/';
        $dir = static::ROOT_ROUTE.$type_route.$date_dir;
        !file_exists($dir) && mkdir($dir, 0777, true);
        $filename = $type.'.log';
        $data = json_encode($data);
        $this->save($data, $dir,$filename);


    }

    /**
     * 写入文件
     * @param $message
     * @param $destination
     */
    public function save($message, $destination, $filename)
    {
        $destination = $destination.$filename;
        $file_size = 2*1024*1024;
        if(is_file($destination) && $file_size <= filesize($destination) ){
            rename($destination,dirname($destination).'/'.time().'-'.basename($destination));
        }
        error_log("{$message}\r\n", 3,$destination);

    }

    /**
     * 返回正确存放目录
     * @param $type
     * @return string
     */
    public function returnTypeRoute($type)
    {
        $route = '';
        switch ($type)
        {
            case static::TYPE_OPEN:
                //处理打开
                $route = static::ROUTE_OPEN;
                break;
            case static::TYP_ACT:
                //处理激活
                $route = static::ROUTE_ACT;
                break;
            case static::TYPE_REG:
                //处理注册
                $route = static::ROUTE_REG;
                break;
            case static::TYPE_SIGN:
                //处理登录
                $route = static::ROUTE_SIGN;
                break;
            case static::TYPE_PAY:
                //处理充值
                $route = static::ROUTE_PAY;
                break;
            case static::TYPE_LEVEL:
                //处理充值
                $route = static::ROUTE_LEVEL;
                break;
            case static::TYPE_USERINFO:
                //处理充值
                $route = static::ROUTE_USERINFO;
                break;
        }

        return $route;

    }

}