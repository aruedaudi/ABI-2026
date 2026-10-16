# ABI

Plataforma web para la gestión y trazabilidad del banco de ideas y proyectos de grado. El sistema centraliza el registro de ideas, su evaluación por comité, la consulta del banco de ideas aprobadas, la postulación de estudiantes, la asignación de proyectos, la proyección académica y la administración de catálogos institucionales relacionados con investigación.

El proyecto está construido sobre Laravel y mantiene una arquitectura orientada a roles, donde estudiantes, docentes, líderes de comité y personal de investigación operan con alcances funcionales diferenciados tanto a nivel de interfaz como de acceso a datos.

## 📋 Tabla de contenido

- [Visión general](#-visión-general)
- [Tecnologías utilizadas](#-tecnologías-utilizadas)
- [Módulos funcionales](#-módulos-funcionales)
- [Roles del sistema](#-roles-del-sistema)
- [Flujos principales](#-flujos-principales)
- [Arquitectura del proyecto](#-arquitectura-del-proyecto)
- [Modelo funcional y de datos](#-modelo-funcional-y-de-datos)
- [Instalación y configuración](#-instalación-y-configuración)
- [Conexiones por rol y seguridad de base de datos](#-conexiones-por-rol-y-seguridad-de-base-de-datos)
- [Ejecución diaria](#-ejecución-diaria)
- [Pruebas](#-pruebas)
- [API y documentación automática](#-api-y-documentación-automática)
- [Reportes, archivos y almacenamiento](#-reportes-archivos-y-almacenamiento)
- [Despliegue](#-despliegue)
- [Troubleshooting](#-troubleshooting)
- [Contribución](#-contribución)
- [Licencia](#-licencia)

## 🎯 Visión general

ABI no es solo un CRUD de proyectos. El repositorio implementa un flujo académico completo para:

- registrar ideas de proyecto por estudiantes o docentes
- evaluar ideas por parte de líderes de comité
- mantener trazabilidad por versiones y etapas
- publicar un banco de ideas aprobadas
- permitir postulación estudiantil con prioridades y soportes
- asignar proyectos aprobados a equipos de estudiantes
- administrar periodos académicos y ventanas de proceso
- proyectar demanda de ideas, base estudiantil y carga docente
- construir formatos dinámicos con exportación a PDF
- generar documentación técnica automática del sistema

En su estado actual, el proyecto refleja una evolución importante frente al README histórico: además del banco de ideas, hoy incluye módulos operativos para calendario académico, planeación, reportes institucionales y gestión documental interna.

## 🚀 Tecnologías utilizadas

### Backend

- **Laravel 10**
- **PHP 8.2 o superior**
- **MySQL / MariaDB**
- **Laravel Sanctum**
- **Laravel Tinker**
- **Eloquent ORM** con modelos base y modelos especializados por rol

### Frontend

- **Blade** como motor de plantillas
- **Tablar** y **Tablar Kit** como base visual administrativa
- **Bootstrap 5.3.1**
- **Sass**
- **Vite** para compilación y hot reload
- **jQuery 3.7**
- **Tailwind CSS 4** configurado en el proyecto

### Visualización, carga de archivos y experiencia UI

- **ApexCharts**
- **Bootstrap Icons**
- **Tabler Icons**
- **FilePond**
- **Dropzone**
- **Flatpickr**
- **Tom Select**
- **Jodit**
- **TinyMCE**
- **Tabulator**
- **List.js**

### Reportes y documentos

- **DomPDF**
- **TCPDF**
- **FPDF / FPDI**
- **PhpSpreadsheet**
- **php_xlsxwriter**
- **jsPDF** y **jspdf-autotable**

### Documentación y calidad

- **PHPUnit**
- **Laravel Pint**
- **Faker**
- **Scribe**
- **Doxygen**
- **Graphviz**
- **Laravel ER Diagram Generator**
- **GitHub Actions**

### Integraciones y librerías adicionales

- **Google API Client**
- **Guzzle**
- **Spatie Laravel HTML**

## 🧩 Módulos funcionales

### 1. Gestión de usuarios y perfiles

- autenticación web por sesión
- creación de usuarios por parte de `research_staff`
- activación e inactivación de cuentas
- perfiles separados para estudiantes, docentes y personal de investigación
- edición de perfil y cambio de contraseña
- carga y actualización de foto de perfil

### 2. Estructura académica e institucional

- departamentos y ciudades
- grupos de investigación
- programas académicos
- relación ciudad-programa
- líneas de investigación
- áreas temáticas

### 3. Banco de ideas y proyectos

- registro de ideas por estudiantes
- registro de ideas por docentes y líderes de comité
- asociación de estudiantes y docentes participantes
- asociación de marcos o contenidos seleccionados
- filtros, búsquedas y vistas por rol
- persistencia de versiones con contenidos diligenciados

### 4. Evaluación por comité

- cola de ideas pendientes por programa/ciudad del comité
- aprobación, rechazo o devolución para corrección
- generación de historial de etapas
- notificación por correo a participantes
- reporte PDF de resultados de evaluación

### 5. Banco de ideas aprobadas

- catálogo de ideas aprobadas para estudiantes
- catálogo de ideas aprobadas para docentes
- consulta de detalle de cada idea
- selección de idea y asignación según reglas de negocio

### 6. Postulaciones estudiantiles

- postulación sobre ideas aprobadas
- soporte en PDF de notas
- trabajo individual o en equipo
- roles dentro del equipo postulante
- manejo de prioridades 1, 2 y 3 por estudiante
- evaluación de postulaciones por comité
- aprobación o rechazo con liberación y reordenamiento de prioridades

### 7. Periodos académicos y ventanas de proceso

- creación y cierre de periodos académicos
- activación de un periodo vigente
- configuración de ventanas para procesos institucionales
- control de disponibilidad de actividades según fecha

Los procesos calendarizados contemplados actualmente son:

- propuesta de ideas
- selección de ideas
- proyección de carga docente
- asignación docente
- proyección de demanda de ideas

### 8. Proyecciones y planeación académica

- proyección de carga por programa y periodo
- base estudiantil con clasificación PG1 y PG2
- proyección de continuidad a PG2
- asignación de horas docentes
- seguimiento de ideas esperadas, registradas y faltantes
- comparativos de demanda vs banco disponible
- exportación de reportes PDF

### 9. Formatos dinámicos

- constructor de tipos de formato
- definición de campos, secciones y reglas
- control de acceso por roles
- registro, edición y consulta de diligenciamientos
- exportación a PDF

### 10. Documentación técnica y operativa

- documentación de API con Scribe
- documentación de código con Doxygen
- diagrama entidad-relación
- listados de rutas web y API
- workflow automático para regenerar artefactos

## 👥 Roles del sistema

### `research_staff`

- administra usuarios
- mantiene catálogos académicos e institucionales
- gestiona periodos, ventanas y proyecciones
- administra formatos dinámicos
- consulta y actualiza módulos transversales del sistema

### `professor`

- registra ideas de proyecto
- consulta ideas aprobadas
- revisa su carga y balance de ideas
- participa en proyectos según asignación académica

### `committee_leader`

- comparte capacidades docentes para ideas
- evalúa ideas pendientes
- evalúa postulaciones estudiantiles
- consulta participantes y reportes de evaluación

### `student`

- registra ideas propias
- consulta banco de ideas aprobadas
- se postula a ideas con prioridad y soportes
- consulta estado de postulaciones y proyectos

### Nota importante sobre el modelo de roles

El código contempla una normalización entre `committe_leader` y `committee_leader` para soportar compatibilidad con datos heredados. A nivel funcional, el rol esperado hoy es `committee_leader`.

## 🔄 Flujos principales

### Flujo 1. Registro de idea

1. El estudiante o docente diligencia la idea.
2. El sistema valida ciudad, programa, integrantes y duplicados.
3. Se crea o actualiza el proyecto.
4. Se genera una nueva versión.
5. Se almacenan los contenidos diligenciados.
6. La idea queda en estado pendiente de evaluación.

### Flujo 2. Evaluación por comité

1. El líder de comité visualiza ideas pendientes de su programa.
2. Revisa participantes, contenidos y versión vigente.
3. Define si la idea queda aprobada, rechazada o devuelta para corrección.
4. El sistema registra la etapa en historial.
5. Se envían notificaciones por correo.

### Flujo 3. Banco aprobado y postulación

1. El estudiante consulta ideas aprobadas disponibles.
2. El sistema valida si puede acceder según su estado académico.
3. El estudiante crea una postulación individual o grupal.
4. Adjunta soporte PDF de notas y asigna una prioridad.
5. El comité evalúa la postulación.
6. Si se aprueba, el proyecto se asigna y se cancelan otras postulaciones pendientes del equipo.

### Flujo 4. Seguimiento académico PG1 y PG2

1. El sistema identifica si un estudiante cursa PG1 o PG2.
2. La decisión se apoya en el proyecto asignado y el periodo de asignación.
3. Si ya cursa PG2 o tiene proyecto en trámite, se bloquean nuevas aperturas del banco y nuevas ideas.

### Flujo 5. Planeación y proyección

1. El personal de investigación define periodos y ventanas.
2. Registra proyecciones de carga.
3. Registra asignaciones docentes.
4. Consulta reportes de demanda, cobertura, balance y productividad.
5. Exporta resultados en PDF para seguimiento institucional.

## 🏗️ Arquitectura del proyecto

### Estructura general

```text
ABI/
├── app/
│   ├── Events/                         # Eventos de dominio
│   ├── Helpers/                        # Utilidades para autenticación y roles
│   ├── Http/
│   │   ├── Controllers/                # Controladores web y API
│   │   ├── Middleware/                 # auth, role, back history, etc.
│   │   └── Requests/                   # Form requests de validación
│   ├── Listeners/                      # Envío de notificaciones
│   ├── Mail/                           # Mailables genéricos
│   ├── Models/                         # Modelos base Eloquent
│   │   ├── Professor/                  # Modelos con conexión restringida docente
│   │   ├── ResearchStaff/              # Modelos con conexión restringida research_staff
│   │   └── Student/                    # Modelos con conexión restringida estudiante
│   └── Services/
│       ├── AcademicCalendar/           # Reglas del calendario académico
│       ├── Projects/                   # Lógica de ideas, carga y participantes
│       ├── Projections/                # Planeación, demanda y asignaciones
│       ├── Reports/                    # Construcción y exportación de reportes PDF
│       └── Students/                   # Estado académico y progresión PG1/PG2
├── bootstrap/
├── config/
├── database/
│   ├── migrations/                     # Migraciones del modelo de datos
│   ├── seeders/                        # Seeders basados en CSV
│   │   └── csvs/                       # Datos de carga inicial
│   └── sql/                            # Script SQL para usuarios/roles de MySQL
├── docs/
│   ├── README.md                       # Índice de documentación técnica
│   └── generated/                      # Artefactos generados automáticamente
├── public/
├── resources/
│   ├── js/                             # Entrada Vite y bootstrap frontend
│   ├── sass/                           # SCSS principal Tablar
│   └── views/                          # Blade por módulo
├── routes/
│   ├── api.php                         # API REST del proyecto
│   └── web.php                         # Rutas web y módulos protegidos
├── scripts/                            # Scripts de base de datos y documentación
├── storage/
├── tests/
├── .github/workflows/                  # CI de documentación automática
├── composer.json
├── package.json
└── README.md
```

### Organización de vistas

Dentro de `resources/views/` ya existen carpetas especializadas por módulo, entre ellas:

- `academic-periods`
- `academic-process-windows`
- `postulations`
- `projects`
- `projections`
- `formats`
- `emails`
- `participants`

Esto ayuda a mantener separada la experiencia de usuario por contexto funcional y rol.

### Servicios de dominio relevantes

- `AcademicCalendarService`: determina periodo activo, ventana vigente y registro de etapas.
- `ProjectIdeaService`: encapsula las reglas de persistencia para ideas registradas por docentes o estudiantes.
- `StudentAcademicProgressService`: calcula PG1/PG2 y restricciones de acceso al banco.
- `TeacherWorkloadService`: resume carga y avance docente.
- `TeacherIdeaBalanceService`: genera recomendaciones de balance de ideas.
- `LoadProjectionService`, `TeacherProjectionService`, `StudentProjectionService`, `IdeaDemandProjectionService`: soportan la planeación académica.
- `VisualReportService`: consolida reportes visuales y exportaciones PDF reutilizables.

## 🗃️ Modelo funcional y de datos

### Núcleo de identidad y perfiles

- `users`
- `students`
- `professors`
- `research_staff`

`users` gestiona acceso, credenciales, rol, estado y foto de perfil. La información académica o personal específica vive en tablas de perfil separadas.

### Estructura académica

- `departments`
- `cities`
- `research_groups`
- `programs`
- `city_program`
- `investigation_lines`
- `thematic_areas`

### Banco de ideas y trazabilidad

- `projects`
- `project_statuses`
- `versions`
- `content_version`
- `contents`
- `content_frameworks`
- `content_framework_project`

### Planeación académica

- `academic_periods`
- `academic_process_windows`
- `project_stage_histories`
- `load_projections`
- `teacher_assignments`

### Postulaciones

- `postulations`
- `postulation_members`
- `postulation_priorities`

### Formatos dinámicos

- `formato_tipos`
- `formato_campos`
- `formato_registros`
- `formato_valores`

### Relación funcional resumida

```text
users 1 --- 1 students
users 1 --- 1 professors
users 1 --- 1 research_staff

departments 1 --- N cities
research_groups 1 --- N programs
research_groups 1 --- N investigation_lines
investigation_lines 1 --- N thematic_areas
cities N --- N programs (city_program)

thematic_areas 1 --- N projects
project_statuses 1 --- N projects
projects 1 --- N versions
versions N --- N contents (content_version)
projects N --- N professors
projects N --- N students
projects N --- N content_frameworks

academic_periods 1 --- N academic_process_windows
academic_periods 1 --- N proposal_projects
academic_periods 1 --- N assigned_projects
projects 1 --- N project_stage_histories

projects 1 --- N postulations
postulations 1 --- N postulation_members
postulations 1 --- N postulation_priorities

formato_tipos 1 --- N formato_campos
formato_tipos 1 --- N formato_registros
formato_registros 1 --- N formato_valores
```

### Particularidad importante del modelo

El proyecto maneja:

- **soft deletes** en múltiples catálogos y entidades
- **versionamiento** de contenidos por proyecto
- **snapshot histórico** en versiones
- **historial de etapas** por proyecto
- **asignación de periodo académico** tanto para propuesta como para asignación

## 🛠️ Instalación y configuración

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

Los scripts requieren permisos MySQL para crear usuarios y asignar privilegios. Consulta la [guía de instalación completa](docs/README.md#conexiones-mysql-por-rol) antes de usarlos en una base de datos administrada o remota.

## 🔐 Conexiones por rol y seguridad de base de datos

Uno de los aspectos más distintivos del proyecto es su estrategia de acceso a datos.

### Conexiones definidas en `config/database.php`

- `mysql`
- `mysql_user`
- `mysql_research_staff`
- `mysql_professor`
- `mysql_student`

### ¿Qué aporta esta estrategia?

- separa credenciales por rol
- restringe privilegios en MySQL
- reduce el alcance de operaciones según el perfil
- alinea el modelo de autorización del sistema con el nivel de base de datos

### ¿Dónde se refleja en el código?

Además de los modelos base, existen modelos especializados en:

- `app/Models/Professor`
- `app/Models/ResearchStaff`
- `app/Models/Student`

Estos modelos heredan comportamiento del modelo principal pero fuerzan la conexión correspondiente al rol.

### Script y SQL involucrados

- `scripts/set-db-roles.ps1`
- `scripts/set-db-roles.sh`
- `scripts/set-db-roles-docker.sh`
- `database/sql/roles.sql`

## ▶️ Ejecución diaria

### Desarrollo

```bash
php artisan serve
npm run dev
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Base de datos

```bash
php artisan migrate
php artisan migrate:rollback
php artisan migrate:refresh
php artisan db:seed
```

### Calidad y pruebas

```bash
php artisan test
./vendor/bin/phpunit
./vendor/bin/pint
```

### Producción

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

## 🧪 Pruebas

El repositorio incluye una suite de pruebas automatizadas con cobertura funcional y unitaria. Actualmente hay **39 archivos de prueba** versionados en `tests/`.

### Cobertura presente en el repositorio

- controladores de catálogos
- controladores de proyectos y evaluación
- vistas de perfil y home
- acceso a perfiles por rol
- API de proyectos, contenidos, versiones y content versions
- historial de versiones
- reportes de proyección
- validación de periodos académicos
- páginas de participantes
- banco de aprobados
- módulo de formatos

### Archivos representativos

- `tests/Feature/ProjectApiTest.php`
- `tests/Feature/ProjectionReportsTest.php`
- `tests/Feature/ProjectVersionHistoryTest.php`
- `tests/Feature/ProfileAccessTest.php`
- `tests/Feature/AcademicPeriodValidationTest.php`
- `tests/Unit/Controllers/ProjectControllerTest.php`
- `tests/Unit/Controllers/ProjectEvaluationControllerTest.php`
- `tests/Unit/Controllers/FormularioControllerTest.php`

### Ejecutar pruebas

```bash
php artisan test
```

## 🌐 API y documentación automática

### API disponible

En `routes/api.php` se exponen recursos para:

- `research-groups`
- `programs`
- `investigation-lines`
- `thematic-areas`
- `contents`
- `versions`
- `content-versions`
- `projects`

Además existen endpoints adicionales como:

- `GET /api/projects/meta`
- `POST /api/projects/{project}/restore`

### Nota sobre autenticación API

Laravel Sanctum está configurado en el proyecto y el endpoint `GET /api/user` está protegido con `auth:sanctum`. El resto de recursos API deben revisarse según la configuración actual de rutas del entorno antes de publicarse externamente.

### Documentación generada

La documentación técnica vive en:

- `docs/README.md`
- `docs/generated/api`
- `docs/generated/code`
- `docs/generated/diagrams`
- `docs/generated/routes`

Archivos de consulta frecuentes:

```text
docs/generated/api/index.html
docs/generated/api/openapi.yaml
docs/generated/api/collection.json
docs/generated/code/html/index.html
docs/generated/diagrams/erd.svg
docs/generated/routes/all-routes.txt
docs/generated/routes/api-routes.txt
```

### Generación local

```bash
bash scripts/generate-docs.sh
```

El script:

- genera listados de rutas
- ejecuta Scribe si está disponible
- genera ERD si el comando existe
- ejecuta Doxygen si está instalado en el sistema

### GitHub Actions

Workflow disponible:

```text
.github/workflows/auto-docs.yml
```

Este workflow:

- se ejecuta por `push` a `main` y `docs/auto-documentacion`
- también admite ejecución manual
- regenera `docs/generated`
- hace commit automático cuando detecta cambios en esa carpeta

## 📊 Reportes, archivos y almacenamiento

### Exportaciones PDF

El sistema genera PDF para varios contextos:

- reportes de evaluación por comité
- reportes de proyecciones
- formatos dinámicos

La lógica común de reportes visuales se concentra en `app/Services/Reports/VisualReportService.php`.

### Carga de archivos

- las notas adjuntas de postulaciones se almacenan en disco `local`
- las fotos de perfil se almacenan en disco `public`

Por eso es importante crear el enlace simbólico:

```bash
php artisan storage:link
```

### Versionamiento de proyectos

Cada proyecto puede generar múltiples versiones, y cada versión puede almacenar:

- snapshot histórico
- usuario creador
- valores diligenciados por contenido

Esto permite trazabilidad del proyecto a lo largo del ciclo de evaluación y ajuste.

## ☁️ Despliegue

### Recomendaciones mínimas

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
```

### Aspectos a considerar antes de publicar

- compilar assets con `npm run build`
- ejecutar caches de Laravel
- configurar correctamente correo si se usarán notificaciones
- configurar colas si se quiere desacoplar el envío de correos
- asegurar `storage:link`
- validar permisos de base de datos por rol

### Colas y notificaciones

El listener `SendNotificationListener` implementa `ShouldQueue`. En desarrollo, con `QUEUE_CONNECTION=sync`, los correos se procesan en línea. En producción es recomendable ejecutar un worker real.

## 🤝 Contribución

Sugerencia de flujo de trabajo:

1. crea una rama para tu cambio
2. realiza ajustes en código o documentación
3. ejecuta pruebas del módulo afectado
4. valida rutas, vistas y permisos según rol
5. abre un Pull Request con contexto funcional y técnico

Si tu cambio toca:

- migraciones
- seeders
- permisos por rol
- reportes
- documentación automática

incluye evidencia de validación, ya que son áreas transversales del sistema.

## 📄 Licencia

`composer.json` conserva la referencia estándar de Laravel a licencia MIT, pero actualmente el repositorio no incluye un archivo `LICENSE` versionado en la raíz. Si el proyecto va a distribuirse fuera del equipo, conviene formalizar este punto con una licencia explícita.
