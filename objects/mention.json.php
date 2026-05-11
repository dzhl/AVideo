<?php
global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
require_once $global['systemRootPath'] . 'videos/configuration.php';
require_once $global['systemRootPath'] . 'objects/user.php';
header('Content-Type: application/json');

$_POST['current'] = 1;
$_REQUEST['rowCount'] = 10;

$response = [];

// CVE-2026-43881 sibling fix: gate unauthenticated callers before reaching
// User::getAllUsers(). Without this, the unconditional $ignoreAdmin = true below
// bypasses the admin gate inside getAllUsers(), leaking user id / email / channel
// data to any unauthenticated network attacker who sends term=@<probe>.
// Mirror the canonical fix shape from objects/users.json.php (commit d9cdc7024).
if (!User::isLogged() && !canSearchUsers()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode($response);
    exit;
}

if(preg_match('/^@/', $_REQUEST['term'])){
    $_GET['searchPhrase'] = xss_esc(substr($_REQUEST['term'], 1));
    // Allow any logged-in user to query mentions (needed for @mention autocomplete);
    // unauthenticated callers are blocked by the gate above.
    $ignoreAdmin = (User::isLogged() || canSearchUsers()) ? true : false;
    $users = User::getAllUsers($ignoreAdmin, ['name', 'email', 'user', 'channelName'], 'a');
    foreach ($users as $key => $value) {
        $response[] = [
            'id'=>$value['id'],
            'value'=>$value['identification'],
            'label'=>Video::getCreatorHTML($value['id'], '', true, true)
            ];
    }
}

echo json_encode($response);
