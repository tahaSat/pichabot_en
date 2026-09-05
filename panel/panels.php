<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/panels_lib.php';
require_administrator();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check_post();
    $name = trim($_POST['name_panel'] ?? '');
    $type = $_POST['type'] ?? 'marzban';
    if ($name === '') {
        flash('error', 'Panel name is required.');
        header('Location: panels.php');
        exit;
    }
    if (!isset(PANEL_TYPES[$type])) {
        flash('error', 'Invalid panel type.');
        header('Location: panels.php');
        exit;
    }
    if (panel_name_exists($pdo, $name)) {
        flash('error', 'A panel with this name already exists.');
        header('Location: panels.php');
        exit;
    }
    $url = trim($_POST['url_panel'] ?? '');
    if (in_array($type, ['marzban', 'marzneshin', 'x-ui_single', 'alireza_single', 'ibsng', 'mikrotik'], true) && $url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'Panel URL is not valid.');
        header('Location: panels.php');
        exit;
    }
    try {
        $id = panel_insert_defaults($pdo, [
            'name_panel' => $name,
            'type' => $type,
            'url_panel' => $url,
            'username_panel' => $_POST['username_panel'] ?? '',
            'password_panel' => $_POST['password_panel'] ?? '',
            'limit_panel' => trim($_POST['limit_panel'] ?? '') ?: 'unlimted',
        ]);
        flash('success', 'Panel “' . $name . '” was added. Complete the extra settings on the edit page.');
        header('Location: panel.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
        header('Location: panels.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check_post();
    $id = (int) ($_POST['id'] ?? 0);
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm !== 'CONFIRM') {
        flash('error', 'Type CONFIRM to delete.');
        header('Location: panel.php?id=' . $id);
        exit;
    }
    $row = db_fetch($pdo, "SELECT name_panel FROM marzban_panel WHERE id = ?", [$id]);
    if ($row) {
        db_query($pdo, "DELETE FROM marzban_panel WHERE id = ?", [$id]);
        flash('success', 'Panel “' . $row['name_panel'] . '” was deleted.');
    }
    header('Location: panels.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$params = [];
$where = [];
if ($search !== '') {
    $where[] = "(name_panel LIKE ? OR code_panel LIKE ? OR type LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
if ($typeFilter !== '' && isset(PANEL_TYPES[$typeFilter])) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel $whereSQL ORDER BY id DESC", $params);
} catch (Exception $e) {
    $panels = [];
}

$pageTitle = 'VPN panels';
$pageLede = 'Manage connections and settings for Marzban, Sanaei, Hiddify, and other panel types.';
$activeNav = 'panels';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($panels) ?> panels</div>
  <button class="btn btn-primary" onclick="openModal('addPanelModal')"><?= icon('plus', 14) ?> Add panel</button>
</div>

<div class="card fade-up d1">
  <div class="toolbar">
    <div class="toolbar-title">Panel list</div>
    <form method="GET" class="toolbar-end" style="display:flex;gap:8px;flex-wrap:wrap">
      <div class="search-box" style="min-width:200px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or code...">
      </div>
      <select name="type" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">All types</option>
        <?php foreach (PANEL_TYPES as $k => $label): ?>
          <option value="<?= htmlspecialchars($k) ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
    </form>
  </div>

  <?php if (empty($panels)): ?>
    <div class="empty" style="padding:50px 20px">
      <p>No panels yet</p>
      <button class="btn btn-primary" style="margin-top:12px" onclick="openModal('addPanelModal')"><?= icon('plus', 14) ?> Add panel</button>
    </div>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl-xl">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Type</th>
            <th>Status</th>
            <th>Group</th>
            <th>Limit</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($panels as $p): ?>
            <tr>
              <td class="cf"><?= (int) $p['id'] ?></td>
              <td class="cs">
                <a href="panel.php?id=<?= (int) $p['id'] ?>" style="color:var(--text);font-weight:600;text-decoration:none">
                  <?= htmlspecialchars($p['name_panel'] ?? '') ?>
                </a>
                <div class="cf" style="font-size:.7rem;margin-top:2px">Code: <?= htmlspecialchars($p['code_panel'] ?? '') ?></div>
              </td>
              <td><span class="tag tag-info"><?= htmlspecialchars(panel_type_label($p['type'] ?? '')) ?></span></td>
              <td>
                <span class="tag <?= ($p['status'] ?? '') === 'active' ? 'tag-ok' : 'tag-no' ?>">
                  <?= panel_status_label($p['status'] ?? '') ?>
                </span>
              </td>
              <td class="cf"><?= htmlspecialchars(panel_agent_label($p['agent'] ?? 'all')) ?></td>
              <td class="cn"><?= htmlspecialchars($p['limit_panel'] ?? '—') ?></td>
              <td>
                <a href="panel.php?id=<?= (int) $p['id'] ?>" class="btn btn-ghost btn-sm"><?= icon('edit', 13) ?> Manage</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-veil" id="addPanelModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <h3>Add new panel</h3>
      <button class="modal-x" onclick="closeModal('addPanelModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
          <div class="field full">
            <label>Panel type *</label>
            <select name="type" class="select" required id="addPanelType">
              <?php foreach (PANEL_TYPES as $k => $label): ?>
                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field full">
            <label>Panel name (location) *</label>
            <input type="text" name="name_panel" class="input" placeholder="e.g. Germany 1" required>
          </div>
          <div class="field full" id="urlField">
            <label>Panel URL</label>
            <input type="url" name="url_panel" class="input" placeholder="https://panel.example.com">
            <small class="cf" style="display:block;margin-top:4px">Optional for manual sale and Hiddify</small>
          </div>
          <div class="field" id="userField">
            <label>Panel username</label>
            <input type="text" name="username_panel" class="input" autocomplete="off">
          </div>
          <div class="field" id="passField">
            <label>Password / token</label>
            <input type="text" name="password_panel" class="input" autocomplete="off">
          </div>
          <div class="field full">
            <label>Account creation limit</label>
            <input type="text" name="limit_panel" class="input" placeholder="unlimted or a number">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> Create panel</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('addPanelModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var sel = document.getElementById('addPanelType');
  if (!sel) return;
  function sync() {
    var t = sel.value;
    var manual = t === 'Manualsale' || t === 'hiddify';
    var tokenOnly = t === 's_ui' || t === 'WGDashboard';
    document.getElementById('userField').style.display = tokenOnly ? 'none' : '';
    document.getElementById('passField').querySelector('label').textContent =
      tokenOnly ? 'API token' : 'Password / token';
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
