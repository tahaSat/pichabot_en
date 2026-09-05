<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/users_lib.php';
require_auth();
$pdo = panel_ensure_pdo();
$currentPanelAdmin = db_fetch($pdo, 'SELECT id_admin, username, rule FROM admin WHERE username = ?', [$_SESSION['admin_user'] ?? '']);
$canManageAdmins = ($currentPanelAdmin['rule'] ?? '') === 'administrator';
$bulkChargeJob = null;
$bulkChargeRemaining = 0;
$campaignJob = null;
$campaignRemaining = 0;
$bulkChargeFile = dirname(__DIR__) . '/cronbot/gift';
$bulkChargeQueueFile = dirname(__DIR__) . '/cronbot/username.json';
$campaignInfoFile = dirname(__DIR__) . '/cronbot/info';
$campaignQueueFile = dirname(__DIR__) . '/cronbot/users.json';
if (is_file($bulkChargeFile)) {
    $activeBulkJob = json_decode((string) file_get_contents($bulkChargeFile), true);
    if (is_array($activeBulkJob) && !empty($activeBulkJob['bulk_service_charge'])) {
        $bulkChargeJob = $activeBulkJob;
        if (is_file($bulkChargeQueueFile)) {
            $remainingServices = json_decode((string) file_get_contents($bulkChargeQueueFile), true);
            $bulkChargeRemaining = is_array($remainingServices) ? count($remainingServices) : 0;
        }
    }
}
if (is_file($campaignInfoFile)) {
    $activeCampaign = json_decode((string) file_get_contents($campaignInfoFile), true);
    if (is_array($activeCampaign) && ($activeCampaign['type'] ?? '') === 'sendmessage') {
        $campaignJob = $activeCampaign;
        if (is_file($campaignQueueFile)) {
            $remainingUsers = json_decode((string) file_get_contents($campaignQueueFile), true);
            $campaignRemaining = is_array($remainingUsers) ? count($remainingUsers) : 0;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';
    $redirectView = 'admins';
    if (!$canManageAdmins) {
        flash('error', 'Only the main administrator can manage admins.');
        if ($action === 'reset_all_test_limits') {
            $redirectView = 'users';
        }
    } elseif ($action === 'reset_all_test_limits') {
        $redirectView = 'users';
        $limit = trim($_POST['limit'] ?? '');
        if ($limit === '' || !ctype_digit($limit)) {
            flash('error', 'Test account limit must be a number.');
        } else {
            try {
                ensureColumnExistsForUpdate('user', 'time_usertest', '0');
                db_query($pdo, "UPDATE user SET limit_usertest = ?, time_usertest = '0'", [$limit]);
                db_query($pdo, 'UPDATE setting SET limit_usertest_all = ?', [$limit]);
                $affected = db_count($pdo, 'SELECT COUNT(*) FROM user');
                flash('success', 'Test account limit for all users (' . number_format($affected) . ') was reset to ' . number_format((int) $limit) . '.');
            } catch (Exception $e) {
                error_log('users.php reset_all_test_limits: ' . $e->getMessage());
                flash('error', 'Could not reset the test account limit.');
            }
        }
    } elseif ($action === 'add_admin') {
        $adminId = trim($_POST['admin_id'] ?? '');
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $adminRule = $_POST['admin_rule'] ?? 'support';
        if (!preg_match('/^\d{4,20}$/', $adminId)) {
            flash('error', 'Invalid numeric Telegram ID for the admin.');
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $adminUsername)) {
            flash('error', 'Panel username must be 3 to 100 characters and only letters, numbers, dots, hyphens, or underscores.');
        } elseif (mb_strlen($password, 'UTF-8') < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } elseif (!in_array($adminRule, ['administrator', 'support', 'Seller'], true)) {
            flash('error', 'Invalid admin role.');
        } else {
            try {
                db_query(
                    $pdo,
                    'INSERT INTO admin (id_admin, username, password, rule) VALUES (?, ?, ?, ?)',
                    [$adminId, $adminUsername, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $adminRule]
                );
                flash('success', 'New admin was added.');
            } catch (PDOException $e) {
                flash('error', 'Telegram ID or panel username is already in use.');
            }
        }
    } elseif ($action === 'remove_admin') {
        $adminId = trim($_POST['admin_id'] ?? '');
        $target = db_fetch($pdo, 'SELECT id_admin, username, rule FROM admin WHERE id_admin = ?', [$adminId]);
        if (!$target) {
            flash('error', 'Admin not found.');
        } elseif ($target['id_admin'] === ($currentPanelAdmin['id_admin'] ?? '')) {
            flash('error', 'You cannot delete the current admin account.');
        } elseif ($target['rule'] === 'administrator' && db_count($pdo, "SELECT COUNT(*) FROM admin WHERE rule = 'administrator'") <= 1) {
            flash('error', 'The last main administrator cannot be deleted.');
        } else {
            db_query($pdo, 'DELETE FROM admin WHERE id_admin = ?', [$adminId]);
            flash('success', 'Admin was deleted.');
        }
    }
    header('Location: users.php' . ($redirectView === 'admins' ? '?view=admins' : ''));
    exit;
}

$defaultTestLimit = '1';
try {
    $settingRow = db_fetch($pdo, 'SELECT limit_usertest_all FROM setting LIMIT 1');
    if ($settingRow && isset($settingRow['limit_usertest_all']) && $settingRow['limit_usertest_all'] !== '') {
        $defaultTestLimit = (string) $settingRow['limit_usertest_all'];
    }
} catch (Exception $e) {
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? '';
$userFilters = panel_user_segment_from_request();
$userFiltersActive = panel_user_segment_active($userFilters);
$view = ($_GET['view'] ?? '') === 'admins' ? 'admins' : 'users';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    if ($view === 'admins') {
        $params = $search !== '' ? ["%$search%", "%$search%", "%$search%"] : [];
        $whereSQL = $search !== '' ? 'WHERE (id_admin LIKE ? OR username LIKE ? OR rule LIKE ?)' : '';
        $total = db_count($pdo, "SELECT COUNT(*) FROM admin $whereSQL", $params);
        $users = db_fetchAll($pdo, "SELECT id_admin, username, rule FROM admin $whereSQL ORDER BY username ASC LIMIT $perPage OFFSET $offset", $params);
    } else {
        $query = panel_users_filtered_query($search, $status, $role, $userFilters, $userFiltersActive);
        $selectExtra = $query['select'] ? ', ' . implode(', ', $query['select']) : '';
        $total = db_count($pdo, "SELECT COUNT(*) {$query['from']} {$query['where']}", $query['params']);
        $users = db_fetchAll($pdo, "SELECT u.*$selectExtra {$query['from']} {$query['where']} ORDER BY u.register DESC LIMIT $perPage OFFSET $offset", $query['params']);
    }
} catch (Exception $e) {
    $total = 0;
    $users = [];
    error_log('users.php: ' . $e->getMessage());
}

$totalPages = max(1, (int) ceil($total / $perPage));

$blockedCount = 0;
$agentCount = 0;
$agentAdvCount = 0;

try {
    $blockedCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE LOWER(User_Status)='block'");
    $agentCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent='n'");
    $agentAdvCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent='n2'");
} catch (Exception $e) {
}

$serviceCounts = [];
if ($view === 'users') {
    foreach ($users as $u) {
        $serviceCounts[(int) $u['id']] = panel_count_user_services($pdo, $u['id']);
    }
}

$activeFilterCount = (int) ($status !== '')
    + (int) ($role !== '')
    + (int) ($userFilters['test'] !== '')
    + (int) ($userFilters['min_buys'] !== null)
    + (int) ($userFilters['min_extends'] !== null);
$filtersActive = $activeFilterCount > 0 || $search !== '';
$pageUserIds = $view === 'users' ? array_values(array_map(static fn($u) => (string) $u['id'], $users)) : [];

$pageTitle = 'Users';
$pageLede = 'Bot user list.';
$activeNav = 'users';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title"><?= $view === 'admins' ? 'Admins' : 'Users' ?> <small>(<?= number_format($total) ?>)</small></div>
            <a href="users.php" class="tag <?= $view === 'users' ? 'tag-info' : 'tag-plain' ?>" style="cursor:pointer">Users</a>
            <a href="users.php?view=admins" class="tag <?= $view === 'admins' ? 'tag-info' : 'tag-plain' ?>" style="cursor:pointer">Admins</a>
            <?php if ($view === 'admins' && $canManageAdmins): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addAdminModal')"><?= icon('plus', 14) ?> Add admin</button>
            <?php endif; ?>
            <?php if ($view === 'users' && $canManageAdmins): ?>
                <?php if ($bulkChargeJob): ?>
                    <span class="tag tag-warn">
                        Bulk charge in progress · <?= number_format($bulkChargeRemaining) ?> remaining
                    </span>
                    <form method="POST" action="bulk_service_charge_action.php"
                        onsubmit="return confirm('Cancel the bulk service charge?')">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-no btn-sm"><?= icon('close', 13) ?> Cancel</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('bulkServiceChargeModal')">
                        <?= icon('plus', 14) ?> Bulk service charge
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-ghost btn-sm" onclick="openModal('resetTestLimitModal')">
                    <?= icon('users', 14) ?> Reset test account limit
                </button>
            <?php endif; ?>

            <?php if ($view === 'users' && $campaignJob): ?>
                <span class="tag tag-info">
                    Message send in progress · <?= number_format($campaignRemaining) ?> remaining
                </span>
                <form method="POST" action="user_campaign_action.php"
                    onsubmit="return confirm('Cancel the broadcast?')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-no btn-sm"><?= icon('close', 13) ?> Cancel send</button>
                </form>
            <?php endif; ?>
        </div>

        <form method="GET" id="usersForm" class="toolbar-end">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <?php if ($view === 'users'): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                <input type="hidden" name="test" value="<?= htmlspecialchars($userFilters['test']) ?>">
                <input type="hidden" name="min_buys" value="<?= $userFilters['min_buys'] !== null ? (int) $userFilters['min_buys'] : '' ?>">
                <input type="hidden" name="min_extends" value="<?= $userFilters['min_extends'] !== null ? (int) $userFilters['min_extends'] : '' ?>">
                <button type="button" class="btn btn-ghost btn-sm" onclick="openModal('usersFilterModal')">
                    <?= icon('filter', 14) ?> Filters
                    <?php if ($activeFilterCount > 0): ?>
                        <span class="tag tag-info" style="margin-right:4px"><?= $activeFilterCount ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <div class="search-box users-search">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="<?= $view === 'admins' ? 'ID, username, or role...' : 'ID, username, name, phone...' ?>"
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="search-clear">✕</button>
                <button type="submit" class="search-btn">Search</button>
            </div>

            <?php if ($filtersActive && $view === 'users'): ?>
                <a href="users.php" class="btn-link" style="font-size:.78rem;white-space:nowrap">Clear</a>
            <?php elseif ($search && $view === 'admins'): ?>
                <a href="users.php?view=admins" class="btn-link" style="font-size:.78rem;white-space:nowrap">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($view === 'users'): ?>
    <style>
      .user-data-row{align-items:center}
      .user-select{display:flex;align-items:center;padding:4px 2px 4px 8px;flex-shrink:0}
      .user-select input{width:16px;height:16px;accent-color:var(--ac);cursor:pointer}
      .users-campaign-bar{display:none;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--bd);background:var(--sf2)}
      .users-campaign-bar.open{display:flex}
      .users-campaign-bar-start{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0}
      .users-filter-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
      .users-filter-shortcuts{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
      @media (max-width:560px){
        .users-filter-grid{grid-template-columns:1fr}
        .users-campaign-bar{padding:10px 12px;align-items:stretch}
        .users-campaign-bar-start{flex:1}
        .users-campaign-bar > .btn{width:100%;justify-content:center}
      }
    </style>
    <?php if ($total > 0): ?>
    <div class="users-campaign-bar open" id="usersCampaignBar">
        <div class="users-campaign-bar-start">
            <label class="user-select" style="padding:0">
                <input type="checkbox" id="selectPageUsers">
            </label>
            <strong id="campaignSelectedLabel">0 users selected</strong>
            <button type="button" class="btn btn-ghost btn-sm" id="selectFilteredBtn">Select all results (<?= number_format($total) ?>)</button>
            <button type="button" class="btn btn-link btn-sm" id="clearSelectedBtn">Clear selection</button>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="openCampaignBtn" <?= $campaignJob ? 'disabled data-busy="1"' : '' ?>>
            <?= icon('send', 14) ?> Send message
        </button>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($view === 'admins'): ?>
        <?php if (empty($users)): ?>
            <div class="empty"><p><?= $search ? 'No admin found' : 'No admins yet' ?></p></div>
        <?php else: ?>
            <div class="data-list">
                <?php foreach ($users as $index => $admin): ?>
                    <div class="data-row">
                        <div class="data-row-body">
                            <div class="data-row-head">
                                <div class="data-row-title">
                                    <span class="data-row-index"><?= $offset + $index + 1 ?></span>
                                    <strong><?= htmlspecialchars($admin['username']) ?></strong>
                                </div>
                                <span class="tag tag-info"><?= htmlspecialchars($admin['rule']) ?></span>
                            </div>
                            <div class="data-row-fields">
                                <div class="data-field">
                                    <span class="data-field-label">Telegram ID</span>
                                    <span class="data-field-val cm"><?= htmlspecialchars($admin['id_admin']) ?></span>
                                </div>
                                <div class="data-field">
                                    <span class="data-field-label">Panel username</span>
                                    <span class="data-field-val cm"><?= htmlspecialchars($admin['username']) ?></span>
                                </div>
                                <div class="data-field">
                                    <span class="data-field-label">Role</span>
                                    <span class="data-field-val"><?= htmlspecialchars($admin['rule']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if ($canManageAdmins && $admin['id_admin'] !== ($currentPanelAdmin['id_admin'] ?? '')): ?>
                            <div class="data-row-actions">
                                <form method="POST" onsubmit="return confirm('Delete admin “<?= htmlspecialchars($admin['username']) ?>”?')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="remove_admin">
                                    <input type="hidden" name="admin_id" value="<?= htmlspecialchars($admin['id_admin']) ?>">
                                    <button class="btn btn-no btn-sm btn-icon" title="Delete admin" type="submit"><?= icon('trash', 14) ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif (empty($users)): ?>
        <div class="empty">
            <svg class="ill" viewBox="0 0 200 160" fill="none">
                <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                <circle cx="100" cy="47" r="18" fill="var(--bds)" />
                <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8"
                    stroke-linecap="round" fill="none" />
            </svg>
            <p><?= $search ? 'No results found' : 'No users yet' ?></p>
        </div>
    <?php else: ?>
        <div class="data-list" id="usersList" data-filtered-count="<?= (int) $total ?>">
            <?php
            $i = $offset + 1;
            foreach ($users as $u):
                $agent = $u['agent'] ?? 'f';
                $isBlocked = panel_user_is_blocked($u);
                $name = $u['namecustom'] ?? '';
                if ($name === 'none')
                    $name = '';
                $uname = $u['username'] ?? '';
                if ($uname === 'none')
                    $uname = '';
                $serviceCount = $serviceCounts[(int) $u['id']] ?? 0;
                $displayName = $name ?: ($uname ? '@' . $uname : 'User #' . $u['id']);
                $phone = (!empty($u['number']) && $u['number'] !== 'none') ? $u['number'] : '';
                ?>
                <div class="data-row user-data-row" role="link" tabindex="0"
                    data-user-url="user.php?id=<?= (int) $u['id'] ?>"
                    onclick="if (!event.target.closest('a,button,input,label')) window.location.href = this.dataset.userUrl"
                    onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,label')) { event.preventDefault(); window.location.href = this.dataset.userUrl; }">
                    <label class="user-select" onclick="event.stopPropagation()">
                        <input type="checkbox" class="user-check" value="<?= htmlspecialchars((string) $u['id']) ?>">
                    </label>
                    <div class="data-row-body">
                        <div class="data-row-head">
                            <div class="data-row-title">
                                <span class="data-row-index"><?= $i++ ?></span>
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
                            <?php if ($uname): ?>
                                <div class="data-field">
                                    <span class="data-field-label">Username</span>
                                    <span class="data-field-val cm" style="color:var(--ac)">@<?= htmlspecialchars($uname) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                                <div class="data-field">
                                    <span class="data-field-label">Phone</span>
                                    <span class="data-field-val cm"><?= htmlspecialchars($phone) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="data-field">
                                <span class="data-field-label">Balance</span>
                                <span class="data-field-val cn"><?= number_format((int) ($u['Balance'] ?? 0)) ?> USD</span>
                            </div>
                            <div class="data-field">
                                <span class="data-field-label">Service</span>
                                <span class="data-field-val">
                                    <?php if ($serviceCount > 0): ?>
                                        <a href="user_services.php?id=<?= (int) $u['id'] ?>"><?= number_format($serviceCount) ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($userFiltersActive): ?>
                                <div class="data-field">
                                    <span class="data-field-label">Purchases</span>
                                    <span class="data-field-val cn"><?= number_format((int) ($u['buy_count'] ?? 0)) ?></span>
                                </div>
                                <div class="data-field">
                                    <span class="data-field-label">Renewals</span>
                                    <span class="data-field-val cn"><?= number_format((int) ($u['extend_count'] ?? 0)) ?></span>
                                </div>
                                <div class="data-field">
                                    <span class="data-field-label">Test account</span>
                                    <span class="data-field-val"><?= ((int) ($u['test_count'] ?? 0)) > 0 ? 'Yes' : 'No' ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="data-field">
                                <span class="data-field-label">Joined</span>
                                <span class="data-field-val"><?= safe_date($u['register'] ?? null) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="data-row-actions">
                        <a href="user.php?id=<?= (int) $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon"
                            title="Manage user"><?= icon('eye', 14) ?></a>
                        <a href="user_services.php?id=<?= (int) $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon"
                            title="User services"><?= icon('package', 14) ?></a>
                        <?php if ($isBlocked): ?>
                            <a href="user_action.php?action=unblock&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                class="btn btn-ok btn-sm btn-icon" title="Unblock"
                                data-confirm="Unblock user <?= htmlspecialchars($name ?: $u['id']) ?>?"><?= icon('check', 13) ?></a>
                        <?php else: ?>
                            <a href="user_action.php?action=block&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                class="btn btn-no btn-sm btn-icon" title="Block"
                                data-confirm="Block user <?= htmlspecialchars($name ?: $u['id']) ?>?"><?= icon('block', 13) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="tbl-foot">
        <span><?= number_format($total) ?> <?= $view === 'admins' ? 'admins' : 'users' ?> · page <?= $page ?> of <?= $totalPages ?></span>
        <div class="pager">
            <?php
            $qs = fn($p) => '?view=' . urlencode($view)
                . '&q=' . urlencode($search)
                . '&status=' . urlencode($status)
                . '&role=' . urlencode($role)
                . '&test=' . urlencode($userFilters['test'])
                . '&min_buys=' . urlencode($userFilters['min_buys'] !== null ? (string) $userFilters['min_buys'] : '')
                . '&min_extends=' . urlencode($userFilters['min_extends'] !== null ? (string) $userFilters['min_extends'] : '')
                . '&page=' . $p;
            ?>
            <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
        </div>
    </div>
</div>

<?php if ($view === 'users'): ?>
<div class="modal-veil" id="usersFilterModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Filter users</h3>
            <button class="modal-x" type="button" onclick="closeModal('usersFilterModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="GET">
            <div class="modal-body">
                <input type="hidden" name="view" value="users">
                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php if ($blockedCount > 0 || $agentCount > 0 || $agentAdvCount > 0): ?>
                    <div class="users-filter-shortcuts">
                        <?php if ($blockedCount > 0): ?>
                            <a href="?status=block" class="tag tag-no"><?= $blockedCount ?> blocked</a>
                        <?php endif; ?>
                        <?php if ($agentCount > 0): ?>
                            <a href="?role=n" class="tag tag-info"><?= $agentCount ?> agents</a>
                        <?php endif; ?>
                        <?php if ($agentAdvCount > 0): ?>
                            <a href="?role=n2" class="tag tag-warn"><?= $agentAdvCount ?> advanced agents</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="users-filter-grid">
                    <div class="field">
                        <label>Status</label>
                        <select name="status" class="select">
                            <option value="">All statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="block" <?= $status === 'block' ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>User group</label>
                        <select name="role" class="select">
                            <option value="">All groups</option>
                            <option value="f" <?= $role === 'f' ? 'selected' : '' ?>>Regular user</option>
                            <option value="n" <?= $role === 'n' ? 'selected' : '' ?>>Agent</option>
                            <option value="n2" <?= $role === 'n2' ? 'selected' : '' ?>>Advanced agent</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Test account</label>
                        <select name="test" class="select">
                            <option value="">All</option>
                            <option value="yes" <?= $userFilters['test'] === 'yes' ? 'selected' : '' ?>>Has test account</option>
                            <option value="no" <?= $userFilters['test'] === 'no' ? 'selected' : '' ?>>No test account</option>
                            <option value="only" <?= $userFilters['test'] === 'only' ? 'selected' : '' ?>>Test account only</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Minimum non-test purchases</label>
                        <input class="input" type="number" name="min_buys" min="0" step="1" inputmode="numeric"
                            placeholder="e.g. 2"
                            value="<?= $userFilters['min_buys'] !== null ? (int) $userFilters['min_buys'] : '' ?>">
                    </div>
                    <div class="field full">
                        <label>Minimum renewals</label>
                        <input class="input" type="number" name="min_extends" min="0" step="1" inputmode="numeric"
                            placeholder="e.g. 1"
                            value="<?= $userFilters['min_extends'] !== null ? (int) $userFilters['min_extends'] : '' ?>">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-ghost" href="users.php">Clear</a>
                <button class="btn btn-ghost" type="button" onclick="closeModal('usersFilterModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="usersCampaignModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Send campaign message</h3>
            <button class="modal-x" type="button" onclick="closeModal('usersCampaignModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" action="user_campaign_action.php" id="usersCampaignForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="start">
                <input type="hidden" name="scope" id="campaignScope" value="selected">
                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                <input type="hidden" name="test" value="<?= htmlspecialchars($userFilters['test']) ?>">
                <input type="hidden" name="min_buys" value="<?= $userFilters['min_buys'] !== null ? (int) $userFilters['min_buys'] : '' ?>">
                <input type="hidden" name="min_extends" value="<?= $userFilters['min_extends'] !== null ? (int) $userFilters['min_extends'] : '' ?>">
                <div id="campaignUserIds"></div>
                <p class="field-hint" id="campaignCountHint" style="margin-bottom:12px"></p>
                <div class="field">
                    <label>Message text</label>
                    <textarea class="textarea" name="message" rows="6" maxlength="3500" required
                        placeholder="Message sent via cron to the selected users"></textarea>
                    <span class="field-hint">Each successful send creates a support conversation with status “Campaign”.</span>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-primary" type="submit"><?= icon('send', 14) ?> Send</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal('usersCampaignModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($view === 'users' && $canManageAdmins): ?>
<div class="modal-veil" id="resetTestLimitModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Reset test account limit for all users</h3>
            <button class="modal-x" type="button" onclick="closeModal('resetTestLimitModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" id="resetTestLimitForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reset_all_test_limits">
                <div class="field">
                    <label>Allowed test accounts</label>
                    <input class="input" type="number" name="limit" min="0" step="1" inputmode="numeric"
                        value="<?= htmlspecialchars($defaultTestLimit) ?>" required>
                    <span class="field-hint">This value is applied to all users and their limit period is reset.</span>
                </div>
                <div style="margin:0;padding:10px 12px;border:1px solid var(--warn);border-radius:var(--r);color:var(--warn);font-size:.8rem;line-height:1.8">
                    The system default limit is also updated to this number.
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-primary" type="submit">Reset all users</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal('resetTestLimitModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('resetTestLimitForm');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        if (!window.confirm('Reset the test account limit for all users?')) {
            event.preventDefault();
        }
    });
}());
</script>
<?php endif; ?>

<?php if ($view === 'users' && $canManageAdmins && !$bulkChargeJob): ?>
<div class="modal-veil" id="bulkServiceChargeModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Bulk service charge</h3>
            <button class="modal-x" type="button" onclick="closeModal('bulkServiceChargeModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" action="bulk_service_charge_action.php" id="bulkServiceChargeForm">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="start">

                <div class="field">
                    <label>User group</label>
                    <select class="select" name="agent" required>
                        <option value="all">All users</option>
                        <option value="f">Group f users</option>
                        <option value="n">Group n users</option>
                        <option value="n2">Group n2 users</option>
                    </select>
                    <span class="field-hint">Only users with a purchase and an active service are selected.</span>
                </div>

                <div class="field">
                    <label>Panel</label>
                    <select class="select" name="panel" id="bulkChargePanel" required>
                        <option value="">Select panel...</option>
                        <?php
                        $bulkChargePanels = db_fetchAll($pdo, "SELECT name_panel FROM marzban_panel WHERE status = 'active' ORDER BY name_panel");
                        if (!$bulkChargePanels) {
                            $bulkChargePanels = db_fetchAll($pdo, "SELECT name_panel FROM marzban_panel ORDER BY name_panel");
                        }
                        foreach ($bulkChargePanels as $bulkPanel):
                            $bulkPanelName = (string) ($bulkPanel['name_panel'] ?? '');
                            if ($bulkPanelName === '') {
                                continue;
                            }
                        ?>
                            <option value="<?= htmlspecialchars($bulkPanelName) ?>"><?= htmlspecialchars($bulkPanelName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-hint">The increase is applied only to services on this panel.</span>
                </div>

                <div class="field">
                    <label>Service type</label>
                    <div style="display:flex;gap:16px;flex-wrap:wrap">
                        <label class="check-row" style="margin:0">
                            <input type="checkbox" name="service_types[]" value="volume" id="bulkChargeVolume">
                            <span>Volume</span>
                        </label>
                        <label class="check-row" style="margin:0">
                            <input type="checkbox" name="service_types[]" value="day" id="bulkChargeTime">
                            <span>Time</span>
                        </label>
                    </div>
                    <span class="field-hint">You can select both. The matching field appears after you choose an option.</span>
                </div>

                <div class="field" id="bulkVolumeField" hidden>
                    <label>Added volume (GB)</label>
                    <input class="input" type="number" name="volume_value" id="bulkVolumeValue"
                        min="1" step="1" inputmode="numeric">
                    <span class="field-hint">This amount is added to the volume of every active service.</span>
                </div>

                <div class="field" id="bulkTimeField" hidden>
                    <label>Added time (days)</label>
                    <input class="input" type="number" name="time_value" id="bulkTimeValue"
                        min="1" step="1" inputmode="numeric">
                    <span class="field-hint">This amount is added to the time of every active service.</span>
                </div>

                <div class="field">
                    <label>Message to send</label>
                    <textarea class="input" name="message" rows="5" maxlength="4000" required
                        placeholder="Message sent to the user after each successful charge"></textarea>
                    <span class="field-hint">This message is sent once for each service that is charged successfully.</span>
                </div>

                <div style="margin:0;padding:10px 12px;border:1px solid var(--warn);border-radius:var(--r);color:var(--warn);font-size:.8rem;line-height:1.8">
                    The operation runs only on active services of the selected panel matching the filter and may take a few minutes.
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-primary" type="submit">Confirm and start</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal('bulkServiceChargeModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var volumeCheck = document.getElementById('bulkChargeVolume');
    var timeCheck = document.getElementById('bulkChargeTime');
    var volumeField = document.getElementById('bulkVolumeField');
    var timeField = document.getElementById('bulkTimeField');
    var volumeInput = document.getElementById('bulkVolumeValue');
    var timeInput = document.getElementById('bulkTimeValue');
    var form = document.getElementById('bulkServiceChargeForm');
    if (!volumeCheck || !timeCheck || !volumeField || !timeField || !volumeInput || !timeInput || !form) return;

    function syncChargeFields() {
        var showVolume = volumeCheck.checked;
        var showTime = timeCheck.checked;
        volumeField.hidden = !showVolume;
        timeField.hidden = !showTime;
        volumeInput.required = showVolume;
        timeInput.required = showTime;
        if (!showVolume) volumeInput.value = '';
        if (!showTime) timeInput.value = '';
    }

    volumeCheck.addEventListener('change', syncChargeFields);
    timeCheck.addEventListener('change', syncChargeFields);
    form.addEventListener('submit', function (event) {
        if (!volumeCheck.checked && !timeCheck.checked) {
            event.preventDefault();
            window.alert('Select at least one of volume or time.');
            return;
        }
        var parts = [];
        if (volumeCheck.checked) parts.push('volume');
        if (timeCheck.checked) parts.push('time');
        var panel = document.getElementById('bulkChargePanel');
        var panelName = panel && panel.value ? panel.options[panel.selectedIndex].text : '';
        var msg = 'Add ' + parts.join(' and ') + ' to active services';
        if (panelName) {
            msg += ' on panel “' + panelName + '”';
        }
        msg += ' matching the filter?';
        if (!window.confirm(msg)) {
            event.preventDefault();
        }
    });
    syncChargeFields();
}());
</script>
<?php endif; ?>

<?php if ($canManageAdmins): ?>
<div class="modal-veil" id="addAdminModal">
    <div class="modal">
        <div class="modal-head"><h3>Add admin</h3><button class="modal-x" type="button" onclick="closeModal('addAdminModal')"><?= icon('close', 14) ?></button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_admin">
                <div class="field">
                    <label>Numeric Telegram ID</label>
                    <input class="input" type="text" name="admin_id" inputmode="numeric" required>
                </div>
                <div class="field">
                    <label>Panel username</label>
                    <input class="input" type="text" name="admin_username" autocomplete="username" required>
                </div>
                <div class="field">
                    <label>Password</label>
                    <input class="input" type="password" name="admin_password" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="field">
                    <label>Role</label>
                    <select class="select" name="admin_rule">
                        <option value="support">Support</option>
                        <option value="Seller">Seller</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-primary" type="submit">Add</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal('addAdminModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= htmlspecialchars(panel_asset('js/users.js')) ?>"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>