<?php
$configFile = __DIR__.'/../../videos/configuration.php';
require_once $configFile;
header('Content-Type: application/json');

if (!User::isAdmin()) {
    forbiddenPage('Must be admin');
}

$obj = testFFMPEGRemote();

die(json_encode($obj));
