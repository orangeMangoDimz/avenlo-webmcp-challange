<?php
/**
 * 后台通用字典项（表 admin_dictionary_items）
 */

require_once __DIR__ . '/BaseModel.php';

class AdminDictionaryItem extends BaseModel {
    protected $table = 'admin_dictionary_items';
    protected $primaryKey = 'id';

    public const GROUP_POINTS_MALL = 'points_mall';
    public const CODE_LEDGER_ACQUISITION = 'points_mall_ledger_acquisition';
    public const CODE_LEDGER_EXTRA = 'points_mall_ledger_extra';

    public const GROUP_OPERATION_LOG = 'operation_log';
    public const CODE_OPERATION_TYPE = 'operation_log_operation_type';
    public const CODE_SUB_MODULE_PREFIX = 'operation_log_sub_module_';

    /**
     * @return array<int,array{value:string,labelKey:string,labelZh:string,labelEn:string}>
     */
    public function findOptionsByGroupAndCode($dictGroup, $dictCode) {
        $dictGroup = trim((string) $dictGroup);
        $dictCode = trim((string) $dictCode);
        if ($dictGroup === '' || $dictCode === '') {
            return [];
        }
        $sql = "SELECT item_key AS k, label_key AS lk,
                       COALESCE(NULLIF(TRIM(label_zh), ''), '') AS lzh,
                       COALESCE(NULLIF(TRIM(label_en), ''), '') AS len
                FROM {$this->table}
                WHERE dict_group = :g AND dict_code = :c AND is_active = 1
                ORDER BY sort_order ASC, id ASC";
        $rows = $this->db->fetchAll($sql, ['g' => $dictGroup, 'c' => $dictCode]);
        $out = [];
        foreach ($rows as $r) {
            $k = trim((string) ($r['k'] ?? ''));
            if ($k === '') {
                continue;
            }
            $out[] = [
                'value' => $k,
                'labelKey' => trim((string) ($r['lk'] ?? '')),
                'labelZh' => (string) ($r['lzh'] ?? ''),
                'labelEn' => (string) ($r['len'] ?? ''),
            ];
        }
        return $out;
    }

    public function findOperationLogTypes() {
        return $this->findOptionsByGroupAndCode(self::GROUP_OPERATION_LOG, self::CODE_OPERATION_TYPE);
    }

    /**
     * @param string[] $modelKeys
     * @return array<string,array<int,array{value:string,labelKey:string,labelZh:string,labelEn:string}>>
     */
    public function findOperationLogSubModulesByModelKeys(array $modelKeys) {
        $out = [];
        foreach ($modelKeys as $modelKey) {
            $modelKey = trim((string) $modelKey);
            if ($modelKey === '') {
                continue;
            }
            $code = self::CODE_SUB_MODULE_PREFIX . $modelKey;
            $out[$modelKey] = $this->findOptionsByGroupAndCode(self::GROUP_OPERATION_LOG, $code);
        }
        return $out;
    }


    /**
     * @return array<int,array{value:string,labelKey:string,labelZh:string,labelEn:string}>
     */
    public function findTriggerOptionsByDictCode($dictCode) {
        $dictCode = trim((string) $dictCode);
        if ($dictCode === '') {
            return [];
        }
        $sql = "SELECT item_key AS k, label_key AS lk,
                       COALESCE(NULLIF(TRIM(label_zh), ''), '') AS lzh,
                       COALESCE(NULLIF(TRIM(label_en), ''), '') AS len
                FROM {$this->table}
                WHERE dict_group = :g AND dict_code = :c AND is_active = 1
                ORDER BY sort_order ASC, id ASC";
        $rows = $this->db->fetchAll($sql, ['g' => self::GROUP_POINTS_MALL, 'c' => $dictCode]);
        $out = [];
        foreach ($rows as $r) {
            $k = trim((string) ($r['k'] ?? ''));
            $lk = trim((string) ($r['lk'] ?? ''));
            if ($k !== '' && $lk !== '') {
                $out[] = [
                    'value' => $k,
                    'labelKey' => $lk,
                    'labelZh' => (string) ($r['lzh'] ?? ''),
                    'labelEn' => (string) ($r['len'] ?? ''),
                ];
            }
        }
        return $out;
    }

    /**
     * 积分变动记录：先 8 项获取方式，再 5 项扩展（顺序与 dict_code 固定）
     *
     * @return array<int,array{value:string,labelKey:string,labelZh:string,labelEn:string}>
     */
    public function findMergedLedgerTriggerOptions() {
        $acq = $this->findTriggerOptionsByDictCode(self::CODE_LEDGER_ACQUISITION);
        $extra = $this->findTriggerOptionsByDictCode(self::CODE_LEDGER_EXTRA);
        return array_merge($acq, $extra);
    }

    /**
     * @return string[]
     */
    public function findMergedLedgerTriggerKeys() {
        $opts = $this->findMergedLedgerTriggerOptions();
        $keys = [];
        foreach ($opts as $o) {
            if (!empty($o['value'])) {
                $keys[] = (string) $o['value'];
            }
        }
        return $keys;
    }
}
