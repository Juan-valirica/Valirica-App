-- ============================================================
-- Migración: Asignaciones de equipo a áreas de trabajo
-- Usuario ID: 56 (Aloja)
-- Fecha: 2026-02-17
-- ============================================================

-- 1. Renombrar área "Innovación" a "Creatividad e Innovación"
UPDATE areas_trabajo
SET nombre_area = 'Creatividad e Innovación'
WHERE id = 83 AND usuario_id = 56;

-- 2. Limpiar asignaciones previas para este usuario (si existen)
DELETE eat FROM equipo_areas_trabajo eat
INNER JOIN equipo e ON eat.equipo_id = e.id
WHERE e.usuario_id = 56;

-- 3. Insertar todas las asignaciones de áreas
-- ============================================================
-- VENTAS (area_id = 80)
-- Líder: Cristian (134)
-- ============================================================
INSERT INTO equipo_areas_trabajo (equipo_id, area_id, es_lider) VALUES
(134, 80, 1),  -- Cristian - LÍDER
(144, 80, 0),  -- Aitor
(136, 80, 0);  -- Tomas

-- ============================================================
-- CUENTAS (area_id = 81)
-- Líder: Daniela (147)
-- ============================================================
INSERT INTO equipo_areas_trabajo (equipo_id, area_id, es_lider) VALUES
(147, 81, 1),  -- Daniela - LÍDER
(135, 81, 0),  -- Christine
(142, 81, 0),  -- Carolina
(132, 81, 0),  -- Rebeca
(134, 81, 0),  -- Cristian
(140, 81, 0);  -- Gonzalo

-- ============================================================
-- PRODUCCIÓN (area_id = 82)
-- Líder: Santiago (128)
-- ============================================================
INSERT INTO equipo_areas_trabajo (equipo_id, area_id, es_lider) VALUES
(128, 82, 1),  -- Santiago - LÍDER
(145, 82, 0),  -- Daniel
(133, 82, 0);  -- Alessandra

-- ============================================================
-- CREATIVIDAD E INNOVACIÓN (area_id = 83)
-- Líder: Pablo (146)
-- ============================================================
INSERT INTO equipo_areas_trabajo (equipo_id, area_id, es_lider) VALUES
(146, 83, 1),  -- Pablo - LÍDER
(137, 83, 0),  -- Natalia
(143, 83, 0);  -- Gabriela

-- ============================================================
-- ADMINISTRACIÓN (area_id = 84)
-- Líder: Santiago (128)
-- ============================================================
INSERT INTO equipo_areas_trabajo (equipo_id, area_id, es_lider) VALUES
(128, 84, 1);  -- Santiago - LÍDER (único miembro)

-- ============================================================
-- MARKETING (area_id = 85)
-- Líder: Cristian (134)
-- ============================================================
INSERT INTO equipo_areas_trabajo (equipo_id, area_id, es_lider) VALUES
(134, 85, 1),  -- Cristian - LÍDER
(146, 85, 0),  -- Pablo
(143, 85, 0),  -- Gabriela
(135, 85, 0);  -- Christine

-- 4. Actualizar campo legacy equipo.area_trabajo con el área principal de cada miembro
UPDATE equipo e
INNER JOIN equipo_areas_trabajo eat ON e.id = eat.equipo_id
INNER JOIN areas_trabajo at2 ON eat.area_id = at2.id
SET e.area_trabajo = at2.nombre_area
WHERE e.usuario_id = 56
AND eat.area_id = (
    SELECT MIN(eat2.area_id)
    FROM equipo_areas_trabajo eat2
    WHERE eat2.equipo_id = e.id
);
