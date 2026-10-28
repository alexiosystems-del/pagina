<?php
// Núcleo: configuración, lectura/escritura de datos y utilidades. Compatible con PHP 7.4 a 8.4.

const SITE_URL = 'https://www.marduk.com.ar';
const WA_NUMBER = '5491153121510';
const WA_DISPLAY = '+54 9 11 5312-1510';

define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function read_json(string $file, array $default = []): array
{
    $path = DATA_DIR . '/' . $file;
    if (!is_file($path)) {
        return $default;
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}

/** Escritura atómica con copia de seguridad (se guardan las últimas 30). */
function write_json(string $file, array $data): void
{
    $path = DATA_DIR . '/' . $file;
    $backups = DATA_DIR . '/backups';
    if (!is_dir($backups)) {
        mkdir($backups, 0755, true);
    }
    if (is_file($path)) {
        $base = pathinfo($file, PATHINFO_FILENAME);
        copy($path, $backups . '/' . $base . '-' . date('Ymd-His') . '.json');
        $old = glob($backups . '/' . $base . '-*.json') ?: [];
        sort($old);
        foreach (array_slice($old, 0, max(0, count($old) - 30)) as $f) {
            @unlink($f);
        }
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo guardar ' . $file);
    }
}

function productos(): array
{
    return read_json('productos.json', ['productos' => []])['productos'];
}

function sitio(): array
{
    return read_json('sitio.json', ['videos_inicio' => ['es' => [], 'en' => []], 'galeria' => [], 'imagenes' => []]);
}

/** Productos publicados en un idioma, del más nuevo al más viejo. */
function productos_visibles(string $lang): array
{
    $out = array_filter(productos(), function ($p) use ($lang) {
        return !empty($p['visible']) && !empty($p[$lang]['titulo']) && !empty($p[$lang]['slug']);
    });
    usort($out, function ($a, $b) {
        return strcmp($b['fecha'] ?? '', $a['fecha'] ?? '');
    });
    return array_values($out);
}

function wa_link(string $text): string
{
    return 'https://wa.me/' . WA_NUMBER . '?text=' . rawurlencode($text);
}

/** Extrae IDs de YouTube de un texto (links o IDs sueltos, uno por línea). */
function youtube_ids(string $text): array
{
    $ids = [];
    foreach (preg_split('/[\s,]+/', $text) as $tok) {
        if ($tok === '') {
            continue;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~', $tok, $m)) {
            $ids[] = $m[1];
        } elseif (preg_match('/^[\w-]{11}$/', $tok)) {
            $ids[] = $tok;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Texto simple -> HTML seguro.
 * Párrafos separados por línea en blanco, líneas con "- " son viñetas, **texto** es negrita.
 */
function texto_html(string $t): string
{
    $t = str_replace(["\r\n", "\r"], "\n", trim($t));
    if ($t === '') {
        return '';
    }
    $html = '';
    foreach (preg_split('/\n\s*\n/', $t) as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
        if (!$lines) {
            continue;
        }
        $bullets = array_filter($lines, function ($l) {
            return strpos($l, '- ') === 0 || strpos($l, '• ') === 0;
        });
        if (count($bullets) === count($lines)) {
            $html .= '<ul>';
            foreach ($lines as $l) {
                $html .= '<li>' . inline_md(trim(substr($l, strpos($l, ' ')))) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map('inline_md', $lines)) . '</p>';
        }
    }
    return $html;
}

function inline_md(string $s): string
{
    return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($s));
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c', 'ã' => 'a', 'õ' => 'o', 'â' => 'a', 'ê' => 'e', 'ô' => 'o']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'producto';
}

function plain(string $t, int $len = 155): string
{
    $t = trim(preg_replace('/\s+/u', ' ', str_replace(['**', '- '], '', $t)));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}
