<?php
// No allowOrigin() call: this endpoint is consumed by same-origin JavaScript
// only (see view/js/session.js). Omitting CORS headers means the browser's
// same-origin policy already blocks cross-origin reads, preventing any
// third-party site from fetching the session ID via a credentialed request.
header('Content-Type: application/json');

function getAVideoSessionNameFromConfig()
{
    $configFile = __DIR__ . '/../videos/configuration.php';
    $systemRootPath = '';

    if (is_readable($configFile)) {
        $config = file_get_contents($configFile);
        if (preg_match('/\$global\s*\[\s*[\'"]systemRootPath[\'"]\s*\]\s*=\s*([\'"])(.*?)\1\s*;/', $config, $matches)) {
            $systemRootPath = stripcslashes($matches[2]);
        }
    }

    if (empty($systemRootPath)) {
        $realPath = realpath(__DIR__ . '/..');
        if (!empty($realPath)) {
            $systemRootPath = str_replace('\\', '/', $realPath);
        }
    }

    if (!empty($systemRootPath)) {
        $systemRootPath .= (substr($systemRootPath, -1) == '/' ? '' : '/');
        return md5($systemRootPath);
    }

    return session_name();
}

$sessionName = getAVideoSessionNameFromConfig();

$obj = new stdClass();
$obj->phpsessid = '';
if (!empty($_COOKIE[$sessionName])) {
    $obj->phpsessid = $_COOKIE[$sessionName];
} elseif (!empty($_COOKIE[session_name()])) {
    $obj->phpsessid = $_COOKIE[session_name()];
}
if (empty($obj->phpsessid) && !headers_sent()) {
    session_name($sessionName);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => $isSecure ? 'None' : 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $isSecure, true);
    }
    @session_start(['read_and_close' => true]);
    $obj->phpsessid = session_id();
}

echo json_encode($obj);
