<?php
header('Content-Type: application/json');
global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
$obj = new stdClass();
$obj->error = true;
$obj->url = '';
$obj->status = 'error';
$obj->msg = '';
if (!User::isLogged()) {
    $obj->msg = __("You must be logged");
    die(json_encode($obj));
}
forbidIfIsUntrustedRequest('userSavePhoto');
$imagePath = "videos/userPhoto/";

//Check write Access to Directory
$dirPath = $global['systemRootPath'].$imagePath;
if (!file_exists($dirPath)) {
    mkdir($global['systemRootPath'].$imagePath, 0755, true);
}
/*
if (!is_writable($dirPath)) {
    $obj->msg = __("No write Access on folder").' '.$dirPath;
    die(json_encode($obj));
}
*/
$fileData = getValidatedImageBinaryFromBase64($_POST['imgBase64'] ?? '', 2 * 1024 * 1024, $errorMsg);
if ($fileData === false) {
    $obj->msg = $errorMsg;
    die(json_encode($obj));
}

$fileName = 'photo'. User::getId().'.png';
$photoURL = $imagePath.$fileName;

$obj->url = $photoURL;

$bytes = file_put_contents($global['systemRootPath'].$photoURL, $fileData);
if ($bytes) {
    $obj->status = 'success';
    $obj->error = false;
} else {
    $obj->msg = __("We could not save this file");
}

if ($bytes) {
    $user = new User(User::getId());
    $user->setPhotoURL($photoURL);
    if ($user->save()) {
        User::deleteOGImage(User::getId());
        User::updateSessionInfo();
        clearCache(true);
    }
}
die(json_encode($obj));
