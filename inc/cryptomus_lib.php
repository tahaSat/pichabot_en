<?php

/**
 * Cryptomus core API and fulfillment helpers.
 *
 * This file intentionally has no bootstrap requirements. It is safe to load from
 * bot, webhook, cron, panel, and admin entry points after function.php/config.php.
 */

function cryptomus_json_encode_exact(array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return ['ok' => false, 'json' => null, 'error' => 'json_encode_failed'];
    }
    return ['ok' => true, 'json' => $json, 'error' => null];
}

function cryptomus_sign_json($json, $apiKey)
{
    return md5(base64_encode((string) $json) . (string) $apiKey);
}

function cryptomus_api_post($endpoint, $payload = null, array $credentials = [])
{
    $merchant = (string) ($credentials['merchant'] ?? getPaySettingValue('merchant_cryptomus', '0'));
    $apiKey = (string) ($credentials['api_key'] ?? getPaySettingValue('apicryptomus', '0'));
    if ($merchant === '' || $merchant === '0' || $apiKey === '' || $apiKey === '0') {
        return ['ok' => false, 'data' => null, 'error' => 'cryptomus_not_configured', 'http_status' => 0];
    }

    $endpoint = ltrim((string) $endpoint, '/');
    if (!preg_match('#^v[12]/[a-z0-9_./-]+$#i', $endpoint)) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_endpoint', 'http_status' => 0];
    }

    $json = '';
    if ($payload !== null) {
        if (!is_array($payload)) {
            return ['ok' => false, 'data' => null, 'error' => 'invalid_payload', 'http_status' => 0];
        }
        $encoded = cryptomus_json_encode_exact($payload);
        if (!$encoded['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $encoded['error'], 'http_status' => 0];
        }
        $json = $encoded['json'];
    }

    $curl = curl_init('https://api.cryptomus.com/' . $endpoint);
    if (function_exists('curl_disable_proxy')) {
        curl_disable_proxy($curl);
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'merchant: ' . $merchant,
            'sign: ' . cryptomus_sign_json($json, $apiKey),
            'Content-Type: application/json',
        ],
    ]);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        return ['ok' => false, 'data' => null, 'error' => 'transport_error: ' . $curlError, 'http_status' => $httpStatus];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_json_response', 'http_status' => $httpStatus];
    }
    $success = $httpStatus >= 200 && $httpStatus < 300 && (!isset($decoded['state']) || (int) $decoded['state'] === 0);
    if (!$success) {
        $message = $decoded['message'] ?? $decoded['error'] ?? 'cryptomus_api_error';
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        return ['ok' => false, 'data' => $decoded, 'error' => (string) $message, 'http_status' => $httpStatus];
    }
    return [
        'ok' => true,
        'data' => isset($decoded['result']) && is_array($decoded['result']) ? $decoded['result'] : $decoded,
        'error' => null,
        'http_status' => $httpStatus,
    ];
}

function cryptomus_create_invoice($orderId, $amount, $userId = null, array $credentials = [])
{
    global $domainhosts;
    $payload = [
        'amount' => (string) $amount,
        'currency' => 'USD',
        'order_id' => (string) $orderId,
        'url_callback' => 'https://' . $domainhosts . '/payment/cryptomus.php',
        'lifetime' => 3600,
        'is_payment_multiple' => true,
        'accuracy_payment_percent' => 2,
        'subtract' => 100,
    ];
    if ($userId !== null && $userId !== '') {
        $payload['additional_data'] = (string) $userId;
    }
    return cryptomus_api_post('v1/payment', $payload, $credentials);
}

function cryptomus_payment_info_by_uuid($uuid, array $credentials = [])
{
    return cryptomus_api_post('v1/payment/info', ['uuid' => (string) $uuid], $credentials);
}

function cryptomus_payment_info_by_order($orderId, array $credentials = [])
{
    return cryptomus_api_post('v1/payment/info', ['order_id' => (string) $orderId], $credentials);
}

function cryptomus_services(array $credentials = [])
{
    return cryptomus_api_post('v1/payment/services', null, $credentials);
}

function cryptomus_mark_as_paid($identifier, array $credentials = [])
{
    // This deliberately does not call the fulfillment processor.
    if (is_array($identifier) && !empty($identifier['order_id'])) {
        return cryptomus_api_post('v1/payment/mark-as-paid', ['order_id' => (string) $identifier['order_id']], $credentials);
    }
    $uuid = is_array($identifier) ? ($identifier['uuid'] ?? '') : $identifier;
    if ((string) $uuid === '') {
        return ['ok' => false, 'data' => null, 'error' => 'missing_identifier', 'http_status' => 0];
    }
    return cryptomus_api_post('v1/payment/mark-as-paid', ['uuid' => (string) $uuid], $credentials);
}

function cryptomus_approve_underpayment($orderId, array $credentials = [])
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT dec_not_confirmed FROM Payment_report
         WHERE id_order = ? AND Payment_Method = 'cryptomus'
           AND gateway_status IN ('wrong_amount', 'wrong_amount_waiting')
         LIMIT 1"
    );
    $stmt->execute([(string) $orderId]);
    $uuid = $stmt->fetchColumn();
    if (!$uuid) {
        return ['ok' => false, 'data' => null, 'error' => 'underpayment_not_found', 'http_status' => 0];
    }
    // Acceptance is asynchronous; a later verified status event may fulfill.
    return cryptomus_mark_as_paid(['uuid' => $uuid], $credentials);
}

function cryptomus_refund($identifier, $address, $isSubtract, array $credentials = [])
{
    global $pdo;
    if (!is_array($identifier)) {
        $identifier = ['uuid' => (string) $identifier];
    }
    $field = !empty($identifier['uuid']) ? 'uuid' : 'order_id';
    $value = (string) ($identifier[$field] ?? '');
    $where = $field === 'uuid' ? 'dec_not_confirmed' : 'id_order';
    if ($value === '' || (string) $address === '') {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_refund_request', 'http_status' => 0];
    }
    if (isset($pdo)) {
        $eligible = $pdo->prepare(
            "SELECT refund_status FROM Payment_report
             WHERE $where = ? AND Payment_Method = 'cryptomus'
               AND payment_Status = 'paid' AND fulfillment_state = 'completed'
             LIMIT 1"
        );
        $eligible->execute([$value]);
        $currentRefund = $eligible->fetchColumn();
        if ($currentRefund === false) {
            return ['ok' => false, 'data' => null, 'error' => 'payment_not_refundable', 'http_status' => 0];
        }
        if (in_array((string) $currentRefund, ['refund_process', 'refund_paid'], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'refund_already_requested', 'http_status' => 0];
        }
    }
    $result = cryptomus_api_post('v1/payment/refund', [
        $field => $value,
        'address' => (string) $address,
        'is_subtract' => (bool) $isSubtract,
    ], $credentials);

    $refundStatus = $result['ok'] ? (string) ($result['data']['status'] ?? 'refund_process') : 'refund_fail';
    if (isset($pdo)) {
        $stmt = $pdo->prepare("UPDATE Payment_report SET refund_status = ? WHERE $where = ? AND Payment_Method = 'cryptomus'");
        $stmt->execute([$refundStatus, $value]);
        if (function_exists('clearSelectCache')) {
            clearSelectCache('Payment_report');
        }
    }
    return $result;
}

function cryptomus_get_cached_services($maxAgeSeconds = 3600, $forceRefresh = false, array $credentials = [])
{
    global $pdo;
    $cachedJson = (string) getPaySettingValue('cryptomus_services_cache', '[]');
    $cachedAt = (int) getPaySettingValue('cryptomus_services_cached_at', '0');
    if (!$forceRefresh && $cachedAt > 0 && (time() - $cachedAt) < (int) $maxAgeSeconds) {
        $cached = json_decode($cachedJson, true);
        if (is_array($cached)) {
            return ['ok' => true, 'data' => $cached, 'error' => null, 'cached' => true, 'cached_at' => $cachedAt];
        }
    }

    $result = cryptomus_services($credentials);
    if (!$result['ok'] || !isset($pdo)) {
        $result['cached'] = false;
        return $result;
    }
    $json = json_encode($result['data'], JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        $stmt = $pdo->prepare('INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?) ON DUPLICATE KEY UPDATE ValuePay = VALUES(ValuePay)');
        $stmt->execute(['cryptomus_services_cache', $json]);
        $stmt->execute(['cryptomus_services_cached_at', (string) time()]);
        if (function_exists('clearSelectCache')) {
            clearSelectCache('PaySetting');
        }
    }
    $result['cached'] = false;
    return $result;
}

function cryptomus_decimal_parts($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $value, $matches)) {
        return null;
    }
    $integer = ltrim($matches[2], '0');
    $integer = $integer === '' ? '0' : $integer;
    $fraction = rtrim($matches[3] ?? '', '0');
    $negative = ($matches[1] ?? '') === '-' && ($integer !== '0' || $fraction !== '');
    return ['negative' => $negative, 'integer' => $integer, 'fraction' => $fraction];
}

function cryptomus_decimal_compare($left, $right)
{
    $a = cryptomus_decimal_parts($left);
    $b = cryptomus_decimal_parts($right);
    if ($a === null || $b === null) {
        return null;
    }
    if ($a['negative'] !== $b['negative']) {
        return $a['negative'] ? -1 : 1;
    }
    $direction = $a['negative'] ? -1 : 1;
    if (strlen($a['integer']) !== strlen($b['integer'])) {
        return strlen($a['integer']) < strlen($b['integer']) ? -1 * $direction : 1 * $direction;
    }
    $integerCmp = strcmp($a['integer'], $b['integer']);
    if ($integerCmp !== 0) {
        return ($integerCmp < 0 ? -1 : 1) * $direction;
    }
    $scale = max(strlen($a['fraction']), strlen($b['fraction']));
    $fractionCmp = strcmp(str_pad($a['fraction'], $scale, '0'), str_pad($b['fraction'], $scale, '0'));
    if ($fractionCmp === 0) {
        return 0;
    }
    return ($fractionCmp < 0 ? -1 : 1) * $direction;
}

function cryptomus_decimal_equal($left, $right)
{
    return cryptomus_decimal_compare($left, $right) === 0;
}

function cryptomus_verify_webhook_signature(array $payload, $apiKey = null)
{
    $provided = (string) ($payload['sign'] ?? '');
    if ($provided === '') {
        return false;
    }
    unset($payload['sign']);
    $encoded = cryptomus_json_encode_exact($payload);
    if (!$encoded['ok']) {
        return false;
    }
    $apiKey = $apiKey === null ? getPaySettingValue('apicryptomus', '0') : (string) $apiKey;
    return $apiKey !== '' && $apiKey !== '0' && hash_equals(cryptomus_sign_json($encoded['json'], $apiKey), $provided);
}

function cryptomus_is_final($value)
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true';
}

function cryptomus_safe_gateway_meta(array $remote)
{
    $allowed = [
        'uuid', 'order_id', 'status', 'amount', 'currency', 'payer_amount',
        'payer_currency', 'payment_amount', 'payment_amount_usd', 'merchant_amount',
        'network', 'txid', 'transfer_id', 'is_final', 'created_at', 'updated_at',
        'expired_at', 'additional_data',
    ];
    $safe = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $remote) && (is_scalar($remote[$key]) || $remote[$key] === null)) {
            $safe[$key] = $remote[$key];
        }
    }
    if (isset($remote['convert']) && is_array($remote['convert'])) {
        $safe['convert'] = array_intersect_key($remote['convert'], array_flip(['to_currency', 'commission', 'rate', 'amount']));
    }
    return $safe;
}

function cryptomus_persist_remote_state($orderId, array $remote)
{
    global $pdo;
    $status = (string) ($remote['status'] ?? 'unknown');
    $meta = json_encode(cryptomus_safe_gateway_meta($remote), JSON_UNESCAPED_UNICODE);
    $expiresAt = null;
    if (!empty($remote['expired_at'])) {
        $rawExpiry = (string) $remote['expired_at'];
        $ts = ctype_digit($rawExpiry) ? (int) $rawExpiry : strtotime($rawExpiry);
        $expiresAt = $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }
    $stmt = $pdo->prepare('UPDATE Payment_report SET gateway_status = ?, gateway_meta = ?, gateway_expires_at = COALESCE(?, gateway_expires_at), at_updated = ? WHERE id_order = ? AND Payment_Method = ?');
    $stmt->execute([$status, $meta === false ? '{}' : $meta, $expiresAt, date('Y/m/d H:i:s'), (string) $orderId, 'cryptomus']);
    clearSelectCache('Payment_report');
}

function cryptomus_validate_binding(array $payment, array $remote, $requireType = false)
{
    if (($payment['Payment_Method'] ?? '') !== 'cryptomus') {
        return 'wrong_payment_method';
    }
    if ($requireType && ($remote['type'] ?? '') !== 'payment') {
        return 'wrong_event_type';
    }
    if ((string) ($remote['order_id'] ?? '') !== (string) ($payment['id_order'] ?? '')) {
        return 'order_id_mismatch';
    }
    if ((string) ($remote['uuid'] ?? '') === '' || !hash_equals((string) ($payment['dec_not_confirmed'] ?? ''), (string) $remote['uuid'])) {
        return 'uuid_mismatch';
    }
    if (strtoupper((string) ($remote['currency'] ?? '')) !== 'USD') {
        return 'currency_mismatch';
    }
    if (!cryptomus_decimal_equal($payment['price'] ?? '', $remote['amount'] ?? '')) {
        return 'amount_mismatch';
    }
    return null;
}

function cryptomus_claim_fulfillment($orderId)
{
    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE Payment_report
         SET fulfillment_state = 'processing', fulfillment_started_at = NOW(), payment_Status = 'processing'
         WHERE id_order = ? AND Payment_Method = 'cryptomus'
           AND payment_Status NOT IN ('paid', 'reject')
           AND (fulfillment_state IS NULL OR fulfillment_state = '' OR fulfillment_state = 'pending')"
    );
    $stmt->execute([(string) $orderId]);
    clearSelectCache('Payment_report');
    return $stmt->rowCount() === 1;
}

function cryptomus_cancel_underpayment($orderId, $reason = '')
{
    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE Payment_report
         SET payment_Status = 'reject', fulfillment_state = 'cancelled',
             note = CASE WHEN ? = '' THEN note ELSE ? END, at_updated = ?
         WHERE id_order = ? AND Payment_Method = 'cryptomus'
           AND gateway_status IN ('wrong_amount', 'wrong_amount_waiting')
           AND (fulfillment_state IS NULL OR fulfillment_state = '' OR fulfillment_state = 'pending')"
    );
    $stmt->execute([(string) $reason, (string) $reason, date('Y/m/d H:i:s'), (string) $orderId]);
    clearSelectCache('Payment_report');
    return $stmt->rowCount() === 1;
}

function cryptomus_apply_cashback_once($orderId)
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id_user, price, cashback_applied_at FROM Payment_report WHERE id_order = ? AND Payment_Method = 'cryptomus' FOR UPDATE");
        $stmt->execute([(string) $orderId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment || !empty($payment['cashback_applied_at'])) {
            $pdo->commit();
            return false;
        }
        $percentage = (string) getPaySettingValue('chashbackcryptomus', '0');
        if (cryptomus_decimal_compare($percentage, '0') === 1) {
            $credit = ((float) $payment['price'] * (float) $percentage) / 100;
            $user = $pdo->prepare('UPDATE `user` SET Balance = Balance + ? WHERE id = ?');
            $user->execute([$credit, $payment['id_user']]);
        }
        $mark = $pdo->prepare('UPDATE Payment_report SET cashback_applied_at = NOW() WHERE id_order = ?');
        $mark->execute([(string) $orderId]);
        $pdo->commit();
        clearSelectCache('Payment_report');
        clearSelectCache('user');
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cryptomus_report_channel_once($orderId)
{
    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE Payment_report SET channel_reported_at = NOW()
         WHERE id_order = ? AND Payment_Method = 'cryptomus' AND channel_reported_at IS NULL"
    );
    $stmt->execute([(string) $orderId]);
    if ($stmt->rowCount() !== 1) {
        return false;
    }
    $paymentStmt = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1');
    $paymentStmt->execute([(string) $orderId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    $setting = select('setting', '*');
    $channel = (string) ($setting['Channel_Report'] ?? '');
    if ($channel === '' || $channel === '0') {
        return true;
    }
    $topic = select('topicid', 'idreport', 'report', 'paymentreport', 'select');
    $userStmt = $pdo->prepare('SELECT username FROM `user` WHERE id = ? LIMIT 1');
    $userStmt->execute([$payment['id_user']]);
    $username = (string) ($userStmt->fetchColumn() ?: '');
    telegram('sendmessage', [
        'chat_id' => $channel,
        'message_thread_id' => $topic['idreport'] ?? null,
        'text' => "💵 Cryptomus payment\n- User: @" . $username . "\n- ID: " . $payment['id_user'] . "\n- Amount: " . $payment['price'] . " USD\n- Order: " . $payment['id_order'],
        'parse_mode' => 'HTML',
    ]);
    clearSelectCache('Payment_report');
    return true;
}

function cryptomus_process_payment_status(array $event, $verifyWithApi = true, $image = 'images.jpg', array $credentials = [])
{
    global $pdo, $ManagePanel;
    $orderId = (string) ($event['order_id'] ?? '');
    if ($orderId === '') {
        return ['ok' => false, 'action' => 'rejected', 'error' => 'missing_order_id'];
    }
    $stmt = $pdo->prepare('SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) {
        return ['ok' => false, 'action' => 'rejected', 'error' => 'unknown_order'];
    }
    $bindingError = cryptomus_validate_binding($payment, $event, true);
    if ($bindingError !== null) {
        return ['ok' => false, 'action' => 'rejected', 'error' => $bindingError];
    }
    cryptomus_persist_remote_state($orderId, $event);

    $remote = $event;
    if ($verifyWithApi) {
        $info = cryptomus_payment_info_by_uuid($payment['dec_not_confirmed'], $credentials);
        if (!$info['ok']) {
            return ['ok' => false, 'action' => 'review', 'error' => 'payment_info_failed', 'api' => $info];
        }
        $remote = $info['data'];
        $bindingError = cryptomus_validate_binding($payment, $remote, false);
        if ($bindingError !== null) {
            return ['ok' => false, 'action' => 'review', 'error' => $bindingError];
        }
        cryptomus_persist_remote_state($orderId, $remote);
    }

    $status = (string) ($remote['status'] ?? 'unknown');
    if (in_array($status, ['refund_process', 'refund_paid', 'refund_fail'], true)) {
        $pdo->prepare("UPDATE Payment_report SET refund_status = ? WHERE id_order = ? AND Payment_Method = 'cryptomus'")
            ->execute([$status, $orderId]);
        clearSelectCache('Payment_report');
        return ['ok' => true, 'action' => 'refund', 'status' => $status];
    }
    if (in_array($status, ['check', 'process', 'confirm_check'], true)) {
        return ['ok' => true, 'action' => 'pending', 'status' => $status];
    }
    if ($status === 'wrong_amount_waiting') {
        $pdo->prepare("UPDATE Payment_report SET payment_Status = 'underpaid_waiting' WHERE id_order = ? AND Payment_Method = 'cryptomus'")->execute([$orderId]);
        clearSelectCache('Payment_report');
        return ['ok' => true, 'action' => 'underpaid_waiting', 'status' => $status];
    }
    if ($status === 'wrong_amount') {
        $pdo->prepare("UPDATE Payment_report SET payment_Status = 'underpaid' WHERE id_order = ? AND Payment_Method = 'cryptomus'")->execute([$orderId]);
        clearSelectCache('Payment_report');
        return ['ok' => true, 'action' => 'review', 'status' => $status];
    }
    if ($status === 'cancel') {
        $pdo->prepare("UPDATE Payment_report SET payment_Status = 'expire' WHERE id_order = ? AND Payment_Method = 'cryptomus' AND (fulfillment_state IS NULL OR fulfillment_state = '' OR fulfillment_state = 'pending')")->execute([$orderId]);
        clearSelectCache('Payment_report');
        return ['ok' => true, 'action' => 'expired', 'status' => $status];
    }
    if (!in_array($status, ['paid', 'paid_over'], true) || !cryptomus_is_final($remote['is_final'] ?? false)) {
        $pdo->prepare("UPDATE Payment_report SET payment_Status = 'review' WHERE id_order = ? AND Payment_Method = 'cryptomus' AND fulfillment_state IS NULL")->execute([$orderId]);
        clearSelectCache('Payment_report');
        return ['ok' => true, 'action' => 'review', 'status' => $status];
    }
    if (!cryptomus_claim_fulfillment($orderId)) {
        $stateStmt = $pdo->prepare('SELECT fulfillment_state FROM Payment_report WHERE id_order = ?');
        $stateStmt->execute([$orderId]);
        $fulfillmentState = $stateStmt->fetchColumn();
        if ($fulfillmentState !== 'completed') {
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'review' WHERE id_order = ? AND Payment_Method = 'cryptomus'")->execute([$orderId]);
            clearSelectCache('Payment_report');
        }
        return ['ok' => true, 'action' => 'already_claimed', 'status' => $status, 'fulfillment_state' => $fulfillmentState];
    }

    try {
        if ((!isset($ManagePanel) || !is_object($ManagePanel)) && class_exists('ManagePanel')) {
            $ManagePanel = new ManagePanel();
        }
        $delivered = DirectPayment($orderId, $image);
        if ($delivered !== true) {
            throw new RuntimeException('DirectPayment did not complete');
        }
        cryptomus_apply_cashback_once($orderId);
        cryptomus_report_channel_once($orderId);
        $done = $pdo->prepare("UPDATE Payment_report SET fulfillment_state = 'completed', payment_Status = 'paid' WHERE id_order = ? AND fulfillment_state = 'processing'");
        $done->execute([$orderId]);
        clearSelectCache('Payment_report');
        return ['ok' => true, 'action' => 'fulfilled', 'status' => $status];
    } catch (Throwable $e) {
        $failed = $pdo->prepare("UPDATE Payment_report SET fulfillment_state = 'failed', payment_Status = 'review' WHERE id_order = ? AND fulfillment_state = 'processing'");
        $failed->execute([$orderId]);
        clearSelectCache('Payment_report');
        error_log('Cryptomus fulfillment failed for order ' . $orderId . ': ' . $e->getMessage());
        return ['ok' => false, 'action' => 'review', 'error' => 'fulfillment_failed'];
    }
}

function cryptomus_reconcile_payment($identifier, $image = 'images.jpg', array $credentials = [])
{
    global $pdo;
    $orderLookup = is_array($identifier) && !empty($identifier['order_id']);
    $orderId = $orderLookup ? (string) $identifier['order_id'] : '';
    $info = $orderLookup
        ? cryptomus_payment_info_by_order($orderId, $credentials)
        : cryptomus_payment_info_by_uuid(is_array($identifier) ? ($identifier['uuid'] ?? '') : $identifier, $credentials);
    if (!$info['ok']) {
        return ['ok' => false, 'action' => 'review', 'error' => 'payment_info_failed', 'api' => $info];
    }
    $event = $info['data'];
    if ($orderLookup) {
        $stmt = $pdo->prepare(
            "SELECT * FROM Payment_report
             WHERE id_order = ? AND Payment_Method = 'cryptomus' LIMIT 1"
        );
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        $remoteUuid = (string) ($event['uuid'] ?? '');
        if (!$payment || $remoteUuid === ''
            || (string) ($event['order_id'] ?? '') !== $orderId
            || strtoupper((string) ($event['currency'] ?? '')) !== 'USD'
            || !cryptomus_decimal_equal($payment['price'] ?? '', $event['amount'] ?? '')
        ) {
            return ['ok' => false, 'action' => 'review', 'error' => 'recovery_binding_failed'];
        }
        $storedUuid = (string) ($payment['dec_not_confirmed'] ?? '');
        if ($storedUuid === '') {
            $bind = $pdo->prepare(
                "UPDATE Payment_report SET dec_not_confirmed = ?
                 WHERE id_order = ? AND Payment_Method = 'cryptomus'
                   AND (dec_not_confirmed IS NULL OR dec_not_confirmed = '')
                   AND payment_Status = 'review'
                   AND (fulfillment_state IS NULL OR fulfillment_state = '' OR fulfillment_state = 'pending')"
            );
            $bind->execute([$remoteUuid, $orderId]);
            clearSelectCache('Payment_report');
            if ($bind->rowCount() !== 1) {
                return ['ok' => false, 'action' => 'review', 'error' => 'recovery_claim_failed'];
            }
        } elseif (!hash_equals($storedUuid, $remoteUuid)) {
            return ['ok' => false, 'action' => 'review', 'error' => 'uuid_mismatch'];
        }
    }
    $event['type'] = 'payment';
    return cryptomus_process_payment_status($event, false, $image, $credentials);
}

