<?php

require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../utils/Response.php';

class WebMcpKycController {
    private const MAX_ID = 2147483647;
    private const MAX_PAGE = 1000;
    private const MAX_LIMIT = 50;
    private const MAX_WAITING_HOURS = 87600;

    private $submissionModel;

    public function __construct() {
        $this->submissionModel = new ClientKycSubmission();
    }

    public static function routeHandlers(): array {
        return [
            'admin/search-kyc' => 'search',
            'admin/get-kyc' => 'getSummary',
            'admin/get-kyc-answers' => 'getAnswers',
            'admin/get-kyc-documents' => 'getDocuments',
            'admin/get-kyc-progress' => 'getProgress',
            'admin/get-kyc-timeline' => 'getTimeline'
        ];
    }

    public static function canonicalStatus($status): string {
        $status = strtolower(trim((string)$status));
        if ($status === 'submitted') {
            return 'pending';
        }
        if ($status === 'resubmit_required' || $status === 'pending_documents') {
            return 'requires_documents';
        }
        return $status;
    }

    public static function normalizeSearchInput(array $input): array {
        $filterKeys = [
            'submissionId',
            'email',
            'country',
            'status',
            'assigned',
            'reviewerId',
            'templateId',
            'provider',
            'minWaitingHours'
        ];
        $allowedKeys = array_merge($filterKeys, ['page', 'limit']);
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException("{$key} is not supported for KYC search.");
            }
        }

        $hasFilter = false;
        foreach ($filterKeys as $key) {
            if (array_key_exists($key, $input)) {
                $hasFilter = true;
                break;
            }
        }
        if (!$hasFilter) {
            throw new InvalidArgumentException('At least one KYC search filter is required.');
        }

        $normalized = [];
        foreach (['submissionId', 'reviewerId', 'templateId'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizePositiveInteger($input[$key], $key, self::MAX_ID);
            }
        }

        if (array_key_exists('email', $input)) {
            if (!is_string($input['email'])) {
                throw new InvalidArgumentException('email must be a string.');
            }
            $email = trim($input['email']);
            if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('email must be a valid email address of 254 characters or fewer.');
            }
            $normalized['email'] = $email;
        }

        foreach (['country' => 100, 'provider' => 100] as $key => $maximum) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (!is_string($input[$key])) {
                throw new InvalidArgumentException("{$key} must be a string.");
            }
            $value = trim($input[$key]);
            if ($value === '' || strlen($value) > $maximum) {
                throw new InvalidArgumentException("{$key} must be between 1 and {$maximum} characters.");
            }
            $normalized[$key] = $value;
        }

        if (array_key_exists('status', $input)) {
            if (!is_string($input['status'])) {
                throw new InvalidArgumentException('status must be a string.');
            }
            $status = self::canonicalStatus($input['status']);
            $allowedStatuses = [
                'draft',
                'incomplete',
                'pending',
                'under_review',
                'requires_documents',
                'approved',
                'rejected',
                'expired'
            ];
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException(
                    'status must be one of: draft, incomplete, pending, under_review, requires_documents, approved, rejected, expired.'
                );
            }
            $normalized['status'] = $status;
        }

        if (array_key_exists('assigned', $input)) {
            $normalized['assigned'] = self::normalizeBoolean($input['assigned'], 'assigned');
        }
        if (array_key_exists('minWaitingHours', $input)) {
            $normalized['minWaitingHours'] = self::normalizeNonNegativeInteger(
                $input['minWaitingHours'],
                'minWaitingHours',
                self::MAX_WAITING_HOURS
            );
        }

        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public static function normalizeSubmissionId(array $input): int {
        foreach (array_keys($input) as $key) {
            if ($key !== 'submissionId') {
                throw new InvalidArgumentException("{$key} is not supported for a KYC submission lookup.");
            }
        }
        if (!array_key_exists('submissionId', $input)) {
            throw new InvalidArgumentException('submissionId is required.');
        }
        return self::normalizePositiveInteger($input['submissionId'], 'submissionId', self::MAX_ID);
    }

    public static function maskEmail($email): string {
        $email = trim((string)$email);
        $at = strrpos($email, '@');
        if ($at === false || $at === 0 || $at === strlen($email) - 1) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $maskedLocal = strlen($local) === 1 ? '*' : substr($local, 0, 1) . '***';
        return $maskedLocal . '@' . $domain;
    }

    public static function projectSearchSubmission(array $row): array {
        $reviewerId = self::nullablePositiveInteger($row['reviewerId'] ?? null);
        $providerName = trim((string)($row['thirdPartyProvider'] ?? ''));
        $managedExternally = self::databaseBoolean($row['isThirdParty'] ?? false)
            || self::databaseBoolean($row['isThirdPartyEnabled'] ?? false);

        return [
            'submissionId' => (int)($row['submissionId'] ?? 0),
            'client' => [
                'name' => self::clientName($row),
                'maskedEmail' => self::maskEmail($row['clientEmail'] ?? ''),
                'country' => $row['country'] ?? null
            ],
            'status' => self::canonicalStatus($row['submissionStatus'] ?? ''),
            'template' => [
                'id' => (int)($row['templateId'] ?? 0),
                'name' => $row['templateName'] ?? null
            ],
            'provider' => [
                'name' => $providerName !== '' ? $providerName : 'Local',
                'managedExternally' => $managedExternally
            ],
            'submittedAt' => $row['submittedAt'] ?? null,
            'completionPercentage' => isset($row['progressPercentage'])
                ? (float)$row['progressPercentage']
                : 0.0,
            'reviewer' => $reviewerId === null ? null : [
                'id' => $reviewerId,
                'name' => self::nullableString($row['reviewerName'] ?? null)
            ],
            'waitingHours' => max(0, (int)($row['waitingHours'] ?? 0))
        ];
    }

    public static function sanitizeFileMetadata($file, $fallbackUploadedAt = null): ?array {
        if (is_string($file)) {
            $path = parse_url($file, PHP_URL_PATH);
            $filename = basename((string)($path ?: $file));
            $data = ['fileName' => $filename !== '' ? urldecode($filename) : 'uploaded-file'];
        } elseif (is_array($file)) {
            $filename = $file['fileName'] ?? $file['filename'] ?? $file['name'] ?? null;
            if (!$filename) {
                $path = $file['filePath'] ?? $file['path'] ?? $file['url'] ?? null;
                $parsedPath = $path ? parse_url((string)$path, PHP_URL_PATH) : null;
                $filename = $parsedPath ? basename($parsedPath) : 'uploaded-file';
            }
            $data = ['fileName' => basename((string)$filename) ?: 'uploaded-file'];
        } else {
            return null;
        }

        $extension = strtolower((string)pathinfo($data['fileName'], PATHINFO_EXTENSION));
        $data['extension'] = $extension !== '' ? $extension : null;

        if (is_array($file)) {
            $mimeType = self::nullableString($file['mimeType'] ?? null);
            $size = $file['fileSize'] ?? $file['size'] ?? null;
            $uploadedAt = self::nullableString($file['uploadedAt'] ?? $fallbackUploadedAt);
            if ($mimeType !== null) {
                $data['mimeType'] = substr($mimeType, 0, 100);
            }
            if (is_numeric($size) && (int)$size >= 0) {
                $data['sizeBytes'] = (int)$size;
            }
            if ($uploadedAt !== null) {
                $data['uploadedAt'] = $uploadedAt;
            }
        } elseif ($fallbackUploadedAt !== null) {
            $data['uploadedAt'] = (string)$fallbackUploadedAt;
        }

        return array_filter($data, static fn($value) => $value !== null);
    }

    public static function mergeTimelineEvents(array $timelineRows, array $activityRows): array {
        $timelineEvents = [];
        foreach ($timelineRows as $row) {
            $timelineEvents[] = self::projectTimelineRow($row);
        }

        $activityEvents = [];
        foreach ($activityRows as $row) {
            $activityEvents[] = self::projectActivityRow($row);
        }

        $adminEventTypes = ['assigned', 'approved', 'rejected', 'additional_documents_requested'];
        foreach ($activityEvents as $activity) {
            if (!in_array($activity['eventType'], $adminEventTypes, true)) {
                continue;
            }
            $activityTime = strtotime((string)$activity['occurredAt']);
            $timelineEvents = array_values(array_filter(
                $timelineEvents,
                static function ($timeline) use ($activity, $activityTime) {
                    if ($timeline['eventType'] !== $activity['eventType']) {
                        return true;
                    }
                    $timelineTime = strtotime((string)$timeline['occurredAt']);
                    if ($activityTime === false || $timelineTime === false || abs($activityTime - $timelineTime) > 60) {
                        return true;
                    }
                    $timelineActor = $timeline['actor']['id'] ?? null;
                    $activityActor = $activity['actor']['id'] ?? null;
                    return $timelineActor !== null && $activityActor !== null && $timelineActor !== $activityActor;
                }
            ));
        }

        $events = array_merge($timelineEvents, $activityEvents);
        usort($events, static function ($left, $right) {
            $timeCompare = strcmp((string)$left['occurredAt'], (string)$right['occurredAt']);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }
            if ($left['source'] !== $right['source']) {
                return $left['source'] === 'timeline' ? -1 : 1;
            }
            return $left['sourceId'] <=> $right['sourceId'];
        });
        return $events;
    }

    public function search(): void {
        $scope = $this->requireKycScope();
        try {
            $input = self::normalizeSearchInput(array_diff_key($_GET, ['path' => true]));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $conditions = ['1 = 1'];
        $params = [];
        if (isset($input['submissionId'])) {
            $conditions[] = 's.id = :submission_id';
            $params['submission_id'] = $input['submissionId'];
        }
        if (isset($input['email'])) {
            $conditions[] = 'LOWER(cu.email) = LOWER(:client_email)';
            $params['client_email'] = $input['email'];
        }
        if (isset($input['country'])) {
            $conditions[] = "cu.country = COALESCE(
                (SELECT cl.code FROM countryList cl
                 WHERE UPPER(cl.code) = UPPER(:country)
                    OR UPPER(cl.name) = UPPER(:country)
                 LIMIT 1),
                :country_fallback
            )";
            $params['country'] = $input['country'];
            $params['country_fallback'] = $input['country'];
        }
        if (isset($input['status'])) {
            if ($input['status'] === 'pending') {
                $conditions[] = "s.submissionStatus IN ('pending', 'submitted')";
            } elseif ($input['status'] === 'requires_documents') {
                $conditions[] = "s.submissionStatus IN ('resubmit_required', 'pending_documents')";
            } else {
                $conditions[] = 's.submissionStatus = :submission_status';
                $params['submission_status'] = $input['status'];
            }
        }
        if (array_key_exists('assigned', $input)) {
            $conditions[] = $input['assigned'] ? 's.reviewedBy IS NOT NULL' : 's.reviewedBy IS NULL';
        }
        if (isset($input['reviewerId'])) {
            $conditions[] = 's.reviewedBy = :reviewer_id';
            $params['reviewer_id'] = $input['reviewerId'];
        }
        if (isset($input['templateId'])) {
            $conditions[] = 's.templateId = :template_id';
            $params['template_id'] = $input['templateId'];
        }
        if (isset($input['provider'])) {
            $conditions[] = "UPPER(COALESCE(t.thirdPartyProvider, g.provider, 'Local')) = UPPER(:provider)";
            $params['provider'] = $input['provider'];
        }

        $waitingExpression = "GREATEST(0, TIMESTAMPDIFF(
            HOUR,
            COALESCE(s.submittedAt, s.createdAt),
            CASE
                WHEN s.submissionStatus IN ('approved', 'rejected') AND s.reviewedAt IS NOT NULL
                    THEN s.reviewedAt
                ELSE NOW()
            END
        ))";
        if (isset($input['minWaitingHours'])) {
            $conditions[] = "{$waitingExpression} >= :min_waiting_hours";
            $params['min_waiting_hours'] = $input['minWaitingHours'];
        }
        $this->addScopeCondition($conditions, $params, $scope);
        $whereSql = implode(' AND ', $conditions);

        $fromSql = "FROM clientKycSubmissions s
            INNER JOIN clientUsers cu ON cu.id = s.clientId
            INNER JOIN kycTemplates t ON t.id = s.templateId
            LEFT JOIN adminUsers au ON au.id = s.reviewedBy
            LEFT JOIN externalKycTemplates et ON et.id = t.externalTemplateId
            LEFT JOIN externalKycGateways g ON g.id = et.gatewayId
            WHERE {$whereSql}";
        $count = $this->submissionModel->queryOne("SELECT COUNT(*) AS total {$fromSql}", $params);
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];

        $rows = $this->submissionModel->query(
            "SELECT
                s.id AS submissionId,
                s.clientId,
                cu.firstName,
                cu.lastName,
                cu.email AS clientEmail,
                cu.country,
                s.templateId,
                t.templateName,
                s.isThirdParty,
                t.isThirdPartyEnabled,
                COALESCE(t.thirdPartyProvider, g.provider) AS thirdPartyProvider,
                s.submissionStatus,
                s.submittedAt,
                s.reviewedBy AS reviewerId,
                COALESCE(au.fullName, au.username, '') AS reviewerName,
                COALESCE(ROUND(
                    (SELECT COUNT(*) FROM clientKycAnswers a WHERE a.submissionId = s.id)
                    / NULLIF((SELECT COUNT(*) FROM kycQuestions q WHERE q.templateId = s.templateId AND q.isActive = 1), 0)
                    * 100,
                    2
                ), 100) AS progressPercentage,
                {$waitingExpression} AS waitingHours
             {$fromSql}
             ORDER BY waitingHours DESC, COALESCE(s.submittedAt, s.createdAt) ASC, s.id ASC
             LIMIT " . (int)$input['limit'] . " OFFSET " . (int)$offset,
            $params
        );

        Response::success([
            'submissions' => array_map([self::class, 'projectSearchSubmission'], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total)
        ]);
    }

    public function getSummary(): void {
        [$submission] = $this->visibleSubmissionFromRequest();
        $questionRows = $this->loadAnswerRows((int)$submission['submissionId'], (int)$submission['templateId']);
        $questionnaire = $this->questionnaireSummary($questionRows);
        $documents = $this->loadDocumentData($submission, $questionRows);
        $latestRequest = $this->latestResubmissionRequest((int)$submission['submissionId']);
        $latestDecision = $this->latestDecision($submission);

        $attentionItems = [];
        $status = self::canonicalStatus($submission['submissionStatus'] ?? '');
        if (in_array($status, ['pending', 'under_review'], true) && empty($submission['reviewerId'])) {
            $attentionItems[] = ['code' => 'UNASSIGNED_REVIEWER', 'message' => 'No reviewer is assigned.'];
        }
        if (!empty($questionnaire['missingRequiredQuestions'])) {
            $attentionItems[] = [
                'code' => 'MISSING_REQUIRED_ANSWERS',
                'message' => count($questionnaire['missingRequiredQuestions']) . ' required question(s) are unanswered.'
            ];
        }
        if (!empty($documents['missingRequiredItems'])) {
            $attentionItems[] = [
                'code' => 'MISSING_REQUIRED_DOCUMENTS',
                'message' => count($documents['missingRequiredItems']) . ' required document item(s) are missing.'
            ];
        }
        if ($latestRequest && ($latestRequest['status'] ?? null) === 'pending') {
            $attentionItems[] = [
                'code' => 'RESUBMISSION_OUTSTANDING',
                'message' => 'A request for additional information is still pending.'
            ];
        }
        if (self::providerManaged($submission) && empty($questionRows) && $documents['totals']['submittedFiles'] === 0) {
            $attentionItems[] = [
                'code' => 'PROVIDER_MANAGED_DETAILS_UNAVAILABLE',
                'message' => 'Detailed answers and documents are managed by the verification provider.'
            ];
        }

        Response::success([
            'submissionId' => (int)$submission['submissionId'],
            'status' => $status,
            'client' => [
                'id' => (int)$submission['clientId'],
                'name' => self::clientName($submission),
                'maskedEmail' => self::maskEmail($submission['clientEmail'] ?? ''),
                'country' => $submission['country'] ?? null
            ],
            'template' => [
                'id' => (int)$submission['templateId'],
                'name' => $submission['templateName'] ?? null
            ],
            'provider' => self::providerProjection($submission),
            'dates' => [
                'createdAt' => $submission['createdAt'] ?? null,
                'submittedAt' => $submission['submittedAt'] ?? null,
                'reviewedAt' => $submission['reviewedAt'] ?? null,
                'updatedAt' => $submission['updatedAt'] ?? null
            ],
            'reviewer' => self::reviewerProjection($submission),
            'questionnaire' => $questionnaire,
            'documents' => $documents['summary'],
            'latestResubmissionRequest' => $latestRequest,
            'latestDecision' => $latestDecision,
            'attentionItems' => $attentionItems
        ]);
    }

    public function getAnswers(): void {
        [$submission] = $this->visibleSubmissionFromRequest();
        $rows = $this->loadAnswerRows((int)$submission['submissionId'], (int)$submission['templateId']);
        $categories = [];
        foreach ($rows as $row) {
            $categoryId = (int)($row['categoryId'] ?? 0);
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $row['categoryName'] ?? null,
                    'questions' => []
                ];
            }
            $categories[$categoryId]['questions'][] = self::projectAnswerRow($row);
        }

        Response::success([
            'submissionId' => (int)$submission['submissionId'],
            'provider' => self::providerProjection($submission),
            'categories' => array_values($categories),
            'summary' => $this->questionnaireSummary($rows)
        ]);
    }

    public function getDocuments(): void {
        [$submission] = $this->visibleSubmissionFromRequest();
        $questionRows = $this->loadAnswerRows((int)$submission['submissionId'], (int)$submission['templateId']);
        $documents = $this->loadDocumentData($submission, $questionRows);

        Response::success([
            'submissionId' => (int)$submission['submissionId'],
            'provider' => self::providerProjection($submission),
            'fileQuestions' => $documents['fileQuestions'],
            'templateDocuments' => $documents['templateDocuments'],
            'resubmissionRequests' => $documents['resubmissionRequests'],
            'missingRequiredItems' => $documents['missingRequiredItems'],
            'totals' => $documents['totals']
        ]);
    }

    public function getProgress(): void {
        [$submission] = $this->visibleSubmissionFromRequest();
        $rows = $this->loadAnswerRows((int)$submission['submissionId'], (int)$submission['templateId']);
        $questionnaire = $this->questionnaireSummary($rows);
        $documents = $this->loadDocumentData($submission, $rows);

        $requiredDocumentCount = $documents['totals']['requiredFileQuestions']
            + $documents['totals']['requiredTemplateDocuments'];
        $completedDocumentCount = $documents['totals']['completedFileQuestions']
            + $documents['totals']['signedTemplateDocuments'];
        $documentPercentage = $requiredDocumentCount === 0
            ? 100.0
            : round($completedDocumentCount / $requiredDocumentCount * 100, 2);

        Response::success([
            'submissionId' => (int)$submission['submissionId'],
            'status' => self::canonicalStatus($submission['submissionStatus'] ?? ''),
            'completionPercentage' => $questionnaire['completionPercentage'],
            'questionnaire' => [
                'totalQuestions' => $questionnaire['totalQuestions'],
                'answeredQuestions' => $questionnaire['answeredQuestions'],
                'requiredQuestions' => $questionnaire['requiredQuestions'],
                'answeredRequiredQuestions' => $questionnaire['answeredRequiredQuestions'],
                'missingRequiredQuestionIds' => array_map(
                    static fn($question) => $question['id'],
                    $questionnaire['missingRequiredQuestions']
                ),
                'completionPercentage' => $questionnaire['completionPercentage']
            ],
            'documents' => [
                'requiredItems' => $requiredDocumentCount,
                'completedItems' => $completedDocumentCount,
                'missingRequiredItems' => $documents['missingRequiredItems'],
                'completionPercentage' => $documentPercentage
            ],
            'overallComplete' => empty($questionnaire['missingRequiredQuestions'])
                && empty($documents['missingRequiredItems'])
        ]);
    }

    public function getTimeline(): void {
        [$submission] = $this->visibleSubmissionFromRequest();
        $submissionId = (int)$submission['submissionId'];
        $timelineRows = $this->submissionModel->query(
            "SELECT
                kt.*,
                COALESCE(au.fullName, au.username, '') AS createdByName
             FROM kycTimeline kt
             LEFT JOIN adminUsers au ON au.id = kt.createdBy
             WHERE kt.submissionId = :submission_id
             ORDER BY kt.createdAt ASC, kt.id ASC
             LIMIT 500",
            ['submission_id' => $submissionId]
        );
        $activityRows = $this->submissionModel->query(
            "SELECT
                al.*,
                COALESCE(au.fullName, au.username, '') AS performedByName
             FROM kycSubmissionActivityLog al
             LEFT JOIN adminUsers au ON au.id = al.performedBy
             WHERE al.submissionId = :submission_id
             ORDER BY al.createdAt ASC, al.id ASC
             LIMIT 500",
            ['submission_id' => $submissionId]
        );
        $activityRows = $this->enrichAssignmentReviewerNames($activityRows);

        Response::success([
            'submissionId' => $submissionId,
            'events' => self::mergeTimelineEvents($timelineRows, $activityRows),
            'truncated' => count($timelineRows) >= 500 || count($activityRows) >= 500
        ]);
    }

    private function requireKycScope(): array {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkAnyPermission(['page_kyclist_readonly']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_kyclist');
        if (($scope['scope'] ?? 'none') === 'none') {
            Response::forbidden('You do not have permission to view KYC submissions');
        }
        return $scope;
    }

    private function visibleSubmissionFromRequest(): array {
        $scope = $this->requireKycScope();
        try {
            $submissionId = self::normalizeSubmissionId(array_diff_key($_GET, ['path' => true]));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        $conditions = ['s.id = :submission_id'];
        $params = ['submission_id' => $submissionId];
        $this->addScopeCondition($conditions, $params, $scope);
        $submission = $this->submissionModel->queryOne(
            "SELECT
                s.id AS submissionId,
                s.clientId,
                cu.firstName,
                cu.lastName,
                cu.email AS clientEmail,
                cu.country,
                s.templateId,
                t.templateName,
                s.isThirdParty,
                t.isThirdPartyEnabled,
                COALESCE(t.thirdPartyProvider, g.provider) AS thirdPartyProvider,
                s.submissionStatus,
                s.submittedAt,
                s.reviewedAt,
                s.reviewedBy AS reviewerId,
                COALESCE(au.fullName, au.username, '') AS reviewerName,
                s.approvalNotes,
                s.rejectionReason,
                s.createdAt,
                s.updatedAt
             FROM clientKycSubmissions s
             INNER JOIN clientUsers cu ON cu.id = s.clientId
             INNER JOIN kycTemplates t ON t.id = s.templateId
             LEFT JOIN adminUsers au ON au.id = s.reviewedBy
             LEFT JOIN externalKycTemplates et ON et.id = t.externalTemplateId
             LEFT JOIN externalKycGateways g ON g.id = et.gatewayId
             WHERE " . implode(' AND ', $conditions) . "
             LIMIT 1",
            $params
        );
        if (!$submission) {
            Response::notFound('KYC submission not found');
        }
        return [$submission, $scope];
    }

    private function addScopeCondition(array &$conditions, array &$params, array $scope): void {
        if (($scope['scope'] ?? 'none') !== 'own') {
            return;
        }
        $conditions[] = 's.clientId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
        $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
    }

    private function loadAnswerRows(int $submissionId, int $templateId): array {
        return $this->submissionModel->query(
            "SELECT
                q.id AS questionId,
                q.questionNumber,
                q.questionText,
                q.questionType,
                q.isRequired,
                q.displayOrder,
                c.id AS categoryId,
                c.categoryName,
                c.displayOrder AS categoryDisplayOrder,
                a.id AS answerId,
                a.answerText,
                a.answerValues,
                a.answerDate,
                a.answerNumber,
                a.uploadedFiles,
                a.answeredAt,
                a.updatedAt AS answerUpdatedAt
             FROM kycQuestions q
             INNER JOIN kycQuestionCategories c ON c.id = q.categoryId
             LEFT JOIN clientKycAnswers a
                ON a.questionId = q.id AND a.submissionId = :submission_id
             WHERE q.templateId = :template_id AND q.isActive = 1
             ORDER BY c.displayOrder ASC, q.displayOrder ASC, q.id ASC",
            ['submission_id' => $submissionId, 'template_id' => $templateId]
        );
    }

    private function questionnaireSummary(array $rows): array {
        $answered = 0;
        $required = 0;
        $answeredRequired = 0;
        $missingRequired = [];
        foreach ($rows as $row) {
            $present = self::answerPresent($row);
            $isRequired = self::databaseBoolean($row['isRequired'] ?? false);
            if ($present) {
                $answered++;
            }
            if ($isRequired) {
                $required++;
                if ($present) {
                    $answeredRequired++;
                } else {
                    $missingRequired[] = [
                        'id' => (int)$row['questionId'],
                        'number' => isset($row['questionNumber']) ? (int)$row['questionNumber'] : null,
                        'question' => $row['questionText'] ?? null,
                        'type' => $row['questionType'] ?? null
                    ];
                }
            }
        }
        $completion = $required === 0 ? 100.0 : round($answeredRequired / $required * 100, 2);
        return [
            'totalQuestions' => count($rows),
            'answeredQuestions' => $answered,
            'requiredQuestions' => $required,
            'answeredRequiredQuestions' => $answeredRequired,
            'missingRequiredQuestions' => $missingRequired,
            'completionPercentage' => $completion
        ];
    }

    private function loadDocumentData(array $submission, array $questionRows): array {
        $submissionId = (int)$submission['submissionId'];
        $templateId = (int)$submission['templateId'];
        $documentTypeRows = $this->submissionModel->query(
            "SELECT
                qdt.questionId,
                qdt.documentType,
                qdt.documentDisplayName,
                qdt.isRequired
             FROM kycQuestionDocumentTypes qdt
             INNER JOIN kycQuestions q ON q.id = qdt.questionId
             WHERE q.templateId = :template_id AND q.isActive = 1
             ORDER BY qdt.displayOrder ASC, qdt.id ASC",
            ['template_id' => $templateId]
        );
        $typesByQuestion = [];
        foreach ($documentTypeRows as $typeRow) {
            $questionId = (int)$typeRow['questionId'];
            $typesByQuestion[$questionId][] = [
                'code' => $typeRow['documentType'] ?? null,
                'name' => $typeRow['documentDisplayName'] ?? null,
                'required' => self::databaseBoolean($typeRow['isRequired'] ?? false)
            ];
        }

        $fileQuestions = [];
        $missingRequiredItems = [];
        $submittedFiles = 0;
        $requiredFileQuestions = 0;
        $completedFileQuestions = 0;
        foreach ($questionRows as $row) {
            if (($row['questionType'] ?? null) !== 'file_upload') {
                continue;
            }
            $questionId = (int)$row['questionId'];
            $rawFiles = self::decodeJsonArray($row['uploadedFiles'] ?? null);
            $files = [];
            foreach ($rawFiles as $rawFile) {
                $metadata = self::sanitizeFileMetadata(
                    $rawFile,
                    $row['answerUpdatedAt'] ?? $row['answeredAt'] ?? null
                );
                if ($metadata) {
                    $files[] = $metadata;
                }
            }
            $required = self::databaseBoolean($row['isRequired'] ?? false);
            if ($required) {
                $requiredFileQuestions++;
                if (!empty($files)) {
                    $completedFileQuestions++;
                } else {
                    $missingRequiredItems[] = [
                        'source' => 'question_upload',
                        'id' => $questionId,
                        'name' => $row['questionText'] ?? null
                    ];
                }
            }
            $submittedFiles += count($files);
            $fileQuestions[] = [
                'questionId' => $questionId,
                'question' => $row['questionText'] ?? null,
                'required' => $required,
                'status' => !empty($files) ? 'submitted' : ($required ? 'missing' : 'not_submitted'),
                'acceptedDocumentTypes' => $typesByQuestion[$questionId] ?? [],
                'files' => $files
            ];
        }

        $templateRows = $this->submissionModel->query(
            "SELECT
                td.id AS templateDocumentId,
                td.documentTitle,
                sig.id AS signatureId,
                sig.signedAt
             FROM kycTemplateDocuments td
             LEFT JOIN clientKycDocumentSignatures sig
                ON sig.templateDocumentId = td.id AND sig.submissionId = :submission_id
             WHERE td.templateId = :template_id AND td.isActive = 1
             ORDER BY td.displayOrder ASC, td.id ASC",
            ['submission_id' => $submissionId, 'template_id' => $templateId]
        );
        $templateDocuments = [];
        $signedTemplateDocuments = 0;
        foreach ($templateRows as $row) {
            $signed = !empty($row['signatureId']);
            if ($signed) {
                $signedTemplateDocuments++;
            } else {
                $missingRequiredItems[] = [
                    'source' => 'template_document',
                    'id' => (int)$row['templateDocumentId'],
                    'name' => $row['documentTitle'] ?? null
                ];
            }
            $templateDocuments[] = [
                'documentId' => (int)$row['templateDocumentId'],
                'title' => $row['documentTitle'] ?? null,
                'required' => true,
                'status' => $signed ? 'signed' : 'missing',
                'signedAt' => $row['signedAt'] ?? null
            ];
        }

        $resubmissionRequests = $this->resubmissionDocuments($submissionId);
        foreach ($resubmissionRequests as $request) {
            foreach ($request['items'] as $item) {
                $submittedFiles += count($item['files']);
                if ($request['status'] === 'pending' && $item['required'] && empty($item['files'])) {
                    $missingRequiredItems[] = [
                        'source' => 'resubmission_request',
                        'id' => $item['itemId'],
                        'name' => $item['name'],
                        'requestId' => $request['requestId']
                    ];
                }
            }
        }

        $totals = [
            'submittedFiles' => $submittedFiles,
            'requiredFileQuestions' => $requiredFileQuestions,
            'completedFileQuestions' => $completedFileQuestions,
            'requiredTemplateDocuments' => count($templateDocuments),
            'signedTemplateDocuments' => $signedTemplateDocuments,
            'resubmissionRequests' => count($resubmissionRequests),
            'missingRequiredItems' => count($missingRequiredItems)
        ];

        return [
            'fileQuestions' => $fileQuestions,
            'templateDocuments' => $templateDocuments,
            'resubmissionRequests' => $resubmissionRequests,
            'missingRequiredItems' => $missingRequiredItems,
            'totals' => $totals,
            'summary' => [
                'submittedFiles' => $submittedFiles,
                'requiredFileQuestions' => $requiredFileQuestions,
                'completedFileQuestions' => $completedFileQuestions,
                'requiredTemplateDocuments' => count($templateDocuments),
                'signedTemplateDocuments' => $signedTemplateDocuments,
                'missingRequiredItems' => $missingRequiredItems
            ]
        ];
    }

    private function resubmissionDocuments(int $submissionId): array {
        $requests = $this->submissionModel->query(
            "SELECT
                rr.*,
                COALESCE(au.fullName, au.username, '') AS requestedByName
             FROM kycResubmitRequests rr
             LEFT JOIN adminUsers au ON au.id = rr.requestedBy
             WHERE rr.submissionId = :submission_id
             ORDER BY rr.createdAt ASC, rr.id ASC",
            ['submission_id' => $submissionId]
        );
        if (empty($requests)) {
            return [];
        }
        $answers = $this->submissionModel->query(
            "SELECT * FROM kycResubmitAnswers
             WHERE submissionId = :submission_id
             ORDER BY requestId ASC, submittedAt ASC, id ASC",
            ['submission_id' => $submissionId]
        );
        $answersByRequestAndItem = [];
        foreach ($answers as $answer) {
            $answersByRequestAndItem[(int)$answer['requestId']][(string)$answer['itemId']] = $answer;
        }

        $result = [];
        foreach ($requests as $request) {
            $requestId = (int)$request['id'];
            $items = self::decodeJsonArray($request['requestedItems'] ?? null);
            $projectedItems = [];
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemType = strtolower((string)($item['type'] ?? $item['itemType'] ?? 'document'));
                $questionType = strtolower((string)($item['questionType'] ?? ''));
                if ($itemType !== 'document' && $questionType !== 'file_upload') {
                    continue;
                }
                $itemId = (string)($item['itemId'] ?? $index);
                $answer = $answersByRequestAndItem[$requestId][$itemId] ?? null;
                $rawFiles = $answer ? self::decodeJsonArray($answer['uploadedFiles'] ?? null) : [];
                $files = [];
                foreach ($rawFiles as $rawFile) {
                    $metadata = self::sanitizeFileMetadata($rawFile, $answer['submittedAt'] ?? null);
                    if ($metadata) {
                        $files[] = $metadata;
                    }
                }
                $projectedItems[] = [
                    'itemId' => $itemId,
                    'type' => $itemType,
                    'name' => self::requestedItemName($item),
                    'documentType' => $item['documentType'] ?? null,
                    'required' => true,
                    'status' => !empty($files) ? 'submitted' : (($request['status'] ?? null) === 'pending' ? 'missing' : 'not_submitted'),
                    'files' => $files
                ];
            }
            $result[] = [
                'requestId' => $requestId,
                'status' => $request['status'] ?? null,
                'requestedAt' => $request['requestedAt'] ?? $request['createdAt'] ?? null,
                'completedAt' => $request['completedAt'] ?? null,
                'requestedBy' => empty($request['requestedBy']) ? null : [
                    'id' => (int)$request['requestedBy'],
                    'name' => self::nullableString($request['requestedByName'] ?? null)
                ],
                'items' => $projectedItems
            ];
        }
        return $result;
    }

    private function latestResubmissionRequest(int $submissionId): ?array {
        $row = $this->submissionModel->queryOne(
            "SELECT
                rr.*,
                COALESCE(au.fullName, au.username, '') AS requestedByName
             FROM kycResubmitRequests rr
             LEFT JOIN adminUsers au ON au.id = rr.requestedBy
             WHERE rr.submissionId = :submission_id
             ORDER BY rr.createdAt DESC, rr.id DESC
             LIMIT 1",
            ['submission_id' => $submissionId]
        );
        if (!$row) {
            return null;
        }
        return [
            'requestId' => (int)$row['id'],
            'status' => $row['status'] ?? null,
            'requestedAt' => $row['requestedAt'] ?? $row['createdAt'] ?? null,
            'completedAt' => $row['completedAt'] ?? null,
            'requestedBy' => empty($row['requestedBy']) ? null : [
                'id' => (int)$row['requestedBy'],
                'name' => self::nullableString($row['requestedByName'] ?? null)
            ],
            'itemCount' => count(self::decodeJsonArray($row['requestedItems'] ?? null)),
            'notes' => self::nullableString($row['additionalNotes'] ?? null)
        ];
    }

    private function latestDecision(array $submission): ?array {
        $row = $this->submissionModel->queryOne(
            "SELECT
                al.activityType,
                al.description,
                al.createdAt,
                al.performedBy,
                COALESCE(au.fullName, au.username, '') AS performedByName
             FROM kycSubmissionActivityLog al
             LEFT JOIN adminUsers au ON au.id = al.performedBy
             WHERE al.submissionId = :submission_id
               AND al.activityType IN ('approved', 'rejected')
             ORDER BY al.createdAt DESC, al.id DESC
             LIMIT 1",
            ['submission_id' => (int)$submission['submissionId']]
        );
        if ($row) {
            $type = $row['activityType'];
            return [
                'type' => $type,
                'decidedAt' => $row['createdAt'] ?? null,
                'decidedBy' => empty($row['performedBy']) ? null : [
                    'id' => (int)$row['performedBy'],
                    'name' => self::nullableString($row['performedByName'] ?? null)
                ],
                'reason' => $type === 'rejected'
                    ? self::nullableString($submission['rejectionReason'] ?? $row['description'] ?? null)
                    : null,
                'notes' => $type === 'approved'
                    ? self::nullableString($submission['approvalNotes'] ?? null)
                    : null
            ];
        }

        $status = self::canonicalStatus($submission['submissionStatus'] ?? '');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return null;
        }
        return [
            'type' => $status,
            'decidedAt' => $submission['reviewedAt'] ?? null,
            'decidedBy' => self::reviewerProjection($submission),
            'reason' => $status === 'rejected' ? self::nullableString($submission['rejectionReason'] ?? null) : null,
            'notes' => $status === 'approved' ? self::nullableString($submission['approvalNotes'] ?? null) : null
        ];
    }

    private function enrichAssignmentReviewerNames(array $rows): array {
        $reviewerIds = [];
        foreach ($rows as $row) {
            if (($row['activityType'] ?? null) !== 'assigned') {
                continue;
            }
            if (preg_match('/reviewer ID:\s*(\d+)/i', (string)($row['description'] ?? ''), $matches)) {
                $reviewerIds[] = (int)$matches[1];
            }
        }
        $reviewerIds = array_values(array_unique(array_filter($reviewerIds)));
        if (empty($reviewerIds)) {
            return $rows;
        }

        $placeholders = [];
        $params = [];
        foreach ($reviewerIds as $index => $reviewerId) {
            $key = 'reviewer_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $reviewerId;
        }
        $reviewers = $this->submissionModel->query(
            "SELECT id, COALESCE(fullName, username, '') AS reviewerName
             FROM adminUsers
             WHERE id IN (" . implode(', ', $placeholders) . ")",
            $params
        );
        $namesById = [];
        foreach ($reviewers as $reviewer) {
            $namesById[(int)$reviewer['id']] = self::nullableString($reviewer['reviewerName'] ?? null);
        }
        foreach ($rows as &$row) {
            if (($row['activityType'] ?? null) !== 'assigned') {
                continue;
            }
            if (preg_match('/reviewer ID:\s*(\d+)/i', (string)($row['description'] ?? ''), $matches)) {
                $row['assignedReviewerName'] = $namesById[(int)$matches[1]] ?? null;
            }
        }
        unset($row);
        return $rows;
    }

    private static function projectAnswerRow(array $row): array {
        $present = self::answerPresent($row);
        $value = self::answerValue($row);
        if (($row['questionType'] ?? null) === 'file_upload') {
            $value = ['fileCount' => count(self::decodeJsonArray($row['uploadedFiles'] ?? null))];
        }
        return [
            'id' => (int)$row['questionId'],
            'number' => isset($row['questionNumber']) ? (int)$row['questionNumber'] : null,
            'question' => $row['questionText'] ?? null,
            'type' => $row['questionType'] ?? null,
            'required' => self::databaseBoolean($row['isRequired'] ?? false),
            'answered' => $present,
            'missing' => self::databaseBoolean($row['isRequired'] ?? false) && !$present,
            'value' => $value,
            'answeredAt' => $row['answerUpdatedAt'] ?? $row['answeredAt'] ?? null
        ];
    }

    private static function answerPresent(array $row): bool {
        if (empty($row['answerId'])) {
            return false;
        }
        $value = self::answerValue($row);
        if (is_array($value)) {
            return count($value) > 0;
        }
        if ($value === null) {
            return false;
        }
        return !is_string($value) || trim($value) !== '';
    }

    private static function answerValue(array $row) {
        switch ($row['questionType'] ?? '') {
            case 'number':
                return $row['answerNumber'] !== null ? (float)$row['answerNumber'] : null;
            case 'date':
                return $row['answerDate'] ?? null;
            case 'multiple_choice':
                return self::decodeJsonArray($row['answerValues'] ?? null);
            case 'file_upload':
                return self::decodeJsonArray($row['uploadedFiles'] ?? null);
            default:
                return $row['answerText'] ?? null;
        }
    }

    private static function projectTimelineRow(array $row): array {
        return [
            'source' => 'timeline',
            'sourceId' => (int)($row['id'] ?? 0),
            'eventType' => self::canonicalEventType($row['eventType'] ?? ''),
            'title' => self::sanitizeText($row['eventTitle'] ?? ''),
            'description' => self::sanitizeText($row['eventDescription'] ?? ''),
            'status' => $row['eventStatus'] ?? 'completed',
            'occurredAt' => $row['createdAt'] ?? null,
            'actor' => self::actorProjection($row['createdBy'] ?? null, $row['createdByName'] ?? null),
            'metadata' => self::sanitizeTimelineMetadata($row['eventData'] ?? null)
        ];
    }

    private static function projectActivityRow(array $row): array {
        $eventType = self::canonicalEventType($row['activityType'] ?? 'activity');
        $metadata = self::sanitizeTimelineMetadata($row['metadata'] ?? null);
        if (
            $eventType === 'assigned'
            && preg_match('/reviewer ID:\s*(\d+)/i', (string)($row['description'] ?? ''), $matches)
        ) {
            $metadata['reviewerId'] = (int)$matches[1];
            $reviewerName = self::nullableString($row['assignedReviewerName'] ?? null);
            if ($reviewerName !== null) {
                $metadata['reviewerName'] = $reviewerName;
            }
        }
        return [
            'source' => 'activity',
            'sourceId' => (int)($row['id'] ?? 0),
            'eventType' => $eventType,
            'title' => ucwords(str_replace('_', ' ', $eventType)),
            'description' => self::sanitizeText($row['description'] ?? ''),
            'status' => 'completed',
            'occurredAt' => $row['createdAt'] ?? null,
            'actor' => self::actorProjection($row['performedBy'] ?? null, $row['performedByName'] ?? null),
            'metadata' => $metadata
        ];
    }

    private static function canonicalEventType($type): string {
        $type = strtolower(trim((string)$type));
        $aliases = [
            'submission_created' => 'application_started',
            'submitted' => 'application_submitted',
            'resubmit_required' => 'additional_documents_requested'
        ];
        return $aliases[$type] ?? $type;
    }

    private static function sanitizeTimelineMetadata($metadata): array {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($metadata)) {
            return [];
        }
        $safe = [];
        foreach (['requestId', 'questionId', 'filename', 'reviewerId', 'previousReviewerId', 'rejectionReason', 'notes'] as $key) {
            if (array_key_exists($key, $metadata) && !is_array($metadata[$key]) && !is_object($metadata[$key])) {
                $safe[$key] = is_string($metadata[$key])
                    ? self::sanitizeText($metadata[$key])
                    : $metadata[$key];
            }
        }
        if (isset($metadata['items']) && is_array($metadata['items'])) {
            $safe['items'] = [];
            foreach ($metadata['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $safe['items'][] = array_filter([
                    'type' => $item['type'] ?? $item['itemType'] ?? null,
                    'itemId' => $item['itemId'] ?? $item['id'] ?? null,
                    'name' => self::requestedItemName($item),
                    'questionType' => $item['questionType'] ?? null,
                    'documentType' => $item['documentType'] ?? null
                ], static fn($value) => $value !== null && $value !== '');
            }
        }
        return $safe;
    }

    private static function sanitizeText($value): string {
        $text = trim((string)$value);
        return preg_replace('/https?:\/\/\S+/i', '[redacted URL]', $text) ?? $text;
    }

    private static function requestedItemName(array $item): ?string {
        foreach (['name', 'title', 'documentName', 'questionText'] as $key) {
            $value = self::nullableString($item[$key] ?? null);
            if ($value !== null) {
                return self::sanitizeText($value);
            }
        }
        return null;
    }

    private static function providerProjection(array $row): array {
        $name = self::nullableString($row['thirdPartyProvider'] ?? null);
        return [
            'name' => $name ?? 'Local',
            'managedExternally' => self::providerManaged($row),
            'detailsSource' => self::providerManaged($row) ? 'local_snapshot' : 'local'
        ];
    }

    private static function providerManaged(array $row): bool {
        return self::databaseBoolean($row['isThirdParty'] ?? false)
            || self::databaseBoolean($row['isThirdPartyEnabled'] ?? false);
    }

    private static function reviewerProjection(array $row): ?array {
        $reviewerId = self::nullablePositiveInteger($row['reviewerId'] ?? null);
        if ($reviewerId === null) {
            return null;
        }
        return [
            'id' => $reviewerId,
            'name' => self::nullableString($row['reviewerName'] ?? null)
        ];
    }

    private static function actorProjection($id, $name): ?array {
        $id = self::nullablePositiveInteger($id);
        if ($id === null) {
            return null;
        }
        return ['id' => $id, 'name' => self::nullableString($name)];
    }

    private static function clientName(array $row): string {
        $name = trim(trim((string)($row['firstName'] ?? '')) . ' ' . trim((string)($row['lastName'] ?? '')));
        return $name !== '' ? $name : 'Client #' . (int)($row['clientId'] ?? 0);
    }

    private static function pagination(int $page, int $limit, int $total): array {
        $totalPages = $total === 0 ? 0 : (int)ceil($total / $limit);
        return [
            'page' => $page,
            'limit' => $limit,
            'perPage' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'hasMore' => $page * $limit < $total
        ];
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

    private static function normalizeNonNegativeInteger($value, string $name, int $maximum): int {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $number = (int)trim($value);
        } else {
            throw new InvalidArgumentException("{$name} must be an integer between 0 and {$maximum}.");
        }
        if ($number < 0 || $number > $maximum) {
            throw new InvalidArgumentException("{$name} must be an integer between 0 and {$maximum}.");
        }
        return $number;
    }

    private static function normalizeBoolean($value, string $name): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true' || $normalized === '1') {
                return true;
            }
            if ($normalized === 'false' || $normalized === '0') {
                return false;
            }
        }
        throw new InvalidArgumentException("{$name} must be a boolean.");
    }

    private static function nullablePositiveInteger($value): ?int {
        if ($value === null || $value === '' || !is_numeric($value) || (int)$value < 1) {
            return null;
        }
        return (int)$value;
    }

    private static function nullableString($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function databaseBoolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes'], true);
    }

    private static function decodeJsonArray($value): array {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
