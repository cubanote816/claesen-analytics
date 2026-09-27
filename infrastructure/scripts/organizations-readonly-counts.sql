-- Multiempresa Electro Bertels — conteos de SOLO LECTURA para dimensionar los backfills (ADR §7, pregunta 6).
--
-- Solo SELECT. Ninguna sentencia escribe, crea ni altera nada.
-- Ejecutar SIEMPRE con la sesión forzada a solo lectura, para que el servidor lo garantice
-- aunque este archivo se edite por error:
--
--   mysql --init-command="SET SESSION TRANSACTION READ ONLY" -u <usuario_lectura> -p <base> \
--         < infrastructure/scripts/organizations-readonly-counts.sql
--
-- Requiere autorización explícita del usuario (destino, operación y credencial) antes de correrse
-- contra producción. Pegar la salida completa en el ticket de P7.
--
-- SECCIÓN A — antes de desplegar las migraciones del programa (producción hoy: sin columnas
--   organization_id/site_id). Es la que responde la pregunta 6 y se puede correr ya.
-- SECCIÓN B — después de `migrate` + backfill: verifica que no queden NULL antes de `NOT NULL` (P7).
--   Falla con "Unknown column" si se corre antes de las migraciones; es lo esperado.

-- =====================================================================================
-- SECCIÓN A — dimensionado previo
-- =====================================================================================

SELECT '== A1. Motor y versión ==' AS section;
SELECT VERSION() AS mysql_version, DATABASE() AS db, @@transaction_read_only AS session_read_only;

SELECT '== A2. Tamaño de las tablas que reciben columnas nuevas (coste del ALTER) ==' AS section;
SELECT table_name,
       table_rows AS approx_rows,
       ROUND(data_length / 1024 / 1024, 1) AS data_mb,
       ROUND(index_length / 1024 / 1024, 1) AS index_mb
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('users', 'activity_log', 'website_projects',
                     'website_consultation_requests', 'website_publication_states')
ORDER BY data_length DESC;

SELECT '== A3. Filas exactas a rellenar ==' AS section;
SELECT 'users' AS tabla, COUNT(*) AS filas FROM users
UNION ALL SELECT 'website_projects', COUNT(*) FROM website_projects
UNION ALL SELECT 'website_consultation_requests', COUNT(*) FROM website_consultation_requests
UNION ALL SELECT 'website_publication_states', COUNT(*) FROM website_publication_states
UNION ALL SELECT 'activity_log (sin backfill histórico por diseño)', COUNT(*) FROM activity_log;

SELECT '== A4. Usuarios: todos pasarán a Claesen; revisar los que NO son del dominio corporativo ==' AS section;
SELECT COUNT(*) AS total,
       SUM(is_active = 1) AS activos,
       SUM(is_active = 0) AS inactivos,
       SUM(employee_id IS NULL) AS sin_employee_id,
       SUM(LOWER(email) NOT LIKE '%@claesen-verlichting.be') AS email_fuera_del_dominio_corporativo,
       SUM(LOWER(email) LIKE '%@electrobertels.be') AS email_electrobertels_ya_existente
FROM users;

SELECT '== A5. Usuarios por rol (los roles Spatie siguen siendo globales) ==' AS section;
SELECT r.name AS rol, COUNT(DISTINCT mhr.model_id) AS usuarios
FROM model_has_roles mhr
JOIN roles r ON r.id = mhr.role_id
WHERE mhr.model_type LIKE '%User'
GROUP BY r.name
ORDER BY usuarios DESC;

SELECT '== A6. Usuarios sin ningún rol (caerían fuera de todo enforcement) ==' AS section;
SELECT COUNT(*) AS usuarios_sin_rol
FROM users u
WHERE NOT EXISTS (SELECT 1 FROM model_has_roles mhr WHERE mhr.model_id = u.id AND mhr.model_type LIKE '%User');

SELECT '== A7. Website: filas por estado (todas irán al sitio de Claesen) ==' AS section;
SELECT 'website_projects' AS tabla, COUNT(*) AS total, SUM(published = 1) AS publicados FROM website_projects;
SELECT status, COUNT(*) AS filas FROM website_consultation_requests GROUP BY status;

SELECT '== A8. Unicidad del slug de proyectos (la migración lo pasa a UNIQUE(site_id, slug)) ==' AS section;
SELECT COUNT(*) AS slugs_duplicados
FROM (SELECT slug FROM website_projects GROUP BY slug HAVING COUNT(*) > 1) d;

SELECT '== A9. Publicación: filas en website_publication_states (hoy singleton id=1; el UNIQUE(site_id) exige 1 fila por sitio) ==' AS section;
SELECT id, updated_at FROM website_publication_states ORDER BY id;

SELECT '== A10. activity_log: antigüedad y volumen (define retención y coste del ALTER) ==' AS section;
SELECT MIN(created_at) AS mas_antigua, MAX(created_at) AS mas_reciente, COUNT(*) AS filas FROM activity_log;

-- =====================================================================================
-- SECCIÓN B — verificación posterior (solo después de migrate + backfill)
-- =====================================================================================
-- Descomentar solo tras desplegar las migraciones.
--
-- SELECT '== B1. NULL restantes (deben ser 0 antes de NOT NULL) ==' AS section;
-- SELECT 'users.organization_id' AS columna, COUNT(*) AS nulos FROM users WHERE organization_id IS NULL
-- UNION ALL SELECT 'website_projects.site_id', COUNT(*) FROM website_projects WHERE site_id IS NULL
-- UNION ALL SELECT 'website_consultation_requests.site_id', COUNT(*) FROM website_consultation_requests WHERE site_id IS NULL
-- UNION ALL SELECT 'website_publication_states.site_id', COUNT(*) FROM website_publication_states WHERE site_id IS NULL;
--
-- SELECT '== B2. Reparto por organización/sitio ==' AS section;
-- SELECT o.slug AS organizacion, COUNT(u.id) AS usuarios FROM organizations o LEFT JOIN users u ON u.organization_id = o.id GROUP BY o.slug;
-- SELECT s.`key` AS sitio, COUNT(p.id) AS proyectos FROM sites s LEFT JOIN website_projects p ON p.site_id = s.id GROUP BY s.`key`;
