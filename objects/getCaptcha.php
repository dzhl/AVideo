<?php
require_once 'captcha.php';

$largura       = isset($_GET['l'])  ? max(80,  min(400, (int)$_GET['l']))  : 120;
$altura        = isset($_GET['a'])  ? max(20,  min(200, (int)$_GET['a']))  : 40;
$tamanho_fonte = isset($_GET['tf']) ? max(10,  min(40,  (int)$_GET['tf'])) : 18;
$quantidade_letras = isset($_GET['ql']) ? max(5, min(8, (int)$_GET['ql'])) : 5;

$capcha = new Captcha($largura, $altura, $tamanho_fonte, $quantidade_letras);
$capcha->getCaptchaImage();
