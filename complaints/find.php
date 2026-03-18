<?php
/**
 * complaints/find.php — Punto de entrada público al Canal de Denuncias
 *
 * Sin autenticación. El usuario introduce el código de canal (slug) de
 * su empresa y es redirigido al formulario correspondiente.
 * También enlaza al seguimiento de una denuncia existente.
 */

require_once __DIR__ . '/../config.php';

date_default_timezone_set('Europe/Madrid');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $canal_input = strtolower(trim($_POST['canal'] ?? ''));

        if (empty($canal_input)) {
            $error = 'Introduce el código de canal de tu empresa.';
        } elseif (!preg_match('/^[a-z0-9\-]{2,60}$/', $canal_input)) {
            $error = 'Código de canal no válido. Usa solo letras, números y guiones.';
        } else {
            // Buscar el slug en la BD
            $stmt = $conn->prepare(
                "SELECT company_id, is_active FROM complaint_channel_config
                 WHERE canal_slug = ? LIMIT 1"
            );
            $stmt->bind_param("s", $canal_input);
            $stmt->execute();
            $res = stmt_get_result($stmt);
            $stmt->close();

            if (!$res || $res->num_rows === 0) {
                $error = 'No se encontró ningún canal con ese código. Verifica con tu empresa.';
            } else {
                $row = $res->fetch_assoc();
                if (!(int)$row['is_active']) {
                    $error = 'Este canal de denuncias no está activo en este momento.';
                } else {
                    // Redirigir al formulario
                    header('Location: form.php?canal=' . urlencode($canal_input));
                    exit;
                }
            }
        }
    }
}

$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canal de Denuncias — Valírica</title>
    <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --c-primary:   #012133;
            --c-secondary: #184656;
            --c-teal:      #007a96;
            --c-accent:    #EF7F1B;
            --font: "gelica", system-ui, -apple-system, sans-serif;
        }

        html, body {
            height: 100%;
            font-family: var(--font);
            -webkit-font-smoothing: antialiased;
        }

        .vl-bg {
            min-height: 100vh;
            background:
                radial-gradient(ellipse at 75% 5%,  rgba(0,122,150,0.38) 0%, transparent 52%),
                radial-gradient(ellipse at 10% 95%, rgba(239,127,27,0.20) 0%, transparent 50%),
                linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 70%, #0d3a4f 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px 48px;
        }

        /* Logo */
        .logo-wrap {
            position: relative;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .logo-wrap::before {
            content: '';
            position: absolute; inset: -10px;
            border-radius: 50%;
            background: rgba(0,122,150,0.12);
            border: 1px solid rgba(0,122,150,0.22);
        }
        .logo-wrap img {
            width: 70px; height: 70px;
            border-radius: 50%; object-fit: cover;
            position: relative; z-index: 1;
        }

        .wordmark {
            font-size: 11px; font-weight: 700; letter-spacing: 3.5px;
            text-transform: uppercase; color: rgba(255,255,255,0.38);
            margin-bottom: 10px; text-align: center;
        }

        /* Card */
        .card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            width: 100%; max-width: 460px;
            padding: 40px 36px 36px;
            margin-top: 24px;
        }

        /* Shield icon */
        .icon-shield {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: rgba(239,127,27,0.14);
            border: 1px solid rgba(239,127,27,0.28);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 20px;
        }

        h1 {
            font-size: 22px; font-weight: 800; color: #fff;
            line-height: 1.2; letter-spacing: -0.3px; margin-bottom: 8px;
        }
        .sub {
            font-size: 14px; color: rgba(255,255,255,0.52);
            line-height: 1.6; margin-bottom: 28px;
        }

        /* Form */
        .field-label {
            display: block; font-size: 11px; font-weight: 700;
            letter-spacing: 1.8px; text-transform: uppercase;
            color: rgba(255,255,255,0.45); margin-bottom: 8px;
        }
        .field-input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: #fff; font-size: 15px; font-family: var(--font);
            outline: none; letter-spacing: 0.5px;
            transition: border-color 0.2s;
        }
        .field-input::placeholder { color: rgba(255,255,255,0.25); }
        .field-input:focus { border-color: var(--c-accent); }

        .btn-primary {
            width: 100%; margin-top: 20px;
            padding: 14px;
            background: linear-gradient(135deg, var(--c-accent), #d96b0a);
            color: #fff; font-size: 15px; font-weight: 700;
            font-family: var(--font);
            border: none; border-radius: 12px; cursor: pointer;
            box-shadow: 0 4px 20px rgba(239,127,27,0.35);
            transition: opacity 0.15s, transform 0.12s;
        }
        .btn-primary:hover { opacity: 0.9; transform: scale(0.99); }

        /* Error */
        .error-box {
            background: rgba(185,28,28,0.15);
            border: 1px solid rgba(252,165,165,0.35);
            border-radius: 10px; padding: 13px 16px;
            color: #FCA5A5; font-size: 14px; margin-bottom: 20px;
            line-height: 1.5;
        }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 28px 0 20px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px; background: rgba(255,255,255,0.1);
        }
        .divider span { font-size: 12px; color: rgba(255,255,255,0.3); white-space: nowrap; }

        /* Secondary links */
        .link-secondary {
            display: block; text-align: center;
            padding: 13px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 12px;
            color: rgba(255,255,255,0.65); font-size: 14px;
            text-decoration: none; margin-bottom: 10px;
            transition: background 0.2s, color 0.2s;
        }
        .link-secondary:hover {
            background: rgba(255,255,255,0.09);
            color: #fff;
        }
        .link-secondary span { font-weight: 700; }

        /* Footer */
        .footer {
            margin-top: 32px; font-size: 12px;
            color: rgba(255,255,255,0.22); text-align: center;
        }
        .footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
        .footer a:hover { color: rgba(255,255,255,0.6); }

        /* Confidentiality note */
        .conf-note {
            margin-top: 16px; padding: 12px 14px;
            background: rgba(0,122,150,0.12);
            border: 1px solid rgba(0,122,150,0.25);
            border-radius: 10px;
            font-size: 12px; color: rgba(255,255,255,0.45);
            line-height: 1.6; text-align: center;
        }

        @media (max-width: 480px) {
            .card { padding: 28px 22px 24px; }
        }
    </style>
</head>
<body>
<div class="vl-bg">

    <div class="logo-wrap">
        <img src="https://app.valirica.com/uploads/logo-192.png" alt="Valírica">
    </div>
    <p class="wordmark">Valírica</p>

    <div class="card">
        <div class="icon-shield">🛡️</div>

        <h1>Canal de Denuncias</h1>
        <p class="sub">
            Introduce el código de canal de tu empresa para acceder al formulario
            confidencial de denuncia.
        </p>

        <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <label class="field-label" for="canal-input">Código de canal de tu empresa</label>
            <input
                id="canal-input"
                class="field-input"
                type="text"
                name="canal"
                placeholder="ej. nombre-de-tu-empresa"
                value="<?= htmlspecialchars(strtolower(trim($_POST['canal'] ?? ''))) ?>"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                maxlength="60"
                required
            >
            <button type="submit" class="btn-primary">
                Acceder al formulario →
            </button>
        </form>

        <p class="conf-note">
            🔒 Todas las comunicaciones son confidenciales y están cifradas.
            Tu identidad nunca será revelada sin tu consentimiento.
        </p>

        <div class="divider"><span>o también</span></div>

        <a href="track.php" class="link-secondary">
            🔍 <span>Consultar el estado</span> de una denuncia ya enviada
        </a>

        <a href="../index.php" class="link-secondary">
            ← Volver al inicio
        </a>
    </div>

    <p class="footer">
        © <?= date('Y') ?> Valírica ·
        <a href="mailto:soporte@valirica.com">soporte@valirica.com</a>
        · Canal de Denuncias protegido bajo Ley 2/2023 (ES) y Ley 1010/2006 (CO)
    </p>

</div>
</body>
</html>
