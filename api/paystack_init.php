<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

if (($_SESSION['user_role'] ?? '') === 'attendant') {
    http_response_code(403);
    die("Error: Attendants cannot place orders.");
}

if (!isset($_SESSION['user_id'])) {
    die("Error: You must be logged in to initiate payment.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

if (!function_exists('curl_init')) {
    die("Fatal Error: PHP cURL extension is not enabled in XAMPP. Please enable extension=curl in php.ini and restart Apache.");
}

$amount = (float)($_POST['amount'] ?? 0);
$delivery_type = $_POST['delivery_type'] ?? 'pickup';

if ($amount <= 0) {
    die("Invalid order amount.");
}

try {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        die("User details or email address missing.");
    }

    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, delivery_type, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'], $amount, $delivery_type]);
    $order_id = $pdo->lastInsertId();

    $paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    if (empty($paystackSecretKey)) {
        die("Paystack Secret Key missing in .env (PAYSTACK_SECRET_KEY).");
    }

    $amountInCents = (int)round($amount * 100);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $callbackUrl = $baseUrl . "/jengo-hardware/api/paystack_verify.php";

    $payload = [
        'email'        => $user['email'],
        'amount'       => $amountInCents,
        'currency'     => 'KES',
        'reference'    => 'JENGO_' . $order_id . '_' . time(),
        'callback_url' => $callbackUrl,
        'metadata'     => [
            'order_id'      => $order_id,
            'customer_name' => $user['name']
        ]
    ];

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " . trim($paystackSecretKey),
            "Content-Type: application/json",
            "Cache-Control: no-cache"
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die('cURL Error (Paystack Init): ' . curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] === true) {
        header("Location: " . $result['data']['authorization_url']);
        exit;
    } else {
        die("Paystack Error: " . htmlspecialchars($result['message'] ?? 'Initialization failed.'));
    }

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}