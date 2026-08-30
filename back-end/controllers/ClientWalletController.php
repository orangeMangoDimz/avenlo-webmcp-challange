<?php
/**
 * Client Wallet Controller
 * 负责客户保存的钱包地址管理
 */

require_once __DIR__ . '/../models/ClientSavedWallet.php';
require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';

class ClientWalletController {
    private $walletModel;
    private $paymentMethodModel;
    private $userModel;

    public function __construct() {
        $this->walletModel = new ClientSavedWallet();
        $this->paymentMethodModel = new PaymentMethod();
        $this->userModel = new ClientUser();
    }

    /**
     * 获取客户的所有钱包
     * GET /api/client-wallets
     */
    public function index() {
        $client = $this->requireClient();

        $paymentMethodId = $_GET['paymentMethodId'] ?? null;

        if ($paymentMethodId) {
            $wallets = $this->walletModel->getUserWalletsByMethod($client['userId'], $paymentMethodId);
        } else {
            $wallets = $this->walletModel->getUserWallets($client['userId']);
        }

        Response::success($wallets);
    }

    /**
     * 获取单个钱包详情
     * GET /api/client-wallets/{id}
     */
    public function show($id) {
        $client = $this->requireClient();

        $wallet = $this->walletModel->findById($id);

        if (!$wallet) {
            Response::notFound('Wallet not found');
        }

        // 确保只能查看自己的钱包
        if ($wallet['userId'] != $client['userId']) {
            Response::forbidden('Access denied');
        }

        Response::success($wallet);
    }

    /**
     * 创建新钱包
     * POST /api/client-wallets
     */
    public function create() {
        $client = $this->requireClient();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'walletName' => 'required|string|min:2|max:200',
            'paymentMethodId' => 'required|numeric',
            'walletAddress' => 'required|string'
        ]);

        $paymentMethodId = (int)$data['paymentMethodId'];

        // 验证支付方式
        $paymentMethod = $this->paymentMethodModel->findById($paymentMethodId);
        if (!$paymentMethod || $paymentMethod['methodType'] !== 'crypto') {
            Response::validationError([
                'paymentMethodId' => ['Invalid payment method. Only cryptocurrency methods are supported.']
            ]);
        }

        // 检查地址是否已存在
        $existing = $this->walletModel->findOne([
            'userId' => $client['userId'],
            'walletAddress' => trim($data['walletAddress'])
        ]);

        if ($existing) {
            Response::validationError([
                'walletAddress' => ['This wallet address is already saved']
            ]);
        }

        // 创建钱包
        $walletId = $this->walletModel->create([
            'userId' => $client['userId'],
            'walletName' => trim($data['walletName']),
            'paymentMethodId' => $paymentMethodId,
            'walletAddress' => trim($data['walletAddress']),
            'networkType' => $data['networkType'] ?? null,
            'isDefault' => $data['isDefault'] ?? 0
        ]);

        $wallet = $this->walletModel->findById($walletId);

        Response::created($wallet, 'Wallet saved successfully');
    }

    /**
     * 更新钱包
     * PUT /api/client-wallets/{id}
     */
    public function update($id) {
        $client = $this->requireClient();

        $wallet = $this->walletModel->findById($id);

        if (!$wallet) {
            Response::notFound('Wallet not found');
        }

        // 确保只能更新自己的钱包
        if ($wallet['userId'] != $client['userId']) {
            Response::forbidden('Access denied');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];

        if (isset($data['walletName'])) {
            $updateData['walletName'] = trim($data['walletName']);
        }

        if (isset($data['isDefault'])) {
            // 如果设置为默认，使用专门的方法
            if ($data['isDefault']) {
                $this->walletModel->setAsDefault($id, $client['userId']);
            } else {
                $updateData['isDefault'] = 0;
            }
        }

        if (!empty($updateData)) {
            $this->walletModel->update($id, $updateData);
        }

        $updated = $this->walletModel->findById($id);

        Response::success($updated, 'Wallet updated successfully');
    }

    /**
     * 删除钱包
     * DELETE /api/client-wallets/{id}
     */
    public function delete($id) {
        $client = $this->requireClient();

        $wallet = $this->walletModel->findById($id);

        if (!$wallet) {
            Response::notFound('Wallet not found');
        }

        // 确保只能删除自己的钱包
        if ($wallet['userId'] != $client['userId']) {
            Response::forbidden('Access denied');
        }

        $this->walletModel->delete($id);

        Response::success(null, 'Wallet deleted successfully');
    }

    /**
     * 设置为默认钱包
     * POST /api/client-wallets/{id}/set-default
     */
    public function setDefault($id) {
        $client = $this->requireClient();

        $wallet = $this->walletModel->findById($id);

        if (!$wallet) {
            Response::notFound('Wallet not found');
        }

        // 确保只能设置自己的钱包
        if ($wallet['userId'] != $client['userId']) {
            Response::forbidden('Access denied');
        }

        $this->walletModel->setAsDefault($id, $client['userId']);

        $updated = $this->walletModel->findById($id);

        Response::success($updated, 'Default wallet set successfully');
    }

    /**
     * 要求客户端认证（支持预览 X-Preview-Token 与 JWT）
     */
    private function requireClient() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            $user = $this->userModel->findById($userId);
            if (!$user) {
                Response::unauthorized('User not found');
            }
            return [
                'userId' => $userId,
                'user' => $user
            ];
        }

        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'client') {
            Response::forbidden('Client authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::unauthorized('User not found');
        }

        return [
            'userId' => $userId,
            'user' => $user
        ];
    }
}
