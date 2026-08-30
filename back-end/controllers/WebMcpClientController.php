<?php

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../utils/Response.php';

class WebMcpClientController {
    private const MAX_CLIENT_ID = 2147483647;
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_CODE_LENGTH = 64;
    private const MAX_EXPORT_CLIENTS = 500;
    private const MAX_EXPORT_FILTER_LENGTH = 100;

    private $clientModel;

    public function __construct() {
        $this->clientModel = new ClientUser();
    }

    /**
     * Normalize and validate exactly one supported client lookup identifier.
     *
     * @param array $input
     * @return array
     * @throws InvalidArgumentException
     */
    public static function normalizeLookupInput(array $input): array {
        $lookupKeys = ['email', 'id', 'code'];
        $providedKeys = [];
        foreach ($lookupKeys as $key) {
            if (array_key_exists($key, $input)) {
                $providedKeys[] = $key;
            }
        }

        if (count($providedKeys) !== 1) {
            throw new InvalidArgumentException('Exactly one of email, id, or code is required.');
        }

        $key = $providedKeys[0];
        $value = $input[$key];

        if ($key === 'email') {
            if (!is_string($value)) {
                throw new InvalidArgumentException('email must be a string.');
            }

            $email = trim($value);
            if (
                $email === ''
                || strlen($email) > self::MAX_EMAIL_LENGTH
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new InvalidArgumentException('email must be a valid email address of 254 characters or fewer.');
            }

            return ['email' => $email];
        }

        if ($key === 'code') {
            if (!is_string($value)) {
                throw new InvalidArgumentException('code must be a string.');
            }

            $code = trim($value);
            if ($code === '' || strlen($code) > self::MAX_CODE_LENGTH) {
                throw new InvalidArgumentException('code must be between 1 and 64 characters.');
            }

            return ['code' => $code];
        }

        if (is_int($value)) {
            $id = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $id = (int)trim($value);
        } else {
            throw new InvalidArgumentException('id must be an integer between 1 and 2147483647.');
        }

        if ($id < 1 || $id > self::MAX_CLIENT_ID) {
            throw new InvalidArgumentException('id must be an integer between 1 and 2147483647.');
        }

        return ['id' => $id];
    }

    /**
     * Normalize the supported client list filters and pagination.
     *
     * @param array $input
     * @return array
     * @throws InvalidArgumentException
     */
    public static function normalizeSearchInput(array $input): array {
        $filterKeys = ['country', 'tag', 'neverLoggedIn', 'kycStatus', 'status', 'search'];
        $hasFilter = false;
        foreach ($filterKeys as $key) {
            if (array_key_exists($key, $input)) {
                $hasFilter = true;
                break;
            }
        }

        if (!$hasFilter) {
            throw new InvalidArgumentException(
                'At least one search filter is required: country, tag, neverLoggedIn, kycStatus, status, or search.'
            );
        }

        $normalized = [];
        foreach (['country' => 100, 'tag' => 100, 'kycStatus' => 50, 'status' => 50, 'search' => 100] as $key => $maxLength) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (!is_string($input[$key])) {
                throw new InvalidArgumentException("{$key} must be a string.");
            }

            $value = trim($input[$key]);
            if ($value === '' || strlen($value) > $maxLength) {
                throw new InvalidArgumentException("{$key} must be between 1 and {$maxLength} characters.");
            }
            $normalized[$key] = $value;
        }

        if (array_key_exists('neverLoggedIn', $input)) {
            $neverLoggedIn = $input['neverLoggedIn'];
            if (is_string($neverLoggedIn)) {
                $normalizedBoolean = strtolower(trim($neverLoggedIn));
                if ($normalizedBoolean === 'true' || $normalizedBoolean === '1') {
                    $neverLoggedIn = true;
                } elseif ($normalizedBoolean === 'false' || $normalizedBoolean === '0') {
                    $neverLoggedIn = false;
                }
            }
            if (!is_bool($neverLoggedIn)) {
                throw new InvalidArgumentException('neverLoggedIn must be a boolean.');
            }
            $normalized['neverLoggedIn'] = $neverLoggedIn;
        }

        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', 1000);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', 50);

        return $normalized;
    }

    /**
     * Normalize transaction filters and pagination.
     *
     * @param array $input
     * @return array
     * @throws InvalidArgumentException
     */
    public static function normalizeTransactionInput(array $input): array {
        $lookup = self::normalizeLookupInput(array_intersect_key(
            $input,
            array_flip(['email', 'id', 'code'])
        ));

        $type = strtolower(trim((string)($input['type'] ?? 'all')));
        $typeAliases = [
            'deposits' => 'deposit',
            'withdrawals' => 'withdrawal',
            'internal-transfer' => 'internal_transfer',
            'internal-transfers' => 'internal_transfer',
            'internaltransfer' => 'internal_transfer',
            'internaltransfers' => 'internal_transfer'
        ];
        $type = $typeAliases[$type] ?? $type;
        $allowedTypes = ['all', 'deposit', 'withdrawal', 'internal_transfer', 'credit'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                'type must be one of: all, deposit, withdrawal, internal_transfer, credit.'
            );
        }

        return array_merge($lookup, [
            'type' => $type,
            'page' => self::normalizePositiveInteger($input['page'] ?? 1, 'page', 1000),
            'limit' => self::normalizePositiveInteger($input['limit'] ?? 10, 'limit', 50)
        ]);
    }

    /**
     * Normalize the selected-client export filters.
     *
     * @param array $input
     * @return array
     * @throws InvalidArgumentException
     */
    public static function normalizeExportClientInput(array $input): array {
        $normalized = [
            'clientIds' => self::normalizeExportClientIds($input['clientIds'] ?? null)
        ];

        foreach ([
            'country' => self::MAX_EXPORT_FILTER_LENGTH,
            'tag' => self::MAX_EXPORT_FILTER_LENGTH,
            'kycStatus' => 50,
            'status' => 50,
            'search' => self::MAX_EXPORT_FILTER_LENGTH
        ] as $key => $maxLength) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeOptionalExportString($input[$key], $key, $maxLength);
            }
        }

        if (array_key_exists('neverLoggedIn', $input)) {
            $value = $input['neverLoggedIn'];
            if (is_string($value)) {
                $boolean = strtolower(trim($value));
                if ($boolean === 'true' || $boolean === '1') {
                    $value = true;
                } elseif ($boolean === 'false' || $boolean === '0') {
                    $value = false;
                }
            }
            if (!is_bool($value)) {
                throw new InvalidArgumentException('neverLoggedIn must be a boolean.');
            }
            $normalized['neverLoggedIn'] = $value;
        }

        foreach (['registeredFrom', 'registeredTo'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeExportDate($input[$key], $key);
            }
        }
        self::validateExportDateRange($normalized, 'registeredFrom', 'registeredTo');

        return $normalized;
    }

    /**
     * Normalize the selected-client transaction export filters.
     *
     * @param array $input
     * @return array
     * @throws InvalidArgumentException
     */
    public static function normalizeExportTransactionInput(array $input): array {
        $normalized = [
            'clientIds' => self::normalizeExportClientIds($input['clientIds'] ?? null)
        ];

        foreach (['dateFrom', 'dateTo'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeExportDate($input[$key], $key);
            }
        }
        self::validateExportDateRange($normalized, 'dateFrom', 'dateTo');

        $type = strtolower(trim((string)($input['type'] ?? 'all')));
        $typeAliases = [
            'deposits' => 'deposit',
            'withdrawals' => 'withdrawal',
            'internal-transfer' => 'internal_transfer',
            'internal-transfers' => 'internal_transfer',
            'internaltransfer' => 'internal_transfer',
            'internaltransfers' => 'internal_transfer'
        ];
        $type = $typeAliases[$type] ?? $type;
        if (!in_array($type, ['all', 'deposit', 'withdrawal', 'internal_transfer', 'credit'], true)) {
            throw new InvalidArgumentException(
                'type must be one of: all, deposit, withdrawal, internal_transfer, credit.'
            );
        }
        $normalized['type'] = $type;

        if (array_key_exists('status', $input)) {
            $normalized['status'] = self::normalizeOptionalExportString($input['status'], 'status', 50);
        }

        return $normalized;
    }

    private static function normalizeExportClientIds($value): array {
        if (!is_array($value) || count($value) === 0) {
            throw new InvalidArgumentException('clientIds must contain between 1 and ' . self::MAX_EXPORT_CLIENTS . ' client IDs.');
        }
        if (count($value) > self::MAX_EXPORT_CLIENTS) {
            throw new InvalidArgumentException('clientIds cannot contain more than ' . self::MAX_EXPORT_CLIENTS . ' client IDs.');
        }

        $normalized = [];
        $seen = [];
        foreach ($value as $clientId) {
            if (is_int($clientId)) {
                $id = $clientId;
            } elseif (is_string($clientId) && preg_match('/^\d+$/', trim($clientId))) {
                $id = (int)trim($clientId);
            } else {
                throw new InvalidArgumentException('Each clientIds value must be a positive integer.');
            }
            if ($id < 1 || $id > self::MAX_CLIENT_ID) {
                throw new InvalidArgumentException('Each clientIds value must be between 1 and ' . self::MAX_CLIENT_ID . '.');
            }
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $normalized[] = $id;
            }
        }

        if (count($normalized) === 0) {
            throw new InvalidArgumentException('clientIds must contain at least one client ID.');
        }
        return $normalized;
    }

    private static function normalizeOptionalExportString($value, string $name, int $maximum): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException("{$name} must be a string.");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maximum) {
            throw new InvalidArgumentException("{$name} must be between 1 and {$maximum} characters.");
        }
        return $value;
    }

    private static function normalizeExportDate($value, string $name): string {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            throw new InvalidArgumentException("{$name} must use YYYY-MM-DD format.");
        }
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException("{$name} must use a valid calendar date.");
        }
        return $value;
    }

    private static function validateExportDateRange(array $input, string $fromKey, string $toKey): void {
        if (isset($input[$fromKey], $input[$toKey]) && $input[$fromKey] > $input[$toKey]) {
            throw new InvalidArgumentException("{$fromKey} cannot be after {$toKey}.");
        }
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

    private function requireClientScope(array $permissionKeys): array {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkAnyPermission($permissionKeys);

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if (($scope['scope'] ?? 'none') === 'none') {
            Response::forbidden('You do not have permission to view clients');
        }

        return $scope;
    }

    private function requestLookup(): array {
        try {
            return self::normalizeLookupInput(array_intersect_key(
                $_GET,
                array_flip(['email', 'id', 'code'])
            ));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    private function addClientScopeCondition(array &$conditions, array &$params, array $scope): void {
        if (($scope['scope'] ?? 'none') !== 'own') {
            return;
        }

        $conditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
        $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
    }

    private function findVisibleClient(array $lookup, array $scope): ?array {
        $conditions = [];
        $params = [];
        if (isset($lookup['email'])) {
            $conditions[] = 'cu.email = :email';
            $params['email'] = $lookup['email'];
        } elseif (isset($lookup['id'])) {
            $conditions[] = 'cu.id = :client_id';
            $params['client_id'] = $lookup['id'];
        } else {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM ibPartners ib_lookup
                WHERE ib_lookup.userId = cu.id
                  AND ib_lookup.ibCode = :ib_code
                  AND ib_lookup.status = 'approved'
            )";
            $params['ib_code'] = $lookup['code'];
        }
        $this->addClientScopeCondition($conditions, $params, $scope);

        $sql = "SELECT
                    cu.id,
                    cu.firstName,
                    cu.lastName,
                    cu.email,
                    cu.country,
                    cu.status,
                    cu.kycStatus,
                    cu.kycSubmissionId,
                    cu.createdAt,
                    cu.lastLoginAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    (
                        SELECT ib.ibCode
                        FROM ibPartners ib
                        WHERE ib.userId = cu.id
                          AND ib.status = 'approved'
                          AND TRIM(COALESCE(ib.ibCode, '')) <> ''
                        ORDER BY ib.id DESC
                        LIMIT 1
                    ) AS ibCode
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                WHERE " . implode(' AND ', $conditions) . "
                LIMIT 1";

        return $this->clientModel->queryOne($sql, $params) ?: null;
    }

    private static function projectSearchClient(array $client): array {
        $projected = self::projectClient($client);
        $tagNames = trim((string)($client['tagNames'] ?? ''));
        $projected['tags'] = $tagNames === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $tagNames))));

        return $projected;
    }

    /**
     * GET /api/webmcp/admin/get-client
     */
    public function getClient(): void {
        $scope = $this->requireClientScope([
            'page_clientslist_readonly',
            'page_clientsdetail_profile'
        ]);

        $client = $this->findVisibleClient($this->requestLookup(), $scope);
        if (!$client) {
            Response::notFound('Client not found');
        }

        Response::success(self::projectClient($client));
    }

    /**
     * GET /api/webmcp/admin/search-clients
     */
    public function searchClients(): void {
        $scope = $this->requireClientScope(['page_clientslist_readonly']);

        try {
            $input = self::normalizeSearchInput($_GET);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $conditions = ['1 = 1'];
        $params = [];
        if (isset($input['country'])) {
            $conditions[] = "cu.country = COALESCE(
                (SELECT cl.code
                 FROM countryList cl
                 WHERE UPPER(cl.code) = UPPER(:country)
                    OR UPPER(cl.name) = UPPER(:country)
                 LIMIT 1),
                :country_fallback
            )";
            $params['country'] = $input['country'];
            $params['country_fallback'] = $input['country'];
        }
        if (isset($input['tag'])) {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM leadTagAssignments lta_filter
                INNER JOIN leadTags lt_filter ON lt_filter.id = lta_filter.tagId
                WHERE lta_filter.leadId = cu.id
                  AND UPPER(lt_filter.tagName) = UPPER(:tag)
            )";
            $params['tag'] = $input['tag'];
        }
        if (array_key_exists('neverLoggedIn', $input)) {
            $conditions[] = $input['neverLoggedIn']
                ? 'cu.lastLoginAt IS NULL'
                : 'cu.lastLoginAt IS NOT NULL';
        }
        if (isset($input['kycStatus'])) {
            $conditions[] = 'cu.kycStatus = :kyc_status';
            $params['kyc_status'] = $input['kycStatus'];
        }
        if (isset($input['status'])) {
            $conditions[] = 'cu.status = :client_status';
            $params['client_status'] = $input['status'];
        }
        if (isset($input['search'])) {
            $searchPattern = '%' . $input['search'] . '%';
            $conditions[] = "(
                cu.firstName LIKE :search_first_name
                OR cu.lastName LIKE :search_last_name
                OR cu.email LIKE :search_email
                OR cu.phone LIKE :search_phone
                OR cu.country LIKE :search_country
                OR CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :search_full_name
                OR EXISTS (
                    SELECT 1
                    FROM tradingAccounts ta_search
                    LEFT JOIN tradingAccountExternalAccounts tea_search
                        ON tea_search.tradingAccountId = ta_search.id
                    WHERE ta_search.userId = cu.id
                      AND (
                          ta_search.accountNumber LIKE :search_account_number
                          OR tea_search.providerAccountId LIKE :search_provider_account
                      )
                )
            )";
            $params['search_first_name'] = $searchPattern;
            $params['search_last_name'] = $searchPattern;
            $params['search_email'] = $searchPattern;
            $params['search_phone'] = $searchPattern;
            $params['search_country'] = $searchPattern;
            $params['search_full_name'] = $searchPattern;
            $params['search_account_number'] = $searchPattern;
            $params['search_provider_account'] = $searchPattern;
        }
        $this->addClientScopeCondition($conditions, $params, $scope);
        $whereSql = implode(' AND ', $conditions);

        $fromSql = "FROM clientUsers cu
                    LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                    WHERE {$whereSql}";
        $countRow = $this->clientModel->queryOne(
            "SELECT COUNT(*) AS total {$fromSql}",
            $params
        );
        $total = (int)($countRow['total'] ?? 0);

        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->clientModel->query(
            "SELECT
                cu.id,
                cu.firstName,
                cu.lastName,
                cu.email,
                cu.country,
                cu.status,
                cu.kycStatus,
                cu.createdAt,
                cu.lastLoginAt,
                au.fullName AS managerName,
                au.email AS managerEmail,
                (
                    SELECT GROUP_CONCAT(DISTINCT lt.tagName ORDER BY lt.tagName SEPARATOR ',')
                    FROM leadTagAssignments lta
                    INNER JOIN leadTags lt ON lt.id = lta.tagId
                    WHERE lta.leadId = cu.id
                ) AS tagNames,
                (
                    SELECT ib.ibCode
                    FROM ibPartners ib
                    WHERE ib.userId = cu.id
                      AND ib.status = 'approved'
                      AND TRIM(COALESCE(ib.ibCode, '')) <> ''
                    ORDER BY ib.id DESC
                    LIMIT 1
                ) AS ibCode
             {$fromSql}
             ORDER BY cu.createdAt DESC, cu.id DESC
             LIMIT " . (int)$input['limit'] . " OFFSET " . (int)$offset,
            $params
        );

        $clients = array_map([self::class, 'projectSearchClient'], $rows);
        Response::success([
            'clients' => $clients,
            'pagination' => self::pagination($input['page'], $input['limit'], $total)
        ]);
    }

    /**
     * GET /api/webmcp/admin/get-client-documents
     */
    public function getClientDocuments(): void {
        $scope = $this->requireClientScope(['page_clientsdetail_document']);
        $client = $this->findVisibleClient($this->requestLookup(), $scope);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $documents = [];
        $registrationDocs = $this->clientModel->query(
            "SELECT
                lds.id,
                lds.documentType,
                COALESCE(lds.documentVersion, ld.version) AS version,
                lds.signedAt,
                ld.title,
                'registration' AS source
             FROM legalDocumentSignatures lds
             INNER JOIN legalDocuments ld ON lds.documentId = ld.id
             WHERE lds.leadId = :client_id
             ORDER BY lds.signedAt ASC",
            ['client_id' => (int)$client['id']]
        );
        foreach ($registrationDocs as $doc) {
            $documents[] = [
                'id' => (int)$doc['id'],
                'documentType' => $doc['documentType'] ?? null,
                'title' => $doc['title'] ?? null,
                'version' => $doc['version'] ?? null,
                'signedAt' => $doc['signedAt'] ?? null,
                'source' => 'registration'
            ];
        }

        $kycParams = ['client_id' => (int)$client['id']];
        $kycWhere = 'cks.clientId = :client_id';
        if (!empty($client['kycSubmissionId'])) {
            $kycWhere .= ' AND ckds.submissionId = :submission_id';
            $kycParams['submission_id'] = (int)$client['kycSubmissionId'];
        }
        $kycDocs = $this->clientModel->query(
            "SELECT
                ckds.id,
                ckds.signedAt,
                ktd.documentTitle AS title,
                kt.templateName,
                'kyc_document' AS documentType,
                'kyc' AS source
             FROM clientKycDocumentSignatures ckds
             INNER JOIN kycTemplateDocuments ktd ON ckds.templateDocumentId = ktd.id
             INNER JOIN clientKycSubmissions cks ON ckds.submissionId = cks.id
             INNER JOIN kycTemplates kt ON cks.templateId = kt.id
             WHERE {$kycWhere}
             ORDER BY ckds.signedAt ASC",
            $kycParams
        );
        foreach ($kycDocs as $doc) {
            $documents[] = [
                'id' => 'kyc_' . (int)$doc['id'],
                'documentType' => $doc['documentType'] ?? null,
                'title' => $doc['title'] ?? null,
                'templateName' => $doc['templateName'] ?? null,
                'signedAt' => $doc['signedAt'] ?? null,
                'source' => 'kyc'
            ];
        }

        $ibDocs = $this->clientModel->query(
            "SELECT
                pda.id,
                pda.acknowledgedAt AS signedAt,
                dt.documentTitle AS title,
                dt.version,
                'ib_agreement' AS documentType,
                'ib' AS source
             FROM ibPartners ip
             INNER JOIN ibPartnerDocumentAcknowledgements pda
                ON pda.ibPartnerId = ip.id AND pda.acknowledged = 1
             INNER JOIN ibDocumentTemplates dt ON pda.documentTemplateId = dt.id
             WHERE ip.userId = :client_id
             ORDER BY pda.acknowledgedAt ASC",
            ['client_id' => (int)$client['id']]
        );
        foreach ($ibDocs as $doc) {
            $documents[] = [
                'id' => 'ib_' . (int)$doc['id'],
                'documentType' => $doc['documentType'] ?? null,
                'title' => $doc['title'] ?? null,
                'version' => $doc['version'] ?? null,
                'signedAt' => $doc['signedAt'] ?? null,
                'source' => 'ib'
            ];
        }

        usort($documents, static function (array $left, array $right): int {
            return strcmp((string)($left['signedAt'] ?? ''), (string)($right['signedAt'] ?? ''));
        });

        Response::success([
            'clientId' => (int)$client['id'],
            'documents' => $documents
        ]);
    }

    /**
     * GET /api/webmcp/admin/get-client-trading-accounts
     */
    public function getClientTradingAccounts(): void {
        $scope = $this->requireClientScope(['page_clientsdetail_trading']);
        $client = $this->findVisibleClient($this->requestLookup(), $scope);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $rows = $this->clientModel->query(
            "SELECT
                ta.id,
                ta.accountNumber,
                ta.accountNickname,
                ta.accountCurrency,
                ta.accountType,
                ta.leverageValue,
                ta.initialDeposit,
                ta.status,
                ta.createdAt,
                tp.platformKey,
                tp.displayName AS platformName,
                tea.providerAccountId,
                tea.platformBalance,
                tea.platformCredit,
                tea.leverage AS externalLeverage,
                tab.balance,
                tab.equity,
                tab.credit AS balanceCredit,
                tab.updated_at AS balanceUpdatedAt
             FROM tradingAccounts ta
             INNER JOIN tradingPlatforms tp ON tp.id = ta.platformId
             LEFT JOIN tradingAccountExternalAccounts tea
                ON tea.tradingAccountId = ta.id
             LEFT JOIN trading_account_balances tab
                ON tab.id = (
                    SELECT tab_latest.id
                    FROM trading_account_balances tab_latest
                    WHERE tab_latest.trading_account_id = ta.id
                    ORDER BY tab_latest.updated_at DESC, tab_latest.id DESC
                    LIMIT 1
                )
             WHERE ta.userId = :client_id
             ORDER BY ta.createdAt DESC, ta.id DESC",
            ['client_id' => (int)$client['id']]
        );

        $accounts = array_map(static function (array $row): array {
            $providerAccountId = trim((string)($row['providerAccountId'] ?? ''));
            $balance = $row['balance'] !== null && $row['balance'] !== ''
                ? (float)$row['balance']
                : ($row['platformBalance'] !== null && $row['platformBalance'] !== '' ? (float)$row['platformBalance'] : null);
            $creditValue = $row['balanceCredit'] !== null && $row['balanceCredit'] !== ''
                ? $row['balanceCredit']
                : $row['platformCredit'];

            return [
                'id' => (int)$row['id'],
                'accountNumber' => $row['accountNumber'] ?? null,
                'accountNickname' => $row['accountNickname'] ?? null,
                'login' => $providerAccountId !== '' ? $providerAccountId : ($row['accountNumber'] ?? null),
                'platformKey' => $row['platformKey'] ?? null,
                'platformName' => $row['platformName'] ?? null,
                'status' => $row['status'] ?? null,
                'currency' => $row['accountCurrency'] ?? null,
                'accountType' => $row['accountType'] ?? null,
                'leverage' => $row['externalLeverage'] ?? $row['leverageValue'] ?? null,
                'initialDeposit' => isset($row['initialDeposit']) ? (float)$row['initialDeposit'] : null,
                'balance' => $balance,
                'equity' => $row['equity'] !== null && $row['equity'] !== '' ? (float)$row['equity'] : null,
                'credit' => $creditValue !== null && $creditValue !== '' ? (float)$creditValue : null,
                'balanceUpdatedAt' => $row['balanceUpdatedAt'] ?? null,
                'createdAt' => $row['createdAt'] ?? null
            ];
        }, $rows);

        Response::success([
            'clientId' => (int)$client['id'],
            'accounts' => $accounts
        ]);
    }

    /**
     * GET /api/webmcp/admin/get-client-recent-transactions
     */
    public function getClientRecentTransactions(): void {
        $scope = $this->requireClientScope(['page_clientsdetail_funding']);

        try {
            $input = self::normalizeTransactionInput($_GET);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $client = $this->findVisibleClient($input, $scope);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $queries = [];
        $params = [];
        $clientId = (int)$client['id'];
        if ($input['type'] === 'all' || $input['type'] === 'deposit') {
            $queries[] = "SELECT
                d.id,
                d.transactionId,
                'deposit' AS transactionType,
                d.status,
                d.amount,
                COALESCE(d.currencyCode, 'USD') AS currency,
                d.requestedAt AS transactionDate
             FROM deposits d
             WHERE d.userId = :deposit_client_id";
            $params['deposit_client_id'] = $clientId;
        }
        if ($input['type'] === 'all' || $input['type'] === 'withdrawal') {
            $queries[] = "SELECT
                w.id,
                w.transactionId,
                'withdrawal' AS transactionType,
                w.status,
                w.amount,
                COALESCE(w.currencyCode, 'USD') AS currency,
                w.requestedAt AS transactionDate
             FROM withdrawals w
             WHERE w.userId = :withdrawal_client_id";
            $params['withdrawal_client_id'] = $clientId;
        }
        if ($input['type'] === 'all' || $input['type'] === 'credit') {
            $queries[] = "SELECT
                tcd.id,
                CONCAT('CR-', tcd.id) AS transactionId,
                'credit' AS transactionType,
                'completed' AS status,
                CASE WHEN tcd.direction = 2 THEN -tcd.amount ELSE tcd.amount END AS amount,
                COALESCE(ta_credit.accountCurrency, 'USD') AS currency,
                tcd.deal_time AS transactionDate
             FROM trading_credit_deals tcd
             INNER JOIN tradingAccounts ta_credit ON ta_credit.id = tcd.trading_account_id
             WHERE ta_credit.userId = :credit_client_id";
            $params['credit_client_id'] = $clientId;
        }
        if ($input['type'] === 'all' || $input['type'] === 'internal_transfer') {
            $queries[] = "SELECT
                it.id,
                it.transactionId,
                'internal_transfer' AS transactionType,
                it.status,
                it.amount,
                'USD' AS currency,
                it.requestedAt AS transactionDate
             FROM internalTransfers it
             WHERE it.userId = :internal_transfer_client_id";
            $params['internal_transfer_client_id'] = $clientId;
        }

        $unionSql = implode(' UNION ALL ', $queries);
        $countRow = $this->clientModel->queryOne(
            "SELECT COUNT(*) AS total FROM ({$unionSql}) transactions",
            $params
        );
        $total = (int)($countRow['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->clientModel->query(
            "SELECT *
             FROM ({$unionSql}) transactions
             ORDER BY transactionDate DESC, id DESC
             LIMIT " . (int)$input['limit'] . " OFFSET " . (int)$offset,
            $params
        );

        $transactions = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'transactionId' => $row['transactionId'] ?? null,
                'type' => $row['transactionType'] ?? null,
                'status' => $row['status'] ?? null,
                'amount' => isset($row['amount']) ? (float)$row['amount'] : null,
                'currency' => $row['currency'] ?? null,
                'date' => $row['transactionDate'] ?? null
            ];
        }, $rows);

        Response::success([
            'clientId' => $clientId,
            'type' => $input['type'],
            'transactions' => $transactions,
            'pagination' => self::pagination($input['page'], $input['limit'], $total)
        ]);
    }

    /**
     * POST /api/webmcp/admin/export-clients
     */
    public function exportClients(): void {
        $this->startExport('clients');
    }

    /**
     * POST /api/webmcp/admin/export-client-transactions
     */
    public function exportClientTransactions(): void {
        $this->startExport('transactions');
    }

    /**
     * GET /api/webmcp/admin/export-status?jobId=...
     */
    public function exportStatus(): void {
        require_once __DIR__ . '/../services/WebMcpClientExportService.php';
        [$progress] = $this->authorizedExportJob();
        require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
        $progress = ExportJobTimeoutReaper::reapIfStale(
            (string)$progress['jobId'],
            $progress,
            ['WebMcpClientExportService', 'writeProgress'],
            ['WebMcpClientExportService', 'clearActive']
        );
        Response::success($this->exportProgressPayload($progress));
    }

    /**
     * GET /api/webmcp/admin/export-download?jobId=...
     */
    public function exportDownload(): void {
        require_once __DIR__ . '/../services/WebMcpClientExportService.php';
        [$progress, $adminUserId] = $this->authorizedExportJob();
        if (($progress['status'] ?? '') !== 'done') {
            Response::error('Export is not ready', 409);
        }

        $jobId = (string)$progress['jobId'];
        $file = WebMcpClientExportService::csvPath($jobId);
        if (!is_file($file) || !is_readable($file)) {
            Response::notFound('Export file not found');
        }

        $fileName = trim((string)($progress['fileName'] ?? ''));
        if ($fileName === '' || !preg_match('/^[A-Za-z0-9._-]+\.xls$/', $fileName)) {
            $fileName = 'webmcp_export_' . date('Y-m-d') . '.xls';
        }

        WebMcpClientExportService::clearActive($adminUserId);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-store, private');
        readfile($file);
        exit;
    }

    private function startExport(string $exportType): void {
        require_once __DIR__ . '/../services/WebMcpClientExportService.php';
        $permissionKeys = $exportType === 'clients'
            ? ['page_clientslist_export']
            : ['page_fundingreport_export'];
        $scope = $this->requireClientScope($permissionKeys);

        try {
            $rawInput = $this->requestJsonBody();
            $input = $exportType === 'clients'
                ? self::normalizeExportClientInput($rawInput)
                : self::normalizeExportTransactionInput($rawInput);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $currentUser = AuthMiddleware::requireAdmin();
        $adminUserId = (int)($currentUser['userId'] ?? 0);
        if ($adminUserId <= 0) {
            Response::unauthorized('Invalid admin token.');
        }

        $active = WebMcpClientExportService::getActiveForAdmin($adminUserId);
        if ($active !== null && in_array((string)($active['status'] ?? ''), ['queued', 'running'], true)) {
            Response::error('An export is already in progress.', 409, $this->exportProgressPayload($active));
        }

        $jobId = 'wmcp_' . $exportType . '_' . bin2hex(random_bytes(12));
        $fileName = ($exportType === 'clients' ? 'clients_' : 'client_transactions_') . date('Y-m-d') . '.xls';
        $progress = [
            'adminUserId' => $adminUserId,
            'exportType' => $exportType,
            'status' => 'queued',
            'percent' => 0,
            'processed' => 0,
            'total' => 0,
            'message' => 'Queued',
            'downloadReady' => false,
            'file' => $jobId . '.xls',
            'fileName' => $fileName,
        ];

        try {
            WebMcpClientExportService::writeProgress($jobId, $progress);
            WebMcpClientExportService::writeActive($adminUserId, $jobId);
            $this->dispatchSwooleTask([
                'type' => 'export_webmcp_client',
                'jobId' => $jobId,
                'exportType' => $exportType,
                'adminUserId' => $adminUserId,
                'userId' => $adminUserId,
                'userType' => 'admin',
                'input' => $input,
                'scope' => [
                    'scope' => (string)($scope['scope'] ?? 'none'),
                    'restrict_to_sales_id' => (int)($scope['restrict_to_sales_id'] ?? 0),
                ],
                'fileName' => $fileName,
                'requestedAt' => time(),
            ]);
        } catch (Throwable $exception) {
            WebMcpClientExportService::clearActive($adminUserId);
            WebMcpClientExportService::writeProgress($jobId, array_merge($progress, [
                'status' => 'error',
                'message' => 'Unable to queue export.',
                'file' => null,
            ]));
            Response::error('Unable to queue export.', 503);
        }

        Response::success([
            'jobId' => $jobId,
            'exportType' => $exportType,
            'fileName' => $fileName,
            'queued' => true,
        ], 'Export task accepted');
    }

    private function authorizedExportJob(): array {
        $currentUser = AuthMiddleware::requireAdmin();
        $adminUserId = (int)($currentUser['userId'] ?? 0);
        $jobId = trim((string)($_GET['jobId'] ?? ''));
        if ($jobId === '' || strlen($jobId) > 80 || preg_match('/^[A-Za-z0-9._-]+$/', $jobId) !== 1) {
            Response::error('jobId is required.', 422);
        }

        $progress = WebMcpClientExportService::readProgress($jobId);
        if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
            Response::notFound('Export job not found');
        }

        $exportType = (string)($progress['exportType'] ?? '');
        $permissionKeys = $exportType === 'clients'
            ? ['page_clientslist_export']
            : ($exportType === 'transactions' ? ['page_fundingreport_export'] : []);
        if (!$permissionKeys) {
            Response::notFound('Export job not found');
        }
        $this->requireClientScope($permissionKeys);

        return [$progress, $adminUserId];
    }

    private function requestJsonBody(): array {
        $body = json_decode((string)file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }

    private function dispatchSwooleTask(array $payload): void {
        $address = config_swoole_address();
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, 1.0);
        if (!$socket) {
            throw new RuntimeException('Failed to connect myswoole.');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fclose($socket);
            throw new RuntimeException('Failed to encode export task.');
        }

        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new RuntimeException('Failed to send export task.');
        }
    }

    private function exportProgressPayload(?array $progress): array {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string)($progress['jobId'] ?? ''),
            'exportType' => (string)($progress['exportType'] ?? ''),
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
            'fileName' => (string)($progress['fileName'] ?? ''),
        ];
    }

    private static function pagination(int $page, int $limit, int $total): array {
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;
        return [
            'page' => $page,
            'limit' => $limit,
            'perPage' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages
        ];
    }

    public static function projectClient(array $client): array {
        $firstName = trim((string)($client['firstName'] ?? ''));
        $lastName = trim((string)($client['lastName'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') {
            $name = (string)($client['email'] ?? ('Client ' . (int)($client['id'] ?? 0)));
        }

        $managerName = trim((string)($client['managerName'] ?? ''));
        $managerEmail = trim((string)($client['managerEmail'] ?? ''));
        $ibCode = trim((string)($client['ibCode'] ?? ''));

        return [
            'id' => (int)($client['id'] ?? 0),
            'name' => $name,
            'email' => $client['email'] ?? null,
            'country' => $client['country'] ?? null,
            'status' => $client['status'] ?? null,
            'kycStatus' => $client['kycStatus'] ?? null,
            'manager' => $managerName !== '' ? $managerName : ($managerEmail !== '' ? $managerEmail : null),
            'registeredAt' => $client['createdAt'] ?? null,
            'lastLoginAt' => $client['lastLoginAt'] ?? null,
            'isIb' => $ibCode !== '',
            'ibCode' => $ibCode !== '' ? $ibCode : null
        ];
    }
}
