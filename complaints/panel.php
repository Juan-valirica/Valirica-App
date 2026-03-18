<?php
/**
 * complaints/panel.php — Panel visual del responsable del Canal de Denuncias
 * Standalone, con sidebar + tabla central + detalle lateral slide-in.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../mailer/Mailer.php';

date_default_timezone_set('Europe/Madrid');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$company_id = $user_id;

// Verificar acceso y obtener config
$config = get_company_config($conn, $company_id);

if (!$config || !$config['is_active']) {
    http_response_code(403);
    echo '<p style="font-family:Georgia,serif;padding:40px;">Canal no activo. <a href="admin-config.php">Configurar canal →</a></p>';
    exit;
}

$is_responsible = ((int)$config['responsible_user_id'] === $user_id);
$is_admin       = ($user_id === $company_id);

if (!$is_responsible && !$is_admin) {
    http_response_code(403);
    echo '<p style="font-family:Georgia,serif;padding:40px;">Acceso denegado.</p>';
    exit;
}

// ─── AJAX ────────────────────────────────────────────────────────────────────
// Las acciones se delegan a manage.php para evitar duplicar lógica
// Este panel usa fetch() → manage.php
$csrf = getCsrfToken();

// ─── Datos para el panel ──────────────────────────────────────────────────────
// Conteos por estado
$counts = ['recibida' => 0, 'en_tramite' => 0, 'resuelta' => 0, 'archivada' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) AS n FROM complaints WHERE company_id = ? GROUP BY status");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res_c = stmt_get_result($stmt);
$stmt->close();
while ($row = $res_c->fetch_assoc()) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['n'];
    }
}
$total = array_sum($counts);

// Denuncias próximas a vencer (≤3 días, no resueltas)
$stmt = $conn->prepare("
    SELECT COUNT(*) AS n FROM complaints
    WHERE company_id = ? AND status NOT IN ('resuelta','archivada')
    AND resolution_deadline IS NOT NULL
    AND resolution_deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY)
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res_urg = stmt_get_result($stmt);
$stmt->close();
$urgentes = (int)($res_urg->fetch_assoc()['n'] ?? 0);

// Lista filtrada
$filter_status   = trim($_GET['status'] ?? '');
$filter_type     = trim($_GET['type'] ?? '');
$filter_priority = trim($_GET['priority'] ?? '');

$where = ['c.company_id = ?'];
$types_b = 'i';
$vals_b  = [$company_id];

if ($filter_status)   { $where[] = 'c.status = ?';   $types_b .= 's'; $vals_b[] = $filter_status; }
if ($filter_type)     { $where[] = 'c.type = ?';     $types_b .= 's'; $vals_b[] = $filter_type; }
if ($filter_priority) { $where[] = 'c.priority = ?'; $types_b .= 's'; $vals_b[] = $filter_priority; }

$sql = "SELECT c.id, c.reference_code, c.type, c.status, c.priority, c.is_anonymous,
               c.created_at, c.resolution_deadline, c.receipt_deadline
        FROM complaints c
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.resolution_deadline ASC, c.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types_b, ...$vals_b);
$stmt->execute();
$res_l = stmt_get_result($stmt);
$stmt->close();

$complaints = [];
while ($r = $res_l->fetch_assoc()) $complaints[] = $r;

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'app.valirica.com');

// Mapa de tipos de denuncia para el filtro lateral (todos los tipos posibles)
$all_types = [
    'acoso_laboral'            => 'Acoso laboral',
    'acoso_sexual'             => 'Acoso sexual',
    'fraude'                   => 'Fraude',
    'corrupcion'               => 'Corrupción',
    'discriminacion'           => 'Discriminación',
    'incumplimiento_normativo' => 'Incumplimiento normativo',
    'conflicto_interes'        => 'Conflicto de interés',
    'otro'                     => 'Otro',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canal de Denuncias — <?= htmlspecialchars($config['company_name']) ?></title>
    <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
    <style>
        :root {
            --c-primary:   #012133;
            --c-secondary: #184656;
            --c-teal:      #007a96;
            --c-accent:    #EF7F1B;
            --c-soft:      #FFF5F0;
            --c-body:      #474644;
            --c-bg:        #f5f3f0;
            --c-border:    #e4e2df;
            --radius:      12px;
            --shadow:      0 4px 20px rgba(1,33,51,.07);
            --sidebar-w:   260px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--c-bg);
            color: var(--c-body);
            font-family: "gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex; flex-direction: column; min-height: 100vh;
        }

        /* ── Top bar ── */
        .topbar {
            background: var(--c-primary); color: #fff;
            padding: 0 24px; height: 58px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .topbar-logo { font-size: 17px; font-weight: 800; letter-spacing: -.3px; }
        .topbar-logo span { color: var(--c-accent); }
        .topbar-sep { width: 1px; height: 24px; background: rgba(255,255,255,.2); }
        .topbar-title { font-size: 14px; opacity: .8; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .btn-topbar {
            font-family: inherit; font-size: 13px; font-weight: 600;
            padding: 7px 14px; border-radius: 8px; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
            transition: opacity .15s;
        }
        .btn-topbar-ghost { background: rgba(255,255,255,.1); color: #fff; border: 1px solid rgba(255,255,255,.2); }
        .btn-topbar-ghost:hover { background: rgba(255,255,255,.18); }
        .btn-topbar-accent { background: var(--c-accent); color: #fff; border: none; }
        .btn-topbar-accent:hover { filter: brightness(1.05); }

        /* ── Layout ── */
        .layout { display: flex; flex: 1; overflow: hidden; height: calc(100vh - 58px); }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w); flex-shrink: 0;
            background: #fff; border-right: 1px solid var(--c-border);
            overflow-y: auto; padding: 20px 16px;
            display: flex; flex-direction: column; gap: 24px;
        }

        .sidebar-section-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .8px; color: #aaa; margin-bottom: 8px;
        }

        /* KPIs lateral */
        .kpi-list { display: flex; flex-direction: column; gap: 6px; }
        .kpi-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 12px; border-radius: 9px; background: var(--c-bg);
            border: 1px solid var(--c-border); cursor: pointer;
            transition: border-color .15s, background .15s; text-decoration: none; color: inherit;
        }
        .kpi-item:hover, .kpi-item.active {
            border-color: var(--c-accent); background: var(--c-soft);
        }
        .kpi-item.active .kpi-item-label { color: var(--c-accent); }
        .kpi-item-left { display: flex; align-items: center; gap: 8px; }
        .kpi-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .kpi-item-label { font-size: 13px; font-weight: 600; }
        .kpi-badge-num {
            font-size: 13px; font-weight: 800; min-width: 24px; text-align: center;
            padding: 2px 6px; border-radius: 20px; background: rgba(1,33,51,.07); color: var(--c-primary);
        }

        /* Urgentes */
        .urgent-box {
            background: #FEF2F2; border: 1px solid #FECACA; border-radius: 9px;
            padding: 12px; font-size: 13px; color: #B91C1C;
            display: flex; align-items: center; gap: 8px;
        }
        .urgent-box.hidden { display: none; }

        /* Filtros */
        .filter-select {
            width: 100%; padding: 9px 10px;
            border: 1px solid var(--c-border); border-radius: 8px;
            font-family: inherit; font-size: 13px; color: var(--c-body);
            background: #fff; outline: none; margin-bottom: 8px;
        }
        .filter-select:focus { border-color: var(--c-accent); }
        .btn-filter-reset {
            width: 100%; padding: 8px; background: none; border: 1px solid var(--c-border);
            border-radius: 8px; font-family: inherit; font-size: 12px; color: #888;
            cursor: pointer; transition: border-color .15s, color .15s;
        }
        .btn-filter-reset:hover { border-color: var(--c-accent); color: var(--c-accent); }

        /* ── Main ── */
        .main {
            flex: 1; overflow-y: auto; padding: 20px 24px;
            position: relative;
        }

        .main-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }
        .main-title { font-size: 18px; font-weight: 700; color: var(--c-primary); }
        .main-count { font-size: 13px; color: #888; }

        /* Tabla */
        .table-wrap { background: #fff; border-radius: var(--radius); border: 1px solid var(--c-border); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f7f6f4; padding: 11px 14px; text-align: left;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #aaa;
            position: sticky; top: 0; z-index: 2;
        }
        tbody tr { cursor: pointer; transition: background .1s; }
        tbody tr:hover td { background: #fafaf9; }
        tbody tr.active-row td { background: var(--c-soft) !important; }
        td { padding: 13px 14px; font-size: 13px; border-top: 1px solid var(--c-border); vertical-align: middle; }
        td.urgent { background: rgba(254,242,242,.6); }
        .ref-code { font-family: monospace; font-size: 13px; font-weight: 700; letter-spacing: .5px; color: var(--c-primary); }

        /* Chips */
        .chip {
            display: inline-block; padding: 3px 9px; border-radius: 20px;
            font-size: 11px; font-weight: 700; white-space: nowrap;
        }
        .chip-recibida   { background:#DBEAFE; color:#1D4ED8; }
        .chip-en_tramite { background:#FEF3C7; color:#92400E; }
        .chip-resuelta   { background:#D1FAE5; color:#065F46; }
        .chip-archivada  { background:#F3F4F6; color:#6B7280; }
        .chip-baja       { background:#F3F4F6; color:#6B7280; }
        .chip-media      { background:#DBEAFE; color:#1D4ED8; }
        .chip-alta       { background:#FEF3C7; color:#92400E; }
        .chip-critica    { background:#FEE2E2; color:#B91C1C; }
        .chip-ok         { background:#D1FAE5; color:#065F46; }
        .chip-vencida    { background:#FEE2E2; color:#B91C1C; }

        .empty-state {
            text-align: center; padding: 48px 24px; color: #aaa; font-size: 14px;
        }
        .empty-state i { font-size: 40px; display: block; margin-bottom: 10px; }

        /* ── Detalle slide-in ── */
        .detail-panel {
            width: 420px; flex-shrink: 0;
            background: #fff; border-left: 1px solid var(--c-border);
            overflow-y: auto; padding: 0;
            transform: translateX(100%); transition: transform .25s ease;
            position: relative;
        }
        .detail-panel.open { transform: translateX(0); }

        .detail-header {
            background: var(--c-primary); color: #fff;
            padding: 18px 20px; position: sticky; top: 0; z-index: 5;
            display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
        }
        .detail-header h2 { font-size: 15px; font-family: monospace; letter-spacing: 1px; }
        .detail-header-chips { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
        .btn-close-detail {
            background: rgba(255,255,255,.15); border: none; color: #fff;
            border-radius: 6px; cursor: pointer; padding: 6px 8px;
            font-size: 16px; line-height: 1; flex-shrink: 0;
        }
        .btn-close-detail:hover { background: rgba(255,255,255,.25); }

        .detail-body { padding: 20px; }
        .detail-section-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .7px; color: #aaa; margin: 20px 0 10px;
        }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .meta-block label {
            font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
            color: #aaa; display: block; margin-bottom: 3px;
        }
        .meta-block .val { font-size: 14px; color: var(--c-primary); font-weight: 600; }
        .description-box {
            background: #f7f6f4; border: 1px solid var(--c-border); border-radius: 9px;
            padding: 14px; font-size: 13px; line-height: 1.7; color: var(--c-body);
            white-space: pre-wrap; word-break: break-word;
        }
        .anon-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: #F3F4F6; border-radius: 20px; padding: 3px 10px;
            font-size: 12px; color: #6B7280;
        }

        /* Acciones detalle */
        .action-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .action-select {
            flex: 1; min-width: 130px; padding: 9px 10px;
            border: 1px solid var(--c-border); border-radius: 8px;
            font-family: inherit; font-size: 13px; background: #fff; outline: none;
        }
        .action-select:focus { border-color: var(--c-accent); }
        .btn-action {
            padding: 9px 16px; border-radius: 8px; border: none; cursor: pointer;
            font-family: inherit; font-size: 13px; font-weight: 700;
            transition: filter .15s; white-space: nowrap;
        }
        .btn-action-accent { background: var(--c-accent); color: #fff; }
        .btn-action-accent:hover { filter: brightness(1.05); }
        .btn-action-dark { background: var(--c-primary); color: #fff; }
        .btn-action-dark:hover { filter: brightness(1.1); }

        .note-textarea {
            width: 100%; padding: 10px; min-height: 80px; resize: vertical;
            border: 1px solid var(--c-border); border-radius: 8px;
            font-family: inherit; font-size: 13px; outline: none; margin-top: 8px;
        }
        .note-textarea:focus { border-color: var(--c-accent); }

        /* Historial */
        .activity-list { list-style: none; }
        .activity-list li {
            padding: 8px 0; border-bottom: 1px solid #f0eeeb;
            font-size: 12px; color: var(--c-body); display: flex; gap: 8px;
        }
        .activity-list li:last-child { border-bottom: none; }
        .act-ts { color: #bbb; white-space: nowrap; flex-shrink: 0; }
        .act-label { font-weight: 600; }

        /* Toast */
        .toast {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(20px);
            background: var(--c-primary); color: #fff; padding: 10px 20px;
            border-radius: 9px; font-size: 13px; opacity: 0;
            transition: opacity .25s, transform .25s; z-index: 9999; white-space: nowrap;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.error { background: #B91C1C; }

        /* Loading spinner */
        .detail-loading {
            display: flex; align-items: center; justify-content: center;
            height: 200px; color: #aaa; gap: 10px; font-size: 14px;
        }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .detail-panel { position: fixed; right: 0; top: 58px; height: calc(100vh - 58px); }
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">Val<span>í</span>rica</div>
        <div class="topbar-sep"></div>
        <div class="topbar-title">Canal de Denuncias · <?= htmlspecialchars($config['company_name']) ?></div>
    </div>
    <div class="topbar-right">
        <a href="form.php?empresa=<?= $company_id ?>" class="btn-topbar btn-topbar-ghost" target="_blank">
            <i class="ph ph-link"></i> Enlace al formulario
        </a>
        <a href="admin-config.php" class="btn-topbar btn-topbar-ghost">
            <i class="ph ph-gear"></i> Configuración
        </a>
        <a href="../a-desktop-dashboard-brand.php" class="btn-topbar btn-topbar-accent">
            <i class="ph ph-squares-four"></i> Dashboard
        </a>
    </div>
</div>

<!-- Layout 3 columnas -->
<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <!-- KPIs estado -->
        <div>
            <div class="sidebar-section-label">Estado</div>
            <?php if ($urgentes > 0): ?>
            <div class="urgent-box" style="margin-bottom:10px;">
                <i class="ph ph-warning-circle"></i>
                <strong><?= $urgentes ?> denuncia<?= $urgentes > 1 ? 's' : '' ?></strong>&nbsp;vence<?= $urgentes > 1 ? 'n' : '' ?> pronto
            </div>
            <?php endif; ?>
            <div class="kpi-list">
                <?php
                $estado_dot = [
                    'recibida'   => '#3B82F6',
                    'en_tramite' => '#EF7F1B',
                    'resuelta'   => '#10B981',
                    'archivada'  => '#9CA3AF',
                ];
                $estado_label = ['recibida'=>'Recibidas','en_tramite'=>'En trámite','resuelta'=>'Resueltas','archivada'=>'Archivadas'];
                foreach ($counts as $st => $n): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => $st])) ?>"
                   class="kpi-item <?= $filter_status === $st ? 'active' : '' ?>">
                    <div class="kpi-item-left">
                        <div class="kpi-dot" style="background:<?= $estado_dot[$st] ?>"></div>
                        <span class="kpi-item-label"><?= $estado_label[$st] ?></span>
                    </div>
                    <span class="kpi-badge-num"><?= $n ?></span>
                </a>
                <?php endforeach; ?>
                <?php if ($filter_status): ?>
                <a href="?" class="kpi-item" style="border-style:dashed;">
                    <div class="kpi-item-left" style="gap:6px;">
                        <i class="ph ph-x" style="font-size:12px;color:#888"></i>
                        <span style="font-size:12px;color:#888;">Mostrar todos</span>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros -->
        <div>
            <div class="sidebar-section-label">Filtros</div>
            <form method="GET">
                <?php if ($filter_status): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <select name="type" class="filter-select" onchange="this.form.submit()">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($all_types as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filter_type === $v ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="priority" class="filter-select" onchange="this.form.submit()">
                    <option value="">Todas las prioridades</option>
                    <?php foreach (['baja','media','alta','critica'] as $p): ?>
                    <option value="<?= $p ?>" <?= $filter_priority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filter_type || $filter_priority): ?>
                <button type="button" class="btn-filter-reset"
                        onclick="location.href='?<?= $filter_status ? 'status='.urlencode($filter_status) : '' ?>'">
                    ✕ Limpiar filtros
                </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Total -->
        <div style="font-size:12px;color:#aaa;margin-top:auto;">
            <?= $total ?> denuncia<?= $total !== 1 ? 's' : '' ?> en total
        </div>
    </aside>

    <!-- Tabla central -->
    <main class="main" id="main-panel">
        <div class="main-header">
            <div class="main-title">Denuncias</div>
            <div class="main-count"><?= count($complaints) ?> resultado<?= count($complaints) !== 1 ? 's' : '' ?></div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Referencia</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Plazo restante</th>
                        <th>Recibida</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($complaints)): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <i class="ph ph-inbox"></i>
                            No hay denuncias con los filtros seleccionados.
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($complaints as $c): ?>
                    <?php
                    $dl   = $c['resolution_deadline'] ? days_until($c['resolution_deadline']) : null;
                    $is_urg = $dl !== null && $dl <= 3 && !in_array($c['status'], ['resuelta','archivada']);
                    ?>
                    <tr data-id="<?= $c['id'] ?>"
                        class="complaint-row <?= $is_urg ? 'urgent-row' : '' ?>"
                        onclick="openDetail(<?= $c['id'] ?>, this)">
                        <td class="ref-code <?= $is_urg ? 'urgent' : '' ?>"><?= htmlspecialchars($c['reference_code']) ?></td>
                        <td><?= htmlspecialchars(complaint_type_label($c['type'])) ?></td>
                        <td><span class="chip chip-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$c['status']))) ?></span></td>
                        <td><span class="chip chip-<?= htmlspecialchars($c['priority']) ?>"><?= htmlspecialchars(ucfirst($c['priority'])) ?></span></td>
                        <td>
                            <?php if ($dl !== null && !in_array($c['status'], ['resuelta','archivada'])): ?>
                                <?= urgency_chip($dl) ?>
                            <?php else: ?><span style="color:#bbb">—</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Panel detalle (slide-in) -->
    <aside class="detail-panel" id="detail-panel">
        <div id="detail-content">
            <!-- Rellenado por JS -->
        </div>
    </aside>

</div>

<div id="toast" class="toast"></div>

<script>
const CSRF   = <?= json_encode($csrf) ?>;
const IS_RESPONSIBLE = <?= $is_responsible ? 'true' : 'false' ?>;
let activeId = null;

// ── Abrir detalle ──────────────────────────────────────────────────────────
async function openDetail(id, rowEl) {
    if (activeId === id) {
        closeDetail(); return;
    }
    activeId = id;

    // Highlight fila
    document.querySelectorAll('.complaint-row').forEach(r => r.classList.remove('active-row'));
    if (rowEl) rowEl.classList.add('active-row');

    const panel = document.getElementById('detail-panel');
    panel.classList.add('open');

    document.getElementById('detail-content').innerHTML =
        '<div class="detail-loading"><i class="ph ph-spinner" style="animation:spin 1s linear infinite;font-size:22px"></i> Cargando…</div>';

    renderDetailFromId(id);
}

// Carga los datos directamente vía API interna
async function renderDetailFromId(id) {
    try {
        const res = await fetch('detail_api.php?id=' + id + '&csrf=' + encodeURIComponent(CSRF));
        if (!res.ok) { renderDetailFallback(id); return; }
        const d = await res.json();
        if (!d.ok) { renderDetailFallback(id); return; }
        renderDetail(d);
    } catch (e) {
        document.getElementById('detail-content').innerHTML =
            '<p style="padding:20px;color:#B91C1C">Error al cargar el detalle.</p>';
    }
}

// Fallback: redirigir a manage.php si no hay API
function renderDetailFallback(id) {
    document.getElementById('detail-content').innerHTML = `
        <div class="detail-header">
            <div>
                <h2 style="font-family:monospace">Expediente #${id}</h2>
            </div>
            <button class="btn-close-detail" onclick="closeDetail()">✕</button>
        </div>
        <div class="detail-body" style="padding-top:24px">
            <p style="font-size:14px;color:#888;margin-bottom:16px">
                Abre el expediente completo para ver todos los detalles, descifrar la descripción y gestionar acciones.
            </p>
            <a href="manage.php?id=${id}" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:var(--c-accent);color:#fff;border-radius:9px;text-decoration:none;font-weight:700;font-size:14px">
                <i class="ph ph-arrow-square-out"></i> Abrir expediente completo
            </a>
        </div>
    `;
}

function renderDetail(d) {
    const urgDays  = d.resolution_deadline ? daysUntil(d.resolution_deadline) : null;
    const urgHtml  = urgDays !== null ? urgencyChip(urgDays) : '—';
    const anonHtml = d.is_anonymous
        ? '<span class="anon-badge"><i class="ph ph-lock-key"></i> Anónimo</span>'
        : (IS_RESPONSIBLE && d.reporter_name
            ? `<strong>${escHtml(d.reporter_name)}</strong>${d.reporter_email ? '<br><small style="color:#888">'+escHtml(d.reporter_email)+'</small>' : ''}`
            : '<span class="anon-badge"><i class="ph ph-lock-key"></i> Solo responsable</span>');

    const activitiesHtml = (d.activities || []).map(a =>
        `<li><span class="act-ts">${escHtml(a.created_at?.substr(0,16) || '')}</span>
         <span><span class="act-label">${escHtml(a.action.replace(/_/g,' '))}</span>
         ${a.notes ? ' — ' + escHtml(a.notes) : ''}</span></li>`
    ).join('');

    document.getElementById('detail-content').innerHTML = `
    <div class="detail-header">
        <div>
            <h2>${escHtml(d.reference_code)}</h2>
            <div class="detail-header-chips">
                <span class="chip chip-${d.status}">${ucStatus(d.status)}</span>
                <span class="chip chip-${d.priority}">${ucFirst(d.priority)}</span>
                ${d.country === 'CO' ? '<span class="chip" style="background:#FEF3C7;color:#92400E">🇨🇴 CO</span>' : '<span class="chip" style="background:#DBEAFE;color:#1D4ED8">🇪🇸 ES</span>'}
            </div>
        </div>
        <button class="btn-close-detail" onclick="closeDetail()">✕</button>
    </div>
    <div class="detail-body">

        <div class="meta-grid">
            <div class="meta-block"><label>Tipo</label><div class="val">${escHtml(d.type_label)}</div></div>
            <div class="meta-block"><label>Recibida</label><div class="val">${escHtml(d.created_at?.substr(0,10) || '')}</div></div>
            <div class="meta-block"><label>Plazo resolución</label><div class="val">${urgHtml}</div></div>
            <div class="meta-block"><label>Denunciante</label><div class="val">${anonHtml}</div></div>
        </div>

        <div class="detail-section-title">Descripción</div>
        <div class="description-box">${escHtml(d.description_plain || '(cifrada — abre expediente completo)')}</div>

        ${d.internal_notes_plain ? `
        <div class="detail-section-title">Notas internas</div>
        <div class="description-box" style="background:#FFF7ED;border-color:#FED7AA;">${escHtml(d.internal_notes_plain)}</div>
        ` : ''}

        <div class="detail-section-title">Cambiar estado</div>
        <div class="action-row">
            <select id="sel-status-${d.id}" class="action-select">
                ${['recibida','en_tramite','resuelta','archivada'].map(s =>
                    `<option value="${s}" ${d.status===s?'selected':''}>${ucStatus(s)}</option>`
                ).join('')}
            </select>
            <button class="btn-action btn-action-accent" onclick="changeStatus(${d.id})">Guardar</button>
        </div>

        <div class="detail-section-title">Nota interna</div>
        <textarea id="note-input-${d.id}" class="note-textarea" placeholder="Escribe una nota interna (cifrada)…"></textarea>
        <div class="action-row" style="margin-top:8px">
            <button class="btn-action btn-action-dark" onclick="addNote(${d.id})">
                <i class="ph ph-floppy-disk"></i> Guardar nota
            </button>
            <a href="manage.php?id=${d.id}" target="_blank" class="btn-action btn-action-accent" style="text-decoration:none">
                <i class="ph ph-arrow-square-out"></i> Expediente completo
            </a>
        </div>

        ${activitiesHtml ? `
        <div class="detail-section-title">Actividad reciente</div>
        <ul class="activity-list">${activitiesHtml}</ul>
        ` : ''}
    </div>`;
}

function closeDetail() {
    document.getElementById('detail-panel').classList.remove('open');
    document.querySelectorAll('.complaint-row').forEach(r => r.classList.remove('active-row'));
    activeId = null;
}

// ── Acciones ────────────────────────────────────────────────────────────────
async function doAction(payload) {
    payload.csrf_token   = CSRF;
    payload.complaint_id = activeId;
    const r = await fetch('manage.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams(payload)
    });
    return await r.json();
}

async function changeStatus(id) {
    const sel = document.getElementById('sel-status-' + id);
    const d = await doAction({ajax_action:'change_status', status: sel.value});
    showToast(d.message, d.ok ? '' : 'error');
    if (d.ok) setTimeout(() => location.reload(), 1000);
}

async function addNote(id) {
    const ta = document.getElementById('note-input-' + id);
    if (!ta.value.trim()) return;
    const d = await doAction({ajax_action:'add_note', note: ta.value});
    showToast(d.message, d.ok ? '' : 'error');
    if (d.ok) ta.value = '';
}

// ── Helpers UI ───────────────────────────────────────────────────────────────
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (type === 'error' ? ' error' : '');
    setTimeout(() => t.className = 'toast', 3000);
}

function daysUntil(ds) {
    const now  = new Date();
    const end  = new Date(ds);
    const diff = Math.round((end - now) / 86400000);
    return diff;
}

function urgencyChip(d) {
    if (d < 0)  return '<span class="chip chip-vencida">Vencida</span>';
    if (d <= 3) return '<span class="chip chip-critica">≤' + d + ' días</span>';
    if (d <= 10) return '<span class="chip chip-alta">' + d + ' días</span>';
    return '<span class="chip chip-ok">' + d + ' días</span>';
}

function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function ucStatus(s) { return ucFirst(s.replace('_', ' ')); }
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const all_types_map = <?= json_encode(array_merge(
    ['acoso_laboral'=>'Acoso laboral','acoso_sexual'=>'Acoso sexual','fraude'=>'Fraude',
     'corrupcion'=>'Corrupción','discriminacion'=>'Discriminación',
     'incumplimiento_normativo'=>'Incumplimiento normativo','conflicto_interes'=>'Conflicto de interés','otro'=>'Otro']
)) ?>;

const style = document.createElement('style');
style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(style);
</script>
</body>
</html>
