<?php
// set_password.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'config.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$notice = '';
$valid = false;
$empleado_id = null;
$token_db_id = null;

if (!$token) {
    $notice = "Enlace inválido.";
} else {
    $token_hash = hash('sha256', $token);
    $sql = "SELECT id, empleado_id, expires_at, used FROM equipo_password_tokens WHERE token_hash = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $token_hash);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            if ($row['used']) {
                $notice = "Este enlace ya fue utilizado.";
            } elseif (strtotime($row['expires_at']) < time()) {
                $notice = "El enlace ha expirado. Solicita uno nuevo desde el inicio de sesión.";
            } else {
                $valid = true;
                $empleado_id = (int)$row['empleado_id'];
                $token_db_id = (int)$row['id'];
            }
        } else {
            $notice = "Enlace inválido.";
        }
        $stmt->close();
    } else {
        $notice = "Error interno.";
    }
}

// POST para guardar la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = trim($_POST['password'] ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    if (strlen($password) < 8) {
        $notice = "La contraseña debe tener al menos 8 caracteres.";
        $valid = true; // para mostrar el formulario de nuevo
    } elseif ($password !== $password2) {
        $notice = "Las contraseñas no coinciden.";
        $valid = true;
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Actualizar DB en transacción
        $conn->begin_transaction();
        try {
            $upd = $conn->prepare("UPDATE equipo SET clave_acceso = ?, formulario_completado = 1 WHERE id = ?");
            $upd->bind_param('si', $hash, $empleado_id);
            $upd->execute();
            $upd->close();

            $mark = $conn->prepare("UPDATE equipo_password_tokens SET used = 1, used_at = NOW() WHERE id = ?");
            $mark->bind_param('i', $token_db_id);
            $mark->execute();
            $mark->close();

            $conn->commit();

            // Auto-login
            $u = $conn->prepare("SELECT id, correo, nombre_persona, apellido, cargo FROM equipo WHERE id = ? LIMIT 1");
            $u->bind_param('i', $empleado_id);
            $u->execute();
            $userRow = $u->get_result()->fetch_assoc();
            $u->close();

            session_regenerate_id(true);
            $_SESSION['empleado_id'] = $userRow['id'];
            $_SESSION['empleado_correo'] = $userRow['correo'];
            $_SESSION['empleado_nombre'] = $userRow['nombre_persona'];
            $_SESSION['empleado_apellido'] = $userRow['apellido'] ?? '';
            $_SESSION['empleado_cargo'] = $userRow['cargo'] ?? '';

            header('Location: dashboard_equipo.php');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Error saving password: ' . $e->getMessage());
            $notice = "Error al guardar la contraseña. Intenta de nuevo.";
            $valid = true;
        }
    }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear contraseña — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{ font-family: Arial, Helvetica, sans-serif; background:#f6f7fb; color:#333; padding:24px; }
    .box{ max-width:520px; margin:40px auto; background:#fff; padding:24px; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
    h1{ margin:0 0 8px 0; color:#012133; }
    .muted{ color:#666; font-size:14px; margin-bottom:12px; }
    .input{ width:100%; padding:12px; border-radius:8px; border:1px solid #e6e6e6; margin-bottom:10px; }
    .btn{ padding:12px 14px; border-radius:8px; border:0; background:#EF7F1B; color:#fff; font-weight:700; cursor:pointer; }
    .notice{ background:#fff4e8; border:1px solid rgba(239,127,27,0.12); padding:10px; color:#8a3b00; border-radius:8px; margin-bottom:12px; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Crear tu contraseña</h1>
    <p class="muted">Crea una contraseña segura para acceder a tu espacio de equipo.</p>

    <?php if ($notice): ?>
      <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <?php if ($valid): ?>
      <form method="POST" action="set_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <label>Nueva contraseña</label>
        <input class="input" type="password" name="password" minlength="8" required>
        <label>Repetir contraseña</label>
        <input class="input" type="password" name="password2" minlength="8" required>
        <button class="btn" type="submit">Crear contraseña y entrar</button>
      </form>
    <?php else: ?>
      <p>Si el enlace no funciona, solicita uno nuevo desde el inicio de sesión.</p>
      <p><a href="login_equipo.php">Volver al inicio de sesión</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
