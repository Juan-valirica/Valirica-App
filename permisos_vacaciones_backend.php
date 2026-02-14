<?php
/**
 * BACKEND: GESTIÓN DE PERMISOS Y VACACIONES
 * Maneja todas las operaciones CRUD y lógica de negocio
 */

session_start();
require 'config.php';
date_default_timezone_set('Europe/Madrid');

header('Content-Type: application/json; charset=UTF-8');

// ===================================================================
// UTILIDADES
// ===================================================================

function calcular_dias_laborables($fecha_inicio, $fecha_fin) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day'); // Incluir último día

    $interval = new DateInterval('P1D');
    $periodo = new DatePeriod($inicio, $interval, $fin);

    $dias_laborables = 0;
    foreach ($periodo as $dia) {
        // No contar sábados (6) ni domingos (7)
        if ($dia->format('N') < 6) {
            $dias_laborables++;
        }
    }

    return $dias_laborables;
}

function crear_notificacion($conn, $usuario_destino_id, $tipo_destino, $tipo, $titulo, $mensaje, $referencia_tipo = null, $referencia_id = null) {
    $stmt = $conn->prepare("
        INSERT INTO notificaciones (usuario_destino_id, tipo_destino, tipo, titulo, mensaje, referencia_tipo, referencia_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssssi", $usuario_destino_id, $tipo_destino, $tipo, $titulo, $mensaje, $referencia_tipo, $referencia_id);
    $stmt->execute();
    $stmt->close();
}

// ===================================================================
// SOLICITAR PERMISO (Empleado)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'solicitar_permiso') {
    try {
        $empleado_id = (int)$_POST['empleado_id'];
        $tipo_permiso_id = (int)$_POST['tipo_permiso_id'];
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];

        // Obtener usuario_id (empresa)
        $stmt = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ?");
        $stmt->bind_param("i", $empleado_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $usuario_id = $result['usuario_id'];
        $stmt->close();

        // Validaciones
        if (empty($titulo)) {
            throw new Exception('El título es obligatorio');
        }

        if (strtotime($fecha_inicio) < strtotime(date('Y-m-d'))) {
            throw new Exception('No puedes solicitar permisos para fechas pasadas');
        }

        // Verificar anticipación mínima
        $stmt = $conn->prepare("SELECT dias_anticipacion_minima FROM tipos_permisos WHERE id = ?");
        $stmt->bind_param("i", $tipo_permiso_id);
        $stmt->execute();
        $tipo_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $dias_hasta_inicio = (strtotime($fecha_inicio) - strtotime(date('Y-m-d'))) / 86400;
        if ($dias_hasta_inicio < $tipo_info['dias_anticipacion_minima']) {
            throw new Exception("Este tipo de permiso requiere solicitarse con al menos {$tipo_info['dias_anticipacion_minima']} días de anticipación");
        }

        // Verificar solapamientos
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM permisos
            WHERE persona_id = ?
              AND estado IN ('pendiente', 'aprobado')
              AND (
                  (fecha_inicio BETWEEN ? AND ?)
                  OR (fecha_fin BETWEEN ? AND ?)
                  OR (? BETWEEN fecha_inicio AND fecha_fin)
              )
        ");
        $stmt->bind_param("isssss", $empleado_id, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio);
        $stmt->execute();
        $solape = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($solape['total'] > 0) {
            throw new Exception('Ya tienes un permiso solicitado o aprobado en esas fechas');
        }

        // Procesar documento si existe
        $documento_path = null;
        $documento_nombre = null;

        if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/permisos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $extension = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
            $documento_nombre = $_FILES['documento']['name'];
            $documento_path = $upload_dir . uniqid('permiso_') . '.' . $extension;

            if (!move_uploaded_file($_FILES['documento']['tmp_name'], $documento_path)) {
                throw new Exception('Error al subir el documento');
            }
        }

        // Insertar permiso
        $stmt = $conn->prepare("
            INSERT INTO permisos (
                usuario_id, persona_id, tipo_permiso_id, titulo, descripcion,
                fecha_inicio, fecha_fin, documento_path, documento_nombre_original, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ");
        $stmt->bind_param("iiissssss",
            $usuario_id, $empleado_id, $tipo_permiso_id, $titulo, $descripcion,
            $fecha_inicio, $fecha_fin, $documento_path, $documento_nombre
        );
        $stmt->execute();
        $permiso_id = $conn->insert_id;
        $stmt->close();

        // Crear notificación para el empleador
        crear_notificacion(
            $conn,
            $usuario_id,
            'empleador',
            'permiso_solicitado',
            'Nueva solicitud de permiso',
            "Un empleado ha solicitado un permiso: {$titulo}",
            'permiso',
            $permiso_id
        );

        echo json_encode([
            'success' => true,
            'message' => 'Solicitud de permiso enviada correctamente',
            'permiso_id' => $permiso_id
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ===================================================================
// SOLICITAR VACACIONES (Empleado)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'solicitar_vacaciones') {
    try {
        $empleado_id = (int)$_POST['empleado_id'];
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        $motivo = trim($_POST['motivo'] ?? 'Vacaciones');

        // Obtener usuario_id (empresa)
        $stmt = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ?");
        $stmt->bind_param("i", $empleado_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $usuario_id = $result['usuario_id'];
        $stmt->close();

        // Validaciones
        if (strtotime($fecha_inicio) < strtotime(date('Y-m-d'))) {
            throw new Exception('No puedes solicitar vacaciones para fechas pasadas');
        }

        // Calcular días laborables
        $dias_laborables = calcular_dias_laborables($fecha_inicio, $fecha_fin);

        // Verificar balance
        $anio = date('Y', strtotime($fecha_inicio));
        $stmt = $conn->prepare("
            SELECT dias_disponibles FROM balance_vacaciones
            WHERE persona_id = ? AND anio = ?
        ");
        $stmt->bind_param("ii", $empleado_id, $anio);
        $stmt->execute();
        $balance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$balance) {
            // Crear balance si no existe (22 días por defecto en España)
            $stmt = $conn->prepare("
                INSERT INTO balance_vacaciones (persona_id, anio, dias_totales)
                VALUES (?, ?, 22.00)
            ");
            $stmt->bind_param("ii", $empleado_id, $anio);
            $stmt->execute();
            $stmt->close();
            $dias_disponibles = 22.00;
        } else {
            $dias_disponibles = $balance['dias_disponibles'];
        }

        if ($dias_laborables > $dias_disponibles) {
            throw new Exception("No tienes suficientes días disponibles. Tienes {$dias_disponibles} días y estás solicitando {$dias_laborables} días laborables");
        }

        // Verificar solapamientos
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM vacaciones
            WHERE persona_id = ?
              AND estado IN ('pendiente', 'aprobado')
              AND (
                  (fecha_inicio_programada BETWEEN ? AND ?)
                  OR (fecha_fin_programada BETWEEN ? AND ?)
                  OR (? BETWEEN fecha_inicio_programada AND fecha_fin_programada)
              )
        ");
        $stmt->bind_param("isssss", $empleado_id, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio);
        $stmt->execute();
        $solape = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($solape['total'] > 0) {
            throw new Exception('Ya tienes vacaciones solicitadas o aprobadas en esas fechas');
        }

        // Insertar vacación
        $stmt = $conn->prepare("
            INSERT INTO vacaciones (
                usuario_id, persona_id, fecha_inicio_programada, fecha_fin_programada,
                dias_solicitados, motivo, estado, anio_balance
            ) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)
        ");
        $stmt->bind_param("iissdsi", $usuario_id, $empleado_id, $fecha_inicio, $fecha_fin, $dias_laborables, $motivo, $anio);
        $stmt->execute();
        $vacacion_id = $conn->insert_id;
        $stmt->close();

        // Marcar días como pendientes en balance
        $stmt = $conn->prepare("
            UPDATE balance_vacaciones
            SET dias_pendientes = dias_pendientes + ?
            WHERE persona_id = ? AND anio = ?
        ");
        $stmt->bind_param("dii", $dias_laborables, $empleado_id, $anio);
        $stmt->execute();
        $stmt->close();

        // Crear notificación para el empleador
        crear_notificacion(
            $conn,
            $usuario_id,
            'empleador',
            'vacacion_solicitada',
            'Nueva solicitud de vacaciones',
            "Un empleado ha solicitado vacaciones del {$fecha_inicio} al {$fecha_fin} ({$dias_laborables} días laborables)",
            'vacacion',
            $vacacion_id
        );

        echo json_encode([
            'success' => true,
            'message' => 'Solicitud de vacaciones enviada correctamente',
            'vacacion_id' => $vacacion_id,
            'dias_solicitados' => $dias_laborables
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ===================================================================
// APROBAR/RECHAZAR PERMISO (Empleador)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'decidir_permiso') {
    try {
        $permiso_id = (int)$_POST['permiso_id'];
        $decision = $_POST['decision']; // 'aprobar' o 'rechazar'
        $motivo_rechazo = trim($_POST['motivo_rechazo'] ?? '');
        $user_id = (int)$_SESSION['user_id'];

        if ($decision === 'rechazar' && empty($motivo_rechazo)) {
            throw new Exception('Debes proporcionar un motivo para rechazar la solicitud');
        }

        // Obtener información del permiso
        $stmt = $conn->prepare("
            SELECT p.*, e.nombre_persona
            FROM permisos p
            INNER JOIN equipo e ON p.persona_id = e.id
            WHERE p.id = ? AND p.usuario_id = ?
        ");
        $stmt->bind_param("ii", $permiso_id, $user_id);
        $stmt->execute();
        $permiso = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$permiso) {
            throw new Exception('Permiso no encontrado');
        }

        if ($permiso['estado'] !== 'pendiente') {
            throw new Exception('Este permiso ya fue procesado');
        }

        // Actualizar permiso
        $nuevo_estado = ($decision === 'aprobar') ? 'aprobado' : 'rechazado';
        $stmt = $conn->prepare("
            UPDATE permisos
            SET estado = ?, fecha_decision = NOW(), decidido_por = ?, motivo_rechazo = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sisi", $nuevo_estado, $user_id, $motivo_rechazo, $permiso_id);
        $stmt->execute();
        $stmt->close();

        // Crear notificación para el empleado
        $tipo_notif = ($decision === 'aprobar') ? 'permiso_aprobado' : 'permiso_rechazado';
        $titulo_notif = ($decision === 'aprobar') ? 'Permiso aprobado' : 'Permiso rechazado';
        $mensaje_notif = ($decision === 'aprobar')
            ? "Tu solicitud de permiso '{$permiso['titulo']}' ha sido aprobada"
            : "Tu solicitud de permiso '{$permiso['titulo']}' fue rechazada. Motivo: {$motivo_rechazo}";

        crear_notificacion(
            $conn,
            $permiso['persona_id'],
            'empleado',
            $tipo_notif,
            $titulo_notif,
            $mensaje_notif,
            'permiso',
            $permiso_id
        );

        echo json_encode([
            'success' => true,
            'message' => ($decision === 'aprobar') ? 'Permiso aprobado correctamente' : 'Permiso rechazado correctamente'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ===================================================================
// APROBAR/RECHAZAR VACACIONES (Empleador)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'decidir_vacacion') {
    try {
        $vacacion_id = (int)$_POST['vacacion_id'];
        $decision = $_POST['decision']; // 'aprobar' o 'rechazar'
        $motivo_rechazo = trim($_POST['motivo_rechazo'] ?? '');
        $user_id = (int)$_SESSION['user_id'];

        if ($decision === 'rechazar' && empty($motivo_rechazo)) {
            throw new Exception('Debes proporcionar un motivo para rechazar la solicitud');
        }

        // Obtener información de la vacación
        $stmt = $conn->prepare("
            SELECT v.*, e.nombre_persona
            FROM vacaciones v
            INNER JOIN equipo e ON v.persona_id = e.id
            WHERE v.id = ? AND v.usuario_id = ?
        ");
        $stmt->bind_param("ii", $vacacion_id, $user_id);
        $stmt->execute();
        $vacacion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$vacacion) {
            throw new Exception('Vacación no encontrada');
        }

        if ($vacacion['estado'] !== 'pendiente') {
            throw new Exception('Esta vacación ya fue procesada');
        }

        // Actualizar vacación
        $nuevo_estado = ($decision === 'aprobar') ? 'aprobado' : 'rechazado';
        $stmt = $conn->prepare("
            UPDATE vacaciones
            SET estado = ?, fecha_decision = NOW(), decidido_por = ?, motivo_rechazo = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sisi", $nuevo_estado, $user_id, $motivo_rechazo, $vacacion_id);
        $stmt->execute();
        $stmt->close();

        // El trigger automático actualizará el balance

        // Crear notificación para el empleado
        $tipo_notif = ($decision === 'aprobar') ? 'vacacion_aprobada' : 'vacacion_rechazada';
        $titulo_notif = ($decision === 'aprobar') ? 'Vacaciones aprobadas' : 'Vacaciones rechazadas';
        $mensaje_notif = ($decision === 'aprobar')
            ? "Tu solicitud de vacaciones del {$vacacion['fecha_inicio_programada']} al {$vacacion['fecha_fin_programada']} ha sido aprobada"
            : "Tu solicitud de vacaciones fue rechazada. Motivo: {$motivo_rechazo}";

        crear_notificacion(
            $conn,
            $vacacion['persona_id'],
            'empleado',
            $tipo_notif,
            $titulo_notif,
            $mensaje_notif,
            'vacacion',
            $vacacion_id
        );

        echo json_encode([
            'success' => true,
            'message' => ($decision === 'aprobar') ? 'Vacaciones aprobadas correctamente' : 'Vacaciones rechazadas correctamente'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ===================================================================
// MARCAR NOTIFICACIÓN COMO LEÍDA (Empleado)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_notificacion_leida') {
    try {
        $notificacion_id = (int)$_POST['notificacion_id'];
        $empleado_id = (int)$_POST['empleado_id'];

        $stmt = $conn->prepare("
            UPDATE notificaciones
            SET leida = TRUE, fecha_lectura = NOW()
            WHERE id = ? AND usuario_destino_id = ? AND tipo_destino = 'empleado'
        ");
        $stmt->bind_param("ii", $notificacion_id, $empleado_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Notificación marcada como leída'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ===================================================================
// OBTENER TIPOS DE PERMISOS
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener_tipos_permisos') {
    $stmt = $conn->prepare("SELECT * FROM tipos_permisos WHERE activo = TRUE ORDER BY nombre");
    $stmt->execute();
    $result = $stmt->get_result();

    $tipos = [];
    while ($row = $result->fetch_assoc()) {
        $tipos[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'tipos' => $tipos
    ]);
    exit;
}

// ===================================================================
// OBTENER BALANCE DE VACACIONES (Empleado)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener_balance_vacaciones') {
    $empleado_id = (int)$_GET['empleado_id'];
    $anio = (int)($_GET['anio'] ?? date('Y'));

    $stmt = $conn->prepare("
        SELECT * FROM balance_vacaciones
        WHERE persona_id = ? AND anio = ?
    ");
    $stmt->bind_param("ii", $empleado_id, $anio);
    $stmt->execute();
    $balance = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$balance) {
        // Crear balance si no existe
        $stmt = $conn->prepare("
            INSERT INTO balance_vacaciones (persona_id, anio, dias_totales)
            VALUES (?, ?, 22.00)
        ");
        $stmt->bind_param("ii", $empleado_id, $anio);
        $stmt->execute();
        $stmt->close();

        $balance = [
            'persona_id' => $empleado_id,
            'anio' => $anio,
            'dias_totales' => 22.00,
            'dias_usados' => 0,
            'dias_pendientes' => 0,
            'dias_disponibles' => 22.00,
            'dias_anio_anterior' => 0
        ];
    }

    echo json_encode([
        'success' => true,
        'balance' => $balance
    ]);
    exit;
}

// ===================================================================
// OBTENER NOTIFICACIONES NO LEÍDAS (Empleado o Empleador)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener_notificaciones') {
    $usuario_id = (int)$_GET['usuario_id'];
    $tipo_destino = $_GET['tipo_destino']; // 'empleado' o 'empleador'

    $stmt = $conn->prepare("
        SELECT * FROM notificaciones
        WHERE usuario_destino_id = ? AND tipo_destino = ? AND leida = FALSE
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("is", $usuario_id, $tipo_destino);
    $stmt->execute();
    $result = $stmt->get_result();

    $notificaciones = [];
    while ($row = $result->fetch_assoc()) {
        $notificaciones[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'notificaciones' => $notificaciones
    ]);
    exit;
}

// ===================================================================
// OBTENER COUNT DE SOLICITUDES PENDIENTES (Para polling del empleador)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener_solicitudes_pendientes_count') {
    session_start();
    $user_id = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM permisos WHERE usuario_id = ? AND estado = 'pendiente') +
            (SELECT COUNT(*) FROM vacaciones WHERE usuario_id = ? AND estado = 'pendiente') as total
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = (int)($result['total'] ?? 0);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    exit;
}

// Si no hay acción válida
echo json_encode([
    'success' => false,
    'message' => 'Acción no válida'
]);
?>