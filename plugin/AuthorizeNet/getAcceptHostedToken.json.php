<?php

use Google\Service\ServiceControl\Auth;

require_once __DIR__ . '/../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/AuthorizeNet/AuthorizeNet.php';
require_once $global['systemRootPath'] . 'plugin/AuthorizeNet/Objects/Anet_pending_payment.php';
header('Content-Type: application/json');

if(!User::isLogged()){
    forbiddenPage('You must be logged out to access this endpoint');
}

try {
    $obj = AVideoPlugin::getDataObject('AuthorizeNet');
    $users_id = User::getId();

    // ========== Validate payment amount ==========
    if (!empty($_REQUEST['plans_id']) && AVideoPlugin::isEnabledByName('Subscription')) {
       $sp = new SubscriptionPlansTable($_REQUEST['plans_id']);
       $amount = $sp->getPrice();
    }
    if(empty($amount)){
        $amount = isset($_REQUEST['amount']) ? floatval($_REQUEST['amount']) : 0;
    }
    if ($amount <= 0) {
        echo json_encode(['error' => true, 'msg' => 'Invalid amount', 'line' => __LINE__]);
        exit;
    }

    // ========== Add optional metadata ==========
    $metadata = [];
    $metadata['users_id'] = User::getId();
    $metadata['plans_id'] = $_REQUEST['plans_id'] ?? 0;

    $pending = AuthorizeNet::createPendingPayment((int)$users_id, (float)$amount, $metadata);
    if (!empty($pending['error'])) {
        echo json_encode(['error' => true, 'msg' => $pending['msg'] ?? 'Could not create pending payment', 'line' => __LINE__]);
        exit;
    }
    $metadata = $pending['metadata'];

    $_SESSION['AuthorizeNetAcceptHostedPending'] = [
        'pending_id'  => (int)$pending['id'],
        'ref_id'      => $pending['refId'],
        'users_id'   => (int)$users_id,
        'plans_id'   => (int)$metadata['plans_id'],
        'amount'     => round((float)$amount, 2),
        'created_at' => time(),
    ];

    $webhookCheck = AuthorizeNet::createWebhookIfNotExists();
    if (!empty($webhookCheck['error'])) {
        _error_log('[AuthorizeNet] createWebhookIfNotExists warning on token generation: ' . ($webhookCheck['msg'] ?? 'unknown'));
    }

    // ========== Process payment via SDK using Accept opaque token + metadata ==========
    $result = AuthorizeNet::generateHostedPaymentPage($amount, $metadata);
    if (empty($result['error']) && !empty($result['token']) && !empty($result['url'])) {
        echo json_encode([
            'error' => false,
            'msg'   => 'Payment created successfully',
            'transactionId' => $result['transactionId'] ?? null,
            'token' => $result['token'],
            'url' => $result['url'],
            'refId' => $pending['refId'],
            'line'  => __LINE__
        ]);
        exit;
    }

    Anet_pending_payment::markChecked((int)$pending['id'], 'pending', $result['msg'] ?? 'Could not create hosted payment page');

    // ========== Return error response if payment fails ==========
    echo json_encode([
        'error' => !isset($result['error']) || !empty($result['error']),
        'msg'   => $result['msg'] ?? '',
        'result'   => $result,
        'line'  => __LINE__,
        'url'   => $result['url'] ?? '',
        'token'   => $result['token'] ?? '',
        'refId'   => $pending['refId'],
    ]);
    exit;

} catch (Exception $e) {
    // ========== Return exception error ==========
    echo json_encode([
        'error' => true,
        'msg'   => $e->getMessage(),
        'line'  => __LINE__
    ]);
    exit;
}
