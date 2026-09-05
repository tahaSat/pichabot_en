<?php
require_once __DIR__ . '/cli_only.php';
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$setting = select("setting","*",null,null,"select");

//________________[ time 12 report]________________
$midnight_time = date("H:i");
$reportnight = select("topicid","idreport","report","reportnight","select")['idreport'];
// if(true){
if ($midnight_time == "23:45") {
$datefirst = date("Y-m-d") . " 00:00:00";
$dateend = date("Y-m-d") . " 23:59:59";

// Helper function to execute a prepared statement
function executeQuery($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt;
}

// Fetch count and sum for invoices
$sqlInvoices = "SELECT COUNT(*) AS count, SUM(price_product) AS total_price, SUM(Volume) AS total_volume 
                FROM invoice 
                WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate) 
                AND (status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold')) 
                AND name_product != 'سرویس تست'";
$params = [':startDate' => $datefirst, ':endDate' => $dateend];
$stmt = executeQuery($pdo, $sqlInvoices, $params);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$dayListSell = $result['count'] ?? 0;
$suminvoiceday = $result['total_price'] ?? 0;
$sumvolume = $result['total_volume'] ?? 0;

// Fetch test service count
$sqlTestService = "SELECT COUNT(*) AS count 
                  FROM invoice 
                  WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate) 
                  AND (status IN ('active', 'end_of_time', 'sendedwarn')) 
                  AND name_product = 'سرویس تست'";
$stmt = executeQuery($pdo, $sqlTestService, $params);
$dayListSelltest = $stmt->fetchColumn() ?? 0;

// Fetch new users count
$sqlNewUsers = "SELECT COUNT(*) AS count 
                 FROM user 
                 WHERE (FROM_UNIXTIME(register) BETWEEN :startDate AND :endDate)";
$stmt = executeQuery($pdo, $sqlNewUsers, $params);
$usernew = $stmt->fetchColumn() ?? 0;

// Fetch extension data (only explicitly paid extend records)
$datefirstextend = date("Y/m/d") . " 00:00:00";
$dateendextend = date("Y/m/d") . " 23:59:59";

$sqlExtensions = "SELECT COUNT(*) AS count, COALESCE(SUM(CAST(price AS DECIMAL(20,0))), 0) AS total_price 
                  FROM service_other 
                  WHERE (time BETWEEN :startDate AND :endDate) 
                  AND type IN ('extend_user', 'extends_not_user', 'extend_user_by_admin')
                  AND status = 'paid'";
$params = [':startDate' => $datefirstextend, ':endDate' => $dateendextend];
$stmt = executeQuery($pdo, $sqlExtensions, $params);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$countextendday = $result['count'] ?? 0;
$sumcountextend = number_format($result['total_price'] ?? 0);

// Fetch top agents
$sqlTopAgents = "
    SELECT u.id, u.username, 
           (SELECT SUM(i.price_product) 
            FROM invoice i 
            WHERE i.id_user = u.id 
            AND (i.time_sell BETWEEN :startDate1 AND :endDate1) 
            AND i.status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold')) AS total_spent 
    FROM user u 
    WHERE u.agent IN ('n', 'n2') 
    AND EXISTS (SELECT 1 
                FROM invoice i 
                WHERE i.id_user = u.id 
                AND (i.time_sell BETWEEN :startDate2 AND :endDate2) 
                AND i.status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold')) 
    ORDER BY total_spent DESC 
    LIMIT 3";

$params = [
    ':startDate1' => strtotime($datefirstextend),
    ':endDate1' => strtotime($dateendextend),
    ':startDate2' => strtotime($datefirstextend),
    ':endDate2' => strtotime($dateendextend)
];

$stmt = executeQuery($pdo, $sqlTopAgents, $params);
$listagentuser = $stmt->fetchAll(PDO::FETCH_ASSOC);
$textagent = "Agents with the highest purchases today :\n";
foreach ($listagentuser as $agent) {
    $textagent .= "\nUser ID : {$agent['id']}\nUsername : {$agent['username']}\nToday's purchase total : {$agent['total_spent']}\n---------------\n";
}

// Fetch panel reports
$panels = select("marzban_panel", "*", null, null, "fetchAll");
$textpanel = "Panel report :\n";
foreach ($panels as $panel) {
    $sqlPanel = "SELECT COUNT(*) AS orders, SUM(price_product) AS total_price, SUM(Volume) AS total_volume 
                 FROM invoice 
                 WHERE (FROM_UNIXTIME(time_sell) BETWEEN :startDate AND :endDate) 
                 AND (status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold')) 
                 AND Service_location = :location 
                 AND name_product != 'سرویس تست'";
    $params = [':startDate' => $datefirst, ':endDate' => $dateend, ':location' => $panel['name_panel']];
    $stmt = executeQuery($pdo, $sqlPanel, $params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $orders = $result['orders'] ?? 0;
    $total_price = $result['total_price'] ?? 0;
    $total_volume = $result['total_volume'] ?? 0;

    $textpanel .= "\nPanel : {$panel['name_panel']}\n🛍 Today's orders : $orders\n🛍 Today's order total : $total_price USD\n🔋 Sold volume : $total_volume GB\n---------------\n";
}

// n2 advanced agent purchases (no credit billing)
agent_ensure_n2_tables();
$startTs = strtotime($datefirst);
$endTs = strtotime($dateend);
$sqlN2 = "SELECT agent_id,
                 COUNT(*) AS buy_count,
                 SUM(CAST(volume AS DECIMAL(12,2))) AS total_volume,
                 GROUP_CONCAT(DISTINCT name_product SEPARATOR ', ') AS products
          FROM agent_n2_purchase
          WHERE created_at BETWEEN :startTs AND :endTs
          GROUP BY agent_id
          ORDER BY buy_count DESC";
$stmt = executeQuery($pdo, $sqlN2, [':startTs' => $startTs, ':endTs' => $endTs]);
$n2Rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sqlN2Total = "SELECT COUNT(*) AS buy_count, SUM(CAST(volume AS DECIMAL(12,2))) AS total_volume
               FROM agent_n2_purchase
               WHERE created_at BETWEEN :startTs AND :endTs";
$stmt = executeQuery($pdo, $sqlN2Total, [':startTs' => $startTs, ':endTs' => $endTs]);
$n2Total = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$n2BuyCount = (int) ($n2Total['buy_count'] ?? 0);
$n2VolTotal = $n2Total['total_volume'] ?? 0;
$textn2 = "🛒 Advanced agent (n2) purchases today:\n";
$textn2 .= "Total purchases: {$n2BuyCount} | Total volume: {$n2VolTotal} GB\n";
if (empty($n2Rows)) {
    $textn2 .= "\nNo purchases were recorded today.\n";
} else {
    foreach ($n2Rows as $row) {
        $agentUser = select('user', '*', 'id', $row['agent_id'], 'select');
        $agentUsername = $agentUser['username'] ?? '-';
        $textn2 .= "\nID: {$row['agent_id']}\nUsername: @{$agentUsername}\nPurchases: {$row['buy_count']}\nTotal volume: {$row['total_volume']} GB\nProducts: {$row['products']}\n---------------\n";
    }
}

// Daily report text
$textreport = "📌 Daily bot activity report :\n\n🧲 Renewals today : $countextendday\n💰 Renewal total today : $sumcountextend USD\n🛍 Orders today : $dayListSell\n🛍 Order total today : $suminvoiceday USD\n🔑 Test accounts today : $dayListSelltest\n🔋 Sold volume : $sumvolume GB\n🛒 n2 purchases today : $n2BuyCount ({$n2VolTotal} GB)\nUsers who joined today : $usernew\n";

// Send reports to Telegram
if (!empty($setting['Channel_Report'])) {
    $report_data = [
        ['text' => $textagent],
        ['text' => $textreport],
        ['text' => $textn2],
        ['text' => $textpanel]
    ];

    foreach ($report_data as $report) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportnight,
            'text' => $report['text'],
            'parse_mode' => "HTML"
        ]);
    }
}
}