# Script para instalar PhpSpreadsheet manualmente
# Sin necesidad de Composer ni PHP en PATH

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Instalador de PhpSpreadsheet v1.29.2" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$pluginDir = $PSScriptRoot
$vendorDir = Join-Path $pluginDir "vendor"
$tempDir = Join-Path $pluginDir "temp_download"

# Crear directorio temporal
if (!(Test-Path $tempDir)) {
    New-Item -ItemType Directory -Path $tempDir | Out-Null
}

Write-Host "📦 Descargando PhpSpreadsheet desde GitHub..." -ForegroundColor Yellow

# URL del release de PhpSpreadsheet
$phpSpreadsheetUrl = "https://github.com/PHPOffice/PhpSpreadsheet/archive/refs/tags/1.29.2.zip"
$zipFile = Join-Path $tempDir "phpspreadsheet.zip"

try {
    # Descargar con TLS 1.2
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    
    $ProgressPreference = 'SilentlyContinue'
    Invoke-WebRequest -Uri $phpSpreadsheetUrl -OutFile $zipFile -UseBasicParsing
    $ProgressPreference = 'Continue'
    
    Write-Host "✅ Descarga completada" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "❌ Error al descargar PhpSpreadsheet: $_" -ForegroundColor Red
    exit 1
}

Write-Host "📂 Extrayendo archivos..." -ForegroundColor Yellow

# Extraer el ZIP
try {
    Expand-Archive -Path $zipFile -DestinationPath $tempDir -Force
    Write-Host "✅ Extracción completada" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "❌ Error al extraer: $_" -ForegroundColor Red
    exit 1
}

Write-Host "📁 Organizando estructura vendor/..." -ForegroundColor Yellow

# Crear estructura vendor/phpoffice/phpspreadsheet
$phpOfficeDir = Join-Path $vendorDir "phpoffice"
$phpSpreadsheetDir = Join-Path $phpOfficeDir "phpspreadsheet"

if (Test-Path $vendorDir) {
    Remove-Item $vendorDir -Recurse -Force
}

New-Item -ItemType Directory -Path $phpSpreadsheetDir -Force | Out-Null

# Copiar archivos extraídos
$extractedDir = Join-Path $tempDir "PhpSpreadsheet-1.29.2"
Copy-Item -Path "$extractedDir\*" -Destination $phpSpreadsheetDir -Recurse -Force

Write-Host "✅ Estructura creada" -ForegroundColor Green
Write-Host ""

# Crear autoload.php
Write-Host "🔧 Creando autoload.php..." -ForegroundColor Yellow

$autoloadContent = @'
<?php
/**
 * Autoloader para PhpSpreadsheet
 * Generado automáticamente
 */

spl_autoload_register(function ($class) {
    // Manejar namespace de PhpSpreadsheet
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet\\') === 0) {
        $file = __DIR__ . '/phpoffice/phpspreadsheet/src/' . 
                str_replace('\\', '/', substr($class, 25)) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    // Manejar dependencias comunes
    $prefixes = [
        'Psr\\SimpleCache\\' => '/psr/simple-cache/src/',
        'Psr\\Http\\Message\\' => '/psr/http-message/src/',
        'Psr\\Http\\Client\\' => '/psr/http-client/src/',
    ];
    
    foreach ($prefixes as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = __DIR__ . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }
});
'@

$autoloadFile = Join-Path $vendorDir "autoload.php"
Set-Content -Path $autoloadFile -Value $autoloadContent -Encoding UTF8

Write-Host "✅ Autoload creado" -ForegroundColor Green
Write-Host ""

# Descargar dependencias PSR (interfaces simples)
Write-Host "📦 Descargando dependencias PSR..." -ForegroundColor Yellow

# PSR Simple Cache
$psrDir = Join-Path $vendorDir "psr"
$psrSimpleCacheDir = Join-Path $psrDir "simple-cache\src"
New-Item -ItemType Directory -Path $psrSimpleCacheDir -Force | Out-Null

$psrSimpleCacheUrl = "https://raw.githubusercontent.com/php-fig/simple-cache/master/src/CacheInterface.php"
$psrSimpleCacheFile = Join-Path $psrSimpleCacheDir "CacheInterface.php"

try {
    Invoke-WebRequest -Uri $psrSimpleCacheUrl -OutFile $psrSimpleCacheFile -UseBasicParsing
    Write-Host "  ✅ PSR Simple Cache descargado" -ForegroundColor Green
} catch {
    Write-Host "  ⚠️  No se pudo descargar PSR Simple Cache (opcional)" -ForegroundColor Yellow
}

# PSR HTTP Message
$psrHttpDir = Join-Path $psrDir "http-message\src"
New-Item -ItemType Directory -Path $psrHttpDir -Force | Out-Null

$psrHttpUrls = @(
    "https://raw.githubusercontent.com/php-fig/http-message/master/src/MessageInterface.php",
    "https://raw.githubusercontent.com/php-fig/http-message/master/src/RequestInterface.php",
    "https://raw.githubusercontent.com/php-fig/http-message/master/src/ResponseInterface.php",
    "https://raw.githubusercontent.com/php-fig/http-message/master/src/StreamInterface.php"
)

foreach ($url in $psrHttpUrls) {
    $fileName = Split-Path $url -Leaf
    $outFile = Join-Path $psrHttpDir $fileName
    try {
        Invoke-WebRequest -Uri $url -OutFile $outFile -UseBasicParsing
    } catch {
        # Ignorar errores en PSR (opcionales)
    }
}

Write-Host "✅ Dependencias PSR descargadas" -ForegroundColor Green
Write-Host ""

# Limpiar temporales
Write-Host "🧹 Limpiando archivos temporales..." -ForegroundColor Yellow
Remove-Item $tempDir -Recurse -Force
Write-Host "✅ Limpieza completada" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Green
Write-Host "  ✅ INSTALACIÓN COMPLETADA" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "PhpSpreadsheet instalado en:" -ForegroundColor Cyan
Write-Host "  $phpSpreadsheetDir" -ForegroundColor White
Write-Host ""
Write-Host "Ahora puedes activar el plugin en WordPress" -ForegroundColor Yellow
Write-Host ""
