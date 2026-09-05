<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
$pdo = panel_ensure_pdo();

$totalUsers = 0;
$newToday = 0;
$totalRevenue = 0;
$revenueToday = 0;
$activeNow = 0;
$pendingPay = 0;
$txToday = 0;

$today = stats_tehran_named_range('today');
$mixedTimeSql = sql_unix_or_datetime_between('time');
$mixedTimeParams = [
    $today['start'],
    $today['end'],
    tehran_datetime_string($today['start'], 'Y-m-d H:i:s'),
    tehran_datetime_string($today['end'], 'Y-m-d H:i:s'),
];
$paidIncomeSql = paid_real_income_sql();

try {
    $totalUsers = db_count($pdo, "SELECT COUNT(*) FROM user");
    $newToday = db_count(
        $pdo,
        "SELECT COUNT(*) FROM user
         WHERE register REGEXP '^[0-9]+$'
           AND CAST(register AS UNSIGNED) BETWEEN ? AND ?",
        [$today['start'], $today['end']]
    );
} catch (Exception $e) {
}

try {
    $totalRevenue = (int) db_query(
        $pdo,
        "SELECT COALESCE(SUM(CAST(price AS DECIMAL(20,0))),0) FROM Payment_report WHERE $paidIncomeSql"
    )->fetchColumn();
    $revenueToday = (int) db_query(
        $pdo,
        "SELECT COALESCE(SUM(CAST(price AS DECIMAL(20,0))),0) FROM Payment_report
         WHERE $paidIncomeSql AND $mixedTimeSql",
        $mixedTimeParams
    )->fetchColumn();
    $activeNow = db_count(
        $pdo,
        "SELECT COUNT(*) FROM invoice
         WHERE Status = 'active' AND name_product != 'سرویس تست'"
    );
} catch (Exception $e) {
}

try {
    $pendingPay = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status='waiting'");
    $txToday = db_count(
        $pdo,
        "SELECT COUNT(*) FROM Payment_report WHERE $paidIncomeSql AND $mixedTimeSql",
        $mixedTimeParams
    );
} catch (Exception $e) {
}

$recentInvoices = [];
$recentUsers = [];
try {
    $recentInvoices = db_fetchAll($pdo, "SELECT * FROM invoice ORDER BY time_sell DESC LIMIT 8");
} catch (Exception $e) {
}
try {
    $recentUsers = db_fetchAll($pdo, "SELECT * FROM user ORDER BY register DESC LIMIT 8");
} catch (Exception $e) {
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div class="notice <?= $devModeOn ? 'notice-warn' : 'notice-ok' ?> fade-up" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
        <strong>development_mode</strong>
        = <code><?= $devModeOn ? 'true' : 'false' ?></code>
        <?php if ($devModeOn): ?>
            — The bot is paused for users; cron and payments will not run. Admins still have access.
        <?php else: ?>
            — The bot is running normally.
        <?php endif; ?>
    </div>
    <span class="tag <?= $devModeOn ? 'tag-warn' : 'tag-ok' ?>"><?= $devModeOn ? 'Active' : 'Inactive' ?></span>
</div>

<div class="stats fade-up">
    <div class="stat">
        <div class="stat-label">Total users</div>
        <div class="stat-num"><?= number_format($totalUsers) ?></div>
        <div class="stat-meta"><?= $newToday > 0 ? '<span class="up">+' . $newToday . ' today</span>' : 'No change' ?>
        </div>
    </div>
    <div class="stat ok">
        <div class="stat-label">Total revenue</div>
        <div class="stat-num">
            <?= $totalRevenue >= 1_000_000
                ? number_format($totalRevenue / 1_000_000, 1) . '<small>M USD</small>'
                : number_format($totalRevenue) . '<small>USD</small>' ?>
        </div>
        <div class="stat-meta">
            <?= $revenueToday > 0
                ? '<span class="up">+' . number_format($revenueToday) . ' today</span>'
                : 'Successful payments' ?>
        </div>
    </div>
    <div class="stat warn">
        <div class="stat-label">Active services</div>
        <div class="stat-num"><?= number_format($activeNow) ?></div>
        <div class="stat-meta">Excluding test services</div>
    </div>
    <div class="stat <?= $pendingPay > 0 ? 'no' : '' ?>">
        <div class="stat-label"><?= $pendingPay > 0 ? 'Pending payments' : 'Transactions today' ?></div>
        <div class="stat-num" style="<?= $pendingPay > 0 ? 'color:var(--no)' : '' ?>">
            <?= number_format($pendingPay > 0 ? $pendingPay : $txToday) ?>
        </div>
        <div class="stat-meta">
            <?= $pendingPay > 0 ? '<a href="payment.php?tab=pending" style="color:var(--no)">Review →</a>' : 'Successful payments' ?>
        </div>
    </div>
</div>

<div class="two-col dash-cols">
    <div class="card fade-up d1">
        <div class="card-head">
            <div>
                <div class="card-title">Latest orders</div>
                <div class="card-subtitle"><?= count($recentInvoices) ?> recent</div>
            </div>
            <a href="invoice.php" class="btn-link" style="font-size:.78rem">All →</a>
        </div>
        <?php
        $statusMap = [
            'active' => ['tag-ok', 'Active'],
            'end_of_time' => ['tag-warn', 'Expired'],
            'end_of_volume' => ['tag-no', 'Data exhausted'],
            'sendedwarn' => ['tag-warn', 'Warning'],
            'send_on_hold' => ['tag-plain', 'Pending'],
        ];
        if (empty($recentInvoices)): ?>
            <div class="empty" style="padding:24px"><p>No orders yet</p></div>
        <?php else: ?>
            <div class="data-list">
                <?php foreach ($recentInvoices as $inv):
                    [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                    ?>
                    <div class="data-row">
                        <div class="data-row-body">
                            <div class="data-row-head">
                                <div class="data-row-title"><?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 36)) ?></div>
                                <span class="tag <?= $tagClass ?>"><?= $label ?></span>
                            </div>
                            <div class="data-row-fields">
                                <div class="data-field">
                                    <span class="data-field-label">User</span>
                                    <span class="data-field-val cm">
                                        <a href="user.php?id=<?= (int) ($inv['id_user'] ?? 0) ?>"><?= htmlspecialchars($inv['id_user'] ?? '—') ?></a>
                                    </span>
                                </div>
                                <div class="data-field">
                                    <span class="data-field-label">Amount</span>
                                    <span class="data-field-val cn"><?= number_format((int) ($inv['price_product'] ?? 0)) ?> USD</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card fade-up d2">
        <div class="card-head">
            <div>
                <div class="card-title">Latest users</div>
                <div class="card-subtitle"><?= count($recentUsers) ?> recent</div>
            </div>
            <a href="users.php" class="btn-link" style="font-size:.78rem">All →</a>
        </div>
        <?php if (empty($recentUsers)): ?>
            <div class="empty" style="padding:24px"><p>No users yet</p></div>
        <?php else: ?>
            <div class="data-list">
                <?php foreach ($recentUsers as $u):
                    $agent = $u['agent'] ?? 'f';
                    $isBlocked = ($u['User_Status'] ?? '') === 'block';
                    $name = $u['namecustom'] ?? '';
                    if ($name === 'none')
                        $name = '';
                    $uname = $u['username'] ?? '';
                    if ($uname === 'none')
                        $uname = '';
                    $displayName = $name ?: ($uname ? '@' . $uname : 'User #' . $u['id']);
                    ?>
                    <div class="data-row">
                        <div class="data-row-body">
                            <div class="data-row-head">
                                <div class="data-row-title">
                                    <a href="user.php?id=<?= (int) $u['id'] ?>"><?= htmlspecialchars($displayName) ?></a>
                                </div>
                                <?php if ($isBlocked): ?>
                                    <span class="tag tag-no">Blocked</span>
                                <?php else: ?>
                                    <span class="tag <?= user_role_tag($agent) ?>"><?= user_role_label($agent) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="data-row-fields">
                                <div class="data-field">
                                    <span class="data-field-label">ID</span>
                                    <span class="data-field-val cm"><?= htmlspecialchars($u['id']) ?></span>
                                </div>
                                <div class="data-field">
                                    <span class="data-field-label">Balance</span>
                                    <span class="data-field-val cn"><?= number_format((int) ($u['Balance'] ?? 0)) ?> USD</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
