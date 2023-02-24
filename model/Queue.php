<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/7
 * Time: 11:05
 */
require_once(dirname(dirname(__FILE__)) . "/model/CommonModel.php");

class queue extends CommonModel
{

    const BLOCK_TIME = 5; //空队列等待时间
    private $key_bak;


    public function __construct()
    {
        $this->key_bak = static::LIST_DISASTER.date('Y-m-d-H',time());
        parent::__construct();
    }


    /**
     *从队列取出内容
     * @return string
     */
    public function popList()
    {
        $key = static::LIST_NAME;
        $block_time = static::BLOCK_TIME;
        $res = $this->redis->brpoplpushs($key, $this->key_bak, $block_time);
        return $res;

    }

    /**
     * 队列成功处理结束后执行
     * @param $data
     * @return int
     */
    public function popListEnd($data, $key = null)
    {
        if($key == null)
        {
            $key = $this->key_bak;
        }

        return $this->redis->lrems($key,$data);
    }

    /**
     * 获取容灾列表里面的数据
     * @param $key
     * @param int $num
     * @return array
     */
    public function getDisList($key = null,$num = 100, $debug = false)
    {
        if($key == null)
        {
            $key = $this->key_bak;
        }
        if($debug == true)
        {
            echo $key;
        }
        return $this->redis->lranges($key,0,$num);

    }

    /**
     * 清空队列所有数据
     * @return bool
     */
    public function flush_All()
    {
        return $this->redis->flushAll();
    }


    /**
     * 设置僵尸进程队列
     * @return string
     */
    public function setZombieList($key,$value)
    {
        return $this->redis->lpush($key,$value);
    }


    /**
     * 获取所有僵尸进程队列
     * @return string
     */
    public function getZombieList($key)
    {
        return $this->redis->redisOtherMethods()->LRANGE($key,0,-1);
    }

    /**
     * 清除队列
     * @param $key
     */
    public function del($key)
    {
        return $this->redis->del($key);
    }


}
