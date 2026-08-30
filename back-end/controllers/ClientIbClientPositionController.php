<?php

require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

class ClientIbClientPositionController
{
    private $ibPartnerModel;
    private $orderModel;

    public function __construct()
    {
        $this->ibPartnerModel = new IbPartner();
        $this->orderModel = new Order();
    }

    public function positions()
    {
        $currentUserId = (int) $this->getCurrentUserId();
        $ibPartner = $this->getReportIbPartner($currentUserId);

        $statusMap = ['open' => 0, 'pending' => 1, 'closed' => 2];
        $statusKey = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'closed';
        if (!isset($statusMap[$statusKey])) {
            $statusKey = 'closed';
        }

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $pageSize = isset($_GET['pageSize']) ? (int) $_GET['pageSize'] : 20;
        $pageSize = max(1, min(200, $pageSize));
        $sort = isset($_GET['sort']) ? (string) $_GET['sort'] : ($statusKey === 'closed' ? 'CloseTime' : 'OpenTime');
        $sortOrder = (isset($_GET['sortOrder']) && strtoupper($_GET['sortOrder']) === 'ASC') ? 'ASC' : 'DESC';

        $filters = ['trading_status' => $statusMap[$statusKey]];
        if ($statusKey !== 'closed') {
            $filters['date_field'] = 'opentime';
        }
        if (!empty($_GET['keywords'])) {
            $filters['keywords'] = (string) $_GET['keywords'];
        }
        $side = isset($_GET['side']) ? strtolower(trim((string) $_GET['side'])) : '';
        if ($side === 'buy' || $side === 'sell') {
            $filters['cmd_in'] = $side === 'buy' ? [0, 2, 4, 8] : [1, 3, 5, 9];
        }
        if (!empty($_GET['periodFrom'])) {
            $filters['periodFrom'] = (string) $_GET['periodFrom'];
        }
        if (!empty($_GET['periodTo'])) {
            $filters['periodTo'] = (string) $_GET['periodTo'];
        }

        $emptyResult = [
            'items' => [],
            'totals' => $this->orderModel->getOrderTotalsByLogins([], [], $filters),
            'pagination' => [
                'currentPage' => $page,
                'pageSize' => $pageSize,
                'total' => 0,
                'hasMore' => false,
            ],
        ];

        try {
            $userIds = $this->collectTreeUserIds((int) $ibPartner['id']);
            if (empty($userIds)) {
                Response::success($emptyResult, 'Client positions loaded');
                return;
            }

            $accounts = $this->fetchDownlineAccounts($userIds);
            $logins = [];
            $platformKeys = [];
            $accountMeta = [];
            foreach ($accounts as $account) {
                $login = trim((string) ($account['login'] ?? ''));
                $platformKey = strtolower(trim((string) ($account['platformKey'] ?? '')));
                if ($login === '' || $platformKey === '') {
                    continue;
                }
                if (!in_array($login, $logins, true)) {
                    $logins[] = $login;
                }
                if (!in_array($platformKey, $platformKeys, true)) {
                    $platformKeys[] = $platformKey;
                }
                $accountMeta[$platformKey . '|' . $login] = [
                    'ClientName' => trim((string) ($account['clientName'] ?? '')),
                    'PlatformName' => $account['platformName'] ?? '',
                    'AccountNumber' => $account['accountNumber'] ?? '',
                    'Currency' => $account['accountCurrency'] ?? '',
                    'AccountType' => $account['accountType'] ?? '',
                ];
            }

            if (empty($logins)) {
                Response::success($emptyResult, 'Client positions loaded');
                return;
            }

            $filterLogin = isset($_GET['login']) ? trim((string) $_GET['login']) : '';
            $filterPlatformKey = isset($_GET['platformKey'])
                ? strtolower(trim((string) $_GET['platformKey']))
                : '';
            if ($filterLogin !== '') {
                $matchedLogins = [];
                $matchedPlatformKeys = [];
                $matchedMeta = [];
                foreach ($accountMeta as $metaKey => $meta) {
                    $parts = explode('|', $metaKey, 2);
                    $metaPlatform = $parts[0] ?? '';
                    $metaLogin = $parts[1] ?? '';
                    if ($metaLogin !== $filterLogin) {
                        continue;
                    }
                    if ($filterPlatformKey !== '' && $metaPlatform !== $filterPlatformKey) {
                        continue;
                    }
                    $matchedLogins[] = $metaLogin;
                    if ($metaPlatform !== '' && !in_array($metaPlatform, $matchedPlatformKeys, true)) {
                        $matchedPlatformKeys[] = $metaPlatform;
                    }
                    $matchedMeta[$metaKey] = $meta;
                }
                if (empty($matchedLogins)) {
                    Response::success($emptyResult, 'Client positions loaded');
                    return;
                }
                $logins = array_values(array_unique($matchedLogins));
                $platformKeys = $matchedPlatformKeys;
                $accountMeta = $matchedMeta;
            }

            $dbResult = $this->orderModel->getOrderHistoryByLogins(
                $logins,
                $platformKeys,
                $filters,
                $page,
                $pageSize,
                $sort,
                $sortOrder
            );

            $items = (isset($dbResult['items']) && is_array($dbResult['items'])) ? $dbResult['items'] : [];
            foreach ($items as &$item) {
                $metaKey = strtolower((string) ($item['trading_platforms_key'] ?? '')) . '|' . (string) ($item['Login'] ?? '');
                $meta = $accountMeta[$metaKey] ?? [
                    'ClientName' => '',
                    'PlatformName' => '',
                    'AccountNumber' => '',
                    'Currency' => '',
                    'AccountType' => '',
                ];
                $item = array_merge($item, $meta);
                $item['Lots'] = (float) ($item['Volume'] ?? 0) / Order::VOLUME_PER_LOT;
                $item['NetProfit'] = (float) ($item['Profit'] ?? 0)
                    + (float) ($item['Commission'] ?? 0)
                    + (float) ($item['Storage'] ?? 0)
                    + (float) ($item['Taxes'] ?? 0);
            }
            unset($item);

            Response::success([
                'items' => $items,
                'totals' => $this->orderModel->getOrderTotalsByLogins($logins, $platformKeys, $filters),
                'pagination' => [
                    'currentPage' => $dbResult['page'] ?? $page,
                    'pageSize' => $dbResult['pageSize'] ?? $pageSize,
                    'total' => $dbResult['total'] ?? 0,
                    'hasMore' => $dbResult['hasMore'] ?? false,
                ],
            ], 'Client positions loaded');
        } catch (Exception $e) {
            Logger::error('Failed to fetch IB client positions: ' . $e->getMessage());
            Response::error('Failed to fetch client positions', 500);
        }
    }

    private function getCurrentUserId()
    {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $userId;
        }
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Unauthorized - No token provided', 401);
        }
        try {
            $payload = JWT::decode($token);
            if (!isset($payload['userId'])) {
                Response::error('Invalid token - No user ID', 401);
            }
            return $payload['userId'];
        } catch (Exception $e) {
            Response::error('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    private function getReportIbPartner($currentUserId, $requestedIbPartnerId = null)
    {
        if ($requestedIbPartnerId === null) {
            $requestedIbPartnerId = (int) ($_GET['ibPartnerId'] ?? 0);
        }
        $requestedIbPartnerId = (int) $requestedIbPartnerId;
        $ibPartners = $this->ibPartnerModel->getAllByClientId($currentUserId);
        $approvedPartners = array_values(array_filter($ibPartners, function ($ibPartner) {
            return ($ibPartner['status'] ?? '') === IbPartner::STATUS_APPROVED;
        }));

        if (empty($approvedPartners)) {
            Response::forbidden('Not an approved IB');
        }

        $selectedId = (int) ($approvedPartners[0]['id'] ?? 0);
        if ($requestedIbPartnerId > 0) {
            $selectedId = 0;
            foreach ($approvedPartners as $ibPartner) {
                if ((int) ($ibPartner['id'] ?? 0) === $requestedIbPartnerId) {
                    $selectedId = $requestedIbPartnerId;
                    break;
                }
            }
            if ($selectedId <= 0) {
                Response::notFound('IB partner not found');
            }
        }

        $ibPartner = $this->ibPartnerModel->findById($selectedId);
        if (!$ibPartner || (int) ($ibPartner['userId'] ?? 0) !== (int) $currentUserId || ($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            Response::notFound('IB partner not found');
        }

        return $ibPartner;
    }

    private function collectTreeUserIds($ibPartnerId)
    {
        $bind = new IbPartnerBind();
        $ibIds = $bind->getDescendantIbPartnerIds($ibPartnerId, true);
        $clientIds = $bind->getClientIdsUnderIbTree($ibPartnerId);
        $userIds = [];
        foreach ($clientIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $userIds[$id] = true;
            }
        }
        $ibIds = array_values(array_unique(array_filter(array_map('intval', $ibIds), function ($id) {
            return $id > 0;
        })));
        if (!empty($ibIds)) {
            list($placeholders, $params) = $this->inParams($ibIds, 'ib');
            $rows = Database::getInstance()->fetchAll(
                "SELECT userId FROM ibPartners WHERE id IN ({$placeholders}) AND userId IS NOT NULL",
                $params
            );
            foreach ($rows as $row) {
                $id = (int) ($row['userId'] ?? 0);
                if ($id > 0) {
                    $userIds[$id] = true;
                }
            }
        }
        return array_keys($userIds);
    }

    private function fetchDownlineAccounts(array $userIds)
    {
        if (empty($userIds)) {
            return [];
        }
        list($placeholders, $params) = $this->inParams($userIds, 'u');
        $sql = "SELECT
                    ta.userId AS clientId,
                    TRIM(CONCAT_WS(' ', cu.firstName, cu.lastName)) AS clientName,
                    taea.providerAccountId AS login,
                    LOWER(tp.platformKey) AS platformKey,
                    tp.displayName AS platformName,
                    ta.accountNumber AS accountNumber,
                    ta.accountCurrency AS accountCurrency,
                    ta.accountType AS accountType
                FROM tradingAccounts ta
                INNER JOIN tradingAccountExternalAccounts taea
                    ON taea.id = (
                        SELECT MAX(x.id)
                        FROM tradingAccountExternalAccounts x
                        WHERE x.tradingAccountId = ta.id
                          AND x.providerAccountId IS NOT NULL
                          AND x.providerAccountId != ''
                    )
                INNER JOIN clientUsers cu ON cu.id = ta.userId
                LEFT JOIN tradingPlatforms tp ON tp.id = ta.platformId
                WHERE ta.userId IN ({$placeholders})";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    private function inParams(array $values, $prefix)
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $value;
        }
        return [implode(',', $placeholders), $params];
    }
}
