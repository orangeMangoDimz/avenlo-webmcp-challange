<?php

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/IbProgramSetting.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../utils/Response.php';

class WebMcpIbController {
    private const MAX_ID = 2147483647;
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_CODE_LENGTH = 64;
    private const MAX_PAGE = 1000;
    private const MAX_LIMIT = 50;

    private $ibModel;
    private $bindModel;
    private $clientModel;
    private $programSettingModel;

    public function __construct() {
        $this->ibModel = new IbPartner();
        $this->bindModel = new IbPartnerBind();
        $this->clientModel = new ClientUser();
        $this->programSettingModel = new IbProgramSetting();
    }

    public static function routeHandlers(): array {
        return [
            'admin/get-ib-partner' => 'getPartner',
            'admin/get-ib-network' => 'getNetwork',
            'admin/get-ib-network-stats' => 'getNetworkStats',
            'admin/get-ib-clients' => 'getClients',
            'admin/get-client-ib-upline' => 'getClientUpline'
        ];
    }

    public static function normalizeIbLookupInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['email', 'id', 'code'], 'an IB lookup');

        $provided = [];
        foreach (['email', 'id', 'code'] as $key) {
            if (array_key_exists($key, $input)) {
                $provided[] = $key;
            }
        }
        if (count($provided) !== 1) {
            throw new InvalidArgumentException('Exactly one of email, id, or code is required.');
        }

        $key = $provided[0];
        if ($key === 'email') {
            return ['email' => self::normalizeEmail($input[$key])];
        }
        if ($key === 'code') {
            if (!is_string($input[$key])) {
                throw new InvalidArgumentException('code must be a string.');
            }
            $code = trim($input[$key]);
            if ($code === '' || strlen($code) > self::MAX_CODE_LENGTH) {
                throw new InvalidArgumentException('code must be between 1 and 64 characters.');
            }
            return ['code' => $code];
        }

        return ['id' => self::normalizePositiveInteger($input[$key], 'id', self::MAX_ID)];
    }

    public static function normalizeClientLookupInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['email', 'id'], 'a client upline lookup');
        $provided = [];
        foreach (['email', 'id'] as $key) {
            if (array_key_exists($key, $input)) {
                $provided[] = $key;
            }
        }
        if (count($provided) !== 1) {
            throw new InvalidArgumentException('Exactly one of email or id is required.');
        }

        return $provided[0] === 'email'
            ? ['email' => self::normalizeEmail($input['email'])]
            : ['id' => self::normalizePositiveInteger($input['id'], 'id', self::MAX_ID)];
    }

    public static function normalizeNetworkInput(array $input, int $configuredMaxDepth): array {
        self::rejectUnsupportedKeys($input, ['email', 'id', 'code', 'maxDepth'], 'an IB network lookup');
        $lookup = self::normalizeIbLookupInput(array_intersect_key(
            $input,
            array_flip(['email', 'id', 'code'])
        ));

        $configuredMaxDepth = max(1, $configuredMaxDepth);
        if (!array_key_exists('maxDepth', $input)) {
            $lookup['maxDepth'] = $configuredMaxDepth;
            return $lookup;
        }

        $requested = self::normalizePositiveInteger(
            $input['maxDepth'],
            'maxDepth',
            IbProgramSetting::MAX_TIER_LEVEL_COUNT_SANITY
        );
        $lookup['maxDepth'] = min($requested, $configuredMaxDepth);
        return $lookup;
    }

    public static function normalizeClientsInput(array $input): array {
        self::rejectUnsupportedKeys(
            $input,
            ['email', 'id', 'code', 'relationship', 'page', 'limit'],
            'an IB clients lookup'
        );
        $lookup = self::normalizeIbLookupInput(array_intersect_key(
            $input,
            array_flip(['email', 'id', 'code'])
        ));

        if (!array_key_exists('relationship', $input) || !is_string($input['relationship'])) {
            throw new InvalidArgumentException('relationship is required and must be direct or all.');
        }
        $relationship = strtolower(trim($input['relationship']));
        if (!in_array($relationship, ['direct', 'all'], true)) {
            throw new InvalidArgumentException('relationship must be direct or all.');
        }

        return array_merge($lookup, [
            'relationship' => $relationship,
            'page' => self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE),
            'limit' => self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT)
        ]);
    }

    public static function projectIb(array $row): array {
        $name = trim((string)($row['ibName'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['companyName'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string)($row['email'] ?? ''));
        }

        $clientId = isset($row['userId']) ? (int)$row['userId'] : 0;
        $tierId = isset($row['tierLevelId']) ? (int)$row['tierLevelId'] : 0;
        $tierLevel = isset($row['tierLevel']) ? (int)$row['tierLevel'] : 0;
        $tierName = $row['tierLevelName'] ?? $row['tierName'] ?? null;

        return [
            'id' => (int)($row['id'] ?? 0),
            'clientId' => $clientId > 0 ? $clientId : null,
            'code' => self::nullableString($row['ibCode'] ?? $row['code'] ?? null),
            'name' => $name !== '' ? $name : null,
            'email' => self::nullableString($row['email'] ?? $row['contactEmail'] ?? null),
            'country' => self::nullableString($row['country'] ?? null),
            'status' => self::nullableString($row['status'] ?? null),
            'ibType' => self::nullableString($row['ibType'] ?? null),
            'tier' => [
                'id' => $tierId > 0 ? $tierId : null,
                'level' => $tierLevel > 0 ? $tierLevel : null,
                'name' => self::nullableString($tierName)
            ],
            'registeredAt' => $row['registrationDate'] ?? $row['registeredAt'] ?? null
        ];
    }

    public static function projectClient(array $row): array {
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            $name = trim(
                (string)($row['firstName'] ?? '') . ' ' .
                (string)($row['lastName'] ?? '')
            );
        }

        return [
            'id' => (int)($row['clientId'] ?? $row['id'] ?? 0),
            'name' => $name !== '' ? $name : null,
            'email' => self::nullableString($row['email'] ?? null),
            'country' => self::nullableString($row['country'] ?? null),
            'status' => self::nullableString($row['clientStatus'] ?? $row['status'] ?? null),
            'kycStatus' => self::nullableString($row['kycStatus'] ?? null),
            'registeredAt' => $row['clientRegistrationDate'] ?? $row['createdAt'] ?? null
        ];
    }

    public static function buildHierarchyFromRows(int $rootId, array $rows, int $maxDepth): array {
        $children = [];
        foreach ($rows as $row) {
            $parentId = (int)($row['parentId'] ?? 0);
            $id = (int)($row['id'] ?? 0);
            if ($parentId <= 0 || $id <= 0) {
                continue;
            }
            if (!isset($children[$parentId])) {
                $children[$parentId] = [];
            }
            $children[$parentId][] = $row;
        }
        foreach ($children as &$siblings) {
            usort($siblings, static function ($left, $right) {
                return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
            });
        }
        unset($siblings);

        $walk = function (int $parentId, int $depth, array $path) use (&$walk, $children, $maxDepth): array {
            if ($depth > $maxDepth || !isset($children[$parentId])) {
                return [];
            }
            $nodes = [];
            foreach ($children[$parentId] as $row) {
                $id = (int)$row['id'];
                if (isset($path[$id])) {
                    continue;
                }
                $nextPath = $path;
                $nextPath[$id] = true;
                $node = self::projectIb($row);
                $node['parentId'] = $parentId;
                $node['depth'] = $depth;
                $node['hasChildren'] = !empty($children[$id]);
                $node['children'] = $depth < $maxDepth
                    ? $walk($id, $depth + 1, $nextPath)
                    : [];
                $nodes[] = $node;
            }
            return $nodes;
        };

        return $walk($rootId, 1, [$rootId => true]);
    }

    public static function calculateNetworkTotals(
        array $depthMap,
        int $directClients,
        int $totalNetworkClients
    ): array {
        $directIbs = 0;
        $totalDescendantIbs = 0;
        foreach ($depthMap as $depth) {
            $depth = (int)$depth;
            if ($depth > 0) {
                $totalDescendantIbs++;
            }
            if ($depth === 1) {
                $directIbs++;
            }
        }

        return [
            'directIbs' => $directIbs,
            'totalDescendantIbs' => $totalDescendantIbs,
            'directClients' => max(0, $directClients),
            'totalNetworkClients' => max(0, $totalNetworkClients),
            'totalNetworkMembers' => $totalDescendantIbs + max(0, $totalNetworkClients)
        ];
    }

    public function getPartner(): void {
        $scope = $this->requireIbScope();
        $lookup = $this->requestIbLookup();
        $ib = $this->findVisibleIb($lookup, $scope);
        if (!$ib) {
            Response::notFound('IB partner not found');
        }
        Response::success(['ib' => self::projectIb($ib)]);
    }

    public function getNetwork(): void {
        $scope = $this->requireIbScope();
        try {
            $input = self::normalizeNetworkInput(
                $this->requestInput(['email', 'id', 'code', 'maxDepth']),
                $this->programSettingModel->getNetworkMaxDepth()
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $ib = $this->findVisibleIb($this->ibLookupFromInput($input), $scope);
        if (!$ib) {
            Response::notFound('IB partner not found');
        }
        $rows = $this->getVisibleNetworkRows($scope);
        Response::success([
            'ib' => self::projectIb($ib),
            'maxDepth' => $input['maxDepth'],
            'children' => self::buildHierarchyFromRows((int)$ib['id'], $rows, $input['maxDepth'])
        ]);
    }

    public function getNetworkStats(): void {
        $scope = $this->requireIbScope();
        $lookup = $this->requestIbLookup();
        $ib = $this->findVisibleIb($lookup, $scope);
        if (!$ib) {
            Response::notFound('IB partner not found');
        }

        $rootId = (int)$ib['id'];
        $maxDepth = $this->programSettingModel->getNetworkMaxDepth();
        $depthMap = $this->networkDepthMap($rootId, $this->getVisibleNetworkRows($scope), $maxDepth);
        $networkIds = array_keys($depthMap);
        $clientCounts = $this->countVisibleClients($networkIds, $rootId, $scope);

        Response::success([
            'ib' => self::projectIb($ib),
            'totals' => self::calculateNetworkTotals(
                $depthMap,
                $clientCounts['direct'],
                $clientCounts['total']
            )
        ]);
    }

    public function getClients(): void {
        $scope = $this->requireIbScope();
        try {
            $input = self::normalizeClientsInput(
                $this->requestInput(['email', 'id', 'code', 'relationship', 'page', 'limit'])
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $ib = $this->findVisibleIb($this->ibLookupFromInput($input), $scope);
        if (!$ib) {
            Response::notFound('IB partner not found');
        }

        $rootId = (int)$ib['id'];
        if ($input['relationship'] === 'direct') {
            $depthMap = [$rootId => 0];
        } else {
            $depthMap = $this->networkDepthMap(
                $rootId,
                $this->getVisibleNetworkRows($scope),
                $this->programSettingModel->getNetworkMaxDepth()
            );
        }

        $result = $this->queryVisibleClients($depthMap, $input['page'], $input['limit'], $scope);
        Response::success([
            'ib' => self::projectIb($ib),
            'relationship' => $input['relationship'],
            'clients' => $result['items'],
            'pagination' => self::pagination($input['page'], $input['limit'], $result['total'])
        ]);
    }

    public function getClientUpline(): void {
        $scope = $this->requireIbScope();
        try {
            $lookup = self::normalizeClientLookupInput($this->requestInput(['email', 'id']));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $client = $this->findVisibleClient($lookup, $scope);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $clientId = (int)$client['id'];
        $direct = $this->bindModel->getDirectIbForClient($clientId);
        $currentId = $direct ? (int)$direct['ibPartnerId'] : 0;
        if ($currentId <= 0) {
            $ownedIb = $this->ibModel->queryOne(
                "SELECT id FROM ibPartners WHERE userId = :clientId AND status = 'approved' ORDER BY id DESC LIMIT 1",
                ['clientId' => $clientId]
            );
            if ($ownedIb) {
                $parent = $this->bindModel->getParentIbPartner((int)$ownedIb['id']);
                $currentId = $parent ? (int)($parent['parentIbPartnerId'] ?? 0) : 0;
            }
        }

        $upline = [];
        $seen = [];
        $complete = true;
        $maxDepth = $this->programSettingModel->getNetworkMaxDepth();
        $distance = 1;
        while ($currentId > 0 && $distance <= $maxDepth) {
            if (isset($seen[$currentId])) {
                $complete = false;
                break;
            }
            $seen[$currentId] = true;
            $row = $this->findVisibleIb(['id' => $currentId], $scope);
            if (!$row) {
                $complete = false;
                break;
            }
            $node = self::projectIb($row);
            $node['distance'] = $distance;
            $upline[] = $node;
            $parent = $this->bindModel->getParentIbPartner($currentId);
            $currentId = $parent ? (int)($parent['parentIbPartnerId'] ?? 0) : 0;
            $distance++;
        }
        if ($currentId > 0) {
            $complete = false;
        }

        Response::success([
            'client' => self::projectClient($client),
            'upline' => $upline,
            'complete' => $complete
        ]);
    }

    private function requireIbScope(): array {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkPermission('page_iblist_readonly');
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_iblist');
        if (($scope['scope'] ?? 'none') === 'none') {
            Response::forbidden('You do not have permission to view IB partners');
        }
        return $scope;
    }

    private function requestIbLookup(): array {
        try {
            return self::normalizeIbLookupInput($this->requestInput(['email', 'id', 'code']));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    private function requestInput(array $keys): array {
        $input = [];
        foreach ($_GET as $key => $value) {
            if ($key === 'path') {
                continue;
            }
            if (!in_array($key, $keys, true)) {
                throw new InvalidArgumentException("{$key} is not supported for this request.");
            }
            $input[$key] = $value;
        }
        return $input;
    }

    private function ibLookupFromInput(array $input): array {
        return array_intersect_key($input, array_flip(['email', 'id', 'code']));
    }

    private function findVisibleIb(array $lookup, array $scope): ?array {
        $conditions = ["ib.status = 'approved'"];
        $params = [];
        if (isset($lookup['id'])) {
            $conditions[] = 'ib.id = :ib_id';
            $params['ib_id'] = $lookup['id'];
        } elseif (isset($lookup['code'])) {
            $conditions[] = 'ib.ibCode = :ib_code';
            $params['ib_code'] = $lookup['code'];
        } else {
            $conditions[] = '(LOWER(cu.email) = LOWER(:ib_email) OR LOWER(ib.contactEmail) = LOWER(:ib_contact_email))';
            $params['ib_email'] = $lookup['email'];
            $params['ib_contact_email'] = $lookup['email'];
        }
        $this->addIbScopeCondition($conditions, $params, $scope, 'ib');

        $sql = "SELECT
                    ib.id, ib.userId, ib.ibCode, ib.companyName, ib.ibType,
                    ib.tierLevelId, ib.status, ib.country, ib.registrationDate,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,''))), ''), ib.companyName) AS ibName,
                    COALESCE(cu.email, ib.contactEmail) AS email,
                    tl.tierLevel, tl.tierName AS tierLevelName
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                LEFT JOIN ibTierLevels tl ON tl.id = ib.tierLevelId
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY ib.id DESC
                LIMIT 1";
        return $this->ibModel->queryOne($sql, $params) ?: null;
    }

    private function findVisibleClient(array $lookup, array $scope): ?array {
        $conditions = [];
        $params = [];
        if (isset($lookup['id'])) {
            $conditions[] = 'cu.id = :client_id';
            $params['client_id'] = $lookup['id'];
        } else {
            $conditions[] = 'LOWER(cu.email) = LOWER(:client_email)';
            $params['client_email'] = $lookup['email'];
        }
        if (($scope['scope'] ?? 'none') === 'own') {
            $conditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }
        return $this->clientModel->queryOne(
            "SELECT cu.id, cu.firstName, cu.lastName, cu.email, cu.country,
                    cu.status, cu.kycStatus, cu.createdAt
             FROM clientUsers cu
             WHERE " . implode(' AND ', $conditions) . "
             LIMIT 1",
            $params
        ) ?: null;
    }

    private function getVisibleNetworkRows(array $scope): array {
        $conditions = ["b.isClient = 0", 'b.childId IS NOT NULL', "ib.status = 'approved'"];
        $params = [];
        $this->addIbScopeCondition($conditions, $params, $scope, 'ib');
        return $this->ibModel->query(
            "SELECT
                b.parentId, ib.id, ib.userId, ib.ibCode, ib.companyName,
                ib.ibType, ib.tierLevelId, ib.status, ib.country, ib.registrationDate,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,''))), ''), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                tl.tierLevel, tl.tierName AS tierLevelName
             FROM ib_partner_bind b
             INNER JOIN ibPartners ib ON ib.id = b.childId
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             LEFT JOIN ibTierLevels tl ON tl.id = ib.tierLevelId
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY b.parentId ASC, ib.id ASC",
            $params
        );
    }

    private function networkDepthMap(int $rootId, array $rows, int $maxDepth): array {
        $children = [];
        foreach ($rows as $row) {
            $parentId = (int)($row['parentId'] ?? 0);
            $childId = (int)($row['id'] ?? 0);
            if ($parentId > 0 && $childId > 0) {
                $children[$parentId][] = $childId;
            }
        }
        $depthMap = [$rootId => 0];
        $queue = [[$rootId, 0]];
        while (!empty($queue)) {
            [$parentId, $depth] = array_shift($queue);
            if ($depth >= $maxDepth) {
                continue;
            }
            foreach ($children[$parentId] ?? [] as $childId) {
                if (isset($depthMap[$childId])) {
                    continue;
                }
                $depthMap[$childId] = $depth + 1;
                $queue[] = [$childId, $depth + 1];
            }
        }
        return $depthMap;
    }

    private function countVisibleClients(array $ibIds, int $rootId, array $scope): array {
        if (empty($ibIds)) {
            return ['direct' => 0, 'total' => 0];
        }
        [$inSql, $params] = $this->namedInClause($ibIds, 'network_ib');
        $conditions = ["b.isClient = 1", 'b.childClientId IS NOT NULL', "b.parentId IN ({$inSql})"];
        if (($scope['scope'] ?? 'none') === 'own') {
            $conditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }
        $params['root_ib_id'] = $rootId;
        $row = $this->clientModel->queryOne(
            "SELECT
                COUNT(DISTINCT CASE WHEN b.parentId = :root_ib_id THEN b.childClientId END) AS directCount,
                COUNT(DISTINCT b.childClientId) AS totalCount
             FROM ib_partner_bind b
             INNER JOIN clientUsers cu ON cu.id = b.childClientId
             WHERE " . implode(' AND ', $conditions),
            $params
        );
        return [
            'direct' => (int)($row['directCount'] ?? 0),
            'total' => (int)($row['totalCount'] ?? 0)
        ];
    }

    private function queryVisibleClients(array $depthMap, int $page, int $limit, array $scope): array {
        $ibIds = array_keys($depthMap);
        if (empty($ibIds)) {
            return ['items' => [], 'total' => 0];
        }
        [$inSql, $params] = $this->namedInClause($ibIds, 'client_ib');
        $conditions = ["b.isClient = 1", 'b.childClientId IS NOT NULL', "b.parentId IN ({$inSql})"];
        if (($scope['scope'] ?? 'none') === 'own') {
            $conditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }
        $where = implode(' AND ', $conditions);
        $countRow = $this->clientModel->queryOne(
            "SELECT COUNT(DISTINCT b.childClientId) AS total
             FROM ib_partner_bind b
             INNER JOIN clientUsers cu ON cu.id = b.childClientId
             WHERE {$where}",
            $params
        );
        $total = (int)($countRow['total'] ?? 0);
        $offset = ($page - 1) * $limit;
        $rows = $this->clientModel->query(
            "SELECT DISTINCT
                b.parentId, cu.id AS clientId, cu.firstName, cu.lastName,
                cu.email, cu.country, cu.status AS clientStatus, cu.kycStatus,
                cu.createdAt AS clientRegistrationDate,
                pib.id AS parentIbId, pib.ibCode AS parentIbCode,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(pcu.firstName,''), ' ', COALESCE(pcu.lastName,''))), ''), pib.companyName) AS parentIbName,
                COALESCE(pcu.email, pib.contactEmail) AS parentIbEmail
             FROM ib_partner_bind b
             INNER JOIN clientUsers cu ON cu.id = b.childClientId
             INNER JOIN ibPartners pib ON pib.id = b.parentId
             LEFT JOIN clientUsers pcu ON pcu.id = pib.userId
             WHERE {$where}
             ORDER BY cu.createdAt DESC, cu.id DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $client = self::projectClient($row);
            $client['directIb'] = [
                'id' => (int)$row['parentIbId'],
                'code' => self::nullableString($row['parentIbCode'] ?? null),
                'name' => self::nullableString($row['parentIbName'] ?? null),
                'email' => self::nullableString($row['parentIbEmail'] ?? null)
            ];
            $client['depth'] = (int)($depthMap[(int)$row['parentId']] ?? 0);
            $items[] = $client;
        }
        return ['items' => $items, 'total' => $total];
    }

    private function addIbScopeCondition(array &$conditions, array &$params, array $scope, string $alias): void {
        if (($scope['scope'] ?? 'none') !== 'own') {
            return;
        }
        $conditions[] = "{$alias}.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
        $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
    }

    private function namedInClause(array $values, string $prefix): array {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$value;
        }
        return [implode(',', $placeholders), $params];
    }

    private static function pagination(int $page, int $limit, int $total): array {
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 0;
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages
        ];
    }

    private static function normalizeEmail($value): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException('email must be a string.');
        }
        $email = trim($value);
        if (
            $email === '' || strlen($email) > self::MAX_EMAIL_LENGTH ||
            filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException('email must be a valid email address of 254 characters or fewer.');
        }
        return $email;
    }

    private static function normalizePositiveInteger($value, string $name, int $maximum): int {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $number = (int)trim($value);
        } else {
            throw new InvalidArgumentException("{$name} must be an integer between 1 and {$maximum}.");
        }
        if ($number < 1 || $number > $maximum) {
            throw new InvalidArgumentException("{$name} must be an integer between 1 and {$maximum}.");
        }
        return $number;
    }

    private static function rejectUnsupportedKeys(array $input, array $allowed, string $context): void {
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("{$key} is not supported for {$context}.");
            }
        }
    }

    private static function nullableString($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
