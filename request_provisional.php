<?php
// request_provisional.php
// Envía enlace de creación de contraseña usando Google Workspace SMTP Relay + PHPMailer SIN composer.

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'config.php'; // Debe definir $conn (mysqli)

// ====== CARGAR PHPMAILER MANUALMENTE ======
// Asegúrate que esta ruta coincide EXACTAMENTE con lo que subiste:
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';
require __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===== CONFIGURACIÓN GENERAL =====
define('BASE_URL', 'https://app.valirica.com'); // Cambia si tu subdominio es distinto
define('FROM_EMAIL', 'no-reply@valirica.com');
define('FROM_NAME', 'Valírica');

// ===== CONFIGURACIÓN SMTP RELAY GOOGLE WORKSPACE =====
// Tu servidor ya está autorizado por IP, así que NO uses usuario/contraseña.
// NO uses smtp.gmail.com → se debe usar smtp-relay.gmail.com

define('SMTP_HOST', 'smtp-relay.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', false);   // NO autenticación, Google usa IP permitida
// ================================


// ===== VALIDACIONES =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login_equipo.php');
    exit;
}

$correo = trim($_POST['correo'] ?? '');
if ($correo === '') {
    $_SESSION['notice'] = "Ingresa tu correo para solicitar el enlace.";
    header('Location: login_equipo.php');
    exit;
}


// ===== BUSCAR EMPLEADO =====
$sql = "SELECT id, nombre_persona, apellido, clave_acceso 
        FROM equipo 
        WHERE correo = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $correo);
$stmt->execute();
$res = stmt_get_result($stmt);

// Respuesta genérica para evitar enumeración de usuarios
if (!$res || $res->num_rows === 0) {
    $_SESSION['notice'] = "Si la cuenta existe, recibirás un enlace para crear tu contraseña.";
    header('Location: login_equipo.php');
    exit;
}

$emp = $res->fetch_assoc();
$empleado_id = (int)$emp['id'];
$nombre = trim(($emp['nombre_persona'] ?? '') . ' ' . ($emp['apellido'] ?? ''));


// Si YA tiene contraseña → no enviar enlace de primera vez
if (!empty($emp['clave_acceso'])) {
    $_SESSION['notice'] = "Si la cuenta existe, recibirás un correo con instrucciones.";
    header('Location: login_equipo.php');
    exit;
}


// ===== RATE LIMIT (máx 3 tokens por hora) =====
$check = $conn->prepare("SELECT COUNT(*) AS cnt 
                         FROM equipo_password_tokens 
                         WHERE empleado_id = ? 
                           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");

$check->bind_param('i', $empleado_id);
$check->execute();
$countData = stmt_get_result($check)->fetch_assoc();
$check->close();

if (($countData['cnt'] ?? 0) >= 3) {
    $_SESSION['notice'] = "Se han solicitado varios enlaces recientemente. Intenta más tarde.";
    header('Location: login_equipo.php');
    exit;
}


// ===== GENERAR TOKEN =====
$token = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);
$expires_at = date('Y-m-d H:i:s', time() + 3600); // expira en 1 hora
$ip = $_SERVER['REMOTE_ADDR'] ?? null;


// ===== GUARDAR TOKEN =====
$ins = $conn->prepare("
    INSERT INTO equipo_password_tokens (empleado_id, token_hash, expires_at, ip)
    VALUES (?, ?, ?, ?)
");
$ins->bind_param('isss', $empleado_id, $token_hash, $expires_at, $ip);
$ins->execute();
$ins->close();


// ===== GENERAR LINK PARA CREAR CONTRASEÑA =====
$link = BASE_URL . "/set_password.php?token=" . urlencode($token);


// ===== ENVIAR CORREO CON PHPMAILER =====
$mail = new PHPMailer(true);

try {
    // Configuración SMTP Relay
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->Port = SMTP_PORT;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPAuth = SMTP_AUTH;

    // Remitente
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($correo, $nombre);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = 'Crea tu contraseña — Valírica';

    $mail->Body = "
      <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
      <p>Haz clic en el siguiente botón para crear tu contraseña de acceso. Este enlace expirará en 1 hora.</p>
      <p style='text-align:center; margin:30px 0;'>
        <a href='" . htmlspecialchars($link) . "'
           style='background:#EF7F1B; color:white; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:bold;'>
           Crear mi contraseña
        </a>
      </p>
      <p>Si no solicitaste este correo, simplemente ignóralo.</p>
      <hr>
      <small>Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
      " . htmlspecialchars($link) . "</small>
    ";




// --- AÑADE ESTAS LÍNEAS PARA DEBUG ---
// DEBUG TEMPORAL: imprime diálogo SMTP en pantalla (quitar luego)
$mail->SMTPDebug = 2;
$mail->Debugoutput = function($str, $level) { echo nl2br(htmlspecialchars($str)); };



// ------------------------------







    $mail->send();

    $_SESSION['notice'] = "Si la cuenta existe, recibirás un enlace para crear tu contraseña.";
} catch (Exception $e) {
    // Mostrar info de error y salida completa para debug (temporal)
    echo "<h3>DEBUG SMTP</h3>";
    echo "<pre>";
    echo "PHPMailer ErrorInfo: " . htmlspecialchars($mail->ErrorInfo) . "\n\n";
    echo "Exception message: " . htmlspecialchars($e->getMessage()) . "\n\n";
    echo "</pre>";
    // También registramos en error_log por si no ves la salida
    error_log("PHPMailer Debug: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
    exit;
}


header('Location: login_equipo.php');
exit;

