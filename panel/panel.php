<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/panels_lib.php';
require_administrator();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'No panel specified.');
    header('Location: panels.php');
    exit;
}

$panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE id = ?", [$id]);
if (!$panel) {
    flash('error', 'Panel not found.');
    header('Location: panels.php');
    exit;
}

// Ensure custommonths column exists (migration for older DBs).
try {
    $cmCol = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'custommonths'");
    if (!($cmCol && $cmCol->fetch(PDO::FETCH_ASSOC))) {
        $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN custommonths TEXT NULL");
        $pdo->exec("UPDATE marzban_panel SET custommonths = " . $pdo->quote(panel_default_custommonths()) . " WHERE custommonths IS NULL OR custommonths = ''");
        $panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE id = ?", [$id]) ?: $panel;
    }
} catch (Throwable $e) {
    // ignore — save handler skips missing column
}

try {
    $ncTestCol = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'namecustom_test'");
    if (!($ncTestCol && $ncTestCol->fetch(PDO::FETCH_ASSOC))) {
        $pdo->exec("ALTER TABLE marzban_panel ADD COLUMN namecustom_test VARCHAR(100) NULL");
        $panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE id = ?", [$id]) ?: $panel;
    }
} catch (Throwable $e) {
    // ignore — save handler skips missing column
}

$ptype = $panel['type'] ?? 'marzban';
$features = panel_features_for_type($ptype);
$isPasarguard = panel_is_pasarguard($panel);
$pasarguardGroupIds = panel_format_pasarguard_group_ids($panel['inbounds'] ?? null);
$tab = $_GET['tab'] ?? 'connection';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check_post();
    $newName = trim($_POST['name_panel'] ?? '');
    if ($newName === '') {
        flash('error', 'Panel name is required.');
        header('Location: panel.php?id=' . $id . '&tab=' . urlencode($tab));
        exit;
    }
    if (panel_name_exists($pdo, $newName, $id)) {
        flash('error', 'Panel name is already in use.');
        header('Location: panel.php?id=' . $id);
        exit;
    }
    $url = array_key_exists('url_panel', $_POST) ? trim($_POST['url_panel']) : ($panel['url_panel'] ?? '');
    if (array_key_exists('url_panel', $_POST) && $url !== '' && $url !== 'null' && !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'Panel URL is not valid.');
        header('Location: panel.php?id=' . $id);
        exit;
    }
    $oldName = $panel['name_panel'];
    if ($oldName !== $newName) {
        panel_rename_cascade($pdo, $oldName, $newName);
    }

    $hideJson = array_key_exists('hide_users', $_POST)
        ? panel_format_hide_users(preg_split('/[\s,]+/', trim($_POST['hide_users']), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        : ($panel['hide_user'] ?? '[]');

    $tabSaving = $_POST['tab'] ?? 'connection';
    $toggleInForm = array_flip(panel_toggle_keys_for_tab($tabSaving));
    $toggle = fn(string $postKey, string $dbField, string $onVal, string $offVal) =>
        panel_toggle_field($panel, $postKey, $dbField, $onVal, $offVal, isset($toggleInForm[$postKey]));

    if (array_key_exists('pasarguard_group_ids', $_POST)) {
        $parsedGroups = panel_parse_pasarguard_group_ids((string) $_POST['pasarguard_group_ids']);
        if ($parsedGroups === false) {
            flash('error', 'Invalid PasarGuard group ID. Only comma-separated numbers are allowed (example: 1 or 1,2).');
            header('Location: panel.php?id=' . $id . '&tab=connection');
            exit;
        }
    } else {
        $parsedGroups = null;
    }

    $data = [
        'name_panel' => $newName,
        'url_panel' => array_key_exists('url_panel', $_POST) ? $url : ($panel['url_panel'] ?? ''),
        'username_panel' => array_key_exists('username_panel', $_POST) ? trim($_POST['username_panel']) : ($panel['username_panel'] ?? ''),
        'password_panel' => array_key_exists('password_panel', $_POST) ? trim($_POST['password_panel']) : ($panel['password_panel'] ?? ''),
        'linksubx' => array_key_exists('linksubx', $_POST) ? trim($_POST['linksubx']) : ($panel['linksubx'] ?? ''),
        'secret_code' => array_key_exists('secret_code', $_POST) ? trim($_POST['secret_code']) : ($panel['secret_code'] ?? ''),
        'inboundid' => array_key_exists('inboundid', $_POST) ? trim($_POST['inboundid']) : ($panel['inboundid'] ?? ''),
        'namecustom' => array_key_exists('namecustom', $_POST) ? trim($_POST['namecustom']) : ($panel['namecustom'] ?? ''),
        'namecustom_test' => array_key_exists('namecustom_test', $_POST) ? trim($_POST['namecustom_test']) : ($panel['namecustom_test'] ?? ''),
        'agent' => array_key_exists('agent', $_POST) ? trim($_POST['agent']) : ($panel['agent'] ?? 'all'),
        'limit_panel' => array_key_exists('limit_panel', $_POST) ? trim($_POST['limit_panel']) : ($panel['limit_panel'] ?? 'unlimted'),
        'MethodUsername' => $_POST['MethodUsername'] ?? $panel['MethodUsername'],
        'Methodextend' => $_POST['Methodextend'] ?? $panel['Methodextend'],
        'time_usertest' => array_key_exists('time_usertest', $_POST) ? trim($_POST['time_usertest']) : ($panel['time_usertest'] ?? '1'),
        'val_usertest' => array_key_exists('val_usertest', $_POST) ? trim($_POST['val_usertest']) : ($panel['val_usertest'] ?? '100'),
        'inbound_deactive' => array_key_exists('inbound_deactive', $_POST) ? trim($_POST['inbound_deactive']) : ($panel['inbound_deactive'] ?? '0'),
        // Extra volume/time/changeloc prices are unused in panel UI — keep stored values.
        'priceChangeloc' => $panel['priceChangeloc'] ?? '0',
        'status' => $toggle('status_active', 'status', 'active', 'disable'),
        'TestAccount' => $toggle('test_on', 'TestAccount', 'ONTestAccount', 'OFFTestAccount'),
        'status_extend' => $toggle('extend_on', 'status_extend', 'on_extend', 'off_extend'),
        'config' => $toggle('config_on', 'config', 'onconfig', 'offconfig'),
        'sublink' => $toggle('sublink_on', 'sublink', 'onsublink', 'offsublink'),
        'conecton' => $toggle('conecton_on', 'conecton', 'onconecton', 'offconecton'),
        'on_hold_test' => $toggle('on_hold_test', 'on_hold_test', '1', '0'),
        'changeloc' => $toggle('changeloc_on', 'changeloc', 'onchangeloc', 'offchangeloc'),
        'subvip' => $toggle('subvip_on', 'subvip', 'onsubvip', 'offsubvip'),
        'inboundstatus' => $toggle('inbound_disable_on', 'inboundstatus', 'oninbounddisable', 'offinbounddisable'),
        'version_panel' => $toggle('version_panel_on', 'version_panel', '1', '0'),
        'customvolume' => panel_merge_customvolume($panel, $tabSaving === 'pricing'),
        'hide_user' => $hideJson,
        'priceextravolume' => $panel['priceextravolume'] ?? panel_default_price_json(),
        'priceextratime' => $panel['priceextratime'] ?? panel_default_price_json(),
        // Custom-service prices/limits: edit f only; n/n2 pricing comes from agent page.
        'pricecustomvolume' => panel_merge_agent_json_field_f_only($panel, 'pricecustomvolume', 'pricecustomvolume', '4000'),
        'pricecustomtime' => $panel['pricecustomtime'] ?? panel_default_price_json(),
        'mainvolume' => panel_merge_agent_json_field_f_only($panel, 'mainvolume', 'mainvolume', '1'),
        'maxvolume' => panel_merge_agent_json_field_f_only($panel, 'maxvolume', 'maxvolume', '1000'),
        'maintime' => $panel['maintime'] ?? panel_default_volume_json(),
        'maxtime' => $panel['maxtime'] ?? panel_default_max_json(),
    ];

    if ($tabSaving === 'pricing') {
        $mergedMonths = panel_merge_custommonths($panel, true);
        if ($mergedMonths !== null) {
            $data['custommonths'] = $mergedMonths;
        }
    } elseif (array_key_exists('custommonths', $panel)) {
        $data['custommonths'] = $panel['custommonths'];
    }

    if (array_key_exists('customvolume_text', $_POST)) {
        $btnText = trim((string) $_POST['customvolume_text']);
        $data['customvolume_text'] = $btnText !== '' ? $btnText : null;
    } else {
        $data['customvolume_text'] = $panel['customvolume_text'] ?? null;
    }

    if (array_key_exists('pasarguard_group_ids', $_POST)) {
        $data['inbounds'] = $parsedGroups;
    }

    if ($tabSaving === 'account' && array_key_exists('namecustom', $_POST)) {
        $prefixNormal = trim((string) $data['namecustom']);
        $prefixTest = trim((string) $data['namecustom_test']);
        $methodNeedsPrefix = panel_method_uses_namecustom($data['MethodUsername'] ?? '');

        if ($prefixNormal === '' || strcasecmp($prefixNormal, 'none') === 0) {
            if ($methodNeedsPrefix) {
                flash('error', 'This username method requires a regular-product prefix (3–32 characters: letters, numbers, @, ., - or _).');
                header('Location: panel.php?id=' . $id . '&tab=account');
                exit;
            }
            $data['namecustom'] = 'none';
        } elseif (!panel_username_prefix_valid($prefixNormal)) {
            flash('error', 'The regular-product prefix must be 3–32 characters (letters, numbers, @, ., - or _).');
            header('Location: panel.php?id=' . $id . '&tab=account');
            exit;
        } else {
            $data['namecustom'] = $prefixNormal;
        }

        if ($prefixTest === '' || strcasecmp($prefixTest, 'none') === 0) {
            $data['namecustom_test'] = 'none';
        } elseif (!panel_username_prefix_valid($prefixTest)) {
            flash('error', 'The test-account prefix must be 3–32 characters (letters, numbers, @, ., - or _).');
            header('Location: panel.php?id=' . $id . '&tab=account');
            exit;
        } else {
            $data['namecustom_test'] = $prefixTest;
        }
    }

    // Optional bot message after panel/location is chosen (category keyboard).
    // Only include when the DB column exists so saves never 500 on older DBs.
    try {
        $descCol = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'description'");
        if ($descCol && $descCol->fetch(PDO::FETCH_ASSOC)) {
            if (array_key_exists('description', $_POST)) {
                $desc = trim((string) $_POST['description']);
                $data['description'] = $desc !== '' ? $desc : null;
            } else {
                $data['description'] = $panel['description'] ?? null;
            }
        }
    } catch (Throwable $e) {
        // column missing — skip
    }

    // Drop customvolume_text if column missing (older DBs).
    try {
        $btnCol = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'customvolume_text'");
        if (!($btnCol && $btnCol->fetch(PDO::FETCH_ASSOC))) {
            unset($data['customvolume_text']);
        }
    } catch (Throwable $e) {
        unset($data['customvolume_text']);
    }

    // Drop custommonths if column missing (older DBs before migration).
    try {
        $monthsCol = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'custommonths'");
        if (!($monthsCol && $monthsCol->fetch(PDO::FETCH_ASSOC))) {
            unset($data['custommonths']);
        }
    } catch (Throwable $e) {
        unset($data['custommonths']);
    }

    try {
        $ncTestCol = $pdo->query("SHOW COLUMNS FROM marzban_panel LIKE 'namecustom_test'");
        if (!($ncTestCol && $ncTestCol->fetch(PDO::FETCH_ASSOC))) {
            unset($data['namecustom_test']);
        }
    } catch (Throwable $e) {
        unset($data['namecustom_test']);
    }

    $clearLogin = $url !== ($panel['url_panel'] ?? '')
        || $data['username_panel'] !== ($panel['username_panel'] ?? '')
        || $data['password_panel'] !== ($panel['password_panel'] ?? '');

    $sets = [];
    $params = [];
    foreach ($data as $col => $val) {
        $sets[] = "`$col` = ?";
        $params[] = $val;
    }
    if ($clearLogin) {
        $sets[] = 'datelogin = NULL';
    }
    $params[] = $id;
    try {
        db_query($pdo, "UPDATE marzban_panel SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        flash('success', 'Panel settings saved.');
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }
    header('Location: panel.php?id=' . $id . '&tab=' . urlencode($_POST['tab'] ?? 'connection'));
    exit;
}

$panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE id = ?", [$id]);
$stats = panel_invoice_stats($pdo, $panel['name_panel']);
$priceCustomVol = panel_decode_agent_json($panel['pricecustomvolume'] ?? '', '4000');
$mainVolume = panel_decode_agent_json($panel['mainvolume'] ?? '', '1');
$maxVolume = panel_decode_agent_json($panel['maxvolume'] ?? '', '1000');
$customVolume = panel_decode_agent_json($panel['customvolume'] ?? '', '0');
$customMonthsRows = [];
if (!empty($panel['custommonths'])) {
    $decodedMonths = json_decode((string) $panel['custommonths'], true);
    if (is_array($decodedMonths)) {
        foreach ($decodedMonths as $row) {
            if (!is_array($row)) {
                continue;
            }
            $m = (int) ($row['months'] ?? 0);
            $x = (float) ($row['magnifier'] ?? 0);
            if ($m >= 1 && $x > 0) {
                $customMonthsRows[] = ['months' => $m, 'magnifier' => $x];
            }
        }
    }
}
if ($customMonthsRows === []) {
    $customMonthsRows = [
        ['months' => 1, 'magnifier' => 1],
        ['months' => 2, 'magnifier' => 1.8],
        ['months' => 3, 'magnifier' => 2.5],
    ];
}
$hideUsers = panel_parse_hide_users($panel['hide_user'] ?? null);

$tabs = [
    'connection' => 'Connection',
    'features' => 'Features',
    'account' => 'Account & test',
    'pricing' => 'Custom service',
    'advanced' => 'Advanced',
    'reports' => 'Reports',
];
if (!isset($tabs[$tab])) {
    $tab = 'connection';
}

if (isset($_GET['probe']) && $_GET['probe'] === '1') {
    csrf_check_get();
}
$connectionProbe = ($tab === 'connection') ? panel_probe_connection($panel) : null;
$reportLogs = [];
$reportSelectedKey = '';
$reportSelectedInfo = null;
$reportTail = null;
if ($tab === 'reports') {
    $reportLogs = panel_report_log_files();
    $reportSelectedKey = (string) ($_GET['log'] ?? '');
    if ($reportSelectedKey === '' || !isset($reportLogs[$reportSelectedKey])) {
        $keys = array_keys($reportLogs);
        $reportSelectedKey = $keys[0] ?? '';
    }
    if ($reportSelectedKey !== '' && isset($reportLogs[$reportSelectedKey])) {
        $reportSelectedInfo = $reportLogs[$reportSelectedKey];
        $reportTail = panel_read_log_tail($reportSelectedInfo['path']);
    }
}

$pageTitle = 'Manage panel: ' . ($panel['name_panel'] ?? '');
$activeNav = 'panels';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="margin-bottom:14px" class="fade-up">
  <a href="panels.php" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> Back to list</a>
</div>

<div class="stats fade-up" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
  <div class="stat">
    <div class="stat-label">Panel type</div>
    <div class="stat-num" style="font-size:1rem"><?= htmlspecialchars(panel_type_label($ptype)) ?></div>
  </div>
  <div class="stat ok">
    <div class="stat-label">Sales on this panel</div>
    <div class="stat-num"><?= number_format($stats['count']) ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Total sales</div>
    <div class="stat-num" style="font-size:.95rem"><?= number_format($stats['sum']) ?> <small>USD</small></div>
  </div>
  <div class="stat <?= ($panel['status'] ?? '') === 'active' ? 'ok' : 'warn' ?>">
    <div class="stat-label">Panel visibility</div>
    <div class="stat-num" style="font-size:.9rem"><?= panel_status_label($panel['status'] ?? '') ?></div>
  </div>
</div>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto" class="fade-up">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="panel.php?id=<?= $id ?>&tab=<?= $key ?>"
      style="padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;text-decoration:none;<?= $tab === $key ? 'background:var(--ac);color:#fff' : 'color:var(--mute)' ?>">
      <?= htmlspecialchars($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<form method="POST" class="fade-up">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
  <?php if ($tab !== 'connection'): ?>
    <input type="hidden" name="name_panel" value="<?= htmlspecialchars($panel['name_panel'] ?? '') ?>">
    <input type="hidden" name="url_panel" value="<?= htmlspecialchars(($panel['url_panel'] ?? '') !== 'null' ? ($panel['url_panel'] ?? '') : '') ?>">
    <input type="hidden" name="username_panel" value="<?= htmlspecialchars(($panel['username_panel'] ?? '') !== 'null' ? ($panel['username_panel'] ?? '') : '') ?>">
    <input type="hidden" name="password_panel" value="<?= htmlspecialchars(($panel['password_panel'] ?? '') !== 'null' ? ($panel['password_panel'] ?? '') : '') ?>">
    <input type="hidden" name="linksubx" value="<?= htmlspecialchars($panel['linksubx'] ?? '') ?>">
    <input type="hidden" name="secret_code" value="<?= htmlspecialchars($panel['secret_code'] ?? '') ?>">
    <input type="hidden" name="inboundid" value="<?= htmlspecialchars($panel['inboundid'] ?? '') ?>">
    <?php if ($tab !== 'account'): ?>
    <input type="hidden" name="namecustom" value="<?= htmlspecialchars($panel['namecustom'] ?? '') ?>">
    <input type="hidden" name="namecustom_test" value="<?= htmlspecialchars($panel['namecustom_test'] ?? '') ?>">
    <?php endif; ?>
    <input type="hidden" name="agent" value="<?= htmlspecialchars($panel['agent'] ?? 'all') ?>">
    <input type="hidden" name="limit_panel" value="<?= htmlspecialchars($panel['limit_panel'] ?? '') ?>">
    <input type="hidden" name="description" value="<?= htmlspecialchars($panel['description'] ?? '') ?>">
    <?php if ($isPasarguard): ?>
    <input type="hidden" name="pasarguard_group_ids" value="<?= htmlspecialchars($pasarguardGroupIds) ?>">
    <?php endif; ?>
    <?php if (($panel['status'] ?? '') === 'active'): ?><input type="hidden" name="status_active" value="1"><?php endif; ?>
    <?php if ($tab !== 'features' && ($panel['TestAccount'] ?? '') === 'ONTestAccount'): ?><input type="hidden" name="test_on" value="1"><?php endif; ?>
  <?php endif; ?>
  <?php if ($tab !== 'features'): ?>
    <?php
    $featHidden = [
        'extend_on' => ['status_extend', 'on_extend'],
        'config_on' => ['config', 'onconfig'],
        'sublink_on' => ['sublink', 'onsublink'],
        'conecton_on' => ['conecton', 'onconecton'],
        'on_hold_test' => ['on_hold_test', '1'],
        'changeloc_on' => ['changeloc', 'onchangeloc'],
        'subvip_on' => ['subvip', 'onsubvip'],
        'inbound_disable_on' => ['inboundstatus', 'oninbounddisable'],
        'version_panel_on' => ['version_panel', '1'],
    ];
    foreach ($featHidden as $postKey => [$field, $onVal]) {
        if (($panel[$field] ?? '') === $onVal) {
            echo '<input type="hidden" name="' . $postKey . '" value="1">';
        }
    }
    ?>
  <?php endif; ?>

  <?php if ($tab === 'connection'): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-head" style="flex-wrap:wrap;gap:10px">
        <div>
          <div class="card-title">Connection and panel availability</div>
          <div class="card-subtitle">Same as panel management in the Telegram bot</div>
        </div>
        <a href="panel.php?id=<?= $id ?>&tab=connection&probe=1&amp;_csrf=<?= urlencode(csrf_token()) ?>"
          class="btn btn-ghost btn-sm">Recheck connection</a>
      </div>
      <div class="card-body">
        <?php if ($connectionProbe): ?>
          <div class="kv-list" style="margin-bottom:16px">
            <div class="kv">
              <span class="kv-key">API connection status</span>
              <span class="tag <?= $connectionProbe['ok'] ? 'tag-ok' : 'tag-no' ?>">
                <?= $connectionProbe['ok'] ? 'Connected' : 'Disconnected / error' ?>
              </span>
            </div>
            <div class="kv">
              <span class="kv-key">Message</span>
              <span class="kv-val" style="font-size:.85rem"><?= htmlspecialchars($connectionProbe['title']) ?></span>
            </div>
            <?php foreach ($connectionProbe['lines'] as $line): ?>
              <div class="kv">
                <span class="kv-key">Details</span>
                <span class="kv-val cm" style="font-size:.8rem"><?= htmlspecialchars($line) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--sf2);border:1px solid var(--bd);border-radius:10px;cursor:pointer;margin-bottom:8px">
          <input type="checkbox" name="status_active" value="1" <?= ($panel['status'] ?? '') === 'active' ? 'checked' : '' ?>
            style="width:20px;height:20px;accent-color:var(--ac)">
          <span>
            <strong style="display:block;font-size:.9rem">Show this panel in the bot</strong>
            <span style="font-size:.78rem;color:var(--mute)">If off, this location is hidden from the shop and checkout (same as Show panel in the bot)</span>
          </span>
        </label>
        <?php if (in_array('test', $features, true)): ?>
        <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--sf2);border:1px solid var(--bd);border-radius:10px;cursor:pointer">
          <input type="checkbox" name="test_on" value="1" <?= ($panel['TestAccount'] ?? '') === 'ONTestAccount' ? 'checked' : '' ?>
            style="width:20px;height:20px;accent-color:var(--ac)">
          <span>
            <strong style="display:block;font-size:.9rem">Show test account</strong>
            <span style="font-size:.78rem;color:var(--mute)">Allow test services on this panel</span>
          </span>
        </label>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-title">Connection settings</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field">
            <label>Panel code</label>
            <input type="text" class="input" value="<?= htmlspecialchars($panel['code_panel'] ?? '') ?>" readonly>
          </div>
          <div class="field">
            <label>Type (fixed)</label>
            <input type="text" class="input" value="<?= htmlspecialchars(panel_type_label($ptype)) ?>" readonly>
          </div>
          <div class="field full">
            <label>Panel name *</label>
            <input type="text" name="name_panel" class="input" value="<?= htmlspecialchars($panel['name_panel'] ?? '') ?>" required>
          </div>
          <div class="field full">
            <label>Bot description (optional)</label>
            <textarea name="description" class="input" rows="4" placeholder="If set, this is shown after the panel is chosen instead of “Select your category”"><?= htmlspecialchars($panel['description'] ?? '') ?></textarea>
          </div>
          <div class="field full">
            <label>Panel URL</label>
            <input type="url" name="url_panel" class="input" value="<?= htmlspecialchars(($panel['url_panel'] ?? '') !== 'null' ? ($panel['url_panel'] ?? '') : '') ?>">
          </div>
          <?php if (!in_array($ptype, ['s_ui', 'WGDashboard'], true)): ?>
          <div class="field">
            <label>Panel username</label>
            <input type="text" name="username_panel" class="input" value="<?= htmlspecialchars(($panel['username_panel'] ?? '') !== 'null' ? ($panel['username_panel'] ?? '') : '') ?>" autocomplete="off">
          </div>
          <?php endif; ?>
          <div class="field">
            <label><?= in_array($ptype, ['s_ui', 'WGDashboard'], true) ? 'API token' : 'Password' ?></label>
            <input type="text" name="password_panel" class="input" value="<?= htmlspecialchars(($panel['password_panel'] ?? '') !== 'null' ? ($panel['password_panel'] ?? '') : '') ?>" autocomplete="off">
          </div>
          <div class="field full">
            <label>Subscription link domain</label>
            <input type="url" name="linksubx" class="input" value="<?= htmlspecialchars($panel['linksubx'] ?? '') ?>">
          </div>
          <?php if ($ptype === 'hiddify'): ?>
          <div class="field full">
            <label>Admin UUID (Hiddify)</label>
            <input type="text" name="secret_code" class="input" value="<?= htmlspecialchars($panel['secret_code'] ?? '') ?>">
          </div>
          <?php endif; ?>
          <?php if (in_array($ptype, ['x-ui_single', 'alireza_single', 'WGDashboard', 's_ui'], true)): ?>
          <div class="field">
            <label>Inbound ID</label>
            <input type="text" name="inboundid" class="input" value="<?= htmlspecialchars($panel['inboundid'] ?? '') ?>">
          </div>
          <?php endif; ?>
          <?php if ($ptype === 'ibsng'): ?>
          <div class="field full">
            <label>Default group name (IBSng)</label>
            <input type="text" name="namecustom" class="input" value="<?= htmlspecialchars($panel['namecustom'] ?? '') ?>">
          </div>
          <?php endif; ?>
          <?php if ($isPasarguard): ?>
          <div class="field full">
            <label>PasarGuard group ID (group_ids)</label>
            <input type="text" name="pasarguard_group_ids" class="input"
              value="<?= htmlspecialchars($pasarguardGroupIds) ?>"
              placeholder="Example: 1 or 1, 2"
              pattern="\d+(\s*,\s*\d+)*"
              inputmode="numeric"
              autocomplete="off">
            <small class="cf" style="display:block;margin-top:6px">
              Copy the group ID from PasarGuard → <strong>Groups</strong>. Each group has specific inbounds.
              To enable PasarGuard mode, open the <strong>Features</strong> tab and turn on PasarGuard panel.
            </small>
          </div>
          <?php elseif ($ptype === 'marzban'): ?>
          <div class="field full">
            <div class="notice notice-warn" style="margin:0">
              For PasarGuard, enable PasarGuard panel on the <strong>Features</strong> tab to show the group ID field.
            </div>
          </div>
          <?php endif; ?>
          <div class="field">
            <label>User group</label>
            <select name="agent" class="select">
              <option value="all" <?= ($panel['agent'] ?? '') === 'all' ? 'selected' : '' ?>>All (all)</option>
              <option value="f" <?= ($panel['agent'] ?? '') === 'f' ? 'selected' : '' ?>>Regular user (f)</option>
              <option value="n" <?= ($panel['agent'] ?? '') === 'n' ? 'selected' : '' ?>>Agent (n)</option>
              <option value="n2" <?= ($panel['agent'] ?? '') === 'n2' ? 'selected' : '' ?>>Advanced agent (n2)</option>
            </select>
          </div>
          <div class="field">
            <label>Account creation limit</label>
            <input type="text" name="limit_panel" class="input" value="<?= htmlspecialchars($panel['limit_panel'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'features'): ?>
    <div class="card">
      <div class="card-head"><div class="card-title">Panel feature status</div></div>
      <div class="card-body">
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
          <?php
          $toggles = [
              'test_on' => ['label' => 'Test account', 'on' => ($panel['TestAccount'] ?? '') === 'ONTestAccount', 'feat' => 'test'],
              'extend_on' => ['label' => 'Renewal status', 'on' => ($panel['status_extend'] ?? '') === 'on_extend', 'feat' => 'extend'],
              'config_on' => ['label' => 'Send config', 'on' => ($panel['config'] ?? '') === 'onconfig', 'feat' => 'config'],
              'sublink_on' => ['label' => 'Send subscription link', 'on' => ($panel['sublink'] ?? '') === 'onsublink', 'feat' => 'sublink'],
              'conecton_on' => ['label' => 'First connection', 'on' => ($panel['conecton'] ?? '') === 'onconecton', 'feat' => 'conecton'],
              'on_hold_test' => ['label' => 'First connection — test account', 'on' => ($panel['on_hold_test'] ?? '0') === '1', 'feat' => 'on_hold_test'],
              'changeloc_on' => ['label' => 'Change location', 'on' => ($panel['changeloc'] ?? '') === 'onchangeloc', 'feat' => 'changeloc'],
              'subvip_on' => ['label' => 'Dedicated sub link', 'on' => ($panel['subvip'] ?? '') === 'onsubvip', 'feat' => 'subvip'],
              'inbound_disable_on' => ['label' => 'Disabled-account inbound', 'on' => ($panel['inboundstatus'] ?? '') === 'oninbounddisable', 'feat' => 'inbound_disable'],
              'version_panel_on' => ['label' => 'PasarGuard panel', 'on' => ($panel['version_panel'] ?? '0') === '1', 'feat' => 'version_panel'],
          ];
          foreach ($toggles as $name => $t):
              if (!in_array($t['feat'], $features, true)) continue;
          ?>
            <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--sf2);border:1px solid var(--bd);border-radius:8px;cursor:pointer">
              <input type="checkbox" name="<?= $name ?>" value="1" <?= $t['on'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--ac)">
              <span style="font-size:.85rem"><?= htmlspecialchars($t['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'account'): ?>
    <?php
      $namecustomDisplay = (string) ($panel['namecustom'] ?? '');
      if (strcasecmp($namecustomDisplay, 'none') === 0) {
          $namecustomDisplay = '';
      }
      $namecustomTestDisplay = (string) ($panel['namecustom_test'] ?? '');
      if (strcasecmp($namecustomTestDisplay, 'none') === 0) {
          $namecustomTestDisplay = '';
      }
    ?>
    <div class="card">
      <div class="card-head"><div class="card-title">Username creation and renewal method</div></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field full">
            <label>Username method</label>
            <select name="MethodUsername" id="method-username" class="select">
              <?php foreach (METHOD_USERNAME_OPTIONS as $opt => $label): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= ($panel['MethodUsername'] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" id="namecustom-normal-wrap">
            <label>Regular product prefix</label>
            <input type="text" name="namecustom" id="namecustom-normal" class="input" maxlength="32" pattern="[@A-Za-z0-9._-]{3,32}"
              value="<?= htmlspecialchars($namecustomDisplay) ?>"
              placeholder="e.g. @pichanet"
              autocomplete="off">
            <small class="cf">For methods such as Custom text + random number. Example: @pichanet → @pichanet_a1b2c3</small>
          </div>
          <div class="field" id="namecustom-test-wrap">
            <label>Test account prefix</label>
            <input type="text" name="namecustom_test" id="namecustom-test" class="input" maxlength="32"
              value="<?= htmlspecialchars($namecustomTestDisplay) ?>"
              placeholder="e.g. test"
              autocomplete="off">
            <small class="cf">Separate from regular products. If empty, the regular prefix is used.</small>
          </div>
          <div class="field full" id="namecustom-hint" style="display:none">
            <div class="notice" style="margin:0;font-size:.85rem">This method does not use a prefix. Prefixes apply only to Custom text and Username + sequential number methods.</div>
          </div>
          <div class="field full">
            <label>Service renewal method</label>
            <select name="Methodextend" class="select">
              <?php foreach (METHOD_EXTEND_OPTIONS as $opt => $label): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= ($panel['Methodextend'] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Test service duration (hours)</label>
            <input type="number" name="time_usertest" class="input" min="0" value="<?= htmlspecialchars($panel['time_usertest'] ?? '1') ?>">
          </div>
          <div class="field">
            <label>Test account volume (MB)</label>
            <input type="number" name="val_usertest" class="input" min="0" value="<?= htmlspecialchars($panel['val_usertest'] ?? '100') ?>">
          </div>
          <div class="field full">
            <label>Disabled-account inbound</label>
            <input type="text" name="inbound_deactive" class="input" value="<?= htmlspecialchars($panel['inbound_deactive'] ?? '0') ?>">
          </div>
        </div>
      </div>
    </div>
    <script>
    (function () {
      var prefixMethods = <?= json_encode(array_values(METHOD_USERNAME_PREFIX_OPTIONS), JSON_UNESCAPED_UNICODE) ?>;
      var select = document.getElementById('method-username');
      var hint = document.getElementById('namecustom-hint');
      var normalInput = document.getElementById('namecustom-normal');
      var testInput = document.getElementById('namecustom-test');
      if (!select) return;
      function sync() {
        var uses = prefixMethods.indexOf(select.value) !== -1;
        if (hint) hint.style.display = uses ? 'none' : 'block';
        if (normalInput) {
          normalInput.required = uses;
          if (uses) normalInput.setAttribute('pattern', '[@A-Za-z0-9._-]{3,32}');
          else normalInput.removeAttribute('pattern');
        }
        if (testInput) {
          if (testInput.value) testInput.setAttribute('pattern', '[@A-Za-z0-9._-]{3,32}');
          else testInput.removeAttribute('pattern');
        }
      }
      select.addEventListener('change', sync);
      if (testInput) testInput.addEventListener('input', sync);
      sync();
    })();
    </script>
  <?php endif; ?>

  <?php if ($tab === 'pricing'): ?>
    <div class="card">
      <div class="card-head">
        <div class="card-title">Custom service</div>
        <div class="card-subtitle">Enable for regular users (f) and the button text next to categories. n/n2 pricing is set on the agent page.</div>
      </div>
      <div class="card-body">
        <div class="field" style="margin-bottom:14px">
          <label>Enabled for user groups</label>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">
            <?php foreach (['f' => 'Regular user (f)', 'n' => 'Agent (n)', 'n2' => 'Advanced agent (n2)'] as $k => $label): ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:.85rem">
                <input type="checkbox" name="custom_<?= $k ?>" value="1" <?= ($customVolume[$k] ?? '0') === '1' ? 'checked' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field" style="margin-bottom:14px">
          <label>Bot button text</label>
          <input type="text" name="customvolume_text" class="input" maxlength="200"
            value="<?= htmlspecialchars($panel['customvolume_text'] ?? '') ?>"
            placeholder="Custom service">
          <small class="cf">If empty, the default “Custom service” text is used.</small>
        </div>
        <div class="notice" style="margin-bottom:14px;font-size:.85rem;color:var(--mute)">
          Per-GB price is for regular users (f) only. For n and n2 use the <a href="agents.php">agent page</a>.
          Final price = volume (GB) × price per GB × month multiplier. Each month = 30 days.
        </div>
        <div class="form-grid">
          <div class="field">
            <label>Price per GB (USD)</label>
            <input type="number" name="pricecustomvolume_f" class="input" min="0" value="<?= htmlspecialchars($priceCustomVol['f']) ?>">
          </div>
          <div class="field">
            <label>Minimum volume (GB)</label>
            <input type="number" name="mainvolume_f" class="input" min="0" value="<?= htmlspecialchars($mainVolume['f']) ?>">
          </div>
          <div class="field">
            <label>Maximum volume (GB)</label>
            <input type="number" name="maxvolume_f" class="input" min="0" value="<?= htmlspecialchars($maxVolume['f']) ?>">
          </div>
        </div>
        <div style="margin-top:18px">
          <label style="display:block;margin-bottom:8px;font-weight:600">Month options</label>
          <small class="cf" style="display:block;margin-bottom:10px">Each row is a duration the bot can offer. The multiplier is applied to the base volume price (e.g. 10GB × 4000 × 1.8).</small>
          <div id="custommonths-rows">
            <?php foreach ($customMonthsRows as $idx => $cm): ?>
              <div class="form-grid custommonths-row" style="margin-bottom:8px;align-items:end">
                <div class="field">
                  <label>Months</label>
                  <input type="number" name="custommonths_months[]" class="input" min="1" step="1" required
                    value="<?= (int) $cm['months'] ?>">
                </div>
                <div class="field">
                  <label>Price multiplier (magnifier)</label>
                  <input type="number" name="custommonths_magnifier[]" class="input" min="0.01" step="0.01" required
                    value="<?= htmlspecialchars((string) $cm['magnifier']) ?>">
                </div>
                <div class="field" style="display:flex;align-items:end">
                  <button type="button" class="btn btn-ghost custommonths-remove" <?= count($customMonthsRows) <= 1 ? 'disabled' : '' ?>>Delete</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-ghost" id="custommonths-add" style="margin-top:6px">+ Add month</button>
        </div>
      </div>
    </div>
    <script>
    (function () {
      var wrap = document.getElementById('custommonths-rows');
      var addBtn = document.getElementById('custommonths-add');
      if (!wrap || !addBtn) return;
      function refreshRemove() {
        var rows = wrap.querySelectorAll('.custommonths-row');
        rows.forEach(function (row) {
          var btn = row.querySelector('.custommonths-remove');
          if (btn) btn.disabled = rows.length <= 1;
        });
      }
      addBtn.addEventListener('click', function () {
        var first = wrap.querySelector('.custommonths-row');
        if (!first) return;
        var clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
        wrap.appendChild(clone);
        refreshRemove();
      });
      wrap.addEventListener('click', function (e) {
        var btn = e.target.closest('.custommonths-remove');
        if (!btn || btn.disabled) return;
        var row = btn.closest('.custommonths-row');
        if (row && wrap.querySelectorAll('.custommonths-row').length > 1) {
          row.remove();
          refreshRemove();
        }
      });
    })();
    </script>
  <?php endif; ?>

  <?php if ($tab === 'advanced'): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-head"><div class="card-title">Users hidden from this panel</div></div>
      <div class="card-body">
        <div class="field full">
          <label>Numeric user IDs (comma or newline separated)</label>
          <textarea name="hide_users" class="input" rows="4" placeholder="123456789, 987654321"><?= htmlspecialchars(implode(', ', $hideUsers)) ?></textarea>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><div class="card-title">Technical data (read-only)</div></div>
      <div class="card-body">
        <div class="field full">
          <label><?= $isPasarguard ? 'group_ids (inbounds)' : 'inbounds' ?></label>
          <textarea class="input" rows="3" readonly><?= htmlspecialchars($panel['inbounds'] ?? '') ?></textarea>
          <small class="cf">
            <?php if ($isPasarguard): ?>
              Edit from the <strong>Connection</strong> tab → PasarGuard group ID, or from the Telegram bot → protocol and inbound settings
            <?php else: ?>
              From the bot: protocol and inbound settings — by sending a config username
            <?php endif; ?>
          </small>
        </div>
        <div class="field full">
          <label>proxies</label>
          <textarea class="input" rows="3" readonly><?= htmlspecialchars($panel['proxies'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'reports'): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-head" style="flex-wrap:wrap;gap:10px">
        <div>
          <div class="card-title">Server reports</div>
          <div class="card-subtitle">Latest log lines for bot and panel troubleshooting</div>
        </div>
        <a href="panel.php?id=<?= $id ?>&tab=reports&log=<?= urlencode($reportSelectedKey) ?>"
          class="btn btn-ghost btn-sm">Refresh</a>
      </div>
      <div class="card-body">
        <?php if (empty($reportLogs)): ?>
          <div class="notice notice-warn">No log files found to display (error_log / polling.log).</div>
        <?php else: ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <?php foreach ($reportLogs as $key => $info): ?>
              <a href="panel.php?id=<?= $id ?>&tab=reports&log=<?= urlencode($key) ?>"
                class="btn <?= $reportSelectedKey === $key ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                <?= htmlspecialchars($info['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
          <?php if ($reportSelectedInfo && is_array($reportTail)): ?>
            <div class="kv-list" style="margin-bottom:12px">
              <div class="kv">
                <span class="kv-key">File</span>
                <span class="kv-val cm" style="font-size:.75rem"><?= htmlspecialchars($reportSelectedInfo['path']) ?></span>
              </div>
              <div class="kv">
                <span class="kv-key">Volume</span>
                <span class="kv-val"><?= number_format((int) ($reportTail['size'] ?? 0)) ?> bytes</span>
              </div>
              <div class="kv">
                <span class="kv-key">Last updated</span>
                <span class="kv-val"><?= htmlspecialchars(($reportTail['mtime'] ?? null) ? date('Y/m/d H:i:s', (int) $reportTail['mtime']) : '—') ?></span>
              </div>
            </div>
            <div style="background:var(--sf2);border:1px solid var(--bd);border-radius:10px;padding:12px;max-height:420px;overflow:auto">
              <pre style="margin:0;white-space:pre-wrap;word-break:break-word;direction:ltr;text-align:left;font-size:.78rem;line-height:1.6"><?= htmlspecialchars(!empty($reportTail['lines']) ? implode("\n", $reportTail['lines']) : 'Log is empty.') ?></pre>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Save changes</button>
    <a href="panels.php" class="btn btn-ghost">Cancel</a>
  </div>
</form>

<div class="card fade-up" style="margin-top:24px;border-color:var(--no)">
  <div class="card-head">
    <div class="card-title" style="color:var(--no)">Delete panel</div>
  </div>
  <div class="card-body">
    <form method="POST" onsubmit="return confirm('This panel and all of its settings will be deleted. Continue?')">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $id ?>">
      <p style="font-size:.85rem;color:var(--mute);margin-bottom:12px">Type <strong>CONFIRM</strong> in the box below to confirm (same as the bot).</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="flex:1;min-width:200px;margin:0">
          <input type="text" name="confirm" class="input" placeholder="CONFIRM" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-no"><?= icon('trash', 14) ?> Delete panel</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
