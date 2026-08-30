<?php
/**
 * KYC Template Country Model
 * 对应表: kycTemplateCountries
 */

require_once __DIR__ . '/BaseModel.php';

class KycTemplateCountry extends BaseModel {
    protected $table = 'kycTemplateCountries';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'countryCode',
        'countryName'
    ];

    /**
     * 获取模板的所有国家
     */
    public function getTemplateCountries($templateId) {
        return $this->findAll(['templateId' => $templateId], 'countryName');
    }

    /**
     * 批量添加国家到模板
     */
    public function assignCountriesToTemplate($templateId, $countries) {
        $results = [];
        foreach ($countries as $country) {
            $data = [
                'templateId' => $templateId,
                'countryCode' => $country['code'],
                'countryName' => $country['name']
            ];

            try {
                $results[] = $this->create($data);
            } catch (Exception $e) {
                // 忽略重复错误
                continue;
            }
        }
        return $results;
    }

    /**
     * 移除模板的所有国家
     */
    public function removeTemplateCountries($templateId) {
        $sql = "DELETE FROM {$this->table} WHERE templateId = :templateId";
        $stmt = $this->db->query($sql, ['templateId' => $templateId]);
        return $stmt->rowCount();
    }

    /**
     * 更新模板的国家列表
     */
    public function updateTemplateCountries($templateId, $countries) {
        // 先删除现有的
        $this->removeTemplateCountries($templateId);

        // 再添加新的
        return $this->assignCountriesToTemplate($templateId, $countries);
    }

    /**
     * 检查模板是否适用于某个国家
     */
    public function templateAppliesTo($templateId, $countryCode) {
        $conditions = [
            'templateId' => $templateId,
            'countryCode' => $countryCode
        ];

        $result = $this->findOne($conditions);

        if ($result) {
            return true;
        }

        // 检查是否有 "ALL" 国家
        $allCountries = $this->findOne([
            'templateId' => $templateId,
            'countryCode' => 'ALL'
        ]);

        return $allCountries !== null;
    }

    /**
     * 根据国家代码查找所有适用的活跃模板
     *
     * @param string $countryCode 国家代码
     * @return array 模板列表，按优先级排序
     */
    public function findTemplatesByCountry($countryCode) {
        $sql = "SELECT t.*, tc.countryCode, tc.countryName,
                       CASE WHEN tc.countryCode = :country THEN 0 ELSE 1 END as priority
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                AND (tc.countryCode = :country OR tc.countryCode = 'ALL')
                ORDER BY priority ASC, t.displayOrder ASC, t.id ASC";

        return $this->query($sql, ['country' => $countryCode]);
    }

    /**
     * 获取所有支持的国家列表
     *
     * @return array 国家列表
     */
    public function getAllSupportedCountries() {
        $sql = "SELECT DISTINCT countryCode, countryName
                FROM {$this->table}
                WHERE countryCode != 'ALL'
                ORDER BY countryName ASC";

        return $this->query($sql);
    }

    /**
     * 获取已被其他模板占用的国家代码（每个国家/ALL 只能被一个模板选择）
     * @param int|null $excludeTemplateId 排除的模板 ID（当前编辑的模板，其已选国家不算占用）
     * @return array 已被占用的 countryCode 列表，如 ['ALL', 'US']
     */
    public function getTakenCountryCodes($excludeTemplateId = null) {
        $sql = "SELECT DISTINCT countryCode FROM {$this->table}";
        $params = [];
        if ($excludeTemplateId !== null && $excludeTemplateId !== '') {
            $sql .= " WHERE templateId != :excludeId";
            $params['excludeId'] = $excludeTemplateId;
        }
        $rows = $this->query($sql, $params);
        return array_map(function ($r) {
            return $r['countryCode'];
        }, $rows);
    }
}
