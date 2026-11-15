package com.example.stockflow

import android.content.Context

class SessionManager(context: Context) {

    private val prefs = context.getSharedPreferences("stockflow_session", Context.MODE_PRIVATE)

    fun saveSession(token: String, userId: Int, nama: String, username: String, peran: String) {
        prefs.edit()
            .putString("TOKEN", token)
            .putInt("USER_ID", userId)
            .putString("NAMA", nama)
            .putString("USERNAME", username)
            .putString("PERAN", peran)
            .apply()
    }

    fun getToken(): String? = prefs.getString("TOKEN", null)
    fun getUserId(): Int = prefs.getInt("USER_ID", -1)
    fun getNama(): String = prefs.getString("NAMA", "") ?: ""
    fun getUsername(): String = prefs.getString("USERNAME", "") ?: ""
    fun getPeran(): String = prefs.getString("PERAN", "pemilik") ?: "pemilik"
    fun isLoggedIn(): Boolean = !getToken().isNullOrEmpty()
    fun clearSession() = prefs.edit().clear().apply()
}