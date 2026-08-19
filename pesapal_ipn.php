<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/includes/pesapal.php';
$input = json_decode((string) file_get_contents('php://input'), true); if (!is_array($input)) $input = $_REQUEST;
$trackingId = trim((string) ($input['OrderTrackingId'] ?? $input['orderTrackingId'] ?? '')); $reference = trim((string) ($input['OrderMerchantReference'] ?? $input['orderMerchantReference'] ?? ''));
$response = ['orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => $trackingId, 'orderMerchantReference' => $reference, 'status' => 500];
if ($trackingId !== '' && $reference !== '') { $sync = pesapalSyncPayment($connect, $trackingId, $reference); if ($sync['valid']) $response['status'] = 200; }
header('Content-Type: application/json'); echo json_encode($response);
