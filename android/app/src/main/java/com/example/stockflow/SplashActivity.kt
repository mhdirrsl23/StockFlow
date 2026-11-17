package com.example.stockflow

import android.app.ActivityOptions
import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.ViewGroup
import androidx.appcompat.app.AppCompatActivity

class SplashActivity : AppCompatActivity() {

    private val handler = Handler(Looper.getMainLooper())
    private val navigateRunnable = Runnable {
        // Sudah login → langsung beranda sesuai peran; belum → form login.
        val sesi = SessionManager(this)
        val intent = if (sesi.isLoggedIn()) {
            Intent(this, MainActivity::class.java).putExtra("USER_ROLE", sesi.getPeran())
        } else {
            Intent(this, LoginActivity::class.java)
        }
        val options = ActivityOptions.makeCustomAnimation(
            this,
            android.R.anim.fade_in,
            android.R.anim.fade_out
        )
        startActivity(intent, options.toBundle())
        finish()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_splash)

        val rootView = findViewById<ViewGroup>(android.R.id.content).getChildAt(0)
        rootView?.apply {
            alpha = 0f
            translationY = 24f
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(800L)
                .start()
        }

        handler.postDelayed(navigateRunnable, 2000L)
    }

    override fun onDestroy() {
        handler.removeCallbacks(navigateRunnable)
        super.onDestroy()
    }
}