<?php
/**
 * Account Verification Controller
 * 账户验证控制器 - 处理出金账户验证
 */

require_once __DIR__ . '/../models/AccountVerification.php';
require_once __DIR__ . '/../models/AccountVerificationFile.php';
require_once __DIR__ . '/../models/AccountVerificationLog.php';
require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';

class AccountVerificationController {
    private $verificationModel;
    private $fileModel;
    private $logModel;
    private $paymentMethodModel;
    private $clientUserModel;
    private $uploadDir;

    public function __construct() {
        $this->verificationModel = new AccountVerification();
        $this->fileModel = new AccountVerificationFile();
        $this->logModel = new AccountVerificationLog();
        $this->paymentMethodModel = new PaymentMethod();
        $this->clientUserModel = new ClientUser();

        // 设置上传目录
        $this->uploadDir = __DIR__ . '/../uploads/verifications/';
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * 获取客户的已验证账户
     * GET /api/account-verification/verified-accounts
     */
    public function getVerifiedAccounts() {
        $client = $this->requireClient();
        $paymentMethodId = $_GET['paymentMethodId'] ?? null;

        $accounts = $this->verificationModel->getVerifiedAccounts(
            $client['userId'],
            $paymentMethodId
        );

        Response::success($accounts);
    }

    /**
     * 提交账户验证申请
     * POST /api/account-verification/submit
     */
    public function submitVerification() {
        $client = $this->requireClient();

        // 验证必填字段
        $requiredFields = ['paymentMethodId', 'accountType', 'accountName'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                Response::validationError([
                    $field => ["The {$field} field is required"]
                ]);
            }
        }

        $paymentMethodId = (int)$_POST['paymentMethodId'];
        $accountType = $_POST['accountType'];
        $accountName = $_POST['accountName'];

        // 验证账户类型
        if (!in_array($accountType, ['bank', 'crypto'])) {
            Response::validationError([
                'accountType' => ['Invalid account type']
            ]);
        }

        // 验证支付方式
        $paymentMethod = $this->paymentMethodModel->findById($paymentMethodId);
        if (!$paymentMethod) {
            Response::validationError([
                'paymentMethodId' => ['Invalid payment method']
            ]);
        }

        // 检查是否已有待审核的申请
        if ($this->verificationModel->hasPendingVerification($client['userId'], $paymentMethodId)) {
            Response::error('You already have a pending verification request for this payment method', 400);
        }

        // 准备验证数据
        $verificationData = [
            'userId' => $client['userId'],
            'paymentMethodId' => $paymentMethodId,
            'accountType' => $accountType,
            'accountName' => $accountName,
            'clientNotes' => $_POST['notes'] ?? null
        ];

        // 银行账户验证
        if ($accountType === 'bank') {
            if (empty($_POST['bankName']) || empty($_POST['accountNumber']) || empty($_POST['accountHolderName'])) {
                Response::validationError([
                    'bankInfo' => ['Bank name, account number, and account holder name are required for bank verification']
                ]);
            }

            $verificationData['bankName'] = $_POST['bankName'];
            $verificationData['accountNumber'] = $this->encryptAccountNumber($_POST['accountNumber']);
            $verificationData['accountHolderName'] = $_POST['accountHolderName'];
            $verificationData['swiftCode'] = $_POST['swiftCode'] ?? null;

            // 验证银行流水文件
            if (empty($_FILES['bankStatementFile'])) {
                Response::validationError([
                    'bankStatementFile' => ['Bank statement file is required']
                ]);
            }

            $fileCategory = 'bank_statement';
            $uploadFile = $_FILES['bankStatementFile'];
        }
        // 加密货币钱包验证
        else if ($accountType === 'crypto') {
            if (empty($_POST['walletAddress'])) {
                Response::validationError([
                    'walletAddress' => ['Wallet address is required for crypto verification']
                ]);
            }

            $verificationData['walletAddress'] = $_POST['walletAddress'];
            $verificationData['walletNetwork'] = !empty($_POST['walletNetwork']) ? $_POST['walletNetwork'] : null;

            // 验证钱包截图
            if (empty($_FILES['walletScreenshotFile'])) {
                Response::validationError([
                    'walletScreenshotFile' => ['Wallet screenshot is required']
                ]);
            }

            $fileCategory = 'wallet_screenshot';
            $uploadFile = $_FILES['walletScreenshotFile'];
        }

        // 验证文件
        $fileValidation = $this->validateUploadedFile($uploadFile);
        if (!$fileValidation['valid']) {
            Response::validationError([
                'file' => [$fileValidation['error']]
            ]);
        }

        try {
            // 开始事务
            $db = Database::getInstance();
            $db->beginTransaction();

            // 创建验证记录
            $verificationId = $this->verificationModel->createVerification($verificationData);

            // 上传文件
            $fileData = $this->handleFileUpload($uploadFile, $verificationId, $fileCategory);
            $this->fileModel->createFile($fileData);

            // 记录日志
            $this->logModel->logCreation($verificationId, $client['userId'], $this->getClientIp());
            $this->logModel->logSubmission($verificationId, $client['userId'], $this->getClientIp());
            $this->logModel->logFileUpload($verificationId, $client['userId'], $uploadFile['name'], $this->getClientIp());

            // 提交事务
            $db->commit();

            Response::success([
                'verificationId' => $verificationId,
                'status' => 'pending',
                'message' => 'Verification request submitted successfully. You will be notified once reviewed.'
            ], 'Verification submitted successfully');

        } catch (Exception $e) {
            $db = Database::getInstance();
            $db->rollBack();
//             Logger::error("Failed to submit verification", [
//                 'error' => $e->getMessage(),
//                 'userId' => $client['userId']
//             ]);
            Response::error('Failed to submit verification: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取验证申请列表（管理员）
     * GET /api/account-verification/list
     */
    public function getVerificationList() {
        $this->requireAdmin();

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;

        $filters = [];
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['accountType'])) {
            $filters['accountType'] = $_GET['accountType'];
        }
        if (isset($_GET['paymentMethodId'])) {
            $filters['paymentMethodId'] = $_GET['paymentMethodId'];
        }
        if (isset($_GET['userId'])) {
            $filters['userId'] = $_GET['userId'];
        }

        $result = $this->verificationModel->getVerifications($page, $perPage, $filters);

        // 为每个验证添加文件列表
        foreach ($result['items'] as &$item) {
            $item['files'] = $this->fileModel->getVerificationFiles($item['id']);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取验证详情
     * GET /api/account-verification/{id}
     */
    public function getVerificationDetails($id) {
        $admin = $this->requireAdmin();

        $verification = $this->verificationModel->getVerificationDetails($id);

        if (!$verification) {
            Response::notFound('Verification not found');
        }

        // 添加文件和日志
        $verification['files'] = $this->fileModel->getVerificationFiles($id);
        $verification['logs'] = $this->logModel->getVerificationLogs($id);

        // 解密账号（仅管理员可见）
        if ($verification['accountType'] === 'bank' && !empty($verification['accountNumber'])) {
            $verification['accountNumberDecrypted'] = $this->decryptAccountNumber($verification['accountNumber']);
        }

        Response::success($verification);
    }

    /**
     * 审核验证申请
     * POST /api/account-verification/{id}/review
     */
    public function reviewVerification($id) {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'action' => 'required|in:approve,reject'
        ]);

        $verification = $this->verificationModel->findById($id);
        if (!$verification) {
            Response::notFound('Verification not found');
        }

        if ($verification['verificationStatus'] !== 'pending') {
            Response::error('Verification has already been reviewed', 400);
        }

        $action = $data['action'];
        $reviewNotes = $data['reviewNotes'] ?? null;
        $rejectionReason = $action === 'reject' ? ($data['rejectionReason'] ?? null) : null;

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        try {
            $this->verificationModel->updateVerificationStatus(
                $id,
                $newStatus,
                $admin['userId'],
                $reviewNotes,
                $rejectionReason
            );

            // TODO: 发送邮件通知客户

            Response::success([
                'verificationId' => $id,
                'status' => $newStatus
            ], "Verification {$action}d successfully");

        } catch (Exception $e) {
            // Logger::error("Failed to review verification", [
            //     'error' => $e->getMessage(),
            //     'verificationId' => $id
            // ]);
            Response::error('Failed to review verification: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 下载验证文件
     * GET /api/account-verification/files/{fileId}
     */
    public function downloadFile($fileId) {
        $admin = $this->requireAdmin();

        $file = $this->fileModel->findById($fileId);
        if (!$file) {
            Response::notFound('File not found');
        }

        $filePath = __DIR__ . '/../' . $file['filePath'];

        if (!file_exists($filePath)) {
            Response::notFound('File not found on server');
        }

        // 设置下载头
        header('Content-Type: ' . $file['fileType']);
        header('Content-Disposition: attachment; filename="' . $file['fileName'] . '"');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }

    /**
     * 获取验证统计
     * GET /api/account-verification/statistics
     */
    public function getStatistics() {
        $this->requireAdmin();

        $stats = $this->verificationModel->getStatistics();

        Response::success($stats);
    }

    // ============ 辅助方法 ============

    /**
     * 验证上传的文件
     */
    private function validateUploadedFile($file) {
        // 检查文件是否上传成功
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload failed'];
        }

        // 检查文件类型
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed'];
        }

        // 检查文件大小 (默认5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum limit of 5MB'];
        }

        return ['valid' => true];
    }

    /**
     * 处理文件上传
     */
    private function handleFileUpload($file, $verificationId, $category) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = 'verification_' . $verificationId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = $this->uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        return [
            'verificationId' => $verificationId,
            'fileName' => $file['name'],
            'filePath' => 'uploads/verifications/' . $newFileName,
            'fileType' => $file['type'],
            'fileSize' => $file['size'],
            'fileCategory' => $category
        ];
    }

    /**
     * 加密账号
     */
    private function encryptAccountNumber($accountNumber) {
        // TODO: 实现实际的加密逻辑
        // 这里简单示例，实际应使用 openssl_encrypt 等
        return base64_encode($accountNumber);
    }

    /**
     * 解密账号
     */
    private function decryptAccountNumber($encryptedAccountNumber) {
        // TODO: 实现实际的解密逻辑
        return base64_decode($encryptedAccountNumber);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp() {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * 要求客户端认证（支持预览 X-Preview-Token 与 JWT）
     */
    private function requireClient() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return ['userId' => $userId];
        }

        $token = $this->getBearerToken();
        if (!$token) {
            Response::unauthorized('No token provided');
        }

        $decoded = JWT::decode($token);
        if (!$decoded || ($decoded['type'] ?? '') !== 'client') {
            Response::unauthorized('Invalid token or insufficient permissions');
        }

        return $decoded;
    }

    /**
     * 要求管理员认证
     */
    private function requireAdmin() {
        $token = $this->getBearerToken();
        if (!$token) {
            Response::unauthorized('No token provided');
        }

        $decoded = JWT::decode($token);
        if (!$decoded || ($decoded['type'] ?? '') !== 'admin') {
            Response::unauthorized('Admin access required');
        }

        return $decoded;
    }

    /**
     * 获取Bearer Token
     */
    private function getBearerToken() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
