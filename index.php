<?php
// Enrutador del sitio público. Mantiene las URLs del WordPress anterior.
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/i18n.php';
require __DIR__ . '/app/views.php';

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = trim($path, '/');
if ($path === 'index.php') {
    $path = '';
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 301);
    exit;
}

function find_trick(string $lang, string $slug)
{
    $list = productos_visibles($lang);
    foreach ($list as $p) {
        if ($p[$lang]['slug'] === $slug) {
            return [$p, $list];
        }
    }
    return null;
}

if ($path === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (['es', 'en'] as $l) {
        foreach (ROUTES[$l] as $k => $u) {
            if ($k !== 'trick') {
                echo '<url><loc>' . e(SITE_URL . $u) . "</loc></url>\n";
            }
        }
        foreach (productos_visibles($l) as $p) {
            echo '<url><loc>' . e(SITE_URL . sprintf(ROUTES[$l]['trick'], rawurlencode($p[$l]['slug']))) . "</loc></url>\n";
        }
    }
    echo '</urlset>';
    exit;
}

$lang = 'es';
if ($path === 'en' || strpos($path, 'en/') === 0) {
    $lang = 'en';
    $path = ltrim(substr($path, 2), '/');
}

// URLs viejas
if ($path === 'sample-page' || ($lang === 'en' && $path === 'welcome')) {
    redirect(ROUTES[$lang]['home']);
}

$pages_by_slug = [];
foreach (ROUTES[$lang] as $k => $u) {
    if ($k !== 'trick') {
        $pages_by_slug[trim(substr($u, $lang === 'en' ? 3 : 0), '/')] = $k;
    }
}

header('Content-Type: text/html; charset=utf-8');

if (isset($pages_by_slug[$path])) {
    switch ($pages_by_slug[$path]) {
        case 'home': echo page_home($lang); break;
        case 'bio': echo page_bio($lang); break;
        case 'gallery': echo page_gallery($lang); break;
        case 'courses': echo page_courses($lang); break;
        case 'catalog': echo page_catalog($lang); break;
        case 'contact': echo page_contact($lang); break;
    }
    exit;
}

if (preg_match('~^portfolio/([^/]+)$~', $path, $m)) {
    $found = find_trick($lang, $m[1]);
    if ($found) {
        echo page_trick($lang, $found[0], $found[1]);
        exit;
    }
    // un truco en inglés pedido con la URL vieja sin /en/
    if ($lang === 'es' && find_trick('en', $m[1])) {
        redirect(sprintf(ROUTES['en']['trick'], rawurlencode($m[1])));
    }
}

echo page_404($lang);
