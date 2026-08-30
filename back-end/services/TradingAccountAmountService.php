<?php
/**
 * Trading Account Amount Service
 * 统一处理交易账户与 CRM 基准金额之间的 scale 换算。
 */

require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/TradingGroup.php';

class TradingAccountAmountService {
    private $tradingAccountModel;
    private $externalAccountModel;
    private $platformModel;
    private $tradingGroupModel;

    public function __construct() {
        $this->tradingAccountModel = new TradingAccount();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->platformModel = new TradingPlatform();
        $this->tradingGroupModel = new TradingGroup();
    }

    public function getAccountContext($tradingAccountId): array {
        $tradingAccountId = (int)$tradingAccountId;
        if ($tradingAccountId <= 0) {
            throw new InvalidArgumentException('Trading account id is required');
        }

        $tradingAccount = $this->tradingAccountModel->findById($tradingAccountId);
        if (!$tradingAccount || empty($tradingAccount['platformId'])) {
            throw new RuntimeException('Trading account platform is missing');
        }

        $platform = $this->platformModel->findById((int)$tradingAccount['platformId']);
        if (!$platform || empty($platform['platformKey'])) {
            throw new RuntimeException('Trading platform not found');
        }

        $externalAccount = $this->externalAccountModel->findByTradingAccount($tradingAccountId);
        if (!$externalAccount || empty($externalAccount['providerAccountId'])) {
            throw new RuntimeException('Provider account ID is missing');
        }

        $platformKey = (string)$platform['platformKey'];
        $group = null;
        $scale = 1.0;
        $unit = null;

        if (isset($externalAccount['groupId']) && $externalAccount['groupId'] !== null && $externalAccount['groupId'] !== '') {
            if ($platformKey === 'mt5') {
                $group = $this->tradingGroupModel->findByPlatformAndId($platformKey, (int)$externalAccount['groupId']);
            } else {
                $group = $this->tradingGroupModel->findByTradingId((int)$externalAccount['groupId'], $platformKey);
            }
        }

        if ($group) {
            $scale = $this->normalizeScale($group['scale'] ?? 1);
            $unit = $this->normalizeUnit($group['unit'] ?? null);
        }

        return [
            'tradingAccountId' => $tradingAccountId,
            'tradingAccount' => $tradingAccount,
            'platform' => $platform,
            'externalAccount' => $externalAccount,
            'platformKey' => $platformKey,
            'providerAccountId' => (string)$externalAccount['providerAccountId'],
            'scale' => $scale,
            'unit' => $unit,
            'group' => $group,
        ];
    }

    public function convertBaseToPlatformAmount($baseAmount, array $context): float {
        return round((float)$baseAmount * $this->normalizeScale($context['scale'] ?? 1), 8);
    }

    public function convertPlatformToBaseAmount($platformAmount, array $context): float {
        return round((float)$platformAmount / $this->normalizeScale($context['scale'] ?? 1), 8);
    }

    private function normalizeScale($value): float {
        if (!is_numeric($value) || (float)$value <= 0) {
            return 1.0;
        }

        return (float)$value;
    }

    private function normalizeUnit($value) {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }
}
