<?php
// check recurrent payments
header('Content-Type: application/json');

if (empty($global['systemRootPath'])) {
    $global['systemRootPath'] = '../../';
}
require_once $global['systemRootPath'] . 'videos/configuration.php';
require_once $global['systemRootPath'] . 'objects/user.php';

_error_log("PayPalIPN Start");
$plugin = AVideoPlugin::loadPluginIfEnabled("YPTWallet");
$walletObject = AVideoPlugin::getObjectData("YPTWallet");
$paypal = AVideoPlugin::loadPluginIfEnabled("PayPalYPT");

$ipn = PayPalYPT::IPNcheck();
if (!$ipn) {
    die("IPN Fail");
}
$obj= new stdClass();
$obj->error = true;
if (empty($_POST["recurring_payment_id"])) {
    _error_log("PayPalIPN: recurring_payment_id EMPTY ");
    $users_id = User::getId();

    $invoiceNumber = uniqid();

    $payment = $paypal->execute();
    //var_dump($amount);
    if (!empty($payment)) {
        $amount = PayPalYPT::getAmountFromPayment($payment);
        $plugin->addBalance($users_id, $amount->total, "Paypal payment", "PayPalIPN");
        $obj->error = false;
        _error_log("PayPalIPN: Executed ".json_encode($payment));
    //header("Location: {$global['webSiteRootURL']}plugin/YPTWallet/view/addFunds.php?status=success");
    } else {
        _error_log("PayPalIPN: Fail");
        //header("Location: {$global['webSiteRootURL']}plugin/YPTWallet/view/addFunds.php?status=fail");
    }
} else {
    _error_log("PayPalIPN: recurring_payment_id = {$_POST["recurring_payment_id"]} ");

    // Deduplication: use txn_id when present (most specific), fall back to verify_sign.
    // verify_sign is a per-notification cryptographic signature — unique per authentic IPN —
    // so replaying the same captured POST body always carries the same verify_sign.
    // This mirrors the approach used in ipnV2.php (IPN branch).
    $dedup_key = !empty($_POST['txn_id']) ? $_POST['txn_id'] : $_POST['verify_sign'];
    if (PayPalYPT::isRecurringPaymentIdUsed($dedup_key)) {
        _error_log("PayPalIPN: already processed (dedup_key={$dedup_key}), skipping");
        die(json_encode($obj));
    }

    // check for the recurrement payment
    $subscription = AVideoPlugin::loadPluginIfEnabled("Subscription");
    if (!empty($subscription)) {
        $row = Subscription::getFromAgreement($_POST["recurring_payment_id"]);
        _error_log("PayPalIPN: user found from recurring_payment_id (users_id = {$row['users_id']}) ");
        $users_id = $row['users_id'];
        $payment_amount = empty($_POST['mc_gross']) ? $_POST['amount'] : $_POST['mc_gross'];
        $payment_currency = empty($_POST['mc_currency']) ? $_POST['currency_code'] : $_POST['mc_currency'];
        if ($walletObject->currency===$payment_currency) {
            $pp = new PayPalYPT_log(0);
            $pp->setUsers_id($users_id);
            $pp->setRecurring_payment_id($dedup_key);
            $pp->setValue($payment_amount);
            $pp->setJson(['post' => $_POST]);
            if ($pp->save()) {
                $plugin->addBalance($users_id, $payment_amount, "Paypal recurrent", json_encode($_POST));
                Subscription::renew($users_id, $row['subscriptions_plans_id']);
                $obj->error = false;
            }
        } else {
            _error_log("PayPalIPN: FAIL currency check $walletObject->currency===$payment_currency ");
        }
    }
}

_error_log("PayPalIPN: ".json_encode($obj));
_error_log("PayPalIPN: POST ".json_encode($_POST));
_error_log("PayPalIPN: GET ".json_encode($_GET));
_error_log("PayPalIPN END");
