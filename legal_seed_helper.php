<?php
/**
 * legal_seed_helper.php
 * Funciones para sembrar documentos legales en la tabla `documentos`
 * para usuarios empresa. Es idempotente: no duplica registros existentes.
 *
 * Uso:  require_once 'legal_seed_helper.php';
 *       seed_legal_docs_for_user($conn, $empresa_id);
 */

/**
 * Determina el nombre del archivo de contrato según la ubicación.
 */
function get_contrato_by_ubicacion(string $ubicacion): string {
    $loc = mb_strtolower($ubicacion, 'UTF-8');
    if (
        str_contains($loc, 'españa')  ||
        str_contains($loc, 'espana')  ||
        str_contains($loc, 'spain')   ||
        str_contains($loc, 'madrid')  ||
        str_contains($loc, 'barcelona') ||
        str_contains($loc, 'es')
    ) {
        return 'contrato-espana';
    }
    // Default: Colombia
    return 'contrato-colombia';
}

/**
 * Siembra los 4 documentos legales para un usuario empresa.
 * Solo inserta los que aún no existan (comprueba por titulo + empresa_id).
 *
 * @param  mysqli $conn
 * @param  int    $empresa_id  ID del usuario empresa (tabla usuarios)
 * @return array  ['inserted' => [...titulos], 'errors' => [...msgs]]
 */
function seed_legal_docs_for_user(mysqli $conn, int $empresa_id): array {
    $inserted = [];
    $errors   = [];

    // Asegurar que la tabla documentos existe
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS documentos (
                id             INT           AUTO_INCREMENT PRIMARY KEY,
                empresa_id     INT           NOT NULL,
                empleado_id    INT           DEFAULT NULL,
                titulo         VARCHAR(255)  NOT NULL,
                descripcion    TEXT,
                tipo           ENUM('pdf','drive','microsoft') NOT NULL DEFAULT 'drive',
                url_documento  VARCHAR(2000),
                nombre_archivo VARCHAR(500),
                ruta_archivo   VARCHAR(1000),
                categoria      VARCHAR(100)  NOT NULL DEFAULT 'general',
                estado         ENUM('nuevo','leido','archivado','aceptado') NOT NULL DEFAULT 'nuevo',
                creado_por     INT,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_empresa  (empresa_id),
                INDEX idx_empleado (empleado_id),
                INDEX idx_estado   (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        $errors[] = 'CREATE TABLE: ' . $e->getMessage();
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    // Obtener ubicación de cultura_ideal
    $ubicacion = '';
    try {
        $st = $conn->prepare("SELECT ubicacion FROM cultura_ideal WHERE usuario_id = ? LIMIT 1");
        if ($st) {
            $st->bind_param("i", $empresa_id);
            $st->execute();
            $row = stmt_get_result($st)->fetch_assoc();
            $ubicacion = $row['ubicacion'] ?? '';
            $st->close();
        }
    } catch (\Throwable $e) {
        // Si cultura_ideal no tiene datos, usar contrato Colombia por defecto
    }

    $contrato_doc  = get_contrato_by_ubicacion($ubicacion);
    $contrato_label = ($contrato_doc === 'contrato-espana') ? 'España' : 'Colombia';

    // Definición de los 4 documentos legales
    $docs = [
        [
            'titulo'      => 'Contrato de Servicios – ' . $contrato_label,
            'descripcion' => 'Contrato de prestación de servicios SaaS de Valírica.',
            'url'         => 'ver_legal.php?doc=' . $contrato_doc,
            'categoria'   => 'contrato',
        ],
        [
            'titulo'      => 'Política de Cookies',
            'descripcion' => 'Información sobre el uso de cookies en la plataforma Valírica.',
            'url'         => 'ver_legal.php?doc=cookies',
            'categoria'   => 'politica',
        ],
        [
            'titulo'      => 'Política de Privacidad',
            'descripcion' => 'Política de tratamiento y protección de datos personales.',
            'url'         => 'ver_legal.php?doc=privacidad',
            'categoria'   => 'politica',
        ],
        [
            'titulo'      => 'Términos de Uso',
            'descripcion' => 'Términos y condiciones de uso de la plataforma Valírica.',
            'url'         => 'ver_legal.php?doc=terminos',
            'categoria'   => 'politica',
        ],
    ];

    foreach ($docs as $doc) {
        // ¿Ya existe este documento para esta empresa?
        try {
            $chk = $conn->prepare(
                "SELECT id FROM documentos WHERE empresa_id = ? AND titulo = ? LIMIT 1"
            );
            if (!$chk) {
                $errors[] = $doc['titulo'] . ' (prepare check): ' . $conn->error;
                continue;
            }
            $chk->bind_param("is", $empresa_id, $doc['titulo']);
            $chk->execute();
            $exists = (bool) stmt_get_result($chk)->fetch_assoc();
            $chk->close();
        } catch (\Throwable $e) {
            $errors[] = $doc['titulo'] . ' (check): ' . $e->getMessage();
            continue;
        }

        if ($exists) {
            continue; // Idempotente: ya existe, no duplicar
        }

        // Insertar el documento legal
        try {
            $ins = $conn->prepare("
                INSERT INTO documentos
                  (empresa_id, empleado_id, titulo, descripcion, tipo,
                   url_documento, nombre_archivo, ruta_archivo, categoria, estado, creado_por)
                VALUES (?, NULL, ?, ?, 'drive', ?, NULL, NULL, ?, 'nuevo', 0)
            ");
            if (!$ins) {
                $errors[] = $doc['titulo'] . ' (prepare insert): ' . $conn->error;
                continue;
            }
            $ins->bind_param(
                "issss",
                $empresa_id,
                $doc['titulo'],
                $doc['descripcion'],
                $doc['url'],
                $doc['categoria']
            );
            if ($ins->execute()) {
                $inserted[] = $doc['titulo'];
            } else {
                $errors[] = $doc['titulo'] . ' (insert): ' . $ins->error;
            }
            $ins->close();
        } catch (\Throwable $e) {
            $errors[] = $doc['titulo'] . ' (insert): ' . $e->getMessage();
        }
    }

    return ['inserted' => $inserted, 'errors' => $errors];
}
