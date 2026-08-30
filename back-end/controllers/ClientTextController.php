<?php
/**
 * Client Text Controller
 * 负责客户端界面文字配置管理
 */

require_once __DIR__ . '/../models/ClientInterfaceText.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';

class ClientTextController {
    private $textModel;

    public function __construct() {
        $this->textModel = new ClientInterfaceText();
    }

    /**
     * 获取所有客户端文字配置
     * GET /api/transaction-settings/client-texts
     */
    public function index() {
        try {
            $category = $_GET['category'] ?? null;

            $texts = $this->textModel->getFormattedTexts();

            // 处理 tips 和 informations JSON 字段
            $result = [
                'texts' => []
            ];

            foreach ($texts as $cat => $fields) {
                $result['texts'][$cat] = [];

                foreach ($fields as $key => $value) {
                    // 特殊处理 tips 和 informations 字段
                    if ($key === 'tips') {
                        $result['depositTips'] = json_decode($value, true) ?: [];
                    } elseif ($key === 'informations') {
                        $result['withdrawalInfos'] = json_decode($value, true) ?: [];
                    } else {
                        $result['texts'][$cat][$key] = $value;
                    }
                }
            }

            if ($category && isset($result['texts'][$category])) {
                $response = ['texts' => [$category => $result['texts'][$category]]];

                // 包含相关的 tips/informations
                if ($category === 'deposit' && isset($result['depositTips'])) {
                    $response['depositTips'] = $result['depositTips'];
                }
                if ($category === 'withdrawal' && isset($result['withdrawalInfos'])) {
                    $response['withdrawalInfos'] = $result['withdrawalInfos'];
                }

//                Response::success(['data' => $response]);
                Response::success($response);
            } else {
//                Response::success(['data' => $result]);
                Response::success($result);
            }
        } catch (Exception $e) {
            // Logger::error('Failed to get client texts', ['error' => $e->getMessage()]);
            Response::error('Failed to load client texts', 500);
        }
    }

    /**
     * 获取单个文字配置
     * GET /api/transaction-settings/client-texts/{textKey}
     */
    public function show($textKey) {
        try {
            $text = $this->textModel->getByKey($textKey);

            if (!$text) {
                Response::notFound('Text configuration not found');
                return;
            }

            Response::success(['data' => $text]);
        } catch (Exception $e) {
            // Logger::error('Failed to get client text', [
            //     'textKey' => $textKey,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to load client text', 500);
        }
    }

    /**
     * 批量更新文字配置
     * PUT /api/transaction-settings/client-texts
     *
     * Body: {
     *   "texts": {
     *     "deposit": {
     *       "pageTitle": "...",
     *       "pageDescription": "..."
     *     },
     *     "withdrawal": {
     *       "pageTitle": "...",
     *       "successMessage": "..."
     *     }
     *   },
     *   "depositTips": [
     *     {"id": 1, "icon": "fa-bolt", "title": "...", "description": "..."}
     *   ],
     *   "withdrawalInfos": [
     *     {"id": 1, "icon": "fa-clock", "title": "...", "description": "..."}
     *   ]
     * }
     */
    public function update() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            // 获取管理员ID
            $payload = JWT::getPayload();
            $adminId = $payload['userId'] ?? null;

            $updates = [];
            $successCount = 0;
            $failedCount = 0;

            // 处理文本字段更新（新格式）
            if (isset($data['texts']) && is_array($data['texts'])) {
                foreach (['deposit', 'withdrawal', 'general'] as $category) {
                    if (isset($data['texts'][$category]) && is_array($data['texts'][$category])) {
                        foreach ($data['texts'][$category] as $key => $value) {
                            $textKey = "{$category}.{$key}";
                            $result = $this->textModel->updateText($textKey, $value, $adminId);

                            if ($result) {
                                $successCount++;
                            } else {
                                $failedCount++;
                            }
                        }
                    }
                }
            }

            // 向后兼容旧格式
            foreach (['deposit', 'withdrawal', 'general'] as $category) {
                if (isset($data[$category]) && is_array($data[$category]) && !isset($data['texts'])) {
                    foreach ($data[$category] as $key => $value) {
                        $textKey = "{$category}.{$key}";
                        $result = $this->textModel->updateText($textKey, $value, $adminId);

                        if ($result) {
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    }
                }
            }

            // 处理 depositTips
            if (isset($data['depositTips']) && is_array($data['depositTips'])) {
                $jsonValue = json_encode($data['depositTips'], JSON_UNESCAPED_UNICODE);
                $result = $this->textModel->updateText('deposit.tips', $jsonValue, $adminId);

                if ($result) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            // 处理 withdrawalInfos
            if (isset($data['withdrawalInfos']) && is_array($data['withdrawalInfos'])) {
                $jsonValue = json_encode($data['withdrawalInfos'], JSON_UNESCAPED_UNICODE);
                $result = $this->textModel->updateText('withdrawal.informations', $jsonValue, $adminId);

                if ($result) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            Response::success([
                'message' => 'Client texts updated successfully',
                'data' => [
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'total' => $successCount + $failedCount
                ]
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to update client texts', ['error' => $e->getMessage()]);
            Response::error('Failed to update client texts', 500);
        }
    }

    /**
     * 恢复单个文字为默认值
     * POST /api/transaction-settings/client-texts/{textKey}/restore
     */
    public function restore($textKey) {
        try {
            $result = $this->textModel->restoreDefault($textKey);

            if (!$result) {
                Response::error('Failed to restore default value', 400);
                return;
            }

            // 获取管理员ID用于日志
            $payload = JWT::getPayload();
            $adminId = $payload['userId'] ?? null;

            Response::success([
                'message' => 'Text restored to default successfully',
                'data' => $this->textModel->getByKey($textKey)
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to restore client text', [
            //     'textKey' => $textKey,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to restore default value', 500);
        }
    }

    /**
     * 批量恢复默认值
     * POST /api/transaction-settings/client-texts/restore-all
     *
     * Body: {
     *   "category": "deposit" // optional
     * }
     */
    public function restoreAll() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $category = $data['category'] ?? null;

            $texts = $this->textModel->getActiveTexts($category);
            $successCount = 0;
            $failedCount = 0;

            foreach ($texts as $text) {
                if ($this->textModel->restoreDefault($text['textKey'])) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            $payload = JWT::getPayload();
            $adminId = $payload['userId'] ?? null;

            Response::success([
                'message' => 'Texts restored successfully',
                'data' => [
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'total' => $successCount + $failedCount
                ]
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to restore client texts', ['error' => $e->getMessage()]);
            Response::error('Failed to restore texts', 500);
        }
    }
}
