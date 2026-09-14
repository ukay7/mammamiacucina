$ErrorActionPreference = 'Stop'
$phpPath = Join-Path $PSScriptRoot '../.tools/php/php.exe'
Set-Location (Join-Path $PSScriptRoot 'public')
& $phpPath -S 127.0.0.1:8088 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
