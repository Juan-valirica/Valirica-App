<?php
/* ==========================================================
   a-provider_companies.php
   Vista: PROVEEDOR → Companies vinculadas (con KPIs)
   Muestra: Logo, nombre, % alineación (radial), # miembros, motivación promedio
   Autor: Valírica (CTO Mode)
   ========================================================== */

session_start();
require 'config.php';
require_once 'auth_scope.php';




/* ---------------- Robustez mysqli en errores ---------------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------------- Helpers ---------------- */
if (!function_exists('h')) {
  function h($v){
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
  }
}









/* ---------------- Auth (sólo proveedores) ---------------- */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$viewer_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT empresa, rol, logo FROM usuarios WHERE id = ? LIMIT 1");

$stmt->bind_param("i", $viewer_id);
$stmt->execute();
$user = stmt_get_result($stmt)->fetch_assoc() ?: [];
$stmt->close();

// Normaliza y propaga el rol a la sesión para que las páginas destino lo lean
$_SESSION['role'] = strtolower((string)($user['rol'] ?? ''));

if (!$user || strcasecmp((string)$user['rol'], 'provider') !== 0) {
  http_response_code(403);
  echo "<h1 style='font-family:sans-serif;color:#012133;'>403 — Solo para proveedores</h1>";
  exit;
}

// Nombre del provider (para header, título de página, etc.)
$provider_name = (string)($user['empresa'] ?? 'Proveedor');
$provider_logo = (string)($user['logo'] ?? '');


// =========================
// Contexto para el header
// =========================

// Datos del usuario loggeado (viewer) que leerá a-header-desktop-brand.php
$viewer_empresa      = $provider_name;
$viewer_logo         = '';          // si algún día guardas logo del provider, lo cargas aquí
$viewer_cultura_tipo = '';          // aquí no aplica cultura de empresa
$viewer_rol          = 'provider';

// En esta vista NO hay una empresa concreta seleccionada,
// pero definimos estas variables para evitar "undefined variable"
$usuario_id           = 0;
$empresa              = $provider_name;  // aparecerá en el header como título
$logo                 = '';
$cultura_empresa_tipo = '';

// KPIs neutros (en esta vista los vamos a ocultar desde el header)
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











function resolve_logo_url(?string $path): string {
  $valiricaDefault = 'https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';
  $p = trim((string)$path);

  if ($p === '') return $valiricaDefault;
  if (preg_match('~^https?://~i', $p)) return $p;            // URL absoluta
  if (strpos($p, '//') === 0) return 'https:' . $p;          // protocolo relativo
  if ($p[0] === '/') return 'https://app.valirica.com' . $p; // raíz
  if (stripos($p, 'uploads/') === 0) return 'https://app.valirica.com/' . $p;

  return 'https://app.valirica.com/uploads/' . $p;           // por defecto
}

/** Etiqueta cualitativa para motivación */
function motivation_label(?int $pct): string {
  if ($pct === null) return 'Sin datos';
  if     ($pct >= 75) return 'Alta';
  elseif ($pct >= 45) return 'Media';
  elseif ($pct > 0)   return 'Baja';
  return 'Sin datos';
}

/** Clase de chip y color según tipo de cultura (placeholder para futuras variantes) */
function culture_chip_class(string $ctype): string {
  return 'chip-ctype';
}

/**
 * Ejecuta AVG sobre una expresión y devuelve float|null
 */
function try_avg(mysqli $conn, string $fk, int $company_id, string $expr, bool $filter_active = true): ?float {
  $whereEstado = $filter_active ? " AND (estado IS NULL OR estado='activo')" : "";
  $sql = "SELECT AVG($expr) AS v FROM equipo WHERE `$fk` = ? $whereEstado";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $company_id);
  $stmt->execute();
  $row = stmt_get_result($stmt)->fetch_assoc();
  $stmt->close();
  return isset($row['v']) && $row['v'] !== null ? (float)$row['v'] : null;
}

/**
 * Determina la primera FK válida en equipo entre una lista candidata.
 */
function detect_fk(mysqli $conn, array $candidates = ['empresa_id','company_id','usuario_id']): ?string {
  foreach ($candidates as $fk) {
    try {
      // Probar con un COUNT rápido (no importa el resultado, sólo que no falle)
      $test = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$fk` IS NOT NULL LIMIT 1");
      $test->execute();
      $test->close();
      return $fk;
    } catch (\Throwable $e) {
      // probar siguiente
    }
  }
  return null;
}

/**
 * Obtiene métricas por empresa:
 * - team_count: int
 * - align_pct: int|null  (0..100, null si sin datos)
 * - motiv_pct: int|null  (0..100, null si sin datos)
 *
 * Alineación = AVG( hofstede_porcentaje*0.8 + maslow_porcentaje*0.2 ) si existen columnas;
 * si no, cae a columnas alternativas (alineacion_pct|alineacion_porcentaje|alineacion_total).
 */
function get_company_metrics(mysqli $conn, int $company_id): array {
  // Detectar FK válida
  $fk = null;
  foreach (['empresa_id','company_id','usuario_id'] as $cand) {
    try {
      $test = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$cand` IS NOT NULL LIMIT 1");
      $test->execute(); $test->close();
      $fk = $cand; break;
    } catch (\Throwable $e) { /* sigue */ }
  }
  if ($fk === null) return ['team_count'=>0,'align_pct'=>null,'motiv_pct'=>null];

  // ¿Filtrar por estado activo?
  $whereEstado = " AND (estado IS NULL OR estado='activo')";
  try {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$fk` = ? $whereEstado");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $team_count = (int)(stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
    $stmt->close();
  } catch (\Throwable $e) {
    // fallback sin columna estado
    $whereEstado = "";
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM equipo WHERE `$fk` = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $team_count = (int)(stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
    $stmt->close();
  }

  // ---------- Alineación ponderada (80/20) con normalización de pesos ----------
  // Si una de las columnas es NULL, NO anulamos el cálculo. Reescalamos por el peso disponible.
  // Fórmula por fila:
  // score = ( IFNULL(hofstede,0)*0.8 + IFNULL(maslow,0)*0.2 ) / NULLIF( (IF(hofstede IS NOT NULL,0.8,0)+IF(maslow IS NOT NULL,0.2,0)), 0 )
  // Luego AVG(score).
  $align_avg = null;
  try {
    $sqlAlign = "
      SELECT AVG(
        ( IFNULL(hofstede_porcentaje,0)*0.8 + IFNULL(maslow_porcentaje,0)*0.2 )
        / NULLIF( (IF(hofstede_porcentaje IS NOT NULL,0.8,0) + IF(maslow_porcentaje IS NOT NULL,0.2,0)), 0 )
      ) AS a
      FROM equipo
      WHERE `$fk` = ? " . $whereEstado;
    $stA = $conn->prepare($sqlAlign);
    $stA->bind_param("i", $company_id);
    $stA->execute();
    $rowA = stmt_get_result($stA)->fetch_assoc();
    $stA->close();
    if ($rowA && $rowA['a'] !== null) $align_avg = (float)$rowA['a'];
  } catch (\Throwable $e) {
    $align_avg = null;
  }

  // Si no hay datos en Hofstede/Maslow, caer a columnas alternativas de alineación
  if ($align_avg === null) {
    foreach (['alineacion_pct','alineacion_porcentaje','alineacion_total'] as $col) {
      try {
        $sql = "SELECT AVG(`$col`) AS a FROM equipo WHERE `$fk` = ? " . $whereEstado;
        $st = $conn->prepare($sql);
        $st->bind_param("i", $company_id);
        $st->execute();
        $r = stmt_get_result($st)->fetch_assoc();
        $st->close();
        if ($r && $r['a'] !== null) { $align_avg = (float)$r['a']; break; }
      } catch (\Throwable $e) { /* sigue */ }
    }
  }

  // ---------- Motivación ----------
  $motiv_avg = null;
  foreach (['motivacion_pct','motivacion_porcentaje','motivacion_total','motivacion','motivacion_media'] as $mcol) {
    try {
      $sql = "SELECT AVG(`$mcol`) AS m FROM equipo WHERE `$fk` = ? " . $whereEstado;
      $st = $conn->prepare($sql);
      $st->bind_param("i", $company_id);
      $st->execute();
      $r = stmt_get_result($st)->fetch_assoc();
      $st->close();
      if ($r && $r['m'] !== null) { $motiv_avg = (float)$r['m']; break; }
    } catch (\Throwable $e) { /* sigue */ }
  }

  // ---------- Normalización a 0..100 ----------
  // Si en tu BD guardas 0..1, auto-escalar a 0..100:
  if ($align_avg !== null && $align_avg > 0 && $align_avg <= 1.5) $align_avg *= 100;
  if ($motiv_avg !== null && $motiv_avg > 0 && $motiv_avg <= 1.5) $motiv_avg *= 100;

  $align_pct = $align_avg !== null ? max(0, min(100, (int)round($align_avg))) : null;
  $motiv_pct = $motiv_avg !== null ? max(0, min(100, (int)round($motiv_avg))) : null;

  return [
    'team_count' => $team_count,
    'align_pct'  => $align_pct,
    'motiv_pct'  => $motiv_pct,
  ];
}




/* ---------------- Filtros y paginación ---------------- */
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 36;
$offset = ($page - 1) * $per_page;

/* ---------------- Query base (limpia y consistente) ---------------- */
$sql_base = " FROM usuarios WHERE provider_id = ? AND (LOWER(rol) IN ('company','company_admin','empresa','companyadmin')) ";
$params = [$viewer_id];
$types  = "i";

if ($q !== '') {
  $sql_base .= " AND empresa LIKE ? ";
  $params[] = "%".$q."%";
  $types   .= "s";
}

/* Conteo total */
$sql_count = "SELECT COUNT(*) AS total " . $sql_base;
$stmt = $conn->prepare($sql_count);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)(stmt_get_result($stmt)->fetch_assoc()['total'] ?? 0);
$stmt->close();

/* Página de resultados */
$sql_list = "SELECT id, empresa, logo, cultura_empresa_tipo " . $sql_base . " ORDER BY empresa ASC LIMIT ? OFFSET ?";
$params_list = $params;
$types_list  = $types . "ii";
$params_list[] = $per_page;
$params_list[] = $offset;

$stmt = $conn->prepare($sql_list);
$stmt->bind_param($types_list, ...$params_list);
$stmt->execute();
$rows = stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Paginación */
$total_pages = (int)ceil($total / $per_page);

/* Enlaces a dashboards destino (siempre via ?company_id=) */
$URL_BRAND = 'a-desktop-dashboard-brand.php';
$URL_CPV   = 'a-cultura-proposito-valores.php';
$URL_EMP   = 'dashboard_empleado-2.php';
$URL_OVERVIEW = 'a-provider_company_overview.php';
$qbase = ''; // evitar undefined en cabecera

$link_overview = $URL_OVERVIEW . '?' . $qbase;



?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Portfolio de Companies — <?= h($provider_name) ?></title>
<link rel="preconnect" href="https://use.typekit.net" crossorigin>
<link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
<style>
  :root{
    --c-primary:#012133;
    --c-secondary:#184656;
    --c-accent:#EF7F1B;
    --c-soft:#FFF5F0;
    --c-body:#474644;
    --c-bg:#FFFFFF;
    --radius:16px;
    --shadow:0 8px 24px rgba(0,0,0,.08);
  }
  *{box-sizing:border-box}
  html,body{margin:0;min-height:100vh;background:var(--c-bg);color:var(--c-body);font-family:"gelica",system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif}
  a{color:inherit;text-decoration:none}

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


  .wrap{max-width:1400px;margin:0 auto;padding:24px}
  .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:16px}
  .count{font-weight:700;color:var(--c-secondary)}
  .search{
    display:flex;gap:8px;align-items:center;background:#fff;border:1px solid #eee;border-radius:12px;padding:8px 10px;box-shadow:var(--shadow)
  }
  .search input{border:0;outline:0;min-width:220px;font-size:14px;color:var(--c-body)}
  .search button{background:var(--c-accent);color:#fff;border:0;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer}

 
 
 
 
   /* Grid/cards */
  .grid{
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)); /* 3 columnas iguales */
    gap:18px;
    align-items:stretch; /* todas las celdas misma altura de fila */
  }

  @media (max-width:1200px){
    .grid{grid-template-columns: repeat(3, minmax(0, 1fr));}
  }
  @media (max-width:992px){
    .grid{grid-template-columns: repeat(2, minmax(0, 1fr));}
  }
  @media (max-width:640px){
    .grid{grid-template-columns: minmax(0, 1fr);}
  }

  .card{
    background:#fff;
    border:1px solid #f1f1f1;
    border-radius:16px;
    box-shadow:var(--shadow);
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:12px;
    transition:.15s transform;
    height:100%;           /* 🔑 clave: la tarjeta llena toda la celda */
    width: 100%;
    max-width: 100%;
    min-width: 0; /* 🔑 evita que contenido empuje la tarjeta a 2 columnas */
}
  }
  
  

  .card:hover{
    transform:translateY(-2px);
  }

  /* Footer genérico */
  .footer{
    margin-top:18px;
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  /* Footer dentro de una card: botón siempre pegado abajo */
  .card .footer{
    margin-top:auto;
  }

 
 
 
 
 
  .card-head{display:flex;gap:12px;align-items:center}
  .logo-box{
    width:92px;height:64px;background:#fafafa;border:1px solid #eee;border-radius:12px;
    display:flex;align-items:center;justify-content:center;overflow:hidden;flex:0 0 auto
  }
  .logo-box img{max-width:92%;max-height:92%;object-fit:contain;image-rendering:-webkit-optimize-contrast}
  .title-wrap{flex:1 1 auto;min-width:0}
  
  
  
  .company-name{
    font-size:16px;
    font-weight:800;
    color:var(--c-secondary);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:100%;
}

  
  
  
  
  
  .subtitle{font-size:12px;color:#6d6d6d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  /* KPI zone */
  .kpis{
    display:grid;gap:12px;grid-template-columns: 120px 1fr 1fr;
    align-items:center
  }
  /* Anillo radial % alineación */
  /* Anillo radial % alineación */
.ring{
  --size: 96px;
  --thick: 10px;
  --pct: 0;
  position: relative;
  width: var(--size); height: var(--size);
  border-radius: 50%;
  background:
    conic-gradient(var(--c-accent) calc(var(--pct) * 1%), #eee 0);
  display:flex;align-items:center;justify-content:center;
}
.ring::after{
  content:"";
  position:absolute;inset:calc(var(--thick));
  background:#fff;border-radius:50%;
  z-index:0;             /* <-- clave: el “hueco” queda por debajo */
}
.ring-value{
  position:relative;
  z-index:1;             /* <-- clave: el número por encima */
  font-weight:800;
  font-size:18px;
  color:var(--c-secondary);
}

/* Opcional: centrado de etiqueta bajo el anillo */
.kpi-center { text-align:center; }

  .kpi-block{
    background:#fff;border:1px solid #eee;border-radius:12px;padding:10px 12px;display:flex;align-items:center;gap:10px
  }
  .kpi-label{font-size:12px;color:#6d6d6d}
  .kpi-strong{font-size:18px;font-weight:800;color:var(--c-primary)}
  .pill{font-size:11px;color:#666;padding:4px 8px;border-radius:999px;background:var(--c-soft);border:1px solid rgba(1,33,51,.06)}
  .btn{
    background:var(--c-primary);color:#fff;border:0;border-radius:10px;padding:10px 12px;font-weight:700;cursor:pointer;text-align:center
  }

  .pager a, .pager span{display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid #eee;background:#fff}
  .pager .active{background:var(--c-accent);color:#fff;border-color:var(--c-accent)}
  .empty{padding:24px;border:1px dashed #ddd;border-radius:12px;background:#fff;text-align:center;color:#666}

  .chips-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:2px}
  .chip{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid transparent}
  .chip-ctype{
    background:#FFF5F0;            /* fondo suave Valírica */
    border:1px solid rgba(1,33,51,.08);
    color:#184656;                 /* verde petróleo */
  }
  .chip-dot { width:4px; height:4px; border-radius:50%; background: currentColor; }
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



  <div class="toolbar">
    <div class="count">Encontradas: <?= (int)count($rows) ?> / <?= (int)$total ?></div>
    <form class="search" method="get" action="a-provider_companies.php">
      <input type="text" name="q" placeholder="Buscar empresa…" value="<?= h($q) ?>" />
      <?php if ($page > 1): ?>
        <input type="hidden" name="page" value="<?= (int)$page ?>">
      <?php endif; ?>
      <button type="submit">Buscar</button>
    </form>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <?php if ($q !== ''): ?>
        No hay empresas que coincidan con <strong><?= h($q) ?></strong>.
      <?php else: ?>
        Aún no tienes Companies vinculadas. Invita a tu primera empresa desde tu panel.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $c):
        $cid   = (int)$c['id'];
        $cname = (string)$c['empresa'];
        $clogo = resolve_logo_url($c['logo'] ?? '');
        $ctype = (string)($c['cultura_empresa_tipo'] ?? '');

        // Métricas por empresa (seguras y coherentes)
        $m = get_company_metrics($conn, $cid);
$team_count = (int)$m['team_count'];

/* Preservar NULL para decidir bien qué mostrar */
$align_pct  = $m['align_pct'];   // puede ser NULL
$motiv_pct  = $m['motiv_pct'];   // puede ser NULL

/* Si no hay dato, etiqueta “Sin datos”; si lo hay, mapea por umbrales */
$motiv_lbl  = ($motiv_pct === null) ? 'Sin datos' : motivation_label((int)$motiv_pct);


        $ring_pct   = $align_pct !== null ? $align_pct : 0;
        $ring_text  = $align_pct !== null ? "{$align_pct}%" : "—";

// --- Enlaces firmados para navegación segura provider → company ---
$csrf = (string)($_SESSION['csrf_token'] ?? '');
$sig  = make_company_sig($cid, $viewer_id, $csrf);

// Construye una query base con firma para reutilizar en todos los destinos
$qbase = 'company_id=' . $cid . '&sig=' . urlencode($sig);

$link_brand = $URL_BRAND . '?' . $qbase;
$link_cpv   = $URL_CPV   . '?' . $qbase;
$link_emp   = $URL_EMP   . '?' . $qbase;  // si lo usas en otros botones



      ?>
      <div class="card" title="Company: <?= h($cname) ?>">

        <div class="card-head">
          <div class="logo-box">
            <img
              loading="lazy"
              src="<?= h($clogo) ?>"
              alt="Logo de <?= h($cname) ?>"
              onerror="this.onerror=null;this.src='https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';"
            />
          </div>
          <div class="title-wrap">
            <div class="company-name" title="<?= h($cname) ?>"><?= h($cname) ?></div>
            
          </div>
        </div>

        <div class="kpis">
          <!-- Alineación: anillo radial -->
          <div class="kpi-center">
  <div class="ring" style="--pct: <?= (int)($align_pct ?? 0); ?>;">
    <div class="ring-value">
      <?= ($align_pct === null) ? '—' : ((int)$align_pct . '%') ?>
    </div>
  </div>
</div>


          <!-- Team size -->
          <div class="kpi-block" aria-label="Miembros del equipo">
            <div>
              <div class="kpi-label">Miembros inscritos</div>
              <div class="kpi-strong"><?= (int)$team_count ?></div>
            </div>
          </div>

          <!-- Motivación -->
         

        </div>

<div class="footer">
<a class="btn" href="<?= h($link_overview) ?>">Ver ficha</a>
</div>

      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
      <div class="footer pager" aria-label="Paginación" style="justify-content:center;margin-top:20px">
        <?php for ($p=1; $p <= $total_pages; $p++):
          $qstr = http_build_query(array_filter(['q'=>$q, 'page'=>$p]));
          $url  = 'a-provider_companies.php' . ($qstr ? ('?'.$qstr) : '');
          $cls  = $p === $page ? 'active' : '';
        ?>
          <?php if ($cls): ?>
            <span class="<?= $cls ?>"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= h($url) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

</body>
</html>

