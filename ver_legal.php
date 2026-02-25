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
    '{{EMPRESA_CLIENTE}}'           => htmlspecialchars($ud['empresa']  ?? '', ENT_QUOTES, 'UTF-8'),
    '{{REPRESENTANTE}}'             => htmlspecialchars(trim(($ud['nombre'] ?? '') . ' ' . ($ud['apellido'] ?? '')), ENT_QUOTES, 'UTF-8'),
    '{{EMAIL_CLIENTE}}'             => htmlspecialchars($ud['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{EMAIL_FACTURACION_ADICIONAL}}' => htmlspecialchars($ud['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{FECHA_REGISTRO}}'            => !empty($ud['fecha_registro'])
                                           ? date('d/m/Y', strtotime($ud['fecha_registro']))
                                           : date('d/m/Y'),
    '{{FECHA_ACEPTACION}}'          => date('d/m/Y'),
    '{{PERIODO_PRUEBA}}'            => '15 días',
    '{{TIPO_PLAN}}'                 => 'Mensual',
    '{{NIT_CLIENTE}}'               => '–',
    '{{CIF_CLIENTE}}'               => '–',
    '{{PRECIO_PLAN}}'               => $es_colombia ? '$20.000 COP + IVA/empleado/mes' : '€6/empleado/mes',
];

$contenido = file_get_contents($file_path);
$contenido = str_replace(array_keys($vars), array_values($vars), $contenido);

// Para docs de política: buscar el registro en documentos para este usuario
$policy_docs   = ['cookies', 'privacidad', 'terminos'];
$is_policy_doc = in_array($doc, $policy_docs, true);
$doc_registro_id    = null;
$doc_registro_estado = null;
if ($is_policy_doc) {
    try {
        $st_doc = $conn->prepare("
            SELECT id, estado FROM documentos
            WHERE empresa_id = ? AND url_documento = ?
            LIMIT 1
        ");
        if ($st_doc) {
            $url_buscar = 'ver_legal.php?doc=' . $doc;
            $st_doc->bind_param("is", $user_id, $url_buscar);
            $st_doc->execute();
            $row_doc = stmt_get_result($st_doc)->fetch_assoc();
            if ($row_doc) {
                $doc_registro_id    = (int)$row_doc['id'];
                $doc_registro_estado = $row_doc['estado'];
            }
            $st_doc->close();
        }
    } catch (\Throwable $e) {
        error_log("ver_legal.php: doc lookup failed — " . $e->getMessage());
    }
}

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
    .accept-banner.already-accepted {
      border-color: #86efac;
      background: #dcfce7;
    }
    .accept-banner-text { flex: 1; min-width: 0; }
    .accept-banner-text strong { display: block; color: #15803d; font-size: 15px; margin-bottom: 4px; }
    .accept-banner-text span   { color: #166534; font-size: 13px; }
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
      transition: background .15s;
    }
    .btn-accept:hover:not(:disabled) { background: #15803d; }
    .btn-accept:disabled { background: #86efac; cursor: default; }
    .accept-check-icon { font-size: 28px; color: #16a34a; flex-shrink: 0; }
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

    <?php if ($is_policy_doc && $doc_registro_id): ?>
    <?php $ya_aceptado = ($doc_registro_estado === 'aceptado'); ?>
    <div class="accept-banner <?= $ya_aceptado ? 'already-accepted' : '' ?>" id="acceptBanner">
      <div class="accept-check-icon">
        <?= $ya_aceptado ? '✅' : '📋' ?>
      </div>
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

    <script>
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
