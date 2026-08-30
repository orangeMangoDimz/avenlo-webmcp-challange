<?php

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../utils/Response.php';

class WebMcpClientController {
    private const MAX_CLIENT_ID = 2147483647;
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_CODE_LENGTH = 64;

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
     * GET /api/webmcp/admin/get-client
     */
    public function getClient(): void {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkAnyPermission([
            'page_clientslist_readonly',
            'page_clientsdetail_profile'
        ]);

        try {
            $lookupInput = array_intersect_key(
                $_GET,
                array_flip(['email', 'id', 'code'])
            );
            $lookup = self::normalizeLookupInput($lookupInput);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if (($scope['scope'] ?? 'none') === 'none') {
            Response::forbidden('You do not have permission to view clients');
        }

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

        if (($scope['scope'] ?? 'none') === 'own') {
            $conditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }

        $sql = "SELECT
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

        $client = $this->clientModel->queryOne($sql, $params);
        if (!$client) {
            Response::notFound('Client not found');
        }

        Response::success(self::projectClient($client));
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
