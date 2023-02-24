<?php
require_once(dirname(dirname(__FILE__)) . "/model/Worker.php");

class guard
{
    //最大进程数

    private $size;
    private $curSize;

    /**
     * 构造函数
     * @param string $worker 需要创建的消费者类名
     * @param int $size 最大子进程数量
     * @param $producer 需要创建的消费者类名
     */

    public function __construct($size = 10)
    {
        $this->size = $size;
        $this->curSize = 0;
    }

    public function start()
    {
            while (true) {
                usleep(200);
                $pid = pcntl_fork();
                if ($pid == -1) {
                    die("could not fork");
                } elseif ($pid) {// parent
                    $this->curSize++;

                    if ($this->curSize >= $this->size) {

                        $sunPid = pcntl_wait($status);
                    }
                } else {
                    // worker
                    $worker = new worker();

                    $worker->run();

                    exit();

                }
            }
    }

}