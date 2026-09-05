<?php

require_once __DIR__ . '/inc/config.php';
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
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') {
    flash('error', 'Only the main administrator can run a bulk service charge.');
    header('Location: users.php');
    exit;
}

$cronDir = dirname(__DIR__) . '/cronbot';
$giftFile = $cronDir . '/gift';
$serviceQueueFile = $cronDir . '/username.json';
$messageQueueFile = $cronDir . '/users.json';
$messageInfoFile = $cronDir . '/info';
$action = $_POST['action'] ?? 'start';

if ($action === 'cancel') {
    $job = is_file($giftFile)
        ? json_decode((string) file_get_contents($giftFile), true)
        : null;
    if (!is_array($job) || empty($job['bulk_service_charge'])) {
        flash('warning', 'There is no active bulk charge to cancel.');
    } else {
        $remaining = [];
        if (is_file($serviceQueueFile)) {
            $queued = json_decode((string) file_get_contents($serviceQueueFile), true);
            $remaining = is_array($queued) ? $queued : [];
        }
        require_once dirname(__DIR__) . '/botapi.php';
        require_once $cronDir . '/gift_report.php';
        gift_send_unfinished_report($job, $remaining, true);
        if (is_file($serviceQueueFile)) {
            unlink($serviceQueueFile);
        }
        if (is_file($giftFile)) {
            unlink($giftFile);
        }
        flash('success', 'Bulk service charge was cancelled and the unfinished list was sent.');
    }
    header('Location: users.php');
    exit;
}

$agent = $_POST['agent'] ?? 'all';
$panelName = trim((string) ($_POST['panel'] ?? ''));
$serviceTypes = $_POST['service_types'] ?? [];
if (!is_array($serviceTypes)) {
    $serviceTypes = [$serviceTypes];
}
$serviceTypes = array_values(array_unique(array_map('strval', $serviceTypes)));
$addVolume = in_array('volume', $serviceTypes, true);
$addTime = in_array('day', $serviceTypes, true) || in_array('time', $serviceTypes, true);
$volumeRaw = trim((string) ($_POST['volume_value'] ?? ''));
$timeRaw = trim((string) ($_POST['time_value'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if (!in_array($agent, ['all', 'f', 'n', 'n2'], true)) {
    flash('error', 'Invalid user group.');
    header('Location: users.php');
    exit;
}
if ($panelName === '') {
    flash('error', 'Select a panel.');
    header('Location: users.php');
    exit;
}
$panelRow = db_fetch($pdo, 'SELECT name_panel FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$panelName]);
if (!$panelRow) {
    flash('error', 'The selected panel is invalid.');
    header('Location: users.php');
    exit;
}
$panelName = (string) $panelRow['name_panel'];
if (!$addVolume && !$addTime) {
    flash('error', 'Select at least one of volume or time.');
    header('Location: users.php');
    exit;
}
$volumeValue = 0;
$timeValue = 0;
if ($addVolume) {
    if ($volumeRaw === '' || !ctype_digit($volumeRaw) || intval($volumeRaw) <= 0) {
        flash('error', 'Added volume must be a whole number greater than zero (GB).');
        header('Location: users.php');
        exit;
    }
    $volumeValue = intval($volumeRaw);
}
if ($addTime) {
    if ($timeRaw === '' || !ctype_digit($timeRaw) || intval($timeRaw) <= 0) {
        flash('error', 'Added time must be a whole number greater than zero (days).');
        header('Location: users.php');
        exit;
    }
    $timeValue = intval($timeRaw);
}
if ($message === '' || mb_strlen($message, 'UTF-8') > 4000) {
    flash('error', 'The message must be between 1 and 4000 characters.');
    header('Location: users.php');
    exit;
}

$queueBusy = is_file($giftFile) || is_file($messageInfoFile);
foreach ([$serviceQueueFile, $messageQueueFile] as $queueFile) {
    if ($queueBusy || !is_file($queueFile)) {
        continue;
    }
    $queuedItems = json_decode((string) file_get_contents($queueFile), true);
    $queueBusy = is_array($queuedItems) && count($queuedItems) > 0;
}
if ($queueBusy) {
    flash('error', 'Another bulk operation is already running. Try again after it finishes.');
    header('Location: users.php');
    exit;
}

$sql = "SELECT i.id_invoice, i.id_user, i.username, i.Service_location
        FROM invoice i
        INNER JOIN user u ON u.id = i.id_user
        WHERE i.Status = 'active'
          AND i.name_product != 'سرویس تست'
          AND u.User_Status = 'Active'";
$params = [];
if ($agent !== 'all') {
    $sql .= ' AND u.agent = :agent';
    $params[':agent'] = $agent;
}
$sql .= ' AND i.Service_location = :panel';
$params[':panel'] = $panelName;
$serviceFilters = [];
if ($addVolume) {
    $serviceFilters[] = "CAST(COALESCE(NULLIF(i.Volume, ''), '0') AS UNSIGNED) > 0";
}
if ($addTime) {
    $serviceFilters[] = "CAST(COALESCE(NULLIF(i.Service_time, ''), '0') AS UNSIGNED) > 0";
}
if ($serviceFilters) {
    $sql .= ' AND (' . implode(' OR ', $serviceFilters) . ')';
}
$sql .= ' ORDER BY i.id_invoice';
$services = db_fetchAll($pdo, $sql, $params);

if (!$services) {
    flash('warning', 'No active services matched the selected filter.');
    header('Location: users.php');
    exit;
}

$job = [
    'bulkcharge_mode' => 'service',
    'bulk_service_charge' => true,
    'agent' => $agent,
    'name_panel' => $panelName,
    'typecustomer' => 'customer',
    'typegift' => ($addVolume && $addTime) ? 'both' : ($addVolume ? 'volume' : 'day'),
    'add_volume' => $addVolume,
    'add_time' => $addTime,
    'volume_value' => $volumeValue,
    'time_value' => $timeValue,
    'value' => $addVolume && !$addTime ? $volumeValue : $timeValue,
    'text' => $message,
    'id_admin' => $admin['id_admin'],
    'total' => count($services),
    'success_count' => 0,
    'failed_count' => 0,
    'skipped_count' => 0,
    'unfinished' => [],
];

try {
    $suffix = bin2hex(random_bytes(4));
    $queueTemp = $serviceQueueFile . '.' . $suffix . '.tmp';
    $giftTemp = $giftFile . '.' . $suffix . '.tmp';
    $queueActivated = false;
    if (file_put_contents($queueTemp, json_encode($services, JSON_UNESCAPED_UNICODE), LOCK_EX) === false
        || file_put_contents($giftTemp, json_encode($job, JSON_UNESCAPED_UNICODE), LOCK_EX) === false
    ) {
        throw new RuntimeException('Unable to write bulk service charge queue.');
    }
    if (!rename($queueTemp, $serviceQueueFile)) {
        throw new RuntimeException('Unable to activate bulk service charge queue.');
    }
    $queueActivated = true;
    if (!rename($giftTemp, $giftFile)) {
        throw new RuntimeException('Unable to activate bulk service charge queue.');
    }
    flash('success', 'Bulk charge for ' . number_format(count($services)) . ' services was queued.');
} catch (Throwable $e) {
    if (isset($queueTemp) && is_file($queueTemp)) {
        unlink($queueTemp);
    }
    if (isset($giftTemp) && is_file($giftTemp)) {
        unlink($giftTemp);
    }
    if (!empty($queueActivated) && !is_file($giftFile) && is_file($serviceQueueFile)) {
        unlink($serviceQueueFile);
    }
    error_log('bulk_service_charge_action.php: ' . $e->getMessage());
    flash('error', 'Could not queue the bulk charge.');
}

header('Location: users.php');
exit;
