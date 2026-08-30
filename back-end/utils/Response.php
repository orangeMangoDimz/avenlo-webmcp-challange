<?php
/**
 * API响应处理类
 * 统一的JSON响应格式
 */

class Response {

    /**
     * 成功响应
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], $statusCode);
    }

    /**
     * 空响应
     */
    public static function empty($statusCode = 200) {
        http_response_code($statusCode);
        exit;
    }

    /**
     * 错误响应
     * 注意：所有错误响应统一返回HTTP状态码200，通过success字段判断是否成功
     * 原始状态码保存在响应数据的statusCode字段中，便于前端处理特殊错误（如401需要登出）
     *
     * @param string|null $errorCode 可选业务错误码；不传则响应体中不包含 errorCode 字段（兼容旧调用）
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = null, $errorCode = null) {
        if ((int) $statusCode >= 500) {
            try {
                require_once __DIR__ . '/../services/ApplicationErrorHandler.php';
                ApplicationErrorHandler::recordResponseError($message, $statusCode, $errors, $errorCode);
            } catch (Throwable $e) {
                @error_log('Response::error 5xx log failed: ' . $e->getMessage());
            }
        }

        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'statusCode' => $statusCode,  // 保留原始状态码，便于前端判断（如401需要登出）
            'timestamp' => time()
        ];
        if ($errorCode !== null && $errorCode !== '') {
            $payload['errorCode'] = $errorCode;
        }
        self::json($payload, 200);  // 统一返回200状态码，避免CORS问题
    }

    /**
     * 资源创建成功响应（201）
     * 注意：统一返回HTTP状态码200，通过statusCode字段保留原始状态码，避免CORS问题
     */
    public static function created($data = null, $message = 'Created') {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'statusCode' => 201,  // 保留原始状态码
            'timestamp' => time()
        ], 200);  // 统一返回200状态码，避免CORS问题
    }

    /**
     * 未授权响应
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    /**
     * 禁止访问响应
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    /**
     * 未找到响应
     */
    public static function notFound($message = 'Not Found') {
        self::error($message, 404);
    }

    /**
     * 验证错误响应
     * 如果没有提供 message，自动从 errors 中生成友好的错误信息
     */
    public static function validationError($errors, $message = null) {
        // 如果没有提供 message，从 errors 中自动生成
        if ($message === null) {
            $messages = [];
            foreach ($errors as $field => $errorMessages) {
                // 处理数组格式 ['field' => ['error1', 'error2']]
                if (is_array($errorMessages)) {
                    $messages = array_merge($messages, $errorMessages);
                }
                // 处理字符串格式 ['field' => 'error message']
                else {
                    $messages[] = $errorMessages;
                }
            }
            // 如果成功生成了错误信息，使用它们；否则使用默认值
            $message = !empty($messages) ? implode('; ', $messages) : 'Validation Failed';
        }

        // 记录详细错误到日志
//        error_log("Validation Error: " . json_encode($errors));
        self::error($message, 422, $errors);
    }

    /**
     * 服务器错误响应
     */
    public static function serverError($message = 'Internal Server Error') {
        self::error($message, 500);
    }

    /**
     * 分页响应
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Success') {
        self::success([
            'items' => $data,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$perPage,
                'page' => (int)$page,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ], $message);
    }

    /**
     * 清理数据中的无效 UTF-8 字符
     */
    private static function cleanUtf8($data) {
        if (is_string($data)) {
            // 移除无效的 UTF-8 字符
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        } elseif (is_array($data)) {
            return array_map([self::class, 'cleanUtf8'], $data);
        } elseif (is_object($data)) {
            $cleaned = new \stdClass();
            foreach ($data as $key => $value) {
                $cleaned->$key = self::cleanUtf8($value);
            }
            return $cleaned;
        }
        return $data;
    }

    /**
     * 对 JSON 响应启用 gzip。
     * zlib.output_compression 自己处理 Accept-Encoding 协商，客户端不支持时会自动跳过。
     * 必须在任何输出之前设置，因此只在响应头还没发出时才启用。
     */
    private static function enableCompression() {
        if (headers_sent() || ob_get_level() > 0) {
            return;
        }
        if (ini_get('zlib.output_compression')) {
            return;
        }
        @ini_set('zlib.output_compression', 'On');
    }

    /**
     * 输出JSON响应
     */
    private static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        self::enableCompression();

        // 清理无效的 UTF-8 字符
        $cleanedData = self::cleanUtf8($data);

        // 不使用 JSON_PRETTY_PRINT：缩进只对人可读，却让每个响应体积增加 30%+，
        // 在高延迟链路（VPN / 移动网络）上直接体现为加载变慢。
        $json = json_encode($cleanedData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        if ($json === false) {
            // JSON编码失败，记录错误
            error_log("JSON encode error in Response::json(): " . json_last_error_msg() . " | Error code: " . json_last_error());
            // 尝试返回错误响应
            $errorResponse = [
                'success' => false,
                'message' => 'Internal server error: Failed to encode response',
                'timestamp' => time()
            ];
            $errorJson = json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            if ($errorJson !== false) {
                echo $errorJson;
            }
            exit;
        }
        echo $json;
        exit;
    }

    /**
     * 设置CORS头
     */
    public static function setCorsHeaders() {
        $config = require __DIR__ . '/../config/app.php';
        $cors = $config['cors'];

//        header("Access-Control-Allow-Origin: {$cors['allow_origin']}");
        header("Access-Control-Allow-Methods: {$cors['allow_methods']}");
        header("Access-Control-Allow-Headers: {$cors['allow_headers']}");

        if ($cors['allow_credentials']) {
            header("Access-Control-Allow-Credentials: true");
        }

        // 处理预检请求
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
