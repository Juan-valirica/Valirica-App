<?php
/**
 * Valírica — Blog Admin Panel
 * Gestión de posts: listar, crear, editar, eliminar, publicar/despublicar
 * Requiere sesión activa de empresa (admin)
 */
require_once 'config.php';
session_start();

// ─── Auth: solo usuarios empresa logueados ─────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// ─── CSRF ──────────────────────────────────────────────────────────────────
if (!isset($_SESSION['csrf_blog'])) {
    $_SESSION['csrf_blog'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_blog'];
function verify_csrf($token){ return hash_equals($_SESSION['csrf_blog'], $token ?? ''); }

$action  = $_GET['action']  ?? 'list';
$post_id = (int)($_GET['id'] ?? 0);
$msg     = '';
$msg_type= 'success';

// ─── ACCIONES POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $msg = 'Token de seguridad inválido. Recarga la página.';
        $msg_type = 'error';
    } else {
        $act = $_POST['_action'] ?? '';

        // ── Crear / Editar ──
        if (in_array($act, ['create', 'update'])) {
            $fields = [
                'slug'            => trim(preg_replace('/[^a-z0-9-]/', '', strtolower(iconv('UTF-8','ASCII//TRANSLIT', $_POST['slug'] ?? '')))),
                'title'           => trim($_POST['title'] ?? ''),
                'excerpt'         => trim($_POST['excerpt'] ?? ''),
                'content'         => trim($_POST['content'] ?? ''),
                'cover_gradient'  => trim($_POST['cover_gradient'] ?? 'linear-gradient(135deg,#012133,#184656)'),
                'cover_image'     => trim($_POST['cover_image'] ?? ''),
                'author_name'     => trim($_POST['author_name'] ?? 'Equipo Valírica'),
                'author_title'    => trim($_POST['author_title'] ?? ''),
                'category'        => trim($_POST['category'] ?? ''),
                'tags'            => trim($_POST['tags'] ?? ''),
                'status'          => in_array($_POST['status'] ?? '', ['draft','published']) ? $_POST['status'] : 'draft',
                'featured'        => isset($_POST['featured']) ? 1 : 0,
                'seo_title'       => trim($_POST['seo_title'] ?? ''),
                'seo_description' => trim($_POST['seo_description'] ?? ''),
                'seo_keywords'    => trim($_POST['seo_keywords'] ?? ''),
                'reading_time'    => max(1, (int)($_POST['reading_time'] ?? 5)),
                'published_at'    => ($fields['status'] ?? 'draft') === 'published' ? (date('Y-m-d H:i:s')) : ($_POST['published_at'] ?? null),
            ];
            // Recalculate published_at correctly
            $status = $fields['status'];
            $pub_at = !empty($_POST['published_at']) ? $_POST['published_at'] : ($status === 'published' ? date('Y-m-d H:i:s') : null);

            if (empty($fields['slug']) || empty($fields['title']) || empty($fields['content'])) {
                $msg = 'Slug, título y contenido son obligatorios.';
                $msg_type = 'error';
            } elseif ($act === 'create') {
                $st = $conn->prepare("INSERT INTO blog_posts (slug,title,excerpt,content,cover_gradient,cover_image,author_name,author_title,category,tags,status,featured,seo_title,seo_description,seo_keywords,reading_time,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('ssssssssssssssssi',
                    $fields['slug'],$fields['title'],$fields['excerpt'],$fields['content'],
                    $fields['cover_gradient'],$fields['cover_image'],$fields['author_name'],$fields['author_title'],
                    $fields['category'],$fields['tags'],$status,$fields['featured'],
                    $fields['seo_title'],$fields['seo_description'],$fields['seo_keywords'],$fields['reading_time'],$pub_at
                );
                if ($st->execute()) {
                    $msg = '✅ Post creado correctamente.';
                    header('Location: /blog-admin.php?msg=' . urlencode($msg));
                    exit;
                } else {
                    $msg = 'Error al crear: ' . $st->error;
                    $msg_type = 'error';
                }
                $st->close();
            } else {
                $st = $conn->prepare("UPDATE blog_posts SET slug=?,title=?,excerpt=?,content=?,cover_gradient=?,cover_image=?,author_name=?,author_title=?,category=?,tags=?,status=?,featured=?,seo_title=?,seo_description=?,seo_keywords=?,reading_time=?,published_at=? WHERE id=?");
                $st->bind_param('sssssssssssisssisi',
                    $fields['slug'],$fields['title'],$fields['excerpt'],$fields['content'],
                    $fields['cover_gradient'],$fields['cover_image'],$fields['author_name'],$fields['author_title'],
                    $fields['category'],$fields['tags'],$status,$fields['featured'],
                    $fields['seo_title'],$fields['seo_description'],$fields['seo_keywords'],$fields['reading_time'],$pub_at,
                    $post_id
                );
                if ($st->execute()) {
                    $msg = '✅ Post actualizado correctamente.';
                    header('Location: /blog-admin.php?msg=' . urlencode($msg));
                    exit;
                } else {
                    $msg = 'Error al actualizar: ' . $st->error;
                    $msg_type = 'error';
                }
                $st->close();
            }
        }

        // ── Eliminar ──
        if ($act === 'delete' && $post_id) {
            $conn->query("DELETE FROM blog_posts WHERE id = $post_id");
            header('Location: /blog-admin.php?msg=' . urlencode('🗑️ Post eliminado.'));
            exit;
        }

        // ── Toggle status ──
        if ($act === 'toggle_status' && $post_id) {
            $conn->query("UPDATE blog_posts SET status = IF(status='published','draft','published'), published_at = IF(status='draft', NOW(), published_at) WHERE id = $post_id");
            header('Location: /blog-admin.php?msg=' . urlencode('✅ Estado actualizado.'));
            exit;
        }
    }
}

if (!$msg && isset($_GET['msg'])) $msg = $_GET['msg'];

// ─── Cargar post para editar ───────────────────────────────────────────────
$edit_post = null;
if ($action === 'edit' && $post_id) {
    $st = $conn->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
    $st->bind_param('i', $post_id);
    $st->execute();
    $edit_post = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$edit_post) { header('Location: /blog-admin.php'); exit; }
}

// ─── Listar posts ──────────────────────────────────────────────────────────
$posts_list = [];
if ($action === 'list') {
    $r = $conn->query("SELECT id, slug, title, category, status, featured, reading_time, view_count, published_at, updated_at FROM blog_posts ORDER BY updated_at DESC");
    $posts_list = $r->fetch_all(MYSQLI_ASSOC);
}

// Categorías predefinidas
$preset_cats = ['Cultura Organizacional','Liderazgo y Equipos','Gestión del Talento','Recursos Humanos','Productividad','People Analytics'];
$preset_grads = [
    'Teal'   => 'linear-gradient(135deg, #012133 0%, #023047 40%, #007a96 100%)',
    'Naranja' => 'linear-gradient(135deg, #012133 0%, #2a1a0a 60%, #8a4709 100%)',
    'Azul'   => 'linear-gradient(135deg, #011929 0%, #034461 50%, #2e7d9e 100%)',
    'Oscuro' => 'linear-gradient(135deg, #012133 0%, #103340 50%, #205869 100%)',
    'Mixto'  => 'linear-gradient(135deg, #012133 0%, #1a3a4a 40%, #EF7F1B 100%)',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Blog Admin — Valírica</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
  <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --c-primary:   #012133;
      --c-secondary: #184656;
      --c-teal:      #007a96;
      --c-accent:    #EF7F1B;
      --c-surface:   rgba(255,255,255,0.04);
      --c-border:    rgba(255,255,255,0.09);
      --font: "gelica", system-ui, sans-serif;
      --radius: 14px;
      --tr: 0.18s ease;
    }
    html, body { height: 100%; }
    body {
      font-family: var(--font); background: linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 100%);
      color: #fff; min-height: 100vh; -webkit-font-smoothing: antialiased;
    }

    /* ── HEADER ── */
    .adm-header {
      background: rgba(1,25,41,0.88); backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--c-border);
      padding: 0 28px; position: sticky; top: 0; z-index: 50;
    }
    .adm-header-inner {
      max-width: 1200px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
      height: 60px;
    }
    .adm-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
    .adm-logo img { width: 32px; height: 32px; border-radius: 50%; }
    .adm-logo span { font-weight: 800; font-size: 15px; }
    .adm-badge {
      background: rgba(239,127,27,0.15); border: 1px solid rgba(239,127,27,0.30);
      color: #f5a23d; font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; padding: 3px 10px; border-radius: 100px;
    }
    .adm-header-right { display: flex; align-items: center; gap: 10px; }
    .adm-header-link {
      padding: 7px 14px; border-radius: 10px; font-size: 13px; font-weight: 600;
      text-decoration: none; color: rgba(255,255,255,0.55);
      transition: color var(--tr), background var(--tr);
    }
    .adm-header-link:hover { color: #fff; background: rgba(255,255,255,0.08); }

    /* ── MAIN LAYOUT ── */
    .adm-main { max-width: 1200px; margin: 0 auto; padding: 32px 28px 80px; }
    .adm-page-title {
      font-size: 26px; font-weight: 900; color: #fff;
      margin-bottom: 24px; display: flex; align-items: center; gap: 12px;
    }
    .adm-page-title i { color: var(--c-accent); }

    /* ── MESSAGES ── */
    .adm-msg {
      padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px;
      font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;
    }
    .adm-msg.success { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
    .adm-msg.error   { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.25);  color: #fca5a5; }

    /* ── ACTION BAR ── */
    .adm-action-bar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
    }
    .adm-stats { display: flex; gap: 16px; }
    .adm-stat {
      background: var(--c-surface); border: 1px solid var(--c-border);
      border-radius: var(--radius); padding: 12px 18px;
      display: flex; align-items: center; gap: 10px;
    }
    .adm-stat i { font-size: 20px; color: var(--c-accent); }
    .adm-stat-val { font-size: 20px; font-weight: 900; color: #fff; }
    .adm-stat-label { font-size: 11px; color: rgba(255,255,255,0.45); }

    /* ── BUTTONS ── */
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: var(--font); cursor: pointer; text-decoration: none; border: none; transition: opacity var(--tr), transform var(--tr); }
    .btn:hover { opacity: 0.88; transform: scale(0.98); }
    .btn-primary { background: linear-gradient(135deg, var(--c-accent), #d96b0a); color: #fff; box-shadow: 0 4px 14px rgba(239,127,27,0.30); }
    .btn-ghost { background: var(--c-surface); border: 1px solid var(--c-border); color: rgba(255,255,255,0.65); }
    .btn-ghost:hover { background: rgba(255,255,255,0.08); color: #fff; }
    .btn-danger { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.30); color: #fca5a5; }
    .btn-danger:hover { background: rgba(239,68,68,0.22); }
    .btn-teal { background: linear-gradient(135deg, var(--c-teal), #005f74); color: #fff; }
    .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
    .btn i { font-size: 15px; }

    /* ── TABLE ── */
    .adm-table-wrap { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: 18px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    thead { background: rgba(255,255,255,0.03); }
    th { padding: 14px 16px; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(255,255,255,0.35); text-align: left; white-space: nowrap; border-bottom: 1px solid var(--c-border); }
    td { padding: 14px 16px; font-size: 13px; color: rgba(255,255,255,0.75); vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.04); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.025); }

    .post-title-cell { font-weight: 700; color: #fff; max-width: 320px; }
    .post-title-cell a { color: #fff; text-decoration: none; }
    .post-title-cell a:hover { color: var(--c-accent); }
    .post-slug-cell { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 3px; }

    .status-badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;
    }
    .status-badge.published { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
    .status-badge.draft     { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.45); }
    .status-badge i { font-size: 10px; }

    .featured-badge {
      display: inline-flex; align-items: center; gap: 4px;
      background: rgba(239,127,27,0.12); border: 1px solid rgba(239,127,27,0.25);
      color: #f5a23d; padding: 3px 9px; border-radius: 100px; font-size: 11px; font-weight: 700;
    }

    .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }

    /* ── FORM ── */
    .adm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .adm-form-group { display: flex; flex-direction: column; gap: 6px; }
    .adm-form-group.full { grid-column: 1 / -1; }
    .adm-form-label { font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.50); letter-spacing: 0.5px; }
    .adm-form-label span { color: rgba(239,127,27,0.8); }
    .adm-input, .adm-select, .adm-textarea {
      background: rgba(255,255,255,0.05); border: 1px solid var(--c-border);
      border-radius: 10px; padding: 10px 14px; font-size: 14px; font-family: var(--font);
      color: #fff; outline: none;
      transition: border-color var(--tr), background var(--tr);
    }
    .adm-input:focus, .adm-select:focus, .adm-textarea:focus {
      border-color: rgba(0,122,150,0.50); background: rgba(255,255,255,0.07);
    }
    .adm-input::placeholder, .adm-textarea::placeholder { color: rgba(255,255,255,0.25); }
    .adm-select option { background: #012133; }
    .adm-textarea { resize: vertical; min-height: 120px; }
    .adm-textarea.content-area { min-height: 380px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; }
    .adm-hint { font-size: 11px; color: rgba(255,255,255,0.30); }

    /* Char counter */
    .char-counter { font-size: 11px; text-align: right; color: rgba(255,255,255,0.30); }
    .char-counter.warn { color: #f5a23d; }
    .char-counter.over { color: #fca5a5; }

    /* Gradient preview */
    .grad-preview {
      height: 48px; border-radius: 10px; margin-bottom: 8px; border: 1px solid var(--c-border);
      transition: background 0.3s;
    }
    .grad-presets { display: flex; gap: 8px; flex-wrap: wrap; }
    .grad-preset-btn {
      width: 40px; height: 40px; border-radius: 8px; cursor: pointer;
      border: 2px solid transparent; transition: border-color var(--tr), transform var(--tr);
    }
    .grad-preset-btn:hover { transform: scale(1.08); border-color: rgba(255,255,255,0.40); }

    /* Checkbox */
    .adm-checkbox-wrap { display: flex; align-items: center; gap: 10px; }
    .adm-checkbox { width: 18px; height: 18px; accent-color: var(--c-accent); cursor: pointer; }

    /* Card */
    .adm-card {
      background: var(--c-surface); border: 1px solid var(--c-border);
      border-radius: 18px; padding: 28px; margin-bottom: 20px;
    }
    .adm-card h3 {
      font-size: 14px; font-weight: 700; color: rgba(255,255,255,0.55); letter-spacing: 0.5px;
      text-transform: uppercase; margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
    }
    .adm-card h3 i { font-size: 16px; color: var(--c-accent); }

    /* SEO meter */
    .seo-meter { margin-top: 12px; }
    .seo-meter-bar { height: 4px; border-radius: 2px; background: rgba(255,255,255,0.08); margin-top: 4px; }
    .seo-meter-fill { height: 4px; border-radius: 2px; background: linear-gradient(90deg, var(--c-teal), var(--c-accent)); transition: width 0.3s; }
    .seo-meter-label { font-size: 11px; color: rgba(255,255,255,0.35); display: flex; justify-content: space-between; margin-top: 4px; }

    /* Form actions */
    .adm-form-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--c-border); }

    /* Empty state */
    .adm-empty { text-align: center; padding: 60px 24px; color: rgba(255,255,255,0.35); }
    .adm-empty i { font-size: 48px; display: block; margin-bottom: 12px; }

    @media (max-width: 768px) {
      .adm-form-grid { grid-template-columns: 1fr; }
      .adm-form-group.full { grid-column: 1; }
      .adm-stats { flex-wrap: wrap; }
      .adm-action-bar { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

<!-- ── HEADER ── -->
<header class="adm-header">
  <div class="adm-header-inner">
    <div style="display:flex;align-items:center;gap:14px">
      <a href="/" class="adm-logo">
        <img src="https://app.valirica.com/uploads/logo-192.png" alt="Valírica">
        <span>Valírica</span>
      </a>
      <span class="adm-badge"><i class="ph ph-pencil-line"></i> Blog Admin</span>
    </div>
    <div class="adm-header-right">
      <a href="/blog" class="adm-header-link" target="_blank"><i class="ph ph-arrow-square-out"></i> Ver blog</a>
      <a href="/a-desktop-dashboard-brand.php" class="adm-header-link"><i class="ph ph-house"></i> Dashboard</a>
    </div>
  </div>
</header>

<main class="adm-main">

  <?php if ($msg): ?>
  <div class="adm-msg <?= $msg_type ?>" role="alert">
    <i class="ph <?= $msg_type === 'error' ? 'ph-warning' : 'ph-check-circle' ?>"></i>
    <?= h($msg) ?>
  </div>
  <?php endif; ?>

  <?php if ($action === 'list'): ?>
  <!-- ════════════ LIST ════════════ -->
  <div class="adm-action-bar">
    <div>
      <h1 class="adm-page-title"><i class="ph ph-article"></i> Gestión del Blog</h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <div class="adm-stats">
        <?php
        $total_posts  = count($posts_list);
        $pub_count    = count(array_filter($posts_list, fn($p) => $p['status'] === 'published'));
        $total_views  = array_sum(array_column($posts_list, 'view_count'));
        ?>
        <div class="adm-stat">
          <i class="ph ph-article"></i>
          <div><div class="adm-stat-val"><?= $total_posts ?></div><div class="adm-stat-label">Posts totales</div></div>
        </div>
        <div class="adm-stat">
          <i class="ph ph-check-circle"></i>
          <div><div class="adm-stat-val"><?= $pub_count ?></div><div class="adm-stat-label">Publicados</div></div>
        </div>
        <div class="adm-stat">
          <i class="ph ph-eye"></i>
          <div><div class="adm-stat-val"><?= number_format($total_views) ?></div><div class="adm-stat-label">Lecturas totales</div></div>
        </div>
      </div>
      <a href="/blog-admin.php?action=new" class="btn btn-primary"><i class="ph ph-plus"></i> Nuevo post</a>
    </div>
  </div>

  <?php if (empty($posts_list)): ?>
  <div class="adm-empty">
    <i class="ph ph-article"></i>
    <p>No hay posts todavía.</p>
    <a href="/blog-admin.php?action=new" class="btn btn-primary" style="margin-top:16px"><i class="ph ph-plus"></i> Crear primer post</a>
  </div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table>
      <thead>
        <tr>
          <th>Título / Slug</th>
          <th>Categoría</th>
          <th>Estado</th>
          <th>Lecturas</th>
          <th>Publicado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts_list as $p): ?>
        <tr>
          <td>
            <div class="post-title-cell">
              <a href="/blog/<?= h($p['slug']) ?>" target="_blank"><?= h($p['title']) ?></a>
              <?php if ($p['featured']): ?>&nbsp;<span class="featured-badge"><i class="ph-fill ph-star"></i> Destacado</span><?php endif; ?>
            </div>
            <div class="post-slug-cell">/blog/<?= h($p['slug']) ?></div>
          </td>
          <td><?= h($p['category']) ?></td>
          <td>
            <span class="status-badge <?= h($p['status']) ?>">
              <i class="ph <?= $p['status'] === 'published' ? 'ph-check-circle' : 'ph-circle-dashed' ?>"></i>
              <?= $p['status'] === 'published' ? 'Publicado' : 'Borrador' ?>
            </span>
          </td>
          <td><?= number_format($p['view_count']) ?></td>
          <td style="font-size:12px;white-space:nowrap"><?= $p['published_at'] ? date('d/m/Y', strtotime($p['published_at'])) : '—' ?></td>
          <td>
            <div class="actions-cell">
              <a href="/blog-admin.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm"><i class="ph ph-pencil"></i> Editar</a>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="_action" value="toggle_status">
                <button type="submit" formaction="/blog-admin.php?action=list&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" onclick="this.form.action='/blog-admin.php?id=<?= $p['id'] ?>'">
                  <i class="ph <?= $p['status'] === 'published' ? 'ph-eye-slash' : 'ph-eye' ?>"></i>
                  <?= $p['status'] === 'published' ? 'Despublicar' : 'Publicar' ?>
                </button>
              </form>
              <a href="/blog/<?= h($p['slug']) ?>" target="_blank" class="btn btn-teal btn-sm"><i class="ph ph-arrow-square-out"></i></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este post permanentemente?')">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="_action" value="delete">
                <button type="submit" formaction="/blog-admin.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm"><i class="ph ph-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <!-- ════════════ FORM (new / edit) ════════════ -->
  <?php $is_edit = ($action === 'edit' && $edit_post); ?>
  <div style="margin-bottom:20px">
    <a href="/blog-admin.php" class="btn btn-ghost btn-sm"><i class="ph ph-arrow-left"></i> Volver al listado</a>
  </div>
  <h1 class="adm-page-title">
    <i class="ph <?= $is_edit ? 'ph-pencil' : 'ph-plus-circle' ?>"></i>
    <?= $is_edit ? 'Editar post' : 'Nuevo post' ?>
  </h1>

  <form method="POST" action="/blog-admin.php?action=<?= $is_edit ? 'edit&id=' . $post_id : 'new' ?>" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="_action" value="<?= $is_edit ? 'update' : 'create' ?>">

    <!-- ── Contenido principal ── -->
    <div class="adm-card">
      <h3><i class="ph ph-article"></i> Contenido del artículo</h3>
      <div class="adm-form-grid">

        <div class="adm-form-group">
          <label class="adm-form-label" for="title">Título <span>*</span></label>
          <input class="adm-input" type="text" id="title" name="title"
            value="<?= h($edit_post['title'] ?? '') ?>" placeholder="El gran título del artículo"
            required maxlength="300" oninput="autoSlug(this.value)">
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label" for="slug">Slug URL <span>*</span></label>
          <input class="adm-input" type="text" id="slug" name="slug"
            value="<?= h($edit_post['slug'] ?? '') ?>" placeholder="el-titulo-del-articulo"
            required maxlength="300" pattern="[a-z0-9-]+">
          <span class="adm-hint">Solo letras minúsculas, números y guiones. Se genera automáticamente.</span>
        </div>

        <div class="adm-form-group full">
          <label class="adm-form-label" for="excerpt">Extracto / Descripción</label>
          <textarea class="adm-textarea" id="excerpt" name="excerpt" rows="3"
            placeholder="Resumen atractivo del artículo (aparece en el listado y redes sociales)..."
            maxlength="500" oninput="updateCounter('excerpt','excerpt-count',500)"><?= h($edit_post['excerpt'] ?? '') ?></textarea>
          <div class="char-counter" id="excerpt-count">0 / 500</div>
        </div>

        <div class="adm-form-group full">
          <label class="adm-form-label" for="content">Contenido HTML <span>*</span></label>
          <textarea class="adm-textarea content-area" id="content" name="content"
            placeholder="<h2>Sección 1</h2><p>Tu contenido aquí...</p>"
            required><?= h($edit_post['content'] ?? '') ?></textarea>
          <span class="adm-hint">HTML válido. Usa &lt;h2&gt; para secciones principales, &lt;h3&gt; para subsecciones. Las preguntas con ? al final de &lt;h3&gt; dentro de &lt;div class="blog-faq"&gt; generan FAQ Schema automáticamente.</span>
        </div>

      </div>
    </div>

    <!-- ── Metadatos ── -->
    <div class="adm-card">
      <h3><i class="ph ph-tag"></i> Metadatos y clasificación</h3>
      <div class="adm-form-grid">

        <div class="adm-form-group">
          <label class="adm-form-label" for="category">Categoría</label>
          <select class="adm-select" id="category" name="category">
            <?php foreach ($preset_cats as $cat): ?>
            <option value="<?= h($cat) ?>" <?= ($edit_post['category'] ?? '') === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label" for="reading_time">Tiempo de lectura (min)</label>
          <input class="adm-input" type="number" id="reading_time" name="reading_time" min="1" max="60"
            value="<?= h($edit_post['reading_time'] ?? 5) ?>">
        </div>

        <div class="adm-form-group full">
          <label class="adm-form-label" for="tags">Etiquetas (separadas por comas)</label>
          <input class="adm-input" type="text" id="tags" name="tags"
            value="<?= h($edit_post['tags'] ?? '') ?>"
            placeholder="cultura organizacional, liderazgo, DISC, equipos">
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label" for="status">Estado</label>
          <select class="adm-select" id="status" name="status">
            <option value="draft"     <?= ($edit_post['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>📝 Borrador</option>
            <option value="published" <?= ($edit_post['status'] ?? '')       === 'published' ? 'selected' : '' ?>>✅ Publicado</option>
          </select>
        </div>

        <div class="adm-form-group">
          <label class="adm-form-label" for="published_at">Fecha de publicación</label>
          <input class="adm-input" type="datetime-local" id="published_at" name="published_at"
            value="<?= $edit_post['published_at'] ? date('Y-m-d\TH:i', strtotime($edit_post['published_at'])) : '' ?>">
        </div>

        <div class="adm-form-group full">
          <div class="adm-checkbox-wrap">
            <input type="checkbox" class="adm-checkbox" id="featured" name="featured"
              <?= !empty($edit_post['featured']) ? 'checked' : '' ?>>
            <label for="featured" class="adm-form-label" style="cursor:pointer">
              ⭐ Artículo destacado (aparece primero en el blog)
            </label>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Autoría ── -->
    <div class="adm-card">
      <h3><i class="ph ph-user-circle"></i> Autoría</h3>
      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label" for="author_name">Nombre del autor</label>
          <input class="adm-input" type="text" id="author_name" name="author_name"
            value="<?= h($edit_post['author_name'] ?? 'Equipo Valírica') ?>">
        </div>
        <div class="adm-form-group">
          <label class="adm-form-label" for="author_title">Cargo / Título</label>
          <input class="adm-input" type="text" id="author_title" name="author_title"
            value="<?= h($edit_post['author_title'] ?? 'Especialistas en Cultura Organizacional') ?>">
        </div>
        <div class="adm-form-group full">
          <label class="adm-form-label" for="author_avatar">URL avatar del autor (opcional)</label>
          <input class="adm-input" type="url" id="author_avatar" name="author_avatar"
            value="<?= h($edit_post['author_avatar'] ?? '') ?>" placeholder="https://...">
        </div>
      </div>
    </div>

    <!-- ── Diseño visual ── -->
    <div class="adm-card">
      <h3><i class="ph ph-paint-brush"></i> Diseño visual de portada</h3>
      <div class="adm-form-grid">
        <div class="adm-form-group">
          <label class="adm-form-label" for="cover_gradient">Gradiente CSS</label>
          <div class="grad-preview" id="grad-preview" style="background: <?= h($edit_post['cover_gradient'] ?? 'linear-gradient(135deg,#012133,#184656)') ?>"></div>
          <div class="grad-presets">
            <?php foreach ($preset_grads as $name => $grad): ?>
            <div class="grad-preset-btn" style="background:<?= h($grad) ?>" title="<?= h($name) ?>"
                 onclick="setGrad('<?= addslashes($grad) ?>')" role="button" tabindex="0"></div>
            <?php endforeach; ?>
          </div>
          <input class="adm-input" type="text" id="cover_gradient" name="cover_gradient"
            value="<?= h($edit_post['cover_gradient'] ?? 'linear-gradient(135deg,#012133,#184656)') ?>"
            oninput="document.getElementById('grad-preview').style.background=this.value" style="margin-top:8px">
        </div>
        <div class="adm-form-group">
          <label class="adm-form-label" for="cover_image">URL imagen de portada (opcional)</label>
          <input class="adm-input" type="url" id="cover_image" name="cover_image"
            value="<?= h($edit_post['cover_image'] ?? '') ?>" placeholder="https://...">
          <span class="adm-hint">Recomendado: 1200×630px. Se superpone al gradiente con opacidad.</span>
        </div>
      </div>
    </div>

    <!-- ── SEO ── -->
    <div class="adm-card">
      <h3><i class="ph ph-magnifying-glass"></i> SEO & Metadatos de búsqueda</h3>
      <div class="adm-form-grid">

        <div class="adm-form-group full">
          <label class="adm-form-label" for="seo_title">SEO Title (ideal: 50–60 caracteres)</label>
          <input class="adm-input" type="text" id="seo_title" name="seo_title"
            value="<?= h($edit_post['seo_title'] ?? '') ?>"
            placeholder="Título optimizado para buscadores | Valírica" maxlength="120"
            oninput="updateSeoMeter('seo_title','seo-title-count',60)">
          <div class="seo-meter">
            <div class="seo-meter-bar"><div class="seo-meter-fill" id="seo-title-fill" style="width:0%"></div></div>
            <div class="seo-meter-label"><span id="seo-title-count">0 caracteres</span><span>Ideal: 50–60</span></div>
          </div>
        </div>

        <div class="adm-form-group full">
          <label class="adm-form-label" for="seo_description">Meta Description (ideal: 140–160 caracteres)</label>
          <textarea class="adm-textarea" id="seo_description" name="seo_description" rows="3"
            placeholder="Descripción para resultados de búsqueda y redes sociales..." maxlength="300"
            oninput="updateSeoMeter('seo_description','seo-desc-count',160)"><?= h($edit_post['seo_description'] ?? '') ?></textarea>
          <div class="seo-meter">
            <div class="seo-meter-bar"><div class="seo-meter-fill" id="seo-desc-fill" style="width:0%"></div></div>
            <div class="seo-meter-label"><span id="seo-desc-count">0 caracteres</span><span>Ideal: 140–160</span></div>
          </div>
        </div>

        <div class="adm-form-group full">
          <label class="adm-form-label" for="seo_keywords">Keywords SEO (separadas por comas)</label>
          <input class="adm-input" type="text" id="seo_keywords" name="seo_keywords"
            value="<?= h($edit_post['seo_keywords'] ?? '') ?>"
            placeholder="cultura organizacional, cómo medir cultura empresa, ...">
          <span class="adm-hint">Incluye variantes de long-tail para mejorar el posicionamiento en búsquedas de IA.</span>
        </div>

      </div>
    </div>

    <div class="adm-form-actions">
      <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> <?= $is_edit ? 'Guardar cambios' : 'Crear post' ?></button>
      <?php if ($is_edit): ?>
      <a href="/blog/<?= h($edit_post['slug']) ?>" target="_blank" class="btn btn-teal"><i class="ph ph-arrow-square-out"></i> Ver en el blog</a>
      <?php endif; ?>
      <a href="/blog-admin.php" class="btn btn-ghost"><i class="ph ph-x"></i> Cancelar</a>
    </div>
  </form>
  <?php endif; ?>

</main>

<script>
function autoSlug(val){
  const slugEl = document.getElementById('slug');
  if(!slugEl || slugEl.dataset.manual) return;
  slugEl.value = val.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .replace(/[^a-z0-9\s-]/g,'').trim()
    .replace(/[\s_]+/g,'-').replace(/-+/g,'-');
}
document.getElementById('slug')?.addEventListener('input', function(){
  this.dataset.manual = '1';
});
function updateCounter(id, countId, max){
  const el = document.getElementById(id);
  const counter = document.getElementById(countId);
  if(!el || !counter) return;
  const len = el.value.length;
  counter.textContent = len + ' / ' + max;
  counter.className = 'char-counter' + (len > max ? ' over' : len > max*0.9 ? ' warn' : '');
}
function updateSeoMeter(id, countId, ideal){
  const el = document.getElementById(id);
  const lbl = document.getElementById(countId);
  const fill = document.getElementById(countId.replace('count','fill'));
  if(!el) return;
  const len = el.value.length;
  if(lbl) lbl.textContent = len + ' caracteres';
  if(fill) fill.style.width = Math.min(100, Math.round((len / ideal) * 100)) + '%';
}
function setGrad(g){
  const inp = document.getElementById('cover_gradient');
  if(inp){ inp.value = g; document.getElementById('grad-preview').style.background = g; }
}
// Init counters
['excerpt','seo_title','seo_description'].forEach(id => {
  const el = document.getElementById(id);
  if(el){
    const max = id==='excerpt' ? 500 : id==='seo_title' ? 60 : 160;
    if(id==='excerpt') updateCounter(id,'excerpt-count',500);
    else updateSeoMeter(id, id.replace('_','-').replace('seo-','seo-')+'count', max);
  }
});
document.getElementById('seo_title')?.dispatchEvent(new Event('input'));
document.getElementById('seo_description')?.dispatchEvent(new Event('input'));
document.getElementById('excerpt')?.dispatchEvent(new Event('input'));

// Toggle status form action fix
document.querySelectorAll('[data-toggle-form]')?.forEach(f=>{
  f.addEventListener('submit', function(e){
    const id = this.dataset.toggleForm;
    this.action = '/blog-admin.php?id='+id;
  });
});
</script>
</body>
</html>
