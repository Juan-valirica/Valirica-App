<?php

 

// ------------------------

// Manejo de sesión y empleado/id robusto

// ------------------------

// Soportamos dos maneras de llegar al dashboard:

// - Admin/proveedor: $_SESSION['user_id'] + dashboard_equipo.php?id=NN

// - Empleado: login_equipo.php + $_SESSION['empleado_id']

 

session_start();

require 'config.php';

// Configurar zona horaria de España
date_default_timezone_set('Europe/Madrid');



// ------------------------

// 1) Determinar empleado_id

// ------------------------

$empleado_id = (int)($_GET['id'] ?? 0);

 

if ($empleado_id <= 0 && !empty($_SESSION['empleado_id'])) {

    $empleado_id = (int)$_SESSION['empleado_id'];

}

 

if ($empleado_id <= 0) {

    echo "<p style='padding:24px;color:#B00020;'>⛔ Falta el parámetro ?id del empleado.</p>";

    exit;

}

 

// ------------------------

// 2) Determinar user_id (empresa)

// ------------------------

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

 

if (empty($user_id)) {

 

    $q = $conn->prepare(

        "SELECT usuario_id FROM equipo WHERE id = ? LIMIT 1"

    );

    $q->bind_param("i", $empleado_id);

    $q->execute();

    $row = stmt_get_result($q)->fetch_assoc();

    $q->close();

 

    if ($row && !empty($row['usuario_id'])) {

        $user_id = (int)$row['usuario_id'];

        // $_SESSION['user_id'] = $user_id; // opcional

    } else {

        $user_id = 0;

    }

}

 

// --------------------------------------------------------------------

// Helpers (compatibles con el archivo original)

// --------------------------------------------------------------------

function initials($name) {

    $parts = preg_split('/\s+/u', trim((string)$name));

    $a = isset($parts[0][0]) ? mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8')) : '';

    $b = isset($parts[1][0]) ? mb_strtoupper(mb_substr($parts[1], 0, 1, 'UTF-8')) : '';

    return $a . $b;

}

 

function battery_icon_for_pct($pct) {

    if ($pct <= 25) return '/uploads/Battery-low.png';

    if ($pct <= 50) return '/uploads/Battery-mid.png';

    if ($pct <= 75) return '/uploads/Battery-high.png';

    return '/uploads/Battery-full.png';

}

 

function norm_key($s) {

    $s = trim(mb_strtolower((string)$s, 'UTF-8'));

    $s = strtr($s, [

        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'

    ]);

    return preg_replace('/\s+/', ' ', $s);

}

 

// --------------------------------------------------------------------

// 1) Datos de empresa (header)

// --------------------------------------------------------------------

$stmt = $conn->prepare(

    "SELECT empresa, logo FROM usuarios WHERE id = ?"

);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$r_u = stmt_get_result($stmt)->fetch_assoc() ?: [];

$stmt->close();

 

$empresa = $r_u['empresa'] ?? 'Empresa';

$logo    = $r_u['logo'] ?? '/uploads/logo-192.png';

 

// --------------------------------------------------------------------

// 2) Datos del empleado

// --------------------------------------------------------------------

$sql = $conn->prepare("

    SELECT

        e.id,

        e.nombre_persona,

        COALESCE(e.cargo,'—') AS cargo,

        COALESCE(
            (SELECT GROUP_CONCAT(at2.nombre_area ORDER BY at2.nombre_area SEPARATOR ', ')
             FROM equipo_areas_trabajo eat
             INNER JOIN areas_trabajo at2 ON eat.area_id = at2.id
             WHERE eat.equipo_id = e.id),
            COALESCE(e.area_trabajo, '—')
        ) AS area,

 

        COALESCE(e.hofstede_poder,0) AS hof_poder,

        COALESCE(e.hofstede_individualismo,0) AS hof_indiv,

        COALESCE(e.hofstede_resultados,0) AS hof_resultados,

        COALESCE(e.hofstede_incertidumbre,0) AS hof_incert,

        COALESCE(e.hofstede_largo_plazo,0) AS hof_largo,

        COALESCE(e.hofstede_espontaneidad,0) AS hof_indulg,

 

        COALESCE(e.visual,0) AS visual,

        COALESCE(e.auditivo,0) AS auditivo,

        COALESCE(e.kinestesico,0) AS kinestesico,

 

        COALESCE(e.maslow_fis,0) AS mas_fis,

        COALESCE(e.maslow_seg,0) AS mas_seg,

        COALESCE(e.maslow_afi,0) AS mas_afi,

        COALESCE(e.maslow_rec,0) AS mas_rec,

        COALESCE(e.maslow_aut,0) AS mas_aut,

 

        COALESCE(e.pink_purp,0) AS pink_purp,

        COALESCE(e.pink_auto,0) AS pink_auto,

        COALESCE(e.pink_maes,0) AS pink_maes,

        COALESCE(e.pink_fis,0) AS pink_fis,

        COALESCE(e.pink_rel,0) AS pink_rel

 

    FROM equipo e

    WHERE e.id = ? AND e.usuario_id = ?

    LIMIT 1

");

$sql->bind_param("ii", $empleado_id, $user_id);

$sql->execute();

$emp = stmt_get_result($sql)->fetch_assoc();

$sql->close();

 

if (!$emp) {

    echo "<p style='padding:24px;color:#B00020;'>⛔ Empleado no encontrado o no pertenece a tu empresa.</p>";

    exit;

}

 

// --------------------------------------------------------------------

// 3) Energía (Pink + Maslow)

// --------------------------------------------------------------------

$pink_avg = (

    $emp['pink_purp'] +

    $emp['pink_auto'] +

    $emp['pink_maes'] +

    $emp['pink_fis'] +

    $emp['pink_rel']

) / 5.0;

 

$pink_pct = max(0, min(100, round(($pink_avg / 5.0) * 100)));

 

$mas = [

    'fisiologica'      => (float)$emp['mas_fis'],

    'seguridad'        => (float)$emp['mas_seg'],

    'afiliacion'       => (float)$emp['mas_afi'],

    'reconocimiento'   => (float)$emp['mas_rec'],

    'autorrealizacion' => (float)$emp['mas_aut']

];

 

$mas_dom = array_keys($mas, max($mas))[0] ?? 'fisiologica';

$mas_energy_map = [

    'fisiologica'=>0,

    'seguridad'=>25,

    'afiliacion'=>50,

    'reconocimiento'=>75,

    'autorrealizacion'=>100

];

 

$mas_pct = $mas_energy_map[$mas_dom] ?? 0;

 

$energia_pct  = (int) round(0.6 * $pink_pct + 0.4 * $mas_pct);

$energia_icon = battery_icon_for_pct($energia_pct);

$energia_label = (

    $energia_pct <= 25 ? 'Baja' :

    ($energia_pct <= 50 ? 'Motivación Media' :

    ($energia_pct <= 75 ? 'Motivación Alta' : 'Motivación Óptima'))

);

 

// --------------------------------------------------------------------

// 4) Estilo de aprendizaje

// --------------------------------------------------------------------

$apr = [

    'visual'       => (float)$emp['visual'],

    'auditivo'     => (float)$emp['auditivo'],

    'kinestesico'  => (float)$emp['kinestesico']

];

 

$apr_sum = array_sum($apr);

$apr_dom_key = $apr_sum > 0 ? array_keys($apr, max($apr))[0] : null;

 

$LABELS_SENSORIALES = [

    'visual' => 'Visual',

    'auditivo' => 'Auditivo',

    'kinestesico' => 'Kinestésico'

];

 

$estilo_emp_aprend = $apr_dom_key

    ? $LABELS_SENSORIALES[$apr_dom_key]

    : 'Sin datos';

 

// --------------------------------------------------------------------

// 5) Cultura natural (Hofstede)

// --------------------------------------------------------------------

require_once __DIR__ . '/lib/cultura_ejes.php';

 

function _map_to_0_100_local($v) {

    if (!is_numeric($v)) return 50.0;

    $vv = (float)$v;

    if ($vv >= -5 && $vv <= 5) return (($vv + 5) / 10) * 100;

    if ($vv >= -1 && $vv <= 1) return (($vv + 1) / 2) * 100;

    if ($vv >= 0 && $vv <= 1) return $vv * 100;

    if ($vv > 1 && $vv <= 5) return $vv * 20;

    if ($vv >= 0 && $vv <= 100) return $vv;

    if ($vv < 0) return 0;

    return 100;

}

 

function _to_v2_input_local(array $src) {

    return [

        'individualismo'  => _map_to_0_100_local($src['individualismo'] ?? $src['hofstede_individualismo'] ?? 50),

        'masculinidad'    => _map_to_0_100_local($src['masculinidad'] ?? $src['hofstede_resultados'] ?? 50),

        'incertidumbre'   => _map_to_0_100_local($src['incertidumbre'] ?? $src['hofstede_incertidumbre'] ?? 50),

        'distancia_poder' => _map_to_0_100_local($src['distancia_poder'] ?? $src['hofstede_poder'] ?? 50),

        'largo_plazo'     => _map_to_0_100_local($src['largo_plazo'] ?? $src['hofstede_largo_plazo'] ?? 50),

        'indulgencia'     => _map_to_0_100_local($src['indulgencia'] ?? $src['hofstede_espontaneidad'] ?? 50)

    ];

}

 

$input_hofstede_emp = [

    'distancia_poder' => $emp['hof_poder'],

    'individualismo'  => $emp['hof_indiv'],

    'masculinidad'    => $emp['hof_resultados'],

    'incertidumbre'   => $emp['hof_incert'],

    'largo_plazo'     => $emp['hof_largo'],

    'indulgencia'     => $emp['hof_indulg']

];

 

list($ejeX_emp, $ejeY_emp) =

    calcula_ejes_hofstede_v2(_to_v2_input_local($input_hofstede_emp));

 

function cuadrante_label_simple($x, $y) {

    if ($x < 0 && $y > 0) return 'Colaborativa';

    if ($x >= 0 && $y > 0) return 'Ágil';

    if ($x < 0 && $y <= 0) return 'Estructurada';

    return 'Orientada a Resultados';

}

 

$tipo_cultura_natural = cuadrante_label_simple($ejeX_emp, $ejeY_emp);

 

// --------------------------------------------------------------------

// 6) Identidad

// --------------------------------------------------------------------

$nombre_emp      = (string)$emp['nombre_persona'];

$cargo_emp       = (string)$emp['cargo'];

$area_emp        = (string)$emp['area'];

$avatar_initials = initials($nombre_emp);

 

// --------------------------------------------------------------------

// 7) Metas personales

// --------------------------------------------------------------------

$metas_personales = [];

 

$stmtMp = $conn->prepare("

    SELECT

        id, descripcion, due_date, progress_pct,

        is_completed, status,

        help_requested, help_requested_by,

        created_at, updated_at

    FROM metas_personales

    WHERE persona_id = ? AND user_id = ?

    ORDER BY created_at DESC

");

$stmtMp->bind_param("ii", $empleado_id, $user_id);

$stmtMp->execute();

$resMp = stmt_get_result($stmtMp);

 

while ($row = $resMp->fetch_assoc()) {

    $metas_personales[] = $row;

}

$stmtMp->close();

 

// --------------------------------------------------------------------

// 8) Metas de equipo (empresa / área) + Solicitudes de ayuda

// --------------------------------------------------------------------

// PASO 1: Obtener TODAS las áreas del miembro desde tabla junction
$area_ids_emp = [];
$stmtAreasEmp = $conn->prepare("
    SELECT eat.area_id
    FROM equipo_areas_trabajo eat
    INNER JOIN equipo e ON eat.equipo_id = e.id
    WHERE eat.equipo_id = ? AND e.usuario_id = ?
");
$stmtAreasEmp->bind_param("ii", $empleado_id, $user_id);
$stmtAreasEmp->execute();
$resAreasEmp = stmt_get_result($stmtAreasEmp);
while ($rowA = $resAreasEmp->fetch_assoc()) {
    $area_ids_emp[] = (int)$rowA['area_id'];
}
$stmtAreasEmp->close();

// Compatibilidad: primer área para código legacy
$area_id_emp = !empty($area_ids_emp) ? $area_ids_emp[0] : 0;

$metas_equipo = [];

// PASO 2: Buscar metas de empresa Y metas de TODAS las áreas del miembro
if (!empty($area_ids_emp)) {
    $ph = implode(',', array_fill(0, count($area_ids_emp), '?'));
    $types = 'i' . str_repeat('i', count($area_ids_emp));
    $params = array_merge([$user_id], $area_ids_emp);

    $stmtMe = $conn->prepare("
        SELECT id, descripcion, due_date, progress_pct,
               is_completed, tipo, area_id,
               parent_meta_id, order_index
        FROM metas
        WHERE user_id = ?
          AND (tipo = 'empresa' OR (tipo = 'area' AND area_id IN ($ph)))
        ORDER BY COALESCE(order_index, 9999) ASC, created_at DESC
    ");
    $stmtMe->bind_param($types, ...$params);
} else {
    $stmtMe = $conn->prepare("
        SELECT id, descripcion, due_date, progress_pct,
               is_completed, tipo, area_id,
               parent_meta_id, order_index
        FROM metas
        WHERE user_id = ?
          AND tipo = 'empresa'
        ORDER BY COALESCE(order_index, 9999) ASC, created_at DESC
    ");
    $stmtMe->bind_param("i", $user_id);
}

$stmtMe->execute();
$resMe = stmt_get_result($stmtMe);

while ($row = $resMe->fetch_assoc()) {
    $metas_equipo[] = $row;
}

$stmtMe->close();

 

// --------------------------------------------------------------------

// 9) NUEVO: Solicitudes de ayuda del equipo

// --------------------------------------------------------------------

$solicitudes_ayuda = [];

 

// Buscar solicitudes de ayuda de compañeros en las MISMAS áreas (vía junction)
if (!empty($area_ids_emp)) {
    $ph_ayuda = implode(',', array_fill(0, count($area_ids_emp), '?'));
    $types_ayuda = 'i' . str_repeat('i', count($area_ids_emp)) . 'i';
    $params_ayuda = array_merge([$user_id], $area_ids_emp, [$empleado_id]);

    $stmtAyuda = $conn->prepare("
        SELECT
            mp.id,
            mp.persona_id,
            mp.descripcion,
            mp.progress_pct,
            mp.help_requested,
            mp.help_requested_by,
            e.nombre_persona
        FROM metas_personales mp
        INNER JOIN equipo e ON mp.persona_id = e.id
        INNER JOIN equipo_areas_trabajo eat ON e.id = eat.equipo_id
        WHERE mp.user_id = ?
          AND eat.area_id IN ($ph_ayuda)
          AND mp.help_requested = 1
          AND mp.is_completed = 0
          AND mp.persona_id != ?
        GROUP BY mp.id
        ORDER BY mp.created_at DESC
    ");
    $stmtAyuda->bind_param($types_ayuda, ...$params_ayuda);
    $stmtAyuda->execute();
    $resAyuda = stmt_get_result($stmtAyuda);

    while ($row = $resAyuda->fetch_assoc()) {
        $solicitudes_ayuda[] = $row;
    }
    $stmtAyuda->close();
}







/* ============================================
   SISTEMA DE ASISTENCIA (CHECK-IN / CHECK-OUT)
   ============================================ */

// Procesar check-in o check-out si viene POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_asistencia'])) {
    header('Content-Type: application/json');

    try {
        $action = $_POST['action_asistencia']; // 'entrada' o 'salida'
        $fecha_hoy = date('Y-m-d');
        $hora_actual = date('H:i:s');

        // Obtener ubicación (solo para entrada)
        $ubicacion_tipo = isset($_POST['ubicacion_tipo']) ? trim($_POST['ubicacion_tipo']) : null;
        $ubicacion_detalle = isset($_POST['ubicacion_detalle']) ? trim($_POST['ubicacion_detalle']) : null;

        // Obtener jornada asignada del empleado
        $stmt_jornada = $conn->prepare("
            SELECT ej.jornada_id, j.tolerancia_entrada_min, j.tolerancia_salida_min
            FROM equipo_jornadas ej
            INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
            WHERE ej.persona_id = ?
              AND ej.fecha_inicio <= ?
              AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= ?)
            ORDER BY ej.fecha_inicio DESC
            LIMIT 1
        ");
        $stmt_jornada->bind_param("iss", $empleado_id, $fecha_hoy, $fecha_hoy);
        $stmt_jornada->execute();
        $jornada_result = stmt_get_result($stmt_jornada)->fetch_assoc();
        $stmt_jornada->close();

        if (!$jornada_result) {
            throw new Exception('No tienes una jornada asignada');
        }

        $jornada_id = (int)$jornada_result['jornada_id'];
        $tolerancia_entrada = (int)$jornada_result['tolerancia_entrada_min'];
        $tolerancia_salida = (int)$jornada_result['tolerancia_salida_min'];

        // Obtener turno de hoy (día de la semana)
        $dia_semana = (int)date('N'); // 1=Lun, 7=Dom
        $stmt_turno = $conn->prepare("
            SELECT hora_inicio, hora_fin, cruza_medianoche
            FROM turnos
            WHERE jornada_id = ? AND dia_semana = ?
            LIMIT 1
        ");
        $stmt_turno->bind_param("ii", $jornada_id, $dia_semana);
        $stmt_turno->execute();
        $turno = stmt_get_result($stmt_turno)->fetch_assoc();
        $stmt_turno->close();

        if (!$turno) {
            throw new Exception('No hay turno configurado para hoy');
        }

        $hora_inicio_turno = $turno['hora_inicio'];
        $hora_fin_turno = $turno['hora_fin'];
        $cruza_medianoche = (int)($turno['cruza_medianoche'] ?? 0);

        if ($action === 'entrada') {
            // Verificar si ya marcó entrada hoy
            $stmt_check = $conn->prepare("
                SELECT id, hora_entrada FROM asistencias
                WHERE persona_id = ? AND fecha = ?
            ");
            $stmt_check->bind_param("is", $empleado_id, $fecha_hoy);
            $stmt_check->execute();
            $asistencia_existente = stmt_get_result($stmt_check)->fetch_assoc();
            $stmt_check->close();

            if ($asistencia_existente && $asistencia_existente['hora_entrada']) {
                throw new Exception('Ya marcaste tu entrada hoy a las ' . substr($asistencia_existente['hora_entrada'], 0, 5));
            }

            // Calcular minutos de retraso
            $hora_inicio_timestamp = strtotime($hora_inicio_turno);
            $hora_actual_timestamp = strtotime($hora_actual);
            $minutos_diferencia = round(($hora_actual_timestamp - $hora_inicio_timestamp) / 60);
            $minutos_tarde = max(0, $minutos_diferencia - $tolerancia_entrada);

            $estado = 'presente';
            if ($minutos_tarde > 0) {
                $estado = 'tarde';
            }

            // Insertar o actualizar registro
            if ($asistencia_existente) {
                $stmt_update = $conn->prepare("
                    UPDATE asistencias SET
                        hora_entrada = ?,
                        estado = ?,
                        minutos_tarde_entrada = ?,
                        ubicacion_tipo = ?,
                        ubicacion_detalle = ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param("ssissi", $hora_actual, $estado, $minutos_tarde, $ubicacion_tipo, $ubicacion_detalle, $asistencia_existente['id']);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                $stmt_insert = $conn->prepare("
                    INSERT INTO asistencias (persona_id, jornada_id, fecha, hora_entrada, estado, minutos_tarde_entrada, ubicacion_tipo, ubicacion_detalle)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert->bind_param("iisssiss", $empleado_id, $jornada_id, $fecha_hoy, $hora_actual, $estado, $minutos_tarde, $ubicacion_tipo, $ubicacion_detalle);
                $stmt_insert->execute();
                $stmt_insert->close();
            }

            $mensaje = '✅ Entrada registrada a las ' . substr($hora_actual, 0, 5);
            if ($ubicacion_tipo === 'oficina') {
                $mensaje .= "\n📍 Desde: Oficina";
            } elseif ($ubicacion_tipo === 'remoto') {
                $mensaje .= "\n📍 Desde: " . ($ubicacion_detalle ?: 'Remoto');
            }
            $mensaje .= "\n✅ Estado: " . ($estado === 'tarde' ? 'TARDE ⚠️' : 'A TIEMPO ✅');

            echo json_encode(['success' => true, 'message' => $mensaje]);
            exit;

        } elseif ($action === 'salida') {
            // Verificar que ya marcó entrada
            $stmt_check = $conn->prepare("
                SELECT id, hora_entrada, hora_salida FROM asistencias
                WHERE persona_id = ? AND fecha = ?
            ");
            $stmt_check->bind_param("is", $empleado_id, $fecha_hoy);
            $stmt_check->execute();
            $asistencia_existente = stmt_get_result($stmt_check)->fetch_assoc();
            $stmt_check->close();

            if (!$asistencia_existente || !$asistencia_existente['hora_entrada']) {
                throw new Exception('Primero debes marcar tu entrada');
            }

            if ($asistencia_existente['hora_salida']) {
                throw new Exception('Ya marcaste tu salida hoy a las ' . substr($asistencia_existente['hora_salida'], 0, 5));
            }

            // Calcular diferencia con hora programada de salida
            $hora_fin_timestamp = strtotime($hora_fin_turno);
            $hora_actual_timestamp = strtotime($hora_actual);

            // Si el turno cruza medianoche, la hora de fin es del día siguiente
            if ($cruza_medianoche && $hora_fin_timestamp < strtotime($hora_inicio_turno)) {
                $hora_fin_timestamp += 86400; // Agregar 24 horas (86400 segundos)
            }

            $minutos_diferencia_salida = round(($hora_actual_timestamp - $hora_fin_timestamp) / 60);

            // Lógica mejorada para 3 escenarios:
            // 1. Sale ANTES de tiempo: minutos_diferencia < 0 y fuera de tolerancia
            // 2. Sale a tiempo: dentro del rango de tolerancia
            // 3. Sale DESPUÉS (horas extra): minutos_diferencia > 0

            $minutos_tarde_salida = 0; // Salió antes de tiempo (cuántos minutos antes)
            $mensaje_salida = '';

            if ($minutos_diferencia_salida < -$tolerancia_salida) {
                // Caso 1: Salió ANTES del rango permitido
                $minutos_tarde_salida = abs($minutos_diferencia_salida) - $tolerancia_salida;
                $mensaje_salida = " (⚠️ Salió {$minutos_tarde_salida} min antes de tiempo)";
            } elseif ($minutos_diferencia_salida > 0) {
                // Caso 3: Salió DESPUÉS (horas extra)
                // Guardamos como valor negativo para diferenciarlo
                $minutos_tarde_salida = -$minutos_diferencia_salida;
                $horas_extra = floor($minutos_diferencia_salida / 60);
                $mins_extra = $minutos_diferencia_salida % 60;
                if ($horas_extra > 0) {
                    $mensaje_salida = " (💼 {$horas_extra}h {$mins_extra}m de tiempo extra)";
                } else {
                    $mensaje_salida = " (💼 {$mins_extra}m de tiempo extra)";
                }
            } else {
                // Caso 2: Salió a tiempo (dentro de tolerancia)
                $mensaje_salida = " (✅ A tiempo)";
            }

            // Actualizar registro
            $stmt_update = $conn->prepare("
                UPDATE asistencias SET
                    hora_salida = ?,
                    minutos_tarde_salida = ?
                WHERE id = ?
            ");
            $stmt_update->bind_param("sii", $hora_actual, $minutos_tarde_salida, $asistencia_existente['id']);
            $stmt_update->execute();
            $stmt_update->close();

            $mensaje = '✅ Salida registrada a las ' . substr($hora_actual, 0, 5) . $mensaje_salida;
            $mensaje .= "\n🕐 Turno finaliza: " . substr($hora_fin_turno, 0, 5);
            $mensaje .= "\n📊 Diferencia: {$minutos_diferencia_salida} min";
            $mensaje .= "\n⏱️ Tolerancia: {$tolerancia_salida} min";

            echo json_encode(['success' => true, 'message' => $mensaje]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Obtener jornada asignada del empleado para hoy
$jornada_asignada = null;
$asistencia_hoy = null;
$turno_hoy = null;

try {
    $fecha_hoy = date('Y-m-d');
    $dia_semana = (int)date('N'); // 1=Lun, 7=Dom

    // Obtener jornada asignada
    $stmt_jornada = $conn->prepare("
        SELECT ej.jornada_id, j.nombre as jornada_nombre, j.color_hex, j.codigo_corto,
               j.tolerancia_entrada_min, j.tolerancia_salida_min
        FROM equipo_jornadas ej
        INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        WHERE ej.persona_id = ?
          AND ej.fecha_inicio <= ?
          AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= ?)
        ORDER BY ej.fecha_inicio DESC
        LIMIT 1
    ");
    $stmt_jornada->bind_param("iss", $empleado_id, $fecha_hoy, $fecha_hoy);
    $stmt_jornada->execute();
    $jornada_asignada = stmt_get_result($stmt_jornada)->fetch_assoc();
    $stmt_jornada->close();

    if ($jornada_asignada) {
        // Obtener turno de hoy
        $jornada_id = (int)$jornada_asignada['jornada_id'];
        $stmt_turno = $conn->prepare("
            SELECT nombre_turno, dia_semana, hora_inicio, hora_fin, cruza_medianoche
            FROM turnos
            WHERE jornada_id = ? AND dia_semana = ?
            LIMIT 1
        ");
        $stmt_turno->bind_param("ii", $jornada_id, $dia_semana);
        $stmt_turno->execute();
        $turno_hoy = stmt_get_result($stmt_turno)->fetch_assoc();
        $stmt_turno->close();

        // Obtener registro de asistencia de hoy
        $stmt_asistencia = $conn->prepare("
            SELECT * FROM asistencias
            WHERE persona_id = ? AND fecha = ?
        ");
        $stmt_asistencia->bind_param("is", $empleado_id, $fecha_hoy);
        $stmt_asistencia->execute();
        $asistencia_hoy = stmt_get_result($stmt_asistencia)->fetch_assoc();
        $stmt_asistencia->close();
    }
} catch (Exception $e) {
    // Silenciar errores de tablas que no existen
}



?>

 

<!doctype html>

 

<html lang="es">

 

<head>

  <meta charset="utf-8">

  <title>Tarjeta empleado — Valírica</title>

  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">

<!-- PWA Meta Tags -->
<meta name="theme-color" content="#012133">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Valírica">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Valírica">

<!-- PWA Icons -->
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">
<link rel="icon" type="image/png" sizes="192x192" href="https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png">

<!-- Phosphor Icons — librería de iconos profesionales -->
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">

  <link rel="stylesheet" href="/css/valirica-design-system.css">


 

<style>

  /* ⚠️ TEMPORAL: migrar a Design System */
  /* =========================================================

     Fuentes

     ========================================================= */

  @import url("https://use.typekit.net/qrv8fyz.css");

 

  /* =========================================================

     Variables de diseño (Design Tokens)

     ========================================================= */

  :root{

    --c-primary:   #012133;

    --c-secondary: #184656;

    --c-teal:      #007a96;

    --c-accent:    #EF7F1B;



    --c-soft: #FFF5F0;

    --c-body: #474644;

    --c-bg:   #FFFFFF;



    --radius: 20px;

    --shadow: 0 6px 20px rgba(0,0,0,0.06);



    /* Valirica Brand Gradients */

    --gradient-tech: linear-gradient(135deg, #007a96 0%, #012133 100%);

    --gradient-accent: linear-gradient(135deg, #EF7F1B 0%, #C65F00 100%);

    --gradient-primary: linear-gradient(135deg, #012133 0%, #007a96 100%);

    --gradient-card: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));



    /* Glassmorphism */

    --glass-bg: rgba(255, 255, 255, 0.75);

    --glass-border: rgba(255, 255, 255, 0.18);

    --glass-blur: blur(10px);



    /* Valirica Shadows */

    --shadow-card: 0 4px 24px rgba(1, 33, 51, 0.08);

    --shadow-hover: 0 12px 40px rgba(0, 122, 150, 0.18);

    --shadow-tech: 0 10px 26px rgba(239, 127, 27, 0.35);

    --shadow-primary: 0 8px 28px rgba(1, 33, 51, 0.18);

  }

 

  /* =========================================================

     Reset y base

     ========================================================= */

  *,

  *::before,

  *::after {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

  }

 

  body{

    background: var(--c-bg);

    color: var(--c-body);

    font-family: "gelica",

      system-ui,

      -apple-system,

      Segoe UI,

      Roboto,

      "Helvetica Neue",

      Arial,

      sans-serif;

  }

 

  /* =========================================================

     Header

     ========================================================= */

  header{

    width: 100%;

    background: var(--c-primary);

    color: var(--c-soft);

    padding: 14px 24px;

    display: none; /* Ocultado - no funcional */

    align-items: center;

    justify-content: space-between;

  }

 

  .brand-logo{

    width: 40px;

    height: 40px;

    border-radius: 10px;

    object-fit: cover;

    background: #f4f4f4;

  }

 

  /* =========================================================

     Layout

     ========================================================= */

  .wrap{

    max-width: 980px;

    margin: 0 auto;

    padding: 24px;

  }

 

  .card{

    background: var(--glass-bg);

    backdrop-filter: var(--glass-blur);

    -webkit-backdrop-filter: var(--glass-blur);

    border-radius: var(--radius);

    box-shadow: var(--shadow-card);

    padding: 16px;

    border: 1px solid var(--glass-border);

    transition: all 0.2s ease;

  }



  .card:hover{

    transform: translateY(-2px);

    box-shadow: var(--shadow-hover);

  }

 

  /* =========================================================

     Perfil / Avatar

     ========================================================= */

  .perfil-wrap{

    display: grid;

    grid-template-columns: 64px 1fr;

    gap: 14px;

    align-items: center;

  }

 

  .avatar{

    width: 64px;

    height: 64px;

    border-radius: 9999px;

    background: var(--gradient-accent);

    color: #fff;

    font-weight: 800;

    font-size: 20px;

    display: grid;

    place-items: center;

    box-shadow: 0 4px 12px rgba(239,127,27,0.3);

    transition: all 0.3s ease;

    position: relative;

    overflow: hidden;

  }



  .avatar::before{

    content: '';

    position: absolute;

    top: -50%;

    left: -50%;

    width: 200%;

    height: 200%;

    background: linear-gradient(

      45deg,

      transparent,

      rgba(255,255,255,0.1),

      transparent

    );

    transform: rotate(45deg);

    transition: all 0.5s ease;

  }



  .avatar:hover{

    transform: scale(1.05);

    box-shadow: 0 6px 20px rgba(239,127,27,0.4);

  }



  .avatar:hover::before{

    left: 100%;

  }

 

  .perfil-name{

    font-size: 20px;

    font-weight: 800;

    color: var(--c-secondary);

    letter-spacing: -0.3px;

  }

 

  .perfil-role{

    font-size: 14px;

    color: #5a5a5a;

    font-weight: 500;

  }

 

  /* =========================================================

     Chips (badges informativos)

     ========================================================= */

  .chip-row{

    display: none; /* Ocultado para simplificar perfil */

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 10px;

  }

 

  .chip{

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 8px 12px;

    border-radius: 9999px;

    border: 1px solid rgba(0,0,0,0.06);

    background: var(--c-soft);

    color: var(--c-secondary);

    font-size: 13px;

    font-weight: 700;

  }

 

  .chip img{

    height: 14px;

    display: inline-block;

  }

 

  /* =========================================================

     Botones de meta - ESTILOS FALTANTES ✅

     ========================================================= */

  .meta-btn {

    padding: 6px 10px;

    border-radius: 8px;

    border: 1px solid rgba(0,0,0,0.1);

    background: #fff;

    color: var(--c-secondary);

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    transition: all 0.2s ease;

    font-family: inherit;

  }

 

  .meta-btn:hover {

    border-color: var(--c-accent);

    background: rgba(239, 127, 27, 0.05);

    transform: translateY(-1px);

  }



  .meta-btn:active {

    transform: scale(0.98);

  }

 

  .meta-btn:active {

    transform: translateY(0);

  }

 

  .status-btn[aria-pressed="true"] {

    background: color-mix(in srgb, var(--c-accent) 20%, #fff) !important;

    border-color: var(--c-accent) !important;

    color: var(--c-primary) !important;

    font-weight: 700 !important;

  }

 

  .panic-btn {

    font-weight: 600;

  }

 

  .panic-btn:hover {

    background: var(--c-accent);

    color: #fff;

    border-color: var(--c-accent);

  }

 

  /* =========================================================

     Utilidades

     ========================================================= */

  .muted{

    color: #6a6a6a;

    font-size: 13px;

    margin-top: 8px;

  }

 

  /* =========================================================

     Responsive

     ========================================================= */

  @media (max-width: 600px){

    .perfil-name{

      font-size: 16px;

    }

 

    .chip{

      font-size: 12px;

      padding: 6px 10px;

    }

  }

 

 

  /* =========================================================

   Estados de meta: Ayuda solicitada

   ========================================================= */

    .meta-item--help-requested {

      background: linear-gradient(135deg, #FFF9F0 0%, #FFF5E8 100%) !important;

      border: 2px solid #EF7F1B !important;

      box-shadow: 0 4px 12px rgba(239, 127, 27, 0.15);

      position: relative;

      animation: pulse-help 2s ease-in-out infinite;

    }

 

    @keyframes pulse-help {

      0%, 100% {

        box-shadow: 0 4px 12px rgba(239, 127, 27, 0.15);

      }

      50% {

        box-shadow: 0 4px 16px rgba(239, 127, 27, 0.25);

      }

    }

 

    .meta-help-badge {

      display: inline-flex;

      align-items: center;

      gap: 4px;

      padding: 4px 10px;

      background: var(--gradient-accent);

      color: white;

      border-radius: 999px;

      font-size: 11px;

      font-weight: 800;

      text-transform: uppercase;

      letter-spacing: 0.5px;

      box-shadow: var(--shadow-tech);

      animation: pulse-badge 2s ease-in-out infinite;

    }



    @keyframes pulse-badge {

      0%, 100% {

        box-shadow: 0 2px 8px rgba(239, 127, 27, 0.3);

      }

      50% {

        box-shadow: 0 4px 16px rgba(239, 127, 27, 0.5);

      }

    }

 

    .meta-help-icon {

      font-size: 14px;

      animation: ring 1s ease-in-out infinite;

    }

 

    @keyframes ring {

      0%, 100% { transform: rotate(0deg); }

      10%, 30% { transform: rotate(-10deg); }

      20%, 40% { transform: rotate(10deg); }

      50% { transform: rotate(0deg); }

    }

 

    /* =========================================================

       Campo de progreso mejorado - VERSIÓN AMPLIA

       ========================================================= */

    .progress-wrapper {

      display: flex;

      gap: 8px;

      align-items: center;

    }

 

    .progress-input-group {

      position: relative;

      display: flex;

      align-items: center;

      flex-shrink: 0; /* ← No se comprime */

    }

 

    .progress-label {

      font-size: 10px;

      font-weight: 700;

      color: #5a5a5a;

      text-transform: uppercase;

      letter-spacing: 0.5px;

      margin-right: 6px;

      white-space: nowrap;

      flex-shrink: 0;

    }

 

    .meta-percent {

      width: 100px !important; /* ← MUCHO MÁS ANCHO */

      padding: 10px 32px 10px 16px !important; /* ← Padding generoso */

      border-radius: 8px;

      border: 2px solid #e6e6e6 !important;

      text-align: center !important;

      font-weight: 700;

      font-size: 16px !important; /* ← Fuente más grande */

      line-height: 1.3;

      transition: all 0.2s ease;

      background: #fff;

      box-sizing: border-box;

    }

 

    .meta-percent:hover {

      border-color: #EF7F1B !important;

      box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.1);

    }

 

    .meta-percent:focus {

      outline: none;

      border-color: #EF7F1B !important;

      box-shadow: 0 0 0 4px rgba(239, 127, 27, 0.15);

    }

 

    .percent-symbol {

      position: absolute;

      right: 12px;

      top: 50%;

      transform: translateY(-50%);

      font-size: 14px;

      font-weight: 700;

      color: #6a6a6a;

      pointer-events: none;

    }

 

    /* Colores según progreso */

    .meta-percent[data-progress-level="low"] {

      border-color: #ef5350 !important;

      color: #c62828;

    }

 

    .meta-percent[data-progress-level="medium"] {

      border-color: #ffa726 !important;

      color: #e65100;

    }

 

    .meta-percent[data-progress-level="high"] {

      border-color: #66bb6a !important;

      color: #2e7d32;

    }

 

    .meta-percent[data-progress-level="complete"] {

      border-color: #2e7d32 !important;

      color: #2e7d32;

      background: #e8f5e9;

    }

 

    /* Tooltip de ayuda */

    .progress-tooltip {

      position: relative;

      display: inline-flex;

      align-items: center;

      cursor: help;

    }

 

    .progress-tooltip::after {

      content: attr(data-tooltip);

      position: absolute;

      bottom: calc(100% + 8px);

      left: 50%;

      transform: translateX(-50%) scale(0);

      padding: 6px 10px;

      background: #012133;

      color: white;

      font-size: 11px;

      border-radius: 6px;

      white-space: nowrap;

      opacity: 0;

      transition: all 0.2s ease;

      pointer-events: none;

      z-index: 1000;

      box-shadow: 0 4px 12px rgba(0,0,0,0.2);

    }

 

    .progress-tooltip::before {

      content: '';

      position: absolute;

      bottom: calc(100% + 2px);

      left: 50%;

      transform: translateX(-50%) scale(0);

      border: 6px solid transparent;

      border-top-color: #012133;

      opacity: 0;

      transition: all 0.2s ease;

    }

 

    .progress-tooltip:hover::after,

    .progress-tooltip:hover::before {

      opacity: 1;

      transform: translateX(-50%) scale(1);

    }

 

  /* =========================================================

   Solicitudes de ayuda - Estilos mejorados

   ========================================================= */

    .btn-finalize-help:active {

      transform: scale(0.95);

    }

 

    .btn-finalize-help:disabled {

      cursor: not-allowed !important;

      opacity: 0.6 !important;

    }

 

    /* Animación de entrada para solicitudes */

    @keyframes slideInHelp {

      from {

        opacity: 0;

        transform: translateY(-10px);

      }

      to {

        opacity: 1;

        transform: translateY(0);

      }

    }

 

    #help-list > div {

      animation: slideInHelp 0.3s ease forwards;

    }

 

    #help-list > div:nth-child(1) { animation-delay: 0s; }

    #help-list > div:nth-child(2) { animation-delay: 0.1s; }

    #help-list > div:nth-child(3) { animation-delay: 0.2s; }

    #help-list > div:nth-child(4) { animation-delay: 0.3s; }

 

 

  /* =========================================================

   Modal de ayuda recibida

   ========================================================= */

    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(1, 33, 51, 0.7);
      backdrop-filter: blur(4px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      animation: fadeIn 0.2s ease forwards;
      padding: 20px;
      overflow-y: auto;
    }

    @keyframes fadeIn {
      to { opacity: 1; }
    }

    .modal-content {
      background: white;
      border-radius: 20px;
      padding: 28px;
      max-width: 500px;
      width: 90%;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      transform: scale(0.9);
      animation: scaleIn 0.3s ease forwards;
    }

    @keyframes scaleIn {
      to { transform: scale(1); }
    }

 

    .modal-header {

      display: flex;

      align-items: center;

      gap: 12px;

      margin-bottom: 20px;

      padding-bottom: 16px;

      border-bottom: 2px solid #f1f1f1;

    }

 

    .modal-icon {

      font-size: 32px;

    }

 

    .modal-title {

      font-size: 20px;

      font-weight: 800;

      color: #012133;

      margin: 0;

    }

 

    .modal-body {

      margin-bottom: 24px;

    }

 

    .modal-question {

      font-size: 15px;

      color: #474644;

      margin-bottom: 16px;

      line-height: 1.5;

    }

 

    .help-options {

      display: flex;

      flex-direction: column;

      gap: 10px;

    }

 

    .help-option {

      display: flex;

      align-items: center;

      gap: 12px;

      padding: 14px;

      border: 2px solid #e6e6e6;

      border-radius: 12px;

      cursor: pointer;

      transition: all 0.2s ease;

      background: white;

    }

 

    .help-option:hover {

      border-color: #EF7F1B;

      background: #FFF9F0;

      transform: translateX(4px);

    }

 

    .help-option input[type="radio"] {

      width: 20px;

      height: 20px;

      cursor: pointer;

      accent-color: #EF7F1B;

    }

 

    .help-option-label {

      flex: 1;

      font-size: 14px;

      font-weight: 600;

      color: #012133;

      cursor: pointer;

    }

 

    .help-option-sublabel {

      font-size: 12px;

      color: #6a6a6a;

      font-weight: 400;

      margin-top: 2px;

    }

 

    .teammate-select-wrapper {

      margin-top: 12px;

      padding: 12px;

      background: #f8f8f8;

      border-radius: 10px;

      display: none;

    }

 

    .teammate-select-wrapper.active {

      display: block;

      animation: slideDown 0.3s ease;

    }

 

    @keyframes slideDown {

      from {

        opacity: 0;

        transform: translateY(-10px);

      }

      to {

        opacity: 1;

        transform: translateY(0);

      }

    }

 

    .teammate-select {

      width: 100%;

      padding: 10px 14px;

      border: 2px solid #e6e6e6;

      border-radius: 8px;

      font-size: 14px;

      font-weight: 600;

      color: #012133;

      background: white;

      cursor: pointer;

      transition: all 0.2s ease;

    }

 

    .teammate-select:hover {

      border-color: #EF7F1B;

    }

 

    .teammate-select:focus {

      outline: none;

      border-color: #EF7F1B;

      box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.1);

    }

 

    .modal-actions {

      display: flex;

      gap: 12px;

      justify-content: flex-end;

    }

 

    .modal-btn {

      padding: 12px 24px;

      border-radius: 10px;

      font-size: 14px;

      font-weight: 700;

      cursor: pointer;

      transition: all 0.2s ease;

      border: none;

    }

 

    .modal-btn-cancel {

      background: #f1f1f1;

      color: #474644;

    }

 

    .modal-btn-cancel:hover {

      background: #e6e6e6;

    }

 

    .modal-btn-confirm {

      background: #EF7F1B;

      color: white;

    }

 

    .modal-btn-confirm:hover {

      background: #d66f15;

      transform: translateY(-2px);

      box-shadow: 0 4px 12px rgba(239, 127, 27, 0.3);

    }

 

    .modal-btn-confirm:active {

      transform: translateY(0);

    }

 
 
 /* =========================================================
   Subnav empleado — Action Bar
   ========================================================= */

.employee-subnav{
  position: sticky;
  top: 0;
  z-index: 20;

  display: none; /* Ocultado - solo botón Crear meta funciona, ahora en menú flotante */
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;

  padding: 16px 24px;
  background: linear-gradient(
    to bottom,
    rgba(255,255,255,0.95),
    rgba(255,255,255,0.88)
  );
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);

  border-bottom: 1px solid rgba(255,255,255,0.2);
  box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}

.subnav-item{
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;

  padding: 14px 10px;
  border-radius: 14px;

  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.3);

  font-family: inherit;
  cursor: pointer;

  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.subnav-item:hover{
  border-color: var(--c-accent);
  box-shadow: var(--shadow-hover);
  transform: translateY(-3px) scale(1.02);
  background: rgba(255, 255, 255, 0.95);
}

.subnav-item:active{
  transform: translateY(-1px) scale(0.98);
}

.subnav-icon{
  font-size: 22px;
  line-height: 1;
}

.subnav-label{
  font-size: 12px;
  font-weight: 700;
  color: var(--c-secondary);
  text-align: center;
  white-space: nowrap;
}

/* Responsive */
@media (max-width: 900px){
  .employee-subnav{
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 480px){
  .employee-subnav{
    grid-template-columns: repeat(2, 1fr);
  }
}


/* =========================================================
   Layout principal del dashboard
   ========================================================= */

.dashboard-grid{
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}

/* Grid de metas 50 / 50 */
.metas-grid{
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}

/* Responsive */
@media (max-width: 900px){
  .metas-grid{
    grid-template-columns: 1fr;
  }
}



/* =========================================================
   Acciones primarias del subnav
   ========================================================= */

.subnav-item.is-primary{
  background: linear-gradient(
    135deg,
    #EF7F1B 0%,
    #d96a12 50%,
    #c55a0f 100%
  );
  border: none;
  box-shadow: var(--shadow-tech);
  position: relative;
  overflow: hidden;
}

.subnav-item.is-primary::before{
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255,255,255,0.2),
    transparent
  );
  transition: left 0.5s ease;
}

.subnav-item.is-primary:hover::before{
  left: 100%;
}

.subnav-item.is-primary .subnav-icon{
  font-size: 24px;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.subnav-item.is-primary .subnav-label{
  color: #fff;
  font-weight: 800;
  text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

/* Hover más contundente */
.subnav-item.is-primary:hover{
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 16px 40px rgba(239,127,27,0.5);
}

.subnav-item.is-primary:active{
  transform: translateY(-2px) scale(1.0);
}


/* ==========================================================================
   ASISTENCIA — Diseño profesional Valirica (Monday/Asana style)
   ========================================================================== */

.asistencia-section {
  margin-bottom: 28px;
}

.asistencia-card {
  background: #fff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 6px 32px rgba(1, 33, 51, 0.12);
  border: 1px solid rgba(1, 33, 51, 0.08);
}

/* ---- Header con color primario ---- */
.asistencia-header {
  background: var(--c-primary);
  padding: 22px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.asistencia-header-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.asistencia-fecha {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: rgba(255,255,255,0.80);
  font-weight: 500;
}

.asistencia-fecha i {
  font-size: 15px;
}

.asistencia-jornada-pill {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.3px;
}

.asistencia-reloj {
  font-size: 44px;
  font-weight: 800;
  color: #fff;
  font-variant-numeric: tabular-nums;
  letter-spacing: -2px;
  line-height: 1;
  text-shadow: 0 2px 12px rgba(0,0,0,0.15);
}

/* ---- Franja de turno ---- */
.asistencia-turno {
  padding: 18px 28px;
  display: flex;
  align-items: center;
  gap: 24px;
  background: #f5f8fa;
  border-bottom: 1px solid rgba(1, 33, 51, 0.06);
}

.turno-bloque {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.turno-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.6px;
}

.turno-label i {
  font-size: 13px;
  color: var(--c-teal);
}

.turno-hora {
  font-size: 26px;
  font-weight: 800;
  color: var(--c-primary);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.5px;
}

.turno-flecha {
  color: #ccc;
  font-size: 18px;
  flex-shrink: 0;
  padding-top: 14px;
}

.turno-nocturno-badge {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #555;
  background: rgba(1,33,51,0.07);
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: 600;
}

/* ---- Botones de acción — PROTAGONISTAS ---- */
.asistencia-actions {
  padding: 24px 28px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

/* Botón base */
.asistencia-btn {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 22px 26px;
  border-radius: 16px;
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
  text-align: left;
  width: 100%;
  position: relative;
  overflow: hidden;
}

.asistencia-btn::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
  transition: left 0.5s ease;
}

.asistencia-btn:hover::before {
  left: 100%;
}

.asistencia-btn i {
  font-size: 36px;
  flex-shrink: 0;
  color: #fff;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
}

.asistencia-btn-text {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.asistencia-btn-title {
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.3px;
  line-height: 1.1;
}

.asistencia-btn-sub {
  font-size: 12px;
  color: rgba(255,255,255,0.72);
  font-weight: 500;
}

/* Botón Entrada (teal) */
.asistencia-btn--entrada {
  background: var(--c-teal);
  box-shadow: 0 8px 24px rgba(0, 122, 150, 0.38);
}

.asistencia-btn--entrada:hover {
  background: #006680;
  transform: translateY(-3px);
  box-shadow: 0 14px 32px rgba(0, 122, 150, 0.48);
}

.asistencia-btn--entrada:active {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(0, 122, 150, 0.30);
}

/* Botón Salida (naranja) */
.asistencia-btn--salida {
  background: var(--c-accent);
  box-shadow: 0 8px 24px rgba(239, 127, 27, 0.38);
}

.asistencia-btn--salida:hover {
  background: #d96e0d;
  transform: translateY(-3px);
  box-shadow: 0 14px 32px rgba(239, 127, 27, 0.48);
}

.asistencia-btn--salida:active {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(239, 127, 27, 0.30);
}

/* Estado deshabilitado (salida sin entrada previa) */
.asistencia-btn--disabled {
  background: #e8edf0;
  cursor: not-allowed;
  box-shadow: none;
  opacity: 0.65;
  pointer-events: none;
}

.asistencia-btn--disabled i,
.asistencia-btn--disabled .asistencia-btn-title,
.asistencia-btn--disabled .asistencia-btn-sub {
  color: #9aacb4;
  filter: none;
}

/* ---- Estados registrados ---- */
.asistencia-registered {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 22px 26px;
  border-radius: 16px;
}

.asistencia-registered--in {
  background: rgba(0, 122, 150, 0.06);
  border: 2px solid rgba(0, 122, 150, 0.18);
}

.asistencia-registered--out {
  background: rgba(239, 127, 27, 0.06);
  border: 2px solid rgba(239, 127, 27, 0.18);
}

.asistencia-registered-icon {
  font-size: 36px;
  flex-shrink: 0;
  line-height: 1;
}

.asistencia-registered--in .asistencia-registered-icon {
  color: var(--c-teal);
}

.asistencia-registered--out .asistencia-registered-icon {
  color: var(--c-accent);
}

.asistencia-registered-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.asistencia-registered-label {
  font-size: 11px;
  font-weight: 700;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.6px;
}

.asistencia-registered-time {
  font-size: 28px;
  font-weight: 800;
  color: var(--c-primary);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.5px;
  line-height: 1;
}

.asistencia-registered-loc {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #666;
  font-weight: 500;
  background: rgba(1,33,51,0.05);
  padding: 4px 10px;
  border-radius: 6px;
  width: fit-content;
  margin-top: 2px;
}

.asistencia-registered-loc i {
  font-size: 13px;
  color: var(--c-teal);
}

.asistencia-late-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #b71c1c;
  font-weight: 600;
  background: #ffebee;
  padding: 4px 10px;
  border-radius: 6px;
  width: fit-content;
}

.asistencia-late-badge i {
  font-size: 14px;
}

.asistencia-ontime-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #1b5e20;
  font-weight: 600;
  background: #e8f5e9;
  padding: 4px 10px;
  border-radius: 6px;
  width: fit-content;
}

.asistencia-ontime-badge i {
  font-size: 14px;
  color: #2e7d32;
}

.asistencia-complete-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #1b5e20;
  font-weight: 600;
  background: #e8f5e9;
  padding: 4px 10px;
  border-radius: 6px;
  width: fit-content;
}

.asistencia-complete-badge i {
  font-size: 14px;
}

/* ---- Estado "sin turno hoy" ---- */
.asistencia-no-turno {
  padding: 48px 28px;
  text-align: center;
  color: #888;
}

.asistencia-no-turno i {
  font-size: 52px;
  color: #ccc;
  margin-bottom: 16px;
  display: block;
}

.asistencia-no-turno h3 {
  font-size: 18px;
  font-weight: 700;
  color: var(--c-secondary);
  margin: 0 0 8px;
}

.asistencia-no-turno p {
  font-size: 14px;
  margin: 0;
  opacity: 0.7;
}

/* ---- Sin jornada ---- */
.asistencia-sin-jornada {
  padding: 56px 28px;
  text-align: center;
  background: #f5f8fa;
  border-radius: 20px;
}

.asistencia-sin-jornada i {
  font-size: 56px;
  color: #c5d5db;
  margin-bottom: 16px;
  display: block;
}

/* ---- Responsive ---- */
@media (max-width: 640px) {
  .asistencia-header {
    padding: 18px 20px;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .asistencia-reloj {
    font-size: 36px;
  }

  .asistencia-turno {
    padding: 14px 20px;
    flex-wrap: wrap;
    gap: 12px;
  }

  .turno-nocturno-badge {
    margin-left: 0;
  }

  .asistencia-actions {
    padding: 16px 20px;
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .asistencia-btn {
    padding: 18px 22px;
    gap: 14px;
  }

  .asistencia-btn i {
    font-size: 30px;
  }

  .asistencia-btn-title {
    font-size: 16px;
  }

  .asistencia-registered {
    padding: 18px 22px;
    gap: 14px;
  }

  .asistencia-registered-icon {
    font-size: 30px;
  }

  .asistencia-registered-time {
    font-size: 24px;
  }
}


/* ==========================================================================
   PERFIL — Mejora visual (Slack-style header card)
   ========================================================================== */

.perfil-card-header {
  background: var(--c-primary);
  border-radius: 16px 16px 0 0;
  padding: 20px 20px 0;
  margin: -16px -16px 0;
}

.perfil-avatar-ring {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  background: var(--gradient-accent);
  color: #fff;
  font-weight: 800;
  font-size: 22px;
  display: grid;
  place-items: center;
  box-shadow: 0 4px 16px rgba(239,127,27,0.35);
  border: 3px solid #fff;
  flex-shrink: 0;
}

.perfil-info-name {
  font-size: 19px;
  font-weight: 800;
  color: var(--c-primary);
  letter-spacing: -0.3px;
  line-height: 1.2;
}

.perfil-info-role {
  font-size: 13px;
  color: #666;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.perfil-info-role i {
  font-size: 14px;
  color: var(--c-teal);
}

.perfil-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 20px;
  background: rgba(0,122,150,0.09);
  color: var(--c-teal);
  border: 1px solid rgba(0,122,150,0.18);
}

.perfil-tag i {
  font-size: 13px;
}

.perfil-stats-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #f0f0f0;
}

.perfil-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.perfil-stat-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #999;
}

.perfil-stat-value {
  font-size: 13px;
  font-weight: 700;
  color: var(--c-primary);
}


/* ==========================================================================
   TABS — Estilo Monday/Asana (sin emojis)
   ========================================================================== */

.tabs-valirica {
  display: flex;
  gap: 4px;
  background: #f1f3f5;
  padding: 4px;
  border-radius: 12px;
}

.tab-valirica {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border: none;
  background: transparent;
  border-radius: 9px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  color: #666;
  transition: all 0.18s ease;
  font-family: inherit;
  white-space: nowrap;
}

.tab-valirica i {
  font-size: 15px;
}

.tab-valirica.active {
  background: #fff;
  color: var(--c-primary);
  box-shadow: 0 1px 4px rgba(0,0,0,0.10);
}

.tab-valirica:hover:not(.active) {
  background: rgba(255,255,255,0.6);
  color: var(--c-primary);
}

/* Badge de notificaciones en tab Permisos */
.tab-notif {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  background: #EF4444;
  color: #fff;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 700;
  padding: 0 5px;
  margin-left: 2px;
  line-height: 1;
}

/* Panel de acceso rápido — Tab Permisos */
.permisos-tab-panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 640px;
}

.permisos-tab-intro {
  margin: 0;
  font-size: 13px;
  color: #6B7280;
  font-weight: 500;
}

.permisos-tab-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.permisos-action-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border: 1.5px solid #F0F0F0;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  font-family: inherit;
  text-align: left;
  transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
  width: 100%;
}

.permisos-action-card:hover {
  border-color: var(--c-primary);
  box-shadow: 0 2px 12px rgba(239,127,27,0.10);
  background: #FFFAF6;
}

.permisos-action-card--secondary:hover {
  border-color: #8B9CF8;
  box-shadow: 0 2px 12px rgba(59,91,219,0.07);
  background: #F8F9FF;
}

.permisos-action-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: #FFF5EE;
  color: var(--c-primary);
  font-size: 20px;
  flex-shrink: 0;
}

.permisos-action-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.permisos-action-body strong {
  font-size: 14px;
  font-weight: 700;
  color: #1F2937;
}

.permisos-action-body span {
  font-size: 12px;
  color: #6B7280;
  font-weight: 400;
}

.permisos-action-arrow {
  font-size: 16px;
  color: #D1D5DB;
  flex-shrink: 0;
  transition: transform 0.18s;
}

.permisos-action-card:hover .permisos-action-arrow {
  transform: translateX(3px);
  color: var(--c-primary);
}

/* ==========================================================================
   META ITEMS — Mejora visual
   ========================================================================== */

.section-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: var(--c-primary);
  margin: 0 0 10px 0;
}

.section-label i {
  font-size: 15px;
  color: var(--c-teal);
}

.meta-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 20px;
  white-space: nowrap;
}

.meta-status-badge.done { background: #e8f5e9; color: #2e7d32; }
.meta-status-badge.dev  { background: #e3f2fd; color: #1565c0; }
.meta-status-badge.pause { background: #f5f5f5; color: #666; }
.meta-status-badge.help { background: #fff3e0; color: #e65100; }

/* Toast de notificación (reemplaza alert) */
.v-toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(30px);
  background: var(--c-primary);
  color: #fff;
  padding: 14px 24px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  z-index: 99999;
  box-shadow: 0 8px 32px rgba(1,33,51,0.30);
  opacity: 0;
  transition: opacity 0.25s ease, transform 0.25s ease;
  max-width: calc(100vw - 48px);
  pointer-events: none;
}

.v-toast.show {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.v-toast.error {
  background: #c62828;
}

.v-toast i {
  font-size: 18px;
  flex-shrink: 0;
}


</style>

 

 

</head>

 

<body>

<!-- =========================================================

     Header

     ========================================================= -->

<header>

  <div style="display:flex;align-items:center;gap:12px">

    <img

      class="brand-logo"

      src="<?php echo htmlspecialchars($logo); ?>"

      alt="Logo"

    >

 

    <div>

      <div style="font-weight:800;color:var(--c-soft)">

        <?php echo htmlspecialchars($empresa); ?>

      </div>

 

      <div style="font-size:13px;color:rgba(255,255,255,0.85)">

        Tarjeta de empleado

      </div>

    </div>

  </div>

 

  <div>

    <!-- espacio reservado para botones del header -->

  </div>

</header>

 
 <!-- =========================================================
     Subnav de acciones del empleado
     ========================================================= -->
<!-- subnav eliminado - acciones disponibles dentro de cada tab -->

 

<!-- =========================================================

     Contenedor principal

     ========================================================= -->


<div class="wrap">

  <!-- =========================================================
       SECCIÓN: ASISTENCIA (CHECK-IN / CHECK-OUT)
       ========================================================= -->

  <?php if ($jornada_asignada): ?>
  <section id="seccion-asistencia" class="asistencia-section">
    <div class="asistencia-card">

      <!-- ── Banner superior: color primario Valirica ── -->
      <div class="asistencia-header">
        <div class="asistencia-header-info">
          <div class="asistencia-fecha">
            <i class="ph ph-calendar-blank"></i>
            <?= date('l, d \d\e F Y') ?>
          </div>
          <div>
            <span class="asistencia-jornada-pill"
                  style="background: <?= htmlspecialchars($jornada_asignada['color_hex'] ?: '#007a96') ?>;">
              <?= htmlspecialchars($jornada_asignada['jornada_nombre']) ?>
            </span>
          </div>
        </div>
        <div id="reloj-asistencia" class="asistencia-reloj">
          <?= date('H:i:s') ?>
        </div>
      </div>

      <?php if ($turno_hoy): ?>

      <!-- ── Franja de turno ── -->
      <div class="asistencia-turno">
        <div class="turno-bloque">
          <span class="turno-label">
            <i class="ph ph-arrow-right-to-bracket"></i>
            Entrada programada
          </span>
          <span class="turno-hora"><?= substr($turno_hoy['hora_inicio'], 0, 5) ?></span>
        </div>

        <div class="turno-flecha">
          <i class="ph ph-arrow-right"></i>
        </div>

        <div class="turno-bloque">
          <span class="turno-label">
            <i class="ph ph-arrow-left-from-bracket"></i>
            Salida programada
          </span>
          <span class="turno-hora"><?= substr($turno_hoy['hora_fin'], 0, 5) ?></span>
        </div>

        <?php if ($turno_hoy['cruza_medianoche']): ?>
        <div class="turno-nocturno-badge">
          <i class="ph ph-moon-stars"></i>
          Turno nocturno
        </div>
        <?php endif; ?>
      </div>

      <!-- ── Botones de acción — protagonistas de la pantalla ── -->
      <div class="asistencia-actions">

        <!-- ENTRADA -->
        <?php if (!$asistencia_hoy || !$asistencia_hoy['hora_entrada']): ?>
        <button class="asistencia-btn asistencia-btn--entrada"
                onclick="abrirModalUbicacion()"
                id="btn-entrada">
          <i class="ph-fill ph-sign-in"></i>
          <span class="asistencia-btn-text">
            <span class="asistencia-btn-title">Iniciar Jornada</span>
            <span class="asistencia-btn-sub">Registrar hora de entrada</span>
          </span>
        </button>

        <?php else: ?>
        <div class="asistencia-registered asistencia-registered--in">
          <i class="ph-fill ph-check-circle asistencia-registered-icon"></i>
          <div class="asistencia-registered-info">
            <span class="asistencia-registered-label">Entrada registrada</span>
            <span class="asistencia-registered-time"><?= substr($asistencia_hoy['hora_entrada'], 0, 5) ?></span>
            <?php if ($asistencia_hoy['estado'] === 'tarde'): ?>
            <span class="asistencia-late-badge">
              <i class="ph ph-warning-circle"></i>
              <?= $asistencia_hoy['minutos_tarde_entrada'] ?> min de retraso
            </span>
            <?php else: ?>
            <span class="asistencia-ontime-badge">
              <i class="ph ph-check-circle"></i>
              A tiempo
            </span>
            <?php endif; ?>
            <?php if (!empty($asistencia_hoy['ubicacion_tipo'])): ?>
            <span class="asistencia-registered-loc">
              <?php if ($asistencia_hoy['ubicacion_tipo'] === 'oficina'): ?>
              <i class="ph ph-buildings"></i> Oficina
              <?php else: ?>
              <i class="ph ph-house-line"></i>
              <?= htmlspecialchars($asistencia_hoy['ubicacion_detalle'] ?: 'Remoto') ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- SALIDA -->
        <?php if ($asistencia_hoy && $asistencia_hoy['hora_entrada'] && !$asistencia_hoy['hora_salida']): ?>
        <button class="asistencia-btn asistencia-btn--salida"
                onclick="marcarAsistencia('salida')"
                id="btn-salida">
          <i class="ph-fill ph-sign-out"></i>
          <span class="asistencia-btn-text">
            <span class="asistencia-btn-title">Finalizar Jornada</span>
            <span class="asistencia-btn-sub">Registrar hora de salida</span>
          </span>
        </button>

        <?php elseif ($asistencia_hoy && $asistencia_hoy['hora_salida']): ?>
        <div class="asistencia-registered asistencia-registered--out">
          <i class="ph-fill ph-check-circle asistencia-registered-icon"></i>
          <div class="asistencia-registered-info">
            <span class="asistencia-registered-label">Salida registrada</span>
            <span class="asistencia-registered-time"><?= substr($asistencia_hoy['hora_salida'], 0, 5) ?></span>
            <span class="asistencia-complete-badge">
              <i class="ph ph-check-circle"></i>
              Jornada completa
            </span>
          </div>
        </div>

        <?php else: ?>
        <div class="asistencia-btn asistencia-btn--disabled">
          <i class="ph ph-sign-out"></i>
          <span class="asistencia-btn-text">
            <span class="asistencia-btn-title">Finalizar Jornada</span>
            <span class="asistencia-btn-sub">Primero inicia tu jornada</span>
          </span>
        </div>
        <?php endif; ?>

      </div>

      <?php else: ?>
      <!-- No hay turno hoy -->
      <div class="asistencia-no-turno">
        <i class="ph ph-calendar-x"></i>
        <h3>No hay turno configurado para hoy</h3>
        <p><?= date('l') ?> &mdash; Día libre</p>
      </div>
      <?php endif; ?>

    </div>
  </section>

  <!-- Modal de ubicación para check-in -->
  <div id="modal-ubicacion" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(1,33,51,0.55); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:20px; padding:28px; max-width:420px; width:90%; margin:20px; box-shadow:0 20px 60px rgba(1,33,51,0.25);">

      <!-- Header -->
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid #f0f0f0;">
        <div style="width:44px; height:44px; background:rgba(0,122,150,0.10); border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
          <i class="ph ph-map-pin" style="font-size:22px; color:var(--c-teal);"></i>
        </div>
        <div>
          <h3 style="margin:0 0 2px; color:var(--c-primary); font-size:17px; font-weight:800;">
            ¿Desde dónde trabajas hoy?
          </h3>
          <p style="margin:0; font-size:12px; color:#888; font-weight:500;">
            Selecciona tu ubicación de trabajo
          </p>
        </div>
      </div>

      <!-- Opciones -->
      <div style="display:flex; flex-direction:column; gap:10px;">

        <button onclick="seleccionarUbicacion('oficina')" style="
          padding:16px 20px;
          background:var(--c-primary);
          color:#fff;
          border:none;
          border-radius:14px;
          font-size:15px;
          font-weight:700;
          cursor:pointer;
          display:flex;
          align-items:center;
          gap:14px;
          font-family:inherit;
          transition: all 0.18s ease;
          box-shadow: 0 4px 14px rgba(1,33,51,0.25);
        "
        onmouseover="this.style.background='#023a57'; this.style.transform='translateY(-1px)'"
        onmouseout="this.style.background='var(--c-primary)'; this.style.transform='translateY(0)'"
        >
          <div style="width:38px; height:38px; background:rgba(255,255,255,0.15); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="ph-fill ph-buildings" style="font-size:20px;"></i>
          </div>
          <div style="text-align:left;">
            <div style="font-size:15px; font-weight:700;">Desde la oficina</div>
            <div style="font-size:12px; opacity:0.75; font-weight:400; margin-top:1px;">Trabajo presencial</div>
          </div>
        </button>

        <button onclick="mostrarCampoOtroLugar()" id="btn-otro-lugar" style="
          padding:16px 20px;
          background:#f5f8fa;
          color:var(--c-primary);
          border:2px solid #e0e8ed;
          border-radius:14px;
          font-size:15px;
          font-weight:700;
          cursor:pointer;
          display:flex;
          align-items:center;
          gap:14px;
          font-family:inherit;
          transition: all 0.18s ease;
        "
        onmouseover="this.style.borderColor='var(--c-teal)'; this.style.background='rgba(0,122,150,0.05)'"
        onmouseout="this.style.borderColor='#e0e8ed'; this.style.background='#f5f8fa'"
        >
          <div style="width:38px; height:38px; background:rgba(0,122,150,0.10); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="ph-fill ph-house-line" style="font-size:20px; color:var(--c-teal);"></i>
          </div>
          <div style="text-align:left;">
            <div style="font-size:15px; font-weight:700;">Desde otro lugar</div>
            <div style="font-size:12px; color:#888; font-weight:400; margin-top:1px;">Casa, coworking, etc.</div>
          </div>
        </button>

        <div id="campo-otro-lugar" style="display:none; margin-top:4px;">
          <input
            type="text"
            id="input-ubicacion-detalle"
            placeholder="Ej: Casa, Coworking, Cliente, etc."
            style="
              width:100%;
              padding:14px 16px;
              border:2px solid var(--c-teal);
              border-radius:12px;
              font-size:14px;
              box-sizing:border-box;
              font-family:inherit;
              color:var(--c-primary);
              outline:none;
            "
          >
          <button onclick="confirmarOtroLugar()" style="
            width:100%;
            margin-top:10px;
            padding:14px;
            background:var(--c-teal);
            color:#fff;
            border:none;
            border-radius:12px;
            font-size:15px;
            font-weight:700;
            cursor:pointer;
            font-family:inherit;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            transition: all 0.18s ease;
          "
          onmouseover="this.style.background='#006680'"
          onmouseout="this.style.background='var(--c-teal)'"
          >
            <i class="ph ph-check-circle" style="font-size:18px;"></i>
            Confirmar entrada
          </button>
        </div>
      </div>

      <button onclick="cerrarModalUbicacion()" style="
        width:100%;
        margin-top:16px;
        padding:11px;
        background:transparent;
        color:#999;
        border:none;
        font-size:13px;
        font-weight:600;
        cursor:pointer;
        font-family:inherit;
        border-radius:8px;
        transition: color 0.15s;
      "
      onmouseover="this.style.color='var(--c-primary)'"
      onmouseout="this.style.color='#999'"
      >
        Cancelar
      </button>
    </div>
  </div>

  <script>
  // Modal de ubicación
  function abrirModalUbicacion() {
    const modal = document.getElementById('modal-ubicacion');
    modal.style.display = 'flex';
    document.getElementById('campo-otro-lugar').style.display = 'none';
    document.getElementById('input-ubicacion-detalle').value = '';
  }

  function cerrarModalUbicacion() {
    document.getElementById('modal-ubicacion').style.display = 'none';
  }

  function mostrarCampoOtroLugar() {
    document.getElementById('campo-otro-lugar').style.display = 'block';
    document.getElementById('input-ubicacion-detalle').focus();
  }

  function seleccionarUbicacion(tipo) {
    cerrarModalUbicacion();
    marcarAsistencia('entrada', tipo, '');
  }

  function confirmarOtroLugar() {
    const detalle = document.getElementById('input-ubicacion-detalle').value.trim();
    if (!detalle) {
      alert('Por favor indica desde dónde estás trabajando');
      return;
    }
    cerrarModalUbicacion();
    marcarAsistencia('entrada', 'remoto', detalle);
  }

  // Cerrar modal al hacer clic fuera
  document.getElementById('modal-ubicacion').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalUbicacion();
  });

  // Toast de notificación profesional (reemplaza alert)
  function vToast(msg, tipo = 'success') {
    let t = document.getElementById('v-toast-asistencia');
    if (!t) {
      t = document.createElement('div');
      t.id = 'v-toast-asistencia';
      t.className = 'v-toast';
      document.body.appendChild(t);
    }
    t.className = 'v-toast' + (tipo === 'error' ? ' error' : '');
    t.innerHTML = `<i class="ph-fill ph-${tipo === 'error' ? 'x-circle' : 'check-circle'}"></i><span>${msg}</span>`;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => t.classList.add('show'));
    });
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 4000);
  }

  function marcarAsistencia(tipo, ubicacionTipo = '', ubicacionDetalle = '') {
    const btn = tipo === 'entrada' ? document.getElementById('btn-entrada') : document.getElementById('btn-salida');
    if (!btn) return;

    const titleEl = btn.querySelector('.asistencia-btn-title');
    const subEl   = btn.querySelector('.asistencia-btn-sub');
    const titleOrig = titleEl ? titleEl.textContent : '';
    const subOrig   = subEl   ? subEl.textContent   : '';

    btn.disabled = true;
    btn.style.opacity = '0.65';
    if (titleEl) titleEl.textContent = 'Registrando...';
    if (subEl)   subEl.textContent   = 'Por favor espera';

    const formData = new FormData();
    formData.append('action_asistencia', tipo);
    if (ubicacionTipo) {
      formData.append('ubicacion_tipo', ubicacionTipo);
      formData.append('ubicacion_detalle', ubicacionDetalle);
    }

    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Eliminar emojis del mensaje del backend antes de mostrarlo
        const msgLimpio = (data.message || '').replace(/[\u{1F300}-\u{1FAFF}]/gu, '').trim();
        vToast(msgLimpio || (tipo === 'entrada' ? 'Entrada registrada correctamente' : 'Salida registrada correctamente'));
        setTimeout(() => location.reload(), 1500);
      } else {
        vToast(data.message || 'No se pudo registrar', 'error');
        btn.disabled = false;
        btn.style.opacity = '1';
        if (titleEl) titleEl.textContent = titleOrig;
        if (subEl)   subEl.textContent   = subOrig;
      }
    })
    .catch(err => {
      console.error(err);
      vToast('Error de conexión. Intenta de nuevo.', 'error');
      btn.disabled = false;
      btn.style.opacity = '1';
      if (titleEl) titleEl.textContent = titleOrig;
      if (subEl)   subEl.textContent   = subOrig;
    });
  }

  // Reloj en tiempo real
  function actualizarReloj() {
    const reloj = document.getElementById('reloj-asistencia');
    if (reloj) {
      const ahora = new Date();
      const horas = String(ahora.getHours()).padStart(2, '0');
      const minutos = String(ahora.getMinutes()).padStart(2, '0');
      const segundos = String(ahora.getSeconds()).padStart(2, '0');
      reloj.textContent = `${horas}:${minutos}:${segundos}`;
    }
  }

  // Actualizar cada segundo
  setInterval(actualizarReloj, 1000);
  </script>

  <?php else: ?>
  <!-- No tiene jornada asignada -->
  <section style="margin-bottom: 28px;">
    <div class="asistencia-sin-jornada">
      <i class="ph ph-clock"></i>
      <h3 style="margin: 0 0 10px; color: var(--c-secondary); font-size: 18px; font-weight: 700;">
        Sin jornada asignada
      </h3>
      <p style="margin: 0; color: var(--c-body); opacity: 0.65; font-size: 14px;">
        Contacta a tu supervisor para que te asigne un horario de trabajo
      </p>
    </div>
  </section>
  <?php endif; ?>


  <div class="dashboard-grid">


  <!-- =====================

       Tarjeta: Perfil

       ===================== -->

  <div class="card" id="card-perfil" style="padding:0; overflow:hidden;">

    <!-- Franja superior brand -->
    <div style="background: var(--c-primary); height: 6px; width: 100%;"></div>

    <div style="padding: 20px;">
      <!-- Avatar + info principal -->
      <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">

        <!-- Avatar -->
        <div class="perfil-avatar-ring" aria-hidden="true">
          <?php echo htmlspecialchars($avatar_initials); ?>
        </div>

        <!-- Info -->
        <div style="flex:1; min-width:0;">
          <div class="perfil-info-name">
            <?php echo htmlspecialchars($nombre_emp); ?>
          </div>
          <div class="perfil-info-role" style="margin-top:4px;">
            <i class="ph ph-briefcase"></i>
            <?php echo htmlspecialchars($cargo_emp); ?>
            <?php if ($area_emp && $area_emp !== '—'): ?>
            <span style="color:#ddd; margin: 0 2px;">·</span>
            <i class="ph ph-building-office"></i>
            <?php echo htmlspecialchars($area_emp); ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>

 

  <!-- =====================

       Tarjeta: Metas personales y de equipo

       ===================== -->

<div class="card">

 

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
      <h3 style="margin:0;font-size:20px;font-weight:800;color:var(--c-secondary)">
        Ejecución y seguimiento
      </h3>

      <!-- Tabs de navegación -->
      <div id="tabs-ejecucion" class="tabs-valirica">
        <button class="tab-btn tab-valirica active" data-tab="metas">
          <i class="ph ph-target"></i> Metas
        </button>
        <button class="tab-btn tab-valirica" data-tab="tareas">
          <i class="ph ph-check-square"></i> Tareas
        </button>
        <button class="tab-btn tab-valirica" data-tab="proyectos">
          <i class="ph ph-folder-open"></i> Proyectos
        </button>
        <button class="tab-btn tab-valirica" data-tab="permisos">
          <i class="ph ph-calendar-check"></i> Permisos
        </button>
      </div>
    </div>

    <!-- ==================== TAB: METAS ==================== -->
    <div id="tab-content-metas" class="tab-content" style="display:block;">
    <div class="ds-grid ds-grid-2">

 

      <!-- =================================================

           Columna: Metas personales

           ================================================= -->

      <section aria-labelledby="meta-personal-title">

 

        <h4
          id="meta-personal-title"
          class="section-label"
          style="margin:0 0 10px 0;"
        >
          <i class="ph ph-user-circle"></i>
          Metas personales
        </h4>

 

        <ul

          id="lista-metas-personales"

          style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;"

        >

          <?php foreach($metas_personales as $mp):

            // fallback / cálculo de status si no existe la columna

            $status = $mp['status'] ?? (

              ($mp['is_completed']

                ? 'done'

                : (($mp['progress_pct'] > 0) ? 'dev' : 'pause')

              )

            );

            $progress = (int)($mp['progress_pct'] ?? 0);

            $help_req = !empty($mp['help_requested']) ? true : false;

            $meta_id_attr = htmlspecialchars('p'.$mp['id']);

          ?>

 

          <li

              class="card meta-item <?php echo $help_req ? 'meta-item--help-requested' : ''; ?>"

              data-meta-id="<?php echo (int)$mp['id']; ?>"

              style="padding:8px;border-radius:12px;border:1px solid #efefef;background:#fff;display:flex;flex-direction:column;gap:6px;"

            >

 

              <!-- Header meta -->

              <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">

                <div style="display:flex;align-items:center;gap:8px;flex:1;">

                  <strong style="flex:1;font-size:13px;font-weight:600;"><?php echo htmlspecialchars($mp['descripcion']); ?></strong>

 

                  <?php if ($help_req): ?>

                  <span class="meta-help-badge">
                    <i class="ph-fill ph-bell-ringing meta-help-icon" style="font-size:13px;"></i>
                    Ayuda solicitada
                  </span>

                  <?php endif; ?>

                </div>

 

                <small class="meta-status" aria-live="polite" style="white-space:nowrap;">

                  <?php

                    echo (

                      $status === 'done' ? 'Finalizada' :

                      ($status === 'pause' ? 'Pausada' :

                      ($status === 'help' ? 'Solicita ayuda' : 'En desarrollo'))

                    );

                  ?>

                </small>

              </div>

 

                <!-- Progreso -->

                <div class="progress-wrapper">

                  <div

                    class="progress"

                    role="progressbar"

                    aria-valuemin="0"

                    aria-valuemax="100"

                    aria-valuenow="<?php echo $progress; ?>"

                    style="flex:1;height:6px;background:#f1f1f1;border-radius:999px;overflow:hidden;"

                  >

                    <div

                      class="progress-bar"

                      style="width:<?php echo $progress; ?>%;height:100%;border-radius:999px;background:linear-gradient(90deg,#EF7F1B,#C65F00);transition:width 0.3s ease;"

                    ></div>

                  </div>

 

                  <div class="progress-input-group progress-tooltip"

                       data-tooltip="Ajusta el porcentaje de avance (0-100%)">

                    <span class="progress-label">Progreso</span>

                    <input

                      type="number"

                      data-meta-id="<?php echo (int)$mp['id']; ?>"

                      class="meta-percent"

                      value="<?php echo $progress; ?>"

                      min="0"

                      max="100"

                      step="5"

                      aria-label="Porcentaje de avance"

                      data-progress-level="<?php

                        echo $progress >= 100 ? 'complete' :

                             ($progress >= 75 ? 'high' :

                             ($progress >= 40 ? 'medium' : 'low'));

                      ?>"

                    >

                    <span class="percent-symbol">%</span>

                  </div>

                </div>

 

              <!-- Acciones -->

              <div style="display:flex;gap:8px;flex-wrap:wrap;">

                <button

                  class="meta-btn status-btn"

                  data-meta-id="<?php echo (int)$mp['id']; ?>"

                  data-status="pause"

                  aria-pressed="<?php echo $status==='pause' ? 'true':'false'; ?>"
                  title="Pausar"
                >
                  <i class="ph ph-pause-circle" style="font-size:15px;"></i>
                </button>

                <button
                  class="meta-btn status-btn"
                  data-meta-id="<?php echo (int)$mp['id']; ?>"
                  data-status="dev"
                  aria-pressed="<?php echo $status==='dev' ? 'true':'false'; ?>"
                  title="En desarrollo"
                >
                  <i class="ph ph-play-circle" style="font-size:15px;"></i>
                </button>

                <button
                  class="meta-btn status-btn"
                  data-meta-id="<?php echo (int)$mp['id']; ?>"
                  data-status="done"
                  aria-pressed="<?php echo $status==='done' ? 'true':'false'; ?>"
                  title="Finalizar"
                >
                  <i class="ph ph-check-circle" style="font-size:15px;"></i>
                </button>

              </div>

 

            </li>

 

 

          <?php endforeach; ?>

        </ul>

      </section>

 

<!-- =================================================

     Columna: Metas de equipo

     ================================================= -->

<section aria-labelledby="meta-equipo-title">

 

  <h4
    id="meta-equipo-title"
    class="section-label"
    style="margin:0 0 10px 0;"
  >
    <i class="ph ph-users-three"></i>
    Metas de equipo
  </h4>

 

  <ul

    id="lista-metas-equipo"

    style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;"

  >

    <?php foreach ($metas_equipo as $me):

      // Estado derivado (solo lectura)

      if (!empty($me['is_completed'])) {

        $estado = 'Finalizada';

      } elseif (!empty($me['due_date']) && strtotime($me['due_date']) < time()) {

        $estado = 'Vencida';

      } else {

        $estado = 'En curso';

      }

 

      // Fecha formateada

      $due = !empty($me['due_date'])

        ? date('d/m/Y', strtotime($me['due_date']))

        : 'Sin fecha';

    ?>

    <li

      style="

        padding:8px;

        border-radius:12px;

        border:1px dashed #ddd;

        background:#fff;

        display:flex;

        justify-content:space-between;

        align-items:center;

        gap:8px;

      "

    >

      <div>

        <strong style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($me['descripcion']); ?></strong>



        <div class="muted" style="font-size:12px;margin-top:4px;display:flex;align-items:center;gap:5px;">
          <i class="ph ph-calendar-blank" style="font-size:13px;color:var(--c-teal);"></i>
          <?php echo $due; ?>
        </div>

      </div>

 

      <div

        style="

          font-size:13px;

          font-weight:700;

          color:

            <?php

              echo $estado === 'Finalizada' ? '#2E7D32'

                   : ($estado === 'Vencida' ? '#C62828' : '#184656');

            ?>

        "

      >

        <?php echo $estado; ?>

      </div>

    </li>

    <?php endforeach; ?>


  </ul>

</section>

    </div>
    </div><!-- Fin tab-content-metas -->

    <!-- ==================== TAB: TAREAS ==================== -->
    <div id="tab-content-tareas" class="tab-content" style="display:none;">
      <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Header con filtros -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <select id="filtro-tarea-estado" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
              <option value="">Todos los estados</option>
              <option value="pendiente">Pendiente</option>
              <option value="en_progreso">En progreso</option>
              <option value="completada">Completada</option>
            </select>
            <select id="filtro-tarea-prioridad" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
              <option value="">Todas las prioridades</option>
              <option value="critica">Crítica</option>
              <option value="alta">Alta</option>
              <option value="media">Media</option>
              <option value="baja">Baja</option>
            </select>
          </div>
          <button onclick="openCreateModal()" style="padding:8px 16px;background:var(--c-teal);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;">
            <i class="ph ph-plus" style="font-size:14px;"></i>Nueva tarea / proyecto
          </button>
        </div>

        <!-- Lista de tareas -->
        <div id="lista-mis-tareas" style="display:flex;flex-direction:column;gap:8px;">
          <div style="text-align:center;padding:40px;color:#999;">
            <div style="font-size:48px;margin-bottom:12px;">📋</div>
            <p>Cargando tareas...</p>
          </div>
        </div>
      </div>
    </div><!-- Fin tab-content-tareas -->

    <!-- ==================== TAB: PROYECTOS ==================== -->
    <div id="tab-content-proyectos" class="tab-content" style="display:none;">
      <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <p style="margin:0;color:#666;font-size:13px;">
              Proyectos donde participas o eres líder
            </p>
            <select id="filtro-proyecto-estado" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
              <option value="">Todos los estados</option>
              <option value="planificacion">Planificación</option>
              <option value="en_progreso">En progreso</option>
              <option value="pausado">Pausado</option>
              <option value="completado">Completado</option>
            </select>
          </div>
          <button onclick="openCreateModal()" style="padding:8px 16px;background:var(--c-teal);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;">
            <i class="ph ph-plus" style="font-size:14px;"></i>Nueva tarea / proyecto
          </button>
        </div>

        <!-- Lista de proyectos -->
        <div id="lista-mis-proyectos" style="display:flex;flex-direction:column;gap:12px;">
          <div style="text-align:center;padding:40px;color:#999;">
            <div style="font-size:48px;margin-bottom:12px;">📁</div>
            <p>Cargando proyectos...</p>
          </div>
        </div>
      </div>
    </div><!-- Fin tab-content-proyectos -->

    <!-- ==================== TAB: PERMISOS ==================== -->
    <div id="tab-content-permisos" class="tab-content" style="display:none;">
      <div class="permisos-tab-panel">

        <p class="permisos-tab-intro">Gestiona tus ausencias desde aquí. Selecciona qué tipo de solicitud necesitas.</p>

        <div class="permisos-tab-actions">

          <button class="permisos-action-card" onclick="openPermisosVacacionesModal(); setTimeout(()=>switchPVTab('permiso'),80);">
            <span class="permisos-action-icon"><i class="ph ph-clock"></i></span>
            <div class="permisos-action-body">
              <strong>Solicitar Permiso</strong>
              <span>Médico, personal, académico y más</span>
            </div>
            <i class="ph ph-arrow-right permisos-action-arrow"></i>
          </button>

          <button class="permisos-action-card" onclick="openPermisosVacacionesModal(); setTimeout(()=>switchPVTab('vacacion'),80);">
            <span class="permisos-action-icon" style="background:var(--c-primary-light,#fff5ee);color:var(--c-primary)"><i class="ph ph-sun-horizon"></i></span>
            <div class="permisos-action-body">
              <strong>Solicitar Vacaciones</strong>
              <span>Planifica tus días de descanso</span>
            </div>
            <i class="ph ph-arrow-right permisos-action-arrow"></i>
          </button>

          <button class="permisos-action-card permisos-action-card--secondary" onclick="openPermisosVacacionesModal(); setTimeout(()=>switchPVTab('notificaciones'),80);">
            <span class="permisos-action-icon" style="background:#f0f4ff;color:#3B5BDB"><i class="ph ph-bell"></i></span>
            <div class="permisos-action-body">
              <strong>Notificaciones y Historial</strong>
              <span>Revisa el estado de tus solicitudes</span>
            </div>
            <i class="ph ph-arrow-right permisos-action-arrow"></i>
          </button>

          <button class="permisos-action-card permisos-action-card--secondary" onclick="openPermisosVacacionesModal(); setTimeout(()=>switchPVTab('proximos'),80);">
            <span class="permisos-action-icon" style="background:#f0fdf4;color:#16A34A"><i class="ph ph-calendar"></i></span>
            <div class="permisos-action-body">
              <strong>Próximas Ausencias</strong>
              <span>Consulta lo que tienes agendado</span>
            </div>
            <i class="ph ph-arrow-right permisos-action-arrow"></i>
          </button>

        </div>
      </div>
    </div><!-- Fin tab-content-permisos -->

</div>

<!-- =========================================================
     JS — Interacciones de metas (cliente)
     ========================================================= -->
<script>
(function(){

  /* =======================================================
     Variables base
     ======================================================= */
  const EMPLEADO_ID = <?php echo (int)$empleado_id; ?>;

  const listaPersonales = document.getElementById('lista-metas-personales');
  const helpList        = document.getElementById('help-list');
  const btnCrearMeta    = document.querySelector('[data-action="crear-meta"]');

  /* =======================================================
     AJAX helper
     ======================================================= */
  function sendAjax(formData){
    return fetch('ajax_metas.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json());
  }

  /* =======================================================
     BOTÓN SUBNAV — Crear meta
     ======================================================= */
  if (btnCrearMeta) {
    btnCrearMeta.addEventListener('click', openCreateMetaModal);
  }

  /* =======================================================
     MODAL — Crear meta personal
     ======================================================= */
  function openCreateMetaModal(){
    if (document.getElementById('create-meta-modal')) return;

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'create-meta-modal';

    overlay.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <div class="modal-icon"><i class="ph ph-target" style="font-size:28px;color:var(--c-teal);"></i></div>
          <h3 class="modal-title">Nueva meta personal</h3>
        </div>

        <div class="modal-body">
          <label class="help-option">
            <div class="help-option-label">
              Vincular a meta de área
              <div class="help-option-sublabel">
                Selecciona la meta del área a la que pertenece, o "Meta Personal" si no aplica
              </div>
            </div>
            <select id="meta-area-select"
              style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
              <option value="">Cargando metas...</option>
            </select>
          </label>

          <label class="help-option">
            <div class="help-option-label">
              Descripción de la meta
              <div class="help-option-sublabel">
                Sé claro y accionable
              </div>
            </div>
            <input id="meta-desc"
              type="text"
              placeholder="Ej: Cerrar proyecto Q2"
              style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
          </label>

          <label class="help-option">
            <div class="help-option-label">
              Fecha objetivo
            </div>
            <input id="meta-date"
              type="date"
              style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
          </label>
        </div>

        <div class="modal-actions">
          <button class="modal-btn modal-btn-cancel" onclick="closeCreateMetaModal()">
            Cancelar
          </button>
          <button class="modal-btn modal-btn-confirm" onclick="submitCreateMeta(this)">
            Crear meta
          </button>
        </div>
      </div>
    `;

    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeCreateMetaModal();
    });

    document.body.appendChild(overlay);

    // Cargar metas del área
    loadAreaMetas();
  }

  // Exponer función globalmente para que sea accesible desde menú flotante
  window.openCreateMetaModal = openCreateMetaModal;

  window.closeCreateMetaModal = function(){
    const modal = document.getElementById('create-meta-modal');
    if (!modal) return;
    modal.style.opacity = '0';
    setTimeout(() => modal.remove(), 200);
  };

  /* =======================================================
     CARGAR METAS DEL ÁREA
     ======================================================= */
  function loadAreaMetas(){
    const select = document.getElementById('meta-area-select');
    if (!select) return;

    const fd = new FormData();
    fd.append('action', 'get_area_metas');
    fd.append('empleado_id', EMPLEADO_ID);

    console.log('🔍 Solicitando metas del área para empleado:', EMPLEADO_ID);

    sendAjax(fd)
      .then(r => {
        console.log('📦 Respuesta get_area_metas:', r);

        if (r.ok && r.metas){
          select.innerHTML = '<option value="personal">Meta Personal (sin vincular a meta de área)</option>';

          console.log(`✅ Metas encontradas: ${r.metas.length}`);

          if (r.metas.length > 0){
            r.metas.forEach(meta => {
              console.log('  → Meta:', meta.descripcion);
              const option = document.createElement('option');
              option.value = meta.id;
              option.textContent = meta.descripcion;
              select.appendChild(option);
            });
          }
        } else {
          console.warn('⚠️ No se obtuvieron metas o respuesta no válida:', r);
          select.innerHTML = '<option value="personal">Meta Personal (sin vincular)</option>';
        }
      })
      .catch(err => {
        console.error('❌ Error al cargar metas:', err);
        select.innerHTML = '<option value="personal">Meta Personal (sin vincular)</option>';
      });
  }

  /* =======================================================
     SUBMIT — Crear meta personal
     ======================================================= */
  window.submitCreateMeta = function(btn){
    const desc = document.getElementById('meta-desc').value.trim();
    const date = document.getElementById('meta-date').value;
    const metaAreaSelect = document.getElementById('meta-area-select').value;

    if (!desc){
      alert('Escribe una descripción para la meta');
      return;
    }

    if (!metaAreaSelect){
      alert('Selecciona una meta de área o "Meta Personal"');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const fd = new FormData();
    fd.append('action', 'create_meta_personal');
    fd.append('empleado_id', EMPLEADO_ID);
    fd.append('descripcion', desc);
    fd.append('due_date', date || '');
    fd.append('meta_area_id', metaAreaSelect === 'personal' ? '' : metaAreaSelect);

    sendAjax(fd)
      .then(r => {
        if (!r.ok){
          alert(r.msg || 'Error al crear la meta');
          btn.disabled = false;
          btn.textContent = 'Crear meta';
          return;
        }

        closeCreateMetaModal();
        alert('Meta creada correctamente');
        location.reload();
      })
      .catch(() => {
        alert('Error de conexión');
        btn.disabled = false;
        btn.textContent = 'Crear meta';
      });
  };

  /* =======================================================
     ACTUALIZAR PROGRESO — Metas personales
     ======================================================= */
  // Evento: Cambio en los inputs de progreso
  if (listaPersonales) {
    listaPersonales.addEventListener('change', (e) => {
      if (e.target.classList.contains('meta-percent')) {
        const metaId = parseInt(e.target.dataset.metaId, 10);
        let progress = parseInt(e.target.value, 10);

        // Validar rango 0-100
        if (progress < 0) progress = 0;
        if (progress > 100) progress = 100;
        e.target.value = progress;

        console.log(`📊 Actualizando progreso de meta ${metaId} a ${progress}%`);

        // Enviar actualización
        const fd = new FormData();
        fd.append('action', 'update_progress');
        fd.append('empleado_id', EMPLEADO_ID);
        fd.append('meta_id', metaId);
        fd.append('progress_pct', progress);

        sendAjax(fd)
          .then(r => {
            if (r.ok) {
              console.log(`✅ Progreso actualizado: ${progress}%`);

              // Actualizar nivel visual del input
              const level = progress >= 100 ? 'complete' :
                           (progress >= 75 ? 'high' :
                           (progress >= 40 ? 'medium' : 'low'));
              e.target.setAttribute('data-progress-level', level);

              // Actualizar barra de progreso
              const progressBar = e.target.closest('.meta-item').querySelector('.progress-bar');
              if (progressBar) {
                progressBar.style.width = progress + '%';
              }

              // Actualizar aria-valuenow
              const progressElement = e.target.closest('.meta-item').querySelector('.progress');
              if (progressElement) {
                progressElement.setAttribute('aria-valuenow', progress);
              }

              // Mostrar feedback visual
              e.target.style.borderColor = '#00D98F';
              setTimeout(() => {
                e.target.style.borderColor = '';
              }, 500);

            } else {
              console.error('❌ Error al actualizar progreso:', r.msg);
              alert('Error: ' + (r.msg || 'No se pudo actualizar el progreso'));
            }
          })
          .catch(err => {
            console.error('❌ Error de conexión:', err);
            alert('Error de conexión al actualizar progreso');
          });
      }
    });
  }

  /* =======================================================
     ACTUALIZAR ESTADO — Metas personales
     ======================================================= */
  // Evento: Botones de estado (pause/dev/done)
  if (listaPersonales) {
    listaPersonales.addEventListener('click', (e) => {
      if (e.target.classList.contains('status-btn')) {
        const metaId = parseInt(e.target.dataset.metaId, 10);
        const status = e.target.dataset.status;

        console.log(`🔄 Actualizando estado de meta ${metaId} a "${status}"`);

        // Enviar actualización
        const fd = new FormData();
        fd.append('action', 'update_status');
        fd.append('empleado_id', EMPLEADO_ID);
        fd.append('meta_id', metaId);
        fd.append('status', status);

        sendAjax(fd)
          .then(r => {
            if (r.ok) {
              console.log(`✅ Estado actualizado a: ${status}`);

              // Actualizar UI: remover aria-pressed de todos los botones de esta meta
              const metaItem = e.target.closest('.meta-item');
              metaItem.querySelectorAll('.status-btn').forEach(btn => {
                btn.setAttribute('aria-pressed', 'false');
              });

              // Marcar el botón actual como pressed
              e.target.setAttribute('aria-pressed', 'true');

              // Actualizar texto de estado
              const statusLabel = metaItem.querySelector('.meta-status');
              if (statusLabel) {
                const statusText = {
                  'pause': 'Pausada',
                  'dev': 'En desarrollo',
                  'done': 'Finalizada',
                  'help': 'Solicita ayuda'
                };
                statusLabel.textContent = statusText[status] || status;
              }

              // Si el estado es 'done', actualizar progreso a 100%
              if (status === 'done') {
                const progressInput = metaItem.querySelector('.meta-percent');
                if (progressInput) {
                  progressInput.value = 100;
                  progressInput.setAttribute('data-progress-level', 'complete');
                }
                const progressBar = metaItem.querySelector('.progress-bar');
                if (progressBar) {
                  progressBar.style.width = '100%';
                }
              }

              // Feedback visual
              e.target.style.transform = 'scale(1.1)';
              setTimeout(() => {
                e.target.style.transform = '';
              }, 200);

            } else {
              console.error('❌ Error al actualizar estado:', r.msg);
              alert('Error: ' + (r.msg || 'No se pudo actualizar el estado'));
            }
          })
          .catch(err => {
            console.error('❌ Error de conexión:', err);
            alert('Error de conexión al actualizar estado');
          });
      }
    });
  }

})();
</script>

<!-- =========================================================
     JS — Sistema de Tareas y Proyectos
     ========================================================= -->
<script>
(function(){
  const EMPLEADO_ID = <?php echo (int)$empleado_id; ?>;
  const USER_ID = <?php echo (int)$user_id; ?>;

  /* =======================================================
     TABS - Cambio entre Metas, Tareas, Proyectos
     ======================================================= */
  const tabsContainer = document.getElementById('tabs-ejecucion');
  if (tabsContainer) {
    tabsContainer.addEventListener('click', (e) => {
      const btn = e.target.closest('.tab-btn');
      if (!btn) return;

      const tabName = btn.dataset.tab;

      // Actualizar botones activos
      // Actualizar estado visual de tabs (las clases CSS manejan el estilo)
      tabsContainer.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.background = '';
        b.style.color = '';
        b.style.boxShadow = '';
      });
      btn.classList.add('active');

      // Mostrar/ocultar contenido
      document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
      });
      const targetContent = document.getElementById('tab-content-' + tabName);
      if (targetContent) {
        targetContent.style.display = 'block';

        // Cargar datos según el tab
        if (tabName === 'tareas') {
          cargarMisTareas();
        } else if (tabName === 'proyectos') {
          cargarMisProyectos();
        }
      }
    });
  }

  /* =======================================================
     BOTÓN "CREAR TAREA" EN SUBNAV (eliminado, acciones en tabs)
     ======================================================= */

  /* =======================================================
     CARGAR MIS TAREAS
     ======================================================= */
  window.cargarMisTareas = async function() {
    const container = document.getElementById('lista-mis-tareas');
    if (!container) return;

    container.innerHTML = `
      <div style="text-align:center;padding:40px;color:#999;">
        <i class="ph ph-spinner" style="font-size:32px;margin-bottom:8px;display:block;color:#ccc;"></i>
        <p>Cargando tareas...</p>
      </div>
    `;

    try {
      const filtroEstado = document.getElementById('filtro-tarea-estado')?.value || '';
      const filtroPrioridad = document.getElementById('filtro-tarea-prioridad')?.value || '';

      const params = new URLSearchParams({
        action: 'obtener_tareas_empleado',
        usuario_id: USER_ID,
        responsable_id: EMPLEADO_ID
      });
      if (filtroEstado) params.append('estado', filtroEstado);
      if (filtroPrioridad) params.append('prioridad', filtroPrioridad);

      const response = await fetch('proyectos_tareas_backend.php?' + params.toString());
      const data = await response.json();

      if (!data.ok) {
        container.innerHTML = `
          <div style="text-align:center;padding:40px;color:#C62828;">
            <i class="ph ph-warning-circle" style="font-size:32px;margin-bottom:8px;display:block;"></i>
            <p>${data.error || 'Error al cargar tareas'}</p>
          </div>
        `;
        return;
      }

      if (!data.tareas || data.tareas.length === 0) {
        container.innerHTML = `
          <div style="text-align:center;padding:40px;color:#999;">
            <i class="ph ph-check-circle" style="font-size:48px;margin-bottom:12px;display:block;color:#ccc;"></i>
            <p>No tienes tareas asignadas</p>
            <button onclick="openCreateModal()" style="margin-top:12px;padding:10px 20px;background:var(--c-teal);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-family:inherit;">
              Crear primera tarea
            </button>
          </div>
        `;
        return;
      }

      // Renderizar tareas
      container.innerHTML = data.tareas.map(tarea => renderTareaItem(tarea)).join('');

    } catch (err) {
      console.error('Error al cargar tareas:', err);
      container.innerHTML = `
        <div style="text-align:center;padding:40px;color:#C62828;">
          <i class="ph ph-wifi-slash" style="font-size:32px;margin-bottom:8px;display:block;"></i>
          <p>Error de conexión</p>
        </div>
      `;
    }
  };

  /* =======================================================
     RENDERIZAR ITEM DE TAREA
     ======================================================= */
  function renderTareaItem(tarea) {
    const prioridadColors = {
      'critica': '#C62828',
      'alta': '#EF6C00',
      'media': '#1565C0',
      'baja': '#2E7D32'
    };
    const estadoBadge = {
      'pendiente': { bg: '#FFF3E0', color: '#E65100', text: 'Pendiente' },
      'en_progreso': { bg: '#E3F2FD', color: '#1565C0', text: 'En progreso' },
      'completada': { bg: '#E8F5E9', color: '#2E7D32', text: 'Completada' },
      'cancelada': { bg: '#FFEBEE', color: '#C62828', text: 'Cancelada' }
    };

    const estado = estadoBadge[tarea.estado] || estadoBadge['pendiente'];
    const prioridadColor = prioridadColors[tarea.prioridad] || '#666';

    // Indicador de deadline
    let deadlineClass = '';
    let deadlineIconCls = 'ph ph-calendar-blank';
    let deadlineIconColor = '#999';
    if (tarea.indicador_deadline === 'vencida') {
      deadlineClass = 'color:#C62828;font-weight:700;';
      deadlineIconCls = 'ph-fill ph-warning-circle';
      deadlineIconColor = '#C62828';
    } else if (tarea.indicador_deadline === 'vence_hoy') {
      deadlineClass = 'color:#EF6C00;font-weight:700;';
      deadlineIconCls = 'ph-fill ph-clock';
      deadlineIconColor = '#EF6C00';
    } else if (tarea.indicador_deadline === 'proxima_vencer') {
      deadlineClass = 'color:#F9A825;';
      deadlineIconCls = 'ph ph-clock';
      deadlineIconColor = '#F9A825';
    }

    const deadlineText = tarea.deadline
      ? new Date(tarea.deadline).toLocaleDateString('es-ES', { day: '2-digit', month: 'short' })
      : 'Sin fecha';

    return `
      <div class="tarea-item" data-tarea-id="${tarea.id}" style="
        padding:12px 16px;
        background:#fff;
        border:1px solid #e6e6e6;
        border-radius:12px;
        border-left:4px solid ${prioridadColor};
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
      ">
        <div style="flex:1;min-width:200px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <strong style="font-size:14px;color:#184656;">${escapeHtml(tarea.titulo)}</strong>
            <span style="
              font-size:11px;
              padding:2px 8px;
              border-radius:4px;
              background:${estado.bg};
              color:${estado.color};
              font-weight:600;
            ">${estado.text}</span>
          </div>
          <div style="font-size:12px;color:#666;display:flex;align-items:center;gap:5px;">
            <i class="ph ph-folder-open" style="font-size:13px;color:var(--c-teal);"></i>
            ${escapeHtml(tarea.proyecto_titulo || 'Sin proyecto')}
          </div>
          <div style="font-size:12px;color:#666;margin-top:2px;display:flex;align-items:center;gap:5px;">
            <i class="ph ph-user" style="font-size:13px;color:var(--c-teal);"></i>
            ${tarea.responsable_nombre ? escapeHtml(tarea.responsable_nombre) : '<span style="color:#EF7F1B;font-weight:600;">Sin asignar</span>'}
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:16px;">
          <div style="text-align:center;">
            <div style="font-size:11px;color:#999;">Deadline</div>
            <div style="font-size:13px;${deadlineClass};display:flex;align-items:center;gap:4px;"><i class="${deadlineIconCls}" style="font-size:14px;color:${deadlineIconColor};"></i>${deadlineText}</div>
          </div>

          ${tarea.estado !== 'completada' && tarea.estado !== 'cancelada' ? `
          <select onchange="cambiarEstadoTarea(${tarea.id}, this.value)" style="
            padding:6px 10px;
            border:1px solid #ddd;
            border-radius:6px;
            font-size:12px;
            cursor:pointer;
          ">
            <option value="pendiente" ${tarea.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
            <option value="en_progreso" ${tarea.estado === 'en_progreso' ? 'selected' : ''}>En progreso</option>
            <option value="completada">Marcar completada</option>
          </select>
          ` : ''}
        </div>
      </div>
    `;
  }

  /* =======================================================
     CAMBIAR ESTADO DE TAREA
     ======================================================= */
  window.cambiarEstadoTarea = async function(tareaId, nuevoEstado) {
    try {
      const fd = new FormData();
      fd.append('action', 'cambiar_estado_tarea');
      fd.append('usuario_id', USER_ID);
      fd.append('tarea_id', tareaId);
      fd.append('estado', nuevoEstado);

      const response = await fetch('proyectos_tareas_backend.php', {
        method: 'POST',
        body: fd
      });
      const data = await response.json();

      if (data.ok) {
        cargarMisTareas();
      } else {
        alert('Error: ' + (data.error || 'No se pudo actualizar'));
      }
    } catch (err) {
      console.error('Error:', err);
      alert('Error de conexión');
    }
  };

  


/* ==========================================================================
   PARTE 1: ESTILOS CSS - Agregar dentro de <style> en el <head>
   ========================================================================== */

const PROYECTOS_CSS = `
<style id="proyectos-ux-styles">
  /* =============================================
     SISTEMA DE VARIABLES CSS
     ============================================= */
  :root {
    /* Tipografía - Escala Modular 1.25 */
    --text-xs: 11px;
    --text-sm: 13px;
    --text-base: 15px;
    --text-lg: 18px;
    --text-xl: 22px;
    --text-2xl: 28px;

    /* Espaciado - Sistema 8px */
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-5: 24px;
    --space-6: 32px;

    /* Colores - Paleta Valirica */
    --color-primary: #007a96;
    --color-primary-dark: #006680;
    --color-secondary: #012133;
    --color-accent: #EF7F1B;
    --color-accent-dark: #d66f15;

    /* Estados */
    --color-success: #10b981;
    --color-success-bg: #ecfdf5;
    --color-warning: #f59e0b;
    --color-warning-bg: #fffbeb;
    --color-danger: #ef4444;
    --color-danger-bg: #fef2f2;
    --color-info: #3b82f6;
    --color-info-bg: #eff6ff;
    --color-neutral: #6b7280;
    --color-neutral-bg: #f3f4f6;

    /* Textos */
    --text-primary: #111827;
    --text-secondary: #4b5563;
    --text-tertiary: #6b7280;
    --text-muted: #9ca3af;

    /* Fondos */
    --bg-primary: #ffffff;
    --bg-secondary: #f9fafb;
    --bg-tertiary: #f3f4f6;

    /* Bordes */
    --border-color: #e5e7eb;
    --border-color-light: #f3f4f6;

    /* Sombras */
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);

    /* Transiciones */
    --transition-fast: 0.15s ease;
    --transition-normal: 0.25s ease;
    --transition-slow: 0.4s ease;
  }

  /* =============================================
     SKELETON LOADING
     ============================================= */
  @keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
  }

  .skeleton {
    background: linear-gradient(90deg,
      var(--bg-tertiary) 25%,
      var(--bg-secondary) 50%,
      var(--bg-tertiary) 75%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
  }

  .skeleton-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: var(--space-5);
    margin-bottom: var(--space-4);
  }

  .skeleton-title {
    height: 24px;
    width: 60%;
    margin-bottom: var(--space-3);
  }

  .skeleton-text {
    height: 14px;
    width: 40%;
    margin-bottom: var(--space-2);
  }

  .skeleton-bar {
    height: 10px;
    width: 100%;
    margin-top: var(--space-4);
  }

  /* =============================================
     ANIMACIONES
     ============================================= */
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-10px);
      max-height: 0;
    }
    to {
      opacity: 1;
      transform: translateY(0);
      max-height: 1000px;
    }
  }

  @keyframes slideUp {
    from {
      opacity: 1;
      max-height: 1000px;
    }
    to {
      opacity: 0;
      max-height: 0;
    }
  }

  @keyframes scaleIn {
    from {
      opacity: 0;
      transform: scale(0.95);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
  }

  @keyframes toastSlideIn {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* =============================================
     PROYECTO CARD
     ============================================= */
  .proyecto-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: var(--space-4);
    box-shadow: var(--shadow-sm);
    transition: box-shadow var(--transition-normal), border-color var(--transition-normal);
  }

  .proyecto-card:hover {
    box-shadow: var(--shadow-md);
    border-color: #d1d5db;
  }

  .proyecto-card.es-lider {
    border-left: 4px solid var(--color-primary);
  }

  .proyecto-header {
    padding: var(--space-5);
    background: var(--bg-secondary);
    cursor: pointer;
    transition: background var(--transition-fast);
    user-select: none;
  }

  .proyecto-header:hover {
    background: var(--bg-tertiary);
  }

  .proyecto-header:active {
    background: #ebedf0;
  }

  /* =============================================
     STATUS DOTS - Sistema de indicadores
     ============================================= */
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
  }

  .status-dot.pending { background-color: var(--color-warning); }
  .status-dot.in-progress { background-color: var(--color-info); }
  .status-dot.completed { background-color: var(--color-success); }
  .status-dot.cancelled { background-color: var(--color-neutral); }
  .status-dot.planning { background-color: #a855f7; }
  .status-dot.paused { background-color: #f97316; }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: var(--text-xs);
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    white-space: nowrap;
  }

  /* =============================================
     PROGRESS BAR
     ============================================= */
  .progress-container {
    margin-top: var(--space-4);
  }

  .progress-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 6px;
  }

  .progress-label {
    font-size: var(--text-sm);
    color: var(--text-tertiary);
    font-weight: 500;
  }

  .progress-value {
    font-size: var(--text-xl);
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
  }

  .progress-bar-track {
    height: 10px;
    background: var(--bg-tertiary);
    border-radius: 5px;
    overflow: hidden;
    position: relative;
  }

  .progress-bar-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }

  .progress-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
      90deg,
      transparent,
      rgba(255,255,255,0.3),
      transparent
    );
    animation: shimmer 2s infinite;
    background-size: 200% 100%;
  }

  .progress-bar-fill.low { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
  .progress-bar-fill.medium { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
  .progress-bar-fill.high { background: linear-gradient(90deg, #10b981, #34d399); }
  .progress-bar-fill.complete { background: linear-gradient(90deg, #059669, #10b981); }

  /* =============================================
     TABLA DE TAREAS
     ============================================= */
  .tareas-container {
    overflow: hidden;
    animation: slideDown var(--transition-normal) ease-out;
  }

  .tareas-container.closing {
    animation: slideUp var(--transition-fast) ease-in;
  }

  .tareas-header {
    padding: var(--space-3) var(--space-4);
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .tareas-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--text-sm);
  }

  .tareas-table th {
    padding: var(--space-3) var(--space-4);
    text-align: left;
    font-weight: 600;
    color: var(--text-tertiary);
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .tareas-table td {
    padding: var(--space-3) var(--space-4);
    border-bottom: 1px solid var(--border-color-light);
    vertical-align: middle;
  }

  .tareas-table tr {
    transition: background var(--transition-fast);
  }

  .tareas-table tbody tr:hover {
    background: var(--bg-secondary);
  }

  .tareas-table tbody tr:last-child td {
    border-bottom: none;
  }

  .tarea-row.mi-tarea {
    border-left: 3px solid var(--color-accent);
    background: linear-gradient(90deg, rgba(239,127,27,0.05) 0%, transparent 50%);
  }

  .tarea-row.mi-tarea:hover {
    background: linear-gradient(90deg, rgba(239,127,27,0.08) 0%, var(--bg-secondary) 50%);
  }

  /* =============================================
     DEADLINE INDICATORS
     ============================================= */
  .deadline-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: var(--text-xs);
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 500;
  }

  .deadline-badge.overdue {
    background: var(--color-danger-bg);
    color: var(--color-danger);
    font-weight: 700;
  }

  .deadline-badge.today {
    background: #fff7ed;
    color: #ea580c;
    font-weight: 600;
  }

  .deadline-badge.soon {
    background: var(--color-warning-bg);
    color: #b45309;
  }

  .deadline-badge.normal {
    background: transparent;
    color: var(--text-tertiary);
  }

  /* =============================================
     AVATAR
     ============================================= */
  .avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
  }

  .avatar.primary { background: var(--color-primary); }
  .avatar.accent { background: var(--color-accent); }

  /* =============================================
     BOTONES
     ============================================= */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: var(--text-sm);
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all var(--transition-fast);
    white-space: nowrap;
  }

  .btn:active {
    transform: scale(0.98);
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    color: white;
  }

  .btn-primary:hover {
    box-shadow: 0 4px 12px rgba(0, 122, 150, 0.4);
    transform: translateY(-1px);
  }

  .btn-accent {
    background: linear-gradient(135deg, var(--color-accent), var(--color-accent-dark));
    color: white;
  }

  .btn-accent:hover {
    box-shadow: 0 4px 12px rgba(239, 127, 27, 0.4);
    transform: translateY(-1px);
  }

  .btn-ghost {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
  }

  .btn-ghost:hover {
    background: var(--border-color);
  }

  .btn-sm {
    padding: 6px 12px;
    font-size: var(--text-xs);
  }

  /* =============================================
     TOAST NOTIFICATIONS
     ============================================= */
  .toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: var(--text-sm);
    z-index: 10000;
    animation: toastSlideIn 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: var(--shadow-lg);
  }

  .toast.success {
    background: var(--color-success);
    color: white;
  }

  .toast.error {
    background: var(--color-danger);
    color: white;
  }

  /* =============================================
     MODAL
     ============================================= */
  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(17, 24, 39, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.2s ease;
  }

  .modal-content {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: var(--space-6);
    max-width: 480px;
    width: 90%;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: scaleIn 0.25s ease;
  }

  .modal-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    padding-bottom: var(--space-4);
    border-bottom: 2px solid var(--bg-tertiary);
  }

  .modal-header h3 {
    margin: 0;
    font-size: var(--text-lg);
    font-weight: 800;
    color: var(--text-primary);
  }

  .form-group {
    margin-bottom: var(--space-4);
  }

  .form-label {
    display: block;
    font-size: var(--text-xs);
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .form-input {
    width: 100%;
    padding: var(--space-3);
    border-radius: 10px;
    border: 2px solid var(--border-color);
    font-size: var(--text-base);
    box-sizing: border-box;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    outline: none;
  }

  .form-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(0, 122, 150, 0.15);
  }

  .modal-actions {
    display: flex;
    gap: var(--space-3);
    justify-content: flex-end;
    margin-top: var(--space-5);
  }

  /* =============================================
     EMPTY STATE
     ============================================= */
  .empty-state {
    text-align: center;
    padding: 60px var(--space-5);
    color: var(--text-tertiary);
  }

  .empty-state-icon {
    font-size: 56px;
    margin-bottom: var(--space-4);
    opacity: 0.5;
  }

  .empty-state-title {
    font-size: var(--text-lg);
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: var(--space-2);
  }

  .empty-state-text {
    font-size: var(--text-sm);
    color: var(--text-muted);
  }

  /* =============================================
     RESPONSIVE - MÓVIL
     ============================================= */
  @media (max-width: 768px) {
    .proyecto-header {
      padding: var(--space-4);
    }

    .proyecto-title {
      font-size: var(--text-base) !important;
    }

    /* PROYECTO META - Alineado a la izquierda, compacto */
    .proyecto-meta {
      flex-direction: column;
      gap: 4px !important;
      align-items: flex-start !important;
      line-height: 1.4;
    }

    /* Ocultar separadores "•" en móvil */
    .proyecto-meta > span[style*="color: var(--border-color)"] {
      display: none !important;
    }

    /* Cada item de metadata más compacto */
    .proyecto-meta > span {
      font-size: 12px;
      line-height: 1.35;
    }

    /* Avatar más pequeño en móvil */
    .proyecto-meta .avatar {
      width: 18px !important;
      height: 18px !important;
      font-size: 8px !important;
    }

    /* PROYECTO ACTIONS - Reorganizado */
    .proyecto-actions {
      flex-direction: row !important;
      flex-wrap: wrap;
      align-items: center !important;
      gap: 8px !important;
      margin-top: 8px;
    }

    /* Header principal más compacto */
    .proyecto-header > .flex:first-child {
      flex-wrap: wrap;
      gap: 8px !important;
    }

    /* Progreso más compacto */
    .progress-container {
      margin-top: var(--space-3);
    }

    .progress-value {
      font-size: var(--text-lg);
    }

    .progress-bar-track {
      height: 8px;
    }

    /* Tabla de tareas */
    .tareas-table {
      font-size: var(--text-xs);
    }

    .tareas-table th,
    .tareas-table td {
      padding: var(--space-2) var(--space-3);
    }

    .tareas-table .col-responsable {
      display: none;
    }

    .modal-content {
      padding: var(--space-5);
      margin: var(--space-3);
    }
  }

  /* MÓVIL PEQUEÑO */
  @media (max-width: 480px) {
    .proyecto-header {
      padding: 12px;
    }

    .proyecto-title {
      font-size: 14px !important;
    }

    .proyecto-meta > span {
      font-size: 11px;
    }

    .status-badge {
      font-size: 10px;
      padding: 4px 8px;
    }

    .btn.btn-sm {
      font-size: 11px;
      padding: 6px 10px;
    }
  }

  /* =============================================
     UTILIDADES
     ============================================= */
  .text-strikethrough {
    text-decoration: line-through;
    opacity: 0.6;
  }

  .flex {
    display: flex;
  }

  .items-center {
    align-items: center;
  }

  .justify-between {
    justify-content: space-between;
  }

  .gap-2 { gap: var(--space-2); }
  .gap-3 { gap: var(--space-3); }
  .gap-4 { gap: var(--space-4); }

  .mt-2 { margin-top: var(--space-2); }
  .mt-3 { margin-top: var(--space-3); }
  .mt-4 { margin-top: var(--space-4); }

  /* Toggle icon rotation */
  .toggle-icon {
    transition: transform var(--transition-normal);
    font-size: 12px;
    color: var(--text-muted);
  }

  .toggle-icon.expanded {
    transform: rotate(180deg);
  }
</style>
`;

// Inyectar estilos si no existen
if (!document.getElementById('proyectos-ux-styles')) {
  document.head.insertAdjacentHTML('beforeend', PROYECTOS_CSS);
}


/* ==========================================================================
   PARTE 2: FUNCIONES JAVASCRIPT
   ========================================================================== */

/* =============================================
   SKELETON LOADING - HTML
   ============================================= */
function getSkeletonHTML() {
  return `
    <div class="skeleton-card">
      <div class="skeleton skeleton-title"></div>
      <div class="skeleton skeleton-text"></div>
      <div class="skeleton skeleton-text" style="width: 30%"></div>
      <div class="skeleton skeleton-bar"></div>
    </div>
    <div class="skeleton-card">
      <div class="skeleton skeleton-title"></div>
      <div class="skeleton skeleton-text"></div>
      <div class="skeleton skeleton-text" style="width: 30%"></div>
      <div class="skeleton skeleton-bar"></div>
    </div>
  `;
}


/* =============================================
   CARGAR MIS PROYECTOS - VERSIÓN OPTIMIZADA
   ============================================= */
window.cargarMisProyectos = async function() {
  const container = document.getElementById('lista-mis-proyectos');
  if (!container) return;

  // Skeleton loading
  container.innerHTML = getSkeletonHTML();

  try {
    const filtroEstado = document.getElementById('filtro-proyecto-estado')?.value || '';

    const params = new URLSearchParams({
      action: 'obtener_mis_proyectos',
      empleado_id: EMPLEADO_ID,
      usuario_id: USER_ID
    });
    if (filtroEstado) params.append('estado', filtroEstado);

    const response = await fetch('proyectos_tareas_backend.php?' + params.toString());
    const data = await response.json();

    if (!data.ok) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="ph ph-warning-circle empty-state-icon" style="font-size:56px;color:#f59e0b;"></i>
          <div class="empty-state-title">Error al cargar</div>
          <div class="empty-state-text">${data.error || 'No se pudieron cargar los proyectos'}</div>
        </div>
      `;
      return;
    }

    if (!data.proyectos || data.proyectos.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="ph ph-folder-open empty-state-icon" style="font-size:56px;color:#ccc;"></i>
          <div class="empty-state-title">Sin proyectos</div>
          <div class="empty-state-text">No participas en ningún proyecto aún</div>
        </div>
      `;
      return;
    }

    // Renderizar proyectos con animación escalonada
    container.innerHTML = data.proyectos.map((proy, index) =>
      renderProyectoItem(proy, index)
    ).join('');

  } catch (err) {
    console.error('Error al cargar proyectos:', err);
    container.innerHTML = `
      <div class="empty-state">
        <i class="ph ph-wifi-slash empty-state-icon" style="font-size:56px;color:#ccc;"></i>
        <div class="empty-state-title">Error de conexión</div>
        <div class="empty-state-text">Verifica tu conexión a internet e intenta de nuevo</div>
      </div>
    `;
  }
};


/* =============================================
   RENDERIZAR PROYECTO - DISEÑO F-PATTERN
   ============================================= */
function renderProyectoItem(proy, index = 0) {
  // Status configuration - puntos de color (sin emojis redundantes)
  const estadoConfig = {
    'planificacion': { dot: 'planning', bg: '#faf5ff', color: '#7c3aed', text: 'Planificación' },
    'en_progreso': { dot: 'in-progress', bg: '#eff6ff', color: '#2563eb', text: 'En progreso' },
    'pausado': { dot: 'paused', bg: '#fff7ed', color: '#ea580c', text: 'Pausado' },
    'completado': { dot: 'completed', bg: '#ecfdf5', color: '#059669', text: 'Completado' },
    'cancelado': { dot: 'cancelled', bg: '#f3f4f6', color: '#6b7280', text: 'Cancelado' }
  };

  const estado = estadoConfig[proy.estado] || estadoConfig['planificacion'];
  const porcentaje = parseInt(proy.porcentaje_completado) || 0;
  const esLider = parseInt(proy.lider_id) === EMPLEADO_ID;

  // Determinar clase de la barra de progreso
  let progressClass = 'low';
  if (porcentaje >= 100) progressClass = 'complete';
  else if (porcentaje >= 75) progressClass = 'high';
  else if (porcentaje >= 50) progressClass = 'medium';

  // Todas las tareas
  const todasLasTareas = proy.todas_tareas || proy.mis_tareas || [];
  const misTareas = todasLasTareas.filter(t => t.es_mi_tarea).length;
  const tareasHTML = todasLasTareas.length > 0
    ? todasLasTareas.map(t => renderTareaEnProyecto(t)).join('')
    : `<tr><td colspan="4" class="empty-state" style="padding:40px;">
         <i class="ph ph-check-square" style="font-size:32px;opacity:0.3;margin-bottom:8px;display:block;"></i>
         <div style="color:var(--text-muted);font-size:var(--text-sm);">No hay tareas en este proyecto</div>
       </td></tr>`;

  // Escapar título para onclick
  const tituloEscapado = escapeHtml(proy.titulo).replace(/'/g, "\\'").replace(/"/g, '&quot;');

  // Delay de animación escalonada
  const animDelay = index * 0.05;

  return `
    <div class="proyecto-card ${esLider ? 'es-lider' : ''}"
         data-proyecto-id="${proy.id}"
         style="animation: fadeIn 0.4s ease ${animDelay}s both;">

      <!-- HEADER - Optimizado para F-Pattern -->
      <div class="proyecto-header" onclick="toggleProyectoExpandido(${proy.id})">

        <!-- LÍNEA 1: Información Principal (máxima atención) -->
        <div class="flex justify-between items-center gap-4" style="margin-bottom: var(--space-3);">
          <div class="flex items-center gap-3" style="flex:1; min-width:0;">
            <h4 class="proyecto-title" style="
              margin: 0;
              font-size: var(--text-lg);
              font-weight: 700;
              color: var(--text-primary);
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            ">${escapeHtml(proy.titulo)}</h4>

            ${esLider ? `
              <span style="
                font-size: 9px;
                padding: 3px 8px;
                background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
                color: white;
                border-radius: 20px;
                font-weight: 700;
                letter-spacing: 0.5px;
                white-space: nowrap;
              ">LÍDER</span>
            ` : ''}
          </div>

          <div class="flex items-center gap-3 proyecto-actions">
            <span class="status-badge" style="background:${estado.bg};color:${estado.color};">
              <span class="status-dot ${estado.dot}"></span>
              ${estado.text}
            </span>

            ${esLider ? `
              <button class="btn btn-primary btn-sm"
                      onclick="event.stopPropagation(); abrirModalEditarProyecto(${proy.id}, '${tituloEscapado}', '${proy.estado}', '${proy.fecha_fin_estimada || ''}')">
                Editar
              </button>
            ` : ''}

            <span id="toggle-icon-${proy.id}" class="toggle-icon">▼</span>
          </div>
        </div>

        <!-- LÍNEA 2: Metadata Secundaria (escaneo rápido) -->
        <div class="proyecto-meta flex items-center gap-4" style="font-size: var(--text-sm); color: var(--text-tertiary); flex-wrap: wrap;">
          <span class="flex items-center gap-2">
            <span class="avatar primary" style="width:22px;height:22px;font-size:9px;">
              ${getInitialsProyecto(proy.lider_nombre)}
            </span>
            ${escapeHtml(proy.lider_nombre || 'Sin asignar')}
          </span>

          <span style="color: var(--border-color);">•</span>

          ${proy.fecha_fin_estimada
            ? `<span>${formatearFechaProyecto(proy.fecha_fin_estimada)}</span>`
            : `<span style="color: var(--text-muted);">Sin fecha límite</span>`
          }

          <span style="color: var(--border-color);">•</span>

          <span>${proy.total_tareas || 0} tareas</span>

          ${misTareas > 0 ? `
            <span style="
              background: var(--color-accent);
              color: white;
              font-size: 10px;
              padding: 2px 8px;
              border-radius: 10px;
              font-weight: 700;
            ">${misTareas} mía${misTareas > 1 ? 's' : ''}</span>
          ` : ''}
        </div>

        <!-- LÍNEA 3: Barra de Progreso Prominente -->
        <div class="progress-container">
          <div class="progress-header">
            <span class="progress-label">Progreso</span>
            <span class="progress-value">${porcentaje}%</span>
          </div>
          <div class="progress-bar-track">
            <div class="progress-bar-fill ${progressClass}" style="width: ${porcentaje}%;"></div>
          </div>
        </div>
      </div>

      <!-- TABLA DE TAREAS (expandible) -->
      <div id="tareas-proyecto-${proy.id}" class="tareas-container" style="display:none;">
        <div class="tareas-header">
          <span style="font-size: var(--text-sm); font-weight: 600; color: var(--text-secondary);">
            Tareas del proyecto
          </span>
          ${esLider ? `
            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); openCreateModal('tarea')">
              + Nueva tarea
            </button>
          ` : ''}
        </div>

        <div style="overflow-x:auto;">
          <table class="tareas-table">
            <thead>
              <tr>
                <th>Tarea</th>
                <th class="col-responsable" style="width:140px;">Responsable</th>
                <th style="width:120px;text-align:center;">Estado</th>
                <th style="width:120px;text-align:center;">Fecha límite</th>
              </tr>
            </thead>
            <tbody>
              ${tareasHTML}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `;
}


/* =============================================
   RENDERIZAR TAREA - CON STATUS DOTS
   ============================================= */
function renderTareaEnProyecto(tarea) {
  // Estados con puntos de color
  const estadoConfig = {
    'pendiente': { dot: 'pending', bg: 'var(--color-warning-bg)', color: '#b45309', text: 'Pendiente' },
    'en_progreso': { dot: 'in-progress', bg: 'var(--color-info-bg)', color: '#1d4ed8', text: 'En progreso' },
    'completada': { dot: 'completed', bg: 'var(--color-success-bg)', color: '#047857', text: 'Completada' },
    'cancelada': { dot: 'cancelled', bg: 'var(--color-neutral-bg)', color: '#4b5563', text: 'Cancelada' }
  };

  // Deadline styles
  const deadlineConfig = {
    'vencida': { class: 'overdue', icon: '!' },
    'vence_hoy': { class: 'today', icon: '•' },
    'proxima': { class: 'soon', icon: '•' },
    'normal': { class: 'normal', icon: '' }
  };

  const estado = estadoConfig[tarea.estado] || estadoConfig['pendiente'];
  const deadline = deadlineConfig[tarea.indicador_deadline] || deadlineConfig['normal'];
  const esMiTarea = tarea.es_mi_tarea;
  const esCompletada = tarea.estado === 'completada';

  const deadlineText = tarea.deadline
    ? formatearFechaProyecto(tarea.deadline)
    : 'Sin fecha';

  return `
    <tr class="tarea-row ${esMiTarea ? 'mi-tarea' : ''}">
      <td>
        <div class="flex items-center gap-2">
          <span class="${esCompletada ? 'text-strikethrough' : ''}" style="
            font-weight: 500;
            color: var(--text-primary);
            font-size: var(--text-sm);
          ">${escapeHtml(tarea.titulo)}</span>
        </div>
      </td>

      <td class="col-responsable">
        <div class="flex items-center gap-2">
          <span class="avatar ${esMiTarea ? 'accent' : 'primary'}">
            ${getInitialsProyecto(tarea.responsable_nombre)}
          </span>
          <span style="font-size: var(--text-xs); color: var(--text-secondary);">
            ${esMiTarea ? 'Tú' : escapeHtml(tarea.responsable_nombre || 'Sin asignar')}
          </span>
        </div>
      </td>

      <td style="text-align:center;">
        <span class="status-badge" style="background:${estado.bg};color:${estado.color};">
          <span class="status-dot ${estado.dot}"></span>
          ${estado.text}
        </span>
      </td>

      <td style="text-align:center;">
        <span class="deadline-badge ${deadline.class}">
          ${deadline.icon ? `<strong>${deadline.icon}</strong>` : ''}
          ${deadlineText}
        </span>
      </td>
    </tr>
  `;
}


/* =============================================
   TOGGLE EXPANDIR/COLAPSAR - CON ANIMACIÓN
   ============================================= */
window.toggleProyectoExpandido = function(proyectoId) {
  const tareasContainer = document.getElementById('tareas-proyecto-' + proyectoId);
  const toggleIcon = document.getElementById('toggle-icon-' + proyectoId);
  if (!tareasContainer) return;

  const isHidden = tareasContainer.style.display === 'none';

  if (isHidden) {
    tareasContainer.style.display = 'block';
    toggleIcon?.classList.add('expanded');
  } else {
    tareasContainer.classList.add('closing');
    setTimeout(() => {
      tareasContainer.style.display = 'none';
      tareasContainer.classList.remove('closing');
    }, 150);
    toggleIcon?.classList.remove('expanded');
  }
};


/* =============================================
   UTILIDADES
   ============================================= */
function formatearFechaProyecto(fechaStr) {
  if (!fechaStr) return 'Sin fecha';
  const fecha = new Date(fechaStr);
  const hoy = new Date();
  const diff = Math.ceil((fecha - hoy) / (1000 * 60 * 60 * 24));

  // Fechas relativas para mejor UX
  if (diff === 0) return 'Hoy';
  if (diff === 1) return 'Mañana';
  if (diff === -1) return 'Ayer';
  if (diff > 0 && diff <= 7) return `En ${diff} días`;
  if (diff < 0 && diff >= -7) return `Hace ${Math.abs(diff)} días`;

  return fecha.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
}

function getInitialsProyecto(nombre) {
  if (!nombre) return '?';
  const parts = nombre.trim().split(' ').filter(p => p.length > 0);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return nombre.substring(0, 2).toUpperCase();
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}


/* =============================================
   TOAST NOTIFICATION
   ============================================= */
function showToast(message, type = 'success') {
  // Remover toast existente
  document.querySelectorAll('.toast').forEach(t => t.remove());

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <i class="ph-fill ph-${type === 'success' ? 'check-circle' : 'x-circle'}"></i>
    <span>${message}</span>
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}


/* =============================================
   MODAL DE EDICIÓN DE PROYECTO (SOLO LÍDERES)
   ============================================= */
window.abrirModalEditarProyecto = function(proyectoId, titulo, estado, fechaFin) {
  // Remover modal existente
  document.getElementById('modal-editar-proyecto')?.remove();

  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = 'modal-editar-proyecto';

  overlay.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <i class="ph ph-pencil-simple" style="font-size:26px;color:var(--c-teal);"></i>
        <h3>Editar proyecto</h3>
      </div>

      <div class="form-group">
        <label class="form-label">Nombre del proyecto</label>
        <input
          id="edit-proyecto-titulo"
          type="text"
          class="form-input"
          value="${titulo.replace(/"/g, '&quot;')}"
          placeholder="Nombre del proyecto"
        >
      </div>

      <div class="form-group">
        <label class="form-label">Estado</label>
        <select id="edit-proyecto-estado" class="form-input" style="cursor:pointer;">
          <option value="planificacion" ${estado === 'planificacion' ? 'selected' : ''}>Planificación</option>
          <option value="en_progreso" ${estado === 'en_progreso' ? 'selected' : ''}>En progreso</option>
          <option value="pausado" ${estado === 'pausado' ? 'selected' : ''}>Pausado</option>
          <option value="completado" ${estado === 'completado' ? 'selected' : ''}>Completado</option>
          <option value="cancelado" ${estado === 'cancelado' ? 'selected' : ''}>Cancelado</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Fecha límite</label>
        <input
          id="edit-proyecto-fecha-fin"
          type="date"
          class="form-input"
          value="${fechaFin || ''}"
        >
      </div>

      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="cerrarModalEditarProyecto()">
          Cancelar
        </button>
        <button class="btn btn-accent" onclick="guardarEdicionProyecto(${proyectoId}, this)">
          Guardar cambios
        </button>
      </div>
    </div>
  `;

  // Cerrar al hacer click fuera
  overlay.addEventListener('click', e => {
    if (e.target === overlay) cerrarModalEditarProyecto();
  });

  // Cerrar con ESC
  const escHandler = e => {
    if (e.key === 'Escape') {
      cerrarModalEditarProyecto();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);

  // Focus en el primer input
  setTimeout(() => {
    document.getElementById('edit-proyecto-titulo')?.focus();
  }, 100);
};

window.cerrarModalEditarProyecto = function() {
  const modal = document.getElementById('modal-editar-proyecto');
  if (modal) {
    modal.style.opacity = '0';
    modal.querySelector('.modal-content').style.transform = 'scale(0.95)';
    modal.style.transition = 'opacity 0.2s ease';
    setTimeout(() => modal.remove(), 200);
  }
};

window.guardarEdicionProyecto = async function(proyectoId, btn) {
  const titulo = document.getElementById('edit-proyecto-titulo').value.trim();
  const estado = document.getElementById('edit-proyecto-estado').value;
  const fechaFin = document.getElementById('edit-proyecto-fecha-fin').value;

  if (!titulo) {
    showToast('El nombre del proyecto es obligatorio', 'error');
    document.getElementById('edit-proyecto-titulo').focus();
    return;
  }

  btn.disabled = true;
  const textoOriginal = btn.innerHTML;
  btn.innerHTML = 'Guardando...';
  btn.style.opacity = '0.7';

  try {
    const fd = new FormData();
    fd.append('action', 'actualizar_proyecto');
    fd.append('usuario_id', USER_ID);
    fd.append('proyecto_id', proyectoId);
    fd.append('titulo', titulo);
    fd.append('estado', estado);
    fd.append('fecha_fin_estimada', fechaFin || '');

    const response = await fetch('proyectos_tareas_backend.php', {
      method: 'POST',
      body: fd
    });
    const data = await response.json();

    if (!data.success && !data.ok) {
      showToast(data.message || data.error || 'Error al actualizar', 'error');
      btn.disabled = false;
      btn.innerHTML = textoOriginal;
      btn.style.opacity = '1';
      return;
    }

    cerrarModalEditarProyecto();
    showToast('Proyecto actualizado correctamente', 'success');

    // Recargar lista de proyectos
    cargarMisProyectos();

  } catch (err) {
    console.error('Error:', err);
    showToast('Error de conexión', 'error');
    btn.disabled = false;
    btn.innerHTML = textoOriginal;
    btn.style.opacity = '1';
  }
};




  /* =======================================================
     MODAL UNIFICADO: CREAR TAREA O PROYECTO
     Paso 1: elegir tipo (tarea / proyecto)
     Paso 2a: campos de tarea  (+ área de trabajo)
     Paso 2b: campos de proyecto → opción de crear 1ra tarea
     ======================================================= */

  window.openCreateModal = function(preselect) {
    if (document.getElementById('create-modal')) return;

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'create-modal';

    overlay.innerHTML = `
      <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
          <div class="modal-icon"><i class="ph ph-plus-circle" style="font-size:28px;color:var(--c-accent);"></i></div>
          <h3 class="modal-title" id="create-modal-title">Crear nuevo</h3>
        </div>

        <!-- PASO 1: Elegir tipo -->
        <div id="create-step-choose" class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
          <p style="margin:0 0 4px;font-size:13px;color:#666;">Elige lo que quieres crear:</p>

          <button type="button" class="help-option" id="choose-tarea" style="cursor:pointer;text-align:left;">
            <div style="display:flex;align-items:center;gap:12px;width:100%;">
              <i class="ph ph-check-square" style="font-size:24px;color:var(--c-teal);flex-shrink:0;"></i>
              <div style="flex:1;">
                <div class="help-option-label">Tarea</div>
                <div class="help-option-sublabel">Una actividad asignada dentro de un proyecto</div>
              </div>
              <i class="ph ph-caret-right" style="color:#ccc;font-size:18px;"></i>
            </div>
          </button>

          <button type="button" class="help-option" id="choose-proyecto" style="cursor:pointer;text-align:left;">
            <div style="display:flex;align-items:center;gap:12px;width:100%;">
              <i class="ph ph-folder-open" style="font-size:24px;color:var(--c-accent);flex-shrink:0;"></i>
              <div style="flex:1;">
                <div class="help-option-label">Proyecto</div>
                <div class="help-option-sublabel">Un contenedor de tareas con fechas y equipo</div>
              </div>
              <i class="ph ph-caret-right" style="color:#ccc;font-size:18px;"></i>
            </div>
          </button>
        </div>

        <!-- PASO 2A: Formulario de TAREA -->
        <div id="create-step-tarea" style="display:none;">
          <div class="modal-body">
            <label class="help-option">
              <div class="help-option-label">Proyecto<div class="help-option-sublabel">Selecciona el proyecto al que pertenece</div></div>
              <select id="tarea-proyecto-select" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
                <option value="">Cargando...</option>
              </select>
            </label>

            <label class="help-option">
              <div class="help-option-label">Área de trabajo<div class="help-option-sublabel">El área a la que pertenece esta tarea</div></div>
              <select id="tarea-area-select" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
                <option value="">Cargando áreas...</option>
              </select>
            </label>

            <label class="help-option">
              <div class="help-option-label">Título de la tarea</div>
              <input id="tarea-titulo" type="text" placeholder="Ej: Revisar documentación Q1" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
            </label>

            <label class="help-option">
              <div class="help-option-label">Descripción <span style="font-weight:400;color:#999;">(opcional)</span></div>
              <textarea id="tarea-descripcion" placeholder="Detalles adicionales..." style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;resize:vertical;min-height:50px;"></textarea>
            </label>

            <label class="help-option">
              <div class="help-option-label">Responsable <span style="font-weight:400;color:#999;">(opcional)</span></div>
              <select id="tarea-responsable" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
                <option value="">Sin asignar</option>
              </select>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <label class="help-option">
                <div class="help-option-label">Fecha límite</div>
                <input id="tarea-deadline" type="date" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
              </label>
              <label class="help-option">
                <div class="help-option-label">Prioridad</div>
                <select id="tarea-prioridad" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
                  <option value="media">Media</option>
                  <option value="baja">Baja</option>
                  <option value="alta">Alta</option>
                  <option value="critica">Critica</option>
                </select>
              </label>
            </div>
          </div>

          <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="showCreateStep('choose')">Volver</button>
            <button class="modal-btn modal-btn-confirm" id="btn-guardar-tarea" onclick="guardarTarea(this)">Crear tarea</button>
          </div>
        </div>

        <!-- PASO 2B: Formulario de PROYECTO -->
        <div id="create-step-proyecto" style="display:none;">
          <div class="modal-body">
            <label class="help-option">
              <div class="help-option-label">Título del proyecto</div>
              <input id="proyecto-titulo" type="text" placeholder="Ej: Lanzamiento campaña Q1" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
            </label>

            <label class="help-option">
              <div class="help-option-label">Descripción <span style="font-weight:400;color:#999;">(opcional)</span></div>
              <textarea id="proyecto-descripcion" placeholder="Objetivos del proyecto..." style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;resize:vertical;min-height:50px;"></textarea>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <label class="help-option">
                <div class="help-option-label">Fecha inicio</div>
                <input id="proyecto-fecha-inicio" type="date" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
              </label>
              <label class="help-option">
                <div class="help-option-label">Fecha fin estimada</div>
                <input id="proyecto-fecha-fin" type="date" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
              </label>
            </div>

            <label class="help-option">
              <div class="help-option-label">Prioridad</div>
              <select id="proyecto-prioridad" style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;">
                <option value="media">Media</option>
                <option value="baja">Baja</option>
                <option value="alta">Alta</option>
                <option value="critica">Critica</option>
              </select>
            </label>

            <p style="margin:4px 0 0;font-size:11px;color:#888;"><i class="ph ph-info" style="margin-right:4px;"></i>Serás el líder de este proyecto</p>
          </div>

          <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="showCreateStep('choose')">Volver</button>
            <button class="modal-btn modal-btn-confirm" id="btn-guardar-proyecto" onclick="guardarProyecto(this)">Crear proyecto</button>
          </div>
        </div>

        <!-- PASO 3: Proyecto creado — crear primera tarea? -->
        <div id="create-step-post-proyecto" style="display:none;">
          <div class="modal-body" style="text-align:center;padding:20px 0;">
            <i class="ph ph-check-circle" style="font-size:48px;color:#2E7D32;margin-bottom:12px;display:block;"></i>
            <p style="font-size:16px;font-weight:700;color:#012133;margin:0 0 6px;">Proyecto creado</p>
            <p style="font-size:13px;color:#666;margin:0 0 20px;" id="post-proyecto-msg"></p>

            <div style="display:flex;flex-direction:column;gap:10px;">
              <button class="modal-btn modal-btn-confirm" style="width:100%;" onclick="postProyectoCrearTarea()">
                <i class="ph ph-plus" style="margin-right:6px;"></i>Crear la primera tarea del proyecto
              </button>
              <button class="modal-btn modal-btn-cancel" style="width:100%;" onclick="closeCreateModal()">Cerrar</button>
            </div>
          </div>
        </div>
      </div>
    `;

    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeCreateModal();
    });

    document.body.appendChild(overlay);

    // Listeners de paso 1
    document.getElementById('choose-tarea').addEventListener('click', () => showCreateStep('tarea'));
    document.getElementById('choose-proyecto').addEventListener('click', () => showCreateStep('proyecto'));

    // Si viene preseleccionado
    if (preselect === 'tarea' || preselect === 'proyecto') {
      showCreateStep(preselect);
    }
  };

  // Alias de compatibilidad
  window.openCreateTareaModal = function() { openCreateModal('tarea'); };

  let _lastCreatedProyectoId = null;

  window.closeCreateModal = function() {
    const modal = document.getElementById('create-modal');
    if (!modal) return;
    modal.style.opacity = '0';
    setTimeout(() => modal.remove(), 200);
    _lastCreatedProyectoId = null;
  };

  // Alias de compatibilidad
  window.closeCreateTareaModal = closeCreateModal;

  window.showCreateStep = async function(step) {
    const steps = ['choose', 'tarea', 'proyecto', 'post-proyecto'];
    steps.forEach(s => {
      const el = document.getElementById('create-step-' + s);
      if (el) el.style.display = 'none';
    });
    const target = document.getElementById('create-step-' + step);
    if (target) target.style.display = 'block';

    const title = document.getElementById('create-modal-title');

    if (step === 'tarea') {
      title.textContent = 'Nueva tarea';
      await cargarProyectosParaSelector();
      await cargarAreasParaSelector();
      await cargarMiembrosEquipo(document.getElementById('tarea-responsable'), true);
    } else if (step === 'proyecto') {
      title.textContent = 'Nuevo proyecto';
    } else if (step === 'post-proyecto') {
      title.textContent = 'Proyecto creado';
    } else {
      title.textContent = 'Crear nuevo';
    }
  };

  /* =======================================================
     CARGAR PROYECTOS PARA SELECTOR
     ======================================================= */
  async function cargarProyectosParaSelector() {
    const select = document.getElementById('tarea-proyecto-select');
    if (!select) return;

    try {
      const params = new URLSearchParams({
        action: 'obtener_proyectos_activos',
        usuario_id: USER_ID
      });

      const response = await fetch('proyectos_tareas_backend.php?' + params.toString());
      const data = await response.json();

      select.innerHTML = '<option value="">-- Selecciona un proyecto --</option>';

      if (data.ok && data.proyectos) {
        data.proyectos.forEach(p => {
          const esLider = parseInt(p.lider_id) === EMPLEADO_ID;
          if (!esLider) return;

          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = escapeHtml(p.titulo);
          select.appendChild(opt);
        });
      }

      // Preseleccionar si viene de "crear primera tarea"
      if (_lastCreatedProyectoId) {
        select.value = _lastCreatedProyectoId;
      }
    } catch (err) {
      console.error('Error cargando proyectos:', err);
      select.innerHTML = '<option value="">Error al cargar</option>';
    }
  }

  /* =======================================================
     CARGAR ÁREAS DE TRABAJO PARA SELECTOR
     ======================================================= */
  async function cargarAreasParaSelector() {
    const select = document.getElementById('tarea-area-select');
    if (!select) return;

    try {
      const params = new URLSearchParams({
        action: 'obtener_areas_trabajo',
        usuario_id: USER_ID
      });

      const response = await fetch('proyectos_tareas_backend.php?' + params.toString());
      const data = await response.json();

      select.innerHTML = '<option value="">-- Selecciona un área --</option>';

      if (data.ok && data.areas) {
        data.areas.forEach(a => {
          const opt = document.createElement('option');
          opt.value = a.id;
          opt.textContent = escapeHtml(a.nombre_area);
          select.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Error cargando áreas:', err);
      select.innerHTML = '<option value="">Error al cargar</option>';
    }
  }

  /* =======================================================
     CARGAR MIEMBROS DEL EQUIPO
     ======================================================= */
  async function cargarMiembrosEquipo(selectElement, incluirTodos = false) {
    if (!selectElement) return;
    selectElement.innerHTML = '<option value="">Cargando...</option>';
    selectElement.disabled = true;

    try {
      const params = new URLSearchParams({
        action: 'obtener_miembros_equipo',
        usuario_id: USER_ID
      });

      const response = await fetch('proyectos_tareas_backend.php?' + params.toString());
      const data = await response.json();

      selectElement.innerHTML = '';

      if (incluirTodos) {
        const noAsignar = document.createElement('option');
        noAsignar.value = '';
        noAsignar.textContent = 'Sin asignar (asignar después)';
        selectElement.appendChild(noAsignar);

        const selfOpt = document.createElement('option');
        selfOpt.value = EMPLEADO_ID;
        selfOpt.textContent = 'Yo mismo';
        selectElement.appendChild(selfOpt);

        if (data.ok && data.miembros) {
          data.miembros.forEach(m => {
            if (parseInt(m.id) !== EMPLEADO_ID) {
              const opt = document.createElement('option');
              opt.value = m.id;
              opt.textContent = m.nombre_persona;
              selectElement.appendChild(opt);
            }
          });
        }
      } else {
        selectElement.innerHTML = `<option value="${EMPLEADO_ID}" selected>Yo mismo</option>`;
      }

      selectElement.disabled = false;
    } catch (err) {
      console.error('Error cargando miembros:', err);
      selectElement.innerHTML = `<option value="${EMPLEADO_ID}" selected>Yo mismo</option>`;
      selectElement.disabled = false;
    }
  }

  /* =======================================================
     GUARDAR TAREA
     ======================================================= */
  window.guardarTarea = async function(btn) {
    const titulo = document.getElementById('tarea-titulo').value.trim();
    const descripcion = document.getElementById('tarea-descripcion').value.trim();
    const deadline = document.getElementById('tarea-deadline').value;
    const prioridad = document.getElementById('tarea-prioridad').value;
    const responsableId = document.getElementById('tarea-responsable').value;
    const proyectoId = document.getElementById('tarea-proyecto-select').value;
    const areaId = document.getElementById('tarea-area-select').value;

    if (!titulo) { alert('El título de la tarea es obligatorio'); return; }
    if (!proyectoId) { alert('Debes seleccionar un proyecto'); return; }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
      const fd = new FormData();
      fd.append('action', 'crear_tarea');
      fd.append('usuario_id', USER_ID);
      fd.append('proyecto_id', proyectoId);
      fd.append('titulo', titulo);
      fd.append('descripcion', descripcion);
      fd.append('responsable_id', responsableId || EMPLEADO_ID);
      fd.append('deadline', deadline || '');
      fd.append('prioridad', prioridad);
      if (areaId) fd.append('area_id', areaId);

      const response = await fetch('proyectos_tareas_backend.php', { method: 'POST', body: fd });
      const data = await response.json();

      if (!data.ok) {
        alert('Error: ' + (data.error || 'Error desconocido'));
        btn.disabled = false;
        btn.textContent = 'Crear tarea';
        return;
      }

      closeCreateModal();

      // Recargar tab activo
      if (typeof cargarMisTareas === 'function') cargarMisTareas();
      if (typeof cargarMisProyectos === 'function') cargarMisProyectos();

      const tabTareas = document.querySelector('[data-tab="tareas"]');
      if (tabTareas) tabTareas.click();

    } catch (err) {
      console.error('Error:', err);
      alert('Error de conexión');
      btn.disabled = false;
      btn.textContent = 'Crear tarea';
    }
  };

  /* =======================================================
     GUARDAR PROYECTO
     ======================================================= */
  window.guardarProyecto = async function(btn) {
    const titulo = document.getElementById('proyecto-titulo').value.trim();
    const descripcion = document.getElementById('proyecto-descripcion').value.trim();
    const fechaInicio = document.getElementById('proyecto-fecha-inicio').value;
    const fechaFin = document.getElementById('proyecto-fecha-fin').value;
    const prioridad = document.getElementById('proyecto-prioridad').value;

    if (!titulo) { alert('El título del proyecto es obligatorio'); return; }

    btn.disabled = true;
    btn.textContent = 'Creando...';

    try {
      const fd = new FormData();
      fd.append('action', 'crear_proyecto');
      fd.append('usuario_id', USER_ID);
      fd.append('titulo', titulo);
      fd.append('descripcion', descripcion);
      fd.append('lider_id', EMPLEADO_ID);
      fd.append('fecha_inicio', fechaInicio || '');
      fd.append('fecha_fin_estimada', fechaFin || '');
      fd.append('prioridad', prioridad);

      const response = await fetch('proyectos_tareas_backend.php', { method: 'POST', body: fd });
      const data = await response.json();

      if (!data.ok) {
        alert('Error: ' + (data.error || 'Error desconocido'));
        btn.disabled = false;
        btn.textContent = 'Crear proyecto';
        return;
      }

      _lastCreatedProyectoId = data.proyecto_id;

      // Mostrar paso 3: crear primera tarea?
      document.getElementById('post-proyecto-msg').textContent = '"' + escapeHtml(titulo) + '" ha sido creado.';
      showCreateStep('post-proyecto');

      if (typeof cargarMisProyectos === 'function') cargarMisProyectos();

    } catch (err) {
      console.error('Error:', err);
      alert('Error de conexión');
      btn.disabled = false;
      btn.textContent = 'Crear proyecto';
    }
  };

  /* =======================================================
     POST-PROYECTO: Crear primera tarea
     ======================================================= */
  window.postProyectoCrearTarea = function() {
    showCreateStep('tarea');
  };

  /* =======================================================
     EVENT LISTENERS PARA FILTROS
     ======================================================= */
  document.getElementById('filtro-tarea-estado')?.addEventListener('change', cargarMisTareas);
  document.getElementById('filtro-tarea-prioridad')?.addEventListener('change', cargarMisTareas);
  document.getElementById('filtro-proyecto-estado')?.addEventListener('change', cargarMisProyectos);

  /* =======================================================
     UTILIDAD: Escape HTML
     ======================================================= */
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }



 /* =========================================================
     MODAL DE EDICIÓN DE PROYECTO (SOLO LÍDERES)
     ========================================================= */

  window.abrirModalEditarProyecto = function(proyectoId, titulo, estado, fechaFin) {
    if (document.getElementById('modal-editar-proyecto')) return;

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'modal-editar-proyecto';

    overlay.innerHTML = `
      <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
          <div class="modal-icon"><i class="ph ph-pencil-simple" style="font-size:26px;color:var(--c-teal);"></i></div>
          <h3 class="modal-title">Editar proyecto</h3>
        </div>

        <div class="modal-body">
          <!-- Nombre del proyecto -->
          <label class="help-option">
            <div class="help-option-label">
              Nombre del proyecto
            </div>
            <input 
              id="edit-proyecto-titulo" 
              type="text" 
              value="${titulo}"
              style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;"
            >
          </label>

          <!-- Estado -->
          <label class="help-option" style="margin-top:16px;">
            <div class="help-option-label">
              Estado del proyecto
            </div>
            <select 
              id="edit-proyecto-estado" 
              style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;"
            >
              <option value="planificacion" ${estado === 'planificacion' ? 'selected' : ''}>Planificación</option>
              <option value="en_progreso" ${estado === 'en_progreso' ? 'selected' : ''}>En progreso</option>
              <option value="pausado" ${estado === 'pausado' ? 'selected' : ''}>Pausado</option>
              <option value="completado" ${estado === 'completado' ? 'selected' : ''}>Completado</option>
              <option value="cancelado" ${estado === 'cancelado' ? 'selected' : ''}>Cancelado</option>
            </select>
          </label>

          <!-- Fecha fin estimada -->
          <label class="help-option" style="margin-top:16px;">
            <div class="help-option-label">
              Fecha de vencimiento (deadline)
            </div>
            <input 
              id="edit-proyecto-fecha-fin" 
              type="date" 
              value="${fechaFin || ''}"
              style="width:100%;margin-top:8px;padding:12px;border-radius:8px;border:2px solid #e6e6e6;"
            >
          </label>
        </div>

        <div class="modal-actions">
          <button class="modal-btn modal-btn-cancel" onclick="cerrarModalEditarProyecto()">
            Cancelar
          </button>
          <button class="modal-btn modal-btn-confirm" onclick="guardarEdicionProyecto(${proyectoId}, this)">
            Guardar cambios
          </button>
        </div>
      </div>
    `;

    overlay.addEventListener('click', e => {
      if (e.target === overlay) cerrarModalEditarProyecto();
    });

    document.body.appendChild(overlay);
  };

  window.cerrarModalEditarProyecto = function() {
    const modal = document.getElementById('modal-editar-proyecto');
    if (!modal) return;
    modal.style.opacity = '0';
    setTimeout(() => modal.remove(), 200);
  };

  window.guardarEdicionProyecto = async function(proyectoId, btn) {
    const titulo = document.getElementById('edit-proyecto-titulo').value.trim();
    const estado = document.getElementById('edit-proyecto-estado').value;
    const fechaFin = document.getElementById('edit-proyecto-fecha-fin').value;

    if (!titulo) {
      alert('El nombre del proyecto es obligatorio');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
      const fd = new FormData();
      fd.append('action', 'actualizar_proyecto');
      fd.append('usuario_id', USER_ID);
      fd.append('proyecto_id', proyectoId);
      fd.append('titulo', titulo);
      fd.append('descripcion', ''); // Mantener descripción existente
      fd.append('lider_id', EMPLEADO_ID); // Mantener líder actual
      fd.append('estado', estado);
      fd.append('fecha_fin_estimada', fechaFin || '');
      fd.append('prioridad', 'media'); // Mantener prioridad existente

      const response = await fetch('proyectos_tareas_backend.php', {
        method: 'POST',
        body: fd
      });
      const data = await response.json();

      if (!data.success) {
        alert('Error: ' + (data.message || 'No se pudo actualizar'));
        btn.disabled = false;
        btn.textContent = 'Guardar cambios';
        return;
      }

      cerrarModalEditarProyecto();
      alert('Proyecto actualizado correctamente');
      cargarMisProyectos(); // Recargar lista

    } catch (err) {
      console.error('Error:', err);
      alert('Error de conexión');
      btn.disabled = false;
      btn.textContent = 'Guardar cambios';
    }
  };
})();
</script>

<?php
// ========================================================================
// MÓDULO DE PERMISOS Y VACACIONES
// Permite a los empleados solicitar permisos y vacaciones
// Ver notificaciones y recordatorios de solicitudes aprobadas
// ========================================================================
include 'permisos_vacaciones_empleado_ui.php';
?>

<script src="UPGRADE_METAS_UXUI.js"></script>


<!-- Registrar Service Worker para PWA -->
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/sw.js')
      .then(function(registration) {
        console.log('Service Worker registrado con éxito:', registration.scope);
      })
      .catch(function(error) {
        console.log('Error al registrar Service Worker:', error);
      });
  });
}
</script>
</body>

</html>


 
 

 
 