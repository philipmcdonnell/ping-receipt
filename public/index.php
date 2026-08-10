<?php

declare(strict_types=1);

use Ping\ClientIdentity;
use Ping\Config;
use Ping\Database;
use Ping\DeliveryException;
use Ping\ImageProcessor;
use Ping\MessageValidator;
use Ping\PingService;
use Ping\PrinterFactory;
use Ping\RateLimiter;
use Ping\ReceiptRepository;
use Ping\ValidationException;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = Config::fromEnvironment($root);
date_default_timezone_set($config->timezone);
ini_set('session.use_strict_mode', '1');
$secure = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secure,
    'samesite' => 'Lax',
]);
session_start();

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$pdo = Database::connect($config->databasePath);
$service = new PingService(
    new MessageValidator($config->maxMessageLength),
    new ImageProcessor(
        $config->uploadPath,
        $config->maxImageBytes,
        $config->maxImagePixels,
        $config->printerWidth,
    ),
    new ReceiptRepository($pdo),
    new RateLimiter($pdo, $config->rateLimit, $config->rateWindowSeconds),
    PrinterFactory::create($config),
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'The form expired. Please try again.'];
        header('Location: /', true, 303);
        exit;
    }

    try {
        $transaction = $service->send(
            is_string($_POST['message'] ?? null) ? $_POST['message'] : null,
            is_array($_FILES['image'] ?? null) ? $_FILES['image'] : null,
            ClientIdentity::hash($_SERVER),
        );
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "Your PING #{$transaction} printed successfully. WooHoo!",
        ];
        $_SESSION['old_message'] = null;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    } catch (ValidationException $exception) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $exception->getMessage()];
        $_SESSION['old_message'] = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
    } catch (DeliveryException $exception) {
        error_log($exception->__toString());
        $_SESSION['flash'] = ['type' => 'error', 'message' => $exception->getMessage()];
        $_SESSION['old_message'] = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'PING could not process that request. Please try again later.'];
        $_SESSION['old_message'] = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
    }
    header('Location: /', true, 303);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
$oldMessage = $_SESSION['old_message'] ?? '';
unset($_SESSION['flash'], $_SESSION['old_message']);
$csrf = $_SESSION['csrf'];
$maxMessageLength = $config->maxMessageLength;
$maxImageMegabytes = (int) floor($config->maxImageBytes / 1024 / 1024);

require $root . '/templates/form.php';
