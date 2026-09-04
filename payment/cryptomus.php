<?php

ini_set('display_errors', '0');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json; charset=UTF-8');

function cryptomus_webhook_response($status, $message)
{
    http_response_code((int) $status);
    echo json_encode(['ok' => $status >= 200 && $status < 300, 'message' => (string) $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    cryptomus_webhook_response(405, 'method_not_allowed');
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '') {
    cryptomus_webhook_response(400, 'empty_body');
}
if (strlen($rawBody) > 1048576) {
    cryptomus_webhook_response(413, 'payload_too_large');
}

$event = json_decode($rawBody, true);
if (!is_array($event) || json_last_error() !== JSON_ERROR_NONE) {
    cryptomus_webhook_response(400, 'invalid_json');
}

$required = ['sign', 'type', 'uuid', 'order_id', 'status', 'amount', 'currency', 'is_final'];
foreach ($required as $field) {
    if (!array_key_exists($field, $event) || ($field !== 'is_final' && (!is_scalar($event[$field]) || (string) $event[$field] === ''))) {
        cryptomus_webhook_response(422, 'missing_or_invalid_field');
    }
}
if (!is_scalar($event['is_final']) && !is_bool($event['is_final'])) {
    cryptomus_webhook_response(422, 'missing_or_invalid_field');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!cryptomus_verify_webhook_signature($event)) {
    error_log('Cryptomus webhook rejected: invalid signature');
    cryptomus_webhook_response(401, 'invalid_signature');
}

$orderId = (string) $event['order_id'];
try {
    $textbotlang = languagechange(__DIR__ . '/../text.json');
    $keyboard = null;
    $keyboardextendfnished = null;
    $Confirm_pay = null;
    $datatextbot = [];

    $result = cryptomus_process_payment_status($event, true, __DIR__ . '/../images.jpg');
} catch (Throwable $e) {
    error_log('Cryptomus webhook order ' . $orderId . ' action error: ' . $e->getMessage());
    cryptomus_webhook_response(500, 'processing_failed');
}

$action = (string) ($result['action'] ?? 'error');
if (!empty($result['ok'])) {
    $durableActions = [
        'refund', 'pending', 'underpaid_waiting', 'review',
        'expired', 'already_claimed', 'fulfilled',
    ];
    if (in_array($action, $durableActions, true)) {
        error_log('Cryptomus webhook order ' . $orderId . ' action ' . $action);
        cryptomus_webhook_response(200, $action);
    }
}

$error = (string) ($result['error'] ?? 'processing_failed');
error_log('Cryptomus webhook order ' . $orderId . ' action ' . $action . ' error ' . $error);
if ($action === 'rejected') {
    cryptomus_webhook_response($error === 'unknown_order' ? 404 : 422, $error);
}
cryptomus_webhook_response(500, $error);
