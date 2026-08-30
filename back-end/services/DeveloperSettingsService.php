<?php

require_once __DIR__ . '/../models/AdminSystemSetting.php';

class DeveloperSettingsService {
    public const KEY_MT4_SYNC = 'developerMt4SyncEnabled';
    public const KEY_MT5_SYNC = 'developerMt5SyncEnabled';
    public const KEY_EMAIL_SENDING = 'developerEmailSendingEnabled';

    private const API_TO_SETTING_KEY = [
        'mt4Sync' => self::KEY_MT4_SYNC,
        'mt5Sync' => self::KEY_MT5_SYNC,
        'emailSending' => self::KEY_EMAIL_SENDING,
    ];

    private $settingModel;
    private $environment;

    public function __construct() {
        $this->settingModel = new AdminSystemSetting();
        $config = require __DIR__ . '/../config/app.php';
        $this->environment = strtolower(trim((string)($config['env'] ?? 'dev')));
    }

    public function getEnvironment() {
        return $this->environment;
    }

    public function isNonProduction() {
        return in_array($this->environment, ['dev', 'staging'], true);
    }

    public function isMt4SyncEnabled() {
        return $this->isFeatureEnabled(self::KEY_MT4_SYNC);
    }

    public function isMt5SyncEnabled() {
        return $this->isFeatureEnabled(self::KEY_MT5_SYNC);
    }

    public function isEmailSendingEnabled() {
        return $this->isFeatureEnabled(self::KEY_EMAIL_SENDING);
    }

    public function shouldDispatchOrderBalanceSync() {
        return $this->isMt4SyncEnabled() || $this->isMt5SyncEnabled();
    }

    public function getAll() {
        return [
            'environment' => $this->environment,
            'mt4Sync' => $this->isMt4SyncEnabled(),
            'mt5Sync' => $this->isMt5SyncEnabled(),
            'emailSending' => $this->isEmailSendingEnabled(),
        ];
    }

    public function updateFlags(array $flags, $updatedBy = null) {
        foreach ($flags as $apiKey => $value) {
            if (!isset(self::API_TO_SETTING_KEY[$apiKey])) {
                continue;
            }
            $this->upsertFlag(self::API_TO_SETTING_KEY[$apiKey], $this->toBoolean($value), $updatedBy);
        }
        return $this->getAll();
    }

    private function isFeatureEnabled($settingKey) {
        if (!$this->isNonProduction()) {
            return true;
        }
        return (bool)$this->settingModel->getValue($settingKey, false);
    }

    private function toBoolean($value) {
        if (is_bool($value)) {
            return $value;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed !== null) {
            return $parsed;
        }
        return (bool)$value;
    }

    private function upsertFlag($settingKey, $enabled, $updatedBy = null) {
        $stored = $enabled ? '1' : '0';
        $existing = $this->settingModel->findOne(['settingKey' => $settingKey]);
        if ($existing) {
            $this->settingModel->setValue($settingKey, $stored, $updatedBy);
            return;
        }

        $displayNames = [
            self::KEY_MT4_SYNC => 'MT4 Sync',
            self::KEY_MT5_SYNC => 'MT5 Sync',
            self::KEY_EMAIL_SENDING => 'Email sending',
        ];

        $this->settingModel->create([
            'settingKey' => $settingKey,
            'settingValue' => $stored,
            'settingType' => 'boolean',
            'category' => 'developer',
            'displayName' => $displayNames[$settingKey] ?? $settingKey,
            'description' => '',
            'isPublic' => 0,
            'isEditable' => 1,
            'sortOrder' => 0,
            'updatedBy' => $updatedBy,
        ]);
    }
}
