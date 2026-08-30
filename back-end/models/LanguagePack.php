<?php
/**
 * 语言包模型
 */

require_once __DIR__ . '/BaseModel.php';

class LanguagePack extends BaseModel {
    protected $table = 'languagePacks';
    protected $primaryKey = 'id';

    protected $fillable = [
        'languageCode', 'languageName', 'flagEmoji',
        'translations', 'isEnabled', 'isDefault', 'isBuiltin'
    ];

    /**
     * 语言列表用到的列。
     * 刻意不包含 translations（每个语言包 50KB+），列表接口只需要展示用的元数据，
     * 完整翻译内容通过 getTranslations() 按单个语言按需获取。
     */
    private const LIST_COLUMNS = 'id, languageCode, languageName, flagEmoji, isEnabled, isDefault, isBuiltin';

    /**
     * 覆盖 findAll 方法，添加布尔字段转换
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $languages = parent::findAll($conditions, $orderBy, $limit, $offset);
        return $this->convertBooleanFields($languages);
    }

    /**
     * 构造语言列表查询语句（无副作用，便于测试）
     */
    public function buildLanguageListSql($enabledOnly = false) {
        $sql = "SELECT " . self::LIST_COLUMNS . " FROM {$this->table}";
        if ($enabledOnly) {
            $sql .= " WHERE isEnabled = 1";
        }

        return $sql . " ORDER BY isDefault DESC, languageName ASC";
    }

    /**
     * 获取语言列表（不含 translations）
     */
    public function getLanguageList($enabledOnly = false) {
        return $this->convertBooleanFields($this->db->fetchAll($this->buildLanguageListSql($enabledOnly)));
    }

    /**
     * 获取所有启用的语言
     */
    public function getEnabledLanguages() {
        return $this->getLanguageList(true);
    }

    /**
     * 获取默认语言
     */
    public function getDefaultLanguage() {
        $lang = $this->findOne(['isDefault' => 1]);
        if (!$lang) {
            // 如果没有设置默认语言，返回英文
            $lang = $this->findOne(['languageCode' => 'en']);
        }
        return $this->convertBooleanFields($lang);
    }

    /**
     * 转换布尔字段为真正的布尔值
     */
    private function convertBooleanFields($data) {
        if (empty($data)) {
            return $data;
        }

        // 单个记录
        if (isset($data['id'])) {
            $data['isEnabled'] = (bool)(int)$data['isEnabled'];
            $data['isDefault'] = (bool)(int)$data['isDefault'];
            $data['isBuiltin'] = (bool)(int)$data['isBuiltin'];
            return $data;
        }

        // 多个记录
        foreach ($data as &$lang) {
            $lang['isEnabled'] = (bool)(int)$lang['isEnabled'];
            $lang['isDefault'] = (bool)(int)$lang['isDefault'];
            $lang['isBuiltin'] = (bool)(int)$lang['isBuiltin'];
        }

        return $data;
    }

    /**
     * 根据语言代码获取
     */
    public function getByCode($languageCode) {
        return $this->findOne([
            'languageCode' => $languageCode,
            'isEnabled' => 1
        ]);
    }

    /**
     * 设置默认语言
     */
    public function setDefault($languageCode) {
        // 取消所有默认设置
        $sql = "UPDATE {$this->table} SET isDefault = 0";
        $this->db->query($sql);

        // 设置新的默认语言
        $lang = $this->findOne(['languageCode' => $languageCode]);
        if ($lang) {
            return $this->update($lang['id'], ['isDefault' => 1]);
        }

        return false;
    }

    /**
     * 切换启用状态
     */
    public function toggleEnabled($id, $isEnabled) {
        // 检查是否是内置语言且是默认语言
        $lang = $this->findById($id);
        if ($lang && $lang['isDefault'] && !$isEnabled) {
            throw new Exception('Cannot disable the default language. Please set another language as default first.');
        }

        return $this->update($id, ['isEnabled' => $isEnabled]);
    }

    /**
     * 上传语言包
     */
    public function uploadLanguagePack($data) {
        // 验证必需字段
        $requiredFields = ['languageCode', 'languageName', 'translations'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // 检查语言代码是否已存在
        $existing = $this->findOne(['languageCode' => $data['languageCode']]);
        if ($existing) {
            throw new Exception('Language pack already exists. Use update instead.');
        }

        // 如果translations是数组，转换为JSON
        if (is_array($data['translations'])) {
            $data['translations'] = json_encode($data['translations'], JSON_UNESCAPED_UNICODE);
        }

        return $this->create($data);
    }

    /**
     * 更新语言包
     */
    public function updateLanguagePack($languageCode, $data) {
        $lang = $this->findOne(['languageCode' => $languageCode]);
        if (!$lang) {
            throw new Exception('Language pack not found');
        }

        // 如果translations是数组，转换为JSON
        if (isset($data['translations']) && is_array($data['translations'])) {
            $data['translations'] = json_encode($data['translations'], JSON_UNESCAPED_UNICODE);
        }

        return $this->update($lang['id'], $data);
    }

    /**
     * 删除语言包
     */
    public function deleteLanguagePack($id) {
        $lang = $this->findById($id);

        // 不能删除内置语言
        if ($lang && $lang['isBuiltin']) {
            throw new Exception('Cannot delete built-in language packs');
        }

        // 不能删除默认语言
        if ($lang && $lang['isDefault']) {
            throw new Exception('Cannot delete the default language. Please set another language as default first.');
        }

        return $this->delete($id);
    }

    /**
     * 获取翻译内容
     */
    public function getTranslations($languageCode) {
        $lang = $this->getByCode($languageCode);
        if ($lang) {
            return json_decode($lang['translations'], true);
        }

        // 如果找不到，返回英文
        $defaultLang = $this->getByCode('en');
        return $defaultLang ? json_decode($defaultLang['translations'], true) : [];
    }
}
