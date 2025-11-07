# Guía de Docker para Facto en la Nube

Esta guía te ayudará a ejecutar el proyecto usando Docker sin afectar otros proyectos en tu sistema.

## Requisitos Previos

- Docker instalado ([Descargar Docker](https://www.docker.com/get-started))
- Docker Compose instalado (viene con Docker Desktop)

## Puertos Utilizados

Para evitar conflictos con otros proyectos, esta aplicación usa puertos alternativos:

- **Puerto Web**: `8080` (en lugar del puerto 80 estándar)
- **Puerto MySQL** (opcional): `3307` (en lugar del puerto 3306 estándar)

## Inicio Rápido

### Opción 1: Scripts Automáticos (Recomendado)

**Windows:**
```bash
# Iniciar
docker-start.sh

# Detener
docker-stop.sh
```

**Linux/Mac:**
```bash
# Dar permisos de ejecución
chmod +x docker-start.sh docker-stop.sh

# Iniciar
./docker-start.sh

# Detener
./docker-stop.sh
```

### Opción 2: Comandos Manuales

```bash
# Construir y levantar contenedores
docker-compose up -d --build

# Ejecutar migraciones
docker-compose exec web php yii migrate --interactive=0

# Ver logs
docker-compose logs -f
```

## Acceso a la Aplicación

Una vez iniciados los contenedores:

- **Página Principal**: http://localhost:8080
- **Panel de Administración**: http://localhost:8080/admin

## Comandos Útiles

### Gestión de Contenedores

```bash
# Iniciar contenedores
docker-compose up -d

# Detener contenedores
docker-compose down

# Reiniciar contenedores
docker-compose restart

# Ver estado de contenedores
docker-compose ps

# Ver logs en tiempo real
docker-compose logs -f

# Ver logs solo del servicio web
docker-compose logs -f web
```

### Acceso al Contenedor

```bash
# Acceder al shell del contenedor web
docker-compose exec web bash

# Ejecutar comandos Yii2
docker-compose exec web php yii migrate
docker-compose exec web php yii migrate/create nombre_migracion
```

### Base de Datos

```bash
# Ejecutar migraciones
docker-compose exec web php yii migrate

# Crear nueva migración
docker-compose exec web php yii migrate/create nombre_migracion

# Revertir última migración
docker-compose exec web php yii migrate/down
```

### Limpieza

```bash
# Detener y eliminar contenedores, redes y volúmenes
docker-compose down -v

# Eliminar imágenes construidas
docker-compose down --rmi all

# Limpiar todo (contenedores, imágenes, volúmenes)
docker system prune -a
```

## Estructura de Docker

```
.
├── Dockerfile              # Configuración de la imagen PHP/Apache
├── docker-compose.yml     # Orquestación de servicios
├── .dockerignore          # Archivos excluidos de la imagen
├── docker-start.sh        # Script de inicio automático
├── docker-stop.sh         # Script de detención
└── config/
    └── db-mysql.php       # Configuración opcional de MySQL
```

## Configuración de Base de Datos

### SQLite (Por Defecto)

La aplicación usa SQLite por defecto. El archivo de base de datos se guarda en:
```
runtime/database.db
```

### MySQL (Opcional)

Si prefieres usar MySQL:

1. Descomenta el servicio `db` en `docker-compose.yml`
2. Descomenta `mysql_data` en la sección `volumes`
3. Renombra `config/db-mysql.php` a `config/db.php` o actualiza `config/web.php`

## Solución de Problemas

### El puerto 8080 ya está en uso

Si el puerto 8080 está ocupado, puedes cambiarlo en `docker-compose.yml`:

```yaml
ports:
  - "8081:80"  # Cambia 8080 por otro puerto disponible
```

### Error de permisos

```bash
# Dar permisos a directorios necesarios
chmod -R 777 runtime web/uploads
```

### Reconstruir contenedores

Si hay cambios en el Dockerfile o dependencias:

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Ver logs de errores

```bash
# Logs generales
docker-compose logs

# Logs del servicio web
docker-compose logs web

# Logs en tiempo real
docker-compose logs -f web
```

### Limpiar y empezar de nuevo

```bash
# Detener y eliminar todo
docker-compose down -v

# Reconstruir desde cero
docker-compose build --no-cache
docker-compose up -d
docker-compose exec web php yii migrate --interactive=0
```

## Desarrollo

### Modo Desarrollo

El contenedor está configurado para desarrollo con:
- `YII_ENV=dev`
- `YII_DEBUG=1`

Los cambios en el código se reflejan automáticamente gracias a los volúmenes montados.

### Hot Reload

Los archivos se sincronizan automáticamente entre tu máquina y el contenedor, por lo que los cambios se reflejan inmediatamente.

## Producción

Para producción, modifica el `Dockerfile` y `docker-compose.yml`:

1. Cambiar `YII_ENV=prod` y `YII_DEBUG=0`
2. Usar `composer install --no-dev --optimize-autoloader`
3. Configurar variables de entorno apropiadas

## Notas Importantes

- Los archivos en `runtime/` y `web/uploads/` se mantienen entre reinicios
- El directorio `vendor/` se cachea en un volumen para mejor rendimiento
- La base de datos SQLite se guarda en `runtime/database.db`
- Los logs de Apache están disponibles con `docker-compose logs web`

