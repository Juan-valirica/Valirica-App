<?php
/**
 * documentos_backend.php
 * AJAX backend for the document management system.
 * All responses are JSON.
 */

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

/* ── Auth ── */
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

/* ─────────────────────────────────────────────
   AUTO-MIGRATION: ensure table exists
   ───────────────────────────────────────────── */
$conn->query("
  CREATE TABLE IF NOT EXISTS documentos (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    empresa_id     INT           NOT NULL,
    empleado_id    INT           DEFAULT NULL,
    titulo         VARCHAR(255)  NOT NULL,
    descripcion    TEXT,
    tipo           ENUM('pdf','drive','microsoft') NOT NULL DEFAULT 'pdf',
    url_documento  VARCHAR(2000),
    nombre_archivo VARCHAR(500),
    ruta_archivo   VARCHAR(1000),
    categoria      VARCHAR(100)  NOT NULL DEFAULT 'general',
    estado         ENUM('nuevo','leido','archivado') NOT NULL DEFAULT 'nuevo',
    creado_por     INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresa  (empresa_id),
    INDEX idx_empleado (empleado_id),
    INDEX idx_estado   (estado)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ─────────────────────────────────────────────
   HELPER: verify doc belongs to user's company
   ───────────────────────────────────────────── */
function doc_belongs_to_user(mysqli $conn, int $doc_id, int $user_id): bool {
    $st = $conn->prepare("SELECT id FROM documentos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $st->bind_param("ii", $doc_id, $user_id);
    $st->execute();
    return (bool)stmt_get_result($st)->fetch_assoc();
}

/* ─────────────────────────────────────────────
   UPLOAD DIR
   ───────────────────────────────────────────── */
$upload_base = __DIR__ . '/uploads/documentos/';
if (!is_dir($upload_base)) {
    mkdir($upload_base, 0755, true);
}

/* ═══════════════════════════════════════════════════════════════
   ACTIONS
   ═══════════════════════════════════════════════════════════════ */
switch ($action) {

    /* ── Listar documentos de empresa (con filtros) ── */
    case 'listar':
        $empleado_id_f = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : null;
        $tipo_f        = $_GET['tipo']      ?? '';
        $categoria_f   = $_GET['categoria'] ?? '';
        $scope         = $_GET['scope']     ?? 'todos';
        // Accept both 'q' (frontend) and 'busqueda' (legacy)
        $q             = trim($_GET['q'] ?? $_GET['busqueda'] ?? '');

        $where  = ["d.empresa_id = ?"];
        $params = [$user_id];
        $types  = "i";

        // Scope → estado/empleado filters
        switch ($scope) {
            case 'empresa':
                $where[] = "d.empleado_id IS NULL";
                $where[] = "d.estado != 'archivado'";
                break;
            case 'nuevos':
                $where[] = "d.estado = 'nuevo'";
                break;
            case 'archivados':
                $where[] = "d.estado = 'archivado'";
                break;
            default: // todos, empleado
                $where[] = "d.estado != 'archivado'";
                break;
        }

        if ($empleado_id_f !== null) {
            $where[] = "d.empleado_id = ?";
            $params[] = $empleado_id_f;
            $types .= "i";
        }

        if ($tipo_f !== '') {
            $where[] = "d.tipo = ?";
            $params[] = $tipo_f;
            $types .= "s";
        }

        if ($categoria_f !== '') {
            $where[] = "d.categoria = ?";
            $params[] = $categoria_f;
            $types .= "s";
        }

        if ($q !== '') {
            $where[]  = "(d.titulo LIKE ? OR d.descripcion LIKE ? OR d.categoria LIKE ?)";
            $like     = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types   .= "sss";
        }

        $sql = "
            SELECT d.*,
                   e.nombre_persona AS empleado_nombre,
                   e.cargo          AS empleado_cargo
            FROM   documentos d
            LEFT JOIN equipo e ON e.id = d.empleado_id
            WHERE  " . implode(" AND ", $where) . "
            ORDER BY d.estado = 'nuevo' DESC, d.created_at DESC
            LIMIT 500
        ";

        $st = $conn->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $rows = stmt_get_result($st)->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['ok' => true, 'documentos' => $rows]);
        break;

    /* ── Stats (contadores para el sidebar) ── */
    case 'stats':
        $st = $conn->prepare("
            SELECT
              COUNT(*)                                                    AS total,
              SUM(estado = 'nuevo')                                       AS nuevos,
              SUM(estado = 'archivado')                                   AS archivados,
              SUM(tipo = 'pdf')                                           AS pdfs,
              SUM(tipo IN ('drive','microsoft'))                          AS links,
              SUM(empleado_id IS NULL  AND estado != 'archivado')        AS empresa,
              SUM(empleado_id IS NOT NULL AND estado != 'archivado')     AS empleados
            FROM documentos
            WHERE empresa_id = ?
        ");
        $st->bind_param("i", $user_id);
        $st->execute();
        $stats = stmt_get_result($st)->fetch_assoc();

        // Solicitudes (permisos + vacaciones) — check tables exist first
        $tbl_permisos   = $conn->query("SHOW TABLES LIKE 'permisos'")->num_rows   > 0;
        $tbl_vacaciones = $conn->query("SHOW TABLES LIKE 'vacaciones'")->num_rows > 0;

        $p_total = $p_pend = $v_total = $v_pend = 0;
        if ($tbl_permisos) {
            $st2 = $conn->prepare("SELECT COUNT(*) AS t, SUM(estado='pendiente') AS p FROM permisos WHERE usuario_id = ?");
            $st2->bind_param("i", $user_id);
            $st2->execute();
            $r2 = stmt_get_result($st2)->fetch_assoc();
            $p_total = (int)($r2['t'] ?? 0);
            $p_pend  = (int)($r2['p'] ?? 0);
        }
        if ($tbl_vacaciones) {
            $st3 = $conn->prepare("SELECT COUNT(*) AS t, SUM(estado='pendiente') AS p FROM vacaciones WHERE usuario_id = ?");
            $st3->bind_param("i", $user_id);
            $st3->execute();
            $r3 = stmt_get_result($st3)->fetch_assoc();
            $v_total = (int)($r3['t'] ?? 0);
            $v_pend  = (int)($r3['p'] ?? 0);
        }

        $stats['permisos']                = $p_total;
        $stats['vacaciones']              = $v_total;
        $stats['solicitudes']             = $p_total + $v_total;
        $stats['solicitudes_pendientes']  = $p_pend  + $v_pend;

        echo json_encode(['ok' => true, 'stats' => $stats]);
        break;

    /* ── Listar empleados con conteo de docs ── */
    case 'listar_empleados':
        $st = $conn->prepare("
            SELECT e.id,
                   e.nombre_persona  AS nombre,
                   e.cargo,
                   e.area_trabajo,
                   COUNT(d.id)             AS doc_count,
                   SUM(d.estado = 'nuevo') AS docs_nuevos
            FROM   equipo e
            LEFT JOIN documentos d
                   ON d.empleado_id = e.id
                  AND d.empresa_id  = ?
                  AND d.estado     != 'archivado'
            WHERE  e.usuario_id = ?
            GROUP BY e.id, e.nombre_persona, e.cargo, e.area_trabajo
            ORDER BY e.nombre_persona ASC
        ");
        if (!$st) {
            echo json_encode(['ok' => false, 'error' => 'Error al preparar consulta de empleados: ' . $conn->error]);
            break;
        }
        $st->bind_param("ii", $user_id, $user_id);
        if (!$st->execute()) {
            echo json_encode(['ok' => false, 'error' => 'Error al ejecutar consulta de empleados: ' . $st->error]);
            break;
        }
        $empleados = stmt_get_result($st)->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok' => true, 'empleados' => $empleados]);
        break;

    /* ── Listar solicitudes (permisos + vacaciones de empleados) ── */
    case 'listar_solicitudes':
        $subtipo  = $_GET['subtipo']    ?? 'todos'; // todos | permisos | vacaciones
        $emp_f    = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : null;
        $q_sol    = trim($_GET['q'] ?? '');

        $tbl_p = $conn->query("SHOW TABLES LIKE 'permisos'")->num_rows   > 0;
        $tbl_v = $conn->query("SHOW TABLES LIKE 'vacaciones'")->num_rows > 0;

        $solicitudes = [];

        // ── Permisos ──
        if ($tbl_p && ($subtipo === 'todos' || $subtipo === 'permisos')) {
            $wp = ["p.usuario_id = ?"];
            $pp = [$user_id];
            $tp = "i";

            if ($emp_f) {
                $wp[] = "p.persona_id = ?";
                $pp[] = $emp_f;
                $tp .= "i";
            }
            if ($q_sol !== '') {
                $wp[] = "(p.titulo LIKE ? OR e.nombre_persona LIKE ?)";
                $pp[] = "%$q_sol%";
                $pp[] = "%$q_sol%";
                $tp  .= "ss";
            }

            $sql_p = "
                SELECT
                    p.id,
                    'permiso'                                   AS source_tipo,
                    p.persona_id                                AS empleado_id,
                    e.nombre_persona                            AS empleado_nombre,
                    CONCAT('Permiso: ', p.titulo)              AS titulo,
                    p.descripcion,
                    'permiso'                                   AS categoria,
                    p.estado,
                    p.documento_path                            AS archivo,
                    NULL                                        AS url_documento,
                    IF(p.documento_path IS NOT NULL, 'pdf', 'otro') AS tipo,
                    p.created_at
                FROM permisos p
                INNER JOIN equipo e ON e.id = p.persona_id
                WHERE " . implode(" AND ", $wp) . "
                ORDER BY p.created_at DESC
                LIMIT 300
            ";
            $st = $conn->prepare($sql_p);
            $st->bind_param($tp, ...$pp);
            $st->execute();
            $solicitudes = array_merge($solicitudes, stmt_get_result($st)->fetch_all(MYSQLI_ASSOC));
        }

        // ── Vacaciones ──
        if ($tbl_v && ($subtipo === 'todos' || $subtipo === 'vacaciones')) {
            $wv = ["v.usuario_id = ?"];
            $pv = [$user_id];
            $tv = "i";

            if ($emp_f) {
                $wv[] = "v.persona_id = ?";
                $pv[] = $emp_f;
                $tv  .= "i";
            }
            if ($q_sol !== '') {
                $wv[] = "e.nombre_persona LIKE ?";
                $pv[] = "%$q_sol%";
                $tv  .= "s";
            }

            $sql_v = "
                SELECT
                    v.id,
                    'vacacion'                                  AS source_tipo,
                    v.persona_id                                AS empleado_id,
                    e.nombre_persona                            AS empleado_nombre,
                    CONCAT('Vacaciones: ',
                           DATE_FORMAT(v.fecha_inicio_programada,'%d/%m/%Y'),
                           ' → ',
                           DATE_FORMAT(v.fecha_fin_programada,'%d/%m/%Y'))   AS titulo,
                    CONCAT(v.dias_solicitados, ' días laborables',
                           IF(v.motivo IS NOT NULL AND v.motivo != '',
                              CONCAT('. ', v.motivo), ''))      AS descripcion,
                    'vacacion'                                  AS categoria,
                    v.estado,
                    NULL                                        AS archivo,
                    NULL                                        AS url_documento,
                    'otro'                                      AS tipo,
                    v.created_at
                FROM vacaciones v
                INNER JOIN equipo e ON e.id = v.persona_id
                WHERE " . implode(" AND ", $wv) . "
                ORDER BY v.created_at DESC
                LIMIT 300
            ";
            $st = $conn->prepare($sql_v);
            $st->bind_param($tv, ...$pv);
            $st->execute();
            $solicitudes = array_merge($solicitudes, stmt_get_result($st)->fetch_all(MYSQLI_ASSOC));
        }

        // Sort merged results by created_at desc
        usort($solicitudes, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        echo json_encode(['ok' => true, 'solicitudes' => $solicitudes]);
        break;

    /* ── Subir documento (PDF o link) ── */
    case 'subir':
        $titulo      = trim($_POST['titulo']      ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $tipo        = $_POST['tipo']        ?? 'pdf';
        $categoria   = $_POST['categoria']   ?? 'general';
        $empleado_id = !empty($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : null;
        $url_doc     = trim($_POST['url']         ?? $_POST['url_documento'] ?? '');

        if ($titulo === '') {
            echo json_encode(['ok' => false, 'error' => 'El título es obligatorio']);
            break;
        }

        $tipos_validos = ['pdf', 'drive', 'microsoft'];
        $cats_validas  = [
            'contrato','politica','onboarding','formacion','evaluacion',
            'certificado','reglamento','beneficios','comunicado','general',
            'permiso','vacacion',
        ];

        if (!in_array($tipo, $tipos_validos, true)) {
            echo json_encode(['ok' => false, 'error' => 'Tipo inválido']);
            break;
        }

        $nombre_archivo = null;
        $ruta_archivo   = null;

        if ($tipo === 'pdf') {
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo PDF']);
                break;
            }

            $file    = $_FILES['archivo'];
            $mime    = mime_content_type($file['tmp_name']);
            $allowed = ['application/pdf'];

            if (!in_array($mime, $allowed, true)) {
                echo json_encode(['ok' => false, 'error' => 'Solo se permiten archivos PDF']);
                break;
            }

            if ($file['size'] > 20 * 1024 * 1024) {
                echo json_encode(['ok' => false, 'error' => 'El archivo supera 20 MB']);
                break;
            }

            $safe_name      = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $nombre_archivo = $safe_name . '_' . uniqid() . '.pdf';
            $dest           = $upload_base . $nombre_archivo;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['ok' => false, 'error' => 'Error al guardar el archivo']);
                break;
            }

            $ruta_archivo = 'uploads/documentos/' . $nombre_archivo;
            $url_doc      = null;

        } else {
            if (empty($url_doc)) {
                echo json_encode(['ok' => false, 'error' => 'La URL del documento es obligatoria']);
                break;
            }
            if (!filter_var($url_doc, FILTER_VALIDATE_URL)) {
                echo json_encode(['ok' => false, 'error' => 'URL inválida']);
                break;
            }
        }

        $st = $conn->prepare("
            INSERT INTO documentos
              (empresa_id, empleado_id, titulo, descripcion, tipo, url_documento,
               nombre_archivo, ruta_archivo, categoria, estado, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'nuevo', ?)
        ");
        $st->bind_param(
            "iisssssssi",
            $user_id, $empleado_id, $titulo, $descripcion, $tipo,
            $url_doc, $nombre_archivo, $ruta_archivo, $categoria, $user_id
        );
        $st->execute();

        if ($conn->error) {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
            break;
        }

        echo json_encode(['ok' => true, 'id' => $conn->insert_id]);
        break;

    /* ── Marcar como leído ── */
    case 'marcar_leido':
        $doc_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$doc_id || !doc_belongs_to_user($conn, $doc_id, $user_id)) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            break;
        }
        $st = $conn->prepare("UPDATE documentos SET estado = 'leido' WHERE id = ? AND estado = 'nuevo'");
        $st->bind_param("i", $doc_id);
        $st->execute();
        echo json_encode(['ok' => true]);
        break;

    /* ── Archivar / Desarchivar ── */
    case 'archivar':
        $doc_id = (int)($_POST['id'] ?? 0);
        if (!$doc_id || !doc_belongs_to_user($conn, $doc_id, $user_id)) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            break;
        }
        // Accept both 'archivar' (JS frontend: 1=archive, 0=unarchive) and legacy 'desarchivar'
        if (isset($_POST['archivar'])) {
            $nuevo_estado = ((int)$_POST['archivar'] === 1) ? 'archivado' : 'leido';
        } else {
            $desarchivar  = !empty($_POST['desarchivar']);
            $nuevo_estado = $desarchivar ? 'leido' : 'archivado';
        }
        $st = $conn->prepare("UPDATE documentos SET estado = ? WHERE id = ?");
        $st->bind_param("si", $nuevo_estado, $doc_id);
        $st->execute();
        echo json_encode(['ok' => true]);
        break;

    /* ── Eliminar ── */
    case 'eliminar':
        $doc_id = (int)($_POST['id'] ?? 0);
        if (!$doc_id || !doc_belongs_to_user($conn, $doc_id, $user_id)) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            break;
        }

        $st = $conn->prepare("SELECT ruta_archivo FROM documentos WHERE id = ?");
        $st->bind_param("i", $doc_id);
        $st->execute();
        $row = stmt_get_result($st)->fetch_assoc();

        $st2 = $conn->prepare("DELETE FROM documentos WHERE id = ? AND empresa_id = ?");
        $st2->bind_param("ii", $doc_id, $user_id);
        $st2->execute();

        if (!empty($row['ruta_archivo'])) {
            $fp = __DIR__ . '/' . $row['ruta_archivo'];
            if (is_file($fp)) @unlink($fp);
        }

        echo json_encode(['ok' => true]);
        break;

    /* ── Editar metadatos ── */
    case 'editar':
        $doc_id      = (int)($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo']      ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria   = $_POST['categoria']   ?? 'general';
        $empleado_id = !empty($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : null;

        if (!$doc_id || !doc_belongs_to_user($conn, $doc_id, $user_id)) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            break;
        }
        if ($titulo === '') {
            echo json_encode(['ok' => false, 'error' => 'El título es obligatorio']);
            break;
        }

        $st = $conn->prepare("
            UPDATE documentos
            SET titulo = ?, descripcion = ?, categoria = ?, empleado_id = ?
            WHERE id = ? AND empresa_id = ?
        ");
        $st->bind_param("ssssii", $titulo, $descripcion, $categoria, $empleado_id, $doc_id, $user_id);
        $st->execute();
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
        break;
}

$conn->close();
