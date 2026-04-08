<?php
/**
 * Admin-only endpoint to manually process an Authorize.Net transaction.
 * Use when a payment succeeded but the webhook failed to credit the wallet.
 *
 * POST /plugin/AuthorizeNet/processTransaction.json.php
 * Body: { "transactionId": "60027744829" }
 *
 * The transactionId can be found in the Authorize.Net dashboard.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../videos/configuration.php';
require_once $global['systemRootPath'] . 'objects/user.php';

if (!User::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => true, 'msg' => 'Permission denied']);
    exit;
}

require_once $global['systemRootPath'] . 'plugin/AuthorizeNet/AuthorizeNet.php';

$input         = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$transactionId = trim($input['transactionId'] ?? '');

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'msg' => 'transactionId is required']);
    exit;
}

_error_log('[AuthorizeNet] Manual processTransaction requested by admin'
    . ' | transactionId=' . $transactionId
    . ' | admin_users_id=' . User::getId()
);

// First fetch transaction details so we can show info even before processing
$txnInfo = AuthorizeNet::getTransactionDetails($transactionId);
if (!empty($txnInfo['error'])) {
    http_response_code(422);
    echo json_encode(['error' => true, 'msg' => 'Could not fetch transaction: ' . ($txnInfo['msg'] ?? 'unknown'), 'transactionId' => $transactionId]);
    exit;
}

$eventType = 'net.authorize.payment.authcapture.created';
$uniq_key  = sha1($eventType . $transactionId);

$result = AuthorizeNet::processApprovedTransaction(
    $transactionId,
    $uniq_key,
    $eventType,
    []
);

$analysis = $result['analysis'] ?? [];

_error_log('[AuthorizeNet] Manual processTransaction result'
    . ' | transactionId=' . $transactionId
    . ' | error=' . (!empty($result['error']) ? 'true' : 'false')
    . ' | duplicate=' . (!empty($result['duplicate']) ? 'true' : 'false')
    . ' | users_id=' . ($analysis['users_id'] ?? 'n/a')
    . ' | amount=' . ($analysis['amount'] ?? 'n/a')
    . ' | msg=' . ($result['msg'] ?? '')
);

echo json_encode([
    'transactionId' => $transactionId,
    'txnStatus'     => $txnInfo['status'] ?? null,
    'txnAmount'     => $txnInfo['amount'] ?? null,
    'txnUsers_id'   => $txnInfo['users_id'] ?? null,
    'isApproved'    => $txnInfo['isApproved'] ?? null,
    'duplicate'     => !empty($result['duplicate']),
    'error'         => !empty($result['error']),
    'msg'           => $result['msg'] ?? null,
    'logId'         => $result['logId'] ?? null,
    'analysis'      => [
        'users_id' => $analysis['users_id'] ?? null,
        'amount'   => $analysis['amount'] ?? null,
        'plans_id' => $analysis['plans_id'] ?? null,
    ],
]);
