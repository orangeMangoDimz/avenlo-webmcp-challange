<?php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/WebMcpOperationsService.php';

class WebMcpOperationsController
{
    private $service;

    public function __construct()
    {
        $this->service = new WebMcpOperationsService();
    }

    public static function routeHandlers(): array
    {
        return [
            'admin/get-operations-overview' => ['handler' => 'getOverview', 'method' => 'GET'],
        ];
    }

    public static function normalizeOverviewInput(array $input): array
    {
        $allowed = ['startDate', 'endDate', 'tzOffset', 'severity', 'exceptionType'];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("{$key} is not supported.");
            }
        }
        foreach (['startDate', 'endDate', 'tzOffset'] as $key) {
            if (!array_key_exists($key, $input)) {
                throw new InvalidArgumentException("{$key} is required.");
            }
        }

        $startDate = self::normalizeDate($input['startDate'], 'startDate');
        $endDate = self::normalizeDate($input['endDate'], 'endDate');
        if ($startDate > $endDate) {
            throw new InvalidArgumentException('startDate must be on or before endDate.');
        }
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        if ((int)$start->diff($end)->days > 89) {
            throw new InvalidArgumentException('The dashboard date range cannot exceed 90 days.');
        }

        $normalized = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'tzOffset' => self::normalizeTimezoneOffset($input['tzOffset']),
        ];
        if (array_key_exists('severity', $input)) {
            $normalized['severity'] = self::normalizeQueueSeverity($input['severity']);
        }
        if (array_key_exists('exceptionType', $input)) {
            $normalized['exceptionType'] = self::normalizeExceptionType($input['exceptionType']);
        }
        return $normalized;
    }

    public function getOverview(): void
    {
        AuthMiddleware::requireAdmin();
        try {
            $input = self::normalizeOverviewInput(array_diff_key($_GET, ['path' => true]));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
        Response::success($this->service->getOverview($input));
    }

    private static function normalizeDate($value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            throw new InvalidArgumentException("{$name} must use YYYY-MM-DD format.");
        }
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            throw new InvalidArgumentException("{$name} must be a valid calendar date.");
        }
        return $value;
    }

    private static function normalizeTimezoneOffset($value): int
    {
        if (is_int($value)) {
            $offset = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $offset = (int)trim($value);
        } else {
            throw new InvalidArgumentException('tzOffset must be an integer between -720 and 840.');
        }
        if ($offset < -720 || $offset > 840) {
            throw new InvalidArgumentException('tzOffset must be an integer between -720 and 840.');
        }
        return $offset;
    }

    private static function normalizeQueueSeverity($value): string
    {
        $severity = is_string($value) ? strtolower(trim($value)) : '';
        if (!in_array($severity, ['all', 'critical', 'high', 'medium'], true)) {
            throw new InvalidArgumentException('severity must be all, critical, high, or medium.');
        }
        return $severity;
    }

    private static function normalizeExceptionType($value): string
    {
        $type = is_string($value) ? strtolower(trim($value)) : '';
        if (!in_array($type, ['all', 'transaction', 'kyc', 'client', 'audit'], true)) {
            throw new InvalidArgumentException('exceptionType must be all, transaction, kyc, client, or audit.');
        }
        return $type;
    }
}
