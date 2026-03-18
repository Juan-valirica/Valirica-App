<?php
/**
 * complaints/submit.php — Envío público de denuncia
 * Sin autenticación obligatoria. Sesión solo para CSRF.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../mailer/Mailer.php';

date_default_timezone_set('Europe/Madrid');

$is_ajax = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

function json_exit(bool $ok, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

function fail(string $msg, bool $ajax): void
{
    if ($ajax) json_exit(false, $msg);
    $_SESSION['complaint_error'] = $msg;
    header('Location: ../index.php');
    exit;
}

// ─── Solo POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
$csrf = trim($_POST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrf)) {
    fail('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', $is_ajax);
}

// ─── Rate limiting suave: máx 3 denuncias por sesión/IP por hora ──────────────
if (!isset($_SESSION['complaint_rate']) || !is_array($_SESSION['complaint_rate'])) {
    $_SESSION['complaint_rate'] = [];
}
$_rl_now = time();
// Eliminar timestamps con más de 1 hora de antigüedad
$_SESSION['complaint_rate'] = array_values(array_filter(
    $_SESSION['complaint_rate'],
    static fn(int $ts) => ($_rl_now - $ts) < 3600
));
if (count($_SESSION['complaint_rate']) >= 3) {
    fail('Has enviado demasiadas denuncias en la última hora. Por favor, espera antes de intentarlo de nuevo.', $is_ajax);
}

// ─── Política aceptada ────────────────────────────────────────────────────────
if (empty($_POST['policy_accepted'])) {
    fail('Debes aceptar la política del canal de denuncias.', $is_ajax);
}

// ─── Validar empresa ──────────────────────────────────────────────────────────
$company_id = (int)($_POST['company_id'] ?? 0);
if ($company_id <= 0) {
    fail('Empresa no identificada.', $is_ajax);
}

// ─── Obtener config del canal ─────────────────────────────────────────────────
$config = get_company_config($conn, $company_id);
if (!$config || !$config['is_active']) {
    fail('El canal de denuncias no está activo para esta empresa.', $is_ajax);
}

$country = $config['company_country'] ?? 'ES';

// ─── Validar campos principales ───────────────────────────────────────────────
$type        = trim($_POST['type'] ?? '');
$description = trim($_POST['description'] ?? '');
$is_anonymous = (int)(!empty($_POST['is_anonymous']));

$country_cfg   = get_country_config($country);
$allowed_types = $country_cfg['allowed_types'];
if (!in_array($type, $allowed_types, true)) {
    fail('Tipo de denuncia no válido.', $is_ajax);
}
if (strlen($description) < 20) {
    fail('La descripción debe tener al menos 20 caracteres.', $is_ajax);
}

// ─── Datos del denunciante (si no es anónimo) ─────────────────────────────────
$reporter_equipo_id     = null;
$reporter_encrypted_name  = null;
$reporter_encrypted_email = null;

if (!$is_anonymous) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if (empty($name)) {
        fail('Debes indicar tu nombre o marcar la denuncia como anónima.', $is_ajax);
    }
    $reporter_encrypted_name  = complaint_encrypt($name);
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reporter_encrypted_email = complaint_encrypt($email);
    }
    // Vincular a equipo si el empleado está logueado
    if (!empty($_SESSION['empleado_id'])) {
        $eid  = (int)$_SESSION['empleado_id'];
        $stmt = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ? LIMIT 1");
        $stmt->bind_param("ii", $eid, $company_id);
        $stmt->execute();
        $res = stmt_get_result($stmt);
        $stmt->close();
        if ($res && $res->num_rows > 0) {
            $reporter_equipo_id = $eid;
        }
    }
}

// ─── Cifrar descripción ───────────────────────────────────────────────────────
$encrypted_description = complaint_encrypt($description);

// ─── Código de referencia ─────────────────────────────────────────────────────
$reference_code = generate_reference_code($conn, $company_id);

// ─── Prioridad automática por tipo ────────────────────────────────────────────
$priority = 'media';
if (in_array($type, ['acoso_sexual', 'fraude', 'corrupcion'], true)) {
    $priority = 'alta';
}

// ─── Deadlines según país ─────────────────────────────────────────────────────
$receipt_days     = (int)($config['receipt_days'] ?? 7);
$resolution_days  = (int)($config['resolution_days'] ?? 90);

if ($country === 'ES') {
    $receipt_deadline    = get_working_days_deadline($receipt_days, 'ES');
    $resolution_deadline = get_working_days_deadline($resolution_days, 'ES');
    $receipt_sent_at     = null; // se marca cuando se envía el acuse de recibo
} else {
    // Colombia: acuse inmediato
    $receipt_deadline    = date('Y-m-d H:i:s'); // NOW
    $resolution_deadline = get_working_days_deadline($resolution_days, 'CO');
    $receipt_sent_at     = date('Y-m-d H:i:s');
}

// ─── Subir evidencias ─────────────────────────────────────────────────────────
$evidence_paths = [];
if (!empty($_FILES['evidence']['name'][0])) {
    $upload_dir = __DIR__ . '/../uploads/complaints/' . $reference_code . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_mime = [
        'image/jpeg','image/png','image/gif','image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4','video/webm',
    ];

    foreach ($_FILES['evidence']['tmp_name'] as $idx => $tmp) {
        if ($_FILES['evidence']['error'][$idx] !== UPLOAD_ERR_OK) continue;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp);
        if (!in_array($mime, $allowed_mime, true)) continue;
        if ($_FILES['evidence']['size'][$idx] > 20 * 1024 * 1024) continue; // 20 MB

        $ext      = pathinfo($_FILES['evidence']['name'][$idx], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        if (move_uploaded_file($tmp, $upload_dir . $filename)) {
            $evidence_paths[] = 'uploads/complaints/' . $reference_code . '/' . $filename;
        }
    }
}

$evidence_json = !empty($evidence_paths) ? json_encode($evidence_paths) : null;

// ─── Insertar denuncia ────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO complaints
        (company_id, reference_code, country, type, description, is_anonymous,
         reporter_equipo_id, reporter_encrypted_name, reporter_encrypted_email,
         assigned_to, status, priority, receipt_sent_at, receipt_deadline,
         resolution_deadline, evidence_paths)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'recibida', ?, ?, ?, ?, ?)
");

$assigned_to       = $config['responsible_user_id'] ?? null;
$receipt_sent_null = $receipt_sent_at;

$stmt->bind_param(
    "issssiiisssssss",
    $company_id,
    $reference_code,
    $country,
    $type,
    $encrypted_description,
    $is_anonymous,
    $reporter_equipo_id,
    $reporter_encrypted_name,
    $reporter_encrypted_email,
    $assigned_to,
    $priority,
    $receipt_sent_null,
    $receipt_deadline,
    $resolution_deadline,
    $evidence_json
);
// Registrar envío en el contador de rate limit (solo cuando realmente insertamos)
$_SESSION['complaint_rate'][] = time();
$stmt->execute();
$complaint_id = (int)$conn->insert_id;
$stmt->close();

if ($complaint_id <= 0) {
    fail('Error interno al registrar la denuncia. Inténtalo de nuevo.', $is_ajax);
}

// ─── Actividad inicial ────────────────────────────────────────────────────────
log_complaint_activity($conn, $complaint_id, 'sistema', null, 'recibida', 'Denuncia enviada por el canal público.');

// ─── Notificación interna al responsable ─────────────────────────────────────
if (!empty($config['responsible_user_id'])) {
    $resp_id     = (int)$config['responsible_user_id'];
    $type_label  = complaint_type_label($type);
    send_internal_notification(
        $conn,
        $resp_id,
        'denuncia_recibida',
        'Nueva denuncia recibida',
        "Se ha recibido una nueva denuncia ({$type_label}). Código: {$reference_code}",
        $complaint_id
    );
}

// ─── Email al responsable ─────────────────────────────────────────────────────
$bcc_critica = null;
if ($priority === 'critica' || ($priority === 'alta' && in_array($type, ['acoso_sexual','fraude','corrupcion'], true))) {
    $bcc_critica = $destinatario_confidencial; // denuncias@valirica.com
}

$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$manage_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'app.valirica.com') . '/complaints/manage.php';

if (!empty($config['responsible_user_id'])) {
    $stmt = $conn->prepare("SELECT nombre, email FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $config['responsible_user_id']);
    $stmt->execute();
    $res_user = stmt_get_result($stmt);
    $stmt->close();
    if ($res_user && $res_user->num_rows > 0) {
        $resp = $res_user->fetch_assoc();
        Mailer::sendNuevaDenuncia(
            $resp['email'],
            $resp['nombre'],
            $reference_code,
            complaint_type_label($type),
            $country,
            $manage_url,
            $bcc_critica
        );
    }
} elseif (!empty($config['notification_email'])) {
    Mailer::sendNuevaDenuncia(
        $config['notification_email'],
        'Responsable',
        $reference_code,
        complaint_type_label($type),
        $country,
        $manage_url,
        $bcc_critica
    );
}

// ─── Respuesta ────────────────────────────────────────────────────────────────
if ($is_ajax) {
    json_exit(true, 'Denuncia registrada correctamente.', [
        'reference_code' => $reference_code,
    ]);
}

// Redirect con code en sesión para mostrar modal
$_SESSION['complaint_submitted'] = $reference_code;
header('Location: form.php?empresa=' . $company_id . '&submitted=1');
exit;
