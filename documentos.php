<?php
session_start();
require 'config.php';

/* ── Auth ── */
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ── Helpers ── */
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('resolve_logo_url')) {
    function resolve_logo_url(?string $path): string {
        $def = 'https://app.valirica.com/uploads/logos/1749413056_logo-valirica.png';
        $p = trim((string)$path);
        if ($p === '') return $def;
        if (preg_match('~^https?://~i', $p)) return $p;
        if (strpos($p, '//') === 0) return 'https:' . $p;
        if ($p[0] === '/') return 'https://app.valirica.com' . $p;
        return 'https://app.valirica.com/' . ltrim($p, '/');
    }
}
if (!function_exists('initials')) {
    function initials($name) {
        $parts = preg_split('/\s+/u', trim((string)$name));
        $a = isset($parts[0][0]) ? mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8')) : '';
        $b = isset($parts[1][0]) ? mb_strtoupper(mb_substr($parts[1], 0, 1, 'UTF-8')) : '';
        return $a . $b;
    }
}

/* ── User data ── */
$st = $conn->prepare("SELECT empresa, logo, email, cultura_empresa_tipo FROM usuarios WHERE id = ? LIMIT 1");
$st->bind_param("i", $user_id);
$st->execute();
$usuario = stmt_get_result($st)->fetch_assoc();
$st->close();

$empresa              = $usuario['empresa']              ?? '';
$logo                 = $usuario['logo']                 ?? '';
$email                = $usuario['email']                ?? '';
$cultura_empresa_tipo = $usuario['cultura_empresa_tipo'] ?? '';
$iniciales            = initials($empresa ?: $email);
$viewer_id            = $user_id;
$viewer_empresa       = $empresa;
$viewer_logo          = $logo;
$viewer_cultura_tipo  = $cultura_empresa_tipo;

/* ── Doc unread count (for header badge) ── */
$docs_unread_count = 0;
$has_new_docs = false;
if ($conn instanceof mysqli) {
    $stD = $conn->prepare("SELECT COUNT(*) FROM documentos WHERE empresa_id = ? AND estado = 'nuevo'");
    if ($stD) {
        $stD->bind_param("i", $user_id);
        $stD->execute();
        $stD->bind_result($docs_unread_count);
        $stD->fetch();
        $stD->close();
        $has_new_docs = $docs_unread_count > 0;
    }
}

/* ── KPI defaults needed by header ── */
$promedio_general = 0; $aline_class = ''; $aline_label = ''; $aline_icon = '';
$energia_equipo = 0;   $mot_class = '';   $mot_label = '';   $mot_icon  = '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documentos &mdash; <?php echo h($empresa); ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <style>
    /* ===== RESET / BASE ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ===== LAYOUT ===== */
    .dm-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      height: calc(100vh - 72px);
      overflow: hidden;
    }

    /* ===== SIDEBAR ===== */
    .dm-sidebar {
      background: var(--c-primary, #012133);
      color: #fff;
      overflow-y: auto;
      padding: 20px 0 12px;
      display: flex;
      flex-direction: column;
      gap: 0;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,0.15) transparent;
    }
    .dm-sidebar::-webkit-scrollbar { width: 4px; }
    .dm-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }

    .dm-sidebar-section-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.4);
      padding: 14px 20px 6px;
    }

    .dm-nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 20px;
      cursor: pointer;
      border-left: 3px solid transparent;
      transition: background .15s, border-color .15s;
      font-size: 14px;
      font-weight: 500;
      color: rgba(255,255,255,0.78);
      user-select: none;
    }
    .dm-nav-item:hover { background: rgba(255,255,255,0.06); color: #fff; }
    .dm-nav-item.active {
      border-left: 3px solid var(--c-teal, #007a96);
      background: rgba(255,255,255,0.08);
      color: #fff;
    }
    .dm-nav-item .ph { font-size: 18px; flex-shrink: 0; }
    .dm-nav-item .nav-label { flex: 1; }
    .dm-nav-badge {
      font-size: 11px;
      font-weight: 700;
      background: var(--c-accent, #EF7F1B);
      color: #fff;
      border-radius: 20px;
      padding: 1px 7px;
      min-width: 20px;
      text-align: center;
    }
    .dm-nav-badge.gray { background: rgba(255,255,255,0.2); }

    /* Employee list */
    .dm-emp-list { display: none; flex-direction: column; gap: 0; }
    .dm-emp-list.open { display: flex; }
    .dm-emp-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 20px 8px 44px;
      cursor: pointer;
      font-size: 13px;
      color: rgba(255,255,255,0.7);
      transition: background .15s;
      border-left: 3px solid transparent;
    }
    .dm-emp-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .dm-emp-item.active { border-left: 3px solid var(--c-teal, #007a96); background: rgba(255,255,255,0.07); color: #fff; }
    .dm-emp-avatar {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--c-teal, #007a96);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      text-transform: uppercase;
    }
    .dm-emp-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dm-emp-count { font-size: 11px; color: rgba(255,255,255,0.4); }

    /* Sidebar stats */
    .dm-sidebar-stats {
      margin-top: auto;
      padding: 16px 16px 8px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .dm-stat-mini {
      background: rgba(255,255,255,0.07);
      border-radius: 10px;
      padding: 10px 12px;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .dm-stat-mini .val { font-size: 20px; font-weight: 800; color: #fff; }
    .dm-stat-mini .lbl { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: .06em; }
    .dm-stat-mini.accent .val { color: var(--c-accent, #EF7F1B); }
    .dm-stat-mini.teal .val { color: var(--c-teal, #007a96); }
    .dm-stat-mini-wide { grid-column: 1 / -1; }

    /* ===== MAIN ===== */
    .dm-main {
      overflow-y: auto;
      background: #f8f9fb;
      display: flex;
      flex-direction: column;
    }

    /* ===== TOOLBAR ===== */
    .dm-toolbar {
      padding: 20px 24px 0;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      background: #f8f9fb;
      position: sticky;
      top: 0;
      z-index: 10;
      padding-bottom: 16px;
      border-bottom: 1px solid #e8eaf0;
    }
    .dm-toolbar-title {
      font-size: 18px;
      font-weight: 800;
      color: var(--c-primary, #012133);
      white-space: nowrap;
    }
    .dm-search-wrap {
      position: relative;
      flex: 1;
      min-width: 180px;
      max-width: 320px;
    }
    .dm-search-wrap .ph {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 16px;
      pointer-events: none;
    }
    .dm-search {
      width: 100%;
      padding: 8px 12px 8px 34px;
      border: 1.5px solid #dde1e9;
      border-radius: 10px;
      font-size: 13px;
      background: #fff;
      color: var(--c-primary, #012133);
      outline: none;
      transition: border-color .15s;
    }
    .dm-search:focus { border-color: var(--c-teal, #007a96); }
    .dm-filter-select {
      padding: 8px 10px;
      border: 1.5px solid #dde1e9;
      border-radius: 10px;
      font-size: 13px;
      background: #fff;
      color: var(--c-primary, #012133);
      outline: none;
      cursor: pointer;
      transition: border-color .15s;
    }
    .dm-filter-select:focus { border-color: var(--c-teal, #007a96); }

    .dm-view-toggle {
      display: flex;
      gap: 4px;
      background: #e8eaf0;
      border-radius: 10px;
      padding: 3px;
    }
    .dm-view-btn {
      width: 32px;
      height: 32px;
      border: none;
      border-radius: 8px;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      transition: background .15s, color .15s;
    }
    .dm-view-btn.active { background: #fff; color: var(--c-primary, #012133); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

    .btn-upload {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--c-teal, #007a96);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      white-space: nowrap;
      transition: background .15s, transform .1s;
      font-family: inherit;
    }
    .btn-upload:hover { background: #005f77; transform: translateY(-1px); }
    .btn-upload .ph { font-size: 16px; }

    /* Mobile hamburger */
    .dm-hamburger {
      display: none;
      background: none;
      border: none;
      font-size: 22px;
      color: var(--c-primary, #012133);
      cursor: pointer;
      padding: 4px;
    }

    /* ===== GRID CONTENT AREA ===== */
    .dm-content { padding: 20px 24px 32px; flex: 1; }

    /* GRID VIEW */
    .dm-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 16px;
    }
    .dm-grid.list-mode { display: flex; flex-direction: column; gap: 0; }

    /* ===== DOC CARD (grid) ===== */
    .doc-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(1,33,51,0.07);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      transition: box-shadow .2s, transform .2s;
      position: relative;
    }
    .doc-card:hover { box-shadow: 0 6px 24px rgba(1,33,51,0.13); transform: translateY(-2px); }

    .doc-card-top {
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    .doc-type-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .doc-type-icon.pdf   { background: #fff0f0; color: #e53935; }
    .doc-type-icon.drive { background: #eafaf1; color: #34A853; }
    .doc-type-icon.ms    { background: #e8f4ff; color: #0078D4; }
    .doc-type-icon.otro  { background: #f3f4f6; color: #64748b; }

    .doc-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; margin-left: auto; }
    .badge {
      font-size: 10px;
      font-weight: 700;
      border-radius: 20px;
      padding: 2px 8px;
      white-space: nowrap;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .badge-nuevo   { background: #FFF3E0; color: #E65100; }
    .badge-leido   { background: #f1f5f9; color: #64748b; }
    .badge-archivado { background: #e2e8f0; color: #475569; }

    /* Category badges */
    .cat-badge { font-size: 11px; font-weight: 600; border-radius: 20px; padding: 2px 9px; white-space: nowrap; }
    .cat-contrato   { background: #ede9fe; color: #7c3aed; }
    .cat-politica   { background: #dbeafe; color: #1d4ed8; }
    .cat-onboarding { background: #dcfce7; color: #166534; }
    .cat-formacion  { background: #ccfbf1; color: #0f766e; }
    .cat-evaluacion { background: #fff7ed; color: #c2410c; }
    .cat-certificado{ background: #fef9c3; color: #854d0e; }
    .cat-reglamento { background: #fee2e2; color: #b91c1c; }
    .cat-beneficios { background: #fce7f3; color: #9d174d; }
    .cat-comunicado { background: #e0e7ff; color: #3730a3; }
    .cat-general    { background: #f1f5f9; color: #475569; }

    .doc-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--c-primary, #012133);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.4;
    }
    .doc-desc {
      font-size: 12px;
      color: #64748b;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.5;
    }
    .doc-emp-chip {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #e0f4f8;
      color: var(--c-teal, #007a96);
      border-radius: 20px;
      padding: 3px 10px 3px 4px;
      font-size: 11px;
      font-weight: 600;
      width: fit-content;
      max-width: 100%;
    }
    .doc-emp-chip-avatar {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--c-teal, #007a96);
      color: #fff;
      font-size: 9px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      text-transform: uppercase;
      flex-shrink: 0;
    }
    .doc-emp-chip-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .doc-card-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: auto;
      padding-top: 6px;
      border-top: 1px solid #f1f5f9;
    }
    .doc-date { font-size: 11px; color: #94a3b8; }
    .doc-actions { display: flex; gap: 4px; }
    .doc-action-btn {
      width: 30px;
      height: 30px;
      border: none;
      border-radius: 8px;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      transition: background .15s, color .15s;
    }
    .doc-action-btn:hover { background: #f1f5f9; color: var(--c-primary, #012133); }
    .doc-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
    .doc-action-btn.view:hover { background: #e0f4f8; color: var(--c-teal, #007a96); }

    /* ===== LIST VIEW ===== */
    .doc-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 12px 16px;
      background: #fff;
      border-bottom: 1px solid #f1f5f9;
      transition: background .15s;
      border-radius: 0;
    }
    .doc-row:first-child { border-radius: 12px 12px 0 0; }
    .doc-row:last-child  { border-radius: 0 0 12px 12px; border-bottom: none; }
    .doc-row:only-child  { border-radius: 12px; }
    .doc-row:hover { background: #f8faff; }
    .doc-row .doc-type-icon { width: 36px; height: 36px; font-size: 18px; border-radius: 9px; flex-shrink: 0; }
    .doc-row-info { flex: 1; min-width: 0; }
    .doc-row-title { font-size: 14px; font-weight: 700; color: var(--c-primary, #012133); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .doc-row-desc  { font-size: 12px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .doc-row-cat   { width: 110px; flex-shrink: 0; }
    .doc-row-emp   { width: 140px; flex-shrink: 0; font-size: 12px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .doc-row-date  { width: 90px; flex-shrink: 0; font-size: 12px; color: #94a3b8; }
    .doc-row-status{ width: 80px; flex-shrink: 0; }
    .doc-row-actions { display: flex; gap: 4px; flex-shrink: 0; }

    /* ===== EMPTY STATE ===== */
    .dm-empty {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 60px 20px;
      text-align: center;
      color: #94a3b8;
      gap: 16px;
    }
    .dm-empty .empty-icon { font-size: 56px; color: #cbd5e1; }
    .dm-empty h3 { font-size: 18px; font-weight: 700; color: #475569; }
    .dm-empty p  { font-size: 14px; max-width: 320px; line-height: 1.6; }
    .dm-empty .btn-upload { margin-top: 8px; }

    /* ===== MODAL ===== */
    .modal-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      background: rgba(1,33,51,0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      opacity: 0;
      pointer-events: none;
      transition: opacity .2s;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal-content {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 540px;
      max-height: 90vh;
      overflow-y: auto;
      padding: 0;
      transform: translateY(16px);
      transition: transform .2s;
    }
    .modal-overlay.open .modal-content { transform: translateY(0); }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 22px 24px 16px;
      border-bottom: 1px solid #f1f5f9;
    }
    .modal-header h2 { font-size: 16px; font-weight: 800; color: var(--c-primary, #012133); }
    .modal-close {
      background: none;
      border: none;
      font-size: 20px;
      color: #94a3b8;
      cursor: pointer;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      transition: background .15s, color .15s;
    }
    .modal-close:hover { background: #f1f5f9; color: var(--c-primary, #012133); }

    .modal-body { padding: 24px; }

    /* Step indicators */
    .modal-steps {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: center;
      padding: 0 24px 20px;
    }
    .modal-step-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #e2e8f0;
      transition: background .2s, width .2s;
    }
    .modal-step-dot.active { background: var(--c-teal, #007a96); width: 24px; border-radius: 4px; }
    .modal-step-dot.done   { background: var(--c-teal, #007a96); }

    /* Type selector cards */
    .tipo-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }
    .tipo-card {
      border: 2px solid #e8eaf0;
      border-radius: 14px;
      padding: 18px 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      text-align: center;
      transition: border-color .15s, box-shadow .15s, transform .1s;
      background: #fff;
    }
    .tipo-card:hover { border-color: var(--c-teal, #007a96); box-shadow: 0 4px 16px rgba(0,122,150,0.12); transform: translateY(-2px); }
    .tipo-card-icon { font-size: 32px; }
    .tipo-card-icon.pdf   { color: #e53935; }
    .tipo-card-icon.drive { color: #34A853; }
    .tipo-card-icon.ms    { color: #0078D4; }
    .tipo-card-title { font-size: 13px; font-weight: 700; color: var(--c-primary, #012133); }
    .tipo-card-desc  { font-size: 11px; color: #64748b; line-height: 1.5; }

    /* Form fields */
    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 16px; }
    .form-group label { font-size: 13px; font-weight: 600; color: #374151; }
    .form-group label span.req { color: var(--c-accent, #EF7F1B); }
    .form-input, .form-select, .form-textarea {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid #dde1e9;
      border-radius: 10px;
      font-size: 14px;
      color: var(--c-primary, #012133);
      background: #fff;
      outline: none;
      transition: border-color .15s;
      font-family: inherit;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--c-teal, #007a96); }
    .form-textarea { resize: vertical; min-height: 80px; }
    .form-file-area {
      border: 2px dashed #dde1e9;
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      cursor: pointer;
      transition: border-color .15s, background .15s;
    }
    .form-file-area:hover { border-color: var(--c-teal, #007a96); background: #f0fbfd; }
    .form-file-area .ph  { font-size: 28px; color: #94a3b8; display: block; margin-bottom: 8px; }
    .form-file-area p    { font-size: 13px; color: #64748b; }
    .form-file-area input[type=file] { display: none; }
    .form-file-label { font-size: 12px; color: var(--c-teal, #007a96); font-weight: 600; cursor: pointer; }

    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      padding: 16px 24px;
      border-top: 1px solid #f1f5f9;
    }
    .btn-secondary {
      padding: 9px 20px;
      border: 1.5px solid #dde1e9;
      border-radius: 10px;
      background: #fff;
      color: #475569;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      transition: border-color .15s, background .15s;
    }
    .btn-secondary:hover { border-color: #94a3b8; background: #f8f9fb; }
    .btn-primary {
      padding: 9px 22px;
      border: none;
      border-radius: 10px;
      background: var(--c-teal, #007a96);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: background .15s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .btn-primary:hover { background: #005f77; }
    .btn-primary:disabled { background: #94a3b8; cursor: not-allowed; }

    /* Success screen */
    .upload-success {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
      padding: 12px 0 8px;
      text-align: center;
    }
    .upload-success .success-icon { font-size: 52px; color: #22c55e; }
    .upload-success h3 { font-size: 17px; font-weight: 800; color: var(--c-primary, #012133); }
    .upload-success p  { font-size: 14px; color: #64748b; max-width: 300px; line-height: 1.6; }

    /* Progress bar */
    .upload-progress { width: 100%; height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-top: 4px; }
    .upload-progress-bar { height: 100%; background: var(--c-teal, #007a96); border-radius: 4px; width: 0; transition: width .3s; }

    /* ===== SIDEBAR OVERLAY (mobile) ===== */
    .dm-sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(1,33,51,0.5);
      z-index: 998;
    }

    /* ===== MOBILE ===== */
    @media (max-width: 767px) {
      .dm-layout { grid-template-columns: 1fr; height: auto; overflow: visible; }
      .dm-sidebar {
        position: fixed;
        left: 0; top: 72px; bottom: 0;
        width: 280px;
        z-index: 999;
        transform: translateX(-100%);
        transition: transform .25s ease;
      }
      .dm-sidebar.mobile-open { transform: translateX(0); }
      .dm-sidebar-overlay.active { display: block; }
      .dm-hamburger { display: flex; align-items: center; }
      .dm-main { min-height: calc(100vh - 72px); }
      .dm-toolbar { padding: 12px 14px 10px; gap: 8px; }
      .dm-search-wrap { max-width: none; }
      .dm-filter-select { display: none; }
      .dm-content { padding: 14px 14px 32px; }
      .dm-grid { grid-template-columns: 1fr; }
      .tipo-cards { grid-template-columns: 1fr; }
      .doc-row-cat, .doc-row-emp, .doc-row-date { display: none; }
    }

    /* Scrollbar style for main */
    .dm-main::-webkit-scrollbar { width: 6px; }
    .dm-main::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
  </style>
</head>
<body>
<?php require 'a-header-desktop-brand.php'; ?>

<!-- Mobile sidebar overlay -->
<div class="dm-sidebar-overlay" id="dmOverlay" onclick="closeMobileSidebar()"></div>

<!-- MAIN LAYOUT -->
<div class="dm-layout">

  <!-- ===== SIDEBAR ===== -->
  <aside class="dm-sidebar" id="dmSidebar">
    <div class="dm-sidebar-section-label">Documentos</div>

    <div class="dm-nav-item active" id="nav-todos" onclick="setSidebarFilter('todos', null)">
      <i class="ph ph-files"></i>
      <span class="nav-label">Todos los documentos</span>
      <span class="dm-nav-badge gray" id="badge-todos">0</span>
    </div>

    <div class="dm-nav-item" id="nav-empresa" onclick="setSidebarFilter('empresa', null)">
      <i class="ph ph-buildings"></i>
      <span class="nav-label">Empresa</span>
      <span class="dm-nav-badge gray" id="badge-empresa">0</span>
    </div>

    <div class="dm-nav-item" id="nav-empleado" onclick="toggleEmpList()">
      <i class="ph ph-users-three"></i>
      <span class="nav-label">Por empleado</span>
      <i class="ph ph-caret-down" id="emp-caret" style="font-size:13px;color:rgba(255,255,255,0.4);"></i>
    </div>
    <div class="dm-emp-list" id="dmEmpList">
      <!-- Rendered by JS -->
    </div>

    <div class="dm-nav-item" id="nav-nuevos" onclick="setSidebarFilter('nuevos', null)">
      <i class="ph ph-star"></i>
      <span class="nav-label">Nuevos</span>
      <span class="dm-nav-badge" id="badge-nuevos">0</span>
    </div>

    <div class="dm-nav-item" id="nav-archivados" onclick="setSidebarFilter('archivados', null)">
      <i class="ph ph-archive"></i>
      <span class="nav-label">Archivados</span>
      <span class="dm-nav-badge gray" id="badge-archivados">0</span>
    </div>

    <!-- Stats -->
    <div class="dm-sidebar-stats">
      <div class="dm-stat-mini">
        <span class="val" id="stat-total">0</span>
        <span class="lbl">Total</span>
      </div>
      <div class="dm-stat-mini accent">
        <span class="val" id="stat-nuevos">0</span>
        <span class="lbl">Nuevos</span>
      </div>
      <div class="dm-stat-mini">
        <span class="val" id="stat-pdfs">0</span>
        <span class="lbl">PDFs</span>
      </div>
      <div class="dm-stat-mini teal">
        <span class="val" id="stat-links">0</span>
        <span class="lbl">Links</span>
      </div>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="dm-main">

    <!-- TOOLBAR -->
    <div class="dm-toolbar">
      <button class="dm-hamburger" onclick="toggleMobileSidebar()" aria-label="Menú">
        <i class="ph ph-list"></i>
      </button>

      <span class="dm-toolbar-title" id="dmToolbarTitle">Todos los documentos</span>

      <div class="dm-search-wrap">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" class="dm-search" id="dmSearch" placeholder="Buscar documentos…" oninput="onSearchInput()">
      </div>

      <select class="dm-filter-select" id="filterTipo" onchange="loadDocs()">
        <option value="">Tipo: Todos</option>
        <option value="pdf">PDF</option>
        <option value="drive">Google Drive</option>
        <option value="microsoft">Microsoft</option>
      </select>

      <select class="dm-filter-select" id="filterCat" onchange="loadDocs()">
        <option value="">Categoría: Todas</option>
        <option value="contrato">Contrato</option>
        <option value="politica">Política</option>
        <option value="onboarding">Onboarding</option>
        <option value="formacion">Formación</option>
        <option value="evaluacion">Evaluación</option>
        <option value="certificado">Certificado</option>
        <option value="reglamento">Reglamento</option>
        <option value="beneficios">Beneficios</option>
        <option value="comunicado">Comunicado</option>
        <option value="general">General</option>
      </select>

      <div class="dm-view-toggle">
        <button class="dm-view-btn active" id="btnGrid" onclick="setView('grid')" title="Vista cuadrícula">
          <i class="ph ph-squares-four"></i>
        </button>
        <button class="dm-view-btn" id="btnList" onclick="setView('list')" title="Vista lista">
          <i class="ph ph-rows"></i>
        </button>
      </div>

      <button class="btn-upload" onclick="openUploadModal()">
        <i class="ph ph-upload-simple"></i>
        Subir documento
      </button>
    </div>

    <!-- DOCUMENT GRID/LIST -->
    <div class="dm-content">
      <div class="dm-grid" id="dmGrid">
        <!-- Rendered by JS -->
      </div>
      <!-- Empty state -->
      <div class="dm-empty" id="dmEmpty" style="display:none;">
        <i class="ph ph-files empty-icon"></i>
        <h3>No hay documentos aún</h3>
        <p>Sube el primer documento para tu equipo o selecciona otro filtro.</p>
        <button class="btn-upload" onclick="openUploadModal()">
          <i class="ph ph-upload-simple"></i>
          Subir documento
        </button>
      </div>
    </div>

  </main>
</div>

<!-- ===== UPLOAD MODAL ===== -->
<div class="modal-overlay" id="uploadModal">
  <div class="modal-content">

    <div class="modal-header">
      <h2 id="modalTitle">Subir documento</h2>
      <button class="modal-close" onclick="closeUploadModal()"><i class="ph ph-x"></i></button>
    </div>

    <div class="modal-steps" id="modalSteps">
      <div class="modal-step-dot active" id="dot0"></div>
      <div class="modal-step-dot" id="dot1"></div>
      <div class="modal-step-dot" id="dot2"></div>
    </div>

    <!-- Step 0: Select type -->
    <div class="modal-body" id="step0">
      <p style="font-size:14px;color:#64748b;margin-bottom:18px;text-align:center;">
        ¿Qué tipo de documento quieres subir?
      </p>
      <div class="tipo-cards">
        <div class="tipo-card" onclick="selectTipo('pdf')">
          <i class="ph ph-file-pdf tipo-card-icon pdf"></i>
          <div class="tipo-card-title">PDF</div>
          <div class="tipo-card-desc">Sube un archivo PDF hasta 20&nbsp;MB</div>
        </div>
        <div class="tipo-card" onclick="selectTipo('drive')">
          <i class="ph ph-cloud tipo-card-icon drive"></i>
          <div class="tipo-card-title">Google Drive</div>
          <div class="tipo-card-desc">Pega el link compartido de Drive</div>
        </div>
        <div class="tipo-card" onclick="selectTipo('microsoft')">
          <i class="ph ph-cloud-arrow-up tipo-card-icon ms"></i>
          <div class="tipo-card-title">Microsoft</div>
          <div class="tipo-card-desc">OneDrive, SharePoint o Teams</div>
        </div>
      </div>
    </div>

    <!-- Step 1: Form -->
    <div class="modal-body" id="step1" style="display:none;">
      <form id="uploadForm" onsubmit="return false;">

        <div class="form-group">
          <label>Título <span class="req">*</span></label>
          <input type="text" class="form-input" id="fTitulo" placeholder="Nombre del documento" required>
        </div>

        <div class="form-group">
          <label>Categoría <span class="req">*</span></label>
          <select class="form-select" id="fCategoria">
            <option value="">Seleccionar categoría</option>
            <option value="contrato">Contrato</option>
            <option value="politica">Política</option>
            <option value="onboarding">Onboarding</option>
            <option value="formacion">Formación</option>
            <option value="evaluacion">Evaluación</option>
            <option value="certificado">Certificado</option>
            <option value="reglamento">Reglamento</option>
            <option value="beneficios">Beneficios</option>
            <option value="comunicado">Comunicado</option>
            <option value="general">General</option>
          </select>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <textarea class="form-textarea" id="fDescripcion" placeholder="Breve descripción (opcional)"></textarea>
        </div>

        <div class="form-group">
          <label>Empleado asignado <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
          <select class="form-select" id="fEmpleado">
            <option value="">Sin asignar (documento de empresa)</option>
          </select>
        </div>

        <!-- PDF file input -->
        <div class="form-group" id="fFileGroup" style="display:none;">
          <label>Archivo PDF <span class="req">*</span></label>
          <div class="form-file-area" onclick="document.getElementById('fFile').click()">
            <i class="ph ph-file-pdf"></i>
            <p id="fFileName">Haz clic para seleccionar un PDF</p>
            <span class="form-file-label">Seleccionar archivo</span>
            <input type="file" id="fFile" accept=".pdf,application/pdf" onchange="onFileChange()">
          </div>
        </div>

        <!-- Link input (Drive / Microsoft) -->
        <div class="form-group" id="fUrlGroup" style="display:none;">
          <label id="fUrlLabel">URL del documento <span class="req">*</span></label>
          <input type="url" class="form-input" id="fUrl" placeholder="https://...">
        </div>

        <!-- Upload progress -->
        <div id="progressWrap" style="display:none;">
          <div class="upload-progress"><div class="upload-progress-bar" id="progressBar"></div></div>
          <p style="font-size:12px;color:#64748b;margin-top:6px;" id="progressLabel">Subiendo…</p>
        </div>

      </form>
    </div>

    <!-- Step 2: Success -->
    <div class="modal-body" id="step2" style="display:none;">
      <div class="upload-success">
        <i class="ph ph-check-circle success-icon"></i>
        <h3>¡Documento subido!</h3>
        <p>El documento ha sido guardado correctamente y ya está disponible en tu biblioteca.</p>
      </div>
    </div>

    <div class="modal-footer" id="modalFooter">
      <!-- Populated by JS based on step -->
    </div>

  </div>
</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
/* ── Constants ── */
const USER_ID = <?= (int)$user_id ?>;
const BACKEND = 'documentos_backend.php';

/* ── State ── */
let currentView    = 'grid';
let currentScope   = 'todos';
let currentEmpId   = null;
let searchTimer    = null;
let uploadTipo     = null;
let uploadStep     = 0;
let empleadosCache = [];

/* ── Category config ── */
const CAT_LABELS = {
  contrato:'Contrato', politica:'Política', onboarding:'Onboarding',
  formacion:'Formación', evaluacion:'Evaluación', certificado:'Certificado',
  reglamento:'Reglamento', beneficios:'Beneficios', comunicado:'Comunicado',
  general:'General'
};
const CAT_CLASS = {
  contrato:'cat-contrato', politica:'cat-politica', onboarding:'cat-onboarding',
  formacion:'cat-formacion', evaluacion:'cat-evaluacion', certificado:'cat-certificado',
  reglamento:'cat-reglamento', beneficios:'cat-beneficios', comunicado:'cat-comunicado',
  general:'cat-general'
};

/* ─────────────────────────────────────────
   STATS
───────────────────────────────────────── */
function loadStats() {
  fetch(BACKEND + '?action=stats')
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      const s = d.stats;
      setText('stat-total',     s.total     || 0);
      setText('stat-nuevos',    s.nuevos    || 0);
      setText('stat-pdfs',      s.pdfs      || 0);
      setText('stat-links',     s.links     || 0);
      setText('badge-todos',    s.total     || 0);
      setText('badge-empresa',  s.empresa   || 0);
      setText('badge-nuevos',   s.nuevos    || 0);
      setText('badge-archivados', s.archivados || 0);
    })
    .catch(() => {});
}

/* ─────────────────────────────────────────
   EMPLOYEES
───────────────────────────────────────── */
function loadEmpleados() {
  fetch(BACKEND + '?action=listar_empleados')
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      empleadosCache = d.empleados || [];
      renderEmpList(empleadosCache);
      populateEmpSelect(empleadosCache);
    })
    .catch(() => {});
}

function renderEmpList(emps) {
  const el = document.getElementById('dmEmpList');
  if (!emps.length) { el.innerHTML = '<div class="dm-emp-item" style="opacity:.5;cursor:default;">Sin empleados</div>'; return; }
  el.innerHTML = emps.map(e => {
    const initials = empInitials(e.nombre);
    return `<div class="dm-emp-item" id="nav-emp-${e.id}" onclick="setSidebarFilter('empleado', ${e.id})">
      <div class="dm-emp-avatar">${esc(initials)}</div>
      <span class="dm-emp-name">${esc(e.nombre)}</span>
      <span class="dm-emp-count">${e.doc_count || 0}</span>
    </div>`;
  }).join('');
}

function populateEmpSelect(emps) {
  const sel = document.getElementById('fEmpleado');
  sel.innerHTML = '<option value="">Sin asignar (documento de empresa)</option>';
  emps.forEach(e => {
    const opt = document.createElement('option');
    opt.value = e.id;
    opt.textContent = e.nombre;
    sel.appendChild(opt);
  });
}

function toggleEmpList() {
  const list  = document.getElementById('dmEmpList');
  const caret = document.getElementById('emp-caret');
  const open  = list.classList.toggle('open');
  caret.className = open ? 'ph ph-caret-up' : 'ph ph-caret-down';
  caret.style.cssText = 'font-size:13px;color:rgba(255,255,255,0.4);';
}

/* ─────────────────────────────────────────
   DOCS — LOAD & RENDER
───────────────────────────────────────── */
function buildFilters() {
  const q    = document.getElementById('dmSearch').value.trim();
  const tipo = document.getElementById('filterTipo').value;
  const cat  = document.getElementById('filterCat').value;
  const params = new URLSearchParams({ action: 'listar', scope: currentScope });
  if (currentEmpId) params.append('empleado_id', currentEmpId);
  if (q)    params.append('q', q);
  if (tipo) params.append('tipo', tipo);
  if (cat)  params.append('categoria', cat);
  return params;
}

function loadDocs() {
  const grid  = document.getElementById('dmGrid');
  const empty = document.getElementById('dmEmpty');
  grid.innerHTML = '<div style="grid-column:1/-1;padding:40px;text-align:center;color:#94a3b8;font-size:14px;"><i class="ph ph-circle-notch" style="font-size:24px;"></i><br>Cargando…</div>';
  empty.style.display = 'none';

  fetch(BACKEND + '?' + buildFilters())
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { grid.innerHTML = ''; showEmpty(); return; }
      const docs = d.documentos || [];
      if (!docs.length) { grid.innerHTML = ''; showEmpty(); return; }
      grid.className = currentView === 'list' ? 'dm-grid list-mode' : 'dm-grid';
      grid.innerHTML = docs.map(doc =>
        currentView === 'list' ? renderDocRow(doc) : renderDocCard(doc)
      ).join('');
    })
    .catch(() => { grid.innerHTML = ''; showEmpty(); });
}

function showEmpty() {
  document.getElementById('dmEmpty').style.display = 'flex';
}

/* ─────────────────────────────────────────
   RENDER — CARD
───────────────────────────────────────── */
function renderDocCard(doc) {
  const typeIcon = typeIconHtml(doc.tipo);
  const catBadge = catBadgeHtml(doc.categoria);
  const statBadge = statBadgeHtml(doc.estado);
  const empChip = doc.empleado_nombre
    ? `<div class="doc-emp-chip">
         <div class="doc-emp-chip-avatar">${esc(empInitials(doc.empleado_nombre))}</div>
         <span class="doc-emp-chip-name">${esc(doc.empleado_nombre)}</span>
       </div>` : '';
  const desc = doc.descripcion
    ? `<div class="doc-desc">${esc(doc.descripcion)}</div>` : '';

  return `<div class="doc-card" data-id="${doc.id}">
    <div class="doc-card-top">
      ${typeIcon}
      <div class="doc-badges">${catBadge}${statBadge}</div>
    </div>
    <div class="doc-title">${esc(doc.titulo)}</div>
    ${desc}
    ${empChip}
    <div class="doc-card-bottom">
      <span class="doc-date">${relativeDate(doc.created_at)}</span>
      <div class="doc-actions">
        <button class="doc-action-btn view" onclick='viewDoc(${jsonDoc(doc)})' title="Ver documento">
          <i class="ph ph-arrow-square-out"></i>
        </button>
        <button class="doc-action-btn" onclick='archiveDoc(${doc.id}, ${doc.estado === "archivado" ? 1 : 0})' title="${doc.estado === 'archivado' ? 'Desarchivar' : 'Archivar'}">
          <i class="ph ph-${doc.estado === 'archivado' ? 'arrow-counter-clockwise' : 'archive'}"></i>
        </button>
        <button class="doc-action-btn danger" onclick='deleteDoc(${doc.id}, ${JSON.stringify(doc.titulo)})' title="Eliminar">
          <i class="ph ph-trash"></i>
        </button>
      </div>
    </div>
  </div>`;
}

/* ─────────────────────────────────────────
   RENDER — ROW
───────────────────────────────────────── */
function renderDocRow(doc) {
  const typeIcon = typeIconHtml(doc.tipo);
  const catBadge = catBadgeHtml(doc.categoria);
  const statBadge = statBadgeHtml(doc.estado);
  const empName = doc.empleado_nombre || '<span style="color:#cbd5e1">—</span>';

  return `<div class="doc-row" data-id="${doc.id}">
    ${typeIcon}
    <div class="doc-row-info">
      <div class="doc-row-title">${esc(doc.titulo)}</div>
      <div class="doc-row-desc">${doc.descripcion ? esc(doc.descripcion) : '<span style="color:#cbd5e1">Sin descripción</span>'}</div>
    </div>
    <div class="doc-row-cat">${catBadge}</div>
    <div class="doc-row-emp">${typeof empName === 'string' && doc.empleado_nombre ? esc(doc.empleado_nombre) : empName}</div>
    <div class="doc-row-date">${relativeDate(doc.created_at)}</div>
    <div class="doc-row-status">${statBadge}</div>
    <div class="doc-row-actions">
      <button class="doc-action-btn view" onclick='viewDoc(${jsonDoc(doc)})' title="Ver">
        <i class="ph ph-arrow-square-out"></i>
      </button>
      <button class="doc-action-btn" onclick='archiveDoc(${doc.id}, ${doc.estado === "archivado" ? 1 : 0})' title="${doc.estado === 'archivado' ? 'Desarchivar' : 'Archivar'}">
        <i class="ph ph-${doc.estado === 'archivado' ? 'arrow-counter-clockwise' : 'archive'}"></i>
      </button>
      <button class="doc-action-btn danger" onclick='deleteDoc(${doc.id}, ${JSON.stringify(doc.titulo)})' title="Eliminar">
        <i class="ph ph-trash"></i>
      </button>
    </div>
  </div>`;
}

/* ─────────────────────────────────────────
   HELPERS
───────────────────────────────────────── */
function typeIconHtml(tipo) {
  const map = {
    pdf:       ['ph-file-pdf',       'pdf'],
    drive:     ['ph-cloud',          'drive'],
    microsoft: ['ph-cloud-arrow-up', 'ms'],
  };
  const [icon, cls] = map[tipo] || ['ph-file', 'otro'];
  return `<div class="doc-type-icon ${cls}"><i class="ph ${icon}"></i></div>`;
}

function catBadgeHtml(cat) {
  const label = CAT_LABELS[cat] || cat || 'General';
  const cls   = CAT_CLASS[cat]  || 'cat-general';
  return `<span class="cat-badge ${cls}">${esc(label)}</span>`;
}

function statBadgeHtml(estado) {
  const map = {
    nuevo:     ['badge-nuevo',    'Nuevo'],
    leido:     ['badge-leido',    'Leído'],
    archivado: ['badge-archivado','Archivado'],
  };
  const [cls, label] = map[estado] || ['badge-leido','Leído'];
  return `<span class="badge ${cls}">${label}</span>`;
}

function relativeDate(dateStr) {
  if (!dateStr) return '—';
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins  = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days  = Math.floor(diff / 86400000);
  if (mins  < 1)  return 'Ahora mismo';
  if (mins  < 60) return `hace ${mins} min`;
  if (hours < 24) return `hace ${hours}h`;
  if (days  < 30) return `hace ${days} día${days > 1 ? 's' : ''}`;
  const months = Math.floor(days / 30);
  return `hace ${months} mes${months > 1 ? 'es' : ''}`;
}

function empInitials(name) {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
}

function esc(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function jsonDoc(doc) {
  return JSON.stringify({
    id: doc.id, tipo: doc.tipo, url: doc.url, archivo: doc.archivo, titulo: doc.titulo
  });
}

/* ─────────────────────────────────────────
   VIEW TOGGLE
───────────────────────────────────────── */
function setView(mode) {
  currentView = mode;
  document.getElementById('btnGrid').classList.toggle('active', mode === 'grid');
  document.getElementById('btnList').classList.toggle('active', mode === 'list');
  loadDocs();
}

/* ─────────────────────────────────────────
   SIDEBAR FILTER
───────────────────────────────────────── */
function setSidebarFilter(scope, empId) {
  currentScope  = scope;
  currentEmpId  = empId;

  /* Deactivate all nav items */
  document.querySelectorAll('.dm-nav-item').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.dm-emp-item').forEach(el => el.classList.remove('active'));

  /* Activate correct item */
  if (scope === 'empleado' && empId) {
    document.getElementById(`nav-emp-${empId}`)?.classList.add('active');
    const emp = empleadosCache.find(e => e.id == empId);
    setToolbarTitle(emp ? emp.nombre : 'Por empleado');
    /* Ensure list is open */
    document.getElementById('dmEmpList').classList.add('open');
    document.getElementById('emp-caret').className = 'ph ph-caret-up';
    document.getElementById('emp-caret').style.cssText = 'font-size:13px;color:rgba(255,255,255,0.4);';
  } else {
    const navId = { todos:'nav-todos', empresa:'nav-empresa', nuevos:'nav-nuevos', archivados:'nav-archivados' }[scope];
    if (navId) document.getElementById(navId)?.classList.add('active');
    const titles = { todos:'Todos los documentos', empresa:'Documentos de empresa', nuevos:'Documentos nuevos', archivados:'Archivados' };
    setToolbarTitle(titles[scope] || 'Documentos');
  }

  /* Close mobile sidebar */
  closeMobileSidebar();
  loadDocs();
}

function setToolbarTitle(title) {
  setText('dmToolbarTitle', title);
}

/* ─────────────────────────────────────────
   SEARCH (debounced)
───────────────────────────────────────── */
function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadDocs(), 350);
}

/* ─────────────────────────────────────────
   MOBILE SIDEBAR
───────────────────────────────────────── */
function toggleMobileSidebar() {
  const sidebar  = document.getElementById('dmSidebar');
  const overlay  = document.getElementById('dmOverlay');
  const isOpen   = sidebar.classList.toggle('mobile-open');
  overlay.classList.toggle('active', isOpen);
}
function closeMobileSidebar() {
  document.getElementById('dmSidebar').classList.remove('mobile-open');
  document.getElementById('dmOverlay').classList.remove('active');
}

/* ─────────────────────────────────────────
   DOC ACTIONS
───────────────────────────────────────── */
function viewDoc(doc) {
  if (doc.tipo === 'pdf' && doc.archivo) {
    window.open('uploads/documentos/' + encodeURIComponent(doc.archivo), '_blank');
  } else if (doc.url) {
    window.open(doc.url, '_blank');
  } else {
    alert('Este documento no tiene un archivo o enlace disponible.');
    return;
  }
  /* Mark as read */
  markRead(doc.id);
}

function markRead(id) {
  const fd = new FormData();
  fd.append('action', 'marcar_leido');
  fd.append('id', id);
  fetch(BACKEND, { method: 'POST', body: fd })
    .then(() => loadStats())
    .catch(() => {});
}

function archiveDoc(id, isArchived) {
  const fd = new FormData();
  fd.append('action', 'archivar');
  fd.append('id', id);
  fd.append('archivar', isArchived ? 0 : 1);
  fetch(BACKEND, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.ok) { loadDocs(); loadStats(); }
      else alert(d.error || 'Error al archivar el documento.');
    })
    .catch(() => alert('Error de conexión.'));
}

function deleteDoc(id, title) {
  if (!confirm(`¿Eliminar el documento "${title}"? Esta acción no se puede deshacer.`)) return;
  const fd = new FormData();
  fd.append('action', 'eliminar');
  fd.append('id', id);
  fetch(BACKEND, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.ok) { loadDocs(); loadStats(); }
      else alert(d.error || 'Error al eliminar el documento.');
    })
    .catch(() => alert('Error de conexión.'));
}

/* ─────────────────────────────────────────
   UPLOAD MODAL
───────────────────────────────────────── */
function openUploadModal() {
  uploadStep = 0;
  uploadTipo = null;
  goToStep(0);
  document.getElementById('uploadModal').classList.add('open');
}

function closeUploadModal() {
  document.getElementById('uploadModal').classList.remove('open');
  /* Reset form */
  setTimeout(() => {
    document.getElementById('uploadForm').reset();
    setText('fFileName', 'Haz clic para seleccionar un PDF');
    document.getElementById('progressWrap').style.display = 'none';
    document.getElementById('progressBar').style.width = '0';
  }, 200);
}

function goToStep(step) {
  uploadStep = step;
  ['step0','step1','step2'].forEach((id, i) => {
    document.getElementById(id).style.display = i === step ? '' : 'none';
  });
  [0,1,2].forEach(i => {
    const dot = document.getElementById('dot' + i);
    dot.className = 'modal-step-dot' + (i === step ? ' active' : (i < step ? ' done' : ''));
  });

  const footer = document.getElementById('modalFooter');
  const titles = ['Subir documento', 'Detalles del documento', '¡Listo!'];
  setText('modalTitle', titles[step] || 'Subir documento');

  if (step === 0) {
    footer.innerHTML = '';
  } else if (step === 1) {
    footer.innerHTML = `
      <button class="btn-secondary" onclick="goToStep(0)"><i class="ph ph-arrow-left" style="margin-right:4px;"></i>Atrás</button>
      <button class="btn-primary" onclick="submitUpload()"><i class="ph ph-upload-simple"></i>Subir</button>`;
  } else {
    footer.innerHTML = `
      <button class="btn-primary" onclick="closeUploadModal()"><i class="ph ph-check"></i>Cerrar</button>`;
  }
}

function selectTipo(tipo) {
  uploadTipo = tipo;
  /* Show/hide conditional fields */
  document.getElementById('fFileGroup').style.display = tipo === 'pdf'       ? '' : 'none';
  document.getElementById('fUrlGroup').style.display  = tipo !== 'pdf'       ? '' : 'none';
  if (tipo === 'drive') {
    document.getElementById('fUrlLabel').innerHTML = 'URL de Google Drive <span class="req">*</span>';
    document.getElementById('fUrl').placeholder = 'https://drive.google.com/...';
  } else if (tipo === 'microsoft') {
    document.getElementById('fUrlLabel').innerHTML = 'URL de Microsoft (OneDrive / SharePoint) <span class="req">*</span>';
    document.getElementById('fUrl').placeholder = 'https://sharepoint.com/...';
  }
  goToStep(1);
}

function onFileChange() {
  const file = document.getElementById('fFile').files[0];
  setText('fFileName', file ? file.name : 'Haz clic para seleccionar un PDF');
}

function submitUpload() {
  const titulo    = document.getElementById('fTitulo').value.trim();
  const categoria = document.getElementById('fCategoria').value;
  const desc      = document.getElementById('fDescripcion').value.trim();
  const empId     = document.getElementById('fEmpleado').value;

  if (!titulo)    { alert('El título es obligatorio.');    return; }
  if (!categoria) { alert('Selecciona una categoría.');    return; }

  const fd = new FormData();
  fd.append('action', 'subir');
  fd.append('titulo', titulo);
  fd.append('categoria', categoria);
  fd.append('descripcion', desc);
  fd.append('tipo', uploadTipo);
  if (empId) fd.append('empleado_id', empId);

  if (uploadTipo === 'pdf') {
    const file = document.getElementById('fFile').files[0];
    if (!file) { alert('Selecciona un archivo PDF.'); return; }
    if (file.size > 20 * 1024 * 1024) { alert('El archivo supera los 20 MB.'); return; }
    fd.append('archivo', file);
  } else {
    const url = document.getElementById('fUrl').value.trim();
    if (!url) { alert('El enlace es obligatorio.'); return; }
    fd.append('url', url);
  }

  /* Show progress */
  const progressWrap = document.getElementById('progressWrap');
  const progressBar  = document.getElementById('progressBar');
  const progressLbl  = document.getElementById('progressLabel');
  progressWrap.style.display = '';
  progressBar.style.width = '0';
  progressLbl.textContent = 'Subiendo…';

  /* Disable submit button */
  const footer = document.getElementById('modalFooter');
  const submitBtn = footer.querySelector('.btn-primary');
  if (submitBtn) submitBtn.disabled = true;

  /* Fake progress animation */
  let pct = 0;
  const interval = setInterval(() => {
    pct = Math.min(pct + Math.random() * 15, 90);
    progressBar.style.width = pct + '%';
  }, 200);

  fetch(BACKEND, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      clearInterval(interval);
      if (d.ok) {
        progressBar.style.width = '100%';
        progressLbl.textContent = '¡Subido correctamente!';
        setTimeout(() => {
          goToStep(2);
          loadDocs();
          loadStats();
          loadEmpleados();
        }, 600);
      } else {
        progressWrap.style.display = 'none';
        if (submitBtn) submitBtn.disabled = false;
        alert(d.error || 'Error al subir el documento. Inténtalo de nuevo.');
      }
    })
    .catch(() => {
      clearInterval(interval);
      progressWrap.style.display = 'none';
      if (submitBtn) submitBtn.disabled = false;
      alert('Error de conexión. Comprueba tu conexión e inténtalo de nuevo.');
    });
}

/* ─────────────────────────────────────────
   INIT
───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadEmpleados();
  loadDocs();
});

/* Close modal on overlay click */
document.getElementById('uploadModal').addEventListener('click', function(e) {
  if (e.target === this) closeUploadModal();
});
</script>

</body>
</html>
