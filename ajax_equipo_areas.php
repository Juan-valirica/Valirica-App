<?php
/**
 * ajax_equipo_areas.php
 * Endpoint AJAX para gestionar las áreas de trabajo de los miembros del equipo.
 * Soporta múltiples áreas por miembro (tabla junction equipo_areas_trabajo).
 *
 * Acciones:
 *   - list_members:     Lista miembros con sus áreas asignadas
 *   - get_member_areas: Obtiene áreas de un miembro específico
 *   - save_areas:       Guarda (reemplaza) las áreas de un miembro
 *   - add_area:         Agrega una área a un miembro
 *   - remove_area:      Quita una área de un miembro
 */
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        /* ========== Listar todos los miembros con sus áreas ========== */
        case 'list_members':
            $stmt = $conn->prepare("
                SELECT e.id, e.nombre_persona, e.apellido, e.cargo,
                       GROUP_CONCAT(at2.nombre_area ORDER BY at2.nombre_area SEPARATOR ', ') AS areas_texto,
                       GROUP_CONCAT(at2.id ORDER BY at2.nombre_area SEPARATOR ',') AS areas_ids
                FROM equipo e
                LEFT JOIN equipo_areas_trabajo eat ON e.id = eat.equipo_id
                LEFT JOIN areas_trabajo at2 ON eat.area_id = at2.id
                WHERE e.usuario_id = ?
                GROUP BY e.id
                ORDER BY e.nombre_persona ASC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = stmt_get_result($stmt);

            $members = [];
            while ($row = $res->fetch_assoc()) {
                $members[] = [
                    'id'          => (int)$row['id'],
                    'nombre'      => $row['nombre_persona'],
                    'apellido'    => $row['apellido'] ?? '',
                    'cargo'       => $row['cargo'] ?? '',
                    'areas_texto' => $row['areas_texto'] ?? '',
                    'areas_ids'   => $row['areas_ids']
                        ? array_map('intval', explode(',', $row['areas_ids']))
                        : [],
                ];
            }
            $stmt->close();

            echo json_encode(['ok' => true, 'members' => $members]);
            break;

        /* ========== Obtener áreas de un miembro ========== */
        case 'get_member_areas':
            $equipo_id = (int)($_REQUEST['equipo_id'] ?? 0);
            if ($equipo_id <= 0) throw new Exception('equipo_id requerido');

            $stmt = $conn->prepare("
                SELECT at2.id, at2.nombre_area
                FROM equipo_areas_trabajo eat
                INNER JOIN areas_trabajo at2 ON eat.area_id = at2.id
                INNER JOIN equipo e ON eat.equipo_id = e.id
                WHERE eat.equipo_id = ? AND e.usuario_id = ?
                ORDER BY at2.nombre_area
            ");
            $stmt->bind_param("ii", $equipo_id, $user_id);
            $stmt->execute();
            $res = stmt_get_result($stmt);

            $areas = [];
            while ($row = $res->fetch_assoc()) {
                $areas[] = ['id' => (int)$row['id'], 'nombre' => $row['nombre_area']];
            }
            $stmt->close();

            echo json_encode(['ok' => true, 'areas' => $areas]);
            break;

        /* ========== Guardar áreas (reemplaza todas) ========== */
        case 'save_areas':
            $equipo_id = (int)($_POST['equipo_id'] ?? 0);
            $area_ids  = $_POST['area_ids'] ?? [];
            if ($equipo_id <= 0) throw new Exception('equipo_id requerido');

            // Validar que el miembro pertenece al usuario
            $chk = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ?");
            $chk->bind_param("ii", $equipo_id, $user_id);
            $chk->execute();
            if (!stmt_get_result($chk)->fetch_assoc()) {
                $chk->close();
                throw new Exception('Miembro no encontrado');
            }
            $chk->close();

            // Sanitizar area_ids
            $area_ids = array_filter(array_map('intval', (array)$area_ids), function($v) { return $v > 0; });

            // Validar que las áreas pertenecen al usuario
            if (!empty($area_ids)) {
                $placeholders = implode(',', array_fill(0, count($area_ids), '?'));
                $types = str_repeat('i', count($area_ids)) . 'i';
                $params = array_merge($area_ids, [$user_id]);

                $val = $conn->prepare("
                    SELECT COUNT(*) AS cnt FROM areas_trabajo
                    WHERE id IN ($placeholders) AND usuario_id = ?
                ");
                $val->bind_param($types, ...$params);
                $val->execute();
                $cnt = (int)stmt_get_result($val)->fetch_assoc()['cnt'];
                $val->close();

                if ($cnt !== count($area_ids)) {
                    throw new Exception('Algunas áreas no son válidas');
                }
            }

            $conn->begin_transaction();

            // Borrar asignaciones anteriores
            $del = $conn->prepare("DELETE FROM equipo_areas_trabajo WHERE equipo_id = ?");
            $del->bind_param("i", $equipo_id);
            $del->execute();
            $del->close();

            // Insertar nuevas
            if (!empty($area_ids)) {
                $ins = $conn->prepare("INSERT INTO equipo_areas_trabajo (equipo_id, area_id) VALUES (?, ?)");
                foreach ($area_ids as $aid) {
                    $ins->bind_param("ii", $equipo_id, $aid);
                    $ins->execute();
                }
                $ins->close();
            }

            // Actualizar campo legacy equipo.area_trabajo (compatibilidad)
            if (!empty($area_ids)) {
                // Guardar la primera área como fallback en el campo legacy
                $first = $conn->prepare("SELECT nombre_area FROM areas_trabajo WHERE id = ? LIMIT 1");
                $first->bind_param("i", $area_ids[0]);
                $first->execute();
                $first_name = stmt_get_result($first)->fetch_assoc()['nombre_area'] ?? null;
                $first->close();

                $upd = $conn->prepare("UPDATE equipo SET area_trabajo = ? WHERE id = ?");
                $upd->bind_param("si", $first_name, $equipo_id);
                $upd->execute();
                $upd->close();
            } else {
                $upd = $conn->prepare("UPDATE equipo SET area_trabajo = NULL WHERE id = ?");
                $upd->bind_param("i", $equipo_id);
                $upd->execute();
                $upd->close();
            }

            $conn->commit();

            echo json_encode(['ok' => true, 'message' => 'Áreas actualizadas']);
            break;

        /* ========== Agregar una área ========== */
        case 'add_area':
            $equipo_id = (int)($_POST['equipo_id'] ?? 0);
            $area_id   = (int)($_POST['area_id'] ?? 0);
            if ($equipo_id <= 0 || $area_id <= 0) throw new Exception('equipo_id y area_id requeridos');

            // Validar pertenencia
            $chk = $conn->prepare("SELECT id FROM equipo WHERE id = ? AND usuario_id = ?");
            $chk->bind_param("ii", $equipo_id, $user_id);
            $chk->execute();
            if (!stmt_get_result($chk)->fetch_assoc()) {
                $chk->close();
                throw new Exception('Miembro no encontrado');
            }
            $chk->close();

            $chk2 = $conn->prepare("SELECT id FROM areas_trabajo WHERE id = ? AND usuario_id = ?");
            $chk2->bind_param("ii", $area_id, $user_id);
            $chk2->execute();
            if (!stmt_get_result($chk2)->fetch_assoc()) {
                $chk2->close();
                throw new Exception('Área no encontrada');
            }
            $chk2->close();

            $ins = $conn->prepare("INSERT IGNORE INTO equipo_areas_trabajo (equipo_id, area_id) VALUES (?, ?)");
            $ins->bind_param("ii", $equipo_id, $area_id);
            $ins->execute();
            $ins->close();

            echo json_encode(['ok' => true, 'message' => 'Área asignada']);
            break;

        /* ========== Quitar una área ========== */
        case 'remove_area':
            $equipo_id = (int)($_POST['equipo_id'] ?? 0);
            $area_id   = (int)($_POST['area_id'] ?? 0);
            if ($equipo_id <= 0 || $area_id <= 0) throw new Exception('equipo_id y area_id requeridos');

            $del = $conn->prepare("
                DELETE eat FROM equipo_areas_trabajo eat
                INNER JOIN equipo e ON eat.equipo_id = e.id
                WHERE eat.equipo_id = ? AND eat.area_id = ? AND e.usuario_id = ?
            ");
            $del->bind_param("iii", $equipo_id, $area_id, $user_id);
            $del->execute();
            $del->close();

            echo json_encode(['ok' => true, 'message' => 'Área removida']);
            break;

        default:
            throw new Exception('Acción no válida: ' . $action);
    }

} catch (Exception $e) {
    if ($conn->in_transaction ?? false) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
