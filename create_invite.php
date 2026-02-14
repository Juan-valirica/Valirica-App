<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Helper para responder errores legibles
function respond($code, $arr) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION['user_id'])) {
  respond(401, ['ok'=>false, 'error'=>'No autenticado (no hay $_SESSION[user_id]). ¿Mismo dominio? ¿cookies habilitadas?']);
}

$provider_id = (int)$_SESSION['user_id'];
$role = 'company';

// Caducidad 7 días (ajusta si quieres)
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

// Inserción
$sql = "INSERT INTO invites (token, provider_id, role, expires_at) VALUES (?,?,?,?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Prepare falló','extra'=>$conn->error], JSON_UNESCAPED_UNICODE);
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
  echo json_encode(['ok'=>false,'error'=>'bind_param falló','extra'=>$stmt->error], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'execute falló','extra'=>$stmt->error ?: $conn->error], JSON_UNESCAPED_UNICODE);
  exit;
}
$stmt->close();


// Construcción robusta de la URL absoluta a registro.php
$scheme = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
  $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Si tu registro se llama "registro.php" (como me pasaste), usamos ese nombre:
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$register_url = $scheme . '://' . $host . $basePath . '/registro.php?invite=' . $token;

respond(200, ['ok'=>true, 'url'=>$register_url]);
