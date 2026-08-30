<?php
/**
 * Email Template Section Settings Model
 * 邮件模板板块设置模型
 */

require_once __DIR__ . '/../utils/Database.php';

class EmailTemplateSectionSettings {
    private $db;
    private $table = 'emailTemplateSectionSettings';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 获取所有板块设置
     */
    public function getAllSections() {
        try {
            $query = "SELECT * FROM {$this->table} ORDER BY sectionKey ASC";
            return $this->db->fetchAll($query);
        } catch (Exception $e) {
            error_log("Error getting all sections: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 根据板块标识获取设置
     */
    public function getBySectionKey($sectionKey) {
        try {
            $query = "SELECT * FROM {$this->table} WHERE sectionKey = :sectionKey";
            return $this->db->fetchOne($query, ['sectionKey' => $sectionKey]);
        } catch (Exception $e) {
            error_log("Error getting section by key: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取板块的模板ID列表
     */
    public function getTemplateIds($sectionKey) {
        $section = $this->getBySectionKey($sectionKey);
        if (!$section || empty($section['templateIds'])) {
            return [];
        }

        $templateIds = json_decode($section['templateIds'], true);
        return is_array($templateIds) ? $templateIds : [];
    }

    /**
     * 更新板块的模板设置
     */
    public function updateTemplateIds($sectionKey, $templateIds, $adminId = null) {
        try {
            $templateIdsJson = json_encode($templateIds);

            // 检查是否存在
            $existing = $this->getBySectionKey($sectionKey);

            // 使用 Database 类的方法
            if ($existing) {
                $updateData = [
                    'templateIds' => $templateIdsJson,
                    'updatedBy' => $adminId,
                    'updatedAt' => date('Y-m-d H:i:s')
                ];
                return $this->db->update($this->table, $updateData, 'sectionKey = :sectionKey', ['sectionKey' => $sectionKey]) > 0;
            } else {
                $insertData = [
                    'sectionKey' => $sectionKey,
                    'sectionName' => ucfirst(str_replace('_', ' ', $sectionKey)),
                    'templateIds' => $templateIdsJson,
                    'createdBy' => $adminId,
                    'updatedBy' => $adminId,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'updatedAt' => date('Y-m-d H:i:s')
                ];
                return $this->db->insert($this->table, $insertData) > 0;
            }
        } catch (Exception $e) {
            error_log("Error updating template IDs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量更新多个板块的设置
     */
    public function updateBatch($settings, $adminId = null) {
        try {
            $this->db->beginTransaction();

            foreach ($settings as $sectionKey => $templateIds) {
                $result = $this->updateTemplateIds($sectionKey, $templateIds, $adminId);
                if (!$result) {
                    $this->db->rollback();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating batch settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据板块标识获取可用的邮件模板列表（只返回激活的模板）
     */
    public function getAvailableTemplates($sectionKey) {
        try {
            $templateIds = $this->getTemplateIds($sectionKey);

            if (empty($templateIds)) {
                return [];
            }

            // 查询激活的邮件模板
            $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
            $query = "SELECT id, templateKey, templateName, emailSubject, emailBody, category, recipientType
                     FROM emailTemplates
                     WHERE id IN ($placeholders) AND isActive = 1
                     ORDER BY templateName ASC";

            return $this->db->fetchAll($query, $templateIds);
        } catch (Exception $e) {
            error_log("Error getting available templates: " . $e->getMessage());
            return [];
        }
    }
}
