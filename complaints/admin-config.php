<?php
/**
 * complaints/admin-config.php — Configuración del Canal de Denuncias
 * Acceso: SOLO el dueño de la empresa (usuarios.id === company_id)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';

date_default_timezone_set('Europe/Madrid');

// ── Auto-migración: garantizar que canal_slug exista ─────────────────────────
// Si run_migration_paso8.php no se ejecutó todavía, la columna puede faltar y
// cualquier INSERT/UPDATE/SELECT que la referencia provoca un error fatal.
(function() use ($conn) {
    $chk = $conn->query("SHOW COLUMNS FROM complaint_channel_config LIKE 'canal_slug'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query(
            "ALTER TABLE complaint_channel_config
             ADD COLUMN canal_slug VARCHAR(60) NULL UNIQUE AFTER company_id,
             ADD INDEX idx_canal_slug (canal_slug)"
        );
    }
})();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$company_id = $user_id; // El dueño de la empresa ES su propio usuarios.id

// Obtener datos de la empresa
$stmt = $conn->prepare("SELECT nombre, empresa, country FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res_u   = stmt_get_result($stmt);
$stmt->close();

if (!$res_u || $res_u->num_rows === 0) {
    http_response_code(403); exit('Acceso denegado');
}
$empresa = $res_u->fetch_assoc();

// Config actual del canal
$config = get_company_config($conn, $company_id);

// Obtener usuarios del equipo para designar responsable
$stmt = $conn->prepare("
    SELECT id, nombre_persona AS nombre, cargo, correo
    FROM equipo
    WHERE usuario_id = ?
    ORDER BY nombre_persona ASC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res_eq = stmt_get_result($stmt);
$stmt->close();

$equipo_members = [];
while ($m = $res_eq->fetch_assoc()) {
    $equipo_members[] = $m;
}

// También pueden ser responsables otros usuarios (company_admin de este mismo tenant)
$stmt = $conn->prepare("SELECT id, nombre, correo FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res_own = stmt_get_result($stmt);
$stmt->close();
$owner_user = ($res_own && $res_own->num_rows > 0) ? $res_own->fetch_assoc() : null;

// ─────────────────────────────────────────────────────────────────────────────
// SAVE
// ─────────────────────────────────────────────────────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $country_val           = in_array($_POST['country'] ?? '', ['ES','CO']) ? $_POST['country'] : 'ES';
        $is_anon_allowed       = !empty($_POST['is_anonymous_allowed']) ? 1 : 0;
        $responsible_user_id   = !empty($_POST['responsible_user_id']) ? (int)$_POST['responsible_user_id'] : null;
        $channel_policy_text   = trim($_POST['channel_policy_text'] ?? '');
        $receipt_days          = max(1, (int)($_POST['receipt_days'] ?? 7));
        $resolution_days       = max(1, (int)($_POST['resolution_days'] ?? 90));
        $notification_email    = trim($_POST['notification_email'] ?? '');
        $do_activate           = !empty($_POST['activate']);

        // Validar activación
        if ($do_activate && !$responsible_user_id) {
            $error_msg = 'Debes designar un responsable antes de activar el canal.';
        } else {
            $is_active = $do_activate ? 1 : (int)($config['is_active'] ?? 0);

            if (!empty($_POST['deactivate'])) {
                $is_active = 0;
            }

            if ($notification_email && !filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = 'El correo de notificación no es válido.';
            } else {
                // Generar slug si el canal no lo tiene todavía
                $canal_slug = $config['canal_slug'] ?? null;
                if (empty($canal_slug)) {
                    $canal_slug = generate_canal_slug($conn, $empresa['empresa'] ?? 'canal');
                }

                if ($config) {
                    // UPDATE — incluye canal_slug si aún no estaba guardado
                    $stmt = $conn->prepare("
                        UPDATE complaint_channel_config SET
                            country = ?, is_anonymous_allowed = ?, responsible_user_id = ?,
                            channel_policy_text = ?, receipt_days = ?, resolution_days = ?,
                            notification_email = ?, is_active = ?,
                            canal_slug = COALESCE(canal_slug, ?),
                            updated_at = NOW()
                        WHERE company_id = ?
                    ");
                    $stmt->bind_param(
                        "siisiisisi",
                        $country_val, $is_anon_allowed, $responsible_user_id,
                        $channel_policy_text, $receipt_days, $resolution_days,
                        $notification_email, $is_active,
                        $canal_slug, $company_id
                    );
                } else {
                    // INSERT — incluye canal_slug desde el principio
                    $stmt = $conn->prepare("
                        INSERT INTO complaint_channel_config
                            (company_id, canal_slug, country, is_anonymous_allowed, responsible_user_id,
                             channel_policy_text, receipt_days, resolution_days,
                             notification_email, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "issiisiisi",
                        $company_id, $canal_slug, $country_val, $is_anon_allowed, $responsible_user_id,
                        $channel_policy_text, $receipt_days, $resolution_days,
                        $notification_email, $is_active
                    );
                }
                $stmt->execute();
                $stmt->close();

                // Actualizar también country en usuarios
                $stmt = $conn->prepare("UPDATE usuarios SET country = ? WHERE id = ?");
                $stmt->bind_param("si", $country_val, $company_id);
                $stmt->execute();
                $stmt->close();

                $success_msg = $is_active
                    ? '✅ Canal activado y configuración guardada.'
                    : '✅ Configuración guardada.';

                // Recargar config
                $config = get_company_config($conn, $company_id);
            }
        }
    }
}

$csrf = getCsrfToken();

// Valores actuales del formulario
$f_country          = $config['country']              ?? $empresa['country'] ?? 'ES';
$f_is_anon          = $config['is_anonymous_allowed'] ?? 1;
$f_responsible      = $config['responsible_user_id']  ?? '';
$f_policy           = $config['channel_policy_text']  ?? '';
$f_receipt_days     = $config['receipt_days']          ?? 7;
$f_resolution_days  = $config['resolution_days']       ?? 90;
$f_notification_email = $config['notification_email'] ?? '';
$f_is_active        = (int)($config['is_active']      ?? 0);
$f_canal_slug       = $config['canal_slug']            ?? null;

// URL pública del canal (para mostrar y compartir)
$_scheme          = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host            = $_SERVER['HTTP_HOST'] ?? 'app.valirica.com';
$public_canal_url = $f_canal_slug
    ? "{$_scheme}://{$_host}/complaints/form.php?canal=" . urlencode($f_canal_slug)
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Canal de Denuncias — Valírica</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Georgia, serif; background: #f0eeeb; color: #012133; }
        .top-bar {
            background: #012133; color: #fff; padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar h1 { font-size: 18px; }
        .top-bar a { color: #EF7F1B; text-decoration: none; font-size: 13px; }
        .container { max-width: 780px; margin: 0 auto; padding: 32px 20px; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700;
            margin-bottom: 24px;
        }
        .badge-active   { background: #D1FAE5; color: #065F46; }
        .badge-inactive { background: #F3F4F6; color: #6B7280; }

        .card {
            background: #fff; border-radius: 14px; border: 1px solid #e8e6e3;
            overflow: hidden; margin-bottom: 24px;
        }
        .card-header {
            padding: 18px 24px; border-bottom: 1px solid #e8e6e3;
            background: #f7f6f4;
        }
        .card-header h2 { font-size: 15px; color: #012133; }
        .card-header p { font-size: 13px; color: #7a7977; margin-top: 3px; }
        .card-body { padding: 24px; }

        .form-group { margin-bottom: 20px; }
        label.field-label {
            display: block; font-size: 12px; text-transform: uppercase;
            letter-spacing: .6px; color: #7a7977; margin-bottom: 6px;
        }
        input[type=text], input[type=email], input[type=number], select, textarea {
            width: 100%; padding: 11px 14px; border: 1px solid #e8e6e3;
            border-radius: 9px; font-size: 14px; font-family: Georgia, serif;
            outline: none; color: #012133; background: #fff;
        }
        input:focus, select:focus, textarea:focus { border-color: #EF7F1B; }
        textarea { resize: vertical; min-height: 100px; }

        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0; border-bottom: 1px solid #f0eeeb;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 14px; }
        .toggle-label small { display: block; font-size: 12px; color: #7a7977; margin-top: 2px; }
        input[type=checkbox] {
            width: 20px; height: 20px; cursor: pointer; accent-color: #EF7F1B;
        }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media(max-width:540px) { .two-col { grid-template-columns: 1fr; } }

        .policy-preview {
            background: #f7f6f4; border: 1px solid #e8e6e3; border-radius: 9px;
            padding: 14px; font-size: 13px; color: #3d3c3b; line-height: 1.7;
            min-height: 80px; white-space: pre-wrap; word-break: break-word;
        }

        .btn-save {
            padding: 13px 28px; background: #012133; color: #fff;
            border: none; border-radius: 10px; font-size: 15px;
            font-family: Georgia, serif; font-weight: 700; cursor: pointer;
        }
        .btn-save:hover { background: #023a55; }
        .btn-activate {
            padding: 13px 28px; background: #EF7F1B; color: #fff;
            border: none; border-radius: 10px; font-size: 15px;
            font-family: Georgia, serif; font-weight: 700; cursor: pointer;
        }
        .btn-activate:hover { background: #d96e0e; }
        .btn-deactivate {
            padding: 13px 28px; background: #FEE2E2; color: #B91C1C;
            border: 1px solid #FECACA; border-radius: 10px; font-size: 14px;
            font-family: Georgia, serif; cursor: pointer;
        }
        .btn-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }

        .alert-success {
            background: #D1FAE5; border: 1px solid #A7F3D0; border-radius: 10px;
            padding: 14px 18px; color: #065F46; font-size: 14px; margin-bottom: 20px;
        }
        .alert-error {
            background: #FEE2E2; border: 1px solid #FECACA; border-radius: 10px;
            padding: 14px 18px; color: #B91C1C; font-size: 14px; margin-bottom: 20px;
        }

        /* Wizard (sin config previa) */
        .wizard-banner {
            background: #FFF7ED; border: 2px dashed #FED7AA; border-radius: 14px;
            padding: 32px; text-align: center; margin-bottom: 28px;
        }
        .wizard-banner h2 { font-size: 20px; margin-bottom: 8px; }
        .wizard-banner p { font-size: 14px; color: #92400E; line-height: 1.6; }

        .legal-note {
            background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 10px;
            padding: 14px 18px; font-size: 13px; color: #1E40AF; line-height: 1.6;
            margin-bottom: 24px;
        }

        /* URL pública del canal */
        .public-url-box {
            background: #fff; border: 1px solid #e8e6e3; border-radius: 12px;
            padding: 18px 20px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .public-url-box .url-label {
            font-size: 11px; text-transform: uppercase; letter-spacing: .6px;
            color: #9a9896; flex-shrink: 0;
        }
        .public-url-box input[type=text] {
            flex: 1; min-width: 200px;
            padding: 9px 14px; border: 1px solid #e8e6e3; border-radius: 8px;
            font-size: 13px; font-family: monospace; color: #012133;
            background: #f7f6f4; cursor: default;
        }
        .btn-copy {
            padding: 9px 16px; background: #012133; color: #fff;
            border: none; border-radius: 8px; font-size: 13px;
            font-family: Georgia, serif; cursor: pointer; white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-copy:hover { background: #023a55; }
        .btn-copy.copied { background: #065F46; }
    </style>
</head>
<body>

<div class="top-bar">
    <div>
        <h1>Configuración del Canal de Denuncias</h1>
    </div>
    <?php if ($config && $config['is_active']): ?>
    <a href="manage.php">← Ver denuncias</a>
    <?php endif; ?>
</div>

<div class="container">

    <?php if ($success_msg): ?>
        <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (!$config): ?>
    <div class="wizard-banner">
        <h2>Activa el Canal de Denuncias</h2>
        <p>
            Tu empresa todavía no tiene el canal configurado.<br>
            Completa el formulario a continuación y pulsa <strong>Activar Canal</strong>.
        </p>
    </div>
    <?php else: ?>
    <span class="status-badge <?= $f_is_active ? 'badge-active' : 'badge-inactive' ?>">
        <?= $f_is_active ? '● Canal activo' : '○ Canal inactivo' ?>
    </span>

    <?php if ($public_canal_url): ?>
    <div class="public-url-box">
        <span class="url-label">🔗 URL pública del canal</span>
        <input type="text" id="canal-url-input"
               value="<?= htmlspecialchars($public_canal_url) ?>"
               readonly onclick="this.select()">
        <button class="btn-copy" id="btn-copy-canal" onclick="copyCanalUrl()">
            Copiar enlace
        </button>
    </div>
    <script>
    function copyCanalUrl() {
        const input = document.getElementById('canal-url-input');
        const btn   = document.getElementById('btn-copy-canal');
        navigator.clipboard.writeText(input.value).then(function() {
            btn.textContent = '✓ Copiado';
            btn.classList.add('copied');
            setTimeout(function() {
                btn.textContent = 'Copiar enlace';
                btn.classList.remove('copied');
            }, 2500);
        }).catch(function() {
            input.select();
            document.execCommand('copy');
        });
    }
    </script>
    <?php endif; ?>

    <?php endif; ?>

    <div class="legal-note">
        🇪🇸 <strong>España:</strong> Ley 2/2023 exige canal confidencial, acuse de recibo en 7 días hábiles y resolución en 90 días.&nbsp;
        🇨🇴 <strong>Colombia:</strong> Ley 1010/2006 y Resolución 3461/2025 exigen investigación en 65 días naturales con acuse inmediato.
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- BLOQUE 1: País y responsable -->
        <div class="card">
            <div class="card-header">
                <h2>Configuración básica</h2>
                <p>Define el país de operación y el responsable del canal.</p>
            </div>
            <div class="card-body">
                <div class="two-col">
                    <div class="form-group">
                        <label class="field-label">País de operación *</label>
                        <select name="country" onchange="updateLegalHints(this.value)">
                            <option value="ES" <?= $f_country === 'ES' ? 'selected' : '' ?>>🇪🇸 España</option>
                            <option value="CO" <?= $f_country === 'CO' ? 'selected' : '' ?>>🇨🇴 Colombia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Correo de notificación</label>
                        <input type="email" name="notification_email"
                            value="<?= htmlspecialchars($f_notification_email) ?>"
                            placeholder="responsable@empresa.com">
                    </div>
                </div>

                <div class="form-group">
                    <label class="field-label">Responsable del canal *</label>
                    <select name="responsible_user_id">
                        <option value="">— Sin designar —</option>
                        <?php if ($owner_user): ?>
                        <option value="<?= $owner_user['id'] ?>"
                            <?= (string)$f_responsible === (string)$owner_user['id'] ? 'selected' : '' ?>>
                            👤 <?= htmlspecialchars($owner_user['nombre']) ?> (Admin empresa)
                        </option>
                        <?php endif; ?>
                        <?php foreach ($equipo_members as $m): ?>
                        <option value="<?= $m['id'] ?>"
                            <?= (string)$f_responsible === (string)$m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nombre']) ?>
                            <?php if ($m['cargo']): ?> — <?= htmlspecialchars($m['cargo']) ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size:12px;color:#9a9896;margin-top:6px;">
                        Solo el responsable designado puede ver la identidad de denunciantes no anónimos.
                    </p>
                </div>
            </div>
        </div>

        <!-- BLOQUE 2: Plazos -->
        <div class="card">
            <div class="card-header">
                <h2>Plazos legales</h2>
                <p id="legal-hint-text">
                    <?= $f_country === 'ES'
                        ? 'España: acuse en días hábiles, resolución en días naturales.'
                        : 'Colombia: acuse inmediato, resolución en días naturales.' ?>
                </p>
            </div>
            <div class="card-body">
                <div class="two-col">
                    <div class="form-group">
                        <label class="field-label" id="receipt-label">
                            <?= $f_country === 'ES' ? 'Plazo acuse (días hábiles)' : 'Plazo acuse (días naturales)' ?>
                        </label>
                        <input type="number" name="receipt_days" min="1" max="30"
                               value="<?= (int)$f_receipt_days ?>" id="receipt-days-input">
                    </div>
                    <div class="form-group">
                        <label class="field-label">Plazo resolución (días naturales)</label>
                        <input type="number" name="resolution_days" min="1" max="365"
                               value="<?= (int)$f_resolution_days ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- BLOQUE 3: Política y opciones -->
        <div class="card">
            <div class="card-header">
                <h2>Política del canal</h2>
                <p>Texto que verá el denunciante antes de enviar. Previsualización en tiempo real.</p>
            </div>
            <div class="card-body">
                <div class="toggle-row">
                    <div class="toggle-label">
                        Permitir denuncias anónimas
                        <small>El denunciante podrá no identificarse.</small>
                    </div>
                    <input type="checkbox" name="is_anonymous_allowed" value="1"
                           <?= $f_is_anon ? 'checked' : '' ?>>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label class="field-label">Texto de política</label>
                    <textarea name="channel_policy_text" id="policy-text"
                              oninput="updatePreview()"
                              placeholder="Escribe aquí la política del canal (se mostrará al denunciante antes de enviar)…"><?= htmlspecialchars($f_policy) ?></textarea>
                </div>

                <label class="field-label">Vista previa</label>
                <div class="policy-preview" id="policy-preview">
                    <?= $f_policy ? htmlspecialchars($f_policy) : '<span style="color:#9a9896;">El texto de política aparecerá aquí…</span>' ?>
                </div>
            </div>
        </div>

        <!-- BOTONES -->
        <div class="btn-row">
            <button type="submit" name="save" class="btn-save">Guardar configuración</button>

            <?php if (!$f_is_active): ?>
            <button type="submit" name="activate" value="1" class="btn-activate"
                    onclick="return confirm('¿Activar el Canal de Denuncias? Asegúrate de tener un responsable designado.')">
                ▶ Activar Canal de Denuncias
            </button>
            <?php else: ?>
            <button type="submit" name="deactivate" value="1" class="btn-deactivate"
                    onclick="return confirm('¿Desactivar el canal? Los empleados no podrán enviar nuevas denuncias.')">
                ■ Desactivar canal
            </button>
            <?php endif; ?>
        </div>
    </form>

</div>

<script>
function updatePreview() {
    const text = document.getElementById('policy-text').value;
    const preview = document.getElementById('policy-preview');
    preview.textContent = text || '';
    if (!text) {
        preview.innerHTML = '<span style="color:#9a9896;">El texto de política aparecerá aquí…</span>';
    }
}

function updateLegalHints(country) {
    const hint = document.getElementById('legal-hint-text');
    const receiptLabel = document.getElementById('receipt-label');
    const receiptInput = document.getElementById('receipt-days-input');
    if (country === 'ES') {
        hint.textContent = 'España: acuse en días hábiles, resolución en días naturales.';
        receiptLabel.textContent = 'Plazo acuse (días hábiles)';
        receiptInput.value = 7;
    } else {
        hint.textContent = 'Colombia: acuse inmediato, resolución en días naturales.';
        receiptLabel.textContent = 'Plazo acuse (días naturales)';
        receiptInput.value = 1;
    }
}
</script>

</body>
</html>
