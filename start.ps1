$ErrorActionPreference = 'Stop'
$env:PING_PRINTER_MODE = if ($env:PING_PRINTER_MODE) { $env:PING_PRINTER_MODE } else { 'file' }
php -S 127.0.0.1:8000 -t public
