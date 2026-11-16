package com.example.stockflow

import android.app.ActivityOptions
import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.ViewGroup
import androidx.appcompat.app.AppCompatActivity

class SplashIntroActivity : AppCompatActivity() {

    private val handler = Handler(Looper.getMainLooper())
    private val navigateRunnable = Runnable {
        val intent = Intent(this, SplashActivity::class.java)
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
        setContentView(R.layout.activity_splash_intro)

        val rootView = findViewById<ViewGroup>(android.R.id.content).getChildAt(0)
        rootView?.apply {
            alpha = 0f
            animate()
                .alpha(1f)
                .setDuration(600L)
                .start()
        }

        handler.postDelayed(navigateRunnable, 1500L)
    }

    override fun onDestroy() {
        handler.removeCallbacks(navigateRunnable)
        super.onDestroy()
    }
}