<?php
session_start();
require 'config.php';

// Configurar zona horaria de España
date_default_timezone_set('Europe/Madrid');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$user_id    = (int)$_SESSION['user_id'];
$usuario_id = $user_id;

/* ============ DESCARGAR HISTÓRICO DE ASISTENCIAS (Normativa Española) ============ */

if (isset($_GET['action']) && $_GET['action'] === 'descargar_historico_asistencias') {
    // Obtener parámetros de filtro (opcional)
    $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual
    $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-t');  // Último día del mes actual

    // PASO 1: Crear registros de ausencia automáticos para días sin registro
    // Esto asegura que el CSV incluya TODOS los días laborables según normativa española
    $fecha_actual = new DateTime($fecha_desde);
    $fecha_fin = new DateTime($fecha_hasta);

    while ($fecha_actual <= $fecha_fin) {
        $fecha_str = $fecha_actual->format('Y-m-d');
        $dia_semana = (int)$fecha_actual->format('N'); // 1=Lunes, 7=Domingo

        // Obtener empleados que DEBERÍAN trabajar este día pero NO tienen registro
        $stmt_ausentes = $conn->prepare("
            SELECT DISTINCT
                e.id as persona_id,
                ej.jornada_id,
                ? as fecha
            FROM equipo e
            INNER JOIN equipo_jornadas ej ON e.id = ej.persona_id
                AND ej.fecha_inicio <= ?
                AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= ?)
            INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
            INNER JOIN turnos t ON j.id = t.jornada_id AND t.dia_semana = ?
            LEFT JOIN asistencias a ON e.id = a.persona_id AND a.fecha = ?
            WHERE e.usuario_id = ?
              AND a.id IS NULL
        ");

        $stmt_ausentes->bind_param("sssisi", $fecha_str, $fecha_str, $fecha_str, $dia_semana, $fecha_str, $user_id);
        $stmt_ausentes->execute();
        $result_ausentes = stmt_get_result($stmt_ausentes);

        // Crear registro de ausencia para cada empleado que debería haber trabajado
        if ($result_ausentes->num_rows > 0) {
            $stmt_insert_ausente = $conn->prepare("
                INSERT INTO asistencias (persona_id, jornada_id, fecha, estado, minutos_tarde_entrada, minutos_tarde_salida)
                VALUES (?, ?, ?, 'ausente', 0, 0)
            ");

            while ($ausente = $result_ausentes->fetch_assoc()) {
                $stmt_insert_ausente->bind_param("iis",
                    $ausente['persona_id'],
                    $ausente['jornada_id'],
                    $ausente['fecha']
                );
                $stmt_insert_ausente->execute();
            }

            $stmt_insert_ausente->close();
        }

        $stmt_ausentes->close();
        $fecha_actual->modify('+1 day');
    }

    // PASO 2: Query para obtener registros según normativa española RD-ley 8/2019
    $stmt = $conn->prepare("
        SELECT
            e.nombre_persona AS 'Nombre del Trabajador',
            e.cargo AS 'Puesto de Trabajo',
            a.fecha AS 'Fecha',
            DAYNAME(a.fecha) AS 'Día de la Semana',
            CASE
                WHEN a.estado = 'ausente' THEN 'Sin registro'
                WHEN a.hora_entrada IS NOT NULL THEN DATE_FORMAT(a.hora_entrada, '%H:%i')
                ELSE '--:--'
            END AS 'Hora de Entrada',
            CASE
                WHEN a.estado = 'ausente' THEN 'Sin registro'
                WHEN a.hora_salida IS NOT NULL THEN DATE_FORMAT(a.hora_salida, '%H:%i')
                ELSE '--:--'
            END AS 'Hora de Salida',
            CASE
                WHEN a.estado = 'ausente' THEN '0h 0m'
                WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL THEN
                    CONCAT(
                        FLOOR(TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida) / 60), 'h ',
                        MOD(TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida), 60), 'm'
                    )
                ELSE 'Pendiente'
            END AS 'Total Horas Trabajadas',
            a.estado AS 'Estado',
            CASE
                WHEN a.estado = 'ausente' THEN 'No presentado'
                WHEN a.estado = 'presente' AND a.minutos_tarde_entrada > 0 THEN CONCAT(a.minutos_tarde_entrada, ' min de retraso')
                WHEN a.estado = 'tarde' AND a.minutos_tarde_entrada > 0 THEN CONCAT(a.minutos_tarde_entrada, ' min de retraso')
                ELSE 'A tiempo'
            END AS 'Puntualidad Entrada',
            CASE
                WHEN a.estado = 'ausente' THEN 'No presentado'
                WHEN a.hora_salida IS NULL THEN 'Pendiente'
                WHEN a.minutos_tarde_salida > 0 THEN CONCAT('Salió ', a.minutos_tarde_salida, ' min antes')
                WHEN a.minutos_tarde_salida < 0 THEN CONCAT('Tiempo extra: ', ABS(a.minutos_tarde_salida), ' min')
                ELSE 'A tiempo'
            END AS 'Puntualidad Salida',
            j.nombre AS 'Jornada Asignada',
            COALESCE(a.notas, '') AS 'Observaciones'
        FROM asistencias a
        INNER JOIN equipo e ON a.persona_id = e.id
        INNER JOIN jornadas_trabajo j ON a.jornada_id = j.id
        WHERE e.usuario_id = ?
          AND a.fecha BETWEEN ? AND ?
        ORDER BY e.nombre_persona ASC, a.fecha DESC
    ");

    $stmt->bind_param("iss", $user_id, $fecha_desde, $fecha_hasta);
    $stmt->execute();
    $result = stmt_get_result($stmt);

    // Generar CSV según normativa española
    $filename = 'Registro_Jornada_Laboral_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Agregar BOM para UTF-8 (para que Excel abra correctamente con tildes)
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Encabezado del documento (info de la empresa)
    fputcsv($output, ['REGISTRO DE JORNADA LABORAL'], ';');
    fputcsv($output, ['Conforme al Real Decreto-ley 8/2019, de 8 de marzo'], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta))], ';');
    fputcsv($output, ['Fecha de emisión: ' . date('d/m/Y H:i')], ';');
    fputcsv($output, [], ';'); // Línea vacía

    // Encabezados de columnas
    if ($result->num_rows > 0) {
        $first_row = $result->fetch_assoc();
        fputcsv($output, array_keys($first_row), ';');

        // Primera fila de datos
        fputcsv($output, array_values($first_row), ';');

        // Resto de filas
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, array_values($row), ';');
        }
    } else {
        fputcsv($output, ['Nombre del Trabajador', 'Puesto de Trabajo', 'Fecha', 'Día de la Semana', 'Hora de Entrada', 'Hora de Salida', 'Total Horas Trabajadas', 'Estado', 'Puntualidad', 'Jornada Asignada', 'Observaciones'], ';');
        fputcsv($output, ['No hay registros para el período seleccionado'], ';');
    }

    fclose($output);
    $stmt->close();
    exit;
}

/* ============ CRUD: JORNADAS DE TRABAJO ============ */

// ====== CREAR JORNADA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_jornada') {
    header('Content-Type: application/json');

    try {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $codigo_corto = trim($_POST['codigo_corto'] ?? '');
        $tipo_jornada = $_POST['tipo_jornada'] ?? 'fija';
        $horas_semanales = floatval($_POST['horas_semanales_esperadas'] ?? 40.00);
        $tolerancia_entrada = intval($_POST['tolerancia_entrada_min'] ?? 15);
        $tolerancia_salida = intval($_POST['tolerancia_salida_min'] ?? 5);
        $color_hex = trim($_POST['color_hex'] ?? '#184656');
        $turnos = json_decode($_POST['turnos'] ?? '[]', true);

        // Validaciones
        if (empty($nombre)) {
            throw new Exception('El nombre de la jornada es obligatorio');
        }

        if (!in_array($tipo_jornada, ['fija', 'rotativa', 'flexible'])) {
            throw new Exception('Tipo de jornada inválido');
        }

        if (empty($turnos) || !is_array($turnos)) {
            throw new Exception('Debe configurar al menos un turno');
        }

        // Insertar jornada
        $stmt = $conn->prepare("
            INSERT INTO jornadas_trabajo (
                usuario_id, nombre, descripcion, codigo_corto,
                tipo_jornada, horas_semanales_esperadas,
                tolerancia_entrada_min, tolerancia_salida_min, color_hex
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "issssdiis",
            $user_id, $nombre, $descripcion, $codigo_corto,
            $tipo_jornada, $horas_semanales,
            $tolerancia_entrada, $tolerancia_salida, $color_hex
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar la jornada: ' . $stmt->error);
        }

        $jornada_id = $stmt->insert_id;
        $stmt->close();

        // Insertar turnos
        $stmt_turno = $conn->prepare("
            INSERT INTO turnos (
                jornada_id, nombre_turno, dia_semana,
                hora_inicio, hora_fin, cruza_medianoche, orden
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($turnos as $index => $turno) {
            $nombre_turno = $turno['nombre_turno'] ?? '';
            $dias = $turno['dias'] ?? []; // Array de días: [1,2,3,4,5]

            // Asegurar formato HH:mm:ss (agregar :00 si solo viene HH:mm)
            $hora_inicio = $turno['hora_inicio'] ?? '09:00:00';
            if (strlen($hora_inicio) == 5) $hora_inicio .= ':00'; // 23:00 -> 23:00:00

            $hora_fin = $turno['hora_fin'] ?? '18:00:00';
            if (strlen($hora_fin) == 5) $hora_fin .= ':00'; // 23:00 -> 23:00:00

            // DEBUG: Log para verificar valores
            error_log("DEBUG TURNO - hora_inicio RAW: " . ($turno['hora_inicio'] ?? 'NULL') . " | PROCESADO: $hora_inicio");
            error_log("DEBUG TURNO - hora_fin RAW: " . ($turno['hora_fin'] ?? 'NULL') . " | PROCESADO: $hora_fin");

            $cruza_medianoche = intval($turno['cruza_medianoche'] ?? 0);
            $orden = $index + 1;

            // Insertar un registro por cada día seleccionado
            foreach ($dias as $dia) {
                $dia = intval($dia);
                if ($dia < 1 || $dia > 7) continue;

                $stmt_turno->bind_param(
                    "isissii",
                    $jornada_id, $nombre_turno, $dia,
                    $hora_inicio, $hora_fin, $cruza_medianoche, $orden
                );

                $stmt_turno->execute();
            }
        }

        $stmt_turno->close();

        echo json_encode([
            'success' => true,
            'message' => 'Jornada creada exitosamente',
            'jornada_id' => $jornada_id
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ====== EDITAR JORNADA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_jornada') {
    header('Content-Type: application/json');

    try {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $codigo_corto = trim($_POST['codigo_corto'] ?? '');
        $tipo_jornada = $_POST['tipo_jornada'] ?? 'fija';
        $horas_semanales = floatval($_POST['horas_semanales_esperadas'] ?? 40.00);
        $tolerancia_entrada = intval($_POST['tolerancia_entrada_min'] ?? 15);
        $tolerancia_salida = intval($_POST['tolerancia_salida_min'] ?? 5);
        $color_hex = trim($_POST['color_hex'] ?? '#184656');

        if (!$jornada_id || empty($nombre)) {
            throw new Exception('Datos incompletos');
        }

        // Verificar pertenencia
        $stmt_check = $conn->prepare("SELECT id FROM jornadas_trabajo WHERE id = ? AND usuario_id = ?");
        $stmt_check->bind_param("ii", $jornada_id, $user_id);
        $stmt_check->execute();
        if (stmt_get_result($stmt_check)->num_rows === 0) {
            throw new Exception('Jornada no encontrada');
        }
        $stmt_check->close();

        // Actualizar jornada
        $stmt = $conn->prepare("
            UPDATE jornadas_trabajo SET
                nombre = ?,
                descripcion = ?,
                codigo_corto = ?,
                tipo_jornada = ?,
                horas_semanales_esperadas = ?,
                tolerancia_entrada_min = ?,
                tolerancia_salida_min = ?,
                color_hex = ?
            WHERE id = ? AND usuario_id = ?
        ");

        $stmt->bind_param(
            "ssssdiiiii",
            $nombre, $descripcion, $codigo_corto, $tipo_jornada,
            $horas_semanales, $tolerancia_entrada, $tolerancia_salida,
            $color_hex, $jornada_id, $user_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar: ' . $stmt->error);
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Jornada actualizada exitosamente'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ====== ELIMINAR JORNADA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_jornada') {
    header('Content-Type: application/json');

    try {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);

        if (!$jornada_id) {
            throw new Exception('ID de jornada inválido');
        }

        // Verificar que no tenga empleados asignados actualmente
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as count FROM equipo_jornadas
            WHERE jornada_id = ?
              AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
        ");
        $stmt_check->bind_param("i", $jornada_id);
        $stmt_check->execute();
        $result = stmt_get_result($stmt_check)->fetch_assoc();
        $stmt_check->close();

        if ($result['count'] > 0) {
            throw new Exception('No se puede eliminar: hay ' . $result['count'] . ' empleado(s) asignado(s) actualmente. Primero desasigne o finalice las asignaciones.');
        }

        // Eliminar jornada (CASCADE eliminará turnos y asignaciones históricas)
        $stmt = $conn->prepare("
            DELETE FROM jornadas_trabajo
            WHERE id = ? AND usuario_id = ?
        ");

        $stmt->bind_param("ii", $jornada_id, $user_id);

        if (!$stmt->execute()) {
            throw new Exception('Error al eliminar: ' . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception('Jornada no encontrada o sin permisos');
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Jornada eliminada exitosamente'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ====== ARCHIVAR/DESACTIVAR JORNADA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_jornada') {
    header('Content-Type: application/json');

    try {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 1);

        if (!$jornada_id) {
            throw new Exception('ID inválido');
        }

        $stmt = $conn->prepare("
            UPDATE jornadas_trabajo SET is_active = ?
            WHERE id = ? AND usuario_id = ?
        ");

        $stmt->bind_param("iii", $is_active, $jornada_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $estado = $is_active ? 'activada' : 'archivada';

        echo json_encode([
            'success' => true,
            'message' => "Jornada {$estado} exitosamente"
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ====== ASIGNAR EMPLEADOS A JORNADA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'asignar_empleados') {
    header('Content-Type: application/json');

    try {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);
        $empleados = json_decode($_POST['empleados'] ?? '[]', true); // [{persona_id, fecha_inicio, fecha_fin, notas}]

        if (!$jornada_id || empty($empleados)) {
            throw new Exception('Datos incompletos');
        }

        // Verificar jornada
        $stmt_check = $conn->prepare("SELECT id FROM jornadas_trabajo WHERE id = ? AND usuario_id = ?");
        $stmt_check->bind_param("ii", $jornada_id, $user_id);
        $stmt_check->execute();
        if (stmt_get_result($stmt_check)->num_rows === 0) {
            throw new Exception('Jornada no válida');
        }
        $stmt_check->close();

        $stmt = $conn->prepare("
            INSERT INTO equipo_jornadas (
                persona_id, jornada_id, fecha_inicio, fecha_fin, notas, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $asignados = 0;
        $errores = [];

        foreach ($empleados as $emp) {
            $persona_id = intval($emp['persona_id'] ?? 0);
            $fecha_inicio = $emp['fecha_inicio'] ?? date('Y-m-d');
            $fecha_fin = !empty($emp['fecha_fin']) ? $emp['fecha_fin'] : NULL;
            $notas = trim($emp['notas'] ?? '');

            if (!$persona_id) continue;

            // Verificar que el empleado pertenece al usuario
            $stmt_emp_check = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ?");
            $stmt_emp_check->bind_param("ii", $persona_id, $user_id);
            $stmt_emp_check->execute();
            if (stmt_get_result($stmt_emp_check)->num_rows === 0) {
                $errores[] = "Empleado ID {$persona_id} no válido";
                $stmt_emp_check->close();
                continue;
            }
            $stmt_emp_check->close();

            try {
                $stmt->bind_param(
                    "iisssi",
                    $persona_id, $jornada_id, $fecha_inicio, $fecha_fin, $notas, $user_id
                );

                if ($stmt->execute()) {
                    $asignados++;
                }
            } catch (Exception $e) {
                // El trigger puede lanzar error si hay traslape
                $errores[] = "Empleado ID {$persona_id}: " . $e->getMessage();
            }
        }

        $stmt->close();

        $mensaje = "{$asignados} empleado(s) asignado(s) exitosamente";
        if (!empty($errores)) {
            $mensaje .= ". Errores: " . implode('; ', $errores);
        }

        echo json_encode([
            'success' => $asignados > 0,
            'message' => $mensaje,
            'asignados' => $asignados,
            'errores' => $errores
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ====== DESASIGNAR EMPLEADO DE JORNADA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'desasignar_empleado') {
    header('Content-Type: application/json');

    try {
        $asignacion_id = intval($_POST['asignacion_id'] ?? 0);
        $fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');

        if (!$asignacion_id) {
            throw new Exception('ID inválido');
        }

        // Actualizar fecha_fin de la asignación (no eliminar, para mantener histórico)
        $stmt = $conn->prepare("
            UPDATE equipo_jornadas ej
            INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
            SET ej.fecha_fin = ?
            WHERE ej.id = ? AND j.usuario_id = ?
        ");

        $stmt->bind_param("sii", $fecha_fin, $asignacion_id, $user_id);

        if (!$stmt->execute()) {
            throw new Exception('Error al desasignar');
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception('Asignación no encontrada');
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Empleado desasignado exitosamente'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ====== OBTENER DATOS DE JORNADA (para edición) ======
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_jornada') {
    header('Content-Type: application/json');

    try {
        $jornada_id = intval($_GET['jornada_id'] ?? 0);

        if (!$jornada_id) {
            throw new Exception('ID inválido');
        }

        // Obtener jornada
        $stmt = $conn->prepare("
            SELECT * FROM jornadas_trabajo
            WHERE id = ? AND usuario_id = ?
        ");

        $stmt->bind_param("ii", $jornada_id, $user_id);
        $stmt->execute();
        $jornada = stmt_get_result($stmt)->fetch_assoc();
        $stmt->close();

        if (!$jornada) {
            throw new Exception('Jornada no encontrada');
        }

        // Obtener turnos agrupados
        $stmt_turnos = $conn->prepare("
            SELECT * FROM turnos
            WHERE jornada_id = ?
            ORDER BY orden, dia_semana, hora_inicio
        ");

        $stmt_turnos->bind_param("i", $jornada_id);
        $stmt_turnos->execute();
        $result = stmt_get_result($stmt_turnos);

        $turnos = [];
        while ($row = $result->fetch_assoc()) {
            $turnos[] = $row;
        }
        $stmt_turnos->close();

        // Obtener empleados asignados actualmente
        $stmt_emp = $conn->prepare("
            SELECT ej.*, e.nombre_persona, e.cargo
            FROM equipo_jornadas ej
            INNER JOIN equipo e ON ej.persona_id = e.id
            WHERE ej.jornada_id = ?
              AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
            ORDER BY e.nombre_persona
        ");

        $stmt_emp->bind_param("i", $jornada_id);
        $stmt_emp->execute();
        $result_emp = stmt_get_result($stmt_emp);

        $empleados = [];
        while ($row = $result_emp->fetch_assoc()) {
            $empleados[] = $row;
        }
        $stmt_emp->close();

        echo json_encode([
            'success' => true,
            'jornada' => $jornada,
            'turnos' => $turnos,
            'empleados_asignados' => $empleados
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}






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
      return 'Colaborativa';

    case 'adhocracia': case 'adhocratica': case 'cultura adhocratica':
    case 'innovadora': case 'cultura innovadora': case 'innovacion':
    case 'agil': case 'ágil': case 'cultura de cambio':
      return 'Ágil';

    case 'jerarquica': case 'jerarquia': case 'jerárquica': case 'cultura jerarquica':
    case 'estructurada': case 'estructura': case 'cultura de orden':
      return 'Estructurada';

    case 'mercado': case 'cultura mercado':
    case 'orientada a resultados': case 'resultados': case 'enfoque a resultados':
    case 'cultura de impacto':
      return 'Orientada a Resultados';

    default:
      return '';
  }
}


/* ============ Helpers de metas (desempeño) ============ */

function clamp_pct($v){ 
    $v = is_numeric($v) ? (float)$v : 0; 
    return max(0, min(100, round($v, 0))); 
}
function avg(array $a){ 
    return count($a) ? array_sum($a)/count($a) : 0; 
}
function days_to_due($d){ 
    try{
        $due   = new DateTime($d); 
        $today = new DateTime('today'); 
        return (int)$today->diff($due)->format('%r%a');
    } catch(Exception $e){ 
        return null; 
    } 
}
function fmt_date($d){ 
    $ts = strtotime($d); 
    return $ts ? date('d/m/Y', $ts) : $d; 
}

function status_from_progress(int $pct, int $done): string {
    // $pct: 0, 50, 100
    // $done: 0 o 1
    if ($done === 1 || $pct >= 100) {
        return 'finalizada';
    }
    if ($pct >= 50) {
        return 'desarrollo';
    }
    return 'cola';
}



function pct_meta_area(array $m){ 
    $p = []; 
    foreach(($m['personales'] ?? []) as $x){ 
        $p[] = clamp_pct($x['porcentaje'] ?? 0);
    } 
    return avg($p); 
}
function pct_area(array $a){ 
    $p = []; 
    foreach(($a['metas_area'] ?? []) as $m){ 
        $p[] = pct_meta_area($m);
    } 
    return avg($p); 
}
function pct_corporativa(array $c){ 
    $p = []; 
    foreach(($c['areas'] ?? []) as $a){ 
        $p[] = pct_area($a);
    } 
    return avg($p); 
}

function due_badge($dateStr){
    $d = days_to_due($dateStr); 
    if($d === null) return '';
    if($d < 0){
        $cl = 'badge badge-danger'; 
        $tx = 'Vencida hace '.abs($d).' días';
    } elseif($d === 0){
        $cl = 'badge badge-warning'; 
        $tx = 'Vence hoy';
    } elseif($d <= 7){
        $cl = 'badge badge-warning'; 
        $tx = 'Vence en '.$d.' días';
    } else {
        $cl = 'badge badge-neutral'; 
        $tx = 'Vence en '.$d.' días';
    }
    return '<span class="'.$cl.'">'.$tx.'</span>';
}

/* ==== Helpers para orden y defaults ==== */
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





/* ============ Creación de metas (empresa, área, persona) ============ */
/* Nota: usamos SIEMPRE el $user_id actual del dashboard */

/* ============ Creación de metas (empresa, área, persona) ============ */
/* Nota: usamos SIEMPRE el $user_id actual del dashboard */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__crear_meta'])) {
    $tipo = $_POST['tipo'] ?? '';

    try {
        if ($tipo === 'empresa_quick') {
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

        } elseif ($tipo === 'empresa') {
            $desc = trim($_POST['descripcion'] ?? '');
            $due  = trim($_POST['due_date'] ?? '');
            if ($desc === '' || $due === '') {
                throw new Exception('Descripción y fecha de vencimiento son obligatorias para la meta de empresa.');
            }

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

        } elseif ($tipo === 'area_quick') {
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

        } elseif ($tipo === 'area') {
            $parent = (int)($_POST['parent_meta_id'] ?? 0);
            $areaId = (int)($_POST['area_id'] ?? 0);
            $desc   = trim($_POST['descripcion'] ?? '');
            $due    = trim($_POST['due_date'] ?? '');

            if ($desc === '' || $due === '' || !$parent || !$areaId) {
                throw new Exception('Descripción, fecha límite, meta de empresa y área son obligatorias.');
            }

            $ord = next_order_index_child($conn, $parent);

            $stmt = $conn->prepare("
                INSERT INTO metas (user_id, tipo, parent_meta_id, area_id, descripcion, due_date, order_index)
                VALUES (?, 'area', ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiissi", $user_id, $parent, $areaId, $desc, $due, $ord);
            $stmt->execute();
            $stmt->close();

        } elseif ($tipo === 'persona') {
            $meta_area_id = (int)($_POST['parent_meta_id'] ?? 0);
            $persona_id   = (int)($_POST['persona_id'] ?? 0);
            $desc         = trim($_POST['descripcion'] ?? '');
            $due          = trim($_POST['due_date'] ?? '');
            $status       = $_POST['status'] ?? 'cola'; // cola | desarrollo | finalizada

            if ($desc === '' || $due === '' || !$meta_area_id || !$persona_id) {
                throw new Exception('Faltan datos: descripción, due_date, meta_area_id o persona_id.');
            }

            // Mapear estado → porcentaje y completado
            switch ($status) {
                case 'desarrollo':
                    $pct  = 50;
                    $done = 0;
                    break;
                case 'finalizada':
                    $pct  = 100;
                    $done = 1;
                    break;
                case 'cola':
                default:
                    $pct  = 0;
                    $done = 0;
                    break;
            }

            $ctx = resolve_meta_area_context($conn, $meta_area_id);
            $meta_empresa_id = (int)$ctx['meta_empresa_id'];
            $area_id         = (int)$ctx['area_id'];

            if (!$meta_empresa_id || !$area_id) {
                throw new Exception('No se pudo resolver meta_empresa_id/area_id desde la meta de área.');
            }

            $stmt = $conn->prepare("
                INSERT INTO metas_personales
                  (user_id, meta_empresa_id, area_id, meta_area_id, persona_id, descripcion, due_date, progress_pct, is_completed, completed_at)
                VALUES
                  (?,       ?,               ?,      ?,            ?,          ?,           ?,        ?,            ?, CASE WHEN ?=1 THEN NOW() ELSE NULL END)
            ");

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


        } elseif ($tipo === 'persona_update') {
            $task_id = (int)($_POST['task_id'] ?? 0);
            $status  = $_POST['status'] ?? 'cola'; // cola | desarrollo | finalizada

            if (!$task_id) {
                throw new Exception('Falta el ID de la meta personal a actualizar.');
            }

            // Mapear estado → porcentaje y completado
            switch ($status) {
                case 'desarrollo':
                    $pct  = 50;
                    $done = 0;
                    break;
                case 'finalizada':
                    $pct  = 100;
                    $done = 1;
                    break;
                case 'cola':
                default:
                    $pct  = 0;
                    $done = 0;
                    break;
            }

            $stmt = $conn->prepare("
                UPDATE metas_personales
                SET 
                  progress_pct = ?,
                  is_completed = ?,
                  completed_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param("iiiii", $pct, $done, $done, $task_id, $user_id);

            if (!$stmt->execute()) {
                throw new Exception('No se pudo actualizar la meta personal: '.$stmt->errno.' '.$stmt->error);
            }
            $stmt->close();
        }



        // Si viene desde AJAX, respondemos sin recargar toda la página
        if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => true,
                'tipo'  => $tipo ?? null,
            ]);
            exit;
        }


        // Establecer mensaje de éxito en sesión
        $_SESSION['meta_success'] = 'Meta creada exitosamente';

        // Redirigir al mismo dashboard de desempeño
        header("Location: a-desempeno-dashboard.php");
        exit;

    } catch (Throwable $e) {
        // Guardar error en sesión
        $_SESSION['meta_error'] = 'Error al crear meta: ' . $e->getMessage();

        // Redirigir de vuelta
        header("Location: a-desempeno-dashboard.php");
        exit;
    }
}

/* ============ Crear Meta de Empresa (Nuevo botón en Tab de Metas) ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_meta_empresa') {
    try {
        $descripcion = trim($_POST['descripcion'] ?? '');
        $due_date    = trim($_POST['due_date'] ?? '');

        // Validar campos obligatorios
        if ($descripcion === '' || $due_date === '') {
            throw new Exception('La descripción y fecha límite son obligatorias.');
        }

        // Validar que la fecha no sea pasada
        if (strtotime($due_date) < strtotime(date('Y-m-d'))) {
            throw new Exception('La fecha límite no puede ser anterior a hoy.');
        }

        // Obtener el siguiente order_index para metas de empresa
        $order_index = 1;
        $stmt = $conn->prepare("SELECT MAX(order_index) as max_order FROM metas WHERE user_id = ? AND tipo = 'empresa'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = stmt_get_result($stmt);
        if ($row = $result->fetch_assoc()) {
            $order_index = ((int)$row['max_order']) + 1;
        }
        $stmt->close();

        // Insertar la nueva meta de empresa
        $stmt = $conn->prepare("
            INSERT INTO metas (
                user_id,
                tipo,
                descripcion,
                due_date,
                parent_meta_id,
                area_id,
                persona_id,
                progress_pct,
                is_completed,
                order_index
            ) VALUES (?, 'empresa', ?, ?, NULL, NULL, NULL, 0, 0, ?)
        ");

        $stmt->bind_param("issi", $user_id, $descripcion, $due_date, $order_index);

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar la meta en la base de datos: ' . $stmt->error);
        }

        $stmt->close();

        // Establecer mensaje de éxito
        $_SESSION['meta_success'] = '¡Meta de empresa creada exitosamente! 🎯';

        // Redirigir al tab de metas
        header("Location: a-desempeno-dashboard.php?tab=goals");
        exit;

    } catch (Exception $e) {
        // Guardar error en sesión
        $_SESSION['meta_error'] = $e->getMessage();

        // Redirigir al tab de metas
        header("Location: a-desempeno-dashboard.php?tab=goals");
        exit;
    }
}

/* ============ Marcar Notificación como Leída ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notification_read') {
    try {
        $notification_id = (int)($_POST['notification_id'] ?? 0);

        if ($notification_id <= 0) {
            throw new Exception('ID de notificación inválido.');
        }

        // Marcar como leída (solo si pertenece al usuario)
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = TRUE, read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        $stmt->bind_param("ii", $notification_id, $user_id);

        if (!$stmt->execute()) {
            throw new Exception('Error al marcar notificación como leída.');
        }

        $stmt->close();

        // Responder con JSON si es AJAX
        if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        // Redirigir si no es AJAX
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'a-desempeno-dashboard.php?tab=goals'));
        exit;

    } catch (Exception $e) {
        if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }

        $_SESSION['meta_error'] = $e->getMessage();
        header("Location: a-desempeno-dashboard.php?tab=goals");
        exit;
    }
}

/* ============ Marcar TODAS las Notificaciones como Leídas ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_notifications_read') {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = TRUE, read_at = NOW()
            WHERE user_id = ? AND is_read = FALSE
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'a-desempeno-dashboard.php?tab=goals'));
        exit;

    } catch (Exception $e) {
        if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }

        $_SESSION['meta_error'] = $e->getMessage();
        header("Location: a-desempeno-dashboard.php?tab=goals");
        exit;
    }
}

/* ============ Crear Meta de Equipo/Área (Botón contextual en cada meta empresa) ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear_meta_equipo') {
    try {
        $parent_meta_id = (int)($_POST['parent_meta_id'] ?? 0);
        $area_id        = (int)($_POST['area_id'] ?? 0);
        $descripcion    = trim($_POST['descripcion'] ?? '');
        $due_date       = trim($_POST['due_date'] ?? '');

        // Validar campos obligatorios
        if ($parent_meta_id <= 0) {
            throw new Exception('Meta de empresa no especificada.');
        }

        if ($area_id <= 0) {
            throw new Exception('Debes seleccionar un área de trabajo.');
        }

        if ($descripcion === '' || $due_date === '') {
            throw new Exception('La descripción y fecha límite son obligatorias.');
        }

        // Validar que la fecha no sea pasada
        if (strtotime($due_date) < strtotime(date('Y-m-d'))) {
            throw new Exception('La fecha límite no puede ser anterior a hoy.');
        }

        // Validar que la meta de empresa pertenece al usuario
        $stmt = $conn->prepare("SELECT id FROM metas WHERE id = ? AND user_id = ? AND tipo = 'empresa'");
        $stmt->bind_param("ii", $parent_meta_id, $user_id);
        $stmt->execute();
        $result = stmt_get_result($stmt);
        if ($result->num_rows === 0) {
            throw new Exception('Meta de empresa no válida.');
        }
        $stmt->close();

        // Validar que el área pertenece al usuario
        $stmt = $conn->prepare("SELECT id FROM areas_trabajo WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $area_id, $user_id);
        $stmt->execute();
        $result = stmt_get_result($stmt);
        if ($result->num_rows === 0) {
            throw new Exception('Área de trabajo no válida.');
        }
        $stmt->close();

        // Obtener el siguiente order_index para metas de área bajo esta meta empresa
        $order_index = 1;
        $stmt = $conn->prepare("SELECT MAX(order_index) as max_order FROM metas WHERE parent_meta_id = ? AND tipo = 'area'");
        $stmt->bind_param("i", $parent_meta_id);
        $stmt->execute();
        $result = stmt_get_result($stmt);
        if ($row = $result->fetch_assoc()) {
            $order_index = ((int)$row['max_order']) + 1;
        }
        $stmt->close();

        // Insertar la nueva meta de área (equipo)
        $stmt = $conn->prepare("
            INSERT INTO metas (
                user_id,
                tipo,
                parent_meta_id,
                area_id,
                descripcion,
                due_date,
                persona_id,
                progress_pct,
                is_completed,
                order_index
            ) VALUES (?, 'area', ?, ?, ?, ?, NULL, 0, 0, ?)
        ");

        $stmt->bind_param("iiissi", $user_id, $parent_meta_id, $area_id, $descripcion, $due_date, $order_index);

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar la meta de equipo: ' . $stmt->error);
        }

        $stmt->close();

        // Establecer mensaje de éxito
        $_SESSION['meta_success'] = '¡Meta de equipo creada exitosamente! 🏢';

        // Redirigir al tab de metas
        header("Location: a-desempeno-dashboard.php?tab=goals");
        exit;

    } catch (Exception $e) {
        // Guardar error en sesión
        $_SESSION['meta_error'] = $e->getMessage();

        // Redirigir al tab de metas
        header("Location: a-desempeno-dashboard.php?tab=goals");
        exit;
    }
}




/* ============ Consultas utilitarias de metas ============ */

// 1) Metas corporativas (empresa)
// Nota: progress_pct se calcula dinámicamente en PHP usando pct_corporativa()
function db_get_metas_empresa(mysqli $conn, int $user_id): array {
    $sql = "
        SELECT m.*
        FROM metas m
        WHERE m.user_id = ? AND m.tipo = 'empresa'
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
// Nota: progress_pct se calcula dinámicamente en PHP usando pct_area()
function db_get_metas_area(mysqli $conn, int $meta_empresa_id): array {
    $sql = "
        SELECT a.*, at.nombre_area
        FROM metas a
        LEFT JOIN areas_trabajo at ON at.id = a.area_id
        WHERE a.parent_meta_id = ? AND a.tipo = 'area'
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

// 3b) Metas personales filtradas por persona dentro de una meta-área
function db_get_metas_persona_by_person(
    mysqli $conn,
    int $user_id,
    int $meta_area_id,
    int $persona_id
): array {
    $sql = "
        SELECT mp.*, e.nombre_persona, e.cargo
        FROM metas_personales mp
        LEFT JOIN equipo e ON e.id = mp.persona_id
        WHERE mp.user_id = ? 
          AND mp.meta_area_id = ?
          AND mp.persona_id = ?
        ORDER BY mp.due_date, mp.id
    ";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $meta_area_id, $persona_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    $stmt->close();
    return $out;
}


// 4) Dropdowns: áreas y personas
function db_get_areas(mysqli $conn, int $user_id): array {
    $sql = "SELECT id, nombre_area FROM areas_trabajo WHERE usuario_id = ? ORDER BY nombre_area";
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
    $sql = "SELECT id, nombre_persona, cargo FROM equipo WHERE usuario_id = ? ORDER BY nombre_persona";
    $out = [];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = stmt_get_result($stmt);
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

/* ============ Tab Navigation ============ */
$active_tab = $_GET['tab'] ?? 'overview';
$valid_tabs = ['overview', 'goals', 'time', 'projects', 'people', 'analytics'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'overview';
}

/* ============ Queries para Tab 1: Overview ============ */
/* Nota: Estas queries usan vistas/stored procedures opcionales.
   Si no existen aún, el dashboard seguirá funcionando en Tab 2 (Metas) */

// Variables inicializadas por si las queries fallan
$risk_alerts = [];
$velocity_data = [];
$avg_velocity = 0;
$top_performers = [];
$needs_attention = [];
$total_goals = 0;
$completed_goals = 0;
$at_risk_goals = 0;
$global_completion = 0;
$v2_features_available = true;

// 1) Risk Alerts (proactive risk detection) - Requiere v_goals_at_risk
try {
    $stmt_risk = @$conn->prepare("
        SELECT * FROM v_goals_at_risk
        WHERE user_id = ?
        ORDER BY
            FIELD(risk_level, 'overdue', 'critical', 'high', 'medium', 'low'),
            days_until_due ASC
        LIMIT 50
    ");
    if ($stmt_risk) {
        $stmt_risk->bind_param("i", $user_id);
        $stmt_risk->execute();
        $res_risk = stmt_get_result($stmt_risk);
        while ($row = $res_risk->fetch_assoc()) {
            $risk_alerts[] = $row;
        }
        $stmt_risk->close();
    }
} catch (Exception $e) {
    // Vista no existe aún - silenciar error
    $v2_features_available = false;
}

// 2) Team Velocity (last 4 weeks) - Requiere sp_calculate_team_velocity
try {
    $stmt_velocity = @$conn->prepare("CALL sp_calculate_team_velocity(?, 4)");
    if ($stmt_velocity) {
        $stmt_velocity->bind_param("i", $user_id);
        $stmt_velocity->execute();
        $res_velocity = stmt_get_result($stmt_velocity);
        while ($row = $res_velocity->fetch_assoc()) {
            $velocity_data[] = $row;
        }
        $stmt_velocity->close();
        $conn->next_result(); // Clear stored procedure result

        $avg_velocity = count($velocity_data) > 0
            ? round(array_sum(array_column($velocity_data, 'goals_completed')) / count($velocity_data))
            : 0;
    }
} catch (Exception $e) {
    // Stored procedure no existe aún - silenciar error
    $v2_features_available = false;
}

// 3) Top Performers (last 30 days) - Usa tablas existentes
try {
    $stmt_top = $conn->prepare("
        SELECT
            e.id, e.nombre_persona, e.cargo,
            COUNT(*) as completed_count,
            AVG(DATEDIFF(mp.completed_at, mp.created_at)) as avg_days_to_complete
        FROM metas_personales mp
        LEFT JOIN equipo e ON e.id = mp.persona_id
        WHERE mp.user_id = ?
          AND mp.is_completed = 1
          AND mp.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY e.id
        ORDER BY completed_count DESC
        LIMIT 5
    ");
    if ($stmt_top) {
        $stmt_top->bind_param("i", $user_id);
        $stmt_top->execute();
        $res_top = stmt_get_result($stmt_top);
        while ($row = $res_top->fetch_assoc()) {
            $top_performers[] = $row;
        }
        $stmt_top->close();
    }
} catch (Exception $e) {
    // Silenciar error
}

// 4) People who need attention (stalled goals > 7 days, 0% progress)
try {
    $stmt_need = $conn->prepare("
        SELECT
            e.id, e.nombre_persona, e.cargo,
            COUNT(*) as stalled_count,
            MIN(mp.created_at) as oldest_goal
        FROM metas_personales mp
        LEFT JOIN equipo e ON e.id = mp.persona_id
        WHERE mp.user_id = ?
          AND mp.is_completed = 0
          AND mp.progress_pct = 0
          AND mp.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY e.id
        ORDER BY stalled_count DESC
        LIMIT 5
    ");
    if ($stmt_need) {
        $stmt_need->bind_param("i", $user_id);
        $stmt_need->execute();
        $res_need = stmt_get_result($stmt_need);
        while ($row = $res_need->fetch_assoc()) {
            $needs_attention[] = $row;
        }
        $stmt_need->close();
    }
} catch (Exception $e) {
    // Silenciar error
}

// 5) Global KPIs - Usa tablas existentes
try {
    $stmt_kpi = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
            AVG(progress_pct) as avg_progress
        FROM metas_personales
        WHERE user_id = ?
    ");
    if ($stmt_kpi) {
        $stmt_kpi->bind_param("i", $user_id);
        $stmt_kpi->execute();
        $kpi = stmt_get_result($stmt_kpi)->fetch_assoc();
        $total_goals = (int)($kpi['total'] ?? 0);
        $completed_goals = (int)($kpi['completed'] ?? 0);
        $global_completion = (int)round($kpi['avg_progress'] ?? 0);
        $stmt_kpi->close();
    }
} catch (Exception $e) {
    // Silenciar error
}

$at_risk_goals = count($risk_alerts);

/* ============ Queries Hero KPIs para Tab 2: Metas ============ */

// Variables inicializadas para Hero KPIs
$metas_total_count = 0;
$metas_completed_count = 0;
$metas_overdue_count = 0;
$metas_at_risk_count = 0;
$metas_on_track_count = 0;
$metas_critical_count = 0;
$metas_avg_progress = 0;
$metas_by_person_count = 0;
$metas_by_area_count = 0;
$metas_by_empresa_count = 0;

try {
    // NOTA: Sistema usa tabla 'metas' (empresa/area) y 'metas_personales' (separada)

    // 1) Contar metas de EMPRESA
    $stmt_empresa = $conn->prepare("
        SELECT COUNT(*) as cnt FROM metas
        WHERE user_id = ? AND tipo = 'empresa'
    ");
    if ($stmt_empresa) {
        $stmt_empresa->bind_param("i", $user_id);
        $stmt_empresa->execute();
        $metas_by_empresa_count = (int)stmt_get_result($stmt_empresa)->fetch_assoc()['cnt'];
        $stmt_empresa->close();
    }

    // 2) Contar metas de ÁREA
    $stmt_area = $conn->prepare("
        SELECT COUNT(*) as cnt FROM metas
        WHERE user_id = ? AND tipo = 'area'
    ");
    if ($stmt_area) {
        $stmt_area->bind_param("i", $user_id);
        $stmt_area->execute();
        $metas_by_area_count = (int)stmt_get_result($stmt_area)->fetch_assoc()['cnt'];
        $stmt_area->close();
    }

    // 3) Contar TODAS las metas personales (tabla separada metas_personales)
    $stmt_total = $conn->prepare("
        SELECT COUNT(*) as total FROM metas_personales
        WHERE user_id = ?
    ");
    if ($stmt_total) {
        $stmt_total->bind_param("i", $user_id);
        $stmt_total->execute();
        $metas_total_count = (int)stmt_get_result($stmt_total)->fetch_assoc()['total'];
        $metas_by_person_count = $metas_total_count; // Son lo mismo
        $stmt_total->close();
    }

    // 4) Contar metas COMPLETADAS (solo personas)
    $stmt_completed = $conn->prepare("
        SELECT COUNT(*) as completed FROM metas_personales
        WHERE user_id = ? AND is_completed = 1
    ");
    if ($stmt_completed) {
        $stmt_completed->bind_param("i", $user_id);
        $stmt_completed->execute();
        $metas_completed_count = (int)stmt_get_result($stmt_completed)->fetch_assoc()['completed'];
        $stmt_completed->close();
    }

    // 5) Contar metas VENCIDAS (overdue y no completadas)
    $stmt_overdue = $conn->prepare("
        SELECT COUNT(*) as overdue FROM metas_personales
        WHERE user_id = ?
        AND is_completed = 0
        AND due_date < CURDATE()
    ");
    if ($stmt_overdue) {
        $stmt_overdue->bind_param("i", $user_id);
        $stmt_overdue->execute();
        $metas_overdue_count = (int)stmt_get_result($stmt_overdue)->fetch_assoc()['overdue'];
        $stmt_overdue->close();
    }

    // 6) Contar metas EN RIESGO (<70% progreso y vence en <7 días)
    $stmt_risk = $conn->prepare("
        SELECT COUNT(*) as at_risk FROM metas_personales
        WHERE user_id = ?
        AND is_completed = 0
        AND progress_pct < 70
        AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    if ($stmt_risk) {
        $stmt_risk->bind_param("i", $user_id);
        $stmt_risk->execute();
        $metas_at_risk_count = (int)stmt_get_result($stmt_risk)->fetch_assoc()['at_risk'];
        $stmt_risk->close();
    }

    // 7) Contar metas ON TRACK (>=70% progreso o deadline >7 días)
    $stmt_ontrack = $conn->prepare("
        SELECT COUNT(*) as on_track FROM metas_personales
        WHERE user_id = ?
        AND is_completed = 0
        AND (
            progress_pct >= 70
            OR due_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        )
        AND due_date >= CURDATE()
    ");
    if ($stmt_ontrack) {
        $stmt_ontrack->bind_param("i", $user_id);
        $stmt_ontrack->execute();
        $metas_on_track_count = (int)stmt_get_result($stmt_ontrack)->fetch_assoc()['on_track'];
        $stmt_ontrack->close();
    }

    // 8) Contar metas CRÍTICAS (prioridad alta + baja completitud + cerca deadline)
    $stmt_critical = $conn->prepare("
        SELECT COUNT(*) as critical FROM metas_personales
        WHERE user_id = ?
        AND is_completed = 0
        AND progress_pct < 50
        AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
    if ($stmt_critical) {
        $stmt_critical->bind_param("i", $user_id);
        $stmt_critical->execute();
        $metas_critical_count = (int)stmt_get_result($stmt_critical)->fetch_assoc()['critical'];
        $stmt_critical->close();
    }

    // 9) Calcular PROMEDIO de progreso global
    $stmt_avg = $conn->prepare("
        SELECT AVG(progress_pct) as avg_progress FROM metas_personales
        WHERE user_id = ?
    ");
    if ($stmt_avg) {
        $stmt_avg->bind_param("i", $user_id);
        $stmt_avg->execute();
        $metas_avg_progress = (int)round(stmt_get_result($stmt_avg)->fetch_assoc()['avg_progress'] ?? 0);
        $stmt_avg->close();
    }

} catch (Exception $e) {
    // Silenciar error - Hero KPIs mostrarán 0s
}

// Calcular porcentaje de completitud global
$metas_completion_rate = $metas_total_count > 0
    ? round(($metas_completed_count / $metas_total_count) * 100)
    : 0;

// 9) Query para ALERTAS CRÍTICAS (metas que requieren atención inmediata)
$metas_alertas = [];
try {
    $stmt_alertas = $conn->prepare("
        SELECT
            mp.id,
            mp.descripcion,
            mp.progress_pct,
            mp.due_date,
            mp.created_at,
            e.nombre_persona,
            e.cargo,
            ma.descripcion as area_descripcion,
            DATEDIFF(mp.due_date, CURDATE()) as dias_restantes,
            DATEDIFF(CURDATE(), mp.updated_at) as dias_sin_actualizacion,
            CASE
                WHEN mp.due_date < CURDATE() AND mp.is_completed = 0 THEN 'overdue'
                WHEN mp.progress_pct < 50 AND DATEDIFF(mp.due_date, CURDATE()) <= 3 THEN 'critical'
                WHEN mp.progress_pct < 70 AND DATEDIFF(mp.due_date, CURDATE()) <= 7 THEN 'at_risk'
                ELSE 'normal'
            END as nivel_alerta
        FROM metas_personales mp
        LEFT JOIN equipo e ON e.id = mp.persona_id
        LEFT JOIN metas_area ma ON ma.id = mp.parent_meta_id
        WHERE mp.user_id = ?
        AND mp.is_completed = 0
        AND (
            mp.due_date < CURDATE()
            OR (mp.progress_pct < 50 AND DATEDIFF(mp.due_date, CURDATE()) <= 3)
            OR (mp.progress_pct < 70 AND DATEDIFF(mp.due_date, CURDATE()) <= 7)
        )
        ORDER BY
            FIELD(nivel_alerta, 'overdue', 'critical', 'at_risk'),
            mp.due_date ASC
        LIMIT 10
    ");

    if ($stmt_alertas) {
        $stmt_alertas->bind_param("i", $user_id);
        $stmt_alertas->execute();
        $res_alertas = stmt_get_result($stmt_alertas);

        while ($row = $res_alertas->fetch_assoc()) {
            $metas_alertas[] = $row;
        }
        $stmt_alertas->close();
    }
} catch (Exception $e) {
    // Silenciar error
}

/* ============ Queries para Tab 3: Time & Attendance ============ */

// Variables inicializadas
$asistencia_hoy = [];
$total_personas = 0;
$presentes_hoy = 0;
$tarde_hoy = 0;
$ausentes_hoy = 0;
$a_tiempo_hoy = 0;
$permisos_pendientes = [];
$permisos_hoy = [];
$vacaciones_proximas = [];
$patrones_atencion = [];
$patrones_excelentes = [];

try {
    // 1) Asistencia de HOY - Solo empleados que tienen turno configurado para hoy
    $dia_hoy_semana = (int)date('N'); // 1=Lunes, 7=Domingo
    $fecha_hoy = date('Y-m-d');

    // 1a) Crear registros de ausencia automáticos para empleados sin registro HOY
    $stmt_crear_ausentes = $conn->prepare("
        SELECT DISTINCT
            e.id as persona_id,
            ej.jornada_id
        FROM equipo e
        INNER JOIN equipo_jornadas ej ON e.id = ej.persona_id
            AND ej.fecha_inicio <= CURDATE()
            AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
        INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        INNER JOIN turnos t ON j.id = t.jornada_id AND t.dia_semana = ?
        LEFT JOIN asistencias a ON e.id = a.persona_id AND a.fecha = CURDATE()
        WHERE e.usuario_id = ?
          AND a.id IS NULL
    ");

    if ($stmt_crear_ausentes) {
        $stmt_crear_ausentes->bind_param("ii", $dia_hoy_semana, $user_id);
        $stmt_crear_ausentes->execute();
        $result_crear = stmt_get_result($stmt_crear_ausentes);

        if ($result_crear->num_rows > 0) {
            $stmt_insert = $conn->prepare("
                INSERT INTO asistencias (persona_id, jornada_id, fecha, estado, minutos_tarde_entrada, minutos_tarde_salida)
                VALUES (?, ?, ?, 'ausente', 0, 0)
            ");

            while ($ausente = $result_crear->fetch_assoc()) {
                $stmt_insert->bind_param("iis",
                    $ausente['persona_id'],
                    $ausente['jornada_id'],
                    $fecha_hoy
                );
                $stmt_insert->execute();
            }

            $stmt_insert->close();
        }

        $stmt_crear_ausentes->close();
    }

    // 1b) Query principal: Asistencia de HOY
    $stmt_asist_hoy = $conn->prepare("
        SELECT
            e.id as persona_id,
            e.nombre_persona,
            e.cargo,
            a.estado,
            a.hora_entrada,
            a.hora_salida,
            a.minutos_tarde_entrada as minutos_tarde,
            a.minutos_tarde_salida,
            j.nombre as jornada_nombre,
            j.color_hex as jornada_color,
            j.tolerancia_entrada_min,
            t.hora_inicio,
            t.hora_fin,
            CASE
                WHEN a.estado IS NULL THEN 'ausente'
                WHEN a.estado = 'ausente' THEN 'ausente'
                WHEN a.minutos_tarde_entrada > j.tolerancia_entrada_min THEN 'critico'
                WHEN a.minutos_tarde_entrada > 0 THEN 'warning'
                ELSE 'ok'
            END as nivel_alerta,
            CASE
                WHEN a.estado IS NULL THEN '❌ Ausente'
                WHEN a.estado = 'ausente' THEN '❌ Ausente'
                WHEN a.estado = 'tarde' THEN '⚠️ Tarde'
                WHEN a.estado = 'presente' THEN '✅ Presente'
                ELSE '• Sin registro'
            END as estado_visual
        FROM equipo e
        INNER JOIN equipo_jornadas ej ON e.id = ej.persona_id
            AND ej.fecha_inicio <= CURDATE()
            AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
        INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        INNER JOIN turnos t ON j.id = t.jornada_id AND t.dia_semana = ?
        LEFT JOIN asistencias a ON e.id = a.persona_id AND a.fecha = CURDATE()
        WHERE e.usuario_id = ?
        ORDER BY
            FIELD(nivel_alerta, 'ausente', 'critico', 'warning', 'ok'),
            e.nombre_persona
    ");

    if ($stmt_asist_hoy) {
        $stmt_asist_hoy->bind_param("ii", $dia_hoy_semana, $user_id);
        $stmt_asist_hoy->execute();
        $res = stmt_get_result($stmt_asist_hoy);

        while ($row = $res->fetch_assoc()) {
            $asistencia_hoy[] = $row;

            // Contar estados
            $estado = $row['estado'] ?? 'ausente';

            if ($estado === 'ausente' || $estado === null) {
                $ausentes_hoy++;
            } elseif ($estado === 'tarde') {
                $presentes_hoy++;
                $tarde_hoy++;
            } elseif ($estado === 'presente') {
                $presentes_hoy++;
                $a_tiempo_hoy++;
            }
        }
        $stmt_asist_hoy->close();
    }

    // 2) Total de personas con turno HOY (no todos los empleados, solo los que trabajan hoy)
    $stmt_total = $conn->prepare("
        SELECT COUNT(DISTINCT e.id) as total
        FROM equipo e
        INNER JOIN equipo_jornadas ej ON e.id = ej.persona_id
            AND ej.fecha_inicio <= CURDATE()
            AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
        INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        INNER JOIN turnos t ON j.id = t.jornada_id AND t.dia_semana = ?
        WHERE e.usuario_id = ?
    ");
    if ($stmt_total) {
        $stmt_total->bind_param("ii", $dia_hoy_semana, $user_id);
        $stmt_total->execute();
        $total_personas = (int)stmt_get_result($stmt_total)->fetch_assoc()['total'];
        $stmt_total->close();
    }

    // 3) Permisos pendientes de aprobación
    $stmt_permisos_pend = $conn->prepare("
        SELECT
            p.*,
            e.nombre_persona,
            e.cargo
        FROM permisos p
        LEFT JOIN equipo e ON e.id = p.persona_id
        WHERE p.usuario_id = ?
        AND p.estado = 'pendiente'
        ORDER BY p.created_at DESC
        LIMIT 10
    ");

    if ($stmt_permisos_pend) {
        $stmt_permisos_pend->bind_param("i", $user_id);
        $stmt_permisos_pend->execute();
        $res = stmt_get_result($stmt_permisos_pend);

        while ($row = $res->fetch_assoc()) {
            $permisos_pendientes[] = $row;
        }
        $stmt_permisos_pend->close();
    }

    // 4) Permisos para HOY
    $stmt_permisos_hoy = $conn->prepare("
        SELECT
            p.*,
            e.nombre_persona,
            e.cargo
        FROM permisos p
        LEFT JOIN equipo e ON e.id = p.persona_id
        WHERE p.usuario_id = ?
        AND p.estado = 'aprobado'
        AND CURDATE() BETWEEN p.fecha_inicio AND p.fecha_fin
        ORDER BY e.nombre_persona
    ");

    if ($stmt_permisos_hoy) {
        $stmt_permisos_hoy->bind_param("i", $user_id);
        $stmt_permisos_hoy->execute();
        $res = stmt_get_result($stmt_permisos_hoy);

        while ($row = $res->fetch_assoc()) {
            $permisos_hoy[] = $row;
        }
        $stmt_permisos_hoy->close();
    }

    // 5) Próximas vacaciones (próximos 30 días)
    $stmt_vacaciones = $conn->prepare("
        SELECT
            v.*,
            e.nombre_persona,
            e.cargo,
            DATEDIFF(v.fecha_inicio_programada, CURDATE()) as dias_hasta
        FROM vacaciones v
        LEFT JOIN equipo e ON e.id = v.persona_id
        WHERE v.usuario_id = ?
        AND v.fecha_inicio_programada IS NOT NULL
        AND v.fecha_inicio_programada >= CURDATE()
        AND v.fecha_inicio_programada <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY v.fecha_inicio_programada
        LIMIT 10
    ");

    if ($stmt_vacaciones) {
        $stmt_vacaciones->bind_param("i", $user_id);
        $stmt_vacaciones->execute();
        $res = stmt_get_result($stmt_vacaciones);

        while ($row = $res->fetch_assoc()) {
            $vacaciones_proximas[] = $row;
        }
        $stmt_vacaciones->close();
    }

    // 6) Patrones que necesitan atención (últimos 30 días)
    // Empleados con asistencia < 90% - Inasistencias frecuentes o llegadas tarde
    $stmt_patrones = $conn->prepare("
        SELECT
            e.id as persona_id,
            e.nombre_persona,
            e.cargo,
            COUNT(DISTINCT CASE
                WHEN t.dia_semana IS NOT NULL THEN a.fecha
            END) as dias_esperados,
            COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END) as dias_presente,
            COUNT(CASE WHEN a.estado = 'ausente' OR (a.estado IS NULL AND t.dia_semana IS NOT NULL) THEN 1 END) as dias_ausente,
            COUNT(CASE WHEN a.minutos_tarde_entrada > 15 THEN 1 END) as llegadas_tarde,
            COUNT(CASE WHEN a.estado IN ('presente', 'tarde') AND a.minutos_tarde_entrada = 0 THEN 1 END) as dias_puntual,
            COALESCE(AVG(CASE WHEN a.minutos_tarde_entrada > 0 THEN a.minutos_tarde_entrada END), 0) as promedio_tarde,
            CASE
                WHEN COUNT(DISTINCT CASE WHEN t.dia_semana IS NOT NULL THEN a.fecha END) > 0
                THEN ROUND((COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END) / COUNT(DISTINCT CASE WHEN t.dia_semana IS NOT NULL THEN a.fecha END)) * 100, 1)
                ELSE 0
            END as tasa_asistencia,
            CASE
                WHEN COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END) > 0
                THEN ROUND((COUNT(CASE WHEN a.estado IN ('presente', 'tarde') AND a.minutos_tarde_entrada = 0 THEN 1 END) / COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END)) * 100, 1)
                ELSE 0
            END as tasa_puntualidad
        FROM equipo e
        LEFT JOIN equipo_jornadas ej ON e.id = ej.persona_id
            AND ej.fecha_inicio <= CURDATE()
            AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
        LEFT JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        LEFT JOIN turnos t ON j.id = t.jornada_id
        LEFT JOIN asistencias a ON e.id = a.persona_id
            AND a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND a.fecha <= CURDATE()
        WHERE e.usuario_id = ?
        GROUP BY e.id, e.nombre_persona, e.cargo
        HAVING dias_esperados > 0 AND tasa_asistencia < 90
        ORDER BY tasa_asistencia ASC, llegadas_tarde DESC
        LIMIT 10
    ");

    if ($stmt_patrones) {
        $stmt_patrones->bind_param("i", $user_id);
        $stmt_patrones->execute();
        $res = stmt_get_result($stmt_patrones);

        while ($row = $res->fetch_assoc()) {
            $patrones_atencion[] = $row;
        }
        $stmt_patrones->close();
    }

    // 7) Patrones excelentes (para reconocimiento)
    // Empleados cumplidos con asistencia >= 95%
    $stmt_excelentes = $conn->prepare("
        SELECT
            e.id as persona_id,
            e.nombre_persona,
            e.cargo,
            COUNT(DISTINCT CASE
                WHEN t.dia_semana IS NOT NULL THEN a.fecha
            END) as dias_esperados,
            COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END) as dias_presente,
            COUNT(CASE WHEN a.estado IN ('presente', 'tarde') AND a.minutos_tarde_entrada = 0 THEN 1 END) as dias_puntual,
            CASE
                WHEN COUNT(DISTINCT CASE WHEN t.dia_semana IS NOT NULL THEN a.fecha END) > 0
                THEN ROUND((COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END) / COUNT(DISTINCT CASE WHEN t.dia_semana IS NOT NULL THEN a.fecha END)) * 100, 1)
                ELSE 0
            END as tasa_asistencia,
            CASE
                WHEN COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END) > 0
                THEN ROUND((COUNT(CASE WHEN a.estado IN ('presente', 'tarde') AND a.minutos_tarde_entrada = 0 THEN 1 END) / COUNT(CASE WHEN a.estado IN ('presente', 'tarde') THEN 1 END)) * 100, 1)
                ELSE 0
            END as tasa_puntualidad
        FROM equipo e
        LEFT JOIN equipo_jornadas ej ON e.id = ej.persona_id
            AND ej.fecha_inicio <= CURDATE()
            AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
        LEFT JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        LEFT JOIN turnos t ON j.id = t.jornada_id
        LEFT JOIN asistencias a ON e.id = a.persona_id
            AND a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND a.fecha <= CURDATE()
        WHERE e.usuario_id = ?
        GROUP BY e.id, e.nombre_persona, e.cargo
        HAVING dias_esperados >= 5 AND tasa_asistencia >= 95
        ORDER BY tasa_asistencia DESC, tasa_puntualidad DESC
        LIMIT 5
    ");

    if ($stmt_excelentes) {
        $stmt_excelentes->bind_param("i", $user_id);
        $stmt_excelentes->execute();
        $res = stmt_get_result($stmt_excelentes);

        while ($row = $res->fetch_assoc()) {
            $patrones_excelentes[] = $row;
        }
        $stmt_excelentes->close();
    }

} catch (Exception $e) {
    // Silenciar error - tab funcionará con datos vacíos
}

// Calcular porcentajes
$porcentaje_presentes = $total_personas > 0 ? round(($presentes_hoy / $total_personas) * 100) : 0;
$porcentaje_a_tiempo = $presentes_hoy > 0 ? round(($a_tiempo_hoy / $presentes_hoy) * 100) : 0;

// Contar solicitudes pendientes totales (permisos + vacaciones)
$solicitudes_pendientes_count = 0;
try {
    $stmt_count_pendientes = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM permisos WHERE usuario_id = ? AND estado = 'pendiente') +
            (SELECT COUNT(*) FROM vacaciones WHERE usuario_id = ? AND estado = 'pendiente') as total
    ");
    $stmt_count_pendientes->bind_param("ii", $user_id, $user_id);
    $stmt_count_pendientes->execute();
    $result_count = stmt_get_result($stmt_count_pendientes)->fetch_assoc();
    $solicitudes_pendientes_count = (int)($result_count['total'] ?? 0);
    $stmt_count_pendientes->close();
} catch (Exception $e) {
    // Silenciar error
}

/* ============ Queries para Horarios de Trabajo ============ */

// Variables inicializadas
$jornadas_trabajo = [];
$total_jornadas = 0;
$total_empleados_con_jornada = 0;
$jornadas_activas = 0;

try {
    // 1) Obtener todas las jornadas activas
    $stmt_jornadas = $conn->prepare("
        SELECT * FROM jornadas_trabajo
        WHERE usuario_id = ?
        ORDER BY nombre
    ");

    if ($stmt_jornadas) {
        $stmt_jornadas->bind_param("i", $user_id);
        $stmt_jornadas->execute();
        $result = stmt_get_result($stmt_jornadas);

        while ($row = $result->fetch_assoc()) {
            // Obtener turnos de esta jornada
            $jornada_id = (int)$row['id'];

            $stmt_turnos = $conn->prepare("
                SELECT * FROM turnos
                WHERE jornada_id = ?
                ORDER BY dia_semana, hora_inicio
            ");

            $stmt_turnos->bind_param("i", $jornada_id);
            $stmt_turnos->execute();
            $result_turnos = stmt_get_result($stmt_turnos);

            $turnos = [];
            while ($turno = $result_turnos->fetch_assoc()) {
                $turnos[] = $turno;
            }
            $stmt_turnos->close();

            // Contar empleados asignados a esta jornada
            $stmt_count = $conn->prepare("
                SELECT COUNT(DISTINCT persona_id) as total
                FROM equipo_jornadas
                WHERE jornada_id = ?
                  AND fecha_inicio <= CURDATE()
                  AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            ");
            $stmt_count->bind_param("i", $jornada_id);
            $stmt_count->execute();
            $count_result = stmt_get_result($stmt_count)->fetch_assoc();
            $stmt_count->close();

            $row['turnos'] = $turnos;
            $row['total_empleados'] = (int)$count_result['total'];
            $jornadas_trabajo[] = $row;

            $total_jornadas++;
            if ($row['is_active']) {
                $jornadas_activas++;
            }
        }

        $stmt_jornadas->close();
    }

    // 2) Contar empleados con jornada asignada actualmente
    $stmt_emp = $conn->prepare("
        SELECT COUNT(DISTINCT persona_id) as count
        FROM equipo_jornadas ej
        INNER JOIN jornadas_trabajo j ON ej.jornada_id = j.id
        WHERE j.usuario_id = ?
          AND ej.fecha_inicio <= CURDATE()
          AND (ej.fecha_fin IS NULL OR ej.fecha_fin >= CURDATE())
    ");

    if ($stmt_emp) {
        $stmt_emp->bind_param("i", $user_id);
        $stmt_emp->execute();
        $total_empleados_con_jornada = (int)stmt_get_result($stmt_emp)->fetch_assoc()['count'];
        $stmt_emp->close();
    }

} catch (Exception $e) {
    // Silenciar errores (las tablas pueden no existir aún)
    $jornadas_trabajo = [];
}

// Obtener lista de todos los empleados para el modal de asignación
$empleados_disponibles = [];
try {
    $stmt_empleados = $conn->prepare("
        SELECT id, nombre_persona, cargo
        FROM equipo
        WHERE usuario_id = ?
        ORDER BY nombre_persona
    ");

    if ($stmt_empleados) {
        $stmt_empleados->bind_param("i", $user_id);
        $stmt_empleados->execute();
        $result_empleados = stmt_get_result($stmt_empleados);

        while ($emp = $result_empleados->fetch_assoc()) {
            $empleados_disponibles[] = $emp;
        }

        $stmt_empleados->close();
    }
} catch (Exception $e) {
    $empleados_disponibles = [];
}


// Función helper para formatear días de la semana
function format_dias_turno($turnos) {
    if (empty($turnos)) return 'Sin turnos configurados';

    // Agrupar por días consecutivos
    $dias_map = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    $dias_unicos = array_unique(array_column($turnos, 'dia_semana'));
    sort($dias_unicos);

    // Detectar patrones comunes
    if ($dias_unicos === [1,2,3,4,5]) return 'Lun-Vie';
    if ($dias_unicos === [6,7]) return 'Sáb-Dom';
    if ($dias_unicos === [1,2,3,4,5,6,7]) return 'Todos los días';

    // Si no es patrón conocido, listar días
    $labels = array_map(function($d) use ($dias_map) {
        return $dias_map[$d] ?? '';
    }, $dias_unicos);

    return implode(', ', $labels);
}


// Función helper para detectar turno nocturno
function tiene_turno_nocturno($turnos) {
    foreach ($turnos as $t) {
        if (!empty($t['cruza_medianoche']) && $t['cruza_medianoche'] == 1) {
            return true;
        }
    }
    return false;
}


// Función helper para obtener rango de horas
function rango_horas_turno($turnos) {
    if (empty($turnos)) return 'Sin horario';

    $horas = [];
    foreach ($turnos as $t) {
        $inicio = substr($t['hora_inicio'], 0, 5); // HH:MM
        $fin = substr($t['hora_fin'], 0, 5);

        $clave = "{$inicio} - {$fin}";
        if (!in_array($clave, $horas)) {
            $horas[] = $clave;
        }
    }

    if (count($horas) === 1) {
        return $horas[0];
    }

    return count($horas) . ' horarios diferentes';
}

/* ============ Construcción del árbol de metas corporativas ============ */

$metas_corporativas = [];
$empresaRows = db_get_metas_empresa($conn, (int)$user_id);

foreach ($empresaRows as $erow) {
    $areasById = [];

    $areasRows = db_get_metas_area($conn, (int)$erow['id']);

    foreach ($areasRows as $arow) {

        // Arma lista de metas personales AGRUPADAS por persona bajo esta meta de área
        // Y también guarda las metas individuales para visualización detallada
        $personalesPack = [];
        $persRows = db_get_metas_persona($conn, (int)$user_id, (int)$arow['id']);

        $grouped   = [];
        $todayYmd  = (new DateTime('today'))->format('Y-m-d');

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
                    'due_next'         => null,
                    'count_completed'  => 0,
                    'count_overdue'    => 0,
                    'count_inprogress' => 0,
                    'metas_individuales' => [],  // NUEVO: guardar metas individuales
                ];
            }

            $grouped[$pid]['tareas_count']++;
            $grouped[$pid]['pct_sum'] += $pct;
            $grouped[$pid]['pct_n']   += 1;

            // NUEVO: Agregar meta individual al array
            $grouped[$pid]['metas_individuales'][] = [
                'id'          => (int)$prow['id'],
                'descripcion' => (string)$prow['descripcion'],
                'due_date'    => $due,
                'progress_pct' => $pct,
                'is_completed' => $done,
            ];

            if ($done === 1) {
                $grouped[$pid]['count_completed']++;
            } else {
                if ($due && strtotime($due) < strtotime($todayYmd)) {
                    $grouped[$pid]['count_overdue']++;
                } else {
                    $grouped[$pid]['count_inprogress']++;
                }
            }

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

        foreach ($grouped as $g) {
            $avgPct = $g['pct_n'] ? round($g['pct_sum'] / $g['pct_n']) : 0;
            $personalesPack[] = [
                'persona_id'       => $g['persona_id'],
                'nombre_persona'   => $g['nombre_persona'],
                'cargo'            => $g['cargo'],
                'tareas_count'     => $g['tareas_count'],
                'porcentaje'       => (int)$avgPct,
                'due_date'         => $g['due_next'] ?? null,
                'count_completed'  => $g['count_completed'],
                'count_overdue'    => $g['count_overdue'],
                'count_inprogress' => $g['count_inprogress'],
                'metas_individuales' => $g['metas_individuales'],  // NUEVO: incluir metas individuales
            ];
        }

        $areaId  = isset($arow['area_id']) ? (int)$arow['area_id'] : 0;
        $areaNom = (string)($arow['nombre_area'] ?? 'Área sin nombre');

        if (!isset($areasById[$areaId])) {
            $areasById[$areaId] = [
                'id'          => $areaId,
                'nombre_area' => $areaNom,
                'metas_area'  => [],
            ];
        }

        $areasById[$areaId]['metas_area'][] = [
            'id'          => (int)$arow['id'],
            'descripcion' => (string)$arow['descripcion'],
            'due_date'    => (string)$arow['due_date'],
            'personales'  => $personalesPack,
        ];
    }

    $metas_corporativas[] = [
        'id'          => (int)$erow['id'],
        'descripcion' => (string)$erow['descripcion'],
        'due_date'    => (string)$erow['due_date'],
        'areas'       => array_values($areasById),
    ];
}

/* ============ Cálculo del Avance Global CORRECTO ============ */
// Calcular el promedio del progreso de TODAS las metas corporativas
// Esto considera jerárquicamente: empresa -> área -> personas
$avance_global_total = 0;
$avance_global_count = 0;

foreach ($metas_corporativas as $meta_corp) {
    $pct = clamp_pct(pct_corporativa($meta_corp));
    $avance_global_total += $pct;
    $avance_global_count++;
}

// Calcular promedio global (si no hay metas corporativas, usar metas personales)
if ($avance_global_count > 0) {
    $metas_avg_progress = round($avance_global_total / $avance_global_count);
    $global_completion = $metas_avg_progress; // Sincronizar ambas variables
} else {
    // Fallback: si no hay metas corporativas, usar solo el promedio de metas personales
    // (este valor ya fue calculado anteriormente en las líneas 849-858)
    // $metas_avg_progress ya tiene el valor correcto
    $global_completion = $metas_avg_progress; // Sincronizar ambas variables
}

/* ============ Obtener áreas de trabajo para el dropdown ============ */
$areas_trabajo = db_get_areas($conn, (int)$user_id);

/* ============ Sistema de Notificaciones Top-Tech ============ */
// Obtener notificaciones no leídas para badge counter
$unread_notifications_count = 0;
$notifications = [];

try {
    // Contar notificaciones no leídas
    $stmt_notif_count = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = FALSE
    ");

    if ($stmt_notif_count) {
        $stmt_notif_count->bind_param("i", $user_id);
        $stmt_notif_count->execute();
        $unread_notifications_count = (int)stmt_get_result($stmt_notif_count)->fetch_assoc()['unread_count'];
        $stmt_notif_count->close();
    }

    // Obtener las últimas 50 notificaciones (leídas y no leídas)
    $stmt_notif = $conn->prepare("
        SELECT
            id,
            type,
            actor_name,
            title,
            message,
            related_goal_id,
            is_read,
            priority,
            action_url,
            action_label,
            created_at,
            CASE
                WHEN DATE(created_at) = CURDATE() THEN 'Hoy'
                WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 'Ayer'
                WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Esta semana'
                WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Este mes'
                ELSE 'Más antiguas'
            END AS date_group,
            CASE
                WHEN type = 'new_personal_goal' THEN '🎯'
                WHEN type = 'goal_completed' THEN '✅'
                WHEN type = 'goal_at_risk' THEN '⚠️'
                WHEN type = 'goal_overdue' THEN '🚨'
                WHEN type = 'goal_updated' THEN '📝'
                WHEN type = 'team_milestone' THEN '🎉'
            END AS icon
        FROM notifications
        WHERE user_id = ?
        ORDER BY is_read ASC, created_at DESC
        LIMIT 50
    ");

    if ($stmt_notif) {
        $stmt_notif->bind_param("i", $user_id);
        $stmt_notif->execute();
        $result = stmt_get_result($stmt_notif);
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt_notif->close();
    }

} catch (Exception $e) {
    // Tabla de notificaciones no existe aún - silenciar error
    $unread_notifications_count = 0;
    $notifications = [];
}

// Agrupar notificaciones por fecha
$notifications_grouped = [];
foreach ($notifications as $notif) {
    $group = $notif['date_group'];
    if (!isset($notifications_grouped[$group])) {
        $notifications_grouped[$group] = [];
    }
    $notifications_grouped[$group][] = $notif;
}




/* ============ Identidad de la marca (como en el dashboard) ============ */
// $user_id ya viene definido arriba
$usuario_id = $user_id; // Necesario para reutilizar el mismo header del dashboard

$stmt = $conn->prepare("SELECT empresa, logo, cultura_empresa_tipo, rol FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = stmt_get_result($stmt);
$u = $result->fetch_assoc() ?: [];
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

$valores_ideales = [];
foreach (['distancia_poder','individualismo','masculinidad','incertidumbre','largo_plazo','indulgencia'] as $k) {
  $valores_ideales[$k] = isset($cultura_ideal[$k]) ? round(((float)$cultura_ideal[$k]) / 5, 3) : 0.0;
}

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

$total_alineacion = 0; 
$n = 0;
while ($row = $res_equipo->fetch_assoc()) {
  $suma = 0; $d=0;
  foreach ($valores_ideales as $k=>$ideal) {
    if (!isset($row[$k])) continue;
    $real = (float)$row[$k]; // -1..1
    $aline = 1 - (abs($real - (float)$ideal) / 2);
    $aline = max(0, min(1, $aline));
    $suma += $aline; 
    $d++;
  }
  if ($d>0) { 
    $total_alineacion += ($suma/$d); 
    $n++; 
  }
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
$total_pink = array_sum($pink); 
$max_pink = $equipo_count * 25;
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

$prom_sens = [
  'visual'      => (float)$sen['visual'], 
  'auditivo'    => (float)$sen['auditivo'], 
  'kinestesico' => (float)$sen['kinestesico']
];
$hay_datos_sensoriales = array_sum($prom_sens) > 0;

$LABELS_SENSORIALES = [
  'visual'      => 'Visual',
  'auditivo'    => 'Auditivo',
  'kinestesico' => 'Kinestésico'
];

$NORM = function($s){
  return preg_replace(
    '/\s+/', 
    ' ', 
    strtr(
      mb_strtolower(trim((string)$s),'UTF-8'),
      ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']
    )
  );
};



$dom_sensorial = $hay_datos_sensoriales ? array_keys($prom_sens, max($prom_sens))[0] : null;
$estilo_equipo_aprend = $dom_sensorial ? $LABELS_SENSORIALES[$dom_sensorial] : 'Sin datos';

// Estilo de aprendizaje de la cultura: usamos estilo_comunicacion como referencia
$estilo_cultura_aprend = $cultura_ideal['estilo_comunicacion'] ?? 'Visual';
$_estilo_cultura_norm  = $NORM($estilo_cultura_aprend);


$aprend_alineado = $dom_sensorial 
  ? ($NORM($LABELS_SENSORIALES[$dom_sensorial]) === $_estilo_cultura_norm) 
  : false;

/* 5) Chips para KPIs */
list($aline_label, $aline_class, $aline_icon) = chip_for_alineacion($promedio_general);
list($mot_label,   $mot_class,   $mot_icon)   = chip_for_motivacion($energia_status);

/* --- Cultura por Hofstede (marca) → X/Y, cuadrante y etiqueta visible --- */
$hof_individualismo = (float)($valores_ideales['individualismo']   ?? 0.0);
$hof_poder          = (float)($valores_ideales['distancia_poder'] ?? 0.0);
$hof_indulgencia    = (float)($valores_ideales['indulgencia']     ?? 0.0);
$hof_incertidumbre  = (float)($valores_ideales['incertidumbre']   ?? 0.0);

// Proyección coherente con dashboard_empleado
$hof_x = (0.70 * $hof_individualismo - 0.30 * $hof_poder) * 5.0;      // escala -5..+5
$hof_y = (0.60 * $hof_indulgencia   - 0.40 * $hof_incertidumbre) * 5.0;

// Cuadrante puro Hofstede
$cultura_tipo_hof = '';
if ($hof_x < 0 && $hof_y > 0)        $cultura_tipo_hof = 'Clan';
elseif ($hof_x >= 0 && $hof_y > 0)   $cultura_tipo_hof = 'Adhocracia';
elseif ($hof_x < 0 && $hof_y <= 0)   $cultura_tipo_hof = 'Jerárquica';
elseif ($hof_x >= 0 && $hof_y <= 0)  $cultura_tipo_hof = 'Mercado';

// Etiqueta visible final del header:
$cultura_empresa_tipo_display = '';
if (!empty($cultura_empresa_tipo)) {
  $cultura_empresa_tipo_display = cultura_label($cultura_empresa_tipo);
} elseif (!empty($cultura_tipo_hof)) {
  $cultura_empresa_tipo_display = cultura_label($cultura_tipo_hof);
} else {
  $cultura_empresa_tipo_display = 'No definida';
}



// Tipo de cultura final canónico para TODA la página:
$cultura_tipo_final_raw = !empty($cultura_empresa_tipo) ? $cultura_empresa_tipo : $cultura_tipo_hof;
$cultura_tipo_final     = cultura_key_canon($cultura_tipo_final_raw);

// ⚠️ Respaldo por si sigue vacío
if (empty($cultura_tipo_final)) {
  $cultura_tipo_final = !empty($cultura_tipo_hof) ? $cultura_tipo_hof : 'Clan';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Desempeño del equipo — Valírica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tipografía -->
  <link rel="preconnect" href="https://use.typekit.net" crossorigin>
  <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">

  <!-- Valírica Design System -->
  <link rel="stylesheet" href="valirica-design-system.css">

  <style>
    /* === Desempeño Dashboard Page Specific Styles === */

    /* ===== V2 Ultra-Polished Design System ===== */
    :root {
      --perf-bg: #FAFBFC;
      --perf-card-bg: #FFFFFF;
      --perf-border: #E4E7EB;
      --perf-border-hover: #CBD2D9;
      --risk-overdue: #DA3633;
      --risk-critical: #FF3B6D;
      --risk-high: #FB8500;
      --risk-medium: #FFB020;
      --risk-low: #00D98F;
      --transition: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
      background: var(--perf-bg);
    }

    /* Nota: .wrap ya está definido en valirica-design-system.css */

    /* ===== Tab Navigation (Stripe × Linear level) ===== */
    .tab-nav {
      background: var(--perf-card-bg);
      border-bottom: 1px solid var(--perf-border);
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(8px);
      background: rgba(255, 255, 255, 0.95);
    }

    .tab-nav-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 clamp(16px, 3vw, 40px);
      display: flex;
      gap: var(--space-2);
      overflow-x: auto;
      scrollbar-width: none;
    }

    .tab-nav-container::-webkit-scrollbar {
      display: none;
    }

    .tab-link {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-4) var(--space-3);
      font-size: 14px;
      font-weight: 600;
      color: var(--c-body);
      text-decoration: none;
      border-bottom: 2px solid transparent;
      transition: all var(--transition);
      white-space: nowrap;
      opacity: 0.7;
    }

    .tab-link:hover {
      opacity: 1;
      color: var(--c-accent);
    }

    .tab-link.is-active {
      color: var(--c-accent);
      border-bottom-color: var(--c-accent);
      opacity: 1;
    }

    .tab-icon {
      font-size: 16px;
      filter: grayscale(0.3);
    }

    .tab-link.is-active .tab-icon {
      filter: grayscale(0);
    }

    /* ===== Tab Content ===== */
    .tab-content {
      display: none;
    }

    .tab-content.is-active {
      display: block;
      animation: fadeIn 200ms ease-in;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ===== Hero KPI Cards ===== */
    .hero-kpis {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: var(--space-5);
      margin-bottom: var(--space-6);
    }

    .kpi-hero-card {
      background: var(--perf-card-bg);
      border: 1px solid var(--perf-border);
      border-radius: var(--radius-lg);
      padding: var(--space-6);
      transition: all var(--transition);
      position: relative;
      overflow: hidden;
    }

    .kpi-hero-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(135deg, #012133 0%, #184656 100%);
      opacity: 0;
      transition: opacity var(--transition);
    }

    .kpi-hero-card:hover {
      border-color: var(--perf-border-hover);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      transform: translateY(-2px);
    }

    .kpi-hero-card:hover::before {
      opacity: 1;
    }

    .kpi-hero-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-3);
    }

    .kpi-hero-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--c-body);
      opacity: 0.8;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .kpi-hero-icon {
      font-size: 24px;
      opacity: 0.7;
    }

    .kpi-hero-value {
      font-size: 36px;
      font-weight: 700;
      color: var(--c-accent);
      line-height: 1;
      margin-bottom: var(--space-2);
    }

    .kpi-hero-trend {
      display: flex;
      align-items: center;
      gap: var(--space-1);
      font-size: 13px;
      color: var(--c-body);
      opacity: 0.7;
    }

    .kpi-hero-trend.positive {
      color: #00D98F;
    }

    .kpi-hero-trend.negative {
      color: #DA3633;
    }

    /* ===== Risk Alerts ===== */
    .risk-alerts-section {
      margin-bottom: var(--space-6);
    }

    .risk-alert-card {
      background: var(--perf-card-bg);
      border: 1px solid var(--perf-border);
      border-radius: var(--radius-lg);
      padding: var(--space-5);
      margin-bottom: var(--space-4);
      transition: all var(--transition);
    }

    .risk-alert-card:hover {
      border-color: var(--perf-border-hover);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .risk-alert-header {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      margin-bottom: var(--space-4);
    }

    .risk-badge {
      display: inline-flex;
      align-items: center;
      gap: var(--space-1);
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .risk-badge.overdue {
      background: rgba(218, 54, 51, 0.1);
      color: var(--risk-overdue);
      border: 1px solid rgba(218, 54, 51, 0.2);
    }

    .risk-badge.critical {
      background: rgba(255, 59, 109, 0.1);
      color: var(--risk-critical);
      border: 1px solid rgba(255, 59, 109, 0.2);
    }

    .risk-badge.high {
      background: rgba(251, 133, 0, 0.1);
      color: var(--risk-high);
      border: 1px solid rgba(251, 133, 0, 0.2);
    }

    .risk-badge.medium {
      background: rgba(255, 176, 32, 0.1);
      color: var(--risk-medium);
      border: 1px solid rgba(255, 176, 32, 0.2);
    }

    .risk-badge.low {
      background: rgba(0, 217, 143, 0.1);
      color: var(--risk-low);
      border: 1px solid rgba(0, 217, 143, 0.2);
    }

    .risk-alert-list {
      display: flex;
      flex-direction: column;
      gap: var(--space-3);
    }

    .risk-alert-item {
      display: flex;
      align-items: flex-start;
      gap: var(--space-3);
      padding: var(--space-3);
      background: var(--perf-bg);
      border-radius: var(--radius);
      border: 1px solid var(--perf-border);
    }

    .risk-alert-content {
      flex: 1;
    }

    .risk-alert-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--c-secondary);
      margin-bottom: var(--space-1);
    }

    .risk-alert-meta {
      display: flex;
      gap: var(--space-3);
      font-size: 12px;
      color: var(--c-body);
      opacity: 0.7;
    }

    /* ===== Team Pulse ===== */
    .team-pulse-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: var(--space-5);
    }

    .pulse-card {
      background: var(--perf-card-bg);
      border: 1px solid var(--perf-border);
      border-radius: var(--radius-lg);
      padding: var(--space-5);
    }

    .pulse-card-header {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      margin-bottom: var(--space-4);
    }

    .pulse-card-icon {
      font-size: 20px;
    }

    .pulse-card-title {
      font-size: 15px;
      font-weight: 700;
      color: var(--c-secondary);
    }

    .pulse-person-item {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      padding: var(--space-3);
      background: var(--perf-bg);
      border-radius: var(--radius);
      margin-bottom: var(--space-2);
    }

    .pulse-person-info {
      flex: 1;
    }

    .pulse-person-name {
      font-size: 14px;
      font-weight: 600;
      color: var(--c-secondary);
    }

    .pulse-person-role {
      font-size: 12px;
      color: var(--c-body);
      opacity: 0.7;
    }

    .pulse-person-stat {
      font-size: 18px;
      font-weight: 700;
      color: var(--c-accent);
    }

    /* Section Headers */
    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: var(--space-5);
    }

    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--c-secondary);
    }

    .section-subtitle {
      font-size: 13px;
      color: var(--c-body);
      opacity: 0.7;
      margin-top: var(--space-1);
    }

    /* Componentes específicos del dashboard de desempeño */
    .kpi-help{
      position:relative;display:inline-flex;align-items:center;justify-content:center;
      width:16px;height:16px;margin-left:6px;border-radius:50%;
      background:rgba(255,255,255,.18);color:var(--c-soft);
      font-size:11px;font-weight:600;cursor:pointer;user-select:none
    }
    .kpi-help:hover::after,.kpi-help:focus::after{
      content:attr(data-tooltip);position:absolute;top:130%;right:0;
      background:#012133;color:#fff;padding:8px 10px;font-size:12px;
      border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.25);white-space:nowrap;z-index:10
    }
    .kpi-chip{
      display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;
      font-size:12px;line-height:1;border:1px solid rgba(255,255,255,.22);
      background:rgba(255,255,255,.12);color:var(--c-soft)
    }
    .kpi-chip.ok{border-color:rgba(24,70,86,.5);background:rgba(24,70,86,.25)}
    .kpi-chip.warn{border-color:rgba(239,127,27,.55);background:rgba(239,127,27,.2)}

    .btn-cta-primary{
      display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
      font-weight:700;font-size:14px;text-decoration:none;background:var(--c-accent);color:#fff;
      border:1px solid rgba(0,0,0,0.06);box-shadow:var(--shadow);cursor:pointer
    }
    .btn-cta-primary:hover{filter:brightness(.98)}
    .btn-sm{padding:8px 12px;font-size:13px;border-radius:10px}

    /* ===== Subnav ===== */
    .subnav{
      width:100%;background:transparent;border:0;
      border-bottom:1px solid rgba(1,33,51,.08);
      box-shadow:0 1px 0 rgba(1,33,51,.05)
    }
    .subnav-inner{max-width:1400px;margin:0 auto;padding:6px clamp(16px,3vw,40px)}
    .subnav-list{
      display:grid;grid-auto-flow:column;grid-auto-columns:1fr;
      align-items:center;justify-items:center;list-style:none;gap:0;padding:6px 0
    }
    .subnav-link{
      display:inline-flex;align-items:center;justify-content:center;height:38px;
      padding:0 8px;font-size:14px;font-weight:700;color:var(--c-accent);
      letter-spacing:.2px;opacity:.9;transition:opacity .15s ease,transform .12s ease
    }
    .subnav-link:hover{opacity:1;transform:translateY(-1px)}
    .subnav-link.is-active{position:relative;opacity:1}
    .subnav-link.is-active::after{
      content:"";position:absolute;left:25%;right:25%;bottom:-6px;height:2px;
      background:var(--c-accent);border-radius:2px;opacity:.95
    }

    /* Nota: .wrap, .grid y .card ya están en valirica-design-system.css */
    
    
    
    
    
    /* ===== Sección de metas (Cumplimiento / Desempeño) ===== */

.metas-header-main{
  flex:1 1 260px;
  max-width:720px;
}

.metas-section-title{
  font-size:clamp(22px,2.4vw,26px);
  color:var(--c-primary);
  margin:0 0 6px;
  font-weight:700;
}

.metas-section-subtitle{
  font-size:14px;
  color:var(--c-body);
  opacity:.85;
  margin:0 0 12px;
}

/* Botones Valírica para metas */
.btn-valirica {
  display:flex;
  align-items:center;
  background-color:#FF7800;
  color:white;
  padding:10px 20px;
  border:none;
  border-radius:25px;
  cursor:pointer;
  font-size:16px;
  text-decoration:none;
  font-weight:700;
  box-shadow:var(--shadow);
}
.btn-valirica img {
  width:40px;
  height:40px;
  margin-right:10px;
  object-fit:cover;
  border-radius:8px;
}
.btn-valirica--small {
  background-color:#FF7800;
  color:#fff;
  padding:8px 16px;
  border:none;
  border-radius:22px;
  cursor:pointer;
  font-size:14px;
  font-weight:600;
}
.btn-valirica--small:hover,
.btn-valirica:hover{
  filter:brightness(.98);
}

/* Tarjeta de meta (reusa look de .card pero más específica) */
.meta-card{
  background:#fff;
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:20px;
  border:1px solid #f1f1f1;
  display:flex;
  flex-direction:column;
  gap:10px;
}


/* Formulario viejo eliminado - ahora se usa modal unificado */


.meta-head{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:12px;
  margin-bottom:10px;
}
.meta-title{
  font-size:18px;
  color:var(--c-secondary);
  font-weight:700;
  flex:1;
}

/* Badges de fechas/estados */
.badge{
  display:inline-block;
  padding:4px 10px;
  font-size:12px;
  border-radius:999px;
  border:1px solid var(--line);
  background:#fff;
  color:var(--muted);
  white-space:nowrap;
}
.badge-warning{ border-color:#ffe0b2; background:#fff7e6; color:#8a6d3b;}
.badge-danger{ border-color:#f3c2c0; background:#fdecea; color:#a94442;}
.badge-neutral{ border-color:var(--line); background:#fff; color:var(--muted);}

/* Barra de progreso */
.progress{
  width:100%;
  background:#f3f3f3;
  border:1px solid var(--line);
  height:10px;
  border-radius:8px;
  overflow:hidden;
}
.progress > .fill{
  height:100%;
  width:0%;
  background: var(--c-secondary);
  transition:width .4s ease;
}
.progress-row{
  display:flex; align-items:center; gap:12px; margin:8px 0 4px;
}
.progress-label{
  font-size:13px; color:var(--muted);
}
.progress-value{
  margin-left:auto;
  font-size:16px; color:var(--c-accent); font-weight:700;
}

/* Acordeones de áreas/metas */
details.area, details.meta-area{
  border:1px solid var(--line);
  border-radius:12px;
  background:#fff;
  margin-top:12px;
  overflow:hidden;
}
details > summary{
  list-style:none;
  padding:10px 12px;
  cursor:pointer;
  user-select:none;
  display:flex; align-items:center; gap:10px;
  font-size:14px; color:var(--c-body); font-weight:600;
}
details > summary::-webkit-details-marker{ display:none; }
.caret{
  width:10px; height:10px; border-right:2px solid var(--muted); border-bottom:2px solid var(--muted);
  transform:rotate(-45deg); transition:transform .2s ease;
  margin-right:2px;
}
details[open] > summary .caret{ transform:rotate(45deg); }
.summary-right{
  margin-left:auto; display:flex; align-items:center; gap:10px;
}
.area-body, .meta-body{
  padding:0 12px 12px;
}
.area-title{
  color:#FF7800;
}

/* Listado de personas dentro de una meta de área */
.person-list{
  display:flex;
  flex-direction:column;
  gap:10px;
  width:100%;
}
.person-item{
  border:1px solid var(--line);
  border-radius:10px;
  padding:12px;
  background:#fff;
  width:100%;
  box-sizing:border-box;
}
.person-topline{
  display:flex; align-items:center; gap:8px; margin-bottom:6px;
}
.person-name{
  font-size:15px; color:var(--c-secondary); font-weight:700;
}
.person-role{
  font-size:13px; color:var(--muted); font-weight:400;
}
.cta-perfil{
  margin-left:auto;
  background:#FF7800;
  color:#fff;
  text-decoration:none;
  padding:6px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.person-caption{
  font-size:13px; color:#666; margin:8px 0 0;
}

/* Contenedores de formularios toggle */
.meta-card__actions {
  display:flex;
  justify-content:flex-end;
  margin-top:10px;
}

/* Header ligero de la sección */




/* ===== Hero de metas (propósito + CTA) ===== */
.metas-hero{
  background:#fff;
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  border:1px solid #f1f1f1;
  padding:20px 22px 18px;
  margin-bottom:24px;
  display:flex;
  flex-wrap:wrap;
  gap:18px;
  align-items:flex-start;
  justify-content:space-between;
}

.metas-hero-main{
  flex:1 1 260px;
  max-width:720px;
}



/* ===== Pasos del flujo (1 empresa, 2 áreas, 3 personas) ===== */
.metas-steps{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin:4px 0 10px;
}

.step-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  line-height:1;
  background:#F5F7FA;
  color:var(--c-secondary);
}

.step-chip--primary{
  background:var(--c-soft);
  border:1px solid rgba(239,127,27,.25);
}

.step-number{
  width:18px;
  height:18px;
  border-radius:999px;
  border:1px solid var(--c-accent);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:11px;
  font-weight:700;
  color:var(--c-accent);
}

.step-text{
  white-space:nowrap;
}

.metas-mini-legend{
  font-size:12px;
  color:var(--muted);
  margin:0 0 8px;
}

/* Etiquetas de paso dentro de las tarjetas */
.meta-step-label{
  font-size:12px;
  color:var(--muted);
  margin-right:auto;
}

/* Alineación de acciones dentro de meta-body */
.meta-body__actions{
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:8px;
  margin-top:10px;
}




.metas-section-title{
  font-size:clamp(22px,2.4vw,26px);
  color:var(--c-primary);
  margin:0 0 6px;
  font-weight:700;
}

.metas-section-subtitle{
  font-size:14px;
  color:var(--c-body);
  opacity:.85;
  margin:0 0 12px;
}

/* Bloque propósito dentro del hero */
.metas-purpose{
  padding:10px 14px;
  border-radius:14px;
  background:var(--c-soft);
  border:1px solid rgba(239,127,27,.18);
  display:flex;
  flex-direction:column;
  gap:4px;
}

.metas-purpose-label{
  font-size:11px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.08em;
  color:var(--c-accent);
}

.metas-purpose-text{
  font-size:14px;
  line-height:1.5;
  color:var(--c-secondary);
}

/* Columna derecha: CTA */
.metas-hero-cta{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:8px;
  flex:0 0 auto;
  min-width:220px;
}

.metas-hero-hint{
  font-size:12px;
  color:var(--muted);
  max-width:220px;
  text-align:right;
}

/* Responsivo: en móvil todo en columna */
@media (max-width: 768px){
  .metas-hero{
    padding:18px 16px 16px;
  }
  .metas-hero-cta{
    align-items:flex-start;
    text-align:left;
  }
  .metas-hero-hint{
    text-align:left;
  }
}



/* ===== Layout específico de metas ===== */
.metas-layout{
  display:flex;
  flex-direction:column;
  gap:24px;
}

/* Bloque propósito */
.metas-purpose{
  padding:10px 14px;
  border-radius:14px;
  background:var(--c-soft);
  border:1px solid rgba(239,127,27,.18);
  display:flex;
  flex-direction:column;
  gap:4px;
}

.metas-purpose-label{
  font-size:12px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.08em;
  color:var(--c-accent);
}

.metas-purpose-text{
  font-size:14px;
  line-height:1.5;
  color:var(--c-secondary);
}

/* Tarjeta cabecera de la sección de metas */
.metas-header-card{
  padding:24px 24px 20px;
}

/* Grilla de tarjetas de metas corporativas */
.metas-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));
  gap:20px;
}

/* Cabecera de cada meta corporativa: título + chips + botón área */
.meta-head-right{
  display:flex;
  align-items:center;
  gap:10px;
}

/* Botón fantasma pequeñito dentro de la tarjeta */
.btn-ghost-small{
  border:1px solid var(--line);
  background:#fff;
  color:var(--c-secondary);
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:600;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:4px;
}
.btn-ghost-small:hover{
  background:#f7f7f7;
}

/* Chip peque para % de avance de la meta corporativa */
.chip-meta{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  line-height:1;
  background:#F5F7FA;
  color:var(--c-secondary);
}
.chip-meta span.value{
  color:var(--c-accent);
  font-weight:700;
}


    
    
    .task-status-toggle{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin:4px 0 8px;
}
.status-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 10px;
  border-radius:999px;
  border:1px solid var(--line);
  font-size:12px;
  cursor:pointer;
  background:#F5F7FA;
}
.status-pill input{
  accent-color:var(--c-accent);
}
.status-pill:hover{
  background:#fff;
}








.person-tasks-toggle{
  margin-top:10px;
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:13px;
  color:var(--c-accent);
  background:transparent;
  border:none;
  padding:4px 0;
  cursor:pointer;
}

.person-task-list.is-collapsed{
  display:none;
}

.caret-orange{
  border-right:2px solid var(--c-accent);
  border-bottom:2px solid var(--c-accent);
}





/* Lista de tareas personales (colapsable) */
.person-task-list {
  display: none; /* por defecto, oculto */
}

.person-task-list.is-open {
  display: flex;            /* visible */
  flex-direction: column;
  gap: 8px;
}

/* ===== Mobile Responsiveness for Time & Attendance Tab ===== */
@media (max-width: 768px) {
  /* Hero Header - Stack vertically on mobile */
  .tab-content section > div:first-child {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: var(--space-3);
  }

  /* Hide refresh button on very small screens, show icon only */
  .tab-content button:first-of-type {
    padding: 10px 16px;
    font-size: 18px;
  }

  /* KPI Grid - 2 columns on mobile */
  .tab-content > section:first-child > div:nth-child(2) {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: var(--space-3) !important;
  }

  /* Make KPI cards slightly smaller on mobile */
  .tab-content > section:first-child > div:nth-child(2) > div {
    padding: var(--space-4) !important;
  }

  .tab-content > section:first-child > div:nth-child(2) > div > div:nth-child(3) {
    font-size: 28px !important;
  }

  /* Search and filter controls - Stack on mobile */
  .tab-content h2 + div {
    flex-direction: column !important;
    width: 100%;
  }

  .tab-content h2 + div > input,
  .tab-content h2 + div > select {
    width: 100%;
  }

  /* Attendance list items - Simplify layout on mobile */
  .tab-content > section:first-child > div:last-child > div > div > div {
    flex-direction: column !important;
    gap: var(--space-3) !important;
  }

  .tab-content > section:first-child > div:last-child > div > div > div > div:last-child {
    width: 100%;
    justify-content: space-between !important;
  }

  /* Hide "Ver detalles" button text on small mobile, show icon only */
  .tab-content button:contains("Ver detalles") {
    padding: 8px 12px;
    font-size: 16px;
  }

  /* Pattern analysis - Single column on mobile */
  .tab-content > section:nth-child(2) > div:last-child {
    grid-template-columns: 1fr !important;
    gap: var(--space-4) !important;
  }

  /* Permissions and vacations - Single column on mobile */
  .tab-content > section:last-child > div {
    grid-template-columns: 1fr !important;
    gap: var(--space-4) !important;
  }

  /* Reduce padding on mobile for sections */
  .tab-content section {
    margin-bottom: var(--space-6) !important;
  }

  .tab-content > section > div {
    padding: var(--space-4) !important;
  }

  /* Font size adjustments for mobile readability */
  .tab-content h1 {
    font-size: 22px !important;
  }

  .tab-content h2 {
    font-size: 18px !important;
  }

  .tab-content h3 {
    font-size: 15px !important;
  }
}

/* Extra small mobile devices (320px - 480px) */
@media (max-width: 480px) {
  /* KPI Grid - Single column on very small screens */
  .tab-content > section:first-child > div:nth-child(2) {
    grid-template-columns: 1fr !important;
  }

  /* Reduce decorative elements on tiny screens */
  .tab-content > section:first-child > div:nth-child(2) > div > div:first-child {
    display: none !important; /* Hide decorative circles */
  }

  /* Compact attendance items further */
  .tab-content > section:first-child > div:last-child > div > div > div {
    padding: var(--space-3) !important;
  }

  /* Hide check-out times on very small screens to save space */
  .tab-content > section:first-child > div:last-child > div > div > div > div:last-child > div:nth-child(2) {
    display: none !important;
  }

  /* Stack approval buttons vertically on tiny screens */
  .tab-content section:last-child button {
    font-size: 11px !important;
    padding: 8px !important;
  }
}

/* Tablet landscape (768px - 1024px) */
@media (min-width: 768px) and (max-width: 1024px) {
  /* 3 column KPI grid on tablets */
  .tab-content > section:first-child > div:nth-child(2) {
    grid-template-columns: repeat(3, 1fr) !important;
  }

  /* Pattern analysis - Maintain 2 columns on tablet */
  .tab-content > section:nth-child(2) > div:last-child {
    grid-template-columns: repeat(2, 1fr) !important;
  }

  /* Permissions and vacations - 2 columns on tablet */
  .tab-content > section:last-child > div {
    grid-template-columns: repeat(2, 1fr) !important;
  }
}

/* Print styles for reports */
@media print {
  /* Hide interactive elements when printing */
  .tab-content button,
  .tab-content input,
  .tab-content select {
    display: none !important;
  }

  /* Remove decorative elements */
  .tab-content > section:first-child > div:nth-child(2) > div > div:first-child {
    display: none !important;
  }

  /* Ensure good contrast for printing */
  .tab-content {
    color: #000 !important;
    background: #fff !important;
  }

  /* Remove shadows and gradients */
  .tab-content * {
    box-shadow: none !important;
    background-image: none !important;
  }

  /* Page breaks */
  .tab-content section {
    page-break-inside: avoid;
  }
}

/* ============================================
   NOTIFICATION SYSTEM - TOP-TECH DESIGN
   Inspired by: Revolut, Apple, Meta, Notion, Linear
   ============================================ */

@keyframes pulse-badge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

@keyframes slideInNotificationDrawer {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes fadeInOverlay {
  from { opacity: 0; }
  to { opacity: 1; }
}

.notification-center {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 420px;
  max-width: 90vw;
  background: white;
  box-shadow: -8px 0 32px rgba(0, 0, 0, 0.12);
  z-index: 10001;
  display: flex;
  flex-direction: column;
  animation: slideInNotificationDrawer 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  z-index: 10000;
  animation: fadeInOverlay 0.3s ease;
}

.notif-header {
  padding: 24px;
  border-bottom: 1px solid #F3F4F6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(180deg, #FAFBFC 0%, white 100%);
}

.notif-body {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}

.notif-body::-webkit-scrollbar {
  width: 6px;
}

.notif-body::-webkit-scrollbar-thumb {
  background: #D1D5DB;
  border-radius: 3px;
}

.notif-group {
  margin-bottom: 0;
}

.notif-group-header {
  padding: 16px 24px 8px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6B7280;
  background: #FAFBFC;
  position: sticky;
  top: 0;
  z-index: 10;
}

.notif-item {
  padding: 16px 24px;
  border-bottom: 1px solid #F3F4F6;
  transition: all 0.2s ease;
  cursor: pointer;
  position: relative;
}

.notif-item:hover {
  background: #F9FAFB;
}

.notif-item.unread {
  background: linear-gradient(90deg, rgba(239, 127, 27, 0.03) 0%, transparent 100%);
  border-left: 3px solid #EF7F1B;
}

.notif-item.unread::before {
  content: '';
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 8px;
  height: 8px;
  background: #EF7F1B;
  border-radius: 50%;
  box-shadow: 0 0 8px rgba(239, 127, 27, 0.4);
}

.notif-item.unread {
  padding-left: 36px;
}

.notif-empty {
  padding: 60px 24px;
  text-align: center;
  color: #9CA3AF;
}


  </style>
</head>
<body>
  
  
  <?php require __DIR__ . '/a-header-desktop-brand.php'; ?>

<!-- Tab Navigation -->
<nav class="tab-nav" role="navigation" aria-label="Performance sections">
  <div class="tab-nav-container">
    <a href="?tab=overview" class="tab-link <?= $active_tab === 'overview' ? 'is-active' : '' ?>">
      <span class="tab-icon">🏠</span>
      <span>Overview</span>
    </a>
    <a href="?tab=goals" class="tab-link <?= $active_tab === 'goals' ? 'is-active' : '' ?>" style="position: relative;">
      <span class="tab-icon">🎯</span>
      <span>Metas</span>
      <?php if ($unread_notifications_count > 0): ?>
        <span style="
          position: absolute;
          top: 8px;
          right: -8px;
          background: linear-gradient(135deg, #FF3B6D, #FF6B35);
          color: white;
          font-size: 10px;
          font-weight: 700;
          min-width: 18px;
          height: 18px;
          border-radius: 9px;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 0 5px;
          box-shadow: 0 2px 8px rgba(255, 59, 109, 0.4);
          animation: pulse-badge 2s ease-in-out infinite;
        ">
          <?= $unread_notifications_count > 99 ? '99+' : $unread_notifications_count ?>
        </span>
      <?php endif; ?>
    </a>
    <a href="?tab=time" class="tab-link <?= $active_tab === 'time' ? 'is-active' : '' ?>" style="position: relative;">
      <span class="tab-icon">⏱️</span>
      <span>Tiempo & Asistencia</span>
      <?php if ($solicitudes_pendientes_count > 0): ?>
        <span id="solicitudesPendientesBadge" style="
          position: absolute;
          top: 8px;
          right: -8px;
          background: linear-gradient(135deg, #EF4444, #DC2626);
          color: white;
          font-size: 10px;
          font-weight: 700;
          min-width: 18px;
          height: 18px;
          border-radius: 9px;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 0 5px;
          box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
          animation: pulse-badge 2s ease-in-out infinite;
        ">
          <?= $solicitudes_pendientes_count > 9 ? '9+' : $solicitudes_pendientes_count ?>
        </span>
      <?php endif; ?>
    </a>
    <a href="?tab=projects" class="tab-link <?= $active_tab === 'projects' ? 'is-active' : '' ?>">
      <span class="tab-icon">📋</span>
      <span>Proyectos</span>
    </a>
  </div>
</nav>

<!-- ============================================
     NOTIFICATION CENTER (Top-Tech Design)
     ============================================ -->

<!-- Notification Bell Button (Fixed, solo visible en Goals tab) -->
<?php if ($active_tab === 'goals'): ?>
<button
  id="notificationBellBtn"
  onclick="toggleNotificationCenter()"
  style="
    position: fixed;
    bottom: 32px;
    right: 32px;
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #EF7F1B 0%, #FFB020 100%);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(239, 127, 27, 0.3);
    transition: all 0.3s ease;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
  "
  onmouseover="this.style.transform='scale(1.1) rotate(12deg)'; this.style.boxShadow='0 12px 32px rgba(239, 127, 27, 0.4)'"
  onmouseout="this.style.transform='scale(1) rotate(0deg)'; this.style.boxShadow='0 8px 24px rgba(239, 127, 27, 0.3)'"
>
  🔔
  <?php if ($unread_notifications_count > 0): ?>
    <span style="
      position: absolute;
      top: -2px;
      right: -2px;
      background: #FF3B6D;
      color: white;
      font-size: 11px;
      font-weight: 700;
      min-width: 20px;
      height: 20px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 6px;
      border: 2px solid white;
      box-shadow: 0 2px 8px rgba(255, 59, 109, 0.4);
    ">
      <?= $unread_notifications_count > 99 ? '99+' : $unread_notifications_count ?>
    </span>
  <?php endif; ?>
</button>
<?php endif; ?>

<!-- Notification Drawer (Hidden by default) -->
<div id="notificationOverlay" class="notification-overlay" style="display: none;" onclick="closeNotificationCenter()"></div>

<div id="notificationCenter" class="notification-center" style="display: none;">

  <!-- Header -->
  <div class="notif-header">
    <div>
      <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #012133;">
        🔔 Notificaciones
      </h2>
      <p style="margin: 4px 0 0; font-size: 13px; color: #6B7280;">
        <?= $unread_notifications_count ?> sin leer
      </p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
      <!-- Mark all as read -->
      <?php if ($unread_notifications_count > 0): ?>
        <button
          onclick="markAllAsRead(event)"
          style="
            padding: 8px 12px;
            background: #F3F4F6;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s;
          "
          onmouseover="this.style.background='#E5E7EB'"
          onmouseout="this.style.background='#F3F4F6'"
        >
          Marcar todas
        </button>
      <?php endif; ?>
      <!-- Close button -->
      <button
        onclick="closeNotificationCenter()"
        style="
          width: 32px;
          height: 32px;
          background: transparent;
          border: none;
          border-radius: 6px;
          font-size: 20px;
          cursor: pointer;
          transition: all 0.2s;
          display: flex;
          align-items: center;
          justify-content: center;
        "
        onmouseover="this.style.background='#F3F4F6'"
        onmouseout="this.style.background='transparent'"
      >
        ×
      </button>
    </div>
  </div>

  <!-- Body -->
  <div class="notif-body">
    <?php if (empty($notifications)): ?>
      <!-- Empty State -->
      <div class="notif-empty">
        <div style="font-size: 64px; margin-bottom: 16px; opacity: 0.5;">🔕</div>
        <p style="font-size: 16px; font-weight: 600; color: #6B7280; margin: 0 0 8px;">
          No hay notificaciones
        </p>
        <p style="font-size: 14px; color: #9CA3AF; margin: 0;">
          Te avisaremos cuando tu equipo cree nuevas metas
        </p>
      </div>
    <?php else: ?>
      <!-- Grouped Notifications -->
      <?php foreach ($notifications_grouped as $group_name => $group_notifs): ?>
        <div class="notif-group">
          <div class="notif-group-header">
            <?= h($group_name) ?>
          </div>
          <?php foreach ($group_notifs as $notif): ?>
            <div
              class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>"
              onclick="handleNotificationClick(<?= $notif['id'] ?>, '<?= h($notif['action_url'] ?? '') ?>', <?= $notif['is_read'] ? 'true' : 'false' ?>)"
            >
              <!-- Icon & Content -->
              <div style="display: flex; gap: 12px;">
                <!-- Icon -->
                <div style="
                  width: 40px;
                  height: 40px;
                  border-radius: 50%;
                  background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-size: 20px;
                  flex-shrink: 0;
                ">
                  <?= $notif['icon'] ?>
                </div>

                <!-- Content -->
                <div style="flex: 1; min-width: 0;">
                  <!-- Title -->
                  <div style="font-size: 14px; font-weight: 600; color: #012133; margin-bottom: 4px; line-height: 1.4;">
                    <?= h($notif['title']) ?>
                  </div>

                  <!-- Message -->
                  <?php if ($notif['message']): ?>
                    <div style="font-size: 13px; color: #6B7280; margin-bottom: 8px; line-height: 1.5;">
                      <?= h($notif['message']) ?>
                    </div>
                  <?php endif; ?>

                  <!-- Footer: Time + Action -->
                  <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <!-- Time ago -->
                    <div style="font-size: 12px; color: #9CA3AF;">
                      <?php
                        $time_ago = time() - strtotime($notif['created_at']);
                        if ($time_ago < 60) {
                          echo 'Hace un momento';
                        } elseif ($time_ago < 3600) {
                          echo 'Hace ' . floor($time_ago / 60) . ' min';
                        } elseif ($time_ago < 86400) {
                          echo 'Hace ' . floor($time_ago / 3600) . ' h';
                        } else {
                          echo date('d M', strtotime($notif['created_at']));
                        }
                      ?>
                    </div>

                    <!-- Action Button -->
                    <?php if ($notif['action_label']): ?>
                      <div style="
                        padding: 4px 10px;
                        background: #EF7F1B;
                        color: white;
                        border-radius: 4px;
                        font-size: 11px;
                        font-weight: 600;
                        white-space: nowrap;
                      ">
                        <?= h($notif['action_label']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
// Toggle Notification Center
function toggleNotificationCenter() {
  const center = document.getElementById('notificationCenter');
  const overlay = document.getElementById('notificationOverlay');

  if (center.style.display === 'none') {
    center.style.display = 'flex';
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
  } else {
    closeNotificationCenter();
  }
}

// Close Notification Center
function closeNotificationCenter() {
  const center = document.getElementById('notificationCenter');
  const overlay = document.getElementById('notificationOverlay');

  center.style.display = 'none';
  overlay.style.display = 'none';
  document.body.style.overflow = 'auto';
}

// Handle notification click
async function handleNotificationClick(notifId, actionUrl, isRead) {
  // Mark as read if not already
  if (!isRead) {
    try {
      await fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'mark_notification_read',
          notification_id: notifId,
          ajax: '1'
        })
      });
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  }

  // Navigate to action URL
  if (actionUrl) {
    window.location.href = actionUrl;
  }
}

// Mark all notifications as read
async function markAllAsRead(event) {
  event.stopPropagation();

  try {
    const response = await fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'mark_all_notifications_read',
        ajax: '1'
      })
    });

    if (response.ok) {
      // Reload page to update UI
      window.location.reload();
    }
  } catch (error) {
    console.error('Error marking all notifications as read:', error);
  }
}

// ESC key to close
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeNotificationCenter();
  }
});
</script>

<div class="wrap">

<!-- ============ TAB 1: OVERVIEW ============ -->
<div class="tab-content <?= $active_tab === 'overview' ? 'is-active' : '' ?>">

  <?php if (!$v2_features_available): ?>
  <!-- Setup Notice -->
  <div style="background: #FFF4E5; border: 2px solid #FFB020; border-radius: 12px; padding: var(--space-6); margin: var(--space-6) 0;">
    <div style="display: flex; align-items: flex-start; gap: var(--space-4);">
      <div style="font-size: 32px;">⚠️</div>
      <div>
        <h3 style="color: #FF8800; margin: 0 0 var(--space-2); font-size: 18px; font-weight: 700;">
          Setup Pendiente: Ejecuta el SQL Schema
        </h3>
        <p style="color: #66422B; margin: 0 0 var(--space-3); line-height: 1.6;">
          Para activar el <strong>Tab Overview</strong> con alertas de riesgo proactivas, velocidad del equipo y analytics avanzados,
          necesitas ejecutar el SQL schema en phpMyAdmin.
        </p>
        <div style="background: white; padding: var(--space-4); border-radius: 8px; border: 1px solid #FFD699;">
          <p style="font-weight: 600; margin: 0 0 var(--space-2); color: #012133;">📋 Pasos:</p>
          <ol style="margin: 0; padding-left: var(--space-5); color: #66422B; line-height: 1.8;">
            <li>Ve a <strong>phpMyAdmin</strong></li>
            <li>Selecciona la base de datos: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 4px;">mevytjyn_webapp_test</code></li>
            <li>Pestaña <strong>SQL</strong></li>
            <li>Ejecuta el archivo: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 4px;">sql/performance_time_tracking_fixed.sql</code></li>
          </ol>
        </div>
        <p style="margin: var(--space-3) 0 0; color: #66422B; font-size: 13px;">
          💡 Mientras tanto, puedes usar el <strong>Tab "Metas"</strong> que funciona perfectamente.
        </p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php
  // ============ Executive Insight Calculations ============
  $current_completion = $global_completion;
  $trend_direction = $current_completion >= 70 ? 'up' : ($current_completion >= 50 ? 'stable' : 'down');
  $trend_icon = $trend_direction === 'up' ? '📈' : ($trend_direction === 'stable' ? '➡️' : '📉');
  $trend_color = $trend_direction === 'up' ? '#00D98F' : ($trend_direction === 'stable' ? '#FFB020' : '#FF3B6D');

  // Generate automated insight
  $insight_message = '';
  $insight_action = '';

  if ($at_risk_goals > 0 && $trend_direction === 'down') {
    $insight_message = "Alerta: $at_risk_goals metas en riesgo con tendencia descendente";
    $insight_action = "Prioriza reuniones 1-on-1 con el equipo para identificar blockers";
  } elseif ($metas_overdue_count > 0) {
    $insight_message = "$metas_overdue_count metas vencidas requieren atención inmediata";
    $insight_action = "Revisa con los responsables: ¿necesitan más recursos o ajustar plazos?";
  } elseif ($trend_direction === 'up' && $current_completion >= 70) {
    $insight_message = "¡Excelente! El equipo mantiene momentum con $current_completion% de avance";
    $insight_action = "Reconoce públicamente el esfuerzo para mantener la motivación alta";
  } else {
    $insight_message = "El equipo avanza a ritmo moderado ($current_completion% completado)";
    $insight_action = "Identifica quick wins para acelerar el progreso y generar momentum";
  }
  ?>

  <!-- ============================================
       EXECUTIVE INSIGHT (Data Storytelling)
       ============================================ -->
  <section style="margin-bottom: var(--space-6);">
    <div style="
      background: linear-gradient(135deg, #EF7F1B 0%, #FFB020 100%);
      border-radius: 20px;
      padding: var(--space-8);
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(239, 127, 27, 0.25);
    ">
      <!-- Decorative circles -->
      <div style="
        position: absolute;
        top: -40px;
        right: -40px;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
      "></div>
      <div style="
        position: absolute;
        bottom: -60px;
        left: -60px;
        width: 250px;
        height: 250px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
      "></div>

      <div style="position: relative; z-index: 1;">
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4);">
          <div style="font-size: 32px;"><?= $trend_icon ?></div>
          <div>
            <h2 style="
              font-size: 24px;
              font-weight: 700;
              margin: 0 0 var(--space-1);
              color: white;
            ">
              Insight Ejecutivo
            </h2>
            <p style="
              font-size: 14px;
              opacity: 0.9;
              margin: 0;
            ">
              Análisis automatizado basado en tendencias y estado actual
            </p>
          </div>
        </div>

        <div style="
          background: rgba(255, 255, 255, 0.15);
          backdrop-filter: blur(10px);
          border-radius: 12px;
          padding: var(--space-5);
          border: 1px solid rgba(255, 255, 255, 0.2);
        ">
          <div style="font-size: 18px; font-weight: 600; margin-bottom: var(--space-3); line-height: 1.5;">
            <?= h($insight_message) ?>
          </div>
          <div style="display: flex; align-items: flex-start; gap: var(--space-2);">
            <div style="
              background: white;
              color: var(--c-accent);
              padding: 4px 8px;
              border-radius: 6px;
              font-size: 12px;
              font-weight: 700;
              text-transform: uppercase;
              letter-spacing: 0.05em;
            ">
              Acción
            </div>
            <div style="font-size: 15px; opacity: 0.95; line-height: 1.6; flex: 1;">
              <?= h($insight_action) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Hero KPIs -->
  <div style="
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-4);
    margin-top: var(--space-6);
    margin-bottom: var(--space-6);
  ">

    <!-- KPI 1: Global Completion -->
    <div style="
      background: linear-gradient(135deg, #00A3FF 0%, #00A3FF 100%);
      border-radius: 16px;
      padding: var(--space-5);
      color: white;
      position: relative;
      overflow: hidden;
    ">
      <div style="
        position: absolute;
        top: -20px;
        right: -20px;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
      "></div>
      <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
        📈 Cumplimiento Global
      </div>
      <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);" data-animate-number="<?= $global_completion ?>">
        <?= $global_completion ?>%
      </div>
      <div style="font-size: 12px; opacity: 0.8;">
        Progreso promedio de todas las metas
      </div>
    </div>

    <!-- KPI 2: At Risk Goals -->
    <div style="
      background: linear-gradient(135deg, <?= $at_risk_goals > 0 ? '#FFB020' : '#00D98F' ?> 0%, <?= $at_risk_goals > 0 ? '#EF7F1B' : '#00D98F' ?> 100%);
      border-radius: 16px;
      padding: var(--space-5);
      color: white;
      position: relative;
      overflow: hidden;
    ">
      <div style="
        position: absolute;
        top: -20px;
        right: -20px;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
      "></div>
      <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
        <?= $at_risk_goals > 0 ? '⚠️' : '✅' ?> Metas en Riesgo
      </div>
      <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);" data-animate-number="<?= $at_risk_goals ?>">
        <?= $at_risk_goals ?>
      </div>
      <div style="font-size: 12px; opacity: 0.8;">
        <?= $at_risk_goals > 0 ? 'Necesitan atención prioritaria' : 'Todo bajo control' ?>
      </div>
    </div>

    <!-- KPI 3: Team Velocity -->
    <div style="
      background: linear-gradient(135deg, #8B5CF6 0%, #8B5CF6 100%);
      border-radius: 16px;
      padding: var(--space-5);
      color: white;
      position: relative;
      overflow: hidden;
    ">
      <div style="
        position: absolute;
        top: -20px;
        right: -20px;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
      "></div>
      <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
        ⚡ Velocidad del Equipo
      </div>
      <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);" data-animate-number="<?= $avg_velocity ?>">
        <?= $avg_velocity ?>
      </div>
      <div style="font-size: 12px; opacity: 0.8;">
        Promedio del equipo por semana
      </div>
    </div>

    <!-- KPI 4: Team Energy -->
    <div style="
      background: linear-gradient(135deg, #10B981 0%, #10B981 100%);
      border-radius: 16px;
      padding: var(--space-5);
      color: white;
      position: relative;
      overflow: hidden;
    ">
      <div style="
        position: absolute;
        top: -20px;
        right: -20px;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
      "></div>
      <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
        🔋 Energía del Equipo
      </div>
      <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);" data-animate-number="<?= $energia_equipo ?>">
        <?= $energia_equipo ?>%
      </div>
      <div style="font-size: 12px; opacity: 0.8;">
        <?= $energia_status ?> • Motivación y bienestar
      </div>
    </div>

  </div>

  <!-- Risk Alerts Section -->
  <?php if (!empty($risk_alerts)): ?>
  <section style="margin-bottom: var(--space-8);">
    <div style="margin-bottom: var(--space-5); padding-bottom: var(--space-4); border-bottom: 2px solid #F3F4F6;">
      <h2 style="font-size: 20px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-2);">
        🚨 Alertas de Riesgo
      </h2>
      <p style="font-size: 14px; color: var(--c-body); opacity: 0.7; margin: 0;">
        Metas que requieren atención proactiva según plazo y progreso
      </p>
    </div>

    <div style="
      background: white;
      border-radius: 16px;
      padding: var(--space-6);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    ">
      <div style="display: flex; flex-direction: column; gap: var(--space-3);">
        <?php foreach (array_slice($risk_alerts, 0, 10) as $alert):
          $risk_level = $alert['risk_level'] ?? 'low';
          $days_left = (int)($alert['days_until_due'] ?? 0);
          $progress = (int)($alert['progress_pct'] ?? 0);

          // Colors based on risk level
          $badge_colors = [
            'high' => ['bg' => '#FEE2E2', 'text' => '#991B1B', 'border' => '#FF3B6D'],
            'medium' => ['bg' => '#FEF3C7', 'text' => '#8a4709', 'border' => '#FFB020'],
            'low' => ['bg' => '#DBEAFE', 'text' => '#1E40AF', 'border' => '#00A3FF']
          ];
          $colors = $badge_colors[$risk_level] ?? $badge_colors['low'];
        ?>
          <div style="
            background: white;
            border-left: 4px solid <?= $colors['border'] ?>;
            border-radius: 10px;
            padding: var(--space-4);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          ">
            <div style="display: flex; align-items: flex-start; gap: var(--space-3); flex-wrap: wrap;">
              <div style="
                padding: 4px 12px;
                background: <?= $colors['bg'] ?>;
                color: <?= $colors['text'] ?>;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
              ">
                <?= $risk_level === 'high' ? 'ALTA' : ($risk_level === 'medium' ? 'MEDIA' : 'BAJA') ?>
              </div>

              <div style="flex: 1; min-width: 200px;">
                <div style="font-weight: 600; color: var(--c-secondary); font-size: 15px; margin-bottom: 4px;">
                  <?= h($alert['descripcion'] ?? 'Meta sin descripción') ?>
                </div>
                <div style="font-size: 13px; color: var(--c-body); opacity: 0.8; margin-bottom: 8px;">
                  👤 <?= h($alert['nombre_persona'] ?? 'Sin asignar') ?>
                </div>

                <div style="display: flex; gap: var(--space-3); flex-wrap: wrap; align-items: center;">
                  <!-- Progress -->
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 100px; height: 6px; background: #E5E7EB; border-radius: 3px; overflow: hidden;">
                      <div style="
                        width: <?= $progress ?>%;
                        height: 100%;
                        background: <?= $progress >= 70 ? '#00D98F' : ($progress >= 30 ? '#FFB020' : '#FF3B6D') ?>;
                        border-radius: 3px;
                      "></div>
                    </div>
                    <span style="font-size: 12px; font-weight: 600; color: var(--c-secondary);">
                      <?= $progress ?>%
                    </span>
                  </div>

                  <!-- Deadline -->
                  <div style="
                    padding: 4px 10px;
                    background: <?= $colors['bg'] ?>;
                    border: 1px solid <?= $colors['border'] ?>;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    color: <?= $colors['text'] ?>;
                  ">
                    <?= $days_left < 0 ? '🔴 Vencida hace '.abs($days_left).' días' : '⏰ Vence en '.$days_left.' días' ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php else: ?>
  <section style="margin-bottom: var(--space-8);">
    <div style="
      background: white;
      border-radius: 16px;
      padding: var(--space-8);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      text-align: center;
    ">
      <div style="font-size: 48px; margin-bottom: var(--space-3);">✅</div>
      <h3 style="font-size: 18px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-2);">Todo bajo control</h3>
      <p style="font-size: 14px; color: var(--c-body); opacity: 0.7; margin: 0;">No hay metas en riesgo en este momento. ¡Excelente trabajo!</p>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================================
       GOAL ANALYTICS (Distribuciones)
       ============================================ -->
  <?php
  // Goal type breakdown
  $goals_by_type = [
    'empresa' => $metas_by_empresa_count ?? 0,
    'area' => $metas_by_area_count ?? 0,
    'personal' => $metas_by_person_count ?? 0
  ];
  $total_hierarchical = array_sum($goals_by_type);

  // Goal status breakdown
  $goals_by_status = [
    'completed' => $metas_completed_count ?? 0,
    'on_track' => $metas_on_track_count ?? 0,
    'at_risk' => $metas_at_risk_count ?? 0,
    'critical' => $metas_critical_count ?? 0,
    'overdue' => $metas_overdue_count ?? 0
  ];
  ?>
  <section style="margin-bottom: var(--space-8);">
    <div style="
      margin-bottom: var(--space-5);
      padding-bottom: var(--space-4);
      border-bottom: 2px solid #F3F4F6;
    ">
      <h2 style="font-size: 20px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
        📊 Análisis de Metas
      </h2>
      <p style="font-size: 14px; color: var(--c-body); opacity: 0.7; margin: 0;">
        Distribución y estado de todas las metas del equipo
      </p>
    </div>

    <div style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: var(--space-5);
    ">

      <!-- Goal Type Distribution -->
      <div style="
        background: white;
        border-radius: 16px;
        padding: var(--space-6);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #F3F4F6;
      ">
        <div style="margin-bottom: var(--space-5);">
          <h3 style="font-size: 16px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
            Distribución por Nivel
          </h3>
          <p style="font-size: 13px; color: #6B7280; margin: 0;">
            Metas organizadas por jerarquía
          </p>
        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-4);">

          <!-- Empresa Goals -->
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 13px; font-weight: 600; color: var(--c-secondary);">
                🏢 Empresa
              </span>
              <span style="font-size: 15px; font-weight: 700; color: var(--c-accent);">
                <?= $goals_by_type['empresa'] ?>
              </span>
            </div>
            <div style="
              width: 100%;
              height: 8px;
              background: #F3F4F6;
              border-radius: 4px;
              overflow: hidden;
            ">
              <div style="
                width: <?= $total_hierarchical > 0 ? ($goals_by_type['empresa'] / $total_hierarchical * 100) : 0 ?>%;
                height: 100%;
                background: linear-gradient(90deg, #EF7F1B, #FFB020);
                border-radius: 4px;
              "></div>
            </div>
          </div>

          <!-- Area Goals -->
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 13px; font-weight: 600; color: var(--c-secondary);">
                👥 Área
              </span>
              <span style="font-size: 15px; font-weight: 700; color: #8B5CF6;">
                <?= $goals_by_type['area'] ?>
              </span>
            </div>
            <div style="
              width: 100%;
              height: 8px;
              background: #F3F4F6;
              border-radius: 4px;
              overflow: hidden;
            ">
              <div style="
                width: <?= $total_hierarchical > 0 ? ($goals_by_type['area'] / $total_hierarchical * 100) : 0 ?>%;
                height: 100%;
                background: linear-gradient(90deg, #8B5CF6, #7C3AED);
                border-radius: 4px;
              "></div>
            </div>
          </div>

          <!-- Personal Goals -->
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-size: 13px; font-weight: 600; color: var(--c-secondary);">
                👤 Personal
              </span>
              <span style="font-size: 15px; font-weight: 700; color: #00A3FF;">
                <?= $goals_by_type['personal'] ?>
              </span>
            </div>
            <div style="
              width: 100%;
              height: 8px;
              background: #F3F4F6;
              border-radius: 4px;
              overflow: hidden;
            ">
              <div style="
                width: <?= $total_hierarchical > 0 ? ($goals_by_type['personal'] / $total_hierarchical * 100) : 0 ?>%;
                height: 100%;
                background: linear-gradient(90deg, #00A3FF, #0077FF);
                border-radius: 4px;
              "></div>
            </div>
          </div>

          <div style="
            margin-top: var(--space-2);
            padding-top: var(--space-3);
            border-top: 1px solid #F3F4F6;
            text-align: center;
          ">
            <div style="font-size: 12px; color: #6B7280;">
              Total: <strong style="color: var(--c-secondary);"><?= $total_hierarchical ?></strong> metas
            </div>
          </div>

        </div>
      </div>

      <!-- Goal Status Distribution -->
      <div style="
        background: white;
        border-radius: 16px;
        padding: var(--space-6);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #F3F4F6;
      ">
        <div style="margin-bottom: var(--space-5);">
          <h3 style="font-size: 16px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
            Distribución por Estado
          </h3>
          <p style="font-size: 13px; color: #6B7280; margin: 0;">
            Estado actual de todas las metas
          </p>
        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-3);">

          <?php
          $status_config = [
            'completed' => ['icon' => '✅', 'label' => 'Completadas', 'color' => '#00D98F'],
            'on_track' => ['icon' => '✅', 'label' => 'Encaminadas', 'color' => '#00D98F'],
            'at_risk' => ['icon' => '⚠️', 'label' => 'En Riesgo', 'color' => '#FFB020'],
            'critical' => ['icon' => '🔥', 'label' => 'Críticas', 'color' => '#FF3B6D'],
            'overdue' => ['icon' => '🚨', 'label' => 'Vencidas', 'color' => '#FF3B6D']
          ];

          foreach ($status_config as $key => $config):
            $count = $goals_by_status[$key];
          ?>
            <div style="
              display: flex;
              align-items: center;
              gap: var(--space-3);
              padding: var(--space-3);
              background: #F9FAFB;
              border-radius: 10px;
              border-left: 4px solid <?= $config['color'] ?>;
            ">
              <span style="font-size: 20px;"><?= $config['icon'] ?></span>
              <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600; color: var(--c-secondary);">
                  <?= $config['label'] ?>
                </div>
              </div>
              <div style="
                font-size: 20px;
                font-weight: 700;
                color: <?= $config['color'] ?>;
                min-width: 40px;
                text-align: right;
              ">
                <?= $count ?>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </div>

    </div>
  </section>

  <!-- Team Pulse -->
  <section style="margin-bottom: var(--space-8);">
    <div style="margin-bottom: var(--space-5); padding-bottom: var(--space-4); border-bottom: 2px solid #F3F4F6;">
      <h2 style="font-size: 20px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-2);">
        💪 Pulso del Equipo
      </h2>
      <p style="font-size: 14px; color: var(--c-body); opacity: 0.7; margin: 0;">
        Top performers y personas que necesitan apoyo
      </p>
    </div>

    <div style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: var(--space-4);
    ">

      <!-- Top Performers -->
      <div style="
        background: white;
        border-radius: 16px;
        padding: var(--space-6);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      ">
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
          <span style="font-size: 24px;">🏆</span>
          <h3 style="font-size: 16px; font-weight: 700; color: var(--c-secondary); margin: 0;">
            Top Performers (últimos 30 días)
          </h3>
        </div>
        <?php if (!empty($top_performers)): ?>
          <div style="display: flex; flex-direction: column; gap: var(--space-3);">
            <?php foreach ($top_performers as $idx => $perf): ?>
              <div style="
                display: flex;
                align-items: center;
                gap: var(--space-3);
                padding: var(--space-3);
                background: #F9FAFB;
                border-radius: 10px;
                border-left: 3px solid #00D98F;
              ">
                <div style="font-size: 20px; font-weight: 700; color: var(--c-accent); width: 28px;">
                  <?= $idx + 1 ?>
                </div>
                <div style="flex: 1;">
                  <div style="font-size: 14px; font-weight: 600; color: #1F2937;">
                    <?= h($perf['nombre_persona'] ?? 'N/A') ?>
                  </div>
                  <div style="font-size: 12px; color: #6B7280;">
                    <?= h($perf['cargo'] ?? 'N/A') ?>
                  </div>
                </div>
                <div style="text-align: right;">
                  <div style="font-size: 18px; font-weight: 700; color: #00D98F;">
                    <?= (int)($perf['completed_count'] ?? 0) ?>
                  </div>
                  <div style="font-size: 11px; color: #6B7280;">
                    metas logradas
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: var(--space-6); color: var(--c-body); opacity: 0.6;">
            <div style="font-size: 48px; margin-bottom: var(--space-2);">📊</div>
            <p style="font-size: 13px; margin: 0;">No hay datos de los últimos 30 días</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Needs Attention -->
      <div style="
        background: white;
        border-radius: 16px;
        padding: var(--space-6);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      ">
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
          <span style="font-size: 24px;">🆘</span>
          <h3 style="font-size: 16px; font-weight: 700; color: var(--c-secondary); margin: 0;">
            Necesitan Atención
          </h3>
        </div>
        <?php if (!empty($needs_attention)): ?>
          <div style="display: flex; flex-direction: column; gap: var(--space-3);">
            <?php foreach ($needs_attention as $person): ?>
              <div style="
                display: flex;
                align-items: center;
                gap: var(--space-3);
                padding: var(--space-3);
                background: #FEF3C7;
                border-radius: 10px;
                border-left: 3px solid #FFB020;
              ">
                <div style="flex: 1;">
                  <div style="font-size: 14px; font-weight: 600; color: #1F2937;">
                    <?= h($person['nombre_persona'] ?? 'N/A') ?>
                  </div>
                  <div style="font-size: 12px; color: #6B7280;">
                    <?= h($person['cargo'] ?? 'N/A') ?>
                  </div>
                </div>
                <div style="text-align: right;">
                  <div style="font-size: 18px; font-weight: 700; color: #FF3B6D;">
                    <?= (int)($person['stalled_count'] ?? 0) ?>
                  </div>
                  <div style="font-size: 11px; color: #6B7280;">
                    metas estancadas
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: var(--space-6); color: var(--c-body); opacity: 0.6;">
            <div style="font-size: 48px; margin-bottom: var(--space-2);">✨</div>
            <p style="font-size: 13px; margin: 0;">Todo el equipo está avanzando bien</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </section>

</div>

<!-- ============ TAB 2: GOALS (Rediseñado UX/UI Senior) ============ -->
<div class="tab-content <?= $active_tab === 'goals' ? 'is-active' : '' ?>">

<section class="metas-layout" style="margin-top: var(--space-6);">

  <!-- Mensajes de feedback -->
  <?php if (isset($_SESSION['meta_success'])): ?>
    <div style="
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border-left: 4px solid #00D98F;
      padding: var(--space-4);
      border-radius: 12px;
      margin-bottom: var(--space-5);
      display: flex;
      align-items: center;
      gap: var(--space-3);
    ">
      <div style="font-size: 24px;">✅</div>
      <div style="flex: 1;">
        <div style="font-weight: 600; color: #047857; margin-bottom: 4px;">¡Éxito!</div>
        <div style="font-size: 14px; color: #065f46;"><?= htmlspecialchars($_SESSION['meta_success']) ?></div>
      </div>
    </div>
    <?php unset($_SESSION['meta_success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['meta_error'])): ?>
    <div style="
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      border-left: 4px solid #FF3B6D;
      padding: var(--space-4);
      border-radius: 12px;
      margin-bottom: var(--space-5);
      display: flex;
      align-items: center;
      gap: var(--space-3);
    ">
      <div style="font-size: 24px;">⚠️</div>
      <div style="flex: 1;">
        <div style="font-weight: 600; color: #991b1b; margin-bottom: 4px;">Error</div>
        <div style="font-size: 14px; color: #7f1d1d;"><?= htmlspecialchars($_SESSION['meta_error']) ?></div>
      </div>
    </div>
    <?php unset($_SESSION['meta_error']); ?>
  <?php endif; ?>

  <!-- Hero: Metas & OKRs Dashboard -->
  <div style="margin-bottom: var(--space-6);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-5); flex-wrap: wrap; gap: var(--space-3);">
      <div>
        <h1 style="font-size: 28px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          🎯 Metas & OKRs
        </h1>
        <p style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
          Q1 2026 • <?= $metas_total_count ?> metas activas • Última actualización: ahora
        </p>
      </div>
      <button
        onclick="openCrearMetaModal()"
        style="
          padding: 12px 24px;
          background: linear-gradient(135deg, var(--c-accent) 0%, #FF6B35 100%);
          border: none;
          border-radius: 8px;
          color: white;
          font-weight: 600;
          font-size: 14px;
          cursor: pointer;
          box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
          transition: all 0.3s ease;
        "
        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 136, 0, 0.3)'"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 136, 0, 0.2)'"
      >
        ✨ Crear Meta
      </button>

    </div>

    <!-- KPIs Hero Grid -->
    <div style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: var(--space-4);
      margin-bottom: var(--space-6);
    ">
      <!-- 1. KPI: Avance Global -->
      <div style="
        background: linear-gradient(135deg, #00A3FF 0%, #00A3FF 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          📊 Avance Global
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $global_completion ?>%
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Progreso general de tus metas
        </div>
      </div>

      <!-- 2. KPI: Encaminadas -->
      <div style="
        background: linear-gradient(135deg, #00D98F 0%, #00D98F 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          ✅ Encaminadas
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $metas_on_track_count ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Más del 70% o sin urgencia
        </div>
      </div>

      <!-- 3. KPI: En Riesgo -->
      <div style="
        background: linear-gradient(135deg, #FFB020 0%, #EF7F1B 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          ⚠️ En Riesgo
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $metas_at_risk_count ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Menos del 70% • Vencen en 7 días
        </div>
      </div>

      <!-- 4. KPI: Críticas -->
      <div style="
        background: linear-gradient(135deg, #FF3B6D 0%, #991B1B 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          🔥 Críticas
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $metas_critical_count ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Menos del 50% • Vencen en 3 días
        </div>
      </div>

      <!-- 5. KPI: Vencidas -->
      <div style="
        background: linear-gradient(135deg, #FF3B6D 0%, #FF3B6D 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          🚨 Vencidas
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $metas_overdue_count ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Pasaron su fecha límite
        </div>
      </div>
    </div>

  <!-- ===============================================
       🚀 VISUALIZACIÓN JERÁRQUICA V2.0 - TOP INDUSTRY LEVEL
       Basado en mejores prácticas de Linear, Asana, Monday 2026
       =============================================== -->

  <div style="margin: var(--space-8) 0;">

    <!-- ============ ACTIONS BAR ============ -->
    <div style="
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: var(--space-6);
      gap: var(--space-3);
    ">
        <!-- View Switcher -->
        <div style="
          display: inline-flex;
          background: white;
          border: 1px solid #E5E7EB;
          border-radius: 10px;
          padding: 4px;
          gap: 4px;
        ">
          <button
            id="viewGridBtn"
            onclick="switchView('grid')"
            style="
              padding: 8px 14px;
              background: linear-gradient(135deg, #EF7F1B, #FFB020);
              color: white;
              border: none;
              border-radius: 7px;
              font-size: 13px;
              font-weight: 600;
              cursor: pointer;
              display: flex;
              align-items: center;
              gap: 6px;
              transition: all 0.2s;
            "
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            Grid
          </button>
          <button
            id="viewTableBtn"
            onclick="switchView('table')"
            style="
              padding: 8px 14px;
              background: transparent;
              color: #6B7280;
              border: none;
              border-radius: 7px;
              font-size: 13px;
              font-weight: 600;
              cursor: pointer;
              display: flex;
              align-items: center;
              gap: 6px;
              transition: all 0.2s;
            "
            onmouseover="if(!this.classList.contains('active')) this.style.background='#F3F4F6'"
            onmouseout="if(!this.classList.contains('active')) this.style.background='transparent'"
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Table
          </button>
        </div>
    </div>

    <?php if (empty($metas_corporativas)): ?>
      <!-- Empty State -->
      <div style="
        text-align: center;
        padding: var(--space-12) var(--space-8);
        background: white;
        border-radius: 20px;
        border: 2px dashed #D1D5DB;
        margin: var(--space-6) 0;
      ">
        <div style="font-size: 64px; margin-bottom: var(--space-4); opacity: 0.4;">🎯</div>
        <h3 style="
          font-size: 20px;
          font-weight: 700;
          color: var(--c-secondary);
          margin: 0 0 var(--space-2);
        ">
          No hay metas definidas aún
        </h3>
        <p style="
          font-size: 14px;
          color: var(--c-body);
          opacity: 0.7;
          margin: 0 0 var(--space-5);
          max-width: 400px;
          margin-left: auto;
          margin-right: auto;
        ">
          Las metas ya registradas en la base de datos se visualizarán aquí.
        </p>
      </div>

    <?php else: ?>

      <!-- ============ GRID VIEW (DEFAULT) ============ -->
      <div id="gridView" style="
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
        gap: var(--space-5);
        margin-top: var(--space-6);
      ">
        <?php foreach ($metas_corporativas as $meta):
          $pctCorp = clamp_pct(pct_corporativa($meta));
          $daysToDue = days_to_due($meta['due_date']);

          // Status determination
          $statusColor = '#00A3FF';
          $statusBg = '#EFF6FF';
          $statusText = 'En curso';
          $statusIcon = '⟳';

          if ($pctCorp >= 100) {
            $statusColor = '#00D98F';
            $statusBg = '#D1FAE5';
            $statusText = 'Completada';
            $statusIcon = '✓';
          } elseif ($daysToDue !== null && $daysToDue < 0) {
            $statusColor = '#FF3B6D';
            $statusBg = '#FEE2E2';
            $statusText = 'Vencida';
            $statusIcon = '!';
          } elseif ($daysToDue !== null && $daysToDue <= 7) {
            $statusColor = '#FFB020';
            $statusBg = '#FEF3C7';
            $statusText = 'Próxima';
            $statusIcon = '⚠';
          }

          // Compact circle
          $circumference = 2 * M_PI * 36; // smaller radius
          $offset = $circumference - ($pctCorp / 100) * $circumference;
        ?>

          <!-- Meta Card - Compact Version -->
          <div style="
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            border: 1px solid #F3F4F6;
            transition: all 0.2s ease;
            overflow: hidden;
            position: relative;
          "
          onmouseover="this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'; this.style.transform='translateY(-2px)'"
          onmouseout="this.style.boxShadow='0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06)'; this.style.transform='translateY(0)'"
          >
            <!-- Accent bar -->
            <div style="
              height: 3px;
              background: linear-gradient(90deg, #EF7F1B 0%, #FFB020 50%, #FFB020 100%);
            "></div>

            <!-- Card Content -->
            <div style="padding: var(--space-5);">

              <!-- Header Row: Icon + Title + Progress Circle -->
              <div style="display: flex; gap: var(--space-4); margin-bottom: var(--space-4);">

                <!-- Icon -->
                <div style="
                  width: 48px;
                  height: 48px;
                  background: linear-gradient(135deg, #EF7F1B, #FFB020);
                  border-radius: 12px;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-size: 24px;
                  flex-shrink: 0;
                  box-shadow: 0 4px 12px rgba(239, 127, 27, 0.3);
                ">🏢</div>

                <!-- Title & Meta -->
                <div style="flex: 1; min-width: 0;">
                  <h3 style="
                    font-size: 17px;
                    font-weight: 700;
                    color: var(--c-secondary);
                    margin: 0 0 8px;
                    line-height: 1.3;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                  "><?= h($meta['descripcion']) ?></h3>

                  <!-- Inline badges -->
                  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <!-- Status -->
                    <span style="
                      display: inline-flex;
                      align-items: center;
                      gap: 4px;
                      padding: 4px 10px;
                      background: <?= $statusBg ?>;
                      border-radius: 6px;
                      font-size: 11px;
                      font-weight: 600;
                      color: <?= $statusColor ?>;
                    "><?= $statusIcon ?> <?= $statusText ?></span>

                    <!-- Date -->
                    <span style="
                      display: inline-flex;
                      align-items: center;
                      gap: 4px;
                      padding: 4px 10px;
                      background: #F9FAFB;
                      border-radius: 6px;
                      font-size: 11px;
                      font-weight: 500;
                      color: #6B7280;
                    ">📅 <?= fmt_date($meta['due_date']) ?></span>
                  </div>
                </div>

                <!-- Compact Progress Circle -->
                <div style="position: relative; width: 72px; height: 72px; flex-shrink: 0;">
                  <svg viewBox="0 0 80 80" style="transform: rotate(-90deg); width: 100%; height: 100%;">
                    <circle cx="40" cy="40" r="36" fill="none" stroke="#F3F4F6" stroke-width="7"></circle>
                    <circle
                      cx="40"
                      cy="40"
                      r="36"
                      fill="none"
                      stroke="<?= $statusColor ?>"
                      stroke-width="7"
                      stroke-linecap="round"
                      stroke-dasharray="<?= $circumference ?>"
                      stroke-dashoffset="<?= $offset ?>"
                      style="transition: stroke-dashoffset 0.6s ease;"
                    ></circle>
                  </svg>
                  <div style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                  ">
                    <div style="
                      font-size: 18px;
                      font-weight: 800;
                      color: var(--c-secondary);
                      line-height: 1;
                    "><?= $pctCorp ?>%</div>
                  </div>
                </div>
              </div>


                <!-- Áreas -->
                <?php if (!empty($meta['areas'])): ?>
                <div
                  style="
                    display: flex;
                    flex-direction: column;
                    gap: var(--space-3);
                    margin-top: var(--space-4);
                  "
                >
                  <?php foreach ($meta['areas'] as $area):
                    $pctArea = clamp_pct(pct_area($area));
                    $totalMetasArea = count($area['metas_area'] ?? []);
                    $totalPersonasArea = 0;
                    foreach ($area['metas_area'] ?? [] as $ma) {
                      $totalPersonasArea += count($ma['personales'] ?? []);
                    }
                  ?>

                    <!-- Area Compact Card -->
                    <div style="
                      background: white;
                      border: 1px solid #E5E7EB;
                      border-radius: 10px;
                      padding: var(--space-4);
                      transition: all 0.2s;
                      cursor: pointer;
                    "
                    onclick="toggleAreaDetails(<?= $area['id'] ?>)"
                    onmouseover="this.style.borderColor='#EF7F1B'; this.style.boxShadow='0 2px 8px rgba(239, 127, 27, 0.15)'"
                    onmouseout="this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"
                    >
                      <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-3);">
                        <!-- Area Icon -->
                        <div style="
                          width: 36px;
                          height: 36px;
                          background: linear-gradient(135deg, #FFB020, #EF7F1B);
                          border-radius: 8px;
                          display: flex;
                          align-items: center;
                          justify-content: center;
                          color: white;
                          font-weight: 700;
                          font-size: 14px;
                          flex-shrink: 0;
                        "><?= h(mb_substr($area['nombre_area'], 0, 1, 'UTF-8')) ?></div>

                        <!-- Area Info -->
                        <div style="flex: 1; min-width: 0;">
                          <div style="
                            font-size: 14px;
                            font-weight: 600;
                            color: #1F2937;
                            margin-bottom: 2px;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                          "><?= h($area['nombre_area']) ?></div>
                          <div style="
                            font-size: 11px;
                            color: #9CA3AF;
                          "><?= $totalMetasArea ?> metas · <?= $totalPersonasArea ?> personas</div>
                        </div>

                        <!-- Progress -->
                        <div style="text-align: right; flex-shrink: 0;">
                          <div style="
                            font-size: 18px;
                            font-weight: 700;
                            color: #EF7F1B;
                            line-height: 1;
                          "><?= $pctArea ?>%</div>
                        </div>
                      </div>

                      <!-- Progress Bar -->
                      <div style="
                        width: 100%;
                        height: 6px;
                        background: #F3F4F6;
                        border-radius: 9999px;
                        overflow: hidden;
                      ">
                        <div style="
                          height: 100%;
                          width: <?= $pctArea ?>%;
                          background: linear-gradient(90deg, #EF7F1B, #FFB020);
                          border-radius: 9999px;
                          transition: width 0.4s ease;
                        "></div>
                      </div>

                      <!-- Area Details (Initially Hidden) -->
                      <div
                        id="area-details-<?= $area['id'] ?>"
                        style="display: none; margin-top: var(--space-3); padding-top: var(--space-3); border-top: 1px solid #F3F4F6;"
                      >
                        <!-- Metas de área -->
                        <?php foreach ($area['metas_area'] as $ma):
                          $pctMetaArea = clamp_pct(pct_meta_area($ma));
                        ?>
                          <div style="
                            padding: var(--space-2) 0;
                            border-bottom: 1px solid #F9FAFB;
                          ">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                              <span style="font-size: 12px; font-weight: 500; color: #374151; flex: 1;">
                                <?= h($ma['descripcion']) ?>
                              </span>
                              <span style="font-size: 13px; font-weight: 700; color: #012133; margin-left: 8px;">
                                <?= $pctMetaArea ?>%
                              </span>
                            </div>

                            <!-- Mini progress -->
                            <div style="width: 100%; height: 4px; background: #F3F4F6; border-radius: 9999px; overflow: hidden;">
                              <div style="height: 100%; width: <?= $pctMetaArea ?>%; background: #EF7F1B; border-radius: 9999px;"></div>
                            </div>

                            <!-- Metas Personales Detalladas -->
                            <?php if (!empty($ma['personales'])): ?>
                              <div style="margin-top: 12px;">
                                <div style="font-size: 11px; font-weight: 600; color: #6B7280; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">
                                  👥 Metas Personales (<?= count($ma['personales']) ?>)
                                </div>
                                <?php foreach ($ma['personales'] as $persona): ?>
                                  <div style="margin-bottom: 10px; padding: 8px; background: #F9FAFB; border-radius: 6px; border-left: 3px solid #EF7F1B;">
                                    <!-- Persona Header -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                      <div style="flex: 1;">
                                        <div style="font-size: 12px; font-weight: 600; color: #1F2937;">
                                          <?= h($persona['nombre_persona']) ?>
                                        </div>
                                        <div style="font-size: 10px; color: #9CA3AF;">
                                          <?= h($persona['cargo']) ?>
                                        </div>
                                      </div>
                                      <div style="font-size: 12px; font-weight: 700; color: #EF7F1B;">
                                        <?= $persona['porcentaje'] ?>%
                                      </div>
                                    </div>

                                    <!-- Metas Individuales de esta persona -->
                                    <?php if (!empty($persona['metas_individuales'])): ?>
                                      <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #E5E7EB;">
                                        <?php foreach ($persona['metas_individuales'] as $metaInd): ?>
                                          <?php
                                            $statusColorInd = '#6B7280';
                                            $statusTextInd = 'En cola';
                                            if ($metaInd['is_completed'] == 1 || $metaInd['progress_pct'] >= 100) {
                                              $statusColorInd = '#00D98F';
                                              $statusTextInd = 'Completada';
                                            } elseif ($metaInd['progress_pct'] >= 50) {
                                              $statusColorInd = '#FFB020';
                                              $statusTextInd = 'En desarrollo';
                                            }
                                          ?>
                                          <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; padding: 4px 0;">
                                            <!-- Status dot -->
                                            <div style="
                                              width: 8px;
                                              height: 8px;
                                              border-radius: 50%;
                                              background: <?= $statusColorInd ?>;
                                              flex-shrink: 0;
                                            "></div>
                                            <!-- Descripción -->
                                            <div style="flex: 1; min-width: 0;">
                                              <div style="font-size: 11px; color: #374151; line-height: 1.3;">
                                                <?= h($metaInd['descripcion']) ?>
                                              </div>
                                              <div style="font-size: 9px; color: #9CA3AF; margin-top: 2px;">
                                                <?= $statusTextInd ?> • <?= fmt_date($metaInd['due_date']) ?>
                                              </div>
                                            </div>
                                            <!-- Progreso -->
                                            <div style="font-size: 10px; font-weight: 600; color: <?= $statusColorInd ?>; flex-shrink: 0;">
                                              <?= $metaInd['progress_pct'] ?>%
                                            </div>
                                          </div>
                                        <?php endforeach; ?>
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div style="
                  text-align: center;
                  padding: var(--space-4);
                  background: #FAFBFC;
                  border-radius: 10px;
                  border: 1px dashed #D1D5DB;
                ">
                  <span style="font-size: 11px; color: #9CA3AF;">
                    Sin áreas asignadas
                  </span>
                </div>
              <?php endif; ?>

              <!-- Botón Contextual: Crear Meta de Equipo -->
              <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid #F3F4F6;">
                <button
                  onclick="openCrearMetaEquipoModal(<?= $meta['id'] ?>)"
                  style="
                    width: 100%;
                    padding: 12px 16px;
                    background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%);
                    border: 2px dashed #D1D5DB;
                    border-radius: 10px;
                    color: #6B7280;
                    font-weight: 600;
                    font-size: 13px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    transition: all 0.2s ease;
                  "
                  onmouseover="this.style.borderColor='#EF7F1B'; this.style.color='#EF7F1B'; this.style.background='linear-gradient(135deg, #FFF7ED 0%, #FFFFFF 100%)'"
                  onmouseout="this.style.borderColor='#D1D5DB'; this.style.color='#6B7280'; this.style.background='linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%)'"
                >
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                  Meta de Equipo
                </button>
              </div>

            </div>
          </div>

        <?php endforeach; ?>
      </div>

      <!-- ============ TABLE VIEW (COMPACT) ============ -->
      <div id="tableView" style="display: none; margin-top: var(--space-6);">
        <div style="
          background: white;
          border-radius: 16px;
          border: 1px solid #E5E7EB;
          overflow: hidden;
        ">
          <!-- Table Header -->
          <div style="
            display: grid;
            grid-template-columns: 1fr 150px 120px 100px;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: #F9FAFB;
            border-bottom: 1px solid #E5E7EB;
          ">
            <div style="font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">
              Meta
            </div>
            <div style="font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">
              Fecha Límite
            </div>
            <div style="font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">
              Estado
            </div>
            <div style="font-size: 12px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">
              Progreso
            </div>
          </div>

          <!-- Table Body -->
          <?php foreach ($metas_corporativas as $meta):
            $pctCorp = clamp_pct(pct_corporativa($meta));
            $daysToDue = days_to_due($meta['due_date']);

            $statusColor = '#00A3FF';
            $statusText = 'En curso';
            if ($pctCorp >= 100) {
              $statusColor = '#00D98F';
              $statusText = 'Completada';
            } elseif ($daysToDue !== null && $daysToDue < 0) {
              $statusColor = '#FF3B6D';
              $statusText = 'Vencida';
            } elseif ($daysToDue !== null && $daysToDue <= 7) {
              $statusColor = '#FFB020';
              $statusText = 'Próxima';
            }
          ?>
            <div style="
              display: grid;
              grid-template-columns: 1fr 150px 120px 100px;
              gap: var(--space-3);
              padding: var(--space-4) var(--space-5);
              border-bottom: 1px solid #F3F4F6;
              transition: all 0.2s;
              cursor: pointer;
            "
            onclick="toggleMetaAreas(<?= $meta['id'] ?>)"
            onmouseover="this.style.background='#F9FAFB'"
            onmouseout="this.style.background='white'"
            >
              <!-- Meta Title -->
              <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div style="
                  width: 32px;
                  height: 32px;
                  background: linear-gradient(135deg, #EF7F1B, #FFB020);
                  border-radius: 8px;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-size: 16px;
                  flex-shrink: 0;
                ">🏢</div>
                <span style="font-size: 14px; font-weight: 600; color: #1F2937;">
                  <?= h($meta['descripcion']) ?>
                </span>
              </div>

              <!-- Date -->
              <div style="display: flex; align-items: center; font-size: 13px; color: #6B7280;">
                <?= fmt_date($meta['due_date']) ?>
              </div>

              <!-- Status -->
              <div style="display: flex; align-items: center;">
                <span style="
                  display: inline-block;
                  padding: 4px 10px;
                  background: <?= $statusColor ?>15;
                  color: <?= $statusColor ?>;
                  border-radius: 6px;
                  font-size: 12px;
                  font-weight: 600;
                "><?= $statusText ?></span>
              </div>

              <!-- Progress -->
              <div style="display: flex; align-items: center; justify-content: flex-end;">
                <div style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  width: 100%;
                ">
                  <div style="flex: 1; height: 6px; background: #F3F4F6; border-radius: 9999px; overflow: hidden;">
                    <div style="
                      height: 100%;
                      width: <?= $pctCorp ?>%;
                      background: <?= $statusColor ?>;
                      border-radius: 9999px;
                    "></div>
                  </div>
                  <span style="
                    font-size: 13px;
                    font-weight: 700;
                    color: <?= $statusColor ?>;
                    min-width: 40px;
                    text-align: right;
                  "><?= $pctCorp ?>%</span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    <?php endif; ?>
  </div>

  <!-- JavaScript for View Switching & Toggles -->
  <script>
    // View Switcher
    function switchView(view) {
      const gridView = document.getElementById('gridView');
      const tableView = document.getElementById('tableView');
      const gridBtn = document.getElementById('viewGridBtn');
      const tableBtn = document.getElementById('viewTableBtn');

      if (view === 'grid') {
        gridView.style.display = 'grid';
        tableView.style.display = 'none';

        // Style buttons
        gridBtn.style.background = 'linear-gradient(135deg, #012133, #184656)';
        gridBtn.style.color = 'white';
        tableBtn.style.background = 'transparent';
        tableBtn.style.color = '#6B7280';
      } else {
        gridView.style.display = 'none';
        tableView.style.display = 'block';

        // Style buttons
        tableBtn.style.background = 'linear-gradient(135deg, #012133, #184656)';
        tableBtn.style.color = 'white';
        gridBtn.style.background = 'transparent';
        gridBtn.style.color = '#6B7280';
      }
    }

    // Toggle meta areas
    function toggleMetaAreas(metaId) {
      const container = document.getElementById('meta-areas-' + metaId);
      const icon = document.querySelector('.expand-icon-' + metaId);

      if (!container) return;

      const isHidden = container.style.display === 'none' || container.style.display === '';

      if (isHidden) {
        container.style.display = 'flex';
        if (icon) icon.style.transform = 'rotate(180deg)';
      } else {
        container.style.display = 'none';
        if (icon) icon.style.transform = 'rotate(0deg)';
      }
    }

    // Toggle area details
    function toggleAreaDetails(areaId) {
      const details = document.getElementById('area-details-' + areaId);
      if (!details) return;

      const isHidden = details.style.display === 'none' || details.style.display === '';
      details.style.display = isHidden ? 'block' : 'none';

      // Prevent event propagation
      event.stopPropagation();
    }
  </script>

  <!-- Modal: Crear Meta de Empresa -->
  <div id="crearMetaModal" style="
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
  ">
    <div style="
      background: white;
      border-radius: 16px;
      padding: var(--space-6);
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      position: relative;
    ">
      <!-- Header -->
      <div style="margin-bottom: var(--space-5);">
        <h2 style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-2);">
          ✨ Crear Meta de Empresa
        </h2>
        <p style="font-size: 14px; color: var(--c-body); opacity: 0.7; margin: 0;">
          Define una nueva meta estratégica para tu organización
        </p>
      </div>

      <!-- Formulario -->
      <form method="POST" action="">
        <input type="hidden" name="action" value="crear_meta_empresa">

        <!-- Descripción -->
        <div style="margin-bottom: var(--space-4);">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--c-secondary); margin-bottom: var(--space-2);">
            📝 Descripción de la Meta
          </label>
          <textarea
            name="descripcion"
            required
            rows="4"
            placeholder="Ej: Aumentar la satisfacción del cliente en un 20%"
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              font-family: inherit;
              resize: vertical;
              transition: all 0.2s ease;
            "
            onfocus="this.style.borderColor='var(--c-accent)'; this.style.boxShadow='0 0 0 3px rgba(255, 136, 0, 0.1)'"
            onblur="this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"
          ></textarea>
        </div>

        <!-- Fecha límite -->
        <div style="margin-bottom: var(--space-5);">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--c-secondary); margin-bottom: var(--space-2);">
            📅 Fecha Límite
          </label>
          <input
            type="date"
            name="due_date"
            required
            min="<?= date('Y-m-d') ?>"
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              font-family: inherit;
              transition: all 0.2s ease;
            "
            onfocus="this.style.borderColor='var(--c-accent)'; this.style.boxShadow='0 0 0 3px rgba(255, 136, 0, 0.1)'"
            onblur="this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"
          >
        </div>

        <!-- Botones -->
        <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
          <button
            type="button"
            onclick="closeCrearMetaModal()"
            style="
              padding: 12px 24px;
              background: #F3F4F6;
              border: none;
              border-radius: 8px;
              color: var(--c-secondary);
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              transition: all 0.2s ease;
            "
            onmouseover="this.style.background='#E5E7EB'"
            onmouseout="this.style.background='#F3F4F6'"
          >
            Cancelar
          </button>
          <button
            type="submit"
            style="
              padding: 12px 24px;
              background: linear-gradient(135deg, var(--c-accent) 0%, #FF6B35 100%);
              border: none;
              border-radius: 8px;
              color: white;
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
              transition: all 0.3s ease;
            "
            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 136, 0, 0.3)'"
            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 136, 0, 0.2)'"
          >
            ✅ Crear Meta
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openCrearMetaModal() {
      const modal = document.getElementById('crearMetaModal');
      modal.style.display = 'flex';
    }

    function closeCrearMetaModal() {
      const modal = document.getElementById('crearMetaModal');
      modal.style.display = 'none';
    }

    // Cerrar modal al hacer clic fuera
    document.getElementById('crearMetaModal')?.addEventListener('click', function(e) {
      if (e.target === this) {
        closeCrearMetaModal();
      }
    });
  </script>

  <!-- Modal: Crear Meta de Equipo (Área) -->
  <div id="crearMetaEquipoModal" style="
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
  ">
    <div style="
      background: white;
      border-radius: 16px;
      padding: var(--space-6);
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      position: relative;
    ">
      <!-- Header -->
      <div style="margin-bottom: var(--space-5);">
        <h2 style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-2);">
          🏢 Crear Meta de Equipo
        </h2>
        <p style="font-size: 14px; color: var(--c-body); opacity: 0.7; margin: 0;">
          Define una nueva meta para un área específica de tu organización
        </p>
      </div>

      <!-- Formulario -->
      <form method="POST" action="">
        <input type="hidden" name="action" value="crear_meta_equipo">
        <input type="hidden" id="parent_meta_id_input" name="parent_meta_id" value="">

        <!-- Área de Trabajo -->
        <div style="margin-bottom: var(--space-4);">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--c-secondary); margin-bottom: var(--space-2);">
            🎯 Área de Trabajo
          </label>
          <select
            name="area_id"
            required
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              font-family: inherit;
              background: white;
              cursor: pointer;
              transition: all 0.2s ease;
            "
            onfocus="this.style.borderColor='var(--c-accent)'; this.style.boxShadow='0 0 0 3px rgba(255, 136, 0, 0.1)'"
            onblur="this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"
          >
            <option value="">Selecciona un área...</option>
            <?php foreach ($areas_trabajo as $area): ?>
              <option value="<?= (int)$area['id'] ?>">
                <?= h($area['nombre_area']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($areas_trabajo)): ?>
            <div style="margin-top: 8px; padding: 8px 12px; background: #FEF3C7; border-radius: 6px; border-left: 3px solid #FFB020;">
              <span style="font-size: 12px; color: #92400E;">
                ⚠️ No hay áreas de trabajo creadas. <a href="a-organizacion.php" style="color: #EF7F1B; text-decoration: underline;">Crear área</a>
              </span>
            </div>
          <?php endif; ?>
        </div>

        <!-- Descripción -->
        <div style="margin-bottom: var(--space-4);">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--c-secondary); margin-bottom: var(--space-2);">
            📝 Descripción de la Meta
          </label>
          <textarea
            name="descripcion"
            required
            rows="4"
            placeholder="Ej: Aumentar conversión de ventas en un 25%"
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              font-family: inherit;
              resize: vertical;
              transition: all 0.2s ease;
            "
            onfocus="this.style.borderColor='var(--c-accent)'; this.style.boxShadow='0 0 0 3px rgba(255, 136, 0, 0.1)'"
            onblur="this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"
          ></textarea>
        </div>

        <!-- Fecha límite -->
        <div style="margin-bottom: var(--space-5);">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--c-secondary); margin-bottom: var(--space-2);">
            📅 Fecha Límite
          </label>
          <input
            type="date"
            name="due_date"
            required
            min="<?= date('Y-m-d') ?>"
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              font-family: inherit;
              transition: all 0.2s ease;
            "
            onfocus="this.style.borderColor='var(--c-accent)'; this.style.boxShadow='0 0 0 3px rgba(255, 136, 0, 0.1)'"
            onblur="this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"
          >
        </div>

        <!-- Botones -->
        <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
          <button
            type="button"
            onclick="closeCrearMetaEquipoModal()"
            style="
              padding: 12px 24px;
              background: #F3F4F6;
              border: none;
              border-radius: 8px;
              color: var(--c-secondary);
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              transition: all 0.2s ease;
            "
            onmouseover="this.style.background='#E5E7EB'"
            onmouseout="this.style.background='#F3F4F6'"
          >
            Cancelar
          </button>
          <button
            type="submit"
            style="
              padding: 12px 24px;
              background: linear-gradient(135deg, var(--c-accent) 0%, #FF6B35 100%);
              border: none;
              border-radius: 8px;
              color: white;
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
              transition: all 0.3s ease;
            "
            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 136, 0, 0.3)'"
            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 136, 0, 0.2)'"
          >
            ✅ Crear Meta de Equipo
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openCrearMetaEquipoModal(metaEmpresaId) {
      const modal = document.getElementById('crearMetaEquipoModal');
      const hiddenInput = document.getElementById('parent_meta_id_input');
      hiddenInput.value = metaEmpresaId;
      modal.style.display = 'flex';
    }

    function closeCrearMetaEquipoModal() {
      const modal = document.getElementById('crearMetaEquipoModal');
      modal.style.display = 'none';
    }

    // Cerrar modal al hacer clic fuera
    document.getElementById('crearMetaEquipoModal')?.addEventListener('click', function(e) {
      if (e.target === this) {
        closeCrearMetaEquipoModal();
      }
    });
  </script>

</section>

</div> <!-- Fin Tab 2: Goals -->

<!-- ============ TAB 3: TIME & ATTENDANCE ============ -->
<div class="tab-content <?= $active_tab === 'time' ? 'is-active' : '' ?>">

  <!-- Hero: Asistencia de Hoy -->
  <section style="margin-bottom: var(--space-8);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-5);">
      <div>
        <h1 style="font-size: 28px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          ⏱️ Asistencia de Hoy
        </h1>
        <p style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
          <?= date('l, d F Y') ?> • Última actualización: hace 2 minutos
        </p>
      </div>
      <button
        style="
          padding: 12px 24px;
          background: linear-gradient(135deg, var(--c-accent) 0%, #FF6B35 100%);
          border: none;
          border-radius: 8px;
          color: white;
          font-weight: 600;
          font-size: 14px;
          cursor: pointer;
          box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
          transition: all 0.3s ease;
        "
        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 136, 0, 0.3)'"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 136, 0, 0.2)'"
      >
        🔄 Actualizar
      </button>
    </div>

    <!-- KPIs Hero Grid -->
    <div style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-4);
      margin-bottom: var(--space-6);
    ">
      <!-- KPI: Presentes -->
      <div style="
        background: linear-gradient(135deg, #00D98F 0%, #00D98F 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          Presentes
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $porcentaje_presentes ?>%
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          <?= $presentes_hoy ?> de <?= $total_personas ?> personas
        </div>
      </div>

      <!-- KPI: A Tiempo -->
      <div style="
        background: linear-gradient(135deg, #00D98F 0%, #00D98F 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          A Tiempo
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $a_tiempo_hoy ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Puntualidad <?= $porcentaje_a_tiempo ?>%
        </div>
      </div>

      <!-- KPI: Tardes -->
      <div style="
        background: linear-gradient(135deg, #FFB020 0%, #EF7F1B 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          Tardes
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $tarde_hoy ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Exceden su tolerancia
        </div>
      </div>

      <!-- KPI: Ausentes -->
      <div style="
        background: linear-gradient(135deg, #FF3B6D 0%, #FF3B6D 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="
          position: absolute;
          top: -20px;
          right: -20px;
          width: 100px;
          height: 100px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 50%;
        "></div>
        <div style="font-size: 13px; font-weight: 500; opacity: 0.9; margin-bottom: var(--space-2);">
          Ausentes
        </div>
        <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
          <?= $ausentes_hoy ?>
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          Sin registro hoy
        </div>
      </div>
    </div>

    <!-- Lista de Asistencia HOY -->
    <div style="
      background: white;
      border-radius: 16px;
      padding: var(--space-6);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    ">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-5);">
        <h2 style="font-size: 18px; font-weight: 700; color: var(--c-secondary); margin: 0;">
          🧑‍💼 Estado del Equipo Hoy
        </h2>
        <div style="display: flex; gap: var(--space-3);">
          <input
            type="text"
            placeholder="🔍 Buscar..."
            style="
              padding: 8px 16px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 13px;
              transition: border-color 0.2s;
            "
            onfocus="this.style.borderColor='var(--c-accent)'"
            onblur="this.style.borderColor='#E5E7EB'"
          >
          <select style="
            padding: 8px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
          ">
            <option>Todos</option>
            <option>Presentes</option>
            <option>Ausentes</option>
            <option>Tardes</option>
          </select>
        </div>
      </div>

      <?php if (empty($asistencia_hoy)): ?>
        <!-- Estado vacío -->
        <div style="text-align: center; padding: var(--space-8) 0; color: var(--c-body); opacity: 0.6;">
          <div style="font-size: 48px; margin-bottom: var(--space-3);">📋</div>
          <p style="font-size: 16px; font-weight: 600; margin-bottom: var(--space-2);">
            No hay registros de asistencia hoy
          </p>
          <p style="font-size: 14px;">
            Los registros aparecerán cuando tu equipo haga check-in
          </p>
        </div>
      <?php else: ?>
        <!-- Lista de personas -->
        <div style="display: flex; flex-direction: column; gap: var(--space-3);">
          <?php foreach ($asistencia_hoy as $persona):
            $nivel_alerta = $persona['nivel_alerta'] ?? 'ok';
            $estado_visual = $persona['estado_visual'] ?? 'ausente';

            // Colores según alerta
            $border_colors = [
              'ok' => '#00D98F',
              'warning' => '#FFB020',
              'critico' => '#FF3B6D',
              'ausente' => '#FF3B6D'
            ];
            $bg_colors = [
              'ok' => '#ECFDF5',
              'warning' => '#FEF3C7',
              'critico' => '#FEE2E2',
              'ausente' => '#FEE2E2'
            ];
            $icons = [
              'ok' => '✅',
              'warning' => '⚠️',
              'critico' => '🚨',
              'ausente' => '❌'
            ];

            $border_color = $border_colors[$nivel_alerta] ?? '#E5E7EB';
            $bg_color = $bg_colors[$nivel_alerta] ?? '#F9FAFB';
            $icon = $icons[$nivel_alerta] ?? '•';
          ?>
            <div style="
              border: 2px solid <?= $border_color ?>;
              border-radius: 12px;
              padding: var(--space-4);
              background: <?= $bg_color ?>;
              transition: all 0.3s ease;
              cursor: pointer;
            "
            onmouseover="this.style.transform='translateX(4px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'"
            onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='none'"
            >
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: var(--space-3); flex: 1;">
                  <div style="font-size: 24px;"><?= $icon ?></div>
                  <div style="flex: 1;">
                    <div style="font-weight: 600; color: var(--c-secondary); font-size: 15px; margin-bottom: 2px;">
                      <?= h($persona['nombre_persona'] ?? 'N/A') ?>
                    </div>
                    <div style="font-size: 13px; color: var(--c-body); opacity: 0.7;">
                      <?= h($persona['cargo'] ?? 'Sin cargo') ?>
                    </div>
                  </div>
                </div>

                <div style="display: flex; align-items: center; gap: var(--space-4);">
                  <?php if (!empty($persona['hora_entrada'])): ?>
                    <div style="text-align: right;">
                      <div style="font-size: 12px; color: var(--c-body); opacity: 0.6; margin-bottom: 2px;">
                        Entrada
                      </div>
                      <div style="font-weight: 600; color: var(--c-secondary); font-size: 14px;">
                        <?= date('g:i A', strtotime($persona['hora_entrada'])) ?>
                      </div>
                      <?php if (!empty($persona['minutos_tarde']) && $persona['minutos_tarde'] > 0): ?>
                        <div style="font-size: 11px; color: #FFB020; font-weight: 600;">
                          +<?= $persona['minutos_tarde'] ?> min tarde
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div style="text-align: right;">
                      <div style="font-size: 12px; color: var(--c-body); opacity: 0.6; margin-bottom: 2px;">
                        Entrada
                      </div>
                      <div style="font-weight: 600; color: #FF3B6D; font-size: 14px;">
                        Sin registro
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($persona['hora_salida'])): ?>
                    <div style="text-align: right;">
                      <div style="font-size: 12px; color: var(--c-body); opacity: 0.6; margin-bottom: 2px;">
                        Salida
                      </div>
                      <div style="font-weight: 600; color: var(--c-secondary); font-size: 14px;">
                        <?= date('g:i A', strtotime($persona['hora_salida'])) ?>
                      </div>
                      <?php
                      $minutos_tarde_salida = (int)($persona['minutos_tarde_salida'] ?? 0);
                      if ($minutos_tarde_salida > 0): ?>
                        <div style="font-size: 11px; color: #FF3B6D; font-weight: 600;">
                          ⚠️ <?= $minutos_tarde_salida ?> min antes
                        </div>
                      <?php elseif ($minutos_tarde_salida < 0):
                        $minutos_extra = abs($minutos_tarde_salida);
                        $horas = floor($minutos_extra / 60);
                        $mins = $minutos_extra % 60;
                      ?>
                        <div style="font-size: 11px; color: #10B981; font-weight: 600;">
                          💼 <?= $horas > 0 ? "{$horas}h {$mins}m" : "{$mins}m" ?> extra
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div style="text-align: right;">
                      <div style="font-size: 12px; color: var(--c-body); opacity: 0.6; margin-bottom: 2px;">
                        Salida
                      </div>
                      <div style="font-weight: 600; color: var(--c-body); opacity: 0.4; font-size: 14px;">
                        --:-- --
                      </div>
                    </div>
                  <?php endif; ?>

                  <button style="
                    padding: 8px 16px;
                    background: white;
                    border: 1px solid #E5E7EB;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    color: var(--c-secondary);
                    cursor: pointer;
                    transition: all 0.2s;
                  "
                  onmouseover="this.style.background='#F9FAFB'"
                  onmouseout="this.style.background='white'"
                  >
                    Ver detalles
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Análisis de Patrones -->
  <?php if (!empty($patrones_atencion) || !empty($patrones_excelentes)): ?>
  <section style="margin-bottom: var(--space-8);">
    <h2 style="font-size: 22px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-4);">
      🔍 Análisis de Patrones de Asistencia
    </h2>
    <p style="color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-5); font-size: 14px;">
      Período: Últimos 30 días • Actualizado automáticamente
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--space-5);">
      <!-- Patrones Excelentes -->
      <?php if (!empty($patrones_excelentes)): ?>
      <div style="
        background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
        border: 2px solid #00D98F;
        border-radius: 16px;
        padding: var(--space-5);
      ">
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
          <div style="font-size: 24px;">🏆</div>
          <h3 style="font-size: 16px; font-weight: 700; color: #00D98F; margin: 0;">
            Patrones Excelentes
          </h3>
        </div>

        <?php foreach ($patrones_excelentes as $patron): ?>
          <div style="
            background: white;
            border-radius: 10px;
            padding: var(--space-3);
            margin-bottom: var(--space-3);
          ">
            <div style="font-weight: 600; color: var(--c-secondary); font-size: 14px; margin-bottom: var(--space-1);">
              <?= h($patron['nombre_persona'] ?? 'N/A') ?>
            </div>
            <div style="display: flex; gap: var(--space-3); font-size: 12px; color: var(--c-body);">
              <span>✨ <?= number_format($patron['tasa_asistencia'] ?? 0, 0) ?>% asistencia</span>
              <span>⏰ <?= number_format($patron['tasa_puntualidad'] ?? 0, 0) ?>% puntualidad</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Patrones que Requieren Atención -->
      <?php if (!empty($patrones_atencion)): ?>
      <div style="
        background: linear-gradient(135deg, #fff8f0 0%, #facb99 100%);
        border: 2px solid #FFB020;
        border-radius: 16px;
        padding: var(--space-5);
      ">
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
          <div style="font-size: 24px;">⚠️</div>
          <h3 style="font-size: 16px; font-weight: 700; color: #EF7F1B; margin: 0;">
            Requieren Atención
          </h3>
        </div>

        <?php foreach (array_slice($patrones_atencion, 0, 3) as $patron): ?>
          <div style="
            background: white;
            border-radius: 10px;
            padding: var(--space-3);
            margin-bottom: var(--space-3);
          ">
            <div style="font-weight: 600; color: var(--c-secondary); font-size: 14px; margin-bottom: var(--space-1);">
              🚨 <?= h($patron['nombre_persona'] ?? 'N/A') ?>
            </div>
            <div style="font-size: 12px; color: var(--c-body); margin-bottom: var(--space-2);">
              Asistencia: <?= number_format($patron['tasa_asistencia'] ?? 0, 0) ?>% •
              Puntualidad: <?= number_format($patron['tasa_puntualidad'] ?? 0, 0) ?>%
            </div>
            <div style="
              padding: var(--space-2);
              background: #FEF3C7;
              border-radius: 6px;
              font-size: 11px;
              color: #8a4709;
            ">
              💡 Acción sugerida: Reunión 1-on-1 con manager
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ========================================================================== -->
  <!-- PANEL DE PERMISOS Y VACACIONES (EMPLEADOR) -->
  <!-- Sistema profesional de gestión de solicitudes con aprobación/rechazo -->
  <!-- ========================================================================== -->
  <?php include 'permisos_vacaciones_empleador_panel.php'; ?>

  <!-- ============ SECCIÓN: HORARIOS DE TRABAJO ============ -->
  <section style="margin-bottom: var(--space-8);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-5);">
      <div>
        <h1 style="font-size: 28px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          🕒 Horarios de Trabajo
        </h1>
        <p style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
          Gestiona las jornadas laborales y asigna empleados a cada horario
        </p>
      </div>
      <div style="display: flex; gap: 12px;">
        <button
          onclick="abrirModalDescargarHistorico()"
          style="
            padding: 12px 24px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
          "
          onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(16, 185, 129, 0.3)'"
          onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.2)'"
        >
          📥 Descargar Histórico
        </button>
        <button
          onclick="abrirModalJornadaWizard()"
          style="
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--c-accent) 0%, #FF6B35 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
            transition: all 0.3s ease;
          "
          onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 136, 0, 0.3)'"
          onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 136, 0, 0.2)'"
        >
          ➕ Crear Jornada
        </button>
      </div>
    </div>

    <!-- KPIs Horarios -->
    <div style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-4);
      margin-bottom: var(--space-6);
    ">
      <!-- KPI: Jornadas Activas -->
      <div style="
        background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="position: relative; z-index: 1;">
          <div style="font-size: 13px; opacity: 0.9; margin-bottom: var(--space-2);">
            Jornadas Activas
          </div>
          <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
            <?= $jornadas_activas ?>
          </div>
          <div style="font-size: 12px; opacity: 0.8;">
            De <?= $total_jornadas ?> totales
          </div>
        </div>
        <div style="
          position: absolute;
          right: -20px;
          bottom: -20px;
          font-size: 80px;
          opacity: 0.15;
        ">📋</div>
      </div>

      <!-- KPI: Empleados Asignados -->
      <div style="
        background: linear-gradient(135deg, #F093FB 0%, #F5576C 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="position: relative; z-index: 1;">
          <div style="font-size: 13px; opacity: 0.9; margin-bottom: var(--space-2);">
            Empleados con Jornada
          </div>
          <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
            <?= $total_empleados_con_jornada ?>
          </div>
          <div style="font-size: 12px; opacity: 0.8;">
            De <?= $total_personas ?> empleados
          </div>
        </div>
        <div style="
          position: absolute;
          right: -20px;
          bottom: -20px;
          font-size: 80px;
          opacity: 0.15;
        ">👥</div>
      </div>

      <!-- KPI: Cobertura -->
      <div style="
        background: linear-gradient(135deg, #4FACFE 0%, #00F2FE 100%);
        border-radius: 16px;
        padding: var(--space-5);
        color: white;
        position: relative;
        overflow: hidden;
      ">
        <div style="position: relative; z-index: 1;">
          <div style="font-size: 13px; opacity: 0.9; margin-bottom: var(--space-2);">
            Cobertura de Horarios
          </div>
          <div style="font-size: 36px; font-weight: 700; margin-bottom: var(--space-1);">
            <?= $total_personas > 0 ? round(($total_empleados_con_jornada / $total_personas) * 100) : 0 ?>%
          </div>
          <div style="font-size: 12px; opacity: 0.8;">
            Del equipo total
          </div>
        </div>
        <div style="
          position: absolute;
          right: -20px;
          bottom: -20px;
          font-size: 80px;
          opacity: 0.15;
        ">📊</div>
      </div>
    </div>

    <!-- Lista de Jornadas -->
    <?php if (empty($jornadas_trabajo)): ?>
      <div style="
        background: white;
        border-radius: 16px;
        padding: var(--space-8);
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      ">
        <div style="font-size: 64px; margin-bottom: var(--space-4); opacity: 0.3;">🕒</div>
        <h3 style="font-size: 20px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-2);">
          No hay jornadas configuradas
        </h3>
        <p style="color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-5); font-size: 14px;">
          Crea tu primera jornada laboral para comenzar a gestionar los horarios del equipo
        </p>
        <button
          onclick="abrirModalJornadaWizard()"
          style="
            padding: 12px 32px;
            background: var(--c-accent);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 136, 0, 0.2);
          "
        >
          ➕ Crear Primera Jornada
        </button>
      </div>
    <?php else: ?>
      <div style="
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: var(--space-4);
      ">
        <?php foreach ($jornadas_trabajo as $jornada): ?>
          <div style="
            background: white;
            border-radius: 16px;
            padding: var(--space-5);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid <?= h($jornada['color_hex']) ?>;
            position: relative;
            transition: all 0.3s ease;
          "
          onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'; this.style.transform='translateY(-2px)'"
          onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'"
          >
            <!-- Header -->
            <div style="display: flex; align-items: start; justify-content: space-between; margin-bottom: var(--space-3);">
              <div style="flex: 1;">
                <h3 style="font-size: 18px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
                  <?= h($jornada['nombre']) ?>
                </h3>
                <?php if (!empty($jornada['codigo_corto'])): ?>
                  <span style="
                    display: inline-block;
                    padding: 2px 8px;
                    background: <?= h($jornada['color_hex']) ?>20;
                    color: <?= h($jornada['color_hex']) ?>;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                  ">
                    <?= h($jornada['codigo_corto']) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div style="
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: <?= $jornada['is_active'] ? '#00D98F' : '#CCCCCC' ?>;
              "></div>
            </div>

            <!-- Descripción -->
            <?php if (!empty($jornada['descripcion'])): ?>
              <p style="font-size: 13px; color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-3); line-height: 1.5;">
                <?= h($jornada['descripcion']) ?>
              </p>
            <?php endif; ?>

            <!-- Detalles -->
            <div style="margin-bottom: var(--space-4);">
              <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                <span style="font-size: 14px;">📅</span>
                <span style="font-size: 13px; color: var(--c-body);">
                  <?= format_dias_turno($jornada['turnos']) ?>
                </span>
              </div>
              <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                <span style="font-size: 14px;">⏰</span>
                <span style="font-size: 13px; color: var(--c-body);">
                  <?= rango_horas_turno($jornada['turnos']) ?>
                </span>
              </div>
              <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                <span style="font-size: 14px;">📊</span>
                <span style="font-size: 13px; color: var(--c-body);">
                  <?= number_format($jornada['horas_semanales_esperadas'], 1) ?> hrs/semana
                </span>
              </div>
              <?php if (tiene_turno_nocturno($jornada['turnos'])): ?>
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                  <span style="font-size: 14px;">🌙</span>
                  <span style="font-size: 13px; color: #667EEA; font-weight: 600;">
                    Incluye turno nocturno
                  </span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Estadísticas -->
            <div style="
              display: grid;
              grid-template-columns: 1fr 1fr;
              gap: var(--space-3);
              padding: var(--space-3);
              background: #F9FAFB;
              border-radius: 8px;
              margin-bottom: var(--space-4);
            ">
              <div>
                <div style="font-size: 11px; color: var(--c-body); opacity: 0.6; margin-bottom: 4px;">
                  Empleados
                </div>
                <div style="font-size: 20px; font-weight: 700; color: var(--c-secondary);">
                  <?= (int)$jornada['total_empleados'] ?>
                </div>
              </div>
              <div>
                <div style="font-size: 11px; color: var(--c-body); opacity: 0.6; margin-bottom: 4px;">
                  Tolerancia
                </div>
                <div style="font-size: 20px; font-weight: 700; color: var(--c-secondary);">
                  <?= (int)$jornada['tolerancia_entrada_min'] ?> min
                </div>
              </div>
            </div>

            <!-- Acciones -->
            <div style="display: flex; gap: var(--space-2);">
              <button
                onclick="verDetallesJornada(<?= (int)$jornada['id'] ?>)"
                style="
                  flex: 1;
                  padding: 8px 12px;
                  background: white;
                  border: 1px solid #E5E7EB;
                  border-radius: 6px;
                  color: var(--c-secondary);
                  font-size: 12px;
                  font-weight: 600;
                  cursor: pointer;
                  transition: all 0.2s ease;
                "
                onmouseover="this.style.background='#F9FAFB'"
                onmouseout="this.style.background='white'"
              >
                👁️ Ver
              </button>
              <button
                onclick="abrirModalAsignarEmpleados(<?= (int)$jornada['id'] ?>, '<?= h($jornada['nombre']) ?>')"
                style="
                  flex: 1;
                  padding: 8px 12px;
                  background: var(--c-accent);
                  border: none;
                  border-radius: 6px;
                  color: white;
                  font-size: 12px;
                  font-weight: 600;
                  cursor: pointer;
                  transition: all 0.2s ease;
                "
                onmouseover="this.style.opacity='0.9'"
                onmouseout="this.style.opacity='1'"
              >
                👥 Asignar
              </button>
              <button
                onclick="editarJornada(<?= (int)$jornada['id'] ?>)"
                style="
                  padding: 8px 12px;
                  background: white;
                  border: 1px solid #E5E7EB;
                  border-radius: 6px;
                  color: var(--c-secondary);
                  font-size: 12px;
                  cursor: pointer;
                  transition: all 0.2s ease;
                "
                onmouseover="this.style.background='#F9FAFB'"
                onmouseout="this.style.background='white'"
              >
                ✏️
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</div>

<!-- ============ MODALES DE HORARIOS ============ -->

<!-- Modal: Crear/Editar Jornada (Wizard 4 pasos) -->
<div id="modalJornadaWizard" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header del modal -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 id="wizard-title" style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          Crear Nueva Jornada
        </h2>
        <div style="display: flex; align-items: center; gap: var(--space-2); margin-top: var(--space-2);">
          <span id="wizard-step-indicator" style="font-size: 12px; font-weight: 600; color: var(--c-body); opacity: 0.5;">
            Paso 1 de 4
          </span>
          <div style="flex: 1; height: 4px; background: var(--perf-border); border-radius: 2px; max-width: 200px;">
            <div id="wizard-progress-bar" style="height: 100%; background: var(--c-accent); border-radius: 2px; width: 25%; transition: width 0.3s ease;"></div>
          </div>
        </div>
      </div>
      <button
        onclick="cerrarModalJornada()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
          padding: 0;
          width: 32px;
          height: 32px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 50%;
          transition: all 0.2s ease;
        "
        onmouseover="this.style.opacity='1'; this.style.background='var(--perf-bg)'"
        onmouseout="this.style.opacity='0.5'; this.style.background='none'"
      >
        ✕
      </button>
    </div>

    <!-- Contenido del wizard -->
    <div id="wizard-content" style="padding: var(--space-6);">
      <!-- Los pasos del wizard se cargarán dinámicamente con JavaScript -->
    </div>

    <!-- Footer con botones -->
    <div style="
      padding: var(--space-6);
      border-top: 1px solid var(--perf-border);
      display: flex;
      gap: var(--space-3);
      justify-content: flex-end;
    ">
      <button
        id="wizard-btn-back"
        onclick="wizardPrevStep()"
        style="
          display: none;
          padding: 12px 24px;
          background: white;
          border: 1px solid var(--perf-border);
          border-radius: 8px;
          color: var(--c-body);
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        ← Atrás
      </button>

      <button
        onclick="cerrarModalJornada()"
        style="
          padding: 12px 24px;
          background: white;
          border: 1px solid var(--perf-border);
          border-radius: 8px;
          color: var(--c-body);
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        Cancelar
      </button>

      <button
        id="wizard-btn-next"
        onclick="wizardNextStep()"
        style="
          padding: 12px 24px;
          background: var(--c-accent);
          border: none;
          border-radius: 8px;
          color: white;
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
        "
      >
        Siguiente →
      </button>
    </div>
  </div>
</div>


<!-- Modal: Asignar Empleados -->
<div id="modalAsignarEmpleados" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          Asignar Empleados
        </h2>
        <p id="asignar-jornada-nombre" style="color: var(--c-body); opacity: 0.7; margin: 0; font-size: 14px;">
          <!-- Se llenará con JS -->
        </p>
      </div>
      <button
        onclick="cerrarModalAsignar()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
        "
      >
        ✕
      </button>
    </div>

    <!-- Contenido -->
    <div style="padding: var(--space-6);">
      <form id="formAsignarEmpleados" onsubmit="submitAsignarEmpleados(event)">
        <input type="hidden" id="asignar-jornada-id" name="jornada_id">

        <!-- Lista de empleados -->
        <div id="lista-empleados-asignar" style="max-height: 400px; overflow-y: auto; margin-bottom: var(--space-4);">
          <?php if (empty($empleados_disponibles)): ?>
            <div style="text-align: center; padding: var(--space-6); color: var(--c-body); opacity: 0.6;">
              <div style="font-size: 48px; margin-bottom: var(--space-3);">👥</div>
              <p style="font-size: 14px;">No hay empleados disponibles</p>
              <p style="font-size: 12px; margin-top: var(--space-2);">Agrega empleados a tu equipo primero</p>
            </div>
          <?php else: ?>
            <?php foreach ($empleados_disponibles as $empleado): ?>
              <label class="empleado-item" data-nombre="<?= h($empleado['nombre_persona']) ?>" style="
                display: flex;
                align-items: center;
                gap: var(--space-3);
                padding: var(--space-3);
                border: 1px solid var(--perf-border);
                border-radius: 8px;
                margin-bottom: var(--space-2);
                cursor: pointer;
                transition: all 0.2s ease;
              "
              onmouseover="this.style.background='var(--perf-bg)'"
              onmouseout="this.style.background='white'"
              >
                <input
                  type="checkbox"
                  name="empleados[]"
                  value="<?= (int)$empleado['id'] ?>"
                  style="
                    width: 18px;
                    height: 18px;
                    cursor: pointer;
                    accent-color: var(--c-accent);
                  "
                >
                <div style="flex: 1;">
                  <div style="font-size: 14px; font-weight: 600; color: var(--c-secondary);">
                    <?= h($empleado['nombre_persona']) ?>
                  </div>
                  <?php if (!empty($empleado['cargo'])): ?>
                    <div style="font-size: 12px; color: var(--c-body); opacity: 0.7; margin-top: 2px;">
                      <?= h($empleado['cargo']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Vigencia -->
        <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--perf-bg); border-radius: 8px;">
          <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: var(--space-2);">
            Vigencia de la asignación:
          </label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
            <div>
              <label style="display: block; font-size: 12px; opacity: 0.7; margin-bottom: var(--space-1);">Desde:</label>
              <input
                type="date"
                id="asignar-fecha-inicio"
                value="<?= date('Y-m-d') ?>"
                style="width: 100%; padding: 8px 12px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
              >
            </div>
            <div>
              <label style="display: block; font-size: 12px; opacity: 0.7; margin-bottom: var(--space-1);">Hasta (opcional):</label>
              <input
                type="date"
                id="asignar-fecha-fin"
                style="width: 100%; padding: 8px 12px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
              >
            </div>
          </div>
          <p style="font-size: 11px; opacity: 0.6; margin: var(--space-2) 0 0;">
            💡 Dejar "Hasta" vacío para asignación indefinida
          </p>
        </div>

        <!-- Botones -->
        <div style="display: flex; gap: var(--space-3); margin-top: var(--space-5);">
          <button
            type="button"
            onclick="cerrarModalAsignar()"
            style="
              flex: 1;
              padding: 12px;
              background: white;
              border: 1px solid var(--perf-border);
              border-radius: 8px;
              font-size: 14px;
              font-weight: 600;
              cursor: pointer;
            "
          >
            Cancelar
          </button>
          <button
            type="submit"
            style="
              flex: 1;
              padding: 12px;
              background: var(--c-accent);
              border: none;
              border-radius: 8px;
              color: white;
              font-size: 14px;
              font-weight: 600;
              cursor: pointer;
            "
          >
            Asignar Seleccionados
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal: Ver Detalles de Jornada -->
<div id="modalDetallesJornada" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 id="detalles-jornada-nombre" style="font-size: 24px; font-weight: 700; color: var(--c-secondary); margin: 0;">
          Detalles de Jornada
        </h2>
      </div>
      <button
        onclick="cerrarModalDetalles()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
        "
      >
        ✕
      </button>
    </div>

    <!-- Contenido -->
    <div id="detalles-jornada-contenido" style="padding: var(--space-6);">
      <!-- Se llenará con JavaScript -->
    </div>
  </div>
</div>

<!-- Modal: Descargar Histórico de Asistencias -->
<div id="modalDescargarHistorico" style="
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
">
  <div style="
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  ">
    <!-- Header -->
    <div style="
      padding: var(--space-6);
      border-bottom: 1px solid var(--perf-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    ">
      <div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--c-secondary); margin: 0 0 var(--space-1);">
          📥 Descargar Histórico de Asistencias
        </h2>
        <p style="font-size: 13px; color: var(--c-body); opacity: 0.7; margin: 0;">
          Registro según Real Decreto-ley 8/2019 (Normativa Española)
        </p>
      </div>
      <button
        onclick="cerrarModalDescargarHistorico()"
        style="
          background: none;
          border: none;
          font-size: 24px;
          color: var(--c-body);
          opacity: 0.5;
          cursor: pointer;
        "
      >
        ✕
      </button>
    </div>

    <!-- Formulario -->
    <div style="padding: var(--space-6);">
      <form id="formDescargarHistorico" onsubmit="descargarHistorico(event)">
        <div style="margin-bottom: var(--space-4);">
          <label style="display: block; font-weight: 600; margin-bottom: var(--space-2); font-size: 14px; color: var(--c-secondary);">
            📅 Fecha Desde
          </label>
          <input
            type="date"
            id="fecha_desde"
            name="fecha_desde"
            value="<?= date('Y-m-01') ?>"
            max="<?= date('Y-m-d') ?>"
            required
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              transition: border-color 0.2s;
            "
            onfocus="this.style.borderColor='var(--c-accent)'"
            onblur="this.style.borderColor='#E5E7EB'"
          >
        </div>

        <div style="margin-bottom: var(--space-5);">
          <label style="display: block; font-weight: 600; margin-bottom: var(--space-2); font-size: 14px; color: var(--c-secondary);">
            📅 Fecha Hasta
          </label>
          <input
            type="date"
            id="fecha_hasta"
            name="fecha_hasta"
            value="<?= date('Y-m-d') ?>"
            max="<?= date('Y-m-d') ?>"
            required
            style="
              width: 100%;
              padding: 12px;
              border: 2px solid #E5E7EB;
              border-radius: 8px;
              font-size: 14px;
              transition: border-color 0.2s;
            "
            onfocus="this.style.borderColor='var(--c-accent)'"
            onblur="this.style.borderColor='#E5E7EB'"
          >
        </div>

        <div style="
          background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
          border-left: 4px solid #3B82F6;
          padding: var(--space-4);
          border-radius: 8px;
          margin-bottom: var(--space-5);
        ">
          <div style="font-size: 13px; color: #1E40AF; line-height: 1.6;">
            <strong>ℹ️ Información:</strong><br>
            El archivo CSV incluye:<br>
            • Nombre y puesto del trabajador<br>
            • Fecha y día de la semana<br>
            • Horas de entrada y salida<br>
            • Total de horas trabajadas<br>
            • Estado y puntualidad<br>
            • Cumple con RD-ley 8/2019
          </div>
        </div>

        <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
          <button
            type="button"
            onclick="cerrarModalDescargarHistorico()"
            style="
              padding: 12px 24px;
              background: #F3F4F6;
              border: none;
              border-radius: 8px;
              color: var(--c-secondary);
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              transition: all 0.2s;
            "
            onmouseover="this.style.background='#E5E7EB'"
            onmouseout="this.style.background='#F3F4F6'"
          >
            Cancelar
          </button>
          <button
            type="submit"
            style="
              padding: 12px 24px;
              background: linear-gradient(135deg, #10B981 0%, #059669 100%);
              border: none;
              border-radius: 8px;
              color: white;
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
              transition: all 0.3s ease;
            "
            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(16, 185, 129, 0.3)'"
            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.2)'"
          >
            📥 Descargar CSV
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================
     TAB 4: PROYECTOS Y TAREAS
     ============================================ -->
<div class="tab-content <?= $active_tab === 'projects' ? 'is-active' : '' ?>">
  <section class="wrap" style="padding-top: var(--space-6); padding-bottom: var(--space-8);">
    <header style="margin-bottom: var(--space-6);">
      <h1 style="font-size: 28px; font-weight: 700; color: var(--c-secondary); margin: 0;">
        Proyectos y Tareas
      </h1>
      <p style="color: var(--c-body); opacity: 0.7; margin-top: var(--space-2);">
        Gestiona tus proyectos y haz seguimiento del progreso de las tareas del equipo
      </p>
    </header>

    <?php include __DIR__ . '/proyectos_tareas_panel.php'; ?>
  </section>
</div>

</div> <!-- Fin .wrap -->


  
  
  
  



 <script>
/* ============================================
   JAVASCRIPT PARA GESTIÓN DE HORARIOS
   ============================================ */

// Variables globales
let wizardCurrentStep = 1;
let wizardData = {
  nombre: '',
  descripcion: '',
  codigo_corto: '',
  tipo_jornada: 'fija',
  color_hex: '#184656',
  horas_semanales_esperadas: 40.00,
  tolerancia_entrada_min: 15,
  tolerancia_salida_min: 5,
  turnos: []
};

// ============================================
// FUNCIONES PARA DESCARGAR HISTÓRICO
// ============================================

function abrirModalDescargarHistorico() {
  document.getElementById('modalDescargarHistorico').style.display = 'flex';
}

function cerrarModalDescargarHistorico() {
  document.getElementById('modalDescargarHistorico').style.display = 'none';
}

function descargarHistorico(event) {
  event.preventDefault();

  const fechaDesde = document.getElementById('fecha_desde').value;
  const fechaHasta = document.getElementById('fecha_hasta').value;

  // Validar que fecha_desde sea anterior o igual a fecha_hasta
  if (new Date(fechaDesde) > new Date(fechaHasta)) {
    alert('⚠️ La fecha de inicio debe ser anterior o igual a la fecha de fin');
    return;
  }

  // Construir URL con parámetros
  const url = window.location.pathname +
    '?action=descargar_historico_asistencias' +
    '&fecha_desde=' + encodeURIComponent(fechaDesde) +
    '&fecha_hasta=' + encodeURIComponent(fechaHasta);

  // Abrir en nueva ventana para descargar
  window.location.href = url;

  // Cerrar modal después de iniciar descarga
  setTimeout(() => {
    cerrarModalDescargarHistorico();
  }, 500);
}

// ============================================
// FUNCIONES PARA JORNADAS
// ============================================

// Alias para compatibilidad
function abrirModalJornadaWizard() {
  abrirModalCrearJornada();
}

function abrirModalCrearJornada() {
  wizardCurrentStep = 1;
  wizardData = {
    nombre: '',
    descripcion: '',
    codigo_corto: '',
    tipo_jornada: 'fija',
    color_hex: '#184656',
    horas_semanales_esperadas: 40.00,
    tolerancia_entrada_min: 15,
    tolerancia_salida_min: 5,
    turnos: []
  };

  document.getElementById('wizard-title').textContent = 'Crear Nueva Jornada';
  document.getElementById('modalJornadaWizard').style.display = 'flex';

  renderWizardStep();
}

function cerrarModalJornada() {
  document.getElementById('modalJornadaWizard').style.display = 'none';
}

function wizardNextStep() {
  if (!validarPasoActual()) return;
  guardarDatosPaso();

  if (wizardCurrentStep < 4) {
    wizardCurrentStep++;
    renderWizardStep();
  } else {
    submitCrearJornada();
  }
}

function wizardPrevStep() {
  if (wizardCurrentStep > 1) {
    wizardCurrentStep--;
    renderWizardStep();
  }
}

function renderWizardStep() {
  const content = document.getElementById('wizard-content');
  const indicator = document.getElementById('wizard-step-indicator');
  const progressBar = document.getElementById('wizard-progress-bar');
  const btnNext = document.getElementById('wizard-btn-next');
  const btnBack = document.getElementById('wizard-btn-back');

  indicator.textContent = `Paso ${wizardCurrentStep} de 4`;
  progressBar.style.width = `${wizardCurrentStep * 25}%`;
  btnBack.style.display = wizardCurrentStep > 1 ? 'block' : 'none';
  btnNext.textContent = wizardCurrentStep === 4 ? '✓ Guardar Jornada' : 'Siguiente →';

  switch (wizardCurrentStep) {
    case 1:
      content.innerHTML = renderPaso1();
      break;
    case 2:
      content.innerHTML = renderPaso2();
      initPaso2Handlers();
      break;
    case 3:
      content.innerHTML = renderPaso3();
      break;
    case 4:
      content.innerHTML = renderPaso4();
      break;
  }
}

function renderPaso1() {
  return `
    <div>
      <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 var(--space-5); color: var(--c-secondary);">
        Información Básica
      </h3>

      <div style="margin-bottom: var(--space-4);">
        <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
          Nombre de la jornada <span style="color: #FF3B6D;">*</span>
        </label>
        <input
          type="text"
          id="wizard-nombre"
          placeholder="Ej: Oficina - Diurno"
          value="${wizardData.nombre}"
          style="width: 100%; padding: 12px 16px; border: 1px solid var(--perf-border); border-radius: 8px; font-size: 14px;"
        >
      </div>

      <div style="margin-bottom: var(--space-4);">
        <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
          Descripción (opcional)
        </label>
        <textarea
          id="wizard-descripcion"
          placeholder="Horario estándar para personal administrativo..."
          style="width: 100%; padding: 12px 16px; border: 1px solid var(--perf-border); border-radius: 8px; font-size: 14px; resize: vertical; min-height: 80px;"
        >${wizardData.descripcion}</textarea>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
        <div>
          <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
            Código corto
          </label>
          <input
            type="text"
            id="wizard-codigo"
            placeholder="Ej: OFF-D"
            value="${wizardData.codigo_corto}"
            maxlength="20"
            style="width: 100%; padding: 12px 16px; border: 1px solid var(--perf-border); border-radius: 8px; font-size: 14px;"
          >
        </div>

        <div>
          <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
            Color de identificación
          </label>
          <input
            type="color"
            id="wizard-color"
            value="${wizardData.color_hex}"
            style="width: 100%; height: 46px; padding: 4px; border: 1px solid var(--perf-border); border-radius: 8px; cursor: pointer;"
          >
        </div>
      </div>

      <input type="hidden" id="wizard-tipo-jornada" value="fija">

      <script>
        // Tipo de jornada siempre fija (simplificado)
      <\/script>
    </div>
  `;
}

function renderPaso2() {
  let turnosHTML = '';

  if (wizardData.turnos.length === 0) {
    wizardData.turnos.push({
      nombre_turno: 'Lunes a Viernes',
      dias: [1, 2, 3, 4, 5],
      hora_inicio: '09:00',
      hora_fin: '18:00',
      cruza_medianoche: 0
    });
  }

  wizardData.turnos.forEach((turno, index) => {
    turnosHTML += renderTurnoCard(turno, index);
  });

  return `
    <div>
      <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 var(--space-3); color: var(--c-secondary);">
        Definir Turnos de Trabajo
      </h3>
      <p style="color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-5); font-size: 14px;">
        Configura los horarios para cada día de la semana
      </p>

      <div id="turnos-container">
        ${turnosHTML}
      </div>

      <button
        type="button"
        onclick="agregarTurno()"
        style="width: 100%; padding: 12px; background: white; border: 2px dashed var(--perf-border); border-radius: 8px; color: var(--c-body); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; margin-top: var(--space-4);"
        onmouseover="this.style.borderColor='var(--c-accent)'; this.style.color='var(--c-accent)'"
        onmouseout="this.style.borderColor='var(--perf-border)'; this.style.color='var(--c-body)'"
      >
        ➕ Agregar Otro Turno
      </button>
    </div>
  `;
}

function renderTurnoCard(turno, index) {
  const diasSemana = [
    { num: 1, label: 'Lun' },
    { num: 2, label: 'Mar' },
    { num: 3, label: 'Mié' },
    { num: 4, label: 'Jue' },
    { num: 5, label: 'Vie' },
    { num: 6, label: 'Sáb' },
    { num: 7, label: 'Dom' }
  ];

  let diasHTML = '';
  diasSemana.forEach(dia => {
    const checked = turno.dias.includes(dia.num);
    diasHTML += `
      <label class="dia-checkbox" style="flex: 1; min-width: 50px; padding: 10px 8px; border: 2px solid ${checked ? 'var(--c-accent)' : 'var(--perf-border)'}; border-radius: 8px; text-align: center; cursor: pointer; font-size: 12px; font-weight: 600; background: ${checked ? 'var(--c-accent)10' : 'white'}; transition: all 0.2s ease; user-select: none;">
        <input
          type="checkbox"
          value="${dia.num}"
          ${checked ? 'checked' : ''}
          onchange="toggleDiaTurno(${index}, ${dia.num})"
          style="display: none;"
        >
        ${dia.label}
      </label>
    `;
  });

  return `
    <div class="turno-card" style="background: var(--perf-bg); border: 1px solid var(--perf-border); border-radius: 12px; padding: var(--space-4); margin-bottom: var(--space-4);">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3);">
        <h4 style="font-size: 15px; font-weight: 700; margin: 0;">
          Turno ${index + 1}
        </h4>
        ${wizardData.turnos.length > 1 ? `
          <button
            type="button"
            onclick="eliminarTurno(${index})"
            style="background: none; border: none; color: #FF3B6D; cursor: pointer; font-size: 18px; padding: 4px 8px;"
            title="Eliminar turno"
          >
            🗑️
          </button>
        ` : ''}
      </div>

      <div style="margin-bottom: var(--space-3);">
        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: var(--space-2);">
          Nombre del turno:
        </label>
        <input
          type="text"
          value="${turno.nombre_turno}"
          onchange="actualizarTurno(${index}, 'nombre_turno', this.value)"
          placeholder="Ej: Lunes a Viernes"
          style="width: 100%; padding: 10px 14px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
        >
      </div>

      <div style="margin-bottom: var(--space-3);">
        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: var(--space-2);">
          Días de la semana:
        </label>
        <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
          ${diasHTML}
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
        <div>
          <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: var(--space-2);">
            Hora inicio:
          </label>
          <input
            type="time"
            value="${turno.hora_inicio}"
            onchange="actualizarTurno(${index}, 'hora_inicio', this.value); calcularHoras(${index})"
            style="width: 100%; padding: 10px 14px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
          >
        </div>
        <div>
          <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: var(--space-2);">
            Hora fin:
          </label>
          <input
            type="time"
            value="${turno.hora_fin}"
            onchange="actualizarTurno(${index}, 'hora_fin', this.value); calcularHoras(${index})"
            style="width: 100%; padding: 10px 14px; border: 1px solid var(--perf-border); border-radius: 6px; font-size: 14px;"
          >
        </div>
      </div>

      <div style="margin-bottom: var(--space-2);">
        <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
          <input
            type="checkbox"
            ${turno.cruza_medianoche ? 'checked' : ''}
            onchange="actualizarTurno(${index}, 'cruza_medianoche', this.checked ? 1 : 0); calcularHoras(${index})"
            style="width: 18px; height: 18px; cursor: pointer;"
          >
          <span style="font-size: 13px;">
            🌙 Este turno cruza medianoche (ej: 10 PM - 6 AM)
          </span>
        </label>
      </div>

      <div id="horas-dia-${index}" style="padding: var(--space-2); background: white; border-radius: 6px; font-size: 12px; opacity: 0.8;">
        <!-- Se llenará con JS -->
      </div>
    </div>
  `;
}

function initPaso2Handlers() {
  wizardData.turnos.forEach((turno, index) => {
    calcularHoras(index);
  });
}

function toggleDiaTurno(turnoIndex, dia) {
  const turno = wizardData.turnos[turnoIndex];
  const index = turno.dias.indexOf(dia);

  if (index > -1) {
    turno.dias.splice(index, 1);
  } else {
    turno.dias.push(dia);
    turno.dias.sort((a, b) => a - b);
  }

  renderWizardStep();
}

function actualizarTurno(turnoIndex, campo, valor) {
  wizardData.turnos[turnoIndex][campo] = valor;
}

function agregarTurno() {
  wizardData.turnos.push({
    nombre_turno: `Turno ${wizardData.turnos.length + 1}`,
    dias: [],
    hora_inicio: '09:00',
    hora_fin: '18:00',
    cruza_medianoche: 0
  });
  renderWizardStep();
}

function eliminarTurno(index) {
  if (wizardData.turnos.length > 1) {
    wizardData.turnos.splice(index, 1);
    renderWizardStep();
  }
}

function calcularHoras(turnoIndex) {
  const turno = wizardData.turnos[turnoIndex];
  const [h1, m1] = turno.hora_inicio.split(':').map(Number);
  const [h2, m2] = turno.hora_fin.split(':').map(Number);

  let minutos = 0;

  if (turno.cruza_medianoche) {
    minutos = (24 * 60 - (h1 * 60 + m1)) + (h2 * 60 + m2);
  } else {
    minutos = (h2 * 60 + m2) - (h1 * 60 + m1);
  }

  const horas = (minutos / 60).toFixed(1);
  const diasCount = turno.dias.length;
  const horasSemanal = (horas * diasCount).toFixed(1);

  const elem = document.getElementById(`horas-dia-${turnoIndex}`);
  if (elem) {
    elem.innerHTML = `
      ⏱️ <strong>${horas} hrs</strong> por día ×
      <strong>${diasCount}</strong> día(s) =
      <strong>${horasSemanal} hrs/semana</strong>
    `;
  }
}

function renderPaso3() {
  return `
    <div>
      <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 var(--space-3); color: var(--c-secondary);">
        Políticas de Asistencia
      </h3>
      <p style="color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-5); font-size: 14px;">
        Define las tolerancias para entrada y salida
      </p>

      <div style="margin-bottom: var(--space-5);">
        <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
          Tolerancia de entrada (minutos)
        </label>
        <input
          type="number"
          id="wizard-tolerancia-entrada"
          value="${wizardData.tolerancia_entrada_min}"
          min="0"
          max="60"
          style="width: 100%; padding: 12px 16px; border: 1px solid var(--perf-border); border-radius: 8px; font-size: 14px;"
        >
        <p style="font-size: 12px; opacity: 0.6; margin: var(--space-2) 0 0;">
          Empleados pueden llegar hasta ${wizardData.tolerancia_entrada_min} minutos tarde sin penalización
        </p>
      </div>

      <div style="margin-bottom: var(--space-5);">
        <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
          Tolerancia de salida (minutos)
        </label>
        <input
          type="number"
          id="wizard-tolerancia-salida"
          value="${wizardData.tolerancia_salida_min}"
          min="0"
          max="60"
          style="width: 100%; padding: 12px 16px; border: 1px solid var(--perf-border); border-radius: 8px; font-size: 14px;"
        >
        <p style="font-size: 12px; opacity: 0.6; margin: var(--space-2) 0 0;">
          Empleados pueden salir hasta ${wizardData.tolerancia_salida_min} minutos después sin penalización
        </p>
      </div>

      <div style="margin-bottom: var(--space-5);">
        <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: var(--space-2);">
          Horas semanales esperadas
        </label>
        <input
          type="number"
          id="wizard-horas-semanales"
          value="${wizardData.horas_semanales_esperadas}"
          min="1"
          max="168"
          step="0.5"
          style="width: 100%; padding: 12px 16px; border: 1px solid var(--perf-border); border-radius: 8px; font-size: 14px;"
        >
        <div id="horas-calculadas" style="margin-top: var(--space-2); padding: var(--space-3); background: var(--perf-bg); border-radius: 8px; font-size: 13px;">
          <!-- Se llenará con JS -->
        </div>
      </div>

      <div style="padding: var(--space-4); background: #00D98F10; border: 1px solid #00D98F30; border-radius: 8px;">
        <div style="font-size: 13px; line-height: 1.6;">
          💡 <strong>Consejo:</strong> Las tolerancias ayudan a evitar marcar como "tarde"
          a empleados que llegaron casi a tiempo. Esto reduce fricción y mejora la moral del equipo.
        </div>
      </div>

      <script>
        setTimeout(() => {
          let totalHoras = 0;
          wizardData.turnos.forEach(turno => {
            const [h1, m1] = turno.hora_inicio.split(':').map(Number);
            const [h2, m2] = turno.hora_fin.split(':').map(Number);
            let minutos = 0;

            if (turno.cruza_medianoche) {
              minutos = (24 * 60 - (h1 * 60 + m1)) + (h2 * 60 + m2);
            } else {
              minutos = (h2 * 60 + m2) - (h1 * 60 + m1);
            }

            const horasPorDia = minutos / 60;
            totalHoras += horasPorDia * turno.dias.length;
          });

          const elem = document.getElementById('horas-calculadas');
          if (elem) {
            elem.innerHTML = totalHoras > 0
              ? '✓ Calculado automáticamente: <strong>' + totalHoras.toFixed(1) + ' hrs/semana</strong>'
              : 'Configura turnos primero para calcular automáticamente';
          }
        }, 100);
      <\/script>
    </div>
  `;
}

function renderPaso4() {
  let totalHoras = 0;
  wizardData.turnos.forEach(turno => {
    const [h1, m1] = turno.hora_inicio.split(':').map(Number);
    const [h2, m2] = turno.hora_fin.split(':').map(Number);
    let minutos = 0;

    if (turno.cruza_medianoche) {
      minutos = (24 * 60 - (h1 * 60 + m1)) + (h2 * 60 + m2);
    } else {
      minutos = (h2 * 60 + m2) - (h1 * 60 + m1);
    }

    totalHoras += (minutos / 60) * turno.dias.length;
  });

  let turnosHTML = '';
  wizardData.turnos.forEach((turno, index) => {
    const diasMap = {1:'Lun', 2:'Mar', 3:'Mié', 4:'Jue', 5:'Vie', 6:'Sáb', 7:'Dom'};
    const diasLabels = turno.dias.map(d => diasMap[d]).join(', ');

    turnosHTML += `
      <div style="padding: var(--space-3); background: var(--perf-bg); border-radius: 8px; margin-bottom: var(--space-2);">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: var(--space-1);">
          📅 ${turno.nombre_turno}
        </div>
        <div style="font-size: 12px; opacity: 0.8;">
          ${diasLabels}: ${turno.hora_inicio} - ${turno.hora_fin}
          ${turno.cruza_medianoche ? ' 🌙' : ''}
        </div>
      </div>
    `;
  });

  return `
    <div>
      <h3 style="font-size: 18px; font-weight: 700; margin: 0 0 var(--space-3); color: var(--c-secondary);">
        📋 Resumen de la Jornada
      </h3>
      <p style="color: var(--c-body); opacity: 0.7; margin: 0 0 var(--space-5); font-size: 14px;">
        Revisa todos los detalles antes de guardar
      </p>

      <div style="margin-bottom: var(--space-5);">
        <div style="font-size: 13px; font-weight: 600; opacity: 0.6; margin-bottom: var(--space-2); text-transform: uppercase;">
          Información básica
        </div>
        <div style="padding: var(--space-4); background: var(--perf-bg); border-radius: 8px;">
          <div style="margin-bottom: var(--space-2);">
            <strong>Nombre:</strong> ${wizardData.nombre || '(Sin nombre)'}
          </div>
          ${wizardData.codigo_corto ? `
            <div style="margin-bottom: var(--space-2);">
              <strong>Código:</strong> ${wizardData.codigo_corto}
            </div>
          ` : ''}
          ${wizardData.descripcion ? `
            <div>
              <strong>Descripción:</strong> ${wizardData.descripcion}
            </div>
          ` : ''}
        </div>
      </div>

      <div style="margin-bottom: var(--space-5);">
        <div style="font-size: 13px; font-weight: 600; opacity: 0.6; margin-bottom: var(--space-2); text-transform: uppercase;">
          Turnos configurados
        </div>
        ${turnosHTML}
      </div>

      <div style="margin-bottom: var(--space-5);">
        <div style="font-size: 13px; font-weight: 600; opacity: 0.6; margin-bottom: var(--space-2); text-transform: uppercase;">
          Políticas
        </div>
        <div style="padding: var(--space-4); background: var(--perf-bg); border-radius: 8px;">
          <div style="margin-bottom: var(--space-2);">
            ⏱️ <strong>${totalHoras.toFixed(1)} hrs/semana</strong>
          </div>
          <div style="margin-bottom: var(--space-2);">
            🔔 Tolerancia: ${wizardData.tolerancia_entrada_min} min entrada / ${wizardData.tolerancia_salida_min} min salida
          </div>
        </div>
      </div>

      <div style="padding: var(--space-4); background: var(--c-accent)10; border: 1px solid var(--c-accent)30; border-radius: 8px; text-align: center;">
        <div style="font-size: 14px;">
          ✅ Todo listo para crear la jornada
        </div>
      </div>
    </div>
  `;
}

function validarPasoActual() {
  switch (wizardCurrentStep) {
    case 1:
      const nombre = document.getElementById('wizard-nombre').value.trim();
      if (!nombre) {
        alert('Por favor ingresa un nombre para la jornada');
        return false;
      }
      return true;

    case 2:
      if (wizardData.turnos.length === 0) {
        alert('Debes configurar al menos un turno');
        return false;
      }

      for (let i = 0; i < wizardData.turnos.length; i++) {
        const turno = wizardData.turnos[i];
        if (turno.dias.length === 0) {
          alert(`Turno ${i + 1}: debes seleccionar al menos un día`);
          return false;
        }
      }
      return true;

    case 3:
      return true;

    case 4:
      return true;

    default:
      return true;
  }
}

function guardarDatosPaso() {
  switch (wizardCurrentStep) {
    case 1:
      wizardData.nombre = document.getElementById('wizard-nombre').value.trim();
      wizardData.descripcion = document.getElementById('wizard-descripcion').value.trim();
      wizardData.codigo_corto = document.getElementById('wizard-codigo').value.trim();
      wizardData.color_hex = document.getElementById('wizard-color').value;
      wizardData.tipo_jornada = 'fija'; // Siempre fija (simplificado)
      break;

    case 2:
      break;

    case 3:
      wizardData.tolerancia_entrada_min = parseInt(document.getElementById('wizard-tolerancia-entrada').value) || 15;
      wizardData.tolerancia_salida_min = parseInt(document.getElementById('wizard-tolerancia-salida').value) || 5;
      wizardData.horas_semanales_esperadas = parseFloat(document.getElementById('wizard-horas-semanales').value) || 40;
      break;
  }
}

function submitCrearJornada() {
  const formData = new FormData();
  formData.append('action', 'crear_jornada');
  formData.append('nombre', wizardData.nombre);
  formData.append('descripcion', wizardData.descripcion);
  formData.append('codigo_corto', wizardData.codigo_corto);
  formData.append('tipo_jornada', wizardData.tipo_jornada);
  formData.append('horas_semanales_esperadas', wizardData.horas_semanales_esperadas);
  formData.append('tolerancia_entrada_min', wizardData.tolerancia_entrada_min);
  formData.append('tolerancia_salida_min', wizardData.tolerancia_salida_min);
  formData.append('color_hex', wizardData.color_hex);
  formData.append('turnos', JSON.stringify(wizardData.turnos));

  fetch('a-desempeno-dashboard.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('¡Jornada creada exitosamente! 🎉');
      cerrarModalJornada();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Error al guardar la jornada');
  });
}

function abrirModalAsignarEmpleados(jornadaId, jornadaNombre) {
  document.getElementById('asignar-jornada-id').value = jornadaId;
  document.getElementById('asignar-jornada-nombre').textContent = `Jornada: ${jornadaNombre}`;
  document.getElementById('modalAsignarEmpleados').style.display = 'flex';
}

function cerrarModalAsignar() {
  document.getElementById('modalAsignarEmpleados').style.display = 'none';
}

function submitAsignarEmpleados(event) {
  event.preventDefault();

  const jornadaId = document.getElementById('asignar-jornada-id').value;
  const fechaInicio = document.getElementById('asignar-fecha-inicio').value;
  const fechaFin = document.getElementById('asignar-fecha-fin').value;

  const checkboxes = document.querySelectorAll('#lista-empleados-asignar input[type="checkbox"]:checked');
  const empleados = Array.from(checkboxes).map(cb => ({
    persona_id: cb.value,
    fecha_inicio: fechaInicio,
    fecha_fin: fechaFin || null,
    notas: ''
  }));

  if (empleados.length === 0) {
    alert('Selecciona al menos un empleado');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'asignar_empleados');
  formData.append('jornada_id', jornadaId);
  formData.append('empleados', JSON.stringify(empleados));

  fetch('a-desempeno-dashboard.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      cerrarModalAsignar();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Error al asignar empleados');
  });
}

function verDetallesJornada(jornadaId) {
  // Obtener datos de la jornada
  fetch(`a-desempeno-dashboard.php?action=get_jornada&jornada_id=${jornadaId}`)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        mostrarDetallesJornada(data.jornada, data.turnos, data.empleados_asignados);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error al cargar los detalles');
    });
}

function mostrarDetallesJornada(jornada, turnos, empleados) {
  const diasMap = {1:'Lun', 2:'Mar', 3:'Mié', 4:'Jue', 5:'Vie', 6:'Sáb', 7:'Dom'};

  // Agrupar turnos por nombre
  const turnosAgrupados = {};
  turnos.forEach(turno => {
    const key = turno.nombre_turno || 'Sin nombre';
    if (!turnosAgrupados[key]) {
      turnosAgrupados[key] = {
        nombre: key,
        dias: [],
        hora_inicio: turno.hora_inicio,
        hora_fin: turno.hora_fin,
        cruza_medianoche: turno.cruza_medianoche
      };
    }
    turnosAgrupados[key].dias.push(turno.dia_semana);
  });

  let turnosHTML = '';
  Object.values(turnosAgrupados).forEach(turno => {
    const diasLabels = turno.dias.sort((a,b) => a-b).map(d => diasMap[d]).join(', ');
    const horaInicio = turno.hora_inicio.substring(0, 5);
    const horaFin = turno.hora_fin.substring(0, 5);

    turnosHTML += `
      <div style="padding: var(--space-3); background: var(--perf-bg); border-radius: 8px; margin-bottom: var(--space-2);">
        <div style="font-weight: 600; margin-bottom: var(--space-1);">${turno.nombre}</div>
        <div style="font-size: 13px; color: var(--c-body);">
          📅 ${diasLabels}
        </div>
        <div style="font-size: 13px; color: var(--c-body);">
          ⏰ ${horaInicio} - ${horaFin} ${turno.cruza_medianoche ? '🌙' : ''}
        </div>
      </div>
    `;
  });

  let empleadosHTML = '';
  if (empleados.length === 0) {
    empleadosHTML = '<div style="text-align: center; padding: var(--space-4); opacity: 0.6;">No hay empleados asignados</div>';
  } else {
    empleados.forEach(emp => {
      empleadosHTML += `
        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3); border: 1px solid var(--perf-border); border-radius: 8px; margin-bottom: var(--space-2);">
          <div>
            <div style="font-weight: 600;">${emp.nombre_persona}</div>
            <div style="font-size: 12px; color: var(--c-body); opacity: 0.7;">
              Desde: ${emp.fecha_inicio}${emp.fecha_fin ? ' - Hasta: ' + emp.fecha_fin : ' (indefinido)'}
            </div>
          </div>
        </div>
      `;
    });
  }

  const contenido = `
    <div style="margin-bottom: var(--space-5);">
      <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3);">
        <div style="width: 12px; height: 12px; border-radius: 50%; background: ${jornada.color_hex};"></div>
        <h3 style="font-size: 20px; font-weight: 700; margin: 0;">${jornada.nombre}</h3>
        ${jornada.codigo_corto ? `<span style="padding: 2px 8px; background: ${jornada.color_hex}20; color: ${jornada.color_hex}; border-radius: 4px; font-size: 11px; font-weight: 600;">${jornada.codigo_corto}</span>` : ''}
      </div>
      ${jornada.descripcion ? `<p style="color: var(--c-body); opacity: 0.8; margin: 0;">${jornada.descripcion}</p>` : ''}
    </div>

    <div style="margin-bottom: var(--space-5);">
      <h4 style="font-size: 14px; font-weight: 600; opacity: 0.6; text-transform: uppercase; margin-bottom: var(--space-3);">Turnos</h4>
      ${turnosHTML}
    </div>

    <div style="margin-bottom: var(--space-5);">
      <h4 style="font-size: 14px; font-weight: 600; opacity: 0.6; text-transform: uppercase; margin-bottom: var(--space-3);">Políticas</h4>
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-3);">
        <div style="padding: var(--space-3); background: var(--perf-bg); border-radius: 8px; text-align: center;">
          <div style="font-size: 24px; font-weight: 700; color: var(--c-accent);">${jornada.horas_semanales_esperadas}</div>
          <div style="font-size: 11px; opacity: 0.7;">hrs/semana</div>
        </div>
        <div style="padding: var(--space-3); background: var(--perf-bg); border-radius: 8px; text-align: center;">
          <div style="font-size: 24px; font-weight: 700; color: var(--c-accent);">${jornada.tolerancia_entrada_min}</div>
          <div style="font-size: 11px; opacity: 0.7;">min entrada</div>
        </div>
        <div style="padding: var(--space-3); background: var(--perf-bg); border-radius: 8px; text-align: center;">
          <div style="font-size: 24px; font-weight: 700; color: var(--c-accent);">${jornada.tolerancia_salida_min}</div>
          <div style="font-size: 11px; opacity: 0.7;">min salida</div>
        </div>
      </div>
    </div>

    <div style="margin-bottom: var(--space-5);">
      <h4 style="font-size: 14px; font-weight: 600; opacity: 0.6; text-transform: uppercase; margin-bottom: var(--space-3);">Empleados Asignados (${empleados.length})</h4>
      ${empleadosHTML}
    </div>

    <div style="display: flex; gap: var(--space-3);">
      <button onclick="cerrarModalDetalles()" style="flex: 1; padding: 12px; background: white; border: 1px solid var(--perf-border); border-radius: 8px; font-weight: 600; cursor: pointer;">
        Cerrar
      </button>
      <button onclick="editarJornadaBasico(${jornada.id})" style="flex: 1; padding: 12px; background: var(--c-accent); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer;">
        ✏️ Editar Info Básica
      </button>
    </div>
  `;

  document.getElementById('detalles-jornada-nombre').textContent = jornada.nombre;
  document.getElementById('detalles-jornada-contenido').innerHTML = contenido;
  document.getElementById('modalDetallesJornada').style.display = 'flex';
}

function cerrarModalDetalles() {
  document.getElementById('modalDetallesJornada').style.display = 'none';
}

function editarJornada(jornadaId) {
  editarJornadaBasico(jornadaId);
}

function editarJornadaBasico(jornadaId) {
  // Obtener datos actuales
  fetch(`a-desempeno-dashboard.php?action=get_jornada&jornada_id=${jornadaId}`)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const jornada = data.jornada;

        // Prompt simple para editar
        const nuevoNombre = prompt('Nombre de la jornada:', jornada.nombre);
        if (!nuevoNombre) return;

        const nuevaDescripcion = prompt('Descripción (opcional):', jornada.descripcion || '');
        const nuevaToleranciaEntrada = prompt('Tolerancia de entrada (minutos):', jornada.tolerancia_entrada_min);
        const nuevaTolerancialida = prompt('Tolerancia de salida (minutos):', jornada.tolerancia_salida_min);

        // Actualizar
        const formData = new FormData();
        formData.append('action', 'editar_jornada');
        formData.append('jornada_id', jornadaId);
        formData.append('nombre', nuevoNombre);
        formData.append('descripcion', nuevaDescripcion);
        formData.append('codigo_corto', jornada.codigo_corto || '');
        formData.append('tipo_jornada', 'fija');
        formData.append('horas_semanales_esperadas', jornada.horas_semanales_esperadas);
        formData.append('tolerancia_entrada_min', nuevaToleranciaEntrada);
        formData.append('tolerancia_salida_min', nuevaTolerancialida);
        formData.append('color_hex', jornada.color_hex);

        fetch('a-desempeno-dashboard.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(result => {
          if (result.success) {
            alert('✅ Jornada actualizada exitosamente');
            location.reload();
          } else {
            alert('Error: ' + result.message);
          }
        })
        .catch(err => {
          console.error(err);
          alert('Error al actualizar');
        });
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error al cargar los datos');
    });
}

// toggleEmpresaForm() eliminada - ahora se usa openMetaModal()

function toggleAreaForm(metaId){
  // Cerrar cualquier otro formulario de área abierto
  document.querySelectorAll('.area-form-wrap').forEach(function(w){
    if (w.id !== 'areaFormWrap_' + metaId){
      w.style.display = 'none';
    }
  });

  const el = document.getElementById('areaFormWrap_' + metaId);
  if(!el) return;

  const isHidden = (el.style.display === 'none' || el.style.display === '');
  el.style.display = isHidden ? 'block' : 'none';

  if (isHidden) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

function togglePersonaForm(metaAreaId){
  // Cerrar cualquier otro formulario personal abierto
  document.querySelectorAll('.persona-form-wrap').forEach(function(w){
    if (w.id !== 'personaFormWrap_' + metaAreaId){
      w.style.display = 'none';
    }
  });

  const el = document.getElementById('personaFormWrap_' + metaAreaId);
  if(!el) return;

  const isHidden = (el.style.display === 'none' || el.style.display === '');
  el.style.display = isHidden ? 'block' : 'none';

  if (isHidden) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

</script>


 
  
  
  
  <script>
document.addEventListener('DOMContentLoaded', function () {
  const toggles = document.querySelectorAll('.person-tasks-toggle');

  toggles.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const panel = document.getElementById(targetId);
      if (!panel) return;

      // Alternar clase y display
      const isOpen = panel.classList.toggle('is-open');

      if (isOpen) {
        panel.style.display = 'flex'; // visible
        // Cambiar texto del botón (opcional)
        const label = btn.querySelector('span:last-child');
        if (label) label.textContent = 'Ocultar metas personales';
      } else {
        panel.style.display = 'none'; // oculto
        const label = btn.querySelector('span:last-child');
        if (label) label.textContent = 'Ver metas personales (<?= count($tasksPersona) ?>)';
      }
    });
  });
});
<\/script>


  
  

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Interceptar formularios de actualización de metas personales
  document.querySelectorAll('form[data-task-update="1"]').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();

      const fd = new FormData(form);
      fd.append('ajax', '1'); // activamos el modo AJAX en el PHP

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn ? submitBtn.textContent : '';

      if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guardando...';
      }

      fetch('a-desempeno-dashboard.php', {
        method: 'POST',
        body: fd
      })
      .then(r => r.json())
      .then(data => {
        // Opcional: podríamos actualizar textos/badges según el estado seleccionado
        if(submitBtn){
          submitBtn.textContent = 'Guardado';
          setTimeout(function(){
            submitBtn.textContent = originalText || 'Guardar';
            submitBtn.disabled = false;
          }, 1200);
        }
      })
      .catch(err => {
        console.error(err);
        if(submitBtn){
          submitBtn.textContent = 'Error';
          setTimeout(function(){
            submitBtn.textContent = originalText || 'Guardar';
            submitBtn.disabled = false;
          }, 1500);
        }
      });
    });
  });
});
<\/script>

<!-- ===== Animated Number Counter for Hero KPIs ===== -->
<script>
function animateNumber(element, target, duration = 1000) {
  const start = parseInt(element.textContent) || 0;
  const range = target - start;
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function: ease-out quart
    const easeProgress = 1 - Math.pow(1 - progress, 4);
    const current = Math.round(start + (range * easeProgress));

    element.textContent = current;

    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }

  requestAnimationFrame(update);
}

// Auto-initialize animated numbers on page load
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-animate-number]').forEach(function(el) {
    const target = parseInt(el.dataset.animateNumber);
    if (!isNaN(target)) {
      animateNumber(el, target, 1200);
    }
  });
});
<\/script>

<!-- ===== Time & Attendance Interactive Features ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Only execute if we're on the time tab
  const timeTab = document.querySelector('.tab-content.is-active');
  if (!timeTab) return;

  // Search and Filter functionality for Attendance List
  const searchInput = timeTab.querySelector('input[placeholder="🔍 Buscar..."]');
  const filterSelect = timeTab.querySelector('select');
  const attendanceItems = timeTab.querySelectorAll('.tab-content > section:first-child > div:last-child > div > div');

  if (searchInput && filterSelect && attendanceItems.length > 0) {
    // Search functionality
    searchInput.addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      filterAttendanceList(searchTerm, filterSelect.value);
    });

    // Filter by status
    filterSelect.addEventListener('change', function(e) {
      const filterValue = e.target.value;
      const searchTerm = searchInput.value.toLowerCase();
      filterAttendanceList(searchTerm, filterValue);
    });

    function filterAttendanceList(searchTerm, filterStatus) {
      let visibleCount = 0;

      attendanceItems.forEach(function(item) {
        const personName = item.querySelector('div > div:nth-child(2) > div:first-child')?.textContent.toLowerCase() || '';
        const personRole = item.querySelector('div > div:nth-child(2) > div:last-child')?.textContent.toLowerCase() || '';

        // Get status from border color
        const borderColor = item.style.border;
        let itemStatus = 'Todos';
        if (borderColor.includes('10, 185, 129') || borderColor.includes('#00D98F')) {
          itemStatus = 'Presentes';
        } else if (borderColor.includes('239, 68, 68') || borderColor.includes('#FF3B6D') || borderColor.includes('220, 38, 38') || borderColor.includes('#FF3B6D')) {
          itemStatus = 'Ausentes';
        } else if (borderColor.includes('245, 158, 11') || borderColor.includes('#FFB020')) {
          itemStatus = 'Tardes';
        }

        // Check if matches search
        const matchesSearch = searchTerm === '' || personName.includes(searchTerm) || personRole.includes(searchTerm);

        // Check if matches filter
        const matchesFilter = filterStatus === 'Todos' || itemStatus === filterStatus;

        // Show/hide item with smooth animation
        if (matchesSearch && matchesFilter) {
          item.style.display = '';
          item.style.opacity = '0';
          setTimeout(function() {
            item.style.transition = 'opacity 0.3s ease';
            item.style.opacity = '1';
          }, visibleCount * 50); // Stagger animation
          visibleCount++;
        } else {
          item.style.opacity = '0';
          setTimeout(function() {
            item.style.display = 'none';
          }, 300);
        }
      });

      // Show "no results" message if needed
      const container = attendanceItems[0]?.parentElement;
      let noResultsMsg = container?.querySelector('.no-results-message');

      if (visibleCount === 0 && container) {
        if (!noResultsMsg) {
          noResultsMsg = document.createElement('div');
          noResultsMsg.className = 'no-results-message';
          noResultsMsg.style.cssText = 'text-align: center; padding: 40px 0; color: #6B7280; opacity: 0.8;';
          noResultsMsg.innerHTML = `
            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
            <p style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">No se encontraron resultados</p>
            <p style="font-size: 14px;">Intenta con otros términos de búsqueda o filtros</p>
          `;
          container.appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = 'block';
      } else if (noResultsMsg) {
        noResultsMsg.style.display = 'none';
      }
    }
  }

  // Auto-refresh functionality (every 2 minutes)
  const refreshButton = timeTab.querySelector('button');
  let autoRefreshInterval;
  let lastUpdateTime = new Date();

  function updateLastUpdateText() {
    const now = new Date();
    const diffMinutes = Math.floor((now - lastUpdateTime) / 60000);
    const updateText = timeTab.querySelector('h1 + p');

    if (updateText) {
      const dateText = updateText.textContent.split('•')[0].trim();
      if (diffMinutes === 0) {
        updateText.textContent = dateText + ' • Última actualización: hace un momento';
      } else if (diffMinutes === 1) {
        updateText.textContent = dateText + ' • Última actualización: hace 1 minuto';
      } else {
        updateText.textContent = dateText + ' • Última actualización: hace ' + diffMinutes + ' minutos';
      }
    }
  }

  // Update time display every 30 seconds
  setInterval(updateLastUpdateText, 30000);

  // Refresh button functionality
  if (refreshButton) {
    refreshButton.addEventListener('click', function() {
      // Add loading animation
      this.style.opacity = '0.6';
      this.style.cursor = 'not-allowed';
      this.textContent = '⏳ Actualizando...';

      // Reload page after animation
      setTimeout(function() {
        window.location.reload();
      }, 800);
    });
  }

  // Auto-refresh every 2 minutes (120000ms)
  autoRefreshInterval = setInterval(function() {
    console.log('Auto-refreshing Time & Attendance data...');
    window.location.reload();
  }, 120000);

  // Clear interval when navigating away from tab
  document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
      }
    });
  });

  // Approval/Rejection button handlers for permissions
  const approvalButtons = timeTab.querySelectorAll('button');
  approvalButtons.forEach(function(btn) {
    if (btn.textContent.includes('Aprobar')) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('¿Confirmar aprobación de este permiso?')) {
          this.textContent = '✓ Aprobado';
          this.style.background = '#00D98F';
          this.disabled = true;
          this.style.cursor = 'not-allowed';

          // Hide rejection button
          const rejectBtn = this.nextElementSibling;
          if (rejectBtn) {
            rejectBtn.style.display = 'none';
          }

          // TODO: Add AJAX call to update database
          console.log('Permission approved - implement AJAX call');
        }
      });
    } else if (btn.textContent.includes('Rechazar')) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const reason = prompt('¿Razón del rechazo? (opcional)');
        if (reason !== null) {
          this.textContent = '✗ Rechazado';
          this.style.background = '#991B1B';
          this.disabled = true;
          this.style.cursor = 'not-allowed';

          // Hide approval button
          const approveBtn = this.previousElementSibling;
          if (approveBtn) {
            approveBtn.style.display = 'none';
          }

          // TODO: Add AJAX call to update database
          console.log('Permission rejected. Reason:', reason || 'No reason provided');
        }
      });
    }
  });

  // "Ver detalles" button functionality
  const detailButtons = timeTab.querySelectorAll('button');
  detailButtons.forEach(function(btn) {
    if (btn.textContent.includes('Ver detalles')) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Get person info from parent container
        const container = this.closest('div[style*="border: 2px solid"]');
        const personName = container?.querySelector('div > div:nth-child(2) > div:first-child')?.textContent || 'N/A';

        // Show modal (simplified version - can be enhanced)
        alert('Vista detallada de ' + personName + '\n\nFuncionalidad completa disponible próximamente con modal avanzado.');

        // TODO: Implement full modal with detailed attendance history, graphs, etc.
        console.log('Show detailed view for:', personName);
      });
    }
  });

  // Smooth scroll to sections
  timeTab.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Add entrance animations for cards
  const cards = timeTab.querySelectorAll('section > div');
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry, index) {
      if (entry.isIntersecting) {
        setTimeout(function() {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, index * 100);
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  cards.forEach(function(card) {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(card);
  });
});
</script>

<?php
// ========================================================================
// SISTEMA DE NOTIFICACIONES TOAST PARA EMPLEADOR
// Notifica en tiempo real cuando llegan nuevas solicitudes de permisos/vacaciones
// ========================================================================
include 'notificaciones_empleador_ui.php';
?>

</body>
</html>