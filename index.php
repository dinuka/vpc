<?php

require_once 'config/config.php';
require_once 'controllers/paymentController.php';

$payment = new paymentController();

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, 'terms') !== false) {
    $payment->termsAction();
} elseif (strpos($uri, 'processLkr') !== false) {
    $payment->processLkrAction();
} elseif (strpos($uri, 'processUsd') !== false) {
    $payment->processUsdAction();
} elseif (strpos($uri, 'confirm') !== false) {
    $payment->confirmAction();
} else {
    $payment->checkoutAction();
}

