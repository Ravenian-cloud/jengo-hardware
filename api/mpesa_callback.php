<?php
require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

$stkCallbackResponse = file_get_contents('php://input');

// Log raw payload for auditing
$logStmt = $pdo->prepare("INSERT INTO payment_logs (provider, raw_payload) VALUES ('mpesa', ?)");
$logStmt->execute([$stkCallbackResponse]);

$data = json_decode($stkCallbackResponse, true);
$callbackData = $data['Body']['stkCallback'] ?? null;

if ($callbackData) {
    $resultCode = $callbackData['ResultCode'];
    $accountReference = $callbackData['CallbackMetadata']['Item'][0]['Value'] ?? '';
    
    // Extract Order ID from reference (JengoHardware_X)
    $order_id = 0;
    foreach ($callbackData['CallbackMetadata']['Item'] as $item) {
        if ($item['Name'] === 'MpesaReceiptNumber') {
            $mpesaReceiptNumber = $item['Value'];
        }
        if ($item['Name'] === 'Amount') {
            $amount = $item['Value'];
        }
    }

    // Extract Order ID from raw json match
    if (preg_match('/JengoHardware_(\d+)/', $stkCallbackResponse, $matches)) {
        $order_id = (int)$matches[1];
    }

    if ($resultCode == 0 && $order_id > 0) {
        // 1. Update Order Status
        $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order_id]);

        // 2. Insert Payment Record
        $pdo->prepare("INSERT INTO payments (order_id, method, amount, status, receipt_or_reference) VALUES (?, 'mpesa', ?, 'paid', ?)")
            ->execute([$order_id, $amount ?? 0, $mpesaReceiptNumber ?? '']);

        // 3. Deduct Stock Automatically
        $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$order_id]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")
                ->execute([$item['quantity'], $item['product_id']]);
        }
    }
}

echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);