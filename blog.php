<?php
/**
 * Valírica — Blog (listado público)
 * SEO-optimizado: JSON-LD WebSite + Blog, OG, Twitter Cards, canonical, hreflang
 */
require_once 'config.php';

// ─── Parámetros ────────────────────────────────────────────────────────────
$page       = max(1, (int)($_GET['p'] ?? 1));
$category   = trim($_GET['cat'] ?? '');
$per_page   = 9;
$offset     = ($page - 1) * $per_page;
$base_url   = 'https://valirica.com';
$canonical  = $base_url . '/blog' . ($page > 1 ? '?p=' . $page : '') . ($category ? ($page > 1 ? '&' : '?') . 'cat=' . urlencode($category) : '');

// ─── Consulta posts ────────────────────────────────────────────────────────
$where  = "status = 'published'";
$params = [];
$types  = '';
if ($category) {
    $where .= " AND category = ?";
    $params[] = $category;
    $types   .= 's';
}

// Total para paginación
$count_sql = "SELECT COUNT(*) FROM blog_posts WHERE $where";
$stmt_c = $conn->prepare($count_sql);
if ($types) $stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$stmt_c->bind_result($total);
$stmt_c->fetch();
$stmt_c->close();
$total_pages = (int)ceil($total / $per_page);

// Posts de la página actual
$sql = "SELECT id, slug, title, excerpt, cover_gradient, cover_image, author_name,
               category, tags, reading_time, published_at, featured
        FROM blog_posts
        WHERE $where
        ORDER BY featured DESC, published_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$p_page = $per_page;
$p_off  = $offset;
if ($types) {
    $stmt->bind_param($types . 'ii', ...[...$params, $p_page, $p_off]);
} else {
    $stmt->bind_param('ii', $p_page, $p_off);
}
$stmt->execute();
$result = $stmt->get_result();
$posts  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Categorías disponibles
$cats_res = $conn->query("SELECT DISTINCT category, COUNT(*) as n FROM blog_posts WHERE status='published' GROUP BY category ORDER BY n DESC");
$categories = $cats_res->fetch_all(MYSQLI_ASSOC);

// Featured post (primero de la lista si aplica)
$featured_post = null;
if ($page === 1 && !$category && !empty($posts) && $posts[0]['featured']) {
    $featured_post = array_shift($posts);
}

// ─── JSON-LD ───────────────────────────────────────────────────────────────
$jsonld_website = [
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    'name'            => 'Valírica',
    'url'             => $base_url,
    'description'     => 'Plataforma de cultura organizacional, equipos y talento.',
    'publisher'       => [
        '@type'  => 'Organization',
        'name'   => 'Valírica',
        'url'    => $base_url,
        'logo'   => [
            '@type' => 'ImageObject',
            'url'   => 'https://app.valirica.com/uploads/logo-192.png',
        ],
        'sameAs' => ['https://www.linkedin.com/company/valirica'],
    ],
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => $base_url . '/blog?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
$jsonld_blog = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Blog',
    'name'        => 'Blog de Cultura Organizacional — Valírica',
    'description' => 'Artículos, guías y recursos sobre cultura organizacional, liderazgo de equipos, gestión del talento y people analytics.',
    'url'         => $base_url . '/blog',
    'inLanguage'  => 'es',
    'publisher'   => ['@type' => 'Organization', 'name' => 'Valírica', 'url' => $base_url],
];
$jsonld_breadcrumb = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $base_url],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog',   'item' => $base_url . '/blog'],
    ],
];
if ($category) {
    $jsonld_breadcrumb['itemListElement'][] = [
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => htmlspecialchars($category),
        'item'     => $base_url . '/blog?cat=' . urlencode($category),
    ];
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function format_date_es($dt){
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($dt);
    return $ts ? date('j', $ts) . ' de ' . $months[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts) : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <!-- ── SEO primario ── -->
  <title><?= $category ? h($category) . ' — Blog Valírica' : 'Blog de Cultura Organizacional y Equipos | Valírica' ?></title>
  <meta name="description" content="<?= $category ? 'Artículos sobre ' . h($category) . ' en Valírica.' : 'Descubre guías, estrategias y recursos sobre cultura organizacional, liderazgo de equipos, DISC, Hofstede y gestión del talento.' ?>">
  <meta name="keywords" content="cultura organizacional, liderazgo equipos, gestión talento, DISC, Hofstede, people analytics, employee engagement, recursos humanos">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <meta name="author" content="Equipo Valírica">
  <link rel="canonical" href="<?= h($canonical) ?>">

  <!-- ── Open Graph ── -->
  <meta property="og:type"        content="website">
  <meta property="og:site_name"   content="Valírica">
  <meta property="og:title"       content="Blog de Cultura Organizacional | Valírica">
  <meta property="og:description" content="Guías, estrategias y recursos sobre cultura organizacional, liderazgo de equipos y gestión del talento.">
  <meta property="og:url"         content="<?= h($canonical) ?>">
  <meta property="og:image"       content="https://app.valirica.com/uploads/logo-192.png">
  <meta property="og:locale"      content="es_ES">

  <!-- ── Twitter Card ── -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="Blog de Cultura Organizacional | Valírica">
  <meta name="twitter:description" content="Guías, estrategias y recursos sobre cultura organizacional, liderazgo de equipos y gestión del talento.">
  <meta name="twitter:image"       content="https://app.valirica.com/uploads/logo-192.png">

  <!-- ── Paginación SEO ── -->
  <?php if ($page > 1): ?><link rel="prev" href="<?= h($base_url . '/blog?p=' . ($page - 1) . ($category ? '&cat=' . urlencode($category) : '')) ?>"><?php endif; ?>
  <?php if ($page < $total_pages): ?><link rel="next" href="<?= h($base_url . '/blog?p=' . ($page + 1) . ($category ? '&cat=' . urlencode($category) : '')) ?>"><?php endif; ?>

  <!-- ── Recursos ── -->
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/png" sizes="192x192" href="https://app.valirica.com/uploads/logo-192.png">
  <link rel="apple-touch-icon" href="https://app.valirica.com/uploads/logo-192.png">
  <meta name="theme-color" content="#012133">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
  <link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css">

  <!-- ── JSON-LD Structured Data ── -->
  <script type="application/ld+json"><?= json_encode($jsonld_website,    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_blog,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --c-primary:   #012133;
      --c-secondary: #184656;
      --c-teal:      #007a96;
      --c-accent:    #EF7F1B;
      --c-soft:      #FFF5F0;
      --font: "gelica", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      --radius-card: 20px;
      --transition: 0.22s ease;
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font);
      background: linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 70%, #0d3a4f 100%);
      min-height: 100vh;
      color: #fff;
      -webkit-font-smoothing: antialiased;
    }

    /* ── NAV ── */
    .vl-nav {
      position: sticky; top: 0; z-index: 100;
      background: rgba(1,25,41,0.85);
      backdrop-filter: blur(16px) saturate(1.4);
      -webkit-backdrop-filter: blur(16px) saturate(1.4);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      padding: 0 24px;
    }
    .vl-nav-inner {
      max-width: 1160px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
      height: 60px;
    }
    .vl-nav-logo {
      display: flex; align-items: center; gap: 10px;
      text-decoration: none; color: #fff;
    }
    .vl-nav-logo img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
    .vl-nav-logo span { font-size: 16px; font-weight: 800; letter-spacing: -0.3px; }
    .vl-nav-links { display: flex; gap: 6px; align-items: center; }
    .vl-nav-link {
      padding: 7px 14px; border-radius: 10px; font-size: 13px; font-weight: 600;
      text-decoration: none; color: rgba(255,255,255,0.62);
      transition: color var(--transition), background var(--transition);
    }
    .vl-nav-link:hover, .vl-nav-link.active {
      color: #fff; background: rgba(255,255,255,0.09);
    }
    .vl-nav-cta {
      padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 700;
      background: linear-gradient(135deg, var(--c-accent), #d96b0a);
      color: #fff; text-decoration: none; margin-left: 8px;
      box-shadow: 0 4px 14px rgba(239,127,27,0.30);
      transition: opacity var(--transition), transform var(--transition);
    }
    .vl-nav-cta:hover { opacity: 0.88; transform: scale(0.98); }

    /* ── HERO ── */
    .blog-hero {
      position: relative; overflow: hidden;
      padding: 80px 24px 72px;
      text-align: center;
    }
    .blog-hero::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse at 70% 0%,  rgba(0,122,150,0.35) 0%, transparent 55%),
        radial-gradient(ellipse at 15% 90%, rgba(239,127,27,0.18) 0%, transparent 48%);
      pointer-events: none;
    }
    /* Decorative rings */
    .blog-hero .vl-ring {
      position: absolute; border-radius: 50%; border: 1px solid rgba(0,122,150,0.13); pointer-events: none;
    }
    .blog-hero .r1 { width: 500px; height: 500px; top: -220px; right: -150px; }
    .blog-hero .r2 { width: 360px; height: 360px; bottom: -130px; left: -100px; border-color: rgba(239,127,27,0.09); }

    .blog-hero-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
    .blog-hero-tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(239,127,27,0.12); border: 1px solid rgba(239,127,27,0.25);
      color: #f5a23d; font-size: 11px; font-weight: 700; letter-spacing: 2px;
      text-transform: uppercase; padding: 6px 14px; border-radius: 100px;
      margin-bottom: 22px;
    }
    .blog-hero-tag i { font-size: 14px; }
    .blog-hero h1 {
      font-size: clamp(32px, 5vw, 52px); font-weight: 900;
      color: #fff; line-height: 1.1; letter-spacing: -1px;
      margin-bottom: 16px;
    }
    .blog-hero h1 span { color: var(--c-accent); }
    .blog-hero p {
      font-size: clamp(15px, 2vw, 17px); color: rgba(255,255,255,0.55);
      line-height: 1.7; max-width: 520px; margin: 0 auto 36px;
    }

    /* ── BREADCRUMB ── */
    .vl-breadcrumb {
      display: flex; align-items: center; gap: 6px;
      justify-content: center;
      font-size: 12px; color: rgba(255,255,255,0.38);
      margin-bottom: 0;
    }
    .vl-breadcrumb a { color: rgba(255,255,255,0.45); text-decoration: none; }
    .vl-breadcrumb a:hover { color: rgba(255,255,255,0.7); }
    .vl-breadcrumb i { font-size: 11px; }

    /* ── CATEGORY FILTERS ── */
    .vl-filters {
      padding: 0 24px 40px;
    }
    .vl-filters-inner {
      max-width: 1160px; margin: 0 auto;
      display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;
    }
    .vl-filter-btn {
      padding: 8px 18px; border-radius: 100px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.58); font-size: 13px; font-weight: 600;
      font-family: var(--font); cursor: pointer;
      text-decoration: none;
      transition: all var(--transition);
    }
    .vl-filter-btn:hover { background: rgba(255,255,255,0.10); color: #fff; border-color: rgba(255,255,255,0.25); }
    .vl-filter-btn.active {
      background: rgba(239,127,27,0.15); border-color: rgba(239,127,27,0.40);
      color: #f5a23d;
    }
    .vl-filter-count {
      display: inline-flex; align-items: center; justify-content: center;
      background: rgba(255,255,255,0.12); border-radius: 100px;
      width: 20px; height: 20px; font-size: 11px; margin-left: 4px;
    }

    /* ── MAIN GRID ── */
    .blog-main { padding: 0 24px 80px; }
    .blog-main-inner { max-width: 1160px; margin: 0 auto; }

    /* ── FEATURED CARD ── */
    .blog-featured {
      display: grid; grid-template-columns: 1fr 1fr; gap: 32px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 26px; overflow: hidden;
      margin-bottom: 48px;
      transition: transform var(--transition), box-shadow var(--transition);
      text-decoration: none; color: inherit;
      position: relative;
    }
    .blog-featured:hover {
      transform: translateY(-4px);
      box-shadow: 0 24px 64px rgba(0,122,150,0.18);
      border-color: rgba(0,122,150,0.30);
    }
    .blog-featured-cover {
      min-height: 340px;
      display: flex; align-items: flex-end;
      padding: 28px;
      position: relative;
    }
    .blog-featured-cover::after {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(1,33,51,0.7) 0%, transparent 60%);
    }
    .blog-featured-badge {
      position: relative; z-index: 1;
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(239,127,27,0.20); border: 1px solid rgba(239,127,27,0.35);
      color: #f5a23d; font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; padding: 5px 12px; border-radius: 100px;
    }
    .blog-featured-badge i { font-size: 13px; }
    .blog-featured-body { padding: 36px 40px 36px 0; display: flex; flex-direction: column; justify-content: center; }
    .blog-cat-pill {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      color: #4dd6f0; margin-bottom: 14px;
    }
    .blog-cat-pill i { font-size: 13px; }
    .blog-featured-title {
      font-size: clamp(22px, 2.5vw, 30px); font-weight: 900;
      color: #fff; line-height: 1.2; letter-spacing: -0.5px; margin-bottom: 14px;
    }
    .blog-featured-excerpt {
      font-size: 14px; color: rgba(255,255,255,0.55); line-height: 1.7;
      margin-bottom: 24px; flex: 1;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-meta {
      display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
      font-size: 12px; color: rgba(255,255,255,0.40);
    }
    .blog-meta span { display: flex; align-items: center; gap: 4px; }
    .blog-meta i { font-size: 14px; }
    .blog-read-more {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 11px 22px; border-radius: 12px; font-size: 13px; font-weight: 700;
      background: linear-gradient(135deg, var(--c-teal), #005f74);
      color: #fff; text-decoration: none; align-self: flex-start;
      box-shadow: 0 4px 14px rgba(0,122,150,0.30);
      transition: opacity var(--transition), transform var(--transition);
    }
    .blog-read-more:hover { opacity: 0.88; transform: scale(0.98); }
    .blog-read-more i { font-size: 16px; }

    /* ── POST GRID ── */
    .blog-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 56px;
    }
    .blog-card {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: var(--radius-card);
      overflow: hidden;
      text-decoration: none; color: inherit;
      transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
      display: flex; flex-direction: column;
    }
    .blog-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 50px rgba(0,0,0,0.25);
      border-color: rgba(255,255,255,0.18);
    }
    .blog-card-cover {
      height: 180px; position: relative;
      display: flex; align-items: flex-end; padding: 16px;
    }
    .blog-card-cover::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(1,33,51,0.55) 0%, transparent 65%);
    }
    .blog-card-cat {
      position: relative; z-index: 1;
      font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      color: rgba(255,255,255,0.75);
      background: rgba(0,0,0,0.30); backdrop-filter: blur(8px);
      padding: 4px 10px; border-radius: 100px;
    }
    .blog-card-body { padding: 20px 22px 22px; flex: 1; display: flex; flex-direction: column; }
    .blog-card-title {
      font-size: 17px; font-weight: 800; color: #fff;
      line-height: 1.3; letter-spacing: -0.3px; margin-bottom: 10px;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-card-excerpt {
      font-size: 13px; color: rgba(255,255,255,0.48); line-height: 1.6;
      flex: 1; margin-bottom: 18px;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-card-footer {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 12px; color: rgba(255,255,255,0.38);
      border-top: 1px solid rgba(255,255,255,0.07); padding-top: 14px;
    }
    .blog-card-footer-left { display: flex; align-items: center; gap: 10px; }
    .blog-card-footer span { display: flex; align-items: center; gap: 4px; }
    .blog-card-footer i { font-size: 13px; }
    .blog-card-arrow {
      width: 30px; height: 30px; border-radius: 50%;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,0.55); font-size: 15px;
      transition: background var(--transition), color var(--transition);
    }
    .blog-card:hover .blog-card-arrow { background: var(--c-accent); color: #fff; border-color: var(--c-accent); }

    /* ── EMPTY STATE ── */
    .blog-empty {
      text-align: center; padding: 80px 24px;
      color: rgba(255,255,255,0.45);
    }
    .blog-empty i { font-size: 56px; color: rgba(255,255,255,0.15); margin-bottom: 16px; display: block; }
    .blog-empty h3 { font-size: 20px; color: rgba(255,255,255,0.6); margin-bottom: 8px; }

    /* ── PAGINATION ── */
    .vl-pagination {
      display: flex; justify-content: center; align-items: center; gap: 8px;
      margin-top: 8px;
    }
    .vl-pag-btn {
      min-width: 42px; height: 42px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.55); font-size: 13px; font-weight: 700; font-family: var(--font);
      text-decoration: none; cursor: pointer;
      transition: all var(--transition);
    }
    .vl-pag-btn:hover { background: rgba(255,255,255,0.10); color: #fff; border-color: rgba(255,255,255,0.25); }
    .vl-pag-btn.active { background: var(--c-accent); border-color: var(--c-accent); color: #fff; box-shadow: 0 4px 14px rgba(239,127,27,0.30); }
    .vl-pag-btn:disabled, .vl-pag-btn.disabled { opacity: 0.3; pointer-events: none; }
    .vl-pag-btn i { font-size: 16px; }

    /* ── CTA BANNER ── */
    .blog-cta {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 24px; padding: 48px 40px;
      text-align: center; margin-top: 56px;
      position: relative; overflow: hidden;
    }
    .blog-cta::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 50% 0%, rgba(239,127,27,0.12) 0%, transparent 65%);
      pointer-events: none;
    }
    .blog-cta-inner { position: relative; z-index: 1; max-width: 540px; margin: 0 auto; }
    .blog-cta h2 { font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 12px; letter-spacing: -0.4px; }
    .blog-cta h2 span { color: var(--c-accent); }
    .blog-cta p { font-size: 15px; color: rgba(255,255,255,0.52); line-height: 1.7; margin-bottom: 28px; }
    .blog-cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn-primary {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 13px 26px; border-radius: 12px; font-size: 14px; font-weight: 700;
      background: linear-gradient(135deg, var(--c-accent), #d96b0a); color: #fff;
      text-decoration: none; box-shadow: 0 4px 18px rgba(239,127,27,0.35);
      transition: opacity var(--transition), transform var(--transition);
    }
    .btn-primary:hover { opacity: 0.88; transform: scale(0.98); }
    .btn-secondary {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 13px 26px; border-radius: 12px; font-size: 14px; font-weight: 700;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); color: #fff;
      text-decoration: none;
      transition: background var(--transition), border-color var(--transition);
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.11); border-color: rgba(255,255,255,0.25); }

    /* ── FOOTER ── */
    .blog-footer {
      border-top: 1px solid rgba(255,255,255,0.07);
      padding: 28px 24px; text-align: center;
      font-size: 13px; color: rgba(255,255,255,0.30);
    }
    .blog-footer a { color: rgba(255,255,255,0.42); text-decoration: none; }
    .blog-footer a:hover { color: rgba(255,255,255,0.65); }

    /* ── RESPONSIVE ── */
    @media (max-width: 1024px) {
      .blog-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .blog-featured { grid-template-columns: 1fr; }
      .blog-featured-cover { min-height: 220px; }
      .blog-featured-body { padding: 24px; }
      .blog-grid { grid-template-columns: 1fr; }
      .blog-hero { padding: 56px 20px 48px; }
    }
    @media (max-width: 480px) {
      .vl-nav-links { display: none; }
      .blog-cta { padding: 36px 24px; }
    }
  </style>
</head>
<body>

<!-- ══ NAV ══════════════════════════════════════════════════════════════════ -->
<nav class="vl-nav" role="navigation" aria-label="Navegación principal">
  <div class="vl-nav-inner">
    <a href="/" class="vl-nav-logo" aria-label="Valírica inicio">
      <img src="https://app.valirica.com/uploads/logo-192.png" alt="Logo Valírica" width="34" height="34">
      <span>Valírica</span>
    </a>
    <div class="vl-nav-links">
      <a href="/" class="vl-nav-link">Inicio</a>
      <a href="/blog" class="vl-nav-link active" aria-current="page">Blog</a>
      <a href="/login.php" class="vl-nav-cta"><i class="ph ph-arrow-right"></i> Acceder</a>
    </div>
  </div>
</nav>

<!-- ══ HERO ══════════════════════════════════════════════════════════════════ -->
<header class="blog-hero" role="banner">
  <span class="vl-ring r1"></span>
  <span class="vl-ring r2"></span>
  <div class="blog-hero-inner">
    <nav aria-label="Breadcrumb" class="vl-breadcrumb" style="margin-bottom:24px">
      <a href="/">Inicio</a>
      <i class="ph ph-caret-right"></i>
      <?php if ($category): ?>
        <a href="/blog">Blog</a>
        <i class="ph ph-caret-right"></i>
        <span><?= h($category) ?></span>
      <?php else: ?>
        <span aria-current="page">Blog</span>
      <?php endif; ?>
    </nav>
    <div class="blog-hero-tag">
      <i class="ph ph-pencil-line"></i>
      <?= $category ? h($category) : 'Cultura, Equipos & Talento' ?>
    </div>
    <h1>
      <?php if ($category): ?>
        <?= h($category) ?>
      <?php else: ?>
        Ideas que <span>transforman</span><br>equipos y culturas
      <?php endif; ?>
    </h1>
    <p>
      <?= $category
        ? 'Artículos, guías y estrategias sobre ' . h($category) . ' para líderes y equipos de recursos humanos.'
        : 'Artículos, guías y estrategias basadas en datos para líderes que quieren construir culturas organizacionales de alto rendimiento.'
      ?>
    </p>
  </div>
</header>

<!-- ══ CATEGORY FILTERS ══════════════════════════════════════════════════════ -->
<?php if (!empty($categories)): ?>
<section class="vl-filters" aria-label="Filtrar por categoría">
  <div class="vl-filters-inner">
    <a href="/blog" class="vl-filter-btn <?= !$category ? 'active' : '' ?>">
      <i class="ph ph-squares-four"></i> Todos
      <span class="vl-filter-count"><?= $total ?></span>
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="/blog?cat=<?= urlencode($cat['category']) ?>"
         class="vl-filter-btn <?= $category === $cat['category'] ? 'active' : '' ?>">
        <?= h($cat['category']) ?>
        <span class="vl-filter-count"><?= (int)$cat['n'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ══ POSTS ════════════════════════════════════════════════════════════════ -->
<main class="blog-main" id="main-content">
  <div class="blog-main-inner">

    <?php if (empty($posts) && !$featured_post): ?>
      <div class="blog-empty" role="status">
        <i class="ph ph-article"></i>
        <h3>No hay artículos todavía</h3>
        <p>Pronto publicaremos contenido sobre cultura organizacional y gestión de equipos.</p>
      </div>

    <?php else: ?>

      <!-- ── Featured post ── -->
      <?php if ($featured_post): ?>
      <article>
        <a href="/blog/<?= h($featured_post['slug']) ?>" class="blog-featured" aria-label="Artículo destacado: <?= h($featured_post['title']) ?>">
          <div class="blog-featured-cover" style="background: <?= h($featured_post['cover_gradient']) ?>">
            <?php if ($featured_post['cover_image']): ?>
              <img src="<?= h($featured_post['cover_image']) ?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0.4;" loading="eager">
            <?php endif; ?>
            <span class="blog-featured-badge"><i class="ph-fill ph-star"></i> Artículo destacado</span>
          </div>
          <div class="blog-featured-body">
            <div class="blog-cat-pill"><i class="ph ph-tag"></i> <?= h($featured_post['category']) ?></div>
            <h2 class="blog-featured-title"><?= h($featured_post['title']) ?></h2>
            <p class="blog-featured-excerpt"><?= h($featured_post['excerpt']) ?></p>
            <div class="blog-meta" style="margin-bottom:24px">
              <span><i class="ph ph-user"></i> <?= h($featured_post['author_name']) ?></span>
              <span><i class="ph ph-calendar"></i> <?= format_date_es($featured_post['published_at']) ?></span>
              <span><i class="ph ph-clock"></i> <?= (int)$featured_post['reading_time'] ?> min de lectura</span>
            </div>
            <span class="blog-read-more">Leer artículo <i class="ph ph-arrow-right"></i></span>
          </div>
        </a>
      </article>
      <?php endif; ?>

      <!-- ── Post grid ── -->
      <?php if (!empty($posts)): ?>
      <section aria-label="Artículos del blog">
        <div class="blog-grid">
          <?php foreach ($posts as $post): ?>
          <article>
            <a href="/blog/<?= h($post['slug']) ?>" class="blog-card" aria-label="<?= h($post['title']) ?>">
              <div class="blog-card-cover" style="background: <?= h($post['cover_gradient']) ?>">
                <?php if ($post['cover_image']): ?>
                  <img src="<?= h($post['cover_image']) ?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0.35;" loading="lazy">
                <?php endif; ?>
                <span class="blog-card-cat"><?= h($post['category']) ?></span>
              </div>
              <div class="blog-card-body">
                <h2 class="blog-card-title"><?= h($post['title']) ?></h2>
                <p class="blog-card-excerpt"><?= h($post['excerpt']) ?></p>
                <div class="blog-card-footer">
                  <div class="blog-card-footer-left">
                    <span><i class="ph ph-calendar"></i> <?= format_date_es($post['published_at']) ?></span>
                    <span><i class="ph ph-clock"></i> <?= (int)$post['reading_time'] ?> min</span>
                  </div>
                  <div class="blog-card-arrow" aria-hidden="true"><i class="ph ph-arrow-right"></i></div>
                </div>
              </div>
            </a>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- ── Pagination ── -->
      <?php if ($total_pages > 1): ?>
      <nav class="vl-pagination" aria-label="Paginación del blog">
        <?php
        $prev_url = $page > 1 ? '/blog?p=' . ($page - 1) . ($category ? '&cat=' . urlencode($category) : '') : null;
        $next_url = $page < $total_pages ? '/blog?p=' . ($page + 1) . ($category ? '&cat=' . urlencode($category) : '') : null;
        ?>
        <a href="<?= $prev_url ?? '#' ?>" class="vl-pag-btn <?= !$prev_url ? 'disabled' : '' ?>" aria-label="Página anterior" <?= !$prev_url ? 'aria-disabled="true"' : '' ?>>
          <i class="ph ph-caret-left"></i>
        </a>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="vl-pag-btn active" aria-current="page"><?= $i ?></span>
          <?php elseif ($i <= 3 || $i >= $total_pages - 1 || abs($i - $page) <= 1): ?>
            <a href="/blog?p=<?= $i ?><?= $category ? '&cat=' . urlencode($category) : '' ?>" class="vl-pag-btn"><?= $i ?></a>
          <?php elseif (abs($i - $page) === 2): ?>
            <span class="vl-pag-btn" style="pointer-events:none">…</span>
          <?php endif; ?>
        <?php endfor; ?>
        <a href="<?= $next_url ?? '#' ?>" class="vl-pag-btn <?= !$next_url ? 'disabled' : '' ?>" aria-label="Página siguiente" <?= !$next_url ? 'aria-disabled="true"' : '' ?>>
          <i class="ph ph-caret-right"></i>
        </a>
      </nav>
      <?php endif; ?>

    <?php endif; ?>

    <!-- ── CTA Banner ── -->
    <aside class="blog-cta" aria-label="Llamada a la acción">
      <div class="blog-cta-inner">
        <h2>¿Listo para <span>medir tu cultura</span> con datos reales?</h2>
        <p>Valírica te ayuda a entender y transformar la cultura de tu equipo con modelos validados como DISC y Hofstede, dashboards en tiempo real y análisis de alineación de valores.</p>
        <div class="blog-cta-btns">
          <a href="/registro.php" class="btn-primary"><i class="ph ph-rocket-launch"></i> Empezar gratis</a>
          <a href="/login.php"    class="btn-secondary"><i class="ph ph-sign-in"></i> Ya tengo cuenta</a>
        </div>
      </div>
    </aside>

  </div>
</main>

<!-- ══ FOOTER ════════════════════════════════════════════════════════════════ -->
<footer class="blog-footer" role="contentinfo">
  <p>© <?= date('Y') ?> Valírica · <a href="mailto:soporte@valirica.com">soporte@valirica.com</a> · <a href="/legal/privacidad">Privacidad</a> · <a href="/legal/terminos">Términos</a></p>
</footer>

</body>
</html>
