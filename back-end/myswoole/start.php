<?php
// 查看swoole进程情况
// netstat -anop |findstr "9501"
// netstat -anop| grep 950
// netstat -tunlp| grep

// 记住对应的进程IDtaskkill  /PID 1984 -T -F (其中-T是包括了子进程，-F是强制)，强制结束该进程
require_once './Service.php';
new \myswoole\Server();
