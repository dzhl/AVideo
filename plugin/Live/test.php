<?php
require_once dirname(__FILE__) . '/../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/Live/Objects/Live_servers.php';

if (!User::isAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

$timeStarted = microtime(true);

$statsURL = $_REQUEST['statsURL'];
if (empty($statsURL) || $statsURL == "php://input" || !preg_match("/^https?:\/\//i", $statsURL)) {
    liveStatsTestLog('this is not a URL ');
    exit;
}
if (!liveIsAllowedStatsTestURL($statsURL)) {
    liveStatsTestLog('this URL is not allowed ' . htmlentities($statsURL));
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

liveStatsTestLog('Starting try to get URL ' . $statsURL);

$result = liveStatsTestUrlGetContents($statsURL, 2);

if ($result) {
    liveStatsTestLog('<span style="background-color: green; padding: 1px 4px; color: #FFF;">SUCCESS</span>');
} else {
    liveStatsTestLog('<span style="background-color: red; padding: 1px 4px; color: #FFF;">FAIL</span>');
}

liveStatsTestLog('Finish try to get URL ');

$timeElapsed = number_format(microtime(true) - $timeStarted, 5);
if ($timeElapsed>=2) {
    liveStatsTestLog('IMPORTANT: your stats took longer than 2 seconds to respond, the Streamer has a 2 seconds timeout rule ');
}

function liveStatsTestUrlGetContents($url, $timeout = 0)
{
    liveStatsTestLog('liveStatsTestUrlGetContents start timeout=' . $timeout);
    $agent = "AVideoStreamer";

    $opts = [
        'http' => ['header' => "User-Agent: {$agent}\r\n"],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
            "allow_self_signed" => true,
        ],
    ];
    if (!empty($timeout)) {
        ini_set('default_socket_timeout', $timeout);
        $opts['http'] = ['timeout' => $timeout];
    }

    $context = stream_context_create($opts);

    if (ini_get('allow_url_fopen')) {
        try {
            $tmp = file_get_contents($url, false, $context);
            liveStatsTestLog('file_get_contents:: '.htmlentities($tmp));
            if (empty($tmp)) {
                liveStatsTestLog('file_get_contents fail return an empty content');
                return false;
            } else {
                liveStatsTestLog('file_get_contents works');
                return true;
            }
        } catch (ErrorException $e) {
            liveStatsTestLog('file_get_contents fail catch error: ' . $e->getMessage());
            return false;
        }
    } elseif (function_exists('curl_init')) {
        liveStatsTestLog('allow_url_fopen is NOT enabled but curl_init is, we will try CURL');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if (!empty($timeout)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout + 10);
        }
        $output = curl_exec($ch);
        curl_close($ch);
        liveStatsTestLog('curl_init:: '.htmlentities($output));
        if (empty($output)) {
            liveStatsTestLog('curl_init fail to download');
            return false;
        } else {
            liveStatsTestLog('curl_init success to download');
            return true;
        }
    } else {
        liveStatsTestLog('IMPORTANT: allow_url_fopen is NOT enabled also curl_init is NOT enable, please investigate it and make sure it is enabled');
    }


    liveStatsTestLog('Try wget');
    // try wget
    $tmpDir = sys_get_temp_dir();
    if (empty($tmpDir)) {
        liveStatsTestLog('IMPORTANT: your sys_get_temp_dir is empty');
        return false;
    }
    if (!is_writable($tmpDir)) {
        liveStatsTestLog('IMPORTANT: we cannot write in your temp directory ' . $tmpDir);
        return false;
    }
    $tmpDir = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $filename = $tmpDir . md5($url);
    if (liveStatsTestWget($url, $filename)) {
        $result = file_get_contents($filename);
        liveStatsTestLog('wget:: '.htmlentities($result));
        unlink($filename);
        if (!empty($result)) {
            liveStatsTestLog('wget works ');
            return true;
        } else {
            liveStatsTestLog('wget fail ');
        }
    }
    unlink($filename);

    return false;
}

function liveIsAllowedStatsTestURL($url)
{
    $url = liveNormalizeStatsTestURL($url);
    if (empty($url)) {
        return false;
    }

    $allowedUrls = [];
    if (isDocker()) {
        $allowedUrls[] = getDockerStatsURL();
    }

    $liveObj = AVideoPlugin::getDataObject('Live');
    if (!empty($liveObj->stats)) {
        $allowedUrls[] = $liveObj->stats;
    }

    $servers = Live_servers::getAll();
    if (is_array($servers)) {
        foreach ($servers as $server) {
            if (!empty($server['stats_url'])) {
                $allowedUrls[] = $server['stats_url'];
            }
        }
    }

    foreach ($allowedUrls as $allowedUrl) {
        if ($url === liveNormalizeStatsTestURL($allowedUrl)) {
            return true;
        }
    }

    return false;
}

function liveNormalizeStatsTestURL($url)
{
    if (!is_string($url)) {
        return '';
    }
    return rtrim(trim($url), '/');
}

function liveStatsTestWget($url, $filename)
{
    if (empty($url) || $url == "php://input" || !preg_match("/^https?:\/\//i", $url)) {
        liveStatsTestLog('this is not a URL ');
        return false;
    }
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        liveStatsTestLog('this is a windows OS ');
        return false;
    }
    $cmd = "wget --tries=1 " . escapeshellarg($url) . " -O " . escapeshellarg($filename) . " --no-check-certificate";
    exec($cmd);
    if (!file_exists($filename)) {
        liveStatsTestLog('wget download fail, we cannot read the file: ' . $filename);
        return false;
    }
    if (empty(filesize($filename))) {
        liveStatsTestLog('wget download fail, the file is empty: ' . $filename);
        return false;
    } else {
        liveStatsTestLog('wget download success, the file is NOT empty: ' . $filename);
        return true;
    }
}

function liveStatsTestLog($msg)
{
    global $timeStarted;
    $timeElapsed = number_format(microtime(true) - $timeStarted, 5);
    echo '[' . date('Y-m-d H:i:s') . "] Time Elapsed: {$timeElapsed} seconds - " . $msg . '<br>' . PHP_EOL;
}
