<?php
/**
 * Client Payment Account Controller
 * 负责客户端支付账户管理相关接口
 */

require_once __DIR__ . '/../models/ClientPaymentAccount.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';

class ClientPaymentAccountController {
    private $accountModel;

    public function __construct() {
        $this->accountModel = new ClientPaymentAccount();
    }

    /**
     * 获取当前客户端用户 ID（预览或 JWT）
     */
    private function getCurrentClientUserId() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $userId;
        }
        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'client') {
            return null;
        }
        return $payload['userId'] ?? null;
    }

    /**
     * 获取当前用户的支付账户列表
     * GET /api/client/payment-accounts
     */
    public function index() {
        try {
            $userId = $this->getCurrentClientUserId();
            if (!$userId) {
                Response::unauthorized('Client authentication required');
            }

            $accounts = $this->accountModel->getByUserId($userId);

            Response::success($accounts);

        } catch (Exception $e) {
            Logger::error('Failed to get payment accounts', [
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to get payment accounts: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 创建支付账户
     * POST /api/client/payment-accounts
     */
    public function create() {
        try {
            $userId = $this->getCurrentClientUserId();
            if (!$userId) {
                Response::unauthorized('Client authentication required');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // 验证必填字段
            if (empty($data['legalName'])) {
                Response::error('Legal Name is required', 400);
            }

            if (empty($data['bsb'])) {
                Response::error('BSB is required', 400);
            }

            if (empty($data['accountNumber'])) {
                Response::error('Account Number is required', 400);
            }

            // 验证 BSB 格式（6位数字）
            if (!ClientPaymentAccount::validateBSB($data['bsb'])) {
                Response::error('BSB must be 6 digits', 400);
            }

            // 验证账户号码格式
            if (!ClientPaymentAccount::validateAccountNumber($data['accountNumber'])) {
                Response::error('Account Number must be 6-50 digits', 400);
            }

            // 验证 Legal Name 长度
            if (strlen($data['legalName']) > 255) {
                Response::error('Legal Name must not exceed 255 characters', 400);
            }

            // 检查是否已存在相同的账户（相同用户、BSB 和账户号码）
            $existing = $this->accountModel->findAll([
                'userId' => $userId,
                'bsb' => $data['bsb'],
                'accountNumber' => $data['accountNumber']
            ]);

            if (!empty($existing)) {
                Response::error('This payment account already exists', 400);
            }

            // 创建账户
            $accountData = [
                'userId' => $userId,
                'legalName' => trim($data['legalName']),
                'bsb' => trim($data['bsb']),
                'accountNumber' => trim($data['accountNumber']),
                'isDefault' => !empty($data['isDefault']) ? 1 : 0
            ];

            $accountId = $this->accountModel->createAccount($accountData);

            if (!$accountId) {
                Response::error('Failed to create payment account', 500);
            }

            $account = $this->accountModel->findById($accountId);
            Response::created($account, 'Payment account created successfully');

        } catch (Exception $e) {
            Logger::error('Failed to create payment account', [
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to create payment account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新支付账户
     * PUT /api/client/payment-accounts/{id}
     */
    public function update($id) {
        try {
            $userId = $this->getCurrentClientUserId();
            if (!$userId) {
                Response::unauthorized('Client authentication required');
            }

            // 检查账户是否存在且属于当前用户
            $account = $this->accountModel->findById($id);
            if (!$account || $account['userId'] != $userId) {
                Response::notFound('Payment account not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // 验证字段
            if (isset($data['legalName'])) {
                if (empty($data['legalName'])) {
                    Response::error('Legal Name cannot be empty', 400);
                }
                if (strlen($data['legalName']) > 255) {
                    Response::error('Legal Name must not exceed 255 characters', 400);
                }
            }

            if (isset($data['bsb'])) {
                if (empty($data['bsb'])) {
                    Response::error('BSB cannot be empty', 400);
                }
                if (!ClientPaymentAccount::validateBSB($data['bsb'])) {
                    Response::error('BSB must be 6 digits', 400);
                }
            }

            if (isset($data['accountNumber'])) {
                if (empty($data['accountNumber'])) {
                    Response::error('Account Number cannot be empty', 400);
                }
                if (!ClientPaymentAccount::validateAccountNumber($data['accountNumber'])) {
                    Response::error('Account Number must be 6-50 digits', 400);
                }
            }

            // 如果修改了 BSB 或账户号码，检查是否与其他账户重复
            if (isset($data['bsb']) || isset($data['accountNumber'])) {
                $checkBSB = $data['bsb'] ?? $account['bsb'];
                $checkAccountNumber = $data['accountNumber'] ?? $account['accountNumber'];

                $existing = $this->accountModel->findAll([
                    'userId' => $userId,
                    'bsb' => $checkBSB,
                    'accountNumber' => $checkAccountNumber
                ]);

                // 排除当前账户
                $existing = array_values(array_filter($existing, function($item) use ($id) {
                    return $item['id'] != $id;
                }));

                if (!empty($existing)) {
                    Response::error('This payment account already exists', 400);
                }
            }

            // 更新账户
            $updateData = [];
            if (isset($data['legalName'])) {
                $updateData['legalName'] = trim($data['legalName']);
            }
            if (isset($data['bsb'])) {
                $updateData['bsb'] = trim($data['bsb']);
            }
            if (isset($data['accountNumber'])) {
                $updateData['accountNumber'] = trim($data['accountNumber']);
            }
            if (isset($data['isDefault'])) {
                $updateData['isDefault'] = !empty($data['isDefault']) ? 1 : 0;
            }

            if (empty($updateData)) {
                Response::error('No fields to update', 400);
            }

            $this->accountModel->updateAccount($id, $updateData);

            $updatedAccount = $this->accountModel->findById($id);
            Response::success($updatedAccount, 'Payment account updated successfully');

        } catch (Exception $e) {
            Logger::error('Failed to update payment account', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to update payment account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除支付账户
     * DELETE /api/client/payment-accounts/{id}
     */
    public function delete($id) {
        try {
            $userId = $this->getCurrentClientUserId();
            if (!$userId) {
                Response::unauthorized('Client authentication required');
            }

            // 检查账户是否存在且属于当前用户
            $account = $this->accountModel->findById($id);
            if (!$account || $account['userId'] != $userId) {
                Response::notFound('Payment account not found');
            }

            // 软删除账户（设置 deletedAt，不物理删除）
            $this->accountModel->softDelete($id);

            Response::success(null, 'Payment account deleted successfully');

        } catch (Exception $e) {
            Logger::error('Failed to delete payment account', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to delete payment account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 设置默认账户
     * POST /api/client/payment-accounts/{id}/set-default
     */
    public function setDefault($id) {
        try {
            $userId = $this->getCurrentClientUserId();
            if (!$userId) {
                Response::unauthorized('Client authentication required');
            }

            // 检查账户是否存在且属于当前用户
            $account = $this->accountModel->findById($id);
            if (!$account || $account['userId'] != $userId) {
                Response::notFound('Payment account not found');
            }

            // 设置默认账户
            $this->accountModel->setDefault($id, $userId);

            $updatedAccount = $this->accountModel->findById($id);
            Response::success($updatedAccount, 'Default payment account updated successfully');

        } catch (Exception $e) {
            Logger::error('Failed to set default payment account', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to set default payment account: ' . $e->getMessage(), 500);
        }
    }
}
