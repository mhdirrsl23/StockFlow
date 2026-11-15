package com.example.stockflow

import android.content.Intent
import android.os.Bundle
import android.text.InputType
import android.view.View
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {

    private lateinit var session: SessionManager
    private var showPassword = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)
        session = SessionManager(this)

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

        findViewById<View>(R.id.demo_pemilik).setOnClickListener {
            session.saveSession("demo-token", 1, "Budi Santoso", "pemilik", "pemilik")
            openMain("pemilik")
        }

        findViewById<View>(R.id.demo_staf).setOnClickListener {
            session.saveSession("demo-token", 2, "Rina Wulandari", "staf", "staf")
            openMain("staf")
        }
    }

    private fun loginApi(username: String, password: String) {
        lifecycleScope.launch {
            try {
                val res = RetrofitClient.getInstance(this@LoginActivity).login(LoginRequest(username, password))
                val body = res.body()
                if (res.isSuccessful && body?.status == true && body.token != null && body.user != null) {
                    val u = body.user
                    session.saveSession(body.token, u.id, u.nama, u.username, u.peran)
                    openMain(u.peran)
                } else {
                    Toast.makeText(this@LoginActivity, body?.message ?: "Username atau kata sandi salah", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                Toast.makeText(this@LoginActivity, "Gagal terhubung ke server: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun openMain(peran: String) {
        startActivity(Intent(this, MainActivity::class.java).putExtra("USER_ROLE", peran))
        finish()
    }
}