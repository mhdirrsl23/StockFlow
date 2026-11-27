package com.example.stockflow







data class LoginRequest(val username: String, val password: String)


data class SesiData(val token: String? = null, val user: UserItem? = null)
data class LoginResponse(val success: Boolean, val message: String, val data: SesiData? = null)

data class ApiResponse<T>(
    val success: Boolean,
    val message: String,
    val data: T? = null,

    val errors: Map<String, List<String>>? = null,
)


data class Paginasi(
    val halaman: Int = 1,
    val per_halaman: Int = 15,
    val total_baris: Int = 0,
    val halaman_terakhir: Int = 1,
)
data class DaftarResponse<T>(val items: List<T> = emptyList(), val pagination: Paginasi? = null)


data class RingkasanDashboard(
    val total_barang_aktif: Int = 0,
    val total_batch_aktif: Int = 0,
    val stok_menipis: Int = 0,
    val batch_mendekati_kedaluwarsa: Int = 0,
    val batch_kedaluwarsa: Int = 0,
    val notifikasi_belum_dibaca: Int = 0,
)


data class ItemRingkas(
    val id: Int? = null,
    val barang_id: Int? = null,
    val batch_id: Int? = null,
    val kode_sku: String? = null,
    val nama: String? = null,
    val nama_barang: String? = null,
    val nomor_batch: String? = null,
    val satuan: String? = null,
    val stok_minimum: Int? = null,
    val total_stok_tersedia: Int? = null,
    val jumlah_sisa: Int? = null,
    val tanggal_kedaluwarsa: String? = null,
    val sisa_hari: Int? = null,
    val status: String? = null,
    val jenis: String? = null,
    val jumlah: Int? = null,
    val waktu: String? = null,
    val user_id: Int? = null,
    val barang: BarangRingkas? = null,
)

data class TransaksiRingkas(
    val id: Int = 0,
    val batch_id: Int? = null,
    val jenis: String = "",
    val jumlah: Int = 0,
    val waktu: String? = null,
    val user_id: Int? = null,
    val pengguna: PenggunaRingkas? = null,
)

data class PenggunaRingkas(val id: Int? = null, val nama: String? = null)

data class DashboardData(
    val ringkasan: RingkasanDashboard = RingkasanDashboard(),
    val stok_menipis: List<ItemRingkas> = emptyList(),
    val perlu_ditangani: List<ItemRingkas> = emptyList(),
    val mendekati_kedaluwarsa: List<ItemRingkas> = emptyList(),
    val rekomendasi_fefo: List<ItemRingkas> = emptyList(),
    val transaksi_terbaru: List<TransaksiRingkas> = emptyList(),
)


data class UserItem(
    val id: Int,
    val nama: String,
    val username: String,
    val peran: String,
    val aktif: Boolean = true,
)

data class UserRequest(
    val nama: String,
    val username: String,
    val password: String? = null,
)

data class AktifRequest(val aktif: Boolean)


data class UserListData(val items: List<UserItem> = emptyList())


data class BarangItem(
    val id: Int,
    val kode_sku: String,
    val nama: String,
    val kategori: String,
    val satuan: String,
    val stok_minimum: Int,
    val total_stok_tersedia: Int = 0,
    val status_stok: String? = null,
    val jumlah_batch_aktif: Int? = null,
    val created_by: Int? = null,
    val created_at: String? = null,
    val updated_at: String? = null,
    val batches: List<BatchStokItem> = emptyList(),
)

data class BarangRequest(
    val kode_sku: String,
    val nama: String,
    val kategori: String,
    val satuan: String,
    val stok_minimum: Int,
)

data class BulkDeleteRequest(val ids: List<Int>)
data class HasilHapus(val id: Int, val berhasil: Boolean, val pesan: String)
data class BulkDeleteResult(
    val total: Int = 0,
    val berhasil: Int = 0,
    val gagal: Int = 0,
    val hasil: List<HasilHapus> = emptyList(),
)


data class BatchStokItem(
    val id: Int,
    val barang_id: Int,
    val kode_sku: String? = null,
    val nama_barang: String? = null,
    val satuan: String? = null,
    val nama_pembuat: String? = null,
    val nomor_batch: String,
    val jumlah_awal: Int,
    val jumlah_sisa: Int,
    val tanggal_kedaluwarsa: String? = null,
    val status: String,
    val status_penanganan: String? = null,
    val catatan_penanganan: String? = null,
    val lampiran: List<LampiranItem> = emptyList(),
    val mutasi_terbaru: List<MutasiStokItem> = emptyList(),
    val sisa_hari: Long? = null,
    val created_at: String? = null,
    val updated_at: String? = null,
    val deleted_at: String? = null,
)


data class BatchStokRequest(
    val nomor_batch: String,
    val jumlah_awal: Int,
    val tanggal_kedaluwarsa: String,
)


data class BatchUpdateRequest(
    val nomor_batch: String? = null,
    val tanggal_kedaluwarsa: String? = null,
)


data class BarangRingkas(
    val id: Int = 0,
    val kode_sku: String = "",
    val nama: String = "",
    val kategori: String = "",
    val satuan: String = "",
    val stok_minimum: Int = 0,
)

data class BatchBarangData(
    val barang: BarangRingkas = BarangRingkas(),
    val items: List<BatchStokItem> = emptyList(),
    val rekomendasi_fefo: Int? = null,
)




data class TransaksiRequest(
    val jenis: String,
    val barang_id: Int,
    val jumlah: Int,
    val catatan: String? = null,
    val batch_id: Int? = null,
    val nomor_batch: String? = null,
    val tanggal_kedaluwarsa: String? = null,
    val waktu: String? = null,
)



data class PenangananRequest(
    val batch_id: Int,
    val status_penanganan: String,
    val jumlah: Int? = null,
    val catatan_penanganan: String? = null,
    val waktu: String? = null,
)


data class MutasiStokItem(
    val id: Int,
    val batch_id: Int,
    val jenis: String,
    val jumlah: Int,
    val catatan: String? = null,
    val user_id: Int,
    val waktu: String,
    val batch: ItemRingkas? = null,
    val barang: ItemRingkas? = null,
    val pengguna: PenggunaRingkas? = null,
)

data class RiwayatAktivitasItem(
    val id: Int,
    val user_id: Int? = null,
    val aksi: String,
    val entitas: String? = null,
    val entitas_id: Int? = null,
    val keterangan: String? = null,
    val waktu: String,
    val pengguna: PenggunaRingkas? = null,
)


data class NotifikasiItem(
    val id: Int,
    val jenis: String,
    val barang_id: Int? = null,
    val batch_id: Int? = null,
    val pesan: String? = null,
    val tanggal: String? = null,
    val ditampilkan_pada: String? = null,
)


data class PengaturanItem(
    val kunci: String,
    val nilai: String,
    val updated_at: String? = null,
)


data class LaporanData(
    val judul: String? = null,
    val judul_tabel: String? = null,
    val dibuat_pada: String? = null,
    val header: List<String> = emptyList(),
    val rows: List<List<Any?>> = emptyList(),
    val nama_berkas: String? = null,
)


data class LampiranItem(
    val id: Int,
    val batch_id: Int,
    val jenis: String,
    val path: String? = null,
    val created_at: String? = null,
)
