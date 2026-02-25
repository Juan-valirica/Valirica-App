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
  </div>
</body>
</html>
