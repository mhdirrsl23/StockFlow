package com.example.stockflow

data class LoginRequest(val username: String, val password: String)
data class LoginResponse(val status: Boolean, val message: String, val token: String?, val user: UserItem?)
data class ApiResponse<T>(val status: Boolean, val message: String, val data: T?)

data class DashboardData(
    val total_barang: Int = 0,
    val total_stok: Int = 0,
    val stok_menipis: Int = 0,
    val mendekati_kedaluwarsa: Int = 0,
    val kedaluwarsa: Int = 0,
    val perlu_ditangani: Int = 0
)

data class UserItem(val id: Int, val nama: String, val username: String, val peran: String, val aktif: Int)
data class UserRequest(val nama: String, val username: String, val password: String?, val peran: String, val aktif: Int)

data class BarangItem(
    val id: Int,
    val kode_sku: String,
    val nama: String,
    val kategori: String,
    val satuan: String,
    val stok_minimum: Int,
    val total_stok_tersedia: Int = 0,
    val status_stok: String? = null,
    val created_by: Int? = null
)

data class BarangRequest(
    val kode_sku: String,
    val nama: String,
    val kategori: String,
    val satuan: String,
    val stok_minimum: Int
)

data class BatchStokItem(
    val id: Int,
    val barang_id: Int,
    val nomor_batch: String,
    val jumlah_awal: Int,
    val jumlah_sisa: Int,
    val tanggal_kedaluwarsa: String,
    val status: String,
    val status_penanganan: String? = null,
    val catatan_penanganan: String? = null,
    val barang: BarangItem? = null
)

data class BatchStokRequest(
    val barang_id: Int,
    val nomor_batch: String,
    val jumlah_awal: Int,
    val tanggal_kedaluwarsa: String
)

data class StokKeluarRequest(val barang_id: Int, val jumlah: Int, val catatan: String?)
data class PenyesuaianRequest(val batch_id: Int, val selisih: Int, val alasan: String)
data class PenangananRequest(val status_penanganan: String, val catatan_penanganan: String?)

data class MutasiStokItem(
    val id: Int,
    val batch_id: Int,
    val jenis: String,
    val jumlah: Int,
    val catatan: String?,
    val user_id: Int,
    val waktu: String
)

data class RiwayatAktivitasItem(
    val id: Int,
    val user_id: Int?,
    val aksi: String,
    val entitas: String?,
    val entitas_id: Int?,
    val keterangan: String?,
    val waktu: String
)

data class NotifikasiItem(
    val id: Int,
    val jenis: String,
    val barang_id: Int?,
    val batch_id: Int?,
    val pesan: String,
    val tanggal: String,
    val ditampilkan_pada: String?
)

data class PengaturanItem(val kunci: String, val nilai: String)