<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

/* ============ Helpers ============ */
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function normalize_key($s){
  $s = trim(mb_strtolower((string)$s, 'UTF-8'));
  $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
  return preg_replace('/\s+/', ' ', $s);
}
function battery_icon_for_pct($pct){
  if ($pct <= 25)  return ['/uploads/Battery-low.png','Baja'];
  if ($pct <= 50)  return ['/uploads/Battery-mid.png','Media'];
  if ($pct <= 75)  return ['/uploads/Battery-high.png','Alta'];
  return ['/uploads/Battery-full.png','Óptima'];
}

function resolve_logo_url(?string $path): string {
    $valiricaDefault = 'https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';
    $p = trim((string)$path);

    if ($p === '') return $valiricaDefault;

    // Ya es URL absoluta
    if (preg_match('~^https?://~i', $p)) return $p;

    // Doble slash (protocolo relativo) → fuerza https
    if (strpos($p, '//') === 0) return 'https:' . $p;

    // Empieza con slash → host + path
    if ($p[0] === '/') return 'https://app.valirica.com' . $p;

    // Empieza por 'uploads/...' → la colgamos del dominio
    if (stripos($p, 'uploads/') === 0) return 'https://app.valirica.com/' . $p;

    // Cualquier otro caso → lo colgamos de /uploads/
    return 'https://app.valirica.com/uploads/' . $p;
}


function chip_for_alineacion($pct){
  $pct = max(0, min(100, (float)$pct));
  if ($pct < 20)  return ['Baja',       'warn', 'x'];
  if ($pct < 40)  return ['Media - Baja','warn', 'x'];
  if ($pct < 60)  return ['Media',      '',      null];
  if ($pct < 80)  return ['Media - Alta','',     null];
  return ['Alta', 'ok', 'check'];
}


function chip_for_motivacion($status){
  $s = mb_strtolower((string)$status, 'UTF-8');
  if ($s === 'baja')   return ['Baja',   'warn', 'x'];
  if ($s === 'media')  return ['Media',  '',     null];
  // 'Alta' u 'Óptima' → ok
  return [ucfirst($status), 'ok', 'check'];
}




function cultura_label($tipo){
  $s = trim((string)$tipo); $n = normalize_key($s);
  switch ($n) {
    case 'clan': case 'cultura clan': case 'colaborativa': case 'cultura colaborativa': return 'Colaborativa';
    case 'adhocracia': case 'adhocratica': case 'cultura adhocratica': case 'innovadora': case 'cultura innovadora': case 'innovacion': return 'Ágil';
    case 'mercado': case 'cultura mercado': case 'orientada a resultados': case 'resultados': case 'enfoque a resultados': return 'Orientada a Resultados';
    case 'jerarquica': case 'jerarquia': case 'jerárquica': case 'cultura jerarquica': case 'estructurada': case 'estructura': return 'Estructurada';
    default: return ucfirst($s);
  }
}


function cultura_key_canon($tipo){
  $n = normalize_key($tipo);
  switch ($n) {
    // Claves históricas
    case 'clan': case 'cultura clan': case 'colaborativa': case 'cultura colaborativa':
      return 'Clan';

    case 'adhocracia': case 'adhocratica': case 'cultura adhocratica':
    case 'innovadora': case 'cultura innovadora': case 'innovacion':
    case 'agil': case 'ágil': case 'cultura de cambio':
      return 'Adhocracia';

    case 'jerarquica': case 'jerarquia': case 'jerárquica': case 'cultura jerarquica':
    case 'estructurada': case 'estructura': case 'cultura de orden':
      return 'Jerárquica';

    case 'mercado': case 'cultura mercado':
    case 'orientada a resultados': case 'resultados': case 'enfoque a resultados':
    case 'cultura de impacto':
      return 'Mercado';

    default:
      return '';
  }
}




/* ============ Identidad de la marca (como en el dashboard) ============ */
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT empresa, logo, cultura_empresa_tipo, rol FROM usuarios WHERE id = ?");
$usuario_id = $user_id; // Necesario para reutilizar el mismo header del dashboard

$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$empresa = $u['empresa'] ?? 'Nombre de la empresa';
$logo    = $u['logo']    ?? '/uploads/logo-192.png';
$rol_usuario = (string)($u['rol'] ?? '');
$cultura_empresa_tipo = (string)($u['cultura_empresa_tipo'] ?? '');


/* ============ Áreas de trabajo ============ */
$stmt_areas = $conn->prepare("
  SELECT id, nombre_area
  FROM areas_trabajo
  WHERE usuario_id = ?
  ORDER BY nombre_area ASC
");
$stmt_areas->bind_param("i", $user_id);
$stmt_areas->execute();
$areas_trabajo = $stmt_areas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_areas->close();

/* Área seleccionada (GET o default) */
$area_seleccionada = $_GET['area_id'] ?? 'custom';


/* ============ Miembros del equipo seleccionados (checkboxes) ============ */
$equipo_ids_seleccionados = [];

if (!empty($_GET['equipo_ids']) && is_array($_GET['equipo_ids'])) {
    // Sanitizar IDs
    $equipo_ids_seleccionados = array_map('intval', $_GET['equipo_ids']);
}



/* ============ Miembros del equipo según área (por NOMBRE) ============ */
$equipo_miembros = [];

if ($area_seleccionada === 'custom') {

    // Equipo personalizado → TODOS los miembros
    $stmt_eq = $conn->prepare("
        SELECT id, nombre_persona, apellido, cargo
        FROM equipo
        WHERE usuario_id = ?
        ORDER BY nombre_persona ASC
    ");
    $stmt_eq->bind_param("i", $user_id);

} else {

    // Obtener el nombre real del área desde areas_trabajo
    $stmt_area = $conn->prepare("
        SELECT nombre_area
        FROM areas_trabajo
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt_area->bind_param("ii", $area_seleccionada, $user_id);
    $stmt_area->execute();
    $area_row = $stmt_area->get_result()->fetch_assoc();
    $stmt_area->close();

    $nombre_area = $area_row['nombre_area'] ?? '';

    // Si el área existe, filtramos por NOMBRE
    if ($nombre_area !== '') {
        $stmt_eq = $conn->prepare("
            SELECT id, nombre_persona, apellido, cargo
            FROM equipo
            WHERE usuario_id = ?
              AND area_trabajo = ?
            ORDER BY nombre_persona ASC
        ");
        $stmt_eq->bind_param("is", $user_id, $nombre_area);
    } else {
        // Área inválida → no mostrar nadie
        $equipo_miembros = [];
        $stmt_eq = null;
    }
}

if ($stmt_eq) {
    $stmt_eq->execute();
    $equipo_miembros = $stmt_eq->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_eq->close();
}




/* ==========================================================
   🔹 CONSULTAS PRINCIPALES — CULTURA IDEAL + PROPÓSITO + VALORES
   ========================================================== */

// --- CULTURA IDEAL (estructura de tu tabla actual) ---
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
");
$stmt_cultura->bind_param("i", $user_id);
$stmt_cultura->execute();
$result_cultura = $stmt_cultura->get_result();
$cultura_ideal = $result_cultura->fetch_assoc() ?? [];
$stmt_cultura->close();

// --- VARIABLES BASE DEL PROPÓSITO ---
$proposito_txt        = trim($cultura_ideal['proposito'] ?? '');
$proposito_enfoque    = (float)($cultura_ideal['proposito_enfoque'] ?? 0);
$proposito_motivacion = (float)($cultura_ideal['proposito_motivacion'] ?? 0);
$proposito_tiempo     = (float)($cultura_ideal['proposito_tiempo'] ?? 0);
$proposito_disrupcion = (float)($cultura_ideal['proposito_disrupcion'] ?? 0);
$proposito_inmersion  = (float)($cultura_ideal['proposito_inmersion'] ?? 0);
$ubicacion            = trim($cultura_ideal['ubicacion'] ?? '');
$estilo_comunicacion  = trim($cultura_ideal['estilo_comunicacion'] ?? '');
$valores_json         = $cultura_ideal['valores_json'] ?? '';
$estilo_cultura_aprend = 'Visual'; // Fallback seguro, ya que no existe esa columna

// --- VALORES DE MARCA (para mapa y texto dinámico) ---
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
$valores_list = [];

while ($v = $result_valores->fetch_assoc()) {
    $titulo = trim($v['titulo'] ?? '');
    $descripcion = trim($v['descripcion'] ?? '');

    $aplicacion    = is_numeric($v['aplicacion']) ? (float)$v['aplicacion'] : 0;
    $activador     = is_numeric($v['activador']) ? (float)$v['activador'] : 0;
    $proposito_val = is_numeric($v['proposito']) ? (float)$v['proposito'] : 0;
    $rol           = is_numeric($v['rol']) ? (float)$v['rol'] : 0;
    $institucional = is_numeric($v['institucional']) ? (float)$v['institucional'] : 0;

    // Calcular coordenadas de cada valor
    $x_val = round(($aplicacion + $activador + $proposito_val) / 3, 2);
    $y_val = round(($rol + $institucional) / 2, 2);

    if ($titulo !== '') {
        $valores_puntos[] = [
            'x' => $x_val,
            'y' => $y_val,
            'label' => htmlspecialchars($titulo)
        ];
    }

    if ($titulo !== '' && $descripcion !== '') {
        $valores_list[] = [
            'titulo' => htmlspecialchars($titulo),
            'descripcion' => htmlspecialchars($descripcion)
        ];
    }
}
$stmt_valores->close();

$valores_ideales = [];
foreach (['distancia_poder','individualismo','masculinidad','incertidumbre','largo_plazo','indulgencia'] as $k) {
  $valores_ideales[$k] = isset($cultura_ideal[$k]) ? round(((float)$cultura_ideal[$k]) / 5, 3) : 0.0;
}
$estilo_cultura_aprend = $cultura_ideal['estilo_aprendizaje'] ?? 'Visual';
$LABELS_SENSORIALES = ['visual'=>'Visual','auditivo'=>'Auditivo','kinestesico'=>'Kinestésico'];
$NORM = fn($s)=>preg_replace('/\s+/', ' ', strtr(mb_strtolower(trim((string)$s),'UTF-8'),
        ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']));
$_estilo_cultura_norm = $NORM($estilo_cultura_aprend);











/* 3) Motivación (Pink) y Maslow → batería de energía */
$res_pink = mysqli_query($conn, "SELECT 
  SUM(pink_purp) proposito, SUM(pink_auto) autonomia, SUM(pink_maes) maestria,
  SUM(pink_fis) salud, SUM(pink_rel) relaciones
  FROM equipo WHERE usuario_id = {$user_id}");
$pink = mysqli_fetch_assoc($res_pink) ?: ['proposito'=>0,'autonomia'=>0,'maestria'=>0,'salud'=>0,'relaciones'=>0];
$res_count = mysqli_query($conn, "SELECT COUNT(*) total FROM equipo WHERE usuario_id = {$user_id}");
$equipo_count = (int) (mysqli_fetch_assoc($res_count)['total'] ?? 0);
$total_pink = array_sum($pink); $max_pink = $equipo_count * 25;
$porcentaje_pink = ($max_pink>0) ? min(100, round(($total_pink/$max_pink)*100)) : 0;

$res_maslow = mysqli_query($conn, "SELECT 
  AVG(maslow_fis) fisiologica, AVG(maslow_seg) seguridad, AVG(maslow_afi) afiliacion,
  AVG(maslow_rec) reconocimiento, AVG(maslow_aut) autorrealizacion
  FROM equipo WHERE usuario_id = {$user_id}");
$maslow = mysqli_fetch_assoc($res_maslow) ?: ['fisiologica'=>0,'seguridad'=>0,'afiliacion'=>0,'reconocimiento'=>0,'autorrealizacion'=>0];
$dom_maslow = array_keys($maslow, max($maslow))[0] ?? 'fisiologica';
$map_maslow_energy = ['fisiologica'=>0,'seguridad'=>25,'afiliacion'=>50,'reconocimiento'=>75,'autorrealizacion'=>100];
$maslow_pct = $map_maslow_energy[$dom_maslow] ?? 0;

$energia_equipo = (int) round(0.6 * $porcentaje_pink + 0.4 * $maslow_pct);
list($energia_icon, $energia_status) = battery_icon_for_pct($energia_equipo);

/* 4) Estilo de aprendizaje del equipo vs cultura */
$stmt_sen = $conn->prepare("SELECT AVG(visual) visual, AVG(auditivo) auditivo, AVG(kinestesico) kinestesico FROM equipo WHERE usuario_id = ?");
$stmt_sen->bind_param("i", $user_id);
$stmt_sen->execute();
$sen = $stmt_sen->get_result()->fetch_assoc() ?: ['visual'=>0,'auditivo'=>0,'kinestesico'=>0];
$stmt_sen->close();
$prom_sens = ['visual'=>(float)$sen['visual'], 'auditivo'=>(float)$sen['auditivo'], 'kinestesico'=>(float)$sen['kinestesico']];
$hay_datos_sensoriales = array_sum($prom_sens) > 0;
$dom_sensorial = $hay_datos_sensoriales ? array_keys($prom_sens, max($prom_sens))[0] : null;
$estilo_equipo_aprend = $dom_sensorial ? $LABELS_SENSORIALES[$dom_sensorial] : 'Sin datos';
$aprend_alineado = $dom_sensorial ? ($NORM($LABELS_SENSORIALES[$dom_sensorial]) === $_estilo_cultura_norm) : false;

/* 5) Chips para KPIs — se recalculan más abajo, tras obtener $promedio_general */


/* ========= PROPÓSITO Y VALORES (datos) ========= */

// usamos el mismo $user_id del header (session)
$uid = (int)$user_id;

/** Promedia columnas específicas de valores_marca (exacto como tu referencia) */
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
    $total = array_sum($sumas);
    return $total / ($count * count($claves));
}

/* --- dimensiones propósito --- */
$stmt = $conn->prepare("SELECT proposito, proposito_enfoque, proposito_motivacion, proposito_tiempo, proposito_disrupcion, proposito_inmersion FROM cultura_ideal WHERE usuario_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$datos_proposito = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$proposito_txt        = trim((string)($datos_proposito['proposito'] ?? ''));
$proposito_enfoque    = (float)($datos_proposito['proposito_enfoque'] ?? 0);
$proposito_motivacion = (float)($datos_proposito['proposito_motivacion'] ?? 0);
$proposito_tiempo     = (float)($datos_proposito['proposito_tiempo'] ?? 0);
$proposito_disrupcion = (float)($datos_proposito['proposito_disrupcion'] ?? 0);
$proposito_inmersion  = (float)($datos_proposito['proposito_inmersion'] ?? 0);

/* --- valores individuales (x=(aplicación+activador+propósito)/3; y=(rol+institucional)/2) --- */
$stmt = $conn->prepare("SELECT titulo, descripcion, aplicacion, activador, proposito, rol, institucional FROM valores_marca WHERE usuario_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res_val = $stmt->get_result();

$valores_puntos = [];
$valores_list   = []; // para mostrar títulos/descripciones
while ($v = $res_val->fetch_assoc()) {
    $titulo = trim((string)($v['titulo'] ?? ''));
    $desc   = trim((string)($v['descripcion'] ?? ''));
    $ap     = is_numeric($v['aplicacion'])    ? (float)$v['aplicacion']    : 0;
    $ac     = is_numeric($v['activador'])     ? (float)$v['activador']     : 0;
    $pp     = is_numeric($v['proposito'])     ? (float)$v['proposito']     : 0;
    $rol    = is_numeric($v['rol'])           ? (float)$v['rol']           : 0;
    $inst   = is_numeric($v['institucional']) ? (float)$v['institucional'] : 0;

    if ($titulo !== '') {
        $valores_puntos[] = [
            'x' => round(($ap + $ac + $pp) / 3, 2),
            'y' => round(($rol + $inst) / 2, 2),
            'label' => $titulo
        ];
    }
    if ($titulo !== '' && $desc !== '') {
        $valores_list[] = ['titulo' => $titulo, 'descripcion' => $desc];
    }
}
$stmt->close();





/* --- promedios de apoyo para ejes --- */
$prom_x = promedio_valores_marca(['aplicacion','activador','proposito'], $conn, $uid);
$prom_y = promedio_valores_marca(['rol','institucional'], $conn, $uid);

/* --- puntos del propósito (peso 1.3) --- */
$proposito_puntos = [
  ['x'=>$proposito_enfoque, 'y'=>$proposito_motivacion],
  ['x'=>$proposito_disrupcion, 'y'=>$proposito_inmersion],
  ['x'=>$proposito_enfoque, 'y'=>$proposito_tiempo],
];

/* ===== Inicialización defensiva ===== */
$equipo_ids_visibles = [];

/* ===== PUNTOS DEL EQUIPO EN EL CUADRANTE (FILTRADOS) ===== */
$equipo_puntos = [];









/* IDs seleccionados por checkbox (si existen) */
$equipo_ids_seleccionados = [];
if (!empty($_GET['equipo_ids']) && is_array($_GET['equipo_ids'])) {
    $equipo_ids_seleccionados = array_map('intval', $_GET['equipo_ids']);
}

/* Si hay área seleccionada (no custom), obtenemos su nombre */
$nombre_area = '';
if ($area_seleccionada !== 'custom') {
    $stmt_area = $conn->prepare("
        SELECT nombre_area
        FROM areas_trabajo
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt_area->bind_param("ii", $area_seleccionada, $uid);
    $stmt_area->execute();
    $row_area = $stmt_area->get_result()->fetch_assoc();
    $stmt_area->close();

    $nombre_area = $row_area['nombre_area'] ?? '';
}

/* Traemos TODOS los miembros (filtraremos en PHP) */
$q = $conn->prepare("
  SELECT id, nombre_persona, area_trabajo,
         hofstede_poder          AS distancia_poder,
         hofstede_individualismo AS individualismo,
         hofstede_incertidumbre  AS incertidumbre,
         hofstede_espontaneidad  AS indulgencia,
         hofstede_resultados     AS masculinidad,
         hofstede_largo_plazo    AS largo_plazo
  FROM equipo
  WHERE usuario_id = ?
");
$q->bind_param("i", $uid);
$q->execute();
$rs = $q->get_result();

while ($r = $rs->fetch_assoc()) {

    /* ===== FILTRO 1: ÁREA ===== */
    if ($area_seleccionada !== 'custom') {
        if ($nombre_area === '' || $r['area_trabajo'] !== $nombre_area) {
            continue; // ❌ fuera del área
        }
    }

    /* ===== FILTRO 2: CHECKBOX ===== */
    if (!empty($equipo_ids_seleccionados)) {
        if (!in_array((int)$r['id'], $equipo_ids_seleccionados, true)) {
            continue; // ❌ no marcado
        }
    }

    /* ===== CONSTRUCCIÓN DEL PUNTO (MISMA LÓGICA QUE YA TENÍAS) ===== */
    $raw_row = [
        'individualismo'  => (float)$r['individualismo'],
        'masculinidad'    => (float)$r['masculinidad'],
        'incertidumbre'   => (float)$r['incertidumbre'],
        'distancia_poder' => (float)$r['distancia_poder'],
        'largo_plazo'     => (float)$r['largo_plazo'],
        'indulgencia'     => (float)$r['indulgencia'],
    ];

// ===== CÁLCULO DIRECTO COMO EN EL ARCHIVO ORIGINAL =====
// Asumimos valores Hofstede ya normalizados en -1..+1 (como antes)

$ind = (float)$r['individualismo'];
$mas = (float)$r['masculinidad'];
$inc = (float)$r['incertidumbre'];
$pwr = (float)$r['distancia_poder'];
$indul = (float)$r['indulgencia'];
$lto = (float)$r['largo_plazo'];

// EJE X: Interno (-) ←→ Externo (+)
$px = (
    (0.35 * $ind) +
    (0.20 * $mas) -
    (0.25 * $pwr) -
    (0.10 * $lto)
) * 5;

// EJE Y: Controlado (-) ←→ Flexible (+)
$py = (
    (0.40 * $indul) -
    (0.35 * $inc) +
    (0.10 * $mas) -
    (0.15 * $lto)
) * 5;

// Clamp defensivo
$px = max(-5, min(5, $px));
$py = max(-5, min(5, $py));


$equipo_puntos[] = [
    'x'     => round($px, 2),
    'y'     => round($py, 2),
    'label' => $r['nombre_persona'],
    'id'    => $r['id']
];
}

$q->close();


/* ===== IDs reales del equipo visible (área + checkboxes) ===== */
$equipo_ids_visibles = array_column($equipo_puntos, 'id');


// ======================================================
// Promedios Hofstede SOLO del equipo visible (filtrado)
// ======================================================

$team_avg = [
    'distancia_poder' => 0,
    'individualismo'  => 0,
    'masculinidad'    => 0,
    'incertidumbre'   => 0,
    'largo_plazo'     => 0,
    'indulgencia'     => 0,
];

$team_n = count($equipo_ids_visibles);

if ($team_n > 0) {

    $placeholders = implode(',', array_fill(0, $team_n, '?'));
    $types = str_repeat('i', $team_n);

    $sql = "
        SELECT 
            AVG(hofstede_poder)          AS distancia_poder,
            AVG(hofstede_individualismo) AS individualismo,
            AVG(hofstede_resultados)     AS masculinidad,
            AVG(hofstede_incertidumbre)  AS incertidumbre,
            AVG(hofstede_largo_plazo)    AS largo_plazo,
            AVG(hofstede_espontaneidad)  AS indulgencia
        FROM equipo
        WHERE id IN ($placeholders)
    ";

    $stmt_team_avg = $conn->prepare($sql);
    $stmt_team_avg->bind_param($types, ...$equipo_ids_visibles);
    $stmt_team_avg->execute();

    $team_avg = $stmt_team_avg->get_result()->fetch_assoc() ?: $team_avg;

    $stmt_team_avg->close();
}






/* ======================================================
   Alineación cultural PROMEDIO (0..100)
   SOLO del equipo visible (checkboxes + área)
   ====================================================== */

$promedio_general = 0.0;
$total_alineacion = 0.0;
$n = 0;

if (!empty($equipo_ids_visibles)) {

    $placeholders = implode(',', array_fill(0, count($equipo_ids_visibles), '?'));
    $types = str_repeat('i', count($equipo_ids_visibles));

    $sql = "
      SELECT 
        hofstede_poder          AS distancia_poder,
        hofstede_individualismo AS individualismo,
        hofstede_resultados     AS masculinidad,
        hofstede_incertidumbre  AS incertidumbre,
        hofstede_largo_plazo    AS largo_plazo,
        hofstede_espontaneidad  AS indulgencia
      FROM equipo
      WHERE id IN ($placeholders)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$equipo_ids_visibles);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $suma = 0.0;
        $d = 0;

        foreach ($valores_ideales as $k => $ideal) {
            if (!isset($row[$k])) continue;

            $real  = (float)$row[$k];        // -1..1
            $ideal = (float)$ideal;          // -1..1

            $aline = 1 - (abs($real - $ideal) / 2);
            $aline = max(0, min(1, $aline));

            $suma += $aline;
            $d++;
        }

        if ($d > 0) {
            $total_alineacion += ($suma / $d);
            $n++;
        }
    }

    $stmt->close();

    $promedio_general = $n > 0
        ? round(($total_alineacion / $n) * 100, 1)
        : 0.0;
}

/* 5) Chips para KPIs */
list($aline_label, $aline_class, $aline_icon) = chip_for_alineacion($promedio_general);
list($mot_label,   $mot_class,   $mot_icon)   = chip_for_motivacion($energia_status);


/* ======================================================
   PERFILES — alineación individual (círculo concéntrico)
   Solo del equipo visible (checkboxes + área)
   ====================================================== */
$perfiles = [];

if (!empty($equipo_ids_visibles)) {
    $ph_p = implode(',', array_fill(0, count($equipo_ids_visibles), '?'));
    $tp_p = str_repeat('i', count($equipo_ids_visibles));

    $sql_p = "
      SELECT id, nombre_persona,
        hofstede_poder          AS distancia_poder,
        hofstede_individualismo AS individualismo,
        hofstede_resultados     AS masculinidad,
        hofstede_incertidumbre  AS incertidumbre,
        hofstede_largo_plazo    AS largo_plazo,
        hofstede_espontaneidad  AS indulgencia
      FROM equipo
      WHERE id IN ($ph_p)
    ";

    $stmt_p = $conn->prepare($sql_p);
    $stmt_p->bind_param($tp_p, ...$equipo_ids_visibles);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();

    while ($row_p = $res_p->fetch_assoc()) {
        $suma_p = 0.0;
        $d_p = 0;

        foreach ($valores_ideales as $k => $ideal) {
            if (!isset($row_p[$k])) continue;

            $real_p  = (float)$row_p[$k];
            $ideal_p = (float)$ideal;

            $aline_p = 1 - (abs($real_p - $ideal_p) / 2);
            $aline_p = max(0, min(1, $aline_p));

            $suma_p += $aline_p;
            $d_p++;
        }

        $perfiles[] = [
            'nombre'     => $row_p['nombre_persona'],
            'id'         => $row_p['id'],
            'alineacion' => $d_p > 0 ? round(($suma_p / $d_p) * 100, 1) : 0.0
        ];
    }

    $stmt_p->close();

    usort($perfiles, fn($a, $b) => $b['alineacion'] <=> $a['alineacion']);
}


/* ======================================================
   FORTALEZAS Y TENSIONES — EQUIPO VS CULTURA
   ====================================================== */
$fortalezas_equipo_cultura = [];
$tensiones_equipo_cultura  = [];

foreach ($valores_ideales as $dim => $ideal) {

    if (!isset($team_avg[$dim])) continue;

    $real = (float)$team_avg[$dim];     // -1 .. +1
    $ideal = (float)$ideal;             // -1 .. +1

    $gap = abs($real - $ideal);         // 0..2
    $gap_pct = round(($gap / 2) * 100); // 0..100

    if ($gap_pct < 20) {
        $fortalezas_equipo_cultura[] = [
            'label' => ucfirst(str_replace('_',' ', $dim))
        ];
    } else {
        $tensiones_equipo_cultura[] = [
            'label' => ucfirst(str_replace('_',' ', $dim)),
            'gap'   => $gap_pct
        ];
    }
}

/* --- promedio ponderado final (valores peso 1, propósito 1.3) --- */
$peso_total=0; $sx=0; $sy=0;
foreach ($valores_puntos as $p){ $sx += $p['x'] * 1.0; $sy += $p['y'] * 1.0; $peso_total += 1.0; }
foreach ($proposito_puntos as $p){ $sx += $p['x'] * 1.3; $sy += $p['y'] * 1.3; $peso_total += 1.3; }
$ejeX = ($peso_total>0) ? round($sx / $peso_total, 2) : 0;
$ejeY = ($peso_total>0) ? round($sy / $peso_total, 2) : 0;

/* --- punto del propósito puro (para pintar) --- */
$proposito_punto = [
  'x' => round(($proposito_enfoque + $proposito_motivacion)/2, 2),
  'y' => round(($proposito_disrupcion + $proposito_inmersion + $proposito_tiempo)/3, 2)
];

/* --- tipo cultural por cuadrante y actualización en usuarios --- */
$cultura_tipo = '';
if ($ejeX < 0 && $ejeY > 0)        $cultura_tipo = 'Clan';
elseif ($ejeX >= 0 && $ejeY > 0)   $cultura_tipo = 'Adhocracia';
elseif ($ejeX < 0 && $ejeY <= 0)   $cultura_tipo = 'Jerárquica';
elseif ($ejeX >= 0 && $ejeY <= 0)  $cultura_tipo = 'Mercado';




/* --- textos visibles por cultura (como tu diccionario) --- */
$info_culturas = [
  'Clan' => [
    'nombre'      => 'Cultura Colaborativa',
    'subtitulo'   => 'Colaboración, equipo, comunidad',
    'descripcion' => 'En esta cultura, lo más importante son las personas. El trabajo en equipo, el sentido de comunidad y el cuidado mutuo...',
    'fortalezas'  => [
      'Alta cohesión y confianza.',
      'Onboarding más humano.',
      'Retención por sentido de pertenencia.'
    ],
    'alertas'     => [
      'Evitar exceso de consenso que frene decisiones.',
      'Cuidar meritocracia y accountability.'
    ],
    'rituales'    => [
      'Dailies breves con check-in emocional.',
      'Ritos de reconocimiento por comportamientos culturales.',
      'Espacios de feedback seguro (retro quincenal).'
    ]
  ],
  'Adhocracia' => [
    'nombre'      => 'Cultura Ágil',
    'subtitulo'   => 'Innovación, disrupción, cambio',
    'descripcion' => 'Esta cultura es puro movimiento. Las ideas vuelan rápido; la innovación es el estado natural...',
    'fortalezas'  => [
      'Creatividad y experimentación constantes.',
      'Atracción de talento curioso y autosuficiente.'
    ],
    'alertas'     => [
      'Riesgo de dispersión y deuda de procesos.',
      'Cansancio por cambio sin contención.'
    ],
    'rituales'    => [
      'Sprints con demos visibles.',
      'Post-mortems sin culpas.',
      'Roadmap público y priorizado.'
    ]
  ],
  'Jerárquica' => [
    'nombre'      => 'Cultura Estructurada',
    'subtitulo'   => 'Estructura, procesos, estabilidad',
    'descripcion' => 'Nada queda al azar. Claridad de roles, procesos y reglas para mantener el control...',
    'fortalezas'  => [
      'Previsibilidad y reducción de errores.',
      'Escalabilidad operativa.'
    ],
    'alertas'     => [
      'Rigidez ante el cambio.',
      'Burocracia que ralentiza el delivery.'
    ],
    'rituales'    => [
      'RACI por procesos críticos.',
      'Revisiones de cumplimiento y mejora continua.',
      'Tableros de progreso visibles.'
    ]
  ],
  'Mercado' => [
    'nombre'      => 'Cultura de Resultados',
    'subtitulo'   => 'Metas, competencia, resultados',
    'descripcion' => 'Aquí todo gira alrededor del logro y la ambición por objetivos concretos...',
    'fortalezas'  => [
      'Claridad de metas e impacto.',
      'Velocidad para capturar oportunidades.'
    ],
    'alertas'     => [
      'Riesgo de burnout y rotación por presión.',
      'Descuidar vínculos y aprendizaje.'
    ],
    'rituales'    => [
      'OKR con cadencia y retrospectivas.',
      'Reconocimiento por resultados y por aprendizajes.',
      'Revisiones semanales de pipeline/entregas.'
    ]
  ],
];









/* START PATCH: reemplaza la lógica Hofstede previa POR ESTE BLOQUE */

// --- nueva librería local (v2) para calcular ejes Hofstede (usa 0..100 como entrada)
function norm_0_100_to_m1_1($v) {
    if (!is_numeric($v)) return 0.0;
    $v = (float)$v;
    if ($v < 0) $v = 0;
    if ($v > 100) $v = 100;
    return ($v / 100.0) * 2.0 - 1.0; // -1 .. +1
}
function clamp($v, $min = -1.0, $max = 1.0) {
    if ($v < $min) return $min;
    if ($v > $max) return $max;
    return $v;
}
function calcula_ejes_hofstede_v2(array $v, array $opts = []): array {
    // Normalizar (0..100 -> -1..+1) - valores por defecto 50 -> 0
    $ind = norm_0_100_to_m1_1($v['individualismo'] ?? 50.0);
    $mas = norm_0_100_to_m1_1($v['masculinidad'] ?? 50.0);
    $inc = norm_0_100_to_m1_1($v['incertidumbre'] ?? 50.0);
    $pwr = norm_0_100_to_m1_1($v['distancia_poder'] ?? 50.0);
    $lto = norm_0_100_to_m1_1($v['largo_plazo'] ?? 50.0);
    $indul = norm_0_100_to_m1_1($v['indulgencia'] ?? 50.0);

    // Pesos recomendados (ajustables)
    $weightsX = $opts['weights']['X'] ?? [
        'individualismo' => 0.35,
        'masculinidad'   => 0.20,
        'distancia_poder'=> -0.25,
        'largo_plazo'    => -0.10
    ];
    $weightsY = $opts['weights']['Y'] ?? [
        'indulgencia'    => 0.40,
        'incertidumbre'  => -0.35,
        'masculinidad'   => 0.10,
        'largo_plazo'    => -0.15
    ];

    // Suma ponderada
    $X = 0.0;
    $X += ($weightsX['individualismo'] ?? 0.0) * $ind;
    $X += ($weightsX['masculinidad']   ?? 0.0) * $mas;
    $X += ($weightsX['distancia_poder']?? 0.0) * $pwr;
    $X += ($weightsX['largo_plazo']    ?? 0.0) * $lto;

    $Y = 0.0;
    $Y += ($weightsY['indulgencia']    ?? 0.0) * $indul;
    $Y += ($weightsY['incertidumbre']  ?? 0.0) * $inc;
    $Y += ($weightsY['masculinidad']   ?? 0.0) * $mas;
    $Y += ($weightsY['largo_plazo']    ?? 0.0) * $lto;

    // Normalizar por suma absoluta si es necesario
    $sumAbsX = abs($weightsX['individualismo'] ?? 0) + abs($weightsX['masculinidad'] ?? 0) + abs($weightsX['distancia_poder'] ?? 0) + abs($weightsX['largo_plazo'] ?? 0);
    $sumAbsY = abs($weightsY['indulgencia'] ?? 0) + abs($weightsY['incertidumbre'] ?? 0) + abs($weightsY['masculinidad'] ?? 0) + abs($weightsY['largo_plazo'] ?? 0);
    if ($sumAbsX > 1.0) $X = $X / $sumAbsX;
    if ($sumAbsY > 1.0) $Y = $Y / $sumAbsY;

    // Clamp y escalar a -5..+5
    $X = clamp($X, -1.0, 1.0) * 5.0;
    $Y = clamp($Y, -1.0, 1.0) * 5.0;

    return [ round($X, 2), round($Y, 2) ];
}

// --- Tomamos los valores *crudos* que ya vienen en $cultura_ideal
$raw_vals = [
  'individualismo'  => isset($cultura_ideal['individualismo'])  ? (float)$cultura_ideal['individualismo']  : null,
  'masculinidad'    => isset($cultura_ideal['masculinidad'])    ? (float)$cultura_ideal['masculinidad']    : null,
  'incertidumbre'   => isset($cultura_ideal['incertidumbre'])   ? (float)$cultura_ideal['incertidumbre']   : null,
  'distancia_poder' => isset($cultura_ideal['distancia_poder']) ? (float)$cultura_ideal['distancia_poder'] : null,
  'largo_plazo'     => isset($cultura_ideal['largo_plazo'])     ? (float)$cultura_ideal['largo_plazo']     : null,
  'indulgencia'     => isset($cultura_ideal['indulgencia'])     ? (float)$cultura_ideal['indulgencia']     : null
];



// Detectar la escala y convertir a 0..100 (lo que espera la función)
// Cubrimos explícitamente: -5..+5 ; -1..+1 ; 0..1 ; 0..5 ; 0..100
$vals_for_0_100 = [];
$min = PHP_FLOAT_MAX; $max = -PHP_FLOAT_MAX;
foreach ($raw_vals as $k => $v) {
    if ($v === null) continue;
    $min = min($min, $v);
    $max = max($max, $v);
}
if ($min === PHP_FLOAT_MAX) { // no hay datos -> fallback 50
    $vals_for_0_100 = [
        'individualismo'=>50,'masculinidad'=>50,'incertidumbre'=>50,
        'distancia_poder'=>50,'largo_plazo'=>50,'indulgencia'=>50
    ];
} else {
    // 1) Caso -5..+5 (mapeo correcto)
    if ($min >= -5.0 && $max <= 5.0) {
        foreach ($raw_vals as $k=>$v) {
            if ($v === null) $v = 0.0;
            $vals_for_0_100[$k] = (($v + 5.0) / 10.0) * 100.0; // (v+5)*10
        }
    }
    // 2) Caso -1..+1  -> convertimos: -1..1 -> 0..100
    elseif ($min >= -1.0 && $max <= 1.0) {
        foreach ($raw_vals as $k=>$v) {
            if ($v === null) $v = 0.0;
            $vals_for_0_100[$k] = (($v + 1.0) / 2.0) * 100.0;
        }
    }
    // 3) Caso 0..5 -> 0..100 (x20)
    elseif ($min >= 0.0 && $max <= 5.0) {
        foreach ($raw_vals as $k=>$v) {
            if ($v === null) $v = 0.0;
            $vals_for_0_100[$k] = $v * 20.0;
        }
    }
    // 4) Caso 0..1 -> fracción a 0..100
    elseif ($min >= 0.0 && $max <= 1.0) {
        foreach ($raw_vals as $k=>$v) {
            if ($v === null) $v = 0.0;
            $vals_for_0_100[$k] = $v * 100.0;
        }
    }
    // 5) Caso ya 0..100 o valores fuera de los anteriores: clamp a 0..100
    else {
        foreach ($raw_vals as $k=>$v) {
            if ($v === null) $v = 50.0;
            $vv = (float)$v;
            if ($vv < 0) $vv = 0; if ($vv > 100) $vv = 100;
            $vals_for_0_100[$k] = $vv;
        }
    }
}




// Llamada al calculador v2 (devuelve X,Y en -5..+5)
list($hof_calc_x, $hof_calc_y) = calcula_ejes_hofstede_v2($vals_for_0_100);

// Asignamos las variables esperadas por el resto del archivo (misma escala -5..+5)
$hof_x = (float)$hof_calc_x;
$hof_y = (float)$hof_calc_y;


// --- Derivar tipo (cuadrante) a partir de las coordenadas Hofstede (como en la lógica previa)
$cultura_tipo_hof = '';
if ($hof_x < 0 && $hof_y > 0)        $cultura_tipo_hof = 'Clan';
elseif ($hof_x >= 0 && $hof_y > 0)   $cultura_tipo_hof = 'Adhocracia';
elseif ($hof_x < 0 && $hof_y <= 0)   $cultura_tipo_hof = 'Jerárquica';
elseif ($hof_x >= 0 && $hof_y <= 0)  $cultura_tipo_hof = 'Mercado';

// Tipo final canónico para toda la página: prioriza lo que venga en usuarios.cultura_empresa_tipo
$cultura_tipo_final_raw = !empty($cultura_empresa_tipo) ? $cultura_empresa_tipo : $cultura_tipo_hof;
$cultura_tipo_final     = cultura_key_canon($cultura_tipo_final_raw);

// Respaldo si quedó vacío (por si hay datos raros)
if (empty($cultura_tipo_final)) {
  $cultura_tipo_final = !empty($cultura_tipo_hof) ? $cultura_tipo_hof : '';
}

// Ahora sí poblar los textos visibles que usa la plantilla
$orientacion_cultura = $info_culturas[$cultura_tipo_final]['nombre']      ?? '';
$subtitulo_cultura   = $info_culturas[$cultura_tipo_final]['subtitulo']   ?? '';
$descripcion_cultura = $info_culturas[$cultura_tipo_final]['descripcion'] ?? '';


/* END PATCH */













?>











<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Propósito y valores — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tipografía -->
  <link rel="preconnect" href="https://use.typekit.net" crossorigin>
  <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">

  <!-- Valírica Design System -->
  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
    /* === Análisis Equipos Page Specific Styles === */

    /* Nota: .wrap, .grid-sidebar y .card base ya están en valirica-design-system.css */

    /* ===== Selector de áreas ===== */
.areas-selector{
  display:flex;
  flex-wrap:wrap;
  gap:var(--space-3);
  margin-top:var(--space-3);
}

.grid-analytics {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: var(--space-6);
}

@media (max-width: 1100px){
  .grid-analytics {
    grid-template-columns: 1fr;
  }
}


.area-chip{
  padding:var(--space-3) var(--space-4);
  border-radius:var(--radius);
  font-size:var(--text-sm);
  font-weight:var(--font-semibold);
  background:#f6f8f9;
  color:var(--c-secondary);
  border:1px solid rgba(1,33,51,.08);
  transition:.15s ease;
}

.area-chip:hover{
  background:#eef3f5;
}

.area-chip.is-active{
  background:var(--c-accent);
  color:#fff;
  border-color:var(--c-accent);
  box-shadow:0 4px 12px rgba(239,127,27,.35);
}

.area-chip.area-custom{
  border-style:dashed;
  font-weight:700;
}


/* ===== Listado de equipo ===== */
.equipo-listado{
  display:flex;
  flex-direction:column;
  gap:var(--space-3);
  margin-top:var(--space-2);
}

.equipo-item{
  display:flex;
  align-items:center;
  gap:var(--space-3);
  padding:var(--space-3) var(--space-4);
  border-radius:var(--radius);
  background:#f6f8f9;
  border:1px solid rgba(1,33,51,.06);
  cursor:pointer;
  transition:.15s ease;
}

.equipo-item:hover{
  background:#eef3f5;
}

.equipo-check{
  width:18px;
  height:18px;
  accent-color: var(--c-accent);
}

.equipo-info{
  display:flex;
  flex-direction:column;
}

.equipo-info strong{
  font-size:var(--text-base);
  color:var(--c-secondary);
}

.equipo-cargo{
  font-size:var(--text-xs);
  color:#6b7c85;
}

    
    
/* ===== TEAM INSIGHTS ===== */
.team-insights{
  margin-top:var(--space-8);
}

.team-insights-header{
  margin-bottom:var(--space-5);
}

.team-insights-header h4{
  color:var(--c-secondary);
  font-size:var(--text-lg);
}

.team-insights-header p{
  font-size:var(--text-xs);
  color:#6b7c85;
}

/* Grid */
.team-insights-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:var(--space-5);
}

@media (max-width:1024px){
  .team-insights-grid{
    grid-template-columns:1fr;
  }
}

/* Card base */
.team-card{
  background:#fff;
  border-radius:var(--radius);
  padding:var(--space-5);
  border:1px solid rgba(1,33,51,.08);
  box-shadow:var(--shadow);
  display:flex;
  flex-direction:column;
  gap:var(--space-4);
}

.team-card.accent{
  border-left:4px solid var(--c-accent);
}

.team-card.neutral{
  border-left:4px solid #184656;
}

/* Header */
.team-card-header{
  display:flex;
  gap:var(--space-3);
  align-items:flex-start;
}

.team-card-icon{
  font-size:var(--text-xl);
  line-height:1;
}

.team-card-header h5{
  margin:0;
  font-size:var(--text-lg);
  color:var(--c-secondary);
}

.team-card-header span{
  font-size:var(--text-xs);
  color:#6b7c85;
}

/* Body */
.team-card-body{
  display:flex;
  flex-direction:column;
  gap:var(--space-4);
}

.team-card-body.two-cols{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:var(--space-4);
}

@media (max-width:768px){
  .team-card-body.two-cols{
    grid-template-columns:1fr;
  }
}

.team-col h6,
.sync-block h6{
  font-size:var(--text-xs);
  font-weight:var(--font-bold);
  margin-bottom:var(--space-2);
  color:var(--c-secondary);
}

.team-col.success h6{ color:#1b7f5a; }
.team-col.warning h6,
.sync-block.warning h6{ color:#b45309; }

.team-col ul,
.sync-block ul{
  padding-left:var(--space-5);
  font-size:var(--text-xs);
  color:#474644;
}

/* Pills */
.pill-list{
  display:flex;
  flex-wrap:wrap;
  gap:var(--space-2);
  list-style:none;
  padding-left:0;
}

.pill-list li{
  padding:var(--space-2) var(--space-3);
  border-radius:9999px;
  font-size:var(--text-xs);
  background:#f6f8f9;
  border:1px solid rgba(1,33,51,.08);
}

.sync-block.warning .pill-list li{
  background:#fff5f0;
  border-color:rgba(239,127,27,.35);
}

/* Footer */
.team-card-footer{
  font-size:12px;
  color:#6b7c85;
  border-top:1px dashed rgba(1,33,51,.08);
  padding-top:10px;
}

/* Alignment circle (canvas) */
.alignment-wrap { position: relative; width: 100%; aspect-ratio: 1 / 1; min-height: 320px; }
.alignment-canvas { width: 100%; height: 100%; display: block; border-radius: 12px; }

/* Tooltip for canvas */
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

/* Team count badge */
.team-count-badge {
  display:inline-flex; align-items:center; gap:8px; background:linear-gradient(90deg,var(--c-accent),#ffb37a); color:#fff; font-weight:700; padding:6px 10px; border-radius:999px; font-size:13px; box-shadow:0 6px 18px rgba(2,34,51,0.12); margin-left:0px; vertical-align:middle;
}
.team-count-badge .count-num { background:rgba(255,255,255,0.12); padding:4px 8px; border-radius:12px; min-width:36px; text-align:center; font-feature-settings:"tnum" 1; }
@media (max-width:680px){ .team-count-badge { font-size:12px; padding:5px 8px; margin-left:8px; } .team-count-badge .count-num { min-width:30px; padding:3px 6px; } }

.card-equipo-compacto {
  display: flex;
  flex-direction: column;

  min-height: 420px;
  max-height: 70vh;   /* 🔥 clave real */
  height: 100%;
}

.equipo-form {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0; /* 🔥 importante para que el scroll funcione */
}

.areas-compact {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.area-pill {
  padding: 6px 10px;
  font-size: 12px;
  border-radius: 20px;
  background: #f3f5f6;
  border: 1px solid rgba(1,33,51,.08);
  color: #184656;
  text-decoration: none;
  transition: all .15s ease;
}

.area-pill:hover {
  background: #e7ecef;
}

.area-pill.is-active {
  background: #EF7F1B;
  color: white;
  border-color: #EF7F1B;
}

.miembros-scroll {
  flex: 1;
  min-height: 0;          /* 🔥 absolutamente necesario */
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-right: 6px;
}

.miembro-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  padding: 6px 8px;
  border-radius: 8px;
  transition: background .15s ease;
}

.miembro-item:hover {
  background: #f3f5f6;
}

.miembro-item small {
  display: block;
  font-size: 11px;
  opacity: .6;
}

.equipo-footer {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(1,33,51,.08);
}




  </style>
</head>
<body>







<!-- ===== Header idéntico al dashboard ===== -->

<?php
// ======================================================
// HEADER — usa KPIs YA CALCULADOS en el análisis
// (NO recalcula nada)
// ======================================================

// Asegurar valores por si el equipo está vacío
$promedio_general = $promedio_general ?? 0;
$energia_status   = $energia_status   ?? 'Media';

// Construir chips (solo presentación)
list($aline_label, $aline_class, $aline_icon) =
    chip_for_alineacion($promedio_general);

list($mot_label, $mot_class, $mot_icon) =
    chip_for_motivacion($energia_status);
?>

<!-- Barra superior -->
<?php require __DIR__ . '/a-header-desktop-brand.php'; ?>







  <!-- ===== Contenido (mismo grid / cards) ===== -->
  <div class="wrap">
    <div class="grid-analytics">

      <!-- Columna izquierda -->
<!-- NUEVO BLOQUE FULL WIDTH -->



<div class="card card-equipo-compacto">

  <h3 style="margin-bottom:12px;">Equipo</h3>

  <?php if (!empty($areas_trabajo)): ?>
    <div class="areas-compact">
      <?php foreach ($areas_trabajo as $area): 
        $active = ((string)$area['id'] === (string)$area_seleccionada);
      ?>
        <a
          href="?area_id=<?= (int)$area['id'] ?>"
          class="area-pill <?= $active ? 'is-active' : '' ?>"
        >
          <?= h($area['nombre_area']) ?>
        </a>
      <?php endforeach; ?>

      <a
        href="?area_id=custom"
        class="area-pill <?= $area_seleccionada === 'custom' ? 'is-active' : '' ?>"
      >
        Custom
      </a>
    </div>
  <?php endif; ?>

  <form method="GET" class="equipo-form">

    <input type="hidden" name="area_id" value="<?= h($area_seleccionada) ?>">

    <div class="miembros-scroll">

      <?php if (!empty($equipo_miembros)): ?>

        <?php
        $seleccionados = [];
        $no_seleccionados = [];

        foreach ($equipo_miembros as $m) {
            if (!empty($equipo_ids_seleccionados) && in_array((int)$m['id'], $equipo_ids_seleccionados, true)) {
                $seleccionados[] = $m;
            } else {
                $no_seleccionados[] = $m;
            }
        }

        $equipo_miembros = array_merge($seleccionados, $no_seleccionados);
        ?>

        <?php foreach ($equipo_miembros as $m):

          if (!empty($equipo_ids_seleccionados)) {
              $is_checked = in_array((int)$m['id'], $equipo_ids_seleccionados, true);
          } else {
              $is_checked = ($area_seleccionada !== 'custom');
          }

          $nombre_completo = trim($m['nombre_persona'] . ' ' . $m['apellido']);
        ?>

          <label class="miembro-item">
            <input
              type="checkbox"
              name="equipo_ids[]"
              value="<?= (int)$m['id'] ?>"
              <?= $is_checked ? 'checked' : '' ?>
            />
            <span>
              <?= h($nombre_completo) ?>
              <?php if (!empty($m['cargo'])): ?>
                <small><?= h($m['cargo']) ?></small>
              <?php endif; ?>
            </span>
          </label>

        <?php endforeach; ?>

      <?php else: ?>
        <p style="opacity:.7;">No hay personas registradas.</p>
      <?php endif; ?>

    </div>

    <div class="equipo-footer">
      <button type="submit" class="btn-cta-primary btn-sm">
        Actualizar
      </button>
    </div>

  </form>

</div>




            
<div class="card">
    <h3>Mapa de orientación cultural</h3>
    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Cultura Proyectada</span>
      <span class="vl-tag">Cultura Real</span>
    </div>
    <p style="margin-bottom:16px;">
      Punto ideal ponderado por propósito y valores. Los ejes siguen tu convención:
      <strong>X</strong> (Interno ↔ Externo) y <strong>Y</strong> (Controlado ↔ Flexible).
    </p>
    <canvas id="cuadranteCultural" style="width:100%; max-height:460px;"></canvas>

  </div>  

   <!-- Tarjeta: Alineación Cultural del Equipo (Círculo concéntrico) -->
  <div class="card" id="card-alineacion-cultural">
    <h3>Alineación cultural</h3>
    <span class="team-count-badge" role="status" aria-live="polite" aria-label="<?php echo count($perfiles); ?> miembros analizados">
      <span class="count-num"><?php echo count($perfiles); ?></span>
      <span class="count-label" style="opacity:.95;font-weight:600;font-size:13px;">Miembros analizados</span>
    </span>

    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Dimensiones Culturales</span>
      <span class="vl-tag">Social Networking</span>
    </div>

    <p style="margin-bottom:16px;">
      Miembros de tu equipo según su
      <strong>alineación cultural</strong>
      (entre más cerca del centro, mayor alineación e impacto en la cultura).
    </p>

    <div class="alignment-wrap">
      <canvas id="vlAlignmentCanvas" class="alignment-canvas"></canvas>
      <div id="vlTooltip" class="vl-tooltip"></div>
    </div>
  </div>





 
<!-- ===== FILA DE 3 COLUMNAS FULL WIDTH ===== -->



  <!-- ========================= -->
  <!-- COLUMNA 1: Propósito + Valores -->
  <!-- ========================= -->
  <div>

    <!-- Propósito -->
    <div class="card">
      <h3 style="cursor:pointer;" onclick="toggleSection('proposito-box')">
        Propósito de marca <span style="font-size:14px;">▾</span>
      </h3>

      <div id="proposito-box" style="display:none;">

        <?php if ($proposito_txt === '' && empty($valores_list)): ?>
          <p style="opacity:.75; margin-bottom:var(--space-4);">
            Aún no has definido propósito ni valores.
          </p>
          <a class="btn-cta-primary" href="cultura_ideal.php?usuario_id=<?= (int)$user_id ?>">
            Definir ahora
          </a>
        <?php else: ?>
          <?php if ($proposito_txt !== ''): ?>
            <p style="font-size:var(--text-lg); line-height:1.6;">
              <?= h($proposito_txt) ?>
            </p>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </div>

    <!-- Valores -->
    <div class="card">
      <h3 style="cursor:pointer;" onclick="toggleSection('valores-box')">
        Valores de la marca <span style="font-size:14px;">▾</span>
      </h3>

      <div id="valores-box" style="display:none;">

        <?php if (empty($valores_list)): ?>
          <p style="opacity:.75;">Aún no has definido valores.</p>
        <?php else: ?>
          <?php foreach ($valores_list as $v): ?>
            <div style="margin-top:var(--space-4);">
              <strong style="color:#EF7F1B; font-size:var(--text-lg);">
                <?= h($v['titulo']) ?>
              </strong>
              <p style="margin-top:var(--space-2);">
                <?= h($v['descripcion']) ?>
              </p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>
    </div>

  </div>


  <!-- ========================= -->
  <!-- COLUMNA 2: Cultura proyectada -->
  <!-- ========================= -->
  <div class="card">

    <h5>Cultura proyectada</h5>

    <?php if ($orientacion_cultura): ?>
      <div style="margin-top:18px;">
        <strong style="color:#184656;">
          <?= h($orientacion_cultura) ?>
        </strong><br>

        <span style="color:#6a6a6a;">
          <?= h($subtitulo_cultura) ?>
        </span>

        <p style="margin-top:8px;">
          <?= $descripcion_cultura ?>
        </p>

        <?php if (!empty($info_culturas[$cultura_tipo_final]['fortalezas'])): ?>
          <div style="margin-top:10px;">
            <strong style="color:#184656;">Fortalezas</strong>
            <ul style="margin:6px 0 0 18px;">
              <?php foreach ($info_culturas[$cultura_tipo_final]['fortalezas'] as $f): ?>
                <li><?= h($f) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($info_culturas[$cultura_tipo_final]['alertas'])): ?>
          <div style="margin-top:10px;">
            <strong style="color:#184656;">Alertas</strong>
            <ul style="margin:6px 0 0 18px;">
              <?php foreach ($info_culturas[$cultura_tipo_final]['alertas'] as $a): ?>
                <li><?= h($a) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

      </div>
    <?php endif; ?>

  </div>


  <!-- ========================= -->
  <!-- COLUMNA 3: Sección desarrollo -->
  <!-- ========================= -->
  <div class="card">

    <div class="team-card-header">
      <span class="team-card-icon">🚧</span>
      <div>
        <h5>Sección en desarrollo</h5>
        <span>
          Estamos construyendo esta lectura estratégica del equipo
        </span>
      </div>
    </div>

    <div class="team-card-body">
      <p style="font-size:14px; line-height:1.5; color:#474644;">
        Esta sección estará disponible próximamente y te permitirá comprender,
        con profundidad y contexto, cómo se comporta tu equipo, dónde existen
        oportunidades reales de mejora y qué decisiones estratégicas puedes tomar
        para gestionarlo mejor.
      </p>

      <p style="font-size:14px; line-height:1.5; color:#474644;">
        Nuestro objetivo es que esta lectura se convierta en una herramienta
        clara, accionable y de alto valor para liderar tus equipos con criterio
        y no solo con intuición.
      </p>
    </div>

    <div class="team-card-footer">
      Próximamente disponible en Valírica
    </div>

  </div>





      
      
      
    </div>
  </div>

  <!-- === JS de invitaciones (mismo comportamiento del dashboard) === -->
  
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1"></script>
<script>
  // Registro explícito del plugin en Chart.js 4
  Chart.register(window['chartjs-plugin-annotation']);
</script>

<script>
(function(){
  const ctx = document.getElementById('cuadranteCultural');
  if(!ctx) return;

const x = <?= json_encode($ejeX) ?>;
const y = <?= json_encode($ejeY) ?>;

// Coordenadas Hofstede (proyección -5..+5) calculadas en PHP
const hofX = <?= json_encode($hof_x) ?>;
const hofY = <?= json_encode($hof_y) ?>;


  const valoresData   = <?= json_encode($valores_puntos, JSON_UNESCAPED_UNICODE) ?>;
  const propositoData = <?= json_encode($proposito_punto, JSON_UNESCAPED_UNICODE) ?>;

  let cuadrante = -1;
if      (hofX < 0 && hofY > 0)  cuadrante = 0; // Clan
else if (hofX >= 0 && hofY > 0) cuadrante = 1; // Adhocracia
else if (hofX < 0 && hofY <= 0) cuadrante = 2; // Jerárquica
else if (hofX >= 0 && hofY <= 0)cuadrante = 3; // Mercado

  const quadBg = ['transparent','transparent','transparent','transparent'];
  if (cuadrante !== -1) quadBg[cuadrante] = 'rgba(239,127,27,0.06)';  // naranja suave

  new Chart(ctx, {
    type: 'scatter',
    data: {
      datasets: [
  {
    label: 'Cultura de la empresa (Hofstede)',
    data: [{ x: hofX, y: hofY }],
    pointRadius: 8,
    pointHoverRadius: 10,
    pointBorderWidth: 2,
    pointBackgroundColor: '#FFFFFF',
    pointBorderColor: '#184656'
  },
  {
    label: 'Equipo',
    data: <?= json_encode($equipo_puntos, JSON_UNESCAPED_UNICODE) ?>,
    pointRadius: 4,
    pointHoverRadius: 6,
    pointBackgroundColor: '#184656',
    pointBorderColor: '#184656',
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
            color: ctx => ctx.tick.value===0 ? 'rgba(1,33,51,0.35)' : 'transparent',
            lineWidth: ctx => ctx.tick.value===0 ? 1 : 0

          }
        },
        y: {
          min:-5, max:5,
          title:{ display:true, text:'← Controlado | Flexible →', color:'#184656', font:{weight:'700'} },
          ticks:{ display:false },
          grid:{
            drawTicks:false,
            borderColor:'transparent',
            color: ctx => ctx.tick.value===0 ? 'rgba(1,33,51,0.35)' : 'transparent',
            lineWidth: ctx => ctx.tick.value===0 ? 1 : 0

          }
        }
      },
      plugins: {
        legend: { display:false },
        annotation: {
  annotations: {
    q1: { // Clan (Colaborativa)
      type:'box', xMin:-5,xMax:0, yMin:0,yMax:5,
      backgroundColor: quadBg[0],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Cultura Colaborativa',
        position: { x: 'center', y: 'center' },
        color: '#184656',
        font: { weight: '700' },
        backgroundColor: 'rgba(255,255,255,0.85)',
        padding: 6,
        borderRadius: 8,
        borderWidth: 1,
        borderColor: 'rgba(1,33,51,0.12)'
      }
    },
    q2: { // Adhocracia (Ágil)
      type:'box', xMin:0,xMax:5,  yMin:0,yMax:5,
      backgroundColor: quadBg[1],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Cultura Ágil',
        position: { x: 'center', y: 'center' },
        color: '#184656',
        font: { weight: '700' },
        backgroundColor: 'rgba(255,255,255,0.85)',
        padding: 6,
        borderRadius: 8,
        borderWidth: 1,
        borderColor: 'rgba(1,33,51,0.12)'
      }
    },
    q3: { // Jerárquica (Estructurada)
      type:'box', xMin:-5,xMax:0, yMin:-5,yMax:0,
      backgroundColor: quadBg[2],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Cultura Estructurada',
        position: { x: 'center', y: 'center' },
        color: '#184656',
        font: { weight: '700' },
        backgroundColor: 'rgba(255,255,255,0.85)',
        padding: 6,
        borderRadius: 8,
        borderWidth: 1,
        borderColor: 'rgba(1,33,51,0.12)'
      }
    },
    q4: { // Mercado (Orientada a Resultados)
      type:'box', xMin:0,xMax:5,  yMin:-5,yMax:0,
      backgroundColor: quadBg[3],
      borderWidth: 0,
      label: {
        display: true,
        content: 'Orientada a Resultados',
        position: { x: 'center', y: 'center' },
        color: '#184656',
        font: { weight: '700' },
        backgroundColor: 'rgba(255,255,255,0.85)',
        padding: 6,
        borderRadius: 8,
        borderWidth: 1,
        borderColor: 'rgba(1,33,51,0.12)'
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


<script>
/* ===========================================
   Alineación Cultural — Círculo concéntrico
   Centro = 100%, Borde = 0%, 10 anillos
   - Único borde fuerte: anillo exterior (verde)
   - Anillos internos: gris tenue y delgados
   =========================================== */

// 1) Datos desde PHP (array $perfiles)
const VL_PERFILES = <?php echo json_encode($perfiles, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?> || [];

// 2) Paleta de marca coherente con el resto
const VL_COLORS  = ["#EF7F1B"];           // avatares naranja
const VL_GREEN      = "#184656";          // borde exterior fuerte
const VL_GRAY_SOFT  = "rgba(0,0,0,0.06)"; // anillos internos + ejes suaves
const VL_AXIS_COLOR = VL_GRAY_SOFT;

const VL_TEXT_COLOR = getComputedStyle(document.documentElement).getPropertyValue("--c-body") || "#474644";

// 3) Utilidades
const DPR = Math.max(1, window.devicePixelRatio || 1);
function initialsFromName(name = "") {
  const parts = String(name).trim().split(/\s+/);
  const a = (parts[0] || "").charAt(0).toUpperCase();
  const b = (parts[1] || "").charAt(0).toUpperCase();
  return (a + b) || (a || "?");
}
function deg2rad(deg){ return deg * Math.PI/180; }

// Golden angle para distribuir (evita clusters)
const GOLDEN_ANGLE = 137.508;

// Mapea % → radio (centro = 100%, borde = 0%)
function pctToRadius(pct, maxR){
  const p = Math.max(0, Math.min(100, pct));
  return ((100 - p) / 100) * maxR; // 0% → maxR (borde), 100% → 0 (centro)
}

// 4) Dibujo principal
(function initAlignmentCircle(){
  const canvas = document.getElementById("vlAlignmentCanvas");
  const tooltip = document.getElementById("vlTooltip");
  if(!canvas) return;

  const ctx = canvas.getContext("2d");

  function resizeCanvas(){
    const rect = canvas.getBoundingClientRect();
    canvas.width  = Math.floor(rect.width  * DPR);
    canvas.height = Math.floor(rect.height * DPR);
    draw();
  }

  // Preparar data de puntos en coordenadas canvas para hit-testing
  let points = [];

  function draw(){
    const W = canvas.width, H = canvas.height;
    const cx = W/2, cy = H/2;
    ctx.clearRect(0,0,W,H);

    // Tomamos el menor lado para el radio máximo, dejando padding
    const pad = 36 * DPR;
    const R   = Math.min(cx, cy) - pad;

    // --- 10 Anillos (0..100 → 10 pasos) ---
    for(let i=1;i<=10;i++){
      const rr = (R * i) / 10;
      ctx.beginPath();
      ctx.arc(cx, cy, rr, 0, Math.PI*2);

      if (i === 10) {
        ctx.lineWidth = 2 * DPR;
        ctx.strokeStyle = VL_GREEN;
      } else {
        ctx.lineWidth = 1 * DPR;
        ctx.strokeStyle = VL_GRAY_SOFT;
      }
      ctx.stroke();
    }

    // Ejes cruzados suaves
    ctx.save();
    ctx.strokeStyle = VL_AXIS_COLOR;
    ctx.lineWidth = 1 * DPR;
    ctx.beginPath(); ctx.moveTo(cx - R, cy); ctx.lineTo(cx + R, cy); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(cx, cy - R); ctx.lineTo(cx, cy + R); ctx.stroke();
    ctx.restore();

    // --- Puntos (miembros) ---
    points = [];

    const avatarRadius = Math.max(18, Math.floor(R * 0.075));
    const fontSize = Math.max(10, Math.floor(avatarRadius * 0.60));

    VL_PERFILES.forEach((p, idx) => {
      const pct  = Number(p.alineacion) || 0;
      const name = String(p.nombre || "\u2014");
      const ini  = initialsFromName(name);

      const angleDeg = (idx * GOLDEN_ANGLE) % 360;
      const theta = deg2rad(angleDeg);

      const r = pctToRadius(pct, R);

      const x = cx + r * Math.cos(theta);
      const y = cy + r * Math.sin(theta);

      // Avatar (círculo)
      ctx.save();
      ctx.beginPath();
      ctx.arc(x, y, avatarRadius, 0, Math.PI*2);
      ctx.closePath();
      ctx.fillStyle = VL_COLORS[idx % VL_COLORS.length];
      ctx.fill();

      ctx.lineWidth = 2 * DPR;
      ctx.strokeStyle = "rgba(255,255,255,0.9)";
      ctx.stroke();

      // Iniciales
      ctx.fillStyle = "#FFFFFF";
      ctx.font = `${fontSize * DPR}px gelica, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif`;
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.fillText(ini, x, y);
      ctx.restore();

      points.push({
        x, y, r: avatarRadius,
        name, pct: Math.round(pct * 10) / 10
      });
    });
  }

  // Hover: mostrar tooltip si el cursor está sobre un avatar
  function handleMove(ev){
    const rect = canvas.getBoundingClientRect();
    const mx = (ev.clientX - rect.left) * DPR;
    const my = (ev.clientY - rect.top)  * DPR;

    let hit = null;
    for(const pt of points){
      const dx = mx - pt.x;
      const dy = my - pt.y;
      if (dx*dx + dy*dy <= (pt.r*pt.r)) { hit = pt; break; }
    }

    if(hit){
      tooltip.style.opacity = 1;
      tooltip.textContent = `${hit.name} \u2014 ${hit.pct}%`;
      tooltip.style.left = `${ev.clientX - rect.left}px`;
      tooltip.style.top  = `${ev.clientY - rect.top}px`;
    } else {
      tooltip.style.opacity = 0;
    }
  }

  window.addEventListener("resize", resizeCanvas);
  canvas.addEventListener("mousemove", handleMove);
  resizeCanvas(); // primera pintura
})();
</script>

<script>
function toggleSection(id){
  const el = document.getElementById(id);
  if(!el) return;

  if(el.style.display === "none"){
    el.style.display = "block";
  } else {
    el.style.display = "none";
  }
}
</script>


</body>
</html>
