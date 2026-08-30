<?php
namespace myswoole;

class SwooleTasks
{
    private static $obj = null;

    public static function getInstance()
    {
        if (is_null(self::$obj)) {
            self::$obj = new self();
        }
        return self::$obj;
    }

    /**
     * 测试任务
     * @param $data
     * @param $serv
     * @return true
     */
    public function test($data, $serv)
    {
        var_dump($serv->manager_pid);
        var_dump("|123|");
        var_dump($data);
        return true;
    }

    public function __call($data, $serv)
    {

        echo 'call coming';
        return true;
    }
}
