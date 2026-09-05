<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/users_lib.php';
require_auth();
$pdo = panel_ensure_pdo();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: users.php');
    exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', 'User not found.');
    header('Location: users.php');
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$total = panel_count_user_services($pdo, $id);
$services = panel_fetch_user_services($pdo, $id, $perPage, $offset);
$totalPages = max(1, (int) ceil($total / $perPage));

$panels = [];
$products = [];
$panelsMeta = [];
try {
    $panelRows = db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY name_panel");
    $activePanels = array_values(array_filter($panelRows, static function ($row) {
        return ($row['status'] ?? '') === 'active';
    }));
    $panels = $activePanels ?: $panelRows;
    $products = db_fetchAll(
        $pdo,
        "SELECT name_product, Location, price_product, Volume_constraint, Service_time FROM product ORDER BY name_product"
    );
    $userAgent = (string) ($user['agent'] ?? 'f');
    foreach ($panelRows as $pr) {
        $name = (string) ($pr['name_panel'] ?? '');
        if ($name === '') {
            continue;
        }
        $method = (string) ($pr['MethodUsername'] ?? '');
        $monthOpts = [];
        foreach (panel_custom_months($pr) as $opt) {
            $m = (int) $opt['months'];
            $monthOpts[] = ['months' => $m, 'label' => $m . ' months'];
        }
        $panelsMeta[$name] = [
            'method' => $method,
            'asksUsername' => panel_method_asks_custom_username($method),
            'customEnabled' => (($pr['type'] ?? '') !== 'Manualsale'),
            'customLabel' => panel_custom_button_text($pr),
            'months' => $monthOpts,
            'minVolume' => (int) panel_agent_field($pr, 'mainvolume', $userAgent, '1'),
            'maxVolume' => (int) panel_agent_field($pr, 'maxvolume', $userAgent, '1000'),
        ];
    }
} catch (Throwable $e) {
    error_log('user_services.php: ' . $e->getMessage());
}

$displayName = panel_user_display_name($user);
$pageTitle = 'Services · ' . $displayName;
$activeNav = 'users';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px"
    class="fade-up">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="users.php" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> Users</a>
        <a href="user.php?id=<?= $id ?>" class="btn btn-ghost btn-sm"><?= icon('user', 14) ?> Manage user</a>
    </div>
    <span class="tag tag-info"><?= number_format($total) ?> active services</span>
</div>

<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title">Services for <?= htmlspecialchars($displayName) ?></div>
            <div class="card-subtitle">Same list as “Purchased services” in the Telegram bot</div>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addServiceModal')">
            <?= icon('plus', 13) ?> Add service
        </button>
    </div>

    <div class="tbl-wrap">
        <table class="tbl-lg">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Service username</th>
                    <th>Product</th>
                    <th>Panel</th>
                    <th>Data used</th>
                    <th>Time remaining</th>
                    <th>Purchase date</th>
                    <th>Status</th>
                    <th style="width:156px"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty" style="padding:36px">
                                <p>This user has no active services</p>
                                <button type="button" class="btn btn-primary btn-sm" style="margin-top:12px" onclick="openModal('addServiceModal')">
                                    <?= icon('plus', 13) ?> Add first service
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = $offset + 1;
                    foreach ($services as $svc):
                        [$tagClass, $label] = panel_invoice_status_label(panel_invoice_get_status($svc));
                        ?>
                        <tr data-invoice="<?= htmlspecialchars($svc['id_invoice'] ?? '') ?>">
                            <td class="cf"><?= $i++ ?></td>
                            <td>
                                <span class="cm" style="color:var(--ac)"><?= htmlspecialchars($svc['username'] ?? '—') ?></span>
                                <?php if (!empty($svc['note']) && $svc['note'] !== 'none'): ?>
                                    <div class="cf" style="margin-top:2px"><?= htmlspecialchars(trunc($svc['note'], 24)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="cs"><?= htmlspecialchars(trunc($svc['name_product'] ?? '—', 24)) ?></td>
                            <td class="cf"><?= htmlspecialchars($svc['Service_location'] ?? '—') ?></td>
                            <td class="cn cf js-usage-volume"><span class="usage-pending" aria-label="Loading"></span></td>
                            <td class="cn cf js-usage-time"><span class="usage-pending" aria-hidden="true"></span></td>
                            <td class="cf"><?= safe_date($svc['time_sell'] ?? null, 'Y/m/d') ?></td>
                            <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                            <td>
                                <div style="display:flex;gap:4px">
                                    <a href="invoice.php?q=<?= urlencode($svc['username'] ?? '') ?>" class="btn btn-ghost btn-sm btn-icon"
                                        title="Search orders">
                                        <?= icon('search', 13) ?>
                                    </a>
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon btn-extend-service"
                                        title="Renew service"
                                        data-invoice="<?= htmlspecialchars($svc['id_invoice'] ?? '') ?>"
                                        data-username="<?= htmlspecialchars($svc['username'] ?? '') ?>"
                                        data-panel="<?= htmlspecialchars($svc['Service_location'] ?? '') ?>">
                                        <?= icon('refresh', 13) ?>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon btn-refund-service"
                                        title="Refund"
                                        data-invoice="<?= htmlspecialchars($svc['id_invoice'] ?? '') ?>"
                                        data-username="<?= htmlspecialchars($svc['username'] ?? '') ?>"
                                        data-price="<?= (int) ($svc['price_product'] ?? 0) ?>">
                                        <?= icon('block', 13) ?>
                                    </button>
                                    <button type="button" class="btn btn-no btn-sm btn-icon btn-remove-service"
                                        title="Remove service"
                                        data-invoice="<?= htmlspecialchars($svc['id_invoice'] ?? '') ?>"
                                        data-username="<?= htmlspecialchars($svc['username'] ?? '') ?>">
                                        <?= icon('trash', 13) ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="tbl-foot">
            <span><?= number_format($total) ?> services · page <?= $page ?> of <?= $totalPages ?></span>
            <div class="pager">
                <?php $qs = fn($p) => '?id=' . $id . '&page=' . $p; ?>
                <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal-veil" id="addServiceModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Add a service for the user</h3>
            <button type="button" class="modal-x" onclick="closeModal('addServiceModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" action="user_service_action.php" id="addServiceForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_service">
                <input type="hidden" name="user_id" value="<?= $id ?>">
                <div class="field">
                    <label>Panel / location</label>
                    <select name="panel" id="servicePanel" class="select" required>
                        <option value="">Select panel...</option>
                        <?php foreach ($panels as $p): ?>
                            <option value="<?= htmlspecialchars($p['name_panel']) ?>"><?= htmlspecialchars($p['name_panel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Product</label>
                    <select name="product" id="serviceProduct" class="select" required disabled>
                        <option value="">Select a panel first</option>
                    </select>
                </div>
                <div id="customServiceFields" hidden>
                    <div class="field">
                        <label>Volume (GB)</label>
                        <input type="number" name="custom_gb" id="customGb" class="input" min="1" step="1">
                        <span class="field-hint" id="customGbHint"></span>
                    </div>
                    <div class="field">
                        <label>Service duration</label>
                        <select name="custom_months" id="customMonths" class="select">
                            <option value="">Select duration...</option>
                        </select>
                    </div>
                </div>
                <p id="usernameAutoHint" class="field-hint" style="margin:0 0 12px">The username is generated automatically from the panel naming method.</p>
                <div class="field" id="serviceUsernameField" hidden>
                    <label>Service username</label>
                    <input type="text" name="username" id="serviceUsername" class="input cm" pattern="[A-Za-z0-9_]{3,32}" minlength="3" maxlength="32"
                        placeholder="e.g. user_5016" autocomplete="off">
                    <span class="field-hint">Only for the “custom username” method on this panel</span>
                </div>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:.85rem;cursor:pointer;line-height:1.6">
                    <input type="checkbox" name="record_payment" value="1" checked style="width:16px;height:16px;margin-top:3px">
                    <span>Record a new payment? <span class="cf">(order by admin)</span></span>
                </label>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> Create service</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addServiceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="extendServiceModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Renew service</h3>
            <button type="button" class="modal-x" onclick="closeModal('extendServiceModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" action="user_service_action.php" id="extendServiceForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="extend_service">
                <input type="hidden" name="user_id" value="<?= $id ?>">
                <input type="hidden" name="id_invoice" id="extendInvoiceId" value="">
                <p id="extendServiceText" style="font-size:.88rem;color:var(--mute);line-height:1.7;margin-bottom:14px"></p>
                <div class="field">
                    <label>Renewal product</label>
                    <select name="product" id="extendProduct" class="select" required>
                        <option value="">Select product...</option>
                    </select>
                    <span class="field-hint" id="extendProductHint"></span>
                </div>
                <div id="extendCustomFields" hidden>
                    <div class="field">
                        <label>Volume (GB)</label>
                        <input type="number" name="custom_gb" id="extendCustomGb" class="input" min="1" step="1">
                        <span class="field-hint" id="extendCustomGbHint"></span>
                    </div>
                    <div class="field">
                        <label>Service duration</label>
                        <select name="custom_months" id="extendCustomMonths" class="select">
                            <option value="">Select duration...</option>
                        </select>
                    </div>
                </div>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:.85rem;cursor:pointer;line-height:1.6">
                    <input type="checkbox" name="record_payment" value="1" checked style="width:16px;height:16px;margin-top:3px">
                    <span>Record a new payment? <span class="cf">(renewal by admin)</span></span>
                </label>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('refresh', 13) ?> Renew service</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('extendServiceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="removeServiceModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Remove service</h3>
            <button type="button" class="modal-x" onclick="closeModal('removeServiceModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" action="user_service_action.php" id="removeServiceForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="remove_service">
                <input type="hidden" name="user_id" value="<?= $id ?>">
                <input type="hidden" name="id_invoice" id="removeInvoiceId" value="">
                <p id="removeServiceText" style="font-size:.88rem;color:var(--mute);line-height:1.7;margin-bottom:14px"></p>
                <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;cursor:pointer">
                    <input type="checkbox" name="refund" value="1" style="width:16px;height:16px">
                    Refund the service amount to the user wallet
                </label>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-no"><?= icon('trash', 13) ?> Remove service</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('removeServiceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="refundServiceModal">
    <div class="modal">
        <div class="modal-head">
            <h3>Refund service</h3>
            <button type="button" class="modal-x" onclick="closeModal('refundServiceModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" action="user_service_action.php" id="refundServiceForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="refund_service">
                <input type="hidden" name="user_id" value="<?= $id ?>">
                <input type="hidden" name="back" value="user_services.php?id=<?= $id ?>">
                <input type="hidden" name="id_invoice" id="refundInvoiceId" value="">
                <p id="refundServiceText" style="font-size:.88rem;color:var(--mute);line-height:1.7;margin-bottom:14px"></p>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:.85rem;cursor:pointer;line-height:1.6;margin-bottom:10px">
                    <input type="checkbox" name="credit_wallet" id="refundCreditWallet" value="1" style="width:16px;height:16px;margin-top:3px">
                    <span id="refundCreditWalletLabel">Refund the service amount to the user wallet?</span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:.85rem;cursor:pointer;line-height:1.6">
                    <input type="checkbox" name="disable_product" id="refundDisableProduct" value="1" checked style="width:16px;height:16px;margin-top:3px">
                    <span>Disable the service on the sub-link panel and in the bot?</span>
                </label>
                <p style="font-size:.75rem;color:var(--mute);margin-top:8px;line-height:1.6">
                    The order record is kept. If disable is confirmed, the service status becomes “Disabled by admin”.
                </p>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-no"><?= icon('block', 13) ?> Save refund</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('refundServiceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
window.__serviceProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
window.__servicePanels = <?= json_encode($panelsMeta, JSON_UNESCAPED_UNICODE) ?>;
window.__customServiceToken = <?= json_encode(admin_custom_service_product_token(), JSON_UNESCAPED_UNICODE) ?>;
window.__serviceUsage = {
    userId: <?= (int) $id ?>,
    csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<style>
.usage-pending{display:inline-block;width:12px;height:12px;border:2px solid var(--line,#334155);border-top-color:var(--ac,#38bdf8);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle}
</style>
<script src="<?= htmlspecialchars(panel_asset('js/user_services.js')) ?>"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
