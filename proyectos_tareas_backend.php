<?php
/**
 * BACKEND: Sistema de Proyectos y Tareas
 * Valírica HHRR - API para gestión de proyectos y tareas
 */

// Capturar cualquier output no deseado
ob_start();

session_start();
require 'config.php';

// Limpiar cualquier output previo (warnings, notices, etc)
ob_end_clean();

header('Content-Type: application/json');

// Verificar autenticación - soportar tanto admin como empleado
$user_id = null;
$empleado_id = null;

if (isset($_SESSION['user_id'])) {
    // Admin/proveedor logueado
    $user_id = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['empleado_id'])) {
    // Empleado logueado - obtener su usuario_id
    $empleado_id = (int)$_SESSION['empleado_id'];
    $stmt_user = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ? LIMIT 1");
    $stmt_user->bind_param("i", $empleado_id);
    $stmt_user->execute();
    $row_user = stmt_get_result($stmt_user)->fetch_assoc();
    $stmt_user->close();

    if ($row_user) {
        $user_id = (int)$row_user['usuario_id'];
    }
}

// Permitir también pasar usuario_id por parámetro (para llamadas desde dashboard_equipo)
if (!$user_id && !empty($_GET['usuario_id'])) {
    $user_id = (int)$_GET['usuario_id'];
} elseif (!$user_id && !empty($_POST['usuario_id'])) {
    $user_id = (int)$_POST['usuario_id'];
}

if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ==================== PROYECTOS ====================

        case 'crear_proyecto':
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $lider_id = (int)($_POST['lider_id'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin_estimada = $_POST['fecha_fin_estimada'] ?? null;
            $prioridad = $_POST['prioridad'] ?? 'media';

            if (empty($titulo)) {
                throw new Exception('El título del proyecto es obligatorio');
            }

            if ($lider_id <= 0) {
                throw new Exception('Debe seleccionar un líder para el proyecto');
            }

            // Verificar que el líder pertenece a la empresa
            $stmt_check = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ?");
            $stmt_check->bind_param("ii", $lider_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('El líder seleccionado no es válido');
            }
            $stmt_check->close();

            $stmt = $conn->prepare("
                INSERT INTO proyectos (usuario_id, titulo, descripcion, lider_id, fecha_inicio, fecha_fin_estimada, prioridad, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'planificacion')
            ");

            $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : null;
            $fecha_fin_estimada = !empty($fecha_fin_estimada) ? $fecha_fin_estimada : null;

            $stmt->bind_param("ississs", $user_id, $titulo, $descripcion, $lider_id, $fecha_inicio, $fecha_fin_estimada, $prioridad);
            $stmt->execute();

            $proyecto_id = $conn->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'ok' => true,
                'message' => 'Proyecto creado correctamente',
                'proyecto_id' => $proyecto_id
            ]);
            break;

        case 'actualizar_proyecto':
            $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $lider_id = (int)($_POST['lider_id'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin_estimada = $_POST['fecha_fin_estimada'] ?? null;
            $estado = $_POST['estado'] ?? 'planificacion';
            $prioridad = $_POST['prioridad'] ?? 'media';

            if ($proyecto_id <= 0) {
                throw new Exception('ID de proyecto inválido');
            }

            if (empty($titulo)) {
                throw new Exception('El título del proyecto es obligatorio');
            }

            // Verificar que el proyecto pertenece a la empresa
            $stmt_check = $conn->prepare("SELECT id FROM proyectos WHERE id = ? AND usuario_id = ?");
            $stmt_check->bind_param("ii", $proyecto_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('Proyecto no encontrado');
            }
            $stmt_check->close();

            $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : null;
            $fecha_fin_estimada = !empty($fecha_fin_estimada) ? $fecha_fin_estimada : null;

            $stmt = $conn->prepare("
                UPDATE proyectos
                SET titulo = ?, descripcion = ?, lider_id = ?, fecha_inicio = ?, fecha_fin_estimada = ?, estado = ?, prioridad = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->bind_param("ssissssii", $titulo, $descripcion, $lider_id, $fecha_inicio, $fecha_fin_estimada, $estado, $prioridad, $proyecto_id, $user_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Proyecto actualizado correctamente'
            ]);
            break;

        case 'eliminar_proyecto':
            $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);

            if ($proyecto_id <= 0) {
                throw new Exception('ID de proyecto inválido');
            }

            // Las tareas se eliminan en cascada por FK
            $stmt = $conn->prepare("DELETE FROM proyectos WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $proyecto_id, $user_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception('Proyecto no encontrado');
            }

            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Proyecto eliminado correctamente'
            ]);
            break;

        case 'cambiar_estado_proyecto':
            $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
            $estado = $_POST['estado'] ?? '';

            $estados_validos = ['planificacion', 'en_progreso', 'pausado', 'completado', 'cancelado'];
            if (!in_array($estado, $estados_validos)) {
                throw new Exception('Estado no válido');
            }

            $stmt = $conn->prepare("UPDATE proyectos SET estado = ? WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("sii", $estado, $proyecto_id, $user_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Estado del proyecto actualizado'
            ]);
            break;

        case 'obtener_proyectos':
            $estado_filtro = $_GET['estado'] ?? '';
            $lider_filtro = (int)($_GET['lider_id'] ?? 0);

            $sql = "
                SELECT
                    p.*,
                    e.nombre_persona as lider_nombre,
                    e.cargo as lider_cargo,
                    (SELECT COUNT(*) FROM tareas WHERE proyecto_id = p.id) as total_tareas,
                    (SELECT COUNT(*) FROM tareas WHERE proyecto_id = p.id AND estado = 'completada') as tareas_completadas,
                    (SELECT COUNT(*) FROM tareas WHERE proyecto_id = p.id AND estado = 'en_progreso') as tareas_en_progreso,
                    (SELECT COUNT(*) FROM tareas WHERE proyecto_id = p.id AND deadline < CURDATE() AND estado NOT IN ('completada', 'cancelada')) as tareas_vencidas
                FROM proyectos p
                LEFT JOIN equipo e ON p.lider_id = e.id
                WHERE p.usuario_id = ?
            ";

            $params = [$user_id];
            $types = "i";

            if (!empty($estado_filtro)) {
                $sql .= " AND p.estado = ?";
                $params[] = $estado_filtro;
                $types .= "s";
            }

            if ($lider_filtro > 0) {
                $sql .= " AND p.lider_id = ?";
                $params[] = $lider_filtro;
                $types .= "i";
            }

            $sql .= " ORDER BY
                FIELD(p.prioridad, 'critica', 'alta', 'media', 'baja'),
                FIELD(p.estado, 'en_progreso', 'planificacion', 'pausado', 'completado', 'cancelado'),
                p.fecha_fin_estimada ASC
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $proyectos = [];
            while ($row = $result->fetch_assoc()) {
                $row['porcentaje_completado'] = $row['total_tareas'] > 0
                    ? round(($row['tareas_completadas'] / $row['total_tareas']) * 100)
                    : 0;
                $proyectos[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'proyectos' => $proyectos
            ]);
            break;

        case 'obtener_proyecto':
            $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);

            $stmt = $conn->prepare("
                SELECT p.*, e.nombre_persona as lider_nombre
                FROM proyectos p
                LEFT JOIN equipo e ON p.lider_id = e.id
                WHERE p.id = ? AND p.usuario_id = ?
            ");
            $stmt->bind_param("ii", $proyecto_id, $user_id);
            $stmt->execute();
            $proyecto = stmt_get_result($stmt)->fetch_assoc();
            $stmt->close();

            if (!$proyecto) {
                throw new Exception('Proyecto no encontrado');
            }

            echo json_encode([
                'success' => true,
                'proyecto' => $proyecto
            ]);
            break;

        // ==================== TAREAS ====================

        case 'crear_tarea':
            $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $responsable_id = (int)($_POST['responsable_id'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $deadline = $_POST['deadline'] ?? null;
            $prioridad = $_POST['prioridad'] ?? 'media';
            $horas_estimadas = !empty($_POST['horas_estimadas']) ? floatval($_POST['horas_estimadas']) : null;
            $area_id = !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null;

            if (empty($titulo)) {
                throw new Exception('El título de la tarea es obligatorio');
            }

            if ($proyecto_id <= 0) {
                throw new Exception('Debe seleccionar un proyecto');
            }

            if ($responsable_id <= 0) {
                throw new Exception('Debe asignar un responsable');
            }

            // Verificar que el proyecto pertenece a la empresa
            $stmt_check = $conn->prepare("SELECT id FROM proyectos WHERE id = ? AND usuario_id = ?");
            $stmt_check->bind_param("ii", $proyecto_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('Proyecto no válido');
            }
            $stmt_check->close();

            // Verificar que el responsable pertenece a la empresa
            $stmt_check2 = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ?");
            $stmt_check2->bind_param("ii", $responsable_id, $user_id);
            $stmt_check2->execute();
            if (stmt_get_result($stmt_check2)->num_rows === 0) {
                throw new Exception('Responsable no válido');
            }
            $stmt_check2->close();

            // Obtener el siguiente orden
            $stmt_orden = $conn->prepare("SELECT COALESCE(MAX(orden), 0) + 1 as next_orden FROM tareas WHERE proyecto_id = ?");
            $stmt_orden->bind_param("i", $proyecto_id);
            $stmt_orden->execute();
            $orden = (int)stmt_get_result($stmt_orden)->fetch_assoc()['next_orden'];
            $stmt_orden->close();

            $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : null;
            $deadline = !empty($deadline) ? $deadline : null;

            $stmt = $conn->prepare("
                INSERT INTO tareas (proyecto_id, titulo, descripcion, responsable_id, fecha_inicio, deadline, prioridad, horas_estimadas, orden, estado, area_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)
            ");
            $stmt->bind_param("ississsdii", $proyecto_id, $titulo, $descripcion, $responsable_id, $fecha_inicio, $deadline, $prioridad, $horas_estimadas, $orden, $area_id);
            $stmt->execute();

            $tarea_id = $conn->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'ok' => true,
                'message' => 'Tarea creada correctamente',
                'tarea_id' => $tarea_id
            ]);
            break;

        case 'actualizar_tarea':
            $tarea_id = (int)($_POST['tarea_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $responsable_id = (int)($_POST['responsable_id'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $deadline = $_POST['deadline'] ?? null;
            $estado = $_POST['estado'] ?? 'pendiente';
            $prioridad = $_POST['prioridad'] ?? 'media';
            $horas_estimadas = !empty($_POST['horas_estimadas']) ? floatval($_POST['horas_estimadas']) : null;
            $horas_reales = !empty($_POST['horas_reales']) ? floatval($_POST['horas_reales']) : null;

            if ($tarea_id <= 0) {
                throw new Exception('ID de tarea inválido');
            }

            if (empty($titulo)) {
                throw new Exception('El título de la tarea es obligatorio');
            }

            // Verificar que la tarea pertenece a un proyecto de la empresa
            $stmt_check = $conn->prepare("
                SELECT t.id FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                WHERE t.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->bind_param("ii", $tarea_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('Tarea no encontrada');
            }
            $stmt_check->close();

            $fecha_inicio = !empty($fecha_inicio) ? $fecha_inicio : null;
            $deadline = !empty($deadline) ? $deadline : null;

            // Si se marca como completada, registrar fecha de finalización
            $fecha_finalizacion_real = null;
            if ($estado === 'completada') {
                $fecha_finalizacion_real = date('Y-m-d');
            }

            $stmt = $conn->prepare("
                UPDATE tareas
                SET titulo = ?, descripcion = ?, responsable_id = ?, fecha_inicio = ?, deadline = ?,
                    estado = ?, prioridad = ?, horas_estimadas = ?, horas_reales = ?, fecha_finalizacion_real = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssissssdisi", $titulo, $descripcion, $responsable_id, $fecha_inicio, $deadline, $estado, $prioridad, $horas_estimadas, $horas_reales, $fecha_finalizacion_real, $tarea_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Tarea actualizada correctamente'
            ]);
            break;

        case 'eliminar_tarea':
            $tarea_id = (int)($_POST['tarea_id'] ?? 0);

            if ($tarea_id <= 0) {
                throw new Exception('ID de tarea inválido');
            }

            // Verificar pertenencia
            $stmt_check = $conn->prepare("
                SELECT t.id FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                WHERE t.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->bind_param("ii", $tarea_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('Tarea no encontrada');
            }
            $stmt_check->close();

            $stmt = $conn->prepare("DELETE FROM tareas WHERE id = ?");
            $stmt->bind_param("i", $tarea_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Tarea eliminada correctamente'
            ]);
            break;

        case 'cambiar_estado_tarea':
            $tarea_id = (int)($_POST['tarea_id'] ?? 0);
            $estado = $_POST['estado'] ?? '';

            $estados_validos = ['pendiente', 'en_progreso', 'completada', 'cancelada'];
            if (!in_array($estado, $estados_validos)) {
                throw new Exception('Estado no válido');
            }

            // Verificar pertenencia
            $stmt_check = $conn->prepare("
                SELECT t.id FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                WHERE t.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->bind_param("ii", $tarea_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('Tarea no encontrada');
            }
            $stmt_check->close();

            $fecha_finalizacion_real = ($estado === 'completada') ? date('Y-m-d') : null;

            $stmt = $conn->prepare("UPDATE tareas SET estado = ?, fecha_finalizacion_real = ? WHERE id = ?");
            $stmt->bind_param("ssi", $estado, $fecha_finalizacion_real, $tarea_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'ok' => true,
                'message' => 'Estado de la tarea actualizado'
            ]);
            break;

        case 'obtener_tareas':
            $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
            $responsable_id = (int)($_GET['responsable_id'] ?? 0);
            $estado_filtro = $_GET['estado'] ?? '';

            $sql = "
                SELECT
                    t.*,
                    p.titulo as proyecto_titulo,
                    e.nombre_persona as responsable_nombre,
                    e.cargo as responsable_cargo,
                    CASE
                        WHEN t.estado = 'completada' THEN 'completada'
                        WHEN t.deadline < CURDATE() AND t.estado NOT IN ('completada', 'cancelada') THEN 'vencida'
                        WHEN t.deadline = CURDATE() THEN 'vence_hoy'
                        WHEN t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'proxima_vencer'
                        ELSE 'en_tiempo'
                    END as indicador_deadline,
                    DATEDIFF(t.deadline, CURDATE()) as dias_restantes
                FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                LEFT JOIN equipo e ON t.responsable_id = e.id
                WHERE p.usuario_id = ?
            ";

            $params = [$user_id];
            $types = "i";

            if ($proyecto_id > 0) {
                $sql .= " AND t.proyecto_id = ?";
                $params[] = $proyecto_id;
                $types .= "i";
            }

            if ($responsable_id > 0) {
                $sql .= " AND t.responsable_id = ?";
                $params[] = $responsable_id;
                $types .= "i";
            }

            if (!empty($estado_filtro)) {
                $sql .= " AND t.estado = ?";
                $params[] = $estado_filtro;
                $types .= "s";
            }

            $sql .= " ORDER BY t.proyecto_id, t.orden, t.deadline ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $tareas = [];
            while ($row = $result->fetch_assoc()) {
                $tareas[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'tareas' => $tareas
            ]);
            break;

        case 'obtener_tareas_proyecto':
            $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);

            if ($proyecto_id <= 0) {
                throw new Exception('ID de proyecto inválido');
            }

            // Verificar pertenencia
            $stmt_check = $conn->prepare("SELECT id FROM proyectos WHERE id = ? AND usuario_id = ?");
            $stmt_check->bind_param("ii", $proyecto_id, $user_id);
            $stmt_check->execute();
            if (stmt_get_result($stmt_check)->num_rows === 0) {
                throw new Exception('Proyecto no encontrado');
            }
            $stmt_check->close();

            $stmt = $conn->prepare("
                SELECT
                    t.*,
                    e.nombre_persona as responsable_nombre,
                    CASE
                        WHEN t.estado = 'completada' THEN 'completada'
                        WHEN t.deadline < CURDATE() AND t.estado NOT IN ('completada', 'cancelada') THEN 'vencida'
                        WHEN t.deadline = CURDATE() THEN 'vence_hoy'
                        WHEN t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'proxima_vencer'
                        ELSE 'en_tiempo'
                    END as indicador_deadline,
                    DATEDIFF(t.deadline, CURDATE()) as dias_restantes
                FROM tareas t
                LEFT JOIN equipo e ON t.responsable_id = e.id
                WHERE t.proyecto_id = ?
                ORDER BY t.orden, t.deadline ASC
            ");
            $stmt->bind_param("i", $proyecto_id);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $tareas = [];
            while ($row = $result->fetch_assoc()) {
                $tareas[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'tareas' => $tareas
            ]);
            break;

        // ==================== ESTADÍSTICAS ====================

        case 'obtener_estadisticas':
            // Estadísticas generales
            $stats = [
                'total_proyectos' => 0,
                'proyectos_activos' => 0,
                'proyectos_completados' => 0,
                'total_tareas' => 0,
                'tareas_pendientes' => 0,
                'tareas_en_progreso' => 0,
                'tareas_completadas' => 0,
                'tareas_vencidas' => 0,
                'promedio_completado' => 0
            ];

            // Contar proyectos
            $stmt = $conn->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN estado IN ('planificacion', 'en_progreso') THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados
                FROM proyectos WHERE usuario_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = stmt_get_result($stmt)->fetch_assoc();
            $stats['total_proyectos'] = (int)$row['total'];
            $stats['proyectos_activos'] = (int)$row['activos'];
            $stats['proyectos_completados'] = (int)$row['completados'];
            $stmt->close();

            // Contar tareas
            $stmt = $conn->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN t.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                    SUM(CASE WHEN t.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN t.deadline < CURDATE() AND t.estado NOT IN ('completada', 'cancelada') THEN 1 ELSE 0 END) as vencidas
                FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                WHERE p.usuario_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = stmt_get_result($stmt)->fetch_assoc();
            $stats['total_tareas'] = (int)$row['total'];
            $stats['tareas_pendientes'] = (int)$row['pendientes'];
            $stats['tareas_en_progreso'] = (int)$row['en_progreso'];
            $stats['tareas_completadas'] = (int)$row['completadas'];
            $stats['tareas_vencidas'] = (int)$row['vencidas'];
            $stmt->close();

            if ($stats['total_tareas'] > 0) {
                $stats['promedio_completado'] = round(($stats['tareas_completadas'] / $stats['total_tareas']) * 100);
            }

            echo json_encode([
                'success' => true,
                'estadisticas' => $stats
            ]);
            break;

        case 'obtener_tareas_por_responsable':
            $stmt = $conn->prepare("
                SELECT
                    e.id as responsable_id,
                    e.nombre_persona,
                    e.cargo,
                    COUNT(t.id) as total_tareas,
                    SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN t.estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                    SUM(CASE WHEN t.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN t.deadline < CURDATE() AND t.estado NOT IN ('completada', 'cancelada') THEN 1 ELSE 0 END) as vencidas
                FROM equipo e
                LEFT JOIN tareas t ON e.id = t.responsable_id
                LEFT JOIN proyectos p ON t.proyecto_id = p.id AND p.usuario_id = ?
                WHERE e.usuario_id = ?
                GROUP BY e.id, e.nombre_persona, e.cargo
                ORDER BY total_tareas DESC
            ");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $responsables = [];
            while ($row = $result->fetch_assoc()) {
                $responsables[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'responsables' => $responsables
            ]);
            break;

        // =====================================================
        // ENDPOINTS PARA DASHBOARD DE EMPLEADO
        // =====================================================

        // Obtener tareas asignadas a un empleado específico
        case 'obtener_tareas_empleado':
            $responsable_id = (int)($_GET['responsable_id'] ?? $_POST['responsable_id'] ?? 0);
            $estado_filtro = $_GET['estado'] ?? $_POST['estado'] ?? '';
            $prioridad_filtro = $_GET['prioridad'] ?? $_POST['prioridad'] ?? '';

            if ($responsable_id <= 0) {
                throw new Exception('ID de responsable requerido');
            }

            // Usar consulta directa en lugar de vista (más portable)
            $sql = "
                SELECT
                    t.*,
                    p.titulo as proyecto_titulo,
                    e.nombre_persona as responsable_nombre,
                    CASE
                        WHEN t.estado = 'completada' THEN 'completada'
                        WHEN t.deadline < CURDATE() AND t.estado NOT IN ('completada', 'cancelada') THEN 'vencida'
                        WHEN t.deadline = CURDATE() THEN 'vence_hoy'
                        WHEN t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'proxima_vencer'
                        ELSE 'en_tiempo'
                    END as indicador_deadline,
                    DATEDIFF(t.deadline, CURDATE()) as dias_restantes
                FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                LEFT JOIN equipo e ON t.responsable_id = e.id
                WHERE t.responsable_id = ?
            ";

            $params = [$responsable_id];
            $types = "i";

            if (!empty($estado_filtro)) {
                $sql .= " AND t.estado = ?";
                $params[] = $estado_filtro;
                $types .= "s";
            }

            if (!empty($prioridad_filtro)) {
                $sql .= " AND t.prioridad = ?";
                $params[] = $prioridad_filtro;
                $types .= "s";
            }

            $sql .= " ORDER BY
                CASE WHEN t.deadline < CURDATE() AND t.estado NOT IN ('completada', 'cancelada') THEN 1
                     WHEN t.deadline = CURDATE() THEN 2
                     WHEN t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 3
                     ELSE 4 END,
                FIELD(t.prioridad, 'critica', 'alta', 'media', 'baja'),
                t.deadline ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $tareas = [];
            while ($row = $result->fetch_assoc()) {
                $tareas[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'tareas' => $tareas
            ]);
            break;

        // Obtener proyectos donde el empleado participa o es líder
        case 'obtener_mis_proyectos':
            $empleado_id = (int)($_GET['empleado_id'] ?? $_POST['empleado_id'] ?? 0);
            $estado_filtro = $_GET['estado'] ?? $_POST['estado'] ?? '';

            if ($empleado_id <= 0) {
                throw new Exception('ID de empleado requerido');
            }

            // Primero obtenemos los proyectos donde es líder o tiene tareas
            $sql = "
                SELECT DISTINCT
                    p.id,
                    p.titulo,
                    p.descripcion,
                    p.lider_id,
                    e.nombre_persona as lider_nombre,
                    p.fecha_inicio,
                    p.fecha_fin_estimada,
                    p.estado,
                    p.prioridad,
                    COUNT(t.id) as total_tareas,
                    SUM(CASE WHEN t.estado = 'completada' THEN 1 ELSE 0 END) as tareas_completadas,
                    CASE
                        WHEN COUNT(t.id) = 0 THEN 0
                        ELSE ROUND((SUM(CASE WHEN t.estado = 'completada' THEN 1 ELSE 0 END) / COUNT(t.id)) * 100, 0)
                    END as porcentaje_completado
                FROM proyectos p
                LEFT JOIN equipo e ON p.lider_id = e.id
                LEFT JOIN tareas t ON p.id = t.proyecto_id AND t.estado != 'cancelada'
                WHERE (p.lider_id = ? OR EXISTS (
                    SELECT 1 FROM tareas t2 WHERE t2.proyecto_id = p.id AND t2.responsable_id = ?
                ))
            ";

            $params = [$empleado_id, $empleado_id];
            $types = "ii";

            if (!empty($estado_filtro)) {
                $sql .= " AND p.estado = ?";
                $params[] = $estado_filtro;
                $types .= "s";
            }

            $sql .= " GROUP BY p.id, p.titulo, p.descripcion, p.lider_id, e.nombre_persona,
                      p.fecha_inicio, p.fecha_fin_estimada, p.estado, p.prioridad
                      ORDER BY
                        CASE WHEN p.lider_id = ? THEN 0 ELSE 1 END,
                        FIELD(p.estado, 'en_progreso', 'planificacion', 'pausado', 'completado', 'cancelado'),
                        p.fecha_fin_estimada ASC";
            $params[] = $empleado_id;
            $types .= "i";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $proyectos = [];
            while ($row = $result->fetch_assoc()) {
                // ⭐ Obtener TODAS las tareas del proyecto con info completa
                $stmt_tareas = $conn->prepare("
                    SELECT
                        t.id,
                        t.titulo,
                        t.estado,
                        t.deadline,
                        t.prioridad,
                        t.responsable_id,
                        COALESCE(e.nombre_persona, 'Sin asignar') as responsable_nombre,
                        DATEDIFF(t.deadline, CURDATE()) as dias_restantes
                    FROM tareas t
                    LEFT JOIN equipo e ON t.responsable_id = e.id
                    WHERE t.proyecto_id = ? AND t.estado != 'cancelada'
                    ORDER BY
                        FIELD(t.estado, 'en_progreso', 'pendiente', 'completada'),
                        t.deadline ASC
                ");
                $stmt_tareas->bind_param("i", $row['id']);
                $stmt_tareas->execute();
                $res_tareas = stmt_get_result($stmt_tareas);

                $todas_tareas = [];
                while ($tarea = $res_tareas->fetch_assoc()) {
                    // Marcar si es mi tarea
                    $tarea['es_mi_tarea'] = ((int)$tarea['responsable_id'] === $empleado_id);
                    // Indicador de deadline
                    $dias = $tarea['dias_restantes'];
                    if ($dias !== null && $tarea['estado'] !== 'completada') {
                        if ($dias < 0) {
                            $tarea['indicador_deadline'] = 'vencida';
                        } elseif ($dias === 0) {
                            $tarea['indicador_deadline'] = 'vence_hoy';
                        } elseif ($dias <= 3) {
                            $tarea['indicador_deadline'] = 'proxima';
                        } else {
                            $tarea['indicador_deadline'] = 'normal';
                        }
                    } else {
                        $tarea['indicador_deadline'] = 'normal';
                    }
                    $todas_tareas[] = $tarea;
                }
                $stmt_tareas->close();

                $row['todas_tareas'] = $todas_tareas;
                // Mantener compatibilidad: mis_tareas solo las mías
                $row['mis_tareas'] = array_filter($todas_tareas, function($t) {
                    return $t['es_mi_tarea'];
                });
                $row['mis_tareas'] = array_values($row['mis_tareas']); // Reindexar
                $proyectos[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'proyectos' => $proyectos
            ]);
            break;

        // Obtener proyectos activos para el selector
        case 'obtener_proyectos_activos':
            $sql = "
                SELECT id, titulo, lider_id, estado
                FROM proyectos
                WHERE usuario_id = ? AND estado NOT IN ('completado', 'cancelado')
                ORDER BY titulo ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $proyectos = [];
            while ($row = $result->fetch_assoc()) {
                $proyectos[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'proyectos' => $proyectos
            ]);
            break;

        // Obtener miembros del equipo para asignación
        case 'obtener_miembros_equipo':
            // Verificar si existe la columna 'activo'
            $result_columns = $conn->query("SHOW COLUMNS FROM equipo LIKE 'activo'");
            $has_activo = $result_columns->num_rows > 0;

            $sql = "
                SELECT id, nombre_persona, cargo
                FROM equipo
                WHERE usuario_id = ?
            ";

            if ($has_activo) {
                $sql .= " AND activo = 1";
            }

            $sql .= " ORDER BY nombre_persona ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $miembros = [];
            while ($row = $result->fetch_assoc()) {
                $miembros[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'miembros' => $miembros
            ]);
            break;

        // Obtener áreas de trabajo de la empresa
        case 'obtener_areas_trabajo':
            $stmt = $conn->prepare("
                SELECT id, nombre_area
                FROM areas_trabajo
                WHERE usuario_id = ?
                ORDER BY nombre_area ASC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = stmt_get_result($stmt);

            $areas = [];
            while ($row = $result->fetch_assoc()) {
                $areas[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'ok' => true,
                'areas' => $areas
            ]);
            break;

        // =====================================================
        // ACTUALIZAR CAMPO INDIVIDUAL DE TAREA (SOLO LÍDER)
        // =====================================================
        case 'actualizar_tarea_campo':
            $tarea_id = (int)($_POST['tarea_id'] ?? 0);
            $campo = $_POST['campo'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $solicitante_id = (int)($_POST['empleado_id'] ?? $empleado_id ?? 0);

            if ($tarea_id <= 0) {
                throw new Exception('ID de tarea inválido');
            }

            // Campos permitidos para edición
            $campos_permitidos = ['responsable_id', 'deadline', 'estado'];
            if (!in_array($campo, $campos_permitidos)) {
                throw new Exception('Campo no permitido para edición: ' . $campo);
            }

            // Obtener información de la tarea y el proyecto
            $stmt_info = $conn->prepare("
                SELECT t.id, t.proyecto_id, p.lider_id, p.usuario_id
                FROM tareas t
                INNER JOIN proyectos p ON t.proyecto_id = p.id
                WHERE t.id = ?
            ");
            $stmt_info->bind_param("i", $tarea_id);
            $stmt_info->execute();
            $info = stmt_get_result($stmt_info)->fetch_assoc();
            $stmt_info->close();

            if (!$info) {
                throw new Exception('Tarea no encontrada');
            }

            // Verificar que el proyecto pertenece a la empresa
            if ((int)$info['usuario_id'] !== $user_id) {
                throw new Exception('No tienes permisos para modificar esta tarea');
            }

            // ⭐ VALIDACIÓN CRÍTICA: Solo el líder puede editar
            if ($solicitante_id > 0 && (int)$info['lider_id'] !== $solicitante_id) {
                throw new Exception('Solo el líder del proyecto puede editar las tareas');
            }

            // Validar valor según el campo
            switch ($campo) {
                case 'responsable_id':
                    $valor = (int)$valor;
                    if ($valor <= 0) {
                        throw new Exception('Responsable inválido');
                    }
                    // Verificar que el responsable pertenece a la empresa
                    $stmt_check = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ?");
                    $stmt_check->bind_param("ii", $valor, $user_id);
                    $stmt_check->execute();
                    if (stmt_get_result($stmt_check)->num_rows === 0) {
                        throw new Exception('El responsable seleccionado no es válido');
                    }
                    $stmt_check->close();

                    $stmt = $conn->prepare("UPDATE tareas SET responsable_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $valor, $tarea_id);
                    break;

                case 'deadline':
                    $valor = !empty($valor) ? $valor : null;
                    $stmt = $conn->prepare("UPDATE tareas SET deadline = ? WHERE id = ?");
                    $stmt->bind_param("si", $valor, $tarea_id);
                    break;

                case 'estado':
                    $estados_validos = ['pendiente', 'en_progreso', 'completada', 'cancelada'];
                    if (!in_array($valor, $estados_validos)) {
                        throw new Exception('Estado no válido');
                    }
                    // Si se marca como completada, registrar fecha de finalización
                    $fecha_fin = ($valor === 'completada') ? date('Y-m-d') : null;
                    $stmt = $conn->prepare("UPDATE tareas SET estado = ?, fecha_finalizacion_real = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $valor, $fecha_fin, $tarea_id);
                    break;
            }

            $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'ok' => true,
                'message' => 'Tarea actualizada correctamente',
                'campo' => $campo,
                'valor' => $valor
            ]);
            break;

        default:
            throw new Exception('Acción no reconocida: ' . $action);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();