#!/bin/bash
set -euo pipefail

# Cargar variables del .env
set -a
[ -f .env ] && source .env
set +a

# Asignar valores por defecto si no están definidos
DB_PASSWORD="${DB_PASSWORD:-root}"
DB_DATABASE="${DB_DATABASE:-abi}"

# Paso 1: Ejecutar migraciones y seeders en el contenedor app
echo "==> Ejecutando migraciones y seeders..."
docker compose exec -T app php artisan migrate --seed

# Paso 2: Procesar el archivo SQL con sed
echo "==> Reemplazando variables en el SQL de roles..."
temp_file="temp_roles.sql"

sed -e "s|{{DB_USER_PASS}}|${DB_USER_PASS:-}|g" \
    -e "s|{{DB_DATABASE}}|$DB_DATABASE|g" \
    -e "s|{{DB_STUDENT_PASS}}|${DB_STUDENT_PASS:-}|g" \
    -e "s|{{DB_PROFESSOR_PASS}}|${DB_PROFESSOR_PASS:-}|g" \
    -e "s|{{DB_RESEARCH_PASS}}|${DB_RESEARCH_PASS:-}|g" \
    "database/sql/roles.sql" > "$temp_file"

# Paso 3: Ejecutar el SQL dentro del contenedor db
echo "==> Ejecutando SQL de roles en la base de datos..."
if ! docker compose exec -T db mariadb -u"root" -p"$DB_PASSWORD" "$DB_DATABASE" < "$temp_file"; then
    # Fallback si usas MySQL en vez de MariaDB
    if ! docker compose exec -T db mysql -u"root" -p"$DB_PASSWORD" "$DB_DATABASE" < "$temp_file"; then
        echo "❌ Error al ejecutar el archivo SQL. Contenido del archivo temporal:"
        cat "$temp_file"
        rm -f "$temp_file"
        exit 1
    fi
fi

# Limpiar archivo temporal
rm -f "$temp_file"
echo "✅ Base de datos inicializada correctamente 🎉"
