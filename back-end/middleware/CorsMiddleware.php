<?php
/**
 * CORS中间件
 * 处理跨域请求
 */

class CorsMiddleware {

    /**
     * 设置CORS头
     */
    public static function handle() {
        $config = require __DIR__ . '/../config/app.php';
        $cors = $config['cors'];

        // 设置允许的源
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            if ($cors['allow_origin'] === '*') {
//                header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            } else {
                $allowedOrigins = explode(',', $cors['allow_origin']);
                if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
//                    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                }
            }
        } else {
//            header("Access-Control-Allow-Origin: {$cors['allow_origin']}");
        }

        header("Access-Control-Allow-Methods: {$cors['allow_methods']}");
        header("Access-Control-Allow-Headers: {$cors['allow_headers']}");
        header('Access-Control-Expose-Headers: X-Request-ID');

        if ($cors['allow_credentials']) {
            header("Access-Control-Allow-Credentials: true");
        }

        header("Access-Control-Max-Age: 3600");

        // 处理OPTIONS预检请求
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
