package com.example.stockflow

import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

object RetrofitClient {


    private const val BASE_URL = "https://colony-reorder-prepay.ngrok-free.dev/api/"


    private val redirect401Berjalan = AtomicBoolean(false)

    fun resetRedirectFlag() {
        redirect401Berjalan.set(false)
    }

    fun getInstance(context: Context): ApiService {
        val session = SessionManager(context)
        val logging = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BODY }

        val client = OkHttpClient.Builder()
            .addInterceptor { chain ->
                val req = chain.request().newBuilder().header("Accept", "application/json")
                session.getToken()?.takeIf { it.isNotEmpty() }?.let { req.header("Authorization", "Bearer $it") }
                val res = chain.proceed(req.build())




                val jalur = res.request.url.encodedPath
                if (res.code == 401 &&
                    !jalur.endsWith("/login") &&
                    !session.getToken().isNullOrEmpty() &&
                    redirect401Berjalan.compareAndSet(false, true)
                ) {
                    session.clearSession()
                    Handler(Looper.getMainLooper()).post {
                        context.startActivity(
                            Intent(context, LoginActivity::class.java).apply {
                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
                            },
                        )
                    }
                }
                res
            }
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .build()

        return Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }
}
