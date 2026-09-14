#!/bin/sh
set -e

cd /var/www/html

APP_MODE="${APP_MODE:-production}"
echo ">> ABI container arrancando en APP_MODE=${APP_MODE}"

# Laravel necesita un .env en disco aunque compose ya inyecte variables reales.
if [ ! -f .env ]; then
    echo ">> No hay .env, copiando .env.docker"
    cp .env.docker .env
fi

# En producción forzamos debug apagado
if [ "$APP_MODE" = "production" ]; then
    export APP_ENV=production
    export APP_DEBUG=false
fi

# Clave de aplicación
if ! grep -q '^APP_KEY=base64:' .env && [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force
fi

# Esperar a la base de datos (Con fallback a 'root' si DB_PASSWORD viene vacía)
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

# 1. Migraciones (+ seeders opcionales) PRIMERO
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo ">> php artisan migrate --seed --force"
    php artisan migrate --seed --force
else
    echo ">> php artisan migrate --force"
    php artisan migrate --force
fi

# 2. Crear usuarios y otorgar permisos desde database/sql/roles.sql DESPUÉS de migrar
if [ "${SETUP_DB_ROLES:-false}" = "true" ]; then
    echo ">> Configurando usuarios y permisos por rol"
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

# Enlace de storage público
php artisan storage:link 2>/dev/null || true

# Caché según el modo
if [ "$APP_MODE" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

echo ">> Listo. Ejecutando: $*"
exec "$@"
