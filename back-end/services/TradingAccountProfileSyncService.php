<?php
/**
 * TradingAccountProfileSyncService
 *
 * 把客户资料(name / phone / country)同步到其名下的 MT4 / MT5 交易账户。
 * 由 swoole task(sync_client_profile) 异步触发——best-effort：每个账户独立处理，
 * 失败只记日志、继续下一个，不影响客户在 CRM 侧改资料本身。
 *
 * 注意：FinancePro 不在资料同步范围内——改 firstname/lastname/email/phone 都不会同步到 FP，
 * 只同步 MT4/MT5。FP 的 group / leverage 变更是独立流程（各自走 EditAccount），不在这里。
 */

require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../utils/Logger.php';

class TradingAccountProfileSyncService
{
    private $externalAccountModel;
    private $mt5ApiClient;
    private $mt4ApiClient;

    public function __construct()
    {
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->mt5ApiClient = new Mt5ApiClient();
        $this->mt4ApiClient = new Mt4ApiClient();
    }

    /**
     * 把资料变更投递给 swoole，异步同步到交易平台。
     * 整个投递 try/catch(\Throwable) 包住——swoole 不可用 / 投递失败都只记日志，绝不影响调用方主流程。
     *
     * @param int $userId
     * @param array $fields { name?, phone?, country?, email? }（只带需要同步的字段）
     */
    public static function dispatch($userId, array $fields)
    {
        if (empty($fields)) {
            return;
        }
        try {
            // 直接用普通 TCP socket 投递到 swoole 服务。不走 \myswoole\SwooleClient：
            // 1) 避免依赖 web 端（PHP-FPM）是否加载了 swoole 扩展（swoole 通常只在跑服务的 CLI 里有，
            //    FPM 里 new \Swoole\Client 会直接抛异常，任务发不出去）；
            // 2) 原 SwooleClient 用 "\r\n" 结尾是错的——swoole 服务按 package_eof="$$$###" 分包，
            //    收不到该 EOF 就永远凑不齐一个包、onReceive 不触发。这里对齐可用的 IbSettings/PaymentSettlement 写法。
            $payload = json_encode([
                'type'   => 'sync_client_profile',
                'userId' => (int)$userId,
                'fields' => $fields,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $errno = 0;
            $errstr = '';
            $sock = @stream_socket_client(config_swoole_address(), $errno, $errstr, 1.0);
            if ($sock === false) {
                error_log("dispatch sync_client_profile connect failed: [$errno] $errstr");
                return;
            }
            @fwrite($sock, $payload . '$$$###');
            fclose($sock);
        } catch (\Throwable $e) {
            error_log('dispatch sync_client_profile failed: ' . $e->getMessage());
        }
    }

    /**
     * 同步一个客户的资料到其 MT4 / MT5 交易账户（逐账户更新）。FP 不同步。
     *
     * @param int $userId
     * @param array $fields { name, phone, country }（只发有值的字段）
     * @return array
     */
    public function syncUserProfile($userId, array $fields)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return ['success' => false, 'message' => 'invalid userId'];
        }

        $name    = isset($fields['name']) ? trim((string)$fields['name']) : '';
        $phone   = isset($fields['phone']) ? trim((string)$fields['phone']) : null;
        $country = isset($fields['country']) ? trim((string)$fields['country']) : null;
        $email   = isset($fields['email']) ? trim((string)$fields['email']) : null;

        $accounts = $this->externalAccountModel->findAllByUserIdWithPlatform($userId);
        $results = [];

        // 只同步 MT4 / MT5，逐账户更新；FinancePro 账户跳过（资料不同步到 FP）
        foreach ($accounts as $acc) {
            $platformKey = (string)($acc['platformKey'] ?? $acc['providerKey'] ?? '');
            $login = (string)($acc['providerAccountId'] ?? '');
            if ($login === '' || !in_array($platformKey, ['mt5', 'mt4'], true)) {
                continue;
            }

            try {
                $this->syncOne($platformKey, $login, $name, $phone, $country, $email);
                $results[] = ['login' => $login, 'platform' => $platformKey, 'ok' => true];
            } catch (Exception $e) {
                // best-effort：失败只记日志，继续同步下一个账户
                Logger::error('Profile sync to platform failed', [
                    'userId'   => $userId,
                    'login'    => $login,
                    'platform' => $platformKey,
                    'error'    => $e->getMessage(),
                ]);
                $results[] = ['login' => $login, 'platform' => $platformKey, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => true, 'userId' => $userId, 'results' => $results];
    }

    /**
     * 把资料推到单个 MT4 / MT5 账户。login 统一用 providerAccountId；updateUser 是局部更新。
     */
    private function syncOne($platformKey, $login, $name, $phone, $country, $email = null)
    {
        // 只发有值的字段
        $patch = [];
        if ($name !== '')                     $patch['name'] = $name;
        if ($phone !== null)                  $patch['phone'] = $phone;
        if ($country !== null)                $patch['country'] = $country;
        if ($email !== null && $email !== '') $patch['email'] = $email;
        if (empty($patch)) {
            return;
        }

        if ($platformKey === 'mt5') {
            try {
                $this->mt5ApiClient->updateUser($login, $patch);
            } finally {
                $this->mt5ApiClient->disconnect();
            }
        } elseif ($platformKey === 'mt4') {
            $this->mt4ApiClient->updateUser($login, $patch);
        }
    }
}
