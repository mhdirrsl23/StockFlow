# StokAman (StockFlow)

Aplikasi manajemen stok barang dengan pemantauan kedaluwarsa berbasis **FEFO** (*First Expired First Out*). Repositori ini terdiri dari dua komponen:

- **`laravel-api-tugas/`** — REST API Laravel + Sanctum + MySQL 8 (kontrak PRD 9.4).
- **`android/`** — aplikasi Android native (Kotlin) yang mengonsumsi API tersebut.

> Proyek akademik: implementasi PRD 9.4 "Sistem Pemantauan Stok Kedaluwarsa".

## Fitur

### Autentikasi & akun
- Login token (Laravel Sanctum), sesi tunggal per akun (login baru mencabut token lama), throttle 5 percobaan login/menit.
- Manajemen pengguna (khusus pemilik): tambah, ubah, aktif/nonaktif, hapus.
- Profil, ganti kata sandi (`GET /me`).

### Barang & batch
- CRUD barang + pencarian dan filter kategori.
- Batch per barang: nomor batch, tanggal kedaluwarsa, jumlah awal dan sisa.
- Urutan FEFO otomatis (kedaluwarsa terdekat lebih dulu).
- Penanganan kedaluwarsa (dimusnahkan / dikembalikan ke pemasok / lainnya) sesuai BR-01.
- Lampiran foto bukti per batch.

### Transaksi stok
- Stok masuk (menambah batch baru).
- Stok keluar memotong batch secara FEFO.
- Penyesuaian stok — alasan wajib diisi.

### Notifikasi & dashboard
- Dashboard ringkasan: barang/batch aktif, stok menipis, batch mendekati kedaluwarsa, notifikasi belum dibaca.
- Notifikasi: Mendekati Kedaluwarsa, Kedaluwarsa, Stok Menipis; tandai dibaca per item maupun semua sekaligus.

### Laporan, impor, audit
- Laporan stok, mutasi/transaksi, kedaluwarsa, dan fast-moving.
- Ekspor laporan **xlsx/pdf** (khusus pemilik).
- Impor produk via xlsx: `preview` → `commit` (tersedia di API; jalur desktop/web).
- Riwayat aktivitas pengguna.

### Pengaturan (khusus pemilik)
- Ambang peringatan kedaluwarsa (hari), ukuran maksimum lampiran (MB), maksimum baris impor.

## Arsitektur

```text
┌───────────────────────┐    HTTP/JSON (Retrofit + OkHttp)   ┌─────────────────────────┐
│  Aplikasi Android     │ ─────────────────────────────────► │  REST API Laravel 13    │
│  Kotlin · Material    │ ◄───────────────────────────────── │  Sanctum · validasi     │
└───────────────────────┘   {success, message, data|errors}  └───────────┬─────────────┘
                                                                         │ Eloquent
                                                                         ▼
                                                              ┌────────────────────┐
                                                              │  MySQL 8 (Docker)  │
                                                              └────────────────────┘
```

## Struktur repositori

```text
StockFlow/
├── android/                      # aplikasi Android (Kotlin, Gradle 9.6)
│   └── app/src/main/
│       ├── java/com/example/stockflow/   # activity, fragment, adapter, API client
│       └── res/                          # layout, drawable, values
├── laravel-api-tugas/            # REST API (Laravel)
│   ├── app/Http/Controllers/     # logika endpoint
│   ├── routes/api.php            # kontrak API (acuan)
│   └── tests/                    # pengujian backend
└── README.md
```

## Prasyarat

| Komponen | Versi |
|---|---|
| PHP + Composer | PHP ≥ 8.3 |
| MySQL | 8.0 (disarankan via Docker) |
| JDK | 17+ |
| Android SDK | compileSdk 37, minSdk 24 (Android 7.0+) |
| Gradle | 9.6 (via wrapper, tidak perlu instal manual) |

## Menjalankan backend

1. **Siapkan database** (contoh Docker):

   ```bash
   docker run -d --name stockflow-mysql -p 3307:3306 \
     -e MYSQL_ROOT_PASSWORD=<root_password> \
     -e MYSQL_DATABASE=stockflow \
     -e MYSQL_USER=stockflow_app \
     -e MYSQL_PASSWORD=<password_app> \
     mysql:8.0
   ```

2. **Konfigurasi dan migrasi**:

   ```bash
   cd laravel-api-tugas
   cp .env.example .env
   # Sesuaikan DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env
   composer install
   php artisan key:generate
   php artisan migrate --seed
   ```

3. **Jalankan server**:

   ```bash
   php artisan serve --host=0.0.0.0 --port=8010
   ```

   > Port **8010** dipakai karena port 8000 pada mesin pengembangan sering sudah terpakai layanan lain (mis. HPLIP printer). Port bebas diganti — yang penting konsisten dengan `BASE_URL` di aplikasi Android.

4. **Verifikasi**:

   ```bash
   php artisan test        # semua pengujian harus hijau
   ```

## Membangun aplikasi Android

1. **Buat `android/local.properties`** (tidak di-commit ke git):

   ```properties
   sdk.dir=/path/ke/Android/Sdk
   ```

2. **Atur alamat API** di `android/app/src/main/java/com/example/stockflow/RetrofitClient.kt`:

   ```kotlin
   private const val BASE_URL = "http://10.0.2.2:8010/api/"   // emulator
   // HP fisik: "http://<IP-LAN-PC>:8010/api/" — HP dan PC harus satu jaringan WiFi
   ```

   Nilai `10.0.2.2` adalah alias localhost komputer *host* khusus emulator Android.

3. **Build APK debug**:

   ```bash
   cd android
   ./gradlew :app:assembleDebug
   ```

   Hasil: `android/app/build/outputs/apk/debug/app-debug.apk`

4. **Pasang ke perangkat**:
   - Salin APK ke HP → izinkan "instal dari sumber tidak dikenal" → install; atau
   - Via USB dengan *USB debugging* aktif: `adb install -r app-debug.apk`.

   > **Catatan tanda tangan**: build debug ditandatangani keystore debug masing-masing komputer. APK dari komputer berbeda (paket sama, tanda tangan beda) **tidak dapat saling menimpa** — uninstall aplikasi lama terlebih dahulu bila berganti sumber APK.

## Akun demo (hasil seeder)

| Username | Kata sandi | Peran |
|---|---|---|
| `pemilik` | `password` | Pemilik gudang — akses penuh (laporan, pengaturan, manajemen akun) |
| `staf` | `password` | Staf gudang — operasional harian (barang, batch, transaksi) |

> Sesi tunggal: login akun yang sama di perangkat lain akan mengakhiri sesi sebelumnya (FR-05).

## API (ringkas)

Base URL: `http://<host>:8010/api` · Autentikasi: header `Authorization: Bearer <token>` (dari `POST /login`).

Format respons seragam (PRD 9.5):

```jsonc
// sukses
{ "success": true, "message": "…", "data": { } }
// gagal validasi (422)
{ "success": false, "message": "Data tidak valid", "errors": { "kolom": ["…"] } }
```

| Kelompok | Endpoint |
|---|---|
| Auth | `POST login` · `POST logout` · `GET me` · `GET tokens` · `DELETE tokens/{id}` · `DELETE tokens/others` |
| Dashboard | `GET dashboard` |
| Barang | `GET/POST products` · `GET/PUT/DELETE products/{id}` · `POST products/bulk-delete` · `GET products/{id}/fefo` · `GET/POST products/{id}/batches` |
| Batch | `GET/PUT/DELETE batches/{id}` · `POST batches/bulk-delete` · `POST batches/{id}/attachments` · `GET attachments/{id}/file` · `DELETE attachments/{id}` |
| Stok | `POST stock-transactions` · `GET stock-transactions` |
| Kedaluwarsa | `GET expiry-monitoring` · `GET expiry-monitoring/perlu-ditangani` · `POST expiry-actions` |
| Notifikasi | `GET notifications` · `PATCH notifications/{id}/read` · `POST notifications/read-all` |
| Impor (FR-15) | `POST imports/products/preview` · `POST imports/products/commit` · `GET imports` · `GET imports/{id}` |
| Laporan (pemilik) | `GET reports/stock` · `GET reports/transactions` · `GET reports/expiry` · `GET reports/fast-moving` · `GET reports/export?jenis=&format=` |
| Pengaturan (pemilik) | `GET/PUT pengaturan` |
| Pengguna (pemilik) | `GET/POST users` · `PUT users/{id}` · `PATCH users/{id}/aktif` · `DELETE users/{id}` |
| Audit | `GET riwayat-aktivitas` |

Detail parameter dan contoh lengkap: lihat [`laravel-api-tugas/README.md`](laravel-api-tugas/README.md).

## Pengujian

**Backend** — `php artisan test`: 43 pengujian / 175 assertion, mencakup autentikasi, validasi, FEFO, BR-01, laporan, impor, pengaturan, dan pengguna.

**Android** — smoke test end-to-end di emulator dengan backend live:

1. Login → beranda terisi data nyata (dashboard).
2. Daftar barang → detail barang → daftar batch FEFO → detail batch.
3. Simpan pengaturan → `PUT` 200, nilai terverifikasi di database.
4. Notifikasi: daftar, filter, tandai semua dibaca (badge → 0).
5. Laporan 4 tab; ekspor xlsx → berkas tersimpan + share sheet sistem.
6. Logout → token dicabut, kembali ke layar login.
7. Penanganan error: 401 → kembali ke login, 422 → pesan validasi per kolom, 429 → throttle.

## Pemecahan masalah

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| "Gagal terhubung ke server" | HP & PC beda jaringan, backend mati, atau `BASE_URL` salah | Pastikan satu WiFi, jalankan `php artisan serve --host=0.0.0.0 --port=8010`, cocokkan IP LAN di `BASE_URL` |
| "App not installed" saat update APK | APK berasal dari komputer lain (tanda tangan berbeda) | Uninstall aplikasi lama, lalu install APK baru |
| Login langsung ditolak / sesi berakhir | Akun yang sama dipakai di perangkat lain (sesi tunggal) | Gunakan akun berbeda atau login ulang |
| "Terlalu banyak percobaan" | Throttle 5 login/menit | Tunggu ± 1 menit |
| Backend jalan tetapi HP tidak bisa mengakses | Firewall PC menutup port | `sudo ufw allow 8010/tcp` (Linux) atau izinkan port tersebut di firewall |
| Build Gradle lambat / gagal karena memori | Daemon Gradle menumpuk | `./gradlew --stop`, tutup proses berat lain |

---

Proyek akademik — *Sistem Pemantauan Stok Kedaluwarsa* (PRD 9.4).
