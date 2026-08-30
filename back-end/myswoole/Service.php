<?php

namespace myswoole;

use think\Exception;

require_once __DIR__ . '/../config/env.php';

class Server
{
    private $serv;
    private $workerPids = [];  // 记录 worker 进程信息
    private $isShuttingDown = false;  // 是否正在关闭服务
    private $root_path;
    /** @var string Unique per Swoole process generation */
    private $serverInstanceId;
    /** @var bool */
    private $workerMonitorRegistered = false;

    // 重启相关常量
    const WORKER_RESTART_INTERVAL = 60;  // worker 重启间隔(秒)
    const MAX_WORKER_RESTART_ATTEMPTS = 3;  // 最大重启尝试次数
    private $workerRestartAttempts = [];  // 记录每个 worker 的重启尝试次数
    private $lastExchangeRateSyncAt = 0;  // 上次投递汇率同步的时间戳，用于分钟 tick 里按 5 分钟节流

    public function __construct()
    {
        // 设置错误处理
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);

        $this->root_path = dirname(__DIR__);
        $this->serverInstanceId = strtoupper(bin2hex(random_bytes(16)));

        // 确保 runtime 目录存在
        $runtimeDir = $this->root_path . '/runtime';
        if (!is_dir($runtimeDir)) {
            if (!mkdir($runtimeDir, 0755, true)) {
                error_log("无法创建 runtime 目录: {$runtimeDir}");
            }
        }

        $bindHost = trim((string)\config_env('SWOOLE_BIND_HOST', '127.0.0.1'));
        if ($bindHost === '') {
            throw new \RuntimeException('SWOOLE_BIND_HOST must not be empty.');
        }
        $this->serv = new \Swoole\Server($bindHost, \config_swoole_port());
        //配置参数
        $this->serv->set(array(
            // task进程的数量
            'task_worker_num' => 40,
            // worker进程数量一般设置为服务器CPU数的1-4倍
            'worker_num' => 32,
            // 1以守护进程执行,0 否
            'daemonize' => 0,
            'max_request' => 1000,  // 每个 worker 处理 1000 个请求后重启，防止内存泄漏
            'dispatch_mode' => 3,
            'reload_async' => true,  // 异步安全重启
            'max_wait_time' => 60,   // 最大等待时间
            'log_file' => $this->root_path . '/runtime/swoole_error.log',  // 错误日志
            'pid_file' => $this->root_path . '/runtime/swoole.pid',  // PID 文件
            /*1 => 轮循模式，收到会轮循分配给每一个worker进程
            2 => 固定模式，根据连接的文件描述符分配worker。这样可以保证同一个连接发来的数据只会被同一个worker处理
            3 => 抢占模式，主进程会根据Worker的忙闲状态选择投递，只会投递给处于闲置状态的Worker*/
            'dispatch_mode' => 3,
            // 使用Unix Socket通信，默认模式
            // "task_ipc_mode " => 1,
            // 解决粘包问题：同一个链接,短时间内多次投递数据，可能会有数据粘包问题
            // 自动分包
            'open_eof_split' => true,   //swoole底层实现自动分包。比较消耗cpu资源
            // 手动分包 explode("\r\n", $data) 来拆分数据包
            'open_eof_check' => true,   //打开EOF检测,每次包以EOF结尾，才send给服务端
            'package_eof'    => "$$$###", //设置EOF
        ));

        // 注册事件回调
        $this->serv->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->serv->on('WorkerStop', [$this, 'onWorkerStop']);
        $this->serv->on('WorkerError', [$this, 'onWorkerError']);
        $this->serv->on('ManagerStart', [$this, 'onManagerStart']);
        $this->serv->on('ManagerStop', [$this, 'onManagerStop']);
        // 当客户端发送数据时，会触发Receive函数。此方法中能接受到客户端send的参数
        $this->serv->on('Receive', array($this, 'onReceive'));

        // 在task方法中，执行对应的操作
        $this->serv->on('Task', array($this, 'onTask'));

        // 当任务执行完成后，会触发在finish事件。
        $this->serv->on('Finish', array($this, 'onFinish'));

        // 启动监控协程
        $this->startMonitor();

        // 启动swoole服务
        $this->serv->start();
    }

    /**
     * Worker 进程启动时调用
     */
    public function onWorkerStart($serv, $workerId)
    {
        // 记录 worker 进程信息
        $this->workerPids[$workerId] = [
            'pid' => $serv->worker_pid,
            'start_time' => time(),
            'last_active_time' => time()
        ];

        // 重置重启尝试次数
        $this->workerRestartAttempts[$workerId] = 0;

        // 设置进程标题
        cli_set_process_title("swoole_worker_{$workerId}");

        // $this->log("Worker {$workerId} 已启动 (PID: {$serv->worker_pid})");

        // 只让 worker 0 执行定时任务，避免重复执行
        if ($workerId === 0) {
            // $this->log("Worker {$workerId} 将启动定时任务 (PID: {$serv->worker_pid})");
            $this->startScheduledTasks($serv);
        }
    }

    /**
     * Worker 进程停止时调用
     */
    public function onWorkerStop($serv, $workerId)
    {
        // $this->log("Worker {$workerId} 已停止 (PID: {$serv->worker_pid})");
        unset($this->workerPids[$workerId]);
        unset($this->workerRestartAttempts[$workerId]);
    }

    /**
     * Worker 进程发生错误时调用
     */
    public function onWorkerError($serv, $workerId, $workerPid, $exitCode, $signal)
    {
        $error = sprintf(
            "Worker 异常退出: workerId=%d, workerPid=%d, exitCode=%d, signal=%d",
            $workerId, $workerPid, $exitCode, $signal
        );
        $this->log($error, 'error');

        // 检查是否需要重启 worker
        if (!$this->isShuttingDown) {
            $this->checkAndRestartWorker($serv, $workerId);
        }
    }

    /**
     * Manager 进程启动时调用
     */
    public function onManagerStart($serv)
    {
        cli_set_process_title('swoole_manager');
        // $this->log("Manager 进程已启动 (PID: {$serv->manager_pid})");
    }

    /**
     * Manager 进程停止时调用
     */
    public function onManagerStop($serv)
    {
        // $this->log("Manager 进程已停止");
    }

    /**
     * 启动监控协程
     */
    private function startMonitor()
    {
        // 使用协程定时器监控 worker 状态
        \Swoole\Timer::tick(10000, function($timerId) {  // 每10秒检查一次
            if ($this->isShuttingDown) {
                return;
            }

            $this->tickWorkerMonitor($timerId);
            $this->checkWorkersStatus();
        });
    }

    /**
     * worker_monitor: status/heartbeat only (no run history). Also runs stale reaper.
     */
    private function tickWorkerMonitor($timerId = null)
    {
        try {
            require_once $this->root_path . '/services/SwooleSchedulerService.php';
            require_once $this->root_path . '/services/BackgroundJobService.php';

            $scheduler = new \SwooleSchedulerService();
            \SwooleSchedulerService::ensureServerInstanceId($this->serverInstanceId);

            if (!$this->workerMonitorRegistered) {
                $scheduler->registerScheduler(\SwooleSchedulerService::SCHEDULER_WORKER_MONITOR, [
                    'serverInstanceId' => $this->serverInstanceId,
                    'timerId' => $timerId,
                    'processId' => getmypid(),
                    'intervalMs' => 10000,
                ]);
                $this->workerMonitorRegistered = true;
            }

            $scheduler->heartbeat(\SwooleSchedulerService::SCHEDULER_WORKER_MONITOR, [
                'timerId' => $timerId,
                'processId' => getmypid(),
                'serverInstanceId' => $this->serverInstanceId,
                'intervalMs' => 10000,
            ]);

            $scheduler->reapStaleSchedulers();
            (new \BackgroundJobService())->reapStaleJobs();
        } catch (\Throwable $e) {
            // Tracking must never break the monitor loop.
            @error_log('[tickWorkerMonitor] ' . $e->getMessage());
        }
    }

    /**
     * 检查 worker 状态并处理异常
     */
    private function checkWorkersStatus()
    {
        if ($this->isShuttingDown) {
            return;
        }

        $currentTime = time();
        $workerNum = $this->serv->setting['worker_num'] ?? 1;

        // 检查 worker 进程
        foreach ($this->workerPids as $workerId => $info) {
            $pid = $info['pid'] ?? 0;

            // 检查进程是否存在
            if ($pid > 0 && !$this->isProcessRunning($pid)) {
                // $this->log("Worker {$workerId} (PID: {$pid}) 已退出，尝试重启...", 'warning');
                $this->checkAndRestartWorker($this->serv, $workerId);
                continue;
            }

            // 检查 worker 是否假死（超过5分钟没有活动）
            $maxIdleTime = 300; // 5分钟
            if (isset($info['last_active_time']) &&
                ($currentTime - $info['last_active_time']) > $maxIdleTime) {
                // $this->log("Worker {$workerId} (PID: {$pid}) 可能假死，尝试重启...", 'warning');
                $this->restartWorker($workerId);
            }
        }
    }

    /**
     * 检查并重启 worker
     */
    private function checkAndRestartWorker($serv, $workerId)
    {
        $currentTime = time();
        $workerInfo = $this->workerPids[$workerId] ?? null;

        if (!$workerInfo) {
            return;
        }

        // 检查重启频率限制
        $lastRestartTime = $workerInfo['last_restart_time'] ?? 0;
        $restartAttempts = $this->workerRestartAttempts[$workerId] ?? 0;

        if (($currentTime - $lastRestartTime) < self::WORKER_RESTART_INTERVAL) {
            $restartAttempts++;
            $this->workerRestartAttempts[$workerId] = $restartAttempts;

            // 超过最大尝试次数，记录错误并停止重启
            if ($restartAttempts >= self::MAX_WORKER_RESTART_ATTEMPTS) {
                $this->log("Worker {$workerId} 在短时间内重启次数过多，停止自动重启", 'error');
                return;
            }
        } else {
            // 重置重启计数
            $this->workerRestartAttempts[$workerId] = 0;
        }

        // 重启 worker
        $this->restartWorker($workerId);
    }

    /**
     * 重启指定 worker
     */
    private function restartWorker($workerId)
    {
        if ($this->isShuttingDown) {
            return;
        }

        try {
            // $this->log("正在重启 Worker {$workerId}...");

            // 更新重启信息
            $this->workerPids[$workerId]['last_restart_time'] = time();
            $this->workerRestartAttempts[$workerId] = ($this->workerRestartAttempts[$workerId] ?? 0) + 1;

            // 发送重启信号
            $this->serv->reload();

        } catch (\Exception $e) {
            $this->log("重启 Worker {$workerId} 失败: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 检查进程是否在运行
     */
    private function isProcessRunning($pid)
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // Windows 系统
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            exec("tasklist /FI \"PID eq {$pid}\"", $output);
            return count($output) > 1;
        }

        // 其他系统
        $output = [];
        exec("ps -p {$pid}", $output);
        return count($output) > 1;
    }

    /**
     * 启动定时任务
     */
    private function startScheduledTasks($serv)
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/SwooleTasks.php';
        require_once __DIR__ . '/RestartThread.php';

        // $this->log("startScheduledTasks 方法被调用");

        require_once $this->root_path . '/services/SwooleSchedulerService.php';
        \SwooleSchedulerService::ensureServerInstanceId($this->serverInstanceId);
        $this->registerBusinessScheduler($serv, null);

        // 先立即执行一次
        $this->executeScheduledTasks($serv);

        // 延迟 1 秒后设置定时器，确保事件循环已完全启动
        \Swoole\Timer::after(1000, function() use ($serv) {
            // $this->log("开始设置 Timer::tick");

            // 每分钟检查一次任务（通知任务和佣金提现）
            $timerId = \Swoole\Timer::tick(60000, function() use ($serv) {
                // $this->log("定时定时开始|");
                $this->executeScheduledTasks($serv);
            });

            if ($timerId === false) {
                $this->log("Timer::tick 设置失败", 'error');
            } else {
                $this->registerBusinessScheduler($serv, $timerId);
            }
            // else {
            //     $this->log("Timer::tick 设置成功，Timer ID: {$timerId}");
            // }

            // 每5秒处理一次佣金计算队列（平衡实时性和资源消耗）
            // TODO: 流程有问题，还在讨论需求，暂时注释掉
            // $commissionTimerId = \Swoole\Timer::tick(5000, function() use ($serv) {
            //     $this->processCommissionCalculationQueue($serv);
            // });

            // if ($commissionTimerId === false) {
            //     $this->log("佣金计算队列定时器设置失败", 'error');
            // }
        });
    }

    /**
     * Register business_scheduler status row (worker 0).
     */
    private function registerBusinessScheduler($serv, $timerId)
    {
        try {
            require_once $this->root_path . '/services/SwooleSchedulerService.php';
            $scheduler = new \SwooleSchedulerService();
            \SwooleSchedulerService::ensureServerInstanceId($this->serverInstanceId);
            $scheduler->registerScheduler(\SwooleSchedulerService::SCHEDULER_BUSINESS, [
                'serverInstanceId' => $this->serverInstanceId,
                'timerId' => $timerId,
                'workerId' => $serv->worker_id,
                'processId' => $serv->worker_pid,
                'intervalMs' => 60000,
            ]);
        } catch (\Throwable $e) {
            @error_log('[registerBusinessScheduler] ' . $e->getMessage());
        }
    }

    /**
     * 执行定时任务
     */
    private function executeScheduledTasks($serv)
    {
        $run = null;
        $discovered = 0;
        $dispatched = 0;
        $failed = 0;

        try {
            // 更新最后活动时间
            if (isset($this->workerPids[$serv->worker_id])) {
                $this->workerPids[$serv->worker_id]['last_active_time'] = time();
            }

            require_once $this->root_path . '/services/SwooleSchedulerService.php';
            require_once $this->root_path . '/services/BackgroundJobService.php';
            $scheduler = new \SwooleSchedulerService();
            $jobs = new \BackgroundJobService();
            \SwooleSchedulerService::ensureServerInstanceId($this->serverInstanceId);

            $run = $scheduler->beginRun(
                \SwooleSchedulerService::SCHEDULER_BUSINESS,
                $this->serverInstanceId
            );
            $runDbId = $run['dbId'] ?? null;

            $thread = new \RestartThread();

            // 处理定时发送通知/邮件任务
            $notificationTasks = $thread->getDueNotificationTasks();
            if (!empty($notificationTasks)) {
                $discovered += count($notificationTasks);
                foreach ($notificationTasks as $task) {
                    $result = $this->dispatchTrackedTask($serv, $jobs, [
                        'type' => 'send_notification',
                        'notification_id' => $task['id'],
                    ], $runDbId, true);
                    if ($result['dispatched']) {
                        $dispatched++;
                    } else {
                        $failed++;
                    }
                }
            }

            // 处理佣金提现（每分钟检查一次）
            // TODO: 流程有问题，还在讨论需求，暂时注释掉
            // $this->processCommissionWithdrawals($serv);

            if ($this->shouldDispatchOrderBalanceSync()) {
                $discovered++;
                $syncResult = $this->syncOrdersAndBalances($serv, $jobs, $runDbId);
                if ($syncResult['dispatched']) {
                    $dispatched++;
                } else {
                    $failed++;
                }
            }

            // PSP deposits are reconciled after each provider payment window.
            // Adapters currently cover 5Pay and X-Link.
            $discovered++;
            $pspResult = $this->reconcilePspDeposits($serv, $jobs, $runDbId);
            if ($pspResult['dispatched']) {
                $dispatched++;
            } else {
                $failed++;
            }

            if ($run && !empty($run['runId'])) {
                $scheduler->finishRun($run['runId'], [
                    'status' => $failed > 0 && $dispatched === 0 ? 'failed' : 'completed',
                    'discoveredCount' => $discovered,
                    'dispatchedCount' => $dispatched,
                    'failedCount' => $failed,
                    'errorMessage' => $failed > 0 && $dispatched === 0
                        ? 'All task dispatches failed'
                        : null,
                ]);
            }

            // 每 5 分钟从 MT5 网关拉一次最新汇率，刷新 currencyExchangeRates
            $this->syncExchangeRates($serv);

        } catch (\Exception $e) {
            $this->log("定时任务执行失败: " . $e->getMessage(), 'error');
            echo ($e->getTraceAsString());
            if ($run && !empty($run['runId'])) {
                try {
                    require_once $this->root_path . '/services/SwooleSchedulerService.php';
                    (new \SwooleSchedulerService())->finishRun($run['runId'], [
                        'status' => 'failed',
                        'discoveredCount' => $discovered,
                        'dispatchedCount' => $dispatched,
                        'failedCount' => $failed,
                        'errorMessage' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * Dispatch a Swoole task and record backgroundJobs row.
     *
     * @return array{dispatched:bool,jobId:?string,taskId:mixed}
     */
    private function dispatchTrackedTask($serv, \BackgroundJobService $jobs, array $payload, $runDbId = null, $system = true)
    {
        $type = (string) ($payload['type'] ?? 'unknown');
        $existingJobId = isset($payload['jobId']) ? trim((string) $payload['jobId']) : '';

        if ($existingJobId !== '') {
            $jobId = $jobs->createQueued([
                'type' => $type,
                'jobId' => $existingJobId,
                'schedulerRunId' => $runDbId,
                'serverInstanceId' => $this->serverInstanceId,
                'requestId' => $payload['requestId'] ?? null,
                'correlationId' => $payload['correlationId'] ?? null,
                'userId' => $payload['userId'] ?? null,
                'userType' => $payload['userType'] ?? ($system ? 'system' : null),
            ]);
        } elseif ($system) {
            $jobId = $jobs->createSystemJob($type, $runDbId, $this->serverInstanceId);
        } else {
            $jobId = $jobs->createFromUserContext($type, $runDbId, [
                'serverInstanceId' => $this->serverInstanceId,
                'requestId' => $payload['requestId'] ?? null,
                'correlationId' => $payload['correlationId'] ?? null,
                'userId' => $payload['userId'] ?? null,
                'userType' => $payload['userType'] ?? null,
            ]);
        }

        if ($jobId) {
            $payload['jobId'] = $jobId;
        }
        $payload['serverInstanceId'] = $this->serverInstanceId;

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            if ($jobId) {
                $jobs->markFailedQueue($jobId, 'Failed to encode task payload');
            }
            return ['dispatched' => false, 'jobId' => $jobId, 'taskId' => false];
        }

        $taskId = $serv->task($encoded . "$$$###");
        if ($taskId === false) {
            if ($jobId) {
                $jobs->markFailedQueue($jobId, 'Swoole task() returned false (queue full or failed)');
            }
            return ['dispatched' => false, 'jobId' => $jobId, 'taskId' => false];
        }

        if ($jobId) {
            $jobs->attachTaskId($jobId, $taskId, $this->serverInstanceId);
        }

        return ['dispatched' => true, 'jobId' => $jobId, 'taskId' => $taskId];
    }

    private function shouldDispatchOrderBalanceSync()
    {
        try {
            require_once $this->root_path . '/services/DeveloperSettingsService.php';
            return (new \DeveloperSettingsService())->shouldDispatchOrderBalanceSync();
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function isMt5SyncEnabled()
    {
        try {
            require_once $this->root_path . '/services/DeveloperSettingsService.php';
            return (new \DeveloperSettingsService())->isMt5SyncEnabled();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * 同步订单和余额
     *
     * @return array{dispatched:bool,jobId:?string,taskId:mixed}
     */
    private function syncOrdersAndBalances($serv, $jobs = null, $runDbId = null)
    {
        try {
            require_once __DIR__ . '/../services/OrderSyncService.php';
            if ($jobs === null) {
                require_once $this->root_path . '/services/BackgroundJobService.php';
                $jobs = new \BackgroundJobService();
            }

            return $this->dispatchTrackedTask($serv, $jobs, [
                'type' => 'sync_orders_balances',
            ], $runDbId, true);

        } catch (\Exception $e) {
            $this->log("同步订单和余额任务投递失败: " . $e->getMessage(), 'error');
            return ['dispatched' => false, 'jobId' => null, 'taskId' => false];
        }
    }

    /**
     * Dispatch shared PSP deposit status reconciliation (5Pay + X-Link).
     *
     * @return array{dispatched:bool,jobId:?string,taskId:mixed}
     */
    private function reconcilePspDeposits($serv, $jobs = null, $runDbId = null)
    {
        try {
            if ($jobs === null) {
                require_once $this->root_path . '/services/BackgroundJobService.php';
                $jobs = new \BackgroundJobService();
            }

            return $this->dispatchTrackedTask($serv, $jobs, [
                'type' => 'reconcile_psp_deposits',
            ], $runDbId, true);
        } catch (\Exception $e) {
            $this->log("PSP deposit reconciliation task dispatch failed: " . $e->getMessage(), 'error');
            return ['dispatched' => false, 'jobId' => null, 'taskId' => false];
        }
    }

    /**
     * 汇率同步：分钟 tick 里按 5 分钟节流，投递异步 task（实际拉取在 onTask 里做）。
     * 定时器只在 worker 0 跑，用实例变量记录上次投递即可节流。
     */
    private function syncExchangeRates($serv)
    {
        if (!$this->isMt5SyncEnabled()) {
            return;
        }
        if (time() - $this->lastExchangeRateSyncAt < 300) {
            return;
        }
        try {
            $serv->task(json_encode([
                'type' => 'sync_exchange_rates'
            ]) . "$$$###");
            $this->lastExchangeRateSyncAt = time();
        } catch (\Exception $e) {
            $this->log("汇率同步任务投递失败: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 处理佣金计算队列
     */
    private function processCommissionCalculationQueue($serv)
    {
        try {
            require_once __DIR__ . '/../services/IbCommissionCalculator.php';
            require_once __DIR__ . '/RestartThread.php';

            $thread = new \RestartThread();
            $pendingOrders = $thread->getPendingCommissionCalculations();

            if (!empty($pendingOrders)) {
                foreach ($pendingOrders as $order) {
                    // 异步处理佣金计算
                    $serv->task(json_encode([
                        'type' => 'calculate_commission',
                        'order_id' => $order['orderId']
                    ]) . "$$$###");
                }
            }
        } catch (\Exception $e) {
            $this->log("处理佣金计算队列失败: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 处理佣金提现
     */
    private function processCommissionWithdrawals($serv)
    {
        try {
            require_once __DIR__ . '/../services/IbCommissionWithdrawal.php';

            $withdrawalService = new \IbCommissionWithdrawal();
            $results = $withdrawalService->processWithdrawals();

            // 记录处理结果（可选）
            if (!empty($results)) {
                foreach ($results as $result) {
                    if ($result['success']) {
                        // $this->log("佣金提现成功: IB {$result['ibCode']}, 金额 {$result['totalAmount']}");
                    } else {
                        $this->log("佣金提现失败: IB {$result['ibCode']}, 原因: {$result['message']}", 'error');
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("处理佣金提现失败: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 错误处理
     */
    public function handleError($errno, $errstr, $errfile = '', $errline = 0)
    {
        $error = sprintf(
            "错误: [%s] %s in %s on line %d",
            $errno,
            $errstr,
            $errfile,
            $errline
        );

        $this->log($error, 'error');

        // 不执行 PHP 内部错误处理
        return true;
    }

    /**
     * 致命错误处理
     */
    public function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorMsg = sprintf(
                "致命错误: [%s] %s in %s on line %d",
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );

            $this->log($errorMsg, 'error');

            // 记录崩溃信息
            file_put_contents(
                $this->root_path . '/runtime/swoole_crash.log',
                date('Y-m-d H:i:s') . " - " . $errorMsg . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * 记录日志
     */
    private function log($message, $level = 'info')
    {
        $log = sprintf(
            "[%s] [%s] %s" . PHP_EOL,
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        // 输出到控制台
        if (php_sapi_name() === 'cli' && !$this->serv->setting['daemonize']) {
            echo $log;
        }

        // 写入日志文件
//        $logDir = $this->root_path . '/myswoole/';
//        if (!is_dir($logDir)) {
//            mkdir($logDir, 0777, true);
//        }
//        $logFile = $this->root_path . '/myswoole/swoole_' . date('Y-m-d') . '.log';
//        file_put_contents($logFile, $log, FILE_APPEND);
    }

    /**
     * 优雅关闭服务
     */
    public function gracefulShutdown()
    {
        if ($this->isShuttingDown) {
            return;
        }

        $this->isShuttingDown = true;
        // $this->log("正在关闭服务...");

        // 停止接收新请求
        $this->serv->stop();

        // 等待所有 worker 进程退出
        $timeout = 30; // 30秒超时
        $startTime = time();

        while (count($this->workerPids) > 0 && (time() - $startTime) < $timeout) {
            usleep(100000); // 100ms
        }

        if (count($this->workerPids) > 0) {
            // $this->log("强制终止 worker 进程...", 'warning');
            foreach ($this->workerPids as $workerId => $info) {
                if (isset($info['pid']) && $info['pid'] > 0) {
                    posix_kill($info['pid'], SIGKILL);
                }
            }
        }

        // $this->log("服务已关闭");
        exit(0);
    }

    /**
     * @param $serv
     * @param $fd
     * @param $from_id
     * @param $data
     * @return void
     */
    public function onReceive($serv, $fd, $from_id, $data)
    {
        // 更新最后活动时间
        if (isset($this->workerPids[$serv->worker_id])) {
            $this->workerPids[$serv->worker_id]['last_active_time'] = time();
        }

        try {
            require_once $this->root_path . '/services/BackgroundJobService.php';
            require_once $this->root_path . '/utils/RequestLogContext.php';

            $raw = explode('$$$###', $data)[0];
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $serv->task($data);
                return;
            }

            \RequestLogContext::initFromTaskPayload($payload);
            $jobs = new \BackgroundJobService();
            $hasUserContext = !empty($payload['requestId'])
                || !empty($payload['userId'])
                || (isset($payload['userType']) && $payload['userType'] !== 'system');

            $result = $this->dispatchTrackedTask(
                $serv,
                $jobs,
                $payload,
                null,
                !$hasUserContext
            );
            if (!$result['dispatched'] && empty($result['jobId'])) {
                // Fallback: still attempt original dispatch if tracking failed entirely.
                $serv->task($data);
            }
        } catch (\Throwable $e) {
            @error_log('[onReceive tracking] ' . $e->getMessage());
            $serv->task($data);
        }
    }

    /**
     * 任务监听事件
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
        // 更新最后活动时间
        if (isset($this->workerPids[$serv->worker_id])) {
            $this->workerPids[$serv->worker_id]['last_active_time'] = time();
        }

        require_once './../vendor/autoload.php';
        require_once './SwooleTasks.php';
        require_once './RestartThread.php';

        $data = explode("$$$###", $data)[0];
        // json数据
        $data = json_decode($data,true);
        if (!is_array($data)) {
            $data = [];
        }

        require_once __DIR__ . '/../utils/RequestLogContext.php';
        require_once __DIR__ . '/../utils/Logger.php';
        require_once __DIR__ . '/../services/BackgroundJobService.php';
        \RequestLogContext::initFromTaskPayload($data);
        if (!empty($data['type'])) {
            $GLOBALS['appLogContext']['jobId'] = $GLOBALS['appLogContext']['jobId']
                ?? ((string) $data['type'] . '-' . (string) $task_id);
        }

        $jobService = new \BackgroundJobService();
        $jobId = isset($data['jobId']) ? trim((string) $data['jobId']) : '';
        if ($jobId === '') {
            $jobId = (string) ($GLOBALS['appLogContext']['jobId'] ?? '');
        }
        if ($jobId !== '') {
            $jobService->markRunning($jobId, (int) $serv->worker_id, (int) $serv->worker_pid);
            $GLOBALS['appLogContext']['jobId'] = $jobId;
        }

        $thread = new \RestartThread();
        try {
        if (isset($data['type']) && $data['type'] == 'send_notification') {
            // 处理定时通知/邮件任务
            echo $thread->processScheduledNotification($data['notification_id']);
            if ($jobId !== '') {
                $jobService->markCompleted($jobId, ['notification_id' => $data['notification_id'] ?? null]);
            }
        } else if (isset($data['type']) && $data['type'] == 'sync_orders_balances') {
            // 处理订单和余额同步任务
            require_once __DIR__ . '/../services/OrderSyncService.php';
            $syncService = new \OrderSyncService();
            $result = $syncService->syncAll();
            if ($jobId !== '') {
                $jobService->heartbeat($jobId, [
                    'progressPercent' => 100,
                    'currentStep' => 'syncAll',
                ]);
            }
            // 仅失败时输出到日志，成功不输出以节省磁盘空间
            if ($result['success'] !== true) {
                \Logger::error('Swoole sync_orders_balances failed', [
                    'result' => $result,
                    'domain' => 'payment',
                ]);
                echo json_encode($result);
                if ($jobId !== '') {
                    $jobService->markFailed($jobId, (string) ($result['message'] ?? 'sync_orders_balances failed'));
                }
            } elseif ($jobId !== '') {
                $jobService->markCompleted($jobId, [
                    'success' => true,
                ]);
            }
        } else if (
            isset($data['type'])
            && in_array((string)$data['type'], ['reconcile_psp_deposits', 'reconcile_fivepay_deposits'], true)
        ) {
            require_once __DIR__ . '/../services/PspDepositReconciliationService.php';
            $reconciliationService = new \PspDepositReconciliationService();
            $result = $reconciliationService->run();
            $taskType = (string)$data['type'];
            if ($jobId !== '') {
                $jobService->heartbeat($jobId, [
                    'progressPercent' => 100,
                    'currentStep' => $taskType,
                    'processed' => (int)($result['processed'] ?? 0),
                    'total' => (int)($result['discovered'] ?? 0),
                ]);
            }
            if (($result['success'] ?? false) !== true) {
                \Logger::error('Swoole ' . $taskType . ' completed with errors', [
                    'result' => $result,
                    'domain' => 'payment',
                ]);
                if ($jobId !== '') {
                    $jobService->markFailed($jobId, 'PSP deposit reconciliation completed with errors');
                }
            } elseif ($jobId !== '') {
                $jobService->markCompleted($jobId, $result);
            }
        } else if (isset($data['type']) && $data['type'] == 'sync_ib_products') {
            $platformKey = isset($data['platformKey']) ? strtolower(trim((string)$data['platformKey'])) : '';
            $adminId = isset($data['adminId']) ? (int)$data['adminId'] : null;
            $logIbSettings = !empty($data['logIbSettings']);
            $syncOk = false;
            if ($platformKey === 'mt5') {
                require_once __DIR__ . '/../services/IbProductSyncService.php';
                $service = new \IbProductSyncService();
                try {
                    $result = $service->syncMt5Products($adminId, $logIbSettings);
                    $syncOk = (($result['success'] ?? false) === true);
                    if (!$syncOk) {
                        echo json_encode($result);
                        if ($jobId !== '') {
                            $jobService->markFailed($jobId, (string) ($result['message'] ?? 'sync_ib_products mt5 failed'));
                        }
                    }
                } catch (\Exception $e) {
                    if ($logIbSettings && $adminId > 0) {
                        require_once __DIR__ . '/../services/OperationLog/IbSettingsOperationLog.php';
                        IbSettingsOperationLog::logSyncFailureForOperator($adminId, 'mt5', $e->getMessage());
                    }
                    // 将错误写入进度文件，让前端能看到失败状态
                    $progressFile = __DIR__ . '/../storage/sync_progress_mt5.json';
                    @file_put_contents($progressFile, json_encode([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'percent' => 0,
                        'updatedAt' => date('Y-m-d H:i:s'),
                    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    echo json_encode([
                        'success' => false,
                        'platformKey' => 'mt5',
                        'message' => $e->getMessage(),
                    ]);
                    if ($jobId !== '') {
                        $jobService->markFailed($jobId, $e->getMessage());
                    }
                }
            } else if ($platformKey === 'mt4') {
                require_once __DIR__ . '/../services/IbProductSyncService.php';
                $service = new \IbProductSyncService();
                try {
                    $result = $service->syncMt4Products($adminId, $logIbSettings);
                    $syncOk = (($result['success'] ?? false) === true);
                    if (!$syncOk) {
                        echo json_encode($result);
                        if ($jobId !== '') {
                            $jobService->markFailed($jobId, (string) ($result['message'] ?? 'sync_ib_products mt4 failed'));
                        }
                    }
                } catch (\Exception $e) {
                    if ($logIbSettings && $adminId > 0) {
                        require_once __DIR__ . '/../services/OperationLog/IbSettingsOperationLog.php';
                        IbSettingsOperationLog::logSyncFailureForOperator($adminId, 'mt4', $e->getMessage());
                    }
                    $progressFile = __DIR__ . '/../storage/sync_progress_mt4.json';
                    @file_put_contents($progressFile, json_encode([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'percent' => 0,
                        'updatedAt' => date('Y-m-d H:i:s'),
                    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
                    echo json_encode([
                        'success' => false,
                        'platformKey' => 'mt4',
                        'message' => $e->getMessage(),
                    ]);
                    if ($jobId !== '') {
                        $jobService->markFailed($jobId, $e->getMessage());
                    }
                }
            }

            if ($jobId !== '' && $syncOk) {
                $jobService->markCompleted($jobId, ['platformKey' => $platformKey]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_commission_report') {
            require_once __DIR__ . '/../services/ClientCommissionReportExportService.php';
            $exportService = new \ClientCommissionReportExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \ClientCommissionReportExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $clientUserId = isset($data['clientUserId']) ? (int)$data['clientUserId'] : 0;
                if ($jobId !== '') {
                    \ClientCommissionReportExportService::writeProgress($jobId, [
                        'clientUserId' => $clientUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($clientUserId > 0) {
                    \ClientCommissionReportExportService::clearActive($clientUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_commission_report',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_deposit_withdraw_report') {
            require_once __DIR__ . '/../services/ClientDepositWithdrawReportExportService.php';
            $exportService = new \ClientDepositWithdrawReportExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \ClientDepositWithdrawReportExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $clientUserId = isset($data['clientUserId']) ? (int)$data['clientUserId'] : 0;
                if ($jobId !== '') {
                    \ClientDepositWithdrawReportExportService::writeProgress($jobId, [
                        'clientUserId' => $clientUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($clientUserId > 0) {
                    \ClientDepositWithdrawReportExportService::clearActive($clientUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_deposit_withdraw_report',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_admin_ib_commission_detail') {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $exportService = new \AdminIbCommissionDetailExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \AdminIbCommissionDetailExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \AdminIbCommissionDetailExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \AdminIbCommissionDetailExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_admin_ib_commission_detail',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_custom_report_widget') {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';
            $exportService = new \CustomReportWidgetExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \CustomReportWidgetExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \CustomReportWidgetExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \CustomReportWidgetExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_custom_report_widget',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_client_ib_statement_report') {
            require_once __DIR__ . '/../services/ClientIbStatementReportExportService.php';
            $exportService = new \ClientIbStatementReportExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \ClientIbStatementReportExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $clientUserId = isset($data['clientUserId']) ? (int)$data['clientUserId'] : 0;
                if ($jobId !== '') {
                    \ClientIbStatementReportExportService::writeProgress($jobId, [
                        'clientUserId' => $clientUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($clientUserId > 0) {
                    \ClientIbStatementReportExportService::clearActive($clientUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_client_ib_statement_report',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_admin_ib_statement_report') {
            require_once __DIR__ . '/../services/IbStatementReportExportService.php';
            $exportService = new \IbStatementReportExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \IbStatementReportExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \IbStatementReportExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \IbStatementReportExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_admin_ib_statement_report',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_admin_funding_report') {
            require_once __DIR__ . '/../services/FundingReportExportService.php';
            $exportService = new \FundingReportExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \FundingReportExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \FundingReportExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \FundingReportExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_admin_funding_report',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_webmcp_client') {
            require_once __DIR__ . '/../services/WebMcpClientExportService.php';
            $exportService = new \WebMcpClientExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \WebMcpClientExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \WebMcpClientExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'exportType' => (string)($data['exportType'] ?? ''),
                        'status' => 'error',
                        'percent' => 0,
                        'processed' => 0,
                        'total' => 0,
                        'message' => 'Export failed',
                        'downloadReady' => false,
                        'file' => null,
                        'fileName' => (string)($data['fileName'] ?? ''),
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \WebMcpClientExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_webmcp_client',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_admin_operation_log_report') {
            require_once __DIR__ . '/../services/OperationLogReportExportService.php';
            $exportService = new \OperationLogReportExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \OperationLogReportExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \OperationLogReportExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \OperationLogReportExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_admin_operation_log_report',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'export_admin_ib_network_clients') {
            require_once __DIR__ . '/../services/AdminIbNetworkClientsExportService.php';
            $exportService = new \AdminIbNetworkClientsExportService();
            try {
                $exportService->run($data);
                $this->finalizeExportBackgroundJob(
                    $jobService,
                    $jobId,
                    \AdminIbNetworkClientsExportService::readProgress($jobId)
                );
            } catch (\Throwable $e) {
                $jobId = isset($data['jobId']) ? (string)$data['jobId'] : '';
                $adminUserId = isset($data['adminUserId']) ? (int)$data['adminUserId'] : 0;
                if ($jobId !== '') {
                    \AdminIbNetworkClientsExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    $jobService->markFailed($jobId, $e->getMessage());
                }
                if ($adminUserId > 0) {
                    \AdminIbNetworkClientsExportService::clearActive($adminUserId);
                }
                echo json_encode([
                    'success' => false,
                    'type' => 'export_admin_ib_network_clients',
                    'message' => $e->getMessage(),
                ]);
            }
        } else if (isset($data['type']) && $data['type'] == 'sync_exchange_rates') {
            require_once __DIR__ . '/../services/ExchangeRateSyncService.php';
            $service = new \ExchangeRateSyncService();
            $result = $service->syncAll();
            if (($result['success'] ?? false) !== true) {
                echo json_encode($result);
            }
        } else if (isset($data['type']) && $data['type'] == 'sync_client_profile') {
            require_once __DIR__ . '/../services/TradingAccountProfileSyncService.php';
            $service = new \TradingAccountProfileSyncService();
            $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : [];
            $result = $service->syncUserProfile($data['userId'] ?? 0, $fields);
            echo json_encode($result);
        } elseif ($jobId !== '') {
            $jobService->markCompleted($jobId, ['type' => $data['type'] ?? null]);
        }
        // TODO: 流程有问题，还在讨论需求，暂时注释掉
        // elseif (isset($data['type']) && $data['type'] == 'calculate_commission') {
        //     // 处理佣金计算任务
        //     echo $thread->processCommissionCalculation($data['order_id']);
        // }
        } catch (\Throwable $e) {
            if ($jobId !== '') {
                $jobService->markFailed($jobId, $e->getMessage());
            }
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map export progress file status onto backgroundJobs (cancelled vs completed vs failed).
     *
     * @param \BackgroundJobService $jobService
     * @param string $jobId
     * @param array|null $progress
     */
    private function finalizeExportBackgroundJob($jobService, $jobId, $progress)
    {
        $jobId = trim((string) $jobId);
        if ($jobId === '' || !($jobService instanceof \BackgroundJobService)) {
            return;
        }

        $status = is_array($progress) ? (string) ($progress['status'] ?? '') : '';
        if ($status === 'cancelled' || $status === 'cancelling') {
            $jobService->markCancelled(
                $jobId,
                (string) ($progress['message'] ?? 'Export cancelled'),
                [
                    'progressPercent' => is_array($progress) ? (int) ($progress['percent'] ?? 0) : 0,
                    'processed' => is_array($progress) ? (int) ($progress['processed'] ?? 0) : 0,
                    'total' => is_array($progress) ? (int) ($progress['total'] ?? 0) : 0,
                ]
            );
            return;
        }
        if ($status === 'error') {
            $jobService->markFailed(
                $jobId,
                (string) ($progress['message'] ?? 'Export failed')
            );
            return;
        }

        $jobService->markCompleted($jobId, [
            'status' => $status !== '' ? $status : 'done',
            'percent' => is_array($progress) ? ($progress['percent'] ?? 100) : 100,
            'processed' => is_array($progress) ? (int) ($progress['processed'] ?? 0) : 0,
            'total' => is_array($progress) ? (int) ($progress['total'] ?? 0) : 0,
        ]);
    }

    /**
     * optional
     * finish 操作是可选的，也可以不返回任何结果，如果在 onTask 事件中通过 return 返回结果时，相当于调用 Swoole\Server::finish() 操作。
     *
     * @param $serv
     * @param $task_id
     * @param $data
     * @return void
     */
    public function onFinish($serv, $task_id, $data)
    {
        // 更新最后活动时间
        if (isset($this->workerPids[$serv->worker_id])) {
            $this->workerPids[$serv->worker_id]['last_active_time'] = time();
        }
        echo $data."\r\n";
        // var_dump($data);
    }
}
