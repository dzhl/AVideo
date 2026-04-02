<?php
require_once '../../videos/configuration.php';
if (!User::isAdmin()) {
    $obj = new stdClass();
    $obj->msg = "You can't do this";
    die(json_encode($obj));
}
include '../../videos/cache/clones/client.log';
