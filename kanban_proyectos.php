<?php
/**
 * KANBAN DE PROYECTOS — Valírica HHRR
 * Vista Kanban profesional para seguimiento de proyectos y tareas.
 * Columnas: Mis Tareas | Mi Área | Planificación | En Progreso | Pausado | Completado | [Cancelado]
 * Soporta modo embebido ($modo_embebido = true) para renderizar dentro del dashboard.
 */

$modo_embebido = $modo_embebido ?? false;

if (!$modo_embebido) {
    session_start();
    require 'config.php';
}
date_default_timezone_set('Europe/Madrid');

// ── Auth: determinar empleado_id ──
$empleado_id = (int)($_GET['id'] ?? 0);
if ($empleado_id <= 0 && !empty($_SESSION['empleado_id'])) {
    $empleado_id = (int)$_SESSION['empleado_id'];
}
if ($empleado_id <= 0) {
    echo "<p style='padding:24px;color:#B00020;'>Falta el parámetro ?id del empleado.</p>";
    exit;
}

// ── Auth: determinar user_id (empresa) ──
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (empty($user_id)) {
    $q = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ? LIMIT 1");
    $q->bind_param("i", $empleado_id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    $user_id = ($row && !empty($row['usuario_id'])) ? (int)$row['usuario_id'] : 0;
}

// ── Datos del empleado ──
$stmt_emp = $conn->prepare("
    SELECT e.id, e.nombre_persona, COALESCE(e.cargo,'—') AS cargo,
           COALESCE(e.area_trabajo,'—') AS area
    FROM equipo e WHERE e.id = ? AND e.usuario_id = ? LIMIT 1
");
$stmt_emp->bind_param("ii", $empleado_id, $user_id);
$stmt_emp->execute();
$emp = $stmt_emp->get_result()->fetch_assoc();
$stmt_emp->close();
if (!$emp) {
    echo "<p style='padding:24px;color:#B00020;'>Empleado no encontrado.</p>";
    exit;
}

// ── Datos de empresa ──
$stmt_co = $conn->prepare("SELECT empresa, logo FROM usuarios WHERE id = ?");
$stmt_co->bind_param("i", $user_id);
$stmt_co->execute();
$co = $stmt_co->get_result()->fetch_assoc() ?: [];
$stmt_co->close();
$empresa = $co['empresa'] ?? 'Empresa';
$logo    = $co['logo'] ?? '/uploads/logo-192.png';

// ── Auto-migración: agregar area_trabajo_id a equipo si no existe ──
try {
    $col_check = $conn->query("SHOW COLUMNS FROM equipo LIKE 'area_trabajo_id'");
    if ($col_check->num_rows === 0) {
        $conn->query("ALTER TABLE equipo ADD COLUMN area_trabajo_id INT NULL DEFAULT NULL");
    }
} catch (Exception $e) { /* silenciar */ }

// ── Determinar si es líder de área ──
$es_lider_area = false;
$area_lider_id = 0;
$area_lider_nombre = '';
try {
    $stmt_al = $conn->prepare("SELECT area_trabajo_id FROM equipo WHERE id = ? LIMIT 1");
    $stmt_al->bind_param("i", $empleado_id);
    $stmt_al->execute();
    $r_al = $stmt_al->get_result()->fetch_assoc();
    $stmt_al->close();
    if ($r_al && !empty($r_al['area_trabajo_id'])) {
        $area_lider_id = (int)$r_al['area_trabajo_id'];
        // Obtener nombre del área
        $stmt_an = $conn->prepare("SELECT nombre_area FROM areas_trabajo WHERE id = ? AND usuario_id = ? LIMIT 1");
        $stmt_an->bind_param("ii", $area_lider_id, $user_id);
        $stmt_an->execute();
        $r_an = $stmt_an->get_result()->fetch_assoc();
        $stmt_an->close();
        if ($r_an) {
            $es_lider_area = true;
            $area_lider_nombre = $r_an['nombre_area'];
        }
    }
} catch (Exception $e) { /* column may not exist yet */ }

if (!$modo_embebido) $conn->close();
?>
<?php if (!$modo_embebido): ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Proyectos — <?php echo htmlspecialchars($empresa); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#012133">
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/css/valirica-design-system.css">
<?php endif; ?>
  <style>
    @import url("https://use.typekit.net/qrv8fyz.css");

    /* ─── Tokens ─── */
    :root {
      --kb-primary: #012133;
      --kb-secondary: #184656;
      --kb-accent: #EF7F1B;
      --kb-bg: #f0f2f5;
      --kb-card: #ffffff;
      --kb-column-width: 330px;
      --kb-column-gap: 12px;
      --kb-radius: 12px;
      --kb-radius-sm: 8px;
      --kb-shadow-card: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
      --kb-shadow-card-hover: 0 4px 12px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
      --kb-transition: 200ms cubic-bezier(0.4,0,0.2,1);
      --font-family-base: "gelica", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      font-family: var(--font-family-base);
      background: var(--kb-bg);
      color: #1a1a1a;
      -webkit-font-smoothing: antialiased;
    }

    /* ─── Layout Shell ─── */
    .kb-shell {
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow: hidden;
    }

    /* ─── Top Bar ─── */
    .kb-topbar {
      background: var(--kb-primary);
      color: #fff;
      padding: 0 24px;
      height: 56px;
      display: flex;
      align-items: center;
      gap: 16px;
      flex-shrink: 0;
      z-index: 10;
    }
    .kb-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: rgba(255,255,255,0.85);
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 8px;
      transition: var(--kb-transition);
      border: 1px solid rgba(255,255,255,0.15);
      background: rgba(255,255,255,0.08);
    }
    .kb-back-btn:hover {
      background: rgba(255,255,255,0.15);
      color: #fff;
    }
    .kb-back-btn svg { width: 16px; height: 16px; }
    .kb-topbar-title {
      font-size: 16px;
      font-weight: 700;
      letter-spacing: -0.01em;
      flex: 1;
    }
    .kb-topbar-actions {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .kb-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--kb-transition);
      font-family: inherit;
      white-space: nowrap;
    }
    .kb-btn-accent {
      background: var(--kb-accent);
      color: #fff;
    }
    .kb-btn-accent:hover { background: #d66f15; }
    .kb-btn-ghost {
      background: rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.9);
      border: 1px solid rgba(255,255,255,0.15);
    }
    .kb-btn-ghost:hover { background: rgba(255,255,255,0.18); }
    .kb-btn-outline {
      background: #fff;
      color: var(--kb-secondary);
      border: 1px solid #d1d5db;
    }
    .kb-btn-outline:hover { border-color: #9ca3af; background: #f9fafb; }

    /* ─── Stats Strip ─── */
    .kb-stats {
      background: #fff;
      border-bottom: 1px solid #e5e7eb;
      padding: 10px 24px;
      display: flex;
      align-items: center;
      gap: 24px;
      flex-shrink: 0;
      overflow-x: auto;
    }
    .kb-stat {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #6b7280;
      white-space: nowrap;
    }
    .kb-stat strong {
      font-size: 18px;
      font-weight: 800;
      color: var(--kb-primary);
    }
    .kb-stat-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    /* ─── Kanban Board ─── */
    .kb-board {
      flex: 1;
      display: flex;
      gap: var(--kb-column-gap);
      padding: 16px;
      overflow-x: auto;
      overflow-y: hidden;
      scroll-behavior: smooth;
      -webkit-overflow-scrolling: touch;
    }
    .kb-board::-webkit-scrollbar { height: 8px; }
    .kb-board::-webkit-scrollbar-track { background: transparent; }
    .kb-board::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 4px;
    }

    /* ─── Column ─── */
    .kb-column {
      flex: 0 0 var(--kb-column-width);
      max-width: var(--kb-column-width);
      display: flex;
      flex-direction: column;
      background: #f8f9fb;
      border-radius: var(--kb-radius);
      border: 1px solid #e8eaed;
      max-height: 100%;
      transition: var(--kb-transition);
    }
    .kb-column.special {
      background: #fefcfa;
      border-color: #f0e6d9;
    }
    .kb-column-header {
      padding: 14px 16px 10px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .kb-column-color {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .kb-column-title {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #374151;
      flex: 1;
    }
    .kb-column-count {
      font-size: 11px;
      font-weight: 700;
      background: #e5e7eb;
      color: #6b7280;
      padding: 2px 8px;
      border-radius: 10px;
    }
    .kb-column-body {
      flex: 1;
      overflow-y: auto;
      padding: 8px 10px 12px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .kb-column-body::-webkit-scrollbar { width: 4px; }
    .kb-column-body::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 2px;
    }

    /* ─── Collapsed Column (Cancelado) ─── */
    .kb-column.collapsed {
      flex: 0 0 48px;
      max-width: 48px;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      background: #f3f4f6;
    }
    .kb-column.collapsed .kb-column-header {
      writing-mode: vertical-rl;
      text-orientation: mixed;
      transform: rotate(180deg);
      padding: 16px 8px;
      border: none;
      flex: 1;
      justify-content: center;
    }
    .kb-column.collapsed .kb-column-body { display: none; }
    .kb-column.collapsed .kb-column-count { display: none; }
    .kb-column.collapsed:hover {
      background: #ebedf0;
    }

    /* ─── Project Card ─── */
    .kb-project-card {
      background: var(--kb-card);
      border-radius: var(--kb-radius);
      border: 1px solid #e8eaed;
      box-shadow: var(--kb-shadow-card);
      transition: box-shadow var(--kb-transition), transform var(--kb-transition);
      overflow: hidden;
      animation: kb-fadeSlide 0.3s ease both;
    }
    .kb-project-card:hover {
      box-shadow: var(--kb-shadow-card-hover);
      transform: translateY(-1px);
    }
    .kb-project-card.is-leader {
      border-left: 3px solid var(--kb-accent);
    }
    .kb-project-head {
      padding: 14px 14px 12px;
      cursor: default;
    }
    .kb-project-row1 {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 8px;
    }
    .kb-project-title {
      font-size: 14px;
      font-weight: 700;
      color: #111827;
      line-height: 1.35;
      flex: 1;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .kb-badge-leader {
      font-size: 9px;
      padding: 2px 7px;
      background: linear-gradient(135deg, var(--kb-primary), var(--kb-secondary));
      color: #fff;
      border-radius: 20px;
      font-weight: 700;
      letter-spacing: 0.5px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .kb-priority-badge {
      font-size: 10px;
      padding: 2px 7px;
      border-radius: 6px;
      font-weight: 700;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .kb-priority-critica { background: #fee2e2; color: #b91c1c; }
    .kb-priority-alta { background: #fff7ed; color: #c2410c; }
    .kb-priority-media { background: #eff6ff; color: #1d4ed8; }
    .kb-priority-baja { background: #f0fdf4; color: #15803d; }

    .kb-project-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      font-size: 12px;
      color: #6b7280;
    }
    .kb-project-meta .sep {
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: #d1d5db;
      flex-shrink: 0;
    }
    .kb-avatar-sm {
      width: 20px; height: 20px;
      border-radius: 50%;
      background: var(--kb-secondary);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 8px;
      font-weight: 700;
      flex-shrink: 0;
    }

    /* Progress bar within project card */
    .kb-progress {
      margin-top: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .kb-progress-track {
      flex: 1;
      height: 4px;
      background: #e5e7eb;
      border-radius: 2px;
      overflow: hidden;
    }
    .kb-progress-fill {
      height: 100%;
      border-radius: 2px;
      transition: width 0.5s ease;
    }
    .kb-progress-fill.low { background: #f87171; }
    .kb-progress-fill.medium { background: #fbbf24; }
    .kb-progress-fill.high { background: #34d399; }
    .kb-progress-fill.complete { background: #059669; }
    .kb-progress-pct {
      font-size: 11px;
      font-weight: 700;
      color: #374151;
      min-width: 30px;
      text-align: right;
    }

    /* Actions for leaders */
    .kb-project-actions {
      display: flex;
      gap: 4px;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid #f3f4f6;
    }
    .kb-edit-btn {
      font-size: 11px;
      padding: 4px 10px;
      background: #f3f4f6;
      border: none;
      border-radius: 6px;
      color: #4b5563;
      cursor: pointer;
      font-weight: 600;
      font-family: inherit;
      transition: var(--kb-transition);
    }
    .kb-edit-btn:hover { background: #e5e7eb; color: #111827; }

    /* ─── Task Sub-cards ─── */
    .kb-tasks-list {
      padding: 0 14px 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .kb-task-card {
      background: #f9fafb;
      border: 1px solid #f0f1f3;
      border-radius: var(--kb-radius-sm);
      padding: 8px 10px;
      cursor: pointer;
      transition: var(--kb-transition);
    }
    .kb-task-card:hover { background: #f3f4f6; border-color: #e5e7eb; }
    .kb-task-card.my-task {
      border-left: 2px solid var(--kb-accent);
    }
    .kb-task-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .kb-status-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .kb-dot-pendiente { background: #fbbf24; }
    .kb-dot-en_progreso { background: #3b82f6; }
    .kb-dot-completada { background: #10b981; }
    .kb-dot-cancelada { background: #9ca3af; }
    .kb-task-title {
      font-size: 12px;
      font-weight: 500;
      color: #374151;
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .kb-task-title.done {
      text-decoration: line-through;
      color: #9ca3af;
    }
    .kb-task-status-text {
      font-size: 10px;
      font-weight: 600;
      white-space: nowrap;
    }
    /* Task expanded details - hidden by default, shown when .expanded */
    .kb-task-details {
      display: none;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px dashed #e5e7eb;
      font-size: 11px;
      color: #6b7280;
      flex-direction: column;
      gap: 6px;
    }
    .kb-task-card.expanded .kb-task-details,
    .kb-mytask-card.expanded .kb-task-details {
      display: flex;
    }
    .kb-task-detail-row {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .kb-task-detail-label {
      font-weight: 600;
      color: #9ca3af;
      min-width: 72px;
    }
    .kb-task-detail-value {
      color: #374151;
      font-weight: 500;
    }
    /* Leader inline actions on tasks */
    .kb-task-leader-actions {
      display: flex;
      gap: 4px;
      margin-top: 6px;
    }
    .kb-task-action-btn {
      font-size: 10px;
      padding: 3px 8px;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      background: #fff;
      color: #4b5563;
      cursor: pointer;
      font-weight: 600;
      font-family: inherit;
      transition: var(--kb-transition);
    }
    .kb-task-action-btn:hover { border-color: #9ca3af; background: #f9fafb; }

    /* ─── My Task Card (special columns) ─── */
    .kb-mytask-card {
      background: var(--kb-card);
      border-radius: var(--kb-radius-sm);
      border: 1px solid #e8eaed;
      box-shadow: var(--kb-shadow-card);
      padding: 10px 12px;
      cursor: pointer;
      transition: var(--kb-transition);
      animation: kb-fadeSlide 0.3s ease both;
    }
    .kb-mytask-card:hover {
      box-shadow: var(--kb-shadow-card-hover);
      transform: translateY(-1px);
    }
    .kb-mytask-card.overdue { border-left: 3px solid #ef4444; }
    .kb-mytask-card.due-soon { border-left: 3px solid #f59e0b; }
    .kb-mytask-project {
      font-size: 10px;
      font-weight: 600;
      color: #9ca3af;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .kb-mytask-title {
      font-size: 13px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 6px;
    }
    .kb-mytask-title.done {
      text-decoration: line-through;
      color: #9ca3af;
    }
    .kb-mytask-footer {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 11px;
      color: #6b7280;
    }
    .kb-deadline-badge {
      font-size: 10px;
      font-weight: 600;
      padding: 2px 6px;
      border-radius: 4px;
    }
    .kb-deadline-overdue { background: #fee2e2; color: #b91c1c; }
    .kb-deadline-today { background: #fef3c7; color: #92400e; }
    .kb-deadline-soon { background: #fff7ed; color: #c2410c; }
    .kb-deadline-normal { background: #f3f4f6; color: #6b7280; }

    /* ─── Empty State ─── */
    .kb-empty {
      text-align: center;
      padding: 32px 16px;
      color: #9ca3af;
    }
    .kb-empty-icon {
      font-size: 32px;
      margin-bottom: 8px;
      opacity: 0.5;
    }
    .kb-empty-text {
      font-size: 12px;
      font-weight: 500;
    }

    /* ─── Skeleton Loading ─── */
    .kb-skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: kb-shimmer 1.5s infinite;
      border-radius: var(--kb-radius-sm);
    }
    .kb-skeleton-card {
      height: 100px;
      margin-bottom: 8px;
      border-radius: var(--kb-radius);
    }

    /* ─── Modal ─── */
    .kb-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(4px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1400;
      animation: kb-fadeIn 0.2s ease;
      padding: 24px;
    }
    .kb-modal {
      background: #fff;
      border-radius: 16px;
      width: 100%;
      max-width: 520px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
      animation: kb-scaleIn 0.25s ease;
    }
    .kb-modal-header {
      padding: 20px 24px 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .kb-modal-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--kb-primary);
    }
    .kb-modal-body { padding: 16px 24px; }
    .kb-modal-footer {
      padding: 12px 24px 20px;
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }
    .kb-input-group {
      margin-bottom: 14px;
    }
    .kb-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 5px;
    }
    .kb-input,
    .kb-select,
    .kb-textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1.5px solid #e5e7eb;
      border-radius: var(--kb-radius-sm);
      font-size: 13px;
      font-family: inherit;
      color: #111827;
      transition: var(--kb-transition);
      background: #fff;
    }
    .kb-input:focus, .kb-select:focus, .kb-textarea:focus {
      outline: none;
      border-color: var(--kb-accent);
      box-shadow: 0 0 0 3px rgba(239,127,27,0.12);
    }
    .kb-textarea { resize: vertical; min-height: 60px; }
    .kb-select { cursor: pointer; }
    .kb-row { display: flex; gap: 10px; }
    .kb-row > * { flex: 1; }

    /* ─── Toast ─── */
    .kb-toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      color: #fff;
      z-index: 1600;
      animation: kb-slideUp 0.3s ease;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }
    .kb-toast.success { background: #059669; }
    .kb-toast.error { background: #dc2626; }

    /* ─── Animations ─── */
    @keyframes kb-fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes kb-scaleIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    @keyframes kb-fadeSlide {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes kb-shimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
    @keyframes kb-slideUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ─── Responsive ─── */
    @media (max-width: 768px) {
      .kb-topbar { padding: 0 14px; height: 50px; }
      .kb-topbar-title { font-size: 14px; }
      .kb-stats { padding: 8px 14px; gap: 16px; }
      .kb-board { padding: 10px; gap: 10px; }
      :root { --kb-column-width: 280px; }
      .kb-back-btn span.hide-mobile { display: none; }
      .kb-btn span.hide-mobile { display: none; }
    }
    @media (max-width: 480px) {
      :root { --kb-column-width: 260px; }
      .kb-topbar-actions .kb-btn-ghost { display: none; }
    }

    /* ─── Chip de área ─── */
    .kb-area-chip {
      display: inline-flex;
      align-items: center;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 99px;
      background: rgba(0, 122, 150, 0.09);
      color: #007a96;
      border: 1px solid rgba(0, 122, 150, 0.15);
      letter-spacing: 0.02em;
      white-space: nowrap;
    }
  </style>

<?php if (!$modo_embebido): ?>
</head>
<body>
<?php endif; ?>

<?php if ($modo_embebido): ?>
<style>
  /* ─── Overrides para modo embebido en dashboard ─── */
  .kb-shell--embedded {
    height: auto;
    overflow: visible;
    border-radius: 0;
    background: transparent;
  }
  .kb-shell--embedded .kb-stats {
    border-bottom: 1px solid #F0F0F0;
    background: transparent;
    padding: 0 0 14px 0;
    margin-bottom: 0;
    gap: 20px;
    flex-wrap: wrap;
  }
  .kb-shell--embedded .kb-board {
    height: 68vh;
    padding: 14px 0 0 0;
    min-height: 320px;
  }
  /* Chip de área en tarjetas de tarea */
  .kb-area-chip {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 99px;
    background: rgba(0, 122, 150, 0.09);
    color: #007a96;
    border: 1px solid rgba(0, 122, 150, 0.15);
    letter-spacing: 0.02em;
    white-space: nowrap;
  }
</style>
<?php endif; ?>

<div class="kb-shell<?= $modo_embebido ? ' kb-shell--embedded' : '' ?>">

<?php if (!$modo_embebido): ?>
  <!-- ── Top Bar ── -->
  <header class="kb-topbar">
    <a class="kb-back-btn" href="dashboard_equipo.php?id=<?php echo $empleado_id; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
      <span class="hide-mobile">Dashboard</span>
    </a>
    <div class="kb-topbar-title">Seguimiento de Proyectos</div>
    <div class="kb-topbar-actions">
      <button class="kb-btn kb-btn-ghost" onclick="openCreateProjectModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        <span class="hide-mobile">Proyecto</span>
      </button>
      <button class="kb-btn kb-btn-accent" onclick="openCreateTareaModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Nueva tarea
      </button>
    </div>
  </header>
<?php endif; ?>

  <!-- ── Stats Strip ── -->
  <div class="kb-stats" id="kb-stats">
    <div class="kb-stat">
      <strong id="stat-projects">—</strong> Proyectos
    </div>
    <div class="kb-stat">
      <div class="kb-stat-dot" style="background:#3b82f6"></div>
      <strong id="stat-in-progress">—</strong> En progreso
    </div>
    <div class="kb-stat">
      <div class="kb-stat-dot" style="background:#10b981"></div>
      <strong id="stat-completed">—</strong> Completados
    </div>
    <div class="kb-stat">
      <div class="kb-stat-dot" style="background:#fbbf24"></div>
      <strong id="stat-tasks-pending">—</strong> Tareas pendientes
    </div>
    <div class="kb-stat">
      <div class="kb-stat-dot" style="background:#ef4444"></div>
      <strong id="stat-overdue">—</strong> Vencidas
    </div>
  </div>

  <!-- ── Kanban Board ── -->
  <div class="kb-board" id="kb-board">
    <!-- Columns rendered by JS -->
  </div>
</div>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════ -->
<script>
(function() {
  'use strict';

  /* ─── Constants ─── */
  const EMPLEADO_ID = <?php echo (int)$empleado_id; ?>;
  const USER_ID = <?php echo (int)$user_id; ?>;
  const ES_LIDER_AREA = <?php echo $es_lider_area ? 'true' : 'false'; ?>;
  const AREA_LIDER_ID = <?php echo (int)$area_lider_id; ?>;
  const AREA_LIDER_NOMBRE = <?php echo json_encode($area_lider_nombre); ?>;
  const BACKEND = 'proyectos_tareas_backend.php';

  const COLUMN_CONFIG = {
    'mis_tareas':    { title: 'Mis Tareas',      color: '#EF7F1B', icon: 'user', special: true },
    'mi_area':       { title: 'Mi Area',          color: '#184656', icon: 'users', special: true },
    'planificacion': { title: 'Planificacion',    color: '#7c3aed' },
    'en_progreso':   { title: 'En Progreso',      color: '#2563eb' },
    'pausado':       { title: 'Pausado',           color: '#ea580c' },
    'completado':    { title: 'Completado',        color: '#059669' },
    'cancelado':     { title: 'Cancelado',         color: '#6b7280', collapsed: true }
  };

  const ESTADO_TAREA = {
    'pendiente':   { color: '#fbbf24', text: 'Pendiente' },
    'en_progreso': { color: '#3b82f6', text: 'En progreso' },
    'completada':  { color: '#10b981', text: 'Completada' },
    'cancelada':   { color: '#9ca3af', text: 'Cancelada' }
  };

  const PRIORIDAD_CONFIG = {
    'critica': { cls: 'kb-priority-critica', text: 'Critica' },
    'alta':    { cls: 'kb-priority-alta',    text: 'Alta' },
    'media':   { cls: 'kb-priority-media',   text: 'Media' },
    'baja':    { cls: 'kb-priority-baja',    text: 'Baja' }
  };

  /* ─── State ─── */
  let allProjects = [];
  let myTasks = [];
  let areaTasks = [];
  let teamMembers = [];

  /* ═════════════════════════════════════════════
     DATA LOADING
     ═════════════════════════════════════════════ */

  async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  async function loadAllData() {
    showSkeletons();
    try {
      const promises = [
        fetchJSON(`${BACKEND}?action=obtener_mis_proyectos&empleado_id=${EMPLEADO_ID}&usuario_id=${USER_ID}`),
        fetchJSON(`${BACKEND}?action=obtener_tareas_empleado&responsable_id=${EMPLEADO_ID}&usuario_id=${USER_ID}`)
      ];
      if (ES_LIDER_AREA && AREA_LIDER_ID > 0) {
        promises.push(fetchJSON(`${BACKEND}?action=obtener_tareas_area&area_id=${AREA_LIDER_ID}&usuario_id=${USER_ID}`));
      }

      const results = await Promise.all(promises);

      // Projects
      if (results[0].ok && results[0].proyectos) {
        allProjects = results[0].proyectos;
      }

      // My tasks
      if (results[1].ok && results[1].tareas) {
        myTasks = results[1].tareas.filter(t => t.estado !== 'cancelada');
      }

      // Area tasks
      if (results[2] && results[2].ok && results[2].tareas) {
        areaTasks = results[2].tareas;
      }

      renderBoard();
      updateStats();
    } catch (err) {
      console.error('Error loading data:', err);
      document.getElementById('kb-board').innerHTML = `
        <div style="margin:auto;text-align:center;padding:60px;color:#6b7280;">
          <div style="font-size:48px;margin-bottom:16px;">&#9888;</div>
          <p style="font-size:16px;font-weight:600;">Error al cargar datos</p>
          <p style="font-size:13px;margin-top:8px;">Verifica tu conexion e intenta de nuevo</p>
          <button class="kb-btn kb-btn-outline" onclick="location.reload()" style="margin-top:16px;">Reintentar</button>
        </div>
      `;
    }
  }

  function showSkeletons() {
    const board = document.getElementById('kb-board');
    const cols = ES_LIDER_AREA
      ? ['mis_tareas','mi_area','planificacion','en_progreso','pausado','completado','cancelado']
      : ['mis_tareas','planificacion','en_progreso','pausado','completado','cancelado'];
    board.innerHTML = cols.map(key => {
      const cfg = COLUMN_CONFIG[key];
      const collapsed = cfg.collapsed ? ' collapsed' : '';
      return `
        <div class="kb-column${collapsed}${cfg.special ? ' special' : ''}">
          <div class="kb-column-header">
            <div class="kb-column-color" style="background:${cfg.color}"></div>
            <div class="kb-column-title">${cfg.title}</div>
          </div>
          <div class="kb-column-body">
            ${cfg.collapsed ? '' : `
              <div class="kb-skeleton kb-skeleton-card"></div>
              <div class="kb-skeleton kb-skeleton-card" style="height:70px"></div>
            `}
          </div>
        </div>
      `;
    }).join('');
  }

  /* ═════════════════════════════════════════════
     RENDERING
     ═════════════════════════════════════════════ */

  function renderBoard() {
    const board = document.getElementById('kb-board');
    const cols = [];

    // 1) MIS TAREAS
    cols.push(renderSpecialColumn('mis_tareas', myTasks));

    // 2) MI ÁREA (if leader)
    if (ES_LIDER_AREA) {
      cols.push(renderSpecialColumn('mi_area', areaTasks));
    }

    // 3) Project state columns
    const projectsByState = {};
    ['planificacion','en_progreso','pausado','completado','cancelado'].forEach(s => {
      projectsByState[s] = [];
    });
    allProjects.forEach(p => {
      const state = p.estado || 'planificacion';
      if (projectsByState[state]) projectsByState[state].push(p);
    });

    ['planificacion','en_progreso','pausado','completado','cancelado'].forEach(state => {
      cols.push(renderProjectColumn(state, projectsByState[state]));
    });

    board.innerHTML = cols.join('');

    // Set up collapsed column toggle
    document.querySelectorAll('.kb-column.collapsed').forEach(col => {
      col.addEventListener('click', () => {
        col.classList.remove('collapsed');
        col.style.flex = '0 0 var(--kb-column-width)';
        col.style.maxWidth = 'var(--kb-column-width)';
        col.querySelector('.kb-column-body').style.display = 'flex';
        const count = col.querySelector('.kb-column-count');
        if (count) count.style.display = '';
      });
    });

    // Delegated event listener for edit project buttons (safe from XSS)
    board.addEventListener('click', function(e) {
      const editBtn = e.target.closest('.kb-edit-project-btn');
      if (editBtn) {
        e.stopPropagation();
        openEditProjectModal(
          parseInt(editBtn.dataset.pid),
          editBtn.dataset.titulo,
          editBtn.dataset.estado,
          editBtn.dataset.fecha,
          editBtn.dataset.prioridad
        );
      }
    });
  }

  function renderSpecialColumn(key, tasks) {
    const cfg = COLUMN_CONFIG[key];
    const activeTasks = tasks.filter(t => t.estado !== 'cancelada');
    const cardsHTML = activeTasks.length > 0
      ? activeTasks.map((t, i) => renderMyTaskCard(t, i)).join('')
      : `<div class="kb-empty"><div class="kb-empty-icon">&#9745;</div><div class="kb-empty-text">Sin tareas${key === 'mi_area' ? ' en tu area' : ' asignadas'}</div></div>`;

    return `
      <div class="kb-column special">
        <div class="kb-column-header">
          <div class="kb-column-color" style="background:${cfg.color}"></div>
          <div class="kb-column-title">${cfg.title}</div>
          <span class="kb-column-count">${activeTasks.length}</span>
        </div>
        <div class="kb-column-body">${cardsHTML}</div>
      </div>
    `;
  }

  function renderProjectColumn(state, projects) {
    const cfg = COLUMN_CONFIG[state];
    const collapsed = cfg.collapsed && projects.length === 0 ? ' collapsed' : (cfg.collapsed ? '' : '');
    const cardsHTML = projects.length > 0
      ? projects.map((p, i) => renderProjectCard(p, i)).join('')
      : `<div class="kb-empty"><div class="kb-empty-icon">&#128194;</div><div class="kb-empty-text">Sin proyectos</div></div>`;

    return `
      <div class="kb-column${collapsed ? ' collapsed' : ''}" data-state="${state}">
        <div class="kb-column-header">
          <div class="kb-column-color" style="background:${cfg.color}"></div>
          <div class="kb-column-title">${cfg.title}</div>
          <span class="kb-column-count">${projects.length}</span>
        </div>
        <div class="kb-column-body">${cardsHTML}</div>
      </div>
    `;
  }

  function renderProjectCard(proy, index) {
    const esLider = parseInt(proy.lider_id) === EMPLEADO_ID;
    const porcentaje = parseInt(proy.porcentaje_completado) || 0;
    let progressClass = 'low';
    if (porcentaje >= 100) progressClass = 'complete';
    else if (porcentaje >= 75) progressClass = 'high';
    else if (porcentaje >= 50) progressClass = 'medium';

    const prioridadCfg = PRIORIDAD_CONFIG[proy.prioridad] || PRIORIDAD_CONFIG['media'];
    const allTasks = proy.todas_tareas || proy.mis_tareas || [];
    const activeTasks = allTasks.filter(t => t.estado !== 'cancelada');
    const myCount = activeTasks.filter(t => t.es_mi_tarea).length;
    const delay = index * 0.05;

    const tasksHTML = activeTasks.length > 0
      ? activeTasks.map(t => renderTaskSubCard(t, esLider, proy.id)).join('')
      : `<div style="padding:8px;text-align:center;font-size:11px;color:#9ca3af;">Sin tareas</div>`;

    return `
      <div class="kb-project-card ${esLider ? 'is-leader' : ''}" data-project-id="${proy.id}" style="animation-delay:${delay}s">
        <div class="kb-project-head">
          <div class="kb-project-row1">
            <div style="flex:1;min-width:0;">
              <div class="kb-project-title">${esc(proy.titulo)}</div>
            </div>
            <div style="display:flex;gap:4px;align-items:center;flex-shrink:0;">
              ${esLider ? '<span class="kb-badge-leader">LIDER</span>' : ''}
              <span class="kb-priority-badge ${prioridadCfg.cls}">${prioridadCfg.text}</span>
            </div>
          </div>
          <div class="kb-project-meta">
            <span style="display:inline-flex;align-items:center;gap:4px;">
              <span class="kb-avatar-sm">${esc(getInitials(proy.lider_nombre))}</span>
              ${esc(proy.lider_nombre || 'Sin asignar')}
            </span>
            <span class="sep"></span>
            <span>${formatDate(proy.fecha_fin_estimada)}</span>
            <span class="sep"></span>
            <span>${proy.total_tareas || 0} tareas</span>
            ${myCount > 0 ? `<span style="background:var(--kb-accent);color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:700;">${myCount} mia${myCount > 1 ? 's' : ''}</span>` : ''}
          </div>
          <div class="kb-progress">
            <div class="kb-progress-track">
              <div class="kb-progress-fill ${progressClass}" style="width:${porcentaje}%"></div>
            </div>
            <span class="kb-progress-pct">${porcentaje}%</span>
          </div>
          ${esLider ? `
            <div class="kb-project-actions">
              <button class="kb-edit-btn kb-edit-project-btn"
                data-pid="${proy.id}"
                data-titulo="${escapeAttr(proy.titulo)}"
                data-estado="${escapeAttr(proy.estado)}"
                data-fecha="${escapeAttr(proy.fecha_fin_estimada || '')}"
                data-prioridad="${escapeAttr(proy.prioridad || 'media')}">Editar proyecto</button>
            </div>
          ` : ''}
        </div>
        <div class="kb-tasks-list">${tasksHTML}</div>
      </div>
    `;
  }

  function renderTaskSubCard(tarea, esLider, proyectoId) {
    const estado = ESTADO_TAREA[tarea.estado] || ESTADO_TAREA['pendiente'];
    const esMiTarea = tarea.es_mi_tarea;
    const isDone = tarea.estado === 'completada';
    const deadlineInfo = getDeadlineInfo(tarea);

    const areaLabel = tarea.responsable_area && tarea.responsable_area !== '—' ? tarea.responsable_area : null;

    return `
      <div class="kb-task-card ${esMiTarea ? 'my-task' : ''}" data-task-id="${tarea.id}" onclick="toggleTaskExpand(this)">
        <div class="kb-task-row">
          <span class="kb-status-dot kb-dot-${tarea.estado}"></span>
          <span class="kb-task-title ${isDone ? 'done' : ''}">${esc(tarea.titulo)}</span>
          ${areaLabel ? `<span class="kb-area-chip">${esc(areaLabel)}</span>` : ''}
          <span class="kb-task-status-text" style="color:${estado.color}">${estado.text}</span>
        </div>
        <div class="kb-task-details">
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Responsable</span>
            <span class="kb-task-detail-value">
              ${esMiTarea ? 'Tú' : esc(tarea.responsable_nombre || 'Sin asignar')}
            </span>
          </div>
          ${areaLabel ? `
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Área</span>
            <span class="kb-task-detail-value"><span class="kb-area-chip">${esc(areaLabel)}</span></span>
          </div>` : ''}
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Fecha límite</span>
            <span class="kb-task-detail-value">
              ${deadlineInfo.html}
            </span>
          </div>
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Prioridad</span>
            <span class="kb-task-detail-value">${capitalize(tarea.prioridad || 'media')}</span>
          </div>
          ${esLider ? `
            <div class="kb-task-leader-actions">
              <button class="kb-task-action-btn" data-action="status" data-tid="${tarea.id}" data-estado="${escapeAttr(tarea.estado)}" data-pid="${proyectoId}" onclick="event.stopPropagation(); changeTaskStatus(+this.dataset.tid, this.dataset.estado, +this.dataset.pid)">Cambiar estado</button>
              <button class="kb-task-action-btn" data-action="reassign" data-tid="${tarea.id}" data-pid="${proyectoId}" onclick="event.stopPropagation(); changeTaskResponsable(+this.dataset.tid, +this.dataset.pid)">Reasignar</button>
              <button class="kb-task-action-btn" data-action="deadline" data-tid="${tarea.id}" data-deadline="${escapeAttr(tarea.deadline||'')}" data-pid="${proyectoId}" onclick="event.stopPropagation(); changeTaskDeadline(+this.dataset.tid, this.dataset.deadline, +this.dataset.pid)">Fecha</button>
            </div>
          ` : ''}
        </div>
      </div>
    `;
  }

  function renderMyTaskCard(tarea, index) {
    const estado = ESTADO_TAREA[tarea.estado] || ESTADO_TAREA['pendiente'];
    const isDone = tarea.estado === 'completada';
    const deadlineInfo = getDeadlineInfo(tarea);
    const delay = index * 0.04;
    let urgencyClass = '';
    if (deadlineInfo.type === 'overdue') urgencyClass = 'overdue';
    else if (deadlineInfo.type === 'today' || deadlineInfo.type === 'soon') urgencyClass = 'due-soon';

    const areaMyTask = tarea.responsable_area && tarea.responsable_area !== '—' ? tarea.responsable_area : null;

    return `
      <div class="kb-mytask-card ${urgencyClass}" style="animation-delay:${delay}s" onclick="toggleTaskExpand(this)" data-task-id="${tarea.id}">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:2px;">
          <div class="kb-mytask-project">${esc(tarea.proyecto_titulo || 'Sin proyecto')}</div>
          ${areaMyTask ? `<span class="kb-area-chip">${esc(areaMyTask)}</span>` : ''}
        </div>
        <div class="kb-mytask-title ${isDone ? 'done' : ''}">${esc(tarea.titulo)}</div>
        <div class="kb-mytask-footer">
          <span class="kb-status-dot kb-dot-${tarea.estado}"></span>
          <span style="font-weight:600;color:${estado.color}">${estado.text}</span>
          <span style="color:#d1d5db">|</span>
          ${deadlineInfo.html}
        </div>
        <div class="kb-task-details">
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Responsable</span>
            <span class="kb-task-detail-value">${esc(tarea.responsable_nombre || 'Sin asignar')}</span>
          </div>
          ${areaMyTask ? `
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Área</span>
            <span class="kb-task-detail-value"><span class="kb-area-chip">${esc(areaMyTask)}</span></span>
          </div>` : ''}
          <div class="kb-task-detail-row">
            <span class="kb-task-detail-label">Prioridad</span>
            <span class="kb-task-detail-value">${capitalize(tarea.prioridad || 'media')}</span>
          </div>
        </div>
      </div>
    `;
  }

  /* ═════════════════════════════════════════════
     STATS
     ═════════════════════════════════════════════ */

  function updateStats() {
    document.getElementById('stat-projects').textContent = allProjects.length;
    document.getElementById('stat-in-progress').textContent =
      allProjects.filter(p => p.estado === 'en_progreso').length;
    document.getElementById('stat-completed').textContent =
      allProjects.filter(p => p.estado === 'completado').length;
    document.getElementById('stat-tasks-pending').textContent =
      myTasks.filter(t => t.estado === 'pendiente').length;
    document.getElementById('stat-overdue').textContent =
      myTasks.filter(t => {
        if (!t.deadline || t.estado === 'completada' || t.estado === 'cancelada') return false;
        return new Date(t.deadline) < new Date(new Date().toDateString());
      }).length;
  }

  /* ═════════════════════════════════════════════
     INTERACTIONS
     ═════════════════════════════════════════════ */

  window.toggleTaskExpand = function(el) {
    el.classList.toggle('expanded');
  };

  window.changeTaskStatus = async function(tareaId, currentStatus, proyectoId) {
    const states = ['pendiente','en_progreso','completada','cancelada'];
    const labels = ['Pendiente','En progreso','Completada','Cancelada'];
    const currentIdx = states.indexOf(currentStatus);

    const html = states.map((s, i) => `
      <button class="kb-btn kb-btn-outline" style="width:100%;justify-content:center;margin-bottom:6px;
        ${i === currentIdx ? 'border-color:var(--kb-accent);color:var(--kb-accent);font-weight:700;' : ''}"
        onclick="doChangeTaskStatus(${tareaId}, '${s}')">${labels[i]}</button>
    `).join('');

    openQuickModal('Cambiar estado', html);
  };

  window.doChangeTaskStatus = async function(tareaId, nuevoEstado) {
    closeQuickModal();
    try {
      const fd = new FormData();
      fd.append('action', 'actualizar_tarea_campo');
      fd.append('usuario_id', USER_ID);
      fd.append('empleado_id', EMPLEADO_ID);
      fd.append('tarea_id', tareaId);
      fd.append('campo', 'estado');
      fd.append('valor', nuevoEstado);
      const res = await fetch(BACKEND, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok || data.success) {
        showToast('Estado actualizado');
        await loadAllData();
      } else {
        showToast(data.message || 'Error al actualizar', 'error');
      }
    } catch (e) {
      showToast('Error de conexion', 'error');
    }
  };

  window.changeTaskResponsable = async function(tareaId, proyectoId) {
    if (teamMembers.length === 0) {
      try {
        const data = await fetchJSON(`${BACKEND}?action=obtener_miembros_equipo&usuario_id=${USER_ID}`);
        if (data.ok) teamMembers = data.miembros || [];
      } catch(e) {}
    }
    const options = teamMembers.map(m =>
      `<button class="kb-btn kb-btn-outline" style="width:100%;justify-content:flex-start;margin-bottom:4px;"
        onclick="doChangeTaskResponsable(${tareaId}, ${m.id})">${esc(m.nombre_persona)}</button>`
    ).join('');
    openQuickModal('Reasignar tarea', options || '<p style="color:#6b7280">Sin miembros disponibles</p>');
  };

  window.doChangeTaskResponsable = async function(tareaId, responsableId) {
    closeQuickModal();
    try {
      const fd = new FormData();
      fd.append('action', 'actualizar_tarea_campo');
      fd.append('usuario_id', USER_ID);
      fd.append('empleado_id', EMPLEADO_ID);
      fd.append('tarea_id', tareaId);
      fd.append('campo', 'responsable_id');
      fd.append('valor', responsableId);
      const res = await fetch(BACKEND, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok || data.success) {
        showToast('Responsable actualizado');
        await loadAllData();
      } else {
        showToast(data.message || 'Error al actualizar', 'error');
      }
    } catch(e) {
      showToast('Error de conexion', 'error');
    }
  };

  window.changeTaskDeadline = function(tareaId, currentDeadline, proyectoId) {
    const html = `
      <div class="kb-input-group">
        <label class="kb-label">Nueva fecha limite</label>
        <input type="date" id="qm-deadline" class="kb-input" value="${currentDeadline}">
      </div>
      <button class="kb-btn kb-btn-accent" style="width:100%;justify-content:center;"
        onclick="doChangeTaskDeadline(${tareaId})">Guardar</button>
    `;
    openQuickModal('Cambiar fecha limite', html);
  };

  window.doChangeTaskDeadline = async function(tareaId) {
    const val = document.getElementById('qm-deadline')?.value || '';
    closeQuickModal();
    try {
      const fd = new FormData();
      fd.append('action', 'actualizar_tarea_campo');
      fd.append('usuario_id', USER_ID);
      fd.append('empleado_id', EMPLEADO_ID);
      fd.append('tarea_id', tareaId);
      fd.append('campo', 'deadline');
      fd.append('valor', val);
      const res = await fetch(BACKEND, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok || data.success) {
        showToast('Fecha actualizada');
        await loadAllData();
      } else {
        showToast(data.message || 'Error al actualizar', 'error');
      }
    } catch(e) {
      showToast('Error de conexion', 'error');
    }
  };

  /* ═════════════════════════════════════════════
     QUICK MODAL (for inline task actions)
     ═════════════════════════════════════════════ */

  function openQuickModal(title, bodyHTML) {
    closeQuickModal();
    const overlay = document.createElement('div');
    overlay.className = 'kb-modal-overlay';
    overlay.id = 'quick-modal';
    overlay.innerHTML = `
      <div class="kb-modal" style="max-width:360px">
        <div class="kb-modal-header">
          <div class="kb-modal-title">${title}</div>
        </div>
        <div class="kb-modal-body">${bodyHTML}</div>
        <div class="kb-modal-footer">
          <button class="kb-btn kb-btn-outline" onclick="closeQuickModal()">Cancelar</button>
        </div>
      </div>
    `;
    overlay.addEventListener('click', e => { if (e.target === overlay) closeQuickModal(); });
    document.body.appendChild(overlay);
  }

  window.closeQuickModal = function() {
    document.getElementById('quick-modal')?.remove();
  };

  /* ═════════════════════════════════════════════
     MODAL: EDIT PROJECT (leaders only)
     ═════════════════════════════════════════════ */

  window.openEditProjectModal = function(id, titulo, estado, fechaFin, prioridad) {
    if (document.getElementById('edit-project-modal')) return;

    const overlay = document.createElement('div');
    overlay.className = 'kb-modal-overlay';
    overlay.id = 'edit-project-modal';
    overlay.innerHTML = `
      <div class="kb-modal">
        <div class="kb-modal-header">
          <div class="kb-modal-title">Editar proyecto</div>
        </div>
        <div class="kb-modal-body">
          <div class="kb-input-group">
            <label class="kb-label">Nombre del proyecto</label>
            <input type="text" id="ep-titulo" class="kb-input" value="${escapeAttr(titulo)}">
          </div>
          <div class="kb-row">
            <div class="kb-input-group">
              <label class="kb-label">Estado</label>
              <select id="ep-estado" class="kb-select">
                <option value="planificacion" ${estado==='planificacion'?'selected':''}>Planificacion</option>
                <option value="en_progreso" ${estado==='en_progreso'?'selected':''}>En progreso</option>
                <option value="pausado" ${estado==='pausado'?'selected':''}>Pausado</option>
                <option value="completado" ${estado==='completado'?'selected':''}>Completado</option>
                <option value="cancelado" ${estado==='cancelado'?'selected':''}>Cancelado</option>
              </select>
            </div>
            <div class="kb-input-group">
              <label class="kb-label">Prioridad</label>
              <select id="ep-prioridad" class="kb-select">
                <option value="baja" ${prioridad==='baja'?'selected':''}>Baja</option>
                <option value="media" ${prioridad==='media'?'selected':''}>Media</option>
                <option value="alta" ${prioridad==='alta'?'selected':''}>Alta</option>
                <option value="critica" ${prioridad==='critica'?'selected':''}>Critica</option>
              </select>
            </div>
          </div>
          <div class="kb-input-group">
            <label class="kb-label">Fecha limite</label>
            <input type="date" id="ep-fecha" class="kb-input" value="${fechaFin}">
          </div>
        </div>
        <div class="kb-modal-footer">
          <button class="kb-btn kb-btn-outline" onclick="closeEditProjectModal()">Cancelar</button>
          <button class="kb-btn kb-btn-accent" id="ep-save-btn" onclick="saveEditProject(${id})">Guardar</button>
        </div>
      </div>
    `;
    overlay.addEventListener('click', e => { if (e.target === overlay) closeEditProjectModal(); });
    document.body.appendChild(overlay);
    setTimeout(() => document.getElementById('ep-titulo')?.focus(), 100);
  };

  window.closeEditProjectModal = function() {
    const m = document.getElementById('edit-project-modal');
    if (m) { m.style.opacity = '0'; setTimeout(() => m.remove(), 200); }
  };

  window.saveEditProject = async function(id) {
    const titulo = document.getElementById('ep-titulo').value.trim();
    const estado = document.getElementById('ep-estado').value;
    const prioridad = document.getElementById('ep-prioridad').value;
    const fecha = document.getElementById('ep-fecha').value;
    const btn = document.getElementById('ep-save-btn');

    if (!titulo) { showToast('El nombre es obligatorio', 'error'); return; }
    btn.disabled = true; btn.textContent = 'Guardando...';

    try {
      const fd = new FormData();
      fd.append('action', 'actualizar_proyecto');
      fd.append('usuario_id', USER_ID);
      fd.append('proyecto_id', id);
      fd.append('titulo', titulo);
      fd.append('descripcion', '');
      fd.append('lider_id', EMPLEADO_ID);
      fd.append('estado', estado);
      fd.append('prioridad', prioridad);
      fd.append('fecha_fin_estimada', fecha || '');
      const res = await fetch(BACKEND, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success || data.ok) {
        closeEditProjectModal();
        showToast('Proyecto actualizado');
        await loadAllData();
      } else {
        showToast(data.message || 'Error al actualizar', 'error');
        btn.disabled = false; btn.textContent = 'Guardar';
      }
    } catch(e) {
      showToast('Error de conexion', 'error');
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  };

  /* ═════════════════════════════════════════════
     MODAL: CREATE PROJECT
     ═════════════════════════════════════════════ */

  window.openCreateProjectModal = function() {
    if (document.getElementById('create-project-modal')) return;
    const overlay = document.createElement('div');
    overlay.className = 'kb-modal-overlay';
    overlay.id = 'create-project-modal';
    overlay.innerHTML = `
      <div class="kb-modal">
        <div class="kb-modal-header">
          <div class="kb-modal-title">Nuevo proyecto</div>
        </div>
        <div class="kb-modal-body">
          <div class="kb-input-group">
            <label class="kb-label">Titulo del proyecto</label>
            <input type="text" id="np-titulo" class="kb-input" placeholder="Nombre del proyecto">
          </div>
          <div class="kb-input-group">
            <label class="kb-label">Descripcion (opcional)</label>
            <textarea id="np-desc" class="kb-textarea" placeholder="Descripcion del proyecto"></textarea>
          </div>
          <div class="kb-row">
            <div class="kb-input-group">
              <label class="kb-label">Fecha inicio</label>
              <input type="date" id="np-inicio" class="kb-input">
            </div>
            <div class="kb-input-group">
              <label class="kb-label">Fecha limite</label>
              <input type="date" id="np-fin" class="kb-input">
            </div>
          </div>
          <div class="kb-input-group">
            <label class="kb-label">Prioridad</label>
            <select id="np-prioridad" class="kb-select">
              <option value="media">Media</option>
              <option value="baja">Baja</option>
              <option value="alta">Alta</option>
              <option value="critica">Critica</option>
            </select>
          </div>
          <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Seras el lider de este proyecto</p>
        </div>
        <div class="kb-modal-footer">
          <button class="kb-btn kb-btn-outline" onclick="closeCreateProjectModal()">Cancelar</button>
          <button class="kb-btn kb-btn-accent" id="np-save-btn" onclick="saveNewProject()">Crear proyecto</button>
        </div>
      </div>
    `;
    overlay.addEventListener('click', e => { if (e.target === overlay) closeCreateProjectModal(); });
    document.body.appendChild(overlay);
    setTimeout(() => document.getElementById('np-titulo')?.focus(), 100);
  };

  window.closeCreateProjectModal = function() {
    const m = document.getElementById('create-project-modal');
    if (m) { m.style.opacity = '0'; setTimeout(() => m.remove(), 200); }
  };

  window.saveNewProject = async function() {
    const titulo = document.getElementById('np-titulo').value.trim();
    const desc = document.getElementById('np-desc').value.trim();
    const inicio = document.getElementById('np-inicio').value;
    const fin = document.getElementById('np-fin').value;
    const prioridad = document.getElementById('np-prioridad').value;
    const btn = document.getElementById('np-save-btn');

    if (!titulo) { showToast('El titulo es obligatorio', 'error'); return; }
    btn.disabled = true; btn.textContent = 'Creando...';

    try {
      const fd = new FormData();
      fd.append('action', 'crear_proyecto');
      fd.append('usuario_id', USER_ID);
      fd.append('titulo', titulo);
      fd.append('descripcion', desc);
      fd.append('lider_id', EMPLEADO_ID);
      fd.append('fecha_inicio', inicio || '');
      fd.append('fecha_fin_estimada', fin || '');
      fd.append('prioridad', prioridad);
      const res = await fetch(BACKEND, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok || data.success) {
        closeCreateProjectModal();
        showToast('Proyecto creado');
        await loadAllData();
      } else {
        showToast(data.message || data.error || 'Error', 'error');
        btn.disabled = false; btn.textContent = 'Crear proyecto';
      }
    } catch(e) {
      showToast('Error de conexion', 'error');
      btn.disabled = false; btn.textContent = 'Crear proyecto';
    }
  };

  /* ═════════════════════════════════════════════
     MODAL: CREATE TASK
     ═════════════════════════════════════════════ */

  window.openCreateTareaModal = async function() {
    if (document.getElementById('create-tarea-modal')) return;
    const overlay = document.createElement('div');
    overlay.className = 'kb-modal-overlay';
    overlay.id = 'create-tarea-modal';
    overlay.innerHTML = `
      <div class="kb-modal">
        <div class="kb-modal-header">
          <div class="kb-modal-title">Nueva tarea</div>
        </div>
        <div class="kb-modal-body">
          <div class="kb-input-group">
            <label class="kb-label">Proyecto</label>
            <select id="ct-proyecto" class="kb-select">
              <option value="">Cargando proyectos...</option>
            </select>
          </div>
          <div id="ct-new-project-form" style="display:none;background:#f9fafb;padding:12px;border-radius:10px;margin-bottom:14px;border:1px dashed #d1d5db;">
            <label class="kb-label">Titulo del nuevo proyecto</label>
            <input type="text" id="ct-np-titulo" class="kb-input" placeholder="Nombre del proyecto" style="margin-bottom:8px;">
            <div class="kb-row">
              <div><label class="kb-label">Fecha inicio</label><input type="date" id="ct-np-inicio" class="kb-input"></div>
              <div><label class="kb-label">Fecha fin</label><input type="date" id="ct-np-fin" class="kb-input"></div>
            </div>
            <p style="font-size:10px;color:#9ca3af;margin-top:6px;">Seras el lider de este proyecto</p>
          </div>
          <div class="kb-input-group">
            <label class="kb-label">Titulo de la tarea</label>
            <input type="text" id="ct-titulo" class="kb-input" placeholder="Titulo de la tarea">
          </div>
          <div class="kb-input-group">
            <label class="kb-label">Descripcion (opcional)</label>
            <textarea id="ct-desc" class="kb-textarea" placeholder="Detalles adicionales"></textarea>
          </div>
          <div class="kb-input-group">
            <label class="kb-label">Responsable</label>
            <select id="ct-responsable" class="kb-select">
              <option value="">Cargando...</option>
            </select>
          </div>
          <div class="kb-row">
            <div class="kb-input-group">
              <label class="kb-label">Fecha limite</label>
              <input type="date" id="ct-deadline" class="kb-input">
            </div>
            <div class="kb-input-group">
              <label class="kb-label">Prioridad</label>
              <select id="ct-prioridad" class="kb-select">
                <option value="media">Media</option>
                <option value="baja">Baja</option>
                <option value="alta">Alta</option>
                <option value="critica">Critica</option>
              </select>
            </div>
          </div>
        </div>
        <div class="kb-modal-footer">
          <button class="kb-btn kb-btn-outline" onclick="closeCreateTareaModal()">Cancelar</button>
          <button class="kb-btn kb-btn-accent" id="ct-save-btn" onclick="saveNewTask()">Crear tarea</button>
        </div>
      </div>
    `;
    overlay.addEventListener('click', e => { if (e.target === overlay) closeCreateTareaModal(); });
    document.body.appendChild(overlay);

    // Load projects for selector
    try {
      const data = await fetchJSON(`${BACKEND}?action=obtener_proyectos_activos&usuario_id=${USER_ID}`);
      const sel = document.getElementById('ct-proyecto');
      let optionsHTML = '<option value="">-- Selecciona un proyecto --</option>';
      optionsHTML += '<option value="nuevo">+ Crear proyecto nuevo</option>';
      if (data.ok && data.proyectos) {
        data.proyectos.forEach(p => {
          if (parseInt(p.lider_id) === EMPLEADO_ID) {
            optionsHTML += `<option value="${p.id}">${esc(p.titulo)}</option>`;
          }
        });
      }
      sel.innerHTML = optionsHTML;
      sel.addEventListener('change', function() {
        const form = document.getElementById('ct-new-project-form');
        form.style.display = this.value === 'nuevo' ? 'block' : 'none';
      });
    } catch(e) {}

    // Load team members
    try {
      const data = await fetchJSON(`${BACKEND}?action=obtener_miembros_equipo&usuario_id=${USER_ID}`);
      const sel = document.getElementById('ct-responsable');
      let membersHTML = '<option value="">Sin asignar</option>';
      membersHTML += `<option value="${EMPLEADO_ID}">Yo mismo</option>`;
      if (data.ok && data.miembros) {
        data.miembros.forEach(m => {
          if (parseInt(m.id) !== EMPLEADO_ID) {
            membersHTML += `<option value="${m.id}">${esc(m.nombre_persona)}</option>`;
          }
        });
      }
      sel.innerHTML = membersHTML;
    } catch(e) {}

    setTimeout(() => document.getElementById('ct-titulo')?.focus(), 200);
  };

  window.closeCreateTareaModal = function() {
    const m = document.getElementById('create-tarea-modal');
    if (m) { m.style.opacity = '0'; setTimeout(() => m.remove(), 200); }
  };

  window.saveNewTask = async function() {
    const titulo = document.getElementById('ct-titulo').value.trim();
    const desc = document.getElementById('ct-desc').value.trim();
    const deadline = document.getElementById('ct-deadline').value;
    const prioridad = document.getElementById('ct-prioridad').value;
    const responsableId = document.getElementById('ct-responsable').value;
    let proyectoId = document.getElementById('ct-proyecto').value;
    const btn = document.getElementById('ct-save-btn');

    if (!titulo) { showToast('El titulo es obligatorio', 'error'); return; }

    btn.disabled = true; btn.textContent = 'Guardando...';

    // Create new project first if needed
    if (proyectoId === 'nuevo') {
      const npTitulo = document.getElementById('ct-np-titulo').value.trim();
      if (!npTitulo) { showToast('Titulo del proyecto obligatorio', 'error'); btn.disabled = false; btn.textContent = 'Crear tarea'; return; }

      try {
        const fd = new FormData();
        fd.append('action', 'crear_proyecto');
        fd.append('usuario_id', USER_ID);
        fd.append('titulo', npTitulo);
        fd.append('descripcion', '');
        fd.append('lider_id', EMPLEADO_ID);
        fd.append('fecha_inicio', document.getElementById('ct-np-inicio').value || '');
        fd.append('fecha_fin_estimada', document.getElementById('ct-np-fin').value || '');
        fd.append('prioridad', 'media');
        const res = await fetch(BACKEND, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok && !data.success) {
          showToast('Error al crear proyecto', 'error');
          btn.disabled = false; btn.textContent = 'Crear tarea'; return;
        }
        proyectoId = data.proyecto_id;
      } catch(e) {
        showToast('Error de conexion', 'error');
        btn.disabled = false; btn.textContent = 'Crear tarea'; return;
      }
    }

    if (!proyectoId || proyectoId === 'nuevo') {
      showToast('Selecciona o crea un proyecto', 'error');
      btn.disabled = false; btn.textContent = 'Crear tarea'; return;
    }

    try {
      const fd = new FormData();
      fd.append('action', 'crear_tarea');
      fd.append('usuario_id', USER_ID);
      fd.append('proyecto_id', proyectoId);
      fd.append('titulo', titulo);
      fd.append('descripcion', desc);
      fd.append('responsable_id', responsableId || EMPLEADO_ID);
      fd.append('deadline', deadline || '');
      fd.append('prioridad', prioridad);
      const res = await fetch(BACKEND, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok || data.success) {
        closeCreateTareaModal();
        showToast('Tarea creada');
        await loadAllData();
      } else {
        showToast(data.message || data.error || 'Error', 'error');
        btn.disabled = false; btn.textContent = 'Crear tarea';
      }
    } catch(e) {
      showToast('Error de conexion', 'error');
      btn.disabled = false; btn.textContent = 'Crear tarea';
    }
  };

  /* ═════════════════════════════════════════════
     UTILITIES
     ═════════════════════════════════════════════ */

  function esc(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }

  function escapeAttr(text) {
    if (!text) return '';
    return text.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/).filter(p => p.length > 0);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function formatDate(dateStr) {
    if (!dateStr) return 'Sin fecha';
    // Parse as local date to avoid UTC timezone mismatch
    const parts = dateStr.split('-');
    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    const today = new Date();
    today.setHours(0,0,0,0);
    const diff = Math.ceil((d - today) / (1000 * 60 * 60 * 24));
    if (diff === 0) return 'Hoy';
    if (diff === 1) return 'Manana';
    if (diff === -1) return 'Ayer';
    if (diff > 0 && diff <= 7) return `En ${diff} dias`;
    if (diff < 0 && diff >= -7) return `Hace ${Math.abs(diff)} dias`;
    return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }

  function getDeadlineInfo(tarea) {
    if (!tarea.deadline) return { type: 'none', html: '<span style="color:#9ca3af">Sin fecha</span>' };
    const parts = tarea.deadline.split('-');
    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    const today = new Date();
    today.setHours(0,0,0,0);
    const diff = Math.ceil((d - today) / (1000 * 60 * 60 * 24));
    const text = formatDate(tarea.deadline);

    if (tarea.estado === 'completada') {
      return { type: 'done', html: `<span class="kb-deadline-badge kb-deadline-normal">${text}</span>` };
    }
    if (diff < 0) {
      return { type: 'overdue', html: `<span class="kb-deadline-badge kb-deadline-overdue">${text}</span>` };
    }
    if (diff === 0) {
      return { type: 'today', html: `<span class="kb-deadline-badge kb-deadline-today">${text}</span>` };
    }
    if (diff <= 3) {
      return { type: 'soon', html: `<span class="kb-deadline-badge kb-deadline-soon">${text}</span>` };
    }
    return { type: 'normal', html: `<span class="kb-deadline-badge kb-deadline-normal">${text}</span>` };
  }

  function showToast(msg, type = 'success') {
    document.querySelectorAll('.kb-toast').forEach(t => t.remove());
    const toast = document.createElement('div');
    toast.className = `kb-toast ${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(12px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  /* ─── Init ─── */
  loadAllData();

})();
</script>
<?php if (!$modo_embebido): ?>
</body>
</html>
<?php endif; ?>
