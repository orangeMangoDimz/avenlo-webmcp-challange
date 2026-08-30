<?php
/**
 * 销售模块（log_sales）操作日志详情文案
 */

require_once __DIR__ . '/OperationLogTextHelpers.php';

class SalesOperationLogTexts {
    /**
     * @return array{0:string,1:string}
     */
    public static function referralSuffixUpdate($salesId, $displayName, $oldSuffix, $newSuffix) {
        $id = (int) $salesId;
        $name = trim((string) $displayName);
        if ($name === '') {
            $name = 'Sales #' . $id;
        }
        $old = trim((string) $oldSuffix);
        $new = trim((string) $newSuffix);
        if ($old === '') {
            $old = '—';
        }
        return [
            "更新销售推荐链接后缀：{$name}；{$old} → {$new}；销售 ID：{$id}",
            "Updated sales referral suffix: {$name}; {$old} → {$new}; Sales ID: {$id}",
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function referralSuffixUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新销售推荐链接后缀失败',
            'Update sales referral suffix failed',
            $apiMessageEn
        );
    }
}
