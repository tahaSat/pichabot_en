<?php
require_once __DIR__ . '/cli_only.php';
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
$ManagePanel = new ManagePanel();

$setting = select("setting", "*");
// buy service 
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE type = 'marzban'  ORDER BY RAND() LIMIT 25");
$stmt->execute();
        while ($panel = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users = getusers($panel['name_panel'],"on_hold")['users'];
        foreach($users as $user){
        $invoice = select("invoice","*","username",$user['username'],"select");
        if($invoice == false )continue;
        if($invoice['Status'] == "send_on_hold")continue;
        $line  = $invoice['username'];
        $resultss = $invoice;
        $marzban_list_get = $panel;
        $get_username_Check = $user;
        if($get_username_Check['status'] != "Unsuccessful"){
        if(in_array($get_username_Check['status'],['on_hold'])){
            $timebuyremin = (time() - $resultss['time_sell'])/86400;
        if ($timebuyremin >= $setting['on_hold_day']) {
        $sql = "SELECT * FROM service_other WHERE username = :username  AND type = 'change_location'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $line ,PDO::PARAM_STR);
        $stmt->execute();
        $service_other = $stmt->rowCount();
        if($service_other != 0)continue;
                $text = "Hello! 🌐

We noticed you have not connected to config $line and more than {$setting['on_hold_day']} day(s) have passed since it was activated. If you need help setting it up, please contact support using the ID below.
We are ready to help with any question! 📞

Support: @{$setting['id_support']}";
            sendmessage($resultss['id_user'], $text, null, 'HTML');
            update("invoice","Status","send_on_hold", "username",$line);
            }
        }
        }
}
}