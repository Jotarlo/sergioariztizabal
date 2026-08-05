@echo off
title Migrador WordPress Local <-> Produccion
color 0A

:MENU
cls
echo ======================================================
echo         MIGRADOR WORDPRESS - SERGIO ARISTIZABAL
echo ======================================================
echo.
echo   1. Preparar para PRODUCCION
echo   2. Regresar a LOCAL
echo   3. Verificar estado actual
echo   4. Salir
echo.
set /p op=Seleccione una opcion:

if "%op%"=="1" goto PRODUCCION
if "%op%"=="2" goto LOCAL
if "%op%"=="3" goto VERIFICAR
if "%op%"=="4" exit

goto MENU


:PRODUCCION
cls
echo ============================================
echo CAMBIANDO URL LOCAL -> PRODUCCION
echo ============================================
echo.

wp search-replace "http://sergioaristizabal.local" "https://sergioaristizabal.com" --precise --all-tables --report-changed-only
wp search-replace "https://sergioaristizabal.local" "https://sergioaristizabal.com" --precise --all-tables --report-changed-only

echo.
echo Regenerando CSS de Elementor...
wp elementor flush_css

echo.
echo Limpiando cache...
wp cache flush

echo.
echo Exportando Base de Datos...
if exist "%~dp0sergioaristizabal-produccion.sql" del "%~dp0sergioaristizabal-produccion.sql"
wp db export "%~dp0sergioaristizabal-produccion.sql"

echo.
echo ============================================
echo VERIFICANDO...
echo ============================================

echo.
echo Home:
wp option get home

echo.
echo SiteURL:
wp option get siteurl

echo.
echo Buscando URLs locales...
wp search-replace "http://sergioaristizabal.local" "https://sergioaristizabal.com" --dry-run --precise --all-tables --report-changed-only
wp search-replace "https://sergioaristizabal.local" "https://sergioaristizabal.com" --dry-run --precise --all-tables --report-changed-only

echo.
echo Buscando doble HTTPS...
wp search-replace "https://https://" "https://" --dry-run --precise --all-tables --report-changed-only

echo.
echo ============================================
echo BASE DE DATOS EXPORTADA EN:
echo %~dp0sergioaristizabal-produccion.sql
echo ============================================
pause
goto MENU


:LOCAL
cls
echo ============================================
echo CAMBIANDO URL PRODUCCION -> LOCAL
echo ============================================
echo.

wp search-replace "https://sergioaristizabal.com" "http://sergioaristizabal.local" --precise --all-tables --report-changed-only

echo.
echo Regenerando CSS de Elementor...
wp elementor flush_css

echo.
echo Limpiando cache...
wp cache flush

echo.
echo Verificando...

echo.
echo Home:
wp option get home

echo.
echo SiteURL:
wp option get siteurl

echo.
echo Buscando URLs de produccion...
wp search-replace "https://sergioaristizabal.com" "http://sergioaristizabal.local" --dry-run --precise --all-tables --report-changed-only

echo.
echo ============================================
echo EL SITIO ESTA NUEVAMENTE EN LOCAL
echo ============================================
pause
goto MENU


:VERIFICAR
cls

echo ============================================
echo ESTADO ACTUAL
echo ============================================

echo.
echo Home:
wp option get home

echo.
echo SiteURL:
wp option get siteurl

echo.
echo URLs locales pendientes:
wp search-replace "http://sergioaristizabal.local" "https://sergioaristizabal.com" --dry-run --precise --all-tables --report-changed-only
wp search-replace "https://sergioaristizabal.local" "https://sergioaristizabal.com" --dry-run --precise --all-tables --report-changed-only

echo.
echo Doble HTTPS:
wp search-replace "https://https://" "https://" --dry-run --precise --all-tables --report-changed-only

pause
goto MENU