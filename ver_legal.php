<?php
/**
 * ver_legal.php
 * Visor de documentos legales con sustitución de variables dinámicas.
 * Requiere sesión activa de usuario empresa.
 */
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$allowed_docs = ['contrato-colombia', 'contrato-espana', 'cookies', 'privacidad', 'terminos'];
$doc = trim($_GET['doc'] ?? '');

if (!in_array($doc, $allowed_docs, true)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$file_path = __DIR__ . '/legal/' . $doc;
if (!is_file($file_path)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

// Obtener datos de la empresa para sustitución de variables
try {
    $stmt = $conn->prepare("
        SELECT u.empresa, u.nombre, u.apellido, u.email, u.fecha_registro,
               ci.ubicacion
        FROM   usuarios u
        LEFT JOIN cultura_ideal ci ON ci.usuario_id = u.id
        WHERE  u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $ud = stmt_get_result($stmt)->fetch_assoc() ?: [];
    $stmt->close();
} catch (\Throwable $e) {
    $ud = [];
}

// Sustitución de variables del contrato
$es_colombia = str_contains(strtolower($doc), 'colombia');

$vars = [
    '{{EMPRESA_CLIENTE}}'             => htmlspecialchars($ud['empresa']  ?? '', ENT_QUOTES, 'UTF-8'),
    '{{REPRESENTANTE}}'               => htmlspecialchars(trim(($ud['nombre'] ?? '') . ' ' . ($ud['apellido'] ?? '')), ENT_QUOTES, 'UTF-8'),
    '{{EMAIL_CLIENTE}}'               => htmlspecialchars($ud['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{EMAIL_FACTURACION_ADICIONAL}}' => htmlspecialchars($ud['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{FECHA_REGISTRO}}'              => !empty($ud['fecha_registro'])
                                             ? date('d/m/Y', strtotime($ud['fecha_registro']))
                                             : date('d/m/Y'),
    '{{FECHA_ACEPTACION}}'            => date('d/m/Y'),
    '{{PERIODO_PRUEBA}}'              => '15 días',
    '{{TIPO_PLAN}}'                   => 'Mensual',
    '{{NIT_CLIENTE}}'                 => '–',
    '{{CIF_CLIENTE}}'                 => '–',
    '{{PRECIO_PLAN}}'                 => $es_colombia ? '$20.000 COP + IVA/empleado/mes' : '€6/empleado/mes',
];

$contenido = file_get_contents($file_path);
$contenido = str_replace(array_keys($vars), array_values($vars), $contenido);

// ── Clasificación del documento ──────────────────────────────────────────
$is_contract   = in_array($doc, ['contrato-colombia', 'contrato-espana'], true);
$is_policy_doc = in_array($doc, ['cookies', 'privacidad', 'terminos'], true);
$needs_accept  = $is_contract || $is_policy_doc;

// ── Buscar registro en documentos + datos de auditoría ───────────────────
$doc_registro_id      = null;
$doc_registro_estado  = null;
$doc_fecha_aceptacion = null;
$doc_ip_aceptacion    = null;

if ($needs_accept) {
    try {
        $st_doc = $conn->prepare("
            SELECT id, estado, fecha_aceptacion, ip_aceptacion
            FROM   documentos
            WHERE  empresa_id    = ?
              AND  url_documento = ?
            LIMIT 1
        ");
        if ($st_doc) {
            $url_buscar = 'ver_legal.php?doc=' . $doc;
            $st_doc->bind_param("is", $user_id, $url_buscar);
            $st_doc->execute();
            $row_doc = stmt_get_result($st_doc)->fetch_assoc();
            if ($row_doc) {
                $doc_registro_id      = (int)$row_doc['id'];
                $doc_registro_estado  = $row_doc['estado'];
                $doc_fecha_aceptacion = $row_doc['fecha_aceptacion'];
                $doc_ip_aceptacion    = $row_doc['ip_aceptacion'];
            }
            $st_doc->close();
        }
    } catch (\Throwable $e) {
        error_log("ver_legal.php: doc lookup failed — " . $e->getMessage());
    }
}

// ── IP del cliente para mostrar en el formulario (antes de aceptar) ──────
$raw_ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$client_ip = trim(explode(',', $raw_ip)[0]);

$titulo_doc = match($doc) {
    'contrato-colombia' => 'Contrato de Servicios – Colombia',
    'contrato-espana'   => 'Contrato de Servicios – España',
    'cookies'           => 'Política de Cookies',
    'privacidad'        => 'Política de Privacidad',
    'terminos'          => 'Términos de Uso',
    default             => 'Documento Legal',
};
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($titulo_doc, ENT_QUOTES, 'UTF-8') ?> – Valírica</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
      background: #f0eeeb;
      color: #3d3c3b;
      min-height: 100vh;
      padding: 40px 16px;
    }
    .doc-card {
      max-width: 800px;
      margin: 0 auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 40px rgba(1,33,51,.09);
      border: 1px solid #e8e6e3;
      padding: clamp(24px, 5vw, 48px);
    }
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 28px;
      color: #EF7F1B;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
    }
    .back-link:hover { text-decoration: underline; }
    .doc-content h1 { color: #012133; font-size: clamp(18px, 3vw, 26px); margin-bottom: 8px; }
    .doc-content h2 { color: #184656; font-size: clamp(14px, 2vw, 18px); margin-top: 28px; margin-bottom: 8px; }
    .doc-content hr  { border: none; border-top: 1px solid #e8e6e3; margin: 20px 0; }
    .doc-content p   { margin-bottom: 12px; line-height: 1.7; font-size: 15px; }
    .doc-content ul  { margin-bottom: 12px; padding-left: 22px; }
    .doc-content li  { margin-bottom: 6px; line-height: 1.6; font-size: 15px; }
    .doc-content strong { color: #012133; }
    .doc-meta {
      background: #f8f7f5;
      border-radius: 8px;
      padding: 12px 16px;
      margin-bottom: 24px;
      font-size: 13px;
      color: #7a7977;
    }

    /* ── Banner simple (políticas: cookies / privacidad / términos) ── */
    .accept-banner {
      margin-top: 32px;
      padding: 20px 24px;
      border-radius: 12px;
      border: 1.5px solid #bbf7d0;
      background: #f0fdf4;
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .accept-banner.already-accepted { border-color: #86efac; background: #dcfce7; }
    .accept-banner-text { flex: 1; min-width: 0; }
    .accept-banner-text strong { display: block; color: #15803d; font-size: 15px; margin-bottom: 4px; }
    .accept-banner-text span   { color: #166534; font-size: 13px; }
    .accept-check-icon { font-size: 28px; color: #16a34a; flex-shrink: 0; }

    /* ── Formulario formal (contratos) ── */
    .contract-accept-form {
      margin-top: 36px;
      border: 2px solid #012133;
      border-radius: 14px;
      overflow: hidden;
    }
    .contract-accept-form.signed {
      border-color: #16a34a;
    }
    .caf-header {
      background: #012133;
      color: #fff;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .caf-header.signed { background: #16a34a; }
    .caf-header-icon { font-size: 22px; }
    .caf-header h3 { font-size: 16px; font-weight: 700; margin: 0; }
    .caf-body { padding: 24px; display: flex; flex-direction: column; gap: 18px; }

    .caf-legal-basis {
      background: #f0f4f8;
      border-left: 4px solid #007a96;
      border-radius: 0 8px 8px 0;
      padding: 14px 18px;
      font-size: 13px;
      color: #1e3a4a;
      line-height: 1.6;
    }
    .caf-legal-basis strong { display: block; margin-bottom: 6px; color: #012133; font-size: 13px; }
    .caf-legal-basis ul { padding-left: 20px; margin-top: 4px; }
    .caf-legal-basis li { margin-bottom: 4px; }

    .caf-record {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 13px;
      color: #92400e;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .caf-record strong { color: #78350f; }
    .caf-record-row { display: flex; gap: 8px; align-items: baseline; }
    .caf-record-label { font-weight: 700; min-width: 100px; }

    .caf-checkbox-wrap {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 16px;
      background: #f8fafc;
      border: 1.5px solid #cbd5e1;
      border-radius: 10px;
      cursor: pointer;
      transition: border-color .15s, background .15s;
    }
    .caf-checkbox-wrap:hover { border-color: #007a96; background: #f0f9ff; }
    .caf-checkbox-wrap input[type="checkbox"] {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      margin-top: 2px;
      accent-color: #012133;
      cursor: pointer;
    }
    .caf-checkbox-label {
      font-size: 14px;
      color: #1e293b;
      line-height: 1.6;
      cursor: pointer;
      user-select: none;
    }
    .caf-checkbox-label em { font-style: normal; font-weight: 700; color: #012133; }

    .caf-actions { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }

    /* ── Botones de aceptar (compartidos) ── */
    .btn-accept {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #16a34a;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      white-space: nowrap;
      transition: background .15s, opacity .15s;
    }
    .btn-accept:hover:not(:disabled) { background: #15803d; }
    .btn-accept:disabled { opacity: 0.45; cursor: not-allowed; }
    .btn-accept-contract {
      background: #012133;
      padding: 12px 28px;
      font-size: 15px;
      border-radius: 12px;
    }
    .btn-accept-contract:hover:not(:disabled) { background: #013d5e; }

    .caf-hint { font-size: 12px; color: #94a3b8; line-height: 1.5; }

    /* ── Recibo de aceptación ── */
    .caf-receipt {
      background: #f0fdf4;
      border: 1.5px solid #86efac;
      border-radius: 10px;
      padding: 16px 20px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .caf-receipt-title { font-size: 15px; font-weight: 700; color: #15803d; display: flex; align-items: center; gap: 8px; }
    .caf-receipt-row { font-size: 13px; color: #166534; display: flex; gap: 8px; }
    .caf-receipt-row strong { min-width: 120px; color: #14532d; }
  </style>
</head>
<body>
  <div class="doc-card">
    <a class="back-link" href="documentos.php">
      ← Volver a Documentos
    </a>
    <div class="doc-meta">
      📄 <?= htmlspecialchars($titulo_doc, ENT_QUOTES, 'UTF-8') ?>
      &nbsp;·&nbsp;
      Empresa: <strong><?= htmlspecialchars($ud['empresa'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <div class="doc-content">
      <?= $contenido ?>
    </div>

    <?php if ($needs_accept && $doc_registro_id): ?>
    <?php $ya_aceptado = ($doc_registro_estado === 'aceptado'); ?>

    <?php if ($is_contract): ?>
    <!-- ════════════════════════════════════════════════════════
         FORMULARIO FORMAL DE ACEPTACIÓN — CONTRATO
         Cumple: LSSI-CE art. 23-29 (España) + Ley 527/1999 (Colombia)
    ═════════════════════════════════════════════════════════ -->
    <div class="contract-accept-form <?= $ya_aceptado ? 'signed' : '' ?>" id="contractAcceptForm">
      <div class="caf-header <?= $ya_aceptado ? 'signed' : '' ?>">
        <span class="caf-header-icon"><?= $ya_aceptado ? '✅' : '✍️' ?></span>
        <h3><?= $ya_aceptado ? 'Contrato firmado electrónicamente' : 'Firma electrónica del contrato' ?></h3>
      </div>
      <div class="caf-body">

        <?php if ($ya_aceptado): ?>
        <!-- Recibo de aceptación -->
        <div class="caf-receipt">
          <div class="caf-receipt-title">✅ Contrato aceptado</div>
          <div class="caf-receipt-row">
            <strong>Empresa:</strong>
            <?= htmlspecialchars($ud['empresa'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="caf-receipt-row">
            <strong>Representante:</strong>
            <?= htmlspecialchars(trim(($ud['nombre'] ?? '') . ' ' . ($ud['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="caf-receipt-row">
            <strong>Fecha y hora:</strong>
            <?= $doc_fecha_aceptacion
                  ? htmlspecialchars(date('d/m/Y H:i:s', strtotime($doc_fecha_aceptacion)), ENT_QUOTES, 'UTF-8')
                  : '–' ?>
          </div>
          <div class="caf-receipt-row">
            <strong>IP de origen:</strong>
            <?= htmlspecialchars($doc_ip_aceptacion ?? '–', ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="caf-receipt-row">
            <strong>Referencia:</strong>
            DOC-<?= $doc_registro_id ?>-<?= $user_id ?>
          </div>
        </div>

        <?php else: ?>
        <!-- Base legal -->
        <div class="caf-legal-basis">
          <strong>Base legal de esta aceptación electrónica</strong>
          <ul>
            <li>🇪🇸 <strong>España:</strong> Ley 34/2002 LSSI-CE (arts. 23–29) y art. 1262 del Código Civil — los contratos electrónicos tienen plena validez jurídica.</li>
            <li>🇨🇴 <strong>Colombia:</strong> Ley 527 de 1999 (art. 14) sobre comercio electrónico — el mensaje de datos equivale a la aceptación escrita del contrato.</li>
          </ul>
        </div>

        <!-- Datos que quedarán registrados -->
        <div class="caf-record">
          <strong>Se registrará con su aceptación:</strong>
          <div class="caf-record-row">
            <span class="caf-record-label">Fecha y hora:</span>
            <span><?= date('d/m/Y H:i') ?> (hora del servidor)</span>
          </div>
          <div class="caf-record-row">
            <span class="caf-record-label">IP de origen:</span>
            <span><?= htmlspecialchars($client_ip, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="caf-record-row">
            <span class="caf-record-label">Empresa:</span>
            <span><?= htmlspecialchars($ud['empresa'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="caf-record-row">
            <span class="caf-record-label">Representante:</span>
            <span><?= htmlspecialchars(trim(($ud['nombre'] ?? '') . ' ' . ($ud['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>

        <!-- Checkbox de consentimiento informado -->
        <label class="caf-checkbox-wrap" for="chkContrato">
          <input type="checkbox" id="chkContrato" onchange="toggleContractBtn()">
          <span class="caf-checkbox-label">
            He leído íntegramente el presente <em><?= htmlspecialchars($titulo_doc, ENT_QUOTES, 'UTF-8') ?></em>
            y acepto todos sus términos y condiciones de forma libre, informada e inequívoca.
          </span>
        </label>

        <!-- Botón de firma -->
        <div class="caf-actions">
          <button class="btn-accept btn-accept-contract"
                  id="btnContrato"
                  disabled
                  onclick="firmarContrato(<?= $doc_registro_id ?>)">
            ✍ Firmar y aceptar contrato
          </button>
          <span class="caf-hint">
            Esta acción no se puede deshacer. Marca la casilla para habilitar el botón.
          </span>
        </div>
        <?php endif; ?>

      </div><!-- /.caf-body -->
    </div><!-- /.contract-accept-form -->

    <?php else: ?>
    <!-- ════════════════════════════════════════════════════════
         BANNER SIMPLE — POLÍTICAS (cookies / privacidad / términos)
    ═════════════════════════════════════════════════════════ -->
    <div class="accept-banner <?= $ya_aceptado ? 'already-accepted' : '' ?>" id="acceptBanner">
      <div class="accept-check-icon"><?= $ya_aceptado ? '✅' : '📋' ?></div>
      <div class="accept-banner-text">
        <?php if ($ya_aceptado): ?>
          <strong>Documento aceptado</strong>
          <span>Has aceptado este documento. Gracias por revisarlo.</span>
        <?php else: ?>
          <strong>Acepta este documento</strong>
          <span>Al aceptar confirmas que has leído y entendido el contenido de este documento.</span>
        <?php endif; ?>
      </div>
      <?php if (!$ya_aceptado): ?>
      <button class="btn-accept" id="btnAccept" onclick="aceptarDocumento(<?= $doc_registro_id ?>)">
        ✓ Aceptar documento
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
    /* ── Contrato ── */
    function toggleContractBtn() {
      const btn = document.getElementById('btnContrato');
      if (btn) btn.disabled = !document.getElementById('chkContrato').checked;
    }

    function firmarContrato(docId) {
      const btn = document.getElementById('btnContrato');
      const chk = document.getElementById('chkContrato');
      if (!chk || !chk.checked) return;
      if (!confirm('¿Confirmas que has leído íntegramente el contrato y aceptas todos sus términos?\n\nEsta acción quedará registrada con tu IP y la fecha/hora actual.')) return;
      btn.disabled = true;
      chk.disabled = true;
      btn.textContent = 'Registrando firma…';
      const fd = new FormData();
      fd.append('action', 'marcar_aceptado');
      fd.append('id', docId);
      fetch('documentos_backend.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.ok) {
            const form = document.getElementById('contractAcceptForm');
            form.classList.add('signed');
            form.querySelector('.caf-header').classList.add('signed');
            form.querySelector('.caf-header h3').textContent = 'Contrato firmado electrónicamente';
            form.querySelector('.caf-header-icon').textContent = '✅';
            form.querySelector('.caf-body').innerHTML = `
              <div class="caf-receipt">
                <div class="caf-receipt-title">✅ Contrato aceptado</div>
                <div class="caf-receipt-row"><strong>Fecha y hora:</strong> ${d.fecha}</div>
                <div class="caf-receipt-row"><strong>IP de origen:</strong> ${d.ip || '–'}</div>
                <div class="caf-receipt-row"><strong>Referencia:</strong> DOC-${docId}-<?= $user_id ?></div>
              </div>`;
          } else {
            btn.disabled = false;
            chk.disabled = false;
            btn.textContent = '✍ Firmar y aceptar contrato';
            alert(d.error || 'Error al registrar la firma. Intenta de nuevo.');
          }
        })
        .catch(() => {
          btn.disabled = false;
          chk.disabled = false;
          btn.textContent = '✍ Firmar y aceptar contrato';
          alert('Error de conexión. Intenta de nuevo.');
        });
    }

    /* ── Políticas ── */
    function aceptarDocumento(docId) {
      const btn = document.getElementById('btnAccept');
      if (!btn) return;
      if (!confirm('¿Confirmas que has leído y aceptas este documento?')) return;
      btn.disabled = true;
      btn.textContent = 'Aceptando…';
      const fd = new FormData();
      fd.append('action', 'marcar_aceptado');
      fd.append('id', docId);
      fetch('documentos_backend.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.ok) {
            const banner = document.getElementById('acceptBanner');
            banner.classList.add('already-accepted');
            banner.innerHTML = `
              <div class="accept-check-icon">✅</div>
              <div class="accept-banner-text">
                <strong>Documento aceptado</strong>
                <span>Has aceptado este documento. Gracias por revisarlo.</span>
              </div>`;
          } else {
            btn.disabled = false;
            btn.textContent = '✓ Aceptar documento';
            alert(d.error || 'Error al aceptar el documento. Intenta de nuevo.');
          }
        })
        .catch(() => {
          btn.disabled = false;
          btn.textContent = '✓ Aceptar documento';
          alert('Error de conexión. Intenta de nuevo.');
        });
    }
    </script>
    <?php endif; ?>

  </div>
</body>
</html>
