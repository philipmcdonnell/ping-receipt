<?php
declare(strict_types=1);

use Ping\Config;
use Ping\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
$config = Config::fromEnvironment(dirname(__DIR__));
Database::connect($config->databasePath);
echo "PING database is ready: {$config->databasePath}\n";
