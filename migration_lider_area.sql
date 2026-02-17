-- Migration: Agregar campo es_lider a equipo_areas_trabajo
-- Permite identificar al líder de cada área de trabajo.
-- Es opcional (default 0), cada área puede tener máximo un líder.

ALTER TABLE equipo_areas_trabajo
  ADD COLUMN es_lider TINYINT(1) NOT NULL DEFAULT 0
  AFTER area_id;
