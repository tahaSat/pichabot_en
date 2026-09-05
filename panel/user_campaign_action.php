<?php

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/users_lib.php';
require_auth();
$pdo = panel_ensure_pdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

csrf_check_post();

$admin = db_fetch(
    $pdo,
    'SELECT id_admin, username, rule FROM admin WHERE username = ?',
    [$_SESSION['admin_user'] ?? '']
);
if (!$admin) {
    flash('error', 'Admin session is not valid.');
    header('Location: users.php');
    exit;
}

$cronDir = dirname(__DIR__) . '/cronbot';
$infoFile = $cronDir . '/info';
$usersFile = $cronDir . '/users.json';
$giftFile = $cronDir . '/gift';
$action = $_POST['action'] ?? 'start';

$redirectQs = [
    'q' => trim((string) ($_POST['q'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? '')),
    'role' => trim((string) ($_POST['role'] ?? '')),
    'test' => trim((string) ($_POST['test'] ?? '')),
    'min_buys' => trim((string) ($_POST['min_buys'] ?? '')),
    'min_extends' => trim((string) ($_POST['min_extends'] ?? '')),
];
$redirectQs = array_filter($redirectQs, static fn($v) => $v !== '');
$redirect = 'users.php' . ($redirectQs ? ('?' . http_build_query($redirectQs)) : '');

if ($action === 'cancel') {
    $job = is_file($infoFile) ? json_decode((string) file_get_contents($infoFile), true) : null;
    if (!is_array($job) || ($job['type'] ?? '') !== 'sendmessage') {
        flash('warning', 'There is no active message send to cancel.');
    } else {
        if (is_file($usersFile)) {
            unlink($usersFile);
        }
        if (is_file($infoFile)) {
            unlink($infoFile);
        }
        flash('success', 'Broadcast message send was cancelled.');
    }
    header('Location: ' . $redirect);
    exit;
}

$message = trim((string) ($_POST['message'] ?? ''));
$scope = ($_POST['scope'] ?? '') === 'filtered' ? 'filtered' : 'selected';
if ($message === '' || mb_strlen($message, 'UTF-8') > 3500) {
    flash('error', 'Message text must be between 1 and 3500 characters.');
    header('Location: ' . $redirect);
    exit;
}

support_ensure_schema($pdo);

$queueBusy = is_file($infoFile) || is_file($giftFile);
if (!$queueBusy && is_file($usersFile)) {
    $queuedItems = json_decode((string) file_get_contents($usersFile), true);
    $queueBusy = is_array($queuedItems) && count($queuedItems) > 0;
}
if ($queueBusy) {
    flash('error', 'Another bulk operation is already running. Try again after it finishes.');
    header('Location: ' . $redirect);
    exit;
}

$userIds = [];
if ($scope === 'filtered') {
    $userFilters = [
        'test' => in_array($redirectQs['test'] ?? '', panel_user_test_filter_values(), true) ? $redirectQs['test'] : '',
        'min_buys' => isset($redirectQs['min_buys']) && ctype_digit($redirectQs['min_buys']) ? (int) $redirectQs['min_buys'] : null,
        'min_extends' => isset($redirectQs['min_extends']) && ctype_digit($redirectQs['min_extends']) ? (int) $redirectQs['min_extends'] : null,
    ];
    $query = panel_users_filtered_query(
        $redirectQs['q'] ?? '',
        $redirectQs['status'] ?? '',
        $redirectQs['role'] ?? '',
        $userFilters
    );
    try {
        $rows = db_fetchAll($pdo, "SELECT u.id {$query['from']} {$query['where']}", $query['params']);
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $userIds[] = $id;
            }
        }
    } catch (Exception $e) {
        error_log('user_campaign_action filtered: ' . $e->getMessage());
        flash('error', 'Could not read the filtered user list.');
        header('Location: ' . $redirect);
        exit;
    }
} else {
    $rawIds = $_POST['user_ids'] ?? [];
    if (!is_array($rawIds)) {
        $rawIds = [$rawIds];
    }
    $rawIds = array_values(array_unique(array_filter(array_map('strval', $rawIds), static fn($id) => preg_match('/^\d{4,20}$/', $id))));
    if ($rawIds) {
        $placeholders = implode(',', array_fill(0, count($rawIds), '?'));
        $found = db_fetchAll($pdo, "SELECT id FROM user WHERE id IN ($placeholders)", $rawIds);
        $userIds = array_values(array_filter(array_map(static fn($row) => (string) ($row['id'] ?? ''), $found)));
    }
}

$userIds = array_values(array_unique($userIds));
if ($userIds === []) {
    flash('error', 'No users were selected for the message.');
    header('Location: ' . $redirect);
    exit;
}

$userslist = json_encode(array_map(static fn($id) => ['id' => $id], $userIds), JSON_UNESCAPED_UNICODE);
$cancelKeyboard = json_encode([
    'inline_keyboard' => [[
        ['text' => 'Cancel', 'callback_data' => 'cancel_sendmessage'],
    ]],
], JSON_UNESCAPED_UNICODE);

require_once dirname(__DIR__) . '/botapi.php';
$progress = sendmessage(
    $admin['id_admin'],
    'The operation has started. You will be notified when it finishes.',
    $cancelKeyboard,
    'HTML'
);
$messageId = (int) ($progress['result']['message_id'] ?? 0);

$info = [
    'id_admin' => $admin['id_admin'],
    'type' => 'sendmessage',
    'id_message' => $messageId,
    'message' => $message,
    'messagemediatype' => 'text',
    'photoid' => '',
    'pingmessage' => 'no',
    'btnmessage' => 'none',
    'btntextmessage' => '',
    'create_campaign_conversation' => true,
    'campaign_admin_id' => $admin['id_admin'],
    'campaign_admin_username' => $admin['username'],
];

if (file_put_contents($usersFile, $userslist) === false || file_put_contents($infoFile, json_encode($info, JSON_UNESCAPED_UNICODE)) === false) {
    flash('error', 'Could not queue the message send.');
    header('Location: ' . $redirect);
    exit;
}

flash('success', 'Message send to ' . number_format(count($userIds)) . ' users was queued for cron. Each send is logged as a support conversation with status “Campaign”.');
header('Location: ' . $redirect);
exit;
