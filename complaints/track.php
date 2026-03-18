<?php
/**
 * complaints/track.php — Seguimiento público por código de referencia
 * Sin autenticación. Solo muestra estado y fechas genéricas.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helper.php';

date_default_timezone_set('Europe/Madrid');

$reference_code = strtoupper(trim($_REQUEST['code'] ?? ''));
$complaint      = null;
$error          = null;
$last_activity  = null;

if ($reference_code !== '') {
    // Validar formato VLD-YYYY-XXXX
    if (!preg_match('/^VLD-\d{4}-[A-Z0-9]{4}$/', $reference_code)) {
        $error = 'Código de referencia con formato inválido.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, status, created_at, receipt_deadline, resolution_deadline,
                   country, is_anonymous, priority
            FROM complaints
            WHERE reference_code = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $reference_code);
        $stmt->execute();
        $res = stmt_get_result($stmt);
        $stmt->close();

        if (!$res || $res->num_rows === 0) {
            $error = 'No se encontró ninguna denuncia con ese código.';
        } else {
            $complaint = $res->fetch_assoc();

            // Último movimiento (excluyendo datos sensibles)
            $stmt2 = $conn->prepare("
                SELECT action, created_at
                FROM complaint_activities
                WHERE complaint_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt2->bind_param("i", $complaint['id']);
            $stmt2->execute();
            $res2 = stmt_get_result($stmt2);
            $stmt2->close();
            if ($res2 && $res2->num_rows > 0) {
                $last_activity = $res2->fetch_assoc();
            }
        }
    }
}

// ─── Mapas de estado para mostrar al público ──────────────────────────────────
$status_label = [
    'recibida'   => 'Recibida',
    'en_tramite' => 'En trámite',
    'resuelta'   => 'Resuelta',
    'archivada'  => 'Archivada',
];

$status_next_step = [
    'recibida'   => 'El responsable del canal revisará tu denuncia y comenzará su instrucción en los próximos días.',
    'en_tramite' => 'Tu denuncia está siendo investigada por el responsable designado. Recibirás novedades cuando haya resolución.',
    'resuelta'   => 'El expediente ha sido resuelto. Si dejaste un correo de contacto, habrás recibido comunicación al respecto.',
    'archivada'  => 'El expediente ha sido archivado por el responsable del canal.',
];

$status_color = [
    'recibida'   => '#3B82F6',
    'en_tramite' => '#EF7F1B',
    'resuelta'   => '#10B981',
    'archivada'  => '#9CA3AF',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de denuncia — Valírica</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Georgia, serif;
            background: #f0eeeb;
            color: #012133;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(1,33,51,.09);
            border: 1px solid #e8e6e3;
            max-width: 560px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: #012133;
            padding: 32px 40px;
            text-align: center;
        }
        .card-header img { max-width: 130px; }
        .card-body { padding: 40px; }
        h1 { font-size: 22px; margin-bottom: 8px; color: #012133; }
        p.sub { font-size: 14px; color: #7a7977; margin-bottom: 28px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; color: #7a7977; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .6px; }
        input[type=text] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e8e6e3;
            border-radius: 10px;
            font-size: 15px;
            font-family: monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
            outline: none;
        }
        input[type=text]:focus { border-color: #EF7F1B; }
        .btn-primary {
            width: 100%;
            padding: 13px;
            background: #EF7F1B;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-family: Georgia, serif;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-primary:hover { background: #d96e0e; }
        .error-box {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-radius: 10px;
            padding: 14px 18px;
            color: #B91C1C;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .status-card {
            border-radius: 12px;
            border: 1px solid #e8e6e3;
            overflow: hidden;
            margin-top: 24px;
        }
        .status-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .status-label { font-size: 17px; font-weight: 700; }
        .status-body { padding: 20px; background: #f7f6f4; }
        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 6px 0;
            border-bottom: 1px solid #e8e6e3;
        }
        .meta-row:last-child { border-bottom: none; }
        .meta-key { color: #9a9896; }
        .meta-val { font-weight: 600; color: #012133; }
        .next-step {
            margin-top: 20px;
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            border-radius: 10px;
            padding: 16px 18px;
            font-size: 14px;
            color: #92400E;
            line-height: 1.6;
        }
        .disclaimer {
            margin-top: 28px;
            font-size: 12px;
            color: #9a9896;
            text-align: center;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <img src="https://valirica.com/uploads/logo-valirica.png" alt="Valírica">
    </div>
    <div class="card-body">
        <h1>Consulta tu denuncia</h1>
        <p class="sub">Introduce el código de referencia que recibiste al enviar tu denuncia.</p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <div class="form-group">
                <label>Código de referencia</label>
                <input
                    type="text"
                    name="code"
                    placeholder="VLD-2025-XXXX"
                    value="<?= htmlspecialchars($reference_code) ?>"
                    maxlength="13"
                    autocomplete="off"
                    required
                >
            </div>
            <button type="submit" class="btn-primary">Consultar estado →</button>
        </form>

        <?php if ($complaint): ?>
            <?php
                $st    = $complaint['status'];
                $color = $status_color[$st] ?? '#9CA3AF';
            ?>
            <div class="status-card">
                <div class="status-header" style="border-bottom: 3px solid <?= $color ?>;">
                    <div class="status-dot" style="background:<?= $color ?>;"></div>
                    <span class="status-label"><?= htmlspecialchars($status_label[$st] ?? ucfirst($st)) ?></span>
                </div>
                <div class="status-body">
                    <div class="meta-row">
                        <span class="meta-key">Código</span>
                        <span class="meta-val"><?= htmlspecialchars($reference_code) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">Fecha de recepción</span>
                        <span class="meta-val"><?= htmlspecialchars(date('d/m/Y', strtotime($complaint['created_at']))) ?></span>
                    </div>
                    <?php if ($last_activity): ?>
                    <div class="meta-row">
                        <span class="meta-key">Último movimiento</span>
                        <span class="meta-val"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($last_activity['created_at']))) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['resolution_deadline'] && $st !== 'resuelta' && $st !== 'archivada'): ?>
                    <div class="meta-row">
                        <span class="meta-key">Plazo de resolución</span>
                        <span class="meta-val"><?= htmlspecialchars(date('d/m/Y', strtotime($complaint['resolution_deadline']))) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="next-step">
                <strong>¿Qué ocurre ahora?</strong><br>
                <?= htmlspecialchars($status_next_step[$st] ?? '') ?>
            </div>
        <?php endif; ?>

        <p class="disclaimer">
            Este canal garantiza la confidencialidad de los datos.<br>
            Nunca se mostrarán datos personales en esta consulta pública.
        </p>
    </div>
</div>
</body>
</html>
