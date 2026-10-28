<?php
// Panel de administración: productos del catálogo, precios, fotos y videos.
require dirname(__DIR__) . '/app/core.php';

const MAX_UPLOAD = 10 * 1024 * 1024;
const MAX_SIDE = 1400;
const LOCK_AFTER = 5;          // intentos fallidos
const LOCK_MINUTES = 15;

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
session_name('mkadmin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sesión vencida. Volvé atrás y recargá la página.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = [$msg, $type];
}

function go(string $q = ''): void
{
    header('Location: /admin/' . ($q !== '' ? '?' . $q : ''), true, 303);
    exit;
}

function admin_conf(): array
{
    return read_json('admin.json');
}

// ------------------------------------------------------------- bloqueo por intentos fallidos (por IP)
function client_key(): string
{
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function login_state(): array
{
    return read_json('login.json')[client_key()] ?? ['fallos' => 0, 'hasta' => 0];
}

function login_set(?array $state): void
{
    $all = array_filter(read_json('login.json'), function ($s) {
        return ($s['hasta'] ?? 0) > time() || ($s['fallos'] ?? 0) > 0;
    });
    if ($state === null) {
        unset($all[client_key()]);
    } else {
        $all[client_key()] = $state;
    }
    file_put_contents(DATA_DIR . '/login.json', json_encode($all), LOCK_EX);
}

function login_failed(): void
{
    $s = login_state();
    $s['fallos'] = ($s['fallos'] ?? 0) + 1;
    if ($s['fallos'] >= LOCK_AFTER) {
        $s = ['fallos' => 0, 'hasta' => time() + LOCK_MINUTES * 60];
    }
    login_set($s);
}

// ------------------------------------------------------------- imágenes
function save_upload(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_UPLOAD || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('No se pudo subir la imagen "' . $f['name'] . '" (máximo 10 MB).');
    }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('"' . $f['name'] . '" no es una imagen JPG, PNG, WEBP o GIF.');
    }
    $ext = $types[$info[2]];
    $dir = BASE_DIR . '/img/productos/' . date('Y');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('md') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!resize_image($f['tmp_name'], $dest, $info) && !move_uploaded_file($f['tmp_name'], $dest)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }
    return '/img/productos/' . date('Y') . '/' . $name;
}

/** Achica fotos grandes si el servidor tiene GD. Devuelve false si no hizo nada. */
function resize_image(string $src, string $dest, array $info): bool
{
    [$w, $h, $type] = $info;
    if (max($w, $h) <= MAX_SIDE || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    $open = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!isset($open[$type]) || !function_exists($open[$type])) {
        return false;
    }
    $im = @$open[$type]($src);
    if (!$im) {
        return false;
    }
    $k = MAX_SIDE / max($w, $h);
    $nw = (int)round($w * $k);
    $nh = (int)round($h * $k);
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = $type === IMAGETYPE_PNG ? imagepng($out, $dest, 7) : ($type === IMAGETYPE_WEBP ? imagewebp($out, $dest, 85) : imagejpeg($out, $dest, 85));
    imagedestroy($im);
    imagedestroy($out);
    return $ok;
}

function files_array(array $f): array
{
    $out = [];
    if (!isset($f['name']) || !is_array($f['name'])) {
        return $out;
    }
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** Borra del disco una foto subida desde el panel (las migradas del sitio viejo no se tocan). */
function delete_upload(?string $url): void
{
    if ($url && preg_match('~^/img/productos/\d{4}/[\w-]+\.(jpg|png|webp|gif)$~', $url)) {
        @unlink(BASE_DIR . $url);
    }
}

// ------------------------------------------------------------- productos
function load_all(): array
{
    return productos();
}

function save_all(array $list): void
{
    write_json('productos.json', ['productos' => array_values($list)]);
}

function find_index(array $list, string $id)
{
    foreach ($list as $i => $p) {
        if ($p['id'] === $id) {
            return $i;
        }
    }
    return null;
}

function unique_slug(array $list, string $lang, string $slug, string $id): string
{
    $base = $slug;
    $n = 2;
    $taken = [];
    foreach ($list as $p) {
        if ($p['id'] !== $id && !empty($p[$lang]['slug'])) {
            $taken[$p[$lang]['slug']] = true;
        }
    }
    while (isset($taken[$slug])) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

function product_from_post(array $list, ?array $old): array
{
    $id = $old['id'] ?? ('p' . date('ymdHis') . bin2hex(random_bytes(2)));
    $p = $old ?? ['id' => $id, 'fecha' => date('Y-m-d'), 'fotos' => [], 'imagen' => null];
    $p['visible'] = !empty($_POST['visible']);
    $p['destacado'] = !empty($_POST['destacado']);
    $p['precio'] = trim(str_replace("\r", '', (string)($_POST['precio'] ?? '')));
    $p['videos'] = youtube_ids((string)($_POST['videos'] ?? ''));
    foreach (['es', 'en'] as $l) {
        $titulo = trim((string)($_POST[$l . '_titulo'] ?? ''));
        if ($titulo === '') {
            $p[$l] = null;
            continue;
        }
        $slug = slugify((string)($_POST[$l . '_slug'] ?? '') ?: $titulo);
        $p[$l] = [
            'titulo' => $titulo,
            'slug' => unique_slug($list, $l, $slug, $id),
            'texto' => trim(str_replace("\r", '', (string)($_POST[$l . '_texto'] ?? ''))),
            'nota' => trim((string)($_POST[$l . '_nota'] ?? '')),
        ];
    }
    if (!$p['es'] && !$p['en']) {
        throw new RuntimeException('Poné al menos el nombre en español o en inglés.');
    }
    // fotos: quitar las marcadas, subir las nuevas
    $quitar = array_map('strval', (array)($_POST['quitar'] ?? []));
    if ($p['imagen'] && in_array($p['imagen'], $quitar, true)) {
        delete_upload($p['imagen']);
        $p['imagen'] = null;
    }
    $p['fotos'] = array_values(array_filter($p['fotos'] ?? [], function ($f) use ($quitar) {
        if (in_array($f, $quitar, true)) {
            delete_upload($f);
            return false;
        }
        return true;
    }));
    if ($new = save_upload($_FILES['imagen'] ?? [])) {
        delete_upload($p['imagen']);
        $p['imagen'] = $new;
    }
    foreach (files_array($_FILES['fotos'] ?? []) as $f) {
        if ($u = save_upload($f)) {
            $p['fotos'][] = $u;
        }
    }
    if (!$p['imagen'] && $p['fotos']) {
        $p['imagen'] = array_shift($p['fotos']);
    }
    return $p;
}

// ------------------------------------------------------------- acciones
$conf = admin_conf();
$action = $_GET['a'] ?? '';
$logged = !empty($_SESSION['ok']) && ($_SESSION['ver'] ?? '') === ($conf['hash'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'setup' && empty($conf['hash'])) {
            $pw = (string)($_POST['pw'] ?? '');
            if (mb_strlen($pw) < 10 || $pw !== ($_POST['pw2'] ?? '')) {
                throw new RuntimeException('La contraseña debe tener al menos 10 caracteres y coincidir en los dos campos.');
            }
            $conf = ['hash' => password_hash($pw, PASSWORD_DEFAULT)];
            write_json('admin.json', $conf);
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['ver'] = $conf['hash'];
            flash('Contraseña creada. ¡Bienvenido!');
            go();
        }
        if ($do === 'login') {
            $st = login_state();
            if (($st['hasta'] ?? 0) > time()) {
                throw new RuntimeException('Demasiados intentos. Probá de nuevo en unos minutos.');
            }
            if (!empty($conf['hash']) && password_verify((string)($_POST['pw'] ?? ''), $conf['hash'])) {
                login_set(null);
                session_regenerate_id(true);
                $_SESSION['ok'] = true;
                $_SESSION['ver'] = $conf['hash'];
                go();
            }
            login_failed();
            usleep(500000);
            throw new RuntimeException('Contraseña incorrecta.');
        }
        if (!$logged) {
            go();
        }
        if ($do === 'logout') {
            $_SESSION = [];
            session_destroy();
            go();
        }
        $list = load_all();
        if ($do === 'save') {
            $id = (string)($_POST['id'] ?? '');
            $i = $id !== '' ? find_index($list, $id) : null;
            $p = product_from_post($list, $i !== null ? $list[$i] : null);
            if ($i !== null) {
                $list[$i] = $p;
            } else {
                array_unshift($list, $p);
            }
            save_all($list);
            flash('Producto guardado.');
            go(!empty($_POST['seguir']) ? 'a=edit&id=' . rawurlencode($p['id']) : '');
        }
        if ($do === 'price') {
            $i = find_index($list, (string)($_POST['id'] ?? ''));
            if ($i !== null) {
                $list[$i]['precio'] = trim(str_replace("\r", '', (string)($_POST['precio'] ?? '')));
                save_all($list);
                flash('Precio actualizado.');
            }
            go('#' . rawurlencode((string)$_POST['id']));
        }
        if ($do === 'toggle') {
            $i = find_index($list, (string)($_POST['id'] ?? ''));
            if ($i !== null) {
                $list[$i]['visible'] = empty($list[$i]['visible']);
                save_all($list);
                flash($list[$i]['visible'] ? 'Producto publicado.' : 'Producto oculto.');
            }
            go('#' . rawurlencode((string)$_POST['id']));
        }
        if ($do === 'delete') {
            $i = find_index($list, (string)($_POST['id'] ?? ''));
            if ($i !== null) {
                delete_upload($list[$i]['imagen'] ?? null);
                foreach ($list[$i]['fotos'] ?? [] as $f) {
                    delete_upload($f);
                }
                array_splice($list, $i, 1);
                save_all($list);
                flash('Producto eliminado.');
            }
            go();
        }
        if ($do === 'home_videos') {
            $s = sitio();
            $s['videos_inicio'] = ['es' => youtube_ids((string)($_POST['es'] ?? '')), 'en' => youtube_ids((string)($_POST['en'] ?? ''))];
            write_json('sitio.json', $s);
            flash('Videos de inicio guardados.');
            go('a=home');
        }
        if ($do === 'password') {
            if (!password_verify((string)($_POST['actual'] ?? ''), $conf['hash'])) {
                throw new RuntimeException('La contraseña actual no es correcta.');
            }
            $pw = (string)($_POST['pw'] ?? '');
            if (mb_strlen($pw) < 10 || $pw !== ($_POST['pw2'] ?? '')) {
                throw new RuntimeException('La nueva contraseña debe tener al menos 10 caracteres y coincidir en los dos campos.');
            }
            $conf = ['hash' => password_hash($pw, PASSWORD_DEFAULT)];
            write_json('admin.json', $conf);
            $_SESSION['ver'] = $conf['hash'];
            flash('Contraseña cambiada.');
            go('a=home');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
        $back = $do === 'save' ? 'a=edit' . (!empty($_POST['id']) ? '&id=' . rawurlencode((string)$_POST['id']) : '') : ($logged ? 'a=' . rawurlencode($action) : '');
        go($back);
    }
    go();
}

// ------------------------------------------------------------- vistas
function head(string $title): void
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · Panel Marduk</title>
<link rel="icon" href="/img/favicon.jpg">
<link rel="stylesheet" href="/admin/admin.css?v=1">
</head>
<body>
<?php if (!empty($_SESSION['ok'])): ?>
<header class="top">
  <a class="brand" href="/admin/"><img src="/img/logo.png" alt="Quique Marduk"> <span>Panel</span></a>
  <nav>
    <a href="/admin/">Productos</a>
    <a href="/admin/?a=edit" class="primary">+ Nuevo producto</a>
    <a href="/admin/?a=home">Videos y ajustes</a>
    <a href="/" target="_blank" rel="noopener">Ver sitio ↗</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button class="linkish">Salir</button></form>
  </nav>
</header>
<?php endif ?>
<main>
<?php if ($flash): ?><p class="flash <?= e($flash[1]) ?>" role="status"><?= e($flash[0]) ?></p><?php endif ?>
<?php
}

function foot(): void
{
    echo "</main>\n<script src=\"/admin/admin.js?v=1\" defer></script>\n</body>\n</html>";
}

if (empty($conf['hash'])) {
    head('Crear contraseña');
    ?>
<form method="post" class="box narrow">
  <img class="logo" src="/img/logo.png" alt="Quique Marduk">
  <h1>Crear contraseña del panel</h1>
  <p>Es la primera vez que se entra al panel. Elegí una contraseña de al menos 10 caracteres y guardala en un lugar seguro.</p>
  <?= csrf_field() ?><input type="hidden" name="do" value="setup">
  <label>Contraseña<input type="password" name="pw" minlength="10" required autocomplete="new-password"></label>
  <label>Repetir contraseña<input type="password" name="pw2" minlength="10" required autocomplete="new-password"></label>
  <button class="btn">Crear y entrar</button>
</form>
<?php
    foot();
    exit;
}

if (!$logged) {
    head('Entrar');
    ?>
<form method="post" class="box narrow">
  <img class="logo" src="/img/logo.png" alt="Quique Marduk">
  <h1>Panel de administración</h1>
  <?= csrf_field() ?><input type="hidden" name="do" value="login">
  <label>Contraseña<input type="password" name="pw" required autofocus autocomplete="current-password"></label>
  <button class="btn">Entrar</button>
</form>
<?php
    foot();
    exit;
}

if ($action === 'edit') {
    $list = load_all();
    $id = (string)($_GET['id'] ?? '');
    $i = $id !== '' ? find_index($list, $id) : null;
    $p = $i !== null ? $list[$i] : ['id' => '', 'visible' => true, 'destacado' => false, 'precio' => '', 'imagen' => null, 'fotos' => [], 'videos' => [], 'es' => null, 'en' => null];
    $is_new = $i === null;
    head($is_new ? 'Nuevo producto' : 'Editar producto');
    $yt = implode("\n", array_map(function ($v) {
        return 'https://www.youtube.com/watch?v=' . $v;
    }, $p['videos'] ?? []));
    ?>
<form method="post" enctype="multipart/form-data" class="edit">
  <?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
  <div class="edit-head">
    <h1><?= $is_new ? 'Nuevo producto' : e(($p['es']['titulo'] ?? $p['en']['titulo'] ?? '')) ?></h1>
    <?php if (!$is_new): ?>
      <div class="links">
        <?php if (!empty($p['es']['slug'])): ?><a href="/portfolio/<?= e(rawurlencode($p['es']['slug'])) ?>/" target="_blank" rel="noopener">Ver en español ↗</a><?php endif ?>
        <?php if (!empty($p['en']['slug'])): ?><a href="/en/portfolio/<?= e(rawurlencode($p['en']['slug'])) ?>/" target="_blank" rel="noopener">Ver en inglés ↗</a><?php endif ?>
      </div>
    <?php endif ?>
  </div>

  <div class="grid">
    <section class="box">
      <h2>General</h2>
      <label class="check"><input type="checkbox" name="visible" value="1" <?= !empty($p['visible']) ? 'checked' : '' ?>> Publicado (se ve en el sitio)</label>
      <label class="check"><input type="checkbox" name="destacado" value="1" <?= !empty($p['destacado']) ? 'checked' : '' ?>> Destacado en la página de inicio</label>
      <label>Precio
        <textarea name="precio" rows="2" placeholder="u$s 45"><?= e($p['precio'] ?? '') ?></textarea>
        <small>Una línea por precio. Ej.: <code>u$s 45</code> o <code>Modelo grande: u$s 60</code></small>
      </label>
      <label>Videos de YouTube
        <textarea name="videos" rows="3" placeholder="https://www.youtube.com/watch?v=..."><?= e($yt) ?></textarea>
        <small>Pegá un link de YouTube por línea.</small>
      </label>
    </section>

    <section class="box">
      <h2>Fotos</h2>
      <div class="photos">
        <?php foreach (array_merge($p['imagen'] ? [$p['imagen']] : [], $p['fotos'] ?? []) as $k => $f): ?>
          <figure>
            <img src="<?= e($f) ?>" alt="">
            <figcaption><?= $k === 0 ? 'Principal' : 'Extra' ?> · <label class="check small"><input type="checkbox" name="quitar[]" value="<?= e($f) ?>"> quitar</label></figcaption>
          </figure>
        <?php endforeach ?>
      </div>
      <label>Foto principal <?= $p['imagen'] ? '(reemplazar)' : '' ?><input type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif"></label>
      <label>Fotos extra<input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label>
      <small>JPG, PNG o WEBP, hasta 10 MB. Lo ideal es una foto cuadrada.</small>
    </section>
  </div>

  <div class="grid">
    <?php foreach (['es' => 'Español', 'en' => 'Inglés'] as $l => $name): $d = $p[$l] ?? []; ?>
    <section class="box">
      <h2><?= $name ?></h2>
      <label>Nombre del truco<input name="<?= $l ?>_titulo" value="<?= e($d['titulo'] ?? '') ?>" <?= $l === 'es' ? 'autofocus' : '' ?>></label>
      <label>Descripción
        <textarea name="<?= $l ?>_texto" rows="14"><?= e($d['texto'] ?? '') ?></textarea>
        <small>Dejá una línea en blanco entre párrafos. Empezá la línea con <code>- </code> para hacer una lista. <code>**texto**</code> = negrita.</small>
      </label>
      <label>Aviso corto (opcional)<input name="<?= $l ?>_nota" value="<?= e($d['nota'] ?? '') ?>" placeholder="<?= $l === 'es' ? 'Especifique color al ordenar' : 'Select color when ordering' ?>"></label>
      <details>
        <summary>Dirección web</summary>
        <label>Slug<input name="<?= $l ?>_slug" value="<?= e($d['slug'] ?? '') ?>" placeholder="se genera solo a partir del nombre"></label>
      </details>
      <?php if ($l === 'en'): ?><small>Si dejás vacío el nombre en inglés, el producto solo aparece en la versión en español (y al revés).</small><?php endif ?>
    </section>
    <?php endforeach ?>
  </div>

  <div class="save-bar">
    <button class="btn" name="seguir" value="">Guardar</button>
    <button class="btn ghost" name="seguir" value="1">Guardar y seguir editando</button>
    <a href="/admin/">Cancelar</a>
  </div>
</form>
<?php if (!$is_new): ?>
<form method="post" class="danger" data-confirm="¿Eliminar este producto? No se puede deshacer.">
  <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
  <button class="btn red">Eliminar producto</button>
</form>
<?php endif ?>
<?php
    foot();
    exit;
}

if ($action === 'home') {
    $s = sitio();
    $fmt = function ($ids) {
        return implode("\n", array_map(function ($v) {
            return 'https://www.youtube.com/watch?v=' . $v;
        }, $ids));
    };
    head('Videos y ajustes');
    ?>
<h1>Videos y ajustes</h1>
<div class="grid">
  <form method="post" class="box">
    <h2>Videos de la página de inicio</h2>
    <?= csrf_field() ?><input type="hidden" name="do" value="home_videos">
    <label>Español<textarea name="es" rows="5"><?= e($fmt($s['videos_inicio']['es'] ?? [])) ?></textarea></label>
    <label>Inglés<textarea name="en" rows="5"><?= e($fmt($s['videos_inicio']['en'] ?? [])) ?></textarea></label>
    <small>Un link de YouTube por línea, en el orden en que se muestran.</small>
    <button class="btn">Guardar videos</button>
  </form>
  <form method="post" class="box">
    <h2>Cambiar contraseña</h2>
    <?= csrf_field() ?><input type="hidden" name="do" value="password">
    <label>Contraseña actual<input type="password" name="actual" required autocomplete="current-password"></label>
    <label>Nueva contraseña<input type="password" name="pw" minlength="10" required autocomplete="new-password"></label>
    <label>Repetir nueva contraseña<input type="password" name="pw2" minlength="10" required autocomplete="new-password"></label>
    <button class="btn">Cambiar contraseña</button>
  </form>
</div>
<?php
    foot();
    exit;
}

// lista de productos
$list = load_all();
usort($list, function ($a, $b) {
    return strcmp($b['fecha'] ?? '', $a['fecha'] ?? '');
});
head('Productos');
?>
<div class="list-head">
  <h1>Productos <span class="count"><?= count($list) ?></span></h1>
  <input type="search" id="filtro" placeholder="Buscar producto…" aria-label="Buscar producto">
  <a class="btn" href="/admin/?a=edit">+ Nuevo producto</a>
</div>
<ul class="products" id="products">
<?php foreach ($list as $p): $name = $p['es']['titulo'] ?? $p['en']['titulo'] ?? '(sin nombre)'; ?>
  <li id="<?= e($p['id']) ?>" class="<?= empty($p['visible']) ? 'hidden-item' : '' ?>" data-search="<?= e(mb_strtolower(($p['es']['titulo'] ?? '') . ' ' . ($p['en']['titulo'] ?? ''))) ?>">
    <a class="thumb" href="/admin/?a=edit&id=<?= e(rawurlencode($p['id'])) ?>"><?php if ($p['imagen']): ?><img src="<?= e($p['imagen']) ?>" alt="" loading="lazy"><?php endif ?></a>
    <div class="info">
      <a class="name" href="/admin/?a=edit&id=<?= e(rawurlencode($p['id'])) ?>"><?= e($name) ?></a>
      <div class="tags">
        <span class="tag <?= $p['es'] ? 'on' : '' ?>">ES</span>
        <span class="tag <?= $p['en'] ? 'on' : '' ?>">EN</span>
        <?php if (empty($p['visible'])): ?><span class="tag warn">Oculto</span><?php endif ?>
        <?php if (!empty($p['destacado'])): ?><span class="tag star">★ Destacado</span><?php endif ?>
        <?php if (!empty($p['videos'])): ?><span class="tag">▶ <?= count($p['videos']) ?></span><?php endif ?>
      </div>
    </div>
    <form method="post" class="price-form">
      <?= csrf_field() ?><input type="hidden" name="do" value="price"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <textarea name="precio" rows="1" aria-label="Precio de <?= e($name) ?>"><?= e($p['precio'] ?? '') ?></textarea>
      <button class="btn small">Guardar precio</button>
    </form>
    <div class="row-actions">
      <a class="btn ghost small" href="/admin/?a=edit&id=<?= e(rawurlencode($p['id'])) ?>">Editar</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
        <button class="btn ghost small"><?= empty($p['visible']) ? 'Publicar' : 'Ocultar' ?></button></form>
    </div>
  </li>
<?php endforeach ?>
</ul>
<?php
foot();
