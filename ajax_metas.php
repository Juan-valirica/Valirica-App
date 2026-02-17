<?php
/**
 * AJAX BACKEND: Gestión completa de metas personales
 * Maneja creación, actualización, progreso, estados y sistema de ayuda entre empleados
 *
 * Acciones disponibles:
 * - create_meta_personal: Crear nueva meta personal
 * - update_progress: Actualizar progreso (0-100%)
 * - update_status: Cambiar estado (pause/dev/done/help)
 * - request_help: Solicitar ayuda a compañeros
 * - get_teammates: Obtener lista de compañeros de área
 * - complete_with_help: Completar meta con ayuda
 * - finalize_help_team: Finalizar ayuda desde panel de ayudas
 */

session_start();
require 'config.php';

// Configurar zona horaria de España
date_default_timezone_set('Europe/Madrid');

// Header JSON (debe ir antes de cualquier salida)
header('Content-Type: application/json');

// Validación AJAX para seguridad
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso no autorizado']);
    exit;
}

// ============================================================================
// DETERMINAR EMPLEADO_ID (flexible: POST, GET, SESSION)
// ============================================================================
$empleado_id = 0;
if (!empty($_POST['empleado_id'])) {
    $empleado_id = (int)$_POST['empleado_id'];
} elseif (!empty($_GET['id'])) {
    $empleado_id = (int)$_GET['id'];
} elseif (!empty($_SESSION['empleado_id'])) {
    $empleado_id = (int)$_SESSION['empleado_id'];
}

// ============================================================================
// DETERMINAR USER_ID (empresa)
// ============================================================================
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($user_id <= 0) {
    // Intentar obtenerlo desde empleado
    if ($empleado_id > 0) {
        try {
            $q = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ? LIMIT 1");
            $q->bind_param("i", $empleado_id);
            $q->execute();
            $row = stmt_get_result($q)->fetch_assoc();
            $q->close();

            if ($row && !empty($row['usuario_id'])) {
                $user_id = (int)$row['usuario_id'];
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error al obtener usuario']);
            exit;
        }
    }
}

// Validar user_id
if ($user_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
    exit;
}

// ============================================================================
// OBTENER ACCIÓN
// ============================================================================
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if (empty($action)) {
    echo json_encode(['ok' => false, 'msg' => 'Acción no especificada']);
    exit;
}

// ============================================================================
// PROCESAR SEGÚN LA ACCIÓN
// ============================================================================
switch ($action) {

    // ========================================================================
    // CREAR META PERSONAL
    // ========================================================================
    case 'create_meta_personal':
        $descripcion = trim($_POST['descripcion'] ?? '');
        $due_date = $_POST['due_date'] ?? null;
        $meta_area_id_selected = !empty($_POST['meta_area_id']) ? (int)$_POST['meta_area_id'] : null;

        // Validar datos básicos
        if ($empleado_id <= 0 || $descripcion === '') {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
            exit;
        }

        try {
            // PASO 1: Verificar estructura de tabla metas_personales
            $columnsCheck = $conn->query("SHOW COLUMNS FROM metas_personales");
            $tableColumns = [];
            $columnNullable = [];
            while ($col = $columnsCheck->fetch_assoc()) {
                $tableColumns[] = $col['Field'];
                $columnNullable[$col['Field']] = ($col['Null'] === 'YES');
            }

            // PASO 2: Obtener datos del empleado
            $stmtEmp = $conn->prepare("
                SELECT usuario_id
                FROM equipo
                WHERE id = ?
                LIMIT 1
            ");
            $stmtEmp->bind_param("i", $empleado_id);
            $stmtEmp->execute();
            $emp = stmt_get_result($stmtEmp)->fetch_assoc();
            $stmtEmp->close();

            if (!$emp) {
                echo json_encode(['ok' => false, 'msg' => 'Empleado no encontrado']);
                exit;
            }

            $user_id_emp = (int)$emp['usuario_id'];

            // PASO 3: Obtener áreas del empleado desde tabla junction
            $area_ids_emp = [];
            $stmtAreas = $conn->prepare("
                SELECT eat.area_id
                FROM equipo_areas_trabajo eat
                INNER JOIN equipo e ON eat.equipo_id = e.id
                WHERE eat.equipo_id = ? AND e.usuario_id = ?
            ");
            $stmtAreas->bind_param("ii", $empleado_id, $user_id_emp);
            $stmtAreas->execute();
            $resAreas = stmt_get_result($stmtAreas);
            while ($rowA = $resAreas->fetch_assoc()) {
                $area_ids_emp[] = (int)$rowA['area_id'];
            }
            $stmtAreas->close();

            // Compatibilidad: usar primera área como area_id principal
            $area_id = !empty($area_ids_emp) ? $area_ids_emp[0] : null;
            $area_id_raw = $area_id;

            // PASO 4: Resolver meta_empresa_id (REQUERIDO)
            $meta_empresa_id = null;
            $qEmpresa = $conn->prepare("
                SELECT id
                FROM metas
                WHERE user_id = ?
                  AND tipo = 'empresa'
                  AND is_completed = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $qEmpresa->bind_param("i", $user_id_emp);
            $qEmpresa->execute();
            $rowEmpresa = stmt_get_result($qEmpresa)->fetch_assoc();
            $qEmpresa->close();

            if ($rowEmpresa) {
                $meta_empresa_id = (int)$rowEmpresa['id'];
            } else {
                // Si no hay meta empresa activa, buscar cualquiera
                $qEmpresaAny = $conn->prepare("
                    SELECT id
                    FROM metas
                    WHERE user_id = ?
                      AND tipo = 'empresa'
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $qEmpresaAny->bind_param("i", $user_id_emp);
                $qEmpresaAny->execute();
                $rowEmpresaAny = stmt_get_result($qEmpresaAny)->fetch_assoc();
                $qEmpresaAny->close();

                if ($rowEmpresaAny) {
                    $meta_empresa_id = (int)$rowEmpresaAny['id'];
                } else {
                    // Si aún no hay meta empresa, intentar sin validar (si la columna es nullable)
                    if (!in_array('meta_empresa_id', $tableColumns)) {
                        $meta_empresa_id = null; // La columna no existe
                    } else {
                        echo json_encode(['ok' => false, 'msg' => 'No se encontró una meta de empresa. Debe existir al menos una meta de empresa.']);
                        exit;
                    }
                }
            }

            // PASO 5: Determinar meta_area_id
            $meta_area_id = $meta_area_id_selected;

            // Si no seleccionó una meta específica, intentar obtener la del área
            // Usar area_id_raw (puede ser texto o número) para buscar en metas
            if (!$meta_area_id && $area_id_raw) {
                $qArea = $conn->prepare("
                    SELECT id
                    FROM metas
                    WHERE user_id = ?
                      AND tipo = 'area'
                      AND area_id = ?
                      AND is_completed = 0
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $bindType = is_numeric($area_id_raw) ? 'ii' : 'is';
                $areaValue = is_numeric($area_id_raw) ? (int)$area_id_raw : $area_id_raw;
                $qArea->bind_param($bindType, $user_id_emp, $areaValue);
                $qArea->execute();
                $rowArea = stmt_get_result($qArea)->fetch_assoc();
                $qArea->close();

                if ($rowArea) {
                    $meta_area_id = (int)$rowArea['id'];
                }
            }

            // PASO 6: Construir INSERT dinámicamente según columnas disponibles
            // Comenzar con campos base (sin created_at/updated_at aún)
            $insertColumns = [];
            $insertValues = [];
            $bindTypes = '';

            // Campos obligatorios
            $insertColumns[] = 'user_id';
            $insertValues[] = $user_id_emp;
            $bindTypes .= 'i';

            $insertColumns[] = 'persona_id';
            $insertValues[] = $empleado_id;
            $bindTypes .= 'i';

            $insertColumns[] = 'descripcion';
            $insertValues[] = $descripcion;
            $bindTypes .= 's';

            if ($due_date) {
                $insertColumns[] = 'due_date';
                $insertValues[] = $due_date;
                $bindTypes .= 's';
            }

            $insertColumns[] = 'progress_pct';
            $insertValues[] = 0;
            $bindTypes .= 'i';

            $insertColumns[] = 'is_completed';
            $insertValues[] = 0;
            $bindTypes .= 'i';

            $insertColumns[] = 'status';
            $insertValues[] = 'dev';
            $bindTypes .= 's';

            $insertColumns[] = 'help_requested';
            $insertValues[] = 0;
            $bindTypes .= 'i';

            // Añadir campos opcionales solo si existen en la tabla
            if (in_array('meta_empresa_id', $tableColumns)) {
                $insertColumns[] = 'meta_empresa_id';
                // Si es NOT NULL y no hay valor, usar 0 como fallback
                if ($meta_empresa_id !== null) {
                    $insertValues[] = $meta_empresa_id;
                } elseif (!$columnNullable['meta_empresa_id']) {
                    $insertValues[] = 0; // NOT NULL, usar 0
                } else {
                    $insertValues[] = null; // Permite NULL
                }
                $bindTypes .= 'i';
            }

            if (in_array('area_id', $tableColumns) && $area_id !== null && is_numeric($area_id)) {
                $insertColumns[] = 'area_id';
                $insertValues[] = (int)$area_id;
                $bindTypes .= 'i';
            }

            // meta_area_id: Siempre incluir si la columna existe
            if (in_array('meta_area_id', $tableColumns)) {
                $insertColumns[] = 'meta_area_id';
                // Si es "Meta Personal" (sin vincular), verificar si permite NULL
                if ($meta_area_id !== null) {
                    $insertValues[] = $meta_area_id;
                } elseif (!$columnNullable['meta_area_id']) {
                    // NOT NULL: usar 0 para indicar "sin meta de área"
                    $insertValues[] = 0;
                } else {
                    // Permite NULL
                    $insertValues[] = null;
                }
                $bindTypes .= 'i';
            }

            // Añadir created_at y updated_at al final
            $insertColumns[] = 'created_at';
            $insertColumns[] = 'updated_at';

            // Construir placeholders: ? para cada valor, NOW() para timestamps
            $placeholders = [];
            foreach ($insertColumns as $col) {
                if ($col === 'created_at' || $col === 'updated_at') {
                    $placeholders[] = 'NOW()';
                } else {
                    $placeholders[] = '?';
                }
            }

            $sql = "INSERT INTO metas_personales (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $conn->prepare($sql);

            // Bind parameters
            if (!empty($insertValues)) {
                $stmt->bind_param($bindTypes, ...$insertValues);
            }

            if ($stmt->execute()) {
                $meta_id = $stmt->insert_id;
                // Encontrar el valor insertado de meta_area_id
                $metaAreaIdInserted = null;
                $metaAreaIdIndex = array_search('meta_area_id', $insertColumns);
                if ($metaAreaIdIndex !== false && $metaAreaIdIndex < count($insertValues)) {
                    $metaAreaIdInserted = $insertValues[$metaAreaIdIndex];
                }

                echo json_encode([
                    'ok' => true,
                    'msg' => 'Meta creada exitosamente',
                    'meta_id' => $meta_id,
                    'debug' => [
                        'area_id_raw' => $area_id_raw,
                        'area_id_numeric' => $area_id,
                        'meta_empresa_id' => $meta_empresa_id,
                        'meta_area_id_selected' => $meta_area_id,
                        'meta_area_id_inserted' => $metaAreaIdInserted,
                        'columns_used' => $insertColumns,
                        'sql_generated' => $sql
                    ]
                ]);
            } else {
                echo json_encode([
                    'ok' => false,
                    'msg' => 'Error al crear la meta: ' . $stmt->error,
                    'debug' => [
                        'sql' => $sql,
                        'bind_types' => $bindTypes,
                        'values_count' => count($insertValues)
                    ]
                ]);
            }
            $stmt->close();

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // ACTUALIZAR PROGRESO
    // ========================================================================
    case 'update_progress':
        $meta_id = isset($_POST['meta_id']) ? (int)$_POST['meta_id'] : 0;
        $progress_pct = isset($_POST['progress_pct']) ? (int)$_POST['progress_pct'] : 0;

        if ($meta_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Meta no válida']);
            exit;
        }

        if ($progress_pct < 0 || $progress_pct > 100) {
            echo json_encode(['ok' => false, 'msg' => 'Porcentaje inválido (0-100)']);
            exit;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE metas_personales
                SET progress_pct = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param("iii", $progress_pct, $meta_id, $user_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'ok' => true,
                    'msg' => 'Progreso actualizado',
                    'progress' => $progress_pct
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $stmt->error]);
            }
            $stmt->close();

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // ACTUALIZAR ESTADO
    // ========================================================================
    case 'update_status':
        $meta_id = isset($_POST['meta_id']) ? (int)$_POST['meta_id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';

        if ($meta_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Meta no válida']);
            exit;
        }

        $valid_statuses = ['pause', 'dev', 'done', 'help'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['ok' => false, 'msg' => 'Estado no válido']);
            exit;
        }

        try {
            // Si el status es 'done', también marcar como completada
            if ($status === 'done') {
                $stmt = $conn->prepare("
                    UPDATE metas_personales
                    SET
                        status = ?,
                        is_completed = 1,
                        completed_at = NOW(),
                        progress_pct = 100,
                        updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    UPDATE metas_personales
                    SET status = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
            }

            $stmt->bind_param("sii", $status, $meta_id, $user_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'ok' => true,
                    'msg' => 'Estado actualizado',
                    'status' => $status
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $stmt->error]);
            }
            $stmt->close();

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // SOLICITAR AYUDA
    // ========================================================================
    case 'request_help':
        $meta_id = isset($_POST['meta_id']) ? (int)$_POST['meta_id'] : 0;

        if ($meta_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Meta no válida']);
            exit;
        }

        try {
            // Verificar si la columna help_requested_by existe (compatibilidad)
            $columns_check = $conn->query("SHOW COLUMNS FROM metas_personales LIKE 'help_requested_by'");
            $has_timestamp_column = $columns_check->num_rows > 0;

            if ($has_timestamp_column) {
                $stmt = $conn->prepare("
                    UPDATE metas_personales
                    SET
                        help_requested = 1,
                        help_requested_by = NOW(),
                        status = 'help',
                        updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    UPDATE metas_personales
                    SET
                        help_requested = 1,
                        status = 'help',
                        updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
            }

            $stmt->bind_param("ii", $meta_id, $user_id);

            if ($stmt->execute()) {
                echo json_encode(['ok' => true, 'msg' => 'Ayuda solicitada']);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error SQL: ' . $stmt->error]);
            }
            $stmt->close();

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // OBTENER METAS DEL ÁREA DEL EMPLEADO
    // ========================================================================
    case 'get_area_metas':
        if ($empleado_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida', 'debug' => ['empleado_id' => $empleado_id]]);
            exit;
        }

        try {
            // Primero, verificar qué columnas tiene la tabla equipo
            $columnsCheck = $conn->query("SHOW COLUMNS FROM equipo");
            $availableColumns = [];
            while ($col = $columnsCheck->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }

            // Obtener áreas del empleado desde tabla junction
            $area_ids_emp2 = [];
            $stmtEmpUser = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ? LIMIT 1");
            $stmtEmpUser->bind_param("i", $empleado_id);
            $stmtEmpUser->execute();
            $empUserRow = stmt_get_result($stmtEmpUser)->fetch_assoc();
            $stmtEmpUser->close();

            if ($empUserRow) {
                if ($user_id <= 0) {
                    $user_id = (int)$empUserRow['usuario_id'];
                }
            }

            $stmtAreas2 = $conn->prepare("
                SELECT eat.area_id
                FROM equipo_areas_trabajo eat
                INNER JOIN equipo e ON eat.equipo_id = e.id
                WHERE eat.equipo_id = ? AND e.usuario_id = ?
            ");
            $stmtAreas2->bind_param("ii", $empleado_id, $user_id);
            $stmtAreas2->execute();
            $resAreas2 = stmt_get_result($stmtAreas2);
            while ($rowA2 = $resAreas2->fetch_assoc()) {
                $area_ids_emp2[] = (int)$rowA2['area_id'];
            }
            $stmtAreas2->close();

            $area_id = !empty($area_ids_emp2) ? $area_ids_emp2[0] : null;
            $area_id_numeric = $area_id;

            if (!$area_id) {
                echo json_encode([
                    'ok' => true,
                    'metas' => [],
                    'debug' => [
                        'empleado_id' => $empleado_id,
                        'user_id' => $user_id,
                        'area_id_found' => false,
                        'note' => 'No areas assigned via equipo_areas_trabajo'
                    ]
                ]);
                exit;
            }

            if (!$area_id_numeric) {
                // No se pudo resolver el área
                echo json_encode([
                    'ok' => true,
                    'metas' => [],
                    'debug' => [
                        'empleado_id' => $empleado_id,
                        'user_id' => $user_id,
                        'area_id_raw' => $area_id,
                        'area_id_numeric' => null,
                        'message' => 'No se encontró el área en areas_trabajo'
                    ]
                ]);
                exit;
            }

            $area_id_for_query = $area_id_numeric;

            // Debug: Verificar qué metas hay en total para esta empresa
            $debugStmt = $conn->prepare("
                SELECT id, tipo, area_id, descripcion, is_completed
                FROM metas
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $debugStmt->bind_param("i", $user_id);
            $debugStmt->execute();
            $debugResult = stmt_get_result($debugStmt);
            $allMetas = [];
            while ($row = $debugResult->fetch_assoc()) {
                $allMetas[] = $row;
            }
            $debugStmt->close();

            // Obtener metas del área desde tabla "metas"
            // Ahora area_id_for_query siempre es numérico
            $stmtMetas = $conn->prepare("
                SELECT id, descripcion, due_date, progress_pct
                FROM metas
                WHERE user_id = ?
                  AND tipo = 'area'
                  AND area_id = ?
                  AND is_completed = 0
                ORDER BY created_at DESC
            ");

            // Bind siempre con tipo int
            $stmtMetas->bind_param("ii", $user_id, $area_id_for_query);
            $stmtMetas->execute();
            $result = stmt_get_result($stmtMetas);

            $metas = [];
            while ($row = $result->fetch_assoc()) {
                $metas[] = $row;
            }
            $stmtMetas->close();

            echo json_encode([
                'ok' => true,
                'metas' => $metas,
                'debug' => [
                    'area_id_raw' => $area_id,
                    'area_id_numeric' => $area_id_for_query,
                    'user_id' => $user_id,
                    'count' => count($metas),
                    'all_metas_in_db' => $allMetas,
                    'query' => "SELECT * FROM metas WHERE user_id = {$user_id} AND tipo = 'area' AND area_id = {$area_id_for_query} AND is_completed = 0"
                ]
            ]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // OBTENER COMPAÑEROS DE EQUIPO (para ayuda)
    // ========================================================================
    case 'get_teammates':
        if ($empleado_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            exit;
        }

        try {
            // Obtener áreas del empleado desde tabla junction
            $stmtAreaIds = $conn->prepare("
                SELECT eat.area_id
                FROM equipo_areas_trabajo eat
                INNER JOIN equipo e ON eat.equipo_id = e.id
                WHERE eat.equipo_id = ? AND e.usuario_id = ?
            ");
            $stmtAreaIds->bind_param("ii", $empleado_id, $user_id);
            $stmtAreaIds->execute();
            $resAreaIds = stmt_get_result($stmtAreaIds);

            $my_area_ids = [];
            while ($rowA = $resAreaIds->fetch_assoc()) {
                $my_area_ids[] = (int)$rowA['area_id'];
            }
            $stmtAreaIds->close();

            if (empty($my_area_ids)) {
                echo json_encode(['ok' => true, 'teammates' => []]);
                exit;
            }

            // Obtener compañeros que comparten AL MENOS un área (excluyendo al empleado)
            $ph_tm = implode(',', array_fill(0, count($my_area_ids), '?'));
            $types_tm = str_repeat('i', count($my_area_ids)) . 'ii';
            $params_tm = array_merge($my_area_ids, [$user_id, $empleado_id]);

            $stmtTeam = $conn->prepare("
                SELECT DISTINCT e.id, e.nombre_persona as nombre
                FROM equipo e
                INNER JOIN equipo_areas_trabajo eat ON e.id = eat.equipo_id
                WHERE eat.area_id IN ($ph_tm)
                  AND e.usuario_id = ?
                  AND e.id != ?
                ORDER BY e.nombre_persona ASC
            ");
            $stmtTeam->bind_param($types_tm, ...$params_tm);
            $stmtTeam->execute();
            $result = stmt_get_result($stmtTeam);

            $teammates = [];
            while ($row = $result->fetch_assoc()) {
                $teammates[] = $row;
            }
            $stmtTeam->close();

            echo json_encode(['ok' => true, 'teammates' => $teammates]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // COMPLETAR META CON AYUDA
    // ========================================================================
    case 'complete_with_help':
        $meta_id = isset($_POST['meta_id']) ? (int)$_POST['meta_id'] : 0;
        $helped_by_id = isset($_POST['helped_by_id']) && $_POST['helped_by_id'] !== ''
            ? (int)$_POST['helped_by_id']
            : null;
        $helped_by_name = isset($_POST['helped_by_name']) && $_POST['helped_by_name'] !== ''
            ? trim($_POST['helped_by_name'])
            : null;

        if ($meta_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Meta no válida']);
            exit;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE metas_personales
                SET
                    is_completed = 1,
                    completed_at = NOW(),
                    status = 'done',
                    progress_pct = 100,
                    help_requested = 0,
                    helped_by_persona_id = ?,
                    helped_by_name = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param("isii", $helped_by_id, $helped_by_name, $meta_id, $user_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'ok' => true,
                    'msg' => 'Meta completada con ayuda',
                    'helped_by' => $helped_by_name
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $stmt->error]);
            }
            $stmt->close();

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // FINALIZAR AYUDA DESDE PANEL DE EQUIPO
    // ========================================================================
    case 'finalize_help_team':
        $meta_id = isset($_POST['meta_id']) ? (int)$_POST['meta_id'] : 0;
        $persona_id = isset($_POST['persona_id']) ? (int)$_POST['persona_id'] : 0;
        $progress_pct = isset($_POST['progress_pct']) ? (int)$_POST['progress_pct'] : 100;

        if ($meta_id <= 0 || $persona_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Parámetros no válidos']);
            exit;
        }

        try {
            // Obtener nombre del empleado que ayudó
            $helped_by_name = null;
            if ($empleado_id > 0) {
                $stmtName = $conn->prepare("SELECT nombre_persona FROM equipo WHERE id = ?");
                $stmtName->bind_param("i", $empleado_id);
                $stmtName->execute();
                $resultName = stmt_get_result($stmtName)->fetch_assoc();
                if ($resultName) {
                    $helped_by_name = $resultName['nombre_persona'];
                }
                $stmtName->close();
            }

            // Actualizar meta
            $stmt = $conn->prepare("
                UPDATE metas_personales
                SET
                    is_completed = 1,
                    completed_at = NOW(),
                    status = 'done',
                    progress_pct = ?,
                    help_requested = 0,
                    helped_by_persona_id = ?,
                    helped_by_name = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ? AND persona_id = ?
            ");
            $stmt->bind_param("iisiii", $progress_pct, $empleado_id, $helped_by_name, $meta_id, $user_id, $persona_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'ok' => true,
                    'msg' => 'Ayuda finalizada correctamente'
                ]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al finalizar ayuda: ' . $stmt->error]);
            }
            $stmt->close();

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error en el servidor: ' . $e->getMessage()]);
        }
        break;

    // ========================================================================
    // ACCIÓN NO RECONOCIDA
    // ========================================================================
    default:
        echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida: ' . $action]);
        break;
}

$conn->close();
exit;
