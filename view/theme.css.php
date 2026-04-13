<?php
$sessionName = 'themeCSSSession';
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    session_set_cookie_params([
        'path' => '/',
        'domain' => preg_replace('/:[0-9]+$/', '', preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? '')),
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None',
    ]);
} else {
    session_set_cookie_params(0, '/; SameSite=None', preg_replace('/:[0-9]+$/', '', preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? '')), true, true);
}
session_name($sessionName);
session_start();
if(!empty($_SESSION['theme'])){
    $theme = $_SESSION['theme'];
    $doNotStartSessionIncludeConfig = $doNotConnectDatabaseIncludeConfig = 1;
}
session_write_close();
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
header("Content-type: text/css; charset: UTF-8");
if(empty($theme)){
    $theme = getCurrentTheme();
    _mysql_close();
    _session_write_close();
    session_name($sessionName);
    session_start();
    $_SESSION['theme'] = $theme;
    _session_write_close();
    echo "/* theme = {$theme} from DB */".PHP_EOL;
}else{    
    echo "/* theme = {$theme} from Session */".PHP_EOL;
}
echo file_get_contents("{$global['systemRootPath']}view/css/custom/{$theme}.css");
exit;
/*
$filename = "{$global['systemRootPath']}videos/cache/custom.css";
if(file_exists($filename)){
    echo file_get_contents($filename);
}
 *
 */
