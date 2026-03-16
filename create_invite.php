<?php
session_start();
require 'config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mailer/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

// Helper para responder errores legibles
function respond($code, $arr) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION['user_id'])) {
  respond(401, ['ok'=>false, 'error'=>'No autenticado (no hay $_SESSION[user_id]). �0�7Mismo dominio? �0�7cookies habilitadas?']);
}

$provider_id = (int)$_SESSION['user_id'];
$role = 'company';

// Email opcional del invitado para envío automático
$email_destino = trim($_POST['email_destino'] ?? $_GET['email_destino'] ?? '');

// Caducidad 7 d��as (ajusta si quieres)
try {
  $tz = new DateTimeZone('Europe/Madrid');
} catch (Throwable $e) {
  $tz = new DateTimeZone('UTC');
}
$expires_at = (new DateTime('+7 days', $tz))->format('Y-m-d H:i:s');

// Generar token
try {
  $token = bin2hex(random_bytes(32));
} catch (Throwable $e) {
  respond(500, ['ok'=>false, 'error'=>'No se pudo generar token seguro', 'extra'=>$e->getMessage()]);
}

// Inserci��n
$sql = "INSERT INTO invites (token, provider_id, role, expires_at) VALUES (?,?,?,?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Prepare fall��','extra'=>$conn->error], JSON_UNESCAPED_UNICODE);
  exit;
}

/* Tipos:
   token        = string  -> 's'
   provider_id  = int     -> 'i'
   role         = string  -> 's'
   expires_at   = string  -> 's'
*/
if (!$stmt->bind_param('siss', $token, $provider_id, $role, $expires_at)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'bind_param fall��','extra'=>$stmt->error], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'execute fall��','extra'=>$stmt->error ?: $conn->error], JSON_UNESCAPED_UNICODE);
  exit;
}
$stmt->close();


// Construcci��n robusta de la URL absoluta a registro.php
$scheme = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
  $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Si tu registro se llama "registro.php" (como me pasaste), usamos ese nombre:
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$register_url = $scheme . '://' . $host . $basePath . '/registro.php?invite=' . $token;

// Si se proporcionó email del invitado, enviar la invitación automáticamente
$email_enviado = false;
if ($email_destino && filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
    // Obtener nombre de empresa del provider
    $q = $conn->prepare("SELECT empresa FROM usuarios WHERE id = ? LIMIT 1");
    $q->bind_param('i', $provider_id);
    $q->execute();
    $prov = stmt_get_result($q)->fetch_assoc();
    $q->close();
    $empresa_nombre = $prov['empresa'] ?? 'Tu empresa';

    $email_enviado = Mailer::sendInvitacion($email_destino, $register_url, $empresa_nombre);
}

respond(200, ['ok' => true, 'url' => $register_url, 'email_enviado' => $email_enviado]);
