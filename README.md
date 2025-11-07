# Facto en la Nube - Aplicación Web Yii2

Aplicación web moderna con banner de slides, formulario de contacto y gestión de contenido.

## Características

- **Banner Principal**: Carrusel automático de hasta 5 slides con transiciones suaves
- **Formulario de Contacto**: Formulario flotante sobre el banner con campos: Nombre, WhatsApp, Dirección, Email
- **Botones Configurables**: Sección de 6 botones con enlaces personalizables
- **Área de Administración**: Panel de gestión para subir imágenes y configurar enlaces
- **Material Design**: Interfaz moderna con tema morado/azul
- **Responsive**: Diseño adaptable a diferentes dispositivos

## Requisitos

- PHP >= 7.4.0
- Composer
- Extensiones PHP: pdo, pdo_sqlite (o pdo_mysql)

## Instalación

### Opción 1: Con Docker (Recomendado)

La forma más fácil de ejecutar el proyecto es usando Docker:

**Windows:**
```bash
docker-start.bat
```

**Linux/Mac:**
```bash
chmod +x docker-start.sh
./docker-start.sh
```

O manualmente:
```bash
docker-compose up -d --build
docker-compose exec web php yii migrate --interactive=0
```

La aplicación estará disponible en: **http://localhost:8080**

Ver [DOCKER.md](DOCKER.md) para más detalles sobre Docker.

### Opción 2: Instalación Manual

1. Clonar o descargar el proyecto
2. Instalar dependencias:
```bash
composer install
```

3. Ejecutar migraciones para crear las tablas:
```bash
php yii migrate
```

4. Configurar permisos de escritura:
```bash
chmod -R 777 runtime/
chmod -R 777 web/uploads/
```

5. Configurar servidor web para apuntar a la carpeta `web/`

## Estructura del Proyecto

```
/
├── config/          # Configuración de la aplicación
├── controllers/     # Controladores
├── models/          # Modelos de datos
├── views/           # Vistas
├── migrations/      # Migraciones de base de datos
├── assets/          # Asset bundles
├── web/             # Archivos públicos
│   ├── css/         # Estilos CSS
│   ├── js/          # JavaScript
│   └── uploads/     # Imágenes subidas
└── runtime/         # Archivos temporales
```

## Uso

### Acceso a la Aplicación

**Con Docker:**
- **Página Principal**: `http://localhost:8080`
- **Área de Administración**: `http://localhost:8080/admin`

**Sin Docker:**
- **Página Principal**: `http://localhost/`
- **Área de Administración**: `http://localhost/admin`

### Gestión de Slides

1. Ir a `/admin/slides`
2. Hacer clic en "Crear Nuevo Slide"
3. Subir imagen (máximo 5MB, formatos: PNG, JPG, JPEG, GIF)
4. Configurar título y orden
5. Guardar

### Gestión de Botones

1. Ir a `/admin/buttons`
2. Hacer clic en "Crear Nuevo Botón"
3. Configurar:
   - Título del botón
   - URL de destino
   - Icono (nombre de Material Icons)
   - Orden
4. Guardar

## Base de Datos

La aplicación usa SQLite por defecto (archivo en `runtime/database.db`).

Para cambiar a MySQL, editar `config/db.php`:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=nombre_db',
    'username' => 'usuario',
    'password' => 'contraseña',
    'charset' => 'utf8',
];
```

## Personalización

### Colores

Los colores del tema se pueden modificar en `web/css/site.css` en las variables CSS:

```css
:root {
    --primary-purple: #7B1FA2;
    --primary-blue: #1976D2;
    /* ... más colores ... */
}
```

### Iconos

Los iconos utilizan Material Icons. Ver iconos disponibles en: https://fonts.google.com/icons

## Licencia

BSD-3-Clause

