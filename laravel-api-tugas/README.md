# StockFlow — API Monitoring Stok Kedaluwarsa

Backend REST API **Sistem Pemantauan Stok Kedaluwarsa** sesuai PRD 9.4 — Laravel 12 + Sanctum (token personal) + MySQL 8.0. Dipakai aplikasi Android StockFlow (Retrofit).

## Format Respons Seragam (PRD 9.5)

```jsonc
// sukses
{ "success": true, "message": "…", "data": { } }
// gagal validasi
{ "success": false, "message": "Data tidak valid", "errors": { "kolom": ["…"] } }
```

Seluruh pesan berbahasa Indonesia dan merujuk nomor aturan PRD (FR-xx, BR-xx, Q-xx, NFR-xx).

## Menjalankan

```bash
composer install
cp .env.example .env        # isi DB_*; file .env tidak pernah ikut commit
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve           # http://127.0.0.1:8000
```

Database lokal memakai container Docker (port **3307**, tidak mengganggu MySQL lain):

```bash
docker run -d --name stockflow-mysql \
  -e MYSQL_ROOT_PASSWORD='*ubuntu123*' \
  -e MYSQL_DATABASE=stockflow \
  -e MYSQL_USER=stockflow_app \
  -e MYSQL_PASSWORD='stockflow_dev_2026' \
  -p 3307:3306 mysql:8.0
```

`.env` minimal: `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=stockflow`, `DB_USERNAME=stockflow_app`, `DB_PASSWORD=stockflow_dev_2026`.

### Akun demo (seeder)

| Username | Password   | Peran   |
| -------- | ---------- | ------- |
| pemilik  | password   | pemilik |
| staf     | password   | staf    |

## Kontrak API — 49 endpoint (`routes/api.php`)

Auth: `POST /api/login`, `POST /api/logout`, `GET /api/me`, manajemen token `GET/DELETE /api/tokens*` (FR-02, FR-05).

| Grup | Endpoint | Keterangan |
| --- | --- | --- |
| Dashboard | `GET /api/dashboard` | Ringkasan sesuai peran + rekomendasi FEFO (FR-03) |
| Barang | `GET/POST /api/products`, `GET/PUT/DELETE /api/products/{id}`, `POST /api/products/bulk-delete` | Master barang + hapus massal (FR-04, FR-17, FR-18, AB-08) |
| Batch | `GET/POST /api/products/{barang}/batches`, `GET/PUT/DELETE /api/batches/{batch}`, `GET /api/batches`, `POST /api/batches/bulk-delete` | Batch stok, daftar FEFO (FR-05, FR-12) |
| Transaksi | `GET/POST /api/stock-transactions` | masuk / keluar FEFO / penyesuaian (FR-06, FR-07, FR-13, BR-03) |
| Penanganan | `POST /api/expiry-actions` | musnah / retur / lainnya (FR-12) |
| Monitoring | `GET /api/expiry-monitoring`, `GET /api/expiry-monitoring/perlu-ditangani`, `GET /api/products/{barang}/fefo` | status kedaluwarsa & alokasi FEFO (FR-10, FR-11) |
| Lampiran | `POST /api/batches/{batch}/attachments`, `GET /api/attachments/{id}/file`, `DELETE /api/attachments/{id}` | foto bukti JPG/PNG, akses terotorisasi (FR-14) |
| Impor | `POST /api/imports/products/preview`, `POST /api/imports/products/commit`, `GET /api/imports[/{id}]` | impor xlsx 2 langkah + token 30 menit (FR-15, BR-06) |
| Notifikasi | `GET /api/notifications`, `PATCH /api/notifications/{id}/read`, `POST /api/notifications/read-all` | peringatan (FR-13, FR-24) |
| Laporan | `GET /api/reports/{stock,transactions,expiry,fast-moving}`, `GET /api/reports/export?jenis=&format=xlsx\|pdf` | khusus pemilik (FR-16, BR-08) |
| Audit | `GET /api/riwayat-aktivitas` | audit tak terubah; staf melihat miliknya (FR-31, NFR-08) |
| Akun | `GET/POST /api/users`, `PUT/DELETE /api/users/{id}`, `PATCH /api/users/{id}/aktif` | khusus pemilik (FR-05, BR-08) |
| Pengaturan | `GET/PUT /api/pengaturan` | ambang hari & batas unggah/impor (FR-25, BR-08) |

Aturan inti: FEFO wajib (BR-04), batch kedaluwarsa/Ditangani tidak bisa keluar normal (BR-03), perubahan saldo atomik `DB::transaction` + `lockForUpdate` (NFR-02), batas satu pemilik aktif (FR-05).

## Validasi otomatis

```bash
php artisan test            # 43 feature test: auth, role, FEFO, monitoring,
                            # laporan, impor, lampiran, manajemen akun
vendor/bin/pint --dirty     # format PSR-12
```

## Terkait aplikasi Android

- BASE_URL mesin: `http://<ip-pc>:8000/api/` (komputer host, bukan emulator/localhost).
- Kontrak Retrofit diselaraskan ke endpoint tabel di atas + kunci `success` (lihat `ApiService.kt`).
- Pemantauan otomatis: `php artisan stockflow:monitor` (terjadwal 06.00 & 18.00 via scheduler `routes/console.php`) menyegarkan status batch dan membuat notifikasi (FR-20, FR-23).

## Koleksi Postman

Impor `docs/stockflow.postman_collection.json` — variabel `base_url` + token otomatis tersimpan saat `POST login`.
