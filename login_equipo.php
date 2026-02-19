<?php
// --- inicio: bloque AJAX/PHP actualizado ---
session_start();
require 'config.php'; // mantiene $conn (mysqli)

// Inicializar $notice para evitar warnings y usar flash desde sesión
$notice = '';
if (!empty($_SESSION['notice'])) {
    $notice = $_SESSION['notice'];
    unset($_SESSION['notice']);
}

// Si quieres debugging temporal (quítalo en prod)
// error_reporting(E_ALL); ini_set('display_errors', 1);

header_remove(); // limpiamos headers para controlar la respuesta

function json_response($ok, $msg = '', $redirect = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok ? true : false, 'msg' => $msg, 'redirect' => $redirect]);
    exit;
}

$AJAX = ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax']) && $_POST['ajax'] === '1'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalizar entradas
$action = $_POST['action'] ?? 'login';
$correo = trim($_POST['correo'] ?? '');

// elegir el origen del password según la acción para evitar ambigüedad
if ($action === 'create') {
    $password = trim($_POST['password_new'] ?? $_POST['password'] ?? '');
    $password2 = trim($_POST['password_new2'] ?? $_POST['password2'] ?? '');
} else {
    $password = trim($_POST['password'] ?? '');
    $password2 = trim($_POST['password2'] ?? '');
}


    // validaciones básicas
    if ($correo === '') {
        if ($AJAX) json_response(false, 'Completa el correo.');
        else { $_SESSION['notice'] = 'Completa el correo.'; header('Location: login_equipo.php'); exit; }
    }

    // buscar usuario
    $sql = "SELECT * FROM equipo WHERE correo = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        if ($AJAX) json_response(false, 'Error interno DB (prepare).');
        else { $_SESSION['notice'] = 'Error interno DB (prepare).'; header('Location: login_equipo.php'); exit; }
    }
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $res = stmt_get_result($stmt);

    if (!$res || $res->num_rows !== 1) {
        if ($AJAX) json_response(false, 'No encontramos un registro con ese correo.');
        else { $_SESSION['notice'] = 'No encontramos un registro con ese correo.'; header('Location: login_equipo.php'); exit; }
    }

    $empleado = $res->fetch_assoc();
    // Aseguramos que el valor venga como string y sin espacios extra
    $stored = isset($empleado['clave_acceso']) ? trim((string)$empleado['clave_acceso']) : '';
    



    
    // Log para debugging - quítalo después de arreglar
error_log("LOGIN DEBUG - correo: {$correo} - stored_len: " . strlen($stored) . " - sample: " . substr($stored,0,12));

// Si la columna tiene un hash demasiado corto, devolvemos un mensaje claro
if (!empty($stored) && strlen($stored) < 40) {
    // Respuesta amigable para AJAX y web
    $msg = "La contraseña guardada en la base de datos parece incompleta (hash truncado). Por favor, pide al administrador que ejecute: ALTER TABLE equipo MODIFY clave_acceso VARCHAR(255); y luego recrea la contraseña para este usuario.";
    if ($AJAX) json_response(false, $msg);
    else { $_SESSION['notice'] = $msg; header('Location: login_equipo.php'); exit; }
}
    

    if ($action === 'create') {
        // Crear contraseña (primera vez)
        if (!empty($stored)) {
            if ($AJAX) json_response(false, 'Ya existe una contraseña para este usuario. Usa Iniciar sesión.');
            else { $_SESSION['notice'] = 'Ya existe una contraseña para este usuario.'; header('Location: login_equipo.php'); exit; }
        }
        if (strlen($password) < 8) {
            if ($AJAX) json_response(false, 'La contraseña debe tener al menos 8 caracteres.');
            else { $_SESSION['notice'] = 'La contraseña debe tener al menos 8 caracteres.'; header('Location: login_equipo.php'); exit; }
        }
        if ($password !== $password2) {
            if ($AJAX) json_response(false, 'Las contraseñas no coinciden.');
            else { $_SESSION['notice'] = 'Las contraseñas no coinciden.'; header('Location: login_equipo.php'); exit; }
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE equipo SET clave_acceso = ?, formulario_completado = 1 WHERE id = ?");
        $upd->bind_param("si", $hash, $empleado['id']);
        if ($upd->execute()) {
           // Autologin
session_regenerate_id(true);
$_SESSION['empleado_id'] = $empleado['id'];
$_SESSION['empleado_correo'] = $empleado['correo'];
$redirect = 'dashboard_equipo.php?id=' . (int)$empleado['id'];
if ($AJAX) json_response(true, 'Contraseña creada correctamente. Redirigiendo...', $redirect);
else header('Location: ' . $redirect);
exit;

        } else {
            if ($AJAX) json_response(false, 'Error al guardar la contraseña. Intenta de nuevo.');
            else { $_SESSION['notice'] = 'Error al guardar la contraseña.'; header('Location: login_equipo.php'); exit; }
        }
    } else {
        // Login normal
        if (empty($stored)) {
            if ($AJAX) json_response(false, 'Primera vez: crea tu contraseña usando la pestaña "Crear contraseña".');
            else { $_SESSION['notice'] = 'Primera vez: crea tu contraseña.'; header('Location: login_equipo.php'); exit; }
        }

        $ok = false;

        // Primero verificación estándar (hash)
        if (!empty($stored) && password_verify($password, $stored)) {
            $ok = true;
        } else {
            // Fallback: si por alguna razón el DB contiene la contraseña en texto plano (no recomendable),
            // lo detectamos comparando strings exactos y rehacemos el hash.
            if (!empty($stored) && hash_equals($stored, $password)) {
                $newhash = password_hash($password, PASSWORD_DEFAULT);
                $upd2 = $conn->prepare("UPDATE equipo SET clave_acceso = ? WHERE id = ?");
                $upd2->bind_param("si", $newhash, $empleado['id']);
                if ($upd2->execute()) $ok = true;
            }
        }

      if ($ok) {
    session_regenerate_id(true);
    $_SESSION['empleado_id'] = $empleado['id'];
    $_SESSION['empleado_correo'] = $empleado['correo'];
    $redirect = 'dashboard_equipo.php?id=' . (int)$empleado['id'];
    if ($AJAX) json_response(true, 'Inicio de sesión correcto.', $redirect);
    else header('Location: ' . $redirect);
    exit;
    } else {
            if ($AJAX) json_response(false, 'Credenciales inválidas.');
            else { $_SESSION['notice'] = 'Credenciales inválidas.'; header('Location: login_equipo.php'); exit; }
        }
    }
}
// --- fin del bloque AJAX/PHP ---
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Acceso Equipo — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#012133">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">
  <link rel="icon" type="image/png" sizes="192x192" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --c-primary:   #012133;
      --c-secondary: #184656;
      --c-teal:      #007a96;
      --c-accent:    #EF7F1B;
      --c-soft:      #FFF5F0;
      --radius:      20px;
    }

    html, body {
      height: 100%;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
      color: var(--c-primary);
      -webkit-font-smoothing: antialiased;
    }

    /* ── Full-height split layout ── */
    .login-shell {
      display: flex;
      min-height: 100vh;
    }

    /* ══════════════════════════════
       LEFT BRAND PANEL
    ══════════════════════════════ */
    .login-brand {
      width: 44%;
      background: linear-gradient(155deg, var(--c-primary) 0%, var(--c-secondary) 55%, #00657e 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 48px 40px;
      position: relative;
      overflow: hidden;
      flex-shrink: 0;
    }

    /* Decorative blobs */
    .login-brand::before {
      content: '';
      position: absolute;
      width: 420px; height: 420px;
      border-radius: 50%;
      background: rgba(0, 122, 150, 0.18);
      top: -120px; right: -120px;
    }
    .login-brand::after {
      content: '';
      position: absolute;
      width: 320px; height: 320px;
      border-radius: 50%;
      background: rgba(239, 127, 27, 0.10);
      bottom: -100px; left: -80px;
    }
    .blob-mid {
      position: absolute;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
    }

    .brand-logo-card {
      background: rgba(255,255,255,0.12);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.22);
      border-radius: 20px;
      padding: 20px 28px;
      margin-bottom: 28px;
      z-index: 1;
    }
    .brand-logo-card img {
      max-width: 160px;
      max-height: 56px;
      display: block;
      object-fit: contain;
    }

    .brand-headline {
      font-size: 26px;
      font-weight: 800;
      color: #fff;
      text-align: center;
      line-height: 1.25;
      z-index: 1;
      margin-bottom: 10px;
      letter-spacing: -0.3px;
    }
    .brand-tagline {
      font-size: 14px;
      color: rgba(255,255,255,0.72);
      text-align: center;
      line-height: 1.65;
      max-width: 280px;
      z-index: 1;
      margin-bottom: 36px;
    }

    .brand-features {
      display: flex;
      flex-direction: column;
      gap: 10px;
      z-index: 1;
      width: 100%;
      max-width: 290px;
    }
    .feature-pill {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 12px;
      padding: 10px 14px;
      color: rgba(255,255,255,0.88);
      font-size: 13px;
      font-weight: 500;
    }
    .feature-pill i {
      color: var(--c-accent);
      font-size: 18px;
      flex-shrink: 0;
    }

    /* ══════════════════════════════
       RIGHT FORM PANEL
    ══════════════════════════════ */
    .login-form-panel {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      background: #fff;
      overflow-y: auto;
    }

    .login-form-inner {
      width: 100%;
      max-width: 400px;
    }

    .form-eyebrow {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      color: var(--c-teal);
      margin-bottom: 8px;
    }
    .form-title {
      font-size: 28px;
      font-weight: 800;
      color: var(--c-primary);
      line-height: 1.2;
      margin-bottom: 6px;
      letter-spacing: -0.4px;
    }
    .form-subtitle {
      font-size: 14px;
      color: #6B7280;
      line-height: 1.55;
      margin-bottom: 28px;
    }

    /* Mode switcher tabs */
    .mode-tabs {
      display: flex;
      background: #f1f3f5;
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 24px;
      gap: 4px;
    }
    .mode-tab {
      flex: 1;
      padding: 8px 10px;
      border: none;
      border-radius: 9px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      color: #6B7280;
      background: transparent;
      transition: all 0.2s ease;
      font-family: inherit;
      text-align: center;
      line-height: 1.3;
    }
    .mode-tab.active {
      background: #fff;
      color: var(--c-primary);
      box-shadow: 0 2px 8px rgba(1,33,51,0.12);
    }

    /* Notice */
    .form-notice {
      padding: 11px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 18px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      animation: noticeIn 0.2s ease;
    }
    @keyframes noticeIn {
      from { transform: translateY(-6px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }
    .form-notice.error {
      background: rgba(239,68,68,0.07);
      border: 1px solid rgba(239,68,68,0.18);
      color: #991B1B;
    }
    .form-notice.success {
      background: rgba(0,122,150,0.07);
      border: 1px solid rgba(0,122,150,0.2);
      color: var(--c-secondary);
    }
    .form-notice i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }

    /* Fields */
    .lf-field { margin-bottom: 14px; }

    .lf-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--c-primary);
      margin-bottom: 6px;
    }

    .lf-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .lf-input-icon {
      position: absolute;
      left: 13px;
      color: #9CA3AF;
      font-size: 17px;
      pointer-events: none;
      z-index: 1;
    }
    .lf-input {
      width: 100%;
      padding: 11px 14px 11px 40px;
      border: 1.5px solid #E5E7EB;
      border-radius: 12px;
      font-size: 14px;
      font-family: inherit;
      color: var(--c-primary);
      background: #fff;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
      outline: none;
    }
    .lf-input:focus {
      border-color: var(--c-teal);
      box-shadow: 0 0 0 3px rgba(0,122,150,0.12);
    }
    .lf-input.has-toggle { padding-right: 44px; }

    .lf-toggle {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      color: #9CA3AF;
      font-size: 17px;
      padding: 4px;
      display: flex;
      align-items: center;
      transition: color 0.15s ease;
      z-index: 1;
    }
    .lf-toggle:hover { color: var(--c-primary); }

    .lf-helper {
      font-size: 12px;
      color: #6B7280;
      margin-top: 5px;
      line-height: 1.4;
    }

    /* Password strength */
    .pass-strength { display: flex; gap: 4px; margin-top: 6px; }
    .ps-bar {
      flex: 1; height: 3px; border-radius: 2px;
      background: #E5E7EB;
      transition: background 0.3s ease;
    }
    .ps-bar.weak   { background: #EF4444; }
    .ps-bar.medium { background: #F59E0B; }
    .ps-bar.strong { background: #10B981; }

    /* Submit */
    .lf-submit {
      width: 100%;
      padding: 13px;
      background: var(--c-primary);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
      margin-top: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .lf-submit:hover:not(:disabled) {
      background: var(--c-secondary);
      box-shadow: 0 6px 20px rgba(1,33,51,0.22);
      transform: translateY(-1px);
    }
    .lf-submit:disabled { opacity: 0.6; cursor: not-allowed; }

    .lf-spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.35);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.65s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .lf-btn-secondary {
      width: 100%;
      margin-top: 10px;
      padding: 11px;
      background: transparent;
      color: #6B7280;
      border: 1.5px solid #E5E7EB;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: all 0.15s ease;
      display: none;
    }
    .lf-btn-secondary:hover {
      background: #F9FAFB;
      color: var(--c-primary);
      border-color: #D1D5DB;
    }

    /* Footer */
    .form-footer {
      margin-top: 28px;
      text-align: center;
      font-size: 12px;
      color: #9CA3AF;
      line-height: 1.6;
    }
    .form-footer a { color: var(--c-teal); text-decoration: none; }
    .form-footer a:hover { text-decoration: underline; }

    /* Divider */
    .lf-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 18px 0;
      color: #D1D5DB;
      font-size: 12px;
    }
    .lf-divider::before, .lf-divider::after {
      content: ''; flex: 1;
      border-top: 1px solid #E5E7EB;
    }

    /* ── Responsive ── */
    @media (max-width: 820px) {
      .login-shell { flex-direction: column; }
      .login-brand {
        width: 100%;
        padding: 32px 24px 28px;
        min-height: auto;
      }
      .brand-features { display: none; }
      .brand-tagline { margin-bottom: 0; }
      .login-form-panel { padding: 32px 20px 40px; }
    }
  </style>
</head>
<body>
<div class="login-shell">

  <!-- ═══ LEFT: BRAND PANEL ═══ -->
  <aside class="login-brand" aria-hidden="true">
    <span class="blob-mid"></span>

    <div class="brand-logo-card">
      <img src="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png" alt="Valírica">
    </div>

    <h2 class="brand-headline">Portal de Equipo</h2>
    <p class="brand-tagline">Accede a tu espacio personal, revisa tus metas y gestiona tu día a día en la organización.</p>

    <div class="brand-features">
      <div class="feature-pill">
        <i class="ph ph-target"></i>
        <span>Metas y objetivos en tiempo real</span>
      </div>
      <div class="feature-pill">
        <i class="ph ph-kanban"></i>
        <span>Tareas y proyectos del equipo</span>
      </div>
      <div class="feature-pill">
        <i class="ph ph-calendar-check"></i>
        <span>Permisos y vacaciones simplificados</span>
      </div>
      <div class="feature-pill">
        <i class="ph ph-files"></i>
        <span>Documentos siempre disponibles</span>
      </div>
    </div>
  </aside>

  <!-- ═══ RIGHT: FORM PANEL ═══ -->
  <main class="login-form-panel" role="main">
    <div class="login-form-inner">

      <p class="form-eyebrow">Portal de Equipo</p>
      <h1 class="form-title">Bienvenido de nuevo</h1>
      <p class="form-subtitle">Ingresa con tu correo corporativo y contraseña.</p>

      <!-- Mode tabs -->
      <div class="mode-tabs">
        <button id="tab-login"  class="mode-tab active" type="button">Iniciar sesión</button>
        <button id="tab-create" class="mode-tab"        type="button">Primera vez</button>
      </div>

      <!-- Notice (PHP flash + AJAX) -->
      <?php if (!empty($notice)): ?>
      <div class="form-notice error" role="alert">
        <i class="ph ph-warning-circle"></i>
        <span><?php echo htmlspecialchars($notice); ?></span>
      </div>
      <?php endif; ?>
      <div id="notice-container" role="status" aria-live="polite"></div>

      <!-- ═══ FORM ═══ -->
      <form id="team-form" autocomplete="on" novalidate>
        <input type="hidden" name="ajax"   value="1" />
        <input type="hidden" name="action" id="action-field" value="login" />

        <!-- Correo -->
        <div class="lf-field">
          <label class="lf-label" for="correo">Correo corporativo</label>
          <div class="lf-input-wrap">
            <i class="ph ph-envelope lf-input-icon"></i>
            <input class="lf-input" id="correo" name="correo"
                   type="email" autocomplete="email"
                   placeholder="tu@empresa.com" required />
          </div>
        </div>

        <!-- Login: password -->
        <div id="login-fields">
          <div class="lf-field">
            <label class="lf-label" for="password">Contraseña</label>
            <div class="lf-input-wrap">
              <i class="ph ph-lock lf-input-icon"></i>
              <input class="lf-input has-toggle" id="password" name="password"
                     type="password" autocomplete="current-password"
                     placeholder="Tu contraseña" />
              <button type="button" class="lf-toggle" id="toggle-pass"
                      aria-label="Mostrar contraseña">
                <i class="ph ph-eye" id="toggle-pass-icon"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Create: new password + repeat -->
        <div id="create-fields" style="display:none;">
          <div class="lf-field">
            <label class="lf-label" for="password_new">Crear contraseña</label>
            <div class="lf-input-wrap">
              <i class="ph ph-lock lf-input-icon"></i>
              <input class="lf-input has-toggle" id="password_new" name="password_new"
                     type="password" autocomplete="new-password"
                     placeholder="Mínimo 8 caracteres"
                     aria-describedby="helper-pass" />
              <button type="button" class="lf-toggle" id="toggle-pass"
                      aria-label="Mostrar contraseña">
                <i class="ph ph-eye" id="toggle-pass-icon"></i>
              </button>
            </div>
            <div class="pass-strength" id="pass-strength" aria-hidden="true">
              <div class="ps-bar" id="ps1"></div>
              <div class="ps-bar" id="ps2"></div>
              <div class="ps-bar" id="ps3"></div>
            </div>
            <p class="lf-helper" id="helper-pass">Mínimo 8 caracteres. Combina letras, números y símbolos.</p>
          </div>

          <div class="lf-field">
            <label class="lf-label" for="password_new2">Repetir contraseña</label>
            <div class="lf-input-wrap">
              <i class="ph ph-lock-key lf-input-icon"></i>
              <input class="lf-input has-toggle" id="password_new2" name="password_new2"
                     type="password" autocomplete="new-password"
                     placeholder="Repite la contraseña"
                     aria-describedby="helper-pass2" />
              <button type="button" class="lf-toggle" id="toggle-pass2"
                      aria-label="Mostrar contraseña repetida">
                <i class="ph ph-eye" id="toggle-pass2-icon"></i>
              </button>
            </div>
            <p class="lf-helper" id="helper-pass2">Repite exactamente la contraseña anterior.</p>
          </div>
        </div>

        <!-- Submit -->
        <button type="submit" id="btn-submit" class="lf-submit">
          <span id="btn-text">Ingresar</span>
          <span class="lf-spinner" id="spinner"></span>
        </button>
        <button type="button" id="btn-cancel" class="lf-btn-secondary">
          Volver al inicio de sesión
        </button>
      </form>

      <div class="form-footer">
        ¿Problemas para acceder?
        <a href="mailto:soporte@valirica.com">Contacta a soporte</a>
      </div>

    </div>
  </main>
</div>

<script>
(function () {
  'use strict';

  /* ── DOM refs ── */
  const tabLogin      = document.getElementById('tab-login');
  const tabCreate     = document.getElementById('tab-create');
  const loginFields   = document.getElementById('login-fields');
  const createFields  = document.getElementById('create-fields');
  const actionField   = document.getElementById('action-field');
  const form          = document.getElementById('team-form');
  const btnSubmit     = document.getElementById('btn-submit');
  const btnText       = document.getElementById('btn-text');
  const btnCancel     = document.getElementById('btn-cancel');
  const spinner       = document.getElementById('spinner');
  const noticeBox     = document.getElementById('notice-container');

  /* ── Tab switcher ── */
  function setTab(mode) {
    const isLogin = mode === 'login';
    tabLogin.classList.toggle('active', isLogin);
    tabCreate.classList.toggle('active', !isLogin);
    tabLogin.setAttribute('aria-pressed', isLogin);
    tabCreate.setAttribute('aria-pressed', !isLogin);
    loginFields.style.display  = isLogin ? '' : 'none';
    createFields.style.display = isLogin ? 'none' : '';
    actionField.value = isLogin ? 'login' : 'create';
    btnText.textContent = isLogin ? 'Ingresar' : 'Crear contraseña';
    btnCancel.style.display = isLogin ? 'none' : '';
    if (isLogin) document.getElementById('password').value = '';
    clearNotice();
    if (!isLogin) {
      const p = document.getElementById('password_new');
      if (p) setTimeout(() => p.focus(), 50);
    }
  }

  tabLogin.addEventListener('click',  () => setTab('login'));
  tabCreate.addEventListener('click', () => setTab('create'));
  btnCancel.addEventListener('click', () => setTab('login'));

  /* ── Notice ── */
  function showNotice(msg, type) {
    const icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
    noticeBox.innerHTML = `
      <div class="form-notice ${type}" role="alert">
        <i class="ph ${icon}"></i>
        <span>${msg}</span>
      </div>`;
  }
  function clearNotice() { noticeBox.innerHTML = ''; }

  /* ── Password strength ── */
  const passInput = document.getElementById('password_new');
  if (passInput) {
    passInput.addEventListener('input', function () {
      const v = this.value;
      let score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v) || /[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v) && v.length >= 10) score++;
      const bars  = ['ps1','ps2','ps3'];
      const cls   = ['weak','medium','strong'];
      bars.forEach((id, i) => {
        const el = document.getElementById(id);
        el.className = 'ps-bar' + (i < score ? ' ' + cls[score - 1] : '');
      });
    });
  }

  /* ── Toggle password visibility ── */
  function bindToggle(btnId, inputId, iconId) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      const inp  = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      if (!inp) return;
      const hidden = inp.type === 'password';
      inp.type = hidden ? 'text' : 'password';
      if (icon) icon.className = hidden ? 'ph ph-eye-slash' : 'ph ph-eye';
      btn.setAttribute('aria-label', hidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
  }
  bindToggle('toggle-pass',  'password',      'toggle-pass-icon');
  bindToggle('toggle-pass',  'password_new',  'toggle-pass-icon');   // reused id in create tab
  bindToggle('toggle-pass2', 'password_new2', 'toggle-pass2-icon');

  /* ── Form submit (AJAX — no backend changes) ── */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearNotice();

    const fd     = new FormData(form);
    const action = fd.get('action');
    const correo = (fd.get('correo') || '').trim();

    /* Client-side validation */
    if (!correo || !correo.includes('@')) {
      showNotice('Introduce un correo válido.', 'error'); return;
    }

    if (action === 'create') {
      const p1 = fd.get('password_new') || '';
      const p2 = fd.get('password_new2') || '';
      if (p1.length < 8) { showNotice('La contraseña debe tener al menos 8 caracteres.', 'error'); return; }
      if (p1 !== p2)     { showNotice('Las contraseñas no coinciden.', 'error'); return; }
      fd.set('password',  p1);
      fd.set('password2', p2);
    } else {
      if (!(fd.get('password') || '').length) {
        showNotice('Introduce tu contraseña.', 'error'); return;
      }
    }

    /* Lock UI */
    btnSubmit.disabled   = true;
    spinner.style.display = 'inline-block';
    btnText.textContent   = action === 'create' ? 'Creando...' : 'Verificando...';

    fetch('login_equipo.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(j => {
        if (j.ok) {
          showNotice(j.msg || 'Acceso correcto. Redirigiendo...', 'success');
          setTimeout(() => {
            window.location.href = j.redirect || 'dashboard_equipo.php';
          }, 800);
        } else {
          showNotice(j.msg || 'Ocurrió un error. Revisa tus datos.', 'error');
          btnSubmit.disabled    = false;
          spinner.style.display = 'none';
          btnText.textContent   = action === 'create' ? 'Crear contraseña' : 'Ingresar';
        }
      })
      .catch(() => {
        showNotice('Error de conexión. Intenta de nuevo.', 'error');
        btnSubmit.disabled    = false;
        spinner.style.display = 'none';
        btnText.textContent   = action === 'create' ? 'Crear contraseña' : 'Ingresar';
      });
  });

})();
</script>

<!-- PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
  });
}
</script>
</body>
</html>
