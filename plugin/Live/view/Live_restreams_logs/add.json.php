<?php
$global['skipAutoCSRFCheck'] = true;
header('Content-Type: application/json');
require_once '../../../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/Live/Objects/Live_restreams_logs.php';

$obj = new stdClass();
$obj->error = true;
$obj->msg = "";

$plugin = AVideoPlugin::loadPluginIfEnabled('Live');


if(empty($_POST['responseToken'])){
    $request = file_get_contents("php://input");
    _error_log("restreamer log add.json.php php://input {$request}", AVideoLog::$DEBUG, true);
    $robj = json_decode($request);
    foreach ($robj as $key => $value) {
        $_POST[$key] = object_to_array($value);
    }
}

$string = decryptString($_POST['responseToken']);

if(empty($string)){
   forbiddenPage('Invalid responseToken');
   _error_log("Invalid responseToken {$_POST['responseToken']}", AVideoLog::$WARNING, true);
}

$token = json_decode($string);

if(!User::isAdmin()){
    if(empty($token->users_id)){
        forbiddenPage('Invalid token');
    }
    if($token->time < strtotime('-10 minutes')){
        forbiddenPage('Token expired');
    }
}

_error_log('add.json.php restream log POST '.json_encode($_POST), AVideoLog::$DEBUG, true);
_error_log('add.json.php restream log token '.json_encode($token), AVideoLog::$DEBUG, true);

// SSRF protection: validate restreamerURL host+port against every admin-configured
// restreamer endpoint. The submitted URL is the restreamer's own self-reported address
// (built from $_SERVER['HTTP_HOST'] in restreamer.json.php), so it must match one of
// the hosts the admin explicitly configured. This prevents an authenticated streamer
// from storing an arbitrary URL and causing the server to fetch internal resources.
// We do NOT use isSSRFSafeURL() here because legitimate single-server deployments
// use http://localhost/ as the restreamer address, which that function would block.
if (!empty($_POST['restreamerURL']) && !empty($plugin)) {
    $submittedScheme = strtolower(parse_url($_POST['restreamerURL'], PHP_URL_SCHEME));
    $submittedHost   = strtolower(parse_url($_POST['restreamerURL'], PHP_URL_HOST));
    $submittedPort   = parse_url($_POST['restreamerURL'], PHP_URL_PORT) ?: ($submittedScheme === 'https' ? 443 : 80);
    $submittedKey    = "{$submittedHost}:{$submittedPort}";

    $allowedKeys = [];

    // Primary configured restreamer (Live plugin settings)
    $primaryURL = Live::getRestreamer();
    if (!empty($primaryURL)) {
        $h = strtolower(parse_url($primaryURL, PHP_URL_HOST));
        $p = parse_url($primaryURL, PHP_URL_PORT) ?: (strtolower(parse_url($primaryURL, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
        $allowedKeys[] = "{$h}:{$p}";
    }

    // Per-server restreamer URLs (Live_servers plugin setting)
    $liveObj = AVideoPlugin::getObjectData('Live');
    if (!empty($liveObj->useLiveServers)) {
        require_once $global['systemRootPath'] . 'plugin/Live/Objects/Live_servers.php';
        $servers = Live_servers::getAllActive();
        if (!empty($servers)) {
            foreach ($servers as $row) {
                if (empty($row['restreamerURL'])) {
                    continue;
                }
                $h = strtolower(parse_url($row['restreamerURL'], PHP_URL_HOST));
                $p = parse_url($row['restreamerURL'], PHP_URL_PORT) ?: (strtolower(parse_url($row['restreamerURL'], PHP_URL_SCHEME)) === 'https' ? 443 : 80);
                $allowedKeys[] = "{$h}:{$p}";
            }
        }
    }

    if (empty($allowedKeys)) {
        // No restreamer configured by admin — cannot validate; block to fail-closed.
        _error_log("add.json.php: no configured restreamer URLs found, rejecting restreamerURL submission", AVideoLog::$WARNING, true);
        forbiddenPage('No restreamer configured');
    }

    if (!in_array($submittedKey, $allowedKeys, true)) {
        _error_log("add.json.php: restreamerURL host not allowed. submitted={$submittedKey} allowed=" . implode(',', $allowedKeys), AVideoLog::$WARNING, true);
        forbiddenPage('restreamerURL host not allowed');
    }
}

$o = new Live_restreams_logs(@$_POST['id']);
$o->setRestreamer($_POST['restreamerURL']);
$o->setM3u8($_POST['m3u8']);
$o->setLogFile($_POST['logFile']);
$o->setJson(json_encode($_POST));
$o->setLive_transmitions_history_id($token->liveTransmitionHistory_id);
$o->setLive_restreams_id($_POST['live_restreams_id']);

if($id = $o->save()){
    $obj->error = false;
}

echo json_encode($obj);
