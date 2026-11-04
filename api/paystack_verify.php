<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

$reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';

if (empty($reference)) {
    die("Error: No transaction reference provided.");
}

try {
    $paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';

    // Verify transaction with Paystack API
    $ch = curl_init("https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " . trim($paystackSecretKey),
            "Cache-Control: no-cache"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die("cURL Error (Paystack Verification): " . curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] === true && $result['data']['status'] === 'success') {
        $order_id = $result['data']['metadata']['order_id'] ?? 0;
        $amount   = $result['data']['amount'] / 100; // Convert cents back to KES

        if ($order_id > 0) {
            // Update order status to paid
            $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
            $stmt->execute([$order_id]);

            // Record payment details
            $payStmt = $pdo->prepare("INSERT INTO payments (order_id, method, amount, receipt_or_reference, status) VALUES (?, 'paystack', ?, ?, 'paid')");
            $payStmt->execute([$order_id, $amount, $reference]);

            // Clear session cart
            unset($_SESSION['cart']);

            header("Location: ../public/customer_dashboard.php?payment=success");
            exit;
        }
    }

    die("Payment verification failed or transaction was not successful.");

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}