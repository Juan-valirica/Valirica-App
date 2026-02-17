-- ============================================================
-- Migración: Agregar area_id a la tabla tareas
-- Cada tarea pertenece a un área de trabajo de la empresa
-- Fecha: 2026-02-17
-- ============================================================

ALTER TABLE tareas
ADD COLUMN area_id INT NULL DEFAULT NULL AFTER orden,
ADD INDEX idx_area (area_id);
