<?php
/**
 * FinancePro Token Model
 * 使用本地JSON文件存储Token
 */

require_once __DIR__ . '/../utils/Encryptor.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';

class FinanceProToken {
    private $filePath;

    public function __construct() {
        $config = require __DIR__ . '/../config/app.php';
        $storagePath = $config['token_storage']['path'] ?? __DIR__ . '/../storage/tokens/';
        $filename = $config['token_storage']['filename'] ?? 'financepro_token.json';

        // 确保目录存在
        if (!is_dir($storagePath)) {
            @mkdir($storagePath, 0755, true);
        }

        $this->filePath = rtrim($storagePath, '/') . '/' . $filename;
    }

    /**
     * 保存或更新Token
     * @param array $tokenData Token数据（包含access_token、token_type、expires_in、scope）
     * @return bool
     */
    public function saveToken(array $tokenData) {
        try {
            // 加密存储access_token
            $encryptedToken = Encryptor::encrypt($tokenData['access_token'] ?? '');

            // 计算过期时间
            $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

            $data = [
                'accessToken' => $encryptedToken,
                'tokenType' => $tokenData['token_type'] ?? 'Bearer',
                'expiresIn' => $expiresIn,
                'scope' => $tokenData['scope'] ?? null,
                'expiresAt' => $expiresAt,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ];

            // 写入文件（使用文件锁确保并发安全）
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $result = @file_put_contents($this->filePath, $jsonData, LOCK_EX);

            if ($result === false) {
//                Logger::error('Failed to save FinancePro Token to file', [
//                    'file_path' => $this->filePath
//                ]);
                return false;
            }

            // 设置文件权限（仅所有者可读写）
            @chmod($this->filePath, 0600);

            return true;
        } catch (Exception $e) {
//            Logger::error('Exception while saving FinancePro Token', [
//                'exception' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);
            return false;
        }
    }

    /**
     * 获取当前有效的Token
     * 如果Token过期，会自动重新获取并保存
     * @return array|null
     */
    public function getActiveToken() {
        // 如果文件不存在，尝试获取新token
        if (!file_exists($this->filePath)) {
            return $this->refreshToken();
        }

        try {
            // 读取文件（使用文件锁）
            $content = @file_get_contents($this->filePath);
            if ($content === false) {
//                Logger::warning('Failed to read FinancePro Token file, attempting to refresh');
                return $this->refreshToken();
            }

            $data = json_decode($content, true);
            if ($data === null || !isset($data['accessToken'])) {
//                Logger::warning('FinancePro Token file is invalid, attempting to refresh');
                return $this->refreshToken();
            }

            // 检查是否过期
            $isExpired = false;
            if (isset($data['expiresAt'])) {
                $expiresAt = strtotime($data['expiresAt']);
                // 提前5分钟刷新token，避免在请求过程中过期
                $bufferTime = 300; // 5分钟
                if ($expiresAt <= (time() + $bufferTime)) {
                    $isExpired = true;
                }
            }

            // 如果过期，尝试刷新token
            if ($isExpired) {
                return $this->refreshToken();
            }

            // 解密Token
            $decryptedToken = Encryptor::decrypt($data['accessToken']);
            if ($decryptedToken === null) {
//                Logger::error('Failed to decrypt FinancePro Token, attempting to refresh');
                return $this->refreshToken();
            }

            // 返回解密后的数据
            return [
                'accessToken' => $decryptedToken,
                'tokenType' => $data['tokenType'] ?? 'Bearer',
                'expiresIn' => $data['expiresIn'] ?? null,
                'scope' => $data['scope'] ?? null,
                'expiresAt' => $data['expiresAt'] ?? null,
                'createdAt' => $data['createdAt'] ?? null,
                'updatedAt' => $data['updatedAt'] ?? null
            ];
        } catch (Exception $e) {
//            Logger::error('Exception while reading FinancePro Token, attempting to refresh', [
//                'exception' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);
            return $this->refreshToken();
        }
    }

    /**
     * 刷新Token（重新获取并保存）
     * @return array|null
     */
    private function refreshToken() {
        try {
            // 创建 FinanceProApiClient 实例来获取新token
            $client = new FinanceProApiClient();
            $tokenData = $client->getToken();

            // 保存新token
            $saved = $this->saveToken($tokenData);
            if (!$saved) {
//                Logger::error('Failed to save refreshed FinancePro Token');
                return null;
            }

            // 返回新token的数据（与getActiveToken返回格式一致）
            $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

            return [
                'accessToken' => $tokenData['access_token'] ?? '',
                'tokenType' => $tokenData['token_type'] ?? 'Bearer',
                'expiresIn' => $expiresIn,
                'scope' => $tokenData['scope'] ?? null,
                'expiresAt' => $expiresAt,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
//            Logger::error('Failed to refresh FinancePro Token', [
//                'exception' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);
            return null;
        }
    }

    /**
     * 检查Token是否有效（未过期）
     * @return bool
     */
    public function hasValidToken() {
        $token = $this->getActiveToken();
        return $token !== null;
    }

    /**
     * 删除Token文件（停用Token）
     */
    public function deactivateAll() {
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    /**
     * 清理过期Token（如果过期则删除文件）
     */
    public function cleanExpired() {
        $token = $this->getActiveToken();
        if ($token === null && file_exists($this->filePath)) {
            // Token已过期或无效，删除文件
            @unlink($this->filePath);
        }
    }

    /**
     * 获取Token文件路径（用于调试）
     * @return string
     */
    public function getFilePath() {
        return $this->filePath;
    }
}
