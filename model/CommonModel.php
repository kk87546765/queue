<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/31
 * Time: 16:59
 */
require_once(dirname(dirname(__FILE__)) . "/db/RedisModel.class.php");
require_once(dirname(dirname(__FILE__)) . "/model/HandleOpen.php");


class CommonModel{

    const TYPE_OPEN = 1;  //打开
    const TYP_ACT = 2;   //激活
    const TYPE_REG = 3;   //注册
    const TYPE_SIGN = 4;  //登录
    const TYPE_PAY = 5; //充值
    const TYPE_LEVEL = 300; //等级上报 网赚
    const TYPE_USERINFO = 301; //用户信息等级上报
    const TYPE_CLICK = 500; //用户信息等级上报
    const LIST_NAME = 'clickList';  //队列名
    const LIST_DISASTER = 'clickDis'; //容灾队列，按小时存

    protected $redis;
    public function __construct()
    {
        $this->redis = RedisModel::instance();
    }


    /**
     * 处理任务详情
     * @param $data
     * @return bool
     */
    protected function handleType($data)
    {
        $status = true;
        switch ($data['type'])
        {
            case static::TYPE_OPEN:
                //处理打开
                $status = $this->open->start($data['data']);
                break;
            case static::TYP_ACT:
                //处理激活
                $status = $this->act->start($data['data']);
                break;
            case static::TYPE_REG:
                //处理注册
                $status = $this->reg->start($data['data']);
                break;
            case static::TYPE_SIGN:
                //处理登录
                $status = $this->sign->start($data['data']);
                break;
            case static::TYPE_PAY:
                //处理充值
                $status = $this->pay->start($data['data']);
                break;
            case static::TYPE_LEVEL:
                //处理等级上报
                $status = $this->level->start($data['data']);
                break;
            case static::TYPE_USERINFO:
                //处理等级上报
                $status = $this->userinfo->start($data['data']);
                break;
            case static::TYPE_CLICK:
                //处理等级上报
                $status = $this->click->start($data['data']);
                break;
        }

        return $status;

    }

}