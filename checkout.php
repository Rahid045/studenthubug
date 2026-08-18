<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }

function flutterwaveSecretKey() { return trim((string) getenv('FLUTTERWAVE_SECRET_KEY')); }
function flutterwaveRequest($method, $path, $payload = null) {
    $key = flutterwaveSecretKey();
    if ($key === '') return ['error' => 'Flutterwave has not been configured.'];
    $ch = curl_init('https://api.flutterwave.com' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json']]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
    if ($error) return ['error' => $error];
    $decoded = json_decode((string) $response, true);
    return is_array($decoded) ? $decoded : ['error' => 'Invalid response from Flutterwave.'];
}
function applicationUrl() {
    $configured = rtrim((string) getenv('APP_URL'), '/');
    if ($configured !== '') return $configured;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}
function savePurchase($db, $userId, $resourceId, $txRef, $amount, $currency, $status, $transactionId = null) {
    $stmt = mysqli_prepare($db, 'INSERT INTO resource_purchases (user_id, resource_id, tx_ref, transaction_id, amount, currency, provider, status) VALUES (?, ?, ?, ?, ?, ?, "flutterwave", ?) ON DUPLICATE KEY UPDATE transaction_id = VALUES(transaction_id), amount = VALUES(amount), currency = VALUES(currency), provider = VALUES(provider), status = VALUES(status)');
    mysqli_stmt_bind_param($stmt, 'iissdss', $userId, $resourceId, $txRef, $transactionId, $amount, $currency, $status);
    return mysqli_stmt_execute($stmt);
}

$userId = (int) $_SESSION['user_id'];
$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resourceId) { http_response_code(400); exit('Invalid resource.'); }
$stmt = mysqli_prepare($connect, 'SELECT * FROM resources WHERE resource_id = ? LIMIT 1'); mysqli_stmt_bind_param($stmt, 'i', $resourceId); mysqli_stmt_execute($stmt);
$resource = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$resource) { http_response_code(404); exit('Resource not found.'); }
$paid = mysqli_prepare($connect, "SELECT purchase_id FROM resource_purchases WHERE user_id=? AND resource_id=? AND status='successful' LIMIT 1"); mysqli_stmt_bind_param($paid, 'ii', $userId, $resourceId); mysqli_stmt_execute($paid);
if (!(int) $resource['is_paid'] || mysqli_fetch_assoc(mysqli_stmt_get_result($paid))) { header('Location: download.php?id=' . $resourceId); exit; }

$error = '';
// Never trust the return status. Verify each returned transaction with Flutterwave.
if (isset($_GET['transaction_id'], $_GET['tx_ref'])) {
    $txRef = trim((string) $_GET['tx_ref']); $transactionId = filter_var($_GET['transaction_id'], FILTER_VALIDATE_INT);
    if (!$transactionId || strpos($txRef, 'edu-' . $userId . '-' . $resourceId . '-') !== 0) $error = 'Invalid payment return details.';
    else {
        $result = flutterwaveRequest('GET', '/v3/transactions/' . $transactionId . '/verify'); $data = $result['data'] ?? [];
        $valid = ($result['status'] ?? '') === 'success' && strtolower((string) ($data['status'] ?? '')) === 'successful'
            && hash_equals($txRef, (string) ($data['tx_ref'] ?? ''))
            && abs((float) ($data['amount'] ?? -1) - (float) $resource['price']) < 0.01
            && strtoupper((string) ($data['currency'] ?? '')) === strtoupper((string) $resource['currency'])
            && (string) ($data['meta']['resource_id'] ?? '') === (string) $resourceId
            && (string) ($data['meta']['user_id'] ?? '') === (string) $userId;
        savePurchase($connect, $userId, $resourceId, $txRef, (float) $resource['price'], $resource['currency'], $valid ? 'successful' : 'failed', (string) $transactionId);
        if ($valid) { header('Location: download.php?id=' . $resourceId); exit; }
        $error = 'The payment could not be verified. If you were charged, contact support with your transaction reference.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $email = filter_var($_SESSION['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (flutterwaveSecretKey() === '') $error = 'Payments are temporarily unavailable. Please contact the site administrator.';
    elseif (!$email) $error = 'Your account needs a valid email address before payment.';
    else {
        $txRef = 'edu-' . $userId . '-' . $resourceId . '-' . bin2hex(random_bytes(8));
        savePurchase($connect, $userId, $resourceId, $txRef, (float) $resource['price'], $resource['currency'], 'pending');
        $payload = ['tx_ref' => $txRef, 'amount' => number_format((float) $resource['price'], 2, '.', ''), 'currency' => strtoupper($resource['currency']), 'redirect_url' => applicationUrl() . '/checkout.php?id=' . $resourceId, 'payment_options' => 'card,mobilemoneyuganda', 'customer' => ['email' => $email, 'name' => $_SESSION['full_name'] ?? 'EduConnect Student'], 'meta' => ['resource_id' => (string) $resourceId, 'user_id' => (string) $userId], 'customizations' => ['title' => 'EduConnect Resource Purchase', 'description' => 'Payment for ' . $resource['title']]];
        $result = flutterwaveRequest('POST', '/v3/payments', $payload);
        if (($result['status'] ?? '') === 'success' && !empty($result['data']['link'])) { header('Location: ' . $result['data']['link']); exit; }
        $error = !empty($result['message']) ? $result['message'] : 'Unable to start the payment. Please try again.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pay for resource | EduConnect</title><link rel="stylesheet" href="style.css"></head><body><header class="site-header"><div class="container nav"><a class="logo" href="index.html"><span class="logo-mark">E</span>EduConnect</a></div></header><main class="form-wrap"><section class="form-card"><div class="form-header"><h1>Complete payment</h1><p>Checkout is secured by Flutterwave.</p></div><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><div class="notice"><strong><?= htmlspecialchars($resource['title']) ?></strong><br>Amount: <strong><?= htmlspecialchars($resource['currency']) ?> <?= number_format((float) $resource['price'], 2) ?></strong></div><form method="POST" data-loading><button class="btn btn-primary btn-block" type="submit">Pay with Flutterwave</button></form></section></main></body></html>
