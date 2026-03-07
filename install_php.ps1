$ErrorActionPreference = "Stop"

$phpPath = "C:\php"
$composerPath = "C:\composer"
$phpUrl = "https://windows.php.net/downloads/releases/php-8.2.30-nts-Win32-vs16-x64.zip"

Write-Host "Creating directories..."
if (-not (Test-Path $phpPath)) { New-Item -ItemType Directory -Path $phpPath | Out-Null }
if (-not (Test-Path $composerPath)) { New-Item -ItemType Directory -Path $composerPath | Out-Null }

Write-Host "Downloading PHP 8.2.30..."
$phpZip = "$phpPath\php.zip"
& curl.exe -sL -o $phpZip $phpUrl

if (-not (Test-Path $phpZip)) {
    throw "Descarga de PHP falló"
}

Write-Host "Extracting PHP..."
Expand-Archive -Path $phpZip -DestinationPath $phpPath -Force
Remove-Item $phpZip

Write-Host "Configuring php.ini..."
$phpIniPath = "$phpPath\php.ini"
Copy-Item "$phpPath\php.ini-development" $phpIniPath
$iniContent = Get-Content $phpIniPath
$iniContent = $iniContent -replace "^;extension_dir = `"ext`"", "extension_dir = `"ext`""
$iniContent = $iniContent -replace "^;extension=curl", "extension=curl"
$iniContent = $iniContent -replace "^;extension=fileinfo", "extension=fileinfo"
$iniContent = $iniContent -replace "^;extension=mbstring", "extension=mbstring"
$iniContent = $iniContent -replace "^;extension=openssl", "extension=openssl"
$iniContent = $iniContent -replace "^;extension=pdo_mysql", "extension=pdo_mysql"
$iniContent | Set-Content $phpIniPath

Write-Host "Downloading Composer..."
$composerInstaller = "$composerPath\composer-setup.php"
& curl.exe -sL -o $composerInstaller "https://getcomposer.org/installer"
& "$phpPath\php.exe" $composerInstaller --install-dir=$composerPath --filename=composer.phar

Write-Host "Creating composer.bat..."
"@echo off`r`n`"%~dp0..\php\php.exe`" `"%~dp0composer.phar`" %*" | Set-Content "$composerPath\composer.bat"

Write-Host "Adding to PATH..."
$envPath = [Environment]::GetEnvironmentVariable("Path", "User")
$pathsToAdd = @($phpPath, $composerPath)
$updated = $false

foreach ($p in $pathsToAdd) {
    if ($envPath -notmatch [regex]::Escape($p)) {
        $envPath += ";$p"
        $updated = $true
    }
}

if ($updated) {
    [Environment]::SetEnvironmentVariable("Path", $envPath, "User")
    Write-Host "PATH updated."
}

Write-Host "Installation complete!"
