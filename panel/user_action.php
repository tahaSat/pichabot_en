<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/users_lib.php';
require_auth();
$pdo = panel_ensure_pdo();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) {
    csrf_check_post();
} else {
    csrf_check_get();
}

$action = $isPost ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');
$id = (int) ($isPost ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));

$allowed_back = ['users.php', 'user.php', 'user_services.php'];
$rawBack = $isPost ? ($_POST['back'] ?? '') : ($_GET['back'] ?? '');
$back = 'users.php';
foreach ($allowed_back as $allowed) {
    if (strpos($rawBack, $allowed) === 0) {
        $base = explode('?', $rawBack)[0];
        if ($base === 'users.php') {
            $back = 'users.php';
        } elseif ($base === 'user_services.php') {
            $back = "user_services.php?id=$id";
        } else {
            $back = $base . ($id ? "?id=$id" : '');
        }
        break;
    }
}

if (!$id) {
    flash('error', 'Invalid user ID.');
    header('Location: users.php');
    exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', 'User not found.');
    header('Location: users.php');
    exit;
}

function panel_user_action_redirect(string $back, int $id): void
{
    header("Location: $back");
    exit;
}

switch ($action) {
    case 'block':
        if (panel_user_is_blocked($user)) {
            flash('warning', 'User was already blocked.');
        } else {
            $reason = trim($isPost ? ($_POST['reason'] ?? '') : ($_GET['reason'] ?? ''));
            db_query($pdo, "UPDATE user SET User_Status = 'block', description_blocking = ? WHERE id = ?", [$reason ?: 'Blocked by admin', $id]);
            flash('success', "User $id was blocked.");
            error_log("Admin {$_SESSION['admin_user']} blocked user $id");
        }
        break;

    case 'unblock':
        if (!panel_user_is_blocked($user)) {
            flash('warning', 'User is already active.');
        } else {
            db_query($pdo, "UPDATE user SET User_Status = 'active', description_blocking = ' ' WHERE id = ?", [$id]);
            panel_notify_user($id, "✳️ Your account has been unblocked ✳️\nYou can use the bot again ✔️");
            flash('success', "User $id was unblocked.");
            error_log("Admin {$_SESSION['admin_user']} unblocked user $id");
        }
        break;

    case 'add_balance':
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount < 1 || $amount > 1000000) {
            flash('error', 'Amount must be between 1 and 1,000,000 USD.');
            break;
        }
        db_query($pdo, "UPDATE user SET Balance = Balance + ? WHERE id = ?", [$amount, $id]);
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
        db_query($pdo, "UPDATE user SET Balance = GREATEST(0, Balance - ?) WHERE id = ?", [$amount, $id]);
        panel_record_admin_balance_change($pdo, $id, $amount, 'low balance by admin');
        panel_notify_user($id, '❌ ' . number_format($amount) . ' USD was deducted from your wallet.');
        flash('success', number_format($amount) . ' USD was deducted from the balance.');
        break;

    case 'zero_balance':
        $prev = (int) ($user['Balance'] ?? 0);
        db_query($pdo, "UPDATE user SET Balance = 0 WHERE id = ?", [$id]);
        if ($prev > 0) {
            panel_record_admin_balance_change($pdo, $id, $prev, 'low balance by admin');
        }
        flash('success', 'User balance (' . number_format($prev) . ' USD) was set to zero.');
        break;

    case 'set_role':
        $newRole = $_POST['new_role'] ?? 'f';
        if (!in_array($newRole, ['f', 'n', 'n2', 'all'], true)) {
            flash('error', 'Invalid group.');
            break;
        }
        db_query($pdo, "UPDATE user SET agent = ? WHERE id = ?", [$newRole, $id]);
        if ($newRole === 'f') {
            db_query($pdo, "UPDATE user SET pricediscount = 0, expire = NULL WHERE id = ?", [$id]);
        }
        flash('success', 'User group changed to “' . user_role_label($newRole) . '”.');
        break;

    case 'remove_agent':
        db_query($pdo, "UPDATE user SET agent = 'f', pricediscount = 0, expire = NULL WHERE id = ?", [$id]);
        try {
            db_query($pdo, "DELETE FROM Requestagent WHERE id = ?", [$id]);
        } catch (Throwable $e) {
        }
        flash('success', 'Agent role was removed.');
        break;

    case 'set_discount':
        $percent = (int) ($_POST['percent'] ?? -1);
        if ($percent < 0 || $percent > 100) {
            flash('error', 'Discount percent must be between 0 and 100.');
            break;
        }
        db_query($pdo, "UPDATE user SET pricediscount = ? WHERE id = ?", [$percent, $id]);
        flash('success', 'User discount was set to ' . $percent . '%.');
        break;

    case 'confirm_number':
        db_query($pdo, "UPDATE user SET number = 'confrim number by admin' WHERE id = ?", [$id]);
        flash('success', 'Phone number was confirmed by admin.');
        break;

    case 'verify':
        db_query($pdo, "UPDATE user SET verify = '1' WHERE id = ?", [$id]);
        panel_notify_user($id, '💎 Your account was verified by an admin.');
        flash('success', 'User was verified.');
        break;

    case 'unverify':
        db_query($pdo, "UPDATE user SET verify = '0' WHERE id = ?", [$id]);
        flash('success', 'User verification was removed.');
        break;

    case 'show_card':
        db_query($pdo, "UPDATE user SET cardpayment = '1' WHERE id = ?", [$id]);
        panel_notify_user($id, '💳 Card-to-card payment is now enabled. You can complete your purchase.');
        flash('success', 'Card number display was enabled for the user.');
        break;

    case 'hide_card':
        db_query($pdo, "UPDATE user SET cardpayment = '0' WHERE id = ?", [$id]);
        flash('success', 'Card number display was disabled for the user.');
        break;

    case 'confirm_channel':
        db_query($pdo, "UPDATE user SET joinchannel = 'bypass' WHERE id = ?", [$id]);
        flash('success', 'User was exempted from required channel join.');
        break;

    case 'toggle_cron':
        $newVal = (int) ($user['status_cron'] ?? 0) === 1 ? '0' : '1';
        db_query($pdo, "UPDATE user SET status_cron = ? WHERE id = ?", [$newVal, $id]);
        flash('success', $newVal === '1' ? 'Cron notifications were enabled.' : 'Cron notifications were disabled.');
        break;

    case 'remove_affiliate':
        if (!empty($user['affiliates']) && $user['affiliates'] !== '0') {
            $parent = db_fetch($pdo, "SELECT id, affiliatescount FROM user WHERE id = ?", [$user['affiliates']]);
            if ($parent) {
                $count = max(0, (int) ($parent['affiliatescount'] ?? 0) - 1);
                db_query($pdo, "UPDATE user SET affiliatescount = ? WHERE id = ?", [$count, $parent['id']]);
            }
        }
        db_query($pdo, "UPDATE user SET affiliates = '0' WHERE id = ?", [$id]);
        flash('success', 'User was removed from the referral tree.');
        break;

    case 'clear_affiliates':
        db_query($pdo, "UPDATE user SET affiliatescount = 0 WHERE id = ?", [$id]);
        db_query($pdo, "UPDATE user SET affiliates = '0' WHERE affiliates = ?", [(string) $id]);
        flash('success', 'User referrals were cleared.');
        break;

    case 'set_test_limit':
        $limit = trim($_POST['limit'] ?? '');
        if ($limit === '' || !ctype_digit($limit)) {
            flash('error', 'Test account limit must be a number.');
            break;
        }
        db_query($pdo, "UPDATE user SET limit_usertest = ? WHERE id = ?", [$limit, $id]);
        flash('success', 'Test account limit was set to ' . number_format((int) $limit) . '.');
        break;

    case 'set_max_buy_agent':
        $max = trim($_POST['max'] ?? '');
        if ($max === '' || !ctype_digit($max)) {
            flash('error', 'Purchase cap must be a number (0 = unlimited).');
            break;
        }
        db_query($pdo, "UPDATE user SET maxbuyagent = ? WHERE id = ?", [$max, $id]);
        flash('success', 'Agent purchase cap was saved.');
        break;

    case 'set_agent_expire':
        $days = (int) ($_POST['days'] ?? 0);
        if ($days < 1) {
            flash('error', 'Days must be at least 1.');
            break;
        }
        $expire = time() + ($days * 86400);
        db_query($pdo, "UPDATE user SET expire = ? WHERE id = ?", [$expire, $id]);
        flash('success', 'Agent expiry was set to ' . $days . ' days from now.');
        break;

    case 'send_message':
        $text = trim($_POST['message'] ?? '');
        if ($text === '') {
            flash('error', 'Message text is empty.');
            break;
        }
        $prefix = "👤 A message from the admin was sent to you\n\nMessage:\n\n";
        panel_notify_user($id, $prefix . htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        flash('success', 'Message was sent to the user.');
        break;

    default:
        flash('error', 'Invalid action.');
}

panel_user_action_redirect($back, $id);
