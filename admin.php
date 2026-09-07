<?php
if (!isset($from_id)) {
    $from_id = 0;
}
$from_id = (int) $from_id;
#----------------[  admin section  ]------------------#
$textadmin = ["panel", "/panel", $textbotlang['Admin']['textpaneladmin']];
$text_panel_admin_login_template = "💎 | Version Bot: $version
📌 | Version Mini App: 0.1.1

<blockquote>🔹 | This bot is completely free and developed by the Picha team</blockquote>

<blockquote>🔹 | Selling this bot or charging money for it is a violation.</blockquote>

<blockquote>🔹 | If you see it being sold or charged for, please recover your money.</blockquote>

<blockquote>🐞 | If you run into a bug or issue, contact us via the **📬 Bot report** button in the admin panel.</blockquote>";

if (!in_array($from_id, $admin_ids))
    return;

$domainhostsEscaped = htmlspecialchars($domainhosts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if (!function_exists('cryptomus_admin_payment_by_id')) {
    function cryptomus_admin_payment_by_id($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id = ? AND Payment_Method = 'cryptomus' LIMIT 1");
        $stmt->execute([(int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }
}

if (!function_exists('cryptomus_admin_operations_view')) {
    function cryptomus_admin_operations_view()
    {
        global $pdo;
        $stmt = $pdo->query(
            "SELECT * FROM Payment_report
             WHERE Payment_Method = 'cryptomus' AND (
                 gateway_status IN ('wrong_amount', 'wrong_amount_waiting', 'review', 'check', 'locked', 'fail', 'system_fail', 'refund_fail')
                 OR payment_Status IN ('review', 'locked', 'fail', 'failed')
                 OR (payment_Status = 'paid' AND fulfillment_state = 'completed'
                     AND COALESCE(refund_status, '') NOT IN ('refund_process', 'refund_paid'))
             )
             ORDER BY id DESC LIMIT 10"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $keyboard = ['inline_keyboard' => []];
        $lines = ["🧾 <b>Recent Cryptomus operations</b>"];
        if (!$rows) {
            $lines[] = "\nNo actionable items.";
        }
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $uuid = (string) ($row['dec_not_confirmed'] ?? '');
            $shortUuid = $uuid === '' ? '—' : (strlen($uuid) > 16 ? substr($uuid, 0, 8) . '…' . substr($uuid, -6) : $uuid);
            $esc = static function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            };
            $short = static function ($value, $limit) {
                $value = (string) $value;
                return strlen($value) > $limit ? substr($value, 0, $limit - 1) . '…' : $value;
            };
            $lines[] = "\n<b>#" . $id . "</b>"
                . "\nLocal: <code>" . $esc($short($row['payment_Status'] ?? '—', 30))
                . " / " . $esc($short($row['fulfillment_state'] ?? '—', 30)) . "</code>"
                . " | Gateway: <code>" . $esc($short($row['gateway_status'] ?? '—', 30)) . "</code>"
                . "\nOrder: <code>" . $esc($short($row['id_order'] ?? '—', 48)) . "</code>"
                . " | User: <code>" . $esc($short($row['id_user'] ?? '—', 24)) . "</code>"
                . "\nAmount: <code>" . $esc($short($row['price'] ?? '—', 24)) . " USD</code>"
                . " | UUID: <code>" . $esc($shortUuid) . "</code>"
                . "\nRefund: <code>" . $esc($short($row['refund_status'] ?: 'none', 30)) . "</code>";

            $buttons = [];
            if (in_array((string) ($row['gateway_status'] ?? ''), ['wrong_amount', 'wrong_amount_waiting'], true)) {
                $buttons[] = ['text' => "✅ Approve underpayment #$id", 'callback_data' => "cm_ap_$id"];
                $buttons[] = ['text' => "❌ Cancel #$id", 'callback_data' => "cm_ca_$id"];
            }
            if (($row['payment_Status'] ?? '') === 'paid'
                && ($row['fulfillment_state'] ?? '') === 'completed'
                && !in_array((string) ($row['refund_status'] ?? ''), ['refund_process', 'refund_paid'], true)
            ) {
                $buttons[] = ['text' => "↩️ Full refund #$id", 'callback_data' => "cm_rf_$id"];
            }
            if ($buttons) {
                $keyboard['inline_keyboard'][] = $buttons;
            }
        }
        $keyboard['inline_keyboard'][] = [['text' => "🔄 Refresh", 'callback_data' => "cm_ops"]];
        return ['text' => implode("\n", $lines), 'keyboard' => json_encode($keyboard)];
    }
}

if (!function_exists('cryptomus_admin_settings_text')) {
    function cryptomus_admin_settings_text($domain)
    {
        $merchantSet = !in_array((string) getPaySettingValue('merchant_cryptomus', '0'), ['', '0'], true);
        $apiSet = !in_array((string) getPaySettingValue('apicryptomus', '0'), ['', '0'], true);
        $min = htmlspecialchars((string) getPaySettingValue('minbalancecryptomus', '0'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $max = htmlspecialchars((string) getPaySettingValue('maxbalancecryptomus', '0'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cashback = htmlspecialchars((string) getPaySettingValue('chashbackcryptomus', '0'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $callback = htmlspecialchars("https://{$domain}/payment/cryptomus.php", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "⚙️ <b>Cryptomus settings</b>"
            . "\n\nMerchant UUID: " . ($merchantSet ? '✅ Set' : '❌ Not set')
            . "\nPayment API Key: " . ($apiSet ? '✅ Set (hidden)' : '❌ Not set')
            . "\nMin/max: <code>$min / $max USD</code>"
            . "\nCashback: <code>$cashback%</code>"
            . "\n\nCallback URL:\n<code>$callback</code>"
            . "\n\n⚠️ The <b>Auto-Convert to USDT</b> option must be enabled manually in the Cryptomus dashboard.";
    }
}

$miniAppInstructionText = <<<HTML
📌 How to enable the Mini App in BotFather

/mybots > Select Bot > Bot Setting >  Configure Mini App > Enable Mini App  > Edit Mini App URL

Follow the steps above, then send this URL:

<code>https://{$domainhostsEscaped}/app/</code>
HTML;

if (in_array($text, $textadmin) || $datain == "admin") {
    if ($datain == "admin")
        deletemessage($from_id, $message_id);
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        sendmessage($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    $version_mini_app = file_get_contents('app/version');
    activecron();
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    sendmessage($from_id, $text_admin, $keyboardadmin, 'HTML');
    $miniAppInstructionHidden = isset($user['hide_mini_app_instruction']) ? (string) $user['hide_mini_app_instruction'] : '0';
    if ($miniAppInstructionHidden !== '1') {
        $miniAppInstructionKeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'Don\'t show again ⛓️‍💥', 'callback_data' => 'hide_mini_app_instruction'],
                ],
            ],
        ]);
        sendmessage($from_id, $miniAppInstructionText, $miniAppInstructionKeyboard, 'HTML');
    }
} elseif ($text == $textbotlang['Admin']['backadmin']) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        sendmessage($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    $version_mini_app = file_get_contents('app/version');
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    sendmessage($from_id, $text_admin, $keyboardadmin, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "hide_mini_app_instruction") {
    if (!in_array($from_id, $admin_ids))
        return;
    if (($user['hide_mini_app_instruction'] ?? '0') !== '1') {
        update("user", "hide_mini_app_instruction", "1", "id", $from_id);
        $user['hide_mini_app_instruction'] = '1';
    }
    $confirmationKeyboard = json_encode(['inline_keyboard' => []]);
    $confirmationText = $miniAppInstructionText . "\n\n✅ This message will no longer be shown to you.";
    Editmessagetext($from_id, $message_id, $confirmationText, $confirmationKeyboard, 'HTML');
    return;
} elseif ($text == $textbotlang['Admin']['backmenu']) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        sendmessage($from_id, $textbotlang['Admin']['activebottext'], $setting_panel, 'HTML');
        return;
    }
    step('home', $from_id);
    if (in_array($user['step'], ["updatetime", "val_usertest", "getlimitnew", "GetusernameNew", "GeturlNew", "protocolset", "updatemethodusername", "GetNameNew", "getprotocol", "getprotocolremove", "GetpaawordNew", "updateextendmethod", "setpricechangelocation"])) {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        outtypepanel($typepanel['type'], $textbotlang['Admin']['Back-menu']);
    } elseif (in_array($user['step'], ["selectloc", "get_limit", "selectlocedite", "GetPriceExtra", "GetPriceexstratime", "GetPricecustomtime", "GetPricecustomvolume", "get_code", "get_codesell", "minbalancebulk"])) {
        sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
    } elseif (in_array($user['step'], ["addchannel", "getremark", "getlinkjoin", "removechannel"])) {
        sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
    }
    return;
} elseif ($text == $textbotlang['Admin']['channel']['title'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['channel']['changechannel'], $backadmin, 'HTML');
    step('addchannel', $from_id);
} elseif ($user['step'] == "addchannel") {
    $channel_id = normalize_forced_join_channel_id($text);
    if ($channel_id === '') {
        sendmessage($from_id, "❌ Invalid channel username or ID.\nCorrect example: <code>@mychannel</code> or <code>-1001234567890</code>", $backadmin, 'HTML');
        return;
    }
    $exists = select("channels", "*", "link", $channel_id, "select");
    if (is_array($exists) && !empty($exists['link'])) {
        sendmessage($from_id, "❌ This channel is already registered.", $backadmin, 'HTML');
        return;
    }
    $verify = verify_bot_is_channel_admin($channel_id);
    if (empty($verify['ok'])) {
        $err = ($verify['error'] ?? '') === 'not_admin'
            ? "❌ The bot is not an admin of this channel. Make the bot a channel admin, then send again."
            : "❌ Channel not found. Check the username/ID and make sure the bot is a member of the channel.";
        sendmessage($from_id, $err, $backadmin, 'HTML');
        return;
    }
    savedata("clear", "link", $channel_id);
    $title = htmlspecialchars((string) ($verify['title'] ?? $channel_id), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    sendmessage($from_id, "✅ Channel <b>{$title}</b> confirmed.\n\n📌 Choose a name for the join button.", $backadmin, 'HTML');
    step('getremark', $from_id);
} elseif ($user['step'] == "getremark") {
    $remark = trim((string) $text);
    if ($remark === '' || mb_strlen($remark) > 64) {
        sendmessage($from_id, "❌ Button name must be between 1 and 64 characters.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "remark", $remark);
    $userdata = json_decode($user['Processing_value'], true);
    $channel_id = is_array($userdata) ? (string) ($userdata['link'] ?? '') : '';
    $suggested = normalize_forced_join_url('', $channel_id);
    $hint = $suggested !== ''
        ? "If the channel is public you can send this link:\n<code>{$suggested}</code>"
        : "For a private channel, send an Invite Link.";
    sendmessage($from_id, "📌 Send the channel join link.\n{$hint}", $backadmin, 'HTML');
    step('getlinkjoin', $from_id);
} elseif ($user['step'] == "getlinkjoin") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }
    $saved = save_forced_join_channel($userdata['link'] ?? '', $userdata['remark'] ?? '', $text);
    if (empty($saved['ok'])) {
        sendmessage($from_id, "❌ " . ($saved['msg'] ?? 'Failed to register the channel.'), $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ " . $saved['msg'], $channelkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['channel']['removechannelbtn'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['channel']['removechannel'], $list_channels_joins, 'HTML');
    step('removechannel', $from_id);
} elseif ($user['step'] == "removechannel") {
    $removed = delete_forced_join_channel($text);
    sendmessage($from_id, empty($removed['ok']) ? ("❌ " . $removed['msg']) : $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "addnewadmin" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    $adminId = trim($text);
    if ($adminId === '') {
        sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $adminId, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['setrule'], $adminrule, 'HTML');
    step('getrule', $from_id);
} elseif ($user['step'] == "getrule") {
    $rule = ['administrator', 'Seller', 'support'];
    if (!in_array($text, $rule)) {
        sendmessage($from_id, $textbotlang['Admin']['manageadmin']['invalidrule'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $usernamepanel = "root";
    $randomString = bin2hex(random_bytes(5));
    $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username, password, rule) VALUES (:id_admin, :username, :password, :rule)");
    $stmt->bindParam(':id_admin', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
    $stmt->bindParam(':password', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':rule', $text, PDO::PARAM_STR);
    $stmt->execute();
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['adminadded'], $username, $from_id, $text, $user['Processing_value']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/limitusertest_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('get_number_limit', $from_id);
} elseif ($user['step'] == "get_number_limit") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['setlimit'], $keyboardadmin, 'HTML');
    $id_user_set = $text;
    step('home', $from_id);
    update("user", "limit_usertest", $text, "id", $user['Processing_value']);
} elseif ($text == $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($user['step'] == "limit_usertest_allusers") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['setlimitall'], $keyboardadmin, 'HTML');
    step('home', $from_id);
    update("user", "limit_usertest", $text);
    update("setting", "limit_usertest_all", $text);
} elseif ($text == "📯 Channel settings" && $adminrulecheck['rule'] == "administrator") {
    $channel_rows = select("channels", "*", null, null, "fetchAll");
    $channel_summary = $textbotlang['Admin']['channel']['description'];
    if (is_array($channel_rows) && count($channel_rows) > 0) {
        $channel_summary .= "\n\n📋 Current channels:";
        foreach ($channel_rows as $channel_row) {
            $remark_label = htmlspecialchars((string) ($channel_row['remark'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $link_label = htmlspecialchars((string) ($channel_row['link'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $join_label = htmlspecialchars((string) ($channel_row['linkjoin'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $channel_summary .= "\n• {$remark_label}\n  ID: <code>{$link_label}</code>\n  Link: {$join_label}";
        }
    } else {
        $channel_summary .= "\n\nNo channel has been registered yet.";
    }
    sendmessage($from_id, $channel_summary, $channelkeyboard, 'HTML');
} elseif ($text == $textbotlang['Admin']['Status']['btn'] || $datain == "stat_all_bot") {
    $Balanceall = select("user", "SUM(Balance)", null, null, "select")['SUM(Balance)'];
    $statistics = select("user", "*", null, null, "count");
    $sumpanel = select("marzban_panel", "*", null, null, "count");
    $sql1 = "SELECT COUNT(id) AS count FROM user WHERE agent != 'f'";
    $stmt1 = $pdo->query($sql1);
    $agentsum = $stmt1->fetch(PDO::FETCH_ASSOC)['count'];
    $agentsumn = select("user", "COUNT(id)", "agent", "n", "select")['COUNT(id)'];
    $agentsumn2 = select("user", "COUNT(id)", "agent", "n2", "select")['COUNT(id)'];
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt1 = $pdo->query($sql1);
    $invoiceactive = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sqlall = "SELECT COUNT(*) AS invoice_count, COALESCE(SUM(price_product),0) AS invoice_sum FROM invoice WHERE " . invoice_paid_status_sql('Status') . " AND name_product != 'سرویس تست'";
    $sqlall = $pdo->query($sqlall);
    $invoiceRow = $sqlall->fetch(PDO::FETCH_ASSOC) ?: [];
    $invoice = $invoiceRow['invoice_count'] ?? 0;
    $invoicePaidSum = (float) ($invoiceRow['invoice_sum'] ?? 0);
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt2 = $pdo->query($sql2);
    $invoicesum = $stmt2->fetch(PDO::FETCH_ASSOC)['total_price'];
    $sql33 = "SELECT COALESCE(SUM(CAST(price AS DECIMAL(20,0))),0) AS total_price FROM Payment_report WHERE " . paid_real_income_sql();
    $sql33 = $pdo->query($sql33);
    $invoiceSumRow = $sql33->fetch(PDO::FETCH_ASSOC);
    $invoiceTotal = isset($invoiceSumRow['total_price']) ? (float) $invoiceSumRow['total_price'] : 0;
    $withdrawAll = bot_wallet_withdraw_stats($pdo);
    $withdrawCountAll = (int) ($withdrawAll['count'] ?? 0);
    $withdrawSumAll = (float) ($withdrawAll['sum'] ?? 0);
    $invoicesumall = number_format($invoiceTotal - $withdrawSumAll, 0);
    $withdrawSumAllFmt = number_format($withdrawSumAll, 0);
    $sql3 = "SELECT COALESCE(SUM(CAST(price AS DECIMAL(20,0))),0) AS total_extend FROM service_other WHERE type IN ('extend_user','extends_not_user','extend_user_by_admin') AND status = 'paid'";
    $stmt3 = $pdo->query($sql3);
    $extendSumRow = $stmt3->fetch(PDO::FETCH_ASSOC);
    $extendsum = isset($extendSumRow['total_extend']) ? (float) $extendSumRow['total_extend'] : 0;
    $count_usertest = select("invoice", "*", "name_product", "سرویس تست", "count");
    $timeacc = jdate('H:i:s', time());
    $paidSql = invoice_paid_status_sql('Status');
    $stmt2 = $pdo->query("SELECT
        COUNT(*) AS users_with_account,
        COALESCE(SUM(has_purchase), 0) AS users_with_purchase,
        COALESCE(SUM(has_test), 0) AS users_with_test,
        COALESCE(SUM(has_test AND NOT has_purchase), 0) AS users_with_test_no_purchase,
        COALESCE(SUM(has_test AND has_purchase), 0) AS users_with_test_and_purchase
        FROM (
            SELECT id_user,
                   MAX(name_product = 'سرویس تست') AS has_test,
                   MAX(name_product != 'سرویس تست') AS has_purchase
            FROM invoice
            WHERE $paidSql
            GROUP BY id_user
        ) user_invoice_flags");
    $statsUsers = $stmt2->fetch(PDO::FETCH_ASSOC);
    $count_users_account = (int) ($statsUsers['users_with_account'] ?? 0);
    $statisticsorder = (int) ($statsUsers['users_with_purchase'] ?? 0);
    $count_users_test = (int) ($statsUsers['users_with_test'] ?? 0);
    $count_users_test_no_purchase = (int) ($statsUsers['users_with_test_no_purchase'] ?? 0);
    $count_users_test_and_purchase = (int) ($statsUsers['users_with_test_and_purchase'] ?? 0);
    $sqlsum = "SELECT SUM(price) AS sumpay , Payment_Method,COUNT(price) AS countpay FROM Payment_report WHERE " . paid_real_income_sql() . " GROUP BY  Payment_Method;";
    $stmt = $pdo->prepare($sqlsum);
    $stmt->execute();
    $statispay = $stmt->fetchAll();
    $date = date("Y-m-d");
    $timeacc = jdate('H:i:s', time());
    $invoicesum = (float) ($invoicesum ?? 0);
    $extendsum = (float) ($extendsum ?? 0);
    $statistics = (int) ($statistics ?? 0);
    $paycount = "";
    $ratecustomer = $statistics > 0 ? round(($statisticsorder / $statistics) * 100, 2) : 0;
    $ratetest = $statistics > 0 ? round(($count_users_test / $statistics) * 100, 2) : 0;
    $avgbuy_customer = $statisticsorder > 0 ? number_format($invoiceTotal / $statisticsorder) : '0';
    $monthe_buy = number_format(forecast_monthly_paid_income($pdo));
    $monthe_volume = bot_format_gb(forecast_monthly_sold_volume($pdo) ?? 0);
    $percent_of_extend = $invoiceTotal > 0 ? round(($extendsum / $invoiceTotal) * 100, 2) : 0;
    $percent_of_extend = $percent_of_extend > 100 ? 100 : $percent_of_extend;
    $firstPurchaseStats = bot_first_purchase_stats($pdo);
    $firstPurchaseSum = (float) ($firstPurchaseStats['sum'] ?? 0);
    $repeatPurchaseSum = max(0.0, $invoicePaidSum - $firstPurchaseSum);
    $percent_of_loyalty = $invoiceTotal > 0
        ? round((($repeatPurchaseSum + $extendsum) / $invoiceTotal) * 100, 2)
        : 0;
    $percent_of_loyalty = $percent_of_loyalty > 100 ? 100 : $percent_of_loyalty;
    $extendsum = number_format($extendsum, 0);
    $avgJoinBuy = avg_join_to_first_purchase_label($pdo);
    $soldVolumeText = bot_format_sold_volume_block(bot_sold_volume_stats($pdo), true);
    $firstPurchaseText = bot_format_first_purchase_block($firstPurchaseStats, (int) $invoice, $invoicePaidSum, true);
    $autoRenewStats = invoice_auto_renew_stats($pdo);
    $autoRenewUsers = $autoRenewStats['users'];
    $autoRenewServices = $autoRenewStats['services'];
    if (count($statispay) != 0) {
        foreach ($statispay as $tracepay) {
            $status_var = [
                'cart to cart' => $datatextbot['carttocart'],
                'aqayepardakht' => $datatextbot['aqayepardakht'],
                'zarinpal' => $datatextbot['zarinpal'],
                'plisio' => $datatextbot['textnowpayment'],
                'arze digital offline' => $datatextbot['textnowpaymenttron'],
                'Currency Rial 1' => $datatextbot['iranpay2'],
                'Currency Rial 2' => $datatextbot['iranpay3'],
                'Currency Rial 3' => $datatextbot['iranpay1'],
                'paymentnotverify' => $datatextbot['textpaymentnotverify'],
                'Star Telegram' => $datatextbot['text_star_telegram'],
                'tetraminator' => $datatextbot['tetraminator'] ?? 'Tetraminator',
                'add order by admin' => 'Order by admin',
                'extend by admin' => 'Renewal by admin',
                'capital_injection' => 'Capital injection',

            ][$tracepay['Payment_Method']] ?? ($tracepay['Payment_Method'] ?: 'Other');
            $paycount .= "\n📌 Gateway name: <code>$status_var</code>\n - Successful payments: <code>{$tracepay['countpay']}</code>\n - Total paid: <code>{$tracepay['sumpay']}</code>\n";
        }
    }
    $statisticsall = "📊 <b>Bot overall stats</b>
━━━━━━━━━━━━━━━━━━
👥 <b>Total users:</b> <code>$statistics</code>
👤 <b>Users with an account:</b> <code>$count_users_account</code>
💳 <b>Users who purchased:</b> <code>$statisticsorder</code>
🧪 <b>Test accounts:</b> <code>$count_usertest</code>
🧪 <b>Users with test and no purchase:</b> <code>$count_users_test_no_purchase</code>
🧪💳 <b>Users with test and a purchase:</b> <code>$count_users_test_and_purchase</code>
💰 <b>Total user balances:</b> <code>$Balanceall</code> USD

🧾 <b>Total sales:</b> <code>$invoice</code>
$firstPurchaseText
🧾 <b>Total active service sales:</b> <code>$invoiceactive</code>
💸 <b>Wallet withdrawals:</b> <code>$withdrawCountAll</code>
💰 <b>Wallet withdrawal amount:</b> <code>$withdrawSumAllFmt</code> USD
💵 <b>Total revenue (successful payments):</b> <code>$invoicesumall</code> USD
💵 <b>Total active service sales amount:</b> <code>$invoicesum</code> USD
🔄 <b>Total renewals:</b> <code>$extendsum</code> USD
♻️ <b>Users with auto-renew:</b> <code>$autoRenewUsers</code>
♻️ <b>Auto-renew services:</b> <code>$autoRenewServices</code>
$soldVolumeText

📈 <b>Customer conversion rate:</b> <code>$ratecustomer</code>%
🧪 <b>Test claim rate:</b> <code>$ratetest</code>%
💳 <b>Average purchase per customer:</b> <code>$avgbuy_customer</code> USD
⏱ <b>Average time from join to first purchase:</b> <code>$avgJoinBuy</code>
📅 <b>Projected monthly revenue:</b> <code>$monthe_buy</code> USD
🔋 <b>Projected monthly volume sold:</b> <code>$monthe_volume</code> GB
📊 <b>Renewal share of sales:</b> <code>$percent_of_extend</code>%
💚 <b>Loyalty rate:</b> <code>$percent_of_loyalty</code>%


👨‍💼 <b>Total agents:</b> <code>$agentsum</code>
🔹 <b>N agents:</b> <code>$agentsumn</code>
🔸 <b>N2 agents:</b> <code>$agentsumn2</code>
🧩 <b>Panels:</b> <code>$sumpanel</code>
$paycount
";
    if ($datain == "stat_all_bot") {
        Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
    } else {
        sendmessage($from_id, $statisticsall, $keyboard_stat, 'HTML');
    }
} elseif ($datain == "hoursago_stat") {
    $range = stats_tehran_named_range('last_hour');
    Editmessagetext($from_id, $message_id, bot_format_period_stats(bot_period_stats($pdo, $range['start'], $range['end']), 'Stats — last 1 hour', $range['label']), $keyboard_stat, 'HTML');
} elseif ($datain == "yesterday_stat") {
    $range = stats_tehran_named_range('yesterday');
    Editmessagetext($from_id, $message_id, bot_format_period_stats(bot_period_stats($pdo, $range['start'], $range['end']), 'Stats — yesterday', $range['label']), $keyboard_stat, 'HTML');
} elseif ($datain == "today_stat") {
    $range = stats_tehran_named_range('today');
    Editmessagetext($from_id, $message_id, bot_format_period_stats(bot_period_stats($pdo, $range['start'], $range['end']), 'Stats — today', $range['label']), $keyboard_stat, 'HTML');
} elseif ($datain == "month_old_stat") {
    $range = stats_tehran_named_range('last_month');
    Editmessagetext($from_id, $message_id, bot_format_period_stats(bot_period_stats($pdo, $range['start'], $range['end']), 'Stats — last month', $range['label']), $keyboard_stat, 'HTML');
} elseif ($datain == "month_current_stat") {
    $range = stats_tehran_named_range('this_month');
    Editmessagetext($from_id, $message_id, bot_format_period_stats(bot_period_stats($pdo, $range['start'], $range['end']), 'Stats — current month', $range['label']), $keyboard_stat, 'HTML');
} elseif ($datain == "view_stat_time") {
    sendmessage($from_id, sprintf($textbotlang['Admin']['getstats'], jalali_tehran_format(time(), 'Y/m/d')), $backadmin, 'HTML');
    step("get_time_start", $from_id);
} elseif ($user['step'] == "get_time_start") {
    if (!isValidJalaliDate($text)) {
        sendmessage($from_id, "Invalid Jalali date. Example: <code>" . jalali_tehran_format(time(), 'Y/m/d') . "</code>", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "start_time", tr_num($text, 'en'));
    sendmessage($from_id, "Send the Jalali end date, for example:\n<code>" . jalali_tehran_format(time(), 'Y/m/d') . "</code>", $backadmin, 'HTML');
    step("get_time_end", $from_id);
} elseif ($user['step'] == "get_time_end") {
    if (!isValidJalaliDate($text)) {
        sendmessage($from_id, "Invalid Jalali date. Example: <code>" . jalali_tehran_format(time(), 'Y/m/d') . "</code>", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $start_time_timestamp = jalali_tehran_parse((string) ($userdata['start_time'] ?? ''));
    $end_time_timestamp = jalali_tehran_parse($text, true);
    if ($start_time_timestamp === null || $end_time_timestamp === null) {
        sendmessage($from_id, "Invalid Jalali date.", $backadmin, 'HTML');
        return;
    }
    if ($start_time_timestamp > $end_time_timestamp) {
        sendmessage($from_id, "Start date must be before end date.", $backadmin, 'HTML');
        return;
    }
    $range_label = jalali_tehran_format($start_time_timestamp, 'Y/m/d H:i:s') . ' to ' . jalali_tehran_format($end_time_timestamp, 'Y/m/d H:i:s');
    step('home', $from_id);
    sendmessage($from_id, bot_format_period_stats(bot_period_stats($pdo, $start_time_timestamp, $end_time_timestamp), 'Stats for selected dates', $range_label), $keyboardadmin, 'HTML');
} elseif ($datain == "settingaffiliatesf") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $affiliates, 'HTML');
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['gettypepanel'], $keyboardtypepanel, 'HTML');
} elseif (preg_match('/typepanel#(.*)/', $datain, $dataget)) {
    $typepanel = $dataget[1];
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $backadmin, 'HTML');
    step("add_name_panel", $from_id);
    deletemessage($from_id, $message_id);
    savedata("clear", "type", $typepanel);
} elseif ($user['step'] == "add_name_panel") {
    if (rowExists('marzban_panel', 'name_panel', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    savedata("save", "namepanel", $text);
    if ($userdata['type'] == "Manualsale") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "url_panel", "null");
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
    step('add_link_panel', $from_id);
} elseif ($user['step'] == "add_link_panel") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "url_panel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    if ($userdata['type'] == "hiddify") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    } elseif ($userdata['type'] == "s_ui" || $userdata['type'] == "WGDashboard") {
        sendmessage($from_id, "📌 Send the token", $backadmin, 'HTML');
        step('add_password_panel', $from_id);
        savedata("save", "username", "null");
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
    step('add_username_panel', $from_id);
} elseif ($user['step'] == "add_username_panel") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_password_panel', $from_id);
    savedata("save", "username", $text);
} elseif ($user['step'] == "add_password_panel") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
    savedata("save", "password", $text);
} elseif ($user['step'] == "getlimitedpanel") {
    savedata("save", "limitpanel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    if ($userdata['type'] == "x-ui_single" || $userdata['type'] == "alireza") {
        $marzbanprotocol = $randomString;
        $protocols = "vmess";
        $settingpanel = json_encode(array(
            'network' => 'ws',
            'security' => 'none',
            'externalProxy' => array(),
            'wsSettings' => array(
                'acceptProxyProtocol' => false,
                'path' => '/',
                'host' => '',
                'headers' => array()

            ),
        ));
    }
    $sublink = "onsublink";
    $configstatus = "offconfig";
    $MethodUsername = "آیدی عددی + حروف و عدد رندوم";
    $status = "active";
    $ONTestAccount = "ONTestAccount";
    $extendtextadd = "ریست حجم و زمان";
    $namecustoms = "none";
    $type = "marzban";
    $conecton = "offconecton";
    $inboundid = 1;
    $agent = "all";
    $time = "1";
    $valume = "100";
    $changeloc = "offchangeloc";
    $value = json_encode(array(
        'f' => "4000",
        'n' => "4000",
        'n2' => "4000"
    ));
    $valuemain = json_encode(array(
        'f' => "1",
        'n' => "1",
        'n2' => "1"
    ));
    $valuemax = json_encode(array(
        'f' => "1000",
        'n' => "1000",
        'n2' => "1000"
    ));
    $VALUE = json_encode(array(
        'f' => '0',
        'n' => '0',
        'n2' => '0'
    ));
    $valuestatusin = "offinbounddisable";
    $statusextend = "on_extend";
    $subvip = "offsubvip";
    $stauts_on_holed = "1";
    $stmt = $pdo->prepare("INSERT INTO marzban_panel (code_panel,name_panel,sublink,config,MethodUsername,TestAccount,status,limit_panel,namecustom,Methodextend,type,conecton,inboundid,agent,inbound_deactive,inboundstatus,url_panel,username_panel,password_panel,time_usertest,val_usertest,linksubx,priceextravolume,priceextratime,pricecustomvolume,pricecustomtime,mainvolume,maxvolume,maintime,maxtime,status_extend,subvip,changeloc,customvolume,on_hold_test,version_panel) VALUES (:code_panel,:name_panel,:sublink,:config,:MethodUsername,:TestAccount,:status,:limit_panel,:namecustom,:Methodextend,:type,:conecton,:inboundid,:agent,:inbound_deactive,:inboundstatus,:url_panel,:username_panel,:password_panel,:val_usertest,:time_usertest,:linksubx,:priceextravolume,:priceextratime,:pricecustomvolume,:pricecustomtime,:mainvolume,:maxvolume,:maintime,:maxtime,:status_extend,:subvip,:changeloc,:customvolume,:on_hold_test,'0')");
    $stmt->bindParam(':code_panel', $randomString);
    $stmt->bindParam(':name_panel', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->bindParam(':sublink', $sublink);
    $stmt->bindParam(':config', $configstatus);
    $stmt->bindParam(':MethodUsername', $MethodUsername);
    $stmt->bindParam(':TestAccount', $ONTestAccount);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':limit_panel', $text);
    $stmt->bindParam(':namecustom', $namecustoms);
    $stmt->bindParam(':Methodextend', $extendtextadd);
    $stmt->bindParam(':type', $userdata['type'], PDO::PARAM_STR);
    $stmt->bindParam(':conecton', $conecton);
    $stmt->bindParam(':inboundid', $inboundid);
    $stmt->bindParam(':agent', $agent);
    $stmt->bindParam(':inbound_deactive', $inboundid);
    $stmt->bindParam(':inboundstatus', $valuestatusin);
    $stmt->bindParam(':url_panel', $userdata['url_panel']);
    $stmt->bindParam(':linksubx', $userdata['url_panel']);
    $stmt->bindParam(':username_panel', $userdata['username']);
    $stmt->bindParam(':password_panel', $userdata['password']);
    $stmt->bindParam(':val_usertest', $valume);
    $stmt->bindParam(':time_usertest', $time);
    $stmt->bindParam(':priceextravolume', $value);
    $stmt->bindParam(':priceextratime', $value);
    $stmt->bindParam(':pricecustomtime', $value);
    $stmt->bindParam(':pricecustomvolume', $value);
    $stmt->bindParam(':mainvolume', $valuemain);
    $stmt->bindParam(':maxvolume', $valuemax);
    $stmt->bindParam(':maintime', $valuemain);
    $stmt->bindParam(':maxtime', $valuemax);
    $stmt->bindParam(':status_extend', $statusextend);
    $stmt->bindParam(':subvip', $subvip);
    $stmt->bindParam(':changeloc', $changeloc);
    $stmt->bindParam(':customvolume', $VALUE);
    $stmt->bindParam(':on_hold_test', $stauts_on_holed);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
    sendmessage($from_id, "🥳", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if ($userdata['type'] == "x-ui_single" or $userdata['type'] == "alireza_single") {
        sendmessage($from_id, "❌ Note:
To enable the panel, go to panel management and set
inbound ID and subscription link domain, otherwise no config will be created", null, 'HTML');
    } elseif ($userdata['type'] == "marzban") {
        sendmessage($from_id, "❌ Note:
To enable the panel, go to panel management and set
protocol and inbound so the bot can issue configs, otherwise the user will not receive a config", null, 'HTML');
    } elseif ($userdata['type'] == "WGDashboard") {
        sendmessage($from_id, "❌ Note:
To enable the panel, go to panel management and open
set inbound ID, then set the config name, otherwise the bot will not create any config", null, 'HTML');
    } elseif ($userdata['type'] == "ibsng") {
        sendmessage($from_id, "❌ Note:
To enable it, from panel management > set group name, send a default group name that you defined in ibsng.", null, 'HTML');
    } elseif ($userdata['type'] == "mikrotik") {
        sendmessage($from_id, "❌ Note:
1 - The accounting plugin must be installed on your MikroTik
2 - In ip » services » http or https must be enabled (if you have SSL, enable https, otherwise http)", null, 'HTML');
    } elseif ($userdata['type'] == "hiddify") {
        sendmessage($from_id, "❌ Note:
1 - From panel management set the following

1 - uuid admin: get the admin UUID from the panel and save it
2- Subscription link domain: send the Hiddify panel subscription link domain", null, 'HTML');
    } elseif ($userdata['type'] == "s_ui") {
        sendmessage($from_id, "❌ Note:
1 - From panel management > ⚙️ set protocol and inbound, send a config username.", null, 'HTML');
    }
}
//_____________________[ message ]____________________________//
elseif ($datain == "systemsms") {
    if (is_file('cronbot/gift')) {
        sendmessage($from_id, "❌ Bulk service charge is currently running. Send a new message after it finishes.", $keyboardadmin, 'HTML');
        return;
    }
    if (is_file('cronbot/users.json')) {
        $userslist = json_decode(file_get_contents('cronbot/users.json'), true);
        if (is_array($userslist) and count($userslist) != 0) {
            sendmessage($from_id, "❌ The broadcast system is currently running. After it finishes and you are notified, you can send a new message.", $keyboardadmin, 'HTML');
            return;
        }
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Broadcast", 'callback_data' => 'typeservice-sendmessage'],
            ],
            [
                ['text' => "Forward to all", 'callback_data' => 'typeservice-forwardmessage'],
            ],
            [
                ['text' => "Days since last use", 'callback_data' => 'typeservice-xdaynotmessage'],
            ],
            [
                ['text' => "Unpin messages", 'callback_data' => 'typeservice-unpinmessage'],
            ],
            [
                ['text' => "Back to main menu", 'callback_data' => 'backlistuser'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $listbtn);
} elseif (preg_match('/^typeservice-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    savedata("clear", "typeservice", $type);
    if ($type == "unpinmessage") {
        deletemessage($from_id, $message_id);
        $typesend = [
            "unpinmessage" => "Unpin message"
        ][$type];
        $textconfirm = "📌 You are about to run a broadcast. Review the details below and confirm to start.
⚙️ Operation type: $typesend";
        $startaction = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Confirm and start", 'callback_data' => 'startaction'],
                ],
            ]
        ]);
        sendmessage($from_id, $textconfirm, $startaction, 'HTML');
        sendmessage($from_id, "Confirming the option above will start the process", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $usergroup_rows = [
        [
            ['text' => "All users", 'callback_data' => 'typeusermessage-all'],
        ],
        [
            ['text' => "Customers who purchased", 'callback_data' => 'typeusermessage-customer'],
        ],
        [
            ['text' => "Users who tested but did not purchase", 'callback_data' => 'typeusermessage-testonly'],
        ],
        [
            ['text' => "Users with no test and no purchase", 'callback_data' => 'typeusermessage-notestnopurchase'],
        ],
        [
            ['text' => "Users who used over 80%", 'callback_data' => 'typeusermessage-highvolume'],
        ],
    ];
    if ($type == "sendmessage") {
        $usergroup_rows[] = [
            ['text' => "Post to channel", 'callback_data' => 'typeusermessage-channelpost'],
        ];
    }
    $usergroup_rows[] = [
        ['text' => "Back to previous menu", 'callback_data' => 'systemsms'],
    ];
    $listbtn = json_encode(['inline_keyboard' => $usergroup_rows]);
    Editmessagetext($from_id, $message_id, "📌 Which user group should this apply to?", $listbtn);
} elseif (preg_match('/^typeusermessage-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typeusermessage", $dataget[1]);
    if ($dataget[1] == "channelpost") {
        if ($userdata['typeservice'] != "sendmessage") {
            sendmessage($from_id, "❌ Posting to the channel is only available for broadcasts.", $keyboardadmin, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        ensure_channel_post_setting_column();
        $default_channel = normalize_channel_post_input($setting['Channel_Post'] ?? '');
        if ($default_channel !== '') {
            $resolved = resolve_channel_post_target($default_channel);
            if ($resolved['ok']) {
                savedata("save", "channel_id", $resolved['channel_id']);
                savedata("save", "channel_title", $resolved['channel_title']);
                $listbtn = broadcast_btn_picker_keyboard('channelpostbtn', [
                    [
                        ['text' => "Back to previous menu", 'callback_data' => 'typeservice-sendmessage'],
                    ],
                ]);
                step("home", $from_id);
                $channel_title = htmlspecialchars($resolved['channel_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                sendmessage($from_id, "✅ Default channel <b>{$channel_title}</b> confirmed.\n\n📌 Choose the button shown under the post:", $listbtn, 'HTML');
                return;
            }
            step("getchannelpostid", $from_id);
            sendmessage($from_id, channel_post_resolve_error_message($resolved['error']) . "\n\n📌 The default channel in the panel is invalid. Send the numeric ID or username.\n\nExample:\n<code>@mychannel</code>\nor\n<code>-1001234567890</code>\n\n⚠️ The bot must be a channel admin with permission to post messages.", $backadmin, 'HTML');
            return;
        }
        step("getchannelpostid", $from_id);
        sendmessage($from_id, "📌 Send the numeric ID or username of the channel.

Example:
<code>@mychannel</code>
or
<code>-1001234567890</code>

⚠️ The bot must be a channel admin with permission to post messages.
💡 You can save a default channel in the admin panel → settings so you are not asked every time.", $backadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "sendmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Text only", 'callback_data' => 'messagemediatype-text'],
                ],
                [
                    ['text' => "Send with photo", 'callback_data' => 'messagemediatype-photo'],
                ],
                [
                    ['text' => "Back to previous menu", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 Choose the message type:", $listbtn);
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All users", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "Group f users", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "Group n users", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "Group n2 users", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "Back to previous menu", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Which user category should this apply to?", $listbtn);
} elseif ($user['step'] == "getchannelpostid") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || ($userdata['typeusermessage'] ?? '') != "channelpost") {
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $resolved = resolve_channel_post_target($text);
    if (!$resolved['ok']) {
        sendmessage($from_id, channel_post_resolve_error_message($resolved['error']), $backadmin, 'HTML');
        return;
    }
    savedata("save", "channel_id", $resolved['channel_id']);
    savedata("save", "channel_title", $resolved['channel_title']);
    $listbtn = broadcast_btn_picker_keyboard('channelpostbtn', [
        [
            ['text' => "Back to previous menu", 'callback_data' => 'typeservice-sendmessage'],
        ],
    ]);
    step("home", $from_id);
    sendmessage($from_id, "✅ Channel <b>" . htmlspecialchars($resolved['channel_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b> confirmed.\n\n📌 Choose the button shown under the post:", $listbtn, 'HTML');
} elseif (preg_match('/^channelpostbtn-(\w+)/', $datain, $dataget)) {
    $btn_type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || ($userdata['typeusermessage'] ?? '') != "channelpost") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    if (!in_array($btn_type, broadcast_attachable_button_keys(), true)) {
        sendmessage($from_id, "❌ Invalid option", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "btntypemessage", $btn_type);
    savedata("save", "btntextmessage", "");
    deletemessage($from_id, $message_id);
    if ($btn_type === 'none') {
        step("gettextChannelPost", $from_id);
        sendmessage($from_id, "📌 Send the channel post content.\nYou can send plain text or a photo with a caption.", $backadmin, 'HTML');
        return;
    }
    broadcast_ask_btn_title_step($from_id, $btn_type);
} elseif ($datain == "btntextdefault") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || empty($userdata['btntypemessage']) || ($userdata['btntypemessage'] ?? 'none') === 'none') {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    $default = broadcast_btn_label($userdata['btntypemessage'], $datatextbot);
    savedata("save", "btntextmessage", $default);
    deletemessage($from_id, $message_id);
    broadcast_continue_after_btn_title($from_id);
} elseif ($user['step'] == "getbtntextmessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || empty($userdata['btntypemessage']) || ($userdata['btntypemessage'] ?? 'none') === 'none') {
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    if (!$text || trim($text) === '') {
        sendmessage($from_id, "📌 Please send the button title as text.", $backadmin, 'HTML');
        return;
    }
    $btn_title = trim($text);
    if (mb_strlen($btn_title) > 64) {
        sendmessage($from_id, "❌ Button title must not be longer than 64 characters.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "btntextmessage", $btn_title);
    broadcast_continue_after_btn_title($from_id);
} elseif ($user['step'] == "gettextChannelPost") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || ($userdata['typeusermessage'] ?? '') != "channelpost") {
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $message = is_array($update) ? ($update['message'] ?? null) : null;
    $raw_text = '';
    $raw_entities = [];
    if ($photo) {
        $raw_text = is_array($message) ? (string) ($message['caption'] ?? '') : '';
        $raw_entities = (is_array($message) && isset($message['caption_entities']) && is_array($message['caption_entities']))
            ? $message['caption_entities']
            : [];
        savedata("save", "messagemediatype", "photo");
        savedata("save", "photoid", $photoid);
    } elseif (is_array($message) && isset($message['text']) && $message['text'] !== '') {
        $raw_text = (string) $message['text'];
        $raw_entities = (isset($message['entities']) && is_array($message['entities'])) ? $message['entities'] : [];
        savedata("save", "messagemediatype", "text");
        savedata("save", "photoid", "");
    } else {
        sendmessage($from_id, "📌 Please send text or a photo (optional caption).", $backadmin, 'HTML');
        return;
    }
    savedata("save", "source_message_id", $message_id);
    savedata("save", "source_chat_id", $from_id);
    savedata("save", "message_raw", $raw_text);
    savedata("save", "message_entities", normalize_outgoing_telegram_entities($raw_entities));
    savedata("save", "message", text_from_telegram_update($update));
    $userdata = json_decode(select("user", "*", "id", $from_id, "select")['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }
    $btn_type_selected = $userdata['btntypemessage'] ?? 'none';
    $btn_title_show = ($btn_type_selected === 'none')
        ? 'No button'
        : broadcast_resolve_btn_text($btn_type_selected, $userdata['btntextmessage'] ?? '', $datatextbot);
    $media_label = (($userdata['messagemediatype'] ?? 'text') == 'photo') ? 'Photo + text' : 'Text only';
    $channel_title = htmlspecialchars($userdata['channel_title'] ?? $userdata['channel_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $btn_title_safe = htmlspecialchars($btn_title_show, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $preview_keyboard = null;
    if ($btn_type_selected !== 'none') {
        global $usernamebot;
        $preview_keyboard = broadcast_inline_keyboard($btn_type_selected, $btn_title_show, [
            'url' => "https://t.me/{$usernamebot}",
        ]);
    }
    sendmessage($from_id, "👁 <b>Post preview</b>\nThe message below is what will be published after confirmation:", null, 'HTML');
    send_channel_post_preview($from_id, $userdata, $preview_keyboard);
    $textconfirm = "📌 You are about to post to the channel. Confirm to publish.

⚙️ Operation type: Post to channel
📢 Channel: {$channel_title}
🔘 Button: {$btn_title_safe}
📝 Content type: {$media_label}";
    $startaction = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Confirm and send to channel", 'callback_data' => 'startaction'],
            ],
        ]
    ]);
    sendmessage($from_id, $textconfirm, $startaction, 'HTML');
    sendmessage($from_id, "Confirming the option above will publish the post in the channel", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "choosemessagemedia") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || $userdata['typeservice'] != "sendmessage") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Text only", 'callback_data' => 'messagemediatype-text'],
            ],
            [
                ['text' => "Send with photo", 'callback_data' => 'messagemediatype-photo'],
            ],
            [
                ['text' => "Back to previous menu", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Choose the message type:", $listbtn);
} elseif (preg_match('/^messagemediatype-(\w+)/', $datain, $dataget)) {
    $mediatype = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice']) || $userdata['typeservice'] != "sendmessage") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    if (!in_array($mediatype, ['text', 'photo'], true)) {
        sendmessage($from_id, "❌ Invalid option", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "messagemediatype", $mediatype);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All users", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "Group f users", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "Group n users", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "Group n2 users", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "Back to previous menu", 'callback_data' => 'choosemessagemedia'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Which user category should this apply to?", $listbtn);
} elseif ($datain == "showagentfilters") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    $backcb = ($userdata['typeservice'] == "sendmessage")
        ? 'choosemessagemedia'
        : ('typeservice-' . $userdata['typeservice']);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All users", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "Group f users", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "Group n users", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "Group n2 users", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "Back to previous menu", 'callback_data' => $backcb],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Which user category should this apply to?", $listbtn);
} elseif (preg_match('/^typeagent-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $type);
    $back_to_agent_parent = ($userdata['typeservice'] == "sendmessage")
        ? 'showagentfilters'
        : ('typeusermessage-' . $userdata['typeusermessage']);
    if ($userdata['typeusermessage'] == "customer" || $userdata['typeusermessage'] == "highvolume") {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
        $stmt->bindParam(':agent', $type);
        $stmt->execute();
        $list_panel = ['inline_keyboard' => []];
        $list_panel['inline_keyboard'][] = [['text' => "All panels", 'callback_data' => 'locationmessage_all']];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_panel['inline_keyboard'][] = [
                ['text' => $result['name_panel'], 'callback_data' => "locationmessage_{$result['code_panel']}"]
            ];
        }
        $list_panel['inline_keyboard'][] = [['text' => "Back to previous menu", 'callback_data' => $back_to_agent_parent],];
        Editmessagetext($from_id, $message_id, "📌 Which users in the panels below should receive the message.", json_encode($list_panel));
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Yes", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "No", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "Back to previous menu", 'callback_data' => $back_to_agent_parent],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 Do you want the sent message to be pinned?", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        sendmessage($from_id, "📌 This feature messages users who have not used the bot for a number of days you specify.
Send the number of days.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 Send your message text.", $backadmin, 'HTML');
} elseif (preg_match('/^locationmessage_(\w+)/', $datain, $dataget)) {
    $typeoanel = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "selectpanel", $typeoanel);
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Yes", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "No", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "Back to previous menu", 'callback_data' => 'typeagent-' . $userdata['agent']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 Do you want the sent message to be pinned?", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        sendmessage($from_id, "📌 This feature messages users who have not used the bot for a number of days you specify.
Send the number of days.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 Send your message text.", $backadmin, 'HTML');
} elseif (preg_match('/^typepinmessage-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typepinmessage", $type);
    $listbtn = broadcast_btn_picker_keyboard('btntypemessage', [
        [
            ['text' => "Back to previous menu", 'callback_data' => 'typeagent-' . $userdata['agent']],
        ],
    ]);
    if ($userdata['typeservice'] == "forwardmessage") {
        step("gettextSystemMessage", $from_id);
        sendmessage($from_id, "📌 Send your message text.", $backadmin, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, "📌 If you want a button under the message, pick an option from the list below; otherwise tap Send without button", $listbtn);
} elseif (preg_match('/^btntypemessage-(\w+)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $type = $dataget[1];
    if (!in_array($type, broadcast_attachable_button_keys(), true)) {
        sendmessage($from_id, "❌ Invalid option", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "btntypemessage", $type);
    savedata("save", "btntextmessage", "");
    $userdata = json_decode(select("user", "*", "id", $from_id, "select")['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    if ($type === 'none') {
        if ($userdata['typeservice'] == "xdaynotmessage") {
            step("gettextday", $from_id);
            sendmessage($from_id, "📌 This feature messages users who have not used the bot for a number of days you specify.
Send the number of days.", $backadmin, 'HTML');
            return;
        }
        step("gettextSystemMessage", $from_id);
        if (($userdata['messagemediatype'] ?? 'text') == "photo") {
            sendmessage($from_id, "📌 Send your image.\nYou can also include a caption with the photo.", $backadmin, 'HTML');
        } else {
            sendmessage($from_id, "📌 Send your message text.", $backadmin, 'HTML');
        }
        return;
    }
    broadcast_ask_btn_title_step($from_id, $type);
} elseif ($user['step'] == "gettextday") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "daynoyuse", $text);
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 Send your message text.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextSystemMessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "forwardmessage") {
        savedata("save", "message", $message_id);
    } elseif ($userdata['typeservice'] == "xdaynotmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            sendmessage($from_id, "📌 For users inactive for a set number of days, only text messages are allowed.", $backadmin, 'HTML');
            return;
        }
    } elseif ($userdata['typeservice'] == "sendmessage") {
        if (($userdata['messagemediatype'] ?? 'text') == "photo") {
            if ($photo) {
                savedata("save", "photoid", $photoid);
                savedata("save", "message", $caption !== '' ? $caption : '');
            } else {
                sendmessage($from_id, "📌 Please send an image.\nYou can also include a caption with the photo.", $backadmin, 'HTML');
                return;
            }
        } elseif ($text) {
            savedata("save", "message", $text);
            savedata("save", "photoid", "");
        } else {
            sendmessage($from_id, "📌 Broadcasts only support text messages.", $backadmin, 'HTML');
            return;
        }
    }
    $typesend = [
        "xdaynotmessage" => "Users inactive for the specified number of days",
        "sendmessage" => "Broadcast",
        "forwardmessage" => "Forward to all",
        "unpinmessage" => "Unpin message"
    ][$userdata['typeservice']];
    $typeservice = [
        "all" => "Send to all users",
        "customer" => "Customers",
        "nonecustomer" => "Users who did not purchase",
        "testonly" => "Users who tested but did not purchase",
        "notestnopurchase" => "Users with no test and no purchase",
        "highvolume" => "Users who used over 80%",
    ][$userdata['typeusermessage']];
    if ($userdata['typeservice'] == "xdaynotmessage") {
        $textday = "Days since the user last messaged: {$userdata['daynoyuse']}";
    } else {
        $textday = "";
    }
    $mediatypetext = "";
    if ($userdata['typeservice'] == "sendmessage") {
        $mediatypetext = (($userdata['messagemediatype'] ?? 'text') == "photo")
            ? "🖼 Content type: send with photo"
            : "📝 Content type: text only";
    }
    $userdata = json_decode(select("user", "*", "id", $from_id, "select")['Processing_value'], true);
    $btn_type_selected = $userdata['btntypemessage'] ?? 'none';
    $btn_title_show = ($btn_type_selected === 'none')
        ? 'No button'
        : broadcast_resolve_btn_text($btn_type_selected, $userdata['btntextmessage'] ?? '', $datatextbot);
    $btn_title_safe = htmlspecialchars($btn_title_show, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $textconfirm = "📌 You are about to run a broadcast. Review the details below and confirm to start.
⚙️ Operation type: $typesend
🎛 Service type: $typeservice
🗂 User type: {$userdata['agent']}
🔘 Button title: {$btn_title_safe}
$mediatypetext
$textday
";
    $startaction = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Confirm and start", 'callback_data' => 'startaction'],
            ],
        ]
    ]);
    sendmessage($from_id, $textconfirm, $startaction, 'HTML');
    sendmessage($from_id, "Confirming the option above will start the process", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "startaction") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        sendmessage($from_id, "❌ An error occurred. Please restart the broadcast steps from the beginning", $keyboardadmin, 'HTML');
        return;
    }
    if (($userdata['typeusermessage'] ?? '') == "channelpost") {
        global $usernamebot;
        $channel_id = $userdata['channel_id'] ?? '';
        if ($channel_id === '') {
            sendmessage($from_id, "❌ Channel is not set. Restart the steps from the beginning.", $keyboardadmin, 'HTML');
            return;
        }
        $is_photo = (($userdata['messagemediatype'] ?? 'text') == 'photo') && !empty($userdata['photoid']);
        $raw = channel_post_source_text($userdata);
        $has_source = intval($userdata['source_message_id'] ?? 0) > 0;
        if (!$is_photo && $raw === '' && !$has_source) {
            sendmessage($from_id, "❌ Post text is empty.", $keyboardadmin, 'HTML');
            return;
        }
        $broadcast = log_broadcast_to_report([
            'admin_id' => $from_id,
            'type' => 'channelpost',
            'message_text' => $userdata['message'] ?? '',
            'media_type' => $userdata['messagemediatype'] ?? 'text',
            'photo_id' => $userdata['photoid'] ?? '',
            'btn_type' => $userdata['btntypemessage'] ?? 'none',
            'audience_label' => broadcast_audience_label($userdata),
            'recipient_count' => 1,
            'status' => 'started',
            'payload' => $userdata,
        ]);
        $btn_type = $userdata['btntypemessage'] ?? 'none';
        $btn_keyboard = null;
        if ($btn_type != 'none') {
            $btn_text = broadcast_resolve_btn_text($btn_type, $userdata['btntextmessage'] ?? '', $datatextbot);
            $payload = broadcast_channel_start_payload($btn_type, $broadcast['id']);
            $btn_url = "https://t.me/{$usernamebot}?start={$payload}";
            $btn_keyboard = broadcast_inline_keyboard($btn_type, $btn_text, [
                'url' => $btn_url,
            ]);
        }
        $result = publish_channel_post($channel_id, $userdata, $btn_keyboard);
        if (!isset($result['ok']) || !$result['ok']) {
            update("broadcast_log", "status", "cancelled", "id", intval($broadcast['id']));
            refresh_broadcast_report_message(intval($broadcast['id']));
            $err = $result['description'] ?? 'Unknown error';
            sendmessage($from_id, "❌ Failed to send the post to the channel:\n<code>" . htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>", $keyboardadmin, 'HTML');
            return;
        }
        update("broadcast_log", "status", "published", "id", intval($broadcast['id']));
        refresh_broadcast_report_message(intval($broadcast['id']));
        Editmessagetext($from_id, $message_id, "✅ Post published in the channel successfully.", null);
        sendmessage($from_id, "✅ Channel post sent successfully.", $keyboardadmin, 'HTML');
        return;
    }
    $agent = $userdata['agent'];
    $typeservice = $userdata['typeservice'];
    $typeusermessage = $userdata['typeusermessage'];
    $text = $userdata['message'];
    $cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Cancel operation", 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);

    $highvolume_panel = 'all';
    if ($typeusermessage == "highvolume") {
        if (!empty($userdata['selectpanel']) && $userdata['selectpanel'] != "all") {
            $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
            $highvolume_panel = $panel['name_panel'] ?? 'all';
        }
        Editmessagetext($from_id, $message_id, "⏳ Checking user volume usage (over 80%)... Please wait.", null);
        @set_time_limit(300);
        $highvolume_users = getUsersHighVolumeUsage(80, $agent, $highvolume_panel);
        if (count($highvolume_users) == 0) {
            sendmessage($from_id, "❌ No user with over 80% service volume usage was found.", $keyboardadmin, 'HTML');
            return;
        }
    }

    if ($typeservice == "unpinmessage") {
        $userlist = json_encode(select("user", "id", null, null, "fetchAll"));
        $message_id = Editmessagetext($from_id, $message_id, "✅ Operation started. You will be notified when it finishes.", $cancelmessage);
        $dataunpin = json_encode(array(
            "id_admin" => $from_id,
            'type' => "unpinmessage",
            "id_message" => $message_id['result']['message_id']
        ));
        file_put_contents("cronbot/users.json", $userlist);
        file_put_contents('cronbot/info', $dataunpin);
    } elseif ($typeservice == "sendmessage") {
        if ($typeusermessage == "highvolume") {
            $userslist = json_encode($highvolume_users);
        } elseif ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "testonly") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.User_Status = 'Active' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست')");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "notestnopurchase") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE  u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "testonly") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent = :agent AND u.User_Status = 'Active' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست')");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "notestnopurchase") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent = :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ Operation started. You will be notified when it finishes.", $cancelmessage);
        $recipient_count = is_string($userslist) ? count(json_decode($userslist, true) ?: []) : 0;
        $broadcast = log_broadcast_to_report([
            'admin_id' => $from_id,
            'type' => 'sendmessage',
            'message_text' => $userdata['message'] ?? '',
            'media_type' => $userdata['messagemediatype'] ?? 'text',
            'photo_id' => $userdata['photoid'] ?? '',
            'btn_type' => $userdata['btntypemessage'] ?? 'none',
            'audience_label' => broadcast_audience_label($userdata),
            'recipient_count' => $recipient_count,
            'status' => 'started',
            'payload' => $userdata,
        ]);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "messagemediatype" => $userdata['messagemediatype'] ?? 'text',
            "photoid" => $userdata['photoid'] ?? '',
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage'],
            "btntextmessage" => $userdata['btntextmessage'] ?? '',
            "broadcast_id" => intval($broadcast['id']),
        ));
        file_put_contents("cronbot/users.json", $userslist);
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "forwardmessage") {
        if ($typeusermessage == "highvolume") {
            $userslist = json_encode($highvolume_users);
        } elseif ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "testonly") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.User_Status = 'Active' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست')");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "notestnopurchase") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "testonly") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent = :agent AND u.User_Status = 'Active' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست')");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "notestnopurchase") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent = :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ Operation started. You will be notified when it finishes.", $cancelmessage);
        $recipient_count = is_string($userslist) ? count(json_decode($userslist, true) ?: []) : 0;
        $broadcast = log_broadcast_to_report([
            'admin_id' => $from_id,
            'type' => 'forwardmessage',
            'message_text' => 'Forward message #' . ($userdata['message'] ?? ''),
            'media_type' => 'text',
            'photo_id' => '',
            'btn_type' => 'none',
            'audience_label' => broadcast_audience_label($userdata),
            'recipient_count' => $recipient_count,
            'status' => 'started',
            'payload' => $userdata,
        ]);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "forwardmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "broadcast_id" => intval($broadcast['id']),
        ));
        file_put_contents("cronbot/users.json", $userslist);
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "xdaynotmessage") {
        $timedaystamp = intval($userdata['daynoyuse']) * 86400;
        $timenouser = time() - $timedaystamp;
        if ($typeusermessage == "highvolume") {
            $ids = array_column($highvolume_users, 'id');
            if (count($ids) == 0) {
                sendmessage($from_id, "❌ No user with over 80% service volume usage was found.", $keyboardadmin, 'HTML');
                return;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id FROM user WHERE last_message_time < ? AND id IN ($placeholders)");
            $stmt->execute(array_merge([$timenouser], $ids));
            $userslist = json_encode($stmt->fetchAll());
        } elseif ($agent == "all") {
            if ($typeusermessage == "customer") {
                if (($userdata['selectpanel'] ?? 'all') == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id)");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}')");
                }
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id)");
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "testonly") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست')");
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "notestnopurchase") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id)");
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } else {
                $stmt = $pdo->prepare("SELECT id FROM user WHERE last_message_time < :time");
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                if ($typeusermessage == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "customer") {
                    if ($userdata['selectpanel'] == "all") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    } else {
                        $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                    }
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "nonecustomer") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "testonly") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست');");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                }
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "testonly") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent = :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product = 'سرویس تست') AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.name_product != 'سرویس تست');");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "notestnopurchase") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent = :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ Operation started. You will be notified when it finishes.", $cancelmessage);
        $recipient_count = is_string($userslist) ? count(json_decode($userslist, true) ?: []) : 0;
        $broadcast = log_broadcast_to_report([
            'admin_id' => $from_id,
            'type' => 'xdaynotmessage',
            'message_text' => $userdata['message'] ?? '',
            'media_type' => 'text',
            'photo_id' => '',
            'btn_type' => $userdata['btntypemessage'] ?? 'none',
            'audience_label' => broadcast_audience_label($userdata),
            'recipient_count' => $recipient_count,
            'status' => 'started',
            'payload' => $userdata,
        ]);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "xdaynotmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage'],
            "btntextmessage" => $userdata['btntextmessage'] ?? '',
            "broadcast_id" => intval($broadcast['id']),
        ));
        file_put_contents("cronbot/users.json", $userslist);
        file_put_contents('cronbot/info', $data);
    }
} elseif ($datain == "cancel_sendmessage") {
    ensure_broadcast_schema();
    $info_raw = @file_get_contents(__DIR__ . '/cronbot/info');
    if ($info_raw) {
        $info_cancel = json_decode($info_raw, true);
        if (!empty($info_cancel['broadcast_id'])) {
            update("broadcast_log", "status", "cancelled", "id", intval($info_cancel['broadcast_id']));
            refresh_broadcast_report_message(intval($info_cancel['broadcast_id']));
        }
    }
    file_put_contents('users.json', json_encode(array()));
    unlink('cronbot/users.json');
    unlink('cronbot/info');
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 Broadcast cancelled.", null, 'HTML');
}
//_____________________[ text ]____________________________//
elseif ($text == "📝 Bot text settings" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $textbot, 'HTML');
} elseif (($text == "🛒 Purchase flow texts" || $datain == "purchase_texts_back") && $adminrulecheck['rule'] == "administrator") {
    if ($datain == "purchase_texts_back") {
        deletemessage($from_id, $message_id);
    }
    sendmessage($from_id, "🛒 Choose purchase flow texts:\n✨ For a premium emoji, send the text together with the premium emoji.", $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "🔙 Back to text settings" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $textbot, 'HTML');
    step('home', $from_id);
} elseif ($text == "Category selection text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_category_select', 'purchasetext_category_select', $backadmin, "💡 If the panel description is empty, this text is shown.\nNo variables.");
} elseif ($user['step'] == "purchasetext_category_select") {
    if (!save_textbot_from_update('text_category_select', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Service selection text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_service_select', 'purchasetext_service_select', $backadmin);
} elseif ($user['step'] == "purchasetext_service_select") {
    if (!save_textbot_from_update('text_service_select', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Service selection text (first)" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_service_select_first', 'purchasetext_service_select_first', $backadmin, "💡 Used inside a category (if the category description is empty) and for the first product list.");
} elseif ($user['step'] == "purchasetext_service_select_first") {
    if (!save_textbot_from_update('text_service_select_first', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Duration selection text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_month_select', 'purchasetext_month_select', $backadmin);
} elseif ($user['step'] == "purchasetext_month_select") {
    if (!save_textbot_from_update('text_month_select', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Purchase note text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_sell_notestep', 'purchasetext_sell_notestep', $backadmin);
} elseif ($user['step'] == "purchasetext_sell_notestep") {
    if (!save_textbot_from_update('text_sell_notestep', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Custom volume request text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_custom_volume_ask', 'purchasetext_custom_volume_ask', $backadmin, "Variables:\n<code>{price}</code> price per GB\n<code>{min}</code> minimum volume\n<code>{max}</code> maximum volume");
} elseif ($user['step'] == "purchasetext_custom_volume_ask") {
    if (!save_textbot_from_update('text_custom_volume_ask', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Custom duration selection text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_custom_month_ask', 'purchasetext_custom_month_ask', $backadmin);
} elseif ($user['step'] == "purchasetext_custom_month_ask") {
    if (!save_textbot_from_update('text_custom_month_ask', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Invalid volume text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_custom_volume_invalid', 'purchasetext_custom_volume_invalid', $backadmin, "Variables:\n<code>{min}</code> minimum volume\n<code>{max}</code> maximum volume");
} elseif ($user['step'] == "purchasetext_custom_volume_invalid") {
    if (!save_textbot_from_update('text_custom_volume_invalid', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Username selection text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_select_username', 'purchasetext_select_username', $backadmin);
} elseif ($user['step'] == "purchasetext_select_username") {
    if (!save_textbot_from_update('text_select_username', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Panel description (after select)" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Choose the panel whose description you want to edit:", keyboard_panels_purchase_text_edit('editpaneldesc_'), 'HTML');
} elseif (preg_match('/^editpaneldesc_(.+)$/', (string) $datain, $m) && $adminrulecheck['rule'] == "administrator") {
    $panel = select('marzban_panel', '*', 'code_panel', $m[1], 'select');
    if (!$panel || !is_array($panel)) {
        sendmessage($from_id, '❌ Panel not found.', $textbot_purchase, 'HTML');
        return;
    }
    update('user', 'Processing_value', $m[1], 'id', $from_id);
    $current = trim((string) ($panel['description'] ?? ''));
    sendmessage($from_id, "📝 Send the new description for panel «{$panel['name_panel']}»:\n💡 Leave it empty and send <code>-</code> to clear it and use the default category text.", $backadmin, 'HTML');
    if ($current !== '') {
        sendmessage($from_id, $current, null, 'HTML');
    } else {
        sendmessage($from_id, "⚠️ No description is set yet.", null, 'HTML');
    }
    step('edit_panel_description', $from_id);
} elseif ($user['step'] == "edit_panel_description") {
    $code = (string) ($user['Processing_value'] ?? '');
    $panel = select('marzban_panel', '*', 'code_panel', $code, 'select');
    if (!$panel || !is_array($panel)) {
        sendmessage($from_id, '❌ Panel not found.', $textbot_purchase, 'HTML');
        step('home', $from_id);
        return;
    }
    if (trim((string) $text) === '-') {
        update('marzban_panel', 'description', '', 'code_panel', $code);
        sendmessage($from_id, '✅ Panel description cleared.', $textbot_purchase, 'HTML');
        step('home', $from_id);
        return;
    }
    $html = text_from_telegram_update($update);
    if ($html === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    update('marzban_panel', 'description', $html, 'code_panel', $code);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Custom service button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Choose the panel whose custom service button text you want to edit:", keyboard_panels_purchase_text_edit('editpanelcustombtn_'), 'HTML');
} elseif (preg_match('/^editpanelcustombtn_(.+)$/', (string) $datain, $m) && $adminrulecheck['rule'] == "administrator") {
    $panel = select('marzban_panel', '*', 'code_panel', $m[1], 'select');
    if (!$panel || !is_array($panel)) {
        sendmessage($from_id, '❌ Panel not found.', $textbot_purchase, 'HTML');
        return;
    }
    update('user', 'Processing_value', $m[1], 'id', $from_id);
    $current = panel_custom_button_text($panel);
    $emojiId = panel_custom_button_emoji_id($panel);
    sendmessage($from_id, "📝 Send the custom service button text for panel «{$panel['name_panel']}»:\n✨ If you send a premium emoji, it will also show on the button.\nTo clear the custom text, send <code>-</code>.", $backadmin, 'HTML');
    sendmessage($from_id, $current . ($emojiId !== '' ? "\n🆔 <code>{$emojiId}</code>" : ''), null, 'HTML');
    step('edit_panel_custom_btn', $from_id);
} elseif ($user['step'] == "edit_panel_custom_btn") {
    $code = (string) ($user['Processing_value'] ?? '');
    $panel = select('marzban_panel', '*', 'code_panel', $code, 'select');
    if (!$panel || !is_array($panel)) {
        sendmessage($from_id, '❌ Panel not found.', $textbot_purchase, 'HTML');
        step('home', $from_id);
        return;
    }
    if (trim((string) $text) === '-') {
        update('marzban_panel', 'customvolume_text', '', 'code_panel', $code);
        update('marzban_panel', 'customvolume_emoji_id', '', 'code_panel', $code);
        sendmessage($from_id, '✅ Button text restored to default.', $textbot_purchase, 'HTML');
        step('home', $from_id);
        return;
    }
    $parsed = plain_text_and_custom_emoji_from_message($update['message'] ?? null);
    if ($parsed['text'] === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    update('marzban_panel', 'customvolume_text', $parsed['text'], 'code_panel', $code);
    update('marzban_panel', 'customvolume_emoji_id', $parsed['emoji_id'], 'code_panel', $code);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Inside-category description" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Choose the category whose description you want to edit:", keyboard_categories_purchase_text_edit('editcategorydesc_'), 'HTML');
} elseif (preg_match('/^editcategorydesc_(\d+)$/', (string) $datain, $m) && $adminrulecheck['rule'] == "administrator") {
    $category = select('category', '*', 'id', $m[1], 'select');
    if (!$category || !is_array($category)) {
        sendmessage($from_id, '❌ Category not found.', $textbot_purchase, 'HTML');
        return;
    }
    update('user', 'Processing_value', $m[1], 'id', $from_id);
    $current = trim((string) ($category['description'] ?? ''));
    $remark = htmlspecialchars((string) ($category['remark'] ?? ''), ENT_QUOTES, 'UTF-8');
    sendmessage($from_id, "📝 Send the description inside category «{$remark}»:\n💡 To clear it, send <code>-</code>.", $backadmin, 'HTML');
    if ($current !== '') {
        sendmessage($from_id, $current, null, 'HTML');
    } else {
        sendmessage($from_id, "⚠️ No description is set yet.", null, 'HTML');
    }
    step('edit_category_description', $from_id);
} elseif ($user['step'] == "edit_category_description") {
    $catId = (string) ($user['Processing_value'] ?? '');
    $category = select('category', '*', 'id', $catId, 'select');
    if (!$category || !is_array($category)) {
        sendmessage($from_id, '❌ Category not found.', $textbot_purchase, 'HTML');
        step('home', $from_id);
        return;
    }
    if (trim((string) $text) === '-') {
        update('category', 'description', '', 'id', $catId);
        sendmessage($from_id, '✅ Category description cleared.', $textbot_purchase, 'HTML');
        step('home', $from_id);
        return;
    }
    $html = text_from_telegram_update($update);
    if ($html === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    update('category', 'description', $html, 'id', $catId);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Start text" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_start']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    sendmessage($from_id, "📌 Available variables 

⚠️Username: 
 <blockquote>{username}</blockquote>

⚠️Account first name:
<blockquote>{first_name}</blockquote>

⚠️Account last name:
<blockquote>{last_name}</blockquote>

⚠️Current time: 
<blockquote>{time}</blockquote>

⚠️ Current bot version: 
<blockquote>{version}</blockquote>", null, "html");
    step('changetextstart', $from_id);
} elseif ($user['step'] == "changetextstart") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_start");
    step('home', $from_id);
} elseif ($text == "Purchased services button" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Purchased_services']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('changetextinfo', $from_id);
} elseif ($user['step'] == "changetextinfo") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_Purchased_services");
    step('home', $from_id);
} elseif ($text == "Test account button" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_usertest']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('changetextusertest', $from_id);
} elseif ($user['step'] == "changetextusertest") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_usertest");
    step('home', $from_id);
} elseif ($text == "📚 Guides button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_help']}</code>", $backadmin, 'HTML');
    step('text_help', $from_id);
} elseif ($user['step'] == "text_help") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_help");
    step('home', $from_id);
} elseif ($text == "Agency request text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textrequestagent']}</code>", $backadmin, 'HTML');
    step('textrequestagent', $from_id);
} elseif ($user['step'] == "textrequestagent") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textrequestagent");
    step('home', $from_id);
} elseif ($text == "Agency button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textpanelagent']}</code>", $backadmin, 'HTML');
    step('textpanelagent', $from_id);
} elseif ($user['step'] == "textpanelagent") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textpanelagent");
    step('home', $from_id);
} elseif ($text == "☎️ Support button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_support']}</code>", $backadmin, 'HTML');
    step('text_support', $from_id);
} elseif ($user['step'] == "text_support") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_support");
    step('home', $from_id);
} elseif ($text == "FAQ button" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_fq']}</code>", $backadmin, 'HTML');
    step('text_fq', $from_id);
} elseif ($user['step'] == "text_fq") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_fq");
    step('home', $from_id);
} elseif ($text == "📝 FAQ description" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_fq']}</code>", $backadmin, 'HTML');
    step('text_dec_fq', $from_id);
} elseif ($user['step'] == "text_dec_fq") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_dec_fq");
    step('home', $from_id);
} elseif ($text == "📝 Required-join description" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_channel']}</code>", $backadmin, 'HTML');
    step('text_channel', $from_id);
} elseif ($user['step'] == "text_channel") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_channel");
    step('home', $from_id);
} elseif ($text == "Wallet button text" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['accountwallet']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('accountwallet', $from_id);
} elseif ($user['step'] == "accountwallet") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "accountwallet");
    step('home', $from_id);
} elseif ($text == "Gift code button text" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Discount']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('text_Discount', $from_id);
} elseif ($user['step'] == "text_Discount") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_Discount");
    step('home', $from_id);
} elseif ($text == "Add balance button" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Add_Balance']}</code>", $backadmin, 'HTML');
    step('text_Add_Balance', $from_id);
} elseif ($user['step'] == "text_Add_Balance") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_Add_Balance");
    step('home', $from_id);
} elseif ($text == "Buy subscription button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_sell']}</code>", $backadmin, 'HTML');
    step('text_sell', $from_id);
} elseif ($user['step'] == "text_sell") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_sell");
    step('home', $from_id);
} elseif ($text == "Referral button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_affiliates']}</code>", $backadmin, 'HTML');
    step('text_affiliates', $from_id);
} elseif ($user['step'] == "text_affiliates") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_affiliates");
    step('home', $from_id);
} elseif ($text == "Tariff list button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_Tariff_list', $from_id);
} elseif ($user['step'] == "text_Tariff_list") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_Tariff_list");
    step('home', $from_id);
} elseif ($text == "Tariff list description" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_dec_Tariff_list', $from_id);
} elseif ($user['step'] == "text_dec_Tariff_list") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_dec_Tariff_list");
    step('home', $from_id);
} elseif ($text == "Location selection text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'textselectlocation', 'textselectlocation', $backadmin);
} elseif ($user['step'] == "textselectlocation") {
    if (!save_textbot_from_update('textselectlocation', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "Invoice preview text" && $adminrulecheck['rule'] == "administrator") {
    prompt_textbot_edit($from_id, 'text_pishinvoice', 'text_pishinvoice', $backadmin, "Variable names: 
username : config username 
name_product : product name
Service_time : service duration
price : service price
Volume : service volume
userBalance : user balance 
note : note

⚠️ These names must be inside braces");
} elseif ($user['step'] == "text_pishinvoice") {
    if (!save_textbot_from_update('text_pishinvoice', $update)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot_purchase, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot_purchase, 'HTML');
    step('home', $from_id);
} elseif ($text == "After-purchase text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpay']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 
name_service : product name
day : service duration
location : service location
volume : service volume
config : sub link
links : config without copy
links2 : sub link without copy

⚠️ These names must be inside braces", null, 'HTML');
    step('text_afterpaytext', $from_id);
} elseif ($user['step'] == "text_afterpaytext") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textafterpay");
    step('home', $from_id);
} elseif ($text == "After-purchase text (ibsng)" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpayibsng']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 
name_service : product name
day : service duration
location : service location
volume : service volume
config : sub link
links : config without copy
links2 : sub link without copy

⚠️ These names must be inside braces", null, 'HTML');
    step('text_afterpaytextibsng', $from_id);
} elseif ($user['step'] == "text_afterpaytextibsng") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textafterpayibsng");
    step('home', $from_id);
} elseif ($text == "Card-to-card text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
price : transaction amount
card_number : card number 
name_card : cardholder name
⚠️ These names must be inside braces", null, 'HTML');
    step('text_cart', $from_id);
} elseif ($user['step'] == "text_cart") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_cart");
    step('home', $from_id);
} elseif ($text == "Auto card-to-card text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart_auto']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
price : transaction amount
card_number : card number 
name_card : cardholder name
⚠️ These names must be inside braces", null, 'HTML');
    step('text_cart_auto', $from_id);
} elseif ($user['step'] == "text_cart_auto") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_cart_auto");
    step('home', $from_id);
} elseif ($text == "After test-account text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textaftertext']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 
name_service : product name
day : service duration
location : service location
volume : service volume
config : connection link
links : config without copy
links2 : sub link without copy

⚠️ These names must be inside braces", null, 'HTML');
    step('text_aftertesttext', $from_id);
} elseif ($user['step'] == "text_aftertesttext") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textaftertext");
    step('home', $from_id);
} elseif ($text == "After manual-account text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 
name_service : product name
location : service location
config : service info

⚠️ These names must be inside braces", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($text == "Test cron text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['crontest']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 

⚠️ These names must be inside braces", null, 'HTML');
    step('text_crontest', $from_id);
} elseif ($user['step'] == "text_crontest") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "crontest");
    step('home', $from_id);
} elseif ($text == "After manual-account text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 
name_service : product name
location : service location
config : service info

⚠️ These names must be inside braces", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($user['step'] == "text_textmanual") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textmanual");
    step('home', $from_id);
} elseif ($text == "After WGDashboard account text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_wgdashboard']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "Variable names: 
username : config username 
name_service : product name
day : service duration
location : service location
volume : service volume

⚠️ These names must be inside braces", null, 'HTML');
    step('text_wgdashboard', $from_id);
} elseif ($user['step'] == "text_wgdashboard") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_wgdashboard");
    step('home', $from_id);
} elseif ($text == "Renew button" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_extend']}</code>", $backadmin, 'HTML');
    step('text_extend', $from_id);
} elseif ($user['step'] == "text_extend") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_extend");
    step('home', $from_id);
} elseif (preg_match('/sendmessageuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    savedata("clear", "iduser", $iduser);
    sendmessage($from_id, "📌 Send your text or image", $backadmin, 'HTML');
    step('sendmessagetext', $from_id);
} elseif ($user['step'] == "sendmessagetext") {
    if ($photo) {
        savedata("save", "type", "photo");
        savedata("save", "photoid", $photoid);
        savedata("save", "text", $caption);
    } else {
        savedata("save", "text", $text);
        savedata("save", "type", "text");
    }
    $textb = "📌 Should the user be able to reply?
1 - Yes, they can reply 
2 - No, they cannot reply
Send the answer as a number";
    sendmessage($from_id, $textb, $backadmin, 'HTML');
    step('sendmessagetid', $from_id);
} elseif ($user['step'] == "sendmessagetid") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $textsendadmin = "
👤 A message from the admin has been sent  
Message text:

{$userdata['text']}";
    if (intval($text) == "1") {
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                ],
            ]
        ]);
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'reply_markup' => $Response,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, $Response, 'HTML');
        }
    } else {
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, null, 'HTML');
        }
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📤 Forward a message to a user") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetText'], $backadmin, 'HTML');
    step('getmessageforward', $from_id);
} elseif ($user['step'] == "getmessageforward") {
    savedata("clear", "messageid", $message_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetIDMessage'], $backadmin, 'HTML');
    step('getbtnresponseforward', $from_id);
} elseif ($user['step'] == "getbtnresponseforward") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    forwardMessage($from_id, $userdata['messageid'], $text);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 Guides section" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardhelpadmin, 'HTML');
} elseif ($text == "📚 Add guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Help']['GetAddNameHelp'], $backadmin, 'HTML');
    step('add_name_help', $from_id);
} elseif ($user['step'] == "add_name_help") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, "❌ Tutorial name must be under 150 characters", null, 'HTML');
        return;
    }
    $helpexits = select("help", "*", "name_os", $text, "count");
    if ($helpexits != 0) {
        sendmessage($from_id, "❌ That tutorial name already exists. Use a different name.", null, 'HTML');
        return;
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO help (name_os) VALUES (?)");
    $stmt->bind_param("s", $text);
    $stmt->execute();
    update("user", "Processing_value", $text, "id", $from_id);
    if ($setting['categoryhelp'] == "0") {
        update("help", "category", "0", "name_os", $user['Processing_value']);
        sendmessage($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
        step('add_dec', $from_id);
        return;
    }
    sendmessage($from_id, "📌 Send the category name for the tutorial", $backadmin, 'HTML');
    step('getcatgoryhelp', $from_id);
} elseif ($user['step'] == "getcatgoryhelp") {
    update("help", "category", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
    step('add_dec', $from_id);
} elseif ($user['step'] == "add_dec") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($text) {
        update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    } elseif ($document) {
        if (isset($fileid))
            update("help", "Media_os", $fileid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "document", "name_os", $user['Processing_value']);
    }
    sendmessage($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ Remove guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Help']['SelectName'], keyboard_help_os_list(), 'HTML');
    step('remove_help', $from_id);
} elseif ($user['step'] == "remove_help") {
    $stmt = $pdo->prepare("DELETE FROM help WHERE name_os = :name_os");
    $stmt->bindParam(':name_os', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Help']['RemoveHelp'], $keyboardhelpadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/Response_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('getmessageAsAdmin', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backadmin, 'HTML');
} elseif ($user['step'] == "getmessageAsAdmin") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SendMessageuser'], null, 'HTML');
    $Respuseronse = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
            ],
        ]
    ]);
    if ($text) {
        $textSendAdminToUser = "
📩 A message from management was sent to you.
                    
Message text: 
$text";
        sendmessage($user['Processing_value'], $textSendAdminToUser, $Respuseronse, 'HTML');
    }
    if ($photo) {
        $textSendAdminToUser = "
📩 A message from management was sent to you.
                    
Message text: 
$caption";
        telegram('sendphoto', [
            'chat_id' => $user['Processing_value'],
            'photo' => $photoid,
            'reply_markup' => $Respuseronse,
            'caption' => $textSendAdminToUser,
            'parse_mode' => "HTML",
        ]);
    }
    step('home', $from_id);
} elseif ($text == "⚙️ Feature status" && $adminrulecheck['rule'] == "administrator") {
    if ($setting['Bot_Status'] == "✅  ربات روشن است") {
        update("setting", "Bot_Status", "botstatuson");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        update("setting", "Bot_Status", "botstatusoff");
    }
    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        update("setting", "roll_Status", "rolleon");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        update("setting", "roll_Status", "rolleoff");
    }
    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        update("setting", "get_number", "onAuthenticationphone");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        update("setting", "get_number", "offAuthenticationphone");
    }
    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        update("setting", "iran_number", "onAuthenticationiran");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        update("setting", "iran_number", "offAuthenticationiran");
    }
    $status_cron = json_decode($setting['cron_status'], true);
    $setting = select("setting", "*", null, null, "select");
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $referralstatus_label = [
        'onreferral' => $textbotlang['Admin']['Status']['statuson'],
        'offreferral' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['referralstatus'] ?? 'offreferral'];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $btnstatuslinkapp = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['linkappstatus']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $languagestatus = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageen']];
    $languagestatusru = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageru']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
            ],
            [
                ['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"],
            ],
            [
                ['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"],
            ],
            [
                ['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"],
            ],
            [
                ['text' => $Authenticationphone, 'callback_data' => "editstsuts-Authenticationphone-{$setting['get_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"],
            ],
            [
                ['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"],
            ],
            [
                ['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"],
                ['text' => $textbotlang['Admin']['Status']['inlinebtns'], 'callback_data' => "inlinebtnmain"],
            ],
            [
                ['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                ['text' => "🔒 Verification", 'callback_data' => "verify"],
            ],
            [
                ['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                ['text' => "👤 Support in private chat", 'callback_data' => "statussupportpv"],
            ],
            [
                ['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                ['text' => "📨 Config note", 'callback_data' => "statusnamecustom"],
            ],
            [
                ['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                ['text' => "📨 Regular user note", 'callback_data' => "statusnamecustomf"],
            ],
            [
                ['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                ['text' => "🛍 Bulk purchase status", 'callback_data' => "bulkbuy"],
            ],
            [
                ['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                ['text' => "🔑 Link verification", 'callback_data' => "verifybyuser"],
            ],
            [
                ['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                ['text' => "📗Tutorial categories", 'callback_data' => "btn_status_category"],
            ],
            [
                ['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                ['text' => "🎲 Agent lucky wheel", 'callback_data' => "wheelagent"],
            ],
            [
                ['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                ['text' => "🔗 Config keyboard", 'callback_data' => "keyconfig"],
            ],
            [
                ['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                ['text' => "🎰 Show dice", 'callback_data' => "Dice"],
            ],
            [
                ['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                ['text' => "🎲 First-purchase lucky wheel", 'callback_data' => "wheelagentfirst"],
            ],
            [
                ['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                ['text' => "🎁 Agent raffle", 'callback_data' => "Lotteryagent"],
            ],
            [
                ['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                ['text' => "💎 Debt settlement", 'callback_data' => "Debtsettlement"],
            ],
            [
                ['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                ['text' => "💳 Copy card number", 'callback_data' => "copycart"],
            ],
            [
                ['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                ['text' => "🔓Test cron", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                ['text' => "🎛 Node uptime", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                ['text' => "🎛 Panel uptime", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Warning time", 'callback_data' => "settimecornday"],
                ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                ['text' => "🕚 Time cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ First-connect time", 'callback_data' => "setting_on_holdcron"],
                ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                ['text' => "🕚 First-connect cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Warning volume", 'callback_data' => "settimecornvolume"],
                ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                ['text' => "🔋 Volume cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Delete time", 'callback_data' => "settimecornremove"],
                ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                ['text' => "❌ Delete cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Delete time", 'callback_data' => "settimecornremovevolume"],
                ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                ['text' => "❌ Volume delete cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "linkappsetting"],
                ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"],
                ['text' => "🔗App download link", 'callback_data' => "linkappstatus"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "scoresetting"],
                ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                ['text' => "🎁 Nightly raffle", 'callback_data' => "score"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "gradonhshans"],
                ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                ['text' => "🎲 Lucky wheel", 'callback_data' => "wheel_luck"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "settingaffiliatesf"],
                ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                ['text' => "🎁Referral", 'callback_data' => "affiliatesstatus"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "referral_campaigns_admin"],
                ['text' => $referralstatus_label, 'callback_data' => "editstsuts-referralstatus-" . ($setting['referralstatus'] ?? 'offreferral')],
                ['text' => "🎁 Invite friends", 'callback_data' => "referralstatus"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "changeloclimit"],
                ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                ['text' => "🌍 Location-change limit", 'callback_data' => "changeloc"],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $status_cron = json_decode($setting['cron_status'], true);
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusbot") {
        if ($value == "botstatuson") {
            $valuenew = "botstatusoff";
        } else {
            $valuenew = "botstatuson";
        }
        update("setting", "Bot_Status", $valuenew);
    } elseif ($type == "usernamebtn") {
        if ($value == "onnotuser") {
            $valuenew = "offnotuser";
        } else {
            $valuenew = "onnotuser";
        }
        update("setting", "NotUser", $valuenew);
    } elseif ($type == "notifnew") {
        if ($value == "onnewuser") {
            $valuenew = "offnewuser";
        } else {
            $valuenew = "onnewuser";
        }
        update("setting", "statusnewuser", $valuenew);
    } elseif ($type == "showagent") {
        if ($value == "onrequestagent") {
            $valuenew = "offrequestagent";
        } else {
            $valuenew = "onrequestagent";
        }
        update("setting", "statusagentrequest", $valuenew);
    } elseif ($type == "role") {
        if ($value == "rolleon") {
            $valuenew = "rolleoff";
        } else {
            $valuenew = "rolleon";
        }
        update("setting", "roll_Status", $valuenew);
    } elseif ($type == "Authenticationphone") {
        if ($value == "onAuthenticationphone") {
            $valuenew = "offAuthenticationphone";
        } else {
            $valuenew = "onAuthenticationphone";
        }
        update("setting", "get_number", $valuenew);
    } elseif ($type == "Authenticationiran") {
        if ($value == "onAuthenticationiran") {
            $valuenew = "offAuthenticationiran";
        } else {
            $valuenew = "onAuthenticationiran";
        }
        update("setting", "iran_number", $valuenew);
    } elseif ($type == "inlinebtnmain") {
        if ($value == "oninline") {
            $valuenew = "offinline";
        } else {
            $valuenew = "oninline";
        }
        update("setting", "inlinebtnmain", $valuenew);
    } elseif ($type == "verifystart") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifystart", $valuenew);
    } elseif ($type == "statussupportpv") {
        if ($value == "onpvsupport") {
            $valuenew = "offpvsupport";
        } else {
            $valuenew = "onpvsupport";
        }
        update("setting", "statussupportpv", $valuenew);
    } elseif ($type == "statusnamecustom") {
        if ($value == "onnamecustom") {
            $valuenew = "offnamecustom";
        } else {
            $valuenew = "onnamecustom";
        }
        update("setting", "statusnamecustom", $valuenew);
    } elseif ($type == "bulkbuy") {
        if ($value == "onbulk") {
            $valuenew = "offbulk";
        } else {
            $valuenew = "onbulk";
        }
        update("setting", "bulkbuy", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "wheelagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelagent", $valuenew);
    } elseif ($type == "keyconfig") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "status_keyboard_config", $valuenew);
    } elseif ($type == "Lotteryagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Lotteryagent", $valuenew);
    } elseif ($type == "compycart") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuscopycart", $valuenew);
    } elseif ($type == "score") {
        if ($value == "1") {
            if (isShellExecAvailable()) {
                $crontabBinary = getCrontabBinary();
                if ($crontabBinary === null) {
                    error_log('Unable to locate crontab executable; cannot remove lottery cron job.');
                } else {
                    $currentCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
                    $jobToRemove = "*/1 * * * * curl https://$domainhosts/cronbot/lottery.php";
                    $newCronJobs = preg_replace('/' . preg_quote($jobToRemove, '/') . '/', '', (string) $currentCronJobs);
                    $tempCronFile = '/tmp/crontab.txt';
                    file_put_contents($tempCronFile, trim($newCronJobs) . PHP_EOL);
                    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($tempCronFile)));
                    if (file_exists($tempCronFile)) {
                        unlink($tempCronFile);
                    }
                }
            } else {
                error_log('Unable to remove lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "0";
        } else {
            $phpFilePath = "https://$domainhosts/cronbot/lottery.php";
            $cronCommand = "*/1 * * * * curl $phpFilePath";
            if (!addCronIfNotExists($cronCommand)) {
                error_log('Unable to register lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "1";
        }
        update("setting", "scorestatus", $valuenew);
    } elseif ($type == "wheel_luck") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelـluck", $valuenew);
    } elseif ($type == "affiliatesstatus") {
        if ($value == "onaffiliates") {
            $valuenew = "offaffiliates";
        } else {
            $valuenew = "onaffiliates";
        }
        update("setting", "affiliatesstatus", $valuenew);
    } elseif ($type == "referralstatus") {
        if ($value == "onreferral") {
            $valuenew = "offreferral";
        } else {
            $valuenew = "onreferral";
        }
        update("setting", "referralstatus", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "btn_status_category") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "categoryhelp", $valuenew);
    } elseif ($type == "linkappstatus") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "linkappstatus", $valuenew);
    } elseif ($type == "btnstautslanguage") {
        if ($setting['languageru'] == "1") {
            sendmessage($from_id, "Russian is enabled so you cannot toggle English", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageen", $valuenew);
    } elseif ($type == "btnstautslanguageru") {
        if ($setting['languageen'] == "1") {
            sendmessage($from_id, "English is enabled so you cannot toggle Russian", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageru", $valuenew);
    } elseif ($type == "wheelagentfirst") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusfirstwheel", $valuenew);
    } elseif ($type == "changeloc") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuslimitchangeloc", $valuenew);
    } elseif ($type == "Debtsettlement") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Debtsettlement", $valuenew);
    } elseif ($type == "Dice") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Dice", $valuenew);
    } elseif ($type == "statusnamecustomf") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusnoteforf", $valuenew);
    } elseif ($type == "crontest") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['test'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronday") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['day'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronvolume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['remove'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove_volume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['remove_volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_node") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['uptime_node'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_panel") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['uptime_panel'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "on_hold") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['on_hold'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    }
    $setting = select("setting", "*");
    $status_cron = json_decode($setting['cron_status'], true);
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $referralstatus_label = [
        'onreferral' => $textbotlang['Admin']['Status']['statuson'],
        'offreferral' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['referralstatus'] ?? 'offreferral'];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $btnstatuslinkapp = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['linkappstatus']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $languagestatus = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageen']];
    $languagestatusru = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageru']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
            ],
            [
                ['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"],
            ],
            [
                ['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"],
            ],
            [
                ['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"],
            ],
            [
                ['text' => $Authenticationphone, 'callback_data' => "editstsuts-Authenticationphone-{$setting['get_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"],
            ],
            [
                ['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"],
            ],
            [
                ['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"],
                ['text' => $textbotlang['Admin']['Status']['inlinebtns'], 'callback_data' => "inlinebtnmain"],
            ],
            [
                ['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                ['text' => "🔒 Verification", 'callback_data' => "verify"],
            ],
            [
                ['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                ['text' => "👤 Support in private chat", 'callback_data' => "statussupportpv"],
            ],
            [
                ['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                ['text' => "📨 Config note", 'callback_data' => "statusnamecustom"],
            ],
            [
                ['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                ['text' => "📨 Regular user note", 'callback_data' => "statusnamecustomf"],
            ],
            [
                ['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                ['text' => "🛍 Bulk purchase status", 'callback_data' => "bulkbuy"],
            ],
            [
                ['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                ['text' => "🔑 Link verification", 'callback_data' => "verifybyuser"],
            ],
            [
                ['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                ['text' => "📗Tutorial categories", 'callback_data' => "btn_status_category"],
            ],
            [
                ['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                ['text' => "🎲 Agent lucky wheel", 'callback_data' => "wheelagent"],
            ],
            [
                ['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                ['text' => "🔗 Config keyboard", 'callback_data' => "keyconfig"],
            ],
            [
                ['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                ['text' => "🎰 Show dice", 'callback_data' => "Dice"],
            ],
            [
                ['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                ['text' => "🎲 First-purchase lucky wheel", 'callback_data' => "wheelagentfirst"],
            ],
            [
                ['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                ['text' => "🎁 Agent raffle", 'callback_data' => "Lotteryagent"],
            ],
            [
                ['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                ['text' => "💎 Debt settlement", 'callback_data' => "Debtsettlement"],
            ],
            [
                ['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                ['text' => "💳 Copy card number", 'callback_data' => "copycart"],
            ],
            [
                ['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                ['text' => "🔓Test cron", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                ['text' => "🎛 Node uptime", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                ['text' => "🎛 Panel uptime", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Warning time", 'callback_data' => "settimecornday"],
                ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                ['text' => "🕚 Time cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ First-connect time", 'callback_data' => "setting_on_holdcron"],
                ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                ['text' => "🕚 First-connect cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Warning volume", 'callback_data' => "settimecornvolume"],
                ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                ['text' => "🔋 Volume cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Delete time", 'callback_data' => "settimecornremove"],
                ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                ['text' => "❌ Delete cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Delete time", 'callback_data' => "settimecornremovevolume"],
                ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                ['text' => "❌ Volume delete cron", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "linkappsetting"],
                ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"],
                ['text' => "🔗App download link", 'callback_data' => "linkappstatus"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "scoresetting"],
                ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                ['text' => "🎁 Nightly raffle", 'callback_data' => "score"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "gradonhshans"],
                ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                ['text' => "🎲 Lucky wheel", 'callback_data' => "wheel_luck"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "settingaffiliatesf"],
                ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                ['text' => "🎁Referral", 'callback_data' => "affiliatesstatus"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "referral_campaigns_admin"],
                ['text' => $referralstatus_label, 'callback_data' => "editstsuts-referralstatus-" . ($setting['referralstatus'] ?? 'offreferral')],
                ['text' => "🎁 Invite friends", 'callback_data' => "referralstatus"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "changeloclimit"],
                ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                ['text' => "🌍 Location-change limit", 'callback_data' => "changeloc"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($text == "⚖️ Rules text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_roll'], $backadmin, 'HTML');
    step('text_roll', $from_id);
} elseif ($user['step'] == "text_roll") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_roll");
    step('home', $from_id);
} elseif ($text == "📣 Bot reports" && $adminrulecheck['rule'] == "administrator") {
    $textreports = "📣 In this section you can send the numeric group ID for notifications
How to set up the group:
1 - Create a group 
2 - Add @myidbot to the group and send /getgroupid@myidbot in the group 
3 - Enable topics/forum in the group settings
4 - Make your bot a group admin 
5 - Send the numeric ID in the bot.

Your current numeric ID: {$setting['Channel_Report']}";
    sendmessage($from_id, $textreports, $backadmin, 'HTML');
    step('addchannelid', $from_id);
} elseif ($user['step'] == "addchannelid") {
    $outputcheck = sendmessage($text, $textbotlang['Admin']['Channel']['TestChannel'], null, 'HTML');
    if (!$outputcheck['ok']) {
        $texterror = "❌ Failed to connect to the group  

Error:  {$outputcheck['description']}";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($outputcheck['result']['chat']['is_forum'] == false) {
        $texterror = "❌ The selected group is not in forum mode. Enable topics first, then set the numeric group ID again";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🛍 Purchase reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($buyreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "buyreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "📌 Service purchase reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($otherservice != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherservice");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🔑 Test account reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($reporttest != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reporttest");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "⚙️ Other reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($otherreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "❌ Error reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($errorreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "errorreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "💰 Financial reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    if ($paymentreports != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "paymentreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => $textbotlang['Admin']['affiliates']['titletopic']
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    if ($porsantreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "porsantreport");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => $textbotlang['Admin']['report']['reportnight']
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    if ($reportnight != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reportnight");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => $textbotlang['Admin']['report']['reportcron']
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    if ($reportcron != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reportcron");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "🤖 Bot backup "
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    if ($reportbackup != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "backupfile");
    }
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name' => "📨 Broadcast reports"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ The bot is not a group admin";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    $reportsms_topic = select("topicid", "idreport", "report", "reportsms", "select")['idreport'] ?? '0';
    if ($reportsms_topic != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reportsms");
    }
    sendmessage($from_id, $textbotlang['Admin']['Channel']['SetChannelReport'], $setting_panel, 'HTML');
    update("setting", "Channel_Report", $text);
    step('home', $from_id);
} elseif ($text == "🏬 Shop settings" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🎁 Invite campaigns" && $adminrulecheck['rule'] == "administrator") {
    referral_ensure_schema();
    $all_campaigns_stmt = $pdo->query("SELECT * FROM referral_campaign ORDER BY id DESC LIMIT 20");
    $all_campaigns = $all_campaigns_stmt ? $all_campaigns_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $master = ($setting['referralstatus'] ?? 'offreferral') === 'onreferral' ? 'Active' : 'Inactive';
    $text_list = "<b>🎁 Invite campaign management</b>\n\nSystem status: <b>{$master}</b>\n";
    if (empty($all_campaigns)) {
        $text_list .= "\nNo campaign has been registered yet.";
    } else {
        foreach ($all_campaigns as $camp) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM referral_invite WHERE campaign_id = ?");
            $stmt->execute([(int) $camp['id']]);
            $invite_total = (int) $stmt->fetchColumn();
            $status_label = ($camp['status'] ?? '') === 'active' ? '✅' : '⛔';
            $text_list .= "\n{$status_label} <b>{$camp['title']}</b> (<code>{$camp['code']}</code>) — {$invite_total} invites — goal: {$camp['required_invites']}";
        }
    }
    $inline = ['inline_keyboard' => [
        [['text' => '➕ New campaign', 'callback_data' => 'referral_admin_add']],
        [['text' => '🔄 Toggle system status', 'callback_data' => 'referral_admin_toggle_master']],
    ]];
    foreach ($all_campaigns as $camp) {
        $inline['inline_keyboard'][] = [[
            'text' => (($camp['status'] ?? '') === 'active' ? '⛔ Inactive' : '✅ Active') . " — {$camp['code']}",
            'callback_data' => 'referral_admin_toggle_' . $camp['id'],
        ]];
    }
    sendmessage($from_id, $text_list, json_encode($inline, JSON_UNESCAPED_UNICODE), 'HTML');
} elseif ($datain == "referral_campaigns_admin") {
    sendmessage($from_id, "🎁 To manage campaigns, tap «🎁 Invite campaigns» in the shop menu.", $shopkeyboard, 'HTML');
} elseif ($datain == "referral_admin_toggle_master") {
    $current = $setting['referralstatus'] ?? 'offreferral';
    $new = $current === 'onreferral' ? 'offreferral' : 'onreferral';
    update("setting", "referralstatus", $new);
    $setting['referralstatus'] = $new;
    sendmessage($from_id, $new === 'onreferral' ? "✅ Invite system enabled." : "⛔ Invite system disabled.", null, 'HTML');
} elseif ($datain == "referral_admin_add") {
    savedata("clear", "referral_product", '');
    $products = select("product", "*", null, null, "fetchAll");
    $inline = ['inline_keyboard' => []];
    foreach ($products as $product) {
        if (($product['Location'] ?? '') === '' || ($product['Location'] ?? '') === '/all') {
            continue;
        }
        $inline['inline_keyboard'][] = [[
            'text' => $product['name_product'] . ' (' . $product['Location'] . ')',
            'callback_data' => 'referral_admin_product_' . $product['code_product'],
        ]];
    }
    if (empty($inline['inline_keyboard'])) {
        sendmessage($from_id, "❌ No suitable product (linked to a specific panel) was found.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "🛍 Choose the reward product:\n\n<i>Each user's link is built with their numeric Telegram ID.</i>", json_encode($inline, JSON_UNESCAPED_UNICODE), 'HTML');
    step('referral_campaign_product', $from_id);
} elseif (preg_match('/^referral_admin_product_(.+)$/', $datain, $ref_prod_match)) {
    if ($user['step'] != 'referral_campaign_product') {
        sendmessage($from_id, "❌ First tap «➕ New campaign» from the campaign menu.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $product = select("product", "*", "code_product", $ref_prod_match[1], "select");
    if (!$product || ($product['Location'] ?? '') === '' || ($product['Location'] ?? '') === '/all') {
        sendmessage($from_id, "❌ Invalid product.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "referral_product", $product['code_product']);
    savedata("save", "referral_panel", $product['Location']);
    sendmessage($from_id, "📌 Enter the number of invites required for the reward:", $backadmin, 'HTML');
    step('referral_campaign_invites', $from_id);
} elseif ($user['step'] == "referral_campaign_invites") {
    if (!ctype_digit($text) || intval($text) < 1) {
        sendmessage($from_id, "❌ Enter a valid number (minimum 1).", $backadmin, 'HTML');
        return;
    }
    savedata("save", "referral_invites", $text);
    sendmessage($from_id, "📌 Enter the campaign title (or «-» for the default name):", $backadmin, 'HTML');
    step('referral_campaign_title', $from_id);
} elseif ($user['step'] == "referral_campaign_title") {
    $userdata = json_decode($user['Processing_value'], true);
    $title = trim($text) === '-' ? '' : trim($text);
    $created_at = date('Y/m/d H:i:s');
    $placeholder = 'REF' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $pdo->prepare("INSERT INTO referral_campaign (code, title, description, code_product, panel_name, required_invites, status, new_users_only, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', 1, ?)");
    $stmt->execute([
        $placeholder,
        $title !== '' ? $title : 'New campaign',
        'none',
        $userdata['referral_product'],
        $userdata['referral_panel'],
        (int) $userdata['referral_invites'],
        $created_at,
    ]);
    $campaign_id = (int) $pdo->lastInsertId();
    $auto_code = referral_auto_campaign_code($campaign_id);
    if ($title === '') {
        $title = 'Campaign #' . $campaign_id;
    }
    update("referral_campaign", "code", $auto_code, "id", $campaign_id);
    update("referral_campaign", "title", $title, "id", $campaign_id);
    step('home', $from_id);
    sendmessage($from_id, "✅ Campaign «{$title}» created.\n\nCampaign ID: <code>{$campaign_id}</code>\nPer-user link format:\n<code>ref_{$campaign_id}_USER_NUMERIC_ID</code>", $shopkeyboard, 'HTML');
} elseif (preg_match('/^referral_admin_toggle_(\d+)$/', $datain, $ref_toggle_match)) {
    $campaign = referral_get_campaign_by_id($ref_toggle_match[1]);
    if (!$campaign) {
        sendmessage($from_id, "❌ Campaign not found.", null, 'HTML');
        return;
    }
    $new_status = ($campaign['status'] ?? '') === 'active' ? 'inactive' : 'active';
    update("referral_campaign", "status", $new_status, "id", $campaign['id']);
    sendmessage($from_id, "✅ Campaign «{$campaign['title']}» status changed to " . ($new_status === 'active' ? 'Active' : 'Inactive') . ".", null, 'HTML');
} elseif ($text == "🛍 Add product" && $adminrulecheck['rule'] == "administrator") {
    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpaneladmin'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'] . "\n\n✨ If you send a premium emoji, it will show on the product button.", $backadmin, 'HTML');
    step('get_limit', $from_id);
} elseif ($user['step'] == "get_limit") {
    $parsed = button_label_and_icon_from_update($update);
    $productName = $parsed['text'] !== '' ? $parsed['text'] : (string) $text;
    if (strlen($productName) > 150) {
        sendmessage($from_id, "❌ Product name must be under 150 characters", $backadmin, 'HTML');
        return;
    }
    if ($productName === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $backadmin, 'HTML');
        return;
    }
    if (rowExists('product', 'name_product', $productName)) {
        sendmessage($from_id, "❌ A product named $productName already exists", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name_product", $productName);
    savedata("save", "name_product_emoji_id", $parsed['emoji_id']);
    sendmessage($from_id, $textbotlang['Admin']['agent']['setagentproduct'], $backadmin, 'HTML');
    step('get_agent', $from_id);
} elseif ($user['step'] == "get_agent") {
    $agent = ["n", "f", "n2"];
    if (!in_array($text, $agent)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    sendmessage($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
    step('get_location', $from_id);
} elseif ($user['step'] == "get_location") {
    if ($text !== '/all' && !rowExists('marzban_panel', 'name_panel', $text)) {
        sendmessage($from_id, "❌ Selected panel is incorrect", null, 'HTML');
        return;
    }
    savedata("save", "Location", $text);
    if ($setting['statuscategorygenral'] == "oncategorys") {
        sendmessage($from_id, "📌 Send your category name.", KeyboardCategoryadmin(), 'HTML');
        step("getcategory", $from_id);
        return;
    }
    $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    if ($panel['type'] == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "getcategory") {
    $resolved = resolve_category_from_update($update);
    if (!$resolved['ok']) {
        sendmessage($from_id, category_resolve_error_text($resolved['error']), KeyboardCategoryadmin(), 'HTML');
        return;
    }
    savedata("save", "category", $resolved['category']['remark']);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($panel['type'] == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "get_time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Volume_constraint", $text);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
    step('get_price', $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Service_time", $text);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
    step('gettimereset', $from_id);
} elseif ($user['step'] == "gettimereset") {
    if (!is_valid_money_input($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price_product", (string) money_amount($text));
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($panel['type'] == "marzban" || $panel['type'] == "marzneshin") {
        sendmessage($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
        step('getnote', $from_id);
        return;
    }
    savedata("save", "data_limit_reset", "no_reset");
    sendmessage($from_id, " 🗒 Send a note for the product. This note is shown on the user's proforma invoice.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "getnote") {
    savedata("save", "data_limit_reset", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($panel && $panel['type'] == 'marzban' && ($panel['version_panel'] ?? '0') === '1') {
        sendmessage($from_id, "📌 Send the device limit (HWID).\nPositive number = max allowed devices\n«-» = no limit", $backadmin, 'HTML');
        step('get_hwid_limit', $from_id);
        return;
    }
    sendmessage($from_id, " 🗒 Send a note for the product. This note is shown on the user's proforma invoice.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "get_hwid_limit") {
    $hwid_limit = null;
    $trimmed = trim($text);
    if ($trimmed !== '' && $trimmed !== '-') {
        if (!ctype_digit($trimmed) || (int) $trimmed <= 0) {
            sendmessage($from_id, "❌ Invalid value. Send a positive number or «-» for no limit.", $backadmin, 'HTML');
            return;
        }
        $hwid_limit = (int) $trimmed;
    }
    savedata("save", "hwid_limit", $hwid_limit);
    sendmessage($from_id, " 🗒 Send a note for the product. This note is shown on the user's proforma invoice.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "endstep") {
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    $varhide_panel = "{}";
    if (!isset($userdata['category']))
        $userdata['category'] = null;
    $hwid_limit = isset($userdata['hwid_limit']) ? $userdata['hwid_limit'] : null;
    ensure_shop_button_emoji_columns();
    $emoji_id = stored_custom_emoji_id($userdata['name_product_emoji_id'] ?? '');
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,hwid_limit,emoji_id) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0',:hwid_limit,:emoji_id)");
    $stmt->bindParam(':name_product', $userdata['name_product']);
    $stmt->bindParam(':code_product', $randomString);
    $stmt->bindParam(':price_product', $userdata['price_product']);
    $stmt->bindParam(':Volume_constraint', $userdata['Volume_constraint']);
    $stmt->bindParam(':Service_time', $userdata['Service_time']);
    $stmt->bindParam(':Location', $userdata['Location']);
    $stmt->bindParam(':agent', $userdata['agent']);
    $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
    $stmt->bindParam(':category', $userdata['category'], PDO::PARAM_STR);
    $stmt->bindParam(':note', $text, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $varhide_panel, PDO::PARAM_STR);
    $stmt->bindValue(':hwid_limit', $hwid_limit, $hwid_limit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':emoji_id', $emoji_id !== '' ? $emoji_id : null, $emoji_id !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "👨‍🔧 Admin section" && $adminrulecheck['rule'] == "administrator") {
    $list_admin = select("admin", "*", null, null, "fetchAll");
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($list_admin as $admin) {
        $adminId = isset($admin['id_admin']) ? trim($admin['id_admin']) : '';
        if ($adminId === '') {
            continue;
        }
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $adminId],
            ['text' => $adminId, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 Add admin", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    sendmessage($from_id, "📌 Below you can see the admin list. Tap the X button to remove an admin", $keyboardadmin, 'HTML');
} elseif ($text == "⚙️ General settings" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "⌨️ Menu button settings" && $adminrulecheck['rule'] == "administrator") {
    $markup = build_main_keyboard_admin_markup($datatextbot, $setting['keyboardmain']);
    sendmessage($from_id, "⌨️ Main bot menu buttons\n\nTap a button to manage its visibility or premium emoji.\n✦ means a premium emoji is set for that button.", $markup, 'HTML');
} elseif ($datain == "listmainbtn" && $adminrulecheck['rule'] == "administrator") {
    step('home', $from_id);
    $markup = build_main_keyboard_admin_markup($datatextbot, $setting['keyboardmain']);
    Editmessagetext($from_id, $message_id, "⌨️ Main bot menu buttons\n\nTap a button to manage its visibility or premium emoji.\n✦ means a premium emoji is set for that button.", $markup);
} elseif ($datain == "resetmainbtn" && $adminrulecheck['rule'] == "administrator") {
    $default = get_default_main_keyboard_json();
    update("setting", "keyboardmain", $default, null, null);
    reset_main_keyboard_button_styles();
    reset_main_keyboard_button_icons();
    $setting['keyboardmain'] = $default;
    step('home', $from_id);
    $markup = build_main_keyboard_admin_markup($datatextbot, $setting['keyboardmain']);
    Editmessagetext($from_id, $message_id, "⌨️ Main bot menu buttons\n\nTap a button to manage its visibility or premium emoji.\n✦ means a premium emoji is set for that button.", $markup);
} elseif (preg_match('/^editmainbtn-(.+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $button_id = $dataget[1];
    if (!in_array($button_id, get_main_keyboard_button_ids(), true)) {
        return;
    }
    step('home', $from_id);
    $edit_text = build_main_keyboard_button_edit_text($button_id, $datatextbot, $setting['keyboardmain']);
    $markup = build_main_keyboard_button_edit_markup($button_id, $datatextbot, $setting['keyboardmain']);
    Editmessagetext($from_id, $message_id, $edit_text, $markup);
} elseif (preg_match('/^togglemainbtn-(.+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $button_id = $dataget[1];
    if (!in_array($button_id, get_main_keyboard_button_ids(), true)) {
        return;
    }
    $new_keyboard = toggle_main_keyboard_button($setting['keyboardmain'], $button_id);
    update("setting", "keyboardmain", $new_keyboard, null, null);
    $setting['keyboardmain'] = $new_keyboard;
    $edit_text = build_main_keyboard_button_edit_text($button_id, $datatextbot, $setting['keyboardmain']);
    $markup = build_main_keyboard_button_edit_markup($button_id, $datatextbot, $setting['keyboardmain']);
    Editmessagetext($from_id, $message_id, $edit_text, $markup);
} elseif (preg_match('/^setmainbtnemoji-(.+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $button_id = $dataget[1];
    if (!in_array($button_id, get_main_keyboard_button_ids(), true)) {
        return;
    }
    $label = get_main_keyboard_button_label($button_id, $datatextbot);
    $parts = split_main_keyboard_button_label($label);
    $title = $parts['title'] !== '' ? $parts['title'] : $label;
    $title_esc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    update("user", "Processing_value", $button_id, "id", $from_id);
    step('setmainbtnemoji', $from_id);
    sendmessage(
        $from_id,
        "✨ Set premium emoji for button <b>{$title_esc}</b>\n\nSend a message that contains a Telegram <b>premium emoji</b> (the custom emoji you send with a Premium account).\n\nThe bot will take the emoji ID from your message and apply it to the menu button.\n\nTo cancel, tap Back.",
        $backadmin,
        'HTML'
    );
} elseif (preg_match('/^clearmainbtnemoji-(.+)$/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $button_id = $dataget[1];
    if (!in_array($button_id, get_main_keyboard_button_ids(), true)) {
        return;
    }
    set_main_keyboard_button_icon($button_id, '');
    $edit_text = build_main_keyboard_button_edit_text($button_id, $datatextbot, $setting['keyboardmain']);
    $markup = build_main_keyboard_button_edit_markup($button_id, $datatextbot, $setting['keyboardmain']);
    Editmessagetext($from_id, $message_id, $edit_text . "\n\n✅ Premium emoji removed.", $markup);
} elseif ($user['step'] == "setmainbtnemoji" && $adminrulecheck['rule'] == "administrator" && $datain === '') {
    $button_id = trim((string) ($user['Processing_value'] ?? ''));
    if (!in_array($button_id, get_main_keyboard_button_ids(), true)) {
        step('home', $from_id);
        sendmessage($from_id, "❌ Invalid button.", $setting_panel, 'HTML');
        return;
    }
    $emoji_id = extract_custom_emoji_id_from_update($update);
    if ($emoji_id === '') {
        $normalized_text_id = normalize_main_keyboard_custom_emoji_id($text);
        $emoji_id = ($normalized_text_id !== null && $normalized_text_id !== '') ? $normalized_text_id : '';
    }
    if ($emoji_id === '') {
        sendmessage(
            $from_id,
            "❌ No premium emoji found in the message.\n\nSend a message that includes a premium (custom) emoji, or send its numeric ID.",
            $backadmin,
            'HTML'
        );
        return;
    }
    if (!set_main_keyboard_button_icon($button_id, $emoji_id)) {
        sendmessage($from_id, "❌ Failed to save the emoji. Check the ID.", $backadmin, 'HTML');
        return;
    }
    strip_unicode_emoji_from_main_keyboard_button_title($button_id, $datatextbot);
    $fresh_text = select('textbot', 'text', 'id_text', $button_id, 'select');
    if (is_array($fresh_text) && !empty($fresh_text['text'])) {
        $datatextbot[$button_id] = $fresh_text['text'];
    }
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    $edit_text = build_main_keyboard_button_edit_text($button_id, $datatextbot, $setting['keyboardmain']);
    $markup = build_main_keyboard_button_edit_markup($button_id, $datatextbot, $setting['keyboardmain']);
    sendmessage($from_id, "✅ Premium emoji saved.\n\n" . $edit_text, $markup, 'HTML');
    sendmessage($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "🤙 Support section" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $supportcenter, 'HTML');
} elseif ($datain == "receipt_bot_confirmed" || $datain == "receipt_admin_confirmed") {
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'cache_time' => 5,
    ]);
    return;
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $order_id = $dataget[1];
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ Confirmed", 'callback_data' => "confirmpaid"],
            ],
            [
                ['text' => "⚙️ Manage user", 'callback_data' => "manageuser_" . $Payment_report['id_user']],
            ]
        ]
    ]);
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "Transaction has been deleted",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $sql = "SELECT * FROM Payment_report WHERE id_user = '{$Payment_report['id_user']}' AND payment_Status != 'paid' AND payment_Status != 'Unpaid' AND payment_Status != 'expire' AND payment_Status != 'reject' AND  (id_invoice  LIKE CONCAT('%','getconfigafterpay', '%') OR id_invoice  LIKE CONCAT('%','getextenduser', '%') OR id_invoice  LIKE CONCAT('%','getextravolumeuser', '%') OR id_invoice  LIKE CONCAT('%','getextratimeuser', '%'))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $countpay = $stmt->rowCount();
    $typepay = explode('|', $Payment_report['id_invoice']);
    if ($countpay > 0 and !in_array($typepay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'])) {
        sendmessage($from_id, "⚠️ To approve user requests, first review and confirm subscription purchase or renewal receipts. Then confirm the wallet top-up receipt. ", null, 'HTML');
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $textconfrom = "✅ Payment was already confirmed by another admin
👤 User ID: <code>{$Balance_id['id']}</code>
🛒 Payment tracking code: {$Payment_report['id_order']}
⚜️ Username: @{$Balance_id['username']}
💎 Balance after confirmation: {$Balance_id['Balance']}
💸 Amount paid: $format_price_cart USD
";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        return;
    }
    DirectPayment($order_id);
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackcart", "select")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) + $result;
        update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
        $pricecashback = number_format($pricecashback);
        $text_report = "🎁 $result USD was added to your account as a deposit bonus.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $Payment_report['price'] = number_format($Payment_report['price']);
    $text_report = "📣 An admin confirmed a payment receipt.
        
Details:
💸 Payment method: {$Payment_report['Payment_Method']}
👤Confirming admin numeric ID: $from_id
💰 Amount paid: {$Payment_report['price']}
👤 User numeric ID: <code>{$Payment_report['id_user']}</code>
👤 User username: @{$Balance_id['username']} 
        Payment tracking code: $order_id";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    update("user", "Processing_value_one", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "none", "id", $Balance_id['id']);
    markAdminReceiptsAdminConfirmed($Payment_report['id_order'], $from_id);
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "Transaction has been deleted",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $id_order);

    sendmessage($from_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $backadmin, 'HTML');
    step('reject-dec', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "reject-dec") {
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ Dear user, your payment was rejected for the following reason.
✍️ $text
🛒 Payment tracking code: {$user['Processing_value_one']}
                ";
    sendmessage($from_id, $textbotlang['Admin']['Payment']['Rejected'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $text_reject, null, 'HTML');
    step('home', $from_id);
    $text_report = "❌ An admin rejected a payment receipt.
        
Details:
💸 Payment method: {$Payment_report['Payment_Method']}
👤Confirming admin numeric ID: $from_id
Confirming admin username: @$username
💰 Amount paid: {$Payment_report['price']}
Rejection reason: $text
👤 User numeric ID: {$Payment_report['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "❌ Remove product" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectloc', $from_id);
} elseif ($user['step'] == "selectloc") {
    update("user", "Processing_value", $text, "id", $from_id);
    step('remove-product', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
} elseif ($user['step'] == "remove-product") {
    if (!rowExists('product', 'name_product', $text)) {
        sendmessage($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM product WHERE name_product =:name_product AND (Location= :Location or Location= '/all')");
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Location', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "✏️ Edit product" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $list_marzban_panel_edit_product, 'HTML');
} elseif (preg_match('/locationedit_(\w+)/', $datain, $dataget)) {
    $location = $dataget[1];
    $location = $location == "all" ? "/all" : $location;
    update("user", "Processing_value_one", $location, "id", $from_id);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Regular user", 'callback_data' => 'typeagenteditproduct_f'],
            ],
            [
                ['text' => "Advanced agent", 'callback_data' => 'typeagenteditproduct_n2'],
                ['text' => "Regular agent", 'callback_data' => 'typeagenteditproduct_n'],
            ],
            [
                ['text' => "Back", 'callback_data' => "admin"]
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Choose the user type", $Response);
} elseif (preg_match('/^typeagenteditproduct_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    update("user", "Processing_value_tow", $typeagent, "id", $from_id);
    $product = [];
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $getdataproduct = mysqli_query($connect, "SELECT * FROM product WHERE (Location = '{$panel['name_panel']}' or Location = '/all') AND agent = '$typeagent'");
    $list_product = [
        'inline_keyboard' => [],
    ];
    if (isset($getdataproduct)) {
        while ($row = mysqli_fetch_assoc($getdataproduct)) {
            $list_product['inline_keyboard'][] = [
                telegram_button_with_icon(
                    ['text' => $row['name_product'], 'callback_data' => "productedit_" . $row['id']],
                    $row['emoji_id'] ?? ''
                )
            ];
        }
        $list_product['inline_keyboard'][] = [
            ['text' => "🏠 Back to previous menu", 'callback_data' => "locationedit_" . $user['Processing_value_one']],
        ];

        $json_list_product_list_admin = json_encode($list_product);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin);
} elseif (preg_match('/^productedit_(\w+)/', $datain, $dataget)) {
    $id_product = $dataget[1];
    deletemessage($from_id, $message_id);
    update("user", "Processing_value", $id_product, "id", $from_id);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $info_product = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM product WHERE id = '$id_product'  AND agent = '{$user['Processing_value_tow']}' AND (Location = '{$panel['name_panel']}' OR Location = '/all') LIMIT 1"));
    $count_invoice = select("invoice", "*", "name_product", $info_product['name_product'], "count");
    $hwidDisplay = ($info_product['hwid_limit'] === null || $info_product['hwid_limit'] === '') ? 'Unlimited' : $info_product['hwid_limit'];
    $infoproduct = "
📌 Product being edited:
Product name:  {$info_product['name_product']}
Product price: {$info_product['price_product']}
Product volume: {$info_product['Volume_constraint']}
Product location: {$info_product['Location']}
Product duration: {$info_product['Service_time']}
Product user type: {$info_product['agent']}
Periodic volume reset: {$info_product['data_limit_reset']}
Device limit (HWID): {$hwidDisplay}
Product note: {$info_product['note']}
Product category: {$info_product['category']}
Sold count: $count_invoice
    ";
    sendmessage($from_id, $infoproduct, $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "Price" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new price", $backadmin, 'HTML');
    step('change_price', $from_id);
} elseif ($user['step'] == "change_price") {
    if (!is_valid_money_input($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    $price_product = (string) money_amount($text);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET price_product = :price_product WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':price_product', $price_product);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ Product price updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Note" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new note", $backadmin, 'HTML');
    step('change_note', $from_id);
} elseif ($user['step'] == "change_note") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET note = :notes WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':notes', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ Product note updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Device limit (HWID)" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new device limit (HWID).\nPositive number = max allowed devices\n«-» = no limit", $backadmin, 'HTML');
    step('change_hwid_limit', $from_id);
} elseif ($user['step'] == "change_hwid_limit") {
    $hwid_limit = null;
    $trimmed = trim($text);
    if ($trimmed !== '' && $trimmed !== '-') {
        if (!ctype_digit($trimmed) || (int) $trimmed <= 0) {
            sendmessage($from_id, "❌ Invalid value. Send a positive number or «-» for no limit.", $backadmin, 'HTML');
            return;
        }
        $hwid_limit = (int) $trimmed;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET hwid_limit = :hwid_limit WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindValue(':hwid_limit', $hwid_limit, $hwid_limit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ Product device limit (HWID) updated", $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "Category" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Choose the new category name", KeyboardCategoryadmin(), 'HTML');
    step('change_categroy', $from_id);
} elseif ($user['step'] == "change_categroy") {
    $resolved = resolve_category_from_update($update);
    if (!$resolved['ok']) {
        sendmessage($from_id, category_resolve_error_text($resolved['error']), KeyboardCategoryadmin(), 'HTML');
        return;
    }
    $categoryRemark = (string) $resolved['category']['remark'];
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET category = :categroy WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':categroy', $categoryRemark);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ Product category updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Product name" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new name\n✨ If you send a premium emoji, it will show on the product button.", $backadmin, 'HTML');
    step('change_name', $from_id);
} elseif ($user['step'] == "change_name") {
    $parsed = button_label_and_icon_from_update($update);
    $productName = $parsed['text'] !== '' ? $parsed['text'] : (string) $text;
    if (strlen($productName) > 150) {
        sendmessage($from_id, "❌ Product name must be under 150 characters", $backadmin, 'HTML');
        return;
    }
    if ($productName === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $backadmin, 'HTML');
        return;
    }
    $currentProduct = select("product", "*", "id", $user['Processing_value'], "select");
    $currentName = is_array($currentProduct) ? (string) ($currentProduct['name_product'] ?? '') : '';
    if (rowExists('product', 'name_product', $productName) && $productName !== $currentName) {
        sendmessage($from_id, "❌ A product named $productName already exists", $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    ensure_shop_button_emoji_columns();
    $stmt = $pdo->prepare("UPDATE product SET name_product = :name_products, emoji_id = :emoji_id WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':name_products', $productName);
    $emoji_id = stored_custom_emoji_id($parsed['emoji_id']);
    $stmt->bindValue(':emoji_id', $emoji_id !== '' ? $emoji_id : null, $emoji_id !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅Product name updated", $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "User type" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new user type:
User types: f , n , n2", $backadmin, 'HTML');
    step('change_type_agent', $from_id);
} elseif ($user['step'] == "change_type_agent") {
    if (!in_array($text, ['f', 'n', 'n2'])) {
        sendmessage($from_id, "❌ Invalid user group", null, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET agent = :agents WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':agents', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅Product name updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Volume reset type" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the volume reset type", $keyboardtimereset, 'HTML');
    step('change_reset_data', $from_id);
} elseif ($user['step'] == "change_reset_data") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET data_limit_reset = :data_limit_reset WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':data_limit_reset', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅Product name updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Product location" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Choose the new product location", $json_list_marzban_panel, 'HTML');
    step('change_loc_data', $from_id);
} elseif ($user['step'] == "change_loc_data") {
    if ($text == "/all") {
        sendmessage($from_id, "❌ You cannot change a defined product to location /all.", $shopkeyboard, 'HTML');
        return;
    }
    $product = select("product", "*", "name_product", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Location = :Location2 WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Location2', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("UPDATE invoice SET Service_location = :Service_location WHERE name_product = :name_product AND Service_location = :Location ");
    $stmt->bindParam(':Service_location', $text);
    $stmt->bindParam(':name_product', $product['name_product']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->execute();
    sendmessage($from_id, "✅Product location updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Volume" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new volume", $backadmin, 'HTML');
    step('change_val', $from_id);
} elseif ($user['step'] == "change_val") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $product = select("product", "*", "id", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $stmt = $pdo->prepare("UPDATE product SET Volume_constraint = :Volume_constraint WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Volume_constraint', $text);
    $stmt->bindParam(':name_product', $product['id']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['volumeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "Time" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
    step('change_time', $from_id);
} elseif ($user['step'] == "change_time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Service_time = :Service_time WHERE id = :id_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Service_time', $text);
    $stmt->bindParam(':id_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "balanceaddall") {
    $keyboardbulkcharge = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💰 Top up user wallets", 'callback_data' => "bulkcharge_wallet"],
            ],
            [
                ['text' => "🔋 Increase service volume or time", 'callback_data' => "bulkcharge_service"],
            ],
            [
                ['text' => "Back to main menu", 'callback_data' => "backuser"],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Choose the bulk charge type.", $keyboardbulkcharge);
    step('home', $from_id);
} elseif ($datain == "bulkcharge_wallet") {
    sendmessage($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
    step('add_Balance_all', $from_id);
} elseif ($datain == "bulkcharge_service") {
    $giftQueueBusy = false;
    foreach (['cronbot/username.json', 'cronbot/users.json'] as $queueFile) {
        if (!is_file($queueFile)) {
            continue;
        }
        $queuedItems = json_decode(file_get_contents($queueFile), true);
        if (is_array($queuedItems) && count($queuedItems) > 0) {
            $giftQueueBusy = true;
            break;
        }
    }
    if ($giftQueueBusy || is_file('cronbot/gift') || is_file('cronbot/info')) {
        sendmessage($from_id, "❌ A bulk operation is already running. Try again after it finishes.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "bulkcharge_mode", "service");
    $keyboardagent = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All users", 'callback_data' => 'servicechargeagent_all'],
            ],
            [
                ['text' => "Group f users", 'callback_data' => 'servicechargeagent_f'],
                ['text' => "Group n users", 'callback_data' => 'servicechargeagent_n'],
                ['text' => "Group n2 users", 'callback_data' => 'servicechargeagent_n2'],
            ],
            [
                ['text' => "Back", 'callback_data' => 'balanceaddall'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Which user group's services should be charged?", $keyboardagent);
} elseif (preg_match('/^servicechargeagent_(all|f|n|n2)$/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (($userdata['bulkcharge_mode'] ?? '') !== 'service') {
        sendmessage($from_id, "❌ Operation data is incomplete. Start the steps from the beginning.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $dataget[1]);
    $keyboardcustomers = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Users who have purchased", 'callback_data' => 'servicechargecustomers'],
            ],
            [
                ['text' => "Back", 'callback_data' => 'bulkcharge_service'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Choose the customer group.", $keyboardcustomers);
} elseif ($datain == "servicechargecustomers") {
    $userdata = json_decode($user['Processing_value'], true);
    if (($userdata['bulkcharge_mode'] ?? '') !== 'service' || !isset($userdata['agent'])) {
        sendmessage($from_id, "❌ Operation data is incomplete. Start the steps from the beginning.", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typecustomer", "customer");
    $keyboardservicetype = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "📦 Volume-based users", 'callback_data' => 'servicechargetype_volume'],
            ],
            [
                ['text' => "♾ Unlimited users", 'callback_data' => 'servicechargetype_day'],
            ],
            [
                ['text' => "Back", 'callback_data' => 'servicechargeagent_' . $userdata['agent']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Choose the users' service type.", $keyboardservicetype);
} elseif (preg_match('/^servicechargetype_(volume|day)$/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (($userdata['bulkcharge_mode'] ?? '') !== 'service' || ($userdata['typecustomer'] ?? '') !== 'customer') {
        sendmessage($from_id, "❌ Operation data is incomplete. Start the steps from the beginning.", $keyboardadmin, 'HTML');
        return;
    }
    $serviceChargeType = $dataget[1];
    savedata("save", "typegift", $serviceChargeType);
    deletemessage($from_id, $message_id);
    if ($serviceChargeType === "volume") {
        sendmessage($from_id, "📌 How many GB should be added to all active volume-based services?", $backadmin, 'HTML');
    } else {
        sendmessage($from_id, "📌 How many days should be added to all active unlimited services?", $backadmin, 'HTML');
    }
    step("getbulkservicevalue", $from_id);
} elseif ($user['step'] == "getbulkservicevalue") {
    $userdata = json_decode($user['Processing_value'], true);
    if (($userdata['bulkcharge_mode'] ?? '') !== 'service' || !in_array($userdata['typegift'] ?? '', ['volume', 'day'], true)) {
        sendmessage($from_id, "❌ Operation data is incomplete. Start the steps from the beginning.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!ctype_digit((string) $text) || intval($text) <= 0) {
        sendmessage($from_id, "❌ Value must be an integer greater than zero.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "value", intval($text));
    sendmessage($from_id, "📌 Send the message to send after each successful service charge.", $backadmin, 'HTML');
    step("getbulkservicemessage", $from_id);
} elseif ($user['step'] == "getbulkservicemessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (($userdata['bulkcharge_mode'] ?? '') !== 'service' || !isset($userdata['value'])) {
        sendmessage($from_id, "❌ Operation data is incomplete. Start the steps from the beginning.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!$text || trim($text) === '') {
        sendmessage($from_id, "❌ The message cannot be empty.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "text", $text);
    savedata("save", "id_admin", $from_id);
    $typeLabel = ($userdata['typegift'] === 'volume') ? 'Volume-based users' : 'Unlimited users';
    $unitLabel = ($userdata['typegift'] === 'volume') ? 'GB' : 'days';
    $agentLabel = ($userdata['agent'] === 'all') ? 'All groups' : $userdata['agent'];
    $keyboardconfirm = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ Confirm and start", 'callback_data' => 'startbulkservicecharge'],
            ],
            [
                ['text' => "Cancel", 'callback_data' => 'balanceaddall'],
            ],
        ]
    ]);
    $safeMessage = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    sendmessage(
        $from_id,
        "📌 Confirm bulk service charge\n\n"
        . "👥 User type: {$typeLabel}\n"
        . "🗂 User group: {$agentLabel}\n"
        . "➕ Amount: {$userdata['value']} {$unitLabel}\n"
        . "💬 Message:\n<blockquote>{$safeMessage}</blockquote>",
        $keyboardconfirm,
        'HTML'
    );
    step("home", $from_id);
} elseif ($datain == "startbulkservicecharge") {
    $userdata = json_decode($user['Processing_value'], true);
    $requiredKeys = ['bulkcharge_mode', 'agent', 'typecustomer', 'typegift', 'value', 'text'];
    foreach ($requiredKeys as $requiredKey) {
        if (!isset($userdata[$requiredKey])) {
            sendmessage($from_id, "❌ Operation data is incomplete. Start the steps from the beginning.", $keyboardadmin, 'HTML');
            return;
        }
    }
    if ($userdata['bulkcharge_mode'] !== 'service'
        || $userdata['typecustomer'] !== 'customer'
        || !in_array($userdata['typegift'], ['volume', 'day'], true)
        || intval($userdata['value']) <= 0
    ) {
        sendmessage($from_id, "❌ Invalid operation data. Start the steps from the beginning.", $keyboardadmin, 'HTML');
        return;
    }
    foreach (['cronbot/username.json', 'cronbot/users.json', 'cronbot/gift', 'cronbot/info'] as $queueFile) {
        if (!is_file($queueFile)) {
            continue;
        }
        $queueBusy = true;
        if (str_ends_with($queueFile, '.json')) {
            $queuedItems = json_decode(file_get_contents($queueFile), true);
            $queueBusy = is_array($queuedItems) && count($queuedItems) > 0;
        }
        if ($queueBusy) {
            sendmessage($from_id, "❌ Another bulk operation is already running.", $keyboardadmin, 'HTML');
            return;
        }
    }
    $sql = "SELECT i.id_invoice, i.id_user, i.username, i.Service_location
            FROM invoice i
            INNER JOIN user u ON u.id = i.id_user
            WHERE i.Status = 'active'
              AND i.name_product != 'سرویس تست'
              AND u.User_Status = 'Active'";
    $params = [];
    if ($userdata['agent'] !== 'all') {
        $sql .= " AND u.agent = :agent";
        $params[':agent'] = $userdata['agent'];
    }
    if ($userdata['typegift'] === 'volume') {
        $sql .= " AND CAST(COALESCE(NULLIF(i.Volume, ''), '0') AS UNSIGNED) > 0";
    } else {
        $sql .= " AND CAST(COALESCE(NULLIF(i.Volume, ''), '0') AS UNSIGNED) = 0
                  AND CAST(COALESCE(NULLIF(i.Service_time, ''), '0') AS UNSIGNED) > 0";
    }
    $sql .= " ORDER BY i.id_invoice";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$services) {
        sendmessage($from_id, "❌ No active service matching the selected filter was found.", $keyboardadmin, 'HTML');
        return;
    }
    $cancelKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌ Cancel operation", 'callback_data' => 'cancel_gift'],
            ],
        ]
    ]);
    $messageResult = Editmessagetext(
        $from_id,
        $message_id,
        "✅ Operation started for " . count($services) . " services. You will be notified when it finishes.",
        $cancelKeyboard
    );
    $userdata['id_admin'] = $from_id;
    $userdata['id_message'] = $messageResult['result']['message_id'] ?? $message_id;
    $userdata['bulk_service_charge'] = true;
    $userdata['total'] = count($services);
    $userdata['success_count'] = 0;
    $userdata['failed_count'] = 0;
    $userdata['skipped_count'] = 0;
    file_put_contents('cronbot/gift', json_encode($userdata, JSON_UNESCAPED_UNICODE), LOCK_EX);
    file_put_contents('cronbot/username.json', json_encode($services, JSON_UNESCAPED_UNICODE), LOCK_EX);
} elseif ($user['step'] == "add_Balance_all") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    savedata("clear", "price", $text);
    $keyboardagent = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All users", 'callback_data' => 'typebalanceall_all'],
            ],
            [
                ['text' => "Group f users", 'callback_data' => 'typebalanceall_f'],
                ['text' => "Group n users", 'callback_data' => 'typebalanceall_nl'],
                ['text' => "Group n2 users", 'callback_data' => 'typebalanceall_n2'],
            ],
            [
                ['text' => "Back to main menu", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, "📌 Which user group should receive the top-up.", $keyboardagent, 'HTML');
} elseif (preg_match('/typebalanceall_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    savedata("save", "agent", $typeagent);
    $keyboardtypeuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All users", 'callback_data' => 'typecustomer_all'],
            ],
            [
                ['text' => "Users who purchased", 'callback_data' => 'typecustomer_customer'],
            ],
            [
                ['text' => "Users who did not purchase", 'callback_data' => 'typecustomer_notcustomer'],
            ],
            [
                ['text' => "Back to main menu", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Which users should receive the bulk top-up", $keyboardtypeuser);
} elseif (preg_match('/typecustomer_(\w+)/', $datain, $dataget)) {
    $typecustomer = $dataget[1];
    savedata("save", "typecustomer", $typecustomer);
    sendmessage($from_id, "📌 Should a top-up message be sent to users?
Yes: 1 
No: 0", $backadmin, 'HTML');
    step("getmeesagestatus", $from_id);
} elseif ($user['step'] == "getmeesagestatus") {
    $userdata = json_decode($user['Processing_value'], true);
    sendmessage($from_id, $textbotlang['Admin']['Balance']['AddBalanceUsers'], $keyboardadmin, 'HTML');
    $query_where = "";
    if ($userdata['agent'] == "all") {
        if ($userdata['typecustomer'] == "all") {
            $query_where = "";
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = "WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = "WHERE  NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    } else {
        if ($userdata['typecustomer'] == "all") {
            $query_where = null;
            ;
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    }
    $stmt = $pdo->prepare("SELECT u.id FROM user u " . $query_where);
    $stmt->execute();
    $Balance_user = $stmt->fetchAll();
    $stmt = $pdo->prepare("UPDATE user as u SET  Balance = Balance + {$userdata['price']} " . $query_where);
    $stmt->execute();
    $bulkPrice = (int) $userdata['price'];
    if ($bulkPrice > 0) {
        foreach ($Balance_user as $row) {
            record_admin_balance_payment($pdo, $row['id'] ?? null, $bulkPrice, 'add balance by admin');
        }
    }
    step('home', $from_id);
    if ($text == "1") {
        $cancelmessage = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Cancel operation", 'callback_data' => 'cancel_sendmessage'],
                ],
            ]
        ]);
        $textgift = "🎁 Dear user, {$userdata['price']} USD has been added to your wallet as a gift from management.";
        $message_id = sendmessage($from_id, "✅ Broadcast started. You will be notified when it finishes.", $cancelmessage, "html");
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $textgift,
            "pingmessage" => "no",
            "btnmessage" => "start"
        ));
        file_put_contents("cronbot/users.json", json_encode($Balance_user));
        file_put_contents('cronbot/info', $data);
    }
} elseif ($text == "⬇️ Decrease balance") {
    sendmessage($from_id, $textbotlang['Admin']['Balance']['NegativeBalance'], $backadmin, 'HTML');
    step('Negative_Balance', $from_id);
} elseif ($user['step'] == "Negative_Balance") {
    if (!rowExists('user', 'id', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Balance']['PriceBalancek'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('get_price_Negative', $from_id);
} elseif ($user['step'] == "get_price_Negative") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) >= 100000000) {
        sendmessage($from_id, "📌 Maximum amount is 100 million rials.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Balance']['NegativeBalanceUser'], $keyboardadmin, 'HTML');
    $Balance_usersa = select("user", "*", "id", $user['Processing_value'], "select");
    $Balance_Low_userkam = $Balance_usersa['Balance'] - $text;
    update("user", "Balance", $Balance_Low_userkam, "id", $user['Processing_value']);
    record_admin_balance_payment($pdo, $user['Processing_value'], (int) $text, 'low balance by admin');
    $balances1 = number_format($text, 0);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $textkam = "❌ $balances1 USD was deducted from your wallet.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 An admin decreased a user's balance:
        
🪪 Admin who decreased the balance: 
Username: @$username
Numeric ID: $from_id
👤 User info:
User numeric ID: {$user['Processing_value']}
Amount: $text
User balance after decrease: $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchuser" || $text == "👁‍🗨 Search user") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
    step('show_info', $from_id);
} elseif ($datain == "manageuserbyforward") {
    sendmessage($from_id, "Forward a message from the user's Telegram account to the bot.", $backadmin, 'HTML');
    step('show_info_forward', $from_id);
} elseif ($user['step'] == "show_info" || $user['step'] == "show_info_forward" || preg_match('/manageuser_(\w+)/', $datain, $dataget) || preg_match('/updateinfouser_(\w+)/', $datain, $dataget) || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
    if ($user['step'] == "show_info_forward") {
        if (intval($forward_from_id) === 0) {
            sendmessage($from_id, "This account's ID is hidden in the forwarded message. Ask the user to disable forward privacy and try again.", $backadmin, 'HTML');
            return;
        }
        $id_user = (string) $forward_from_id;
    } elseif ($user['step'] == "show_info") {
        $id_user = $text;
    } elseif (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } elseif (explode(" ", $text)[0] == "/id") {
        $id_user = explode(" ", $text)[1];
    } else {
        $id_user = $dataget[1];
    }
    if (!rowExists('user', 'id', $id_user)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    $date = date("Y-m-d");
    $dayListSell = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user'"));
    $balanceall = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = '$id_user' AND Payment_Method != 'low balance by admin'"));
    $subbuyuser = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user'"));
    $invoicecount = select("invoice", '*', "id_user", $id_user, "count");
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $sumvolume = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND name_product != 'سرویس تست'"));
    }
    $affiliatesBoughtCount = (int) (mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(DISTINCT u.id) AS cnt FROM user u INNER JOIN invoice i ON i.id_user = u.id WHERE u.affiliates = '$id_user' AND i.name_product != 'سرویس تست' AND i.Status NOT IN ('Unpaid','unpaid','reject','removebyadmin','removedbyadmin')"))['cnt'] ?? 0);
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $keyboardmanage = [
        'inline_keyboard' => [
            [['text' => "♻️  Refresh info", 'callback_data' => "updateinfouser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => "addbalanceuser_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => "lowbalanceuser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => "banuserlist_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => "unbanuserr_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => "addagent_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => "removeagent_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => "confirmnumber_" . $id_user]],
            [['text' => "🎁 Discount percent", 'callback_data' => "Percentlow_" . $id_user], ['text' => "✍️ Send message to user", 'callback_data' => "sendmessageuser_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => "vieworderuser_" . $id_user]],
            [['text' => "👥 User referrals", 'callback_data' => "affiliates-" . $id_user]],
            [['text' => "🔄 Remove from referral", 'callback_data' => "removeaffiliate-" . $id_user], ['text' => "🔄 Delete user's referrals", 'callback_data' => "removeaffiliateuser-" . $id_user]],
            [['text' => "💳 Enable card number", 'callback_data' => "showcarduser-" . $id_user]],
            [['text' => "Verify user", 'callback_data' => "verify_" . $id_user], ['text' => "Unverify user", 'callback_data' => "unverify-" . $id_user]],
            [['text' => "💳  Disable card number", 'callback_data' => "carduserhide-" . $id_user]],
            [['text' => "🛒 Add order", 'callback_data' => "addordermanualـ" . $id_user], ['text' => "➕ Test account limit", 'callback_data' => "limitusertest_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => "viewpaymentuser_" . $id_user], ['text' => "Transfer account ", 'callback_data' => "transferaccount_" . $id_user]],
            [['text' => "💡 Disable account", 'callback_data' => "disableconfig-" . $id_user], ['text' => "💡 Enable account", 'callback_data' => "activeconfig-" . $id_user]],
            [['text' => "📑 Verify channel membership", 'callback_data' => "confirmchannel-" . $id_user], ['text' => "0️⃣ Zero the balance", 'callback_data' => "zerobalance-" . $id_user]],
            [['text' => "🕚 Cron message status", 'callback_data' => "statuscronuser-" . $id_user]],
        ]
    ];
    if ($user['agent'] == "n2")
        $keyboardmanage['inline_keyboard'][] = [['text' => "Agent purchase cap", 'callback_data' => "maxbuyagent_" . $id_user]];
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🤖 Enable sales bot", 'callback_data' => "createbot_" . $id_user],
            ['text' => "❌ Delete sales bot", 'callback_data' => "removebotsell_" . $id_user]
        ];
    }
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🔋 Base volume price", 'callback_data' => "setvolumesrc_" . $id_user],
            ['text' => "⏳ Base time price", 'callback_data' => "settimepricesrc_" . $id_user]
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "❌ Hide a panel for the agent", 'callback_data' => "hidepanel_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🗑 Show hidden panels", 'callback_data' => "removehide_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "⏱️ Agency expiry", 'callback_data' => "expireset_" . $id_user],
        ];
    }
    if (intval($setting['statuslimitchangeloc']) == 1) {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "Location-change limit", 'callback_data' => "changeloclimitbyuser_" . $id_user]
        ];
    }
    $keyboardmanage = json_encode($keyboardmanage, JSON_UNESCAPED_UNICODE);
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "Unknown";
    }
    $userverify = [
        '0' => "Not verified",
        '1' => "Verified"
    ][$user['verify']];
    $showcart = [
        '0' => "Hidden",
        '1' => "Shown"
    ][$user['cardpayment']];
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    if ($user['agent'] != "f" && $user['expire'] != null) {
        $text_expie_agent = "⭕️ Agency end date: " . jdate('Y/m/d H:i:s', $user['expire']);
    } else {
        $text_expie_agent = "";
    }
    $text_agent_volume = "";
    if (agent_is_reseller($user['agent'] ?? 'f')) {
        $volConsumedAgent = agent_sum_volume_consumed($id_user, $user['agent']);
        $volConsumedFmt = number_format($volConsumedAgent);
        $text_agent_volume = "🔋 Total volume used to create volume-based services: {$volConsumedFmt} GB\n";
        if (($user['agent'] ?? '') === 'n') {
            $volRem = number_format((int) ($user['agent_volume_remaining'] ?? 0));
            $ppg = number_format((int) ($user['agent_price_per_gb'] ?? 0));
            $text_agent_volume .= "🔋 Remaining agency volume: {$volRem} GB\n🔋 Agency price per GB: {$ppg} USD\n";
        }
    }
    $textinfouser = "👀 User info:

🔗 Account info

⭕️ User status: {$user['User_Status']}
⭕️ Username: @{$user['username']}
⭕️ Numeric ID:  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ Invite code: {$user['codeInvitation']}
⭕️ Join time: $userjoin
⭕️ Last time the user used the bot: $lastmessage
⭕️ Test account limit:  {$user['limit_usertest']} 
⭕️ Terms confirmation: $roll_Status
⭕️ Phone number: <code>{$user['number']}</code>
⭕️ User type: {$user['agent']}
⭕️ Referral count: {$user['affiliatescount']}
⭕️ Referrals who purchased a service: $affiliatesBoughtCount
⭕  Referrer: {$user['affiliates']}
⭕  Verification status: $userverify   
⭕  Show card number: $showcart
⭕ User score: {$user['score']}
⭕️  Total active purchased volume (for accurate volume stats, cron must be on): {$sumvolume['SUM(Volume)']}
$text_agent_volume$text_expie_agent

💎 Financial reports

🔰 User balance: {$user['Balance']}
🔰 Total purchases: {$dayListSell['COUNT(*)']}
🔰️ Total paid:  {$balanceall['SUM(price)']}
🔰 Total purchase amount: {$subbuyuser['SUM(price_product)']}
🔰 User discount percent: {$user['pricediscount']}
🔰 Sales in the last hour: $listhours
🔰 Sales amount in the last hour: $suminvoicehours USD
🔰 Sales in the last month: $listmonth
🔰 Sales amount in the last month: $suminvoicemonth USD

";
    if (isset($datain[0]) && $datain[0] == "u") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "Info updated",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
    } else {
        sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
} elseif ($text == "🎁 Create gift code" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['GetCode'], $backadmin, 'HTML');
    step('get_code', $from_id);
} elseif ($user['step'] == "get_code") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO Discount (code, limitused) VALUES (:code, :limitused)");
    $value = "0";
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->bindParam(':limitused', $value, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['PriceCode'], null, 'HTML');
    step('get_price_code', $from_id);
    update("user", "Processing_value", $text, "id", $from_id);
} elseif ($user['step'] == "get_price_code") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Discount']['setlimituse'], $backadmin, 'HTML');
    update("Discount", "price", $text, "code", $user['Processing_value']);
    step('getlimitcodedis', $from_id);
} elseif ($user['step'] == "getlimitcodedis") {
    step("home", $from_id);
    update("Discount", "limituse", $text, "code", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['SaveCode'], $keyboardadmin, 'HTML');
} elseif ($text == "❌ Remove gift code" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin, 'HTML');
    step('remove-Discount', $from_id);
} elseif ($user['step'] == "remove-Discount") {
    if (!rowExists('Discount', 'code', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Discount WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "🗑 Remove protocol" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Protocol']['RemoveProtocol'], $keyboardprotocollist, 'HTML');
    step('removeprotocol', $from_id);
} elseif ($user['step'] == "removeprotocol") {
    if (!in_array($text, $protocoldata)) {
        sendmessage($from_id, $textbotlang['Admin']['Protocol']['invalidProtocol'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Protocol']['RemovedProtocol'], $optionMarzban, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM protocol WHERE NameProtocol = :protocol");
    $stmt->bindParam(':protocol', $text, PDO::PARAM_STR);
    $stmt->execute();
    step('home', $from_id);
} elseif ($text == "💡 Username generation method" && $adminrulecheck['rule'] == "administrator") {
    $text_username = "⭕️ Choose how usernames are generated for accounts from the buttons below.
        
⚠️ If a user has no username, the word you choose will be used instead of the username.
        
⚠️ If the username already exists, a random number will be appended";
    sendmessage($from_id, $text_username, $MethodUsername, 'HTML');
    step('updatemethodusername', $from_id);
} elseif ($user['step'] == "updatemethodusername") {
    update("marzban_panel", "MethodUsername", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($text == "متن دلخواه + عدد رندوم" || $text == "متن دلخواه + عدد ترتیبی" || $text == "متن دلخواه نماینده + عدد ترتیبی") {
        step('getnamecustom', $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $backadmin, 'HTML');
        return;
    }
    if ($text == "نام کاربری + عدد به ترتیب") {
        step('getnamecustom', $from_id);
        sendmessage($from_id, "📌 If the user has no username, what name should be saved?", $backadmin, 'HTML');
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['AlgortimeUsername']['SaveData']);
    step('home', $from_id);
} elseif ($user['step'] == "getnamecustom") {
    if (!panel_username_prefix_valid($text)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $backadmin, 'html');
        return;
    }
    update("marzban_panel", "namecustom", $text, "name_panel", $user['Processing_value']);
    step('getnamecustom_test', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['customnamesend_test'], $backadmin, 'HTML');
} elseif ($user['step'] == "getnamecustom_test") {
    if ($text === '-') {
        update("marzban_panel", "namecustom_test", 'none', "name_panel", $user['Processing_value']);
    } else {
        if (!panel_username_prefix_valid($text)) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $backadmin, 'html');
            return;
        }
        update("marzban_panel", "namecustom_test", $text, "name_panel", $user['Processing_value']);
    }
    step('home', $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
} elseif (($datain == "cartsetting" && $adminrulecheck['rule'] == "administrator") || $text == "▶️ Back to card settings") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
} elseif ($text == "💳 Set card number" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "💳 Send your card number

⚠️ You can define multiple card numbers. If several are defined, the user will be shown one of them at random";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('changecard', $from_id);
} elseif ($user['step'] == "changecard") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌Card number must be digits only.", $backuser, 'HTML');
        return;
    }
    if (rowExists('card_number', 'cardnumber', $text)) {
        sendmessage($from_id, "❌ This card number already exists in the database.", $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['getnamecard'], $backuser, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('getnamecard', $from_id);
} elseif ($user['step'] == "getnamecard") {
    try {
        if (function_exists('ensureCardNumberTableSupportsUnicode')) {
            ensureCardNumberTableSupportsUnicode();
        }

        $stmt = $connect->prepare("INSERT INTO card_number (cardnumber,namecard) VALUES (?,?)");
        $stmt->bind_param("ss", $user['Processing_value'], $text);
        $stmt->execute();
        $stmt->close();
        sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['Savacard'], $CartManage, 'HTML');
        step('home', $from_id);
    } catch (\mysqli_sql_exception $e) {
        error_log('Failed to save card number: ' . $e->getMessage());
        if (stripos($e->getMessage(), 'Incorrect string value') !== false) {
            error_log('card_number insert failed due to charset mismatch. Please verify the table collation.');
        }
        sendmessage($from_id, "❌ Failed to save the card number. Please try again or contact support.", $backadmin, 'HTML');
        step('home', $from_id);
    }
} elseif ($datain == "plisiosetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $NowPaymentsManage, 'HTML');
} elseif ($text == "🧩 api plisio" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apinowpayment")['ValuePay'];
    $textcart = "⚙️ Send the plisio.net.io site API
        
        plisio api: $PaySetting";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('apinowpayment', $from_id);
} elseif ($user['step'] == "apinowpayment") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $NowPaymentsManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apinowpayment");
    step('home', $from_id);
} elseif ($datain == "iranpay1setting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $Swapinokey, 'HTML');
} elseif ($text == "API NOWPAYMENT") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller")['ValuePay'];
    $texttronseller = "💳 Get your NOWPAYMENT API and enter it here
        
 your current api: $PaySetting";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('marchent_tronseller', $from_id);
} elseif ($user['step'] == "marchent_tronseller") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_tronseller");
    step('home', $from_id);
} elseif ($datain == "aqayepardakhtsetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $aqayepardakht, 'HTML');
} elseif ($datain == "zarinpalsetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Choose an option", $keyboardzarinpal, 'HTML');
} elseif ($datain == "tetraminatorsetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Choose an option
⚠️ Set the API Key in config.php ($tetraminator_api_key)", $keyboardtetraminator, 'HTML');
} elseif ($text == "Set Aghaye Pardakht merchant" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht")['ValuePay'];
    $textaqayepardakht = "💳 Get your merchant code from Aghaye Pardakht and enter it here
        
Your current merchant code: $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_id_aqayepardakht', $from_id);
} elseif ($user['step'] == "merchant_id_aqayepardakht") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $aqayepardakht, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_id_aqayepardakht");
    step('home', $from_id);
} elseif ($text == "Zarinpal merchant" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal")['ValuePay'];
    $textaqayepardakht = "💳 Get your merchant code from Zarinpal and enter it here
        
Your current merchant code: $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_zarinpal', $from_id);
} elseif ($user['step'] == "merchant_zarinpal") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardzarinpal, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_zarinpal");
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
    step('GetLocationEdit', $from_id);
} elseif ($user['step'] == "GetLocationEdit") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $text, "select");
    if ($marzban_list_get['type'] == "marzban") {
        $Check_token = token_panel($marzban_list_get['code_panel'], false);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Stats($text);
            if ($marzban_list_get['version_panel'] == "1") {
                $active_users = $System_Stats['active_users']
                    ?? $System_Stats['users_active']
                    ?? $System_Stats['online_users']
                    ?? 0;
            } else {
                $active_users = $System_Stats['users_active']
                    ?? $System_Stats['active_users']
                    ?? $System_Stats['online_users']
                    ?? 0;
            }
            $total_user = $System_Stats['total_user'];
            $mem_total = formatBytes($System_Stats['mem_total']);
            $mem_used = formatBytes($System_Stats['mem_used']);
            $bandwidth = formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
            $ListSell = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['COUNT(*)'] ?? 0);
            $ListSellSUM = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['SUM(price_product)'] ?? 0);

            $Condition_marzban = "";
            $text_marzban = "
Your panel stats👇:
                             
🖥 Marzban panel connection: ✅ Panel is connected
👥  Total users: $total_user
👤 Active users: $active_users
📡 Marzban panel version:  {$System_Stats['version']}
💻 Total server RAM: $mem_total
💻 Marzban panel RAM usage: $mem_used
🌐 Total traffic used (upload / download): $bandwidth
🛍 Total sales on this panel: $ListSell
🛍 Total sales amount on this panel: $ListSellSUM USD
User group: {$marzban_list_get['agent']}
        
⭕️ Choose one of the options below to manage the panel";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ Panel username or password is incorrect";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'] . json_encode($Check_token);
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionX_ui_single, 'HTML');
        } elseif ($x_ui_check_connect['msg'] == "Invalid username or password.") {
            $text_marzban = "❌ Panel username or password is incorrect";
            sendmessage($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'] . "Error reason: \n{$x_ui_check_connect['msg']}";
            sendmessage($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "alireza_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionalireza_single, 'HTML');
        } elseif ($x_ui_check_connect['msg'] == "The username or password is incorrect") {
            $text_marzban = "❌ Panel username or password is incorrect";
            sendmessage($from_id, $text_marzban, $optionalireza_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'] . "Error reason {$x_ui_check_connect['errror']}";
            sendmessage($from_id, $text_marzban, $optionalireza_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "hiddify") {
        $System_Stats = serverstatus($marzban_list_get['name_panel']);
        if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
            $text_marzban = "❌ An error occurred while fetching info. Error code: " . $System_Stats['status'];
            sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } elseif (!empty($System_Stats['error'])) {
            $text_marzban = "❌ An error occurred while fetching info. Error: " . $System_Stats['error'];
            sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } else {
            $System_Stats = json_decode($System_Stats['body'], true);
            if (isset($System_Stats['stats'])) {
                $mem_total = round($System_Stats['stats']['system']['ram_total'], 2);
                $mem_used = round($System_Stats['stats']['system']['ram_used'], 2);
                $bandwidth = formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
                $text_marzban = "
Your panel stats👇:
                             
🖥 Panel connection: ✅ Panel is connected
💻 Total server RAM: $mem_total
💻 Panel RAM usage: $mem_used
User group: {$marzban_list_get['agent']}
⭕️ Choose one of the options below to manage the panel";
                sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } elseif (isset($System_Stats['message']) && $System_Stats['message'] == "Unathorized") {
                $text_marzban = "❌  The panel link is incorrect";
                sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } else {
                sendmessage($from_id, "Panel is not connected", $optionhiddfy, 'HTML');
            }
        }
    } elseif ($marzban_list_get['type'] == "Manualsale") {
        sendmessage($from_id, "Choose an option", $optionManualsale, 'HTML');
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $Check_token = token_panelm($marzban_list_get['code_panel']);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Statsm($text);
            if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
                $text_marzban = "❌ An error occurred while fetching info. Error code: " . $System_Stats['status'];
                sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            } elseif (!empty($System_Stats['error'])) {
                $text_marzban = "❌ An error occurred while fetching info. Error: " . $System_Stats['error'];
                sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            }
            $System_Stats = json_decode($System_Stats['body'], true);
            $active_users = $System_Stats['active'];
            $total_user = $System_Stats['total'];
            $ListSell = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['COUNT(*)'] ?? 0);
            $ListSellSUM = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['SUM(price_product)'] ?? 0);
            $Condition_marzban = "";
            $text_marzban = "
Your panel stats👇:
                             
🖥 Marzban panel connection: ✅ Panel is connected
👥  Total users: $total_user
👤 Active users: $active_users
🛍 Total sales on this panel: $ListSell
🛍 Total sales amount on this panel: $ListSellSUM USD
User group: {$marzban_list_get['agent']}
        
⭕️ Choose one of the options below to manage the panel";
            sendmessage($from_id, $text_marzban, $optionmarzneshin, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ Panel username or password is incorrect";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'] . json_encode($Check_token);
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "WGDashboard") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionwg, 'HTML');
    } elseif ($marzban_list_get['type'] == "s_ui") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $options_ui, 'HTML');
    } elseif ($marzban_list_get['type'] == "ibsng") {
        $result = loginIBsng($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if ($result) {
            sendmessage($from_id, $result['msg'], $optionibsng, 'HTML');
        } else {
            sendmessage($from_id, $result['msg'], $optionibsng, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $result = login_mikrotik($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (isset($result['error'])) {
            sendmessage($from_id, json_encode($result), $option_mikrotik, 'HTML');
        } else {
            $free_hdd_space = round($result['free-hdd-space'] / pow(1024, 3), 2);
            $free_memory = round($result['free-memory'] / pow(1024, 3), 2);
            $total_hdd_space = round($result['total-hdd-space'] / pow(1024, 3), 2);
            $total_memory = round($result['total-memory'] / pow(1024, 3), 2);
            sendmessage($from_id, "<b>📡 Your MikroTik system info:</b>

<blockquote>
🖥 <b>Platform:</b> {$result['platform']}  
🏷 <b>Version:</b> {$result['version']}  
🕰 <b>Uptime:</b> {$result['uptime']}  
</blockquote>

<blockquote>
💽 <b>Architecture name:</b> {$result['architecture-name']}  
📋 <b>Board model:</b> {$result['board-name']}  
🏗 <b>Build time:</b> {$result['build-time']}  
</blockquote>

<blockquote>
⚙️ <b>CPU:</b> {$result['cpu']}  
🔢 <b>Core count:</b> {$result['cpu-count']}  
🚀 <b>CPU frequency:</b> {$result['cpu-frequency']}  
📊 <b>CPU load:</b> {$result['cpu-load']} %
</blockquote>

<blockquote>
💾 <b>Total disk space:</b> $total_hdd_space GB  
📂 <b>Free disk space:</b> $free_hdd_space GB  
🧠 <b>Total RAM:</b> $total_memory GB  
📉 <b>Free RAM:</b> $free_memory GB
</blockquote>

<blockquote>
📝 <b>Sectors written since reboot:</b> {$result['write-sect-since-reboot']}  
🧮 <b>Total sectors written:</b> {$result['write-sect-total']}
</blockquote>
", $option_mikrotik, 'HTML');
        }
    } else {
        sendmessage($from_id, "Choose an option", $optionMarzban, 'HTML');
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
} elseif ($text == "✍️ Panel name" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
    step('GetNameNew', $from_id);
} elseif ($user['step'] == "GetNameNew") {
    if (rowExists('marzban_panel', 'name_panel', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedNmaePanel']);
    update("user", "Processing_value", $text, "id", $from_id);
    update("marzban_panel", "name_panel", $text, "name_panel", $user['Processing_value']);
    update("invoice", "Service_location", $text, "Service_location", $user['Processing_value']);
    update("product", "Location", $text, "Location", $user['Processing_value']);
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔗 Edit panel URL" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNew', $from_id);
} elseif ($user['step'] == "GeturlNew") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "📍 Change user group" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the user type
User groups: f,n,n2
❌ If you want the panel shown for all user groups, send all", $backadmin, 'HTML');
    step('getagentpanel', $from_id);
} elseif ($user['step'] == "getagentpanel") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌User group changed successfully");
    update("marzban_panel", "agent", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 Subscription domain" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 If this is a Sanaei panel, copy a user's sub link from the panel and send it here. Other panels must follow their own structure.", $backadmin, 'HTML');
    step('GeturlNewx', $from_id);
} elseif ($user['step'] == "GeturlNewx") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($typepanel['type'] == "x-ui_single") {
        $req = new CurlRequest($text);
        $response = $req->get();
        if ($response['status'] != 200) {
            sendmessage($from_id, "Subscription link is not enabled", null, 'HTML');
            return;
        } elseif (!empty($response['error'])) {
            sendmessage($from_id, "Subscription link is not enabled", null, 'HTML');
            return;
        }
        $response = $response['body'];
        if (isBase64($response)) {
            $response = base64_decode($response);
        }
        $protocol = ['vmess', 'vless', 'trojan', 'ss'];
        $sub_check = explode('://', $response)[0];
        if (!in_array($sub_check, $protocol)) {
            sendmessage($from_id, "Invalid subscription link", null, 'HTML');
            return;
        }
        $text = dirname($text);
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "linksubx", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 uuid admin" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the admin UUID", $backadmin, 'HTML');
    step('getuuidadmin', $from_id);
} elseif ($user['step'] == "getuuidadmin") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ Admin UUID saved");
    update("marzban_panel", "secret_code", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🚨 Account creation limit" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['setlimit'], $backadmin, 'HTML');
    step('getlimitnew', $from_id);
} elseif ($user['step'] == "getlimitnew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['changedlimit']);
    update("marzban_panel", "limit_panel", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⏳ Test service duration" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "🕰 Send the test service duration.
⚠️ Time is in hours.", $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($user['step'] == "updatetime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "time_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💾 Test account volume" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the test service volume.
⚠️ Volume is in megabytes.", $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($user['step'] == "val_usertest") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "val_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💎 Set inbound ID" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the inbound ID you want configs created from. The inbound ID is a multi-digit number shown in the panel on the inbounds page, ID column

⚠️ If this is a wgdashboard panel, send the config name", $backadmin, 'HTML');
    step('getinboundiid', $from_id);
} elseif ($user['step'] == "getinboundiid") {
    sendmessage($from_id, "✅ Inbound ID saved successfully", $optionX_ui_single, 'HTML');
    update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "👤 Edit username" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
    step('GetusernameNew', $from_id);
} elseif ($user['step'] == "GetusernameNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
    update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⚙️ Protocol settings" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocolx_ui', $from_id);
} elseif ($user['step'] == "getprotocolx_ui") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['setprotocol']);
    $marzbanprotocol = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("x_ui", "protocol", $text, "codepanel", $marzbanprotocol['code_panel']);
    step('home', $from_id);
} elseif ($text == "🔐 Edit password" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
    step('GetpaawordNew', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌ Remove panel" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "To confirm, send the word below.
<code>Confirm</code>", $backadmin, 'HTML');
    step('confirmremovepanel', $from_id);
} elseif ($user['step'] == "confirmremovepanel") {
    if ($text == "Confirm") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['RemovedPanel'], $keyboardadmin, 'HTML');
        $marzban = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $stmt = $pdo->prepare("DELETE FROM marzban_panel WHERE name_panel = :name_panel");
        $stmt->bindParam(':name_panel', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->execute();
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managruser'] || $datain == "backlistuser") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Users who have a balance.", 'callback_data' => "balanceuserlist"],
            ],
            [
                ['text' => "Users who have referrals.", 'callback_data' => "listrefral"],
            ],
            [
                ['text' => "Users with card number enabled.", 'callback_data' => "cartuserlist"],
            ],
            [
                ['text' => "Users with a negative balance", 'callback_data' => "zerobalance"],
            ],
            [
                ['text' => "Agent list", 'callback_data' => "agentlistusers"],
                ['text' => "All users", 'callback_data' => "alllistusers"],
            ],
            [
                ['text' => "🛍 Search order", 'callback_data' => "searchorder"],
                ['text' => "👥 Bulk top-up", 'callback_data' => "balanceaddall"],
            ],
            [
                ['text' => "🔍 Search user", 'callback_data' => "searchuser"],
                ['text' => "📨 Broadcast section", 'callback_data' => "systemsms"],
            ],
            [
                ['text' => "👤 Manage via forwarded message", 'callback_data' => "manageuserbyforward"],
            ],
            [
                ['text' => "🔋 Bulk volume or time", 'callback_data' => "voloume_or_day_all"],
            ]
        ]
    ]);
    $text_list_users = "📌 Choose an option from the list below";
    if ($datain == "backlistuser") {
        Editmessagetext($from_id, $message_id, $text_list_users, $keyboardtypelistuser);
    } else {
        sendmessage($from_id, $text_list_users, $keyboardtypelistuser, 'html');
    }
} elseif ($datain == "alllistusers") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuser') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuser') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "agentlistusers") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "n", 'callback_data' => "agenttypshowlist_n"],
                ['text' => "n2", 'callback_data' => "agenttypshowlist_n2"],
            ],
            [
                ['text' => "All agents", 'callback_data' => "agenttypshowlist_all"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Which agent group do you want to view?", $keyboardtypelistuser);
} elseif (preg_match('/agenttypshowlist_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent != 'f'  LIMIT $start_index, $items_per_page");
    } else {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent = '$typeagent'  LIMIT $start_index, $items_per_page");
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/next_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent != 'f'  LIMIT $start_index, $items_per_page");
    } else {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent = '$typeagent'  LIMIT $start_index, $items_per_page");
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent != 'f'  LIMIT $start_index, $items_per_page");
    } else {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent = '$typeagent'  LIMIT $start_index, $items_per_page");
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "balanceuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserbalance') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserbalance') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "listrefral") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE affiliatescount != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"],
        ['text' => "Count", 'callback_data' => "affiliatescount"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "⚙️",
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'] ?: '—',
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
            [
                'text' => (string) $row['affiliatescount'],
                'callback_data' => "affiliatescount_" . $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserrefral') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE affiliatescount != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"],
        ['text' => "Count", 'callback_data' => "affiliatescount"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "⚙️",
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'] ?: '—',
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
            [
                'text' => (string) $row['affiliatescount'],
                'callback_data' => "affiliatescount_" . $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserrefral') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE affiliatescount != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"],
        ['text' => "Count", 'callback_data' => "affiliatescount"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "⚙️",
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'] ?: '—',
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
            [
                'text' => (string) $row['affiliatescount'],
                'callback_data' => "affiliatescount_" . $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['addbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceusercurrent', $from_id);
} elseif ($user['step'] == "addbalanceusercurrent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ Maximum amount is 100 million USD", $backadmin, 'HTML');
        return;
    }
    record_admin_balance_payment($pdo, $user['Processing_value'], (int) $text, 'add balance by admin');
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');
    $Balance_user = select("user", "*", "id", $user['Processing_value'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $text;
    update("user", "Balance", $Balance_add_user, "id", $user['Processing_value']);
    $heibalanceuser = number_format($text, 0);
    $textadd = "💎 $heibalanceuser USD was added to your wallet.";
    sendmessage($user['Processing_value'], $textadd, null, 'HTML');
    step('home', $from_id);
    $Balance_user_after = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $pricadd = number_format($text);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 An admin increased a user's balance:
        
🪪 Admin who increased the balance: 
Username: @$username
Numeric ID: $from_id
👤 User receiving the balance:
User numeric ID: {$user['Processing_value']}
Amount: $pricadd
User balance after increase: $Balance_user_after";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceuser', $from_id);
} elseif ($user['step'] == "addbalanceuser") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ Maximum amount is 100 million USD", $backadmin, 'HTML');
        return;
    }
    record_admin_balance_payment($pdo, $user['Processing_value'], (int) $text, 'low balance by admin');
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');
    $Balance_user = select("user", "*", "id", $user['Processing_value'], "select");
    $Balance_add_user = $Balance_user['Balance'] - $text;
    update("user", "Balance", $Balance_add_user, "id", $user['Processing_value']);
    $lowbalanceuser = number_format($text, 0);
    $textkam = "❌ $lowbalanceuser USD was deducted from your wallet.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 An admin decreased a user's balance:
        
🪪 Admin who decreased the balance: 
Username: @$username
Numeric ID: $from_id
👤 User info:
User numeric ID: {$user['Processing_value']}
Amount: $text
User balance after decrease: $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ((preg_match('/banuserlist_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "block") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Confirm", 'callback_data' => 'acceptblock_' . $iduser],
            ],
        ]
    ]);
    sendmessage($from_id, "If you confirm, tap the confirm button", $Response, 'HTML');
} elseif ($user['step'] == "adddecriptionblock") {
    update("user", "description_blocking", $text, "id", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['DescriptionBlock'], $keyboardadmin, 'HTML');
    step('home', $from_id);

} elseif ((preg_match('/acceptblock_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {

    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    update("user", "User_Status", "block", "id", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['BlockUser'], $backadmin, 'HTML');
    step('adddecriptionblock', $from_id);
    $textblok = "User with numeric ID
$iduser  was blocked in the bot 
Blocking admin: $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (preg_match('/verify_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "1", "id", $iduser);
    sendmessage($from_id, "✅ User verified successfully.", null, 'HTML');
    sendmessage($iduser, "💎 Dear user, your account was verified by an admin and you can now make a purchase", $keyboard, 'HTML');
} elseif (preg_match('/unverify-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "0", "id", $iduser);
    sendmessage($from_id, "✅ User was unverified successfully.", null, 'HTML');


} elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "Active") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], null, 'HTML');
        return;
    }
    $textblok = "User with numeric ID
$iduser  was unblocked in the bot 
Unblocking admin: $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "User_Status", "Active", "id", $iduser);
    update("user", "description_blocking", " ", "id", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['UserUnblocked'], $keyboardadmin, 'HTML');
    sendmessage($iduser, "✳️ Your account is no longer blocked ✳️
You can now use the bot ✔️", $keyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmnumber_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "number", "confrim number by admin", "id", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['phone']['active'], $keyboardadmin, 'HTML');
} elseif (preg_match('/viewpaymentuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $PaymentUsers = select("Payment_report", "*", "id_user", $iduser, "fetchAll");
    foreach ($PaymentUsers as $paymentUser) {
        $text_order = "🛒 Payment number:  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ User ID: <code>{$paymentUser['id_user']}</code>
💰 Amount paid: {$paymentUser['price']} USD
⚜️ Payment status: {$paymentUser['payment_Status']}
⭕️ Payment method: {$paymentUser['Payment_Method']} 
📆 Purchase date:  {$paymentUser['time']}";
        sendmessage($from_id, $text_order, null, 'HTML');
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['sendpayemntlist'], $keyboardadmin, 'HTML');
} elseif (preg_match('/affiliates-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "count");
    if ($affiliatesUsers == 0) {
        sendmessage($from_id, "❌ This user has no referrals.", null, 'HTML');
        return;
    }
    $affiliatesTotal = (int) $affiliatesUsers;
    $affiliatesBoughtCount = (int) (mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(DISTINCT u.id) AS cnt FROM user u INNER JOIN invoice i ON i.id_user = u.id WHERE u.affiliates = '$iduser' AND i.name_product != 'سرویس تست' AND i.Status NOT IN ('Unpaid','unpaid','reject','removebyadmin','removedbyadmin')"))['cnt'] ?? 0);
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "fetchAll");
    $boughtIds = [];
    $boughtResult = mysqli_query($connect, "SELECT DISTINCT u.id FROM user u INNER JOIN invoice i ON i.id_user = u.id WHERE u.affiliates = '$iduser' AND i.name_product != 'سرویس تست' AND i.Status NOT IN ('Unpaid','unpaid','reject','removebyadmin','removedbyadmin')");
    if ($boughtResult) {
        while ($boughtRow = mysqli_fetch_assoc($boughtResult)) {
            $boughtIds[$boughtRow['id']] = true;
        }
    }
    $count = 0;
    $text_affiliates = "";
    foreach ($affiliatesUsers as $affiliatesUser) {
        $buyerMark = isset($boughtIds[$affiliatesUser['id']]) ? " ✅ Buyer" : "";
        $text_affiliates .= "<code>{$affiliatesUser['id']}</code>$buyerMark\n\r";
        $count++;
        if ($count == 10) {
            sendmessage($from_id, $text_affiliates, null, 'HTML');
            $count = 0;
            $text_affiliates = "";
        }
    }
    if ($text_affiliates !== "") {
        sendmessage($from_id, $text_affiliates, null, 'HTML');
    }
    sendmessage($from_id, "📌 Referral IDs were sent.\n👥 Total referrals: $affiliatesTotal\n🛒 Service buyers: $affiliatesBoughtCount", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliate-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $user2 = select("user", "*", "id", $iduser, "select");
    $user2 = select("user", "*", "id", $user2['affiliates'], "select");
    $affiliatescount = intval($user2['affiliatescount']) - 1;
    update("user", "affiliatescount", $affiliatescount, "id", $user2['id']);
    update("user", "affiliates", "0", "id", $iduser);
    sendmessage($from_id, "📌 User was removed from referrals.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliateuser-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "affiliatescount", "0", "id", $iduser);
    update("user", "affiliates", "0", "affiliates", $iduser);
    sendmessage($from_id, "📌 User's referrals were deleted.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeservice-(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif (preg_match('/removeserviceandback-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    if ($info_product['Status'] == "removebyadmin") {
        sendmessage($from_id, "❌ Service was already deleted", $keyboardadmin, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
    } else {
        if ($DataUserOut['status'] == "Unsuccessful") {
            sendmessage($from_id, 'An error occurred', $keyboardadmin, 'HTML');
        }
    }
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    $Balance_user = select("user", "*", "id", $info_product['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $info_product['price_product'];
    update("user", "Balance", $Balance_add_user, "id", $info_product['id_user']);
    $textadd = "💎 {$info_product['price_product']} USD was added to your wallet.";
    sendmessage($info_product['id_user'], $textadd, null, 'HTML');
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif ($text == "🎁 Create discount code" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['GetCode'], $backadmin, 'HTML');
    step('get_codesell', $from_id);
} elseif ($user['step'] == "get_codesell") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Discount']['PriceCodesell'], null, 'HTML');
    step('get_price_codesell', $from_id);
    savedata("clear", "code", strtolower($text));
} elseif ($user['step'] == "get_price_codesell") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['getlimit'], $backadmin, 'HTML');
    step('getlimitcode', $from_id);
} elseif ($user['step'] == "getlimitcode") {
    savedata("save", "limitDiscount", $text);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['agentcode'], $backadmin, 'HTML');
    step('gettypecodeagent', $from_id);
} elseif ($user['step'] == "gettypecodeagent") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    sendmessage($from_id, "📌 How many hours should the discount code stay active? Send 0 for unlimited", $backadmin, 'HTML');
    step('gettimediscount', $from_id);
} elseif ($user['step'] == "gettimediscount") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) == 0) {
        $text = "0";
    } else {
        $text = time() + (intval($text) * 3600);
    }
    savedata("save", "time", $text);
    $keyboarddiscount = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "All purchases", 'callback_data' => "discountlimitbuy_0"],
                ['text' => "First purchase", 'callback_data' => "discountlimitbuy_1"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['firstdiscount'], $keyboarddiscount, 'HTML');
    step('getfirstdiscount', $from_id);
} elseif (preg_match('/discountlimitbuy_(\w+)/', $datain, $dataget)) {
    $discountbuylimit = $dataget[1];
    savedata("save", "usefirst", $discountbuylimit);
    if (intval($discountbuylimit) == 1) {
        sendmessage($from_id, "📌Send the per-user usage limit.", $backadmin, 'HTML');
        step('getuseuser', $from_id);
        savedata("save", "typediscount", "all");
    } else {
        $keyboarddiscount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Purchase", 'callback_data' => "discounttype_buy"],
                    ['text' => "Renewal", 'callback_data' => "discounttype_extend"],
                ],
                [
                    ['text' => "Both", 'callback_data' => "discounttype_all"]
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 Which section should the discount code apply to", $keyboarddiscount);
    }
} elseif (preg_match('/discounttype_(\w+)/', $datain, $dataget)) {
    $discountbuytype = $dataget[1];
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    savedata("save", "typediscount", $discountbuytype);
    sendmessage($from_id, "📌Send the per-user usage limit.", $backadmin, 'HTML');
    step('getuseuser', $from_id);
} elseif ($user['step'] == "getuseuser") {
    $userdata = json_decode($user['Processing_value'], true);
    $numberlimit = $userdata['limitDiscount'];
    if (intval($text) > intval($userdata['limitDiscount'])) {
        sendmessage($from_id, "📌 Per-user usage must be smaller than the total limit", $backadmin, 'HTML');
        return;
    }
    step('getlocdiscount', $from_id);
    savedata("save", "useuser", $text);
    sendmessage($from_id, "📌 To set a product-specific discount code, first choose the product location.
Note: to select all panels send <code>/all</code>", $json_list_marzban_panel, 'HTML');
    step('getlocdiscount', $from_id);
} elseif ($user['step'] == "getlocdiscount") {
    if ($text == "/all") {
        $panel['code_panel'] = "/all";
    } else {
        $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    }
    if ($panel == false)
        return;
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "name_panel", $text);
    sendmessage($from_id, "📌  Which product should the discount code apply to? If you want it for all products, send the word all", $json_list_product_list_admin, 'HTML');
    step('getproductdiscount', $from_id);
} elseif ($user['step'] == "getproductdiscount") {
    if ($text != "all") {
        $product = select("product", "*", "name_product", $text, "select");
    } else {
        $product['code_product'] = "all";
    }
    if ($product == false) {
        sendmessage($from_id, "❌ Selected product does not exist", $keyboardadmin, 'HTML');
        return;
    }
    discount_sell_ensure_schema();
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO DiscountSell (codeDiscount, usedDiscount, price, limitDiscount, agent, usefirst, useuser, code_panel, code_product, code_category, time,type) VALUES (:codeDiscount, :usedDiscount, :price, :limitDiscount, :agent, :usefirst, :useuser, :code_panel, :code_product, :code_category, :time,:type)");
    $values = "0";
    $code_category_all = "all";
    $stmt->bindParam(':codeDiscount', $userdata['code'], PDO::PARAM_STR);
    $stmt->bindParam(':usedDiscount', $values, PDO::PARAM_STR);
    $stmt->bindParam(':price', $userdata['price'], PDO::PARAM_STR);
    $stmt->bindParam(':limitDiscount', $userdata['limitDiscount'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $userdata['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':usefirst', $userdata['usefirst'], PDO::PARAM_STR);
    $stmt->bindParam(':useuser', $userdata['useuser'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $userdata['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':code_product', $product['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':code_category', $code_category_all, PDO::PARAM_STR);
    $stmt->bindParam(':time', $userdata['time'], PDO::PARAM_STR);
    $stmt->bindParam(':type', $userdata['typediscount'], PDO::PARAM_STR);
    $stmt->execute();
    $textdiscount = "
🎁 Your discount code was created successfully.

📩 Discount code name: <code>{$userdata['code']}</code>
🧮 Discount percent: {$userdata['price']}
🎛 Panel:  {$userdata['name_panel']}
📌  Product: $text
♻️ User type: {$userdata['agent']}
🔴 Usage limit: {$userdata['limitDiscount']}";
    sendmessage($from_id, $textdiscount, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ Remove discount code" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin_sell, 'HTML');
    step('remove-Discountsell', $from_id);
} elseif ($user['step'] == "remove-Discountsell") {
    if (!rowExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Giftcodeconsumed WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = :codeDiscount");
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "/end") {
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['name_panel'], "select");
    if ($panel['type'] == "marzneshin") {
        update("user", "Processing_value", $userdata['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionmarzneshin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionMarzban, 'HTML');
    step('home', $from_id);
    return;
} elseif ($text == "🧮 Set referral percent" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($user['step'] == "setpercentage") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "Invalid percent", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['affiliates']['changedpercentage'], $affiliates, 'HTML');
    update("setting", "affiliatespercentage", $text);
    step('home', $from_id);
} elseif ($text == "🏞 Set referral banner") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['banner'], $backadmin, 'HTML');
    step('setbanner', $from_id);
} elseif ($user['step'] == "setbanner") {
    if (!$photo) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
        return;
    }
    update("affiliates", "id_media", $photoid);
    update("affiliates", "description", $caption);
    sendmessage($from_id, $textbotlang['users']['affiliates']['insertbanner'], $affiliates, 'HTML');
    step('home', $from_id);
} elseif ($text == "👤 Support username" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDirect");
    $textcart = "📌 Send your username without @ to receive the card number\n\n{$PaySetting['ValuePay']}";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('CartDirect', $from_id);
} elseif ($user['step'] == "CartDirect") {
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $CartManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "CartDirect");
    step('home', $from_id);
} elseif ($text == "💳 Offline gateway in private chat" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "Cartstatuspv")['ValuePay'];
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['cardTitlepv'], $card_Statuspv, 'HTML');
} elseif ($datain == "oncardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offcardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffpv'], null);
} elseif ($datain == "offcardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "oncardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonpv'], null);
} elseif (preg_match('/addbalamceuser_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    update("user", "Processing_value", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        $ff = telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $id_order);

    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalancemanual', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "addbalancemanual") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Balance']['AddBalanceUser'], $keyboardadmin, 'HTML');
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Balance_user = select("user", "*", "id", $Payment_report['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $text;
    $balanceusers = number_format($text, 0);
    update("user", "Balance", $Balance_add_user, "id", $Payment_report['id_user']);
    $creditAmount = (int) $text;
    $originalPrice = (int) ($Payment_report['price'] ?? 0);
    if ($creditAmount !== $originalPrice) {
        update("Payment_report", "price", (string) $creditAmount, "id_order", $Payment_report['id_order']);
    }
    $payPrefix = trim(explode('|', (string) ($Payment_report['id_invoice'] ?? ''), 2)[0]);
    if (in_array($payPrefix, ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'], true)) {
        update("Payment_report", "id_invoice", '', "id_order", $Payment_report['id_order']);
    }
    $textadd = "💎 $balanceusers USD was added to your wallet.";
    sendmessage($Payment_report['id_user'], $textadd, null, 'HTML');
    $text_report = "Card-to-card receipt confirmation and manual balance increase by admin
        
User numeric ID: {$Payment_report['id_user']}
Username: {$Balance_user['username']}
Invoice amount:  {$Payment_report['price']}
Amount credited by admin: $text";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($text == "🎁 Commission after purchase" && $adminrulecheck['rule'] == "administrator") {
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
} elseif ($datain == "oncommission") {
    update("affiliates", "status_commission", "offcommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatusOff'], $keyboardcommission);
} elseif ($datain == "offcommission") {
    update("affiliates", "status_commission", "oncommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatuson'], $keyboardcommission);
} elseif ($text == "🎁 Start gift" && $adminrulecheck['rule'] == "administrator") {
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "onDiscountaffiliates") {
    update("affiliates", "Discount", "offDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatusOff'], $keyboardDiscountaffiliates);
} elseif ($datain == "offDiscountaffiliates") {
    update("affiliates", "Discount", "onDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatuson'], $keyboardDiscountaffiliates);
} elseif ($text == "🌟 Start gift amount" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($user['step'] == "getdiscont") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['changedpriceDiscount'], $affiliates, 'HTML');
    update("affiliates", "price_Discount", $text);
    step('home', $from_id);
} elseif ($datain == "mainbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")[$user['agent']], true);
    $textmin = "📌 Set the minimum amount the user can charge their account";
    sendmessage($from_id, $textmin, $backadmin, 'HTML');
    step('minbalance', $from_id);
} elseif ($user['step'] == "minbalance") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemin', $from_id);
    sendmessage($from_id, "📌Which user group should the minimum balance apply to.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemin") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "minbalance");
} elseif ($datain == "maxbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select");
    $textmax = "📌 Set the maximum amount the user can charge their account";
    sendmessage($from_id, $textmax, $backadmin, 'HTML');
    step('maxbalance', $from_id);
} elseif ($user['step'] == "maxbalance") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemax', $from_id);
    sendmessage($from_id, "📌Which user group should the minimum balance apply to.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemax") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "maxbalance");
} elseif (preg_match('/removeagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['useragentremoved'],
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    update("user", "agent", "f", "id", $id_user);
    update("user", "pricediscount", "0", "id", $id_user);
    update("user", "expire", null, "id", $id_user);
    $stmt = $pdo->prepare("DELETE FROM Requestagent WHERE id = '$id_user'");
    $stmt->execute();
    step('home', $from_id);
} elseif (preg_match('/addagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['gettypeagent'],
        'parse_mode' => "HTML",
        'reply_markup' => $backadmin,
        'reply_to_message_id' => $message_id,
    ]);
    step('gettypeagentoflist', $from_id);
} elseif ($user['step'] == "gettypeagentoflist") {
    $agentst = ["n", "n2"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    update("user", "expire", null, "id", $user['Processing_value']);
    update("user", "agent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/Percentlow_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => "📌 Send the percent discount the user should receive after any purchase.",
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('getpercentuser', $from_id);
} elseif ($user['step'] == "getpercentuser") {
    if (intval($text) > 100 || intval($text) < 0 || !ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $keyboardadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "Changes applied successfully", $keyboardadmin, 'HTML');
    update("user", "pricediscount", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/maxbuyagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    sendmessage($from_id, "📌 Send the maximum negative balance allowed at purchase time
Note: send the number without a minus sign
Send 0 if the user may purchase without limit", $backadmin, 'HTML');
    step('getmaxbuyagent', $from_id);
} elseif ($user['step'] == "getmaxbuyagent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "Changes applied successfully", $keyboardadmin, 'HTML');
    update("user", "maxbuyagent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif ($datain == "searchorder") {
    sendmessage($from_id, $textbotlang['Admin']['order']['vieworderusername'], $backadmin, 'HTML');
    step('GetusernameconfigAndOrdedrs', $from_id);
} elseif ($user['step'] == "GetusernameconfigAndOrdedrs" || strpos($text, "/config ") !== false || preg_match('/manageinvoice_(\w+)/', $datain, $datagetr)) {
    if ($user['step'] == "GetusernameconfigAndOrdedrs") {
        $usernameconfig = $text;
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } elseif ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } else {
        $usernameconfig = select("invoice", "*", "id_invoice", $datagetr[1], "select")['username'];
        $sql = "SELECT * FROM invoice WHERE username = :username OR note  = :notes";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    }
    $stmt->execute();
    step("home", $from_id);
    if ($stmt->rowCount() > 1) {
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "Action", 'callback_data' => "action"],
            ['text' => "Service status", 'callback_data' => "Status"],
            ['text' => "Username", 'callback_data' => "username"],
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "View info",
                    'callback_data' => "manageinvoice_" . $row['id_invoice']
                ],
                [
                    'text' => $row['Status'],
                    'callback_data' => "username"
                ],
                [
                    'text' => $row['username'],
                    'callback_data' => $row['username']
                ],
            ];
        }
        $keyboardlists = json_encode($keyboardlists);
        sendmessage($from_id, "⚠️ More than one service was found. Choose the correct one from the list", $keyboardlists, 'HTML');
        return;
    }
    $OrderUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$OrderUser) {
        sendmessage($from_id, $textbotlang['Admin']['order']['notfound'], null, 'HTML');
        return;
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "♻️ Refresh", 'callback_data' => "manageinvoice_" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['Admin']['ManageUser']['removeservice'], 'callback_data' => "removeservice-" . $OrderUser['id_invoice']],
        ['text' => $textbotlang['Admin']['ManageUser']['removeserviceandback'], 'callback_data' => "removeserviceandback-" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "🗑 Fully delete service", 'callback_data' => "removefull-" . $OrderUser['id_invoice']],
    ];
    if (isset($OrderUser['time_sell'])) {
        $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
    } else {
        $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
    }
    if ($OrderUser['name_product'] == "سرویس تست") {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "hours";
        $OrderUser['Volume'] = $OrderUser['Volume'] . "MB";
    } else {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "days";
        $OrderUser['Volume'] = $OrderUser['Volume'] . "GB";
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC LIMIT 20");
    $stmt->execute([
        ':username' => $OrderUser['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "name_product", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $OrderUser['name_product'] = $codeproduct['name_product'];
                $OrderUser['Volume'] = $codeproduct['Volume_constraint'];
                $OrderUser['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }
    $text_order = "
🛒 Order number:  <code>{$OrderUser['id_invoice']}</code>
🛒  Order status in the bot: <code>{$OrderUser['Status']}</code>
🙍‍♂️ User ID: <code>{$OrderUser['id_user']}</code>
👤 Subscription username:  <code>{$OrderUser['username']}</code> 
📍 Service location:  {$OrderUser['Service_location']}
🛍 Product name:  {$OrderUser['name_product']}
💰 Amount paid: {$OrderUser['price_product']} USD
⚜️ Purchased volume: {$OrderUser['Volume']}
⏳ Purchased duration: {$OrderUser['Service_time']} 
📆 Purchase date: $datatime  
";
    $DataUserOut = $ManagePanel->DataUser($OrderUser['Service_location'], $OrderUser['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $keyboard_json = json_encode($keyboardlists);
        sendmessage($from_id, "User does not exist in the panel", $keyboardadmin, 'html');
        sendmessage($from_id, $text_order, $keyboard_json, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = 'Online';
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = 'Offline';
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateString = $DataUserOut['online_at'];
            $lastonline = jdate('Y/m/d H:i:s', strtotime($dateString));
        } else {
            $lastonline = "Not connected";
        }
    }
    #-------------status----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "Unlimited";
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
    #--------------[ subsupdate ]---------------#
    $lastupdate = "";
    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }
    $limitValue = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
    $usedTrafficValue = isset($DataUserOut['used_traffic']) ? (float) $DataUserOut['used_traffic'] : 0;
    if ($limitValue > 0) {
        $Percent = (($limitValue - $usedTrafficValue) * 100) / $limitValue;
    } else {
        $Percent = 100;
    }
    if ($Percent < 0) {
        $Percent = -$Percent;
    }
    $Percent = round($Percent, 2);
    $text_order .= "
  
 Service status: $status_var
        
🔋 Service volume: $LastTraffic
📥 Used volume: $usedTrafficGb
💢 Remaining volume: $RemainingVolume ($Percent%)

📅 Active until: $expirationDate ($day)

User subscription link: 
<code>{$DataUserOut['subscription_url']}</code>

📶 Last connection: $lastonline
🔄 Last sub link update: $lastupdate
#️⃣ Connected client: <code>{$DataUserOut['sub_last_user_agent']}</code>";
    if ($DataUserOut['status'] == "active") {
        $namestatus = '❌ Disable account';
    } else {
        $namestatus = '💡 Enable account';
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extendadmin_' . $OrderUser['id_invoice']],
        ['text' => $textbotlang['users']['stateus']['config'], 'callback_data' => 'config_' . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $namestatus, 'callback_data' => 'changestatusadmin_' . $OrderUser['id_invoice']],
    ];
    $keyboard_json = json_encode($keyboardlists);
    sendmessage($from_id, $text_order, $keyboard_json, 'HTML');
    $stmt = $pdo->prepare("SELECT * FROM service_other s WHERE username = :username AND (status = 'paid' OR status IS NULL)");
    $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
    $stmt->execute();
    $list_service = $stmt->fetchAll();
    if ($list_service) {
        foreach ($list_service as $extend) {
            $extend_type = [
                'extend_user' => "Renewal",
                'extend_user_by_admin' => 'Renewed by admin',
                'extra_user' => "Extra volume",
                "extra_time_user" => "Extra time",
                "transfertouser" => "Transfer to another account",
                "extends_not_user" => "Renewal because user was not in the list",
                "change_location" => "Change location",
                'gift_time' => 'Bulk time gift',
                'gift_volume' => 'Bulk volume gift'
            ][$extend['type']];
            $time_jalali = jdate('Y/m/d H:i:s', strtotime($extend['time']));

            $extendtext = "\n📌 Service report \n🔗  Service type: $extend_type\n🕰 Service time: {$extend['time']} \n\n($time_jalali)\n💰Service amount: {$extend['price']}\n👤 User numeric ID: {$extend['id_user']}\n👤 Config username: {$extend['username']}";
            sendmessage($from_id, $extendtext, null, 'HTML');
        }
    }
    step('home', $from_id);
} elseif ($text == "🛒 Shop feature status" && $adminrulecheck['rule'] == "administrator") {
    $marzbanstatusextra = select("shopSetting", "*", "Namevalue", "statusextra", "select")['value'];
    $marzbandirectpay = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select")['value'];
    $statustimeextra = select("shopSetting", "*", "Namevalue", "statustimeextra", "select")['value'];
    $statusdisorder = select("shopSetting", "*", "Namevalue", "statusdisorder", "select")['value'];
    $statuschangeservice = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select")['value'];
    $statusshowprice = select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value'];
    $statusshowconfig = select("shopSetting", "*", "Namevalue", "configshow", "select")['value'];
    $statusremoveserveice = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select")['value'];
    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra];
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay];
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra];
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder];
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscategorygenral']];
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice];
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice];
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig];
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice];
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscategory']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => $textbotlang['Admin']['Status']['statusvolumeextra'], 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => $textbotlang['Admin']['Status']['paydirect'], 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => $textbotlang['Admin']['Status']['statustimeextra'], 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ Send outage report", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 Category ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => $textbotlang['Admin']['Status']['statuscategorytime'], 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓Disable-account status", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 Show product price", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 Get-config button", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 Refund button", 'callback_data' => "removeservicebackbtn"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editshops-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "extravolunme") {
        if ($value == "onextra") {
            $valuenew = "offextra";
        } else {
            $valuenew = "onextra";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusextra");
    } elseif ($type == "paydirect") {
        if ($value == "ondirectbuy") {
            $valuenew = "offdirectbuy";
        } else {
            $valuenew = "ondirectbuy";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdirectpabuy");
    } elseif ($type == "statustimeextra") {
        if ($value == "ontimeextraa") {
            $valuenew = "offtimeextraa";
        } else {
            $valuenew = "ontimeextraa";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statustimeextra");
    } elseif ($type == "disorderss") {
        if ($value == "ondisorder") {
            $valuenew = "offdisorder";
        } else {
            $valuenew = "ondisorder";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdisorder");
    } elseif ($type == "categroygenral") {
        if ($value == "oncategorys") {
            $valuenew = "offcategorys";
        } else {
            $valuenew = "oncategorys";
        }
        update("setting", "statuscategorygenral", $valuenew, null, null);
    } elseif ($type == "changgestatus") {
        if ($value == "onstatus") {
            $valuenew = "offstatus";
        } else {
            $valuenew = "onstatus";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statuschangeservice");
    } elseif ($type == "showprice") {
        if ($value == "onshowprice") {
            $valuenew = "offshowprice";
        } else {
            $valuenew = "onshowprice";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusshowprice");
    } elseif ($type == "showconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "configshow");
    } elseif ($type == "removeservicebackbtn") {
        if ($value == "on") {
            $valuenew = "off";
        } else {
            $valuenew = "on";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "backserviecstatus");
    } elseif ($type == "categorytime") {
        if ($value == "oncategory") {
            $valuenew = "offcategory";
        } else {
            $valuenew = "oncategory";
        }
        update("setting", "statuscategory", $valuenew);
    }
    $setting = select("setting", "*", null, null, "select");
    $marzbanstatusextra = select("shopSetting", "*", "Namevalue", "statusextra", "select")['value'];
    $marzbandirectpay = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select")['value'];
    $statustimeextra = select("shopSetting", "*", "Namevalue", "statustimeextra", "select")['value'];
    $statusdisorder = select("shopSetting", "*", "Namevalue", "statusdisorder", "select")['value'];
    $statuschangeservice = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select")['value'];
    $statusshowprice = select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value'];
    $statusshowconfig = select("shopSetting", "*", "Namevalue", "configshow", "select")['value'];
    $statusremoveserveice = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select")['value'];
    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra];
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay];
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra];
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder];
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscategorygenral']];
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice];
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice];
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig];
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice];
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscategory']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => $textbotlang['Admin']['Status']['statusvolumeextra'], 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => $textbotlang['Admin']['Status']['paydirect'], 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => $textbotlang['Admin']['Status']['statustimeextra'], 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ Send outage report", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 Category ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => $textbotlang['Admin']['Status']['statuscategorytime'], 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓Disable-account status", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 Show product price", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 Get-config button", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 Refund button", 'callback_data' => "removeservicebackbtn"],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($text == "🪪 Export data" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardexportdata, 'HTML');
} elseif ($text == "🕚 Cron job settings" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "Export users" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("user", "*", null, null, "count");
    if ($counttable == 0) {
        sendmessage($from_id, "❌ There is no data to export", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM user";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "users_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 Export user data");
    unlink($filename);
} elseif ($text == "Export orders" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("invoice", "*", null, null, "count");
    if ($counttable == 0) {
        sendmessage($from_id, "❌ There is no data to export", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM invoice";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "invoice_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 Export user orders");
    unlink($filename);
} elseif ($text == "Export payments" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("Payment_report", "*", null, null, "count");
    if ($counttable == 0) {
        sendmessage($from_id, "❌ There is no data to export", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM Payment_report";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "Payment_report_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 Export user payments");
    unlink($filename);
} elseif (preg_match('/rejectremoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "This request was already reviewed by another admin",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    step("descriptionsrequsts", $from_id);
    update("user", "Processing_value", $requestcheck['username'], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['stateus']['requestadmin'], $backuser, 'HTML');
} elseif ($user['step'] == "descriptionsrequsts") {
    sendmessage($from_id, $textbotlang['users']['stateus']['accecptreqests'], $keyboardadmin, 'HTML');
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    update("cancel_service", "status", "reject", "username", $user['Processing_value']);
    update("cancel_service", "description", $text, "username", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($nameloc['id_user'], "❌ Your deletion request for username {$user['Processing_value']} was not approved.

        Reason: $text", null, 'HTML');
} elseif (preg_match('/remoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "This request was already reviewed by another admin",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $nameloc = select("invoice", "*", "username", $requestcheck['username'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $requestcheck['username']);
    $stmt = $pdo->prepare("SELECT  SUM(price) FROM service_other WHERE username = :username AND type != 'change_location' AND type != 'extend_user' LIMIT 1");
    $stmt->bindParam(':username', $nameloc['username']);
    $stmt->execute();
    $sumproduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['data_limit'] == null && $DataUserOut['expire'] == null) {
        sendmessage($from_id, "❌ The service cannot be deleted because volume and time are unlimited. ", null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        $pricelast = $invoice['price_product'];
    } elseif ($DataUserOut['data_limit'] == null) {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        if ($serviceTime > 0) {
            $pricetime = ($nameloc['price_product'] / $serviceTime) + intval($sumproduct['SUM(price)']);
            $pricelast = (($DataUserOut['expire'] - time()) / 86400) * $pricetime;
        } else {
            $pricelast = 0;
        }
    } elseif ($DataUserOut['expire'] == null) {
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        if ($dataLimit > 0) {
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeDivisor = $dataLimit / pow(1024, 3);
            $volumeleft = $volumeDivisor > 0 ? $volumelefts / $volumeDivisor : 0;
            $pricelast = round($volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    } else {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        $volumeDivisor = $dataLimit / pow(1024, 3);
        if ($serviceTime > 0 && $volumeDivisor > 0) {
            $timeleft = (round(($DataUserOut['expire'] - time()) / 86400, 0)) / $serviceTime;
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeleft = $volumelefts / $volumeDivisor;
            $pricelast = round($timeleft * $volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    }
    $pricelast = intval($pricelast);
    if (intval($pricelast) != 0) {
        $Balance_id_cancel = select("user", "*", "id", $nameloc['id_user'], "select");
        $Balance_id_cancel_fee = intval($Balance_id_cancel['Balance']) + intval($pricelast);
        update("user", "Balance", $Balance_id_cancel_fee, "id", $nameloc['id_user']);
        sendmessage($nameloc['id_user'], "💰 $pricelast USD was added to your wallet.", null, 'HTML');
    }
    $ManagePanel->RemoveUser($nameloc['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    sendmessage($from_id, "❌ $pricelast USD was added to the user's balance.", null, 'HTML');
    sendmessage($nameloc['id_user'], "✅ Your deletion request for username {$nameloc['username']} was approved.", null, 'HTML');
    $text_report = "⭕️ An admin approved a user's service-deletion request
        
Approving admin: 

🪪 Numeric ID: <code>$from_id</code>
💰 Refunded amount: $pricelast USD
👤 Username: {$requestcheck['username']}
        Requester numeric ID: {$nameloc['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/remoceserviceadminmanual-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "This request was already reviewed by another admin",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $ManagePanel->RemoveUser($invoice['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    sendmessage($invoice['id_user'], "✅ Your deletion request for username {$invoice['username']} was approved.", null, 'HTML');
    sendmessage($from_id, "📌 Send the refund amount", $backadmin, 'HTML');
    step("getpricebackremove", $from_id);
} elseif ($user['step'] == "getpricebackremove") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $Balance_id_cancel = select("user", "*", "id", $invoice['id_user'], "select");
    $Balance_id_cancel_fee = intval($Balance_id_cancel['Balance']) + intval($text);
    update("user", "Balance", $Balance_id_cancel_fee, "id", $invoice['id_user']);
    sendmessage($invoice['id_user'], "💰 $text USD was added to your wallet.", null, 'HTML');
    sendmessage($from_id, "✅ Amount was added to the user's account.", $keyboardadmin, 'HTML');
    $text_report = "⭕️ An admin approved a user's service-deletion request
        
Approving admin: 

🪪 Numeric ID: <code>$from_id</code>
💰 Refunded amount: $text USD
👤 Username: {$invoice['username']}
Requester numeric ID: {$invoice['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "settimecornremovevolume" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['setvolumeremove'] . $setting['cronvolumere'] . "days", $backadmin, 'HTML');
    step("getcronvolumere", $from_id);
} elseif ($user['step'] == "getcronvolumere") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "cronvolumere", $text);
} elseif ($datain == "setting_on_holdcron" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "In this section set after how many days a user who has not connected to their config and is on_hold should be notified" . $setting['on_hold_day'] . "days", $backadmin, 'HTML');
    step("on_hold_day", $from_id);
} elseif ($user['step'] == "on_hold_day") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "on_hold_day", $text);
}
if ($datain == "settimecornremove" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['setdayremove'] . $setting['removedayc'] . "days", $backadmin, 'HTML');
    step("getdaycron", $from_id);
} elseif ($user['step'] == "getdaycron") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "removedayc", $text);
} elseif ($text == "Set API URL" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "urlpaymenttron", "select");
    $texttronseller = "📌 Send the API URL.

Current URL: {$PaySetting['ValuePay']}";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('urlpaymenttron', $from_id);
} elseif ($user['step'] == "urlpaymenttron") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "urlpaymenttron");
    step('home', $from_id);
} elseif ($text == "✏️ Edit guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Help']['SelectName'], keyboard_help_os_list(), 'HTML');
    step("getnameforedite", $from_id);
} elseif ($user['step'] == "getnameforedite") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == "Edit name" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new name", $backadmin, 'HTML');
    step('changenamehelp', $from_id);
} elseif ($user['step'] == "changenamehelp") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, "❌ Tutorial name must be under 150 characters", null, 'HTML');
        return;
    }
    update("help", "name_os", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, "✅ Tutorial name updated", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "Edit category" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send your new category", $backadmin, 'HTML');
    step('changecategoryhelp', $from_id);
} elseif ($user['step'] == "changecategoryhelp") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, "❌ Tutorial name must be under 150 characters", null, 'HTML');
        return;
    }
    update("help", "category", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, "✅ Tutorial category name updated", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "Edit description" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new description", $backadmin, 'HTML');
    step('changedeshelp', $from_id);
} elseif ($user['step'] == "changedeshelp") {
    update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, "✅ Tutorial description updated", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "Edit media" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "Send the new image or video", $backadmin, 'HTML');
    step('changemedia', $from_id);
} elseif ($user['step'] == "changemedia") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    }
    sendmessage($from_id, "✅ Tutorial description updated", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰 Disable card-number display") {
    sendmessage($from_id, "Disable for all users or new users only?
    New users 0 
    All users 1
    2 Users except agents", null, 'HTML');
    step('showcardallusers', $from_id);
} elseif ($user['step'] == "showcardallusers") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['disableshowcardstatus'], null, 'HTML');
    if (intval($text) == "1") {
        update("user", "cardpayment", "0");
        update("setting", "showcard", "0");
    } elseif (intval($text) == 2) {
        update("user", "cardpayment", "0", "agent", "f");
        update("setting", "showcard", "0");
    } else {
        update("setting", "showcard", "0");
    }
} elseif ($text == "💰 Enable card-number display") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['activeshowcardstatus'], null, 'HTML');
    update("user", "cardpayment", "1");
    update("setting", "showcard", "1");
} elseif ($text == "🔋 Renewal method" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $Methodextend, 'HTML');
    step('updateextendmethod', $from_id);
} elseif ($user['step'] == "updateextendmethod") {
    $aarayvalid = array(
        'ریست حجم و زمان',
        'اضافه شدن زمان و حجم به ماه بعد',
        'ریست زمان و اضافه کردن حجم قبلی',
        'ریست شدن حجم و اضافه شدن زمان',
        'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده'
    );
    if (!in_array($text, $aarayvalid)) {
        sendmessage($from_id, "❌ Invalid renewal method. Choose the correct method from the list", null, 'HTML');
        return;
    }
    update("marzban_panel", "Methodextend", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['Algortimeextend']['SaveData']);
    step('home', $from_id);
} elseif ($text == "♻️ Auto-confirm receipts" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    if ($paymentverify == "onauto") {
        sendmessage($from_id, "❌ First turn off auto-confirm without review.", null, 'HTML');
        return;
    }
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    $card_Status_auto = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['autoconfirmcard'], $card_Status_auto, 'HTML');
} elseif ($datain == "onautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffautoconfirmcard'], null);
} elseif ($datain == "offautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "onautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonautoconfirmcard'], null);
} elseif ($text == "/token") {
    $secret_key = select("admin", "*", "id_admin", $from_id, "select");
    $secret_key = base64_encode($secret_key['password']);
    sendmessage($from_id, "<code>$secret_key</code>", null, 'HTML');
} elseif ($text == "/token2") {
    $token = bin2hex(random_bytes(16));
    file_put_contents('api/hash.txt', $token);
    sendmessage($from_id, "Your API token: <code>$token</code>", null, 'HTML');
    sendDocument($from_id, 'api/documents.txt', "📌 Bot API documentation 
Notes: 
1 - If you need a specific endpoint, message the support account so it can be reviewed.");
} elseif ($text == "✅ Enable web panel" && $adminrulecheck['rule'] == "administrator") {
    $admin_select = select("admin", "*", "id_admin", $from_id, "select");
    $randomString = bin2hex(random_bytes(6));
    update("admin", "username", $from_id, "id_admin", $from_id);
    update("admin", "password", password_hash($randomString, PASSWORD_BCRYPT, ['cost' => 12]), "id_admin", $from_id);
    sendmessage($from_id, "✅  Your web panel was enabled successfully.


🔗Login URL: https://$domainhosts/panel
👤Username:  <code>$from_id</code>
🔑Password:  <code>$randomString</code>

⚠️ Tapping enable panel again will give you a new password.", null, 'HTML');
} elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['threestep'], $json_list_marzban_panel, 'HTML');
    step('getnamepanelconfig', $from_id);
} elseif ($user['step'] == "getnamepanelconfig") {
    $panelForOrder = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!$panelForOrder) {
        sendmessage($from_id, "❌ Panel not found. Choose from the list below.", $json_list_marzban_panel, 'HTML');
        return;
    }
    update("user", "Processing_value_tow", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['fourstep'], keyboard_admin_addorder_products($text), 'HTML');
    step('stependforaddorder', $from_id);
} elseif ($user['step'] == "stependforaddorder") {
    $panelForOrder = select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
    $targetUser = select("user", "*", "id", $user['Processing_value'], "select");
    if (!$panelForOrder || !$targetUser) {
        sendmessage($from_id, "❌ Panel or user not found. Restart the steps from the beginning.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_custom_service_product_choice($panelForOrder, (string) $text)) {
        if (($panelForOrder['type'] ?? '') === 'Manualsale') {
            sendmessage($from_id, "❌ Custom service is not available for manual sales.", $keyboardadmin, 'HTML');
            return;
        }
        $custompricevalue = panel_agent_field($panelForOrder, 'pricecustomvolume', (string) ($targetUser['agent'] ?? 'f'), '4000');
        $mainvolume = panel_agent_field($panelForOrder, 'mainvolume', (string) ($targetUser['agent'] ?? 'f'), '1');
        $maxvolume = panel_agent_field($panelForOrder, 'maxvolume', (string) ($targetUser['agent'] ?? 'f'), '1000');
        sendmessage($from_id, textbot_custom_volume_ask($custompricevalue, $mainvolume, $maxvolume), $backadmin, 'HTML');
        step('adminaddcustomvol', $from_id);
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    if (panel_method_asks_custom_username($panelForOrder['MethodUsername'] ?? '')) {
        sendmessage($from_id, textbot_get('text_select_username', $textbotlang['users']['selectusername']), $backadmin, 'HTML');
        step('adminaddcustomuser', $from_id);
        return;
    }
    $resultAdd = admin_provision_user_service($user['Processing_value'], [
        'panel' => $user['Processing_value_tow'],
        'product' => $text,
    ]);
    sendmessage($from_id, $resultAdd['ok'] ? $textbotlang['Admin']['addorder']['fivestep'] : ('❌ ' . $resultAdd['msg']), $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "adminaddcustomvol") {
    $panelForOrder = select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
    $targetUser = select("user", "*", "id", $user['Processing_value'], "select");
    if (!$panelForOrder || !$targetUser) {
        sendmessage($from_id, "❌ Panel or user not found.", $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $mainvolume = panel_agent_field($panelForOrder, 'mainvolume', (string) ($targetUser['agent'] ?? 'f'), '1');
    $maxvolume = panel_agent_field($panelForOrder, 'maxvolume', (string) ($targetUser['agent'] ?? 'f'), '1000');
    if (!ctype_digit((string) $text) || intval($text) < intval($mainvolume) || intval($text) > intval($maxvolume)) {
        sendmessage($from_id, textbot_custom_volume_invalid($mainvolume, $maxvolume), $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value_four", $text, "id", $from_id);
    sendmessage($from_id, textbot_get(
        'text_custom_month_ask',
        "⌛️ Choose the service duration\n📌 Each month equals 30 days"
    ), KeyboardCustomMonths($panelForOrder, 'admincustommonth_', 'adminaddorderback', (int) $text, $targetUser), 'html');
    step('adminaddcustommonth', $from_id);
} elseif ($datain == "adminaddorderback") {
    sendmessage($from_id, $textbotlang['Admin']['addorder']['fourstep'], keyboard_admin_addorder_products((string) $user['Processing_value_tow']), 'HTML');
    step('stependforaddorder', $from_id);
} elseif (preg_match('/^admincustommonth_(\d+)$/', $datain, $dataget) && $user['step'] == "adminaddcustommonth") {
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
        ]);
    }
    $months = (int) $dataget[1];
    $gb = (int) $user['Processing_value_four'];
    $panelForOrder = select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
    if (!$panelForOrder || !panel_custom_month_option($panelForOrder, $months)) {
        sendmessage($from_id, "❌ Selected duration is invalid.", $backadmin, 'HTML');
        return;
    }
    $customToken = 'customvolume_' . panel_custom_months_to_days($months) . '_' . $gb;
    update("user", "Processing_value_one", $customToken, "id", $from_id);
    if (panel_method_asks_custom_username($panelForOrder['MethodUsername'] ?? '')) {
        sendmessage($from_id, textbot_get('text_select_username', $textbotlang['users']['selectusername']), $backadmin, 'HTML');
        step('adminaddcustomuser', $from_id);
        return;
    }
    $resultAdd = admin_provision_user_service($user['Processing_value'], [
        'panel' => $user['Processing_value_tow'],
        'product' => $customToken,
        'gb' => $gb,
        'months' => $months,
        'custom' => true,
    ]);
    sendmessage($from_id, $resultAdd['ok'] ? $textbotlang['Admin']['addorder']['fivestep'] : ('❌ ' . $resultAdd['msg']), $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "adminaddcustomuser") {
    $resultAdd = admin_provision_user_service($user['Processing_value'], [
        'panel' => $user['Processing_value_tow'],
        'product' => $user['Processing_value_one'],
        'username' => $text,
    ]);
    sendmessage($from_id, $resultAdd['ok'] ? $textbotlang['Admin']['addorder']['fivestep'] : ('❌ ' . $resultAdd['msg']), $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ Wholesale minimum balance" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'];
    $textmin = "📌 Send the minimum amount for bulk purchase.
        
Current amount: $PaySetting";
    sendmessage($from_id, $textmin, $backadmin, 'HTML');
    step('minbalancebulk', $from_id);
} elseif ($user['step'] == "minbalancebulk") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $shopkeyboard, 'HTML');
    update("shopSetting", "value", $text, "Namevalue", "minbalancebuybulk");
    step('home', $from_id);
} elseif (preg_match('/showcarduser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($id_user, "💳 Card-to-card payment is now enabled. You can complete your purchase.", null, 'HTML');
    sendmessage($from_id, "✅  Card number enabled", null, 'HTML');
    update("user", "cardpayment", "1", "id", $id_user);
} elseif (preg_match('/carduserhide-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($from_id, "✅  Card number disabled", null, 'HTML');
    update("user", "cardpayment", "0", "id", $id_user);
} elseif ($text == "❌ Remove card number" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the card number you want to delete.", $list_card_remove, 'HTML');
    step('getcardremove', $from_id);
} elseif ($user['step'] == "getcardremove") {
    $stmt = $pdo->prepare("DELETE FROM card_number WHERE cardnumber = :cardnumber");
    $stmt->bindParam(':cardnumber', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "✅ Card number deleted successfully.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/rejectrequesta_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    update("Requestagent", "status", "reject", "id", $id_user);
    $userinfo = select("user", "*", "id", $id_user, "select");
    $Balancenew = $userinfo['Balance'] + intval($setting['agentreqprice']);
    update("user", "Balance", $Balancenew, "id", $id_user);
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "This request was already reviewed by another admin",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅Request rejected.", 'callback_data' => "reject"]],
        ]
    ]);
    sendmessage($from_id, "✅ Request rejected successfully.", null, 'HTML');
    sendmessage($id_user, "❌ Your reseller request was declined.", null, 'HTML');
    $textrequestagent = "📣 A user submitted an agency request. Please review the info and set the status.

Numeric ID: $id_user
Username: {$request_agent['username']} 
Description:  {$request_agent['Description']} ";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
} elseif (preg_match('/addagentrequest_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "Request not found.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "This request was already reviewed by another admin",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $defaultAgentType = 'n';
    $agentTypeLabels = [
        'n' => 'Regular agent',
        'n2' => 'Advanced agent',
    ];
    update("Requestagent", "status", "accept", "id", $id_user);
    update("Requestagent", "type", $defaultAgentType, "id", $id_user);
    update("user", "agent", $defaultAgentType, "id", $id_user);
    update("user", "expire", null, "id", $id_user);
    sendmessage($id_user, "✅ Your reseller request was approved. You are now a reseller.", null, 'HTML');
    sendmessage($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    $agentTypeButtons = [];
    foreach ($agentTypeLabels as $typeCode => $label) {
        $buttonText = ($typeCode === $defaultAgentType ? "✅ " : "") . $label;
        $agentTypeButtons[] = [
            'text' => $buttonText,
            'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
        ];
    }
    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅Request approved.", 'callback_data' => "accept"]],
            $agentTypeButtons,
            [['text' => "⏱️ Agency expiry", 'callback_data' => 'expireset_' . $id_user]],
            [['text' => "Manage user", 'callback_data' => 'manageuser_' . $id_user]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textrequestagent = "📣 A user submitted an agency request. Please review the info and set the status.\n\nNumeric ID: $id_user\nUsername: {$request_agent['username']}\nDescription:  {$request_agent['Description']} ";
    $textrequestagent .= "\nStatus: approved ({$agentTypeLabels[$defaultAgentType]})";
    $textrequestagent .= "\nUse the buttons below to change the agent type.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "Request approved and regular agent enabled.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/^setagenttype_(n|n2)_(\w+)/', $datain, $datagetr)) {
    $selectedType = $datagetr[1];
    $id_user = $datagetr[2];
    $agentTypeLabels = [
        'n' => 'Regular agent',
        'n2' => 'Advanced agent',
    ];
    if (!array_key_exists($selectedType, $agentTypeLabels)) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['agent']['invalidtypeagent'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "agent", $selectedType, "id", $id_user);
    update("Requestagent", "type", $selectedType, "id", $id_user);
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if ($request_agent) {
        $agentTypeButtons = [];
        foreach ($agentTypeLabels as $typeCode => $label) {
            $buttonText = ($typeCode === $selectedType ? "✅ " : "") . $label;
            $agentTypeButtons[] = [
                'text' => $buttonText,
                'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
            ];
        }
        $keyboardreject = json_encode([
            'inline_keyboard' => [
                [['text' => "✅Request approved.", 'callback_data' => "accept"]],
                $agentTypeButtons,
                [['text' => "⏱️ Agency expiry", 'callback_data' => 'expireset_' . $id_user]],
                [['text' => "Manage user", 'callback_data' => 'manageuser_' . $id_user]]
            ]
        ], JSON_UNESCAPED_UNICODE);
        $textrequestagent = "📣 A user submitted an agency request. Please review the info and set the status.\n\nNumeric ID: $id_user\nUsername: {$request_agent['username']}\nDescription:  {$request_agent['Description']} ";
        $textrequestagent .= "\nStatus: approved ({$agentTypeLabels[$selectedType]})";
        $textrequestagent .= "\nUse the buttons below to change the agent type.";
        Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "Agent type changed to {$agentTypeLabels[$selectedType]}.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif ($datain == "iranpay2setting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $trnado, 'HTML');
} elseif ($datain == "iranpay3setting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $iranpaykeyboard, 'HTML');
} elseif ($text == "Tornado gateway status" && $adminrulecheck['rule'] == "administrator") {
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    $textternado = "In this section you can turn the Tornado gateway on or off";
    sendmessage($from_id, $textternado, $statusternadoo, 'HTML');
} elseif ($datain == "onternado") {
    update("PaySetting", "ValuePay", "offternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "Turned off", $statusternadoo);
} elseif ($datain == "offternado") {
    update("PaySetting", "ValuePay", "onternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "Turned on", $statusternadoo);
} elseif ($text == "API T" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $texttronseller = "💳 Get your merchant code and enter it here
        
Your current merchant code: {$PaySetting['ValuePay']}";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiternado', $from_id);
} elseif ($user['step'] == "apiternado") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiternado");
    step('home', $from_id);
} elseif ($datain == "affilnecurrencysetting") {
    sendmessage($from_id, "Choose an option", $tronnowpayments, 'HTML');
} elseif ($text == "🗂 Card-to-card gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("getnamecarttocart", $from_id);
} elseif ($user['step'] == "getnamecarttocart") {
    sendmessage($from_id, "✅  Text set successfully.", $CartManage, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "carttocart");
    step("home", $from_id);
} elseif ($text == "🗂 NowPayments gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("getnamenowpayment", $from_id);
} elseif ($user['step'] == "getnamenowpayment") {
    sendmessage($from_id, "✅  Text set successfully.", $nowpayment_setting_keyboard, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textsnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 Unverified IRR gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("getnamecarttopaynotverify", $from_id);
} elseif ($user['step'] == "getnamecarttopaynotverify") {
    sendmessage($from_id, "✅  Text set successfully.", $CartManage, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textpaymentnotverify");
    step("home", $from_id);
} elseif ($text == "🗂 Plisio gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextnowpayment", $from_id);
} elseif ($user['step'] == "gettextnowpayment") {
    sendmessage($from_id, "✅  Text set successfully.", $NowPaymentsManage, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 Offline crypto gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextnowpaymentTRON", $from_id);
} elseif ($user['step'] == "gettextnowpaymentTRON") {
    sendmessage($from_id, "✅  Text set successfully.", $tronnowpayments, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "textnowpaymenttron");
    step("home", $from_id);
} elseif ($text == "🗂 IRR gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextiranpay2", $from_id);
} elseif ($user['step'] == "gettextiranpay2") {
    sendmessage($from_id, "✅  Text set successfully.", $Swapinokey, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "iranpay2");
    step("home", $from_id);
} elseif ($text == "🗂 Stars gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextstartelegram", $from_id);
} elseif ($user['step'] == "gettextstartelegram") {
    sendmessage($from_id, "✅  Text set successfully.", $Swapinokey, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_star_telegram");
    step("home", $from_id);
} elseif ($text == "🗂 IRR gateway 2 name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextiranpay3", $from_id);
} elseif ($user['step'] == "gettextiranpay3") {
    sendmessage($from_id, "✅  Text set successfully.", $trnado, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "iranpay3");
    step("home", $from_id);
} elseif ($text == "🗂 IRR gateway 3 name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextiranpay1", $from_id);
} elseif ($user['step'] == "gettextiranpay1") {
    sendmessage($from_id, "✅  Text set successfully.", $iranpaykeyboard, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "iranpay1");
    step("home", $from_id);
} elseif ($text == "🗂 Aghaye Pardakht gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextaqayepardakht", $from_id);
} elseif ($user['step'] == "gettextaqayepardakht") {
    sendmessage($from_id, "✅  Text set successfully.", $aqayepardakht, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "aqayepardakht");
    step("home", $from_id);
} elseif ($text == "🗂 Zarinpal gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettextzarinpal", $from_id);
} elseif ($user['step'] == "gettextzarinpal") {
    sendmessage($from_id, "✅  Text set successfully.", $keyboardzarinpal, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "zarinpal");
    step("home", $from_id);
} elseif ($text == "🗂 Tetraminator gateway name") {
    sendmessage($from_id, " 📌 Send the gateway name", $backadmin, 'HTML');
    step("gettexttetraminator", $from_id);
} elseif ($user['step'] == "gettexttetraminator") {
    sendmessage($from_id, "✅  Text set successfully.", $keyboardtetraminator, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "tetraminator");
    step("home", $from_id);
} elseif ($text == "⚙️ Disabled-account inbound" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocoldisable', $from_id);
} elseif ($user['step'] == "getprotocoldisable") {
    global $json_list_marzban_panel_inbounds;
    $protocol = ["vless", "vmess", "trojan", "shadowsocks"];
    if (!in_array($text, $protocol)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['invalidprotocol'], null, 'HTML');
        return;
    }
    $getinbounds = getinbounds($user['Processing_value'])[$text];
    $list_marzban_panel_inbounds = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($getinbounds as $button) {
        $list_marzban_panel_inbounds['keyboard'][] = [
            ['text' => $button['tag']]
        ];
    }
    $list_marzban_panel_inbounds['keyboard'][] = [
        ['text' => "🏠 Back to management menu"],
    ];
    $json_list_marzban_panel_inbounds = json_encode($list_marzban_panel_inbounds);
    update("user", "Processing_value_one", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['getInbound'], $json_list_marzban_panel_inbounds, 'HTML');
    step('getInbounddisable', $from_id);
} elseif ($user['step'] == "getInbounddisable") {
    sendmessage($from_id, "Inbound name saved successfully", $optionMarzban, 'HTML');
    $textpro = "{$user['Processing_value_one']}*$text";
    update("marzban_panel", "inbound_deactive", $textpro, "name_panel", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🗑 Optimize bot" && $adminrulecheck['rule'] == "administrator") {
    $textoptimize = "❌❌❌❌❌❌❌ Read the text below carefully

📌 Confirming the option below will run these operations. They cannot be undone

1 - Inactive orders will be deleted
2 - Unpaid orders will be deleted.
3 - Orders deleted by admin 
4- Inactive test services will be deleted
5 - Orders deleted by the user 
6 - Orders whose time or volume has ended
";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ Confirm and optimize", 'callback_data' => 'optimizebot'],
            ],
        ]
    ]);
    sendmessage($from_id, $textoptimize, $Response, 'HTML');
} elseif ($datain == "optimizebot") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countunpiadorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countdisableorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE (Status = 'removebyadmin' or Status = 'removedbyadmin')");
    $stmt->execute();
    $countremoveadminorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $countdisableordtester = $stmt->rowCount();
    #remove data
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removedbyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removeTime'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removevolume'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyuser' ");
    $stmt->execute();
    $optimizebot = "
✅ $countunpiadorder unpaid orders deleted
✅ $countdisableorder inactive orders deleted.
✅ $countremoveadminorder admin-deleted orders removed
✅ $countdisableordtester test orders deleted.";
    Editmessagetext($from_id, $message_id, $optimizebot, null);
    $time = time();
    $logss = "optimize_{$countunpiadorder}_{$countdisableorder}_{$countremoveadminorder}_{$countdisableordtester}_$time";
    file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
} elseif ($datain == "settimecornvolume") {
    sendmessage($from_id, "📌 Here you can set sending a warning when the user's volume reaches x. Send the volume in GB.", $backadmin, 'HTML');
    step("getvolumewarn", $from_id);
} elseif ($user['step'] == "getvolumewarn") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌ Invalid value", null, 'html');
        return;
    }
    update("setting", "volumewarn", $text);
    sendmessage($from_id, "✅ Changes saved successfully", $setting_panel, 'HTML');
    step("home", $from_id);
} elseif ($text == "🔧 Create manual config") {
    savedata("clear", "idpanel", $user['Processing_value']);
    sendmessage($from_id, "📌Here you can create and receive an order manually 
⚠️ If you want the config added to the user's account so they can manage it, use Add order.
- To add a config, first send the username.", $backadmin, 'HTML');
    step('getusernameconfigcr', $from_id);
} elseif ($user['step'] == "getusernameconfigcr") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    step('getcountcreate', $from_id);
    sendmessage($from_id, "📌 Send how many configs to create. Maximum is 10", $backadmin, 'HTML');
} elseif ($user['step'] == "getcountcreate") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) > 10 or intval($text) < 0) {
        sendmessage($from_id, "❌ Minimum is 1 and maximum is 10.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "count", $text);
    step('getvolumesconfig', $from_id);
    sendmessage($from_id, "📌 Send the account usage volume. Volume is in gigabytes.", $backadmin, 'HTML');
} elseif ($user['step'] == "getvolumesconfig") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌ Invalid value", null, 'html');
        return;
    }
    update("user", "Processing_value_tow", $text, "id", $from_id);
    sendmessage($from_id, "📌 Send the service duration. Time is in days.", $backadmin, 'HTML');
    step("gettimeaccount", $from_id);
} elseif ($user['step'] == "gettimeaccount") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌ Invalid value", null, 'html');
        return;
    }
    if (intval($text) == 0) {
        $expire = 0;
    } else {
        $datetimestep = strtotime("+" . $text . "days");
        $expire = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $expire,
        'data_limit' => $user['Processing_value_tow'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => "$username",
        'type' => "new by admin $from_id"
    );
    $panel = select("marzban_panel", "*", "name_panel", $userdata['idpanel'], "select");
    for ($i = 0; $i < $userdata['count']; $i++) {
        $usernameconfig = $user['Processing_value_one'] . "_" . $i;
        $dataoutput = $ManagePanel->createUser($userdata['idpanel'], "usertest", $usernameconfig, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            error_log("Admin bulk create failed | panel={$panel['name_panel']} | admin={$from_id} | username={$usernameconfig} | reason={$dataoutput['msg']}");
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], null, 'HTML');
            $texterros = "
⭕️ A user tried to get an account but config creation failed and no config was given
✍️ Error: 
{$dataoutput['msg']}
User ID: $from_id
Username: @$username
Panel name: {$panel['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
        $randomString = bin2hex(random_bytes(5));
        $output_config_link = $panel['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $config = "";
        if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $datatextbot['textafterpay'] = $panel['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "ibsng" || $panel['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($text) == 0)
            $text = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', "Custom plan", $textcreatuser);
        $textcreatuser = str_replace('{location}', $panel['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $text, $textcreatuser);
        $textcreatuser = str_replace('{volume}', $user['Processing_value_tow'], $textcreatuser);
        $textcreatuser = str_replace('{config}', $output_config_link, $textcreatuser);
        $textcreatuser = str_replace('{links}', $config, $textcreatuser);
        $textcreatuser = str_replace('{links2}', $output_config_link, $textcreatuser);
        if ($panel['type'] == "Manualsale" || $panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
        }
        sendMessageService($panel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], null, $textcreatuser, $randomString);
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    $text_report = "";
    if (strlen($setting['Channel_Report']) > 0) {
        $text_report = " 🛍 Config created by admin 

Config username: {$user['Processing_value_one']}
Config volume: {$user['Processing_value_tow']} GB
Config duration: $text days
Admin numeric ID: $from_id
Admin username: $username
Create count: {$userdata['count']}";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("user", "Processing_value", $userdata['idpanel'], "id", $from_id);
    step("home", $from_id);
} elseif ($text == "📬 Bot reports" && $adminrulecheck['rule'] == "administrator") {
    $textupdate = "💬 | Bot report\n\n🔹 | If you run into a <b>bug or issue</b>, please tell us so we can review it.\n➖➖➖➖➖➖➖➖➖➖➖\n🔹 | If you hit a <b>serious bug</b> or unusual behavior, report it sooner so it can be fixed.\n➖➖➖➖➖➖➖➖➖➖➖\n🔹 | If you have a suggestion for a <b>new feature</b> or an idea to improve the bot, we would be glad to hear it.\n➖➖➖➖➖➖➖➖➖➖➖\n🔹 | If you need <b>help</b> or guidance, you can also contact the support team in private.\n\n📩 | To send a report, suggestion, or help request, post in the <b>Picha group</b>:\n<a href=\"https://t.me/mirzapanelgroup\" rel=\"nofollow\" target=\"_blank\">Picha Group</a>";
    sendmessage($from_id, $textupdate, null, 'HTML');
    step('home', $from_id);
} elseif ($text == "🛠 Panel features") {
    sendmessage($from_id, "🪚 To use this feature, choose one of the panels below", $json_list_marzban_panel, 'HTML');
    step('getlocoption', $from_id);
} elseif ($user['step'] == "getlocoption") {
    update("user", "Processing_value", $text, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $text, "select")['type'];
    if ($typepanel == "marzban") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "hiddify") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🖥 Node management" || $datain == "bakcnode") {
    if ($adminnumber != $from_id) {
        sendmessage($from_id, "❌ This section is only available to the main admin", null, 'HTML');
        return;
    }
    $nodes = Get_Nodes($user['Processing_value']);
    if (!empty($nodes['error'])) {
        sendmessage($from_id, $nodes['error'], null, 'HTML');
        return;
    }
    if (!empty($nodes['status']) && $nodes['status'] != 200) {
        sendmessage($from_id, "❌  An error occurred. Error code:  {$nodes['status']}", null, 'HTML');
        return;
    }
    $nodes = json_decode($nodes['body'], true);
    if (count($nodes) == 0) {
        sendmessage($from_id, "❌  Node settings cannot be viewed", null, 'HTML');
        return;
    }
    $keyboardlistsnode['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "actionnode"],
        ['text' => "Name", 'callback_data' => "namenode"]
    ];
    foreach ($nodes as $result) {
        if (!isset($result['id']))
            continue;
        $keyboardlistsnode['inline_keyboard'][] = [
            ['text' => "Manage", 'callback_data' => "node_{$result['id']}"],
            ['text' => $result['name'], 'callback_data' => "node_{$result['id']}"],
        ];
    }
    $keyboardlistsnode = json_encode($keyboardlistsnode);
    if ($datain == "bakcnode") {
        Editmessagetext($from_id, $message_id, "📌 In this section you can manage Marzban panel nodes.", $keyboardlistsnode);
    } else {
        sendmessage($from_id, "📌 In this section you can manage Marzban panel nodes.", $keyboardlistsnode, 'HTML');
    }
} elseif (preg_match('/^node_(.*)/', $datain, $dataget)) {
    $nodeid = $dataget[1];
    update("user", "Processing_value_one", $nodeid, "id", $from_id);
    $node = Get_Node($user['Processing_value'], $nodeid);
    if (!empty($node['error'])) {
        sendmessage($from_id, $node['error'], null, 'HTML');
        return;
    }
    if (!empty($node['status']) && $node['status'] != 200) {
        sendmessage($from_id, "❌  An error occurred. Error code:  {$node['status']}", null, 'HTML');
        return;
    }
    $nodeusage = Get_usage_Nodes($user['Processing_value']);
    if (!empty($nodeusage['error'])) {
        sendmessage($from_id, $nodeusage['error'], null, 'HTML');
        return;
    }
    if (!empty($nodeusage['status']) && $nodeusage['status'] != 200) {
        sendmessage($from_id, "❌  An error occurred. Error code:  {$nodeusage['status']}", null, 'HTML');
        return;
    }
    $node = json_decode($node['body'], true);
    $nodeusage = json_decode($nodeusage['body'], true);
    foreach ($nodeusage['usages'] as $nodeusages) {
        if ($nodeusages['node_id'] == $nodeid) {
            $nodeusage = $nodeusages;
            break;
        }
    }
    $sumvolume = formatBytes($nodeusage['downlink'] + $nodeusage['uplink']);
    $textnode = "📌 Node info 

🖥 Node name:  {$node['name']}
🌍 Node IP: {$node['address']}
🔻 Node port: {$node['port']}
🔺 Node API port: {$node['api_port']}
🔋Total node usage: $sumvolume
🔄 Node usage coefficient: {$node['usage_coefficient']}
🔵 Node xray version: {$node['xray_version']}
🟢 Node status: {$node['status']}
    ";
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🗂 Rename node", 'callback_data' => "changenamenode"],
                ['text' => "🔄 Change node usage coefficient", 'callback_data' => "changecoefficient"],
            ],
            [
                ['text' => "🌍 Change node IP address", 'callback_data' => "changeipnode"],
                ['text' => "♻️ Reconnect node", 'callback_data' => "reconnectnode"],
            ],
            [
                ['text' => "❌ Delete node", 'callback_data' => "removenode"],
            ],
            [
                ['text' => "🔙 Back to node list", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "changecoefficient") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 Send your node usage coefficient.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getusage_coefficient", $from_id);
} elseif ($user['step'] == "getusage_coefficient") {
    $config = array(
        'usage_coefficient' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    sendmessage($from_id, "✅ Node usage coefficient saved successfully.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changenamenode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 Send your node name.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getnamenode", $from_id);
} elseif ($user['step'] == "getnamenode") {
    $config = array(
        'name' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    sendmessage($from_id, "✅  Node name saved successfully.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changeipnode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 Send the node IP.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getipnodeset", $from_id);
} elseif ($user['step'] == "getipnodeset") {
    $config = array(
        'address' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    sendmessage($from_id, "✅  Node address saved successfully.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "reconnectnode") {
    reconnect_node($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "✅ Node reconnected.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "removenode") {
    removenode($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 Back to node ", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    $textnode = "✅ Node deleted successfully";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($text == "💎 Finance" && $adminrulecheck['rule'] == "administrator") {
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    if ($arzireyali1 != "onSwapinoBot" && $arzireyali1 != "offSwapinoBot") {
        update("PaySetting", "ValuePay", "onSwapinoBot", "NamePay", "statusSwapWallet");
        $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    }
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $tetraminator_status = getPaySettingValue('statustetraminator', 'offtetraminator');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $payment_status_cryptomus = getPaySettingValue('statuscryptomus', 'offcryptomus');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $tetraminatorstatus = [
        'ontetraminator' => $textbotlang['Admin']['Status']['statuson'],
        'offtetraminator' => $textbotlang['Admin']['Status']['statusoff']
    ][$tetraminator_status];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $cryptomus_status = [
        'oncryptomus' => $textbotlang['Admin']['Status']['statuson'],
        'offcryptomus' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_cryptomus] ?? $textbotlang['Admin']['Status']['statusoff'];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💸 Wallet withdrawal", 'callback_data' => "wd_menu"],
            ],
            [
                ['text' => "Action", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 Card to card", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "cm_set"],
                ['text' => "🧾 Operations", 'callback_data' => "cm_ops"],
                ['text' => $cryptomus_status, 'callback_data' => "editpayment-cryptomus-$payment_status_cryptomus"],
                ['text' => "💠 Cryptomus", 'callback_data' => "cm_ops"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => "📌 Currency-rial 1", 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => "📌 Currency-rial 2", 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => "📌Currency-rial 3", 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 Aghaye Pardakht", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 Zarinpal", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "tetraminatorsetting"],
                ['text' => $tetraminatorstatus, 'callback_data' => "editpayment-tetraminator-$tetraminator_status"],
                ['text' => "💸 Tetraminator", 'callback_data' => "tetraminator"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵Offline crypto", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ Maximum balance top-up", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ Minimum balance top-up", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "Wallet address", 'callback_data' => "walletaddress"],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 You can manage gateways from the list below.

⚠️ The Picha team does not guarantee any gateway; use is entirely at your own risk", $Bot_Status, 'HTML');
} elseif (
    in_array((string) $datain, ['cm_set', 'cm_ops'], true)
    || preg_match('/^cm_(ap|ca|rf)_(\d+)$/', (string) $datain, $cmActionMatch)
    || preg_match('/^cm_rc_(\d+)_([01])$/', (string) $datain, $cmRefundMatch)
    || in_array((string) ($user['step'] ?? ''), [
        'cm_merchant', 'cm_api', 'cm_min', 'cm_max', 'cm_cashback',
        'cm_button_text', 'cm_help', 'cm_cancel_reason', 'cm_refund_address'
    ], true)
    || in_array((string) $text, [
        '🪪 Cryptomus Merchant UUID', '🔐 Cryptomus Payment API Key',
        '⬇️ Cryptomus minimum USD', '⬆️ Cryptomus maximum USD',
        '💰 Cryptomus cashback', '🗂 Cryptomus button text', '📚 Cryptomus tutorial'
    ], true)
) {
    if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
        if ($callback_query_id) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'Only the main admin has access',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $cmClearState = static function () use ($from_id) {
        step('home', $from_id);
        update('user', 'Processing_value', '0', 'id', $from_id);
    };
    $cmApiMessage = static function (array $result) {
        $error = $result['error'] ?? 'Unknown error';
        if (is_array($error)) {
            $error = json_encode($error, JSON_UNESCAPED_UNICODE);
        }
        return htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    if ($datain === 'cm_set') {
        step('home', $from_id);
        sendmessage($from_id, cryptomus_admin_settings_text($domainhosts), $CryptomusManage, 'HTML');
        return;
    }
    if ($datain === 'cm_ops') {
        step('home', $from_id);
        update('user', 'Processing_value', '0', 'id', $from_id);
        $view = cryptomus_admin_operations_view();
        if ($message_id) {
            Editmessagetext($from_id, $message_id, $view['text'], $view['keyboard'], 'HTML');
        } else {
            sendmessage($from_id, $view['text'], $view['keyboard'], 'HTML');
        }
        return;
    }

    $cmActionMatch = [];
    $cmRefundMatch = [];
    preg_match('/^cm_(ap|ca|rf)_(\d+)$/', (string) $datain, $cmActionMatch);
    preg_match('/^cm_rc_(\d+)_([01])$/', (string) $datain, $cmRefundMatch);

    if (($cmActionMatch[1] ?? '') === 'ap') {
        $payment = cryptomus_admin_payment_by_id((int) $cmActionMatch[2]);
        if (!$payment || !in_array((string) ($payment['gateway_status'] ?? ''), ['wrong_amount', 'wrong_amount_waiting'], true)) {
            sendmessage($from_id, "❌ This payment is no longer in an underpayment-approval state.", null, 'HTML');
            return;
        }
        $result = cryptomus_approve_underpayment((string) $payment['id_order']);
        if (empty($result['ok'])) {
            sendmessage($from_id, "❌ Approval request failed: <code>" . $cmApiMessage($result) . "</code>", null, 'HTML');
            return;
        }
        sendmessage(
            $from_id,
            "✅ Underpayment approval request was sent to Cryptomus.\n\n⚠️ Service delivery/balance increase happens only after a valid webhook or cron check and final payment confirmation.",
            null,
            'HTML'
        );
        return;
    }

    if (($cmActionMatch[1] ?? '') === 'ca') {
        $payment = cryptomus_admin_payment_by_id((int) $cmActionMatch[2]);
        if (!$payment || !in_array((string) ($payment['gateway_status'] ?? ''), ['wrong_amount', 'wrong_amount_waiting'], true)) {
            sendmessage($from_id, "❌ This payment can no longer be cancelled.", null, 'HTML');
            return;
        }
        update('user', 'Processing_value', json_encode(['payment_id' => (int) $payment['id']], JSON_UNESCAPED_UNICODE), 'id', $from_id);
        step('cm_cancel_reason', $from_id);
        sendmessage($from_id, "✍️ Send the cancellation reason for order <code>" . htmlspecialchars((string) $payment['id_order'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>.\nThis does not issue a refund.", $backadmin, 'HTML');
        return;
    }

    if (($user['step'] ?? '') === 'cm_cancel_reason') {
        $reason = trim((string) text_from_telegram_update($update));
        $state = json_decode((string) ($user['Processing_value'] ?? ''), true);
        $payment = cryptomus_admin_payment_by_id((int) ($state['payment_id'] ?? 0));
        if ($reason === '' || mb_strlen($reason) > 500) {
            sendmessage($from_id, "❌ Reason must be between 1 and 500 characters.", $backadmin, 'HTML');
            return;
        }
        if (!$payment || !in_array((string) ($payment['gateway_status'] ?? ''), ['wrong_amount', 'wrong_amount_waiting'], true)) {
            $cmClearState();
            sendmessage($from_id, "❌ Payment status changed and it was not cancelled.", $CryptomusManage, 'HTML');
            return;
        }
        $cancelled = cryptomus_cancel_underpayment((string) $payment['id_order'], $reason);
        $cmClearState();
        if (!$cancelled) {
            sendmessage($from_id, "❌ Cancel failed; this payment may already have been processed.", $CryptomusManage, 'HTML');
            return;
        }
        $safeReason = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        sendmessage((int) $payment['id_user'], "❌ Your Cryptomus payment was cancelled.\nReason: {$safeReason}\n\nThis cancellation does not include a refund; contact support for follow-up.", null, 'HTML');
        sendmessage($from_id, "✅ Payment cancelled and the user was notified. UUID and gateway info were kept and no refund was recorded.", $CryptomusManage, 'HTML');
        return;
    }

    if (($cmActionMatch[1] ?? '') === 'rf') {
        $payment = cryptomus_admin_payment_by_id((int) $cmActionMatch[2]);
        if (!$payment
            || ($payment['payment_Status'] ?? '') !== 'paid'
            || ($payment['fulfillment_state'] ?? '') !== 'completed'
            || in_array((string) ($payment['refund_status'] ?? ''), ['refund_process', 'refund_paid'], true)
        ) {
            sendmessage($from_id, "❌ This payment is not eligible for a refund.", null, 'HTML');
            return;
        }
        update('user', 'Processing_value', json_encode(['payment_id' => (int) $payment['id']], JSON_UNESCAPED_UNICODE), 'id', $from_id);
        step('cm_refund_address', $from_id);
        sendmessage($from_id, "📬 Send the full-refund destination address (8 to 256 characters; no spaces).\n\n⚠️ The address is never inferred from the callback.", $backadmin, 'HTML');
        return;
    }

    if (($user['step'] ?? '') === 'cm_refund_address') {
        $address = trim((string) $text);
        $state = json_decode((string) ($user['Processing_value'] ?? ''), true);
        $paymentId = (int) ($state['payment_id'] ?? 0);
        if (strlen($address) < 8 || strlen($address) > 256 || !preg_match('/^[A-Za-z0-9:._-]+$/', $address)) {
            sendmessage($from_id, "❌ Invalid address. Only Latin letters, digits, and <code>: . _ -</code>, with no spaces, length 8 to 256.", $backadmin, 'HTML');
            return;
        }
        $payment = cryptomus_admin_payment_by_id($paymentId);
        if (!$payment
            || ($payment['payment_Status'] ?? '') !== 'paid'
            || ($payment['fulfillment_state'] ?? '') !== 'completed'
            || in_array((string) ($payment['refund_status'] ?? ''), ['refund_process', 'refund_paid'], true)
        ) {
            $cmClearState();
            sendmessage($from_id, "❌ Payment status changed and the refund was not prepared.", $CryptomusManage, 'HTML');
            return;
        }
        update('user', 'Processing_value', json_encode([
            'payment_id' => $paymentId,
            'refund_address' => $address,
        ], JSON_UNESCAPED_UNICODE), 'id', $from_id);
        step('home', $from_id);
        $confirmKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '✅ Recipient pays the fee (recommended)', 'callback_data' => "cm_rc_{$paymentId}_0"]],
            [['text' => '⚠️ Merchant pays the fee', 'callback_data' => "cm_rc_{$paymentId}_1"]],
            [['text' => 'Cancel', 'callback_data' => 'cm_ops']],
        ]]);
        $safeAddress = htmlspecialchars($address, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        sendmessage(
            $from_id,
            "⚠️ <b>Confirm full refund</b>\n\nDestination: <code>{$safeAddress}</code>\nThis refunds the full amount and does not reverse internal service delivery or user balance.\n\nRecommended mode: <b>Recipient pays the fee</b>.",
            $confirmKeyboard,
            'HTML'
        );
        return;
    }

    if (isset($cmRefundMatch[1])) {
        $paymentId = (int) $cmRefundMatch[1];
        $merchantBearsFee = $cmRefundMatch[2] === '1';
        $state = json_decode((string) ($user['Processing_value'] ?? ''), true);
        $address = (string) ($state['refund_address'] ?? '');
        $statePaymentId = (int) ($state['payment_id'] ?? 0);
        $payment = cryptomus_admin_payment_by_id($paymentId);
        if ($statePaymentId !== $paymentId
            || strlen($address) < 8
            || strlen($address) > 256
            || !preg_match('/^[A-Za-z0-9:._-]+$/', $address)
            || !$payment
            || ($payment['payment_Status'] ?? '') !== 'paid'
            || ($payment['fulfillment_state'] ?? '') !== 'completed'
            || in_array((string) ($payment['refund_status'] ?? ''), ['refund_process', 'refund_paid'], true)
        ) {
            $cmClearState();
            sendmessage($from_id, "❌ Refund confirmation expired or payment status changed.", null, 'HTML');
            return;
        }
        $result = cryptomus_refund(['uuid' => (string) $payment['dec_not_confirmed']], $address, $merchantBearsFee);
        $cmClearState();
        if (empty($result['ok'])) {
            sendmessage($from_id, "❌ Refund request failed: <code>" . $cmApiMessage($result) . "</code>", $CryptomusManage, 'HTML');
            return;
        }
        sendmessage($from_id, "✅ Full refund request recorded. Track the final refund status from Cryptomus operations.\n⚠️ Internal delivery/user balance was not reversed automatically.", $CryptomusManage, 'HTML');
        return;
    }

    $settingPrompts = [
        '🪪 Cryptomus Merchant UUID' => ['cm_merchant', 'Send the Merchant UUID.'],
        '🔐 Cryptomus Payment API Key' => ['cm_api', 'Send the new Payment API Key. The current key is never shown.'],
        '⬇️ Cryptomus minimum USD' => ['cm_min', 'Send the minimum USD amount as a non-negative number.'],
        '⬆️ Cryptomus maximum USD' => ['cm_max', 'Send the maximum USD amount as a non-negative number.'],
        '💰 Cryptomus cashback' => ['cm_cashback', 'Send the cashback percent from 0 to 100.'],
        '🗂 Cryptomus button text' => ['cm_button_text', 'Send the Cryptomus button display text.'],
        '📚 Cryptomus tutorial' => ['cm_help', "Send the tutorial as text, photo, or video.\nTo disable it, send <code>2</code>."],
    ];
    if (isset($settingPrompts[$text])) {
        step($settingPrompts[$text][0], $from_id);
        sendmessage($from_id, $settingPrompts[$text][1], $backadmin, 'HTML');
        return;
    }

    $currentStep = (string) ($user['step'] ?? '');
    if ($currentStep === 'cm_merchant' || $currentStep === 'cm_api') {
        $value = trim((string) $text);
        if ($value === '' || strlen($value) > 500) {
            sendmessage($from_id, "❌ Invalid value.", $backadmin, 'HTML');
            return;
        }
        $key = $currentStep === 'cm_merchant' ? 'merchant_cryptomus' : 'apicryptomus';
        update('PaySetting', 'ValuePay', $value, 'NamePay', $key);
        $cmClearState();
        sendmessage($from_id, $currentStep === 'cm_api' ? "✅ Payment API Key saved and will not be displayed." : "✅ Merchant UUID saved.", $CryptomusManage, 'HTML');
        return;
    }

    if (in_array($currentStep, ['cm_min', 'cm_max', 'cm_cashback'], true)) {
        $value = trim((string) $text);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $value)) {
            sendmessage($from_id, "❌ Only a non-negative number is valid.", $backadmin, 'HTML');
            return;
        }
        if ($currentStep === 'cm_cashback' && cryptomus_decimal_compare($value, '100') === 1) {
            sendmessage($from_id, "❌ Cashback must be between 0 and 100.", $backadmin, 'HTML');
            return;
        }
        $min = $currentStep === 'cm_min' ? $value : (string) getPaySettingValue('minbalancecryptomus', '0');
        $max = $currentStep === 'cm_max' ? $value : (string) getPaySettingValue('maxbalancecryptomus', '0');
        if ($currentStep !== 'cm_cashback' && cryptomus_decimal_compare($min, $max) === 1) {
            sendmessage($from_id, "❌ Minimum amount cannot be greater than maximum.", $backadmin, 'HTML');
            return;
        }
        $key = [
            'cm_min' => 'minbalancecryptomus',
            'cm_max' => 'maxbalancecryptomus',
            'cm_cashback' => 'chashbackcryptomus',
        ][$currentStep];
        update('PaySetting', 'ValuePay', $value, 'NamePay', $key);
        $cmClearState();
        sendmessage($from_id, "✅ Value saved successfully.", $CryptomusManage, 'HTML');
        return;
    }

    if ($currentStep === 'cm_button_text') {
        $value = trim((string) text_from_telegram_update($update));
        if ($value === '' || mb_strlen($value) > 64) {
            sendmessage($from_id, "❌ Button text must be between 1 and 64 characters.", $backadmin, 'HTML');
            return;
        }
        update('textbot', 'text', $value, 'id_text', 'textcryptomus');
        $cmClearState();
        sendmessage($from_id, "✅ Cryptomus button text saved.", $CryptomusManage, 'HTML');
        return;
    }

    if ($currentStep === 'cm_help') {
        if ($text && trim((string) $text) === '2') {
            $data = '2';
        } elseif ($text) {
            $data = json_encode(['type' => 'text', 'text' => text_from_telegram_update($update)], JSON_UNESCAPED_UNICODE);
        } elseif ($photo) {
            $data = json_encode(['type' => 'photo', 'text' => (string) $caption, 'photoid' => $photoid], JSON_UNESCAPED_UNICODE);
        } elseif ($video) {
            $data = json_encode(['type' => 'video', 'text' => (string) $caption, 'videoid' => $videoid], JSON_UNESCAPED_UNICODE);
        } else {
            sendmessage($from_id, "❌ Only text, photo, video, or the number 2 is allowed.", $backadmin, 'HTML');
            return;
        }
        update('PaySetting', 'ValuePay', $data, 'NamePay', 'helpcryptomus');
        $cmClearState();
        sendmessage($from_id, "✅ Cryptomus tutorial saved.", $CryptomusManage, 'HTML');
        return;
    }
} elseif (
    $datain == "wd_menu"
    || preg_match('/^wd_tab_(settings|pending|history)(?:_(\d+))?$/', (string) $datain, $wdTabMatch)
    || preg_match('/^wd_ok_(\d+)$/', (string) $datain, $wdOkMatch)
    || preg_match('/^wd_no_(\d+)$/', (string) $datain, $wdNoMatch)
    || in_array($datain, ['wd_set_min', 'wd_set_prompt', 'wd_set_success', 'wd_none'], true)
    || in_array($user['step'] ?? '', ['wd_admin_min', 'wd_admin_prompt', 'wd_admin_success', 'wd_admin_receipt', 'wd_admin_reject'], true)
) {
    $wdRule = $adminrulecheck['rule'] ?? '';
    $wdCanManage = ($wdRule === 'administrator' || $wdRule === 'Seller');
    if (!$wdCanManage) {
        if ($callback_query_id) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'You do not have access',
                'show_alert' => true,
            ]);
        }
        return;
    }
    withdraw_ensure_schema($pdo);
    $wdIsAdmin = ($wdRule === 'administrator');
    $wdTabMatch = [];
    $wdOkMatch = [];
    $wdNoMatch = [];
    if (is_string($datain) && $datain !== '') {
        preg_match('/^wd_tab_(settings|pending|history)(?:_(\d+))?$/', $datain, $wdTabMatch);
        preg_match('/^wd_ok_(\d+)$/', $datain, $wdOkMatch);
        preg_match('/^wd_no_(\d+)$/', $datain, $wdNoMatch);
    }

    if ($datain == "wd_none") {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
        return;
    }

    if ($datain == "wd_menu" || isset($wdTabMatch[1])) {
        step('home', $from_id);
        $tab = $wdTabMatch[1] ?? 'pending';
        $page = (int) ($wdTabMatch[2] ?? 1);
        if ($tab === 'settings') {
            if (!$wdIsAdmin) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'Only the main admin can change settings',
                    'show_alert' => true,
                ]);
                return;
            }
            reply_or_edit($from_id, $message_id, withdraw_admin_settings_text(), withdraw_admin_settings_keyboard(), 'HTML');
            return;
        }
        if ($tab === 'history') {
            $view = withdraw_admin_history_view($page);
            reply_or_edit($from_id, $message_id, $view['text'], $view['keyboard'], 'HTML');
            return;
        }
        $view = withdraw_admin_pending_view($page);
        reply_or_edit($from_id, $message_id, $view['text'], $view['keyboard'], 'HTML');
        return;
    }

    if ($datain == "wd_set_min") {
        if (!$wdIsAdmin) {
            return;
        }
        sendmessage($from_id, "⬇️ Send the minimum withdrawal amount in USD (0 means any positive amount).", $backadmin, 'HTML');
        step('wd_admin_min', $from_id);
        return;
    }
    if ($user['step'] == "wd_admin_min") {
        $min = withdraw_parse_int((string) $text);
        if ($min === null) {
            sendmessage($from_id, "❌ Invalid value. Send a number.", $backadmin, 'HTML');
            return;
        }
        withdraw_set_min($min, $pdo);
        sendmessage($from_id, "✅ Minimum withdrawal set to " . number_format($min) . " USD.", $backadmin, 'HTML');
        step('home', $from_id);
        sendmessage($from_id, withdraw_admin_settings_text(), withdraw_admin_settings_keyboard(), 'HTML');
        return;
    }

    if ($datain == "wd_set_prompt") {
        if (!$wdIsAdmin) {
            return;
        }
        prompt_textbot_edit($from_id, WITHDRAW_TEXT_PROMPT, 'wd_admin_prompt', $backadmin);
        return;
    }
    if ($user['step'] == "wd_admin_prompt") {
        if (!save_textbot_from_update(WITHDRAW_TEXT_PROMPT, $update)) {
            sendmessage($from_id, "❌ Text is empty. Send it again.", $backadmin, 'HTML');
            return;
        }
        global $datatextbot;
        if (is_array($datatextbot)) {
            $datatextbot[WITHDRAW_TEXT_PROMPT] = text_from_telegram_update($update);
        }
        sendmessage($from_id, "✅ Settlement button text saved.", $backadmin, 'HTML');
        step('home', $from_id);
        sendmessage($from_id, withdraw_admin_settings_text(), withdraw_admin_settings_keyboard(), 'HTML');
        return;
    }

    if ($datain == "wd_set_success") {
        if (!$wdIsAdmin) {
            return;
        }
        prompt_textbot_edit($from_id, WITHDRAW_TEXT_SUCCESS, 'wd_admin_success', $backadmin);
        return;
    }
    if ($user['step'] == "wd_admin_success") {
        if (!save_textbot_from_update(WITHDRAW_TEXT_SUCCESS, $update)) {
            sendmessage($from_id, "❌ Text is empty. Send it again.", $backadmin, 'HTML');
            return;
        }
        global $datatextbot;
        if (is_array($datatextbot)) {
            $datatextbot[WITHDRAW_TEXT_SUCCESS] = text_from_telegram_update($update);
        }
        sendmessage($from_id, "✅ Success message text saved.", $backadmin, 'HTML');
        step('home', $from_id);
        sendmessage($from_id, withdraw_admin_settings_text(), withdraw_admin_settings_keyboard(), 'HTML');
        return;
    }

    if (isset($wdOkMatch[1])) {
        $wdId = (int) $wdOkMatch[1];
        $wdRow = withdraw_get($wdId, $pdo);
        if (!$wdRow || ($wdRow['status'] ?? '') !== WITHDRAW_STATUS_PENDING) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'This request was already reviewed',
                'show_alert' => true,
            ]);
            return;
        }
        update("user", "Processing_value", (string) $wdId, "id", $from_id);
        sendmessage($from_id, "🖼 Send the payment receipt photo for request #$wdId.", $backadmin, 'HTML');
        step('wd_admin_receipt', $from_id);
        return;
    }

    if ($user['step'] == "wd_admin_receipt") {
        $wdId = (int) $user['Processing_value'];
        if (!$photo || isset($update['message']['media_group_id'])) {
            sendmessage($from_id, "❌ Send only one receipt image.", $backadmin, 'HTML');
            return;
        }
        $saved = withdraw_save_receipt_from_telegram($wdId, $photoid);
        $result = withdraw_approve($wdId, (string) $from_id, $saved, $pdo);
        step('home', $from_id);
        update("user", "Processing_value", "0", "id", $from_id);
        sendmessage($from_id, !empty($result['ok']) ? "✅ " . $result['msg'] : "❌ " . ($result['msg'] ?? 'Error'), $keyboardadmin, 'HTML');
        return;
    }

    if (isset($wdNoMatch[1])) {
        $wdId = (int) $wdNoMatch[1];
        $wdRow = withdraw_get($wdId, $pdo);
        if (!$wdRow || ($wdRow['status'] ?? '') !== WITHDRAW_STATUS_PENDING) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => 'This request was already reviewed',
                'show_alert' => true,
            ]);
            return;
        }
        update("user", "Processing_value", (string) $wdId, "id", $from_id);
        sendmessage($from_id, "✍️ Send the rejection reason for request #$wdId. This text will be sent to the user.", $backadmin, 'HTML');
        step('wd_admin_reject', $from_id);
        return;
    }

    if ($user['step'] == "wd_admin_reject") {
        $wdId = (int) $user['Processing_value'];
        $result = withdraw_reject($wdId, (string) $from_id, (string) $text, $pdo);
        step('home', $from_id);
        update("user", "Processing_value", "0", "id", $from_id);
        sendmessage($from_id, !empty($result['ok']) ? "✅ " . $result['msg'] : "❌ " . ($result['msg'] ?? 'Error'), $keyboardadmin, 'HTML');
        return;
    }
} elseif ($text == "🎁 Renewal cashback" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the percent to credit as a gift after renewal.
⚠️ Send 0 to disable it", $backadmin, 'HTML');
    step('getpricecashback', $from_id);
} elseif ($user['step'] == "getpricecashback") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "price_cashback", $text);
    sendmessage($from_id, "📌 Choose the user type
f
n
n2", $backadmin, 'HTML');
    step('getagent', $from_id);
} elseif ($user['step'] == "getagent") {
    if (!in_array($text, ['f', 'n', 'n2'])) {
        sendmessage($from_id, "❌ Invalid user group", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if ($text == "f") {
        update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
    } else {
        $shop_cashbackagent = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
        $shop_cashbackagent[$text] = $userdata['price_cashback'];
        update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
    }
    sendmessage($from_id, "✅ Amount set successfully", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^editpayment-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type === 'cryptomus' && ($adminrulecheck['rule'] ?? '') !== 'administrator') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'Only the main admin has access',
            'show_alert' => true,
        ]);
        return;
    }
    if ($type == "Cartstatus") {
        if ($value == "oncard") {
            $valuenew = "offcard";
        } else {
            $valuenew = "oncard";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "Cartstatus");
    } elseif ($type == "plisio") {
        if ($value == "onnowpayment") {
            $valuenew = "offnowpayment";
        } else {
            $valuenew = "onnowpayment";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "nowpaymentstatus");
    } elseif ($type == "arzireyali1") {
        if ($value == "onSwapinoBot") {
            $valuenew = "offSwapinoBot";
        } else {
            $valuenew = "onSwapinoBot";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusSwapWallet");
    } elseif ($type == "arzireyali2") {
        if ($value == "onternado") {
            $valuenew = "offternado";
        } else {
            $valuenew = "onternado";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustarnado");
    } elseif ($type == "aqayepardakht") {
        if ($value == "onaqayepardakht") {
            $valuenew = "offaqayepardakht";
        } else {
            $valuenew = "onaqayepardakht";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusaqayepardakht");
    } elseif ($type == "zarinpal") {
        if ($value == "onzarinpal") {
            $valuenew = "offzarinpal";
        } else {
            $valuenew = "onzarinpal";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpalstatus");
    } elseif ($type == "tetraminator") {
        if ($value == "ontetraminator") {
            $valuenew = "offtetraminator";
        } else {
            $valuenew = "ontetraminator";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustetraminator");
    } elseif ($type == "affilnecurrency") {
        if ($value == "ondigi") {
            $valuenew = "offdigi";
        } else {
            $valuenew = "ondigi";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "digistatus");
    } elseif ($type == "oniranpay3") {
        if ($value == "oniranpay3") {
            $valuenew = "offiranpay3";
        } else {
            $valuenew = "oniranpay3";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusiranpay3");
    } elseif ($type == "startelegram") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusstar");
    } elseif ($type == "nowpayment") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusnowpayment");
    } elseif ($type == "cryptomus") {
        $valuenew = $value === "oncryptomus" ? "offcryptomus" : "oncryptomus";
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statuscryptomus");
    }
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $tetraminator_status = getPaySettingValue('statustetraminator', 'offtetraminator');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $payment_status_cryptomus = getPaySettingValue('statuscryptomus', 'offcryptomus');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $tetraminatorstatus = [
        'ontetraminator' => $textbotlang['Admin']['Status']['statuson'],
        'offtetraminator' => $textbotlang['Admin']['Status']['statusoff']
    ][$tetraminator_status];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $cryptomus_status = [
        'oncryptomus' => $textbotlang['Admin']['Status']['statuson'],
        'offcryptomus' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_cryptomus] ?? $textbotlang['Admin']['Status']['statusoff'];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "💸 Wallet withdrawal", 'callback_data' => "wd_menu"],
            ],
            [
                ['text' => "Action", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 Card to card", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "cm_set"],
                ['text' => "🧾 Operations", 'callback_data' => "cm_ops"],
                ['text' => $cryptomus_status, 'callback_data' => "editpayment-cryptomus-$payment_status_cryptomus"],
                ['text' => "💠 Cryptomus", 'callback_data' => "cm_ops"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => "📌 Currency-rial 1", 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => "📌 Currency-rial 2", 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => "📌Currency-rial 3", 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 Aghaye Pardakht", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 Zarinpal", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "tetraminatorsetting"],
                ['text' => $tetraminatorstatus, 'callback_data' => "editpayment-tetraminator-$tetraminator_status"],
                ['text' => "💸 Tetraminator", 'callback_data' => "tetraminator"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵Offline crypto", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ Settings", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ Maximum balance top-up", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ Minimum balance top-up", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "Wallet address", 'callback_data' => "walletaddress"],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 You can manage gateways from the list below.

⚠️ The Picha team does not guarantee any gateway; use is entirely at your own risk", $Bot_Status);
} elseif ($text == "💰 Card-to-card cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashcart", $from_id);
} elseif ($user['step'] == "getcashcart") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackcart");
} elseif ($text == "💰 Aghaye Pardakht cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashahaypar", $from_id);
} elseif ($user['step'] == "getcashahaypar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackaqaypardokht");
} elseif ($text == "💰 IRR gateway 2 cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashiranpay2", $from_id);
} elseif ($user['step'] == "getcashiranpay2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay2");
} elseif ($text == "💰 IRR gateway 3 cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashiranpay4", $from_id);
} elseif ($user['step'] == "getcashiranpay4") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay3");
} elseif ($text == "💰 IRR cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashiranpay1", $from_id);
} elseif ($user['step'] == "getcashiranpay1") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay1");
} elseif ($text == "💰 Plisio cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashplisio", $from_id);
} elseif ($user['step'] == "getcashplisio") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackplisio");
} elseif ($text == "💰 NowPayments cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashnowpayment", $from_id);
} elseif ($user['step'] == "getcashnowpayment") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "cashbacknowpayment");
} elseif ($text == "💰 Zarinpal cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashzarinpal", $from_id);
} elseif ($user['step'] == "getcashzarinpal") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $keyboardzarinpal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpal");
} elseif ($text == "💰 Tetraminator cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("getcashtetraminator", $from_id);
} elseif ($user['step'] == "getcashtetraminator") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $keyboardtetraminator, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbacktetraminator");
} elseif ($text == "➕ Add config") {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_product['keyboard'][] = [
        ['text' => "🏠 Back to management menu"],
    ];
    $json_list_product_list_admin = json_encode($list_product);
    sendmessage($from_id, "📌 Send the product name. To set it for the test account, send the word test.", $json_list_product_list_admin, 'HTML');
    step('getnameproduct', $from_id);
    savedata("clear", "namepanel", $user['Processing_value']);
} elseif ($user['step'] == "getnameproduct") {
    $product_check = select("product", "*", "name_product", $text, "select");
    if ($product_check == false && $text != "test") {
        sendmessage($from_id, "No product with that name was found. Send the exact product name, or send the word test to set the test config.", $backadmin, 'HTML');
        return;
    }
    if ($text == "test") {
        savedata("save", "name_product", "usertest");
    } else {
        savedata("save", "name_product", $product_check['code_product']);
    }
    sendmessage($from_id, "📌 Send your configs like the example below.

# Config name (one line, starting with #)
Config (can span multiple lines 

# Config name (one line, starting with #)

trojan://xyz", $backadmin, 'HTML');
    step("getconfigtext", $from_id);
} elseif ($user['step'] == "getconfigtext") {
    $userdata = json_decode($user['Processing_value'], true);
    step('home', $from_id);
    $config = parseConfigs($text);
    sendmessage($from_id, "✅ Number of saved configs: " . count($config), $optionManualsale, 'HTML');
    $panel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    if ($panel == false) {
        sendmessage($from_id, "❌ An error occurred while saving the config. Please try again.", $backadmin, 'HTML');
        return;
    }
    $status = "active";
    foreach ($config as $content_config) {

        $stmt = $pdo->prepare("INSERT IGNORE INTO manualsell (codepanel,namerecord,contentrecord,status,codeproduct) VALUES (:codepanel,:namerecord,:contentrecord,:status,:codeproduct)");
        $stmt->bindParam(':codepanel', $panel['code_panel']);
        $stmt->bindParam(':namerecord', $content_config['name']);
        $stmt->bindParam(':contentrecord', $content_config['config']);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':codeproduct', $userdata['name_product']);
        $stmt->execute();
    }
    update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
} elseif ($text == "❌ Remove config") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 Back to management menu"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    sendmessage($from_id, "📌 Send the name of the config you want to delete ", $json_list_manualconfig_list, 'HTML');
    step("getnameremove", $from_id);
} elseif ($user['step'] == "getnameremove") {
    sendmessage($from_id, "✅ Config deleted successfully.", $optionManualsale, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM manualsell WHERE namerecord = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    step("home", $from_id);
} elseif ($text == "🌍 Location-change price" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the location-change price from other panels to this panel", $backadmin, 'HTML');
    step('setpricechangelocation', $from_id);
} elseif ($user['step'] == "setpricechangelocation") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌Location-change price updated successfully");
    update("marzban_panel", "priceChangeloc", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "➕ Extra volume price" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the extra volume price for this panel.", $backadmin, 'HTML');
    step('GetPriceExtra', $from_id);
} elseif ($user['step'] == "GetPriceExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ If you want the price set for all user groups, send <code>all</code>", $backuser, 'HTML');
    step('gettypeextra', $from_id);
} elseif ($user['step'] == "gettypeextra") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextravolume'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextravolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⚙️ Custom volume price" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the custom extra volume price for this panel.", $backadmin, 'HTML');
    step('GetPricecustomvo', $from_id);
} elseif ($user['step'] == "GetPricecustomvo") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ If you want the price set for all user groups, send <code>all</code>", $backuser, 'HTML');
    step('gettypeextracustom', $from_id);
} elseif ($user['step'] == "gettypeextracustom") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomvolume'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ Extra time price" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the extra time price for this panel.", $backadmin, 'HTML');
    step('GetPricetimeextra', $from_id);
} elseif ($user['step'] == "GetPricetimeextra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ If you want the price set for all user groups, send <code>all</code>", $backuser, 'HTML');
    step('gettypeextratime', $from_id);
} elseif ($user['step'] == "gettypeextratime") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextratime'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextratime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ Custom time price" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Set custom-service month options and price coefficients from the web panel:\n<code>/panel/panel.php</code> → «Custom service» tab\n\nFinal price = volume (GB) × price per GB × month coefficient (each month = 30 days).", $backadmin, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "GetPriceExtratime") {
    sendmessage($from_id, "📌 Daily custom-service price setting has been removed. Set month options and coefficients from the web panel.", $backadmin, 'HTML');
    step('home', $from_id);
} elseif ($user['step'] == "gettypeextratimecustom") {
    sendmessage($from_id, "📌 Daily custom-service price setting has been removed. Set month options and coefficients from the web panel.", $backadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "🔒 Show card-to-card after first payment" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 Enabling this feature turns on card-to-card for the user after their first payment", $keyboardverify, 'HTML');
} elseif ($datain == "onpayverify") {
    update("PaySetting", "ValuePay", "offpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "Turned off", $keyboardverify);
} elseif ($datain == "offpayverify") {
    update("PaySetting", "ValuePay", "onpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "Turned on", $keyboardverify);
} elseif ($text == "✏️ Edit config") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 Back to management menu"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    sendmessage($from_id, "📌 Send the name of the config you want to edit ", $json_list_manualconfig_list, 'HTML');
    step("getnameedit", $from_id);
} elseif ($user['step'] == "getnameedit") {
    sendmessage($from_id, "Choose one of the options below ", $configedit, 'HTML');
    step("home", $from_id);
    update("user", "Processing_value_one", $text, "id", $from_id);
} elseif ($text == "Config details") {
    sendmessage($from_id, "Send the new config content", $backadmin, 'HTML');
    step("getcontentedit", $from_id);
} elseif ($user['step'] == "getcontentedit") {
    sendmessage($from_id, "✅ Saved.", $optionManualsale, 'HTML');
    update("manualsell", "contentrecord", $text, "namerecord", $user['Processing_value_one']);
} elseif ($text == "⬆️ Bulk price increase") {
    sendmessage($from_id, "📌 Which panel's products do you want to increase prices for?
If you defined the product as /all, you must send /all for this category to change", $json_list_marzban_panel, 'HTML');
    step("getaddpricepeoductloc", $from_id);
} elseif ($user['step'] == "getaddpricepeoductloc") {
    sendmessage($from_id, "📌 Which user group should the price apply to 
f,n.n2", $backadmin, 'HTML');
    savedata("clear", "namepanel", $text);
    step("getagentaddpriceproduct", $from_id);
} elseif ($user['step'] == "getagentaddpriceproduct") {
    $keyboard_type_price = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Percent", 'callback_data' => 'typeaddprice_percent'],
                ['text' => "Fixed", 'callback_data' => 'typeaddprice_static'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 Should the amount be added as a percent or a fixed amount", $keyboard_type_price, 'HTML');
    savedata("save", "agent", $text);
    step("home", $from_id);
} elseif (preg_match('/^typeaddprice_(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    deletemessage($from_id, $message_id);
    if ($type == "static") {
        sendmessage($from_id, "📌 Send the amount to apply", $backadmin, 'HTML');
    } else {
        sendmessage($from_id, "📌 Send the percent to apply", $backadmin, 'HTML');
    }
    savedata("save", "type_price", $type);
    step("getaddpricepeoduct", $from_id);
} elseif ($user['step'] == "getaddpricepeoduct") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        sendmessage($from_id, "❌ No product found to change the price", $shopkeyboard, 'HTML');
        step("home", $from_id);
        return;
    }
    if ($userdata['type_price'] == "static") {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + :price WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + (price_product * :price / 100)  WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    }
    $stmt->execute();
    sendmessage($from_id, "✅ Amount applied to all products", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ Bulk price decrease") {
    sendmessage($from_id, "📌 Which panel's products do you want to decrease prices for?
If you defined the product as /all, you must send /all for this category to change", $json_list_marzban_panel, 'HTML');
    step("getlowpricepeoductloc", $from_id);
} elseif ($user['step'] == "getlowpricepeoductloc") {
    sendmessage($from_id, "📌 Which user group should the price apply to 
f,n.n2", $backadmin, 'HTML');
    savedata("clear", "namepanel", $text);
    step("getkampricepeoductloc", $from_id);
} elseif ($user['step'] == "getkampricepeoductloc") {
    sendmessage($from_id, "📌 Send the amount to apply", $backadmin, 'HTML');
    savedata("save", "agent", $text);
    step("getkampricepeoduct", $from_id);
} elseif ($user['step'] == "getkampricepeoduct") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        sendmessage($from_id, "❌ No product found to change the price", $shopkeyboard, 'HTML');
        return;
    }
    foreach ($product as $products) {
        $result = $products['price_product'] - intval($text);
        update("product", "price_product", round($result), "code_product", $products['code_product']);
    }
    sendmessage($from_id, "✅ Amount applied to all products", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ Card-to-card minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmaincart", $from_id);
} elseif ($user['step'] == "getmaincart") {
    sendmessage($from_id, "✅ Minimum deposit amount set.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecart");
} elseif ($text == "⬆️ Card-to-card maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaxcart", $from_id);
} elseif ($user['step'] == "getmaxcart") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecart");
} elseif ($text == "⬇️ Plisio minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainplisio", $from_id);
} elseif ($user['step'] == "getmainplisio") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceplisio");
} elseif ($text == "⬆️ Plisio maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaxplisio", $from_id);
} elseif ($user['step'] == "getmaxplisio") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceplisio");
} elseif ($text == "⬇️ Offline crypto minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmaindigitaltron", $from_id);
} elseif ($user['step'] == "getmaindigitaltron") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancedigitaltron");
} elseif ($text == "⬆️ Offline crypto maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaxdigitaltron", $from_id);
} elseif ($user['step'] == "getmaxdigitaltron") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancedigitaltron");
} elseif ($text == "⬇️ IRR minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainiranpay1", $from_id);
} elseif ($user['step'] == "getmainiranpay1") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay1");
} elseif ($text == "⬆️ IRR maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaaxiranpay1", $from_id);
} elseif ($user['step'] == "getmaaxiranpay1") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay1");
} elseif ($text == "⬇️ IRR gateway 2 minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainiranpay2", $from_id);
} elseif ($user['step'] == "getmainiranpay2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay2");
} elseif ($text == "⬆️ IRR gateway 2 maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaaxiranpay2", $from_id);
} elseif ($user['step'] == "getmaaxiranpay2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay2");
} elseif ($text == "⬇️ Aghaye Pardakht minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainaqayepardakht", $from_id);
} elseif ($user['step'] == "getmainaqayepardakht") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceaqayepardakht");
} elseif ($text == "⬆️ Aghaye Pardakht maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaaxaqayepardakht", $from_id);
} elseif ($user['step'] == "getmaaxaqayepardakht") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceaqayepardakht");
} elseif ($text == "⬇️ Zarinpal minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainaqzarinpal", $from_id);
} elseif ($user['step'] == "getmainaqzarinpal") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpal");
} elseif ($text == "⬆️ Zarinpal maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaaxzarinpal", $from_id);
} elseif ($user['step'] == "getmaaxzarinpal") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpal");
} elseif ($text == "⬇️ Tetraminator minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount (minimum 50000 USD)", $backadmin, 'HTML');
    step("getmaintetraminator", $from_id);
} elseif ($user['step'] == "getmaintetraminator") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) < 50000) {
        sendmessage($from_id, "❌ This gateway's minimum amount cannot be less than 50000 USD", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $keyboardtetraminator, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancetetraminator");
} elseif ($text == "⬆️ Tetraminator maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("getmaaxtetraminator", $from_id);
} elseif ($user['step'] == "getmaaxtetraminator") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $keyboardtetraminator, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancetetraminator");
} elseif ($datain == "walletaddress") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
    $texttronseller = "💳 Send your TRON TRC20 wallet address
        
        Your current wallet: {$PaySetting['ValuePay']}";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('walletaddresssiranpay', $from_id);
} elseif ($user['step'] == "walletaddresssiranpay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "walletaddress");
    step('home', $from_id);
} elseif ($text == "IRR gateway API" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiiranpay", "select")['ValuePay'];
    $texttronseller = "📌 Send your API code.
        
        Your current merchant: $PaySetting";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiiranpay', $from_id);
} elseif ($user['step'] == "apiiranpay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $iranpaykeyboard, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiiranpay");
    step('home', $from_id);
} elseif ($text == "⬇️ IRR gateway 3 minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("minbalanceiranpay", $from_id);
} elseif ($user['step'] == "minbalanceiranpay") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay");
} elseif ($text == "⬆️ IRR gateway 3 maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("maxbalanceiranpay", $from_id);
} elseif ($user['step'] == "maxbalanceiranpay") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay");
} elseif ($text == "📍 Custom volume minimum" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the minimum volume the user can buy for this panel.", $backadmin, 'HTML');
    step('GetmaineExtra', $from_id);
} elseif ($user['step'] == "GetmaineExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "mainvalume", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramain', $from_id);
} elseif ($user['step'] == "gettypeextramain") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['mainvolume'], true);
    $eextraprice[$text] = $userdata['mainvalume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "mainvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 Custom volume maximum" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the maximum volume the user can buy for this panel.", $backadmin, 'HTML');
    step('GetmaxeExtra', $from_id);
} elseif ($user['step'] == "GetmaxeExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maxvolume", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramax', $from_id);
} elseif ($user['step'] == "gettypeextramax") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxvolume'], true);
    $eextraprice[$text] = $userdata['maxvolume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 Custom time minimum" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the minimum custom duration the user can buy for this panel.", $backadmin, 'HTML');
    step('Getmaintime', $from_id);
} elseif ($user['step'] == "Getmaintime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maintime", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramaintime', $from_id);
} elseif ($user['step'] == "gettypeextramaintime") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maintime'], true);
    $eextraprice[$text] = $userdata['maintime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maintime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 Custom time maximum" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Send the maximum custom duration the user can buy for this panel.", $backadmin, 'HTML');
    step('Getmaxtime', $from_id);
} elseif ($user['step'] == "Getmaxtime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maxtime", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramaxtime', $from_id);
} elseif ($user['step'] == "gettypeextramaxtime") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxtime'], true);
    $eextraprice[$text] = $userdata['maxtime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔼 Add department") {
    sendmessage($from_id, "📌 Send the numeric ID of the admin who should receive the messages", $backadmin, 'HTML');
    step("getidadmindep", $from_id);
} elseif ($user['step'] == "getidadmindep") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata('clear', 'idadmin', $text);
    sendmessage($from_id, "📌 Send the department name", $backadmin, 'HTML');
    step("getdeparteman", $from_id);
} elseif ($user['step'] == "getdeparteman") {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT IGNORE INTO departman (idsupport,name_departman) VALUES (:idsupport,:name_departman)");
    $stmt->bindParam(':idsupport', $userdata['idadmin']);
    $stmt->bindParam(':name_departman', $text);
    $stmt->execute();
    step("home", $from_id);
    sendmessage($from_id, "📌 Department added successfully.", $supportcenter, 'HTML');
} elseif ($text == "🔽 Remove department") {
    $countdeparteman = select("departman", "*", null, null, "count");
    if ($countdeparteman == 0) {
        sendmessage($from_id, "❌ There is no department to delete.", keyboard_departman_admin(), 'HTML');
        return;
    }
    sendmessage($from_id, "📌 Send the department type to delete.", keyboard_departman_admin(), 'HTML');
    step("getremovedep", $from_id);
} elseif ($user['step'] == "getremovedep") {
    $candidates = department_name_match_values($text);
    if ($candidates === []) {
        $candidates = [trim((string) $text)];
    }
    $placeholders = implode(',', array_fill(0, count($candidates), '?'));
    $stmt = $pdo->prepare("DELETE FROM departman WHERE name_departman IN ($placeholders)");
    $stmt->execute($candidates);
    sendmessage($from_id, "📌 The selected section was deleted.", $supportcenter, 'HTML');
    step("home", $from_id);
} elseif ($text == "⚙️ Service settings" && $adminrulecheck['rule'] == "administrator") {
    $textsetservice = "📌 To set the service, create a config in your panel, enable the services you want, and send the config username";
    sendmessage($from_id, $textsetservice, $backadmin, 'HTML');
    step('getservceid', $from_id);
} elseif ($user['step'] == "getservceid") {
    $userdata = json_decode(getuserm($text, $user['Processing_value'])['body'], true);
    if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
        sendmessage($from_id, "User does not exist in the panel", null, 'HTML');
        return;
    }
    update("marzban_panel", "proxies", json_encode($userdata['service_ids']), "name_panel", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($from_id, "✅ Info set successfully", $optionmarzneshin, 'HTML');
} elseif ($text == "👤 Set support ID" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "📌 Send your username without @ for support\n\n{$setting['id_support']}";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('idsupportset', $from_id);
} elseif ($user['step'] == "idsupportset") {
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $supportcenter, 'HTML');
    update("setting", "id_support", $text, null, null);
    step('home', $from_id);
} elseif ($text == "📚 Set card-to-card guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("gethelpcart", $from_id);
} elseif ($user['step'] == "gethelpcart") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set NowPayments guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("gethelpnowpayment", $from_id);
} elseif ($user['step'] == "gethelpnowpayment") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpnowpayment");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "📚 Set Perfect Money guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("gethelpperfect", $from_id);
} elseif ($user['step'] == "gethelpperfect") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpperfectmony");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set Plisio guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("gethelpplisio", $from_id);
} elseif ($user['step'] == "gethelpplisio") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpplisio");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set IRR gateway 1 guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("gethelpiranpay1", $from_id);
} elseif ($user['step'] == "gethelpiranpay1") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay1");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set IRR gateway 2 guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("helpiranpay2", $from_id);
} elseif ($user['step'] == "helpiranpay2") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay2");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set IRR gateway 3 guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("helpiranpay3", $from_id);
} elseif ($user['step'] == "helpiranpay3") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay3");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set Aghaye Pardakht guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("helpaqayepardakht", $from_id);
} elseif ($user['step'] == "helpaqayepardakht") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpaqayepardakht");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set Zarinpal guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("helpzarinpal", $from_id);
} elseif ($user['step'] == "helpzarinpal") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpzarinpal");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "📚 Set offline crypto guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("helpofflinearze", $from_id);
} elseif ($user['step'] == "helpofflinearze") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpofflinearze");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $CartManage, 'HTML');
} elseif ($text == "💰 Agency membership fee") {
    sendmessage($from_id, "📌 Send the agency membership request price.", $backadmin, 'HTML');
    step("getpricereqagent", $from_id);
} elseif ($user['step'] == "getpricereqagent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Changes saved successfully", $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "agentreqprice", $text, null, null);
} elseif ($text == "🤖 Confirm receipts without review" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    if ($paymentverify == "onautoconfirm") {
        sendmessage($from_id, "❌ First turn off auto-confirm.", null, 'HTML');
        return;
    }
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 Enabling this feature makes the bot auto-approve all card-to-card transactions while you are offline. After you come online, review the receipts and cancel any fake ones", $keyboardverify, 'HTML');
} elseif ($datain == "onauto") {
    update("PaySetting", "ValuePay", "offauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "Turned off", $keyboardverify);
} elseif ($datain == "offauto") {
    update("PaySetting", "ValuePay", "onauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "Turned on", $keyboardverify);
} elseif (preg_match('/transferaccount_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, "Send the numeric ID of the user you want all data transferred to
    Note: if the destination user has a balance, it will be removed", $backadmin, 'HTML');
    step("getidfortransfers", $from_id);
} elseif ($user['step'] == "getidfortransfers") {
    if (!rowExists('user', 'id', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    if ($text == $user['Processing_value']) {
        sendmessage($from_id, "❌ You cannot transfer data to the current user", $keyboardadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "Data transferred to the new account successfully", $keyboardadmin, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id_user");
    $stmt->bindParam(':id_user', $text, PDO::PARAM_STR);
    $stmt->execute();
    update("user", "id", $text, "id", $user['Processing_value']);
    update("Payment_report", "id_user", $text, "id_user", $user['Processing_value']);
    update("invoice", "id_user", $text, "id_user", $user['Processing_value']);
    update("support_message", "iduser", $text, "iduser", $user['Processing_value']);
    update("support_conversation", "iduser", $text, "iduser", $user['Processing_value']);
    update("service_other", "id_user", $text, "id_user", $user['Processing_value']);
    update("Giftcodeconsumed", "id_user", $text, "id_user", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🖼 QR code background") {
    sendmessage($from_id, "Send your image for the background", $backadmin, 'HTML');
    step("getimagebackgroundqr", $from_id);
} elseif ($user['step'] == "getimagebackgroundqr") {
    if (!$photo) {
        sendmessage($from_id, "Invalid image", $backadmin, 'HTML');
        return;
    }
    $response = getFileddire($photoid);
    if ($response['ok']) {
        $filePath = $response['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot$APIKEY/$filePath";
        $fileContent = file_get_contents($fileUrl);
        file_put_contents("custom.jpg", $fileContent);
        file_put_contents("images.jpg", $fileContent);
        sendmessage($from_id, "🖼 Background set successfully", $setting_panel, 'HTML');
        step("home", $from_id);
    }
} elseif ($text == "⚙️ Protocol and inbound settings" || $text == "🎛 Set group name" || $text == "⚙️ Node settings") {
    if ($text == "🎛 Set group name") {
        $textsetprotocol = "📌 Send the default group name you want accounts created from.";
    } elseif ($text == "⚙️ Node settings") {
        $textsetprotocol = "📌 To set the node, create a user in your panel, enable the nodes you want, and send that user's username";
    } else {
        $panelForHint = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        if (($panelForHint['type'] ?? '') === 'marzban' && ($panelForHint['version_panel'] ?? '0') === '1') {
            $textsetprotocol = "📌 PasarGuard panel — send one of these two:\n"
                . "• Group ID from PasarGuard → Groups (e.g. 1 or 1,2)\n"
                . "• Or a sample username that has Groups in the panel";
        } else {
            $textsetprotocol = "📌 To set inbound and protocol, create a config in your panel, enable the protocols and inbounds you want, and send the config username";
        }
    }
    sendmessage($from_id, $textsetprotocol, $backadmin, 'HTML');
    step("setinboundandprotocol", $from_id);
} elseif ($user['step'] == "setinboundandprotocol") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['type'] == "marzban") {
        if ($panel['version_panel'] == "1") {
            if (preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $text)) {
                $groupIds = array_map('intval', preg_split('/\s*,\s*/', trim($text)));
                update("marzban_panel", "inbounds", json_encode($groupIds), "name_panel", $user['Processing_value']);
                sendmessage($from_id, "✅ PasarGuard groups set: " . implode(', ', $groupIds), $optionMarzban, 'HTML');
                step("home", $from_id);
                return;
            }
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                sendmessage($from_id, $DataUserOut['error'], null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                sendmessage($from_id, "❌  An error occurred. Error code:  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings'])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            if (empty($DataUserOut['group_ids']) || !is_array($DataUserOut['group_ids'])) {
                sendmessage($from_id, "❌ This user has no groups in PasarGuard. Assign Groups in the panel, or send the group ID directly (e.g. 1).", null, 'HTML');
                return;
            }
            $DataUserOut['proxy_settings'] = marzban_sanitize_proxy_settings_for_storage($DataUserOut['proxy_settings']);
            update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], JSON_FORCE_OBJECT), "name_panel", $user['Processing_value']);
        } else {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                sendmessage($from_id, $DataUserOut['error'], null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                sendmessage($from_id, "❌  An error occurred. Error code:  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxies'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } else {
                    unset($DataUserOut['proxies'][$key]['id']);
                }
                if (count($DataUserOut['proxies'][$key]) == 0) {
                    $DataUserOut['proxies'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['inbounds']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxies'], true), "name_panel", $user['Processing_value']);
        }
    } elseif ($panel['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $panel['name_panel']); {
            if (count($data) == 0) {
                sendmessage($from_id, "❌ User does not exist in the panel.", $options_ui, 'HTML');
                return;
            }
            $servies = [];
            foreach ($data['inbounds'] as $service) {
                $servies[] = $service;
            }
            update("marzban_panel", "proxies", json_encode($servies, true), "name_panel", $user['Processing_value']);
        }
    } elseif ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        update("marzban_panel", "proxies", $text, "name_panel", $user['Processing_value']);
    }
    if ($panel['type'] == "ibsng") {
        sendmessage($from_id, "✅ Group name set successfully.", $optionibsng, 'HTML');
    } elseif ($panel['type'] == "mikrotik") {
        sendmessage($from_id, "✅ Group name set successfully.", $option_mikrotik, 'HTML');
    } else {
        if ($panel['type'] == 'marzban' && ($panel['version_panel'] ?? '0') === '1') {
            sendmessage($from_id, "✅ PasarGuard groups and protocols set successfully.", $optionMarzban, 'HTML');
        } else {
            sendmessage($from_id, "✅ Your inbound and protocols were set successfully.", $optionMarzban, 'HTML');
        }
    }
    step("home", $from_id);
} elseif ($text == "🔋 Renewal status" && $adminrulecheck['rule'] == "administrator") {
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['activepanel'], $keyboardstatus, 'HTML');
} elseif ($datain == "on_extend") {
    update("marzban_panel", "status_extend", "off_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepanelStatusOff'], $keyboardstatus);
} elseif ($datain == "off_extend") {
    update("marzban_panel", "status_extend", "on_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepaneltatuson'], $keyboardstatus);
} elseif ((preg_match('/confirmchannel-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if (!is_array($userdata)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    if (($userdata['joinchannel'] ?? '') === "bypass") {
        sendmessage($from_id, "✍️ This user is already exempt from forced join", null, 'HTML');
        return;
    }
    update("user", "joinchannel", "bypass", "id", $iduser);
    sendmessage($from_id, "📌 The user can now use the bot without joining the channel", $keyboardadmin, 'HTML');
} elseif ((preg_match('/zerobalance-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    $prevBalance = (int) ($userdata['Balance'] ?? 0);
    update("user", "Balance", "0", "id", $iduser);
    if ($prevBalance > 0) {
        record_admin_balance_payment($pdo, $iduser, $prevBalance, 'low balance by admin');
    }
    sendmessage($from_id, "User balance of {$userdata['Balance']} was set to zero", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $idadmin = trim($dataget[1]);
    $mainAdminId = trim((string) $adminnumber);
    if ($idadmin === $mainAdminId) {
        sendmessage($from_id, "❌ The main admin cannot be deleted", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM admin WHERE TRIM(id_admin) = :id_admin");
    $stmt->bindParam(':id_admin', $idadmin, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        sendmessage($from_id, "⚠️ No admin with this ID was found.", null, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Admin deleted successfully", null, 'HTML');
}
// elseif (preg_match('/activeconfig-(\w+)/', $datain, $dataget)) {
//     $iduser = $dataget[1];
//     $checkexits = select("user", "*", "id", $iduser, "select");
//     if (intval($checkexits['checkstatus']) != 0) {
//         sendmessage($from_id, "❌ The bot is currently enabling or disabling accounts. Wait for the previous operation to finish, then send a new request", null, 'HTML');
//         return;
//     }
//     update("user", "checkstatus", "1", "id", $iduser);
//     sendmessage($from_id, "✅  The user's configs were queued for enabling. This may take more than 2 hours depending on the number of configs.", null, 'HTML');
// } elseif (preg_match('/disableconfig-(\w+)/', $datain, $dataget)) {
//     $iduser = $dataget[1];
//     $checkexits = select("user", "*", "id", $iduser, "select");
//     if (intval($checkexits['checkstatus']) != 0) {
//         sendmessage($from_id, "❌ The bot is currently enabling or disabling accounts. Wait for the previous operation to finish, then send a new request", null, 'HTML');
//         return;
//     }
//     update("user", "checkstatus", "2", "id", $iduser);
//     sendmessage($from_id, "✅  The user's configs were queued for disabling. This may take more than 2 hours depending on the number of configs.", null, 'HTML');
// }
elseif ($text == "🫣 Hide panel for a user" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send the user's numeric ID for this panel.", $backadmin, 'HTML');
    step('getuserhide', $from_id);
} elseif ($user['step'] == "getuserhide") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ Panel hidden for the user successfully");
    if ($typepanel['hide_user'] == null) {
        $hideuserid = [];
    } else {
        $hideuserid = json_decode($typepanel['hide_user'], true);
    }
    $hideuserid[] = $text;
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌ Remove user from hidden list" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send the user's numeric ID for this panel.", $backadmin, 'HTML');
    step('getuserhideforremove', $from_id);
} elseif ($user['step'] == "getuserhideforremove") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    step("home", $from_id);
    if ($typepanel['hide_user'] == null) {
        outtypepanel($typepanel['type'], "❌ There is no user in the hidden list");
        return;
    }
    $hideuserid = json_decode($typepanel['hide_user'], true);
    if (count($hideuserid) == 0) {
        outtypepanel($typepanel['type'], "❌  User is not in the list");
        return;
    }
    if (!in_array($text, $hideuserid)) {
        outtypepanel($typepanel['type'], "❌ User is not in the list.");
        return;
    }
    $key = array_search($text, $hideuserid);
    if ($key !== false) {
        unset($hideuserid[$key]);
        $hideuserid = array_values($hideuserid);
    }
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    outtypepanel($typepanel['type'], "✅  User removed from the list successfully.");
} elseif ($datain == "scoresetting") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $lottery, 'HTML');
} elseif ($text == "1️⃣ Set 1st-place prize") {
    sendmessage($from_id, "📌 Send the amount you want credited to the user's account.", $lottery, 'HTML');
    step("getonelotary", $from_id);
} elseif ($user['step'] == "getonelotary") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Prize amount set successfully", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['one'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "2️⃣ Set 2nd-place prize") {
    sendmessage($from_id, "📌 Send the amount you want credited to the user's account.", $lottery, 'HTML');
    step("getonelotary2", $from_id);
} elseif ($user['step'] == "getonelotary2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Prize amount set successfully", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['tow'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "3️⃣ Set 3rd-place prize") {
    sendmessage($from_id, "📌 Send the amount you want credited to the user's account.", $lottery, 'HTML');
    step("getonelotary3", $from_id);
} elseif ($user['step'] == "getonelotary3") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Prize amount set successfully", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['theree'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($datain == "gradonhshans") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $wheelkeyboard, 'HTML');
} elseif ($text == "🎲 User win amount") {
    sendmessage($from_id, "📌 Send the amount you want credited to the user's account.", $backadmin, 'HTML');
    step("getpricewheel", $from_id);
} elseif ($user['step'] == "getpricewheel") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Prize amount set successfully", $wheelkeyboard, 'HTML');
    step("home", $from_id);
    update("setting", "wheelـluck_price", $text, null, null);
} elseif ($text == "💵 Unverified receipts") {
    $sql = "SELECT * FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $list_payment = $stmt->fetchAll();
    $list_payment_count = $stmt->rowCount();
    if ($list_payment_count == 0) {
        sendmessage($from_id, "❌ You have no unconfirmed payments.", $list_payment, 'HTML');
        return;
    }
    $list_pay = ['inline_keyboard' => []];
    foreach ($list_payment as $payment) {
        $list_payment['inline_keyboard'][] = [
            ['text' => $payment['id_user'], 'callback_data' => "checkpay"]
        ];
        $list_payment['inline_keyboard'][] = [
            ['text' => "✅", 'callback_data' => "Confirm_pay_{$payment['id_order']}"],
            ['text' => "❌", 'callback_data' => "reject_pay_{$payment['id_order']}"],
            ['text' => "📝", 'callback_data' => "showinfopay_{$payment['id_order']}"],
            ['text' => "🗑", 'callback_data' => "removeresid_{$payment['id_order']}"],
        ];
        $list_payment['inline_keyboard'][] = [
            ['text' => "💸💸💸💸💸💸💸💸💸", 'callback_data' => "checkpay"]
        ];
    }
    $list_payment['inline_keyboard'][] = [
        ['text' => "❌ Delete all receipts", 'callback_data' => "removeresid"]
    ];
    $list_payment = json_encode($list_payment);
    sendmessage($from_id, "📌 Unconfirmed card-to-card payments 
Here you can view unconfirmed payments and approve or reject them.
❌ : reject payment 
✅ : approve payment
📝 payment details
🗑 : delete receipt without notifying the user", $list_payment, 'HTML');
} elseif ($datain == "removeresid") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "✅  All receipts deleted successfully ", $list_payment, 'HTML');
    $sql = "UPDATE Payment_report SET payment_Status = 'reject',dec_not_confirmed = 'remove_all' WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} elseif (preg_match('/showinfopay_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $paymentUser = select("Payment_report", "*", "id_order", $idorder, "select");
    if ($paymentUser == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "Transaction has been deleted",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $text_order = "🛒 Payment number:  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ User ID: <code>{$paymentUser['id_user']}</code>
💰 Amount paid: {$paymentUser['price']} USD
⚜️ Payment status: {$paymentUser['payment_Status']}
⭕️ Payment method: {$paymentUser['Payment_Method']} 
📆 Purchase date:  {$paymentUser['time']}";
    sendmessage($from_id, $text_order, null, 'HTML');
} elseif ($text == "🎛 Inbound settings") {
    sendmessage($from_id, "📌 If this is a Marzban or Marzneshin panel, copy a config username from the panel and send it; otherwise for Sanaei and Alireza panels send the inbound ID", $backadmin, 'HTML');
    step("getdatainboundproduct", $from_id);
} elseif ($user['step'] == "getdatainboundproduct") {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $datainbound = "";
    if ($marzban_list_get['type'] == "marzban") {
        $DataUserOut = getuser($text, $marzban_list_get['name_panel']);
        if (!empty($DataUserOut['error'])) {
            sendmessage($from_id, $DataUserOut['error'], null, 'HTML');
            return;
        }
        if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
            sendmessage($from_id, "❌  An error occurred. Error code:  {$DataUserOut['status']}", null, 'HTML');
            return;
        }
        $DataUserOut = json_decode($DataUserOut['body'], true);
        if (($marzban_list_get['version_panel'] ?? '0') === '1') {
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings'])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            if (empty($DataUserOut['group_ids']) || !is_array($DataUserOut['group_ids'])) {
                sendmessage($from_id, "❌ This user has no groups in PasarGuard.", null, 'HTML');
                return;
            }
            $DataUserOut['proxy_settings'] = marzban_sanitize_proxy_settings_for_storage($DataUserOut['proxy_settings']);
            $proxies_json = json_encode($DataUserOut['proxy_settings'], JSON_FORCE_OBJECT);
            $datainbound = json_encode($DataUserOut['group_ids']);
            $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':proxies', $proxies_json);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            if (isset($DataUserOut['hwid_limit']) && $DataUserOut['hwid_limit'] !== null && (int) $DataUserOut['hwid_limit'] > 0) {
                $hwid_limit = (int) $DataUserOut['hwid_limit'];
                $stmt = $pdo->prepare("UPDATE product SET hwid_limit = :hwid_limit WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
                $stmt->bindParam(':hwid_limit', $hwid_limit, PDO::PARAM_INT);
                $stmt->bindParam(':name_product', $user['Processing_value']);
                $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
                $stmt->bindParam(':agent', $user['Processing_value_tow']);
                $stmt->execute();
            }
        } else {
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxies'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } else {
                    unset($DataUserOut['proxies'][$key]['id']);
                }
                if (count($DataUserOut['proxies'][$key]) == 0) {
                    $DataUserOut['proxies'][$key] = new stdClass();
                }
            }
            $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
            $proxies_json = json_encode($DataUserOut['proxies']);
            $stmt->bindParam(':proxies', $proxies_json);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            $datainbound = json_encode($DataUserOut['inbounds']);
        }
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $userdata = json_decode(getuserm($text, $marzban_list_get['name_panel'])['body'], true);
        if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
            sendmessage($from_id, "User does not exist in the panel", null, 'HTML');
            return;
        }
        $datainbound = json_encode($userdata['service_ids'], true);
    } elseif ($marzban_list_get['type'] == "x-ui_single" || $marzban_list_get['type'] == "alireza_single") {
        $datainbound = $text;
    } elseif ($marzban_list_get['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $marzban_list_get['name_panel']);
        if (count($data) == 0) {
            sendmessage($from_id, "❌ User does not exist in the panel.", $options_ui, 'HTML');
            return;
        }
        $servies = [];
        foreach ($data['inbounds'] as $service) {
            $servies[] = $service;
        }
        $datainbound = json_encode($servies);
    } elseif ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $datainbound = $text;
    } else {
        sendmessage($from_id, "❌ This panel does not support defining inbounds", $shopkeyboard, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':inbounds', $datainbound);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅Product updated", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/extendadmin_(\w+)/', $datain, $dataget) || strpos($text, "/extend ") !== false) {
    if ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $id_invoice = select("invoice", "id_invoice", "username", $usernameconfig, 'select');
        if ($id_invoice == false) {
            sendmessage($from_id, "❌ User does not exist.", null, 'HTML');
            return;
        }
        $id_invoice = $id_invoice['id_invoice'];
    } else {
        $id_invoice = $dataget[1];
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, "❌ Renewal failed. Please run the renewal steps again.", null, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    $textcustom = "📌 Send the requested volume.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('gettimecustomvolomforextendadmin', $from_id);
} elseif ($user['step'] == "gettimecustomvolomforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("save", "volume", $text);
    $textcustom = "⌛️ Choose your service duration ";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextendadmin', $from_id);
} elseif ($user['step'] == "getvolumecustomuserforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['note'] = "";
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $text;
    $prodcut['Volume_constraint'] = $userdate['volume'];
    update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $userdate['id_invoice']);
    step("home", $from_id);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivceadmin-" . $nameloc['id_invoice']],
            ],
            [
                ['text' => "🏠 Back to main menu", 'callback_data' => "backuser"]
            ]
        ]
    ]);
    $textextend = "📜 Your renewal invoice for username {$nameloc['username']} was created.
        
🛍 Product name: {$prodcut['name_product']}
⏱ Renewal duration: {$prodcut['Service_time']} days
🔋 Renewal volume: {$prodcut['Volume_constraint']} GB
✍️ Description: {$prodcut['note']}
✅ Tap the button below to confirm and renew the service";
    if ($user['step'] == "getvolumecustomuserforextendadmin") {
        sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif (preg_match('/^confirmserivceadmin-(.*)/', $datain, $dataget)) {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $prodcut['code_product'] = "custom_volume";
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $nameloc['Service_time'];
    $prodcut['Volume_constraint'] = $nameloc['Volume'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ Renewal failed. Please run the renewal steps again.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = "
        Service renewal error
Panel name: {$marzban_list_get['name_panel']}
Service username: {$nameloc['username']}
Error: {$extend['msg']}";
        sendmessage($from_id, "❌An error occurred while renewing the service. Contact support", null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output, status) VALUES (:id_user, :username, :value, :type, :time, :price, :output, :status)");
    $dateacc = date('Y/m/d H:i:s');
    $value = $prodcut['Volume_constraint'] . "_" . $prodcut['Service_time'];
    $type = "extend_user_by_admin";
    $status = "paid";
    $stmt->bindParam(':id_user', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':username', $nameloc['username'], PDO::PARAM_STR);
    $stmt->bindParam(':value', $value, PDO::PARAM_STR);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':time', $dateacc, PDO::PARAM_STR);
    $stmt->bindParam(':price', $prodcut['price_product'], PDO::PARAM_STR);
    $output_json = json_encode($extend);
    $stmt->bindParam(':output', $output_json, PDO::PARAM_STR);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->execute();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], null, 'HTML');
    $text_report = "⭕️ An admin renewed the user's service.
        
User info: 
        
🪪 Admin numeric ID: <code>$from_id</code>
🪪 Numeric ID: <code>{$nameloc['id_user']}</code>
🛍 Product name:  {$prodcut['name_product']}
👤 Customer username in the panel: {$nameloc['username']}
User service location: {$nameloc['Service_location']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/removeresid_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order");
    $stmt->bindParam(':id_order', $idorder, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "✅ Receipt deleted successfully.", null, 'HTML');
}
if (isset($update["inline_query"])) {
    $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $query, PDO::PARAM_STR);
    $stmt->bindParam(':notes', $query, PDO::PARAM_STR);
    $stmt->execute();
    $invoices = $stmt->fetchAll();
    $results = [];
    foreach ($invoices as $OrderUser) {
        if (isset($OrderUser['time_sell'])) {
            $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
        } else {
            $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
        }
        if ($OrderUser['name_product'] == "سرویس تست") {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "hours";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "MB";
        } else {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "days";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "GB";
        }
        $results[] = [
            "type" => "article",
            "id" => uniqid(),
            'cache_time' => 0,
            'is_personal' => true,
            "title" => $OrderUser['username'],
            "input_message_content" => [
                "message_text" => "
🛒 Order number:  {$OrderUser['id_invoice']}
🛒  Order status in the bot: {$OrderUser['Status']}
🙍‍♂️ User ID: {$OrderUser['id_user']}
👤 Subscription username:  {$OrderUser['username']}
📍 Service location:  {$OrderUser['Service_location']}
🛍 Product name:  {$OrderUser['name_product']}
💰 Amount paid: {$OrderUser['price_product']} USD
⚜️ Purchased volume: {$OrderUser['Volume']}
⏳ Purchased duration: {$OrderUser['Service_time']} 
📆 Purchase date: $datatime  
"
            ]
        ];
    }
    answerInlineQuery($inline_query_id, $results);
} elseif (preg_match('/vieworderuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM invoice WHERE id_user = '$id_user'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Service status", 'callback_data' => "Status"],
        ['text' => "Username", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "View info",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json, 'html');
} elseif (preg_match('/next_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM invoice WHERE id_user = '$id_user'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Service status", 'callback_data' => "Status"],
        ['text' => "Username", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "View info",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM invoice WHERE id_user = '$id_user'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Service status", 'callback_data' => "Status"],
        ['text' => "Username", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "View info",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "Lucky wheel button text" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_wheel_luck'], $backadmin, 'HTML');
    step('text_wheel_luck', $from_id);
} elseif ($user['step'] == "text_wheel_luck") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_wheel_luck");
    step('home', $from_id);
} elseif ($datain == "cartuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE cardpayment = '1'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageusercart') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE cardpayment = '1'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageusercart') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE cardpayment = '1'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/createbot_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $checkbot = select("botsaz", "*", "id_user", $id_user, "count");
    $checkbots = select("botsaz", "*", null, null, "count");
    if ($checkbots >= 15) {
        sendmessage($from_id, "❌  You are currently limited to creating 15 bots for your agents.", $keyboardadmin, 'HTML');
        return;
    }
    if ($checkbot != 0) {
        $textexitsbot = "❌ This bot is already installed and cannot be installed again.";
        sendmessage($from_id, $textexitsbot, $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "id_user", $id_user);
    $texbot = "📌  Here you can create a sales bot for your agent so they can sell with their own bot

- To create the bot, send the bot token.";
    sendmessage($from_id, $texbot, $backadmin, 'HTML');
    step("gettokenbot", $from_id);
} elseif ($user['step'] == "gettokenbot") {
    $getInfoToken = json_decode(file_get_contents("https://api.telegram.org/bot$text/getme"), true);
    if ($getInfoToken == false or !$getInfoToken['ok']) {
        sendmessage($from_id, "❌ Invalid token", $backadmin, 'HTML');
        return;
    }
    $checkbot = select("botsaz", "*", "bot_token", $text, "count");
    if ($checkbot != 0) {
        sendmessage($from_id, "📌 This token is already registered", null, 'HTML');
        return;
    }
    savedata("save", "token", $text);
    savedata("save", "username", $getInfoToken['result']['username']);
    $texbot = "📌 Send the admin numeric ID";
    sendmessage($from_id, $texbot, $backadmin, 'HTML');
    step("getadminidbot", $from_id);
} elseif ($user['step'] == "getadminidbot") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    step("home", $from_id);
    $result = agent_create_sell_bot($userdate['id_user'], $userdate['token'], getcwd());
    if (!$result['ok']) {
        sendmessage($from_id, "❌ " . $result['msg'], $keyboardadmin, 'HTML');
        return;
    }
    $texbot = "✅ Agent bot created successfully.
⚙️ Bot username: @{$result['username']}
🤠 Bot token: <code>{$userdate['token']}</code>";
    sendmessage($from_id, $texbot, $keyboardadmin, 'HTML');
} elseif (preg_match('/removebotsell_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $result = agent_remove_sell_bot($id_user, getcwd());
    sendmessage($from_id, $result['ok'] ? "❌ Agent sales bot deleted successfully." : ("❌ " . $result['msg']), $keyboardadmin, 'HTML');
} elseif (preg_match('/setvolumesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "📌 Set the lowest price the agent should pay per GB of volume", $backadmin, 'HTML');
    step("getpricevolumesrc", $from_id);
} elseif ($user['step'] == "getpricevolumesrc") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricevolume'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    sendmessage($from_id, "✅ Price saved successfully.", $keyboardadmin, 'HTML');
} elseif (preg_match('/settimepricesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "📌 Set the lowest price the agent should pay per day of time", $backadmin, 'HTML');
    step("getpricetimesrc", $from_id);
} elseif ($user['step'] == "getpricetimesrc") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricetime'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    sendmessage($from_id, "✅ Price saved successfully.", $keyboardadmin, 'HTML');
}
if ($datain == "settimecornday" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 Here you can set how many days before subscription expiry the user is notified. Time is in days" . $setting['daywarn'] . "days", $backadmin, 'HTML');
    step("getdaywarn", $from_id);
} elseif ($user['step'] == "getdaywarn") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $keyboardadmin, 'HTML');
    step("home", $from_id);
    update("setting", "daywarn", $text);
} elseif ($datain == "linkappsetting") {
    sendmessage($from_id, "📌 Choose an option.", $keyboardlinkapp, 'HTML');
} elseif ($text == "🔗 Add app") {
    sendmessage($from_id, "📌 To add an app download link, send the app name or button name.", $backadmin, 'HTML');
    step("getnamebtnapp", $from_id);
} elseif ($user['step'] == "getnamebtnapp") {
    if (strlen($text) > 200) {
        sendmessage($from_id, "📌 Name must be under 200 characters.", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name", $text);
    sendmessage($from_id, "📌 Send the app download link", $backadmin, 'HTML');
    step("geturlbtnapp", $from_id);
} elseif ($user['step'] == "geturlbtnapp") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO app (name, link) VALUES (:name, :link)");
    $stmt->bindParam(':name', $userdate['name'], PDO::PARAM_STR);
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "✅ Your app link was added successfully.", $keyboardlinkapp, 'HTML');
    step("home", $from_id);
} elseif ($text == "❌ Remove app") {
    sendmessage($from_id, "📌 To delete an app, choose the app name from the list", keyboard_help_app_remove(), 'HTML');
    step("getnameappforremove", $from_id);
} elseif ($user['step'] == "getnameappforremove") {
    sendmessage($from_id, "✅ App deleted successfully.", $keyboardlinkapp, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM app WHERE name = :name");
    $stmt->bindParam(':name', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($text == "⚙️ Panel feature status" && $adminrulecheck['rule'] == "administrator") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!in_array($panel['subvip'], ['offsubvip', 'onsubvip'])) {
        update("marzban_panel", "subvip", "offsubvip", "code_panel", $panel['code_panel']);
        $panel = select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
    }
    if (!in_array($panel['version_panel'], ['0', '1'])) {
        $panel['version_panel'] = '0';
    }
    $customvlume = json_decode($panel['customvolume'], true);
    $statusconfig = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['config']];
    $statussublink = [
        'onsublink' => $textbotlang['Admin']['Status']['statuson'],
        'offsublink' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['sublink']];
    $statusshowbuy = [
        'active' => $textbotlang['Admin']['Status']['statuson'],
        'disable' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['status']];
    $statusshowtest = [
        'ONTestAccount' => $textbotlang['Admin']['Status']['statuson'],
        'OFFTestAccount' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['TestAccount']];
    $statusconnecton = [
        'onconecton' => $textbotlang['Admin']['Status']['statuson'],
        'offconecton' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['conecton']];
    $status_extend = [
        'on_extend' => $textbotlang['Admin']['Status']['statuson'],
        'off_extend' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['status_extend']];
    $changeloc = [
        'onchangeloc' => $textbotlang['Admin']['Status']['statuson'],
        'offchangeloc' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['changeloc']];
    $inbocunddisable = [
        'oninbounddisable' => $textbotlang['Admin']['Status']['statuson'],
        'offinbounddisable' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['inboundstatus']];
    $subvip = [
        'onsubvip' => $textbotlang['Admin']['Status']['statuson'],
        'offsubvip' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['subvip']];
    $customstatusf = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['f']];
    $customstatusn = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['n']];
    $customstatusn2 = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['n2']];
    $on_hold_test = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['on_hold_test']];
    $version_panel_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['version_panel']];
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 Show panel", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 Show test", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 Renewal status", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ Custom service group f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ Custom service group n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ Custom service group n2", 'callback_data' => "none"],
            ]
        ]
    ];
    if (in_array($panel['type'], ['marzban'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $version_panel_status, 'callback_data' => "editpanel-versionpanel-{$panel['version_panel']}-{$panel['code_panel']}"],
            ['text' => "🎛 PasarGuard panel", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ Send config", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ Send subscription link", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 First connection", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 Test account first connection", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 Change location", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 Dedicated sub link", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 Disabled account", 'callback_data' => "none"],
        ];
    }
    if ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        unset($Bot_Status['inline_keyboard'][2]);
        unset($Bot_Status['inline_keyboard'][3]);
        unset($Bot_Status['inline_keyboard'][4]);
        unset($Bot_Status['inline_keyboard'][5]);
        unset($Bot_Status['inline_keyboard'][6]);
        unset($Bot_Status['inline_keyboard'][7]);
        unset($Bot_Status['inline_keyboard'][8]);
        unset($Bot_Status['inline_keyboard'][9]);
    }
    $Bot_Status['inline_keyboard'] = array_values($Bot_Status['inline_keyboard']);
    $Bot_Status = json_encode($Bot_Status);
    sendmessage($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editpanel-(.*)-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    $code_panel = $dataget[3];
    if ($type == "stautsconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("marzban_panel", "config", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "sublink") {
        if ($value == "onsublink") {
            $valuenew = "offsublink";
        } else {
            $valuenew = "onsublink";
        }
        update("marzban_panel", "sublink", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statusbuy") {
        if ($value == "active") {
            $valuenew = "disable";
        } else {
            $valuenew = "active";
        }
        update("marzban_panel", "status", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statustest") {
        if ($value == "ONTestAccount") {
            $valuenew = "OFFTestAccount";
        } else {
            $valuenew = "ONTestAccount";
        }
        update("marzban_panel", "TestAccount", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "connecton") {
        if ($value == "onconecton") {
            $valuenew = "offconecton";
        } else {
            $valuenew = "onconecton";
        }
        update("marzban_panel", "conecton", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "stautsextend") {
        if ($value == "on_extend") {
            $valuenew = "off_extend";
        } else {
            $valuenew = "on_extend";
        }
        update("marzban_panel", "status_extend", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "changeloc") {
        if ($value == "onchangeloc") {
            $valuenew = "offchangeloc";
        } else {
            $valuenew = "onchangeloc";
        }
        update("marzban_panel", "changeloc", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "inbocunddisable") {
        if ($value == "oninbounddisable") {
            $valuenew = "offinbounddisable";
        } else {
            $valuenew = "oninbounddisable";
        }
        update("marzban_panel", "inboundstatus", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "subvip") {
        if ($value == "onsubvip") {
            $valuenew = "offsubvip";
        } else {
            $valuenew = "onsubvip";
        }
        update("marzban_panel", "subvip", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "customstatusf") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['f'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn2") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n2'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "on_hold_Test") {
        if ($value == "0") {
            $valuenew = "1";
        } else {
            $valuenew = "0";
        }
        update("marzban_panel", "on_hold_test", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "versionpanel") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("marzban_panel", "version_panel", $valuenew, "code_panel", $code_panel);
    }
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $customvlume = json_decode($panel['customvolume'], true);
    $statusconfig = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['config']];
    $statussublink = [
        'onsublink' => $textbotlang['Admin']['Status']['statuson'],
        'offsublink' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['sublink']];
    $statusshowbuy = [
        'active' => $textbotlang['Admin']['Status']['statuson'],
        'disable' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['status']];
    $statusshowtest = [
        'ONTestAccount' => $textbotlang['Admin']['Status']['statuson'],
        'OFFTestAccount' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['TestAccount']];
    $statusconnecton = [
        'onconecton' => $textbotlang['Admin']['Status']['statuson'],
        'offconecton' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['conecton']];
    $status_extend = [
        'on_extend' => $textbotlang['Admin']['Status']['statuson'],
        'off_extend' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['status_extend']];
    $changeloc = [
        'onchangeloc' => $textbotlang['Admin']['Status']['statuson'],
        'offchangeloc' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['changeloc']];
    $inbocunddisable = [
        'oninbounddisable' => $textbotlang['Admin']['Status']['statuson'],
        'offinbounddisable' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['inboundstatus']];
    $subvip = [
        'onsubvip' => $textbotlang['Admin']['Status']['statuson'],
        'offsubvip' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['subvip']];
    $customstatusf = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['f']];
    $customstatusn = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['n']];
    $customstatusn2 = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['n2']];
    $on_hold_test = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['on_hold_test']];
    $version_panel_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['version_panel']];
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 Show panel", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 Show test", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 Renewal status", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ Custom service group f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ Custom service group n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ Custom service group n2", 'callback_data' => "none"],
            ]
        ]
    ];
    if (in_array($panel['type'], ['marzban'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $version_panel_status, 'callback_data' => "editpanel-versionpanel-{$panel['version_panel']}-{$panel['code_panel']}"],
            ['text' => "🎛 PasarGuard panel", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ Send config", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ Send subscription link", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 First connection", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 Test account first connection", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 Change location", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 Dedicated sub link", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 Disabled account", 'callback_data' => "none"],
        ];
    }
    $Bot_Status = json_encode($Bot_Status);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($datain == "startelegram") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $Startelegram, 'HTML');
} elseif ($text == "⬇️ Stars minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainaqstar", $from_id);
} elseif ($user['step'] == "getmainaqstar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancestar");
} elseif ($text == "⬆️ Stars maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("maxbalancestar", $from_id);
} elseif ($user['step'] == "maxbalancestar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancestar");
} elseif ($text == "⬇️ NowPayments minimum") {
    sendmessage($from_id, "📌 Send the minimum deposit amount", $backadmin, 'HTML');
    step("getmainaqnowpayment", $from_id);
} elseif ($user['step'] == "getmainaqnowpayment") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Minimum deposit amount set.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancenowpayment");
} elseif ($text == "⬆️ NowPayments maximum") {
    sendmessage($from_id, "📌 Send the maximum deposit amount", $backadmin, 'HTML');
    step("maxbalancenowpayment", $from_id);
} elseif ($user['step'] == "maxbalancenowpayment") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Maximum deposit amount set.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancenowpayment");
} elseif ($text == "📚 Set Stars guide" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌Send your tutorial.
1 - If you do not want a tutorial shown, send 2
2 - You can send the tutorial as video, text, or image", $backadmin, 'HTML');
    step("gethelpstar", $from_id);
} elseif ($user['step'] == "gethelpstar") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpstar");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } else {
        sendmessage($from_id, "❌ Invalid submitted content.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ Tutorial saved successfully.", $Startelegram, 'HTML');
} elseif ($text == "💰 Stars cashback") {
    sendmessage($from_id, "📌 Here you can set what percent is credited as a gift after payment. (Send zero to disable this feature)", $backadmin, 'HTML');
    step("chashbackstar", $from_id);
} elseif ($user['step'] == "chashbackstar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ Amount saved successfully.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackstar");
} elseif ($text == "🔋 Quick volume-price setup") {
    sendmessage($from_id, "📌 Please read the text below before sending info. 
1 - This feature is for custom service.
2 - If all your panels have the same price, you can set prices in one place instead of one by one.
3 - Setting a price here cannot be undone.


To set the price, first send the group f price.", $backadmin, 'HTML');
    step("getpricef", $from_id);
} elseif ($user['step'] == "getpricef") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    sendmessage($from_id, "📌 Send the group n price.", $backadmin, 'HTML');
    step("getpricnn", $from_id);
} elseif ($user['step'] == "getpricnn") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    sendmessage($from_id, "📌 Send the group n2 price.", $backadmin, 'HTML');
    step("getpricnn2", $from_id);
} elseif ($user['step'] == "getpricnn2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomvolume", $pricelist, null, null);
    sendmessage($from_id, "✅ Price set successfully", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($text == "⏳ Quick time-price setup") {
    sendmessage($from_id, "📌 Daily custom-service price setting has been removed.\nSet month options and price coefficients from the web panel → «Custom service» tab.\nFinal price = GB × price per GB × month coefficient.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getpriceftime" || $user['step'] == "getpricnntime" || $user['step'] == "getpricnn2time") {
    sendmessage($from_id, "📌 Daily custom-service price setting has been removed. Set month options and coefficients from the web panel.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "changeloclimit") {
    sendmessage($from_id, "📌 Choose an option.
1 - Overall limit: how many times the user can change location in total.
2 - Free limit: how many of the overall changes can be free.", $keyboardchangelimit, 'HTML');
} elseif ($text == "↙️ Overall limit") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    sendmessage($from_id, "📌  Send the overall location-change limit. This limit applies to all configs
Current limit: {$limitnumber['all']}", $backadmin, 'HTML');
    step("limitchangeall", $from_id);
} elseif ($user['step'] == "limitchangeall") {
    sendmessage($from_id, "✅ Limit set successfully.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['all'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🆓 Free limit") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    sendmessage($from_id, "📌  Send the free location-change limit. This limit applies to all configs
Current limit: {$limitnumber['free']}", $backadmin, 'HTML');
    step("limitfreechangefree", $from_id);
} elseif ($user['step'] == "limitfreechangefree") {
    sendmessage($from_id, "✅ Limit set successfully.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['free'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🔄 Reset all-user limits") {
    $keyboarddata = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Confirm and reset", 'callback_data' => 'reasetchangeloc'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 Confirming the option below will reset all location changes the user has made. If you agree, tap the option below.", $keyboarddata, 'HTML');
} elseif ($datain == "reasetchangeloc") {
    Editmessagetext($from_id, $message_id, "✅ All user limits were reset.", null);
    update("user", "limitchangeloc", "0", null, null);
} elseif (preg_match('/changeloclimitbyuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "📌 Send the new limit you want for the user. This changes the number of location changes already used", $backadmin, 'HTML');
    step("getlimitchangenewbyuser", $from_id);
} elseif ($user['step'] == "getlimitchangenewbyuser") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    update("user", "limitchangeloc", $text, "id", $userdate['id_user']);
    sendmessage($from_id, "✅ User usage count saved successfully.", $keyboardadmin, 'HTML');
} elseif (preg_match('/hidepanel_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "❌ Choose the panels that should not be shown to this agent from the buttons below. After selecting, send /finish to save.", $json_list_marzban_panel, 'HTML');
    step("getpanelhidebotsaz", $from_id);
} elseif ($text == "/finish") {
    sendmessage($from_id, "✅ Panels saved and hidden for the user.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getpanelhidebotsaz") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        sendmessage($from_id, "❌ Panel was already added", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    sendmessage($from_id, "✅ Panel selected. After you finish, send /finish to save.", null, 'HTML');
} elseif (preg_match('/removehide_(\w+)/', $datain, $datagetr)) {
    global $list_hide_panel;
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $id_user, "select")['hide_panel'], true);
    $list_hide_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_panel as $panelname) {
        $list_hide_panel['keyboard'][] = [
            ['text' => $panelname]
        ];
    }
    $list_hide_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $list_hide_panel = json_encode($list_hide_panel);
    sendmessage($from_id, "❌ From the list below, choose the panels you want shown again in the agent bot. After selecting all panels, send /remove to save.", $list_hide_panel, 'HTML');
    step("getremovehidepanel", $from_id);
} elseif ($text == "/remove") {
    sendmessage($from_id, "✅ Panels are now shown and enabled for the user.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getremovehidepanel") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (!in_array($text, $list_panel)) {
        sendmessage($from_id, "❌ Panel is not in the list", null, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_panel as $panel) {
        if ($panel == $text) {
            unset($list_panel[$count]);
            break;
        }
        $count += 1;
    }
    $list_panel = array_values($list_panel);
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    sendmessage($from_id, "✅ Panel selected. After you finish, send /remove to save.", null, 'HTML');
} elseif ($datain == "voloume_or_day_all") {
    if (is_file('cronbot/gift') || is_file('cronbot/info')) {
        sendmessage($from_id, "❌ A bulk operation is already running. Try again after it finishes.", $keyboardadmin, 'HTML');
        return;
    }
    foreach (['cronbot/username.json', 'cronbot/users.json'] as $queueFile) {
        if (!is_file($queueFile)) {
            continue;
        }
        $queuedItems = json_decode(file_get_contents($queueFile), true);
        if (is_array($queuedItems) && count($queuedItems) > 0) {
            sendmessage($from_id, "❌ A bulk operation is already running. Try again after it finishes.", $keyboardadmin, 'HTML');
            return;
        }
    }
    sendmessage($from_id, "📌 Which panel's services do you want to gift volume or time to?", $json_list_marzban_panel, "html");
    step("getpanelgift", $from_id);
} elseif ($user['step'] == "getpanelgift") {
    $panel = select("marzban_panel", "*", "name_panel", $text, "count");
    if ($panel == 0) {
        sendmessage($from_id, "❌ Panel does not exist", null, "html");
        return;
    }
    savedata("clear", "name_panel", $text);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔋 Volume", 'callback_data' => 'typegift_volume'],
                ['text' => "⏳ Time", 'callback_data' => 'typegift_day'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 Choose one of the gifts below.", $keyboardstatistics, "html");
    step('home', $from_id);
} elseif (preg_match('/typegift_(\w+)/', $datain, $datagetr)) {
    $typegift = $datagetr[1];
    savedata("save", "typegift", $typegift);
    deletemessage($from_id, $message_id);
    if ($typegift == "volume") {
        sendmessage($from_id, "📌 How many GB should be added to the user's services", $backadmin, "html");
    } else {
        sendmessage($from_id, "📌 How many days should be added to users' services", $backadmin, "html");
    }
    step("getvaluegift", $from_id);
} elseif ($user['step'] == "getvaluegift") {
    if (!ctype_digit((string) $text) || intval($text) <= 0) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "value", $text);
    sendmessage($from_id, "📌 Send the text you want sent to the user", $backadmin, "html");
    step("gettextgift", $from_id);
} elseif ($user['step'] == "gettextgift") {
    savedata("save", "text", $text);
    savedata("save", "id_admin", $from_id);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ Confirm and start", 'callback_data' => 'startgift'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 Dear admin, confirming the option below will start applying gifts. This may take time due to limits.", $keyboardstatistics, "html");
    step("home", $from_id);
} elseif ($datain == "startgift") {
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌ Cancel gift", 'callback_data' => 'cancel_gift'],
            ],
        ]
    ]);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typegift'])) {
        sendmessage($from_id, "❌ An error occurred. Restart the steps from the beginning.", $keyboardstatistics, "html");
        return;
    }
    foreach (['cronbot/username.json', 'cronbot/users.json', 'cronbot/gift', 'cronbot/info'] as $queueFile) {
        if (!is_file($queueFile)) {
            continue;
        }
        $queueBusy = true;
        if (str_ends_with($queueFile, '.json')) {
            $queuedItems = json_decode(file_get_contents($queueFile), true);
            $queueBusy = is_array($queuedItems) && count($queuedItems) > 0;
        }
        if ($queueBusy) {
            sendmessage($from_id, "❌ Another bulk operation is already running.", $keyboardadmin, 'HTML');
            return;
        }
    }
    $stmt = $pdo->prepare(
        "SELECT id_invoice, id_user, username, Service_location
         FROM invoice
         WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')
           AND Service_location = :panel
           AND name_product != 'سرویس تست'"
    );
    $stmt->execute([':panel' => $userdata['name_panel']]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$services) {
        sendmessage($from_id, "❌ No service found to apply the gift.", $keyboardadmin, 'HTML');
        return;
    }
    $message_id = Editmessagetext($from_id, $message_id, "✅ Gift operation started. You will be notified after it is applied and finished.", $keyboardstatistics);
    $userdata['id_message'] = $message_id['result']['message_id'];
    $userdata['total'] = count($services);
    $userdata['success_count'] = 0;
    $userdata['failed_count'] = 0;
    $userdata['skipped_count'] = 0;
    file_put_contents('cronbot/gift', json_encode($userdata, JSON_UNESCAPED_UNICODE), LOCK_EX);
    file_put_contents('cronbot/username.json', json_encode($services, JSON_UNESCAPED_UNICODE), LOCK_EX);
} elseif ($datain == "cancel_gift") {
    $giftJob = is_file('cronbot/gift')
        ? json_decode((string) file_get_contents('cronbot/gift'), true)
        : null;
    $remainingGift = [];
    if (is_file('cronbot/username.json')) {
        $queuedGift = json_decode((string) file_get_contents('cronbot/username.json'), true);
        $remainingGift = is_array($queuedGift) ? $queuedGift : [];
    }
    if (is_array($giftJob) && !empty($giftJob['bulk_service_charge'])) {
        require_once __DIR__ . '/cronbot/gift_report.php';
        gift_send_unfinished_report($giftJob, $remainingGift, true);
    }
    if (is_file('cronbot/username.json')) {
        unlink('cronbot/username.json');
    }
    if (is_file('cronbot/gift')) {
        unlink('cronbot/gift');
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 Gift cancelled.", null, 'HTML');
} elseif (preg_match('/expireset_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "🕘 Send the agency expiry time. After the set number of days the user leaves agency mode and their group becomes f.
Note: this is unrelated to the bot builder or agent sales bot and only applies to your main bot

📌 Send the number of days", $backadmin, 'HTML');
    step("gettime_expire_agent", $from_id);
} elseif ($user['step'] == "gettime_expire_agent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $timestamp = time() + (intval(value: $text) * 86400);
    update("user", "expire", $timestamp, "id", $userdate['id_user']);
    sendmessage($from_id, "✅ Expiry date set.
📌 After the time ends, the user's group will change to f and they will be notified.", $keyboardadmin, 'HTML');
} elseif ($text == "♻️ Group card-number display") {
    sendmessage($from_id, "📌 Send the list of IDs you want the card number shown for 
Example: 
1234435423
23423131", $backadmin, 'HTML');
    step("getlistidcart", $from_id);
} elseif ($user['step'] == "getlistidcart") {
    $list = explode("\n", $text);
    foreach ($list as $id_user) {
        if (!rowExists('user', 'id', $id_user)) {
            sendmessage($from_id, "📌 User with numeric ID $id_user does not exist in the database", $backadmin, 'HTML');
            continue;
        }
        update("user", "cardpayment", "1", "id", $id_user);
    }
    sendmessage($from_id, "✅ Card number enabled for the submitted users.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "📄 Export users with card display on") {
    $listusers = select("user", "id", "cardpayment", "1", "fetchAll");
    if (!$listusers) {
        sendmessage($from_id, "📌 Card number is not enabled for any user", $CartManage, 'HTML');
        return;
    }
    $filename = 'cartlist.txt';
    foreach ($listusers as $id_user) {
        file_put_contents($filename, $id_user['id'] . "\n", FILE_APPEND);
    }
    sendDocument($from_id, $filename, "🪪 Users who have card number enabled");
    unlink($filename);
} elseif ($text == "🎉 First-purchase commission only" && $adminrulecheck['rule'] == "administrator") {
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    sendmessage($from_id, "You can set whether commission is paid only on the referral's first purchase or on all of their purchases.", $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "on_buy_porsant") {
    update("affiliates", "porsant_one_buy", "off_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "You can set whether commission is paid only on the referral's first purchase or on all of their purchases.", $keyboardDiscountaffiliates);
} elseif ($datain == "off_buy_porsant") {
    update("affiliates", "porsant_one_buy", "on_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "You can set whether commission is paid only on the referral's first purchase or on all of their purchases.", $keyboardDiscountaffiliates);
} elseif ($text == "Agency request description" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_request_agent_dec']}</code>", $backadmin, 'HTML');
    step('text_request_agent_dec', $from_id);
} elseif ($user['step'] == "text_request_agent_dec") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", text_from_telegram_update($update), "id_text", "text_request_agent_dec");
    step('home', $from_id);
} elseif (preg_match('/changestatusadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ Not connected to the config yet, so the service status cannot be changed. After connecting to the config you can use this feature.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ Confirm and disable config', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 Confirming the option below will turn the config off and it can no longer be connected to.
⚠️ To enable it again, tap <u>💡 Enable account</u> in service management", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ Confirm and enable config', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 Confirming the option below will turn the config on so you can connect.
⚠️ To disable it again, tap <u>❌ Disable account</u> in service management", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisableadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disablebyadmin", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/removefull-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "Confirm and delete ", 'callback_data' => "confirmremovefulls-" . $id_invoice],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 Confirming the option below will fully delete this service from the bot database and it will no longer count in stats (this does not delete the service from the panel, only from the bot database)", $bakinfos);
} elseif (preg_match('/confirmremovefulls-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invocie = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id_invoice");
    $stmt->bindParam(':id_invoice', $id_invoice, PDO::PARAM_STR);
    $stmt->execute();
    Editmessagetext($from_id, $message_id, "✅ Service deleted successfully.", json_encode(['inline_keyboard' => []]));
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => "🔗 An admin deleted a service from the bot database.

- Admin numeric ID: $from_id
- Admin name: $first_name
- Service username: {$invocie['username']}",
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "🛒 Add category") {
    sendmessage($from_id, "📌 To add a category, send the category name.\n✨ If you send a premium emoji, it will show on the category button.", $backadmin, 'HTML');
    step("getremarkcategory", $from_id);
} elseif ($user['step'] == "getremarkcategory") {
    $parsed = button_label_and_icon_from_update($update);
    $remark = $parsed['text'] !== '' ? $parsed['text'] : category_remark_from_update($update);
    if ($remark === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $backadmin, 'HTML');
        return;
    }
    if (category_remark_taken($remark)) {
        sendmessage($from_id, '❌ A category with this name is already registered.', $backadmin, 'HTML');
        return;
    }
    ensure_shop_button_emoji_columns();
    $emoji_id = stored_custom_emoji_id($parsed['emoji_id']);
    $stmt = $pdo->prepare("INSERT INTO category (remark, emoji_id) VALUES (?, ?)");
    $stmt->execute([$remark, $emoji_id !== '' ? $emoji_id : null]);
    sendmessage($from_id, "✅ Category added successfully.", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "❌ Remove category") {
    sendmessage($from_id, "📌 Choose the category to delete", KeyboardCategoryadmin(), 'HTML');
    step("removecategory", $from_id);
} elseif ($user['step'] == "removecategory") {
    $resolved = resolve_category_from_update($update);
    if (!$resolved['ok']) {
        sendmessage($from_id, category_resolve_error_text($resolved['error']), KeyboardCategoryadmin(), 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM category WHERE id = :id");
    $stmt->bindValue(':id', (int) $resolved['category']['id'], PDO::PARAM_INT);
    $stmt->execute();
    sendmessage($from_id, "✅ Category deleted successfully.", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "Hide panel" && $adminrulecheck['rule'] == "administrator") {
    if ($user['Processing_value_one'] != "/all") {
        sendmessage($from_id, "📌 This feature only applies when you defined the product location as /all.", null, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 If you selected panel location /all but need to hide one panel, use this feature

To hide a panel, choose your panels from the list then send /end_hide.", $json_list_marzban_panel, 'HTML');
    step('getlistpanel', $from_id);
} elseif ($text == "/end_hide") {
    sendmessage($from_id, "✅ Panels saved and hidden for the selected product.", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getlistpanel") {
    $list_panel = json_decode(select("product", "hide_panel", "id", $user['Processing_value'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        sendmessage($from_id, "❌ Panel was already added", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("product", "hide_panel", json_encode($list_panel), "id", $user['Processing_value']);
    sendmessage($from_id, "✅ Panel selected. After you finish, send /end_hide to save.", null, 'HTML');
} elseif ($text == "Clear all hidden panels" && $adminrulecheck['rule'] == "administrator") {
    update("product", "hide_panel", "{}", "name_product", $user['Processing_value']);
    sendmessage($from_id, "✅ All hidden panels were removed", null, 'HTML');
} elseif ($text == "🔗 Re-webhook agent bots") {
    $bots_agent = select("botsaz", "*", null, null, "fetchAll");
    if (count($bots_agent) == 0) {
        sendmessage($from_id, "❌ There is no bot", null, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 Setting webhook ...", null, 'HTML');
    foreach ($bots_agent as $bot) {
        file_get_contents("https://api.telegram.org/bot{$bot['bot_token']}/setwebhook?url=https://$domainhosts/vpnbot/{$bot['id_user']}{$bot['username']}/index.php");
    }
    sendmessage($from_id, "✅ Webhook set successfully.", null, 'HTML');
} elseif (preg_match('/statuscronuser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $user_status = select("user", "*", "id", $id_user);
    if (intval($user_status['status_cron']) == 0) {
        update("user", "status_cron", "1", "id", $id_user);
        sendmessage($from_id, "✅ Cron notifications enabled for the user.", null, 'HTML');
    } else {
        update("user", "status_cron", "0", "id", $id_user);
        sendmessage($from_id, "✅ Cron notifications disabled for the user.", null, 'HTML');
    }
} elseif ($text == "🗂 Category management") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
} elseif ($text == "⬅️ Back to shop menu") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 Product management" || $datain == "backproductadmin") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
} elseif ($text == "✏️ Edit category") {
    sendmessage($from_id, "📌 Choose the category to edit", KeyboardCategoryadmin(), 'HTML');
    step("editcategory_name", $from_id);
} elseif ($user['step'] == "editcategory_name") {
    $resolved = resolve_category_from_update($update);
    if (!$resolved['ok']) {
        sendmessage($from_id, category_resolve_error_text($resolved['error']), KeyboardCategoryadmin(), 'HTML');
        return;
    }
    savedata("clear", "category_id", (string) $resolved['category']['id']);
    savedata("save", "category", $resolved['category']['remark']);
    sendmessage($from_id, "📌  Send the new category name\n✨ If you send a premium emoji, it will show on the category button.", $backadmin, 'HTML');
    step("get_name_new_category", $from_id);
} elseif ($user['step'] == "get_name_new_category") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }
    $categoryId = (int) ($userdata['category_id'] ?? 0);
    $old = $categoryId > 0 ? select("category", "*", "id", $categoryId, "select") : null;
    if (!is_array($old) && !empty($userdata['category'])) {
        $matches = fetch_categories_by_remark((string) $userdata['category']);
        if (count($matches) === 1) {
            $old = $matches[0];
            $categoryId = (int) ($old['id'] ?? 0);
        }
    }
    if (!is_array($old) || $categoryId <= 0) {
        sendmessage($from_id, '❌ Category not found. Choose again.', $keyboard_Category_manage, 'HTML');
        step("home", $from_id);
        return;
    }
    $parsed = button_label_and_icon_from_update($update);
    $remark = $parsed['text'] !== '' ? $parsed['text'] : category_remark_from_update($update);
    if ($remark === '') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $backadmin, 'HTML');
        return;
    }
    if (category_remark_taken($remark, $categoryId)) {
        sendmessage($from_id, '❌ A category with this name is already registered.', $backadmin, 'HTML');
        return;
    }
    ensure_shop_button_emoji_columns();
    $emoji_id = stored_custom_emoji_id($parsed['emoji_id']);
    $oldRemark = (string) ($old['remark'] ?? '');
    if ($emoji_id !== '') {
        $stmt = $pdo->prepare("UPDATE category SET remark = ?, emoji_id = ? WHERE id = ?");
        $stmt->execute([$remark, $emoji_id, $categoryId]);
    } else {
        $stmt = $pdo->prepare("UPDATE category SET remark = ? WHERE id = ?");
        $stmt->execute([$remark, $categoryId]);
    }
    if ($oldRemark !== $remark) {
        $stmt = $pdo->prepare("UPDATE product SET category = ? WHERE category = ?");
        $stmt->execute([$remark, $oldRemark]);
    }
    clearSelectCache('category');
    clearSelectCache('product');
    sendmessage($from_id, "✅ Category name changed successfully.", $keyboard_Category_manage, 'HTML');
    step("home", $from_id);
} elseif ($datain == "zerobalance") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance < 0  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserzero') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance < 0  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserzero') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance < 0  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "Action", 'callback_data' => "action"],
        ['text' => "Username", 'callback_data' => "username"],
        ['text' => "ID", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "Back to previous menu",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "✏️ Edit app") {
    sendmessage($from_id, "📌 To edit an app, choose the app name from the list", keyboard_help_app_remove(), 'HTML');
    step("edit_app", $from_id);
} elseif ($user['step'] == "edit_app") {
    savedata("clear", "nameapp", $text);
    step("get_new_lin_app", $from_id);
    sendmessage($from_id, "📌 Send the new app link", $backadmin, 'HTML');
} elseif ($user['step'] == "get_new_lin_app") {
    step("home", $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    sendmessage($from_id, "✅ App link updated successfully.", $keyboardlinkapp, 'HTML');
    update("app", "link", $text, "name", $userdata['nameapp']);
} elseif ($datain == "nowpaymentsetting") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "⏳ Auto-confirm without review delay") {
    sendmessage($from_id, "📌 Here you can set after how many minutes auto-confirm without review should approve the receipt.
Send the time in minutes
Current time: {$setting['timeauto_not_verify']}", $backadmin, 'HTML');
    step("gettimeauto", $from_id);
} elseif ($user['step'] == "gettimeauto") {
    if (!is_numeric($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("setting", "timeauto_not_verify", $text);
    sendmessage($from_id, "✅ Time saved successfully.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "Show for first purchase") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :name_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "Off",
        '1' => "On"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 With this feature you can set whether this product is for first purchase or not", $Response, 'HTML');
} elseif (preg_match('/status_on_buy-(.*)-(.*)/', $datain, $dataget)) {
    $code_product = $dataget[1];
    $status_now = $dataget[2];
    if ($status_now == '0') {
        $status_now = '1';
    } else {
        $status_now = '0';
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET one_buy_status = :one_buy_status WHERE code_product = :code_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':one_buy_status', $status_now);
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "Off",
        '1' => "On"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 With this feature you can set whether this product is for first purchase or not", $Response);
} elseif ($text == "💳 Exclude user from auto-confirm") {
    sendmessage($from_id, "📌 Choose an option
⚠️ This section is for auto-confirm without review", $Exception_auto_cart_keyboard, 'HTML');
} elseif ($text == "➕ Exclude user") {
    sendmessage($from_id, "📌 Send the user's numeric ID", $backadmin, 'HTML');
    step("getidExceptio", $from_id);
} elseif ($user['step'] == "getidExceptio") {
    if (!rowExists('user', 'id', $text)) {
        sendmessage($from_id, "❌ User does not exist.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ User is already in the exception list", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions[] = $text;
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ User added to the list successfully.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "❌ Remove user from list") {
    sendmessage($from_id, "📌 Send the user's numeric ID to remove them from the list", $backadmin, 'HTML');
    step("getidExceptioremove", $from_id);
} elseif ($user['step'] == "getidExceptioremove") {
    if (!rowExists('user', 'id', $text)) {
        sendmessage($from_id, "❌ User does not exist.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (!in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ User is not in the exception list", $backadmin, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_Exceptions as $list) {
        if ($list == $text) {
            unset($list_Exceptions[$count]);
            break;
        }
        $count += 1;
    }
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ User removed from the list successfully.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "👁 Show user list") {
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (count($list_Exceptions) == 0) {
        sendmessage($from_id, "❌ There is no user in the list", null, 'HTML');
        return;
    }
    $list = "";
    foreach ($list_Exceptions as $list_ex) {
        $list .= $list_ex . "\n";
    }
    sendmessage($from_id, "People list👇", null, 'HTML');
    sendmessage($from_id, $list, null, 'HTML');
} elseif ($text == "Set API" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchent_floypay")['ValuePay'];
    $textaqayepardakht = "Send the received API here
        
Your current merchant code: $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('marchent_floypay', $from_id);
} elseif ($user['step'] == "marchent_floypay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $Swapinokey, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_floypay");
    step('home', $from_id);
}
