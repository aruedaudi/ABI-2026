#!/bin/sh
set -e

cd /var/www/html

APP_MODE="${APP_MODE:-production}"
echo ">> ABI container arrancando en APP_MODE=${APP_MODE}"

# 1. Copiar .env si no existe en disco
if [ ! -f .env ]; then
    echo ">> No hay .env, copiando .env.docker"
    cp .env.docker .env
fi

# 2. Configurar variables de entorno según APP_MODE
if [ "$APP_MODE" = "production" ]; then
    export APP_ENV=production
    export APP_DEBUG=false
fi

# 3. Asegurar que exista APP_KEY e inyectarla explícitamente al entorno shell
if ! grep -q '^APP_KEY=base64:' .env || [ -z "${APP_KEY:-}" ]; then
    echo ">> Generando APP_KEY..."
    php artisan key:generate --force
fi

export APP_KEY=$(grep '^APP_KEY=' .env | cut -d '=' -f2-)

# 4. Esperar a que la base de datos esté lista (soporta password vacía o 'root')
DB_HOST="${DB_HOST:-db}"; DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"; DB_PASSWORD="${DB_PASSWORD:-root}"
echo ">> Esperando base de datos en ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r '$h=getenv("DB_HOST")?:"db";$p=getenv("DB_PORT")?:"3306";$u=getenv("DB_USERNAME")?:"root";$w=getenv("DB_PASSWORD")?:"root";try{new PDO("mysql:host=$h;port=$p",$u,$w);}catch(Exception $e){exit(1);}' 2>/dev/null
do
    i=$((i+1))
    [ "$i" -ge 300 ] && { echo "!! Base de datos no responde tras 10 min, abortando"; exit 1; }
    [ $((i % 15)) -eq 0 ] && echo "   ... aun esperando base de datos (${i})"
    sleep 2
done
echo ">> Base de datos disponible"

# 5. Ejecutar migraciones (+ seeders opcionales)
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo ">> php artisan migrate --seed --force"
    php artisan migrate --seed --force
else
    echo ">> php artisan migrate --force"
    php artisan migrate --force
fi

# 6. Crear usuarios y asignar roles en MariaDB/MySQL desde database/sql/roles.sql
if [ "${SETUP_DB_ROLES:-false}" = "true" ]; then
    echo ">> Configurando usuarios y permisos por rol..."
    tmp=$(mktemp)
    sed -e "s|{{DB_DATABASE}}|${DB_DATABASE:-abi}|g" \
        -e "s|{{DB_USER_PASS}}|${DB_USER_PASS:-}|g" \
        -e "s|{{DB_STUDENT_PASS}}|${DB_STUDENT_PASS:-}|g" \
        -e "s|{{DB_PROFESSOR_PASS}}|${DB_PROFESSOR_PASS:-}|g" \
        -e "s|{{DB_RESEARCH_PASS}}|${DB_RESEARCH_PASS:-${DB_RESEARCH_STAFF_PASS:-}}|g" \
        database/sql/roles.sql > "$tmp"
    MYSQL_PWD="${DB_PASSWORD}" mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" "${DB_DATABASE:-abi}" < "$tmp"
    rm -f "$tmp"
    echo ">> Usuarios por rol listos"
fi

# 7. Crear enlace simbólico de storage público
php artisan storage:link 2>/dev/null || true

# 8. Corregir propiedad de carpetas para el usuario de Apache (www-data)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 9. Gestionar cachés según el entorno
if [ "$APP_MODE" = "production" ]; then
    php artisan config:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

# Reasegurar que los archivos de caché recién creados sigan perteneciendo a www-data
chown -R www-data:www-data storage bootstrap/cache

echo ">> Listo. Ejecutando: $*"
exec "$@"
