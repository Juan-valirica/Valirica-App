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

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Iniciar sesión | Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Valírica Design System -->
  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
    /* === Login Page Specific Styles === */
    /* Solo estilos únicos de esta página que no están en el design system */

    body {
      min-height: 100svh;
      display: block;
      overflow: auto;
      padding: 0;
      scrollbar-gutter: stable both-edges;
    }

    /* Contenedor auth específico para login/registro */
    .auth-shell {
      width: min(1100px, 100%);
      background: #fff;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--color-gray-100);
      overflow: hidden;
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      margin: clamp(16px, 4vh, 56px) auto;
    }

    @media (max-width: 940px) {
      .auth-shell {
        grid-template-columns: 1fr;
      }
    }

    /* Panel de marca (izquierda) */
    .brand-pane {
      background: linear-gradient(180deg, var(--c-primary) 0%, var(--c-secondary) 100%);
      color: #fff;
      padding: clamp(20px, 4vh, 56px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: var(--space-4);
      text-align: center;
    }

    .brand-logo {
      display: none !important;
      width: min(360px, 64%);
      max-width: 360px;
      border-radius: var(--radius-md);
      background: #fff;
      object-fit: contain;
      padding: var(--space-4);
      box-shadow: var(--shadow-xl);
      margin-bottom: var(--space-2);
    }

    .brand-byline {
      font-size: var(--text-xs);
      font-style: italic;
      color: rgba(255, 255, 255, 0.85);
      letter-spacing: var(--tracking-wide);
      margin-top: calc(-1 * var(--space-1));
    }

    .brand-title {
      font-size: clamp(22px, 3.2vw, 28px);
      font-weight: var(--font-extrabold);
      letter-spacing: var(--tracking-tight);
      margin: var(--space-1) 0 0 0;
      color: #fff;
    }

    .brand-sub {
      font-size: var(--text-sm);
      color: rgba(255, 255, 255, 0.92);
      line-height: var(--leading-relaxed);
      max-width: 56ch;
    }

    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-2) var(--space-3);
      border-radius: var(--radius-full);
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.22);
      font-size: var(--text-xs);
      color: #fff;
      width: max-content;
    }

    .brand-badge::before {
      content: "•";
      opacity: 0.85;
    }

    /* Panel de formulario (derecha) */
    .form-pane {
      padding: clamp(20px, 4vh, 56px);
      display: flex;
      flex-direction: column;
      gap: var(--space-6);
      justify-content: center;
    }

    .form-head h1 {
      margin: 0 0 var(--space-1) 0;
    }

    .form-head p {
      margin: 0;
      color: var(--color-gray-500);
    }

    form {
      display: grid;
      gap: var(--space-4);
    }

    /* Toggle password button específico */
    .toggle-pass {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: 0;
      background: transparent;
      cursor: pointer;
      padding: var(--space-2);
      color: var(--color-gray-500);
      border-radius: var(--radius-sm);
      transition: background var(--transition-fast);
    }

    .toggle-pass:hover {
      background: var(--color-gray-100);
    }

    /* Input group para password con toggle */
    .input-group {
      position: relative;
      display: flex;
      align-items: center;
    }

    /* Divider específico */
    .divider {
      height: 1px;
      width: 100%;
      background: linear-gradient(
        90deg,
        rgba(1, 33, 51, 0.04) 0%,
        rgba(1, 33, 51, 0.1) 12%,
        rgba(1, 33, 51, 0.04) 100%
      );
      margin: var(--space-2) 0;
    }

    /* Inline badges container */
    .inline-badges {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-2);
    }

    /* Text muted con link */
    .muted {
      font-size: var(--text-sm);
      color: var(--color-gray-500);
    }

    .muted a {
      color: var(--c-secondary);
      font-weight: var(--font-extrabold);
      text-decoration: none;
    }

    .muted a:hover {
      text-decoration: underline;
    }

    /* Responsive adjustments */
    @media (max-height: 740px) {
      .brand-pane,
      .form-pane {
        padding: var(--space-4);
      }
      .brand-logo {
        max-width: 300px;
        padding: var(--space-3);
      }
    }
  </style>
</head>
<body>

  <main class="auth-shell" role="main">
    <!-- Panel de marca -->
    <section class="brand-pane" aria-label="Identidad">
      <img
        src="/uploads/logo-valirica.png"
        alt="Valírica"
        class="brand-logo"
      />
      <h2 class="brand-title h2">Bienvenido de nuevo</h2>
      <p class="brand-sub">
        Accede a tu <strong>dashboard</strong> para gestionar cultura, motivación
        y decisiones de talento con <strong>datos reales</strong>.
      </p>
      <span class="brand-badge" title="Acceso seguro">Acceso seguro</span>
      <div class="brand-byline">Developed by valirica.com</div>
    </section>

    <!-- Formulario -->
    <section class="form-pane" aria-label="Formulario de acceso">
      <div class="form-head">
        <h1 class="h2">Iniciar sesión</h1>
        <p>Ingresa tus credenciales para continuar.</p>
      </div>

      <form action="login.php" method="POST" novalidate>
        <div class="form-group">
          <label class="form-label" for="email">Correo electrónico</label>
          <input class="form-input" id="email" name="email" type="email" inputmode="email" autocomplete="email" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Contraseña</label>
          <div class="input-group">
            <input class="form-input" id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="toggle-pass" type="button" aria-label="Mostrar u ocultar contraseña" onclick="togglePass()">
              👁️
            </button>
          </div>
        </div>

        <div class="inline-badges" aria-hidden="true">
          <span class="badge badge-accent">Datos protegidos</span>
          <span class="badge badge-accent">Sesión segura</span>
        </div>

        <div class="divider" role="presentation"></div>

        <button class="btn btn-primary btn-lg btn-full" type="submit">Entrar ➝</button>

        <p class="muted">¿Aún no tienes cuenta? <a href="registro.php">Crear cuenta</a></p>
      </form>
    </section>
  </main>

  <script>
    function togglePass(){
      const el = document.getElementById('password');
      el.type = (el.type === 'password') ? 'text' : 'password';
    }
  </script>
</body>
</html>
