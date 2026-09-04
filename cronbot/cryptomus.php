<?php

require_once __DIR__ . '/cli_only.php';
ini_set('display_errors', '0');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../vendor/autoload.php';

$textbotlang = languagechange(__DIR__ . '/../text.json');
$keyboard = null;
$keyboardextendfnished = null;
$Confirm_pay = null;
$datatextbot = [];
$image = __DIR__ . '/../images.jpg';

try {
    $payments = $pdo->query(
        "SELECT id_order, dec_not_confirmed
         FROM Payment_report
         WHERE Payment_Method = 'cryptomus'
           AND (
               (dec_not_confirmed IS NOT NULL AND dec_not_confirmed != '')
               OR payment_Status = 'review'
           )
           AND (
               (
                   payment_Status IN ('Unpaid', 'underpaid_waiting', 'underpaid', 'review')
                   AND (fulfillment_state IS NULL OR fulfillment_state = '' OR fulfillment_state = 'pending')
               )
               OR (
                   payment_Status = 'paid'
                   AND fulfillment_state = 'completed'
                   AND refund_status = 'refund_process'
               )
           )
           AND (
               at_updated IS NULL OR at_updated = ''
               OR COALESCE(
                   STR_TO_DATE(at_updated, '%Y/%m/%d %H:%i:%s'),
                   STR_TO_DATE(at_updated, '%Y-%m-%d %H:%i:%s')
               ) <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
           )
         ORDER BY at_updated ASC, id ASC
         LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Cryptomus cron action query error: ' . $e->getMessage());
    $payments = [];
}

foreach ($payments as $payment) {
    $orderId = (string) $payment['id_order'];
    try {
        $uuid = (string) ($payment['dec_not_confirmed'] ?? '');
        $identifier = $uuid !== '' ? $uuid : ['order_id' => $orderId];
        $result = cryptomus_reconcile_payment($identifier, $image);
        $action = (string) ($result['action'] ?? 'error');
        if (!empty($result['ok'])) {
            error_log('Cryptomus cron order ' . $orderId . ' action ' . $action);
        } else {
            error_log(
                'Cryptomus cron order ' . $orderId . ' action ' . $action .
                ' error ' . (string) ($result['error'] ?? 'reconciliation_failed')
            );
        }
    } catch (Throwable $e) {
        error_log('Cryptomus cron order ' . $orderId . ' action error: ' . $e->getMessage());
    }
}

try {
    $attemptStmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = 'cryptomus_services_last_attempt_at' LIMIT 1");
    $attemptStmt->execute();
    $lastAttempt = (int) ($attemptStmt->fetchColumn() ?: 0);
    $cachedAt = (int) getPaySettingValue('cryptomus_services_cached_at', '0');

    if ((time() - $cachedAt) >= 3600 && (time() - $lastAttempt) >= 3600) {
        $recordAttempt = $pdo->prepare(
            'INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE ValuePay = VALUES(ValuePay)'
        );
        $recordAttempt->execute(['cryptomus_services_last_attempt_at', (string) time()]);
        clearSelectCache('PaySetting');

        $services = cryptomus_get_cached_services(3600, true);
        if (empty($services['ok'])) {
            error_log('Cryptomus cron action services_refresh error ' . (string) ($services['error'] ?? 'refresh_failed'));
        } else {
            error_log('Cryptomus cron action services_refresh');
        }
    }
} catch (Throwable $e) {
    error_log('Cryptomus cron action services_refresh error: ' . $e->getMessage());
}
