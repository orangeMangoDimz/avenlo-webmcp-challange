<?php

class ExportProgressGuard
{
    public static function applyCancelIntent(array $existing, array $data, string $csvPath): array
    {
        if (empty($existing['cancelRequested'])) {
            return $data;
        }

        $data['cancelRequested'] = true;
        $status = (string)($data['status'] ?? '');

        if ($status === 'error') {
            return $data;
        }

        if ($status === 'done' || $status === 'cancelled') {
            if (is_file($csvPath)) {
                @unlink($csvPath);
            }
            $data['status'] = 'cancelled';
            $data['message'] = 'Export cancelled';
            $data['downloadReady'] = false;
            $data['file'] = null;
            return $data;
        }

        if (in_array($status, ['', 'queued', 'running', 'cancelling'], true)) {
            $data['status'] = 'cancelling';
            $data['message'] = 'Cancelling export...';
        }

        return $data;
    }
}
