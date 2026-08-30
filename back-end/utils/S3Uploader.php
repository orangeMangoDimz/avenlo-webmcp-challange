<?php
/**
 * AWS S3 文件上传工具类
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

class S3Uploader {
    private $s3Client;
    private $bucket;
    private $config;

    public function __construct() {
        $appConfig = require __DIR__ . '/../config/app.php';
        $s3Config = $appConfig['aws']['s3'] ?? null;

        if (!$s3Config || empty($s3Config['key']) || empty($s3Config['secret'])) {
            throw new Exception('AWS S3 configuration not found');
        }

        $this->config = $s3Config;
        $this->bucket = $s3Config['bucket'];

        // 初始化 S3 客户端（使用 AWS SDK v2）
        $this->s3Client = Aws\S3\S3Client::factory([
            'key' => $s3Config['key'],
            'secret' => $s3Config['secret'],
            'region' => $s3Config['region'],
//            'version' => $s3Config['version']
        ]);
    }

    /**
     * 上传文件到 S3
     * @param string $filePath 本地文件路径（临时文件）
     * @param string $s3Key S3 中的文件键名（路径）
     * @param string $contentType 文件 MIME 类型
     * @return array ['url' => S3 URL, 'key' => S3 Key]
     */
    public function uploadFile($filePath, $s3Key, $contentType = null) {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'SourceFile' => $filePath,
                'ACL' => 'private'  // 私有文件，或改为 'public-read' 如果需要公开访问
            ];

            if ($contentType) {
                $params['ContentType'] = $contentType;
            }

            $result = $this->s3Client->putObject($params);

            // 生成文件 URL
            $url = $this->s3Client->getObjectUrl($this->bucket, $s3Key);

            return [
                'success' => true,
                'url' => $url,
                'key' => $s3Key,
                'bucket' => $this->bucket
            ];
        } catch (Exception $e) {
//            error_log("S3 Upload Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成 S3 文件键名（路径）
     * @param string $filename 文件名
     * @param string $pathPrefix 路径前缀，默认为 'common'
     * @return string S3 Key
     */
    public function generateS3Key($filename, $pathPrefix = 'common') {
        $appConfig = require __DIR__ . '/../config/app.php';
        $filepath = $appConfig['aws']['filepath'] ?? 'crm';
        // 生成日期格式：YYYYMMDD（例如：20251105）
        $dateFolder = date('Ymd');

        // 格式: {$filepath}/{pathPrefix}/日期/filename
        return "{$filepath}/{$pathPrefix}/{$dateFolder}/{$filename}";
    }

    /**
     * 删除 S3 文件
     * @param string $s3Key S3 Key
     * @return bool
     */
    public function deleteFile($s3Key) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key
            ]);
            return true;
        } catch (Exception $e) {
//            error_log("S3 Delete Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 生成预签名 URL（用于私有文件访问）
     * @param string $s3Key S3 Key
     * @param int $expires 过期时间（秒），默认 1 小时
     * @return string 预签名 URL
     */
    public function getPresignedUrl($s3Key, $expires = 3600) {
        try {
            // AWS SDK v2.8 最简单方式：使用 getObjectUrl 方法
            // 该方法已内置预签名 URL 功能，如果提供了 $expires 参数，会自动生成预签名 URL
            // 方法签名：getObjectUrl($bucket, $key, $expires = null, array $args = array())
            // $expires 可以是 Unix 时间戳、DateTime 对象或字符串
            $expiresTimestamp = time() + $expires;
            $presignedUrl = $this->s3Client->getObjectUrl($this->bucket, $s3Key, $expiresTimestamp);

            return $presignedUrl;

        } catch (Exception $e) {
            error_log("S3 Presigned URL Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 从 S3 URL 中提取 S3 Key
     * @param string $url S3 完整 URL
     * @return string|null S3 Key
     */
    public function extractS3KeyFromUrl($url) {
        // 解析 URL
        $parsedUrl = parse_url($url);

        if (!$parsedUrl || !isset($parsedUrl['path'])) {
            return null;
        }

        // 移除路径开头的 /
        $s3Key = ltrim($parsedUrl['path'], '/');

        // Path-style S3 URLs include the bucket name as the first path segment,
        // e.g. /bucket-name/path/to/object. Strip it so we return the real key.
        $host = $parsedUrl['host'] ?? '';
        if ($host !== '' && preg_match('/(^s3[.-]|amazonaws\.com$)/', $host)) {
            $bucketPrefix = $this->bucket . '/';
            if (strpos($s3Key, $bucketPrefix) === 0) {
                $s3Key = substr($s3Key, strlen($bucketPrefix));
            }
        }

        return $s3Key ?: null;
    }

    /**
     * 从 S3 URL 中提取 S3 Key，并生成预签名 URL
     * @param string $s3Url S3 完整 URL（例如：https://bucket.s3.amazonaws.com/kyc/20251105/file.jpg）
     * @param int $expires 过期时间（秒），默认 1 小时
     * @return string 预签名 URL
     */
    public function getS3PresignedUrl($s3Url, $expires = 3600) {
        try {
            // 从完整 URL 中提取 S3 Key
            $s3Key = $this->extractS3KeyFromUrl($s3Url);

            if (!$s3Key) {
                error_log("Failed to extract S3 key from URL: " . $s3Url);
                return $s3Url; // 如果提取失败，返回原 URL
            }

            // 生成预签名 URL
            return $this->getPresignedUrl($s3Key, $expires);
        } catch (Exception $e) {
            error_log("S3 Presigned URL Generation Error: " . $e->getMessage());
            return $s3Url; // 如果出错，返回原 URL
        }
    }

    /**
     * 生成文件下载链接
     * 支持本地文件和 S3 文件
     * @param string $filePath 文件路径（本地路径）或 S3 URL（完整 URL）或 S3 Key
     * @param string|null $baseUrl 本地文件的基础URL（如果不提供，从配置中读取）
     * @return string|null 完整的下载链接（本地文件返回完整 URL，S3 文件返回预签名 URL）
     */
    public function getFileDownloadUrl($filePath, $baseUrl = null) {
        if (empty($filePath)) {
            return null;
        }

        // 判断是否为 S3 URL（以 http:// 或 https:// 开头，且包含 s3 或 amazonaws.com）
        if (preg_match('/^https?:\/\//', $filePath) &&
            (strpos($filePath, 's3') !== false || strpos($filePath, 'amazonaws.com') !== false)) {
            // 这是 S3 URL，需要生成预签名 URL
            return $this->getS3PresignedUrl($filePath);
        }

        // 判断是否为 S3 Key（格式：path/YYYYMMDD/filename）
        if (preg_match('/^(?:.+\/)?(withdraw|kyc|common)\/\d{8}\//', $filePath)) {
            // 这是 S3 Key，直接生成预签名 URL
            try {
                return $this->getPresignedUrl($filePath, 3600);
            } catch (Exception $e) {
                error_log("S3 Presigned URL Generation Error: " . $e->getMessage());
                return null;
            }
        }

        // 本地文件路径，使用原有逻辑
        if ($baseUrl === null) {
            $appConfig = require __DIR__ . '/../config/app.php';
            $baseUrl = $appConfig['file_base_url'] ?? '';
        }

        // 确保路径不以 / 开头
        $filePath = ltrim($filePath, '/');

        // 拼接完整URL
        return rtrim($baseUrl, '/') . '/' . $filePath;
    }
}
