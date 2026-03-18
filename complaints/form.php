<?php
/**
 * complaints/form.php — Formulario público de denuncia
 * Accesible sin autenticación desde: ?empresa={company_id}
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';

date_default_timezone_set('Europe/Madrid');

// Resolver company_id — prioridad: ?empresa=ID > ?canal=SLUG > sesión empleado
$company_id = (int)($_GET['empresa'] ?? $_POST['company_id'] ?? 0);

// Soporte URL pública con slug: complaints/form.php?canal=nombre-empresa
if ($company_id <= 0 && !empty($_GET['canal'])) {
    $canal_slug = trim($_GET['canal']);
    if (preg_match('/^[a-z0-9\-]{2,60}$/', $canal_slug)) {
        $stmt = $conn->prepare(
            "SELECT company_id FROM complaint_channel_config WHERE canal_slug = ? LIMIT 1"
        );
        $stmt->bind_param("s", $canal_slug);
        $stmt->execute();
        $res_slug = stmt_get_result($stmt);
        $stmt->close();
        if ($res_slug && $res_slug->num_rows > 0) {
            $company_id = (int)$res_slug->fetch_assoc()['company_id'];
        }
    }
}

// Si el empleado está logueado, deducir company_id desde su sesión
if ($company_id <= 0 && !empty($_SESSION['empleado_id'])) {
    $eid  = (int)$_SESSION['empleado_id'];
    $stmt = $conn->prepare("SELECT usuario_id FROM equipo WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $res_e = stmt_get_result($stmt);
    $stmt->close();
    if ($res_e && $res_e->num_rows > 0) {
        $row_e      = $res_e->fetch_assoc();
        $company_id = (int)($row_e['usuario_id'] ?? 0);
    }
}

if ($company_id <= 0) {
    http_response_code(400);
    echo '<p style="font-family:Georgia,serif;padding:40px;">No se encontró la empresa. Usa el enlace que te proporcionó tu empresa.</p>';
    exit;
}

$config = get_company_config($conn, $company_id);

if (!$config || !$config['is_active']) {
    http_response_code(404);
    echo '<p style="font-family:Georgia,serif;padding:40px;">El canal de denuncias no está disponible para esta empresa.</p>';
    exit;
}

$country     = $config['company_country'] ?? 'ES';
$policy_text = trim($config['channel_policy_text'] ?? '');
$allow_anon  = (bool)$config['is_anonymous_allowed'];

// Texto legal por país
$legal_text = $country === 'ES'
    ? 'Este canal cumple con la <strong>Ley 2/2023</strong>, de 20 de febrero, de protección de las personas que informen sobre infracciones normativas (Canal de Denuncias). El Responsable del Sistema garantiza la confidencialidad de su identidad.'
    : 'Este canal cumple con la <strong>Ley 1010 de 2006</strong> (acoso laboral), <strong>Ley 2365 de 2024</strong> (acoso sexual laboral) y la <strong>Resolución 3461 de 2025</strong>. Las denuncias son atendidas por el Comité de Convivencia Laboral.';

// Labels completos de cada tipo (para el <select> del formulario)
$all_type_labels = [
    'acoso_laboral'            => 'Acoso laboral',
    'acoso_sexual'             => 'Acoso sexual',
    'fraude'                   => 'Fraude',
    'corrupcion'               => 'Corrupción',
    'discriminacion'           => 'Discriminación',
    'incumplimiento_normativo' => 'Incumplimiento normativo',
    'conflicto_interes'        => 'Conflicto de interés',
    'otro'                     => 'Otro',
];

// Tipos permitidos según país — fuente única: get_country_config()
$country_cfg = get_country_config($country);
$types       = array_intersect_key(
    $all_type_labels,
    array_flip($country_cfg['allowed_types'])
);

$csrf = getCsrfToken();
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
            --radius:      14px;
            --shadow:      0 4px 20px rgba(1,33,51,.07);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            background: var(--c-bg);
            color: var(--c-body);
            font-family: "gelica", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }

        /* ── Header ── */
        .site-header {
            background: var(--c-primary);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .site-header .brand { display: flex; align-items: center; gap: 12px; }
        .site-header .brand img { height: 36px; border-radius: 8px; }
        .site-header .brand-name { font-size: 16px; font-weight: 700; color: #fff; }
        .site-header .brand-sub  { font-size: 12px; color: rgba(255,255,255,.65); margin-top: 1px; }
        .track-link {
            font-size: 13px; color: var(--c-soft); text-decoration: none;
            display: flex; align-items: center; gap: 6px; opacity: .8;
            transition: opacity .15s;
        }
        .track-link:hover { opacity: 1; }

        /* ── Layout ── */
        .page { max-width: 680px; margin: 0 auto; padding: 32px 16px 64px; }

        /* ── Card ── */
        .card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--c-border);
            box-shadow: var(--shadow);
            padding: 28px;
            margin-bottom: 20px;
        }
        .card-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 17px; font-weight: 700; color: var(--c-primary);
            margin-bottom: 6px;
        }
        .card-title .icon-wrap {
            width: 34px; height: 34px; border-radius: 10px;
            background: var(--c-soft); display: grid; place-items: center;
            color: var(--c-accent); flex-shrink: 0;
        }
        .card-sub { font-size: 13px; color: #888; margin-bottom: 20px; line-height: 1.5; }

        /* ── Legal banner ── */
        .legal-banner {
            background: #EFF6FF; border: 1px solid #BFDBFE;
            border-radius: 10px; padding: 14px 16px;
            font-size: 13px; color: #1E40AF; line-height: 1.6; margin-bottom: 20px;
            display: flex; gap: 10px;
        }
        .legal-banner i { flex-shrink: 0; font-size: 18px; margin-top: 1px; }

        /* ── Form fields ── */
        .field { margin-bottom: 20px; }
        .field label {
            display: block; font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--c-secondary); margin-bottom: 7px;
        }
        .field label span.req { color: var(--c-accent); margin-left: 2px; }
        select, textarea, input[type=text], input[type=email] {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--c-border);
            border-radius: 10px;
            font-family: inherit; font-size: 15px; color: var(--c-primary);
            background: #fff; outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        select:focus, textarea:focus, input:focus {
            border-color: var(--c-accent);
            box-shadow: 0 0 0 3px rgba(239,127,27,.12);
        }
        textarea { resize: vertical; min-height: 120px; }
        .char-count {
            text-align: right; font-size: 12px; color: #aaa;
            margin-top: 5px; transition: color .2s;
        }
        .char-count.warn { color: var(--c-accent); }
        .char-count.ok   { color: #10B981; }

        /* ── Toggle anónimo ── */
        .anon-toggle {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px;
            background: var(--c-soft); border-radius: 10px; border: 1px solid rgba(239,127,27,.2);
            margin-bottom: 20px; gap: 16px;
        }
        .anon-toggle-label { font-size: 14px; font-weight: 600; color: var(--c-primary); }
        .anon-toggle-label small { display: block; font-size: 12px; color: #888; font-weight: 400; margin-top: 2px; }
        .toggle-switch { position: relative; width: 48px; height: 26px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; inset: 0; border-radius: 13px;
            background: #d1d5db; cursor: pointer; transition: background .2s;
        }
        .toggle-slider::before {
            content: ''; position: absolute;
            width: 20px; height: 20px; left: 3px; top: 3px;
            border-radius: 50%; background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
            transition: transform .2s;
        }
        .toggle-switch input:checked + .toggle-slider { background: var(--c-accent); }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(22px); }

        /* ── Identidad (oculto si anónimo) ── */
        #identity-fields { overflow: hidden; transition: max-height .3s ease, opacity .3s ease; }
        #identity-fields.hidden { max-height: 0; opacity: 0; pointer-events: none; }
        #identity-fields.visible { max-height: 400px; opacity: 1; }
        .encrypt-note {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: #6B7280; margin-top: 8px;
        }

        /* ── Upload ── */
        .upload-zone {
            border: 2px dashed var(--c-border); border-radius: 10px;
            padding: 24px; text-align: center; cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .upload-zone:hover { border-color: var(--c-accent); background: var(--c-soft); }
        .upload-zone input { display: none; }
        .upload-zone .upload-icon { font-size: 28px; color: var(--c-accent); display: block; margin-bottom: 8px; }
        .upload-zone .upload-text { font-size: 13px; color: #888; }
        .upload-zone .upload-cta { font-size: 14px; font-weight: 600; color: var(--c-accent); }
        #file-list { list-style: none; margin-top: 10px; }
        #file-list li {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--c-body); padding: 6px 0;
            border-bottom: 1px solid var(--c-border);
        }
        #file-list li:last-child { border-bottom: none; }

        /* ── Policy ── */
        .policy-box {
            background: #f7f6f4; border: 1px solid var(--c-border); border-radius: 10px;
            padding: 14px 16px; font-size: 13px; color: var(--c-body); line-height: 1.6;
            margin-bottom: 14px; max-height: 120px; overflow-y: auto;
        }
        .policy-check {
            display: flex; align-items: flex-start; gap: 10px;
            font-size: 14px; color: var(--c-body);
        }
        .policy-check input[type=checkbox] {
            width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0;
            accent-color: var(--c-accent); cursor: pointer;
        }

        /* ── Submit ── */
        .btn-submit {
            width: 100%; padding: 15px;
            background: var(--c-accent); color: #fff;
            border: none; border-radius: 12px;
            font-family: inherit; font-size: 16px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: transform .1s, box-shadow .15s, filter .15s;
            box-shadow: 0 4px 14px rgba(239,127,27,.35);
        }
        .btn-submit:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.04); box-shadow: 0 6px 20px rgba(239,127,27,.4); }
        .btn-submit:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        /* ── Modal confirmación ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(1,33,51,.55); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; z-index: 9999;
            opacity: 0; pointer-events: none; transition: opacity .25s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal {
            background: #fff; border-radius: 20px; padding: 36px 32px;
            max-width: 480px; width: 100%; text-align: center;
            box-shadow: 0 20px 60px rgba(1,33,51,.2);
            transform: translateY(20px); transition: transform .25s;
        }
        .modal-overlay.open .modal { transform: translateY(0); }
        .modal-icon { font-size: 48px; color: #10B981; margin-bottom: 16px; }
        .modal-title { font-size: 22px; font-weight: 700; color: var(--c-primary); margin-bottom: 8px; }
        .modal-sub { font-size: 14px; color: #888; margin-bottom: 20px; line-height: 1.5; }
        .modal-code {
            font-family: monospace; font-size: 26px; font-weight: 700;
            color: var(--c-primary); letter-spacing: 3px;
            background: var(--c-soft); border-radius: 10px; padding: 14px 24px;
            margin-bottom: 8px; display: inline-block;
        }
        .modal-code-note { font-size: 12px; color: #888; margin-bottom: 24px; }
        .modal-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
        .btn-modal-primary {
            padding: 12px 22px; background: var(--c-accent); color: #fff;
            border: none; border-radius: 10px; font-family: inherit; font-size: 14px; font-weight: 700;
            cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-modal-ghost {
            padding: 12px 22px; background: var(--c-soft); color: var(--c-secondary);
            border: 1px solid rgba(1,33,51,.12); border-radius: 10px; font-family: inherit;
            font-size: 14px; cursor: pointer; text-decoration: none;
        }

        /* ── Copy button ── */
        .copy-btn {
            background: none; border: none; color: var(--c-teal); cursor: pointer;
            font-size: 13px; display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 8px; border-radius: 6px; transition: background .15s;
        }
        .copy-btn:hover { background: rgba(0,122,150,.08); }
    </style>
</head>
<body>

<header class="site-header">
    <div class="brand">
        <div>
            <div class="brand-name"><?= htmlspecialchars($config['company_name']) ?></div>
            <div class="brand-sub">Canal de Denuncias</div>
        </div>
    </div>
    <a href="track.php?empresa=<?= $company_id ?>" class="track-link">
        <i class="ph ph-magnifying-glass"></i> Consultar estado
    </a>
</header>

<div class="page">

    <!-- Bloque legal -->
    <div class="legal-banner">
        <i class="ph ph-scales"></i>
        <span><?= $legal_text ?></span>
    </div>

    <form id="complaint-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="company_id" value="<?= $company_id ?>">

        <!-- Tipo de denuncia -->
        <div class="card">
            <div class="card-title">
                <div class="icon-wrap"><i class="ph ph-warning-circle"></i></div>
                <span>¿Qué quieres denunciar?</span>
            </div>
            <p class="card-sub">Selecciona el tipo de situación que describes.</p>

            <div class="field">
                <label>Tipo de denuncia <span class="req">*</span></label>
                <select name="type" id="type" required>
                    <option value="">— Selecciona una categoría —</option>
                    <?php foreach ($types as $val => $label): ?>
                    <option value="<?= $val ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Descripción detallada <span class="req">*</span></label>
                <textarea
                    name="description"
                    id="description"
                    placeholder="Describe los hechos con el mayor detalle posible: quién, qué, cuándo, dónde. Mínimo 100 caracteres."
                    minlength="100"
                    required
                    oninput="updateCharCount(this)"
                ></textarea>
                <div class="char-count" id="char-count">0 / mín. 100 caracteres</div>
            </div>
        </div>

        <!-- Anonimato -->
        <?php if ($allow_anon): ?>
        <div class="card">
            <div class="card-title">
                <div class="icon-wrap"><i class="ph ph-user-circle-dashed"></i></div>
                <span>Identificación</span>
            </div>

            <div class="anon-toggle">
                <div class="anon-toggle-label">
                    Mantenerme anónimo/a
                    <small>Tu identidad no quedará registrada en ningún momento.</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="is_anonymous" id="is-anon" value="1" checked
                           onchange="toggleIdentity(this)">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div id="identity-fields" class="hidden">
                <div class="field">
                    <label>Nombre <span class="req">*</span></label>
                    <input type="text" name="name" id="name" placeholder="Tu nombre completo" autocomplete="off">
                </div>
                <div class="field">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" id="email" placeholder="para recibir actualizaciones" autocomplete="off">
                    <div class="encrypt-note">
                        <i class="ph ph-lock-key" style="font-size:14px;color:var(--c-teal)"></i>
                        Tus datos se cifrarán con AES-256 antes de guardarse.
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="is_anonymous" value="1">
        <?php endif; ?>

        <!-- Evidencias -->
        <div class="card">
            <div class="card-title">
                <div class="icon-wrap"><i class="ph ph-paperclip"></i></div>
                <span>Evidencias (opcional)</span>
            </div>
            <p class="card-sub">Adjunta capturas, documentos o archivos que respalden tu denuncia. Máx. 10 MB en total.</p>

            <div class="upload-zone" onclick="document.getElementById('evidence-input').click()" id="upload-zone">
                <input type="file" name="evidence[]" id="evidence-input" multiple
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.mp4,.webm"
                       onchange="handleFiles(this.files)">
                <i class="ph ph-cloud-arrow-up upload-icon"></i>
                <div class="upload-cta">Haz clic para adjuntar archivos</div>
                <div class="upload-text">PDF, imágenes, Word, vídeo · Máx. 20 MB por archivo</div>
            </div>
            <ul id="file-list"></ul>
        </div>

        <!-- Política y aceptación -->
        <div class="card">
            <div class="card-title">
                <div class="icon-wrap"><i class="ph ph-shield-check"></i></div>
                <span>Política del canal</span>
            </div>
            <?php if ($policy_text): ?>
            <div class="policy-box"><?= nl2br(htmlspecialchars($policy_text)) ?></div>
            <?php endif; ?>

            <label class="policy-check">
                <input type="checkbox" name="policy_accepted" id="policy-check" value="1" required>
                <span>He leído y acepto la política del canal de denuncias y confirmo que la información proporcionada es veraz.</span>
            </label>
        </div>

        <!-- Botón envío -->
        <button type="submit" class="btn-submit" id="submit-btn" disabled>
            <i class="ph ph-paper-plane-tilt"></i>
            Enviar denuncia de forma segura
        </button>
    </form>
</div>

<!-- Modal de confirmación -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal">
        <div class="modal-icon"><i class="ph ph-check-circle"></i></div>
        <div class="modal-title">Denuncia registrada</div>
        <p class="modal-sub">Tu denuncia ha sido enviada de forma segura. Guarda este código:</p>
        <div class="modal-code" id="modal-code">VLD-0000-XXXX</div>
        <div class="modal-code-note">
            Es la única forma de hacer seguimiento.
            <button class="copy-btn" onclick="copyCode()">
                <i class="ph ph-copy"></i> Copiar
            </button>
        </div>
        <div class="modal-actions">
            <a href="track.php?empresa=<?= $company_id ?>" id="modal-track-btn"
               class="btn-modal-primary">
                <i class="ph ph-magnifying-glass"></i> Seguimiento
            </a>
            <a href="javascript:location.reload()" class="btn-modal-ghost">
                Volver al inicio
            </a>
        </div>
    </div>
</div>

<script>
// ── Contador de caracteres ──
function updateCharCount(ta) {
    const len = ta.value.length;
    const el  = document.getElementById('char-count');
    el.textContent = len + ' / mín. 100 caracteres';
    el.className = 'char-count ' + (len === 0 ? '' : len < 100 ? 'warn' : 'ok');
    checkSubmit();
}

// ── Toggle identidad ──
function toggleIdentity(cb) {
    const fields = document.getElementById('identity-fields');
    const name   = document.getElementById('name');
    const email  = document.getElementById('email');
    if (cb.checked) {
        fields.classList.replace('visible','hidden');
        if (name) name.required = false;
    } else {
        fields.classList.replace('hidden','visible');
        if (name) name.required = true;
    }
    checkSubmit();
}

// ── Habilitar submit ──
function checkSubmit() {
    const type    = document.getElementById('type').value;
    const desc    = document.getElementById('description').value;
    const policy  = document.getElementById('policy-check').checked;
    const anonCb  = document.getElementById('is-anon');
    const isAnon  = anonCb ? anonCb.checked : true;
    let nameOk    = true;
    if (!isAnon) {
        const name = document.getElementById('name');
        nameOk = name && name.value.trim().length > 0;
    }
    const ok = type && desc.length >= 100 && policy && nameOk;
    document.getElementById('submit-btn').disabled = !ok;
}

document.getElementById('type').addEventListener('change', checkSubmit);
document.getElementById('policy-check').addEventListener('change', checkSubmit);

// ── Archivos ──
let totalSize = 0;
function handleFiles(files) {
    const list = document.getElementById('file-list');
    list.innerHTML = '';
    totalSize = 0;
    Array.from(files).forEach(f => {
        totalSize += f.size;
        const li = document.createElement('li');
        li.innerHTML = `<i class="ph ph-file" style="color:var(--c-accent)"></i>
            <span>${escapeHtml(f.name)}</span>
            <span style="color:#aaa;margin-left:auto">${(f.size/1024/1024).toFixed(1)} MB</span>`;
        list.appendChild(li);
    });
    if (totalSize > 10 * 1024 * 1024) {
        list.innerHTML += '<li style="color:#B91C1C"><i class="ph ph-warning"></i> Total supera 10 MB — algunos archivos pueden rechazarse</li>';
    }
}
function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Submit AJAX ──
document.getElementById('complaint-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-duotone" style="animation:spin 1s linear infinite"></i> Enviando…';

    const data = new FormData(this);

    try {
        const res  = await fetch('submit.php', { method: 'POST', body: data });
        const json = await res.json();

        if (json.ok) {
            showModal(json.reference_code);
        } else {
            alert('Error: ' + json.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Enviar denuncia de forma segura';
        }
    } catch (err) {
        alert('Error de red. Inténtalo de nuevo.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Enviar denuncia de forma segura';
    }
});

function showModal(code) {
    document.getElementById('modal-code').textContent = code;
    const trackBtn = document.getElementById('modal-track-btn');
    trackBtn.href = 'track.php?empresa=<?= $company_id ?>&code=' + encodeURIComponent(code);
    document.getElementById('modal-overlay').classList.add('open');
}

function copyCode() {
    const code = document.getElementById('modal-code').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="ph ph-check"></i> Copiado';
        setTimeout(() => { btn.innerHTML = '<i class="ph ph-copy"></i> Copiar'; }, 2000);
    });
}

// Spin animation
const st = document.createElement('style');
st.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(st);
</script>
</body>
</html>
