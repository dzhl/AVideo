<?php
require_once 'captcha.php';

// CAPTCHAs are for human users only. Web crawlers and bots must never reach this.
// This blocks subnet-distributed crawlers (e.g. meta-externalagent) that bypass
// per-IP rate limiting by rotating across dozens of IPs in the same /24.
if (isBot(true)) {
    http_response_code(403);
    exit;
}

// Per-IP rate limit as a second layer against non-identified bots or abusive humans.
enforceRateLimit('getCaptcha', 10, 60); // 10 images / 60 s / IP → HTTP 429 on excess

$largura       = isset($_GET['l'])  ? max(80,  min(400, (int)$_GET['l']))  : 120;
$altura        = isset($_GET['a'])  ? max(20,  min(200, (int)$_GET['a']))  : 40;
$tamanho_fonte = isset($_GET['tf']) ? max(10,  min(40,  (int)$_GET['tf'])) : 18;
$quantidade_letras = isset($_GET['ql']) ? max(5, min(8, (int)$_GET['ql'])) : 5;

$capcha = new Captcha($largura, $altura, $tamanho_fonte, $quantidade_letras);
$capcha->getCaptchaImage();
