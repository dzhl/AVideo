<?php
require_once __DIR__ . '/../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/AuthorizeNet/AuthorizeNet.php';
$global['bypassSameDomainCheck'] = 1;

$rawBody = file_get_contents('php://input');
$headers = getallheaders();

// Log every incoming request immediately so we have a trace even if something crashes below.
_error_log('[Authorize.Net webhook] RECEIVED'
    . ' | body_len=' . strlen($rawBody)
    . ' | sig=' . ($headers['X-ANET-Signature'] ?? ($headers['x-anet-signature'] ?? 'missing'))
    . ' | body_preview=' . substr($rawBody, 0, 600)
);

// 1) Parse + signature — reject immediately if signature is invalid
$parsed = AuthorizeNet::parseWebhookRequest($rawBody, $headers);
if (!empty($parsed['error'])) {
    _error_log('[Authorize.Net webhook] IGNORED: ' . ($parsed['msg'] ?? 'unknown')
        . ' | eventType=' . ($parsed['eventType'] ?? 'n/a'));
    http_response_code(200);
    echo $parsed['msg'] ?? 'ignored';
    exit;
}
if (!$parsed['signatureValid']) {
    $sigHeader = $headers['X-ANET-Signature'] ?? ($headers['x-anet-signature'] ?? '');
    _error_log('[Authorize.Net webhook] FAIL: Bad signature'
        . ' | event=' . $parsed['eventType']
        . ' | txn=' . ($parsed['transactionId'] ?? 'n/a')
        . ' | body_len=' . strlen($rawBody)
        . ' | sig_header=' . (empty($sigHeader) ? 'missing' : 'present')
    );
    http_response_code(401);
    echo 'invalid signature';
    exit;
}

_error_log('[Authorize.Net webhook] Signature OK'
    . ' | eventType=' . $parsed['eventType']
    . ' | transactionId=' . ($parsed['transactionId'] ?? 'null')
    . ' | amount=' . ($parsed['amount'] ?? 'null')
    . ' | uniq_key=' . $parsed['uniq_key']
);

// 2) Guard: transactionId must be present.
// Authorize.Net test pings and some sandbox events may omit it — acknowledge with 200 so the
// webhook is not retried/deactivated, but do not process any balance change.
if (empty($parsed['transactionId'])) {
    _error_log('[Authorize.Net webhook] IGNORED: transactionId is null/empty'
        . ' | eventType=' . $parsed['eventType']
        . ' | payload=' . json_encode($parsed['payload'])
        . ' | Likely a test ping — returning 200 so Authorize.Net does not retry'
    );
    $reconcile = AuthorizeNet::reconcilePendingPayments(20, 7200);
    _error_log('[Authorize.Net webhook] Reconcile after ping'
        . ' | total=' . ($reconcile['total'] ?? 0)
    );
    http_response_code(200);
    echo 'no transactionId - ignored';
    exit;
}

// 3) Process the transaction using Authorize.Net transaction details as source of truth.
$result = AuthorizeNet::processApprovedTransaction(
    $parsed['transactionId'],
    $parsed['uniq_key'],
    $parsed['eventType'],
    $parsed['payload']
);

if (!empty($result['duplicate'])) {
    _error_log('[Authorize.Net webhook] DUPLICATE: already processed uniq_key=' . $parsed['uniq_key']);
    http_response_code(200);
    echo 'duplicate';
    exit;
}

if (!empty($result['error'])) {
    _error_log('[Authorize.Net webhook] FAIL: processApprovedTransaction error: ' . ($result['msg'] ?? ''));
    http_response_code(500);
    echo json_encode($result);
    exit;
}

$_analysis = $result['analysis'] ?? [];
_error_log('[Authorize.Net webhook] SUCCESS: wallet credited'
    . ' | txn=' . $parsed['transactionId']
    . ' | users_id=' . ($_analysis['users_id'] ?? 'null')
    . ' | amount=' . ($_analysis['amount'] ?? 'null')
    . ' | logId=' . ($result['logId'] ?? 'n/a')
);

http_response_code(200);
echo json_encode(['success' => true, 'logId' => $result['logId'] ?? null]);
