<?php
/**
 * 后台「销售管理」「销售」判定与客户数据范围（View All Clients 权限）
 * 销售管理 = Sales Manager 角色 OR page_saleslist_view
 * 销售 = Sales 角色 OR page_salesdashboard_view
 * 仅销售 = 销售 且 非 销售管理
 */

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminPermission.php';

class AdminSalesPermission {

    /**
     * 当前登录用户是否为「仅销售」（历史接口，部分旧代码仍引用）
     * @return array ['is_sales_only' => bool, 'admin_user_id' => int]
     */
    public static function getCurrentSalesOnlyFilter() {
        $ctx = self::resolveSalesContext();
        return [
            'is_sales_only' => $ctx['is_sales_only'],
            'admin_user_id' => $ctx['admin_user_id'],
        ];
    }

    /**
     * Current administrator's sales-tool visibility.
     * Management and super admins can view every sales user; a sales user can
     * view only themselves. Daily-report permission is tracked independently.
     */
    public static function getCurrentSalesAccess(): array {
        $ctx = self::resolveSalesContext();
        $isSuperAdmin = (int)$ctx['role_id'] === 1;
        $canViewAllSales = $isSuperAdmin || $ctx['is_sales_management'];
        $canViewSales = $canViewAllSales || $ctx['is_sales'];
        $canViewDaily = false;
        if ((int)$ctx['admin_user_id'] > 0) {
            $canViewDaily = (new AdminPermission())->userHasPermission(
                (int)$ctx['admin_user_id'],
                'page_dailyreport_readonly'
            );
        }

        return array_merge($ctx, [
            'is_super_admin' => $isSuperAdmin,
            'can_view_sales' => $canViewSales,
            'can_view_all_sales' => $canViewAllSales,
            'can_view_daily' => $canViewDaily,
        ]);
    }

    /**
     * 按页面 permissionKey 计算客户/IB 数据范围
     * @param string $pagePermissionKey 如 page_leads、page_iblist
     * @return array {
     *   scope: 'all'|'own'|'none',
     *   restrict_to_sales_id: int|null,
     *   admin_user_id: int,
     *   role_id: int
     * }
     */
    public static function getClientDataScopeForPage(string $pagePermissionKey) {
        $ctx = self::resolveSalesContext();
        $userId = $ctx['admin_user_id'];
        $roleId = $ctx['role_id'];

        if ($userId <= 0) {
            return self::scopeResult('none', 0, $roleId);
        }

        if ($roleId === 1) {
            return self::scopeResult('all', $userId, $roleId);
        }

        $permModel = new AdminPermission();
        $viewAllKey = $pagePermissionKey;
        if (substr($pagePermissionKey, -16) !== '_viewallclients') {
            $viewAllKey = $pagePermissionKey . '_viewallclients';
        }

        if ($permModel->userHasPermission($userId, $viewAllKey)) {
            return self::scopeResult('all', $userId, $roleId);
        }

        if ($ctx['is_sales']) {
            return self::scopeResult('own', $userId, $roleId);
        }

        return self::scopeResult('none', $userId, $roleId);
    }

    /**
     * sales_bind 直接绑定的 clientId 子查询 SQL（用于 clientUsers.id / 交易的 clientId）
     */
    public static function salesBindClientIdSubquerySql(string $clientIdColumn, string $paramName = 'restrict_to_sales_id'): string {
        return " {$clientIdColumn} IN (SELECT clientId FROM sales_bind WHERE salesId = :{$paramName}) ";
    }

    /**
     * sales_bind 直接绑定的 IB（ibPartners.userId = sales_bind.clientId）
     */
    public static function salesBindIbUserIdSubquerySql(string $ibUserIdColumn = 'ib.userId', string $paramName = 'restrict_to_sales_id'): string {
        return " {$ibUserIdColumn} IN (SELECT clientId FROM sales_bind WHERE salesId = :{$paramName}) ";
    }

    /**
     * sales_bind 直接绑定的 ibPartner.id
     */
    public static function salesBindIbPartnerIdSubquerySql(string $ibPartnerIdColumn = 'ib.id', string $paramName = 'restrict_to_sales_id'): string {
        return " {$ibPartnerIdColumn} IN (
            SELECT ib_inner.id FROM ibPartners ib_inner
            INNER JOIN sales_bind sb ON sb.clientId = ib_inner.userId
            WHERE sb.salesId = :{$paramName}
        ) ";
    }

    private static function scopeResult(string $scope, int $adminUserId, int $roleId): array {
        return [
            'scope' => $scope,
            'restrict_to_sales_id' => $scope === 'own' ? $adminUserId : null,
            'admin_user_id' => $adminUserId,
            'role_id' => $roleId,
        ];
    }

    /**
     * @return array {
     *   admin_user_id: int,
     *   role_id: int,
     *   is_sales: bool,
     *   is_sales_management: bool,
     *   is_sales_only: bool
     * }
     */
    private static function resolveSalesContext(): array {
        $current = AuthMiddleware::getCurrentUser();
        $userId = isset($current['userId']) ? (int)$current['userId'] : 0;
        $roleId = isset($current['roleId']) ? (int)$current['roleId'] : 0;

        if ($userId <= 0) {
            return [
                'admin_user_id' => 0,
                'role_id' => 0,
                'is_sales' => false,
                'is_sales_management' => false,
                'is_sales_only' => false,
            ];
        }

        $config = require __DIR__ . '/../config/app.php';
        $special = $config['special_roles'] ?? [];
        $salesManagerRoleId = (int)($special['sales_manager_role_id'] ?? 0);
        $salesRoleId = (int)($special['sales_role_id'] ?? 0);

        $permModel = new AdminPermission();
        $salesListPerm = $permModel->findByKey('page_saleslist_view');
        $salesDashboardPerm = $permModel->findByKey('page_salesdashboard_view');
        $salesListViewPid = $salesListPerm ? (int)($salesListPerm['id'] ?? 0) : 0;
        $salesDashboardViewPid = $salesDashboardPerm ? (int)($salesDashboardPerm['id'] ?? 0) : 0;

        $userModel = new AdminUser();
        $hasSalesListViewed = $salesListViewPid > 0 && $userModel->hasRolePermission($userId, $salesListViewPid);
        $hasSalesDashboardViewed = $salesDashboardViewPid > 0 && $userModel->hasRolePermission($userId, $salesDashboardViewPid);

        $isSalesManagement = ($roleId === $salesManagerRoleId) || $hasSalesListViewed;
        $isSales = ($roleId === $salesRoleId) || $hasSalesDashboardViewed;
        $isSalesOnly = $isSales && !$isSalesManagement;

        return [
            'admin_user_id' => $userId,
            'role_id' => $roleId,
            'is_sales' => $isSales,
            'is_sales_management' => $isSalesManagement,
            'is_sales_only' => $isSalesOnly,
        ];
    }
}
