<?php
/**
 * 管理员用户模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminUser extends BaseModel {
    /** 超级管理员角色：隐式拥有全部权限，没有 adminRolePermissions 记录 */
    const SUPER_ADMIN_ROLE_ID = 1;

    protected $table = 'adminUsers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'username', 'email', 'passwordHash', 'fullName',
        'avatarInitials', 'avatarColor', 'roleId', 'departmentId', 'positionId', 'status',
        'isLocked', 'failedLoginAttempts', 'lastLoginAt', 'lastLoginIp',
        'rememberToken', 'passwordChangedAt', 'mustChangePassword',
        'twoFactorEnabled', 'twoFactorSecret', 'createdBy', 'updatedBy',
        'deletedAt'
    ];

    protected $hidden = ['passwordHash', 'twoFactorSecret', 'rememberToken'];

    /**
     * 获取完整用户信息（包含角色和资料）
     */
    public function getUserFullInfo($userId) {
        $sql = "SELECT * FROM vAdminUsersFull WHERE id = :id LIMIT 1";
        return $this->db->fetchOne($sql, ['id' => $userId]);
    }

    /**
     * 根据用户名查找
     */
    public function findByUsername($username) {
        return $this->findOne(['username' => $username, 'deletedAt' => null]);
    }

    /**
     * 根据邮箱查找
     */
    public function findByEmail($email) {
        return $this->findOne(['email' => $email, 'deletedAt' => null]);
    }

    /**
     * 根据用户名或邮箱查找（用于登录）
     */
    public function findByCredentials($usernameOrEmail) {
        $sql = "SELECT * FROM {$this->table}
                WHERE (username = :username OR email = :email)
                AND deletedAt IS NULL
                LIMIT 1";
        return $this->db->fetchOne($sql, [
            'username' => $usernameOrEmail,
            'email' => $usernameOrEmail
        ]);
    }

    /**
     * 验证密码
     */
    public function verifyPassword($inputPassword, $hashedPassword) {
        return password_verify($inputPassword, $hashedPassword);
    }

    /**
     * 哈希密码
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLogin($userId, $ipAddress) {
        return $this->db->update(
            $this->table,
            [
                'lastLoginAt' => date('Y-m-d H:i:s'),
                'lastLoginIp' => $ipAddress,
                'failedLoginAttempts' => 0
            ],
            'id = :id',
            ['id' => $userId]
        );
    }

    /**
     * 增加失败登录次数
     */
    public function incrementFailedAttempts($userId) {
        $sql = "UPDATE {$this->table}
                SET failedLoginAttempts = failedLoginAttempts + 1
                WHERE id = :id";
        $this->db->query($sql, ['id' => $userId]);

        // 检查是否需要锁定账户
        $user = $this->findById($userId);
        $config = require __DIR__ . '/../config/app.php';
        $maxAttempts = $config['security']['max_login_attempts'];

        if ($user && $user['failedLoginAttempts'] >= $maxAttempts) {
            $this->lockAccount($userId);
        }
    }

    /**
     * 锁定账户
     */
    public function lockAccount($userId) {
        return $this->update($userId, ['isLocked' => 1]);
    }

    /**
     * 解锁账户
     */
    public function unlockAccount($userId) {
        return $this->update($userId, [
            'isLocked' => 0,
            'failedLoginAttempts' => 0
        ]);
    }

    /**
     * 检查账户是否被锁定
     */
    public function isLocked($userId) {
        $user = $this->findById($userId);
        return $user && $user['isLocked'] == 1;
    }

    /**
     * 获取活跃用户列表
     */
    public function getActiveUsers() {
        return $this->findAll([
            'status' => 'active',
            'deletedAt' => null
        ], 'createdAt DESC');
    }

    /**
     * 搜索用户
     */
    public function search($keyword, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$this->table}
                WHERE (username LIKE :keyword
                   OR email LIKE :keyword
                   OR fullName LIKE :keyword)
                AND deletedAt IS NULL
                ORDER BY createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count FROM {$this->table}
                     WHERE (username LIKE :keyword
                        OR email LIKE :keyword
                        OR fullName LIKE :keyword)
                     AND deletedAt IS NULL";

        $params = ['keyword' => "%{$keyword}%"];

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $this->hideFields($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 按角色 ID 分页查询，支持关键词搜索（Name、id）
     */
    public function searchSalesByRole($roleId, $keyword, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;
        $perPage = (int)$perPage;
        if ($perPage < 1) $perPage = 10;

        $like = "%{$keyword}%";
        $idExact = ctype_digit(trim($keyword)) ? (int)$keyword : null;
        $whereExtra = "AND (username LIKE :like OR fullName LIKE :like OR email LIKE :like";
        $params = ['roleId' => $roleId, 'like' => $like];
        if ($idExact !== null) {
            $whereExtra .= " OR id = :idExact";
            $params['idExact'] = $idExact;
        }
        $whereExtra .= ")";

        $sql = "SELECT * FROM {$this->table}
                WHERE roleId = :roleId AND deletedAt IS NULL {$whereExtra}
                ORDER BY createdAt DESC
                LIMIT {$perPage} OFFSET " . (int)$offset;
        $countSql = "SELECT COUNT(*) as count FROM {$this->table}
                     WHERE roleId = :roleId AND deletedAt IS NULL {$whereExtra}";

        $items = $this->db->fetchAll($sql, $params);
        $total = (int)$this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $this->hideFields($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * Sales 角色统计：总数、Active、Inactive
     */
    public function getSalesRoleStats($roleId) {
        $sql = "SELECT
            COUNT(*) AS totalSales,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS activeSales,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactiveSales
            FROM {$this->table}
            WHERE roleId = :roleId AND deletedAt IS NULL";
        $row = $this->db->fetchOne($sql, ['roleId' => $roleId]);
        return [
            'totalSales' => (int)($row['totalSales'] ?? 0),
            'activeSales' => (int)($row['activeSales'] ?? 0),
            'inactiveSales' => (int)($row['inactiveSales'] ?? 0),
        ];
    }

    /**
     * 「销售」列表条件：roleId = Sales、角色拥有 Sales Dashboard View 权限，或超级管理员
     * 用于 Sales List 列表、Assign 下拉等
     *
     * Super admins (roleId 1) hold every permission implicitly and have no
     * adminRolePermissions rows, so a permission-id join alone never matches them.
     * Without this they cannot be picked as a sales representative anywhere.
     */
    private function salesListWhereClause($salesRoleId, $salesDashboardViewPermissionId) {
        return "u.deletedAt IS NULL AND ("
            . "u.roleId = :salesRoleId"
            . " OR u.roleId = " . self::SUPER_ADMIN_ROLE_ID
            . " OR u.roleId IN (SELECT roleId FROM adminRolePermissions WHERE permissionId = :pid))";
    }

    /**
     * 「销售」用户列表（不分页，供 Daily Report 等按同一口径取全量）
     * @param string   $search     可选，匹配 username / fullName / email
     * @param int|null $onlyUserId 可选，仅返回该用户（销售只能看自己）
     */
    public function getSalesUsers($salesRoleId, $salesDashboardViewPermissionId, $search = '', $onlyUserId = null) {
        $where = $this->salesListWhereClause($salesRoleId, $salesDashboardViewPermissionId);
        $params = ['salesRoleId' => $salesRoleId, 'pid' => $salesDashboardViewPermissionId];

        if ($onlyUserId !== null) {
            $where .= ' AND u.id = :onlyUserId';
            $params['onlyUserId'] = (int)$onlyUserId;
        }

        if (trim((string)$search) !== '') {
            $where .= ' AND (u.username LIKE :like OR u.fullName LIKE :like OR u.email LIKE :like)';
            $params['like'] = '%' . trim($search) . '%';
        }

        $sql = "SELECT u.id, u.username, u.fullName, u.email, u.status
                FROM {$this->table} u
                WHERE {$where}
                ORDER BY COALESCE(NULLIF(u.fullName, ''), u.username) ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 分页查询「销售」用户列表（支持搜索）
     */
    public function getSalesListPaginate($page, $perPage, $search, $salesRoleId, $salesDashboardViewPermissionId) {
        $perPage = (int)$perPage;
        if ($perPage < 1) $perPage = 10;
        if ($perPage > 9999) $perPage = 9999;
        $offset = ($page - 1) * $perPage;
        $where = $this->salesListWhereClause($salesRoleId, $salesDashboardViewPermissionId);
        $params = ['salesRoleId' => $salesRoleId, 'pid' => $salesDashboardViewPermissionId];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = " AND (u.username LIKE :like OR u.fullName LIKE :like OR u.email LIKE :like)";
            $params['like'] = '%' . $search . '%';
            if (ctype_digit(trim($search))) {
                $searchSql .= " OR u.id = :idExact";
                $params['idExact'] = (int)$search;
            }
        }
        $sql = "SELECT u.* FROM {$this->table} u WHERE {$where}{$searchSql} ORDER BY u.createdAt DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $countSql = "SELECT COUNT(*) AS cnt FROM {$this->table} u WHERE {$where}{$searchSql}";
        $items = $this->db->fetchAll($sql, $params);
        $total = (int)($this->db->fetchOne($countSql, $params)['cnt'] ?? 0);
        return [
            'items' => $this->hideFields($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 「销售」统计：总数、Active、Inactive
     */
    public function getSalesListStats($salesRoleId, $salesDashboardViewPermissionId) {
        $where = $this->salesListWhereClause($salesRoleId, $salesDashboardViewPermissionId);
        $sql = "SELECT
            COUNT(*) AS totalSales,
            SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS activeSales,
            SUM(CASE WHEN u.status = 'inactive' THEN 1 ELSE 0 END) AS inactiveSales
            FROM {$this->table} u
            WHERE {$where}";
        $row = $this->db->fetchOne($sql, ['salesRoleId' => $salesRoleId, 'pid' => $salesDashboardViewPermissionId]);
        return [
            'totalSales' => (int)($row['totalSales'] ?? 0),
            'activeSales' => (int)($row['activeSales'] ?? 0),
            'inactiveSales' => (int)($row['inactiveSales'] ?? 0),
        ];
    }

    /**
     * 获取「销售」用户 ID 列表（仅 active，用于 Assign 下拉）
     */
    public function getSalesListUserIdsForAssign($salesRoleId, $salesDashboardViewPermissionId) {
        $where = $this->salesListWhereClause($salesRoleId, $salesDashboardViewPermissionId);
        $sql = "SELECT u.id, u.fullName, u.email FROM {$this->table} u WHERE {$where} AND u.status = 'active' ORDER BY u.fullName";
        return $this->db->fetchAll($sql, ['salesRoleId' => $salesRoleId, 'pid' => $salesDashboardViewPermissionId]);
    }

    /**
     * 判断用户是否通过角色拥有某权限（仅查 adminRolePermissions）
     */
    public function hasRolePermission($userId, $permissionId) {
        $u = $this->db->fetchOne("SELECT roleId FROM {$this->table} WHERE id = :id LIMIT 1", ['id' => (int)$userId]);
        if (!$u || empty($u['roleId'])) return false;
        $r = $this->db->fetchOne("SELECT 1 FROM adminRolePermissions WHERE roleId = :roleId AND permissionId = :permissionId LIMIT 1", [
            'roleId' => (int)$u['roleId'],
            'permissionId' => (int)$permissionId
        ]);
        return $r !== false && !empty($r);
    }

    /**
     * 积分商城「兑换提醒」管理员选择器：未删除且 status=active（不含 isLocked 过滤）
     *
     * @return array{items: list<array{id:int,username:string,email:string,fullName:?string}>, total:int}
     */
    public function searchActiveAdminsForPointsMallNotifyPicker($keyword, $page, $perPage) {
        $page = max(1, (int) $page);
        $perPage = max(1, min(200, (int) $perPage));
        $offset = ($page - 1) * $perPage;

        $where = 'deletedAt IS NULL AND status = :st';
        $params = ['st' => 'active'];
        $kw = trim((string) $keyword);
        if ($kw !== '') {
            $where .= ' AND (username LIKE :kw OR email LIKE :kw2 OR fullName LIKE :kw3';
            $like = '%' . $kw . '%';
            $params['kw'] = $like;
            $params['kw2'] = $like;
            $params['kw3'] = $like;
            if (ctype_digit($kw)) {
                $where .= ' OR id = :idex';
                $params['idex'] = (int) $kw;
            }
            $where .= ')';
        }

        $countSql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE {$where}";
        $total = (int) ($this->db->fetchOne($countSql, $params)['c'] ?? 0);

        $sql = "SELECT id, username, email, fullName FROM {$this->table}
                WHERE {$where}
                ORDER BY fullName ASC, username ASC, id ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return ['items' => $items ?: [], 'total' => $total];
    }
}
