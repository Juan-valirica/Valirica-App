<?php
/**
 * PANEL DE PROYECTOS Y TAREAS - Dashboard Empleado
 * Vista con permisos: Solo el LÍDER puede editar tareas
 *
 * Variables requeridas:
 * - $conn: conexión a BD
 * - $user_id: ID de la empresa
 * - $empleado_id: ID del empleado actual
 */

// Verificar que tenemos las variables necesarias
if (!isset($empleado_id) || !isset($user_id) || !isset($conn)) {
    echo '<div class="pyt-empty"><p>Error: Variables de sesión no disponibles</p></div>';
    return;
}

// Verificar si las tablas existen
$tablas_existen = false;
try {
    $check = $conn->query("SHOW TABLES LIKE 'proyectos'");
    $tablas_existen = $check && $check->num_rows > 0;
} catch (Exception $e) {
    $tablas_existen = false;
}

$proyectos_con_tareas = [];
$stats = ['total_proyectos' => 0, 'total_tareas' => 0, 'tareas_completadas' => 0, 'tareas_vencidas' => 0, 'mis_tareas' => 0];

// Obtener empleados del equipo (para selects de responsable)
$empleados_equipo = [];
$stmt_eq = $conn->prepare("SELECT id, nombre_persona, cargo FROM equipo WHERE usuario_id = ? ORDER BY nombre_persona ASC");
if ($stmt_eq) {
    $stmt_eq->bind_param("i", $user_id);
    $stmt_eq->execute();
    $res_eq = stmt_get_result($stmt_eq);
    while ($row = $res_eq->fetch_assoc()) {
        $empleados_equipo[] = $row;
    }
    $stmt_eq->close();
}

if ($tablas_existen) {
    // Obtener proyectos donde el empleado es LÍDER o está INVOLUCRADO (tiene tareas asignadas)
    $stmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.titulo,
            p.descripcion,
            p.estado as proyecto_estado,
            p.prioridad,
            p.fecha_inicio,
            p.fecha_fin_estimada,
            p.lider_id,
            e.nombre_persona as lider_nombre,
            e.cargo as lider_cargo
        FROM proyectos p
        LEFT JOIN equipo e ON p.lider_id = e.id
        LEFT JOIN tareas t ON t.proyecto_id = p.id
        WHERE p.usuario_id = ?
          AND (p.lider_id = ? OR t.responsable_id = ?)
        ORDER BY
            CASE WHEN p.lider_id = ? THEN 0 ELSE 1 END,
            FIELD(p.estado, 'en_progreso', 'planificacion', 'pausado', 'completado', 'cancelado'),
            FIELD(p.prioridad, 'critica', 'alta', 'media', 'baja'),
            p.created_at DESC
    ");

    if ($stmt) {
        $stmt->bind_param("iiii", $user_id, $empleado_id, $empleado_id, $empleado_id);
        $stmt->execute();
        $result = stmt_get_result($stmt);

        while ($proyecto = $result->fetch_assoc()) {
            // ⭐ Determinar si el empleado actual es el LÍDER de este proyecto
            $es_lider = ((int)$proyecto['lider_id'] === (int)$empleado_id);
            $proyecto['es_lider'] = $es_lider;

            // Obtener tareas del proyecto
            $stmt_tareas = $conn->prepare("
                SELECT
                    t.id,
                    t.titulo,
                    t.estado,
                    t.prioridad,
                    t.deadline,
                    t.fecha_finalizacion_real,
                    t.responsable_id,
                    eq.nombre_persona as responsable_nombre,
                    DATEDIFF(t.deadline, CURDATE()) as dias_restantes
                FROM tareas t
                LEFT JOIN equipo eq ON t.responsable_id = eq.id
                WHERE t.proyecto_id = ?
                ORDER BY
                    FIELD(t.estado, 'en_progreso', 'pendiente', 'completada', 'cancelada'),
                    t.deadline ASC,
                    t.orden ASC
            ");
            $stmt_tareas->bind_param("i", $proyecto['id']);
            $stmt_tareas->execute();
            $result_tareas = stmt_get_result($stmt_tareas);

            $tareas = [];
            $completadas = 0;
            $total = 0;
            while ($tarea = $result_tareas->fetch_assoc()) {
                // Marcar si esta tarea es mía
                $tarea['es_mi_tarea'] = ((int)$tarea['responsable_id'] === $empleado_id);
                $tareas[] = $tarea;
                $total++;
                if ($tarea['estado'] === 'completada') $completadas++;
                if ($tarea['dias_restantes'] < 0 && $tarea['estado'] !== 'completada' && $tarea['estado'] !== 'cancelada') {
                    $stats['tareas_vencidas']++;
                }
                if ($tarea['es_mi_tarea']) {
                    $stats['mis_tareas']++;
                }
            }
            $stmt_tareas->close();

            $proyecto['tareas'] = $tareas;
            $proyecto['total_tareas'] = $total;
            $proyecto['tareas_completadas'] = $completadas;
            $proyecto['porcentaje'] = $total > 0 ? round(($completadas / $total) * 100) : 0;

            $proyectos_con_tareas[] = $proyecto;

            $stats['total_proyectos']++;
            $stats['total_tareas'] += $total;
            $stats['tareas_completadas'] += $completadas;
        }
        $stmt->close();
    }
}

$porcentaje_general = $stats['total_tareas'] > 0
    ? round(($stats['tareas_completadas'] / $stats['total_tareas']) * 100)
    : 0;

// ⭐ Filtrar solo proyectos donde SOY LÍDER (para el selector de crear tarea)
$proyectos_como_lider = array_filter($proyectos_con_tareas, function($p) {
    return !empty($p['es_lider']); // Solo proyectos donde es_lider = true
});
?>

<style>
/* === Dashboard Proyectos Empleado - Estilos === */
.pyt-stats-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.pyt-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: white;
    border: 1px solid #e6e6e6;
    border-radius: 12px;
}

.pyt-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--c-accent, #EF7F1B);
}

.pyt-stat-label {
    font-size: 12px;
    color: #6a6a6a;
}

.pyt-stat-item.warning .pyt-stat-value {
    color: #EF4444;
}

.pyt-stat-item.highlight {
    background: linear-gradient(135deg, #FFF9F0 0%, #FFF5E8 100%);
    border-color: #EF7F1B;
}

/* Header con acciones */
.pyt-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.pyt-header-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--c-secondary, #184656);
}

/* Proyecto Card (Accordion) */
.pyt-proyecto {
    background: white;
    border: 1px solid #e6e6e6;
    border-radius: 16px;
    margin-bottom: 16px;
    overflow: hidden;
}

.pyt-proyecto.completado {
    opacity: 0.7;
}

/* Badge de líder */
.pyt-lider-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: linear-gradient(135deg, #EF7F1B 0%, #d66f15 100%);
    color: white;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pyt-proyecto-header {
    display: flex;
    align-items: center;
    padding: 16px;
    cursor: pointer;
    transition: background 0.15s ease;
    gap: 12px;
}

.pyt-proyecto-header:hover {
    background: rgba(0,0,0,0.02);
}

.pyt-proyecto-toggle {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6a6a6a;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.pyt-proyecto.expanded .pyt-proyecto-toggle {
    transform: rotate(90deg);
}

.pyt-proyecto-prioridad {
    width: 4px;
    height: 36px;
    border-radius: 2px;
    flex-shrink: 0;
}

.pyt-proyecto-prioridad.critica { background: #EF4444; }
.pyt-proyecto-prioridad.alta { background: #F59E0B; }
.pyt-proyecto-prioridad.media { background: #3B82F6; }
.pyt-proyecto-prioridad.baja { background: #10B981; }

.pyt-proyecto-info {
    flex: 1;
    min-width: 0;
}

.pyt-proyecto-titulo {
    font-size: 15px;
    font-weight: 600;
    color: var(--c-secondary, #184656);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pyt-proyecto-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #6a6a6a;
}

.pyt-proyecto-lider {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pyt-proyecto-progress {
    width: 140px;
    flex-shrink: 0;
    text-align: right;
}

.pyt-progress-bar {
    height: 6px;
    background: #E5E7EB;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 4px;
}

.pyt-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10B981, #059669);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.pyt-progress-text {
    font-size: 11px;
    color: #6a6a6a;
}

.pyt-proyecto-estado {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
}

.pyt-proyecto-estado.planificacion { background: #E0E7FF; color: #4338CA; }
.pyt-proyecto-estado.en_progreso { background: #DBEAFE; color: #1D4ED8; }
.pyt-proyecto-estado.pausado { background: #FEF3C7; color: #D97706; }
.pyt-proyecto-estado.completado { background: #D1FAE5; color: #059669; }
.pyt-proyecto-estado.cancelado { background: #FEE2E2; color: #DC2626; }

/* Contenido expandible (Tareas) */
.pyt-proyecto-body {
    display: none;
    border-top: 1px solid #e6e6e6;
    background: #FAFBFC;
}

.pyt-proyecto.expanded .pyt-proyecto-body {
    display: block;
}

/* Tabla de Tareas */
.pyt-tareas-table {
    width: 100%;
    border-collapse: collapse;
}

.pyt-tareas-table th {
    text-align: left;
    padding: 10px 16px;
    font-size: 11px;
    font-weight: 600;
    color: #6a6a6a;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e6e6e6;
    background: white;
}

.pyt-tareas-table td {
    padding: 12px 16px;
    font-size: 13px;
    border-bottom: 1px solid #F3F4F6;
    vertical-align: middle;
}

.pyt-tareas-table tr:last-child td {
    border-bottom: none;
}

.pyt-tareas-table tr:hover td {
    background: white;
}

.pyt-tareas-table tr.mi-tarea td {
    background: rgba(239, 127, 27, 0.05);
}

.pyt-tarea-titulo {
    font-weight: 500;
    color: var(--c-secondary, #184656);
    display: flex;
    align-items: center;
    gap: 8px;
}

.pyt-tarea-titulo.completada {
    text-decoration: line-through;
    opacity: 0.6;
}

.pyt-mi-tarea-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #EF7F1B;
    color: white;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
}

.pyt-tarea-responsable {
    color: #6a6a6a;
}

.pyt-tarea-deadline {
    font-size: 12px;
    font-weight: 500;
}

.pyt-tarea-deadline.vencida {
    color: #DC2626;
    background: #FEE2E2;
    padding: 2px 8px;
    border-radius: 4px;
}

.pyt-tarea-deadline.hoy {
    color: #D97706;
    background: #FEF3C7;
    padding: 2px 8px;
    border-radius: 4px;
}

.pyt-tarea-deadline.proxima {
    color: #2563EB;
}

.pyt-tarea-estado {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.pyt-tarea-estado.pendiente { background: #F3F4F6; color: #6B7280; }
.pyt-tarea-estado.en_progreso { background: #DBEAFE; color: #1D4ED8; }
.pyt-tarea-estado.completada { background: #D1FAE5; color: #059669; }
.pyt-tarea-estado.cancelada { background: #FEE2E2; color: #DC2626; }

/* Controles editables (solo para líder) */
.pyt-edit-select {
    padding: 6px 10px;
    border: 1px solid #e6e6e6;
    border-radius: 8px;
    font-size: 12px;
    background: white;
    cursor: pointer;
    min-width: 120px;
    transition: all 0.2s ease;
}

.pyt-edit-select:hover {
    border-color: #EF7F1B;
}

.pyt-edit-select:focus {
    outline: none;
    border-color: #EF7F1B;
    box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.1);
}

.pyt-edit-date {
    padding: 6px 10px;
    border: 1px solid #e6e6e6;
    border-radius: 8px;
    font-size: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pyt-edit-date:hover {
    border-color: #EF7F1B;
}

.pyt-edit-date:focus {
    outline: none;
    border-color: #EF7F1B;
    box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.1);
}

/* Botones */
.pyt-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.15s ease;
}

.pyt-btn-primary {
    background: var(--c-accent, #EF7F1B);
    color: white;
}

.pyt-btn-primary:hover {
    background: #d66f15;
    transform: translateY(-1px);
}

.pyt-btn-ghost {
    background: transparent;
    color: #6a6a6a;
    padding: 6px 10px;
}

.pyt-btn-ghost:hover {
    background: #f3f4f6;
}

.pyt-btn-sm {
    padding: 4px 8px;
    font-size: 11px;
}

/* Nota de permisos */
.pyt-permisos-nota {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 12px;
    color: #6a6a6a;
    margin-bottom: 12px;
}

.pyt-permisos-nota.es-lider {
    background: linear-gradient(135deg, #FFF9F0 0%, #FFF5E8 100%);
    border: 1px solid rgba(239, 127, 27, 0.2);
    color: #8a4709;
}

/* Empty state */
.pyt-empty {
    text-align: center;
    padding: 40px;
    color: #6a6a6a;
}

.pyt-empty-icon {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.4;
}

.pyt-empty-text {
    opacity: 0.6;
    margin-bottom: 16px;
}

/* Sin tareas */
.pyt-sin-tareas {
    padding: 24px;
    text-align: center;
    color: #6a6a6a;
    font-size: 13px;
}

/* Agregar tarea (solo líder) */
.pyt-agregar-tarea {
    padding: 12px 16px;
    background: white;
    border-top: 1px solid #e6e6e6;
}

.pyt-agregar-tarea-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #6a6a6a;
    font-size: 13px;
    cursor: pointer;
    padding: 6px 0;
    transition: opacity 0.15s;
}

.pyt-agregar-tarea-btn:hover {
    color: #EF7F1B;
}

/* Feedback guardado */
.pyt-saved-feedback {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: #D1FAE5;
    color: #059669;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    animation: fadeInOut 2s ease forwards;
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateY(-5px); }
    20% { opacity: 1; transform: translateY(0); }
    80% { opacity: 1; }
    100% { opacity: 0; }
}

/* Modal para nueva tarea */
.pyt-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
}

.pyt-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.pyt-modal {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    transform: translateY(20px);
    transition: transform 0.2s ease;
}

.pyt-modal-overlay.active .pyt-modal {
    transform: translateY(0);
}

.pyt-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e6e6e6;
}

.pyt-modal-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--c-secondary, #184656);
}

.pyt-modal-close {
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    font-size: 18px;
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pyt-modal-close:hover {
    background: #f3f4f6;
}

.pyt-modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: calc(90vh - 130px);
}

.pyt-form-group {
    margin-bottom: 16px;
}

.pyt-form-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--c-secondary, #184656);
    margin-bottom: 6px;
}

.pyt-form-input,
.pyt-form-select,
.pyt-form-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e6e6e6;
    border-radius: 8px;
    font-size: 14px;
}

.pyt-form-input:focus,
.pyt-form-select:focus,
.pyt-form-textarea:focus {
    outline: none;
    border-color: #EF7F1B;
    box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.1);
}

.pyt-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.pyt-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid #e6e6e6;
    background: #FAFBFC;
}

.pyt-btn-secondary {
    background: white;
    border: 1px solid #e6e6e6;
    color: #6a6a6a;
}

.pyt-btn-secondary:hover {
    background: #F3F4F6;
}

@media (max-width: 768px) {
    .pyt-stats-bar {
        flex-direction: column;
    }

    .pyt-proyecto-header {
        flex-wrap: wrap;
    }

    .pyt-proyecto-progress {
        width: 100%;
        margin-top: 8px;
    }

    .pyt-form-row {
        grid-template-columns: 1fr;
    }
}

/* =====================================================================
   UPGRADE UX/UI - PANEL DE TAREAS (proyectos_tareas_panel.php)
   Versión 2.0

   INSTRUCCIONES:
   1. Copia estos estilos
   2. Agrégalos AL FINAL del bloque <style> en proyectos_tareas_panel.php
   3. Los estilos nuevos sobrescribirán los anteriores automáticamente
   ===================================================================== */


/* =============================================
   VARIABLES CSS (agregar si no existen)
   ============================================= */
:root {
  /* Tipografía */
  --text-xs: 11px;
  --text-sm: 13px;
  --text-base: 15px;
  --text-lg: 18px;
  --text-xl: 22px;

  /* Espaciado */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;

  /* Colores */
  --color-primary: #667EEA;
  --color-secondary: #764BA2;
  --color-accent: #EF7F1B;
  --color-accent-dark: #d66f15;
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

  /* Bordes y sombras */
  --border-color: #e5e7eb;
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);

  /* Transiciones */
  --transition-fast: 0.15s ease;
  --transition-normal: 0.25s ease;
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
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

@keyframes pulse-ring {
  0% { box-shadow: 0 0 0 0 rgba(239, 127, 27, 0.4); }
  70% { box-shadow: 0 0 0 6px rgba(239, 127, 27, 0); }
  100% { box-shadow: 0 0 0 0 rgba(239, 127, 27, 0); }
}


/* =============================================
   CONTENEDOR PRINCIPAL - STATS
   ============================================= */
.pyt-stats {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) !important;
  gap: var(--space-4) !important;
  margin-bottom: var(--space-5) !important;
}

.pyt-stat {
  background: var(--bg-primary) !important;
  padding: var(--space-4) !important;
  border-radius: 16px !important;
  border: 1px solid var(--border-color) !important;
  text-align: center !important;
  transition: all var(--transition-normal) !important;
}

.pyt-stat:hover {
  transform: translateY(-2px) !important;
  box-shadow: var(--shadow-md) !important;
}

.pyt-stat-value {
  font-size: var(--text-xl) !important;
  font-weight: 800 !important;
  color: var(--text-primary) !important;
  line-height: 1 !important;
  margin-bottom: var(--space-1) !important;
}

.pyt-stat-label {
  font-size: var(--text-xs) !important;
  color: var(--text-tertiary) !important;
  font-weight: 500 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
}


/* =============================================
   PROYECTO CARD - REDISEÑADO
   ============================================= */
.pyt-proyecto {
  background: var(--bg-primary) !important;
  border: 1px solid var(--border-color) !important;
  border-radius: 16px !important;
  margin-bottom: var(--space-4) !important;
  overflow: hidden !important;
  box-shadow: var(--shadow-sm) !important;
  transition: all var(--transition-normal) !important;
}

.pyt-proyecto:hover {
  box-shadow: var(--shadow-md) !important;
  border-color: #d1d5db !important;
}

.pyt-proyecto.es-lider {
  border-left: 4px solid var(--color-primary) !important;
}

.pyt-proyecto.completado {
  opacity: 0.75 !important;
}


/* =============================================
   PROYECTO HEADER - F-PATTERN
   ============================================= */
.pyt-proyecto-header {
  display: flex !important;
  align-items: center !important;
  padding: var(--space-4) var(--space-5) !important;
  cursor: pointer !important;
  transition: background var(--transition-fast) !important;
  gap: var(--space-4) !important;
  background: var(--bg-secondary) !important;
}

.pyt-proyecto-header:hover {
  background: var(--bg-tertiary) !important;
}

.pyt-proyecto-header:active {
  background: #ebedf0 !important;
}

/* Toggle icon */
.pyt-proyecto-toggle {
  width: 28px !important;
  height: 28px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  color: var(--text-muted) !important;
  transition: all var(--transition-normal) !important;
  flex-shrink: 0 !important;
  font-size: 12px !important;
  background: var(--bg-tertiary) !important;
  border-radius: 8px !important;
}

.pyt-proyecto.expanded .pyt-proyecto-toggle {
  transform: rotate(90deg) !important;
  background: var(--color-primary) !important;
  color: white !important;
}

/* Priority indicator */
.pyt-proyecto-prioridad {
  width: 4px !important;
  height: 40px !important;
  border-radius: 2px !important;
  flex-shrink: 0 !important;
}

.pyt-proyecto-prioridad.critica { background: var(--color-danger) !important; }
.pyt-proyecto-prioridad.alta { background: var(--color-warning) !important; }
.pyt-proyecto-prioridad.media { background: var(--color-info) !important; }
.pyt-proyecto-prioridad.baja { background: var(--color-success) !important; }

/* Project info */
.pyt-proyecto-info {
  flex: 1 !important;
  min-width: 0 !important;
}

.pyt-proyecto-titulo {
  font-size: var(--text-lg) !important;
  font-weight: 700 !important;
  color: var(--text-primary) !important;
  margin-bottom: var(--space-1) !important;
  display: flex !important;
  align-items: center !important;
  gap: var(--space-3) !important;
}

/* Leader badge */
.pyt-lider-badge {
  display: inline-flex !important;
  align-items: center !important;
  gap: 4px !important;
  padding: 3px 10px !important;
  background: linear-gradient(135deg, var(--color-primary), var(--color-secondary)) !important;
  color: white !important;
  border-radius: 20px !important;
  font-size: 9px !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
}

/* Project metadata */
.pyt-proyecto-meta {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-4) !important;
  font-size: var(--text-sm) !important;
  color: var(--text-tertiary) !important;
  flex-wrap: wrap !important;
}

.pyt-proyecto-meta > span {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-1) !important;
}


/* =============================================
   PROGRESS BAR - PROMINENTE
   ============================================= */
.pyt-proyecto-progress {
  width: 160px !important;
  flex-shrink: 0 !important;
}

.pyt-progress-bar {
  height: 10px !important;
  background: var(--bg-tertiary) !important;
  border-radius: 5px !important;
  overflow: hidden !important;
  margin-bottom: 6px !important;
}

.pyt-progress-fill {
  height: 100% !important;
  border-radius: 5px !important;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1) !important;
  position: relative !important;
}

/* Progress fill colors by percentage */
.pyt-progress-fill[style*="width: 0%"],
.pyt-progress-fill[style*="width:0%"] {
  background: var(--bg-tertiary) !important;
}

.pyt-progress-fill {
  background: linear-gradient(90deg, var(--color-info), #60a5fa) !important;
}

.pyt-proyecto[data-progress="medium"] .pyt-progress-fill {
  background: linear-gradient(90deg, var(--color-warning), #fbbf24) !important;
}

.pyt-proyecto[data-progress="high"] .pyt-progress-fill {
  background: linear-gradient(90deg, var(--color-success), #34d399) !important;
}

.pyt-proyecto[data-progress="complete"] .pyt-progress-fill {
  background: linear-gradient(90deg, #059669, var(--color-success)) !important;
}

/* Shimmer effect */
.pyt-progress-fill::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
  animation: shimmer 2s infinite;
  background-size: 200% 100%;
}

.pyt-progress-text {
  font-size: var(--text-xl) !important;
  font-weight: 800 !important;
  color: var(--text-primary) !important;
  text-align: right !important;
}


/* =============================================
   STATUS BADGES - DOTS + TEXT
   ============================================= */
.pyt-proyecto-estado {
  padding: 6px 14px !important;
  border-radius: 20px !important;
  font-size: var(--text-xs) !important;
  font-weight: 600 !important;
  flex-shrink: 0 !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
}

.pyt-proyecto-estado::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.pyt-proyecto-estado.planificacion {
  background: #faf5ff !important;
  color: #7c3aed !important;
}
.pyt-proyecto-estado.planificacion::before { background: #a855f7; }

.pyt-proyecto-estado.en_progreso {
  background: var(--color-info-bg) !important;
  color: #1d4ed8 !important;
}
.pyt-proyecto-estado.en_progreso::before { background: var(--color-info); }

.pyt-proyecto-estado.pausado {
  background: #fff7ed !important;
  color: #ea580c !important;
}
.pyt-proyecto-estado.pausado::before { background: #f97316; }

.pyt-proyecto-estado.completado {
  background: var(--color-success-bg) !important;
  color: #047857 !important;
}
.pyt-proyecto-estado.completado::before { background: var(--color-success); }

.pyt-proyecto-estado.cancelado {
  background: var(--color-neutral-bg) !important;
  color: var(--color-neutral) !important;
}
.pyt-proyecto-estado.cancelado::before { background: var(--color-neutral); }


/* =============================================
   TAREAS BODY - EXPANDIBLE
   ============================================= */
.pyt-proyecto-body {
  display: none !important;
  border-top: 1px solid var(--border-color) !important;
  background: var(--bg-primary) !important;
  animation: slideDown var(--transition-normal) ease-out !important;
}

.pyt-proyecto.expanded .pyt-proyecto-body {
  display: block !important;
}

/* Header de tareas */
.pyt-tareas-header {
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  padding: var(--space-3) var(--space-5) !important;
  background: var(--bg-tertiary) !important;
  border-bottom: 1px solid var(--border-color) !important;
}

.pyt-tareas-header span {
  font-size: var(--text-sm) !important;
  font-weight: 600 !important;
  color: var(--text-secondary) !important;
}


/* =============================================
   TABLA DE TAREAS - OPTIMIZADA
   ============================================= */
.pyt-tareas-table {
  width: 100% !important;
  border-collapse: collapse !important;
}

.pyt-tareas-table th {
  text-align: left !important;
  padding: var(--space-3) var(--space-5) !important;
  font-size: var(--text-xs) !important;
  font-weight: 600 !important;
  color: var(--text-tertiary) !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid var(--border-color) !important;
  background: var(--bg-secondary) !important;
}

.pyt-tareas-table td {
  padding: var(--space-3) var(--space-5) !important;
  font-size: var(--text-sm) !important;
  border-bottom: 1px solid #f3f4f6 !important;
  vertical-align: middle !important;
  transition: background var(--transition-fast) !important;
}

.pyt-tareas-table tr:last-child td {
  border-bottom: none !important;
}

.pyt-tareas-table tbody tr:hover td {
  background: var(--bg-secondary) !important;
}


/* =============================================
   MI TAREA - HIGHLIGHT
   ============================================= */
.pyt-tareas-table tr.mi-tarea {
  border-left: 3px solid var(--color-accent) !important;
}

.pyt-tareas-table tr.mi-tarea td {
  background: linear-gradient(90deg, rgba(239,127,27,0.05) 0%, transparent 50%) !important;
}

.pyt-tareas-table tr.mi-tarea:hover td {
  background: linear-gradient(90deg, rgba(239,127,27,0.08) 0%, var(--bg-secondary) 50%) !important;
}

.pyt-mi-tarea-badge {
  display: none !important; /* Ocultamos badge, usamos borde izquierdo */
}


/* =============================================
   TAREA TÍTULO Y ESTADO
   ============================================= */
.pyt-tarea-titulo {
  font-weight: 500 !important;
  color: var(--text-primary) !important;
  display: flex !important;
  align-items: center !important;
  gap: var(--space-2) !important;
}

.pyt-tarea-titulo.completada {
  text-decoration: line-through !important;
  opacity: 0.6 !important;
}


/* =============================================
   RESPONSABLE - AVATAR
   ============================================= */
.pyt-tarea-responsable {
  display: flex !important;
  align-items: center !important;
  gap: var(--space-2) !important;
  color: var(--text-secondary) !important;
  font-size: var(--text-sm) !important;
}

.pyt-responsable-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.mi-tarea .pyt-responsable-avatar {
  background: var(--color-accent);
}


/* =============================================
   ESTADO DE TAREA - DOTS
   ============================================= */
.pyt-tarea-estado {
  padding: 5px 12px !important;
  border-radius: 20px !important;
  font-size: var(--text-xs) !important;
  font-weight: 600 !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
}

.pyt-tarea-estado::before {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.pyt-tarea-estado.pendiente {
  background: var(--color-warning-bg) !important;
  color: #b45309 !important;
}
.pyt-tarea-estado.pendiente::before { background: var(--color-warning); }

.pyt-tarea-estado.en_progreso {
  background: var(--color-info-bg) !important;
  color: #1d4ed8 !important;
}
.pyt-tarea-estado.en_progreso::before { background: var(--color-info); }

.pyt-tarea-estado.completada {
  background: var(--color-success-bg) !important;
  color: #047857 !important;
}
.pyt-tarea-estado.completada::before { background: var(--color-success); }

.pyt-tarea-estado.cancelada {
  background: var(--color-neutral-bg) !important;
  color: var(--color-neutral) !important;
}
.pyt-tarea-estado.cancelada::before { background: var(--color-neutral); }


/* =============================================
   DEADLINE - INDICADORES VISUALES
   ============================================= */
.pyt-tarea-deadline {
  font-size: var(--text-xs) !important;
  font-weight: 500 !important;
  padding: 4px 10px !important;
  border-radius: 6px !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 4px !important;
}

.pyt-tarea-deadline.vencida {
  color: var(--color-danger) !important;
  background: var(--color-danger-bg) !important;
  font-weight: 700 !important;
  animation: pulse-ring 1.5s ease-in-out infinite !important;
}

.pyt-tarea-deadline.vencida::before {
  content: '!';
  font-weight: 800;
}

.pyt-tarea-deadline.hoy {
  color: #ea580c !important;
  background: #fff7ed !important;
  font-weight: 600 !important;
}

.pyt-tarea-deadline.proxima {
  color: #b45309 !important;
  background: var(--color-warning-bg) !important;
}

.pyt-tarea-deadline.normal {
  color: var(--text-tertiary) !important;
  background: transparent !important;
}


/* =============================================
   CONTROLES EDITABLES (LÍDER)
   ============================================= */
.pyt-edit-select,
.pyt-edit-date {
  padding: 8px 12px !important;
  border: 2px solid var(--border-color) !important;
  border-radius: 10px !important;
  font-size: var(--text-sm) !important;
  background: var(--bg-primary) !important;
  cursor: pointer !important;
  transition: all var(--transition-fast) !important;
}

.pyt-edit-select:hover,
.pyt-edit-date:hover {
  border-color: var(--color-accent) !important;
}

.pyt-edit-select:focus,
.pyt-edit-date:focus {
  outline: none !important;
  border-color: var(--color-accent) !important;
  box-shadow: 0 0 0 3px rgba(239, 127, 27, 0.15) !important;
}


/* =============================================
   BOTONES
   ============================================= */
.pyt-btn {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 6px !important;
  padding: 8px 16px !important;
  border-radius: 10px !important;
  font-size: var(--text-sm) !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  border: none !important;
  transition: all var(--transition-fast) !important;
}

.pyt-btn:active {
  transform: scale(0.98) !important;
}

.pyt-btn-primary {
  background: linear-gradient(135deg, var(--color-primary), var(--color-secondary)) !important;
  color: white !important;
}

.pyt-btn-primary:hover {
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
  transform: translateY(-1px) !important;
}

.pyt-btn-accent {
  background: linear-gradient(135deg, var(--color-accent), var(--color-accent-dark)) !important;
  color: white !important;
}

.pyt-btn-accent:hover {
  box-shadow: 0 4px 12px rgba(239, 127, 27, 0.4) !important;
  transform: translateY(-1px) !important;
}

.pyt-btn-ghost {
  background: var(--bg-tertiary) !important;
  color: var(--text-secondary) !important;
}

.pyt-btn-ghost:hover {
  background: var(--border-color) !important;
}

.pyt-btn-sm {
  padding: 6px 12px !important;
  font-size: var(--text-xs) !important;
}


/* =============================================
   EMPTY STATE
   ============================================= */
.pyt-empty {
  text-align: center !important;
  padding: 60px var(--space-5) !important;
  color: var(--text-tertiary) !important;
}

.pyt-empty-icon {
  font-size: 56px !important;
  margin-bottom: var(--space-4) !important;
  opacity: 0.5 !important;
}

.pyt-empty p {
  font-size: var(--text-base) !important;
  color: var(--text-muted) !important;
}


/* =============================================
   TOAST NOTIFICATION
   ============================================= */
.pyt-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  padding: 14px 20px;
  border-radius: 12px;
  font-weight: 600;
  font-size: var(--text-sm);
  z-index: 10000;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: var(--shadow-lg);
  animation: toastSlideIn 0.3s ease;
}

.pyt-toast.success {
  background: var(--color-success);
  color: white;
}

.pyt-toast.error {
  background: var(--color-danger);
  color: white;
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
   RESPONSIVE
   ============================================= */
@media (max-width: 768px) {
  .pyt-proyecto-header {
    flex-wrap: wrap !important;
    padding: var(--space-4) !important;
  }

  .pyt-proyecto-progress {
    width: 100% !important;
    margin-top: var(--space-3) !important;
  }

  .pyt-proyecto-meta {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: var(--space-2) !important;
  }

  .pyt-tareas-table th,
  .pyt-tareas-table td {
    padding: var(--space-2) var(--space-3) !important;
  }

  .pyt-tareas-table .col-responsable {
    display: none !important;
  }

  .pyt-tarea-titulo {
    font-size: var(--text-sm) !important;
  }
}


/* =============================================
   SKELETON LOADING
   ============================================= */
.pyt-skeleton {
  padding: var(--space-4);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  margin-bottom: var(--space-4);
}

.pyt-skeleton-line {
  height: 16px;
  background: linear-gradient(90deg, var(--bg-tertiary) 25%, var(--bg-secondary) 50%, var(--bg-tertiary) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
  margin-bottom: var(--space-2);
}

.pyt-skeleton-line.title {
  width: 60%;
  height: 24px;
}

.pyt-skeleton-line.meta {
  width: 40%;
  height: 14px;
}

.pyt-skeleton-line.progress {
  width: 100%;
  height: 10px;
  margin-top: var(--space-3);
}
</style>

<?php if (!$tablas_existen): ?>
<div class="pyt-empty">
    <div class="pyt-empty-icon">📋</div>
    <h3 style="margin-bottom: 8px;">Sistema de Proyectos</h3>
    <p class="pyt-empty-text">El sistema de proyectos aún no está configurado.</p>
</div>
<?php elseif (empty($proyectos_con_tareas)): ?>
<div class="pyt-empty">
    <div class="pyt-empty-icon">📂</div>
    <h3 style="margin-bottom: 8px;">Sin proyectos</h3>
    <p class="pyt-empty-text">No participas en ningún proyecto actualmente.</p>
</div>
<?php else: ?>

<!-- Stats Bar -->
<div class="pyt-stats-bar">
    <div class="pyt-stat-item">
        <div>
            <div class="pyt-stat-value"><?= $stats['total_proyectos'] ?></div>
            <div class="pyt-stat-label">Proyectos</div>
        </div>
    </div>
    <div class="pyt-stat-item highlight">
        <div>
            <div class="pyt-stat-value"><?= $stats['mis_tareas'] ?></div>
            <div class="pyt-stat-label">Mis tareas</div>
        </div>
    </div>
    <div class="pyt-stat-item">
        <div>
            <div class="pyt-stat-value"><?= $porcentaje_general ?>%</div>
            <div class="pyt-stat-label">Completado</div>
        </div>
    </div>
    <?php if ($stats['tareas_vencidas'] > 0): ?>
    <div class="pyt-stat-item warning">
        <div>
            <div class="pyt-stat-value"><?= $stats['tareas_vencidas'] ?></div>
            <div class="pyt-stat-label">Vencidas</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Lista de Proyectos -->
<?php foreach ($proyectos_con_tareas as $proyecto):
    $es_lider = $proyecto['es_lider'];
?>
    <div class="pyt-proyecto <?= $proyecto['proyecto_estado'] === 'completado' ? 'completado' : '' ?>"
         id="proyecto-<?= $proyecto['id'] ?>"
         data-es-lider="<?= $es_lider ? '1' : '0' ?>">

        <!-- Header del Proyecto -->
        <div class="pyt-proyecto-header" onclick="toggleProyectoEmpleado(<?= $proyecto['id'] ?>)">
            <div class="pyt-proyecto-toggle">▶</div>
            <div class="pyt-proyecto-prioridad <?= $proyecto['prioridad'] ?>"></div>
            <div class="pyt-proyecto-info">
                <div class="pyt-proyecto-titulo">
                    <?= htmlspecialchars($proyecto['titulo']) ?>
                    <?php if ($es_lider): ?>
                        <span class="pyt-lider-badge">👑 Eres el líder</span>
                    <?php endif; ?>
                </div>
                <div class="pyt-proyecto-meta">
                    <span class="pyt-proyecto-lider">
                        👤 <?= htmlspecialchars($proyecto['lider_nombre'] ?? 'Sin líder') ?>
                    </span>
                    <?php if ($proyecto['fecha_fin_estimada']): ?>
                        <span>📅 <?= date('d M', strtotime($proyecto['fecha_fin_estimada'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pyt-proyecto-progress">
                <div class="pyt-progress-bar">
                    <div class="pyt-progress-fill" style="width: <?= $proyecto['porcentaje'] ?>%"></div>
                </div>
                <div class="pyt-progress-text"><?= $proyecto['porcentaje'] ?>% · <?= $proyecto['tareas_completadas'] ?>/<?= $proyecto['total_tareas'] ?> tareas</div>
            </div>
            <div class="pyt-proyecto-estado <?= $proyecto['proyecto_estado'] ?>">
                <?= ucfirst(str_replace('_', ' ', $proyecto['proyecto_estado'])) ?>
            </div>
        </div>

        <!-- Cuerpo: Lista de Tareas -->
        <div class="pyt-proyecto-body">

            <!-- Nota de permisos -->
            <div class="pyt-permisos-nota <?= $es_lider ? 'es-lider' : '' ?>" style="margin: 12px 16px;">
                <?php if ($es_lider): ?>
                    👑 <strong>Eres el líder</strong> — Puedes editar responsable, deadline y estado de las tareas
                <?php else: ?>
                    👁️ <strong>Solo lectura</strong> — Solo el líder (<?= htmlspecialchars($proyecto['lider_nombre']) ?>) puede editar las tareas
                <?php endif; ?>
            </div>

            <?php if (empty($proyecto['tareas'])): ?>
                <div class="pyt-sin-tareas">No hay tareas en este proyecto</div>
            <?php else: ?>
                <table class="pyt-tareas-table">
                    <thead>
                        <tr>
                            <th>Tarea</th>
                            <th style="width: 150px;">Responsable</th>
                            <th style="width: 130px;">Deadline</th>
                            <th style="width: 130px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyecto['tareas'] as $tarea):
                            $dias = $tarea['dias_restantes'];
                            $deadline_class = '';
                            if ($tarea['estado'] !== 'completada' && $tarea['estado'] !== 'cancelada') {
                                if ($dias < 0) $deadline_class = 'vencida';
                                elseif ($dias === 0) $deadline_class = 'hoy';
                                elseif ($dias <= 3) $deadline_class = 'proxima';
                            }
                        ?>
                            <tr class="<?= $tarea['es_mi_tarea'] ? 'mi-tarea' : '' ?>"
                                data-tarea-id="<?= $tarea['id'] ?>">
                                <td>
                                    <div class="pyt-tarea-titulo <?= $tarea['estado'] === 'completada' ? 'completada' : '' ?>">
                                        <?= htmlspecialchars($tarea['titulo']) ?>
                                        <?php if ($tarea['es_mi_tarea']): ?>
                                            <span class="pyt-mi-tarea-badge">Mi tarea</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- RESPONSABLE -->
                                <td class="pyt-tarea-responsable">
                                    <?php if ($es_lider): ?>
                                        <!-- ✅ LÍDER: Puede editar responsable -->
                                        <select class="pyt-edit-select"
                                                onchange="actualizarTareaEmpleado(<?= $tarea['id'] ?>, 'responsable_id', this.value, this)">
                                            <?php foreach ($empleados_equipo as $emp): ?>
                                                <option value="<?= $emp['id'] ?>"
                                                    <?= (int)$emp['id'] === (int)$tarea['responsable_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($emp['nombre_persona']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <!-- ❌ NO LÍDER: Solo lectura -->
                                        <?= htmlspecialchars($tarea['responsable_nombre'] ?? 'Sin asignar') ?>
                                    <?php endif; ?>
                                </td>

                                <!-- DEADLINE -->
                                <td>
                                    <?php if ($es_lider): ?>
                                        <!-- ✅ LÍDER: Puede editar deadline -->
                                        <input type="date"
                                               class="pyt-edit-date"
                                               value="<?= $tarea['deadline'] ?? '' ?>"
                                               onchange="actualizarTareaEmpleado(<?= $tarea['id'] ?>, 'deadline', this.value, this)">
                                    <?php else: ?>
                                        <!-- ❌ NO LÍDER: Solo lectura -->
                                        <?php if ($tarea['deadline']): ?>
                                            <span class="pyt-tarea-deadline <?= $deadline_class ?>">
                                                <?= date('d/m/Y', strtotime($tarea['deadline'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="opacity: 0.4;">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- ESTADO -->
                                <td>
                                    <?php if ($es_lider): ?>
                                        <!-- ✅ LÍDER: Puede editar estado -->
                                        <select class="pyt-edit-select"
                                                onchange="actualizarTareaEmpleado(<?= $tarea['id'] ?>, 'estado', this.value, this)">
                                            <option value="pendiente" <?= $tarea['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                            <option value="en_progreso" <?= $tarea['estado'] === 'en_progreso' ? 'selected' : '' ?>>En progreso</option>
                                            <option value="completada" <?= $tarea['estado'] === 'completada' ? 'selected' : '' ?>>Completada</option>
                                            <option value="cancelada" <?= $tarea['estado'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                        </select>
                                    <?php else: ?>
                                        <!-- ❌ NO LÍDER: Solo lectura -->
                                        <span class="pyt-tarea-estado <?= $tarea['estado'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $tarea['estado'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($es_lider): ?>
            <!-- Botón agregar tarea (solo líder) -->
            <div class="pyt-agregar-tarea">
                <div class="pyt-agregar-tarea-btn" onclick="abrirModalTareaEmpleado(<?= $proyecto['id'] ?>)">
                    + Agregar tarea
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modal: Nueva Tarea (solo para líderes) -->
<div class="pyt-modal-overlay" id="modalTareaEmpleado">
    <div class="pyt-modal">
        <div class="pyt-modal-header">
            <div class="pyt-modal-title">Nueva Tarea</div>
            <button class="pyt-modal-close" onclick="cerrarModalEmpleado('modalTareaEmpleado')">&times;</button>
        </div>
        <div class="pyt-modal-body">
            <!-- ⭐ Selector de Proyecto: Solo muestra proyectos donde SOY LÍDER -->
            <div class="pyt-form-group">
                <label class="pyt-form-label">Proyecto * <span style="font-weight:400;color:#6a6a6a;">(solo proyectos donde eres líder)</span></label>
                <select class="pyt-form-select" id="nueva_tarea_proyecto_id" required>
                    <option value="">Seleccionar proyecto...</option>
                    <?php foreach ($proyectos_como_lider as $proy): ?>
                        <option value="<?= $proy['id'] ?>"><?= htmlspecialchars($proy['titulo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pyt-form-group">
                <label class="pyt-form-label">Título *</label>
                <input type="text" class="pyt-form-input" id="nueva_tarea_titulo" required>
            </div>

            <div class="pyt-form-group">
                <label class="pyt-form-label">Descripción</label>
                <textarea class="pyt-form-textarea" id="nueva_tarea_descripcion" rows="2"></textarea>
            </div>

            <div class="pyt-form-row">
                <div class="pyt-form-group">
                    <label class="pyt-form-label">Responsable *</label>
                    <select class="pyt-form-select" id="nueva_tarea_responsable" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($empleados_equipo as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre_persona']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pyt-form-group">
                    <label class="pyt-form-label">Prioridad</label>
                    <select class="pyt-form-select" id="nueva_tarea_prioridad">
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                        <option value="critica">Crítica</option>
                    </select>
                </div>
            </div>

            <div class="pyt-form-group">
                <label class="pyt-form-label">Deadline</label>
                <input type="date" class="pyt-form-input" id="nueva_tarea_deadline">
            </div>
        </div>
        <div class="pyt-modal-footer">
            <button type="button" class="pyt-btn pyt-btn-secondary" onclick="cerrarModalEmpleado('modalTareaEmpleado')">Cancelar</button>
            <button type="button" class="pyt-btn pyt-btn-primary" onclick="guardarNuevaTareaEmpleado()">Crear Tarea</button>
        </div>
    </div>
</div>

<script>
// ============================================
// JS para Panel de Proyectos del Empleado
// ============================================

const EMPLEADO_ID_PROYECTOS = <?= (int)$empleado_id ?>;
const USER_ID_PROYECTOS = <?= (int)$user_id ?>;

// Toggle proyecto expandido
function toggleProyectoEmpleado(proyectoId) {
    const proyecto = document.getElementById('proyecto-' + proyectoId);
    proyecto.classList.toggle('expanded');
}

// Cerrar modal
function cerrarModalEmpleado(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Abrir modal para nueva tarea
function abrirModalTareaEmpleado(proyectoId = null) {
    // Limpiar campos
    document.getElementById('nueva_tarea_titulo').value = '';
    document.getElementById('nueva_tarea_descripcion').value = '';
    document.getElementById('nueva_tarea_responsable').value = '';
    document.getElementById('nueva_tarea_prioridad').value = 'media';
    document.getElementById('nueva_tarea_deadline').value = '';

    // Si viene de un proyecto específico, pre-seleccionarlo
    const selectProyecto = document.getElementById('nueva_tarea_proyecto_id');
    if (proyectoId) {
        selectProyecto.value = proyectoId;
    } else {
        selectProyecto.value = '';
    }

    document.getElementById('modalTareaEmpleado').classList.add('active');
}

// Guardar nueva tarea
async function guardarNuevaTareaEmpleado() {
    const proyecto_id = document.getElementById('nueva_tarea_proyecto_id').value;
    const titulo = document.getElementById('nueva_tarea_titulo').value.trim();
    const descripcion = document.getElementById('nueva_tarea_descripcion').value.trim();
    const responsable_id = document.getElementById('nueva_tarea_responsable').value;
    const prioridad = document.getElementById('nueva_tarea_prioridad').value;
    const deadline = document.getElementById('nueva_tarea_deadline').value;

    if (!proyecto_id) {
        alert('Debe seleccionar un proyecto');
        return;
    }
    if (!titulo) {
        alert('El título es obligatorio');
        return;
    }
    if (!responsable_id) {
        alert('Debe seleccionar un responsable');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'crear_tarea');
    formData.append('usuario_id', USER_ID_PROYECTOS);
    formData.append('proyecto_id', proyecto_id);
    formData.append('titulo', titulo);
    formData.append('descripcion', descripcion);
    formData.append('responsable_id', responsable_id);
    formData.append('prioridad', prioridad);
    formData.append('deadline', deadline);

    try {
        const response = await fetch('proyectos_tareas_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success || data.ok) {
            cerrarModalEmpleado('modalTareaEmpleado');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'No se pudo crear la tarea'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
}

// Actualizar tarea (solo líder puede usar esto)
async function actualizarTareaEmpleado(tareaId, campo, valor, elemento) {
    const formData = new FormData();
    formData.append('action', 'actualizar_tarea_campo');
    formData.append('usuario_id', USER_ID_PROYECTOS);
    formData.append('tarea_id', tareaId);
    formData.append('campo', campo);
    formData.append('valor', valor);

    // Mostrar feedback visual
    elemento.style.opacity = '0.6';

    try {
        const response = await fetch('proyectos_tareas_backend.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        elemento.style.opacity = '1';

        if (data.success || data.ok) {
            // Mostrar feedback de guardado
            mostrarFeedbackGuardado(elemento);
        } else {
            alert('Error: ' + (data.message || 'No se pudo actualizar'));
            // Recargar para restaurar valores
            location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        elemento.style.opacity = '1';
        alert('Error de conexión');
    }
}

// Mostrar feedback visual de guardado
function mostrarFeedbackGuardado(elemento) {
    const feedback = document.createElement('span');
    feedback.className = 'pyt-saved-feedback';
    feedback.innerHTML = '✓ Guardado';

    elemento.parentNode.appendChild(feedback);

    setTimeout(() => {
        feedback.remove();
    }, 2000);
}

// Cerrar modales con ESC o click afuera
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        cerrarModalEmpleado('modalTareaEmpleado');
    }
});

document.querySelectorAll('.pyt-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('active');
    });
});

// Expandir primer proyecto por defecto
document.addEventListener('DOMContentLoaded', () => {
    const primerProyecto = document.querySelector('.pyt-proyecto');
    if (primerProyecto) primerProyecto.classList.add('expanded');
});
</script>

<?php endif; ?>