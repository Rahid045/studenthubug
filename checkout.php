<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.html'); exit; }

require_once __DIR__ . '/includes/pesapal.php';
function applicationUrl() {
    $configured = rtrim((string) getenv('APP_URL'), '/');
    if ($configured !== '') return $configured;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}

$userId = (int) $_SESSION['user_id'];
$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resourceId) { http_response_code(400); exit('Invalid resource.'); }
$stmt = mysqli_prepare($connect, 'SELECT * FROM resources WHERE resource_id = ? LIMIT 1'); mysqli_stmt_bind_param($stmt, 'i', $resourceId); mysqli_stmt_execute($stmt);
$resource = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$resource) { http_response_code(404); exit('Resource not found.'); }
$paid = mysqli_prepare($connect, "SELECT purchase_id FROM resource_purchases WHERE user_id=? AND resource_id=? AND status='successful' LIMIT 1"); mysqli_stmt_bind_param($paid, 'ii', $userId, $resourceId); mysqli_stmt_execute($paid);
if (!(int) $resource['is_paid'] || mysqli_fetch_assoc(mysqli_stmt_get_result($paid))) { header('Location: download.php?id=' . $resourceId); exit; }

$error = ''; $notice = '';
// Pesapal returns only identifiers. Fetch the transaction status server-to-server before granting access.
if (isset($_GET['OrderTrackingId'], $_GET['OrderMerchantReference'])) {
    $trackingId = trim((string) $_GET['OrderTrackingId']); $reference = trim((string) $_GET['OrderMerchantReference']);
    if ($trackingId === '' || strpos($reference, 'edu-' . $userId . '-' . $resourceId . '-') !== 0) $error = 'Invalid payment return details.';
    else {
        $sync = pesapalSyncPayment($connect, $trackingId, $reference);
        if (!$sync['valid'] || $sync['user_id'] !== $userId || $sync['resource_id'] !== $resourceId) $error = $sync['message'] ?? 'The payment could not be verified.';
        elseif ($sync['status'] === 'successful') { header('Location: download.php?id=' . $resourceId); exit; }
        elseif ($sync['status'] === 'failed') $error = 'The payment was not completed. Please try again.';
        else $notice = 'Your payment is still being processed. Refresh this page shortly to check again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $email = filter_var($_SESSION['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!pesapalCredentialsConfigured()) $error = 'Payments are temporarily unavailable. Please contact the site administrator.';
    elseif (!$email) $error = 'Your account needs a valid email address before payment.';
    else {
        $reference = 'edu-' . $userId . '-' . $resourceId . '-' . bin2hex(random_bytes(8));
        pesapalSavePurchase($connect, $userId, $resourceId, $reference, (float) $resource['price'], $resource['currency'], 'pending');
        $names = preg_split('/\s+/', trim((string) ($_SESSION['full_name'] ?? 'EduConnect Student')), 2);
        $payload = ['id' => $reference, 'currency' => strtoupper($resource['currency']), 'amount' => (float) $resource['price'], 'description' => substr('EduConnect: ' . $resource['title'], 0, 100), 'callback_url' => applicationUrl() . '/checkout.php?id=' . $resourceId, 'cancellation_url' => applicationUrl() . '/checkout.php?id=' . $resourceId, 'notification_id' => trim((string) getenv('PESAPAL_IPN_ID')), 'billing_address' => ['email_address' => $email, 'phone_number' => $_SESSION['phone_number'] ?? '', 'country_code' => getenv('PESAPAL_COUNTRY_CODE') ?: 'UG', 'first_name' => $names[0] ?: 'Student', 'middle_name' => '', 'last_name' => $names[1] ?? '', 'line_1' => '', 'line_2' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'zip_code' => '']];
        $result = pesapalApiRequest('POST', '/api/Transactions/SubmitOrderRequest', $payload);
        if ((string) ($result['status'] ?? '') === '200' && !empty($result['redirect_url']) && !empty($result['order_tracking_id'])) { pesapalSavePurchase($connect, $userId, $resourceId, $reference, (float) $resource['price'], $resource['currency'], 'pending', $result['order_tracking_id']); header('Location: ' . $result['redirect_url']); exit; }
        $error = $result['error']['message'] ?? $result['message'] ?? 'Unable to start the payment. Please try again.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pay for resource | EduConnect</title><link rel="stylesheet" href="style.css"></head><body><header class="site-header"><div class="container nav"><a class="logo" href="index.html"><span class="logo-mark">E</span>EduConnect</a></div></header><main class="form-wrap"><section class="form-card"><div class="form-header"><h1>Complete payment</h1><p>Checkout is secured by Pesapal.</p></div><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?><div class="notice"><strong><?= htmlspecialchars($resource['title']) ?></strong><br>Amount: <strong><?= htmlspecialchars($resource['currency']) ?> <?= number_format((float) $resource['price'], 2) ?></strong></div><form method="POST" data-loading><button class="btn btn-primary btn-block" type="submit">Pay with Pesapal</button></form></section></main></body></html>
