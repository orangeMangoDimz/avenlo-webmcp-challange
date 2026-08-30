<?php
/**
 * 恢复被软删除的 payment gateway 及其关联的 withdrawal verification templates
 *
 * 用法：
 *   php script/restore_soft_deleted_gateway.php --gateway-key=alchemy_pay
 *   php script/restore_soft_deleted_gateway.php --gateway-id=1
 */

require_once __DIR__ . '/../utils/Database.php';

function printUsage() {
    echo "Usage:\n";
    echo "  php script/restore_soft_deleted_gateway.php --gateway-key=alchemy_pay\n";
    echo "  php script/restore_soft_deleted_gateway.php --gateway-id=1\n";
    exit(1);
}

$options = getopt('', ['gateway-key::', 'gateway-id::']);
$gatewayKey = isset($options['gateway-key']) ? trim((string)$options['gateway-key']) : '';
$gatewayId = isset($options['gateway-id']) ? (int)$options['gateway-id'] : 0;

if (($gatewayKey === '' && $gatewayId <= 0) || ($gatewayKey !== '' && $gatewayId > 0)) {
    printUsage();
}

try {
    $db = Database::getInstance();

    if ($gatewayId > 0) {
        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gatewayId]
        );
    } else {
        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );
    }

    if (!$gateway) {
        throw new Exception('Gateway not found.');
    }

    if (empty($gateway['deletedAt'])) {
        echo "Gateway '{$gateway['gatewayKey']}' is not soft deleted.\n";
        exit(0);
    }

    $db->beginTransaction();

    $gatewayAffected = $db->update(
        'paymentGatewaySettings',
        ['deletedAt' => null],
        'id = :id',
        ['id' => (int)$gateway['id']]
    );

    $templateSql = "UPDATE withdrawalVerificationTemplates
                    SET deletedAt = NULL
                    WHERE gatewaySettingId = :gatewaySettingId
                      AND deletedAt IS NOT NULL";
    $templateStmt = $db->query($templateSql, [
        'gatewaySettingId' => (int)$gateway['id']
    ]);
    $templateAffected = $templateStmt->rowCount();

    $db->commit();

    echo "Gateway restored successfully.\n";
    echo "gatewayId: " . (int)$gateway['id'] . "\n";
    echo "gatewayKey: " . $gateway['gatewayKey'] . "\n";
    echo "gatewayAffected: " . (int)$gatewayAffected . "\n";
    echo "templateAffected: " . (int)$templateAffected . "\n";
} catch (Exception $e) {
    if (isset($db)) {
        try {
            $db->rollback();
        } catch (Exception $rollbackException) {
            // ignore rollback errors
        }
    }

    fwrite(STDERR, "Restore failed: " . $e->getMessage() . "\n");
    exit(1);
}
