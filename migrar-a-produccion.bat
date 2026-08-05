@echo off
setlocal enabledelayedexpansion

REM ============================================
REM   Script de migracion Local -> Produccion
REM   Sergio Aristizabal - sitio WordPress
REM ============================================
REM
REM IMPORTANTE: Este script debe ejecutarse DESDE
REM LA SITE SHELL de Local (clic derecho en el sitio
REM dentro de Local > "Open Site Shell"), no desde
REM un cmd normal de Windows. La Site Shell es la que
REM deja disponible el comando "wp".

set DOMINIO_LOCAL=https://sergioaristizabal.local
set DOMINIO_PROD=https://sergioaristizabal.com

REM Genera un nombre de archivo con fecha y hora para no sobreescribir exportaciones viejas
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set FECHA=%datetime:~0,8%-%datetime:~8,6%
set ARCHIVO_SQL=sergioaristizabal-produccion-%FECHA%.sql

echo.
echo ============================================
echo   PASO 1: Verificando estado actual de Local
echo ============================================
wp option get siteurl
wp option get home
echo.

echo ============================================
echo   PASO 2: Verificacion previa (dry-run)
echo   Buscando restos de %DOMINIO_LOCAL%
echo ============================================
wp search-replace "sergioaristizabal.local" "sergioaristizabal.com" --all-tables --precise --dry-run
echo.
echo Si el numero de arriba es alto, es normal (asi deberia ser antes de reemplazar).
echo.
pause

echo ============================================
echo   PASO 3: Reemplazando dominio local por produccion
echo ============================================
wp search-replace "sergioaristizabal.local" "sergioaristizabal.com" --all-tables --precise
echo.

echo ============================================
echo   PASO 4: Verificando que no quede nada de .local
echo ============================================
wp search-replace "sergioaristizabal.local" "sergioaristizabal.com" --all-tables --precise --dry-run
echo (deberia decir 0 replacements arriba)
echo.

echo ============================================
echo   PASO 5: Exportando base de datos limpia
echo ============================================
wp db export %ARCHIVO_SQL%
echo.
echo Archivo generado: %ARCHIVO_SQL%
echo Ubicacion: carpeta del sitio (app\public)
echo.

echo ============================================
echo   PASO 6: Revirtiendo Local a su dominio normal
echo ============================================
wp search-replace "sergioaristizabal.com" "sergioaristizabal.local" --all-tables --precise
echo.

echo ============================================
echo   PASO 7: Verificacion final de Local
echo ============================================
wp option get siteurl
wp option get home
echo.

echo ============================================
echo   LISTO
echo ============================================
echo Sube el archivo %ARCHIVO_SQL% al servidor de
echo produccion (MySQL Workbench / phpMyAdmin) para
echo completar la migracion.
echo.
echo Local ya quedo funcionando normal con su dominio .local
echo.
pause
