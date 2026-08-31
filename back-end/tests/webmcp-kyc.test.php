<?php

require_once __DIR__ . '/../controllers/WebMcpKycController.php';

function assertKycTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertKycSame($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true)
        );
    }
}

function assertKycThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }

    throw new RuntimeException($message);
}

assertKycThrows(
    static fn() => WebMcpKycController::normalizeSearchInput([]),
    'Expected an empty KYC search to be rejected.'
);
assertKycThrows(
    static fn() => WebMcpKycController::normalizeSearchInput(['unexpected' => 'value']),
    'Expected unsupported KYC search fields to be rejected.'
);
assertKycThrows(
    static fn() => WebMcpKycController::normalizeSearchInput(['status' => 'fraudulent']),
    'Expected an unsupported KYC status to be rejected.'
);
assertKycThrows(
    static fn() => WebMcpKycController::normalizeSearchInput(['assigned' => 'yes']),
    'Expected an invalid assigned flag to be rejected.'
);
assertKycThrows(
    static fn() => WebMcpKycController::normalizeSearchInput(['country' => 'ID', 'limit' => 51]),
    'Expected a KYC search limit above 50 to be rejected.'
);

assertKycSame(
    [
        'email' => 'john@example.com',
        'country' => 'Indonesia',
        'status' => 'pending',
        'assigned' => false,
        'minWaitingHours' => 24,
        'page' => 2,
        'limit' => 10
    ],
    WebMcpKycController::normalizeSearchInput([
        'email' => ' john@example.com ',
        'country' => ' Indonesia ',
        'status' => 'submitted',
        'assigned' => 'false',
        'minWaitingHours' => '24',
        'page' => '2',
        'limit' => '10'
    ]),
    'Expected KYC search filters to be normalized.'
);
assertKycSame(
    'requires_documents',
    WebMcpKycController::canonicalStatus('resubmit_required'),
    'Expected resubmit_required to use the public requires_documents status.'
);
assertKycSame(
    'requires_documents',
    WebMcpKycController::canonicalStatus('pending_documents'),
    'Expected pending_documents to use the public requires_documents status.'
);
assertKycSame(
    123,
    WebMcpKycController::normalizeSubmissionId(['submissionId' => '123']),
    'Expected string submission IDs to be normalized.'
);
assertKycThrows(
    static fn() => WebMcpKycController::normalizeSubmissionId(['submissionId' => 0]),
    'Expected an invalid submission ID to be rejected.'
);

assertKycSame(
    'j***@example.com',
    WebMcpKycController::maskEmail('john@example.com'),
    'Expected queue email addresses to be masked.'
);
assertKycSame(
    '*@example.com',
    WebMcpKycController::maskEmail('a@example.com'),
    'Expected one-character email local parts to be masked.'
);

$projectedSubmission = WebMcpKycController::projectSearchSubmission([
    'submissionId' => '123',
    'clientId' => '42',
    'firstName' => 'John',
    'lastName' => 'Smith',
    'clientEmail' => 'john@example.com',
    'country' => 'ID',
    'templateId' => '7',
    'templateName' => 'Standard KYC',
    'submissionStatus' => 'submitted',
    'submittedAt' => '2026-08-30 10:00:00',
    'progressPercentage' => '75.50',
    'reviewerId' => null,
    'reviewerName' => '',
    'waitingHours' => '27',
    'isThirdParty' => '1',
    'thirdPartyProvider' => 'Sumsub',
    'externalId' => 'must-not-be-returned',
    'detailUrl' => 'https://provider.example/applicant/secret'
]);

assertKycSame(123, $projectedSubmission['submissionId'], 'Expected a numeric submission ID.');
assertKycSame('j***@example.com', $projectedSubmission['client']['maskedEmail'], 'Expected a masked queue email.');
assertKycTrue(!isset($projectedSubmission['client']['id']), 'Expected queue results to omit internal client IDs.');
assertKycSame('pending', $projectedSubmission['status'], 'Expected a canonical queue status.');
assertKycSame(true, $projectedSubmission['provider']['managedExternally'], 'Expected provider-managed submissions to be marked.');
assertKycSame(null, $projectedSubmission['reviewer'], 'Expected unassigned submissions to have no reviewer.');
assertKycTrue(!isset($projectedSubmission['externalId']), 'Expected provider external IDs to be omitted.');
assertKycTrue(strpos(json_encode($projectedSubmission), 'provider.example') === false, 'Expected provider URLs to be omitted.');

$file = WebMcpKycController::sanitizeFileMetadata([
    'fileName' => 'passport.pdf',
    'filePath' => 'https://bucket.example/private/passport.pdf',
    'downloadUrl' => 'https://download.example/private/passport.pdf',
    's3Key' => 'kyc/private/passport.pdf',
    'fileSize' => '2048',
    'mimeType' => 'application/pdf',
    'uploadedAt' => '2026-08-30T10:15:00Z'
]);
assertKycSame(
    [
        'fileName' => 'passport.pdf',
        'extension' => 'pdf',
        'mimeType' => 'application/pdf',
        'sizeBytes' => 2048,
        'uploadedAt' => '2026-08-30T10:15:00Z'
    ],
    $file,
    'Expected uploaded files to expose metadata only.'
);

$events = WebMcpKycController::mergeTimelineEvents(
    [
        [
            'id' => 1,
            'eventType' => 'documents_uploaded',
            'eventTitle' => 'Document uploaded',
            'eventDescription' => 'Passport uploaded',
            'eventStatus' => 'completed',
            'eventData' => json_encode([
                'questionId' => 12,
                'filename' => 'passport.pdf',
                'url' => 'https://bucket.example/private/passport.pdf',
                's3Key' => 'kyc/private/passport.pdf'
            ]),
            'createdAt' => '2026-08-30 10:15:00',
            'createdBy' => null,
            'createdByName' => null
        ],
        [
            'id' => 2,
            'eventType' => 'approved',
            'eventTitle' => 'Approved',
            'eventDescription' => 'Approved by administrator',
            'eventStatus' => 'completed',
            'eventData' => null,
            'createdAt' => '2026-08-30 11:00:00',
            'createdBy' => 8,
            'createdByName' => 'Sarah Reviewer'
        ]
    ],
    [
        [
            'id' => 10,
            'activityType' => 'assigned',
            'description' => 'Assigned to reviewer ID: 8',
            'metadata' => null,
            'createdAt' => '2026-08-30 10:30:00',
            'performedBy' => 3,
            'performedByName' => 'Admin User',
            'assignedReviewerName' => 'Sarah Reviewer'
        ],
        [
            'id' => 11,
            'activityType' => 'approved',
            'description' => 'KYC approved',
            'metadata' => null,
            'createdAt' => '2026-08-30 11:00:20',
            'performedBy' => 8,
            'performedByName' => 'Sarah Reviewer'
        ]
    ]
);

assertKycSame(3, count($events), 'Expected duplicate approval events to be merged.');
assertKycSame('documents_uploaded', $events[0]['eventType'], 'Expected chronological timeline ordering.');
assertKycSame('assigned', $events[1]['eventType'], 'Expected assignments to appear in the timeline.');
assertKycSame(
    ['reviewerId' => 8, 'reviewerName' => 'Sarah Reviewer'],
    $events[1]['metadata'],
    'Expected assignment events to identify the assigned reviewer.'
);
assertKycSame('activity', $events[2]['source'], 'Expected the audit activity to win duplicate admin events.');
assertKycTrue(strpos(json_encode($events), 'bucket.example') === false, 'Expected timeline URLs to be removed.');
assertKycTrue(strpos(json_encode($events), 's3Key') === false, 'Expected timeline storage keys to be removed.');

assertKycSame(
    [
        'admin/search-kyc' => 'search',
        'admin/get-kyc' => 'getSummary',
        'admin/get-kyc-answers' => 'getAnswers',
        'admin/get-kyc-documents' => 'getDocuments',
        'admin/get-kyc-progress' => 'getProgress',
        'admin/get-kyc-timeline' => 'getTimeline'
    ],
    WebMcpKycController::routeHandlers(),
    'Expected the six approved KYC WebMCP routes.'
);

echo "webmcp KYC validation tests passed\n";
