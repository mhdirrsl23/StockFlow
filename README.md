# Panduan Instalasi dan Penggunaan StockFlow

StockFlow adalah aplikasi Android untuk mengelola data barang dan batch stok. Aplikasi Android terhubung ke REST API Laravel dan database MySQL.

Panduan ini ditujukan kepada penerima source code proyek. Alamat API aplikasi diatur langsung pada `RetrofitClient.kt`, sehingga penerima dapat mengarahkannya ke server Laravel miliknya sendiri. Source code saat ini masih berisi alamat tunnel ngrok milik pembuat sebagai konfigurasi awal.

## 1. Persyaratan

Untuk memakai aplikasi:

- Perangkat Android atau emulator.
- Server API Laravel yang aktif, baik di komputer sendiri maupun server pembuat.

Untuk menyiapkan environment dan membangun APK dari source code:

- PHP 8.3 atau lebih baru dan Composer.
- MySQL 8.
- JDK 17 atau lebih baru.
- Android Studio dan Android SDK yang diperlukan proyek.
- ngrok hanya diperlukan jika API perlu diakses melalui internet dari luar jaringan lokal.

## 2. Menyiapkan Environment Backend

1. Masuk ke folder backend:

   ```bash
   cd laravel-api-tugas
   ```

2. Pasang dependensi Composer:

   ```bash
   composer install
   ```

3. Buat file `.env` dari contoh yang tersedia. Di PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

   Buka `.env` dan atur koneksi database MySQL. `.env.example` menggunakan SQLite secara default, sedangkan migrasi proyek ini membutuhkan MySQL 8. Gunakan konfigurasi seperti berikut:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=stockflow
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. Buat database `stockflow` di MySQL jika belum tersedia:

   ```sql
   CREATE DATABASE stockflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. Siapkan application key, skema, dan data demo:

   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```

   `key:generate` mengisi `APP_KEY` pada `.env`. `migrate` membuat tabel database, sedangkan `--seed` mengisi akun demo beserta data awal. Jalankan perintah ini pada database kosong untuk setup pertama kali.

6. Jalankan server Laravel:

   ```bash
   php artisan serve --host=0.0.0.0 --port=8010
   ```

   Biarkan terminal ini tetap berjalan selama aplikasi menggunakan API.

## 3. Mengatur Alamat API Android

Buka `android/app/src/main/java/com/example/stockflow/RetrofitClient.kt` dan ubah nilai `BASE_URL` agar mengarah ke server Laravel yang disiapkan. URL harus diakhiri `/api/`.

```kotlin
private const val BASE_URL = "http://192.168.1.10:8010/api/"
```

Pilih alamat sesuai cara menjalankan aplikasi:

- Emulator Android: gunakan `http://10.0.2.2:8010/api/`.
- Perangkat Android di jaringan Wi-Fi yang sama: gunakan `http://<IP-komputer-server>:8010/api/`. Jalankan Laravel dengan `--host=0.0.0.0` dan pastikan firewall mengizinkan port `8010`.
- Akses melalui internet/ngrok: jalankan tunnel ke port Laravel dengan perintah berikut, lalu gunakan alamat HTTPS yang diberikan ngrok dan tambahkan `/api/`.

   ```bash
   ngrok http 8010
   ```

> Ngrok hanya meneruskan koneksi ke server Laravel; ngrok tidak menjalankan Laravel atau database. Jika memakai server dan ngrok milik pembuat, keduanya harus aktif agar APK yang masih memakai URL tersebut dapat terhubung. Untuk menjalankan proyek secara mandiri, gunakan server sendiri dan ubah `BASE_URL` seperti di atas.

## 4. Membangun dan Memasang APK

1. Buka proyek `android` melalui Android Studio dan sinkronkan Gradle, atau jalankan build dari terminal:

   ```bash
   cd android
   .\gradlew.bat :app:assembleDebug
   ```

   Pada macOS/Linux, jalankan `./gradlew :app:assembleDebug`.
2. APK hasil build tersedia di `android/app/build/outputs/apk/debug/app-debug.apk`. Pasang APK tersebut pada perangkat Android.

Pastikan `BASE_URL` sudah menunjuk ke API yang aktif sebelum membangun APK. Setiap kali alamat API berubah, ubah nilai tersebut lalu build dan pasang ulang APK.

## 5. Login

Seeder menyediakan akun demo berikut:

| Peran | Username | Kata sandi |
|---|---|---|
| Pemilik | `pemilik` | `password` |
| Staf | `staf` | `password` |

Masukkan username dan kata sandi pada halaman login. Akun demo hanya tersedia jika database telah diisi menggunakan `php artisan migrate --seed`.

## 6. Penggunaan CRUD

### Barang

- **Create:** buka menu **Barang**, pilih tombol tambah, isi SKU, nama, kategori, satuan, dan stok minimum, lalu simpan.
- **Read:** pilih menu **Barang** untuk melihat daftar. Ketuk salah satu barang untuk melihat detailnya dan batch yang terkait.
- **Update:** antarmuka Android saat ini tidak menyediakan aksi ubah data barang.
- **Delete:** buka detail barang, pilih aksi hapus, lalu konfirmasikan. Penghapusan mengikuti aturan aplikasi dan dapat ditolak jika masih ada batch aktif.

### Batch stok

- **Create:** dari detail barang pilih tambah batch; atau gunakan tombol tambah batch dan pilih barang. Isi nomor batch, jumlah awal, dan tanggal kedaluwarsa, lalu simpan.
- **Read:** buka detail barang untuk melihat daftar batch, lalu ketuk batch untuk melihat detail.
- **Update:** pada detail batch buka menu aksi (ikon tiga titik), pilih ubah karakteristik, ubah nomor batch dan/atau tanggal kedaluwarsa, lalu simpan. Jumlah stok tidak diubah dari formulir ini.
- **Delete:** pada detail batch buka menu aksi, pilih hapus batch, lalu konfirmasikan.

## 7. Pemecahan Masalah Koneksi

- Jika login atau pemuatan data gagal, pastikan MySQL dan server Laravel aktif, lalu periksa `BASE_URL` di `RetrofitClient.kt`.
- Jika menggunakan ngrok, pastikan tunnel mengarah ke port Laravel yang benar. Jika alamat tunnel berubah, perbarui `BASE_URL` dan build ulang APK.
- Untuk jaringan pribadi, periksa IP komputer, koneksi Wi-Fi, firewall, port Laravel, dan `BASE_URL`. Jangan gunakan `localhost` atau `127.0.0.1` untuk mengakses komputer server dari perangkat Android fisik.
- Untuk masalah kredensial, pastikan akun demo tersedia melalui proses seeding dan username/kata sandi dimasukkan dengan benar.
