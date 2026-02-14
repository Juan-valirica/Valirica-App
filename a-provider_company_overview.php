<?php
/* ==========================================================
   a-provider_company_overview.php
   Vista: PROVEEDOR → Ficha completa de una “Company” cliente
   - Header unificado (igual a brand) con fallback
   - Tarjeta inicial: logo, propósito (cultura_ideal), valores (chips desde valores_marca),
     estilo, ubicación, contacto, miembros inscritos
   - KPIs: Alineación (80/20) y Motivación
   - Accesos firmados a vistas profundas
   ========================================================== */

session_start();
require 'config.php';
require_once 'auth_scope.php'; // make_company_sig(...) y CSRF en $_SESSION['csrf_token']

// CSRF para firma (si no existe)
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ------------ Helpers ------------ */
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function resolve_logo_url(?string $path): string {
  $valiricaDefault = 'https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';
  $p = trim((string)$path);
  if ($p === '') return $valiricaDefault;
  if (preg_match('~^https?://~i', $p)) return $p;
  if (strpos($p, '//') === 0) return 'https:' . $p;
  if ($p !== '' && $p[0] === '/') return 'https://app.valirica.com' . $p;
  if (stripos($p, 'uploads/') === 0) return 'https://app.valirica.com/' . $p;
  return 'https://app.valirica.com/uploads/' . $p;
}
function motivation_label(?int $pct): string {
  if ($pct === null) return 'Sin datos';
  if ($pct >= 75) return 'Alta';
  if ($pct >= 45) return 'Media';
  if ($pct > 0)   return 'Baja';
  return 'Sin datos';
}




function normalize_culture_type(?string $raw): string {
  $v = trim(mb_strtolower((string)$raw, 'UTF-8'));

  if ($v === '') return '';

  // Cultura Colaborativa
  if (in_array($v, ['clan','colaborativa','cultura colaborativa'], true)) {
    return 'Cultura Colaborativa';
  }

  // Cultura Ágil
  if (in_array($v, ['adhocracia','adhocracy','innovadora','ágil','agil','cultura ágil','cultura agil'], true)) {
    return 'Cultura Ágil';
  }

  // Cultura Estructurada
  if (in_array($v, ['jerárquica','jerarquica','burocrática','burocratica','estructurada','cultura estructurada'], true)) {
    return 'Cultura Estructurada';
  }

  // Orientada a Resultados
  if (in_array($v, ['mercado','orientada a resultados','resultados','market'], true)) {
    return 'Orientada a Resultados';
  }

  // Si no hace match, devolvemos tal cual
  return (string)$raw;
}


/* ------------ Auth: solo proveedores ------------ */
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$viewer_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, empresa, rol, logo FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $viewer_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$_SESSION['role'] = strtolower((string)($viewer['rol'] ?? ''));
if (!$viewer || strcasecmp((string)$viewer['rol'], 'provider') !== 0) {
  http_response_code(403);
  echo "<h1 style='font-family:sans-serif;color:#012133;'>403 — Solo para proveedores</h1>";
  exit;
}

$provider_name = (string)($viewer['empresa'] ?? 'Proveedor');
$provider_logo = (string)($viewer['logo'] ?? '');




// =========================
// Contexto para el header
// (igual que en a-provider_companies.php)
// =========================

// Datos del usuario loggeado (viewer) que leerá a-header-desktop-brand.php
$viewer_empresa      = $provider_name;
$viewer_logo         = '';          // si algún día guardas logo del provider, lo cargas aquí
$viewer_cultura_tipo = '';          // aquí no aplica cultura de empresa
$viewer_rol          = 'provider';

// En esta vista el foco principal es el CLIENTE, pero para el header
// usamos al provider como "empresa" para mantener la misma cabecera
$usuario_id           = 0;
$empresa              = $provider_name;  // aparecerá en el header como título
$logo                 = '';
$cultura_empresa_tipo = '';

// KPIs neutros (en esta vista los mostramos en las tarjetas, no en el header)
$promedio_general     = null;
$aline_label          = '';
$aline_class          = '';
$aline_icon           = null;
$energia_equipo       = null;
$energia_status       = '';
$estilo_equipo_aprend = '';
$aprend_alineado      = '';
$mot_label            = '';
$mot_class            = '';
$mot_icon             = null;




/* ------------ Input seguro: company_id + firma (con auto-firma y selector) ------------ */
$company_id = (int)($_GET['company_id'] ?? 0);
$sig        = (string)($_GET['sig'] ?? '');
$csrf       = (string)($_SESSION['csrf_token'] ?? '');

// Para reutilizar la lógica de cultura/propósito/valores del otro archivo:
$user_id = $company_id;


// Si viene company_id pero sin sig → auto-firma si pertenece al provider y redirige
if ($company_id > 0 && $sig === '') {
  $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ? AND provider_id = ? LIMIT 1");
  $stmt->bind_param("ii", $company_id, $viewer_id);
  $stmt->execute();
  $exists = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($exists) {
    $auto_sig = make_company_sig($company_id, $viewer_id, $csrf);
    $q = http_build_query(['company_id'=>$company_id, 'sig'=>$auto_sig]);
    header("Location: a-provider_company_overview.php?$q", true, 302);
    exit;
  } else {
    http_response_code(404);
    echo "<h1 style='font-family:sans-serif;color:#012133;'>404 — Company no encontrada para este provider</h1>";
    exit;
  }
}

// Si no viene nada → mostrar selector de Companies del provider
if ($company_id <= 0 && $sig === '') {
  $stmt = $conn->prepare("
    SELECT id, empresa FROM usuarios
    WHERE provider_id = ? AND (LOWER(rol) IN ('company','company_admin','empresa','companyadmin'))
    ORDER BY empresa ASC
  ");
  $stmt->bind_param("i", $viewer_id);
  $stmt->execute();
  $companies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  ?>
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Seleccione una Company — <?= h($provider_name) ?></title>
    <link rel="preconnect" href="https://use.typekit.net" crossorigin>
    <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
    <style>
      
   
  :root {
  --c-primary:#012133;
  --c-secondary:#184656;
  --c-accent:#EF7F1B;
  --c-soft:#FFF5F0;
  --c-body:#474644;
  --c-bg:#FFFFFF;
  --shadow:0 6px 20px rgba(0,0,0,0.06);
  --radius:20px;
}

  *{box-sizing:border-box;}

  html,body{
    margin:0;
    min-height:100vh;
    background:var(--c-bg);
    color:var(--c-body);
    font-family:"gelica",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
  }

  img{max-width:100%;display:block;}
  a{color:inherit;text-decoration:none;}

  /* Header estilo Valírica (versión provider simple) */
  header {
    width:100%;
    background:var(--c-primary);
    color:var(--c-soft);
    padding:14px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    box-shadow:0 3px 12px rgba(0,0,0,0.08);
  }

  .nav-left {
    display:flex;
    align-items:center;
    gap:14px;
  }

  .brand-logo {
    width:40px;
    height:40px;
    border-radius:10px;
    object-fit:cover;
    background:#f4f4f4;
    box-shadow:0 2px 6px rgba(0,0,0,0.25);
  }

  .title {
    display:flex;
    flex-direction:column;
  }

  .title h1 {
    margin:0;
    font-size:clamp(18px,2.4vw,24px);
    color:var(--c-soft);
    letter-spacing:-0.3px;
    line-height:1.1;
  }

  .title span {
    font-size:13px;
    color:var(--c-soft);
    opacity:0.8;
  }

  @media (max-width: 680px){
    header {
      padding:12px 16px;
      flex-wrap:wrap;
      row-gap:6px;
    }
  }




  .wrap{
    max-width:1200px;
    margin:0 auto;
    padding:24px;
  }

  /* Pequeño texto contextual arriba de la ficha */
  .page-intro{
    font-size:13px;
    color:#6d6d6d;
    margin:8px 0 16px;
  }

  /* Tarjeta principal de la marca (logo + propósito + valores + contacto) */
  .brand-card{
    display:grid;
    grid-template-columns:140px 1fr 320px;
    gap:16px;
    border:1px solid #eee;
    border-radius:16px;
    background:#fff;
    box-shadow:var(--shadow);
    padding:24px;
  }
  @media (max-width:980px){
    .brand-card{
      grid-template-columns:120px 1fr;
      grid-template-rows:auto auto;
    }
    .contact{
      grid-column:1 / -1;
      border-left:none;
      border-top:1px dashed #eee;
      margin-top:12px;
      padding-left:0;
      padding-top:12px;
    }
  }
  @media (max-width:640px){
    .brand-card{
      grid-template-columns:1fr;
    }
    .logo-box{
      margin:0 auto 10px;
    }
  }

  .logo-box{
    width:140px;
    height:140px;
    border:1px solid #eee;
    border-radius:12px;
    background:#fafafa;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
  }
  .logo-box img{
    max-width:94%;
    max-height:94%;
    object-fit:contain;
  }

  .brand-main .name{
    font-size:20px;
    font-weight:800;
    color:var(--c-secondary);
  }

  .chips{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:6px;
  }
  .chip{
    font-size:11px;
    font-weight:700;
    border:1px solid rgba(1,33,51,0.08);
    background:#FFF5F0;
    border-radius:999px;
    padding:4px 8px;
    color:#184656;
    line-height:1.2;
  }

  .section-title{
    margin:20px 0 8px;
    font-weight:900;
    color:var(--c-primary);
    font-size:14px;
  }
  .muted{
    color:#666;
    font-size:13px;
    line-height:1.5;
  }

  .contact{
    border-left:1px dashed #eee;
    padding-left:16px;
  }
  .contact .line{
    font-size:14px;
    margin:6px 0;
  }

  /* Tags suaves tipo dashboard_brand (Dimensiones, Cultura, etc.) */
  .vl-tags{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin:4px 0 10px;
  }
  .vl-tag{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:3px 9px;
    border-radius:9999px;
    font-size:11px;
    font-weight:600;
    background:#FFF5F0;
    border:1px solid rgba(1,33,51,0.08);
    color:var(--c-secondary);
  }
  .vl-tag::before{
    content:"•";
    font-weight:700;
    opacity:.6;
  }

  /* Bloques KPI alineación / motivación */
  .kpis{
    display:grid;
    grid-template-columns:200px 1fr;
    gap:14px;
    margin-top:20px;
  }
  @media (max-width:720px){
    .kpis{
      grid-template-columns:1fr;
    }
  }
  .kpi-block{
    background:#fff;
    border:1px solid #eee;
    border-radius:12px;
    box-shadow:var(--shadow);
    padding:14px;
  }
  .kpi-label{
    font-size:12px;
    color:#6d6d6d;
    margin-bottom:6px;
  }
  .kpi-strong{
    font-size:22px;
    font-weight:900;
    color:var(--c-primary);
  }
  .kpi-meta{
    margin-top:6px;
    font-size:12px;
    color:#6d6d6d;
  }
  .pill{
    font-size:11px;
    padding:4px 8px;
    border-radius:999px;
    background:var(--c-soft);
    border:1px solid rgba(1,33,51,.06);
  }

  /* Anillo de alineación (ring) */
  .ring{
    --size:120px;
    --thick:12px;
    --pct: <?= (int)($align_pct ?? 0); ?>;
    width:var(--size);
    height:var(--size);
    border-radius:50%;
    background:conic-gradient(var(--c-accent) calc(var(--pct) * 1%), #eee 0);
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto;
    position:relative;
  }
  .ring::after{
    content:"";
    position:absolute;
    inset:calc(var(--thick));
    background:#fff;
    border-radius:50%;
  }
  .ring-wrap{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  .ring-value{
    position:relative;
    z-index:1;
    font-weight:900;
    font-size:22px;
    color:var(--c-secondary);
  }

  /* Grid inferior: resumen CPV + accesos rápidos */
  .grid-two{
    display:grid;
    grid-template-columns:1.4fr 1fr;
    gap:16px;
    margin-top:10px;
  }
  @media (max-width:960px){
    .grid-two{
      grid-template-columns:1fr;
    }
  }

  .card{
  background:#fff;
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:24px;
  border:1px solid #f1f1f1;
  min-height:180px;
  display:flex;
  flex-direction:column;
  gap:10px;
  transition:.2s ease;
  text-align:justify;
  margin-bottom:24px;
}
.card:hover{
  transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
.card h3{
  color:var(--c-secondary);
  font-size:clamp(16px,2vw,20px);
}







  /* === Lista de Riesgos de fuga (personas) === */
  .rf-list{
    list-style:none;
    margin:8px 0 0;
    padding:0;
    display:flex;
    flex-direction:column;
    gap:10px;
  }
  .rf-item{
    display:grid;
    grid-template-columns:1fr auto auto; /* izquierda ocupa, centro chip, derecha acciones */
    gap:14px;
    align-items:center;
    width:100%;
  }

  .rf-left{
    display:grid;
    grid-template-columns:40px auto;
    gap:12px;
    min-width:0;
    align-items:center;
  }
  .rf-avatar{
    width:40px;
    height:40px;
    border-radius:9999px;
    background:var(--c-accent);
    color:#fff;
    font-weight:700;
    font-size:15px;
    display:grid;
    place-items:center;
    box-shadow:0 1px 3px rgba(0,0,0,0.12);
  }
  .rf-id{
    min-width:0;
  }
  .rf-name{
    font-weight:700;
    font-size:13px;
    color:var(--c-secondary);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .rf-role{
    font-weight:400;
    font-size:12px;
    color:#6a6a6a;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .rf-center{
    display:inline-flex;
    align-items:center;
    gap:8px;
    justify-self:center;
  }
  .rf-right{
    display:inline-flex;
    align-items:center;
    gap:10px;
    justify-self:end;
  }

  .rf-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:9999px;
    line-height:1;
    font-size:12px;
    border:1px solid rgba(0,0,0,0.08);
    background:#FFF5F0;
    font-weight:600;
  }
  .rf-alert{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.06em;
    padding:2px 6px;
    border-radius:9999px;
    background:rgba(255,0,0,0.06);
    color:#b00020;
    border:1px solid rgba(176,0,32,0.18);
  }

  .rf-battery{
    height:18px;
    width:auto;
    display:block;
    image-rendering:-webkit-optimize-contrast;
  }
  .rf-battery-lg{
    height:20px;
  }

  .rf-btn{
    font-size:11px;
    font-weight:700;
    padding:6px 10px;
    border-radius:9999px;
    border:1px solid rgba(1,33,51,0.12);
    background:#fff;
    color:var(--c-primary);
    text-decoration:none;
    white-space:nowrap;
  }

  #card-riesgos-fuga .rf-list{
    max-height:260px;
    overflow-y:auto;
    padding-right:4px;
  }

  /* === Lista de Áreas de oportunidad (equipo) === */
  .gr-list{
    list-style:none;
    margin:8px 0 0;
    padding:0;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  .gr-item{
    display:grid;
    grid-template-columns:1fr auto auto;
    gap:14px;
    align-items:center;
  }
  .gr-left{
    display:grid;
    grid-template-rows:auto auto;
    gap:4px;
    min-width:0;
  }
  .gr-title{
    font-weight:700;
    color:var(--c-secondary);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .gr-desc{
    font-size:12px;
    color:#6d6d6d;
    line-height:1.5;
  }
  .gr-center{
    justify-self:center;
  }
  .gr-right{
    display:inline-flex;
    justify-self:end;
    align-items:center;
  }

  .gr-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:9999px;
    line-height:1;
    font-size:12px;
    border:1px solid rgba(0,0,0,0.08);
    background:#FFF5F0;
    font-weight:600;
  }
  .gr-btn{
    font-size:11px;
    font-weight:700;
    padding:6px 10px;
    border-radius:9999px;
    border:1px solid rgba(1,33,51,0.12);
    background:#fff;
    color:var(--c-primary);
    text-decoration:none;
    white-space:nowrap;
  }

  #card-riesgos-equipo .gr-list{
    max-height:260px;
    overflow-y:auto;
    padding-right:4px;
  }

  @media (max-width: 880px){
    .rf-item,
    .gr-item{
      grid-template-columns:1fr;
      align-items:flex-start;
    }
    .rf-right,
    .gr-right{
      justify-self:flex-start;
    }
  }


.full-width-cards{
  margin-top:10px;
  display:flex;
  flex-direction:column;
  gap:16px;
}



  .footer-links{
    display:flex;
    gap:10px;
    justify-content:flex-start;
    margin-top:8px;
    flex-wrap:wrap;
  }
  .btn{
    background:var(--c-primary);
    color:#fff;
    border:0;
    border-radius:10px;
    padding:10px 12px;
    font-weight:800;
    cursor:pointer;
    font-size:13px;
    display:inline-flex;
    align-items:center;
    gap:6px;
  }
  .btn.secondary{
    background:var(--c-accent);
  }
  
  
 
   /* ==== Separación vertical entre bloques principales ==== */
  .brand-card,
  .kpis,
  .grid-two,
  .card {
    margin-top:10px;
    margin-bottom:10px;
  }

  /* Ajuste fino para que el primer bloque no quede demasiado abajo */
  .wrap > .brand-card:first-of-type {
    margin-top:16px;
  }

 
  
  
  
  
  
  
   /* Columna derecha con cards en stack (alineación + accesos) */
  .stack-col{
    display:flex;
    flex-direction:column;
    gap:16px;
  }

  /* Círculo de alineación – tamaño compacto */
  .alignment-wrap {
    position: relative;
    width: 100%;
    max-width: 100%;
    aspect-ratio: 1 / 1;
    min-height: 220px;  /* Más pequeño que antes */
  }
  .alignment-canvas {
    width: 100%;
    height: 100%;
    display: block;
    border-radius: 12px;
    background:#fff;
  }

  .vl-tooltip {
    position: absolute;
    pointer-events: none;
    background: #012133;
    color: #fff;
    font-family: "gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    line-height: 1.35;
    padding: 8px 10px;
    border-radius: 10px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    transform: translate(-50%, -120%);
    white-space: nowrap;
    opacity: 0;
    transition: opacity .12s ease;
    z-index: 2;
    border: 1px solid rgba(255,255,255,0.08);
  }

  @media (max-width:960px){
    .stack-col{
      margin-top:16px;
    }
  }

  
  
  
  
  
  /* =======================
   CUADRANTES CULTURALES
======================== */
.culture-quadrants {
  margin-top:10px;
  padding:20px;
  border-radius:16px;
  background:#fff;
  border:1px solid #eee;
  box-shadow:var(--shadow);
}

.cq-wrap {
  width:100%;
  max-width:520px;
  margin:0 auto;
}

#cqCanvas {
  width:100%;
  height:auto;
  aspect-ratio:1 / 1;
}

.cq-legend {
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  margin-top:16px;
  font-size:12px;
  color:#184656;
}

.cq-legend .dot {
  width:10px;
  height:10px;
  border-radius:50%;
  display:inline-block;
  margin-right:4px;
}

.dot-real { background:#184656; }
.dot-ideal { background:#EF7F1B; }
.dot-purpose { background:#6a00ff; }
.dot-values { background:#00a884; }
.dot-type { background:#f54291; }




  
  
  

      
      
      
      .wrap{max-width:720px;margin:0 auto;padding:24px}
      select,button{font-size:15px;padding:10px;border-radius:10px;border:1px solid #ddd}
      button{background:var(--c-accent);color:#fff;border:0;font-weight:800;cursor:pointer;margin-left:8px}
      .row{display:flex;gap:8px;align-items:center}
    </style>
  </head>
  <body>

    <!-- HEADER NUEVO (FUERA DE PHP) -->
    <header>
      <div class="nav-left">
        <img
          class="brand-logo"
          src="<?= h($provider_logo !== '' ? resolve_logo_url($provider_logo) : 'https://app.valirica.com/uploads/logo-192.png'); ?>"
          alt="Logo de <?= h($provider_name) ?>"
        >
        <div class="title">
          <h1><?= h($provider_name) ?></h1>
          <span>Dashboard de administración de clientes</span>
        </div>
      </div>
       <div class="nav-right">
    <a href="https://app.valirica.com/a-desktop-dashboard-brand.php" class="go-dashboard-btn">
      Regresar a tu Dashboard
    </a>
  </div>
      
    </header>

    <?php
    // Aquí continúa el PHP normal
    // (por ejemplo el condicional de si hay o no company_id)
    ?>

    <div class="wrap">
      <?php if (!$companies): ?>
        <p>No hay Companies vinculadas todavía.</p>
      <?php else: ?>
        <form method="get" class="row" onsubmit="
          const sel=this.querySelector('select');
          if(!sel.value) return false;
          return true;
        ">
          <select name="company_id" required>
            <option value="">— Elegir empresa —</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= h($c['empresa']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Abrir ficha</button>
        </form>
        <p style="color:#666;margin-top:10px">Al elegir, generaremos automáticamente un enlace firmado y te redirigiremos.</p>
      <?php endif; ?>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// Validar firma normal
$expected = make_company_sig($company_id, $viewer_id, $csrf);
if (!hash_equals($expected, $sig)) {
  http_response_code(401);
  echo "<h1 style='font-family:sans-serif;color:#012133;'>401 — Firma inválida</h1>";
  exit;
}

/* ------------ Validar que la company pertenece al provider ------------ */
$stmt = $conn->prepare("
  SELECT id, empresa, logo, cultura_empresa_tipo,
         nombre, apellido, email, correo_oficial_comunicacion,
         pdf_analisis, pdf_valores, provider_id
  FROM usuarios
  WHERE id = ? AND provider_id = ?
  LIMIT 1
");
$stmt->bind_param("ii", $company_id, $viewer_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$company) {
  http_response_code(404);
  echo "<h1 style='font-family:sans-serif;color:#012133;'>404 — Company no encontrada para este provider</h1>";
  exit;
}

/* ------------ Cargar propósito/estilo/ubicación desde cultura_ideal ------------ */
function detect_ci_fk(mysqli $conn): ?string {
  foreach (['company_id','empresa_id','usuario_id'] as $fk) {
    try { $t = $conn->prepare("SELECT COUNT(*) c FROM cultura_ideal WHERE `$fk` IS NOT NULL LIMIT 1"); $t->execute(); $t->close(); return $fk; }
    catch (\Throwable $e) { /* siguiente */ }
  }
  return null;
}
$ci_fk    = detect_ci_fk($conn);
$prop     = '';   // proposito (cultura_ideal)
$estilo   = '';   // estilo_comunicacion (visual/auditivo/kinestesico)
$ubicacion= '';   // país

if ($ci_fk) {
  $sqlCI = "SELECT proposito, estilo_comunicacion, ubicacion
            FROM cultura_ideal
            WHERE `$ci_fk` = ?
            ORDER BY id DESC
            LIMIT 1";
  $stCI = $conn->prepare($sqlCI);
  $stCI->bind_param("i", $company_id);
  $stCI->execute();
  $ci = $stCI->get_result()->fetch_assoc() ?: null;
  $stCI->close();
  if ($ci) {
    $prop      = trim((string)($ci['proposito'] ?? ''));
    $estilo    = trim((string)($ci['estilo_comunicacion'] ?? ''));
    $ubicacion = trim((string)($ci['ubicacion'] ?? ''));
  }
}





/* ------------ KPIs (reutiliza lógica 80/20 Hofstede/Maslow) ------------ */
function detect_fk(mysqli $conn): ?string {
  foreach (['empresa_id','company_id','usuario_id'] as $fk) {
    try { $t = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$fk` IS NOT NULL LIMIT 1"); $t->execute(); $t->close(); return $fk; }
    catch (\Throwable $e) { /* siguiente */ }
  }
  return null;
}
function get_company_metrics(mysqli $conn, int $company_id): array {
  $fk = detect_fk($conn);
  if (!$fk) return ['team_count'=>0,'align_pct'=>null,'motiv_pct'=>null];

  $whereEstado = " AND (estado IS NULL OR estado='activo')";
  try {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$fk` = ? $whereEstado");
    $stmt->bind_param("i", $company_id); $stmt->execute();
    $team_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
  } catch (\Throwable $e) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$fk` = ?");
    $stmt->bind_param("i", $company_id); $stmt->execute();
    $team_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
  }

  $align_avg = null;
  try {
    $sqlAlign = "
      SELECT AVG(
        ( IFNULL(hofstede_porcentaje,0)*0.8 + IFNULL(maslow_porcentaje,0)*0.2 )
        / NULLIF( (IF(hofstede_porcentaje IS NOT NULL,0.8,0) + IF(maslow_porcentaje IS NOT NULL,0.2,0)), 0 )
      ) AS a
      FROM equipo
      WHERE `$fk` = ? $whereEstado
    ";
    $st = $conn->prepare($sqlAlign); $st->bind_param("i", $company_id);
    $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
    if ($r && $r['a'] !== null) $align_avg = (float)$r['a'];
  } catch (\Throwable $e) { $align_avg = null; }

  if ($align_avg === null) {
    foreach (['alineacion_pct','alineacion_porcentaje','alineacion_total'] as $col) {
      try {
        $st = $conn->prepare("SELECT AVG(`$col`) a FROM equipo WHERE `$fk` = ? $whereEstado");
        $st->bind_param("i", $company_id); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        if ($r && $r['a'] !== null) { $align_avg = (float)$r['a']; break; }
      } catch (\Throwable $e) { /* siguiente */ }
    }
  }

  $motiv_avg = null;
  foreach (['motivacion_pct','motivacion_porcentaje','motivacion_total','motivacion','motivacion_media'] as $mcol) {
    try {
      $st = $conn->prepare("SELECT AVG(`$mcol`) m FROM equipo WHERE `$fk` = ? $whereEstado");
      $st->bind_param("i", $company_id); $st->execute();
      $r = $st->get_result()->fetch_assoc(); $st->close();
      if ($r && $r['m'] !== null) { $motiv_avg = (float)$r['m']; break; }
    } catch (\Throwable $e) { /* siguiente */ }
  }

  if ($align_avg !== null && $align_avg > 0 && $align_avg <= 1.5) $align_avg *= 100;
  if ($motiv_avg !== null && $motiv_avg > 0 && $motiv_avg <= 1.5) $motiv_avg *= 100;

  $align_pct = $align_avg !== null ? max(0, min(100, (int)round($align_avg))) : null;
  $motiv_pct = $motiv_avg !== null ? max(0, min(100, (int)round($motiv_avg))) : null;

  return ['team_count'=>$team_count, 'align_pct'=>$align_pct, 'motiv_pct'=>$motiv_pct];
}






/* ==========================================================
   MAPA DE ORIENTACIÓN CULTURAL — DATOS (para Chart.js)
   Basado en a-cultura-proposito-valores.php, adaptado a $company_id
   ========================================================== */




// 1) CULTURA IDEAL (incluye propósito)
$stmt_cultura = $conn->prepare("
    SELECT 
        distancia_poder,
        individualismo,
        masculinidad,
        incertidumbre,
        largo_plazo,
        indulgencia,
        proposito,
        proposito_enfoque,
        proposito_motivacion,
        proposito_tiempo,
        proposito_disrupcion,
        proposito_inmersion,
        valores_json,
        estilo_comunicacion,
        ubicacion
    FROM cultura_ideal
    WHERE usuario_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt_cultura->bind_param("i", $user_id);
$stmt_cultura->execute();
$result_cultura = $stmt_cultura->get_result();
$cultura_ideal_mapa = $result_cultura->fetch_assoc() ?? [];
$stmt_cultura->close();

// Variables base del propósito
$proposito_txt        = trim($cultura_ideal_mapa['proposito'] ?? '');
$proposito_enfoque    = (float)($cultura_ideal_mapa['proposito_enfoque'] ?? 0);
$proposito_motivacion = (float)($cultura_ideal_mapa['proposito_motivacion'] ?? 0);
$proposito_tiempo     = (float)($cultura_ideal_mapa['proposito_tiempo'] ?? 0);
$proposito_disrupcion = (float)($cultura_ideal_mapa['proposito_disrupcion'] ?? 0);
$proposito_inmersion  = (float)($cultura_ideal_mapa['proposito_inmersion'] ?? 0);

// 2) VALORES → puntos individuales (X/Y)
$stmt_valores = $conn->prepare("
    SELECT 
        titulo,
        descripcion,
        aplicacion,
        activador,
        proposito,
        rol,
        institucional
    FROM valores_marca
    WHERE usuario_id = ?
");
$stmt_valores->bind_param("i", $user_id);
$stmt_valores->execute();
$result_valores = $stmt_valores->get_result();

$valores_puntos = [];
while ($v = $result_valores->fetch_assoc()) {
    $titulo = trim($v['titulo'] ?? '');
    $descripcion = trim($v['descripcion'] ?? '');

    $aplicacion    = is_numeric($v['aplicacion']) ? (float)$v['aplicacion'] : 0;
    $activador     = is_numeric($v['activador']) ? (float)$v['activador'] : 0;
    $proposito_val = is_numeric($v['proposito']) ? (float)$v['proposito'] : 0;
    $rol           = is_numeric($v['rol']) ? (float)$v['rol'] : 0;
    $institucional = is_numeric($v['institucional']) ? (float)$v['institucional'] : 0;

    // Coordenadas de cada valor en el mapa
    $x_val = round(($aplicacion + $activador + $proposito_val) / 3, 2);
    $y_val = round(($rol + $institucional) / 2, 2);

    if ($titulo !== '') {
        $valores_puntos[] = [
            'x'         => $x_val,
            'y'         => $y_val,
            'label'     => $titulo,
            'desc'      => $descripcion
        ];
    }
}
$stmt_valores->close();

/** Promedia columnas específicas de valores_marca (igual que en cultura-proposito-valores) */
function promedio_valores_marca(array $claves, mysqli $conn, int $uid): float {
    $sumas = array_fill_keys($claves, 0);
    $count = 0;
    $campos = implode(', ', $claves);
    $stmt = $conn->prepare("SELECT $campos FROM valores_marca WHERE usuario_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        foreach ($claves as $clave) { $sumas[$clave] += (int)$fila[$clave]; }
        $count++;
    }
    $stmt->close();

    if ($count === 0) return 0.0;
    $total = 0; $dim = 0;
    foreach ($claves as $c) { $total += $sumas[$c]; $dim++; }
    return ($dim > 0) ? round($total / ($count * $dim), 2) : 0.0;
}

// Promedios de apoyo para los ejes (valores)
$prom_x = promedio_valores_marca(['aplicacion','activador','proposito'], $conn, $user_id);
$prom_y = promedio_valores_marca(['rol','institucional'], $conn, $user_id);

// Puntos del propósito (peso 1.3 en el promedio final)
$proposito_puntos = [
  ['x'=>$proposito_enfoque,    'y'=>$proposito_motivacion],
  ['x'=>$proposito_disrupcion, 'y'=>$proposito_inmersion],
  ['x'=>$proposito_enfoque,    'y'=>$proposito_tiempo],
];

// 3) PUNTOS DEL EQUIPO EN EL CUADRANTE (Hofstede → eje X/Y)
$equipo_puntos = [];
$q = $conn->prepare("
  SELECT nombre_persona,
         hofstede_poder          AS distancia_poder,
         hofstede_individualismo AS individualismo,
         hofstede_incertidumbre  AS incertidumbre,
         hofstede_espontaneidad  AS indulgencia
  FROM equipo
  WHERE usuario_id = ?
");
$q->bind_param("i", $user_id);
$q->execute();
$rs = $q->get_result();

while ($r = $rs->fetch_assoc()) {
    $indiv = (float)($r['individualismo']   ?? 0);   // -1..+1 (externo +)
    $poder = (float)($r['distancia_poder']  ?? 0);   // -1..+1 (verticalidad)
    $inc   = (float)($r['incertidumbre']    ?? 0);   // -1..+1 (aversión)
    $indul = (float)($r['indulgencia']      ?? 0);   // -1..+1 (flexibilidad)

    // Heurística coherente con ejes:
    // X: más individualismo, algo menos distancia de poder
    $x = (0.70 * $indiv) - (0.30 * $poder);
    // Y: más indulgencia, algo menos aversión a la incertidumbre
    $y = (0.60 * $indul) - (0.40 * $inc);

    // Escala -5..+5 y clamp
    $x = max(-1, min(1, $x)) * 5.0;
    $y = max(-1, min(1, $y)) * 5.0;

    $label = trim((string)($r['nombre_persona'] ?? '—'));
    if ($label !== '') {
        $equipo_puntos[] = ['x'=>round($x,2), 'y'=>round($y,2), 'label'=>$label];
    }
}
$q->close();

// 4) PROMEDIO PONDERADO FINAL (valores peso 1, propósito 1.3)
$peso_total=0; $sx=0; $sy=0;
foreach ($valores_puntos as $p){ $sx += $p['x'] * 1.0; $sy += $p['y'] * 1.0; $peso_total += 1.0; }
foreach ($proposito_puntos as $p){ $sx += $p['x'] * 1.3; $sy += $p['y'] * 1.3; $peso_total += 1.3; }
$ejeX = ($peso_total>0) ? round($sx / $peso_total, 2) : 0;
$ejeY = ($peso_total>0) ? round($sy / $peso_total, 2) : 0;

// Punto del propósito puro (para pintar)
$proposito_punto = [
  'x' => round(($proposito_enfoque + $proposito_motivacion)/2, 2),
  'y' => round(($proposito_disrupcion + $proposito_inmersion)/2, 2),
];

// 5) Cultura por Hofstede (marca) → X/Y para el punto "Cultura (Hofstede)"
$valores_ideales_mapa = [];
foreach (['distancia_poder','individualismo','masculinidad','incertidumbre','largo_plazo','indulgencia'] as $k) {
  $valores_ideales_mapa[$k] = isset($cultura_ideal_mapa[$k]) ? round(((float)$cultura_ideal_mapa[$k]) / 5, 3) : 0.0;
}

$hof_individualismo = (float)($valores_ideales_mapa['individualismo']   ?? 0.0);
$hof_poder          = (float)($valores_ideales_mapa['distancia_poder'] ?? 0.0);
$hof_indulgencia    = (float)($valores_ideales_mapa['indulgencia']     ?? 0.0);
$hof_incertidumbre  = (float)($valores_ideales_mapa['incertidumbre']   ?? 0.0);

$hof_x = (0.70 * $hof_individualismo - 0.30 * $hof_poder) * 5.0;      // -5..+5
$hof_y = (0.60 * $hof_indulgencia   - 0.40 * $hof_incertidumbre) * 5.0;



$fk_equipo = detect_fk($conn);


// === Perfiles de alineación cultural (círculo equipo) ===
$perfiles = [];

try {
    
  $fk_equipo = detect_fk($conn);

$stmt_team = $conn->prepare("
    SELECT id, nombre_persona,
           hofstede_poder, hofstede_individualismo, hofstede_resultados,
           hofstede_incertidumbre, hofstede_largo_plazo, hofstede_espontaneidad
    FROM equipo
    WHERE $fk_equipo = ?
");
$stmt_team->bind_param("i", $company_id);

    $stmt_team->execute();
    $res_team = $stmt_team->get_result();


    // 2) Cultura ideal de esa Company (para comparar)
    
    
$stmt_ideal = $conn->prepare("
    SELECT 
        distancia_poder,
        individualismo,
        masculinidad,
        incertidumbre,
        largo_plazo,
        indulgencia,
        proposito_enfoque,
        proposito_motivacion,
        proposito_disrupcion,
        proposito_inmersion,
        proposito_tiempo
    FROM cultura_ideal 
    WHERE usuario_id = ?
");



    $stmt_ideal->bind_param("i", $company_id);
    $stmt_ideal->execute();
    $res_ideal    = $stmt_ideal->get_result();
    $cultura_ideal = $res_ideal->fetch_assoc() ?: [];
    $stmt_ideal->close();

    // 3) Normalizar cultura ideal (-5..+5 → -1..+1)
    $valores_ideales = [];
    foreach ($cultura_ideal as $clave => $valor) {
        $valores_ideales[$clave] = round(((float)$valor) / 5, 3);
    }

    // 4) Calcular % de alineación por persona (igual lógica que en desktop dashboard)
    while ($perfil = $res_team->fetch_assoc()) {
        $suma = 0;
        $dimensiones = 0;

        $mapeo = [
            'distancia_poder' => (float)$perfil['hofstede_poder'],
            'individualismo'  => (float)$perfil['hofstede_individualismo'],
            'masculinidad'    => (float)$perfil['hofstede_resultados'],
            'incertidumbre'   => (float)$perfil['hofstede_incertidumbre'],
            'largo_plazo'     => (float)$perfil['hofstede_largo_plazo'],
            'indulgencia'     => (float)$perfil['hofstede_espontaneidad'],
        ];

        foreach ($valores_ideales as $clave => $ideal_norm) {
            if (!array_key_exists($clave, $mapeo)) continue;

            $real = (float)$mapeo[$clave];

            // Distancia máxima posible entre real/ideal en [-1..+1] es 2
            $alineacion = 1 - (abs($real - $ideal_norm) / 2.0);
            $alineacion = max(0.0, min(1.0, $alineacion));

            $suma        += $alineacion;
            $dimensiones++;
        }

        $porcentaje = ($dimensiones > 0)
            ? round(($suma / $dimensiones) * 100, 1)
            : 0.0;

        $perfiles[] = [
            'id'         => (int)$perfil['id'],
            'nombre'     => $perfil['nombre_persona'],
            'alineacion' => $porcentaje,
        ];
    }

    // Ordenar de mayor a menor alineación
    usort($perfiles, function($a, $b){
        return $b['alineacion'] <=> $a['alineacion'];
    });

    $stmt_team->close();
} catch (\Throwable $e) {
    $perfiles = [];
}








/* ==========================================================
   RIESGOS DE FUGA (Tarjeta 1) + ÁREAS DE OPORTUNIDAD (Tarjeta 2)
   Basado en alineación cultural, motivación y distribución del equipo
   ========================================================== */

/**
 * Icono de batería según porcentaje (0–100).
 * Aquí lo interpretamos como "nivel de energía / retención":
 *  - 100%  → batería llena
 *  - 0%    → batería vacía
 */
function battery_icon_for_pct($pct){
  if ($pct <= 25)  return '/uploads/Battery-low.png';
  if ($pct <= 50)  return '/uploads/Battery-mid.png';
  if ($pct <= 75)  return '/uploads/Battery-high.png';
  return '/uploads/Battery-full.png';
}

/**
 * Niveles de riesgo de forma homogénea para equipo / personas
 */
function risk_level_from_score(float $score): array {
  if ($score >= 70) return ['Riesgo Alto',     '#ff009e']; // fucsia
  if ($score >= 40) return ['Riesgo Moderado', '#ffe600']; // amarillo
  return ['Riesgo Bajo', '#00c980'];                       // verde
}

function push_risk(array &$arr, string $titulo, string $desc, float $score, ?string $ctaHref = null) {
  [$nivel, $color] = risk_level_from_score($score);
  $arr[] = [
    'titulo'      => $titulo,
    'descripcion' => $desc,
    'score'       => round($score, 1),
    'nivel'       => $nivel,
    'color'       => $color,
    'cta'         => $ctaHref,
  ];
}

/**
 * Mapa rápido: id → % alineación (ya calculado arriba)
 */
$alineacion_por_persona = [];
foreach ($perfiles as $p) {
  $alineacion_por_persona[(int)$p['id']] = (float)$p['alineacion'];
}

/* -----------------------------------
   RIESGOS DE FUGA — por persona
----------------------------------- */
$riesgos_sorted = [];
$hay_equipo     = false;

try {
  // Trabajamos SIEMPRE con usuario_id = company_id (igual que en el dashboard de marca)

    $sqlRF = "
    SELECT 
      id,
      nombre_persona,
      COALESCE(cargo, '') AS cargo,
      hofstede_poder,
      hofstede_individualismo,
      hofstede_resultados,
      hofstede_incertidumbre,
      hofstede_largo_plazo,
      hofstede_espontaneidad,
      motivacion_pct,
      motivacion_porcentaje,
      motivacion_total,
      motivacion,
      motivacion_media
    FROM equipo
    WHERE $fk_equipo = ?

  ";



  $stmt_rf = $conn->prepare($sqlRF);
  $stmt_rf->bind_param("i", $company_id);
  $stmt_rf->execute();
  $res_rf = $stmt_rf->get_result();

  $cols_motiv = ['motivacion_pct','motivacion_porcentaje','motivacion_total','motivacion','motivacion_media'];

  while ($row = $res_rf->fetch_assoc()) {
    $hay_equipo = true;

    $id_equipo = (int)$row['id'];
    $nombre    = trim((string)($row['nombre_persona'] ?? ''));
    $cargo     = trim((string)($row['cargo'] ?? ''));

    // Alineación cultural individual (0–100) = ya calculada en $perfiles
    // Alineación cultural individual (0–100), recalculada igual que en $perfiles
    $mapeo_rf = [
      'distancia_poder'        => (float)($row['hofstede_poder'] ?? 0),
      'individualismo'         => (float)($row['hofstede_individualismo'] ?? 0),
      'orientacion_resultados' => (float)($row['hofstede_resultados'] ?? 0),
      'control_incertidumbre'  => (float)($row['hofstede_incertidumbre'] ?? 0),
      'orientacion_plazo'      => (float)($row['hofstede_largo_plazo'] ?? 0),
      'espontaneidad'          => (float)($row['hofstede_espontaneidad'] ?? 0),
    ];

    $suma_rf = 0.0;
    $dims_rf = 0;

    foreach ($valores_ideales as $clave_vi => $ideal_norm_vi) {
      if (!array_key_exists($clave_vi, $mapeo_rf)) {
        continue;
      }
      $real_norm_vi = (float)$mapeo_rf[$clave_vi];
      // misma fórmula que en la tarjeta de alineación
      $alineacion_dim = 1 - (abs($real_norm_vi - $ideal_norm_vi) / 2.0);

      if ($alineacion_dim < 0) $alineacion_dim = 0;
      if ($alineacion_dim > 1) $alineacion_dim = 1;

      $suma_rf += $alineacion_dim;
      $dims_rf++;
    }

    $ali = null;
    if ($dims_rf > 0) {
      $ali = round(($suma_rf / $dims_rf) * 100, 1);
    }

    if ($ali === null) {
    continue;
}


    // Motivación: buscamos la primera columna no nula
    $motiv = null;
    foreach ($cols_motiv as $cm) {
      if (array_key_exists($cm, $row) && $row[$cm] !== null) {
        $motiv = (float)$row[$cm];
        break;
      }
    }
    if ($motiv !== null && $motiv > 0 && $motiv <= 1.5) {
      $motiv *= 100; // por si vienen normalizados
    }
    if ($motiv !== null) {
      $motiv = max(0, min(100, $motiv));
    }

    // GAP cultural: cuanto más lejos de 100, más riesgo
    $gap_cultural = 100 - max(0, min(100, (float)$ali));

    // Riesgo por motivación: penalizamos motivaciones bajas
    $riesgo_motiv = 0.0;
    if ($motiv !== null) {
      if ($motiv < 40)      $riesgo_motiv = 30;
      elseif ($motiv < 60)  $riesgo_motiv = 20;
      elseif ($motiv < 80)  $riesgo_motiv = 10;
      else                  $riesgo_motiv = 0;
    }

    // Riesgo total (0–100) ponderando más el GAP cultural
    $riesgo_total = (0.7 * $gap_cultural) + (0.3 * $riesgo_motiv);
    $riesgo_total = max(0, min(100, $riesgo_total));

    // Nivel + color
    [$nivel, $nivel_color] = risk_level_from_score($riesgo_total);

    // Batería: usamos 100 - riesgo (más riesgo = batería más vacía)
    $battery_icon = battery_icon_for_pct(100 - $riesgo_total);

    // Iniciales para el avatar
    $parts    = preg_split('/\s+/', $nombre);
    $inicial1 = isset($parts[0][0]) ? mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8'), 'UTF-8') : '';
    $inicial2 = isset($parts[1][0]) ? mb_strtoupper(mb_substr($parts[1], 0, 1, 'UTF-8'), 'UTF-8') : '';
    $iniciales = $inicial1 . $inicial2;
    if ($iniciales === '') {
      $iniciales = '??';
    }

    $riesgos_sorted[] = [
      'id'           => $id_equipo,
      'nombre'       => $nombre,
      'cargo'        => $cargo,
      'alineacion'   => $ali,
      'motivacion'   => $motiv,
      'riesgo_total' => $riesgo_total,
      'nivel'        => $nivel,
      'nivel_color'  => $nivel_color,
      'battery_icon' => $battery_icon,
      'iniciales'    => $iniciales,
      'alerta'       => ($riesgo_total >= 70),
    ];
  }

  $stmt_rf->close();

  // Ordenamos de mayor a menor riesgo solo si hay equipo
  if ($hay_equipo) {
    usort($riesgos_sorted, fn($a,$b) => $b['riesgo_total'] <=> $a['riesgo_total']);
  } else {
    $riesgos_sorted = [];
  }

} catch (\Throwable $e) {
  $riesgos_sorted = [];
}



$metrics    = get_company_metrics($conn, $company_id);
$team_count = (int)$metrics['team_count'];
$align_pct  = $metrics['align_pct'];
$motiv_pct  = $metrics['motiv_pct'];
$motiv_lbl  = motivation_label($motiv_pct);




/* ------------ Enlaces profundos con firma reutilizada ------------ */
$URL_BRAND = 'a-desktop-dashboard-brand.php';
$URL_CPV   = 'a-cultura-proposito-valores.php';
$qbase = http_build_query([
  'company_id' => $company_id,
  'sig'        => $expected,
]);
$link_brand = $URL_BRAND . '?' . $qbase;
$link_cpv   = $URL_CPV   . '?' . $qbase;




/* -----------------------------------
   ÁREAS DE OPORTUNIDAD — nivel equipo
----------------------------------- */

$riesgos_equipo = [];

try {
  // 1) GAP cultural medio (ya tienes $align_pct como 0–100)
  if ($align_pct !== null) {
    $gap_global = max(0, min(100, 100 - (float)$align_pct));

    push_risk(
      $riesgos_equipo,
      'Desajuste cultural general',
      sprintf(
        'La alineación cultural media del equipo es <strong>%.1f%%</strong>. Entre más cerca esté de 100%%, más sincronizada está la cultura diaria con el propósito y los valores que definieron juntos.',
        (float)$align_pct
      ),
      $gap_global,
      $link_cpv
    );
  }

  // 2) Riesgo por motivación media del equipo
  if ($motiv_pct !== null) {
    $gap_motiv = max(0, min(100, 100 - (float)$motiv_pct));

    push_risk(
      $riesgos_equipo,
      'Motivación y energía del equipo',
      sprintf(
        'La motivación media del equipo es <strong>%.1f%%</strong>. Una motivación baja suele anticipar rotación, desenganche o bajo aprovechamiento del talento clave.',
        (float)$motiv_pct
      ),
      $gap_motiv,
      $link_brand
    );
  }

  // 3) Porcentaje de personas en zona de riesgo (alineación < 60%)
  $total_personas = count($perfiles);
  if ($total_personas > 0) {
    $en_riesgo = 0;
    foreach ($perfiles as $p) {
      if ((float)$p['alineacion'] < 60) {
        $en_riesgo++;
      }
    }
    $porc_riesgo = ($en_riesgo / $total_personas) * 100.0;

    push_risk(
      $riesgos_equipo,
      'Talento en zona de riesgo',
      sprintf(
        'El <strong>%.1f%%</strong> de las personas medidas está por debajo del 60%% de alineación cultural. Vale la pena revisar quiénes son y qué roles ocupan antes de que se convierta en fuga o ruido interno.',
        $porc_riesgo
      ),
      max(0, min(100, $porc_riesgo)),
      $link_brand
    );
  }

} catch (\Throwable $e) {
  $riesgos_equipo = [];
}












/* ------------ View variables ------------ */


$logo         = resolve_logo_url($company['logo'] ?? '');
$cname        = (string)$company['empresa'];
$ctype_raw    = (string)($company['cultura_empresa_tipo'] ?? '');
$ctype        = normalize_culture_type($ctype_raw);
$mgr          = trim(join(' ', array_filter([(string)($company['nombre'] ?? ''),(string)($company['apellido'] ?? '')])));




$mgr_email    = (string)($company['email'] ?? '');
$corp_email   = (string)($company['correo_oficial_comunicacion'] ?? '');
$pdf_analisis = (string)($company['pdf_analisis'] ?? '');
$pdf_valores  = (string)($company['pdf_valores'] ?? '');

/* ------------ Cargar VALORES desde valores_marca (titulos) ------------ */
$vals_list = [];
try {
  $stV = $conn->prepare("
    SELECT titulo
    FROM valores_marca
    WHERE usuario_id = ? AND titulo IS NOT NULL AND titulo <> ''
    ORDER BY id ASC
    LIMIT 24
  ");
  $stV->bind_param("i", $company_id);
  $stV->execute();
  $rsV = $stV->get_result();
  while ($rowV = $rsV->fetch_assoc()) {
    $t = trim((string)$rowV['titulo']);
    if ($t !== '' && !in_array($t, $vals_list, true)) $vals_list[] = $t;
  }
  $stV->close();
} catch (\Throwable $e) {
  // Si falla, deja $vals_list vacío (se mostrará "—")
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Overview cultural — <?= h($cname) ?></title>

<link rel="preconnect" href="https://use.typekit.net" crossorigin>
<link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
<style>
  :root {
  --c-primary:#012133;
  --c-secondary:#184656;
  --c-accent:#EF7F1B;
  --c-soft:#FFF5F0;
  --c-body:#474644;
  --c-bg:#FFFFFF;
  --shadow:0 6px 20px rgba(0,0,0,0.06);
  --radius:20px;
}

  *{box-sizing:border-box;}

  html,body{
    margin:0;
    min-height:100vh;
    background:var(--c-bg);
    color:var(--c-body);
    font-family:"gelica",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif;
  }

  img{max-width:100%;display:block;}
  a{color:inherit;text-decoration:none;}

  /* Header estilo Valírica (versión provider simple) */
  header {
    width:100%;
    background:var(--c-primary);
    color:var(--c-soft);
    padding:14px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    box-shadow:0 3px 12px rgba(0,0,0,0.08);
  }

  .nav-left {
    display:flex;
    align-items:center;
    gap:14px;
  }

  .brand-logo {
    width:40px;
    height:40px;
    border-radius:10px;
    object-fit:cover;
    background:#f4f4f4;
    box-shadow:0 2px 6px rgba(0,0,0,0.25);
  }

  .title {
    display:flex;
    flex-direction:column;
  }

  .title h1 {
    margin:0;
    font-size:clamp(18px,2.4vw,24px);
    color:var(--c-soft);
    letter-spacing:-0.3px;
    line-height:1.1;
  }

  .title span {
    font-size:13px;
    color:var(--c-soft);
    opacity:0.8;
  }

  @media (max-width: 680px){
    header {
      padding:12px 16px;
      flex-wrap:wrap;
      row-gap:6px;
    }
  }




  .wrap{
    max-width:1200px;
    margin:0 auto;
    padding:24px;
  }

  /* Pequeño texto contextual arriba de la ficha */
  .page-intro{
    font-size:13px;
    color:#6d6d6d;
    margin:8px 0 16px;
  }

  /* Tarjeta principal de la marca (logo + propósito + valores + contacto) */
  .brand-card{
    display:grid;
    grid-template-columns:140px 1fr 320px;
    gap:16px;
    border:1px solid #eee;
    border-radius:16px;
    background:#fff;
    box-shadow:var(--shadow);
    padding:24px;
  }
  @media (max-width:980px){
    .brand-card{
      grid-template-columns:120px 1fr;
      grid-template-rows:auto auto;
    }
    .contact{
      grid-column:1 / -1;
      border-left:none;
      border-top:1px dashed #eee;
      margin-top:12px;
      padding-left:0;
      padding-top:12px;
    }
  }
  @media (max-width:640px){
    .brand-card{
      grid-template-columns:1fr;
    }
    .logo-box{
      margin:0 auto 10px;
    }
  }

  .logo-box{
    width:140px;
    height:140px;
    border:1px solid #eee;
    border-radius:12px;
    background:#fafafa;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
  }
  .logo-box img{
    max-width:94%;
    max-height:94%;
    object-fit:contain;
  }

  .brand-main .name{
    font-size:20px;
    font-weight:800;
    color:var(--c-secondary);
  }

  .chips{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:6px;
  }
  .chip{
    font-size:11px;
    font-weight:700;
    border:1px solid rgba(1,33,51,0.08);
    background:#FFF5F0;
    border-radius:999px;
    padding:4px 8px;
    color:#184656;
    line-height:1.2;
  }

  .section-title{
    margin:20px 0 8px;
    font-weight:900;
    color:var(--c-primary);
    font-size:14px;
  }
  .muted{
    color:#666;
    font-size:13px;
    line-height:1.5;
  }

  .contact{
    border-left:1px dashed #eee;
    padding-left:16px;
  }
  .contact .line{
    font-size:14px;
    margin:6px 0;
  }

  /* Tags suaves tipo dashboard_brand (Dimensiones, Cultura, etc.) */
  .vl-tags{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin:4px 0 10px;
  }
  .vl-tag{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:3px 9px;
    border-radius:9999px;
    font-size:11px;
    font-weight:600;
    background:#FFF5F0;
    border:1px solid rgba(1,33,51,0.08);
    color:var(--c-secondary);
  }
  .vl-tag::before{
    content:"•";
    font-weight:700;
    opacity:.6;
  }

  /* Bloques KPI alineación / motivación */
  .kpis{
    display:grid;
    grid-template-columns:200px 1fr;
    gap:14px;
    margin-top:20px;
  }
  @media (max-width:720px){
    .kpis{
      grid-template-columns:1fr;
    }
  }
  .kpi-block{
    background:#fff;
    border:1px solid #eee;
    border-radius:12px;
    box-shadow:var(--shadow);
    padding:14px;
  }
  .kpi-label{
    font-size:12px;
    color:#6d6d6d;
    margin-bottom:6px;
  }
  .kpi-strong{
    font-size:22px;
    font-weight:900;
    color:var(--c-primary);
  }
  .kpi-meta{
    margin-top:6px;
    font-size:12px;
    color:#6d6d6d;
  }
  .pill{
    font-size:11px;
    padding:4px 8px;
    border-radius:999px;
    background:var(--c-soft);
    border:1px solid rgba(1,33,51,.06);
  }

  /* Anillo de alineación (ring) */
  .ring{
    --size:120px;
    --thick:12px;
    --pct: <?= (int)($align_pct ?? 0); ?>;
    width:var(--size);
    height:var(--size);
    border-radius:50%;
    background:conic-gradient(var(--c-accent) calc(var(--pct) * 1%), #eee 0);
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto;
    position:relative;
  }
  .ring::after{
    content:"";
    position:absolute;
    inset:calc(var(--thick));
    background:#fff;
    border-radius:50%;
  }
  .ring-wrap{
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  .ring-value{
    position:relative;
    z-index:1;
    font-weight:900;
    font-size:22px;
    color:var(--c-secondary);
  }

  /* Grid inferior: resumen CPV + accesos rápidos */
  .grid-two{
    display:grid;
    grid-template-columns:1.4fr 1fr;
    gap:16px;
    margin-top:10px;
  }
  @media (max-width:960px){
    .grid-two{
      grid-template-columns:1fr;
    }
  }

  .card{
  background:#fff;
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:24px;
  border:1px solid #f1f1f1;
  min-height:180px;
  display:flex;
  flex-direction:column;
  gap:10px;
  transition:.2s ease;
  text-align:justify;
  margin-bottom:24px;
}
.card:hover{
  transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
.card h3{
  color:var(--c-secondary);
  font-size:clamp(16px,2vw,20px);
}







  /* === Lista de Riesgos de fuga (personas) === */
  .rf-list{
    list-style:none;
    margin:8px 0 0;
    padding:0;
    display:flex;
    flex-direction:column;
    gap:10px;
  }
  .rf-item{
    display:grid;
    grid-template-columns:1fr auto auto; /* izquierda ocupa, centro chip, derecha acciones */
    gap:14px;
    align-items:center;
    width:100%;
  }

  .rf-left{
    display:grid;
    grid-template-columns:40px auto;
    gap:12px;
    min-width:0;
    align-items:center;
  }
  .rf-avatar{
    width:40px;
    height:40px;
    border-radius:9999px;
    background:var(--c-accent);
    color:#fff;
    font-weight:700;
    font-size:15px;
    display:grid;
    place-items:center;
    box-shadow:0 1px 3px rgba(0,0,0,0.12);
  }
  .rf-id{
    min-width:0;
  }
  .rf-name{
    font-weight:700;
    font-size:13px;
    color:var(--c-secondary);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .rf-role{
    font-weight:400;
    font-size:12px;
    color:#6a6a6a;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .rf-center{
    display:inline-flex;
    align-items:center;
    gap:8px;
    justify-self:center;
  }
  .rf-right{
    display:inline-flex;
    align-items:center;
    gap:10px;
    justify-self:end;
  }

  .rf-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:9999px;
    line-height:1;
    font-size:12px;
    border:1px solid rgba(0,0,0,0.08);
    background:#FFF5F0;
    font-weight:600;
  }
  .rf-alert{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.06em;
    padding:2px 6px;
    border-radius:9999px;
    background:rgba(255,0,0,0.06);
    color:#b00020;
    border:1px solid rgba(176,0,32,0.18);
  }

  .rf-battery{
    height:18px;
    width:auto;
    display:block;
    image-rendering:-webkit-optimize-contrast;
  }
  .rf-battery-lg{
    height:20px;
  }

  .rf-btn{
    font-size:11px;
    font-weight:700;
    padding:6px 10px;
    border-radius:9999px;
    border:1px solid rgba(1,33,51,0.12);
    background:#fff;
    color:var(--c-primary);
    text-decoration:none;
    white-space:nowrap;
  }

  #card-riesgos-fuga .rf-list{
    max-height:260px;
    overflow-y:auto;
    padding-right:4px;
  }

  /* === Lista de Áreas de oportunidad (equipo) === */
  .gr-list{
    list-style:none;
    margin:8px 0 0;
    padding:0;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  .gr-item{
    display:grid;
    grid-template-columns:1fr auto auto;
    gap:14px;
    align-items:center;
  }
  .gr-left{
    display:grid;
    grid-template-rows:auto auto;
    gap:4px;
    min-width:0;
  }
  .gr-title{
    font-weight:700;
    color:var(--c-secondary);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .gr-desc{
    font-size:12px;
    color:#6d6d6d;
    line-height:1.5;
  }
  .gr-center{
    justify-self:center;
  }
  .gr-right{
    display:inline-flex;
    justify-self:end;
    align-items:center;
  }

  .gr-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:9999px;
    line-height:1;
    font-size:12px;
    border:1px solid rgba(0,0,0,0.08);
    background:#FFF5F0;
    font-weight:600;
  }
  .gr-btn{
    font-size:11px;
    font-weight:700;
    padding:6px 10px;
    border-radius:9999px;
    border:1px solid rgba(1,33,51,0.12);
    background:#fff;
    color:var(--c-primary);
    text-decoration:none;
    white-space:nowrap;
  }

  #card-riesgos-equipo .gr-list{
    max-height:260px;
    overflow-y:auto;
    padding-right:4px;
  }

  @media (max-width: 880px){
    .rf-item,
    .gr-item{
      grid-template-columns:1fr;
      align-items:flex-start;
    }
    .rf-right,
    .gr-right{
      justify-self:flex-start;
    }
  }


.full-width-cards{
  margin-top:10px;
  display:flex;
  flex-direction:column;
  gap:16px;
}



  .footer-links{
    display:flex;
    gap:10px;
    justify-content:flex-start;
    margin-top:8px;
    flex-wrap:wrap;
  }
  .btn{
    background:var(--c-primary);
    color:#fff;
    border:0;
    border-radius:10px;
    padding:10px 12px;
    font-weight:800;
    cursor:pointer;
    font-size:13px;
    display:inline-flex;
    align-items:center;
    gap:6px;
  }
  .btn.secondary{
    background:var(--c-accent);
  }
  
  
 
   /* ==== Separación vertical entre bloques principales ==== */
  .brand-card,
  .kpis,
  .grid-two,
  .card {
    margin-top:10px;
    margin-bottom:10px;
  }

  /* Ajuste fino para que el primer bloque no quede demasiado abajo */
  .wrap > .brand-card:first-of-type {
    margin-top:16px;
  }

 
  
  
  
  
  
  
   /* Columna derecha con cards en stack (alineación + accesos) */
  .stack-col{
    display:flex;
    flex-direction:column;
    gap:16px;
  }

  /* Círculo de alineación – tamaño compacto */
  .alignment-wrap {
    position: relative;
    width: 100%;
    max-width: 100%;
    aspect-ratio: 1 / 1;
    min-height: 220px;  /* Más pequeño que antes */
  }
  .alignment-canvas {
    width: 100%;
    height: 100%;
    display: block;
    border-radius: 12px;
    background:#fff;
  }

  .vl-tooltip {
    position: absolute;
    pointer-events: none;
    background: #012133;
    color: #fff;
    font-family: "gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    line-height: 1.35;
    padding: 8px 10px;
    border-radius: 10px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    transform: translate(-50%, -120%);
    white-space: nowrap;
    opacity: 0;
    transition: opacity .12s ease;
    z-index: 2;
    border: 1px solid rgba(255,255,255,0.08);
  }

  @media (max-width:960px){
    .stack-col{
      margin-top:16px;
    }
  }

  
  
  
  
  
  /* =======================
   CUADRANTES CULTURALES
======================== */
.culture-quadrants {
  margin-top:10px;
  padding:20px;
  border-radius:16px;
  background:#fff;
  border:1px solid #eee;
  box-shadow:var(--shadow);
}

.cq-wrap {
  width:100%;
  max-width:520px;
  margin:0 auto;
}

#cqCanvas {
  width:100%;
  height:auto;
  aspect-ratio:1 / 1;
}

.cq-legend {
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  margin-top:16px;
  font-size:12px;
  color:#184656;
}

.cq-legend .dot {
  width:10px;
  height:10px;
  border-radius:50%;
  display:inline-block;
  margin-right:4px;
}

.dot-real { background:#184656; }
.dot-ideal { background:#EF7F1B; }
.dot-purpose { background:#6a00ff; }
.dot-values { background:#00a884; }
.dot-type { background:#f54291; }




  
  
  
</style>

</head>
<body>

<header>
  <div class="nav-left">
    <img
      class="brand-logo"
      src="<?= h($provider_logo !== '' ? resolve_logo_url($provider_logo) : 'https://app.valirica.com/uploads/logo-192.png'); ?>"
      alt="Logo de <?= h($provider_name) ?>"
    >
    <div class="title">
      <h1><?= h($provider_name) ?></h1>
      <span>Dashboard de administración de clientes</span>
    </div>
  </div>
  
   <div class="nav-right">
    <a href="https://app.valirica.com/a-desktop-dashboard-brand.php" class="go-dashboard-btn">
      Regresar a tu Dashboard
    </a>
  </div>
</header>

<div class="wrap">

    


 



  <!-- Tarjeta principal de la marca -->
  <section class="brand-card">
    <div class="logo-box">
      <img src="<?= h($logo) ?>" alt="Logo de <?= h($cname) ?>"
           onerror="this.onerror=null;this.src='https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';">
    </div>

    <div class="brand-main">
      <div class="name"><?= h($cname) ?></div>

      <div class="chips">
        <?php if ($ctype): ?>
          <span class="chip" title="Tipo de cultura"><?= h($ctype) ?></span>
        <?php endif; ?>
        <?php if ($estilo): ?>
          <span class="chip" title="Estilo de aprendizaje/comunicación"><?= h(ucfirst(mb_strtolower($estilo, 'UTF-8'))) ?></span>
        <?php endif; ?>
        <?php if ($ubicacion): ?>
          <span class="chip" title="País de operación"><?= h($ubicacion) ?></span>
        <?php endif; ?>
      </div>

      <div class="section-title">Propósito</div>
      <div class="muted"><?= $prop ? nl2br(h($prop)) : '—' ?></div>

      <div class="section-title" style="margin-top:12px">Valores</div>
      <?php if ($vals_list): ?>
        <div class="chips values">
          <?php foreach ($vals_list as $v): ?>
            <span class="chip" title="Valor de marca"><?= h($v) ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="muted">—</div>
      <?php endif; ?>
    </div>

    <div class="contact">
      <div class="section-title">Contacto</div>
      <div class="line"><strong>Responsable:</strong> <?= $mgr ? h($mgr) : '—' ?></div>
      <div class="line"><strong>Email responsable:</strong> <?= $mgr_email ? h($mgr_email) : '—' ?></div>
      <?php if ($corp_email): ?>
        <div class="line"><strong>Correo oficial:</strong> <?= h($corp_email) ?></div>
      <?php endif; ?>

      <?php if ($pdf_analisis || $pdf_valores): ?>
        <div class="section-title" style="margin-top:14px;">Documentos</div>
        <?php if ($pdf_analisis): ?>
          <div class="line"><a class="pill" href="<?= h($pdf_analisis) ?>" target="_blank" rel="noopener">PDF Análisis</a></div>
        <?php endif; ?>
        <?php if ($pdf_valores): ?>
          <div class="line"><a class="pill" href="<?= h($pdf_valores) ?>" target="_blank" rel="noopener">PDF Valores</a></div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="section-title" style="margin-top:14px;">Equipo</div>
      <div class="line">
        <span class="pill">Miembros inscritos: <strong><?= (int)$team_count ?></strong></span>
      </div>
    </div>
  </section>



  
  
  <p style="margin-top:25px;" class="page-intro">
  Contexto cultural de <strong><?= h($cname) ?></strong>.
</p>
  


  
  

  <!-- Cultura / Propósito / Valores (resumen) -->
  <!-- Cultura / Propósito / Valores + Alineación visual + Accesos -->
<section class="grid-two">
  

<!-- =======================
     MAPA DE ORIENTACIÓN CULTURAL
======================== -->
<div class="card culture-quadrants">
  <h3>Mapa de orientación cultural</h3>

  <div class="vl-tags" style="margin-bottom:16px;">
    <span class="vl-tag">Propósito</span>
    <span class="vl-tag">Valores</span>
    <span class="vl-tag">Equipo</span>
  </div>

  <p class="muted" style="margin-bottom:16px;">
  Resumen visual del <strong>propósito</strong>, los <strong>valores</strong> y la
  <strong>cultura real del equipo</strong>.
</p>


  <div class="cq-wrap">
  <canvas id="cqCanvas"></canvas>
</div>



</div>




  <!-- Columna derecha: alineación visual + accesos rápidos en stack -->
    <div class="stack-col">
    <!-- Tarjeta: círculo de alineación cultural del equipo -->
    <div class="card">
      <h3>Alineación cultural del equipo</h3>
      <p class="muted" style="margin-top:6px;margin-bottom:16px;">
        Cada círculo representa a una persona del equipo. Entre más cerca del centro,
        mayor es su <strong>alineación cultural</strong> con la marca y su impacto
        en la cultura del cliente.
      </p>
      <div class="alignment-wrap">
        <canvas id="vlAlignmentCanvas" class="alignment-canvas"></canvas>
        <div id="vlTooltip" class="vl-tooltip"></div>
      </div>
    </div>

    

    <!-- Tarjeta: accesos rápidos -->
    <div class="card">
      <h3>Accesos rápidos</h3>
      <div class="footer-links">
        <a class="btn" href="<?= h($link_brand) ?>">Dashboard de marca</a>
        <a class="btn secondary" href="<?= h($link_cpv) ?>">Cultura · Propósito · Valores</a>
      </div>
    </div>
  </div>

  
  
  
</section>








<section class="full-width-cards">
  <!-- Tarjeta: Riesgos de fuga -->
  <div class="card" id="card-riesgos-fuga">
    <h3>Riesgos de fuga</h3>

    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Posible fuga de talentos</span>
      <span class="vl-tag">Personas clave a cuidar</span>
    </div>

    <p class="muted" style="margin-bottom:16px;">
      Vista rápida de las personas con mayor <strong>riesgo de fuga</strong>,
      combinando su alineación cultural con su nivel de motivación.
    </p>

    <?php if (empty($riesgos_sorted)): ?>
      <p class="muted" style="opacity:.75;">Todavía no hay datos suficientes para calcular riesgos de fuga.</p>
    <?php else: ?>
      <ul class="rf-list">
        <?php foreach ($riesgos_sorted as $r): ?>
          <li class="rf-item">
            <!-- IZQUIERDA: avatar + nombre + cargo -->
            <div class="rf-left">
              <div class="rf-avatar" aria-hidden="true"><?= h($r['iniciales']) ?></div>
              <div class="rf-id">
                <div class="rf-name"><?= h($r['nombre']) ?></div>
                <div class="rf-role"><?= h($r['cargo']) ?></div>
              </div>
            </div>

            <!-- CENTRO: chip de nivel -->
            <div class="rf-center">
              <span class="rf-chip" style="border-color:<?= h($r['nivel_color']) ?>;color:<?= h($r['nivel_color']) ?>;">
                <?= h($r['nivel']) ?> · <?= (float)$r['riesgo_total'] ?>%
              </span>
              <?php if (!empty($r['alerta'])): ?>
                <span class="rf-alert">Alerta</span>
              <?php endif; ?>
            </div>

            <!-- DERECHA: batería + enlace -->
            <div class="rf-right">
              <img 
                src="<?= h($r['battery_icon']) ?>" 
                alt="Nivel de energía de permanencia" 
                class="rf-battery rf-battery-lg"
              >
              <a href="<?= h($link_brand) ?>#empleado-<?= (int)$r['id'] ?>" class="rf-btn">
                Ver detalle
              </a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Tarjeta: Áreas de oportunidad -->
  <div class="card" id="card-riesgos-equipo">
    <h3>Áreas de oportunidad</h3>

    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Desincronías clave</span>
      <span class="vl-tag">Prioriza acciones</span>
    </div>

    <p class="muted" style="margin-bottom:16px;">
      Resumen de las <strong>desalineaciones más relevantes</strong> entre la cultura ideal,
      la motivación del equipo y la distribución del talento en zona de riesgo.
    </p>

    <?php if (empty($riesgos_equipo)): ?>
      <p class="muted" style="opacity:.75;">Todavía no hay datos consolidados del equipo para mostrar áreas de oportunidad.</p>
    <?php else: ?>
      <ul class="gr-list">
        <?php foreach ($riesgos_equipo as $g): ?>
          <li class="gr-item">
            <div class="gr-left">
              <div class="gr-title"><?= h($g['titulo']) ?></div>
              <div class="gr-desc"><?= $g['descripcion'] ?></div>
            </div>

            <div class="gr-center">
              <span class="gr-chip" style="border-color:<?= h($g['color']) ?>;color:<?= h($g['color']) ?>;">
                <?= h($g['nivel']) ?> — <?= (float)$g['score'] ?>%
              </span>
            </div>

            <div class="gr-right">
              <?php if (!empty($g['cta'])): ?>
                <a href="<?= h($g['cta']) ?>" class="gr-btn">Ver detalle</a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>







  
    </div>

</div>
























<script>
  // Datos desde PHP: mismos perfiles que en desktop dashboard
  const VL_PERFILES = <?php
    echo json_encode($perfiles, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
  ?> || [];

  (function initAlignmentCircle(){
    const canvas  = document.getElementById("vlAlignmentCanvas");
    const tooltip = document.getElementById("vlTooltip");

    if (!canvas || !tooltip) return;

    const ctx = canvas.getContext("2d");
    let points = [];

    function initials(name) {
      const parts = String(name || "").trim().split(/\s+/);
      const a = (parts[0] || "").charAt(0).toUpperCase();
      const b = (parts[1] || "").charAt(0).toUpperCase();
      return (a + b) || (a || "?");
    }

    function pctToRadius(pct, maxR) {
      const p = Math.max(0, Math.min(100, Number(pct) || 0));
      // 100% → centro, 0% → borde
      return ((100 - p) / 100) * maxR;
    }

    function draw() {
      const rect = canvas.getBoundingClientRect();
      const W = rect.width || 280;
      const H = rect.height || 280;

      canvas.width  = W;
      canvas.height = H;

      const cx = W / 2;
      const cy = H / 2;
      const R  = Math.min(cx, cy) - 24;

      ctx.clearRect(0, 0, W, H);

      // Anillos de referencia (25 / 50 / 75 / 100)
      const steps = [25, 50, 75, 100];
      steps.forEach((step, idx) => {
        const r = (R * step) / 100;
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.strokeStyle = idx === steps.length - 1 ? "#184656" : "rgba(0,0,0,0.06)";
        ctx.lineWidth = idx === steps.length - 1 ? 2 : 1;
        ctx.stroke();
      });

      // Ejes cruzados suaves
      ctx.beginPath();
      ctx.moveTo(cx - R, cy);
      ctx.lineTo(cx + R, cy);
      ctx.moveTo(cx, cy - R);
      ctx.lineTo(cx, cy + R);
      ctx.strokeStyle = "rgba(0,0,0,0.06)";
      ctx.lineWidth = 1;
      ctx.stroke();

      // Puntos de personas (golden angle para distribuirlos)
      points = [];
      const GOLDEN_ANGLE = 137.508 * Math.PI / 180;
      let angle = 0;

      VL_PERFILES.forEach((p) => {
        const pct = typeof p.alineacion === "number" ? p.alineacion : 0;
        const radius = pctToRadius(pct, R);
        const a = angle;

        const x = cx + Math.cos(a) * radius;
        const y = cy + Math.sin(a) * radius;
        angle += GOLDEN_ANGLE;

        const rDot = 11;

        // Avatar
        ctx.beginPath();
        ctx.arc(x, y, rDot, 0, Math.PI * 2);
        ctx.fillStyle = "#f54291";
        ctx.fill();

        // Borde sutil blanco
        ctx.lineWidth = 2;
        ctx.strokeStyle = "rgba(255,255,255,0.9)";
        ctx.stroke();

        // Iniciales
        ctx.fillStyle = "#ffffff";
        ctx.font = "11px \"gelica\", system-ui, -apple-system, sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(initials(p.nombre), x, y);

        points.push({ x, y, r: rDot + 4, data: p });
      });

      // Si no hay perfiles, mostramos un mensaje suave en el centro
      if (!Array.isArray(VL_PERFILES) || VL_PERFILES.length === 0) {
        ctx.fillStyle = "#666";
        ctx.font = "13px \"gelica\", system-ui, -apple-system, sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("Sin datos de equipo todavía", cx, cy);
      }
    }

    function handleMove(ev) {
      if (!points.length) return;

      const rect = canvas.getBoundingClientRect();
      const x = ev.clientX - rect.left;
      const y = ev.clientY - rect.top;

      let found = null;
      for (const pt of points) {
        const dx = x - pt.x;
        const dy = y - pt.y;
        if (Math.sqrt(dx*dx + dy*dy) <= pt.r) {
          found = pt;
          break;
        }
      }

      if (!found) {
        tooltip.style.opacity = "0";
        return;
      }

      tooltip.style.opacity = "1";
      tooltip.style.left = x + "px";
      tooltip.style.top  = y + "px";

      const nombre = found.data.nombre || "Sin nombre";
      const pct    = typeof found.data.alineacion === "number"
                       ? Math.round(found.data.alineacion)
                       : 0;

      tooltip.textContent = nombre + " · " + pct + "% de alineación";
    }

    function hideTooltip() {
      tooltip.style.opacity = "0";
    }

    canvas.addEventListener("mousemove", handleMove);
    canvas.addEventListener("mouseleave", hideTooltip);
    window.addEventListener("resize", draw);

    draw();
  })();
</script>









<!-- Chart.js + plugin de anotaciones -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1"></script>
<script>
  Chart.register(window['chartjs-plugin-annotation']);
</script>

<script>
(function(){
  const ctx = document.getElementById('cqCanvas');
  if(!ctx) return;


  const x = <?= json_encode($ejeX) ?>;
  const y = <?= json_encode($ejeY) ?>;

  // Coordenadas Hofstede (proyección -5..+5) calculadas en PHP
  const hofX = <?= json_encode($hof_x) ?>;
  const hofY = <?= json_encode($hof_y) ?>;

  const valoresData   = <?= json_encode($valores_puntos, JSON_UNESCAPED_UNICODE) ?>;
  const propositoData = <?= json_encode($proposito_punto, JSON_UNESCAPED_UNICODE) ?>;
  const equipoData    = <?= json_encode($equipo_puntos,  JSON_UNESCAPED_UNICODE) ?>;

let cuadrante = -1;
if      (hofX < 0 && hofY > 0)  cuadrante = 0; // Cultura Colaborativa
else if (hofX >= 0 && hofY > 0) cuadrante = 1; // Cultura Ágil
else if (hofX < 0 && hofY <= 0) cuadrante = 2; // Cultura Estructurada
else if (hofX >= 0 && hofY <= 0)cuadrante = 3; // Orientada a Resultados


  const quadBg = ['transparent','transparent','transparent','transparent'];
  if (cuadrante !== -1) quadBg[cuadrante] = 'rgba(239,127,27,0.10)'; // naranja suave

  new Chart(ctx, {
    type: 'scatter',
    data: {
      datasets: [
  { // Punto ideal ponderado (propósito + valores)
    label: 'Cultura ideal (promedio)',
    data: [{ x: x, y: y }],
    pointRadius: 10,
    pointBorderWidth: 2,
    pointBackgroundColor: '#EF7F1B',  // dot-ideal
    pointBorderColor: '#184656'
  },
  {
    label: 'Propósito',
    data: [propositoData],
    pointRadius: 10,
    pointBorderWidth: 2,
    pointBackgroundColor: '#6a00ff',  // dot-purpose
    pointBorderColor: '#6a00ff'
  },
  {
    label: 'Valores',
    data: valoresData,
    pointRadius: 6,
    pointBackgroundColor: '#00a884',  // dot-values
    pointBorderColor: '#00a884'
  },
  {
    label: 'Cultura (Hofstede)',
    data: [{ x: hofX, y: hofY }],
    pointRadius: 9,
    pointBorderWidth: 2,
    pointBackgroundColor: '#184656',  // dot-real
    pointBorderColor: '#184656'
  },
  {
    label: 'Equipo',
    data: equipoData,
    pointRadius: 4,
    pointHoverRadius: 6,
    pointBackgroundColor: '#f54291',  // dot-type
    pointBorderColor: '#f54291',
    showLine: false
  }
]

    },
    options: {
      responsive:true,
      maintainAspectRatio:false,
      scales: {
        x: {
          min:-5, max:5,
          title:{ display:true, text:'← Interno | Externo →', color:'#184656', font:{weight:'700'} },
          ticks:{ display:false },
          grid:{
            drawTicks:false,
            borderColor:'transparent',
            color: ctx => ctx.tick.value===0 ? '#184656' : 'transparent',
            lineWidth: ctx => ctx.tick.value===0 ? 2 : 0
          }
        },
        y: {
          min:-5, max:5,
          title:{ display:true, text:'← Controlado | Flexible →', color:'#184656', font:{weight:'700'} },
          ticks:{ display:false },
          grid:{
            drawTicks:false,
            borderColor:'transparent',
            color: ctx => ctx.tick.value===0 ? '#184656' : 'transparent',
            lineWidth: ctx => ctx.tick.value===0 ? 2 : 0
          }
        }
      },
      plugins: {
        legend: { display:true },
annotation: {
  annotations: {
    q1: { // Cultura Colaborativa
      type: 'box',
      xMin: -5, xMax: 0,
      yMin:  0, yMax: 5,
      backgroundColor: quadBg[0],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Cultura Colaborativa',
        position: 'center',
        color: '#184656',
        font: {
          size: 11,
          weight: '700'
        },
        padding: 4,
        backgroundColor: 'rgba(255,255,255,0.8)'
      }
    },
    q2: { // Cultura Ágil
      type: 'box',
      xMin: 0, xMax: 5,
      yMin: 0, yMax: 5,
      backgroundColor: quadBg[1],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Cultura Ágil',
        position: 'center',
        color: '#184656',
        font: {
          size: 11,
          weight: '700'
        },
        padding: 4,
        backgroundColor: 'rgba(255,255,255,0.8)'
      }
    },
    q3: { // Cultura Estructurada
      type: 'box',
      xMin: -5, xMax: 0,
      yMin: -5, yMax: 0,
      backgroundColor: quadBg[2],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Cultura Estructurada',
        position: 'center',
        color: '#184656',
        font: {
          size: 11,
          weight: '700'
        },
        padding: 4,
        backgroundColor: 'rgba(255,255,255,0.8)'
      }
    },
    q4: { // Orientada a Resultados
      type: 'box',
      xMin: 0, xMax: 5,
      yMin: -5, yMax: 0,
      backgroundColor: quadBg[3],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Orientada a Resultados',
        position: 'center',
        color: '#184656',
        font: {
          size: 11,
          weight: '700'
        },
        padding: 4,
        backgroundColor: 'rgba(255,255,255,0.8)'
      }
    }
  }
}
,

        tooltip: {
          callbacks: {
            label: (ctx) => {
              const d = ctx.raw;
              const lbl = ctx.dataset.label || '';
              if (d && d.label) return `${d.label} (${d.x}, ${d.y})`;
              return `${lbl ? lbl+': ' : ''}(${d.x}, ${d.y})`;
            }
          }
        }
      }
    }
  });
})();
</script>









</body>
</html>
