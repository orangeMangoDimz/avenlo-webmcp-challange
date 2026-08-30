<?php

require_once __DIR__ . '/S3Uploader.php';

class UploadedFilePayload {
    public static function normalizeForStorage($files) {
        $items = self::toFileList($files);
        $normalized = [];

        foreach ($items as $item) {
            $file = self::normalizeSingleFile($item, false);
            if ($file !== null) {
                $normalized[] = $file;
            }
        }

        return $normalized;
    }

    public static function normalizeForResponse($files, $includeDownloadUrl = true) {
        $items = self::toFileList($files);
        $normalized = [];
        $s3Uploader = null;

        foreach ($items as $item) {
            $file = self::normalizeSingleFile($item, $includeDownloadUrl, $s3Uploader);
            if ($file !== null) {
                $normalized[] = $file;
            }
        }

        return $normalized;
    }

    private static function normalizeSingleFile($file, $includeDownloadUrl = false, &$s3Uploader = null) {
        if (is_string($file)) {
            $filePath = trim($file);
            if ($filePath === '') {
                return null;
            }

            return self::buildNormalizedFile([
                'filePath' => $filePath
            ], $includeDownloadUrl, $s3Uploader);
        }

        if (!is_array($file)) {
            return null;
        }

        return self::buildNormalizedFile($file, $includeDownloadUrl, $s3Uploader);
    }

    private static function buildNormalizedFile($source, $includeDownloadUrl = false, &$s3Uploader = null) {
        $filePath = self::firstNonEmptyString([
            $source['filePath'] ?? null,
            $source['path'] ?? null,
            $source['s3Key'] ?? null,
            self::getNestedValue($source, 'response.filePath'),
            self::getNestedValue($source, 'response.s3Key'),
            self::getNestedValue($source, 'response.data.filePath'),
            self::getNestedValue($source, 'response.data.s3Key'),
            $source['s3Url'] ?? null,
            $source['url'] ?? null,
            $source['downloadUrl'] ?? null,
            self::getNestedValue($source, 'response.s3Url'),
            self::getNestedValue($source, 'response.url'),
            self::getNestedValue($source, 'response.downloadUrl'),
            self::getNestedValue($source, 'response.data.s3Url'),
            self::getNestedValue($source, 'response.data.url'),
            self::getNestedValue($source, 'response.data.downloadUrl')
        ]);

        $s3Key = self::firstNonEmptyString([
            $source['s3Key'] ?? null,
            self::getNestedValue($source, 'response.s3Key'),
            self::getNestedValue($source, 'response.data.s3Key')
        ]);

        $s3Url = self::firstNonEmptyString([
            $source['s3Url'] ?? null,
            $source['url'] ?? null,
            self::getNestedValue($source, 'response.s3Url'),
            self::getNestedValue($source, 'response.url'),
            self::getNestedValue($source, 'response.data.s3Url'),
            self::getNestedValue($source, 'response.data.url')
        ]);

        if ($filePath === null && $s3Url !== null) {
            $filePath = $s3Url;
        }

        if ($s3Url === null && $filePath !== null && preg_match('/^https?:\/\//', $filePath)) {
            $s3Url = $filePath;
        }

        $fileName = self::firstNonEmptyString([
            $source['fileName'] ?? null,
            $source['filename'] ?? null,
            $source['name'] ?? null,
            self::getNestedValue($source, 'response.fileName'),
            self::getNestedValue($source, 'response.filename'),
            self::getNestedValue($source, 'response.data.fileName'),
            self::getNestedValue($source, 'response.data.filename')
        ]);

        if ($fileName === null && $filePath !== null) {
            $parsedPath = parse_url($filePath, PHP_URL_PATH);
            $fileName = basename($parsedPath ?: $filePath);
        }

        $fileSize = self::firstNonEmptyNumeric([
            $source['fileSize'] ?? null,
            $source['size'] ?? null,
            self::getNestedValue($source, 'response.fileSize'),
            self::getNestedValue($source, 'response.size'),
            self::getNestedValue($source, 'response.data.fileSize'),
            self::getNestedValue($source, 'response.data.size')
        ]);

        $mimeType = self::firstNonEmptyString([
            $source['mimeType'] ?? null,
            $source['type'] ?? null,
            self::getNestedValue($source, 'response.mimeType'),
            self::getNestedValue($source, 'response.type'),
            self::getNestedValue($source, 'response.data.mimeType'),
            self::getNestedValue($source, 'response.data.type')
        ]);

        $uploadedAt = self::firstNonEmptyString([
            $source['uploadedAt'] ?? null,
            self::getNestedValue($source, 'response.uploadedAt'),
            self::getNestedValue($source, 'response.data.uploadedAt')
        ]);

        $downloadUrl = self::firstNonEmptyString([
            $source['downloadUrl'] ?? null,
            self::getNestedValue($source, 'response.downloadUrl'),
            self::getNestedValue($source, 'response.data.downloadUrl')
        ]);

        $normalized = [];

        if ($fileName !== null) {
            $normalized['fileName'] = $fileName;
        }
        if ($fileSize !== null) {
            $normalized['fileSize'] = (int)$fileSize;
        }
        if ($mimeType !== null) {
            $normalized['mimeType'] = $mimeType;
        }
        if ($filePath !== null) {
            $normalized['filePath'] = $filePath;
        }
        if ($s3Key !== null) {
            $normalized['s3Key'] = $s3Key;
        }
        if ($s3Url !== null) {
            $normalized['s3Url'] = $s3Url;
        }
        if ($uploadedAt !== null) {
            $normalized['uploadedAt'] = $uploadedAt;
        }

        if (empty($normalized)) {
            return null;
        }

        if ($includeDownloadUrl) {
            // Prefer the stored S3 key when available. Re-parsing a full S3 URL
            // can be ambiguous for path-style endpoints because the bucket name
            // appears in the URL path.
            $target = $s3Key ?? $s3Url ?? $filePath;
            if ($target !== null) {
                try {
                    if ($s3Uploader === null) {
                        $s3Uploader = new S3Uploader();
                    }
                    $normalized['downloadUrl'] = $s3Uploader->getFileDownloadUrl($target);
                } catch (Exception $e) {
                    $normalized['downloadUrl'] = $downloadUrl ?? $target;
                }
            } elseif ($downloadUrl !== null) {
                $normalized['downloadUrl'] = $downloadUrl;
            }
        } elseif ($downloadUrl !== null) {
            $normalized['downloadUrl'] = $downloadUrl;
        }

        return $normalized;
    }

    private static function toFileList($files) {
        if ($files === null || $files === '') {
            return [];
        }

        if (is_string($files)) {
            $decoded = json_decode($files, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $files = $decoded;
            } else {
                return [$files];
            }
        }

        if (!is_array($files)) {
            return [];
        }

        if (self::isAssociativeArray($files)) {
            return [$files];
        }

        return array_values($files);
    }

    private static function getNestedValue($source, $path) {
        if (!is_array($source) || $path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $value = $source;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function firstNonEmptyString($values) {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private static function firstNonEmptyNumeric($values) {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return $value;
            }
        }

        return null;
    }

    private static function isAssociativeArray($array) {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
