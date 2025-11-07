@echo off
echo ==========================================
echo   Facto en la Nube - Docker Setup
echo ==========================================
echo.

REM Verificar si Docker está instalado
where docker >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Docker no esta instalado. Por favor instala Docker primero.
    exit /b 1
)

where docker-compose >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Docker Compose no esta instalado. Por favor instala Docker Compose primero.
    exit /b 1
)

echo [OK] Docker y Docker Compose estan instalados
echo.

REM Construir y levantar los contenedores
echo [INFO] Construyendo contenedores...
docker-compose build

echo.
echo [INFO] Iniciando contenedores...
docker-compose up -d

echo.
echo [INFO] Esperando a que el contenedor este listo...
timeout /t 5 /nobreak >nul

REM Ejecutar migraciones
echo.
echo [INFO] Ejecutando migraciones de base de datos...
docker-compose exec web php yii migrate --interactive=0

echo.
echo ==========================================
echo [OK] Aplicacion iniciada correctamente!
echo ==========================================
echo.
echo [INFO] Accede a la aplicacion en: http://localhost:8080
echo [INFO] Panel de administracion: http://localhost:8080/admin
echo.
echo [INFO] Comandos utiles:
echo    - Ver logs: docker-compose logs -f
echo    - Detener: docker-compose down
echo    - Reiniciar: docker-compose restart
echo    - Acceder al contenedor: docker-compose exec web bash
echo.
pause

