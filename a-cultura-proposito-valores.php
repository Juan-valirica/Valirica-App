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
$u = stmt_get_result($stmt)->fetch_assoc() ?: [];
$stmt->close();

$empresa = $u['empresa'] ?? 'Nombre de la empresa';
$logo    = $u['logo']    ?? '/uploads/logo-192.png';
$rol_usuario = (string)($u['rol'] ?? '');
$cultura_empresa_tipo = (string)($u['cultura_empresa_tipo'] ?? '');

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
$result_cultura = stmt_get_result($stmt_cultura);
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
$result_valores = stmt_get_result($stmt_valores);

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

/* 2) Alineación cultural promedio del equipo (0..100) */
$stmt_equipo = $conn->prepare("
  SELECT hofstede_poder as distancia_poder, hofstede_individualismo as individualismo,
         hofstede_resultados as masculinidad, hofstede_incertidumbre as incertidumbre,
         hofstede_largo_plazo as largo_plazo, hofstede_espontaneidad as indulgencia
  FROM equipo WHERE usuario_id = ?
");
$stmt_equipo->bind_param("i", $user_id);
$stmt_equipo->execute();
$res_equipo = stmt_get_result($stmt_equipo);

$total_alineacion = 0; $n = 0;
while ($row = $res_equipo->fetch_assoc()) {
  $suma = 0; $d=0;
  foreach ($valores_ideales as $k=>$ideal) {
    if (!isset($row[$k])) continue;
    $real = (float)$row[$k]; // -1..1
    $aline = 1 - (abs($real - (float)$ideal) / 2);
    $aline = max(0, min(1, $aline));
    $suma += $aline; $d++;
  }
  if ($d>0) { $total_alineacion += ($suma/$d); $n++; }
}
$stmt_equipo->close();
$promedio_general = $n>0 ? round(($total_alineacion/$n)*100, 1) : 0.0;

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
$sen = stmt_get_result($stmt_sen)->fetch_assoc() ?: ['visual'=>0,'auditivo'=>0,'kinestesico'=>0];
$stmt_sen->close();
$prom_sens = ['visual'=>(float)$sen['visual'], 'auditivo'=>(float)$sen['auditivo'], 'kinestesico'=>(float)$sen['kinestesico']];
$hay_datos_sensoriales = array_sum($prom_sens) > 0;
$dom_sensorial = $hay_datos_sensoriales ? array_keys($prom_sens, max($prom_sens))[0] : null;
$estilo_equipo_aprend = $dom_sensorial ? $LABELS_SENSORIALES[$dom_sensorial] : 'Sin datos';
$aprend_alineado = $dom_sensorial ? ($NORM($LABELS_SENSORIALES[$dom_sensorial]) === $_estilo_cultura_norm) : false;

/* 5) Chips para KPIs */
// Construir chips de alineación y motivación con la misma lógica del dashboard
list($aline_label, $aline_class, $aline_icon) = chip_for_alineacion($promedio_general);
list($mot_label,   $mot_class,   $mot_icon)   = chip_for_motivacion($energia_status);


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
    $res = stmt_get_result($stmt);
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
$datos_proposito = stmt_get_result($stmt)->fetch_assoc() ?: [];
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
$res_val = stmt_get_result($stmt);

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


/* ===== PUNTOS DEL EQUIPO EN EL CUADRANTE (Hofstede → eje X/Y) =====
   Escala DB: -1..+1  →  Escala del gráfico: -5..+5
   Heurística coherente con los ejes:
   - X (Interno ↔ Externo): +Individualismo vs (algo de) Distancia de poder
   - Y (Controlado ↔ Flexible): +Indulgencia vs (algo de) Aversión a la incertidumbre
*/
// --- REEMPLAZAR DESDE AQUÍ: cálculo antiguo de equipo_puntos
$equipo_puntos = [];
$q = $conn->prepare("
  SELECT id, nombre_persona,
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
$rs = stmt_get_result($q);

// Nueva lógica: para cada miembro detectamos la escala y usamos la función v2
while ($r = $rs->fetch_assoc()) {

    // Valores crudos tomados de la fila (pueden venir en -5..5, -1..1, 0..5, 0..1 o 0..100)
    $raw_row = [
        'individualismo'  => isset($r['individualismo'])  ? (float)$r['individualismo']  : null,
        'masculinidad'    => isset($r['masculinidad'])    ? (float)$r['masculinidad']    : null,
        'incertidumbre'   => isset($r['incertidumbre'])   ? (float)$r['incertidumbre']   : null,
        'distancia_poder' => isset($r['distancia_poder']) ? (float)$r['distancia_poder'] : null,
        'largo_plazo'     => isset($r['largo_plazo'])     ? (float)$r['largo_plazo']     : null,
        'indulgencia'     => isset($r['indulgencia'])     ? (float)$r['indulgencia']     : null
    ];

    // Detectar min/max por fila (si falta algún valor usamos 0 como referencia)
    $min = PHP_FLOAT_MAX; $max = -PHP_FLOAT_MAX;
    foreach ($raw_row as $v) {
        if ($v === null) continue;
        $min = min($min, $v);
        $max = max($max, $v);
    }
    // Fallback si todos null
    if ($min === PHP_FLOAT_MAX) { $min = 0; $max = 0; }

    // Convertir cada campo a 0..100 según la escala detectada (misma lógica que usaste para cultura_ideal)
    $vals_for_0_100 = [];
    // 1) -5..+5
    if ($min >= -5.0 && $max <= 5.0) {
        foreach ($raw_row as $k=>$v) {
            $vv = $v === null ? 0.0 : $v;
            $vals_for_0_100[$k] = (($vv + 5.0) / 10.0) * 100.0; // (v+5)*10
        }
    }
    // 2) -1..+1
    elseif ($min >= -1.0 && $max <= 1.0) {
        foreach ($raw_row as $k=>$v) {
            $vv = $v === null ? 0.0 : $v;
            $vals_for_0_100[$k] = (($vv + 1.0) / 2.0) * 100.0;
        }
    }
    // 3) 0..5
    elseif ($min >= 0.0 && $max <= 5.0) {
        foreach ($raw_row as $k=>$v) {
            $vv = $v === null ? 0.0 : $v;
            $vals_for_0_100[$k] = $vv * 20.0;
        }
    }
    // 4) 0..1
    elseif ($min >= 0.0 && $max <= 1.0) {
        foreach ($raw_row as $k=>$v) {
            $vv = $v === null ? 0.0 : $v;
            $vals_for_0_100[$k] = $vv * 100.0;
        }
    }
    // 5) ya 0..100 o fuera de rango: clamp
    else {
        foreach ($raw_row as $k=>$v) {
            $vv = $v === null ? 50.0 : (float)$v;
            if ($vv < 0) $vv = 0; if ($vv > 100) $vv = 100;
            $vals_for_0_100[$k] = $vv;
        }
    }

    // Llamada a la función v2 (devuelve X,Y en -5..+5)
        // Llamada a la función v2 (puede devolver -1..+1 o -5..+5 según versión)
    list($px, $py) = calcula_ejes_hofstede_v2($vals_for_0_100);

    // --- PARCHE DEFENSIVO: si la función devolvió -1..+1 lo escalamos a -5..+5
    // (si ya está en -5..+5 los dejamos intactos).
    if (abs($px) <= 1.01) $px = $px * 5.0;
    if (abs($py) <= 1.01) $py = $py * 5.0;

    // Clamp adicional por seguridad (no necesario pero recomendable)
    $px = max(-5.0, min(5.0, (float)$px));
    $py = max(-5.0, min(5.0, (float)$py));

    $label = trim((string)($r['nombre_persona'] ?? '—'));
    if ($label !== '') {
        $equipo_puntos[] = [
            'x' => round($px, 2),
            'y' => round($py, 2),
            'label' => $label,
            'id' => $r['id'] ?? null
        ];
    }

}
$q->close();
// --- HASTA AQUÍ (reemplazando el bloque antiguo)





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










// Tipo final canónico a partir de BD o Hofstede
$cultura_tipo_final_raw = !empty($cultura_empresa_tipo) ? $cultura_empresa_tipo : $cultura_tipo_hof;
$cultura_tipo_final     = cultura_key_canon($cultura_tipo_final_raw);

// ⚠️ Respaldo por si sigue vacío (datos raros en BD)
if (empty($cultura_tipo_final)) {
  $cultura_tipo_final = !empty($cultura_tipo_hof) ? $cultura_tipo_hof : $cultura_tipo;
}





?>











<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Propósito y valores — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Valírica Design System -->
  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
    /* === Page Specific Styles === */

    /* Nota: .wrap, .grid-sidebar y .card base ya están en valirica-design-system.css */
  </style>
</head>
<body>







<!-- ===== Header idéntico al dashboard ===== -->

  <!-- Barra superior -->
<?php require __DIR__ . '/a-header-desktop-brand.php'; ?>






  <!-- ===== Contenido (mismo grid / cards) ===== -->
  <div class="wrap">
    <div class="grid-sidebar">
      <!-- Columna izquierda -->
      
      
      <!-- ===== Columna izquierda ===== -->
<section>
  <!-- Propósito -->
  <div class="card">
    <h3>Propósito de marca</h3>
    <div class="vl-tags" style="margin-bottom:16px;">
      <span class="vl-tag">Dirección</span>
      <span class="vl-tag">Sentido</span>
    </div>

    <?php if ($proposito_txt === '' && empty($valores_list)): ?>
      <p style="opacity:.75; margin-bottom:var(--space-4);">
        Aún no has definido propósito ni valores.
      </p>
      <a class="btn-cta-primary" href="cultura_ideal.php?usuario_id=<?= (int)$user_id ?>">Definir ahora</a>
    <?php else: ?>
      <?php if ($proposito_txt !== ''): ?>
        <p style="font-size:var(--text-lg); line-height:1.6;"><?= h($proposito_txt) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Valores de marca -->
  <div class="card">
    <h3>Valores de la marca</h3>
    <div class="vl-tags" style="margin-bottom:var(--space-4);">
      <span class="vl-tag">Comportamientos</span>
      <span class="vl-tag">Decisiones</span>
    </div>

    <?php if (empty($valores_list)): ?>
      <p style="opacity:.75;">Aún no has definido valores.</p>
    <?php else: ?>
      <?php foreach ($valores_list as $v): ?>
        <div style="margin-top:var(--space-4);">
          <strong style="color:#EF7F1B; font-size:var(--text-lg);"><?= h($v['titulo']) ?></strong>
          <p style="margin-top:var(--space-2);"><?= h($v['descripcion']) ?></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Columna derecha ===== -->
<section>
  <!-- Mapa de orientación (propósito + valores) -->
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

    <?php if ($orientacion_cultura): ?>
  <div style="margin-top:18px;">
    <strong style="color:#184656;"><?= h($orientacion_cultura) ?></strong><br>
    <span style="color:#6a6a6a;"><?= h($subtitulo_cultura) ?></span>
    <p style="margin-top:8px;"><?= $descripcion_cultura ?></p>

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

    <?php if (!empty($info_culturas[$cultura_tipo_final]['rituales'])): ?>
 
      <div style="margin-top:10px;">
        <strong style="color:#184656;">Rituales recomendados</strong>
        <ul style="margin:6px 0 0 18px;">
          <?php foreach ($info_culturas[$cultura_tipo_final]['rituales'] as $r): ?>
            <li><?= h($r) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

  </div>
</section>

      
      
      
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
  if (cuadrante !== -1) quadBg[cuadrante] = 'rgba(239,127,27,0.10)'; // naranja suave

  new Chart(ctx, {
    type: 'scatter',
    data: {
      datasets: [
  {
    label: 'Cultura de la empresa (Hofstede)',
    data: [{ x: hofX, y: hofY }],
    pointRadius: 10,
    pointHoverRadius: 12,
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

  
  
  
 
 

  
  
</body>
</html>

