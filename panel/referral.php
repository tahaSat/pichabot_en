<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/referral_lib.php';
require_once __DIR__ . '/inc/panels_lib.php';
require_auth();
require_administrator();
$pdo = panel_ensure_pdo();
referral_ensure_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_master') {
    csrf_check_post();
    $new = referral_lib_toggle_master($pdo);
    flash('success', $new === 'onreferral' ? 'Invite system enabled.' : 'Invite system disabled.');
    header('Location: referral.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check_post();
    try {
        $id = (int) ($_POST['edit_id'] ?? 0);
        referral_lib_save_campaign($pdo, [
            'code' => $_POST['code'] ?? '',
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'code_product' => $_POST['code_product'] ?? '',
            'required_invites' => $_POST['required_invites'] ?? 1,
            'status' => $_POST['status'] ?? 'inactive',
            'new_users_only' => isset($_POST['new_users_only']) ? 1 : 0,
        ], $id ?: null);
        flash('success', $id ? 'Campaign updated.' : 'Campaign created.');
    } catch (Exception $e) {
        flash('error', $e->getMessage());
    }
    header('Location: referral.php');
    exit;
}

if (isset($_GET['toggle'])) {
    csrf_check_get();
    try {
        referral_lib_toggle_status($pdo, (int) $_GET['toggle']);
        flash('success', 'Campaign status changed.');
    } catch (Exception $e) {
        flash('error', $e->getMessage());
    }
    header('Location: referral.php');
    exit;
}

if (isset($_GET['delete'])) {
    csrf_check_get();
    db_query($pdo, "DELETE FROM referral_campaign WHERE id = ?", [(int) $_GET['delete']]);
    flash('success', 'Campaign deleted.');
    header('Location: referral.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grant_reward') {
    csrf_check_post();
    $grantCampaignId = (int) ($_POST['campaign_id'] ?? 0);
    $grantUserId = trim((string) ($_POST['user_id'] ?? ''));
    try {
        $result = referral_lib_manual_grant($pdo, $grantCampaignId, $grantUserId);
    } catch (Throwable $e) {
        error_log('grant_reward: ' . $e->getMessage());
        $result = ['ok' => false, 'msg' => 'System error: ' . $e->getMessage()];
    }
    flash($result['ok'] ? 'success' : 'error', $result['msg']);
    header('Location: referral.php?view=' . $grantCampaignId . '&scan=1#pending');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grant_last_product') {
    csrf_check_post();
    $grantCampaignId = (int) ($_POST['campaign_id'] ?? 0);
    $grantUserId = trim((string) ($_POST['user_id'] ?? ''));
    try {
        $result = referral_lib_grant_last_product($pdo, $grantCampaignId, $grantUserId);
    } catch (Throwable $e) {
        error_log('grant_last_product: ' . $e->getMessage());
        $result = ['ok' => false, 'msg' => 'System error: ' . $e->getMessage()];
    }
    flash($result['ok'] ? 'success' : 'error', $result['msg']);
    header('Location: referral.php?view=' . $grantCampaignId . '&scan=1#pending');
    exit;
}

$campaigns = referral_lib_list_campaigns($pdo);
$products = referral_lib_products($pdo);
$master_status = referral_lib_master_status($pdo);
$view_id = (int) ($_GET['view'] ?? 0);
$invite_search = trim($_GET['q'] ?? '');
$invite_page = max(1, (int) ($_GET['page'] ?? 1));
$invite_per_page = 25;
$do_scan = isset($_GET['scan']);
$view_campaign = $view_id ? referral_lib_get_campaign($pdo, $view_id) : null;
$recent_invites = [];
$invite_total = 0;
$invite_total_pages = 1;
$view_stats = ['invites' => 0, 'referrers' => 0, 'rewards' => 0];
$pending_rewards = [];
if ($view_campaign) {
    $view_stats = referral_lib_campaign_stats($pdo, $view_id);
    $invite_result = referral_lib_list_invites(
        $pdo,
        $view_id,
        $invite_search,
        $invite_per_page,
        ($invite_page - 1) * $invite_per_page
    );
    $recent_invites = $invite_result['rows'];
    $invite_total = (int) $invite_result['total'];
    $invite_total_pages = max(1, (int) ceil($invite_total / $invite_per_page));
    if ($do_scan) {
        $pending_rewards = referral_lib_pending_rewards($pdo, $view_id);
    }
}

$pageTitle = 'Free product campaign';
$pageLede = 'Manage invite links, required invite count, and the service reward.';
$activeNav = 'referral';
$referralTab = 'campaign';
include __DIR__ . '/inc/layout_head.php';
include __DIR__ . '/inc/referral_nav.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px" class="fade-up">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="tag <?= $master_status === 'onreferral' ? 'tag-ok' : 'tag-no' ?>">
      <?= $master_status === 'onreferral' ? 'System on' : 'System off' ?>
    </span>
    <form method="post" style="margin:0">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="toggle_master">
      <button type="submit" class="btn btn-ghost btn-sm">
        <?= $master_status === 'onreferral' ? 'Disable' : 'Enable' ?> system
      </button>
    </form>
  </div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> New campaign</button>
</div>

<div class="card fade-up d1">
  <?php if (empty($campaigns)): ?>
    <div class="empty" style="padding:60px 20px">
      <p>No invite campaigns yet.</p>
      <button class="btn btn-primary" style="margin-top:14px" onclick="openModal('addModal')"><?= icon('plus', 14) ?> Create the first campaign</button>
    </div>
  <?php else: ?>
    <div class="toolbar">
      <div class="toolbar-title">Campaigns <small>(<?= count($campaigns) ?>)</small></div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl-lg">
        <thead>
          <tr>
            <th>#</th>
            <th>Title</th>
            <th>Product</th>
            <th>Invites</th>
            <th>Stats</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($campaigns as $c):
            $product = db_fetch($pdo, "SELECT name_product FROM product WHERE code_product = ?", [$c['code_product']]);
            ?>
            <tr>
              <td class="cn"><?= (int) $c['id'] ?></td>
              <td><?= htmlspecialchars($c['title']) ?></td>
              <td class="cf"><?= htmlspecialchars($product['name_product'] ?? $c['code_product']) ?></td>
              <td class="cn"><?= (int) $c['required_invites'] ?></td>
              <td class="cf">
                <?= (int) ($c['stats']['invites'] ?? 0) ?> invites ·
                <?= (int) ($c['stats']['rewards'] ?? 0) ?> rewards
              </td>
              <td>
                <span class="tag <?= ($c['status'] ?? '') === 'active' ? 'tag-ok' : 'tag-warn' ?>">
                  <?= ($c['status'] ?? '') === 'active' ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                  <a href="referral.php?view=<?= (int) $c['id'] ?>#invites" class="btn btn-ghost btn-sm">Details</a>
                  <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">Edit</button>
                  <a href="referral.php?toggle=<?= (int) $c['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-ghost btn-sm">Toggle status</a>
                  <a href="referral.php?delete=<?= (int) $c['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-no btn-sm" data-confirm="Delete campaign “<?= htmlspecialchars($c['title']) ?>”?">Delete</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($view_campaign): ?>
  <div class="card fade-up d2" style="margin-top:18px" id="invites">
    <div class="toolbar" style="flex-wrap:wrap;gap:10px">
      <div class="toolbar-title">Invites — <?= htmlspecialchars($view_campaign['title']) ?></div>
      <div class="toolbar-end" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="tag"><?= number_format((int) $view_stats['invites']) ?> joins</span>
        <span class="tag"><?= number_format((int) $view_stats['referrers']) ?> referrers</span>
        <span class="tag tag-ok"><?= number_format((int) $view_stats['rewards']) ?> rewards</span>
        <span class="tag tag-warn"><?= (int) $view_campaign['required_invites'] ?> required invites</span>
        <a href="referral.php?view=<?= (int) $view_id ?>&scan=1#pending" class="btn btn-primary btn-sm">
          <?= icon('search', 14) ?> Scan eligible without reward
        </a>
      </div>
    </div>
    <div class="toolbar" style="border-top:1px solid var(--bd,rgba(0,0,0,.06));padding-top:12px">
      <div class="toolbar-title" style="font-size:.9rem">Invite list <small>(<?= number_format($invite_total) ?>)</small></div>
      <form method="GET" class="toolbar-end">
        <input type="hidden" name="view" value="<?= (int) $view_id ?>">
        <?php if ($do_scan): ?><input type="hidden" name="scan" value="1"><?php endif; ?>
        <div class="search-box" style="min-width:240px">
          <?= icon('search', 15) ?>
          <input type="text" name="q" value="<?= htmlspecialchars($invite_search) ?>" placeholder="ID or username..." autocomplete="off">
          <button type="submit" class="search-btn">Search</button>
        </div>
        <?php if ($invite_search !== ''): ?>
          <a href="referral.php?view=<?= (int) $view_id ?><?= $do_scan ? '&scan=1' : '' ?>#invites" class="btn-link" style="font-size:.78rem">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <?php if (empty($recent_invites)): ?>
      <div class="empty" style="padding:36px">
        <p><?= $invite_search !== '' ? 'No results found.' : 'No invites yet.' ?></p>
      </div>
    <?php else: ?>
      <div class="tbl-wrap">
        <table class="tbl-lg">
          <thead>
            <tr>
              <th>Referrer</th>
              <th>Referrer Telegram ID</th>
              <th>Invitee</th>
              <th>Invitee Telegram ID</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_invites as $inv): ?>
              <tr>
                <td><?= !empty($inv['referrer_username']) ? '@' . htmlspecialchars($inv['referrer_username']) : '—' ?></td>
                <td class="cm"><?= htmlspecialchars((string) $inv['referrer_id']) ?></td>
                <td><?= !empty($inv['invited_username']) ? '@' . htmlspecialchars($inv['invited_username']) : '—' ?></td>
                <td class="cm"><?= htmlspecialchars((string) $inv['invited_user_id']) ?></td>
                <td class="cf"><?= htmlspecialchars($inv['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($invite_total_pages > 1): ?>
        <div class="tbl-foot">
          <span><?= number_format($invite_total) ?> invites · page <?= $invite_page ?> of <?= $invite_total_pages ?></span>
          <div class="pager">
            <?php
            $invite_qs = fn($p) => 'referral.php?view=' . (int) $view_id
                . '&q=' . urlencode($invite_search)
                . ($do_scan ? '&scan=1' : '')
                . '&page=' . $p
                . '#invites';
            ?>
            <a class="<?= $invite_page <= 1 ? 'dis' : '' ?>" href="<?= $invite_qs(max(1, $invite_page - 1)) ?>">‹</a>
            <?php for ($p = max(1, $invite_page - 2); $p <= min($invite_total_pages, $invite_page + 2); $p++): ?>
              <a class="<?= $p === $invite_page ? 'cur' : '' ?>" href="<?= $invite_qs($p) ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a class="<?= $invite_page >= $invite_total_pages ? 'dis' : '' ?>" href="<?= $invite_qs(min($invite_total_pages, $invite_page + 1)) ?>">›</a>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($do_scan): ?>
    <div class="card fade-up d3" style="margin-top:18px" id="pending">
      <div class="toolbar">
        <div class="toolbar-title">
          Eligible without reward
          <small>(<?= count($pending_rewards) ?> people · min. <?= (int) $view_campaign['required_invites'] ?> invites)</small>
        </div>
        <a href="referral.php?view=<?= (int) $view_id ?>#invites" class="btn btn-ghost btn-sm">Close scan</a>
      </div>
      <?php if (empty($pending_rewards)): ?>
        <div class="empty" style="padding:36px">
          <p>No one with enough invites and no reward was found.</p>
        </div>
      <?php else: ?>
        <p class="cf" style="padding:0 16px 12px;margin:0">
          These users have reached the required invites but have no reward record (the subscription may have failed to create). Confirm to create the service and send it on Telegram.
        </p>
        <div class="tbl-wrap">
          <table class="tbl-lg">
            <thead>
              <tr>
                <th>Referrer</th>
                <th>Telegram ID</th>
                <th>Invite count</th>
                <th>Required</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending_rewards as $row): ?>
                <tr>
                  <td><?= !empty($row['username']) ? '@' . htmlspecialchars((string) $row['username']) : '—' ?></td>
                  <td class="cm"><?= htmlspecialchars((string) $row['referrer_id']) ?></td>
                  <td class="cn"><?= (int) $row['invite_count'] ?></td>
                  <td class="cn"><?= (int) $view_campaign['required_invites'] ?></td>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <form method="post" style="margin:0">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="grant_reward">
                        <input type="hidden" name="campaign_id" value="<?= (int) $view_id ?>">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $row['referrer_id']) ?>">
                        <button
                          type="submit"
                          class="btn btn-primary btn-sm"
                          onclick="return confirm('Send the reward to this user?');"
                        ><?= icon('check', 13) ?> Confirm and send reward</button>
                      </form>
                      <form method="post" style="margin:0">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="grant_last_product">
                        <input type="hidden" name="campaign_id" value="<?= (int) $view_id ?>">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $row['referrer_id']) ?>">
                        <button
                          type="submit"
                          class="btn btn-ghost btn-sm"
                          onclick="return confirm('Record this user’s latest product as the reward and remove them from the list?');"
                        >Use latest product as reward</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="modal-veil" id="addModal">
  <div class="modal">
    <div class="modal-head">
      <h3>New invite campaign</h3>
      <button type="button" class="modal-x" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <div class="modal-body">
        <p class="cf" style="margin-bottom:12px">Each user’s link is built from their <b>numeric Telegram ID</b> automatically.</p>
        <label class="lbl">Title</label>
        <input class="inp" name="title" placeholder="Summer campaign">
        <label class="lbl">Description</label>
        <textarea class="inp" name="description" rows="3" placeholder="Text shown to the user"></textarea>
        <label class="lbl">Reward product</label>
        <select class="inp" name="code_product" required>
          <option value="">Select a product</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= htmlspecialchars($p['code_product']) ?>"><?= htmlspecialchars($p['name_product']) ?> (<?= htmlspecialchars($p['Location']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <label class="lbl">Required invites</label>
        <input class="inp" type="number" name="required_invites" min="1" value="3" required>
        <label class="lbl"><input type="checkbox" name="new_users_only" value="1" checked> New users only</label>
        <label class="lbl">Status</label>
        <select class="inp" name="status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Edit campaign</h3>
      <button type="button" class="modal-x" onclick="closeModal('editModal')">✕</button>
    </div>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="edit_id" id="edit_id">
      <div class="modal-body">
        <label class="lbl">Campaign ID</label>
        <input class="inp" id="edit_id_display" readonly disabled>
        <label class="lbl">Title</label>
        <input class="inp" name="title" id="edit_title">
        <label class="lbl">Description</label>
        <textarea class="inp" name="description" id="edit_description" rows="3"></textarea>
        <label class="lbl">Reward product</label>
        <select class="inp" name="code_product" id="edit_code_product" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= htmlspecialchars($p['code_product']) ?>"><?= htmlspecialchars($p['name_product']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="lbl">Invite count</label>
        <input class="inp" type="number" name="required_invites" id="edit_required_invites" min="1" required>
        <label class="lbl"><input type="checkbox" name="new_users_only" id="edit_new_users_only" value="1"> New users only</label>
        <label class="lbl">Status</label>
        <select class="inp" name="status" id="edit_status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(c) {
  document.getElementById('edit_id').value = c.id;
  document.getElementById('edit_id_display').value = c.id;
  document.getElementById('edit_title').value = c.title || '';
  document.getElementById('edit_description').value = c.description || '';
  document.getElementById('edit_code_product').value = c.code_product || '';
  document.getElementById('edit_required_invites').value = c.required_invites || 1;
  document.getElementById('edit_new_users_only').checked = String(c.new_users_only) === '1';
  document.getElementById('edit_status').value = c.status || 'inactive';
  openModal('editModal');
}
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
