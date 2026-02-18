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
   AUTO-MIGRATION: ensure tables exist
   ───────────────────────────────────────────── */
$conn->query("
  CREATE TABLE IF NOT EXISTS documentos (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT          NOT NULL,
    empleado_id   INT          DEFAULT NULL,
    titulo        VARCHAR(255) NOT NULL,
    descripcion   TEXT,
    tipo          ENUM('pdf','drive','microsoft') NOT NULL DEFAULT 'pdf',
    url_documento VARCHAR(2000),
    nombre_archivo VARCHAR(500),
    ruta_archivo  VARCHAR(1000),
    categoria     VARCHAR(100) NOT NULL DEFAULT 'general',
    estado        ENUM('nuevo','leido','archivado') NOT NULL DEFAULT 'nuevo',
    creado_por    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

    /* ── Listar documentos (con filtros) ── */
    case 'listar':
        $empleado_id  = isset($_GET['empleado_id'])  ? (int)$_GET['empleado_id']  : null;
        $tipo         = $_GET['tipo']         ?? '';
        $categoria    = $_GET['categoria']    ?? '';
        $estado       = $_GET['estado']       ?? '';  // '' = todos excepto archivados
        $busqueda     = trim($_GET['busqueda'] ?? '');
        $scope        = $_GET['scope']        ?? 'all'; // 'empresa', 'empleados', 'all'

        $where  = ["d.empresa_id = ?"];
        $params = [$user_id];
        $types  = "i";

        if ($scope === 'empresa') {
            $where[] = "d.empleado_id IS NULL";
        } elseif ($scope === 'empleados') {
            $where[] = "d.empleado_id IS NOT NULL";
        }

        if ($empleado_id !== null) {
            $where[] = "d.empleado_id = ?";
            $params[] = $empleado_id;
            $types .= "i";
        }

        if ($tipo !== '') {
            $where[] = "d.tipo = ?";
            $params[] = $tipo;
            $types .= "s";
        }

        if ($categoria !== '') {
            $where[] = "d.categoria = ?";
            $params[] = $categoria;
            $types .= "s";
        }

        if ($estado === 'archivado') {
            $where[] = "d.estado = 'archivado'";
        } elseif ($estado !== '') {
            $where[] = "d.estado = ?";
            $params[] = $estado;
            $types .= "s";
        } else {
            // Default: exclude archivados
            $where[] = "d.estado != 'archivado'";
        }

        if ($busqueda !== '') {
            $where[]  = "(d.titulo LIKE ? OR d.descripcion LIKE ? OR d.categoria LIKE ?)";
            $like     = "%$busqueda%";
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
        $sql = "
            SELECT
              COUNT(*) AS total,
              SUM(estado = 'nuevo')     AS nuevos,
              SUM(estado = 'archivado') AS archivados,
              SUM(tipo = 'pdf')         AS pdfs,
              SUM(tipo = 'drive')       AS drives,
              SUM(tipo = 'microsoft')   AS microsofts,
              SUM(empleado_id IS NULL AND estado != 'archivado') AS empresa,
              SUM(empleado_id IS NOT NULL AND estado != 'archivado') AS empleados
            FROM documentos
            WHERE empresa_id = ?
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("i", $user_id);
        $st->execute();
        $stats = stmt_get_result($st)->fetch_assoc();
        echo json_encode(['ok' => true, 'stats' => $stats]);
        break;

    /* ── Listar empleados con conteo de docs ── */
    case 'listar_empleados':
        $st = $conn->prepare("
            SELECT e.id, e.nombre_persona, e.cargo, e.area_trabajo,
                   COUNT(d.id) AS total_docs,
                   SUM(d.estado = 'nuevo') AS docs_nuevos
            FROM   equipo e
            LEFT JOIN documentos d ON d.empleado_id = e.id AND d.empresa_id = ?
              AND d.estado != 'archivado'
            WHERE  e.usuario_id = ?
            GROUP BY e.id
            ORDER BY e.nombre_persona ASC
        ");
        $st->bind_param("ii", $user_id, $user_id);
        $st->execute();
        $empleados = stmt_get_result($st)->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok' => true, 'empleados' => $empleados]);
        break;

    /* ── Subir documento (PDF o link) ── */
    case 'subir':
        $titulo      = trim($_POST['titulo']      ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $tipo        = $_POST['tipo']        ?? 'pdf';
        $categoria   = $_POST['categoria']   ?? 'general';
        $empleado_id = !empty($_POST['empleado_id']) ? (int)$_POST['empleado_id'] : null;
        $url_doc     = trim($_POST['url_documento'] ?? '');

        if ($titulo === '') {
            echo json_encode(['ok' => false, 'error' => 'El título es obligatorio']);
            break;
        }

        $tipos_validos      = ['pdf', 'drive', 'microsoft'];
        $categorias_validas = ['contrato', 'politica', 'onboarding', 'formacion', 'evaluacion', 'certificado', 'reglamento', 'beneficios', 'comunicado', 'general'];

        if (!in_array($tipo, $tipos_validos, true)) {
            echo json_encode(['ok' => false, 'error' => 'Tipo inválido']);
            break;
        }

        $nombre_archivo = null;
        $ruta_archivo   = null;

        if ($tipo === 'pdf') {
            /* ── File upload ── */
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo PDF']);
                break;
            }

            $file      = $_FILES['archivo'];
            $mime      = mime_content_type($file['tmp_name']);
            $allowed   = ['application/pdf'];

            if (!in_array($mime, $allowed, true)) {
                echo json_encode(['ok' => false, 'error' => 'Solo se permiten archivos PDF']);
                break;
            }

            if ($file['size'] > 20 * 1024 * 1024) { // 20 MB
                echo json_encode(['ok' => false, 'error' => 'El archivo supera 20 MB']);
                break;
            }

            $ext            = 'pdf';
            $safe_name      = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $nombre_archivo = $safe_name . '_' . uniqid() . '.' . $ext;
            $dest           = $upload_base . $nombre_archivo;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['ok' => false, 'error' => 'Error al guardar el archivo']);
                break;
            }

            $ruta_archivo = 'uploads/documentos/' . $nombre_archivo;
            $url_doc      = null;

        } else {
            /* ── Link (Drive / Microsoft) ── */
            if (empty($url_doc)) {
                echo json_encode(['ok' => false, 'error' => 'La URL del documento es obligatoria']);
                break;
            }

            // Basic URL validation
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
        $doc_id     = (int)($_POST['id'] ?? 0);
        $desarchivar = !empty($_POST['desarchivar']);
        if (!$doc_id || !doc_belongs_to_user($conn, $doc_id, $user_id)) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            break;
        }
        $nuevo_estado = $desarchivar ? 'leido' : 'archivado';
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

        // Get file path before deleting
        $st = $conn->prepare("SELECT ruta_archivo FROM documentos WHERE id = ?");
        $st->bind_param("i", $doc_id);
        $st->execute();
        $row = stmt_get_result($st)->fetch_assoc();

        $st2 = $conn->prepare("DELETE FROM documentos WHERE id = ? AND empresa_id = ?");
        $st2->bind_param("ii", $doc_id, $user_id);
        $st2->execute();

        // Delete physical file if it was a PDF
        if (!empty($row['ruta_archivo'])) {
            $file_path = __DIR__ . '/' . $row['ruta_archivo'];
            if (is_file($file_path)) {
                @unlink($file_path);
            }
        }

        echo json_encode(['ok' => true]);
        break;

    /* ── Editar metadatos ── */
    case 'editar':
        $doc_id      = (int)($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo']      ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoria   = $_POST['categoria'] ?? 'general';
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
