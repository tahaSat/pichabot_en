<?php
require_once 'config.php';
require_once __DIR__ . '/function.php';
if (!isset($from_id)) {
    $from_id = 0;
}
$from_id = (int) $from_id;
$build_admin_keyboards = request_user_is_admin();
if (!isset($setting) || !is_array($setting) || !isset($setting['keyboardmain'])) {
    $setting = select("setting", "*", null, null, "select");
}
if (!isset($textbotlang) || !is_array($textbotlang)) {
    $textbotlang = languagechange(__DIR__ . '/text.json');
}
if (!function_exists('getPaySettingValue')) {
    function getPaySettingValue($name)
    {
        $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
        return $result['ValuePay'] ?? null;
    }
}
//-----------------------------[  text panel  ]-------------------------------
if (!isset($datatextbot) || !is_array($datatextbot) || !array_key_exists('text_sell', $datatextbot)) {
$stmt = $pdo->prepare("SHOW TABLES LIKE 'textbot'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_dec_usertest' => '',
    'text_fq' => '',
    'accountwallet' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',
    'text_affiliates' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textcryptomus' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'aqayepardakht' => '',
    'zarinpal' => '',
    'tetraminator' => '',
    'text_fq' => '',
    'textpaymentnotverify' => "",
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_star_telegram' => "",
    'text_extend' => '',
    'textsnowpayment' => '',
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'text_wgdashboard' => '',
    'textafterpayibsng' => '',
    'textselectlocation' => '',
);
if ($table_exists) {
    $textdatabot = select("textbot", "*", null, null, "fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
}
$adminrulecheck = select("admin", "*", "id_admin", $from_id, "select");
if (!$adminrulecheck) {
    $adminrulecheck = array(
        'rule' => '',
    );
}
if (isset($user) && is_array($user) && isset($user['id'])) {
    $users = $user;
} else {
    $users = select("user", "*", "id", $from_id, "select");
}
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
        'agent' => '',
        'limit_usertest' => '',
        'Processing_value' => '',
        'Processing_value_four' => '',
        'cardpayment' => ""
    );
}
$admin_idss = select("admin", "*", "id_admin", $from_id, "count");
$keyboard = build_user_main_keyboard_markup($setting, $datatextbot, $textbotlang, $from_id, [
    'users' => $users,
    'admin_idss' => $admin_idss,
]);

$keyboardPanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => textbot_button_label('text_Discount', $datatextbot), 'callback_data' => "Discount"],
            ['text' => textbot_button_label('text_Add_Balance', $datatextbot), 'callback_data' => "Add_Balance"]
        ],
        [
            ['text' => '💸 Withdraw request', 'callback_data' => 'Wallet_Withdraw'],
        ],
        [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]],
    ],
    'resize_keyboard' => true
]);
if ($adminrulecheck['rule'] == "administrator") {
    $keyboardadmin = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['Admin']['Status']['btn']]],
            [['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel']], ['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel']]],
            [['text' => "⏳ Quick time-price setup"], ['text' => "🔋 Quick volume-price setup"]],
            [['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser']], ['text' => "🏬 Shop settings"]],
            [['text' => "💎 Finance"]],
            [['text' => "🤙 Support section"], ['text' => "📚 Guides section"]],
            [['text' => "📬 Bot reports"], ['text' => "🛠 Panel features"]],
            [['text' => "⚙️ General settings"], ['text' => "💵 Unverified receipts"]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ]);
}
if ($adminrulecheck['rule'] == "Seller") {
    $keyboardadmin = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['Admin']['Status']['btn']]],
            [['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser']]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ]);
}
if ($adminrulecheck['rule'] == "support") {
    $keyboardadmin = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser']], ['text' => "👁‍🗨 Search user"]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ]);
}
$CartManage = json_encode([
    'keyboard' => [
        [['text' => "🗂 Card-to-card gateway name"]],
        [['text' => "💳 Set card number"], ['text' => "❌ Remove card number"]],
        [['text' => "👤 Support username",], ['text' => "💳 Offline gateway in private chat"]],
        [['text' => "💰 Disable card-number display"], ['text' => "💰 Enable card-number display"]],
        [['text' => "♻️ Group card-number display"]],
        [['text' => "📄 Export users with card display on"]],
        [['text' => "♻️ Auto-confirm receipts"], ['text' => "💰 Card-to-card cashback"]],
        [['text' => "🔒 Show card-to-card after first payment"]],
        [['text' => "⬇️ Card-to-card minimum"], ['text' => "⬆️ Card-to-card maximum"]],
        [['text' => "📚 Set card-to-card guide"]],
        [['text' => "🤖 Confirm receipts without review"]],
        [['text' => "💳 Exclude user from auto-confirm"]],
        [['text' => "⏳ Auto-confirm without review delay"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$trnado = json_encode([
    'keyboard' => [
        [['text' => "🗂 IRR gateway 2 name"]],
        [['text' => "API T"]],
        [['text' => "Set API URL"]],
        [['text' => "💰 IRR gateway 2 cashback"]],
        [['text' => "⬇️ IRR gateway 2 minimum"], ['text' => "⬆️ IRR gateway 2 maximum"]],
        [['text' => "📚 Set IRR gateway 2 guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardzarinpal = json_encode([
    'keyboard' => [
        [['text' => "🗂 Zarinpal gateway name"], ['text' => "Zarinpal merchant"]],
        [['text' => "💰 Zarinpal cashback"]],
        [['text' => "⬇️ Zarinpal minimum"], ['text' => "⬆️ Zarinpal maximum"]],
        [['text' => "📚 Set Zarinpal guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtetraminator = json_encode([
    'keyboard' => [
        [['text' => "🗂 Tetraminator gateway name"]],
        [['text' => "💰 Tetraminator cashback"]],
        [['text' => "⬇️ Tetraminator minimum"], ['text' => "⬆️ Tetraminator maximum"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$aqayepardakht = json_encode([
    'keyboard' => [
        [['text' => "🗂 Aghaye Pardakht gateway name"]],
        [['text' => "Set Aghaye Pardakht merchant"], ['text' => "💰 Aghaye Pardakht cashback"]],
        [['text' => "⬇️ Aghaye Pardakht minimum"], ['text' => "⬆️ Aghaye Pardakht maximum"]],
        [['text' => "📚 Set Aghaye Pardakht guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$NowPaymentsManage = json_encode([
    'keyboard' => [
        [['text' => "🗂 Plisio gateway name"]],
        [['text' => "🧩 api plisio"], ['text' => "💰 Plisio cashback"]],
        [['text' => "⬇️ Plisio minimum"], ['text' => "⬆️ Plisio maximum"]],
        [['text' => "📚 Set Plisio guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$CryptomusManage = json_encode([
    'keyboard' => [
        [['text' => "🪪 Cryptomus Merchant UUID"], ['text' => "🔐 Cryptomus Payment API Key"]],
        [['text' => "⬇️ Cryptomus minimum USD"], ['text' => "⬆️ Cryptomus maximum USD"]],
        [['text' => "💰 Cryptomus cashback"], ['text' => "🗂 Cryptomus button text"]],
        [['text' => "📚 Set Cryptomus guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$setting_panel = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Feature status"]],
        [['text' => "📣 Bot reports"], ['text' => "📯 Channel settings"]],
        [['text' => "✅ Enable web panel"]],
        [['text' => "🗑 Optimize bot"]],
        [['text' => "📝 Bot text settings"], ['text' => "⌨️ Menu button settings"]],
        [['text' => "👨‍🔧 Admin section"]],
        [['text' => $textbotlang['Admin']['getlimitusertest']['setlimitbtn']]],
        [['text' => "💰 Agency membership fee"], ['text' => "🖼 QR code background"]],
        [['text' => "🔗 Re-webhook agent bots"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingaqayepardakht = getPaySettingValue("statusaqayepardakht");
$PaySettingpv = getPaySettingValue("Cartstatuspv");
$usernamecart = getPaySettingValue("CartDirect");
$Swapino = getPaySettingValue("statusSwapWallet");
$trnadoo = getPaySettingValue("statustarnado");
$paymentverify = getPaySettingValue("checkpaycartfirst");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Payment_report WHERE id_user = :user_id AND payment_Status = 'paid'");
$stmt->bindValue(':user_id', $from_id);
$stmt->execute();
$paymentexits = (int) $stmt->fetchColumn();
$zarinpal = getPaySettingValue("zarinpalstatus");
$tetraminator = getPaySettingValue("statustetraminator");
$affilnecurrency = getPaySettingValue("digistatus");
$arzireyali3 = getPaySettingValue("statusiranpay3");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$payment_status_cryptomus = getPaySettingValue("statuscryptomus");
$step_payment = [
    'inline_keyboard' => []
];
if ($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1) {
    if ($PaySettingpv == "oncardpv") {
        $step_payment['inline_keyboard'][] = [
            ['text' => textbot_button_label('carttocart', $datatextbot), 'url' => "https://t.me/$usernamecart"],
        ];
    } else {
        $step_payment['inline_keyboard'][] = [
            ['text' => textbot_button_label('carttocart', $datatextbot), 'callback_data' => "cart_to_offline"],
        ];
    }
}
if (($paymentexits == 0 && $paymentverify == "onpayverify"))
    unset($step_payment['inline_keyboard']);
if ($PaySettingnow == "onnowpayment") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('textnowpayment', $datatextbot), 'callback_data' => "plisio"]
    ];
}
if ($payment_status_nowpayment == "1") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('textsnowpayment', $datatextbot), 'callback_data' => "nowpayment"]
    ];
}
if ($payment_status_cryptomus === "oncryptomus") {
    $cryptomus_button_text = textbot_button_label('textcryptomus', $datatextbot);
    if ($cryptomus_button_text !== '') {
        $step_payment['inline_keyboard'][] = [
            ['text' => $cryptomus_button_text, 'callback_data' => "cryptomus"]
        ];
    }
}
if ($affilnecurrency == "ondigi") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('textnowpaymenttron', $datatextbot), 'callback_data' => "digitaltron"]
    ];
}
if ($Swapino == "onSwapinoBot") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('iranpay2', $datatextbot), 'callback_data' => "iranpay1"]
    ];
}
if ($trnadoo == "onternado") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('iranpay3', $datatextbot), 'callback_data' => "iranpay2"]
    ];
}
if ($arzireyali3 == "oniranpay3" && $paymentexits >= 2) {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('iranpay1', $datatextbot), 'callback_data' => "iranpay3"]
    ];
}
if ($PaySettingaqayepardakht == "onaqayepardakht") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('aqayepardakht', $datatextbot), 'callback_data' => "aqayepardakht"]
    ];
}
if ($zarinpal == "onzarinpal") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('zarinpal', $datatextbot), 'callback_data' => "zarinpal"]
    ];
}
if ($tetraminator == "ontetraminator") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('tetraminator', $datatextbot), 'callback_data' => "tetraminator"]
    ];
}
if ($paymentstatussnotverify == "onverifypay") {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('textpaymentnotverify', $datatextbot), 'callback_data' => "paymentnotverify"]
    ];
}
if (intval($paymentsstartelegram) == 1) {
    $step_payment['inline_keyboard'][] = [
        ['text' => textbot_button_label('text_star_telegram', $datatextbot), 'callback_data' => "startelegrams"]
    ];
}
$step_payment['inline_keyboard'][] = [
    ['text' => "❌ Close list", 'callback_data' => "colselist"]
];
$step_payment = json_encode($step_payment);
$keyboardhelpadmin = json_encode([
    'keyboard' => [
        [['text' => "📚 Add guide"], ['text' => "❌ Remove guide"]],
        [['text' => "✏️ Edit guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$shopkeyboard = json_encode([
    'keyboard' => [
        [['text' => "🛒 Shop feature status"]],
        [['text' => "🗂 Category management"], ['text' => "🛍 Product management"]],
        [['text' => "🎁 Create gift code"], ['text' => "❌ Remove gift code"]],
        [['text' => "🎁 Create discount code"], ['text' => "❌ Remove discount code"]],
        [['text' => "🎁 Invite campaigns"]],
        [['text' => "⬇️ Wholesale minimum balance"], ['text' => "🎁 Renewal cashback"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboard_Category_manage = json_encode([
    'keyboard' => [
        [['text' => "🛒 Add category"], ['text' => "❌ Remove category"]],
        [['text' => "✏️ Edit category"]],
        [['text' => "⬅️ Back to shop menu"]]
    ],
    'resize_keyboard' => true
]);
$keyboard_shop_manage = json_encode([
    'keyboard' => [
        [['text' => "🛍 Add product"], ['text' => "❌ Remove product"]],
        [['text' => "✏️ Edit product"]],
        [['text' => "⬆️ Bulk price increase"], ['text' => "⬇️ Bulk price decrease"]],
        [['text' => "⬅️ Back to shop menu"]]
    ],
    'resize_keyboard' => true
]);
if ($setting['inlinebtnmain'] == "oninline") {
    $confrimrolls = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ I accept the rules", 'callback_data' => "acceptrule"],
            ],
        ]
    ]);
} else {
    $confrimrolls = json_encode([
        'keyboard' => [
            [['text' => "✅ I accept the rules"]],
        ],
        'resize_keyboard' => true
    ]);
}
$request_contact = json_encode([
    'keyboard' => [
        [['text' => "☎️ Send phone number", 'request_contact' => true]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
]);
$Feature_status = json_encode([
    'keyboard' => [
        [['text' => "Account info feature"]],
        [['text' => "Test account feature"], ['text' => "Guides feature"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$channelkeyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['channel']['title']], ['text' => $textbotlang['Admin']['channel']['removechannelbtn']]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if ($setting['inlinebtnmain'] == "oninline") {
    $backuser = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]]
        ],
    ]);
} else {
    $backuser = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true,
        'input_field_placeholder' => "Tap the button below to go back"
    ]);
}
$backadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' => "Tap the button below to go back"
]);
if ($build_admin_keyboards) {
//------------------  [ list panel ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);
    //------------------  [ list panel inline ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    $list_marzban_panel_edit_product = ['inline_keyboard' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' => $row['name_panel'], 'callback_data' => 'locationedit_' . $row['code_panel']]];
    }
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' => "All panels", 'callback_data' => 'locationedit_all']];
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' => "▶️ Previous menu", 'callback_data' => 'backproductadmin']];
    $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
}
//------------------  [ list channel ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_channels = [];
$list_channels_join = [
    'keyboard' => [
        [
            ['text' => $textbotlang['Admin']['backadmin']],
            ['text' => $textbotlang['Admin']['backmenu']]
        ]
    ],
    'resize_keyboard' => true,
];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM channels");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_channels[] = [$row['link']];
    }
    $list_channels_join = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_channels as $button) {
        $list_channels_join['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_channels_join['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
}
$list_channels_joins = json_encode($list_channels_join);
//------------------  [ list card ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_card = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM card_number");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_card[] = [$row['cardnumber']];
    }
    $list_card_remove = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_card as $button) {
        $list_card_remove['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_card_remove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    $list_card_remove = json_encode($list_card_remove);
}
}
$textbot = json_encode([
    'keyboard' => [
        [['text' => "Start text"], ['text' => "Purchased services button"]],
        [['text' => "Test account button"], ['text' => "FAQ button"]],
        [['text' => "📚 Guides button text"], ['text' => "☎️ Support button text"]],
        [['text' => "Add balance button"], ['text' => "Referral button text"]],
        [['text' => "Buy subscription button text"], ['text' => "Tariff list button text"]],
        [['text' => "Tariff list description"]],
        [['text' => "🛒 Purchase flow texts"]],
        [['text' => "Wallet button text"], ['text' => "Invoice preview text"]],
        [['text' => "📝 Required-join description"]],
        [['text' => "📝 FAQ description"]],
        [['text' => "⚖️ Rules text"], ['text' => "After-purchase text"]],
        [['text' => "After-purchase text (ibsng)"], ['text' => "Renew button"]],
        [['text' => "After test-account text"], ['text' => "Test cron text"]],
        [['text' => "After manual-account text"]],
        [['text' => "After WGDashboard account text"]],
        [['text' => "Location selection text"], ['text' => "Gift code button text"]],
        [['text' => "Agency request text"], ['text' => "Agency button text"]],
        [['text' => "Lucky wheel button text"], ['text' => "Card-to-card text"]],
        [['text' => "Auto card-to-card text"]],
        [['text' => "Agency request description"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$textbot_purchase = json_encode([
    'keyboard' => [
        [['text' => "Location selection text"], ['text' => "Category selection text"]],
        [['text' => "Service selection text"], ['text' => "Service selection text (first)"]],
        [['text' => "Duration selection text"], ['text' => "Purchase note text"]],
        [['text' => "Custom volume request text"]],
        [['text' => "Custom duration selection text"]],
        [['text' => "Invalid volume text"], ['text' => "Invoice preview text"]],
        [['text' => "Username selection text"]],
        [['text' => "Panel description (after select)"]],
        [['text' => "Custom service button text"]],
        [['text' => "Inside-category description"]],
        [['text' => "🔙 Back to text settings"], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if ($build_admin_keyboards) {
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $getdataprotocol = select("protocol", "*", null, null, "fetchAll");
    $protocol = [];
    foreach ($getdataprotocol as $result) {
        $protocol[] = [['text' => $result['NameProtocol']]];
    }
    $protocol[] = [['text' => $textbotlang['Admin']['backadmin']]];
    $keyboardprotocollist = json_encode(['resize_keyboard' => true, 'keyboard' => $protocol]);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product'], $row['emoji_id'] ?? ''];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            telegram_button_with_icon(['text' => $button[0]], $button[1] ?? '')
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
}

function keyboard_admin_addorder_products(string $panelName, string $agent = 'f'): string
{
    global $pdo, $textbotlang;
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin'] ?? 'Back'],
    ];
    $panel = select("marzban_panel", "*", "name_panel", $panelName, "select");
    if (is_array($panel) && ($panel['type'] ?? '') !== 'Manualsale') {
        $list_product['keyboard'][] = [
            ['text' => panel_custom_button_text($panel)],
        ];
    }
    $stmt = $pdo->prepare("SELECT name_product, emoji_id FROM product WHERE Location = :loc OR Location = '/all' ORDER BY name_product");
    $stmt->execute([':loc' => $panelName]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_product['keyboard'][] = [
            telegram_button_with_icon(['text' => $row['name_product']], $row['emoji_id'] ?? ''),
        ];
    }
    return json_encode($list_product);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin = json_encode($list_Discount);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Inboundkeyboard = [];
    $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Processing_value', $users['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $Inboundkeyboard[] = [$row['NameInbound']];
        }

    }
    $list_Inbound = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Inboundkeyboard as $button) {
        $list_Inbound['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_Inbound['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Inbound_list_admin = json_encode($list_Inbound);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'DiscountSell'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discountsell['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
}
function KeyboardPayment(string $backCallback = 'backuser', bool $withDiscount = true, string $confirmCallback = 'confirmandgetservice'): string
{
    global $textbotlang;
    $rows = [
        [['text' => "💰 Pay and get service", 'callback_data' => $confirmCallback]],
    ];
    if ($withDiscount) {
        $rows[] = [['text' => "🎁 Apply discount code", 'callback_data' => "aptdc"]];
    }
    $rows[] = [['text' => $textbotlang['users']['backbtn'], 'callback_data' => $backCallback]];
    return json_encode(['inline_keyboard' => $rows]);
}

function purchase_inline_back_keyboard(string $callback): string
{
    global $textbotlang;
    return json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['stateus']['backinfo'] ?? '🏠 Back', 'callback_data' => $callback]],
        ],
    ]);
}

$payment = KeyboardPayment();
$paymentom = json_encode([
    'inline_keyboard' => [
        [['text' => "💰 Pay and get service", 'callback_data' => "confirmandgetservice"]],
        [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]]
    ]
]);
$change_product = json_encode([
    'keyboard' => [
        [['text' => "Price"], ['text' => "Volume"], ['text' => "Time"]],
        [['text' => "Product name"], ['text' => "User type"]],
        [['text' => "Volume reset type"], ['text' => "Note"]],
        [['text' => "Product location"], ['text' => "Category"]],
        [['text' => "Device limit (HWID)"]],
        [['text' => "🎛 Inbound settings"], ['text' => "Show for first purchase"]],
        [['text' => "Hide panel"], ['text' => "Clear all hidden panels"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"], ['text' => "vmess"], ['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionMarzban = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "⚙️ Protocol and inbound settings"]],
        [['text' => "🔋 Renewal method"], ['text' => "💡 Username generation method"]],
        [['text' => "🚨 Account creation limit"], ['text' => "📍 Change user group"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "⚙️ Custom volume price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⏳ Custom time price"]],
        [['text' => "🌍 Location-change price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "⚙️ Disabled-account inbound"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionibsng = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => '🎛 Set group name']],
        [['text' => "🔋 Renewal method"], ['text' => "💡 Username generation method"]],
        [['text' => "🚨 Account creation limit"], ['text' => "📍 Change user group"]],
        [['text' => "⚙️ Custom volume price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⏳ Custom time price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$option_mikrotik = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => '🎛 Set group name']],
        [['text' => "🔋 Renewal method"], ['text' => "💡 Username generation method"]],
        [['text' => "🚨 Account creation limit"], ['text' => "📍 Change user group"]],
        [['text' => "⚙️ Custom volume price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⏳ Custom time price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$options_ui = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "⚙️ Protocol and inbound settings"]],
        [['text' => "🔋 Renewal method"], ['text' => "💡 Username generation method"]],
        [['text' => "🚨 Account creation limit"], ['text' => "📍 Change user group"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "⚙️ Custom volume price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⏳ Custom time price"]],
        [['text' => "🌍 Location-change price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "⚙️ Disabled-account inbound"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionwg = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "💎 Set inbound ID"]],
        [['text' => "🔋 Renewal method"], ['text' => "💡 Username generation method"]],
        [['text' => "🚨 Account creation limit"], ['text' => "📍 Change user group"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "⚙️ Custom volume price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⏳ Custom time price"]],
        [['text' => "🌍 Location-change price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "⚙️ Disabled-account inbound"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionmarzneshin = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "🔋 Renewal method"]],
        [['text' => "💡 Username generation method"]],
        [['text' => "⚙️ Service settings"], ['text' => "🚨 Account creation limit"]],
        [['text' => "📍 Change user group"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "🌍 Location-change price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⚙️ Custom volume price"]],
        [['text' => "⏳ Custom time price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionManualsale = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "💡 Username generation method"]],
        [['text' => "🚨 Account creation limit"], ['text' => "📍 Change user group"]],
        [['text' => "➕ Add config"], ['text' => "❌ Remove config"]],
        [['text' => "✏️ Edit config"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionX_ui_single = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "🔋 Renewal method"]],
        [['text' => "💎 Set inbound ID"]],
        [['text' => "💡 Username generation method"], ['text' => '🔗 Subscription domain']],
        [['text' => "📍 Change user group"], ['text' => "🚨 Account creation limit"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "🌍 Location-change price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⚙️ Custom volume price"]],
        [['text' => "⏳ Custom time price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionalireza_single = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔐 Edit password"], ['text' => "👤 Edit username"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "🔋 Renewal method"]],
        [['text' => "💎 Set inbound ID"]],
        [['text' => "💡 Username generation method"]],
        [['text' => '🔗 Subscription domain']],
        [['text' => "📍 Change user group"], ['text' => "🚨 Account creation limit"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "🌍 Location-change price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⚙️ Custom volume price"]],
        [['text' => "⏳ Custom time price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionhiddfy = json_encode([
    'keyboard' => [
        [['text' => "⚙️ Panel feature status"]],
        [['text' => "✍️ Panel name"], ['text' => "❌ Remove panel"]],
        [['text' => "🔗 Edit panel URL"], ['text' => "🔋 Renewal method"]],
        [['text' => "📍 Change user group"]],
        [['text' => "💡 Username generation method"]],
        [['text' => '🔗 Subscription domain']],
        [['text' => "🚨 Account creation limit"], ['text' => "🔗 uuid admin"]],
        [['text' => "⏳ Test service duration"], ['text' => "💾 Test account volume"]],
        [['text' => "🌍 Location-change price"], ['text' => "➕ Extra volume price"]],
        [['text' => "⏳ Extra time price"], ['text' => "⚙️ Custom volume price"]],
        [['text' => "⏳ Custom time price"]],
        [['text' => "📍 Custom volume minimum"], ['text' => "📍 Custom volume maximum"]],
        [['text' => "📍 Custom time minimum"], ['text' => "📍 Custom time maximum"]],
        [['text' => "🫣 Hide panel for a user"]],
        [['text' => "❌ Remove user from hidden list"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
if ($setting['statussupportpv'] == "onpvsupport") {
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => textbot_button_label('text_fq', $datatextbot), 'callback_data' => "fqQuestions"],
                ['text' => "🎟 Message support", 'url' => "https://t.me/{$setting['id_support']}"],
            ],
            [
                ['text' => "🔙 Back to main menu", 'callback_data' => "backuser"]
            ],

        ]
    ]);
} else {
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => textbot_button_label('text_fq', $datatextbot), 'callback_data' => "fqQuestions"],
                ['text' => "🎟 Message support", 'callback_data' => "support"],
            ],
            [
                ['text' => "🔙 Back to main menu", 'callback_data' => "backuser"]
            ],

        ]
    ]);
}
$adminrule = json_encode([
    'keyboard' => [
        [['text' => "administrator"], ['text' => "Seller"], ['text' => "support"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$affiliates = json_encode([
    'keyboard' => [
        [['text' => "🧮 Set referral percent"]],
        [['text' => "🏞 Set referral banner"]],
        [['text' => "🎁 Commission after purchase"], ['text' => "🎁 Start gift"]],
        [['text' => "🎉 First-purchase commission only"]],
        [['text' => "🌟 Start gift amount"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardexportdata = json_encode([
    'keyboard' => [
        [['text' => "Export users"], ['text' => "Export orders"]],
        [['text' => "Export payments"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$helpedit = json_encode([
    'keyboard' => [
        [['text' => "Edit name"], ['text' => "Edit description"]],
        [['text' => "Edit media"], ['text' => "Edit category"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Methodextend = json_encode([
    'keyboard' => [
        [['text' => "ریست حجم و زمان"]],
        [['text' => "اضافه شدن زمان و حجم به ماه بعد"]],
        [['text' => "ریست زمان و اضافه کردن حجم قبلی"]],
        [['text' => "ریست شدن حجم و اضافه شدن زمان"]],
        [['text' => "اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtimereset = json_encode([
    'keyboard' => [
        [['text' => "no_reset"], ['text' => "day"], ['text' => "week"]],
        [['text' => "month"], ['text' => "year"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtypepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "Marzban", 'callback_data' => "typepanel#marzban"],
            ['text' => "Marzneshin", 'callback_data' => "typepanel#marzneshin"]
        ],
        [
            ['text' => 'Sanaei single-port', 'callback_data' => 'typepanel#x-ui_single'],
            ['text' => 'Alireza single-port', 'callback_data' => 'typepanel#alireza_single']
        ],
        [
            ['text' => "Manual sale", 'callback_data' => 'typepanel#Manualsale'],
            ['text' => "Hiddify", 'callback_data' => 'typepanel#hiddify'],
        ],
        [
            ['text' => "WGDashboard", 'callback_data' => 'typepanel#WGDashboard'],
            ['text' => "s_ui", 'callback_data' => 'typepanel#s_ui']
        ],
        [
            ['text' => "ibsng", 'callback_data' => 'typepanel#ibsng'],
            ['text' => "MikroTik", 'callback_data' => 'typepanel#mikrotik']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'admin']
        ]
    ],
]);

$panelechekc = select("marzban_panel", "*", "MethodUsername", "متن دلخواه نماینده + عدد ترتیبی", "count");
if ($setting['inlinebtnmain'] == "oninline") {
    $keyboardagent = [
        'inline_keyboard' => [
            [
                ['text' => "🗂 Bulk buy", 'callback_data' => "kharidanbuh"],
                ['text' => "👤 Choose a custom name", 'callback_data' => "selectname"]
            ],
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
            ]
        ],
        'resize_keyboard' => true
    ];
    if ($panelechekc == 0) {
        unset($keyboardagent['inline_keyboard'][0][1]);
    }
} else {
    $keyboardagent = [
        'keyboard' => [
            [['text' => "🗂 Bulk buy"], ['text' => "👤 Choose a custom name"]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ];
    if ($panelechekc == 0) {
        unset($keyboardagent['keyboard'][0][1]);
    }
}
$keyboardagent = json_encode($keyboardagent);
$Swapinokey = json_encode([
    'keyboard' => [
        [['text' => "Set API"]],
        [['text' => "🗂 IRR gateway name"]],
        [['text' => "💰 IRR cashback"], ['text' => "📚 Set IRR gateway 1 guide"]],
        [['text' => "⬇️ IRR minimum"], ['text' => "⬆️ IRR maximum"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => "🗂 Offline crypto gateway name"]],
        [['text' => "⬇️ Offline crypto minimum"], ['text' => "⬆️ Offline crypto maximum"]],
        [['text' => "📚 Set offline crypto guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = json_encode([
    'keyboard' => [
        [['text' => "🔧 Create manual config"], ['text' => "🖥 Node management"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathx_ui = json_encode([
    'keyboard' => [
        [['text' => "🔧 Create manual config"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$configedit = json_encode([
    'keyboard' => [
        [['text' => "Config details"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$iranpaykeyboard = json_encode([
    'keyboard' => [
        [['text' => "IRR gateway API"]],
        [['text' => "🗂 IRR gateway 3 name"]],
        [['text' => "⬇️ IRR gateway 3 minimum"], ['text' => "⬆️ IRR gateway 3 maximum"]],
        [['text' => "💰 IRR gateway 3 cashback"]],
        [['text' => "📚 Set IRR gateway 3 guide"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$supportcenter = json_encode([
    'keyboard' => [
        [['text' => "👤 Set support ID"]],
        [['text' => "🔼 Add department"], ['text' => "🔽 Remove department"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$active_panell = json_encode([
    'keyboard' => [
        [['text' => "📣 Bot reports"]],
    ],
    'resize_keyboard' => true
]);
$lottery = json_encode([
    'keyboard' => [
        [['text' => "1️⃣ Set 1st-place prize"], ['text' => "2️⃣ Set 2nd-place prize"]],
        [['text' => "3️⃣ Set 3rd-place prize"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$wheelkeyboard = json_encode([
    'keyboard' => [
        [['text' => "🎲 User win amount"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$keyboardlinkapp = json_encode([
    'keyboard' => [
        [['text' => "🔗 Add app"], ['text' => "❌ Remove app"]],
        [['text' => "✏️ Edit app"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
function keyboard_resolve_user($user = null)
{
    if (is_array($user) && $user !== []) {
        return $user;
    }
    if (isset($GLOBALS['user']) && is_array($GLOBALS['user']) && isset($GLOBALS['user']['id'])) {
        return $GLOBALS['user'];
    }
    if (isset($GLOBALS['users']) && is_array($GLOBALS['users'])) {
        return $GLOBALS['users'];
    }
    return [];
}

function keyboard_fetch_active_panels($agent)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all')");
    $stmt->execute([':agent' => $agent]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function keyboard_count_active_panels(): int
{
    global $pdo;
    return (int) $pdo->query("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'")->fetchColumn();
}

function keyboard_help_os_list()
{
    global $pdo, $textbotlang;
    $help_arrke = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    try {
        $stmt = $pdo->query("SELECT name_os FROM help");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $help_arrke['keyboard'][] = [['text' => $row['name_os']]];
            }
        }
    } catch (PDOException $e) {
    }
    $help_arrke['keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn']],
    ];
    return json_encode($help_arrke);
}

function keyboard_help_category()
{
    global $pdo, $setting, $textbotlang;
    $helpcwtgory = ['inline_keyboard' => []];
    $datahelp = [];
    try {
        $stmt = $pdo->query("SELECT category FROM help");
        if ($stmt) {
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (in_array($result['category'], $datahelp)) {
                    continue;
                }
                if ($result['category'] == null) {
                    continue;
                }
                $datahelp[] = $result['category'];
                $helpcwtgory['inline_keyboard'][] = [
                    ['text' => $result['category'], 'callback_data' => "helpctgoryـ{$result['category']}"]
                ];
            }
        }
    } catch (PDOException $e) {
    }
    if (($setting['linkappstatus'] ?? '') == "1") {
        $helpcwtgory['inline_keyboard'][] = [
            ['text' => "🔗 App download link", 'callback_data' => "linkappdownlod"],
        ];
    }
    $helpcwtgory['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
    ];
    return json_encode($helpcwtgory);
}

function keyboard_help_app_links()
{
    global $pdo, $textbotlang;
    $helpapp = ['inline_keyboard' => []];
    try {
        $stmt = $pdo->query("SELECT name, link FROM app");
        if ($stmt) {
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $helpapp['inline_keyboard'][] = [
                    ['text' => $result['name'], 'url' => $result['link']]
                ];
            }
        }
    } catch (PDOException $e) {
    }
    $helpapp['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
    ];
    return json_encode($helpapp);
}

function keyboard_help_app_remove()
{
    global $pdo, $textbotlang;
    $helpappremove = ['keyboard' => [], 'resize_keyboard' => true];
    try {
        $stmt = $pdo->query("SELECT name FROM app");
        if ($stmt) {
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $helpappremove['keyboard'][] = [
                    ['text' => $result['name']],
                ];
            }
        }
    } catch (PDOException $e) {
    }
    $helpappremove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    return json_encode($helpappremove);
}

function keyboard_panels_buy($user = null)
{
    global $pdo, $from_id, $setting, $textbotlang;
    $user = keyboard_resolve_user($user);
    $agent = $user['agent'] ?? 'f';
    $step = $user['step'] ?? '';
    $uid = $from_id ?: ($user['id'] ?? 0);
    $panels = keyboard_fetch_active_panels($agent);
    $use_grid = keyboard_count_active_panels() > 10;
    $list = ['inline_keyboard' => []];
    $temp_row = [];
    $notuser = ($step == "getusernameinfo");
    foreach ($panels as $result) {
        if (panel_is_hidden_from_user($result, $uid)) {
            continue;
        }
        if (($result['type'] ?? '') == "Manualsale" && !panel_manualsale_in_stock($result['code_panel'])) {
            continue;
        }
        $cb = $notuser ? "locationnotuser_{$result['code_panel']}" : "location_{$result['code_panel']}";
        $btn = ['text' => $result['name_panel'], 'callback_data' => $cb];
        if ($use_grid) {
            $temp_row[] = $btn;
            if (count($temp_row) == 2) {
                $list['inline_keyboard'][] = $temp_row;
                $temp_row = [];
            }
        } else {
            $list['inline_keyboard'][] = [$btn];
        }
    }
    if ($use_grid && !empty($temp_row)) {
        $list['inline_keyboard'][] = $temp_row;
    }
    $statusnote = false;
    if (($setting['statusnamecustom'] ?? '') == 'onnamecustom') {
        $statusnote = true;
    }
    if (($setting['statusnoteforf'] ?? '') == "0" && $agent == "f") {
        $statusnote = false;
    }
    $list['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => $statusnote ? "buyback" : "backuser"],
    ];
    return json_encode($list);
}

function keyboard_panels_bulk($user = null)
{
    global $from_id, $textbotlang;
    $user = keyboard_resolve_user($user);
    $agent = $user['agent'] ?? 'f';
    $uid = $from_id ?: ($user['id'] ?? 0);
    $list = ['inline_keyboard' => []];
    foreach (keyboard_fetch_active_panels($agent) as $result) {
        if (panel_is_hidden_from_user($result, $uid)) {
            continue;
        }
        $list['inline_keyboard'][] = [
            ['text' => $result['name_panel'], 'callback_data' => "locationom_{$result['code_panel']}"]
        ];
    }
    $list['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
    ];
    return json_encode($list);
}

function keyboard_panels_changeloc($user = null)
{
    global $pdo, $from_id, $textbotlang;
    $user = keyboard_resolve_user($user);
    $agent = $user['agent'] ?? 'f';
    $uid = $from_id ?: ($user['id'] ?? 0);
    $exclude = $user['Processing_value_four'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') AND name_panel != :name_panel");
    $stmt->bindValue(':name_panel', $exclude, PDO::PARAM_STR);
    $stmt->bindValue(':agent', $agent, PDO::PARAM_STR);
    $stmt->execute();
    $panels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($panels)) {
        $panels = [];
    }
    $use_grid = keyboard_count_active_panels() > 10;
    $list = ['inline_keyboard' => []];
    $temp_row = [];
    foreach ($panels as $result) {
        if (panel_is_hidden_from_user($result, $uid)) {
            continue;
        }
        $btn = ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"];
        if ($use_grid) {
            $temp_row[] = $btn;
            if (count($temp_row) == 2) {
                $list['inline_keyboard'][] = $temp_row;
                $temp_row = [];
            }
        } else {
            $list['inline_keyboard'][] = [$btn];
        }
    }
    if ($use_grid && !empty($temp_row)) {
        $list['inline_keyboard'][] = $temp_row;
    }
    $list['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backorder"],
    ];
    return json_encode($list);
}

function keyboard_panels_usertest($user = null)
{
    global $pdo, $from_id, $textbotlang;
    $user = keyboard_resolve_user($user);
    $agent = $user['agent'] ?? 'f';
    $uid = $from_id ?: ($user['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = :agent OR agent = 'all')");
    $stmt->bindValue(':agent', $agent, PDO::PARAM_STR);
    $stmt->execute();
    $list = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (panel_is_hidden_from_user($result, $uid)) {
            continue;
        }
        $list['inline_keyboard'][] = [
            ['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"]
        ];
    }
    $list['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
    ];
    return json_encode($list);
}

function keyboard_departman_admin()
{
    global $pdo, $textbotlang;
    $departemans = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    try {
        $stmt = $pdo->query("SELECT name_departman FROM departman");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $departemans['keyboard'][] = [
                    ['text' => department_button_label($row['name_departman'])]
                ];
            }
        }
    } catch (PDOException $e) {
    }
    $departemans['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu']]
    ];
    return json_encode($departemans);
}

function keyboard_departman_user()
{
    global $pdo, $textbotlang;
    $list_departman = ['inline_keyboard' => []];
    try {
        $stmt = $pdo->query("SELECT id, name_departman FROM departman");
        if ($stmt) {
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list_departman['inline_keyboard'][] = [
                    ['text' => department_button_label($result['name_departman']), 'callback_data' => "departman_{$result['id']}"]
                ];
            }
        }
    } catch (PDOException $e) {
    }
    $list_departman['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
    ];
    return json_encode($list_departman);
}

function KeyboardProduct($location, $query, $pricediscount, $datakeyboard, $statuscustom = false, $backuser = "backuser", $valuetow = null, $customvolume = "customsellvolume")
{
    global $pdo, $textbotlang, $from_id, $user;
    ensure_shop_button_emoji_columns();
    $product = ['inline_keyboard' => []];
    $shopShow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select");
    $statusshowprice = is_array($shopShow) ? ($shopShow['value'] ?? '') : '';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    if ($valuetow != null) {
        $valuetow = "-$valuetow";
    } else {
        $valuetow = "";
    }
    $isAgentN = (($user['agent'] ?? '') === 'n');
    foreach (sortProductsByOrder($stmt->fetchAll(PDO::FETCH_ASSOC)) as $result) {
        $hide_panel = json_decode($result['hide_panel'] ?? '[]', true);
        if (!is_array($hide_panel)) {
            $hide_panel = [];
        }
        if (in_array($location, $hide_panel, true)) {
            continue;
        }
        if (!product_category_is_active($result)) {
            continue;
        }
        $stmts2 = $pdo->prepare("SELECT * FROM invoice WHERE Status != 'Unpaid' AND id_user = :id_user");
        $stmts2->bindValue(':id_user', $from_id);
        $stmts2->execute();
        $countorder = $stmts2->rowCount();
        if ($result['one_buy_status'] == "1" && $countorder != 0)
            continue;
        $discountApplied = false;
        if ($isAgentN) {
            $result['price_product'] = agent_wholesale_cost($user, (int) ($result['Volume_constraint'] ?? 0));
            $namekeyboard = $result['name_product'] . " - " . format_money_display($result['price_product']);
        } else {
            $priceInfo = product_discount_payable($result['price_product'], $result['code_product'] ?? '', $pricediscount, $user);
            $result['price_product'] = $priceInfo['payable'];
            $discountApplied = !empty($priceInfo['applied']);
            $displayName = (string) ($result['name_product'] ?? '');
            if ($discountApplied) {
                $displayName = product_discount_rewrite_name(
                    $displayName,
                    $priceInfo['original'],
                    $priceInfo['payable'],
                    false
                );
            }
            $result['name_product'] = $displayName;
            $namekeyboard = $displayName . " - " . product_discount_format_button($priceInfo['original'], $priceInfo['payable'], (bool) $priceInfo['applied']);
        }
        if ($statusshowprice == "onshowprice") {
            $result['name_product'] = $namekeyboard;
        }
        $product['inline_keyboard'][] = [
            telegram_button_with_icon(
                ['text' => $result['name_product'], 'callback_data' => "{$datakeyboard}{$result['code_product']}{$valuetow}"],
                product_discount_button_emoji($result['emoji_id'] ?? '', $discountApplied)
            )
        ];
    }
    if ($statuscustom) {
        $panelRow = select("marzban_panel", "*", "name_panel", $location, "select");
        $customBtn = panel_custom_service_inline_button(is_array($panelRow) ? $panelRow : [], $customvolume);
        $product['inline_keyboard'][] = [$customBtn];
    }
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser],
    ];
    return json_encode($product);
}
function KeyboardCategory($location, $agent, $backuser = "backuser", $agentUserId = null, $options = [])
{
    global $pdo, $textbotlang, $from_id;
    ensure_shop_button_emoji_columns();
    if (!is_array($options)) {
        $options = [];
    }
    $callback_prefix = (string) ($options['callback_prefix'] ?? 'categorynames_');
    $includeCustomVolume = array_key_exists('custom_volume', $options) ? (bool) $options['custom_volume'] : true;
    $productExtraSql = trim((string) ($options['product_extra_sql'] ?? ''));
    $uid = $agentUserId !== null ? $agentUserId : $from_id;
    $accessSql = agent_product_access_sql($agent, $uid);
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = ['inline_keyboard' => [],];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!category_is_active($row)) {
            continue;
        }
        $productSql = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND category = :category AND {$accessSql}";
        if ($productExtraSql !== '') {
            $productSql .= ' ' . $productExtraSql;
        }
        $stmts = $pdo->prepare($productSql);
        $stmts->bindParam(':location', $location, PDO::PARAM_STR);
        $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
        $stmts->execute();
        $visibleCount = 0;
        foreach ($stmts->fetchAll(PDO::FETCH_ASSOC) as $prodRow) {
            $hide_panel = json_decode($prodRow['hide_panel'] ?? '[]', true);
            if (!is_array($hide_panel)) {
                $hide_panel = [];
            }
            if (in_array($location, $hide_panel, true)) {
                continue;
            }
            $visibleCount++;
            break;
        }
        if ($visibleCount === 0) {
            continue;
        }
        $list_category['inline_keyboard'][] = [telegram_button_with_icon(
            ['text' => $row['remark'], 'callback_data' => $callback_prefix . $row['id']],
            $row['emoji_id'] ?? ''
        )];
    }
    $panel = select("marzban_panel", "*", "name_panel", $location, "select");
    if ($includeCustomVolume && is_array($panel) && panel_custom_enabled($panel, (string) $agent)) {
        $list_category['inline_keyboard'][] = [
            panel_custom_service_inline_button($panel, 'customsellvolume'),
        ];
    }
    $list_category['inline_keyboard'][] = [
        ['text' => "▶️ Previous menu", "callback_data" => $backuser],
    ];
    return json_encode($list_category);
}

function keyboardTimeCategory($name_panel, $agent, $callback_data = "producttime_", $callback_data_back = "backuser", $statuscustomvolume = false, $statusbtnextend = false)
{
    global $pdo, $textbotlang, $from_id;
    $accessSql = agent_product_access_sql($agent, $from_id);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :name_panel OR Location = '/all') AND {$accessSql}");
    $stmt->bindValue(':name_panel', $name_panel, PDO::PARAM_STR);
    $stmt->execute();
    $montheproduct = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $prodRow) {
        if (!product_category_is_active($prodRow)) {
            continue;
        }
        $montheproduct[] = (string) ($prodRow['Service_time'] ?? '');
    }
    $montheproduct = array_flip(array_flip($montheproduct));
    $monthkeyboard = ['inline_keyboard' => []];
    if (in_array("1", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['1day'], 'callback_data' => "{$callback_data}1"]
        ];
    }
    if (in_array("7", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['7day'], 'callback_data' => "{$callback_data}7"]
        ];
    }
    if (in_array("31", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}31"]
        ];
    }
    if (in_array("30", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}30"]
        ];
    }
    if (in_array("61", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}61"]
        ];
    }
    if (in_array("60", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}60"]
        ];
    }
    if (in_array("91", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}91"]
        ];
    }
    if (in_array("90", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}90"]
        ];
    }
    if (in_array("121", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}121"]
        ];
    }
    if (in_array("120", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}120"]
        ];
    }
    if (in_array("181", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}181"]
        ];
    }
    if (in_array("180", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}180"]
        ];
    }
    if (in_array("365", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['365'], 'callback_data' => "{$callback_data}365"]
        ];
    }
    if (in_array("0", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['month']['unlimited'], 'callback_data' => "{$callback_data}0"]
        ];
    }
    if ($statusbtnextend)
        $monthkeyboard['inline_keyboard'][] = [['text' => "♻️ Renew current plan", 'callback_data' => "exntedagei"]];
    if ($statuscustomvolume == true) {
        $panelForCustom = select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
        $monthkeyboard['inline_keyboard'][] = [
            panel_custom_service_inline_button(is_array($panelForCustom) ? $panelForCustom : [], 'customsellvolume'),
        ];
    }
    $monthkeyboard['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $callback_data_back]
    ];
    return json_encode($monthkeyboard);
}
$Startelegram = json_encode([
    'keyboard' => [
        [['text' => "🗂 Stars gateway name"]],
        [['text' => "💰 Stars cashback"], ['text' => "📚 Set Stars guide"]],
        [['text' => "⬇️ Stars minimum"], ['text' => "⬆️ Stars maximum"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardchangelimit = json_encode([
    'keyboard' => [
        [['text' => "🆓 Free limit"], ['text' => "↙️ Overall limit"]],
        [['text' => "🔄 Reset all-user limits"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategoryadmin()
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [telegram_button_with_icon(
            ['text' => $row['remark']],
            $row['emoji_id'] ?? ''
        )];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    return json_encode($list_category);
}
$nowpayment_setting_keyboard = json_encode([
    'keyboard' => [
        [['text' => "API NOWPAYMENT"], ['text' => "🗂 NowPayments gateway name"]],
        [['text' => "💰 NowPayments cashback"], ['text' => "📚 Set NowPayments guide"]],
        [['text' => "⬇️ NowPayments minimum"], ['text' => "⬆️ NowPayments maximum"]],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Exception_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => "➕ Exclude user"], ['text' => "❌ Remove user from list"]],
        [['text' => "👁 Show user list"]],
        [['text' => "▶️ Back to card settings"]]
    ],
    'resize_keyboard' => true
]);
function keyboard_config($config_split, $id_invoice, $back_active = true)
{
    global $textbotlang;
    $keyboard_config = ['inline_keyboard' => []];
    $keyboard_config['inline_keyboard'][] = [
        ['text' => "⚙️ Config", 'callback_data' => "none"],
        ['text' => "✏️ Config name", 'callback_data' => "none"],
    ];
    for ($i = 0; $i < count($config_split); $i++) {
        $config = $config_split[$i];
        $split_config = explode("://", $config);
        $type_prtocol = $split_config[0];
        $split_config = $split_config[1];
        if (isBase64($split_config)) {
            $split_config = base64_decode($split_config);
        }
        if ($type_prtocol == "vmess") {
            $split_config = json_decode($split_config, true)['ps'];
        } elseif ($type_prtocol == "ss") {
            $split_config = $split_config;
            $split_config = explode("#", $split_config)[1];
        } else {
            $split_config = explode("#", $split_config)[1];
        }
        $keyboard_config['inline_keyboard'][] = [
            ['text' => "Get config", 'callback_data' => "configget_{$id_invoice}_$i"],
            ['text' => urldecode($split_config), 'callback_data' => "none"],
        ];

    }
    $keyboard_config['inline_keyboard'][] = [['text' => "⚙️ Get all configs", 'callback_data' => "configget_$id_invoice" . "_1520"]];
    if ($back_active) {
        $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"]];
    }
    return json_encode($keyboard_config);
}
$keyboard_buy = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "🛍 Buy subscription", 'callback_data' => 'buy'],
        ],
    ]
]);
$keyboard_stat = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "⏱️ All-time stats", 'callback_data' => 'stat_all_bot'],
        ],
        [
            ['text' => "⏱️ Last hour", 'callback_data' => 'hoursago_stat'],
        ],
        [
            ['text' => "⛅️ Today", 'callback_data' => 'today_stat'],
            ['text' => "☀️ Yesterday", 'callback_data' => 'yesterday_stat'],
        ],
        [
            ['text' => "☀️ Current month", 'callback_data' => 'month_current_stat'],
            ['text' => "⛅️ Previous month", 'callback_data' => 'month_old_stat'],
        ],
        [
            ['text' => "🗓 Stats for a specific date", 'callback_data' => 'view_stat_time'],
        ]
    ]
]);