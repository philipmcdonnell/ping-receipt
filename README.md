# PING Receipt

PING is a small PHP application that lets a visitor send text and an optional image to Phil McDonnell's local Star TSP100 receipt printer.

This version is adapted from [Andrew Schmelyun's original project](https://github.com/aschmelyun/ping-receipt). It removes Laravel and renders each complete receipt as a black-and-white image for the Windows Star printer driver.

## Requirements

- PHP 8.2 or newer
- PHP extensions: Fileinfo, GD, PDO, and PDO SQLite
- Composer
- For real printing: a Windows-shared Star TSP100 receipt printer

## Install

```powershell
composer install
php bin/migrate.php
```

## Safe local preview

The default printer mode writes a text receipt and processed image to `storage/print-output`; it does not operate the real printer.

```powershell
.\start.ps1
```

Open <http://127.0.0.1:8000>.

## Real printer mode

Set these values only in the service environment on the Windows machine connected to the printer:

```powershell
$env:PING_PRINTER_MODE='windows'
$env:PING_PRINTER_NAME='StarTSP100'
```

Then start the application through the production Windows web service. Do not use PHP's built-in development server as the permanent public origin.

## Images

The form accepts JPEG, PNG, and WebP files up to 5 MB. Files are verified by content, bounded by pixel count, resized to the configured printer width, flattened onto white, converted to grayscale PNG, and stored outside the public directory. A successfully printed image is deleted. An image for a failed print is retained for `bin/retry-failed.php`.

## Tests

```powershell
composer test
```

Tests use temporary SQLite storage and fake printer output. They never operate the Star printer.

## Configuration

Configuration is read from environment variables. Secrets do not belong in the repository.

| Variable | Default | Purpose |
| --- | --- | --- |
| `PING_PRINTER_MODE` | `file` | `file` for safe output or `windows` for the real printer |
| `PING_PRINTER_NAME` | `StarTSP100` | Windows printer share name |
| `PING_DATABASE` | `storage/ping.sqlite` | SQLite database path |
| `PING_UPLOAD_PATH` | `storage/uploads` | Private processed-image storage |
| `PING_OUTPUT_PATH` | `storage/print-output` | Fake-printer output |
| `PING_PRINTER_WIDTH` | `512` | Maximum processed image width in pixels |
| `PING_MAX_IMAGE_BYTES` | `5242880` | Maximum upload size |
| `PING_MAX_IMAGE_PIXELS` | `20000000` | Decompression safety limit |
| `PING_RATE_LIMIT` | `10` | Requests allowed per window |
| `PING_RATE_WINDOW` | `60` | Rate-limit window in seconds |
| `PING_TIMEZONE` | `America/New_York` | Timezone used on printed receipts |

## Public hosting

Bind the origin to localhost and expose only the intended service through the existing Cloudflare Tunnel. Keep tunnel credentials and service configuration outside this repository.

The verified Windows deployment uses:

- `PING Caddy` restartable Windows logon task on `127.0.0.1:8000`
- `PING PHP-CGI` restartable Windows logon task on `127.0.0.1:9000`
- Existing automatic `Cloudflared` service routing `ping.philmcdonnell.com` to the local origin
- `Caddyfile` for the localhost server and `bin/run-php-cgi.ps1` for the printer-aware PHP process
