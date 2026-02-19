<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Valírica — Bienvenido</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#011929">
  <meta name="description" content="Plataforma de cultura, equipos y talento.">
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
    }

    /* ─── Full-screen background ─── */
    .vl-bg {
      min-height: 100vh;
      background:
        radial-gradient(ellipse at 75% 5%,  rgba(0,122,150,0.38) 0%, transparent 52%),
        radial-gradient(ellipse at 10% 95%, rgba(239,127,27,0.20) 0%, transparent 50%),
        radial-gradient(ellipse at 90% 85%, rgba(0,122,150,0.14) 0%, transparent 42%),
        linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 70%, #0d3a4f 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 48px;
      position: relative;
      overflow: hidden;
    }

    /* Decorative rings */
    .vl-ring {
      position: absolute;
      border-radius: 50%;
      border: 1px solid rgba(0,122,150,0.15);
      pointer-events: none;
    }
    .vl-ring-1 { width: 560px; height: 560px; top: -200px; right: -160px; }
    .vl-ring-2 { width: 400px; height: 400px; bottom: -140px; left: -120px; border-color: rgba(239,127,27,0.10); }
    .vl-ring-3 { width: 280px; height: 280px; top: 50%; right: -80px; transform: translateY(-50%); border-color: rgba(0,122,150,0.10); }

    /* ─── Logo + brand ─── */
    .vl-logo-wrap {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 18px;
    }
    .vl-logo-wrap::before {
      content: '';
      position: absolute;
      inset: -10px;
      border-radius: 50%;
      background: rgba(0,122,150,0.12);
      border: 1px solid rgba(0,122,150,0.22);
    }
    .vl-logo-wrap img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      position: relative;
      z-index: 1;
      display: block;
    }

    .vl-wordmark {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 3.5px;
      text-transform: uppercase;
      color: rgba(255,255,255,0.38);
      margin-bottom: 12px;
      text-align: center;
    }

    .vl-headline {
      font-size: clamp(26px, 4vw, 36px);
      font-weight: 800;
      color: #fff;
      text-align: center;
      line-height: 1.18;
      letter-spacing: -0.6px;
      margin-bottom: 10px;
    }
    .vl-headline span { color: var(--c-accent); }

    .vl-sub {
      font-size: 15px;
      color: rgba(255,255,255,0.52);
      text-align: center;
      line-height: 1.6;
      max-width: 360px;
      margin-bottom: 44px;
    }

    /* ─── Choice cards ─── */
    .vl-choices {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      width: 100%;
      max-width: 680px;
    }

    .vl-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 22px;
      padding: 32px 28px 28px;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 0;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      transition: transform 0.22s ease, box-shadow 0.22s ease,
                  background 0.22s ease, border-color 0.22s ease;
      position: relative;
      overflow: hidden;
    }

    /* Top accent bar */
    .vl-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      border-radius: 22px 22px 0 0;
      opacity: 0;
      transition: opacity 0.22s ease;
    }
    .vl-card--empresa::before  { background: linear-gradient(90deg, var(--c-teal), #00b0d0); }
    .vl-card--equipo::before   { background: linear-gradient(90deg, var(--c-accent), #f5a23d); }

    .vl-card:hover {
      transform: translateY(-5px);
      background: rgba(255,255,255,0.09);
    }
    .vl-card--empresa:hover {
      border-color: rgba(0,122,150,0.45);
      box-shadow: 0 16px 48px rgba(0,122,150,0.22);
    }
    .vl-card--equipo:hover {
      border-color: rgba(239,127,27,0.45);
      box-shadow: 0 16px 48px rgba(239,127,27,0.20);
    }
    .vl-card:hover::before { opacity: 1; }

    /* Icon bubble */
    .vl-card-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin-bottom: 20px;
      flex-shrink: 0;
    }
    .vl-card--empresa .vl-card-icon {
      background: rgba(0,122,150,0.15);
      color: #4dd6f0;
      border: 1px solid rgba(0,122,150,0.25);
    }
    .vl-card--equipo .vl-card-icon {
      background: rgba(239,127,27,0.13);
      color: #f5a23d;
      border: 1px solid rgba(239,127,27,0.25);
    }

    .vl-card-role {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-bottom: 6px;
    }
    .vl-card--empresa .vl-card-role { color: rgba(77,214,240,0.7); }
    .vl-card--equipo  .vl-card-role { color: rgba(245,162,61,0.7); }

    .vl-card-title {
      font-size: 21px;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
      letter-spacing: -0.3px;
      margin-bottom: 8px;
    }

    .vl-card-desc {
      font-size: 13px;
      color: rgba(255,255,255,0.52);
      line-height: 1.6;
      margin-bottom: 24px;
      flex: 1;
    }

    /* CTA button inside card */
    .vl-card-cta {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 10px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      font-family: var(--font);
      border: none;
      cursor: pointer;
      transition: opacity 0.15s ease, transform 0.12s ease;
      text-decoration: none;
      align-self: stretch;
      justify-content: center;
    }
    .vl-card-cta:hover { opacity: 0.88; transform: scale(0.98); }

    .vl-card--empresa .vl-card-cta {
      background: linear-gradient(135deg, var(--c-teal), #005f74);
      color: #fff;
      box-shadow: 0 4px 14px rgba(0,122,150,0.35);
    }
    .vl-card--equipo .vl-card-cta {
      background: linear-gradient(135deg, var(--c-accent), #d96b0a);
      color: #fff;
      box-shadow: 0 4px 14px rgba(239,127,27,0.35);
    }

    .vl-card-cta i { font-size: 16px; }

    /* ─── Footer ─── */
    .vl-footer {
      margin-top: 40px;
      font-size: 12px;
      color: rgba(255,255,255,0.25);
      text-align: center;
      line-height: 1.7;
    }
    .vl-footer a {
      color: rgba(255,255,255,0.38);
      text-decoration: none;
    }
    .vl-footer a:hover { color: rgba(255,255,255,0.6); }

    /* ─── Mobile ─── */
    @media (max-width: 600px) {
      .vl-choices {
        grid-template-columns: 1fr;
        max-width: 360px;
      }
      .vl-card { padding: 26px 22px 22px; }
      .vl-headline { font-size: 24px; }
      .vl-sub { margin-bottom: 32px; font-size: 14px; }
    }
  </style>
</head>
<body>
<div class="vl-bg">

  <!-- Decorative rings -->
  <span class="vl-ring vl-ring-1"></span>
  <span class="vl-ring vl-ring-2"></span>
  <span class="vl-ring vl-ring-3"></span>

  <!-- Logo -->
  <div class="vl-logo-wrap">
    <img src="https://app.valirica.com/uploads/logo-192.png" alt="Valírica">
  </div>

  <p class="vl-wordmark">Valírica</p>

  <h1 class="vl-headline">¿Cómo deseas <span>ingresar?</span></h1>
  <p class="vl-sub">Selecciona tu perfil para acceder a tu espacio en la plataforma.</p>

  <!-- Choice cards -->
  <div class="vl-choices">

    <!-- Empresa -->
    <a href="login.php" class="vl-card vl-card--empresa">
      <div class="vl-card-icon"><i class="ph ph-buildings"></i></div>
      <p class="vl-card-role">Empresa</p>
      <h2 class="vl-card-title">Soy empresa</h2>
      <p class="vl-card-desc">Gestiona tu equipo, mide la cultura organizacional y toma decisiones basadas en datos.</p>
      <span class="vl-card-cta">
        Ingresar como empresa
        <i class="ph ph-arrow-right"></i>
      </span>
    </a>

    <!-- Equipo -->
    <a href="login_equipo.php" class="vl-card vl-card--equipo">
      <div class="vl-card-icon"><i class="ph ph-user-circle"></i></div>
      <p class="vl-card-role">Miembro del equipo</p>
      <h2 class="vl-card-title">Soy miembro del equipo</h2>
      <p class="vl-card-desc">Accede a tu dashboard personal, revisa tus metas, tareas y gestiona tu jornada.</p>
      <span class="vl-card-cta">
        Ingresar como empleado
        <i class="ph ph-arrow-right"></i>
      </span>
    </a>

  </div>

  <!-- Footer -->
  <p class="vl-footer">
    © <?php echo date('Y'); ?> Valírica · <a href="mailto:soporte@valirica.com">soporte@valirica.com</a>
  </p>

</div>
</body>
</html>
