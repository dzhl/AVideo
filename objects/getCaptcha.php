<?php
require_once 'captcha.php';

// Prevent bots from harvesting CAPTCHA images at high frequency.
// A legitimate user almost never needs more than a handful of CAPTCHAs per minute
// (page load + one or two retries). Bots scraping ~10/s are stopped immediately.
enforceRateLimit('getCaptcha', 10, 60); // 20 images / 60 s / IP → HTTP 429 on excess

$largura       = isset($_GET['l'])  ? max(80,  min(400, (int)$_GET['l']))  : 120;
$altura        = isset($_GET['a'])  ? max(20,  min(200, (int)$_GET['a']))  : 40;
$tamanho_fonte = isset($_GET['tf']) ? max(10,  min(40,  (int)$_GET['tf'])) : 18;
$quantidade_letras = isset($_GET['ql']) ? max(5, min(8, (int)$_GET['ql'])) : 5;

$capcha = new Captcha($largura, $altura, $tamanho_fonte, $quantidade_letras);
$capcha->getCaptchaImage();
