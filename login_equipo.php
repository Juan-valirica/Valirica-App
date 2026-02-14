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
    $res = $stmt->get_result();

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
  <title>Iniciar sesión | Equipo — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#012133">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Valírica">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="application-name" content="Valírica">

  <!-- PWA Icons -->
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">
  <link rel="icon" type="image/png" sizes="192x192" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">

  <!-- Valírica Design System -->
  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
    /* === Login Equipo Page Specific Styles === */
    /* Nota: Variables CSS, reset, botones y formularios base están en valirica-design-system.css */

    body {
      background: #f4f6f8;
    }

    /* Layout específico de login equipo (grid 460px 1fr) */
    .wrap {
      max-width: 980px;
      margin: var(--space-9) auto;
      display: grid;
      grid-template-columns: 460px 1fr;
      gap: var(--space-7);
      align-items: stretch;
      padding: var(--space-5);
    }

    /* Panel de marca (izquierda) */
    .brand-pane {
      background: linear-gradient(180deg, var(--c-primary), var(--c-secondary));
      border-radius: var(--radius);
      padding: var(--space-9);
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: var(--space-5);
      box-shadow: var(--shadow);
    }

    .brand-logo {
      max-width: 240px;
      background: #fff;
      padding: var(--space-3);
      border-radius: var(--radius);
    }

    /* Panel de formulario (derecha) */
    .form-pane {
      background: var(--c-bg);
      border-radius: var(--radius);
      padding: var(--space-9);
      box-shadow: var(--shadow);
    }

    h1 {
      margin: 0 0 var(--space-2) 0;
      color: var(--c-primary);
      font-size: var(--text-2xl);
    }

    p.lead {
      margin: 0 0 var(--space-3) 0;
      color: var(--c-body);
    }

    /* Form base */
    form {
      display: flex;
      flex-direction: column;
      gap: var(--space-3);
    }

    /* Input específico para esta página (mapea a .form-input del design system) */
    .input {
      padding: var(--space-3) var(--space-4);
      border-radius: var(--radius);
      border: 1px solid #e6e6e6;
      font-size: var(--text-base);
      background: #fff;
      width: 100%;
    }

    .input:focus {
      outline: none;
      box-shadow: 0 0 0 4px rgba(239, 127, 27, 0.12);
      border-color: color-mix(in srgb, var(--c-accent) 35%, #e6e6e6);
    }

    .row {
      display: flex;
      gap: var(--space-2);
      align-items: center;
    }

    /* Botón ghost específico */
    .btn-ghost {
      background: #f1f1f1;
      color: var(--c-body);
      border: 1px solid #e9e9e9;
      padding: var(--space-3) var(--space-3);
      border-radius: var(--radius);
      cursor: pointer;
    }

    /* Tabs específicos para toggle login/crear */
    .tabs {
      display: flex;
      gap: var(--space-2);
      margin-bottom: var(--space-3);
    }

    .tab {
      background: transparent;
      border: 0;
      padding: var(--space-2) var(--space-3);
      border-radius: var(--radius);
      cursor: pointer;
      font-weight: var(--font-bold);
      color: var(--c-body);
      transition: all var(--transition-fast);
    }

    .tab.active {
      background: color-mix(in srgb, var(--c-accent) 10%, #fff);
      color: var(--c-accent);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    /* Notice/alert específico */
    .notice {
      padding: var(--space-3);
      border-radius: var(--radius);
      background: color-mix(in srgb, var(--c-accent) 6%, #fff);
      border: 1px solid rgba(239, 127, 27, 0.12);
      color: #8a3b00;
      font-size: var(--text-sm);
    }

    /* Field container */
    .field {
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
      margin-bottom: var(--space-3);
    }

    /* Helper text */
    .helper {
      font-size: var(--text-xs);
      color: #7a7a7a;
      margin-top: var(--space-1);
    }

    /* Toggle button (mostrar contraseña) */
    .toggle-btn {
      background: transparent;
      border: 0;
      cursor: pointer;
      padding: var(--space-2) var(--space-2);
      border-radius: var(--radius);
      font-size: var(--text-sm);
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    /* Input row con toggle */
    .field .input-row {
      display: flex;
      gap: var(--space-2);
      align-items: center;
    }

    .field .input-row .input {
      flex: 1;
    }

    /* Utilidades de texto */
    .muted {
      font-size: var(--text-sm);
      color: #6b6b6b;
    }

    .small {
      font-size: var(--text-xs);
      color: #777;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .wrap {
        grid-template-columns: 1fr;
        padding: var(--space-3);
      }
      .brand-pane {
        order: 2;
      }
      .btn {
        width: 100%;
        min-width: 0;
      }
    }

    @media (min-width: 769px) {
      .tab {
        padding: var(--space-3) var(--space-4);
        font-size: var(--text-sm);
      }
      .tab.active {
        transform: translateY(-1px);
      }
    }
  
  </style>
</head>
<body>
  <main class="wrap" role="main">
    <aside class="brand-pane" aria-hidden="true">
      <img class="brand-logo" src="/uploads/logo-valirica.png" alt="Valírica">
      <div style="text-align:center; max-width:320px;">
        <h2 style="color:#fff; margin:var(--space-2) 0 0 0;">Valírica — Equipo</h2>
        <p class="small" style="color:rgba(255,255,255,0.9);">Mide y activa la cultura de tu equipo. Si tienes problemas, escribe a soporte.</p>
      </div>
    </aside>

    <section class="form-pane" aria-label="Formulario equipo">
      <div style="margin-bottom:var(--space-2);">
        <h1>Acceso — Equipo</h1>
        <p class="lead">Usa tu correo de trabajo y contraseña.</p>
      </div>

      <?php if (!empty($notice)): ?>
  <div class="notice" role="alert"><?php echo htmlspecialchars($notice); ?></div>
<?php endif; ?>


      <!-- Reemplaza el <form> y script por este bloque -->
<div class="tabs">
  <button id="tab-login" class="tab active" aria-pressed="true">Iniciar sesión</button>
  <button id="tab-create" class="tab" aria-pressed="false">Crear contraseña (primera vez)</button>
</div>

<div id="notice-container" role="status" aria-live="polite" style="margin-top:var(--space-3);"></div>

<form id="team-form" autocomplete="on" novalidate>
  <input type="hidden" name="ajax" value="1" />
  <input type="hidden" name="action" id="action-field" value="login" />

  <div>
    <label for="correo">Correo</label>
    <input class="input" id="correo" name="correo" type="email" required />
  </div>

  <div id="login-fields">
    <label for="password">Contraseña</label>
    <input class="input" id="password" name="password" type="password" autocomplete="current-password" />
  </div>

<div id="create-fields" style="display:none;">
  <div class="field">
    <label for="password_new">Crear contraseña</label>
    <div style="position:relative; display:flex; gap:var(--space-2); align-items:center;">
      <input class="input" id="password_new" name="password_new" type="password" autocomplete="new-password" aria-describedby="helper-pass" />
      <button type="button" id="toggle-pass" aria-label="Mostrar contraseña" style="border:0; background:transparent; cursor:pointer; padding:var(--space-2) var(--space-2); font-size:var(--text-sm);">👁️</button>
    </div>
    <div id="helper-pass" class="helper">Mínimo 8 caracteres. Usa una combinación segura.</div>
  </div>

  <div class="field">
    <label for="password_new2">Repetir contraseña</label>
    <div style="position:relative; display:flex; gap:var(--space-2); align-items:center;">
      <input class="input" id="password_new2" name="password_new2" type="password" autocomplete="new-password" aria-describedby="helper-pass2" />
      <button type="button" id="toggle-pass2" aria-label="Mostrar contraseña repetida" style="border:0; background:transparent; cursor:pointer; padding:var(--space-2) var(--space-2); font-size:var(--text-sm);">👁️</button>
    </div>
    <div id="helper-pass2" class="helper">Repite exactamente la contraseña anterior.</div>
  </div>
</div>



  <div style="display:flex; gap:var(--space-2); margin-top:var(--space-3); align-items:center;">
    <button type="submit" id="btn-submit" class="btn">Continuar</button>
    <button type="button" id="btn-cancel" class="btn-ghost" style="display:none;">Volver</button>
    <div id="spinner" style="display:none; margin-left:var(--space-2);">⏳</div>
  </div>
</form>

<script>
(function(){
  const tabLogin = document.getElementById('tab-login');
  const tabCreate = document.getElementById('tab-create');
  const loginFields = document.getElementById('login-fields');
  const createFields = document.getElementById('create-fields');
  const actionField = document.getElementById('action-field');
  const form = document.getElementById('team-form');
  const btnSubmit = document.getElementById('btn-submit');
  const btnCancel = document.getElementById('btn-cancel');
  const spinner = document.getElementById('spinner');
  const notice = document.getElementById('notice-container');

  function setTab(mode){
    if(mode === 'login'){
      tabLogin.classList.add('active'); tabLogin.setAttribute('aria-pressed','true');
      tabCreate.classList.remove('active'); tabCreate.setAttribute('aria-pressed','false');
      loginFields.style.display = ''; createFields.style.display = 'none';
      actionField.value = 'login';
      btnCancel.style.display = 'none';
      btnSubmit.textContent = 'Ingresar';
    } else {
      tabCreate.classList.add('active'); tabCreate.setAttribute('aria-pressed','true');
      tabLogin.classList.remove('active'); tabLogin.setAttribute('aria-pressed','false');
      loginFields.style.display = 'none'; createFields.style.display = '';
      actionField.value = 'create';
      btnCancel.style.display = '';
      btnSubmit.textContent = 'Crear contraseña';
      // vaciar el input de login para evitar que exista un campo con el mismo name pero vacío
document.getElementById('password').value = '';

    }
    clearNotice();
  }

  tabLogin.addEventListener('click', ()=> setTab('login'));
  tabCreate.addEventListener('click', ()=> setTab('create'));
  btnCancel.addEventListener('click', ()=> setTab('login'));

  function showNotice(msg, ok=true){
    notice.innerHTML = '<div class="notice" role="alert">' + msg + '</div>';
    if(ok) notice.querySelector('.notice').style.borderColor = 'rgba(0,128,0,0.12)';
  }
  function clearNotice(){ notice.innerHTML = ''; }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    clearNotice();

    const formData = new FormData(form);
    const action = formData.get('action');

    // client-side validation
    const correo = formData.get('correo') || '';
    if(!correo || correo.indexOf('@') === -1){
      showNotice('Introduce un correo válido');
      return;
    }
  if(action === 'create'){
  // ahora usamos los nombres nuevos para no confundir con el campo de login
  const p1 = formData.get('password_new') || '';
  const p2 = formData.get('password_new2') || '';
  if(p1.length < 8){ showNotice('La contraseña debe tener al menos 8 caracteres'); return; }
  if(p1 !== p2){ showNotice('Las contraseñas no coinciden'); return; }
  // sobreescribimos los valores que se enviarán con los nombres esperados por el servidor:
  // (opcional) no hace falta, pero para claridad vamos a setear el campo 'password' para compatibilidad server-side
  formData.set('password', p1);
  formData.set('password2', p2);
  // NOTA: fetch usa el FormData que acabamos de modificar
}
 else {
      if((formData.get('password') || '').length === 0){
        showNotice('Introduce tu contraseña'); return;
      }
    }

    // UI: bloquear y mostrar spinner
    btnSubmit.disabled = true;
    spinner.style.display = 'inline-block';

    // envío AJAX
    fetch('login_equipo.php', {
      method: 'POST',
      body: formData,
      headers: {'X-Requested-With':'XMLHttpRequest'}
    }).then(r => r.json())
      .then(j => {
        if(j.ok){
          showNotice(j.msg || 'Operación exitosa. Redirigiendo...', true);
          // pequeña pausa visual luego redirect
          setTimeout(()=> {
            window.location.href = j.redirect || 'dashboard_equipo.php';
          }, 900);
        } else {
          showNotice(j.msg || 'Ocurrió un error. Revisa tus datos.', false);
          btnSubmit.disabled = false;
          spinner.style.display = 'none';
        }
      })
      .catch(err => {
        console.error(err);
        showNotice('Error de conexión. Intenta de nuevo.', false);
        btnSubmit.disabled = false;
        spinner.style.display = 'none';
      });
  });

  // Optional: al cargar, si el correo está prellenado por PHP, podrías cambiar a create o login
  // setTab('login');
})();

// show/hide password toggles (solo para la pestaña Crear)
const togglePass = document.getElementById('toggle-pass');
const togglePass2 = document.getElementById('toggle-pass2');

function toggleInputVisibility(btn, inputId) {
  if (!btn) return;
  btn.addEventListener('click', function(){
    const inp = document.getElementById(inputId);
    if (!inp) return;
    if (inp.type === 'password') {
      inp.type = 'text';
      btn.setAttribute('aria-label','Ocultar contraseña');
    } else {
      inp.type = 'password';
      btn.setAttribute('aria-label','Mostrar contraseña');
    }
  });
}
toggleInputVisibility(togglePass, 'password_new');
toggleInputVisibility(togglePass2, 'password_new2');

// Al cambiar a create, enfocamos el primer campo y hacemos scroll suave si es necesario
function focusCreateFirst() {
  const p = document.getElementById('password_new');
  if (p) { p.focus(); p.scrollIntoView({behavior:'smooth', block:'center'}); }
}

// integra esto dentro de setTab: cuando setTab('create') se llama, ejecuta focusCreateFirst()
// en tu setTab actual ya puse el vaciado del password; ahora añadimos foco:
const originalSetTab = setTab;
setTab = function(mode){
  originalSetTab(mode);
  if (mode === 'create') focusCreateFirst();
};

</script>



    </section>
  </main>

  <!-- Registrar Service Worker para PWA -->
  <script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('/sw.js')
        .then(function(registration) {
          console.log('Service Worker registrado con éxito:', registration.scope);
        })
        .catch(function(error) {
          console.log('Error al registrar Service Worker:', error);
        });
    });
  }
  </script>
</body>
</html>