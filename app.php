<?php
declare(strict_types=1);

const ROOT = __DIR__;

function env(): array {
    static $env;
    if ($env !== null) return $env;
    $file = ROOT . '/.env';
    $env = is_file($file) ? parse_ini_file($file, false, INI_SCANNER_RAW) : [];
    return $env ?: [];
}
function installed(): bool { return is_file(ROOT . '/storage/installed.lock') && env() !== []; }
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $e = env();
    $pdo = new PDO("mysql:host={$e['DB_HOST']};port={$e['DB_PORT']};dbname={$e['DB_NAME']};charset=utf8mb4", $e['DB_USER'], $e['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return $pdo;
}
function q(string $sql, array $values = []): PDOStatement { $s = db()->prepare($sql); $s->execute($values); return $s; }
function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function base(string $path = ''): string { $prefix = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); return ($prefix === '/' ? '' : $prefix) . $path; }
function absolute_url(string $path = ''): string { $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; $host = preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'); return ($https ? 'https://' : 'http://') . $host . base($path); }
function redirect(string $path): never { header('Location: ' . base($path), true, 303); exit; }
function flash(string $key, ?string $message = null): ?string { if ($message !== null) { $_SESSION['flash'][$key] = $message; return null; } $m = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $m; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('CSRF tidak valid.'); } }
function admin(): ?array { return $_SESSION['admin'] ?? null; }
function auth(): void { if (!admin()) redirect('/admin/login'); }
function post_only(): void { if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Method tidak diizinkan.'); } }
function valid_product_type(string $type): bool { return in_array($type, ['file_link', 'account_stock', 'manual'], true); }
function valid_delivery_url(string $url): bool { return $url === '' || (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https'], true)); }
function token(int $bytes = 24): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }
function encrypt_stock(string $value): string { $key = hash('sha256', env()['APP_KEY'], true); $iv = random_bytes(12); $tag = ''; $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag); return base64_encode($iv . $tag . $cipher); }
function decrypt_stock(string $value): string { $raw = base64_decode($value, true); $key = hash('sha256', env()['APP_KEY'], true); return openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)) ?: ''; }
function view(string $title, string $content, bool $adminArea = false): never {
    $notice = flash('success'); $error = flash('error'); $brand = installed() ? (q('SELECT value FROM settings WHERE `key` = "store_name"')->fetchColumn() ?: 'Toko Digital') : 'Toko Digital';
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> | <?=e($brand)?></title><style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap');
    :root{--ink:#17231e;--paper:#f3f0e8;--lime:#c8f45c;--rust:#d65331;--line:#c8c4b8}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font:15px 'DM Mono',monospace}a{color:inherit}.nav{display:flex;justify-content:space-between;gap:20px;padding:22px 6vw;border-bottom:1px solid var(--line);align-items:center}.brand{font:700 24px Fraunces,serif;text-decoration:none}.nav a{text-decoration:none}.wrap{max-width:1100px;margin:auto;padding:46px 6vw}.hero{padding:44px 0 32px;border-bottom:1px solid var(--line)}h1,h2{font-family:Fraunces,serif;line-height:1;margin:0 0 18px}h1{font-size:clamp(40px,7vw,78px);max-width:820px}h2{font-size:30px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px;margin-top:30px}.card,.panel{border:1px solid var(--ink);padding:22px;background:#fffdf8}.card{min-height:200px;display:flex;flex-direction:column;justify-content:space-between;text-decoration:none}.type{font-size:11px;text-transform:uppercase;color:#617069}.price{font:700 25px Fraunces,serif}.btn,button{background:var(--ink);color:white;border:1px solid var(--ink);padding:12px 16px;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}.btn.lime{background:var(--lime);color:var(--ink)}form{display:grid;gap:13px;max-width:640px}label{display:grid;gap:7px}input,select,textarea{width:100%;padding:12px;border:1px solid var(--ink);background:#fffdf8;font:inherit}textarea{min-height:115px}.notice{padding:14px;margin-bottom:18px;background:var(--lime);border:1px solid var(--ink)}.error{background:#ffd1c7}.admin-nav{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:32px;border-bottom:1px solid var(--line);padding-bottom:16px}.table{width:100%;border-collapse:collapse;background:#fffdf8}.table th,.table td{text-align:left;padding:12px;border:1px solid var(--line);vertical-align:top}.muted{color:#617069;font-size:13px}.split{display:grid;grid-template-columns:1fr 1fr;gap:35px}.code{overflow-wrap:anywhere;background:#ece8de;padding:12px}@media(max-width:650px){.split{grid-template-columns:1fr}.wrap{padding-top:28px}.table{font-size:12px}}
    </style></head><body><nav class="nav"><a class="brand" href="<?=base('/')?>"><?=e($brand)?></a><span><?php if ($adminArea): ?><a href="<?=base('/admin')?>">Panel</a> · <form method="post" action="<?=base('/admin/logout')?>" style="display:inline"><input type="hidden" name="_csrf" value="<?=csrf()?>"><button type="submit" style="padding:0;border:0;background:none;color:inherit">Keluar</button></form><?php else: ?><a href="<?=base('/admin/login')?>">Admin</a><?php endif ?></span></nav><main class="wrap"><?php if($adminArea): ?><nav class="admin-nav"><a href="<?=base('/admin')?>">Ringkasan</a><a href="<?=base('/admin/products')?>">Produk</a><a href="<?=base('/admin/orders')?>">Pesanan</a><a href="<?=base('/admin/settings')?>">Pengaturan</a></nav><?php endif ?><?php if($notice): ?><div class="notice"><?=e($notice)?></div><?php endif ?><?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif ?><?=$content?></main></body></html><?php exit;
}
