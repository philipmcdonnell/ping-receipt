<?php
declare(strict_types=1);

use Ping\Config;
use Ping\Database;
use Ping\PrinterFactory;
use Ping\ReceiptRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
$config = Config::fromEnvironment(dirname(__DIR__));
$repository = new ReceiptRepository(Database::connect($config->databasePath));
$printer = PrinterFactory::create($config);
$failed = $repository->findFailed();

if ($failed === []) {
    echo "No failed PINGs are waiting.\n";
    exit(0);
}

foreach ($failed as $ping) {
    try {
        $printer->print($ping['transaction_number'], $ping['message'] ?? '', $ping['image_path'] ?: null);
        $repository->markPrinted((int) $ping['id']);
        if ($ping['image_path'] && is_file($ping['image_path'])) {
            @unlink($ping['image_path']);
        }
        echo "Printed PING #{$ping['transaction_number']}\n";
    } catch (Throwable $exception) {
        $repository->markFailed((int) $ping['id'], $exception->getMessage());
        fwrite(STDERR, "Failed PING #{$ping['transaction_number']}: {$exception->getMessage()}\n");
    }
}
