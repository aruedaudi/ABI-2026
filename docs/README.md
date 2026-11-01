# Documentación técnica de ABI

Este directorio reúne la documentación técnica del proyecto. La guía de esta página es la referencia para preparar un entorno de desarrollo; el [README principal](../README.md) ofrece una vista funcional del sistema y un inicio rápido.

## Índice

- [Instalación y configuración](#instalación-y-configuración)
- [Conexiones MySQL por rol](#conexiones-mysql-por-rol)
- [Ejecución y comprobaciones](#ejecución-y-comprobaciones)
- [Documentación generada](#documentación-generada)
- [Automatización con GitHub Actions](#automatización-con-github-actions)

## Instalación y configuración

### Prerrequisitos

Para ejecutar el proyecto en local, **usa XAMPP**. No instales PHP, MySQL ni Apache por separado para evitar conflictos.

- **XAMPP**, con:
  - **PHP 8.2 o superior con `pdo_mysql`**
  - **MySQL o MariaDB**
  - **Apache**
- **Composer**, para instalar dependencias de PHP
- **Node.js y npm**, para compilar los assets del frontend

> **Importante:** este proyecto asume en Windows que XAMPP está instalado en `C:\xampp`. Si lo instalaste en otra ruta, debes ajustar los scripts o la variable PATH.

### Instalación local en Windows con XAMPP

#### 1. Instala XAMPP
Instala XAMPP según el sistema operativo donde vayas a desplegarlo y, al terminar, abre el panel de control de XAMPP.
Link para la instalación de XAMPP: https://www.apachefriends.org/es/index.html

#### 2. Inicia servicios
En el panel de XAMPP, inicia:
- **Apache**
- **MySQL**

#### 3. Verifica que estás usando el PHP de XAMPP
Antes de ejecutar comandos de Laravel, abre **PowerShell** y asegúrate de que `php` apunte al PHP de XAMPP.

Puedes hacerlo temporalmente en la terminal actual con:

```powershell
$env:Path = "C:\xampp\php;C:\xampp\mysql\bin;" + $env:Path
php --ini
```

La salida de `php --ini` debe apuntar a un `php.ini` dentro de `C:\xampp\php`.

> **No uses otro PHP instalado aparte**, porque eso suele causar errores como `could not find driver` al correr migraciones.

#### 4. Instala Composer
Instala Composer y durante el proceso de instalación en caso de que se habilite la opción debes seleccionar "Add This PHP To Your PATH".
Link para descargar la ultima versión de Composer: https://getcomposer.org/Composer-Setup.exe

#### 5. Reiniciar el equipo
Debes reiniciar el equipo para que el sistema operativo actualice sus rutas internas y reconozca el comando Composer.

#### 6. Clona el repositorio
```bash
git clone <url-del-repositorio>
cd ABI-2026-main
```

#### 7. Instala dependencias de PHP
```bash
composer install
```

```
En caso de que Composer presente problemas de instalación reemplacen el archivo php.ini del Config de Apache en XAMPP.
```
> PHP.INI corregido: https://drive.google.com/file/d/1EquA3PZSD6l0HsOLjGdzTbd6wqDecLKb/view?usp=sharing

#### 8. Instala dependencias del frontend
```bash
npm install
```

#### 9. Configura el archivo `.env`
Si vas a trabajar con base de datos local:

```bash
copy .env.example .env
```

Si vas a usar base de datos en la nube:

```bash
copy .env.examplenube .env
```

#### 10. Ajusta las variables de entorno
Si trabajas en local, revisa como mínimo estos valores en `.env`:

```env
APP_NAME=ABI
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

Si tu usuario `root` en MySQL tiene contraseña, colócala en `DB_PASSWORD`.

#### 11. Genera la clave de la aplicación
```bash
php artisan key:generate
```

#### 12. Inicializa la base de datos local
Este paso solo aplica si estás usando `.env` local.

En Windows PowerShell, desde la raíz del proyecto:

```powershell
.\scripts\set-db-roles.ps1
```

Ese script realiza dos acciones:
- ejecuta migraciones y seeders
- crea los usuarios y permisos de MySQL usados por el sistema

> **Importante:** si vas a usar base de datos en la nube, omite este paso.

#### 12. Compila los assets
```bash
npm run build
```

#### 13. Inicia el servidor de desarrollo
```bash
php artisan serve
```

La aplicación quedará disponible en:

```text
http://127.0.0.1:8000
```

### Instalación local en Linux

1. Instala PHP 8.1+, Composer, Node.js y MySQL/MariaDB.
2. Copia `.env.example` a `.env`.
3. Ajusta credenciales de base de datos.
4. Ejecuta:

```bash
composer install
npm install
php artisan key:generate
bash scripts/set-db-roles.sh
npm run build
php artisan serve
```

### Para manejar un inicio rápido

```bash
composer install
npm install
cp .env.example .env
Abre XAMPP y enciende:
- Apache
- MySQL

php artisan key:generate
.\scripts\set-db-roles.ps1
npm run build
php artisan serve
```

### Base de datos con privilegios por rol

El inicio rápido usa una única conexión administrativa. Si necesitas reproducir las restricciones MySQL por rol, configura las variables `DB_USER_*`, `DB_RESEARCH_*`, `DB_PROFESSOR_PASS` y `DB_STUDENT_PASS` en tu `.env`, y ejecuta el script para tu sistema:

```bash
# Linux o Git Bash
bash scripts/set-db-roles.sh

# Windows PowerShell
powershell -ExecutionPolicy Bypass -File .\scripts\set-db-roles.ps1
```

Estos scripts ejecutan `migrate --seed` y procesan `database/sql/roles.sql`. Necesitan una cuenta de MySQL con permisos para crear usuarios y asignar privilegios. No los ejecutes contra una base de datos administrada si el proveedor no permite `CREATE USER` o `GRANT`; en ese caso utiliza `php artisan migrate --seed` y adapta las credenciales de rol a las capacidades del proveedor.

### 5. Compilar e iniciar

```bash
npm run build
php artisan serve
```

La aplicación queda disponible normalmente en `http://127.0.0.1:8000`. Durante el desarrollo de frontend, ejecuta además `npm run dev` en otra terminal. Vite usa HMR en `localhost:3000`.

## Conexiones MySQL por rol

Además de `mysql`, la aplicación define las conexiones `mysql_user`, `mysql_research_staff`, `mysql_professor` y `mysql_student` en [`config/database.php`](../config/database.php). Para utilizar el esquema de privilegios por rol, añade a tu `.env` las variables que consume esa configuración y el script de roles:

```env
DB_USER_USERNAME=db_user
DB_USER_PASS=<contraseña-segura>
DB_RESEARCH_USER=db_research_staff
DB_RESEARCH_PASS=<contraseña-segura>
DB_PROFESSOR_PASS=<contraseña-segura>
DB_STUDENT_PASS=<contraseña-segura>
```

`mysql_professor` y `mysql_student` utilizan respectivamente los usuarios `db_professor` y `db_student`; `mysql_user` y `mysql_research_staff` permiten configurar el nombre de usuario mediante las variables anteriores. Conserva estas contraseñas fuera del repositorio.

## Ejecución y comprobaciones

Comandos cotidianos:

```bash
# Limpiar cachés cuando cambie la configuración o las rutas
php artisan optimize:clear

# Ejecutar la suite de pruebas
php artisan test

# Aplicar el formateador PHP
./vendor/bin/pint
```

Las pruebas están configuradas para MySQL en `phpunit.xml`; verifica que la base de datos de pruebas exista y que las credenciales disponibles puedan crear o migrar el esquema antes de ejecutarlas.

## Documentación generada

Los siguientes artefactos se generan automáticamente y sirven de consulta:

- [Rutas completas](generated/routes/all-routes.txt)
- [Rutas API](generated/routes/api-routes.txt)
- [Diagrama ERD](generated/diagrams/erd.svg)
- [Documentación API](generated/api/index.html)
- [Documentación de código](generated/code/html/index.html)

Para regenerarlos localmente, instala `doxygen` y `graphviz` si deseas incluir la documentación de código y el ERD, y ejecuta:

```bash
bash scripts/generate-docs.sh
```

El script genera los listados de rutas con Artisan; ejecuta Scribe y el generador ERD cuando sus comandos están disponibles; y ejecuta Doxygen cuando está instalado.

No edites manualmente estas carpetas generadas:

- `docs/generated/api`
- `docs/generated/code`
- `docs/generated/diagrams`
- `docs/generated/routes`

## Automatización con GitHub Actions

El workflow [`.github/workflows/auto-docs.yml`](../.github/workflows/auto-docs.yml) se ejecuta ante `push` a las ramas `main` y `docs/auto-documentacion`, y también puede iniciarse manualmente con `workflow_dispatch`.

Instala PHP 8.2, Doxygen y Graphviz, prepara Laravel y regenera `docs/generated`. Si esos artefactos cambian, el workflow crea y publica un commit automático. Los cambios que afectan únicamente `docs/generated/**` se ignoran como desencadenantes para evitar bucles.
