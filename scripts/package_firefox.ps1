Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$buildScript = Join-Path $projectRoot 'scripts\build_extensions.php'
$firefoxDir = Join-Path $projectRoot 'extension-firefox'
$manifestPath = Join-Path $firefoxDir 'manifest.json'
$distDir = Join-Path $projectRoot 'dist'

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP bulunamadı. Önce PHP komutunun PATH içinde erişilebilir olduğundan emin olun.'
}

& php $buildScript
if ($LASTEXITCODE -ne 0) {
    throw "Firefox çıktısı yeniden üretilemedi. PHP çıkış kodu: $LASTEXITCODE"
}

if (-not (Test-Path $manifestPath)) {
    throw "Firefox manifest dosyası bulunamadı: $manifestPath"
}

$manifest = Get-Content $manifestPath -Raw | ConvertFrom-Json
$version = [string]$manifest.version

if ([string]::IsNullOrWhiteSpace($version)) {
    throw 'Firefox manifest içindeki sürüm bilgisi okunamadı.'
}

if (-not (Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

$packagePath = Join-Path $distDir ("guvenlink-firefox-{0}.xpi" -f $version)

if (Test-Path $packagePath) {
    Remove-Item $packagePath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$fileStream = [System.IO.File]::Open($packagePath, [System.IO.FileMode]::CreateNew)

try {
    $archive = [System.IO.Compression.ZipArchive]::new(
        $fileStream,
        [System.IO.Compression.ZipArchiveMode]::Create,
        $false
    )

    try {
        Get-ChildItem -Path $firefoxDir -Recurse -File | ForEach-Object {
            $relativePath = $_.FullName.Substring($firefoxDir.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $_.FullName,
                $relativePath,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }
}
finally {
    $fileStream.Dispose()
}

$package = Get-Item $packagePath
Write-Output ("Firefox paketi oluşturuldu: {0}" -f $package.FullName)
Write-Output ("Boyut: {0} bayt" -f $package.Length)
