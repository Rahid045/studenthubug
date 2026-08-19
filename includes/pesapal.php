<?php

function pesapalBaseUrl() {
    return strtolower((string) getenv('PESAPAL_ENV')) === 'live' ? 'https://pay.pesapal.com/v3' : 'https://cybqa.pesapal.com/pesapalv3';
}
function pesapalCredentialsConfigured() {
    return trim((string) getenv('PESAPAL_CONSUMER_KEY')) !== '' && trim((string) getenv('PESAPAL_CONSUMER_SECRET')) !== '' && trim((string) getenv('PESAPAL_IPN_ID')) !== '';
}
function pesapalHttpRequest($method, $path, $payload = null, $token = null) {
    $ch = curl_init(pesapalBaseUrl() . $path); $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch); $error = curl_error($ch); $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($error) return ['error' => $error];
    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) return ['error' => 'Invalid response from Pesapal.', 'http_code' => $httpCode];
    $decoded['http_code'] = $httpCode; return $decoded;
}
function pesapalAccessToken() {
    $key = trim((string) getenv('PESAPAL_CONSUMER_KEY')); $secret = trim((string) getenv('PESAPAL_CONSUMER_SECRET'));
    if ($key === '' || $secret === '') return ['error' => 'Pesapal has not been configured.'];
    $result = pesapalHttpRequest('POST', '/api/Auth/RequestToken', ['consumer_key' => $key, 'consumer_secret' => $secret]);
    return !empty($result['token']) ? $result : ['error' => $result['error']['message'] ?? $result['message'] ?? 'Unable to authenticate with Pesapal.'];
}
function pesapalApiRequest($method, $path, $payload = null) {
    $auth = pesapalAccessToken(); if (empty($auth['token'])) return $auth;
    return pesapalHttpRequest($method, $path, $payload, $auth['token']);
}
function pesapalSavePurchase($db, $userId, $resourceId, $reference, $amount, $currency, $status, $trackingId = null) {
    $stmt = mysqli_prepare($db, 'INSERT INTO resource_purchases (user_id, resource_id, tx_ref, transaction_id, amount, currency, provider, status) VALUES (?, ?, ?, ?, ?, ?, "pesapal", ?) ON DUPLICATE KEY UPDATE tx_ref = VALUES(tx_ref), transaction_id = VALUES(transaction_id), amount = VALUES(amount), currency = VALUES(currency), provider = VALUES(provider), status = VALUES(status)');
    mysqli_stmt_bind_param($stmt, 'iissdss', $userId, $resourceId, $reference, $trackingId, $amount, $currency, $status); return mysqli_stmt_execute($stmt);
}
function pesapalSyncPayment($db, $trackingId, $expectedReference = null) {
    $result = pesapalApiRequest('GET', '/api/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode($trackingId));
    $reference = trim((string) ($result['merchant_reference'] ?? ''));
    if ($reference === '' || ($expectedReference !== null && !hash_equals($expectedReference, $reference))) return ['valid' => false, 'message' => 'Invalid payment return details.'];
    $purchase = mysqli_prepare($db, 'SELECT user_id, resource_id, amount, currency FROM resource_purchases WHERE tx_ref = ? AND provider = "pesapal" LIMIT 1'); mysqli_stmt_bind_param($purchase, 's', $reference); mysqli_stmt_execute($purchase);
    $record = mysqli_fetch_assoc(mysqli_stmt_get_result($purchase));
    if (!$record) return ['valid' => false, 'message' => 'Unknown payment reference.'];
    $sameAmount = abs((float) ($result['amount'] ?? -1) - (float) $record['amount']) < 0.01; $sameCurrency = strtoupper((string) ($result['currency'] ?? '')) === strtoupper((string) $record['currency']); $statusCode = (int) ($result['status_code'] ?? -1);
    $status = ($statusCode === 1 && $sameAmount && $sameCurrency) ? 'successful' : (($statusCode === 2 || $statusCode === 3) ? 'failed' : 'pending');
    pesapalSavePurchase($db, (int) $record['user_id'], (int) $record['resource_id'], $reference, (float) $record['amount'], $record['currency'], $status, $trackingId);
    return ['valid' => $sameAmount && $sameCurrency, 'status' => $status, 'reference' => $reference, 'resource_id' => (int) $record['resource_id'], 'user_id' => (int) $record['user_id'], 'message' => $result['description'] ?? $result['payment_status_description'] ?? 'Payment is still being processed.'];
}
