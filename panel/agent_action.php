<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/users_lib.php';
require_auth();
$pdo = panel_ensure_pdo();
agent_ensure_volume_columns();
agent_ensure_n2_tables();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) {
    csrf_check_post();
} else {
    csrf_check_get();
}

$action = $isPost ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');
$id = (int) ($isPost ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));

$allowed_back = ['agents.php', 'agent.php'];
$rawBack = $isPost ? ($_POST['back'] ?? '') : ($_GET['back'] ?? '');
$back = 'agents.php';
foreach ($allowed_back as $allowed) {
    if (strpos($rawBack, $allowed) === 0) {
        $base = explode('?', $rawBack)[0];
        if ($base === 'agents.php') {
            $back = 'agents.php';
        } else {
            $back = 'agent.php' . ($id ? "?id=$id" : '');
        }
        break;
    }
}

$projectRoot = dirname(__DIR__);

function agent_action_redirect(string $back): void
{
    header("Location: $back");
    exit;
}

if ($action === 'promote') {
    $telegramId = trim((string) ($_POST['telegram_id'] ?? ''));
    $newRole = $_POST['new_role'] ?? 'n';
    if (!ctype_digit($telegramId)) {
        flash('error', 'Invalid Telegram ID.');
        agent_action_redirect('agents.php');
    }
    if (!in_array($newRole, ['n', 'n2'], true)) {
        flash('error', 'Invalid role.');
        agent_action_redirect('agents.php');
    }
    $user = db_fetch($pdo, 'SELECT * FROM user WHERE id = ?', [(int) $telegramId]);
    if (!$user) {
        flash('error', 'No user with this ID was found in the bot.');
        agent_action_redirect('agents.php');
    }
    db_query($pdo, 'UPDATE user SET agent = ?, expire = NULL WHERE id = ?', [$newRole, (int) $telegramId]);
    flash('success', 'User was promoted to “' . user_role_label($newRole) . '”.');
    header('Location: agent.php?id=' . (int) $telegramId);
    exit;
}

if (!$id && $action !== 'promote') {
    flash('error', 'Invalid user ID.');
    agent_action_redirect('agents.php');
}

$user = $id ? db_fetch($pdo, 'SELECT * FROM user WHERE id = ?', [$id]) : null;
if ($id && !$user) {
    flash('error', 'User not found.');
    agent_action_redirect('agents.php');
}

switch ($action) {
    case 'set_role':
        $newRole = $_POST['new_role'] ?? 'f';
        if (!in_array($newRole, ['f', 'n', 'n2'], true)) {
            flash('error', 'Invalid role.');
            break;
        }
        if ($newRole === 'f') {
            db_query($pdo, "UPDATE user SET agent = 'f', pricediscount = 0, expire = NULL WHERE id = ?", [$id]);
            try {
                db_query($pdo, 'DELETE FROM Requestagent WHERE id = ?', [$id]);
            } catch (Throwable $e) {
            }
            flash('success', 'Agent role was removed.');
            $back = 'agents.php';
        } else {
            db_query($pdo, 'UPDATE user SET agent = ? WHERE id = ?', [$newRole, $id]);
            flash('success', 'Role changed to “' . user_role_label($newRole) . '”.');
        }
        break;

    case 'set_volume_remaining':
        $volume = (int) ($_POST['volume'] ?? -1);
        if ($volume < 0) {
            flash('error', 'Invalid volume.');
            break;
        }
        db_query($pdo, 'UPDATE user SET agent_volume_remaining = ? WHERE id = ?', [(string) $volume, $id]);
        flash('success', 'Remaining volume was set to ' . number_format($volume) . ' GB.');
        break;

    case 'add_volume':
        $volume = (int) ($_POST['volume'] ?? 0);
        if ($volume < 1) {
            flash('error', 'Amount to add must be at least 1 GB.');
            break;
        }
        $current = (int) ($user['agent_volume_remaining'] ?? 0);
        db_query($pdo, 'UPDATE user SET agent_volume_remaining = ? WHERE id = ?', [(string) ($current + $volume), $id]);
        flash('success', number_format($volume) . ' GB was added to the quota.');
        break;

    case 'set_price_per_gb':
        $price = (int) ($_POST['price'] ?? -1);
        if ($price < 0) {
            flash('error', 'Invalid price.');
            break;
        }
        db_query($pdo, 'UPDATE user SET agent_price_per_gb = ? WHERE id = ?', [(string) $price, $id]);
        flash('success', 'Price per GB was set to ' . number_format($price) . ' USD.');
        break;

    case 'set_price_tiers':
        agent_ensure_volume_columns();
        $uptoList = $_POST['upto_tb'] ?? [];
        $priceList = $_POST['price_per_gb'] ?? [];
        if (!is_array($uptoList)) {
            $uptoList = [];
        }
        if (!is_array($priceList)) {
            $priceList = [];
        }
        $tiers = [];
        $count = max(count($uptoList), count($priceList));
        for ($i = 0; $i < $count; $i++) {
            $price = (int) ($priceList[$i] ?? -1);
            if ($price < 0) {
                continue;
            }
            $uptoRaw = trim((string) ($uptoList[$i] ?? ''));
            $tiers[] = [
                'upto_tb' => $uptoRaw === '' ? null : $uptoRaw,
                'price_per_gb' => $price,
            ];
        }
        $tiers = agent_decode_price_tiers($tiers);
        // Validate strictly increasing finite ceilings
        $prev = 0.0;
        $tiersValid = true;
        foreach ($tiers as $t) {
            if ($t['upto_tb'] === null) {
                continue;
            }
            if ((float) $t['upto_tb'] <= $prev) {
                flash('error', 'TB ceilings must be strictly increasing (e.g. 10 then 30).');
                $tiersValid = false;
                break;
            }
            $prev = (float) $t['upto_tb'];
        }
        if (!$tiersValid) {
            break;
        }
        $json = agent_encode_price_tiers($tiers);
        // Keep flat fallback in sync with first tier / current marginal for list views
        $fallback = !empty($tiers) ? (int) $tiers[0]['price_per_gb'] : 0;
        db_query($pdo, 'UPDATE user SET agent_price_tiers = ?, agent_price_per_gb = ? WHERE id = ?', [$json, (string) $fallback, $id]);
        flash('success', 'Price tiers were saved (' . count($tiers) . ' tiers).');
        break;

    case 'set_max_buy':
        $max = (int) ($_POST['max'] ?? -1);
        if ($max < 0) {
            flash('error', 'Invalid cap.');
            break;
        }
        db_query($pdo, 'UPDATE user SET maxbuyagent = ? WHERE id = ?', [(string) $max, $id]);
        flash('success', 'Agent purchase cap was saved.');
        break;

    case 'set_expire':
        $days = (int) ($_POST['days'] ?? -1);
        if ($days < 0) {
            flash('error', 'Invalid number of days.');
            break;
        }
        if ($days === 0) {
            db_query($pdo, 'UPDATE user SET expire = NULL WHERE id = ?', [$id]);
            flash('success', 'Agent expiry was removed.');
        } else {
            $ts = time() + ($days * 86400);
            db_query($pdo, 'UPDATE user SET expire = ? WHERE id = ?', [(string) $ts, $id]);
            flash('success', "Agent expiry was set to $days days from now.");
        }
        break;

    case 'add_balance':
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount < 1 || $amount > 1000000) {
            flash('error', 'Amount must be between 1 and 1,000,000 USD.');
            break;
        }
        db_query($pdo, 'UPDATE user SET Balance = Balance + ? WHERE id = ?', [$amount, $id]);
        panel_record_admin_balance_change($pdo, $id, $amount, 'add balance by admin');
        panel_notify_user($id, '💎 ' . number_format($amount) . ' USD was added to your wallet.');
        flash('success', number_format($amount) . ' USD was added to the balance.');
        break;

    case 'low_balance':
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount < 1 || $amount > 1000000) {
            flash('error', 'Invalid amount.');
            break;
        }
        db_query($pdo, 'UPDATE user SET Balance = GREATEST(0, Balance - ?) WHERE id = ?', [$amount, $id]);
        panel_record_admin_balance_change($pdo, $id, $amount, 'low balance by admin');
        panel_notify_user($id, '❌ ' . number_format($amount) . ' USD was deducted from your wallet.');
        flash('success', number_format($amount) . ' USD was deducted from the balance.');
        break;

    case 'create_bot':
        if (!agent_is_reseller($user['agent'] ?? 'f')) {
            flash('error', 'Set an agent role first.');
            break;
        }
        $token = trim((string) ($_POST['token'] ?? ''));
        $result = agent_create_sell_bot($id, $token, $projectRoot);
        flash($result['ok'] ? 'success' : 'error', $result['msg'] . (!empty($result['username']) ? ' (@' . $result['username'] . ')' : ''));
        break;

    case 'remove_bot':
        $result = agent_remove_sell_bot($id, $projectRoot);
        flash($result['ok'] ? 'success' : 'error', $result['msg']);
        break;

    case 'repair_bot':
        if (!agent_is_reseller($user['agent'] ?? 'f')) {
            flash('error', 'Set an agent role first.');
            break;
        }
        $result = agent_repair_sell_bot($id, $projectRoot);
        flash($result['ok'] ? 'success' : 'error', $result['msg']);
        break;

    case 'set_bot_min_volume':
        $amount = (int) ($_POST['amount'] ?? -1);
        if ($amount < 0) {
            flash('error', 'Invalid amount.');
            break;
        }
        $row = db_fetch($pdo, 'SELECT setting FROM botsaz WHERE id_user = ?', [(string) $id]);
        if (!$row) {
            flash('error', 'Sales bot not found.');
            break;
        }
        $setting = json_decode($row['setting'] ?? '{}', true) ?: [];
        $setting['minpricevolume'] = $amount;
        db_query($pdo, 'UPDATE botsaz SET setting = ? WHERE id_user = ?', [json_encode($setting), (string) $id]);
        flash('success', 'Minimum volume price was saved.');
        break;

    case 'set_bot_min_time':
        $amount = (int) ($_POST['amount'] ?? -1);
        if ($amount < 0) {
            flash('error', 'Invalid amount.');
            break;
        }
        $row = db_fetch($pdo, 'SELECT setting FROM botsaz WHERE id_user = ?', [(string) $id]);
        if (!$row) {
            flash('error', 'Sales bot not found.');
            break;
        }
        $setting = botsaz_normalize_setting(json_decode($row['setting'] ?? '{}', true) ?: []);
        $setting['minpricetime'] = $amount;
        db_query($pdo, 'UPDATE botsaz SET setting = ? WHERE id_user = ?', [json_encode($setting, JSON_UNESCAPED_UNICODE), (string) $id]);
        flash('success', 'Minimum time price was saved.');
        break;

    case 'set_bot_card_payment':
        $row = db_fetch($pdo, 'SELECT setting FROM botsaz WHERE id_user = ?', [(string) $id]);
        if (!$row) {
            flash('error', 'Sales bot not found.');
            break;
        }
        $setting = botsaz_normalize_setting(json_decode($row['setting'] ?? '{}', true) ?: []);
        $cardNumber = preg_replace('/\s+/', '', (string) ($_POST['card_number'] ?? ''));
        $cardHolder = trim((string) ($_POST['card_holder'] ?? ''));
        $cartInfo = trim((string) ($_POST['cart_info'] ?? ''));
        if ($cardNumber !== '' && !preg_match('/^\d{16}$/', $cardNumber)) {
            flash('error', 'Card number must be 16 digits (or leave it empty).');
            break;
        }
        if ($cardHolder !== '' && (mb_strlen($cardHolder) < 2 || mb_strlen($cardHolder) > 80)) {
            flash('error', 'Invalid cardholder name.');
            break;
        }
        $setting['card_number'] = $cardNumber;
        $setting['card_holder'] = $cardHolder;
        $setting['cart_info'] = $cartInfo !== '' ? $cartInfo : 'After paying, send a photo of the receipt in this chat.';
        db_query($pdo, 'UPDATE botsaz SET setting = ? WHERE id_user = ?', [json_encode($setting, JSON_UNESCAPED_UNICODE), (string) $id]);
        flash('success', 'Agent bot card-to-card settings were saved.');
        break;

    case 'set_hide_panels':
        $panels = $_POST['panels'] ?? [];
        if (!is_array($panels)) {
            $panels = [];
        }
        $panels = array_values(array_filter(array_map('strval', $panels)));
        $exists = db_fetch($pdo, 'SELECT id FROM botsaz WHERE id_user = ?', [(string) $id]);
        if (!$exists) {
            flash('error', 'Sales bot not found.');
            break;
        }
        db_query($pdo, 'UPDATE botsaz SET hide_panel = ? WHERE id_user = ?', [json_encode($panels, JSON_UNESCAPED_UNICODE), (string) $id]);
        flash('success', 'Hidden panels were saved.');
        break;

    case 'set_n2_categories':
        if (!agent_uses_category_whitelist($user['agent'] ?? 'f')) {
            flash('error', 'This action is only for agents in groups n and n2.');
            break;
        }
        agent_ensure_n2_tables();
        $agentIdKey = function_exists('agent_n2_agent_id') ? agent_n2_agent_id($id) : (string) $id;
        $selected = $_POST['categories'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_unique(array_filter(array_map('strval', $selected))));
        // remove both normalized and legacy agent_id rows
        db_query($pdo, 'DELETE FROM agent_n2_category WHERE agent_id = ? OR agent_id = ?', [$agentIdKey, (string) $id]);
        if (!empty($selected)) {
            $ins = $pdo->prepare('INSERT INTO agent_n2_category (agent_id, category, enabled) VALUES (?, ?, 1)');
            foreach ($selected as $cat) {
                $ins->execute([$agentIdKey, $cat]);
            }
        }
        flash('success', 'Allowed agent categories were saved (' . count($selected) . ').');
        break;

    case 'set_n2_products':
        // legacy action kept for old forms — convert to categories of selected products
        if (!agent_uses_category_whitelist($user['agent'] ?? 'f')) {
            flash('error', 'This action is only for agents in groups n and n2.');
            break;
        }
        agent_ensure_n2_tables();
        $agentIdKey = function_exists('agent_n2_agent_id') ? agent_n2_agent_id($id) : (string) $id;
        $selected = $_POST['products'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_unique(array_filter(array_map('strval', $selected))));
        $cats = [];
        if (!empty($selected)) {
            $placeholders = implode(',', array_fill(0, count($selected), '?'));
            $stmt = $pdo->prepare("SELECT DISTINCT category FROM product WHERE code_product IN ($placeholders) AND category IS NOT NULL AND category != ''");
            $stmt->execute($selected);
            $cats = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        db_query($pdo, 'DELETE FROM agent_n2_category WHERE agent_id = ? OR agent_id = ?', [$agentIdKey, (string) $id]);
        if (!empty($cats)) {
            $ins = $pdo->prepare('INSERT INTO agent_n2_category (agent_id, category, enabled) VALUES (?, ?, 1)');
            foreach ($cats as $cat) {
                $ins->execute([$agentIdKey, (string) $cat]);
            }
        }
        flash('success', 'Categories derived from products were saved (' . count($cats) . ').');
        break;

    default:
        flash('error', 'Invalid action.');
        break;
}

agent_action_redirect($back);
