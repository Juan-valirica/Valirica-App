<?php
/**
 * seed_legal_docs.php
 * Script de administración: siembra los documentos legales en la tabla
 * `documentos` para TODOS los usuarios empresa existentes.
 *
 * Acceso: solo administradores ($_SESSION['is_admin'] = true).
 * Es seguro ejecutarlo múltiples veces (idempotente).
 *
 * Uso: abrir en navegador autenticado como admin.
 */
session_start();
require 'config.php';
require_once 'legal_seed_helper.php';

/* ── Auth: solo admin ── */
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Acceso restringido. Debes iniciar sesión como administrador.');
}

/* ── Obtener todos los usuarios empresa ── */
$empresas = [];
try {
    $res = $conn->query("SELECT id, empresa, email FROM usuarios ORDER BY id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $empresas[] = $row;
        }
    }
} catch (\Throwable $e) {
    die('Error al obtener usuarios: ' . htmlspecialchars($e->getMessage()));
}

/* ── Sembrar docs para cada empresa ── */
$results = [];
foreach ($empresas as $emp) {
    $res = seed_legal_docs_for_user($conn, (int)$emp['id']);
    $results[] = [
        'id'       => $emp['id'],
        'empresa'  => $emp['empresa'],
        'email'    => $emp['email'],
        'inserted' => $res['inserted'],
        'errors'   => $res['errors'],
    ];
}

$conn->close();

$total_inserted = array_sum(array_map(fn($r) => count($r['inserted']), $results));
$total_errors   = array_sum(array_map(fn($r) => count($r['errors']),   $results));
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Siembra de Documentos Legales – Valírica Admin</title>
  <style>
    body { font-family: -apple-system, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1   { color: #012133; }
    h2   { color: #184656; margin-top: 32px; }
    .summary { background: #f0f9f4; border: 1px solid #b2dfcb; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
    .summary.has-errors { background: #fff8f0; border-color: #fcd5a0; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
    th    { background: #012133; color: #fff; padding: 10px 12px; text-align: left; }
    td    { padding: 9px 12px; border-bottom: 1px solid #e8e6e3; vertical-align: top; }
    tr:hover td { background: #f8f7f5; }
    .tag  { display: inline-block; font-size: 12px; padding: 2px 8px; border-radius: 20px; margin: 2px; }
    .tag-ok  { background: #d4edda; color: #155724; }
    .tag-err { background: #f8d7da; color: #721c24; }
    .tag-skip { background: #e2e3e5; color: #383d41; }
    .ok   { color: #155724; font-weight: 600; }
    .err  { color: #721c24; font-weight: 600; }
    .back { display: inline-block; margin-top: 24px; color: #EF7F1B; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
  <h1>🌱 Siembra de Documentos Legales</h1>
  <p>Proceso completado para <strong><?= count($results) ?> empresas</strong>.</p>

  <div class="summary <?= $total_errors > 0 ? 'has-errors' : '' ?>">
    ✅ Documentos insertados: <strong class="ok"><?= $total_inserted ?></strong>
    &nbsp;&nbsp;
    <?php if ($total_errors > 0): ?>
      ❌ Errores: <strong class="err"><?= $total_errors ?></strong>
    <?php else: ?>
      ✔ Sin errores
    <?php endif; ?>
  </div>

  <h2>Detalle por empresa</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Empresa</th>
        <th>Email</th>
        <th>Docs insertados</th>
        <th>Errores</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['empresa'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['email'],   ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php if (empty($r['inserted'])): ?>
            <span class="tag tag-skip">sin cambios</span>
          <?php else: ?>
            <?php foreach ($r['inserted'] as $t): ?>
              <span class="tag tag-ok"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if (empty($r['errors'])): ?>
            –
          <?php else: ?>
            <?php foreach ($r['errors'] as $e): ?>
              <span class="tag tag-err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a class="back" href="documentos.php">← Volver a Documentos</a>
</body>
</html>
