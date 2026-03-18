<?php
/**
 * Migration: Canal de Denuncias — Paso 1
 * Run once, then delete this file.
 */
require_once __DIR__ . '/config.php';

$migrations = [];

// 1. Añadir country a usuarios
$migrations['country_usuarios'] = "
    ALTER TABLE usuarios ADD COLUMN country ENUM('ES', 'CO') DEFAULT 'ES' AFTER empresa
";

// 2. Extender ENUM tipo en notificaciones
$migrations['notificaciones_enum'] = "
    ALTER TABLE notificaciones
      MODIFY COLUMN tipo ENUM(
        'permiso_solicitado','permiso_aprobado','permiso_rechazado',
        'vacacion_solicitada','vacacion_aprobada','vacacion_rechazada',
        'denuncia_recibida','denuncia_asignada','denuncia_resuelta','denuncia_vencimiento'
      ) NOT NULL
";

// 3. Tabla complaints
$migrations['create_complaints'] = "
    CREATE TABLE complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        reference_code VARCHAR(16) UNIQUE NOT NULL,
        country ENUM('ES', 'CO') NOT NULL,
        type ENUM(
            'acoso_laboral','acoso_sexual','fraude','corrupcion',
            'discriminacion','incumplimiento_normativo','conflicto_interes','otro'
        ) NOT NULL,
        description MEDIUMTEXT NOT NULL,
        is_anonymous TINYINT(1) DEFAULT 1,
        reporter_equipo_id INT NULL,
        reporter_encrypted_name TEXT NULL,
        reporter_encrypted_email TEXT NULL,
        assigned_to INT NULL,
        status ENUM('recibida','en_tramite','resuelta','archivada') DEFAULT 'recibida',
        priority ENUM('baja','media','alta','critica') DEFAULT 'media',
        receipt_sent_at TIMESTAMP NULL,
        receipt_deadline TIMESTAMP NULL,
        resolution_deadline TIMESTAMP NULL,
        resolved_at TIMESTAMP NULL,
        internal_notes MEDIUMTEXT NULL,
        evidence_paths JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_equipo_id) REFERENCES equipo(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES usuarios(id) ON DELETE SET NULL,
        INDEX idx_company_status (company_id, status),
        INDEX idx_reference (reference_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

// 4. Tabla complaint_activities
$migrations['create_complaint_activities'] = "
    CREATE TABLE complaint_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_id INT NOT NULL,
        actor_tipo ENUM('sistema','empresa','empleado') DEFAULT 'sistema',
        actor_id INT NULL,
        action VARCHAR(100) NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

// 5. Tabla complaint_channel_config
$migrations['create_complaint_channel_config'] = "
    CREATE TABLE complaint_channel_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL UNIQUE,
        canal_slug VARCHAR(60) NULL UNIQUE,
        country ENUM('ES', 'CO') NOT NULL DEFAULT 'ES',
        is_anonymous_allowed TINYINT(1) DEFAULT 1,
        responsible_user_id INT NULL,
        channel_policy_text TEXT NULL,
        receipt_days INT DEFAULT 7,
        resolution_days INT DEFAULT 90,
        notification_email VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (responsible_user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
        INDEX idx_canal_slug (canal_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$results = [];
foreach ($migrations as $name => $sql) {
    $sql = trim($sql);
    $ok = $conn->query($sql);
    if ($ok) {
        $results[$name] = 'OK';
    } else {
        // 1060 = duplicate column, 1050 = table exists, 1060 = already exists — skip gracefully
        $errno = $conn->errno;
        $error = $conn->error;
        if (in_array($errno, [1060, 1050, 1091])) {
            $results[$name] = "SKIP (already exists: $error)";
        } else {
            $results[$name] = "ERROR $errno: $error";
        }
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== VALIRICA MIGRATIONS — Canal de Denuncias ===\n\n";
foreach ($results as $name => $status) {
    $icon = str_starts_with($status, 'OK') ? '✅' : (str_starts_with($status, 'SKIP') ? '⚠️' : '❌');
    echo "$icon  $name: $status\n";
}
echo "\nDone. Delete this file after confirming.\n";
