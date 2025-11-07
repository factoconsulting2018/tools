#!/bin/bash

echo "=========================================="
echo "  Facto en la Nube - Docker Setup"
echo "=========================================="
echo ""

# Verificar si Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker no está instalado. Por favor instala Docker primero."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose no está instalado. Por favor instala Docker Compose primero."
    exit 1
fi

echo "✅ Docker y Docker Compose están instalados"
echo ""

# Construir y levantar los contenedores
echo "🔨 Construyendo contenedores..."
docker-compose build

echo ""
echo "🚀 Iniciando contenedores..."
docker-compose up -d

echo ""
echo "⏳ Esperando a que el contenedor esté listo..."
sleep 5

# Ejecutar migraciones
echo ""
echo "📦 Ejecutando migraciones de base de datos..."
docker-compose exec web php yii migrate --interactive=0

echo ""
echo "=========================================="
echo "✅ ¡Aplicación iniciada correctamente!"
echo "=========================================="
echo ""
echo "🌐 Accede a la aplicación en: http://localhost:8080"
echo "🔧 Panel de administración: http://localhost:8080/admin"
echo ""
echo "📋 Comandos útiles:"
echo "   - Ver logs: docker-compose logs -f"
echo "   - Detener: docker-compose down"
echo "   - Reiniciar: docker-compose restart"
echo "   - Acceder al contenedor: docker-compose exec web bash"
echo ""

