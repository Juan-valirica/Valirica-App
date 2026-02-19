<?php
session_start();
require 'config.php';


// === INVITE: leer token (soporta GET y POST para no perderlo) ===
$invite = $_GET['invite'] ?? ($_POST['invite'] ?? null);
$inviteData = null;

if ($invite) {
    $sql = "SELECT * FROM invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $invite);
    $stmt->execute();
    $inviteData = stmt_get_result($stmt)->fetch_assoc();
    $stmt->close();

    if (!$inviteData) {
        // Token inválido / usado / vencido
        die("❌ El enlace de invitación no es válido o ha caducado.");
    }
}




// === BRANDING: mostrar logo del provider si viene por invitación ===
$branding = [
    'logo_src'   => '/uploads/logo-valirica.png', // fallback Valírica
    'brand_name' => 'Valírica'
];

if ($inviteData && !empty($inviteData['provider_id'])) {
    $provId = (int)$inviteData['provider_id'];
    if ($provId > 0) {
        $q = $conn->prepare("SELECT empresa, logo FROM usuarios WHERE id = ? LIMIT 1");
        $q->bind_param('i', $provId);
        $q->execute();
        $prov = stmt_get_result($q)->fetch_assoc();
        $q->close();

        if ($prov) {
            // Si el provider tiene logo, úsalo; si no, mantenemos el de Valírica
            $logo = trim((string)$prov['logo']);
            if ($logo !== '') {
                // Normaliza a ruta absoluta desde la raíz web
                $branding['logo_src'] = (strpos($logo, '/') === 0) ? $logo : '/'.$logo;
            }
            if (!empty($prov['empresa'])) {
                $branding['brand_name'] = $prov['empresa'];
            }

            // Fallback si el archivo no existe físicamente
            $absPath = $_SERVER['DOCUMENT_ROOT'] . $branding['logo_src'];
            if (!@file_exists($absPath)) {
                $branding['logo_src']   = '/uploads/logo-valirica.png';
                $branding['brand_name'] = 'Valírica';
            }
        }
    }
}




if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"]);
    $apellido = trim($_POST["apellido"]);
    $empresa = trim($_POST["empresa"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($nombre) || empty($apellido) || empty($empresa) || empty($email) || empty($password)) {
        die("❌ Todos los campos son obligatorios.");
    }

    $logo_dir = "uploads/logos/";
    $logo_path = "";

    if (!empty($_FILES["logo"]["name"])) {
        $logo_name = time() . "_" . basename($_FILES["logo"]["name"]);
        $logo_path = $logo_dir . $logo_name;

        if (!move_uploaded_file($_FILES["logo"]["tmp_name"], $logo_path)) {
            die("❌ Error al subir el logo.");
        }
    }

    $password_hashed = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, empresa, email, password, logo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nombre, $apellido, $empresa, $email, $password_hashed, $logo_path);

    if ($stmt->execute()) {

        // === INVITE: si el alta viene por invitación, forzar rol y provider_id por UPDATE ===
if ($inviteData) {
    $newUserId = $stmt->insert_id;
    // rol proviene de invites.role (normalmente 'company'), y provider_id del emisor
    $rol_forzado = $inviteData['role'];               // esperado: 'company'
    $provider_id = (int)$inviteData['provider_id'];

    // 1) actualizar rol y provider_id
    $up = $conn->prepare("UPDATE usuarios SET rol = ?, provider_id = ? WHERE id = ?");
    $up->bind_param('sii', $rol_forzado, $provider_id, $newUserId);
    $up->execute();
    $up->close();

    // 2) marcar invitación como usada
    $up2 = $conn->prepare("UPDATE invites SET used = 1 WHERE id = ?");
    $up2->bind_param('i', $inviteData['id']);
    $up2->execute();
    $up2->close();
}
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $nombre;
        $_SESSION['user_apellido'] = $apellido;
        $_SESSION['empresa'] = $empresa;

header("Location: cultura_ideal.php?usuario_id=" . $_SESSION['user_id']);
        exit;
    } else {
        echo "❌ Error en el registro: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro | Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    @import url("https://use.typekit.net/qrv8fyz.css");

    /* ── Design tokens ── */
    :root {
      --navy:   #012133;
      --teal:   #184656;
      --amber:  #EF7F1B;
      --amber2: #f5962c;
      --soft:   #FFF5EE;
      --body:   #3d3c3b;
      --muted:  #7a7977;
      --border: #e8e6e3;
      --radius: 20px;
      --ring:   0 0 0 3px rgba(239,127,27,.22);
      --shadow: 0 8px 40px rgba(1,33,51,.09);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { height: 100%; }

    body {
      font-family: "gelica", ui-sans-serif, system-ui, -apple-system, sans-serif;
      background: #f0eeeb;
      color: var(--body);
      min-height: 100svh;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: clamp(16px, 3.5vh, 52px) 16px;
    }

    /* ── Card shell ── */
    .shell {
      width: min(1080px, 100%);
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      overflow: hidden;
      display: grid;
      grid-template-columns: 1.1fr 1fr;
    }

    @media (max-width: 860px) {
      .shell { grid-template-columns: 1fr; }
      .brand { display: none; }
    }

    /* ══════════════════════════════════
       BRAND PANEL — izquierda
    ══════════════════════════════════ */
    .brand {
      position: relative;
      background: linear-gradient(155deg, #011b2a 0%, #012133 40%, #0e3547 72%, #184656 100%);
      padding: clamp(36px, 5vh, 68px) clamp(28px, 4.5vw, 60px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 30px;
      overflow: hidden;
    }

    /* Blobs decorativos */
    .brand::before {
      content: "";
      position: absolute;
      width: 480px; height: 480px;
      top: -160px; left: -100px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(239,127,27,.11) 0%, transparent 68%);
      pointer-events: none;
    }
    .brand::after {
      content: "";
      position: absolute;
      width: 380px; height: 380px;
      bottom: -110px; right: -90px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(24,70,86,.5) 0%, transparent 70%);
      pointer-events: none;
    }

    .brand-inner {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      gap: 26px;
    }

    /* Logo card */
    .logo-card {
      width: min(270px, 88%);
      background: #fff;
      border-radius: 18px;
      padding: 16px 22px;
      box-shadow:
        0 0 0 1px rgba(255,255,255,.10),
        0 14px 40px rgba(0,0,0,.30);
    }
    .logo-card img {
      width: 100%;
      height: auto;
      max-height: 84px;
      object-fit: contain;
      display: block;
    }

    /* Bloque de texto */
    .brand-text { display: flex; flex-direction: column; gap: 10px; }

    .brand-eyebrow {
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: 1.6px;
      text-transform: uppercase;
      color: var(--amber);
    }
    .brand-title {
      font-size: clamp(20px, 2.5vw, 26px);
      font-weight: 800;
      color: #fff;
      line-height: 1.22;
      letter-spacing: -.25px;
    }
    .brand-desc {
      font-size: 13.5px;
      color: rgba(255,255,255,.75);
      line-height: 1.72;
    }
    .brand-desc strong { color: rgba(255,255,255,.94); font-weight: 700; }

    /* Feature list */
    .feat-list { display: flex; flex-direction: column; gap: 11px; }
    .feat-item {
      display: flex;
      align-items: center;
      gap: 11px;
      font-size: 13px;
      color: rgba(255,255,255,.85);
    }
    .feat-dot {
      flex-shrink: 0;
      width: 28px; height: 28px;
      border-radius: 9px;
      background: rgba(239,127,27,.16);
      border: 1px solid rgba(239,127,27,.28);
      display: flex; align-items: center; justify-content: center;
    }
    .feat-dot svg { width: 13px; height: 13px; color: var(--amber); }

    /* Badge estado invitación */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 9999px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.16);
      font-size: 12px;
      font-weight: 600;
      color: rgba(255,255,255,.88);
      width: max-content;
    }
    .status-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #4ade80;
      box-shadow: 0 0 0 2px rgba(74,222,128,.22);
      animation: blink 2.2s ease-in-out infinite;
    }
    @keyframes blink { 0%,100%{ opacity:1 } 50%{ opacity:.4 } }

    .byline {
      font-size: 11px;
      color: rgba(255,255,255,.35);
      font-style: italic;
      letter-spacing: .3px;
    }

    /* ══════════════════════════════════
       FORM PANEL — derecha
    ══════════════════════════════════ */
    .form-pane {
      padding: clamp(28px, 4.5vh, 60px) clamp(24px, 4.5vw, 56px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 26px;
    }

    /* Encabezado */
    .form-head { display: flex; flex-direction: column; gap: 5px; }
    .form-head h1 {
      font-size: clamp(22px, 2.8vw, 28px);
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -.3px;
    }
    .form-head p { font-size: 14px; color: var(--muted); }

    /* Form */
    form { display: flex; flex-direction: column; gap: 14px; }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }

    /* Campo */
    .field { display: flex; flex-direction: column; gap: 6px; }

    label {
      font-size: 12.5px;
      font-weight: 700;
      color: var(--teal);
      letter-spacing: .1px;
    }

    /* Input */
    .input {
      width: 100%;
      height: 46px;
      padding: 0 14px;
      font-size: 15px;
      font-family: inherit;
      color: var(--body);
      background: #fafaf8;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      outline: none;
      transition: border-color .15s, box-shadow .15s, background .15s;
    }
    .input::placeholder { color: #c0bebb; }
    .input:hover  { border-color: #d6d3cf; background: #fff; }
    .input:focus  { border-color: var(--amber); box-shadow: var(--ring); background: #fff; }

    /* Password */
    .pw-wrap { position: relative; }
    .pw-wrap .input { padding-right: 46px; }
    .pw-btn {
      position: absolute;
      right: 0; top: 0; bottom: 0; width: 46px;
      display: flex; align-items: center; justify-content: center;
      background: transparent; border: none; cursor: pointer;
      color: var(--muted); border-radius: 0 12px 12px 0;
      transition: color .15s;
    }
    .pw-btn:hover { color: var(--teal); }
    .pw-btn svg { width: 17px; height: 17px; }

    /* Hint */
    .hint { font-size: 11.5px; color: var(--muted); }

    /* File upload zone */
    .file-zone {
      position: relative;
      border: 1.5px dashed #d6d3cf;
      border-radius: 14px;
      background: #fafaf8;
      padding: 16px 14px;
      display: flex;
      align-items: center;
      gap: 14px;
      cursor: pointer;
      transition: border-color .15s, background .15s, box-shadow .15s;
    }
    .file-zone:hover { border-color: var(--amber); background: #fff; }
    .file-zone.active {
      border-style: solid;
      border-color: var(--amber);
      background: #fff9f4;
      box-shadow: var(--ring);
    }
    .file-zone input[type="file"] {
      position: absolute; inset: 0; opacity: 0;
      cursor: pointer; width: 100%; height: 100%;
    }
    .fz-icon {
      flex-shrink: 0;
      width: 42px; height: 42px;
      border-radius: 11px;
      background: var(--soft);
      border: 1px solid rgba(239,127,27,.18);
      display: flex; align-items: center; justify-content: center;
    }
    .fz-icon svg { width: 19px; height: 19px; color: var(--amber); }
    .fz-text { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
    .fz-label {
      font-size: 13.5px; font-weight: 700;
      color: var(--teal);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .fz-hint { font-size: 11.5px; color: var(--muted); }
    .fz-preview {
      width: 44px; height: 44px;
      border-radius: 10px;
      border: 1.5px solid var(--border);
      object-fit: cover;
      background: #f0efed;
      flex-shrink: 0;
      display: none;
    }

    /* Chips informativos */
    .chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .chip {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 5px 11px;
      border-radius: 9999px;
      font-size: 11.5px; font-weight: 600;
      color: var(--teal);
      background: var(--soft);
      border: 1px solid rgba(1,33,51,.07);
    }
    .chip svg { width: 11px; height: 11px; opacity: .75; }

    /* Divisor */
    hr.sep {
      border: none;
      border-top: 1px solid var(--border);
      margin: 2px 0;
    }

    /* Botón submit */
    .btn-submit {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; height: 50px;
      border-radius: 14px; border: none;
      background: linear-gradient(135deg, #EF7F1B 0%, #f5962c 100%);
      color: #fff;
      font-size: 15px; font-weight: 800; font-family: inherit;
      letter-spacing: .15px;
      cursor: pointer;
      box-shadow: 0 4px 18px rgba(239,127,27,.30);
      transition: filter .12s ease, box-shadow .12s ease, transform .07s ease;
    }
    .btn-submit:hover {
      filter: brightness(1.05);
      box-shadow: 0 6px 24px rgba(239,127,27,.38);
    }
    .btn-submit:active { transform: translateY(1px); }
    .btn-submit svg { width: 17px; height: 17px; }

    /* Pie del formulario */
    .form-footer { text-align: center; font-size: 13px; color: var(--muted); }
    .form-footer a { color: var(--teal); font-weight: 700; text-decoration: none; }
    .form-footer a:hover { text-decoration: underline; }

    /* Focus visible accesible */
    :focus-visible { outline: 2px solid rgba(239,127,27,.5); outline-offset: 3px; }
  </style>
</head>
<body>

<main class="shell" role="main">

  <!-- ── Panel de marca ── -->
  <aside class="brand" aria-label="Identidad Valírica">
    <div class="brand-inner">

      <div class="logo-card">
        <img
          src="<?php echo htmlspecialchars($branding['logo_src'], ENT_QUOTES, 'UTF-8'); ?>"
          alt="<?php echo htmlspecialchars($branding['brand_name'], ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <?php
        $inviter = !empty($inviteData) ? ($branding['brand_name'] ?? 'Tu aliado') : 'Valírica';
      ?>

      <div class="brand-text">
        <span class="brand-eyebrow">Bienvenido a Valírica</span>
        <h2 class="brand-title">Construye tu<br>cultura ideal</h2>
        <p class="brand-desc">
          Invitación de <strong><?php echo htmlspecialchars($inviter, ENT_QUOTES, 'UTF-8'); ?></strong>
          para alinear tu equipo y tomar <strong>decisiones estratégicas</strong>
          de talento basadas en <strong>datos reales</strong>.
        </p>
      </div>

      <div class="feat-list">
        <div class="feat-item">
          <span class="feat-dot">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 4L6 11l-3-3"/>
            </svg>
          </span>
          Dashboard de cultura organizacional
        </div>
        <div class="feat-item">
          <span class="feat-dot">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 4L6 11l-3-3"/>
            </svg>
          </span>
          Análisis de valores y talento humano
        </div>
        <div class="feat-item">
          <span class="feat-dot">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 4L6 11l-3-3"/>
            </svg>
          </span>
          Reportes estratégicos en tiempo real
        </div>
      </div>

      <?php if (!empty($inviteData)): ?>
        <div class="status-badge">
          <span class="status-dot"></span>
          Invitación activa
        </div>
      <?php else: ?>
        <div class="status-badge">
          <span class="status-dot"></span>
          Registro directo · Valírica
        </div>
      <?php endif; ?>

      <span class="byline">Powered by valirica.com</span>

    </div>
  </aside>

  <!-- ── Formulario ── -->
  <section class="form-pane" aria-label="Formulario de registro">

    <div class="form-head">
      <h1>Crear cuenta</h1>
      <p>Completa los datos para continuar a tu cultura ideal.</p>
    </div>

    <form action="registro.php" method="POST" enctype="multipart/form-data" novalidate>

      <div class="grid-2">
        <div class="field">
          <label for="nombre">Nombre</label>
          <input class="input" id="nombre" name="nombre" type="text" autocomplete="given-name" placeholder="Ana" required>
        </div>
        <div class="field">
          <label for="apellido">Apellido</label>
          <input class="input" id="apellido" name="apellido" type="text" autocomplete="family-name" placeholder="García" required>
        </div>
      </div>

      <div class="field">
        <label for="email">Correo electrónico</label>
        <input class="input" id="email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="ana@empresa.com" required>
      </div>

      <div class="field">
        <label for="empresa">Nombre de la empresa</label>
        <input class="input" id="empresa" name="empresa" type="text" autocomplete="organization" placeholder="Empresa S.A." required>
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <div class="pw-wrap">
          <input class="input" id="password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required>
          <button type="button" class="pw-btn" id="pwToggle" aria-label="Mostrar u ocultar contraseña">
            <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <span class="hint">Recomendado: mínimo 8 caracteres con letras y números.</span>
      </div>

      <div class="field">
        <label for="logo">Logo de la empresa</label>
        <div class="file-zone" id="fileZone">
          <input type="file" id="logo" name="logo" accept="image/*" required>
          <span class="fz-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="4"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
          </span>
          <div class="fz-text">
            <span class="fz-label" id="fzLabel">Seleccionar logo…</span>
            <span class="fz-hint">PNG, JPG, SVG — vista previa al instante</span>
          </div>
          <img id="fzPreview" class="fz-preview" alt="Vista previa del logo">
        </div>
      </div>

      <!-- Token de invitación (si aplica) -->
      <?php if (!empty($invite)): ?>
        <input type="hidden" name="invite" value="<?php echo htmlspecialchars($invite, ENT_QUOTES, 'UTF-8'); ?>">
      <?php endif; ?>

      <div class="chips" aria-hidden="true">
        <span class="chip">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="7" width="10" height="8" rx="2"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/>
          </svg>
          Datos seguros y privados
        </span>
        <span class="chip">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M13 4L6 11l-3-3"/>
          </svg>
          Campos obligatorios
        </span>
      </div>

      <hr class="sep">

      <button class="btn-submit" type="submit">
        Crear cuenta y continuar
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </button>

      <p class="form-footer">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>

    </form>
  </section>

</main>

<script>
  // ── Toggle contraseña ──
  (function () {
    var pwInput = document.getElementById('password');
    var pwBtn   = document.getElementById('pwToggle');
    var eyeIcon = document.getElementById('eye-icon');

    var EYE_OPEN   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    var EYE_CLOSED = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

    pwBtn.addEventListener('click', function () {
      var show = pwInput.type === 'password';
      pwInput.type      = show ? 'text' : 'password';
      eyeIcon.innerHTML = show ? EYE_CLOSED : EYE_OPEN;
    });
  })();

  // ── Preview logo ──
  (function () {
    var input   = document.getElementById('logo');
    var zone    = document.getElementById('fileZone');
    var label   = document.getElementById('fzLabel');
    var preview = document.getElementById('fzPreview');

    input.addEventListener('change', function () {
      var file = this.files && this.files[0];
      if (!file) {
        label.textContent     = 'Seleccionar logo…';
        preview.style.display = 'none';
        zone.classList.remove('active');
        return;
      }
      label.textContent = file.name;
      zone.classList.add('active');
      var reader = new FileReader();
      reader.onload = function (e) {
        preview.src           = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  })();
</script>
</body>
</html>
