# Manual de Producción – Actualización del repositorio `factoconsulting2018/tools`

Este documento describe el procedimiento recomendado para actualizar el código en el servidor de producción (`/home/user-data/www/facturaenlanube.com`) utilizando los últimos cambios publicados en el repositorio remoto: <https://github.com/factoconsulting2018/tools>.

---

## 1. Requisitos previos

1. Acceso SSH al servidor de producción (usuario con permisos para leer y escribir en `/home/user-data/www/facturaenlanube.com`).
2. Claves SSH autorizadas o credenciales válidas.
3. `git`, `composer` y (si aplica) `docker` instalados en el servidor.
4. Conexión estable a internet para descargar dependencias y cambios del repositorio remoto.

---

## 2. Actualización estándar del código

```bash
# 1. Conectarse por SSH al servidor
ssh usuario@tu-servidor.com

# 2. Cambiar al directorio del proyecto
cd /home/user-data/www/facturaenlanube.com

# 3. Verificar el estado del repositorio antes de actualizar
git status

# 4. Obtener los últimos cambios del repositorio remoto
git pull origin main
```

**Notas:**
- Sustituye `usuario@tu-servidor.com` por el usuario/dominio real.
- Si el proyecto utiliza otra rama principal (por ejemplo `master` o `production`), reemplaza `main` por la rama correspondiente.

---

## 3. Post-actualización (opcional según el proyecto)

Dependiendo de la tecnología utilizada por la aplicación, podrían requerirse los siguientes pasos adicionales:

```bash
# Instalar/actualizar dependencias PHP
composer install --no-dev --optimize-autoloader

# Ejecutar migraciones de base de datos (Yii2)
php yii migrate --interactive=0

# Si el proyecto usa assets compilados (npm/yarn)
npm install
npm run build

# Reiniciar servicios externos (ejemplo con Supervisor o systemd)
sudo supervisorctl restart nombre-del-proceso
# o
sudo systemctl restart nombre-del-servicio
```

Realiza únicamente los pasos necesarios para tu despliegue específico.

---

## 4. Verificación y pruebas

1. Comprueba que no existan archivos pendientes por confirmar:
   ```bash
   git status
   ```
2. Revisa los registros de la aplicación y del servidor web (por ejemplo `/var/log/nginx/error.log` o `/var/log/apache2/error.log`).
3. Valida manualmente las funcionalidades críticas en `https://facturaenlanube.com`.

---

## 5. Recursos adicionales

- Repositorio oficial: <https://github.com/factoconsulting2018/tools>
- Documentación de Git: <https://git-scm.com/doc>
- Guía de despliegue de Yii2: <https://www.yiiframework.com/doc/guide/2.0/en/start-installation#deploying-an-application>
- Guía de Composer: <https://getcomposer.org/doc/>

---

**Recomendación:** Antes de ejecutar cualquier actualización en producción, crea un respaldo del código y de la base de datos, o asegúrate de tener un punto de restauración disponible.


