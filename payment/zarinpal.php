<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../panels.php';
require __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

$ManagePanel = new ManagePanel();

$Authority = htmlspecialchars($_GET['Authority'], ENT_QUOTES, 'UTF-8');
$StatusPayment = htmlspecialchars($_GET['Status'], ENT_QUOTES, 'UTF-8');
$setting = select("setting", "*");
$PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal","select")['ValuePay'];
$Payment_reports = select("Payment_report", "*", "dec_not_confirmed", $Authority,"select");
$price = $Payment_reports['price'];
$invoice_id = $Payment_reports['id_order'];
    $datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
    $datatxtbot = array();
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'textafterpayibsng' => ''
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
// verify Transaction
$dec_payment_status = "";
$payment_status = "";
if($StatusPayment == "OK"){
        $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/verify.json',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Accept: application/json'
  ),
));
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
  "merchant_id" => $PaySetting,
  "amount"=> $price,
  "authority" => $Authority,
  "description" => $Payment_reports['id_user']
        ]));
$response = curl_exec($curl);
curl_close($curl);
$response = json_decode($response,true);
       $payment_status = [
			"-9" => "Error sending data",
			"-10" => "Invalid IP or merchant code.",
			"-11" => "Merchant code is not active.",
			"-12" => "Too many attempts in a short time",
			"-15" => "The payment gateway is suspended",
			"-16" => "Merchant verification level is too low.",
			"-17" => "Blue-level merchant restriction",
			"-30" => "Merchant cannot use floating shared settlement.",
			"-31" => "Add a bank account to the panel. Settlement values are incorrect.",
			"-32" => "The amount is greater than the total transaction.",
			"-33" => "Percentages are invalid.",
			"-34" => "The amount is greater than the total transaction.",
			"-35" => "Too many settlement recipients.",
			"-36" => "Minimum settlement amount is 10000 Rial",
			"-37" => "One or more IBAN numbers are inactive.",
			"-38" => "Invalid IBAN. Please try again later.",
			"-39" => "	An error occurred",
			"-40" => "",
			"-50" => "Paid amount does not match the verify amount.",
			"-51" => "Payment failed",
			"-52" => "	An unexpected error occurred. ",
			"-53" => "This payment does not belong to this merchant.",
			"-54" => "Invalid authority.",
    ][$response['errors']['code']];
 if($response['data']['message'] == "Verified" || $response['data']['message'] == "Paid"){
    $payment_status = "Payment successful";
    $dec_payment_status = "Thank you for your payment!";
    $Payment_report = select("Payment_report", "*", "id_order", $invoice_id,"select");
    if($Payment_report['payment_Status'] != "paid"){
    $textbotlang = languagechange('../text.json');
    DirectPayment($invoice_id,"../images.jpg");
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackzarinpal","select")['ValuePay'];
    $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
    if($pricecashback != "0"){
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) +$result;
        update("user","Balance",$Balance_confrim, "id",$Balance_id['id']); 
        $pricecashback =  number_format($pricecashback);
        $text_report = "🎁 $result Toman was added to your account as a deposit bonus.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    update("Payment_report","payment_Status","paid","id_order",$Payment_report['id_order']);
    $paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];
    $refcode = $response['data']['ref_id'];
    $cart_number = $response['data']['card_pan'];
    $price = number_format($price);
$text_report = "💵 پرداخت جدید
        
آیدی عددی کاربر : {$Payment_report['id_user']}
نام کاربری کاربر : {$Balance_id['username']}
مبلغ تراکنش $price
شماره تراکنش پرداخت : $refcode
شماره کارت کاربر : $cart_number
روش پرداخت :  درگاه زرین پال";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage',[
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_report,
        'parse_mode' => "HTML"
        ]);
    }
}
}else {
        $payment_status = [
        '0' => "Payment was not completed",
        '2' => "This transaction was already verified",

    ][$response['errors']['code']];
     $dec_payment_status = "";
}
}
?>
<html>
<head>
    <title>Payment invoice</title>
    <style>
    @font-face {
    font-family: 'vazir';
    src: url('/Vazir.eot');
    src: local('☺'), url('../fonts/Vazir.woff') format('woff'), url('../fonts/Vazir.ttf') format('truetype');
}

        body {
            font-family:vazir;
            background-color: #f2f2f2;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .confirmation-box {
            background-color: #ffffff;
            border-radius: 8px;
            width:25%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }

        h1 {
            color: #333333;
            margin-bottom: 20px;
        }

        p {
            color: #666666;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="confirmation-box">
        <h1><?php echo $payment_status ?></h1>
        <p>Transaction ID: <span><?php echo $invoice_id ?></span></p>
        <p>Amount paid:  <span><?php echo  $price ?></span> Toman</p>
        <p>Date: <span>  <?php echo date('Y-m-d')  ?>  </span></p>
        <p><?php echo $dec_payment_status ?></p>
    </div>
</body>
</html>
