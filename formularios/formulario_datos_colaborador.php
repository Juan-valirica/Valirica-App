<?php
// formulario_datos_colaborador.php — co-branding company + provider
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php'; // ajusta si tu ruta cambia

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/* === 1) DECLARAR FUNCIONES PRIMERO === */

// SVG placeholder (data URI) para cuando no hay logo o está roto
function fallback_svg_data_uri(){
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="120" viewBox="0 0 320 120">
    <rect width="100%" height="100%" rx="12" fill="#ffffff"/>
    <rect x="16" y="16" width="288" height="88" rx="10" fill="#f3f6f8" stroke="#e0e6ea"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif" font-size="16" fill="#8093a1">Logo no disponible</text>
  </svg>';
  return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Normaliza la ruta del logo para que apunte a /uploads si hace falta
function resolve_logo_src($raw){
  $p = trim((string)($raw ?? ''));
  if ($p === '') return '';

  // Absoluta u origen raíz
  if (preg_match('#^https?://#i', $p)) return $p;
  if ($p[0] === '/') return $p;

  // Evitar /uploads/uploads
  $p_no_dot = ltrim($p, './');
  if (stripos($p_no_dot, 'uploads/') === 0) {
    return '/' . $p_no_dot; // "/uploads/archivo.png"
  }

  // Solo nombre → /uploads/archivo.png
  return '/uploads/' . ltrim($p_no_dot, '/');
}

// Verifica que el recurso exista físicamente; si no, devuelve un SVG de fallback
function safe_logo_src($raw){
  $src = resolve_logo_src($raw);
  if ($src === '') return fallback_svg_data_uri();

  if ($src[0] === '/') {
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $abs1 = $docroot . $src;
    if (is_file($abs1)) return $src;

    // Caso subdominio: probar carpeta padre del vhost
    $abs_up_one = dirname($docroot) . $src;
    if (is_file($abs_up_one)) return $src;
  }
  return fallback_svg_data_uri();
}

/* === 2) AHORA VARIABLES E INPUTS === */

$usuario_id_colaborador = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

$usuario_id_empresa = 0;
$empresa = "";
$areas = [];
$company_logo = "";
$provider_logo = "";
$rol_empresa = "";
$provider_id = null;

// Inicializa _src después de TENER las funciones disponibles
$company_logo_src  = fallback_svg_data_uri(); // placeholder por defecto
$provider_logo_src = '';                      // si no existe provider, no mostramos

/* === 3) QUERIES === */
if ($usuario_id_colaborador) {
  // Empresa (en tu modelo, usuarios.id = company)
  $sql = "SELECT empresa, id, rol, provider_id, logo FROM usuarios WHERE id = ? LIMIT 1";
  $queryEmpresa = $conn->prepare($sql);
  $queryEmpresa->bind_param("i", $usuario_id_colaborador);
  $queryEmpresa->execute();
  $result = $queryEmpresa->get_result();

  if ($fila = $result->fetch_assoc()) {
    $empresa            = $fila['empresa'] ?? "";
    $usuario_id_empresa = (int)($fila['id'] ?? 0);
    $rol_empresa        = (string)($fila['rol'] ?? "");
    $provider_id        = $fila['provider_id'] ?? null;
    $company_logo       = (string)($fila['logo'] ?? "");
  }
  $queryEmpresa->close();

  // Provider (si aplica)
  if ($rol_empresa === 'company' && !empty($provider_id)) {
    $sqlProv = "SELECT logo FROM usuarios WHERE id = ? LIMIT 1";
    $queryProvider = $conn->prepare($sqlProv);
    $queryProvider->bind_param("i", $provider_id);
    $queryProvider->execute();
    $resProv = $queryProvider->get_result();
    if ($prov = $resProv->fetch_assoc()) {
      $provider_logo = (string)($prov['logo'] ?? "");
    }
    $queryProvider->close();
  }

  // Normaliza logos YA cargados
  $company_logo_src  = safe_logo_src($company_logo);
  $provider_logo_src = safe_logo_src($provider_logo);

  // Áreas
  $queryAreas = $conn->prepare("SELECT nombre_area FROM areas_trabajo WHERE usuario_id = ?");
  $queryAreas->bind_param("i", $usuario_id_empresa);
  $queryAreas->execute();
  $resultAreas = $queryAreas->get_result();
  while ($row = $resultAreas->fetch_assoc()) {
    if (!empty($row['nombre_area'])) { $areas[] = $row['nombre_area']; }
  }
  $queryAreas->close();
  
}




?>



<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro de Colaborador | Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* Tipografía de marca */
    @import url("https://use.typekit.net/qrv8fyz.css");

    /* === Design tokens — mismos del login === */
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

    /* Contenedor principal (idéntico al login) */
    .auth-shell{
      width:min(1100px, 100%);
      background:#fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border:1px solid #f1f1f1;
      overflow:hidden;
      display:grid;
      grid-template-columns: 1.2fr 1fr;
      margin: clamp(16px, 4vh, 56px) auto;
    }
    @media (max-width: 940px){
      .auth-shell{ grid-template-columns: 1fr; }
    }

    /* Panel marca (izquierda) */
    .brand-pane{
      background: linear-gradient(180deg, #012133 0%, #184656 100%);
      color:#fff;
      padding: clamp(20px, 4vh, 56px);
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap: 18px;
      text-align:center;
    }
    .brand-logo{ display: none !important; } /* no se usa */

    .brand-byline{
      font-size: 12px;
      font-style: italic;
      color: rgba(255,255,255,.85);
      letter-spacing:.2px;
      margin-top: -2px;
    }
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

    /* ——— Co-branding (Company + Provider) ——— */
    .brand-logos{
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap:16px;
      padding:20px;
      background:rgba(255,255,255,0.06);
      border-radius:16px;
      margin-bottom:12px;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
    }
    .brand-logos img{
      max-width:220px;
      max-height:120px;
      object-fit:contain;
      background:#fff;
      border-radius:12px;
      padding:10px 14px;
      box-shadow:0 4px 12px rgba(0,0,0,0.08);
    }
    .brand-logos small{
      font-size:12px;
      color:rgba(255,255,255,0.85);
      letter-spacing:.2px;
    }
    .brand-provider{
      opacity:.9;
      max-height:80px;
      transform:scale(.95);
    }
    .brand-logos img:hover{
      transform:scale(1.02);
      transition:.2s ease;
    }

    /* Columna derecha — formulario */
    .form-pane{
      padding: clamp(20px, 4vh, 56px);
      display:flex; flex-direction:column; gap:24px; justify-content:center;
    }
    .form-head h1{ margin:0 0 2px 0; }
    .form-head p{ margin:0; color:#6b6b6b; }

    form{ display:grid; gap:16px; }
    .field{ display:flex; flex-direction:column; }
    label{
      display:block;
      font-weight:700;
      color: var(--c-secondary);
      font-size: 14px;
      margin-bottom:8px;
    }
    .input, select.input{
      width:100%;
      padding: 12px 14px;
      font-size: 16px;
      border:1px solid #e6e6e6;
      border-radius: 12px;
      background:#fff;
      outline: none;
      transition: border-color .15s ease, box-shadow .15s ease;
      appearance: none;
    }
    .input:hover{ border-color:#dcdcdc; }
    .input:focus{ border-color: var(--c-accent); box-shadow: var(--ring); }
    .input:invalid{ border-color:#ffd1b1; }

    .inline-badges{ display:flex; flex-wrap:wrap; gap:8px; }
    .badge{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:9999px; font-size:12px; line-height:1;
      background: var(--c-soft); color: var(--c-secondary); border:1px solid rgba(1,33,51,.08);
    }

    .alert{
      padding:10px 12px;
      border-radius:12px;
      background: #FFF5F0;
      border:1px solid rgba(239,127,27,.2);
      color:#7a3a12;
      font-size:14px;
    }

    .divider{
      height:1px; width:100%;
      background: linear-gradient(90deg, rgba(1,33,51,0.04) 0%, rgba(1,33,51,0.10) 12%, rgba(1,33,51,0.04) 100%);
      margin: 8px 0;
    }

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
    .muted strong{ color: var(--c-secondary); }

    :focus-visible{ outline: 2px solid color-mix(in srgb, var(--c-accent) 40%, transparent); outline-offset: 3px; }

    @media (max-height: 740px){
      .brand-pane, .form-pane{ padding:16px; }
    }
    
    .form-head strong {
  color: var(--c-accent);
  font-weight: 800;
}



.field small.muted{
  display:block;
  margin-top:6px;
  line-height:1.4;
}
    
    
  </style>
</head>
<body>

  <main class="auth-shell" role="main">
    <!-- Panel de marca -->
    <section class="brand-pane" aria-label="Identidad">
      <!-- Bloque Co-branding -->
      
      
    <div class="brand-logos">
  <img src="<?php echo h($company_logo_src); ?>" alt="Logo de la empresa" />
  <?php if (!empty($provider_logo_src) && strpos($provider_logo_src, 'data:image/svg+xml') !== 0): ?>
    <small>en colaboración con</small>
    <img src="<?php echo h($provider_logo_src); ?>" alt="Logo del provider" class="brand-provider" />
  <?php endif; ?>
</div>




      <h2 class="brand-title">Te damos la bienvenida</h2>
      <p class="brand-sub">
  Antes de iniciar el <strong>formulario de autoconocimiento</strong>, necesitamos
  unos datos básicos para conectar tu perfil con 
  <strong><?php echo h($empresa ?: 'tu empresa'); ?></strong>.
</p>

      <span class="brand-badge" title="Proceso guiado">Proceso guiado</span>
      <div class="brand-byline">Developed by valirica.com</div>
    </section>

    <!-- Formulario -->
    <section class="form-pane" aria-label="Formulario de registro de colaborador">
      <div class="form-head">
  <h1>¡Queremos conocerte!</h1>
  <p>
    Completa el form y conectemos tu perfil con 
    <strong><?php echo h($empresa ?: 'tu empresa'); ?></strong>.
  </p>
</div>


      <form method="POST" action="procesar_colaborador.php" novalidate>
        <input type="hidden" name="usuario_id" value="<?php echo $usuario_id_colaborador ? (int)$usuario_id_colaborador : ''; ?>">
        <input type="hidden" name="empresa" value="<?php echo h($empresa); ?>">
        <input type="hidden" name="relacion_autoridad" value="pendiente">

        <div class="inline-badges" aria-hidden="true">
          <span class="badge">Datos seguros</span>
          <span class="badge">1–2 minutos</span>
        </div>

        <div class="field">
          <label for="nombre_persona">Nombre</label>
          <input class="input" id="nombre_persona" name="nombre_persona" type="text" required>
        </div>

        <div class="field">
          <label for="apellido">Apellido</label>
          <input class="input" id="apellido" name="apellido" type="text" required>
        </div>

        <div class="field">
          <label for="fecha_nacimiento">Fecha de nacimiento</label>
          <input
            class="input"
            id="fecha_nacimiento"
            name="fecha_nacimiento"
            type="date"
          >
          
        </div>



        <div class="field">
          <label for="correo">Correo electrónico</label>
          <input class="input" id="correo" name="correo" type="email" inputmode="email" autocomplete="email" required>
        </div>

        <div class="field">
          <label for="cargo">Cargo</label>
          <input class="input" id="cargo" name="cargo" type="text" required>
        </div>

        <div class="field">
          <label for="area_trabajo">Área de trabajo</label>
          <select class="input" id="area_trabajo" name="area_trabajo" required>
            <option value="">Seleccione un área</option>
            <?php foreach ($areas as $area): ?>
              <option value="<?php echo h($area); ?>"><?php echo h($area); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <!-- Campo: Sexo/Género (opcional, wording inclusivo) -->
<div class="field">
  <label for="sexo">Sexo / Género (opcional)</label>
  <select class="input" id="sexo" name="sexo">
    <option value="">Prefiero no decir</option>
    <option value="Mujer">Mujer</option>
    <option value="Hombre">Hombre</option>
    <option value="No binario">No binario</option>
    <option value="Otro">Otro</option>
  </select>
  <small class="muted">Usamos esta información con fines internos y estadísticos de cultura/igualdad. No afecta tus evaluaciones individuales.</small>
</div>


        <?php if (empty($areas)): ?>
          <div class="alert" role="status">⚠️ No hay áreas de trabajo registradas para esta empresa.</div>
        <?php endif; ?>

        <div class="divider" role="presentation"></div>

        <button class="btn" type="submit">Avanza ➝</button>

        <?php if ($empresa): ?>
          <p class="muted">Tu marca: <strong><?php echo h($empresa); ?></strong></p>
        <?php endif; ?>
      </form>
    </section>
  </main>
</body>
</html>
