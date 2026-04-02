@echo off
title ECO-DENT - Sistema Automático de Tareas
color 0A

echo ========================================
echo    ECO-DENT - CRON JOBS AUTOMATICOS
echo    Fecha: %date%
echo    Hora: %time%
echo ========================================
echo.

cd /d C:\xampp\htdocs\ecodent

:: =============================================
:: 1. PROCESAR MENSAJES PENDIENTES (Email)
:: =============================================
echo [1/6] Procesando mensajes pendientes...
C:\xampp\php\php.exe cron/cron_mensajes_pendientes.php
if %errorlevel% equ 0 (
    echo   ✓ Mensajes procesados correctamente
) else (
    echo   ✗ Error al procesar mensajes
)
echo.

:: =============================================
:: 2. ENVIAR RECORDATORIOS (24h y 1h antes)
:: =============================================
echo [2/6] Enviando recordatorios de citas...
C:\xampp\php\php.exe cron/cron_recordatorios.php
if %errorlevel% equ 0 (
    echo   ✓ Recordatorios enviados correctamente
) else (
    echo   ✗ Error al enviar recordatorios
)
echo.

:: =============================================
:: 3. ACTUALIZAR ESTADISTICAS
:: =============================================
echo [3/6] Actualizando estadisticas mensuales...
C:\xampp\php\php.exe cron/cron_estadisticas.php
if %errorlevel% equ 0 (
    echo   ✓ Estadisticas actualizadas
) else (
    echo   ✗ Error al actualizar estadisticas
)
echo.

:: =============================================
:: 4. REALIZAR BACKUP DE LA BASE DE DATOS
:: =============================================
echo [4/6] Realizando backup de la base de datos...
C:\xampp\php\php.exe cron/cron_backup.php
if %errorlevel% equ 0 (
    echo   ✓ Backup realizado exitosamente
) else (
    echo   ✗ Error al realizar backup
)
echo.

:: =============================================
:: 5. VERIFICAR REGLAS DE ALERTAS
:: =============================================
echo [5/6] Verificando reglas de alertas...
C:\xampp\php\php.exe cron/cron_reglas_alertas.php
if %errorlevel% equ 0 (
    echo   ✓ Reglas verificadas
) else (
    echo   ✗ Error en reglas de alertas
)
echo.

:: =============================================
:: 6. LIMPIAR SLOTS BLOQUEADOS ANTIGUOS
:: =============================================
echo [6/6] Limpiando slots bloqueados antiguos...
C:\xampp\php\php.exe cron/cron_limpiar_slots_bloqueados.php
if %errorlevel% equ 0 (
    echo   ✓ Slots antiguos eliminados
) else (
    echo   ✗ Error al limpiar slots
)
echo.
:: =============================================
:: 7. GENERAR ALERTAS AUTOMATICAS
:: =============================================
echo [7/7] Generando alertas automaticas...
C:\xampp\php\php.exe cron/cron_generar_alertas.php
if %errorlevel% equ 0 (
    echo   ✓ Alertas generadas
) else (
    echo   ✗ Error al generar alertas
)
echo.

:: =============================================
:: FINALIZAR
:: =============================================
echo ========================================
echo   PROCESO COMPLETADO
echo   Hora final: %time%
echo ========================================
echo.

:: Mantener ventana abierta 10 segundos
timeout /t 10 /nobreak >nul