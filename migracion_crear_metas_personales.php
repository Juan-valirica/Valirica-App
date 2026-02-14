<?php
require 'config.php';

// asegúrate de tener esto en config.php justo después de crear $conn:
// $conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
// $conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS metas_personales (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED    NOT NULL,
  meta_empresa_id  INT UNSIGNED    NOT NULL,
  area_id          INT UNSIGNED    NOT NULL,
  meta_area_id     INT UNSIGNED    NOT NULL,
  persona_id       INT UNSIGNED    NOT NULL,
  descripcion      VARCHAR(500)    NOT NULL,
  due_date         DATE            NOT NULL,
  progress_pct     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_completed     TINYINT(1)      NOT NULL DEFAULT 0,
  completed_at     DATETIME        NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_area (user_id, area_id),
  KEY idx_meta_area (meta_area_id),
  KEY idx_persona (persona_id),
  KEY idx_due (due_date),
  KEY idx_completed (is_completed)
)
ENGINE=InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;
SQL;

try {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->query($sql);
  echo "OK: Tabla metas_personales creada o ya existía.\n";

  // Intenta agregar FKs (si fallan por tipos o permisos, no pasa nada).
  $conn->query("
    ALTER TABLE metas_personales
      ADD CONSTRAINT fk_mp_user         FOREIGN KEY (user_id)         REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
      ADD CONSTRAINT fk_mp_meta_empresa FOREIGN KEY (meta_empresa_id) REFERENCES metas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
      ADD CONSTRAINT fk_mp_area         FOREIGN KEY (area_id)         REFERENCES areas_trabajo(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
      ADD CONSTRAINT fk_mp_meta_area    FOREIGN KEY (meta_area_id)    REFERENCES metas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
      ADD CONSTRAINT fk_mp_persona      FOREIGN KEY (persona_id)      REFERENCES equipo(id)
        ON DELETE CASCADE ON UPDATE CASCADE
  ");
  echo "OK: Llaves foráneas agregadas.\n";
} catch (Throwable $e) {
  echo "AVISO: No se pudieron crear las llaves foráneas: " . $e->getMessage() . "\n";
  echo "La tabla quedó creada y usable sin FKs. Luego las añadimos.\n";
}
