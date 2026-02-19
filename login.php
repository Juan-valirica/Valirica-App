<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        die("⚠️ Error: Completa todos los campos.");
    }

    $stmt = $conn->prepare("SELECT id, nombre, apellido, empresa, email, password FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // 👇 Reemplazo de get_result()
    $stmt->store_result();

    if ($stmt->num_rows === 1) {

        $stmt->bind_result($id, $nombre, $apellido, $empresa, $email_db, $password_hash);
        $stmt->fetch();

        if (password_verify($password, $password_hash)) {

            $_SESSION['user_id'] = $id;
            $_SESSION['user_email'] = $email_db;
            $_SESSION['user_name'] = $nombre;
            $_SESSION['user_apellido'] = $apellido;
            $_SESSION['empresa'] = $empresa;

            $admin_emails = ["juan@valirica.com", "calderon10b@gmail.com"];
            $_SESSION['is_admin'] = in_array($email_db, $admin_emails);

            header("Location: a-desktop-dashboard-brand.php");
            exit;

        } else {
            die("❌ Error: Contraseña incorrecta.");
        }

    } else {
        die("❌ Error: Usuario no encontrado.");
    }

    $stmt->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Acceso Empresa — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#011929">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="https://app.valirica.com/uploads/logo-192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="https://app.valirica.com/uploads/logo-192.png">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --c-primary:   #012133;
      --c-secondary: #184656;
      --c-teal:      #007a96;
      --c-accent:    #EF7F1B;
      --c-soft:      #FFF5F0;
      --font: "gelica", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    html, body {
      height: 100%;
      font-family: var(--font);
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      color: var(--c-primary);
    }

    /* ── Split layout ── */
    .login-shell {
      display: flex;
      min-height: 100vh;
    }

    /* ══════════════════════════════
       LEFT BRAND PANEL
    ══════════════════════════════ */
    .login-brand {
      width: 44%;
      background:
        radial-gradient(ellipse at 80% 8%,  rgba(0,122,150,0.40) 0%, transparent 52%),
        radial-gradient(ellipse at 8%  92%, rgba(239,127,27,0.16) 0%, transparent 50%),
        radial-gradient(ellipse at 92% 80%, rgba(0,122,150,0.14) 0%, transparent 42%),
        linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 70%, #0d3a4f 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 48px 40px;
      position: relative;
      overflow: hidden;
      flex-shrink: 0;
    }

    .login-brand::before {
      content: '';
      position: absolute;
      width: 480px; height: 480px;
      border-radius: 50%;
      border: 1px solid rgba(0,122,150,0.18);
      top: -160px; right: -160px;
    }
    .login-brand::after {
      content: '';
      position: absolute;
      width: 340px; height: 340px;
      border-radius: 50%;
      border: 1px solid rgba(239,127,27,0.10);
      bottom: -120px; left: -100px;
    }
    .blob-accent { position: absolute; width: 180px; height: 180px; border-radius: 50%; background: rgba(239,127,27,0.05); top: 38%; left: -60px; pointer-events: none; }
    .blob-teal   { position: absolute; width: 140px; height: 140px; border-radius: 50%; background: rgba(0,122,150,0.10); bottom: 22%; right: -40px; pointer-events: none; }

    .brand-logo-wrap {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 28px;
      z-index: 1;
    }
    .brand-logo-wrap::before {
      content: '';
      position: absolute;
      inset: -10px;
      border-radius: 50%;
      background: rgba(0,122,150,0.12);
      border: 1px solid rgba(0,122,150,0.25);
    }
    .brand-logo-wrap img {
      width: 88px; height: 88px;
      border-radius: 50%;
      object-fit: cover;
      position: relative;
      z-index: 1;
      display: block;
    }

    .brand-wordmark {
      font-size: 11px; font-weight: 700;
      letter-spacing: 3.5px; text-transform: uppercase;
      color: rgba(255,255,255,0.38);
      margin-bottom: 12px; text-align: center; z-index: 1;
    }
    .brand-headline {
      font-size: 28px; font-weight: 800;
      color: #fff; text-align: center;
      line-height: 1.2; letter-spacing: -0.5px;
      margin-bottom: 8px; z-index: 1;
    }
    .brand-headline span { color: var(--c-teal); filter: brightness(1.4); }

    .brand-tagline {
      font-size: 14px; color: rgba(255,255,255,0.58);
      text-align: center; line-height: 1.65;
      max-width: 270px; z-index: 1; margin-bottom: 36px;
    }

    .brand-divider {
      width: 36px; height: 3px;
      background: linear-gradient(90deg, var(--c-teal), var(--c-accent));
      border-radius: 2px; margin: 0 auto 24px; z-index: 1;
    }

    .brand-features {
      display: flex; flex-direction: column;
      gap: 8px; z-index: 1;
      width: 100%; max-width: 286px;
    }
    .feature-pill {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 10px; padding: 9px 14px;
      color: rgba(255,255,255,0.82);
      font-size: 13px; font-weight: 500;
      transition: background 0.2s ease, border-color 0.2s ease;
    }
    .feature-pill:hover {
      background: rgba(255,255,255,0.09);
      border-color: rgba(0,122,150,0.3);
    }
    .feature-pill i { color: var(--c-teal); filter: brightness(1.5); font-size: 17px; flex-shrink: 0; }

    /* ══════════════════════════════
       RIGHT FORM PANEL
    ══════════════════════════════ */
    .login-form-panel {
      flex: 1; display: flex;
      align-items: center; justify-content: center;
      padding: 48px 40px;
      background: #fafbfc; overflow-y: auto;
    }

    .login-form-inner { width: 100%; max-width: 400px; }

    .form-eyebrow {
      font-size: 11px; font-weight: 700;
      letter-spacing: 2px; text-transform: uppercase;
      color: var(--c-teal); margin-bottom: 10px;
    }
    .form-title {
      font-size: 30px; font-weight: 800;
      color: var(--c-primary); line-height: 1.15;
      margin-bottom: 6px; letter-spacing: -0.6px;
    }
    .form-subtitle {
      font-size: 14px; color: #6B7280;
      line-height: 1.55; margin-bottom: 32px;
    }

    /* Fields */
    .lf-field { margin-bottom: 16px; }
    .lf-label {
      display: block; font-size: 13px;
      font-weight: 600; color: var(--c-primary); margin-bottom: 6px;
    }
    .lf-input-wrap { position: relative; display: flex; align-items: center; }
    .lf-input-icon {
      position: absolute; left: 13px;
      color: #9CA3AF; font-size: 17px;
      pointer-events: none; z-index: 1;
    }
    .lf-input {
      width: 100%;
      padding: 12px 14px 12px 42px;
      border: 1.5px solid #E2E6EA;
      border-radius: 12px;
      font-size: 14px; font-family: var(--font);
      color: var(--c-primary); background: #fff;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
      outline: none;
    }
    .lf-input:focus {
      border-color: var(--c-teal);
      box-shadow: 0 0 0 3px rgba(0,122,150,0.12);
    }
    .lf-input.has-toggle { padding-right: 44px; }

    .lf-toggle {
      position: absolute; right: 12px;
      background: none; border: none; cursor: pointer;
      color: #9CA3AF; font-size: 17px; padding: 4px;
      display: flex; align-items: center;
      transition: color 0.15s ease; z-index: 1;
    }
    .lf-toggle:hover { color: var(--c-primary); }

    /* Submit — teal for empresa */
    .lf-submit {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, var(--c-teal), #005f74);
      color: #fff; border: none; border-radius: 12px;
      font-size: 15px; font-weight: 700;
      font-family: var(--font); cursor: pointer;
      transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
      margin-top: 8px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      letter-spacing: 0.1px;
      box-shadow: 0 4px 14px rgba(0,122,150,0.35);
    }
    .lf-submit:hover {
      opacity: 0.92;
      box-shadow: 0 8px 24px rgba(0,122,150,0.45);
      transform: translateY(-1px);
    }
    .lf-submit i { font-size: 17px; }

    /* Register link */
    .form-footer {
      margin-top: 22px; text-align: center;
      font-size: 13px; color: #6B7280; line-height: 1.6;
    }
    .form-footer a { color: var(--c-teal); font-weight: 700; text-decoration: none; }
    .form-footer a:hover { text-decoration: underline; }

    /* Back to index link */
    .form-back {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 12px; color: #9CA3AF; text-decoration: none;
      margin-bottom: 28px;
      transition: color 0.15s ease;
    }
    .form-back:hover { color: var(--c-primary); }
    .form-back i { font-size: 14px; }

    /* ── Responsive ── */
    @media (max-width: 820px) {
      .login-shell { flex-direction: column; }
      .login-brand {
        width: 100%; padding: 28px 24px 24px; min-height: auto;
      }
      .brand-features, .brand-divider { display: none; }
      .brand-tagline { margin-bottom: 0; }
      .brand-logo-wrap img { width: 68px; height: 68px; }
      .brand-headline { font-size: 22px; }
      .login-form-panel { padding: 28px 20px 40px; background: #fff; }
    }
  </style>
</head>
<body>
<div class="login-shell">

  <!-- ═══ LEFT: BRAND PANEL ═══ -->
  <aside class="login-brand" aria-hidden="true">
    <span class="blob-accent"></span>
    <span class="blob-teal"></span>

    <div class="brand-logo-wrap">
      <img src="https://app.valirica.com/uploads/logo-192.png" alt="Valírica">
    </div>

    <p class="brand-wordmark">Valírica</p>
    <h2 class="brand-headline">Tu dashboard de<br><span>empresa</span></h2>
    <p class="brand-tagline">Gestiona tu equipo, mide la cultura organizacional y toma decisiones basadas en datos reales.</p>

    <div class="brand-divider"></div>

    <div class="brand-features">
      <div class="feature-pill">
        <i class="ph ph-chart-bar"></i>
        <span>Dashboard de cultura y alineación</span>
      </div>
      <div class="feature-pill">
        <i class="ph ph-users-three"></i>
        <span>Gestión completa del equipo</span>
      </div>
      <div class="feature-pill">
        <i class="ph ph-lightning"></i>
        <span>Análisis de motivación colectiva</span>
      </div>
      <div class="feature-pill">
        <i class="ph ph-trend-up"></i>
        <span>Reportes de desempeño en tiempo real</span>
      </div>
    </div>
  </aside>

  <!-- ═══ RIGHT: FORM PANEL ═══ -->
  <main class="login-form-panel" role="main">
    <div class="login-form-inner">

      <a href="index.php" class="form-back">
        <i class="ph ph-arrow-left"></i>
        Volver al inicio
      </a>

      <p class="form-eyebrow">Acceso Empresa — Valírica</p>
      <h1 class="form-title">Bienvenido de nuevo</h1>
      <p class="form-subtitle">Ingresa con tu correo y contraseña para acceder a tu dashboard.</p>

      <form action="login.php" method="POST" novalidate>

        <!-- Email -->
        <div class="lf-field">
          <label class="lf-label" for="email">Correo electrónico</label>
          <div class="lf-input-wrap">
            <i class="ph ph-envelope lf-input-icon"></i>
            <input class="lf-input" id="email" name="email"
                   type="email" autocomplete="email"
                   placeholder="tu@empresa.com" required>
          </div>
        </div>

        <!-- Password -->
        <div class="lf-field">
          <label class="lf-label" for="password">Contraseña</label>
          <div class="lf-input-wrap">
            <i class="ph ph-lock lf-input-icon"></i>
            <input class="lf-input has-toggle" id="password" name="password"
                   type="password" autocomplete="current-password"
                   placeholder="Tu contraseña" required>
            <button type="button" class="lf-toggle" id="toggle-pass"
                    aria-label="Mostrar contraseña" onclick="togglePass()">
              <i class="ph ph-eye" id="toggle-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="lf-submit">
          Ingresar al dashboard
          <i class="ph ph-arrow-right"></i>
        </button>
      </form>

      <p class="form-footer">
        ¿Aún no tienes cuenta? <a href="registro.php">Crear cuenta</a>
      </p>

    </div>
  </main>
</div>

<script>
  function togglePass() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('toggle-icon');
    const hidden = inp.type === 'password';
    inp.type = hidden ? 'text' : 'password';
    icon.className = hidden ? 'ph ph-eye-slash' : 'ph ph-eye';
    document.getElementById('toggle-pass')
      .setAttribute('aria-label', hidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
  }
</script>
</body>
</html>
