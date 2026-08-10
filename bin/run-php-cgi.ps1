$ErrorActionPreference = 'Stop'
$env:PING_PRINTER_MODE = 'windows'
$env:PING_PRINTER_NAME = 'StarTSP100'
$env:PING_TIMEZONE = 'America/New_York'
$env:PING_DATABASE = 'C:\Users\phil\Documents\My Projects\Experiments\ping-receipt\storage\ping.sqlite'
$env:PING_UPLOAD_PATH = 'C:\Users\phil\Documents\My Projects\Experiments\ping-receipt\storage\uploads'
$env:PHP_FCGI_MAX_REQUESTS = '1000'

& 'C:\php85\php-cgi.exe' -b 127.0.0.1:9000
