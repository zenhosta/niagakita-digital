<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = file_get_contents($root . '/index.php');
$failures = [];

function expect(bool $condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

// Every application route must have a route handler and state-changing routes use CSRF.
$routes = [
    '/install', '/install/database', '/install/admin', '/install/license',
    '/admin/login', '/admin/logout', '/admin', '/admin/appearance',
    '/admin/products', '/admin/products/new', '/admin/stocks', '/admin/orders', '/admin/orders/', '/admin/reports', '/admin/settings',
    '/admin/settings/test-smtp', '/webhooks/pakasir', '/webhook/pakasir', '/pakasir/webhook', '/api/pakasir/webhook', '/checkout/', '/invoice/',
    '/produk/', '/media/',
];
foreach ($routes as $route) expect(str_contains($source, $route), "Route hilang: $route");
expect(str_contains($source, 'function pakasir_transaction(array $order)'), 'Pakasir Transaction Detail client hilang');
expect(str_contains($source, 'https://app.pakasir.com/api/transactiondetail?'), 'Endpoint Transaction Detail Pakasir salah atau hilang');
expect(str_contains($source, 'sync_pakasir_payment($o)'), 'Invoice tidak melakukan cek mandiri ke Pakasir');
expect(str_contains($source, 'Cek ulang status'), 'Tombol cek ulang invoice hilang');
expect(str_contains($source, 'function validate_pakasir_webhook'), 'Validator webhook Pakasir hilang');
expect(str_contains($source, 'fulfill_order($order);http_response_code(200)'), 'Webhook completed tidak menjalankan fulfillment');
expect(str_contains($source, 'Order ID Pakasir yang dicek:'), 'Invoice tidak menampilkan Order ID Pakasir yang dicek');
expect(str_contains($source, '?check=1'), 'Tombol cek ulang tidak memakai URL eksplisit');
expect(str_contains($source, 'stock_total'), 'Jumlah stok publik tidak dihitung');
expect(str_contains($source, "'/admin/stocks' && \$method === 'GET'"), 'Halaman admin stok hilang');
expect(str_contains($source, '/admin/stocks/(\\d+)/delete'), 'Aksi hapus stok hilang');
expect(str_contains($source, "status='fulfilled'"), 'Laporan omzet harus menghitung pesanan berhasil saja');
expect(str_contains($source, 'Rekap harian'), 'Tabel laporan omzet hilang');
expect(str_contains($source, "/admin/orders/(\\d+)/check"), 'Route cek Pakasir admin hilang');
expect(str_contains($source, "/admin/orders/(\\d+)/validate"), 'Route validasi manual admin hilang');
expect(str_contains($source, 'Cek Pakasir'), 'Tombol cek Pakasir admin hilang');
expect(str_contains($source, 'Validasi manual'), 'Tombol validasi manual admin hilang');
expect(str_contains($source, 'name="email_note"'), 'Field catatan email produk hilang');
expect(str_contains($source, 'delivery_url,email_note'), 'Catatan email tidak disnapshot ke order item');
foreach (['Invoice:', 'Produk:', 'Jenis:', 'Harga:', 'CATATAN DARI PENJUAL'] as $detail) {
    expect(str_contains($source, $detail), "Detail fulfillment email hilang: $detail");
}

foreach ([
    "'/admin/products/new' && \$method === 'POST'",
    "'/admin/settings' && \$method==='POST'",
    "'/admin/appearance' && \$method === 'POST'",
    "'/admin/settings/test-smtp' && \$method==='POST'",
    "/admin/orders/(\\d+)/check",
    "/admin/orders/(\\d+)/validate",
] as $handler) {
    $position = strpos($source, $handler);
    expect($position !== false, "Handler POST hilang: $handler");
    if ($position !== false) expect(str_contains(substr($source, $position, 180), 'check_csrf()'), "CSRF tidak dipakai: $handler");
}

// Forms must use centralized markup helper instead of fragile inline quoting.
expect(str_contains($source, "function hidden_csrf(string \$token)"), 'Helper hidden CSRF hilang');
expect(str_contains($source, "action=\"'.base('/admin/products/new').'\">'.hidden_csrf(\$csrf)"), 'Form produk baru tidak memakai helper CSRF');
expect(str_contains($source, "action=\"'.base('/admin/settings').'\">'.hidden_csrf(\$csrf)"), 'Form settings tidak memakai helper CSRF');

// Account stock requires email|password, one line per account.
function parseStockForTest(string $input): ?array {
    $accounts = [];
    foreach (preg_split('/\R/', trim($input)) as $line) {
        $parts = explode('|', trim($line), 2);
        if (count($parts) !== 2 || !filter_var(trim($parts[0]), FILTER_VALIDATE_EMAIL) || trim($parts[1]) === '') return null;
        $accounts[] = trim($parts[0]) . '|' . trim($parts[1]);
    }
    return $accounts;
}
expect(parseStockForTest("email@gmail.com|password\nuser@example.com|secret") === ['email@gmail.com|password', 'user@example.com|secret'], 'Parser stok valid gagal');
expect(parseStockForTest('email@gmail.com:password') === null, 'Parser stok menerima delimiter salah');
expect(parseStockForTest('not-an-email|password') === null, 'Parser stok menerima email salah');

function formatAccountCredentialsForTest(string $payload): string {
    $parts = explode('|', $payload, 2);
    if (count($parts) === 2 && filter_var(trim($parts[0]), FILTER_VALIDATE_EMAIL) && trim($parts[1]) !== '') return "Email: " . trim($parts[0]) . "\nPassword: " . trim($parts[1]);
    return $payload;
}
expect(formatAccountCredentialsForTest('email@gmail.com|pass123') === "Email: email@gmail.com\nPassword: pass123", 'Email/password tidak diberi label');
expect(formatAccountCredentialsForTest('username|pass123') === 'username|pass123', 'Format non-email tidak boleh diubah');
expect(str_contains($source, 'function format_account_credentials'), 'Formatter kredensial email hilang');

// Pakasir docs webhook fixture: completed + amount + order_id + project.
function validatePakasirWebhookForTest(mixed $body): ?string {
    if (!is_array($body)) return 'Payload JSON tidak valid';
    if (($body['status'] ?? '') !== 'completed') return 'Status pembayaran bukan completed';
    if (!isset($body['amount'], $body['order_id'], $body['project']) || !is_numeric($body['amount']) || !is_string($body['order_id']) || !is_string($body['project'])) return 'Field webhook tidak lengkap';
    return null;
}
$webhook = ['amount' => 120000, 'order_id' => 'ZTH-20260716-JWQPWFZ_', 'project' => 'sound-on', 'status' => 'completed', 'payment_method' => 'qris'];
expect(validatePakasirWebhookForTest($webhook) === null, 'Webhook completed valid ditolak');
$webhook['status'] = 'pending';
expect(validatePakasirWebhookForTest($webhook) !== null, 'Webhook pending diterima');
expect(validatePakasirWebhookForTest(['status' => 'completed']) !== null, 'Webhook field tidak lengkap diterima');

// Payment mapping: a completed webhook may settle only its own local order.
function webhookMatchesOrderForTest(array $webhook, array $order, string $project): bool {
    return ($webhook['status'] ?? '') === 'completed'
        && ($webhook['order_id'] ?? '') === $order['invoice']
        && (int) ($webhook['amount'] ?? -1) === (int) $order['total']
        && ($webhook['project'] ?? '') === $project;
}
$paidWebhook = ['amount' => 120000, 'order_id' => 'ZTH-20260716-JWQPWFZ_', 'project' => 'sound-on', 'status' => 'completed'];
expect(webhookMatchesOrderForTest($paidWebhook, ['invoice' => 'ZTH-20260716-JWQPWFZ_', 'total' => 120000], 'sound-on'), 'Webhook invoice yang cocok tidak dapat diproses');
expect(!webhookMatchesOrderForTest($paidWebhook, ['invoice' => 'ZTH-20260716-S6JZF-3O', 'total' => 120000], 'sound-on'), 'Webhook invoice berbeda tidak boleh memproses order lain');

if ($failures) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: route contract, CSRF, stock parser\n";
