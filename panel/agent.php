<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/users_lib.php';
require_auth();
$pdo = panel_ensure_pdo();
agent_ensure_volume_columns();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: agents.php');
    exit;
}

$user = db_fetch($pdo, 'SELECT * FROM user WHERE id = ?', [$id]);
if (!$user) {
    flash('error', 'User not found.');
    header('Location: agents.php');
    exit;
}

$agent = $user['agent'] ?? 'f';
if (!agent_is_reseller($agent)) {
    flash('warning', 'This user is not an agent. Assign an agent role first.');
}

$bot = null;
try {
    $bot = db_fetch($pdo, 'SELECT * FROM botsaz WHERE id_user = ?', [(string) $id]);
} catch (Exception $e) {
}

$botSetting = [];
if ($bot && !empty($bot['setting'])) {
    $botSetting = json_decode($bot['setting'], true) ?: [];
}
$hidePanels = [];
if ($bot && !empty($bot['hide_panel'])) {
    $decoded = json_decode($bot['hide_panel'], true);
    $hidePanels = is_array($decoded) ? $decoded : [];
}

$allPanels = [];
try {
    $allPanels = db_fetchAll($pdo, "SELECT name_panel FROM marzban_panel ORDER BY name_panel ASC");
} catch (Exception $e) {
}

$balance = (int) ($user['Balance'] ?? 0);
$volRemaining = (int) ($user['agent_volume_remaining'] ?? 0);
$pricePerGb = (int) ($user['agent_price_per_gb'] ?? 0);
$maxBuy = (int) ($user['maxbuyagent'] ?? 0);
$username = ($user['username'] ?? '') === 'none' ? '' : ($user['username'] ?? '');
$expire = $user['expire'] ?? null;
$expireLabel = $expire ? date('Y-m-d H:i', (int) $expire) : 'No expiry';

$isN2 = ($agent === 'n2');
$usesCategoryWhitelist = function_exists('agent_uses_category_whitelist')
    ? agent_uses_category_whitelist($agent)
    : in_array($agent, ['n', 'n2'], true);
$volumeConsumed = agent_is_reseller($agent) ? agent_sum_volume_consumed($id, $agent) : 0.0;
$priceTiers = (!$isN2 && function_exists('agent_decode_price_tiers'))
    ? agent_decode_price_tiers($user)
    : [];
if (!$isN2 && empty($priceTiers) && $pricePerGb > 0) {
    $priceTiers = [['upto_tb' => null, 'price_per_gb' => $pricePerGb]];
}
$currentPricePerGb = (!$isN2 && function_exists('agent_current_price_per_gb'))
    ? agent_current_price_per_gb($user, $volumeConsumed)
    : $pricePerGb;
$consumedTb = $volumeConsumed / (function_exists('agent_gb_per_tb') ? agent_gb_per_tb() : 1024);
$allCategories = [];
$enabledCategories = [];
$agentPurchases = [];
$agentPurchaseTotal = 0;
if ($usesCategoryWhitelist) {
    agent_ensure_n2_tables();
    try {
        $allCategories = db_fetchAll($pdo, 'SELECT id, remark FROM category ORDER BY remark ASC');
    } catch (Exception $e) {
        $allCategories = [];
    }
    try {
        $agentIdKey = function_exists('agent_n2_agent_id') ? agent_n2_agent_id($id) : (string) $id;
        $rows = db_fetchAll($pdo, 'SELECT category, enabled FROM agent_n2_category WHERE agent_id = ? OR agent_id = ?', [$agentIdKey, (string) $id]);
        foreach ($rows as $r) {
            if ((int) ($r['enabled'] ?? 0) === 1) {
                $enabledCategories[$r['category']] = true;
            }
        }
    } catch (Exception $e) {
    }

    // Purchases: n2 uses dedicated log; n agents use invoices (same fields for the table)
    try {
        $agentIdKey = function_exists('agent_n2_agent_id') ? agent_n2_agent_id($id) : (string) $id;
        if ($isN2) {
            $agentPurchases = db_fetchAll(
                $pdo,
                'SELECT * FROM agent_n2_purchase WHERE agent_id = ? OR agent_id = ? ORDER BY created_at DESC LIMIT 100',
                [$agentIdKey, (string) $id]
            );
            $agentPurchaseTotal = (int) db_count(
                $pdo,
                'SELECT COUNT(*) FROM agent_n2_purchase WHERE agent_id = ? OR agent_id = ?',
                [$agentIdKey, (string) $id]
            );
        } else {
            $invRows = db_fetchAll(
                $pdo,
                "SELECT id_invoice, name_product, Volume, Service_time, Service_location, username, price_product, time_sell, Status
                 FROM invoice
                 WHERE id_user = ?
                   AND name_product != 'سرویس تست'
                   AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                 ORDER BY CAST(time_sell AS UNSIGNED) DESC
                 LIMIT 100",
                [(string) $id]
            );
            foreach ($invRows as $inv) {
                $ts = (int) ($inv['time_sell'] ?? 0);
                $agentPurchases[] = [
                    'created_at' => $ts,
                    'name_product' => $inv['name_product'] ?? '',
                    'volume' => $inv['Volume'] ?? '',
                    'service_time' => $inv['Service_time'] ?? '',
                    'panel' => $inv['Service_location'] ?? '',
                    'username_service' => $inv['username'] ?? '',
                    'id_invoice' => $inv['id_invoice'] ?? '',
                    'price_product' => $inv['price_product'] ?? '0',
                    'status' => $inv['Status'] ?? '',
                ];
            }
            $agentPurchaseTotal = (int) db_count(
                $pdo,
                "SELECT COUNT(*) FROM invoice
                 WHERE id_user = ?
                   AND name_product != 'سرویس تست'
                   AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')",
                [(string) $id]
            );
        }
    } catch (Exception $e) {
        $agentPurchases = [];
        $agentPurchaseTotal = 0;
    }
}

$tokenMasked = '';
if ($bot && !empty($bot['bot_token'])) {
    $tok = $bot['bot_token'];
    $tokenMasked = strlen($tok) > 12 ? substr($tok, 0, 8) . '…' . substr($tok, -4) : '••••';
}

$pageTitle = 'Agent #' . $id;
$activeNav = 'agents';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px" class="fade-up">
    <a href="agents.php" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> Agents</a>
    <a href="user.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">User profile</a>
</div>

<div class="stats u-stats fade-up" style="margin-bottom:18px">
    <?php if (!$isN2): ?>
    <div class="stat">
        <div class="stat-label">Balance</div>
        <div class="stat-num"><?= number_format($balance) ?><small>USD</small></div>
    </div>
    <div class="stat">
        <div class="stat-label">Current price per GB</div>
        <div class="stat-num"><?= number_format($currentPricePerGb) ?><small>USD</small></div>
    </div>
    <div class="stat">
        <div class="stat-label">Cumulative usage</div>
        <div class="stat-num"><?= number_format($consumedTb, 2) ?><small>TB</small></div>
    </div>
    <?php endif; ?>
    <div class="stat">
        <div class="stat-label">Enabled categories</div>
        <div class="stat-num"><?= number_format(count($enabledCategories)) ?></div>
    </div>
    <?php if ($usesCategoryWhitelist): ?>
    <div class="stat">
        <div class="stat-label">Purchases</div>
        <div class="stat-num"><?= number_format($agentPurchaseTotal) ?></div>
    </div>
    <?php endif; ?>
    <div class="stat">
        <div class="stat-label">Volume used to create services</div>
        <div class="stat-num"><?= number_format($volumeConsumed) ?><small>GB</small></div>
    </div>
    <div class="stat">
        <div class="stat-label">Role</div>
        <div class="stat-num" style="font-size:1rem">
            <span class="tag <?= user_role_tag($agent) ?>"><?= user_role_label($agent) ?></span>
        </div>
    </div>
</div>

<div class="agent-page fade-up">

    <div class="agent-top">
        <div class="card">
            <div class="card-head"><strong>Role and expiry</strong></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                <div class="cf">ID: <span class="cm"><?= $id ?></span>
                    <?php if ($username): ?> · @<?= htmlspecialchars($username) ?><?php endif; ?>
                </div>
                <div class="cf">Expiry: <?= htmlspecialchars($expireLabel) ?></div>
                <form method="POST" action="agent_action.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="set_role">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                    <div class="field" style="flex:1;min-width:140px;margin:0">
                        <label>Role</label>
                        <select name="new_role" class="select">
                            <option value="n" <?= $agent === 'n' ? 'selected' : '' ?>>Agent (n)</option>
                            <option value="n2" <?= $agent === 'n2' ? 'selected' : '' ?>>Advanced (n2)</option>
                            <option value="f">Remove agent role (f)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save role</button>
                </form>
                <button type="button" class="btn btn-ghost btn-sm" onclick="openModal('expireModal')">Set expiry</button>
            </div>
        </div>

        <?php if (!$isN2): ?>
        <div class="card">
            <div class="card-head"><strong>Wallet balance</strong></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                <div class="cf">Current balance: <strong><?= number_format($balance) ?></strong> USD</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn-ok btn-sm" onclick="openModal('addBalModal')">Add balance</button>
                    <button type="button" class="btn btn-no btn-sm" onclick="openModal('lowBalModal')">Deduct balance</button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-head"><strong>Balance and purchase cap</strong></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                <div class="cf">Current balance: <strong><?= number_format($balance) ?></strong> USD</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn-ok btn-sm" onclick="openModal('addBalModal')">Add balance</button>
                    <button type="button" class="btn btn-no btn-sm" onclick="openModal('lowBalModal')">Deduct balance</button>
                </div>
                <form method="POST" action="agent_action.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="set_max_buy">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                    <div class="field" style="flex:1;margin:0">
                        <label>Negative purchase cap (0 = unlimited)</label>
                        <input type="number" name="max" class="input" min="0" value="<?= $maxBuy ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$isN2): ?>
    <div class="card">
        <div class="card-head"><strong>Price tiers (pay per usage)</strong></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            <p class="cf" style="margin:0">
                Cost is based on cumulative purchased volume (1 TB = 1024 GB).
                For example, one price up to 10 TB, another from 10 to 30 TB, and a final price above 30 TB.
                If a purchase crosses a tier boundary, that whole purchase uses the next tier price (not a blend).
                Each tier’s TB ceiling is editable; leave the last tier empty for unlimited.
            </p>
            <p class="cf" style="margin:0">Current usage: <strong><?= number_format($volumeConsumed, 2) ?> GB</strong> (≈ <?= number_format($consumedTb, 3) ?> TB) · Current price per GB: <strong><?= number_format($currentPricePerGb) ?></strong> USD</p>
            <form method="POST" action="agent_action.php" id="tiersForm">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_price_tiers">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                <div class="tbl-wrap">
                    <table class="table" style="width:100%;border-collapse:collapse" id="tiersTable">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:8px">From (cumulative)</th>
                                <th style="text-align:left;padding:8px">Up to (TB)</th>
                                <th style="text-align:left;padding:8px">Price per GB (USD)</th>
                                <th style="text-align:left;padding:8px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tierRows = $priceTiers;
                            if (empty($tierRows)) {
                                $tierRows = [
                                    ['upto_tb' => 10, 'price_per_gb' => $pricePerGb ?: 0],
                                    ['upto_tb' => 30, 'price_per_gb' => $pricePerGb ?: 0],
                                    ['upto_tb' => null, 'price_per_gb' => $pricePerGb ?: 0],
                                ];
                            }
                            $prevLabel = '0';
                            foreach ($tierRows as $ti => $tier):
                                $uptoVal = $tier['upto_tb'];
                                $uptoAttr = $uptoVal === null ? '' : htmlspecialchars((string) $uptoVal);
                                ?>
                                <tr class="tier-row">
                                    <td style="padding:8px" class="tier-from cf"><?= htmlspecialchars($prevLabel) ?></td>
                                    <td style="padding:8px">
                                        <input type="number" name="upto_tb[]" class="input tier-upto" min="0" step="0.01" value="<?= $uptoAttr ?>" placeholder="empty = unlimited" style="min-width:120px">
                                    </td>
                                    <td style="padding:8px">
                                        <input type="number" name="price_per_gb[]" class="input" min="0" step="1" value="<?= (int) ($tier['price_per_gb'] ?? 0) ?>" required style="min-width:140px">
                                    </td>
                                    <td style="padding:8px">
                                        <button type="button" class="btn btn-ghost btn-sm" onclick="removeTierRow(this)">Remove</button>
                                    </td>
                                </tr>
                                <?php
                                $prevLabel = $uptoVal === null ? '—' : ((string) $uptoVal . ' TB');
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="addTierRow()">Add tier</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save tiers</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($usesCategoryWhitelist): ?>
    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">Allowed categories<?= $isN2 ? ' — advanced' : '' ?></div>
                <div class="card-subtitle"><?= number_format(count($enabledCategories)) ?> of <?= number_format(count($allCategories)) ?> enabled</div>
            </div>
        </div>
        <div class="card-body">
            <p class="cf" style="margin-bottom:14px"><?= $isN2
                ? 'Enable the categories this agent can see and buy from (no credit).'
                : 'Enable the categories this agent can see and buy from. Each purchase is charged to the wallet using the price tiers.' ?></p>
            <form method="POST" action="agent_action.php">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_n2_categories">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                <?php if (empty($allCategories)): ?>
                    <p class="cf">No categories have been added.</p>
                <?php else: ?>
                    <div class="agent-cat-grid">
                        <?php foreach ($allCategories as $c):
                            $remark = (string) ($c['remark'] ?? '');
                            if ($remark === '') continue;
                            $checked = !empty($enabledCategories[$remark]);
                            ?>
                            <label class="agent-cat-item <?= $checked ? 'is-on' : '' ?>">
                                <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($remark) ?>" <?= $checked ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($remark) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:14px">Save enabled categories</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">Agent purchases</div>
                <div class="card-subtitle"><?= number_format($agentPurchaseTotal) ?> purchases<?= $agentPurchaseTotal > count($agentPurchases) ? ' · showing ' . count($agentPurchases) . ' recent' : '' ?></div>
            </div>
        </div>
        <div class="card-body" style="padding-top:0;padding-bottom:0">
            <?php if (empty($agentPurchases)): ?>
                <p class="cf" style="padding:16px 0">No purchases yet.</p>
            <?php else: ?>
                <div class="tbl-wrap">
                    <table class="table tbl-lg" style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:8px">Date</th>
                                <th style="text-align:left;padding:8px">Product</th>
                                <th style="text-align:left;padding:8px">Volume</th>
                                <th style="text-align:left;padding:8px">Time</th>
                                <th style="text-align:left;padding:8px">Panel</th>
                                <th style="text-align:left;padding:8px">Username</th>
                                <th style="text-align:left;padding:8px">Invoice</th>
                                <th style="text-align:left;padding:8px"><?= $isN2 ? 'Catalog price' : 'Amount' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agentPurchases as $pur):
                                $ts = (int) ($pur['created_at'] ?? 0);
                                $dateLabel = $ts > 0 ? date('Y/m/d H:i', $ts) : '—';
                                ?>
                                <tr>
                                    <td style="padding:8px"><?= htmlspecialchars($dateLabel) ?></td>
                                    <td style="padding:8px"><?= htmlspecialchars($pur['name_product'] ?? '') ?></td>
                                    <td style="padding:8px"><?= htmlspecialchars((string) ($pur['volume'] ?? '')) ?> GB</td>
                                    <td style="padding:8px"><?= htmlspecialchars((string) ($pur['service_time'] ?? '')) ?></td>
                                    <td style="padding:8px"><?= htmlspecialchars($pur['panel'] ?? '') ?></td>
                                    <td style="padding:8px" class="cm"><?= htmlspecialchars($pur['username_service'] ?? '') ?></td>
                                    <td style="padding:8px" class="cm"><?= htmlspecialchars($pur['id_invoice'] ?? '') ?></td>
                                    <td style="padding:8px"><?= number_format((int) ($pur['price_product'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head"><strong>Agent sales bot</strong></div>
        <div class="card-body">
            <?php if (!$bot): ?>
                <p class="cf" style="margin-bottom:12px">No sales bot is active. Get a bot token from BotFather and enable it.</p>
                    <form method="POST" action="agent_action.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;max-width:560px" id="createBotForm">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="create_bot">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                    <div class="field" style="flex:1;margin:0">
                        <label>Bot token</label>
                        <input type="text" name="token" class="input" required placeholder="123456:ABC-DEF...">
                    </div>
                    <button type="submit" class="btn btn-primary" id="createBotBtn">Enable bot</button>
                </form>
                <p class="cf" style="margin-top:8px;font-size:.8rem">After submit, wait up to about 15 seconds while Telegram is contacted.</p>
                <script>
                (function () {
                    var f = document.getElementById('createBotForm');
                    var b = document.getElementById('createBotBtn');
                    if (!f || !b) return;
                    f.addEventListener('submit', function () {
                        b.disabled = true;
                        b.textContent = 'Creating…';
                    });
                })();
                </script>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px">
                    <div>
                        <div class="cf" style="font-size:.75rem">Username</div>
                        <div><a href="https://t.me/<?= htmlspecialchars($bot['username']) ?>" target="_blank" rel="noopener">@<?= htmlspecialchars($bot['username']) ?></a></div>
                    </div>
                    <div>
                        <div class="cf" style="font-size:.75rem">Token</div>
                        <div class="cm" style="word-break:break-all" id="botTokenDisplay"><?= htmlspecialchars($tokenMasked) ?></div>
                        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:6px"
                            onclick="navigator.clipboard.writeText(<?= json_encode($bot['bot_token']) ?>).then(()=>this.textContent='Copied')">Copy token</button>
                    </div>
                    <div>
                        <div class="cf" style="font-size:.75rem">Created</div>
                        <div><?= htmlspecialchars($bot['time'] ?? '—') ?></div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                    <form method="POST" action="agent_action.php" style="display:flex;gap:8px;align-items:end">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="set_bot_min_volume">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                        <div class="field" style="margin:0">
                            <label>Minimum volume price (retail)</label>
                            <input type="number" name="amount" class="input" min="0" value="<?= (int) ($botSetting['minpricevolume'] ?? 4000) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                    </form>
                    <form method="POST" action="agent_action.php" style="display:flex;gap:8px;align-items:end">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="set_bot_min_time">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                        <div class="field" style="margin:0">
                            <label>Minimum time price (retail)</label>
                            <input type="number" name="amount" class="input" min="0" value="<?= (int) ($botSetting['minpricetime'] ?? 4000) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                    </form>
                </div>

                <?php
                $botSetting = botsaz_normalize_setting($botSetting);
                $cardNumber = (string) ($botSetting['card_number'] ?? '');
                $cardHolder = (string) ($botSetting['card_holder'] ?? '');
                $cartInfo = (string) ($botSetting['cart_info'] ?? '');
                ?>
                <form method="POST" action="agent_action.php" style="margin-bottom:16px;padding:12px;border:1px solid var(--border, #333);border-radius:8px">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="set_bot_card_payment">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                    <div class="cf" style="margin-bottom:10px;font-weight:600">💳 Card-to-card payment (agent bot)</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
                        <div class="field" style="margin:0">
                            <label>Card number</label>
                            <input type="text" name="card_number" class="input" inputmode="numeric" maxlength="19" value="<?= htmlspecialchars($cardNumber) ?>" placeholder="6037...">
                        </div>
                        <div class="field" style="margin:0">
                            <label>Cardholder name</label>
                            <input type="text" name="card_holder" class="input" maxlength="80" value="<?= htmlspecialchars($cardHolder) ?>" placeholder="Full name">
                        </div>
                    </div>
                    <div class="field" style="margin-top:12px">
                        <label>Payment instructions</label>
                        <textarea name="cart_info" class="input" rows="3" placeholder="After paying, send the receipt..."><?= htmlspecialchars($cartInfo) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">Save card settings</button>
                </form>

                <?php if (!empty($allPanels)): ?>
                    <form method="POST" action="agent_action.php" style="margin-bottom:16px">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="set_hide_panels">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                        <div class="field">
                            <label>Hidden panels for this bot</label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
                                <?php foreach ($allPanels as $pl):
                                    $name = $pl['name_panel'] ?? '';
                                    if ($name === '') continue;
                                    $checked = in_array($name, $hidePanels, true);
                                    ?>
                                    <label class="tag <?= $checked ? 'tag-no' : 'tag-plain' ?>" style="cursor:pointer">
                                        <input type="checkbox" name="panels[]" value="<?= htmlspecialchars($name) ?>" <?= $checked ? 'checked' : '' ?> style="margin-left:4px">
                                        <?= htmlspecialchars($name) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">Save hidden panels</button>
                    </form>
                <?php endif; ?>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                    <a href="agent_action.php?action=repair_bot&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=agent.php?id=<?= $id ?>"
                        class="btn btn-primary btn-sm" data-confirm="Rebuild bot files from the template and set the webhook?">Repair / rebuild bot</a>
                    <a href="agent_action.php?action=remove_bot&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=agent.php?id=<?= $id ?>"
                        class="btn btn-no btn-sm" data-confirm="Delete this agent’s sales bot?">Delete sales bot</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal-veil" id="expireModal">
    <div class="modal">
        <div class="modal-head"><h3>Agent expiry</h3><button class="modal-x" onclick="closeModal('expireModal')"><?= icon('close', 14) ?></button></div>
        <form method="POST" action="agent_action.php">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_expire">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                <div class="field">
                    <label>Days from today (0 = remove expiry)</label>
                    <input type="number" name="days" class="input" min="0" value="30" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary">Set</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('expireModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="addBalModal">
    <div class="modal">
        <div class="modal-head"><h3>Add balance</h3><button class="modal-x" onclick="closeModal('addBalModal')"><?= icon('close', 14) ?></button></div>
        <form method="POST" action="agent_action.php">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_balance">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                <div class="field">
                    <label>Amount (USD)</label>
                    <input type="number" name="amount" class="input" min="1" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-ok">Add</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addBalModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="lowBalModal">
    <div class="modal">
        <div class="modal-head"><h3>Deduct balance</h3><button class="modal-x" onclick="closeModal('lowBalModal')"><?= icon('close', 14) ?></button></div>
        <form method="POST" action="agent_action.php">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="low_balance">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="back" value="agent.php?id=<?= $id ?>">
                <div class="field">
                    <label>Amount (USD)</label>
                    <input type="number" name="amount" class="input" min="1" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-no">Deduct</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('lowBalModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$isN2): ?>
<script>
function refreshTierFromLabels() {
    const rows = document.querySelectorAll('#tiersTable tbody .tier-row');
    let prev = '0';
    rows.forEach((row) => {
        const fromCell = row.querySelector('.tier-from');
        if (fromCell) fromCell.textContent = prev;
        const upto = row.querySelector('.tier-upto');
        const v = upto ? String(upto.value || '').trim() : '';
        prev = v === '' ? '—' : (v + ' TB');
    });
}
function addTierRow() {
    const tbody = document.querySelector('#tiersTable tbody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.className = 'tier-row';
    tr.innerHTML = `
        <td style="padding:8px" class="tier-from cf">—</td>
        <td style="padding:8px">
            <input type="number" name="upto_tb[]" class="input tier-upto" min="0" step="0.01" value="" placeholder="empty = unlimited" style="min-width:120px">
        </td>
        <td style="padding:8px">
            <input type="number" name="price_per_gb[]" class="input" min="0" step="1" value="0" required style="min-width:140px">
        </td>
        <td style="padding:8px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="removeTierRow(this)">Remove</button>
        </td>`;
    tbody.appendChild(tr);
    const upto = tr.querySelector('.tier-upto');
    if (upto) upto.addEventListener('input', refreshTierFromLabels);
    refreshTierFromLabels();
}
function removeTierRow(btn) {
    const tbody = document.querySelector('#tiersTable tbody');
    if (!tbody || tbody.querySelectorAll('.tier-row').length <= 1) return;
    const row = btn.closest('tr');
    if (row) row.remove();
    refreshTierFromLabels();
}
document.querySelectorAll('#tiersTable .tier-upto').forEach((el) => {
    el.addEventListener('input', refreshTierFromLabels);
});
</script>
<?php endif; ?>

<script>
document.querySelectorAll('.agent-cat-item input[type="checkbox"]').forEach((cb) => {
    cb.addEventListener('change', function () {
        this.closest('.agent-cat-item')?.classList.toggle('is-on', this.checked);
    });
});
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
