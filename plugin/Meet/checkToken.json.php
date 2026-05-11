<?php
header('Content-Type: application/json');

if (!isset($global['systemRootPath'])) {
    $configFile = '../../videos/configuration.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    }
}
$objM = AVideoPlugin::getObjectDataIfEnabled("Meet");
//_error_log(json_encode($_SERVER));
if (empty($objM)) {
    die("Plugin disabled");
}

// Only admins may probe whether a secret matches — this endpoint is a
// confirmation oracle and must not be reachable by unauthenticated callers.
if (!User::isAdmin()) {
    forbiddenPage('Admin only');
}

$obj = new stdClass();
$obj->error = true;
$obj->msg = "";
$obj->match = false;

if (empty($_GET['secret'])) {
    $obj->msg = "Empty Token";
    die(json_encode($obj));
}

// Constant-time comparison to prevent byte-by-byte timing analysis.
if (hash_equals($objM->secret, $_GET['secret'])) {
    $obj->msg = "Token and secret match";
    $obj->error = false;
    $obj->match = true;
} else {
    $obj->msg = "Different token and secret";
}

die(json_encode($obj));
