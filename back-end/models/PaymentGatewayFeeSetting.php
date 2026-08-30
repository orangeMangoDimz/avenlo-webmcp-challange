<?php
/**
 * 兼容层：旧名称 PaymentGatewayFeeSetting
 * 请优先使用 PaymentGatewayFundingSetting
 */

require_once __DIR__ . '/PaymentGatewayFundingSetting.php';

class PaymentGatewayFeeSetting extends PaymentGatewayFundingSetting {
}
