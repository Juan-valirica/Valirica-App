<?php
/**
 * complaints/detail_api.php — API interna del panel de denuncias
 *
 * Devuelve JSON con el detalle completo de una denuncia (campos descifrados).
 * Consumido por panel.php vía fetch(). CSRF validado por GET param.
 *
 * Acceso: responsible_user_id O company_admin de ese canal.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';

date_default_timezone_set('Europe/Madrid');

header('Content-Type: application/json; charset=UTF-8');

// ── Helpers internos ─────────────────────────────────────────────────────────

function api_fail(string $msg, int $code = 403): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

function safe_decrypt(string $ciphertext): string
{
    if ($ciphertext === '') return '';
    try {
        return complaint_decrypt($ciphertext);
    } catch (Throwable) {
        return '';
    }
}

// ── Autenticación ────────────────────────────────────────────────────────────

if (!isset($_SESSION['user_id'])) {
    api_fail('No autenticado', 401);
}

$user_id    = (int)$_SESSION['user_id'];
$company_id = $user_id;

// ── CSRF (enviado como ?csrf=TOKEN desde el JS) ───────────────────────────────

if (!verifyCsrfToken($_GET['csrf'] ?? '')) {
    api_fail('Token de seguridad inválido', 403);
}

// ── Parámetro id ─────────────────────────────────────────────────────────────

$complaint_id = (int)($_GET['id'] ?? 0);
if ($complaint_id <= 0) {
    api_fail('ID no válido', 400);
}

// ── Verificar acceso al canal ─────────────────────────────────────────────────

$config = get_company_config($conn, $company_id);
if (!$config || !$config['is_active']) {
    api_fail('Canal no activo', 403);
}

$is_responsible = ((int)$config['responsible_user_id'] === $user_id);
$is_admin       = ($user_id === $company_id);

if (!$is_responsible && !$is_admin) {
    api_fail('Acceso denegado', 403);
}

// ── Cargar denuncia ───────────────────────────────────────────────────────────

$stmt = $conn->prepare("
    SELECT c.*, u.nombre AS assigned_name
    FROM complaints c
    LEFT JOIN usuarios u ON u.id = c.assigned_to
    WHERE c.id = ? AND c.company_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $complaint_id, $company_id);
$stmt->execute();
$res = stmt_get_result($stmt);
$stmt->close();

if (!$res || $res->num_rows === 0) {
    api_fail('Denuncia no encontrada', 404);
}

$row = $res->fetch_assoc();

// ── Trazabilidad ─────────────────────────────────────────────────────────────

log_complaint_activity($conn, $complaint_id, 'empresa', $user_id, 'acceso_detalle', null);

// ── Actividad reciente (últimas 10, orden descendente) ────────────────────────

$stmt2 = $conn->prepare("
    SELECT ca.action, ca.notes, ca.created_at, u.nombre AS actor_name
    FROM complaint_activities ca
    LEFT JOIN usuarios u ON u.id = ca.actor_id AND ca.actor_tipo = 'empresa'
    WHERE ca.complaint_id = ?
    ORDER BY ca.created_at DESC
    LIMIT 10
");
$stmt2->bind_param("i", $complaint_id);
$stmt2->execute();
$res_a = stmt_get_result($stmt2);
$stmt2->close();

$activities = [];
while ($a = $res_a->fetch_assoc()) {
    $activities[] = [
        'action'     => $a['action'],
        'notes'      => $a['notes'],
        'created_at' => $a['created_at'],
        'actor_name' => $a['actor_name'],
    ];
}

// ── Descifrar descripción y notas ─────────────────────────────────────────────

$description_plain    = safe_decrypt($row['description'] ?? '');
$internal_notes_plain = !empty($row['internal_notes'])
    ? safe_decrypt($row['internal_notes'])
    : null;

// ── Identidad del denunciante — solo al responsable designado ─────────────────

$reporter_name  = null;
$reporter_email = null;

if ($is_responsible && !(bool)$row['is_anonymous']) {
    if (!empty($row['reporter_encrypted_name'])) {
        $reporter_name = safe_decrypt($row['reporter_encrypted_name']);
    }
    if (!empty($row['reporter_encrypted_email'])) {
        $reporter_email = safe_decrypt($row['reporter_encrypted_email']);
    }
}

// ── Respuesta JSON ────────────────────────────────────────────────────────────

echo json_encode([
    'ok'                   => true,
    'id'                   => (int)$row['id'],
    'reference_code'       => $row['reference_code'],
    'country'              => $row['country'],
    'type'                 => $row['type'],
    'type_label'           => complaint_type_label($row['type']),
    'status'               => $row['status'],
    'priority'             => $row['priority'],
    'is_anonymous'         => (bool)$row['is_anonymous'],
    'reporter_name'        => $reporter_name,
    'reporter_email'       => $reporter_email,
    'created_at'           => $row['created_at'],
    'resolution_deadline'  => $row['resolution_deadline'],
    'receipt_deadline'     => $row['receipt_deadline'],
    'description_plain'    => $description_plain,
    'internal_notes_plain' => $internal_notes_plain,
    'activities'           => $activities,
]);
