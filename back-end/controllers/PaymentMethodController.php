<?php
/**
 * Payment Method Controller
 * 负责支付方式相关接口
 */

require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';

class PaymentMethodController {
    private $paymentMethodModel;

    public function __construct() {
        $this->paymentMethodModel = new PaymentMethod();
    }

    /**
     * 获取所有支付方式
     * GET /api/payment-methods
     */
    public function index() {
        $type = $_GET['type'] ?? null; // 'deposit' or 'withdrawal'

        $methods = $this->paymentMethodModel->getEnabledMethods($type);

        Response::success($methods);
    }

    /**
     * 获取单个支付方式
     * GET /api/payment-methods/{id}
     */
    public function show($id) {
        $method = $this->paymentMethodModel->findById($id);

        if (!$method) {
            Response::notFound('Payment method not found');
        }

        Response::success($method);
    }

    /**
     * 获取加密货币支付方式
     * GET /api/payment-methods/crypto
     */
    public function getCryptoMethods() {
        $methods = $this->paymentMethodModel->getCryptoMethods();
        Response::success($methods);
    }

    /**
     * 获取法币支付方式
     * GET /api/payment-methods/fiat
     */
    public function getFiatMethods() {
        $methods = $this->paymentMethodModel->getFiatMethods();
        Response::success($methods);
    }

    /**
     * 更新支付方式配置 (管理员)
     * PUT /api/payment-methods/{id}
     */
    public function update($id) {
        $this->requireAdmin();

        $method = $this->paymentMethodModel->findById($id);
        if (!$method) {
            Response::notFound('Payment method not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];

        if (isset($data['isDepositEnabled'])) {
            $updateData['isDepositEnabled'] = $data['isDepositEnabled'] ? 1 : 0;
        }

        if (isset($data['isWithdrawalEnabled'])) {
            $updateData['isWithdrawalEnabled'] = $data['isWithdrawalEnabled'] ? 1 : 0;
        }

        if (isset($data['displayOrder'])) {
            $updateData['displayOrder'] = (int)$data['displayOrder'];
        }

        if (empty($updateData)) {
            Response::error('No valid fields to update', 400);
        }

        $this->paymentMethodModel->update($id, $updateData);

        $updatedMethod = $this->paymentMethodModel->findById($id);

        Response::success($updatedMethod, 'Payment method updated successfully');
    }

    /**
     * 要求管理员认证
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        return $payload;
    }
}
