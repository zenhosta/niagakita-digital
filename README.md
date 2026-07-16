# NiagaKita Digital

Marketplace produk digital PHP native. Mendukung checkout tanpa akun, produk link digital, stok akun terenkripsi, SMTP, dan pembayaran Pakasir.

## Requirement

- cPanel dengan Terminal atau SSH.
- PHP 8.2 atau lebih baru.
- MySQL/MariaDB.
- Extension PHP: `pdo_mysql`, `openssl`, `mbstring`, `json`.
- Composer 2.
- SSL aktif. Checkout, admin, dan webhook wajib memakai HTTPS.

## Deploy Dengan Git Clone

1. Buat database dan user MySQL lewat cPanel. Beri user akses penuh ke database tersebut.
2. Buka **Terminal** cPanel.
3. Masuk ke `public_html`.

```bash
cd ~/public_html
```

4. Pastikan folder kosong. Jika domain utama sudah memiliki file website lama, backup dulu. Jangan menjalankan `git clone` ke folder berisi aplikasi lain.
5. Clone repository langsung ke folder saat ini.

```bash
git clone https://github.com/zenhosta/niagakita-digital.git .
```

6. Dependency `vendor/` sudah termasuk repository. Composer tidak diperlukan untuk instalasi standar.
7. Pastikan folder runtime dapat ditulis user cPanel.

```bash
chmod 755 storage
```

8. Buka installer melalui browser.

```text
https://domain-anda.com/install
```

9. Selesaikan installer.
   - Cek requirement server.
   - Isi koneksi database.
   - Buat admin.
   - Input lisensi: `zenhosta`.

10. Setelah selesai, login di:

```text
https://domain-anda.com/admin/login
```

## Konfigurasi Setelah Install

1. Buka **Admin > Pengaturan**.
2. Isi project slug dan API key Pakasir.
3. Isi SMTP host, port, username, password, email pengirim, dan nama pengirim.
4. Set webhook Pakasir ke:

```text
https://domain-anda.com/webhooks/pakasir
```

5. Tambahkan produk di **Admin > Produk**.
6. Untuk ebook/video, pilih `File / link digital`, lalu isi URL HTTPS Google Drive, Cloudflare R2, S3, atau storage lain.
7. Untuk akun digital, pilih `Stok akun`, lalu input satu akun per baris.

## Update Dari Git

Backup database dan `.env` sebelum update.

```bash
cd ~/public_html
git pull origin main
```

Jangan hapus:

```text
.env
storage/installed.lock
```

## Keamanan Deploy

- `.env`, `database/`, `storage/`, `vendor/`, dan file Composer diblok lewat `.htaccess`.
- Jangan commit `.env`, password SMTP, API key Pakasir, atau database dump.
- Pastikan Apache `mod_rewrite` aktif.
- Gunakan password admin unik minimal 12 karakter.
- Gunakan link delivery HTTPS saja.
- Cek webhook Pakasir memakai HTTPS publik, bukan localhost.

## Troubleshooting

### Error 500

```bash
php -v
php -m | grep -E 'pdo_mysql|openssl|mbstring|json'
php -l index.php
php -l app.php
```

Pastikan PHP domain di cPanel memakai versi 8.2+.

### Installer Mengatakan Folder Tidak Writable

```bash
chmod 755 ~/public_html
chmod 755 ~/public_html/storage
```

Jika masih gagal, ubah ownership melalui File Manager atau hubungi provider hosting. Jangan gunakan permission `777` kecuali provider secara eksplisit memerlukannya.

### `git clone` Gagal Karena Folder Tidak Kosong

Clone ke folder sementara, lalu pindahkan file setelah backup:

```bash
cd ~
git clone https://github.com/zenhosta/niagakita-digital.git niagakita-digital
cp -a niagakita-digital/. ~/public_html/
```
