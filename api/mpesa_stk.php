<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    die("Error: User must be logged in to initiate payment.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$phone         = trim($_POST['phone'] ?? '');
$amount        = (float)($_POST['amount'] ?? 0);
$delivery_type = $_POST['delivery_type'] ?? 'pickup';

if (empty($phone) || $amount <= 0) {
    die("Invalid phone number or amount.");
}

// Ensure phone starts with 254
if (strpos($phone, '0') === 0) {
    $phone = '254' . substr($phone, 1);
} elseif (strpos($phone, '+254') === 0) {
    $phone = substr($phone, 1);
}

try {
    // 1. Create order in pending status
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, delivery_type, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'], $amount, $delivery_type]);
    $order_id = $pdo->lastInsertId();

    // 2. Retrieve credentials from environment
    $consumerKey    = trim($_ENV['DARAJA_CONSUMER_KEY'] ?? '');
    $consumerSecret = trim($_ENV['DARAJA_CONSUMER_SECRET'] ?? '');
    $passkey        = trim($_ENV['DARAJA_PASSKEY'] ?? '');
    $shortcode      = trim($_ENV['DARAJA_SHORTCODE'] ?? '174379');
    $callbackUrl    = trim($_ENV['DARAJA_CALLBACK_URL'] ?? '');

    if (empty($consumerKey) || empty($consumerSecret) || empty($passkey) || empty($callbackUrl)) {
        die("Missing Safaricom Daraja API credentials in .env configuration.");
    }

    // 3. Generate OAuth Access Token using native stream context (No cURL required)
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $tokenOptions = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic " . $credentials . "\r\n"
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false
        ]
    ];

    $tokenContext  = stream_context_create($tokenOptions);
    $tokenResponse = @file_get_contents('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials', false, $tokenContext);

    if ($tokenResponse === false) {
        $lastError = error_get_last();
        die("Failed to connect to Daraja API (Token Generation): " . htmlspecialchars($lastError['message'] ?? 'Unknown network error.'));
    }

    $tokenResult = json_decode($tokenResponse, true);
    if (!isset($tokenResult['access_token'])) {
        die("Failed to obtain Daraja Access Token: " . htmlspecialchars($tokenResponse));
    }
    $accessToken = $tokenResult['access_token'];

    // 4. Prepare STK Push Payload
    $timestamp = date('YmdHis');
    $password  = base64_encode($shortcode . $passkey . $timestamp);

    $stkPayload = json_encode([
        'BusinessShortCode' => $shortcode,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int)ceil($amount),
        'PartyA'            => $phone,
        'PartyB'            => $shortcode,
        'PhoneNumber'       => $phone,
        'CallBackURL'       => $callbackUrl,
        'AccountReference'  => 'JengoOrder#' . $order_id,
        'TransactionDesc'   => 'Jengo Hardware Purchase'
    ]);

    // 5. Send STK Push Request using native stream context
    $stkOptions = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Bearer " . $accessToken . "\r\n" .
                         "Content-Type: application/json\r\n",
            'content' => $stkPayload
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false
        ]
    ];

    $stkContext  = stream_context_create($stkOptions);
    $stkResponse = @file_get_contents('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', false, $stkContext);

    if ($stkResponse === false) {
        $lastError = error_get_last();
        die("STK Push Request Failed: " . htmlspecialchars($lastError['message'] ?? 'Unable to send request to Safaricom.'));
    }

    $res = json_decode($stkResponse, true);

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (\Exception $e) {
    die("System Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa STK Push - Jengo Hardware</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full border-t-4 border-green-600 text-center">
        <?php if (isset($res['ResponseCode']) && $res['ResponseCode'] == '0'): ?>
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">✓</div>
            <h2 class="text-2xl font-bold text-stone-800 mb-2">STK Push Sent!</h2>
            <p class="text-stone-600 text-sm mb-4">Check your phone (<strong><?= htmlspecialchars($phone) ?></strong>) and enter your M-Pesa PIN to complete payment for Order #<?= $order_id ?>.</p>
            <div class="bg-stone-50 p-3 rounded text-xs text-stone-500 font-mono mb-6">
                CheckoutRequestID: <?= htmlspecialchars($res['CheckoutRequestID'] ?? 'N/A') ?>
            </div>
            <a href="../public/customer_dashboard.php" class="inline-block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 rounded-lg">Go to Dashboard</a>
        <?php else: ?>
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">✕</div>
            <h2 class="text-2xl font-bold text-red-600 mb-2">STK Push Failed</h2>
            <p class="text-stone-600 text-sm mb-4"><?= htmlspecialchars($res['errorMessage'] ?? $res['ResponseDescription'] ?? 'An error occurred while connecting to Daraja API.') ?></p>
            <div class="bg-red-50 text-red-800 p-3 rounded text-xs font-mono mb-6 text-left overflow-x-auto">
                <pre><?= htmlspecialchars(json_encode($res ?? [], JSON_PRETTY_PRINT)) ?></pre>
            </div>
            <a href="../public/checkout.php" class="inline-block w-full bg-stone-800 hover:bg-stone-900 text-white font-bold py-2.5 rounded-lg">Return to Checkout</a>
        <?php endif; ?>
    </div>
</body>
</html>