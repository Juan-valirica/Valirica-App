<?php
/**
 * complaints/evidence.php — Servicio autenticado de archivos de evidencia
 *
 * Solo accesible para el responsible_user_id o el admin de la empresa propietaria.
 * Nunca sirve ficheros por URL directa; valida pertenencia y path traversal.
 *
 * Uso:  evidence.php?file=VLD-2025-ABCD/ab12cd34ef56...ext
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';

// ─── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

$user_id = (int)$_SESSION['user_id'];

// ─── Validar parámetro ─────────────────────────────────────────────────────────
$file_param = trim($_GET['file'] ?? '');

// Formato esperado: VLD-YYYY-XXXX/hexhex...ext
// - Código de referencia: VLD-{4 dígitos}-{4 alfanum mayúscula}
// - Nombre de fichero: 32 hex chars + . + extensión 2-5 chars alfanum
if (!preg_match(
    '/^(VLD-\d{4}-[A-Z0-9]{4})\/([a-f0-9]{32}\.[a-z0-9]{2,5})$/',
    $file_param,
    $matches
)) {
    http_response_code(400);
    exit('Parámetro de archivo no válido');
}

$reference_code = $matches[1]; // ej. VLD-2025-A3F1
$filename       = $matches[2]; // ej. abc123...def.pdf

// ─── Buscar denuncia y verificar acceso ────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.id, c.company_id, c.evidence_paths,
           ccc.responsible_user_id
    FROM complaints c
    INNER JOIN complaint_channel_config ccc ON ccc.company_id = c.company_id
    WHERE c.reference_code = ?
    LIMIT 1
");
$stmt->bind_param("s", $reference_code);
$stmt->execute();
$res = stmt_get_result($stmt);
$stmt->close();

if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$complaint      = $res->fetch_assoc();
$company_id     = (int)$complaint['company_id'];
$complaint_id   = (int)$complaint['id'];

// Solo el responsable designado o el admin de la empresa pueden acceder
$is_admin       = ($user_id === $company_id);
$is_responsible = ((int)$complaint['responsible_user_id'] === $user_id);

if (!$is_admin && !$is_responsible) {
    http_response_code(403);
    exit('Acceso denegado');
}

// ─── Verificar que el fichero está registrado en evidence_paths ────────────────
$evidence_paths = json_decode($complaint['evidence_paths'] ?? '[]', true) ?? [];
$expected_path  = 'uploads/complaints/' . $reference_code . '/' . $filename;

if (!in_array($expected_path, $evidence_paths, true)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

// ─── Validar path real (evitar path traversal) ────────────────────────────────
$upload_base = realpath(__DIR__ . '/../uploads/complaints/');
$real_path   = realpath(__DIR__ . '/../' . $expected_path);

if (
    !$real_path ||
    !$upload_base ||
    !str_starts_with($real_path, $upload_base . DIRECTORY_SEPARATOR)
) {
    http_response_code(400);
    exit('Ruta de archivo no válida');
}

if (!is_file($real_path)) {
    http_response_code(404);
    exit('Archivo no encontrado en disco');
}

// ─── Validar MIME type real ────────────────────────────────────────────────────
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($real_path);

$allowed_mime = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'video/mp4',
    'video/webm',
];

if (!in_array($mime, $allowed_mime, true)) {
    http_response_code(403);
    exit('Tipo de archivo no permitido');
}

// ─── Auditoría: registrar la descarga ─────────────────────────────────────────
log_complaint_activity(
    $conn,
    $complaint_id,
    'empresa',
    $user_id,
    'descarga_evidencia',
    basename($real_path)
);

// ─── Servir el archivo ────────────────────────────────────────────────────────
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real_path));
// inline para imágenes/PDF; attachment para docs Word y vídeos
$disposition = in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','application/pdf'], true)
    ? 'inline'
    : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes(basename($real_path)) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache');
header('Pragma: no-cache');

// Desactivar output buffering para ficheros grandes
if (ob_get_level()) {
    ob_end_clean();
}

readfile($real_path);
exit;
