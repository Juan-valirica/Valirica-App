-- ============================================================
-- MIGRACIÓN: Soporte de múltiples áreas de trabajo por miembro
-- Tabla junction: equipo_areas_trabajo (many-to-many)
-- ============================================================

-- 1) Crear tabla junction
CREATE TABLE IF NOT EXISTS equipo_areas_trabajo (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id   INT          NOT NULL,
    area_id     INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_equipo_area (equipo_id, area_id),
    KEY idx_area (area_id),
    KEY idx_equipo (equipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Migrar datos existentes de equipo.area_trabajo → equipo_areas_trabajo
--    Solo migra filas donde area_trabajo tiene un nombre válido en areas_trabajo
INSERT IGNORE INTO equipo_areas_trabajo (equipo_id, area_id)
SELECT e.id, at2.id
FROM equipo e
INNER JOIN areas_trabajo at2
    ON at2.nombre_area = e.area_trabajo
    AND at2.usuario_id = e.usuario_id
WHERE e.area_trabajo IS NOT NULL
  AND e.area_trabajo != ''
  AND e.area_trabajo != '—';
