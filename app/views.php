<?php
// Plantillas de las páginas públicas.

const ICON_WA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2a9.9 9.9 0 0 0-8.52 14.95L2 22l5.2-1.49A9.9 9.9 0 1 0 12.04 2Zm0 18.1a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.09.88.83-3.01-.2-.31a8.2 8.2 0 1 1 6.94 3.77Zm4.5-6.14c-.25-.12-1.46-.72-1.69-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.97-.14.16-.29.18-.53.06a6.7 6.7 0 0 1-3.32-2.9c-.25-.43.25-.4.71-1.33.08-.16.04-.31-.02-.43l-.76-1.83c-.2-.48-.4-.41-.56-.42h-.47a.9.9 0 0 0-.66.31 2.77 2.77 0 0 0-.86 2.06 4.8 4.8 0 0 0 1 2.55 11 11 0 0 0 4.2 3.71c1.57.68 2.18.74 2.96.62.48-.07 1.46-.6 1.67-1.18.2-.58.2-1.08.14-1.18-.06-.1-.22-.16-.47-.28Z"/></svg>';
const ICON_PLAY = '<svg viewBox="0 0 68 48" aria-hidden="true"><path d="M66.5 7.7A8.5 8.5 0 0 0 60.5 1.6C55.2 0 34 0 34 0S12.8 0 7.5 1.6a8.5 8.5 0 0 0-6 6.1C0 13 0 24 0 24s0 11 1.5 16.3a8.5 8.5 0 0 0 6 6.1C12.8 48 34 48 34 48s21.2 0 26.5-1.6a8.5 8.5 0 0 0 6-6.1C68 35 68 24 68 24s0-11-1.5-16.3Z" fill="#c9302c"/><path d="m27 34 18-10-18-10z" fill="#fff"/></svg>';
const ICON_MAIL = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" d="M3 6h18v12H3zM3 7l9 6 9-6"/></svg>';
const ICON_SEARCH ='<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>';

function t(string $lang, string $key)
{
    return T[$lang][$key];
}

function btn_wa(string $text, string $label, string $extra = ''): string
{
    return '<a class="btn btn-wa ' . $extra . '" href="' . e(wa_link($text)) . '" target="_blank" rel="noopener">' . ICON_WA . ' ' . e($label) . '</a>';
}

function yt(string $id, string $lang, string $title = ''): string
{
    $label = t($lang, 'play') . ($title !== '' ? ': ' . $title : '');
    return '<div class="yt" data-id="' . e($id) . '"><button type="button" class="yt-btn" aria-label="' . e($label) . '">'
        . '<img src="https://i.ytimg.com/vi/' . e($id) . '/hqdefault.jpg" alt="" loading="lazy" width="480" height="360">'
        . '<span class="yt-play">' . ICON_PLAY . '</span></button></div>';
}

function paras(array $list): string
{
    return implode('', array_map(function ($p) {
        return '<p>' . e($p) . '</p>';
    }, $list));
}

function first_price(string $precio): string
{
    $line = trim(explode("\n", $precio)[0]);
    return preg_match('/(u\$s|us\$|usd)\s*[\d.,]+/i', $line, $m) ? $m[0] : $line;
}

function card(array $p, string $lang): string
{
    $d = $p[$lang];
    $href = sprintf(ROUTES[$lang]['trick'], rawurlencode($d['slug']));
    $pic = !empty($p['imagen']) ? '<img src="' . e($p['imagen']) . '" alt="" loading="lazy">' : '<span class="noimg"></span>';
    $search = mb_strtolower($d['titulo'] . ' ' . mb_substr(str_replace('**', '', $d['texto'] ?? ''), 0, 500));
    $price = first_price($p['precio'] ?? '');
    return '<li class="card" data-search="' . e($search) . '"><a href="' . e($href) . '">'
        . '<div class="card-img">' . $pic . '</div><div class="card-body"><h3>' . e($d['titulo']) . '</h3>'
        . ($price !== '' ? '<span class="card-price">' . e($price) . '</span>' : '') . '</div></a></li>';
}

function zoom_img(string $small, string $full): string
{
    return '<button type="button" class="zoom" data-full="' . e($full) . '"><img src="' . e($small) . '" alt="" loading="lazy"></button>';
}

function layout(string $lang, string $key, string $title, string $body, string $alt_url, array $opt = []): string
{
    $r = ROUTES[$lang];
    $other = $lang === 'es' ? 'en' : 'es';
    $full = $key === 'home' ? t($lang, 'suffix') : $title . ' · ' . t($lang, 'suffix');
    $desc = $opt['description'] ?? t($lang, 'hero_lead');
    $wa_hello = wa_link(t($lang, 'wa_hello'));
    $nav = '';
    foreach (NAV as $k) {
        $label = $k === 'catalog' ? t($lang, 'catalog_short') : t($lang, $k);
        $nav .= '<li><a href="' . $r[$k] . '"' . ($k === $key ? ' aria-current="page"' : '') . '>' . e($label) . '</a></li>';
    }
    ob_start();
    ?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($full) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if (!empty($opt['image'])): ?><meta property="og:image" content="<?= e(SITE_URL . $opt['image']) ?>">
<?php endif ?>
<link rel="alternate" hreflang="<?= $other ?>" href="<?= e(SITE_URL . $alt_url) ?>">
<link rel="icon" href="/img/favicon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=3">
</head>
<body class="<?= e($opt['class'] ?? '') ?>">
<a class="skip" href="#main"><?= e(t($lang, 'skip')) ?></a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= $r['home'] ?>"><img src="/img/logo.png" alt="Quique Marduk" width="300" height="74"></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span><span class="sr"><?= e(t($lang, 'menu')) ?></span></button>
    <nav id="nav" class="nav" aria-label="<?= e(t($lang, 'menu')) ?>">
      <ul><?= $nav ?></ul>
      <a class="lang" href="<?= e($alt_url) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= strtoupper($other) ?><span class="sr"> – <?= e(t($lang, 'other')) ?></span></a>
    </nav>
  </div>
</header>
<main id="main">
<?= $body ?>
</main>
<footer class="site-footer">
  <div class="wrap footer-inner">
    <div>
      <img src="/img/logo.png" alt="Quique Marduk" width="200" height="49" loading="lazy">
      <p><?= e(t($lang, 'footer')) ?></p>
    </div>
    <ul class="footer-nav"><?= $nav ?></ul>
    <div><?= btn_wa(t($lang, 'wa_hello'), WA_DISPLAY) ?><p class="footer-mail"><a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a></p></div>
  </div>
  <p class="wrap copy">© <?= date('Y') ?> Quique Marduk</p>
</footer>
<a class="wa-float" href="<?= e($wa_hello) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= ICON_WA ?></a>
<dialog class="lightbox" aria-label="<?= e(t($lang, 'gallery')) ?>"><button type="button" class="lb-close" aria-label="<?= e(t($lang, 'close')) ?>">×</button><img alt=""></dialog>
<script src="/assets/site.js?v=3" defer></script>
</body>
</html>
<?php
    return ob_get_clean();
}

// ------------------------------------------------------------------ páginas

function page_home(string $lang): string
{
    $r = ROUTES[$lang];
    $s = sitio();
    $img = $s['imagenes'];
    $todos = productos_visibles($lang);
    $dest = array_values(array_filter($todos, function ($p) {
        return !empty($p['destacado']);
    }));
    // los 4 más nuevos primero, después los destacados
    $feat = array_slice($todos, 0, 4);
    foreach ($dest as $p) {
        if (count($feat) >= 8) {
            break;
        }
        if (!in_array($p, $feat, true)) {
            $feat[] = $p;
        }
    }
    $videos = implode('', array_map(function ($id) use ($lang) {
        return yt($id, $lang);
    }, $s['videos_inicio'][$lang] ?? []));
    $strip = implode('', array_map(function ($g) {
        return '<li>' . zoom_img($g['chica'], $g['grande']) . '</li>';
    }, array_slice($s['galeria'], 0, 6)));
    $cards = implode('', array_map(function ($p) use ($lang) {
        return card($p, $lang);
    }, $feat));
    ob_start();
    ?>
<section class="hero">
  <div class="hero-bg" style="background-image:url('<?= e($img['hero']) ?>')"></div>
  <div class="wrap hero-inner">
    <p class="eyebrow"><?= e(t($lang, 'tagline')) ?></p>
    <h1><img src="/img/logo.png" alt="Quique Marduk" width="560" height="139"></h1>
    <p class="lead"><?= e(t($lang, 'hero_lead')) ?></p>
    <div class="actions">
      <?= btn_wa(t($lang, 'wa_show'), t($lang, 'hire')) ?>
      <a class="btn btn-ghost" href="<?= $r['catalog'] ?>"><?= e(t($lang, 'see_catalog')) ?></a>
    </div>
  </div>
</section>
<section class="wrap split">
  <div class="split-text">
    <p class="eyebrow"><?= e(t($lang, 'bio')) ?></p>
    <h2>Quique Marduk</h2>
    <?= paras(t($lang, 'home_about')) ?>
    <a class="link" href="<?= $r['bio'] ?>"><?= e(t($lang, 'more')) ?> →</a>
  </div>
  <img class="split-img" src="<?= e($img['cursos']) ?>" alt="Quique Marduk" loading="lazy">
</section>
<?php if ($videos): ?>
<section class="band">
  <div class="wrap">
    <h2><?= e(t($lang, 'videos')) ?></h2>
    <div class="video-grid"><?= $videos ?></div>
  </div>
</section>
<?php endif ?>
<section class="wrap">
  <div class="section-head"><h2><?= e(t($lang, 'featured')) ?></h2><a class="link" href="<?= $r['catalog'] ?>"><?= e(t($lang, 'all_tricks')) ?> →</a></div>
  <ul class="cards"><?= $cards ?></ul>
</section>
<section class="wrap">
  <div class="section-head"><h2><?= e(t($lang, 'gallery')) ?></h2><a class="link" href="<?= $r['gallery'] ?>"><?= e(t($lang, 'see_gallery')) ?> →</a></div>
  <ul class="gallery gallery-strip"><?= $strip ?></ul>
</section>
<section class="band cta">
  <div class="wrap cta-inner">
    <div>
      <p class="eyebrow"><?= e(t($lang, 'courses')) ?></p>
      <h2><?= e(t($lang, 'courses_lead')) ?></h2>
    </div>
    <div class="actions">
      <?= btn_wa(t($lang, 'wa_course'), t($lang, 'book')) ?>
      <a class="btn btn-ghost" href="<?= $r['courses'] ?>"><?= e(t($lang, 'more_info')) ?></a>
    </div>
  </div>
</section>
<?php
    $other = $lang === 'es' ? 'en' : 'es';
    return layout($lang, 'home', t($lang, 'home'), ob_get_clean(), ROUTES[$other]['home'], ['image' => $img['hero'], 'class' => 'is-home']);
}

function page_split(string $lang, string $key, string $h1, string $content, string $image, bool $portrait = false): string
{
    $other = $lang === 'es' ? 'en' : 'es';
    $body = '<section class="wrap split page-split"><div class="split-text">' . $content . '</div>'
        . '<img class="split-img' . ($portrait ? ' portrait' : '') . '" src="' . e($image) . '" alt="Quique Marduk"></section>';
    return layout($lang, $key, $h1, $body, ROUTES[$other][$key], ['image' => $image]);
}

function page_bio(string $lang): string
{
    $c = '<p class="eyebrow">' . e(t($lang, 'tagline')) . '</p><h1>' . e(t($lang, 'bio')) . '</h1>'
        . paras(t($lang, 'bio_text')) . btn_wa(t($lang, 'wa_show'), t($lang, 'hire'));
    return page_split($lang, 'bio', t($lang, 'bio'), $c, sitio()['imagenes']['retrato'], true);
}

function page_courses(string $lang): string
{
    $c = '<p class="eyebrow">' . e(t($lang, 'courses')) . '</p><h1>' . e(t($lang, 'courses_lead')) . '</h1>'
        . paras(t($lang, 'courses_text')) . '<p class="highlight">' . e(t($lang, 'courses_online')) . '</p>'
        . btn_wa(t($lang, 'wa_course'), t($lang, 'book'));
    return page_split($lang, 'courses', t($lang, 'courses'), $c, sitio()['imagenes']['cursos']);
}

function page_contact(string $lang): string
{
    $c = '<h1>' . e(t($lang, 'contact')) . '</h1><p class="lead">' . e(t($lang, 'contact_lead')) . '</p>'
        . '<div class="actions">' . btn_wa(t($lang, 'wa_hello'), WA_DISPLAY, 'big')
        . '<a class="btn btn-ghost big" href="mailto:' . EMAIL . '">' . ICON_MAIL . ' ' . EMAIL . '</a></div>'
        . '<form class="wa-form" data-number="' . WA_NUMBER . '" data-email="' . EMAIL . '" data-subject="' . e(t($lang, 'mail_subject')) . '">'
        . '<label>' . e(t($lang, 'name')) . '<input name="name" autocomplete="name"></label>'
        . '<label>' . e(t($lang, 'message')) . '<textarea name="msg" rows="5" required></textarea></label>'
        . '<div class="actions"><button class="btn btn-gold" type="submit" value="wa">' . e(t($lang, 'send')) . '</button>'
        . '<button class="btn btn-ghost" type="submit" value="mail">' . e(t($lang, 'send_mail')) . '</button></div></form>';
    return page_split($lang, 'contact', t($lang, 'contact'), $c, sitio()['imagenes']['contacto']);
}

function page_gallery(string $lang): string
{
    $other = $lang === 'es' ? 'en' : 'es';
    $items = implode('', array_map(function ($g) {
        return '<li>' . zoom_img($g['chica'], $g['grande']) . '</li>';
    }, sitio()['galeria']));
    $body = '<section class="page-head wrap"><h1>' . e(t($lang, 'gallery')) . '</h1><p class="lead">' . e(t($lang, 'gallery_lead')) . '</p></section>'
        . '<section class="wrap"><ul class="gallery">' . $items . '</ul></section>';
    return layout($lang, 'gallery', t($lang, 'gallery'), $body, ROUTES[$other]['gallery'], ['description' => t($lang, 'gallery_lead')]);
}

function page_catalog(string $lang): string
{
    $other = $lang === 'es' ? 'en' : 'es';
    $items = productos_visibles($lang);
    $cards = implode('', array_map(function ($p) use ($lang) {
        return card($p, $lang);
    }, $items));
    $body = '<section class="page-head wrap"><p class="eyebrow">' . e(sprintf(t($lang, 'tricks_count'), count($items))) . '</p>'
        . '<h1>' . e(t($lang, 'catalog')) . '</h1><p class="lead">' . e(t($lang, 'catalog_lead')) . '</p>'
        . '<label class="search">' . ICON_SEARCH . '<span class="sr">' . e(t($lang, 'search')) . '</span>'
        . '<input type="search" id="q" placeholder="' . e(t($lang, 'search')) . '" autocomplete="off"></label></section>'
        . '<section class="wrap"><ul class="cards" id="cards">' . $cards . '</ul>'
        . '<p class="empty" id="empty" hidden>' . e(t($lang, 'no_results')) . '</p></section>';
    return layout($lang, 'catalog', t($lang, 'catalog'), $body, ROUTES[$other]['catalog'], ['description' => t($lang, 'catalog_lead')]);
}

function page_trick(string $lang, array $p, array $list): string
{
    $r = ROUTES[$lang];
    $other = $lang === 'es' ? 'en' : 'es';
    $d = $p[$lang];
    $alt = !empty($p[$other]['slug']) && !empty($p['visible']) ? sprintf(ROUTES[$other]['trick'], rawurlencode($p[$other]['slug'])) : ROUTES[$other]['catalog'];
    $i = array_search($p['id'], array_column($list, 'id'), true);
    $prev = $i > 0 ? $list[$i - 1] : null;
    $next = $i !== false && $i + 1 < count($list) ? $list[$i + 1] : null;
    $prices = array_filter(array_map('trim', explode("\n", $p['precio'] ?? '')), 'strlen');
    $fotos = implode('', array_map(function ($f) {
        return zoom_img($f, $f);
    }, $p['fotos'] ?? []));
    $videos = implode('', array_map(function ($id) use ($lang, $d) {
        return yt($id, $lang, $d['titulo']);
    }, $p['videos'] ?? []));
    ob_start();
    ?>
<section class="trick wrap">
  <a class="back" href="<?= $r['catalog'] ?>">← <?= e(t($lang, 'back')) ?></a>
  <div class="trick-grid">
    <div class="trick-media">
      <?php if (!empty($p['imagen'])): ?>
      <button type="button" class="zoom main-img" data-full="<?= e($p['imagen']) ?>"><img src="<?= e($p['imagen']) ?>" alt="<?= e($d['titulo']) ?>"></button>
      <?php endif ?>
      <?php if ($fotos): ?><div class="thumbs"><?= $fotos ?></div><?php endif ?>
    </div>
    <div class="trick-info">
      <p class="eyebrow"><?= e(t($lang, 'catalog')) ?></p>
      <h1><?= e($d['titulo']) ?></h1>
      <div class="prose"><?= texto_html($d['texto'] ?? '') ?></div>
      <div class="buy">
        <?php if ($prices): ?><ul class="price"><?php foreach ($prices as $pr): ?><li><?= e($pr) ?></li><?php endforeach ?></ul><?php endif ?>
        <?php if (!empty($d['nota'])): ?><p class="note"><?= e($d['nota']) ?></p><?php endif ?>
        <?= btn_wa(sprintf(t($lang, 'wa_trick'), $d['titulo']), t($lang, 'ask')) ?>
        <p class="ship"><?= e(t($lang, 'shipping')) ?></p>
      </div>
    </div>
  </div>
  <?php if ($videos): ?>
  <div class="trick-videos"><h2><?= e(t($lang, 'video')) ?></h2><div class="video-grid"><?= $videos ?></div></div>
  <?php endif ?>
  <nav class="pager">
    <?php if ($prev): ?><a href="<?= e(sprintf($r['trick'], rawurlencode($prev[$lang]['slug']))) ?>">← <?= e($prev[$lang]['titulo']) ?></a><?php else: ?><span></span><?php endif ?>
    <?php if ($next): ?><a href="<?= e(sprintf($r['trick'], rawurlencode($next[$lang]['slug']))) ?>"><?= e($next[$lang]['titulo']) ?> →</a><?php endif ?>
  </nav>
</section>
<?php
    return layout($lang, 'catalog', $d['titulo'], ob_get_clean(), $alt, ['description' => plain($d['texto'] ?? ''), 'image' => $p['imagen'] ?? null]);
}

function page_404(string $lang): string
{
    http_response_code(404);
    $r = ROUTES[$lang];
    $other = $lang === 'es' ? 'en' : 'es';
    $body = '<section class="page-head wrap"><h1>' . e(t($lang, 'not_found')) . '</h1><p class="lead">' . e(t($lang, 'not_found_lead'))
        . '</p><p><a class="btn btn-ghost" href="' . $r['home'] . '">' . e(t($lang, 'back_home')) . '</a></p></section>';
    return layout($lang, '', t($lang, 'not_found'), $body, ROUTES[$other]['home']);
}
