<?php
session_start();
require 'config.php';

// Redirección si no hay sesión iniciada
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/**
 * IMPORTANTE:
 * - Aquí vamos a pegar los HELPERS y la IDENTIDAD DE MARCA
 *   exactamente igual que en a-cultura-proposito-valores.php
 * - NO borres todavía nada más.
 */






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
// Usuario logueado (para el header)
$usuario_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT empresa, logo, cultura_empresa_tipo, rol FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$u = stmt_get_result($stmt)->fetch_assoc() ?: [];
$stmt->close();

$empresa             = $u['empresa'] ?? 'Nombre de la empresa';
$logo                = $u['logo']    ?? '/uploads/logo-192.png';
$rol_usuario         = (string)($u['rol'] ?? '');
$cultura_empresa_tipo = (string)($u['cultura_empresa_tipo'] ?? '');


// (borra si no lo vas a usar de verdad)
// $stmt = $conn->prepare("SELECT empresa, logo, cultura_empresa_tipo FROM usuarios WHERE id = ?");





// ========= Usuario sobre el que se gestionan las metas (desempeño) =========
// Si en la URL viene ?user_id=XX, usamos ese.
// Si NO viene, usamos el propio usuario logueado ($usuario_id).
if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
    $user_id = (int)$_GET['user_id'];
} else {
    $user_id = $usuario_id;
}

// Seguridad mínima por si algo raro pasa
if ($user_id <= 0) {
    echo "Usuario no especificado.";
    exit;
}







// Helpers...



function clamp_pct($v){ $v=is_numeric($v)?(float)$v:0; return max(0,min(100,round($v,0))); }
function avg(array $a){ return count($a)?array_sum($a)/count($a):0; }
function days_to_due($d){ try{$due=new DateTime($d); $today=new DateTime('today'); return (int)$today->diff($due)->format('%r%a');}catch(Exception $e){return null;} }
function fmt_date($d){ $ts=strtotime($d); return $ts?date('d/m/Y',$ts):$d; }
function pct_meta_area(array $m){ $p=[]; foreach(($m['personales']??[]) as $x){ $p[] = clamp_pct($x['porcentaje']??0);} return avg($p); }
function pct_area(array $a){ $p=[]; foreach(($a['metas_area']??[]) as $m){ $p[] = pct_meta_area($m);} return avg($p); }
function pct_corporativa(array $c){ $p=[]; foreach(($c['areas']??[]) as $a){ $p[] = pct_area($a);} return avg($p); }
function due_badge($dateStr){
    $d=days_to_due($dateStr); if($d===null) return '';
    if($d<0){$cl='badge badge-danger'; $tx='Vencida hace '.abs($d).' días';}
    elseif($d===0){$cl='badge badge-warning'; $tx='Vence hoy';}
    elseif($d<=7){$cl='badge badge-warning'; $tx='Vence en '.$d.' días';}
    else{$cl='badge badge-neutral'; $tx='Vence en '.$d.' días';}
    return '<span class="'.$cl.'">'.$tx.'</span>';
}

// ==== HELPERS PARA ORDEN Y DEFAULTS ====
function next_order_index_empresa(mysqli $conn, int $user_id): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 AS nxt FROM metas WHERE user_id=? AND tipo='empresa'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $nxt = (int)stmt_get_result($stmt)->fetch_assoc()['nxt'];
    $stmt->close();
    return $nxt;
}

function next_order_index_child(mysqli $conn, int $parent_meta_id): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(order_index), -1) + 1 AS nxt FROM metas WHERE parent_meta_id=?");
    $stmt->bind_param("i", $parent_meta_id);
    $stmt->execute();
    $nxt = (int)stmt_get_result($stmt)->fetch_assoc()['nxt'];
    $stmt->close();
    return $nxt;
}

function default_due_date_plus_days(int $days = 30): string {
    return (new DateTime("+{$days} days"))->format('Y-m-d');
}


function resolve_meta_area_context(mysqli $conn, int $meta_area_id): array {
    $stmt = $conn->prepare("
        SELECT parent_meta_id AS meta_empresa_id, area_id
        FROM metas
        WHERE id=? LIMIT 1
    ");
    $stmt->bind_param("i", $meta_area_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    $row = $res->fetch_assoc() ?: ['meta_empresa_id' => null, 'area_id' => null];
    $stmt->close();
    return [
        'meta_empresa_id' => (int)($row['meta_empresa_id'] ?? 0),
        'area_id'         => (int)($row['area_id'] ?? 0),
    ];
}


// ==== DEV: CREACIÓN RÁPIDA DE METAS PARA PRUEBA ====
// Nota: en producción mover a endpoints separados (POST /metas), con CSRF y permisos.



// ==== CREACIÓN DE METAS (quick + forms existentes) ====
// Nota: en producción mover a endpoints separados (POST /metas), con CSRF y permisos.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__crear_meta'])) {
    $tipo = $_POST['tipo'] ?? '';

    try {
        if ($tipo === 'empresa_quick') {
            // 1 clic: crea meta de empresa con defaults
            $stmt = $conn->prepare("
                INSERT INTO metas (user_id, tipo, descripcion, due_date, order_index)
                VALUES (?, 'empresa', ?, ?, ?)
            ");
            $desc = "Nueva meta de empresa";
            $due  = default_due_date_plus_days(30);
            $ord  = next_order_index_empresa($conn, (int)$user_id);
            $stmt->bind_param("issi", $user_id, $desc, $due, $ord);
            $stmt->execute();
            $stmt->close();

        } elseif ($tipo === 'area_quick') {
            // Minimal: crear meta de área para una meta-empresa específica
            // Requiere: parent_meta_id, area_id, descripcion, due_date
            $parent = (int)($_POST['parent_meta_id'] ?? 0);
            $areaId = (int)($_POST['area_id'] ?? 0);
            $desc   = trim($_POST['descripcion'] ?? 'Nueva meta de área');
            $due    = trim($_POST['due_date'] ?? default_due_date_plus_days(21));
            $ord    = next_order_index_child($conn, $parent);

            $stmt = $conn->prepare("
                INSERT INTO metas (user_id, tipo, parent_meta_id, area_id, descripcion, due_date, order_index)
                VALUES (?, 'area', ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiissi", $user_id, $parent, $areaId, $desc, $due, $ord);
            $stmt->execute();
            $stmt->close();

        } elseif ($tipo === 'empresa') {
    // Validación mínima
    $desc = trim($_POST['descripcion'] ?? '');
    $due  = trim($_POST['due_date'] ?? '');
    if ($desc === '' || $due === '') {
        throw new Exception('Descripción y fecha de vencimiento son obligatorias para la meta de empresa.');
    }

    // Si no viene order_index, lo calculamos
    $ord = isset($_POST['order_index']) && $_POST['order_index'] !== ''
        ? (int)$_POST['order_index']
        : next_order_index_empresa($conn, (int)$user_id);

    $stmt = $conn->prepare("
        INSERT INTO metas (user_id, tipo, descripcion, due_date, order_index)
        VALUES (?, 'empresa', ?, ?, ?)
    ");
    $stmt->bind_param("issi", $user_id, $desc, $due, $ord);
    $stmt->execute();
    $stmt->close();


        } elseif ($tipo === 'area') {
            $stmt = $conn->prepare("
                INSERT INTO metas (user_id, tipo, parent_meta_id, area_id, descripcion, due_date, order_index)
                VALUES (?, 'area', ?, ?, ?, ?, ?)
            ");
            $parent = (int)($_POST['parent_meta_id'] ?? 0);
            $areaId = (int)($_POST['area_id'] ?? 0);
            $desc   = trim($_POST['descripcion'] ?? '');
            $due    = trim($_POST['due_date'] ?? '');
            $ord    = (int)($_POST['order_index'] ?? 0);
            $stmt->bind_param("iiissi", $user_id, $parent, $areaId, $desc, $due, $ord);
            $stmt->execute();
            $stmt->close();

        } elseif ($tipo === 'persona') {
    // parent_meta_id = ID de la META DE ÁREA (lo envías en el form oculto)
    $meta_area_id = (int)($_POST['parent_meta_id'] ?? 0);
    $persona_id   = (int)($_POST['persona_id'] ?? 0);
    $desc         = trim($_POST['descripcion'] ?? '');
    $due          = trim($_POST['due_date'] ?? '');
    $pct          = isset($_POST['progress_pct']) ? (int)$_POST['progress_pct'] : 0;
    $done         = isset($_POST['is_completed']) ? 1 : 0;

    if ($desc === '' || $due === '' || !$meta_area_id || !$persona_id) {
        throw new Exception('Faltan datos: descripción, due_date, meta_area_id o persona_id.');
    }

    // Resuelve meta_empresa_id y area_id desde la meta de área
    $ctx = resolve_meta_area_context($conn, $meta_area_id);
    $meta_empresa_id = (int)$ctx['meta_empresa_id'];
    $area_id         = (int)$ctx['area_id'];

    if (!$meta_empresa_id || !$area_id) {
        throw new Exception('No se pudo resolver meta_empresa_id/area_id desde la meta de área.');
    }

    // Inserta en la nueva tabla metas_personales
    $stmt = $conn->prepare("
        INSERT INTO metas_personales
          (user_id, meta_empresa_id, area_id, meta_area_id, persona_id, descripcion, due_date, progress_pct, is_completed, completed_at)
        VALUES
          (?,       ?,               ?,      ?,            ?,          ?,           ?,        ?,            ?, CASE WHEN ?=1 THEN NOW() ELSE NULL END)
    ");
    // Tipos: 5 ints + 2 strings + 3 ints = 10 parámetros  => "iiiiissiii"
    $stmt->bind_param(
        "iiiiissiii",
        $user_id,
        $meta_empresa_id,
        $area_id,
        $meta_area_id,
        $persona_id,
        $desc,
        $due,
        $pct,
        $done,
        $done
    );

    if (!$stmt->execute()) {
        throw new Exception('Insert metas_personales falló: '.$stmt->errno.' '.$stmt->error);
    }
    $stmt->close();
}

        header("Location: cumplimiento.php?user_id=".$user_id);
        exit;

    } catch (Throwable $e) {
        echo "<pre style='color:#a94442;background:#fdecea;border:1px solid #f3c2c0;padding:10px;border-radius:8px;'>Error al crear meta: "
           . htmlspecialchars($e->getMessage()) . "</pre>";
    }
}




// ==== QUERIES UTILITARIAS ====

// 1) Metas corporativas (empresa)
function db_get_metas_empresa(mysqli $conn, int $user_id): array {
    $sql = "
        SELECT m.*, vp.progress_pct
        FROM metas m
        LEFT JOIN v_metas_progress vp ON vp.id=m.id
        WHERE m.user_id=? AND m.tipo='empresa'
        ORDER BY m.order_index, m.due_date
    ";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

// 2) Metas de área por meta-empresa
function db_get_metas_area(mysqli $conn, int $meta_empresa_id): array {
    $sql = "
        SELECT a.*, vp.progress_pct, at.nombre_area
        FROM metas a
        LEFT JOIN v_metas_progress vp ON vp.id=a.id
        LEFT JOIN areas_trabajo at ON at.id=a.area_id
        WHERE a.parent_meta_id=? AND a.tipo='area'
        ORDER BY a.order_index, a.due_date
    ";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meta_empresa_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

// 3) Metas personales por meta-área
function db_get_metas_persona(mysqli $conn, int $user_id, int $meta_area_id): array {
    $sql = "
        SELECT mp.*, e.nombre_persona, e.cargo
        FROM metas_personales mp
        LEFT JOIN equipo e ON e.id = mp.persona_id
        WHERE mp.user_id = ? AND mp.meta_area_id = ?
        ORDER BY mp.due_date, mp.id
    ";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $meta_area_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    $stmt->close();
    return $out;
}





// 4) Dropdowns dev: áreas y personas del usuario (para formularios)
function db_get_areas(mysqli $conn, int $user_id): array {
    $sql = "SELECT id, nombre_area FROM areas_trabajo WHERE usuario_id=? ORDER BY nombre_area";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}
function db_get_personas(mysqli $conn, int $user_id): array {
    $sql = "SELECT id, nombre_persona, cargo FROM equipo WHERE usuario_id=? ORDER BY nombre_persona";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

// 5) Dropdowns dev: metas empresa y metas área (para relacionar jerarquía)
function db_get_metas_empresa_min(mysqli $conn, int $user_id): array {
    $sql = "SELECT id, descripcion FROM metas WHERE user_id=? AND tipo='empresa' ORDER BY order_index, due_date";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}
function db_get_metas_area_min_por_empresa(mysqli $conn, int $meta_empresa_id): array {
    $sql = "SELECT id, descripcion FROM metas WHERE parent_meta_id=? AND tipo='area' ORDER BY order_index, due_date";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meta_empresa_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}



// ==== CONSTRUCCIÓN DEL ÁRBOL (AGRUPANDO METAS POR ÁREA) ====
$metas_corporativas = [];
$empresaRows = db_get_metas_empresa($conn, (int)$user_id);

foreach ($empresaRows as $erow) {
    // Agrupador por área_id para NO repetir el nombre del área
    $areasById = [];

    // Trae TODAS las metas de área de esta meta de empresa
    $areasRows = db_get_metas_area($conn, (int)$erow['id']);

    foreach ($areasRows as $arow) {



// Arma lista de metas personales AGRUPADAS por persona bajo esta meta de área
$personalesPack = [];
$persRows = db_get_metas_persona($conn, (int)$user_id, (int)$arow['id']);

// Agrupar por persona_id: cuenta tareas, promedio de progreso, due_date más cercano
$grouped = [];
$todayYmd = (new DateTime('today'))->format('Y-m-d');

foreach ($persRows as $prow) {
    $pid  = (int)$prow['persona_id'];
    $pct  = isset($prow['progress_pct']) ? (int)$prow['progress_pct'] : 0;
    $due  = (string)($prow['due_date'] ?? '');
    $done = (int)($prow['is_completed'] ?? 0);

    if (!isset($grouped[$pid])) {
        $grouped[$pid] = [
            'persona_id'       => $pid,
            'nombre_persona'   => (string)$prow['nombre_persona'],
            'cargo'            => (string)$prow['cargo'],
            'tareas_count'     => 0,
            'pct_sum'          => 0,
            'pct_n'            => 0,
            'due_next'         => null, // fecha más cercana (mínima)
            // NUEVOS CONTADORES
            'count_completed'  => 0,
            'count_overdue'    => 0,   // no cumplidas (vencidas)
            'count_inprogress' => 0,   // no cumplidas y con due >= hoy (en desarrollo)
        ];
    }

    $grouped[$pid]['tareas_count']++;
    $grouped[$pid]['pct_sum'] += $pct;
    $grouped[$pid]['pct_n']   += 1;

    // Clasificación por estado
    if ($done === 1) {
        $grouped[$pid]['count_completed']++;
    } else {
        if ($due && strtotime($due) < strtotime($todayYmd)) {
            $grouped[$pid]['count_overdue']++;
        } else {
            $grouped[$pid]['count_inprogress']++;
        }
    }

    // due_next = mínimo (fecha más cercana)
    if ($due) {
        if ($grouped[$pid]['due_next'] === null) {
            $grouped[$pid]['due_next'] = $due;
        } else {
            if (strtotime($due) < strtotime($grouped[$pid]['due_next'])) {
                $grouped[$pid]['due_next'] = $due;
            }
        }
    }
}

// Normaliza para el render: porcentaje promedio por persona
foreach ($grouped as $g) {
    $avgPct = $g['pct_n'] ? round($g['pct_sum'] / $g['pct_n']) : 0;
    $personalesPack[] = [
        'persona_id'       => $g['persona_id'],
        'nombre_persona'   => $g['nombre_persona'],
        'cargo'            => $g['cargo'],
        'tareas_count'     => $g['tareas_count'],
        'porcentaje'       => (int)$avgPct,
        'due_date'         => $g['due_next'] ?? null, // no lo mostramos, pero lo dejamos por si lo usas luego
        // NUEVOS CAMPOS PARA TAGS
        'count_completed'  => $g['count_completed'],
        'count_overdue'    => $g['count_overdue'],
        'count_inprogress' => $g['count_inprogress'],
    ];
}

// DEBUG (quítalo luego)
error_log("MetaArea {$arow['id']} - personales: " . count($persRows));



// DEBUG (quítalo luego)
error_log("MetaArea {$arow['id']} - personales: " . count($persRows));


        // Normaliza identificador de área
        $areaId = isset($arow['area_id']) ? (int)$arow['area_id'] : 0;
        $areaNom = (string)($arow['nombre_area'] ?? 'Área sin nombre');

        // Si el área aún no existe en el agrupador, créala
        if (!isset($areasById[$areaId])) {
            $areasById[$areaId] = [
                'id'          => $areaId,          // ID del área (NO el id de la meta)
                'nombre_area' => $areaNom,
                'metas_area'  => [],               // aquí acumulamos todas las metas de esa área
            ];
        }

        // Agrega la meta de área actual dentro de la lista de metas del área
        $areasById[$areaId]['metas_area'][] = [
            'id'          => (int)$arow['id'],          // id de la META DE ÁREA
            'descripcion' => (string)$arow['descripcion'],
            'due_date'    => (string)$arow['due_date'],
            'personales'  => $personalesPack,
        ];
    }

    // Empuja la meta corporativa con las áreas agrupadas
    $metas_corporativas[] = [
        'id'          => (int)$erow['id'],
        'descripcion' => (string)$erow['descripcion'],
        'due_date'    => (string)$erow['due_date'],
        'areas'       => array_values($areasById), // reset índices
    ];
}







?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cumplimiento de Metas</title>
    <!-- Valírica Design System -->
    <link rel="stylesheet" href="valirica-design-system.css">
      <style>
      /* Estilos específicos de la página de metas */

/* Contenedor principal de la página */
.main-shell {
    max-width: 1100px;
    margin: 0 auto;
    padding: 120px 24px 80px;
}

/* Título principal de sección */
.page-title {
    font-size: 40px;
    color: var(--c-primary);
    margin: 0 0 24px;
}

/* Tarjetas de metas */
.meta-card {
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    width: 100%;
    position: relative;
}

.meta-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    border-bottom: 1px solid var(--color-gray-100);
    padding-bottom: 8px;
}

.meta-title {
    font-size: 25px;
    color: var(--c-secondary);
    font-weight: 700;
    flex: 1;
}

.progress-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 12px 0 4px;
}

.progress-label {
    font-size: 18px;
    color: var(--color-gray-500);
}

.progress-value {
    margin-left: auto;
    font-size: 30px;
    color: var(--c-accent);
    font-weight: 700;
}

/* CTA perfil (usado en las tarjetas de personas) */
.cta-perfil {
    margin-left: auto;
    background: var(--c-accent);
    color: #fff;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 16px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: opacity var(--transition-fast);
}

.cta-perfil:hover {
    opacity: 0.9;
}

/* Acordeones de áreas y metas */
details.area,
details.meta-area {
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius);
    background: #fff;
    margin-top: 12px;
    overflow: hidden;
}

details.area > summary .area-title {
    color: var(--c-accent);
}

details > summary {
    list-style: none;
    padding: 14px 16px;
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 25px;
    color: var(--color-gray-900);
    font-weight: 600;
}

details > summary::-webkit-details-marker {
    display: none;
}

.caret {
    width: 12px;
    height: 12px;
    border-right: 2px solid var(--color-gray-500);
    border-bottom: 2px solid var(--color-gray-500);
    transform: rotate(-45deg);
    transition: transform var(--transition-fast);
    margin-right: 2px;
}

details[open] > summary .caret {
    transform: rotate(45deg);
}

.summary-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 10px;
}

.area-body,
.meta-body {
    padding: 0 16px 16px;
}

.meta-headline {
    font-size: 22px;
    color: var(--color-gray-900);
    font-weight: 600;
    margin: 6px 0;
}

.meta-sub {
    font-size: 16px;
    color: var(--color-gray-500);
    margin-bottom: 8px;
}

/* Lista de personas */
.person-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

.person-item {
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    padding: 12px;
    background: #fff;
    width: 100%;
    box-sizing: border-box;
}

.person-topline {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.person-name {
    font-size: 20px;
    color: var(--color-gray-900);
    font-weight: 700;
}

.person-role {
    font-size: 16px;
    color: var(--color-gray-500);
}

.person-label {
    font-size: 20px;
    color: var(--color-gray-900);
    font-weight: 700;
}

.person-pct {
    margin-left: auto;
    font-size: 18px;
    color: var(--color-gray-900);
    font-weight: 700;
}

.person-caption {
    font-size: 14px;
    color: var(--color-gray-500);
    margin: 8px 0 0;
}

/* Botones específicos de metas */
.btn-valirica {
    display: flex;
    align-items: center;
    background-color: var(--c-accent);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    font-size: 30px;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.btn-valirica img {
    width: 100px;
    height: 100px;
    margin-right: 10px;
    object-fit: cover;
    border-radius: var(--radius-sm);
}

.btn-valirica:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-valirica:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.35);
}

.btn-valirica--small {
    background-color: var(--c-accent);
    color: #fff;
    padding: 8px 16px;
    border: none;
    border-radius: 22px;
    cursor: pointer;
    font-size: 22px;
    transition: all var(--transition-fast);
}

.btn-valirica--small:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Acciones de tarjetas */
.meta-card__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}

.meta-body__actions {
    display: flex;
    justify-content: flex-start;
    margin: 8px 0 12px;
}

/* Formularios toggle */
.area-form-wrap,
.persona-form-wrap {
    display: none;
    margin-top: 12px;
}
 
     
     
     
    </style>        





  
</head>

<body>




<!-- Header unificado, igual que en a-cultura-proposito-valores -->
<?php require __DIR__ . '/a-header-desktop-brand.php'; ?>

<div class="main-shell">

    <h1 class="page-title">Cumplimiento de Metas</h1>

    <!-- Botón (idéntico al dashboard) + Formulario toggle para meta de empresa -->
    <div style="max-width:980px;width:100%;margin:10px auto 24px;">
      <!-- Botón principal -->
      <div style="display:flex;justify-content:center;margin-bottom:12px;">
        <button type="button" class="btn-valirica" onclick="toggleEmpresaForm()">
          <img src="/uploads/Boton-AddMember.png" alt="Agregar">
          Crear Meta de Empresa
        </button>
      </div>
      ...
    </div>
 </div>



  <!-- Botón principal -->
  <div style="display:flex;justify-content:center;margin-bottom:12px;">
    <button type="button" class="btn-valirica" onclick="toggleEmpresaForm()">
      <img src="/uploads/Boton-AddMember.png" alt="Agregar">
      Crear Meta de Empresa
    </button>
  </div>

  <!-- Formulario oculto -->
  <div id="empresaFormWrap" style="display:none;">
    <form method="post" style="background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.05);padding:16px;display:grid;gap:12px;">
      <input type="hidden" name="__crear_meta" value="1">
      <input type="hidden" name="tipo" value="empresa">

      <label><strong>Descripción (empresa)</strong>
        <input type="text" name="descripcion" required
               style="width:100%;padding:10px;border-radius:10px;border:1px solid var(--line);font-size:18px;">
      </label>

      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <label><strong>Due date</strong>
          <input type="date" name="due_date" required
                 style="padding:10px;border-radius:10px;border:1px solid var(--line);font-size:18px;">
        </label>

        <!-- Opcional: deja visible si quieres control manual; si no, quítalo
        <label><strong>Orden</strong>
          <input type="number" name="order_index"
                 placeholder="auto" style="padding:10px;border-radius:10px;border:1px solid var(--line);width:140px;font-size:18px;">
        </label>
        -->
      </div>

      <div style="display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn-valirica--small" onclick="toggleEmpresaForm()" style="background:#666;">Cancelar</button>
        <button type="submit" class="btn-valirica--small">Guardar meta de empresa</button>
      </div>
    </form>
  </div>
</div>


    








    
    
    
    
    
    
    
    
    
    <?php foreach ($metas_corporativas as $meta): 
      $pctCorp = clamp_pct(pct_corporativa($meta));
    ?>
      <div class="meta-card">
        <div class="meta-head">
          <div class="meta-title"><?= htmlspecialchars($meta['descripcion']) ?></div>
          
          <?= due_badge($meta['due_date']) ?>
        </div>

        <div class="progress-row">
          <span class="progress-label">Avance global</span>
          <span class="progress-value"><?= $pctCorp ?>%</span>
        </div>
        <div class="progress" aria-label="Avance meta corporativa">
          <div class="fill" style="width: <?= $pctCorp ?>%;"></div>
        </div>
        
        
        


        
        
        

        <!-- Áreas (colapsadas) -->
        <?php foreach ($meta['areas'] as $area): 
          $pctArea = clamp_pct(pct_area($area));
        ?>
          <details class="area">
            <summary>
              <span class="caret"></span>
              <span class="area-title"><?= htmlspecialchars($area['nombre_area']) ?></span>
              <span class="summary-right">
              <span class="badge"><?= count($area['metas_area']) ?> tareas</span>

                <strong style="color:#FF7800;"><?= $pctArea ?>% completado</strong>
              </span>
            </summary>
            <div class="area-body">
              <!-- Metas de área -->
              <?php foreach ($area['metas_area'] as $ma): 
                $pctMetaArea = clamp_pct(pct_meta_area($ma));
              ?>
                <details class="meta-area">
                  <summary>
                    <span class="caret"></span>
                    <span><?= htmlspecialchars($ma['descripcion']) ?></span>
                    <span class="summary-right">
                     
                      <?= due_badge($ma['due_date']) ?>
                      <strong style="color:#FF7800;"><?= $pctMetaArea ?>%</strong>
                    </span>
                  </summary>
                
                
                
                
                
                  <div class="meta-body">
       
       
       
               





  
                    <!-- Personas / Metas personales -->
                    <div class="person-list">
                      <?php foreach ($ma['personales'] as $p):
  $pp = clamp_pct($p['porcentaje'] ?? 0);
  $empleadoId = (int)$p['persona_id'];
  $tcount = (int)($p['tareas_count'] ?? 1);
?>


<div class="person-item">
  <?php
    $pp          = clamp_pct($p['porcentaje'] ?? 0);
    $empleadoId  = (int)$p['persona_id'];
    $tcount      = (int)($p['tareas_count'] ?? 0);
    $nOverdue    = (int)($p['count_overdue'] ?? 0);
    $nDone       = (int)($p['count_completed'] ?? 0);
    $nProgress   = (int)($p['count_inprogress'] ?? 0);
  ?>
  <div class="person-topline">
    <!-- Nombre (25px) + cargo en la misma línea -->
    <div class="person-name" style="font-size:25px; font-weight:700; color:#004758;">
      <?= htmlspecialchars($p['nombre_persona']) ?>
      <span class="person-role" style="font-size:18px; color:#666; font-weight:400;">
        — <?= htmlspecialchars($p['cargo']) ?>
      </span>
    </div>

    <!-- CTA naranja a la derecha -->
    <a href="https://app.valirica.com/dashboard_empleado.php?empleado_id=<?= $empleadoId ?>"
       class="cta-perfil">ver perfil</a>
  </div>

  <!-- Barra de progreso debajo del nombre -->
  <div class="progress" aria-label="Avance metas personales (promedio)">
    <div class="fill" style="width: <?= $pp ?>%;"></div>
  </div>

  <!-- Tags: X metas no cumplidas / X completadas / X en desarrollo -->
  <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
    <span class="badge badge-neutral" style="border-color:#cfe9cf; background:#f3fbf3; color:#2e7d32;">
      <?= $nDone ?> <?= ($nDone===1?'completada':'completadas') ?>
    </span>
    <span class="badge badge-warning"><?= $nProgress ?> <?= ($nProgress===1?'en desarrollo':'en desarrollo') ?></span>
    <span class="badge badge-danger"><?= $nOverdue ?> <?= ($nOverdue===1?'meta no cumplida':'metas no cumplidas') ?></span>
    
    
  </div>

  
</div>




<?php endforeach; ?>

                      
                      
                      
                      <?php if (empty($ma['personales'])): ?>
  <div class="person-item" style="opacity:.85">
    <div class="person-topline">
      <span class="person-label">Sin metas personales aún</span>
    </div>
    <p class="person-caption">Usa “➕ Agregar meta individual” para crear la primera.</p>
  </div>
<?php endif; ?>

                           <!-- Acciones meta de área (botón izquierda, mismo tamaño que crear meta de área) -->
<div class="meta-body__actions">
  <button type="button" class="btn-valirica--small"
          onclick="togglePersonaForm(<?= (int)$ma['id'] ?>)">➕ Agregar meta individual</button>
</div>




<!-- Formulario toggle para crear meta individual -->
<div id="personaFormWrap_<?= (int)$ma['id'] ?>" class="persona-form-wrap">
  <form method="post" style="display:grid;gap:10px;max-width:720px;">
    <input type="hidden" name="__crear_meta" value="1">
    <input type="hidden" name="tipo" value="persona">
    <input type="hidden" name="parent_meta_id" value="<?= (int)$ma['id'] ?>">

    <label><strong>Persona</strong>
      <select name="persona_id" required
              style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line);">
        <?php foreach (db_get_personas($conn, (int)$user_id) as $opt): ?>
          <option value="<?= (int)$opt['id'] ?>">
            <?= htmlspecialchars($opt['nombre_persona'].' — '.$opt['cargo']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <label style="flex:1;min-width:260px;"><strong>Descripción (personal)</strong>
        <input type="text" name="descripcion" required
               placeholder="Describe el objetivo personal"
               style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line);">
      </label>

      <label><strong>Due date</strong>
        <input type="date" name="due_date" required
               style="padding:8px;border-radius:8px;border:1px solid var(--line);">
      </label>

      <label><strong>% avance</strong>
        <input type="number" name="progress_pct" min="0" max="100" value="0"
               style="padding:8px;border-radius:8px;border:1px solid var(--line);width:120px;">
      </label>

      <label style="display:flex;align-items:center;gap:6px;margin-top:6px;">
        <input type="checkbox" name="is_completed" value="1"> Marcar completada
      </label>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn-valirica--small" onclick="togglePersonaForm(<?= (int)$ma['id'] ?>)" style="background:#666;">
        Cancelar
      </button>
      <button class="btn-valirica--small" type="submit">Guardar meta individual</button>
    </div>
  </form>
</div>
                      
                      
                      
                      
                    </div>
                    
                    
                    
                    
                    
                  </div>
                </details>
              <?php endforeach; ?>
              

            </div>
          </details>
        <?php endforeach; ?>
        <!-- Acciones de tarjeta (bottom-right) -->
<div class="meta-card__actions">
  <button type="button" class="btn-valirica--small"
          onclick="toggleAreaForm(<?= (int)$meta['id'] ?>)">➕ Crear meta de área</button>
</div>

<!-- Formulario toggle para crear meta de área -->
<div id="areaFormWrap_<?= (int)$meta['id'] ?>" class="area-form-wrap">
  <form method="post" style="display:grid;gap:10px;max-width:720px;margin-left:auto;">
    <input type="hidden" name="__crear_meta" value="1">
    <input type="hidden" name="tipo" value="area_quick">
    <input type="hidden" name="parent_meta_id" value="<?= (int)$meta['id'] ?>">

    <label><strong>Área</strong>
      <select name="area_id" required style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line);">
        <?php foreach (db_get_areas($conn, (int)$user_id) as $opt): ?>
          <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars($opt['nombre_area']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <label style="flex:1;min-width:260px;"><strong>Descripción</strong>
        <input type="text" name="descripcion" placeholder="Descripción de la meta de área" required
               style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--line);">
      </label>
      <label><strong>Due date</strong>
        <input type="date" name="due_date" required
               style="padding:8px;border-radius:8px;border:1px solid var(--line);">
      </label>
    </div>

    <div style="display:flex;justify-content:flex-end;">
      <button class="btn-valirica--small" type="submit">Guardar meta de área</button>
    </div>
  </form>
</div>

    <?php endforeach; ?>
    
  </div>
  
  <script>
function togglePersonaForm(metaAreaId){
  const el = document.getElementById('personaFormWrap_' + metaAreaId);
  if(!el) return;
  el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}
</script>

  
  
  <script>
function toggleEmpresaForm(){
  const wrap = document.getElementById('empresaFormWrap');
  if(!wrap) return;
  wrap.style.display = (wrap.style.display === 'none' || wrap.style.display === '') ? 'block' : 'none';
}
</script>

  
  
  <script>
function toggleAreaForm(metaId){
  const el = document.getElementById('areaFormWrap_' + metaId);
  if(!el) return;
  el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}
</script>
</body>
</html>