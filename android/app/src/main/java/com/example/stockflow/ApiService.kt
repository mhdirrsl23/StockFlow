package com.example.stockflow

import okhttp3.MultipartBody
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.*


interface ApiService {


    @POST("login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @POST("logout")
    suspend fun logout(): Response<ApiResponse<Any?>>

    @GET("me")
    suspend fun getMe(): Response<ApiResponse<UserItem>>


    @GET("dashboard")
    suspend fun getDashboard(): Response<ApiResponse<DashboardData>>


    @GET("products")
    suspend fun getBarang(
        @Query("q") search: String? = null,
        @Query("kategori") kategori: String? = null,
        @Query("per_page") perPage: Int = 15,
    ): Response<ApiResponse<DaftarResponse<BarangItem>>>

    @GET("products/{id}")
    suspend fun getDetailBarang(@Path("id") id: Int): Response<ApiResponse<BarangItem>>

    @POST("products")
    suspend fun storeBarang(@Body request: BarangRequest): Response<ApiResponse<BarangItem>>

    @PUT("products/{id}")
    suspend fun updateBarang(@Path("id") id: Int, @Body request: BarangRequest): Response<ApiResponse<BarangItem>>

    @DELETE("products/{id}")
    suspend fun deleteBarang(@Path("id") id: Int): Response<ApiResponse<Any?>>

    @POST("products/bulk-delete")
    suspend fun bulkDeleteBarang(@Body request: BulkDeleteRequest): Response<ApiResponse<BulkDeleteResult>>


    @GET("batches")
    suspend fun getBatchStok(
        @Query("barang_id") barangId: Int? = null,
        @Query("status") status: String? = null,
        @Query("dari") dari: String? = null,
        @Query("sampai") sampai: String? = null,
        @Query("per_page") perPage: Int = 20,
    ): Response<ApiResponse<DaftarResponse<BatchStokItem>>>

    @GET("batches/{id}")
    suspend fun getDetailBatch(@Path("id") id: Int): Response<ApiResponse<BatchStokItem>>

    @GET("products/{barangId}/batches")
    suspend fun getBatchPerBarang(@Path("barangId") barangId: Int): Response<ApiResponse<BatchBarangData>>


    @POST("products/{barangId}/batches")
    suspend fun storeBatchStok(@Path("barangId") barangId: Int, @Body request: BatchStokRequest): Response<ApiResponse<BatchStokItem>>

    @PUT("batches/{id}")
    suspend fun updateBatch(@Path("id") id: Int, @Body request: BatchUpdateRequest): Response<ApiResponse<BatchStokItem>>

    @DELETE("batches/{id}")
    suspend fun deleteBatch(@Path("id") id: Int): Response<ApiResponse<Any?>>

    @POST("batches/bulk-delete")
    suspend fun bulkDeleteBatch(@Body request: BulkDeleteRequest): Response<ApiResponse<BulkDeleteResult>>



    @Multipart
    @POST("batches/{batchId}/attachments")
    suspend fun uploadLampiran(
        @Path("batchId") batchId: Int,
        @Part lampiran: MultipartBody.Part,
        @Part jenis: MultipartBody.Part,
    ): Response<ApiResponse<LampiranItem>>

    @GET("attachments/{id}/file")
    suspend fun unduhLampiran(@Path("id") id: Int): Response<ResponseBody>

    @DELETE("attachments/{id}")
    suspend fun deleteLampiran(@Path("id") id: Int): Response<ApiResponse<Any?>>




    @GET("stock-transactions")
    suspend fun getMutasiStok(
        @Query("jenis") jenis: String? = null,
        @Query("barang_id") barangId: Int? = null,
        @Query("batch_id") batchId: Int? = null,
        @Query("dari") dari: String? = null,
        @Query("sampai") sampai: String? = null,
        @Query("per_page") perPage: Int = 20,
    ): Response<ApiResponse<DaftarResponse<MutasiStokItem>>>

    @POST("stock-transactions")
    suspend fun storeStokKeluar(@Body request: TransaksiRequest): Response<ApiResponse<Any?>>

    @POST("stock-transactions")
    suspend fun storePenyesuaian(@Body request: TransaksiRequest): Response<ApiResponse<Any?>>


    @POST("expiry-actions")
    suspend fun tanganiKedaluwarsa(@Body request: PenangananRequest): Response<ApiResponse<Any?>>


    @GET("notifications")
    suspend fun getNotifikasi(
        @Query("jenis") jenis: String? = null,
        @Query("belum_ditampilkan") belumDitampilkan: Boolean? = null,
        @Query("per_page") perPage: Int = 20,
    ): Response<ApiResponse<DaftarResponse<NotifikasiItem>>>

    @PATCH("notifications/{id}/read")
    suspend fun tandaiNotifikasiDibaca(@Path("id") id: Int): Response<ApiResponse<Any?>>

    @POST("notifications/read-all")
    suspend fun tandaiSemuaNotifikasiDibaca(): Response<ApiResponse<Any?>>



    @GET("reports/stock")
    suspend fun getLaporanStok(): Response<ApiResponse<LaporanData>>

    @GET("reports/transactions")
    suspend fun getLaporanTransaksi(): Response<ApiResponse<LaporanData>>

    @GET("reports/expiry")
    suspend fun getLaporanKedaluwarsa(): Response<ApiResponse<LaporanData>>

    @GET("reports/export")
    suspend fun unduhLaporan(
        @Query("jenis") jenis: String,
        @Query("format") format: String,
    ): Response<ResponseBody>


    @GET("riwayat-aktivitas")
    suspend fun getRiwayatAktivitas(
        @Query("aksi") aksi: String? = null,
        @Query("entitas") entitas: String? = null,
        @Query("dari") dari: String? = null,
        @Query("sampai") sampai: String? = null,
        @Query("per_page") perPage: Int = 20,
    ): Response<ApiResponse<DaftarResponse<RiwayatAktivitasItem>>>


    @GET("users")
    suspend fun getUsers(): Response<ApiResponse<UserListData>>

    @POST("users")
    suspend fun storeUser(@Body request: UserRequest): Response<ApiResponse<UserItem>>

    @PUT("users/{id}")
    suspend fun updateUser(@Path("id") id: Int, @Body request: UserRequest): Response<ApiResponse<UserItem>>

    @PATCH("users/{id}/aktif")
    suspend fun ubahAkunAktif(@Path("id") id: Int, @Body request: AktifRequest): Response<ApiResponse<UserItem>>

    @DELETE("users/{id}")
    suspend fun deleteUser(@Path("id") id: Int): Response<ApiResponse<Any?>>


    @GET("pengaturan")
    suspend fun getPengaturan(): Response<ApiResponse<List<PengaturanItem>>>



    @PUT("pengaturan")
    suspend fun updatePengaturan(@Body items: Map<String, Int>): Response<ApiResponse<List<PengaturanItem>>>
}
