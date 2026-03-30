<?php
require_once '../../../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/{pluginName}/Objects/{classname}.php';
header('Content-Type: application/json');

if (!User::isAdmin()) {
    forbiddenPage("You can't do this");
}

$rows = {classname}::getAll();
$total = {classname}::getTotal();

$response = array(
    'data' => $rows,
    'draw' => intval(@$_REQUEST['draw']),
    'recordsTotal' => $total,
    'recordsFiltered' => $total,
);
echo _json_encode($response);
?>
