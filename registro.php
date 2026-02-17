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
    /* Tipografía de marca */
    @import url("https://use.typekit.net/qrv8fyz.css");

    /* === Design tokens — iguales al dashboard === */
    :root{
      --c-primary:#012133;
      --c-secondary:#184656;
      --c-accent:#EF7F1B;
      --c-soft:#FFF5F0;
      --c-body:#474644;
      --c-bg:#FFFFFF;
      --radius:20px;
      --shadow:0 6px 20px rgba(0,0,0,0.06);
      --ring: 0 0 0 4px color-mix(in srgb, var(--c-accent) 18%, transparent);
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    
body{
  margin:0;
  font-family:"gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
  background:#ffffff;
  color: var(--c-body);
  min-height: 100svh;
  display: block;
  overflow: auto;
  padding: 0;
  scrollbar-gutter: stable both-edges;
}

/* La “tarjeta” general centrada con buen aire */
.auth-shell{
  width:min(1100px, 100%);
  background:#fff;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border:1px solid #f1f1f1;
  overflow:hidden;
  display:grid;
  grid-template-columns: 1.2fr 1fr;
  margin: clamp(16px, 4vh, 56px) auto; /* ← más respiración arriba/abajo */
}


@media (max-width: 940px){
  .auth-shell{ grid-template-columns: 1fr; }
}

    /* ===== Panel marca (left) ===== */
    .brand-pane{
  background: linear-gradient(180deg, #012133 0%, #184656 100%);
  color:#fff;
  padding: clamp(20px, 4vh, 56px);
  display:flex;
  flex-direction:column;
  align-items:center;           /* ← centrado horizontal */
  justify-content:center;       /* ← centrado vertical */
  gap: 18px;
  text-align:center;            /* ← texto centrado */
}

    .brand-logo{
  width:min(360px, 64%);
  max-width:360px;
  border-radius: 16px;
  background:#fff;
  object-fit: contain;
  padding:16px;
  box-shadow: 0 6px 18px rgba(0,0,0,.25);
  margin-bottom: 6px;
}


/* Firma “By valirica.com” sutil e itálica */
.brand-byline{
  font-size: 12px;
  font-style: italic;
  color: rgba(255,255,255,.85);
  letter-spacing:.2px;
  margin-top: -2px;
}


/* Título + copy inspirador */
.brand-title{
  font-size: clamp(22px, 3.2vw, 28px);
  font-weight: 800;
  letter-spacing:-.2px;
  margin: 4px 0 0 0;
  color: #fff;
}

.brand-sub{
  font-size: 14px;
  color: rgba(255,255,255,.92);
  line-height: 1.65;
  max-width: 56ch;
}

    .brand-badge{
      display:inline-flex; align-items:center; gap:8px;
      padding: 8px 12px;
      border-radius: 9999px;
      background: rgba(255,255,255,.12);
      border:1px solid rgba(255,255,255,.22);
      font-size: 12px;
      color:#fff;
      width:max-content;
    }
    .brand-badge::before{content:"•"; opacity:.85}

    /* ===== Formulario (right) ===== */
/* Columna derecha: más aire arriba/abajo para el form */
.form-pane{
  padding: clamp(20px, 4vh, 56px);  /* ← respiración vertical */
  display:flex; flex-direction:column; gap:24px; justify-content:center;
}
.form-head h1{ margin:0 0 2px 0; }
.form-head p{ margin:0; color:#6b6b6b; }


    form{ display:grid; gap:16px; }
    .grid-2{
      display:grid; grid-template-columns: 1fr 1fr; gap:16px;
    }
    @media (max-width: 640px){ .grid-2{ grid-template-columns:1fr; } }
    
    
    @media (max-height: 740px){
  .brand-pane, .form-pane{ padding:16px; }
  .brand-logo{ max-width: 300px; padding: 12px; }
}


    label{
      display:block;
      font-weight:700;
      color: var(--c-secondary);
      font-size: 14px;
      margin-bottom:8px;
    }
    .field{
      display:flex; flex-direction:column;
    }
    .input, .file-trigger{
      width:100%;
      padding: 12px 14px;
      font-size: 16px;
      border:1px solid #e6e6e6;
      border-radius: 12px;
      background:#fff;
      outline: none;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    .input:hover{ border-color:#dcdcdc; }
    .input:focus{ border-color: var(--c-accent); box-shadow: var(--ring); }
    .input:invalid{ border-color:#ffd1b1; }

    /* Contraseña + toggle */
    .input-group{
      position:relative; display:flex; align-items:center;
    }
    .toggle-pass{
      position:absolute; right:10px; top:50%; transform: translateY(-50%);
      border:0; background:transparent; cursor:pointer; padding:6px;
      color:#7b7b7b; border-radius:8px;
    }
    .toggle-pass:hover{ background:#f6f6f6; }

    /* File input custom */
    .file-wrap{
      display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center;
    }
    .file-trigger{
      display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none;
      background: #fff;
    }
    .file-trigger:hover{ border-color:#dcdcdc; }
    .file-trigger:focus-within{ border-color: var(--c-accent); box-shadow: var(--ring); }
    .hidden-input{ position:absolute; left:-9999px; }
    .file-hint{ font-size:12px; color:#7b7b7b; }

    .preview{
      width:44px; height:44px; border-radius:10px; border:1px solid #eee; object-fit:cover; background:#fafafa;
      box-shadow: 0 2px 6px rgba(0,0,0,.06);
    }

    /* Botón */
/* Botón y pequeños ajustes */
.btn{
  display:inline-flex; align-items:center; justify-content:center;
  gap:8px; padding: 12px 16px; border-radius: 14px; font-weight:800;
  font-size: 16px; cursor:pointer; text-decoration:none; border:1px solid rgba(0,0,0,0.06);
  background: var(--c-accent); color:#fff; box-shadow: var(--shadow);
  transition: transform .06s ease, filter .12s ease;
}
.btn:hover{ filter: brightness(.98); }
.btn:active{ transform: translateY(1px); }

    .muted{ font-size:14px; color:#6b6b6b; }
    .muted a{ color: var(--c-secondary); font-weight:800; text-decoration:none; }
    .muted a:hover{ text-decoration:underline; }

    /* Informativos y validaciones */
    .hint{ font-size:12px; color:#7b7b7b; margin-top:6px; }
    .inline-badges{ display:flex; flex-wrap:wrap; gap:8px; }
    .badge{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:9999px; font-size:12px; line-height:1;
      background: var(--c-soft); color: var(--c-secondary); border:1px solid rgba(1,33,51,.08);
    }
    .divider{
      height:1px; width:100%;
      background: linear-gradient(90deg, rgba(1,33,51,0.04) 0%, rgba(1,33,51,0.10) 12%, rgba(1,33,51,0.04) 100%);
      margin: 8px 0;
    }

    /* Accesibilidad focus visibles */
    :focus-visible{ outline: 2px solid color-mix(in srgb, var(--c-accent) 40%, transparent); outline-offset: 3px; }
  </style>
</head>
<body>

  <main class="auth-shell" role="main">
      
      
    <!-- Panel de marca / invitación -->
    <section class="brand-pane" aria-label="Identidad">
  <img
    src="<?php echo htmlspecialchars($branding['logo_src'], ENT_QUOTES, 'UTF-8'); ?>"
    alt="<?php echo htmlspecialchars($branding['brand_name'], ENT_QUOTES, 'UTF-8'); ?>"
    class="brand-logo"
  />
  

  

  <?php
    // Nombre para el mensaje inspirador
    $inviter = !empty($inviteData) ? ($branding['brand_name'] ?? 'Tu aliado') : 'Valírica';
  ?>
  <p class="brand-sub">
    Esta es una invitación de <strong><?php echo htmlspecialchars($inviter, ENT_QUOTES, 'UTF-8'); ?></strong>
    para avanzar en tu <strong>construcción de cultura ideal</strong>, alinear tu equipo
    y activar <strong>decisiones estratégicas</strong> de talento humano basadas en <strong>datos reales</strong>.
  </p>

  <?php if (!empty($inviteData)): ?>
    <span class="brand-badge" title="Registro por invitación">
      Invitación activa
    </span>
  <?php else: ?>
    <span class="brand-badge" title="Registro directo">
      Registro directo • Valírica
    </span>
  <?php endif; ?>
  
  <div class="brand-byline">Developed by valirica.com</div>
</section>


    <!-- Formulario -->
    <section class="form-pane" aria-label="Formulario de registro">
      <div class="form-head">
        <h1>Crear cuenta</h1>
        <p>Completa los datos para continuar a cultura ideal.</p>
      </div>

      <form action="registro.php" method="POST" enctype="multipart/form-data" novalidate>
        <div class="grid-2">
          <div class="field">
            <label for="nombre">Nombre</label>
            <input class="input" id="nombre" name="nombre" type="text" autocomplete="given-name" required>
          </div>
          <div class="field">
            <label for="apellido">Apellido</label>
            <input class="input" id="apellido" name="apellido" type="text" autocomplete="family-name" required>
          </div>
        </div>

        <div class="field">
          <label for="email">Correo electrónico</label>
          <input class="input" id="email" name="email" type="email" inputmode="email" autocomplete="email" required>
        </div>

        <div class="field">
          <label for="empresa">Nombre de la empresa</label>
          <input class="input" id="empresa" name="empresa" type="text" autocomplete="organization" required>
        </div>

        <div class="field">
          <label for="password">Contraseña</label>
          <div class="input-group">
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required>
            <button class="toggle-pass" type="button" aria-label="Mostrar u ocultar contraseña" onclick="togglePass()">
              👁️
            </button>
          </div>
          <p class="hint">Recomendado: mínimo 8 caracteres con combinación de letras y números.</p>
        </div>

        <div class="field">
          <label for="logo">Logo de la empresa</label>
          <div class="file-wrap">
            <label class="file-trigger" for="logo">
              <input class="hidden-input" id="logo" name="logo" type="file" accept="image/*" required>
              <span>Seleccionar archivo…</span>
            </label>
            <img id="preview" class="preview" alt="Vista previa del logo" src="" style="display:none;">
          </div>
          <p class="file-hint">Formatos admitidos: PNG, JPG, SVG. Se mostrará una vista previa.</p>
        </div>

        <!-- Mantener el token de invitación en el POST (si existe) -->
        <?php if (!empty($invite)): ?>
          <input type="hidden" name="invite" value="<?php echo htmlspecialchars($invite, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>

        <div class="inline-badges" aria-hidden="true">
          <span class="badge">Datos necesarios</span>
          <span class="badge">Seguro y privado</span>
        </div>

        <div class="divider" role="presentation"></div>

        <button class="btn" type="submit">Siguiente ➝</button>

        <p class="muted">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
      </form>
    </section>
  </main>

  <script>
    // === Toggle de contraseña (UI) ===
    function togglePass(){
      const el = document.getElementById('password');
      el.type = (el.type === 'password') ? 'text' : 'password';
    }

    // === File preview para logo (no cambia backend) ===
    const inputLogo = document.getElementById('logo');
    const preview   = document.getElementById('preview');
    const trigger   = document.querySelector('.file-trigger');

    inputLogo.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) { preview.style.display='none'; preview.src=''; return; }

      // UX: nombre del archivo en el trigger
      trigger.querySelector('span').textContent = file.name;

      const reader = new FileReader();
      reader.onload = (ev) => {
        preview.src = ev.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });

    // Accesibilidad: activar foco visual en el "trigger" al focusear el input oculto
    inputLogo.addEventListener('focus', () => trigger.style.boxShadow = 'var(--ring)');
    inputLogo.addEventListener('blur',  () => trigger.style.boxShadow = 'none');
  </script>
</body>
</html>
