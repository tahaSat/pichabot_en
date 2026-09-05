<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/payments_lib.php';
require_auth();
$pdo = panel_ensure_pdo();

$bot_button_labels = get_main_keyboard_button_fallback_labels();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_bot_button') {
    csrf_check_post();
    $button_id = $_POST['button_id'] ?? '';
    $setting_row = select('setting', '*', null, null, 'select');
    $textbot_rows = db_fetchAll($pdo, "SELECT id_text, text FROM textbot WHERE id_text IN ('" . implode("','", array_keys($bot_button_labels)) . "')");
    $toggle_datatextbot = $bot_button_labels;
    foreach ($textbot_rows as $row) {
        if (!empty($row['text'])) {
            $toggle_datatextbot[$row['id_text']] = $row['text'];
        }
    }
    $new_keyboard = toggle_main_keyboard_button($setting_row['keyboardmain'], $button_id, $toggle_datatextbot);
    update('setting', 'keyboardmain', $new_keyboard, null, null);
    clearSelectCache('setting');
    flash('success', 'Button status updated.');
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_bot_buttons') {
    csrf_check_post();
    $default_keyboard = get_default_main_keyboard_json();
    update('setting', 'keyboardmain', $default_keyboard, null, null);
    reset_main_keyboard_button_styles();
    reset_main_keyboard_button_icons();
    clearSelectCache('setting');
    flash('success', 'Menu buttons restored to defaults.');
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bot_button_title') {
    csrf_check_post();
    $button_id = trim((string) ($_POST['button_id'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $emoji_input = trim((string) ($_POST['emoji'] ?? ''));
    $allowed_ids = get_main_keyboard_button_ids();
    $custom_emoji_id = normalize_main_keyboard_custom_emoji_id($emoji_input);

    $flash_error = null;
    $stored_title = $title;
    $stored_icon = '';

    if (!in_array($button_id, $allowed_ids, true)) {
        $flash_error = 'Invalid button.';
    } elseif ($title === '') {
        $flash_error = 'Button title cannot be empty.';
    } elseif ($emoji_input !== '' && $custom_emoji_id === null && mb_strlen($emoji_input) > 8) {
        $flash_error = 'Premium emoji ID must be numeric (for example 5368324170671202286).';
    } elseif ($custom_emoji_id !== null && $custom_emoji_id !== '') {
        $stored_title = $title;
        $stored_icon = $custom_emoji_id;
    } elseif ($emoji_input !== '') {
        $stored_title = trim($emoji_input . ' ' . $title);
        $stored_icon = '';
    }

    if ($flash_error === null) {
        if (str_contains($stored_title, "\n") || mb_strlen($stored_title) > 32) {
            $flash_error = 'Button title must be at most 32 characters and must not contain a newline.';
        } elseif (is_main_keyboard_internal_id($stored_title)) {
            $flash_error = 'This title is not allowed.';
        }
    }

    if ($flash_error !== null) {
        flash('error', $flash_error);
    } else {
        $exists = db_fetch($pdo, "SELECT id_text FROM textbot WHERE id_text = ?", [$button_id]);
        if ($exists) {
            db_query($pdo, "UPDATE textbot SET text = ? WHERE id_text = ?", [$stored_title, $button_id]);
        } else {
            db_query($pdo, "INSERT INTO textbot (id_text, text) VALUES (?, ?)", [$button_id, $stored_title]);
        }
        set_main_keyboard_button_icon($button_id, $stored_icon);
        clearSelectCache('textbot');
        flash('success', $stored_icon !== '' ? 'Button title and premium emoji saved.' : 'Button title saved.');
    }
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_bot_button') {
    csrf_check_post();
    $button_id = trim((string) ($_POST['button_id'] ?? ''));
    $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
    $setting_row = select('setting', '*', null, null, 'select');
    $textbot_rows = db_fetchAll($pdo, "SELECT id_text, text FROM textbot WHERE id_text IN ('" . implode("','", array_keys($bot_button_labels)) . "')");
    $move_datatextbot = $bot_button_labels;
    foreach ($textbot_rows as $row) {
        if (!empty($row['text'])) {
            $move_datatextbot[$row['id_text']] = $row['text'];
        }
    }
    $new_keyboard = move_main_keyboard_button($setting_row['keyboardmain'], $button_id, $direction, $move_datatextbot);
    update('setting', 'keyboardmain', $new_keyboard, null, null);
    clearSelectCache('setting');
    flash('success', 'Button order updated.');
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_bot_button_width') {
    csrf_check_post();
    $button_id = trim((string) ($_POST['button_id'] ?? ''));
    $width = ($_POST['width'] ?? '') === 'full' ? 'full' : 'half';
    $setting_row = select('setting', '*', null, null, 'select');
    $textbot_rows = db_fetchAll($pdo, "SELECT id_text, text FROM textbot WHERE id_text IN ('" . implode("','", array_keys($bot_button_labels)) . "')");
    $width_datatextbot = $bot_button_labels;
    foreach ($textbot_rows as $row) {
        if (!empty($row['text'])) {
            $width_datatextbot[$row['id_text']] = $row['text'];
        }
    }
    $new_keyboard = set_main_keyboard_button_width($setting_row['keyboardmain'], $button_id, $width, $width_datatextbot);
    update('setting', 'keyboardmain', $new_keyboard, null, null);
    clearSelectCache('setting');
    flash('success', $width === 'full' ? 'Button is now full width.' : 'Button is now half width (two columns).');
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_bot_button_style') {
    csrf_check_post();
    $button_id = trim((string) ($_POST['button_id'] ?? ''));
    $style = trim((string) ($_POST['style'] ?? ''));
    if (set_main_keyboard_button_style($button_id, $style)) {
        flash('success', 'Button color saved.');
    } else {
        flash('error', 'Invalid button color.');
    }
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_check_post();
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $admin = db_fetch($pdo, "SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
    $valid = password_verify($cur, $admin['password']) || $cur === $admin['password'];

    if (!$valid) {
        flash('error', 'Current password is incorrect.');
    } elseif ($new !== $confirm) {
        flash('error', 'New password confirmation does not match.');
    } elseif (strlen($new) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } else {
        db_query(
            $pdo,
            "UPDATE admin SET password = ? WHERE username = ?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $_SESSION['admin_user']]
        );
        flash('success', 'Password changed.');
    }
    header('Location: settings.php?tab=security');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_channel_post') {
    csrf_check_post();
    ensure_channel_post_setting_column();
    $channel = normalize_channel_post_input($_POST['channel_post'] ?? '');
    update('setting', 'Channel_Post', $channel, null, null);
    clearSelectCache('setting');
    flash('success', $channel === '' ? 'Default channel cleared.' : 'Default channel saved.');
    header('Location: settings.php?tab=system');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_forced_join') {
    csrf_check_post();
    $saved = save_forced_join_channel(
        $_POST['channel_id'] ?? '',
        $_POST['remark'] ?? '',
        $_POST['linkjoin'] ?? ''
    );
    flash(!empty($saved['ok']) ? 'success' : 'error', $saved['msg'] ?? '');
    header('Location: settings.php?tab=system');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_forced_join') {
    csrf_check_post();
    $removed = delete_forced_join_channel($_POST['channel_link'] ?? '');
    flash(!empty($removed['ok']) ? 'success' : 'error', $removed['msg'] ?? '');
    header('Location: settings.php?tab=system');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['expense_add', 'expense_edit', 'expense_delete'], true)) {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'expense_add') {
        $r = panel_expense_add($pdo, (string) ($_POST['label'] ?? ''), (int) ($_POST['sort_order'] ?? 0));
    } elseif ($action === 'expense_edit') {
        $sortRaw = trim((string) ($_POST['sort_order'] ?? ''));
        $r = panel_expense_rename(
            $pdo,
            (int) ($_POST['edit_id'] ?? 0),
            (string) ($_POST['label'] ?? ''),
            $sortRaw === '' ? null : (int) $sortRaw
        );
    } else {
        $r = panel_expense_delete($pdo, (int) ($_POST['delete_id'] ?? 0));
    }
    flash(!empty($r['ok']) ? 'success' : 'error', $r['msg'] ?? '');
    header('Location: settings.php?tab=finance');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['income_add', 'income_edit', 'income_delete'], true)) {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'income_add') {
        $r = panel_income_add($pdo, (string) ($_POST['label'] ?? ''), (int) ($_POST['sort_order'] ?? 0));
    } elseif ($action === 'income_edit') {
        $sortRaw = trim((string) ($_POST['sort_order'] ?? ''));
        $r = panel_income_rename(
            $pdo,
            (int) ($_POST['edit_id'] ?? 0),
            (string) ($_POST['label'] ?? ''),
            $sortRaw === '' ? null : (int) $sortRaw
        );
    } else {
        $r = panel_income_delete($pdo, (int) ($_POST['delete_id'] ?? 0));
    }
    flash(!empty($r['ok']) ? 'success' : 'error', $r['msg'] ?? '');
    header('Location: settings.php?tab=finance');
    exit;
}

if (isset($_GET['delete_expense'])) {
    csrf_check_get();
    $r = panel_expense_delete($pdo, (int) ($_GET['delete_expense'] ?? 0));
    flash(!empty($r['ok']) ? 'success' : 'error', $r['msg'] ?? '');
    header('Location: settings.php?tab=finance');
    exit;
}

if (isset($_GET['delete_income'])) {
    csrf_check_get();
    $r = panel_income_delete($pdo, (int) ($_GET['delete_income'] ?? 0));
    flash(!empty($r['ok']) ? 'success' : 'error', $r['msg'] ?? '');
    header('Location: settings.php?tab=finance');
    exit;
}

$tab = $_GET['tab'] ?? 'appearance';

ensure_channel_post_setting_column();
$channel_post_setting = select('setting', 'Channel_Post', null, null, 'select', ['cache' => false]);
$channel_post_value = normalize_channel_post_input(is_array($channel_post_setting) ? ($channel_post_setting['Channel_Post'] ?? '') : '');

$forced_join_channels = [];
try {
    $forced_join_channels = db_fetchAll($pdo, 'SELECT remark, link, linkjoin FROM channels ORDER BY remark');
} catch (Throwable $e) {
    $forced_join_channels = [];
}

$bot_setting = select('setting', 'keyboardmain', null, null, 'select', ['cache' => false]);
$bot_keyboardmain = $bot_setting['keyboardmain'] ?? get_default_main_keyboard_json();
$textbot_rows = db_fetchAll($pdo, "SELECT id_text, text FROM textbot WHERE id_text IN ('" . implode("','", array_keys($bot_button_labels)) . "')");
$bot_text_labels = $bot_button_labels;
$bot_datatextbot = [];
foreach ($textbot_rows as $row) {
    if (!empty($row['text'])) {
        $bot_text_labels[$row['id_text']] = $row['text'];
    }
    $bot_datatextbot[$row['id_text']] = $row['text'];
}
foreach (array_keys($bot_button_labels) as $btn_id) {
    if (!isset($bot_datatextbot[$btn_id])) {
        $bot_datatextbot[$btn_id] = $bot_text_labels[$btn_id] ?? $btn_id;
    }
}
$normalized_bot_keyboard = normalize_keyboardmain_to_ids($bot_keyboardmain, $bot_datatextbot);
if ($normalized_bot_keyboard !== $bot_keyboardmain) {
    update('setting', 'keyboardmain', $normalized_bot_keyboard, null, null);
    $bot_keyboardmain = $normalized_bot_keyboard;
}
$bot_active_ids = get_active_main_keyboard_buttons($bot_keyboardmain, $bot_datatextbot);
$bot_solo_ids = get_main_keyboard_solo_button_ids($bot_keyboardmain, $bot_datatextbot);
$bot_button_styles = get_main_keyboard_button_styles();
$bot_button_icons = get_main_keyboard_button_icons();
$bot_style_options = [
    'primary' => 'Blue',
    'success' => 'Green',
    'danger' => 'Red',
];
$bot_all_ids = get_main_keyboard_button_ids();
$bot_ordered_ids = array_values(array_unique(array_merge(
    $bot_active_ids,
    array_values(array_diff($bot_all_ids, $bot_active_ids))
)));
$bot_menu_buttons = [];
$active_count = count($bot_active_ids);
foreach ($bot_ordered_ids as $index => $btn_id) {
    $is_active = in_array($btn_id, $bot_active_ids, true);
    $active_index = $is_active ? array_search($btn_id, $bot_active_ids, true) : false;
    $is_full = $is_active && in_array($btn_id, $bot_solo_ids, true);
    $full_label = get_main_keyboard_button_label($btn_id, $bot_datatextbot);
    $label_parts = split_main_keyboard_button_label($full_label);
    $icon_id = $bot_button_icons[$btn_id] ?? '';
    $bot_menu_buttons[] = [
        'id' => $btn_id,
        'label' => $full_label,
        'emoji' => $icon_id !== '' ? $icon_id : $label_parts['emoji'],
        'title' => $label_parts['title'] !== '' ? $label_parts['title'] : $full_label,
        'has_premium_emoji' => $icon_id !== '',
        'active' => $is_active,
        'full_width' => $is_full,
        'style' => $bot_button_styles[$btn_id] ?? '',
        'can_move_up' => $is_active && $active_index !== false && $active_index > 0,
        'can_move_down' => $is_active && $active_index !== false && $active_index < ($active_count - 1),
        'position' => $is_active && $active_index !== false ? ($active_index + 1) : null,
    ];
}
$bot_style_preview_colors = [
    '' => ['bg' => 'var(--sf3)', 'fg' => 'var(--tx)', 'bd' => 'var(--bd)'],
    'primary' => ['bg' => '#1B6AC9', 'fg' => '#fff', 'bd' => '#1B6AC9'],
    'success' => ['bg' => '#31B545', 'fg' => '#fff', 'bd' => '#31B545'],
    'danger' => ['bg' => '#E44C4C', 'fg' => '#fff', 'bd' => '#E44C4C'],
];
$bot_preview_rows = [];
$layout_preview = json_decode($bot_keyboardmain, true);
if (is_array($layout_preview) && !empty($layout_preview['keyboard'])) {
    foreach ($layout_preview['keyboard'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $preview_row = [];
        foreach ($row as $btn) {
            $id = $btn['text'] ?? '';
            if ($id === '') {
                continue;
            }
            $style = $bot_button_styles[$id] ?? '';
            $full_label = get_main_keyboard_button_label($id, $bot_datatextbot);
            $label_parts = split_main_keyboard_button_label($full_label);
            $icon_id = $bot_button_icons[$id] ?? '';
            $preview_row[] = [
                'label' => $full_label,
                'title' => $label_parts['title'] !== '' ? $label_parts['title'] : $full_label,
                'emoji' => $icon_id !== '' ? '' : $label_parts['emoji'],
                'has_premium_emoji' => $icon_id !== '',
                'style' => $style,
                'colors' => $bot_style_preview_colors[$style] ?? $bot_style_preview_colors[''],
            ];
        }
        if ($preview_row !== []) {
            $bot_preview_rows[] = $preview_row;
        }
    }
}

$themes = [
    'navy' => ['name' => 'Ocean blue', 'desc' => 'Default · Teal', 'c' => ['#0F172A', '#1E293B', '#06B6D4', '#22C55E'], 'dark' => true],
    'purple' => ['name' => 'Dream purple', 'desc' => 'Dark · modern', 'c' => ['#180D2E', '#231545', '#A855F7', '#F43F5E'], 'dark' => true],
    'emerald' => ['name' => 'Emerald green', 'desc' => 'Natural · calm', 'c' => ['#0A1F1C', '#132E2A', '#10B981', '#84CC16'], 'dark' => true],
    'sunset' => ['name' => 'Warm sunset', 'desc' => 'Warm · energetic', 'c' => ['#1A0D0D', '#2A1615', '#F97316', '#FBBF24'], 'dark' => true],
    'slate' => ['name' => 'Black', 'desc' => 'Neutral · minimal', 'c' => ['#080808', '#141414', '#E2E8F0', '#22C55E'], 'dark' => true],
    'light' => ['name' => 'Bright white', 'desc' => 'Light · professional', 'c' => ['#F1F5F9', '#FFFFFF', '#0891B2', '#16A34A'], 'dark' => false],
    'linen' => ['name' => 'Cream paper', 'desc' => 'Warm · editorial', 'c' => ['#FAF7F2', '#FFFFFF', '#B87333', '#5D7C4A'], 'dark' => false],
    'mint' => ['name' => 'Mint green', 'desc' => 'Fresh · natural', 'c' => ['#F0FDF4', '#FFFFFF', '#166534', '#1D4ED8'], 'dark' => false],
    'lavender' => ['name' => 'Lavender', 'desc' => 'Soft · calming', 'c' => ['#FAF5FF', '#FFFFFF', '#6D28D9', '#15803D'], 'dark' => false],
];

$tabs = [
    'appearance' => ['icon' => 'settings', 'label' => 'Appearance'],
    'bot' => ['icon' => 'menu', 'label' => 'Bot menu'],
    'finance' => ['icon' => 'wallet', 'label' => 'Finance'],
    'security' => ['icon' => 'block', 'label' => 'Security'],
    'system' => ['icon' => 'dashboard', 'label' => 'System'],
];

$expenseCategories = [];
$expenseUsage = [];
$incomeCategories = [];
$incomeUsage = [];
if ($tab === 'finance') {
    panel_payment_ensure_schema($pdo);
    $expenseCategories = panel_expense_categories($pdo);
    $expenseUsage = panel_expense_usage_counts($pdo);
    $incomeCategories = panel_income_categories($pdo);
    $incomeUsage = panel_income_usage_counts($pdo);
}

$pageTitle = $tab === 'bot' ? 'Bot menu' : 'Settings';
$activeNav = $tab === 'bot' ? 'bot_menu' : 'settings';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto"
    class="fade-up">
    <?php foreach ($tabs as $key => $tab_data): ?>
        <a href="?tab=<?= $key ?>"
            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .15s;text-decoration:none;
                  <?= $tab === $key ? 'background:var(--ac);color:#fff;box-shadow:0 0 14px var(--acg)' : 'color:var(--mute)' ?>">
            <?= icon($tab_data['icon'], 15) ?>     <?= $tab_data['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'appearance'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title">Panel colors</div>
                <div class="card-subtitle">Applies instantly · saved in the browser</div>
            </div>
        </div>
        <div class="card-body">
            <div
                style="font-size:.75rem;font-weight:700;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">
                Dark</div>
            <div class="theme-grid" style="margin-bottom:20px">
                <?php foreach ($themes as $key => $theme):
                    if (!$theme['dark'])
                        continue; ?>
                    <div class="theme-card" data-tk="<?= $key ?>" onclick="pickTheme('<?= $key ?>')">
                        <div class="theme-preview">
                            <?php foreach ($theme['c'] as $color): ?>
                                <div style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-desc"><?= htmlspecialchars($theme['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div
                style="font-size:.75rem;font-weight:700;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">
                Light</div>
            <div class="theme-grid">
                <?php foreach ($themes as $key => $theme):
                    if ($theme['dark'])
                        continue; ?>
                    <div class="theme-card" data-tk="<?= $key ?>" onclick="pickTheme('<?= $key ?>')">
                        <div class="theme-preview" style="border:1px solid var(--bd)">
                            <?php foreach ($theme['c'] as $color): ?>
                                <div style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-desc"><?= htmlspecialchars($theme['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card fade-up d1" style="margin-top:14px">
        <div class="card-head">
            <div>
                <div class="card-title">Sidebar layout</div>
            </div>
        </div>
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
            <button onclick="setSidebarMode(false)" class="btn btn-ghost" id="modeExpanded"
                style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 20px;flex:1;min-width:120px">
                <svg width="44" height="32" viewBox="0 0 44 32" fill="none">
                    <rect x="0" y="0" width="13" height="32" rx="3" fill="var(--sf3)" />
                    <rect x="2" y="5" width="9" height="2" rx="1" fill="var(--ac)" />
                    <rect x="2" y="10" width="9" height="2" rx="1" fill="var(--bd)" />
                    <rect x="15" y="0" width="29" height="32" rx="3" fill="var(--sf3)" />
                </svg>
                <span style="font-size:.78rem;font-weight:600">Expanded</span>
            </button>
            <button onclick="setSidebarMode(true)" class="btn btn-ghost" id="modeCollapsed"
                style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 20px;flex:1;min-width:120px">
                <svg width="44" height="32" viewBox="0 0 44 32" fill="none">
                    <rect x="0" y="0" width="7" height="32" rx="3" fill="var(--sf3)" />
                    <rect x="2" y="5" width="3" height="2" rx="1" fill="var(--ac)" />
                    <rect x="2" y="10" width="3" height="2" rx="1" fill="var(--bd)" />
                    <rect x="9" y="0" width="35" height="32" rx="3" fill="var(--sf3)" />
                </svg>
                <span style="font-size:.78rem;font-weight:600">Collapsed</span>
            </button>
        </div>
    </div>

<?php elseif ($tab === 'bot'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title">Bot main menu buttons</div>
                <div class="card-subtitle">Title, order, and visibility of the buttons users see in Telegram</div>
            </div>
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reset_bot_buttons">
                <button type="submit" class="btn btn-ghost btn-sm"><?= icon('settings', 14) ?> Reset to defaults</button>
            </form>
        </div>
        <div class="tbl-wrap">
            <table class="tbl-md">
                <thead>
                    <tr>
                        <th style="width:56px">Order</th>
                        <th colspan="2">Emoji and title</th>
                        <th>Width</th>
                        <th>Color</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bot_menu_buttons as $btn): ?>
                        <tr style="<?= $btn['active'] ? '' : 'opacity:.65' ?>">
                            <td>
                                <?php if ($btn['active']): ?>
                                    <div style="display:flex;flex-direction:column;gap:4px;align-items:center">
                                        <form method="POST" style="margin:0">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="move_bot_button">
                                            <input type="hidden" name="button_id" value="<?= htmlspecialchars($btn['id']) ?>">
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Move up"
                                                <?= $btn['can_move_up'] ? '' : 'disabled style="opacity:.35;pointer-events:none"' ?>>
                                                <?= icon('arrow-up', 14) ?>
                                            </button>
                                        </form>
                                        <span style="font-size:.72rem;color:var(--mute);font-weight:700"><?= (int) $btn['position'] ?></span>
                                        <form method="POST" style="margin:0">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="move_bot_button">
                                            <input type="hidden" name="button_id" value="<?= htmlspecialchars($btn['id']) ?>">
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Move down"
                                                <?= $btn['can_move_down'] ? '' : 'disabled style="opacity:.35;pointer-events:none"' ?>>
                                                <?= icon('arrow-down', 14) ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:.72rem;color:var(--mute)">—</span>
                                <?php endif; ?>
                            </td>
                            <td colspan="2">
                                <form method="POST" style="display:flex;gap:8px;align-items:center;min-width:280px">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="save_bot_button_title">
                                    <input type="hidden" name="button_id" value="<?= htmlspecialchars($btn['id']) ?>">
                                    <input type="text" name="emoji" class="input" maxlength="64"
                                        value="<?= htmlspecialchars($btn['emoji']) ?>"
                                        placeholder="<?= $btn['has_premium_emoji'] ? 'Premium ID' : '🔐 or ID' ?>"
                                        title="Regular emoji or Telegram premium emoji numeric ID"
                                        style="width:110px;flex:0 0 110px;padding:7px 8px;font-size:.8rem;text-align:center<?= $btn['has_premium_emoji'] ? ';font-family:ui-monospace,monospace;font-size:.72rem' : '' ?>">
                                    <input type="text" name="title" class="input" maxlength="32" required
                                        value="<?= htmlspecialchars($btn['title']) ?>"
                                        placeholder="Title without emoji"
                                        style="flex:1;min-width:0;padding:7px 10px;font-size:.85rem">
                                    <button type="submit" class="btn btn-primary btn-sm" title="Save">
                                        <?= icon('check', 14) ?>
                                    </button>
                                </form>
                                <?php if ($btn['has_premium_emoji']): ?>
                                    <div style="margin-top:4px;font-size:.68rem;color:var(--mute)">Premium emoji enabled</div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap">
                                <?php if ($btn['active']): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="set_bot_button_width">
                                        <input type="hidden" name="button_id" value="<?= htmlspecialchars($btn['id']) ?>">
                                        <input type="hidden" name="width" value="<?= $btn['full_width'] ? 'half' : 'full' ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm"
                                            title="<?= $btn['full_width'] ? 'Switch to two columns (half width)' : 'Full row (full width)' ?>">
                                            <span class="tag <?= $btn['full_width'] ? 'tag-ok' : 'tag-plain' ?>">
                                                <?= $btn['full_width'] ? 'Full' : 'Half' ?>
                                            </span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:.72rem;color:var(--mute)">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:flex;align-items:center;gap:6px;min-width:120px">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="set_bot_button_style">
                                    <input type="hidden" name="button_id" value="<?= htmlspecialchars($btn['id']) ?>">
                                    <select name="style" class="input" style="padding:6px 8px;font-size:.8rem;min-width:100px"
                                        onchange="this.form.submit()">
                                        <option value="default" <?= $btn['style'] === '' ? 'selected' : '' ?>>Default</option>
                                        <?php foreach ($bot_style_options as $style_key => $style_label): ?>
                                            <option value="<?= htmlspecialchars($style_key) ?>" <?= $btn['style'] === $style_key ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($style_label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <span class="tag <?= $btn['active'] ? 'tag-ok' : 'tag-plain' ?>">
                                    <?= $btn['active'] ? 'Visible' : 'Hidden' ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="toggle_bot_button">
                                    <input type="hidden" name="button_id" value="<?= htmlspecialchars($btn['id']) ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm">
                                        <?= $btn['active'] ? 'Hide' : 'Show' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($bot_preview_rows !== []): ?>
        <div class="card fade-up d1" style="margin-top:14px">
            <div class="card-head">
                <div>
                    <div class="card-title">Menu preview</div>
                    <div class="card-subtitle">Layout of active buttons as they appear in Telegram</div>
                </div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;max-width:420px">
                <?php foreach ($bot_preview_rows as $preview_row): ?>
                    <div style="display:grid;grid-template-columns:repeat(<?= count($preview_row) ?>,minmax(0,1fr));gap:8px">
                        <?php foreach ($preview_row as $preview_btn): ?>
                            <div style="background:<?= htmlspecialchars($preview_btn['colors']['bg']) ?>;color:<?= htmlspecialchars($preview_btn['colors']['fg']) ?>;border:1px solid <?= htmlspecialchars($preview_btn['colors']['bd']) ?>;border-radius:8px;padding:10px 12px;text-align:center;font-size:.82rem;font-weight:600">
                                <?php if ($preview_btn['has_premium_emoji']): ?>
                                    <span style="opacity:.85;margin-inline-end:4px" title="Premium emoji">✦</span>
                                <?php elseif ($preview_btn['emoji'] !== ''): ?>
                                    <span style="margin-inline-end:4px"><?= htmlspecialchars($preview_btn['emoji']) ?></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($preview_btn['title']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card fade-up d2" style="margin-top:14px">
        <div class="card-body" style="font-size:.82rem;color:var(--mute);line-height:1.7">
            Use the up/down buttons to change the order.
            <strong>Emoji</strong> column: you can put a regular emoji here (such as 🔐).
            For a <strong>premium emoji</strong>, in the bot go to: General settings → Configure menu buttons → the button you want → Set premium emoji (send a custom emoji with the bot owner's premium account).
            <strong>Width</strong> column: <strong>Full</strong> means the button sits alone on a row (full width in Telegram), <strong>Half</strong> means two buttons on one row.
            <strong>Color</strong> column: blue, green, or red (official Telegram feature; may not show in older app versions).
            Titles are limited to 32 characters. Current users see the changes after they receive the menu again.
        </div>
    </div>

<?php elseif ($tab === 'finance'): ?>

    <div class="card fade-up" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <div class="card-title">Income categories</div>
                <div class="card-subtitle">Payment methods and gateways are fixed; add custom income categories here</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('incomeAddModal')"><?= icon('plus', 14) ?> Add category</button>
        </div>
        <?php if (empty($incomeCategories)): ?>
            <div class="empty" style="padding:48px 20px">
                <p>No categories yet</p>
                <button type="button" class="btn btn-primary" style="margin-top:14px" onclick="openModal('incomeAddModal')"><?= icon('plus', 14) ?> Add category</button>
            </div>
        <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category name</th>
                            <th>Income count</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($incomeCategories as $cat):
                            $slug = (string) ($cat['slug'] ?? '');
                            $isProtected = in_array($slug, panel_income_protected_slugs(), true);
                            $used = (int) ($incomeUsage[$slug] ?? 0);
                            $editPayload = [
                                'id' => (int) ($cat['id'] ?? 0),
                                'label' => (string) ($cat['label'] ?? ''),
                                'sort_order' => (int) ($cat['sort_order'] ?? 0),
                            ];
                        ?>
                        <tr>
                            <td class="cf"><?= $i++ ?></td>
                            <td>
                                <?= htmlspecialchars((string) ($cat['label'] ?? '')) ?>
                                <?php if ($isProtected): ?>
                                    <span class="tag tag-plain" style="margin-right:6px">System</span>
                                <?php endif; ?>
                            </td>
                            <td class="cn"><?= number_format($used) ?></td>
                            <td class="cf"><?= (int) ($cat['sort_order'] ?? 0) ?></td>
                            <td>
                                <div style="display:flex;gap:5px;flex-wrap:wrap">
                                    <?php if (!$isProtected): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" title="Edit"
                                        onclick="openIncomeEditModal(<?= htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                                        <?= icon('edit', 13) ?>
                                    </button>
                                    <?php if ($used > 0): ?>
                                    <button type="button" class="btn btn-no btn-sm btn-icon" title="This category is used on <?= number_format($used) ?> income records" disabled>
                                        <?= icon('trash', 13) ?>
                                    </button>
                                    <?php else: ?>
                                    <a href="settings.php?tab=finance&delete_income=<?= (int) ($cat['id'] ?? 0) ?>&_csrf=<?= csrf_token() ?>"
                                        class="btn btn-no btn-sm btn-icon" title="Delete"
                                        data-confirm="Delete category “<?= htmlspecialchars((string) ($cat['label'] ?? '')) ?>”?">
                                        <?= icon('trash', 13) ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="cf" style="font-size:.75rem">Locked</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title">Expense categories</div>
                <div class="card-subtitle">These categories are used instead of a payment method when recording expenses in Finance</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('expenseAddModal')"><?= icon('plus', 14) ?> Add category</button>
        </div>
        <?php if (empty($expenseCategories)): ?>
            <div class="empty" style="padding:48px 20px">
                <p>No categories yet</p>
                <button type="button" class="btn btn-primary" style="margin-top:14px" onclick="openModal('expenseAddModal')"><?= icon('plus', 14) ?> Add category</button>
            </div>
        <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category name</th>
                            <th>Expense count</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($expenseCategories as $cat):
                            $slug = (string) ($cat['slug'] ?? '');
                            $isDefault = $slug === panel_expense_default_slug();
                            $used = (int) ($expenseUsage[$slug] ?? 0);
                            $editPayload = [
                                'id' => (int) ($cat['id'] ?? 0),
                                'label' => (string) ($cat['label'] ?? ''),
                                'sort_order' => (int) ($cat['sort_order'] ?? 0),
                            ];
                        ?>
                        <tr>
                            <td class="cf"><?= $i++ ?></td>
                            <td>
                                <?= htmlspecialchars((string) ($cat['label'] ?? '')) ?>
                                <?php if ($isDefault): ?>
                                    <span class="tag tag-plain" style="margin-right:6px">Default</span>
                                <?php endif; ?>
                            </td>
                            <td class="cn"><?= number_format($used) ?></td>
                            <td class="cf"><?= (int) ($cat['sort_order'] ?? 0) ?></td>
                            <td>
                                <div style="display:flex;gap:5px;flex-wrap:wrap">
                                    <button type="button" class="btn btn-ghost btn-sm btn-icon" title="Edit"
                                        onclick="openExpenseEditModal(<?= htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                                        <?= icon('edit', 13) ?>
                                    </button>
                                    <?php if (!$isDefault): ?>
                                    <?php if ($used > 0): ?>
                                    <button type="button" class="btn btn-no btn-sm btn-icon" title="This category is used on <?= number_format($used) ?> expense records" disabled>
                                        <?= icon('trash', 13) ?>
                                    </button>
                                    <?php else: ?>
                                    <a href="settings.php?tab=finance&delete_expense=<?= (int) ($cat['id'] ?? 0) ?>&_csrf=<?= csrf_token() ?>"
                                        class="btn btn-no btn-sm btn-icon" title="Delete"
                                        data-confirm="Delete category “<?= htmlspecialchars((string) ($cat['label'] ?? '')) ?>”?">
                                        <?= icon('trash', 13) ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-veil" id="expenseAddModal">
        <div class="modal" style="max-width:480px">
            <div class="modal-head">
                <h3>Add expense category</h3>
                <button type="button" class="modal-x" onclick="closeModal('expenseAddModal')"><?= icon('close', 14) ?></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="expense_add">
                    <div class="field">
                        <label>Category name *</label>
                        <input type="text" name="label" class="input" placeholder="e.g. server rent, ads, ..." required maxlength="64">
                    </div>
                    <div class="field">
                        <label>Display order</label>
                        <input type="number" name="sort_order" class="input" value="0" step="1">
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> Save</button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('expenseAddModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-veil" id="expenseEditModal">
        <div class="modal" style="max-width:480px">
            <div class="modal-head">
                <h3>Edit expense category</h3>
                <button type="button" class="modal-x" onclick="closeModal('expenseEditModal')"><?= icon('close', 14) ?></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="expense_edit">
                    <input type="hidden" name="edit_id" id="expense_edit_id">
                    <div class="field">
                        <label>Category name *</label>
                        <input type="text" name="label" id="expense_edit_label" class="input" required maxlength="64">
                    </div>
                    <div class="field">
                        <label>Display order</label>
                        <input type="number" name="sort_order" id="expense_edit_sort" class="input" step="1">
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> Save changes</button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('expenseEditModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.openExpenseEditModal = function (c) {
        document.getElementById('expense_edit_id').value = c.id || '';
        document.getElementById('expense_edit_label').value = c.label || '';
        document.getElementById('expense_edit_sort').value = c.sort_order != null ? c.sort_order : 0;
        openModal('expenseEditModal');
    };
    window.openIncomeEditModal = function (c) {
        document.getElementById('income_edit_id').value = c.id || '';
        document.getElementById('income_edit_label').value = c.label || '';
        document.getElementById('income_edit_sort').value = c.sort_order != null ? c.sort_order : 0;
        openModal('incomeEditModal');
    };
    </script>

    <div class="modal-veil" id="incomeAddModal">
        <div class="modal" style="max-width:480px">
            <div class="modal-head">
                <h3>Add income category</h3>
                <button type="button" class="modal-x" onclick="closeModal('incomeAddModal')"><?= icon('close', 14) ?></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="income_add">
                    <div class="field">
                        <label>Category name *</label>
                        <input type="text" name="label" class="input" placeholder="e.g. hardware sales, rent, ..." required maxlength="64">
                    </div>
                    <div class="field">
                        <label>Display order</label>
                        <input type="number" name="sort_order" class="input" value="0" step="1">
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> Save</button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('incomeAddModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-veil" id="incomeEditModal">
        <div class="modal" style="max-width:480px">
            <div class="modal-head">
                <h3>Edit income category</h3>
                <button type="button" class="modal-x" onclick="closeModal('incomeEditModal')"><?= icon('close', 14) ?></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="income_edit">
                    <input type="hidden" name="edit_id" id="income_edit_id">
                    <div class="field">
                        <label>Category name *</label>
                        <input type="text" name="label" id="income_edit_label" class="input" required maxlength="64">
                    </div>
                    <div class="field">
                        <label>Display order</label>
                        <input type="number" name="sort_order" id="income_edit_sort" class="input" step="1">
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> Save changes</button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('incomeEditModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'security'): ?>

    <div class="two-col">
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title">Change password</div>
                    <div class="card-subtitle">Used to sign in to the panel</div>
                </div>
            </div>
            <form method="POST" class="card-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="field">
                        <label>Current password</label>
                        <div style="position:relative">
                            <input type="password" name="current_password" id="pw1" class="input" required
                                autocomplete="current-password" style="padding-left:40px">
                            <button type="button" onclick="togglePw('pw1', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--dim);cursor:pointer">
                                <?= icon('eye', 16) ?>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label>New password</label>
                        <div style="position:relative">
                            <input type="password" name="new_password" id="pw2" class="input" minlength="6" required
                                autocomplete="new-password" style="padding-left:40px" oninput="checkPwStr(this.value)">
                            <button type="button" onclick="togglePw('pw2', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--dim);cursor:pointer">
                                <?= icon('eye', 16) ?>
                            </button>
                        </div>
                        <div style="height:4px;background:var(--sf3);border-radius:99px;margin-top:5px">
                            <div id="pwBar"
                                style="height:100%;width:0;border-radius:99px;transition:all .3s;background:var(--no)">
                            </div>
                        </div>
                        <span id="pwHint" class="field-hint">At least 6 characters</span>
                    </div>
                    <div class="field">
                        <label>Confirm new password</label>
                        <input type="password" name="confirm_password" class="input" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Change password</button>
                </div>
            </form>
        </div>

        <div class="card fade-up d1" style="height:fit-content">
            <div class="card-head">
                <div>
                    <div class="card-title">Current session</div>
                </div>
                <a href="logout.php" class="btn btn-no btn-sm"><?= icon('logout', 13) ?> Log out</a>
            </div>
            <div class="kv-list">
                <div class="kv">
                    <span class="kv-key">Admin</span>
                    <span class="kv-val"><?= htmlspecialchars($_SESSION['admin_user']) ?></span>
                </div>
                <div class="kv">
                    <span class="kv-key">Signed in</span>
                    <span class="kv-val">
                        <?= isset($_SESSION['login_time']) ? date('Y/m/d H:i:s', $_SESSION['login_time']) : '—' ?>
                    </span>
                </div>
                <div class="kv">
                    <span class="kv-key">IP</span>
                    <span class="kv-val cm"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—') ?></span>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'system'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title">Forced channel join</div>
                <div class="card-subtitle">Users cannot use the bot until they join these channels. The bot must be a channel admin.</div>
            </div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:18px">
            <?php if (empty($forced_join_channels)): ?>
                <p style="margin:0;color:var(--mute);font-size:.9rem">No forced-join channels yet.</p>
            <?php else: ?>
                <div class="tbl-wrap">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th>Button name</th>
                                <th>Channel ID</th>
                                <th>Join link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forced_join_channels as $ch): ?>
                                <tr>
                                    <td class="cs"><?= htmlspecialchars($ch['remark'] ?? '') ?></td>
                                    <td class="cm" dir="ltr"><?= htmlspecialchars($ch['link'] ?? '') ?></td>
                                    <td class="cn" dir="ltr" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        <?php if (!empty($ch['linkjoin'])): ?>
                                            <a href="<?= htmlspecialchars($ch['linkjoin']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($ch['linkjoin']) ?></a>
                                        <?php else: ?>
                                            <span style="color:var(--mute)">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Remove this channel from forced join?')">
                                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_forced_join">
                                            <input type="hidden" name="channel_link" value="<?= htmlspecialchars($ch['link'] ?? '') ?>">
                                            <button type="submit" class="btn btn-no btn-sm btn-icon" title="Delete"><?= icon('trash', 13) ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <form method="POST" style="display:flex;flex-direction:column;gap:14px">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_forced_join">
                <div class="field">
                    <label>Channel username or numeric ID</label>
                    <input type="text" name="channel_id" class="input" dir="ltr"
                        placeholder="@mychannel or -1001234567890"
                        autocomplete="off" required>
                    <span class="field-hint">The same ID the bot uses to check Telegram membership.</span>
                </div>
                <div class="field">
                    <label>Join button label</label>
                    <input type="text" name="remark" class="input" placeholder="e.g. Join the news channel" maxlength="64" required>
                </div>
                <div class="field">
                    <label>Join link</label>
                    <input type="text" name="linkjoin" class="input" dir="ltr"
                        placeholder="https://t.me/mychannel or invite link"
                        autocomplete="off">
                    <span class="field-hint">For a public channel, leave this empty to build it from the username. Private channels always need an invite link.</span>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary"><?= icon('plus', 14) ?> Add channel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card fade-up d1" style="margin-top:14px">
        <div class="card-head">
            <div>
                <div class="card-title">Post-to-channel</div>
                <div class="card-subtitle">Default ID or username for “post to channel” in the bot</div>
            </div>
        </div>
        <form method="POST" class="card-body">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_channel_post">
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="field">
                    <label>Numeric ID or channel username</label>
                    <input type="text" name="channel_post" class="input" dir="ltr"
                        value="<?= htmlspecialchars($channel_post_value) ?>"
                        placeholder="@mychannel or -1001234567890"
                        autocomplete="off">
                    <span class="field-hint">The bot must be a channel admin with permission to post. Leave empty to ask in the bot each time.</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Save</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card fade-up d1" style="margin-top:14px">
        <div class="card-head">
            <div>
                <div class="card-title">Environment</div>
            </div>
        </div>
        <div class="kv-list">
            <?php
            $dbVer = '—';
            try {
                $dbVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Exception $e) {
            }
            $sysInfo = [
                ['Panel version', 1.0],
                ['PHP', phpversion()],
                ['MySQL', $dbVer],
                ['Web server', $_SERVER['SERVER_SOFTWARE'] ?? '—'],
                ['Current admin', $_SESSION['admin_user']],
                ['Server time', date('Y/m/d H:i:s')],
                ['PHP memory', ini_get('memory_limit')],
            ];
            foreach ($sysInfo as [$key, $value]):
                ?>
                <div class="kv">
                    <span class="kv-key"><?= $key ?></span>
                    <span class="kv-val cm" style="font-size:.78rem"><?= htmlspecialchars($value) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

<script src="<?= htmlspecialchars(panel_asset('js/settings.js')) ?>"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>