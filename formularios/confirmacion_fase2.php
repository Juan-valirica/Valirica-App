<?php
// confirmacion_fase2.php
session_start();
require_once __DIR__ . '/../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// === Utilidades de logos ===
function fallback_svg_data_uri(){
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="80" viewBox="0 0 320 120">
    <rect width="100%" height="100%" rx="12" fill="#ffffff"/>
    <rect x="16" y="16" width="288" height="88" rx="10" fill="#f3f6f8" stroke="#e0e6ea"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif" font-size="16" fill="#8093a1">Logo</text>
  </svg>';
  return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
function resolve_logo_src($raw){
  $p = trim((string)($raw ?? ''));
  if ($p === '') return '';
  if (preg_match('#^https?://#i', $p)) return $p;
  if ($p[0] === '/') return $p;
  $p_no_dot = ltrim($p, './');
  if (stripos($p_no_dot, 'uploads/') === 0) return '/' . $p_no_dot;
  return '/uploads/' . ltrim($p_no_dot, '/');
}
function safe_logo_src($raw){
  $src = resolve_logo_src($raw);
  if ($src === '') return fallback_svg_data_uri();
  if ($src[0] === '/') {
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $abs1 = $docroot . $src;
    if (is_file($abs1)) return $src;
    $abs2 = dirname($docroot) . $src;
    if (is_file($abs2)) return $src;
  }
  return fallback_svg_data_uri();
}

// === Carga de persona & empresa ===
$equipo_id = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;
if ($equipo_id <= 0) { http_response_code(400); echo "Falta equipo_id"; exit; }

$sql = "SELECT e.nombre_persona, e.correo, e.usuario_id AS empresa_id,
               u.empresa AS empresa_nombre, u.rol, u.provider_id, u.logo AS logo_empresa
        FROM equipo e
        JOIN usuarios u ON u.id = e.usuario_id
        WHERE e.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $equipo_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$info) { http_response_code(404); echo "Registro no encontrado"; exit; }

$nombre_persona = $info['nombre_persona'] ?? '';
$correo         = $info['correo'] ?? '';
$empresa_nombre = $info['empresa_nombre'] ?? '';
$rol_empresa    = $info['rol'] ?? '';
$provider_id    = $info['provider_id'] ?? null;

// Lógica de logo final mostrado al empleado
$logo_empresa_final = safe_logo_src($info['logo_empresa'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>¡Gracias! | Registro completado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">

<style>
  :root{
    --c-primary:#012133;
    --c-secondary:#184656;
    --c-accent:#EF7F1B;
    --c-soft:#FFF5F0;
    --c-body:#474644;
    --radius:22px;
    --shadow:0 6px 26px rgba(0,0,0,0.08);
  }

  *{ box-sizing:border-box; }
  body{
    margin:0; background:#fff; color:var(--c-body);
    font-family:"gelica", system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  }

  .shell{
    width:min(900px, 100%);
    margin: 4vh auto;
    background:#fff;
    border:1px solid #f1f1f1;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    overflow:hidden;
  }

  .hero{
    padding: clamp(32px, 6vh, 64px);
    text-align:center;
    background: linear-gradient(180deg, #012133 0%, #184656 100%);
    color:#fff;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:16px;
  }

  .brand-block{
    background:#fff;
    padding:12px 20px;
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,.12);
    display:flex;
    justify-content:center;
    align-items:center;
  }
  .brand-block img{
    max-width:200px;
    max-height:90px;
    object-fit:contain;
  }

  h1{
    margin:0;
    font-size: clamp(24px, 4vw, 32px);
    font-weight:800;
  }
  .hero-sub{
    margin:0;
    opacity:.92;
    font-size: clamp(15px, 1.8vw, 18px);
    line-height:1.45;
  }

  .content{
    padding: clamp(28px, 5vh, 48px);
    display:grid;
    gap:20px;
  }

  .card{
    padding:22px;
    background:#fff;
    border-radius:18px;
    border:1px solid #eee;
  }

  .card h2{
    margin:0 0 8px 0;
    font-size:20px;
    color:var(--c-secondary);
  }

  .hint{
    background: var(--c-soft);
    border-radius:16px;
    padding:18px;
    border:1px dashed rgba(239,127,27,.45);
  }

  .closing{
    margin-top:8px;
    font-size:15px;
    color:#6b6b6b;
  }
</style>
</head>

<body>
  <main class="shell">

    <!-- HERO -->
    <section class="hero">
      <div class="brand-block">
        <img src="<?php echo h($logo_empresa_final); ?>" alt="Logo de la empresa">
      </div>

      <h1>¡Listo, <?php echo h($nombre_persona); ?>! Gracias por completar tu registro</h1>

      <p class="hero-sub">
        Eres parte del motor que impulsa a <strong><?php echo h($empresa_nombre); ?></strong>.
        Las empresas mueven al mundo… pero son las personas como tú quienes mueven a las empresas.
      </p>
    </section>

    <!-- CONTENIDO -->
    <section class="content">

      <div class="card">
        <h2>Lo que viene ahora</h2>
        <p>
          Durante los próximos días activaremos tu acceso al <strong>Dashboard Valírica</strong>:
          un espacio diseñado para ayudarte a entender mejor cómo trabajas, cómo aportas al equipo
          y cómo puedes hacer aún más poderosa la cultura de <?php echo h($empresa_nombre); ?>.
        </p>
        <p>
          Este no es un formulario más. Es el inicio de un viaje donde tu talento se vuelve visible,
          útil y estratégico.
        </p>
      </div>

      <div class="card hint">
        <p>
          No tienes que hacer nada más. Cuando tu plataforma esté habilitada, te enviaremos un acceso directo.
        </p>
        <p class="closing">
          Usuario registrado: <strong><?php echo h($correo); ?></strong>
        </p>
      </div>

      <div class="card">
        <h2>Un recordatorio importante</h2>
        <p>
          A veces olvidamos algo simple: tú haces la diferencia.  
          Tu forma de comunicar, crear, colaborar y liderar influye más de lo que imaginas en el futuro de tu empresa.
        </p>
        <p>
          Y ese es el corazón de Valírica: activar el 100% del talento para que <strong><?php echo h($empresa_nombre); ?></strong> avance,
          crezca y siga moviendo el mundo.
        </p>
      </div>

    </section>
  </main>
</body>
</html>

