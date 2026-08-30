<?php
/**
 * Auto Approval Controller
 * 负责自动审批规则管理和检查
 */

require_once __DIR__ . '/../models/AutoApprovalRule.php';
require_once __DIR__ . '/../models/AutoApprovalLog.php';
require_once __DIR__ . '/../models/Country.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../services/OperationLog/TransactionSettingsOperationLog.php';

class AutoApprovalController {
    private $ruleModel;
    private $logModel;
    private $countryModel;
    private $depositModel;
    private $withdrawalModel;
    private $userModel;

    public function __construct() {
        $this->ruleModel = new AutoApprovalRule();
        $this->logModel = new AutoApprovalLog();
        $this->countryModel = new Country();
        $this->depositModel = new Deposit();
        $this->withdrawalModel = new Withdrawal();
        $this->userModel = new ClientUser();
    }

    /**
     * 获取自动审批规则列表
     * GET /api/transaction-settings/auto-approval-rules
     */
    public function index() {
        try {
            $ruleType = $_GET['rule_type'] ?? null;

            $rules = $this->ruleModel->getAllRulesStats($ruleType);

            // 格式化规则数据
            foreach ($rules as &$rule) {
                if ($rule['allowedCountries']) {
                    $rule['allowedCountries'] = json_decode($rule['allowedCountries'], true);
                }
                if ($rule['excludedCountries']) {
                    $rule['excludedCountries'] = json_decode($rule['excludedCountries'], true);
                }
                if ($rule['allowedPaymentMethods']) {
                    $rule['allowedPaymentMethods'] = json_decode($rule['allowedPaymentMethods'], true);
                }
            }

            Response::success(['data' => $rules]);
        } catch (Exception $e) {
            // Logger::error('Failed to get auto approval rules', ['error' => $e->getMessage()]);
            Response::error('Failed to load auto approval rules', 500);
        }
    }

    /**
     * 获取单个规则详情
     * GET /api/transaction-settings/auto-approval-rules/{id}
     */
    public function show($id) {
        try {
            $rule = $this->ruleModel->getRuleDetails($id);

            if (!$rule) {
                Response::notFound('Auto approval rule not found');
                return;
            }

            // 获取规则统计
            $stats = $this->ruleModel->getRuleStats($id);
            $rule['stats'] = $stats;

            Response::success(['data' => $rule]);
        } catch (Exception $e) {
            // Logger::error('Failed to get auto approval rule', [
            //     'rule_id' => $id,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to load auto approval rule', 500);
        }
    }

    /**
     * 创建新的自动审批规则
     * POST /api/transaction-settings/auto-approval-rules
     */
    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['ruleType']) || !isset($data['ruleName'])) {
                Response::badRequest('Rule type and name are required');
                return;
            }

            // 获取管理员ID
            $payload = JWT::getPayload();
            $data['updatedBy'] = $payload['userId'] ?? null;

            $ruleId = $this->ruleModel->createRule($data);

            if (!$ruleId) {
                Response::error('Failed to create auto approval rule', 500);
                return;
            }

            Response::success([
                'message' => 'Auto approval rule created successfully',
                'data' => ['id' => $ruleId]
            ], 201);
        } catch (Exception $e) {
            // Logger::error('Failed to create auto approval rule', ['error' => $e->getMessage()]);
            Response::error('Failed to create auto approval rule', 500);
        }
    }

    /**
     * 更新自动审批规则
     * PUT /api/transaction-settings/auto-approval-rules/{id}
     */
    public function update($id) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            // 检查规则是否存在
            $existingRule = $this->ruleModel->findById($id);
            if (!$existingRule) {
                Response::notFound('Auto approval rule not found');
                return;
            }

            // 获取管理员ID
            $payload = JWT::getPayload();
            $data['updatedBy'] = $payload['userId'] ?? null;

            $result = $this->ruleModel->updateRule($id, $data);

            if (!$result) {
                Response::error('Failed to update auto approval rule', 500);
                return;
            }

            Response::success([
                'message' => 'Auto approval rule updated successfully',
                'data' => $this->ruleModel->getRuleDetails($id)
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to update auto approval rule', [
            //     'rule_id' => $id,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to update auto approval rule', 500);
        }
    }

    /**
     * 批量更新规则（用于前端保存）
     * PUT /api/transaction-settings/auto-approval-rules
     *
     * Body: {
     *   "deposit": { enabled, minAmount, maxAmount, ... },
     *   "withdrawal": { enabled, minAmount, maxAmount, ... }
     * }
     */
    public function batchUpdate() {
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            if (!$input) {
                TransactionSettingsOperationLog::logFailure(
                    [],
                    'edit',
                    'transactionSettingsAutoApprovalFailure',
                    'Invalid request data'
                );
                Response::badRequest('Invalid request data');
                return;
            }

            // 获取管理员ID
            $payload = JWT::getPayload();
            $adminId = $payload['userId'] ?? null;

            $successCount = 0;
            $failedCount = 0;
            $linesZh = [];
            $linesEn = [];

            // 更新deposit规则（ID 1）
            if (isset($input['deposit'])) {
                $existing = $this->ruleModel->getRuleDetails(1) ?: [];
                list($partZh, $partEn) = TransactionSettingsOperationLog::detectAutoApprovalRuleChanges(
                    'deposit',
                    $existing,
                    $input['deposit']
                );
                $linesZh = array_merge($linesZh, $partZh);
                $linesEn = array_merge($linesEn, $partEn);

                $depositData = $input['deposit'];
                $depositData['updatedBy'] = $adminId;
                if ($this->ruleModel->updateRule(1, $depositData)) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            // 更新withdrawal规则（ID 2）
            if (isset($input['withdrawal'])) {
                $existing = $this->ruleModel->getRuleDetails(2) ?: [];
                list($partZh, $partEn) = TransactionSettingsOperationLog::detectAutoApprovalRuleChanges(
                    'withdrawal',
                    $existing,
                    $input['withdrawal']
                );
                $linesZh = array_merge($linesZh, $partZh);
                $linesEn = array_merge($linesEn, $partEn);

                $withdrawalData = $input['withdrawal'];
                $withdrawalData['updatedBy'] = $adminId;
                if ($this->ruleModel->updateRule(2, $withdrawalData)) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            // 更新internal transfer规则（ID 3）
            $internalTransferPayload = $input['internal_transfer'] ?? ($input['internalTransfer'] ?? null);
            if (is_array($internalTransferPayload)) {
                $existing = $this->ruleModel->getRuleDetails(3) ?: [];
                list($partZh, $partEn) = TransactionSettingsOperationLog::detectAutoApprovalRuleChanges(
                    'internal_transfer',
                    $existing,
                    $internalTransferPayload
                );
                $linesZh = array_merge($linesZh, $partZh);
                $linesEn = array_merge($linesEn, $partEn);

                $internalTransferPayload['updatedBy'] = $adminId;
                if ($this->ruleModel->updateRule(3, $internalTransferPayload)) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            if ($failedCount > 0) {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsAutoApprovalFailure',
                    'Failed to update auto approval rules'
                );
                Response::error('Failed to update auto approval rules', 500);
                return;
            }

            TransactionSettingsOperationLog::logAutoApprovalBatch($input, $linesZh, $linesEn);

            Response::success([
                'message' => 'Auto approval rules updated successfully',
                'data' => [
                    'success' => $successCount,
                    'failed' => $failedCount
                ]
            ]);
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                is_array($input) ? $input : [],
                'edit',
                'transactionSettingsAutoApprovalFailure',
                'Failed to update auto approval rules: ' . $e->getMessage()
            );
            Response::error('Failed to update auto approval rules', 500);
        }
    }

    /**
     * 删除规则
     * DELETE /api/transaction-settings/auto-approval-rules/{id}
     */
    public function delete($id) {
        try {
            // 不允许删除默认规则（ID 1和2）
            if ($id == 1 || $id == 2) {
                Response::error('Cannot delete default rules', 400);
                return;
            }

            $result = $this->ruleModel->delete(['id' => $id]);

            if (!$result) {
                Response::error('Failed to delete auto approval rule', 500);
                return;
            }

            $payload = JWT::getPayload();
            $adminId = $payload['userId'] ?? null;

            Response::success(['message' => 'Auto approval rule deleted successfully']);
        } catch (Exception $e) {
            // Logger::error('Failed to delete auto approval rule', [
            //     'rule_id' => $id,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to delete auto approval rule', 500);
        }
    }

    /**
     * 启用/禁用规则
     * POST /api/transaction-settings/auto-approval-rules/{id}/toggle
     */
    public function toggle($id) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $enabled = $data['enabled'] ?? null;

            if ($enabled === null) {
                Response::badRequest('enabled field is required');
                return;
            }

            $result = $this->ruleModel->toggleRule($id, $enabled);

            if (!$result) {
                Response::error('Failed to toggle auto approval rule', 500);
                return;
            }

            $payload = JWT::getPayload();
            $adminId = $payload['userId'] ?? null;

            Response::success([
                'message' => 'Auto approval rule ' . ($enabled ? 'enabled' : 'disabled') . ' successfully'
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to toggle auto approval rule', [
            //     'rule_id' => $id,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to toggle auto approval rule', 500);
        }
    }

    /**
     * 检查交易是否满足自动审批条件
     * POST /api/transaction-settings/check-auto-approval
     *
     * Body: {
     *   "type": "deposit|withdrawal",
     *   "transactionId": 123
     * }
     */
    public function checkTransaction() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['type']) || !isset($data['transactionId'])) {
                Response::badRequest('Transaction type and ID are required');
                return;
            }

            $type = $data['type'];
            $transactionId = $data['transactionId'];

            // 获取交易详情
            if ($type === 'deposit') {
                $transaction = $this->depositModel->getDepositDetails($transactionId);
            } else {
                $transaction = $this->withdrawalModel->getWithdrawalDetails($transactionId);
            }

            if (!$transaction) {
                Response::notFound('Transaction not found');
                return;
            }

            // 执行自动审批检查
            $checkResult = $this->performAutoApprovalCheck($type, $transaction);

            Response::success(['data' => $checkResult]);
        } catch (Exception $e) {
            // Logger::error('Failed to check auto approval', ['error' => $e->getMessage()]);
            Response::error('Failed to check auto approval', 500);
        }
    }

    /**
     * 执行自动审批检查逻辑
     * @param string $type
     * @param array $transaction
     * @return array
     */
    private function performAutoApprovalCheck($type, $transaction) {
        $rules = $this->ruleModel->getEnabledRules($type);

        $checkResults = [
            'eligible' => false,
            'appliedRule' => null,
            'checks' => []
        ];

        // 获取客户信息
        $user = $this->userModel->findById($transaction['userId']);

        foreach ($rules as $rule) {
            $ruleDetails = $this->ruleModel->getRuleDetails($rule['id']);
            $passed = true;
            $reasons = [];

            // 检查金额范围
            if ($ruleDetails['minAmount'] && $transaction['amount'] < $ruleDetails['minAmount']) {
                $passed = false;
                $reasons[] = "Amount below minimum ({$ruleDetails['minAmount']})";
            }
            if ($ruleDetails['maxAmount'] && $transaction['amount'] > $ruleDetails['maxAmount']) {
                $passed = false;
                $reasons[] = "Amount above maximum ({$ruleDetails['maxAmount']})";
            }

            // 检查国家限制
            if ($ruleDetails['allowedCountries'] && !in_array('ALL', $ruleDetails['allowedCountries'])) {
                $userCountry = $user['country'] ?? null;
                if (!$userCountry || !in_array($userCountry, $ruleDetails['allowedCountries'])) {
                    $passed = false;
                    $reasons[] = "Country not in allowed list";
                }
            }

            // 检查KYC状态
            if ($ruleDetails['requireKycVerified'] && (!isset($user['kycStatus']) || $user['kycStatus'] !== 'verified')) {
                $passed = false;
                $reasons[] = "KYC not verified";
            }

            // 如果所有检查通过
            if ($passed) {
                $checkResults['eligible'] = true;
                $checkResults['appliedRule'] = $ruleDetails;
                break;
            }

            $checkResults['checks'][] = [
                'ruleId' => $rule['id'],
                'ruleName' => $rule['ruleName'],
                'passed' => $passed,
                'reasons' => $reasons
            ];
        }

        // 记录检查结果到日志
        $this->logModel->logCheck([
            'transactionType' => $type,
            'transactionId' => $transaction['id'],
            'transactionRefId' => $transaction['transactionId'] ?? null,
            'userId' => $transaction['userId'],
            'ruleId' => $checkResults['appliedRule']['id'] ?? null,
            'wasAutoApproved' => $checkResults['eligible'],
            'checkResults' => $checkResults['checks'],
            'rejectionReason' => !$checkResults['eligible'] ? 'No matching rule found' : null,
            'amount' => $transaction['amount'],
            'clientCountry' => $user['country'] ?? null,
            'kycStatus' => $user['kycStatus'] ?? null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        return $checkResults;
    }

    /**
     * 获取审批统计
     * GET /api/transaction-settings/auto-approval-stats
     */
    public function stats() {
        try {
            $type = $_GET['type'] ?? null;
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            $stats = $this->logModel->getApprovalStats($type, $dateFrom, $dateTo);
            $rejections = $this->logModel->getRejectionReasons($type);
            $countries = $this->logModel->getCountryDistribution($type);

            Response::success([
                'data' => [
                    'stats' => $stats,
                    'rejectionReasons' => $rejections,
                    'countryDistribution' => $countries
                ]
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to get auto approval stats', ['error' => $e->getMessage()]);
            Response::error('Failed to load auto approval stats', 500);
        }
    }
}
