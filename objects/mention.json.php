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
    // $ignoreAdmin = true: all active logged-in users may see the full user list for
    // autocomplete. Unauthenticated callers are already blocked by the gate above.
    // email intentionally excluded from searchFields: @mention autocomplete has no
    // legitimate need to match on email, and including it would let any logged-in
    // user enumerate accounts by email domain (e.g. @gmail.com).
    $ignoreAdmin = true;
    $users = User::getAllUsers($ignoreAdmin, ['name', 'user', 'channelName'], 'a');
    foreach ($users as $key => $value) {
        $response[] = [
            'id'=>$value['id'],
            'value'=>$value['identification'],
            'label'=>Video::getCreatorHTML($value['id'], '', true, true)
            ];
    }
}

echo json_encode($response);
