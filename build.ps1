# Build Social2WP plugin ZIP for WordPress upload
# Run from the plugin directory: .\build.ps1

$pluginDir  = $PSScriptRoot
$pluginSlug = "social2wp"

# Read version from main plugin file
$mainFile    = Join-Path $pluginDir "social2wp.php"
$versionLine = Get-Content $mainFile | Where-Object { $_ -match '^\s*\*\s*Version:' } | Select-Object -First 1
$version     = ($versionLine -replace '.*Version:\s*', '').Trim()

# Output to Desktop
$desktop = [Environment]::GetFolderPath("Desktop")
$zipPath = Join-Path $desktop "$pluginSlug.zip"

# Files to include in the ZIP
$includes = @(
    "social2wp.php",
    "readme.txt",
    "includes\class-api.php",
    "includes\class-formatter.php",
    "includes\class-settings.php"
)

# Remove old ZIP if it exists
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

foreach ($file in $includes) {
    $fullPath  = Join-Path $pluginDir $file
    $entryName = $file -replace '\\', '/'
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $zip, $fullPath, $entryName, [System.IO.Compression.CompressionLevel]::Optimal
    ) | Out-Null
    Write-Host "  + $entryName"
}

$zip.Dispose()

Write-Host ""
Write-Host "Done: $zipPath" -ForegroundColor Green
Write-Host "Version: $version"   -ForegroundColor Cyan
