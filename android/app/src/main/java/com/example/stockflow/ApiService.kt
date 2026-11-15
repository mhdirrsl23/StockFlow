package com.example.stockflow

import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    @POST("login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @POST("logout")
    suspend fun logout(): Response<ApiResponse<Any>>

    @GET("dashboard")
    suspend fun getDashboard(): Response<ApiResponse<DashboardData>>

    @GET("barang")
    suspend fun getBarang(@Query("search") search: String? = null, @Query("kategori") kategori: String? = null): Response<ApiResponse<List<BarangItem>>>

    @GET("barang/{id}")
    suspend fun getDetailBarang(@Path("id") id: Int): Response<ApiResponse<BarangItem>>

    @POST("barang")
    suspend fun storeBarang(@Body request: BarangRequest): Response<ApiResponse<BarangItem>>

    @PUT("barang/{id}")
    suspend fun updateBarang(@Path("id") id: Int, @Body request: BarangRequest): Response<ApiResponse<BarangItem>>

    @DELETE("barang/{id}")
    suspend fun deleteBarang(@Path("id") id: Int): Response<ApiResponse<Any>>

    @GET("batch-stok")
    suspend fun getBatchStok(@Query("barang_id") barangId: Int? = null, @Query("status") status: String? = null): Response<ApiResponse<List<BatchStokItem>>>

    @GET("batch-stok/{id}")
    suspend fun getDetailBatch(@Path("id") id: Int): Response<ApiResponse<BatchStokItem>>

    @POST("batch-stok")
    suspend fun storeBatchStok(@Body request: BatchStokRequest): Response<ApiResponse<BatchStokItem>>

    @POST("batch-stok/{id}/penanganan")
    suspend fun tanganiBatch(@Path("id") id: Int, @Body request: PenangananRequest): Response<ApiResponse<BatchStokItem>>

    @Multipart
    @POST("batch-stok/{id}/lampiran")
    suspend fun uploadLampiran(@Path("id") batchId: Int, @Part file: MultipartBody.Part): Response<ApiResponse<Any>>

    @POST("mutasi-stok/keluar")
    suspend fun storeStokKeluar(@Body request: StokKeluarRequest): Response<ApiResponse<Any>>

    @POST("mutasi-stok/penyesuaian")
    suspend fun storePenyesuaian(@Body request: PenyesuaianRequest): Response<ApiResponse<Any>>

    @GET("mutasi-stok")
    suspend fun getMutasiStok(): Response<ApiResponse<List<MutasiStokItem>>>

    @GET("riwayat-aktivitas")
    suspend fun getRiwayatAktivitas(): Response<ApiResponse<List<RiwayatAktivitasItem>>>

    @GET("notifikasi")
    suspend fun getNotifikasi(): Response<ApiResponse<List<NotifikasiItem>>>

    @GET("users")
    suspend fun getUsers(): Response<ApiResponse<List<UserItem>>>

    @POST("users")
    suspend fun storeUser(@Body request: UserRequest): Response<ApiResponse<UserItem>>

    @PUT("users/{id}")
    suspend fun updateUser(@Path("id") id: Int, @Body request: UserRequest): Response<ApiResponse<UserItem>>

    @GET("pengaturan")
    suspend fun getPengaturan(): Response<ApiResponse<List<PengaturanItem>>>

    @POST("pengaturan")
    suspend fun updatePengaturan(@Body items: Map<String, String>): Response<ApiResponse<Any>>
}