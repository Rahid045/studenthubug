<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

function flutterwaveRequest($method, $path, $payload = []) {
    $secretKey = getenv('FLUTTERWAVE_SECRET_KEY') ?: ($_ENV['FLUTTERWAVE_SECRET_KEY'] ?? '');
    if ($secretKey === '') {
        return ['mock' => true];
    }

    $ch = curl_init('https://api.flutterwave.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
    ]);

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['raw' => $response];
}

function upsertPurchaseRecord($connect, $userId, $resourceId, $txRef, $amount, $currency, $provider, $status = 'pending', $transactionId = null) {
    $existing = mysqli_prepare($connect, 'SELECT purchase_id FROM resource_purchases WHERE user_id = ? AND resource_id = ? LIMIT 1');
    mysqli_stmt_bind_param($existing, 'ii', $userId, $resourceId);
    mysqli_stmt_execute($existing);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($existing));

    if ($row) {
        $stmt = mysqli_prepare($connect, 'UPDATE resource_purchases SET tx_ref = ?, amount = ?, currency = ?, provider = ?, status = ?, transaction_id = ? WHERE purchase_id = ?');
        mysqli_stmt_bind_param($stmt, 'sdsdssi', $txRef, $amount, $currency, $provider, $status, $transactionId, $row['purchase_id']);
        return mysqli_stmt_execute($stmt);
    }

    $stmt = mysqli_prepare($connect, 'INSERT INTO resource_purchases (user_id, resource_id, tx_ref, transaction_id, amount, currency, provider, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'iisdssds', $userId, $resourceId, $txRef, $transactionId, $amount, $currency, $provider, $status);
    return mysqli_stmt_execute($stmt);
}

$userId = (int) $_SESSION['user_id'];
$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$resource = null;

if (!$resourceId) {
    http_response_code(400);
    exit('Invalid resource.');
}

$resourceStmt = mysqli_prepare($connect, 'SELECT * FROM resources WHERE resource_id = ? LIMIT 1');
mysqli_stmt_bind_param($resourceStmt, 'i', $resourceId);
mysqli_stmt_execute($resourceStmt);
$resource = mysqli_fetch_assoc(mysqli_stmt_get_result($resourceStmt));

if (!$resource) {
    http_response_code(404);
    exit('Resource not found.');
}

$alreadyPaid = false;
$purchaseCheck = mysqli_prepare($connect, 'SELECT purchase_id FROM resource_purchases WHERE user_id = ? AND resource_id = ? AND status = "successful" LIMIT 1');
mysqli_stmt_bind_param($purchaseCheck, 'ii', $userId, $resourceId);
mysqli_stmt_execute($purchaseCheck);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseCheck))) {
    $alreadyPaid = true;
}

if ((int) $resource['is_paid'] === 0 || $alreadyPaid) {
    header('Location: download.php?id=' . (int) $resource['resource_id']);
    exit;
}

if (isset($_GET['status']) && !empty($_GET['tx_ref'])) {
    $txRef = trim($_GET['tx_ref']);
    $status = trim($_GET['status']);
    $transactionId = trim($_GET['transaction_id'] ?? '');

    $verifyResult = flutterwaveRequest('GET', '/v3/transactions/verify_by_reference?tx_ref=' . urlencode($txRef));
    $verified = false;

    if (isset($verifyResult['status']) && $verifyResult['status'] === 'success' && isset($verifyResult['data'])) {
        $verified = true;
        $paymentStatus = strtolower((string) ($verifyResult['data']['status'] ?? 'pending'));
        $verifiedTransactionId = (string) ($verifyResult['data']['id'] ?? $transactionId);
    } elseif (isset($verifyResult['mock'])) {
        $verified = true;
        $paymentStatus = 'successful';
        $verifiedTransactionId = 'mock-' . time();
    } else {
        $paymentStatus = 'failed';
    }

    if ($verified) {
        upsertPurchaseRecord(
            $connect,
            $userId,
            (int) $resource['resource_id'],
            $txRef,
            (float) $resource['price'],
            $resource['currency'],
            'mobile_money',
            $paymentStatus === 'successful' ? 'successful' : 'failed',
            $verifiedTransactionId
        );

        if ($paymentStatus === 'successful') {
            header('Location: download.php?id=' . (int) $resource['resource_id']);
            exit;
        }
    }

    $_SESSION['payment_error'] = 'The payment could not be confirmed. Please try again.';
    header('Location: checkout.php?id=' . (int) $resource['resource_id']);
    exit;
}

$error = $_SESSION['payment_error'] ?? '';
unset($_SESSION['payment_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = trim($_POST['provider'] ?? 'mtn');
    $phone = trim($_POST['phone'] ?? '');
    $amount = (float) $resource['price'];
    $currency = $resource['currency'] ?: 'GHS';
    $txRef = 'edu-' . time() . '-' . $userId . '-' . $resourceId;

    if ($phone === '') {
        $error = 'Please enter your mobile money phone number.';
    } else {
        $paymentData = [
            'tx_ref' => $txRef,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'redirect_url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/checkout.php?id=' . $resourceId . '&tx_ref=' . urlencode($txRef),
            'payment_options' => 'mobile_money_ghana',
            'meta' => [
                'resource_id' => (string) $resourceId,
                'user_id' => (string) $userId,
                'email' => $_SESSION['email'] ?? '',
            ],
            'customer' => [
                'email' => $_SESSION['email'] ?? 'student@educonnect.local',
                'name' => $_SESSION['full_name'] ?? 'EduConnect Student',
                'phonenumber' => $phone,
            ],
            'customizations' => [
                'title' => 'EduConnect Resource Purchase',
                'description' => 'Payment for ' . $resource['title'],
            ],
        ];

        upsertPurchaseRecord($connect, $userId, (int) $resource['resource_id'], $txRef, $amount, $currency, $provider, 'pending', null);

        $result = flutterwaveRequest('POST', '/v3/payments', $paymentData);

        if (isset($result['mock'])) {
            upsertPurchaseRecord($connect, $userId, (int) $resource['resource_id'], $txRef, $amount, $currency, $provider, 'successful', 'mock-' . time());
            header('Location: download.php?id=' . (int) $resource['resource_id']);
            exit;
        }

        if (isset($result['status']) && $result['status'] === 'success' && !empty($result['data']['link'])) {
            header('Location: ' . $result['data']['link']);
            exit;
        }

        $error = 'Unable to start the payment right now. Please try again.';
        if (!empty($result['message'])) {
            $error = $result['message'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay for resource | EduConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav">
            <a class="logo" href="index.html"><span class="logo-mark">E</span>EduConnect</a>
            <ul class="nav-links">
                <li><a href="resources.php">Resources</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
            </ul>
        </div>
    </header>

    <main class="form-wrap">
        <section class="form-card">
            <div class="form-header">
                <div class="icon-box" style="margin:0 auto 14px">₵</div>
                <h1>Complete payment</h1>
                <p>Pay securely with Mobile Money and download instantly.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="notice">
                <strong><?= htmlspecialchars($resource['title']) ?></strong><br>
                <?= htmlspecialchars($resource['course'] ?: 'General resource') ?><br>
                Amount: <strong><?= htmlspecialchars($resource['currency']) ?> <?= number_format((float) $resource['price'], 2) ?></strong>
            </div>

            <form method="POST" data-loading>
                <div class="form-group">
                    <label>Mobile money option</label>
                    <select name="provider" required>
                        <option value="mtn">MTN MoMo</option>
                        <option value="airtel">Airtel Money</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone number</label>
                    <input type="tel" name="phone" placeholder="e.g. 0241234567" required>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Pay with Flutterwave</button>
            </form>
        </section>
    </main>
</body>
</html>
