-- ======================================
-- ROLES AND PERMISSIONS MYSQL
-- ======================================
-- This file defines MySQL users and assigns permissions
-- for different roles in the system:
--   - db_user (basic login)
--   - db_student
--   - db_professor
--   - db_research_staff
--
-- General notes:
-- 1. Users are defined for three hosts: %, localhost, and 127.0.0.1
--    to ensure compatibility across the development team.
--    In production, only the required host should remain (usually %).
--
-- 2. Placeholders like {{DB_DATABASE}}, {{DB_USER_PASS}}, etc.
--    are replaced at runtime using environment variables defined in `.env`.
--
-- 3. Main permissions used in this setup:
--    - SELECT = read-only access
--    - INSERT, UPDATE = controlled data modification
-- ======================================


-- ======================================
-- HOST: %
-- ======================================

-- Create a basic user for login
DROP USER IF EXISTS 'db_user'@'%';
CREATE USER IF NOT EXISTS 'db_user'@'%' IDENTIFIED BY '{{DB_USER_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_user'@'%';

-- Basic users can:
GRANT ALL PRIVILEGES ON {{DB_DATABASE}}.* TO 'db_user'@'%';

-- GRANT SELECT, INSERT, UPDATE, DELETE ON {{DB_DATABASE}}.password_reset_tokens TO 'db_user'@'%';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON {{DB_DATABASE}}.personal_access_tokens TO 'db_user'@'%';


-- Create user for students
DROP USER IF EXISTS 'db_student'@'%';
CREATE USER IF NOT EXISTS 'db_student'@'%' IDENTIFIED BY '{{DB_STUDENT_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_student'@'%';

-- Students can:
GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.student_project TO 'db_student'@'%';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_student'@'%';


-- Create a user for professors
DROP USER IF EXISTS 'db_professor'@'%';
CREATE USER IF NOT EXISTS 'db_professor'@'%' IDENTIFIED BY '{{DB_PROFESSOR_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_professor'@'%';

-- Professors can:
GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professor_project TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_professor'@'%';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_professor'@'%';


-- Create a user for research_staff
DROP USER IF EXISTS 'db_research_staff'@'%';
CREATE USER IF NOT EXISTS 'db_research_staff'@'%' IDENTIFIED BY '{{DB_RESEARCH_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_research_staff'@'%';

-- Research staff can:
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.departments TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.cities TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.city_program TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.programs TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_groups TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.investigation_lines TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.thematic_areas TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.academic_periods TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.academic_process_windows TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.load_projections TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.teacher_assignments TO 'db_research_staff'@'%';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.content_framework_project TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_frameworks TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.frameworks TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.contents TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professors TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.students TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.users TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_staff TO 'db_research_staff'@'%';



-- ======================================
-- HOST: localhost
-- ======================================

-- Create a basic user for login
DROP USER IF EXISTS 'db_user'@'localhost';
CREATE USER IF NOT EXISTS 'db_user'@'localhost' IDENTIFIED BY '{{DB_USER_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_user'@'localhost';

-- Basic users can:
GRANT ALL PRIVILEGES ON {{DB_DATABASE}}.* TO 'db_user'@'localhost';

-- GRANT SELECT, INSERT, UPDATE, DELETE ON {{DB_DATABASE}}.password_reset_tokens TO 'db_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON {{DB_DATABASE}}.personal_access_tokens TO 'db_user'@'localhost';


-- Create user for students
DROP USER IF EXISTS 'db_student'@'localhost';
CREATE USER IF NOT EXISTS 'db_student'@'localhost' IDENTIFIED BY '{{DB_STUDENT_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_student'@'localhost';

-- Students can:
GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.student_project TO 'db_student'@'localhost';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_student'@'localhost';


-- Create a user for professors
DROP USER IF EXISTS 'db_professor'@'localhost';
CREATE USER IF NOT EXISTS 'db_professor'@'localhost' IDENTIFIED BY '{{DB_PROFESSOR_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_professor'@'localhost';

-- Professors can:
GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professor_project TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_professor'@'localhost';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_professor'@'localhost';


-- Create a user for research_staff
DROP USER IF EXISTS 'db_research_staff'@'localhost';
CREATE USER IF NOT EXISTS 'db_research_staff'@'localhost' IDENTIFIED BY '{{DB_RESEARCH_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_research_staff'@'localhost';

-- Research staff can:
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.departments TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.cities TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.city_program TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.programs TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_groups TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.investigation_lines TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.thematic_areas TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.academic_periods TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.academic_process_windows TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.load_projections TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.teacher_assignments TO 'db_research_staff'@'localhost';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.content_framework_project TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_frameworks TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.frameworks TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.contents TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professors TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.students TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.users TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_staff TO 'db_research_staff'@'localhost';



-- ======================================
-- HOST: 127.0.0.1
-- ======================================

-- Create a basic user for login
DROP USER IF EXISTS 'db_user'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_user'@'127.0.0.1' IDENTIFIED BY '{{DB_USER_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_user'@'127.0.0.1';

-- Basic users can:
GRANT ALL PRIVILEGES ON {{DB_DATABASE}}.* TO 'db_user'@'127.0.0.1';

-- GRANT SELECT, INSERT, UPDATE, DELETE ON {{DB_DATABASE}}.password_reset_tokens TO 'db_user'@'127.0.0.1';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON {{DB_DATABASE}}.personal_access_tokens TO 'db_user'@'127.0.0.1';


-- Create user for students
DROP USER IF EXISTS 'db_student'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_student'@'127.0.0.1' IDENTIFIED BY '{{DB_STUDENT_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_student'@'127.0.0.1';

-- Students can:
GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.student_project TO 'db_student'@'127.0.0.1';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_student'@'127.0.0.1';


-- Create user for professors
DROP USER IF EXISTS 'db_professor'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_professor'@'127.0.0.1' IDENTIFIED BY '{{DB_PROFESSOR_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_professor'@'127.0.0.1';

-- Professors can:
GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professor_project TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_professor'@'127.0.0.1';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_professor'@'127.0.0.1';


-- Create a user for research_staff
DROP USER IF EXISTS 'db_research_staff'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_research_staff'@'127.0.0.1' IDENTIFIED BY '{{DB_RESEARCH_PASS}}';

-- Database connection permission
GRANT USAGE ON *.* TO 'db_research_staff'@'127.0.0.1';

-- Research staff can:
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.departments TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.cities TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.city_program TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.programs TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_groups TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.investigation_lines TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.thematic_areas TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.academic_periods TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.academic_process_windows TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.load_projections TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.teacher_assignments TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.content_framework_project TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_frameworks TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.frameworks TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.contents TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professors TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.students TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.users TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_staff TO 'db_research_staff'@'127.0.0.1';


-- Compatibility with older versions
FLUSH PRIVILEGES;
