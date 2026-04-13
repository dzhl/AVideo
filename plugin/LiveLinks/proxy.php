<?php
$doNotConnectDatabaseIncludeConfig = 1;
$doNotStartSessionbaseIncludeConfig = 1;
require_once '../../videos/configuration.php';
_session_write_close();
_mysql_close();


/*
 * this file is to handle HTTP URLs into HTTPS
 */
if (!filter_var($_GET['livelink'], FILTER_VALIDATE_URL) || !preg_match("/^http.*/i", $_GET['livelink'])) {
    echo "Invalid Link";
    exit;
}

// Block same-origin URLs: proxying own content would let the token injected by
// addGlobalTokenIfSameDomain() bypass video access-control in view/hls.php.
// This proxy is only intended for external live streams (HTTP→HTTPS conversion).
if (isSameDomainAsMyAVideo($_GET['livelink'])) {
    echo "Access denied: Same-origin proxying not allowed";
    exit;
}

// SSRF Protection: Block requests to internal/private networks.
// $resolvedIP receives the validated IP so we can pin it in the cURL call below,
// eliminating the DNS TOCTOU race between validation and TCP connect.
$resolvedIP = null;
if (!isSSRFSafeURL($_GET['livelink'], $resolvedIP)) {
    _error_log("LiveLinks proxy: SSRF protection blocked URL: " . $_GET['livelink']);
    echo "Access denied: URL targets restricted network";
    exit;
}

header("Content-Type: video/vnd.mpegurl");
header("Content-Disposition: attachment;filename=playlist.m3u");

$_GET['livelink'] = addGlobalTokenIfSameDomain($_GET['livelink']);

/**
 * Fetch a URL through a DNS-pinned cURL request, following redirects manually
 * so every hop is re-validated with isSSRFSafeURL() and re-pinned via CURLOPT_RESOLVE.
 *
 * This eliminates the DNS TOCTOU race: gethostbyname() is called once per hop
 * (inside isSSRFSafeURL), and that same IP is forced into the TCP connection via
 * CURLOPT_RESOLVE — no second DNS lookup can occur between validation and connect.
 *
 * @param string $url           The initial URL to fetch.
 * @param string|null $pinnedIP The pre-validated IP returned by isSSRFSafeURL().
 * @param int $maxRedirects     Maximum number of redirects to follow.
 * @return array{content:string,finalUrl:string}|false
 */
function proxyDNSPinnedFetch($url, $pinnedIP, $maxRedirects = 5)
{
    if (!function_exists('curl_init')) {
        // cURL unavailable: fall back to the non-pinned path and log the degradation.
        // TOCTOU risk remains in this path; operators should ensure cURL is installed.
        _error_log("LiveLinks proxy: cURL unavailable, DNS pinning disabled for {$url}");
        $content = fakeBrowser($url);
        return $content !== false ? ['content' => $content, 'finalUrl' => $url] : false;
    }

    $currentUrl  = $url;
    $currentIP   = $pinnedIP;

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $host   = parse_url($currentUrl, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($currentUrl, PHP_URL_SCHEME));
        $port   = parse_url($currentUrl, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        $curlOpts = [
            CURLOPT_URL            => $currentUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,   // we handle each redirect ourselves
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Referer: localhost', 'Accept-Language: en', 'Cookie: foo=bar'],
            CURLOPT_HEADER         => true,    // include response headers so we can read Location:
        ];

        // Pin the validated IP — CURLOPT_RESOLVE format: "hostname:port:ip"
        // cURL uses this map instead of re-resolving the hostname, closing the TOCTOU window.
        if (!empty($currentIP)) {
            $curlOpts[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$currentIP}"];
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curlOpts);
        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false || $httpCode === 0) {
            _error_log("LiveLinks proxy: cURL fetch failed for {$currentUrl}");
            return false;
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $body            = substr($response, $headerSize);

        if ($httpCode >= 300 && $httpCode < 400) {
            // Parse the single Location header from the raw response headers.
            if (!preg_match('/^Location:\s*(.+)$/im', $responseHeaders, $m)) {
                _error_log("LiveLinks proxy: 3xx with no Location header at {$currentUrl}");
                return false;
            }
            $redirectUrl = trim($m[1]);

            // Validate redirect target and get its pinned IP for the next hop.
            $nextIP = null;
            if (!isSSRFSafeURL($redirectUrl, $nextIP)) {
                _error_log("LiveLinks proxy: SSRF protection blocked redirect to: {$redirectUrl}");
                return false;
            }

            $currentUrl = $redirectUrl;
            $currentIP  = $nextIP;
            continue;
        }

        return ['content' => $body, 'finalUrl' => $currentUrl];
    }

    _error_log("LiveLinks proxy: too many redirects for {$url}");
    return false;
}

$fetchResult = proxyDNSPinnedFetch($_GET['livelink'], $resolvedIP);
if ($fetchResult === false) {
    echo "Access denied or fetch failed";
    exit;
}

$content  = $fetchResult['content'];
$finalUrl = $fetchResult['finalUrl'];

// Preserve the original base-URL logic for relative path resolution in m3u8 playlists.
if ($finalUrl !== $_GET['livelink']) {
    // URL was redirected — use scheme://host:port as the base (no trailing path).
    $urlinfo = parse_url($finalUrl);
    $_GET['livelink'] = "{$urlinfo["scheme"]}://{$urlinfo["host"]}:{$urlinfo["port"]}";
    unset($pathinfo);
} else {
    $pathinfo = pathinfo($_GET['livelink']);
}
if($content === "Empty Token"){
    die("Empty Token on URL {$_GET['livelink']}");
}else{
    foreach (preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
        $line = trim($line);
        if (!empty($line) && $line[0] !== "#") {
            if (!filter_var($line, FILTER_VALIDATE_URL)) {
                if (!empty($pathinfo["extension"])) {
                    $_GET['livelink'] = str_replace($pathinfo["basename"], "", $_GET['livelink']);
                }
                $line = $_GET['livelink'] . $line;
            }
        }
        echo $line . PHP_EOL;
    }
}
