#!/bin/bash
set -e

echo "Iniciando aplicación..."

# Esperar a que la base de datos esté lista (si usas MySQL)
# echo "Esperando a la base de datos..."
# while ! mysqladmin ping -h"db" --silent; do
#   sleep 1
# done

# Ejecutar migraciones
echo "Ejecutando migraciones..."
php yii migrate --interactive=0 || true

# Configurar permisos
echo "Configurando permisos..."
chmod -R 777 runtime web/uploads || true

# Iniciar Apache
echo "Iniciando servidor web..."
exec apache2-foreground

