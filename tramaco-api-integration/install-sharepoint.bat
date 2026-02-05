@echo off
REM Script de instalación para SharePoint Integration
REM Plugin Tramaco

echo.
echo ================================================
echo   Instalacion SharePoint - Plugin Tramaco
echo ================================================
echo.

REM Verificar si Composer está instalado
where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Composer no esta instalado
    echo.
    echo Instala Composer desde: https://getcomposer.org
    pause
    exit /b 1
)

echo OK: Composer encontrado
echo.

REM Instalar dependencias
echo Instalando PhpSpreadsheet...
composer install --no-dev --optimize-autoloader

if %ERRORLEVEL% EQU 0 (
    echo.
    echo OK: Instalacion completada exitosamente
    echo.
    echo Proximos pasos:
    echo    1. Ve a WordPress Admin ^> Tramaco API ^> SharePoint
    echo    2. Configura las credenciales de Azure AD
    echo    3. Prueba la conexion
    echo    4. Habilita la sincronizacion automatica
    echo.
    echo Documentacion: SHAREPOINT-IMPLEMENTATION.md
) else (
    echo.
    echo ERROR: Error durante la instalacion
    echo    Verifica que tienes conexion a internet
    echo    y permisos de escritura en la carpeta
)

echo.
pause
