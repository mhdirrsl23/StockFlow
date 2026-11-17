package com.example.stockflow

import android.content.Intent
import android.os.Bundle
import android.text.InputType
import android.view.View
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {

    private lateinit var session: SessionManager
    private var showPassword = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)
        session = SessionManager(this)
        RetrofitClient.resetRedirectFlag()

        val etUser = findViewById<EditText>(R.id.et_username)
        val etPass = findViewById<EditText>(R.id.et_password)

        findViewById<View>(R.id.btn_toggle_password).setOnClickListener {
            showPassword = !showPassword
            etPass.inputType = InputType.TYPE_CLASS_TEXT or
                    if (showPassword) InputType.TYPE_TEXT_VARIATION_VISIBLE_PASSWORD else InputType.TYPE_TEXT_VARIATION_PASSWORD
            etPass.setSelection(etPass.text.length)
        }

        findViewById<View>(R.id.btn_masuk).setOnClickListener {
            val u = etUser.text.toString().trim()
            val p = etPass.text.toString().trim()
            if (u.isEmpty() || p.isEmpty()) {
                Toast.makeText(this, "Username dan kata sandi wajib diisi", Toast.LENGTH_SHORT).show()
            } else {
                loginApi(u, p)
            }
        }
    }

    private fun loginApi(username: String, password: String) {
        lifecycleScope.launch {
            try {
                val res = RetrofitClient.getInstance(this@LoginActivity).login(LoginRequest(username, password))
                val body = res.body()
                val sesi = body?.data
                if (res.isSuccessful && body?.success == true && sesi?.token != null && sesi.user != null) {
                    val u = sesi.user
                    session.saveSession(sesi.token, u.id, u.nama, u.username, u.peran)
                    openMain(u.peran)
                } else {
                    Toast.makeText(this@LoginActivity, uraiGagal(res), Toast.LENGTH_LONG).show()
                }
            } catch (e: Exception) {
                Toast.makeText(this@LoginActivity, "Gagal terhubung ke server: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    /**
     * Urai pesan gagal login: 401 kredensial salah, 422 kolom kosong, 429 throttle.
     * Isi JSON untuk status non-2xx berada di errorBody (PRD 9.5).
     */
    private fun uraiGagal(res: retrofit2.Response<LoginResponse>): String {
        val teks = res.errorBody()?.string()
        if (teks != null) {
            return runCatching {
                val obj = Gson().fromJson(teks, JsonObject::class.java)
                val pesan = obj.get("message")?.asString ?: "Username atau kata sandi salah"
                val errors = obj.getAsJsonObject("errors")
                val rincian = errors?.entrySet()?.take(2)?.joinToString("\n") { (kunci, nilai) ->
                    "• ${(nilai.asJsonArray.firstOrNull()?.asString ?: kunci)}"
                }
                if (rincian.isNullOrEmpty()) pesan else "$pesan\n$rincian"
            }.getOrDefault("Username atau kata sandi salah")
        }
        return "Username atau kata sandi salah"
    }

    private fun openMain(peran: String) {
        startActivity(Intent(this, MainActivity::class.java).putExtra("USER_ROLE", peran))
        finish()
    }
}