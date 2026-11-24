# Buku Panduan Pengguna StokAman

**StockFlow — Aplikasi Manajemen Stok & Pemantauan Kedaluwarsa**

| Informasi | Keterangan |
|---|---|
| Nama aplikasi | StokAman (StockFlow) |
| Versi panduan | 1.0 |
| Diperbarui | 23 November 2026 |
| Platform | Android |
| Pengguna | Pemilik Gudang dan Staf Gudang |
| Backend | Laravel REST API + MySQL |

Panduan ini menjelaskan cara memakai aplikasi dari sisi **pemilik** dan **staf**, mulai dari masuk, mencatat barang, menerima stok, mengeluarkan stok dengan FEFO, menangani barang kedaluwarsa, sampai membaca laporan.

> **Catatan:** panduan mengikuti perilaku aplikasi versi 1.0. Fitur yang belum aktif pada aplikasi Android ditandai secara jujur di bagian **Catatan Versi** agar tidak menimbulkan kesalahpahaman saat demo atau pelatihan.

---

## Daftar Isi

1. [Tentang Aplikasi](#1-tentang-aplikasi)
2. [Peran dan Hak Akses](#2-peran-dan-hak-akses)
3. [Memulai Aplikasi](#3-memulai-aplikasi)
4. [Mengenal Navigasi](#4-mengenal-navigasi)
5. [Panduan untuk Pemilik](#5-panduan-untuk-pemilik)
6. [Panduan untuk Staf](#6-panduan-untuk-staf)
7. [Alur Kerja Harian](#7-alur-kerja-harian)
8. [Aturan Perhitungan dan Validasi](#8-aturan-perhitungan-dan-validasi)
9. [Penyelesaian Masalah](#9-penyelesaian-masalah)
10. [Pertanyaan yang Sering Diajukan](#10-pertanyaan-yang-sering-diajukan)
11. [Catatan Versi dan Keterbatasan](#11-catatan-versi-dan-keterbatasan)
12. [Lampiran](#12-lampiran)

---

## 1. Tentang Aplikasi

StokAman membantu pengelola gudang mencatat persediaan berdasarkan **batch** dan **tanggal kedaluwarsa**. Aplikasi ini bukan sekadar mencatat jumlah barang, tetapi memastikan barang yang lebih cepat kedaluwarsa dikeluarkan lebih dulu.

### 1.1 Konsep FEFO

**FEFO** (*First Expired First Out*) berarti:

> Batch dengan tanggal kedaluwarsa paling dekat dipakai atau dijual lebih dulu.

Saat staf mencatat stok keluar, aplikasi tidak meminta pemilihan batch. Sistem otomatis memotong batch yang paling dekat kedaluwarsa. Jika jumlah keluar melebihi isi satu batch, sistem melanjutkan ke batch berikutnya. Setiap batch yang terpakai menghasilkan **satu baris mutasi keluar** agar jejak audit tetap jelas.

Contoh:

- Batch A: sisa 50 pcs, kedaluwarsa 29 November 2026.
- Batch B: sisa 70 pcs, kedaluwarsa 30 November 2026.
- Stok keluar 120 pcs.

Hasilnya: Batch A dipotong 50 pcs dan Batch B dipotong 70 pcs. Terbentuk dua baris mutasi keluar, bukan satu.

### 1.2 Status batch

| Status | Arti |
|---|---|
| **Aman** | Sisa hari lebih besar dari ambang peringatan. |
| **Mendekati Kedaluwarsa** | Sisa hari berada di antara 0 sampai ambang peringatan (inklusif). |
| **Kedaluwarsa** | Tanggal kedaluwarsa sudah lewat. |
| **Ditangani** | Batch kedaluwarsa sudah ditindaklanjuti dan sisanya habis. |

Ambang peringatan bawaan adalah **30 hari**, dapat diubah pemilik di menu Pengaturan.

### 1.3 Stok tersedia

Angka **stok tersedia** hanya menjumlahkan sisa batch yang:

- belum kedaluwarsa, dan
- belum berstatus **Ditangani**.

Batch kedaluwarsa tidak dihitung sebagai stok yang boleh dijual. Batch seperti ini harus ditangani lebih dulu.

### 1.4 Stok menipis

Barang berstatus **Stok Menipis** jika stok tersedia **lebih kecil atau sama dengan** stok minimum yang ditentukan saat membuat barang.

Contoh: stok minimum 25 pcs, stok tersedia 20 pcs → berstatus Stok Menipis.

### 1.5 Yang tidak dimiliki aplikasi

- Tidak ada kolom harga, nilai uang, atau laporan penjualan.
- Tidak ada pemindaian barcode.
- Tidak ada registrasi akun mandiri; akun dibuat oleh pemilik.
- Tidak ada pengubahan jumlah stok secara langsung tanpa transaksi.

---

## 2. Peran dan Hak Akses

Aplikasi memiliki dua peran.

| Kemampuan | Pemilik | Staf |
|---|:---:|:---:|
| Melihat beranda dan ringkasan gudang | Ya | Ya |
| Menambah barang | Ya | Ya |
| Mencatat stok masuk / batch baru | Ya | Ya |
| Mencatat stok keluar FEFO | Ya | Ya |
| Menyesuaikan stok batch | Ya | Ya |
| Menangani batch kedaluwarsa | Ya | Ya |
| Melihat notifikasi | Ya | Ya |
| Melihat seluruh riwayat aktivitas | Ya | Tidak |
| Melihat laporan | Ya | Tidak |
| Mengekspor laporan | Ya | Tidak |
| Mengelola akun pengguna | Ya | Tidak |
| Mengubah pengaturan | Ya | Tidak |
| Melihat Data & Skema | Ya | Ya |

### 2.1 Batasan staf

- Staf hanya melihat **riwayat aktivitas miliknya sendiri**.
- Staf hanya melihat **transaksi stok miliknya sendiri** pada data API.
- Staf hanya boleh menghapus barang atau batch yang **ia buat sendiri**; aturan ini berlaku di API. Layar staf saat ini belum menyediakan daftar untuk melakukan penghapusan.
- Menu Laporan, Pengaturan, dan Akun Pengguna tidak muncul untuk staf.

### 2.2 Batasan pemilik

Pemilik memiliki akses penuh, tetapi tetap terikat aturan data:

- Barang yang masih memiliki batch aktif tidak dapat dihapus.
- Jumlah stok tidak bisa diubah langsung; harus lewat stok masuk, stok keluar, atau penyesuaian.
- Batch yang sudah kedaluwarsa tidak dapat diisi ulang; buat batch baru atau tangani batch lama.

---

## 3. Memulai Aplikasi

### 3.1 Prasyarat

Sebelum memakai aplikasi:

1. Backend Laravel dan database MySQL sudah berjalan.
2. Ponsel atau emulator terhubung ke alamat API yang benar.
3. Aplikasi StokAman sudah terpasang.

Untuk emulator Android, alamat API bawaan proyek adalah `http://10.0.2.2:8010/api/`. Untuk ponsel fisik, alamat API harus memakai IP komputer di jaringan yang sama, misalnya `http://192.168.1.10:8010/api/`.

> Port **8000** pada komputer pengembang dipakai layanan printer HPLIP. Karena itu backend proyek ini berjalan di port **8010**. Jangan menukar port tanpa menyesuaikan konfigurasi.

### 3.2 Akun demo

| Peran | Username | Kata sandi | Nama tampilan |
|---|---|---|---|
| Pemilik | `pemilik` | `password` | Budi Santoso |
| Staf | `staf` | `password` | Rina Wulandari |

Akun demo hanya untuk pembelajaran dan demo. Untuk penggunaan nyata, buat akun baru dan gunakan kata sandi yang kuat.

### 3.3 Masuk

1. Buka aplikasi **StokAman**.
2. Pada layar masuk, isi **Username**.
3. Isi **Kata sandi**.
4. Ketuk **Masuk**.
5. Aplikasi membuka beranda sesuai peran:
   - pemilik → Beranda Pemilik,
   - staf → Beranda Staf.

Di bagian bawah layar masuk terdapat keterangan:

> Akun demo: pemilik / staf · kata sandi: password

### 3.4 Sesi tunggal

Satu akun hanya boleh memiliki satu sesi aktif.

- Jika akun yang sama masuk di perangkat lain, token lama otomatis dicabut.
- Perangkat lama akan gagal memuat data dan harus masuk kembali.
- Logout mencabut token perangkat tersebut.

### 3.5 Batas percobaan masuk

Aplikasi membatasi **5 percobaan masuk per menit**. Jika terlalu banyak mencoba, tunggu sekitar satu menit sebelum mencoba lagi.

Pesan yang mungkin muncul:

- **"Username atau password salah."** → periksa ejaan username dan kata sandi.
- **"Akun dinonaktifkan oleh pemilik; hubungi pemilik toko."** → minta pemilik mengaktifkan akun.
- Pesan batas percobaan → tunggu satu menit.

### 3.6 Keluar

1. Buka tab **Profil**.
2. Pilih **Keluar & ganti akun**.
3. Aplikasi kembali ke layar masuk.

---

## 4. Mengenal Navigasi

### 4.1 Menu bawah

| Posisi | Pemilik | Staf |
|---|---|---|
| 1 | Beranda | Beranda |
| 2 | Barang | Barang |
| 3 | Laporan | Riwayat |
| 4 | Profil | Profil |

Menu bawah hanya tampil di halaman utama. Saat membuka detail atau formulir, menu bawah disembunyikan. Gunakan tombol panah kembali di kiri atas.

### 4.2 Elemen umum

- **Lonceng** di kanan atas membuka Notifikasi.
- **Badge angka** pada lonceng Beranda menunjukkan jumlah notifikasi yang belum ditampilkan.
- **Avatar inisial** hanya penanda akun; gunakan tab **Profil** untuk membuka profil.
- **Tanggal** di beranda mengikuti tanggal perangkat.
- **Jam aktivitas** dikonversi dari UTC ke zona waktu perangkat.

### 4.3 Tombol kembali dan batal

- Tombol **panah kiri** kembali ke layar sebelumnya.
- Tombol **Batal** pada formulir membatalkan pengisian tanpa menyimpan.
- Tombol **Simpan** menyimpan data dan biasanya langsung kembali ke layar sebelumnya.

---

## 5. Panduan untuk Pemilik

### 5.1 Beranda Pemilik

Beranda adalah ringkasan kondisi gudang hari ini.

#### a. Sapaan dan tanggal

Bagian atas menampilkan:

- **Halo, [nama pemilik]**
- Ringkasan singkat: *Ringkasan gudang & hal yang perlu ditindak hari ini.*
- Tanggal perangkat, misalnya **Min, 23 Nov**.

#### b. Ringkasan stok

Kartu ringkasan menampilkan indikator utama:

- **Stok tersedia**
- **Barang aktif**
- **Stok menipis**
- **Mendekati kedaluwarsa**
- **Kedaluwarsa**
- **Batch aktif**

Angka di kartu mengikuti data dashboard dari API. Jika database masih kosong, semua angka menunjukkan **0**.

#### c. Kartu peringatan

Ada dua kartu peringatan yang hanya muncul jika ada masalah:

1. **Batch kedaluwarsa perlu ditangani**
   - Menampilkan jumlah batch dan contoh batch pertama: nama barang, nomor batch, sisa, dan satuan.
   - Ketuk kartu untuk membuka **Perlu Ditangani**.

2. **Barang di bawah stok minimum**
   - Menampilkan jumlah barang dan contoh barang yang perlu restok.
   - Ketuk kartu untuk membuka daftar **Barang**.

Jika tidak ada batch kedaluwarsa atau stok menipis, kartu tersebut disembunyikan. Ini bukan error; artinya tidak ada yang perlu ditindak.

#### d. Prioritas FEFO

Bagian **Prioritas FEFO** menampilkan maksimal empat batch yang paling dekat kedaluwarsa, diurutkan dari tanggal terdekat. Setiap baris berisi:

- nama barang,
- nomor batch,
- sisa stok dan satuan,
- status (Aman / Mendekati Kedaluwarsa / Kedaluwarsa),
- keterangan waktu relatif, misalnya **13 hr lagi** atau **2 hr lalu**.

Warna status:

- **Aman** → hijau,
- **Mendekati Kedaluwarsa** → kuning,
- **Kedaluwarsa** → merah.

Jika tidak ada batch layak jual, seluruh bagian FEFO disembunyikan.

#### e. Stok menipis

Bagian ini menampilkan maksimal dua barang dengan stok tersedia di bawah atau sama dengan minimum, misalnya:

> **Sunlight 755ml**  
> 20 / min. 25 pcs  
> Menipis

Jika tidak ada, bagian disembunyikan.

#### f. Aktivitas terbaru

Menampilkan maksimal tiga aktivitas terakhir, misalnya:

> **Stok masuk 20 pcs**  
> batch #1 · Budi Santoso · Tambah data  
> 08.15

Jam mengikuti zona waktu perangkat. Jika belum ada aktivitas, bagian ini disembunyikan.

#### g. Aksi cepat

Empat tombol aksi cepat:

| Tombol | Fungsi |
|---|---|
| **Stok masuk** | Membuka formulir batch baru. |
| **Stok keluar** | Membuka formulir stok keluar FEFO. |
| **Tangani** | Membuka daftar batch kedaluwarsa. |
| **Tambah** | Membuka formulir barang baru. |

### 5.2 Menu Barang

Menu **Barang** adalah pusat master data dan batch.

#### a. Mencari barang

1. Buka menu **Barang**.
2. Ketuk kolom pencarian **Cari nama, SKU, atau kategori**.
3. Ketik kata kunci.
4. Daftar langsung menyaring sesuai ketikan.

Pencarian mencakup nama barang, kode SKU, dan kategori. Hapus kata kunci untuk menampilkan semua barang.

#### b. Menyaring kategori

Tersedia chip kategori:

- **Semua**
- **Makanan**
- **Minuman**
- **Obat**
- **Kebutuhan Rumah**
- **Lainnya**

Ketuk satu chip untuk menyaring. Pilih **Semua** untuk kembali menampilkan seluruh kategori.

#### c. Membaca kartu barang

Setiap kartu menampilkan:

- nama barang,
- kode SKU,
- kategori,
- total stok tersedia dan satuan,
- status stok (misalnya **Aman** atau **Stok Menipis**).

Ketuk kartu untuk membuka **Detail Barang**.

#### d. Menambah barang baru

1. Dari **Beranda**, ketuk **Tambah**, atau dari **Barang** ketuk tombol **+** di kanan atas.
2. Isi formulir **Tambah barang**:

| Kolom | Wajib | Aturan |
|---|:---:|---|
| Kode SKU | Ya | Maksimal 50 karakter; unik di antara barang aktif. |
| Nama barang | Ya | Maksimal 150 karakter. |
| Kategori | Ya | Makanan, Minuman, Obat, Kebutuhan Rumah, atau Lainnya. |
| Satuan | Ya | Maksimal 30 karakter, misalnya pcs, box, pack. |
| Stok minimum | Ya | Bilangan 0–999.999. |

3. Ketuk **Simpan barang**.
4. Jika berhasil, muncul pesan **"Barang berhasil disimpan"** dan aplikasi kembali.

> Menambah barang **tidak menambah stok**. Stok baru bertambah setelah batch dicatat. Barang baru akan tampil dengan stok 0 dan status Stok Menipis sampai ada batch masuk.

#### e. Detail barang

Halaman Detail Barang menampilkan:

- nama barang, SKU, kategori, dan satuan;
- stok tersedia dan stok minimum;
- status stok;
- daftar batch stok diurutkan FEFO;
- tombol **Tambah** untuk batch baru;
- tombol **Stok keluar**;
- tombol **⋮** di kanan atas untuk menghapus barang.

Ketuk salah satu batch untuk membuka **Detail Batch**.

#### f. Menghapus barang

1. Buka Detail Barang.
2. Ketuk ikon **⋮** di kanan atas.
3. Konfirmasi **Hapus**.

Aturan:

- Barang yang masih memiliki batch aktif **tidak dapat dihapus**. Muncul pesan agar menghapus batch terlebih dahulu.
- Penghapusan bersifat **soft delete**: data tidak benar-benar hilang dari database dan riwayat tetap tersimpan.
- Staf hanya dapat menghapus barang yang ia buat sendiri; pemilik dapat menghapus semua barang.

### 5.3 Mencatat Batch Baru (Stok Masuk)

Batch adalah wadah stok dengan tanggal kedaluwarsa sendiri. Satu barang boleh memiliki banyak batch.

#### a. Membuka formulir

Ada tiga cara:

- Beranda → **Stok masuk**.
- Barang → Detail Barang → **Tambah batch**.
- Detail Barang → **Tambah**.

#### b. Mengisi formulir

| Kolom | Wajib | Aturan |
|---|:---:|---|
| Barang | Ya | Pilih dari daftar barang aktif. Jika dibuka dari Detail Barang, barang sudah terpilih. |
| Nomor batch | Ya | Maksimal 100 karakter; unik per barang. |
| Jumlah awal | Ya | Bilangan 1–999.999. Gunakan tombol **−** dan **+**. |
| Tanggal kedaluwarsa | Ya | Tidak boleh sebelum hari ini. |
| Lampiran bukti | Tidak | JPG/JPEG/PNG, ukuran mengikuti Pengaturan (bawaan 2 MB). |

#### c. Menyimpan

1. Ketuk **Simpan batch**.
2. Sistem mencatat:
   - batch baru,
   - mutasi **masuk** sebesar jumlah awal,
   - riwayat aktivitas **tambah batch_stok**.
3. Jika memilih foto, aplikasi mengunggah foto setelah batch berhasil dibuat.

> Jika unggah foto gagal, batch tetap tersimpan. Buka Detail Batch dan tambahkan foto lagi.

#### d. Aturan nomor batch

- Nomor batch harus unik **di antara batch aktif pada barang yang sama**.
- Barang berbeda boleh memakai nomor batch yang sama.
- Nomor batch yang sudah dihapus (soft delete) tidak menghalangi pembuatan nomor yang sama.

### 5.4 Stok Keluar FEFO

#### a. Membuka formulir

- Beranda → **Stok keluar**.
- Detail Barang → **Stok keluar**.

#### b. Mengisi

1. Pilih **Barang** jika belum terpilih otomatis.
2. Tentukan **Jumlah keluar** dengan tombol **−** / **+**.
3. Isi **Catatan** bila perlu, misalnya *Penjualan harian*.
4. Ketuk **Catat keluar**.

#### c. Yang terjadi setelah disimpan

- Sistem memotong batch dengan tanggal kedaluwarsa terdekat lebih dulu.
- Jika satu batch tidak cukup, sistem melanjutkan ke batch berikutnya.
- Setiap batch yang terpakai menghasilkan satu baris mutasi **keluar**.
- Sistem mencatat satu riwayat aktivitas **pengeluaran_stok**.
- Jika stok menjadi di bawah minimum, status Stok Menipis diperbarui.

#### d. Jika stok tidak cukup

Muncul pesan seperti:

> **Stok layak jual tidak mencukupi: butuh 120, tersedia 90.**

Periksa:

- stok tersedia di Detail Barang,
- batch yang sudah kedaluwarsa (tidak dihitung),
- batch yang sudah ditangani (tidak dihitung).

Tambahkan stok masuk atau kurangi jumlah keluar.

### 5.5 Detail Batch

Halaman Detail Batch menampilkan:

- status batch;
- nama barang dan nomor batch;
- sisa / jumlah awal;
- tanggal kedaluwarsa dan keterangan relatif;
- tombol **Sesuaikan** dan **Tangani sekarang**;
- bagian lampiran foto;
- menu **⋮** untuk aksi batch.

> Beberapa panel ringkasan dan daftar mutasi di bagian bawah layar masih berupa tampilan referensi dan belum diisi ulang otomatis. Untuk melihat seluruh mutasi, pemilik dapat membuka **Laporan → Mutasi**.

#### a. Menyesuaikan stok

Gunakan untuk koreksi hasil hitung fisik (stock opname).

1. Buka Detail Batch.
2. Ketuk **Sesuaikan**.
3. Isi **Selisih jumlah**:
   - positif untuk menambah,
   - negatif untuk mengurangi.
4. Perhatikan **Sisa setelah penyesuaian**.
5. Isi **Alasan penyesuaian** (wajib), misalnya *Koreksi stok opname mingguan*.
6. Ketuk **Simpan selisih**.

Aturan:

- Selisih tidak boleh 0.
- Sisa akhir tidak boleh kurang dari 0.
- Sisa akhir tidak boleh melebihi jumlah awal batch.
- Penyesuaian dicatat sebagai mutasi **penyesuaian** dengan nilai selisih dan alasan.

#### b. Menangani batch kedaluwarsa

1. Buka Detail Batch yang berstatus **Kedaluwarsa**.
2. Ketuk **Tangani sekarang**.
3. Pilih tindakan:
   - **Dimusnahkan**,
   - **Dikembalikan ke Pemasok**,
   - **Lainnya**.
4. Sistem mencatat mutasi **penanganan** dan riwayat aktivitas.

Setelah seluruh sisa ditangani:

- status batch menjadi **Ditangani**,
- batch hilang dari daftar **Perlu Ditangani**,
- batch tidak lagi dihitung sebagai stok tersedia,
- kartu peringatan di beranda berkurang atau hilang.

> Aplikasi menangani **seluruh sisa batch** pada satu tindakan. Jika ingin menangani sebagian, gunakan API atau minta pengembang menambahkan input jumlah.

#### c. Menu ⋮ batch

Ketuk ikon **⋮** di kanan atas Detail Batch. Tersedia tiga aksi:

1. **Tambah stok ke batch (masuk)**
   - Masukkan jumlah masuk.
   - Batch harus belum kedaluwarsa dan belum ditangani.
   - Jumlah awal dan sisa bertambah.
   - Tercatat sebagai mutasi masuk.

2. **Ubah karakteristik batch (BR-01)**
   - Hanya nomor batch dan tanggal kedaluwarsa yang dapat diubah.
   - Tanggal kedaluwarsa tidak boleh sebelum tanggal masuk pertama.
   - Nomor batch harus unik per barang.

3. **Hapus batch**
   - Soft delete; riwayat mutasi tetap tersimpan.
   - Staf hanya dapat menghapus batch yang ia buat sendiri.

#### d. Menambah foto bukti

1. Buka Detail Batch.
2. Ketuk **Tambah foto**.
3. Pilih foto dari galeri.
4. Aplikasi mengunggah foto sebagai lampiran **bukti_batch**.

Aturan:

- Format JPG, JPEG, atau PNG.
- Ukuran maksimum mengikuti Pengaturan (bawaan 2 MB).
- Jika ukuran melebihi batas, muncul pesan kesalahan; perkecil foto atau naikkan batas di Pengaturan.

### 5.6 Perlu Ditangani

Menu ini khusus menampilkan batch **Kedaluwarsa** yang belum ditangani.

1. Dari Beranda, ketuk kartu **Batch kedaluwarsa** atau aksi cepat **Tangani**.
2. Daftar batch tampil urut FEFO.
3. Ketuk batch untuk membuka Detail Batch.
4. Ketuk **Tangani sekarang** dan pilih tindakan.

Jika tidak ada batch kedaluwarsa, muncul keadaan kosong:

> **Aman, tidak ada batch kedaluwarsa**  
> Semua stok batch dalam kondisi aman atau sudah ditangani.

### 5.7 Notifikasi

#### a. Membuka

Ketuk ikon **lonceng** di beranda, Barang, Laporan, atau Riwayat.

#### b. Isi layar

- jumlah **belum dibaca**,
- filter **Semua**, **Mendekati**, **Kedaluwarsa**, **Stok Menipis**,
- daftar notifikasi.

#### c. Tindakan

- Ketuk notifikasi untuk membuka Detail Batch atau Detail Barang terkait.
- Ketuk ikon **centang** di kanan atas untuk **menandai semua notifikasi sudah dibaca**.

#### d. Kapan notifikasi dibuat

Notifikasi dibuat oleh penjadwal Laravel pada:

- **06.00 WIB**, dan
- **18.00 WIB**.

Aplikasi Android memuat notifikasi saat layar **Notifikasi** dibuka. Sistem mencegah duplikasi: satu baris per jenis, per barang/batch, per hari.

Jika notifikasi belum muncul:

- pastikan backend dan scheduler berjalan,
- jalankan scheduler manual bila sedang demo,
- tarik data ulang dengan membuka layar Notifikasi.

### 5.8 Laporan

Menu **Laporan** hanya tersedia untuk pemilik.

#### a. Tab laporan

| Tab | Isi |
|---|---|
| **Stok** | Ringkasan barang aktif, unit tersedia, stok menipis, dan daftar stok per barang. |
| **Mutasi** | Daftar transaksi stok masuk, keluar, penyesuaian, dan penanganan. |
| **Kedaluwarsa** | Daftar batch beserta status dan status penanganan. |
| **Aktivitas** | Audit log seluruh pengguna (pemilik melihat semua). |

#### b. Kartu statistik tab Stok

- **Barang aktif**
- **Unit tersedia**
- **Stok menipis**

> Total unit menjumlahkan satuan yang berbeda (pcs, box, pack). Angka ini bukan nilai uang. Skema tidak memiliki kolom harga.

#### c. Ekspor laporan

1. Buka tab yang ingin diekspor: **Stok**, **Mutasi**, atau **Kedaluwarsa**.
2. Ketuk tombol **CSV** di kanan atas.
3. Aplikasi mengunduh berkas **.xlsx** dan membuka menu berbagi.
4. Pilih aplikasi tujuan, misalnya WhatsApp, email, atau penyimpanan berkas.

Catatan:

- Tombol berlabel **CSV**, tetapi berkas yang dihasilkan adalah **Excel (.xlsx)**.
- Tab **Aktivitas** tidak memiliki ekspor.
- Ekspor khusus pemilik. Staf tidak memiliki menu Laporan.

### 5.9 Profil Pemilik

Layar Profil menampilkan:

- nama dan username,
- peran **Pemilik Gudang**,
- badge **Aktif**,
- menu **Akun pengguna** (khusus pemilik),
- menu **Pengaturan** (khusus pemilik),
- kartu informasi **Notifikasi** (buka lewat ikon lonceng),
- menu **Data & skema**,
- tombol **Keluar & ganti akun**.

#### a. Mengelola akun pengguna

1. Profil → **Akun pengguna**.
2. Layar menampilkan jumlah total akun, akun aktif, dan akun staf.
3. Daftar akun menampilkan nama, `@username`, dan peran.
4. Ketuk akun untuk mengubah status:
   - **Aktif → Nonaktif**: akun tidak bisa masuk dan seluruh tokennya dicabut.
   - **Nonaktif → Aktif**: akun bisa masuk lagi.
5. Konfirmasi perubahan.

Aturan tambahan:

- Pemilik tidak dapat menonaktifkan akun yang sedang ia pakai sendiri.
- Sistem menolak menonaktifkan pemilik terakhir yang masih aktif; minimal satu akun pemilik harus tetap aktif.

#### b. Menambah akun

1. Pada layar **Akun pengguna**, ketuk tombol **+**.
2. Isi:
   - **Nama lengkap** (wajib),
   - **Username** (wajib, unik),
   - **Kata sandi** (wajib, minimal **8 karakter**).
3. Ketuk **Buat akun**.

Ketentuan penting:

- Akun baru selalu dibuat sebagai **staf** dan langsung **aktif**.
- Pilihan peran dan sakelar aktif pada formulir belum diterapkan oleh backend. Jangan mengandalkan formulir tersebut untuk membuat akun pemilik.
- Tidak ada registrasi mandiri.
- Username harus unik.
- Kata sandi disimpan sebagai hash bcrypt.

#### c. Mengubah pengaturan

1. Profil → **Pengaturan**.
2. Ubah nilai yang diinginkan:

| Pengaturan | Bawaan | Rentang | Fungsi |
|---|---:|---:|---|
| **Ambang peringatan** | 30 hari | 1–365 | Batch dengan sisa hari ≤ nilai ini berstatus Mendekati Kedaluwarsa. |
| **Ukuran maksimum lampiran** | 2 MB | 1–20 MB | Batas unggah foto bukti. |
| **Maksimum baris impor** | 1000 | 10–10.000 | Batas impor di backend. |

3. Ketuk **Simpan pengaturan**.

Catatan:

- Ambang bersifat **inklusif**. Contoh: sisa 30 hari dengan ambang 30 → **Mendekati Kedaluwarsa**, bukan Aman.
- Perubahan ambang dipakai pada pemantauan berikutnya (jadwal 06.00/18.00 WIB) atau saat `php artisan stockflow:monitor` dijalankan manual. Setelah itu status batch dihitung ulang.
- Setiap perubahan dicatat sebagai **ubah_pengaturan** pada riwayat aktivitas.
- Hanya pemilik yang dapat mengubah pengaturan.

#### d. Data & Skema

Menu ini menampilkan informasi teknis:

- daftar tabel dan view,
- kolom kunci,
- aturan stok, status, FEFO, dan soft delete,
- keterangan bahwa skema tidak memiliki harga.

Layar ini bersifat **referensi baca**. Tombol unduh/reset pada layout bukan bagian dari alur operasional harian.

### 5.10 Keluar

1. Profil → **Keluar & ganti akun**.
2. Aplikasi memanggil API logout, menghapus sesi lokal, dan membuka layar masuk.

Jika jaringan bermasalah, aplikasi tetap menghapus sesi lokal agar pengguna tidak terjebak.

---

## 6. Panduan untuk Staf

Staf berfokus pada pekerjaan operasional: menerima stok, mengeluarkan stok, menangani batch kedaluwarsa, dan melihat riwayat sendiri.

### 6.1 Beranda Staf

Beranda staf menampilkan:

- **Halo, [nama staf]**
- *Tugas stok yang perlu diselesaikan hari ini.*
- tanggal perangkat,
- kartu **Tugas hari ini**:
  - batch kedaluwarsa,
  - mendekati kedaluwarsa,
  - stok menipis,
  - notifikasi baru,
- kartu peringatan kedaluwarsa dan stok menipis (muncul hanya jika ada data),
- ringkasan **Stok menipis**, **Mendekati kedaluwarsa**, **Kedaluwarsa**, **Batch aktif**,
- **Prioritas FEFO**,
- **Stok menipis**,
- **Aktivitas terbaru**,
- **Aksi cepat**.

#### Aksi cepat staf

| Tombol | Fungsi |
|---|---|
| **Stok masuk** | Membuka formulir batch baru. |
| **Stok keluar** | Membuka formulir stok keluar FEFO. |
| **Tangani** | Membuka daftar batch kedaluwarsa. |
| **Tambah** | Membuka formulir barang baru. |

Gunakan aksi cepat ini sebagai pintu utama pekerjaan staf.

### 6.2 Menu Barang Staf

Layar **Barang** staf menampilkan kerangka master barang:

- tab **Barang** dan **Batch**,
- kolom pencarian,
- chip kategori,
- banner stok menipis,
- tombol **+**.

**Keterbatasan versi 1.0:** daftar, pencarian, filter, dan tombol tambah pada layar ini belum terhubung ke API. Karena itu layar dapat menampilkan **"Hasil tidak ditemukan"** walaupun database berisi barang.

Untuk sementara, gunakan:

- **Aksi cepat Beranda** untuk menambah barang, stok masuk, stok keluar, dan menangani batch.
- **Notifikasi** atau **Perlu Ditangani** untuk membuka Detail Batch.
- **Detail Barang** yang terbuka dari notifikasi untuk melihat stok dan batch.

Fitur ini perlu dihubungkan ke API pada pembaruan berikutnya. Lihat bagian **Catatan Versi**.

### 6.3 Riwayat Staf

Menu **Riwayat** menampilkan audit aktivitas milik akun staf yang sedang masuk.

#### a. Isi

- nama aktivitas, misalnya **LOGIN**,
- keterangan, misalnya *Login staf (staf)*,
- waktu aktivitas.

#### b. Chip kategori

Layar menampilkan chip **Semua**, **Login**, **Tambah data**, dan **Pengeluaran stok**. Pada versi ini daftar menampilkan seluruh riwayat akun; chip belum menyaring daftar.

#### c. Aturan

- Staf hanya melihat aktivitasnya sendiri.
- Riwayat bersifat **immutable**; tidak dapat diubah atau dihapus dari aplikasi.
- Pemilik dapat melihat seluruh riwayat melalui **Laporan → Aktivitas**.

Jika belum ada aktivitas, muncul:

> **Tidak ada riwayat aktivitas**  
> Aktivitas akun Anda akan tercatat di sini.

### 6.4 Profil Staf

Layar Profil staf menampilkan:

- nama dan username,
- peran **Staf Gudang**,
- badge **Aktif**,
- kartu informasi **Notifikasi** (buka lewat ikon lonceng),
- menu **Data & skema**,
- tombol **Keluar & ganti akun**.

Menu **Akun pengguna** dan **Pengaturan** tidak muncul untuk staf.

> Angka ringkasan pada kartu Profil masih memakai angka contoh dari layout. Untuk angka operasional yang benar, gunakan Beranda dan Laporan (pemilik).

### 6.5 Yang tidak dapat dilakukan staf

- Membuka Laporan dan mengekspor berkas.
- Mengubah Pengaturan.
- Mengelola akun pengguna.
- Melihat riwayat atau transaksi pengguna lain.
- Mengubah atau menghapus barang/batch milik pengguna lain.

Jika staf membutuhkan salah satu hal tersebut, hubungi pemilik.

---

## 7. Alur Kerja Harian

### 7.1 Alur menerima barang baru

1. Pemilik/staf membuat **barang** (jika belum ada).
2. Buka **Stok masuk**.
3. Pilih barang.
4. Isi nomor batch, jumlah awal, dan tanggal kedaluwarsa.
5. Lampirkan foto bukti bila perlu.
6. Simpan.

Hasil: batch baru, mutasi masuk, dan riwayat aktivitas tercatat.

### 7.2 Alur mengeluarkan barang

1. Buka **Stok keluar**.
2. Pilih barang.
3. Tentukan jumlah.
4. Isi catatan bila perlu.
5. Catat keluar.

Hasil: stok dipotong otomatis secara FEFO. Jika satu batch tidak cukup, beberapa batch terpotong dan setiap batch menghasilkan satu baris mutasi.

### 7.3 Alur menangani barang kedaluwarsa

1. Periksa kartu peringatan di Beranda atau buka **Perlu Ditangani**.
2. Pilih batch.
3. Ketuk **Tangani sekarang**.
4. Pilih **Dimusnahkan**, **Dikembalikan ke Pemasok**, atau **Lainnya**.
5. Konfirmasi.

Hasil: batch berstatus **Ditangani**, tidak dihitung sebagai stok tersedia, dan hilang dari daftar perlu ditangani.

### 7.4 Alur stock opname

1. Hitung fisik barang.
2. Buka Detail Batch.
3. Ketuk **Sesuaikan**.
4. Masukkan selisih (positif/negatif).
5. Isi alasan.
6. Simpan.

Hasil: saldo batch disesuaikan dan tercatat sebagai mutasi penyesuaian dengan alasan.

### 7.5 Alur memantau gudang

**Setiap pagi:**

1. Buka Beranda.
2. Periksa kartu kedaluwarsa dan stok menipis.
3. Buka **Prioritas FEFO** untuk melihat batch terdekat.
4. Tangani batch kedaluwarsa.
5. Catat stok masuk untuk barang yang menipis.

**Setiap sore:**

1. Periksa notifikasi.
2. Catat stok keluar hari ini.
3. Pastikan tidak ada batch kedaluwarsa yang tertinggal.
4. Pemilik memeriksa Laporan → Stok/Mutasi bila perlu.

---

## 8. Aturan Perhitungan dan Validasi

### 8.1 Status dan stok

| Aturan | Rumus / Ketentuan |
|---|---|
| Sisa hari | `tanggal_kedaluwarsa − hari ini` |
| Aman | `sisa_hari > ambang` |
| Mendekati Kedaluwarsa | `0 ≤ sisa_hari ≤ ambang` |
| Kedaluwarsa | `sisa_hari < 0` |
| Ditangani | Ditandai manual setelah sisa batch habis |
| Stok tersedia | Σ `jumlah_sisa` batch dengan `tanggal_kedaluwarsa ≥ hari ini` dan status ≠ Ditangani |
| Stok menipis | `stok tersedia ≤ stok_minimum` |
| FEFO | Urut `tanggal_kedaluwarsa` paling awal |

### 8.2 Validasi formulir

| Formulir | Aturan penting |
|---|---|
| Barang | SKU wajib, unik di antara barang aktif, maks. 50 karakter; nama maks. 150; satuan maks. 30; stok minimum 0–999.999. |
| Batch | Nomor batch wajib, unik per barang, maks. 100; jumlah awal 1–999.999; tanggal kedaluwarsa ≥ hari ini. |
| Stok keluar | Jumlah ≥ 1; tidak boleh melebihi stok layak jual. |
| Penyesuaian | Selisih ≠ 0; alasan wajib; sisa akhir 0–jumlah awal. |
| Akun | Nama wajib; username unik; kata sandi minimal 8 karakter. |
| Pengaturan | Ambang 1–365 hari; lampiran 1–20 MB; baris impor 10–10.000. |
| Lampiran | JPG/JPEG/PNG; ukuran mengikuti pengaturan. |

### 8.3 Audit dan penghapusan

- Setiap transaksi dan perubahan penting dicatat pada **riwayat aktivitas**.
- Riwayat aktivitas bersifat **immutable**.
- Penghapusan barang dan batch memakai **soft delete** (`deleted_at` dan `deleted_by`).
- SKU dan nomor batch hanya unik di antara data aktif.
- Notifikasi terkait barang/batch yang dihapus ikut dihapus.

### 8.4 Zona waktu

- Backend memakai **Asia/Jakarta (WIB)** untuk `NOW()` dan `CURDATE()`.
- Aplikasi menampilkan tanggal header sesuai tanggal perangkat.
- Jam aktivitas dikonversi dari UTC ke zona waktu perangkat.
- Notifikasi terjadwal pukul **06.00** dan **18.00 WIB**.

### 8.5 Satuan dan angka

- Total unit pada laporan menjumlahkan satuan berbeda; angka ini bukan nilai uang.
- Tidak ada konversi otomatis antar-satuan (pcs, box, pack).
- Tidak ada kolom harga pada skema.

---

## 9. Penyelesaian Masalah

| Gejala | Kemungkinan penyebab | Solusi |
|---|---|---|
| Tidak bisa masuk, pesan username/password salah | Salah ketik atau akun belum ada | Periksa username dan kata sandi; gunakan akun demo yang benar. |
| Login ditolak karena akun nonaktif | Pemilik menonaktifkan akun | Minta pemilik mengaktifkan akun di Profil → Akun pengguna. |
| Muncul pesan batas percobaan | Lebih dari 5 percobaan dalam 1 menit | Tunggu sekitar satu menit, lalu coba lagi. |
| Sesi tiba-tiba berakhir | Akun yang sama masuk di perangkat lain | Masuk kembali; sesi lama memang dicabut. |
| Beranda menampilkan 0 semua | Database kosong atau data belum dimuat | Tambah barang dan batch; tarik ulang dengan membuka ulang layar. |
| Kartu peringatan tidak muncul | Tidak ada batch kedaluwarsa/stok menipis | Bukan error; memang tidak ada yang perlu ditindak. |
| Stok keluar gagal, "stok layak jual tidak mencukupi" | Stok tersedia kurang; batch kedaluwarsa tidak dihitung | Kurangi jumlah, tambah stok masuk, atau tangani batch kedaluwarsa. |
| Batch tidak bisa diisi stok masuk | Batch sudah kedaluwarsa atau ditangani | Buat batch baru atau tangani batch lama. |
| Tanggal kedaluwarsa ditolak | Tanggal sebelum hari ini | Pilih tanggal hari ini atau setelahnya. |
| Nomor batch ditolak | Nomor sudah dipakai pada barang yang sama | Gunakan nomor lain. |
| Barang tidak bisa dihapus | Masih memiliki batch aktif | Hapus batch terlebih dahulu, lalu hapus barang. |
| Penyesuaian ditolak | Sisa akhir < 0 atau > jumlah awal | Periksa selisih dan saldo batch. |
| Foto gagal diunggah | Format bukan JPG/PNG atau ukuran melebihi batas | Perkecil foto atau naikkan batas lampiran di Pengaturan. |
| Laporan tidak bisa diekspor | Tab Aktivitas tidak mendukung ekspor | Pilih tab Stok, Mutasi, atau Kedaluwarsa. |
| Menu Laporan/Pengaturan tidak ada | Akun yang dipakai adalah staf | Masuk dengan akun pemilik. |
| Layar Barang staf selalu "Hasil tidak ditemukan" | Fitur daftar staf belum terhubung ke API | Gunakan aksi cepat Beranda; lihat Catatan Versi. |
| Notifikasi tidak muncul | Scheduler belum berjalan atau belum ada peringatan | Pastikan scheduler aktif; buka layar Notifikasi untuk memuat ulang. |
| Angka di Profil tidak berubah | Kartu profil masih memakai angka contoh layout | Gunakan Beranda dan Laporan sebagai acuan. |

---

## 10. Pertanyaan yang Sering Diajukan

**Apakah menambah barang otomatis menambah stok?**  
Tidak. Barang adalah master data. Stok bertambah setelah batch dicatat melalui **Stok masuk** atau **Tambah batch**.

**Mengapa stok barang baru 0?**  
Karena belum ada batch. Tambahkan batch dengan jumlah awal dan tanggal kedaluwarsa.

**Bisakah mengubah jumlah stok secara langsung?**  
Tidak. Gunakan stok masuk, stok keluar, atau penyesuaian. Semua perubahan tercatat sebagai mutasi.

**Apa bedanya stok keluar dan penyesuaian?**  
Stok keluar untuk pengeluaran normal dan otomatis FEFO. Penyesuaian untuk koreksi selisih hitung fisik dan wajib menyertakan alasan.

**Mengapa batch kedaluwarsa tidak bisa dijual?**  
Karena batch kedaluwarsa tidak dihitung sebagai stok tersedia. Tangani batch tersebut lebih dulu.

**Apakah data yang dihapus hilang permanen?**  
Tidak. Barang dan batch memakai soft delete; riwayat dan mutasi tetap tersimpan untuk audit.

**Apakah staf bisa melihat laporan?**  
Tidak. Laporan dan ekspor khusus pemilik. Staf hanya melihat riwayat aktivitasnya sendiri.

**Apakah staf bisa membuat akun?**  
Tidak. Akun dibuat oleh pemilik melalui Profil → Akun pengguna.

**Apakah bisa membuat akun pemilik baru dari aplikasi?**  
Belum. Formulir akun selalu menghasilkan akun staf. Akun pemilik tambahan harus dibuat melalui backend/seeder.

**Apakah aplikasi menyimpan harga barang?**  
Tidak. Skema hanya mencatat kuantitas, satuan, batch, mutasi, dan kedaluwarsa.

**Kapan notifikasi dibuat?**  
Setiap pukul 06.00 dan 18.00 WIB. Aplikasi memuatnya saat layar Notifikasi dibuka.

**Mengapa jam aktivitas berbeda dari jam server?**  
Aplikasi mengonversi waktu UTC ke zona waktu perangkat. Pastikan zona waktu ponsel benar.

**Apakah bisa memakai aplikasi tanpa internet?**  
Tidak untuk data utama. Aplikasi membutuhkan koneksi ke backend Laravel.

**Bagaimana mengganti kata sandi?**  
Belum tersedia di aplikasi Android versi ini. Perubahan kata sandi dilakukan melalui backend atau oleh pengembang.

---

## 11. Catatan Versi dan Keterbatasan

Bagian ini penting untuk demo, presentasi, atau pelatihan agar ekspektasi sesuai dengan kondisi aplikasi.

### 11.1 Layar Barang staf belum terhubung penuh

Pada versi 1.0:

- Layar **Barang** staf menampilkan kerangka layout (tab, pencarian, chip, banner, tombol +).
- Daftar barang, pencarian, filter kategori, dan tombol tambah **belum terhubung ke API**.
- Akibatnya layar dapat menampilkan **"Hasil tidak ditemukan"** meskipun data ada.

Pekerjaan staf tetap dapat dilakukan melalui **aksi cepat Beranda** dan **Perlu Ditangani**.

### 11.2 Angka ringkasan Profil

Kartu **aktivitas saya**, **batch saya**, dan **akun pengguna** pada Profil masih memakai angka contoh dari layout. Angka operasional yang benar ada di Beranda dan Laporan (pemilik).

### 11.3 Formulir akun

- Petunjuk pada kolom kata sandi menulis "Minimal 6 karakter", tetapi backend mewajibkan **minimal 8 karakter**. Gunakan minimal 8 karakter.
- Pilihan peran dan sakelar aktif pada formulir belum diterapkan; akun baru selalu **staf** dan **aktif**.

### 11.4 Impor Excel

Backend memiliki endpoint impor produk, tetapi aplikasi Android belum menyediakan layar impor. Pengaturan **maksimum baris impor** tetap tersedia untuk kebutuhan backend.

### 11.5 Tombol ekspor

Tombol berlabel **CSV** menghasilkan berkas **.xlsx**. Ini bukan kesalahan data; hanya label tombol yang belum disesuaikan.

### 11.6 Data & Skema

Layar Data & Skema berfungsi sebagai referensi. Tombol unduh JSON/reset data yang terlihat pada layout bukan bagian dari alur operasional Android saat ini.

### 11.7 Keterbatasan lain

- Tidak ada harga, barcode, atau laporan keuangan.
- Tidak ada registrasi mandiri.
- Tidak ada penggantian kata sandi mandiri di aplikasi.
- Penanganan kedaluwarsa dari aplikasi menangani seluruh sisa batch, bukan sebagian.

---

## 12. Lampiran

### 12.1 Akun demo

| Peran | Username | Kata sandi |
|---|---|---|
| Pemilik | `pemilik` | `password` |
| Staf | `staf` | `password` |

### 12.2 Istilah

| Istilah | Arti |
|---|---|
| **Barang** | Master data produk, misalnya nama, SKU, kategori, satuan, stok minimum. |
| **Batch** | Kelompok stok dengan nomor batch dan tanggal kedaluwarsa sendiri. |
| **Stok tersedia** | Total sisa batch yang belum kedaluwarsa dan belum ditangani. |
| **FEFO** | First Expired First Out; batch terdekat kedaluwarsa dipakai lebih dulu. |
| **Stok menipis** | Stok tersedia ≤ stok minimum. |
| **Mendekati kedaluwarsa** | Sisa hari 0 sampai ambang peringatan. |
| **Kedaluwarsa** | Tanggal kedaluwarsa sudah lewat. |
| **Ditangani** | Batch kedaluwarsa sudah ditindaklanjuti dan sisanya habis. |
| **Mutasi** | Catatan transaksi masuk, keluar, penyesuaian, atau penanganan. |
| **Soft delete** | Penghapusan logis; data tetap tersimpan untuk audit. |
| **Audit log** | Riwayat aktivitas pengguna yang tidak dapat diubah. |

### 12.3 Checklist demo pemilik

- [ ] Masuk dengan akun `pemilik`.
- [ ] Tunjukkan beranda kosong: semua angka 0, tanpa kartu peringatan.
- [ ] Tambah barang baru.
- [ ] Tambah batch dengan tanggal kedaluwarsa.
- [ ] Tunjukkan FEFO, stok menipis, dan aktivitas terisi.
- [ ] Catat stok keluar dan tunjukkan pemotongan FEFO.
- [ ] Ubah tanggal batch menjadi kedaluwarsa dan tunjukkan kartu peringatan.
- [ ] Tangani batch dan tunjukkan status berubah.
- [ ] Buka Laporan dan ekspor berkas.
- [ ] Buka Pengaturan dan ubah ambang.

### 12.4 Checklist demo staf

- [ ] Masuk dengan akun `staf`.
- [ ] Tunjukkan beranda dan tugas hari ini.
- [ ] Gunakan aksi cepat **Stok masuk**.
- [ ] Gunakan aksi cepat **Stok keluar**.
- [ ] Buka **Tangani** dan tangani batch kedaluwarsa.
- [ ] Buka **Riwayat** dan tunjukkan aktivitas sendiri.
- [ ] Tunjukkan menu Laporan/Pengaturan/Akun pengguna tidak tersedia.
- [ ] Jelaskan keterbatasan layar Barang staf.

### 12.5 Referensi teknis

- Repositori: `mhdirrsl23/StockFlow`
- Backend: `laravel-api-tugas/`
- Android: `android/`
- Panduan pemasangan backend: `README.md`
- Kontrak API: `laravel-api-tugas/routes/api.php`
- Format respons: `{ success, message, data | errors }`

---

**Akhir dokumen.**
