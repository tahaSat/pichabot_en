<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/payments_lib.php';
require_once __DIR__ . '/inc/panels_lib.php';
require_administrator();
$pdo = panel_ensure_pdo();

$gid = $_GET['g'] ?? '';
$gw = PAYMENT_GATEWAYS[$gid] ?? null;
if (!$gw) {
    flash('error', 'Invalid gateway.');
    header('Location: payment_methods.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        foreach ($gw['fields'] as $field) {
            $key = $field['key'];
            if ($field['type'] === 'toggle') {
                $on = !empty($_POST['toggle_' . $key]);
                pay_set($pdo, $key, $on ? $field['on'] : $field['off']);
            } elseif ($field['type'] === 'password') {
                $secret = trim((string) ($_POST[$key] ?? ''));
                if ($secret !== '') {
                    pay_set($pdo, $key, $secret);
                }
            } elseif (isset($_POST[$key])) {
                pay_set($pdo, $key, trim((string) $_POST[$key]));
            }
        }
        if (!empty($gw['textbot_key']) && isset($_POST['gateway_display_name'])) {
            pay_textbot_set($pdo, $gw['textbot_key'], trim($_POST['gateway_display_name']));
        }
        if (!empty($gw['help_key'])) {
            pay_help_set($pdo, $gw['help_key'], [
                'enabled' => !empty($_POST['help_enabled']),
                'type' => $_POST['help_type'] ?? 'text',
                'text' => $_POST['help_text'] ?? '',
                'photoid' => $_POST['help_photoid'] ?? '',
                'videoid' => $_POST['help_videoid'] ?? '',
            ]);
        }
        flash('success', 'Gateway settings saved.');
        header('Location: payment_gateway.php?g=' . urlencode($gid));
        exit;
    }

    if ($action === 'refresh_cryptomus_services' && $gid === 'cryptomus') {
        $result = cryptomus_get_cached_services(3600, true);
        flash(
            !empty($result['ok']) ? 'success' : 'error',
            !empty($result['ok'])
                ? 'Cryptomus service list updated.'
                : ('Failed to refresh Cryptomus services: ' . ($result['error'] ?? 'Unknown error'))
        );
        header('Location: payment_gateway.php?g=cryptomus');
        exit;
    }

    if ($action === 'add_card' && !empty($gw['has_cards'])) {
        $r = pay_add_card($pdo, $_POST['cardnumber'] ?? '', $_POST['namecard'] ?? '');
        flash($r['ok'] ? 'success' : 'error', $r['msg']);
        header('Location: payment_gateway.php?g=cart');
        exit;
    }

    if ($action === 'delete_card' && !empty($gw['has_cards'])) {
        pay_delete_card($pdo, $_POST['cardnumber'] ?? '');
        flash('success', 'Card number deleted.');
        header('Location: payment_gateway.php?g=cart');
        exit;
    }
}

$enabled = pay_gateway_enabled($gw);
$displayName = pay_textbot_get($pdo, $gw['textbot_key'] ?? '', $gw['label']);
$cards = !empty($gw['has_cards']) ? pay_list_cards($pdo) : [];
$help = !empty($gw['help_key']) ? pay_help_get($pdo, $gw['help_key']) : null;
$cryptomusServices = null;
$cryptomusServiceRows = [];
if ($gid === 'cryptomus') {
    $cachedData = json_decode(pay_get($pdo, 'cryptomus_services_cache', '[]'), true);
    $cachedAt = (int) pay_get($pdo, 'cryptomus_services_cached_at', '0');
    $cryptomusServices = [
        'ok' => is_array($cachedData) && !empty($cachedData),
        'data' => is_array($cachedData) ? $cachedData : [],
        'cached' => true,
        'cached_at' => $cachedAt,
        'error' => is_array($cachedData) && !empty($cachedData) ? null : 'No cache has been saved yet.',
    ];
    if ($cryptomusServices['ok']) {
        $cryptomusServiceRows = panel_cryptomus_service_rows($cachedData);
    }
}

$pageTitle = 'Settings: ' . $gw['label'];
$pageLede = ($enabled ? 'Active' : 'Inactive') . ' — Same gateway settings as in the Telegram bot.';
$activeNav = 'payment_methods';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="margin-bottom:14px" class="fade-up">
  <a href="payment_methods.php" class="btn btn-ghost btn-sm">← All gateways</a>
</div>

<div class="two-col">
  <div class="card fade-up">
    <div class="card-head">
      <div>
        <div class="card-title"><?= htmlspecialchars($gw['label']) ?></div>
        <div class="card-subtitle">
          <span class="tag <?= $enabled ? 'tag-ok' : 'tag-plain' ?>"><?= $enabled ? 'Active' : 'Inactive' ?></span>
        </div>
      </div>
      <form method="POST" action="payment_methods.php">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="gateway" value="<?= htmlspecialchars($gid) ?>">
        <button type="submit" class="btn btn-ghost btn-sm"><?= $enabled ? 'Turn off' : 'Activate' ?></button>
      </form>
    </div>
    <form method="POST" class="card-body">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <div style="display:flex;flex-direction:column;gap:14px">
        <?php if (!empty($gw['textbot_key'])): ?>
          <div class="field">
            <label>Bot button name</label>
            <input type="text" name="gateway_display_name" class="input" value="<?= htmlspecialchars($displayName) ?>">
          </div>
        <?php endif; ?>

        <?php foreach ($gw['fields'] as $field):
          $val = pay_get($pdo, $field['key'], $field['off'] ?? '');
          if ($field['type'] === 'toggle'):
            $isOn = ($val === ($field['on'] ?? ''));
            ?>
            <div class="field" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
              <label style="margin:0"><?= htmlspecialchars($field['label']) ?></label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="toggle_<?= htmlspecialchars($field['key']) ?>" value="1" <?= $isOn ? 'checked' : '' ?>>
                <span class="tag <?= $isOn ? 'tag-ok' : 'tag-plain' ?>"><?= $isOn ? 'On' : 'Off' ?></span>
              </label>
            </div>
          <?php elseif ($field['type'] === 'password'): ?>
            <div class="field">
              <label><?= htmlspecialchars($field['label']) ?></label>
              <input type="password" name="<?= htmlspecialchars($field['key']) ?>" class="input"
                value="" autocomplete="new-password"
                placeholder="<?= ($val !== '' && $val !== '0') ? 'Saved — leave empty to keep the current key' : 'Enter the API key' ?>">
              <div style="font-size:.74rem;color:var(--mute);margin-top:6px">
                The current key is never shown; leaving the field empty does not clear it.
              </div>
            </div>
          <?php else: ?>
            <div class="field">
              <label><?= htmlspecialchars($field['label']) ?></label>
              <input type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>" name="<?= htmlspecialchars($field['key']) ?>"
                class="input" value="<?= htmlspecialchars($val) ?>" <?= $field['type'] === 'number' ? 'min="0"' : '' ?>>
            </div>
          <?php endif;
        endforeach; ?>

        <?php if ($help !== null): ?>
          <div style="margin-top:8px;padding-top:16px;border-top:1px solid var(--bd)">
            <div style="font-weight:600;margin-bottom:4px">Pre-payment help</div>
            <div style="font-size:.78rem;color:var(--mute);margin-bottom:14px">
              This message is sent before payment details when the user selects this gateway.
            </div>
            <div class="field" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
              <label style="margin:0">Help enabled</label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="help_enabled" value="1" <?= $help['enabled'] ? 'checked' : '' ?> id="help_enabled">
                <span class="tag <?= $help['enabled'] ? 'tag-ok' : 'tag-plain' ?>" id="help_enabled_tag"><?= $help['enabled'] ? 'Active' : 'Inactive' ?></span>
              </label>
            </div>
            <div class="field">
              <label>Content type</label>
              <select name="help_type" class="input" id="help_type">
                <option value="text" <?= $help['type'] === 'text' ? 'selected' : '' ?>>Text</option>
                <option value="photo" <?= $help['type'] === 'photo' ? 'selected' : '' ?>>Photo</option>
                <option value="video" <?= $help['type'] === 'video' ? 'selected' : '' ?>>Video</option>
              </select>
            </div>
            <div class="field">
              <label>Text / caption</label>
              <textarea name="help_text" class="input" rows="4" placeholder="Help text or photo/video caption"><?= htmlspecialchars($help['text']) ?></textarea>
            </div>
            <div class="field" id="help_photoid_wrap" style="<?= $help['type'] === 'photo' ? '' : 'display:none' ?>">
              <label>Telegram photo file_id</label>
              <input type="text" name="help_photoid" class="input" value="<?= htmlspecialchars($help['photoid']) ?>" placeholder="AgACAgQAAxkB...">
            </div>
            <div class="field" id="help_videoid_wrap" style="<?= $help['type'] === 'video' ? '' : 'display:none' ?>">
              <label>Telegram video file_id</label>
              <input type="text" name="help_videoid" class="input" value="<?= htmlspecialchars($help['videoid']) ?>" placeholder="BAACAgQAAxkB...">
            </div>
          </div>
        <?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px"><?= icon('check', 14) ?> Save settings</button>
    </form>
  </div>

  <?php if (!empty($gw['has_cards'])): ?>
    <div class="card fade-up d1">
      <div class="card-head">
        <div class="card-title">Card numbers</div>
        <div class="card-subtitle">Multiple cards — shown to the user at random</div>
      </div>
      <form method="POST" class="card-body" style="border-bottom:1px solid var(--bd);padding-bottom:16px;margin-bottom:16px">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add_card">
        <div class="field">
          <label>Card number</label>
          <input type="text" name="cardnumber" class="input" inputmode="numeric" required placeholder="6037...">
        </div>
        <div class="field">
          <label>Cardholder name</label>
          <input type="text" name="namecard" class="input" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> Add</button>
      </form>
      <?php if (empty($cards)): ?>
        <div class="empty" style="padding:24px"><p>No cards saved</p></div>
      <?php else: ?>
        <div class="kv-list">
          <?php foreach ($cards as $c): ?>
            <div class="kv" style="align-items:center">
              <div>
                <div class="kv-val cm" style="font-size:.82rem"><?= htmlspecialchars($c['cardnumber']) ?></div>
                <div style="font-size:.75rem;color:var(--mute)"><?= htmlspecialchars($c['namecard']) ?></div>
              </div>
              <form method="POST" onsubmit="return confirm('Delete this card?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_card">
                <input type="hidden" name="cardnumber" value="<?= htmlspecialchars($c['cardnumber']) ?>">
                <button type="submit" class="btn btn-no btn-sm">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($gid === 'cryptomus'): ?>
    <div class="card fade-up d1">
      <div class="card-head">
        <div>
          <div class="card-title">Cryptomus setup and troubleshooting</div>
          <div class="card-subtitle">Merchant-specific data stored in cache</div>
        </div>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="refresh_cryptomus_services">
          <button type="submit" class="btn btn-ghost btn-sm">Refresh services</button>
        </form>
      </div>
      <div class="card-body">
        <div class="field">
          <label>Callback URL</label>
          <input type="text" class="input" dir="ltr" readonly
            value="<?= htmlspecialchars('https://' . $domainhosts . '/payment/cryptomus.php') ?>">
        </div>
        <div style="padding:12px;border:1px solid var(--bd);border-radius:10px;font-size:.8rem;line-height:1.8;margin:14px 0">
          In the Cryptomus dashboard, review settlement and Auto-convert. Confirm the current status and fees in the merchant dashboard; this panel does not change them automatically.
        </div>
        <?php if ($cryptomusServices && !empty($cryptomusServices['ok'])): ?>
          <div style="font-size:.76rem;color:var(--mute);margin-bottom:10px">
            <?= !empty($cryptomusServices['cached']) ? 'Showing cached data' : 'Fetched from API' ?>
            <?php if (!empty($cryptomusServices['cached_at'])): ?>
              — <?= htmlspecialchars(panel_payment_time_to_jalali((string) $cryptomusServices['cached_at'])) ?>
            <?php endif; ?>
          </div>
          <?php if ($cryptomusServiceRows): ?>
            <div class="tbl-wrap">
              <table class="tbl-lg">
                <thead><tr><th>Currency</th><th>Network</th><th>Active</th><th>Min</th><th>Max</th><th>Commission</th></tr></thead>
                <tbody>
                  <?php foreach ($cryptomusServiceRows as $service): ?>
                    <tr>
                      <td><?= htmlspecialchars($service['currency'] ?: '—') ?></td>
                      <td><?= htmlspecialchars($service['network'] ?: '—') ?></td>
                      <td><?= htmlspecialchars($service['available'] ?: '—') ?></td>
                      <td><?= htmlspecialchars($service['min'] ?: '—') ?></td>
                      <td><?= htmlspecialchars($service['max'] ?: '—') ?></td>
                      <td><?= htmlspecialchars($service['commission'] ?: '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty" style="padding:20px"><p>No displayable services in the cached response.</p></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="empty" style="padding:20px">
            <p>Service information is not available.</p>
            <?php if (!empty($cryptomusServices['error'])): ?>
              <small><?= htmlspecialchars((string) $cryptomusServices['error']) ?></small>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($help !== null): ?>
<script>
(function () {
  var typeSel = document.getElementById('help_type');
  var photoWrap = document.getElementById('help_photoid_wrap');
  var videoWrap = document.getElementById('help_videoid_wrap');
  var enabled = document.getElementById('help_enabled');
  var tag = document.getElementById('help_enabled_tag');
  function syncType() {
    var t = typeSel ? typeSel.value : 'text';
    if (photoWrap) photoWrap.style.display = t === 'photo' ? '' : 'none';
    if (videoWrap) videoWrap.style.display = t === 'video' ? '' : 'none';
  }
  function syncEnabled() {
    if (!enabled || !tag) return;
    var on = enabled.checked;
    tag.textContent = on ? 'Active' : 'Inactive';
    tag.className = 'tag ' + (on ? 'tag-ok' : 'tag-plain');
  }
  if (typeSel) typeSel.addEventListener('change', syncType);
  if (enabled) enabled.addEventListener('change', syncEnabled);
  syncType();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
