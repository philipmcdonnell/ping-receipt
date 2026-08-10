param(
    [Parameter(Mandatory = $true)]
    [string] $ImagePath,

    [Parameter(Mandatory = $true)]
    [string] $PrinterName
)

$ErrorActionPreference = 'Stop'
$resolvedImage = [System.IO.Path]::GetFullPath($ImagePath)
if (-not (Test-Path -LiteralPath $resolvedImage -PathType Leaf)) {
    throw "Image does not exist: $resolvedImage"
}

Add-Type -AssemblyName System.Drawing
$image = [System.Drawing.Image]::FromFile($resolvedImage)
$document = New-Object System.Drawing.Printing.PrintDocument
$document.PrinterSettings.PrinterName = $PrinterName
if (-not $document.PrinterSettings.IsValid) {
    $image.Dispose()
    $document.Dispose()
    throw "Printer is not available: $PrinterName"
}

$document.DocumentName = 'PING image test'
$document.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0, 0, 0, 0)
$document.add_PrintPage({
    param($sender, $eventArgs)
    $bounds = $eventArgs.MarginBounds
    $scale = [Math]::Min($bounds.Width / $image.Width, $bounds.Height / $image.Height)
    $width = [Math]::Max(1, [int]($image.Width * $scale))
    $height = [Math]::Max(1, [int]($image.Height * $scale))
    $x = $bounds.Left + [int](($bounds.Width - $width) / 2)
    $eventArgs.Graphics.DrawImage($image, $x, $bounds.Top, $width, $height)
    $eventArgs.HasMorePages = $false
})

try {
    $document.Print()
} finally {
    $image.Dispose()
    $document.Dispose()
}
