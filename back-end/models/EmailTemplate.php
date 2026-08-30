<?php
/**
 * 邮件模板模型
 */

require_once __DIR__ . '/BaseModel.php';

class EmailTemplate extends BaseModel {
    protected $table = 'emailTemplates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'templateKey',
        'templateName',
        'category',
        'emailSubject',
        'emailBody',
        'recipientType',
        'description',
        'variables',
        'isActive',
        'createdAt',
        'updatedAt'
    ];

    /**
     * 根据key获取模板
     * @param string $key 模板key
     * @return array|null
     */
    public function getByKey($key) {
        $sql = "SELECT * FROM {$this->table} WHERE templateKey = :key AND isActive = 1 LIMIT 1";
        $result = $this->db->fetchOne($sql, ['key' => $key]);

        if ($result && $result['variables']) {
            $result['variables'] = json_decode($result['variables'], true);
        }

        return $result ?: null;
    }

    /**
     * 获取所有模板（分页）
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @param array $filters 筛选条件
     * @return array
     */
    public function getTemplates($page = 1, $perPage = 10, $filters = []) {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['category'])) {
            $conditions[] = "category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['recipientType'])) {
            $conditions[] = "recipientType = :recipientType";
            $params['recipientType'] = $filters['recipientType'];
        }

        if (isset($filters['isActive'])) {
            $conditions[] = "isActive = :isActive";
            $params['isActive'] = (int)$filters['isActive'];
        }

        if (!empty($filters['search'])) {
            // 使用不同的参数名避免参数绑定冲突
            $conditions[] = "(templateName LIKE :search1 OR templateKey LIKE :search2)";
            $searchValue = '%' . $filters['search'] . '%';
            $params['search1'] = $searchValue;
            $params['search2'] = $searchValue;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $countResult = $this->db->fetchOne($countSql, $params);
        $total = $countResult ? (int)$countResult['total'] : 0;

        // 获取数据 - LIMIT 和 OFFSET 需要直接拼接，不能使用参数绑定
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY category, templateName LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $items = $this->db->fetchAll($sql, $params);

        // 解析variables JSON
        foreach ($items as &$item) {
            if ($item['variables']) {
                $item['variables'] = json_decode($item['variables'], true);
            }
        }

        return [
            'items' => $items,
            'total' => (int)$total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * 获取所有分类
     * @return array
     */
    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM {$this->table} ORDER BY category";
        $results = $this->db->fetchAll($sql);
        return array_column($results, 'category');
    }

    /**
     * 创建模板
     * @param array $data
     * @return int 新创建的ID
     */
    public function create($data) {
        if (isset($data['variables']) && is_array($data['variables'])) {
            $data['variables'] = json_encode($data['variables'], JSON_UNESCAPED_UNICODE);
        }

        return parent::create($data);
    }

    /**
     * 更新模板
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        if (isset($data['variables']) && is_array($data['variables'])) {
            $data['variables'] = json_encode($data['variables'], JSON_UNESCAPED_UNICODE);
        }

        return parent::update($id, $data);
    }

    /**
     * 替换模板变量
     * @param string $template 模板内容
     * @param array $variables 变量数组
     * @return string
     */
    public static function replaceVariables($template, $variables = []) {
        foreach ($variables as $key => $value) {
            // 跳过数组类型的值，只处理标量类型（字符串、数字、布尔值）
            if (is_array($value)) {
                continue;
            }

            // 将值转换为字符串
            $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
            $template = str_replace('{{' . $key . '}}', $stringValue, $template);
        }
        return $template;
    }

    /**
     * @param int[] $ids
     * @return array<int,string> id => templateName
     */
    public function findNamesByIds(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, templateName FROM {$this->table} WHERE id IN ({$placeholders})";
        $rows = $this->db->fetchAll($sql, $ids);
        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['id'] ?? 0)] = (string) ($row['templateName'] ?? '');
        }
        return $map;
    }
}
