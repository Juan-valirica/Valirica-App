<?php
/**
 * Valírica — Sitemap XML dinámico
 * Incluye posts publicados del blog + páginas estáticas
 * URL: https://valirica.com/sitemap.xml (via .htaccess rewrite)
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$base = 'https://valirica.com';
$today = date('Y-m-d');

// Posts publicados
$posts = $conn->query("
    SELECT slug, updated_at, published_at
    FROM blog_posts
    WHERE status = 'published'
    ORDER BY published_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Categorías con posts
$cats = $conn->query("
    SELECT DISTINCT category, MAX(updated_at) as updated_at
    FROM blog_posts
    WHERE status = 'published'
    GROUP BY category
")->fetch_all(MYSQLI_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
         xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
         xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// ─── Páginas estáticas ─────────────────────────────────────────────────────
$static = [
    ['loc' => '/',                         'priority' => '1.0', 'changefreq' => 'weekly'],
    ['loc' => '/blog',                     'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/registro.php',             'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => '/legal/privacidad',         'priority' => '0.3', 'changefreq' => 'yearly'],
    ['loc' => '/legal/terminos',           'priority' => '0.3', 'changefreq' => 'yearly'],
];

foreach ($static as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . $page['loc']) . "</loc>\n";
    echo "    <lastmod>$today</lastmod>\n";
    echo "    <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}

// ─── Categorías de blog ────────────────────────────────────────────────────
foreach ($cats as $cat) {
    $lastmod = date('Y-m-d', strtotime($cat['updated_at']));
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . '/blog?cat=' . urlencode($cat['category'])) . "</loc>\n";
    echo "    <lastmod>$lastmod</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

// ─── Posts del blog ────────────────────────────────────────────────────────
foreach ($posts as $post) {
    $lastmod = date('Y-m-d', strtotime($post['updated_at']));
    $pubdate = date('Y-m-d', strtotime($post['published_at']));
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . '/blog/' . $post['slug']) . "</loc>\n";
    echo "    <lastmod>$lastmod</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
