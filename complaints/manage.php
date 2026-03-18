<?php
/**
 * complaints/manage.php — Panel del Responsable del Canal de Denuncias
 * Acceso: responsible_user_id O company admin (usuarios.id === company_id del canal)
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

$user_id = (int)$_SESSION['user_id'];

// Determinar la empresa a gestionar
// company_admin: su propia empresa; provider con ?company_id puede usarlo también
$company_id = $user_id; // por defecto es su propia empresa

// Verificar acceso al canal
$stmt = $conn->prepare("
    SELECT ccc.*, u.empresa AS company_name, u.country AS company_country
    FROM complaint_channel_config ccc
    INNER JOIN usuarios u ON u.id = ccc.company_id
    WHERE ccc.company_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res    = stmt_get_result($stmt);
$config = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
$stmt->close();

if (!$config || !$config['is_active']) {
    http_response_code(403);
    echo '<p style="font-family:Georgia,serif;padding:40px;color:#B91C1C;">Canal de denuncias no activo. Actívalo en <a href="admin-config.php">Configuración del canal</a>.</p>';
    exit;
}

$is_responsible = ((int)$config['responsible_user_id'] === $user_id);
$is_admin       = ($user_id === $company_id);

if (!$is_responsible && !$is_admin) {
    http_response_code(403);
    echo '<p style="font-family:Georgia,serif;padding:40px;color:#B91C1C;">Acceso denegado.</p>';
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX ACTIONS
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=UTF-8');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Token inválido']);
        exit;
    }

    $action      = $_POST['ajax_action'];
    $cid         = (int)($_POST['complaint_id'] ?? 0);

    // Verificar que la denuncia pertenece a esta empresa
    $stmt = $conn->prepare("SELECT id, status, is_anonymous FROM complaints WHERE id = ? AND company_id = ? LIMIT 1");
    $stmt->bind_param("ii", $cid, $company_id);
    $stmt->execute();
    $res_c = stmt_get_result($stmt);
    $stmt->close();
    if (!$res_c || $res_c->num_rows === 0) {
        echo json_encode(['ok' => false, 'message' => 'Denuncia no encontrada']);
        exit;
    }
    $complaint_row = $res_c->fetch_assoc();

    // Registrar acceso (trazabilidad)
    log_complaint_activity($conn, $cid, 'empresa', $user_id, 'acceso_panel', null);

    if ($action === 'change_status') {
        $new_status = trim($_POST['status'] ?? '');
        $allowed    = ['recibida','en_tramite','resuelta','archivada'];
        if (!in_array($new_status, $allowed, true)) {
            echo json_encode(['ok' => false, 'message' => 'Estado no válido']); exit;
        }
        $resolved_at = ($new_status === 'resuelta') ? date('Y-m-d H:i:s') : null;
        $stmt = $conn->prepare("UPDATE complaints SET status = ?, resolved_at = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $resolved_at, $cid);
        $stmt->execute();
        $stmt->close();
        log_complaint_activity($conn, $cid, 'empresa', $user_id, 'cambio_estado', "Nuevo estado: {$new_status}");
        echo json_encode(['ok' => true, 'message' => 'Estado actualizado']);
        exit;
    }

    if ($action === 'change_priority') {
        $new_priority = trim($_POST['priority'] ?? '');
        $allowed = ['baja','media','alta','critica'];
        if (!in_array($new_priority, $allowed, true)) {
            echo json_encode(['ok' => false, 'message' => 'Prioridad no válida']); exit;
        }
        $stmt = $conn->prepare("UPDATE complaints SET priority = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_priority, $cid);
        $stmt->execute();
        $stmt->close();
        log_complaint_activity($conn, $cid, 'empresa', $user_id, 'cambio_prioridad', "Nueva prioridad: {$new_priority}");
        echo json_encode(['ok' => true, 'message' => 'Prioridad actualizada']);
        exit;
    }

    if ($action === 'add_note') {
        $note_plain = trim($_POST['note'] ?? '');
        if (strlen($note_plain) < 3) {
            echo json_encode(['ok' => false, 'message' => 'Nota demasiado corta']); exit;
        }
        $note_enc = complaint_encrypt($note_plain);
        $stmt = $conn->prepare("UPDATE complaints SET internal_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $note_enc, $cid);
        $stmt->execute();
        $stmt->close();
        log_complaint_activity($conn, $cid, 'empresa', $user_id, 'nota_interna', null);
        echo json_encode(['ok' => true, 'message' => 'Nota guardada']);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Acción no reconocida']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// DETALLE DE UNA DENUNCIA
// ─────────────────────────────────────────────────────────────────────────────
$detail = null;
$activities = [];

if (!empty($_GET['id'])) {
    $detail_id = (int)$_GET['id'];
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre AS assigned_name
        FROM complaints c
        LEFT JOIN usuarios u ON u.id = c.assigned_to
        WHERE c.id = ? AND c.company_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $detail_id, $company_id);
    $stmt->execute();
    $res_d = stmt_get_result($stmt);
    $stmt->close();

    if ($res_d && $res_d->num_rows > 0) {
        $detail = $res_d->fetch_assoc();
        // Trazabilidad: registrar acceso
        log_complaint_activity($conn, $detail_id, 'empresa', $user_id, 'acceso_detalle', null);

        // Actividades
        $stmt2 = $conn->prepare("
            SELECT ca.*, u.nombre AS actor_name
            FROM complaint_activities ca
            LEFT JOIN usuarios u ON u.id = ca.actor_id AND ca.actor_tipo = 'empresa'
            WHERE ca.complaint_id = ?
            ORDER BY ca.created_at ASC
        ");
        $stmt2->bind_param("i", $detail_id);
        $stmt2->execute();
        $res_a = stmt_get_result($stmt2);
        $stmt2->close();
        while ($row = $res_a->fetch_assoc()) {
            $activities[] = $row;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LISTA CON FILTROS
// ─────────────────────────────────────────────────────────────────────────────
$filter_status   = trim($_GET['status'] ?? '');
$filter_type     = trim($_GET['type'] ?? '');
$filter_priority = trim($_GET['priority'] ?? '');

$where_parts = ['c.company_id = ?'];
$bind_types  = 'i';
$bind_vals   = [$company_id];

if ($filter_status !== '') {
    $where_parts[] = 'c.status = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_status;
}
if ($filter_type !== '') {
    $where_parts[] = 'c.type = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_type;
}
if ($filter_priority !== '') {
    $where_parts[] = 'c.priority = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_priority;
}

$where_sql = implode(' AND ', $where_parts);
$sql = "
    SELECT c.id, c.reference_code, c.type, c.status, c.priority, c.is_anonymous,
           c.created_at, c.resolution_deadline, c.receipt_deadline
    FROM complaints c
    WHERE {$where_sql}
    ORDER BY c.resolution_deadline ASC, c.created_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($bind_types, ...$bind_vals);
$stmt->execute();
$res_list = stmt_get_result($stmt);
$stmt->close();

$complaints = [];
while ($row = $res_list->fetch_assoc()) {
    $complaints[] = $row;
}

// Alerta: vencen en ≤3 días y no resueltas
$alert_count = 0;
foreach ($complaints as $c) {
    if (in_array($c['status'], ['resuelta','archivada'], true)) continue;
    if ($c['resolution_deadline'] && days_until($c['resolution_deadline']) <= 3) {
        $alert_count++;
    }
}

$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canal de Denuncias — <?= htmlspecialchars($config['company_name']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Georgia, serif; background: #f0eeeb; color: #012133; }
        .top-bar {
            background: #012133; color: #fff;
            padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar h1 { font-size: 18px; }
        .top-bar .sub { font-size: 12px; opacity: .7; margin-top: 2px; }
        .top-bar a { color: #EF7F1B; text-decoration: none; font-size: 13px; }
        .container { max-width: 1100px; margin: 0 auto; padding: 28px 20px; }

        /* Alerta urgencia */
        .alert-banner {
            background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px;
            padding: 14px 18px; margin-bottom: 20px;
            color: #B91C1C; font-size: 14px; display: flex; align-items: center; gap: 10px;
        }

        /* Filtros */
        .filters {
            display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .filters select, .filters button {
            padding: 8px 14px; border: 1px solid #e8e6e3; border-radius: 8px;
            font-size: 13px; font-family: Georgia, serif; background: #fff; cursor: pointer;
        }
        .filters button { background: #EF7F1B; color: #fff; border-color: #EF7F1B; font-weight: 700; }

        /* Tabla */
        .table-wrap { background: #fff; border-radius: 14px; border: 1px solid #e8e6e3; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f7f6f4; padding: 12px 16px; text-align: left; font-size: 12px;
             text-transform: uppercase; letter-spacing: .6px; color: #9a9896; }
        td { padding: 14px 16px; font-size: 14px; border-top: 1px solid #e8e6e3; vertical-align: middle; }
        tr:hover td { background: #fafaf9; cursor: pointer; }

        /* Chips */
        .chip {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700; white-space: nowrap;
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

        /* Panel detalle */
        .detail-panel {
            background: #fff; border-radius: 14px; border: 1px solid #e8e6e3;
            margin-top: 28px; overflow: hidden;
        }
        .detail-header {
            background: #012133; color: #fff; padding: 20px 28px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .detail-header h2 { font-size: 17px; }
        .detail-body { padding: 28px; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
        @media(max-width:640px){ .detail-grid { grid-template-columns: 1fr; } }

        .meta-block { margin-bottom: 20px; }
        .meta-block label { font-size: 11px; text-transform: uppercase; letter-spacing:.6px; color:#9a9896; display:block; margin-bottom:4px; }
        .meta-block .val { font-size: 15px; color: #012133; }

        .description-box {
            background: #f7f6f4; border: 1px solid #e8e6e3; border-radius: 10px;
            padding: 16px 18px; font-size: 14px; line-height: 1.7; color: #3d3c3b;
            white-space: pre-wrap; word-break: break-word;
        }

        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase;
                         letter-spacing:.6px; color:#9a9896; margin-bottom:12px; margin-top:24px; }

        /* Historial actividad */
        .activity-list { list-style: none; }
        .activity-list li {
            padding: 10px 0; border-bottom: 1px solid #f0eeeb;
            font-size: 13px; color: #3d3c3b; display: flex; gap: 10px;
        }
        .activity-list li:last-child { border-bottom: none; }
        .activity-list .ts { color: #9a9896; white-space: nowrap; }
        .activity-list .act { font-weight: 600; }

        /* Acciones */
        .actions-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        select.action-select, button.action-btn {
            padding: 9px 16px; border-radius: 8px; border: 1px solid #e8e6e3;
            font-family: Georgia, serif; font-size: 14px; cursor: pointer;
        }
        button.action-btn { background: #EF7F1B; color: #fff; border-color: #EF7F1B; font-weight: 700; }
        button.action-btn:hover { background: #d96e0e; }
        button.btn-note { background: #012133; border-color: #012133; }
        button.btn-note:hover { background: #023a55; }

        textarea.note-input {
            width: 100%; padding: 12px; border: 1px solid #e8e6e3; border-radius: 8px;
            font-family: Georgia, serif; font-size: 14px; resize: vertical;
            margin-top: 12px; min-height: 80px;
        }

        .toast {
            position: fixed; bottom: 24px; right: 24px;
            background: #012133; color: #fff;
            padding: 12px 20px; border-radius: 10px; font-size: 14px;
            opacity: 0; transition: opacity .3s;
            z-index: 9999;
        }
        .toast.show { opacity: 1; }
        .toast.error { background: #B91C1C; }

        .back-link { color: #EF7F1B; text-decoration: none; font-size: 14px; display:inline-block; margin-bottom:20px; }
        .anon-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #F3F4F6; border-radius: 20px; padding: 4px 12px;
            font-size: 12px; color: #6B7280;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div>
        <h1>Canal de Denuncias</h1>
        <div class="sub"><?= htmlspecialchars($config['company_name']) ?></div>
    </div>
    <div style="display:flex;gap:12px;align-items:center;">
        <a href="admin-config.php">⚙ Configuración</a>
        <a href="../a-desktop-dashboard-brand.php" style="background:#EF7F1B;color:#fff;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:700;">← Dashboard</a>
    </div>
</div>

<div class="container">

    <?php if ($alert_count > 0): ?>
    <div class="alert-banner">
        ⚠️ <strong><?= $alert_count ?> denuncia<?= $alert_count > 1 ? 's' : '' ?></strong>
        venc<?= $alert_count > 1 ? 'en' : 'e' ?> en 3 días o menos.
    </div>
    <?php endif; ?>

    <?php if ($detail): ?>
    <!-- ──── VISTA DETALLE ──── -->
    <a href="manage.php" class="back-link">← Volver al listado</a>

    <div class="detail-panel">
        <div class="detail-header">
            <h2><?= htmlspecialchars($detail['reference_code']) ?></h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="chip chip-<?= htmlspecialchars($detail['status']) ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_',' ',$detail['status']))) ?>
                </span>
                <span class="chip chip-<?= htmlspecialchars($detail['priority']) ?>">
                    <?= htmlspecialchars(ucfirst($detail['priority'])) ?>
                </span>
            </div>
        </div>
        <div class="detail-body">
            <div class="detail-grid">
                <div>
                    <div class="meta-block">
                        <label>Tipo</label>
                        <div class="val"><?= htmlspecialchars(complaint_type_label($detail['type'])) ?></div>
                    </div>
                    <div class="meta-block">
                        <label>País</label>
                        <div class="val"><?= $detail['country'] === 'ES' ? '🇪🇸 España' : '🇨🇴 Colombia' ?></div>
                    </div>
                    <div class="meta-block">
                        <label>Recibida</label>
                        <div class="val"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($detail['created_at']))) ?></div>
                    </div>
                    <div class="meta-block">
                        <label>Plazo de resolución</label>
                        <div class="val">
                            <?php if ($detail['resolution_deadline']): ?>
                                <?= htmlspecialchars(date('d/m/Y', strtotime($detail['resolution_deadline']))) ?>
                                <?= urgency_chip(days_until($detail['resolution_deadline'])) ?>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="meta-block">
                        <label>Denunciante</label>
                        <div class="val">
                            <?php if ($detail['is_anonymous']): ?>
                                <span class="anon-badge">🔒 Anónimo — no disponible</span>
                            <?php elseif ($is_responsible): ?>
                                <?= htmlspecialchars(complaint_decrypt($detail['reporter_encrypted_name'] ?? '')) ?>
                                <?php if ($detail['reporter_encrypted_email']): ?>
                                    <br><small style="color:#7a7977;"><?= htmlspecialchars(complaint_decrypt($detail['reporter_encrypted_email'])) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="anon-badge">🔒 Solo visible al responsable designado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="meta-block">
                        <label>Asignado a</label>
                        <div class="val"><?= htmlspecialchars($detail['assigned_name'] ?? '—') ?></div>
                    </div>
                    <?php if ($detail['evidence_paths']): ?>
                    <div class="meta-block">
                        <label>Evidencias</label>
                        <div class="val">
                            <?php
                            $evs = json_decode($detail['evidence_paths'], true) ?? [];
                            foreach ($evs as $ev):
                                // Stripear prefijo 'uploads/complaints/' para construir param de evidence.php
                                $ev_file = substr($ev, strlen('uploads/complaints/')); ?>
                                <a href="evidence.php?file=<?= htmlspecialchars($ev_file) ?>" target="_blank" style="color:#EF7F1B;display:block;font-size:13px;">
                                    📎 <?= htmlspecialchars(basename($ev)) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Descripción -->
            <div class="section-title">Descripción</div>
            <div class="description-box">
                <?= htmlspecialchars(complaint_decrypt($detail['description'])) ?>
            </div>

            <!-- Notas internas -->
            <?php if ($detail['internal_notes']): ?>
            <div class="section-title">Notas internas</div>
            <div class="description-box" style="background:#FFF7ED;border-color:#FED7AA;">
                <?= htmlspecialchars(complaint_decrypt($detail['internal_notes'])) ?>
            </div>
            <?php endif; ?>

            <!-- Acciones -->
            <div class="section-title">Acciones</div>
            <div class="actions-row">
                <select id="select-status" class="action-select">
                    <?php foreach (['recibida','en_tramite','resuelta','archivada'] as $s): ?>
                    <option value="<?= $s ?>" <?= $detail['status'] === $s ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_',' ',$s)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="action-btn" onclick="changeStatus()">Cambiar estado</button>

                <select id="select-priority" class="action-select">
                    <?php foreach (['baja','media','alta','critica'] as $p): ?>
                    <option value="<?= $p ?>" <?= $detail['priority'] === $p ? 'selected' : '' ?>>
                        <?= ucfirst($p) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="action-btn" onclick="changePriority()">Cambiar prioridad</button>
            </div>

            <textarea id="note-input" class="note-input" placeholder="Escribe una nota interna (cifrada, no visible al denunciante)…"></textarea>
            <div class="actions-row">
                <button class="action-btn btn-note" onclick="addNote()">💾 Guardar nota interna</button>
            </div>

            <!-- Historial de actividad -->
            <div class="section-title">Historial de actividad</div>
            <ul class="activity-list">
                <?php foreach ($activities as $act): ?>
                <li>
                    <span class="ts"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($act['created_at']))) ?></span>
                    <span>
                        <span class="act"><?= htmlspecialchars(str_replace('_', ' ', $act['action'])) ?></span>
                        <?php if ($act['notes']): ?>
                            — <?= htmlspecialchars($act['notes']) ?>
                        <?php endif; ?>
                        <span style="color:#9a9896;font-size:12px;">
                            (<?= htmlspecialchars($act['actor_tipo']) ?>
                            <?php if ($act['actor_name']): ?>: <?= htmlspecialchars($act['actor_name']) ?><?php endif; ?>)
                        </span>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script>
    const COMPLAINT_ID = <?= (int)$detail['id'] ?>;
    const CSRF         = <?= json_encode($csrf) ?>;

    function doAction(payload, onSuccess) {
        payload.csrf_token  = CSRF;
        payload.complaint_id = COMPLAINT_ID;
        fetch('manage.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(payload)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.message, d.ok ? '' : 'error');
            if (d.ok && onSuccess) onSuccess();
        })
        .catch(() => showToast('Error de red', 'error'));
    }

    function changeStatus() {
        const s = document.getElementById('select-status').value;
        if (!confirm('¿Cambiar estado a "' + s + '"?')) return;
        doAction({ajax_action: 'change_status', status: s}, () => location.reload());
    }
    function changePriority() {
        const p = document.getElementById('select-priority').value;
        doAction({ajax_action: 'change_priority', priority: p}, () => location.reload());
    }
    function addNote() {
        const note = document.getElementById('note-input').value.trim();
        if (!note) return;
        doAction({ajax_action: 'add_note', note: note}, () => location.reload());
    }

    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast show' + (type === 'error' ? ' error' : '');
        setTimeout(() => { t.className = 'toast'; }, 3000);
    }
    </script>

    <?php else: ?>
    <!-- ──── VISTA LISTA ──── -->

    <form method="GET" class="filters">
        <select name="status">
            <option value="">Todos los estados</option>
            <?php foreach (['recibida','en_tramite','resuelta','archivada'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                <?= ucfirst(str_replace('_',' ',$s)) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="type">
            <option value="">Todos los tipos</option>
            <?php foreach (['acoso_laboral','acoso_sexual','fraude','corrupcion','discriminacion','incumplimiento_normativo','conflicto_interes','otro'] as $t): ?>
            <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>>
                <?= htmlspecialchars(complaint_type_label($t)) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="priority">
            <option value="">Todas las prioridades</option>
            <?php foreach (['baja','media','alta','critica'] as $p): ?>
            <option value="<?= $p ?>" <?= $filter_priority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
        <?php if ($filter_status || $filter_type || $filter_priority): ?>
        <a href="manage.php" style="padding:8px 14px;border:1px solid #e8e6e3;border-radius:8px;font-size:13px;background:#fff;text-decoration:none;color:#3d3c3b;">✕ Limpiar</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Referencia</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Días restantes</th>
                    <th>Recibida</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($complaints)): ?>
                <tr><td colspan="6" style="text-align:center;color:#9a9896;padding:32px;">No hay denuncias con los filtros seleccionados.</td></tr>
            <?php endif; ?>
            <?php foreach ($complaints as $c): ?>
                <?php
                    $days_left = $c['resolution_deadline']
                        ? days_until($c['resolution_deadline'])
                        : null;
                    $row_style = '';
                    if ($days_left !== null && $days_left <= 3 && !in_array($c['status'], ['resuelta','archivada'])) {
                        $row_style = 'background:#FEF2F2;';
                    }
                ?>
                <tr onclick="location.href='manage.php?id=<?= $c['id'] ?>'" style="<?= $row_style ?>">
                    <td style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($c['reference_code']) ?></td>
                    <td><?= htmlspecialchars(complaint_type_label($c['type'])) ?></td>
                    <td><span class="chip chip-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$c['status']))) ?></span></td>
                    <td><span class="chip chip-<?= htmlspecialchars($c['priority']) ?>"><?= htmlspecialchars(ucfirst($c['priority'])) ?></span></td>
                    <td>
                        <?php if ($days_left !== null && !in_array($c['status'], ['resuelta','archivada'])): ?>
                            <?= urgency_chip($days_left) ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<div id="toast" class="toast"></div>

</body>
</html>
