<?php
/**
 * Per-sales performance aggregates, shared by the Daily Report page and the
 * Sales Dashboard so the two can never report different numbers for the same range.
 *
 * Clients are attributed through sales_bind, the same mapping the other Sales pages use.
 * Only completed deposits and withdrawals count: pending, processing, rejected, cancelled
 * and failed rows never reach a sales figure. That is deliberately stricter than Funding
 * Report, which sums every status.
 *
 * Report days are cut with the caller's timezone offset (minutes east of UTC), falling
 * back to UTC+10 when none is supplied.
 */

require_once __DIR__ . '/../utils/Database.php';

class SalesPerformanceMetrics {
    /** Fallback offset when the caller sends no timezone (UTC+10, in minutes) */
    const DEFAULT_TZ_OFFSET_MINUTES = 600;

    /** Accepted timezone offset range (UTC-12 .. UTC+14) */
    const MIN_TZ_OFFSET_MINUTES = -720;
    const MAX_TZ_OFFSET_MINUTES = 840;

    /**
     * Deposits / withdrawals per sales user over a date range, grouped by
     * sales user and transaction type.
     */
    public static function transactionTotals(array $salesIds, $startDate, $endDate, $tzOffsetMinutes) {
        if (empty($salesIds)) {
            return [];
        }

        list($sql, $params) = self::buildTransactionTotalsQuery($salesIds, $startDate, $endDate, $tzOffsetMinutes);

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * SQL for the deposit / withdrawal aggregate. Split out so it can be asserted
     * on without a database connection.
     */
    public static function buildTransactionTotalsQuery(array $salesIds, $startDate, $endDate, $tzOffsetMinutes) {
        $shift = self::shiftMinutes($tzOffsetMinutes);
        list($inClause, $params) = self::buildIdInClause($salesIds, 'sid');
        $params['startDate'] = $startDate;
        $params['endDate'] = $endDate;

        // ponytail: DATE(requestedAt + INTERVAL) cannot use the index; a day or a single
        // month stays small. Revisit with UTC-boundary filtering if it ever bites.
        $sql = "SELECT
                    sb.salesId AS salesId,
                    t.transactionType AS transactionType,
                    COALESCE(SUM(t.amount), 0) AS totalAmount,
                    COUNT(*) AS transactionCount
                FROM vAllTransactions t
                INNER JOIN sales_bind sb ON sb.clientId = t.userId
                WHERE sb.salesId IN ({$inClause})
                  AND t.transactionType IN ('deposit', 'withdrawal')
                  AND t.status = 'completed'
                  AND DATE(t.requestedAt + INTERVAL {$shift} MINUTE) >= :startDate
                  AND DATE(t.requestedAt + INTERVAL {$shift} MINUTE) <= :endDate
                GROUP BY sb.salesId, t.transactionType";

        return array($sql, $params);
    }

    /**
     * New leads / new clients per sales user over a date range.
     * Both come from clientUsers.createdAt and are split by kycStatus, the same way
     * the Leads and Client List pages split that table:
     *   kycStatus = 'approved' -> already a client; anything else -> still a lead.
     */
    public static function registrationTotals(array $salesIds, $startDate, $endDate, $tzOffsetMinutes) {
        if (empty($salesIds)) {
            return [];
        }

        $shift = self::shiftMinutes($tzOffsetMinutes);
        list($inClause, $params) = self::buildIdInClause($salesIds, 'sid');
        $params['startDate'] = $startDate;
        $params['endDate'] = $endDate;

        $sql = "SELECT
                    sb.salesId AS salesId,
                    SUM(CASE WHEN cu.kycStatus = 'approved' THEN 1 ELSE 0 END) AS newClients,
                    SUM(CASE WHEN cu.kycStatus = 'approved' THEN 0 ELSE 1 END) AS newLeads
                FROM clientUsers cu
                INNER JOIN sales_bind sb ON sb.clientId = cu.id
                WHERE sb.salesId IN ({$inClause})
                  AND DATE(cu.createdAt + INTERVAL {$shift} MINUTE) >= :startDate
                  AND DATE(cu.createdAt + INTERVAL {$shift} MINUTE) <= :endDate
                GROUP BY sb.salesId";

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Turn the grouped deposit/withdrawal rows into one entry per sales user
     */
    public static function indexTransactionTotals(array $totals) {
        $indexed = array();

        foreach ($totals as $row) {
            $salesId = (int) $row['salesId'];
            if (!isset($indexed[$salesId])) {
                $indexed[$salesId] = array('deposits' => 0.0, 'depositCount' => 0, 'withdrawals' => 0.0, 'withdrawalCount' => 0);
            }

            $amount = (float) (isset($row['totalAmount']) ? $row['totalAmount'] : 0);
            $count = (int) (isset($row['transactionCount']) ? $row['transactionCount'] : 0);

            if ((isset($row['transactionType']) ? $row['transactionType'] : '') === 'deposit') {
                $indexed[$salesId]['deposits'] = $amount;
                $indexed[$salesId]['depositCount'] = $count;
            } else {
                $indexed[$salesId]['withdrawals'] = $amount;
                $indexed[$salesId]['withdrawalCount'] = $count;
            }
        }

        return $indexed;
    }

    /**
     * Every metric for one sales user over a range, zero-filled.
     * Used by the Sales Dashboard, which only ever shows a single sales user.
     */
    public static function metricsForSales($salesId, $startDate, $endDate, $tzOffsetMinutes) {
        $salesIds = array((int) $salesId);

        $transactions = self::indexTransactionTotals(
            self::transactionTotals($salesIds, $startDate, $endDate, $tzOffsetMinutes)
        );
        $registrations = self::registrationTotals($salesIds, $startDate, $endDate, $tzOffsetMinutes);

        $totals = isset($transactions[(int) $salesId])
            ? $transactions[(int) $salesId]
            : array('deposits' => 0.0, 'depositCount' => 0, 'withdrawals' => 0.0, 'withdrawalCount' => 0);

        $newLeads = 0;
        $newClients = 0;
        foreach ($registrations as $row) {
            if ((int) $row['salesId'] === (int) $salesId) {
                $newLeads = (int) (isset($row['newLeads']) ? $row['newLeads'] : 0);
                $newClients = (int) (isset($row['newClients']) ? $row['newClients'] : 0);
            }
        }

        return array(
            'deposits' => $totals['deposits'],
            'depositCount' => $totals['depositCount'],
            'withdrawals' => $totals['withdrawals'],
            'withdrawalCount' => $totals['withdrawalCount'],
            'netDeposit' => $totals['deposits'] - $totals['withdrawals'],
            'newLeads' => $newLeads,
            'newClients' => $newClients,
        );
    }

    /**
     * Bind an id list into an IN clause so ids never reach SQL as raw text
     */
    public static function buildIdInClause(array $ids, $prefix) {
        $placeholders = array();
        $params = array();

        foreach (array_values($ids) as $index => $id) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = (int) $id;
        }

        return array(implode(', ', $placeholders), $params);
    }

    /**
     * Minutes to add to a stored datetime to reach the viewer's timezone.
     * Stored datetimes use the server's local timezone, and PHP shares that
     * timezone with MySQL here (the existing reports rely on the same assumption).
     */
    public static function shiftMinutes($tzOffsetMinutes) {
        $serverOffsetMinutes = (int) (date('Z') / 60);
        return (int) ($tzOffsetMinutes - $serverOffsetMinutes);
    }

    public static function resolveTzOffsetMinutes($raw) {
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return self::DEFAULT_TZ_OFFSET_MINUTES;
        }
        $minutes = (int) $raw;
        if ($minutes < self::MIN_TZ_OFFSET_MINUTES || $minutes > self::MAX_TZ_OFFSET_MINUTES) {
            return self::DEFAULT_TZ_OFFSET_MINUTES;
        }
        return $minutes;
    }

    public static function todayInOffset($tzOffsetMinutes) {
        return date('Y-m-d', time() + self::shiftMinutes($tzOffsetMinutes) * 60);
    }

    public static function offsetLabel($tzOffsetMinutes) {
        $sign = $tzOffsetMinutes < 0 ? '-' : '+';
        $abs = abs((int) $tzOffsetMinutes);
        return sprintf('UTC%s%02d:%02d', $sign, intdiv($abs, 60), $abs % 60);
    }

    /**
     * Accept YYYY-MM-DD only, and only real calendar dates
     */
    public static function parseDate($raw) {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        $parsed = DateTime::createFromFormat('!Y-m-d', $raw);
        if ($parsed === false || $parsed->format('Y-m-d') !== $raw) {
            return null;
        }
        return $raw;
    }

    /**
     * Accept YYYY-MM only
     */
    public static function parseMonth($raw) {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        $parsed = DateTime::createFromFormat('!Y-m', $raw);
        if ($parsed === false || $parsed->format('Y-m') !== $raw) {
            return null;
        }
        return $raw;
    }

    /**
     * First and last day of a YYYY-MM month
     */
    public static function monthBounds($month) {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        return array($start, $end);
    }
}
